<?php

namespace App\Http\Controllers;

use App\Models\NotificationLog;
use App\Models\RepairRequest;
use App\Models\UserNotification;
use App\Models\VendorProfile;
use App\Services\MultiChannelNotificationService;
use App\Services\SmsService;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R14: Automated Multi-Channel BulkSMS, Email & In-App Notification Engine
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Sprint: Sprint 3
 * Architectural Layer: Controller (VendorNotificationController)
 * Models Touched: VendorProfile, NotificationLog, UserNotification
 * Views Rendered: vendor/alert-settings.blade.php, notifications/index.blade.php
 * ============================================================================
 */
class VendorNotificationController extends Controller
{
    /**
     * Vendor Multi-Channel Alert Settings & Gateway Status Console.
     * Returns HTTP 200 for approved vendors AND Admin previewers.
     * Route: GET /vendor/alert-settings
     */
    public function settings(Request $request, SmsService $smsService)
    {
        $user = $request->user();
        $vendorProfile = $user
            ? VendorProfile::with('shops')->where('user_id', $user->id)->first()
            : null;

        // Allow Admin preview smoothly (preserves SprintOneDemoReadinessTest GET /vendor/alert-settings == 200)
        if (!$vendorProfile && $user?->role === 'admin') {
            $vendorProfile = VendorProfile::with('shops')->first();
        }

        $gatewayStatus = $smsService->getCreditBalance();

        $recentLogsQuery = NotificationLog::query()->orderByDesc('id');
        if ($user && $user->role !== 'admin') {
            $phoneDigits = $smsService->normalizeBangladeshiPhone((string) ($vendorProfile?->alert_phone ?: $vendorProfile?->phone ?: $user->phone ?: ''));
            $email = (string) ($user->email ?? '');
            $recentLogsQuery->where(function ($q) use ($user, $email, $phoneDigits) {
                $q->where('recipient', 'ILIKE', "user:{$user->id}%")
                  ->when($email !== '', fn ($eq) => $eq->orWhere('recipient', $email))
                  ->when($phoneDigits !== '', fn ($pq) => $pq->orWhere('recipient', 'ILIKE', "%{$phoneDigits}%"));
            });
        }

        $recentLogs = $recentLogsQuery->limit(20)->get();

        return view('vendor.alert-settings', compact('vendorProfile', 'gatewayStatus', 'recentLogs'));
    }

    /**
     * Persist vendor multi-channel notification preferences & alert phone number.
     * Route: POST /vendor/alert-settings
     */
    public function updateSettings(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login');
        }

        $vendorProfile = VendorProfile::where('user_id', $user->id)->first();
        if (!$vendorProfile && $user->role === 'admin') {
            $vendorProfile = VendorProfile::first();
        }

        if (!$vendorProfile) {
            return redirect()->back()->with('error', 'No workshop profile found to attach notification settings.');
        }

        $validated = $request->validate([
            'alert_phone' => 'nullable|string|max:25',
            'notify_sms_enabled' => 'nullable|boolean',
            'notify_email_enabled' => 'nullable|boolean',
            'notify_in_app_enabled' => 'nullable|boolean',
            'notify_new_requests' => 'nullable|boolean',
            'notify_bid_updates' => 'nullable|boolean',
            'notify_payout_updates' => 'nullable|boolean',
        ]);

        $vendorProfile->update([
            'alert_phone' => !empty($validated['alert_phone']) ? trim($validated['alert_phone']) : null,
            'notify_sms_enabled' => $request->boolean('notify_sms_enabled'),
            'notify_email_enabled' => $request->boolean('notify_email_enabled'),
            'notify_in_app_enabled' => $request->boolean('notify_in_app_enabled'),
            'notify_new_requests' => $request->boolean('notify_new_requests'),
            'notify_bid_updates' => $request->boolean('notify_bid_updates'),
            'notify_payout_updates' => $request->boolean('notify_payout_updates'),
        ]);

        return redirect()
            ->route('vendor.alert-settings')
            ->with('success', 'Multi-channel notification preferences saved.');
    }

    /**
     * Trigger an instant diagnostic test alert across SMS, Email, and In-App Bell.
     * Route: POST /vendor/alert-settings/test
     */
    public function sendTestAlert(Request $request, MultiChannelNotificationService $notifier)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login');
        }

        $vendorProfile = VendorProfile::where('user_id', $user->id)->first();
        $overridePhone = $request->input('test_phone')
            ?: ($vendorProfile?->alert_phone ?: ($vendorProfile?->phone ?: $user->phone));

        $result = $notifier->notifyUser(
            user: $user,
            eventType: 'VENDOR_DIAGNOSTIC_TEST',
            title: 'Multi-Channel Diagnostic Alert Verified',
            message: 'BulkSMS BD, Transactional Email, and Meramot In-App Bell dispatch pipelines responded cleanly.',
            actionUrl: '/vendor/alert-settings',
            severity: 'success',
            sendEmail: true,
            sendSms: true,
            overridePhone: $overridePhone
        );

        $smsMode = !empty($result['sms']['simulated']) ? 'Sandbox Simulated SMS' : 'Live BulkSMS BD';

        return redirect()
            ->route('vendor.alert-settings')
            ->with('success', "Test alert dispatched! In-App Bell + Email + {$smsMode} logged to audit trail.");
    }

    /**
     * Unified In-App & Multi-Channel Notification Center for all authenticated users.
     * Route: GET /notifications
     */
    public function notificationCenter(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login');
        }

        if ($request->boolean('mark_all_read')) {
            UserNotification::where('user_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true, 'read_at' => now()]);
        }

        $channelFilter = strtoupper((string) $request->query('filter', 'ALL'));
        $notificationsQuery = UserNotification::where('user_id', $user->id)->orderByDesc('id');
        if ($channelFilter === 'UNREAD') {
            $notificationsQuery->where('is_read', false);
        }

        $notifications = $notificationsQuery->paginate(15)->withQueryString();
        $unreadCount = UserNotification::where('user_id', $user->id)->where('is_read', false)->count();

        return view('notifications.index', compact('notifications', 'unreadCount', 'channelFilter'));
    }

    /**
     * Dispatch SMS, Email & In-App alerts when a new repair bidding opportunity is published.
     * Route: POST /api/requests/{requestId}/notify-vendors
     */
    public function dispatchBiddingAlert(
        Request $request,
        $repairRequestId,
        MultiChannelNotificationService $notifier
    ) {
        $repairRequest = RepairRequest::find($repairRequestId);
        if (!$repairRequest) {
            return response()->json([
                'status' => 'error',
                'message' => 'Repair request not found.',
            ], 404);
        }

        $dispatchedCount = $notifier->notifyVendorsOfNewRequest($repairRequest);

        return response()->json([
            'status' => 'success',
            'message' => "Multi-channel alerts dispatched to {$dispatchedCount} matching workshop(s).",
            'repair_request_id' => (int) $repairRequest->id,
            'vendors_notified' => $dispatchedCount,
        ]);
    }
}

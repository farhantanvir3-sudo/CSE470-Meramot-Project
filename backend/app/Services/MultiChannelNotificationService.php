<?php

namespace App\Services;

use App\Models\RepairRequest;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * Feature R14: Multi-Channel Marketplace Notification Dispatcher
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Dispatches synchronized alerts across:
 *   1. In-App Notification Bell (`user_notifications` + `notification_logs[IN_APP]`)
 *   2. Transactional Email (`GmailApiService` -> `notification_logs[EMAIL]`)
 *   3. BulkSMS BD Gateway (`SmsService` -> `notification_logs[SMS]`)
 * Automatically simulates Email/SMS for synthetic demo accounts so live carrier
 * credits and Gmail reputation are never wasted during testing or demos.
 * ============================================================================
 */
class MultiChannelNotificationService
{
    public function __construct(
        protected SmsService $smsService,
        protected GmailApiService $gmailService
    ) {
    }

    public function isSyntheticDemoEmail(?string $email): bool
    {
        $lower = strtolower(trim((string) $email));
        if ($lower === '') {
            return true;
        }

        foreach (['@meramot.demo', '@meramotbd.com', '@example.com', '@example.org', '@test.local'] as $suffix) {
            if (str_ends_with($lower, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Dispatch a unified multi-channel alert to a specific user honoring preferences.
     */
    public function notifyUser(
        User $user,
        string $eventType,
        string $title,
        string $message,
        ?string $actionUrl = null,
        ?int $repairRequestId = null,
        ?int $deliveryJobId = null,
        string $severity = 'info',
        bool $sendEmail = true,
        bool $sendSms = true,
        ?string $overridePhone = null
    ): array {
        $vendorProfile = $user->role === 'vendor'
            ? VendorProfile::where('user_id', $user->id)->first()
            : null;

        $inAppEnabled = $vendorProfile ? (bool) ($vendorProfile->notify_in_app_enabled ?? true) : true;
        $emailEnabled = $sendEmail && ($vendorProfile ? (bool) ($vendorProfile->notify_email_enabled ?? true) : true);
        $smsEnabled = $sendSms && ($vendorProfile ? (bool) ($vendorProfile->notify_sms_enabled ?? true) : true);

        $results = [
            'in_app' => false,
            'email' => ['sent' => false, 'simulated' => false],
            'sms' => ['sent' => false, 'simulated' => false],
        ];

        // 1. In-App Notification Bell + Audit Log
        if ($inAppEnabled) {
            try {
                if (Schema::hasTable('user_notifications')) {
                    UserNotification::create([
                        'user_id' => $user->id,
                        'type' => $eventType,
                        'severity' => $severity,
                        'title' => mb_substr($title, 0, 180),
                        'message' => mb_substr($message, 0, 900),
                        'action_url' => $actionUrl,
                        'is_read' => false,
                    ]);
                }

                $this->recordLogRow(
                    channel: 'IN_APP',
                    recipient: 'user:' . $user->id . ' (' . ($user->email ?? $user->name) . ')',
                    subject: $title,
                    body: $message,
                    provider: 'MeramotBell',
                    providerRef: 'INAPP-' . strtoupper(substr(md5($user->id . $eventType . microtime(true)), 0, 10)),
                    status: 'SENT',
                    repairRequestId: $repairRequestId,
                    deliveryJobId: $deliveryJobId
                );

                $results['in_app'] = true;
            } catch (\Throwable $e) {
                Log::warning('[MultiChannelNotificationService] In-App notification failed: ' . $e->getMessage());
            }
        }

        // 2. Email Channel
        $email = trim((string) ($user->email ?? ''));
        if ($emailEnabled && $email !== '') {
            if (app()->environment('testing') || $this->isSyntheticDemoEmail($email)) {
                $simRef = 'SIM-EMAIL-' . strtoupper(substr(md5($email . $eventType . microtime(true)), 0, 10));
                $this->recordLogRow(
                    channel: 'EMAIL',
                    recipient: $email,
                    subject: 'Meramot — ' . $title,
                    body: $message,
                    provider: 'GmailAPI (Simulated)',
                    providerRef: $simRef,
                    status: 'SENT',
                    repairRequestId: $repairRequestId,
                    deliveryJobId: $deliveryJobId
                );
                $results['email'] = ['sent' => true, 'simulated' => true, 'provider_ref' => $simRef];
            } else {
                $fullUrl = $actionUrl
                    ? (str_starts_with($actionUrl, 'http')
                        ? $actionUrl
                        : rtrim((string) config('app.url', 'http://127.0.0.1:8000'), '/') . '/' . ltrim($actionUrl, '/'))
                    : null;

                $ctaHtml = $fullUrl
                    ? '<div style="margin: 22px 0; text-align: center;"><a href="' . e($fullUrl) . '" style="background-color: #214c34; color: #ffffff; padding: 11px 24px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;">Open in Meramot</a></div>'
                    : '';

                $html = '
                    <div style="font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #D7E2D2; border-radius: 16px; background: #ffffff;">
                        <h2 style="color: #214c34; margin-top: 0;">' . e($title) . '</h2>
                        <p>Hello <strong>' . e($user->name ?? 'Meramot Member') . '</strong>,</p>
                        <p>' . nl2br(e($message)) . '</p>
                        ' . $ctaHtml . '
                        <p style="margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;">Automated Meramot Marketplace Notification</p>
                    </div>
                ';

                $mailResp = $this->gmailService->sendEmail(
                    $email,
                    'Meramot — ' . $title,
                    $html,
                    $repairRequestId,
                    $deliveryJobId
                );
                $results['email'] = [
                    'sent' => (bool) ($mailResp['ok'] ?? false),
                    'simulated' => false,
                    'provider_ref' => $mailResp['message_id'] ?? null,
                ];
            }
        }

        // 3. SMS Channel (BulkSMS BD with automatic sandbox protection for demo users/numbers)
        $targetPhone = trim((string) ($overridePhone ?? ($vendorProfile->alert_phone ?? null) ?? ($user->phone ?? '')));
        if ($smsEnabled && $targetPhone !== '') {
            $forceSim = $this->isSyntheticDemoEmail($email);
            $smsBody = 'Meramot: ' . mb_substr($title . ' - ' . $message, 0, 145);
            $smsResp = $this->smsService->sendSms(
                $targetPhone,
                $smsBody,
                $eventType,
                $repairRequestId,
                $deliveryJobId,
                $forceSim
            );
            $results['sms'] = $smsResp;
        }

        return $results;
    }

    /**
     * Feature R14: Broadcast a newly created repair request to matching approved workshops.
     */
    public function notifyVendorsOfNewRequest(RepairRequest $repairRequest, int $maxVendors = 5): int
    {
        try {
            $query = VendorProfile::with(['user', 'shops'])
                ->whereRaw("LOWER(COALESCE(verification_status, '')) = 'approved'")
                ->where(function ($q) {
                    $q->whereNull('notify_new_requests')->orWhere('notify_new_requests', true);
                });

            // Direct workshop target gets exclusive priority alert
            if (!empty($repairRequest->requested_shop_id)) {
                $query->whereHas('shops', function ($sq) use ($repairRequest) {
                    $sq->where('id', (int) $repairRequest->requested_shop_id);
                });
            } else {
                $addressText = strtolower(trim((string) ($repairRequest->pickup_address ?? '')));
                foreach (['dhaka', 'chattogram', 'sylhet', 'rajshahi', 'khulna', 'barishal', 'rangpur', 'mymensingh'] as $knownCity) {
                    if (str_contains($addressText, $knownCity)) {
                        $matchedInCity = (clone $query)
                            ->where(function ($cq) use ($knownCity) {
                                $cq->whereRaw('LOWER(COALESCE(city, \'\')) = ?', [$knownCity])
                                    ->orWhereHas('shops', fn ($sq) => $sq->whereRaw('LOWER(COALESCE(city, \'\')) = ?', [$knownCity]));
                            })
                            ->count();

                        if ($matchedInCity > 0) {
                            $query->where(function ($cq) use ($knownCity) {
                                $cq->whereRaw('LOWER(COALESCE(city, \'\')) = ?', [$knownCity])
                                    ->orWhereHas('shops', fn ($sq) => $sq->whereRaw('LOWER(COALESCE(city, \'\')) = ?', [$knownCity]));
                            });
                        }
                        break;
                    }
                }
            }

            $vendors = $query->orderByDesc('rating')->limit($maxVendors)->get();
            $dispatched = 0;

            $deviceSummary = trim(($repairRequest->brand ? $repairRequest->brand . ' ' : '') . ($repairRequest->model_name ?? $repairRequest->device_type ?? 'Device'));
            $areaText = $repairRequest->pickup_address ?: 'Dhaka';
            $refCode = $repairRequest->id;

            foreach ($vendors as $vendor) {
                if (!$vendor->user) {
                    continue;
                }

                $this->notifyUser(
                    user: $vendor->user,
                    eventType: 'NEW_REPAIR_REQUEST',
                    title: "New Repair Lead #{$refCode}",
                    message: "{$deviceSummary} ({$repairRequest->device_type}) at {$areaText} is open for estimates. Review AI fault triage and submit your quote.",
                    actionUrl: "/vendor/request-details/{$repairRequest->id}",
                    repairRequestId: $repairRequest->id,
                    severity: 'info',
                    overridePhone: $vendor->alert_phone ?: $vendor->phone
                );
                $dispatched++;
            }

            return $dispatched;
        } catch (\Throwable $e) {
            Log::warning('[MultiChannelNotificationService] notifyVendorsOfNewRequest failed: ' . $e->getMessage());
            return 0;
        }
    }

    private function recordLogRow(
        string $channel,
        string $recipient,
        string $subject,
        string $body,
        string $provider,
        ?string $providerRef,
        string $status,
        ?int $repairRequestId = null,
        ?int $deliveryJobId = null
    ): void {
        try {
            if (!Schema::hasTable('notification_logs')) {
                return;
            }

            $safeStatus = in_array($status, ['QUEUED', 'SENT', 'FAILED'], true) ? $status : 'SENT';

            DB::table('notification_logs')->insert([
                'repair_request_id' => $repairRequestId,
                'delivery_job_id' => $deliveryJobId,
                'channel' => $channel,
                'recipient' => mb_substr($recipient, 0, 190),
                'subject' => mb_substr($subject, 0, 190),
                'body' => mb_substr($body, 0, 800),
                'provider' => $provider,
                'provider_ref' => $providerRef,
                'status' => $safeStatus,
                'sent_at' => $safeStatus === 'SENT' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[MultiChannelNotificationService] Could not insert notification_logs row: ' . $e->getMessage());
        }
    }
}

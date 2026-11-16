<?php

namespace App\Http\Controllers;

use App\Models\DeliveryJob;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\UserNotification;
use App\Models\VendorProfile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * STATUS TRANSITION CONTROLLER (Supporting Feature R7 & Dual-Quote Flow)
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Merged Lifecycle: Farhan Tanvir [22299179]
 *   - Supports Path A (Marketplace Quote 1 Bid Acceptance -> ASSIGNED + RepairJob)
 *   - Supports Path B (Direct Order Vendor Accept/Decline from PENDING)
 *   - Supports Quote 2 (Bench Diagnosis Approve -> REPAIRING / Decline -> RETURN_SCHEDULED)
 * ============================================================================
 */
class StatusTransitionController extends Controller
{
    /**
     * Update repair request status with state-machine validation.
     * Route: POST /requests/{id}/update-status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:PENDING,BIDDING,ASSIGNED,PICKUP_SCHEDULED,PICKED_UP,AT_SHOP,DIAGNOSING,WAITING_APPROVAL,REPAIRING,RETURN_SCHEDULED,RETURNING,RETURNED_TO_CUSTOMER,COMPLETED,CANCELLED,REJECTED',
            'notes' => 'nullable|string|max:500',
        ]);

        $repairRequest = RepairRequest::with('repairJob')->findOrFail($id);

        if (
            $request->user()
            && $request->user()->role === 'customer'
            && (int) $repairRequest->customer_id !== (int) $request->user()->id
        ) {
            abort(403, 'You are not authorized to modify another customer\'s repair request.');
        }

        $previousStatus = $repairRequest->status;

        if ($repairRequest->transitionTo($validated['status'])) {
            if ($repairRequest->repairJob && in_array($validated['status'], [
                'PICKUP_SCHEDULED', 'PICKED_UP', 'AT_SHOP', 'DIAGNOSING',
                'WAITING_APPROVAL', 'REPAIRING', 'RETURNING', 'COMPLETED', 'CANCELLED'
            ], true)) {
                $jobUpdates = ['status' => $validated['status']];
                if ($validated['status'] === 'COMPLETED' && !$repairRequest->repairJob->completed_at) {
                    $jobUpdates['completed_at'] = now();
                }
                $repairRequest->repairJob->update($jobUpdates);

                if ($validated['status'] === 'COMPLETED') {
                    VendorBranchController::settleCompletedJobFinancials(
                        $repairRequest->repairJob->fresh(['repairRequest', 'shop.vendorProfile'])
                    );
                }
            } elseif ($repairRequest->repairJob && in_array($validated['status'], ['RETURN_SCHEDULED', 'RETURNED_TO_CUSTOMER'], true)) {
                $repairRequest->repairJob->update([
                    'status' => $validated['status'] === 'RETURN_SCHEDULED' ? 'READY_TO_RETURN' : 'COMPLETED',
                ]);
            }

            if ($validated['status'] === 'CANCELLED') {
                $repairRequest->bids()->where('status', 'ACTIVE')->update(['status' => 'EXPIRED']);
                DeliveryJob::where('repair_request_id', $repairRequest->id)
                    ->whereNotIn('status', ['DELIVERED', 'CANCELLED'])
                    ->update(['status' => 'CANCELLED', 'cancellation_reason' => $validated['notes'] ?? 'Cancelled by customer']);
            }

            Log::info('Repair request status updated', [
                'request_id' => $repairRequest->id,
                'old_status' => $previousStatus,
                'new_status' => $validated['status'],
                'notes' => $validated['notes'] ?? null,
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Status updated successfully',
                'new_status' => $validated['status'],
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid status transition from ' . $previousStatus . ' to ' . $validated['status'],
        ], 400);
    }

    /**
     * Get available transitions for current status.
     * Route: GET /requests/{id}/available-transitions
     */
    public function getAvailableTransitions($id)
    {
        $repairRequest = RepairRequest::findOrFail($id);
        $currentStatus = strtoupper($repairRequest->status ?? 'PENDING');

        return response()->json([
            'current_status' => $currentStatus,
            'available_transitions' => RepairRequest::$validTransitions[$currentStatus] ?? [],
        ]);
    }

    /**
     * Accept Quote 1 (Marketplace Bid) -> transition to ASSIGNED + create RepairJob + DeliveryJob (if pickup_dropoff).
     * Route: POST /requests/{id}/accept-bid
     */
    public function acceptBid(Request $request, $id)
    {
        $validated = $request->validate([
            'bid_id' => 'required|integer|exists:bids,id',
        ]);

        $repairRequest = RepairRequest::findOrFail($id);

        if (
            $request->user()
            && $request->user()->role === 'customer'
            && (int) $repairRequest->customer_id !== (int) $request->user()->id
        ) {
            abort(403, 'You are not authorized to accept bids on another customer\'s repair request.');
        }

        if (!in_array(strtoupper((string) $repairRequest->status), ['PENDING', 'BIDDING'], true)) {
            return redirect()
                ->route('requests.track', $repairRequest->id)
                ->with('error', 'A bid has already been accepted or this request is no longer open for bidding.');
        }

        $bid = $repairRequest->bids()->with('shop.vendorProfile.user')->findOrFail($validated['bid_id']);

        DB::transaction(function () use ($repairRequest, $bid) {
            $shopAddress = $bid->shop?->address ?: (($bid->shop?->area ?: 'Banani') . ', ' . ($bid->shop?->city ?: 'Dhaka'));

            $repairRequest->update([
                'selected_bid_id' => $bid->id,
                'requested_shop_id' => $repairRequest->requested_shop_id ?? $bid->shop_id,
                'quoted_final_amount' => $bid->proposed_cost,
                'dropoff_address' => $repairRequest->dropoff_address ?: $shopAddress,
                'approved_at' => now(),
            ]);

            $repairRequest->transitionTo(RepairRequest::STATUS_ASSIGNED);

            $bid->update(['status' => 'ACCEPTED']);

            $repairRequest->bids()
                ->where('id', '!=', $bid->id)
                ->where('status', 'ACTIVE')
                ->update(['status' => 'DECLINED']);

            $job = RepairJob::updateOrCreate(
                ['repair_request_id' => $repairRequest->id],
                [
                    'shop_id' => $bid->shop_id,
                    'accepted_bid_id' => $bid->id,
                    'status' => 'CREATED',
                    'parts_cost' => $bid->parts_cost,
                    'labor_cost' => $bid->labor_cost,
                    'parts_items' => $bid->resolvedPartsItems(),
                    'has_warranty' => $bid->hasWarrantyCoverage(),
                    'warranty_days' => $bid->resolvedWarrantyDays(),
                    'final_quoted_amount' => $bid->proposed_cost,
                    'final_quote_items' => $bid->buildFinalQuoteItems(),
                ]
            );

            $requiresCourier = $repairRequest->requiresCourierLogistics();
            if ($requiresCourier) {
                $this->ensureCourierDeliveryJob($repairRequest->fresh(), $job, 'TO_SHOP');
            }
        });

        $requiresCourier = $repairRequest->requiresCourierLogistics();

        if ($bid->shop?->vendorProfile?->user) {
            app(\App\Services\MultiChannelNotificationService::class)->notifyUser(
                user: $bid->shop->vendorProfile->user,
                eventType: 'BID_ACCEPTED',
                title: "Your Quote Won Request #{$repairRequest->id}",
                message: $requiresCourier
                    ? "Customer accepted your ৳" . number_format((float) $bid->proposed_cost, 0) . " estimate for {$repairRequest->title}. Courier pickup has been queued."
                    : "Customer accepted your ৳" . number_format((float) $bid->proposed_cost, 0) . " estimate for {$repairRequest->title} (In-Shop Walk-In). Customer will visit the workshop directly.",
                actionUrl: '/vendor/dashboard?tab=assigned_jobs',
                repairRequestId: $repairRequest->id,
                severity: 'info'
            );
        }

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with(
                'success',
                $requiresCourier
                    ? 'Initial estimate accepted. The workshop has been assigned and courier pickup is ready.'
                    : 'Initial estimate accepted. Bring your device directly to the workshop and pay securely via Meramot.'
            );
    }

    /**
     * Vendor responds to a Direct-Order cart booking (`PENDING` -> `ASSIGNED` or `REJECTED`).
     * Route: POST /vendor/direct-orders/{id}/respond
     */
    public function respondToDirectOrder(Request $request, $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:accept,decline',
            'reason' => 'nullable|string|max:500',
        ]);

        $repairRequest = RepairRequest::with(['repairJob', 'payments', 'requestedShop'])->findOrFail($id);

        if ($request->user() && $request->user()->role === 'vendor') {
            $vp = VendorProfile::where('user_id', $request->user()->id)->first();
            if (
                !$vp
                || !$repairRequest->requestedShop
                || (int) $repairRequest->requestedShop->vendor_profile_id !== (int) $vp->id
            ) {
                abort(403, 'You are not authorized to respond to direct orders for another workshop.');
            }
        }

        if ($validated['decision'] === 'accept') {
            $repairRequest->transitionTo(RepairRequest::STATUS_ASSIGNED);
            $repairRequest->update(['approved_at' => now()]);
            if ($repairRequest->repairJob) {
                $repairRequest->repairJob->update([
                    'status' => 'CREATED',
                    'started_at' => now(),
                ]);
                $job = $repairRequest->repairJob;
            } elseif ($repairRequest->requested_shop_id) {
                $job = RepairJob::create([
                    'repair_request_id' => $repairRequest->id,
                    'shop_id' => $repairRequest->requested_shop_id,
                    'status' => 'CREATED',
                    'final_quoted_amount' => $repairRequest->quoted_final_amount,
                    'started_at' => now(),
                ]);
            } else {
                $job = null;
            }

            $freshRequest = $repairRequest->fresh();
            $needsCourier = $freshRequest ? $freshRequest->requiresCourierLogistics() : $repairRequest->requiresCourierLogistics();

            if ($job && $needsCourier) {
                $this->ensureCourierDeliveryJob($freshRequest ?? $repairRequest, $job, 'TO_SHOP');
            }

            if ($repairRequest->customer_id && Schema::hasTable('user_notifications')) {
                $shopTitle = $repairRequest->requestedShop?->name ?? 'The workshop';
                UserNotification::create([
                    'user_id' => $repairRequest->customer_id,
                    'type' => 'direct_order_accepted',
                    'severity' => 'info',
                    'title' => "Order #{$repairRequest->id} Confirmed",
                    'message' => $needsCourier
                        ? "{$shopTitle} confirmed your booking and queued doorstep courier pickup."
                        : "{$shopTitle} confirmed your in-shop walk-in booking. Bring your device to the workshop when ready.",
                    'action_url' => '/requests/' . $repairRequest->id . '/track',
                    'is_read' => false,
                ]);
            }

            return back()->with(
                'success',
                $needsCourier
                    ? "Order #{$repairRequest->id} confirmed and queued for courier pickup."
                    : "In-shop walk-in order #{$repairRequest->id} confirmed."
            );
        }

        // Vendor declined: transition PENDING -> REJECTED & auto-refund paid/authorized transactions
        $repairRequest->transitionTo(RepairRequest::STATUS_REJECTED);
        $repairRequest->update(['rejected_at' => now()]);
        if ($repairRequest->repairJob) {
            $repairRequest->repairJob->update(['status' => 'CANCELLED']);
        }
        $repairRequest->payments()
            ->whereIn('status', ['PENDING', 'AUTHORIZED', 'PAID'])
            ->update(['status' => 'REFUNDED']);
        DeliveryJob::where('repair_request_id', $repairRequest->id)
            ->whereNotIn('status', ['DELIVERED', 'CANCELLED'])
            ->update(['status' => 'CANCELLED', 'cancellation_reason' => 'Declined by workshop']);

        if ($repairRequest->customer_id && Schema::hasTable('user_notifications')) {
            $reasonSuffix = !empty($validated['reason'])
                ? ' Reason: ' . trim((string) $validated['reason']) . '.'
                : '';

            UserNotification::create([
                'user_id' => $repairRequest->customer_id,
                'type' => 'direct_order_declined',
                'severity' => 'warning',
                'title' => "Direct Order #{$repairRequest->id} Declined",
                'message' => ($repairRequest->requestedShop?->name ?? 'The workshop') . " could not accept this booking.{$reasonSuffix} Any payment hold has been refunded.",
                'action_url' => '/requests/' . $repairRequest->id . '/track',
                'is_read' => false,
            ]);
        }

        return back()->with('success', "Direct Order #{$repairRequest->id} declined and any payment hold refunded.");
    }

    /**
     * Customer responds to Quote 2 (Final Bench Diagnosis during WAITING_APPROVAL).
     * Approve -> REPAIRING | Decline -> RETURN_SCHEDULED (device returned to customer).
     * Route: POST /requests/{id}/respond-final-quote
     */
    public function respondToFinalQuote(Request $request, $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:approve,accept,decline',
        ]);

        $repairRequest = RepairRequest::with('repairJob.shop.vendorProfile.user')->findOrFail($id);

        if (
            $request->user()
            && $request->user()->role === 'customer'
            && (int) $repairRequest->customer_id !== (int) $request->user()->id
        ) {
            abort(403, 'You are not authorized to respond to quotes on another customer\'s repair request.');
        }

        if (in_array($validated['decision'], ['approve', 'accept'], true)) {
            if ($repairRequest->transitionTo(RepairRequest::STATUS_REPAIRING)) {
                $finalAmount = $repairRequest->repairJob?->final_quoted_amount ?? $repairRequest->quoted_final_amount;
                if ($finalAmount !== null) {
                    $repairRequest->update(['quoted_final_amount' => $finalAmount]);
                }
                if ($repairRequest->repairJob) {
                    $repairRequest->repairJob->update([
                        'customer_approved' => true,
                        'status' => 'REPAIRING',
                        'started_at' => $repairRequest->repairJob->started_at ?? now(),
                    ]);
                }
                if ($repairRequest->repairJob?->shop?->vendorProfile?->user) {
                    app(\App\Services\MultiChannelNotificationService::class)->notifyUser(
                        user: $repairRequest->repairJob->shop->vendorProfile->user,
                        eventType: 'FINAL_QUOTE_APPROVED',
                        title: "Final Quote Approved (#{$repairRequest->id})",
                        message: "Customer approved the final bench quote of ৳" . number_format((float) ($finalAmount ?? 0), 0) . ". Proceed with repair.",
                        actionUrl: '/vendor/dashboard?tab=assigned_jobs',
                        repairRequestId: $repairRequest->id
                    );
                }
                return redirect()
                    ->route('requests.track', $repairRequest->id)
                    ->with('success', 'Final diagnostic quote approved. Workshop repair is now underway.');
            }
        } else {
            if ($repairRequest->transitionTo(RepairRequest::STATUS_RETURN_SCHEDULED)) {
                $requiresCourier = $repairRequest->requiresCourierLogistics();
                if ($repairRequest->repairJob) {
                    $repairRequest->repairJob->update([
                        'customer_approved' => false,
                        'status' => 'READY_TO_RETURN',
                    ]);
                    if ($requiresCourier) {
                        $this->ensureCourierDeliveryJob($repairRequest->fresh(), $repairRequest->repairJob, 'TO_CUSTOMER');
                    }
                }
                return redirect()
                    ->route('requests.track', $repairRequest->id)
                    ->with(
                        'success',
                        $requiresCourier
                            ? 'Final diagnosis quote declined. Return courier has been scheduled to bring your device back.'
                            : 'Final diagnosis quote declined. Your device is ready for in-shop collection.'
                    );
            }
        }

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('error', 'Final quote action is not available in the current status.');
    }

    /**
     * Cancel a repair request.
     * Route: POST /requests/{id}/cancel
     */
    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $repairRequest = RepairRequest::with(['repairJob', 'payments'])->findOrFail($id);

        if (
            $request->user()
            && $request->user()->role === 'customer'
            && (int) $repairRequest->customer_id !== (int) $request->user()->id
        ) {
            abort(403, 'You are not authorized to cancel another customer\'s repair request.');
        }

        if ($repairRequest->transitionTo(RepairRequest::STATUS_CANCELLED)) {
            $repairRequest->update([
                'rejected_at' => now(),
            ]);

            if ($repairRequest->repairJob) {
                $repairRequest->repairJob->update(['status' => 'CANCELLED']);
            }

            $repairRequest->bids()
                ->whereIn('status', ['ACTIVE', 'ACCEPTED'])
                ->update(['status' => 'WITHDRAWN']);

            $repairRequest->payments()
                ->whereIn('status', ['PENDING', 'AUTHORIZED'])
                ->update(['status' => 'REFUNDED']);

            DeliveryJob::where('repair_request_id', $repairRequest->id)
                ->whereNotIn('status', ['DELIVERED', 'CANCELLED'])
                ->update([
                    'status' => 'CANCELLED',
                    'cancellation_reason' => $validated['reason'] ?? 'Cancelled by customer',
                ]);

            return redirect()
                ->route('requests.track', $repairRequest->id)
                ->with('success', 'Request cancelled successfully.');
        }

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('error', 'Cannot cancel request at this stage.');
    }

    /**
     * Ensure a DeliveryJob (`TO_SHOP` or `TO_CUSTOMER`) is queued whenever courier pickup/return is active.
     */
    private function ensureCourierDeliveryJob(RepairRequest $repairRequest, RepairJob $job, string $direction): void
    {
        app(\App\Services\DeliveryLifecycleService::class)->ensureDeliveryJob($repairRequest, $job, $direction);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RepairJob;
use App\Models\RepairRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

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

        $repairRequest = RepairRequest::findOrFail($id);
        $previousStatus = $repairRequest->status;

        if ($repairRequest->transitionTo($validated['status'])) {
            if ($repairRequest->repairJob && in_array($validated['status'], [
                'PICKUP_SCHEDULED', 'PICKED_UP', 'AT_SHOP', 'DIAGNOSING',
                'WAITING_APPROVAL', 'REPAIRING', 'RETURNING', 'COMPLETED', 'CANCELLED'
            ], true)) {
                $repairRequest->repairJob->update(['status' => $validated['status']]);
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
     * Accept Quote 1 (Marketplace Bid) -> transition to ASSIGNED + create RepairJob.
     * Route: POST /requests/{id}/accept-bid
     */
    public function acceptBid(Request $request, $id)
    {
        $validated = $request->validate([
            'bid_id' => 'required|integer|exists:bids,id',
        ]);

        $repairRequest = RepairRequest::findOrFail($id);
        $bid = $repairRequest->bids()->findOrFail($validated['bid_id']);

        DB::transaction(function () use ($repairRequest, $bid) {
            $repairRequest->update([
                'selected_bid_id' => $bid->id,
                'requested_shop_id' => $repairRequest->requested_shop_id ?? $bid->shop_id,
                'quoted_final_amount' => $bid->proposed_cost,
                'approved_at' => now(),
            ]);

            $repairRequest->transitionTo(RepairRequest::STATUS_ASSIGNED);

            $bid->update(['status' => 'ACCEPTED']);

            $repairRequest->bids()
                ->where('id', '!=', $bid->id)
                ->where('status', 'ACTIVE')
                ->update(['status' => 'DECLINED']);

            RepairJob::updateOrCreate(
                ['repair_request_id' => $repairRequest->id],
                [
                    'shop_id' => $bid->shop_id,
                    'accepted_bid_id' => $bid->id,
                    'status' => 'CREATED',
                    'final_quoted_amount' => $bid->proposed_cost,
                ]
            );
        });

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('success', 'Quote 1 bid accepted! Workshop has been assigned and doorstep pickup is ready.');
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

        $repairRequest = RepairRequest::with(['repairJob', 'payments'])->findOrFail($id);

        if ($validated['decision'] === 'accept') {
            $repairRequest->transitionTo(RepairRequest::STATUS_ASSIGNED);
            $repairRequest->update(['approved_at' => now()]);
            if ($repairRequest->repairJob) {
                $repairRequest->repairJob->update([
                    'status' => 'CREATED',
                    'started_at' => now(),
                ]);
            } elseif ($repairRequest->requested_shop_id) {
                RepairJob::create([
                    'repair_request_id' => $repairRequest->id,
                    'shop_id' => $repairRequest->requested_shop_id,
                    'status' => 'CREATED',
                    'final_quoted_amount' => $repairRequest->quoted_final_amount,
                    'started_at' => now(),
                ]);
            }

            return back()->with('success', "Direct Order #{$repairRequest->id} accepted! Status moved to ASSIGNED for courier pickup.");
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

        return back()->with('success', "Direct Order #{$repairRequest->id} declined and marked REJECTED (payment refunded if applicable).");
    }

    /**
     * Customer responds to Quote 2 (Final Bench Diagnosis during WAITING_APPROVAL).
     * Approve -> REPAIRING | Decline -> RETURN_SCHEDULED (device returned to customer).
     * Route: POST /requests/{id}/respond-final-quote
     */
    public function respondToFinalQuote(Request $request, $id)
    {
        $validated = $request->validate([
            'decision' => 'required|in:approve,decline',
        ]);

        $repairRequest = RepairRequest::with('repairJob')->findOrFail($id);

        if ($validated['decision'] === 'approve') {
            if ($repairRequest->transitionTo(RepairRequest::STATUS_REPAIRING)) {
                if ($repairRequest->repairJob) {
                    $repairRequest->repairJob->update([
                        'customer_approved' => true,
                        'status' => 'REPAIRING',
                        'started_at' => $repairRequest->repairJob->started_at ?? now(),
                    ]);
                }
                return redirect()
                    ->route('requests.track', $repairRequest->id)
                    ->with('success', 'Final diagnosis quote approved! Bench repair is now in progress.');
            }
        } else {
            if ($repairRequest->transitionTo(RepairRequest::STATUS_RETURN_SCHEDULED)) {
                if ($repairRequest->repairJob) {
                    $repairRequest->repairJob->update([
                        'customer_approved' => false,
                        'status' => 'READY_TO_RETURN',
                    ]);
                }
                return redirect()
                    ->route('requests.track', $repairRequest->id)
                    ->with('success', 'Final diagnosis quote declined. Return courier has been scheduled to bring your device back.');
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
        $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $repairRequest = RepairRequest::findOrFail($id);

        if ($repairRequest->transitionTo(RepairRequest::STATUS_CANCELLED)) {
            $repairRequest->update([
                'rejected_at' => now(),
            ]);

            return redirect()
                ->route('requests.track', $repairRequest->id)
                ->with('success', 'Request cancelled successfully.');
        }

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('error', 'Cannot cancel request at this stage.');
    }
}

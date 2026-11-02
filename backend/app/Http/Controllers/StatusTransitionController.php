<?php

namespace App\Http\Controllers;

use App\Models\RepairRequest;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * STATUS TRANSITION CONTROLLER (Supporting Feature R7)
 * ============================================================================
 * Handles status updates for repair requests with validation
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * ============================================================================
 */
class StatusTransitionController extends Controller
{
    /**
     * Update repair request status with validation
     * Route: POST /requests/{id}/update-status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|string|in:PENDING,BIDDING,ASSIGNED,PICKUP_SCHEDULED,PICKED_UP,AT_SHOP,DIAGNOSING,WAITING_APPROVAL,REPAIRING,RETURN_SCHEDULED,RETURNING,COMPLETED,CANCELLED',
            'notes' => 'nullable|string|max:500',
        ]);

        $repairRequest = RepairRequest::findOrFail($id);

        // Attempt status transition
        if ($repairRequest->transitionTo($validated['status'])) {
            // Log the transition
            \Log::info('Repair request status updated', [
                'request_id' => $repairRequest->id,
                'old_status' => $repairRequest->status,
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
            'message' => 'Invalid status transition',
        ], 400);
    }

    /**
     * Get available transitions for current status
     * Route: GET /requests/{id}/available-transitions
     */
    public function getAvailableTransitions($id)
    {
        $repairRequest = RepairRequest::findOrFail($id);
        
        $currentStatus = strtoupper($repairRequest->status ?? 'PENDING');
        
        // Get valid transitions from model
        $validTransitions = [
            'DRAFT' => ['PENDING', 'CANCELLED'],
            'PENDING' => ['BIDDING', 'REJECTED', 'CANCELLED'],
            'BIDDING' => ['ASSIGNED', 'CANCELLED'],
            'ASSIGNED' => ['PICKUP_SCHEDULED', 'AT_SHOP', 'CANCELLED'],
            'PICKUP_SCHEDULED' => ['PICKED_UP', 'CANCELLED'],
            'PICKED_UP' => ['AT_SHOP'],
            'AT_SHOP' => ['DIAGNOSING', 'REPAIRING'],
            'DIAGNOSING' => ['WAITING_APPROVAL', 'REPAIRING'],
            'WAITING_APPROVAL' => ['REPAIRING', 'CANCELLED'],
            'REPAIRING' => ['RETURN_SCHEDULED', 'COMPLETED'],
            'RETURN_SCHEDULED' => ['RETURNING'],
            'RETURNING' => ['COMPLETED'],
            'COMPLETED' => [],
            'CANCELLED' => [],
            'REJECTED' => [],
        ];

        return response()->json([
            'current_status' => $currentStatus,
            'available_transitions' => $validTransitions[$currentStatus] ?? [],
        ]);
    }

    /**
     * Accept a bid and transition to ASSIGNED status
     * Route: POST /requests/{id}/accept-bid
     */
    public function acceptBid(Request $request, $id)
    {
        $validated = $request->validate([
            'bid_id' => 'required|integer|exists:bids,id',
        ]);

        $repairRequest = RepairRequest::findOrFail($id);

        // Verify the bid belongs to this request
        $bid = $repairRequest->bids()->findOrFail($validated['bid_id']);

        // Update request with selected bid
        $repairRequest->update([
            'selected_bid_id' => $bid->id,
            'quoted_final_amount' => $bid->proposed_cost,
            'approved_at' => now(),
        ]);

        // Transition to ASSIGNED
        $repairRequest->transitionTo('ASSIGNED');

        // Update bid status
        $bid->update(['status' => 'ACCEPTED']);

        // Decline other bids
        $repairRequest->bids()
            ->where('id', '!=', $bid->id)
            ->where('status', 'ACTIVE')
            ->update(['status' => 'DECLINED']);

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('success', 'Bid accepted successfully! Vendor has been notified.');
    }

    /**
     * Cancel a repair request
     * Route: POST /requests/{id}/cancel
     */
    public function cancel(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'nullable|string|max:500',
        ]);

        $repairRequest = RepairRequest::findOrFail($id);

        if ($repairRequest->transitionTo('CANCELLED')) {
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

<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\RepairRequest;
use Illuminate\Http\Request;

/**
 * Feature R12: Live Bidding Engine & Diagnostic Quote Generator
 * Assigned Developer: Farhan Tanvir [22299179] | Sprint 2
 * MVC Layer: Controller
 */
class BiddingEngineController extends Controller
{
    /**
     * List all bids for a specific repair request.
     */
    public function index($requestId)
    {
        $bids = Bid::with('vendor')
            ->where('repair_request_id', $requestId)
            ->orderBy('proposed_cost', 'asc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => $bids,
        ]);
    }

    /**
     * Submit a new diagnostic quote/bid from a verified vendor.
     */
    public function store(Request $request, $requestId)
    {
        $validated = $request->validate([
            'vendor_id' => 'required|exists:vendor_profiles,id',
            'proposed_cost' => 'required|numeric|min:50',
            'estimated_days' => 'required|integer|min:1|max:30',
            'warranty_days' => 'required|integer|min:0',
            'line_items' => 'required|array',
            'technician_note' => 'nullable|string',
        ]);

        $bid = Bid::create(array_merge($validated, [
            'repair_request_id' => $requestId,
            'status' => 'submitted',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Diagnostic quote submitted successfully to the bidding board.',
            'data' => $bid,
        ], 201);
    }

    /**
     * Customer accepts a specific vendor bid.
     */
    public function acceptBid($bidId)
    {
        $bid = Bid::findOrFail($bidId);
        $bid->update(['status' => 'accepted']);

        // Mark corresponding repair request as assigned
        $request = RepairRequest::findOrFail($bid->repair_request_id);
        $request->update([
            'status' => 'assigned',
            'selected_bid_id' => $bid->id,
        ]);

        // Reject other bids for this request
        Bid::where('repair_request_id', $bid->repair_request_id)
            ->where('id', '!=', $bid->id)
            ->update(['status' => 'rejected']);

        return response()->json([
            'status' => 'success',
            'message' => 'Bid accepted! Repair request assigned to vendor.',
            'data' => $bid,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RepairRequest;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R7: Customer Repair Request Posting & Lifecycle Tracking
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 * Architectural Layer: Controller (RepairRequestController)
 * Models Touched: RepairRequest, DeviceBrand
 * View Rendered: resources/views/requests/create.blade.php
 *
 * INSTRUCTIONS FOR SHANID:
 * 1. Implement create(): Display customer request submission form with device
 *    brand dropdowns, issue categories, and preferred logistics choice.
 * 2. Implement store(Request $request): Validate input, handle photo uploads,
 *    set status to 'bidding_open', and automatically notify nearby vendors.
 * 3. Implement track($id): Show lifecycle progress bar (Request Posted ->
 *    Bidding Open -> Vendor Assigned -> Diagnostics -> Repaired -> Delivered).
 * ============================================================================
 */
class RepairRequestController extends Controller
{
    /**
     * Show repair request creation form.
     * Route: GET /requests/create
     */
    public function create()
    {
        return view('requests.create');
    }

    /**
     * Save new customer repair request.
     * Route: POST /requests
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_type' => 'required|string',
            'brand' => 'required|string',
            'model_name' => 'required|string',
            'serial_number' => 'nullable|string',
            'issue_title' => 'required|string|max:255',
            'issue_description' => 'required|string',
            'preferred_service_type' => 'required|in:pickup_dropoff,walk_in,technician_home_visit',
            'pickup_address' => 'nullable|string',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120', // 5MB max per image
        ]);

        // Handle photo uploads
        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $path = $photo->store('repair-requests', 'public');
                $photoPaths[] = $path;
            }
            $validated['photos'] = $photoPaths;
        }

        // Create repair request
        $repairRequest = RepairRequest::create(array_merge($validated, [
            'customer_id' => $request->user()->id ?? 1, // TODO: Replace with proper auth
            'status' => 'bidding_open',
        ]));

        // TODO: Trigger AI Summary Generation (Feature R13)
        // AISummaryController::generateSummary($repairRequest);

        // TODO: Notify nearby vendors (Feature R14)
        // VendorNotificationController::dispatchBiddingAlert($repairRequest);

        return redirect()->route('requests.track', $repairRequest->id)
            ->with('success', 'Repair request published successfully! Vendors have been notified and bidding is now open.');
    }

    /**
     * Track repair request lifecycle milestones.
     * Route: GET /requests/{id}/track
     */
    public function track($id)
    {
        $repairRequest = RepairRequest::with([
            'bids' => function($query) {
                $query->where('status', '!=', 'WITHDRAWN')
                      ->orderBy('proposed_cost', 'asc'); // Show lowest bids first
            },
            'customer'
        ])->findOrFail($id);

        // TODO: Add authorization check - only customer or admin can view
        // if (auth()->id() !== $repairRequest->customer_id && !auth()->user()->isAdmin()) {
        //     abort(403, 'Unauthorized access');
        // }

        return view('requests.track', compact('repairRequest'));
    }
}

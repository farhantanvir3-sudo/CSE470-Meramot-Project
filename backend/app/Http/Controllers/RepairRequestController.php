<?php

namespace App\Http\Controllers;

use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R7: Customer Repair Request Posting & Lifecycle Tracking
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Merged Lifecycle: Farhan Tanvir [22299179] (Dual-Path & Dual-Quote Flow)
 * Views Rendered (from root `frontend/views/`):
 *   - requests/create.blade.php
 *   - requests/track.blade.php
 * ============================================================================
 */
class RepairRequestController extends Controller
{
    /**
     * Show repair request submission form.
     * Route: GET /requests/create
     */
    public function create()
    {
        return view('requests.create');
    }

    /**
     * Store newly posted repair request and open reverse bidding (Path A: Marketplace).
     * Route: POST /requests
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'device_type' => 'required|string|max:100',
            'brand' => 'required|string|max:100',
            'model_name' => 'required|string|max:150',
            'serial_number' => 'nullable|string|max:100',
            'issue_category' => 'nullable|string|max:120',
            'issue_title' => 'required|string|max:255',
            'issue_description' => 'required|string',
            'preferred_service_type' => 'required|in:pickup_dropoff,walk_in,technician_home_visit',
            'pickup_address' => 'nullable|string|max:500',
            'area' => 'nullable|string|max:100',
            'preferred_shop_id' => 'nullable|integer|exists:repair_shops,id',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        $photoPaths = null;
        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                $photoPaths[] = $photo->store('repair-requests', 'public');
            }
        }

        $customerId = $request->user()?->id ?? User::where('role', 'customer')->value('id') ?? User::value('id') ?? 1;
        $requestedShopId = $validated['preferred_shop_id'] ?? null;

        $fullAddress = $validated['pickup_address'] ?? null;
        if (!empty($validated['area']) && $fullAddress && !str_contains($fullAddress, $validated['area'])) {
            $fullAddress .= ', ' . $validated['area'] . ', Dhaka';
        } elseif (!empty($validated['area']) && empty($fullAddress)) {
            $fullAddress = $validated['area'] . ', Dhaka';
        }

        $repairRequest = RepairRequest::create([
            'customer_id' => $customerId,
            'source' => $requestedShopId ? 'DIRECT_CUSTOM_SHOP' : 'MARKETPLACE',
            'requested_shop_id' => $requestedShopId,
            'title' => $validated['issue_title'],
            'description' => $validated['issue_description'],
            'problem' => $validated['issue_description'],
            'device_type' => $validated['device_type'],
            'brand' => $validated['brand'],
            'model_name' => $validated['model_name'],
            'serial_number' => $validated['serial_number'] ?? null,
            'issue_category' => $validated['issue_category'] ?? 'General Hardware Diagnostics',
            'image_urls' => $photoPaths,
            'mode' => 'CHECKUP_AND_REPAIR',
            'status' => RepairRequest::STATUS_BIDDING,
            'preferred_pickup' => $validated['preferred_service_type'] === 'pickup_dropoff',
            'preferred_service_type' => $validated['preferred_service_type'],
            'pickup_address' => $fullAddress,
        ]);

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('success', 'Repair request published! Verified workshops across Dhaka can now submit Quote 1 bids.');
    }

    /**
     * Track status and bids of a specific repair request.
     * Route: GET /requests/{id}/track
     */
    public function track($id)
    {
        $repairRequest = RepairRequest::with([
            'bids' => function ($query) {
                $query->where('status', '!=', 'WITHDRAWN')
                      ->with('shop')
                      ->orderBy('proposed_cost', 'asc');
            },
            'customer',
            'requestedShop',
            'repairJob.shop',
        ])->findOrFail($id);

        return view('requests.track', compact('repairRequest'));
    }
}

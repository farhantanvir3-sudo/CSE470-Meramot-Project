<?php

namespace App\Http\Controllers;

use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R7: Customer Repair Request Posting, History & Lifecycle Tracking
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Merged Lifecycle: Farhan Tanvir [22299179] (Dual-Path, Dual-Quote & Requests Hub)
 * Views Rendered (from root `frontend/views/`):
 *   - requests/index.blade.php
 *   - requests/create.blade.php
 *   - requests/track.blade.php
 * ============================================================================
 */
class RepairRequestController extends Controller
{
    /**
     * Customer "My Repair Requests & Direct Orders" Hub.
     * Routes: GET /requests, GET /orders
     */
    public function index(Request $request)
    {
        $statusFilter = strtoupper((string) $request->query('filter', 'ALL'));
        $user = $request->user();

        $query = RepairRequest::with(['bids.shop', 'requestedShop', 'repairJob.shop', 'customer'])
            ->orderByDesc('created_at');

        if ($user && $user->role === 'customer' && $request->query('scope') !== 'all') {
            $hasOwnRequests = RepairRequest::where('customer_id', $user->id)->exists();
            if ($hasOwnRequests) {
                $query->where('customer_id', $user->id);
            }
        }

        if ($statusFilter === 'BIDDING') {
            $query->where('status', 'BIDDING');
        } elseif ($statusFilter === 'DIRECT') {
            $query->where('source', 'DIRECT_SERVICE');
        } elseif ($statusFilter === 'ACTIVE') {
            $query->whereIn('status', ['PENDING', 'ASSIGNED', 'PICKUP_SCHEDULED', 'PICKED_UP', 'AT_SHOP', 'DIAGNOSING', 'WAITING_APPROVAL', 'REPAIRING']);
        } elseif ($statusFilter === 'COMPLETED') {
            $query->where('status', 'COMPLETED');
        }

        $requests = $query->paginate(12)->withQueryString();

        return view('requests.index', compact('requests', 'statusFilter'));
    }

    /**
     * Show repair request submission form.
     * Route: GET /requests/create
     */
    public function create(Request $request)
    {
        $selectedShop = $request->filled('shop_id')
            ? RepairShop::where('is_active', true)->where('is_public', true)->find($request->query('shop_id'))
            : null;
        $prefilledIssue = (string) $request->query('issue', '');

        return view('requests.create', compact('selectedShop', 'prefilledIssue'));
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
            'year' => 'nullable|string|max:20',
            'specs' => 'nullable|string|max:150',
            'serial_number' => 'nullable|string|max:100',
            'issue_category' => 'nullable|string|max:120',
            'issue_title' => 'required|string|max:255',
            'issue_description' => 'required|string',
            'preferred_service_type' => 'required|in:pickup_dropoff,walk_in,technician_home_visit',
            'pickup_address' => 'nullable|string|max:500',
            'area' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:100',
            'estimated_budget' => 'nullable|numeric|min:0|max:1000000',
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

        $cityName = trim((string) ($validated['city'] ?? 'Dhaka')) ?: 'Dhaka';
        $areaName = trim((string) ($validated['area'] ?? ''));

        $fullAddress = trim((string) ($validated['pickup_address'] ?? ''));
        if ($areaName !== '' && $fullAddress !== '' && !str_contains(strtolower($fullAddress), strtolower($areaName))) {
            $fullAddress .= ', ' . $areaName;
            if (!str_contains(strtolower($areaName), strtolower($cityName)) && !str_contains(strtolower($fullAddress), strtolower($cityName))) {
                $fullAddress .= ', ' . $cityName;
            }
        } elseif ($areaName !== '' && $fullAddress === '') {
            $fullAddress = str_contains(strtolower($areaName), strtolower($cityName))
                ? $areaName
                : ($areaName . ', ' . $cityName);
        }

        // Preserve optional hardware metadata cleanly in description
        $extraMeta = [];
        if (!empty($validated['year'])) {
            $extraMeta[] = 'Year: ' . trim($validated['year']);
        }
        if (!empty($validated['specs'])) {
            $extraMeta[] = 'Specs: ' . trim($validated['specs']);
        }
        if (!empty($validated['estimated_budget'])) {
            $extraMeta[] = 'Customer Expected Budget: ৳' . number_format((float) $validated['estimated_budget']);
        }

        $enrichedDescription = $validated['issue_description'];
        if (!empty($extraMeta)) {
            $enrichedDescription .= "\n\n[" . implode(' • ', $extraMeta) . ']';
        }

        $repairRequest = RepairRequest::create([
            'customer_id' => $customerId,
            'source' => $requestedShopId ? 'DIRECT_CUSTOM_SHOP' : 'MARKETPLACE',
            'requested_shop_id' => $requestedShopId,
            'title' => $validated['issue_title'],
            'description' => $enrichedDescription,
            'problem' => $enrichedDescription,
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
            'pickup_address' => $fullAddress ?: null,
        ]);

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('success', 'Repair request published! Verified workshops across Bangladesh can now submit Quote 1 bids.');
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

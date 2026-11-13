<?php

namespace App\Http\Controllers;

use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\User;
use App\Services\MediaUploadService;
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
     * Redirect non-customer roles to their designated operational portal.
     */
    private function redirectForNonCustomer(User $user)
    {
        return match ($user->role) {
            'admin' => redirect('/admin')
                ->with('warning', 'Platform administrators manage operations from the Admin Console and do not hold customer orders.'),
            'vendor' => redirect('/vendor/dashboard'),
            'delivery_agent', 'delivery' => redirect('/delivery'),
            default => redirect('/'),
        };
    }

    /**
     * Customer "My Repair Requests & Direct Orders" Hub.
     * Routes: GET /requests, GET /orders
     */
    public function index(Request $request)
    {
        $statusFilter = strtoupper((string) $request->query('filter', 'ALL'));
        $user = $request->user();

        if (!$user) {
            return redirect()->guest(route('login'))
                ->with('warning', 'Please sign in to view your repair requests and past orders.');
        }

        if ($user->role !== 'customer') {
            return $this->redirectForNonCustomer($user);
        }

        $query = RepairRequest::with(['bids.shop', 'requestedShop', 'repairJob.shop', 'customer', 'review'])
            ->where('customer_id', $user->id)
            ->orderByDesc('created_at');

        if ($statusFilter === 'BIDDING') {
            $query->where('status', 'BIDDING');
        } elseif ($statusFilter === 'DIRECT') {
            $query->whereIn('source', ['DIRECT_SERVICE', 'DIRECT_CUSTOM_SHOP']);
        } elseif ($statusFilter === 'ACTIVE') {
            $query->whereIn('status', [
                'PENDING', 'ASSIGNED', 'PICKUP_SCHEDULED', 'PICKED_UP',
                'AT_SHOP', 'DIAGNOSING', 'WAITING_APPROVAL', 'REPAIRING',
                'RETURN_SCHEDULED', 'RETURNING',
            ]);
        } elseif ($statusFilter === 'COMPLETED') {
            $query->whereIn('status', ['COMPLETED', 'RETURNED_TO_CUSTOMER']);
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
        if ($user = $request->user()) {
            if ($user->role !== 'customer') {
                return $this->redirectForNonCustomer($user);
            }
            if ($user->isBanned()) {
                return redirect('/requests')
                    ->with('error', 'Account Restricted: Banned accounts cannot post new repair requests. You may still view and track your ongoing and past orders.');
            }
        }

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
        if ($user = $request->user()) {
            if ($user->role !== 'customer') {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Only customer accounts can post repair requests.'], 403)
                    : $this->redirectForNonCustomer($user);
            }
            if ($user->isBanned()) {
                return $request->expectsJson()
                    ? response()->json(['message' => 'Account Restricted: Banned accounts cannot post repair requests.'], 403)
                    : redirect('/requests')
                        ->with('error', 'Account Restricted: Banned accounts cannot post new repair requests. You may still view and track your ongoing and past orders.');
            }
        }

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
            'contact_phone' => 'nullable|string|max:30',
            'pickup_address' => 'nullable|string|max:500',
            'area' => 'nullable|string|max:120',
            'city' => 'nullable|string|max:100',
            'estimated_budget' => 'nullable|numeric|min:0|max:1000000',
            'preferred_shop_id' => 'nullable|integer|exists:repair_shops,id',
            'photos' => 'nullable|array|max:5',
            'photos.*' => 'nullable|file|mimetypes:image/jpeg,image/pjpeg,image/png,image/webp,image/gif,image/bmp,image/avif|max:15360',
        ]);

        $photoPaths = null;
        if ($request->hasFile('photos')) {
            $photoPaths = [];
            foreach ($request->file('photos') as $photo) {
                if (app()->runningUnitTests()) {
                    $photoPaths[] = $photo->store('repair-requests', 'public');
                } else {
                    $encoded = MediaUploadService::encode($photo, 1400, 82);
                    if ($encoded !== null) {
                        $photoPaths[] = $encoded;
                    }
                }
            }
        }

        $customerId = $request->user()?->id ?? User::where('role', 'customer')->value('id');
        abort_if(!$customerId, 403, 'A customer account is required to post a repair request.');
        $requestedShopId = $validated['preferred_shop_id'] ?? null;
        $targetShop = $requestedShopId ? RepairShop::find($requestedShopId) : null;

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

        $authUser = $request->user();
        $contactPhone = trim((string) ($validated['contact_phone'] ?? $authUser?->phone ?? ''));

        if ($authUser && $contactPhone === '') {
            return back()
                ->withInput()
                ->withErrors(['contact_phone' => 'Please enter your contact phone number (or save it in your Profile Dashboard) so workshops and riders can reach you.']);
        }

        if ($authUser) {
            $profileUpdates = [];
            if (empty($authUser->phone) && $contactPhone !== '') {
                $profileUpdates['phone'] = $contactPhone;
            }
            if (empty($authUser->address) && $fullAddress !== '') {
                $profileUpdates['address'] = $fullAddress;
                $profileUpdates['city'] = $cityName;
                if ($areaName !== '') {
                    $profileUpdates['area'] = $areaName;
                }
            }
            if (!empty($profileUpdates)) {
                $authUser->update($profileUpdates);
            }
        }

        $dropoffAddress = $targetShop
            ? ($targetShop->address ?: ($targetShop->area . ', ' . $targetShop->city))
            : null;

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
            'contact_phone' => $contactPhone ?: null,
            'pickup_address' => $fullAddress ?: null,
            'dropoff_address' => $dropoffAddress,
        ]);

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('success', 'Repair request published. Verified workshops across your region can now submit initial estimates.');
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
            'deliveryJobs.deliveryAgent.user',
            'payments',
            'review',
        ])->findOrFail($id);

        return view('requests.track', compact('repairRequest'));
    }
}

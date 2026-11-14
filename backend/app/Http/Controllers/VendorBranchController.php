<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\FinancialLedger;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VendorProfile;
use App\Services\BangladeshLocationResolver;
use App\Services\DeliveryLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ============================================================================
 * FEATURE R11 & R15: Multi-Branch Vendor Expansion & Dual-Address Control Center
 * ============================================================================
 * Author / Lead Architect: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 *
 * Implements:
 *   1. Unified Vendor Dashboard with Multi-Branch Switcher (`All Branches` or
 *      any individual branch across Bangladesh).
 *   2. Dashboard Branching Activation (`enableBranching`) — allows any single-shop
 *      vendor to turn on Multi-Branch mode and display exact Branch Tags on tiles.
 *   3. New Branch Expansion (`storeBranch`) — creates additional shops around the
 *      city or country under the exact same Brand Name with distinct `branch_name`.
 *   4. Dual-Address Editor (`updateBranchAddresses`) — manages:
 *        - Address 1: GPS Approximate Location (`latitude`, `longitude`, `gps_address`)
 *        - Address 2: Vendor-Provided Public Location (`address`, `city`, `area`)
 *        - Sync Option (`same_as_gps_location` = true when 1 & 2 are the same)
 * ============================================================================
 */
class VendorBranchController extends Controller
{
    /**
     * Render the Vendor Control Center scoped to All Branches or a selected Branch.
     * Route: GET /vendor/dashboard
     */
    public function dashboard(Request $request)
    {
        if ($redirect = $this->guardUnapprovedAuthenticatedVendor($request)) {
            return $redirect;
        }

        $vendorProfile = $this->resolveVendorProfile($request);
        $branches = $vendorProfile->shops()->orderBy('id')->get();

        if ($branches->isEmpty()) {
            $primary = RepairShop::create([
                'vendor_profile_id' => $vendorProfile->id,
                'name' => $vendorProfile->canonical_brand_name,
                'slug' => Str::slug($vendorProfile->canonical_brand_name) . '-' . $vendorProfile->id,
                'is_branch' => (bool) $vendorProfile->is_branched,
                'branch_name' => $vendorProfile->is_branched ? (($vendorProfile->area ?: 'Main') . ' Flagship') : null,
                'description' => 'Verified electronic repair workshop on Meramot.',
                'address' => $vendorProfile->shop_address ?: 'Banani, Dhaka',
                'gps_address' => $vendorProfile->gps_address ?: ($vendorProfile->shop_address ?: 'Banani, Dhaka'),
                'same_as_gps_location' => (bool) $vendorProfile->same_as_gps_location,
                'city' => $vendorProfile->city ?: 'Dhaka',
                'area' => $vendorProfile->area ?: 'Banani',
                'latitude' => $vendorProfile->shop_latitude ?: 23.790428,
                'longitude' => $vendorProfile->shop_longitude ?: 90.398003,
                'inspection_fee' => 300,
                'base_labor_fee' => 500,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => $vendorProfile->categories ?: ['Laptop repair', 'Smartphone repair'],
                'is_active' => true,
                'is_public' => true,
            ]);
            $branches = collect([$primary]);
        }

        $branchIds = $branches->pluck('id')->all();
        $selectedParam = (string) $request->query('shop_id', 'all');
        $activeBranch = null;

        if ($selectedParam !== 'all' && ctype_digit($selectedParam)) {
            $activeBranch = $branches->firstWhere('id', (int) $selectedParam);
        }
        $selectedShopId = $activeBranch ? (string) $activeBranch->id : 'all';
        $scopedShopIds = $activeBranch ? [$activeBranch->id] : $branchIds;

        // Direct orders scoped to selected branch (or all vendor branches + unassigned demo direct orders)
        $directOrdersQuery = RepairRequest::with(['customer', 'requestedShop'])
            ->where('source', 'DIRECT_SERVICE')
            ->where('status', 'PENDING');

        if ($activeBranch) {
            $directOrdersQuery->where('requested_shop_id', $activeBranch->id);
        } else {
            $directOrdersQuery->where(function ($q) use ($scopedShopIds) {
                $q->whereIn('requested_shop_id', $scopedShopIds)
                  ->orWhereNull('requested_shop_id');
            });
        }
        $directOrders = $directOrdersQuery->latest()->get();

        $marketplaceRequests = RepairRequest::with(['customer', 'bids.shop'])
            ->whereIn('status', ['PENDING', 'BIDDING'])
            ->where(function ($q) {
                $q->whereNull('source')->orWhere('source', '!=', 'DIRECT_SERVICE');
            })
            ->latest()
            ->take(8)
            ->get();

        $activeJobs = RepairJob::with(['repairRequest.customer', 'repairRequest.deliveryJobs.deliveryAgent.user', 'acceptedBid', 'shop'])
            ->whereIn('shop_id', $scopedShopIds)
            ->where('status', '!=', 'CANCELLED')
            ->orderByRaw("CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END")
            ->latest()
            ->take(10)
            ->get();

        $ledgerGross = (float) FinancialLedger::whereIn('shop_id', $scopedShopIds)
            ->where('entry_type', 'CUSTOMER_PAYMENT')
            ->sum('amount');

        $reviewedBranches = $branches->where('review_count', '>', 0);
        $avgRating = $activeBranch
            ? (float) $activeBranch->rating
            : ($reviewedBranches->isNotEmpty() ? round((float) $reviewedBranches->avg('rating'), 2) : 0.00);

        $totalReviews = $activeBranch
            ? (int) $activeBranch->review_count
            : (int) $branches->sum('review_count');

        $branchMetrics = [
            'gross_inflow' => $ledgerGross,
            'active_jobs_count' => $activeJobs->count(),
            'avg_rating' => $avgRating,
            'total_reviews' => $totalReviews,
            'branch_count' => $branches->count(),
        ];

        $bangladeshDistricts = array_column(BangladeshLocationResolver::DISTRICTS, 'city');
        sort($bangladeshDistricts);

        return view('vendor.dashboard', compact(
            'vendorProfile',
            'branches',
            'selectedShopId',
            'activeBranch',
            'directOrders',
            'marketplaceRequests',
            'activeJobs',
            'branchMetrics',
            'bangladeshDistricts'
        ));
    }

    /**
     * Activate Multi-Branch Mode from Vendor Dashboard so Branch Tags appear on shop tiles.
     * Route: POST /vendor/branches/toggle
     */
    public function enableBranching(Request $request)
    {
        if ($redirect = $this->guardUnapprovedAuthenticatedVendor($request)) {
            return $redirect;
        }

        $validated = $request->validate([
            'primary_branch_name' => 'required|string|max:120',
            'vendor_profile_id' => 'nullable|integer|exists:vendor_profiles,id',
        ]);

        $vendorProfile = $this->resolveVendorProfile($request);
        $brandName = $vendorProfile->canonical_brand_name;

        $vendorProfile->update([
            'brand_name' => $brandName,
            'is_branched' => true,
        ]);

        $firstShop = $vendorProfile->shops()->orderBy('id')->first();
        if ($firstShop) {
            $firstShop->update([
                'name' => $brandName,
                'is_branch' => true,
                'branch_name' => trim($validated['primary_branch_name']),
            ]);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => "Multi-branch mode enabled for {$brandName}.",
                'data' => $vendorProfile->fresh('shops'),
            ]);
        }

        return redirect('/vendor/dashboard?tab=branches')
            ->with('success', "Multi-branch mode enabled. The '{$validated['primary_branch_name']}' label is now active on {$brandName} listings.");
    }

    /**
     * Create a new branch shop under the same vendor brand name anywhere in Bangladesh.
     * Route: POST /vendor/branches
     */
    public function storeBranch(Request $request)
    {
        if ($redirect = $this->guardUnapprovedAuthenticatedVendor($request)) {
            return $redirect;
        }

        $validated = $request->validate([
            'vendor_profile_id' => 'nullable|integer|exists:vendor_profiles,id',
            'branch_name' => 'required|string|max:120',
            'primary_branch_name' => 'nullable|string|max:120',
            'latitude' => 'required|numeric|between:20.5,26.7',
            'longitude' => 'required|numeric|between:88.0,92.7',
            'gps_address' => 'required|string|max:500',
            'same_as_gps_location' => 'nullable|boolean',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:30',
            'description' => 'nullable|string|max:1000',
            'inspection_fee' => 'nullable|numeric|min:0|max:10000',
            'base_labor_fee' => 'nullable|numeric|min:0|max:50000',
            'categories' => 'nullable|array',
        ]);

        $vendorProfile = $this->resolveVendorProfile($request);
        $brandName = $vendorProfile->canonical_brand_name;
        $branchName = trim($validated['branch_name']);

        // Ensure no duplicate branch_name exists under this same vendor
        $duplicateExists = RepairShop::where('vendor_profile_id', $vendorProfile->id)
            ->whereRaw('LOWER(branch_name) = ?', [mb_strtolower($branchName)])
            ->exists();

        if ($duplicateExists) {
            throw ValidationException::withMessages([
                'branch_name' => "Your brand '{$brandName}' already has a branch named '{$branchName}'. Please choose a distinct branch name.",
            ]);
        }

        // Enforce Dual-Address Coherence
        $sameAsGps = $request->boolean('same_as_gps_location');
        $gpsAddress = trim($validated['gps_address']);
        $vendorAddress = $sameAsGps ? $gpsAddress : trim((string) ($validated['address'] ?? ''));

        if (!$sameAsGps && $vendorAddress === '') {
            throw ValidationException::withMessages([
                'address' => 'Please enter the vendor-provided storefront address, or tick "Use GPS location as public shop address".',
            ]);
        }

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];
        $resolvedGeo = BangladeshLocationResolver::resolve($lat, $lng);

        $city = !empty($validated['city'])
            ? BangladeshLocationResolver::normalizeCity($validated['city'])
            : $resolvedGeo['city'];
        $area = !empty($validated['area'])
            ? trim($validated['area'])
            : $resolvedGeo['area'];

        // Promote VendorProfile & any previously un-tagged first shop into Multi-Branch mode coherently
        $vendorProfile->update([
            'brand_name' => $brandName,
            'is_branched' => true,
        ]);

        $existingShops = $vendorProfile->shops()->orderBy('id')->get();
        foreach ($existingShops as $existing) {
            $fallbackBranchTag = !empty($validated['primary_branch_name'])
                ? trim($validated['primary_branch_name'])
                : (($existing->area ?: $existing->city) . ' Main Branch');

            if (!$existing->is_branch || empty($existing->branch_name)) {
                if (mb_strtolower($fallbackBranchTag) === mb_strtolower($branchName)) {
                    $fallbackBranchTag .= ' (HQ)';
                }
                $existing->update([
                    'name' => $brandName,
                    'is_branch' => true,
                    'branch_name' => $fallbackBranchTag,
                ]);
            }
        }

        // Generate unique slug for the new branch
        $baseSlug = Str::slug($brandName . '-' . $branchName);
        if ($baseSlug === '') {
            $baseSlug = 'branch-' . $vendorProfile->id . '-' . time();
        }
        $slug = $baseSlug;
        $counter = 2;
        while (RepairShop::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$counter}";
            $counter++;
        }

        $templateShop = $existingShops->first();
        $categories = !empty($validated['categories'])
            ? array_values($validated['categories'])
            : ($templateShop->categories ?? ['COURIER_PICKUP', 'IN_SHOP_REPAIR']);
        $specialties = $templateShop->specialties
            ?? $vendorProfile->categories
            ?? ['Laptop Diagnostics', 'Smartphone Hardware', 'Component Repair'];

        $inspectionFee = isset($validated['inspection_fee'])
            ? (float) $validated['inspection_fee']
            : (float) ($templateShop->inspection_fee ?? 250);
        $baseLaborFee = isset($validated['base_labor_fee'])
            ? (float) $validated['base_labor_fee']
            : (float) ($templateShop->base_labor_fee ?? 500);

        $isApprovedVendor = ($vendorProfile->verification_status ?? 'approved') === 'approved';

        $newBranch = RepairShop::create([
            'vendor_profile_id' => $vendorProfile->id,
            'name' => $brandName,
            'slug' => $slug,
            'is_branch' => true,
            'branch_name' => $branchName,
            'description' => !empty($validated['description'])
                ? trim($validated['description'])
                : "{$brandName} — {$branchName} branch serving {$area}, {$city} with certified diagnostics and genuine spare parts.",
            'address' => $vendorAddress,
            'gps_address' => $gpsAddress,
            'same_as_gps_location' => ($vendorAddress === $gpsAddress) && $sameAsGps,
            'city' => $city,
            'area' => $area,
            'latitude' => $lat,
            'longitude' => $lng,
            'rating' => 0.00,
            'review_count' => 0,
            'price_level' => $templateShop->price_level ?? 2,
            'inspection_fee' => $inspectionFee,
            'base_labor_fee' => $baseLaborFee,
            'offer_summary' => 'Inspection ৳' . (int) $inspectionFee,
            'result_tag' => 'New Branch',
            'eta_minutes' => 60,
            'has_voucher' => (bool) ($templateShop->has_voucher ?? false),
            'free_delivery' => in_array('COURIER_PICKUP', $categories, true),
            'has_deals' => false,
            'is_featured' => false,
            'is_active' => true,
            'is_public' => $isApprovedVendor,
            'setup_complete' => $isApprovedVendor,
            'accepts_direct_orders' => $isApprovedVendor,
            'supports_pickup' => in_array('COURIER_PICKUP', $categories, true),
            'phone' => !empty($validated['phone']) ? trim($validated['phone']) : ($templateShop->phone ?? $vendorProfile->phone ?? '+8801711000001'),
            'email' => Str::limit($slug, 40, '') . '@meramot.local',
            'opening_hours_text' => $templateShop->opening_hours_text ?? 'Sat – Thu: 10:00 AM – 8:30 PM',
            'categories' => $categories,
            'specialties' => $specialties,
        ]);

        // Mirror the vendor's own published fixed-price services onto the new branch.
        // Nothing is auto-generated: a vendor with no services gets a branch with an empty list.
        $templateServices = $templateShop
            ? ShopService::where('shop_id', $templateShop->id)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get()
            : collect();
        foreach ($templateServices as $tplSvc) {
            ShopService::updateOrCreate(
                ['shop_id' => $newBranch->id, 'slug' => $tplSvc->slug],
                [
                    'name' => $tplSvc->name,
                    'short_description' => $tplSvc->short_description,
                    'description' => $tplSvc->description,
                    'device_type' => $tplSvc->device_type,
                    'issue_category' => $tplSvc->issue_category,
                    'pricing_type' => $tplSvc->pricing_type,
                    'base_price' => $tplSvc->base_price,
                    'price_max' => $tplSvc->price_max,
                    'estimated_days_min' => $tplSvc->estimated_days_min,
                    'estimated_days_max' => $tplSvc->estimated_days_max,
                    'includes_pickup' => in_array('COURIER_PICKUP', $categories, true),
                    'sort_order' => $tplSvc->sort_order,
                    'is_featured' => (bool) $tplSvc->is_featured,
                    'is_active' => true,
                ]
            );
        }

        if ($vendorProfile->user_id) {
            DB::table('shop_staff')->updateOrInsert(
                ['shop_id' => $newBranch->id, 'user_id' => $vendorProfile->user_id],
                [
                    'role' => 'OWNER',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => "New branch '{$branchName}' created under '{$brandName}'.",
                'data' => $newBranch,
            ], 201);
        }

        return redirect("/vendor/dashboard?shop_id={$newBranch->id}&tab=branches")
            ->with('success', "New branch added. '{$brandName} — {$branchName}' ({$area}, {$city}) is now active on the marketplace.");
    }

    /**
     * Update Dual Addresses (GPS Approximate vs Vendor-Provided) & Branch Name for an existing shop.
     * Route: PATCH /vendor/branches/{shopId}
     */
    public function updateBranchAddresses(Request $request, $shopId)
    {
        $shop = RepairShop::with('vendorProfile')->findOrFail($shopId);

        if ($request->user() && $request->user()->role === 'vendor') {
            $ownedProfile = VendorProfile::where('user_id', $request->user()->id)->first();
            if ($ownedProfile && (int) $shop->vendor_profile_id !== (int) $ownedProfile->id) {
                abort(403, 'You can only update locations for your own repair workshops.');
            }
        }

        $validated = $request->validate([
            'branch_name' => ($shop->is_branch ? 'required' : 'nullable') . '|string|max:120',
            'latitude' => 'required|numeric|between:20.5,26.7',
            'longitude' => 'required|numeric|between:88.0,92.7',
            'gps_address' => 'required|string|max:500',
            'same_as_gps_location' => 'nullable|boolean',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:120',
            'phone' => 'nullable|string|max:30',
        ]);

        if ($shop->is_branch && !empty($validated['branch_name']) && $shop->vendor_profile_id) {
            $newBranchName = trim($validated['branch_name']);
            $dup = RepairShop::where('vendor_profile_id', $shop->vendor_profile_id)
                ->where('id', '!=', $shop->id)
                ->whereRaw('LOWER(branch_name) = ?', [mb_strtolower($newBranchName)])
                ->exists();

            if ($dup) {
                throw ValidationException::withMessages([
                    'branch_name' => "Another branch of {$shop->name} already uses '{$newBranchName}'.",
                ]);
            }
        }

        $sameAsGps = $request->boolean('same_as_gps_location');
        $gpsAddress = trim($validated['gps_address']);
        $vendorAddress = $sameAsGps ? $gpsAddress : trim((string) ($validated['address'] ?? ''));

        if (!$sameAsGps && $vendorAddress === '') {
            throw ValidationException::withMessages([
                'address' => 'Please enter a vendor-provided storefront address or select "Use GPS location as public shop address".',
            ]);
        }

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];
        $resolvedGeo = BangladeshLocationResolver::resolve($lat, $lng);

        $city = !empty($validated['city'])
            ? BangladeshLocationResolver::normalizeCity($validated['city'])
            : $resolvedGeo['city'];
        $area = !empty($validated['area'])
            ? trim($validated['area'])
            : $resolvedGeo['area'];

        $shop->update([
            'branch_name' => $shop->is_branch ? trim((string) $validated['branch_name']) : $shop->branch_name,
            'latitude' => $lat,
            'longitude' => $lng,
            'gps_address' => $gpsAddress,
            'address' => $vendorAddress,
            'same_as_gps_location' => ($vendorAddress === $gpsAddress) && $sameAsGps,
            'city' => $city,
            'area' => $area,
            'phone' => !empty($validated['phone']) ? trim($validated['phone']) : $shop->phone,
        ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Shop dual addresses updated successfully.',
                'data' => $shop->fresh(),
            ]);
        }

        return redirect("/vendor/dashboard?shop_id={$shop->id}&tab=branches")
            ->with('success', "Updated location coordinates and public storefront address for {$shop->name}" . ($shop->display_branch_tag ? " [{$shop->display_branch_tag}]" : '') . '.');
    }

    /**
     * Submit a real itemized Quote 1 Bid on an open Marketplace RepairRequest.
     * Routes: POST /vendor/requests/{id}/bid, POST /requests/{id}/bids
     */
    public function submitBid(Request $request, $requestId)
    {
        if ($redirect = $this->guardUnapprovedAuthenticatedVendor($request)) {
            return $redirect;
        }

        $repairRequest = RepairRequest::findOrFail($requestId);

        if (!in_array(strtoupper((string) $repairRequest->status), ['PENDING', 'BIDDING'], true)) {
            $msg = "Repair Request #{$repairRequest->id} is currently {$repairRequest->status} and is no longer accepting new marketplace bids.";
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['status' => 'error', 'message' => $msg], 422);
            }
            return redirect()->back()->withErrors(['bid' => $msg]);
        }

        $validated = $request->validate([
            'shop_id' => 'nullable|integer|exists:repair_shops,id',
            'parts_cost' => 'nullable|numeric|min:0|max:1000000',
            'parts_items' => 'nullable|array|max:30',
            'parts_items.*.name' => 'nullable|string|max:160',
            'parts_items.*.cost' => 'nullable|numeric|min:0|max:1000000',
            'labor_cost' => 'nullable|numeric|min:0|max:1000000',
            'proposed_cost' => 'nullable|numeric|min:1|max:2000000',
            'estimated_days' => 'required|integer|min:1|max:60',
            'has_warranty' => 'nullable|boolean',
            'warranty_configured' => 'nullable',
            'warranty_days' => 'nullable|integer|min:0|max:730',
            'technician_note' => 'nullable|string|max:1000',
        ]);

        $vendorProfile = $this->resolveVendorProfile($request);
        $shop = !empty($validated['shop_id'])
            ? RepairShop::findOrFail($validated['shop_id'])
            : ($vendorProfile->shops()->orderBy('id')->first() ?? RepairShop::orderBy('id')->firstOrFail());

        if ($request->user() && $request->user()->role === 'vendor') {
            $ownedProfile = VendorProfile::where('user_id', $request->user()->id)->first();
            if ($ownedProfile && (int) $shop->vendor_profile_id !== (int) $ownedProfile->id) {
                abort(403, 'You can only submit bids on behalf of your own verified workshop.');
            }
        }

        $laborCost = round((float) ($validated['labor_cost'] ?? 0), 2);
        $hasPartsArray = $request->has('parts_items') && is_array($request->input('parts_items'));

        if ($hasPartsArray) {
            $normalizedParts = Bid::normalizePartsList($request->input('parts_items'));
            $partsCost = count($normalizedParts) > 0
                ? round((float) collect($normalizedParts)->sum('cost'), 2)
                : round((float) ($validated['parts_cost'] ?? 0), 2);
            if (count($normalizedParts) === 0 && $partsCost > 0) {
                $normalizedParts = [['name' => 'Component / Parts Estimate', 'cost' => $partsCost]];
            }
            $proposedCost = round($partsCost + $laborCost, 2);
        } else {
            $partsCost = round((float) ($validated['parts_cost'] ?? 0), 2);
            $proposedCost = isset($validated['proposed_cost']) && (float) $validated['proposed_cost'] > 0
                ? round((float) $validated['proposed_cost'], 2)
                : round($partsCost + $laborCost, 2);
            if ($partsCost == 0.0 && $laborCost == 0.0 && $proposedCost > 0) {
                $partsCost = round($proposedCost * 0.65, 2);
                $laborCost = round($proposedCost - $partsCost, 2);
            }
            $normalizedParts = $partsCost > 0
                ? [['name' => 'Component / Parts Estimate', 'cost' => $partsCost]]
                : [];
        }

        if ($proposedCost <= 0) {
            throw ValidationException::withMessages([
                'parts_cost' => 'Please enter at least one priced part or a valid labor fee greater than ৳0.',
            ]);
        }

        $warrantyConfig = self::resolveWarrantyConfig($request, $validated, 90);

        $lineItems = array_map(
            fn (array $part) => ['item' => $part['name'], 'cost' => (float) $part['cost'], 'type' => 'part'],
            $normalizedParts
        );
        if ($laborCost > 0 || empty($lineItems)) {
            $lineItems[] = ['item' => 'Bench Diagnostics & Labor', 'cost' => $laborCost, 'type' => 'labor'];
        }

        $wasUpdate = Bid::where('repair_request_id', $repairRequest->id)
            ->where('shop_id', $shop->id)
            ->exists();

        $bid = Bid::updateOrCreate(
            [
                'repair_request_id' => $repairRequest->id,
                'shop_id' => $shop->id,
            ],
            [
                'parts_cost' => $partsCost,
                'parts_items' => $normalizedParts,
                'labor_cost' => $laborCost,
                'proposed_cost' => $proposedCost,
                'estimated_days' => (int) $validated['estimated_days'],
                'has_warranty' => $warrantyConfig['has_warranty'],
                'warranty_days' => $warrantyConfig['warranty_days'],
                'line_items' => $lineItems,
                'technician_note' => !empty($validated['technician_note'])
                    ? $validated['technician_note']
                    : "Verified {$shop->name} bench quote" . ($warrantyConfig['has_warranty'] ? " with {$warrantyConfig['warranty_days']}-day warranty." : '.'),
                'status' => 'ACTIVE',
            ]
        );

        if ($repairRequest->status === 'PENDING') {
            $repairRequest->update(['status' => 'BIDDING']);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => $wasUpdate ? 'Diagnostic quote updated successfully.' : 'Diagnostic quote submitted successfully.',
                'data' => $bid->load('shop'),
            ], $wasUpdate ? 200 : 201);
        }

        $branchLabel = $shop->display_branch_tag ? " [{$shop->display_branch_tag}]" : '';
        $verb = $wasUpdate ? 'updated to' : 'submitted at';

        return redirect()
            ->back()
            ->with(
                'success',
                "Quote 1 bid {$verb} ৳" . number_format($proposedCost, 2) . " by {$shop->name}{$branchLabel} on Request #{$repairRequest->id}!"
            );
    }

    /**
     * Update an active RepairJob's bench status (and optionally dispatch Quote 2 or settle ledger on COMPLETED).
     * Route: POST /vendor/jobs/{jobId}/status
     */
    public function updateJobStatus(Request $request, $jobId)
    {
        if ($redirect = $this->guardUnapprovedAuthenticatedVendor($request)) {
            return $redirect;
        }

        $job = RepairJob::with(['repairRequest', 'shop.vendorProfile'])->findOrFail($jobId);

        if ($request->user() && $request->user()->role === 'vendor') {
            $ownedProfile = VendorProfile::where('user_id', $request->user()->id)->first();
            if ($ownedProfile && (int) ($job->shop?->vendor_profile_id) !== (int) $ownedProfile->id) {
                abort(403, 'You can only update bench milestones for repair jobs assigned to your own workshop.');
            }
        }

        $validated = $request->validate([
            'status' => 'required|in:AT_SHOP,DIAGNOSING,WAITING_APPROVAL,REPAIRING,RETURN_SCHEDULED,COMPLETED',
            'final_quoted_amount' => 'nullable|numeric|min:50|max:2000000',
            'parts_items' => 'nullable|array|max:30',
            'parts_items.*.name' => 'nullable|string|max:160',
            'parts_items.*.cost' => 'nullable|numeric|min:0|max:1000000',
            'labor_cost' => 'nullable|numeric|min:0|max:1000000',
            'has_warranty' => 'nullable|boolean',
            'warranty_configured' => 'nullable',
            'warranty_days' => 'nullable|integer|min:0|max:730',
            'diagnosis_notes' => 'nullable|string|max:1000',
        ]);

        $newStatus = $validated['status'];
        $jobUpdates = ['status' => $newStatus === 'RETURN_SCHEDULED' ? 'READY_TO_RETURN' : $newStatus];
        $updatedFinalQuote = null;

        if ($newStatus === 'REPAIRING' && !$job->started_at) {
            $jobUpdates['started_at'] = now();
        }
        if ($request->has('diagnosis_notes')) {
            $trimmedNotes = trim((string) ($validated['diagnosis_notes'] ?? ''));
            if ($trimmedNotes !== '') {
                $jobUpdates['diagnosis_notes'] = $trimmedNotes;
            }
        }

        $hasQuoteBreakdownInput = $request->has('parts_items') || $request->filled('labor_cost');
        if ($hasQuoteBreakdownInput) {
            $normalizedParts = Bid::normalizePartsList($request->input('parts_items', []));
            $partsCost = round((float) collect($normalizedParts)->sum('cost'), 2);
            $laborCost = round((float) ($validated['labor_cost'] ?? 0), 2);
            $computedFinal = round($partsCost + $laborCost, 2);

            if ($computedFinal <= 0) {
                throw ValidationException::withMessages([
                    'labor_cost' => 'Please enter at least one priced part or a bench labor fee greater than ৳0.',
                ]);
            }

            $finalQuoteItems = array_map(
                fn (array $p) => ['title' => $p['name'], 'amount' => (float) $p['cost'], 'type' => 'part'],
                $normalizedParts
            );
            if ($laborCost > 0 || empty($finalQuoteItems)) {
                $finalQuoteItems[] = ['title' => 'Technician Labor & Calibration', 'amount' => $laborCost, 'type' => 'labor'];
            }

            $jobUpdates['parts_items'] = $normalizedParts;
            $jobUpdates['parts_cost'] = $partsCost;
            $jobUpdates['labor_cost'] = $laborCost;
            $jobUpdates['final_quoted_amount'] = $computedFinal;
            $jobUpdates['final_quote_items'] = $finalQuoteItems;
            $updatedFinalQuote = $computedFinal;
        } elseif (!empty($validated['final_quoted_amount'])) {
            $updatedFinalQuote = round((float) $validated['final_quoted_amount'], 2);
            $jobUpdates['final_quoted_amount'] = $updatedFinalQuote;
        }

        if ($request->has('warranty_configured') || $request->has('has_warranty') || $request->filled('warranty_days')) {
            $warrantyConfig = self::resolveWarrantyConfig($request, $validated, $job->resolvedWarrantyDays() ?: 90);
            $jobUpdates['has_warranty'] = $warrantyConfig['has_warranty'];
            $jobUpdates['warranty_days'] = $warrantyConfig['warranty_days'];
        }

        if ($newStatus === 'COMPLETED') {
            $jobUpdates['completed_at'] = now();
        }

        $job->update($jobUpdates);

        if ($job->repairRequest) {
            $reqUpdates = ['status' => $newStatus];
            if ($updatedFinalQuote !== null) {
                $reqUpdates['quoted_final_amount'] = $updatedFinalQuote;
            }
            $job->repairRequest->update($reqUpdates);

            if ($newStatus === 'RETURN_SCHEDULED') {
                $returnDelivery = app(DeliveryLifecycleService::class)->ensureDeliveryJob(
                    $job->repairRequest->fresh(),
                    $job->fresh(),
                    'TO_CUSTOMER'
                );

                if ($job->repairRequest->customer_id) {
                    UserNotification::create([
                        'user_id' => $job->repairRequest->customer_id,
                        'type' => 'delivery_milestone',
                        'severity' => 'info',
                        'title' => 'Ready for Return Delivery',
                        'message' => ($job->shop?->name ?? 'The workshop') . " completed bench service for #{$job->repairRequest->id} and queued a return courier ({$returnDelivery->tracking_code}).",
                        'action_url' => '/requests/' . $job->repairRequest->id . '/track',
                        'is_read' => false,
                    ]);
                }
            }

            // When repair completes, generate Invoice + Warranty + Escrow Release + 10%/90% Double-Entry Ledger
            if ($newStatus === 'COMPLETED') {
                self::settleCompletedJobFinancials($job->fresh(['repairRequest', 'shop.vendorProfile']));
            }
        }

        return redirect('/vendor/dashboard?tab=assigned_jobs')
            ->with(
                'success',
                "Updated Job #{$job->id} status to {$newStatus}"
                . ($newStatus === 'WAITING_APPROVAL' ? ' — Final quote sent to customer for approval.' : '')
                . ($newStatus === 'RETURN_SCHEDULED' ? ' — Return pickup task queued in courier dispatch pool.' : '')
                . ($newStatus === 'COMPLETED' ? ' — Invoice, warranty & 10%/90% ledger settled.' : '')
            );
    }

    /**
     * Resolve warranty toggle and day count across Quote 1 and Quote 2 forms.
     */
    private static function resolveWarrantyConfig(Request $request, array $validated, int $defaultDays = 90): array
    {
        if ($request->has('warranty_configured') || $request->has('has_warranty')) {
            $hasWarranty = $request->boolean('has_warranty');
            $rawDays = (int) ($validated['warranty_days'] ?? ($defaultDays > 0 ? $defaultDays : 90));

            return [
                'has_warranty' => $hasWarranty,
                'warranty_days' => $hasWarranty ? max(1, $rawDays) : 0,
            ];
        }

        $hasExplicitDays = array_key_exists('warranty_days', $validated) && $validated['warranty_days'] !== null;
        $days = $hasExplicitDays ? max(0, (int) $validated['warranty_days']) : $defaultDays;

        return [
            'has_warranty' => $days > 0,
            'warranty_days' => $days,
        ];
    }

    /**
     * Idempotent end-to-end completion settlement: PAID Invoice + Line Items + Verified Warranty
     * + PaymentTransaction + Escrow HOLD/RELEASE + R16 10%/90% Double-Entry FinancialLedger.
     */
    public static function settleCompletedJobFinancials(RepairJob $job): void
    {
        $job->loadMissing(['repairRequest', 'shop.vendorProfile', 'acceptedBid']);
        if (!$job->repairRequest) {
            return;
        }

        $gross = (float) ($job->final_quoted_amount ?? $job->repairRequest->quoted_final_amount ?? 2500);
        if ($gross <= 0) {
            return;
        }

        $now = now();
        $commission = round($gross * 0.10, 2);
        $vendorNet = round($gross - $commission, 2);

        $existingInvoice = DB::table('invoices')
            ->where('repair_job_id', $job->id)
            ->first();

        if ($existingInvoice) {
            $invoiceId = (int) $existingInvoice->id;
            DB::table('invoices')->where('id', $invoiceId)->update([
                'status' => 'PAID',
                'subtotal_amount' => $gross,
                'commission_amount' => $commission,
                'total_amount' => $gross + (float) ($existingInvoice->delivery_amount ?? 0),
                'paid_at' => $now,
                'updated_at' => $now,
            ]);
        } else {
            $invoiceId = DB::table('invoices')->insertGetId([
                'repair_request_id' => $job->repairRequest->id,
                'repair_job_id' => $job->id,
                'shop_id' => $job->shop_id,
                'type' => 'FINAL',
                'status' => 'PAID',
                'invoice_number' => 'INV-' . $now->format('Y') . '-' . str_pad((string) $job->id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(Str::random(3)),
                'subtotal_amount' => $gross,
                'delivery_amount' => 0.00,
                'discount_amount' => 0.00,
                'commission_amount' => $commission,
                'total_amount' => $gross,
                'issued_at' => $now,
                'due_at' => $now,
                'paid_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $primarySvcId = ShopService::where('shop_id', $job->shop_id)->value('id');
            $invoiceLines = [];

            if (($job->repairRequest->source ?? 'MARKETPLACE') !== 'DIRECT_SERVICE') {
                $partsItems = $job->resolvedPartsItems();
                $partsSum = round((float) collect($partsItems)->sum('cost'), 2);
                $laborAmount = $job->resolvedLaborCost();

                if (!empty($partsItems) && abs(($partsSum + $laborAmount) - $gross) <= 0.02) {
                    foreach ($partsItems as $part) {
                        $cost = round((float) $part['cost'], 2);
                        $invoiceLines[] = [
                            'invoice_id' => $invoiceId,
                            'shop_service_id' => $primarySvcId,
                            'title' => $part['name'],
                            'description' => 'Itemized replacement part approved in workshop quote',
                            'quantity' => 1,
                            'unit_price' => $cost,
                            'total_price' => $cost,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }

                    $remainingLabor = round($gross - $partsSum, 2);
                    if ($remainingLabor > 0) {
                        $invoiceLines[] = [
                            'invoice_id' => $invoiceId,
                            'shop_service_id' => $primarySvcId,
                            'title' => 'Certified Technician Labor & Calibration',
                            'description' => $job->diagnosis_notes ?: 'Completed bench diagnostics & quality verification',
                            'quantity' => 1,
                            'unit_price' => $remainingLabor,
                            'total_price' => $remainingLabor,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
            }

            if (empty($invoiceLines)) {
                $invoiceLines[] = [
                    'invoice_id' => $invoiceId,
                    'shop_service_id' => $primarySvcId,
                    'title' => $job->repairRequest->title,
                    'description' => $job->diagnosis_notes ?: 'Completed workshop repair & quality calibration',
                    'quantity' => 1,
                    'unit_price' => $gross,
                    'total_price' => $gross,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::table('invoice_line_items')->insert($invoiceLines);
        }

        $hasWarranty = $job->hasWarrantyCoverage();
        $warrantyDays = $hasWarranty ? $job->resolvedWarrantyDays() : 0;

        DB::table('warranties')->updateOrInsert(
            ['repair_job_id' => $job->id],
            [
                'title' => $hasWarranty
                    ? "{$warrantyDays}-Day Meramot Verified Repair Warranty"
                    : 'Standard Bench Sign-Off (No Extended Warranty)',
                'details' => $hasWarranty
                    ? "Covers replaced hardware components and bench workmanship for {$warrantyDays} days from completion."
                    : 'Completed without extended parts warranty coverage per accepted workshop quote.',
                'duration_days' => $warrantyDays,
                'starts_at' => $now,
                'ends_at' => $now->copy()->addDays($warrantyDays),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        $payment = PaymentTransaction::firstOrCreate(
            ['repair_request_id' => $job->repairRequest->id],
            [
                'user_id' => $job->repairRequest->customer_id,
                'invoice_id' => $invoiceId,
                'amount' => $gross,
                'currency' => 'BDT',
                'payment_method' => 'CASH',
                'status' => 'PAID',
                'paid_at' => $now,
                'transaction_reference' => 'MRM-JOB-' . $job->id . '-' . strtoupper(Str::random(4)),
            ]
        );
        if ($payment->status !== 'PAID' || !$payment->invoice_id) {
            $payment->update([
                'invoice_id' => $invoiceId,
                'status' => 'PAID',
                'paid_at' => $now,
                'amount' => $gross,
            ]);
        }

        $vendorUserId = $job->shop?->vendorProfile?->user_id;
        $hasEscrowRelease = DB::table('escrow_ledgers')
            ->where('payment_id', $payment->id)
            ->where('action', 'RELEASE')
            ->exists();

        if (!$hasEscrowRelease) {
            DB::table('escrow_ledgers')->insert([
                [
                    'payment_id' => $payment->id,
                    'repair_request_id' => $job->repairRequest->id,
                    'customer_user_id' => $job->repairRequest->customer_id,
                    'vendor_user_id' => $vendorUserId,
                    'amount' => $gross,
                    'gross_amount' => $gross,
                    'platform_commission_amount' => $commission,
                    'vendor_net_amount' => $vendorNet,
                    'action' => 'HOLD',
                    'note' => "Escrow hold captured for Repair Job #{$job->id}",
                    'created_at' => $job->started_at ?? $now,
                    'updated_at' => $job->started_at ?? $now,
                ],
                [
                    'payment_id' => $payment->id,
                    'repair_request_id' => $job->repairRequest->id,
                    'customer_user_id' => $job->repairRequest->customer_id,
                    'vendor_user_id' => $vendorUserId,
                    'amount' => $gross,
                    'gross_amount' => $gross,
                    'platform_commission_amount' => $commission,
                    'vendor_net_amount' => $vendorNet,
                    'action' => 'RELEASE',
                    'note' => "Escrow released upon completion of Repair Job #{$job->id}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ]);
        }

        $alreadySettled = FinancialLedger::where('payment_id', $payment->id)
            ->where('entry_type', 'CUSTOMER_PAYMENT')
            ->exists();

        if (!$alreadySettled) {
            AdminLedgerController::settleOrderWithCommission(
                $gross,
                (int) $job->shop_id,
                (int) $payment->id,
                $invoiceId,
                0.10,
                "Repair Job #{$job->id} ({$job->repairRequest->title})"
            );
        }
    }

    /**
     * Enforce Admin Verification Gate for authenticated vendors whose profile is not yet `approved`
     * or whose primary shop has not completed post-approval setup.
     */
    /**
     * Block non-vendor roles, unapproved vendors, or unconfigured shops from accessing
     * the Vendor Operations Dashboard, redirecting each role to its proper workspace.
     */
    private function guardUnapprovedAuthenticatedVendor(Request $request)
    {
        if ($request->filled('vendor_profile_id')) {
            return null;
        }

        $user = $request->user();

        if (!$user) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return null;
            }

            return redirect()->route('login')
                ->with('warning', 'Please sign in with your vendor partner account to open the Vendor Dashboard.');
        }

        if (in_array($user->role, ['delivery_agent', 'delivery'], true)) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Delivery partner accounts cannot access the Vendor Dashboard.',
                ], 403);
            }

            return redirect('/delivery');
        }

        if ($user->role === 'customer') {
            $ownedCustomerVendor = VendorProfile::where('user_id', $user->id)->first();
            if (!$ownedCustomerVendor) {
                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'status' => 'error',
                        'message' => 'Vendor partner account required.',
                    ], 403);
                }

                return redirect()->route('vendor.apply')
                    ->with('warning', 'Apply as a partner repair shop below (or sign in to your vendor account) to access the Vendor Dashboard.');
            }
        }

        if ($user->role !== 'vendor') {
            return null;
        }

        $owned = VendorProfile::with('shops')->where('user_id', $user->id)->first();
        if (!$owned) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please submit your vendor partner application first.',
                ], 403);
            }

            return redirect()->route('vendor.apply');
        }

        if ($owned->verification_status !== 'approved') {
            $statusUpper = strtoupper(str_replace('_', ' ', (string) $owned->verification_status));
            $msg = "Your vendor application for '{$owned->shop_name}' is currently {$statusUpper}. Admin verification is required before accessing the Vendor Operations Dashboard.";

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $msg,
                    'verification_status' => $owned->verification_status,
                ], 403);
            }

            return redirect()->route('vendor.onboarding')->with('warning', $msg);
        }

        $primaryShop = $owned->shops->sortBy('id')->first();
        if ($primaryShop && !$primaryShop->setup_complete && $primaryShop->inspection_fee === null) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Please complete your post-approval shop setup before accessing the Vendor Dashboard.',
                ], 403);
            }

            return redirect()->route('vendor.setup-shop')
                ->with('warning', 'Your vendor application is approved. Complete your shop pricing and service configuration below to access the Vendor Dashboard.');
        }

        return null;
    }

    /**
     * Resolve active vendor profile for authenticated vendor or demo preview (`ProTech Solutions`).
     */
    private function resolveVendorProfile(Request $request): VendorProfile
    {
        if ($request->filled('vendor_profile_id')) {
            $explicit = VendorProfile::find($request->input('vendor_profile_id'));
            if ($explicit) {
                return $explicit;
            }
        }

        if ($request->user()) {
            $owned = VendorProfile::where('user_id', $request->user()->id)->first();
            if ($owned) {
                return $owned;
            }
        }

        $demoProtech = VendorProfile::where('shop_name', 'ProTech Solutions')->first()
            ?? VendorProfile::orderBy('id')->first();

        if ($demoProtech) {
            return $demoProtech;
        }

        $fallbackUser = $request->user() ?? User::first() ?? User::factory()->create(['role' => 'vendor']);

        return VendorProfile::create([
            'user_id' => $fallbackUser->id,
            'shop_name' => 'ProTech Solutions',
            'brand_name' => 'ProTech Solutions',
            'is_branched' => false,
            'business_license_no' => 'TRAD/BD/000001',
            'nid_number' => '1990000000001',
            'verification_status' => 'approved',
            'shop_address' => 'Level 3, House 42, Banani DOHS, Dhaka',
            'gps_address' => 'Road 9, Banani DOHS, Banani, Dhaka',
            'same_as_gps_location' => false,
            'city' => 'Dhaka',
            'area' => 'Banani',
            'shop_latitude' => 23.790428,
            'shop_longitude' => 90.398003,
            'categories' => ['Laptop repair', 'Smartphone repair'],
            'commission_rate' => 10.00,
            'rating' => 0.00,
        ]);
    }
}

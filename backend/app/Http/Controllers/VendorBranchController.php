<?php

namespace App\Http\Controllers;

use App\Models\Bid;
use App\Models\FinancialLedger;
use App\Models\Invoice;
use App\Models\InvoiceLineItem;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ShopReview;
use App\Models\ShopService;
use App\Models\SparePartListing;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VendorProfile;
use App\Services\BangladeshLocationResolver;
use App\Services\DeliveryLifecycleService;
use App\Services\MarketplaceSearchEngine;
use App\Support\InputSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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

        $escrowSummary = VendorPayoutController::computeShopEscrowSummary($scopedShopIds);
        $vendorPayoutHistory = \App\Models\VendorPayout::with('shop')
            ->whereIn('shop_id', $scopedShopIds)
            ->orderByDesc('id')
            ->limit(25)
            ->get();

        $branchMetrics = [
            'gross_inflow' => $ledgerGross,
            'commission_deducted' => $escrowSummary['commission_deducted'],
            'net_allocated' => $escrowSummary['net_allocated'],
            'paid_out' => $escrowSummary['paid_out'],
            'pending_requested' => $escrowSummary['pending_requested'],
            'available_to_request' => $escrowSummary['available_to_request'],
            'active_jobs_count' => $activeJobs->count(),
            'avg_rating' => $avgRating,
            'total_reviews' => $totalReviews,
            'branch_count' => $branches->count(),
        ];

        $bangladeshDistricts = array_column(BangladeshLocationResolver::DISTRICTS, 'city');
        sort($bangladeshDistricts);

        // Scoped Spare Parts Inventory for Vendor Dashboard (`tab=spare_parts`)
        $spQuery = \App\Models\SparePartListing::with(['shop', 'warrantyCoverage'])
            ->whereIn('shop_id', $scopedShopIds);

        $vendorSparePartStats = [
            'total_count' => (clone $spQuery)->count(),
            'active_count' => (clone $spQuery)->where('is_active', true)->count(),
            'wholesale_count' => (clone $spQuery)->where('wholesale_enabled', true)->count(),
            'offer_count' => (clone $spQuery)->where('offer_enabled', true)->count(),
            'warranty_count' => \Illuminate\Support\Facades\Schema::hasTable('spare_part_warranties')
                ? (clone $spQuery)->whereHas('warrantyCoverage')->count()
                : (clone $spQuery)->where('warranty_days', '>', 0)->count(),
        ];

        $spSearch = trim((string) $request->query('sp_q', ''));
        $spRelevanceOrder = MarketplaceSearchEngine::applySearch(
            $spQuery,
            $spSearch,
            ['title', 'compatible_model', 'brand', 'part_category'],
            ['sku']
        );

        $spFilter = trim((string) $request->query('sp_filter', 'all'));
        if ($spFilter === 'on_sale') {
            $spQuery->where('offer_enabled', true);
        } elseif ($spFilter === 'wholesale') {
            $spQuery->where('wholesale_enabled', true);
        } elseif ($spFilter === 'specs') {
            $spQuery->where('has_specs', true);
        } elseif ($spFilter === 'paused') {
            $spQuery->where('is_active', false);
        }

        if ($spRelevanceOrder !== null) {
            $spQuery->orderByRaw($spRelevanceOrder['sql'], $spRelevanceOrder['bindings']);
        }

        $vendorSpareParts = $spQuery
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(18, ['*'], 'sp_page')
            ->withQueryString();

        $sparePartCategories = SparePartController::PART_CATEGORIES;
        $sparePartBrands = SparePartController::POPULAR_BRANDS;
        $vendorAnalytics = $this->buildVendorPerformanceAnalytics($vendorProfile, $scopedShopIds);

        return view('vendor.dashboard', compact(
            'vendorProfile',
            'branches',
            'selectedShopId',
            'activeBranch',
            'directOrders',
            'marketplaceRequests',
            'activeJobs',
            'branchMetrics',
            'vendorPayoutHistory',
            'bangladeshDistricts',
            'vendorSpareParts',
            'vendorSparePartStats',
            'sparePartCategories',
            'sparePartBrands',
            'spSearch',
            'spFilter',
            'vendorAnalytics'
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
        $cleanPhone = InputSanitizer::sanitizePhone($validated['phone'] ?? null);
        if ($cleanPhone !== null && !InputSanitizer::isValidBdPhone($cleanPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678).',
            ]);
        }

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
            'phone' => $cleanPhone ?? ($templateShop->phone ?? $vendorProfile->phone ?? '+8801711000001'),
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

        $cleanPhone = InputSanitizer::sanitizePhone($validated['phone'] ?? null);
        if ($cleanPhone !== null && !InputSanitizer::isValidBdPhone($cleanPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678).',
            ]);
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
            'phone' => $cleanPhone ?? $shop->phone,
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
            'parts_items.*.warranty_configured' => 'nullable',
            'parts_items.*.has_warranty' => 'nullable|boolean',
            'parts_items.*.warranty_days' => 'nullable|integer|min:0|max:730',
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
        $topLevelWarranty = self::resolveWarrantyConfig($request, $validated, 90);
        $hasPartsArray = $request->has('parts_items') && is_array($request->input('parts_items'));

        if ($hasPartsArray) {
            $normalizedParts = Bid::normalizePartsList(
                $request->input('parts_items'),
                false,
                $topLevelWarranty['warranty_days'],
                $topLevelWarranty['has_warranty']
            );
            $partsCost = count($normalizedParts) > 0
                ? round((float) collect($normalizedParts)->sum('cost'), 2)
                : round((float) ($validated['parts_cost'] ?? 0), 2);
            if (count($normalizedParts) === 0 && $partsCost > 0) {
                $normalizedParts = [[
                    'name' => 'Component / Parts Estimate',
                    'cost' => $partsCost,
                    'has_warranty' => $topLevelWarranty['has_warranty'],
                    'warranty_days' => $topLevelWarranty['warranty_days'],
                ]];
            }
            $proposedCost = round($partsCost + $laborCost, 2);

            if (count($normalizedParts) > 0) {
                $summary = Bid::summarizePartsWarranty($normalizedParts);
                $warrantyConfig = [
                    'has_warranty' => $summary['has_warranty'],
                    'warranty_days' => $summary['warranty_days'],
                ];
            } else {
                $hasExplicitTopWarranty = $request->has('warranty_configured')
                    || $request->has('has_warranty')
                    || $request->filled('warranty_days');
                $warrantyConfig = $hasExplicitTopWarranty
                    ? $topLevelWarranty
                    : ['has_warranty' => false, 'warranty_days' => 0];
            }
        } else {
            $partsCost = round((float) ($validated['parts_cost'] ?? 0), 2);
            $proposedCost = isset($validated['proposed_cost']) && (float) $validated['proposed_cost'] > 0
                ? round((float) $validated['proposed_cost'], 2)
                : round($partsCost + $laborCost, 2);
            if ($partsCost == 0.0 && $laborCost == 0.0 && $proposedCost > 0) {
                $partsCost = round($proposedCost * 0.65, 2);
                $laborCost = round($proposedCost - $partsCost, 2);
            }
            $warrantyConfig = $topLevelWarranty;
            $normalizedParts = $partsCost > 0
                ? [[
                    'name' => 'Component / Parts Estimate',
                    'cost' => $partsCost,
                    'has_warranty' => $warrantyConfig['has_warranty'],
                    'warranty_days' => $warrantyConfig['warranty_days'],
                ]]
                : [];
        }

        if ($proposedCost <= 0) {
            throw ValidationException::withMessages([
                'parts_cost' => 'Please enter at least one priced part or a valid labor fee greater than ৳0.',
            ]);
        }

        $lineItems = array_map(
            fn (array $part) => [
                'item' => $part['name'],
                'cost' => (float) $part['cost'],
                'type' => 'part',
                'has_warranty' => (bool) $part['has_warranty'],
                'warranty_days' => (int) $part['warranty_days'],
            ],
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
            'parts_items.*.warranty_configured' => 'nullable',
            'parts_items.*.has_warranty' => 'nullable|boolean',
            'parts_items.*.warranty_days' => 'nullable|integer|min:0|max:730',
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

        $hasTopWarranty = $request->has('warranty_configured') || $request->has('has_warranty') || $request->filled('warranty_days');
        $topWarranty = $hasTopWarranty
            ? self::resolveWarrantyConfig($request, $validated, $job->resolvedWarrantyDays() ?: 90)
            : ['has_warranty' => $job->hasWarrantyCoverage(), 'warranty_days' => $job->resolvedWarrantyDays() ?: 90];

        $hasQuoteBreakdownInput = $request->has('parts_items') || $request->filled('labor_cost');
        if ($hasQuoteBreakdownInput) {
            $normalizedParts = Bid::normalizePartsList(
                $request->input('parts_items', []),
                false,
                $topWarranty['warranty_days'],
                $topWarranty['has_warranty']
            );
            $partsCost = round((float) collect($normalizedParts)->sum('cost'), 2);
            $laborCost = round((float) ($validated['labor_cost'] ?? 0), 2);
            $computedFinal = round($partsCost + $laborCost, 2);

            if ($computedFinal <= 0) {
                throw ValidationException::withMessages([
                    'labor_cost' => 'Please enter at least one priced part or a bench labor fee greater than ৳0.',
                ]);
            }

            $finalQuoteItems = array_map(
                fn (array $p) => [
                    'title' => $p['name'],
                    'amount' => (float) $p['cost'],
                    'type' => 'part',
                    'has_warranty' => (bool) $p['has_warranty'],
                    'warranty_days' => (int) $p['warranty_days'],
                ],
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

            if (count($normalizedParts) > 0) {
                $summary = Bid::summarizePartsWarranty($normalizedParts);
                $jobUpdates['has_warranty'] = $summary['has_warranty'];
                $jobUpdates['warranty_days'] = $summary['warranty_days'];
            } else {
                $jobUpdates['has_warranty'] = $hasTopWarranty ? $topWarranty['has_warranty'] : false;
                $jobUpdates['warranty_days'] = $hasTopWarranty ? $topWarranty['warranty_days'] : 0;
            }
        } elseif (!empty($validated['final_quoted_amount'])) {
            $updatedFinalQuote = round((float) $validated['final_quoted_amount'], 2);
            $jobUpdates['final_quoted_amount'] = $updatedFinalQuote;
            if ($hasTopWarranty) {
                $jobUpdates['has_warranty'] = $topWarranty['has_warranty'];
                $jobUpdates['warranty_days'] = $topWarranty['warranty_days'];
            }
        } elseif ($hasTopWarranty) {
            $jobUpdates['has_warranty'] = $topWarranty['has_warranty'];
            $jobUpdates['warranty_days'] = $topWarranty['warranty_days'];
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

            $requiresCourier = $job->repairRequest->requiresCourierLogistics();

            if ($newStatus === 'RETURN_SCHEDULED') {
                if ($requiresCourier) {
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
                } elseif ($job->repairRequest->customer_id) {
                    UserNotification::create([
                        'user_id' => $job->repairRequest->customer_id,
                        'type' => 'delivery_milestone',
                        'severity' => 'info',
                        'title' => 'Device Ready for In-Shop Pickup',
                        'message' => ($job->shop?->name ?? 'The workshop') . " finished servicing order #{$job->repairRequest->id}. Pick up your device at the shop and complete payment via Meramot.",
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

        $returnMsg = ($job->repairRequest && !$job->repairRequest->requiresCourierLogistics())
            ? ' — Customer notified that device is ready for in-shop pickup.'
            : ' — Return pickup task queued in courier dispatch pool.';

        return redirect('/vendor/dashboard?tab=assigned_jobs')
            ->with(
                'success',
                "Updated Job #{$job->id} status to {$newStatus}"
                . ($newStatus === 'WAITING_APPROVAL' ? ' — Final quote sent to customer for approval.' : '')
                . ($newStatus === 'RETURN_SCHEDULED' ? $returnMsg : '')
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

        $vendorNet = round((float) ($job->final_quoted_amount ?? $job->repairRequest->quoted_final_amount ?? 2500), 2);
        if ($vendorNet <= 0) {
            return;
        }

        $now = now();
        $ratePct = ServicePackageController::resolveCommissionRatePct($job->shop);
        $rateDecimal = $ratePct / 100.0;
        $commission = round($vendorNet * $rateDecimal, 2);
        $gross = $vendorNet; // Keep local variable `$gross` for invoice line-item sum checks

        $existingInvoice = DB::table('invoices')
            ->where(function ($q) use ($job) {
                $q->where('repair_job_id', $job->id)
                  ->orWhere('repair_request_id', $job->repairRequest->id);
            })
            ->orderByDesc('id')
            ->first();

        $deliveryAmt = 0.0;

        if ($existingInvoice) {
            $invoiceId = (int) $existingInvoice->id;
            $existingLineSum = (float) DB::table('invoice_line_items')
                ->where('invoice_id', $invoiceId)
                ->sum('total_price');
            $deliveryAmt = (float) ($existingInvoice->delivery_amount ?? 0);
            $discountAmt = (float) ($existingInvoice->discount_amount ?? 0);

            // Preserve pre-paid Direct-Order vendor subtotal if line items already match; otherwise sync line items to $vendorNet
            $effectiveSubtotal = ($existingLineSum > 0 && abs($existingLineSum - (float) $existingInvoice->subtotal_amount) <= 0.02 && $existingInvoice->status === 'PAID')
                ? round($existingLineSum, 2)
                : $vendorNet;
            $effectiveCommission = ($existingInvoice->status === 'PAID' && (float) $existingInvoice->commission_amount > 0)
                ? round((float) $existingInvoice->commission_amount, 2)
                : round($effectiveSubtotal * $rateDecimal, 2);

            DB::table('invoices')->where('id', $invoiceId)->update([
                'repair_job_id' => $job->id,
                'shop_id' => $job->shop_id,
                'status' => 'PAID',
                'subtotal_amount' => $effectiveSubtotal,
                'commission_amount' => $effectiveCommission,
                'total_amount' => max(0.0, round($effectiveSubtotal + $effectiveCommission + $deliveryAmt - $discountAmt, 2)),
                'paid_at' => $existingInvoice->paid_at ?? $now,
                'updated_at' => $now,
            ]);

            if (abs($existingLineSum - $effectiveSubtotal) > 0.02) {
                DB::table('invoice_line_items')->where('invoice_id', $invoiceId)->delete();
                $primarySvcId = ShopService::where('shop_id', $job->shop_id)->value('id');
                DB::table('invoice_line_items')->insert([[
                    'invoice_id' => $invoiceId,
                    'shop_service_id' => $primarySvcId,
                    'title' => $job->repairRequest->title,
                    'description' => $job->diagnosis_notes ?: '100% Vendor workshop repair price (excl. platform fee & courier)',
                    'quantity' => 1,
                    'unit_price' => $effectiveSubtotal,
                    'total_price' => $effectiveSubtotal,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]]);
            }

            $gross = $effectiveSubtotal;
            $vendorNet = $effectiveSubtotal;
            $commission = $effectiveCommission;
        } else {
            $deliveryAmt = ($job->repairRequest->preferred_pickup ?? false)
                ? (($job->repairRequest->delivery_type === 'REGULAR') ? 80.0 : 150.0)
                : 0.0;
            $invoiceId = DB::table('invoices')->insertGetId([
                'repair_request_id' => $job->repairRequest->id,
                'repair_job_id' => $job->id,
                'shop_id' => $job->shop_id,
                'type' => 'FINAL',
                'status' => 'PAID',
                'invoice_number' => 'INV-' . $now->format('Y') . '-' . str_pad((string) $job->id, 4, '0', STR_PAD_LEFT) . '-' . strtoupper(Str::random(3)),
                'subtotal_amount' => $vendorNet,
                'delivery_amount' => $deliveryAmt,
                'discount_amount' => 0.00,
                'commission_amount' => $commission,
                'total_amount' => round($vendorNet + $commission + $deliveryAmt, 2),
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
                        $partDays = (int) ($part['warranty_days'] ?? 0);
                        $partDesc = (!empty($part['has_warranty']) && $partDays > 0)
                            ? "Itemized replacement part • {$partDays}-day warranty included"
                            : 'Itemized replacement part • No extended part warranty';
                        $invoiceLines[] = [
                            'invoice_id' => $invoiceId,
                            'shop_service_id' => $primarySvcId,
                            'title' => $part['name'],
                            'description' => $partDesc,
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

        $warrantiedPartLabels = [];
        foreach ($job->resolvedPartsItems() as $part) {
            $partDays = (int) ($part['warranty_days'] ?? 0);
            if (!empty($part['has_warranty']) && $partDays > 0) {
                $warrantiedPartLabels[] = "{$part['name']} ({$partDays} days)";
            }
        }

        $warrantyDetails = $hasWarranty
            ? (!empty($warrantiedPartLabels)
                ? 'Covered parts: ' . implode(', ', $warrantiedPartLabels) . '.'
                : "Covers replaced hardware components and bench workmanship for {$warrantyDays} days from completion.")
            : 'Completed without extended parts warranty coverage per accepted workshop quote.';

        DB::table('warranties')->updateOrInsert(
            ['repair_job_id' => $job->id],
            [
                'title' => $hasWarranty
                    ? "{$warrantyDays}-Day Meramot Verified Repair Warranty"
                    : 'Standard Bench Sign-Off (No Extended Warranty)',
                'details' => $warrantyDetails,
                'duration_days' => $warrantyDays,
                'starts_at' => $now,
                'ends_at' => $now->copy()->addDays($warrantyDays),
                'created_at' => $now,
                'updated_at' => $now,
            ]
        );

        app(\App\Services\WarrantyLifecycleService::class)->issueOrderWarrantiesForCompletedJob($job);

        $payment = PaymentTransaction::where('repair_request_id', $job->repairRequest->id)
            ->orderByRaw("CASE WHEN status = 'PAID' THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();

        if (!$payment) {
            $payment = PaymentTransaction::create([
                'repair_request_id' => $job->repairRequest->id,
                'user_id' => $job->repairRequest->customer_id,
                'invoice_id' => $invoiceId,
                'amount' => round($vendorNet + $commission + $deliveryAmt, 2),
                'currency' => 'BDT',
                'payment_method' => 'CASH',
                'status' => 'PAID',
                'paid_at' => $now,
                'transaction_reference' => 'MRM-JOB-' . $job->id . '-' . strtoupper(Str::random(4)),
            ]);
        } elseif ($payment->status !== 'PAID' || !$payment->invoice_id) {
            $payment->update([
                'invoice_id' => $invoiceId,
                'status' => 'PAID',
                'paid_at' => $payment->paid_at ?? $now,
                'amount' => (float) $payment->amount > 0 ? $payment->amount : round($vendorNet + $commission + $deliveryAmt, 2),
            ]);
        }

        $vendorUserId = $job->shop?->vendorProfile?->user_id;
        $hasEscrowHold = DB::table('escrow_ledgers')
            ->where('payment_id', $payment->id)
            ->where('action', 'HOLD')
            ->exists();
        $hasEscrowRelease = DB::table('escrow_ledgers')
            ->where('payment_id', $payment->id)
            ->where('action', 'RELEASE')
            ->exists();

        if (!$hasEscrowHold) {
            DB::table('escrow_ledgers')->insert([
                'payment_id' => $payment->id,
                'repair_request_id' => $job->repairRequest->id,
                'customer_user_id' => $job->repairRequest->customer_id,
                'vendor_user_id' => $vendorUserId,
                'amount' => (float) $payment->amount,
                'gross_amount' => round($vendorNet + $commission, 2),
                'platform_commission_amount' => $commission,
                'vendor_net_amount' => $vendorNet,
                'action' => 'HOLD',
                'note' => "Escrow hold captured for Repair Job #{$job->id}",
                'created_at' => $job->started_at ?? $now,
                'updated_at' => $job->started_at ?? $now,
            ]);
        }

        if (!$hasEscrowRelease) {
            DB::table('escrow_ledgers')->insert([
                'payment_id' => $payment->id,
                'repair_request_id' => $job->repairRequest->id,
                'customer_user_id' => $job->repairRequest->customer_id,
                'vendor_user_id' => $vendorUserId,
                'amount' => (float) $payment->amount,
                'gross_amount' => round($vendorNet + $commission, 2),
                'platform_commission_amount' => $commission,
                'vendor_net_amount' => $vendorNet,
                'action' => 'RELEASE',
                'note' => "Escrow released upon completion of Repair Job #{$job->id}",
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        $hasCustomerPayment = FinancialLedger::where('payment_id', $payment->id)
            ->where('entry_type', 'CUSTOMER_PAYMENT')
            ->exists();
        $hasVendorEarning = FinancialLedger::where('payment_id', $payment->id)
            ->where('entry_type', 'VENDOR_EARNING')
            ->exists();

        $effectiveRateDecimal = $vendorNet > 0 ? ($commission / $vendorNet) : $rateDecimal;
        $displayPct = rtrim(rtrim(number_format($effectiveRateDecimal * 100, 2), '0'), '.');

        if (!$hasCustomerPayment) {
            AdminLedgerController::settleOrderWithCommission(
                $vendorNet,
                (int) $job->shop_id,
                (int) $payment->id,
                $invoiceId,
                $effectiveRateDecimal,
                "Repair Job #{$job->id} ({$job->repairRequest->title})"
            );

            if ($deliveryAmt > 0.009) {
                AdminLedgerController::logEntry(
                    'DELIVERY_FEE',
                    'CREDIT',
                    $deliveryAmt,
                    "Platform pickup & delivery logistics fee for Repair Job #{$job->id}",
                    ['shop_id' => (int) $job->shop_id, 'payment_id' => (int) $payment->id, 'invoice_id' => $invoiceId]
                );
            }
        } elseif (!$hasVendorEarning) {
            $noteSuffix = " — Note: Repair Job #{$job->id} ({$job->repairRequest->title})";
            AdminLedgerController::logEntry(
                'PLATFORM_COMMISSION',
                'CREDIT',
                $commission,
                "{$displayPct}% platform fee on vendor earning ৳{$vendorNet} (excl. delivery){$noteSuffix}",
                ['shop_id' => (int) $job->shop_id, 'payment_id' => (int) $payment->id, 'invoice_id' => $invoiceId]
            );
            AdminLedgerController::logEntry(
                'VENDOR_EARNING',
                'DEBIT',
                $vendorNet,
                "100% vendor listed price allocated to workshop escrow (৳{$vendorNet}){$noteSuffix}",
                ['shop_id' => (int) $job->shop_id, 'payment_id' => (int) $payment->id, 'invoice_id' => $invoiceId]
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

    /**
     * Build vendor analytics: 5%–10% fee tier, chronological earnings trend,
     * Most/Least Demanding & Most/Least Popular Services and Spare Parts.
     */
    private function buildVendorPerformanceAnalytics(VendorProfile $vendorProfile, array $scopedShopIds): array
    {
        $ratePct = $vendorProfile->effectiveCommissionRatePct();

        // 1. Chronological Workshop Earnings & Orders Trend (SVG-ready)
        $vendorLedgers = empty($scopedShopIds)
            ? collect()
            : FinancialLedger::whereIn('shop_id', $scopedShopIds)
                ->where('entry_type', 'VENDOR_EARNING')
                ->get(['amount', 'effective_at', 'created_at']);

        $paidInvoices = empty($scopedShopIds)
            ? collect()
            : Invoice::whereIn('shop_id', $scopedShopIds)
                ->where('status', 'PAID')
                ->get(['id', 'subtotal_amount', 'paid_at', 'issued_at', 'created_at']);

        $datedEarnings = $vendorLedgers->map(fn ($row) => [
            'date' => Carbon::parse($row->effective_at ?: $row->created_at ?: now()),
            'amount' => (float) $row->amount,
        ]);
        $datedInvoices = $paidInvoices->map(fn ($inv) => [
            'date' => Carbon::parse($inv->paid_at ?: $inv->issued_at ?: $inv->created_at ?: now()),
            'amount' => (float) $inv->subtotal_amount,
        ]);

        $allDates = $datedEarnings->pluck('date')->merge($datedInvoices->pluck('date'));
        $minDate = $allDates->isNotEmpty() ? $allDates->min()->copy()->startOfDay() : now()->copy()->subWeeks(5)->startOfWeek();
        $maxDate = $allDates->isNotEmpty() ? $allDates->max()->copy()->endOfDay() : now()->copy()->endOfDay();
        if ($maxDate->lt(now()->copy()->startOfDay())) {
            $maxDate = now()->copy()->endOfDay();
        }
        $spanDays = max(1, (int) $minDate->diffInDays($maxDate));
        $useWeekly = $spanDays > 18;

        $buckets = [];
        if ($useWeekly) {
            $bucketCount = min(8, max(5, (int) ceil($spanDays / 7)));
            $startWeek = now()->copy()->startOfWeek()->subWeeks($bucketCount - 1);
            for ($i = 0; $i < $bucketCount; $i++) {
                $wStart = $startWeek->copy()->addWeeks($i);
                $buckets[] = [
                    'label' => $wStart->format('d M'),
                    'start' => $wStart,
                    'end' => $wStart->copy()->endOfWeek(),
                    'earning' => 0.0,
                    'orders' => 0,
                ];
            }
        } else {
            $dayCount = min(7, max(5, $spanDays + 1));
            $startDay = now()->copy()->startOfDay()->subDays($dayCount - 1);
            for ($i = 0; $i < $dayCount; $i++) {
                $dStart = $startDay->copy()->addDays($i);
                $buckets[] = [
                    'label' => $dStart->format('d M'),
                    'start' => $dStart,
                    'end' => $dStart->copy()->endOfDay(),
                    'earning' => 0.0,
                    'orders' => 0,
                ];
            }
        }

        foreach ($datedEarnings as $e) {
            foreach ($buckets as $bi => $b) {
                if ($e['date']->betweenIncluded($b['start'], $b['end'])) {
                    $buckets[$bi]['earning'] = round($buckets[$bi]['earning'] + $e['amount'], 2);
                    break;
                }
            }
        }
        foreach ($datedInvoices as $inv) {
            foreach ($buckets as $bi => $b) {
                if ($inv['date']->betweenIncluded($b['start'], $b['end'])) {
                    $buckets[$bi]['orders']++;
                    break;
                }
            }
        }
        if ($datedEarnings->isEmpty() && $datedInvoices->isNotEmpty()) {
            foreach ($datedInvoices as $inv) {
                foreach ($buckets as $bi => $b) {
                    if ($inv['date']->betweenIncluded($b['start'], $b['end'])) {
                        $buckets[$bi]['earning'] = round($buckets[$bi]['earning'] + $inv['amount'], 2);
                        break;
                    }
                }
            }
        }

        $svgW = 640;
        $svgH = 170;
        $padX = 34;
        $padTop = 18;
        $padBottom = 24;
        $plotH = $svgH - $padTop - $padBottom;
        $plotW = $svgW - (2 * $padX);
        $maxEarning = max(100.0, ...array_map(fn ($b) => (float) $b['earning'], $buckets));
        $ptCount = max(1, count($buckets));

        $svgPts = [];
        $seriesPoints = [];
        foreach ($buckets as $idx => $b) {
            $x = $ptCount > 1 ? round($padX + ($idx * ($plotW / ($ptCount - 1))), 1) : round($svgW / 2, 1);
            $y = round($padTop + $plotH - (($b['earning'] / $maxEarning) * $plotH), 1);
            $svgPts[] = "{$x},{$y}";
            $seriesPoints[] = [
                'label' => $b['label'],
                'earning' => round($b['earning'], 2),
                'orders' => (int) $b['orders'],
                'x' => $x,
                'y' => $y,
            ];
        }
        $firstX = $seriesPoints[0]['x'] ?? $padX;
        $lastX = $seriesPoints[count($seriesPoints) - 1]['x'] ?? ($svgW - $padX);
        $baseY = $padTop + $plotH;
        $areaPoints = "{$firstX},{$baseY} " . implode(' ', $svgPts) . " {$lastX},{$baseY}";

        $mid = max(1, (int) floor(count($seriesPoints) / 2));
        $firstHalf = array_slice($seriesPoints, 0, $mid);
        $secondHalf = array_slice($seriesPoints, $mid);
        $prevAvg = count($firstHalf) > 0 ? array_sum(array_column($firstHalf, 'earning')) / count($firstHalf) : 0.0;
        $recentAvg = count($secondHalf) > 0 ? array_sum(array_column($secondHalf, 'earning')) / count($secondHalf) : 0.0;
        $deltaPct = $prevAvg > 0.01
            ? round((($recentAvg - $prevAvg) / $prevAvg) * 100, 1)
            : ($recentAvg > 0 ? 100.0 : 0.0);
        $direction = $deltaPct > 1.0 ? 'UP' : ($deltaPct < -1.0 ? 'DOWN' : 'STEADY');
        $trendLabel = $direction === 'UP' ? 'Going Up ▲' : ($direction === 'DOWN' ? 'Going Down ▼' : 'Steady ●');

        // 2. Reviews & Paid Line Items for Demand / Popularity Ranking
        $reviews = empty($scopedShopIds)
            ? collect()
            : ShopReview::with('repairRequest:id,title,issue_category,device_type')
                ->whereIn('repair_shop_id', $scopedShopIds)
                ->where('is_hidden', false)
                ->get(['id', 'repair_shop_id', 'repair_request_id', 'rating', 'service_provided', 'review_comment']);

        $paidLineItems = empty($scopedShopIds)
            ? collect()
            : InvoiceLineItem::whereHas('invoice', fn ($iq) => $iq->whereIn('shop_id', $scopedShopIds)->where('status', 'PAID'))
                ->get(['invoice_id', 'shop_service_id', 'title', 'description', 'quantity', 'total_price']);

        $completedRequests = empty($scopedShopIds)
            ? collect()
            : RepairRequest::whereIn('requested_shop_id', $scopedShopIds)
                ->where('status', 'COMPLETED')
                ->get(['id', 'title', 'issue_category', 'device_type', 'quoted_final_amount']);

        $matchesItem = function (string $needleText, string $targetTitle, ?string $targetCategory = null): bool {
            $hay = strtolower(trim($needleText));
            $tgt = strtolower(trim($targetTitle));
            $cat = strtolower(trim((string) $targetCategory));
            if ($hay === '' || $tgt === '') {
                return false;
            }
            if (str_contains($hay, $tgt) || str_contains($tgt, $hay)) {
                return true;
            }
            $families = [
                ['display', 'screen', 'oled', 'amoled', 'lcd', 'glass', 'digitizer', 'backlight'],
                ['battery', 'power', 'cell'],
                ['keyboard', 'keycap', 'backlit'],
                ['motherboard', 'logic', 'pmic', 'ic', 'mosfet', 'bga', 'board', 'short'],
                ['fan', 'cooling', 'thermal', 'overheating', 'repaste', 'heatsink'],
                ['charging', 'port', 'usb-c', 'type-c', 'lightning', 'tristar', 'charger', 'magsafe'],
                ['ssd', 'nvme', 'storage', 'ram', 'memory'],
                ['water', 'liquid', 'spill', 'ultrasonic', 'corrosion'],
                ['solder', 'soldering', 'flux'],
                ['hinge', 'ribbon', 'trackpad', 'touchpad', 'speaker'],
            ];
            foreach ($families as $fam) {
                $inHay = false;
                $inTgt = false;
                foreach ($fam as $kw) {
                    if (str_contains($hay, $kw)) {
                        $inHay = true;
                    }
                    if (str_contains($tgt, $kw) || ($cat !== '' && str_contains($cat, $kw))) {
                        $inTgt = true;
                    }
                }
                if ($inHay && $inTgt) {
                    return true;
                }
            }
            return false;
        };

        $shopServices = empty($scopedShopIds)
            ? collect()
            : ShopService::with('shop:id,name,branch_name')
                ->whereIn('shop_id', $scopedShopIds)
                ->where('is_active', true)
                ->orderBy('sort_order')
                ->get();

        $svcStatsById = [];
        foreach ($paidLineItems as $li) {
            if ($li->shop_service_id) {
                $sid = (int) $li->shop_service_id;
                if (!isset($svcStatsById[$sid])) {
                    $svcStatsById[$sid] = ['invoices' => [], 'revenue' => 0.0];
                }
                $svcStatsById[$sid]['invoices'][(int) $li->invoice_id] = max(
                    $svcStatsById[$sid]['invoices'][(int) $li->invoice_id] ?? 0,
                    max(1, (int) $li->quantity)
                );
                $svcStatsById[$sid]['revenue'] = round($svcStatsById[$sid]['revenue'] + (float) $li->total_price, 2);
            }
        }

        $serviceMetrics = $shopServices->map(function (ShopService $svc) use ($svcStatsById, $completedRequests, $reviews, $matchesItem) {
            $stat = $svcStatsById[$svc->id] ?? null;
            $salesCount = $stat ? array_sum($stat['invoices']) : 0;
            $revenue = $stat ? (float) $stat['revenue'] : 0.0;

            if ($salesCount === 0 && $completedRequests->isNotEmpty()) {
                foreach ($completedRequests as $cr) {
                    if ($matchesItem((string) ($cr->title . ' ' . $cr->issue_category), (string) $svc->name, $svc->issue_category)) {
                        $salesCount++;
                        $revenue += (float) ($cr->quoted_final_amount ?? $svc->base_price ?? 0);
                    }
                }
            }

            $matchedRatings = [];
            foreach ($reviews as $rev) {
                $revText = trim(($rev->service_provided ?? '') . ' ' . ($rev->repairRequest?->title ?? '') . ' ' . ($rev->repairRequest?->issue_category ?? ''));
                if ($matchesItem($revText, (string) $svc->name, $svc->issue_category)) {
                    $matchedRatings[] = (int) $rev->rating;
                }
            }

            $reviewCount = count($matchedRatings);
            $avgRating = $reviewCount > 0 ? round(array_sum($matchedRatings) / $reviewCount, 2) : null;

            return [
                'id' => (int) $svc->id,
                'name' => $svc->name,
                'category' => $svc->issue_category ?: $svc->device_type ?: 'Repair Service',
                'price' => (float) ($svc->base_price ?? 0),
                'sales_count' => $salesCount,
                'revenue' => round($revenue, 2),
                'avg_rating' => $avgRating,
                'review_count' => $reviewCount,
            ];
        })->values();

        $reviewedServices = $serviceMetrics->filter(fn ($s) => $s['review_count'] > 0)->values();
        $popularSourceSvcs = $reviewedServices->isNotEmpty() ? $reviewedServices : $serviceMetrics;

        $servicesAnalytics = [
            'total_count' => $serviceMetrics->count(),
            'most_demanding' => $serviceMetrics->sort(function ($a, $b) {
                return ($b['sales_count'] <=> $a['sales_count'])
                    ?: ($b['revenue'] <=> $a['revenue'])
                    ?: ($b['price'] <=> $a['price']);
            })->take(3)->values(),
            'least_demanding' => $serviceMetrics->sort(function ($a, $b) {
                return ($a['sales_count'] <=> $b['sales_count'])
                    ?: ($a['revenue'] <=> $b['revenue'])
                    ?: ($a['id'] <=> $b['id']);
            })->take(3)->values(),
            'most_popular' => $popularSourceSvcs->sort(function ($a, $b) {
                return (($b['avg_rating'] ?? 0) <=> ($a['avg_rating'] ?? 0))
                    ?: ($b['review_count'] <=> $a['review_count'])
                    ?: ($b['sales_count'] <=> $a['sales_count']);
            })->take(3)->values(),
            'least_popular' => $popularSourceSvcs->sort(function ($a, $b) {
                return (($a['avg_rating'] ?? 5.0) <=> ($b['avg_rating'] ?? 5.0))
                    ?: ($b['review_count'] <=> $a['review_count'])
                    ?: ($a['sales_count'] <=> $b['sales_count']);
            })->take(3)->values(),
        ];

        $spareParts = empty($scopedShopIds)
            ? collect()
            : SparePartListing::whereIn('shop_id', $scopedShopIds)
                ->where('is_active', true)
                ->get(['id', 'shop_id', 'slug', 'title', 'part_category', 'brand', 'base_price', 'normal_price', 'offer_enabled', 'offer_price', 'offer_discount_pct', 'offer_starts_at', 'offer_ends_at', 'sold_count', 'returned_count', 'stock_quantity']);

        $partMetrics = $spareParts->map(function (SparePartListing $part) use ($paidLineItems, $reviews, $matchesItem) {
            $unitPrice = $part->effectiveBasePrice();
            $invoiceQty = 0;
            foreach ($paidLineItems as $li) {
                if (str_contains(strtolower((string) $li->title), strtolower($part->title))) {
                    $invoiceQty += (int) $li->quantity;
                }
            }
            $salesCount = (int) $part->sold_count + $invoiceQty;

            $matchedRatings = [];
            foreach ($reviews as $rev) {
                $revText = trim(($rev->service_provided ?? '') . ' ' . ($rev->review_comment ?? '') . ' ' . ($rev->repairRequest?->title ?? ''));
                if ($matchesItem($revText, (string) $part->title, $part->part_category)) {
                    $matchedRatings[] = (int) $rev->rating;
                }
            }
            $reviewCount = count($matchedRatings);
            $avgRating = $reviewCount > 0 ? round(array_sum($matchedRatings) / $reviewCount, 2) : null;

            return [
                'id' => (int) $part->id,
                'slug' => $part->slug,
                'title' => $part->title,
                'category' => $part->part_category,
                'brand' => $part->brand,
                'price' => $unitPrice,
                'stock_quantity' => (int) $part->stock_quantity,
                'sales_count' => $salesCount,
                'returned_count' => (int) ($part->returned_count ?? 0),
                'revenue' => round($salesCount * $unitPrice, 2),
                'avg_rating' => $avgRating,
                'review_count' => $reviewCount,
            ];
        })->values();

        $reviewedParts = $partMetrics->filter(fn ($p) => $p['review_count'] > 0)->values();
        $popularSourceParts = $reviewedParts->isNotEmpty() ? $reviewedParts : $partMetrics;

        $partsAnalytics = [
            'total_count' => $partMetrics->count(),
            'most_demanding' => $partMetrics->sort(function ($a, $b) {
                return ($b['sales_count'] <=> $a['sales_count'])
                    ?: ($b['revenue'] <=> $a['revenue']);
            })->take(3)->values(),
            'least_demanding' => $partMetrics->sort(function ($a, $b) {
                return ($a['sales_count'] <=> $b['sales_count'])
                    ?: ($a['revenue'] <=> $b['revenue'])
                    ?: ($a['id'] <=> $b['id']);
            })->take(3)->values(),
            'most_popular' => $popularSourceParts->sort(function ($a, $b) {
                return (($b['avg_rating'] ?? 0) <=> ($a['avg_rating'] ?? 0))
                    ?: ($b['review_count'] <=> $a['review_count'])
                    ?: ($b['sales_count'] <=> $a['sales_count']);
            })->take(3)->values(),
            'least_popular' => $popularSourceParts->sort(function ($a, $b) {
                return (($a['avg_rating'] ?? 5.0) <=> ($b['avg_rating'] ?? 5.0))
                    ?: ($b['review_count'] <=> $a['review_count'])
                    ?: ($a['sales_count'] <=> $b['sales_count']);
            })->take(3)->values(),
        ];

        return [
            'commission_rate_pct' => $ratePct,
            'earnings_trend' => [
                'granularity' => $useWeekly ? 'Weekly' : 'Daily',
                'direction' => $direction,
                'trend_label' => $trendLabel,
                'rate_delta_pct' => $deltaPct,
                'total_earning' => round(array_sum(array_column($seriesPoints, 'earning')), 2),
                'total_orders' => array_sum(array_column($seriesPoints, 'orders')),
                'svg_width' => $svgW,
                'svg_height' => $svgH,
                'polyline_earning' => implode(' ', $svgPts),
                'area_earning' => $areaPoints,
                'points' => $seriesPoints,
            ],
            'services' => $servicesAnalytics,
            'spare_parts' => $partsAnalytics,
        ];
    }
}

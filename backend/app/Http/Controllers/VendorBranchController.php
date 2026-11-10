<?php

namespace App\Http\Controllers;

use App\Models\FinancialLedger;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BangladeshLocationResolver;
use Illuminate\Http\Request;
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

        $marketplaceRequests = RepairRequest::with('customer')
            ->where('status', 'BIDDING')
            ->latest()
            ->take(6)
            ->get();

        $activeJobs = RepairJob::with(['repairRequest', 'shop'])
            ->whereIn('shop_id', $scopedShopIds)
            ->whereNotIn('status', ['COMPLETED', 'CANCELLED'])
            ->latest()
            ->take(8)
            ->get();

        $ledgerGross = (float) FinancialLedger::whereIn('shop_id', $scopedShopIds)
            ->where('entry_type', 'CUSTOMER_PAYMENT')
            ->sum('amount');

        $avgRating = $activeBranch
            ? (float) $activeBranch->rating
            : round((float) $branches->avg('rating'), 1);

        $totalReviews = $activeBranch
            ? (int) $activeBranch->review_count
            : (int) $branches->sum('review_count');

        $branchMetrics = [
            'gross_inflow' => $ledgerGross > 0 ? $ledgerGross : ($activeBranch ? 18500 : 48500),
            'active_jobs_count' => $activeJobs->count() > 0 ? $activeJobs->count() : ($activeBranch ? 3 : 8),
            'avg_rating' => $avgRating > 0 ? $avgRating : 4.9,
            'total_reviews' => $totalReviews > 0 ? $totalReviews : 84,
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
            ->with('success', "Multi-branch mode enabled! '{$validated['primary_branch_name']}' tag is now live on {$brandName} shop tiles.");
    }

    /**
     * Create a new branch shop under the same vendor brand name anywhere in Bangladesh.
     * Route: POST /vendor/branches
     */
    public function storeBranch(Request $request)
    {
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
            'rating' => 5.0,
            'review_count' => 1,
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
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'supports_pickup' => in_array('COURIER_PICKUP', $categories, true),
            'phone' => !empty($validated['phone']) ? trim($validated['phone']) : ($templateShop->phone ?? $vendorProfile->phone ?? '+8801711000001'),
            'email' => Str::limit($slug, 40, '') . '@meramot.local',
            'opening_hours_text' => $templateShop->opening_hours_text ?? 'Sat – Thu: 10:00 AM – 8:30 PM',
            'categories' => $categories,
            'specialties' => $specialties,
        ]);

        // Seed bookable Direct-Order services for the new branch
        foreach (array_slice((array) $specialties, 0, 3) as $idx => $specName) {
            ShopService::updateOrCreate(
                ['shop_id' => $newBranch->id, 'slug' => Str::slug($specName) ?: ('service-' . ($idx + 1))],
                [
                    'name' => ucfirst((string) $specName),
                    'short_description' => "Certified {$brandName} ({$branchName}) bench service with warranty.",
                    'device_type' => 'Electronics',
                    'issue_category' => ucfirst((string) $specName),
                    'pricing_type' => 'STARTING_FROM',
                    'base_price' => $baseLaborFee + ($idx * 250),
                    'estimated_days_min' => 1,
                    'estimated_days_max' => 3,
                    'includes_pickup' => in_array('COURIER_PICKUP', $categories, true),
                    'sort_order' => $idx,
                    'is_featured' => $idx === 0,
                    'is_active' => true,
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
            ->with('success', "Expanded business! New branch '{$brandName} — {$branchName}' ({$area}, {$city}) is now live.");
    }

    /**
     * Update Dual Addresses (GPS Approximate vs Vendor-Provided) & Branch Name for an existing shop.
     * Route: PATCH /vendor/branches/{shopId}
     */
    public function updateBranchAddresses(Request $request, $shopId)
    {
        $shop = RepairShop::findOrFail($shopId);

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
            ->with('success', "Updated GPS approximate pin & public storefront address for {$shop->name}" . ($shop->display_branch_tag ? " [{$shop->display_branch_tag}]" : '') . '.');
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
            'rating' => 4.9,
        ]);
    }
}

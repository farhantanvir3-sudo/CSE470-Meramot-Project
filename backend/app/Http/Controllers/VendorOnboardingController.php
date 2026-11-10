<?php

namespace App\Http\Controllers;

use App\Models\RepairShop;
use App\Models\VendorProfile;
use App\Services\BangladeshLocationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ============================================================================
 * FEATURE R11: Vendor Onboarding & Business Profile Verification Engine
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179] (Team Lead) | Sprint 1
 * MVC Layer: Controller
 * Supports:
 *   - Dual Shop Addresses during onboarding:
 *       1. GPS Approximate Location (`shop_latitude`, `shop_longitude`, `gps_address`)
 *       2. Vendor-Provided Storefront Location (`shop_address`, `city`, `area`)
 *       3. Optional sync (`same_as_gps_location = true` makes 1 & 2 identical)
 *   - Multi-Branch opt-in (`is_branched`, `branch_name`) from day one or later.
 * ============================================================================
 */
class VendorOnboardingController extends Controller
{
    /**
     * Display vendor onboarding status or application view.
     */
    public function show(Request $request)
    {
        $userId = $request->user()->id ?? 1;
        $profile = VendorProfile::with('shops')->where('user_id', $userId)->first();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'data' => $profile,
            ]);
        }

        return view('vendor.onboarding', compact('profile'));
    }

    /**
     * Store new vendor profile application with coherent Dual Addresses & optional Branching.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'shop_name' => 'required|string|max:255',
            'business_license_no' => 'required|string|max:100',
            'nid_number' => 'required|string|max:30',
            'gps_address' => 'nullable|string|max:500',
            'same_as_gps_location' => 'nullable|boolean',
            'shop_address' => 'nullable|string|max:500',
            'shop_latitude' => 'nullable|numeric|between:20.5,26.7',
            'shop_longitude' => 'nullable|numeric|between:88.0,92.7',
            'city' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:120',
            'is_branched' => 'nullable|boolean',
            'branch_name' => 'nullable|string|max:120',
            'categories' => 'required|array',
        ]);

        $sameAsGps = $request->boolean('same_as_gps_location');
        $gpsAddress = trim((string) ($validated['gps_address'] ?? ''));
        $vendorAddress = trim((string) ($validated['shop_address'] ?? ''));

        if ($sameAsGps) {
            $effectiveAddress = $gpsAddress !== '' ? $gpsAddress : $vendorAddress;
            if ($effectiveAddress === '') {
                throw ValidationException::withMessages([
                    'gps_address' => 'Please pin a GPS location on the map or provide a shop address.',
                ]);
            }
            $gpsAddress = $effectiveAddress;
            $vendorAddress = $effectiveAddress;
        } else {
            if ($vendorAddress === '') {
                throw ValidationException::withMessages([
                    'shop_address' => 'Please enter your public vendor-provided shop address (or tick "Same as GPS location").',
                ]);
            }
            if ($gpsAddress === '') {
                $gpsAddress = $vendorAddress;
            }
        }

        $lat = isset($validated['shop_latitude']) ? (float) $validated['shop_latitude'] : 23.7937;
        $lng = isset($validated['shop_longitude']) ? (float) $validated['shop_longitude'] : 90.4066;
        $resolvedGeo = BangladeshLocationResolver::resolve($lat, $lng);

        $city = !empty($validated['city'])
            ? BangladeshLocationResolver::normalizeCity($validated['city'])
            : $resolvedGeo['city'];
        $area = !empty($validated['area'])
            ? trim($validated['area'])
            : $resolvedGeo['area'];

        $isBranched = $request->boolean('is_branched');
        $branchName = $isBranched
            ? (trim((string) ($validated['branch_name'] ?? '')) ?: "{$area} Main Branch")
            : null;

        $userId = $request->user()->id ?? 1;

        $profile = VendorProfile::updateOrCreate(
            ['user_id' => $userId],
            [
                'shop_name' => $validated['shop_name'],
                'brand_name' => $validated['shop_name'],
                'is_branched' => $isBranched,
                'business_license_no' => $validated['business_license_no'],
                'nid_number' => $validated['nid_number'],
                'shop_address' => $vendorAddress,
                'gps_address' => $gpsAddress,
                'same_as_gps_location' => ($vendorAddress === $gpsAddress) && $sameAsGps,
                'city' => $city,
                'area' => $area,
                'shop_latitude' => $lat,
                'shop_longitude' => $lng,
                'categories' => $validated['categories'],
                'verification_status' => 'pending',
                'commission_rate' => 10.00,
            ]
        );

        // Synchronize primary RepairShop record coherently with dual-address & branch state
        $primaryShop = RepairShop::where('vendor_profile_id', $profile->id)->orderBy('id')->first();
        $baseSlug = Str::slug($validated['shop_name'] . ($branchName ? '-' . $branchName : ''));
        if ($baseSlug === '') {
            $baseSlug = 'vendor-shop-' . $profile->id;
        }

        if ($primaryShop) {
            $primaryShop->update([
                'name' => $validated['shop_name'],
                'is_branch' => $isBranched,
                'branch_name' => $branchName,
                'address' => $vendorAddress,
                'gps_address' => $gpsAddress,
                'same_as_gps_location' => ($vendorAddress === $gpsAddress) && $sameAsGps,
                'city' => $city,
                'area' => $area,
                'latitude' => $lat,
                'longitude' => $lng,
                'specialties' => $validated['categories'],
            ]);
        } else {
            $slug = $baseSlug;
            if (RepairShop::where('slug', $slug)->exists()) {
                $slug .= '-' . $profile->id;
            }
            RepairShop::create([
                'vendor_profile_id' => $profile->id,
                'name' => $validated['shop_name'],
                'slug' => $slug,
                'is_branch' => $isBranched,
                'branch_name' => $branchName,
                'description' => "Verified electronic repair workshop in {$area}, {$city}.",
                'address' => $vendorAddress,
                'gps_address' => $gpsAddress,
                'same_as_gps_location' => ($vendorAddress === $gpsAddress) && $sameAsGps,
                'city' => $city,
                'area' => $area,
                'latitude' => $lat,
                'longitude' => $lng,
                'categories' => ['IN_SHOP_REPAIR', 'COURIER_PICKUP'],
                'specialties' => $validated['categories'],
                'inspection_fee' => 250,
                'base_labor_fee' => 500,
                'is_active' => true,
                'is_public' => true,
            ]);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Vendor application submitted successfully for administrative audit.',
                'data' => $profile->fresh('shops'),
            ], 201);
        }

        return redirect()->back()->with('success', 'Vendor application and dual shop locations saved for administrative audit.');
    }

    /**
     * Admin action to verify/approve vendor profile.
     */
    public function verify(Request $request, $id)
    {
        $profile = VendorProfile::findOrFail($id);
        $status = $request->input('action') === 'approve' ? 'approved' : 'rejected';
        $profile->update([
            'verification_status' => $status,
            'rejection_reason' => $request->input('reason'),
        ]);

        if ($status === 'approved') {
            RepairShop::where('vendor_profile_id', $profile->id)->update([
                'is_active' => true,
                'is_public' => true,
            ]);
        }

        return response()->json([
            'status' => 'success',
            'message' => "Vendor profile has been {$status}.",
            'data' => $profile,
        ]);
    }
}

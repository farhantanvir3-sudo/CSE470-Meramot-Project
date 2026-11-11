<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RepairShop;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BangladeshLocationResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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
 *   - Admin Vendor Verification Desk (`adminIndex`, `approve`, `reject`, `toggleFeatured`)
 *     with `rejection_reason` workflow and public storefront gating.
 * ============================================================================
 */
class VendorOnboardingController extends Controller
{
    /**
     * Display vendor onboarding status or application view.
     * Guest-safe: never exposes User #1's profile when unauthenticated.
     */
    public function show(Request $request)
    {
        $profile = $request->user()
            ? VendorProfile::with('shops')->where('user_id', $request->user()->id)->first()
            : null;

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'data' => $profile,
            ]);
        }

        return view('vendor.onboarding', compact('profile'));
    }

    /**
     * Store new or updated vendor profile application with coherent Dual Addresses & optional Branching.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'owner_name' => 'nullable|string|max:150',
            'owner_email' => 'nullable|email|max:180',
            'owner_phone' => 'nullable|string|max:30',
            'owner_password' => 'nullable|string|min:6|max:100',
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
            'categories' => 'required|array|min:1',
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

        // Resolve or register vendor applicant User safely (never clobber System Admin User #1)
        $user = $request->user();
        if (!$user) {
            $applicantEmail = !empty($validated['owner_email'])
                ? strtolower(trim($validated['owner_email']))
                : ('vendor.' . Str::slug($validated['shop_name']) . '.' . strtolower(Str::random(4)) . '@meramot.applicant');

            $user = User::firstOrCreate(
                ['email' => $applicantEmail],
                [
                    'name' => trim($validated['owner_name'] ?? ($validated['shop_name'] . ' Owner')),
                    'username' => 'vendor_' . strtolower(Str::random(8)),
                    'phone' => trim($validated['owner_phone'] ?? '+8801700000000'),
                    'password' => Hash::make($validated['owner_password'] ?? 'password123'),
                    'role' => 'vendor',
                    'is_active' => true,
                ]
            );

            if (!$request->expectsJson() && !$request->is('api/*')) {
                Auth::login($user);
            }
        } elseif ($user->role === 'customer') {
            $user->update(['role' => 'vendor']);
        }

        $profile = VendorProfile::updateOrCreate(
            ['user_id' => $user->id],
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
                'rejection_reason' => null,
                'rejected_at' => null,
                'reviewed_by_admin_id' => null,
                'reviewed_at' => null,
                'commission_rate' => 10.00,
                'rating' => 0.00,
            ]
        );

        // Synchronize primary RepairShop record coherently; keep hidden & non-bookable until Admin approves
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
                'is_active' => true,
                'is_public' => false,
                'setup_complete' => false,
                'accepts_direct_orders' => false,
            ]);
        } else {
            $slug = $baseSlug;
            if (RepairShop::where('slug', $slug)->exists()) {
                $slug .= '-' . $profile->id;
            }
            $primaryShop = RepairShop::create([
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
                'rating' => 0.00,
                'review_count' => 0,
                'categories' => ['IN_SHOP_REPAIR', 'COURIER_PICKUP'],
                'specialties' => $validated['categories'],
                'inspection_fee' => 250,
                'base_labor_fee' => 500,
                'phone' => $user->phone ?? '+8801711000000',
                'is_active' => true,
                'is_public' => false,
                'setup_complete' => false,
                'accepts_direct_orders' => false,
            ]);
        }

        DB::table('shop_staff')->updateOrInsert(
            ['shop_id' => $primaryShop->id, 'user_id' => $user->id],
            [
                'role' => 'OWNER',
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Vendor application submitted successfully for administrative audit.',
                'data' => $profile->fresh('shops'),
            ], 201);
        }

        return redirect()
            ->route('vendor.onboarding')
            ->with('success', 'Vendor application submitted! Status is now PENDING administrative trade verification.');
    }

    /**
     * Admin Web Desk: Review Pending, Rejected & Approved Vendor Profiles (Feature R11).
     * Route: GET /admin/vendors/pending
     */
    public function adminIndex(Request $request)
    {
        $pendingVendors = VendorProfile::with(['user', 'shops'])
            ->where('verification_status', 'pending')
            ->orderByDesc('updated_at')
            ->get();

        $rejectedVendors = VendorProfile::with(['user', 'shops'])
            ->where('verification_status', 'rejected')
            ->orderByDesc('updated_at')
            ->take(10)
            ->get();

        $search = trim((string) $request->query('q', ''));
        $approvedQuery = VendorProfile::with(['user', 'shops'])
            ->where('verification_status', 'approved');

        if ($search !== '') {
            $needle = '%' . strtolower($search) . '%';
            $approvedQuery->where(function ($q) use ($needle) {
                $q->whereRaw('LOWER(shop_name) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(city, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(area, \'\')) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(business_license_no, \'\')) LIKE ?', [$needle]);
            });
        }

        $approvedVendors = $approvedQuery
            ->orderByDesc('is_branched')
            ->orderBy('id')
            ->paginate(12)
            ->withQueryString();

        $stats = [
            'pendingCount' => $pendingVendors->count(),
            'approvedCount' => VendorProfile::where('verification_status', 'approved')->count(),
            'rejectedCount' => VendorProfile::where('verification_status', 'rejected')->count(),
            'branchedBrandsCount' => VendorProfile::where('is_branched', true)->count(),
            'publicStorefrontsCount' => RepairShop::where('is_active', true)->where('is_public', true)->count(),
        ];

        return view('admin.verify-vendor', compact(
            'pendingVendors',
            'rejectedVendors',
            'approvedVendors',
            'stats',
            'search'
        ));
    }

    /**
     * Admin Web Action: Approve vendor onboarding application & publish shop(s).
     * Route: POST /admin/vendors/{id}/approve
     */
    public function approve($id)
    {
        $profile = VendorProfile::with('user')->findOrFail($id);
        $previousStatus = $profile->verification_status;

        $profile->update([
            'verification_status' => 'approved',
            'rejection_reason' => null,
            'rejected_at' => null,
            'reviewed_by_admin_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        if ($profile->user) {
            $profile->user->update([
                'role' => 'vendor',
                'is_active' => true,
            ]);
        }

        RepairShop::where('vendor_profile_id', $profile->id)->update([
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
        ]);

        AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => 'vendor_approved',
            'target_resource' => "vendor_profiles:{$profile->id}",
            'payload_before' => ['verification_status' => $previousStatus],
            'payload_after' => ['verification_status' => 'approved', 'shop_name' => $profile->shop_name],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with(
            'success',
            "Approved '{$profile->shop_name}'! Its repair storefront is now verified and publicly searchable across Meramot."
        );
    }

    /**
     * Admin Web Action: Reject vendor onboarding application with mandatory reason.
     * Route: POST /admin/vendors/{id}/reject
     */
    public function reject(Request $request, $id)
    {
        $validated = $request->validate([
            'rejection_reason' => 'required|string|max:500',
        ]);

        $profile = VendorProfile::findOrFail($id);
        $previousStatus = $profile->verification_status;

        $profile->update([
            'verification_status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
            'reviewed_by_admin_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        RepairShop::where('vendor_profile_id', $profile->id)->update([
            'is_public' => false,
            'setup_complete' => false,
            'accepts_direct_orders' => false,
        ]);

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'vendor_rejected',
            'target_resource' => "vendor_profiles:{$profile->id}",
            'payload_before' => ['verification_status' => $previousStatus],
            'payload_after' => ['verification_status' => 'rejected', 'rejection_reason' => $validated['rejection_reason']],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with(
            'warning',
            "Rejected vendor application '{$profile->shop_name}' and sent remediation notes to applicant."
        );
    }

    /**
     * Admin Web Action: Toggle homepage Featured status for a verified RepairShop.
     * Route: POST /admin/vendors/shops/{shopId}/toggle-featured
     */
    public function toggleFeatured($shopId)
    {
        $shop = RepairShop::findOrFail($shopId);
        $shop->update([
            'is_featured' => !$shop->is_featured,
        ]);

        $stateLabel = $shop->is_featured ? 'added to' : 'removed from';

        return redirect()->back()->with(
            'success',
            "'{$shop->name}'" . ($shop->display_branch_tag ? " [{$shop->display_branch_tag}]" : '') . " {$stateLabel} Homepage Featured Shops."
        );
    }

    /**
     * API action to verify/approve or reject vendor profile.
     * Route: POST /api/vendors/{id}/verify
     */
    public function verify(Request $request, $id)
    {
        $profile = VendorProfile::findOrFail($id);
        $status = $request->input('action') === 'approve' ? 'approved' : 'rejected';
        $isApproved = $status === 'approved';

        $profile->update([
            'verification_status' => $status,
            'rejection_reason' => $isApproved ? null : $request->input('reason'),
            'rejected_at' => $isApproved ? null : now(),
            'reviewed_by_admin_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        RepairShop::where('vendor_profile_id', $profile->id)->update([
            'is_active' => $isApproved,
            'is_public' => $isApproved,
            'setup_complete' => $isApproved,
            'accepts_direct_orders' => $isApproved,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Vendor profile has been {$status}.",
            'data' => $profile,
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RepairShop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\VendorProfile;
use App\Services\BangladeshLocationResolver;
use App\Services\GmailApiService;
use App\Services\MediaUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ============================================================================
 * FEATURE R11: Vendor Sign-Up, Onboarding, OTP Verification & Shop Setup Engine
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179] (Team Lead) | Sprint 1
 * MVC Layer: Controller
 * Supports:
 *   - Dedicated Vendor Sign-Up Application (`/vendor/apply` -> `/vendor/apply/success`)
 *     with live email availability check (`/api/vendors/check-email`), logo upload,
 *     mandatory map pin, Dual Shop Addresses, and Multi-Branch opt-in.
 *   - Vendor Onboarding Hub (`/vendor/onboarding`) & Status Tracker (`/vendor/status`)
 *     with Email + Phone OTP verification gating.
 *   - Post-Approval Shop Setup (`/vendor/setup-shop`) for vendor-defined pricing,
 *     service options, and available repair services before going public.
 *   - Admin Vendor Verification Desk (`adminIndex`, `approve`, `reject`, `toggleFeatured`).
 * ============================================================================
 */
class VendorOnboardingController extends Controller
{
    private const NON_SERVICE_TAG_WORDS = [
        'laptop', 'phone', 'tablet', 'console', 'printer', 'smartwatch', 'student discount', 'pickup service',
    ];

    /**
     * Live Email Availability Check (`GET /api/vendors/check-email?email=...`)
     */
    public function checkEmail(Request $request)
    {
        $email = strtolower(trim((string) $request->query('email', '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['available' => false, 'message' => 'Please enter a valid email address.'], 400);
        }

        $currentUserId = $request->user()?->id;

        $userExists = User::where('email', $email)
            ->when($currentUserId, fn ($q) => $q->where('id', '!=', $currentUserId))
            ->exists();

        if ($userExists) {
            return response()->json([
                'available' => false,
                'message' => 'An account with this email already exists.',
            ]);
        }

        $appExists = VendorProfile::where('business_email', $email)
            ->when($currentUserId, fn ($q) => $q->where('user_id', '!=', $currentUserId))
            ->exists();

        if ($appExists) {
            return response()->json([
                'available' => false,
                'message' => 'A vendor application with this business email already exists.',
            ]);
        }

        return response()->json(['available' => true]);
    }

    /**
     * Dedicated Vendor Application Form (`GET /vendor/apply`)
     */
    public function showApply(Request $request)
    {
        $user = $request->user();
        $profile = null;
        $isEditMode = false;

        if ($user && $user->role === 'vendor') {
            $profile = VendorProfile::with('shops')->where('user_id', $user->id)->first();
            if ($profile) {
                if ($profile->verification_status === 'approved') {
                    $primaryShop = $profile->shops->sortBy('id')->first();
                    return redirect($primaryShop && $primaryShop->setup_complete ? '/vendor/dashboard' : '/vendor/setup-shop');
                }
                $isEditMode = in_array($profile->verification_status, ['pending', 'under_review', 'rejected'], true);
            }
        }

        return view('vendor.apply', compact('profile', 'isEditMode'));
    }

    /**
     * Submit or Update Dedicated Vendor Application (`POST /vendor/apply`)
     */
    public function storeApply(Request $request)
    {
        $authUser = $request->user();
        $existingProfile = ($authUser && $authUser->role === 'vendor')
            ? VendorProfile::with('shops')->where('user_id', $authUser->id)->first()
            : null;
        $isEditMode = $existingProfile && in_array($existingProfile->verification_status, ['pending', 'under_review', 'rejected'], true);

        $rules = [
            'owner_name' => 'required|string|max:150',
            'business_email' => 'required|email|max:180',
            'phone' => 'required|string|max:30',
            'shop_name' => 'required|string|max:255',
            'business_license_no' => 'nullable|string|max:100',
            'nid_number' => 'nullable|string|max:30',
            'shop_latitude' => 'required|numeric|between:20.5,26.7',
            'shop_longitude' => 'required|numeric|between:88.0,92.7',
            'gps_address' => 'nullable|string|max:500',
            'same_as_gps_location' => 'nullable|boolean',
            'shop_address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:120',
            'is_branched' => 'nullable|boolean',
            'branch_name' => 'nullable|string|max:120',
            'specialties' => 'nullable',
            'courier_pickup' => 'nullable|boolean',
            'in_shop_repair' => 'nullable|boolean',
            'spare_parts' => 'nullable|boolean',
            'notes' => 'nullable|string|max:2000',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:15360',
            'logo_url' => 'nullable|string',
            'verification_document' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:15360',
        ];

        if (!$isEditMode) {
            $rules['password'] = 'required|string|min:8|confirmed';
        }

        $validated = $request->validate($rules, [
            'shop_latitude.required' => 'Please pick your shop location on the OpenStreetMap picker.',
            'shop_longitude.required' => 'Please pick your shop location on the OpenStreetMap picker.',
        ]);

        $cleanPhone = str_replace([' ', '-'], '', trim($validated['phone']));
        if (!preg_match('/^(?:\+?8801|01)[3-9]\d{8}$/', $cleanPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678).',
            ]);
        }

        $email = strtolower(trim($validated['business_email']));

        $emailTakenByUser = User::where('email', $email)
            ->when($isEditMode && $authUser, fn ($q) => $q->where('id', '!=', $authUser->id))
            ->exists();
        $emailTakenByVendor = VendorProfile::where('business_email', $email)
            ->when($isEditMode && $existingProfile, fn ($q) => $q->where('id', '!=', $existingProfile->id))
            ->exists();

        if ($emailTakenByUser || $emailTakenByVendor) {
            throw ValidationException::withMessages([
                'business_email' => 'An account or vendor application with this email already exists. Please sign in instead.',
            ]);
        }

        $lat = (float) $validated['shop_latitude'];
        $lng = (float) $validated['shop_longitude'];
        $resolvedGeo = BangladeshLocationResolver::resolve($lat, $lng);

        $city = !empty($validated['city'])
            ? BangladeshLocationResolver::normalizeCity($validated['city'])
            : $resolvedGeo['city'];
        $area = !empty($validated['area'])
            ? trim($validated['area'])
            : $resolvedGeo['area'];

        $gpsAddress = trim((string) ($validated['gps_address'] ?? ''));
        if ($gpsAddress === '') {
            $gpsAddress = "{$area}, {$city}";
        }

        $sameAsGps = $request->boolean('same_as_gps_location');
        $vendorAddress = trim((string) ($validated['shop_address'] ?? ''));
        if ($sameAsGps) {
            $vendorAddress = $gpsAddress;
        }

        $isBranched = $request->boolean('is_branched');
        $branchName = $isBranched
            ? (trim((string) ($validated['branch_name'] ?? '')) ?: "{$area} Main Branch")
            : null;

        $rawSpecialties = $validated['specialties'] ?? [];
        if (is_string($rawSpecialties)) {
            $specialties = array_values(array_filter(array_map('trim', explode(',', $rawSpecialties))));
        } elseif (is_array($rawSpecialties)) {
            $specialties = array_values(array_filter(array_map('trim', $rawSpecialties)));
        } else {
            $specialties = [];
        }
        if (empty($specialties)) {
            $specialties = ['Smartphone Repair', 'Laptop Repair'];
        }

        $courierPickup = $request->boolean('courier_pickup');
        $inShopRepair = $request->has('in_shop_repair') ? $request->boolean('in_shop_repair') : true;
        $spareParts = $request->boolean('spare_parts');
        if (!$courierPickup && !$inShopRepair && !$spareParts) {
            $inShopRepair = true;
        }

        $serviceCategories = array_values(array_filter([
            $courierPickup ? 'COURIER_PICKUP' : null,
            $inShopRepair ? 'IN_SHOP_REPAIR' : null,
            $spareParts ? 'SPARE_PARTS' : null,
        ]));

        $logoUrl = $existingProfile?->logo_url;
        if ($request->hasFile('logo')) {
            $logoUrl = MediaUploadService::fromRequest($request, 'logo', $logoUrl, 1000, 84);
        } elseif (array_key_exists('logo_url', $validated) && $validated['logo_url'] !== null) {
            $logoUrl = trim((string) $validated['logo_url']) ?: null;
        }

        $documentsPath = MediaUploadService::fromRequest($request, 'verification_document', $existingProfile?->documents_path);

        $profile = DB::transaction(function () use (
            $isEditMode, $authUser, $existingProfile, $validated, $email, $cleanPhone,
            $lat, $lng, $city, $area, $gpsAddress, $vendorAddress, $sameAsGps,
            $isBranched, $branchName, $specialties, $courierPickup, $inShopRepair, $spareParts,
            $serviceCategories, $logoUrl, $documentsPath
        ) {
            if ($isEditMode && $authUser && $existingProfile) {
                $authUser->update([
                    'name' => trim($validated['owner_name']),
                    'email' => $email,
                    'phone' => $cleanPhone,
                ]);
                $vendorUser = $authUser;
            } else {
                // If someone was signed in as a customer when applying with a brand-new vendor email, log out first
                if (Auth::check()) {
                    Auth::logout();
                }

                $baseUser = Str::slug(explode('@', $email)[0] ?: $validated['shop_name'], '_') ?: 'vendor';
                $username = $baseUser;
                $counter = 1;
                while (User::where('username', $username)->exists()) {
                    $username = $baseUser . '_' . $counter++;
                }

                $vendorUser = User::create([
                    'username' => $username,
                    'name' => trim($validated['owner_name']),
                    'email' => $email,
                    'auth_provider' => 'local',
                    'password' => Hash::make($validated['password']),
                    'phone' => $cleanPhone,
                    'role' => 'vendor',
                    'is_email_verified' => false,
                    'is_phone_verified' => false,
                    'status' => 'active',
                    'is_active' => true,
                ]);
            }

            $profileData = [
                'owner_name' => trim($validated['owner_name']),
                'business_email' => $email,
                'phone' => $cleanPhone,
                'shop_name' => trim($validated['shop_name']),
                'brand_name' => trim($validated['shop_name']),
                'is_branched' => $isBranched,
                'business_license_no' => !empty($validated['business_license_no']) ? trim($validated['business_license_no']) : null,
                'nid_number' => !empty($validated['nid_number']) ? trim($validated['nid_number']) : null,
                'shop_address' => $vendorAddress !== '' ? $vendorAddress : null,
                'gps_address' => $gpsAddress,
                'same_as_gps_location' => ($vendorAddress !== '' && $vendorAddress === $gpsAddress) && $sameAsGps,
                'city' => $city,
                'area' => $area,
                'shop_latitude' => $lat,
                'shop_longitude' => $lng,
                'categories' => $specialties,
                'specialties' => $specialties,
                'courier_pickup' => $courierPickup,
                'in_shop_repair' => $inShopRepair,
                'spare_parts' => $spareParts,
                'notes' => !empty($validated['notes']) ? trim($validated['notes']) : null,
                'logo_url' => $logoUrl,
                'documents_path' => $documentsPath,
                'verification_status' => 'pending',
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejection_visible_until' => null,
                'reviewed_by_admin_id' => null,
                'reviewed_at' => null,
                'commission_rate' => 10.00,
                'rating' => 0.00,
            ];

            $savedProfile = VendorProfile::updateOrCreate(
                ['user_id' => $vendorUser->id],
                $profileData
            );

            $primaryShop = RepairShop::where('vendor_profile_id', $savedProfile->id)->orderBy('id')->first();
            $baseSlug = Str::slug($validated['shop_name'] . ($branchName ? '-' . $branchName : '')) ?: ('vendor-shop-' . $savedProfile->id);

            $shopPayload = [
                'name' => trim($validated['shop_name']),
                'is_branch' => $isBranched,
                'branch_name' => $branchName,
                'description' => !empty($validated['notes']) ? trim($validated['notes']) : null,
                'logo_url' => $logoUrl,
                'address' => $vendorAddress !== '' ? $vendorAddress : null,
                'gps_address' => $gpsAddress,
                'same_as_gps_location' => ($vendorAddress !== '' && $vendorAddress === $gpsAddress) && $sameAsGps,
                'city' => $city,
                'area' => $area,
                'latitude' => $lat,
                'longitude' => $lng,
                'phone' => $cleanPhone,
                'email' => $email,
                'categories' => $serviceCategories,
                'specialties' => $specialties,
                'supports_pickup' => $courierPickup,
                'inspection_fee' => null,
                'base_labor_fee' => null,
                'is_active' => false,
                'is_public' => false,
                'setup_complete' => false,
                'accepts_direct_orders' => false,
            ];

            if ($primaryShop) {
                $primaryShop->update($shopPayload);
            } else {
                $slug = $baseSlug;
                if (RepairShop::where('slug', $slug)->exists()) {
                    $slug .= '-' . strtolower(Str::random(5));
                }
                $primaryShop = RepairShop::create(array_merge($shopPayload, [
                    'vendor_profile_id' => $savedProfile->id,
                    'slug' => $slug,
                    'rating' => 0.00,
                    'review_count' => 0,
                ]));
            }

            DB::table('shop_staff')->updateOrInsert(
                ['shop_id' => $primaryShop->id, 'user_id' => $vendorUser->id],
                [
                    'role' => 'OWNER',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );

            return $savedProfile;
        });

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => $isEditMode
                    ? 'Vendor application updated and resubmitted for review'
                    : 'Vendor application submitted successfully',
                'application' => $profile->fresh('shops'),
            ], $isEditMode ? 200 : 201);
        }

        if ($isEditMode) {
            return redirect()
                ->route('vendor.status')
                ->with('success', 'Your vendor application has been updated and resubmitted for admin review.');
        }

        return redirect()->route('vendor.apply.success', ['id' => $profile->id]);
    }

    /**
     * Application Submitted Confirmation Screen (`GET /vendor/apply/success`)
     */
    public function showApplySuccess(Request $request)
    {
        $applicationId = $request->query('id');
        return view('vendor.apply-success', compact('applicationId'));
    }

    /**
     * Vendor Application Status Page (`GET /vendor/status`)
     */
    public function showStatus(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }

        $profile = VendorProfile::with('shops')->where('user_id', $user->id)->first();
        if (!$profile) {
            return redirect()->route('vendor.apply');
        }

        $primaryShop = $profile->shops->sortBy('id')->first();

        return view('vendor.status', compact('profile', 'primaryShop', 'user'));
    }

    /**
     * Post-Approval Shop Setup Screen (`GET /vendor/setup-shop`)
     */
    public function showSetupShop(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect()->route('login');
        }
        if ($user->role !== 'vendor') {
            return redirect('/');
        }

        $profile = VendorProfile::with('shops')->where('user_id', $user->id)->first();
        if (!$profile) {
            return redirect()->route('vendor.apply');
        }
        if ($profile->verification_status !== 'approved') {
            return redirect()->route('vendor.onboarding')
                ->withErrors(['setup' => 'Your vendor application must be approved by an admin before completing shop setup.']);
        }

        $primaryShop = $profile->shops->sortBy('id')->first();

        return view('vendor.setup-shop', compact('profile', 'primaryShop', 'user'));
    }

    /**
     * Save Post-Approval Shop Setup (`POST /vendor/setup-shop`)
     */
    public function completeSetupShop(Request $request)
    {
        $user = $request->user();
        if (!$user || $user->role !== 'vendor') {
            return redirect()->route('login');
        }

        $profile = VendorProfile::with('shops')->where('user_id', $user->id)->firstOrFail();
        if ($profile->verification_status !== 'approved') {
            return redirect()->route('vendor.onboarding')
                ->withErrors(['setup' => 'Only approved vendors can complete shop setup.']);
        }

        $validated = $request->validate([
            'shop_name' => 'required|string|max:255',
            'phone' => 'required|string|max:30',
            'description' => 'nullable|string|max:2000',
            'address' => 'required|string|max:500',
            'gps_address' => 'nullable|string|max:500',
            'same_as_gps_location' => 'nullable|boolean',
            'city' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:120',
            'latitude' => 'nullable|numeric|between:20.5,26.7',
            'longitude' => 'nullable|numeric|between:88.0,92.7',
            'courier_pickup' => 'nullable|boolean',
            'in_shop_repair' => 'nullable|boolean',
            'spare_parts' => 'nullable|boolean',
            'inspection_fee' => 'required|numeric|min:0',
            'base_labor_fee' => 'required|numeric|min:0',
            'pickup_fee' => 'nullable|numeric|min:0',
            'express_fee' => 'nullable|numeric|min:0',
            'skill_tags' => 'required|array|min:1',
            'skill_tags.*' => 'string|max:180',
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:15360',
        ], [
            'skill_tags.required' => 'Please add at least one available service / skill tag.',
            'skill_tags.min' => 'Please add at least one available service / skill tag.',
        ]);

        $cleanPhone = str_replace([' ', '-'], '', trim($validated['phone']));
        if (!preg_match('/^(?:\+?8801|01)[3-9]\d{8}$/', $cleanPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678).',
            ]);
        }

        $courierPickup = $request->boolean('courier_pickup');
        $inShopRepair = $request->boolean('in_shop_repair');
        $spareParts = $request->boolean('spare_parts');

        if (!$courierPickup && !$inShopRepair && !$spareParts) {
            throw ValidationException::withMessages([
                'services' => 'Please select at least one service option (Courier pickup, In-shop repair, or Spare parts).',
            ]);
        }

        $serviceCategories = array_values(array_filter([
            $courierPickup ? 'COURIER_PICKUP' : null,
            $inShopRepair ? 'IN_SHOP_REPAIR' : null,
            $spareParts ? 'SPARE_PARTS' : null,
        ]));

        $primaryShop = $profile->shops->sortBy('id')->first();

        $lat = isset($validated['latitude']) && $validated['latitude'] !== ''
            ? (float) $validated['latitude']
            : (float) ($primaryShop?->latitude ?? $profile->shop_latitude ?? 23.7937);
        $lng = isset($validated['longitude']) && $validated['longitude'] !== ''
            ? (float) $validated['longitude']
            : (float) ($primaryShop?->longitude ?? $profile->shop_longitude ?? 90.4066);
        $resolvedGeo = BangladeshLocationResolver::resolve($lat, $lng);

        $city = !empty($validated['city'])
            ? BangladeshLocationResolver::normalizeCity($validated['city'])
            : $resolvedGeo['city'];
        $area = !empty($validated['area'])
            ? trim($validated['area'])
            : $resolvedGeo['area'];

        $address = trim($validated['address']);
        $sameAsGps = $request->boolean('same_as_gps_location');
        $gpsAddress = !empty($validated['gps_address'])
            ? trim($validated['gps_address'])
            : ($sameAsGps ? $address : ($primaryShop?->gps_address ?: $address));
        if ($sameAsGps) {
            $gpsAddress = $address;
        }

        $logoUrl = MediaUploadService::fromRequest($request, 'logo', $primaryShop?->logo_url ?: $profile->logo_url, 1000, 84);

        $skillTags = array_values(array_filter(array_map('trim', $validated['skill_tags'])));

        DB::transaction(function () use (
            $user, $profile, $primaryShop, $validated, $cleanPhone, $address, $gpsAddress, $sameAsGps,
            $city, $area, $lat, $lng, $courierPickup, $inShopRepair, $spareParts, $serviceCategories,
            $skillTags, $logoUrl
        ) {
            $user->update([
                'phone' => $cleanPhone,
                'status' => 'active',
                'is_active' => true,
            ]);

            $profile->update([
                'shop_name' => trim($validated['shop_name']),
                'brand_name' => trim($validated['shop_name']),
                'phone' => $cleanPhone,
                'shop_address' => $address,
                'gps_address' => $gpsAddress,
                'same_as_gps_location' => ($address === $gpsAddress) && $sameAsGps,
                'city' => $city,
                'area' => $area,
                'shop_latitude' => $lat,
                'shop_longitude' => $lng,
                'specialties' => $skillTags,
                'courier_pickup' => $courierPickup,
                'in_shop_repair' => $inShopRepair,
                'spare_parts' => $spareParts,
                'notes' => !empty($validated['description']) ? trim($validated['description']) : $profile->notes,
                'logo_url' => $logoUrl,
            ]);

            $shopData = [
                'name' => trim($validated['shop_name']),
                'description' => !empty($validated['description']) ? trim($validated['description']) : "Verified electronic repair workshop in {$area}, {$city}.",
                'logo_url' => $logoUrl,
                'phone' => $cleanPhone,
                'email' => $profile->business_email ?: $user->email,
                'address' => $address,
                'gps_address' => $gpsAddress,
                'same_as_gps_location' => ($address === $gpsAddress) && $sameAsGps,
                'city' => $city,
                'area' => $area,
                'latitude' => $lat,
                'longitude' => $lng,
                'categories' => $serviceCategories,
                'specialties' => $skillTags,
                'supports_pickup' => $courierPickup,
                'inspection_fee' => (float) $validated['inspection_fee'],
                'base_labor_fee' => (float) $validated['base_labor_fee'],
                'pickup_fee' => isset($validated['pickup_fee']) && $validated['pickup_fee'] !== '' ? (float) $validated['pickup_fee'] : null,
                'express_fee' => isset($validated['express_fee']) && $validated['express_fee'] !== '' ? (float) $validated['express_fee'] : null,
                'is_active' => true,
                'is_public' => true,
                'setup_complete' => true,
                'accepts_direct_orders' => true,
            ];

            if ($primaryShop) {
                $primaryShop->update($shopData);
                $shop = $primaryShop;
            } else {
                $slug = Str::slug($validated['shop_name']) ?: ('shop-' . $profile->id);
                if (RepairShop::where('slug', $slug)->exists()) {
                    $slug .= '-' . $profile->id;
                }
                $shop = RepairShop::create(array_merge($shopData, [
                    'vendor_profile_id' => $profile->id,
                    'slug' => $slug,
                    'rating' => 0.00,
                    'review_count' => 0,
                ]));
            }

            // Sync vendor-configured services into `shop_services`
            foreach ($skillTags as $idx => $rawTag) {
                $parts = explode('|', $rawTag, 2);
                $svcName = trim($parts[0]);
                $svcPrice = isset($parts[1]) && is_numeric(trim($parts[1])) ? (float) trim($parts[1]) : null;

                if ($svcName === '' || in_array(strtolower($svcName), self::NON_SERVICE_TAG_WORDS, true)) {
                    continue;
                }

                $svcSlug = Str::slug($svcName) ?: ('service-' . ($idx + 1));
                ShopService::updateOrCreate(
                    ['shop_id' => $shop->id, 'name' => $svcName],
                    [
                        'slug' => $svcSlug,
                        'pricing_type' => $svcPrice !== null ? 'STARTING_FROM' : 'INSPECTION_REQUIRED',
                        'base_price' => $svcPrice,
                        'includes_pickup' => $courierPickup,
                        'sort_order' => $idx,
                        'is_active' => true,
                    ]
                );
            }
        });

        return redirect()
            ->route('vendor.dashboard')
            ->with('success', "Shop setup completed! '{$validated['shop_name']}' is now live and publicly listed on Meramot.");
    }

    /**
     * Display vendor onboarding status or application view (`GET /vendor/onboarding`).
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
     * Store new or updated vendor profile application with coherent Dual Addresses & optional Branching (`POST /vendor/onboarding`).
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
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:15360',
            'verification_document' => 'nullable|file|mimes:jpeg,jpg,png,webp,pdf|max:15360',
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

        $existingVendorProfile = VendorProfile::where('user_id', $user->id)->first();
        $logoUrl = MediaUploadService::fromRequest($request, 'logo', $existingVendorProfile?->logo_url, 1000, 84);
        $documentsPath = MediaUploadService::fromRequest($request, 'verification_document', $existingVendorProfile?->documents_path);

        $profile = VendorProfile::updateOrCreate(
            ['user_id' => $user->id],
            [
                'owner_name' => $user->name,
                'business_email' => $user->email,
                'phone' => $user->phone,
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
                'specialties' => $validated['categories'],
                'logo_url' => $logoUrl,
                'documents_path' => $documentsPath,
                'verification_status' => 'pending',
                'rejection_reason' => null,
                'rejected_at' => null,
                'rejection_visible_until' => null,
                'reviewed_by_admin_id' => null,
                'reviewed_at' => null,
                'commission_rate' => 10.00,
                'rating' => 0.00,
            ]
        );

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
                'logo_url' => $logoUrl ?: $primaryShop->logo_url,
                'address' => $vendorAddress,
                'gps_address' => $gpsAddress,
                'same_as_gps_location' => ($vendorAddress === $gpsAddress) && $sameAsGps,
                'city' => $city,
                'area' => $area,
                'latitude' => $lat,
                'longitude' => $lng,
                'specialties' => $validated['categories'],
                'inspection_fee' => $primaryShop->inspection_fee ?? 250,
                'base_labor_fee' => $primaryShop->base_labor_fee ?? 500,
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
                'logo_url' => $logoUrl,
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
     * Admin Web Desk: Review Pending, Under Review, Rejected & Approved Vendor Profiles (Feature R11).
     * Route: GET /admin/vendors/pending
     */
    public function adminIndex(Request $request)
    {
        $pendingVendors = VendorProfile::with(['user', 'shops'])
            ->whereIn('verification_status', ['pending', 'under_review'])
            ->orderByDesc('updated_at')
            ->get();

        $rejectedVendors = VendorProfile::with(['user', 'shops'])
            ->where('verification_status', 'rejected')
            ->orderByDesc('updated_at')
            ->take(20)
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
            'pendingCount' => VendorProfile::whereIn('verification_status', ['pending', 'under_review'])->count(),
            'underReviewCount' => VendorProfile::where('verification_status', 'under_review')->count(),
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
     * Admin Web Action: Approve vendor onboarding application.
     * Route: POST /admin/vendors/{id}/approve
     */
    public function approve($id)
    {
        $profile = VendorProfile::with(['user', 'shops'])->findOrFail($id);
        $previousStatus = $profile->verification_status;

        $profile->update([
            'verification_status' => 'approved',
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejection_visible_until' => null,
            'reviewed_by_admin_id' => auth()->id(),
            'reviewed_at' => now(),
        ]);

        if ($profile->user) {
            $profile->user->update([
                'role' => 'vendor',
                'is_active' => true,
            ]);

            if (!empty($profile->user->email)) {
                app(GmailApiService::class)->sendVendorApprovalEmail(
                    $profile->user->email,
                    $profile->user->name ?? 'Vendor Partner',
                    $profile->shop_name
                );
            }
        }

        $requiresPostApprovalSetup = false;
        foreach ($profile->shops as $shop) {
            $alreadyConfigured = $shop->inspection_fee !== null
                && $shop->base_labor_fee !== null
                && !empty($shop->address);

            if ($alreadyConfigured) {
                $shop->update([
                    'is_active' => true,
                    'is_public' => true,
                    'setup_complete' => true,
                    'accepts_direct_orders' => true,
                ]);
            } else {
                $requiresPostApprovalSetup = true;
                $shop->update([
                    'is_active' => false,
                    'is_public' => false,
                    'setup_complete' => false,
                    'accepts_direct_orders' => false,
                ]);
            }
        }

        AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => 'vendor_approved',
            'target_resource' => "vendor_profiles:{$profile->id}",
            'payload_before' => ['verification_status' => $previousStatus],
            'payload_after' => ['verification_status' => 'approved', 'shop_name' => $profile->shop_name],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        $msg = $requiresPostApprovalSetup
            ? "Approved '{$profile->shop_name}'! The vendor can now complete shop pricing & services setup to go live."
            : "Approved '{$profile->shop_name}'! Its repair storefront is now verified and publicly searchable across Meramot.";

        return redirect()->back()->with('success', $msg);
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

        $profile = VendorProfile::with(['user', 'shops'])->findOrFail($id);
        $previousStatus = $profile->verification_status;

        $profile->update([
            'verification_status' => 'rejected',
            'rejection_reason' => $validated['rejection_reason'],
            'rejected_at' => now(),
            'rejection_visible_until' => now()->addDays(7),
            'reviewed_by_admin_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        RepairShop::where('vendor_profile_id', $profile->id)->update([
            'is_active' => false,
            'is_public' => false,
            'setup_complete' => false,
            'accepts_direct_orders' => false,
        ]);

        $notifyEmail = $profile->business_email ?: $profile->user?->email;
        if (!empty($notifyEmail)) {
            app(GmailApiService::class)->sendVendorRejectionEmail(
                $notifyEmail,
                $profile->owner_name ?: ($profile->user?->name ?? 'Vendor Applicant'),
                $profile->shop_name,
                $validated['rejection_reason']
            );
        }

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => $previousStatus === 'approved' ? 'vendor_revoked' : 'vendor_rejected',
            'target_resource' => "vendor_profiles:{$profile->id}",
            'payload_before' => ['verification_status' => $previousStatus],
            'payload_after' => ['verification_status' => 'rejected', 'rejection_reason' => $validated['rejection_reason']],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $actionLabel = $previousStatus === 'approved'
            ? "Revoked approval for '{$profile->shop_name}', unpublished its storefronts, and sent remediation notice."
            : "Rejected vendor application '{$profile->shop_name}' and sent remediation notes to applicant.";

        return redirect()->back()->with('warning', $actionLabel);
    }

    /**
     * Admin Web Action: Change vendor application status to any lifecycle state
     * (`pending`, `under_review`, `approved`, `rejected`).
     * Route: POST /admin/vendors/{id}/status
     */
    public function updateStatus(Request $request, $id)
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,under_review,approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string|max:500',
        ]);

        $newStatus = $validated['status'];

        if ($newStatus === 'approved') {
            return $this->approve($id);
        }

        if ($newStatus === 'rejected') {
            return $this->reject($request, $id);
        }

        $profile = VendorProfile::with(['user', 'shops'])->findOrFail($id);
        $previousStatus = $profile->verification_status;

        $profile->update([
            'verification_status' => $newStatus,
            'rejection_reason' => null,
            'rejected_at' => null,
            'rejection_visible_until' => null,
            'reviewed_by_admin_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        RepairShop::where('vendor_profile_id', $profile->id)->update([
            'is_active' => false,
            'is_public' => false,
            'accepts_direct_orders' => false,
        ]);

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'vendor_status_' . $newStatus,
            'target_resource' => "vendor_profiles:{$profile->id}",
            'payload_before' => ['verification_status' => $previousStatus],
            'payload_after' => ['verification_status' => $newStatus, 'shop_name' => $profile->shop_name],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        $statusLabel = strtoupper(str_replace('_', ' ', $newStatus));

        return redirect()->back()->with(
            'success',
            "Updated '{$profile->shop_name}' application status to {$statusLabel}."
        );
    }

    /**
     * Admin Web Action: Permanently delete a vendor application (Pending/Rejected/Test).
     * Route: DELETE /admin/vendors/{id}
     */
    public function destroy(Request $request, $id)
    {
        $profile = VendorProfile::with(['user', 'shops'])->findOrFail($id);
        $shopName = $profile->shop_name;
        $previousStatus = $profile->verification_status;

        DB::transaction(function () use ($profile, $request, $shopName, $previousStatus) {
            foreach ($profile->shops as $shop) {
                DB::table('shop_staff')->where('shop_id', $shop->id)->delete();
                ShopService::where('shop_id', $shop->id)->delete();
                $shop->delete();
            }

            if ($profile->user && $profile->user->role === 'vendor') {
                $profile->user->update(['role' => 'customer']);
            }

            $profileId = $profile->id;
            $profile->delete();

            AuditLog::create([
                'actor_id' => $request->user()?->id,
                'action' => 'vendor_application_deleted',
                'target_resource' => "vendor_profiles:{$profileId}",
                'payload_before' => ['verification_status' => $previousStatus, 'shop_name' => $shopName],
                'payload_after' => ['deleted' => true],
                'ip_address' => $request->ip() ?? '127.0.0.1',
            ]);
        });

        return redirect()->back()->with(
            'warning',
            "Deleted vendor application '{$shopName}' and removed its draft storefront records."
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
     * API action to verify/approve, mark under_review, or reject vendor profile.
     * Route: POST /api/vendors/{id}/verify
     */
    public function verify(Request $request, $id)
    {
        $profile = VendorProfile::with(['user', 'shops'])->findOrFail($id);
        $previousStatus = $profile->verification_status;
        $action = strtolower((string) $request->input('action', 'approve'));

        $status = match ($action) {
            'approve', 'approved' => 'approved',
            'under_review', 'review' => 'under_review',
            'pending' => 'pending',
            default => 'rejected',
        };
        $isApproved = $status === 'approved';
        $isRejected = $status === 'rejected';
        $reason = $isRejected ? ($request->input('reason') ?: $request->input('rejection_reason') ?: 'Trade credentials require verification.') : null;

        $profile->update([
            'verification_status' => $status,
            'rejection_reason' => $reason,
            'rejected_at' => $isRejected ? now() : null,
            'rejection_visible_until' => $isRejected ? now()->addDays(7) : null,
            'reviewed_by_admin_id' => $request->user()?->id,
            'reviewed_at' => now(),
        ]);

        if ($isApproved) {
            foreach ($profile->shops as $shop) {
                $alreadyConfigured = $shop->inspection_fee !== null
                    && $shop->base_labor_fee !== null
                    && !empty($shop->address);

                $shop->update([
                    'is_active' => $alreadyConfigured,
                    'is_public' => $alreadyConfigured,
                    'setup_complete' => $alreadyConfigured,
                    'accepts_direct_orders' => $alreadyConfigured,
                ]);
            }
        } else {
            RepairShop::where('vendor_profile_id', $profile->id)->update([
                'is_active' => false,
                'is_public' => false,
                'setup_complete' => false,
                'accepts_direct_orders' => false,
            ]);
        }

        AuditLog::create([
            'actor_id' => $request->user()?->id,
            'action' => 'vendor_' . $status,
            'target_resource' => "vendor_profiles:{$profile->id}",
            'payload_before' => ['verification_status' => $previousStatus],
            'payload_after' => ['verification_status' => $status, 'rejection_reason' => $reason],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Vendor profile has been {$status}.",
            'data' => $profile->fresh('shops'),
        ]);
    }

    /**
     * Inline Admin Document Preview Endpoint for Vendor Profiles
     * Route: GET /admin/vendors/{id}/document/{type}
     */
    public function viewDocument($id, string $type)
    {
        $profile = VendorProfile::with('shops')->findOrFail($id);
        $primaryShop = $profile->shops->sortBy('id')->first();

        $raw = match ($type) {
            'logo' => $profile->logo_url ?: $primaryShop?->logo_url,
            'document' => $profile->documents_path,
            default => null,
        };

        if (!$raw) {
            abort(404, 'Document not uploaded for this vendor application.');
        }

        if (preg_match('/^data:([^;]+);base64,(.+)$/s', $raw, $matches)) {
            $mime = $matches[1];
            $binary = base64_decode($matches[2], true);
            if ($binary === false) {
                abort(422, 'Corrupted document data.');
            }

            return response($binary, 200)
                ->header('Content-Type', $mime)
                ->header('Content-Disposition', 'inline; filename="vendor-' . $profile->id . '-' . $type . '"');
        }

        return redirect($raw);
    }
}

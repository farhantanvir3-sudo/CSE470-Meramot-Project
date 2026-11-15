<?php

namespace App\Http\Controllers;

use App\Models\Cart;
use App\Models\CartItem;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ServicePackage;
use App\Models\ShopService;
use App\Models\SparePartListing;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VendorProfile;
use App\Services\GeolocationService;
use App\Services\MarketplacePricingService;
use App\Services\MarketplaceSearchEngine;
use App\Support\InputSanitizer;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ============================================================================
 * FEATURE R8: Direct Service Package Catalog & Unified Direct-Order Cart Flow
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Merged Architecture: Farhan Tanvir [22299179] (Direct-Order + Quickmatch)
 * Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
 * Models Touched: ServicePackage, Cart, CartItem, RepairShop, RepairRequest, RepairJob
 * Views Rendered (from root `frontend/views/`):
 *   - packages/catalog.blade.php
 *   - packages/cart.blade.php
 *   - packages/checkout.blade.php
 * ============================================================================
 */
class ServicePackageController extends Controller
{
    public const REGULAR_DELIVERY_FEE = 80;
    public const EXPRESS_DELIVERY_FEE = 150;
    public const SERVICE_CHARGE_RATE = 0.05;
    public const MIN_SERVICE_CHARGE = 30;
    public const MAX_QUICKMATCH_RADIUS_KM = 20.0;
    public const PROXIMITY_BAND_KM = 1.0;

    public static function resolveCommissionRatePct(?RepairShop $shop = null, array $customerLocation = [], array $cart = []): float
    {
        if (empty($customerLocation) && empty($cart)) {
            return $shop
                ? $shop->effectiveCommissionRatePct()
                : VendorProfile::DEFAULT_COMMISSION_RATE;
        }

        return MarketplacePricingService::resolveOrderFeeRatePct($shop, $customerLocation, [
            'cart' => $cart,
        ]);
    }

    public static function calculateServiceCharge(float $subtotal, ?RepairShop $shop = null, array $customerLocation = [], array $cart = []): float
    {
        if ($subtotal <= 0) {
            return 0.0;
        }
        $ratePct = self::resolveCommissionRatePct($shop, $customerLocation, $cart);
        return round($subtotal * ($ratePct / 100.0), 2);
    }

    /**
     * Evaluate promotional voucher codes against cart subtotal, delivery fee, and matched shop perks.
     *
     * @return array{valid: bool, code: ?string, discount: float, label: string, error: ?string}
     */
    public static function evaluateVoucher(
        ?string $rawCode,
        float $subtotal,
        float $deliveryFee = 80.0,
        ?RepairShop $shop = null,
        ?User $user = null,
        ?float $activeFeeRatePct = null
    ): array {
        $code = strtoupper(trim((string) $rawCode));
        if ($code === '' || $subtotal <= 0) {
            return ['valid' => false, 'code' => null, 'discount' => 0.0, 'label' => '', 'error' => null];
        }

        if ($code === 'MERAMOT10') {
            $discount = (float) min(500.0, round($subtotal * 0.10, 2));
            return [
                'valid' => true,
                'code' => 'MERAMOT10',
                'discount' => $discount,
                'label' => '10% Marketplace Discount (up to ৳500)',
                'error' => null,
            ];
        }

        if ($code === 'FIRSTFIX100') {
            if ($subtotal < 800) {
                return [
                    'valid' => false,
                    'code' => null,
                    'discount' => 0.0,
                    'label' => '',
                    'error' => 'FIRSTFIX100 requires a minimum service subtotal of ৳800.',
                ];
            }
            return [
                'valid' => true,
                'code' => 'FIRSTFIX100',
                'discount' => 100.0,
                'label' => '৳100 Off Direct Repair Booking',
                'error' => null,
            ];
        }

        if ($code === 'FREEDELIVERY') {
            return [
                'valid' => true,
                'code' => 'FREEDELIVERY',
                'discount' => (float) $deliveryFee,
                'label' => 'Two-Way Doorstep Courier Fee Waived',
                'error' => null,
            ];
        }

        if ($code === 'SHOPFREE') {
            if (!$shop || !$shop->free_delivery) {
                return [
                    'valid' => false,
                    'code' => null,
                    'discount' => 0.0,
                    'label' => '',
                    'error' => 'SHOPFREE applies only when the selected workshop sponsors Free Delivery.',
                ];
            }
            return [
                'valid' => true,
                'code' => 'SHOPFREE',
                'discount' => (float) min($deliveryFee, self::REGULAR_DELIVERY_FEE),
                'label' => 'Platform Regular Pickup & Delivery Sponsored',
                'error' => null,
            ];
        }

        if (in_array($code, ['FIRSTSHOP1', 'WELCOME1'], true)) {
            if (!$user) {
                return [
                    'valid' => false,
                    'code' => null,
                    'discount' => 0.0,
                    'label' => '',
                    'error' => 'Sign in to redeem this first-order workshop offer.',
                ];
            }
            if (!$shop) {
                return [
                    'valid' => false,
                    'code' => null,
                    'discount' => 0.0,
                    'label' => '',
                    'error' => 'Select a workshop first to verify first-order eligibility.',
                ];
            }
            if ($subtotal < MarketplacePricingService::FIRST_SHOP_PROMO_MIN_SUBTOTAL) {
                return [
                    'valid' => false,
                    'code' => null,
                    'discount' => 0.0,
                    'label' => '',
                    'error' => $code . ' requires a minimum order subtotal of ৳' . number_format(MarketplacePricingService::FIRST_SHOP_PROMO_MIN_SUBTOTAL, 0) . '.',
                ];
            }
            if (!MarketplacePricingService::isEligibleForFirstShopOrderPromo($user, $shop)) {
                return [
                    'valid' => false,
                    'code' => null,
                    'discount' => 0.0,
                    'label' => '',
                    'error' => 'This welcome offer is valid only on your very first order with this workshop.',
                ];
            }

            $ratePct = $activeFeeRatePct ?? self::resolveCommissionRatePct($shop);
            $promo = MarketplacePricingService::calculateFirstShopPromoDiscount($subtotal, $ratePct);

            return [
                'valid' => true,
                'code' => $code,
                'discount' => (float) $promo['discount_amount'],
                'label' => 'First Order at Workshop — Reduced Service Fee Offer',
                'error' => null,
            ];
        }

        return [
            'valid' => false,
            'code' => null,
            'discount' => 0.0,
            'label' => '',
            'error' => "Promo code '{$code}' is invalid or expired. Try MERAMOT10, FIRSTFIX100, or FREEDELIVERY.",
        ];
    }

    /**
     * Apply a promotional voucher to the active cart session.
     * Route: POST /cart/voucher
     */
    public function applyVoucher(Request $request)
    {
        $cart = session()->get('cart', []);
        if ($resp = $this->guardCustomerOrdering($request, $this->cartHasSpareParts($cart))) {
            return $resp;
        }

        $validated = $request->validate([
            'voucher_code' => 'required|string|max:40',
        ]);

        $subtotal = array_reduce($cart, fn ($sum, $item) => $sum + ((float) $item['price'] * (int) $item['quantity']), 0.0);
        $activeShop = !empty($cart) ? $this->resolveTargetShop(null, $request) : null;
        $customerLocation = $this->resolveCustomerLocation($request);
        $deliveryQuote = $this->resolveCartDeliveryQuote($cart, $activeShop, $customerLocation, 'REGULAR');
        $commissionRatePct = self::resolveCommissionRatePct($activeShop, $customerLocation, $cart);

        $eval = self::evaluateVoucher(
            $validated['voucher_code'],
            $subtotal,
            (float) $deliveryQuote['regular_delivery_fee'],
            $activeShop,
            $request->user(),
            $commissionRatePct
        );
        if (!$eval['valid']) {
            session()->forget('cart_voucher_code');
            if ($request->expectsJson()) {
                return response()->json(['success' => false, 'message' => $eval['error']], 422);
            }
            return back()->with('error', $eval['error'] ?: 'Invalid promo code.');
        }

        session()->put('cart_voucher_code', $eval['code']);

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'voucher_code' => $eval['code'],
                'discount' => $eval['discount'],
                'label' => $eval['label'],
            ]);
        }

        return back()->with('success', "Voucher {$eval['code']} applied — {$eval['label']} (-৳" . number_format($eval['discount'], 0) . ').');
    }

    /**
     * Remove active promotional voucher from cart session.
     * Route: DELETE /cart/voucher
     */
    public function removeVoucher(Request $request)
    {
        session()->forget('cart_voucher_code');
        if ($request->expectsJson()) {
            return response()->json(['success' => true]);
        }
        return back()->with('success', 'Promo voucher removed.');
    }

    /**
     * Persist navbar / browser map location into session and refresh auto-matched workshop.
     * Route: POST /location/sync
     */
    public function syncCustomerLocation(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'nullable|numeric|between:-90,90',
            'lng' => 'nullable|numeric|between:-180,180',
            'city' => 'nullable|string|max:100',
            'area' => 'nullable|string|max:120',
            'address' => 'nullable|string|max:500',
        ]);

        $rawLat = isset($validated['lat']) ? (float) $validated['lat'] : null;
        $rawLng = isset($validated['lng']) ? (float) $validated['lng'] : null;
        $area = trim((string) ($validated['area'] ?? '')) ?: null;
        $address = trim((string) ($validated['address'] ?? '')) ?: null;

        $city = GeolocationService::normalizeCityName(
            $validated['city'] ?? null,
            $area,
            $address,
            $rawLat,
            $rawLng
        );

        $hasCoordinates = $rawLat !== null && $rawLng !== null;
        $lat = $rawLat;
        $lng = $rawLng;

        if (!$hasCoordinates && $area !== null && strcasecmp($area, $city) !== 0) {
            $areaCoords = GeolocationService::getAreaCoordinates($area);
            if ($areaCoords) {
                $lat = $areaCoords['lat'];
                $lng = $areaCoords['lng'];
                $hasCoordinates = true;
            }
        }

        $location = [
            'city' => $city,
            'area' => $area,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
            'has_coordinates' => $hasCoordinates,
            'is_default_fallback' => false,
        ];

        session()->put('customer_location', $location);

        $matched = null;
        $requiredPackageIds = $this->extractRequiredPackageIds();
        if (!session('cart_shop_locked')) {
            session()->forget(['cart_shop_manual', 'cart_shop_id']);
            $matched = $this->quickMatchWorkshop($location, $requiredPackageIds);
            if (!empty(session('cart', []))) {
                if ($matched) {
                    session()->put('cart_shop_id', $matched->id);
                }
                $this->syncDatabaseCart($request->user(), $matched, session('cart', []));
            }
        } elseif (session('cart_shop_id')) {
            $matched = RepairShop::find(session('cart_shop_id'));
        }

        return response()->json([
            'success' => true,
            'city' => $location['city'],
            'area' => $location['area'],
            'shop_id' => $matched?->id,
            'shop_name' => $matched?->name,
        ]);
    }

    /**
     * Resolve customer's active city & coordinates from request, session, or authenticated profile.
     */
    private function resolveCustomerLocation(?Request $request = null): array
    {
        if ($request) {
            $hasReqLoc = $request->filled('customer_lat')
                || $request->filled('lat')
                || $request->filled('customer_city')
                || $request->filled('city')
                || $request->filled('customer_area')
                || $request->filled('area');

            if ($hasReqLoc) {
                $rawLat = $request->filled('customer_lat')
                    ? (float) $request->input('customer_lat')
                    : ($request->filled('lat') ? (float) $request->input('lat') : null);
                $rawLng = $request->filled('customer_lng')
                    ? (float) $request->input('customer_lng')
                    : ($request->filled('lng') ? (float) $request->input('lng') : null);
                $rawCity = $request->input('customer_city', $request->input('city'));
                $rawArea = trim((string) $request->input('customer_area', $request->input('area', ''))) ?: null;
                $rawAddr = trim((string) $request->input('address', '')) ?: null;

                $city = GeolocationService::normalizeCityName($rawCity, $rawArea, $rawAddr, $rawLat, $rawLng);
                $hasCoords = $rawLat !== null && $rawLng !== null;
                $lat = $rawLat;
                $lng = $rawLng;

                if (!$hasCoords && $rawArea !== null && strcasecmp($rawArea, $city) !== 0) {
                    $areaCoords = GeolocationService::getAreaCoordinates($rawArea);
                    if ($areaCoords) {
                        $lat = $areaCoords['lat'];
                        $lng = $areaCoords['lng'];
                        $hasCoords = true;
                    }
                }

                $reqLoc = [
                    'city' => $city,
                    'area' => $rawArea,
                    'address' => $rawAddr,
                    'lat' => $lat,
                    'lng' => $lng,
                    'has_coordinates' => $hasCoords,
                    'is_default_fallback' => false,
                ];
                session()->put('customer_location', $reqLoc);

                return $reqLoc;
            }
        }

        $sessionLoc = session('customer_location');
        if (is_array($sessionLoc) && !empty($sessionLoc['city'])) {
            return $sessionLoc;
        }

        $user = $request?->user() ?? auth()->user();
        if ($user && ($user->latitude !== null || $user->longitude !== null || !empty($user->city) || !empty($user->area) || !empty($user->address))) {
            $rawLat = $user->latitude !== null ? (float) $user->latitude : null;
            $rawLng = $user->longitude !== null ? (float) $user->longitude : null;
            $area = trim((string) ($user->area ?? '')) ?: null;
            $address = trim((string) ($user->address ?? '')) ?: null;
            $city = GeolocationService::normalizeCityName($user->city, $area, $address, $rawLat, $rawLng);

            $hasCoords = $rawLat !== null && $rawLng !== null;
            $lat = $rawLat;
            $lng = $rawLng;

            if (!$hasCoords && $area !== null && strcasecmp($area, $city) !== 0) {
                $areaCoords = GeolocationService::getAreaCoordinates($area);
                if ($areaCoords) {
                    $lat = $areaCoords['lat'];
                    $lng = $areaCoords['lng'];
                    $hasCoords = true;
                }
            }

            return [
                'city' => $city,
                'area' => $area,
                'address' => $address,
                'lat' => $lat,
                'lng' => $lng,
                'has_coordinates' => $hasCoords,
                'is_default_fallback' => false,
            ];
        }

        return [
            'city' => 'Dhaka',
            'area' => null,
            'address' => null,
            'lat' => 23.7806,
            'lng' => 90.4070,
            'has_coordinates' => true,
            'is_default_fallback' => true,
        ];
    }

    /**
     * Resolve geographic coordinates for a workshop (stored lat/lng or area centroid fallback).
     */
    private function resolveShopCoordinates(RepairShop $shop): ?array
    {
        if ($shop->latitude !== null && $shop->longitude !== null) {
            return [
                'lat' => (float) $shop->latitude,
                'lng' => (float) $shop->longitude,
            ];
        }

        if (!empty($shop->area)) {
            $areaCoords = GeolocationService::getAreaCoordinates($shop->area);
            if ($areaCoords) {
                return $areaCoords;
            }
        }

        if (!empty($shop->city)) {
            return GeolocationService::getAreaCoordinates($shop->city);
        }

        return null;
    }

    /**
     * Filter workshops to strictly the customer's city and within max local service radius,
     * computing dynamic Haversine distance when customer coordinates exist.
     */
    private function isStrictPackageCapabilityActive(): bool
    {
        if (!Schema::hasTable('repair_shop_service_package')) {
            return false;
        }

        return DB::table('repair_shop_service_package')->exists()
            || VendorProfile::whereNotNull('provides_fixed_packages')->exists();
    }

    /**
     * @return int[]
     */
    private function extractRequiredPackageIds(?array $cart = null): array
    {
        $items = $cart ?? session()->get('cart', []);
        $ids = [];
        foreach ($items as $item) {
            if (($item['source'] ?? '') === 'QUICKMATCH_PACKAGE' && !empty($item['id'])) {
                $ids[] = (int) $item['id'];
            }
        }
        return array_values(array_unique(array_filter($ids, fn (int $id) => $id > 0)));
    }

    /**
     * @param  int[]  $packageIds
     */
    private function shopSupportsPackages(?RepairShop $shop, array $packageIds): bool
    {
        if (!$shop) {
            return false;
        }
        if (empty($packageIds) || !$this->isStrictPackageCapabilityActive()) {
            return true;
        }

        if ($shop->relationLoaded('servicePackages')) {
            $offered = $shop->servicePackages->pluck('id')->map(fn ($id) => (int) $id)->all();
            foreach ($packageIds as $pkgId) {
                if (!in_array((int) $pkgId, $offered, true)) {
                    return false;
                }
            }
            return true;
        }

        $matchedCount = DB::table('repair_shop_service_package')
            ->where('repair_shop_id', $shop->id)
            ->whereIn('service_package_id', $packageIds)
            ->distinct('service_package_id')
            ->count('service_package_id');

        return $matchedCount >= count($packageIds);
    }

    /**
     * @param  int[]  $packageIds
     */
    private function collectLocalEligibleShops(array $customerLocation, float $maxRadiusKm = self::MAX_QUICKMATCH_RADIUS_KM, array $packageIds = []): Collection
    {
        $city = strtolower(trim((string) ($customerLocation['city'] ?? 'Dhaka')));
        $hasCoords = !empty($customerLocation['has_coordinates'])
            && $customerLocation['lat'] !== null
            && $customerLocation['lng'] !== null;

        $query = RepairShop::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->where('setup_complete', true)
            ->where('accepts_direct_orders', true)
            ->whereRaw('LOWER(TRIM(city)) = ?', [$city]);

        if (!empty($packageIds) && $this->isStrictPackageCapabilityActive()) {
            foreach ($packageIds as $pkgId) {
                $query->whereHas('servicePackages', fn ($q) => $q->where('service_packages.id', (int) $pkgId));
            }
        }

        $candidates = $query->get();

        return $candidates->filter(function (RepairShop $shop) use ($customerLocation, $hasCoords, $maxRadiusKm) {
            if (!$hasCoords) {
                $shop->setAttribute('distance_km', null);
                return true;
            }

            $coords = $this->resolveShopCoordinates($shop);
            if (!$coords) {
                return false;
            }

            $distanceKm = GeolocationService::calculateDistance(
                (float) $customerLocation['lat'],
                (float) $customerLocation['lng'],
                (float) $coords['lat'],
                (float) $coords['lng']
            );

            $shopLimit = ($shop->delivery_radius_km !== null && (float) $shop->delivery_radius_km > 0)
                ? min((float) $shop->delivery_radius_km, $maxRadiusKm)
                : $maxRadiusKm;

            if ($distanceKm > $shopLimit) {
                return false;
            }

            $shop->setAttribute('distance_km', round($distanceKm, 2));
            return true;
        })->values();
    }

    /**
     * Smart Quick-Match Algorithm:
     *   0. Strictly bounded to customer's city & <= MAX_QUICKMATCH_RADIUS_KM (never cross-city or far away).
     *   1. Primary: Nearest proximity band first, then highest rating (and featured tie-break).
     *   2. Fallback: If distance/ratings cannot resolve a match, prefer Featured shops in the same city.
     *   3. Final Local Fallback: Best rated / closest verified same-city shop within range, else null.
     */
    /**
     * @param  int[]  $packageIds
     */
    public function quickMatchWorkshop(array $customerLocation, array $packageIds = []): ?RepairShop
    {
        $eligible = $this->collectLocalEligibleShops($customerLocation, self::MAX_QUICKMATCH_RADIUS_KM, $packageIds);
        if ($eligible->isEmpty()) {
            return null;
        }

        $hasCoords = !empty($customerLocation['has_coordinates'])
            && $customerLocation['lat'] !== null
            && $customerLocation['lng'] !== null;

        // Tier 1: Nearest distance band + highest rating
        if ($hasCoords) {
            $ratedLocal = $eligible->filter(fn (RepairShop $s) => (float) $s->rating > 0 && $s->distance_km !== null);
            $qualityPool = $ratedLocal->filter(fn (RepairShop $s) => (float) $s->rating >= 3.5);
            $tierOnePool = ($qualityPool->isNotEmpty() ? $qualityPool : $ratedLocal)->all();

            if (!empty($tierOnePool)) {
                usort($tierOnePool, function (RepairShop $a, RepairShop $b) {
                    $bandA = (int) floor(round((float) $a->distance_km, 1) / self::PROXIMITY_BAND_KM);
                    $bandB = (int) floor(round((float) $b->distance_km, 1) / self::PROXIMITY_BAND_KM);
                    if ($bandA !== $bandB) {
                        return $bandA <=> $bandB;
                    }

                    $ratingCmp = (float) $b->rating <=> (float) $a->rating;
                    if ($ratingCmp !== 0) {
                        return $ratingCmp;
                    }

                    $featCmp = ((int) $b->is_featured) <=> ((int) $a->is_featured);
                    if ($featCmp !== 0) {
                        return $featCmp;
                    }

                    $reviewCmp = ((int) $b->review_count) <=> ((int) $a->review_count);
                    if ($reviewCmp !== 0) {
                        return $reviewCmp;
                    }

                    return (float) $a->distance_km <=> (float) $b->distance_km;
                });

                return $tierOnePool[0];
            }
        }

        // Tier 2: Featured workshops in the same city / local radius
        $featuredLocal = $eligible->filter(fn (RepairShop $s) => (bool) $s->is_featured)->all();
        if (!empty($featuredLocal)) {
            usort($featuredLocal, function (RepairShop $a, RepairShop $b) use ($hasCoords) {
                if ($hasCoords && $a->distance_km !== null && $b->distance_km !== null) {
                    $bandA = (int) floor(round((float) $a->distance_km, 1) / self::PROXIMITY_BAND_KM);
                    $bandB = (int) floor(round((float) $b->distance_km, 1) / self::PROXIMITY_BAND_KM);
                    if ($bandA !== $bandB) {
                        return $bandA <=> $bandB;
                    }
                }

                $ratingCmp = (float) $b->rating <=> (float) $a->rating;
                if ($ratingCmp !== 0) {
                    return $ratingCmp;
                }

                $reviewCmp = ((int) $b->review_count) <=> ((int) $a->review_count);
                if ($reviewCmp !== 0) {
                    return $reviewCmp;
                }

                $featAtA = $a->featured_at ? $a->featured_at->getTimestamp() : 0;
                $featAtB = $b->featured_at ? $b->featured_at->getTimestamp() : 0;
                if ($featAtA !== $featAtB) {
                    return $featAtB <=> $featAtA;
                }

                if ($hasCoords && $a->distance_km !== null && $b->distance_km !== null) {
                    return (float) $a->distance_km <=> (float) $b->distance_km;
                }

                return $a->id <=> $b->id;
            });

            return $featuredLocal[0];
        }

        // Tier 3: Remaining verified local workshops within same city & radius
        $fallbackPool = $eligible->all();
        usort($fallbackPool, function (RepairShop $a, RepairShop $b) use ($hasCoords) {
            $hasRatingA = (float) $a->rating > 0 ? 1 : 0;
            $hasRatingB = (float) $b->rating > 0 ? 1 : 0;
            if ($hasRatingA !== $hasRatingB) {
                return $hasRatingB <=> $hasRatingA;
            }

            if ($hasCoords && $a->distance_km !== null && $b->distance_km !== null) {
                $bandA = (int) floor(round((float) $a->distance_km, 1) / self::PROXIMITY_BAND_KM);
                $bandB = (int) floor(round((float) $b->distance_km, 1) / self::PROXIMITY_BAND_KM);
                if ($bandA !== $bandB) {
                    return $bandA <=> $bandB;
                }
            }

            $ratingCmp = (float) $b->rating <=> (float) $a->rating;
            if ($ratingCmp !== 0) {
                return $ratingCmp;
            }

            $reviewCmp = ((int) $b->review_count) <=> ((int) $a->review_count);
            if ($reviewCmp !== 0) {
                return $reviewCmp;
            }

            if ($hasCoords && $a->distance_km !== null && $b->distance_km !== null) {
                return (float) $a->distance_km <=> (float) $b->distance_km;
            }

            return $a->id <=> $b->id;
        });

        return $fallbackPool[0] ?? null;
    }

    /**
     * Attach computed Haversine distance onto a resolved workshop if customer coordinates exist.
     */
    private function attachDistanceToShop(?RepairShop $shop, array $customerLocation): ?RepairShop
    {
        if (!$shop) {
            return null;
        }

        $hasCoords = !empty($customerLocation['has_coordinates'])
            && $customerLocation['lat'] !== null
            && $customerLocation['lng'] !== null;

        if (!$hasCoords) {
            $shop->setAttribute('distance_km', null);
            return $shop;
        }

        $coords = $this->resolveShopCoordinates($shop);
        if ($coords) {
            $dist = GeolocationService::calculateDistance(
                (float) $customerLocation['lat'],
                (float) $customerLocation['lng'],
                (float) $coords['lat'],
                (float) $coords['lng']
            );
            $shop->setAttribute('distance_km', round($dist, 2));
        } else {
            $shop->setAttribute('distance_km', null);
        }

        return $shop;
    }

    /**
     * Return bounded local-city workshops sorted by proximity, rating & featured status for dropdown selectors.
     */
    /**
     * Sort local eligible workshops by proximity band, rating, featured priority, exact distance, and name.
     *
     * @param  RepairShop[]  $shops
     * @return RepairShop[]
     */
    private function sortLocalShops(array $shops, bool $hasCoords): array
    {
        usort($shops, function (RepairShop $a, RepairShop $b) use ($hasCoords) {
            $rankA = ((float) $a->rating > 0 || $a->is_featured) ? 1 : 0;
            $rankB = ((float) $b->rating > 0 || $b->is_featured) ? 1 : 0;
            if ($rankA !== $rankB) {
                return $rankB <=> $rankA;
            }

            if ($hasCoords && $a->distance_km !== null && $b->distance_km !== null) {
                $bandA = (int) floor(round((float) $a->distance_km, 1) / self::PROXIMITY_BAND_KM);
                $bandB = (int) floor(round((float) $b->distance_km, 1) / self::PROXIMITY_BAND_KM);
                if ($bandA !== $bandB) {
                    return $bandA <=> $bandB;
                }
            }

            $ratingCmp = (float) $b->rating <=> (float) $a->rating;
            if ($ratingCmp !== 0) {
                return $ratingCmp;
            }

            $featCmp = ((int) $b->is_featured) <=> ((int) $a->is_featured);
            if ($featCmp !== 0) {
                return $featCmp;
            }

            if ($hasCoords && $a->distance_km !== null && $b->distance_km !== null) {
                return (float) $a->distance_km <=> (float) $b->distance_km;
            }

            return strcmp((string) $a->name, (string) $b->name);
        });

        return $shops;
    }

    /**
     * Return bounded local-city workshops sorted by proximity, rating & featured status for dropdown selectors.
     *
     * @param  int[]  $packageIds
     */
    private function getLocalSelectableShops(array $customerLocation, ?RepairShop $activeShop = null, array $packageIds = []): Collection
    {
        $hasCoords = !empty($customerLocation['has_coordinates'])
            && $customerLocation['lat'] !== null
            && $customerLocation['lng'] !== null;

        $shops = $this->sortLocalShops(
            $this->collectLocalEligibleShops($customerLocation, 25.0, $packageIds)->all(),
            $hasCoords
        );

        $collection = collect(array_slice($shops, 0, 25));

        if ($activeShop && $this->shopSupportsPackages($activeShop, $packageIds) && !$collection->contains('id', $activeShop->id)) {
            $collection->prepend($activeShop);
        }

        return $collection;
    }

    /**
     * Resolve target workshop for Direct Order (explicit/locked shop or Smart Quick-Match).
     *
     * @param  int[]  $packageIds
     */
    private function resolveTargetShop(?int $requestedShopId = null, ?Request $request = null, array $packageIds = []): ?RepairShop
    {
        $customerLocation = $this->resolveCustomerLocation($request);

        if ($requestedShopId) {
            $shop = RepairShop::where('is_active', true)->where('is_public', true)->find($requestedShopId);
            if ($shop) {
                if (!empty($packageIds) && !$this->shopSupportsPackages($shop, $packageIds)) {
                    return null;
                }
                return $this->attachDistanceToShop($shop, $customerLocation);
            }
            return null;
        }

        $sessionShopId = session('cart_shop_id');
        if ($sessionShopId) {
            $shop = RepairShop::where('is_active', true)->where('is_public', true)->find($sessionShopId);
            if ($shop) {
                if (session('cart_shop_locked')) {
                    if (!empty($packageIds) && !$this->shopSupportsPackages($shop, $packageIds)) {
                        return null;
                    }
                    return $this->attachDistanceToShop($shop, $customerLocation);
                }

                if (session('cart_shop_manual')) {
                    if (strcasecmp((string) $shop->city, (string) ($customerLocation['city'] ?? 'Dhaka')) === 0) {
                        if (!empty($packageIds) && !$this->shopSupportsPackages($shop, $packageIds)) {
                            return null;
                        }
                        return $this->attachDistanceToShop($shop, $customerLocation);
                    }
                    session()->forget(['cart_shop_manual', 'cart_shop_id']);
                } else {
                    $matched = $this->quickMatchWorkshop($customerLocation, $packageIds);
                    if ($matched) {
                        session()->put('cart_shop_id', $matched->id);
                    } else {
                        session()->forget('cart_shop_id');
                    }
                    return $matched;
                }
            }
        }

        $matched = $this->quickMatchWorkshop($customerLocation, $packageIds);
        if ($matched) {
            session()->put('cart_shop_id', $matched->id);
        }

        return $matched;
    }

    /**
     * Persist session cart into PostgreSQL `carts` & `cart_items` when user & shop exist.
     */
    private function syncDatabaseCart(?User $user, ?RepairShop $shop, array $sessionCart): void
    {
        if (!$user || !$shop) {
            return;
        }

        $dbCart = Cart::firstOrCreate(
            [
                'user_id' => $user->id,
                'shop_id' => $shop->id,
                'status' => 'ACTIVE',
            ]
        );

        $dbCart->items()->delete();

        if (empty($sessionCart)) {
            $dbCart->update(['status' => 'ABANDONED']);
            return;
        }

        foreach ($sessionCart as $key => $item) {
            CartItem::create([
                'cart_id' => $dbCart->id,
                'service_name' => $item['title'],
                'description' => $item['description'] ?? ($item['category'] ?? 'Direct Repair Service'),
                'price' => $item['price'],
                'quantity' => $item['quantity'],
                'metadata' => [
                    'item_key' => (string) $key,
                    'source' => $item['source'] ?? 'PACKAGE',
                    'duration' => $item['duration'] ?? 24,
                ],
            ]);
        }
    }

    /**
     * Display service package catalog with Smart Quick-Match & local shop selector.
     * Route: GET /packages/catalog
     */
    public function index(Request $request)
    {
        $query = ServicePackage::query()->where('is_active', true);

        if ($request->filled('category')) {
            $query->where('device_category', $request->input('category'));
        }

        if ($request->filled('max_price')) {
            $query->where('fixed_price', '<=', (float) $request->input('max_price'));
        }

        $searchRelevanceOrder = MarketplaceSearchEngine::applySearch(
            $query,
            $request->input('q'),
            ['title', 'device_category'],
            ['description', 'CAST(inclusions AS TEXT)']
        );

        $sort = $request->input('sort', 'popular');
        if ($searchRelevanceOrder !== null && ($sort === 'popular' || !($searchRelevanceOrder['all_matched'] ?? true))) {
            $query->orderByRaw($searchRelevanceOrder['sql'], $searchRelevanceOrder['bindings']);
        }
        switch ($sort) {
            case 'price_low':
                $query->orderBy('fixed_price', 'asc');
                break;
            case 'price_high':
                $query->orderBy('fixed_price', 'desc');
                break;
            case 'popular':
            default:
                $query->orderBy('is_popular', 'desc')
                      ->orderBy('order_count', 'desc');
                break;
        }

        $packages = $query->paginate(12)->withQueryString();

        $categories = ServicePackage::select('device_category')
            ->distinct()
            ->pluck('device_category')
            ->toArray();

        $customerLocation = $this->resolveCustomerLocation($request);
        $requiredPackageIds = $this->extractRequiredPackageIds();
        $activeShop = session('cart_shop_id') ? $this->resolveTargetShop(null, $request, $requiredPackageIds) : null;
        $verifiedShops = $this->getLocalSelectableShops($customerLocation, $activeShop);
        $cartCount = count(session()->get('cart', []));

        $packageShopsMap = [];
        $pkgIds = $packages->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();
        if (!empty($pkgIds)) {
            if ($this->isStrictPackageCapabilityActive()) {
                $hasCoords = !empty($customerLocation['has_coordinates'])
                    && $customerLocation['lat'] !== null
                    && $customerLocation['lng'] !== null;
                $allSortedLocal = $this->sortLocalShops(
                    $this->collectLocalEligibleShops($customerLocation, 25.0)->all(),
                    $hasCoords
                );
                $pivotRows = DB::table('repair_shop_service_package')
                    ->whereIn('service_package_id', $pkgIds)
                    ->get(['repair_shop_id', 'service_package_id']);

                $pivotByPkg = [];
                foreach ($pivotRows as $row) {
                    $pivotByPkg[(int) $row->service_package_id][(int) $row->repair_shop_id] = true;
                }

                foreach ($pkgIds as $pkgId) {
                    $allowedShopIds = $pivotByPkg[$pkgId] ?? [];
                    $matched = array_values(array_filter(
                        $allSortedLocal,
                        fn (RepairShop $s) => isset($allowedShopIds[(int) $s->id])
                    ));
                    $col = collect(array_slice($matched, 0, 25));
                    if ($activeShop && isset($allowedShopIds[(int) $activeShop->id]) && !$col->contains('id', $activeShop->id)) {
                        $col->prepend($activeShop);
                    }
                    $packageShopsMap[$pkgId] = $col;
                }
            } else {
                foreach ($pkgIds as $pkgId) {
                    $packageShopsMap[$pkgId] = $verifiedShops;
                }
            }
        }

        return view('packages.catalog', compact('packages', 'categories', 'verifiedShops', 'packageShopsMap', 'cartCount', 'customerLocation'));
    }

    /**
     * Check whether the active cart contains Spare Part marketplace items.
     */
    private function cartHasSpareParts(array $cart): bool
    {
        foreach ($cart as $item) {
            if (($item['source'] ?? '') === 'SPARE_PART') {
                return true;
            }
        }
        return false;
    }

    /**
     * Compute regular/express courier delivery fees (distance-based nationwide for Spare Parts,
     * fixed metro rates for standard workshop services).
     */
    private function resolveCartDeliveryQuote(array $cart, ?RepairShop $shop, array $customerLocation, string $deliveryType = 'REGULAR'): array
    {
        if ($this->cartHasSpareParts($cart) && $shop) {
            $quote = SparePartListing::calculateDeliveryQuote($shop, $customerLocation, $deliveryType);
            return array_merge($quote, ['is_spare_part' => true]);
        }

        $reg = (float) self::REGULAR_DELIVERY_FEE;
        $exp = (float) self::EXPRESS_DELIVERY_FEE;
        return [
            'distance_km' => $shop?->distance_km,
            'distance_formatted' => null,
            'delivery_fee' => strtoupper($deliveryType) === 'EXPRESS' ? $exp : $reg,
            'regular_delivery_fee' => $reg,
            'express_delivery_fee' => $exp,
            'eta_minutes' => $shop?->eta_minutes ?? 120,
            'eta_label' => null,
            'zone_label' => 'Doorstep Courier',
            'is_same_city' => true,
            'is_spare_part' => false,
        ];
    }

    /**
     * Prevent non-customer roles and banned users from ordering repair services.
     * Vendors ARE permitted to purchase Spare Parts from OTHER vendors (`$allowVendorSpareParts = true`),
     * while remaining blocked from ordering repair services or buying from their own shop.
     */
    private function guardCustomerOrdering(Request $request, bool $allowVendorSpareParts = false)
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        if ($user->role === 'vendor' && $allowVendorSpareParts) {
            if ($user->isBanned()) {
                $msg = 'Account Restricted: Your account is restricted from placing new orders.';
                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $msg], 403)
                    : redirect('/vendor/dashboard')->with('error', $msg);
            }
            return null;
        }

        if ($user->role !== 'customer') {
            $vendorMsg = $user->role === 'vendor'
                ? 'Vendor accounts can purchase spare parts from other workshops, but cannot book customer repair services.'
                : 'Only customer accounts can order repair services.';

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $vendorMsg,
                ], 403);
            }

            return match ($user->role) {
                'admin' => redirect('/admin')->with('warning', 'Only customer accounts can place service orders.'),
                'vendor' => redirect('/vendor/dashboard')->with('warning', $vendorMsg),
                'delivery_agent', 'delivery' => redirect('/delivery')->with('warning', 'Only customer accounts can place service orders.'),
                default => redirect('/'),
            };
        }

        if ($user->isBanned()) {
            $msg = 'Account Restricted: Your account is restricted from placing new service orders. You may continue to view your ongoing and past orders.';
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => $msg,
                ], 403);
            }

            return redirect('/requests')->with('error', $msg);
        }

        return null;
    }

    /**
     * Add a Spare Part (`spare_part_id`), Fixed Package (`package_id`), or Shop Service (`service_name` + `shop_id`) to Cart.
     * Route: POST /cart/add
     */
    public function addToCart(Request $request)
    {
        $isSparePartAdd = $request->filled('spare_part_id');
        if ($resp = $this->guardCustomerOrdering($request, $isSparePartAdd)) {
            return $resp;
        }

        if ($isSparePartAdd) {
            return $this->addSparePartToCart($request);
        }

        $validated = $request->validate([
            'package_id' => 'nullable|exists:service_packages,id',
            'shop_id' => 'nullable|exists:repair_shops,id',
            'service_name' => 'required_without:package_id|nullable|string|max:255',
            'description' => 'nullable|string|max:500',
            'price' => 'required_without:package_id|nullable|numeric|min:0',
            'quantity' => 'nullable|integer|min:1|max:10',
            'customer_lat' => 'nullable|numeric|between:-90,90',
            'customer_lng' => 'nullable|numeric|between:-180,180',
            'customer_city' => 'nullable|string|max:100',
            'customer_area' => 'nullable|string|max:120',
        ]);

        $quantity = (int) ($validated['quantity'] ?? 1);
        $explicitShopId = isset($validated['shop_id']) ? (int) $validated['shop_id'] : null;
        $addingPackageId = !empty($validated['package_id']) ? (int) $validated['package_id'] : null;
        $cart = session()->get('cart', []);

        $strictCapability = $this->isStrictPackageCapabilityActive();
        if ($addingPackageId && $strictCapability) {
            if ($explicitShopId) {
                $requestedShop = RepairShop::where('is_active', true)->where('is_public', true)->find($explicitShopId);
                if (!$requestedShop || !$this->shopSupportsPackages($requestedShop, [$addingPackageId])) {
                    $msg = 'This workshop does not offer the selected fixed-price package.';
                    if (!$request->expectsJson()) {
                        return back()->withErrors(['cart' => $msg]);
                    }
                    return response()->json(['success' => false, 'message' => $msg], 422);
                }
            } elseif (empty($cart)) {
                session()->forget(['cart_shop_manual', 'cart_shop_id']);
            }
        }

        $requiredPackageIds = $addingPackageId
            ? array_values(array_unique(array_merge($this->extractRequiredPackageIds($cart), [$addingPackageId])))
            : $this->extractRequiredPackageIds($cart);

        $shop = $this->resolveTargetShop($explicitShopId, $request, $requiredPackageIds);
        if ($addingPackageId && $strictCapability && !$shop) {
            $msg = !empty($cart)
                ? 'Your currently selected workshop does not support all fixed-price packages in your cart together. Complete or clear your existing cart first.'
                : 'No verified workshop in your pickup area currently provides this fixed-price package.';
            if (!$request->expectsJson()) {
                return back()->withErrors(['cart' => $msg]);
            }
            return response()->json(['success' => false, 'message' => $msg], 422);
        }

        // Server-side price authority: only vendor-published fixed-price services can be
        // added, and always at the price the workshop set (never the client-supplied price).
        $publishedService = null;
        if (empty($validated['package_id'])) {
            $publishedService = $shop
                ? ShopService::where('shop_id', $shop->id)
                    ->where('is_active', true)
                    ->whereNotNull('base_price')
                    ->where('base_price', '>', 0)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower(trim((string) $validated['service_name']))])
                    ->first()
                : null;

            if (!$publishedService) {
                $msg = 'This service is not offered at a fixed price by this workshop. Please request a custom quote instead.';
                if (!$request->expectsJson()) {
                    return back()->withErrors(['cart' => $msg]);
                }
                return response()->json(['success' => false, 'message' => $msg], 422);
            }
        }

        // If user explicitly added from a different shop profile, scope cart to that shop
        if ($explicitShopId && session('cart_shop_id') && (int) session('cart_shop_id') !== $explicitShopId && empty($validated['package_id'])) {
            $cart = [];
        }

        if ($shop) {
            session()->put('cart_shop_id', $shop->id);
            $isShopService = !empty($validated['service_name']) && empty($validated['package_id']);
            session()->put('cart_shop_locked', $isShopService);
            if ($explicitShopId !== null) {
                session()->put('cart_shop_manual', true);
            } elseif (!session()->has('cart_shop_manual')) {
                session()->put('cart_shop_manual', false);
            }
        } else {
            session()->forget(['cart_shop_id', 'cart_shop_locked', 'cart_shop_manual']);
        }

        if (!empty($validated['package_id'])) {
            $package = ServicePackage::findOrFail($validated['package_id']);
            $itemKey = $package->id;

            if (isset($cart[$itemKey])) {
                $cart[$itemKey]['quantity'] = min(10, $cart[$itemKey]['quantity'] + $quantity);
            } else {
                $cart[$itemKey] = [
                    'id' => $package->id,
                    'title' => $package->title,
                    'description' => $package->description ?? implode(', ', array_slice($package->inclusions ?? [], 0, 2)),
                    'price' => (float) $package->fixed_price,
                    'category' => $package->device_category,
                    'duration' => $package->estimated_turnaround_hours,
                    'warranty_months' => $package->warranty_months,
                    'quantity' => $quantity,
                    'source' => 'QUICKMATCH_PACKAGE',
                ];
            }
        } else {
            $serviceTitle = $publishedService->name;
            $itemKey = 'svc_' . md5(($shop?->id ?? 0) . '_' . strtolower($serviceTitle));

            if (isset($cart[$itemKey])) {
                $cart[$itemKey]['quantity'] = min(10, $cart[$itemKey]['quantity'] + $quantity);
                $cart[$itemKey]['price'] = (float) $publishedService->base_price;
            } else {
                $cart[$itemKey] = [
                    'id' => $itemKey,
                    'title' => $serviceTitle,
                    'description' => $publishedService->short_description ?: ($publishedService->description ?: 'Fixed-price workshop service'),
                    'price' => (float) $publishedService->base_price,
                    'category' => 'Shop Direct Service',
                    'duration' => $shop ? max(2, (int) round(($shop->eta_minutes ?: 120) / 60)) : 4,
                    'warranty_months' => 3,
                    'quantity' => $quantity,
                    'source' => 'SHOP_SERVICE',
                ];
            }
        }

        session()->put('cart', $cart);
        $this->syncDatabaseCart($request->user(), $shop, $cart);

        $message = $shop
            ? "Service added to cart — {$shop->name}"
            : 'Service added to cart';

        if (!$request->expectsJson()) {
            if ($request->boolean('buy_now')) {
                return redirect()->route('cart.checkout')->with('success', $message);
            }
            return redirect()->route('cart.view')->with('success', $message);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'cart_count' => count($cart),
            'shop_id' => $shop?->id,
            'shop_name' => $shop?->name,
        ]);
    }

    /**
     * Add a Spare Part Marketplace item (with optional Spec Option, Sale Offer & Wholesale Discount) to Cart.
     */
    private function addSparePartToCart(Request $request)
    {
        $validated = $request->validate([
            'spare_part_id' => 'required|integer|exists:spare_part_listings,id',
            'spec_code' => 'nullable|string|max:100',
            'quantity' => 'nullable|integer|min:1|max:10000',
            'include_installation' => 'nullable|boolean',
            'buy_now' => 'nullable|boolean',
            'customer_lat' => 'nullable|numeric|between:-90,90',
            'customer_lng' => 'nullable|numeric|between:-180,180',
            'customer_city' => 'nullable|string|max:100',
            'customer_area' => 'nullable|string|max:120',
        ]);

        /** @var SparePartListing $listing */
        $listing = SparePartListing::with(['shop.vendorProfile', 'vendorProfile'])
            ->where('is_active', true)
            ->findOrFail((int) $validated['spare_part_id']);

        $shop = $listing->shop;
        if (!$shop || !$shop->is_active) {
            $msg = 'This seller workshop is currently unavailable.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->withErrors(['cart' => $msg]);
        }

        // Prevent a vendor from purchasing spare parts from their OWN workshop/branch
        $buyer = $request->user();
        if ($buyer && $buyer->role === 'vendor') {
            $ownedProfileId = VendorProfile::where('user_id', $buyer->id)->value('id');
            if (
                ($ownedProfileId && (int) $shop->vendor_profile_id === (int) $ownedProfileId)
                || ($shop->vendorProfile && (int) $shop->vendorProfile->user_id === (int) $buyer->id)
            ) {
                $msg = 'You cannot order spare parts from your own workshop listing. Please select another competing seller.';
                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $msg], 422)
                    : back()->with('error', $msg);
            }
        }

        // Enforce required specification selection when `has_specs` is true (Etel / LaptopWorld UX)
        $selectedSpec = null;
        $specCode = trim((string) ($validated['spec_code'] ?? ''));
        if ($listing->has_specs) {
            $selectedSpec = $listing->resolveSpecOption($specCode);
            if (!$selectedSpec) {
                $label = $listing->spec_label ?: 'Quality';
                $msg = "Please choose a {$label} option before ordering.";
                return $request->expectsJson()
                    ? response()->json(['success' => false, 'message' => $msg, 'errors' => ['spec_code' => [$msg]]], 422)
                    : back()->withErrors(['spec_code' => $msg])->withInput();
            }
        }

        $maxStock = max(1, (int) ($selectedSpec['stock'] ?? $listing->stock_quantity ?: 1000));
        $requestedQty = max(1, (int) ($validated['quantity'] ?? 1));

        $cart = session()->get('cart', []);

        // Scope cart to this spare-part seller shop (clear if switching sellers or switching from repair services)
        if (
            (session('cart_shop_id') && (int) session('cart_shop_id') !== (int) $shop->id)
            || (!empty($cart) && !$this->cartHasSpareParts($cart))
        ) {
            $cart = [];
        }

        session()->put('cart_shop_id', $shop->id);
        session()->put('cart_shop_locked', true);
        session()->put('cart_shop_manual', true);

        $itemKey = 'sp_' . $listing->id . ($selectedSpec ? ('_' . $selectedSpec['code']) : '');
        $existingQty = isset($cart[$itemKey]) && !$request->boolean('buy_now')
            ? (int) $cart[$itemKey]['quantity']
            : 0;
        $finalQty = min($maxStock, $existingQty + $requestedQty);

        $wantInstallation = $request->boolean('include_installation');
        $supportsInstall = $listing->supportsWorkshopInstallation();
        $maxInstallQty = $listing->maxInstallationQuantity();

        if ($wantInstallation && !$supportsInstall) {
            $msg = 'Workshop installation is not applicable for this tool/consumable item.';
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg], 422)
                : back()->withErrors(['include_installation' => $msg])->withInput();
        }

        if ($wantInstallation && $finalQty > $maxInstallQty) {
            $msg = "Certified workshop installation is capped at {$maxInstallQty} retail units per order. Please uncheck installation for bulk/wholesale quantities ({$finalQty} units).";
            return $request->expectsJson()
                ? response()->json(['success' => false, 'message' => $msg, 'errors' => ['include_installation' => [$msg]]], 422)
                : back()->withErrors(['include_installation' => $msg])->withInput();
        }

        $includeInstallation = $wantInstallation && $supportsInstall && $finalQty <= $maxInstallQty;
        $installFeePerUnit = $includeInstallation ? round((float) $listing->assembly_fee, 2) : 0.0;

        $breakdown = $listing->calculatePricingBreakdown($finalQty, $selectedSpec['code'] ?? null);
        $specSuffix = $selectedSpec ? (' (' . ($listing->spec_label ?: 'Spec') . ': ' . $selectedSpec['name'] . ')') : '';
        $displayTitle = $listing->title . $specSuffix . ($includeInstallation ? ' + Workshop Installation' : '');

        $descParts = [
            $listing->part_category,
            'Seller: ' . $shop->name . ($shop->display_branch_tag ? " [{$shop->display_branch_tag}]" : ''),
        ];
        if ($includeInstallation) {
            $descParts[] = 'Includes Branch Installation (৳' . number_format($installFeePerUnit, 0) . '/unit)';
        }
        if ($breakdown['has_active_offer']) {
            $descParts[] = 'Sale Offer (Normal ৳' . number_format($breakdown['normal_unit_price'], 0) . ')';
        }
        if ($breakdown['is_wholesale_applied']) {
            $descParts[] = 'Wholesale ' . rtrim(rtrim(number_format($breakdown['wholesale_discount_pct'], 2), '0'), '.') . '% off applied';
        }

        $cart[$itemKey] = [
            'id' => $itemKey,
            'spare_part_id' => $listing->id,
            'slug' => $listing->slug,
            'title' => $displayTitle,
            'description' => implode(' • ', $descParts),
            'part_unit_price' => (float) $breakdown['effective_unit_price'],
            'include_installation' => $includeInstallation,
            'installation_fee_per_unit' => $installFeePerUnit,
            'price' => round((float) $breakdown['effective_unit_price'] + $installFeePerUnit, 2),
            'normal_unit_price' => (float) $breakdown['normal_unit_price'],
            'raw_unit_price' => (float) $breakdown['raw_unit_price'],
            'has_active_offer' => (bool) $breakdown['has_active_offer'],
            'wholesale_enabled' => (bool) $listing->wholesale_enabled,
            'wholesale_min_qty' => (int) ($listing->wholesale_min_qty ?: 100),
            'wholesale_discount_pct' => (float) $breakdown['wholesale_discount_pct'],
            'wholesale_savings' => (float) $breakdown['wholesale_savings'],
            'spec_code' => $selectedSpec['code'] ?? null,
            'spec_name' => $selectedSpec['name'] ?? null,
            'image_url' => $listing->image_url,
            'part_category' => $listing->part_category,
            'category' => 'Spare Parts • ' . $listing->part_category,
            'duration' => max(1, (int) ($listing->handling_hours ?: 1)),
            'warranty_months' => max(0, (int) round(($listing->warranty_days ?: 30) / 30)),
            'max_stock' => $maxStock,
            'quantity' => $finalQty,
            'source' => 'SPARE_PART',
        ];

        session()->put('cart', $cart);
        $this->syncDatabaseCart($request->user(), $shop, $cart);

        $wholesaleNote = $breakdown['is_wholesale_applied']
            ? ' (Wholesale ' . rtrim(rtrim(number_format($breakdown['wholesale_discount_pct'], 2), '0'), '.') . '% discount applied!)'
            : '';
        $message = "Added {$finalQty} × {$displayTitle} from {$shop->name}{$wholesaleNote}";

        if (!$request->expectsJson()) {
            if ($request->boolean('buy_now')) {
                return redirect()->route('cart.checkout')->with('success', $message);
            }
            return redirect()->route('cart.view')->with('success', $message);
        }

        return response()->json([
            'success' => true,
            'message' => $message,
            'cart_count' => count($cart),
            'shop_id' => $shop->id,
            'shop_name' => $shop->name,
            'item' => $cart[$itemKey],
        ]);
    }

    /**
     * Switch target shop for Direct-Order Cart (enforces same-city boundary when customer city is set).
     * Route: POST /cart/shop
     */
    public function selectCartShop(Request $request)
    {
        if ($resp = $this->guardCustomerOrdering($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'shop_id' => 'required|exists:repair_shops,id',
        ]);

        $shop = RepairShop::where('is_active', true)
            ->where('is_public', true)
            ->findOrFail($validated['shop_id']);

        $customerLocation = $this->resolveCustomerLocation($request);
        $customerCity = $customerLocation['city'] ?? null;

        if (!empty($customerCity) && !empty($shop->city) && strcasecmp((string) $shop->city, (string) $customerCity) !== 0) {
            return redirect()->route('cart.view')
                ->with('warning', "Please select a workshop within {$customerCity} for doorstep pickup, or update your pickup location first.");
        }

        $cart = session()->get('cart', []);
        $requiredPackageIds = $this->extractRequiredPackageIds($cart);
        if (!empty($requiredPackageIds) && $this->isStrictPackageCapabilityActive() && !$this->shopSupportsPackages($shop, $requiredPackageIds)) {
            return redirect()->route('cart.view')
                ->with('warning', "{$shop->name} does not offer all fixed-price packages currently in your cart.");
        }

        session()->put('cart_shop_id', $shop->id);
        session()->put('cart_shop_manual', true);
        $this->syncDatabaseCart($request->user(), $shop, $cart);

        $locationTag = $shop->display_branch_tag
            ? " [{$shop->display_branch_tag}]"
            : ($shop->area ? " ({$shop->area})" : '');

        return redirect()->route('cart.view')->with('success', "Workshop updated to {$shop->name}{$locationTag}.");
    }

    /**
     * View single-page Direct-Order Cart Wizard.
     * Route: GET /cart
     */
    public function viewCart(Request $request)
    {
        $cart = session()->get('cart', []);
        $isSparePartCart = $this->cartHasSpareParts($cart);
        // Allow vendors into /cart when ordering spare parts or viewing an empty spare-parts cart
        if ($resp = $this->guardCustomerOrdering($request, $isSparePartCart || empty($cart))) {
            return $resp;
        }

        $total = array_reduce($cart, function ($sum, $item) {
            return $sum + ((float) $item['price'] * (int) $item['quantity']);
        }, 0);

        $customerLocation = $this->resolveCustomerLocation($request);
        $requiredPackageIds = $this->extractRequiredPackageIds($cart);
        $activeShop = !empty($cart) ? $this->resolveTargetShop(null, $request, $requiredPackageIds) : null;
        $commissionRatePct = self::resolveCommissionRatePct($activeShop, $customerLocation, $cart);
        $serviceCharge = self::calculateServiceCharge($total, $activeShop, $customerLocation, $cart);
        $shopLocked = (bool) session('cart_shop_locked', false);
        $shopManuallySelected = (bool) session('cart_shop_manual', false);
        $verifiedShops = $this->getLocalSelectableShops($customerLocation, $activeShop, $requiredPackageIds);
        $deliveryQuote = $this->resolveCartDeliveryQuote($cart, $activeShop, $customerLocation, 'REGULAR');
        $regularDeliveryFee = (float) $deliveryQuote['regular_delivery_fee'];
        $expressDeliveryFee = (float) $deliveryQuote['express_delivery_fee'];

        $voucherEval = self::evaluateVoucher(
            session('cart_voucher_code'),
            (float) $total,
            $regularDeliveryFee,
            $activeShop,
            $request->user(),
            $commissionRatePct
        );
        $appliedVoucher = $voucherEval['valid'] ? $voucherEval['code'] : null;
        $discountAmount = $voucherEval['valid'] ? (float) $voucherEval['discount'] : 0.0;
        $voucherLabel = $voucherEval['valid'] ? $voucherEval['label'] : null;
        $firstShopPromoEligible = MarketplacePricingService::isEligibleForFirstShopOrderPromo($request->user(), $activeShop);

        return view('packages.cart', compact(
            'cart',
            'total',
            'serviceCharge',
            'commissionRatePct',
            'activeShop',
            'shopLocked',
            'shopManuallySelected',
            'verifiedShops',
            'customerLocation',
            'appliedVoucher',
            'discountAmount',
            'voucherLabel',
            'firstShopPromoEligible',
            'isSparePartCart',
            'deliveryQuote',
            'regularDeliveryFee',
            'expressDeliveryFee'
        ));
    }

    /**
     * Remove item from cart.
     * Route: DELETE /cart/remove/{packageId}
     */
    public function removeFromCart(Request $request, $packageId)
    {
        $packageId = rawurldecode((string) $packageId);
        $cart = session()->get('cart', []);
        if ($resp = $this->guardCustomerOrdering($request, $this->cartHasSpareParts($cart))) {
            return $resp;
        }

        if (isset($cart[$packageId])) {
            unset($cart[$packageId]);
            session()->put('cart', $cart);
        }

        if (empty($cart)) {
            session()->forget(['cart_shop_id', 'cart_shop_locked', 'cart_shop_manual']);
        }

        $targetShop = empty($cart) ? null : $this->resolveTargetShop(null, $request);
        $this->syncDatabaseCart($request->user(), $targetShop, $cart);

        if (!$request->expectsJson()) {
            return redirect()->route('cart.view')->with('success', 'Service removed from cart.');
        }

        $subtotal = array_reduce($cart, fn ($sum, $item) => $sum + ((float) $item['price'] * (int) $item['quantity']), 0.0);
        $customerLocation = $this->resolveCustomerLocation($request);

        return response()->json([
            'success' => true,
            'message' => 'Service removed from cart',
            'cart_count' => count($cart),
            'subtotal' => $subtotal,
            'commission_rate_pct' => self::resolveCommissionRatePct($targetShop, $customerLocation, $cart),
            'service_charge' => self::calculateServiceCharge($subtotal, $targetShop, $customerLocation, $cart),
        ]);
    }

    /**
     * Update cart item quantity.
     * Route: PATCH /cart/update/{packageId}
     */
    public function updateCartQuantity(Request $request, $packageId)
    {
        $packageId = rawurldecode((string) $packageId);
        $cart = session()->get('cart', []);
        $isSparePartCart = $this->cartHasSpareParts($cart);
        if ($resp = $this->guardCustomerOrdering($request, $isSparePartCart)) {
            return $resp;
        }

        $isSpareItem = isset($cart[$packageId]) && (($cart[$packageId]['source'] ?? '') === 'SPARE_PART');
        $maxAllowed = $isSpareItem ? max(1, (int) ($cart[$packageId]['max_stock'] ?? 10000)) : 10;

        $validated = $request->validate([
            'quantity' => "required|integer|min:1|max:{$maxAllowed}",
        ]);

        if (isset($cart[$packageId])) {
            $newQty = (int) $validated['quantity'];
            $cart[$packageId]['quantity'] = $newQty;

            // Recalculate AliExpress progressive wholesale & active offer price for Spare Parts
            if ($isSpareItem && !empty($cart[$packageId]['spare_part_id'])) {
                $spListing = SparePartListing::with('shop')->find((int) $cart[$packageId]['spare_part_id']);
                if ($spListing) {
                    $breakdown = $spListing->calculatePricingBreakdown($newQty, $cart[$packageId]['spec_code'] ?? null);
                    $hadInstall = !empty($cart[$packageId]['include_installation']) && $spListing->supportsWorkshopInstallation();
                    $keepInstall = $hadInstall && $newQty <= $spListing->maxInstallationQuantity();
                    $installFeePerUnit = $keepInstall ? round((float) $spListing->assembly_fee, 2) : 0.0;

                    $cart[$packageId]['part_unit_price'] = (float) $breakdown['effective_unit_price'];
                    $cart[$packageId]['include_installation'] = $keepInstall;
                    $cart[$packageId]['installation_fee_per_unit'] = $installFeePerUnit;
                    $cart[$packageId]['price'] = round((float) $breakdown['effective_unit_price'] + $installFeePerUnit, 2);
                    $cart[$packageId]['normal_unit_price'] = (float) $breakdown['normal_unit_price'];
                    $cart[$packageId]['raw_unit_price'] = (float) $breakdown['raw_unit_price'];
                    $cart[$packageId]['has_active_offer'] = (bool) $breakdown['has_active_offer'];
                    $cart[$packageId]['wholesale_discount_pct'] = (float) $breakdown['wholesale_discount_pct'];
                    $cart[$packageId]['wholesale_savings'] = (float) $breakdown['wholesale_savings'];

                    if ($hadInstall && !$keepInstall) {
                        $cart[$packageId]['title'] = str_replace(' + Workshop Installation', '', (string) $cart[$packageId]['title']);
                    }
                }
            }

            session()->put('cart', $cart);
            $this->syncDatabaseCart($request->user(), $this->resolveTargetShop(null, $request), $cart);
        }

        if (!$request->expectsJson()) {
            return redirect()->route('cart.view')->with('success', 'Cart quantity updated.');
        }

        $subtotal = array_reduce($cart, fn ($sum, $item) => $sum + ((float) $item['price'] * (int) $item['quantity']), 0.0);
        $activeShop = $this->resolveTargetShop(null, $request);
        $customerLocation = $this->resolveCustomerLocation($request);

        return response()->json([
            'success' => true,
            'message' => 'Cart quantity updated',
            'cart_count' => count($cart),
            'subtotal' => $subtotal,
            'commission_rate_pct' => self::resolveCommissionRatePct($activeShop, $customerLocation, $cart),
            'service_charge' => self::calculateServiceCharge($subtotal, $activeShop, $customerLocation, $cart),
            'item' => $cart[$packageId] ?? null,
        ]);
    }

    /**
     * Clear entire cart.
     * Route: POST /cart/clear
     */
    public function clearCart(Request $request)
    {
        $wasSparePartCart = $this->cartHasSpareParts(session()->get('cart', []));
        if ($resp = $this->guardCustomerOrdering($request, $wasSparePartCart)) {
            return $resp;
        }

        session()->forget(['cart', 'cart_shop_id', 'cart_shop_locked', 'cart_shop_manual', 'cart_voucher_code']);
        $this->syncDatabaseCart($request->user(), null, []);

        if ($wasSparePartCart) {
            return redirect()->route('spare-parts.index')->with('success', 'Your spare parts cart has been cleared.');
        }

        return redirect()->route('packages.catalog')->with('success', 'Your cart has been cleared.');
    }

    /**
     * Proceed to checkout screen (kept compatible with Feature R8 routes & tests).
     * Route: GET /cart/checkout
     */
    public function checkout(Request $request)
    {
        $cart = session()->get('cart', []);
        $isSparePartCart = $this->cartHasSpareParts($cart);
        if ($resp = $this->guardCustomerOrdering($request, $isSparePartCart)) {
            return $resp;
        }

        if (empty($cart)) {
            return redirect()->route('packages.catalog')
                ->with('error', 'Your cart is empty');
        }

        $total = array_reduce($cart, function ($sum, $item) {
            return $sum + ((float) $item['price'] * (int) $item['quantity']);
        }, 0);

        $customerLocation = $this->resolveCustomerLocation($request);
        $requiredPackageIds = $this->extractRequiredPackageIds($cart);
        $activeShop = $this->resolveTargetShop(null, $request, $requiredPackageIds);
        $commissionRatePct = self::resolveCommissionRatePct($activeShop, $customerLocation, $cart);
        $serviceCharge = self::calculateServiceCharge($total, $activeShop, $customerLocation, $cart);
        $shopLocked = (bool) session('cart_shop_locked', false);
        $verifiedShops = $this->getLocalSelectableShops($customerLocation, $activeShop, $requiredPackageIds);
        $deliveryQuote = $this->resolveCartDeliveryQuote($cart, $activeShop, $customerLocation, 'REGULAR');
        $regularDeliveryFee = (float) $deliveryQuote['regular_delivery_fee'];
        $expressDeliveryFee = (float) $deliveryQuote['express_delivery_fee'];

        $voucherEval = self::evaluateVoucher(
            session('cart_voucher_code'),
            (float) $total,
            $regularDeliveryFee,
            $activeShop,
            $request->user(),
            $commissionRatePct
        );
        $appliedVoucher = $voucherEval['valid'] ? $voucherEval['code'] : null;
        $discountAmount = $voucherEval['valid'] ? (float) $voucherEval['discount'] : 0.0;
        $voucherLabel = $voucherEval['valid'] ? $voucherEval['label'] : null;

        return view('packages.checkout', compact(
            'cart',
            'total',
            'serviceCharge',
            'commissionRatePct',
            'activeShop',
            'shopLocked',
            'verifiedShops',
            'customerLocation',
            'appliedVoucher',
            'discountAmount',
            'voucherLabel',
            'isSparePartCart',
            'deliveryQuote',
            'regularDeliveryFee',
            'expressDeliveryFee'
        ));
    }

    /**
     * Place Direct Order from Cart (Unified Direct-Order Architecture in Laravel MVC):
     * Creates RepairRequest (`status = PENDING`, `source = DIRECT_SERVICE`) + pre-linked
     * RepairJob (`status = CREATED`) + PaymentTransaction, routing into Vendor "Direct Orders".
     * Route: POST /cart/checkout/place
     */
    public function placeDirectOrder(Request $request)
    {
        $cart = session()->get('cart', []);
        $isSparePartCart = $this->cartHasSpareParts($cart);
        if ($resp = $this->guardCustomerOrdering($request, $isSparePartCart)) {
            return $resp;
        }

        if (empty($cart)) {
            return redirect()->route('packages.catalog')->with('error', 'Your cart is empty');
        }

        $validated = $request->validate([
            'shop_id' => 'nullable|exists:repair_shops,id',
            'schedule_type' => 'required|in:NOW,LATER',
            'scheduled_at' => 'nullable|date',
            'delivery_type' => 'required|in:REGULAR,EXPRESS',
            'payment_method' => 'required|in:CASH,SSLCOMMERZ,BKASH,NAGAD,CARD',
            'voucher_code' => 'nullable|string|max:40',
            'area' => 'nullable|string|max:100',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:30',
            'problem_note' => 'nullable|string|max:1000',
        ]);

        if ($validated['schedule_type'] === 'LATER' && empty($validated['scheduled_at'])) {
            return back()->withErrors(['scheduled_at' => 'Please choose a pickup date and time for scheduled bookings.'])->withInput();
        }

        $cleanPhone = InputSanitizer::sanitizePhone($validated['phone']);
        if (!InputSanitizer::isValidBdPhone($cleanPhone)) {
            throw ValidationException::withMessages([
                'phone' => 'Please enter a valid Bangladeshi mobile number (e.g. 01712345678).',
            ]);
        }

        $customerLocation = $this->resolveCustomerLocation($request);
        if (!empty($validated['area'])) {
            $customerLocation['area'] = trim((string) $validated['area']);
        }
        $requiredPackageIds = $this->extractRequiredPackageIds($cart);
        $shop = $this->resolveTargetShop(isset($validated['shop_id']) ? (int) $validated['shop_id'] : null, $request, $requiredPackageIds);
        if (!$shop) {
            $cityLabel = $customerLocation['city'] ?? 'your selected area';
            $errMsg = !empty($requiredPackageIds) && $this->isStrictPackageCapabilityActive()
                ? "No verified workshop in {$cityLabel} provides all selected fixed-price packages. Please choose an eligible workshop or adjust your cart."
                : "No verified workshop is available within {$cityLabel} for doorstep dispatch. Please update your pickup location or select an available workshop.";
            return back()->with('error', $errMsg);
        }
        $shop->loadMissing('vendorProfile');

        // Re-verify that a vendor buyer cannot place a Spare Parts order against their own workshop
        if ($request->user() && $request->user()->role === 'vendor') {
            $ownedProfileId = VendorProfile::where('user_id', $request->user()->id)->value('id');
            if (
                ($ownedProfileId && (int) $shop->vendor_profile_id === (int) $ownedProfileId)
                || ($shop->vendorProfile && (int) $shop->vendorProfile->user_id === (int) $request->user()->id)
            ) {
                return back()->with('error', 'You cannot place a spare parts order with your own workshop.');
            }
        }

        $user = $request->user() ?? User::where('role', 'customer')->firstOrFail();
        if ($request->user()) {
            $syncProfile = [];
            if (empty($request->user()->phone) && $cleanPhone !== null) {
                $syncProfile['phone'] = $cleanPhone;
            }
            if (empty($request->user()->address) && !empty($validated['address'])) {
                $syncProfile['address'] = trim($validated['address']);
                if (!empty($validated['area'])) {
                    $syncProfile['area'] = trim($validated['area']);
                }
            }
            if (!empty($syncProfile)) {
                $request->user()->update($syncProfile);
            }
        }

        // Recompute authoritative server-side pricing for Spare Parts (active offers + wholesale discounts)
        if ($isSparePartCart) {
            foreach ($cart as $cKey => &$cItem) {
                if (($cItem['source'] ?? '') === 'SPARE_PART' && !empty($cItem['spare_part_id'])) {
                    $spListing = SparePartListing::find((int) $cItem['spare_part_id']);
                    if ($spListing) {
                        $b = $spListing->calculatePricingBreakdown((int) $cItem['quantity'], $cItem['spec_code'] ?? null);
                        $cItem['price'] = (float) $b['effective_unit_price'];
                        $cItem['wholesale_discount_pct'] = (float) $b['wholesale_discount_pct'];
                        $cItem['wholesale_savings'] = (float) $b['wholesale_savings'];
                    }
                }
            }
            unset($cItem);
        }

        $subtotal = array_reduce($cart, fn ($sum, $item) => $sum + ((float) $item['price'] * (int) $item['quantity']), 0);
        $commissionRatePct = self::resolveCommissionRatePct($shop, $customerLocation, $cart);
        $serviceCharge = self::calculateServiceCharge($subtotal, $shop, $customerLocation, $cart);
        $deliveryQuote = $this->resolveCartDeliveryQuote($cart, $shop, $customerLocation, $validated['delivery_type']);
        $deliveryFee = (float) $deliveryQuote['delivery_fee'];

        // Attach computed delivery fee onto quote items so online checkout & PDF invoices reflect exact courier fee
        foreach ($cart as &$qItem) {
            $qItem['delivery_fee'] = $deliveryFee;
        }
        unset($qItem);

        $requestedVoucher = $validated['voucher_code'] ?? session('cart_voucher_code');
        $voucherEval = self::evaluateVoucher(
            $requestedVoucher,
            (float) $subtotal,
            (float) $deliveryFee,
            $shop,
            $request->user(),
            $commissionRatePct
        );
        $appliedVoucherCode = $voucherEval['valid'] ? $voucherEval['code'] : null;
        $discountAmount = $voucherEval['valid'] ? (float) $voucherEval['discount'] : 0.0;
        $totalAmount = max(0.0, ($subtotal + $serviceCharge + $deliveryFee) - $discountAmount);
        $scheduledPickupAt = ($validated['schedule_type'] === 'LATER' && !empty($validated['scheduled_at']))
            ? \Carbon\Carbon::parse($validated['scheduled_at'])
            : null;

        $serviceLines = collect($cart)->values()->map(function ($item, $idx) {
            return ($idx + 1) . '. ' . $item['title'] . ' × ' . $item['quantity'] . ' — ৳' . number_format($item['price'] * $item['quantity'], 2);
        })->implode("\n");

        $firstItem = reset($cart);
        $title = count($cart) === 1
            ? $firstItem['title']
            : count($cart) . ' Direct Services • ' . $shop->name;

        $scheduleLabel = $validated['schedule_type'] === 'NOW'
            ? 'Immediate Courier Pickup (NOW)'
            : 'Scheduled Pickup (' . $validated['scheduled_at'] . ')';

        $customerCity = $customerLocation['city'] ?? ($shop->city ?: 'Dhaka');
        $fullAddress = trim($validated['address']);
        if (!empty($validated['area']) && !str_contains(strtolower($fullAddress), strtolower($validated['area']))) {
            $fullAddress .= ', ' . $validated['area'];
            if (!str_contains(strtolower($fullAddress), strtolower($customerCity))) {
                $fullAddress .= ', ' . $customerCity;
            }
        }

        $repairRequest = DB::transaction(function () use (
            $user, $shop, $cart, $firstItem, $validated, $cleanPhone, $title, $serviceLines, $scheduleLabel,
            $subtotal, $commissionRatePct, $serviceCharge, $deliveryFee, $totalAmount, $fullAddress, $customerCity,
            $appliedVoucherCode, $discountAmount, $scheduledPickupAt
        ) {
            $requestedServiceId = is_numeric($firstItem['id'] ?? null) ? (int) $firstItem['id'] : null;
            $discountLine = $discountAmount > 0 ? " | Discount ({$appliedVoucherCode}): -৳" . number_format($discountAmount, 2) : '';

            $req = RepairRequest::create([
                'customer_id' => $user->id,
                'source' => 'DIRECT_SERVICE',
                'requested_shop_id' => $shop->id,
                'requested_service_id' => $requestedServiceId,
                'title' => $title,
                'description' => "Direct Service Booking ({$shop->name})\n"
                    . "Schedule: {$scheduleLabel}\n"
                    . "Platform Pickup & Delivery Logistics: {$validated['delivery_type']} (৳{$deliveryFee})\n"
                    . "Payment: {$validated['payment_method']}\n"
                    . "Order Subtotal: ৳" . number_format($subtotal, 2) . " | Service & Protection Fee: ৳" . number_format($serviceCharge, 2) . $discountLine . " | Total: ৳" . number_format($totalAmount, 2) . "\n\n"
                    . "Selected Services:\n{$serviceLines}",
                'problem' => !empty($validated['problem_note'])
                    ? trim($validated['problem_note']) . "\n\nBooked Services:\n" . $serviceLines
                    : "Booked Direct Services:\n" . $serviceLines,
                'device_type' => $firstItem['category'] ?? 'Service Order',
                'brand' => $shop->name,
                'model_name' => 'Direct Service Booking',
                'issue_category' => 'Direct Order',
                'mode' => 'DIRECT_REPAIR',
                // Starts as PENDING in Vendor Dashboard "Direct Orders" queue
                'status' => RepairRequest::STATUS_PENDING,
                'preferred_pickup' => true,
                'preferred_service_type' => 'pickup_dropoff',
                'delivery_type' => $validated['delivery_type'],
                'contact_phone' => $cleanPhone ?? $validated['phone'],
                'pickup_address' => $fullAddress,
                'dropoff_address' => $shop->public_address ?: ($shop->address ?? trim(implode(', ', array_filter([$shop->area, $shop->city ?: $customerCity])))),
                'checkup_fee' => $serviceCharge,
                'commission_amount' => $serviceCharge,
                'quoted_final_amount' => $totalAmount,
                'voucher_code' => $appliedVoucherCode,
                'discount_amount' => $discountAmount,
                'scheduled_pickup_at' => $scheduledPickupAt,
            ]);

            RepairJob::create([
                'repair_request_id' => $req->id,
                'shop_id' => $shop->id,
                'status' => 'CREATED',
                'final_quoted_amount' => $totalAmount,
                'final_quote_items' => array_values($cart),
            ]);

            PaymentTransaction::create([
                'user_id' => $user->id,
                'repair_request_id' => $req->id,
                'amount' => $totalAmount,
                'currency' => 'BDT',
                'payment_method' => $validated['payment_method'],
                'status' => 'PENDING',
                'transaction_reference' => 'MRM-DO-' . strtoupper(Str::random(8)),
            ]);

            Cart::where('user_id', $user->id)
                ->where('status', 'ACTIVE')
                ->update(['status' => 'CHECKED_OUT']);

            foreach ($cart as $key => $item) {
                if (is_numeric($key)) {
                    ServicePackage::where('id', (int) $key)->increment('order_count', (int) $item['quantity']);
                } elseif (($item['source'] ?? '') === 'SPARE_PART' && !empty($item['spare_part_id'])) {
                    $qtyBought = max(1, (int) $item['quantity']);
                    SparePartListing::where('id', (int) $item['spare_part_id'])->update([
                        'stock_quantity' => DB::raw("GREATEST(0, stock_quantity - {$qtyBought})"),
                        'sold_count' => DB::raw("sold_count + {$qtyBought}"),
                    ]);
                }
            }

            if ($shop->vendorProfile?->user_id && Schema::hasTable('user_notifications')) {
                UserNotification::create([
                    'user_id' => $shop->vendorProfile->user_id,
                    'type' => 'direct_order_new',
                    'severity' => 'info',
                    'title' => "New Direct Order #{$req->id}",
                    'message' => "{$user->name} booked {$title} (৳" . number_format($totalAmount, 0) . '). Review and confirm pickup dispatch.',
                    'action_url' => '/vendor/dashboard?tab=direct_orders',
                    'is_read' => false,
                ]);
            }

            return $req;
        });

        session()->forget(['cart', 'cart_shop_id', 'cart_shop_locked', 'cart_shop_manual', 'cart_voucher_code']);

        if ($validated['payment_method'] !== 'CASH') {
            session()->flash('preselected_payment_method', $validated['payment_method']);
            return redirect("/checkout/payment/{$repairRequest->id}")
                ->with('success', "Booking #{$repairRequest->id} created with {$shop->name}. Complete escrow authorization below.");
        }

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('success', "Booking #{$repairRequest->id} sent to {$shop->name} and awaiting confirmation.");
    }
}

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
use App\Models\User;
use App\Models\UserNotification;
use App\Services\GeolocationService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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

    public static function calculateServiceCharge(float $subtotal): float
    {
        if ($subtotal <= 0) {
            return 0;
        }
        return (float) max(self::MIN_SERVICE_CHARGE, round($subtotal * self::SERVICE_CHARGE_RATE));
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
        if (!session('cart_shop_locked')) {
            session()->forget(['cart_shop_manual', 'cart_shop_id']);
            $matched = $this->quickMatchWorkshop($location);
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
    private function collectLocalEligibleShops(array $customerLocation, float $maxRadiusKm = self::MAX_QUICKMATCH_RADIUS_KM): Collection
    {
        $city = strtolower(trim((string) ($customerLocation['city'] ?? 'Dhaka')));
        $hasCoords = !empty($customerLocation['has_coordinates'])
            && $customerLocation['lat'] !== null
            && $customerLocation['lng'] !== null;

        $candidates = RepairShop::query()
            ->where('is_active', true)
            ->where('is_public', true)
            ->where('setup_complete', true)
            ->where('accepts_direct_orders', true)
            ->whereRaw('LOWER(TRIM(city)) = ?', [$city])
            ->get();

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
    public function quickMatchWorkshop(array $customerLocation): ?RepairShop
    {
        $eligible = $this->collectLocalEligibleShops($customerLocation, self::MAX_QUICKMATCH_RADIUS_KM);
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
    private function getLocalSelectableShops(array $customerLocation, ?RepairShop $activeShop = null): Collection
    {
        $hasCoords = !empty($customerLocation['has_coordinates'])
            && $customerLocation['lat'] !== null
            && $customerLocation['lng'] !== null;

        $shops = $this->collectLocalEligibleShops($customerLocation, 25.0)->all();

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

        $collection = collect(array_slice($shops, 0, 25));

        if ($activeShop && !$collection->contains('id', $activeShop->id)) {
            $collection->prepend($activeShop);
        }

        return $collection;
    }

    /**
     * Resolve target workshop for Direct Order (explicit/locked shop or Smart Quick-Match).
     */
    private function resolveTargetShop(?int $requestedShopId = null, ?Request $request = null): ?RepairShop
    {
        $customerLocation = $this->resolveCustomerLocation($request);

        if ($requestedShopId) {
            $shop = RepairShop::where('is_active', true)->where('is_public', true)->find($requestedShopId);
            if ($shop) {
                return $this->attachDistanceToShop($shop, $customerLocation);
            }
        }

        $sessionShopId = session('cart_shop_id');
        if ($sessionShopId) {
            $shop = RepairShop::where('is_active', true)->where('is_public', true)->find($sessionShopId);
            if ($shop) {
                if (session('cart_shop_locked')) {
                    return $this->attachDistanceToShop($shop, $customerLocation);
                }

                if (session('cart_shop_manual')) {
                    if (strcasecmp((string) $shop->city, (string) ($customerLocation['city'] ?? 'Dhaka')) === 0) {
                        return $this->attachDistanceToShop($shop, $customerLocation);
                    }
                    session()->forget(['cart_shop_manual', 'cart_shop_id']);
                } else {
                    $matched = $this->quickMatchWorkshop($customerLocation);
                    if ($matched) {
                        session()->put('cart_shop_id', $matched->id);
                    } else {
                        session()->forget('cart_shop_id');
                    }
                    return $matched;
                }
            }
        }

        $matched = $this->quickMatchWorkshop($customerLocation);
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

        if ($request->filled('q')) {
            $searchTerm = trim($request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('title', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('device_category', 'ILIKE', "%{$searchTerm}%")
                  ->orWhereRaw('CAST(inclusions AS TEXT) ILIKE ?', ["%{$searchTerm}%"]);
            });
        }

        $sort = $request->input('sort', 'popular');
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
        $activeShop = session('cart_shop_id') ? $this->resolveTargetShop(null, $request) : null;
        $verifiedShops = $this->getLocalSelectableShops($customerLocation, $activeShop);
        $cartCount = count(session()->get('cart', []));

        return view('packages.catalog', compact('packages', 'categories', 'verifiedShops', 'cartCount', 'customerLocation'));
    }

    /**
     * Prevent non-customer roles and banned customers from using the Direct-Order Cart or placing orders.
     */
    private function guardCustomerOrdering(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return null;
        }

        if ($user->role !== 'customer') {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Only customer accounts can order repair services.',
                ], 403);
            }

            return match ($user->role) {
                'admin' => redirect('/admin')->with('warning', 'Only customer accounts can place service orders.'),
                'vendor' => redirect('/vendor/dashboard')->with('warning', 'Only customer accounts can place service orders.'),
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
     * Add either a Fixed Package (`package_id`) or a Shop Service (`service_name` + `shop_id`) to Cart.
     * Route: POST /cart/add
     */
    public function addToCart(Request $request)
    {
        if ($resp = $this->guardCustomerOrdering($request)) {
            return $resp;
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
        $shop = $this->resolveTargetShop($explicitShopId, $request);
        $cart = session()->get('cart', []);

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

        session()->put('cart_shop_id', $shop->id);
        session()->put('cart_shop_manual', true);
        $this->syncDatabaseCart($request->user(), $shop, session()->get('cart', []));

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
        if ($resp = $this->guardCustomerOrdering($request)) {
            return $resp;
        }

        $cart = session()->get('cart', []);
        $total = array_reduce($cart, function ($sum, $item) {
            return $sum + ((float) $item['price'] * (int) $item['quantity']);
        }, 0);

        $serviceCharge = self::calculateServiceCharge($total);
        $customerLocation = $this->resolveCustomerLocation($request);
        $activeShop = !empty($cart) ? $this->resolveTargetShop(null, $request) : null;
        $shopLocked = (bool) session('cart_shop_locked', false);
        $shopManuallySelected = (bool) session('cart_shop_manual', false);
        $verifiedShops = $this->getLocalSelectableShops($customerLocation, $activeShop);

        return view('packages.cart', compact(
            'cart',
            'total',
            'serviceCharge',
            'activeShop',
            'shopLocked',
            'shopManuallySelected',
            'verifiedShops',
            'customerLocation'
        ));
    }

    /**
     * Remove item from cart.
     * Route: DELETE /cart/remove/{packageId}
     */
    public function removeFromCart(Request $request, $packageId)
    {
        if ($resp = $this->guardCustomerOrdering($request)) {
            return $resp;
        }

        $cart = session()->get('cart', []);

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

        return response()->json([
            'success' => true,
            'message' => 'Service removed from cart',
            'cart_count' => count($cart),
            'subtotal' => $subtotal,
            'service_charge' => self::calculateServiceCharge($subtotal),
        ]);
    }

    /**
     * Update cart item quantity.
     * Route: PATCH /cart/update/{packageId}
     */
    public function updateCartQuantity(Request $request, $packageId)
    {
        if ($resp = $this->guardCustomerOrdering($request)) {
            return $resp;
        }

        $validated = $request->validate([
            'quantity' => 'required|integer|min:1|max:10',
        ]);

        $cart = session()->get('cart', []);

        if (isset($cart[$packageId])) {
            $cart[$packageId]['quantity'] = (int) $validated['quantity'];
            session()->put('cart', $cart);
            $this->syncDatabaseCart($request->user(), $this->resolveTargetShop(null, $request), $cart);
        }

        if (!$request->expectsJson()) {
            return redirect()->route('cart.view')->with('success', 'Cart quantity updated.');
        }

        $subtotal = array_reduce($cart, fn ($sum, $item) => $sum + ((float) $item['price'] * (int) $item['quantity']), 0.0);

        return response()->json([
            'success' => true,
            'message' => 'Cart quantity updated',
            'cart_count' => count($cart),
            'subtotal' => $subtotal,
            'service_charge' => self::calculateServiceCharge($subtotal),
        ]);
    }

    /**
     * Clear entire cart.
     * Route: POST /cart/clear
     */
    public function clearCart(Request $request)
    {
        if ($resp = $this->guardCustomerOrdering($request)) {
            return $resp;
        }

        session()->forget(['cart', 'cart_shop_id', 'cart_shop_locked', 'cart_shop_manual']);
        $this->syncDatabaseCart($request->user(), null, []);

        return redirect()->route('packages.catalog')->with('success', 'Your cart has been cleared.');
    }

    /**
     * Proceed to checkout screen (kept compatible with Feature R8 routes & tests).
     * Route: GET /cart/checkout
     */
    public function checkout(Request $request)
    {
        if ($resp = $this->guardCustomerOrdering($request)) {
            return $resp;
        }

        $cart = session()->get('cart', []);

        if (empty($cart)) {
            return redirect()->route('packages.catalog')
                ->with('error', 'Your cart is empty');
        }

        $total = array_reduce($cart, function ($sum, $item) {
            return $sum + ((float) $item['price'] * (int) $item['quantity']);
        }, 0);

        $serviceCharge = self::calculateServiceCharge($total);
        $customerLocation = $this->resolveCustomerLocation($request);
        $activeShop = $this->resolveTargetShop(null, $request);
        $verifiedShops = $this->getLocalSelectableShops($customerLocation, $activeShop);

        return view('packages.checkout', compact('cart', 'total', 'serviceCharge', 'activeShop', 'verifiedShops', 'customerLocation'));
    }

    /**
     * Place Direct Order from Cart (Unified Direct-Order Architecture in Laravel MVC):
     * Creates RepairRequest (`status = PENDING`, `source = DIRECT_SERVICE`) + pre-linked
     * RepairJob (`status = CREATED`) + PaymentTransaction, routing into Vendor "Direct Orders".
     * Route: POST /cart/checkout/place
     */
    public function placeDirectOrder(Request $request)
    {
        if ($resp = $this->guardCustomerOrdering($request)) {
            return $resp;
        }

        $cart = session()->get('cart', []);
        if (empty($cart)) {
            return redirect()->route('packages.catalog')->with('error', 'Your cart is empty');
        }

        $validated = $request->validate([
            'shop_id' => 'nullable|exists:repair_shops,id',
            'schedule_type' => 'required|in:NOW,LATER',
            'scheduled_at' => 'nullable|date',
            'delivery_type' => 'required|in:REGULAR,EXPRESS',
            'payment_method' => 'required|in:CASH,SSLCOMMERZ',
            'area' => 'nullable|string|max:100',
            'address' => 'required|string|max:500',
            'phone' => 'required|string|max:30',
            'problem_note' => 'nullable|string|max:1000',
        ]);

        if ($validated['schedule_type'] === 'LATER' && empty($validated['scheduled_at'])) {
            return back()->withErrors(['scheduled_at' => 'Please choose a pickup date and time for scheduled bookings.'])->withInput();
        }

        $customerLocation = $this->resolveCustomerLocation($request);
        $shop = $this->resolveTargetShop(isset($validated['shop_id']) ? (int) $validated['shop_id'] : null, $request);
        if (!$shop) {
            $cityLabel = $customerLocation['city'] ?? 'your selected area';
            return back()->with('error', "No verified workshop is available within {$cityLabel} for doorstep dispatch. Please update your pickup location or select an available workshop.");
        }
        $shop->loadMissing('vendorProfile');

        $user = $request->user() ?? User::where('role', 'customer')->firstOrFail();
        if ($request->user()) {
            $syncProfile = [];
            if (empty($request->user()->phone) && !empty($validated['phone'])) {
                $syncProfile['phone'] = trim($validated['phone']);
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
        $subtotal = array_reduce($cart, fn ($sum, $item) => $sum + ((float) $item['price'] * (int) $item['quantity']), 0);
        $serviceCharge = self::calculateServiceCharge($subtotal);
        $deliveryFee = $validated['delivery_type'] === 'EXPRESS' ? self::EXPRESS_DELIVERY_FEE : self::REGULAR_DELIVERY_FEE;
        $totalAmount = $subtotal + $serviceCharge + $deliveryFee;

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
            $user, $shop, $cart, $firstItem, $validated, $title, $serviceLines, $scheduleLabel,
            $subtotal, $serviceCharge, $deliveryFee, $totalAmount, $fullAddress, $customerCity
        ) {
            $requestedServiceId = is_numeric($firstItem['id'] ?? null) ? (int) $firstItem['id'] : null;

            $req = RepairRequest::create([
                'customer_id' => $user->id,
                'source' => 'DIRECT_SERVICE',
                'requested_shop_id' => $shop->id,
                'requested_service_id' => $requestedServiceId,
                'title' => $title,
                'description' => "Direct Service Booking ({$shop->name})\n"
                    . "Schedule: {$scheduleLabel}\n"
                    . "Logistics: {$validated['delivery_type']} (৳{$deliveryFee})\n"
                    . "Payment: {$validated['payment_method']}\n"
                    . "Subtotal: ৳" . number_format($subtotal, 2) . " | Escrow Fee: ৳" . number_format($serviceCharge, 2) . " | Total: ৳" . number_format($totalAmount, 2) . "\n\n"
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
                'contact_phone' => $validated['phone'],
                'pickup_address' => $fullAddress,
                'dropoff_address' => $shop->public_address ?: ($shop->address ?? trim(implode(', ', array_filter([$shop->area, $shop->city ?: $customerCity])))),
                'checkup_fee' => $serviceCharge,
                'quoted_final_amount' => $totalAmount,
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

        session()->forget(['cart', 'cart_shop_id', 'cart_shop_locked', 'cart_shop_manual']);

        if ($validated['payment_method'] === 'SSLCOMMERZ') {
            return redirect("/checkout/payment/{$repairRequest->id}")
                ->with('success', "Booking #{$repairRequest->id} created with {$shop->name}. Complete escrow authorization below.");
        }

        return redirect()
            ->route('requests.track', $repairRequest->id)
            ->with('success', "Booking #{$repairRequest->id} sent to {$shop->name} and awaiting confirmation.");
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RepairShop;
use App\Models\ServicePackage;
use App\Models\SparePartListing;
use App\Services\GeolocationService;
use App\Services\MarketplaceSearchEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * FEATURE R6: Advanced Repair Shop Search & Multi-Parameter Filtering
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Nationwide Bangladesh, Multi-Branch & Dual-Address Alignment:
 *   Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 * Architectural Layer: Controller (ShopDiscoveryController)
 * Models Touched: RepairShop, VendorProfile, ShopReview, ServicePackage, ShopService
 * Views Rendered (from root `frontend/views/`):
 *   - home.blade.php
 *   - shops/explore.blade.php
 *   - shops/show.blade.php
 * ============================================================================
 */
class ShopDiscoveryController extends Controller
{
    /**
     * Reusable PostgreSQL-safe Haversine SQL expression (clamped to [-1, 1] for acos).
     * Operates on the private GPS Approximate Location (`latitude`, `longitude`).
     */
    private const HAVERSINE_SQL = '(6371 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians(?)) * cos(radians(latitude)) * cos(radians(longitude) - radians(?)) + sin(radians(?)) * sin(radians(latitude))))))';

    /**
     * Public Homepage (Matches Meramot home).
     * Route: GET /
     */
    public function home()
    {
        $featuredShops = RepairShop::where('is_active', true)
            ->where('is_public', true)
            ->where('is_featured', true)
            ->orderByRaw('COALESCE(featured_at, updated_at) DESC')
            ->orderByDesc('id')
            ->take(6)
            ->get();

        $offerCards = [
            [
                'title' => 'Free pickup on your first repair',
                'subtitle' => 'Complimentary doorstep pickup and return on eligible first bookings.',
                'href' => '/shops?sort=distance&freeDelivery=true&deals=true&q=pickup',
            ],
            [
                'title' => 'Laptop battery week',
                'subtitle' => 'Save up to 15% on replacement batteries at participating shops.',
                'href' => '/shops?q=battery&sort=price&deals=true',
            ],
        ];

        $popularCategories = [
            ['label' => 'Phone', 'sprite' => 'fa-mobile-screen-button', 'trend' => 'Screens & batteries', 'href' => '/shops?q=phone'],
            ['label' => 'Laptop', 'sprite' => 'fa-laptop', 'trend' => 'Boards & cooling', 'href' => '/shops?q=laptop'],
            ['label' => 'Tablet', 'sprite' => 'fa-tablet-screen-button', 'trend' => 'Glass & ports', 'href' => '/shops?q=tablet'],
            ['label' => 'Smartwatch', 'sprite' => 'fa-clock', 'trend' => 'Sensors & straps', 'href' => '/shops?q=smartwatch'],
            ['label' => 'Gaming Console', 'sprite' => 'fa-gamepad', 'trend' => 'HDMI & controllers', 'href' => '/shops?q=console'],
            ['label' => 'Printer', 'sprite' => 'fa-print', 'trend' => 'Feed & printheads', 'href' => '/shops?q=printer'],
        ];

        $recentlyViewed = [
            ['label' => 'MacBook Air M2 battery replacement', 'href' => '/spare-parts?q=MacBook%20Air%20M2%20Battery'],
            ['label' => 'iPhone 13 display set', 'href' => '/spare-parts?q=iPhone%2013%20Display'],
            ['label' => 'ThinkPad keyboard repair', 'href' => '/shops?q=ThinkPad%20keyboard%20repair'],
            ['label' => 'Sony PlayStation 5 HDMI port', 'href' => '/shops?q=PlayStation%205%20HDMI'],
            ['label' => 'iPad Pro 11-inch digitizer', 'href' => '/shops?q=iPad%20Pro%20digitizer'],
        ];

        $popularPackages = ServicePackage::where('is_active', true)->where('is_popular', true)->take(4)->get();

        return view('home', compact(
            'featuredShops',
            'offerCards',
            'popularCategories',
            'recentlyViewed',
            'popularPackages'
        ));
    }

    /**
     * Search and filter verified repair shops across all 64 districts of Bangladesh.
     * Route: GET /shops/explore or GET /shops
     */
    public function index(Request $request)
    {
        // Redirect legacy `/shops?category=SPARE_PARTS` requests directly to the dedicated Spare Parts Marketplace
        if (strtoupper(trim((string) $request->input('category'))) === 'SPARE_PARTS') {
            $forward = array_filter([
                'q' => $request->input('q'),
                'city' => $request->input('city'),
                'area' => $request->input('area'),
                'lat' => $request->input('lat'),
                'lng' => $request->input('lng'),
            ], fn ($v) => $v !== null && $v !== '');

            return redirect()->route('spare-parts.index', $forward);
        }

        $query = RepairShop::query()
            ->where('is_active', true)
            ->where('is_public', true);

        // 1. Fuzzy, Tokenized & Cross-Catalog Search (q) across Workshop Info, Offered Services & Stocked Parts
        $searchRelevanceOrder = MarketplaceSearchEngine::applyShopSearch($query, $request->input('q'));

        // 2. Category Tab Filter (COURIER_PICKUP, IN_SHOP_REPAIR, SPARE_PARTS)
        // Exclude pure spare-parts-only wholesalers from default repair workshop listings unless searching explicitly.
        if ($request->filled('category')) {
            $category = $request->input('category');
            $query->whereRaw("categories::text ILIKE ?", ["%{$category}%"]);
        } elseif (!$request->filled('q')) {
            $query->where(function ($q) {
                $q->whereRaw("categories::text ILIKE ?", ['%COURIER_PICKUP%'])
                    ->orWhereRaw("categories::text ILIKE ?", ['%IN_SHOP_REPAIR%'])
                    ->orWhereHas('vendorProfile', fn ($vp) => $vp->where('provides_fixed_packages', true));
            });
        }

        // 3. Nationwide District / City Filter
        if ($request->filled('city') && $request->input('city') !== 'All') {
            $query->where('city', $request->input('city'));
        }

        // 4. Area / Neighborhood Filter
        if ($request->filled('area') && $request->input('area') !== 'All') {
            $query->where('area', $request->input('area'));
        }

        // 5. Multi-Branch Chain Toggle (`?branched=true`)
        if ($request->boolean('branched')) {
            $query->where('is_branch', true);
        }

        // 6. Promo Toggles
        if ($request->boolean('voucher') || $request->boolean('has_voucher')) {
            $query->where('has_voucher', true);
        }
        if ($request->boolean('freeDelivery') || $request->boolean('free_delivery')) {
            $query->where('free_delivery', true)
                ->where('supports_pickup', true);
        }
        if ($request->boolean('deals') || $request->boolean('has_deals')) {
            $query->where('has_deals', true);
        }

        // 7. Minimum Rating
        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', (float) $request->input('min_rating'));
        }

        // 8. PostgreSQL Haversine Distance & Dynamic Distance-Based ETA (Shanid Sajjatuz Islam [23201465])
        $rawSort = $request->input('sort', 'relevance');
        $sortMap = [
            'rating' => 'topRated',
            'top_rated' => 'topRated',
            'topRated' => 'topRated',
            'lowest_price' => 'price',
            'price' => 'price',
            'nearest' => 'distance',
            'distance' => 'distance',
            'best_match' => 'relevance',
            'relevance' => 'relevance',
        ];
        $sort = $sortMap[$rawSort] ?? 'relevance';

        $hasExplicitCoords = $request->filled('lat') && $request->filled('lng');
        $hasDistanceRadius = $request->filled('maxDistanceKm') || $request->filled('max_distance_km');

        $refCoords = null;
        if ($request->filled('area') && $request->input('area') !== 'All') {
            $refCoords = GeolocationService::getAreaCoordinates($request->input('area'));
        }
        if (!$refCoords && $request->filled('city') && $request->input('city') !== 'All') {
            $refCoords = GeolocationService::getAreaCoordinates($request->input('city'));
        }
        $userLat = $hasExplicitCoords
            ? (float) $request->input('lat')
            : (float) ($refCoords['lat'] ?? 23.8103);
        $userLng = $hasExplicitCoords
            ? (float) $request->input('lng')
            : (float) ($refCoords['lng'] ?? 90.4125);

        $haversine = self::HAVERSINE_SQL;

        // Always compute Haversine distance_km when coordinates exist so ETA & distance badges are accurate
        $query->selectRaw(
            "*, CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN {$haversine} ELSE NULL END AS distance_km",
            [$userLat, $userLng, $userLat]
        );

        if ($sort === 'distance' || $hasDistanceRadius) {
            $query->whereNotNull('latitude')->whereNotNull('longitude');
        }

        if ($hasDistanceRadius) {
            $maxDistance = (float) ($request->input('maxDistanceKm', $request->input('max_distance_km', 12)));
            $query->whereRaw("{$haversine} <= ?", [$userLat, $userLng, $userLat, $maxDistance]);
        }

        // 9. Sorting Order
        if ($searchRelevanceOrder !== null && ($sort === 'relevance' || !($searchRelevanceOrder['all_matched'] ?? true))) {
            $query->orderByRaw($searchRelevanceOrder['sql'], $searchRelevanceOrder['bindings']);
        }
        switch ($sort) {
            case 'price':
                $query->orderBy('price_level', 'asc')
                      ->orderBy('inspection_fee', 'asc')
                      ->orderBy('base_labor_fee', 'asc')
                      ->orderBy('rating', 'desc');
                break;
            case 'distance':
                $query->orderBy('distance_km', 'asc')->orderBy('rating', 'desc');
                break;
            case 'topRated':
                $query->orderBy('rating', 'desc')->orderBy('review_count', 'desc');
                break;
            case 'relevance':
            default:
                $query->orderBy('is_featured', 'desc')->orderBy('rating', 'desc')->orderBy('review_count', 'desc');
                break;
        }

        $shops = $query->paginate(12)->withQueryString();

        // Synchronize each shop's ETA dynamically with its calculated Haversine distance
        $shops->getCollection()->transform(function ($shop) {
            if (isset($shop->distance_km) && is_numeric($shop->distance_km)) {
                $shop->eta_minutes = GeolocationService::estimateTravelTime((float) $shop->distance_km);
            }
            return $shop;
        });

        $cities = [
            'All', 'Dhaka', 'Chattogram', 'Gazipur', 'Sylhet', 'Mymensingh',
            'Narayanganj', 'Bogura', 'Rajshahi', 'Khulna', 'Cumilla',
            'Rangpur', 'Barishal', 'Jashore', "Cox's Bazar", 'Narail', 'Tangail',
        ];
        if ($request->filled('city') && !in_array($request->input('city'), $cities, true)) {
            $cities[] = $request->input('city');
        }

        $cityAreasMap = [
            'Dhaka' => [
                'All', 'Banani', 'Gulshan', 'Dhanmondi', 'Hatirpool', 'Mirpur',
                'Uttara', 'Mohammadpur', 'Bashundhara', 'Farmgate',
                'Tejgaon', 'Mohakhali', 'Badda', 'Rampura', 'New Market', 'Motijheel', 'Wari',
            ],
            'Chattogram' => ['All', 'Agrabad', 'GEC Circle', 'Nasirabad', 'Panchlaish', 'Chawkbazar', 'Halishahar'],
            'Sylhet' => ['All', 'Zindabazar', 'Amberkhana', 'Subhanighat', 'Upashahar'],
            'Rajshahi' => ['All', 'Shaheb Bazar', 'Boalia', 'Kazla'],
            'Khulna' => ['All', 'Shib Bari', 'Sonadanga', 'Khalishpur'],
            'Gazipur' => ['All', 'Joydebpur', 'Tongi', 'Chandana'],
            'Mymensingh' => ['All', 'Ganginarpar', 'Charpara'],
            'Bogura' => ['All', 'Satmatha', 'Jaleswaritola'],
            'Narayanganj' => ['All', 'Chashara', 'Siddhirganj'],
            'Cumilla' => ['All', 'Kandirpar', 'Tomsom Bridge'],
            'Rangpur' => ['All', 'Jahaj Company More', 'Dhap'],
            'Barishal' => ['All', 'Sadar Road', 'Nathullabad'],
        ];

        $selectedCity = $request->input('city', 'All');
        if ($selectedCity && $selectedCity !== 'All') {
            $dbCityAreas = RepairShop::where('is_active', true)
                ->where('is_public', true)
                ->where('city', $selectedCity)
                ->whereNotNull('area')
                ->distinct()
                ->orderBy('area')
                ->pluck('area')
                ->filter()
                ->values()
                ->all();
            $presetAreas = $cityAreasMap[$selectedCity] ?? ['All'];
            $areas = array_values(array_unique(array_merge(['All'], $presetAreas, $dbCityAreas)));
        } else {
            $areas = [
                'All', 'Banani', 'Gulshan', 'Dhanmondi', 'Hatirpool', 'Mirpur',
                'Uttara', 'Mohammadpur', 'Bashundhara', 'Farmgate',
                'Tejgaon', 'Mohakhali', 'Badda', 'Rampura', 'New Market', 'Motijheel', 'Wari',
                'Agrabad', 'GEC Circle', 'Zindabazar', 'Shaheb Bazar', 'Shib Bari',
            ];
        }

        if ($request->filled('area') && !in_array($request->input('area'), $areas, true)) {
            $areas[] = $request->input('area');
        }

        return view('shops.explore', compact('shops', 'areas', 'cities'));
    }

    /**
     * Show detailed profile for a selected repair shop + sibling branches if branched.
     * Route: GET /shops/{slug}
     */
    public function show(Request $request, $slug)
    {
        $shop = RepairShop::with([
            'vendorProfile',
            'reviews.customer',
            'reviews.repairRequest',
            'services' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order', 'asc');
            },
            'servicePackages' => function ($q) {
                $q->where('service_packages.is_active', true)
                    ->orderByDesc('is_popular')
                    ->orderBy('fixed_price', 'asc')
                    ->orderBy('service_packages.id', 'asc');
            },
        ])->where('slug', $slug)->firstOrFail();

        if ($shop->latitude !== null && $shop->longitude !== null) {
            $refCoords = GeolocationService::getAreaCoordinates((string) $shop->area)
                ?? GeolocationService::getAreaCoordinates((string) $shop->city)
                ?? ['lat' => 23.8103, 'lng' => 90.4125];
            $userLat = $request->filled('lat') ? (float) $request->input('lat') : (float) $refCoords['lat'];
            $userLng = $request->filled('lng') ? (float) $request->input('lng') : (float) $refCoords['lng'];

            $distanceKm = GeolocationService::calculateDistance(
                $userLat,
                $userLng,
                (float) $shop->latitude,
                (float) $shop->longitude
            );
            $shop->distance_km = $distanceKm;
            $shop->eta_minutes = GeolocationService::estimateTravelTime($distanceKm);
        }

        $siblingBranches = $shop->siblingBranches();

        $shopSparePartsCount = SparePartListing::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->count();

        $shopSpareParts = SparePartListing::where('shop_id', $shop->id)
            ->where('is_active', true)
            ->orderByRaw('LOWER(TRIM(title)) ASC')
            ->limit(48)
            ->get();

        $relatedShops = RepairShop::where('is_active', true)
            ->where('is_public', true)
            ->where('id', '!=', $shop->id)
            ->when(!$shop->isPureSparePartsWholesaler(), function ($q) {
                $q->where(function ($sub) {
                    $sub->whereRaw("categories::text ILIKE ?", ['%COURIER_PICKUP%'])
                        ->orWhereRaw("categories::text ILIKE ?", ['%IN_SHOP_REPAIR%'])
                        ->orWhereHas('vendorProfile', fn ($vp) => $vp->where('provides_fixed_packages', true));
                });
            })
            ->where(function ($q) use ($shop) {
                $q->where('area', $shop->area)->orWhere('city', $shop->city);
            })
            ->orderByRaw('CASE WHEN area = ? THEN 0 ELSE 1 END', [$shop->area])
            ->orderBy('rating', 'desc')
            ->take(3)
            ->get();

        return view('shops.show', compact('shop', 'siblingBranches', 'relatedShops', 'shopSpareParts', 'shopSparePartsCount'));
    }

    /**
     * API: Get nearby shops across Bangladesh based on user GPS coordinates
     * Assigned: Shanid Sajjatuz Islam [23201465]
     * Route: GET /api/shops/nearby
     */
    public function getNearbyShops(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:20.5,26.7',
            'lng' => 'required|numeric|between:88.0,92.7',
            'radius' => 'nullable|numeric|min:1|max:500',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $userLat = (float) $validated['lat'];
        $userLng = (float) $validated['lng'];
        $radius = (float) ($validated['radius'] ?? 10);
        $limit = (int) ($validated['limit'] ?? 20);
        $haversine = self::HAVERSINE_SQL;

        $shops = RepairShop::where('is_active', true)
            ->where('is_public', true)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->selectRaw("*, {$haversine} AS distance_km", [$userLat, $userLng, $userLat])
            ->whereRaw("{$haversine} <= ?", [$userLat, $userLng, $userLat, $radius])
            ->orderBy('distance_km', 'asc')
            ->take($limit)
            ->get()
            ->map(function ($shop) {
                $shop->distance_formatted = GeolocationService::formatDistance((float) $shop->distance_km);
                $shop->eta_minutes = GeolocationService::estimateTravelTime((float) $shop->distance_km);
                return $shop;
            });

        return response()->json([
            'success' => true,
            'user_location' => [
                'lat' => $userLat,
                'lng' => $userLng,
            ],
            'radius_km' => $radius,
            'total_found' => $shops->count(),
            'shops' => $shops,
        ]);
    }

    /**
     * API: Advanced search with all filters across Bangladesh
     * Assigned: Shanid Sajjatuz Islam [23201465]
     * Route: GET /api/shops/search
     */
    public function apiSearch(Request $request)
    {
        $query = RepairShop::query()
            ->where('is_active', true)
            ->where('is_public', true);

        $apiSearchRelevanceOrder = MarketplaceSearchEngine::applyShopSearch($query, $request->input('q'));

        if ($request->filled('category')) {
            $query->whereRaw("categories::text ILIKE ?", ["%{$request->input('category')}%"]);
        }

        if ($request->filled('city') && $request->input('city') !== 'All') {
            $query->where('city', $request->input('city'));
        }

        if ($request->filled('area') && $request->input('area') !== 'All') {
            $query->where('area', $request->input('area'));
        }

        if ($request->boolean('branched')) {
            $query->where('is_branch', true);
        }

        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', (float) $request->input('min_rating'));
        }

        if ($request->filled('max_price_level')) {
            $query->where('price_level', '<=', (int) $request->input('max_price_level'));
        }

        if ($request->boolean('has_deals') || $request->boolean('deals')) {
            $query->where('has_deals', true);
        }
        if ($request->boolean('has_voucher') || $request->boolean('voucher')) {
            $query->where('has_voucher', true);
        }
        if ($request->boolean('free_delivery') || $request->boolean('freeDelivery')) {
            $query->where('free_delivery', true);
        }

        $rawSort = $request->input('sort', 'rating');
        $sortMap = [
            'rating' => 'topRated',
            'top_rated' => 'topRated',
            'topRated' => 'topRated',
            'lowest_price' => 'price',
            'price' => 'price',
            'nearest' => 'distance',
            'distance' => 'distance',
            'best_match' => 'relevance',
            'relevance' => 'relevance',
        ];
        $sort = $sortMap[$rawSort] ?? 'topRated';

        $hasExplicitCoords = $request->filled('lat') && $request->filled('lng');
        $hasDistanceRadius = $request->filled('max_distance_km') || $request->filled('maxDistanceKm');

        $refCoords = null;
        if ($request->filled('area') && $request->input('area') !== 'All') {
            $refCoords = GeolocationService::getAreaCoordinates($request->input('area'));
        }
        if (!$refCoords && $request->filled('city') && $request->input('city') !== 'All') {
            $refCoords = GeolocationService::getAreaCoordinates($request->input('city'));
        }
        $userLat = $hasExplicitCoords
            ? (float) $request->input('lat')
            : (float) ($refCoords['lat'] ?? 23.8103);
        $userLng = $hasExplicitCoords
            ? (float) $request->input('lng')
            : (float) ($refCoords['lng'] ?? 90.4125);

        $haversine = self::HAVERSINE_SQL;

        $query->selectRaw(
            "*, CASE WHEN latitude IS NOT NULL AND longitude IS NOT NULL THEN {$haversine} ELSE NULL END AS distance_km",
            [$userLat, $userLng, $userLat]
        );

        if ($sort === 'distance' || $hasDistanceRadius) {
            $query->whereNotNull('latitude')->whereNotNull('longitude');
        }

        if ($hasDistanceRadius) {
            $maxDistance = (float) ($request->input('max_distance_km', $request->input('maxDistanceKm', 15)));
            $query->whereRaw("{$haversine} <= ?", [$userLat, $userLng, $userLat, $maxDistance]);
        }

        if ($apiSearchRelevanceOrder !== null && ($sort === 'relevance' || !($apiSearchRelevanceOrder['all_matched'] ?? true))) {
            $query->orderByRaw($apiSearchRelevanceOrder['sql'], $apiSearchRelevanceOrder['bindings']);
        }

        switch ($sort) {
            case 'price':
                $query->orderBy('price_level', 'asc')
                      ->orderBy('inspection_fee', 'asc')
                      ->orderBy('base_labor_fee', 'asc')
                      ->orderBy('rating', 'desc');
                break;
            case 'distance':
                $query->orderBy('distance_km', 'asc')->orderBy('rating', 'desc');
                break;
            case 'relevance':
                $query->orderBy('is_featured', 'desc')->orderBy('rating', 'desc')->orderBy('review_count', 'desc');
                break;
            case 'topRated':
            default:
                $query->orderBy('rating', 'desc')->orderBy('review_count', 'desc');
                break;
        }

        $perPage = (int) $request->input('per_page', 15);
        $shops = $query->paginate($perPage);

        $shops->getCollection()->transform(function ($shop) {
            if (isset($shop->distance_km) && is_numeric($shop->distance_km)) {
                $shop->distance_formatted = GeolocationService::formatDistance((float) $shop->distance_km);
                $shop->eta_minutes = GeolocationService::estimateTravelTime((float) $shop->distance_km);
                $shop->eta_formatted = GeolocationService::formatEta((int) $shop->eta_minutes);
            }
            return $shop;
        });

        return response()->json([
            'success' => true,
            'filters_applied' => $request->only([
                'q', 'category', 'city', 'area', 'branched', 'min_rating', 'max_price_level',
                'has_deals', 'deals', 'has_voucher', 'voucher', 'free_delivery', 'freeDelivery',
                'max_distance_km', 'maxDistanceKm', 'lat', 'lng', 'sort',
            ]),
            'data' => $shops,
        ]);
    }

    /**
     * Localhost-only fallback that queries Windows Location Services (Wi-Fi BSSID triangulation)
     * when Chromium desktop Geolocation API times out or drops to IP-level precision.
     * Route: GET /api/geolocation/device-fix
     */
    public function detectLocalDeviceLocation(Request $request)
    {
        if (app()->environment('testing') || PHP_OS_FAMILY !== 'Windows' || !in_array($request->ip(), ['127.0.0.1', '::1'], true)) {
            return response()->json(['ok' => false, 'reason' => 'unsupported']);
        }

        $cached = Cache::get('meramot.local_os_wifi_fix');
        if (is_array($cached) && !empty($cached['ok'])) {
            return response()->json($cached);
        }

        $psScript = 'Add-Type -AssemblyName System.Device; $w = New-Object System.Device.Location.GeoCoordinateWatcher([System.Device.Location.GeoPositionAccuracy]::Default); $w.Start(); $ms = 0; while ($w.Position.Location.IsUnknown -and $w.Permission -ne \'Denied\' -and $ms -lt 4200) { Start-Sleep -Milliseconds 80; $ms += 80 }; $c = $w.Position.Location; $w.Stop(); if (-not $c.IsUnknown) { Write-Output (\'{0:F7},{1:F7},{2:F1}\' -f $c.Latitude, $c.Longitude, $c.HorizontalAccuracy) }';

        $winDir = getenv('SystemRoot') ?: (getenv('WINDIR') ?: 'C:\\Windows');
        $tempDir = getenv('TEMP') ?: (getenv('TMP') ?: sys_get_temp_dir());
        $psExe = $winDir . '\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';
        if (!is_file($psExe)) {
            $psExe = 'powershell.exe';
        }
        $procEnv = [
            'SystemRoot' => $winDir,
            'SYSTEMROOT' => $winDir,
            'WINDIR' => $winDir,
            'PATH' => getenv('PATH') ?: ($winDir . '\\System32;' . $winDir . ';' . $winDir . '\\System32\\WindowsPowerShell\\v1.0'),
            'TEMP' => $tempDir,
            'TMP' => $tempDir,
        ];

        try {
            $process = new \Symfony\Component\Process\Process([
                $psExe,
                '-NoProfile',
                '-NonInteractive',
                '-Command',
                $psScript,
            ], null, $procEnv);
            $process->setTimeout(7);
            $process->run();
            $out = trim($process->getOutput());

            if (preg_match('/(-?\d+(?:\.\d+)?),(-?\d+(?:\.\d+)?),(\d+(?:\.\d+)?)/', $out, $m)) {
                $lat = (float) $m[1];
                $lng = (float) $m[2];
                $acc = (float) $m[3];
                if (is_finite($lat) && is_finite($lng) && $lat >= -90 && $lat <= 90 && $lng >= -180 && $lng <= 180) {
                    $payload = [
                        'ok' => true,
                        'lat' => $lat,
                        'lng' => $lng,
                        'accuracy' => max(15.0, is_finite($acc) ? $acc : 120.0),
                        'source' => 'windows_wifi',
                    ];
                    Cache::put('meramot.local_os_wifi_fix', $payload, 45);
                    return response()->json($payload);
                }
            }
        } catch (\Throwable $e) {
            // Silently return unavailable when OS location sensor is unreachable
        }

        return response()->json(['ok' => false, 'reason' => 'unavailable']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RepairShop;
use App\Models\ServicePackage;
use App\Services\GeolocationService;
use Illuminate\Http\Request;

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
            ->orderBy('rating', 'desc')
            ->take(6)
            ->get();

        $offerCards = [
            [
                'title' => 'Free pickup on your first repair',
                'subtitle' => 'Use code MERAMOTNEW to save the courier fee today.',
                'href' => '/shops?sort=distance&freeDelivery=true&deals=true&q=pickup',
            ],
            [
                'title' => 'Laptop battery week',
                'subtitle' => 'Selected vendors are offering up to 15% off original cells.',
                'href' => '/shops?q=battery&sort=price&deals=true',
            ],
        ];

        $popularCategories = [
            ['label' => 'Phone', 'sprite' => '📱', 'trend' => 'High screen repair demand', 'href' => '/shops?q=phone'],
            ['label' => 'Laptop', 'sprite' => '💻', 'trend' => 'Battery and keyboard issues', 'href' => '/shops?q=laptop'],
            ['label' => 'Tablet', 'sprite' => '🧾', 'trend' => 'Port replacement trending', 'href' => '/shops?q=tablet'],
            ['label' => 'Smartwatch', 'sprite' => '⌚', 'trend' => 'Glass and strap repairs', 'href' => '/shops?q=smartwatch'],
            ['label' => 'Gaming Console', 'sprite' => '🎮', 'trend' => 'Fan cleaning rising', 'href' => '/shops?q=console'],
            ['label' => 'Printer', 'sprite' => '🖨️', 'trend' => 'Roller and cartridge requests', 'href' => '/shops?q=printer'],
        ];

        $recentlyViewed = [
            ['label' => 'MacBook Air M2 battery replacement', 'href' => '/shops?q=MacBook%20Air%20M2%20Repair'],
            ['label' => 'iPhone 13 display set', 'href' => '/shops?q=iPhone%2013%20display'],
            ['label' => 'ThinkPad keyboard repair', 'href' => '/shops?q=ThinkPad%20keyboard%20repair'],
            ['label' => 'Sony PlayStation 5 HDMI port', 'href' => '/shops?q=PlayStation%205%20HDMI'],
            ['label' => 'iPad Pro 11-inch digitizer', 'href' => '/shops?q=iPad%20Pro%20digitizer'],
        ];

        $popularPackages = ServicePackage::where('is_popular', true)->take(4)->get();

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
        $query = RepairShop::query()
            ->where('is_active', true)
            ->where('is_public', true);

        // 1. Text Search (q) across Brand Name, Branch Tag, Description, Public Address, Area, City, and Specialties
        if ($request->filled('q')) {
            $searchTerm = trim($request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('branch_name', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('address', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('area', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('city', 'ILIKE', "%{$searchTerm}%")
                  ->orWhereRaw("specialties::text ILIKE ?", ["%{$searchTerm}%"]);
            });
        }

        // 2. Category Tab Filter (COURIER_PICKUP, IN_SHOP_REPAIR, SPARE_PARTS)
        if ($request->filled('category')) {
            $category = $request->input('category');
            $query->whereRaw("categories::text ILIKE ?", ["%{$category}%"]);
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
            $query->where('free_delivery', true);
        }
        if ($request->boolean('deals') || $request->boolean('has_deals')) {
            $query->where('has_deals', true);
        }

        // 7. Minimum Rating
        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', (float) $request->input('min_rating'));
        }

        // 8. PostgreSQL Haversine Distance Filter on Private GPS Pin (Shanid Sajjatuz Islam [23201465])
        $hasCoords = $request->filled('lat') && $request->filled('lng');
        if ($hasCoords) {
            $userLat = (float) $request->input('lat');
            $userLng = (float) $request->input('lng');
            $haversine = self::HAVERSINE_SQL;

            $query->whereNotNull('latitude')
                  ->whereNotNull('longitude')
                  ->selectRaw("*, {$haversine} AS distance_km", [$userLat, $userLng, $userLat]);

            if ($request->filled('maxDistanceKm')) {
                $maxDistance = (float) $request->input('maxDistanceKm', 12);
                $query->whereRaw("{$haversine} <= ?", [$userLat, $userLng, $userLat, $maxDistance]);
            }
        }

        // 9. Sorting Order
        $sort = $request->input('sort', 'relevance');
        switch ($sort) {
            case 'price':
                $query->orderBy('price_level', 'asc')->orderBy('inspection_fee', 'asc');
                break;
            case 'distance':
                if ($hasCoords) {
                    $query->orderBy('distance_km', 'asc');
                } else {
                    $query->orderBy('eta_minutes', 'asc')->orderBy('is_featured', 'desc');
                }
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

        $cities = [
            'All', 'Dhaka', 'Chattogram', 'Gazipur', 'Sylhet', 'Mymensingh',
            'Narayanganj', 'Bogura', 'Rajshahi', 'Khulna', 'Cumilla',
            'Rangpur', 'Barishal', 'Jashore', "Cox's Bazar", 'Narail', 'Tangail',
        ];
        if ($request->filled('city') && !in_array($request->input('city'), $cities, true)) {
            $cities[] = $request->input('city');
        }

        $areas = [
            'All', 'Banani', 'Gulshan', 'Dhanmondi', 'Hatirpool', 'Mirpur',
            'Uttara', 'Mohammadpur', 'Bashundhara', 'Farmgate',
            'Tejgaon', 'Mohakhali', 'Badda', 'Rampura', 'New Market', 'Motijheel', 'Wari',
            'Agrabad', 'GEC Circle', 'Zindabazar', 'Shaheb Bazar', 'Shib Bari',
        ];

        return view('shops.explore', compact('shops', 'areas', 'cities'));
    }

    /**
     * Show detailed profile for a selected repair shop + sibling branches if branched.
     * Route: GET /shops/{slug}
     */
    public function show($slug)
    {
        $shop = RepairShop::with([
            'vendorProfile',
            'reviews.customer',
            'services' => function ($q) {
                $q->where('is_active', true)->orderBy('sort_order', 'asc');
            },
        ])->where('slug', $slug)->firstOrFail();

        $siblingBranches = $shop->siblingBranches();

        $relatedShops = RepairShop::where('is_active', true)
            ->where('is_public', true)
            ->where('id', '!=', $shop->id)
            ->where(function ($q) use ($shop) {
                $q->where('area', $shop->area)->orWhere('city', $shop->city);
            })
            ->orderByRaw('CASE WHEN area = ? THEN 0 ELSE 1 END', [$shop->area])
            ->orderBy('rating', 'desc')
            ->take(3)
            ->get();

        return view('shops.show', compact('shop', 'siblingBranches', 'relatedShops'));
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

        if ($request->filled('q')) {
            $searchTerm = trim($request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('branch_name', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('address', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('area', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('city', 'ILIKE', "%{$searchTerm}%")
                  ->orWhereRaw("specialties::text ILIKE ?", ["%{$searchTerm}%"]);
            });
        }

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

        if ($request->boolean('has_deals')) {
            $query->where('has_deals', true);
        }
        if ($request->boolean('has_voucher')) {
            $query->where('has_voucher', true);
        }
        if ($request->boolean('free_delivery')) {
            $query->where('free_delivery', true);
        }

        $hasCoords = $request->filled('lat') && $request->filled('lng');
        if ($hasCoords) {
            $userLat = (float) $request->input('lat');
            $userLng = (float) $request->input('lng');
            $maxDistance = (float) $request->input('max_distance_km', 15);
            $haversine = self::HAVERSINE_SQL;

            $query->whereNotNull('latitude')
                  ->whereNotNull('longitude')
                  ->selectRaw("*, {$haversine} AS distance_km", [$userLat, $userLng, $userLat])
                  ->whereRaw("{$haversine} <= ?", [$userLat, $userLng, $userLat, $maxDistance]);
        }

        $sort = $request->input('sort', 'rating');
        switch ($sort) {
            case 'price':
                $query->orderBy('price_level', 'asc');
                break;
            case 'distance':
                if ($hasCoords) {
                    $query->orderBy('distance_km', 'asc');
                } else {
                    $query->orderBy('eta_minutes', 'asc');
                }
                break;
            case 'rating':
            default:
                $query->orderBy('rating', 'desc')->orderBy('review_count', 'desc');
                break;
        }

        $perPage = (int) $request->input('per_page', 15);
        $shops = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'filters_applied' => $request->only([
                'q', 'category', 'city', 'area', 'branched', 'min_rating', 'max_price_level',
                'has_deals', 'has_voucher', 'free_delivery', 'sort',
            ]),
            'data' => $shops,
        ]);
    }
}

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
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 * Architectural Layer: Controller (ShopDiscoveryController)
 * Models Touched: RepairShop, ShopReview, ServicePackage
 * Views Rendered:
 *   - resources/views/home.blade.php
 *   - resources/views/shops/explore.blade.php
 *   - resources/views/shops/show.blade.php
 * ============================================================================
 */
class ShopDiscoveryController extends Controller
{
    /**
     * Public Homepage (Matches legacy Meramot home).
     * Route: GET /
     */
    public function home()
    {
        $featuredShops = RepairShop::where('is_featured', true)
            ->orderBy('rating', 'desc')
            ->take(6)
            ->get();

        // Exact offer cards from commit 8e44218 / mock-data.ts
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

        // Exact 6 popular categories from commit 8e44218 / mock-data.ts
        $popularCategories = [
            ['label' => 'Phone', 'sprite' => '📱', 'trend' => 'High screen repair demand', 'href' => '/shops?q=phone'],
            ['label' => 'Laptop', 'sprite' => '💻', 'trend' => 'Battery and keyboard issues', 'href' => '/shops?q=laptop'],
            ['label' => 'Tablet', 'sprite' => '🧾', 'trend' => 'Port replacement trending', 'href' => '/shops?q=tablet'],
            ['label' => 'Smartwatch', 'sprite' => '⌚', 'trend' => 'Glass and strap repairs', 'href' => '/shops?q=smartwatch'],
            ['label' => 'Gaming Console', 'sprite' => '🎮', 'trend' => 'Fan cleaning rising', 'href' => '/shops?q=console'],
            ['label' => 'Printer', 'sprite' => '🖨️', 'trend' => 'Roller and cartridge requests', 'href' => '/shops?q=printer'],
        ];

        // Exact recently viewed searches from commit 8e44218 / mock-data.ts
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
     * Search and filter verified repair shops across metro areas.
     * Route: GET /shops/explore or GET /shops
     */
    public function index(Request $request)
    {
        $query = RepairShop::query();

        // 1. Text Search (q) across Name, Description, Address, Area, and Specialties
        if ($request->filled('q')) {
            $searchTerm = trim($request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('address', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('area', 'ILIKE', "%{$searchTerm}%")
                  ->orWhereRaw("specialties::text ILIKE ?", ["%{$searchTerm}%"]);
            });
        }

        // 2. Category Tab Filter (COURIER_PICKUP, IN_SHOP_REPAIR, SPARE_PARTS)
        if ($request->filled('category')) {
            $category = $request->input('category');
            $query->whereRaw("categories::text ILIKE ?", ["%{$category}%"]);
        }

        // 3. Area Filter (Banani, Gulshan, Dhanmondi, Uttara, Mirpur, Mohammadpur, etc.)
        if ($request->filled('area') && $request->input('area') !== 'All') {
            $query->where('area', $request->input('area'));
        }

        // 4. Promo Toggles
        if ($request->boolean('voucher') || $request->boolean('has_voucher')) {
            $query->where('has_voucher', true);
        }
        if ($request->boolean('freeDelivery') || $request->boolean('free_delivery')) {
            $query->where('free_delivery', true);
        }
        if ($request->boolean('deals') || $request->boolean('has_deals')) {
            $query->where('has_deals', true);
        }

        // 5. Minimum Rating
        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', (float)$request->input('min_rating'));
        }

        // 6. Distance Filter (using Haversine formula if user provides coordinates)
        if ($request->filled('lat') && $request->filled('lng') && $request->filled('maxDistanceKm')) {
            $userLat = (float) $request->input('lat');
            $userLng = (float) $request->input('lng');
            $maxDistance = (float) $request->input('maxDistanceKm', 12);

            // Haversine formula for PostgreSQL
            $query->selectRaw("
                *,
                (
                    6371 * acos(
                        cos(radians(?)) 
                        * cos(radians(latitude)) 
                        * cos(radians(longitude) - radians(?)) 
                        + sin(radians(?)) 
                        * sin(radians(latitude))
                    )
                ) AS distance_km
            ", [$userLat, $userLng, $userLat])
            ->havingRaw('distance_km <= ?', [$maxDistance]);
        }

        // 7. Sorting Order
        $sort = $request->input('sort', 'topRated');
        switch ($sort) {
            case 'price':
                $query->orderBy('price_level', 'asc')->orderBy('inspection_fee', 'asc');
                break;
            case 'distance':
                // If distance calculated, use it; otherwise use ETA
                if ($request->filled('lat') && $request->filled('lng')) {
                    $query->orderBy('distance_km', 'asc');
                } else {
                    $query->orderBy('eta_minutes', 'asc')->orderBy('is_featured', 'desc');
                }
                break;
            case 'relevance':
                $query->orderBy('is_featured', 'desc')->orderBy('rating', 'desc');
                break;
            case 'topRated':
            default:
                $query->orderBy('rating', 'desc')->orderBy('review_count', 'desc');
                break;
        }

        $shops = $query->paginate(12)->withQueryString();

        // Area list for filters
        $areas = [
            'All', 'Banani', 'Gulshan', 'Dhanmondi', 'Mirpur',
            'Uttara', 'Mohammadpur', 'Bashundhara', 'Farmgate',
            'Tejgaon', 'Mohakhali', 'Badda', 'Rampura', 'New Market', 'Wari'
        ];

        return view('shops.explore', compact('shops', 'areas'));
    }

    /**
     * Show detailed profile for a selected repair shop.
     * Route: GET /shops/{slug}
     */
    public function show($slug)
    {
        $shop = RepairShop::with(['reviews.customer'])->where('slug', $slug)->firstOrFail();
        $relatedShops = RepairShop::where('area', $shop->area)
            ->where('id', '!=', $shop->id)
            ->take(3)
            ->get();

        return view('shops.show', compact('shop', 'relatedShops'));
    }

    /**
     * API: Get nearby shops based on user coordinates
     * Route: GET /api/shops/nearby
     */
    public function getNearbyShops(Request $request)
    {
        $validated = $request->validate([
            'lat' => 'required|numeric|between:23.7,23.9',
            'lng' => 'required|numeric|between:90.3,90.5',
            'radius' => 'nullable|numeric|min:1|max:50', // kilometers
            'limit' => 'nullable|integer|min:1|max:50',
        ]);

        $userLat = $validated['lat'];
        $userLng = $validated['lng'];
        $radius = $validated['radius'] ?? 10; // Default 10km
        $limit = $validated['limit'] ?? 20;

        // Use Haversine formula to calculate distance
        $shops = RepairShop::selectRaw("
            *,
            (
                6371 * acos(
                    cos(radians(?)) 
                    * cos(radians(latitude)) 
                    * cos(radians(longitude) - radians(?)) 
                    + sin(radians(?)) 
                    * sin(radians(latitude))
                )
            ) AS distance_km
        ", [$userLat, $userLng, $userLat])
        ->havingRaw('distance_km <= ?', [$radius])
        ->orderBy('distance_km', 'asc')
        ->take($limit)
        ->get()
        ->map(function ($shop) {
            $shop->distance_formatted = GeolocationService::formatDistance($shop->distance_km);
            $shop->eta_minutes = GeolocationService::estimateTravelTime($shop->distance_km);
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
     * API: Advanced search with all filters
     * Route: GET /api/shops/search
     */
    public function apiSearch(Request $request)
    {
        $query = RepairShop::query();

        // Text search
        if ($request->filled('q')) {
            $searchTerm = trim($request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->where('name', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('description', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('address', 'ILIKE', "%{$searchTerm}%")
                  ->orWhere('area', 'ILIKE', "%{$searchTerm}%")
                  ->orWhereRaw("specialties::text ILIKE ?", ["%{$searchTerm}%"]);
            });
        }

        // Category filter
        if ($request->filled('category')) {
            $query->whereRaw("categories::text ILIKE ?", ["%{$request->input('category')}%"]);
        }

        // Area filter
        if ($request->filled('area')) {
            $query->where('area', $request->input('area'));
        }

        // Rating filter
        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', (float) $request->input('min_rating'));
        }

        // Price level filter
        if ($request->filled('max_price_level')) {
            $query->where('price_level', '<=', (int) $request->input('max_price_level'));
        }

        // Promotional filters
        if ($request->boolean('has_deals')) {
            $query->where('has_deals', true);
        }
        if ($request->boolean('has_voucher')) {
            $query->where('has_voucher', true);
        }
        if ($request->boolean('free_delivery')) {
            $query->where('free_delivery', true);
        }

        // Distance-based filtering
        if ($request->filled('lat') && $request->filled('lng')) {
            $userLat = (float) $request->input('lat');
            $userLng = (float) $request->input('lng');
            $maxDistance = (float) $request->input('max_distance_km', 15);

            $query->selectRaw("
                *,
                (
                    6371 * acos(
                        cos(radians(?)) 
                        * cos(radians(latitude)) 
                        * cos(radians(longitude) - radians(?)) 
                        + sin(radians(?)) 
                        * sin(radians(latitude))
                    )
                ) AS distance_km
            ", [$userLat, $userLng, $userLat])
            ->havingRaw('distance_km <= ?', [$maxDistance]);
        }

        // Sorting
        $sort = $request->input('sort', 'rating');
        switch ($sort) {
            case 'price':
                $query->orderBy('price_level', 'asc');
                break;
            case 'distance':
                if ($request->filled('lat') && $request->filled('lng')) {
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

        // Pagination
        $perPage = $request->input('per_page', 15);
        $shops = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'filters_applied' => $request->only([
                'q', 'category', 'area', 'min_rating', 'max_price_level',
                'has_deals', 'has_voucher', 'free_delivery', 'sort'
            ]),
            'data' => $shops,
        ]);
    }
}

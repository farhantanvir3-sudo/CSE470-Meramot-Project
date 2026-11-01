<?php

namespace App\Http\Controllers;

use App\Models\RepairShop;
use App\Models\ServicePackage;
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

        $offerCards = [
            [
                'title' => 'Free pickup on your first repair',
                'subtitle' => 'Use code MERAMOTNEW to save the courier fee today.',
                'href' => '/shops?freeDelivery=true&deals=true',
                'badge' => 'PROMO CODE',
            ],
            [
                'title' => 'Laptop battery week',
                'subtitle' => 'Selected vendors are offering up to 15% off original replacement cells.',
                'href' => '/shops?q=battery&sort=price&deals=true',
                'badge' => 'SPECIAL DEAL',
            ],
            [
                'title' => 'Express 1-Hour Screen Fix',
                'subtitle' => 'Certified technicians in Banani & Gulshan with instant parts.',
                'href' => '/shops?q=screen&sort=distance',
                'badge' => 'FAST TRACK',
            ]
        ];

        $popularCategories = [
            ['label' => 'Phone', 'sprite' => '📱', 'trend' => 'High screen repair demand', 'query' => 'phone'],
            ['label' => 'Laptop', 'sprite' => '💻', 'trend' => 'Battery and keyboard issues', 'query' => 'laptop'],
            ['label' => 'Tablet', 'sprite' => '🧾', 'trend' => 'Port replacement trending', 'query' => 'tablet'],
            ['label' => 'Smartwatch', 'sprite' => '⌚', 'trend' => 'Glass and strap repairs', 'query' => 'smartwatch'],
            ['label' => 'Gaming Console', 'sprite' => '🎮', 'trend' => 'Fan cleaning rising', 'query' => 'console'],
            ['label' => 'Printer', 'sprite' => '🖨️', 'trend' => 'Roller and cartridge requests', 'query' => 'printer'],
        ];

        $recentlyViewed = [
            ['label' => 'MacBook Air M2 battery replacement', 'query' => 'MacBook Air M2 repair'],
            ['label' => 'iPhone 13 display set', 'query' => 'iPhone display'],
            ['label' => 'ThinkPad keyboard repair', 'query' => 'keyboard repair'],
            ['label' => 'Samsung S23 charging port fix', 'query' => 'charging port'],
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

        // 6. Sorting Order
        $sort = $request->input('sort', 'topRated');
        switch ($sort) {
            case 'price':
                $query->orderBy('price_level', 'asc')->orderBy('inspection_fee', 'asc');
                break;
            case 'distance':
                // Order by simulated distance / priority
                $query->orderBy('eta_minutes', 'asc')->orderBy('is_featured', 'desc');
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
}

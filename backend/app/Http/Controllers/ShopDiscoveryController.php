<?php

namespace App\Http\Controllers;

use App\Models\RepairShop;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R6: Advanced Repair Shop Search & Multi-Parameter Filtering
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 * Architectural Layer: Controller (ShopDiscoveryController)
 * Models Touched: RepairShop, Category
 * View Rendered: resources/views/shops/explore.blade.php
 *
 * INSTRUCTIONS FOR SHANID:
 * 1. Implement index(Request $request): Filter shops by device categories (laptop,
 *    phone, etc.), area/distance radius, minimum rating (e.g. >= 4.5), and deals.
 * 2. Implement show($slug): Render shop profile displaying customer ratings,
 *    technician specialties, and available service packages.
 * ============================================================================
 */
class ShopDiscoveryController extends Controller
{
    /**
     * Search and filter verified repair shops across metro areas.
     * Route: GET /shops/explore
     */
    public function index(Request $request)
    {
        $query = RepairShop::query();

        // Filter by Area
        if ($request->filled('area')) {
            $query->where('area', $request->input('area'));
        }

        // Filter by Minimum Rating
        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', (float)$request->input('min_rating'));
        }

        // Filter by Special Voucher or Deals
        if ($request->boolean('has_voucher')) {
            $query->where('has_voucher', true);
        }

        $shops = $query->orderBy('is_featured', 'desc')->orderBy('rating', 'desc')->paginate(12);

        return view('shops.explore', compact('shops'));
    }

    /**
     * Show detailed profile for a selected repair shop.
     * Route: GET /shops/{slug}
     */
    public function show($slug)
    {
        // TODO (Shanid): Load shop along with verified customer reviews
        $shop = RepairShop::with('reviews')->where('slug', $slug)->firstOrFail();

        return view('shops.show', compact('shop'));
    }
}

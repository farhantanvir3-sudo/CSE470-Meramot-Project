<?php

namespace App\Http\Controllers;

use App\Models\RepairShop;
use App\Models\ShopReview;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R9: Vendor Rating & Verified Repair Review System
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
 * Architectural Layer: Controller (ReviewController)
 * Models Touched: ShopReview, Order
 * View Rendered: resources/views/reviews/write-review.blade.php
 *
 * INSTRUCTIONS FOR SHANID:
 * 1. Implement create($shopId): Verify that customer has a completed repair request
 *    with this shop before allowing review submission (verified purchase check).
 * 2. Implement store(Request $request): Validate star rating (1-5), save review,
 *    and recalculate average rating for the target RepairShop.
 * ============================================================================
 */
class ReviewController extends Controller
{
    /**
     * Show review writing form.
     * Route: GET /reviews/create/{shopId}
     */
    public function create($shopId)
    {
        $shop = RepairShop::findOrFail($shopId);
        return view('reviews.write-review', compact('shop'));
    }

    /**
     * Store submitted review and update shop average rating.
     * Route: POST /reviews
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'repair_shop_id' => 'required|exists:repair_shops,id',
            'rating' => 'required|integer|min:1|max:5',
            'review_comment' => 'required|string|min:10|max:1000',
        ]);

        $review = ShopReview::create(array_merge($validated, [
            'customer_id' => $request->user()->id ?? 1,
            'is_verified_purchase' => true,
        ]));

        // Recalculate shop average rating
        $shop = RepairShop::findOrFail($validated['repair_shop_id']);
        $newAvg = ShopReview::where('repair_shop_id', $shop->id)->avg('rating');
        $shop->update(['rating' => round($newAvg, 2)]);

        return redirect()->route('shops.show', $shop->slug)->with('success', 'Thank you! Your verified review has been posted.');
    }
}

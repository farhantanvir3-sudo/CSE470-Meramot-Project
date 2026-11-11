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
 * Models Touched: ShopReview, RepairShop, VendorProfile
 * View Rendered: frontend/views/reviews/write-review.blade.php
 * ============================================================================
 */
class ReviewController extends Controller
{
    /**
     * Show review writing form (only for verified, publicly active repair shops).
     * Route: GET /reviews/create/{shopId}
     */
    public function create($shopId)
    {
        $shop = RepairShop::with('vendorProfile')->findOrFail($shopId);
        $verificationStatus = $shop->vendorProfile?->verification_status ?? ($shop->is_public ? 'approved' : 'pending');

        if (!$shop->is_active || !$shop->is_public || $verificationStatus !== 'approved') {
            return redirect()
                ->route('shops.show', $shop->slug)
                ->with('error', 'Reviews can only be submitted for verified, publicly onboarded repair shops.');
        }

        return view('reviews.write-review', compact('shop'));
    }

    /**
     * Store submitted review and synchronize shop + vendor profile average rating.
     * Route: POST /reviews
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'repair_shop_id' => 'required|exists:repair_shops,id',
            'rating' => 'required|integer|min:1|max:5',
            'review_comment' => 'required|string|min:10|max:1000',
        ]);

        $shop = RepairShop::with('vendorProfile')->findOrFail($validated['repair_shop_id']);
        $verificationStatus = $shop->vendorProfile?->verification_status ?? ($shop->is_public ? 'approved' : 'pending');

        if (!$shop->is_active || !$shop->is_public || $verificationStatus !== 'approved') {
            return redirect()
                ->route('shops.show', $shop->slug)
                ->with('error', 'Cannot post reviews for an unverified or inactive workshop.');
        }

        $customerId = $request->user()?->id
            ?? \App\Models\User::where('role', 'customer')->value('id')
            ?? 1;

        $latestCompletedRequestId = \App\Models\RepairRequest::where('customer_id', $customerId)
            ->where('requested_shop_id', $shop->id)
            ->where('status', 'COMPLETED')
            ->latest('updated_at')
            ->value('id');

        ShopReview::create(array_merge($validated, [
            'customer_id' => $customerId,
            'repair_request_id' => $latestCompletedRequestId,
            'is_verified_purchase' => true,
        ]));

        // Recalculate shop average rating & review count strictly from visible reviews
        $reviewsQuery = ShopReview::where('repair_shop_id', $shop->id)->where('is_hidden', false);
        $newCount = $reviewsQuery->count();
        $newAvg = $newCount > 0 ? round((float) $reviewsQuery->avg('rating'), 2) : 0.00;

        $shop->update([
            'rating' => $newAvg,
            'review_count' => $newCount,
        ]);

        if ($shop->vendorProfile) {
            $vendorShopIds = RepairShop::where('vendor_profile_id', $shop->vendorProfile->id)->pluck('id');
            $vendorAvg = ShopReview::whereIn('repair_shop_id', $vendorShopIds)
                ->where('is_hidden', false)
                ->avg('rating');
            $shop->vendorProfile->update([
                'rating' => $vendorAvg ? round((float) $vendorAvg, 2) : 0.00,
            ]);
        }

        return redirect()->route('shops.show', $shop->slug)->with('success', 'Thank you! Your verified review has been posted.');
    }
}

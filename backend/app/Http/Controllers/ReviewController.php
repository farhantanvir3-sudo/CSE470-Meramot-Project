<?php

namespace App\Http\Controllers;

use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ShopReview;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * FEATURE R9: Vendor Rating & Verified Repair Review System
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
 * Architectural Layer: Controller (ReviewController)
 * Models Touched: ShopReview, RepairShop, VendorProfile, RepairRequest
 * View Rendered: frontend/views/reviews/write-review.blade.php
 * ============================================================================
 */
class ReviewController extends Controller
{
    /**
     * Show review writing form (only for verified, publicly active repair shops).
     * When opened from the Past Orders page (`?order_id=...`), links the review
     * to that completed repair order as a Verified Repair Review with service details.
     * Route: GET /reviews/create/{shopId}
     */
    public function create(Request $request, $shopId)
    {
        $shop = RepairShop::with([
            'vendorProfile',
            'services' => fn ($q) => $q->where('is_active', true)->orderBy('sort_order'),
        ])->findOrFail($shopId);

        $verificationStatus = $shop->vendorProfile?->verification_status ?? ($shop->is_public ? 'approved' : 'pending');

        if (!$shop->is_active || !$shop->is_public || $verificationStatus !== 'approved') {
            return redirect()
                ->route('shops.show', $shop->slug)
                ->with('error', 'Reviews can only be submitted for verified, publicly onboarded repair shops.');
        }

        $user = $request->user();
        if (!$user) {
            return redirect()->guest(route('login'))
                ->with('warning', 'Please sign in to write or manage your reviews.');
        }

        if ((int) ($shop->vendorProfile?->user_id ?? 0) === (int) $user->id) {
            return redirect()
                ->route('shops.show', $shop->slug)
                ->with('warning', 'Workshop owners cannot submit customer reviews for their own shop.');
        }

        $customerId = (int) $user->id;

        // Completed orders strictly belonging to this customer at this shop (via requested_shop_id or repairJob.shop_id)
        $completedOrders = RepairRequest::with('repairJob')
            ->where('customer_id', $customerId)
            ->whereIn('status', ['COMPLETED', 'RETURNED_TO_CUSTOMER'])
            ->where(function ($q) use ($shop) {
                $q->where('requested_shop_id', $shop->id)
                  ->orWhereHas('repairJob', fn ($jq) => $jq->where('shop_id', $shop->id));
            })
            ->orderByDesc('updated_at')
            ->get();

        $selectedOrder = null;
        if ($request->filled('order_id')) {
            $orderId = (int) $request->query('order_id');
            $selectedOrder = $completedOrders->firstWhere('id', $orderId);
        }

        $isFromPastOrders = $selectedOrder !== null;
        $prefilledService = null;
        if ($selectedOrder) {
            $cat = trim((string) ($selectedOrder->issue_category ?: $selectedOrder->device_type ?: 'Hardware Repair'));
            $title = trim((string) ($selectedOrder->title ?: 'Bench Repair'));
            $prefilledService = Str::limit("{$cat} — {$title}", 175, '');
        }

        return view('reviews.write-review', compact(
            'shop',
            'completedOrders',
            'selectedOrder',
            'isFromPastOrders',
            'prefilledService'
        ));
    }

    /**
     * Store submitted review and synchronize shop + vendor profile average rating.
     * A review is marked `is_verified_purchase = true` ONLY when submitted from
     * a completed past order (`repair_request_id` / `from_past_orders`), recording
     * the exact `service_provided` so other customers see what the shop is skilled at.
     * Route: POST /reviews
     */
    public function store(Request $request)
    {
        if (!$request->user()) {
            return redirect()->guest(route('login'))
                ->with('warning', 'Please sign in to submit a review.');
        }

        $validated = $request->validate([
            'repair_shop_id' => 'required|exists:repair_shops,id',
            'rating' => 'required|integer|min:1|max:5',
            'review_comment' => 'required|string|min:10|max:1000',
            'repair_request_id' => 'nullable|integer|exists:repair_requests,id',
            'service_provided' => 'nullable|string|max:180',
            'from_past_orders' => 'nullable|boolean',
        ]);

        $shop = RepairShop::with('vendorProfile')->findOrFail($validated['repair_shop_id']);
        $verificationStatus = $shop->vendorProfile?->verification_status ?? ($shop->is_public ? 'approved' : 'pending');

        if (!$shop->is_active || !$shop->is_public || $verificationStatus !== 'approved') {
            return redirect()
                ->route('shops.show', $shop->slug)
                ->with('error', 'Cannot post reviews for an unverified or inactive workshop.');
        }

        if ((int) ($shop->vendorProfile?->user_id ?? 0) === (int) $request->user()->id) {
            return redirect()
                ->route('shops.show', $shop->slug)
                ->with('warning', 'Workshop owners cannot submit customer reviews for their own shop.');
        }

        $customerId = (int) $request->user()->id;

        $verifiedOrder = null;
        if (!empty($validated['repair_request_id'])) {
            $verifiedOrder = RepairRequest::with('repairJob')
                ->where('id', (int) $validated['repair_request_id'])
                ->where('customer_id', $customerId)
                ->whereIn('status', ['COMPLETED', 'RETURNED_TO_CUSTOMER'])
                ->where(function ($q) use ($shop) {
                    $q->where('requested_shop_id', $shop->id)
                      ->orWhereHas('repairJob', fn ($jq) => $jq->where('shop_id', $shop->id));
                })
                ->first();
        }

        $fallbackCompletedOrder = RepairRequest::where('customer_id', $customerId)
            ->where(function ($q) use ($shop) {
                $q->where('requested_shop_id', $shop->id)
                  ->orWhereHas('repairJob', fn ($jq) => $jq->where('shop_id', $shop->id));
            })
            ->whereIn('status', ['COMPLETED', 'RETURNED_TO_CUSTOMER'])
            ->latest('updated_at')
            ->first();

        // Strictly mark as a Verified Repair Review ONLY when submitted from Past Orders (with a valid completed order)
        $isVerifiedRepair = $verifiedOrder !== null;
        $linkedRequestId = $verifiedOrder?->id ?? $fallbackCompletedOrder?->id;

        $serviceProvided = trim((string) ($validated['service_provided'] ?? ''));
        if ($serviceProvided === '' && $verifiedOrder) {
            $cat = trim((string) ($verifiedOrder->issue_category ?: $verifiedOrder->device_type ?: 'Hardware Repair'));
            $title = trim((string) ($verifiedOrder->title ?: 'Bench Repair'));
            $serviceProvided = Str::limit("{$cat} — {$title}", 175, '');
        }

        // If the customer already reviewed this exact past order, update it instead of creating a duplicate
        if ($verifiedOrder) {
            $existingReview = ShopReview::where('customer_id', $customerId)
                ->where('repair_shop_id', $shop->id)
                ->where('repair_request_id', $verifiedOrder->id)
                ->first();

            if ($existingReview) {
                $existingReview->update([
                    'rating' => $validated['rating'],
                    'service_provided' => $serviceProvided ?: $existingReview->service_provided,
                    'review_comment' => $validated['review_comment'],
                    'is_verified_purchase' => true,
                ]);

                self::recalculateShopRating($shop);

                return redirect()
                    ->route('shops.show', $shop->slug)
                    ->with('success', 'Your verified repair review for Order #' . $verifiedOrder->id . ' has been updated.');
            }
        }

        ShopReview::create([
            'repair_shop_id' => $shop->id,
            'customer_id' => $customerId,
            'repair_request_id' => $linkedRequestId,
            'rating' => $validated['rating'],
            'service_provided' => $serviceProvided !== '' ? $serviceProvided : null,
            'review_comment' => $validated['review_comment'],
            'is_verified_purchase' => $isVerifiedRepair,
        ]);

        self::recalculateShopRating($shop);

        $msg = $isVerifiedRepair
            ? 'Thank you! Your Verified Repair Review (with service details) has been posted.'
            : 'Thank you! Your review has been posted. (Tip: Review from Past Orders to earn the Verified Repair badge.)';

        return redirect()->route('shops.show', $shop->slug)->with('success', $msg);
    }

    /**
     * Update an existing review owned by the currently authenticated user.
     * Route: PUT /reviews/{id}
     */
    public function update(Request $request, $id)
    {
        $review = ShopReview::with('shop.vendorProfile')->findOrFail($id);
        $user = $request->user();

        if (!$user || ((int) $review->customer_id !== (int) $user->id && $user->role !== 'admin')) {
            abort(403, 'You can only edit your own reviews.');
        }

        $validated = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'review_comment' => 'required|string|min:10|max:1000',
            'service_provided' => 'nullable|string|max:180',
        ]);

        $updateData = [
            'rating' => (int) $validated['rating'],
            'review_comment' => trim($validated['review_comment']),
        ];

        if ($request->has('service_provided')) {
            $srv = trim((string) $validated['service_provided']);
            $updateData['service_provided'] = $srv !== '' ? $srv : $review->service_provided;
        }

        $review->update($updateData);

        if ($review->shop) {
            self::recalculateShopRating($review->shop);
        }

        return redirect()
            ->route('shops.show', $review->shop->slug)
            ->with('success', 'Your review has been updated successfully.');
    }

    /**
     * Delete a single review owned by the currently authenticated user.
     * Route: DELETE /reviews/{id}
     */
    public function destroy(Request $request, $id)
    {
        $review = ShopReview::with('shop.vendorProfile')->findOrFail($id);
        $user = $request->user();

        if (!$user || ((int) $review->customer_id !== (int) $user->id && $user->role !== 'admin')) {
            abort(403, 'You can only delete your own reviews.');
        }

        $shop = $review->shop;
        $review->delete();

        if ($shop) {
            self::recalculateShopRating($shop);
        }

        if ($request->boolean('redirect_back')) {
            return redirect()
                ->back()
                ->with('success', 'Your review has been deleted.');
        }

        return redirect()
            ->route('shops.show', $shop->slug)
            ->with('success', 'Your review has been deleted.');
    }

    /**
     * Bulk delete selected reviews owned by the currently authenticated user.
     * Route: POST /reviews/bulk-delete
     */
    public function bulkDestroy(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            abort(403, 'Authentication required.');
        }

        $validated = $request->validate([
            'review_ids' => 'required|array|min:1',
            'review_ids.*' => 'integer|exists:shop_reviews,id',
            'repair_shop_id' => 'required|integer|exists:repair_shops,id',
        ]);

        $shop = RepairShop::with('vendorProfile')->findOrFail($validated['repair_shop_id']);

        $query = ShopReview::where('repair_shop_id', $shop->id)
            ->whereIn('id', $validated['review_ids']);

        if ($user->role !== 'admin') {
            $query->where('customer_id', $user->id);
        }

        $deletedCount = $query->delete();
        self::recalculateShopRating($shop);

        return redirect()
            ->route('shops.show', $shop->slug)
            ->with('success', "Deleted {$deletedCount} of your review(s).");
    }

    /**
     * Recalculate shop and parent vendor profile rating & review count from visible reviews.
     */
    public static function recalculateShopRating(RepairShop $shop): void
    {
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
    }
}

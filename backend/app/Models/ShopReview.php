<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R9: Vendor Rating & Verified Repair Review System
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
 * Architectural Layer: Model (ShopReview)
 * Associated Controller: ReviewController
 * View: resources/views/reviews/write-review.blade.php
 * ============================================================================
 */
class ShopReview extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_shop_id',
        'customer_id',
        'repair_request_id',
        'rating', // 1 to 5 stars
        'review_comment',
        'is_verified_purchase',
    ];

    protected $casts = [
        'rating' => 'integer',
        'is_verified_purchase' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'repair_shop_id');
    }
}

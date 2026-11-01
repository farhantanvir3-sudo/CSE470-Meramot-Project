<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R6: Advanced Repair Shop Search & Multi-Parameter Filtering
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 * Architectural Layer: Model (RepairShop)
 * Associated Controller: ShopDiscoveryController
 * View: resources/views/shops/explore.blade.php
 * ============================================================================
 */
class RepairShop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'address',
        'city',
        'area',
        'latitude',
        'longitude',
        'rating',
        'review_count',
        'price_level',
        'inspection_fee',
        'base_labor_fee',
        'offer_summary',
        'result_tag',
        'eta_minutes',
        'has_deals',
        'has_voucher',
        'free_delivery',
        'is_featured',
        'phone',
        'email',
        'logo_url',
        'categories',
        'specialties',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'rating' => 'decimal:2',
        'review_count' => 'integer',
        'price_level' => 'integer',
        'inspection_fee' => 'decimal:2',
        'base_labor_fee' => 'decimal:2',
        'eta_minutes' => 'integer',
        'has_deals' => 'boolean',
        'is_featured' => 'boolean',
        'has_voucher' => 'boolean',
        'free_delivery' => 'boolean',
        'categories' => 'array',
        'specialties' => 'array',
    ];

    public function reviews()
    {
        return $this->hasMany(ShopReview::class);
    }
}

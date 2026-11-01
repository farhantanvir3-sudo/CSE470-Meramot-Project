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
        'area',
        'latitude',
        'longitude',
        'rating',
        'price_level',
        'is_featured',
        'has_voucher',
        'free_delivery',
        'categories',
        'specialties',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'rating' => 'decimal:2',
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

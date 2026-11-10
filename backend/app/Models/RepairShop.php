<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R6: Advanced Repair Shop Search & Multi-Parameter Filtering
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Multi-Branch & Dual-Address Architecture: Farhan Tanvir [Student ID: 22299179]
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 * Architectural Layer: Model (RepairShop)
 * Associated Controller: ShopDiscoveryController, VendorOnboardingController
 * View: frontend/views/shops/explore.blade.php, frontend/views/shops/show.blade.php
 * ============================================================================
 */
class RepairShop extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_profile_id',
        'osm_id',
        'name',
        'slug',
        'is_branch',
        'branch_name',
        'description',
        'address',
        'gps_address',
        'same_as_gps_location',
        'city',
        'area',
        'latitude',
        'longitude',
        'rating',
        'review_count',
        'price_level',
        'inspection_fee',
        'base_labor_fee',
        'pickup_fee',
        'express_fee',
        'opening_days',
        'opening_hours',
        'opening_hours_text',
        'offer_summary',
        'result_tag',
        'eta_minutes',
        'has_deals',
        'has_voucher',
        'free_delivery',
        'is_featured',
        'is_active',
        'is_public',
        'setup_complete',
        'accepts_direct_orders',
        'supports_pickup',
        'supports_onsite_repair',
        'delivery_radius_km',
        'phone',
        'email',
        'logo_url',
        'banner_url',
        'categories',
        'specialties',
    ];

    /**
     * Hide private GPS approximate location text from raw public JSON serialization
     * while keeping Haversine distance calculations operational server-side.
     */
    protected $hidden = [
        'gps_address',
    ];

    protected $casts = [
        'is_branch' => 'boolean',
        'same_as_gps_location' => 'boolean',
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'rating' => 'decimal:2',
        'review_count' => 'integer',
        'price_level' => 'integer',
        'inspection_fee' => 'decimal:2',
        'base_labor_fee' => 'decimal:2',
        'pickup_fee' => 'decimal:2',
        'express_fee' => 'decimal:2',
        'delivery_radius_km' => 'decimal:2',
        'eta_minutes' => 'integer',
        'has_deals' => 'boolean',
        'is_featured' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'setup_complete' => 'boolean',
        'accepts_direct_orders' => 'boolean',
        'supports_pickup' => 'boolean',
        'supports_onsite_repair' => 'boolean',
        'has_voucher' => 'boolean',
        'free_delivery' => 'boolean',
        'categories' => 'array',
        'specialties' => 'array',
    ];

    public function vendorProfile()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    public function reviews()
    {
        return $this->hasMany(ShopReview::class);
    }

    public function services()
    {
        return $this->hasMany(ShopService::class, 'shop_id');
    }

    public function carts()
    {
        return $this->hasMany(Cart::class, 'shop_id');
    }

    public function repairJobs()
    {
        return $this->hasMany(RepairJob::class, 'shop_id');
    }

    /**
     * Public vendor-provided storefront address.
     * If `same_as_gps_location` is true, returns `gps_address` (which equals `address`).
     * Otherwise returns ONLY the vendor-provided `address`, keeping raw GPS pin private.
     */
    public function getPublicAddressAttribute(): string
    {
        if ($this->same_as_gps_location && !empty($this->gps_address)) {
            return $this->gps_address;
        }

        if (!empty($this->address)) {
            return $this->address;
        }

        return collect([$this->area, $this->city])->filter()->implode(', ') ?: 'Bangladesh';
    }

    /**
     * Exact branch tag label to display on shop tiles when branching is enabled.
     */
    public function getDisplayBranchTagAttribute(): ?string
    {
        if ($this->is_branch && !empty($this->branch_name)) {
            return $this->branch_name;
        }

        return null;
    }

    /**
     * Fetch other active branches owned by the same vendor brand.
     */
    public function siblingBranches()
    {
        if (!$this->vendor_profile_id) {
            return collect();
        }

        return static::where('vendor_profile_id', $this->vendor_profile_id)
            ->where('id', '!=', $this->id)
            ->where('is_active', true)
            ->orderBy('city')
            ->orderBy('branch_name')
            ->get();
    }
}

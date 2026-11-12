<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R11: Vendor Onboarding, Dual-Address & Multi-Branch Brand Profile
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Supports:
 *   - Single vendor owning 1 standalone shop OR expanding into multiple
 *     city/countrywide branches (`is_branched = true`) under `brand_name`.
 *   - Dual Shop Addresses: GPS Approximate Location (`gps_address`,
 *     `shop_latitude`, `shop_longitude`) vs Vendor-Provided Location (`shop_address`).
 * ============================================================================
 */
class VendorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'owner_name',
        'business_email',
        'phone',
        'shop_name',
        'brand_name',
        'is_branched',
        'business_license_no',
        'nid_number',
        'verification_status', // 'pending', 'under_review', 'approved', 'rejected'
        'rejection_reason',
        'rejected_at',
        'rejection_visible_until',
        'shop_address',
        'gps_address',
        'same_as_gps_location',
        'city',
        'area',
        'shop_latitude',
        'shop_longitude',
        'categories',
        'specialties',
        'courier_pickup',
        'in_shop_repair',
        'spare_parts',
        'notes',
        'logo_url',
        'documents_path',
        'reviewed_by_admin_id',
        'reviewed_at',
        'commission_rate',
        'rating',
    ];

    protected $casts = [
        'categories' => 'array',
        'specialties' => 'array',
        'is_branched' => 'boolean',
        'same_as_gps_location' => 'boolean',
        'courier_pickup' => 'boolean',
        'in_shop_repair' => 'boolean',
        'spare_parts' => 'boolean',
        'shop_latitude' => 'decimal:8',
        'shop_longitude' => 'decimal:8',
        'commission_rate' => 'decimal:2',
        'rating' => 'decimal:2',
        'rejected_at' => 'datetime',
        'rejection_visible_until' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function shops()
    {
        return $this->hasMany(RepairShop::class, 'vendor_profile_id');
    }

    public function bids()
    {
        return $this->hasMany(Bid::class, 'vendor_id');
    }

    /**
     * Canonical brand name shared across all branches of this vendor.
     */
    public function getCanonicalBrandNameAttribute(): string
    {
        return $this->brand_name ?: $this->shop_name;
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R11: Vendor Onboarding & Business Profile Verification Engine
 * Assigned Developer: Farhan Tanvir [22299179]
 */
class VendorProfile extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'shop_name',
        'business_license_no',
        'nid_number',
        'verification_status', // 'pending', 'approved', 'rejected'
        'rejection_reason',
        'shop_address',
        'shop_latitude',
        'shop_longitude',
        'categories',
        'documents_path',
        'commission_rate',
        'rating',
    ];

    protected $casts = [
        'categories' => 'array',
        'shop_latitude' => 'decimal:8',
        'shop_longitude' => 'decimal:8',
        'commission_rate' => 'decimal:2',
        'rating' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class, 'vendor_id');
    }
}

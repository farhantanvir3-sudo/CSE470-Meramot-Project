<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R1: Delivery Agent Onboarding & Credential Verification
 * Assigned Developer: Md. Nafiul Islam [22299035]
 */
class DeliveryAgent extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'nid_number',
        'vehicle_type',
        'license_number',
        'verification_status', // 'pending', 'verified', 'rejected'
        'current_latitude',
        'current_longitude',
        'is_available',
        'total_earnings',
    ];

    protected $casts = [
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'is_available' => 'boolean',
        'total_earnings' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

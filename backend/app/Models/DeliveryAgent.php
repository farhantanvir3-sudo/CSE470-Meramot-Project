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
        'registration_status', // 'PENDING', 'APPROVED', 'REJECTED'
        'status',              // 'OFFLINE', 'AVAILABLE', 'BUSY', 'SUSPENDED'
        'is_active',
        'rejection_reason',
        'current_latitude',
        'current_longitude',
        'total_earnings',
    ];

    protected $casts = [
        'current_latitude' => 'decimal:8',
        'current_longitude' => 'decimal:8',
        'is_active' => 'boolean',
        'total_earnings' => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
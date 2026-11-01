<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R7: Customer Repair Request Posting & Lifecycle Tracking
 * Assigned Developer: Shanid Sajjatuz Islam [23201465]
 */
class RepairRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'device_type',
        'brand',
        'model_name',
        'serial_number',
        'issue_title',
        'issue_description',
        'photos',
        'status', // 'pending_review', 'bidding_open', 'assigned', 'in_repair', 'repaired', 'delivered', 'cancelled'
        'preferred_service_type', // 'pickup_dropoff', 'walk_in', 'technician_home_visit'
        'ai_diagnostic_summary',
        'pickup_address',
        'selected_bid_id',
    ];

    protected $casts = [
        'photos' => 'array',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }
}

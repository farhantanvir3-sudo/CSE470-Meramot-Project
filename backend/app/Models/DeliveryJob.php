<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R2: Delivery Job Queue & Status Milestone Synchronization
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
 * Architectural Layer: Model (DeliveryJob)
 * Associated Controller: DeliveryJobController
 * View: frontend/views/delivery/jobs-queue.blade.php
 * ============================================================================
 */
class DeliveryJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_job_id',
        'repair_request_id',
        'delivery_agent_id',
        'coverage_zone_id',
        'direction', // 'TO_SHOP', 'TO_CUSTOMER'
        'type',      // 'REGULAR', 'EXPRESS'
        'status',    // 'PENDING', 'SCHEDULED', 'DISPATCHED', 'PICKED_UP', 'IN_TRANSIT', 'DELIVERED', 'FAILED', 'CANCELLED'
        'partner_name',
        'external_order_id',
        'tracking_code',
        'rider_name',
        'rider_phone',
        'pickup_address',
        'dropoff_address',
        'pickup_otp',
        'dropoff_otp',
        'distance_km',
        'fee',
        'scheduled_at',
        'dispatched_at',
        'picked_up_at',
        'delivered_at',
        'cancellation_reason',
        'milestone_timestamps',
    ];

    protected $casts = [
        'distance_km' => 'decimal:2',
        'fee' => 'decimal:2',
        'scheduled_at' => 'datetime',
        'dispatched_at' => 'datetime',
        'picked_up_at' => 'datetime',
        'delivered_at' => 'datetime',
        'milestone_timestamps' => 'array',
    ];

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class);
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class);
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }
}

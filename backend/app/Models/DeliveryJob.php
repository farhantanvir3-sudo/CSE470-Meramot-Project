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
 * View: resources/views/delivery/jobs-queue.blade.php
 * ============================================================================
 */
class DeliveryJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_request_id',
        'delivery_agent_id',
        'job_type', // 'pickup_from_customer', 'deliver_to_shop', 'return_to_customer'
        'status',   // 'unassigned', 'assigned', 'picked_up', 'in_transit', 'delivered', 'failed'
        'pickup_address',
        'dropoff_address',
        'pickup_contact',
        'dropoff_contact',
        'pickup_otp',
        'dropoff_otp',
        'milestone_timestamps',
    ];

    protected $casts = [
        'milestone_timestamps' => 'array',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class);
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }
}

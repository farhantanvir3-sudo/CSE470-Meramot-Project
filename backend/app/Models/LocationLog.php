<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R4: Live Delivery Agent GPS Location & Route Sharing
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
 * Architectural Layer: Model (LocationLog)
 * Associated Controller: TrackingController
 * View: resources/views/tracking/live-map.blade.php
 * ============================================================================
 */
class LocationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_agent_id',
        'delivery_job_id',
        'latitude',
        'longitude',
        'speed',
        'heading',
        'recorded_at',
    ];

    protected $casts = [
        'latitude' => 'decimal:8',
        'longitude' => 'decimal:8',
        'recorded_at' => 'datetime',
    ];

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }
}

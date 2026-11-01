<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R3: Delivery Agent Earnings Ledger & Payout Tracker
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
 * Architectural Layer: Model (RiderEarnings)
 * Associated Controller: RiderLedgerController
 * View: resources/views/delivery/earnings.blade.php
 * ============================================================================
 */
class RiderEarnings extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_agent_id',
        'delivery_job_id',
        'trip_fee',
        'bonus_amount',
        'total_earned',
        'payout_status', // 'unpaid', 'requested', 'settled'
        'payout_reference',
    ];

    protected $casts = [
        'trip_fee' => 'decimal:2',
        'bonus_amount' => 'decimal:2',
        'total_earned' => 'decimal:2',
    ];

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function deliveryJob()
    {
        return $this->belongsTo(DeliveryJob::class);
    }
}

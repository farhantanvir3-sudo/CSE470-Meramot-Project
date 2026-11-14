<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * RepairJob Model — Dual-Path & Dual-Quote Execution Record
 * ============================================================================
 * Created either when a customer accepts a Marketplace Bid (Quote 1) or
 * immediately pre-linked when checking out a Direct-Order Cart (`status: CREATED`).
 * Holds Quote 2 (`diagnosis_notes`, `final_quoted_amount`, `final_quote_items`).
 * ============================================================================
 */
class RepairJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_request_id',
        'shop_id',
        'accepted_bid_id',
        'status',
        'diagnosis_notes',
        'final_quoted_amount',
        'final_quote_items',
        'customer_approved',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'final_quoted_amount' => 'decimal:2',
        'final_quote_items' => 'array',
        'customer_approved' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class);
    }

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }

    public function acceptedBid()
    {
        return $this->belongsTo(Bid::class, 'accepted_bid_id');
    }

    public function deliveryJobs()
    {
        return $this->hasMany(DeliveryJob::class, 'repair_job_id');
    }
}

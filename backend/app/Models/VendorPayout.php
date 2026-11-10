<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R18: Automated Vendor Payout & Net Commission Settlement
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
 * Architectural Layer: Model (VendorPayout)
 * Associated Controller: VendorPayoutController
 * View: resources/views/admin/payouts/manage.blade.php
 * ============================================================================
 */
class VendorPayout extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'delivery_agent_id',
        'amount',
        'currency',
        'status', // 'PENDING', 'PROCESSING', 'PAID', 'FAILED', 'CANCELLED'
        'reference_code',
        'notes',
        'paid_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class, 'delivery_agent_id');
    }

    public function vendor()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }
}

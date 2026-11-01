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
        'vendor_id',
        'gross_earnings',
        'platform_commission_amount', // 10% deduction
        'net_payout_amount',
        'payout_method', // 'bKash', 'Nagad', 'bank_transfer'
        'account_number',
        'status', // 'pending', 'approved', 'disbursed', 'rejected'
        'disbursed_at',
    ];

    protected $casts = [
        'gross_earnings' => 'decimal:2',
        'platform_commission_amount' => 'decimal:2',
        'net_payout_amount' => 'decimal:2',
        'disbursed_at' => 'datetime',
    ];

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }
}

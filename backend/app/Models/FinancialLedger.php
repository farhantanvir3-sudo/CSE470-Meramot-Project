<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * Feature R16: Platform Financial Summary & Double-Entry Ledger
 * ============================================================================
 * Assigned Developer: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Deliverable:
 * - Cumulative platform credits vs debits
 * - 10% platform commission accounting
 * - Immutable double-entry ledger journal
 * ============================================================================
 */
class FinancialLedger extends Model
{
    use HasFactory;

    protected $table = 'financial_ledgers';

    // Prevent direct modifications to existing records to preserve ledger immutability
    public static function boot()
    {
        parent::boot();

        static::updating(function ($entry) {
            // Immutable ledger entries cannot be altered after creation
            return false;
        });

        static::deleting(function ($entry) {
            // Immutable ledger entries cannot be deleted
            return false;
        });
    }

    protected $fillable = [
        'shop_id',
        'payment_id',
        'invoice_id',
        'vendor_payout_id',
        'entry_type',
        'direction',
        'amount',
        'currency',
        'description',
        'effective_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'effective_at' => 'datetime',
    ];

    // Allowed Entry Types
    public const ENTRY_TYPES = [
        'CUSTOMER_PAYMENT'    => 'Customer Payment',
        'PLATFORM_COMMISSION' => 'Platform Commission (10%)',
        'VENDOR_EARNING'      => 'Vendor Net Earning',
        'VENDOR_PAYOUT'       => 'Vendor Payout Disbursed',
        'DELIVERY_FEE'        => 'Logistics Delivery Fee',
        'DELIVERY_PAYOUT'     => 'Rider Fee Disbursed',
        'REFUND'              => 'Customer Refund',
        'ADJUSTMENT'          => 'Manual Audit Adjustment',
    ];

    // Relationships
    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }

    public function payment()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_id');
    }

    public function vendorPayout()
    {
        return $this->belongsTo(VendorPayout::class, 'vendor_payout_id');
    }

    // Scopes for easy double-entry ledger calculations
    public function scopeCredits($query)
    {
        return $query->where('direction', 'CREDIT');
    }

    public function scopeDebits($query)
    {
        return $query->where('direction', 'DEBIT');
    }

    public function scopeCommissions($query)
    {
        return $query->where('entry_type', 'PLATFORM_COMMISSION');
    }
}

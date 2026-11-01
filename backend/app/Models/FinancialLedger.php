<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R16: Platform Financial Summary & Double-Entry Ledger
 * Assigned Developer: Md. Nawshad Zaman Khan [23301622]
 */
class FinancialLedger extends Model
{
    use HasFactory;

    protected $fillable = [
        'entry_type', // 'debit', 'credit'
        'account_category', // 'platform_revenue', 'vendor_payout', 'rider_fee', 'gateway_charge'
        'amount',
        'currency',
        'reference_type', // 'repair_order', 'payout', 'refund'
        'reference_id',
        'balance_after',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
    ];
}

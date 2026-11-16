<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Immutable audit & idempotency ledger entry for every spare-part inventory movement:
 * SALE_DEDUCT, ORDER_CANCEL_RESTORE, RETURN_RESTORE, or VENDOR_ADJUST.
 */
class SparePartStockMovement extends Model
{
    use HasFactory;

    public const TYPE_SALE_DEDUCT = 'SALE_DEDUCT';
    public const TYPE_ORDER_CANCEL_RESTORE = 'ORDER_CANCEL_RESTORE';
    public const TYPE_RETURN_RESTORE = 'RETURN_RESTORE';
    public const TYPE_VENDOR_ADJUST = 'VENDOR_ADJUST';

    protected $fillable = [
        'spare_part_listing_id',
        'repair_request_id',
        'actor_user_id',
        'spec_code',
        'movement_type',
        'quantity',
        'stock_before',
        'stock_after',
        'sold_after',
        'returned_after',
        'idempotency_key',
        'reason',
    ];

    protected $casts = [
        'spare_part_listing_id' => 'integer',
        'repair_request_id' => 'integer',
        'actor_user_id' => 'integer',
        'quantity' => 'integer',
        'stock_before' => 'integer',
        'stock_after' => 'integer',
        'sold_after' => 'integer',
        'returned_after' => 'integer',
    ];

    public function listing()
    {
        return $this->belongsTo(SparePartListing::class, 'spare_part_listing_id');
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }
}

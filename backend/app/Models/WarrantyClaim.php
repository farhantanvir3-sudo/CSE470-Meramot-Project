<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer warranty claim with uploaded photo evidence, anti-misuse telemetry,
 * and Platform Admin arbitration ruling (Replacement, Free Repair, Refund, or Reject with evidence).
 */
class WarrantyClaim extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'PENDING';
    public const STATUS_APPROVED_REFUND = 'APPROVED_REFUND';
    public const STATUS_APPROVED_REPLACEMENT = 'APPROVED_REPLACEMENT';
    public const STATUS_APPROVED_FREE_REPAIR = 'APPROVED_FREE_REPAIR';
    public const STATUS_REJECTED = 'REJECTED';

    public const DECISION_REFUND = 'REFUND';
    public const DECISION_REPLACEMENT = 'REPLACEMENT';
    public const DECISION_FREE_REPAIR = 'FREE_REPAIR';
    public const DECISION_REJECT = 'REJECT';

    protected $table = 'warranty_claims';

    protected $fillable = [
        'issued_warranty_id',
        'repair_request_id',
        'customer_id',
        'shop_id',
        'spare_part_listing_id',
        'claim_number',
        'claimed_quantity',
        'preferred_remedy',
        'customer_explanation',
        'customer_image_urls',
        'customer_image_hashes',
        'status',
        'admin_decision',
        'admin_reply',
        'admin_image_urls',
        'refund_amount',
        'replacement_movement_id',
        'followup_repair_request_id',
        'misuse_risk_score',
        'misuse_flags',
        'reviewed_by_admin_id',
        'reviewed_at',
    ];

    protected $casts = [
        'issued_warranty_id' => 'integer',
        'repair_request_id' => 'integer',
        'customer_id' => 'integer',
        'shop_id' => 'integer',
        'spare_part_listing_id' => 'integer',
        'claim_number' => 'integer',
        'claimed_quantity' => 'integer',
        'customer_image_urls' => 'array',
        'customer_image_hashes' => 'array',
        'admin_image_urls' => 'array',
        'refund_amount' => 'decimal:2',
        'replacement_movement_id' => 'integer',
        'followup_repair_request_id' => 'integer',
        'misuse_risk_score' => 'integer',
        'misuse_flags' => 'array',
        'reviewed_by_admin_id' => 'integer',
        'reviewed_at' => 'datetime',
    ];

    public function statusBadgeLabel(): string
    {
        return match ($this->status) {
            self::STATUS_APPROVED_REFUND => 'Approved • Refund Issued',
            self::STATUS_APPROVED_REPLACEMENT => 'Approved • Replacement Dispatched',
            self::STATUS_APPROVED_FREE_REPAIR => 'Approved • Free Repair Scheduled',
            self::STATUS_REJECTED => 'Rejected by Admin',
            default => 'Pending Admin Review',
        };
    }

    public function riskLevel(): string
    {
        $score = (int) ($this->misuse_risk_score ?? 0);
        if ($score >= 50) {
            return 'HIGH';
        }
        if ($score >= 25) {
            return 'MEDIUM';
        }
        return 'LOW';
    }

    public function issuedWarranty()
    {
        return $this->belongsTo(IssuedWarranty::class, 'issued_warranty_id');
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }

    public function sparePartListing()
    {
        return $this->belongsTo(SparePartListing::class, 'spare_part_listing_id');
    }

    public function reviewer()
    {
        return $this->belongsTo(User::class, 'reviewed_by_admin_id');
    }

    public function replacementMovement()
    {
        return $this->belongsTo(SparePartStockMovement::class, 'replacement_movement_id');
    }

    public function followupRepairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'followup_repair_request_id');
    }
}

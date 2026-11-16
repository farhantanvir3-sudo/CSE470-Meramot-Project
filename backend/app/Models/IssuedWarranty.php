<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Customer warranty certificate activated upon order completion (`COMPLETED`).
 * Supports unlimited repeatable claims within its bounded `expires_at` window.
 */
class IssuedWarranty extends Model
{
    use HasFactory;

    public const STATUS_ACTIVE = 'ACTIVE';
    public const STATUS_EXPIRED = 'EXPIRED';
    public const STATUS_REFUNDED_CLOSED = 'REFUNDED_CLOSED';

    protected $table = 'issued_warranties';

    protected $fillable = [
        'repair_request_id',
        'repair_job_id',
        'customer_id',
        'shop_id',
        'spare_part_listing_id',
        'spare_part_warranty_id',
        'line_key',
        'spec_code',
        'item_title',
        'item_type',
        'quantity',
        'unit_price',
        'coverage_type',
        'terms',
        'duration_days',
        'starts_at',
        'expires_at',
        'status',
        'claims_count',
        'approved_claims_count',
        'rejected_claims_count',
        'replacements_issued',
        'repairs_issued',
        'refunded_amount',
    ];

    protected $casts = [
        'repair_request_id' => 'integer',
        'repair_job_id' => 'integer',
        'customer_id' => 'integer',
        'shop_id' => 'integer',
        'spare_part_listing_id' => 'integer',
        'spare_part_warranty_id' => 'integer',
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'duration_days' => 'integer',
        'starts_at' => 'datetime',
        'expires_at' => 'datetime',
        'claims_count' => 'integer',
        'approved_claims_count' => 'integer',
        'rejected_claims_count' => 'integer',
        'replacements_issued' => 'integer',
        'repairs_issued' => 'integer',
        'refunded_amount' => 'decimal:2',
    ];

    public function isCurrentlyActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->expires_at !== null
            && now()->lte($this->expires_at);
    }

    public function canSubmitClaim(): bool
    {
        return $this->isCurrentlyActive();
    }

    public function daysRemaining(): int
    {
        if (!$this->isCurrentlyActive()) {
            return 0;
        }
        $hoursLeft = max(1, (int) now()->diffInHours($this->expires_at));
        return max(1, (int) ceil($hoursLeft / 24));
    }

    public function progressPercent(): int
    {
        if (!$this->starts_at || !$this->expires_at) {
            return 100;
        }
        $totalSec = max(1, $this->starts_at->diffInSeconds($this->expires_at));
        if (now()->lte($this->starts_at)) {
            return 0;
        }
        if (now()->gte($this->expires_at)) {
            return 100;
        }
        $elapsed = $this->starts_at->diffInSeconds(now());
        return (int) min(100, max(0, round(($elapsed / $totalSec) * 100)));
    }

    /**
     * @return array<int, string>
     */
    public function remedyBadges(): array
    {
        $meta = SparePartWarranty::metaForType($this->coverage_type);
        return SparePartWarranty::remediesForFlags(
            $meta['covers_replacement'],
            $meta['covers_free_repair'],
            $meta['covers_refund']
        );
    }

    public function coverageLabel(): string
    {
        return SparePartWarranty::metaForType($this->coverage_type)['label'];
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
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

    public function sparePartWarranty()
    {
        return $this->belongsTo(SparePartWarranty::class, 'spare_part_warranty_id');
    }

    public function claims()
    {
        return $this->hasMany(WarrantyClaim::class, 'issued_warranty_id')
            ->orderByDesc('claim_number')
            ->orderByDesc('id');
    }
}

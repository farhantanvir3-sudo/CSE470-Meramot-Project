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
        'parts_cost',
        'labor_cost',
        'final_quoted_amount',
        'final_quote_items',
        'parts_items',
        'has_warranty',
        'warranty_days',
        'customer_approved',
        'started_at',
        'completed_at',
    ];

    protected $casts = [
        'parts_cost' => 'decimal:2',
        'labor_cost' => 'decimal:2',
        'final_quoted_amount' => 'decimal:2',
        'final_quote_items' => 'array',
        'parts_items' => 'array',
        'has_warranty' => 'boolean',
        'warranty_days' => 'integer',
        'customer_approved' => 'boolean',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    /**
     * Resolve itemized parts breakdown for this repair job.
     */
    public function resolvedPartsItems(): array
    {
        $fallbackDays = $this->resolvedWarrantyDays();
        $fallbackHas = $this->hasWarrantyCoverage();

        if (is_array($this->parts_items)) {
            return Bid::normalizePartsList($this->parts_items, false, $fallbackDays, $fallbackHas);
        }

        if ($this->acceptedBid) {
            return $this->acceptedBid->resolvedPartsItems();
        }

        if (is_array($this->final_quote_items)) {
            $fromFinal = Bid::normalizePartsList($this->final_quote_items, true, $fallbackDays, $fallbackHas);
            if (!empty($fromFinal)) {
                return $fromFinal;
            }
        }

        $partsCost = round((float) ($this->parts_cost ?? 0), 2);
        return $partsCost > 0
            ? [[
                'name' => 'Replacement Hardware Components',
                'cost' => $partsCost,
                'has_warranty' => $fallbackHas,
                'warranty_days' => $fallbackDays,
            ]]
            : [];
    }

    /**
     * Resolve total parts subtotal in BDT.
     */
    public function resolvedPartsCost(): float
    {
        if ($this->parts_cost !== null) {
            return round((float) $this->parts_cost, 2);
        }

        if (is_array($this->parts_items)) {
            return round((float) collect($this->resolvedPartsItems())->sum('cost'), 2);
        }

        if ($this->acceptedBid) {
            return round((float) $this->acceptedBid->parts_cost, 2);
        }

        return round((float) collect($this->resolvedPartsItems())->sum('cost'), 2);
    }

    /**
     * Resolve technician labor charge in BDT.
     */
    public function resolvedLaborCost(): float
    {
        if ($this->labor_cost !== null) {
            return round((float) $this->labor_cost, 2);
        }

        if ($this->acceptedBid && $this->final_quoted_amount === null) {
            return round((float) $this->acceptedBid->labor_cost, 2);
        }

        $total = (float) ($this->final_quoted_amount ?? $this->acceptedBid?->proposed_cost ?? 0);
        return max(0.0, round($total - $this->resolvedPartsCost(), 2));
    }

    /**
     * Determine whether warranty coverage is enabled for this repair job.
     */
    public function hasWarrantyCoverage(): bool
    {
        if ($this->has_warranty !== null) {
            return (bool) $this->has_warranty && ((int) ($this->warranty_days ?? 0) > 0);
        }

        if ($this->acceptedBid) {
            return $this->acceptedBid->hasWarrantyCoverage();
        }

        return (int) ($this->warranty_days ?? 90) > 0;
    }

    /**
     * Resolve warranty coverage duration in days (0 when disabled).
     */
    public function resolvedWarrantyDays(): int
    {
        if ($this->has_warranty !== null) {
            return $this->has_warranty && ((int) ($this->warranty_days ?? 0) > 0)
                ? (int) $this->warranty_days
                : 0;
        }

        if ($this->acceptedBid) {
            return $this->acceptedBid->resolvedWarrantyDays();
        }

        return max(0, (int) ($this->warranty_days ?? 90));
    }

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

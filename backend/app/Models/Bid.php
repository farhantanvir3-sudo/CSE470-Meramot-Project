<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R12: Live Bidding Engine & Diagnostic Quote Generator
 * Assigned Developer: Farhan Tanvir [22299179]
 */
class Bid extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_request_id',
        'shop_id',
        'vendor_id',
        'parts_cost',
        'parts_items',
        'labor_cost',
        'proposed_cost',
        'estimated_days',
        'has_warranty',
        'warranty_days',
        'line_items', // breakdown of parts cost vs service labor
        'technician_note',
        'status', // 'ACTIVE', 'ACCEPTED', 'DECLINED', 'EXPIRED', 'WITHDRAWN'
    ];

    protected $casts = [
        'parts_cost' => 'decimal:2',
        'parts_items' => 'array',
        'labor_cost' => 'decimal:2',
        'proposed_cost' => 'decimal:2',
        'estimated_days' => 'integer',
        'has_warranty' => 'boolean',
        'warranty_days' => 'integer',
        'line_items' => 'array',
    ];

    /**
     * Normalize raw component line entries into `[['name' => string, 'cost' => float], ...]`.
     */
    public static function normalizePartsList(mixed $rawItems, bool $skipLabor = false): array
    {
        if (!is_array($rawItems)) {
            return [];
        }

        $normalized = [];
        foreach ($rawItems as $row) {
            if (!is_array($row)) {
                continue;
            }
            $type = strtolower((string) ($row['type'] ?? ''));
            $name = trim((string) ($row['name'] ?? $row['item'] ?? $row['title'] ?? ''));
            $cost = round(max(0.0, (float) ($row['cost'] ?? $row['amount'] ?? $row['price'] ?? 0)), 2);

            if ($skipLabor && ($type === 'labor' || stripos($name, 'labor') !== false)) {
                continue;
            }
            if ($name === '' && $cost <= 0.0) {
                continue;
            }

            $normalized[] = [
                'name' => $name !== '' ? $name : 'Replacement Part #' . (count($normalized) + 1),
                'cost' => $cost,
            ];
        }

        return $normalized;
    }

    /**
     * Return itemized parts breakdown for this bid (with legacy line_items / aggregate fallback).
     */
    public function resolvedPartsItems(): array
    {
        if (is_array($this->parts_items)) {
            return self::normalizePartsList($this->parts_items);
        }

        if (is_array($this->line_items)) {
            $fromLines = self::normalizePartsList($this->line_items, skipLabor: true);
            if (!empty($fromLines)) {
                return $fromLines;
            }
        }

        $partsCost = round((float) ($this->parts_cost ?? 0), 2);
        return $partsCost > 0
            ? [['name' => 'Component / Parts Estimate', 'cost' => $partsCost]]
            : [];
    }

    /**
     * Determine whether extended repair warranty is enabled for this quote.
     */
    public function hasWarrantyCoverage(): bool
    {
        $flag = $this->has_warranty === null
            ? ((int) ($this->warranty_days ?? 0) > 0)
            : (bool) $this->has_warranty;

        return $flag && ((int) ($this->warranty_days ?? 0) > 0);
    }

    /**
     * Return active warranty duration in days (0 when disabled).
     */
    public function resolvedWarrantyDays(): int
    {
        return $this->hasWarrantyCoverage() ? (int) ($this->warranty_days ?? 0) : 0;
    }

    /**
     * Build `RepairJob.final_quote_items` array mirroring this bid's itemized breakdown.
     */
    public function buildFinalQuoteItems(): array
    {
        $items = [];
        foreach ($this->resolvedPartsItems() as $part) {
            $items[] = [
                'title' => $part['name'],
                'amount' => (float) $part['cost'],
                'type' => 'part',
            ];
        }

        $laborCost = round((float) ($this->labor_cost ?? 0), 2);
        if ($laborCost > 0) {
            $items[] = [
                'title' => 'Technician Labor & Calibration',
                'amount' => $laborCost,
                'type' => 'labor',
            ];
        }

        return $items;
    }

    /**
     * Return JSON-safe payload for Vendor View / Edit quote forms.
     */
    public function toFrontendPayload(): array
    {
        return [
            'id' => $this->id,
            'shop_id' => (int) $this->shop_id,
            'parts_items' => $this->resolvedPartsItems(),
            'parts_cost' => round((float) ($this->parts_cost ?? 0), 2),
            'labor_cost' => round((float) ($this->labor_cost ?? 0), 2),
            'proposed_cost' => round((float) ($this->proposed_cost ?? 0), 2),
            'estimated_days' => (int) ($this->estimated_days ?? 2),
            'has_warranty' => $this->hasWarrantyCoverage(),
            'warranty_days' => $this->resolvedWarrantyDays(),
            'technician_note' => (string) ($this->technician_note ?? ''),
            'status' => (string) ($this->status ?? 'ACTIVE'),
        ];
    }

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class);
    }

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }
}

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
     * Normalize raw component line entries with per-part warranty into
     * `[['name' => string, 'cost' => float, 'has_warranty' => bool, 'warranty_days' => int], ...]`.
     */
    public static function normalizePartsList(
        mixed $rawItems,
        bool $skipLabor = false,
        int $fallbackWarrantyDays = 0,
        ?bool $fallbackHasWarranty = null
    ): array {
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

            if (array_key_exists('has_warranty', $row) || array_key_exists('warranty_configured', $row)) {
                $partHasWarranty = filter_var($row['has_warranty'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $rawDaysVal = $row['warranty_days'] ?? null;
                $rawDays = ($rawDaysVal !== null && $rawDaysVal !== '')
                    ? (int) $rawDaysVal
                    : ($fallbackWarrantyDays > 0 ? $fallbackWarrantyDays : 90);
                $partWarrantyDays = $partHasWarranty ? max(1, $rawDays) : 0;
            } elseif (array_key_exists('warranty_days', $row) && $row['warranty_days'] !== null && $row['warranty_days'] !== '') {
                $rawDays = max(0, (int) $row['warranty_days']);
                $partHasWarranty = $rawDays > 0;
                $partWarrantyDays = $rawDays;
            } else {
                $partHasWarranty = $fallbackHasWarranty ?? ($fallbackWarrantyDays > 0);
                $partWarrantyDays = $partHasWarranty ? max(1, $fallbackWarrantyDays ?: 90) : 0;
            }

            $normalized[] = [
                'name' => $name !== '' ? $name : 'Replacement Part #' . (count($normalized) + 1),
                'cost' => $cost,
                'has_warranty' => $partHasWarranty,
                'warranty_days' => $partWarrantyDays,
            ];
        }

        return $normalized;
    }

    /**
     * Aggregate per-part warranty state across a normalized parts list.
     */
    public static function summarizePartsWarranty(array $normalizedParts): array
    {
        $activeDays = [];
        foreach ($normalizedParts as $part) {
            if (!empty($part['has_warranty']) && (int) ($part['warranty_days'] ?? 0) > 0) {
                $activeDays[] = (int) $part['warranty_days'];
            }
        }

        $warrantiedCount = count($activeDays);
        $maxDays = $warrantiedCount > 0 ? max($activeDays) : 0;

        return [
            'has_warranty' => $maxDays > 0,
            'warranty_days' => $maxDays,
            'warrantied_count' => $warrantiedCount,
            'total_count' => count($normalizedParts),
        ];
    }

    /**
     * Format a concise display label summarizing per-part warranty coverage.
     */
    public static function formatPartsWarrantySummary(
        array $normalizedParts,
        bool $fallbackHasWarranty = false,
        int $fallbackDays = 0
    ): string {
        if (!empty($normalizedParts)) {
            $summary = self::summarizePartsWarranty($normalizedParts);
            $warrantiedCount = $summary['warrantied_count'];
            $totalCount = $summary['total_count'];
            $maxDays = $summary['warranty_days'];

            if ($warrantiedCount === 0) {
                return 'No Parts Warranty';
            }

            $distinctDays = [];
            foreach ($normalizedParts as $part) {
                if (!empty($part['has_warranty']) && (int) ($part['warranty_days'] ?? 0) > 0) {
                    $distinctDays[(int) $part['warranty_days']] = true;
                }
            }

            if ($warrantiedCount === $totalCount && count($distinctDays) === 1) {
                return "{$maxDays}-Day Warranty Included";
            }

            return "{$warrantiedCount}/{$totalCount} Parts Warrantied (up to {$maxDays}d)";
        }

        return ($fallbackHasWarranty && $fallbackDays > 0)
            ? "{$fallbackDays}-Day Warranty Included"
            : 'No Parts Warranty';
    }

    /**
     * Return itemized parts breakdown for this bid (with legacy line_items / aggregate fallback).
     */
    public function resolvedPartsItems(): array
    {
        $fallbackDays = $this->resolvedWarrantyDays();
        $fallbackHas = $this->hasWarrantyCoverage();

        if (is_array($this->parts_items)) {
            return self::normalizePartsList($this->parts_items, false, $fallbackDays, $fallbackHas);
        }

        if (is_array($this->line_items)) {
            $fromLines = self::normalizePartsList($this->line_items, true, $fallbackDays, $fallbackHas);
            if (!empty($fromLines)) {
                return $fromLines;
            }
        }

        $partsCost = round((float) ($this->parts_cost ?? 0), 2);
        return $partsCost > 0
            ? [[
                'name' => 'Component / Parts Estimate',
                'cost' => $partsCost,
                'has_warranty' => $fallbackHas,
                'warranty_days' => $fallbackDays,
            ]]
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
                'has_warranty' => (bool) $part['has_warranty'],
                'warranty_days' => (int) $part['warranty_days'],
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
        $parts = $this->resolvedPartsItems();
        $hasWarranty = $this->hasWarrantyCoverage();
        $warrantyDays = $this->resolvedWarrantyDays();

        return [
            'id' => $this->id,
            'shop_id' => (int) $this->shop_id,
            'parts_items' => $parts,
            'parts_cost' => round((float) ($this->parts_cost ?? 0), 2),
            'labor_cost' => round((float) ($this->labor_cost ?? 0), 2),
            'proposed_cost' => round((float) ($this->proposed_cost ?? 0), 2),
            'estimated_days' => (int) ($this->estimated_days ?? 2),
            'has_warranty' => $hasWarranty,
            'warranty_days' => $warrantyDays,
            'warranty_summary' => self::formatPartsWarrantySummary($parts, $hasWarranty, $warrantyDays),
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

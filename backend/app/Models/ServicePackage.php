<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R8: Direct Service Package Catalog & Unified Quickmatch Cart
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
 * Architectural Layer: Model (ServicePackage)
 * Associated Controller: ServicePackageController
 * View: frontend/views/packages/catalog.blade.php
 * ============================================================================
 */
class ServicePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'description',
        'device_category',
        'fixed_price',
        'estimated_turnaround_hours',
        'warranty_months',
        'inclusions',
        'is_popular',
        'is_active',
        'order_count',
    ];

    protected $casts = [
        'fixed_price' => 'decimal:2',
        'estimated_turnaround_hours' => 'integer',
        'warranty_months' => 'integer',
        'inclusions' => 'array',
        'is_popular' => 'boolean',
        'is_active' => 'boolean',
        'order_count' => 'integer',
    ];

    /**
     * Compatibility alias for Shanid's controller/views (`estimated_duration_hours`).
     */
    public function getEstimatedDurationHoursAttribute(): int
    {
        return (int) ($this->attributes['estimated_turnaround_hours'] ?? 24);
    }

    /**
     * Compatibility alias for Shanid's views (`included_items`).
     */
    public function getIncludedItemsAttribute(): array
    {
        return $this->inclusions ?? [];
    }
}

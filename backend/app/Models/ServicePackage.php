<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R8: Direct Service Package Catalog & Shopping Cart Flow
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
 * Architectural Layer: Model (ServicePackage)
 * Associated Controller: ServicePackageController
 * View: resources/views/packages/catalog.blade.php
 * ============================================================================
 */
class ServicePackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'device_category',
        'fixed_price',
        'estimated_turnaround_hours',
        'warranty_months',
        'inclusions',
        'is_popular',
    ];

    protected $casts = [
        'fixed_price' => 'decimal:2',
        'inclusions' => 'array',
        'is_popular' => 'boolean',
    ];
}

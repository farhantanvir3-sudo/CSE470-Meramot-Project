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
        'vendor_id',
        'proposed_cost',
        'estimated_days',
        'warranty_days',
        'line_items', // breakdown of parts cost vs service labor
        'technician_note',
        'status', // 'submitted', 'accepted', 'rejected', 'expired'
    ];

    protected $casts = [
        'proposed_cost' => 'decimal:2',
        'line_items' => 'array',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class);
    }

    public function vendor()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_id');
    }
}

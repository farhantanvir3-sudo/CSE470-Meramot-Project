<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * ============================================================================
 * FEATURE R7: Customer Repair Request Posting & Dual-Quote Lifecycle Tracking
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Merged Lifecycle: Farhan Tanvir [22299179] (Dual-Path & Dual-Quote Flow)
 * View: frontend/views/requests/create.blade.php & track.blade.php
 * ============================================================================
 */
class RepairRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'source',
        'requested_shop_id',
        'requested_service_id',
        'title',
        'description',
        'device_type',
        'brand',
        'model_name',
        'serial_number',
        'issue_category',
        'problem',
        'image_urls',
        'mode',
        'status',
        'preferred_pickup',
        'preferred_service_type',
        'delivery_type',
        'contact_phone',
        'pickup_address',
        'dropoff_address',
        'checkup_fee',
        'quoted_final_amount',
        'ai_summary',
        'selected_bid_id',
        'approved_at',
        'rejected_at',
        // Virtual attribute aliases (mapped cleanly to PostgreSQL columns via mutators below)
        'issue_title',
        'issue_description',
        'photos',
        'ai_diagnostic_summary',
    ];

    protected $casts = [
        'image_urls' => 'array',
        'preferred_pickup' => 'boolean',
        'checkup_fee' => 'decimal:2',
        'quoted_final_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // 16-state Dual-Path + Dual-Quote Status Constants
    const STATUS_DRAFT = 'DRAFT';
    const STATUS_PENDING = 'PENDING';
    const STATUS_BIDDING = 'BIDDING';
    const STATUS_ASSIGNED = 'ASSIGNED';
    const STATUS_PICKUP_SCHEDULED = 'PICKUP_SCHEDULED';
    const STATUS_PICKED_UP = 'PICKED_UP';
    const STATUS_AT_SHOP = 'AT_SHOP';
    const STATUS_DIAGNOSING = 'DIAGNOSING';
    const STATUS_WAITING_APPROVAL = 'WAITING_APPROVAL';
    const STATUS_REPAIRING = 'REPAIRING';
    const STATUS_RETURN_SCHEDULED = 'RETURN_SCHEDULED';
    const STATUS_RETURNING = 'RETURNING';
    const STATUS_RETURNED_TO_CUSTOMER = 'RETURNED_TO_CUSTOMER';
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_REJECTED = 'REJECTED';

    /**
     * Valid status transitions (Merged R7 + Dual-Quote Lifecycle)
     */
    public static array $validTransitions = [
        self::STATUS_DRAFT => [self::STATUS_PENDING, self::STATUS_BIDDING, self::STATUS_CANCELLED],
        // Direct Orders enter at PENDING -> Vendor Accepts (ASSIGNED) or Declines (REJECTED)
        self::STATUS_PENDING => [self::STATUS_BIDDING, self::STATUS_ASSIGNED, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_BIDDING => [self::STATUS_ASSIGNED, self::STATUS_CANCELLED],
        self::STATUS_ASSIGNED => [self::STATUS_PICKUP_SCHEDULED, self::STATUS_AT_SHOP, self::STATUS_CANCELLED],
        self::STATUS_PICKUP_SCHEDULED => [self::STATUS_PICKED_UP, self::STATUS_CANCELLED],
        self::STATUS_PICKED_UP => [self::STATUS_AT_SHOP],
        self::STATUS_AT_SHOP => [self::STATUS_DIAGNOSING, self::STATUS_REPAIRING],
        self::STATUS_DIAGNOSING => [self::STATUS_WAITING_APPROVAL, self::STATUS_REPAIRING],
        // Quote 2 (Final Bench Diagnosis): Approve -> REPAIRING, Decline -> RETURN_SCHEDULED
        self::STATUS_WAITING_APPROVAL => [self::STATUS_REPAIRING, self::STATUS_RETURN_SCHEDULED, self::STATUS_CANCELLED],
        self::STATUS_REPAIRING => [self::STATUS_RETURN_SCHEDULED, self::STATUS_RETURNING, self::STATUS_COMPLETED],
        self::STATUS_RETURN_SCHEDULED => [self::STATUS_RETURNING],
        self::STATUS_RETURNING => [self::STATUS_COMPLETED, self::STATUS_RETURNED_TO_CUSTOMER],
        self::STATUS_RETURNED_TO_CUSTOMER => [],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_REJECTED => [],
    ];

    /* ------------------------------------------------------------------------
     * Transparent Mutators & Accessors (Bridges Shanid's attribute names with
     * the PostgreSQL `repair_requests` schema columns)
     * ------------------------------------------------------------------------ */
    public function setIssueTitleAttribute($value): void
    {
        $this->attributes['title'] = $value;
    }

    public function getIssueTitleAttribute(): ?string
    {
        return $this->attributes['title'] ?? null;
    }

    public function setIssueDescriptionAttribute($value): void
    {
        $this->attributes['problem'] = $value;
        if (empty($this->attributes['description'])) {
            $this->attributes['description'] = $value;
        }
    }

    public function getIssueDescriptionAttribute(): ?string
    {
        return $this->attributes['problem'] ?? $this->attributes['description'] ?? null;
    }

    public function setPhotosAttribute($value): void
    {
        $this->attributes['image_urls'] = is_string($value) || is_null($value)
            ? $value
            : json_encode($value);
    }

    public function getPhotosAttribute(): ?array
    {
        return $this->image_urls;
    }

    public function setAiDiagnosticSummaryAttribute($value): void
    {
        $this->attributes['ai_summary'] = $value;
    }

    public function getAiDiagnosticSummaryAttribute(): ?string
    {
        return $this->attributes['ai_summary'] ?? null;
    }

    public function transitionTo(string $newStatus): bool
    {
        $currentStatus = strtoupper($this->status ?? self::STATUS_PENDING);
        if ($currentStatus === 'BIDDING_OPEN') {
            $currentStatus = self::STATUS_BIDDING;
        }
        $newStatus = strtoupper($newStatus);

        $allowedTransitions = self::$validTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowedTransitions, true)) {
            Log::warning('Invalid status transition attempted', [
                'repair_request_id' => $this->id,
                'from' => $currentStatus,
                'to' => $newStatus,
            ]);
            return false;
        }

        $this->update(['status' => $newStatus]);
        return true;
    }

    public function canTransitionTo(string $status): bool
    {
        $currentStatus = strtoupper($this->status ?? self::STATUS_PENDING);
        $status = strtoupper($status);

        $allowedTransitions = self::$validTransitions[$currentStatus] ?? [];
        return in_array($status, $allowedTransitions, true);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function requestedShop()
    {
        return $this->belongsTo(RepairShop::class, 'requested_shop_id');
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function selectedBid()
    {
        return $this->belongsTo(Bid::class, 'selected_bid_id');
    }

    public function repairJob()
    {
        return $this->hasOne(RepairJob::class);
    }

    public function payments()
    {
        return $this->hasMany(PaymentTransaction::class);
    }
}

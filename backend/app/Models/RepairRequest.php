<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R7: Customer Repair Request Posting & Lifecycle Tracking
 * Assigned Developer: Shanid Sajjatuz Islam [23201465]
 */
class RepairRequest extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'device_type',
        'brand',
        'model_name',
        'serial_number',
        'issue_title',
        'issue_description',
        'photos',
        'status', // 'pending_review', 'bidding_open', 'assigned', 'in_repair', 'repaired', 'delivered', 'cancelled'
        'preferred_service_type', // 'pickup_dropoff', 'walk_in', 'technician_home_visit'
        'ai_diagnostic_summary',
        'pickup_address',
        'selected_bid_id',
        'quoted_final_amount',
        'approved_at',
        'rejected_at',
    ];

    protected $casts = [
        'photos' => 'array',
        'quoted_final_amount' => 'decimal:2',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    // Status constants
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
    const STATUS_COMPLETED = 'COMPLETED';
    const STATUS_CANCELLED = 'CANCELLED';
    const STATUS_REJECTED = 'REJECTED';

    /**
     * Valid status transitions
     */
    private static $validTransitions = [
        self::STATUS_DRAFT => [self::STATUS_PENDING, self::STATUS_CANCELLED],
        self::STATUS_PENDING => [self::STATUS_BIDDING, self::STATUS_REJECTED, self::STATUS_CANCELLED],
        self::STATUS_BIDDING => [self::STATUS_ASSIGNED, self::STATUS_CANCELLED],
        self::STATUS_ASSIGNED => [self::STATUS_PICKUP_SCHEDULED, self::STATUS_AT_SHOP, self::STATUS_CANCELLED],
        self::STATUS_PICKUP_SCHEDULED => [self::STATUS_PICKED_UP, self::STATUS_CANCELLED],
        self::STATUS_PICKED_UP => [self::STATUS_AT_SHOP],
        self::STATUS_AT_SHOP => [self::STATUS_DIAGNOSING, self::STATUS_REPAIRING],
        self::STATUS_DIAGNOSING => [self::STATUS_WAITING_APPROVAL, self::STATUS_REPAIRING],
        self::STATUS_WAITING_APPROVAL => [self::STATUS_REPAIRING, self::STATUS_CANCELLED],
        self::STATUS_REPAIRING => [self::STATUS_RETURN_SCHEDULED, self::STATUS_COMPLETED],
        self::STATUS_RETURN_SCHEDULED => [self::STATUS_RETURNING],
        self::STATUS_RETURNING => [self::STATUS_COMPLETED],
        self::STATUS_COMPLETED => [],
        self::STATUS_CANCELLED => [],
        self::STATUS_REJECTED => [],
    ];

    /**
     * Transition to a new status with validation
     */
    public function transitionTo(string $newStatus): bool
    {
        $currentStatus = strtoupper($this->status ?? self::STATUS_PENDING);
        $newStatus = strtoupper($newStatus);

        $allowedTransitions = self::$validTransitions[$currentStatus] ?? [];

        if (!in_array($newStatus, $allowedTransitions)) {
            \Log::warning("Invalid status transition attempted", [
                'repair_request_id' => $this->id,
                'from' => $currentStatus,
                'to' => $newStatus,
            ]);
            return false;
        }

        $this->update(['status' => $newStatus]);
        return true;
    }

    /**
     * Check if can transition to a specific status
     */
    public function canTransitionTo(string $status): bool
    {
        $currentStatus = strtoupper($this->status ?? self::STATUS_PENDING);
        $status = strtoupper($status);
        
        $allowedTransitions = self::$validTransitions[$currentStatus] ?? [];
        return in_array($status, $allowedTransitions);
    }

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function selectedBid()
    {
        return $this->belongsTo(Bid::class, 'selected_bid_id');
    }
}

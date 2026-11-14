<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R19: Order Dispute & Governance Case
 * Assigned Developer: Md. Nawshad Zaman Khan [Student ID: 23301622]
 */
class DisputeCase extends Model
{
    use HasFactory;

    protected $fillable = [
        'repair_request_id',
        'repair_job_id',
        'payment_id',
        'opened_by_id',
        'against_id',
        'assigned_admin_id',
        'status', // 'OPEN', 'INVESTIGATING', 'WAITING_EVIDENCE', 'WAITING_RESPONSE', 'RESOLVED', 'REFUNDED', 'PARTIALLY_REFUNDED', 'REJECTED'
        'resolution',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function repairRequest()
    {
        return $this->belongsTo(RepairRequest::class, 'repair_request_id');
    }

    public function repairJob()
    {
        return $this->belongsTo(RepairJob::class, 'repair_job_id');
    }

    public function payment()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_id');
    }

    public function openedBy()
    {
        return $this->belongsTo(User::class, 'opened_by_id');
    }

    public function against()
    {
        return $this->belongsTo(User::class, 'against_id');
    }

    public function assignedAdmin()
    {
        return $this->belongsTo(User::class, 'assigned_admin_id');
    }

    public function notes()
    {
        return $this->hasMany(DisputeNote::class, 'dispute_case_id');
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class, 'dispute_case_id');
    }
}

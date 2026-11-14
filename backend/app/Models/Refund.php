<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Refund extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_id',
        'dispute_case_id',
        'approved_by_id',
        'amount',
        'reason',
        'status', // 'PENDING', 'APPROVED', 'PROCESSED', 'REJECTED'
        'processed_at',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function payment()
    {
        return $this->belongsTo(PaymentTransaction::class, 'payment_id');
    }

    public function disputeCase()
    {
        return $this->belongsTo(DisputeCase::class, 'dispute_case_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }
}

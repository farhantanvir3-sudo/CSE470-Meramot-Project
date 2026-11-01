<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Feature R20: System Audit Logging & Security Compliance Monitor
 * Assigned Developer: Md. Nawshad Zaman Khan [23301622]
 */
class AuditLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'event_action', // e.g., 'vendor_approved', 'bid_accepted', 'payment_verified'
        'ip_address',
        'user_agent',
        'payload_before',
        'payload_after',
        'is_suspicious',
    ];

    protected $casts = [
        'payload_before' => 'array',
        'payload_after' => 'array',
        'is_suspicious' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}

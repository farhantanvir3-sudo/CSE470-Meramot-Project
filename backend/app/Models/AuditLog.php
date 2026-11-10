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
        'actor_id',
        'action', // e.g., 'vendor_approved', 'bid_accepted', 'payment_verified'
        'target_resource',
        'payload_before',
        'payload_after',
        'ip_address',
    ];

    protected $casts = [
        'payload_before' => 'array',
        'payload_after' => 'array',
    ];

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R5: Real-Time Admin-to-Rider WebSocket Support Chat
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
 * Architectural Layer: Model (RiderChatMessage)
 * Associated Controller: RiderChatController
 * View: resources/views/support/rider-chat.blade.php
 * ============================================================================
 */
class RiderChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'delivery_job_id',
        'sender_user_id',
        'sender_role',
        'recipient_user_id',
        'message',
    ];

    public function deliveryJob()
    {
        return $this->belongsTo(DeliveryJob::class, 'delivery_job_id');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_user_id');
    }
}

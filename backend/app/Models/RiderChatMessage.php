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
        'delivery_agent_id',
        'sender_role', // 'admin', 'rider'
        'message_text',
        'attachment_url',
        'is_read',
    ];

    protected $casts = [
        'is_read' => 'boolean',
    ];

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R10: AI-Powered Repair Diagnostic Conversational Consultant
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
 * Architectural Layer: Model (AIChatSession)
 * Associated Controller: AIChatController
 * View: resources/views/ai-consultant/chat.blade.php
 * ============================================================================
 */
class AIChatSession extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'session_token',
        'device_category',
        'conversation_history', // JSON array of messages between customer & AI consultant
        'is_resolved',
    ];

    protected $casts = [
        'conversation_history' => 'array',
        'is_resolved' => 'boolean',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'customer_id');
    }
}

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
 * Architectural Layer: Model (AIChatMessage)
 * Associated Controller: AIChatController
 * ============================================================================
 */
class AIChatMessage extends Model
{
    use HasFactory;

    protected $table = 'ai_chat_messages';

    protected $fillable = [
        'ai_chat_session_id',
        'role',
        'text',
    ];

    public function session()
    {
        return $this->belongsTo(AIChatSession::class, 'ai_chat_session_id');
    }
}

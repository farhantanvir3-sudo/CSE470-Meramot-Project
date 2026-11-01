<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * ============================================================================
 * FEATURE R19: Sitewide User Moderation & Content Governance Panel
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
 * Architectural Layer: Model (ModerationLog)
 * Associated Controller: AdminModerationController
 * View: resources/views/admin/moderation.blade.php
 * ============================================================================
 */
class ModerationLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'moderator_id',
        'target_user_id',
        'action_taken', // 'warn', 'suspend_7_days', 'ban_permanent', 'dismiss_report'
        'reason',
        'flagged_entity_type', // 'repair_request', 'shop_review', 'chat_message'
        'flagged_entity_id',
    ];

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_user_id');
    }
}

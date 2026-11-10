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
        'admin_id',
        'target_type', // 'shop', 'user', 'review'
        'target_id',
        'violation_type', // 'fraudulent_quote', 'abusive_chat', 'fake_review'
        'action_taken', // 'suspended', 'warned', 'content_removed'
        'reason',
    ];

    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }

    public function targetUser()
    {
        return $this->belongsTo(User::class, 'target_id');
    }
}

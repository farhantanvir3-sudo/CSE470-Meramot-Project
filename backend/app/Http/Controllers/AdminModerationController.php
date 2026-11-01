<?php

namespace App\Http\Controllers;

use App\Models\ModerationLog;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R19: Sitewide User Moderation & Content Governance Panel
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
 * Architectural Layer: Controller (AdminModerationController)
 * Models Touched: ModerationLog, Flagged
 * View Rendered: resources/views/admin/moderation.blade.php
 *
 * INSTRUCTIONS FOR NAWSHAD:
 * 1. Implement index(): View flagged reviews, abusive messages, or fake requests.
 * 2. Implement applySanction(Request $request): Suspend or ban offending users,
 *    updating User `is_active` to false and logging audit rationale.
 * ============================================================================
 */
class AdminModerationController extends Controller
{
    /**
     * Show moderation and content governance dashboard.
     * Route: GET /admin/moderation
     */
    public function index()
    {
        // TODO (Nawshad): Load moderation records with target user info
        $logs = ModerationLog::with('targetUser')->latest()->paginate(20);

        return view('admin.moderation', compact('logs'));
    }

    /**
     * Apply governance sanction on offending user.
     * Route: POST /admin/moderation/sanction
     */
    public function applySanction(Request $request)
    {
        $validated = $request->validate([
            'target_user_id' => 'required|exists:users,id',
            'action_taken' => 'required|in:warn,suspend_7_days,ban_permanent',
            'reason' => 'required|string|min:5',
        ]);

        $log = ModerationLog::create(array_merge($validated, [
            'moderator_id' => $request->user()->id ?? 1,
        ]));

        if (in_array($validated['action_taken'], ['suspend_7_days', 'ban_permanent'])) {
            User::where('id', $validated['target_user_id'])->update(['is_active' => false]);
        }

        return redirect()->back()->with('success', 'Moderation sanction applied successfully.');
    }
}

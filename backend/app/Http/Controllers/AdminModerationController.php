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
        $logs = ModerationLog::with('admin')->latest()->paginate(20);

        return view('admin.moderation', compact('logs'));
    }

    /**
     * Apply governance sanction on offending user (Sprint 3 — routes to Coming Soon during Sprint 1).
     * Route: POST /admin/moderation/sanction
     */
    public function applySanction(Request $request)
    {
        return app(ComingSoonController::class)->show($request, 'content-moderation');
    }
}

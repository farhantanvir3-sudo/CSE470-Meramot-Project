<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R20: System Audit Logging & Security Compliance Monitor
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
 * Architectural Layer: Controller (AuditLogController)
 * Models Touched: AuditLog
 * View Rendered: resources/views/admin/audit-logs.blade.php
 *
 * INSTRUCTIONS FOR NAWSHAD:
 * 1. Implement index(): View immutable audit trail with IP address tracking,
 *    suspicious flag filters, and actor user info.
 * 2. Implement logEvent($userId, $action, $before, $after): Security compliance
 *    helper executed upon state-altering actions (e.g. payout, role change).
 * ============================================================================
 */
class AuditLogController extends Controller
{
    /**
     * Display system security and compliance audit trail.
     * Route: GET /admin/audit-logs
     */
    public function index(Request $request)
    {
        $logs = AuditLog::with('actor')->latest()->paginate(25);

        return view('admin.audit-logs', compact('logs'));
    }
}

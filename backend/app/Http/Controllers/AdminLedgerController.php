<?php

namespace App\Http\Controllers;

use App\Models\FinancialLedger;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R16: Platform Financial Summary & Double-Entry Ledger
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 * Architectural Layer: Controller (AdminLedgerController)
 * Models Touched: FinancialLedger, Transaction
 * View Rendered: resources/views/admin/financial-overview.blade.php
 *
 * INSTRUCTIONS FOR NAWSHAD:
 * 1. Implement index(): Query total platform revenues (10% vendor commission),
 *    disbursed rider fees, pending escrow balances, and net revenue.
 * 2. Implement logEntry($type, $amount, $category, $referenceId): Helper method
 *    ensuring strict double-entry ledger bookkeeping.
 * ============================================================================
 */
class AdminLedgerController extends Controller
{
    /**
     * Display platform financial summary and audit ledger.
     * Route: GET /admin/financial-overview
     */
    public function index()
    {
        // TODO (Nawshad): Compute total credit vs debit sums
        $entries = FinancialLedger::latest()->paginate(20);
        $totalCredits = FinancialLedger::where('entry_type', 'credit')->sum('amount');
        $totalDebits = FinancialLedger::where('entry_type', 'debit')->sum('amount');
        $netPlatformBalance = $totalCredits - $totalDebits;

        return view('admin.financial-overview', compact('entries', 'totalCredits', 'totalDebits', 'netPlatformBalance'));
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\VendorPayout;
use App\Models\VendorProfile;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R18: Automated Vendor Payout & Net Commission Settlement
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
 * Architectural Layer: Controller (VendorPayoutController)
 * Models Touched: VendorPayout, Settlement
 * View Rendered: resources/views/admin/payouts/manage.blade.php
 *
 * INSTRUCTIONS FOR NAWSHAD:
 * 1. Implement index(): List all vendor payout requests with net calculation
 *    (Gross minus 10% platform commission).
 * 2. Implement disbursePayout($id): Mark payout as 'disbursed', record debit in
 *    FinancialLedger, and trigger bKash/Bank API disbursement.
 * ============================================================================
 */
class VendorPayoutController extends Controller
{
    /**
     * Display pending vendor payout requests for admin settlement.
     * Route: GET /admin/payouts/manage
     */
    public function index()
    {
        // TODO (Nawshad): Fetch pending payouts with vendor details
        $payouts = VendorPayout::with('vendor')->latest()->get();

        return view('admin.payouts.manage', compact('payouts'));
    }

    /**
     * Disburse vendor payout settlement.
     * Route: POST /admin/payouts/{id}/disburse
     */
    public function disbursePayout($id)
    {
        $payout = VendorPayout::findOrFail($id);
        $payout->update([
            'status' => 'disbursed',
            'disbursed_at' => now(),
        ]);

        return redirect()->back()->with('success', "Payout {$payout->id} disbursed successfully.");
    }
}

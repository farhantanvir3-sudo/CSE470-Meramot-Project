<?php

namespace App\Http\Controllers;

use App\Models\RiderEarnings;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R3: Delivery Agent Earnings Ledger & Payout Tracker
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
 * Architectural Layer: Controller (RiderLedgerController)
 * Models Touched: RiderEarnings, Payout
 * View Rendered: resources/views/delivery/earnings.blade.php
 *
 * INSTRUCTIONS FOR NAFIUL:
 * 1. Implement index(): Calculate cumulative earnings, unsettled balance, and
 *    list of completed delivery payouts for the logged-in rider.
 * 2. Implement requestPayout(): Allow rider to trigger payout request if
 *    unsettled balance is >= 500 BDT.
 * ============================================================================
 */
class RiderLedgerController extends Controller
{
    /**
     * Display rider earnings overview and payment breakdown.
     * Route: GET /delivery/earnings
     */
    public function index(Request $request)
    {
        $agentId = $request->user()?->deliveryAgent?->id;

        $earnings = $agentId
            ? RiderEarnings::where('delivery_agent_id', $agentId)->latest()->get()
            : collect();
        $totalEarned = $earnings->sum('total_earned');
        $unsettledBalance = $earnings->where('payout_status', 'unpaid')->sum('total_earned');

        return view('delivery.earnings', compact('earnings', 'totalEarned', 'unsettledBalance'));
    }

    /**
     * Request payout withdrawal for rider (Sprint 3 — routes to Coming Soon during Sprint 1).
     * Route: POST /delivery/payout/request
     */
    public function requestPayout(Request $request)
    {
        return app(ComingSoonController::class)->show($request, 'rider-earnings-payout');
    }
}

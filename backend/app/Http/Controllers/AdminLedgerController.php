<?php

namespace App\Http\Controllers;

use App\Models\FinancialLedger;
use App\Models\RepairShop;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R16: Platform Financial Summary & Double-Entry Ledger
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 * Architectural Layer: Controller (AdminLedgerController)
 * Models Touched: FinancialLedger, RepairShop, PaymentTransaction
 * View Rendered: resources/views/admin/financial-overview.blade.php
 *
 * DELIVERABLES IMPLEMENTED:
 * 1. Cumulative platform credits vs debits bookkeeping.
 * 2. 10% platform commission accounting and analytics.
 * 3. Immutable double-entry ledger journal with running balance calculation.
 * 4. Helper logEntry() for strict double-entry recording across the system.
 * ============================================================================
 */
class AdminLedgerController extends Controller
{
    /**
     * Display platform financial summary and immutable double-entry audit ledger.
     * Route: GET /admin/financial-overview
     */
    public function index(Request $request)
    {
        // 1. Overall cumulative totals for cards
        $totalCredits = (float) FinancialLedger::where('direction', 'CREDIT')->sum('amount');
        $totalDebits  = (float) FinancialLedger::where('direction', 'DEBIT')->sum('amount');
        $netPlatformBalance = $totalCredits - $totalDebits;

        // 2. 10% Platform Commission Accounting Metrics
        $totalCommissionRevenue = (float) FinancialLedger::where('entry_type', 'PLATFORM_COMMISSION')->sum('amount');
        $totalCustomerInflow    = (float) FinancialLedger::where('entry_type', 'CUSTOMER_PAYMENT')->sum('amount');
        $totalVendorDisbursed   = (float) FinancialLedger::where('entry_type', 'VENDOR_PAYOUT')->sum('amount');
        $totalRiderDisbursed    = (float) FinancialLedger::where('entry_type', 'DELIVERY_PAYOUT')->sum('amount');
        $totalRefunds           = (float) FinancialLedger::where('entry_type', 'REFUND')->sum('amount');

        // Escrow pending payout = (Customer inflow - refunds) - (Vendor payouts + Rider payouts + Platform commission)
        $pendingEscrowReserve = max(0, ($totalCustomerInflow - $totalRefunds) - ($totalVendorDisbursed + $totalRiderDisbursed + $totalCommissionRevenue));

        // 3. Filterable Query for Ledger Journal Table
        $query = FinancialLedger::with(['shop', 'payment'])->orderBy('id', 'asc');

        if ($request->filled('entry_type')) {
            $query->where('entry_type', $request->entry_type);
        }

        if ($request->filled('direction')) {
            $query->where('direction', strtoupper($request->direction));
        }

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('id', 'like', "%{$search}%");
            });
        }

        // Fetch all ordered ascending to compute exact sequential running balance
        $allEntries = $query->get();

        $runningBalance = 0;
        foreach ($allEntries as $entry) {
            if ($entry->direction === 'CREDIT') {
                $runningBalance += (float) $entry->amount;
            } else {
                $runningBalance -= (float) $entry->amount;
            }
            $entry->running_balance = $runningBalance;
        }

        // Paginate manually from the sequential collection (display latest first for admin convenience)
        $reversed = $allEntries->reverse();
        $page = (int) $request->get('page', 1);
        $perPage = 15;
        $paginatedEntries = new \Illuminate\Pagination\LengthAwarePaginator(
            $reversed->forPage($page, $perPage)->values(),
            $reversed->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        $shops = RepairShop::select('id', 'name')->orderBy('name')->get();

        return view('admin.financial-overview', [
            'entries'                => $paginatedEntries,
            'totalCredits'           => $totalCredits,
            'totalDebits'            => $totalDebits,
            'netPlatformBalance'     => $netPlatformBalance,
            'totalCommissionRevenue' => $totalCommissionRevenue,
            'totalCustomerInflow'    => $totalCustomerInflow,
            'totalVendorDisbursed'   => $totalVendorDisbursed,
            'totalRiderDisbursed'    => $totalRiderDisbursed,
            'pendingEscrowReserve'   => $pendingEscrowReserve,
            'totalRefunds'           => $totalRefunds,
            'shops'                  => $shops,
            'selectedEntryType'      => $request->entry_type,
            'selectedDirection'      => $request->direction,
            'selectedShop'           => $request->shop_id,
            'searchTerm'             => $request->search,
        ]);
    }

    /**
     * Helper to post double-entry ledger transactions immutably.
     * Accessible by PaymentGatewayController, VendorPayoutController, RiderLedgerController.
     *
     * @param string $entryType
     * @param string $direction ('CREDIT' or 'DEBIT')
     * @param float $amount
     * @param string|null $description
     * @param array $references ['shop_id', 'payment_id', 'invoice_id', 'vendor_payout_id']
     * @return FinancialLedger
     */
    public static function logEntry(
        string $entryType,
        string $direction,
        float $amount,
        ?string $description = null,
        array $references = []
    ): FinancialLedger {
        return FinancialLedger::create([
            'entry_type'       => strtoupper($entryType),
            'direction'        => strtoupper($direction),
            'amount'           => round($amount, 2),
            'currency'         => 'BDT',
            'description'      => $description,
            'shop_id'          => $references['shop_id'] ?? null,
            'payment_id'       => $references['payment_id'] ?? null,
            'invoice_id'       => $references['invoice_id'] ?? null,
            'vendor_payout_id' => $references['vendor_payout_id'] ?? null,
            'effective_at'     => now(),
        ]);
    }

    /**
     * Helper to process a completed order settlement with standard 10% platform commission:
     * 1. Customer Payment (CREDIT total gross)
     * 2. Platform Commission 10% (CREDIT platform commission)
     * 3. Vendor Earning 90% (DEBIT from pool / allocated to vendor)
     */
    public static function settleOrderWithCommission(
        float $grossAmount,
        int $shopId,
        ?int $paymentId = null,
        ?int $invoiceId = null,
        float $commissionRate = 0.10
    ): array {
        $commissionAmount = round($grossAmount * $commissionRate, 2);
        $vendorEarning = round($grossAmount - $commissionAmount, 2);

        // 1. Credit customer gross payment into platform account
        $paymentEntry = self::logEntry(
            'CUSTOMER_PAYMENT',
            'CREDIT',
            $grossAmount,
            "Customer settlement payment received for repair order (Shop #{$shopId})",
            ['shop_id' => $shopId, 'payment_id' => $paymentId, 'invoice_id' => $invoiceId]
        );

        // 2. Credit 10% platform commission
        $commissionEntry = self::logEntry(
            'PLATFORM_COMMISSION',
            'CREDIT',
            $commissionAmount,
            "10% platform service fee deducted (Gross: ৳{$grossAmount})",
            ['shop_id' => $shopId, 'payment_id' => $paymentId, 'invoice_id' => $invoiceId]
        );

        // 3. Debit vendor net earning to vendor escrow liability
        $vendorEntry = self::logEntry(
            'VENDOR_EARNING',
            'DEBIT',
            $vendorEarning,
            "Net earning allocated to vendor escrow (90% of ৳{$grossAmount})",
            ['shop_id' => $shopId, 'payment_id' => $paymentId, 'invoice_id' => $invoiceId]
        );

        return [
            'payment_entry'     => $paymentEntry,
            'commission_entry'  => $commissionEntry,
            'vendor_entry'      => $vendorEntry,
            'commission_amount' => $commissionAmount,
            'vendor_earning'    => $vendorEarning,
        ];
    }
}

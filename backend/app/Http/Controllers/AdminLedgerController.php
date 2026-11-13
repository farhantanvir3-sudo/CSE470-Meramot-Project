<?php

namespace App\Http\Controllers;

use App\Models\FinancialLedger;
use App\Models\PaymentTransaction;
use App\Models\RepairShop;
use App\Models\VendorPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * FEATURE R16: Platform Financial Summary & Double-Entry Ledger
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Commit by Nawshad: 4742c9c (feat(R16): implement platform financial summary
 *                    and double-entry ledger)
 * Merged with Original Meramot Financial Business Intelligence (`AdminFinancePage`):
 *   - Farhan Tanvir [Student ID: 22299179]
 *   - Fixed PostgreSQL `BIGINT` text search (`CAST(id AS TEXT) ILIKE`)
 *   - Added Revenue Trends & Fund Distribution analytics breakdown
 *   - Added interactive Manual Settlement & Batch Auto-Settlement actions
 * View Rendered (relocated to root `frontend/views/`):
 *   - frontend/views/admin/financial-overview.blade.php
 * ============================================================================
 */
class AdminLedgerController extends Controller
{
    /* ------------------------------------------------------------------------
     * BLOCK 1: Platform Financial Summary & Immutable Double-Entry Audit Ledger
     * Original Author: Md. Nawshad Zaman Khan [23301622] (Commit 4742c9c)
     * Enhanced with PostgreSQL-safe search & Original Meramot BI metrics
     * Route: GET /admin/financial-overview
     * ------------------------------------------------------------------------ */
    public function index(Request $request)
    {
        // 1. Overall cumulative totals for cards (Nawshad — Commit 4742c9c)
        $totalCredits = (float) FinancialLedger::where('direction', 'CREDIT')->sum('amount');
        $totalDebits  = (float) FinancialLedger::where('direction', 'DEBIT')->sum('amount');
        $netPlatformBalance = $totalCredits - $totalDebits;

        // 2. 10% Platform Commission Accounting Metrics (Nawshad — Commit 4742c9c)
        $totalCommissionRevenue = (float) FinancialLedger::where('entry_type', 'PLATFORM_COMMISSION')->sum('amount');
        $totalCustomerInflow    = (float) FinancialLedger::where('entry_type', 'CUSTOMER_PAYMENT')->sum('amount');
        $totalVendorDisbursed   = (float) FinancialLedger::where('entry_type', 'VENDOR_PAYOUT')->sum('amount');
        $totalVendorAllocated   = (float) FinancialLedger::where('entry_type', 'VENDOR_EARNING')->sum('amount');
        $totalRiderDisbursed    = (float) FinancialLedger::where('entry_type', 'DELIVERY_PAYOUT')->sum('amount');
        $totalRefunds           = (float) FinancialLedger::where('entry_type', 'REFUND')->sum('amount');

        // Escrow pending payout = (Customer inflow - refunds) - (Vendor payouts + Rider payouts + Platform commission)
        $pendingEscrowReserve = max(0, ($totalCustomerInflow - $totalRefunds) - ($totalVendorDisbursed + $totalRiderDisbursed + $totalCommissionRevenue));

        // Open settlements count (shops with positive unsettled vendor escrow balances)
        $pendingSettlementCount = self::countUnsettledShops();

        // Fund Distribution breakdown by entry_type (Ported from Original Meramot AdminFinancePage)
        $rawDistribution = FinancialLedger::select(
            'entry_type',
            DB::raw('COUNT(*) as entry_count'),
            DB::raw('SUM(amount) as total_amount')
        )
            ->groupBy('entry_type')
            ->orderByDesc('total_amount')
            ->get();

        $maxDistAmount = max(1.0, (float) ($rawDistribution->max('total_amount') ?? 1));
        $fundDistribution = $rawDistribution->map(function ($row) use ($maxDistAmount) {
            return [
                'type' => $row->entry_type,
                'label' => FinancialLedger::ENTRY_TYPES[$row->entry_type] ?? $row->entry_type,
                'count' => (int) $row->entry_count,
                'amount' => (float) $row->total_amount,
                'share_pct' => round(((float) $row->total_amount / $maxDistAmount) * 100, 1),
            ];
        });

        // 3. Filterable Query for Ledger Journal Table (Nawshad — Commit 4742c9c, fixed for PostgreSQL)
        $query = FinancialLedger::with(['shop', 'payment'])
            ->whereNull('hidden_at')
            ->orderBy('id', 'asc');

        if ($request->filled('entry_type')) {
            if ($request->entry_type === 'ADJUSTMENT') {
                $query->where(function ($q) {
                    $q->where('entry_type', 'ADJUSTMENT')
                      ->orWhere('description', 'ILIKE', '%[Manual Settlement]%')
                      ->orWhere(function ($mq) {
                          $mq->whereNull('payment_id')
                             ->whereNull('invoice_id')
                             ->where('description', 'ILIKE', '%Customer settlement payment received for repair order%');
                      });
                });
            } else {
                $query->where('entry_type', $request->entry_type);
            }
        }

        if ($request->filled('direction')) {
            $query->where('direction', strtoupper($request->direction));
        }

        if ($request->filled('shop_id')) {
            $query->where('shop_id', $request->shop_id);
        }

        if ($request->filled('search')) {
            $search = trim($request->search);
            $query->where(function ($q) use ($search) {
                $q->where('description', 'ILIKE', "%{$search}%")
                  ->orWhere('entry_type', 'ILIKE', "%{$search}%")
                  ->orWhereRaw('CAST(id AS TEXT) ILIKE ?', ["%{$search}%"])
                  ->orWhereHas('shop', function ($sq) use ($search) {
                      $sq->where('name', 'ILIKE', "%{$search}%");
                  });
            });
        }

        // Fetch all ordered ascending to compute exact sequential running balance (Nawshad — Commit 4742c9c)
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
            'entries'                 => $paginatedEntries,
            'totalCredits'            => $totalCredits,
            'totalDebits'             => $totalDebits,
            'netPlatformBalance'      => $netPlatformBalance,
            'totalCommissionRevenue'  => $totalCommissionRevenue,
            'totalCustomerInflow'     => $totalCustomerInflow,
            'totalVendorDisbursed'    => $totalVendorDisbursed,
            'totalVendorAllocated'    => $totalVendorAllocated,
            'totalRiderDisbursed'     => $totalRiderDisbursed,
            'pendingEscrowReserve'    => $pendingEscrowReserve,
            'pendingSettlementCount'  => $pendingSettlementCount,
            'totalRefunds'            => $totalRefunds,
            'fundDistribution'        => $fundDistribution,
            'shops'                   => $shops,
            'selectedEntryType'       => $request->entry_type,
            'selectedDirection'       => $request->direction,
            'selectedShop'            => $request->shop_id,
            'searchTerm'              => $request->search,
        ]);
    }

    /* ------------------------------------------------------------------------
     * BLOCK 2: Manual Single Order Settlement Action
     * Merged from Original Meramot `AdminFinancePage::handleSettleSingle`
     * Uses Nawshad's `settleOrderWithCommission()` helper.
     * Route: POST /admin/financial-overview/settle
     * ------------------------------------------------------------------------ */
    public function settleManual(Request $request)
    {
        $validated = $request->validate([
            'shop_id' => 'required|exists:repair_shops,id',
            'gross_amount' => 'required|numeric|min:50',
            'payment_id' => 'nullable|integer',
            'note' => 'nullable|string|max:300',
        ]);

        $shop = RepairShop::findOrFail($validated['shop_id']);
        $grossAmount = (float) $validated['gross_amount'];

        $customNote = !empty($validated['note'])
            ? '[Manual Settlement] ' . trim($validated['note'])
            : '[Manual Settlement] Admin manual order settlement';

        $result = self::settleOrderWithCommission(
            $grossAmount,
            (int) $shop->id,
            $validated['payment_id'] ?? null,
            null,
            0.10,
            $customNote
        );

        return redirect()
            ->route('admin.financial-overview')
            ->with(
                'success',
                "Manual settlement posted for {$shop->name}: Gross ৳" . number_format($grossAmount, 2)
                . " | 10% Commission ৳" . number_format($result['commission_amount'], 2)
                . " | Vendor Net ৳" . number_format($result['vendor_earning'], 2)
            );
    }

    /* ------------------------------------------------------------------------
     * BLOCK 3: Batch Escrow Payout Auto-Settlement Action
     * Merged from Original Meramot `AdminFinancePage::handleAutoSettle`
     * Disburses pending VENDOR_EARNING allocations into VENDOR_PAYOUT entries.
     * Route: POST /admin/financial-overview/auto-settle
     * ------------------------------------------------------------------------ */
    public function autoSettleBatch(Request $request)
    {
        $limit = max(1, min(50, (int) $request->input('limit', 20)));

        $unsettledShops = FinancialLedger::query()
            ->select(
                'shop_id',
                DB::raw("SUM(CASE WHEN entry_type = 'VENDOR_EARNING' THEN amount ELSE 0 END) as total_earned"),
                DB::raw("SUM(CASE WHEN entry_type = 'VENDOR_PAYOUT' THEN amount ELSE 0 END) as total_paid")
            )
            ->whereNotNull('shop_id')
            ->whereIn('entry_type', ['VENDOR_EARNING', 'VENDOR_PAYOUT'])
            ->groupBy('shop_id')
            ->orderBy('shop_id', 'asc')
            ->get()
            ->filter(fn ($row) => round((float) $row->total_earned - (float) $row->total_paid, 2) > 0.009)
            ->take($limit)
            ->values();

        $settledCount = 0;
        $totalDisbursed = 0.0;

        DB::transaction(function () use ($unsettledShops, &$settledCount, &$totalDisbursed) {
            foreach ($unsettledShops as $row) {
                $shopId = (int) $row->shop_id;
                $unsettledAmount = round((float) $row->total_earned - (float) $row->total_paid, 2);
                if ($unsettledAmount <= 0.009) {
                    continue;
                }

                $shop = RepairShop::find($shopId);
                $shopName = $shop?->name ?? "Shop #{$shopId}";

                $latestPaymentId = FinancialLedger::where('shop_id', $shopId)
                    ->where('entry_type', 'VENDOR_EARNING')
                    ->whereNotNull('payment_id')
                    ->latest('id')
                    ->value('payment_id');

                $matchingPendingPayout = VendorPayout::where('shop_id', $shopId)
                    ->where('status', 'PENDING')
                    ->whereBetween('amount', [$unsettledAmount - 0.02, $unsettledAmount + 0.02])
                    ->orderBy('id', 'asc')
                    ->first();

                if ($matchingPendingPayout) {
                    $matchingPendingPayout->update([
                        'amount' => $unsettledAmount,
                        'status' => 'PAID',
                        'paid_at' => now(),
                        'notes' => $matchingPendingPayout->notes ?: "Automated batch escrow settlement disbursed to {$shopName}",
                    ]);
                    $payout = $matchingPendingPayout;
                } else {
                    VendorPayout::where('shop_id', $shopId)
                        ->where('status', 'PENDING')
                        ->update([
                            'status' => 'CANCELLED',
                            'notes' => 'Superseded by full batch escrow settlement.',
                        ]);

                    $payout = VendorPayout::create([
                        'shop_id' => $shopId,
                        'amount' => $unsettledAmount,
                        'currency' => 'BDT',
                        'status' => 'PAID',
                        'reference_code' => 'PYT-AUTO-' . $shopId . '-' . strtoupper(Str::random(5)),
                        'notes' => "Automated batch escrow settlement disbursed to {$shopName}",
                        'paid_at' => now(),
                    ]);
                }

                self::logEntry(
                    'VENDOR_PAYOUT',
                    'DEBIT',
                    $unsettledAmount,
                    "Automated batch escrow settlement disbursed to {$shopName} (Ref {$payout->reference_code})",
                    [
                        'shop_id' => $shopId,
                        'payment_id' => $latestPaymentId,
                        'vendor_payout_id' => $payout->id,
                    ]
                );

                $settledCount++;
                $totalDisbursed += $unsettledAmount;
            }
        });

        if ($settledCount === 0) {
            return redirect()
                ->route('admin.financial-overview')
                ->with('success', 'Batch settlement check complete: All eligible vendor earning allocations have already been disbursed.');
        }

        return redirect()
            ->route('admin.financial-overview')
            ->with(
                'success',
                "Batch auto-settlement completed: Disbursed {$settledCount} vendor payout(s) totaling ৳" . number_format($totalDisbursed, 2) . "."
            );
    }

    /**
     * Bulk Delete or Undo Ledger Effects for selected audit/ledger entries.
     * Route: POST /admin/financial-overview/bulk-delete
     */
    public function bulkDelete(Request $request)
    {
        $validated = $request->validate([
            'entry_ids' => 'required|array|min:1',
            'entry_ids.*' => 'integer|exists:financial_ledgers,id',
            'delete_mode' => 'required|in:records_only,undo_effects',
        ]);

        $ids = array_values(array_unique(array_map('intval', $validated['entry_ids'])));
        $mode = $validated['delete_mode'];

        $affectedCount = 0;
        DB::transaction(function () use ($ids, $mode, &$affectedCount) {
            if ($mode === 'undo_effects') {
                $affectedCount = DB::table('financial_ledgers')
                    ->whereIn('id', $ids)
                    ->delete();
            } else {
                $affectedCount = DB::table('financial_ledgers')
                    ->whereIn('id', $ids)
                    ->update(['hidden_at' => now()]);
            }

            if (class_exists(\App\Models\AuditLog::class)) {
                \App\Models\AuditLog::create([
                    'actor_id' => auth()->id(),
                    'action' => $mode === 'undo_effects' ? 'ledger_entries_undone' : 'ledger_records_hidden',
                    'target_resource' => 'financial_ledgers',
                    'payload_before' => ['entry_ids' => $ids],
                    'payload_after' => ['mode' => $mode, 'affected_count' => $affectedCount],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                ]);
            }
        });

        $msg = $mode === 'undo_effects'
            ? "Deleted {$affectedCount} ledger record(s) and reversed their financial effects on totals and balances."
            : "Removed {$affectedCount} audit record(s) from the journal view while preserving ledger totals.";

        return redirect()
            ->route('admin.financial-overview')
            ->with('success', $msg);
    }

    /**
     * Count how many workshops currently hold a positive unsettled escrow balance.
     */
    public static function countUnsettledShops(): int
    {
        return FinancialLedger::query()
            ->select(
                'shop_id',
                DB::raw("SUM(CASE WHEN entry_type = 'VENDOR_EARNING' THEN amount ELSE 0 END) as total_earned"),
                DB::raw("SUM(CASE WHEN entry_type = 'VENDOR_PAYOUT' THEN amount ELSE 0 END) as total_paid")
            )
            ->whereNotNull('shop_id')
            ->whereIn('entry_type', ['VENDOR_EARNING', 'VENDOR_PAYOUT'])
            ->groupBy('shop_id')
            ->get()
            ->filter(fn ($row) => round((float) $row->total_earned - (float) $row->total_paid, 2) > 0.009)
            ->count();
    }

    /* ------------------------------------------------------------------------
     * BLOCK 4: Immutable Double-Entry Ledger Helper Methods
     * Original Author: Md. Nawshad Zaman Khan [23301622] (Commit 4742c9c)
     * ------------------------------------------------------------------------ */
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
     * Author: Md. Nawshad Zaman Khan [23301622] (Commit 4742c9c)
     */
    public static function settleOrderWithCommission(
        float $grossAmount,
        int $shopId,
        ?int $paymentId = null,
        ?int $invoiceId = null,
        float $commissionRate = 0.10,
        ?string $customNote = null
    ): array {
        $commissionAmount = round($grossAmount * $commissionRate, 2);
        $vendorEarning = round($grossAmount - $commissionAmount, 2);
        $noteSuffix = $customNote ? " — Note: {$customNote}" : '';

        // 1. Credit customer gross payment into platform account
        $paymentEntry = self::logEntry(
            'CUSTOMER_PAYMENT',
            'CREDIT',
            $grossAmount,
            "Customer settlement payment received for repair order (Shop #{$shopId}){$noteSuffix}",
            ['shop_id' => $shopId, 'payment_id' => $paymentId, 'invoice_id' => $invoiceId]
        );

        // 2. Credit 10% platform commission
        $commissionEntry = self::logEntry(
            'PLATFORM_COMMISSION',
            'CREDIT',
            $commissionAmount,
            "10% platform service fee deducted (Gross: ৳{$grossAmount}){$noteSuffix}",
            ['shop_id' => $shopId, 'payment_id' => $paymentId, 'invoice_id' => $invoiceId]
        );

        // 3. Debit vendor net earning to vendor escrow liability
        $vendorEntry = self::logEntry(
            'VENDOR_EARNING',
            'DEBIT',
            $vendorEarning,
            "Net earning allocated to vendor escrow (90% of ৳{$grossAmount}){$noteSuffix}",
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

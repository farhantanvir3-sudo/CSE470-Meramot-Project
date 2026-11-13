<?php

namespace App\Http\Controllers;

use App\Models\FinancialLedger;
use App\Models\PaymentTransaction;
use App\Models\RepairShop;
use App\Models\VendorPayout;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        // 1. Industry-Standard Treasury Cash Flows (External Cash Inflows vs Cash Outflows)
        // Sub-ledger allocations (PLATFORM_COMMISSION, VENDOR_EARNING) partition customer inflow
        // internally and must NOT be double-counted as separate cash credits/debits.
        $cashInflowTypes  = ['CUSTOMER_PAYMENT', 'DELIVERY_FEE'];
        $cashOutflowTypes = ['VENDOR_PAYOUT', 'DELIVERY_PAYOUT', 'REFUND'];

        $totalCredits = (float) FinancialLedger::where('direction', 'CREDIT')
            ->whereIn('entry_type', array_merge($cashInflowTypes, ['ADJUSTMENT']))
            ->sum('amount');

        $totalDebits = (float) FinancialLedger::where('direction', 'DEBIT')
            ->whereIn('entry_type', array_merge($cashOutflowTypes, ['ADJUSTMENT']))
            ->sum('amount');

        // Actual Cash Treasury Balance held by Platform (Commission Reserve + Unsettled Vendor Escrow + Delivery Float)
        $netPlatformBalance = round($totalCredits - $totalDebits, 2);

        // 2. 10% Platform Commission & Vendor Escrow Sub-Ledger Accounting
        $grossCommissionEarned  = (float) FinancialLedger::where('entry_type', 'PLATFORM_COMMISSION')->where('direction', 'CREDIT')->sum('amount');
        $commissionReversals    = (float) FinancialLedger::where('entry_type', 'PLATFORM_COMMISSION')->where('direction', 'DEBIT')->sum('amount');
        $totalCustomerInflow    = (float) FinancialLedger::where('entry_type', 'CUSTOMER_PAYMENT')->sum('amount');
        $totalVendorDisbursed   = (float) FinancialLedger::where('entry_type', 'VENDOR_PAYOUT')->sum('amount');
        $totalVendorAllocated   = (float) FinancialLedger::where('entry_type', 'VENDOR_EARNING')->sum('amount');
        $totalRiderDisbursed    = (float) FinancialLedger::where('entry_type', 'DELIVERY_PAYOUT')->sum('amount');
        $totalRefunds           = (float) FinancialLedger::where('entry_type', 'REFUND')->sum('amount');

        // Net Commission Revenue deducts the 10% commission share on any refunded orders
        $refundCommissionOffset = $commissionReversals > 0
            ? $commissionReversals
            : round($totalRefunds * 0.10, 2);
        $totalCommissionRevenue = max(0, round($grossCommissionEarned - $refundCommissionOffset, 2));

        // Vendor Escrow Payable = Net Allocated Earnings (after 90% refund clawbacks) minus Disbursed Payouts
        $refundVendorClawback   = round($totalRefunds * 0.90, 2);
        $netVendorPayable       = max(0, round($totalVendorAllocated - $refundVendorClawback, 2));
        $pendingEscrowReserve   = max(0, round($netVendorPayable - $totalVendorDisbursed, 2));

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
            ->when(Schema::hasColumn('financial_ledger', 'hidden_at'), fn ($q) => $q->whereNull('hidden_at'))
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

        // Fetch all ordered ascending to compute exact sequential Treasury Cash running balance.
        // Internal sub-ledger allocations (PLATFORM_COMMISSION, VENDOR_EARNING) partition existing
        // cash rather than moving cash in/out of the platform treasury.
        $allEntries = $query->get();

        $allocationTypes = ['PLATFORM_COMMISSION', 'VENDOR_EARNING'];
        $runningBalance = 0.0;
        foreach ($allEntries as $entry) {
            $isAllocation = in_array($entry->entry_type, $allocationTypes, true);
            $entry->is_allocation = $isAllocation;
            $entry->is_deletable_manual_audit = self::isManualAdjustedAudit($entry);

            if (!$isAllocation) {
                if ($entry->direction === 'CREDIT') {
                    $runningBalance += (float) $entry->amount;
                } else {
                    $runningBalance -= (float) $entry->amount;
                }
            }
            $entry->running_balance = round($runningBalance, 2);
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

        // Pending post-settlement disputes / orders eligible for Admin Refund
        $pendingRefundDisputes = DB::table('dispute_cases as dc')
            ->join('payment_transactions as pt', 'pt.id', '=', 'dc.payment_id')
            ->join('repair_requests as rr', 'rr.id', '=', 'dc.repair_request_id')
            ->join('users as cu', 'cu.id', '=', 'dc.opened_by_id')
            ->leftJoin('invoices as inv', 'inv.id', '=', 'pt.invoice_id')
            ->leftJoin('repair_shops as rs', 'rs.id', '=', 'inv.shop_id')
            ->whereIn('dc.status', ['OPEN', 'INVESTIGATING', 'WAITING_EVIDENCE', 'WAITING_RESPONSE'])
            ->where('pt.status', 'PAID')
            ->select(
                'dc.id as dispute_id',
                'dc.status as dispute_status',
                'dc.resolution as dispute_reason',
                'pt.id as payment_id',
                'pt.amount as paid_amount',
                'pt.payment_method',
                'pt.transaction_reference',
                'rr.title as order_title',
                'cu.name as customer_name',
                'rs.id as shop_id',
                'rs.name as shop_name'
            )
            ->orderByDesc('dc.id')
            ->get();

        $settledPayments = DB::table('payment_transactions as pt')
            ->leftJoin('users as u', 'u.id', '=', 'pt.user_id')
            ->leftJoin('invoices as inv', 'inv.id', '=', 'pt.invoice_id')
            ->leftJoin('repair_shops as rs', 'rs.id', '=', 'inv.shop_id')
            ->where('pt.status', 'PAID')
            ->select(
                'pt.id',
                'pt.amount',
                'pt.payment_method',
                'pt.transaction_reference',
                'pt.paid_at',
                'u.name as customer_name',
                'rs.id as shop_id',
                'rs.name as shop_name'
            )
            ->orderByDesc('pt.id')
            ->limit(20)
            ->get();

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
            'pendingRefundDisputes'   => $pendingRefundDisputes,
            'settledPayments'         => $settledPayments,
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
                DB::raw("SUM(CASE WHEN entry_type = 'VENDOR_PAYOUT' THEN amount ELSE 0 END) as total_paid"),
                DB::raw("SUM(CASE WHEN entry_type = 'REFUND' THEN amount * 0.90 ELSE 0 END) as total_refund_clawback")
            )
            ->whereNotNull('shop_id')
            ->whereIn('entry_type', ['VENDOR_EARNING', 'VENDOR_PAYOUT', 'REFUND'])
            ->groupBy('shop_id')
            ->orderBy('shop_id', 'asc')
            ->get()
            ->filter(fn ($row) => round((float) $row->total_earned - (float) $row->total_paid - (float) $row->total_refund_clawback, 2) > 0.009)
            ->take($limit)
            ->values();

        $settledCount = 0;
        $totalDisbursed = 0.0;

        DB::transaction(function () use ($unsettledShops, &$settledCount, &$totalDisbursed) {
            foreach ($unsettledShops as $row) {
                $shopId = (int) $row->shop_id;
                $unsettledAmount = round((float) $row->total_earned - (float) $row->total_paid - (float) $row->total_refund_clawback, 2);
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

        // Strictly allow deletion ONLY for manual adjusted settlement audits
        $eligibleIds = FinancialLedger::whereIn('id', $ids)
            ->where(function ($q) {
                $q->where('entry_type', 'ADJUSTMENT')
                  ->orWhere('description', 'ILIKE', '%[Manual Settlement]%')
                  ->orWhere(function ($mq) {
                      $mq->whereNull('payment_id')
                         ->whereNull('invoice_id')
                         ->where('description', 'ILIKE', '%Customer settlement payment received for repair order%');
                  });
            })
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        if (empty($eligibleIds)) {
            return redirect()
                ->route('admin.financial-overview')
                ->with('warning', 'Only manual adjusted settlement audits can be deleted. Automated order, payout, and refund ledger entries remain immutable.');
        }

        $skippedCount = count($ids) - count($eligibleIds);
        $affectedCount = 0;
        DB::transaction(function () use ($eligibleIds, $mode, &$affectedCount) {
            if ($mode === 'undo_effects') {
                $affectedCount = DB::table('financial_ledgers')
                    ->whereIn('id', $eligibleIds)
                    ->delete();
            } else {
                $affectedCount = DB::table('financial_ledgers')
                    ->whereIn('id', $eligibleIds)
                    ->update(['hidden_at' => now()]);
            }

            if (class_exists(\App\Models\AuditLog::class)) {
                \App\Models\AuditLog::create([
                    'actor_id' => auth()->id(),
                    'action' => $mode === 'undo_effects' ? 'ledger_entries_undone' : 'ledger_records_hidden',
                    'target_resource' => 'financial_ledgers',
                    'payload_before' => ['entry_ids' => $eligibleIds],
                    'payload_after' => ['mode' => $mode, 'affected_count' => $affectedCount],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                ]);
            }
        });

        $msg = $mode === 'undo_effects'
            ? "Deleted {$affectedCount} manual audit record(s) and reversed their financial effects on totals and balances."
            : "Removed {$affectedCount} manual audit record(s) from the journal view while preserving ledger totals.";

        if ($skippedCount > 0) {
            $msg .= " ({$skippedCount} immutable system ledger entry/entries skipped.)";
        }

        return redirect()
            ->route('admin.financial-overview')
            ->with('success', $msg);
    }

    public static function isManualAdjustedAudit(FinancialLedger $entry): bool
    {
        if ($entry->entry_type === 'ADJUSTMENT') {
            return true;
        }

        $desc = (string) ($entry->description ?? '');
        if (stripos($desc, '[Manual Settlement]') !== false) {
            return true;
        }

        return is_null($entry->payment_id)
            && is_null($entry->invoice_id)
            && stripos($desc, 'Customer settlement payment received for repair order') !== false;
    }

    /**
     * Issue a Customer Refund (full or partial) against a settled PaymentTransaction
     * and record the DEBIT entry in FinancialLedger + refunds + dispute_cases.
     * Route: POST /admin/financial-overview/issue-refund
     */
    public function issueRefund(Request $request)
    {
        $validated = $request->validate([
            'payment_id' => 'required|integer|exists:payment_transactions,id',
            'refund_amount' => 'required|numeric|min:1',
            'reason' => 'required|string|max:300',
            'dispute_case_id' => 'nullable|integer',
        ]);

        $payment = PaymentTransaction::findOrFail($validated['payment_id']);
        $refundAmount = round((float) $validated['refund_amount'], 2);

        if ($payment->status === 'REFUNDED') {
            return redirect()->route('admin.financial-overview')
                ->with('warning', "Transaction {$payment->transaction_reference} has already been fully refunded.");
        }

        if ($refundAmount > (float) $payment->amount) {
            return redirect()->route('admin.financial-overview')
                ->withErrors(['refund_amount' => "Refund amount (৳{$refundAmount}) cannot exceed the paid amount (৳{$payment->amount})."]);
        }

        $shopId = $payment->invoice_id
            ? DB::table('invoices')->where('id', $payment->invoice_id)->value('shop_id')
            : FinancialLedger::where('payment_id', $payment->id)->whereNotNull('shop_id')->value('shop_id');
        $isFullRefund = $refundAmount >= round((float) $payment->amount - 0.01, 2);

        DB::transaction(function () use ($payment, $validated, $refundAmount, $shopId, $isFullRefund) {
            $disputeId = $validated['dispute_case_id'] ?? DB::table('dispute_cases')
                ->where('payment_id', $payment->id)
                ->whereIn('status', ['OPEN', 'INVESTIGATING', 'WAITING_EVIDENCE', 'WAITING_RESPONSE'])
                ->value('id');

            DB::table('refunds')->insert([
                'payment_id' => $payment->id,
                'dispute_case_id' => $disputeId ?: null,
                'approved_by_id' => auth()->id(),
                'amount' => $refundAmount,
                'reason' => trim($validated['reason']),
                'status' => 'PROCESSED',
                'processed_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $payment->update([
                'status' => $isFullRefund ? 'REFUNDED' : 'PARTIALLY_REFUNDED',
            ]);

            if ($payment->invoice_id && $isFullRefund) {
                DB::table('invoices')->where('id', $payment->invoice_id)->update([
                    'status' => 'REFUNDED',
                    'updated_at' => now(),
                ]);
            }

            if ($disputeId) {
                DB::table('dispute_cases')->where('id', $disputeId)->update([
                    'status' => 'REFUNDED',
                    'resolution' => trim($validated['reason']),
                    'resolved_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            self::logEntry(
                'REFUND',
                'DEBIT',
                $refundAmount,
                "Customer refund issued ({$payment->transaction_reference}) — " . trim($validated['reason']),
                [
                    'shop_id' => $shopId,
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                ]
            );

            if (class_exists(\App\Models\AuditLog::class)) {
                \App\Models\AuditLog::create([
                    'actor_id' => auth()->id(),
                    'action' => 'admin_refund_issued',
                    'target_resource' => "payment_transactions:{$payment->id}",
                    'payload_before' => ['status' => 'PAID', 'amount' => $payment->amount],
                    'payload_after' => ['refund_amount' => $refundAmount, 'reason' => trim($validated['reason'])],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                ]);
            }
        });

        return redirect()
            ->route('admin.financial-overview')
            ->with('success', "Refund of ৳" . number_format($refundAmount, 2) . " issued for {$payment->transaction_reference} and logged to the Double-Entry Ledger.");
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
                DB::raw("SUM(CASE WHEN entry_type = 'VENDOR_PAYOUT' THEN amount ELSE 0 END) as total_paid"),
                DB::raw("SUM(CASE WHEN entry_type = 'REFUND' THEN amount * 0.90 ELSE 0 END) as total_refund_clawback")
            )
            ->whereNotNull('shop_id')
            ->whereIn('entry_type', ['VENDOR_EARNING', 'VENDOR_PAYOUT', 'REFUND'])
            ->groupBy('shop_id')
            ->get()
            ->filter(fn ($row) => round((float) $row->total_earned - (float) $row->total_paid - (float) $row->total_refund_clawback, 2) > 0.009)
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

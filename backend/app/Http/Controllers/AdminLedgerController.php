<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FinancialLedger;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairShop;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use Carbon\Carbon;
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

        // 2. Dynamic Platform Fee (5%–10% excl. delivery) & 100% Vendor Listed-Price Escrow Accounting
        $grossCommissionEarned  = (float) FinancialLedger::where('entry_type', 'PLATFORM_COMMISSION')->where('direction', 'CREDIT')->sum('amount');
        $commissionReversals    = (float) FinancialLedger::where('entry_type', 'PLATFORM_COMMISSION')->where('direction', 'DEBIT')->sum('amount');
        $totalCustomerInflow    = (float) FinancialLedger::where('entry_type', 'CUSTOMER_PAYMENT')->sum('amount');
        $totalDeliveryFees      = (float) FinancialLedger::where('entry_type', 'DELIVERY_FEE')->where('direction', 'CREDIT')->sum('amount');
        $totalVendorDisbursed   = (float) FinancialLedger::where('entry_type', 'VENDOR_PAYOUT')->sum('amount');
        $totalVendorAllocated   = (float) FinancialLedger::where('entry_type', 'VENDOR_EARNING')->sum('amount');
        $totalRiderDisbursed    = (float) FinancialLedger::where('entry_type', 'DELIVERY_PAYOUT')->sum('amount');
        $totalRefunds           = (float) FinancialLedger::where('entry_type', 'REFUND')->sum('amount');

        // Compute exact net commission & vendor clawbacks across dynamic 5%–10% shop rates
        $refundClawbackSummary  = self::computeRefundAccountingSummary();
        $refundCommissionOffset = $commissionReversals > 0
            ? max($commissionReversals, $refundClawbackSummary['commission_offset'])
            : $refundClawbackSummary['commission_offset'];
        $totalCommissionRevenue = max(0, round($grossCommissionEarned - $refundCommissionOffset, 2));

        // Vendor Escrow Payable = Net Allocated Earnings (100% listed prices minus refund clawbacks) minus Disbursed Payouts
        $refundVendorClawback   = $refundClawbackSummary['vendor_clawback'];
        $netVendorPayable       = max(0, round($totalVendorAllocated - $refundVendorClawback, 2));
        $pendingEscrowReserve   = max(0, round($netVendorPayable - $totalVendorDisbursed, 2));

        // Open settlements count (shops with positive unsettled vendor escrow balances)
        $pendingSettlementCount = self::countUnsettledShops();

        // Platform Income Trend & Best Performing Workshops
        $biMetrics = self::buildShopPerformanceAndIncomeMetrics();

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
            ->when(Schema::hasColumn('financial_ledgers', 'hidden_at'), fn ($q) => $q->whereNull('hidden_at'))
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
            'totalDeliveryFees'       => $totalDeliveryFees,
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
            'bestPerformingShops'     => $biMetrics['bestPerformingShops'],
            'platformIncomeTrend'     => $biMetrics['platformIncomeTrend'],
            'avgCommissionRatePct'    => $biMetrics['avgCommissionRatePct'],
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

        $shop = RepairShop::with('vendorProfile')->findOrFail($validated['shop_id']);
        $vendorAmount = (float) $validated['gross_amount'];

        $customNote = !empty($validated['note'])
            ? '[Manual Settlement] ' . trim($validated['note'])
            : '[Manual Settlement] Admin manual order settlement';

        $result = self::settleOrderWithCommission(
            $vendorAmount,
            (int) $shop->id,
            $validated['payment_id'] ?? null,
            null,
            null,
            $customNote
        );

        return redirect()
            ->route('admin.financial-overview')
            ->with(
                'success',
                "Manual settlement posted for {$shop->name}: Customer Order ৳" . number_format($result['customer_inflow'], 2)
                . " | {$result['commission_rate_pct']}% Platform Fee ৳" . number_format($result['commission_amount'], 2)
                . " | 100% Vendor Earning ৳" . number_format($result['vendor_earning'], 2)
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
        $shopClawbacks = self::computeShopVendorClawbacks();

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
            ->map(function ($row) use ($shopClawbacks) {
                $clawback = (float) ($shopClawbacks[(int) $row->shop_id] ?? 0.0);
                $row->unsettled_amount = round((float) $row->total_earned - (float) $row->total_paid - $clawback, 2);
                return $row;
            })
            ->filter(fn ($row) => $row->unsettled_amount > 0.009)
            ->take($limit)
            ->values();

        $settledCount = 0;
        $totalDisbursed = 0.0;

        DB::transaction(function () use ($unsettledShops, &$settledCount, &$totalDisbursed) {
            foreach ($unsettledShops as $row) {
                $shopId = (int) $row->shop_id;
                $unsettledAmount = (float) $row->unsettled_amount;
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

            if ($isFullRefund && $payment->repair_request_id) {
                $refundedOrder = \App\Models\RepairRequest::with('repairJob')->find($payment->repair_request_id);
                if ($refundedOrder) {
                    app(\App\Services\SparePartInventoryService::class)->restoreForOrder(
                        $refundedOrder,
                        \App\Models\SparePartStockMovement::TYPE_RETURN_RESTORE,
                        'Admin full refund',
                        auth()->id()
                    );
                }
            }

            $split = self::computeRefundSplit($payment, $refundAmount, $shopId ? (int) $shopId : null);

            DB::table('escrow_ledgers')->insert([
                'payment_id' => $payment->id,
                'repair_request_id' => $payment->repair_request_id,
                'customer_user_id' => $payment->user_id,
                'vendor_user_id' => $shopId ? RepairShop::with('vendorProfile')->find($shopId)?->vendorProfile?->user_id : null,
                'amount' => $refundAmount,
                'gross_amount' => $refundAmount,
                'platform_commission_amount' => $split['commission_reversal'],
                'vendor_net_amount' => $split['vendor_clawback'],
                'action' => 'REFUND',
                'note' => "Admin refund processed ({$payment->transaction_reference}): " . trim($validated['reason']),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

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

            if ($split['commission_reversal'] > 0.009) {
                self::logEntry(
                    'PLATFORM_COMMISSION',
                    'DEBIT',
                    $split['commission_reversal'],
                    "Platform commission reversal for refunded order ({$payment->transaction_reference})",
                    [
                        'shop_id' => $shopId,
                        'payment_id' => $payment->id,
                        'invoice_id' => $payment->invoice_id,
                    ]
                );
            }

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
        $shopClawbacks = self::computeShopVendorClawbacks();

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
            ->filter(function ($row) use ($shopClawbacks) {
                $clawback = (float) ($shopClawbacks[(int) $row->shop_id] ?? 0.0);
                return round((float) $row->total_earned - (float) $row->total_paid - $clawback, 2) > 0.009;
            })
            ->count();
    }

    /**
     * Compute exact refund split between Vendor Earning clawback, Platform Fee reversal, and Delivery Logistics.
     */
    public static function computeRefundSplit(PaymentTransaction $payment, float $refundAmount, ?int $shopId = null): array
    {
        $ve = (float) FinancialLedger::where('payment_id', $payment->id)
            ->where('entry_type', 'VENDOR_EARNING')
            ->sum('amount');
        $pc = (float) FinancialLedger::where('payment_id', $payment->id)
            ->where('entry_type', 'PLATFORM_COMMISSION')
            ->where('direction', 'CREDIT')
            ->sum('amount');
        $df = (float) FinancialLedger::where('payment_id', $payment->id)
            ->where('entry_type', 'DELIVERY_FEE')
            ->where('direction', 'CREDIT')
            ->sum('amount');

        if ($ve > 0.009) {
            if ($refundAmount >= round((float) $payment->amount - 0.01, 2)) {
                $ratio = 1.0;
            } else {
                $orderPool = max(0.01, (float) $payment->amount > 0 ? (float) $payment->amount : round($ve + $pc + $df, 2));
                $ratio = min(1.0, $refundAmount / $orderPool);
            }
            $vendorClawback = round($ve * $ratio, 2);
            $commissionReversal = round($pc * $ratio, 2);
            $deliveryReversal = max(0.0, round($refundAmount - $vendorClawback - $commissionReversal, 2));

            return [
                'vendor_clawback' => $vendorClawback,
                'commission_reversal' => $commissionReversal,
                'delivery_reversal' => $deliveryReversal,
            ];
        }

        $resolvedShopId = $shopId ?: ($payment->invoice_id ? DB::table('invoices')->where('id', $payment->invoice_id)->value('shop_id') : null);
        $shop = $resolvedShopId ? RepairShop::with('vendorProfile')->find($resolvedShopId) : null;
        $rateDecimal = ServicePackageController::resolveCommissionRatePct($shop) / 100.0;

        $deliveryAmt = $payment->invoice_id ? (float) DB::table('invoices')->where('id', $payment->invoice_id)->value('delivery_amount') : 0.0;
        $deliveryReversal = min($refundAmount, max(0.0, $deliveryAmt));
        $orderShare = max(0.0, round($refundAmount - $deliveryReversal, 2));

        $vendorClawback = round($orderShare / (1.0 + $rateDecimal), 2);
        $commissionReversal = max(0.0, round($orderShare - $vendorClawback, 2));

        return [
            'vendor_clawback' => $vendorClawback,
            'commission_reversal' => $commissionReversal,
            'delivery_reversal' => $deliveryReversal,
        ];
    }

    /**
     * Aggregate vendor refund clawbacks & commission offsets across dynamic 5%–10% shop rates.
     */
    public static function computeRefundAccountingSummary(array $shopIds = []): array
    {
        $refundQuery = FinancialLedger::where('entry_type', 'REFUND');
        if (!empty($shopIds)) {
            $refundQuery->whereIn('shop_id', $shopIds);
        }
        $refundRows = $refundQuery->get(['shop_id', 'payment_id', 'amount']);

        if ($refundRows->isEmpty()) {
            return ['by_shop' => [], 'vendor_clawback' => 0.0, 'commission_offset' => 0.0];
        }

        $paymentIds = $refundRows->pluck('payment_id')->filter()->unique()->values()->all();
        $escrowRefundsByPayment = !empty($paymentIds)
            ? DB::table('escrow_ledgers')
                ->whereIn('payment_id', $paymentIds)
                ->where('action', 'REFUND')
                ->select(
                    'payment_id',
                    DB::raw('SUM(vendor_net_amount) as vendor_clawback'),
                    DB::raw('SUM(platform_commission_amount) as commission_offset')
                )
                ->groupBy('payment_id')
                ->get()
                ->keyBy('payment_id')
            : collect();

        $shopRates = RepairShop::with('vendorProfile')
            ->get()
            ->mapWithKeys(fn ($s) => [(int) $s->id => ServicePackageController::resolveCommissionRatePct($s) / 100.0]);

        $byShop = [];
        $totalVendor = 0.0;
        $totalCommission = 0.0;

        foreach ($refundRows as $row) {
            $sId = (int) ($row->shop_id ?? 0);
            $pId = (int) ($row->payment_id ?? 0);
            $amt = (float) $row->amount;

            if ($pId > 0 && $escrowRefundsByPayment->has($pId)) {
                $eRow = $escrowRefundsByPayment->get($pId);
                $v = round((float) ($eRow->vendor_clawback ?? 0), 2);
                $c = round((float) ($eRow->commission_offset ?? 0), 2);
            } else {
                $r = (float) ($shopRates[$sId] ?? (VendorProfile::DEFAULT_COMMISSION_RATE / 100.0));
                $v = round($amt / (1.0 + $r), 2);
                $c = round($amt - $v, 2);
            }

            if ($sId > 0) {
                $byShop[$sId] = round(($byShop[$sId] ?? 0.0) + $v, 2);
            }
            $totalVendor = round($totalVendor + $v, 2);
            $totalCommission = round($totalCommission + $c, 2);
        }

        return [
            'by_shop' => $byShop,
            'vendor_clawback' => $totalVendor,
            'commission_offset' => $totalCommission,
        ];
    }

    public static function computeShopVendorClawbacks(array $shopIds = []): array
    {
        return self::computeRefundAccountingSummary($shopIds)['by_shop'];
    }

    public static function computeTotalVendorClawback(array $shopIds = []): float
    {
        return self::computeRefundAccountingSummary($shopIds)['vendor_clawback'];
    }

    /**
     * Allow Admin to adjust a workshop's platform commission fee strictly between 5% and 10%.
     * Route: POST /admin/shops/{shop}/commission-rate
     */
    public function updateShopCommissionRate(Request $request, RepairShop $shop)
    {
        $validated = $request->validate([
            'commission_rate' => 'required|numeric|min:' . VendorProfile::MIN_COMMISSION_RATE . '|max:' . VendorProfile::MAX_COMMISSION_RATE,
        ]);

        $newRate = round(
            min(VendorProfile::MAX_COMMISSION_RATE, max(VendorProfile::MIN_COMMISSION_RATE, (float) $validated['commission_rate'])),
            2
        );

        $shop->loadMissing('vendorProfile');
        $vp = $shop->vendorProfile;
        if (!$vp) {
            return redirect()->back()->with('warning', "Unable to update fee rate for {$shop->name}: missing linked vendor profile.");
        }

        $oldRate = $vp->effectiveCommissionRatePct();
        $vp->update(['commission_rate' => $newRate]);

        AuditLog::create([
            'actor_id' => auth()->id(),
            'action' => 'vendor_commission_rate_updated',
            'target_resource' => "vendor_profiles:{$vp->id}",
            'payload_before' => ['shop_id' => $shop->id, 'commission_rate' => $oldRate],
            'payload_after' => ['shop_id' => $shop->id, 'commission_rate' => $newRate],
            'ip_address' => $request->ip() ?? '127.0.0.1',
        ]);

        return redirect()->back()->with(
            'success',
            "Updated platform fee rate for {$shop->name} to " . rtrim(rtrim(number_format($newRate, 2), '0'), '.') . "% (applied on workshop earnings, excluding pickup & delivery)."
        );
    }

    /**
     * Build ranked Best Performing Workshops & chronological Platform Income Rate Trend Analytics.
     */
    public static function buildShopPerformanceAndIncomeMetrics(): array
    {
        $shops = RepairShop::with('vendorProfile')
            ->where('is_active', true)
            ->get();

        $ledgerByShop = FinancialLedger::whereNotNull('shop_id')
            ->whereIn('entry_type', ['VENDOR_EARNING', 'PLATFORM_COMMISSION', 'DELIVERY_FEE'])
            ->get(['shop_id', 'entry_type', 'direction', 'amount'])
            ->groupBy('shop_id');

        $shopClawbacks = self::computeShopVendorClawbacks();

        $completedJobsByShop = RepairJob::where('status', 'COMPLETED')
            ->select('shop_id', DB::raw('COUNT(*) as completed_count'))
            ->groupBy('shop_id')
            ->pluck('completed_count', 'shop_id');

        $bestPerformingShops = $shops->map(function (RepairShop $shop) use ($ledgerByShop, $shopClawbacks, $completedJobsByShop) {
            $entries = $ledgerByShop->get($shop->id, collect());
            $rawVendorEarning = (float) $entries->where('entry_type', 'VENDOR_EARNING')->sum('amount');
            $vendorClawback = (float) ($shopClawbacks[$shop->id] ?? 0.0);
            $netVendorEarning = max(0.0, round($rawVendorEarning - $vendorClawback, 2));

            $commCredit = (float) $entries->where('entry_type', 'PLATFORM_COMMISSION')->where('direction', 'CREDIT')->sum('amount');
            $commDebit = (float) $entries->where('entry_type', 'PLATFORM_COMMISSION')->where('direction', 'DEBIT')->sum('amount');
            $netCommission = max(0.0, round($commCredit - $commDebit, 2));

            $completedOrders = (int) ($completedJobsByShop[$shop->id] ?? 0);
            $rating = round((float) ($shop->rating ?? 0), 2);
            $reviewCount = (int) ($shop->review_count ?? 0);
            $ratePct = $shop->effectiveCommissionRatePct();

            // Dynamic tier guidance (5% reward for high volume/rating top performers, up to 10% standard/new tier)
            if ($completedOrders >= 5 && $rating >= 4.7) {
                $suggestedRate = 5.5;
                $tierBadge = 'Platinum Partner';
            } elseif ($completedOrders >= 3 && $rating >= 4.4) {
                $suggestedRate = 7.0;
                $tierBadge = 'Gold Performer';
            } elseif ($completedOrders >= 1 || $netVendorEarning > 0) {
                $suggestedRate = 8.5;
                $tierBadge = 'Verified Active';
            } else {
                $suggestedRate = 10.0;
                $tierBadge = 'Standard Onboarding';
            }

            $score = ($netVendorEarning / 500.0) + ($completedOrders * 6.0) + ($rating * 12.0) + ($reviewCount * 0.8);

            return [
                'shop_id' => (int) $shop->id,
                'name' => $shop->name,
                'city' => $shop->city,
                'area' => $shop->area,
                'is_verified' => (bool) ($shop->vendorProfile?->verification_status === 'approved' || $shop->is_verified),
                'rating' => $rating,
                'review_count' => $reviewCount,
                'completed_orders' => $completedOrders,
                'vendor_earning' => $netVendorEarning,
                'platform_commission' => $netCommission,
                'commission_rate_pct' => $ratePct,
                'suggested_rate_pct' => $suggestedRate,
                'tier_badge' => $tierBadge,
                'score' => round($score, 2),
            ];
        })
            ->sortByDesc('score')
            ->values()
            ->take(10);

        $avgCommissionRatePct = $shops->isNotEmpty()
            ? round((float) $shops->avg(fn (RepairShop $s) => $s->effectiveCommissionRatePct()), 2)
            : VendorProfile::DEFAULT_COMMISSION_RATE;

        // Build Platform Income Rate Trend across chronological periods
        $trendEntries = FinancialLedger::whereIn('entry_type', ['PLATFORM_COMMISSION', 'VENDOR_EARNING', 'DELIVERY_FEE'])
            ->get(['entry_type', 'direction', 'amount', 'effective_at', 'created_at']);

        $datedEntries = $trendEntries->map(function ($entry) {
            $ts = $entry->effective_at ?: $entry->created_at ?: now();
            return [
                'date' => Carbon::parse($ts),
                'entry_type' => $entry->entry_type,
                'direction' => strtoupper((string) $entry->direction),
                'amount' => (float) $entry->amount,
            ];
        });

        $minDate = $datedEntries->isNotEmpty() ? $datedEntries->min('date')->copy()->startOfDay() : now()->copy()->subWeeks(7)->startOfWeek();
        $maxDate = $datedEntries->isNotEmpty() ? $datedEntries->max('date')->copy()->endOfDay() : now()->copy()->endOfDay();
        if ($maxDate->lt(now()->copy()->startOfDay())) {
            $maxDate = now()->copy()->endOfDay();
        }
        $spanDays = max(1, (int) $minDate->diffInDays($maxDate));
        $useWeekly = $spanDays > 21;

        $buckets = [];
        if ($useWeekly) {
            $bucketCount = min(10, max(6, (int) ceil($spanDays / 7)));
            $startWeek = now()->copy()->startOfWeek()->subWeeks($bucketCount - 1);
            for ($i = 0; $i < $bucketCount; $i++) {
                $wStart = $startWeek->copy()->addWeeks($i);
                $wEnd = $wStart->copy()->endOfWeek();
                $key = $wStart->format('Y-m-d');
                $buckets[$key] = [
                    'key' => $key,
                    'label' => $wStart->format('d M'),
                    'start' => $wStart,
                    'end' => $wEnd,
                    'commission' => 0.0,
                    'delivery' => 0.0,
                    'vendor' => 0.0,
                ];
            }
        } else {
            $dayCount = min(8, max(5, $spanDays + 1));
            $startDay = now()->copy()->startOfDay()->subDays($dayCount - 1);
            for ($i = 0; $i < $dayCount; $i++) {
                $dStart = $startDay->copy()->addDays($i);
                $dEnd = $dStart->copy()->endOfDay();
                $key = $dStart->format('Y-m-d');
                $buckets[$key] = [
                    'key' => $key,
                    'label' => $dStart->format('d M'),
                    'start' => $dStart,
                    'end' => $dEnd,
                    'commission' => 0.0,
                    'delivery' => 0.0,
                    'vendor' => 0.0,
                ];
            }
        }

        foreach ($datedEntries as $row) {
            foreach ($buckets as $k => $b) {
                if ($row['date']->betweenIncluded($b['start'], $b['end'])) {
                    if ($row['entry_type'] === 'PLATFORM_COMMISSION') {
                        $sign = $row['direction'] === 'DEBIT' ? -1.0 : 1.0;
                        $buckets[$k]['commission'] = round($buckets[$k]['commission'] + ($sign * $row['amount']), 2);
                    } elseif ($row['entry_type'] === 'DELIVERY_FEE' && $row['direction'] === 'CREDIT') {
                        $buckets[$k]['delivery'] = round($buckets[$k]['delivery'] + $row['amount'], 2);
                    } elseif ($row['entry_type'] === 'VENDOR_EARNING') {
                        $buckets[$k]['vendor'] = round($buckets[$k]['vendor'] + $row['amount'], 2);
                    }
                    break;
                }
            }
        }

        $series = array_values(array_map(function ($b) {
            $comm = max(0.0, round($b['commission'], 2));
            $deliv = max(0.0, round($b['delivery'], 2));
            $totalPlatform = round($comm + $deliv, 2);
            $effectiveFeeRate = $b['vendor'] > 0 ? round(($comm / $b['vendor']) * 100, 2) : 0.0;
            return [
                'label' => $b['label'],
                'commission' => $comm,
                'delivery' => $deliv,
                'platform_income' => $totalPlatform,
                'vendor_earning' => round($b['vendor'], 2),
                'effective_rate_pct' => $effectiveFeeRate,
            ];
        }, $buckets));

        $maxVal = max(100.0, ...array_map(fn ($p) => max($p['platform_income'], $p['commission'], 0.0), $series));
        $pointCount = max(1, count($series));
        $chartWidth = 640;
        $chartHeight = 190;
        $padX = 36;
        $padTop = 20;
        $padBottom = 26;
        $plotH = $chartHeight - $padTop - $padBottom;
        $plotW = $chartWidth - (2 * $padX);

        $svgPoints = [];
        $svgArea = [];
        foreach ($series as $idx => &$pt) {
            $x = $pointCount > 1
                ? round($padX + ($idx * ($plotW / ($pointCount - 1))), 1)
                : round($chartWidth / 2, 1);
            $yIncome = round($padTop + $plotH - (($pt['platform_income'] / $maxVal) * $plotH), 1);
            $yComm = round($padTop + $plotH - (($pt['commission'] / $maxVal) * $plotH), 1);
            $pt['x'] = $x;
            $pt['y_income'] = $yIncome;
            $pt['y_commission'] = $yComm;
            $svgPoints[] = "{$x},{$yIncome}";
            $svgArea[] = "{$x},{$yIncome}";
        }
        unset($pt);

        $polylinePoints = implode(' ', $svgPoints);
        $commPolylinePoints = implode(' ', array_map(fn ($p) => "{$p['x']},{$p['y_commission']}", $series));
        $firstX = $series[0]['x'] ?? $padX;
        $lastX = $series[count($series) - 1]['x'] ?? ($chartWidth - $padX);
        $baseY = $padTop + $plotH;
        $areaPoints = "{$firstX},{$baseY} " . implode(' ', $svgArea) . " {$lastX},{$baseY}";

        // Calculate Going Up vs Going Down rate comparing second half vs first half
        $mid = max(1, (int) floor(count($series) / 2));
        $firstHalf = array_slice($series, 0, $mid);
        $secondHalf = array_slice($series, $mid);
        $prevAvg = count($firstHalf) > 0 ? array_sum(array_column($firstHalf, 'platform_income')) / count($firstHalf) : 0.0;
        $recentAvg = count($secondHalf) > 0 ? array_sum(array_column($secondHalf, 'platform_income')) / count($secondHalf) : 0.0;

        $rateDeltaPct = $prevAvg > 0.01
            ? round((($recentAvg - $prevAvg) / $prevAvg) * 100, 1)
            : ($recentAvg > 0 ? 100.0 : 0.0);

        $direction = $rateDeltaPct > 1.0 ? 'UP' : ($rateDeltaPct < -1.0 ? 'DOWN' : 'STEADY');
        $trendLabel = $direction === 'UP'
            ? 'Going Up ▲'
            : ($direction === 'DOWN' ? 'Going Down ▼' : 'Steady ●');

        return [
            'bestPerformingShops' => $bestPerformingShops,
            'avgCommissionRatePct' => $avgCommissionRatePct,
            'platformIncomeTrend' => [
                'granularity' => $useWeekly ? 'Weekly' : 'Daily',
                'direction' => $direction,
                'trend_label' => $trendLabel,
                'rate_delta_pct' => $rateDeltaPct,
                'previous_period_avg' => round($prevAvg, 2),
                'recent_period_avg' => round($recentAvg, 2),
                'total_commission' => round(array_sum(array_column($series, 'commission')), 2),
                'total_delivery' => round(array_sum(array_column($series, 'delivery')), 2),
                'max_val' => $maxVal,
                'svg_width' => $chartWidth,
                'svg_height' => $chartHeight,
                'polyline_income' => $polylinePoints,
                'polyline_commission' => $commPolylinePoints,
                'area_income' => $areaPoints,
                'points' => $series,
            ],
        ];
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
     * Helper to process a completed order settlement under the exclusive platform-fee policy:
     *   - 100% of listed/quoted service or spare-part price ($vendorSubtotal) goes to Vendor Earning.
     *   - Shop's dynamic platform fee (5%–10%, excl. pickup & delivery) is added on top for Platform Commission.
     * 1. Customer Payment (CREDIT vendorSubtotal + commissionAmount, excl. delivery)
     * 2. Platform Commission (CREDIT 5%–10% commission)
     * 3. Vendor Earning (DEBIT 100% vendorSubtotal allocated to vendor escrow)
     */
    public static function settleOrderWithCommission(
        float $vendorSubtotal,
        int $shopId,
        ?int $paymentId = null,
        ?int $invoiceId = null,
        ?float $commissionRate = null,
        ?string $customNote = null
    ): array {
        if ($commissionRate === null) {
            $shop = RepairShop::with('vendorProfile')->find($shopId);
            $rateDecimal = ServicePackageController::resolveCommissionRatePct($shop) / 100.0;
        } else {
            $normalized = $commissionRate > 1.0 ? ($commissionRate / 100.0) : $commissionRate;
            $rateDecimal = min(
                VendorProfile::MAX_COMMISSION_RATE / 100.0,
                max(VendorProfile::MIN_COMMISSION_RATE / 100.0, $normalized)
            );
        }

        $vendorEarning = round($vendorSubtotal, 2);
        $commissionAmount = round($vendorEarning * $rateDecimal, 2);
        $customerSettlement = round($vendorEarning + $commissionAmount, 2);
        $ratePct = round($rateDecimal * 100, 2);
        $rateLabel = rtrim(rtrim(number_format($ratePct, 2), '0'), '.');
        $noteSuffix = $customNote ? " — Note: {$customNote}" : '';

        // 1. Credit customer settlement payment into platform account (vendor earning + platform fee, excl. delivery)
        $paymentEntry = self::logEntry(
            'CUSTOMER_PAYMENT',
            'CREDIT',
            $customerSettlement,
            "Customer settlement payment received (Vendor ৳{$vendorEarning} + {$rateLabel}% Fee ৳{$commissionAmount}; Shop #{$shopId}){$noteSuffix}",
            ['shop_id' => $shopId, 'payment_id' => $paymentId, 'invoice_id' => $invoiceId]
        );

        // 2. Credit dynamic 5%–10% platform commission
        $commissionEntry = self::logEntry(
            'PLATFORM_COMMISSION',
            'CREDIT',
            $commissionAmount,
            "{$rateLabel}% platform fee on vendor earning ৳{$vendorEarning} (excl. pickup & delivery){$noteSuffix}",
            ['shop_id' => $shopId, 'payment_id' => $paymentId, 'invoice_id' => $invoiceId]
        );

        // 3. Allocate 100% of vendor listed price to vendor escrow liability
        $vendorEntry = self::logEntry(
            'VENDOR_EARNING',
            'DEBIT',
            $vendorEarning,
            "100% vendor earning allocated to workshop escrow (৳{$vendorEarning}){$noteSuffix}",
            ['shop_id' => $shopId, 'payment_id' => $paymentId, 'invoice_id' => $invoiceId]
        );

        return [
            'payment_entry'       => $paymentEntry,
            'commission_entry'    => $commissionEntry,
            'vendor_entry'        => $vendorEntry,
            'commission_amount'   => $commissionAmount,
            'vendor_earning'      => $vendorEarning,
            'customer_inflow'     => $customerSettlement,
            'commission_rate_pct' => $ratePct,
        ];
    }
}

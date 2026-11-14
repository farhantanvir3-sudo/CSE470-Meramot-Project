<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\FinancialLedger;
use App\Models\RepairShop;
use App\Models\RiderEarnings;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use App\Services\MultiChannelNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * FEATURE R18: Automated Vendor & Courier Payout & Net Commission Settlement
 * ============================================================================
 * Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
 * Sprint: Sprint 3
 * Architectural Layer: Controller (VendorPayoutController)
 * Models Touched: VendorPayout, FinancialLedger, VendorProfile, RiderEarnings, AuditLog
 * Views Rendered: admin/payouts/manage.blade.php, vendor/dashboard.blade.php (Payouts Tab)
 * ============================================================================
 */
class VendorPayoutController extends Controller
{
    /**
     * Compute deterministic 90% Vendor Net / 10% Platform Commission escrow summary for shop IDs.
     */
    public static function computeShopEscrowSummary(array $shopIds): array
    {
        if (empty($shopIds)) {
            return [
                'gross_inflow' => 0.0,
                'commission_deducted' => 0.0,
                'net_allocated' => 0.0,
                'paid_out' => 0.0,
                'pending_requested' => 0.0,
                'unsettled_balance' => 0.0,
                'available_to_request' => 0.0,
            ];
        }

        $grossInflow = (float) FinancialLedger::whereIn('shop_id', $shopIds)
            ->where('entry_type', 'CUSTOMER_PAYMENT')
            ->sum('amount');

        $commissionCredit = (float) FinancialLedger::whereIn('shop_id', $shopIds)
            ->where('entry_type', 'PLATFORM_COMMISSION')
            ->where('direction', 'CREDIT')
            ->sum('amount');

        $commissionDebit = (float) FinancialLedger::whereIn('shop_id', $shopIds)
            ->where('entry_type', 'PLATFORM_COMMISSION')
            ->where('direction', 'DEBIT')
            ->sum('amount');

        $vendorAllocated = (float) FinancialLedger::whereIn('shop_id', $shopIds)
            ->where('entry_type', 'VENDOR_EARNING')
            ->sum('amount');

        $refundClawback = (float) FinancialLedger::whereIn('shop_id', $shopIds)
            ->where('entry_type', 'REFUND')
            ->sum(DB::raw('amount * 0.90'));

        $netAllocated = max(0.0, round($vendorAllocated - $refundClawback, 2));
        $paidOut = round((float) FinancialLedger::whereIn('shop_id', $shopIds)
            ->where('entry_type', 'VENDOR_PAYOUT')
            ->sum('amount'), 2);

        $pendingRequested = round((float) VendorPayout::whereIn('shop_id', $shopIds)
            ->where('status', 'PENDING')
            ->sum('amount'), 2);

        $unsettledBalance = max(0.0, round($netAllocated - $paidOut, 2));
        $availableToRequest = max(0.0, round($unsettledBalance - $pendingRequested, 2));

        return [
            'gross_inflow' => round($grossInflow, 2),
            'commission_deducted' => max(0.0, round($commissionCredit - $commissionDebit, 2)),
            'net_allocated' => $netAllocated,
            'paid_out' => $paidOut,
            'pending_requested' => $pendingRequested,
            'unsettled_balance' => $unsettledBalance,
            'available_to_request' => $availableToRequest,
        ];
    }

    /**
     * Display pending & historical vendor + rider payout settlements for Admin.
     * Route: GET /admin/payouts/manage
     */
    public function index(Request $request)
    {
        $statusFilter = strtoupper((string) $request->query('status', 'ALL'));
        $typeFilter = strtoupper((string) $request->query('recipient', 'ALL'));

        $payoutsQuery = VendorPayout::with([
            'shop.vendorProfile.user',
            'deliveryAgent.user',
            'processedByAdmin',
        ])->orderByRaw("CASE WHEN status = 'PENDING' THEN 0 ELSE 1 END")->orderByDesc('id');

        if (in_array($statusFilter, ['PENDING', 'PAID', 'CANCELLED', 'FAILED'], true)) {
            $payoutsQuery->where('status', $statusFilter);
        }

        if ($typeFilter === 'VENDOR') {
            $payoutsQuery->whereNotNull('shop_id');
        } elseif ($typeFilter === 'RIDER') {
            $payoutsQuery->whereNotNull('delivery_agent_id');
        }

        $payouts = $payoutsQuery->paginate(20)->withQueryString();

        // Eligible unsettled workshops for 1-click Admin direct settlement
        $shopsWithEscrow = RepairShop::with('vendorProfile.user')
            ->orderBy('name')
            ->get()
            ->map(function (RepairShop $shop) {
                $summary = self::computeShopEscrowSummary([$shop->id]);
                $shop->escrow_summary = $summary;
                return $shop;
            })
            ->filter(fn (RepairShop $shop) => $shop->escrow_summary['unsettled_balance'] > 0.009)
            ->values();

        $kpis = [
            'pending_count' => VendorPayout::where('status', 'PENDING')->count(),
            'pending_amount' => round((float) VendorPayout::where('status', 'PENDING')->sum('amount'), 2),
            'unsettled_workshop_pool' => round((float) $shopsWithEscrow->sum(fn ($s) => $s->escrow_summary['unsettled_balance']), 2),
            'vendor_paid_total' => round((float) FinancialLedger::where('entry_type', 'VENDOR_PAYOUT')->sum('amount'), 2),
            'rider_paid_total' => round((float) FinancialLedger::where('entry_type', 'DELIVERY_PAYOUT')->sum('amount'), 2),
        ];

        return view('admin.payouts.manage', compact(
            'payouts',
            'shopsWithEscrow',
            'kpis',
            'statusFilter',
            'typeFilter'
        ));
    }

    /**
     * Disburse or reject a queued vendor / courier payout request.
     * Enforces FinancialLedger(VENDOR_PAYOUT).vendor_payout_id != null invariant.
     * Route: POST /admin/payouts/{id}/disburse
     */
    public function disbursePayout(Request $request, $id, MultiChannelNotificationService $notifier)
    {
        $payout = VendorPayout::with(['shop.vendorProfile.user', 'deliveryAgent.user'])->find($id);

        if (!$payout) {
            return redirect('/admin/payouts/manage')
                ->with('warning', "Payout request #{$id} could not be found.");
        }

        if ($payout->status === 'PAID') {
            return redirect('/admin/payouts/manage')
                ->with('warning', "Payout {$payout->reference_code} has already been disbursed.");
        }

        $decision = strtolower((string) $request->input('decision', 'disburse'));
        $adminNote = trim((string) $request->input('notes', ''));
        $txnRef = trim((string) ($request->input('transaction_ref') ?: ('SBX-DISB-' . strtoupper(Str::random(8)))));

        if (in_array($decision, ['reject', 'cancel'], true)) {
            DB::transaction(function () use ($payout, $adminNote) {
                $payout->update([
                    'status' => 'CANCELLED',
                    'processed_by_admin_id' => auth()->id(),
                    'notes' => $adminNote !== '' ? $adminNote : 'Cancelled by Meramot Treasury Admin.',
                ]);

                if ($payout->delivery_agent_id) {
                    RiderEarnings::where('delivery_agent_id', $payout->delivery_agent_id)
                        ->where('payout_status', 'requested')
                        ->when($payout->reference_code, fn ($q) => $q->where('payout_reference', $payout->reference_code))
                        ->update([
                            'payout_status' => 'unpaid',
                            'payout_reference' => null,
                        ]);
                }

                AuditLog::create([
                    'actor_id' => auth()->id(),
                    'action' => 'payout_request_cancelled',
                    'target_resource' => "vendor_payouts:{$payout->id}",
                    'payload_before' => ['status' => 'PENDING'],
                    'payload_after' => ['status' => 'CANCELLED', 'notes' => $adminNote],
                    'ip_address' => request()->ip() ?? '127.0.0.1',
                ]);
            });

            return redirect('/admin/payouts/manage')
                ->with('warning', "Payout request {$payout->reference_code} was cancelled and returned to unsettled balance.");
        }

        // Guard against over-disbursing a shop beyond its unsettled 90% net escrow allocation
        if ($payout->shop_id) {
            $shopSummary = self::computeShopEscrowSummary([(int) $payout->shop_id]);
            if ((float) $payout->amount > ($shopSummary['unsettled_balance'] + 0.05)) {
                return redirect('/admin/payouts/manage')->with(
                    'warning',
                    "Requested amount (৳" . number_format((float) $payout->amount, 2) . ") exceeds {$payout->shop->name}'s unsettled net escrow balance (৳" . number_format($shopSummary['unsettled_balance'], 2) . ")."
                );
            }
        }

        DB::transaction(function () use ($payout, $txnRef, $adminNote) {
            $now = now();
            $channel = $payout->payout_method
                ?: ($payout->shop?->vendorProfile?->payout_method ?: ($payout->deliveryAgent?->payout_method ?: 'BKASH'));
            $account = $payout->payout_account
                ?: ($payout->shop?->vendorProfile?->payout_account_number ?: ($payout->deliveryAgent?->payout_account_number ?: 'Sandbox-MFS'));

            $payout->update([
                'payout_method' => $channel,
                'payout_account' => $account,
                'status' => 'PAID',
                'transaction_ref' => $txnRef,
                'processed_by_admin_id' => auth()->id(),
                'paid_at' => $now,
                'notes' => $adminNote !== ''
                    ? $adminNote
                    : ($payout->notes ?: "Disbursed via {$channel} ({$account}) • Ref {$txnRef}"),
            ]);

            if ($payout->shop_id) {
                $latestPaymentId = FinancialLedger::where('shop_id', $payout->shop_id)
                    ->where('entry_type', 'VENDOR_EARNING')
                    ->whereNotNull('payment_id')
                    ->latest('id')
                    ->value('payment_id');

                $shopLabel = $payout->shop?->name ?? "Shop #{$payout->shop_id}";
                AdminLedgerController::logEntry(
                    'VENDOR_PAYOUT',
                    'DEBIT',
                    (float) $payout->amount,
                    "Net 90% escrow payout disbursed to {$shopLabel} via {$channel} ({$payout->reference_code} / {$txnRef})",
                    [
                        'shop_id' => (int) $payout->shop_id,
                        'payment_id' => $latestPaymentId,
                        'vendor_payout_id' => (int) $payout->id,
                    ]
                );
            } elseif ($payout->delivery_agent_id) {
                $riderLabel = $payout->deliveryAgent?->user?->name ?? "Courier #{$payout->delivery_agent_id}";
                AdminLedgerController::logEntry(
                    'DELIVERY_PAYOUT',
                    'DEBIT',
                    (float) $payout->amount,
                    "Courier wallet withdrawal disbursed to {$riderLabel} via {$channel} ({$payout->reference_code} / {$txnRef})",
                    [
                        'vendor_payout_id' => (int) $payout->id,
                    ]
                );

                $matched = RiderEarnings::where('delivery_agent_id', $payout->delivery_agent_id)
                    ->where('payout_status', 'requested')
                    ->when($payout->reference_code, fn ($q) => $q->where('payout_reference', $payout->reference_code))
                    ->update(['payout_status' => 'settled']);

                if ($matched === 0) {
                    RiderEarnings::where('delivery_agent_id', $payout->delivery_agent_id)
                        ->whereIn('payout_status', ['requested', 'unpaid'])
                        ->update([
                            'payout_status' => 'settled',
                            'payout_reference' => $payout->reference_code,
                        ]);
                }
            }

            AuditLog::create([
                'actor_id' => auth()->id(),
                'action' => 'disburse_payout',
                'target_resource' => "vendor_payouts:{$payout->id}",
                'payload_before' => ['status' => 'PENDING'],
                'payload_after' => [
                    'status' => 'PAID',
                    'amount' => (float) $payout->amount,
                    'channel' => $channel,
                    'transaction_ref' => $txnRef,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);
        });

        $recipientUser = $payout->shop?->vendorProfile?->user ?? $payout->deliveryAgent?->user;
        if ($recipientUser) {
            try {
                $notifier->notifyUser(
                    user: $recipientUser,
                    eventType: 'PAYOUT_DISBURSED',
                    title: "Payout Disbursed (৳" . number_format((float) $payout->amount, 2) . ")",
                    message: "Meramot Treasury disbursed BDT " . number_format((float) $payout->amount, 2) . " via {$payout->payout_method} (Ref: {$payout->transaction_ref}).",
                    actionUrl: $payout->shop_id ? '/vendor/dashboard?tab=payouts' : '/delivery/earnings',
                    severity: 'success',
                    sendEmail: true,
                    sendSms: true
                );
            } catch (\Throwable $e) {
                // Non-blocking notification
            }
        }

        return redirect('/admin/payouts/manage')->with(
            'success',
            "Disbursed ৳" . number_format((float) $payout->amount, 2) . " ({$payout->reference_code} • TXN {$txnRef}) and recorded DEBIT in Financial Ledger."
        );
    }

    /**
     * Direct 1-click Admin settlement of an unsettled workshop's 90% net balance.
     * Route: POST /admin/payouts/settle-shop
     */
    public function settleShopDirect(Request $request, MultiChannelNotificationService $notifier)
    {
        $validated = $request->validate([
            'shop_id' => 'required|integer|exists:repair_shops,id',
        ]);

        $shop = RepairShop::with('vendorProfile.user')->findOrFail($validated['shop_id']);
        $summary = self::computeShopEscrowSummary([$shop->id]);

        if ($summary['unsettled_balance'] <= 0.009) {
            return redirect('/admin/payouts/manage')
                ->with('warning', "{$shop->name} has no unsettled vendor escrow balance right now.");
        }

        $vp = $shop->vendorProfile;
        $method = $vp?->payout_method ?: 'BKASH';
        $account = $vp?->payout_account_number ?: ($vp?->phone ?: '01700000000');

        $payout = VendorPayout::where('shop_id', $shop->id)
            ->where('status', 'PENDING')
            ->orderBy('id')
            ->first();

        if (!$payout) {
            $grossRef = round($summary['unsettled_balance'] / 0.90, 2);
            $payout = VendorPayout::create([
                'shop_id' => $shop->id,
                'amount' => $summary['unsettled_balance'],
                'gross_reference_amount' => $grossRef,
                'commission_deducted' => round($grossRef - $summary['unsettled_balance'], 2),
                'currency' => 'BDT',
                'payout_method' => $method,
                'payout_account' => $account,
                'status' => 'PENDING',
                'reference_code' => 'VND-DIR-' . $shop->id . '-' . strtoupper(Str::random(5)),
                'notes' => "Direct Admin net settlement for {$shop->name}",
            ]);
        } else {
            $payout->update(['amount' => $summary['unsettled_balance']]);
        }

        return $this->disbursePayout($request, $payout->id, $notifier);
    }

    /**
     * Save Vendor Payout Account Preferences (bKash / Nagad / Rocket / Bank).
     * Route: POST /vendor/payouts/settings
     */
    public function saveVendorSettings(Request $request)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login');
        }

        $vp = VendorProfile::where('user_id', $user->id)->first();
        if (!$vp) {
            return redirect()->back()->with('error', 'Vendor profile not found.');
        }

        $validated = $request->validate([
            'payout_method' => 'required|string|in:BKASH,NAGAD,ROCKET,BANK',
            'payout_account_name' => 'nullable|string|max:120',
            'payout_account_number' => 'required|string|min:6|max:60',
            'payout_bank_name' => 'nullable|string|max:120',
            'payout_branch_name' => 'nullable|string|max:120',
        ]);

        $vp->update([
            'payout_method' => strtoupper($validated['payout_method']),
            'payout_account_name' => $validated['payout_account_name'] ?? $user->name,
            'payout_account_number' => trim($validated['payout_account_number']),
            'payout_bank_name' => $validated['payout_bank_name'] ?? null,
            'payout_branch_name' => $validated['payout_branch_name'] ?? null,
        ]);

        return redirect('/vendor/dashboard?tab=payouts')
            ->with('success', 'Workshop payout destination saved (' . strtoupper($validated['payout_method']) . ' • ' . $vp->payout_account_number . ').');
    }

    /**
     * Vendor requests withdrawal of available 90% net escrow earnings.
     * Route: POST /vendor/payouts/request
     */
    public function requestVendorPayout(Request $request, MultiChannelNotificationService $notifier)
    {
        $user = $request->user();
        if (!$user) {
            return redirect('/login');
        }

        $vp = VendorProfile::with('shops')->where('user_id', $user->id)->first();
        if (!$vp || $vp->shops->isEmpty()) {
            return redirect('/vendor/dashboard?tab=payouts')->with('error', 'No workshop branch available for payout withdrawal.');
        }

        $validated = $request->validate([
            'shop_id' => 'nullable|integer',
            'amount' => 'nullable|numeric|min:100',
            'payout_method' => 'nullable|string|in:BKASH,NAGAD,ROCKET,BANK',
            'payout_account_number' => 'nullable|string|min:6|max:60',
        ]);

        $targetShop = $validated['shop_id'] ?? null
            ? $vp->shops->firstWhere('id', (int) $validated['shop_id'])
            : $vp->shops->first();

        if (!$targetShop) {
            abort(403, 'Selected branch does not belong to your vendor profile.');
        }

        $summary = self::computeShopEscrowSummary([$targetShop->id]);
        $available = $summary['available_to_request'];

        if ($available < 100.0) {
            return redirect('/vendor/dashboard?tab=payouts&shop_id=' . $targetShop->id)
                ->with('warning', "Available withdrawable net balance for {$targetShop->name} is ৳" . number_format($available, 2) . " (minimum ৳100.00 required).");
        }

        $requestAmount = isset($validated['amount']) && (float) $validated['amount'] > 0
            ? min($available, round((float) $validated['amount'], 2))
            : $available;

        $method = strtoupper((string) ($validated['payout_method'] ?? $vp->payout_method ?? 'BKASH'));
        $account = trim((string) ($validated['payout_account_number'] ?? $vp->payout_account_number ?? $vp->phone ?? $user->phone ?? ''));

        if ($account === '') {
            return redirect('/vendor/dashboard?tab=payouts')->withErrors([
                'payout_account_number' => 'Please configure your bKash, Nagad, or bank account number before requesting withdrawal.',
            ]);
        }

        $vp->update([
            'payout_method' => $method,
            'payout_account_number' => $account,
        ]);

        $grossRef = round($requestAmount / 0.90, 2);
        $commissionRef = round($grossRef - $requestAmount, 2);
        $refCode = 'VND-PYT-' . $targetShop->id . '-' . strtoupper(Str::random(5));

        $payout = VendorPayout::create([
            'shop_id' => $targetShop->id,
            'amount' => $requestAmount,
            'gross_reference_amount' => $grossRef,
            'commission_deducted' => $commissionRef,
            'currency' => 'BDT',
            'payout_method' => $method,
            'payout_account' => $account,
            'status' => 'PENDING',
            'reference_code' => $refCode,
            'notes' => "Vendor withdrawal request for {$targetShop->name} (90% net after 10% platform commission)",
        ]);

        AuditLog::create([
            'actor_id' => $user->id,
            'action' => 'vendor_payout_requested',
            'target_resource' => "vendor_payouts:{$payout->id}",
            'payload_before' => ['available_balance' => $available],
            'payload_after' => [
                'reference_code' => $refCode,
                'amount' => $requestAmount,
                'method' => $method,
                'shop_id' => $targetShop->id,
            ],
            'ip_address' => request()->ip() ?? '127.0.0.1',
        ]);

        try {
            $notifier->notifyUser(
                user: $user,
                eventType: 'VENDOR_PAYOUT_REQUESTED',
                title: "Payout Request Queued ({$refCode})",
                message: "Your net withdrawal request of BDT " . number_format($requestAmount, 2) . " for {$targetShop->name} has been submitted to Meramot Treasury.",
                actionUrl: '/vendor/dashboard?tab=payouts',
                severity: 'info',
                sendEmail: false,
                sendSms: true
            );
        } catch (\Throwable $e) {
            // Non-blocking notification
        }

        return redirect('/vendor/dashboard?tab=payouts&shop_id=' . $targetShop->id)->with(
            'success',
            "Withdrawal request {$refCode} for ৳" . number_format($requestAmount, 2) . " queued for Admin disbursement ({$method} • {$account})."
        );
    }
}

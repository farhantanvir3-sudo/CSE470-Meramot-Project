<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\RiderEarnings;
use App\Models\VendorPayout;
use App\Services\MultiChannelNotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * FEATURE R3: Delivery Agent Earnings Ledger & Payout Tracker
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 3
 * Architectural Layer: Controller (RiderLedgerController)
 * Models Touched: RiderEarnings, VendorPayout, DeliveryAgent, AuditLog
 * View Rendered: frontend/views/delivery/earnings.blade.php
 * ============================================================================
 */
class RiderLedgerController extends Controller
{
    public const MIN_PAYOUT_THRESHOLD = 500.00;

    /**
     * Display rider earnings overview, trip ledger, and payout withdrawal history.
     * Route: GET /delivery/earnings
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $agent = $user?->deliveryAgent;

        $earnings = $agent
            ? RiderEarnings::with(['deliveryJob.repairRequest'])
                ->where('delivery_agent_id', $agent->id)
                ->orderByDesc('id')
                ->get()
            : collect();

        $totalEarned = round((float) $earnings->sum('total_earned'), 2);
        $unsettledBalance = round((float) $earnings->where('payout_status', 'unpaid')->sum('total_earned'), 2);
        $pendingWithdrawal = round((float) $earnings->where('payout_status', 'requested')->sum('total_earned'), 2);
        $settledBalance = round((float) $earnings->where('payout_status', 'settled')->sum('total_earned'), 2);

        $payoutHistory = $agent
            ? VendorPayout::where('delivery_agent_id', $agent->id)->orderByDesc('id')->get()
            : collect();

        $minThreshold = self::MIN_PAYOUT_THRESHOLD;

        return view('delivery.earnings', compact(
            'agent',
            'earnings',
            'totalEarned',
            'unsettledBalance',
            'pendingWithdrawal',
            'settledBalance',
            'payoutHistory',
            'minThreshold'
        ));
    }

    /**
     * Save preferred mobile wallet / bank payout account for the delivery agent.
     * Route: POST /delivery/payout/settings
     */
    public function savePayoutSettings(Request $request)
    {
        $agent = $request->user()?->deliveryAgent;
        if (!$agent) {
            return redirect()->back()->with('error', 'Only registered delivery partners can update courier wallet settings.');
        }

        $validated = $request->validate([
            'payout_method' => 'required|string|in:BKASH,NAGAD,ROCKET,BANK',
            'payout_account_number' => 'required|string|min:6|max:60',
        ]);

        $agent->update([
            'payout_method' => strtoupper($validated['payout_method']),
            'payout_account_number' => trim($validated['payout_account_number']),
        ]);

        return redirect()
            ->route('delivery.earnings')
            ->with('success', 'Courier payout destination saved (' . strtoupper($validated['payout_method']) . ' • ' . $agent->payout_account_number . ').');
    }

    /**
     * Request instant payout withdrawal for rider (requires >= 500 BDT unsettled balance).
     * Preserves delivery_agents.total_earnings == SUM(rider_earnings.total_earned) invariant.
     * Route: POST /delivery/payout/request
     */
    public function requestPayout(Request $request, MultiChannelNotificationService $notifier)
    {
        $user = $request->user();
        $agent = $user?->deliveryAgent;

        if (!$agent) {
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Courier profile required to request rider payout.'], 422);
            }
            return redirect('/delivery/earnings')->with('warning', 'Only active delivery partners with a courier wallet can submit withdrawal requests.');
        }

        if (($agent->registration_status ?? 'APPROVED') !== 'APPROVED' || !$agent->is_active) {
            return redirect('/delivery/earnings')->with('error', 'Your courier account must be approved and active before requesting withdrawals.');
        }

        $validated = $request->validate([
            'payout_method' => 'nullable|string|in:BKASH,NAGAD,ROCKET,BANK',
            'payout_account_number' => 'nullable|string|min:6|max:60',
        ]);

        $payoutMethod = strtoupper((string) ($validated['payout_method'] ?? $agent->payout_method ?? 'BKASH'));
        $payoutAccount = trim((string) ($validated['payout_account_number'] ?? $agent->payout_account_number ?? $user->phone ?? ''));

        if ($payoutAccount === '') {
            return redirect('/delivery/earnings')->withErrors([
                'payout_account_number' => 'Please enter your bKash, Nagad, or bank account number before requesting a payout.',
            ]);
        }

        $unpaidRows = RiderEarnings::where('delivery_agent_id', $agent->id)
            ->where('payout_status', 'unpaid')
            ->lockForUpdate()
            ->get();

        $unsettledAmount = round((float) $unpaidRows->sum('total_earned'), 2);

        if ($unsettledAmount < self::MIN_PAYOUT_THRESHOLD) {
            return redirect('/delivery/earnings')->with(
                'warning',
                'Minimum withdrawal amount is BDT ' . number_format(self::MIN_PAYOUT_THRESHOLD, 2)
                . '. Your current unsettled wallet balance is BDT ' . number_format($unsettledAmount, 2) . '.'
            );
        }

        $payout = DB::transaction(function () use ($agent, $user, $payoutMethod, $payoutAccount, $unsettledAmount) {
            $lockedRows = RiderEarnings::where('delivery_agent_id', $agent->id)
                ->where('payout_status', 'unpaid')
                ->lockForUpdate()
                ->get();

            $lockedSum = round((float) $lockedRows->sum('total_earned'), 2);
            if ($lockedSum < self::MIN_PAYOUT_THRESHOLD) {
                return null;
            }

            $agent->update([
                'payout_method' => $payoutMethod,
                'payout_account_number' => $payoutAccount,
            ]);

            $refCode = 'RDR-PYT-' . $agent->id . '-' . strtoupper(Str::random(5));

            $payout = VendorPayout::create([
                'shop_id' => null,
                'delivery_agent_id' => $agent->id,
                'amount' => $lockedSum,
                'gross_reference_amount' => $lockedSum,
                'commission_deducted' => 0.00,
                'currency' => 'BDT',
                'payout_method' => $payoutMethod,
                'payout_account' => $payoutAccount,
                'status' => 'PENDING',
                'reference_code' => $refCode,
                'notes' => "Courier payout request ({$lockedRows->count()} completed trips) via {$payoutMethod} ({$payoutAccount})",
            ]);

            RiderEarnings::whereIn('id', $lockedRows->pluck('id')->all())->update([
                'payout_status' => 'requested',
                'payout_reference' => $refCode,
            ]);

            AuditLog::create([
                'actor_id' => $user->id,
                'action' => 'rider_payout_requested',
                'target_resource' => "vendor_payouts:{$payout->id}",
                'payload_before' => ['unsettled_balance' => $lockedSum],
                'payload_after' => [
                    'reference_code' => $refCode,
                    'amount' => $lockedSum,
                    'method' => $payoutMethod,
                    'account' => $payoutAccount,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return $payout;
        });

        if (!$payout) {
            return redirect('/delivery/earnings')->with('warning', 'No eligible unsettled balance available for withdrawal.');
        }

        try {
            $notifier->notifyUser(
                user: $user,
                eventType: 'RIDER_PAYOUT_REQUESTED',
                title: "Courier Payout Requested ({$payout->reference_code})",
                message: "Your withdrawal request of BDT " . number_format((float) $payout->amount, 2) . " to {$payoutMethod} ({$payoutAccount}) has been queued for Admin disbursement.",
                actionUrl: '/delivery/earnings',
                severity: 'info',
                sendEmail: false,
                sendSms: true
            );
        } catch (\Throwable $e) {
            // Non-blocking notification
        }

        return redirect('/delivery/earnings')->with(
            'success',
            "Withdrawal request {$payout->reference_code} for BDT " . number_format((float) $payout->amount, 2) . " submitted to Meramot Treasury ({$payoutMethod} • {$payoutAccount})."
        );
    }
}

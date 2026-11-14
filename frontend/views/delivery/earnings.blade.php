{{--
=============================================================================
FEATURE R3: Delivery Agent Earnings Ledger & Payout Tracker
=============================================================================
Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
Sprint: Sprint 3
Architectural Layer: View (Delivery/Earnings)
Controller: RiderLedgerController@index, savePayoutSettings, requestPayout
=============================================================================
--}}
@extends('delivery.layout')

@section('title', 'Rider Earnings & Payout Ledger | Meramot')

@section('content')
@php
    $threshold = $minThreshold ?? 500.00;
    $canWithdraw = ($agent ?? null) && ($unsettledBalance ?? 0) >= $threshold;
    $savedMethod = $agent?->payout_method ?? 'BKASH';
    $savedAccount = $agent?->payout_account_number ?? auth()->user()?->phone ?? '';
@endphp

<div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-[#163625]">Rider Earnings &amp; Payout Ledger</h1>
            <p class="text-xs text-[#163625]/70 mt-1">Real-time trip commissions, courier bonuses, and instant mobile wallet withdrawals</p>
        </div>
        <a href="/delivery" class="self-start sm:self-auto rounded-full bg-[#163625] px-4 py-2 text-xs font-bold text-[#E4FCD5] hover:opacity-90 transition">
            <i class="fa-solid fa-arrow-left mr-1"></i> Delivery Dashboard
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-300 bg-emerald-50 p-4 text-xs font-semibold text-emerald-950 flex items-center gap-3">
            <i class="fa-solid fa-circle-check text-emerald-700 text-base"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs font-semibold text-amber-950 flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-amber-700 text-base"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if(session('error') || $errors->any())
        <div class="rounded-2xl border border-rose-300 bg-rose-50 p-4 text-xs font-semibold text-rose-900">
            {{ session('error') ?? $errors->first() }}
        </div>
    @endif

    <!-- 4-Card Wallet KPI Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-4">
        <div class="bg-white p-5 rounded-2xl border border-[#d9e5d5] shadow-sm">
            <span class="text-xs text-[#163625]/65 block font-semibold">Cumulative Lifetime Earned</span>
            <span class="text-2xl font-extrabold text-[#163625] mt-1.5 block">BDT {{ number_format($totalEarned ?? 0, 2) }}</span>
            <span class="text-[11px] text-[#163625]/60 mt-1 block">{{ isset($earnings) ? $earnings->count() : 0 }} completed courier trips</span>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-emerald-300 shadow-sm">
            <span class="text-xs text-emerald-800 block font-bold">Available Unsettled Balance</span>
            <span class="text-2xl font-extrabold text-emerald-700 mt-1.5 block">BDT {{ number_format($unsettledBalance ?? 0, 2) }}</span>
            <span class="text-[11px] text-emerald-800/80 mt-1 block">Min withdrawal: BDT {{ number_format($threshold, 0) }}</span>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-[#d9e5d5] shadow-sm">
            <span class="text-xs text-amber-800 block font-semibold">Pending Treasury Disbursement</span>
            <span class="text-2xl font-extrabold text-amber-700 mt-1.5 block">BDT {{ number_format($pendingWithdrawal ?? 0, 2) }}</span>
            <span class="text-[11px] text-[#163625]/60 mt-1 block">Awaiting Admin payout release</span>
        </div>

        <div class="bg-white p-5 rounded-2xl border border-[#d9e5d5] shadow-sm">
            <span class="text-xs text-[#163625]/65 block font-semibold">Settled &amp; Paid Out</span>
            <span class="text-2xl font-extrabold text-[#163625] mt-1.5 block">BDT {{ number_format($settledBalance ?? 0, 2) }}</span>
            <span class="text-[11px] text-[#163625]/60 mt-1 block">Disbursed to mobile wallet</span>
        </div>
    </div>

    <!-- Payout Destination & Instant Withdrawal Request -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <div class="lg:col-span-7 rounded-3xl border border-[#d9e5d5] bg-white p-5 sm:p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-extrabold text-[#163625]">Request Instant Wallet Withdrawal</h2>
                    <p class="text-xs text-[#163625]/65 mt-0.5">Withdraw unsettled courier commissions directly to bKash, Nagad, Rocket, or Bank</p>
                </div>
                <span class="rounded-full bg-[#eef4ea] border border-[#d9e5d5] px-3 py-1 text-[11px] font-bold text-[#163625]">
                    0% Rider Fee
                </span>
            </div>

            <form method="POST" action="{{ route('delivery.payout.request') }}" class="space-y-4 text-xs">
                @csrf
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label class="block font-bold text-[#163625] mb-1">Payout Channel</label>
                        <select name="payout_method" class="w-full rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] px-3.5 py-2.5 font-bold text-[#163625] focus:outline-none focus:ring-2 focus:ring-[#163625]">
                            @foreach(['BKASH' => 'bKash Personal / Agent', 'NAGAD' => 'Nagad Mobile Wallet', 'ROCKET' => 'DBBL Rocket', 'BANK' => 'BEFTN Bank Account'] as $code => $label)
                                <option value="{{ $code }}" @selected($savedMethod === $code)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-[#163625] mb-1">Wallet / Account Number</label>
                        <input type="text" name="payout_account_number" value="{{ old('payout_account_number', $savedAccount) }}"
                               placeholder="017XXXXXXXX" required
                               class="w-full rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] px-3.5 py-2.5 font-mono font-bold text-[#163625] focus:outline-none focus:ring-2 focus:ring-[#163625]">
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#d9e5d5] bg-[#f3faf0] px-4 py-3">
                    <div>
                        <p class="font-bold text-[#163625]">Eligible Withdrawal Amount: <span class="text-emerald-700 font-black">BDT {{ number_format($unsettledBalance ?? 0, 2) }}</span></p>
                        <p class="text-[11px] text-[#163625]/70">
                            @if(($unsettledBalance ?? 0) >= $threshold)
                                Ready for instant disbursement approval by Meramot Treasury.
                            @else
                                Complete trips worth BDT {{ number_format(max(0, $threshold - ($unsettledBalance ?? 0)), 2) }} more to unlock instant withdrawal.
                            @endif
                        </p>
                    </div>
                    <button type="submit"
                            class="px-5 py-2.5 rounded-full text-xs font-extrabold transition {{ $canWithdraw ? 'bg-[#163625] text-[#E4FCD5] hover:opacity-90 shadow' : 'bg-[#163625]/80 text-[#E4FCD5] hover:opacity-90' }}">
                        <i class="fa-solid fa-paper-plane mr-1"></i> Withdraw BDT {{ number_format($unsettledBalance ?? 0, 0) }}
                    </button>
                </div>
            </form>
        </div>

        <!-- Saved Payout Preferences Quick Card -->
        <div class="lg:col-span-5 rounded-3xl border border-[#d9e5d5] bg-white p-5 sm:p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-base font-extrabold text-[#163625]">Default Payout Account</h2>
                <p class="text-xs text-[#163625]/65 mt-0.5">Update your default mobile banking number anytime</p>
            </div>
            <form method="POST" action="{{ route('delivery.payout.settings') }}" class="space-y-3 text-xs">
                @csrf
                <div>
                    <label class="block font-bold text-[#163625] mb-1">Preferred Method</label>
                    <select name="payout_method" class="w-full rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] px-3 py-2 font-bold text-[#163625]">
                        @foreach(['BKASH' => 'bKash', 'NAGAD' => 'Nagad', 'ROCKET' => 'Rocket', 'BANK' => 'Bank Transfer'] as $code => $label)
                            <option value="{{ $code }}" @selected($savedMethod === $code)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-[#163625] mb-1">Registered Account Number</label>
                    <input type="text" name="payout_account_number" value="{{ $savedAccount }}" placeholder="017XXXXXXXX" required
                           class="w-full rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] px-3 py-2 font-mono font-bold text-[#163625]">
                </div>
                <button type="submit" class="w-full rounded-full border border-[#163625] bg-[#eef4ea] hover:bg-[#d9e5d5] py-2.5 px-4 font-extrabold text-[#163625] transition">
                    Save Default Wallet
                </button>
            </form>
        </div>
    </div>

    <!-- Withdrawal History Table -->
    @if(isset($payoutHistory) && $payoutHistory->count() > 0)
        <div class="rounded-3xl border border-[#d9e5d5] bg-white p-5 sm:p-6 shadow-sm">
            <h2 class="text-base font-extrabold text-[#163625] mb-4">Withdrawal Disbursement History</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-[#d9e5d5] text-[10px] font-black uppercase tracking-wider text-[#163625]/70">
                            <th class="py-2.5 px-3">Reference</th>
                            <th class="py-2.5 px-3">Channel &amp; Account</th>
                            <th class="py-2.5 px-3">Requested At</th>
                            <th class="py-2.5 px-3">Status</th>
                            <th class="py-2.5 px-3 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#d9e5d5]">
                        @foreach($payoutHistory as $pyt)
                            <tr>
                                <td class="py-3 px-3 font-mono font-bold text-[#163625]">
                                    {{ $pyt->reference_code }}
                                    @if($pyt->transaction_ref)
                                        <span class="block text-[10px] text-emerald-700">TXN: {{ $pyt->transaction_ref }}</span>
                                    @endif
                                </td>
                                <td class="py-3 px-3 font-semibold">{{ $pyt->payout_method ?? 'BKASH' }} • {{ $pyt->payout_account ?? 'N/A' }}</td>
                                <td class="py-3 px-3 text-[#163625]/70">{{ optional($pyt->created_at)->format('d M Y, h:i A') }}</td>
                                <td class="py-3 px-3">
                                    @php
                                        $st = strtoupper((string) $pyt->status);
                                        $badgeCls = $st === 'PAID'
                                            ? 'bg-emerald-100 text-emerald-900 border-emerald-300'
                                            : ($st === 'PENDING' ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-rose-100 text-rose-900 border-rose-300');
                                    @endphp
                                    <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-extrabold {{ $badgeCls }}">{{ $st }}</span>
                                </td>
                                <td class="py-3 px-3 text-right font-extrabold text-[#163625]">BDT {{ number_format((float) $pyt->amount, 2) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    <!-- Completed Trip Earnings Breakdown -->
    <div class="rounded-3xl border border-[#d9e5d5] bg-white p-5 sm:p-6 shadow-sm">
        <h2 class="text-base font-extrabold text-[#163625] mb-4">Completed Trip Earnings Ledger</h2>
        @if(isset($earnings) && $earnings->count() > 0)
            <div class="space-y-2.5">
                @foreach($earnings as $row)
                    @php
                        $pStatus = strtolower((string) $row->payout_status);
                        $rowBadge = $pStatus === 'settled'
                            ? 'bg-emerald-100 border-emerald-300 text-emerald-900'
                            : ($pStatus === 'requested' ? 'bg-sky-100 border-sky-300 text-sky-900' : 'bg-amber-100 border-amber-300 text-amber-900');
                    @endphp
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-2xl border border-[#d9e5d5] bg-[#f8fbf6] px-4 py-3 text-xs">
                        <div>
                            <span class="font-bold text-[#163625]">Delivery Trip #{{ $row->delivery_job_id }}</span>
                            @if($row->deliveryJob?->direction)
                                <span class="ml-1.5 text-[10px] font-bold px-2 py-0.5 rounded-full bg-white border border-[#d9e5d5]">{{ $row->deliveryJob->direction }}</span>
                            @endif
                            <span class="ml-2 text-[#163625]/65">{{ optional($row->created_at)->format('d M Y, h:i A') }}</span>
                            @if((float) $row->bonus_amount > 0)
                                <span class="ml-2 text-emerald-700 font-semibold">(Base ৳{{ number_format((float) $row->trip_fee, 0) }} + Express Bonus ৳{{ number_format((float) $row->bonus_amount, 0) }})</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-3">
                            @if($row->payout_reference)
                                <span class="font-mono text-[10px] text-[#163625]/65">{{ $row->payout_reference }}</span>
                            @endif
                            <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase {{ $rowBadge }}">{{ $row->payout_status }}</span>
                            <span class="font-extrabold text-[#163625]">BDT {{ number_format((float) $row->total_earned, 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-2xl border border-dashed border-[#d9e5d5] bg-[#f8fbf6] p-8 text-center text-xs text-[#163625]/70">
                <i class="fa-solid fa-route text-2xl text-[#163625]/40 mb-2 block"></i>
                No completed courier trips credited yet. Accept and complete a dispatch from the <a href="/delivery/jobs-queue" class="font-bold underline">Open Job Pool</a> to start earning!
            </div>
        @endif
    </div>
</div>
@endsection

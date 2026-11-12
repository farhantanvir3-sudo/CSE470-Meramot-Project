{{--
=============================================================================
FEATURE R3: Delivery Agent Earnings Ledger & Payout Tracker
=============================================================================
Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
Architectural Layer: View (Delivery/Earnings)
Controller: RiderLedgerController@index, requestPayout
=============================================================================
--}}
@extends('delivery.layout')

@section('title', 'Rider Earnings & Payout Ledger | Meramot')

@section('content')
<div class="space-y-6">
    <!-- Sprint 3 Roadmap Notice -->
    <div class="rounded-3xl border border-amber-300 bg-amber-50/90 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="h-10 w-10 rounded-2xl bg-amber-100 border border-amber-300 flex items-center justify-center text-amber-800 shrink-0">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-900">Scheduled for Sprint 3 &bull; Coming Soon</span>
                <h2 class="text-sm font-black text-amber-950">Automated Courier Wallet &amp; Mobile Banking Payouts</h2>
                <p class="text-xs text-amber-900 mt-0.5">Instant bKash / Nagad rider payout withdrawals roll out in Sprint 3.</p>
            </div>
        </div>
        <a href="/delivery" class="rounded-full bg-[#163625] px-4 py-2 text-xs font-bold text-[#E4FCD5] hover:opacity-90 transition shrink-0">
            Delivery Dashboard &rarr;
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-extrabold text-[#163625]">Rider Earnings &amp; Payout Ledger</h1>
            <p class="text-xs text-[#163625]/70 mt-1">Completed trip commissions credited to your Meramot Courier Wallet</p>
        </div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-5">
        <div class="bg-white p-6 rounded-2xl border border-[#d9e5d5] shadow-sm">
            <span class="text-xs text-[#163625]/65 block font-semibold">Cumulative Total Earned</span>
            <span class="text-3xl font-extrabold text-[#163625] mt-2 block">BDT {{ number_format($totalEarned ?? 0, 2) }}</span>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-[#d9e5d5] shadow-sm">
            <span class="text-xs text-[#163625]/65 block font-semibold">Unsettled Wallet Balance</span>
            <span class="text-3xl font-extrabold text-emerald-700 mt-2 block">BDT {{ number_format($unsettledBalance ?? 0, 2) }}</span>
        </div>
        <div class="bg-white p-6 rounded-2xl border border-[#d9e5d5] shadow-sm flex items-center justify-between gap-3">
            <div>
                <span class="text-xs text-[#163625]/65 block font-semibold">Payout Gateway</span>
                <span class="text-xs font-bold text-[#163625] mt-1 block">bKash / Nagad (Sprint 3)</span>
            </div>
            <form method="POST" action="/delivery/payout/request">
                @csrf
                <button type="submit" class="px-5 py-2.5 bg-[#163625] text-[#E4FCD5] rounded-full text-xs font-bold hover:opacity-90 transition">
                    Withdraw
                </button>
            </form>
        </div>
    </div>

    @if(isset($earnings) && $earnings->count() > 0)
        <div class="rounded-3xl border border-[#d9e5d5] bg-white p-6 shadow-sm">
            <h2 class="text-base font-bold text-[#163625] mb-4">Completed Trip Earnings Breakdown</h2>
            <div class="space-y-2.5">
                @foreach($earnings as $row)
                    <div class="flex items-center justify-between rounded-2xl border border-[#d9e5d5] bg-[#f8fbf6] px-4 py-3 text-xs">
                        <div>
                            <span class="font-bold text-[#163625]">Delivery Trip #{{ $row->delivery_job_id }}</span>
                            <span class="ml-2 text-[#163625]/65">{{ optional($row->created_at)->format('d M Y') }}</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="rounded-full bg-amber-100 border border-amber-300 px-2.5 py-0.5 text-[10px] font-bold uppercase text-amber-900">{{ $row->payout_status }}</span>
                            <span class="font-extrabold text-[#163625]">BDT {{ number_format((float) $row->total_earned, 2) }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection

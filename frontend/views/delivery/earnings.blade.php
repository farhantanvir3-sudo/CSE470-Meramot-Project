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
@extends('layouts.app')

@section('title', 'Rider Earnings & Payout Ledger | Meramot')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 md:px-6 space-y-6">
    <!-- Sprint 3 Roadmap Notice -->
    <div class="rounded-[1.75rem] border border-amber-300 bg-amber-50/90 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="h-10 w-10 rounded-2xl bg-amber-100 border border-amber-300 flex items-center justify-center text-amber-800 shrink-0">
                <i class="fa-solid fa-wallet"></i>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-900">Scheduled for Sprint 3 • Coming Soon</span>
                <h2 class="text-sm font-black text-amber-950">Automated Courier Wallet & Mobile Banking Payouts</h2>
                <p class="text-xs text-amber-900 mt-0.5">Instant bKash / Nagad rider payout withdrawals roll out in Sprint 3.</p>
            </div>
        </div>
        <a href="/delivery-agent/register" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition shrink-0">
            Rider Application Portal (Live) &rarr;
        </a>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Rider Earnings & Payout Ledger</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 dev-only-badge">Feature R3 | Assigned to: <strong class="text-[var(--accent-dark)]">Md. Nafiul Islam [22299035]</strong></p>
        </div>
        <span class="dev-only-badge text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-1.5 rounded-full border border-[var(--border)] self-start">
            Sprint 3 Preview
        </span>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
        <div class="bg-[var(--card)] p-6 rounded-[2rem] border border-[var(--border)] shadow-sm">
            <span class="text-[11px] text-[var(--muted-foreground)] block uppercase font-bold tracking-wider">Cumulative Total Earned</span>
            <span class="text-2xl font-black text-[var(--foreground)] mt-1 block">৳{{ number_format($totalEarned ?? 0, 2) }}</span>
        </div>
        <div class="bg-[var(--card)] p-6 rounded-[2rem] border border-[var(--border)] shadow-sm">
            <span class="text-[11px] text-[var(--muted-foreground)] block uppercase font-bold tracking-wider">Unsettled Wallet Balance</span>
            <span class="text-2xl font-black text-[var(--accent-dark)] mt-1 block">৳{{ number_format($unsettledBalance ?? 0, 2) }}</span>
        </div>
        <div class="bg-[var(--card)] p-6 rounded-[2rem] border border-[var(--border)] shadow-sm flex items-center justify-between gap-3">
            <div>
                <span class="text-[11px] text-[var(--muted-foreground)] block uppercase font-bold tracking-wider">Payout Gateway</span>
                <span class="text-xs font-bold text-[var(--foreground)] mt-1 block">bKash / Nagad (Sprint 3)</span>
            </div>
            <form method="POST" action="/delivery/payout/request">
                @csrf
                <button type="submit" class="px-5 py-2.5 bg-[var(--accent-dark)] text-[var(--accent-foreground)] rounded-full text-xs font-bold hover:opacity-90 transition">
                    Withdraw
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

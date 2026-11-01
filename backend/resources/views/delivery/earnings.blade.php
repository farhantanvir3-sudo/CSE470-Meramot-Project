{{--
=============================================================================
FEATURE R3: Delivery Agent Earnings Ledger & Payout Tracker
=============================================================================
Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
Architectural Layer: View (Delivery/Earnings)
Controller: RiderLedgerController@index, requestPayout
Models Touched: RiderEarnings, Payout

INSTRUCTIONS FOR NAFIUL:
- Show total earnings card, unsettled balance, and settled payouts.
- Include "Request Payout Withdrawal" button (requires min 500 BDT balance).
- List trip-by-trip historical delivery fee breakdown with date timestamps.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Rider Earnings & Payout Ledger | Meramot')

@section('content')
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Rider Earnings & Payout Ledger</h1>
            <p class="text-xs text-slate-500 mt-1 dev-only-badge">Feature R3 | Assigned to: <strong class="text-teal-700">Md. Nafiul Islam [22299035]</strong></p>
        </div>
        <span class="dev-only-badge text-xs bg-amber-100 text-amber-800 font-semibold px-3 py-1 rounded-full border border-amber-200">
            Sprint 3 Target
        </span>
    </div>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <span class="text-xs text-slate-400 block uppercase font-medium">Cumulative Total Earned</span>
        <span class="text-2xl font-black text-slate-900">৳{{ number_format($totalEarned ?? 4500, 2) }}</span>
    </div>
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <span class="text-xs text-slate-400 block uppercase font-medium">Unsettled Wallet Balance</span>
        <span class="text-2xl font-black text-emerald-600">৳{{ number_format($unsettledBalance ?? 1250, 2) }}</span>
    </div>
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm flex items-center justify-between">
        <div>
            <span class="text-xs text-slate-400 block uppercase font-medium">Payout Status</span>
            <span class="text-xs font-semibold text-slate-700">bKash Ready</span>
        </div>
        <form method="POST" action="/delivery/payout/request">
            @csrf
            <button type="submit" class="px-4 py-2 bg-emerald-600 text-white rounded-lg text-xs font-semibold hover:bg-emerald-700">
                Withdraw
            </button>
        </form>
    </div>
</div>
@endsection

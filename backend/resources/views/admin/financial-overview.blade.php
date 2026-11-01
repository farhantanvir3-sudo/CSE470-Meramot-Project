{{--
=============================================================================
FEATURE R16: Platform Financial Summary & Double-Entry Ledger
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
Architectural Layer: View (Admin/FinancialOverview)
Controller: AdminLedgerController@index
Models Touched: FinancialLedger, Transaction

INSTRUCTIONS FOR NAWSHAD:
- Double-entry platform balance card (Credits vs Debits).
- Table displaying immutable ledger log entries (entry_type, amount, balance_after).
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Platform Financial Overview | Feature R16')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Platform Financial Double-Entry Ledger</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R16 | Assigned to: <strong class="text-teal-700">Md. Nawshad Zaman Khan [23301622]</strong></p>
    </div>
    <span class="text-xs bg-amber-100 text-amber-800 font-semibold px-3 py-1 rounded-full border border-amber-200">
        Sprint 1 Target
    </span>
</div>

<div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-8">
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <span class="text-xs text-slate-400 block uppercase font-medium">Total Platform Inflow (Credits)</span>
        <span class="text-2xl font-black text-emerald-600">৳{{ number_format($totalCredits ?? 154200, 2) }}</span>
    </div>
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <span class="text-xs text-slate-400 block uppercase font-medium">Disbursements & Rider Fees (Debits)</span>
        <span class="text-2xl font-black text-rose-600">৳{{ number_format($totalDebits ?? 82000, 2) }}</span>
    </div>
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <span class="text-xs text-slate-400 block uppercase font-medium">Net Platform Commission Reserve</span>
        <span class="text-2xl font-black text-slate-900">৳{{ number_format($netPlatformBalance ?? 72200, 2) }}</span>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <div class="p-4 border-b border-slate-200 font-semibold text-xs text-slate-700 uppercase">
        Real-Time Immutable Transaction Journal
    </div>
    <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-slate-500 uppercase font-semibold">
            <tr>
                <th class="px-6 py-3 text-left">Entry ID</th>
                <th class="px-6 py-3 text-left">Category</th>
                <th class="px-6 py-3 text-left">Type</th>
                <th class="px-6 py-3 text-right">Amount (৳)</th>
                <th class="px-6 py-3 text-right">Balance After (৳)</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 font-mono">
            @forelse($entries ?? [] as $entry)
                <tr>
                    <td class="px-6 py-3 text-slate-900">#{{ $entry->id }}</td>
                    <td class="px-6 py-3 text-slate-600">{{ $entry->account_category }}</td>
                    <td class="px-6 py-3 uppercase {{ $entry->entry_type === 'credit' ? 'text-emerald-600 font-bold' : 'text-rose-600 font-bold' }}">
                        {{ $entry->entry_type }}
                    </td>
                    <td class="px-6 py-3 text-right font-bold">{{ number_format($entry->amount, 2) }}</td>
                    <td class="px-6 py-3 text-right text-slate-500">{{ number_format($entry->balance_after, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400">No ledger transactions posted yet.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

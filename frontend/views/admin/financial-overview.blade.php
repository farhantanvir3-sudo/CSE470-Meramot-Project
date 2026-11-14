@extends('layouts.app')

@section('title', 'Platform Financial Double-Entry Ledger | Meramot')

{{-- ==========================================================================
     FEATURE R16: Platform Financial Summary & Double-Entry Ledger
     Original Author: Md. Nawshad Zaman Khan [Student ID: 23301622] (Commit 4742c9c)
     Relocated From: backend/resources/views/admin/financial-overview.blade.php
     Relocated To:   frontend/views/admin/financial-overview.blade.php
     Merged with Original Meramot Financial Business Intelligence (`AdminFinancePage`):
       - Farhan Tanvir [Student ID: 22299179]
       - 6 BI Summary Tiles, Fund Distribution breakdown, Shop filter dropdown,
         Manual Settlement & Batch Auto-Settlement interactive cards, and
         `.dev-only-badge` visibility alignment
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-7xl px-3.5 py-4 sm:px-4 sm:py-6 md:px-6 space-y-4 sm:space-y-8">

    @include('admin.partials.nav')

    <!-- Header with Student & Feature Badge (Nawshad [23301622] — Commit 4742c9c) -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 sm:gap-4 border-b border-[var(--border)] pb-4 sm:pb-5">
        <div>
            <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-[0.22em] text-[var(--muted-foreground)]">Analytics & Accounting</p>
            <div class="dev-only-badge flex flex-wrap items-center gap-2 mt-1">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800">
                    <i class="fa-solid fa-code-commit mr-1 text-[10px]"></i> Feature R16
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-blue-100 text-blue-900 dark:bg-blue-950/80 dark:text-blue-300 border border-blue-300 dark:border-blue-800">
                    Sprint 1 Target
                </span>
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)]">
                    Double-Entry GAAP Accounting
                </span>
            </div>
            <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-[var(--foreground)] mt-1 sm:mt-2 tracking-tight flex items-start sm:items-center gap-2 leading-tight">
                <i class="fa-solid fa-scale-balanced text-emerald-600 dark:text-emerald-400 mt-1 sm:mt-0 shrink-0"></i>
                <span>Financial Business Intelligence & Double-Entry Ledger</span>
            </h1>
            <p class="text-[11px] sm:text-xs text-[var(--muted-foreground)] mt-1 leading-relaxed">
                Real-time tracking of platform revenue, 10% commission flow, escrow reserves, and vendor payout distributions.
            </p>
            <p class="dev-only-badge text-xs text-[var(--muted-foreground)] mt-1">
                Assigned Developer: <strong class="text-[var(--foreground)] font-bold">Md. Nawshad Zaman Khan</strong>
                (Student ID: <code class="bg-[var(--mint-200)] px-1.5 py-0.5 rounded text-[var(--accent-dark)] font-mono font-bold">23301622</code>)
            </p>
        </div>

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center text-[11px] sm:text-xs font-semibold text-[var(--foreground)] bg-[var(--card)] border border-[var(--border)] px-3 py-1.5 rounded-full shadow-sm">
                <i class="fa-solid fa-shield-halved text-emerald-600 dark:text-emerald-400 mr-2"></i> Immutable Ledger Active
            </span>
        </div>
    </div>

    <!-- Flash Feedback Banner -->
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-600/40 bg-[var(--mint-100)] px-5 py-3.5 text-xs font-bold text-[var(--accent-dark)] flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    {{-- ======================================================================
         RELOCATED BLOCK 1: Cumulative Financial Cards (2x2 on Mobile, 4-up Desktop)
         ====================================================================== --}}
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4 sm:gap-y-5">

        <!-- Cumulative Credits -->
        <div class="meramot-card rounded-2xl sm:rounded-3xl p-3.5 sm:p-5 shadow-sm transition hover:scale-[1.01] flex flex-col justify-between min-w-0">
            <div class="flex items-start justify-between gap-1.5">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider leading-tight text-[var(--muted-foreground)]">
                    <span class="sm:hidden">Inflow (Credits)</span>
                    <span class="hidden sm:inline">Cumulative Inflow (Credits)</span>
                </span>
                <div class="w-7 h-7 sm:w-9 sm:h-9 shrink-0 rounded-xl bg-emerald-100 dark:bg-emerald-950/70 border border-emerald-300/40 dark:border-emerald-800 flex items-center justify-center text-emerald-700 dark:text-emerald-300 shadow-sm">
                    <i class="fa-solid fa-arrow-down text-xs sm:text-sm"></i>
                </div>
            </div>
            <div>
                <p class="text-sm sm:text-2xl xl:text-3xl font-black text-emerald-700 dark:text-emerald-400 mt-2 sm:mt-3 font-mono tracking-tight break-all sm:break-normal">
                    ৳{{ number_format($totalCredits, 2) }}
                </p>
                <p class="text-[10px] sm:text-[11px] text-[var(--muted-foreground)] mt-1 flex items-center leading-snug">
                    <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400 mr-1 text-[10px] shrink-0"></i>
                    <span class="sm:hidden">Payments &amp; fees</span>
                    <span class="hidden sm:inline">Customer payments &amp; fees</span>
                </p>
            </div>
        </div>

        <!-- Cumulative Debits -->
        <div class="meramot-card rounded-2xl sm:rounded-3xl p-3.5 sm:p-5 shadow-sm transition hover:scale-[1.01] flex flex-col justify-between min-w-0">
            <div class="flex items-start justify-between gap-1.5">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider leading-tight text-[var(--muted-foreground)]">
                    <span class="sm:hidden">Outflow (Debits)</span>
                    <span class="hidden sm:inline">Cumulative Outflow (Debits)</span>
                </span>
                <div class="w-7 h-7 sm:w-9 sm:h-9 shrink-0 rounded-xl bg-rose-100 dark:bg-rose-950/70 border border-rose-300/40 dark:border-rose-800 flex items-center justify-center text-rose-700 dark:text-rose-300 shadow-sm">
                    <i class="fa-solid fa-arrow-up text-xs sm:text-sm"></i>
                </div>
            </div>
            <div>
                <p class="text-sm sm:text-2xl xl:text-3xl font-black text-rose-700 dark:text-rose-400 mt-2 sm:mt-3 font-mono tracking-tight break-all sm:break-normal">
                    ৳{{ number_format($totalDebits, 2) }}
                </p>
                <p class="text-[10px] sm:text-[11px] text-[var(--muted-foreground)] mt-1 flex items-center leading-snug">
                    <i class="fa-solid fa-money-bill-transfer text-rose-600 dark:text-rose-400 mr-1 text-[10px] shrink-0"></i>
                    <span class="sm:hidden">Payouts &amp; refunds</span>
                    <span class="hidden sm:inline">Vendor payouts, riders &amp; refunds</span>
                </p>
            </div>
        </div>

        <!-- 10% Platform Commission Revenue -->
        <div class="meramot-card rounded-2xl sm:rounded-3xl p-3.5 sm:p-5 shadow-sm transition hover:scale-[1.01] flex flex-col justify-between min-w-0">
            <div class="flex items-start justify-between gap-1.5">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider leading-tight text-[var(--muted-foreground)]">
                    <span class="sm:hidden">10% Commission</span>
                    <span class="hidden sm:inline">10% Platform Commission</span>
                </span>
                <div class="w-7 h-7 sm:w-9 sm:h-9 shrink-0 rounded-xl bg-teal-100 dark:bg-teal-950/70 border border-teal-300/40 dark:border-teal-800 flex items-center justify-center text-teal-700 dark:text-teal-300 shadow-sm">
                    <i class="fa-solid fa-percent text-xs sm:text-sm"></i>
                </div>
            </div>
            <div>
                <p class="text-sm sm:text-2xl xl:text-3xl font-black text-teal-700 dark:text-teal-400 mt-2 sm:mt-3 font-mono tracking-tight break-all sm:break-normal">
                    ৳{{ number_format($totalCommissionRevenue, 2) }}
                </p>
                <p class="text-[10px] sm:text-[11px] text-[var(--muted-foreground)] mt-1 flex items-center leading-snug">
                    <i class="fa-solid fa-receipt text-teal-600 dark:text-teal-400 mr-1 text-[10px] shrink-0"></i>
                    <span class="sm:hidden">Retained 10% fee</span>
                    <span class="hidden sm:inline">Net service fee retained (10%)</span>
                </p>
            </div>
        </div>

        <!-- Net Platform Reserve -->
        <div class="rounded-2xl sm:rounded-3xl p-3.5 sm:p-5 shadow-sm border border-[var(--border)] bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 dark:from-[#0d1611] dark:via-[#14231b] dark:to-[#1b3125] text-white transition hover:scale-[1.01] flex flex-col justify-between min-w-0">
            <div class="flex items-start justify-between gap-1.5">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider leading-tight text-emerald-300">
                    <span class="sm:hidden">Net Reserve</span>
                    <span class="hidden sm:inline">Net Platform Reserve</span>
                </span>
                <div class="w-7 h-7 sm:w-9 sm:h-9 shrink-0 rounded-xl bg-white/10 dark:bg-emerald-400/10 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-vault text-xs sm:text-sm"></i>
                </div>
            </div>
            <div>
                <p class="text-sm sm:text-2xl xl:text-3xl font-black text-emerald-300 mt-2 sm:mt-3 font-mono tracking-tight break-all sm:break-normal">
                    ৳{{ number_format($netPlatformBalance, 2) }}
                </p>
                <p class="text-[10px] sm:text-[11px] text-slate-300 mt-1 flex items-center leading-snug">
                    <i class="fa-solid fa-scale-balanced mr-1 text-[10px] text-emerald-400 shrink-0"></i>
                    <span class="sm:hidden">Credits minus debits</span>
                    <span class="hidden sm:inline">Balance after debits &amp; credits</span>
                </p>
            </div>
        </div>
    </div>

    {{-- ======================================================================
         RELOCATED BLOCK 2: 10% Platform Commission Accounting Breakdown
         ====================================================================== --}}
    <div class="meramot-card rounded-2xl sm:rounded-[2rem] p-4 sm:p-6 shadow-sm bg-[var(--mint-100)] border border-[var(--border)] space-y-4 sm:space-y-5">
        <div class="space-y-3 sm:space-y-4">
            <div class="max-w-3xl">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="px-2.5 py-0.5 text-[10px] font-black uppercase rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] tracking-wide">
                        10% Rule Engine
                    </span>
                    <h3 class="text-xs sm:text-sm font-black text-[var(--foreground)]">Automated Platform Commission &amp; Escrow Accounting</h3>
                </div>
                <p class="text-[11px] sm:text-xs text-[var(--muted-foreground)] mt-1.5 sm:mt-2 leading-relaxed">
                    Meramot enforces an automated <strong>10% platform commission</strong> deduction on gross electronic repair tickets. The remaining <strong>90%</strong> is credited to vendor escrow accounts for banking/bKash settlement.
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2 sm:gap-3 text-center">
                <div class="meramot-card px-2.5 py-2.5 sm:px-4 sm:py-3 shadow-none border border-[var(--border)] bg-[var(--card)] min-w-0">
                    <span class="text-[9px] sm:text-[10px] font-bold text-[var(--muted-foreground)] uppercase block tracking-wide">Total Volume</span>
                    <span class="mt-1 block text-xs sm:text-sm font-black text-[var(--foreground)] font-mono whitespace-nowrap">৳{{ number_format($totalCustomerInflow, 2) }}</span>
                </div>
                <div class="meramot-card px-2.5 py-2.5 sm:px-4 sm:py-3 shadow-none border border-[var(--border)] bg-[var(--card)] min-w-0">
                    <span class="text-[9px] sm:text-[10px] font-bold text-[var(--muted-foreground)] uppercase block tracking-wide">Commission (10%)</span>
                    <span class="mt-1 block text-xs sm:text-sm font-black text-teal-700 dark:text-teal-400 font-mono whitespace-nowrap">৳{{ number_format($totalCommissionRevenue, 2) }}</span>
                </div>
                <div class="meramot-card px-2.5 py-2.5 sm:px-4 sm:py-3 shadow-none border border-[var(--border)] bg-[var(--card)] min-w-0">
                    <span class="text-[9px] sm:text-[10px] font-bold text-[var(--muted-foreground)] uppercase block tracking-wide">Vendor Payouts</span>
                    <span class="mt-1 block text-xs sm:text-sm font-black text-[var(--foreground)] font-mono whitespace-nowrap">৳{{ number_format($totalVendorDisbursed ?? 0, 2) }}</span>
                </div>
                <div class="meramot-card px-2.5 py-2.5 sm:px-4 sm:py-3 shadow-none border border-[var(--border)] bg-[var(--card)] min-w-0">
                    <span class="text-[9px] sm:text-[10px] font-bold text-[var(--muted-foreground)] uppercase block tracking-wide">Refunds Issued</span>
                    <span class="mt-1 block text-xs sm:text-sm font-black text-rose-700 dark:text-rose-400 font-mono whitespace-nowrap">৳{{ number_format($totalRefunds ?? 0, 2) }}</span>
                </div>
                <div class="meramot-card px-2.5 py-2.5 sm:px-4 sm:py-3 shadow-none border border-[var(--border)] bg-[var(--card)] min-w-0">
                    <span class="text-[9px] sm:text-[10px] font-bold text-[var(--muted-foreground)] uppercase block tracking-wide">Escrow Balance</span>
                    <span class="mt-1 block text-xs sm:text-sm font-black text-amber-700 dark:text-amber-400 font-mono whitespace-nowrap">৳{{ number_format($pendingEscrowReserve, 2) }}</span>
                </div>
                <div class="meramot-card px-2.5 py-2.5 sm:px-4 sm:py-3 shadow-none border border-[var(--border)] bg-[var(--card)] min-w-0">
                    <span class="text-[9px] sm:text-[10px] font-bold text-[var(--muted-foreground)] uppercase block tracking-wide">Open Settlements</span>
                    <span class="mt-1 block text-xs sm:text-sm font-black text-[var(--accent-dark)] font-mono whitespace-nowrap">{{ $pendingSettlementCount ?? 0 }}</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Fund Distribution & Manual / Batch Settlement Controls (Merged from Original Meramot `AdminFinancePage`) -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-5">

        <!-- Fund Distribution Breakdown -->
        <div class="meramot-card p-6 shadow-sm space-y-4">
            <div>
                <h3 class="text-sm font-black text-[var(--foreground)]">Fund Distribution by Flow</h3>
                <p class="text-[11px] text-[var(--muted-foreground)]">Transaction volume & count across ledger categories</p>
            </div>
            <div class="space-y-3 text-xs">
                @forelse($fundDistribution ?? [] as $dist)
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <span class="font-bold text-[var(--foreground)]">{{ $dist['label'] }} <span class="text-[10px] font-normal text-[var(--muted-foreground)]">({{ $dist['count'] }})</span></span>
                            <span class="font-mono font-bold text-[var(--accent-dark)]">৳{{ number_format($dist['amount'], 2) }}</span>
                        </div>
                        <div class="h-2 w-full rounded-full bg-[var(--mint-100)] overflow-hidden">
                            <div class="h-full rounded-full bg-[var(--accent-dark)]" style="width: {{ max(6, $dist['share_pct']) }}%"></div>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-[var(--muted-foreground)]">No distribution activity yet.</p>
                @endforelse
            </div>
        </div>

        <!-- Manual Settlement Card (Uses Nawshad's `AdminLedgerController::settleOrderWithCommission`) -->
        <form method="POST" action="{{ route('admin.financial-overview.settle') }}" class="meramot-card p-6 shadow-sm flex flex-col justify-between space-y-4 text-xs">
            @csrf
            <div class="space-y-3">
                <div>
                    <h3 class="text-sm font-black text-[var(--foreground)]">Manual Order Settlement</h3>
                    <p class="text-[11px] text-[var(--muted-foreground)]">
                        Post a single repair order settlement with automated 10% platform commission & 90% vendor escrow allocation.
                    </p>
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Select Verified Workshop *</label>
                    <select name="shop_id" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs font-semibold text-[var(--foreground)] outline-none">
                        @foreach($shops as $shopOption)
                            <option value="{{ $shopOption->id }}">{{ $shopOption->name }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Gross Amount (৳) *</label>
                        <input type="number" step="0.01" min="50" name="gross_amount" placeholder="e.g. 4500" required
                               class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs text-[var(--foreground)] outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Audit Note</label>
                        <input type="text" name="note" placeholder="Optional reference"
                               class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs text-[var(--foreground)] outline-none">
                    </div>
                </div>
            </div>

            <button type="submit" class="w-full rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] py-2.5 text-xs font-bold shadow-sm hover:opacity-90 transition">
                Confirm 10% / 90% Settlement
            </button>
        </form>

        <!-- Batch Operations Card (Ported from Original Meramot `AdminFinancePage`) -->
        <form method="POST" action="{{ route('admin.financial-overview.auto-settle') }}" class="meramot-card p-6 shadow-sm flex flex-col justify-between space-y-4 text-xs">
            @csrf
            <div class="space-y-3">
                <div>
                    <h3 class="text-sm font-black text-[var(--foreground)]">Batch Escrow Operations</h3>
                    <p class="text-[11px] text-[var(--muted-foreground)]">
                        Execute automated batch disbursement across eligible vendor escrow earning allocations.
                    </p>
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Batch Settlement Limit</label>
                    <input type="number" name="limit" value="20" min="1" max="50"
                           class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none">
                </div>

                <div class="rounded-xl bg-[var(--mint-100)]/70 border border-[var(--border)] p-3 text-[11px] text-[var(--muted-foreground)]">
                    <i class="fa-solid fa-bolt text-amber-600 mr-1"></i>
                    Converts unsettled vendor earnings into disbursed payout debit entries in the ledger.
                </div>
            </div>

            <button type="submit" class="w-full rounded-full border-2 border-[var(--accent-dark)] py-2.5 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)] transition">
                Run Batch Settlement
            </button>
        </form>
    </div>

    <!-- Customer Refund & Post-Settlement Dispute Resolution Desk -->
    <div class="meramot-card p-6 shadow-sm space-y-5 border border-[var(--border)]">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[var(--border)] pb-3">
            <div>
                <h3 class="text-sm font-black text-[var(--foreground)] flex items-center gap-2">
                    <i class="fa-solid fa-rotate-left text-rose-600"></i>
                    <span>Refunds &amp; Post-Settlement Dispute Resolution</span>
                </h3>
                <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                    Orders flagged with post-settlement issues. Issuing a refund credits the customer, resolves the dispute, and records a refund debit entry in the double-entry ledger.
                </p>
            </div>
            <span class="inline-flex items-center rounded-full bg-rose-100 px-3 py-1 text-[11px] font-bold text-rose-800">
                {{ ($pendingRefundDisputes ?? collect())->count() }} Pending Dispute(s)
            </span>
        </div>

        @if(($pendingRefundDisputes ?? collect())->isNotEmpty())
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
                @foreach($pendingRefundDisputes as $dispute)
                    <form method="POST" action="{{ route('admin.financial-overview.issue-refund') }}" class="rounded-2xl border border-rose-200 bg-rose-50/40 dark:bg-rose-950/20 p-4 space-y-3 text-xs">
                        @csrf
                        <input type="hidden" name="payment_id" value="{{ $dispute->payment_id }}">
                        <input type="hidden" name="dispute_case_id" value="{{ $dispute->dispute_id }}">

                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex flex-wrap items-center gap-1.5">
                                    <span class="rounded-full bg-rose-600 px-2.5 py-0.5 text-[10px] font-bold text-white">{{ $dispute->dispute_status }}</span>
                                    <span class="font-mono text-[11px] font-bold text-[var(--muted-foreground)]">{{ $dispute->transaction_reference }}</span>
                                    <span class="rounded-full bg-[var(--mint-100)] px-2 py-0.5 text-[10px] font-bold text-[var(--foreground)]">{{ $dispute->payment_method }}</span>
                                </div>
                                <h4 class="mt-1.5 text-sm font-extrabold text-[var(--foreground)]">{{ $dispute->order_title }}</h4>
                                <p class="text-[11px] text-[var(--muted-foreground)]">
                                    Customer: <strong class="text-[var(--foreground)]">{{ $dispute->customer_name }}</strong>
                                    · Workshop: <strong class="text-[var(--foreground)]">{{ $dispute->shop_name ?? 'Verified Workshop' }}</strong>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <span class="text-[10px] uppercase font-bold text-[var(--muted-foreground)] block">Settled Paid</span>
                                <span class="text-sm font-black font-mono text-rose-700">৳{{ number_format($dispute->paid_amount, 2) }}</span>
                            </div>
                        </div>

                        <p class="rounded-xl border border-rose-200/70 bg-white/80 dark:bg-[var(--card)] px-3 py-2 text-[11px] text-[var(--foreground)] leading-relaxed">
                            <i class="fa-solid fa-triangle-exclamation text-amber-600 mr-1"></i>
                            {{ $dispute->dispute_reason }}
                        </p>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2 items-end">
                            <div>
                                <label class="block text-[10px] font-bold text-[var(--muted-foreground)] uppercase mb-1">Refund Amount (৳)</label>
                                <input type="number" step="0.01" min="1" max="{{ $dispute->paid_amount }}" name="refund_amount" value="{{ $dispute->paid_amount }}" required
                                       class="w-full rounded-xl border border-[var(--border)] bg-white dark:bg-[var(--card)] px-3 py-2 text-xs font-mono font-bold text-[var(--foreground)] outline-none">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block text-[10px] font-bold text-[var(--muted-foreground)] uppercase mb-1">Refund Reason</label>
                                <input type="text" name="reason" value="Admin approved post-settlement vendor issue refund" required
                                       class="w-full rounded-xl border border-[var(--border)] bg-white dark:bg-[var(--card)] px-3 py-2 text-xs text-[var(--foreground)] outline-none">
                            </div>
                        </div>

                        <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-full bg-rose-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition">
                            <i class="fa-solid fa-rotate-left"></i>
                            <span>Issue Refund &amp; Post to Ledger</span>
                        </button>
                    </form>
                @endforeach
            </div>
        @else
            <p class="text-xs text-[var(--muted-foreground)]">No open post-settlement disputes awaiting refund. You can still issue a refund against any settled payment below.</p>
        @endif

        @if(($settledPayments ?? collect())->isNotEmpty())
            <form method="POST" action="{{ route('admin.financial-overview.issue-refund') }}" class="rounded-2xl border border-[var(--border)] bg-[var(--mint-50)] p-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end text-xs">
                @csrf
                <div class="lg:col-span-2">
                    <label class="block font-bold text-[var(--foreground)] mb-1">Manual Refund on Any Settled Payment</label>
                    <select name="payment_id" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-xs font-semibold text-[var(--foreground)] outline-none">
                        @foreach($settledPayments as $sp)
                            <option value="{{ $sp->id }}">
                                {{ $sp->transaction_reference }} — {{ $sp->shop_name ?? 'Workshop' }} (৳{{ number_format($sp->amount, 2) }})
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Refund Amount (৳)</label>
                    <input type="number" step="0.01" min="1" name="refund_amount" placeholder="e.g. 1500" required
                           class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-xs font-mono text-[var(--foreground)] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Reason</label>
                    <input type="text" name="reason" placeholder="Vendor issue / customer claim" required
                           class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-xs text-[var(--foreground)] outline-none">
                </div>
                <button type="submit" class="rounded-full bg-rose-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition">
                    Issue Refund
                </button>
            </form>
        @endif
    </div>

    {{-- ======================================================================
         RELOCATED BLOCK 3: Filter Bar & Search (with Shop Filter added)
         Original Author: Md. Nawshad Zaman Khan [23301622] (Commit 4742c9c)
         ====================================================================== --}}
    <div class="meramot-card p-4 sm:p-5 shadow-sm">
        <form method="GET" action="{{ url('/admin/financial-overview') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-3">

            <!-- Search -->
            <div class="lg:col-span-2 relative">
                <span class="absolute inset-y-0 left-0 flex items-center pl-3.5 pointer-events-none text-[var(--muted-foreground)]">
                    <i class="fa-solid fa-magnifying-glass text-xs"></i>
                </span>
                <input
                    type="text"
                    name="search"
                    value="{{ $searchTerm }}"
                    placeholder="Search by entry ID, shop, or description..."
                    class="w-full pl-9 pr-3 py-2 text-xs rounded-xl border border-[var(--border)] bg-[var(--mint-100)] text-[var(--foreground)] placeholder:text-[var(--muted-foreground)] focus:ring-2 focus:ring-[var(--accent-dark)] focus:outline-none transition"
                >
            </div>

            <!-- Entry Type Filter -->
            <div>
                <select name="entry_type" class="w-full py-2 px-3 text-xs rounded-xl border border-[var(--border)] bg-[var(--mint-100)] text-[var(--foreground)] focus:ring-2 focus:ring-[var(--accent-dark)] focus:outline-none transition">
                    <option value="">All Categories</option>
                    @foreach(\App\Models\FinancialLedger::ENTRY_TYPES as $key => $label)
                        <option value="{{ $key }}" {{ $selectedEntryType === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <!-- Direction Filter -->
            <div>
                <select name="direction" class="w-full py-2 px-3 text-xs rounded-xl border border-[var(--border)] bg-[var(--mint-100)] text-[var(--foreground)] focus:ring-2 focus:ring-[var(--accent-dark)] focus:outline-none transition">
                    <option value="">All Flow Types (Credit/Debit)</option>
                    <option value="CREDIT" {{ $selectedDirection === 'CREDIT' ? 'selected' : '' }}>CREDIT (Inflow +)</option>
                    <option value="DEBIT" {{ $selectedDirection === 'DEBIT' ? 'selected' : '' }}>DEBIT (Outflow -)</option>
                </select>
            </div>

            <!-- Shop Filter -->
            <div>
                <select name="shop_id" class="w-full py-2 px-3 text-xs rounded-xl border border-[var(--border)] bg-[var(--mint-100)] text-[var(--foreground)] focus:ring-2 focus:ring-[var(--accent-dark)] focus:outline-none transition">
                    <option value="">All Repair Shops</option>
                    @foreach($shops as $shopItem)
                        <option value="{{ $shopItem->id }}" {{ (string) $selectedShop === (string) $shopItem->id ? 'selected' : '' }}>
                            {{ $shopItem->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 bg-[var(--accent-dark)] text-[var(--accent-foreground)] hover:opacity-90 font-bold text-xs py-2 px-4 rounded-xl transition shadow-sm flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-filter text-[10px]"></i> Filter
                </button>
                @if($searchTerm || $selectedEntryType || $selectedDirection || $selectedShop)
                    <a href="{{ url('/admin/financial-overview') }}" class="text-xs bg-[var(--mint-200)] hover:bg-[var(--mint-300)] text-[var(--foreground)] font-semibold px-3 py-2 rounded-xl transition">
                        Reset
                    </a>
                @endif
            </div>
        </form>
    </div>

    {{-- ======================================================================
         RELOCATED BLOCK 4: Immutable Real-Time Double-Entry Ledger Journal
         Original Author: Md. Nawshad Zaman Khan [23301622] (Commit 4742c9c)
         ====================================================================== --}}
    <div
        class="meramot-card overflow-hidden shadow-sm"
        x-data="{
            pageIds: @js($entries->getCollection()->filter(fn ($e) => !empty($e->is_deletable_manual_audit))->pluck('id')->map(fn ($id) => (int) $id)->values()),
            selectedIds: [],
            deleteModalOpen: false,
            deleteMode: 'records_only',
            submittingDelete: false,
            get allPageSelected() {
                return this.pageIds.length > 0 && this.pageIds.every(id => this.selectedIds.includes(id));
            },
            toggleAllPage() {
                if (this.allPageSelected) {
                    this.selectedIds = this.selectedIds.filter(id => !this.pageIds.includes(id));
                } else {
                    const merged = new Set([...this.selectedIds, ...this.pageIds]);
                    this.selectedIds = Array.from(merged);
                }
            },
            toggleRow(id) {
                if (this.selectedIds.includes(id)) {
                    this.selectedIds = this.selectedIds.filter(item => item !== id);
                } else {
                    this.selectedIds.push(id);
                }
            },
            openBulkDeleteModal() {
                if (this.selectedIds.length === 0) return;
                this.deleteMode = 'records_only';
                this.submittingDelete = false;
                this.deleteModalOpen = true;
            },
            openSingleDeleteModal(id) {
                this.selectedIds = [id];
                this.deleteMode = 'records_only';
                this.submittingDelete = false;
                this.deleteModalOpen = true;
            }
        }"
    >
        <div class="px-6 py-4 border-b border-[var(--border)] bg-[var(--mint-100)] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-sm font-black text-[var(--foreground)] uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-book-bookmark text-emerald-600 dark:text-emerald-400"></i>
                    Immutable Double-Entry Ledger Journal
                </h2>
                <p class="text-xs text-[var(--muted-foreground)] mt-0.5">Real-time chronologically audited ledger transactions and calculated running balance.</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <template x-if="selectedIds.length > 0">
                    <div class="inline-flex items-center gap-2 rounded-full border border-rose-300 bg-rose-50 px-3 py-1.5 text-xs font-bold text-rose-800 shadow-sm">
                        <span x-text="selectedIds.length + ' selected'"></span>
                        <button
                            type="button"
                            @click="openBulkDeleteModal()"
                            class="inline-flex items-center gap-1 rounded-full bg-rose-600 px-3 py-1 text-[11px] font-bold text-white hover:bg-rose-700 transition"
                        >
                            <i class="fa-solid fa-trash-can text-[10px]"></i>
                            <span>Delete Selected</span>
                        </button>
                        <button
                            type="button"
                            @click="selectedIds = []"
                            class="text-[11px] font-semibold text-rose-700 hover:underline px-1"
                        >
                            Clear
                        </button>
                    </div>
                </template>
                <span class="text-xs font-mono font-bold text-[var(--muted-foreground)]">
                    Showing {{ $entries->firstItem() ?? 0 }}–{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} entries
                </span>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-xs text-left">
                <thead class="bg-[var(--mint-50)] text-[var(--muted-foreground)] uppercase font-bold tracking-wider">
                    <tr>
                        <th class="px-4 py-3.5 w-10 text-center">
                            <input
                                type="checkbox"
                                :checked="allPageSelected"
                                :disabled="pageIds.length === 0"
                                @change="toggleAllPage()"
                                title="Select all manual adjusted settlement audits on this page"
                                aria-label="Select all manual adjusted settlement audits on this page"
                                class="h-4 w-4 rounded accent-[#214c34] cursor-pointer disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                        </th>
                        <th class="px-5 py-3.5">Entry #</th>
                        <th class="px-5 py-3.5">Effective Date</th>
                        <th class="px-5 py-3.5">Category / Flow</th>
                        <th class="px-5 py-3.5">Related Shop</th>
                        <th class="px-5 py-3.5 text-center">Direction</th>
                        <th class="px-5 py-3.5 text-right">Amount</th>
                        <th class="px-5 py-3.5 text-right">Running Balance</th>
                        <th class="px-5 py-3.5">Description</th>
                        <th class="px-4 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @forelse($entries as $entry)
                        <tr
                            class="hover:bg-[var(--mint-50)]/70 transition"
                            :class="selectedIds.includes({{ (int) $entry->id }}) ? 'bg-rose-50/40 dark:bg-rose-950/20' : ''"
                        >
                            <!-- Multi-Select Checkbox (Only Manual Adjusted Settlement Audits) -->
                            <td class="px-4 py-3.5 text-center">
                                @if(!empty($entry->is_deletable_manual_audit))
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes({{ (int) $entry->id }})"
                                        @change="toggleRow({{ (int) $entry->id }})"
                                        aria-label="Select manual audit entry #{{ $entry->id }}"
                                        class="h-4 w-4 rounded accent-[#214c34] cursor-pointer"
                                    >
                                @else
                                    <span class="inline-flex items-center justify-center text-slate-300 dark:text-slate-600" title="Immutable system ledger entry (cannot be deleted)">
                                        <i class="fa-solid fa-lock text-[11px]"></i>
                                    </span>
                                @endif
                            </td>

                            <!-- Entry ID -->
                            <td class="px-5 py-3.5 font-mono font-bold text-[var(--foreground)]">
                                #{{ str_pad($entry->id, 5, '0', STR_PAD_LEFT) }}
                            </td>

                            <!-- Effective Date -->
                            <td class="px-5 py-3.5 text-[var(--muted-foreground)] font-mono text-[11px] whitespace-nowrap">
                                {{ optional($entry->effective_at ?? $entry->created_at)->format('Y-m-d H:i') }}
                            </td>

                            <!-- Category Badge -->
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold
                                    @if($entry->entry_type === 'PLATFORM_COMMISSION') bg-teal-100 text-teal-900 dark:bg-teal-950/80 dark:text-teal-300 border border-teal-300 dark:border-teal-800
                                    @elseif($entry->entry_type === 'CUSTOMER_PAYMENT') bg-emerald-100 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800
                                    @elseif($entry->entry_type === 'VENDOR_EARNING') bg-blue-100 text-blue-900 dark:bg-blue-950/80 dark:text-blue-300 border border-blue-300 dark:border-blue-800
                                    @elseif($entry->entry_type === 'VENDOR_PAYOUT') bg-purple-100 text-purple-900 dark:bg-purple-950/80 dark:text-purple-300 border border-purple-300 dark:border-purple-800
                                    @elseif($entry->entry_type === 'REFUND') bg-rose-100 text-rose-900 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300 dark:border-rose-800
                                    @else bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] @endif
                                ">
                                    {{ \App\Models\FinancialLedger::ENTRY_TYPES[$entry->entry_type] ?? $entry->entry_type }}
                                </span>
                            </td>

                            <!-- Shop -->
                            <td class="px-5 py-3.5 text-[var(--foreground)] whitespace-nowrap">
                                @if($entry->shop)
                                    <span class="font-bold text-[var(--foreground)]">{{ $entry->shop->name }}</span>
                                @else
                                    <span class="text-[var(--muted-foreground)] italic">Platform Core</span>
                                @endif
                            </td>

                            <!-- Direction Badge -->
                            <td class="px-5 py-3.5 text-center whitespace-nowrap">
                                @if(!empty($entry->is_allocation))
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300 border border-slate-300/60 dark:border-slate-700 uppercase tracking-wider" title="Internal sub-ledger allocation (does not double-count cash)">
                                        <i class="fa-solid fa-layer-group mr-1 text-[9px]"></i> ALLOC
                                    </span>
                                @elseif($entry->direction === 'CREDIT')
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black bg-emerald-100 text-emerald-900 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300/50 dark:border-emerald-800 uppercase tracking-wider">
                                        <i class="fa-solid fa-plus mr-1 text-[9px]"></i> CREDIT
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-black bg-rose-100 text-rose-900 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300/50 dark:border-rose-800 uppercase tracking-wider">
                                        <i class="fa-solid fa-minus mr-1 text-[9px]"></i> DEBIT
                                    </span>
                                @endif
                            </td>

                            <!-- Amount -->
                            <td class="px-5 py-3.5 text-right font-mono font-black whitespace-nowrap text-xs {{ !empty($entry->is_allocation) ? 'text-slate-700 dark:text-slate-300' : ($entry->direction === 'CREDIT' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400') }}">
                                @if(!empty($entry->is_allocation))
                                    ৳{{ number_format($entry->amount, 2) }}
                                @else
                                    {{ $entry->direction === 'CREDIT' ? '+' : '-' }}৳{{ number_format($entry->amount, 2) }}
                                @endif
                            </td>

                            <!-- Running Balance -->
                            <td class="px-5 py-3.5 text-right font-mono font-black text-[var(--foreground)] whitespace-nowrap text-xs">
                                ৳{{ number_format($entry->running_balance ?? 0, 2) }}
                            </td>

                            <!-- Description -->
                            <td class="px-5 py-3.5 text-[var(--muted-foreground)] max-w-xs truncate text-[11px]" title="{{ $entry->description }}">
                                {{ $entry->description ?: '—' }}
                            </td>

                            <!-- Single Delete Trigger (Only Manual Adjusted Settlement Audits) -->
                            <td class="px-4 py-3.5 text-right whitespace-nowrap">
                                @if(!empty($entry->is_deletable_manual_audit))
                                    <button
                                        type="button"
                                        @click="openSingleDeleteModal({{ (int) $entry->id }})"
                                        title="Delete manual audit entry #{{ $entry->id }}"
                                        class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-rose-200 bg-rose-50 text-rose-700 hover:bg-rose-600 hover:text-white hover:border-rose-600 transition"
                                    >
                                        <i class="fa-solid fa-trash-can text-[11px]"></i>
                                    </button>
                                @else
                                    <span class="inline-flex h-8 w-8 items-center justify-center rounded-full border border-slate-200 dark:border-slate-700 bg-slate-50 dark:bg-slate-800/50 text-slate-400" title="Immutable system entry">
                                        <i class="fa-solid fa-shield-halved text-[11px]"></i>
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="10" class="px-6 py-12 text-center text-[var(--muted-foreground)]">
                                <div class="max-w-sm mx-auto">
                                    <div class="w-12 h-12 mx-auto rounded-full bg-[var(--mint-100)] flex items-center justify-center text-[var(--muted-foreground)] mb-3">
                                        <i class="fa-solid fa-receipt text-xl"></i>
                                    </div>
                                    <p class="font-bold text-[var(--foreground)] text-sm">No ledger entries found</p>
                                    <p class="text-xs text-[var(--muted-foreground)] mt-1">Once payments or payouts occur, immutable double-entry transactions will be logged here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($entries->hasPages())
            <div class="px-6 py-4 border-t border-[var(--border)] bg-[var(--mint-50)]">
                {{ $entries->links() }}
            </div>
        @endif

        <!-- ── Audit / Ledger Deletion Mode Confirmation Pop-Up Tile ── -->
        <div
            x-show="deleteModalOpen"
            x-cloak
            @keydown.escape.window="if (deleteModalOpen && !submittingDelete) deleteModalOpen = false"
            class="fixed inset-0 z-[110] flex items-center justify-center p-4"
            role="dialog"
            aria-modal="true"
            aria-labelledby="audit-delete-modal-title"
        >
            <div
                x-show="deleteModalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0"
                x-transition:enter-end="opacity-100"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100"
                x-transition:leave-end="opacity-0"
                class="absolute inset-0 bg-[#0b1f14]/55 backdrop-blur-sm"
                @click="if (!submittingDelete) deleteModalOpen = false"
            ></div>

            <div
                x-show="deleteModalOpen"
                x-transition:enter="transition ease-out duration-200"
                x-transition:enter-start="opacity-0 scale-95 translate-y-2"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-150"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-2"
                class="relative z-[111] w-full max-w-lg overflow-hidden rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-[0_24px_64px_rgba(11,31,20,0.28)]"
            >
                <div class="h-1.5 w-full bg-gradient-to-r from-rose-500 via-rose-600 to-amber-500"></div>

                <form method="POST" action="{{ route('admin.financial-overview.bulk-delete') }}" @submit="submittingDelete = true" class="p-7 sm:p-8 space-y-5">
                    @csrf
                    <template x-for="id in selectedIds" :key="id">
                        <input type="hidden" name="entry_ids[]" :value="id">
                    </template>
                    <input type="hidden" name="delete_mode" :value="deleteMode">

                    <div class="text-center">
                        <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 text-rose-600 shadow-inner">
                            <i class="fa-solid fa-trash-can text-xl"></i>
                        </div>
                        <h3 id="audit-delete-modal-title" class="text-xl font-extrabold text-[var(--foreground)]">
                            Delete Selected Audit Records?
                        </h3>
                        <div class="mt-2 inline-flex items-center gap-2 rounded-full border border-[var(--border)] bg-[var(--background)] px-3.5 py-1 text-xs font-bold text-[var(--foreground)]">
                            <span class="h-2 w-2 rounded-full bg-rose-500"></span>
                            <span x-text="selectedIds.length + (selectedIds.length === 1 ? ' audit entry selected' : ' audit entries selected')"></span>
                        </div>
                        <p class="mt-2.5 text-xs text-[var(--muted-foreground)]">
                            Choose whether to remove only the visible audit records or also reverse their financial effects on the ledger.
                        </p>
                    </div>

                    <div class="space-y-3 text-left">
                        <button
                            type="button"
                            @click="deleteMode = 'records_only'"
                            :class="deleteMode === 'records_only' ? 'border-[#214c34] bg-[var(--mint-100)] ring-2 ring-[#214c34]/20' : 'border-[var(--border)] bg-[var(--background)] hover:bg-[var(--mint-50)]'"
                            class="w-full rounded-2xl border p-4 text-left transition flex items-start gap-3"
                        >
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2" :class="deleteMode === 'records_only' ? 'border-[#214c34] bg-[#214c34] text-white' : 'border-slate-300'">
                                <i x-show="deleteMode === 'records_only'" class="fa-solid fa-check text-[10px]"></i>
                            </span>
                            <div>
                                <div class="text-xs font-extrabold text-[var(--foreground)]">Delete Records Only</div>
                                <p class="mt-0.5 text-[11px] leading-relaxed text-[var(--muted-foreground)]">
                                    Hides the selected audit entries from the journal table while preserving their cumulative amounts in Total Volume, Platform Commission, Escrow Balance, and Fund Distribution.
                                </p>
                            </div>
                        </button>

                        <button
                            type="button"
                            @click="deleteMode = 'undo_effects'"
                            :class="deleteMode === 'undo_effects' ? 'border-rose-600 bg-rose-50/70 ring-2 ring-rose-500/20' : 'border-[var(--border)] bg-[var(--background)] hover:bg-rose-50/30'"
                            class="w-full rounded-2xl border p-4 text-left transition flex items-start gap-3"
                        >
                            <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2" :class="deleteMode === 'undo_effects' ? 'border-rose-600 bg-rose-600 text-white' : 'border-slate-300'">
                                <i x-show="deleteMode === 'undo_effects'" class="fa-solid fa-check text-[10px]"></i>
                            </span>
                            <div>
                                <div class="text-xs font-extrabold text-rose-700">Delete &amp; Undo Ledger Effects</div>
                                <p class="mt-0.5 text-[11px] leading-relaxed text-[var(--muted-foreground)]">
                                    Permanently removes the selected entries and reverses their impact on Total Volume, Platform Commission, Vendor Escrow Balance, and Running Balances.
                                </p>
                            </div>
                        </button>
                    </div>

                    <div class="grid grid-cols-2 gap-3 pt-1">
                        <button
                            type="button"
                            @click="deleteModalOpen = false"
                            :disabled="submittingDelete"
                            class="rounded-full border border-[var(--border)] bg-[var(--background)] px-5 py-2.5 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-50)] transition"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            :disabled="submittingDelete"
                            :class="deleteMode === 'undo_effects' ? 'bg-rose-600 hover:bg-rose-700' : 'bg-[#163625] hover:bg-[#1d4630]'"
                            class="inline-flex items-center justify-center gap-2 rounded-full px-5 py-2.5 text-xs font-bold text-white shadow-md transition disabled:opacity-60"
                        >
                            <i class="fa-solid" :class="submittingDelete ? 'fa-spinner fa-spin' : 'fa-trash-can'"></i>
                            <span x-text="submittingDelete ? 'Processing...' : (deleteMode === 'undo_effects' ? 'Delete & Undo Effects' : 'Delete Records Only')"></span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>
@endsection

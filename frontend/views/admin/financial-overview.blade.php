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
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-8">

    <!-- Header with Student & Feature Badge (Nawshad [23301622] — Commit 4742c9c) -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 border-b border-[var(--border)] pb-5">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-[var(--muted-foreground)]">Analytics & Accounting</p>
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
            <h1 class="text-2xl sm:text-3xl font-black text-[var(--foreground)] mt-2 tracking-tight flex items-center gap-2">
                <i class="fa-solid fa-scale-balanced text-emerald-600 dark:text-emerald-400"></i>
                Financial Business Intelligence & Double-Entry Ledger
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Real-time tracking of platform revenue, 10% commission flow, escrow reserves, and vendor payout distributions.
            </p>
            <p class="dev-only-badge text-xs text-[var(--muted-foreground)] mt-1">
                Assigned Developer: <strong class="text-[var(--foreground)] font-bold">Md. Nawshad Zaman Khan</strong>
                (Student ID: <code class="bg-[var(--mint-200)] px-1.5 py-0.5 rounded text-[var(--accent-dark)] font-mono font-bold">23301622</code>)
            </p>
        </div>

        <div class="flex items-center gap-3">
            <span class="inline-flex items-center text-xs font-semibold text-[var(--foreground)] bg-[var(--card)] border border-[var(--border)] px-3.5 py-1.5 rounded-full shadow-sm">
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
         RELOCATED BLOCK 1: Cumulative Financial Cards (Double-Entry Balance)
         Original Author: Md. Nawshad Zaman Khan [23301622] (Commit 4742c9c)
         ====================================================================== --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-5">

        <!-- Cumulative Credits -->
        <div class="meramot-card p-5 shadow-sm transition hover:scale-[1.01]">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Cumulative Inflow (Credits)</span>
                <div class="w-9 h-9 rounded-xl bg-emerald-100 dark:bg-emerald-950/70 border border-emerald-300/40 dark:border-emerald-800 flex items-center justify-center text-emerald-700 dark:text-emerald-300 shadow-sm">
                    <i class="fa-solid fa-arrow-down-left text-sm"></i>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-emerald-700 dark:text-emerald-400 mt-3 font-mono">
                ৳{{ number_format($totalCredits, 2) }}
            </p>
            <p class="text-[11px] text-[var(--muted-foreground)] mt-1 flex items-center">
                <i class="fa-solid fa-circle-check text-emerald-600 dark:text-emerald-400 mr-1.5 text-[10px]"></i> Customer payments & fees
            </p>
        </div>

        <!-- Cumulative Debits -->
        <div class="meramot-card p-5 shadow-sm transition hover:scale-[1.01]">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Cumulative Outflow (Debits)</span>
                <div class="w-9 h-9 rounded-xl bg-rose-100 dark:bg-rose-950/70 border border-rose-300/40 dark:border-rose-800 flex items-center justify-center text-rose-700 dark:text-rose-300 shadow-sm">
                    <i class="fa-solid fa-arrow-up-right text-sm"></i>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-rose-700 dark:text-rose-400 mt-3 font-mono">
                ৳{{ number_format($totalDebits, 2) }}
            </p>
            <p class="text-[11px] text-[var(--muted-foreground)] mt-1 flex items-center">
                <i class="fa-solid fa-money-bill-transfer text-rose-600 dark:text-rose-400 mr-1.5 text-[10px]"></i> Vendor payouts, riders & refunds
            </p>
        </div>

        <!-- 10% Platform Commission Revenue -->
        <div class="meramot-card p-5 shadow-sm transition hover:scale-[1.01]">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">10% Platform Commission</span>
                <div class="w-9 h-9 rounded-xl bg-teal-100 dark:bg-teal-950/70 border border-teal-300/40 dark:border-teal-800 flex items-center justify-center text-teal-700 dark:text-teal-300 shadow-sm">
                    <i class="fa-solid fa-percent text-sm"></i>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-teal-700 dark:text-teal-400 mt-3 font-mono">
                ৳{{ number_format($totalCommissionRevenue, 2) }}
            </p>
            <p class="text-[11px] text-[var(--muted-foreground)] mt-1 flex items-center">
                <i class="fa-solid fa-receipt text-teal-600 dark:text-teal-400 mr-1.5 text-[10px]"></i> Net service fee retained (10%)
            </p>
        </div>

        <!-- Net Platform Reserve -->
        <div class="rounded-3xl p-5 shadow-sm border border-[var(--border)] bg-gradient-to-br from-slate-900 via-slate-800 to-emerald-950 dark:from-[#0d1611] dark:via-[#14231b] dark:to-[#1b3125] text-white transition hover:scale-[1.01]">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-300">Net Platform Reserve</span>
                <div class="w-9 h-9 rounded-xl bg-white/10 dark:bg-emerald-400/10 flex items-center justify-center text-emerald-400">
                    <i class="fa-solid fa-vault text-sm"></i>
                </div>
            </div>
            <p class="text-2xl sm:text-3xl font-black text-emerald-300 mt-3 font-mono">
                ৳{{ number_format($netPlatformBalance, 2) }}
            </p>
            <p class="text-[11px] text-slate-300 mt-1 flex items-center">
                <i class="fa-solid fa-scale-balanced mr-1.5 text-[10px] text-emerald-400"></i> Balance after debits & credits
            </p>
        </div>
    </div>

    {{-- ======================================================================
         RELOCATED BLOCK 2: 10% Platform Commission Accounting Breakdown
         Original Author: Md. Nawshad Zaman Khan [23301622] (Commit 4742c9c)
         Merged with Original Meramot `AdminFinancePage` 6 BI Metrics
         ====================================================================== --}}
    <div class="meramot-card p-6 shadow-sm bg-[var(--mint-100)] border border-[var(--border)] space-y-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6">
            <div class="max-w-xl">
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-0.5 text-[10px] font-black uppercase rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] tracking-wide">
                        10% Rule Engine
                    </span>
                    <h3 class="text-sm font-black text-[var(--foreground)]">Automated Platform Commission & Escrow Accounting</h3>
                </div>
                <p class="text-xs text-[var(--muted-foreground)] mt-2 leading-relaxed">
                    Meramot enforces an automated <strong>10% platform commission</strong> deduction on gross electronic repair tickets. The remaining <strong>90%</strong> is credited to vendor escrow accounts for banking/bKash settlement.
                </p>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 text-center">
                <div class="meramot-card px-3.5 py-2.5 shadow-none border border-[var(--border)] bg-[var(--card)]">
                    <span class="text-[10px] font-bold text-[var(--muted-foreground)] uppercase block">Total Volume</span>
                    <span class="text-xs font-black text-[var(--foreground)] font-mono">৳{{ number_format($totalCustomerInflow, 2) }}</span>
                </div>
                <div class="meramot-card px-3.5 py-2.5 shadow-none border border-[var(--border)] bg-[var(--card)]">
                    <span class="text-[10px] font-bold text-[var(--muted-foreground)] uppercase block">10% Cut</span>
                    <span class="text-xs font-black text-teal-700 dark:text-teal-400 font-mono">৳{{ number_format($totalCommissionRevenue, 2) }}</span>
                </div>
                <div class="meramot-card px-3.5 py-2.5 shadow-none border border-[var(--border)] bg-[var(--card)]">
                    <span class="text-[10px] font-bold text-[var(--muted-foreground)] uppercase block">Vendor Payouts</span>
                    <span class="text-xs font-black text-[var(--foreground)] font-mono">৳{{ number_format($totalVendorDisbursed ?? 0, 2) }}</span>
                </div>
                <div class="meramot-card px-3.5 py-2.5 shadow-none border border-[var(--border)] bg-[var(--card)]">
                    <span class="text-[10px] font-bold text-[var(--muted-foreground)] uppercase block">Refunds Issued</span>
                    <span class="text-xs font-black text-rose-700 dark:text-rose-400 font-mono">৳{{ number_format($totalRefunds ?? 0, 2) }}</span>
                </div>
                <div class="meramot-card px-3.5 py-2.5 shadow-none border border-[var(--border)] bg-[var(--card)]">
                    <span class="text-[10px] font-bold text-[var(--muted-foreground)] uppercase block">Escrow Balance</span>
                    <span class="text-xs font-black text-amber-700 dark:text-amber-400 font-mono">৳{{ number_format($pendingEscrowReserve, 2) }}</span>
                </div>
                <div class="meramot-card px-3.5 py-2.5 shadow-none border border-[var(--border)] bg-[var(--card)]">
                    <span class="text-[10px] font-bold text-[var(--muted-foreground)] uppercase block">Open Settlements</span>
                    <span class="text-xs font-black text-[var(--accent-dark)] font-mono">{{ $pendingSettlementCount ?? 0 }}</span>
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
                    Converts unsettled <code class="font-mono font-bold text-[var(--foreground)]">VENDOR_EARNING</code> rows into disbursed <code class="font-mono font-bold text-[var(--foreground)]">VENDOR_PAYOUT</code> debit entries.
                </div>
            </div>

            <button type="submit" class="w-full rounded-full border-2 border-[var(--accent-dark)] py-2.5 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)] transition">
                Run Batch Settlement
            </button>
        </form>
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
    <div class="meramot-card overflow-hidden shadow-sm">
        <div class="px-6 py-4 border-b border-[var(--border)] bg-[var(--mint-100)] flex flex-col sm:flex-row sm:items-center justify-between gap-2">
            <div>
                <h2 class="text-sm font-black text-[var(--foreground)] uppercase tracking-wider flex items-center gap-2">
                    <i class="fa-solid fa-book-bookmark text-emerald-600 dark:text-emerald-400"></i>
                    Immutable Double-Entry Ledger Journal
                </h2>
                <p class="text-xs text-[var(--muted-foreground)] mt-0.5">Real-time chronologically audited ledger transactions and calculated running balance.</p>
            </div>
            <span class="text-xs font-mono font-bold text-[var(--muted-foreground)]">
                Showing {{ $entries->firstItem() ?? 0 }}–{{ $entries->lastItem() ?? 0 }} of {{ $entries->total() }} entries
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-xs text-left">
                <thead class="bg-[var(--mint-50)] text-[var(--muted-foreground)] uppercase font-bold tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5">Entry #</th>
                        <th class="px-5 py-3.5">Effective Date</th>
                        <th class="px-5 py-3.5">Category / Flow</th>
                        <th class="px-5 py-3.5">Related Shop</th>
                        <th class="px-5 py-3.5 text-center">Direction</th>
                        <th class="px-5 py-3.5 text-right">Amount</th>
                        <th class="px-5 py-3.5 text-right">Running Balance</th>
                        <th class="px-5 py-3.5">Description</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @forelse($entries as $entry)
                        <tr class="hover:bg-[var(--mint-50)]/70 transition">

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
                                @if($entry->direction === 'CREDIT')
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
                            <td class="px-5 py-3.5 text-right font-mono font-black whitespace-nowrap text-xs {{ $entry->direction === 'CREDIT' ? 'text-emerald-700 dark:text-emerald-400' : 'text-rose-700 dark:text-rose-400' }}">
                                {{ $entry->direction === 'CREDIT' ? '+' : '-' }}৳{{ number_format($entry->amount, 2) }}
                            </td>

                            <!-- Running Balance -->
                            <td class="px-5 py-3.5 text-right font-mono font-black text-[var(--foreground)] whitespace-nowrap text-xs">
                                ৳{{ number_format($entry->running_balance ?? 0, 2) }}
                            </td>

                            <!-- Description -->
                            <td class="px-5 py-3.5 text-[var(--muted-foreground)] max-w-xs truncate text-[11px]" title="{{ $entry->description }}">
                                {{ $entry->description ?: '—' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-6 py-12 text-center text-[var(--muted-foreground)]">
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
    </div>

</div>
@endsection

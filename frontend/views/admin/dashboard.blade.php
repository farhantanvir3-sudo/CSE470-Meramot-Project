@extends('layouts.app')

@section('title', 'Admin Governance Console | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-3.5 py-4 sm:px-4 sm:py-6 md:px-6 space-y-4 sm:space-y-6 md:space-y-8">

    @include('admin.partials.nav')

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 border-b border-[var(--border)] pb-4 sm:pb-5">
        <div class="max-w-2xl">
            <p class="text-[10px] sm:text-[11px] font-bold uppercase tracking-[0.22em] text-[var(--accent-dark)]">Operations &amp; Governance</p>
            <h1 class="text-xl sm:text-2xl font-bold tracking-tight leading-tight text-[var(--foreground)] mt-0.5 sm:mt-1">
                Meramot Platform Admin Console
            </h1>
            <p class="text-[11px] sm:text-xs text-[var(--muted-foreground)] mt-1 leading-relaxed">
                Manage vendor approvals, courier onboarding, settlements, and marketplace activity.
            </p>
        </div>
        <div class="grid grid-cols-2 sm:flex md:flex-col items-stretch md:items-end justify-start md:justify-end gap-2 w-full sm:w-auto shrink-0 md:ml-auto md:text-right">
            <a href="/shops" class="whitespace-nowrap inline-flex items-center justify-center rounded-md border border-[#c8dac2] bg-[var(--card)] px-3 py-2 sm:px-4 text-[11px] sm:text-xs font-bold text-[var(--foreground)] shadow-sm transition-all duration-200 hover:border-[var(--accent-dark)] hover:bg-[var(--mint-100)] hover:shadow-md text-center">
                <span class="sm:hidden">Directory ({{ number_format($kpis['public_shops']) }})</span>
                <span class="hidden sm:inline">View Live Directory ({{ number_format($kpis['public_shops']) }} Shops)</span>
            </a>
            <a href="/admin/warranties" class="whitespace-nowrap inline-flex items-center justify-center gap-1.5 rounded-md border border-[#c8dac2] bg-[var(--card)] px-3 py-2 sm:px-4 text-[11px] sm:text-xs font-bold text-[var(--foreground)] shadow-sm transition-all duration-200 hover:border-[var(--accent-dark)] hover:bg-[var(--mint-100)] hover:shadow-md text-center">
                <i class="fa-solid fa-shield-halved text-[11px] text-[var(--accent-dark)]"></i>
                <span>Warranties ({{ (int) ($kpis['pending_warranty_claims'] ?? 0) }})</span>
            </a>
            <a href="/admin/financial-overview" class="whitespace-nowrap inline-flex items-center justify-center rounded-md bg-[var(--accent-dark)] px-3 py-2 sm:px-4 text-[11px] sm:text-xs font-bold text-[var(--accent-foreground)] shadow-sm transition-all duration-200 hover:opacity-90 hover:shadow-md text-center">
                <span class="sm:hidden">Ledger &rarr;</span>
                <span class="hidden sm:inline">Open Financial Ledger &rarr;</span>
            </a>
        </div>
    </div>

    <!-- Top KPI Grid (2x2 on Phone, 4-up on Desktop) -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
        <a href="/admin/vendors/pending" class="rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-3.5 sm:p-5 shadow-sm hover:border-[var(--accent-dark)] transition flex flex-col justify-between min-w-0">
            <div class="flex items-start justify-between gap-1.5">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider leading-tight text-[var(--muted-foreground)]">
                    <span class="sm:hidden">Pending Vendors</span>
                    <span class="hidden sm:inline">Pending Vendor Audits</span>
                </span>
                <span class="h-7 w-7 sm:h-9 sm:w-9 shrink-0 rounded-xl sm:rounded-lg bg-amber-100 text-amber-800 flex items-center justify-center text-xs sm:text-sm"><i class="fa-solid fa-store"></i></span>
            </div>
            <div>
                <div class="mt-2 sm:mt-3 text-2xl sm:text-3xl font-bold leading-none text-[var(--foreground)]">{{ $kpis['pending_vendors'] }}</div>
                <p class="mt-1.5 sm:mt-1 text-[10px] sm:text-xs leading-snug text-[var(--accent-dark)] font-semibold">
                    <span class="sm:hidden">{{ number_format($kpis['public_shops']) }} shops &bull; {{ $kpis['branched_brands'] }} branched &rarr;</span>
                    <span class="hidden sm:inline">{{ number_format($kpis['public_shops']) }} public storefronts &bull; {{ $kpis['branched_brands'] }} branched brands &rarr;</span>
                </p>
            </div>
        </a>

        <a href="/admin/delivery-agents/pending" class="rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-3.5 sm:p-5 shadow-sm hover:border-[var(--accent-dark)] transition flex flex-col justify-between min-w-0">
            <div class="flex items-start justify-between gap-1.5">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider leading-tight text-[var(--muted-foreground)]">
                    <span class="sm:hidden">Pending Riders</span>
                    <span class="hidden sm:inline">Pending Rider Verifications</span>
                </span>
                <span class="h-7 w-7 sm:h-9 sm:w-9 shrink-0 rounded-xl sm:rounded-lg bg-sky-100 text-sky-800 flex items-center justify-center text-xs sm:text-sm"><i class="fa-solid fa-motorcycle"></i></span>
            </div>
            <div>
                <div class="mt-2 sm:mt-3 text-2xl sm:text-3xl font-bold leading-none text-[var(--foreground)]">{{ $kpis['pending_riders'] }}</div>
                <p class="mt-1.5 sm:mt-1 text-[10px] sm:text-xs leading-snug text-[var(--accent-dark)] font-semibold">
                    <span class="sm:hidden">{{ $kpis['approved_riders'] }} active riders &rarr;</span>
                    <span class="hidden sm:inline">{{ $kpis['approved_riders'] }} approved riders active &rarr;</span>
                </p>
            </div>
        </a>

        <a href="/admin/financial-overview" class="rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-3.5 sm:p-5 shadow-sm hover:border-[var(--accent-dark)] transition flex flex-col justify-between min-w-0">
            <div class="flex items-start justify-between gap-1.5">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider leading-tight text-[var(--muted-foreground)]">
                    <span class="sm:hidden">Gross Volume</span>
                    <span class="hidden sm:inline">Gross Ledger Volume</span>
                </span>
                <span class="h-7 w-7 sm:h-9 sm:w-9 shrink-0 rounded-xl sm:rounded-lg bg-emerald-100 text-emerald-800 flex items-center justify-center text-xs sm:text-sm"><i class="fa-solid fa-scale-balanced"></i></span>
            </div>
            <div>
                <div class="mt-2 sm:mt-3 text-base sm:text-2xl font-bold leading-tight tracking-tight break-words text-[var(--foreground)]">
                    <span class="sm:hidden">৳{{ number_format($kpis['platform_volume'], 0) }}</span>
                    <span class="hidden sm:inline">৳{{ number_format($kpis['platform_volume'], 2) }}</span>
                </div>
                @php
                    $dashAvgRate = rtrim(rtrim(number_format((float) ($avgCommissionRatePct ?? 8.0), 2), '0'), '.');
                    $dashTrend = $platformIncomeTrend ?? [];
                @endphp
                <p class="mt-1.5 sm:mt-1 text-[10px] sm:text-xs leading-snug text-emerald-800 font-semibold">
                    <span class="sm:hidden">Fee (~{{ $dashAvgRate }}%): ৳{{ number_format($kpis['platform_commission'], 0) }} &rarr;</span>
                    <span class="hidden sm:inline">Platform Fee (~{{ $dashAvgRate }}%): ৳{{ number_format($kpis['platform_commission'], 2) }} ({{ $kpis['unsettled_tx_count'] }} unsettled) &rarr;</span>
                </p>
            </div>
        </a>

        <a href="#platform-tickets-oversight" class="rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-3.5 sm:p-5 shadow-sm hover:border-[var(--accent-dark)] transition flex flex-col justify-between min-w-0">
            <div class="flex items-start justify-between gap-1.5">
                <span class="text-[10px] sm:text-[11px] font-bold uppercase tracking-wider leading-tight text-[var(--muted-foreground)]">
                    <span class="sm:hidden">Active Tickets</span>
                    <span class="hidden sm:inline">Active Repair Tickets</span>
                </span>
                <span class="h-7 w-7 sm:h-9 sm:w-9 shrink-0 rounded-xl sm:rounded-lg bg-[#e4f3df] text-[#244229] flex items-center justify-center text-xs sm:text-sm"><i class="fa-solid fa-screwdriver-wrench"></i></span>
            </div>
            <div>
                <div class="mt-2 sm:mt-3 text-2xl sm:text-3xl font-bold leading-none text-[var(--foreground)]">{{ $kpis['open_requests'] }}</div>
                <p class="mt-1.5 sm:mt-1 text-[10px] sm:text-xs leading-snug text-[var(--muted-foreground)] font-semibold">
                    <span class="sm:hidden">{{ number_format($kpis['total_users']) }} total users &rarr;</span>
                    <span class="hidden sm:inline">{{ number_format($kpis['total_users']) }} registered accounts across roles &rarr;</span>
                </p>
            </div>
        </a>
    </div>

    <!-- Sprint 1 Primary Operations Desks -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">

        <!-- Pending Vendor Verification Snapshot (R11) -->
        <div class="rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-start sm:items-center justify-between gap-2 mb-3 sm:mb-4">
                    <div class="min-w-0">
                        <span class="dev-only-badge text-[10px] font-mono font-bold text-emerald-800 block mb-0.5">Feature R11 • Farhan Tanvir [22299179]</span>
                        <h2 class="text-sm sm:text-base font-bold leading-snug text-[var(--foreground)]">Vendor Business Verification Queue</h2>
                    </div>
                    <a href="/admin/vendors/pending" class="shrink-0 whitespace-nowrap rounded bg-[var(--accent-dark)] px-3 py-1.5 text-[10px] sm:text-[11px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                        <span class="sm:hidden">Review ({{ $kpis['pending_vendors'] }}) &rarr;</span>
                        <span class="hidden sm:inline">Review All ({{ $kpis['pending_vendors'] }}) &rarr;</span>
                    </a>
                </div>

                <div class="space-y-2.5 sm:space-y-3 text-xs">
                    @forelse($recentPendingVendors as $vp)
                        <div class="rounded-xl sm:rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/60 p-3 sm:p-3.5 flex items-center justify-between gap-2.5">
                            <div class="min-w-0 flex-1">
                                <div class="break-words font-bold text-[var(--foreground)]">{{ $vp->shop_name }}</div>
                                <div class="break-words text-[11px] text-[var(--muted-foreground)]">
                                    License: <span class="font-mono">{{ $vp->business_license_no }}</span> • {{ $vp->area }}, {{ $vp->city }}
                                </div>
                            </div>
                            <a href="/admin/vendors/pending" class="shrink-0 rounded-md border border-[var(--accent-dark)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)]">
                                Audit
                            </a>
                        </div>
                    @empty
                        <p class="rounded-xl sm:rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/40 p-3.5 text-center text-[var(--muted-foreground)]">
                            All pending vendor onboarding applications have been reviewed.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Pending Delivery Agent Verification Snapshot (R1) -->
        <div class="rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-start sm:items-center justify-between gap-2 mb-3 sm:mb-4">
                    <div class="min-w-0">
                        <span class="dev-only-badge text-[10px] font-mono font-bold text-emerald-800 block mb-0.5">Feature R1 • Md. Nafiul Islam [22299035]</span>
                        <h2 class="text-sm sm:text-base font-bold leading-snug text-[var(--foreground)]">Delivery Courier Credential Queue</h2>
                    </div>
                    <a href="/admin/delivery-agents/pending" class="shrink-0 whitespace-nowrap rounded bg-[var(--accent-dark)] px-3 py-1.5 text-[10px] sm:text-[11px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                        <span class="sm:hidden">Review ({{ $kpis['pending_riders'] }}) &rarr;</span>
                        <span class="hidden sm:inline">Review All ({{ $kpis['pending_riders'] }}) &rarr;</span>
                    </a>
                </div>

                <div class="space-y-2.5 sm:space-y-3 text-xs">
                    @forelse($recentPendingRiders as $agent)
                        <div class="rounded-xl sm:rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/60 p-3 sm:p-3.5 flex items-center justify-between gap-2.5">
                            <div class="min-w-0 flex-1">
                                <div class="break-words font-bold text-[var(--foreground)]">{{ $agent->applicant_name ?: ($agent->user->name ?? 'Rider Applicant #' . $agent->id) }}</div>
                                <div class="break-words text-[11px] text-[var(--muted-foreground)]">
                                    NID: <span class="font-mono">{{ $agent->nid_number }}</span> • License: <span class="font-mono">{{ $agent->license_number }}</span> ({{ ucfirst($agent->vehicle_type) }})
                                </div>
                            </div>
                            <a href="/admin/delivery-agents/pending" class="shrink-0 rounded-md border border-[var(--accent-dark)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)]">
                                Verify
                            </a>
                        </div>
                    @empty
                        <p class="rounded-xl sm:rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/40 p-3.5 text-center text-[var(--muted-foreground)]">
                            No pending delivery agent applications at this time.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Operations, Payout & Governance Modules (2x2 on Phone, 4-up on Desktop) -->
    <div class="rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/60 p-4 sm:p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3 sm:mb-4">
            <div>
                <h2 class="text-sm sm:text-base font-bold text-[var(--foreground)]">Operations, Payout &amp; Governance Modules</h2>
                <p class="text-[11px] sm:text-xs text-[var(--muted-foreground)]">Direct shortcuts to courier dispatch, vendor settlements, content moderation, and compliance auditing.</p>
            </div>
            <span class="hidden sm:inline-flex rounded-md border border-[var(--border)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
                Platform Operations
            </span>
        </div>

        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4 text-xs">
            <a href="/delivery/jobs-queue" class="rounded-xl sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 sm:p-4 hover:border-[var(--accent-dark)] transition flex flex-col justify-between min-w-0">
                <div>
                    <span class="inline-block rounded bg-[var(--mint-100)] px-2 sm:px-2.5 py-0.5 text-[9px] sm:text-[10px] font-bold text-[var(--accent-dark)]">Operations &bull; Queue</span>
                    <h3 class="mt-1.5 sm:mt-2 font-bold text-xs sm:text-sm leading-snug text-[var(--foreground)]">Rider Dispatch Queue</h3>
                </div>
                <p class="mt-1 text-[10px] sm:text-[11px] leading-snug line-clamp-2 sm:line-clamp-none text-[var(--muted-foreground)]">Live pickup and return trip allocation with OTP handover milestones.</p>
            </a>

            <a href="/admin/payouts/manage" class="rounded-xl sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 sm:p-4 hover:border-[var(--accent-dark)] transition flex flex-col justify-between min-w-0">
                <div>
                    <span class="inline-block rounded bg-[var(--mint-100)] px-2 sm:px-2.5 py-0.5 text-[9px] sm:text-[10px] font-bold text-[var(--accent-dark)]">Finance &bull; Scheduled</span>
                    <h3 class="mt-1.5 sm:mt-2 font-bold text-xs sm:text-sm leading-snug text-[var(--foreground)]">Vendor Payout Settlements</h3>
                </div>
                <p class="mt-1 text-[10px] sm:text-[11px] leading-snug line-clamp-2 sm:line-clamp-none text-[var(--muted-foreground)]">Automated workshop bank and mobile banking withdrawal disbursements.</p>
            </a>

            <a href="/admin/moderation" class="rounded-xl sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 sm:p-4 hover:border-[var(--accent-dark)] transition flex flex-col justify-between min-w-0">
                <div>
                    <span class="inline-block rounded bg-emerald-100 px-2 sm:px-2.5 py-0.5 text-[9px] sm:text-[10px] font-bold text-emerald-800">Active Governance</span>
                    <h3 class="mt-1.5 sm:mt-2 font-bold text-xs sm:text-sm leading-snug text-[var(--foreground)]">Content Moderation</h3>
                </div>
                <p class="mt-1 text-[10px] sm:text-[11px] leading-snug line-clamp-2 sm:line-clamp-none text-[var(--muted-foreground)]">Review removal, policy enforcement, and account restriction controls.</p>
            </a>

            <a href="/admin/audit-logs" class="rounded-xl sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 sm:p-4 hover:border-[var(--accent-dark)] transition flex flex-col justify-between min-w-0">
                <div>
                    <span class="inline-block rounded bg-emerald-100 px-2 sm:px-2.5 py-0.5 text-[9px] sm:text-[10px] font-bold text-emerald-800">Active Compliance</span>
                    <h3 class="mt-1.5 sm:mt-2 font-bold text-xs sm:text-sm leading-snug text-[var(--foreground)]">Security Audit Monitor</h3>
                </div>
                <p class="mt-1 text-[10px] sm:text-[11px] leading-snug line-clamp-2 sm:line-clamp-none text-[var(--muted-foreground)]">Immutable IP forensics and state-transition compliance audit trail.</p>
            </a>
        </div>
    </div>

    <!-- Best Performing Workshops & Platform Income Trajectory (Admin Governance) -->
    @if(session('success'))
        <div class="rounded-lg border border-emerald-600/40 bg-[var(--mint-100)] px-5 py-3 text-xs font-bold text-[var(--accent-dark)] flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-rose-500/40 bg-rose-50 px-5 py-3 text-xs font-bold text-rose-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
            <span>{{ $errors->first() }}</span>
        </div>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 sm:gap-6">
        <!-- Platform Income Trajectory Mini-Chart -->
        <div class="xl:col-span-5 rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm flex flex-col justify-between space-y-3">
            <div class="flex flex-wrap items-start justify-between gap-2">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--muted-foreground)]">{{ $dashTrend['granularity'] ?? 'Weekly' }} Income Rate</p>
                    <h2 class="text-sm sm:text-base font-bold text-[var(--foreground)]">Platform Revenue Trajectory</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Marketplace service fees &amp; courier logistics revenue</p>
                </div>
                @php
                    $dashDir = $dashTrend['direction'] ?? 'STEADY';
                    $dashDelta = (float) ($dashTrend['rate_delta_pct'] ?? 0);
                @endphp
                <span class="inline-flex items-center gap-1 rounded-md px-3 py-1 text-[11px] font-bold border
                    {{ $dashDir === 'UP' ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : ($dashDir === 'DOWN' ? 'bg-rose-100 text-rose-900 border-rose-300' : 'bg-[var(--mint-100)] text-[var(--accent-dark)] border-[var(--border)]') }}">
                    {{ $dashTrend['trend_label'] ?? 'Steady ●' }} ({{ $dashDelta > 0 ? '+' : '' }}{{ $dashDelta }}%)
                </span>
            </div>

            <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/35 p-2.5">
                <svg viewBox="0 0 {{ $dashTrend['svg_width'] ?? 640 }} {{ $dashTrend['svg_height'] ?? 190 }}" class="w-full h-36 sm:h-40 overflow-visible" role="img" aria-label="Admin Dashboard Platform Income Trend">
                    @if(!empty($dashTrend['area_income']))
                        <polygon points="{{ $dashTrend['area_income'] }}" fill="#047857" fill-opacity="0.14"/>
                    @endif
                    @if(!empty($dashTrend['polyline_income']))
                        <polyline fill="none" stroke="#047857" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="{{ $dashTrend['polyline_income'] }}"/>
                    @endif
                    @foreach(($dashTrend['points'] ?? []) as $pt)
                        <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y_income'] }}" r="4" fill="#047857" stroke="#ffffff" stroke-width="1.5">
                            <title>{{ $pt['label'] }}: ৳{{ number_format($pt['platform_income'], 2) }}</title>
                        </circle>
                        <text x="{{ $pt['x'] }}" y="183" text-anchor="middle" fill="currentColor" opacity="0.65" font-size="10" font-weight="700">{{ $pt['label'] }}</text>
                    @endforeach
                </svg>
            </div>

            <div class="grid grid-cols-2 gap-2 text-xs">
                <div class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/45 p-2.5">
                    <span class="block text-[9px] font-bold uppercase text-[var(--muted-foreground)]">Commission Fees</span>
                    <span class="font-mono font-bold text-emerald-800">৳{{ number_format($dashTrend['total_commission'] ?? 0, 0) }}</span>
                </div>
                <div class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/45 p-2.5">
                    <span class="block text-[9px] font-bold uppercase text-[var(--muted-foreground)]">Platform Delivery</span>
                    <span class="font-mono font-bold text-sky-800">৳{{ number_format($dashTrend['total_delivery'] ?? 0, 0) }}</span>
                </div>
            </div>
        </div>

        <!-- Best Performing Workshops Ranking & Inline Commission Adjustment -->
        <div class="xl:col-span-7 rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] pb-3">
                <div>
                    <p class="text-[10px] font-bold uppercase tracking-widest text-[var(--accent-dark)]">Top Ranked Partners</p>
                    <h2 class="text-sm sm:text-base font-bold text-[var(--foreground)]">Best Performing Workshops &amp; Base Fee Rates</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Base fee rates apply to workshop subtotals only; courier logistics are billed separately.</p>
                </div>
                <a href="/admin/financial-overview" class="text-xs font-bold text-[var(--accent-dark)] hover:underline whitespace-nowrap">Full Ledger &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead>
                        <tr class="border-b border-[var(--border)] text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)]">
                            <th class="py-2 pr-3">Workshop</th>
                            <th class="py-2 pr-3">Rating &amp; Jobs</th>
                            <th class="py-2 pr-3">Vendor Earnings</th>
                            <th class="py-2 text-right">Base Rate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]/60">
                        @forelse(($bestPerformingShops ?? collect())->take(5) as $rankIdx => $perfShop)
                            <tr class="hover:bg-[var(--mint-100)]/40 transition">
                                <td class="py-2.5 pr-3">
                                    <div class="font-bold text-[var(--foreground)]">#{{ $rankIdx + 1 }} {{ $perfShop['name'] }}</div>
                                    <div class="text-[10px] text-[var(--muted-foreground)]">{{ $perfShop['tier_badge'] }} &bull; {{ $perfShop['city'] }}</div>
                                </td>
                                <td class="py-2.5 pr-3 whitespace-nowrap">
                                    <span class="font-bold text-amber-600">★ {{ number_format($perfShop['rating'], 1) }}</span>
                                    <span class="text-[10px] text-[var(--muted-foreground)]">({{ $perfShop['completed_orders'] }} jobs)</span>
                                </td>
                                <td class="py-2.5 pr-3 font-mono font-bold text-[var(--foreground)] whitespace-nowrap">
                                    ৳{{ number_format($perfShop['vendor_earning'], 0) }}
                                </td>
                                <td class="py-2.5 text-right whitespace-nowrap">
                                    <form method="POST" action="{{ route('admin.shops.commission-rate', $perfShop['shop_id']) }}" class="inline-flex items-center justify-end gap-1.5">
                                        @csrf
                                        <input type="number" name="commission_rate" min="5" max="10" step="0.5" onwheel="this.blur()"
                                               value="{{ rtrim(rtrim(number_format($perfShop['commission_rate_pct'], 2), '0'), '.') }}"
                                               aria-label="Fee rate for {{ $perfShop['name'] }}"
                                               class="w-16 rounded-lg border border-[var(--border)] bg-[var(--mint-100)] px-2 py-1 text-right font-mono text-xs font-bold text-[var(--foreground)] outline-none">
                                        <button type="submit" class="rounded bg-[var(--accent-dark)] px-2.5 py-1 text-[10px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                                            Save
                                        </button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="4" class="py-5 text-center text-[var(--muted-foreground)]">No ranked workshops yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Read-Only Platform Repair Tickets Oversight -->
    <div id="platform-tickets-oversight" class="rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-3 sm:mb-4">
            <div class="min-w-0">
                <h2 class="text-sm sm:text-base font-bold text-[var(--foreground)]">Platform Repair Tickets Oversight</h2>
                <p class="text-[11px] sm:text-xs text-[var(--muted-foreground)]">Read-only snapshot of recent marketplace and direct-order repair tickets across all customers.</p>
            </div>
            <span class="rounded bg-[var(--mint-100)] px-3 py-1 text-[10px] sm:text-[11px] font-bold text-[var(--accent-dark)] shrink-0">
                {{ $kpis['open_requests'] }} Open Platform Tickets
            </span>
        </div>

        <!-- Mobile Card List (No Horizontal Table Scroll on Phones) -->
        <div class="md:hidden space-y-2.5">
            @forelse($recentRequests as $ticket)
                <div class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/45 p-3 space-y-1.5 text-xs">
                    <div class="flex items-start justify-between gap-2">
                        <div class="min-w-0 flex-1 font-bold text-[var(--foreground)] leading-snug break-words">
                            #{{ $ticket->id }} &mdash; {{ \Illuminate\Support\Str::limit($ticket->title ?? $ticket->device_model ?? 'Repair Ticket', 48) }}
                        </div>
                        <span class="shrink-0 rounded bg-[var(--card)] border border-[var(--border)] px-2 py-0.5 text-[9px] font-semibold uppercase text-[var(--accent-dark)] whitespace-nowrap">
                            {{ str_replace('_', ' ', $ticket->status) }}
                        </span>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1 text-[11px] text-[var(--muted-foreground)]">
                        <span class="truncate">
                            {{ $ticket->customer->name ?? 'Customer' }}
                            @if($ticket->requestedShop)
                                &bull;
                                <a href="{{ route('shops.show', $ticket->requestedShop->slug) }}" class="font-semibold text-[var(--accent-dark)] hover:underline">
                                    {{ $ticket->requestedShop->name }}
                                </a>
                            @else
                                &bull; Marketplace Bidding
                            @endif
                        </span>
                        <span class="shrink-0 text-[10px]">{{ $ticket->updated_at?->diffForHumans() ?? 'Recently' }}</span>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/40 p-4 text-center text-xs text-[var(--muted-foreground)]">
                    No platform repair tickets recorded yet.
                </div>
            @endforelse
        </div>

        <!-- Tablet & Desktop Full Table -->
        <div class="hidden md:block overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[var(--border)] text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)]">
                        <th class="py-2.5 pr-4">Ticket</th>
                        <th class="py-2.5 pr-4">Customer</th>
                        <th class="py-2.5 pr-4">Assigned Workshop</th>
                        <th class="py-2.5 pr-4">Status</th>
                        <th class="py-2.5">Updated</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]/60">
                    @forelse($recentRequests as $ticket)
                        <tr class="hover:bg-[var(--mint-100)]/40 transition">
                            <td class="py-3 pr-4 min-w-[200px]">
                                <div class="font-bold text-[var(--foreground)]">#{{ $ticket->id }} — {{ \Illuminate\Support\Str::limit($ticket->title ?? $ticket->device_model ?? 'Repair Ticket', 52) }}</div>
                                <div class="text-[11px] text-[var(--muted-foreground)]">{{ $ticket->device_type ?? 'Device' }}</div>
                            </td>
                            <td class="py-3 pr-4 font-semibold text-[var(--foreground)] whitespace-nowrap">
                                {{ $ticket->customer->name ?? 'Customer' }}
                                <div class="text-[10px] font-normal text-[var(--muted-foreground)]">{{ $ticket->customer->email ?? '' }}</div>
                            </td>
                            <td class="py-3 pr-4 whitespace-nowrap">
                                @if($ticket->requestedShop)
                                    <a href="{{ route('shops.show', $ticket->requestedShop->slug) }}" class="font-bold text-[var(--accent-dark)] hover:underline">
                                        {{ $ticket->requestedShop->name }}
                                    </a>
                                @else
                                    <span class="text-[var(--muted-foreground)]">Marketplace Bidding</span>
                                @endif
                            </td>
                            <td class="py-3 pr-4 whitespace-nowrap">
                                <span class="rounded bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-semibold uppercase text-[var(--accent-dark)]">
                                    {{ str_replace('_', ' ', $ticket->status) }}
                                </span>
                            </td>
                            <td class="py-3 text-[var(--muted-foreground)] whitespace-nowrap">
                                {{ $ticket->updated_at?->diffForHumans() ?? 'Recently' }}
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-[var(--muted-foreground)]">
                                No platform repair tickets recorded yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

</div>
@endsection

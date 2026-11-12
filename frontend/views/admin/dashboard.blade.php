@extends('layouts.app')

@section('title', 'Admin Governance Console | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-8">

    @include('admin.partials.nav')

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--border)] pb-5">
        <div class="max-w-2xl">
            <p class="text-[11px] font-bold uppercase tracking-[0.22em] text-[var(--accent-dark)]">Operations &amp; Governance</p>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)] mt-1">
                Meramot Platform Admin Console
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Centralized command desk for vendor trade verification, delivery courier onboarding, double-entry financial settlements, and nationwide marketplace oversight.
            </p>
        </div>
        <div class="flex flex-col items-end justify-end gap-2 shrink-0 ml-auto text-right">
            <a href="/shops" class="whitespace-nowrap inline-flex items-center justify-center rounded-full border border-[#c8dac2] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--foreground)] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--accent-dark)] hover:bg-[var(--mint-100)] hover:shadow-md">
                View Live Directory ({{ number_format($kpis['public_shops']) }} Shops)
            </a>
            <a href="/admin/financial-overview" class="whitespace-nowrap inline-flex items-center justify-center rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-md">
                Open Financial Ledger &rarr;
            </a>
        </div>
    </div>

    <!-- Top KPI Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <a href="/admin/vendors/pending" class="rounded-[1.75rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm hover:border-[var(--accent-dark)] transition block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Pending Vendor Audits</span>
                <span class="h-9 w-9 rounded-2xl bg-amber-100 text-amber-800 flex items-center justify-center"><i class="fa-solid fa-store"></i></span>
            </div>
            <div class="mt-3 text-3xl font-black text-[var(--foreground)]">{{ $kpis['pending_vendors'] }}</div>
            <p class="mt-1 text-xs text-[var(--accent-dark)] font-semibold">
                {{ number_format($kpis['public_shops']) }} public storefronts • {{ $kpis['branched_brands'] }} branched brands &rarr;
            </p>
        </a>

        <a href="/admin/delivery-agents/pending" class="rounded-[1.75rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm hover:border-[var(--accent-dark)] transition block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Pending Rider Verifications</span>
                <span class="h-9 w-9 rounded-2xl bg-sky-100 text-sky-800 flex items-center justify-center"><i class="fa-solid fa-motorcycle"></i></span>
            </div>
            <div class="mt-3 text-3xl font-black text-[var(--foreground)]">{{ $kpis['pending_riders'] }}</div>
            <p class="mt-1 text-xs text-[var(--accent-dark)] font-semibold">
                {{ $kpis['approved_riders'] }} approved riders active &rarr;
            </p>
        </a>

        <a href="/admin/financial-overview" class="rounded-[1.75rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm hover:border-[var(--accent-dark)] transition block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Gross Ledger Volume</span>
                <span class="h-9 w-9 rounded-2xl bg-emerald-100 text-emerald-800 flex items-center justify-center"><i class="fa-solid fa-scale-balanced"></i></span>
            </div>
            <div class="mt-3 text-2xl font-black text-[var(--foreground)]">৳{{ number_format($kpis['platform_volume'], 2) }}</div>
            <p class="mt-1 text-xs text-emerald-800 font-semibold">
                10% Net Revenue: ৳{{ number_format($kpis['platform_commission'], 2) }} ({{ $kpis['unsettled_tx_count'] }} unsettled) &rarr;
            </p>
        </a>

        <a href="/requests" class="rounded-[1.75rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm hover:border-[var(--accent-dark)] transition block">
            <div class="flex items-center justify-between">
                <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Active Repair Tickets</span>
                <span class="h-9 w-9 rounded-2xl bg-[#e4f3df] text-[#244229] flex items-center justify-center"><i class="fa-solid fa-screwdriver-wrench"></i></span>
            </div>
            <div class="mt-3 text-3xl font-black text-[var(--foreground)]">{{ $kpis['open_requests'] }}</div>
            <p class="mt-1 text-xs text-[var(--muted-foreground)] font-semibold">
                {{ number_format($kpis['total_users']) }} registered accounts across roles &rarr;
            </p>
        </a>
    </div>

    <!-- Sprint 1 Primary Operations Desks -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <!-- Pending Vendor Verification Snapshot (R11) -->
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <span class="dev-only-badge text-[10px] font-mono font-bold text-emerald-800 block mb-0.5">Feature R11 • Farhan Tanvir [22299179]</span>
                        <h2 class="text-base font-black text-[var(--foreground)]">Vendor Business Verification Queue</h2>
                    </div>
                    <a href="/admin/vendors/pending" class="rounded-full bg-[var(--accent-dark)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                        Review All ({{ $kpis['pending_vendors'] }}) &rarr;
                    </a>
                </div>

                <div class="space-y-3 text-xs">
                    @forelse($recentPendingVendors as $vp)
                        <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-bold text-[var(--foreground)]">{{ $vp->shop_name }}</div>
                                <div class="text-[11px] text-[var(--muted-foreground)]">
                                    License: <span class="font-mono">{{ $vp->business_license_no }}</span> • {{ $vp->area }}, {{ $vp->city }}
                                </div>
                            </div>
                            <a href="/admin/vendors/pending" class="rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)]">
                                Audit
                            </a>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/40 p-4 text-center text-[var(--muted-foreground)]">
                            All vendor applications are currently reviewed! <a href="/vendor/onboarding" class="font-bold text-[var(--accent-dark)] underline">Submit a test vendor onboarding</a>.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Pending Delivery Agent Verification Snapshot (R1) -->
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm flex flex-col justify-between">
            <div>
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <span class="dev-only-badge text-[10px] font-mono font-bold text-emerald-800 block mb-0.5">Feature R1 • Md. Nafiul Islam [22299035]</span>
                        <h2 class="text-base font-black text-[var(--foreground)]">Delivery Courier Credential Queue</h2>
                    </div>
                    <a href="/admin/delivery-agents/pending" class="rounded-full bg-[var(--accent-dark)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                        Review All ({{ $kpis['pending_riders'] }}) &rarr;
                    </a>
                </div>

                <div class="space-y-3 text-xs">
                    @forelse($recentPendingRiders as $agent)
                        <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5 flex items-center justify-between gap-3">
                            <div>
                                <div class="font-bold text-[var(--foreground)]">{{ $agent->applicant_name ?: ($agent->user->name ?? 'Rider Applicant #' . $agent->id) }}</div>
                                <div class="text-[11px] text-[var(--muted-foreground)]">
                                    NID: <span class="font-mono">{{ $agent->nid_number }}</span> • License: <span class="font-mono">{{ $agent->license_number }}</span> ({{ ucfirst($agent->vehicle_type) }})
                                </div>
                            </div>
                            <a href="/admin/delivery-agents/pending" class="rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)]">
                                Verify
                            </a>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/40 p-4 text-center text-[var(--muted-foreground)]">
                            No pending courier applications right now. <a href="/delivery-agent/register" class="font-bold text-[var(--accent-dark)] underline">Open rider registration form</a>.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Sprint Governance Modules -->
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--mint-100)]/60 p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h2 class="text-base font-black text-[var(--foreground)]">Scheduled Governance &amp; Dispatch Modules (Upcoming Sprints)</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Preview scaffolded panels scheduled for Sprints 2, 3, and 4.</p>
            </div>
            <span class="rounded-full border border-[var(--border)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
                Roadmap Preview
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <a href="/delivery/jobs-queue" class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 hover:border-[var(--accent-dark)] transition">
                <span class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">Sprint 2 • Coming Soon</span>
                <h3 class="mt-2 font-bold text-sm text-[var(--foreground)]">Rider Dispatch Queue</h3>
                <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">Live pickup/return job claiming and 4-digit OTP milestones.</p>
            </a>

            <a href="/admin/payouts/manage" class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 hover:border-[var(--accent-dark)] transition">
                <span class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">Sprint 3 • Coming Soon</span>
                <h3 class="mt-2 font-bold text-sm text-[var(--foreground)]">Vendor Payout Settlements</h3>
                <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">Automated vendor bank/bKash withdrawal disbursements.</p>
            </a>

            <a href="/admin/moderation" class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 hover:border-[var(--accent-dark)] transition">
                <span class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">Sprint 3 • Coming Soon</span>
                <h3 class="mt-2 font-bold text-sm text-[var(--foreground)]">Content Moderation</h3>
                <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">Abusive review flagging, warnings, and account sanctions.</p>
            </a>

            <a href="/admin/audit-logs" class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 hover:border-[var(--accent-dark)] transition">
                <span class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">Sprint 4 • Coming Soon</span>
                <h3 class="mt-2 font-bold text-sm text-[var(--foreground)]">Security Audit Monitor</h3>
                <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">Immutable IP forensics and state-transition audit trail.</p>
            </a>
        </div>
    </div>

</div>
@endsection

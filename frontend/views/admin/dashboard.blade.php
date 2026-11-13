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
        <div class="flex flex-wrap sm:flex-nowrap md:flex-col items-start md:items-end justify-start md:justify-end gap-2 shrink-0 md:ml-auto md:text-right">
            <a href="/shops" class="whitespace-nowrap inline-flex items-center justify-center rounded-full border border-[#c8dac2] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--foreground)] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[var(--accent-dark)] hover:bg-[var(--mint-100)] hover:shadow-md">
                View Live Directory ({{ number_format($kpis['public_shops']) }} Shops)
            </a>
            <a href="/admin/financial-overview" class="whitespace-nowrap inline-flex items-center justify-center rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:opacity-90 hover:shadow-md">
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

        <a href="#platform-tickets-oversight" class="rounded-[1.75rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm hover:border-[var(--accent-dark)] transition block">
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
                            <div class="min-w-0 flex-1">
                                <div class="break-words font-bold text-[var(--foreground)]">{{ $vp->shop_name }}</div>
                                <div class="break-words text-[11px] text-[var(--muted-foreground)]">
                                    License: <span class="font-mono">{{ $vp->business_license_no }}</span> • {{ $vp->area }}, {{ $vp->city }}
                                </div>
                            </div>
                            <a href="/admin/vendors/pending" class="shrink-0 rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)]">
                                Audit
                            </a>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/40 p-4 text-center text-[var(--muted-foreground)]">
                            All pending vendor onboarding applications have been reviewed.
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
                            <div class="min-w-0 flex-1">
                                <div class="break-words font-bold text-[var(--foreground)]">{{ $agent->applicant_name ?: ($agent->user->name ?? 'Rider Applicant #' . $agent->id) }}</div>
                                <div class="break-words text-[11px] text-[var(--muted-foreground)]">
                                    NID: <span class="font-mono">{{ $agent->nid_number }}</span> • License: <span class="font-mono">{{ $agent->license_number }}</span> ({{ ucfirst($agent->vehicle_type) }})
                                </div>
                            </div>
                            <a href="/admin/delivery-agents/pending" class="shrink-0 rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)]">
                                Verify
                            </a>
                        </div>
                    @empty
                        <p class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/40 p-4 text-center text-[var(--muted-foreground)]">
                            No pending delivery agent applications at this time.
                        </p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>

    <!-- Upcoming Operations & Governance Modules -->
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--mint-100)]/60 p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h2 class="text-base font-black text-[var(--foreground)]">Operations, Payout &amp; Governance Modules</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Direct shortcuts to courier dispatch, vendor settlements, content moderation, and compliance auditing.</p>
            </div>
            <span class="rounded-full border border-[var(--border)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
                Platform Operations
            </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 text-xs">
            <a href="/delivery/jobs-queue" class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 hover:border-[var(--accent-dark)] transition">
                <span class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">Operations • Queue</span>
                <h3 class="mt-2 font-bold text-sm text-[var(--foreground)]">Rider Dispatch Queue</h3>
                <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">Live pickup and return trip allocation with OTP handover milestones.</p>
            </a>

            <a href="/admin/payouts/manage" class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 hover:border-[var(--accent-dark)] transition">
                <span class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">Finance • Scheduled</span>
                <h3 class="mt-2 font-bold text-sm text-[var(--foreground)]">Vendor Payout Settlements</h3>
                <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">Automated workshop bank and mobile banking withdrawal disbursements.</p>
            </a>

            <a href="/admin/moderation" class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 hover:border-[var(--accent-dark)] transition">
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">Active Governance</span>
                <h3 class="mt-2 font-bold text-sm text-[var(--foreground)]">Content Moderation</h3>
                <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">Review removal, policy enforcement, and account restriction controls.</p>
            </a>

            <a href="/admin/audit-logs" class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 hover:border-[var(--accent-dark)] transition">
                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">Active Compliance</span>
                <h3 class="mt-2 font-bold text-sm text-[var(--foreground)]">Security Audit Monitor</h3>
                <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">Immutable IP forensics and state-transition compliance audit trail.</p>
            </a>
        </div>
    </div>

    <!-- Read-Only Platform Repair Tickets Oversight -->
    <div id="platform-tickets-oversight" class="mt-8 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
        <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
            <div>
                <h2 class="text-base font-black text-[var(--foreground)]">Platform Repair Tickets Oversight</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Read-only snapshot of recent marketplace and direct-order repair tickets across all customers.</p>
            </div>
            <span class="rounded-full bg-[var(--mint-100)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
                {{ $kpis['open_requests'] }} Open Platform Tickets
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[var(--border)] text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">
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
                                <span class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-[var(--accent-dark)]">
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

@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-3.5 py-4 sm:px-6 sm:py-8 lg:px-8 space-y-4 sm:space-y-8">
    @include('admin.partials.nav')

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3.5 sm:gap-4 rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded bg-rose-100 px-3 py-1 text-[10px] font-semibold uppercase tracking-wider text-rose-800">
                <i class="fa-solid fa-shield-halved"></i> Trust &amp; Safety Governance
            </span>
            <h1 class="mt-2 text-xl sm:text-2xl md:text-3xl font-bold leading-tight text-[var(--foreground)]">Content Moderation &amp; User Sanctions</h1>
            <p class="mt-1 text-[11px] sm:text-xs md:text-sm text-[var(--muted-foreground)] leading-relaxed">
                Audit workshop reviews for policy violations, remove abusive feedback, restrict or restore user ordering privileges, and dispatch email and in-app alerts to affected accounts.
            </p>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 w-full md:w-auto md:flex items-center gap-2.5 sm:gap-3 shrink-0">
            <div class="rounded-xl sm:rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/70 px-3.5 py-2 sm:px-4 sm:py-2.5 text-center">
                <div class="text-base sm:text-lg font-bold text-[var(--foreground)]">{{ $reviews->count() }}</div>
                <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Active Reviews</div>
            </div>
            <div class="rounded-xl sm:rounded-lg border border-amber-300 bg-amber-50 px-3.5 py-2 sm:px-4 sm:py-2.5 text-center">
                <div class="text-base sm:text-lg font-bold text-amber-800">{{ isset($disputes) ? $disputes->whereIn('status', ['OPEN', 'INVESTIGATING', 'WAITING_EVIDENCE', 'WAITING_RESPONSE'])->count() : 0 }}</div>
                <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-amber-900">Open Disputes</div>
            </div>
            <div class="rounded-xl sm:rounded-lg border border-sky-300 bg-sky-50 px-3.5 py-2 sm:px-4 sm:py-2.5 text-center">
                <div class="text-base sm:text-lg font-bold text-sky-800">{{ isset($supportTickets) ? $supportTickets->whereIn('status', ['OPEN', 'IN_PROGRESS', 'ESCALATED'])->count() : 0 }}</div>
                <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-sky-900">Open Tickets</div>
            </div>
            <div class="rounded-xl sm:rounded-lg border border-rose-300 bg-rose-50 px-3.5 py-2 sm:px-4 sm:py-2.5 text-center">
                <div class="text-base sm:text-lg font-bold text-rose-700">{{ $bannedUsers->count() }}</div>
                <div class="text-[9px] sm:text-[10px] font-bold uppercase tracking-wider text-rose-800">Banned Users</div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-400 bg-emerald-50 p-4 text-xs font-bold text-emerald-950 flex items-center gap-2.5">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error') || $errors->any())
        <div class="rounded-lg border border-rose-300 bg-rose-50 p-4 text-xs font-bold text-rose-900">
            {{ session('error') ?? $errors->first() }}
        </div>
    @endif

    {{-- Section 0: Order Disputes & Governance Sanction Desk (Feature R19) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <section class="lg:col-span-7 rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm space-y-4 text-xs">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <h2 class="text-lg font-bold text-[var(--foreground)]">Escrow Order Disputes Arbitration</h2>
                    <p class="text-xs text-[var(--muted-foreground)]">Resolve customer-workshop disputes with optional full or partial escrow refunds.</p>
                </div>
                <span class="rounded-md bg-amber-100 px-3 py-1 text-[11px] font-bold text-amber-900">
                    {{ isset($disputes) ? $disputes->count() : 0 }} Cases
                </span>
            </div>

            <div class="space-y-3 max-h-[440px] overflow-y-auto pr-1">
                @forelse($disputes ?? [] as $dc)
                    @php
                        $isOpenCase = in_array($dc->status, ['OPEN', 'INVESTIGATING', 'WAITING_EVIDENCE', 'WAITING_RESPONSE'], true);
                    @endphp
                    <div class="rounded-lg border p-4 space-y-2.5 {{ $isOpenCase ? 'border-amber-300 bg-amber-50/60' : 'border-[var(--border)] bg-[var(--mint-100)]/45' }}">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <span class="font-bold text-sm text-[var(--foreground)]">
                                Dispute #{{ $dc->id }} • Order #{{ $dc->repair_request_id }} ({{ $dc->repairRequest?->title ?? 'Repair Order' }})
                            </span>
                            <span class="rounded px-2.5 py-0.5 text-[10px] font-semibold uppercase {{ $isOpenCase ? 'bg-amber-200 text-amber-950' : 'bg-emerald-100 text-emerald-900' }}">
                                {{ $dc->status }}
                            </span>
                        </div>
                        <p class="text-[11px] text-[var(--muted-foreground)]">
                            Opened by <strong>{{ $dc->openedBy?->name ?? 'Customer' }}</strong> against <strong>{{ $dc->against?->name ?? 'Workshop' }}</strong>
                            @if($dc->payment)
                                • Paid Escrow: <strong class="text-[var(--accent-dark)]">৳{{ number_format((float) $dc->payment->amount, 2) }}</strong>
                            @endif
                        </p>
                        @if($dc->notes->isNotEmpty())
                            <div class="rounded-xl bg-white/85 border border-[var(--border)] p-2.5 space-y-1">
                                @foreach($dc->notes->take(3) as $dn)
                                    <p class="text-[11px]"><strong class="text-[var(--accent-dark)]">{{ $dn->author?->name ?? 'User' }}:</strong> {{ $dn->note }}</p>
                                @endforeach
                            </div>
                        @endif

                        @if($isOpenCase)
                            <form method="POST" action="{{ route('admin.disputes.resolve', $dc->id) }}" class="grid grid-cols-1 sm:grid-cols-12 gap-2 pt-1">
                                @csrf
                                <select name="decision" class="sm:col-span-4 rounded-xl border border-[var(--border)] bg-white px-2.5 py-2 font-bold text-[var(--foreground)]">
                                    <option value="resolve">Resolve (Release to Shop)</option>
                                    <option value="full_refund">Full Escrow Refund</option>
                                    <option value="partial_refund">Partial Refund (50%)</option>
                                    <option value="reject">Reject Claim</option>
                                </select>
                                <input type="text" name="resolution" required placeholder="Arbitration ruling & settlement note..."
                                       class="sm:col-span-5 rounded-xl border border-[var(--border)] bg-white px-3 py-2 text-[var(--foreground)]">
                                <button type="submit" class="sm:col-span-3 rounded-md bg-[var(--accent-dark)] text-[var(--accent-foreground)] font-bold px-3 py-2 hover:opacity-90 transition">
                                    Rule Case
                                </button>
                            </form>
                        @elseif($dc->resolution)
                            <p class="text-[11px] font-semibold text-emerald-900">Ruling: {{ $dc->resolution }}</p>
                        @endif
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-[var(--border)] p-6 text-center text-[var(--muted-foreground)]">
                        No order disputes filed.
                    </div>
                @endforelse
            </div>
        </section>

        <!-- Governance Sanction Desk -->
        <section class="lg:col-span-5 rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm space-y-4 text-xs"
                 x-data="{ targetType: 'user' }">
            <div>
                <h2 class="text-lg font-bold text-[var(--foreground)]">Direct Governance Sanction Desk</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Issue official warnings, account suspensions, or workshop delistings.</p>
            </div>

            <form method="POST" action="{{ route('admin.moderation.sanction') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Target Entity Type</label>
                    <select name="target_type" x-model="targetType" class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-bold">
                        <option value="user">Platform User Account</option>
                        <option value="shop">Partner Repair Workshop</option>
                    </select>
                </div>

                <div x-show="targetType === 'user'">
                    <label class="block font-bold text-[var(--foreground)] mb-1">Select User</label>
                    <select :name="targetType === 'user' ? 'target_id' : ''" class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-semibold">
                        @foreach($sanctionableUsers ?? [] as $su)
                            <option value="{{ $su->id }}">{{ $su->name }} ({{ $su->email }} • {{ $su->role }})</option>
                        @endforeach
                    </select>
                </div>

                <div x-show="targetType === 'shop'" x-cloak>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Select Workshop</label>
                    <select :name="targetType === 'shop' ? 'target_id' : ''" class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-semibold">
                        @foreach($sanctionableShops ?? [] as $ss)
                            <option value="{{ $ss->id }}">{{ $ss->name }} ({{ $ss->area }}, {{ $ss->city }} • {{ $ss->is_public ? 'Public' : 'Delisted' }})</option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Governance Action</label>
                        <select name="action" class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-bold">
                            <option value="warn">Issue Official Warning</option>
                            <option value="suspend">Suspend Ordering Privileges</option>
                            <option value="ban">Ban Account</option>
                            <option value="restore_user">Restore User Access</option>
                            <option value="delist_shop">Delist Workshop</option>
                            <option value="restore_shop">Restore Workshop</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Violation Category</label>
                        <input type="text" name="violation_type" value="fraudulent_quote_or_policy"
                               class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-mono">
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Reason &amp; Notice Sent to Party</label>
                    <textarea name="reason" rows="2" required placeholder="State clear policy basis for the audit log and recipient notification..."
                              class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2"></textarea>
                </div>

                <button type="submit" class="w-full rounded-md bg-rose-700 hover:bg-rose-800 py-2.5 px-5 font-bold text-white transition shadow">
                    <i class="fa-solid fa-gavel mr-1"></i> Execute Governance Action
                </button>
            </form>
        </section>
    </div>

    {{-- Support Escalations Queue --}}
    @if(isset($supportTickets) && $supportTickets->count() > 0)
        <section class="rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm space-y-4 text-xs">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-bold text-[var(--foreground)]">Customer &amp; Partner Support Escalations</h2>
                    <p class="text-xs text-[var(--muted-foreground)]">Respond directly to open support tickets submitted via the Help &amp; Dispute Desk.</p>
                </div>
                <span class="rounded-md bg-sky-100 px-3 py-1 text-[11px] font-bold text-sky-900">{{ $supportTickets->count() }} Tickets</span>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                @foreach($supportTickets->take(6) as $st)
                    <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/50 p-4 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-bold text-[var(--foreground)]">#{{ $st->id }} — {{ $st->subject }}</span>
                            <span class="rounded bg-white border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-bold">{{ $st->status }}</span>
                        </div>
                        <p class="text-[11px] text-[var(--muted-foreground)]">{{ $st->user?->name ?? 'User' }} • Priority: <strong class="uppercase">{{ $st->priority }}</strong></p>
                        <p class="rounded-xl bg-white p-2.5 border border-[var(--border)] text-[11px]">{{ $st->message }}</p>
                        @if($st->status !== 'RESOLVED' && $st->status !== 'CLOSED')
                            <form method="POST" action="{{ route('admin.support.resolve', $st->id) }}" class="flex gap-2 pt-1">
                                @csrf
                                <input type="text" name="admin_notes" required placeholder="Official support resolution..."
                                       class="flex-1 rounded-xl border border-[var(--border)] bg-white px-3 py-1.5">
                                <button type="submit" class="rounded-md bg-[var(--accent-dark)] text-[var(--accent-foreground)] px-4 py-1.5 font-bold">
                                    Resolve
                                </button>
                            </form>
                        @elseif($st->admin_notes)
                            <p class="text-[11px] font-semibold text-emerald-800">Admin Reply: {{ $st->admin_notes }}</p>
                        @endif
                    </div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Section 1: Workshop Reviews Moderation Queue --}}
    <section class="rounded-lg sm:rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm space-y-4 sm:space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold text-[var(--foreground)]">Workshop Reviews Moderation Queue</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Flagged reviews containing potentially offensive words appear first so admins can delete them or ban repeat offenders.</p>
            </div>
            <span class="rounded-md bg-[var(--mint-100)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
                {{ $reviews->where(fn ($r) => \App\Models\ShopReview::containsOffensiveLanguage($r->review_comment))->count() }} Flagged
            </span>
        </div>

        <div class="space-y-4">
            @forelse($reviews as $rev)
                @php
                    $detectedWords = \App\Models\ShopReview::detectOffensiveWords($rev->review_comment);
                    $hasOffensive = count($detectedWords) > 0;
                    $authorIsBanned = $rev->customer && $rev->customer->isBanned();
                @endphp
                <div x-data="{ banFormOpen: false }" class="rounded-lg border p-4 sm:p-5 {{ $hasOffensive ? 'border-rose-300 bg-rose-50/60 dark:bg-rose-950/20' : 'border-[var(--border)] bg-[var(--mint-100)]/40' }}">
                    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                        <div class="space-y-2 min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-sm text-[var(--foreground)]">
                                    {{ $rev->customer->name ?? 'Meramot Customer' }}
                                </span>
                                <span class="text-xs text-[var(--muted-foreground)] break-all">({{ $rev->customer->email ?? 'N/A' }})</span>

                                @if($authorIsBanned)
                                    <span class="rounded bg-rose-600 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white">
                                        Banned User
                                    </span>
                                @endif

                                @if($hasOffensive)
                                    <span class="rounded bg-amber-500 px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white">
                                        Offensive Words: {{ implode(', ', $detectedWords) }}
                                    </span>
                                @endif

                                @if($rev->is_verified_purchase)
                                    <span class="rounded bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">
                                        Verified Repair
                                    </span>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-3 text-xs text-[var(--muted-foreground)]">
                                <span>
                                    Workshop:
                                    @if($rev->shop)
                                        <a href="{{ route('shops.show', $rev->shop->slug) }}" class="font-bold text-[var(--accent-dark)] hover:underline">{{ $rev->shop->name }}</a>
                                    @else
                                        <span class="font-semibold">Workshop #{{ $rev->repair_shop_id }}</span>
                                    @endif
                                </span>
                                <span class="font-bold text-amber-600">{{ str_repeat('★', (int) $rev->rating) }} ({{ $rev->rating }}/5)</span>
                                @if($rev->service_provided)
                                    <span>Service: <strong class="text-[var(--foreground)]">{{ $rev->service_provided }}</strong></span>
                                @endif
                                <span>{{ $rev->created_at?->diffForHumans() }}</span>
                            </div>

                            <p class="rounded-xl border border-[var(--border)] bg-[var(--card)] p-3 text-sm text-[var(--foreground)] leading-relaxed">
                                {{ $rev->review_comment ?: 'No written comment.' }}
                            </p>
                        </div>

                        <div class="flex flex-col sm:flex-row lg:flex-col items-stretch lg:items-end gap-2 shrink-0 w-full lg:w-auto">
                            <form
                                method="POST"
                                action="{{ route('admin.reviews.moderate', $rev->id) }}"
                                data-confirm-delete
                                data-confirm-title="Delete Review?"
                                data-confirm-item="Review #{{ $rev->id }} by {{ $rev->customer->name ?? 'Customer' }}"
                                data-confirm-message="This removes the review, recalculates the workshop rating, and sends email and in-app notifications."
                                data-confirm-cta="Delete Review"
                                class="flex-1 sm:flex-none lg:w-full"
                            >
                                @csrf
                                <input type="hidden" name="action_type" value="delete_only">
                                <input type="hidden" name="reason" value="Removed by Admin for inappropriate or offensive language in review #{{ $rev->id }}.">
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 rounded-md bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-amber-700 transition">
                                    <i class="fa-solid fa-trash-can"></i>
                                    <span>Delete Review</span>
                                </button>
                            </form>

                            @if($rev->customer && $rev->customer->role !== 'admin')
                                <button
                                    type="button"
                                    @click="banFormOpen = !banFormOpen"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-md bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition"
                                >
                                    <i class="fa-solid fa-ban"></i>
                                    <span>Delete &amp; Ban User</span>
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($rev->customer && $rev->customer->role !== 'admin')
                        <form
                            x-show="banFormOpen"
                            x-cloak
                            method="POST"
                            action="{{ route('admin.reviews.moderate', $rev->id) }}"
                            class="mt-4 rounded-lg border border-rose-300 bg-[var(--card)] p-4 space-y-3"
                        >
                            @csrf
                            <input type="hidden" name="action_type" value="delete_and_ban">
                            <div>
                                <label class="block text-[11px] font-semibold uppercase tracking-wider text-rose-800 mb-1">
                                    Restriction Reason (Delivered via Email &amp; In-App Notification to {{ $rev->customer->email }})
                                </label>
                                <input
                                    type="text"
                                    name="reason"
                                    required
                                    maxlength="500"
                                    value="Use of offensive words in review on {{ $rev->shop->name ?? 'Meramot workshop' }}"
                                    class="w-full rounded-xl border border-rose-300 bg-white dark:bg-[var(--card)] px-3 py-2 text-xs text-[var(--foreground)] outline-none"
                                >
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <button type="button" @click="banFormOpen = false" class="rounded-md border border-[var(--border)] px-4 py-1.5 text-xs font-bold text-[var(--foreground)]">
                                    Cancel
                                </button>
                                <button type="submit" class="rounded-md bg-rose-700 px-5 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-rose-800 transition">
                                    Confirm Delete Review &amp; Ban User
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @empty
                <div class="rounded-lg border border-dashed border-[var(--border)] p-8 text-center text-xs text-[var(--muted-foreground)]">
                    No active workshop reviews in the queue right now.
                </div>
            @endforelse
        </div>
    </section>

    {{-- Section 2: Banned / Restricted Users Desk --}}
    <section class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold text-[var(--foreground)]">Banned &amp; Restricted Accounts</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Banned users can still log in to view ongoing &amp; past orders, but cannot place orders, post repair requests, or submit reviews.</p>
            </div>
            <span class="rounded-md bg-rose-100 px-3 py-1 text-[11px] font-bold text-rose-800">
                {{ $bannedUsers->count() }} Restricted
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[var(--border)] text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)]">
                        <th class="py-2.5 pr-4">User</th>
                        <th class="py-2.5 pr-4">Role</th>
                        <th class="py-2.5 pr-4">Ban Reason</th>
                        <th class="py-2.5 pr-4">Banned At</th>
                        <th class="py-2.5 text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]/60">
                    @forelse($bannedUsers as $u)
                        <tr>
                            <td class="py-3 pr-4">
                                <div class="font-bold text-[var(--foreground)]">{{ $u->name }}</div>
                                <div class="text-[11px] text-[var(--muted-foreground)] break-all">{{ $u->email }}</div>
                            </td>
                            <td class="py-3 pr-4 whitespace-nowrap">
                                <span class="rounded bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold uppercase text-[var(--accent-dark)]">
                                    {{ $u->role }}
                                </span>
                            </td>
                            <td class="py-3 pr-4 text-[var(--foreground)] max-w-md min-w-[180px]">
                                {{ $u->ban_reason ?: 'Policy violation / abusive content.' }}
                            </td>
                            <td class="py-3 pr-4 text-[var(--muted-foreground)] whitespace-nowrap">
                                {{ $u->banned_at?->format('M d, Y h:i A') ?? 'Recently' }}
                            </td>
                            <td class="py-3 text-right whitespace-nowrap">
                                <form method="POST" action="{{ route('admin.users.unban', $u->id) }}" class="inline">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-emerald-600 px-4 py-1.5 text-[11px] font-bold text-white shadow-sm hover:bg-emerald-700 transition">
                                        <i class="fa-solid fa-unlock"></i>
                                        <span>Unban &amp; Restore Access</span>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-6 text-center text-[var(--muted-foreground)]">
                                No users are currently banned.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- Section 3: Moderation Audit Trail --}}
    <section class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-bold text-[var(--foreground)]">Moderation Audit Log</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Immutable record of review deletions, user bans, and account restorations.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[var(--border)] text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)]">
                        <th class="py-2.5 pr-4">Target</th>
                        <th class="py-2.5 pr-4">Violation</th>
                        <th class="py-2.5 pr-4">Action Taken</th>
                        <th class="py-2.5 pr-4">Reason</th>
                        <th class="py-2.5 pr-4">Admin</th>
                        <th class="py-2.5">Timestamp</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]/60">
                    @forelse($logs as $log)
                        <tr>
                            <td class="py-3 pr-4 font-mono font-bold text-[var(--foreground)] whitespace-nowrap">
                                {{ ucfirst($log->target_type) }} #{{ $log->target_id }}
                            </td>
                            <td class="py-3 pr-4 text-[var(--muted-foreground)] whitespace-nowrap">{{ $log->violation_type }}</td>
                            <td class="py-3 pr-4 whitespace-nowrap">
                                <span class="rounded px-2.5 py-0.5 text-[10px] font-semibold uppercase {{ $log->action_taken === 'unban' ? 'bg-emerald-100 text-emerald-800' : (str_contains($log->action_taken, 'ban') ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
                                    {{ str_replace('_', ' ', $log->action_taken) }}
                                </span>
                            </td>
                            <td class="py-3 pr-4 text-[var(--foreground)] max-w-md min-w-[200px]">{{ $log->reason }}</td>
                            <td class="py-3 pr-4 font-semibold text-[var(--foreground)] whitespace-nowrap">{{ $log->admin->name ?? 'Admin #' . $log->admin_id }}</td>
                            <td class="py-3 text-[var(--muted-foreground)] whitespace-nowrap">{{ $log->created_at?->format('M d, Y h:i A') }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-6 text-center text-[var(--muted-foreground)]">
                                No moderation actions logged yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="pt-2">
                {{ $logs->links() }}
            </div>
        @endif
    </section>
</div>
@endsection

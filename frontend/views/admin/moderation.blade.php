@extends('layouts.app')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8 space-y-8">
    @include('admin.partials.nav')

    {{-- Header --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-100 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-rose-800">
                <i class="fa-solid fa-shield-halved"></i> Trust &amp; Safety Governance
            </span>
            <h1 class="mt-2 text-2xl sm:text-3xl font-black text-[var(--foreground)]">Content Moderation &amp; User Sanctions</h1>
            <p class="mt-1 text-xs sm:text-sm text-[var(--muted-foreground)]">
                Audit workshop reviews for policy violations, remove abusive feedback, restrict or restore user ordering privileges, and dispatch email and in-app alerts to affected accounts.
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-3 shrink-0">
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/70 px-4 py-2.5 text-center">
                <div class="text-lg font-black text-[var(--foreground)]">{{ $reviews->count() }}</div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Active Reviews</div>
            </div>
            <div class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-2.5 text-center">
                <div class="text-lg font-black text-rose-700">{{ $bannedUsers->count() }}</div>
                <div class="text-[10px] font-bold uppercase tracking-wider text-rose-800">Banned Users</div>
            </div>
        </div>
    </div>

    {{-- Section 1: Workshop Reviews Moderation Queue --}}
    <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-5">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-black text-[var(--foreground)]">Workshop Reviews Moderation Queue</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Flagged reviews containing potentially offensive words appear first so admins can delete them or ban repeat offenders.</p>
            </div>
            <span class="rounded-full bg-[var(--mint-100)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
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
                <div x-data="{ banFormOpen: false }" class="rounded-2xl border p-4 sm:p-5 {{ $hasOffensive ? 'border-rose-300 bg-rose-50/60 dark:bg-rose-950/20' : 'border-[var(--border)] bg-[var(--mint-100)]/40' }}">
                    <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                        <div class="space-y-2 min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-extrabold text-sm text-[var(--foreground)]">
                                    {{ $rev->customer->name ?? 'Meramot Customer' }}
                                </span>
                                <span class="text-xs text-[var(--muted-foreground)] break-all">({{ $rev->customer->email ?? 'N/A' }})</span>

                                @if($authorIsBanned)
                                    <span class="rounded-full bg-rose-600 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-white">
                                        Banned User
                                    </span>
                                @endif

                                @if($hasOffensive)
                                    <span class="rounded-full bg-amber-500 px-2.5 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-white">
                                        Offensive Words: {{ implode(', ', $detectedWords) }}
                                    </span>
                                @endif

                                @if($rev->is_verified_purchase)
                                    <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">
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
                                <button type="submit" class="w-full inline-flex items-center justify-center gap-1.5 rounded-full bg-amber-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-amber-700 transition">
                                    <i class="fa-solid fa-trash-can"></i>
                                    <span>Delete Review</span>
                                </button>
                            </form>

                            @if($rev->customer && $rev->customer->role !== 'admin')
                                <button
                                    type="button"
                                    @click="banFormOpen = !banFormOpen"
                                    class="inline-flex items-center justify-center gap-1.5 rounded-full bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition"
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
                            class="mt-4 rounded-2xl border border-rose-300 bg-[var(--card)] p-4 space-y-3"
                        >
                            @csrf
                            <input type="hidden" name="action_type" value="delete_and_ban">
                            <div>
                                <label class="block text-[11px] font-extrabold uppercase tracking-wider text-rose-800 mb-1">
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
                                <button type="button" @click="banFormOpen = false" class="rounded-full border border-[var(--border)] px-4 py-1.5 text-xs font-bold text-[var(--foreground)]">
                                    Cancel
                                </button>
                                <button type="submit" class="rounded-full bg-rose-700 px-5 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-rose-800 transition">
                                    Confirm Delete Review &amp; Ban User
                                </button>
                            </div>
                        </form>
                    @endif
                </div>
            @empty
                <div class="rounded-2xl border border-dashed border-[var(--border)] p-8 text-center text-xs text-[var(--muted-foreground)]">
                    No active workshop reviews in the queue right now.
                </div>
            @endforelse
        </div>
    </section>

    {{-- Section 2: Banned / Restricted Users Desk --}}
    <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-black text-[var(--foreground)]">Banned &amp; Restricted Accounts</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Banned users can still log in to view ongoing &amp; past orders, but cannot place orders, post repair requests, or submit reviews.</p>
            </div>
            <span class="rounded-full bg-rose-100 px-3 py-1 text-[11px] font-bold text-rose-800">
                {{ $bannedUsers->count() }} Restricted
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[var(--border)] text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">
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
                                <span class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold uppercase text-[var(--accent-dark)]">
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
                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full bg-emerald-600 px-4 py-1.5 text-[11px] font-bold text-white shadow-sm hover:bg-emerald-700 transition">
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
    <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-lg font-black text-[var(--foreground)]">Moderation Audit Log</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Immutable record of review deletions, user bans, and account restorations.</p>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs border-collapse">
                <thead>
                    <tr class="border-b border-[var(--border)] text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">
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
                                <span class="rounded-full px-2.5 py-0.5 text-[10px] font-extrabold uppercase {{ $log->action_taken === 'unban' ? 'bg-emerald-100 text-emerald-800' : (str_contains($log->action_taken, 'ban') ? 'bg-rose-100 text-rose-800' : 'bg-amber-100 text-amber-800') }}">
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

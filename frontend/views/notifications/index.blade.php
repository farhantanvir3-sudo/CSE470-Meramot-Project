{{--
=============================================================================
FEATURE R14: Unified User Notification Center
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Controller: VendorNotificationController@notificationCenter
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Notification Center | Meramot')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6 md:px-6 space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[var(--foreground)]">Notification Center</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Order updates, workshop quotes, courier milestones, and payment confirmations &middot; {{ $unreadCount ?? 0 }} unread
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 text-xs">
            <a href="{{ url('/notifications?filter=ALL') }}"
               class="rounded-md px-3.5 py-2 font-bold border transition {{ ($channelFilter ?? 'ALL') === 'ALL' ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] border-[var(--accent-dark)]' : 'bg-[var(--card)] text-[var(--foreground)] border-[var(--border)]' }}">
                All Alerts
            </a>
            <a href="{{ url('/notifications?filter=UNREAD') }}"
               class="rounded-md px-3.5 py-2 font-bold border transition {{ ($channelFilter ?? 'ALL') === 'UNREAD' ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] border-[var(--accent-dark)]' : 'bg-[var(--card)] text-[var(--foreground)] border-[var(--border)]' }}">
                Unread ({{ $unreadCount ?? 0 }})
            </a>
            @if(($unreadCount ?? 0) > 0)
                <a href="{{ url('/notifications?mark_all_read=1') }}"
                   class="rounded-md border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 px-3.5 py-2 font-bold text-emerald-900 transition">
                    <i class="fa-solid fa-check-double mr-1"></i> Mark All Read
                </a>
            @endif
        </div>
    </div>

    <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm space-y-3">
        @forelse($notifications as $item)
            <div class="rounded-lg border p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs transition {{ $item->is_read ? 'border-[var(--border)] bg-white/75' : 'border-emerald-400 bg-emerald-50/75 shadow-sm' }}">
                <div class="space-y-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2">
                        @if(!$item->is_read)
                            <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                        @endif
                        <span class="font-bold text-sm text-[var(--foreground)]">{{ $item->title }}</span>
                        <span class="text-[10px] text-[var(--muted-foreground)]">{{ optional($item->created_at)->diffForHumans() }}</span>
                    </div>
                    <p class="text-[var(--muted-foreground)] leading-relaxed">{{ $item->message }}</p>
                </div>

                @if($item->action_url)
                    <a href="{{ $item->action_url }}" class="self-start sm:self-center shrink-0 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                        Open &rarr;
                    </a>
                @endif
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--mint-100)]/60 p-10 text-center text-xs text-[var(--muted-foreground)]">
                <i class="fa-regular fa-bell text-2xl mb-2 block text-[var(--accent-dark)]"></i>
                You're all caught up — new repair bids, courier handovers, and payment updates will appear here automatically.
            </div>
        @endforelse

        @if(method_exists($notifications, 'links'))
            <div class="pt-3">
                {{ $notifications->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

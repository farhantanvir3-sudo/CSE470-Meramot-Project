{{--
=============================================================================
FEATURE R7: Customer "My Repair Requests & Direct Orders" Hub
=============================================================================
Assigned Member: Shanid Sajjatuz Islam [23201465]
Merged Dual-Path History View: Farhan Tanvir [22299179]
Controller: RepairRequestController@index
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'My Repair Requests & Orders | Meramot')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 md:px-6 space-y-6">

    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>›</span>
        <span class="font-semibold text-[var(--foreground)]">My Repair Requests &amp; Orders</span>
    </div>

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--border)] pb-4">
        <div>
            <span class="dev-only-badge text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mb-1.5">
                Feature R7 • Shanid Sajjatuz Islam [23201465]
            </span>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Track Your Repair Requests &amp; Orders</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Compare incoming workshop estimates, approve final bench diagnostics, and monitor scheduled doorstep orders.
            </p>
        </div>
        @php
            $isBannedViewer = auth()->check() && auth()->user()->isBanned();
        @endphp
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <a href="/packages/catalog" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                Fixed Packages
            </a>
            @if($isBannedViewer)
                <span class="inline-flex items-center gap-1.5 rounded-full border border-rose-300 bg-rose-50 px-4 py-2.5 text-xs font-bold text-rose-700">
                    <i class="fa-solid fa-ban text-[11px]"></i>
                    <span>New Requests Restricted</span>
                </span>
            @else
                <a href="/requests/create" class="rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                    + Post New Repair Request
                </a>
            @endif
        </div>
    </div>

    <!-- Filter Pills -->
    <div class="flex flex-wrap items-center gap-2 text-xs">
        @foreach([
            'ALL' => 'All Tickets',
            'BIDDING' => 'Open for Estimates',
            'DIRECT' => 'Direct Orders',
            'ACTIVE' => 'In Progress / Bench',
            'COMPLETED' => 'Past Orders (Completed)',
        ] as $filterKey => $filterLabel)
            <a
                href="/requests?filter={{ $filterKey }}"
                class="rounded-full px-4 py-2 font-bold transition {{ $statusFilter === $filterKey ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] border border-[var(--border)] text-[var(--foreground)] hover:bg-[var(--mint-100)]' }}"
            >
                {{ $filterLabel }}
            </a>
        @endforeach
    </div>

    <!-- Requests List -->
    <div class="space-y-4">
        @forelse($requests as $req)
            @php
                $st = strtoupper($req->status ?? 'PENDING');
                $assignedShop = $req->repairJob?->shop ?? $req->requestedShop;
                $activeBidsCount = $req->bids->where('status', '!=', 'WITHDRAWN')->count();
                $lowestBid = $req->bids->where('status', '!=', 'WITHDRAWN')->min('proposed_cost');
            @endphp
            <div class="rounded-[1.75rem] border border-[var(--border)] bg-[var(--card)] p-4 sm:p-5 md:p-6 shadow-sm hover:border-[var(--accent-dark)]/50 transition">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div class="space-y-1.5 flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono text-xs font-black text-[var(--muted-foreground)]">#{{ $req->id }}</span>
                            <span class="rounded-full px-3 py-0.5 text-[10px] font-extrabold uppercase {{ $st === 'COMPLETED' ? 'bg-emerald-100 text-emerald-900' : ($st === 'WAITING_APPROVAL' ? 'bg-amber-200 text-amber-950' : ($st === 'BIDDING' ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)]' : 'bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)]')) }}">
                                {{ str_replace('_', ' ', $st) }}
                            </span>
                            <span class="rounded-full border border-[var(--border)] bg-[var(--mint-100)]/70 px-2.5 py-0.5 text-[10px] font-bold text-[var(--foreground)]">
                                {{ ($req->source ?? 'MARKETPLACE') === 'DIRECT_SERVICE' ? 'Direct-Order Cart' : 'Marketplace Bidding' }}
                            </span>
                            @if($assignedShop)
                                <span class="text-xs font-bold text-[var(--accent-dark)] break-words">
                                    <i class="fa-solid fa-store mr-1"></i>{{ $assignedShop->name }}{{ $assignedShop->display_branch_tag ? ' [' . $assignedShop->display_branch_tag . ']' : '' }}
                                </span>
                            @endif
                        </div>

                        <h2 class="break-words text-base md:text-lg font-black text-[var(--foreground)]">
                            {{ $req->brand }} {{ $req->model_name }} — {{ $req->title }}
                        </h2>

                        <p class="text-xs text-[var(--muted-foreground)] line-clamp-2 break-words">
                            {{ $req->description }}
                        </p>

                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 pt-1 text-[11px] text-[var(--muted-foreground)]">
                            <span><i class="fa-solid fa-laptop-medical mr-1 text-[var(--accent-dark)]"></i>{{ $req->device_type }}</span>
                            @if($req->pickup_address)
                                <span class="break-words"><i class="fa-solid fa-location-dot mr-1 text-[var(--accent-dark)]"></i>{{ $req->pickup_address }}</span>
                            @endif
                            <span><i class="fa-regular fa-clock mr-1"></i>{{ $req->created_at?->diffForHumans() }}</span>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row md:flex-col items-stretch sm:items-center md:items-end justify-between gap-3 shrink-0 w-full md:w-auto border-t md:border-t-0 pt-3 md:pt-0 border-[var(--border)]">
                        <div class="text-left md:text-right">
                            @if($req->quoted_final_amount)
                                <span class="text-[10px] uppercase font-bold text-[var(--muted-foreground)] block">Quoted / Agreed</span>
                                <span class="text-xl font-black text-[var(--accent-dark)]">৳{{ number_format($req->quoted_final_amount, 2) }}</span>
                            @elseif($activeBidsCount > 0)
                                <span class="text-[10px] uppercase font-bold text-emerald-700 block">{{ $activeBidsCount }} Workshop {{ Str::plural('Bid', $activeBidsCount) }}</span>
                                <span class="text-lg font-black text-[var(--accent-dark)]">From ৳{{ number_format($lowestBid, 0) }}</span>
                            @else
                                <span class="text-xs font-bold text-[var(--muted-foreground)]">Awaiting Workshop Quotes</span>
                            @endif
                        </div>

                        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 w-full sm:w-auto">
                            @if(in_array($st, ['COMPLETED', 'RETURNED_TO_CUSTOMER'], true) && $assignedShop)
                                @if($req->review)
                                    <a
                                        href="{{ route('shops.show', $assignedShop->slug) }}?edit_review={{ $req->review->id }}#review-{{ $req->review->id }}"
                                        class="w-full sm:w-auto inline-flex items-center justify-center whitespace-nowrap gap-1.5 rounded-full border border-emerald-300 bg-emerald-100 px-4 py-2 text-xs font-bold text-emerald-900 shadow-sm hover:bg-emerald-200 transition"
                                    >
                                        <i class="fa-solid fa-pen-to-square text-emerald-700 text-[11px]"></i>
                                        <span>Edit Review ({{ $req->review->rating }}★)</span>
                                    </a>
                                    <form
                                        method="POST"
                                        action="{{ route('reviews.destroy', $req->review->id) }}"
                                        data-confirm-delete
                                        data-confirm-title="Delete Verified Review?"
                                        data-confirm-item="Order #{{ $req->id }}"
                                        data-confirm-message="Your verified review will be deleted and you can write a new one anytime."
                                        data-confirm-cta="Delete Review"
                                        class="w-full sm:w-auto inline-flex"
                                    >
                                        @csrf
                                        @method('DELETE')
                                        <input type="hidden" name="redirect_back" value="1">
                                        <button
                                            type="submit"
                                            class="w-full sm:w-auto inline-flex items-center justify-center whitespace-nowrap gap-1.5 rounded-full bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition"
                                        >
                                            <i class="fa-solid fa-trash-can text-[11px]"></i>
                                            <span>Delete Review</span>
                                        </button>
                                    </form>
                                @else
                                    @if($isBannedViewer)
                                        <span class="w-full sm:w-auto inline-flex items-center justify-center whitespace-nowrap gap-1.5 rounded-full border border-rose-300 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-700">
                                            <i class="fa-solid fa-ban text-[11px]"></i>
                                            <span>Reviews Restricted (Banned)</span>
                                        </span>
                                    @else
                                        <a
                                            href="{{ route('reviews.create', ['shopId' => $assignedShop->id, 'order_id' => $req->id]) }}"
                                            class="w-full sm:w-auto inline-flex items-center justify-center whitespace-nowrap gap-1.5 rounded-full border border-emerald-600/40 bg-emerald-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition"
                                        >
                                            <i class="fa-solid fa-certificate text-[11px]"></i>
                                            <span>Write Verified Repair Review</span>
                                        </a>
                                    @endif
                                @endif
                            @endif

                            <a
                                href="{{ route('requests.track', $req->id) }}"
                                class="w-full sm:w-auto inline-flex items-center justify-center whitespace-nowrap gap-1.5 rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition"
                            >
                                <span>{{ $st === 'WAITING_APPROVAL' ? 'Review Quote 2' : ($st === 'BIDDING' && $activeBidsCount > 0 ? 'Compare Bids' : 'Track Lifecycle') }}</span>
                                <i class="fa-solid fa-arrow-right text-[10px]"></i>
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        @empty
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-10 text-center shadow-sm">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-[var(--mint-100)] text-[var(--accent-dark)]">
                    <i class="fa-solid fa-screwdriver-wrench text-xl"></i>
                </div>
                <h3 class="text-lg font-black text-[var(--foreground)]">No repair requests match this filter yet</h3>
                <p class="mt-1 text-xs text-[var(--muted-foreground)]">Publish a repair request to receive estimates from verified local workshops.</p>
                @if(!$isBannedViewer)
                    <a href="/requests/create" class="mt-5 inline-flex items-center gap-2 rounded-full bg-[var(--accent-dark)] px-6 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                        Post a Repair Request &rarr;
                    </a>
                @endif
            </div>
        @endforelse
    </div>

    @if($requests->hasPages())
        <div class="pt-2">
            {{ $requests->links() }}
        </div>
    @endif

</div>
@endsection

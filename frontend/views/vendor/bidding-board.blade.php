@extends('layouts.app')

@section('title', 'Vendor Live Bidding Board | Meramot')

@section('content')
@php
    $totalTickets = method_exists($openRequests, 'total') ? $openRequests->total() : ($openRequests ?? collect())->count();
@endphp
<div
    class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6"
    x-data="{
        liveCount: {{ (int) $totalTickets }},
        lastSynced: '{{ now()->format('h:i:s A') }}',
        hasNewLead: false,
        initialLatestId: {{ (int) (optional(($openRequests ?? collect())->first())->id ?? 0) }},
        init() {
            setInterval(async () => {
                try {
                    const url = new URL(window.location.href);
                    url.searchParams.set('poll', '1');
                    const res = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    if (data && data.ok) {
                        this.liveCount = data.open_count;
                        this.lastSynced = data.server_time;
                        if (data.latest_id && this.initialLatestId && Number(data.latest_id) > Number(this.initialLatestId)) {
                            this.hasNewLead = true;
                        }
                    }
                } catch (e) {}
            }, 15000);
        }
    }"
>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--border)] pb-4">
        <div>
            <h1 class="text-2xl font-black text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-gavel text-amber-700"></i>
                Live Repair Bidding Marketplace
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R12 &amp; R13 • Assigned: Farhan Tanvir [22299179] (Lead)
                </span>
                <span>Inspect AI hardware triage reports and submit itemized diagnostic estimates on live customer tickets.</span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 self-start">
            <a href="/vendor/alert-settings" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                <i class="fa-solid fa-bell mr-1"></i>Alert Settings
            </a>
            <a href="/vendor/dashboard?tab=marketplace" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                &larr; Vendor Dashboard
            </a>
            <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] font-bold text-xs px-3.5 py-2 inline-flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-emerald-600 animate-pulse"></span>
                <span><span x-text="liveCount">{{ $totalTickets }}</span> Open Leads</span>
            </span>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-400 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900 flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-900 flex items-center gap-2">
            <i class="fa-solid fa-circle-exclamation text-rose-700"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <div x-show="hasNewLead" x-cloak class="rounded-2xl border border-emerald-400 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-950 flex items-center justify-between gap-3">
        <span><i class="fa-solid fa-bolt text-amber-600 mr-1"></i> New customer repair lead published since you opened this board.</span>
        <button type="button" onclick="window.location.reload()" class="rounded-full bg-[var(--accent-dark)] px-3.5 py-1.5 text-[11px] font-bold text-white">Refresh Board</button>
    </div>

    <!-- Search, Scope & Filter Toolbar -->
    <form method="GET" action="/vendor/bidding-board" class="rounded-[1.75rem] border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm space-y-3 text-xs">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach(['all' => 'All Open Tickets', 'unbid' => 'Unquoted by Us', 'my_bids' => 'Quoted by Us', 'direct' => 'Direct Shop Inquiries'] as $sKey => $sLabel)
                    <a
                        href="{{ request()->fullUrlWithQuery(['scope' => $sKey, 'page' => null]) }}"
                        class="rounded-full px-3.5 py-1.5 font-bold border transition {{ ($scopeFilter ?? 'all') === $sKey ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] border-[var(--accent-dark)]' : 'bg-[var(--mint-100)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-200)]' }}"
                    >
                        {{ $sLabel }}
                    </a>
                @endforeach
            </div>
            <span class="text-[11px] text-[var(--muted-foreground)]">Live sync: <span class="font-mono font-semibold" x-text="lastSynced"></span></span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-2.5">
            <input
                type="text"
                name="q"
                value="{{ $search ?? '' }}"
                placeholder="Search brand, model, fault keyword..."
                class="lg:col-span-2 rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2 font-medium text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
            >
            <select name="device_type" class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 font-semibold text-[var(--foreground)] outline-none">
                <option value="">All Devices</option>
                @foreach(['Smartphone', 'Laptop', 'Tablet', 'Desktop', 'Monitor', 'Console', 'Camera'] as $dt)
                    <option value="{{ $dt }}" {{ strcasecmp((string) ($deviceFilter ?? ''), $dt) === 0 ? 'selected' : '' }}>{{ $dt }}</option>
                @endforeach
            </select>
            <select name="mode" class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 font-semibold text-[var(--foreground)] outline-none">
                <option value="">Pickup &amp; Walk-In</option>
                <option value="pickup" {{ ($modeFilter ?? '') === 'pickup' ? 'selected' : '' }}>Courier Pickup Preferred</option>
                <option value="walk_in" {{ ($modeFilter ?? '') === 'walk_in' ? 'selected' : '' }}>In-Shop Dropoff</option>
            </select>
            <div class="flex gap-2">
                <select name="sort" class="flex-1 rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 font-semibold text-[var(--foreground)] outline-none">
                    <option value="newest" {{ ($sort ?? 'newest') === 'newest' ? 'selected' : '' }}>Newest First</option>
                    <option value="fewest_bids" {{ ($sort ?? '') === 'fewest_bids' ? 'selected' : '' }}>Fewest Bids First</option>
                    <option value="oldest" {{ ($sort ?? '') === 'oldest' ? 'selected' : '' }}>Oldest Waiting</option>
                </select>
                <input type="hidden" name="scope" value="{{ $scopeFilter ?? 'all' }}">
                <button type="submit" class="rounded-xl bg-[var(--accent-dark)] px-4 py-2 font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                    Apply
                </button>
            </div>
        </div>
    </form>

    <div class="space-y-6">
        @forelse($openRequests ?? [] as $req)
            @php
                $triage = is_array($req->ai_triage_json) ? $req->ai_triage_json : null;
                $riskLevel = strtoupper((string) ($triage['risk_level'] ?? 'MEDIUM'));
                $riskBadge = match ($riskLevel) {
                    'HIGH' => 'bg-rose-100 text-rose-900 border-rose-300',
                    'LOW' => 'bg-emerald-100 text-emerald-900 border-emerald-300',
                    default => 'bg-amber-100 text-amber-900 border-amber-300',
                };
                $photos = is_array($req->photos) ? $req->photos : [];
                $firstPhoto = $photos[0] ?? null;
                $firstPhotoUrl = $firstPhoto
                    ? ((str_starts_with($firstPhoto, 'http') || str_starts_with($firstPhoto, 'data:') || str_starts_with($firstPhoto, '/')) ? $firstPhoto : asset('storage/' . $firstPhoto))
                    : null;
            @endphp
            <div id="ticket-{{ $req->id }}" class="grid grid-cols-1 lg:grid-cols-3 gap-6 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm">
                <!-- Left: Active Request & AI Triage Card -->
                <div class="flex flex-col justify-between space-y-3">
                    <div>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-1.5">
                                <span class="text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-emerald-800 text-white">
                                    Bidding Open • #{{ $req->id }}
                                </span>
                                <span class="text-[10px] font-bold px-2.5 py-0.5 rounded-full border bg-[var(--mint-100)] text-[var(--accent-dark)] border-[var(--border)]">
                                    {{ $req->preferred_pickup ? 'Courier Pickup' : 'Walk-In' }}
                                </span>
                            </div>
                            <a href="{{ route('requests.track', $req->id) }}" class="text-[11px] font-bold text-[var(--accent-dark)] hover:underline">
                                Customer View &rarr;
                            </a>
                        </div>

                        <div class="mt-3 flex items-start gap-3">
                            @if($firstPhotoUrl)
                                <img src="{{ $firstPhotoUrl }}" alt="Fault photo" class="h-16 w-16 rounded-2xl object-cover border border-[var(--border)] shrink-0">
                            @endif
                            <div class="min-w-0">
                                <h3 class="font-black text-[var(--foreground)] text-base break-words">
                                    {{ $req->brand }} {{ $req->model_name }} ({{ $req->device_type }})
                                </h3>
                                <p class="text-xs text-[var(--accent-dark)] mt-0.5 font-bold break-words">{{ $req->title }}</p>
                            </div>
                        </div>

                        <p class="text-xs text-[var(--muted-foreground)] mt-2 leading-relaxed line-clamp-3">{{ $req->description }}</p>

                        <!-- R13 AI Hardware Triage Snippet -->
                        @if($req->ai_summary)
                            <div class="mt-3 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/80 p-3 space-y-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-[10px] font-black uppercase tracking-wider text-[var(--accent-dark)] flex items-center gap-1">
                                        <i class="fa-solid fa-microchip text-amber-700"></i> AI Triage
                                    </span>
                                    <span class="rounded-full border px-2 py-0.5 text-[9px] font-black {{ $riskBadge }}">{{ $riskLevel }} RISK</span>
                                </div>
                                <p class="text-[11px] text-[var(--foreground)] line-clamp-2 leading-snug">{{ $req->ai_summary }}</p>
                            </div>
                        @endif

                        <div class="mt-3 text-[11px] text-[var(--muted-foreground)] space-y-1">
                            <div><i class="fa-solid fa-location-dot text-[var(--accent-dark)] mr-1"></i> {{ $req->pickup_address ?? 'Dhaka, Bangladesh' }}</div>
                            <div><i class="fa-solid fa-clock text-[var(--accent-dark)] mr-1"></i> Posted {{ $req->created_at?->diffForHumans() }} • {{ $req->bids->count() }} active {{ $req->bids->count() === 1 ? 'bid' : 'bids' }}</div>
                        </div>
                    </div>

                    <div class="pt-3 border-t border-[var(--border)] flex items-center justify-between text-[11px]">
                        <span class="text-[var(--muted-foreground)]">AI Triage &amp; Photos</span>
                        <a href="/vendor/request-details/{{ $req->id }}" class="inline-flex items-center gap-1 rounded-full bg-[var(--accent-dark)] px-3.5 py-1.5 font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                            <span>Full Triage &amp; Parts Pre-Fill</span>
                            <span>&rarr;</span>
                        </a>
                    </div>
                </div>

                <!-- Right: Submit / Edit Itemized Diagnostic Estimate -->
                <div class="lg:col-span-2 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-4 sm:p-5">
                    <h4 class="font-bold text-sm text-[var(--foreground)] mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[var(--accent-dark)]"></i>
                        Itemized Estimate on Ticket #{{ $req->id }}
                    </h4>

                    @include('vendor.partials.bid-quote-widget', [
                        'req' => $req,
                        'shopOptions' => $vendorShops ?? collect(),
                        'lockedShopId' => null,
                    ])

                    <p class="mt-3 text-[11px] text-[var(--muted-foreground)]">
                        10.00% Platform Escrow Commission automatically accounted on completion.
                    </p>
                </div>
            </div>
        @empty
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-10 text-center shadow-sm">
                <i class="fa-solid fa-gavel text-3xl text-[var(--muted-foreground)] mb-3 block"></i>
                <h3 class="text-lg font-black text-[var(--foreground)]">No open marketplace tickets match your current filters</h3>
                <p class="text-xs text-[var(--muted-foreground)] mt-1">Clear filters or return to the Vendor Dashboard while awaiting new customer tickets.</p>
                <div class="mt-4 flex flex-wrap justify-center gap-2">
                    <a href="/vendor/bidding-board" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-5 py-2.5 text-xs font-bold text-[var(--foreground)]">
                        Reset Filters
                    </a>
                    <a href="/vendor/dashboard" class="rounded-full bg-[var(--accent-dark)] px-6 py-2.5 text-xs font-bold text-[var(--accent-foreground)]">
                        Vendor Dashboard &rarr;
                    </a>
                </div>
            </div>
        @endforelse
    </div>

    @if(method_exists($openRequests, 'hasPages') && $openRequests->hasPages())
        <div class="pt-2">
            {{ $openRequests->links() }}
        </div>
    @endif

</div>
@endsection

@extends('layouts.app')

@section('title', 'Explore Verified Repair Shops in Dhaka | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6">

    <!-- Breadcrumb & Page Header -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
                <a href="/" class="hover:underline">Home</a>
                <span>/</span>
                <span class="text-[var(--foreground)] font-semibold">Repair Shops</span>
                @if(request('area') && request('area') !== 'All')
                    <span>/</span>
                    <span class="text-[var(--accent-dark)] font-bold">{{ request('area') }}</span>
                @endif
                @if(request('q'))
                    <span>/</span>
                    <span class="italic">"{{ request('q') }}"</span>
                @endif
            </div>
            <h1 class="text-2xl font-black text-[var(--foreground)]">
                Explore Verified Repair Shops
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-0.5 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R6 • Lead: Shanid Sajjatuz Islam [23201465]
                </span>
                <span>Discover top-rated electronic repair experts across Dhaka</span>
            </p>
        </div>

        <!-- Quick Action: Make Request Button -->
        <div class="flex items-center gap-3">
            <a href="/requests/create" class="inline-flex items-center gap-2 rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-white shadow hover:opacity-90 transition">
                <i class="fa-solid fa-plus"></i>
                <span>Post Device Repair Request</span>
            </a>
        </div>
    </div>

    <!-- Main Layout: 2 Columns on Desktop (Matching SidebarFilters.tsx & shops-results-client.tsx) -->
    <div class="grid grid-cols-1 lg:grid-cols-[240px_minmax(0,1fr)] gap-6 items-start">

        <!-- ── Left Column: Sticky Sidebar Filters (Exact match to SidebarFilters.tsx) ── -->
        <aside class="space-y-4 lg:sticky lg:top-24 hidden lg:block shrink-0">
            
            <!-- 1. Sort Shops Card -->
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                <h3 class="mb-4 text-[1.05rem] font-semibold text-[var(--foreground)]">
                    Sort shops
                </h3>
                <div class="space-y-3">
                    @php
                        $currSort = request('sort', 'topRated');
                        $sortOptions = [
                            ['label' => 'Top rated', 'value' => 'topRated'],
                            ['label' => 'Relevance', 'value' => 'relevance'],
                            ['label' => 'Nearest to me', 'value' => 'distance'],
                            ['label' => 'Lower price', 'value' => 'price'],
                        ];
                    @endphp
                    @foreach($sortOptions as $opt)
                        @php
                            $sParams = array_merge(request()->query(), ['sort' => $opt['value'], 'page' => 1]);
                            $isActive = ($currSort === $opt['value']);
                        @endphp
                        <a href="/shops?{{ http_build_query($sParams) }}" class="flex w-full items-center gap-3 rounded-2xl px-2 py-2 text-left text-sm text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                            <span class="h-4 w-4 rounded-full border flex items-center justify-center {{ $isActive ? 'border-[var(--accent-dark)] bg-[var(--accent-dark)]' : 'border-[var(--border)] bg-[var(--card)]' }}">
                                @if($isActive)
                                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                @endif
                            </span>
                            <span class="{{ $isActive ? 'font-bold text-[var(--accent-dark)]' : '' }}">{{ $opt['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- 2. Distance Card -->
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm" x-data="{ dist: {{ request('maxDistanceKm', 12) }} }">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-[1.05rem] font-semibold text-[var(--foreground)]">
                        Distance
                    </h3>
                    <span class="rounded-full bg-[var(--mint-100)] px-3 py-1 text-xs font-semibold text-[var(--accent-dark)]">
                        Up to <span x-text="dist">12</span> km
                    </span>
                </div>
                <input
                    type="range"
                    min="1"
                    max="25"
                    step="1"
                    x-model="dist"
                    @change="window.location.href = '/shops?' + new URLSearchParams({...Object.fromEntries(new URLSearchParams(window.location.search)), maxDistanceKm: dist, page: 1}).toString()"
                    class="w-full accent-[var(--accent-dark)]"
                />
                <p class="mt-3 text-xs text-[var(--muted-foreground)]">
                    Limit results to nearby shops and courier coverage.
                </p>
            </div>

            <!-- 3. Offers & Savings Card -->
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                <h3 class="mb-4 text-[1.05rem] font-semibold text-[var(--foreground)]">
                    Offers & savings
                </h3>
                <div class="space-y-3">
                    @php
                        $offers = [
                            ['label' => 'Vouchers', 'key' => 'voucher'],
                            ['label' => 'Free delivery', 'key' => 'freeDelivery'],
                            ['label' => 'Deals', 'key' => 'deals'],
                        ];
                    @endphp
                    @foreach($offers as $off)
                        @php
                            $isCheck = request()->boolean($off['key']);
                            $offParams = array_merge(request()->query(), [$off['key'] => $isCheck ? null : 'true', 'page' => 1]);
                        @endphp
                        <a href="/shops?{{ http_build_query(array_filter($offParams)) }}" class="flex cursor-pointer items-center gap-3 rounded-2xl px-2 py-2 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                            <input
                                type="checkbox"
                                {{ $isCheck ? 'checked' : '' }}
                                class="h-4 w-4 rounded border-[var(--border)] accent-[var(--accent-dark)] pointer-events-none"
                            />
                            <span class="{{ $isCheck ? 'font-bold text-[var(--accent-dark)]' : '' }}">{{ $off['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

        </aside>

        <!-- ── Right Column: Area Pills, Sort Tabs & Rich Shop Cards ── -->
        <main class="min-w-0 max-w-full space-y-4">
            
            <!-- Area Selector Pills & Quick Filters Bar -->
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm space-y-3 min-w-0 max-w-full">
                <!-- Row 1: Area Pills -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs whitespace-nowrap no-scrollbar">
                    <span class="font-bold text-[var(--foreground)] text-[11px] uppercase tracking-wider mr-1 shrink-0">
                        <i class="fa-solid fa-location-dot text-[var(--accent-dark)] mr-1"></i> Area:
                    </span>
                    @php
                        $activeArea = request('area', 'All');
                    @endphp
                    @foreach($areas as $area)
                        @php
                            $areaParams = array_merge(request()->query(), ['area' => $area, 'page' => 1]);
                        @endphp
                        <a href="/shops?{{ http_build_query($areaParams) }}" 
                           class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-semibold transition {{ ($activeArea === $area || ($area === 'All' && empty(request('area')))) ? 'bg-[var(--accent-dark)] text-white shadow-sm' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]' }}">
                            {{ $area }}
                        </a>
                    @endforeach
                </div>

                <!-- Row 2: Sort Tabs & Promo Filters -->
                <div class="border-t border-[var(--border)] pt-3 flex flex-wrap items-center justify-between gap-3 text-xs min-w-0">
                    <!-- Quick Sort Tabs -->
                    <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto no-scrollbar max-w-full">
                        <span class="font-bold text-[var(--foreground)] text-[11px] uppercase mr-1 shrink-0">Sort:</span>
                        @php
                            $sortTabs = [
                                ['label' => 'Top Rated', 'value' => 'topRated', 'icon' => 'fa-star'],
                                ['label' => 'Lowest Price', 'value' => 'price', 'icon' => 'fa-tag'],
                                ['label' => 'Nearest Distance', 'value' => 'distance', 'icon' => 'fa-compass'],
                                ['label' => 'Best Match', 'value' => 'relevance', 'icon' => 'fa-bullseye'],
                            ];
                        @endphp
                        @foreach($sortTabs as $st)
                            @php
                                $sortParams = array_merge(request()->query(), ['sort' => $st['value'], 'page' => 1]);
                            @endphp
                            <a href="/shops?{{ http_build_query($sortParams) }}" 
                               class="shrink-0 px-3 py-1 rounded-xl transition flex items-center gap-1.5 {{ $currSort === $st['value'] ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-50)]' }}">
                                <i class="fa-solid {{ $st['icon'] }} text-[10px]"></i>
                                <span>{{ $st['label'] }}</span>
                            </a>
                        @endforeach
                    </div>

                    <!-- Quick Promo Toggles -->
                    <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                        @php
                            $hasVoucher = request()->boolean('voucher');
                            $voucherParams = array_merge(request()->query(), ['voucher' => $hasVoucher ? null : 'true', 'page' => 1]);

                            $hasFreeDel = request()->boolean('freeDelivery');
                            $delParams = array_merge(request()->query(), ['freeDelivery' => $hasFreeDel ? null : 'true', 'page' => 1]);

                            $hasDeals = request()->boolean('deals');
                            $dealsParams = array_merge(request()->query(), ['deals' => $hasDeals ? null : 'true', 'page' => 1]);
                        @endphp

                        <a href="/shops?{{ http_build_query(array_filter($voucherParams)) }}" 
                           class="px-2.5 py-1 rounded-xl border border-[var(--border)] transition flex items-center gap-1.5 {{ $hasVoucher ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                            <i class="fa-solid fa-ticket text-[10px] text-amber-600"></i>
                            <span>Vouchers</span>
                        </a>

                        <a href="/shops?{{ http_build_query(array_filter($delParams)) }}" 
                           class="px-2.5 py-1 rounded-xl border border-[var(--border)] transition flex items-center gap-1.5 {{ $hasFreeDel ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                            <i class="fa-solid fa-truck-fast text-[10px] text-emerald-700"></i>
                            <span>Free Delivery</span>
                        </a>

                        <a href="/shops?{{ http_build_query(array_filter($dealsParams)) }}" 
                           class="px-2.5 py-1 rounded-xl border border-[var(--border)] transition flex items-center gap-1.5 {{ $hasDeals ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                            <i class="fa-solid fa-fire text-[10px] text-rose-600"></i>
                            <span>Deals</span>
                        </a>

                        @if(request()->anyFilled(['q', 'area', 'voucher', 'freeDelivery', 'deals', 'category', 'maxDistanceKm']))
                            <a href="/shops" class="text-[11px] text-rose-700 font-bold hover:underline ml-1">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Active Results Counter -->
            <div class="flex items-center justify-between text-xs text-[var(--muted-foreground)] px-1">
                <span>Showing <strong>{{ $shops->total() }}</strong> verified repair shops</span>
                @if(request('q'))
                    <span>Search: <strong class="text-[var(--foreground)]">"{{ request('q') }}"</strong></span>
                @endif
            </div>

            <!-- ── Shop Result Cards (Exact match to ShopResultCard from commit 8e44218) ── -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 min-w-0">
                @forelse($shops as $shop)
                    <a
                        href="/shops/{{ $shop->slug }}"
                        class="group flex flex-col justify-between rounded-[1.6rem] border border-[var(--border)] bg-[var(--card)] p-3.5 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md md:p-4 min-w-0"
                    >
                        <!-- Top row: logo + name/rating + price -->
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-[var(--mint-100)] text-lg font-bold text-[var(--accent-dark)] border border-[var(--border)] md:h-14 md:w-14 md:text-2xl overflow-hidden">
                                @if($shop->logo_url)
                                    <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-full w-full object-cover" />
                                @else
                                    {{ strtoupper(substr($shop->name, 0, 1)) }}
                                @endif
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <h3 class="truncate text-[0.95rem] font-bold leading-tight text-[var(--foreground)] md:text-[1.1rem] group-hover:text-[var(--accent-dark)] transition">
                                            {{ $shop->name }}
                                        </h3>

                                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11px] text-[var(--muted-foreground)] md:text-xs">
                                            <span>⭐ {{ number_format($shop->rating, 1) }}</span>
                                            <span class="opacity-60">·</span>
                                            <span>{{ $shop->review_count ?? 12 }} reviews</span>
                                            <span class="opacity-60">·</span>
                                            <span><i class="fa-solid fa-location-dot text-[10px] text-[var(--accent-dark)]"></i> {{ $shop->area }}</span>
                                        </div>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <div class="text-base font-black leading-tight text-[var(--accent-dark)] md:text-lg">
                                            ৳{{ number_format($shop->inspection_fee ?: 200) }}
                                        </div>
                                        <div class="text-[10px] font-semibold text-[var(--muted-foreground)]">
                                            {{ $shop->eta_minutes < 60 ? $shop->eta_minutes . ' min' : round($shop->eta_minutes / 60) . ' hr' }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Price + Labor + ETA compact row -->
                        <div class="mt-2.5 grid grid-cols-3 items-center rounded-xl bg-[var(--mint-50)] px-2.5 py-1.5 md:mt-3 text-center min-w-0">
                            <div>
                                <span class="block truncate text-[9px] font-semibold uppercase text-[var(--muted-foreground)]">Inspection</span>
                                <div class="text-xs font-black text-[var(--accent-dark)] sm:text-sm">৳{{ number_format($shop->inspection_fee ?: 200) }}</div>
                            </div>
                            <div class="border-x border-[var(--border)]/50 px-1">
                                <span class="block truncate text-[9px] font-semibold uppercase text-[var(--muted-foreground)]">Labor</span>
                                <div class="text-xs font-black text-[var(--accent-dark)] sm:text-sm">৳{{ number_format($shop->base_labor_fee ?: 400) }}</div>
                            </div>
                            <div>
                                <span class="block truncate text-[9px] font-semibold uppercase text-[var(--muted-foreground)]">ETA</span>
                                <div class="text-xs font-bold text-[var(--foreground)] sm:text-sm">
                                    {{ $shop->eta_minutes < 60 ? $shop->eta_minutes . ' min' : round($shop->eta_minutes / 60) . ' hr' }}
                                </div>
                            </div>
                        </div>

                        <!-- Description -->
                        <p class="mt-2 line-clamp-1 text-[11px] text-[var(--muted-foreground)] md:text-xs">
                            {{ $shop->description ?? $shop->address }}
                        </p>

                        <!-- Badges -->
                        <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[10px] font-medium text-[var(--accent-dark)] md:text-[11px]">
                            @if($shop->has_voucher)
                                <span class="rounded-full bg-[var(--mint-100)] px-2 py-0.5">Voucher</span>
                            @endif
                            @if($shop->free_delivery)
                                <span class="rounded-full bg-[var(--mint-100)] px-2 py-0.5 text-emerald-900 font-semibold">Free delivery</span>
                            @endif
                            @if($shop->has_deals)
                                <span class="rounded-full bg-[var(--mint-100)] px-2 py-0.5 text-rose-900 font-semibold">Deal</span>
                            @endif
                            <span class="rounded-full bg-[var(--mint-100)] px-2 py-0.5 font-bold">
                                {{ str_repeat('৳', $shop->price_level ?? 2) }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="col-span-2 py-16 text-center rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-8">
                        <div class="text-4xl mb-3">🔍</div>
                        <h3 class="text-base font-bold text-[var(--foreground)]">No repair shops found</h3>
                        <p class="text-xs text-[var(--muted-foreground)] mt-1">We couldn't find any shops matching your filter parameters. Try clearing some filters or searching a different area.</p>
                        <a href="/shops" class="inline-block mt-4 rounded-full bg-[var(--accent-dark)] px-5 py-2 text-xs font-bold text-white">
                            Reset All Filters
                        </a>
                    </div>
                @endforelse
            </div>

            <!-- Pagination Links -->
            <div class="mt-8 flex justify-center">
                {{ $shops->links() }}
            </div>

        </main>
    </div>

</div>
@endsection

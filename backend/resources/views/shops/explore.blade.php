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

    <!-- Filters & Area Pills Bar -->
    <div class="meramot-card p-4 mb-6 shadow-sm space-y-3">
        
        <!-- Row 1: Area Pills -->
        <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs whitespace-nowrap">
            <span class="font-bold text-[var(--foreground)] text-[11px] uppercase tracking-wider mr-1">
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
                   class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition {{ ($activeArea === $area || ($area === 'All' && empty(request('area')))) ? 'bg-[var(--accent-dark)] text-white shadow-sm' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]' }}">
                    {{ $area }}
                </a>
            @endforeach
        </div>

        <div class="border-t border-[var(--border)] pt-3 flex flex-wrap items-center justify-between gap-3 text-xs">
            
            <!-- Row 2 Left: Sort Tabs (Matching legacy sortTabs) -->
            <div class="flex items-center gap-2 overflow-x-auto">
                <span class="font-bold text-[var(--foreground)] text-[11px] uppercase mr-1">Sort:</span>
                @php
                    $activeSort = request('sort', 'topRated');
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
                       class="px-3 py-1 rounded-xl transition flex items-center gap-1.5 {{ $activeSort === $st['value'] ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-50)]' }}">
                        <i class="fa-solid {{ $st['icon'] }} text-[10px]"></i>
                        <span>{{ $st['label'] }}</span>
                    </a>
                @endforeach
            </div>

            <!-- Row 2 Right: Promo Toggles (Vouchers, Free Delivery, Deals) -->
            <div class="flex items-center gap-2">
                @php
                    $hasVoucher = request()->boolean('voucher') || request()->boolean('has_voucher');
                    $voucherParams = array_merge(request()->query(), ['voucher' => $hasVoucher ? null : 'true', 'page' => 1]);

                    $hasFreeDel = request()->boolean('freeDelivery') || request()->boolean('free_delivery');
                    $delParams = array_merge(request()->query(), ['freeDelivery' => $hasFreeDel ? null : 'true', 'page' => 1]);

                    $hasDeals = request()->boolean('deals') || request()->boolean('has_deals');
                    $dealsParams = array_merge(request()->query(), ['deals' => $hasDeals ? null : 'true', 'page' => 1]);
                @endphp

                <a href="/shops?{{ http_build_query(array_filter($voucherParams)) }}" 
                   class="px-3 py-1 rounded-xl border border-[var(--border)] transition flex items-center gap-1.5 {{ $hasVoucher ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                    <i class="fa-solid fa-ticket text-[10px] text-amber-500"></i>
                    <span>Vouchers</span>
                </a>

                <a href="/shops?{{ http_build_query(array_filter($delParams)) }}" 
                   class="px-3 py-1 rounded-xl border border-[var(--border)] transition flex items-center gap-1.5 {{ $hasFreeDel ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                    <i class="fa-solid fa-truck-fast text-[10px] text-emerald-600"></i>
                    <span>Free Delivery</span>
                </a>

                <a href="/shops?{{ http_build_query(array_filter($dealsParams)) }}" 
                   class="px-3 py-1 rounded-xl border border-[var(--border)] transition flex items-center gap-1.5 {{ $hasDeals ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                    <i class="fa-solid fa-fire text-[10px] text-rose-500"></i>
                    <span>Deals</span>
                </a>

                @if(request()->anyFilled(['q', 'area', 'voucher', 'freeDelivery', 'deals', 'category']))
                    <a href="/shops" class="text-[11px] text-rose-700 font-bold hover:underline ml-2">
                        Reset Filters
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Active Filters Summary Banner -->
    <div class="mb-4 flex items-center justify-between text-xs text-[var(--muted-foreground)]">
        <span>Showing <strong>{{ $shops->total() }}</strong> verified repair shops</span>
        @if(request('q'))
            <span>Search keyword: <strong class="text-[var(--foreground)]">"{{ request('q') }}"</strong></span>
        @endif
    </div>

    <!-- Shop Result Cards Grid (Faithful Port of ShopResultCard from legacy Meramot) -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        @forelse($shops as $shop)
            <div class="group rounded-[1.6rem] border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md flex flex-col justify-between">
                
                <div>
                    <!-- Shop Card Top Info -->
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex items-start gap-3 min-w-0">
                            <!-- Shop Logo or Letter Badge -->
                            <div class="h-14 w-14 shrink-0 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] flex items-center justify-center font-black text-2xl text-[var(--accent-dark)] shadow-sm">
                                {{ strtoupper(substr($shop->name, 0, 1)) }}
                            </div>

                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <h3 class="truncate text-base font-bold text-[var(--foreground)] group-hover:text-[var(--accent-dark)] transition">
                                        <a href="/shops/{{ $shop->slug }}">{{ $shop->name }}</a>
                                    </h3>
                                    @if($shop->is_featured)
                                        <span class="rounded-full bg-emerald-800 text-white text-[9px] font-extrabold px-2 py-0.5 uppercase tracking-wider">
                                            Verified
                                        </span>
                                    @endif
                                </div>

                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-[var(--muted-foreground)]">
                                    <span class="font-bold text-[var(--foreground)] text-amber-700">⭐ {{ number_format($shop->rating, 1) }}</span>
                                    <span>({{ number_format($shop->review_count) }})</span>
                                    <span>•</span>
                                    <span><i class="fa-solid fa-location-dot text-[10px] text-[var(--accent-dark)]"></i> {{ $shop->area }}, Dhaka</span>
                                    <span>•</span>
                                    <span>{{ $shop->eta_minutes < 60 ? $shop->eta_minutes . ' min' : round($shop->eta_minutes / 60) . ' hour' }}</span>
                                </div>
                            </div>
                        </div>

                        <!-- Right Pricing & ETA Tag -->
                        <div class="shrink-0 text-right">
                            <div class="text-xl font-extrabold leading-none text-[var(--accent-dark)]">
                                {{ $shop->offer_summary ?? 'Inspection ৳' . number_format($shop->inspection_fee ?? 200) }}
                            </div>
                            <div class="mt-1 text-[11px] font-semibold text-[var(--muted-foreground)]">
                                Base Labor: ৳{{ number_format($shop->base_labor_fee ?? 400) }}
                            </div>
                        </div>
                    </div>

                    <!-- Description / Address Quote -->
                    <p class="mt-3 text-xs text-[var(--muted-foreground)] line-clamp-2 leading-relaxed">
                        "{{ $shop->description ?? $shop->address }}"
                    </p>

                    <!-- Specialties Tags -->
                    <div class="mt-3 flex flex-wrap gap-1.5 text-[11px]">
                        @foreach(array_slice($shop->specialties ?? [], 0, 4) as $spec)
                            <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] text-[var(--accent-dark)] font-medium">
                                {{ $spec }}
                            </span>
                        @endforeach
                    </div>
                </div>

                <!-- Bottom Badges & Action Buttons -->
                <div class="mt-4 pt-3 border-t border-[var(--border)] flex flex-wrap items-center justify-between gap-2">
                    <div class="flex flex-wrap gap-1.5 text-[11px] text-[var(--accent-dark)]">
                        @if($shop->has_voucher)
                            <span class="rounded-full bg-[var(--mint-50)] px-2.5 py-0.5 text-[10px] font-bold">
                                <i class="fa-solid fa-ticket text-amber-600 mr-1"></i> Voucher
                            </span>
                        @endif
                        @if($shop->free_delivery)
                            <span class="rounded-full bg-[var(--mint-50)] px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">
                                <i class="fa-solid fa-truck text-emerald-600 mr-1"></i> Free delivery
                            </span>
                        @endif
                        @if($shop->has_deals)
                            <span class="rounded-full bg-[var(--mint-50)] px-2.5 py-0.5 text-[10px] font-bold text-rose-800">
                                <i class="fa-solid fa-fire text-rose-600 mr-1"></i> Deal
                            </span>
                        @endif
                        <span class="rounded-full bg-[var(--mint-50)] px-2 py-0.5 text-[10px] font-bold">
                            {{ str_repeat('৳', $shop->price_level ?? 2) }}
                        </span>
                    </div>

                    <div class="flex items-center gap-2">
                        <a href="/requests/create?shop_id={{ $shop->id }}" class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1.5 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-300)] transition">
                            Book Repair
                        </a>
                        <a href="/shops/{{ $shop->slug }}" class="rounded-full bg-[var(--accent-dark)] px-4 py-1.5 text-xs font-bold text-white hover:opacity-90 shadow-sm transition">
                            View Profile &rarr;
                        </a>
                    </div>
                </div>

            </div>
        @empty
            <div class="col-span-2 py-16 text-center meramot-card p-8">
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

</div>
@endsection

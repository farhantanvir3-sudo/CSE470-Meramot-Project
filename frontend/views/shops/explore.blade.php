@extends('layouts.app')

@section('title', 'Explore Verified Repair Shops Across Bangladesh | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-3 py-3 sm:px-4 sm:py-6 md:px-6">

    <!-- Breadcrumb & Page Header -->
    <div class="mb-3 sm:mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-1.5 sm:gap-2 text-[11px] sm:text-xs text-[var(--muted-foreground)] mb-1 truncate">
                <a href="/" class="hover:underline">Home</a>
                <span>/</span>
                <span class="text-[var(--foreground)] font-semibold" data-i18n="nav_repair_shops">Repair Shops</span>
                @if(request('city') && request('city') !== 'All')
                    <span>/</span>
                    <span class="text-[var(--accent-dark)] font-bold">{{ request('city') }}</span>
                @endif
                @if(request('area') && request('area') !== 'All')
                    <span>/</span>
                    <span class="text-[var(--accent-dark)] font-bold">{{ request('area') }}</span>
                @endif
                @if(request('q'))
                    <span>/</span>
                    <span class="italic">"{{ request('q') }}"</span>
                @endif
            </div>
            <h1 class="text-lg sm:text-2xl font-black text-[var(--foreground)] leading-tight">
                Explore Verified Repair Shops
            </h1>
            <p class="text-[11px] sm:text-xs text-[var(--muted-foreground)] mt-0.5 flex flex-wrap items-center gap-1.5 sm:gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R6 • Lead: Shanid Sajjatuz Islam [23201465]
                </span>
                <span>Browse vetted electronics and device repair specialists across Bangladesh.</span>
            </p>
        </div>

        <!-- Quick Action: Make Request Button -->
        <div class="flex items-center gap-3 shrink-0">
            <a href="/requests/create" class="inline-flex items-center justify-center gap-1.5 sm:gap-2 rounded-full bg-[var(--accent-dark)] px-3.5 py-2 sm:px-5 sm:py-2.5 text-[11px] sm:text-xs font-bold text-white shadow hover:opacity-90 transition whitespace-nowrap">
                <i class="fa-solid fa-plus"></i>
                <span data-i18n="post_repair_request">Post Device Repair Request</span>
            </a>
        </div>
    </div>

    <!-- Main Layout: 2 Columns on Desktop (Matching SidebarFilters.tsx & shops-results-client.tsx) -->
    <div class="grid grid-cols-1 lg:grid-cols-[240px_minmax(0,1fr)] gap-4 sm:gap-6 items-start">

        <!-- ── Left Column: Sticky Sidebar Filters (Exact match to SidebarFilters.tsx) ── -->
        <aside class="space-y-4 lg:sticky lg:top-24 hidden lg:block shrink-0">
            
            <!-- 1. Sort Shops Card -->
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                <h3 class="mb-4 text-[1.05rem] font-semibold text-[var(--foreground)]">
                    Sort shops
                </h3>
                <div class="space-y-3">
                    @php
                        $rawCurrSort = request('sort', 'relevance');
                        $currSortMap = [
                            'rating' => 'topRated',
                            'top_rated' => 'topRated',
                            'topRated' => 'topRated',
                            'lowest_price' => 'price',
                            'price' => 'price',
                            'nearest' => 'distance',
                            'distance' => 'distance',
                            'best_match' => 'relevance',
                            'relevance' => 'relevance',
                        ];
                        $currSort = $currSortMap[$rawCurrSort] ?? 'relevance';
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
                        <a href="/shops?{{ http_build_query($sParams) }}" data-sort-link="{{ $opt['value'] }}" class="flex w-full items-center gap-3 rounded-2xl px-2 py-2 text-left text-sm text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
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

            <!-- 2. Distance & OpenStreetMap Filter Card -->
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm" x-data="{
                dist: {{ request('maxDistanceKm', 12) }},
                applyDistanceFilter() {
                    const params = new URLSearchParams(window.location.search);
                    params.set('maxDistanceKm', this.dist);
                    params.set('page', '1');
                    if (!params.get('lat') || !params.get('lng')) {
                        const stored = window.MeramotLocationPicker ? window.MeramotLocationPicker.parseStoredLocation() : null;
                        if (stored && typeof stored.lat === 'number' && typeof stored.lng === 'number') {
                            params.set('lat', stored.lat.toFixed(5));
                            params.set('lng', stored.lng.toFixed(5));
                        }
                    }
                    const nextUrl = '/shops?' + params.toString();
                    if (window.MeramotSoftNav) {
                        window.MeramotSoftNav.visit(nextUrl);
                    } else {
                        window.location.href = nextUrl;
                    }
                }
            }">
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
                    @change="applyDistanceFilter()"
                    class="w-full accent-[var(--accent-dark)]"
                />
                <div class="mt-3 flex flex-col gap-2">
                    <button
                        type="button"
                        @click="$dispatch('meramot-open-location-modal')"
                        class="w-full inline-flex items-center justify-center gap-1.5 rounded-xl bg-[var(--accent-dark)] px-3 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm transition hover:opacity-90"
                    >
                        <i class="fa-solid fa-map-location-dot"></i>
                        <span data-i18n="pin_location_map">Pin Location on Map</span>
                    </button>
                    @php
                        $isDesktopNearMeActive = request()->filled('maxDistanceKm') || ($currSort === 'distance' && request()->filled('lat') && request()->filled('lng'));
                    @endphp
                    <button
                        type="button"
                        id="use-my-location-btn"
                        data-near-me-active="{{ $isDesktopNearMeActive ? 'true' : 'false' }}"
                        aria-pressed="{{ $isDesktopNearMeActive ? 'true' : 'false' }}"
                        class="w-full select-none inline-flex items-center justify-center gap-1.5 rounded-xl border px-3 py-2 text-xs font-bold transition {{ $isDesktopNearMeActive ? 'bg-[var(--accent-dark)] text-white border-[var(--accent-dark)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)] text-[var(--accent-dark)] hover:bg-[var(--mint-200)]' }}"
                    >
                        <i class="fa-solid fa-location-crosshairs"></i>
                        <span>{{ $isDesktopNearMeActive ? 'Near Me Active (Tap to Clear)' : 'Use My GPS / Pin' }}</span>
                    </button>
                </div>
                <p class="mt-2.5 text-xs text-[var(--muted-foreground)]">
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
                            ['label' => 'Vouchers', 'key' => 'voucher', 'alt' => 'has_voucher'],
                            ['label' => 'Free delivery', 'key' => 'freeDelivery', 'alt' => 'free_delivery'],
                            ['label' => 'Deals', 'key' => 'deals', 'alt' => 'has_deals'],
                        ];
                    @endphp
                    @foreach($offers as $off)
                        @php
                            $isCheck = request()->boolean($off['key']) || request()->boolean($off['alt']);
                            $offParams = array_merge(request()->query(), [
                                $off['key'] => $isCheck ? null : 'true',
                                $off['alt'] => null,
                                'page' => 1,
                            ]);
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
        <main class="min-w-0 max-w-full space-y-3 sm:space-y-4">
            
            <!-- City / Area Selector Pills & Quick Filters Bar -->
            <div x-data class="rounded-2xl sm:rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-2.5 sm:p-4 shadow-sm space-y-2 sm:space-y-2.5 min-w-0 max-w-full">
                <!-- Row 1: Nationwide Bangladesh City / District Slider -->
                <div class="flex items-center gap-1.5 text-xs min-w-0">
                    <span class="font-bold text-[var(--foreground)] text-[10px] sm:text-[11px] uppercase tracking-wider shrink-0">
                        <i class="fa-solid fa-map text-[var(--accent-dark)] mr-1"></i>District:
                    </span>
                    @php
                        $activeCity = request('city', 'All');
                        $cityList = $cities ?? ['All', 'Dhaka', 'Chattogram', 'Gazipur', 'Sylhet', 'Mymensingh', 'Narayanganj', 'Bogura', 'Rajshahi', 'Khulna', 'Cumilla', 'Rangpur', 'Barishal'];
                    @endphp
                    <div
                        class="relative flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5"
                        x-init="const el = $el.querySelector('[data-active-pill=true]'); if (el && (el.offsetLeft + el.offsetWidth > $el.clientWidth)) { $el.scrollLeft = Math.max(0, el.offsetLeft - ($el.clientWidth - el.offsetWidth) / 2); }"
                    >
                        @foreach($cityList as $cityItem)
                            @php
                                $isCityActive = ($activeCity === $cityItem || ($cityItem === 'All' && empty(request('city'))));
                                $cityParams = array_merge(request()->query(), [
                                    'city' => $cityItem === 'All' ? null : $cityItem,
                                    'area' => null,
                                    'lat' => null,
                                    'lng' => null,
                                    'page' => 1,
                                ]);
                            @endphp
                            <a href="/shops?{{ http_build_query(array_filter($cityParams)) }}"
                               @if($isCityActive) data-active-pill="true" @endif
                               class="shrink-0 whitespace-nowrap select-none px-3 sm:px-3.5 py-1 sm:py-1.5 rounded-full text-[11px] sm:text-xs font-semibold transition {{ $isCityActive ? 'bg-[var(--accent-dark)] text-white font-bold shadow-sm' : 'bg-[var(--mint-50)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]' }}">
                                {{ $cityItem }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Row 2: Area / Hub Slider -->
                <div class="border-t border-[var(--border)]/60 pt-2 flex items-center gap-1.5 text-[11px] min-w-0">
                    <span class="font-bold text-[var(--foreground)] text-[10px] sm:text-[11px] uppercase tracking-wider shrink-0">
                        <i class="fa-solid fa-location-dot text-[var(--accent-dark)] mr-1"></i>Area:
                    </span>
                    @php
                        $activeArea = request('area', 'All');
                    @endphp
                    <div
                        class="relative flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5"
                        x-init="const el = $el.querySelector('[data-active-pill=true]'); if (el && (el.offsetLeft + el.offsetWidth > $el.clientWidth)) { $el.scrollLeft = Math.max(0, el.offsetLeft - ($el.clientWidth - el.offsetWidth) / 2); }"
                    >
                        @foreach($areas as $area)
                            @php
                                $isAreaActive = ($activeArea === $area || ($area === 'All' && empty(request('area'))));
                                $areaParams = array_merge(request()->query(), [
                                    'area' => $area === 'All' ? null : $area,
                                    'lat' => null,
                                    'lng' => null,
                                    'page' => 1,
                                ]);
                            @endphp
                            <a href="/shops?{{ http_build_query(array_filter($areaParams)) }}"
                               @if($isAreaActive) data-active-pill="true" @endif
                               class="shrink-0 whitespace-nowrap select-none px-3 sm:px-3.5 py-1 sm:py-1.5 rounded-full text-[11px] sm:text-xs font-semibold transition {{ $isAreaActive ? 'bg-[var(--accent-dark)] text-white font-bold shadow-sm' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]' }}">
                                {{ $area }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Row 3: Sort Tabs & Quick Filter Sliders -->
                <div class="border-t border-[var(--border)]/60 pt-2 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-2 xl:gap-3 text-xs min-w-0">
                    <!-- Quick Sort Slider -->
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="font-bold text-[var(--foreground)] text-[10px] sm:text-[11px] uppercase tracking-wider shrink-0" data-i18n="sort_label">Sort:</span>
                        @php
                            $sortTabs = [
                                ['label' => 'Top Rated', 'value' => 'topRated', 'icon' => 'fa-star'],
                                ['label' => 'Lowest Price', 'value' => 'price', 'icon' => 'fa-tag'],
                                ['label' => 'Nearest Distance', 'value' => 'distance', 'icon' => 'fa-compass'],
                                ['label' => 'Best Match', 'value' => 'relevance', 'icon' => 'fa-bullseye'],
                            ];
                        @endphp
                        <div class="relative flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                            @foreach($sortTabs as $st)
                                @php
                                    $sortParams = array_merge(request()->query(), ['sort' => $st['value'], 'page' => 1]);
                                @endphp
                                <a href="/shops?{{ http_build_query($sortParams) }}"
                                   data-sort-link="{{ $st['value'] }}"
                                   class="shrink-0 whitespace-nowrap select-none px-2.5 sm:px-3 py-1 rounded-xl text-[11px] sm:text-xs transition flex items-center gap-1.5 {{ $currSort === $st['value'] ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-50)]' }}">
                                    <i class="fa-solid {{ $st['icon'] }} text-[10px]"></i>
                                    <span>{{ $st['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <!-- Quick Promo, Multi-Branch & Map Slider -->
                    <div class="flex items-center gap-1.5 min-w-0 border-t border-[var(--border)]/60 pt-2 xl:border-t-0 xl:pt-0">
                        <span class="xl:hidden font-bold text-[var(--foreground)] text-[10px] uppercase tracking-wider shrink-0">
                            <i class="fa-solid fa-sliders text-[var(--accent-dark)] mr-1"></i>Filter:
                        </span>
                        @php
                            $isNearMeActive = request()->filled('maxDistanceKm') || ($currSort === 'distance' && request()->filled('lat') && request()->filled('lng'));

                            $hasBranched = request()->boolean('branched');
                            $branchedParams = array_merge(request()->query(), ['branched' => $hasBranched ? null : 'true', 'page' => 1]);

                            $hasVoucher = request()->boolean('voucher') || request()->boolean('has_voucher');
                            $voucherParams = array_merge(request()->query(), ['voucher' => $hasVoucher ? null : 'true', 'has_voucher' => null, 'page' => 1]);

                            $hasFreeDel = request()->boolean('freeDelivery') || request()->boolean('free_delivery');
                            $delParams = array_merge(request()->query(), ['freeDelivery' => $hasFreeDel ? null : 'true', 'free_delivery' => null, 'page' => 1]);

                            $hasDeals = request()->boolean('deals') || request()->boolean('has_deals');
                            $dealsParams = array_merge(request()->query(), ['deals' => $hasDeals ? null : 'true', 'has_deals' => null, 'page' => 1]);
                        @endphp
                        <div class="relative flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                            <button
                                type="button"
                                @click="$dispatch('meramot-open-location-modal')"
                                class="lg:hidden shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-xl border border-[var(--accent-dark)]/40 bg-[var(--mint-50)] text-[var(--accent-dark)] font-bold transition flex items-center gap-1.5 text-[11px] hover:bg-[var(--mint-200)]"
                            >
                                <i class="fa-solid fa-map-location-dot text-[10px]"></i>
                                <span data-i18n="pin_on_map">Pin on Map</span>
                            </button>

                            <button
                                type="button"
                                data-detect-location
                                data-near-me-active="{{ $isNearMeActive ? 'true' : 'false' }}"
                                aria-pressed="{{ $isNearMeActive ? 'true' : 'false' }}"
                                class="lg:hidden shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-xl border transition flex items-center gap-1.5 text-[11px] font-bold {{ $isNearMeActive ? 'bg-[var(--accent-dark)] text-white shadow-sm border-[var(--accent-dark)]' : 'bg-[var(--mint-100)] text-[var(--accent-dark)] border-[var(--border)] hover:bg-[var(--mint-200)]' }}"
                            >
                                <i class="fa-solid fa-location-crosshairs text-[10px]"></i>
                                <span data-i18n="near_me">Near Me</span>
                            </button>

                            <a href="/shops?{{ http_build_query(array_filter($branchedParams)) }}"
                               class="shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-xl border border-[var(--border)] transition flex items-center gap-1.5 text-[11px] sm:text-xs {{ $hasBranched ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                                <i class="fa-solid fa-code-branch text-[10px] {{ $hasBranched ? 'text-emerald-200' : 'text-[var(--accent-dark)]' }}"></i>
                                <span data-i18n="multi_branch">Multi-Branch</span>
                            </a>

                            <a href="/shops?{{ http_build_query(array_filter($voucherParams)) }}"
                               class="shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-xl border border-[var(--border)] transition flex items-center gap-1.5 text-[11px] sm:text-xs {{ $hasVoucher ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                                <i class="fa-solid fa-ticket text-[10px] text-amber-600"></i>
                                <span data-i18n="vouchers">Vouchers</span>
                            </a>

                            <a href="/shops?{{ http_build_query(array_filter($delParams)) }}"
                               class="shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-xl border border-[var(--border)] transition flex items-center gap-1.5 text-[11px] sm:text-xs {{ $hasFreeDel ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                                <i class="fa-solid fa-truck-fast text-[10px] text-emerald-700"></i>
                                <span data-i18n="free_delivery_filter">Free Delivery</span>
                            </a>

                            <a href="/shops?{{ http_build_query(array_filter($dealsParams)) }}"
                               class="shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-xl border border-[var(--border)] transition flex items-center gap-1.5 text-[11px] sm:text-xs {{ $hasDeals ? 'bg-[var(--accent-dark)] text-white font-bold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                                <i class="fa-solid fa-fire text-[10px] text-rose-600"></i>
                                <span data-i18n="deals_filter">Deals</span>
                            </a>

                            @if(request()->anyFilled(['q', 'city', 'area', 'branched', 'voucher', 'freeDelivery', 'deals', 'category', 'maxDistanceKm', 'lat']))
                                <a href="/shops" class="shrink-0 whitespace-nowrap select-none text-[11px] text-rose-700 font-bold hover:underline px-1.5">
                                    Reset
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Active Results Counter & Filter Summary -->
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-[var(--muted-foreground)] px-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span>Showing <strong class="text-[var(--foreground)]">{{ $shops->total() }}</strong> verified repair shops</span>
                    @if(request('category'))
                        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                            {{ ucwords(strtolower(str_replace('_', ' ', request('category')))) }}
                        </span>
                    @endif
                    @if(request('city') && request('city') !== 'All')
                        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                            {{ request('city') }}
                        </span>
                    @endif
                    @if(request('area') && request('area') !== 'All')
                        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                            {{ request('area') }}
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    @if(request('q'))
                        <span>Search: <strong class="text-[var(--foreground)]">"{{ request('q') }}"</strong></span>
                    @endif
                    @if(request()->anyFilled(['q', 'city', 'area', 'branched', 'voucher', 'freeDelivery', 'deals', 'category', 'maxDistanceKm', 'lat']))
                        <a href="/shops" class="select-none font-bold text-rose-700 hover:underline" data-i18n="clear_all_filters">Clear all filters</a>
                    @endif
                </div>
            </div>

            <!-- ── Shop Result Cards (Exact match to ShopResultCard from commit 8e44218) ── -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 min-w-0">
                @forelse($shops as $shop)
                    <a
                        href="/shops/{{ $shop->slug }}"
                        data-shop-lat="{{ $shop->latitude }}"
                        data-shop-lng="{{ $shop->longitude }}"
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
                                        <div class="flex flex-wrap items-center gap-1.5">
                                            <h3 class="break-words text-[0.95rem] font-bold leading-tight text-[var(--foreground)] md:text-[1.1rem] group-hover:text-[var(--accent-dark)] transition">
                                                {{ $shop->name }}
                                            </h3>
                                            @if($shop->display_branch_tag)
                                                <span class="inline-flex items-center gap-1 rounded-full bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)] shadow-sm">
                                                    <i class="fa-solid fa-code-branch text-[8px]"></i>
                                                    <span>{{ $shop->display_branch_tag }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11px] text-[var(--muted-foreground)] md:text-xs">
                                            @if((int) ($shop->review_count ?? 0) > 0)
                                                <span>⭐ {{ number_format($shop->rating, 1) }}</span>
                                                <span class="opacity-60">·</span>
                                                <span>{{ $shop->review_count }} {{ \Illuminate\Support\Str::plural('review', $shop->review_count) }}</span>
                                            @else
                                                <span class="font-semibold text-[var(--accent-dark)]" data-i18n="new_no_reviews">New · No reviews yet</span>
                                            @endif
                                            <span class="opacity-60">·</span>
                                            <span><i class="fa-solid fa-location-dot text-[10px] text-[var(--accent-dark)]"></i> {{ $shop->area }}{{ ($shop->city && !str_contains(strtolower((string)$shop->area), strtolower($shop->city))) ? ', ' . $shop->city : '' }}</span>
                                            <span class="distance-display {{ isset($shop->distance_km) ? '' : 'hidden' }} rounded-full bg-[var(--mint-100)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                                                {{ isset($shop->distance_km) ? number_format((float) $shop->distance_km, 1) . 'km' : '' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <div class="text-base font-black leading-tight text-[var(--accent-dark)] md:text-lg">
                                            ৳{{ number_format($shop->inspection_fee ?? 200) }}
                                        </div>
                                        <div class="eta-display text-[10px] font-semibold text-[var(--muted-foreground)]">
                                            {{ \App\Services\GeolocationService::formatEta((int) ($shop->eta_minutes ?: 30)) }}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Price + Labor + ETA compact row -->
                        <div class="mt-2.5 grid grid-cols-3 items-center rounded-xl bg-[var(--mint-50)] px-2.5 py-1.5 md:mt-3 text-center min-w-0">
                            <div>
                                <span class="block truncate text-[9px] font-semibold uppercase text-[var(--muted-foreground)]" data-i18n="inspection_label">Inspection</span>
                                <div class="text-xs font-black text-[var(--accent-dark)] sm:text-sm">৳{{ number_format($shop->inspection_fee ?? 200) }}</div>
                            </div>
                            <div class="border-x border-[var(--border)]/50 px-1">
                                <span class="block truncate text-[9px] font-semibold uppercase text-[var(--muted-foreground)]" data-i18n="labor_label">Labor</span>
                                <div class="text-xs font-black text-[var(--accent-dark)] sm:text-sm">৳{{ number_format($shop->base_labor_fee ?? 400) }}</div>
                            </div>
                            <div>
                                <span class="block truncate text-[9px] font-semibold uppercase text-[var(--muted-foreground)]">ETA</span>
                                <div class="eta-display text-xs font-bold text-[var(--foreground)] sm:text-sm">
                                    {{ \App\Services\GeolocationService::formatEta((int) ($shop->eta_minutes ?: 30)) }}
                                </div>
                            </div>
                        </div>

                        <!-- Public Vendor Storefront Address & Summary -->
                        <p class="mt-2 line-clamp-1 text-[11px] text-[var(--muted-foreground)] md:text-xs">
                            <i class="fa-solid fa-store text-[9px] opacity-75 mr-1"></i>{{ $shop->public_address }}
                        </p>

                        <!-- Badges -->
                        <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[10px] font-medium text-[var(--accent-dark)] md:text-[11px]">
                            @if($shop->has_voucher)
                                <span class="rounded-full bg-[var(--mint-100)] px-2 py-0.5" data-i18n="voucher">Voucher</span>
                            @endif
                            @if($shop->free_delivery)
                                <span class="rounded-full bg-[var(--mint-100)] px-2 py-0.5 text-emerald-900 font-semibold" data-i18n="free_delivery">Free delivery</span>
                            @endif
                            @if($shop->has_deals)
                                <span class="rounded-full bg-[var(--mint-100)] px-2 py-0.5 text-rose-900 font-semibold" data-i18n="deal">Deal</span>
                            @endif
                            <span class="rounded-full bg-[var(--mint-100)] px-2 py-0.5 font-bold">
                                {{ str_repeat('৳', $shop->price_level ?? 2) }}
                            </span>
                        </div>
                    </a>
                @empty
                    <div class="col-span-2 py-16 text-center rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-8">
                        <div class="text-4xl mb-3">🔍</div>
                        <h3 class="text-base font-bold text-[var(--foreground)]" data-i18n="no_workshops_found">No matching workshops found</h3>
                        <p class="text-xs text-[var(--muted-foreground)] mt-1">No repair centers matched your current filters. Adjust your criteria or reset all filters to view available shops.</p>
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

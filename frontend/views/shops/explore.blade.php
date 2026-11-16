@extends('layouts.app')

@section('title', 'Explore Verified Repair Shops Across Bangladesh | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-3 py-3 sm:px-4 sm:py-6 md:px-6">

    <!-- Breadcrumb & Page Header -->
    <div class="mb-3 sm:mb-6 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2.5 sm:gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-1.5 sm:gap-2 text-[11px] sm:text-xs text-[var(--muted-foreground)] mb-1 truncate">
                <a href="/" class="hover:underline" data-i18n="home_nav">Home</a>
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
            <h1 class="text-xl sm:text-2xl font-bold text-[var(--foreground)] leading-tight" data-i18n="explore_verified_shops">
                Explore Verified Repair Shops
            </h1>
            <p class="dev-only-badge text-[11px] sm:text-xs text-[var(--muted-foreground)] mt-0.5 flex flex-wrap items-center gap-1.5 sm:gap-2">
                <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R6 • Lead: Shanid Sajjatuz Islam [23201465]
                </span>
            </p>
        </div>

        <!-- Quick Action: Make Request Button -->
        <div class="flex items-center gap-3 shrink-0">
            <a href="/requests/create" class="inline-flex items-center justify-center gap-1.5 sm:gap-2 rounded-md bg-[var(--accent-dark)] px-3.5 py-2 sm:px-4 sm:py-2 text-xs font-semibold text-white shadow-sm hover:opacity-90 transition whitespace-nowrap">
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
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-[var(--foreground)]" data-i18n="sort_shops">
                    Sort shops
                </h3>
                <div class="space-y-1.5">
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
                        <a href="/shops?{{ http_build_query($sParams) }}" data-sort-link="{{ $opt['value'] }}" class="flex w-full items-center gap-2.5 rounded-md px-2 py-1.5 text-left text-xs text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                            <span class="h-3.5 w-3.5 rounded-full border flex items-center justify-center shrink-0 {{ $isActive ? 'border-[var(--accent-dark)] bg-[var(--accent-dark)]' : 'border-[var(--border)] bg-[var(--card)]' }}">
                                @if($isActive)
                                    <span class="h-1.5 w-1.5 rounded-full bg-white"></span>
                                @endif
                            </span>
                            <span class="{{ $isActive ? 'font-semibold text-[var(--accent-dark)]' : '' }}">{{ $opt['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <!-- 2. Distance & OpenStreetMap Filter Card -->
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm"
                 x-data="meramotDistanceFilter(@js((int) request('maxDistanceKm', request('max_distance_km', 12))), @js(request()->filled('maxDistanceKm') || request()->filled('max_distance_km')))">
                <div class="mb-2.5 flex items-center justify-between gap-2">
                    <h3 class="text-sm font-semibold text-[var(--foreground)]" data-i18n="distance_label">
                        Distance
                    </h3>
                    <div class="flex items-center gap-1">
                        <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 text-[11px] font-semibold text-[var(--accent-dark)] tabular-nums">
                            Up to <span x-text="dist">{{ (int) request('maxDistanceKm', request('max_distance_km', 12)) }}</span> km
                        </span>
                        <button
                            type="button"
                            x-show="active"
                            x-cloak
                            @click="clearDistanceFilter()"
                            title="Clear distance filter"
                            aria-label="Clear distance filter"
                            class="inline-flex h-5 w-5 items-center justify-center rounded border border-[var(--border)] bg-[var(--mint-50)] text-[10px] font-semibold text-[var(--muted-foreground)] hover:bg-[var(--mint-200)] hover:text-[var(--foreground)] transition cursor-pointer"
                        >✕</button>
                    </div>
                </div>
                <input
                    type="range"
                    min="1"
                    max="25"
                    step="1"
                    x-model.number="dist"
                    @input="dragging = true"
                    @change="applyDistanceFilter()"
                    :style="'--range-progress: ' + progressPct"
                    aria-label="Maximum distance in kilometers"
                    class="meramot-range-slider w-full cursor-pointer"
                />
                <div class="mt-3 flex flex-col gap-1.5">
                    <button
                        type="button"
                        @click="$dispatch('meramot-open-location-modal')"
                        class="w-full inline-flex items-center justify-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-3 py-2 text-xs font-semibold text-[var(--accent-foreground)] shadow-sm transition hover:opacity-90"
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
                        class="group w-full select-none inline-flex items-center {{ $isDesktopNearMeActive ? 'justify-between border-[var(--accent-dark)]/45 bg-[var(--mint-100)] font-semibold text-[var(--accent-dark)] shadow-2xs hover:border-[var(--accent-dark)] hover:bg-[var(--mint-200)]/70' : 'justify-center border-[var(--border)] bg-[var(--mint-100)]/80 font-medium text-[var(--foreground)] hover:border-[var(--accent-dark)]/50 hover:bg-[var(--mint-100)]' }} gap-2 rounded-md border px-3 py-2 text-xs transition"
                    >
                        @if($isDesktopNearMeActive)
                            <span class="inline-flex items-center gap-2 min-w-0 truncate">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-600 dark:bg-emerald-400 shrink-0"></span>
                                <span class="truncate" data-i18n="nearby_active">Nearby Active</span>
                            </span>
                            <span class="inline-flex items-center gap-1 rounded bg-[var(--mint-200)]/75 px-1.5 py-0.5 text-[10px] font-medium text-[var(--foreground)]/80 group-hover:bg-[var(--mint-200)] group-hover:text-[var(--foreground)] transition shrink-0">
                                <span data-i18n="clear_short">Clear</span>
                                <i class="fa-solid fa-xmark text-[9px]"></i>
                            </span>
                        @else
                            <i class="fa-solid fa-location-crosshairs text-[11px] text-[var(--accent-dark)] opacity-85 group-hover:opacity-100 transition"></i>
                            <span class="truncate" data-i18n="use_current_location">Use Current Location</span>
                        @endif
                    </button>
                    <p class="px-0.5 text-[10.5px] leading-snug text-[var(--muted-foreground)]" data-i18n="{{ $isDesktopNearMeActive ? 'near_me_sub_active' : 'near_me_sub_idle' }}">
                        {{ $isDesktopNearMeActive ? 'Showing workshops closest to your location.' : 'Sorts results by proximity to your location.' }}
                    </p>
                </div>
            </div>

            <!-- 3. Offers & Savings Card -->
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm">
                <h3 class="mb-3 text-sm font-semibold text-[var(--foreground)]" data-i18n="offers_savings">
                    Offers & savings
                </h3>
                <div class="space-y-1.5">
                    @php
                        $offers = [
                            ['label' => 'Vouchers', 'key' => 'voucher', 'alt' => 'has_voucher'],
                            ['label' => 'Free courier', 'key' => 'freeDelivery', 'alt' => 'free_delivery'],
                            ['label' => 'Deals', 'key' => 'deals', 'alt' => 'has_deals'],
                            ['label' => 'Sale', 'key' => 'sale', 'alt' => 'has_sale'],
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
                        <a href="/shops?{{ http_build_query(array_filter($offParams)) }}"
                           data-filter-offer="{{ $off['key'] }}"
                           class="flex cursor-pointer items-center gap-2.5 rounded-md px-2 py-1.5 text-xs text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                            <input
                                type="checkbox"
                                {{ $isCheck ? 'checked' : '' }}
                                class="h-3.5 w-3.5 rounded border-[var(--border)] accent-[var(--accent-dark)] pointer-events-none"
                            />
                            <span class="{{ $isCheck ? 'font-semibold text-[var(--accent-dark)]' : '' }}">{{ $off['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

        </aside>

        <!-- ── Right Column: Area Pills, Sort Tabs & Crisp Storefront Cards ── -->
        <main class="min-w-0 max-w-full space-y-3 sm:space-y-4">
            
            <!-- City / Area Selector Chips & Quick Filters Bar -->
            <div x-data class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 sm:p-4 shadow-sm space-y-2 min-w-0 max-w-full">
                <!-- Row 1: Nationwide Bangladesh City / District Slider -->
                <div class="flex items-center gap-1.5 text-xs min-w-0">
                    <span class="font-semibold text-[var(--foreground)] text-[10px] sm:text-[11px] uppercase tracking-wider shrink-0">
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
                               class="shrink-0 whitespace-nowrap select-none px-2.5 sm:px-3 py-1 rounded-md text-[11px] sm:text-xs font-medium transition {{ $isCityActive ? 'bg-[var(--accent-dark)] text-white font-semibold shadow-sm' : 'bg-[var(--mint-50)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]' }}">
                                {{ $cityItem }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Row 2: Area / Hub Slider -->
                <div class="border-t border-[var(--border)]/60 pt-2 flex items-center gap-1.5 text-[11px] min-w-0">
                    <span class="font-semibold text-[var(--foreground)] text-[10px] sm:text-[11px] uppercase tracking-wider shrink-0">
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
                               class="shrink-0 whitespace-nowrap select-none px-2.5 sm:px-3 py-1 rounded-md text-[11px] sm:text-xs font-medium transition {{ $isAreaActive ? 'bg-[var(--accent-dark)] text-white font-semibold shadow-sm' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]' }}">
                                {{ $area }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- Row 3: Sort Tabs & Quick Filter Sliders -->
                <div class="border-t border-[var(--border)]/60 pt-2 flex flex-col xl:flex-row xl:items-center xl:justify-between gap-2 xl:gap-3 text-xs min-w-0">
                    <!-- Quick Sort Slider -->
                    <div class="flex items-center gap-1.5 min-w-0">
                        <span class="font-semibold text-[var(--foreground)] text-[10px] sm:text-[11px] uppercase tracking-wider shrink-0" data-i18n="sort_label">Sort:</span>
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
                                   class="shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-md text-[11px] sm:text-xs transition flex items-center gap-1.5 {{ $currSort === $st['value'] ? 'bg-[var(--accent-dark)] text-white font-semibold' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-50)]' }}">
                                    <i class="fa-solid {{ $st['icon'] }} text-[10px]"></i>
                                    <span>{{ $st['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>

                    <!-- Quick Promo, Multi-Branch & Map Slider -->
                    <div class="flex items-center gap-1.5 min-w-0 border-t border-[var(--border)]/60 pt-2 xl:border-t-0 xl:pt-0">
                        <span class="xl:hidden font-semibold text-[var(--foreground)] text-[10px] uppercase tracking-wider shrink-0">
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

                            $hasSale = request()->boolean('sale') || request()->boolean('has_sale');
                            $saleParams = array_merge(request()->query(), ['sale' => $hasSale ? null : 'true', 'has_sale' => null, 'page' => 1]);
                        @endphp
                        <div class="relative flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                            <button
                                type="button"
                                @click="$dispatch('meramot-open-location-modal')"
                                class="lg:hidden shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-md border border-[var(--accent-dark)]/40 bg-[var(--mint-50)] text-[var(--accent-dark)] font-semibold transition flex items-center gap-1.5 text-[11px] hover:bg-[var(--mint-200)]"
                            >
                                <i class="fa-solid fa-map-location-dot text-[10px]"></i>
                                <span data-i18n="pin_on_map">Pin on Map</span>
                            </button>

                            <button
                                type="button"
                                data-detect-location
                                data-near-me-active="{{ $isNearMeActive ? 'true' : 'false' }}"
                                aria-pressed="{{ $isNearMeActive ? 'true' : 'false' }}"
                                class="lg:hidden shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-md border transition flex items-center gap-1.5 text-[11px] font-semibold {{ $isNearMeActive ? 'bg-[var(--accent-dark)] text-white shadow-sm border-[var(--accent-dark)]' : 'bg-[var(--mint-100)] text-[var(--accent-dark)] border-[var(--border)] hover:bg-[var(--mint-200)]' }}"
                            >
                                <i class="fa-solid fa-location-crosshairs text-[10px]"></i>
                                <span data-i18n="near_me">Near Me</span>
                            </button>

                            <a href="/shops?{{ http_build_query(array_filter($branchedParams)) }}"
                               class="shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-md border border-[var(--border)] transition flex items-center gap-1.5 text-[11px] sm:text-xs {{ $hasBranched ? 'bg-[var(--accent-dark)] text-white font-semibold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                                <i class="fa-solid fa-code-branch text-[10px] {{ $hasBranched ? 'text-emerald-200' : 'text-[var(--accent-dark)]' }}"></i>
                                <span data-i18n="multi_branch">Multi-Branch</span>
                            </a>

                            <a href="/shops?{{ http_build_query(array_filter($voucherParams)) }}"
                               class="shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-md border border-[var(--border)] transition flex items-center gap-1.5 text-[11px] sm:text-xs {{ $hasVoucher ? 'bg-[var(--accent-dark)] text-white font-semibold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                                <i class="fa-solid fa-ticket text-[10px] text-amber-600"></i>
                                <span data-i18n="vouchers">Vouchers</span>
                            </a>

                            <a href="/shops?{{ http_build_query(array_filter($delParams)) }}"
                               class="shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-md border border-[var(--border)] transition flex items-center gap-1.5 text-[11px] sm:text-xs {{ $hasFreeDel ? 'bg-[var(--accent-dark)] text-white font-semibold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                                <i class="fa-solid fa-truck-fast text-[10px] text-emerald-700"></i>
                                <span data-i18n="free_delivery_filter">Free Courier</span>
                            </a>

                            <a href="/shops?{{ http_build_query(array_filter($dealsParams)) }}"
                               data-filter-chip="deals"
                               class="shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-md border border-[var(--border)] transition flex items-center gap-1.5 text-[11px] sm:text-xs {{ $hasDeals ? 'bg-[var(--accent-dark)] text-white font-semibold' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                                <i class="fa-solid fa-fire text-[10px] {{ $hasDeals ? 'text-amber-200' : 'text-amber-600' }}"></i>
                                <span data-i18n="deals_filter">Deals</span>
                            </a>

                            <a href="/shops?{{ http_build_query(array_filter($saleParams)) }}"
                               data-filter-chip="sale"
                               class="shrink-0 whitespace-nowrap select-none px-2.5 py-1 rounded-md border border-[var(--border)] transition flex items-center gap-1.5 text-[11px] sm:text-xs {{ $hasSale ? 'bg-rose-700 text-white font-semibold shadow-xs border-rose-800' : 'bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                                <i class="fa-solid fa-bolt text-[10px] {{ $hasSale ? 'text-rose-200' : 'text-rose-600' }}"></i>
                                <span data-i18n="sale_filter">Sale</span>
                            </a>

                            @if(request()->anyFilled(['q', 'city', 'area', 'branched', 'voucher', 'freeDelivery', 'deals', 'sale', 'has_sale', 'category', 'maxDistanceKm', 'lat']))
                                <a href="/shops" class="shrink-0 whitespace-nowrap select-none text-[11px] text-rose-700 font-semibold hover:underline px-1.5" data-i18n="reset_short">
                                    Reset
                                </a>
                            @endif
                        </div>
                    </div>
                </div>

                <!-- Row 4 (Mobile/Tablet < lg): Compact Inline Distance Radius Slider -->
                <div x-data="meramotDistanceFilter(@js((int) request('maxDistanceKm', request('max_distance_km', 12))), @js(request()->filled('maxDistanceKm') || request()->filled('max_distance_km')))"
                     data-mobile-distance-row
                     class="lg:hidden border-t border-[var(--border)]/60 pt-2 flex items-center gap-2 text-xs min-w-0">
                    <span class="font-semibold text-[var(--foreground)] text-[10px] sm:text-[11px] uppercase tracking-wider shrink-0 flex items-center">
                        <i class="fa-solid fa-route text-[var(--accent-dark)] mr-1 text-[10px]"></i>
                        <span data-i18n="distance_row_label">Distance:</span>
                    </span>

                    <div class="flex-1 min-w-0 flex items-center gap-1.5 py-0.5">
                        <span class="hidden sm:inline text-[10px] text-[var(--muted-foreground)] tabular-nums shrink-0">1km</span>
                        <input
                            type="range"
                            min="1"
                            max="25"
                            step="1"
                            x-model.number="dist"
                            @input="dragging = true"
                            @change="applyDistanceFilter()"
                            :style="'--range-progress: ' + progressPct"
                            aria-label="Maximum distance in kilometers"
                            data-mobile-distance-slider
                            class="meramot-range-slider w-full min-w-0 cursor-pointer"
                        />
                        <span class="hidden sm:inline text-[10px] text-[var(--muted-foreground)] tabular-nums shrink-0">25km</span>
                    </div>

                    <div class="shrink-0 flex items-center gap-1">
                        <button
                            type="button"
                            @click="applyDistanceFilter()"
                            data-mobile-distance-badge
                            :class="(active || dragging)
                                ? 'bg-[var(--accent-dark)] text-white border-[var(--accent-dark)] font-semibold shadow-2xs'
                                : 'bg-[var(--mint-100)] text-[var(--foreground)] border-[var(--border)] font-medium hover:bg-[var(--mint-200)]'"
                            class="min-w-[54px] text-center select-none rounded-md border px-2 py-0.5 text-[11px] tabular-nums transition cursor-pointer"
                        >
                            <span x-show="!active && !dragging" data-i18n="any_distance">Any</span>
                            <span x-show="active || dragging" x-cloak>&le; <span x-text="dist">{{ (int) request('maxDistanceKm', request('max_distance_km', 12)) }}</span> km</span>
                        </button>
                        <button
                            type="button"
                            x-show="active"
                            x-cloak
                            @click="clearDistanceFilter()"
                            data-mobile-distance-clear
                            title="Clear distance filter"
                            aria-label="Clear distance filter"
                            class="inline-flex h-6 w-6 items-center justify-center rounded-md border border-[var(--border)] bg-[var(--mint-50)] text-[10px] font-semibold text-[var(--muted-foreground)] hover:bg-[var(--mint-200)] hover:text-[var(--foreground)] transition cursor-pointer"
                        >✕</button>
                    </div>
                </div>
            </div>

            <!-- Active Results Counter & Filter Summary -->
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-[var(--muted-foreground)] px-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="tabular-nums">Showing <strong class="text-[var(--foreground)]">{{ $shops->total() }}</strong> verified repair shops</span>
                    @if(request('category'))
                        <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-semibold text-[var(--accent-dark)]">
                            {{ ucwords(strtolower(str_replace('_', ' ', request('category')))) }}
                        </span>
                    @endif
                    @if(request('city') && request('city') !== 'All')
                        <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-semibold text-[var(--accent-dark)]">
                            {{ request('city') }}
                        </span>
                    @endif
                    @if(request('area') && request('area') !== 'All')
                        <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-semibold text-[var(--accent-dark)]">
                            {{ request('area') }}
                        </span>
                    @endif
                    @if(request()->filled('maxDistanceKm') || request()->filled('max_distance_km'))
                        <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-semibold text-[var(--accent-dark)] tabular-nums">
                            &le; {{ (int) request('maxDistanceKm', request('max_distance_km')) }} km
                        </span>
                    @endif
                </div>
                <div class="flex items-center gap-3">
                    @if(request('q'))
                        <span>Search: <strong class="text-[var(--foreground)]">"{{ request('q') }}"</strong></span>
                    @endif
                    @if(request()->anyFilled(['q', 'city', 'area', 'branched', 'voucher', 'freeDelivery', 'deals', 'category', 'maxDistanceKm', 'lat']))
                        <a href="/shops" class="select-none font-semibold text-rose-700 hover:underline" data-i18n="clear_all_filters">Clear all filters</a>
                    @endif
                </div>
            </div>

            <!-- ── Shop Result Cards ── -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5 min-w-0">
                @forelse($shops as $shop)
                    <a
                        href="/shops/{{ $shop->slug }}"
                        data-shop-lat="{{ $shop->latitude }}"
                        data-shop-lng="{{ $shop->longitude }}"
                        class="group flex flex-col justify-between rounded-lg border border-[var(--border)] bg-[var(--card)] p-3.5 shadow-sm transition hover:border-[var(--accent-dark)]/55 hover:shadow-md md:p-4 min-w-0"
                    >
                        <!-- Top row: logo + name/rating + price -->
                        <div class="flex items-start gap-3 min-w-0">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md bg-[var(--mint-100)] text-lg font-bold text-[var(--accent-dark)] border border-[var(--border)] md:h-14 md:w-14 md:text-xl overflow-hidden">
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
                                            <h3 class="break-words text-[0.95rem] font-semibold leading-tight text-[var(--foreground)] md:text-base group-hover:text-[var(--accent-dark)] transition">
                                                {{ $shop->name }}
                                            </h3>
                                            @if($shop->display_branch_tag)
                                                <span class="inline-flex items-center gap-1 rounded bg-[var(--accent-dark)] px-2 py-0.5 text-[10px] font-semibold text-[var(--accent-foreground)]">
                                                    <i class="fa-solid fa-code-branch text-[8px]"></i>
                                                    <span>{{ $shop->display_branch_tag }}</span>
                                                </span>
                                            @endif
                                        </div>

                                        <div class="mt-1 flex flex-wrap items-center gap-x-1.5 gap-y-0.5 text-[11px] text-[var(--muted-foreground)] md:text-xs tabular-nums">
                                            @if((int) ($shop->review_count ?? 0) > 0)
                                                <span class="inline-flex items-center gap-1 font-medium text-[var(--foreground)]"><i class="fa-solid fa-star text-amber-500 text-[10px]"></i> {{ number_format($shop->rating, 1) }}</span>
                                                <span class="opacity-60">·</span>
                                                <span>{{ $shop->review_count }} {{ \Illuminate\Support\Str::plural('review', $shop->review_count) }}</span>
                                            @else
                                                <span class="font-medium text-[var(--accent-dark)]" data-i18n="new_no_reviews">New · No reviews yet</span>
                                            @endif
                                            <span class="opacity-60">·</span>
                                            <span><i class="fa-solid fa-location-dot text-[10px] text-[var(--accent-dark)]"></i> {{ $shop->area }}{{ ($shop->city && !str_contains(strtolower((string)$shop->area), strtolower($shop->city))) ? ', ' . $shop->city : '' }}</span>
                                            <span class="distance-display {{ isset($shop->distance_km) ? '' : 'hidden' }} rounded bg-[var(--mint-100)] border border-[var(--border)] px-1.5 py-0.5 text-[10px] font-semibold text-[var(--accent-dark)]">
                                                {{ isset($shop->distance_km) ? number_format((float) $shop->distance_km, 1) . 'km' : '' }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="shrink-0 text-right tabular-nums">
                                        @if($shop->isPureSparePartsWholesaler())
                                            <div class="text-xs font-bold leading-tight text-[var(--accent-dark)]">
                                                Parts Store
                                            </div>
                                            <div class="text-[10px] font-medium text-[var(--muted-foreground)]">
                                                Wholesale &amp; Retail
                                            </div>
                                        @else
                                            <div class="text-base font-bold leading-tight text-[var(--accent-dark)] md:text-lg">
                                                ৳{{ number_format($shop->inspection_fee ?? 200) }}
                                            </div>
                                            <div class="eta-display text-[10px] font-medium text-[var(--muted-foreground)]">
                                                {{ \App\Services\GeolocationService::formatEta((int) ($shop->eta_minutes ?: 30)) }}
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Price + Labor + ETA compact row -->
                        @if($shop->isPureSparePartsWholesaler())
                            <div class="mt-2.5 flex items-center justify-between rounded-md bg-[var(--mint-50)] border border-[var(--border)]/50 px-3 py-2 md:mt-3 text-[11px] font-semibold text-[var(--accent-dark)] min-w-0">
                                <span><i class="fa-solid fa-boxes-stacked mr-1.5"></i>Genuine Spare Parts Wholesaler</span>
                                <span class="text-[10px] font-medium text-[var(--muted-foreground)]">Courier Dispatch</span>
                            </div>
                        @else
                            <div class="mt-2.5 grid grid-cols-3 items-center rounded-md bg-[var(--mint-50)] border border-[var(--border)]/50 px-2.5 py-1.5 md:mt-3 text-center min-w-0 tabular-nums">
                                <div>
                                    <span class="block truncate text-[9px] font-semibold uppercase text-[var(--muted-foreground)]" data-i18n="inspection_label">Inspection</span>
                                    <div class="text-xs font-bold text-[var(--accent-dark)] sm:text-sm">৳{{ number_format($shop->inspection_fee ?? 200) }}</div>
                                </div>
                                <div class="border-x border-[var(--border)]/50 px-1">
                                    <span class="block truncate text-[9px] font-semibold uppercase text-[var(--muted-foreground)]" data-i18n="labor_label">Labor</span>
                                    <div class="text-xs font-bold text-[var(--accent-dark)] sm:text-sm">৳{{ number_format($shop->base_labor_fee ?? 400) }}</div>
                                </div>
                                <div>
                                    <span class="block truncate text-[9px] font-semibold uppercase text-[var(--muted-foreground)]" data-i18n="eta_label">ETA</span>
                                    <div class="eta-display text-xs font-semibold text-[var(--foreground)] sm:text-sm">
                                        {{ \App\Services\GeolocationService::formatEta((int) ($shop->eta_minutes ?: 30)) }}
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Public Vendor Storefront Address & Summary -->
                        <p class="mt-2 line-clamp-1 text-[11px] text-[var(--muted-foreground)] md:text-xs">
                            <i class="fa-solid fa-store text-[9px] opacity-75 mr-1"></i>{{ $shop->public_address }}
                        </p>

                        <!-- Capability Badges -->
                        <div class="mt-2 flex flex-wrap items-center gap-1.5 text-[10px] font-medium text-[var(--accent-dark)] md:text-[11px]">
                            @if($shop->supportsCourierPickup())
                                <span class="rounded bg-[var(--mint-100)] border border-[var(--border)]/70 px-2 py-0.5 font-medium text-emerald-950" data-i18n="nav_courier_pickup">Courier Pickup</span>
                            @endif
                            @if($shop->supportsInShopRepair())
                                <span class="rounded bg-[var(--mint-100)] border border-[var(--border)]/70 px-2 py-0.5 font-medium text-teal-950" data-i18n="in_shop_walk_in">In-Shop Repair</span>
                            @endif
                            @if($shop->supportsSpareParts())
                                <span class="rounded bg-[var(--mint-100)] border border-[var(--border)]/70 px-2 py-0.5 font-medium text-sky-950" data-i18n="nav_spare_parts">Spare Parts</span>
                            @endif
                            @if($shop->hasActiveServiceDeals())
                                <span class="rounded bg-rose-100 border border-rose-300 px-2 py-0.5 font-bold text-rose-800" data-shop-deal-badge>
                                    <i class="fa-solid fa-fire mr-0.5 text-[9px] text-rose-600"></i>Active Deal
                                </span>
                            @endif
                            @if($shop->hasSparePartsOnSale())
                                <span class="rounded bg-rose-100 border border-rose-300 px-2 py-0.5 font-bold text-rose-800" data-shop-sale-badge>
                                    <i class="fa-solid fa-bolt mr-0.5 text-[9px] text-rose-600"></i>Active Sale
                                </span>
                            @endif
                            <span class="rounded bg-[var(--mint-100)] border border-[var(--border)]/70 px-2 py-0.5 font-semibold">
                                {{ str_repeat('৳', $shop->price_level ?? 2) }}
                            </span>
                        </div>

                        <!-- Available Vouchers (Foodpanda Alfresco Format: Offer + Code) -->
                        @php
                            $tileVouchers = $shop->availableVouchers();
                        @endphp
                        @if($tileVouchers->isNotEmpty())
                            <div class="mt-2 flex flex-wrap items-center gap-1.5" data-shop-tile-vouchers>
                                @foreach($tileVouchers->take(3) as $tv)
                                    <span
                                        data-shop-voucher-badge="{{ $tv->code }}"
                                        class="inline-flex items-center gap-1.5 rounded-md border border-[var(--border)] bg-white px-2.5 py-1 text-[10px] md:text-[11px] font-bold text-[var(--accent-dark)] shadow-2xs max-w-full"
                                    >
                                        <i class="fa-solid fa-ticket text-[9px] text-[var(--accent-dark)] shrink-0"></i>
                                        <span class="truncate">{{ $tv->advertisedBadgeText() }}</span>
                                    </span>
                                @endforeach
                                @if($tileVouchers->count() > 3)
                                    <span class="inline-flex items-center rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-2 py-1 text-[10px] font-bold text-[var(--accent-dark)] shadow-2xs">
                                        +{{ $tileVouchers->count() - 3 }} more
                                    </span>
                                @endif
                            </div>
                        @endif
                    </a>
                @empty
                    <div class="col-span-2 py-12 text-center rounded-lg border border-[var(--border)] bg-[var(--card)] p-8">
                        <i class="fa-solid fa-magnifying-glass text-2xl text-[var(--muted-foreground)] mb-3"></i>
                        <h3 class="text-base font-bold text-[var(--foreground)]" data-i18n="no_workshops_found">No matching workshops found</h3>
                        <p class="text-xs text-[var(--muted-foreground)] mt-1">Adjust your filters or clear selection to view available shops.</p>
                        <a href="/shops" class="inline-block mt-4 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-semibold text-white" data-i18n="clear_all_filters">
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

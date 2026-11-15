@extends('layouts.app')

@section('title', 'Spare Parts Marketplace — Mobile, Laptop & PC Components | Meramot')

@section('content')
@php
    $locLabel = collect([$customerLocation['area'] ?? null, $customerLocation['city'] ?? 'Dhaka'])
        ->filter()
        ->unique()
        ->implode(', ') ?: 'Banani, Dhaka';

    $currentQuery = request()->query();
    $buildFilterUrl = function (array $overrides = [], array $remove = ['page']) use ($currentQuery) {
        $merged = array_merge($currentQuery, $overrides);
        foreach ($remove as $rKey) {
            unset($merged[$rKey]);
        }
        $merged = array_filter($merged, fn ($v) => $v !== null && $v !== '' && $v !== 'All');
        $qs = http_build_query($merged);
        return url('/spare-parts') . ($qs ? ('?' . $qs) : '');
    };

    $defaultSort = !empty($hasSearchContext) ? 'best_deal' : 'alphabetical';
    $hasActiveFilters = ($search !== '' || $selectedCategory !== 'All' || $selectedBrand !== 'All' || $deviceType !== 'all' || $selectedGroup !== '' || $wholesaleOnly || $specsOnly || $localOnly || $onSaleOnly || $sort !== $defaultSort);

    $sortOptions = !empty($hasSearchContext)
        ? [
            'best_deal' => 'Price + Shipping: lowest first (Best Deal)',
            'lowest_price' => 'Price: lowest first',
            'highest_price' => 'Price + Shipping: highest first',
            'distance' => 'Distance: nearest first',
            'fastest_eta' => 'Fastest Delivery',
            'popular' => 'Top Sold',
            'wholesale' => 'Best Wholesale',
            'alphabetical' => 'Alphabetical: A–Z',
        ]
        : [
            'alphabetical' => 'Alphabetical: A–Z',
            'distance' => 'Distance: nearest first',
            'fastest_eta' => 'Fastest Delivery',
            'popular' => 'Top Sold',
            'wholesale' => 'Best Wholesale',
            'highest_price' => 'Price + Shipping: highest first',
        ];
    $activeSortLabel = $sortOptions[$sort] ?? ($hasSearchContext ? 'Price + Shipping: lowest first' : 'Alphabetical: A–Z');

    $authVendorProfileId = null;
    if (auth()->check() && auth()->user()->role === 'vendor') {
        $authVendorProfileId = \App\Models\VendorProfile::where('user_id', auth()->id())->value('id');
    }
@endphp

<div class="max-w-7xl mx-auto px-2.5 sm:px-6 lg:px-8 py-3 sm:py-6">
    {{-- Compact Breadcrumb & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-2 sm:gap-3 mb-2.5 sm:mb-4">
        <div class="min-w-0">
            <nav class="text-[11px] sm:text-xs font-medium text-[var(--muted-foreground)] mb-0.5 flex items-center gap-1.5 truncate">
                <a href="{{ url('/') }}" class="hover:underline">Home</a>
                <span>/</span>
                <span class="text-[var(--foreground)] font-semibold">Spare Parts</span>
                @if($selectedCategory !== 'All')
                    <span>/</span>
                    <span class="text-[var(--accent-dark)] font-bold truncate">{{ $selectedCategory }}</span>
                @endif
            </nav>
            <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-lg sm:text-2xl lg:text-3xl font-black tracking-tight text-[var(--foreground)] leading-tight">
                    Spare Parts Marketplace
                </h1>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] sm:text-xs font-bold bg-emerald-900 text-emerald-50">
                    {{ number_format($totalListingsCount) }} Live Listings
                </span>
            </div>
            <p class="hidden sm:block text-xs sm:text-sm text-[var(--muted-foreground)] mt-0.5">
                Compare genuine mobile, laptop &amp; PC parts across verified workshops — ranked by <strong class="font-semibold text-[var(--foreground)]">Item Price + Courier Delivery</strong> to your location, with AliExpress-style wholesale tiers.
            </p>
        </div>

        @auth
            @if(auth()->user()->role === 'vendor')
                <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                    <a href="{{ url('/orders') }}" data-no-soft-nav="true"
                       class="inline-flex items-center gap-1 px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-full text-[11px] sm:text-xs font-bold bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:opacity-90 transition shadow-sm">
                        📦 My Orders
                    </a>
                    <a href="{{ route('vendor.dashboard', ['tab' => 'spare_parts']) }}" data-no-soft-nav="true"
                       class="inline-flex items-center gap-1 px-3 py-1.5 sm:px-4 sm:py-2 rounded-full text-[11px] sm:text-xs font-bold bg-emerald-900 text-white hover:bg-emerald-800 transition shadow-sm">
                        + List My Parts
                    </a>
                </div>
            @endif
        @endauth
    </div>

    {{-- Unified Sleek Filter & Location Control Card --}}
    <div x-data class="rounded-2xl sm:rounded-[1.75rem] bg-[var(--card)] border border-[var(--border)] p-2.5 sm:p-4 mb-3 sm:mb-5 shadow-sm space-y-2 sm:space-y-2.5 select-none">

        {{-- Row 1: Search Input (Desktop/Tablet; Mobile uses global header search) + 1-Line Delivery Location Controls --}}
        <div class="flex flex-col lg:flex-row lg:items-center gap-2">
            {{-- Compact Search Form (Shown md+; mobile header already provides full-width spare-parts search) --}}
            <form action="{{ url('/spare-parts') }}" method="GET" class="hidden md:flex flex-1 items-center gap-1.5 min-w-0">
                @if($deviceType !== 'all')
                    <input type="hidden" name="device_type" value="{{ $deviceType }}">
                @endif
                @if($selectedCategory !== 'All')
                    <input type="hidden" name="part_category" value="{{ $selectedCategory }}">
                @endif
                @if($selectedBrand !== 'All')
                    <input type="hidden" name="brand" value="{{ $selectedBrand }}">
                @endif
                @if($sort !== 'best_deal')
                    <input type="hidden" name="sort" value="{{ $sort }}">
                @endif
                @if($onSaleOnly)
                    <input type="hidden" name="on_sale" value="1">
                @endif

                <div class="relative flex-1 min-w-0">
                    <input type="text"
                           name="q"
                           value="{{ $search }}"
                           placeholder="Search displays, batteries, laptop keyboards, SSDs, models..."
                           class="w-full h-9 sm:h-10 pl-3.5 {{ $search !== '' ? 'pr-16 sm:pr-10' : 'pr-10' }} rounded-full bg-[var(--mint-50)] border border-[var(--border)] text-xs sm:text-sm text-[var(--foreground)] placeholder-[var(--muted-foreground)] focus:outline-none focus:ring-2 focus:ring-emerald-800">
                    @if($search !== '')
                        <a href="{{ $buildFilterUrl(['q' => null]) }}"
                           class="absolute right-9 sm:right-3 top-1/2 -translate-y-1/2 w-5 h-5 rounded-full bg-emerald-900/10 flex items-center justify-center text-[11px] font-bold text-[var(--foreground)] hover:text-red-600"
                           title="Clear search">✕</a>
                    @endif
                    <button type="submit"
                            aria-label="Search Parts"
                            class="sm:hidden absolute right-1 top-1/2 -translate-y-1/2 w-7 h-7 rounded-full bg-emerald-900 text-white flex items-center justify-center text-xs hover:bg-emerald-800 transition">
                        <i class="fa-solid fa-magnifying-glass text-[11px]"></i>
                    </button>
                </div>

                <button type="submit"
                        class="hidden sm:inline-flex items-center px-4 h-10 rounded-full bg-emerald-900 text-white text-xs sm:text-sm font-bold hover:bg-emerald-800 transition shrink-0">
                    Search Parts
                </button>
            </form>

            {{-- Compact Single-Line Delivery Destination Bar (Never wraps awkwardly on mobile) --}}
            <div class="flex items-center justify-between sm:justify-end gap-1.5 min-w-0">
                <button type="button"
                        onclick="if(window.openMeramotLocationModal){window.openMeramotLocationModal();}"
                        class="min-w-0 flex-1 sm:flex-initial inline-flex items-center gap-1.5 h-8 sm:h-10 px-2.5 sm:px-3.5 rounded-full bg-[var(--mint-50)] border border-[var(--border)] text-[11px] sm:text-xs text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                    <span class="text-emerald-700 font-bold shrink-0">📍</span>
                    <span class="text-[var(--muted-foreground)] hidden md:inline shrink-0">Deliver to:</span>
                    <strong class="font-extrabold text-[var(--foreground)] truncate max-w-[165px] sm:max-w-[200px]" id="spActiveLocationLabel">{{ $locLabel }}</strong>
                    <span class="text-[10px] text-emerald-800 font-bold shrink-0 ml-auto sm:ml-0.5">Change ▾</span>
                </button>
            </div>
        </div>

        {{-- Row 2 (Mobile): 4-Tab Segmented Switcher (Mobile / PC Parts / Others / Sale!) — tap active tab to clear --}}
        <div class="grid grid-cols-4 gap-1 sm:hidden pt-0.5">
            <a href="{{ $buildFilterUrl(['device_type' => $deviceType === 'mobile' ? null : 'mobile', 'part_category' => null]) }}"
               class="text-center py-1.5 px-1 rounded-xl text-[11px] font-extrabold border transition truncate {{ $deviceType === 'mobile' ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)]' }}">
                📱 Mobile
            </a>
            <a href="{{ $buildFilterUrl(['device_type' => $deviceType === 'pc' ? null : 'pc', 'part_category' => null]) }}"
               class="text-center py-1.5 px-1 rounded-xl text-[11px] font-extrabold border transition truncate {{ $deviceType === 'pc' ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)]' }}">
                💻 PC Parts
            </a>
            <a href="{{ $buildFilterUrl(['device_type' => $deviceType === 'others' ? null : 'others', 'part_category' => null]) }}"
               class="text-center py-1.5 px-1 rounded-xl text-[11px] font-extrabold border transition truncate {{ $deviceType === 'others' ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)]' }}">
                🧩 Others
            </a>
            <a href="{{ $buildFilterUrl(['on_sale' => $onSaleOnly ? null : '1']) }}"
               class="text-center py-1.5 px-1 rounded-xl text-[11px] font-extrabold border transition truncate {{ $onSaleOnly ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-red-50 text-red-700 border-red-200' }}">
                🔴 Sale!
            </a>
        </div>

        {{-- Desktop Catalog & Deal Toggles Row (No redundant "All Parts" pill; click active segment to show all) --}}
        <div class="hidden sm:flex items-center gap-2 min-w-0 border-t border-[var(--border)]/60 pt-2">
            <span class="w-20 shrink-0 text-[11px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">Catalog:</span>
            <div class="flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                @foreach([
                    'mobile' => '📱 Mobile & Gadget Parts',
                    'pc' => '💻 Laptop & PC Parts',
                    'others' => '🧩 Others',
                ] as $dtKey => $dtLabel)
                    <a href="{{ $buildFilterUrl(['device_type' => $deviceType === $dtKey ? null : $dtKey, 'part_category' => null]) }}"
                       class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-bold border transition {{ $deviceType === $dtKey ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                        {{ $dtLabel }}
                    </a>
                @endforeach

                <span class="text-[var(--border)] px-0.5">|</span>

                <a href="{{ $buildFilterUrl(['on_sale' => $onSaleOnly ? null : '1']) }}"
                   class="shrink-0 px-3 py-1.5 rounded-full text-xs font-bold border transition {{ $onSaleOnly ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' }}">
                    🔴 Sale! Offers
                </a>

                <a href="{{ $buildFilterUrl(['wholesale' => $wholesaleOnly ? null : '1']) }}"
                   class="shrink-0 px-3 py-1.5 rounded-full text-xs font-bold border transition {{ $wholesaleOnly ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                    📦 Wholesale (100+)
                </a>

                <a href="{{ $buildFilterUrl(['has_specs' => $specsOnly ? null : '1']) }}"
                   class="shrink-0 px-3 py-1.5 rounded-full text-xs font-bold border transition {{ $specsOnly ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                    🎛️ Multi-Spec Grades
                </a>

                <a href="{{ $buildFilterUrl(['local_only' => $localOnly ? null : '1']) }}"
                   class="shrink-0 px-3 py-1.5 rounded-full text-xs font-bold border transition {{ $localOnly ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                    📍 Same-City ({{ $customerLocation['city'] ?? 'Dhaka' }})
                </a>
            </div>
        </div>
    </div>

    {{-- eBay-Style Result Summary, Active Breadcrumb Chips & Clean Sort Dropdown Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3 sm:mb-4 px-0.5 text-[11px] sm:text-sm text-[var(--muted-foreground)]">
        <div class="min-w-0 flex flex-wrap items-center gap-1.5 sm:gap-2">
            <span>
                Showing <strong class="text-[var(--foreground)]">{{ number_format($listings->firstItem() ?? 0) }}–{{ number_format($listings->lastItem() ?? 0) }}</strong>
                of <strong class="text-[var(--foreground)]">{{ number_format($totalListingsCount) }}</strong> results
            </span>
            @if($selectedCategory !== 'All')
                <a href="{{ $buildFilterUrl(['part_category' => null, 'brand' => null]) }}"
                   class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-900/10 text-emerald-950 text-[11px] font-bold hover:bg-emerald-900/20">
                    {{ $selectedCategory }} <span class="text-red-600">✕</span>
                </a>
            @endif
            @if($selectedBrand !== 'All')
                <a href="{{ $buildFilterUrl(['brand' => null]) }}"
                   class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full bg-emerald-900/10 text-emerald-950 text-[11px] font-bold hover:bg-emerald-900/20">
                    Brand: {{ $selectedBrand }} <span class="text-red-600">✕</span>
                </a>
            @endif
            @if($selectedGroup !== '')
                <a href="{{ $buildFilterUrl(['group' => null]) }}" class="text-[11px] sm:text-xs font-bold text-emerald-800 underline">
                    ← All parts
                </a>
            @endif
            @if($hasActiveFilters)
                <a href="{{ url('/spare-parts') }}" class="text-[11px] sm:text-xs font-extrabold text-red-600 hover:underline">
                    Reset Filters
                </a>
            @endif
        </div>

        <div class="flex items-center gap-2 shrink-0">
            <button type="button"
                    onclick="if(window.openMeramotLocationModal){window.openMeramotLocationModal();}"
                    class="hidden md:inline-flex items-center gap-1 text-xs font-semibold text-[var(--muted-foreground)] hover:text-[var(--foreground)] transition">
                <span>Shipping to <strong class="text-[var(--foreground)]">{{ $customerLocation['city'] ?? 'Dhaka' }}</strong></span>
                <span>▾</span>
            </button>

            {{-- eBay-Style Sort Popover Dropdown --}}
            <div class="relative" x-data="{ sortOpen: false }" @click.away="sortOpen = false" @keydown.escape.window="sortOpen = false">
                <button type="button"
                        @click="sortOpen = !sortOpen"
                        aria-haspopup="true"
                        :aria-expanded="sortOpen"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-[var(--card)] border border-[var(--border)] text-[11px] sm:text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-50)] transition shadow-xs">
                    <span>⇅</span>
                    <span class="hidden sm:inline text-[var(--muted-foreground)] font-semibold">Sort:</span>
                    <span class="truncate max-w-[140px] sm:max-w-[210px]">{{ $activeSortLabel }}</span>
                    <span class="text-[10px]">▾</span>
                </button>

                <div x-show="sortOpen"
                     x-cloak
                     x-transition.opacity.duration.120ms
                     class="absolute right-0 mt-1.5 w-64 sm:w-72 rounded-2xl bg-white dark:bg-[var(--card)] border border-[var(--border)] shadow-xl py-1.5 z-30">
                    @foreach($sortOptions as $sKey => $sLabel)
                        @php
                            $isSortActive = $sort === $sKey;
                            $sortHref = $buildFilterUrl(['sort' => $sKey === $defaultSort ? null : $sKey]);
                        @endphp
                        <a href="{{ $sortHref }}"
                           @click="sortOpen = false"
                           class="flex items-center gap-2.5 px-3.5 py-2 text-xs transition {{ $isSortActive ? 'bg-emerald-50 text-emerald-950 font-extrabold' : 'text-[var(--foreground)] hover:bg-[var(--mint-50)] font-medium' }}">
                            <span class="w-4 h-4 rounded-full border-2 flex items-center justify-center shrink-0 {{ $isSortActive ? 'border-emerald-900' : 'border-slate-300' }}">
                                @if($isSortActive)
                                    <span class="w-2 h-2 rounded-full bg-emerald-900"></span>
                                @endif
                            </span>
                            <span class="leading-snug">{{ $sLabel }}</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Main 2-Column Catalog Layout: Left Hierarchical CATEGORIES Tree + Right Product Grid --}}
    <div x-data="{ mobileCatsOpen: false, openCat: @js($selectedCategory !== 'All' ? $selectedCategory : '') }"
         class="grid grid-cols-1 lg:grid-cols-[248px_minmax(0,1fr)] gap-4 items-start">

        {{-- Mobile Collapsible Category Trigger --}}
        <div class="lg:hidden">
            <button type="button"
                    @click="mobileCatsOpen = !mobileCatsOpen"
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-xl bg-[var(--card)] border border-[var(--border)] text-xs font-extrabold text-[var(--foreground)] shadow-xs">
                <span>📂 Categories &amp; Brands ({{ count($categoryBrandTree) }})</span>
                <span x-text="mobileCatsOpen ? '▲ Hide' : '▼ Browse'">▼ Browse</span>
            </button>
        </div>

        {{-- Left Hierarchical CATEGORIES Sidebar (`laptopbd.net` style: Category -> Expandable Brands) --}}
        <aside :class="mobileCatsOpen ? 'block' : 'hidden lg:block'"
               class="rounded-2xl bg-[var(--card)] border border-[var(--border)] shadow-sm overflow-hidden">
            <div class="px-4 py-3 bg-emerald-900 text-white flex items-center justify-between">
                <span class="text-xs font-extrabold uppercase tracking-wider">Categories</span>
                @if($selectedCategory !== 'All' || $selectedBrand !== 'All')
                    <a href="{{ $buildFilterUrl(['part_category' => null, 'brand' => null]) }}"
                       class="text-[10px] font-bold text-amber-200 hover:underline">
                        Show All
                    </a>
                @endif
            </div>

            <div class="divide-y divide-[var(--border)]/60 max-h-[68vh] lg:max-h-[calc(100vh-140px)] overflow-y-auto">
                @foreach($categoryBrandTree as $catNode)
                    @php
                        $catName = $catNode['name'];
                        $isCatSelected = strcasecmp($selectedCategory, $catName) === 0;
                    @endphp
                    <div class="px-3 py-2">
                        <div class="flex items-center justify-between gap-1.5">
                            <a href="{{ $buildFilterUrl(['part_category' => $catName, 'brand' => null]) }}"
                               class="flex-1 min-w-0 py-1 text-xs font-bold truncate transition {{ $isCatSelected ? 'text-emerald-900 dark:text-emerald-300 font-extrabold' : 'text-[var(--foreground)] hover:text-emerald-800' }}">
                                {{ $catName }}
                                <span class="text-[10px] font-semibold text-[var(--muted-foreground)] ml-0.5">({{ number_format($catNode['total']) }})</span>
                            </a>
                            @if(!empty($catNode['brands']))
                                <button type="button"
                                        @click="openCat = (openCat === @js($catName) ? '' : @js($catName))"
                                        aria-label="Toggle brands in {{ $catName }}"
                                        class="w-6 h-6 rounded-md flex items-center justify-center text-[11px] text-[var(--muted-foreground)] hover:bg-[var(--mint-50)] hover:text-[var(--foreground)] transition shrink-0">
                                    <span x-text="openCat === @js($catName) ? '⌃' : '⌄'">⌄</span>
                                </button>
                            @endif
                        </div>

                        @if(!empty($catNode['brands']))
                            <div x-show="openCat === @js($catName)"
                                 x-cloak
                                 class="mt-1.5 pl-3 border-l-2 border-emerald-900/20 space-y-1 pb-1">
                                @foreach($catNode['brands'] as $brandNode)
                                    @php
                                        $bName = $brandNode['name'];
                                        $isBrandSelected = $isCatSelected && strcasecmp($selectedBrand, $bName) === 0;
                                    @endphp
                                    <a href="{{ $buildFilterUrl(['part_category' => $catName, 'brand' => $bName]) }}"
                                       class="flex items-center justify-between py-0.5 text-[11px] rounded px-1.5 transition {{ $isBrandSelected ? 'bg-emerald-900 text-white font-bold' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)] hover:bg-[var(--mint-50)]' }}">
                                        <span class="truncate">{{ $bName }}</span>
                                        <span class="text-[10px] opacity-75 shrink-0">{{ number_format($brandNode['count']) }}</span>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </aside>

        {{-- Right Column: Spare Part Item Grid --}}
        <div class="min-w-0">
    @if($listings->isEmpty())
        <div class="rounded-2xl bg-[var(--card)] border border-[var(--border)] p-8 sm:p-10 text-center">
            <div class="text-3xl mb-2">🔩</div>
            <h2 class="text-base sm:text-lg font-bold text-[var(--foreground)]">No spare part listings matched your filters</h2>
            <p class="text-xs sm:text-sm text-[var(--muted-foreground)] mt-1 mb-4">Try clearing filters or switching from Same-City Sellers to nationwide delivery.</p>
            <a href="{{ url('/spare-parts') }}" class="inline-flex items-center px-5 py-2.5 rounded-full bg-emerald-900 text-white text-xs font-bold hover:bg-emerald-800 transition">
                Browse All Spare Parts
            </a>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-3.5">
            @foreach($listings as $item)
                @php
                    $shop = $item->shop;
                    $quote = $item->landed_quote ?? $item->calculateLandedQuote($customerLocation, 1);
                    $offerActive = $item->isOfferActive();
                    $effBase = $item->effectiveBasePrice();
                    $effMax = $item->effectiveMaxPrice();
                    $normalBase = $item->resolvedNormalBasePrice();
                    $isOwnVendorListing = $authVendorProfileId && $shop && ((int) $shop->vendor_profile_id === (int) $authVendorProfileId);
                    $detailUrl = route('spare-parts.show', ['slug' => $item->slug]);
                    $showBestDealBadge = !empty($hasSearchContext) && $item->is_best_deal && $item->seller_count > 1;
                @endphp

                <a href="{{ $detailUrl }}"
                   data-no-soft-nav="true"
                   class="group rounded-xl sm:rounded-2xl bg-white/95 dark:bg-[var(--card)] border {{ $showBestDealBadge ? 'border-emerald-700/50' : 'border-[var(--border)]' }} hover:border-emerald-700 hover:shadow-md transition flex flex-col overflow-hidden min-w-0">
                    {{-- Product Image with Circular Red "Sale!" Banner & Search-Only Best Deal Tag --}}
                    <div class="relative aspect-square bg-white border-b border-slate-100 dark:border-[var(--border)]/40 overflow-hidden p-2.5 sm:p-3.5">
                        @if($offerActive)
                            <span class="absolute top-2 left-2 sm:top-2.5 sm:left-2.5 z-10 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-red-600 text-white text-[10px] sm:text-[11px] font-extrabold tracking-tight flex items-center justify-center shadow-sm select-none"
                                  title="{{ $item->offerWindowLabel() }}">
                                Sale!
                            </span>
                        @endif

                        @if($showBestDealBadge)
                            <span class="absolute top-2 right-2 sm:top-2.5 sm:right-2.5 z-10 px-2 py-0.5 rounded-full bg-emerald-800 text-white text-[9px] sm:text-[10px] font-bold tracking-tight shadow-xs">
                                ★ Best Deal
                            </span>
                        @elseif($isOwnVendorListing)
                            <span class="absolute top-2 right-2 sm:top-2.5 sm:right-2.5 z-10 px-2 py-0.5 rounded-full bg-amber-600 text-white text-[9px] sm:text-[10px] font-bold shadow-xs">
                                Yours
                            </span>
                        @endif

                        <img src="{{ $item->image_url }}"
                             alt="{{ $item->title }}"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='/images/spare-parts/samsung-galaxy-s21-fe-5g-display.webp';"
                             class="w-full h-full object-contain group-hover:scale-105 transition duration-300">
                    </div>

                    {{-- Minimalistic Card Body: Title + Vendor-Net Price (Excl. Platform Fee) + Delivery + Seller --}}
                    <div class="p-2.5 sm:p-3.5 flex-1 flex flex-col justify-between gap-2 min-w-0">
                        <h2 class="text-xs sm:text-[13px] font-semibold text-[var(--foreground)] leading-snug line-clamp-2 group-hover:text-emerald-800 transition">
                            {{ $item->title }}
                        </h2>

                        <div class="space-y-1.5 min-w-0">
                            {{-- Part Price / Spec Range + Strikethrough Normal Price --}}
                            <div>
                                @if($item->has_specs && $effMax && $effMax > $effBase)
                                    <div class="flex items-baseline gap-1 flex-wrap">
                                        @if($offerActive && $normalBase > $effBase)
                                            <span class="text-[11px] text-slate-400 line-through">৳{{ number_format($normalBase, 0) }}</span>
                                        @endif
                                        <span class="text-sm sm:text-base font-extrabold {{ $offerActive ? 'text-red-600' : 'text-[var(--foreground)]' }}">
                                            ৳{{ number_format($effBase, 0) }}<span class="text-xs font-bold">–৳{{ number_format($effMax, 0) }}</span>
                                        </span>
                                    </div>
                                @else
                                    <div class="flex items-baseline gap-1.5 flex-wrap">
                                        @if($offerActive && $normalBase > $effBase)
                                            <span class="text-[11px] text-slate-400 line-through">৳{{ number_format($normalBase, 0) }}</span>
                                            <span class="text-sm sm:text-base font-extrabold text-red-600">৳{{ number_format($effBase, 0) }}</span>
                                        @else
                                            <span class="text-sm sm:text-base font-extrabold text-[var(--foreground)]">৳{{ number_format($effBase, 0) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Minimalistic Unboxed Shipping + Landed Breakdown --}}
                            <div class="pt-1.5 border-t border-slate-200/70 dark:border-[var(--border)]/50 space-y-0.5 text-[10px] sm:text-[11px]">
                                <div class="flex items-center justify-between text-[var(--muted-foreground)] gap-1">
                                    <span>Delivery: ৳{{ number_format((float) $quote['delivery_fee'], 0) }}</span>
                                    <span class="hidden sm:inline truncate">{{ $quote['eta_label'] }}</span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] sm:text-xs">
                                    <span class="text-[var(--muted-foreground)]">Est. Total</span>
                                    <span class="font-extrabold text-[var(--foreground)]">
                                        {{ ($item->has_specs && $effMax && $effMax > $effBase) ? 'from ' : '' }}৳{{ number_format((float) $quote['landed_total'], 0) }}
                                    </span>
                                </div>
                            </div>

                            {{-- Quiet 1-Line Seller Footer --}}
                            @if($shop)
                                <div class="flex items-center justify-between gap-1 text-[10px] sm:text-[11px] text-[var(--muted-foreground)] pt-0.5 min-w-0">
                                    <span class="truncate">{{ $shop->name }}</span>
                                    <span class="shrink-0">★ {{ number_format((float) ($shop->rating ?: 4.8), 1) }}</span>
                                </div>
                            @endif
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($listings->hasPages())
            <div class="mt-6 sm:mt-7">
                {{ $listings->links() }}
            </div>
        @endif
    @endif
        </div>
    </div>
</div>

<script>
window.spNavigateFilterUrl = function (targetUrl) {
    if (!targetUrl) return;
    if (window.MeramotSoftNav && typeof window.MeramotSoftNav.visit === 'function') {
        window.MeramotSoftNav.visit(targetUrl, { push: true });
    } else {
        window.location.href = targetUrl;
    }
};
</script>
@endsection

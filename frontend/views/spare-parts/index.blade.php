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

    $selectedSubCategory = $selectedSubCategory ?? '';
    $selectedSubCategoryLabel = $selectedSubCategoryLabel ?? '';
    $defaultSort = !empty($hasActiveSearchQuery) ? 'best_match' : (!empty($hasSearchContext) ? 'best_deal' : 'alphabetical');
    $hasActiveFilters = ($search !== '' || $selectedCategory !== 'All' || $selectedSubCategory !== '' || $selectedBrand !== 'All' || $deviceType !== 'all' || $selectedGroup !== '' || $wholesaleOnly || $specsOnly || $localOnly || $onSaleOnly || $sort !== $defaultSort);

    $sortOptions = !empty($hasActiveSearchQuery)
        ? [
            'best_match' => 'Best Match (Relevance)',
            'best_deal' => 'Price + Shipping: lowest first (Best Deal)',
            'lowest_price' => 'Item Price: lowest first',
            'highest_price' => 'Price + Shipping: highest first',
            'distance' => 'Distance: nearest first',
            'fastest_eta' => 'Fastest Delivery',
            'popular' => 'Top Sold',
            'wholesale' => 'Best Wholesale',
            'alphabetical' => 'Alphabetical: A–Z',
        ]
        : (!empty($hasSearchContext)
        ? [
            'best_deal' => 'Price + Shipping: lowest first (Best Deal)',
            'lowest_price' => 'Item Price: lowest first',
            'highest_price' => 'Price + Shipping: highest first',
            'distance' => 'Distance: nearest first',
            'fastest_eta' => 'Fastest Delivery',
            'popular' => 'Top Sold',
            'wholesale' => 'Best Wholesale',
            'alphabetical' => 'Alphabetical: A–Z',
        ]
        : [
            'alphabetical' => 'Alphabetical: A–Z',
            'best_deal' => 'Price + Shipping: lowest first (Best Deal)',
            'lowest_price' => 'Item Price: lowest first',
            'highest_price' => 'Price + Shipping: highest first',
            'distance' => 'Distance: nearest first',
            'fastest_eta' => 'Fastest Delivery',
            'popular' => 'Top Sold',
            'wholesale' => 'Best Wholesale',
        ]);
    $activeSortLabel = $sortOptions[$sort] ?? (!empty($hasActiveSearchQuery) ? 'Best Match (Relevance)' : ($hasSearchContext ? 'Price + Shipping: lowest first (Best Deal)' : 'Alphabetical: A–Z'));

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
                <a href="{{ url('/') }}" class="hover:underline" data-i18n="home_nav">Home</a>
                <span>/</span>
                <span class="text-[var(--foreground)] font-semibold" data-i18n="nav_spare_parts">Spare Parts</span>
                @if($selectedCategory !== 'All')
                    <span>/</span>
                    <span class="text-[var(--accent-dark)] font-bold truncate">{{ $selectedCategory }}</span>
                @endif
                @if($selectedSubCategoryLabel !== '')
                    <span>/</span>
                    <span class="text-[var(--foreground)] font-semibold truncate">{{ $selectedSubCategoryLabel }}</span>
                @endif
                @if($selectedBrand !== 'All')
                    <span>/</span>
                    <span class="text-[var(--foreground)] font-semibold truncate">{{ $selectedBrand }}</span>
                @endif
            </nav>
            <h1 class="text-lg sm:text-2xl font-bold tracking-tight text-[var(--foreground)] leading-tight" data-i18n="spare_parts_market">
                Spare Parts Marketplace
            </h1>
            <p class="hidden sm:block text-xs sm:text-sm text-[var(--muted-foreground)] mt-0.5" data-i18n="spare_parts_subtitle">
                Genuine replacement components and precision repair tools from verified workshops.
            </p>
        </div>

        @auth
            @if(auth()->user()->role === 'vendor')
                <div class="flex items-center gap-1.5 sm:gap-2 shrink-0">
                    <a href="{{ url('/orders') }}" data-no-soft-nav="true"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 sm:px-3.5 sm:py-2 rounded-md text-[11px] sm:text-xs font-semibold bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:opacity-90 transition shadow-xs">
                        <i class="fa-solid fa-box text-[11px]"></i> My Orders
                    </a>
                    <a href="{{ route('vendor.dashboard', ['tab' => 'spare_parts']) }}" data-no-soft-nav="true"
                       class="inline-flex items-center gap-1 px-3 py-1.5 sm:px-4 sm:py-2 rounded-md text-[11px] sm:text-xs font-semibold bg-emerald-900 text-white hover:bg-emerald-800 transition shadow-xs">
                        + List My Parts
                    </a>
                </div>
            @endif
        @endauth
    </div>

    {{-- Unified Sleek Filter & Location Control Card --}}
    <div x-data class="rounded-lg bg-[var(--card)] border border-[var(--border)] p-2.5 sm:p-3.5 mb-3 sm:mb-4 shadow-xs space-y-2 sm:space-y-2.5 select-none">

        {{-- Row 1: Search Input (Desktop/Tablet; Mobile uses global header search) + 1-Line Delivery Location Controls --}}
        <div class="flex flex-col lg:flex-row lg:items-center gap-2">
            {{-- Compact Search Form (Shown md+; mobile header already provides full-width spare-parts search) --}}
            <form action="{{ url('/spare-parts') }}" method="GET" class="hidden md:flex flex-1 items-center gap-1.5 min-w-0">
                <input type="hidden" name="src" value="search">
                @if($deviceType !== 'all')
                    <input type="hidden" name="device_type" value="{{ $deviceType }}">
                @endif
                @if($selectedCategory !== 'All')
                    <input type="hidden" name="part_category" value="{{ $selectedCategory }}">
                @endif
                @if($selectedSubCategory !== '')
                    <input type="hidden" name="sub_category" value="{{ $selectedSubCategory }}">
                @endif
                @if($selectedBrand !== 'All')
                    <input type="hidden" name="brand" value="{{ $selectedBrand }}">
                @endif
                @if($sort !== $defaultSort)
                    <input type="hidden" name="sort" value="{{ $sort }}">
                @endif
                @if($onSaleOnly)
                    <input type="hidden" name="on_sale" value="1">
                @endif

                <div class="relative flex-1 min-w-0"
                     x-data="meramotTypeahead(@js($search ?? ''), 'spare-parts')"
                     @click.outside="close()">
                    <input type="text"
                           name="q"
                           value="{{ $search }}"
                           x-model="query"
                           autocomplete="off"
                           @input.debounce.180ms="fetchSuggestions()"
                           @focus="onFocus()"
                           @keydown.down.prevent="moveSelection(1)"
                           @keydown.up.prevent="moveSelection(-1)"
                           @keydown.enter="onEnter($event)"
                           @keydown.escape="close()"
                           placeholder="Search displays, batteries, laptop keyboards, SSDs, models..."
                           data-i18n-placeholder="search_parts_placeholder"
                           class="w-full h-9 sm:h-10 pl-3.5 {{ $search !== '' ? 'pr-16 sm:pr-10' : 'pr-10' }} rounded-md bg-[var(--mint-50)] border border-[var(--border)] text-xs sm:text-sm text-[var(--foreground)] placeholder-[var(--muted-foreground)] focus:outline-none focus:ring-2 focus:ring-emerald-800">
                    @if($search !== '')
                        <a href="{{ $buildFilterUrl(['q' => null]) }}"
                           class="absolute right-9 sm:right-3 top-1/2 -translate-y-1/2 w-5 h-5 rounded-md bg-emerald-900/10 flex items-center justify-center text-[11px] font-semibold text-[var(--foreground)] hover:text-red-600"
                           title="Clear search"><i class="fa-solid fa-xmark text-[10px]"></i></a>
                    @endif
                    <button type="submit"
                            @click="close()"
                            aria-label="Search Parts"
                            class="sm:hidden absolute right-1 top-1/2 -translate-y-1/2 w-7 h-7 rounded-md bg-emerald-900 text-white flex items-center justify-center text-xs hover:bg-emerald-800 transition">
                        <i class="fa-solid fa-magnifying-glass text-[11px]"></i>
                    </button>
                    @include('partials.search-suggest-dropdown')
                </div>

                <button type="submit"
                        class="hidden sm:inline-flex items-center px-4 h-10 rounded-md bg-emerald-900 text-white text-xs sm:text-sm font-semibold hover:bg-emerald-800 transition shrink-0"
                        data-i18n="search_parts_btn">
                    Search Parts
                </button>
            </form>

            {{-- Compact Single-Line Delivery Destination Bar (Never wraps awkwardly on mobile) --}}
            <div class="flex items-center justify-between sm:justify-end gap-1.5 min-w-0">
                <button type="button"
                        onclick="if(window.openMeramotLocationModal){window.openMeramotLocationModal();}"
                        class="min-w-0 flex-1 sm:flex-initial inline-flex items-center gap-1.5 h-8 sm:h-10 px-2.5 sm:px-3.5 rounded-md bg-[var(--mint-50)] border border-[var(--border)] text-[11px] sm:text-xs text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                    <i class="fa-solid fa-location-dot text-emerald-700 text-xs shrink-0"></i>
                    <span class="text-[var(--muted-foreground)] hidden md:inline shrink-0" data-i18n="deliver_to">Deliver to:</span>
                    <strong class="font-semibold text-[var(--foreground)] truncate max-w-[165px] sm:max-w-[200px]" id="spActiveLocationLabel">{{ $locLabel }}</strong>
                    <span class="text-[10px] text-emerald-800 font-semibold shrink-0 ml-auto sm:ml-0.5" data-i18n="change_dropdown">Change ▾</span>
                </button>
            </div>
        </div>

        {{-- Row 2 (Mobile): 4-Tab Segmented Switcher (Mobile / PC Parts / Tools / Sale!) — tap active tab to clear --}}
        <div class="grid grid-cols-4 gap-1 sm:hidden pt-0.5">
            <a href="{{ $buildFilterUrl(['device_type' => $deviceType === 'mobile' ? null : 'mobile', 'part_category' => null, 'sub_category' => null]) }}"
               class="text-center py-1.5 px-1 rounded-md text-[11px] font-semibold border transition truncate inline-flex items-center justify-center gap-1 {{ $deviceType === 'mobile' ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)]' }}">
                <i class="fa-solid fa-mobile-screen-button text-[10px]"></i> Mobile
            </a>
            <a href="{{ $buildFilterUrl(['device_type' => $deviceType === 'pc' ? null : 'pc', 'part_category' => null, 'sub_category' => null]) }}"
               class="text-center py-1.5 px-1 rounded-md text-[11px] font-semibold border transition truncate inline-flex items-center justify-center gap-1 {{ $deviceType === 'pc' ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)]' }}">
                <i class="fa-solid fa-laptop text-[10px]"></i> PC Parts
            </a>
            <a href="{{ $buildFilterUrl(['device_type' => $deviceType === 'others' ? null : 'others', 'part_category' => null, 'sub_category' => null]) }}"
               class="text-center py-1.5 px-1 rounded-md text-[11px] font-semibold border transition truncate inline-flex items-center justify-center gap-1 {{ $deviceType === 'others' ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)]' }}">
                <i class="fa-solid fa-screwdriver-wrench text-[10px]"></i> Tools
            </a>
            <a href="{{ $buildFilterUrl(['on_sale' => $onSaleOnly ? null : '1']) }}"
               class="text-center py-1.5 px-1 rounded-md text-[11px] font-semibold border transition truncate inline-flex items-center justify-center gap-1 {{ $onSaleOnly ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-red-50 text-red-700 border-red-200' }}">
                <i class="fa-solid fa-tag text-[10px]"></i> Sale!
            </a>
        </div>

        {{-- Desktop Catalog & Deal Toggles Row --}}
        <div class="hidden sm:flex items-center gap-2 min-w-0 border-t border-[var(--border)]/60 pt-2">
            <span class="w-20 shrink-0 text-[11px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)]" data-i18n="catalog_label">Catalog:</span>
            <div class="flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                @foreach([
                    'mobile' => ['icon' => 'fa-mobile-screen-button', 'label' => 'Mobile Parts'],
                    'pc' => ['icon' => 'fa-laptop', 'label' => 'Laptop & PC Parts'],
                    'others' => ['icon' => 'fa-screwdriver-wrench', 'label' => 'Tools & Soldering'],
                ] as $dtKey => $dtMeta)
                    <a href="{{ $buildFilterUrl(['device_type' => $deviceType === $dtKey ? null : $dtKey, 'part_category' => null, 'sub_category' => null]) }}"
                       class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold border transition {{ $deviceType === $dtKey ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                        <i class="fa-solid {{ $dtMeta['icon'] }} text-[11px] opacity-80"></i>
                        <span>{{ $dtMeta['label'] }}</span>
                    </a>
                @endforeach

                <span class="text-[var(--border)] px-0.5">|</span>

                <a href="{{ $buildFilterUrl(['on_sale' => $onSaleOnly ? null : '1']) }}"
                   class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold border transition {{ $onSaleOnly ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-red-50 text-red-700 border-red-200 hover:bg-red-100' }}">
                    <i class="fa-solid fa-tag text-[11px]"></i> Sale! Offers
                </a>

                <a href="{{ $buildFilterUrl(['wholesale' => $wholesaleOnly ? null : '1']) }}"
                   class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold border transition {{ $wholesaleOnly ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                    <i class="fa-solid fa-boxes-stacked text-[11px] opacity-80"></i> Wholesale (100+)
                </a>

                <a href="{{ $buildFilterUrl(['has_specs' => $specsOnly ? null : '1']) }}"
                   class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold border transition {{ $specsOnly ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                    <i class="fa-solid fa-sliders text-[11px] opacity-80"></i> Multi-Spec Grades
                </a>

                <a href="{{ $buildFilterUrl(['local_only' => $localOnly ? null : '1']) }}"
                   class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-xs font-semibold border transition {{ $localOnly ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                    <i class="fa-solid fa-location-dot text-[11px] opacity-80"></i> Same-City ({{ $customerLocation['city'] ?? 'Dhaka' }})
                </a>
            </div>
        </div>
    </div>

    {{-- eBay-Style Result Summary, Active Breadcrumb Chips & Clean Sort Dropdown Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-3 sm:mb-4 px-0.5 text-[11px] sm:text-sm text-[var(--muted-foreground)]">
        <div class="min-w-0 flex flex-wrap items-center gap-1.5 sm:gap-2">
            <span class="tabular-nums">
                Showing <strong class="text-[var(--foreground)] font-semibold">{{ number_format($listings->firstItem() ?? 0) }}–{{ number_format($listings->lastItem() ?? 0) }}</strong>
                of <strong class="text-[var(--foreground)] font-semibold">{{ number_format($totalListingsCount) }}</strong> results
            </span>
            @if($selectedCategory !== 'All')
                <a href="{{ $buildFilterUrl(['part_category' => null, 'sub_category' => null, 'brand' => null]) }}"
                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-900/10 text-emerald-950 text-[11px] font-semibold hover:bg-emerald-900/20">
                    {{ $selectedCategory }} <i class="fa-solid fa-xmark text-red-600 text-[10px]"></i>
                </a>
            @endif
            @if($selectedSubCategory !== '' && $selectedSubCategoryLabel !== '')
                <a href="{{ $buildFilterUrl(['sub_category' => null]) }}"
                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-900/10 text-emerald-950 text-[11px] font-semibold hover:bg-emerald-900/20">
                    {{ $selectedSubCategoryLabel }} <i class="fa-solid fa-xmark text-red-600 text-[10px]"></i>
                </a>
            @endif
            @if($selectedBrand !== 'All')
                <a href="{{ $buildFilterUrl(['brand' => null]) }}"
                   class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-emerald-900/10 text-emerald-950 text-[11px] font-semibold hover:bg-emerald-900/20">
                    Brand: {{ $selectedBrand }} <i class="fa-solid fa-xmark text-red-600 text-[10px]"></i>
                </a>
            @endif
            @if($selectedGroup !== '')
                <a href="{{ $buildFilterUrl(['group' => null]) }}" class="text-[11px] sm:text-xs font-semibold text-emerald-800 underline">
                    ← All parts
                </a>
            @endif
            @if($hasActiveFilters)
                <a href="{{ url('/spare-parts') }}" class="text-[11px] sm:text-xs font-semibold text-red-600 hover:underline" data-i18n="clear_all_filters">
                    Reset Filters
                </a>
            @endif
        </div>

        <div class="flex items-center gap-2 shrink-0">

            {{-- eBay-Style Sort Popover Dropdown --}}
            <div class="relative" x-data="{ sortOpen: false }" @click.away="sortOpen = false" @keydown.escape.window="sortOpen = false">
                <button type="button"
                        @click="sortOpen = !sortOpen"
                        aria-haspopup="true"
                        :aria-expanded="sortOpen"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-[var(--card)] border border-[var(--border)] text-[11px] sm:text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-50)] transition shadow-xs">
                    <i class="fa-solid fa-arrow-up-wide-short text-[11px] text-[var(--muted-foreground)]"></i>
                    <span class="hidden sm:inline text-[var(--muted-foreground)] font-medium">Sort:</span>
                    <span class="truncate max-w-[140px] sm:max-w-[210px]">{{ $activeSortLabel }}</span>
                    <i class="fa-solid fa-chevron-down text-[9px] text-[var(--muted-foreground)]"></i>
                </button>

                <div x-show="sortOpen"
                     x-cloak
                     x-transition.opacity.duration.120ms
                     class="absolute right-0 mt-1.5 w-64 sm:w-72 rounded-lg bg-white dark:bg-[var(--card)] border border-[var(--border)] shadow-xl py-1.5 z-30">
                    @foreach($sortOptions as $sKey => $sLabel)
                        @php
                            $isSortActive = $sort === $sKey;
                            $sortHref = $buildFilterUrl(['sort' => $sKey === $defaultSort ? null : $sKey]);
                        @endphp
                        <a href="{{ $sortHref }}"
                           @click="sortOpen = false"
                           class="flex items-center gap-2.5 px-3.5 py-2 text-xs transition {{ $isSortActive ? 'bg-emerald-50 text-emerald-950 font-semibold' : 'text-[var(--foreground)] hover:bg-[var(--mint-50)] font-medium' }}">
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

    @php
        $directoryTree = $directoryTree ?? [];
        $defaultOpenRoots = collect($directoryTree)->pluck('key')->values()->all();
        $defaultOpenSub = ($selectedCategory !== 'All' && $selectedSubCategory !== '') ? ($selectedCategory . '::' . $selectedSubCategory) : '';
        $defaultOpenBrandsKey = ($selectedCategory !== 'All' && $selectedBrand !== 'All')
            ? ($selectedSubCategory !== '' ? ($selectedCategory . '::' . $selectedSubCategory) : ($selectedCategory . '::__cat'))
            : '';
    @endphp

    {{-- Main 2-Column Catalog Layout: Minimalistic Hierarchical Categories Tree + Product Grid --}}
    <div x-data="{
            mobileCatsOpen: false,
            openRoots: @js($defaultOpenRoots),
            openCat: @js($selectedCategory !== 'All' ? $selectedCategory : ''),
            openSub: @js($defaultOpenSub),
            openBrandsKey: @js($defaultOpenBrandsKey),
            toggleRoot(k) {
                if (this.openRoots.includes(k)) {
                    this.openRoots = this.openRoots.filter(x => x !== k);
                } else {
                    this.openRoots = [...this.openRoots, k];
                }
            }
         }"
         class="grid grid-cols-1 lg:grid-cols-[260px_minmax(0,1fr)] gap-4 items-start">

        {{-- Mobile Collapsible Categories Drawer Trigger --}}
        <div class="lg:hidden">
            <button type="button"
                    @click="mobileCatsOpen = !mobileCatsOpen"
                    class="w-full flex items-center justify-between px-3.5 py-2.5 rounded-lg bg-white/90 dark:bg-[var(--card)] border border-[var(--border)] text-xs font-semibold text-[var(--foreground)]">
                <span class="inline-flex items-center gap-2">
                    <i class="fa-regular fa-folder text-xs text-slate-500"></i>
                    <span>Categories ({{ count($directoryTree) }} groups)</span>
                </span>
                <span class="text-[11px] text-[var(--muted-foreground)]" x-text="mobileCatsOpen ? 'Hide ▴' : 'Open ▾'">Open ▾</span>
            </button>
        </div>

        {{-- Left Minimalistic Hierarchical Categories Sidebar --}}
        <aside :class="mobileCatsOpen ? 'block' : 'hidden lg:block'"
               aria-label="Parts categories tree"
               class="rounded-lg bg-white/85 dark:bg-[var(--card)] border border-slate-200/85 dark:border-[var(--border)] overflow-hidden">
            <div class="px-3 py-2 border-b border-slate-200/75 dark:border-[var(--border)] flex items-center justify-between bg-slate-50/70 dark:bg-transparent">
                <div class="flex items-center gap-1.5 text-[11px] font-semibold uppercase tracking-wider text-slate-500 dark:text-[var(--muted-foreground)]">
                    <i class="fa-regular fa-folder text-[11px] text-slate-400"></i>
                    <span>CATEGORIES</span>
                </div>
                @if($deviceType !== 'all' || $selectedCategory !== 'All' || $selectedSubCategory !== '' || $selectedBrand !== 'All')
                    <a href="{{ $buildFilterUrl(['device_type' => null, 'part_category' => null, 'sub_category' => null, 'brand' => null]) }}"
                       class="text-[11px] font-medium text-emerald-700 hover:underline">
                        Show all
                    </a>
                @endif
            </div>

            <div class="p-2 text-[12px] space-y-1 max-h-[70vh] lg:max-h-[calc(100vh-130px)] overflow-y-auto">
                {{-- Top-Level Root Entry --}}
                <a href="{{ $buildFilterUrl(['device_type' => null, 'part_category' => null, 'sub_category' => null, 'brand' => null]) }}"
                   class="flex items-center justify-between gap-2 px-2 py-1 rounded-md transition {{ ($deviceType === 'all' && $selectedCategory === 'All') ? 'bg-emerald-50/90 text-emerald-900 font-semibold dark:bg-emerald-950/40 dark:text-emerald-200' : 'text-[var(--foreground)] hover:bg-slate-100/70 dark:hover:bg-white/5 font-medium' }}">
                    <span class="inline-flex items-center gap-1.5 min-w-0">
                        <i class="fa-regular fa-folder text-[11px] shrink-0 text-slate-400"></i>
                        <span class="truncate">All Spare Parts &amp; Tools</span>
                    </span>
                </a>

                {{-- Level 1: Root Directories -> Level 2: Category Folders -> Level 3: Sub-Categories & Brands --}}
                @foreach($directoryTree as $rootNode)
                    @php
                        $rKey = $rootNode['key'];
                        $isRootActive = ($deviceType === $rKey && $selectedCategory === 'All');
                    @endphp
                    <div class="space-y-0.5 pt-0.5">
                        <div class="flex items-center gap-1 px-1 py-1 rounded-md {{ $isRootActive ? 'bg-emerald-50/90 text-emerald-900 dark:bg-emerald-950/40 dark:text-emerald-200' : 'hover:bg-slate-100/70 dark:hover:bg-white/5 text-[var(--foreground)]' }}">
                            <button type="button"
                                    @click="toggleRoot(@js($rKey))"
                                    aria-label="Toggle {{ $rootNode['label'] }} folder"
                                    class="w-4 h-4 flex items-center justify-center text-slate-400 hover:text-slate-700 shrink-0 transition-transform"
                                    :class="openRoots.includes(@js($rKey)) ? 'rotate-90' : ''">
                                <i class="fa-solid fa-chevron-right text-[9px]"></i>
                            </button>
                            <a href="{{ $buildFilterUrl(['device_type' => $isRootActive ? null : $rKey, 'part_category' => null, 'sub_category' => null, 'brand' => null]) }}"
                               class="flex-1 min-w-0 flex items-center justify-between gap-1.5">
                                <span class="inline-flex items-center gap-1.5 min-w-0">
                                    <i class="fa-regular fa-folder text-[11px] shrink-0 {{ $isRootActive ? 'text-emerald-700' : 'text-slate-500' }}"></i>
                                    <span class="truncate font-semibold">{{ $rootNode['label'] }}</span>
                                </span>
                                <span class="text-[10px] text-slate-400 tabular-nums shrink-0">{{ number_format($rootNode['total']) }}</span>
                            </a>
                        </div>

                        {{-- Category Folders under Root --}}
                        <div x-show="openRoots.includes(@js($rKey))"
                             x-cloak
                             class="ml-3 pl-2.5 border-l border-slate-200/85 dark:border-[var(--border)] space-y-0.5">
                            @foreach($rootNode['categories'] as $catNode)
                                @php
                                    $catName = $catNode['name'];
                                    $catLabel = $catNode['label'] ?? $catName;
                                    $isCatSelected = strcasecmp($selectedCategory, $catName) === 0;
                                    $hasChildren = !empty($catNode['sub_categories']) || !empty($catNode['brands']);
                                @endphp
                                <div class="space-y-0.5">
                                    <div class="flex items-center gap-1 px-1 py-0.5 rounded-md transition {{ $isCatSelected ? 'bg-emerald-50/90 text-emerald-950 font-semibold dark:bg-emerald-950/40 dark:text-emerald-200' : 'text-slate-700 dark:text-[var(--foreground)] hover:bg-slate-100/70 dark:hover:bg-white/5' }}">
                                        @if($hasChildren)
                                            <button type="button"
                                                    @click="openCat = (openCat === @js($catName) ? '' : @js($catName))"
                                                    aria-label="Toggle subfolders in {{ $catLabel }}"
                                                    class="w-4 h-4 flex items-center justify-center text-slate-400 hover:text-slate-700 shrink-0 transition-transform"
                                                    :class="openCat === @js($catName) ? 'rotate-90' : ''">
                                                <i class="fa-solid fa-chevron-right text-[8px]"></i>
                                            </button>
                                        @else
                                            <span class="w-4 shrink-0"></span>
                                        @endif

                                        <a href="{{ $buildFilterUrl(['device_type' => null, 'part_category' => $isCatSelected && $selectedSubCategory === '' && $selectedBrand === 'All' ? null : $catName, 'sub_category' => null, 'brand' => null]) }}"
                                           class="flex-1 min-w-0 flex items-center justify-between gap-1.5 py-0.5">
                                            <span class="inline-flex items-center gap-1.5 min-w-0">
                                                <i class="fa-regular fa-folder text-[11px] shrink-0 {{ $isCatSelected ? 'text-emerald-700' : 'text-slate-400' }}"></i>
                                                <span class="truncate {{ $isCatSelected ? 'font-semibold' : 'font-medium' }}">{{ $catLabel }}</span>
                                            </span>
                                            <span class="text-[10px] text-slate-400 tabular-nums shrink-0">{{ number_format($catNode['total']) }}</span>
                                        </a>
                                    </div>

                                    {{-- Level 3: Sub-Categories -> Level 4: Brands (Last Sub-Category) -> Level 5: Brand --}}
                                    @if($hasChildren)
                                        <div x-show="openCat === @js($catName)"
                                             x-cloak
                                             class="ml-3 pl-2 border-l border-slate-200/80 dark:border-[var(--border)] space-y-0.5 py-0.5">
                                            @foreach($catNode['sub_categories'] as $subNode)
                                                @php
                                                    $subKey = $subNode['key'];
                                                    $subCompositeKey = $catName . '::' . $subKey;
                                                    $subBrands = $subNode['brands'] ?? [];
                                                    $isSubSelected = $isCatSelected && ($selectedSubCategory === $subKey);
                                                    $isSubDirectActive = $isSubSelected && $selectedBrand === 'All';
                                                @endphp
                                                <div class="space-y-0.5">
                                                    <div class="flex items-center gap-1 px-1 py-0.5 rounded transition {{ $isSubSelected ? 'bg-emerald-900/10 text-emerald-950 font-semibold dark:bg-emerald-900/40 dark:text-emerald-200' : 'text-slate-600 dark:text-[var(--muted-foreground)] hover:text-slate-950 hover:bg-slate-100/70 dark:hover:bg-white/5' }}">
                                                        @if(!empty($subBrands))
                                                            <button type="button"
                                                                    @click="openSub = (openSub === @js($subCompositeKey) ? '' : @js($subCompositeKey))"
                                                                    aria-label="Toggle brands in {{ $subNode['label'] }}"
                                                                    class="w-3.5 h-3.5 flex items-center justify-center text-slate-400 hover:text-slate-700 shrink-0 transition-transform"
                                                                    :class="openSub === @js($subCompositeKey) ? 'rotate-90' : ''">
                                                                <i class="fa-solid fa-chevron-right text-[8px]"></i>
                                                            </button>
                                                        @else
                                                            <span class="w-3.5 shrink-0"></span>
                                                        @endif

                                                        <a href="{{ $buildFilterUrl(['device_type' => null, 'part_category' => $catName, 'sub_category' => $isSubDirectActive ? null : $subKey, 'brand' => null]) }}"
                                                           class="flex-1 min-w-0 flex items-center justify-between gap-1 text-[11px]">
                                                            <span class="truncate">{{ $subNode['label'] }}</span>
                                                            <span class="text-[10px] text-slate-400 tabular-nums shrink-0">{{ number_format($subNode['count']) }}</span>
                                                        </a>
                                                    </div>

                                                    @if(!empty($subBrands))
                                                        <div x-show="openSub === @js($subCompositeKey)"
                                                             x-cloak
                                                             class="ml-3 pl-2 border-l border-slate-200/75 dark:border-[var(--border)] space-y-0.5 py-0.5">
                                                            <button type="button"
                                                                    @click="openBrandsKey = (openBrandsKey === @js($subCompositeKey) ? '' : @js($subCompositeKey))"
                                                                    class="w-full flex items-center justify-between gap-1.5 px-1.5 py-0.5 rounded text-[11px] text-slate-500 hover:text-slate-900 hover:bg-slate-100/70 dark:hover:bg-white/5 transition">
                                                                <span class="inline-flex items-center gap-1.5 min-w-0">
                                                                    <i class="fa-regular fa-folder text-[10px] text-slate-400 shrink-0"></i>
                                                                    <span class="font-medium truncate">Brands ({{ count($subBrands) }})</span>
                                                                </span>
                                                                <span class="text-[10px] text-slate-400" x-text="openBrandsKey === @js($subCompositeKey) ? '▾' : '▸'">▸</span>
                                                            </button>

                                                            <div x-show="openBrandsKey === @js($subCompositeKey)"
                                                                 x-cloak
                                                                 class="ml-2.5 pl-2 border-l border-slate-200/75 dark:border-[var(--border)] space-y-0.5 mt-0.5">
                                                                @foreach(array_slice($subBrands, 0, $isCatSelected ? 100 : 12) as $brandNode)
                                                                    @php
                                                                        $bName = $brandNode['name'];
                                                                        $isBrandSelected = $isSubSelected && strcasecmp($selectedBrand, $bName) === 0;
                                                                    @endphp
                                                                    <a href="{{ $buildFilterUrl(['device_type' => null, 'part_category' => $catName, 'sub_category' => $subKey, 'brand' => $isBrandSelected ? null : $bName]) }}"
                                                                       class="flex items-center justify-between gap-1.5 px-1.5 py-0.5 rounded text-[11px] transition {{ $isBrandSelected ? 'bg-emerald-900 text-white font-semibold' : 'text-slate-500 hover:text-slate-950 hover:bg-slate-100/70 dark:text-[var(--muted-foreground)] dark:hover:bg-white/5' }}">
                                                                        <span class="truncate">{{ $bName }}</span>
                                                                        <span class="text-[10px] {{ $isBrandSelected ? 'text-emerald-100' : 'text-slate-400' }} tabular-nums shrink-0">{{ number_format($brandNode['count']) }}</span>
                                                                    </a>
                                                                @endforeach
                                                            </div>
                                                        </div>
                                                    @endif
                                                </div>
                                            @endforeach

                                            @if(empty($catNode['sub_categories']) && !empty($catNode['brands']))
                                                @php $catBrandKey = $catName . '::__cat'; @endphp
                                                <div class="pt-0.5">
                                                    <button type="button"
                                                            @click="openBrandsKey = (openBrandsKey === @js($catBrandKey) ? '' : @js($catBrandKey))"
                                                            class="w-full flex items-center justify-between gap-1.5 px-1.5 py-0.5 rounded text-[11px] text-slate-500 hover:text-slate-900 hover:bg-slate-100/70 dark:hover:bg-white/5 transition">
                                                        <span class="inline-flex items-center gap-1.5 min-w-0">
                                                            <i class="fa-regular fa-folder text-[10px] text-slate-400 shrink-0"></i>
                                                            <span class="font-medium truncate">Brands ({{ count($catNode['brands']) }})</span>
                                                        </span>
                                                        <span class="text-[10px] text-slate-400" x-text="openBrandsKey === @js($catBrandKey) ? '▾' : '▸'">▸</span>
                                                    </button>

                                                    <div x-show="openBrandsKey === @js($catBrandKey)"
                                                         x-cloak
                                                         class="ml-2.5 pl-2 border-l border-slate-200/75 dark:border-[var(--border)] space-y-0.5 mt-0.5">
                                                        @foreach(array_slice($catNode['brands'], 0, $isCatSelected ? 100 : 12) as $brandNode)
                                                            @php
                                                                $bName = $brandNode['name'];
                                                                $isBrandSelected = $isCatSelected && $selectedSubCategory === '' && strcasecmp($selectedBrand, $bName) === 0;
                                                            @endphp
                                                            <a href="{{ $buildFilterUrl(['device_type' => null, 'part_category' => $catName, 'sub_category' => null, 'brand' => $isBrandSelected ? null : $bName]) }}"
                                                               class="flex items-center justify-between gap-1.5 px-1.5 py-0.5 rounded text-[11px] transition {{ $isBrandSelected ? 'bg-emerald-900 text-white font-semibold' : 'text-slate-500 hover:text-slate-950 hover:bg-slate-100/70 dark:text-[var(--muted-foreground)] dark:hover:bg-white/5' }}">
                                                                <span class="truncate">{{ $bName }}</span>
                                                                <span class="text-[10px] {{ $isBrandSelected ? 'text-emerald-100' : 'text-slate-400' }} tabular-nums shrink-0">{{ number_format($brandNode['count']) }}</span>
                                                            </a>
                                                        @endforeach
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </aside>

        {{-- Right Column: Spare Part Item Grid --}}
        <div class="min-w-0">
    @if($listings->isEmpty())
        <div class="rounded-lg bg-[var(--card)] border border-[var(--border)] p-8 sm:p-10 text-center">
            <div class="text-2xl text-[var(--muted-foreground)] mb-2"><i class="fa-solid fa-screwdriver-wrench"></i></div>
            <h2 class="text-base sm:text-lg font-bold text-[var(--foreground)]">No spare part listings matched your filters</h2>
            <p class="text-xs sm:text-sm text-[var(--muted-foreground)] mt-1 mb-4">Try adjusting your search keywords, clearing active filters, or expanding to nationwide delivery.</p>
            <a href="{{ url('/spare-parts') }}" class="inline-flex items-center px-5 py-2 rounded-md bg-emerald-900 text-white text-xs font-semibold hover:bg-emerald-800 transition">
                Browse All Spare Parts
            </a>
        </div>
    @else
        <div class="grid grid-cols-2 sm:grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-3">
            @foreach($listings as $item)
                @php
                    $shop = $item->shop;
                    $quote = $item->landed_quote ?? $item->calculateLandedQuote($customerLocation, 1);
                    $offerActive = $item->isOfferActive();
                    $effBase = $item->effectiveBasePrice();
                    $effMax = $item->effectiveMaxPrice();
                    $normalBase = $item->resolvedNormalBasePrice();
                    $isOwnVendorListing = $authVendorProfileId && $shop && ((int) $shop->vendor_profile_id === (int) $authVendorProfileId);
                    $detailUrl = route('spare-parts.show', array_filter([
                        'slug' => $item->slug,
                        'q' => $search !== '' ? $search : null,
                    ]));
                    $showBestDealBadge = !empty($hasActiveSearchQuery) && $item->is_best_deal && $item->seller_count > 1;
                @endphp

                <a href="{{ $detailUrl }}"
                   data-no-soft-nav="true"
                   class="group rounded-lg bg-white/95 dark:bg-[var(--card)] border {{ $showBestDealBadge ? 'border-emerald-700/60' : 'border-[var(--border)]' }} hover:border-emerald-700 hover:shadow-md transition flex flex-col overflow-hidden min-w-0">
                    {{-- Product Image with Compact Red "Sale!" Tag & Search-Only Best Deal Tag --}}
                    <div class="relative aspect-square bg-white border-b border-slate-100 dark:border-[var(--border)]/40 overflow-hidden p-2.5 sm:p-3">
                        @if($offerActive)
                            <span class="absolute top-2 left-2 z-10 rounded px-1.5 py-0.5 bg-red-600 text-white text-[10px] font-semibold tracking-tight leading-none shadow-xs select-none"
                                  title="{{ $item->offerWindowLabel() }}">
                                Sale!
                            </span>
                        @endif

                        @if($showBestDealBadge)
                            <span class="absolute top-2 right-2 z-10 px-1.5 py-0.5 rounded bg-emerald-800 text-white text-[9px] sm:text-[10px] font-semibold tracking-tight leading-none shadow-xs">
                                Best Deal
                            </span>
                        @elseif($isOwnVendorListing)
                            <span class="absolute top-2 right-2 z-10 px-1.5 py-0.5 rounded bg-amber-600 text-white text-[9px] sm:text-[10px] font-semibold leading-none shadow-xs">
                                Yours
                            </span>
                        @endif

                        <img src="{{ $item->image_url }}"
                             alt="{{ $item->title }}"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='{{ e($item->fallbackImageUrl()) }}';"
                             class="w-full h-full object-contain group-hover:scale-105 transition duration-300">
                    </div>

                    {{-- Minimalistic Card Body: Title + Vendor-Net Price (Excl. Platform Fee) + Delivery + Seller --}}
                    <div class="p-2.5 sm:p-3 flex-1 flex flex-col justify-between gap-2 min-w-0">
                        <h2 class="text-xs sm:text-[13px] font-medium text-[var(--foreground)] leading-snug line-clamp-2 group-hover:text-emerald-800 transition">
                            {{ $item->title }}
                        </h2>

                        <div class="space-y-1.5 min-w-0">
                            {{-- Part Price / Spec Range + Strikethrough Normal Price --}}
                            <div class="tabular-nums">
                                @if($item->has_specs && $effMax && $effMax > $effBase)
                                    <div class="flex items-baseline gap-1 flex-wrap">
                                        @if($offerActive && $normalBase > $effBase)
                                            <span class="text-[11px] text-slate-400 line-through">৳{{ number_format($normalBase, 0) }}</span>
                                        @endif
                                        <span class="text-sm sm:text-base font-bold {{ $offerActive ? 'text-red-600' : 'text-[var(--foreground)]' }}">
                                            ৳{{ number_format($effBase, 0) }}<span class="text-xs font-semibold">–৳{{ number_format($effMax, 0) }}</span>
                                        </span>
                                    </div>
                                @else
                                    <div class="flex items-baseline gap-1.5 flex-wrap">
                                        @if($offerActive && $normalBase > $effBase)
                                            <span class="text-[11px] text-slate-400 line-through">৳{{ number_format($normalBase, 0) }}</span>
                                            <span class="text-sm sm:text-base font-bold text-red-600">৳{{ number_format($effBase, 0) }}</span>
                                        @else
                                            <span class="text-sm sm:text-base font-bold text-[var(--foreground)]">৳{{ number_format($effBase, 0) }}</span>
                                        @endif
                                    </div>
                                @endif
                            </div>

                            {{-- Minimalistic Unboxed Shipping + Landed Breakdown --}}
                            <div class="pt-1.5 border-t border-slate-200/70 dark:border-[var(--border)]/50 space-y-0.5 text-[10px] sm:text-[11px] tabular-nums">
                                <div class="flex items-center justify-between text-[var(--muted-foreground)] gap-1">
                                    <span>Delivery: ৳{{ number_format((float) $quote['delivery_fee'], 0) }}</span>
                                    <span class="hidden sm:inline truncate">{{ $quote['eta_label'] }}</span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] sm:text-xs">
                                    <span class="text-[var(--muted-foreground)]">Est. Total</span>
                                    <span class="font-semibold text-[var(--foreground)]">
                                        {{ ($item->has_specs && $effMax && $effMax > $effBase) ? 'from ' : '' }}৳{{ number_format((float) $quote['landed_total'], 0) }}
                                    </span>
                                </div>
                            </div>

                            {{-- Quiet 1-Line Seller Footer --}}
                            @if($shop)
                                <div class="flex items-center justify-between gap-1 text-[10px] sm:text-[11px] text-[var(--muted-foreground)] pt-0.5 min-w-0">
                                    <span class="truncate">{{ $shop->name }}</span>
                                    <span class="shrink-0 tabular-nums">★ {{ ((float) ($shop->rating ?? 0)) > 0 ? number_format((float) $shop->rating, 1) : '4.8' }}</span>
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
    (function() {
        const isMobileScreen = window.innerWidth < 768;
        const targetPerPage = isMobileScreen ? 9 : 16;
        const currentPerPage = @js((int) $listings->perPage());

        try {
            document.cookie = 'sp_per_page=' + targetPerPage + '; path=/; max-age=604800; SameSite=Lax';
        } catch (e) {}

        const url = new URL(window.location.href);
        if (!url.searchParams.has('per_page') && currentPerPage !== targetPerPage && (!url.searchParams.has('page') || url.searchParams.get('page') === '1')) {
            url.searchParams.set('per_page', targetPerPage);
            window.location.replace(url.toString());
        }
    })();
</script>
@endsection

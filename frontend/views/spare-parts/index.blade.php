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

    $hasActiveFilters = ($search !== '' || $selectedCategory !== 'All' || $selectedBrand !== 'All' || $deviceType !== 'all' || $selectedGroup !== '' || $wholesaleOnly || $specsOnly || $localOnly || $onSaleOnly || $sort !== 'best_deal');

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
                    <strong class="font-extrabold text-[var(--foreground)] truncate max-w-[145px] sm:max-w-[180px]" id="spActiveLocationLabel">{{ $locLabel }}</strong>
                    <span class="text-[10px] text-emerald-800 font-bold shrink-0 ml-auto sm:ml-0.5">Pin ▾</span>
                </button>

                <button type="button"
                        id="spNearMeBtn"
                        onclick="window.meramotUseGpsForSpareParts && window.meramotUseGpsForSpareParts(this)"
                        class="select-none shrink-0 inline-flex items-center gap-1 h-8 sm:h-10 px-2.5 sm:px-3.5 rounded-full bg-emerald-900 text-white text-[11px] sm:text-xs font-bold hover:bg-emerald-800 transition">
                    <span>◎ Near Me</span>
                </button>
            </div>
        </div>

        {{-- Row 2: Mobile 4-Tab Segmented Switcher (Zero Horizontal Clipping) + Desktop Deal Pills --}}
        <div class="grid grid-cols-4 gap-1 sm:hidden pt-0.5">
            <a href="{{ $buildFilterUrl(['device_type' => null, 'part_category' => null, 'on_sale' => null]) }}"
               class="text-center py-1.5 px-1 rounded-xl text-[11px] font-extrabold border transition truncate {{ ($deviceType === 'all' && !$onSaleOnly) ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)]' }}">
                All Parts
            </a>
            <a href="{{ $buildFilterUrl(['device_type' => 'mobile', 'part_category' => null]) }}"
               class="text-center py-1.5 px-1 rounded-xl text-[11px] font-extrabold border transition truncate {{ $deviceType === 'mobile' ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)]' }}">
                📱 Mobile
            </a>
            <a href="{{ $buildFilterUrl(['device_type' => 'pc', 'part_category' => null]) }}"
               class="text-center py-1.5 px-1 rounded-xl text-[11px] font-extrabold border transition truncate {{ $deviceType === 'pc' ? 'bg-emerald-900 text-white border-emerald-900 shadow-xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)]' }}">
                💻 PC Parts
            </a>
            <a href="{{ $buildFilterUrl(['on_sale' => $onSaleOnly ? null : '1']) }}"
               class="text-center py-1.5 px-1 rounded-xl text-[11px] font-extrabold border transition truncate {{ $onSaleOnly ? 'bg-red-600 text-white border-red-600 shadow-xs' : 'bg-red-50 text-red-700 border-red-200' }}">
                🔴 Sale!
            </a>
        </div>

        {{-- Desktop Catalog & Deal Toggles Row --}}
        <div class="hidden sm:flex items-center gap-2 min-w-0 border-t border-[var(--border)]/60 pt-2">
            <span class="w-20 shrink-0 text-[11px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">Catalog:</span>
            <div class="flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                @foreach([
                    'all' => '🌐 All Parts (Mobile + PC)',
                    'mobile' => '📱 Mobile & Gadget Parts',
                    'pc' => '💻 Laptop & PC Parts',
                ] as $dtKey => $dtLabel)
                    <a href="{{ $buildFilterUrl(['device_type' => $dtKey === 'all' ? null : $dtKey, 'part_category' => null]) }}"
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

        {{-- Row 3: Part Category Chip Slider (Fixed Label Outside Scrollport on Desktop, Full-Bleed Unclipped Chips on Mobile) --}}
        <div class="flex items-center gap-2 min-w-0 sm:border-t sm:border-[var(--border)]/60 sm:pt-2">
            <span class="hidden sm:inline-block w-20 shrink-0 text-[11px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">Part Type:</span>
            <div class="flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5"
                 x-init="const el = $el.querySelector('[data-active-pill=true]'); if (el && (el.offsetLeft + el.offsetWidth > $el.clientWidth)) { $el.scrollLeft = Math.max(0, el.offsetLeft - ($el.clientWidth - el.offsetWidth) / 2); }">
                <a href="{{ $buildFilterUrl(['part_category' => null]) }}"
                   @if($selectedCategory === 'All') data-active-pill="true" @endif
                   class="shrink-0 whitespace-nowrap px-3 py-1 rounded-full text-[11px] sm:text-xs font-bold border transition {{ $selectedCategory === 'All' ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                    All Types
                </a>
                @foreach($partCategories as $cat)
                    @php $isCatActive = strcasecmp($selectedCategory, $cat) === 0; @endphp
                    <a href="{{ $buildFilterUrl(['part_category' => $cat]) }}"
                       @if($isCatActive) data-active-pill="true" @endif
                       class="shrink-0 whitespace-nowrap px-3 py-1 rounded-full text-[11px] sm:text-xs font-bold border transition {{ $isCatActive ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                        {{ $cat }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Row 4A (Mobile Only): Ultra-Clean 3-Column Brand / Sort / Wholesale Bar --}}
        <div class="grid grid-cols-3 gap-1.5 sm:hidden pt-0.5">
            <select aria-label="Filter by Brand"
                    onchange="window.spNavigateFilterUrl(this.value)"
                    class="w-full h-8 px-2 rounded-xl bg-[var(--mint-50)] border border-[var(--border)] text-[11px] font-bold text-[var(--foreground)] focus:outline-none focus:ring-1 focus:ring-emerald-800">
                <option value="{{ $buildFilterUrl(['brand' => null]) }}" {{ $selectedBrand === 'All' ? 'selected' : '' }}>Brand: All</option>
                @foreach($popularBrands as $br)
                    <option value="{{ $buildFilterUrl(['brand' => $br]) }}" {{ strcasecmp($selectedBrand, $br) === 0 ? 'selected' : '' }}>
                        {{ $br }}
                    </option>
                @endforeach
            </select>

            <select aria-label="Sort Spare Parts"
                    onchange="window.spNavigateFilterUrl(this.value)"
                    class="w-full h-8 px-2 rounded-xl bg-[var(--mint-50)] border border-[var(--border)] text-[11px] font-bold text-[var(--foreground)] focus:outline-none focus:ring-1 focus:ring-emerald-800">
                <option value="{{ $buildFilterUrl(['sort' => null]) }}" {{ $sort === 'best_deal' ? 'selected' : '' }}>🏆 Best Deal</option>
                <option value="{{ $buildFilterUrl(['sort' => 'lowest_price']) }}" {{ $sort === 'lowest_price' ? 'selected' : '' }}>🏷️ Low Price</option>
                <option value="{{ $buildFilterUrl(['sort' => 'fastest_eta']) }}" {{ $sort === 'fastest_eta' ? 'selected' : '' }}>⚡ Fastest</option>
                <option value="{{ $buildFilterUrl(['sort' => 'popular']) }}" {{ $sort === 'popular' ? 'selected' : '' }}>🔥 Top Sold</option>
                <option value="{{ $buildFilterUrl(['sort' => 'wholesale']) }}" {{ $sort === 'wholesale' ? 'selected' : '' }}>📦 Bulk Tier</option>
            </select>

            <a href="{{ $buildFilterUrl(['wholesale' => $wholesaleOnly ? null : '1']) }}"
               class="h-8 px-2 rounded-xl flex items-center justify-center text-[11px] font-extrabold border transition truncate {{ $wholesaleOnly ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)]' }}">
                📦 100+ Bulk
            </a>
        </div>

        {{-- Row 4B (Desktop Only): Brand, Sort & Quick City Destination Sliders with Fixed Non-Scrolling Labels --}}
        <div class="hidden sm:block space-y-2 border-t border-[var(--border)]/60 pt-2">
            {{-- Brand Row --}}
            <div class="flex items-center gap-2 min-w-0">
                <span class="w-20 shrink-0 text-[11px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">Brand:</span>
                <div class="flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5"
                     x-init="const el = $el.querySelector('[data-active-pill=true]'); if (el && (el.offsetLeft + el.offsetWidth > $el.clientWidth)) { $el.scrollLeft = Math.max(0, el.offsetLeft - ($el.clientWidth - el.offsetWidth) / 2); }">
                    <a href="{{ $buildFilterUrl(['brand' => null]) }}"
                       @if($selectedBrand === 'All') data-active-pill="true" @endif
                       class="shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $selectedBrand === 'All' ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                        All
                    </a>
                    @foreach($popularBrands as $br)
                        @php $isBrandActive = strcasecmp($selectedBrand, $br) === 0; @endphp
                        <a href="{{ $buildFilterUrl(['brand' => $br]) }}"
                           @if($isBrandActive) data-active-pill="true" @endif
                           class="shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $isBrandActive ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                            {{ $br }}
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Sort + Quick City Switcher Row --}}
            <div class="flex items-center justify-between gap-3 min-w-0 pt-0.5">
                <div class="flex items-center gap-2 min-w-0 flex-1">
                    <span class="w-20 shrink-0 text-[11px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">Sort By:</span>
                    <div class="flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar py-0.5">
                        @foreach([
                            'best_deal' => '🏆 Best Deal Near You',
                            'lowest_price' => '🏷️ Lowest Part Price',
                            'fastest_eta' => '⚡ Fastest Delivery',
                            'popular' => '🔥 Top Sold',
                            'wholesale' => '📦 Best Wholesale',
                        ] as $sKey => $sLabel)
                            <a href="{{ $buildFilterUrl(['sort' => $sKey === 'best_deal' ? null : $sKey]) }}"
                               class="shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $sort === $sKey ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                                {{ $sLabel }}
                            </a>
                        @endforeach
                    </div>
                </div>

                <div class="hidden xl:flex items-center gap-1.5 shrink-0">
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">City Quote:</span>
                    @foreach(array_slice($quickLocations, 0, 4) as $ql)
                        @php
                            $isLocActive = strcasecmp((string) ($customerLocation['city'] ?? ''), $ql['city']) === 0
                                && (empty($customerLocation['area']) || strcasecmp((string) $customerLocation['area'], $ql['area']) === 0 || strcasecmp((string) $customerLocation['area'], $ql['city']) === 0);
                        @endphp
                        <a href="{{ $buildFilterUrl(['city' => $ql['city'], 'area' => $ql['area'], 'lat' => $ql['lat'], 'lng' => $ql['lng']]) }}"
                           class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition {{ $isLocActive ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                            {{ $ql['city'] === 'Dhaka' ? $ql['area'] : $ql['city'] }}
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    {{-- Compact Result Summary Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-2.5 sm:mb-4 px-0.5 text-[11px] sm:text-sm text-[var(--muted-foreground)]">
        <div class="min-w-0">
            Showing <strong class="text-[var(--foreground)]">{{ number_format($listings->firstItem() ?? 0) }}–{{ number_format($listings->lastItem() ?? 0) }}</strong>
            of <strong class="text-[var(--foreground)]">{{ number_format($totalListingsCount) }}</strong>
            <span class="hidden sm:inline">spare part listings ({{ number_format($distinctProductCount) }} unique parts)</span>
            <span class="sm:hidden">({{ number_format($distinctProductCount) }} parts)</span>
            <span class="hidden md:inline">• Quotes for <strong class="text-[var(--foreground)]">{{ $locLabel }}</strong></span>
        </div>

        <div class="flex items-center gap-3 shrink-0">
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
    </div>

    {{-- Spare Part Item Grid (2-Column E-Commerce Grid on Mobile, 3 on LG, 4 on XL) --}}
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
        <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-2.5 sm:gap-4">
            @foreach($listings as $item)
                @php
                    $shop = $item->shop;
                    $quote = $item->landed_quote ?? $item->calculateLandedQuote($customerLocation, 1);
                    $offerActive = $item->isOfferActive();
                    $effBase = $item->effectiveBasePrice();
                    $effMax = $item->effectiveMaxPrice();
                    $normalBase = $item->resolvedNormalBasePrice();
                    $branchTag = $shop?->display_branch_tag;
                    $isOwnVendorListing = $authVendorProfileId && $shop && ((int) $shop->vendor_profile_id === (int) $authVendorProfileId);
                    $detailUrl = route('spare-parts.show', ['slug' => $item->slug]);
                @endphp

                <a href="{{ $detailUrl }}"
                   data-no-soft-nav="true"
                   class="group rounded-xl sm:rounded-2xl bg-white/95 dark:bg-[var(--card)] border {{ ($item->is_best_deal && $item->seller_count > 1) ? 'border-emerald-700/50' : 'border-[var(--border)]' }} hover:border-emerald-700 hover:shadow-md transition flex flex-col overflow-hidden min-w-0">
                    {{-- Product Image with Circular Red "Sale!" Banner on Top-Left Corner & Auto Best Deal Tag --}}
                    <div class="relative aspect-square bg-white border-b border-slate-100 dark:border-[var(--border)]/40 overflow-hidden p-2.5 sm:p-3.5">
                        @if($offerActive)
                            <span class="absolute top-2 left-2 sm:top-2.5 sm:left-2.5 z-10 w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-red-600 text-white text-[10px] sm:text-[11px] font-extrabold tracking-tight flex items-center justify-center shadow-sm select-none"
                                  title="{{ $item->offerWindowLabel() }}">
                                Sale!
                            </span>
                        @endif

                        @if($item->is_best_deal && $item->seller_count > 1)
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

                    {{-- Minimalistic Card Body: Title + Clean Unboxed Price Breakdown + Quiet Seller Line --}}
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

                            {{-- Minimalistic Unboxed Total Price Breakdown --}}
                            <div class="pt-1.5 border-t border-slate-200/70 dark:border-[var(--border)]/50 space-y-0.5 text-[10px] sm:text-[11px]">
                                <div class="flex items-center justify-between text-[var(--muted-foreground)] gap-1">
                                    <span>+৳{{ number_format((float) $quote['delivery_fee'], 0) }} delivery</span>
                                    <span class="hidden sm:inline truncate">{{ $quote['eta_label'] }}</span>
                                </div>
                                <div class="flex items-center justify-between text-[11px] sm:text-xs">
                                    <span class="text-[var(--muted-foreground)]">Total</span>
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

<script>
window.spNavigateFilterUrl = function (targetUrl) {
    if (!targetUrl) return;
    if (window.MeramotSoftNav && typeof window.MeramotSoftNav.visit === 'function') {
        window.MeramotSoftNav.visit(targetUrl, { push: true });
    } else {
        window.location.href = targetUrl;
    }
};

window.meramotUseGpsForSpareParts = function (btn) {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser. Please use Pin on Map.');
        return;
    }
    const origText = btn ? btn.innerHTML : '◎ Near Me';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳...';
    }
    navigator.geolocation.getCurrentPosition(
        function (pos) {
            const lat = pos.coords.latitude.toFixed(6);
            const lng = pos.coords.longitude.toFixed(6);
            const url = new URL(window.location.href);
            url.searchParams.set('lat', lat);
            url.searchParams.set('lng', lng);
            url.searchParams.delete('city');
            url.searchParams.delete('area');
            url.searchParams.delete('page');
            if (window.MeramotSoftNav && typeof window.MeramotSoftNav.visit === 'function') {
                window.MeramotSoftNav.visit(url.toString(), { push: true });
            } else {
                window.location.href = url.toString();
            }
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText;
            }
        },
        function () {
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = origText;
            }
            if (window.openMeramotLocationModal) {
                window.openMeramotLocationModal();
            }
        },
        { enableHighAccuracy: true, timeout: 8000 }
    );
};
</script>
@endsection

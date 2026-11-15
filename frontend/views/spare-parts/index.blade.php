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

    $authVendorProfileId = null;
    if (auth()->check() && auth()->user()->role === 'vendor') {
        $authVendorProfileId = \App\Models\VendorProfile::where('user_id', auth()->id())->value('id');
    }
@endphp

<div class="max-w-7xl mx-auto px-3 sm:px-6 lg:px-8 py-5 sm:py-7">
    {{-- Breadcrumb & Header --}}
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-3 mb-4">
        <div>
            <nav class="text-xs font-medium text-emerald-900/70 mb-1 flex items-center gap-1.5">
                <a href="{{ url('/') }}" class="hover:text-emerald-950 transition">Home</a>
                <span>/</span>
                <span class="text-emerald-950 font-semibold">Spare Parts Marketplace</span>
            </nav>
            <div class="flex flex-wrap items-center gap-2.5">
                <h1 class="text-2xl sm:text-3xl font-extrabold tracking-tight text-emerald-950">
                    Spare Parts &amp; Components Marketplace
                </h1>
                <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-900 text-emerald-50">
                    {{ number_format($totalListingsCount) }} Live Listings
                </span>
            </div>
            <p class="text-xs sm:text-sm text-emerald-950/75 mt-1">
                Compare genuine mobile, laptop &amp; PC parts across verified workshops — ranked by <strong class="font-semibold text-emerald-950">Item Price + Courier Delivery</strong> to your location, with progressive AliExpress-style wholesale discounts.
            </p>
        </div>

        @auth
            @if(auth()->user()->role === 'vendor')
                <div class="flex flex-wrap items-center gap-2 shrink-0">
                    <a href="{{ url('/orders') }}" data-no-soft-nav="true"
                       class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-full text-xs font-bold bg-white/85 text-emerald-950 border border-emerald-900/20 hover:bg-white transition shadow-sm">
                        📦 My Spare Part Orders
                    </a>
                    <a href="{{ route('vendor.dashboard', ['tab' => 'spare_parts']) }}" data-no-soft-nav="true"
                       class="inline-flex items-center gap-1.5 px-4 py-2 rounded-full text-xs font-bold bg-emerald-900 text-white hover:bg-emerald-800 transition shadow-sm">
                        + List My Spare Parts
                    </a>
                </div>
            @endif
        @endauth
    </div>

    {{-- Search + Deliver-To Location Best-Deal Bar --}}
    <div class="rounded-2xl bg-[#cde7c7]/90 border border-emerald-900/15 p-3 sm:p-4 mb-4 shadow-sm space-y-3">
        <div class="flex flex-col lg:flex-row gap-2.5">
            {{-- Dedicated Spare Parts Search --}}
            <form action="{{ url('/spare-parts') }}" method="GET" class="flex-1 flex items-center gap-2">
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
                <div class="relative flex-1">
                    <input type="text"
                           name="q"
                           value="{{ $search }}"
                           placeholder="Search displays, batteries, laptop keyboards, cooling fans, SSDs, models..."
                           class="w-full pl-4 pr-10 py-2.5 rounded-full bg-white/95 border border-emerald-900/20 text-sm text-emerald-950 placeholder-emerald-900/50 focus:outline-none focus:ring-2 focus:ring-emerald-800">
                    @if($search !== '')
                        <a href="{{ $buildFilterUrl(['q' => null]) }}"
                           class="absolute right-3 top-1/2 -translate-y-1/2 text-xs font-bold text-emerald-800 hover:text-red-600"
                           title="Clear search">✕</a>
                    @endif
                </div>
                <button type="submit"
                        class="px-4 py-2.5 rounded-full bg-emerald-900 text-white text-xs sm:text-sm font-bold hover:bg-emerald-800 transition shrink-0">
                    Search Parts
                </button>
            </form>

            {{-- Active Delivery Destination Control (Drives Best-Deal Landed Math) --}}
            <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                <div class="inline-flex items-center gap-1.5 px-3 py-2 rounded-full bg-white/90 border border-emerald-900/20 text-xs text-emerald-950">
                    <span class="text-emerald-700 font-bold">📍 Deliver to:</span>
                    <strong class="font-extrabold text-emerald-950 truncate max-w-[160px]" id="spActiveLocationLabel">{{ $locLabel }}</strong>
                </div>
                <button type="button"
                        onclick="if(window.openMeramotLocationModal){window.openMeramotLocationModal();}"
                        class="select-none px-3 py-2 rounded-full bg-emerald-900 text-white text-xs font-bold hover:bg-emerald-800 transition shrink-0">
                    🗺️ Pin on Map
                </button>
                <button type="button"
                        id="spNearMeBtn"
                        onclick="window.meramotUseGpsForSpareParts && window.meramotUseGpsForSpareParts(this)"
                        class="select-none px-3 py-2 rounded-full bg-white/90 border border-emerald-900/20 text-emerald-950 text-xs font-bold hover:bg-white transition shrink-0">
                    ◎ Near Me
                </button>
            </div>
        </div>

        {{-- Quick Delivery Destination Pills (Demonstrates Location-Dependent Best Deal!) --}}
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-0.5 select-none">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-950/75 shrink-0">Best Deal For:</span>
            @foreach($quickLocations as $ql)
                @php
                    $isLocActive = strcasecmp((string) ($customerLocation['city'] ?? ''), $ql['city']) === 0
                        && (empty($customerLocation['area']) || strcasecmp((string) $customerLocation['area'], $ql['area']) === 0 || strcasecmp((string) $customerLocation['area'], $ql['city']) === 0);
                @endphp
                <a href="{{ $buildFilterUrl(['city' => $ql['city'], 'area' => $ql['area'], 'lat' => $ql['lat'], 'lng' => $ql['lng']]) }}"
                   class="select-none shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $isLocActive ? 'bg-emerald-900 text-white border-emerald-900 shadow-sm' : 'bg-white/80 text-emerald-950 border-emerald-900/15 hover:bg-white' }}">
                    {{ $ql['label'] }}
                </a>
            @endforeach
        </div>
    </div>

    {{-- Horizontal Slider Filter Suite (Device Segment • Category • Brand • Sort & Promo Toggles) --}}
    <div class="rounded-2xl bg-[#cde7c7]/80 border border-emerald-900/15 p-3 sm:p-4 mb-5 space-y-2.5 select-none">
        {{-- Row 1: Device Segment --}}
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar pb-0.5">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-950/75 shrink-0">CATALOG:</span>
            @foreach([
                'all' => '🌐 All Parts (Mobile + PC)',
                'mobile' => '📱 Mobile & Gadget Parts',
                'pc' => '💻 Laptop & PC Parts',
            ] as $dtKey => $dtLabel)
                <a href="{{ $buildFilterUrl(['device_type' => $dtKey === 'all' ? null : $dtKey, 'part_category' => null]) }}"
                   class="select-none shrink-0 px-3.5 py-1.5 rounded-full text-xs font-bold border transition {{ $deviceType === $dtKey ? 'bg-emerald-900 text-white border-emerald-900 shadow-sm' : 'bg-white/85 text-emerald-950 border-emerald-900/15 hover:bg-white' }}">
                    {{ $dtLabel }}
                </a>
            @endforeach

            @if($selectedGroup !== '')
                <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-bold bg-amber-100 text-amber-950 border border-amber-300 shrink-0">
                    Comparing Sellers for 1 Item
                    <a href="{{ $buildFilterUrl(['group' => null]) }}" class="text-red-700 hover:underline ml-1">✕ Show All</a>
                </span>
            @endif
        </div>

        {{-- Row 2: Part Category Slider --}}
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-0.5">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-950/75 shrink-0">PART TYPE:</span>
            <a href="{{ $buildFilterUrl(['part_category' => null]) }}"
               class="select-none shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $selectedCategory === 'All' ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-white/80 text-emerald-950 border-emerald-900/15 hover:bg-white' }}">
                All
            </a>
            @foreach($partCategories as $cat)
                <a href="{{ $buildFilterUrl(['part_category' => $cat]) }}"
                   class="select-none shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ strcasecmp($selectedCategory, $cat) === 0 ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-white/80 text-emerald-950 border-emerald-900/15 hover:bg-white' }}">
                    {{ $cat }}
                </a>
            @endforeach
        </div>

        {{-- Row 3: Brand Slider --}}
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pb-0.5">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-950/75 shrink-0">BRAND:</span>
            <a href="{{ $buildFilterUrl(['brand' => null]) }}"
               class="select-none shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $selectedBrand === 'All' ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-white/80 text-emerald-950 border-emerald-900/15 hover:bg-white' }}">
                All
            </a>
            @foreach($popularBrands as $br)
                <a href="{{ $buildFilterUrl(['brand' => $br]) }}"
                   class="select-none shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ strcasecmp($selectedBrand, $br) === 0 ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-white/80 text-emerald-950 border-emerald-900/15 hover:bg-white' }}">
                    {{ $br }}
                </a>
            @endforeach
        </div>

        {{-- Row 4: Sort & Quick Filter Toggles --}}
        <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar pt-0.5">
            <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-950/75 shrink-0">SORT &amp; DEALS:</span>
            @foreach([
                'best_deal' => '🏆 Best Deal Near You',
                'lowest_price' => '🏷️ Lowest Part Price',
                'fastest_eta' => '⚡ Fastest Delivery',
                'popular' => '🔥 Top Sold',
                'wholesale' => '📦 Best Wholesale',
            ] as $sKey => $sLabel)
                <a href="{{ $buildFilterUrl(['sort' => $sKey === 'best_deal' ? null : $sKey]) }}"
                   class="select-none shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $sort === $sKey ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-white/80 text-emerald-950 border-emerald-900/15 hover:bg-white' }}">
                    {{ $sLabel }}
                </a>
            @endforeach

            <span class="text-emerald-900/30 px-0.5">|</span>

            <a href="{{ $buildFilterUrl(['on_sale' => $onSaleOnly ? null : '1']) }}"
               class="select-none shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $onSaleOnly ? 'bg-red-600 text-white border-red-600 shadow-sm' : 'bg-white/85 text-red-700 border-red-300 hover:bg-red-50' }}">
                🔴 Sale! Offers
            </a>

            <a href="{{ $buildFilterUrl(['wholesale' => $wholesaleOnly ? null : '1']) }}"
               class="select-none shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $wholesaleOnly ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-white/85 text-emerald-950 border-emerald-900/15 hover:bg-white' }}">
                📦 Wholesale (100+)
            </a>

            <a href="{{ $buildFilterUrl(['has_specs' => $specsOnly ? null : '1']) }}"
               class="select-none shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $specsOnly ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-white/85 text-emerald-950 border-emerald-900/15 hover:bg-white' }}">
                🎛️ Multi-Spec Grades
            </a>

            <a href="{{ $buildFilterUrl(['local_only' => $localOnly ? null : '1']) }}"
               class="select-none shrink-0 px-3 py-1 rounded-full text-xs font-bold border transition {{ $localOnly ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-white/85 text-emerald-950 border-emerald-900/15 hover:bg-white' }}">
                📍 Same-City Sellers ({{ $customerLocation['city'] ?? 'Dhaka' }})
            </a>

            @if($search !== '' || $selectedCategory !== 'All' || $selectedBrand !== 'All' || $deviceType !== 'all' || $selectedGroup !== '' || $wholesaleOnly || $specsOnly || $localOnly || $onSaleOnly || $sort !== 'best_deal')
                <a href="{{ url('/spare-parts') }}"
                   class="select-none shrink-0 px-2.5 py-1 text-xs font-extrabold text-red-700 hover:underline">
                    Reset
                </a>
            @endif
        </div>
    </div>

    {{-- Result Summary Bar --}}
    <div class="flex flex-wrap items-center justify-between gap-2 mb-4 text-xs sm:text-sm text-emerald-950/85">
        <div>
            Showing <strong>{{ number_format($listings->firstItem() ?? 0) }}–{{ number_format($listings->lastItem() ?? 0) }}</strong>
            of <strong>{{ number_format($totalListingsCount) }}</strong> spare part listings
            <span class="text-emerald-900/65">({{ number_format($distinctProductCount) }} unique parts)</span>
            • Delivery quotes calculated for <strong class="text-emerald-950">{{ $locLabel }}</strong>
        </div>
        @if($selectedGroup !== '')
            <a href="{{ $buildFilterUrl(['group' => null]) }}" class="text-xs font-bold text-emerald-900 underline">
                ← Back to all spare parts
            </a>
        @endif
    </div>

    {{-- Spare Part Item Grid --}}
    @if($listings->isEmpty())
        <div class="rounded-2xl bg-white/85 border border-emerald-900/15 p-10 text-center">
            <div class="text-3xl mb-2">🔩</div>
            <h2 class="text-lg font-bold text-emerald-950">No spare part listings matched your filters</h2>
            <p class="text-sm text-emerald-900/70 mt-1 mb-4">Try clearing filters or switching from Same-City Sellers to nationwide delivery.</p>
            <a href="{{ url('/spare-parts') }}" class="inline-flex items-center px-5 py-2.5 rounded-full bg-emerald-900 text-white text-xs font-bold hover:bg-emerald-800 transition">
                Browse All Spare Parts
            </a>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4">
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

                <article class="group rounded-2xl bg-white/92 border {{ $item->is_best_deal ? 'border-emerald-700 ring-1 ring-emerald-700/35' : 'border-emerald-900/15' }} hover:border-emerald-800 hover:shadow-md transition flex flex-col overflow-hidden">
                    {{-- Product Image with Circular Red "Sale!" Banner on Top-Left Corner --}}
                    <a href="{{ $detailUrl }}" data-no-soft-nav="true" class="relative block aspect-[4/3] bg-white border-b border-emerald-900/10 overflow-hidden p-3">
                        @if($offerActive)
                            <span class="absolute top-2.5 left-2.5 z-10 w-11 h-11 rounded-full bg-red-600 text-white text-[11px] font-extrabold tracking-tight flex items-center justify-center shadow-md select-none"
                                  title="{{ $item->offerWindowLabel() }}">
                                Sale!
                            </span>
                        @endif

                        @if($item->is_best_deal && $item->seller_count > 1)
                            <span class="absolute top-2.5 right-2.5 z-10 px-2.5 py-1 rounded-full bg-emerald-900 text-white text-[10px] font-extrabold shadow-sm">
                                ★ Best Deal Near You
                            </span>
                        @elseif($isOwnVendorListing)
                            <span class="absolute top-2.5 right-2.5 z-10 px-2.5 py-1 rounded-full bg-amber-600 text-white text-[10px] font-extrabold shadow-sm">
                                Your Listing
                            </span>
                        @endif

                        <img src="{{ $item->image_url }}"
                             alt="{{ $item->title }}"
                             loading="lazy"
                             onerror="this.onerror=null;this.src='/images/spare-parts/samsung-galaxy-s21-fe-5g-display.webp';"
                             class="w-full h-full object-contain group-hover:scale-105 transition duration-300">
                    </a>

                    <div class="p-3.5 flex-1 flex flex-col justify-between gap-2.5">
                        <div class="space-y-1.5">
                            {{-- Category & Brand Meta --}}
                            <div class="flex items-center justify-between gap-1.5 text-[11px]">
                                <span class="font-bold text-emerald-800 truncate">{{ $item->part_category }}</span>
                                <span class="px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-900 font-semibold border border-emerald-900/10 shrink-0">
                                    {{ $item->brand }}
                                </span>
                            </div>

                            {{-- Item Title --}}
                            <h2 class="text-sm font-extrabold text-emerald-950 leading-snug line-clamp-2 min-h-[2.5rem]">
                                <a href="{{ $detailUrl }}" data-no-soft-nav="true" class="hover:text-emerald-700 transition">
                                    {{ $item->title }}
                                </a>
                            </h2>

                            {{-- Attached Seller Shop & Branch Badge (eBay / AliExpress Seller Row) --}}
                            @if($shop)
                                <div class="rounded-xl bg-emerald-50/80 border border-emerald-900/10 px-2.5 py-1.5 text-[11px] text-emerald-950">
                                    <div class="flex items-center gap-1 flex-wrap font-bold">
                                        <span class="text-emerald-700" title="Verified Meramot Workshop">✔</span>
                                        <span class="truncate max-w-[145px]">{{ $shop->name }}</span>
                                        @if($branchTag)
                                            <span class="px-1.5 py-0.5 rounded bg-emerald-900 text-white text-[10px] font-bold leading-none">
                                                {{ $branchTag }}
                                            </span>
                                        @endif
                                    </div>
                                    <div class="flex items-center justify-between text-[10px] text-emerald-900/75 mt-0.5">
                                        <span>📍 {{ collect([$shop->area, $shop->city])->filter()->unique()->implode(', ') }}</span>
                                        <span>★ {{ number_format((float) ($shop->rating ?: 4.8), 1) }} · {{ $quote['distance_formatted'] }}</span>
                                    </div>
                                </div>
                            @endif

                            {{-- Item Price / Spec Range + Strikethrough Offer Price --}}
                            <div class="pt-0.5">
                                @if($item->has_specs && $effMax && $effMax > $effBase)
                                    <div class="flex items-baseline gap-1.5 flex-wrap">
                                        @if($offerActive && $normalBase > $effBase)
                                            <span class="text-xs font-semibold text-slate-400 line-through">৳{{ number_format($normalBase, 0) }}</span>
                                        @endif
                                        <span class="text-base font-extrabold {{ $offerActive ? 'text-red-600' : 'text-emerald-950' }}">
                                            ৳{{ number_format($effBase, 0) }} – ৳{{ number_format($effMax, 0) }}
                                        </span>
                                    </div>
                                    <div class="text-[11px] font-semibold text-emerald-800">
                                        {{ $item->spec_label ?: 'Quality' }}: {{ count($item->spec_options ?? []) }} spec options
                                    </div>
                                @else
                                    <div class="flex items-baseline gap-2 flex-wrap">
                                        @if($offerActive && $normalBase > $effBase)
                                            <span class="text-xs font-semibold text-slate-400 line-through">৳{{ number_format($normalBase, 0) }}</span>
                                            <span class="text-lg font-extrabold text-red-600">৳{{ number_format($effBase, 0) }}</span>
                                            <span class="px-1.5 py-0.5 rounded bg-red-50 text-red-700 border border-red-200 text-[10px] font-bold">
                                                -{{ number_format($item->effectiveOfferPercent(), 0) }}%
                                            </span>
                                        @else
                                            <span class="text-lg font-extrabold text-emerald-950">৳{{ number_format($effBase, 0) }}</span>
                                        @endif
                                    </div>
                                    @if($offerActive && $item->offerWindowLabel())
                                        <div class="text-[10px] font-semibold text-red-700">{{ $item->offerWindowLabel() }}</div>
                                    @endif
                                @endif
                            </div>

                            {{-- AliExpress Wholesale Discount Badge --}}
                            @if($item->wholesale_enabled)
                                <div class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md bg-amber-50 border border-amber-300/80 text-amber-950 text-[10px] font-bold">
                                    📦 Wholesale: {{ rtrim(rtrim(number_format((float) $item->wholesale_base_discount_pct, 2), '0'), '.') }}% off ≥{{ $item->wholesale_min_qty }} pcs (+{{ rtrim(rtrim(number_format((float) $item->wholesale_step_discount_pct, 2), '0'), '.') }}%/{{ $item->wholesale_step_qty }})
                                </div>
                            @endif
                        </div>

                        {{-- Location-Based Delivery Cost + Landed Total Comparison Box --}}
                        <div class="pt-2 border-t border-emerald-900/10 space-y-2">
                            <div class="rounded-xl px-2.5 py-1.5 {{ $item->is_best_deal ? 'bg-emerald-900 text-white' : 'bg-slate-100 text-emerald-950' }}">
                                <div class="flex items-center justify-between text-[11px]">
                                    <span>🚚 +৳{{ number_format((float) $quote['delivery_fee'], 0) }} courier</span>
                                    <span class="font-semibold">{{ $quote['eta_label'] }}</span>
                                </div>
                                <div class="flex items-center justify-between text-xs font-extrabold mt-0.5">
                                    <span>Total w/ Delivery:</span>
                                    <span>{{ $item->has_specs ? 'from ' : '' }}৳{{ number_format((float) $quote['landed_total'], 0) }}</span>
                                </div>
                                @if($item->is_cheapest_raw_but_far)
                                    <div class="text-[10px] text-amber-800 font-semibold mt-0.5">
                                        Cheaper part price, but higher courier fee to {{ $customerLocation['city'] ?? 'Dhaka' }}
                                    </div>
                                @endif
                            </div>

                            <div class="flex items-center justify-between gap-2">
                                @if($item->seller_count > 1 && $selectedGroup === '')
                                    <a href="{{ $buildFilterUrl(['group' => $item->product_group_key]) }}"
                                       class="text-[11px] font-bold text-emerald-800 hover:text-emerald-950 underline">
                                        Compare {{ $item->seller_count }} sellers
                                    </a>
                                @else
                                    <span class="text-[11px] text-emerald-900/65">{{ number_format((int) $item->sold_count) }} sold</span>
                                @endif

                                <a href="{{ $detailUrl }}" data-no-soft-nav="true"
                                   class="px-3 py-1.5 rounded-full bg-emerald-900 text-white text-xs font-bold hover:bg-emerald-800 transition shrink-0">
                                    {{ $item->has_specs ? 'Choose Specs →' : 'View / Buy →' }}
                                </a>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>

        {{-- Pagination --}}
        @if($listings->hasPages())
            <div class="mt-7">
                {{ $listings->links() }}
            </div>
        @endif
    @endif
</div>

<script>
window.meramotUseGpsForSpareParts = function (btn) {
    if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser. Please use Pin on Map.');
        return;
    }
    const origText = btn ? btn.innerHTML : '◎ Near Me';
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳ Locating...';
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

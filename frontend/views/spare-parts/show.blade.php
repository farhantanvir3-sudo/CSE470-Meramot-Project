@extends('layouts.app')

@section('title', $listing->title . ' — Spare Parts Marketplace | Meramot')

@section('content')
@php
    $shop = $listing->shop;
    $branchTag = $shop?->display_branch_tag;
    $locLabel = collect([$customerLocation['area'] ?? null, $customerLocation['city'] ?? 'Dhaka'])
        ->filter()
        ->unique()
        ->implode(', ') ?: 'Banani, Dhaka';

    $offerActive = $listing->isOfferActive();
    $offerWindow = $listing->offerWindowLabel();
    $effBase = $listing->effectiveBasePrice();
    $effMax = $listing->effectiveMaxPrice();
    $normalBase = $listing->resolvedNormalBasePrice();
    $normalMax = $listing->normal_max_price !== null ? (float) $listing->normal_max_price : null;

    $gallery = collect([$listing->image_url])
        ->merge(is_array($listing->gallery_urls) ? $listing->gallery_urls : [])
        ->filter(fn ($u) => is_string($u) && trim($u) !== '')
        ->unique()
        ->values()
        ->all();

    if (empty($gallery)) {
        $gallery = [$listing->fallbackImageUrl()];
    }

    $rawHighlights = is_array($listing->highlights) ? $listing->highlights : [];
    $highlights = [];
    foreach ($rawHighlights as $rawHl) {
        $hlText = trim((string) preg_replace('/\s+/', ' ', (string) $rawHl));
        if ($hlText === '' || preg_match('/^[^:]+:\s*$/', $hlText) === 1) {
            continue;
        }
        $hlText = (string) preg_replace('/\s*:\s*/', ': ', $hlText, 1);
        if (preg_match('/^(?:Assemble|Service)\s+Charge\s*:?\s*(\d+)\s*tk\b.*/i', $hlText, $m) === 1) {
            $hlText = 'Optional Workshop Installation: ৳' . number_format((int) $m[1]) . ' per unit';
        } elseif (preg_match('/^Delivery:\s*Only\s+Bangladesh$/i', $hlText) === 1) {
            $hlText = 'Nationwide Courier Delivery Available';
        }
        $highlights[] = $hlText;
    }
    if (empty($highlights)) {
        $highlights = array_values(array_filter([
            'Condition: ' . ($listing->condition_label ?: 'Brand New'),
            $listing->compatible_model ? ('Compatible Model: ' . $listing->compatible_model) : null,
            'Brand: ' . ($listing->brand ?: 'Universal'),
            $listing->warranty_days > 0
                ? ('Replacement Warranty: ' . $listing->warranty_days . ' Days')
                : 'Quality-Inspected & Tested Prior to Dispatch',
        ]));
    }

    $authRole = auth()->check() ? auth()->user()->role : null;
    $authVendorProfileId = null;
    if ($authRole === 'vendor') {
        $authVendorProfileId = \App\Models\VendorProfile::where('user_id', auth()->id())->value('id');
    }
    $isOwnVendorListing = $authVendorProfileId && $shop && ((int) $shop->vendor_profile_id === (int) $authVendorProfileId);

    $initialQty = max(1, min((int) ($listing->stock_quantity ?: 1), (int) request('qty', 1)));
    $deliveryFee = (float) ($activeQuote['delivery_fee'] ?? 60);
    $commissionRatePct = (float) ($commissionRatePct ?? \App\Services\MarketplacePricingService::resolveOrderFeeRatePct($shop, $customerLocation ?? [], [
        'item_type' => 'spare_part',
        'part_category' => $listing->part_category,
    ]));
    $initialSubtotal = (float) ($activeQuote['subtotal'] ?? ($effBase * $initialQty));
    $initialPlatformFee = $initialSubtotal > 0 ? round($initialSubtotal * ($commissionRatePct / 100), 2) : 0.0;
    $initialCheckoutTotal = round($initialSubtotal + $initialPlatformFee + $deliveryFee, 2);

    $deviceSegment = $deviceSegment ?? (
        in_array($listing->part_category, \App\Http\Controllers\SparePartController::PC_CATEGORIES, true)
            ? ['key' => 'pc', 'label' => 'Laptop & PC Parts']
            : (in_array($listing->part_category, \App\Http\Controllers\SparePartController::MOBILE_CATEGORIES, true)
                ? ['key' => 'mobile', 'label' => 'Mobile Parts']
                : ['key' => 'others', 'label' => 'Repair Tools & Soldering'])
    );

    $supportsInstallation = $listing->supportsWorkshopInstallation();
    $installationFeePerUnit = $supportsInstallation ? round((float) $listing->assembly_fee, 2) : 0.0;
    $maxInstallQty = $listing->maxInstallationQuantity();

    $jsPricingConfig = [
        'hasSpecs' => (bool) $listing->has_specs,
        'specLabel' => $listing->spec_label ?: 'Quality',
        'specOptions' => array_values($specOptions),
        'selectedSpecCode' => $selectedSpec['code'] ?? '',
        'effBasePrice' => (float) $effBase,
        'effMaxPrice' => $effMax !== null ? (float) $effMax : null,
        'normalBasePrice' => (float) $normalBase,
        'normalMaxPrice' => $normalMax,
        'offerActive' => (bool) $offerActive,
        'wholesaleEnabled' => (bool) $listing->wholesale_enabled,
        'wholesaleMinQty' => max(2, (int) ($listing->wholesale_min_qty ?: 100)),
        'wholesaleBasePct' => (float) ($listing->wholesale_base_discount_pct ?? 5),
        'wholesaleStepQty' => max(1, (int) ($listing->wholesale_step_qty ?: 100)),
        'wholesaleStepPct' => (float) ($listing->wholesale_step_discount_pct ?? 2),
        'wholesaleMaxPct' => (float) ($listing->wholesale_max_discount_pct ?? 25),
        'supportsInstallation' => $supportsInstallation,
        'installationFeePerUnit' => $installationFeePerUnit,
        'maxInstallQty' => $maxInstallQty,
        'commissionRatePct' => $commissionRatePct,
        'deliveryFee' => $deliveryFee,
        'maxStock' => max(0, (int) $listing->stock_quantity),
    ];
@endphp

<div class="max-w-7xl mx-auto px-2.5 sm:px-6 lg:px-8 py-3 sm:py-6">
    {{-- Top Destination Bar (Clean single location pin — no redundant quick city pills) --}}
    <div class="rounded-lg bg-[var(--card)] border border-[var(--border)] px-3 py-2 sm:px-4 sm:py-2.5 mb-3.5 sm:mb-5 flex flex-wrap items-center justify-between gap-2 shadow-xs">
        <div class="flex flex-wrap items-center gap-2 text-[11px] sm:text-xs text-[var(--foreground)] min-w-0">
            <div class="flex items-center gap-1.5 min-w-0">
                <span class="font-semibold text-emerald-800 shrink-0 inline-flex items-center gap-1"><i class="fa-solid fa-location-dot text-emerald-700"></i> Pickup &amp; Delivery To:</span>
                <strong class="px-2 py-0.5 rounded bg-[var(--mint-50)] border border-[var(--border)] font-semibold truncate max-w-[160px] sm:max-w-none">{{ $locLabel }}</strong>
            </div>
            <span class="text-[var(--muted-foreground)] shrink-0 tabular-nums inline-flex items-center gap-1">
                <i class="fa-solid fa-truck text-[11px]"></i> Platform Courier: <strong class="text-[var(--foreground)] font-semibold">৳{{ number_format($deliveryFee, 0) }}</strong>
                <span class="hidden sm:inline">({{ $activeQuote['distance_formatted'] ?? '—' }}, ETA {{ $activeQuote['eta_label'] ?? 'Same day' }})</span>
                <span class="sm:hidden">({{ $activeQuote['eta_label'] ?? 'Same day' }})</span>
            </span>
        </div>
        <button type="button"
                onclick="if(window.openMeramotLocationModal){window.openMeramotLocationModal();}"
                class="shrink-0 inline-flex items-center gap-1.5 px-3 py-1 rounded-md bg-emerald-900 text-white text-[11px] font-semibold hover:bg-emerald-800 transition">
            <i class="fa-solid fa-location-dot text-[10px]"></i><span>Change Location</span>
        </button>
    </div>

    {{-- Main 2-Column Product Showcase --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-3.5 sm:gap-6">
        {{-- Left Column: Product Gallery + Seller Card (Desktop) --}}
        <div class="lg:col-span-5 space-y-4">
            <div class="rounded-lg bg-white border border-emerald-900/15 p-3 sm:p-4 shadow-xs">
                {{-- Main Image Frame with Compact Red "Sale!" Tag on Top-Left --}}
                <div class="relative aspect-[4/3] sm:aspect-square rounded-md bg-white border border-emerald-900/10 overflow-hidden flex items-center justify-center p-3 sm:p-4">
                    @if($offerActive)
                        <span id="spSaleCircleBadge"
                              class="absolute top-2.5 left-2.5 sm:top-3 sm:left-3 z-20 rounded px-2 py-0.5 bg-red-600 text-white text-[11px] font-semibold tracking-tight leading-none shadow-sm select-none"
                              title="{{ $offerWindow }}">
                            Sale!
                        </span>
                    @endif

                    @if(!empty($hasActiveSearchQuery) && $listing->is_best_deal && $competingListings->count() > 1)
                        <span class="absolute top-2.5 right-2.5 sm:top-3 sm:right-3 z-20 px-2.5 py-1 rounded bg-emerald-900 text-white text-[10px] sm:text-xs font-semibold shadow-sm">
                            Best Deal
                        </span>
                    @endif

                    <img id="spMainProductImage"
                         src="{{ $gallery[0] }}"
                         alt="{{ $listing->title }}"
                         onerror="this.onerror=null;this.src='{{ e($listing->fallbackImageUrl()) }}';"
                         class="w-full h-full object-contain transition duration-300">
                </div>

                {{-- Thumbnail Strip --}}
                @if(count($gallery) > 1)
                    <div class="flex items-center gap-2.5 mt-3 overflow-x-auto no-scrollbar pb-1">
                        @foreach($gallery as $gIdx => $gUrl)
                            <button type="button"
                                    onclick="window.spSwitchGalleryImage('{{ e($gUrl) }}', this)"
                                    class="sp-thumb-btn w-16 h-16 rounded-xl border-2 {{ $gIdx === 0 ? 'border-emerald-800 ring-2 ring-emerald-700/25' : 'border-emerald-900/15 opacity-80 hover:opacity-100' }} bg-white p-1.5 shrink-0 overflow-hidden transition">
                                <img src="{{ $gUrl }}"
                                     alt="Thumbnail {{ $gIdx + 1 }}"
                                     onerror="this.onerror=null;this.src='{{ e($listing->fallbackImageUrl()) }}';"
                                     class="w-full h-full object-contain">
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>

            {{-- Attached Seller / Workshop Card (Desktop Left Column) --}}
            @if($shop)
                <div class="hidden lg:block rounded-lg bg-white/95 border border-emerald-900/15 p-4 shadow-sm space-y-3">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-[11px] font-semibold uppercase tracking-wider text-emerald-800">Sold &amp; Dispatched By</div>
                            <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                <span class="text-emerald-700 font-bold">✔</span>
                                <h2 class="text-base font-semibold text-emerald-950">{{ $shop->name }}</h2>
                                @if($branchTag)
                                    <span class="px-2 py-0.5 rounded-md bg-emerald-900 text-white text-[11px] font-bold">
                                        {{ $branchTag }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-emerald-900/75 mt-1">
                                📍 {{ collect([$shop->address, $shop->area, $shop->city])->filter()->unique()->implode(', ') }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <div class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-amber-50 border border-amber-200 text-xs font-semibold text-amber-900">
                                ★ {{ ((float) ($shop->rating ?? 0)) > 0 ? number_format((float) $shop->rating, 1) : '4.8' }}
                            </div>
                            <div class="text-[11px] text-emerald-900/70 mt-1">
                                {{ $activeQuote['distance_formatted'] ?? '' }} away
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-3 gap-2 pt-2 border-t border-emerald-900/10 text-center text-xs">
                        <div class="rounded-xl bg-emerald-50/70 p-2">
                            <div class="text-[10px] text-emerald-900/70 font-semibold">Dispatch</div>
                            <div class="font-semibold text-emerald-950">~{{ max(1, (int) $listing->handling_hours) }}h</div>
                        </div>
                        <div class="rounded-xl bg-emerald-50/70 p-2">
                            <div class="text-[10px] text-emerald-900/70 font-semibold">Courier Fee</div>
                            <div class="font-semibold text-emerald-950">৳{{ number_format($deliveryFee, 0) }}</div>
                        </div>
                        <div class="rounded-xl bg-emerald-50/70 p-2">
                            <div class="text-[10px] text-emerald-900/70 font-semibold">Warranty</div>
                            <div class="font-semibold text-emerald-950">
                                {{ $listing->warranty_days > 0 ? ($listing->warranty_days . ' Days') : 'Check-on-Delivery' }}
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 pt-1">
                        <a href="{{ route('spare-parts.index', ['seller_shop_id' => $shop->id]) }}"
                           class="flex-1 text-center px-3 py-2 rounded-md bg-emerald-50 hover:bg-emerald-100 text-emerald-950 text-xs font-bold border border-emerald-900/15 transition">
                            🔩 All Parts from Seller
                        </a>
                        <a href="{{ route('shops.show', ['slug' => $shop->slug]) }}"
                           class="flex-1 text-center px-3 py-2 rounded-md bg-white hover:bg-emerald-50 text-emerald-950 text-xs font-bold border border-emerald-900/20 transition">
                            🏪 Workshop Profile
                        </a>
                    </div>
                </div>
            @endif
        </div>

        {{-- Right Column: Specs, Offer Price, Wholesale Tiers & Order Form --}}
        <div class="lg:col-span-7 space-y-4">
            <div class="rounded-lg bg-white/95 border border-emerald-900/15 p-4 sm:p-6 shadow-sm space-y-4">
                {{-- Compact Horizontally-Slidable Folder-Path Breadcrumb (Mobile & Desktop Friendly) --}}
                @php
                    $inferredSubCategory = \App\Http\Controllers\SparePartController::inferSubCategoryForListing($listing);
                @endphp
                <nav aria-label="Category folder path"
                     class="flex items-center gap-1.5 overflow-x-auto no-scrollbar whitespace-nowrap px-3 py-1.5 rounded-xl bg-emerald-950/[0.04] border border-emerald-950/10 text-[11px] sm:text-xs font-mono text-emerald-950">
                    <a href="{{ route('spare-parts.index') }}" class="shrink-0 font-bold text-emerald-900 hover:underline">
                        📁 Spare Parts
                    </a>
                    <span class="text-emerald-900/40 shrink-0">/</span>
                    <a href="{{ route('spare-parts.index', ['device_type' => $deviceSegment['key']]) }}" class="shrink-0 font-semibold text-emerald-900/85 hover:underline">
                        {{ $deviceSegment['label'] }}
                    </a>
                    <span class="text-emerald-900/40 shrink-0">/</span>
                    <a href="{{ route('spare-parts.index', ['part_category' => $listing->part_category]) }}" class="shrink-0 font-bold text-emerald-950 hover:underline">
                        {{ $listing->part_category }}
                    </a>
                    @if($inferredSubCategory)
                        <span class="text-emerald-900/40 shrink-0">/</span>
                        <a href="{{ route('spare-parts.index', ['part_category' => $listing->part_category, 'sub_category' => $inferredSubCategory['key']]) }}" class="shrink-0 font-bold text-emerald-900/90 hover:underline">
                            {{ $inferredSubCategory['label'] }}
                        </a>
                    @endif
                    <span class="text-emerald-900/40 shrink-0">/</span>
                    <a href="{{ route('spare-parts.index', array_filter(['part_category' => $listing->part_category, 'sub_category' => $inferredSubCategory['key'] ?? null, 'brand' => $listing->brand])) }}" class="shrink-0 font-semibold text-emerald-900 hover:underline">
                        {{ $listing->brand }}
                    </a>
                    @if($listing->sku)
                        <span class="text-emerald-900/40 shrink-0">/</span>
                        <span class="shrink-0 text-emerald-900/65">SKU: {{ $listing->sku }}</span>
                    @endif
                </nav>

                {{-- Title --}}
                <h1 class="text-xl sm:text-2xl font-semibold text-emerald-950 leading-snug">
                    {{ $listing->title }}
                </h1>

                {{-- Sold Count + Stock Pill (LaptopWorld / Etel Style) --}}
                <div class="flex flex-wrap items-center gap-2.5">
                    <span class="inline-flex items-center px-3 py-1 rounded-md bg-amber-300/90 text-emerald-950 text-xs font-semibold shadow-xs">
                        Total sold - {{ number_format((int) $listing->sold_count) }}
                    </span>

                    @if($isOwnVendorListing && (int) ($listing->returned_count ?? 0) > 0)
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-slate-100 text-slate-800 text-xs font-semibold border border-slate-300">
                            Returned - {{ number_format((int) $listing->returned_count) }}
                        </span>
                    @endif

                    @if($listing->stock_quantity > 0)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-100 text-emerald-900 text-xs font-bold">
                            ● In Stock (<span id="spStockCountLabel">{{ number_format((int) $listing->stock_quantity) }}</span> available)
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-red-100 text-red-800 text-xs font-bold">
                            ● Out of Stock
                        </span>
                    @endif

                    @if($competingListings->count() > 1)
                        <a href="#compare-sellers"
                           onclick="event.preventDefault(); const el = document.getElementById('compare-sellers'); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });"
                           class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md bg-emerald-50 border border-emerald-900/15 text-emerald-900 text-xs font-bold hover:bg-emerald-100 transition">
                            ⚖️ {{ $competingListings->count() }} Sellers from ৳{{ number_format($competingListings->min(fn($c) => $c->effectiveBasePrice()), 0) }}
                        </a>
                    @endif
                </div>

                {{-- Price & Offer Banner Block --}}
                <div class="rounded-lg {{ $offerActive ? 'bg-red-50/70 border-red-200' : 'bg-emerald-50/70 border-emerald-900/15' }} border p-4 space-y-1.5">
                    @if($offerActive)
                        <div class="flex flex-wrap items-center gap-2 mb-1">
                            <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded bg-red-600 text-white text-[11px] font-semibold uppercase tracking-wide">
                                🔴 Sale! {{ number_format($listing->effectiveOfferPercent(), 0) }}% Off
                            </span>
                            <span class="text-xs font-bold text-red-800">
                                {{ $offerWindow }}
                            </span>
                        </div>
                    @endif

                    <div id="spPriceDisplayRow" class="flex items-baseline gap-2.5 flex-wrap">
                        @if($listing->has_specs && !$selectedSpec && $effMax && $effMax > $effBase)
                            @if($offerActive && $normalBase > $effBase)
                                <span class="text-sm sm:text-base font-semibold text-slate-400 line-through">
                                    ৳{{ number_format($normalBase, 2) }}{{ $normalMax && $normalMax > $normalBase ? (' – ৳' . number_format($normalMax, 2)) : '' }}
                                </span>
                            @endif
                            <span class="text-2xl sm:text-3xl font-semibold {{ $offerActive ? 'text-red-600' : 'text-emerald-950' }}">
                                ৳{{ number_format($effBase, 2) }} – ৳{{ number_format($effMax, 2) }}
                            </span>
                        @else
                            @php
                                $dispPrice = $selectedSpec ? (float) $selectedSpec['price'] : $effBase;
                                $dispNormal = $selectedSpec ? (float) ($selectedSpec['normal_price'] ?? $dispPrice) : $normalBase;
                            @endphp
                            @if($offerActive && $dispNormal > $dispPrice)
                                <span class="text-base sm:text-lg font-semibold text-slate-400 line-through">
                                    ৳{{ number_format($dispNormal, 2) }}
                                </span>
                            @endif
                            <span class="text-2xl sm:text-3xl font-semibold {{ ($offerActive && $dispNormal > $dispPrice) ? 'text-red-600' : 'text-emerald-950' }}">
                                ৳{{ number_format($dispPrice, 2) }}
                            </span>
                        @endif
                        <span class="text-xs font-semibold text-emerald-900/70" id="spPerUnitHint">per unit</span>
                    </div>

                    @if(!empty($hasActiveSearchQuery) && !$listing->is_best_deal && $bestDealListing)
                        <div class="pt-1 text-xs text-amber-900 font-semibold flex flex-wrap items-center gap-2">
                            <span>Lower landed offer available from <strong>{{ $bestDealListing->shop?->name }}</strong> at <strong>৳{{ number_format($bestDealListing->landed_quote['landed_total'], 0) }}</strong> including delivery.</span>
                            <a href="{{ route('spare-parts.show', array_filter(['slug' => $bestDealListing->slug, 'q' => $activeSearchQuery ?? null])) }}" class="underline font-semibold text-emerald-900">
                                View Best Deal →
                            </a>
                        </div>
                    @endif
                </div>

                {{-- Highlights Card (Etel & LaptopWorld Style) --}}
                <div class="rounded-lg bg-white border border-emerald-900/15 p-3.5 sm:p-4">
                    <div class="text-xs font-semibold uppercase tracking-wider text-emerald-900 mb-2">Highlights &amp; Specifications</div>
                    <ul class="space-y-1.5 text-xs sm:text-sm text-emerald-950">
                        @foreach($highlights as $hl)
                            <li class="flex items-start gap-2">
                                <span class="text-emerald-700 font-bold mt-0.5">•</span>
                                <span>{{ $hl }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                {{-- Separate Meramot Warranty Protection Registry Card --}}
                @php
                    $spWarranty = $listing->warrantyCoverage;
                    $effWarrantyDays = (int) ($spWarranty?->duration_days ?? $listing->warranty_days ?? 0);
                @endphp
                @if($spWarranty || $effWarrantyDays > 0)
                    @php
                        $spRemedies = $spWarranty
                            ? $spWarranty->remedyBadges()
                            : ['Replacement', 'Free Repair', 'Refund'];
                        $spCovLabel = $spWarranty?->coverageLabel() ?? 'Full Protection (Replacement, Free Repair or Refund)';
                        $spTermsText = $spWarranty?->terms ?: 'Covers manufacturing defects and hardware failure within the warranty window; assessed by Meramot Platform Admin.';
                    @endphp
                    <div class="rounded-lg bg-emerald-50/75 border border-emerald-700/25 p-3.5 sm:p-4 space-y-2" data-listing-warranty-card>
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <div class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-950">
                                <i class="fa-solid fa-shield-halved text-emerald-700"></i>
                                <span>Meramot Verified {{ $effWarrantyDays }}-Day Warranty</span>
                            </div>
                            <span class="rounded bg-emerald-900 px-2 py-0.5 text-[10px] font-bold text-white uppercase">
                                {{ $spCovLabel }}
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 text-[10px]">
                            <span class="text-emerald-900/75 font-semibold">Covered remedies:</span>
                            @foreach($spRemedies as $remLabel)
                                <span class="rounded bg-white border border-emerald-700/25 px-2 py-0.5 font-bold text-emerald-900">✓ {{ $remLabel }}</span>
                            @endforeach
                        </div>
                        <p class="text-[11px] text-emerald-950/80 leading-relaxed">
                            {{ $spTermsText }} Claimable multiple times within {{ $effWarrantyDays }} days from <strong>Past Completed Orders</strong> with photos and admin review.
                        </p>
                    </div>
                @else
                    <div class="rounded-lg bg-slate-50 border border-slate-200 px-3.5 py-2.5 text-[11px] font-semibold text-slate-600 flex items-center gap-2">
                        <i class="fa-solid fa-circle-info text-slate-400"></i>
                        <span>Standard Consumable / Tool — No Extended Warranty Period Included</span>
                    </div>
                @endif

                {{-- Order / Add-to-Cart Form --}}
                <form id="spOrderForm" action="{{ route('cart.add') }}" method="POST" class="space-y-4">
                    @csrf
                    <input type="hidden" name="spare_part_id" value="{{ $listing->id }}">
                    <input type="hidden" name="buy_now" id="spBuyNowInput" value="0">

                    {{-- Dynamic Spec Selector (Etel / LaptopWorld Quality Grades) --}}
                    @if($listing->has_specs && !empty($specOptions))
                        <div id="spSpecContainer" class="rounded-lg bg-emerald-50/60 border border-emerald-900/20 p-3.5 sm:p-4 space-y-2.5">
                            <div class="flex items-center justify-between gap-2">
                                <label for="spSpecSelect" class="text-xs sm:text-sm font-semibold text-emerald-950">
                                    {{ $listing->spec_label ?: 'Quality' }}:
                                </label>
                                <span class="px-2 py-0.5 rounded bg-amber-100 text-amber-900 border border-amber-300 text-[10px] font-semibold uppercase">
                                    Required — Prices Vary by Spec
                                </span>
                            </div>

                            {{-- Dropdown Select (Compact single spec picker) --}}
                            <select id="spSpecSelect"
                                    name="spec_code"
                                    onchange="window.spOnSpecChange(this.value)"
                                    class="w-full px-3.5 py-2.5 rounded-xl bg-white border border-emerald-900/25 text-sm font-bold text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-800">
                                <option value="">Choose an option</option>
                                @foreach($specOptions as $opt)
                                    @php $optStock = max(0, (int) ($opt['stock'] ?? 0)); @endphp
                                    <option value="{{ $opt['code'] }}"
                                            {{ $optStock <= 0 ? 'disabled' : '' }}
                                            {{ ($selectedSpec && $selectedSpec['code'] === $opt['code']) ? 'selected' : '' }}>
                                        {{ $opt['name'] }}{{ !empty($opt['subtitle']) ? (' — ' . $opt['subtitle']) : '' }}
                                        (৳{{ number_format((float) $opt['price'], 0) }}){{ $optStock <= 0 ? ' — Out of stock' : '' }}
                                    </option>
                                @endforeach
                            </select>

                            <p id="spSpecValidationError" class="hidden text-xs font-semibold text-red-600 pt-1">
                                ⚠ Please select a {{ $listing->spec_label ?: 'Quality' }} option above before adding to cart.
                            </p>
                        </div>
                    @endif

                    {{-- AliExpress-Style Progressive Wholesale Tiers --}}
                    @if($listing->wholesale_enabled && !empty($wholesaleTiers))
                        <div class="rounded-lg bg-amber-50/75 border border-amber-300/70 p-3.5 sm:p-4 space-y-2.5">
                            <div class="flex flex-wrap items-center justify-between gap-1.5">
                                <div class="text-xs sm:text-sm font-semibold text-emerald-950 flex flex-wrap items-center gap-1.5">
                                    <span>📦 Wholesale Bulk Discount</span>
                                    <span class="px-2.5 py-0.5 rounded bg-emerald-900 text-white text-[10px] font-semibold">
                                        Save {{ number_format((float) $listing->wholesale_base_discount_pct, 0) }}% on {{ (int) $listing->wholesale_min_qty }}+ pcs • Extra {{ number_format((float) $listing->wholesale_step_discount_pct, 0) }}% off / {{ (int) $listing->wholesale_step_qty }} pcs
                                    </span>
                                </div>
                                <span class="text-[11px] font-semibold text-emerald-900/75">Click any tier to apply qty</span>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-5 gap-2" id="spWholesaleTierGrid">
                                @foreach($wholesaleTiers as $tIdx => $tier)
                                    <button type="button"
                                            data-min-qty="{{ $tier['min_qty'] }}"
                                            data-max-qty="{{ $tier['max_qty'] ?? '' }}"
                                            data-pct="{{ $tier['discount_pct'] }}"
                                            onclick="window.spApplyQuantity({{ (int) $tier['min_qty'] }})"
                                            class="sp-wholesale-card rounded-xl p-2 text-left border transition {{ $tIdx === 0 ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-white hover:bg-amber-100/60 text-emerald-950 border-amber-300/80' }}">
                                        <div class="text-[10px] font-bold opacity-85">{{ $tier['range_label'] }}</div>
                                        <div class="sp-tier-price text-xs sm:text-sm font-semibold mt-0.5">
                                            ৳{{ number_format((float) $tier['unit_price'], 0) }}
                                        </div>
                                        <div class="text-[10px] font-semibold {{ $tier['discount_pct'] > 0 ? 'text-red-600 sp-tier-badge' : 'opacity-70' }}">
                                            {{ $tier['discount_pct'] > 0 ? (number_format((float) $tier['discount_pct'], 0) . '% OFF') : 'Standard' }}
                                        </div>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    {{-- Optional Certified Workshop Installation (Industry-Standard Retail Cap: Max 3 units) --}}
                    @if($supportsInstallation)
                        <label id="spInstallToggleCard"
                               for="spIncludeInstallation"
                               class="flex items-start gap-3 rounded-lg border border-emerald-900/20 bg-white p-3.5 cursor-pointer hover:border-emerald-800/40 transition">
                            <input type="checkbox"
                                   id="spIncludeInstallation"
                                   name="include_installation"
                                   value="1"
                                   onchange="window.spRecalculateLiveQuote()"
                                   {{ $initialQty > $maxInstallQty ? 'disabled' : '' }}
                                   class="mt-0.5 h-4 w-4 rounded border-emerald-900/30 text-emerald-900 focus:ring-emerald-800">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-xs sm:text-sm font-semibold text-emerald-950">
                                        Include Certified Workshop Installation
                                    </span>
                                    <span class="px-2 py-0.5 rounded bg-emerald-100 text-emerald-950 text-[11px] font-semibold">
                                        ৳{{ number_format($installationFeePerUnit, 0) }} / unit
                                    </span>
                                </div>
                                <p class="text-[11px] text-emerald-900/75 mt-0.5">
                                    Fitted at {{ $shop?->name ?? 'seller workshop' }} by certified technicians • Limited to retail orders of 1–{{ $maxInstallQty }} units.
                                </p>
                                <p id="spInstallLimitNotice" class="{{ $initialQty > $maxInstallQty ? '' : 'hidden' }} text-[11px] font-bold text-amber-800 mt-1">
                                    Workshop installation is capped at {{ $maxInstallQty }} units and is automatically disabled for bulk/wholesale quantities.
                                </p>
                            </div>
                        </label>
                    @endif

                    {{-- Quantity Stepper + Live Landed Total Calculator --}}
                    <div class="rounded-lg bg-[#cde7c7]/55 border border-emerald-900/15 p-3.5 sm:p-4 space-y-3">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <label for="spQuantityInput" class="block text-xs font-semibold text-emerald-950">
                                    Order Quantity
                                </label>
                                <span class="text-[11px] text-emerald-900/75">
                                    Available for single-unit orders and bulk trade quantities
                                </span>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        onclick="window.spStepQuantity(-1)"
                                        class="w-9 h-9 rounded-xl bg-white border border-emerald-900/20 text-emerald-950 font-semibold hover:bg-emerald-50 transition">
                                    −
                                </button>
                                <input type="number"
                                       id="spQuantityInput"
                                       name="quantity"
                                       min="1"
                                       max="{{ max(1, (int) $listing->stock_quantity) }}"
                                       value="{{ $initialQty }}"
                                       oninput="window.spRecalculateLiveQuote()"
                                       class="w-20 h-9 rounded-xl bg-white border border-emerald-900/25 text-center text-sm font-semibold text-emerald-950 focus:outline-none focus:ring-2 focus:ring-emerald-800">
                                <button type="button"
                                        onclick="window.spStepQuantity(1)"
                                        class="w-9 h-9 rounded-xl bg-white border border-emerald-900/20 text-emerald-950 font-semibold hover:bg-emerald-50 transition">
                                    +
                                </button>
                            </div>
                        </div>

                        {{-- Foodpanda-Style Live Transparent Fee Breakdown --}}
                        <div class="rounded-xl bg-white/90 border border-emerald-900/10 p-3 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-900/80">Unit Price:</span>
                                <span class="font-semibold text-emerald-950">
                                    <span id="spLiveUnitPrice">৳{{ number_format((float) $activeQuote['effective_unit_price'], 2) }}</span>
                                    <span id="spWholesaleActivePill" class="hidden ml-1 px-2 py-0.5 rounded bg-emerald-900 text-white text-[10px] font-semibold"></span>
                                </span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-900/80">Items Subtotal (<span id="spLiveQtyLabel">{{ $initialQty }}</span> pcs):</span>
                                <span class="font-bold text-emerald-950" id="spLiveSubtotal">৳{{ number_format($initialSubtotal, 2) }}</span>
                            </div>
                            <div id="spLiveInstallRow" class="hidden items-center justify-between text-emerald-900 font-semibold">
                                <span>Workshop Installation (<span id="spLiveInstallQty">0</span> × ৳{{ number_format($installationFeePerUnit, 0) }}):</span>
                                <span id="spLiveInstallFee">৳0.00</span>
                            </div>
                            <div id="spLiveSavingsRow" class="{{ (($activeQuote['wholesale_savings'] ?? 0) + ($activeQuote['offer_savings'] ?? 0)) > 0 ? 'flex' : 'hidden' }} items-center justify-between text-red-600 font-bold">
                                <span>Total Promo &amp; Wholesale Savings:</span>
                                <span id="spLiveSavingsAmount">−৳{{ number_format((float) (($activeQuote['wholesale_savings'] ?? 0) + ($activeQuote['offer_savings'] ?? 0)), 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-900/80">Service &amp; Buyer Protection Fee:</span>
                                <span class="font-bold text-amber-800" id="spLivePlatformFee">৳{{ number_format($initialPlatformFee, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-emerald-900/80">Doorstep Pickup &amp; Delivery ({{ $locLabel }}):</span>
                                <span class="font-bold text-emerald-950">৳{{ number_format($deliveryFee, 2) }}</span>
                            </div>
                            <div class="flex items-center justify-between pt-1.5 border-t border-emerald-900/10 text-sm">
                                <span class="font-semibold text-emerald-950">Estimated Checkout Total:</span>
                                <span class="font-semibold text-emerald-900 text-base" id="spLiveLandedTotal">
                                    ৳{{ number_format($initialCheckoutTotal, 2) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    {{-- Role-Aware Purchase Actions --}}
                    @if($authRole === 'admin')
                        <div class="rounded-xl bg-amber-50 border border-amber-300 p-3 text-xs font-bold text-amber-950">
                            Admin Preview Mode — Ordering is disabled for administrator accounts.
                        </div>
                    @elseif($isOwnVendorListing)
                        <div class="rounded-xl bg-amber-50 border border-amber-300 p-3.5 space-y-2">
                            <div class="text-xs font-semibold text-amber-950">
                                Listed by your workshop &middot; {{ $shop->name }}
                            </div>
                            <p class="text-xs text-amber-900">
                                You can order spare parts from any <strong>other</strong> vendor on Meramot, or manage your stock, offer dates, and wholesale tiers in your Vendor Dashboard.
                            </p>
                            <div class="flex flex-wrap gap-2 pt-1">
                                <a href="{{ route('vendor.dashboard', ['tab' => 'spare_parts', 'sp_q' => $listing->title]) }}"
                                   class="px-4 py-2 rounded-md bg-emerald-900 text-white text-xs font-bold hover:bg-emerald-800 transition">
                                    ✏️ Edit Listing in Vendor Dashboard
                                </a>
                                @if($competingListings->count() > 1)
                                    <a href="#compare-sellers"
                                       onclick="event.preventDefault(); const el = document.getElementById('compare-sellers'); if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });"
                                       class="px-4 py-2 rounded-md bg-white border border-amber-400 text-amber-950 text-xs font-bold hover:bg-amber-100 transition">
                                        Order from Another Seller ({{ $competingListings->count() - 1 }})
                                    </a>
                                @endif
                            </div>
                        </div>
                    @elseif($listing->stock_quantity <= 0)
                        <button type="button" disabled
                                class="w-full py-3 rounded-full bg-slate-300 text-slate-600 text-sm font-semibold cursor-not-allowed">
                            Currently Out of Stock
                        </button>
                    @else
                        @if($authRole === 'vendor')
                            <div class="rounded-xl bg-emerald-50 border border-emerald-900/20 px-3.5 py-2 text-xs text-emerald-950 font-semibold">
                                🤝 <strong>B2B Workshop Procurement:</strong> Signed in as a Vendor — you can purchase spare parts &amp; bulk wholesale stock directly from other verified sellers.
                            </div>
                        @endif

                        <div class="grid grid-cols-2 gap-2 sm:gap-3">
                            <button type="submit"
                                    id="spAddToCartBtn"
                                    onclick="return window.spPrepareSubmit(false)"
                                    class="w-full py-2.5 sm:py-3 px-3 sm:px-5 rounded-md bg-white border-2 border-emerald-900 text-emerald-950 hover:bg-emerald-50 text-xs sm:text-sm font-semibold transition shadow-xs disabled:opacity-50 disabled:cursor-not-allowed">
                                🛒 Add to Cart
                            </button>
                            <button type="submit"
                                    id="spOrderNowBtn"
                                    onclick="return window.spPrepareSubmit(true)"
                                    class="w-full py-2.5 sm:py-3 px-3 sm:px-5 rounded-md bg-emerald-900 hover:bg-emerald-800 text-white text-xs sm:text-sm font-semibold transition shadow-sm disabled:opacity-50 disabled:cursor-not-allowed">
                                ⚡ Order Now
                            </button>
                        </div>
                    @endif
                </form>
            </div>

            {{-- Attached Seller / Workshop Card (Mobile — placed cleanly after CTA) --}}
            @if($shop)
                <div class="lg:hidden rounded-lg bg-[var(--card)] border border-[var(--border)] p-3.5 shadow-sm space-y-2.5">
                    <div class="flex items-start justify-between gap-2.5">
                        <div class="min-w-0">
                            <div class="text-[10px] font-semibold uppercase tracking-wider text-emerald-800">Sold &amp; Dispatched By</div>
                            <div class="flex items-center gap-1.5 flex-wrap mt-0.5">
                                <span class="text-emerald-700 font-bold">✔</span>
                                <h2 class="text-sm font-semibold text-[var(--foreground)]">{{ $shop->name }}</h2>
                                @if($branchTag)
                                    <span class="px-1.5 py-0.5 rounded bg-emerald-900 text-white text-[10px] font-bold">
                                        {{ $branchTag }}
                                    </span>
                                @endif
                            </div>
                            <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5 truncate">
                                📍 {{ collect([$shop->area, $shop->city])->filter()->unique()->implode(', ') }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded bg-amber-50 border border-amber-200 text-[11px] font-semibold text-amber-900">
                                ★ {{ ((float) ($shop->rating ?? 0)) > 0 ? number_format((float) $shop->rating, 1) : '4.8' }}
                            </span>
                            <div class="text-[10px] text-[var(--muted-foreground)] mt-0.5">{{ $activeQuote['distance_formatted'] ?? '' }}</div>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-2 pt-1">
                        <a href="{{ route('spare-parts.index', ['seller_shop_id' => $shop->id]) }}"
                           class="text-center px-3 py-1.5 rounded-md bg-[var(--mint-50)] text-[var(--foreground)] text-[11px] font-bold border border-[var(--border)]">
                            🔩 Seller's Parts
                        </a>
                        <a href="{{ route('shops.show', ['slug' => $shop->slug]) }}"
                           class="text-center px-3 py-1.5 rounded-md bg-white text-emerald-950 text-[11px] font-bold border border-emerald-900/20">
                            🏪 Workshop Profile
                        </a>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Multi-Seller Comparison Table (eBay / Location-Aware Best Deal Engine) --}}
    <section id="compare-sellers" class="mt-8 rounded-lg bg-white/95 border border-emerald-900/15 p-4 sm:p-6 shadow-sm">
        <div class="mb-4">
            <h2 class="text-lg sm:text-xl font-semibold text-emerald-950">
                Compare All {{ $competingListings->count() }} {{ Str::plural('Seller', $competingListings->count()) }}
            </h2>
            <p class="text-xs text-emerald-900/75">
                Compare verified workshop offers side by side, with estimated doorstep delivery to {{ $locLabel }}.
            </p>
        </div>

        <div class="space-y-3">
            @foreach($competingListings as $sellerListing)
                @php
                    $cShop = $sellerListing->shop;
                    $cQuote = $sellerListing->landed_quote;
                    $cIsCurrent = $sellerListing->id === $listing->id;
                    $cOffer = $sellerListing->isOfferActive();
                    $cNormal = (float) ($cQuote['normal_unit_price'] ?? $sellerListing->resolvedNormalBasePrice());
                    $cRaw = (float) ($cQuote['raw_unit_price'] ?? $sellerListing->effectiveBasePrice());
                    $cBranch = $cShop?->display_branch_tag;
                    $cRate = \App\Services\MarketplacePricingService::resolveOrderFeeRatePct($cShop, $customerLocation ?? [], [
                        'item_type' => 'spare_part',
                        'part_category' => $sellerListing->part_category,
                    ]);
                    $cPlatformFee = round($cRaw * ($cRate / 100), 2);
                    $cCheckoutTotal = round((float) $cQuote['landed_total'] + $cPlatformFee, 2);
                @endphp
                @php
                    $showRowBestDeal = !empty($hasActiveSearchQuery) && $sellerListing->is_best_deal && $competingListings->count() > 1;
                @endphp
                <div class="rounded-lg p-3.5 sm:p-4 border transition flex flex-col lg:flex-row lg:items-center justify-between gap-3 {{ $showRowBestDeal ? 'bg-emerald-50/75 border-emerald-700 ring-1 ring-emerald-700/25' : 'bg-white border-emerald-900/15' }}">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            @if($showRowBestDeal)
                                <span class="px-2.5 py-0.5 rounded bg-emerald-900 text-white text-[10px] font-semibold uppercase">
                                    Best Deal
                                </span>
                            @endif
                            @if($cOffer)
                                <span class="px-2 py-0.5 rounded bg-red-600 text-white text-[10px] font-semibold">
                                    Sale!
                                </span>
                            @endif
                            <span class="font-semibold text-sm text-emerald-950">{{ $cShop?->name ?? 'Verified Seller' }}</span>
                            @if($cBranch)
                                <span class="px-1.5 py-0.5 rounded bg-emerald-900 text-white text-[10px] font-bold">{{ $cBranch }}</span>
                            @endif
                            @if($cIsCurrent)
                                <span class="px-2 py-0.5 rounded bg-emerald-200/80 text-emerald-950 text-[10px] font-semibold">
                                    Currently Viewing
                                </span>
                            @endif
                        </div>

                        <div class="text-xs text-emerald-900/80 flex flex-wrap items-center gap-x-3 gap-y-1">
                            <span>📍 {{ collect([$cShop?->area, $cShop?->city])->filter()->unique()->implode(', ') }} ({{ $cQuote['distance_formatted'] }})</span>
                            <span>🚚 ETA: <strong>{{ $cQuote['eta_label'] }}</strong></span>
                            <span>★ {{ ((float) ($cShop?->rating ?? 0)) > 0 ? number_format((float) $cShop->rating, 1) : '4.8' }}</span>
                            @if($sellerListing->wholesale_enabled)
                                <span class="text-amber-900 font-bold">
                                    📦 Wholesale: {{ number_format((float) $sellerListing->wholesale_base_discount_pct, 0) }}% off ({{ (int) $sellerListing->wholesale_min_qty }}+ pcs)
                                </span>
                            @endif
                        </div>
                    </div>

                    <div class="flex flex-wrap sm:flex-nowrap items-center justify-between lg:justify-end gap-4 pt-2 lg:pt-0 border-t lg:border-t-0 border-emerald-900/10">
                        <div class="text-left sm:text-right">
                            <div class="text-[11px] text-emerald-900/75">
                                Part:
                                @if($cOffer && $cNormal > $cRaw)
                                    <span class="line-through text-slate-400">৳{{ number_format($cNormal, 0) }}</span>
                                @endif
                                <strong class="{{ $cOffer ? 'text-red-600' : 'text-emerald-950' }}">৳{{ number_format($cRaw, 0) }}</strong>
                                + Service Fee: <strong>৳{{ number_format($cPlatformFee, 0) }}</strong>
                                + Courier: <strong>৳{{ number_format((float) $cQuote['delivery_fee'], 0) }}</strong>
                            </div>
                            <div class="text-base font-semibold text-emerald-950">
                                Checkout Total: ৳{{ number_format($cCheckoutTotal, 0) }}
                            </div>
                        </div>

                        @if($cIsCurrent)
                            <span class="px-4 py-2 rounded-md bg-emerald-900/10 text-emerald-950 text-xs font-semibold shrink-0">
                                Selected Seller
                            </span>
                        @else
                            <a href="{{ route('spare-parts.show', array_filter(['slug' => $sellerListing->slug, 'spec' => $selectedSpec['code'] ?? null, 'q' => $activeSearchQuery ?? null])) }}"
                               class="px-4 py-2 rounded-md bg-emerald-900 hover:bg-emerald-800 text-white text-xs font-semibold transition shrink-0">
                                Switch Seller →
                            </a>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Detailed Description --}}
    @if(!empty($listing->description))
        <section class="mt-6 rounded-lg bg-white/95 border border-emerald-900/15 p-4 sm:p-6 shadow-sm">
            <h2 class="text-base sm:text-lg font-semibold text-emerald-950 mb-2">Product Details &amp; Compatibility Notes</h2>
            <div class="text-xs sm:text-sm text-emerald-950/85 leading-relaxed whitespace-pre-line">{{ $listing->description }}</div>
        </section>
    @endif

    {{-- Verified Buyer Reviews for This Spare Part Listing --}}
    @if(isset($partReviews) && $partReviews->isNotEmpty())
        <section class="mt-6 rounded-lg bg-white/95 border border-emerald-900/15 p-4 sm:p-6 shadow-sm space-y-3" data-part-verified-reviews>
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-emerald-900/10 pb-2.5">
                <h2 class="text-base sm:text-lg font-semibold text-emerald-950 flex items-center gap-2">
                    <i class="fa-solid fa-star text-amber-500 text-sm"></i>
                    <span>Verified Spare Part Buyer Reviews ({{ $partReviews->count() }})</span>
                </h2>
                @if($listing->shop)
                    <a href="{{ route('shops.show', $listing->shop->slug) }}" class="text-xs font-semibold text-emerald-900 hover:underline">
                        Sold by {{ $listing->shop->name }} &rarr;
                    </a>
                @endif
            </div>

            <div class="space-y-3">
                @foreach($partReviews as $pRev)
                    <article class="rounded-lg border border-emerald-900/15 bg-[var(--mint-50)]/70 p-3.5 text-xs space-y-1.5">
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-bold text-emerald-950">{{ $pRev->customer?->name ?? 'Verified Buyer' }}</span>
                                <span class="rounded bg-emerald-100 border border-emerald-300 px-2 py-0.5 text-[10px] font-bold text-emerald-900">
                                    Verified Purchase
                                </span>
                                <span class="text-[11px] text-emerald-900/65">{{ $pRev->created_at?->diffForHumans() }}</span>
                            </div>
                            <div class="text-amber-500 shrink-0">
                                @for($s = 1; $s <= 5; $s++)
                                    <i class="fa-solid fa-star text-[10px] {{ $s <= (int) $pRev->rating ? 'text-amber-500' : 'text-slate-300' }}"></i>
                                @endfor
                            </div>
                        </div>
                        <p class="text-emerald-950/90 leading-relaxed">"{{ $pRev->review_comment }}"</p>
                    </article>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Related Spare Parts --}}
    @if($relatedListings->isNotEmpty())
        <section class="mt-8">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-lg font-semibold text-emerald-950">More in {{ $listing->part_category }}</h2>
                <a href="{{ route('spare-parts.index', ['part_category' => $listing->part_category]) }}"
                   class="text-xs font-semibold text-emerald-900 hover:underline">
                    View all →
                </a>
            </div>
            <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
                @foreach($relatedListings as $rel)
                    @php
                        $rOffer = $rel->isOfferActive();
                        $rEff = $rel->effectiveBasePrice();
                        $rNorm = $rel->resolvedNormalBasePrice();
                        $rQuote = $rel->landed_quote ?? $rel->calculateLandedQuote($customerLocation, 1);
                    @endphp
                    <a href="{{ route('spare-parts.show', ['slug' => $rel->slug]) }}"
                       class="group rounded-xl sm:rounded-lg bg-white/95 dark:bg-[var(--card)] border border-[var(--border)] hover:border-emerald-700 hover:shadow-md overflow-hidden transition flex flex-col justify-between min-w-0">
                        <div class="relative aspect-square bg-white border-b border-slate-100 dark:border-[var(--border)]/40 overflow-hidden p-2.5 sm:p-3">
                            @if($rOffer)
                                <span class="absolute top-2 left-2 z-10 w-9 h-9 rounded-full bg-red-600 text-white text-[10px] font-semibold flex items-center justify-center shadow-sm">
                                    Sale!
                                </span>
                            @endif
                            <img src="{{ $rel->image_url }}"
                                 alt="{{ $rel->title }}"
                                 loading="lazy"
                                 onerror="this.onerror=null;this.src='{{ e($rel->fallbackImageUrl()) }}';"
                                 class="w-full h-full object-contain group-hover:scale-105 transition">
                        </div>
                        <div class="p-2.5 sm:p-3.5 flex-1 flex flex-col justify-between gap-1.5 min-w-0">
                            <h3 class="text-xs sm:text-[13px] font-semibold text-[var(--foreground)] line-clamp-2 leading-snug">{{ $rel->title }}</h3>
                            <div class="space-y-1">
                                <div>
                                    @if($rOffer && $rNorm > $rEff)
                                        <span class="text-[11px] text-slate-400 line-through mr-1">৳{{ number_format($rNorm, 0) }}</span>
                                    @endif
                                    <span class="text-sm sm:text-base font-semibold {{ $rOffer ? 'text-red-600' : 'text-[var(--foreground)]' }}">
                                        ৳{{ number_format($rEff, 0) }}
                                    </span>
                                </div>
                                <div class="pt-1.5 border-t border-slate-200/70 dark:border-[var(--border)]/50 space-y-0.5 text-[10px] sm:text-[11px]">
                                    <div class="text-[var(--muted-foreground)]">Delivery: ৳{{ number_format((float) $rQuote['delivery_fee'], 0) }}</div>
                                    <div class="flex items-center justify-between text-[11px] sm:text-xs">
                                        <span class="text-[var(--muted-foreground)]">Total</span>
                                        <span class="font-semibold text-[var(--foreground)]">৳{{ number_format((float) $rQuote['landed_total'], 0) }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>
    @endif
</div>

<script>
(function () {
    const cfg = @json($jsPricingConfig);
    const baseListingMaxStock = Math.max(0, Number(cfg.maxStock || 0));

    function formatBdt(amount, decimals = 2) {
        const num = Number(amount || 0);
        return '৳' + num.toLocaleString('en-BD', {
            minimumFractionDigits: decimals,
            maximumFractionDigits: decimals,
        });
    }

    function calcWholesalePct(qty) {
        if (!cfg.wholesaleEnabled || qty < cfg.wholesaleMinQty) {
            return 0;
        }
        const extraSteps = Math.floor((qty - cfg.wholesaleMinQty) / cfg.wholesaleStepQty);
        const pct = cfg.wholesaleBasePct + (extraSteps * cfg.wholesaleStepPct);
        return Math.min(cfg.wholesaleMaxPct, Math.round(pct * 100) / 100);
    }

    function findSpec(code) {
        if (!cfg.hasSpecs || !code) return null;
        return (cfg.specOptions || []).find(o => o.code === code) || null;
    }

    window.spSwitchGalleryImage = function (url, btn) {
        const img = document.getElementById('spMainProductImage');
        if (img && url) img.src = url;
        document.querySelectorAll('.sp-thumb-btn').forEach(el => {
            el.classList.remove('border-emerald-800', 'ring-2', 'ring-emerald-700/25');
            el.classList.add('border-emerald-900/15', 'opacity-80');
        });
        if (btn) {
            btn.classList.remove('border-emerald-900/15', 'opacity-80');
            btn.classList.add('border-emerald-800', 'ring-2', 'ring-emerald-700/25');
        }
    };

    window.spOnSpecChange = function (code) {
        cfg.selectedSpecCode = code || '';
        const err = document.getElementById('spSpecValidationError');
        if (err && code) err.classList.add('hidden');

        const spec = findSpec(code);
        const stockLabel = document.getElementById('spStockCountLabel');
        const resolvedMaxStock = spec
            ? Math.min(baseListingMaxStock, Math.max(0, Number(spec.stock ?? baseListingMaxStock)))
            : baseListingMaxStock;
        cfg.maxStock = resolvedMaxStock;
        if (stockLabel) {
            stockLabel.textContent = resolvedMaxStock.toLocaleString('en-BD');
        }
        const qtyInput = document.getElementById('spQuantityInput');
        if (qtyInput) {
            qtyInput.max = String(Math.max(1, resolvedMaxStock));
            if (resolvedMaxStock > 0 && Number(qtyInput.value || 1) > resolvedMaxStock) {
                qtyInput.value = String(resolvedMaxStock);
            }
        }
        const addBtn = document.getElementById('spAddToCartBtn');
        const orderBtn = document.getElementById('spOrderNowBtn');
        const isSpecOut = Boolean(spec && resolvedMaxStock <= 0);
        if (addBtn) addBtn.disabled = isSpecOut;
        if (orderBtn) orderBtn.disabled = isSpecOut;

        // Update top price header
        const priceRow = document.getElementById('spPriceDisplayRow');
        if (priceRow) {
            if (spec) {
                const showOld = spec.has_offer && spec.normal_price > spec.price;
                priceRow.innerHTML =
                    (showOld ? `<span class="text-base sm:text-lg font-semibold text-slate-400 line-through">${formatBdt(spec.normal_price, 2)}</span>` : '') +
                    `<span class="text-2xl sm:text-3xl font-semibold ${showOld ? 'text-red-600' : 'text-emerald-950'}">${formatBdt(spec.price, 2)}</span>` +
                    `<span class="text-xs font-semibold text-emerald-900/70">for ${spec.name}</span>`;
            } else if (cfg.hasSpecs && cfg.effMaxPrice && cfg.effMaxPrice > cfg.effBasePrice) {
                const showOld = cfg.offerActive && cfg.normalBasePrice > cfg.effBasePrice;
                priceRow.innerHTML =
                    (showOld ? `<span class="text-sm sm:text-base font-semibold text-slate-400 line-through">${formatBdt(cfg.normalBasePrice, 2)}</span>` : '') +
                    `<span class="text-2xl sm:text-3xl font-semibold ${showOld ? 'text-red-600' : 'text-emerald-950'}">${formatBdt(cfg.effBasePrice, 2)} – ${formatBdt(cfg.effMaxPrice, 2)}</span>` +
                    `<span class="text-xs font-semibold text-emerald-900/70">per unit</span>`;
            }
        }

        // Refresh wholesale tier preview unit prices
        const baseUnit = spec ? Number(spec.price) : Number(cfg.effBasePrice);
        document.querySelectorAll('.sp-wholesale-card').forEach(card => {
            const pct = Number(card.getAttribute('data-pct') || 0);
            const tierUnit = Math.round(baseUnit * (1 - pct / 100));
            const priceEl = card.querySelector('.sp-tier-price');
            if (priceEl) priceEl.textContent = formatBdt(tierUnit, 0);
        });

        window.spRecalculateLiveQuote();
    };

    window.spStepQuantity = function (delta) {
        const input = document.getElementById('spQuantityInput');
        if (!input) return;
        const max = Math.max(1, Number(cfg.maxStock || 10000));
        const next = Math.max(1, Math.min(max, Number(input.value || 1) + delta));
        input.value = next;
        window.spRecalculateLiveQuote();
    };

    window.spApplyQuantity = function (qty) {
        const input = document.getElementById('spQuantityInput');
        if (!input) return;
        const max = Math.max(1, Number(cfg.maxStock || 10000));
        input.value = Math.max(1, Math.min(max, Number(qty || 1)));
        window.spRecalculateLiveQuote();
    };

    window.spRecalculateLiveQuote = function () {
        const input = document.getElementById('spQuantityInput');
        const qty = Math.max(1, Number(input ? input.value : 1) || 1);
        const spec = findSpec(cfg.selectedSpecCode);

        const rawUnit = spec ? Number(spec.price) : Number(cfg.effBasePrice);
        const normalUnit = spec ? Number(spec.normal_price || spec.price) : Number(cfg.normalBasePrice || cfg.effBasePrice);
        const pct = calcWholesalePct(qty);
        const effUnit = pct > 0 ? Math.round(rawUnit * (1 - pct / 100) * 100) / 100 : rawUnit;
        const partSubtotal = Math.round(effUnit * qty * 100) / 100;

        // Industry-standard installation cap: auto-disable & uncheck above maxInstallQty (e.g. > 3 units)
        const installCb = document.getElementById('spIncludeInstallation');
        const installCard = document.getElementById('spInstallToggleCard');
        const installNotice = document.getElementById('spInstallLimitNotice');
        const installRow = document.getElementById('spLiveInstallRow');
        const installQtyEl = document.getElementById('spLiveInstallQty');
        const installFeeEl = document.getElementById('spLiveInstallFee');
        const maxInstall = Number(cfg.maxInstallQty || 3);
        let installTotal = 0;

        if (cfg.supportsInstallation && installCb) {
            if (qty > maxInstall) {
                installCb.checked = false;
                installCb.disabled = true;
                if (installCard) installCard.classList.add('opacity-70');
                if (installNotice) installNotice.classList.remove('hidden');
            } else {
                installCb.disabled = false;
                if (installCard) installCard.classList.remove('opacity-70');
                if (installNotice) installNotice.classList.add('hidden');
                if (installCb.checked) {
                    installTotal = Math.round(Number(cfg.installationFeePerUnit || 0) * qty * 100) / 100;
                }
            }
        }

        if (installRow) {
            if (installTotal > 0) {
                if (installQtyEl) installQtyEl.textContent = qty.toLocaleString('en-BD');
                if (installFeeEl) installFeeEl.textContent = formatBdt(installTotal, 2);
                installRow.classList.remove('hidden');
                installRow.classList.add('flex');
            } else {
                installRow.classList.add('hidden');
                installRow.classList.remove('flex');
            }
        }

        const workshopSubtotal = Math.round((partSubtotal + installTotal) * 100) / 100;
        const wholesaleSavings = Math.max(0, Math.round((rawUnit * qty - partSubtotal) * 100) / 100);
        const offerSavings = (cfg.offerActive && normalUnit > rawUnit) ? Math.round((normalUnit - rawUnit) * qty * 100) / 100 : 0;
        const totalSavings = wholesaleSavings + offerSavings;
        const feePct = Number(cfg.commissionRatePct || 8);
        const platformFee = workshopSubtotal > 0 ? Math.round(workshopSubtotal * (feePct / 100) * 100) / 100 : 0;
        const landed = Math.round((workshopSubtotal + platformFee + Number(cfg.deliveryFee || 0)) * 100) / 100;

        const unitEl = document.getElementById('spLiveUnitPrice');
        const pillEl = document.getElementById('spWholesaleActivePill');
        const qtyEl = document.getElementById('spLiveQtyLabel');
        const subEl = document.getElementById('spLiveSubtotal');
        const feeEl = document.getElementById('spLivePlatformFee');
        const savRow = document.getElementById('spLiveSavingsRow');
        const savEl = document.getElementById('spLiveSavingsAmount');
        const totEl = document.getElementById('spLiveLandedTotal');

        if (unitEl) unitEl.textContent = formatBdt(effUnit, 2);
        if (qtyEl) qtyEl.textContent = qty.toLocaleString('en-BD');
        if (subEl) subEl.textContent = formatBdt(partSubtotal, 2);
        if (feeEl) feeEl.textContent = formatBdt(platformFee, 2);
        if (totEl) totEl.textContent = formatBdt(landed, 2);

        if (pillEl) {
            if (pct > 0) {
                pillEl.textContent = `${pct}% Wholesale Off`;
                pillEl.classList.remove('hidden');
            } else {
                pillEl.classList.add('hidden');
            }
        }

        if (savRow && savEl) {
            if (totalSavings > 0) {
                savEl.textContent = '−' + formatBdt(totalSavings, 2);
                savRow.classList.remove('hidden');
                savRow.classList.add('flex');
            } else {
                savRow.classList.add('hidden');
                savRow.classList.remove('flex');
            }
        }

        // Highlight active wholesale tier card
        document.querySelectorAll('.sp-wholesale-card').forEach(card => {
            const minQ = Number(card.getAttribute('data-min-qty') || 1);
            const maxAttr = card.getAttribute('data-max-qty');
            const maxQ = maxAttr ? Number(maxAttr) : Infinity;
            const active = qty >= minQ && qty <= maxQ;
            card.className = 'sp-wholesale-card rounded-xl p-2 text-left border transition ' +
                (active
                    ? 'bg-emerald-900 text-white border-emerald-900 shadow-sm'
                    : 'bg-white hover:bg-amber-100/60 text-emerald-950 border-amber-300/80');
            const badge = card.querySelector('.sp-tier-badge');
            if (badge) {
                badge.className = 'text-[10px] font-semibold sp-tier-badge ' + (active ? 'text-amber-300' : 'text-red-600');
            }
        });
    };

    window.spPrepareSubmit = function (buyNow) {
        const buyInput = document.getElementById('spBuyNowInput');
        if (buyInput) buyInput.value = buyNow ? '1' : '0';

        if (cfg.hasSpecs) {
            const sel = document.getElementById('spSpecSelect');
            if (!sel || !sel.value) {
                const err = document.getElementById('spSpecValidationError');
                const box = document.getElementById('spSpecContainer');
                if (err) err.classList.remove('hidden');
                if (box) {
                    box.classList.add('ring-2', 'ring-red-500');
                    box.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
                if (sel) sel.focus();
                return false;
            }
        }
        return true;
    };

    window.spRecalculateLiveQuote();
})();
</script>
@endsection

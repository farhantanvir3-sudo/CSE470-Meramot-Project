@extends('layouts.app')

@section('title', 'Meramot (মেরামত) — Digital Electronic Repair Marketplace')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-4 md:px-6 md:py-5">
    <div class="space-y-4 sm:space-y-6 md:space-y-7">

        <!-- ── 1. Promotional Banners ── -->
        <section class="grid {{ count($offerCards) > 1 ? 'grid-cols-2' : 'grid-cols-1' }} gap-2.5 sm:gap-3 md:gap-4" data-home-offer-grid data-offer-count="{{ count($offerCards) }}">
            @foreach($offerCards as $offer)
                <article class="flex flex-col justify-between rounded-lg border border-[var(--border)] bg-[var(--mint-100)] p-2.5 sm:p-5 md:min-h-[168px] md:p-6 shadow-sm transition hover:border-[var(--accent-dark)]/45 min-w-0">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-1.5">
                            <span class="inline-block rounded bg-[var(--mint-200)] px-1.5 sm:px-2 py-0.5 text-[8px] sm:text-[10px] font-semibold uppercase tracking-wider text-[var(--accent-dark)] leading-none sm:leading-normal" data-i18n="offer_available">
                                Featured Offer
                            </span>
                            @if(!empty($offer['voucher_pill']))
                                <span class="inline-flex items-center gap-1 rounded bg-white/85 border border-[var(--border)] px-1.5 sm:px-2 py-0.5 text-[8px] sm:text-[10px] font-semibold text-[var(--accent-dark)] leading-none sm:leading-normal max-w-full truncate" data-home-offer-voucher-pill>
                                    <i class="fa-solid fa-ticket text-[7px] sm:text-[9px] text-[var(--accent-dark)] shrink-0"></i>
                                    <span class="truncate">{{ $offer['voucher_pill'] }}</span>
                                </span>
                            @endif
                        </div>
                        <h2 class="mt-1.5 sm:mt-2 text-xs sm:text-lg sm:leading-snug md:text-2xl font-bold leading-snug text-[var(--foreground)] line-clamp-2 sm:line-clamp-none" @if(!empty($offer['title_key'])) data-i18n="{{ $offer['title_key'] }}" @endif>
                            {{ $offer['title'] }}
                        </h2>
                        <p class="mt-1 sm:mt-1.5 line-clamp-2 text-[10px] sm:text-xs leading-snug sm:leading-relaxed md:max-w-xl md:text-sm text-[var(--muted-foreground)]" @if(!empty($offer['subtitle_key'])) data-i18n="{{ $offer['subtitle_key'] }}" @endif>
                            {{ $offer['subtitle'] }}
                        </p>
                    </div>

                    <div class="mt-2.5 sm:mt-4 flex flex-wrap items-center justify-between gap-2">
                        <a href="{{ $offer['href'] }}" class="inline-flex items-center justify-center rounded-md bg-[var(--accent-dark)] px-2.5 py-1 sm:px-4 sm:py-2 md:px-5 text-[11px] sm:text-xs md:text-sm font-semibold text-[var(--accent-foreground)] transition hover:opacity-90">
                            <span data-i18n="explore_btn">Explore</span><span class="hidden md:inline" data-i18n="explore_shops_suffix">&nbsp;shops</span>
                        </a>
                        @if(!empty($offer['expires_label']))
                            <span class="hidden sm:inline-block text-[10px] font-medium text-[var(--muted-foreground)]">
                                {{ $offer['expires_label'] }}
                            </span>
                        @endif
                    </div>
                </article>
            @endforeach
        </section>

        <!-- ── 2. Featured Shops ── -->
        <section>
            <div class="mb-3 flex items-center justify-between gap-2 md:mb-4">
                <h2 class="text-lg font-bold text-[var(--foreground)] md:text-xl" data-i18n="featured_shops">
                    Featured shops
                </h2>
                <a href="/shops" class="text-xs font-semibold text-[var(--accent-dark)] hover:underline md:text-sm" data-i18n="view_all_arrow">
                    View all &rarr;
                </a>
            </div>

            <!-- Desktop Grid — 2 cols on md, 3 on lg -->
            <div class="hidden md:grid gap-3.5 md:grid-cols-2 lg:grid-cols-3">
                @foreach($featuredShops->slice(0, 6) as $shop)
                    <div class="h-full">
                        <a href="/shops/{{ $shop->slug }}" class="flex h-full flex-col justify-between rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm transition hover:border-[var(--accent-dark)]/55 hover:shadow-md md:p-5">
                            <div>
                                <div class="flex items-start gap-3">
                                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-md border border-[var(--border)] bg-[var(--mint-100)] text-lg font-bold text-[var(--accent-dark)] overflow-hidden md:h-14 md:w-14">
                                        @if($shop->logo_url)
                                            <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-full w-full object-cover">
                                        @else
                                            {{ strtoupper(substr($shop->name, 0, 1)) }}
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <h3 class="line-clamp-2 text-sm font-semibold text-[var(--foreground)] md:text-base">
                                                        {{ $shop->name }}
                                                    </h3>
                                                    @if($shop->display_branch_tag)
                                                        <span class="inline-flex items-center gap-1 rounded bg-[var(--accent-dark)] px-2 py-0.5 text-[10px] font-semibold text-[var(--accent-foreground)]">
                                                            <i class="fa-solid fa-code-branch text-[8px]"></i>
                                                            <span>{{ $shop->display_branch_tag }}</span>
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="mt-0.5 text-[var(--muted-foreground)] line-clamp-1 text-xs">
                                                    {{ $shop->public_address }}
                                                </p>
                                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-[var(--foreground)] tabular-nums">
                                                    @if((int) ($shop->review_count ?? 0) > 0)
                                                        <span class="inline-flex items-center gap-1 font-semibold"><i class="fa-solid fa-star text-amber-500 text-[11px]"></i> {{ number_format($shop->rating, 1) }}</span>
                                                        <span class="text-[var(--muted-foreground)]">({{ $shop->review_count }})</span>
                                                    @else
                                                        <span class="font-medium text-[var(--accent-dark)]" data-i18n="new_no_reviews">New · No reviews yet</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex shrink-0 flex-col items-end pl-2 text-right tabular-nums">
                                                <span class="font-medium text-[var(--muted-foreground)] uppercase tracking-wider text-[10px]" data-i18n="starting_from">
                                                    {{ $shop->inspection_fee !== null ? 'From' : 'Starts' }}
                                                </span>
                                                <span class="font-bold tracking-tight text-[var(--accent-dark)] leading-tight text-base md:text-lg">
                                                    ৳{{ number_format($shop->inspection_fee ?? ($shop->base_labor_fee ?? 350)) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            @php
                                $homeShopVouchers = $shop->availableVouchers();
                            @endphp
                            <div class="mt-3.5 pt-3 border-t border-[var(--border)]/60 flex flex-wrap items-center justify-between gap-2">
                                <div class="flex flex-wrap gap-1.5 min-w-0">
                                    @foreach($homeShopVouchers->take(2) as $hv)
                                        <span data-shop-voucher-badge="{{ $hv->code }}" class="inline-flex items-center gap-1.5 rounded-md border border-[var(--border)] bg-white px-2.5 py-1 text-[10px] font-bold text-[var(--accent-dark)] shadow-2xs max-w-full">
                                            <i class="fa-solid fa-ticket text-[9px] text-[var(--accent-dark)] shrink-0"></i>
                                            <span class="truncate">{{ $hv->advertisedBadgeText() }}</span>
                                        </span>
                                    @endforeach
                                    @if($homeShopVouchers->count() > 2)
                                        <span class="inline-flex items-center rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-2 py-1 text-[10px] font-bold text-[var(--accent-dark)] shadow-2xs">
                                            +{{ $homeShopVouchers->count() - 2 }}
                                        </span>
                                    @endif
                                    @if(!$shop->supportsCourierPickup() && $shop->supportsInShopRepair())
                                        <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 text-[11px] font-medium text-[var(--accent-dark)]" data-i18n="in_shop_walk_in">
                                            In-Shop Walk-In
                                        </span>
                                    @endif
                                </div>
                                @if(!empty($shop->specialties) && $homeShopVouchers->isEmpty())
                                    <span class="truncate max-w-[180px] text-[11px] text-[var(--muted-foreground)]">
                                        {{ is_array($shop->specialties) ? implode(' · ', array_slice($shop->specialties, 0, 2)) : $shop->specialties }}
                                    </span>
                                @endif
                            </div>
                        </a>
                    </div>
                @endforeach
            </div>

            <!-- Mobile Grid — 2 cols, compact crisp cards -->
            <div class="grid grid-cols-2 gap-2.5 md:hidden">
                @foreach($featuredShops->slice(0, 4) as $shop)
                    @php
                        $mobVouchers = $shop->availableVouchers();
                        $topMobVoucher = $mobVouchers->first();
                    @endphp
                    <a href="/shops/{{ $shop->slug }}" class="relative flex flex-col justify-between overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 shadow-sm transition hover:border-[var(--accent-dark)]/50 min-h-[144px]">
                        <!-- Top Row: Logo & Rating -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-md border border-[var(--border)] bg-[var(--mint-100)] text-xs font-bold text-[var(--accent-dark)] overflow-hidden">
                                @if($shop->logo_url)
                                    <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-full w-full object-cover">
                                @else
                                    {{ strtoupper(substr($shop->name, 0, 1)) }}
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center gap-0.5 rounded bg-[var(--mint-100)] px-1.5 py-0.5 text-[10px] font-semibold text-[var(--accent-dark)] tabular-nums">
                                {{ (int) ($shop->review_count ?? 0) > 0 ? '★ ' . number_format($shop->rating, 1) : 'New' }}
                            </div>
                        </div>

                        <!-- Title & Branch Tag -->
                        <div class="mt-2 min-w-0">
                            <h3 class="line-clamp-2 text-xs font-semibold leading-tight text-[var(--foreground)]">
                                {{ $shop->name }}
                            </h3>
                            @if($shop->display_branch_tag)
                                <span class="mt-1 inline-flex max-w-full items-center gap-1 truncate rounded bg-[var(--accent-dark)] px-1.5 py-0.5 text-[8px] font-semibold text-[var(--accent-foreground)]">
                                    <i class="fa-solid fa-code-branch text-[7px]"></i>
                                    <span class="truncate">{{ $shop->display_branch_tag }}</span>
                                </span>
                            @endif
                        </div>

                        <!-- Bottom: Price & Alfresco Voucher Pill -->
                        <div class="mt-auto pt-2 space-y-1.5 min-w-0 tabular-nums">
                            <div class="flex items-end justify-between gap-1">
                                <div class="flex flex-col min-w-0">
                                    <span class="text-[9px] font-medium uppercase text-[var(--muted-foreground)] leading-none truncate" data-i18n="starting_from">
                                        From
                                    </span>
                                    <div class="text-sm font-bold tracking-tight text-[var(--accent-dark)] leading-none mt-1 truncate">
                                        ৳{{ number_format($shop->inspection_fee ?? ($shop->base_labor_fee ?? 350)) }}
                                    </div>
                                </div>
                                @if($mobVouchers->count() > 1)
                                    <span class="rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-1.5 py-0.5 text-[8px] font-bold text-[var(--accent-dark)] leading-none shrink-0 shadow-2xs">
                                        +{{ $mobVouchers->count() - 1 }}
                                    </span>
                                @endif
                            </div>

                            @if($topMobVoucher)
                                <div class="inline-flex max-w-full items-center gap-1 rounded-md border border-[var(--border)] bg-white px-2 py-0.5 text-[9px] font-bold text-[var(--accent-dark)] leading-tight shadow-2xs">
                                    <i class="fa-solid fa-ticket text-[8px] text-[var(--accent-dark)] shrink-0"></i>
                                    <span class="truncate">{{ $topMobVoucher->advertisedBadgeText() }}</span>
                                </div>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- Explore More Button -->
            <div class="mt-5 flex justify-center md:mt-6">
                <a href="/shops" class="inline-flex items-center gap-2 rounded-md border border-[var(--accent-dark)] bg-transparent px-5 py-2 text-xs sm:text-sm font-semibold text-[var(--accent-dark)] transition-colors hover:bg-[var(--mint-50)] md:px-6 md:py-2.5">
                    <span data-i18n="explore_more_featured">Explore More Featured Shops</span>
                    <i class="fa-solid fa-arrow-right text-xs"></i>
                </a>
            </div>
        </section>

        <!-- ── 3. Popular Categories ── -->
        <section class="space-y-3">
            <h2 class="text-lg font-bold text-[var(--foreground)] md:text-xl" data-i18n="popular_categories">
                Popular Categories
            </h2>

            <div class="grid grid-cols-3 lg:grid-cols-6 gap-2.5 md:gap-3">
                @foreach($popularCategories as $item)
                    <a href="{{ $item['href'] }}" class="flex min-w-0 flex-col items-center justify-center gap-2 rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 text-center shadow-sm transition hover:border-[var(--accent-dark)]/55 hover:shadow-md">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-md border border-[var(--border)] bg-[var(--mint-100)] text-[var(--accent-dark)] md:h-11 md:w-11">
                            @if(str_starts_with((string) ($item['sprite'] ?? ''), 'fa-'))
                                <i class="fa-solid {{ $item['sprite'] }} text-base md:text-lg"></i>
                            @else
                                <span class="text-lg">{{ $item['sprite'] }}</span>
                            @endif
                        </div>
                        <div class="min-w-0 w-full">
                            <h3 class="text-xs font-semibold leading-tight text-[var(--foreground)] truncate md:text-sm">
                                {{ $item['label'] }}
                            </h3>
                            <p class="mt-0.5 line-clamp-1 text-[10px] text-[var(--muted-foreground)] md:text-[11px]">
                                {{ $item['trend'] }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        @if(!empty($recentlyViewed))
        <!-- ── 4. Recent Searches ── -->
        <section x-data="meramotHomeRecentSearches(@js($recentlyViewed))"
                 x-show="items.length > 0"
                 x-cloak
                 class="space-y-3"
                 data-recent-searches-section>
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-bold text-[var(--foreground)] md:text-xl" data-i18n="recently_viewed">
                    Recent Searches
                </h2>

                <button type="button"
                        @click="clearAll()"
                        data-recent-clear-all
                        class="inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-[var(--muted-foreground)] hover:bg-[var(--mint-100)] hover:text-[var(--foreground)] transition cursor-pointer"
                        data-i18n="clear_recent_searches">
                    Clear all
                </button>
            </div>

            <div class="flex flex-wrap gap-2">
                <template x-for="item in items" :key="item.normalized || item.label">
                    <div class="group inline-flex items-center rounded-md border border-[var(--border)] bg-[var(--card)] shadow-sm transition hover:border-[var(--accent-dark)]/50 hover:bg-[var(--mint-50)]">
                        <a :href="item.href"
                           :data-recent-search-query="item.label"
                           data-no-prefetch="true"
                           class="inline-flex items-center gap-2 pl-3 pr-2 py-2 text-xs sm:text-sm font-medium leading-none text-[var(--foreground)]">
                            <svg class="h-3.5 w-3.5 shrink-0 text-[var(--muted-foreground)] group-hover:text-[var(--accent-dark)] transition-colors" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/>
                                <polyline points="12 7 12 12 15.5 14"/>
                            </svg>
                            <span x-text="item.label"></span>
                        </a>
                        <button type="button"
                                @click.prevent.stop="removeItem(item.label)"
                                :aria-label="'Remove recent search ' + item.label"
                                class="inline-flex h-6 w-6 mr-1 items-center justify-center rounded text-[11px] text-[var(--muted-foreground)] hover:bg-[var(--mint-100)] hover:text-[var(--foreground)] transition cursor-pointer">
                            ✕
                        </button>
                    </div>
                </template>
            </div>
        </section>
        @endif

    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Meramot (মেরামত) — Digital Electronic Repair Marketplace')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-4 md:px-6 md:py-6">
    <div class="space-y-6 md:space-y-8">

        <!-- ── 1. Offer Carousel (Exact match to OfferCarousel.tsx from commit 8e44218) ── -->
        <section class="grid grid-cols-2 gap-3 md:gap-4">
            @foreach($offerCards as $offer)
                <article class="flex flex-col rounded-[1.4rem] bg-[var(--mint-100)] p-4 shadow-sm md:min-h-[260px] md:justify-center md:rounded-[2rem] md:p-10 lg:min-h-[280px]">
                    <div class="flex flex-1 flex-col">
                        <p class="mb-1 text-[10px] font-semibold uppercase tracking-[0.18em] text-[var(--foreground)] md:mb-2 md:text-sm md:tracking-[0.2em] lg:text-base">
                            Offer available
                        </p>

                        <h2 class="text-lg font-bold leading-tight text-[var(--foreground)] md:text-3xl lg:text-4xl">
                            {{ $offer['title'] }}
                        </h2>

                        <p class="mt-2 line-clamp-2 text-xs leading-5 text-[var(--muted-foreground)] md:mt-3 md:max-w-xl md:text-sm lg:text-base lg:leading-relaxed">
                            {{ $offer['subtitle'] }}
                        </p>

                        <div class="mt-auto pt-4 md:pt-5 lg:pt-6">
                            <a href="{{ $offer['href'] }}" class="self-start inline-flex items-center justify-center rounded-full bg-[var(--accent-dark)] px-4 py-1.5 text-xs font-semibold text-[var(--accent-foreground)] md:px-5 md:py-2 md:text-sm lg:px-6 lg:py-2.5 lg:text-base transition hover:opacity-90">
                                Explore<span class="hidden md:inline">&nbsp;shops</span>
                            </a>
                        </div>
                    </div>
                </article>
            @endforeach
        </section>

        <!-- ── 2. Featured Shops (Exact match to FeaturedShops.tsx from commit 8e44218) ── -->
        <section class="mt-10">
            <h2 class="mb-3 text-lg font-bold text-[var(--foreground)] md:mb-4 md:text-2xl">
                Featured shops
            </h2>

            <!-- Desktop Grid — 2 cols on md, 3 on lg -->
            <div class="hidden md:grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                @foreach($featuredShops->slice(0, 6) as $shop)
                    <div class="h-full">
                        <a href="/shops/{{ $shop->slug }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm transition hover:shadow-md h-full flex flex-col justify-between md:p-6 md:min-h-[220px]">
                            <div>
                                <div class="flex items-start gap-3 md:gap-4">
                                    <div class="flex shrink-0 items-center justify-center rounded-xl bg-[var(--mint-100)] text-xl font-bold text-[var(--accent-dark)] h-12 w-12 md:h-16 md:w-16">
                                        @if($shop->logo_url)
                                            <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-full w-full rounded-xl object-cover">
                                        @else
                                            {{ strtoupper(substr($shop->name, 0, 1)) }}
                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-2">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-1.5">
                                                    <h3 class="line-clamp-2 font-bold text-[var(--foreground)] text-base md:text-lg">
                                                        {{ $shop->name }}
                                                    </h3>
                                                    @if($shop->display_branch_tag)
                                                        <span class="inline-flex items-center gap-1 rounded-full bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)] shadow-sm">
                                                            <i class="fa-solid fa-code-branch text-[9px]"></i>
                                                            <span>{{ $shop->display_branch_tag }}</span>
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="mt-0.5 text-[var(--muted-foreground)] line-clamp-1 text-xs md:text-sm">
                                                    {{ $shop->public_address }}
                                                </p>
                                                <div class="mt-1 flex flex-wrap items-center gap-1.5 text-xs text-[var(--foreground)]">
                                                    @if((int) ($shop->review_count ?? 0) > 0)
                                                        <span>⭐ {{ number_format($shop->rating, 1) }}</span>
                                                        <span class="text-[var(--muted-foreground)]">({{ $shop->review_count }} {{ \Illuminate\Support\Str::plural('review', $shop->review_count) }})</span>
                                                    @else
                                                        <span class="font-semibold text-[var(--accent-dark)]">New · No reviews yet</span>
                                                    @endif
                                                </div>
                                            </div>

                                            <div class="flex shrink-0 flex-col items-end pl-2 text-right">
                                                <span class="font-semibold text-[var(--muted-foreground)] uppercase tracking-wider text-[10px] md:text-[11px]">
                                                    {{ $shop->inspection_fee ? 'Starting from' : 'From' }}
                                                </span>
                                                <span class="font-extrabold tracking-tight text-[var(--accent-dark)] leading-tight text-base md:text-xl">
                                                    ৳{{ number_format($shop->inspection_fee ?: ($shop->base_labor_fee ?: 350)) }}
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mt-3 flex flex-wrap gap-2 md:mt-5">
                                @if($shop->has_voucher)
                                    <span class="rounded-full bg-[var(--mint-100)] px-3 py-1 text-[var(--accent-dark)] text-xs md:text-sm">
                                        Voucher
                                    </span>
                                @endif
                                @if($shop->free_delivery)
                                    <span class="rounded-full bg-[var(--mint-100)] px-3 py-1 text-[var(--accent-dark)] text-xs md:text-sm">
                                        Free delivery
                                    </span>
                                @endif
                                @if($shop->has_deals)
                                    <span class="rounded-full bg-[var(--mint-100)] px-3 py-1 text-[var(--accent-dark)] text-xs md:text-sm">
                                        Deal
                                    </span>
                                @endif
                            </div>

                            @if(!empty($shop->specialties))
                                <p class="mt-3 md:mt-4 text-sm md:text-base font-medium text-[var(--muted-foreground)]">
                                    {{ is_array($shop->specialties) ? implode(' • ', array_slice($shop->specialties, 0, 2)) : $shop->specialties }}
                                </p>
                            @endif
                        </a>
                    </div>
                @endforeach
            </div>

            <!-- Mobile Grid — 2 cols, natural height tiles (Exact match to FeaturedShops.tsx mobile) -->
            <div class="grid grid-cols-2 gap-3 md:hidden">
                @foreach($featuredShops->slice(0, 4) as $shop)
                    <a href="/shops/{{ $shop->slug }}" class="relative flex flex-col justify-between overflow-hidden rounded-[1rem] border border-[var(--border)] bg-[var(--card)] p-3 shadow-sm transition active:scale-95 active:bg-[var(--mint-50)] min-h-[140px]">
                        <!-- Top Row: Logo & Rating -->
                        <div class="flex items-start justify-between gap-2">
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-[0.75rem] bg-[var(--mint-100)] text-sm font-bold text-[var(--accent-dark)]">
                                @if($shop->logo_url)
                                    <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-full w-full rounded-[0.75rem] object-cover">
                                @else
                                    {{ strtoupper(substr($shop->name, 0, 1)) }}
                                @endif
                            </div>
                            <div class="flex shrink-0 items-center rounded-full bg-[var(--mint-50)] px-1.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                                {{ (int) ($shop->review_count ?? 0) > 0 ? '★ ' . number_format($shop->rating, 1) : 'New' }}
                            </div>
                        </div>

                        <!-- Title & Branch Tag -->
                        <div class="mt-2 min-w-0">
                            <h3 class="line-clamp-2 text-xs font-bold leading-tight tracking-tight text-[var(--foreground)]">
                                {{ $shop->name }}
                            </h3>
                            @if($shop->display_branch_tag)
                                <span class="mt-1 inline-flex max-w-full items-center gap-1 truncate rounded-full bg-[var(--accent-dark)] px-2 py-0.5 text-[8px] font-bold text-[var(--accent-foreground)]">
                                    <i class="fa-solid fa-code-branch text-[7px]"></i>
                                    <span class="truncate">{{ $shop->display_branch_tag }}</span>
                                </span>
                            @endif
                        </div>

                        <!-- Bottom: Price & Badges -->
                        <div class="mt-auto pt-2 flex items-end justify-between gap-1">
                            <div class="flex flex-col min-w-0">
                                <span class="text-[9px] font-bold uppercase text-[var(--muted-foreground)] leading-none truncate">
                                    Starting from
                                </span>
                                <div class="text-sm font-black tracking-tight text-[var(--accent-dark)] leading-none mt-1.5 truncate">
                                    ৳{{ number_format($shop->inspection_fee ?: ($shop->base_labor_fee ?: 350)) }}
                                </div>
                            </div>
                            
                            <div class="flex flex-col items-end gap-1 shrink-0">
                                @if($shop->has_voucher)
                                    <span class="rounded bg-[var(--accent-dark)] px-1.5 py-[3px] text-[7px] font-bold uppercase tracking-widest text-[var(--accent-foreground)] leading-none">
                                        VOUCHER
                                    </span>
                                @endif
                                @if($shop->free_delivery)
                                    <span class="rounded bg-[var(--mint-100)] px-1.5 py-[3px] text-[7px] font-bold uppercase tracking-widest text-[var(--accent-dark)] leading-none">
                                        FREE
                                    </span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>

            <!-- Explore More Button -->
            <div class="mt-6 flex justify-center md:mt-8">
                <a href="/shops" class="inline-flex items-center gap-2 rounded-full border-2 border-[var(--accent-dark)] bg-transparent px-6 py-2.5 text-sm font-bold text-[var(--accent-dark)] transition-colors hover:bg-[var(--mint-50)] md:px-8 md:py-3 md:text-base">
                    <span>Explore More Featured Shops</span>
                    <svg class="h-4 w-4 md:h-5 md:w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 5l7 7m0 0l-7 7m7-7H3" />
                    </svg>
                </a>
            </div>
        </section>

        <!-- ── 3. Popular Categories (Exact match to PopularCategories.tsx from commit 8e44218) ── -->
        <section class="space-y-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[var(--muted-foreground)]">
                    Popular Service Categories
                </p>
                <h2 class="text-lg font-bold text-[var(--foreground)] md:text-2xl lg:text-3xl">
                    Most requested device repairs
                </h2>
            </div>

            <div class="grid grid-cols-2 gap-3 md:gap-4 lg:grid-cols-3">
                @foreach($popularCategories as $item)
                    <a href="{{ $item['href'] }}" class="flex flex-col items-center justify-center gap-2 rounded-2xl border border-[var(--border)] bg-[var(--card)] p-3 text-center shadow-sm transition hover:-translate-y-0.5 hover:shadow-md min-h-[120px] md:min-h-0 md:flex-row md:justify-start md:gap-4 md:rounded-[1.75rem] md:p-4 md:text-left">
                        <div class="grid h-12 w-12 shrink-0 place-items-center rounded-xl bg-[var(--mint-200)] text-2xl shadow-inner md:h-16 md:w-16 md:rounded-2xl md:text-3xl">
                            {{ $item['sprite'] }}
                        </div>
                        <div>
                            <h3 class="text-xs font-bold leading-tight text-[var(--foreground)] md:text-lg md:font-semibold">
                                {{ $item['label'] }}
                            </h3>
                            <p class="mt-1 line-clamp-2 text-[10px] leading-[1.3] text-[var(--muted-foreground)] md:mt-1 md:text-sm md:leading-tight">
                                {{ $item['trend'] }}
                            </p>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <!-- ── 4. Recently Viewed (Exact match to RecentlyViewed.tsx from commit 8e44218) ── -->
        <section class="space-y-4">
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-[var(--muted-foreground)]">
                    Recently viewed
                </p>
                <h2 class="text-lg font-bold text-[var(--foreground)] md:text-2xl lg:text-3xl">
                    Resume your recent repair searches
                </h2>
            </div>

            <div class="flex flex-wrap gap-3">
                @foreach($recentlyViewed as $item)
                    <a href="{{ $item['href'] }}" class="inline-flex items-center justify-center rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-sm font-medium leading-none text-[var(--foreground)] shadow-sm transition hover:bg-[var(--mint-50)]">
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </section>

    </div>
</div>
@endsection

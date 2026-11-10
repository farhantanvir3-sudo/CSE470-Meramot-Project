@extends('layouts.app')

@section('title', $shop->name . ' — Verified Electronic Repair Shop | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:text-[var(--accent-dark)]">Homepage</a>
        <span>›</span>
        <a href="/shops" class="hover:text-[var(--accent-dark)]">Shops</a>
        <span>›</span>
        <span class="text-[var(--accent-dark)] font-semibold">{{ $shop->name }}</span>
    </div>

    <!-- Shop Profile Hero Header (Exact match to shops/[slug]/page.tsx) -->
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-8 shadow-sm mb-6 min-w-0 max-w-full">
        <div class="grid gap-6 sm:grid-cols-[minmax(0,1fr)_auto] lg:grid-cols-[130px_minmax(0,1fr)_260px] lg:items-start">
            
            <!-- Left: Shop Logo / Avatar -->
            <div class="h-24 w-24 sm:h-28 sm:w-28 lg:h-[130px] lg:w-[130px] rounded-[1.5rem] border-2 border-[var(--border)] bg-[var(--mint-100)] flex items-center justify-center font-black text-4xl text-[var(--accent-dark)] shrink-0 overflow-hidden shadow-sm">
                @if($shop->logo_url)
                    <img src="{{ $shop->logo_url }}" alt="{{ $shop->name }}" class="h-full w-full object-cover">
                @else
                    {{ strtoupper(substr($shop->name, 0, 1)) }}
                @endif
            </div>

            <!-- Middle: Shop Details -->
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wider text-[var(--muted-foreground)]">
                    @if(!empty($shop->specialties))
                        {{ is_array($shop->specialties) ? implode(' · ', array_slice($shop->specialties, 0, 4)) : $shop->specialties }}
                    @else
                        Electronics Repair & Diagnostics
                    @endif
                </p>

                <h1 class="mt-1 text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-[var(--foreground)]">
                    {{ $shop->name }}
                </h1>

                <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs sm:text-sm text-[var(--foreground)]">
                    <span class="font-bold flex items-center gap-1 text-amber-700">
                        ⭐ {{ number_format($shop->rating, 1) }}
                        <span class="text-[var(--muted-foreground)] font-normal">({{ $shop->review_count ?? count($shop->reviews) }} reviews)</span>
                    </span>
                    <span class="opacity-40">·</span>
                    <span class="flex items-center gap-1 text-[var(--muted-foreground)]">
                        <i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i>
                        {{ $shop->address ?? $shop->area . ', Dhaka' }}
                    </span>
                    @if($shop->phone)
                        <span class="opacity-40">·</span>
                        <span class="text-[var(--muted-foreground)]">{{ $shop->phone }}</span>
                    @endif
                </div>

                <p class="mt-3 text-xs sm:text-sm text-[var(--muted-foreground)] leading-relaxed max-w-3xl">
                    {{ $shop->description ?: "Professional device repair support with diagnostics, component level repairs, and courier handling from this verified repair shop." }}
                </p>

                <!-- Badges & Perks -->
                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                    @if($shop->has_voucher)
                        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-semibold text-[var(--accent-dark)]">
                            <i class="fa-solid fa-ticket text-amber-600 mr-1"></i> Coupon Voucher
                        </span>
                    @endif
                    @if($shop->free_delivery)
                        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-semibold text-emerald-800">
                            <i class="fa-solid fa-truck-fast text-emerald-600 mr-1"></i> Free Doorstep Pickup
                        </span>
                    @endif
                    @if($shop->has_deals)
                        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-semibold text-rose-800">
                            <i class="fa-solid fa-fire text-rose-600 mr-1"></i> Active Deals
                        </span>
                    @endif
                    <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-bold text-[var(--accent-dark)]">
                        Tier {{ str_repeat('৳', $shop->price_level ?? 2) }}
                    </span>
                </div>
            </div>

            <!-- Right: Quick Quote / Booking Card -->
            <div class="rounded-[1.5rem] border border-[var(--border)] bg-[var(--mint-100)] p-5 shadow-sm space-y-4">
                <div>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Starting Inspection</span>
                    <div class="text-2xl font-black text-[var(--accent-dark)]">
                        ৳{{ number_format($shop->inspection_fee ?: 200) }}
                    </div>
                    <div class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                        Base Labor: ৳{{ number_format($shop->base_labor_fee ?: 400) }} · ETA: {{ $shop->eta_minutes < 60 ? $shop->eta_minutes . ' min' : round($shop->eta_minutes / 60) . ' hr' }}
                    </div>
                </div>

                <div class="space-y-2">
                    <a href="/requests/create?shop_id={{ $shop->id }}" class="inline-flex w-full items-center justify-center rounded-full bg-[var(--accent-dark)] px-5 py-3 text-xs sm:text-sm font-semibold text-white shadow hover:opacity-90 transition">
                        Post Repair Request
                    </a>
                    <a href="/ai-consultant/chat" class="inline-flex w-full items-center justify-center rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-semibold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                        <i class="fa-solid fa-wand-magic-sparkles mr-1.5 text-amber-600"></i> AI Diagnose Device
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- Main Two-Column Layout (Matching shops/[slug]/page.tsx) -->
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] items-start">

        <!-- ── Left Column: Available Services & Customer Reviews ── -->
        <div class="min-w-0 space-y-6">

            <!-- 1. Available Services Catalog (Unified Direct-Order Cart + Custom Quote) -->
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-2xl md:text-3xl font-bold text-[var(--foreground)]">Available services</h2>
                        <p class="mt-1 text-xs md:text-sm text-[var(--muted-foreground)]">
                            Add fixed services to your Direct-Order Cart for {{ $shop->name }}, or request a custom diagnostic quote.
                        </p>
                    </div>
                    <a href="/cart" class="inline-flex items-center gap-2 rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                        <i class="fa-solid fa-cart-shopping"></i>
                        <span>Open Direct Cart</span>
                    </a>
                </div>

                @php
                    $dbServices = $shop->services ?? collect();
                    $displayServices = [];

                    if ($dbServices->count() > 0) {
                        foreach ($dbServices as $svc) {
                            $displayServices[] = [
                                'name' => $svc->name,
                                'estimate' => (float) ($svc->base_price ?: 1500),
                                'summary' => $svc->description ?: ($svc->short_description ?: 'Verified workshop repair service with full post-repair testing.'),
                            ];
                        }
                    } else {
                        $specialtiesList = $shop->specialties ?? [];
                        if (empty($specialtiesList) || !is_array($specialtiesList)) {
                            $specialtiesList = [
                                'Display & Touchscreen Replacement',
                                'Battery Diagnostics & Cell Replacement',
                                'Logic Board & Ultrasonic Liquid Treatment',
                                'Port, Jack & Keyboard Replacement'
                            ];
                        }

                        $serviceCatalog = [
                            'Display & Touchscreen Replacement' => ['estimate' => 1800, 'summary' => 'OEM panel check, precision digitizer fitting, calibration, and touch response testing.'],
                            'Battery Diagnostics & Cell Replacement' => ['estimate' => 1200, 'summary' => 'Battery capacity diagnostics, OEM cell replacement, power rail check, and charging cycle test.'],
                            'Logic Board & Ultrasonic Liquid Treatment' => ['estimate' => 2500, 'summary' => 'Micro-soldering, short-circuit detection with thermal camera, ultrasonic bath, and board rework.'],
                            'Port, Jack & Keyboard Replacement' => ['estimate' => 950, 'summary' => 'Pin inspection, flex ribbon replacement, solder joint reflow, and full physical keystroke test.'],
                        ];

                        foreach ($specialtiesList as $spec) {
                            $meta = $serviceCatalog[$spec] ?? [
                                'estimate' => $shop->base_labor_fee ? ($shop->base_labor_fee + 500) : 1500,
                                'summary' => 'Comprehensive inspection, OEM replacement components, certified labor, and functional warranty included.'
                            ];
                            $displayServices[] = [
                                'name' => $spec,
                                'estimate' => (float) $meta['estimate'],
                                'summary' => $meta['summary'],
                            ];
                        }
                    }
                @endphp

                <div class="space-y-4">
                    @foreach($displayServices as $svcItem)
                        <article class="flex flex-col gap-4 rounded-[1.5rem] border border-[var(--border)] p-4 transition hover:shadow-sm md:flex-row md:items-start md:justify-between bg-[var(--mint-100)]/40">
                            <div class="min-w-0 flex-1">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="min-w-0">
                                        <h3 class="text-base md:text-lg font-bold text-[var(--foreground)]">{{ $svcItem['name'] }}</h3>
                                        <p class="mt-1 text-xs md:text-sm leading-relaxed text-[var(--muted-foreground)]">{{ $svcItem['summary'] }}</p>
                                    </div>

                                    <div class="shrink-0 text-right">
                                        <div class="text-base md:text-lg font-black text-[var(--accent-dark)]">
                                            ৳{{ number_format($svcItem['estimate']) }}
                                        </div>
                                        <div class="text-[10px] text-[var(--muted-foreground)]">direct service</div>
                                    </div>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center gap-2 w-full md:w-auto md:pl-4">
                                <button
                                    type="button"
                                    onclick="MeramotCart.addShopService({{ $shop->id }}, @js($svcItem['name']), @js($svcItem['summary']), {{ $svcItem['estimate'] }}, this)"
                                    class="inline-flex h-10 flex-1 md:flex-none items-center justify-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-5 text-xs md:text-sm font-semibold text-white shadow-sm hover:opacity-90 transition whitespace-nowrap"
                                >
                                    <i class="fa-solid fa-cart-plus text-xs"></i>
                                    <span>Add to Cart</span>
                                </button>
                                <a
                                    href="/requests/create?shop_id={{ $shop->id }}&issue={{ urlencode($svcItem['name']) }}"
                                    class="inline-flex h-10 items-center justify-center rounded-full border border-[var(--border)] bg-[var(--card)] px-4 text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition whitespace-nowrap"
                                >
                                    Custom Quote
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <!-- 2. Customer Reviews Section (Exact 2-Card Layout from commit 8e44218) -->
            @php
                $reviewsList = $shop->reviews ?? collect();
                $totalReviewsCount = count($reviewsList);
                $avgRating = $totalReviewsCount > 0 ? $reviewsList->avg('rating') : ($shop->rating ?: 4.8);
                $starCounts = [
                    5 => $reviewsList->where('rating', 5)->count(),
                    4 => $reviewsList->where('rating', 4)->count(),
                    3 => $reviewsList->where('rating', 3)->count(),
                    2 => $reviewsList->where('rating', 2)->count(),
                    1 => $reviewsList->where('rating', 1)->count(),
                ];
                // If seeded reviews count is 0, provide illustrative seed baseline matching mock data
                if ($totalReviewsCount === 0) {
                    $totalReviewsCount = $shop->review_count ?: 18;
                    $starCounts = [5 => intval($totalReviewsCount * 0.7), 4 => intval($totalReviewsCount * 0.2), 3 => intval($totalReviewsCount * 0.08), 2 => 0, 1 => 0];
                }
            @endphp

            <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)] items-start">

                <!-- Left Card: Rating Score & Star Breakdown Bars -->
                <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm self-start">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-[var(--muted-foreground)]">Customer reviews</p>
                    <h2 class="mt-1 text-xl font-bold text-[var(--foreground)]">Rating summary</h2>

                    <div class="mt-4 rounded-[1.5rem] bg-[var(--mint-50)] px-4 py-4 text-center">
                        <div class="text-4xl font-black text-[var(--accent-dark)]">{{ number_format($avgRating, 1) }}</div>
                        <div class="text-amber-500 text-sm mt-1">
                            @for($i = 1; $i <= 5; $i++)
                                <i class="fa-solid fa-star {{ $i <= round($avgRating) ? 'text-amber-500' : 'text-gray-300' }}"></i>
                            @endfor
                        </div>
                        <p class="mt-1 text-xs text-[var(--muted-foreground)]">{{ $totalReviewsCount }} verified reviews</p>
                    </div>

                    <div class="mt-4 space-y-2">
                        @foreach([5, 4, 3, 2, 1] as $star)
                            @php
                                $cnt = $starCounts[$star] ?? 0;
                                $pct = $totalReviewsCount > 0 ? round(($cnt / $totalReviewsCount) * 100) : 0;
                            @endphp
                            <div class="flex items-center gap-2 text-xs">
                                <span class="w-7 font-bold text-[var(--foreground)]">{{ $star }} ★</span>
                                <div class="h-2 flex-1 overflow-hidden rounded-full bg-[var(--mint-200)]">
                                    <div class="h-full rounded-full bg-amber-500" style="width: {{ $pct }}%"></div>
                                </div>
                                <span class="w-6 text-right text-[10px] text-[var(--muted-foreground)] font-semibold">{{ $cnt }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- Right Card: Reviews List -->
                <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] pb-3">
                        <p class="text-xs font-bold uppercase tracking-wider text-[var(--muted-foreground)]">
                            Feedback from verified clients
                        </p>
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-[var(--mint-100)] px-3 py-0.5 text-xs font-semibold text-[var(--accent-dark)]">
                                All Dhaka Hubs
                            </span>
                            <a href="/reviews/create/{{ $shop->id }}" class="rounded-full bg-[var(--accent-dark)] px-3 py-1 text-xs font-semibold text-white hover:opacity-90 transition">
                                <i class="fa-solid fa-pen-to-square mr-1 text-[10px]"></i> Write Review
                            </a>
                        </div>
                    </div>

                    <div class="space-y-4">
                        @forelse($reviewsList as $rev)
                            <article class="rounded-[1.5rem] border border-[var(--border)] bg-[var(--mint-100)]/50 p-4 shadow-sm text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-[var(--foreground)] text-sm">{{ $rev->customer->name ?? 'Meramot Customer' }}</p>
                                            @if($rev->is_verified_purchase ?? true)
                                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-800 border border-emerald-300">
                                                    Verified repair
                                                </span>
                                            @endif
                                        </div>
                                        <p class="text-[10px] text-[var(--muted-foreground)] mt-0.5">
                                            {{ $rev->created_at ? $rev->created_at->diffForHumans() : '2 weeks ago' }}
                                        </p>
                                    </div>
                                    <div class="text-amber-500">
                                        @for($s = 1; $s <= 5; $s++)
                                            <i class="fa-solid fa-star text-[10px] {{ $s <= ($rev->rating ?? 5) ? 'text-amber-500' : 'text-gray-300' }}"></i>
                                        @endfor
                                    </div>
                                </div>
                                <p class="mt-2.5 text-xs text-[var(--foreground)] leading-relaxed">
                                    "{{ $rev->review_comment ?? 'Excellent repair service. The technician was transparent about pricing and completed the inspection in under two hours.' }}"
                                </p>
                            </article>
                        @empty
                            <!-- Default baseline reviews when none in DB yet -->
                            <article class="rounded-[1.5rem] border border-[var(--border)] bg-[var(--mint-100)]/50 p-4 shadow-sm text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-[var(--foreground)] text-sm">Fahim Ahmed</p>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-800 border border-emerald-300">
                                                Verified repair
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-[var(--muted-foreground)] mt-0.5">3 days ago</p>
                                    </div>
                                    <div class="text-amber-500">
                                        <i class="fa-solid fa-star text-[10px]"></i><i class="fa-solid fa-star text-[10px]"></i><i class="fa-solid fa-star text-[10px]"></i><i class="fa-solid fa-star text-[10px]"></i><i class="fa-solid fa-star text-[10px]"></i>
                                    </div>
                                </div>
                                <p class="mt-2.5 text-xs text-[var(--foreground)] leading-relaxed">
                                    "Fast battery replacement for my MacBook Pro. Very satisfied with the precision work and warranty provided."
                                </p>
                            </article>
                            <article class="rounded-[1.5rem] border border-[var(--border)] bg-[var(--mint-100)]/50 p-4 shadow-sm text-xs">
                                <div class="flex items-start justify-between gap-2">
                                    <div>
                                        <div class="flex items-center gap-2">
                                            <p class="font-bold text-[var(--foreground)] text-sm">Sumaiya Rahman</p>
                                            <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-800 border border-emerald-300">
                                                Verified repair
                                            </span>
                                        </div>
                                        <p class="text-[10px] text-[var(--muted-foreground)] mt-0.5">1 week ago</p>
                                    </div>
                                    <div class="text-amber-500">
                                        <i class="fa-solid fa-star text-[10px]"></i><i class="fa-solid fa-star text-[10px]"></i><i class="fa-solid fa-star text-[10px]"></i><i class="fa-solid fa-star text-[10px]"></i><i class="fa-solid fa-star text-[10px]"></i>
                                    </div>
                                </div>
                                <p class="mt-2.5 text-xs text-[var(--foreground)] leading-relaxed">
                                    "Clean diagnostic report and fair price. Doorstep courier pickup was seamless and on time."
                                </p>
                            </article>
                        @endforelse
                    </div>
                </div>

            </div>

        </div>

        <!-- ── Right Column: Shop Contact, Opening Hours & Related Hubs ── -->
        <div class="space-y-6">

            <!-- Shop Info Card -->
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                <h3 class="text-base font-bold text-[var(--foreground)] mb-4 flex items-center gap-2">
                    <i class="fa-solid fa-store text-[var(--accent-dark)]"></i>
                    Shop details & hours
                </h3>
                
                <div class="space-y-3 text-xs text-[var(--foreground)]">
                    <div class="flex items-start gap-2.5">
                        <i class="fa-solid fa-phone text-[var(--accent-dark)] mt-0.5"></i>
                        <span>{{ $shop->phone ?? '+880 1711-234567' }}</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <i class="fa-solid fa-envelope text-[var(--accent-dark)] mt-0.5"></i>
                        <span>{{ $shop->email ?? 'service@' . $shop->slug . '.meramot.com' }}</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <i class="fa-solid fa-map-pin text-[var(--accent-dark)] mt-0.5"></i>
                        <span>{{ $shop->address ?? $shop->area . ', Dhaka' }}</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <i class="fa-solid fa-clock text-[var(--accent-dark)] mt-0.5"></i>
                        <span>Sat – Thu: 10:00 AM – 8:30 PM (Friday Closed)</span>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-[var(--border)]">
                    <a href="/requests/create?shop_id={{ $shop->id }}" class="block w-full text-center rounded-full bg-[var(--accent-dark)] px-4 py-2.5 text-xs font-bold text-white shadow hover:opacity-90 transition">
                        Select This Shop &rarr;
                    </a>
                </div>
            </div>

            <!-- Related Shops in Area -->
            @if(count($relatedShops) > 0)
                <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                    <h3 class="text-base font-bold text-[var(--foreground)] mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i>
                        Other shops in {{ $shop->area }}
                    </h3>
                    <div class="space-y-3">
                        @foreach($relatedShops as $rel)
                            <a href="/shops/{{ $rel->slug }}" class="block rounded-[1.2rem] border border-[var(--border)] bg-[var(--mint-100)] p-3 text-xs hover:bg-[var(--mint-200)] transition group">
                                <div class="flex items-center justify-between">
                                    <div class="font-bold text-[var(--foreground)] group-hover:text-[var(--accent-dark)] truncate">
                                        {{ $rel->name }}
                                    </div>
                                    <span class="text-amber-700 font-bold shrink-0">⭐ {{ number_format($rel->rating, 1) }}</span>
                                </div>
                                <div class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                                    Inspection: ৳{{ number_format($rel->inspection_fee ?: 200) }} · Labor: ৳{{ number_format($rel->base_labor_fee ?: 400) }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

        </div>

    </div>

</div>
@endsection

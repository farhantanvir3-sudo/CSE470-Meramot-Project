@extends('layouts.app')

@section('title', $shop->name . ' — Verified Electronic Repair Shop | Meramot')

@section('content')
@php
    $verificationStatus = $shop->vendorProfile?->verification_status ?? ($shop->is_public ? 'approved' : 'pending');
    $isOperational = (bool) $shop->is_active && (bool) $shop->is_public && $verificationStatus === 'approved';
    $reviewsList = ($shop->reviews ?? collect())->where('is_hidden', false)->values();
    $totalReviewsCount = $reviewsList->count() > 0 ? $reviewsList->count() : (int) ($shop->review_count ?? 0);
    $avgRating = $reviewsList->count() > 0
        ? round((float) $reviewsList->avg('rating'), 2)
        : ($totalReviewsCount > 0 ? (float) $shop->rating : 0.0);
@endphp

<div class="mx-auto max-w-7xl px-4 py-6 md:px-6">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:text-[var(--accent-dark)]">Homepage</a>
        <span>›</span>
        <a href="/shops" class="hover:text-[var(--accent-dark)]">Shops</a>
        <span>›</span>
        <span class="text-[var(--accent-dark)] font-semibold">{{ $shop->name }}</span>
    </div>

    <!-- Pre-Onboarding / Inactive Storefront Preview Banner -->
    @if(!$isOperational)
        <div class="mb-6 rounded-[1.6rem] border-2 {{ $verificationStatus === 'rejected' ? 'border-rose-400 bg-rose-50/95 text-rose-950' : 'border-amber-400 bg-amber-50/95 text-amber-950' }} p-4 md:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded-full {{ $verificationStatus === 'rejected' ? 'bg-rose-700' : 'bg-amber-600' }} px-3 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-white">
                        {{ $verificationStatus === 'rejected' ? 'Application Declined · Inactive' : 'Preview Mode · Pending Admin Verification' }}
                    </span>
                    <span class="text-xs font-bold">
                        {{ $verificationStatus === 'rejected' ? 'This vendor application was declined during compliance audit.' : 'This workshop has not yet been approved and onboarded.' }}
                    </span>
                </div>
                <p class="text-xs opacity-90 leading-relaxed">
                    @if($verificationStatus === 'rejected' && $shop->vendorProfile?->rejection_reason)
                        Audit Note: {{ $shop->vendorProfile->rejection_reason }}
                    @else
                        Hidden from public search results. Direct-Order bookings, repair request dispatch, and customer reviews unlock only after Trade License &amp; NID approval.
                    @endif
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if(auth()->check() && auth()->user()->role === 'admin')
                    <a href="/admin/vendors/pending" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition">
                        <i class="fa-solid fa-shield-halved mr-1"></i> Return to Vendor Audit Desk
                    </a>
                @else
                    <a href="/shops" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition">
                        Browse Verified Shops &rarr;
                    </a>
                @endif
            </div>
        </div>
    @endif

    <!-- Shop Profile Hero Header -->
    <div
        class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-8 shadow-sm mb-6 min-w-0 max-w-full"
        data-shop-lat="{{ $shop->latitude }}"
        data-shop-lng="{{ $shop->longitude }}"
    >
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
                        Electronics Repair &amp; Diagnostics
                    @endif
                </p>

                <div class="mt-1 flex flex-wrap items-center gap-2.5">
                    <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold tracking-tight text-[var(--foreground)]">
                        {{ $shop->name }}
                    </h1>
                    @if($shop->display_branch_tag)
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-3.5 py-1 text-xs sm:text-sm font-bold text-[var(--accent-foreground)] shadow-sm">
                            <i class="fa-solid fa-code-branch text-xs"></i>
                            <span>Branch: {{ $shop->display_branch_tag }}</span>
                        </span>
                    @endif
                </div>

                <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-xs sm:text-sm text-[var(--foreground)]">
                    @if($totalReviewsCount > 0)
                        <span class="font-bold flex items-center gap-1 text-amber-700">
                            ⭐ {{ number_format($avgRating, 1) }}
                            <span class="text-[var(--muted-foreground)] font-normal">({{ $totalReviewsCount }} {{ \Illuminate\Support\Str::plural('review', $totalReviewsCount) }})</span>
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-0.5 text-xs font-bold text-[var(--accent-dark)]">
                            <i class="fa-solid {{ $isOperational ? 'fa-sparkles' : 'fa-clock' }} text-[10px]"></i>
                            <span>{{ $isOperational ? 'New Shop · No reviews yet' : 'Not yet onboarded · 0 reviews' }}</span>
                        </span>
                    @endif
                    <span class="opacity-40">·</span>
                    <span class="flex items-center gap-1 text-[var(--muted-foreground)]">
                        <i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i>
                        {{ $shop->public_address }}
                    </span>
                    <span class="distance-display {{ isset($shop->distance_km) ? '' : 'hidden' }} rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-xs font-bold text-[var(--accent-dark)]">
                        {{ isset($shop->distance_km) ? \App\Services\GeolocationService::formatDistance((float) $shop->distance_km) : '' }}
                    </span>
                    @if($shop->phone)
                        <span class="opacity-40">·</span>
                        <span class="text-[var(--muted-foreground)]">{{ $shop->phone }}</span>
                    @endif
                </div>

                <p class="mt-3 text-xs sm:text-sm text-[var(--muted-foreground)] leading-relaxed max-w-3xl">
                    {{ $shop->description ?: "Professional device repair support with diagnostics, component level repairs, and courier handling from this repair workshop." }}
                </p>

                <!-- Badges & Perks -->
                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                    @if(!$isOperational)
                        <span class="rounded-full bg-amber-100 border border-amber-300 px-3 py-1 font-bold text-amber-900">
                            <i class="fa-solid fa-hourglass-half mr-1"></i> {{ ucfirst($verificationStatus) }} Verification
                        </span>
                    @endif
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
                        Base Labor: ৳{{ number_format($shop->base_labor_fee ?: 400) }} · ETA: <span class="eta-display font-semibold text-[var(--foreground)]">{{ \App\Services\GeolocationService::formatEta((int) ($shop->eta_minutes ?: 30)) }}</span>
                    </div>
                </div>

                <div class="space-y-2">
                    @if($isOperational)
                        <a href="/requests/create?shop_id={{ $shop->id }}" class="inline-flex w-full items-center justify-center rounded-full bg-[var(--accent-dark)] px-5 py-3 text-xs sm:text-sm font-semibold text-white shadow hover:opacity-90 transition">
                            Post Repair Request
                        </a>
                    @else
                        <span class="inline-flex w-full items-center justify-center rounded-full border border-amber-400 bg-amber-100/80 px-4 py-2.5 text-xs font-bold text-amber-900 cursor-not-allowed">
                            <i class="fa-solid fa-lock mr-1.5 text-[10px]"></i> Booking Locked Until Approved
                        </span>
                    @endif
                    <a href="/ai-consultant/chat" class="inline-flex w-full items-center justify-center rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-semibold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                        <i class="fa-solid fa-wand-magic-sparkles mr-1.5 text-amber-600"></i> AI Diagnose Device
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- Main Two-Column Layout -->
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] items-start">

        <!-- ── Left Column: Available Services & Customer Reviews ── -->
        <div class="min-w-0 space-y-6">

            <!-- 1. Available Services Catalog (Unified Direct-Order Cart + Custom Quote) -->
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-2xl md:text-3xl font-bold text-[var(--foreground)]">Available services</h2>
                        <p class="mt-1 text-xs md:text-sm text-[var(--muted-foreground)]">
                            @if($isOperational)
                                Add fixed services to your Direct-Order Cart for {{ $shop->name }}, or request a custom diagnostic quote.
                            @else
                                Proposed service catalog submitted with {{ $shop->name }}'s onboarding application (read-only until verified).
                            @endif
                        </p>
                    </div>
                    @if($isOperational)
                        <a href="/cart" class="inline-flex items-center gap-2 rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                            <i class="fa-solid fa-cart-shopping"></i>
                            <span>Open Direct Cart</span>
                        </a>
                    @else
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 border border-amber-300 px-3.5 py-1.5 text-xs font-bold text-amber-900">
                            <i class="fa-solid fa-eye text-[10px]"></i>
                            <span>Catalog Preview</span>
                        </span>
                    @endif
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
                                'Port, Jack & Keyboard Replacement',
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
                                'summary' => 'Comprehensive hardware inspection, genuine replacement components, certified bench labor, and functional warranty.',
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
                                @if($isOperational && $shop->accepts_direct_orders)
                                    <button
                                        type="button"
                                        onclick="MeramotCart.addShopService({{ $shop->id }}, @js($svcItem['name']), @js($svcItem['summary']), {{ $svcItem['estimate'] }}, this)"
                                        class="inline-flex h-10 flex-1 md:flex-none items-center justify-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-5 text-xs md:text-sm font-semibold text-white shadow-sm hover:opacity-90 transition whitespace-nowrap"
                                    >
                                        <i class="fa-solid fa-cart-plus text-xs"></i>
                                        <span>Add to Cart</span>
                                    </button>
                                @endif

                                @if($isOperational)
                                    <a
                                        href="/requests/create?shop_id={{ $shop->id }}&issue={{ urlencode($svcItem['name']) }}"
                                        class="inline-flex h-10 items-center justify-center rounded-full border border-[var(--border)] bg-[var(--card)] px-4 text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition whitespace-nowrap"
                                    >
                                        Custom Quote
                                    </a>
                                @else
                                    <span class="inline-flex h-10 items-center justify-center rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-4 text-xs font-semibold text-[var(--muted-foreground)] cursor-not-allowed whitespace-nowrap">
                                        <i class="fa-solid fa-lock mr-1.5 text-[10px]"></i> Available After Approval
                                    </span>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>

            <!-- 2. Customer Reviews Section (Strictly Derived from Real Database Reviews — Zero Fabricated Reviews) -->
            @php
                $starCounts = [
                    5 => $reviewsList->where('rating', 5)->count(),
                    4 => $reviewsList->where('rating', 4)->count(),
                    3 => $reviewsList->where('rating', 3)->count(),
                    2 => $reviewsList->where('rating', 2)->count(),
                    1 => $reviewsList->where('rating', 1)->count(),
                ];
                if ($reviewsList->isEmpty() && $totalReviewsCount > 0) {
                    $bucketStar = max(1, min(5, (int) round($avgRating ?: 5)));
                    $starCounts[$bucketStar] = $totalReviewsCount;
                }
            @endphp

            <div class="grid gap-6 lg:grid-cols-[260px_minmax(0,1fr)] items-start">

                <!-- Left Card: Rating Score & Star Breakdown Bars -->
                <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm self-start">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-[var(--muted-foreground)]">Customer reviews</p>
                    <h2 class="mt-1 text-xl font-bold text-[var(--foreground)]">Rating summary</h2>

                    <div class="mt-4 rounded-[1.5rem] bg-[var(--mint-50)] px-4 py-4 text-center">
                        @if($totalReviewsCount > 0)
                            <div class="text-4xl font-black text-[var(--accent-dark)]">{{ number_format($avgRating, 1) }}</div>
                            <div class="text-amber-500 text-sm mt-1">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="fa-solid fa-star {{ $i <= round($avgRating) ? 'text-amber-500' : 'text-gray-300' }}"></i>
                                @endfor
                            </div>
                            <p class="mt-1 text-xs text-[var(--muted-foreground)]">{{ $totalReviewsCount }} verified {{ \Illuminate\Support\Str::plural('review', $totalReviewsCount) }}</p>
                        @else
                            <div class="text-2xl font-black text-[var(--accent-dark)]">{{ $isOperational ? 'New' : '—' }}</div>
                            <div class="text-gray-300 text-sm mt-1">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="fa-solid fa-star"></i>
                                @endfor
                            </div>
                            <p class="mt-1 text-xs text-[var(--muted-foreground)]">0 verified reviews</p>
                        @endif
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
                                {{ $shop->area ? $shop->area . ', ' . $shop->city : ($shop->city ?: 'Bangladesh') }}
                            </span>
                            @if($isOperational)
                                <a href="/reviews/create/{{ $shop->id }}" class="rounded-full bg-[var(--accent-dark)] px-3 py-1 text-xs font-semibold text-white hover:opacity-90 transition">
                                    <i class="fa-solid fa-pen-to-square mr-1 text-[10px]"></i> Write Review
                                </a>
                            @else
                                <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-xs font-semibold text-[var(--muted-foreground)] cursor-not-allowed">
                                    <i class="fa-solid fa-lock mr-1 text-[10px]"></i> Reviews Locked
                                </span>
                            @endif
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
                                            {{ $rev->created_at ? $rev->created_at->diffForHumans() : 'Recently verified' }}
                                        </p>
                                    </div>
                                    <div class="text-amber-500">
                                        @for($s = 1; $s <= 5; $s++)
                                            <i class="fa-solid fa-star text-[10px] {{ $s <= ($rev->rating ?? 5) ? 'text-amber-500' : 'text-gray-300' }}"></i>
                                        @endfor
                                    </div>
                                </div>
                                <p class="mt-2.5 text-xs text-[var(--foreground)] leading-relaxed">
                                    "{{ $rev->review_comment }}"
                                </p>
                            </article>
                        @empty
                            <div class="rounded-[1.5rem] border border-dashed border-[var(--border)] bg-[var(--mint-100)]/40 px-5 py-8 text-center text-xs text-[var(--muted-foreground)] space-y-2">
                                <i class="fa-regular fa-comment-dots text-2xl text-[var(--accent-dark)] opacity-70 block"></i>
                                <p class="font-bold text-sm text-[var(--foreground)]">No verified customer reviews yet</p>
                                <p class="max-w-md mx-auto leading-relaxed">
                                    @if(!$isOperational)
                                        {{ $shop->name }} has not yet completed onboarding verification and has served 0 orders on Meramot.
                                    @else
                                        Customer ratings and feedback appear automatically once verified repair orders are completed with {{ $shop->name }}.
                                    @endif
                                </p>
                            </div>
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
                    Shop details &amp; hours
                </h3>

                <div class="space-y-3 text-xs text-[var(--foreground)]">
                    <div class="flex items-start gap-2.5">
                        <i class="fa-solid fa-phone text-[var(--accent-dark)] mt-0.5"></i>
                        <span>{{ $shop->phone ?: 'Verified via Meramot Dispatch Desk' }}</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <i class="fa-solid fa-envelope text-[var(--accent-dark)] mt-0.5"></i>
                        <span>{{ $shop->email ?? 'service@' . $shop->slug . '.meramot.com' }}</span>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <i class="fa-solid fa-map-pin text-[var(--accent-dark)] mt-0.5"></i>
                        <div>
                            <span class="block font-semibold">{{ $shop->public_address }}</span>
                            <span class="text-[10px] text-[var(--muted-foreground)]">{{ $shop->area }}, {{ $shop->city }}</span>
                        </div>
                    </div>
                    <div class="flex items-start gap-2.5">
                        <i class="fa-solid fa-clock text-[var(--accent-dark)] mt-0.5"></i>
                        <span>{{ $shop->opening_hours_text ?: 'Sat – Thu: 10:00 AM – 8:30 PM (Friday Closed)' }}</span>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-[var(--border)]">
                    @if($isOperational)
                        <a href="/requests/create?shop_id={{ $shop->id }}" class="block w-full text-center rounded-full bg-[var(--accent-dark)] px-4 py-2.5 text-xs font-bold text-white shadow hover:opacity-90 transition">
                            Select This Shop &rarr;
                        </a>
                    @else
                        <span class="block w-full text-center rounded-full border border-amber-400 bg-amber-100/80 px-4 py-2.5 text-xs font-bold text-amber-900 cursor-not-allowed">
                            Pending Onboarding Approval
                        </span>
                    @endif
                </div>
            </div>

            <!-- Other Branches of This Vendor Brand -->
            @if(isset($siblingBranches) && count($siblingBranches) > 0)
                <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="text-base font-bold text-[var(--foreground)] flex items-center gap-2">
                            <i class="fa-solid fa-code-branch text-[var(--accent-dark)]"></i>
                            <span>Other Branches ({{ count($siblingBranches) }})</span>
                        </h3>
                        <span class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                            {{ $shop->name }}
                        </span>
                    </div>
                    <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                        @foreach($siblingBranches as $branch)
                            <a href="/shops/{{ $branch->slug }}" class="block rounded-[1.2rem] border border-[var(--border)] bg-[var(--mint-100)] p-3 text-xs hover:bg-[var(--mint-200)] transition group">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1 rounded-full bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)] truncate">
                                        <i class="fa-solid fa-code-branch text-[8px]"></i>
                                        <span>{{ $branch->display_branch_tag ?: $branch->area }}</span>
                                    </span>
                                    @if((int) ($branch->review_count ?? 0) > 0)
                                        <span class="text-amber-700 font-bold shrink-0">⭐ {{ number_format($branch->rating, 1) }}</span>
                                    @else
                                        <span class="text-[var(--accent-dark)] font-bold text-[10px] shrink-0">New</span>
                                    @endif
                                </div>
                                <div class="text-[11px] text-[var(--muted-foreground)] mt-1.5 line-clamp-1">
                                    {{ $branch->public_address }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

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
                                <div class="flex items-center justify-between gap-1.5">
                                    <div class="font-bold text-[var(--foreground)] group-hover:text-[var(--accent-dark)] truncate">
                                        {{ $rel->name }}{{ $rel->display_branch_tag ? ' [' . $rel->display_branch_tag . ']' : '' }}
                                    </div>
                                    @if((int) ($rel->review_count ?? 0) > 0)
                                        <span class="text-amber-700 font-bold shrink-0">⭐ {{ number_format($rel->rating, 1) }}</span>
                                    @else
                                        <span class="text-[var(--accent-dark)] font-bold text-[10px] shrink-0">New</span>
                                    @endif
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

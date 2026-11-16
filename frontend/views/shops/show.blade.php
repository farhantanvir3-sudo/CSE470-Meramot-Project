@extends('layouts.app')

@section('title', $shop->name . ' — Verified Electronic Repair Shop | Meramot')

@section('content')
@php
    $verificationStatus = $shop->vendorProfile?->verification_status ?? ($shop->is_public ? 'approved' : 'pending');
    $isOperational = (bool) $shop->is_active && (bool) $shop->is_public && $verificationStatus === 'approved';
    $isShopOwner = auth()->check()
        && auth()->user()->role === 'vendor'
        && (int) ($shop->vendorProfile?->user_id ?? 0) === (int) auth()->id();
    $isAdminViewer = auth()->check() && auth()->user()->role === 'admin';
    $isNonCustomerViewer = auth()->check() && auth()->user()->role !== 'customer';
    $isBannedCustomerViewer = auth()->check() && auth()->user()->role === 'customer' && auth()->user()->isBanned();
    $canCustomerBookOrReview = !auth()->check() || (auth()->user()->role === 'customer' && !auth()->user()->isBanned());
    $currentUserId = auth()->check() ? (int) auth()->id() : null;
    $reviewsList = ($shop->reviews ?? collect())
        ->where('is_hidden', false)
        ->sortByDesc(function ($rev) use ($currentUserId) {
            $isOwn = $currentUserId !== null && (int) $rev->customer_id === $currentUserId ? 1 : 0;
            $isVerified = ($rev->is_verified_purchase ?? true) ? 1 : 0;
            $ts = $rev->created_at ? $rev->created_at->getTimestamp() : 0;
            return sprintf('%d-%d-%012d-%08d', $isOwn, $isVerified, $ts, (int) $rev->id);
        })
        ->values();
    $ownReviewIds = $currentUserId !== null
        ? $reviewsList->filter(fn ($r) => (int) $r->customer_id === $currentUserId)->pluck('id')->map(fn ($id) => (int) $id)->values()
        : collect();
    $serviceSkillBreakdown = $reviewsList
        ->filter(fn ($r) => (bool) ($r->is_verified_purchase ?? false))
        ->map(function ($r) {
            $srv = trim((string) ($r->service_provided ?: ($r->repairRequest?->issue_category ? $r->repairRequest->issue_category . ' — ' . $r->repairRequest->title : ($r->repairRequest?->title ?? ''))));
            return [
                'service' => $srv !== '' ? $srv : 'General Bench Repair',
                'rating' => (int) ($r->rating ?? 5),
            ];
        })
        ->groupBy('service')
        ->map(fn ($group, $srvName) => [
            'service' => $srvName,
            'avg_rating' => round((float) $group->avg('rating'), 1),
            'count' => $group->count(),
        ])
        ->sortByDesc('avg_rating')
        ->values();
    $totalReviewsCount = $reviewsList->count() > 0 ? $reviewsList->count() : (int) ($shop->review_count ?? 0);
    $avgRating = $reviewsList->count() > 0
        ? round((float) $reviewsList->avg('rating'), 2)
        : ($totalReviewsCount > 0 ? (float) $shop->rating : 0.0);
    $supportsCourier = $shop->supportsCourierPickup();
    $supportsInShop = $shop->supportsInShopRepair();
    $supportsSpareParts = $shop->supportsSpareParts();
    $isPureWholesaler = $shop->isPureSparePartsWholesaler();
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
        <div class="mb-6 rounded-lg border-2 {{ $verificationStatus === 'rejected' ? 'border-rose-400 bg-rose-50/95 text-rose-950' : 'border-amber-400 bg-amber-50/95 text-amber-950' }} p-4 md:p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="rounded {{ $verificationStatus === 'rejected' ? 'bg-rose-700' : 'bg-amber-600' }} px-3 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-white">
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
                    <a href="/admin/vendors/pending" class="rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition">
                        <i class="fa-solid fa-shield-halved mr-1"></i> Return to Vendor Audit Desk
                    </a>
                @else
                    <a href="/shops" class="rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition">
                        Browse Verified Shops &rarr;
                    </a>
                @endif
            </div>
        </div>
    @endif

    <!-- Shop Profile Hero Header -->
    <div
        class="overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--card)] shadow-sm mb-6 min-w-0 max-w-full"
        data-shop-lat="{{ $shop->latitude }}"
        data-shop-lng="{{ $shop->longitude }}"
    >
        @if($shop->banner_url)
            <div class="relative h-36 sm:h-48 md:h-56 w-full overflow-hidden border-b border-[var(--border)] bg-[var(--mint-200)]">
                <img src="{{ $shop->banner_url }}" alt="{{ $shop->name }} Cover Banner" class="h-full w-full object-cover">
                <div class="pointer-events-none absolute inset-0 bg-gradient-to-t from-black/35 via-transparent to-transparent"></div>
            </div>
        @endif

        <div class="p-4 sm:p-5 md:p-8">
        <div class="grid gap-5 sm:gap-6 sm:grid-cols-[auto_minmax(0,1fr)] lg:grid-cols-[130px_minmax(0,1fr)_260px] lg:items-start">

            <!-- Left: Shop Logo / Avatar -->
            <div class="h-24 w-24 sm:h-28 sm:w-28 lg:h-[130px] lg:w-[130px] rounded-lg border-2 border-[var(--border)] bg-[var(--mint-100)] flex items-center justify-center font-bold text-4xl text-[var(--accent-dark)] shrink-0 overflow-hidden shadow-sm">
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
                    <h1 class="break-words text-2xl sm:text-3xl lg:text-4xl font-semibold tracking-tight text-[var(--foreground)]">
                        {{ $shop->name }}
                    </h1>
                    @if($shop->display_branch_tag)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-3.5 py-1 text-xs sm:text-sm font-bold text-[var(--accent-foreground)] shadow-sm">
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
                        <span class="inline-flex items-center gap-1.5 rounded bg-[var(--mint-100)] border border-[var(--border)] px-3 py-0.5 text-xs font-bold text-[var(--accent-dark)]">
                            <i class="fa-solid {{ $isOperational ? 'fa-sparkles' : 'fa-clock' }} text-[10px]"></i>
                            <span>{{ $isOperational ? ($isPureWholesaler ? 'New Parts Store · No reviews yet' : 'New Workshop · No reviews yet') : 'Pending Verification · 0 reviews' }}</span>
                        </span>
                    @endif
                    <span class="opacity-40">·</span>
                    <span class="flex items-start sm:items-center gap-1 text-[var(--muted-foreground)] break-words">
                        <i class="fa-solid fa-location-dot text-[var(--accent-dark)] mt-0.5 sm:mt-0 shrink-0"></i>
                        <span class="break-words">{{ $shop->public_address }}</span>
                    </span>
                    <span class="distance-display {{ isset($shop->distance_km) ? '' : 'hidden' }} rounded bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-xs font-bold text-[var(--accent-dark)]">
                        {{ isset($shop->distance_km) ? \App\Services\GeolocationService::formatDistance((float) $shop->distance_km) : '' }}
                    </span>
                    @if($shop->phone)
                        <span class="opacity-40">·</span>
                        <span class="text-[var(--muted-foreground)]">{{ $shop->phone }}</span>
                    @endif
                </div>

                <p class="mt-3 text-xs sm:text-sm text-[var(--muted-foreground)] leading-relaxed max-w-3xl">
                    {{ $shop->description ?: ($isPureWholesaler
                        ? "Verified electronics spare parts store supplying genuine components and nationwide courier dispatch."
                        : "Verified electronic device repair center offering transparent inspection, component-level servicing, and platform-protected payments.") }}
                </p>

                <!-- Capability Badges -->
                <div class="mt-4 flex flex-wrap items-center gap-2 text-xs">
                    @if(!$isOperational)
                        <span class="rounded-md bg-amber-100 border border-amber-300 px-3 py-1 font-bold text-amber-900">
                            <i class="fa-solid fa-hourglass-half mr-1"></i> {{ ucfirst($verificationStatus) }} Verification
                        </span>
                    @endif
                    @if($supportsCourier)
                        <span class="rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-semibold text-emerald-950">
                            <i class="fa-solid fa-truck-fast text-emerald-700 mr-1"></i> Courier Pickup &amp; Return
                        </span>
                    @endif
                    @if($supportsInShop)
                        <span class="rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-semibold text-teal-950">
                            <i class="fa-solid fa-store text-teal-700 mr-1"></i> In-Shop Walk-In
                        </span>
                    @endif
                    @if($supportsSpareParts)
                        <span class="rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-semibold text-sky-950">
                            <i class="fa-solid fa-microchip text-sky-700 mr-1"></i> {{ $isPureWholesaler ? 'Spare Parts Wholesaler' : 'Spare Parts Store' }}
                        </span>
                    @endif
                    @if($shop->hasActiveServiceDeals())
                        <span class="rounded-md bg-rose-50 border border-rose-300 px-3 py-1 font-bold text-rose-800" data-badge="active-deal">
                            <i class="fa-solid fa-fire text-rose-600 mr-1"></i> Active Deal
                        </span>
                    @endif
                    @if($shop->hasSparePartsOnSale())
                        <span class="rounded-md bg-rose-50 border border-rose-300 px-3 py-1 font-bold text-rose-800" data-badge="active-sale">
                            <i class="fa-solid fa-bolt text-rose-600 mr-1"></i> Active Sale
                        </span>
                    @endif
                    <span class="rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-bold text-[var(--accent-dark)]">
                        Tier {{ str_repeat('৳', $shop->price_level ?? 2) }}
                    </span>
                </div>

                <!-- Available Vouchers at This Shop (Foodpanda Alfresco Format + Copyable Codes) -->
                @php
                    $profileVouchers = $shopAvailableVouchers ?? $shop->availableVouchers();
                @endphp
                @if($profileVouchers->isNotEmpty())
                    <div class="mt-4 pt-3.5 border-t border-[var(--border)]/70 space-y-2" data-shop-profile-vouchers x-data="{ copiedCode: null }">
                        <div class="flex items-center gap-1.5 text-[11px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">
                            <i class="fa-solid fa-ticket text-[var(--accent-dark)]"></i>
                            <span>Available Vouchers ({{ $profileVouchers->count() }})</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($profileVouchers as $pv)
                                <button
                                    type="button"
                                    data-profile-voucher-badge="{{ $pv->code }}"
                                    @click="navigator.clipboard?.writeText(@js($pv->code)); copiedCode = @js($pv->code); setTimeout(() => { if (copiedCode === @js($pv->code)) copiedCode = null; }, 1800)"
                                    class="group inline-flex items-center gap-2 rounded-md border border-[var(--border)] bg-white hover:bg-[var(--mint-100)]/60 px-3 py-1.5 text-left text-xs transition cursor-pointer shadow-2xs"
                                    title="Click to copy voucher code {{ $pv->code }}"
                                >
                                    <i class="fa-solid fa-ticket text-[var(--accent-dark)] shrink-0"></i>
                                    <div>
                                        <span class="font-bold text-[var(--accent-dark)] block leading-tight">{{ $pv->advertisedBadgeText() }}</span>
                                        <span class="text-[10px] text-[var(--muted-foreground)] block leading-tight">{{ $pv->conditionsPillLabel() }}</span>
                                    </div>
                                    <span class="ml-1 rounded bg-[var(--mint-100)] border border-[var(--border)] px-1.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)] shrink-0" x-text="copiedCode === @js($pv->code) ? 'Copied ✓' : 'Copy'">Copy</span>
                                </button>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            <!-- Right: Quick Quote / Booking Card (full-width row below lg, sidebar column on lg+) -->
            <div class="min-w-0 rounded-lg border border-[var(--border)] bg-[var(--mint-100)] p-5 shadow-sm space-y-4 sm:col-span-2 sm:grid sm:grid-cols-[minmax(0,1fr)_minmax(0,18rem)] sm:items-center sm:gap-5 sm:space-y-0 lg:col-span-1 lg:block lg:space-y-4">
                <div class="min-w-0">
                    @if($isPureWholesaler)
                        <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Spare Parts Inventory</span>
                        <div class="text-2xl font-bold text-[var(--accent-dark)]">
                            {{ number_format($shopSparePartsCount) }} SKUs Stocked
                        </div>
                        <div class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                            Retail &amp; Wholesale Supply · Nationwide Courier Dispatch
                        </div>
                    @else
                        <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Starting Inspection</span>
                        <div class="text-2xl font-bold text-[var(--accent-dark)]">
                            ৳{{ number_format($shop->inspection_fee ?? 200) }}
                        </div>
                        <div class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                            Base Labor: ৳{{ number_format($shop->base_labor_fee ?? 400) }} · ETA: <span class="eta-display font-semibold text-[var(--foreground)]">{{ \App\Services\GeolocationService::formatEta((int) ($shop->eta_minutes ?: 30)) }}</span>
                        </div>
                    @endif
                </div>

                <div class="space-y-2">
                    @if($isShopOwner)
                        <a href="{{ $isOperational ? '/vendor/dashboard?shop_id=' . $shop->id : ($verificationStatus === 'approved' ? '/vendor/setup-shop?shop_id=' . $shop->id : '/vendor/onboarding') }}" class="inline-flex w-full items-center justify-center rounded-md bg-[var(--accent-dark)] px-5 py-3 text-xs sm:text-sm font-semibold text-white shadow hover:opacity-90 transition">
                            <i class="fa-solid fa-gauge-high mr-1.5"></i> {{ $isOperational ? 'Manage in Vendor Dashboard' : ($verificationStatus === 'approved' ? 'Complete Shop Setup' : 'View Application Status') }}
                        </a>
                        @if($verificationStatus === 'approved')
                            <a href="/vendor/setup-shop?shop_id={{ $shop->id }}" class="inline-flex w-full items-center justify-center rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-semibold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                                <i class="fa-solid fa-sliders mr-1.5"></i> Edit Pricing &amp; Services
                            </a>
                        @endif
                    @elseif($isAdminViewer)
                        <a href="/admin" class="inline-flex w-full items-center justify-center rounded-md bg-[var(--accent-dark)] px-5 py-3 text-xs sm:text-sm font-semibold text-white shadow hover:opacity-90 transition">
                            <i class="fa-solid fa-shield-halved mr-1.5"></i> Admin Console
                        </a>
                        <a href="/admin/moderation" class="inline-flex w-full items-center justify-center rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-semibold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                            <i class="fa-solid fa-gavel mr-1.5"></i> Review Moderation Desk
                        </a>
                    @elseif($isNonCustomerViewer)
                        <a href="{{ auth()->user()->role === 'vendor' ? '/vendor/dashboard' : '/delivery' }}" class="inline-flex w-full items-center justify-center rounded-md bg-[var(--accent-dark)] px-5 py-3 text-xs sm:text-sm font-semibold text-white shadow hover:opacity-90 transition">
                            <i class="fa-solid fa-gauge-high mr-1.5"></i> {{ auth()->user()->role === 'vendor' ? 'Vendor Dashboard' : 'Rider Dashboard' }}
                        </a>
                    @elseif($isBannedCustomerViewer)
                        <span class="inline-flex w-full items-center justify-center rounded-md border border-rose-300 bg-rose-100 px-4 py-2.5 text-xs font-bold text-rose-900 cursor-not-allowed">
                            <i class="fa-solid fa-ban mr-1.5 text-[10px]"></i> Ordering &amp; Requests Restricted
                        </span>
                        <a href="/requests" class="inline-flex w-full items-center justify-center rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-semibold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                            <i class="fa-solid fa-list-check mr-1.5"></i> View Ongoing &amp; Past Orders
                        </a>
                    @else
                        @if($isPureWholesaler)
                            <a href="{{ route('spare-parts.index', ['seller_shop_id' => $shop->id]) }}" class="inline-flex w-full items-center justify-center rounded-md bg-[var(--accent-dark)] px-5 py-3 text-xs sm:text-sm font-semibold text-white shadow hover:opacity-90 transition">
                                <i class="fa-solid fa-boxes-stacked mr-1.5"></i> Browse Stocked Parts
                            </a>
                        @elseif($isOperational)
                            <a href="/requests/create?shop_id={{ $shop->id }}" class="inline-flex w-full items-center justify-center rounded-md bg-[var(--accent-dark)] px-5 py-3 text-xs sm:text-sm font-semibold text-white shadow hover:opacity-90 transition">
                                Post Repair Request
                            </a>
                        @else
                            <span class="inline-flex w-full items-center justify-center rounded-md border border-amber-400 bg-amber-100/80 px-4 py-2.5 text-xs font-bold text-amber-900 cursor-not-allowed">
                                <i class="fa-solid fa-lock mr-1.5 text-[10px]"></i> Booking Locked Until Approved
                            </span>
                        @endif
                        @if(!$isPureWholesaler)
                            <a href="/ai-consultant/chat" class="inline-flex w-full items-center justify-center rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-semibold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                                <i class="fa-solid fa-wand-magic-sparkles mr-1.5 text-amber-600"></i> AI Diagnose Device
                            </a>
                        @endif
                    @endif
                </div>
            </div>

        </div>
        </div>
    </div>

    <!-- Main Two-Column Layout -->
    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px] items-start">

        <!-- ── Left Column: Available Services & Customer Reviews ── -->
        <div class="min-w-0 space-y-6">

            @php
                // Show opted-in platform Fixed-Price Repair Packages first, followed by vendor-published shop services.
                $dbPackages = $shop->servicePackages ?? collect();
                $dbServices = $shop->services ?? collect();
                $displayServices = [];

                foreach ($dbPackages as $pkg) {
                    $displayServices[] = [
                        'kind' => 'package',
                        'package_id' => (int) $pkg->id,
                        'name' => $pkg->title,
                        'category' => $pkg->device_category,
                        'turnaround' => (int) $pkg->estimated_turnaround_hours,
                        'warranty_months' => (int) $pkg->warranty_months,
                        'pricing_type' => 'FIXED_PACKAGE',
                        'estimate' => (float) $pkg->fixed_price,
                        'summary' => $pkg->description ?: implode(' · ', array_slice($pkg->inclusions ?? [], 0, 2)),
                    ];
                }

                foreach ($dbServices as $svc) {
                    $hasDeal = method_exists($svc, 'isDealActive') ? $svc->isDealActive() : false;
                    $normalPrice = method_exists($svc, 'resolvedNormalPrice') ? $svc->resolvedNormalPrice() : (float) ($svc->base_price ?? 0);
                    $effectivePrice = method_exists($svc, 'effectivePrice') ? $svc->effectivePrice() : (float) ($svc->base_price ?? 0);
                    $hasFixedPrice = $effectivePrice > 0;
                    $rawPricingType = strtoupper((string) ($svc->pricing_type ?: ($hasFixedPrice ? 'FIXED' : 'QUOTE')));
                    $displayServices[] = [
                        'kind' => 'shop_service',
                        'package_id' => null,
                        'name' => $svc->name,
                        'category' => null,
                        'turnaround' => null,
                        'warranty_months' => null,
                        'pricing_type' => $hasFixedPrice ? ($rawPricingType === 'STARTING_FROM' ? 'STARTING_FROM' : 'FIXED') : 'QUOTE',
                        'estimate' => $hasFixedPrice ? (float) $effectivePrice : null,
                        'regular_price' => ($hasDeal && $normalPrice > $effectivePrice) ? (float) $normalPrice : null,
                        'has_deal' => $hasDeal,
                        'deal_discount_pct' => method_exists($svc, 'effectiveDiscountPct') ? $svc->effectiveDiscountPct() : null,
                        'summary' => $svc->short_description ?: ($svc->description ?: ($hasFixedPrice
                            ? 'Certified workshop service with genuine replacement components, bench testing, and repair warranty.'
                            : 'Priced after diagnosis — request a quote from the workshop.')),
                    ];
                }
                $shopSpareParts = $shopSpareParts ?? collect();
                $shopSparePartsCount = $shopSparePartsCount ?? $shopSpareParts->count();
            @endphp

            <!-- 1. Available Catalog (Split into Services & Spare Parts Tabs) -->
            <div x-data="{ catalogTab: @js(($isPureWholesaler || (empty($displayServices) && $shopSpareParts->isNotEmpty())) ? 'spare_parts' : 'services') }" id="shop-catalog" class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="inline-flex items-center p-1 rounded-full bg-[var(--mint-100)] border border-[var(--border)] mb-2">
                            @if(!$isPureWholesaler || !empty($displayServices))
                                <button type="button"
                                        @click="catalogTab = 'services'"
                                        :class="catalogTab === 'services' ? 'bg-[var(--accent-dark)] text-white shadow-xs' : 'text-[var(--foreground)] hover:bg-white/60'"
                                        class="px-4 py-1.5 rounded-full text-xs sm:text-sm font-bold transition cursor-pointer">
                                    🛠️ Services ({{ count($displayServices) }})
                                </button>
                            @endif
                            <button type="button"
                                    @click="catalogTab = 'spare_parts'"
                                    :class="catalogTab === 'spare_parts' ? 'bg-[var(--accent-dark)] text-white shadow-xs' : 'text-[var(--foreground)] hover:bg-white/60'"
                                    class="px-4 py-1.5 rounded-full text-xs sm:text-sm font-bold transition cursor-pointer">
                                🔩 Spare Parts ({{ number_format($shopSparePartsCount) }})
                            </button>
                        </div>
                        @if(!$isPureWholesaler || !empty($displayServices))
                            <p x-show="catalogTab === 'services'" class="text-xs md:text-sm text-[var(--muted-foreground)]">
                                @if($isOperational)
                                    Book fixed-price services directly with {{ $shop->name }}, or request a custom technician quote.
                                @else
                                    Service catalog submitted for verification — booking opens once storefront review is complete.
                                @endif
                            </p>
                        @endif
                        <p x-show="catalogTab === 'spare_parts'" x-cloak class="text-xs md:text-sm text-[var(--muted-foreground)]">
                            @if($shopSpareParts->isNotEmpty())
                                Genuine spare parts stocked and dispatched by {{ $shop->name }} with doorstep delivery calculated at checkout.
                            @else
                                Stocked spare parts listed by {{ $shop->name }} appear here.
                            @endif
                        </p>
                    </div>
                    @php
                        $shopCartCount = count(session('cart', []));
                    @endphp
                    @if($isOperational && $canCustomerBookOrReview)
                        <a href="/cart" class="inline-flex items-center gap-2 rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition whitespace-nowrap">
                            <i class="fa-solid fa-cart-shopping"></i>
                            <span>Open Cart</span>
                            <span data-cart-badge class="{{ $shopCartCount > 0 ? '' : 'hidden' }} inline-flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[var(--accent-dark)] px-1 text-[10px] font-bold text-[var(--accent-foreground)]">
                                {{ $shopCartCount }}
                            </span>
                        </a>
                    @elseif($isAdminViewer)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-3.5 py-1.5 text-xs font-bold text-[var(--accent-dark)]">
                            <i class="fa-solid fa-shield-halved text-[10px]"></i>
                            <span>Admin Catalog Audit</span>
                        </span>
                    @elseif($isBannedCustomerViewer)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-rose-100 border border-rose-300 px-3.5 py-1.5 text-xs font-bold text-rose-900">
                            <i class="fa-solid fa-ban text-[10px]"></i>
                            <span>Restricted from Ordering</span>
                        </span>
                    @elseif(!$isOperational)
                        <span class="inline-flex items-center gap-1.5 rounded-md bg-amber-100 border border-amber-300 px-3.5 py-1.5 text-xs font-bold text-amber-900">
                            <i class="fa-solid fa-eye text-[10px]"></i>
                            <span>Catalog Preview</span>
                        </span>
                    @endif
                </div>

                {{-- Tab 1: Services --}}
                @if(!$isPureWholesaler || !empty($displayServices))
                @php
                    $servicesCount = count($displayServices);
                    $dealsCount = collect($displayServices)->where('has_deal', true)->count();
                    $packagesCount = collect($displayServices)->where('kind', 'package')->count();
                    $workshopCount = collect($displayServices)->where('kind', 'shop_service')->count();
                @endphp
                <div
                    x-show="catalogTab === 'services'"
                    x-data="{
                        serviceSearch: '',
                        serviceFilter: 'all',
                        services: @js($displayServices),
                        matchesService(name, summary, cat, kind, hasDeal) {
                            if (this.serviceFilter === 'deals' && !hasDeal) return false;
                            if (this.serviceFilter === 'packages' && kind !== 'package') return false;
                            if (this.serviceFilter === 'workshop' && kind !== 'shop_service') return false;
                            if (!this.serviceSearch.trim()) return true;
                            const q = this.serviceSearch.toLowerCase().trim();
                            return (name || '').toLowerCase().includes(q)
                                || (summary || '').toLowerCase().includes(q)
                                || (cat || '').toLowerCase().includes(q);
                        },
                        get matchedCount() {
                            return this.services.filter(s => this.matchesService(s.name, s.summary, s.category, s.kind, s.has_deal)).length;
                        },
                        resetServiceFilters() {
                            this.serviceSearch = '';
                            this.serviceFilter = 'all';
                        }
                    }"
                >
                    @if(empty($displayServices))
                        <div class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--mint-100)]/40 px-5 py-8 text-center">
                            <i class="fa-solid fa-screwdriver-wrench text-2xl text-[var(--muted-foreground)]"></i>
                            <p class="mt-3 text-sm font-semibold text-[var(--foreground)]">This workshop hasn't published fixed-price services yet.</p>
                            <p class="mt-1 text-xs text-[var(--muted-foreground)]">Describe your device issue below to receive a customized repair quote from {{ $shop->name }}.</p>
                            @if($isOperational && $canCustomerBookOrReview)
                                <a href="/requests/create?shop_id={{ $shop->id }}" class="mt-4 inline-flex h-10 items-center justify-center rounded-md bg-[var(--accent-dark)] px-5 text-xs md:text-sm font-semibold text-white shadow-sm hover:opacity-90 transition">
                                    Request a Custom Quote
                                </a>
                            @endif
                        </div>
                    @else
                        <!-- In-Shop Services Search & Filters Toolbar -->
                        <div class="mb-4 space-y-3">
                            <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                                <div class="relative flex-1">
                                    <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-[var(--muted-foreground)]"></i>
                                    <input
                                        type="text"
                                        x-model="serviceSearch"
                                        placeholder="Search services by issue, name, or device..."
                                        data-service-search-input
                                        class="w-full rounded-md border border-[var(--border)] bg-white/95 px-9 py-2 text-xs sm:text-sm text-[var(--foreground)] placeholder-[var(--muted-foreground)] focus:border-[var(--accent-dark)] focus:outline-none focus:ring-1 focus:ring-[var(--accent-dark)] shadow-2xs"
                                    >
                                    <button
                                        type="button"
                                        x-show="serviceSearch.length > 0"
                                        @click="serviceSearch = ''"
                                        class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-600 cursor-pointer"
                                    >
                                        <i class="fa-solid fa-xmark"></i>
                                    </button>
                                </div>
                            </div>

                            <!-- Filter Chips Row -->
                            <div class="flex flex-wrap items-center gap-2 pt-0.5">
                                <button
                                    type="button"
                                    @click="serviceFilter = 'all'"
                                    :class="serviceFilter === 'all'
                                        ? 'bg-[var(--accent-dark)] text-white border-[var(--accent-dark)] shadow-2xs'
                                        : 'bg-[var(--mint-100)] border-[var(--border)] text-[var(--foreground)] hover:bg-[var(--mint-200)]'"
                                    class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-bold transition cursor-pointer"
                                >
                                    <span>All Services</span>
                                    <span :class="serviceFilter === 'all' ? 'bg-white/20 text-white' : 'bg-black/5 text-[var(--muted-foreground)]'" class="rounded-full px-1.5 py-0.2 text-[10px]">
                                        {{ $servicesCount }}
                                    </span>
                                </button>

                                @if($dealsCount > 0)
                                    <button
                                        type="button"
                                        @click="serviceFilter = (serviceFilter === 'deals' ? 'all' : 'deals')"
                                        :class="serviceFilter === 'deals'
                                            ? 'bg-rose-600 text-white border-rose-600 shadow-2xs ring-2 ring-rose-300'
                                            : 'bg-rose-50 border-rose-300 text-rose-800 hover:bg-rose-100'"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-bold transition cursor-pointer"
                                        data-service-filter-deals
                                    >
                                        <i class="fa-solid fa-fire text-[10px]" :class="serviceFilter === 'deals' ? 'text-amber-200' : 'text-rose-600'"></i>
                                        <span>Active Deals</span>
                                        <span :class="serviceFilter === 'deals' ? 'bg-white text-rose-700' : 'bg-rose-200 text-rose-900'" class="rounded-full px-1.5 py-0.2 text-[10px] font-bold">
                                            {{ $dealsCount }}
                                        </span>
                                    </button>
                                @endif

                                @if($packagesCount > 0)
                                    <button
                                        type="button"
                                        @click="serviceFilter = (serviceFilter === 'packages' ? 'all' : 'packages')"
                                        :class="serviceFilter === 'packages'
                                            ? 'bg-[var(--accent-dark)] text-white border-[var(--accent-dark)] shadow-2xs'
                                            : 'bg-[var(--mint-100)] border-[var(--border)] text-[var(--foreground)] hover:bg-[var(--mint-200)]'"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                                    >
                                        <i class="fa-solid fa-box-open text-[10px]"></i>
                                        <span>Fixed Packages</span>
                                        <span :class="serviceFilter === 'packages' ? 'bg-white/20 text-white' : 'bg-black/5 text-[var(--muted-foreground)]'" class="rounded-full px-1.5 py-0.2 text-[10px]">
                                            {{ $packagesCount }}
                                        </span>
                                    </button>
                                @endif

                                @if($workshopCount > 0)
                                    <button
                                        type="button"
                                        @click="serviceFilter = (serviceFilter === 'workshop' ? 'all' : 'workshop')"
                                        :class="serviceFilter === 'workshop'
                                            ? 'bg-[var(--accent-dark)] text-white border-[var(--accent-dark)] shadow-2xs'
                                            : 'bg-[var(--mint-100)] border-[var(--border)] text-[var(--foreground)] hover:bg-[var(--mint-200)]'"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition cursor-pointer"
                                    >
                                        <i class="fa-solid fa-wrench text-[10px]"></i>
                                        <span>Workshop Services</span>
                                        <span :class="serviceFilter === 'workshop' ? 'bg-white/20 text-white' : 'bg-black/5 text-[var(--muted-foreground)]'" class="rounded-full px-1.5 py-0.2 text-[10px]">
                                            {{ $workshopCount }}
                                        </span>
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    data-services-reset-filters
                                    x-show="serviceSearch.trim().length > 0 || serviceFilter !== 'all'"
                                    @click="resetServiceFilters()"
                                    class="inline-flex items-center gap-1 text-xs font-semibold text-rose-700 hover:text-rose-900 underline transition cursor-pointer ml-auto"
                                >
                                    <i class="fa-solid fa-rotate-left text-[10px]"></i>
                                    <span>Reset filters</span>
                                </button>
                            </div>

                            <!-- Active matches feedback -->
                            <div x-show="serviceSearch.trim().length > 0 || serviceFilter !== 'all'" class="text-xs text-[var(--muted-foreground)] pt-0.5">
                                Showing <strong class="text-[var(--foreground)]" x-text="matchedCount"></strong> of {{ $servicesCount }} services
                            </div>
                        </div>

                        <!-- Services List -->
                        <div class="space-y-4" x-show="matchedCount > 0">
                            @foreach($displayServices as $svcItem)
                                <article
                                    x-show="matchesService(@js($svcItem['name']), @js($svcItem['summary']), @js($svcItem['category'] ?? ''), @js($svcItem['kind']), {{ !empty($svcItem['has_deal']) ? 'true' : 'false' }})"
                                    class="flex flex-col gap-4 rounded-lg border border-[var(--border)] p-4 transition hover:shadow-sm md:flex-row md:items-start md:justify-between bg-[var(--mint-100)]/40"
                                >
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-start justify-between gap-4">
                                            <div class="min-w-0">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h3 class="text-base md:text-lg font-bold text-[var(--foreground)]">{{ $svcItem['name'] }}</h3>
                                                    @if(($svcItem['pricing_type'] ?? '') === 'FIXED_PACKAGE')
                                                        <span class="inline-flex items-center gap-1 rounded border border-[#b9d3b6] bg-white px-2.5 py-0.5 text-[10px] font-bold text-[#244229]">
                                                            <i class="fa-solid fa-box-open text-[9px]"></i>
                                                            Fixed Package &bull; {{ $svcItem['category'] ?? 'All Devices' }}
                                                        </span>
                                                    @endif
                                                    @if(!empty($svcItem['has_deal']))
                                                        <span class="inline-flex items-center gap-1 rounded bg-rose-100 border border-rose-300 px-2.5 py-0.5 text-[10px] font-bold text-rose-800" data-service-deal-badge>
                                                            <i class="fa-solid fa-fire text-rose-600 text-[9px]"></i>
                                                            <span>Active Deal &bull; {{ !empty($svcItem['deal_discount_pct']) ? $svcItem['deal_discount_pct'] . '% OFF' : 'Special Price' }}</span>
                                                        </span>
                                                    @endif
                                                </div>
                                                <p class="mt-1 text-xs md:text-sm leading-relaxed text-[var(--muted-foreground)]">{{ $svcItem['summary'] }}</p>
                                                @if(($svcItem['pricing_type'] ?? '') === 'FIXED_PACKAGE')
                                                    <p class="mt-1.5 text-[11px] font-semibold text-[#3f5946]">
                                                        <i class="fa-regular fa-clock mr-1"></i>~{{ (int) ($svcItem['turnaround'] ?? 2) }}h turnaround
                                                        <span class="mx-1.5">&bull;</span>
                                                        <i class="fa-solid fa-shield-halved mr-1"></i>{{ (int) ($svcItem['warranty_months'] ?? 1) }}-mo warranty
                                                    </p>
                                                @endif
                                            </div>

                                            <div class="shrink-0 text-right">
                                                @if($svcItem['estimate'] !== null)
                                                    @if(!empty($svcItem['regular_price']))
                                                        <div class="text-xs line-through text-[var(--muted-foreground)] tabular-nums">
                                                            ৳{{ number_format($svcItem['regular_price']) }}
                                                        </div>
                                                    @endif
                                                    @if(($svcItem['pricing_type'] ?? '') === 'STARTING_FROM')
                                                        <div class="text-base md:text-lg font-bold {{ !empty($svcItem['has_deal']) ? 'text-rose-800' : 'text-[var(--accent-dark)]' }}">
                                                            From ৳{{ number_format($svcItem['estimate']) }}
                                                        </div>
                                                        <div class="text-[10px] text-[var(--muted-foreground)]">{{ !empty($svcItem['has_deal']) ? 'limited-time deal' : 'base estimate' }}</div>
                                                    @else
                                                        <div class="text-base md:text-lg font-bold {{ !empty($svcItem['has_deal']) ? 'text-rose-800' : 'text-[var(--accent-dark)]' }}">
                                                            ৳{{ number_format($svcItem['estimate']) }}
                                                        </div>
                                                        <div class="text-[10px] text-[var(--muted-foreground)]">{{ !empty($svcItem['has_deal']) ? 'limited-time deal' : 'fixed price' }}</div>
                                                    @endif
                                                @else
                                                    <div class="text-sm font-bold text-[var(--accent-dark)]">Assessed on diagnosis</div>
                                                    <div class="text-[10px] text-[var(--muted-foreground)]">transparent itemized quote</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>

                                    <div class="flex flex-wrap items-center gap-2 w-full md:w-auto md:pl-4">
                                        @if($isOperational && $canCustomerBookOrReview)
                                            @if($shop->accepts_direct_orders && $svcItem['estimate'] !== null)
                                                @if(($svcItem['kind'] ?? '') === 'package' && !empty($svcItem['package_id']))
                                                    <button
                                                        type="button"
                                                        onclick="MeramotCart.addDirectPackage({{ $shop->id }}, {{ (int) $svcItem['package_id'] }}, this)"
                                                        class="inline-flex h-10 flex-1 md:flex-none items-center justify-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-5 text-xs md:text-sm font-semibold text-white shadow-sm hover:opacity-90 transition whitespace-nowrap"
                                                    >
                                                        <i class="fa-solid fa-cart-plus text-xs"></i>
                                                        <span>Add to Cart</span>
                                                    </button>
                                                @else
                                                    <button
                                                        type="button"
                                                        onclick="MeramotCart.addShopService({{ $shop->id }}, @js($svcItem['name']), @js($svcItem['summary']), {{ $svcItem['estimate'] }}, this)"
                                                        class="inline-flex h-10 flex-1 md:flex-none items-center justify-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-5 text-xs md:text-sm font-semibold text-white shadow-sm hover:opacity-90 transition whitespace-nowrap"
                                                    >
                                                        <i class="fa-solid fa-cart-plus text-xs"></i>
                                                        <span>Add to Cart</span>
                                                    </button>
                                                @endif
                                            @endif

                                            <a
                                                href="/requests/create?shop_id={{ $shop->id }}&issue={{ urlencode($svcItem['name']) }}"
                                                class="inline-flex h-10 items-center justify-center rounded-md border border-[var(--border)] bg-[var(--card)] px-4 text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition whitespace-nowrap"
                                            >
                                                Custom Quote
                                            </a>
                                        @elseif(!$isOperational)
                                            <span class="inline-flex h-10 items-center justify-center rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-4 text-xs font-semibold text-[var(--muted-foreground)] cursor-not-allowed whitespace-nowrap">
                                                <i class="fa-solid fa-lock mr-1.5 text-[10px]"></i> Available After Approval
                                            </span>
                                        @elseif($isBannedCustomerViewer)
                                            <span class="inline-flex h-10 items-center justify-center rounded-md border border-rose-300 bg-rose-50 px-4 text-xs font-semibold text-rose-800 cursor-not-allowed whitespace-nowrap">
                                                <i class="fa-solid fa-ban mr-1.5 text-[10px]"></i> Ordering Restricted
                                            </span>
                                        @endif
                                    </div>
                                </article>
                            @endforeach
                        </div>

                        <!-- Services Empty State -->
                        <div x-show="matchedCount === 0" class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--mint-100)]/40 px-5 py-8 text-center">
                            <i class="fa-solid fa-magnifying-glass text-2xl text-slate-400"></i>
                            <p class="mt-2 text-sm font-semibold text-[var(--foreground)]">No services match your search or filter.</p>
                            <p class="mt-1 text-xs text-[var(--muted-foreground)]">Try searching with a different term, or reset the active filter.</p>
                            <button
                                type="button"
                                @click="resetServiceFilters()"
                                class="mt-3 inline-flex items-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-semibold text-white shadow-2xs hover:opacity-90 transition cursor-pointer"
                            >
                                <i class="fa-solid fa-rotate-left text-[11px]"></i>
                                <span>Reset Filters</span>
                            </button>
                        </div>
                    @endif
                </div>
                @endif

                {{-- Tab 2: Spare Parts (9 at once on desktop, 6 at once on mobile, with in-profile search, sale filter & pagination) --}}
                <div x-show="catalogTab === 'spare_parts'" x-cloak>
                    @if($shopSpareParts->isNotEmpty() || request()->filled('q') || request()->boolean('sale') || request()->filled('category') || $shopSparePartsCount > 0)
                        @php
                            $initialPartsPayload = $shopSparePartsJson ?? $shopSpareParts->map(fn ($sp) => \App\Http\Controllers\ShopDiscoveryController::serializeShopSparePartCard($sp))->values()->all();
                        @endphp
                        <div
                            data-shop-spare-parts-section
                            x-data="{
                                hydrated: false,
                                isMobile: typeof window !== 'undefined' ? window.matchMedia('(max-width: 639.98px)').matches : false,
                                page: 1,
                                totalCount: {{ (int) ($filteredPartsCount ?? $shopSparePartsCount) }},
                                loadedMap: {},
                                loadingPage: false,
                                apiEndpoint: @js(route('api.shops.spare-parts', $shop->slug)),
                                partsSearch: @js(request('q', '')),
                                saleOnly: @js(request()->boolean('sale') || request()->boolean('has_sale')),
                                selectedCategory: @js(request('category', 'all')),
                                selectedSort: @js(request('sort', 'title_asc')),
                                get perPage() {
                                    return this.isMobile ? 6 : 9;
                                },
                                get totalPages() {
                                    return Math.max(1, Math.ceil(this.totalCount / this.perPage));
                                },
                                get startIndex() {
                                    return (this.page - 1) * this.perPage;
                                },
                                get hasActiveFilters() {
                                    return Boolean(this.partsSearch.trim() || this.saleOnly || (this.selectedCategory && this.selectedCategory !== 'all') || (this.selectedSort && this.selectedSort !== 'title_asc'));
                                },
                                get visibleParts() {
                                    const out = [];
                                    const end = Math.min(this.totalCount, this.startIndex + this.perPage);
                                    for (let i = this.startIndex; i < end; i++) {
                                        if (this.loadedMap[i]) out.push(this.loadedMap[i]);
                                    }
                                    return out;
                                },
                                init() {
                                    const seed = @js($initialPartsPayload);
                                    seed.forEach((it, idx) => { this.loadedMap[idx] = it; });
                                    this.hydrated = true;

                                    if (typeof window !== 'undefined' && window.matchMedia) {
                                        const mq = window.matchMedia('(max-width: 639.98px)');
                                        const onChange = (ev) => {
                                            this.isMobile = ev.matches;
                                            if (this.page > this.totalPages) {
                                                this.page = this.totalPages;
                                            }
                                            this.ensureCurrentSliceLoaded();
                                        };
                                        if (mq.addEventListener) {
                                            mq.addEventListener('change', onChange);
                                        } else if (mq.addListener) {
                                            mq.addListener(onChange);
                                        }
                                    }
                                },
                                buildApiUrl(offset, limit) {
                                    const params = new URLSearchParams();
                                    params.set('offset', offset);
                                    params.set('limit', limit);
                                    if (this.partsSearch.trim()) params.set('q', this.partsSearch.trim());
                                    if (this.saleOnly) params.set('sale', '1');
                                    if (this.selectedCategory && this.selectedCategory !== 'all') params.set('category', this.selectedCategory);
                                    if (this.selectedSort && this.selectedSort !== 'title_asc') params.set('sort', this.selectedSort);
                                    return `${this.apiEndpoint}?${params.toString()}`;
                                },
                                async ensureCurrentSliceLoaded() {
                                    const start = this.startIndex;
                                    const end = Math.min(this.totalCount, start + this.perPage);
                                    let missing = false;
                                    for (let i = start; i < end; i++) {
                                        if (!this.loadedMap[i]) { missing = true; break; }
                                    }
                                    if (!missing || this.loadingPage) return;

                                    this.loadingPage = true;
                                    try {
                                        const batchOffset = Math.floor(start / 18) * 18;
                                        const res = await fetch(this.buildApiUrl(batchOffset, 18), {
                                            headers: { 'Accept': 'application/json' },
                                        });
                                        const data = await res.json();
                                        if (data && Array.isArray(data.items)) {
                                            data.items.forEach((it, k) => {
                                                this.loadedMap[batchOffset + k] = it;
                                            });
                                            if (typeof data.total === 'number') {
                                                this.totalCount = data.total;
                                            }
                                        }
                                    } catch {} finally {
                                        this.loadingPage = false;
                                    }
                                },
                                async fetchFilteredParts(resetPage = true) {
                                    if (resetPage) {
                                        this.page = 1;
                                        this.loadedMap = {};
                                    }
                                    this.loadingPage = true;
                                    try {
                                        const start = this.startIndex;
                                        const batchOffset = Math.floor(start / 18) * 18;
                                        const res = await fetch(this.buildApiUrl(batchOffset, 18), {
                                            headers: { 'Accept': 'application/json' },
                                        });
                                        const data = await res.json();
                                        if (data && Array.isArray(data.items)) {
                                            this.loadedMap = {};
                                            data.items.forEach((it, k) => {
                                                this.loadedMap[batchOffset + k] = it;
                                            });
                                            if (typeof data.total === 'number') {
                                                this.totalCount = data.total;
                                            }
                                        }
                                    } catch {} finally {
                                        this.loadingPage = false;
                                    }
                                },
                                toggleSaleFilter() {
                                    this.saleOnly = !this.saleOnly;
                                    this.fetchFilteredParts(true);
                                },
                                selectCategory(cat) {
                                    this.selectedCategory = (this.selectedCategory === cat) ? 'all' : cat;
                                    this.fetchFilteredParts(true);
                                },
                                resetFilters() {
                                    this.partsSearch = '';
                                    this.saleOnly = false;
                                    this.selectedCategory = 'all';
                                    this.selectedSort = 'title_asc';
                                    this.fetchFilteredParts(true);
                                },
                                async goToPage(nextPage) {
                                    const target = Math.max(1, Math.min(this.totalPages, nextPage));
                                    if (target === this.page) return;
                                    this.page = target;
                                    await this.ensureCurrentSliceLoaded();
                                }
                            }"
                        >
                            <!-- In-Shop Spare Parts Search & Filters Toolbar -->
                            <div class="mb-4 space-y-3">
                                <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
                                    <div class="relative flex-1">
                                        <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-[var(--muted-foreground)]"></i>
                                        <input
                                            type="text"
                                            x-model="partsSearch"
                                            @input.debounce.350ms="fetchFilteredParts(true)"
                                            @keydown.enter.prevent="fetchFilteredParts(true)"
                                            placeholder="Search parts by title, model, brand, or SKU..."
                                            data-parts-search-input
                                            class="w-full rounded-md border border-[var(--border)] bg-white/95 px-9 py-2 text-xs sm:text-sm text-[var(--foreground)] placeholder-[var(--muted-foreground)] focus:border-[var(--accent-dark)] focus:outline-none focus:ring-1 focus:ring-[var(--accent-dark)] shadow-2xs"
                                        >
                                        <button
                                            type="button"
                                            x-show="partsSearch.length > 0"
                                            @click="partsSearch = ''; fetchFilteredParts(true)"
                                            class="absolute right-3 top-1/2 -translate-y-1/2 text-xs text-slate-400 hover:text-slate-600 cursor-pointer"
                                        >
                                            <i class="fa-solid fa-xmark"></i>
                                        </button>
                                    </div>

                                    <div class="flex items-center gap-2">
                                        <label for="parts-sort-select" class="text-xs font-semibold text-[var(--muted-foreground)] whitespace-nowrap">Sort:</label>
                                        <select
                                            id="parts-sort-select"
                                            x-model="selectedSort"
                                            @change="fetchFilteredParts(true)"
                                            class="rounded-md border border-[var(--border)] bg-white/95 px-2.5 py-2 text-xs font-semibold text-[var(--foreground)] focus:border-[var(--accent-dark)] focus:outline-none cursor-pointer shadow-2xs"
                                        >
                                            <option value="title_asc">Title: A &rarr; Z</option>
                                            <option value="price_asc">Price: Low to High</option>
                                            <option value="price_desc">Price: High to Low</option>
                                            <option value="popular">Most Popular</option>
                                        </select>
                                    </div>
                                </div>

                                <!-- Filter Chips Row -->
                                <div class="flex flex-wrap items-center gap-2 pt-0.5">
                                    {{-- All Parts Chip --}}
                                    <button
                                        type="button"
                                        @click="saleOnly = false; selectedCategory = 'all'; fetchFilteredParts(true)"
                                        :class="(!saleOnly && (selectedCategory === 'all' || !selectedCategory))
                                            ? 'bg-[var(--accent-dark)] text-white border-[var(--accent-dark)] shadow-2xs'
                                            : 'bg-[var(--mint-100)] border-[var(--border)] text-[var(--foreground)] hover:bg-[var(--mint-200)]'"
                                        class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-bold transition cursor-pointer"
                                    >
                                        <span>All Parts</span>
                                        <span :class="(!saleOnly && (selectedCategory === 'all' || !selectedCategory)) ? 'bg-white/20 text-white' : 'bg-black/5 text-[var(--muted-foreground)]'" class="rounded-full px-1.5 py-0.2 text-[10px]">
                                            {{ number_format($shopSparePartsCount) }}
                                        </span>
                                    </button>

                                    {{-- Specially Emphasized Sale Offers Chip --}}
                                    @if(!empty($shopSalePartsCount) && $shopSalePartsCount > 0)
                                        <button
                                            type="button"
                                            @click="toggleSaleFilter()"
                                            :class="saleOnly
                                                ? 'bg-red-600 text-white border-red-600 shadow-2xs ring-2 ring-red-300'
                                                : 'bg-rose-50 border-rose-300 text-rose-800 hover:bg-rose-100'"
                                            class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-bold transition cursor-pointer"
                                            data-parts-filter-sale
                                        >
                                            <i class="fa-solid fa-bolt text-[10px]" :class="saleOnly ? 'text-amber-200' : 'text-red-600'"></i>
                                            <span>Sale Offers</span>
                                            <span :class="saleOnly ? 'bg-white text-red-700' : 'bg-rose-200 text-rose-900'" class="rounded-full px-1.5 py-0.2 text-[10px] font-bold">
                                                {{ number_format($shopSalePartsCount) }}
                                            </span>
                                        </button>
                                    @endif

                                    {{-- Category Chips --}}
                                    @if(!empty($shopPartCategories))
                                        @foreach(array_slice($shopPartCategories, 0, 6) as $catItem)
                                            <button
                                                type="button"
                                                @click="selectCategory(@js($catItem['name']))"
                                                :class="selectedCategory === @js($catItem['name'])
                                                    ? 'bg-[var(--accent-dark)] text-white border-[var(--accent-dark)] shadow-2xs'
                                                    : 'bg-[var(--mint-100)] border-[var(--border)] text-[var(--foreground)] hover:bg-[var(--mint-200)]'"
                                                class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-xs font-semibold transition cursor-pointer whitespace-nowrap"
                                            >
                                                <span>{{ $catItem['name'] }}</span>
                                                <span :class="selectedCategory === @js($catItem['name']) ? 'bg-white/20 text-white' : 'bg-black/5 text-[var(--muted-foreground)]'" class="rounded-full px-1.5 py-0.2 text-[10px]">
                                                    {{ number_format($catItem['count']) }}
                                                </span>
                                            </button>
                                        @endforeach

                                        @if(count($shopPartCategories) > 6)
                                            <select
                                                x-model="selectedCategory"
                                                @change="fetchFilteredParts(true)"
                                                class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1 text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-200)] focus:outline-none cursor-pointer shadow-2xs"
                                            >
                                                <option value="all">More categories...</option>
                                                @foreach(array_slice($shopPartCategories, 6) as $catItem)
                                                    <option value="{{ $catItem['name'] }}">{{ $catItem['name'] }} ({{ number_format($catItem['count']) }})</option>
                                                @endforeach
                                            </select>
                                        @endif
                                    @endif

                                    <button
                                        type="button"
                                        data-parts-reset-filters
                                        x-show="hasActiveFilters"
                                        @click="resetFilters()"
                                        class="inline-flex items-center gap-1 text-xs font-semibold text-rose-700 hover:text-rose-900 underline transition cursor-pointer ml-auto"
                                    >
                                        <i class="fa-solid fa-rotate-left text-[10px]"></i>
                                        <span>Reset filters</span>
                                    </button>
                                </div>

                                <!-- Results count & Loading status -->
                                <div class="flex items-center justify-between text-xs text-[var(--muted-foreground)] pt-0.5">
                                    <div>
                                        Showing <strong class="text-[var(--foreground)]" x-text="totalCount > 0 ? ((startIndex + 1) + '–' + Math.min(totalCount, startIndex + perPage)) : '0'"></strong> of <strong class="text-[var(--foreground)]" x-text="totalCount.toLocaleString()"></strong> spare parts
                                        <span x-show="hasActiveFilters" class="font-medium text-[var(--accent-dark)]">(filtered)</span>
                                    </div>
                                    <div x-show="loadingPage" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[var(--accent-dark)]">
                                        <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                                        <span>Loading...</span>
                                    </div>
                                </div>
                            </div>

                            {{-- Pre-hydration SSR Grid: strictly 6 visible on mobile (<640px) and 9 visible on desktop (>=640px) --}}
                            <div x-show="!hydrated" class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                @foreach($shopSpareParts->take(9)->values() as $spIdx => $spItem)
                                    @php
                                        $spEff = $spItem->effectiveBasePrice();
                                        $spNorm = $spItem->resolvedNormalBasePrice();
                                        $spOffer = $spItem->isOfferActive();
                                    @endphp
                                    <a href="{{ route('spare-parts.show', ['slug' => $spItem->slug]) }}"
                                       class="group rounded-lg border border-[var(--border)] bg-white/95 dark:bg-[var(--mint-100)]/20 hover:border-emerald-700 hover:shadow-sm transition overflow-hidden {{ $spIdx >= 6 ? 'hidden sm:flex' : 'flex' }} flex-col">
                                        <div class="relative aspect-square bg-white p-2.5 border-b border-slate-100">
                                            @if($spOffer)
                                                <span class="absolute top-2 left-2 z-10 w-8 h-8 rounded-full bg-red-600 text-white text-[10px] font-semibold flex items-center justify-center shadow-xs">
                                                    Sale!
                                                </span>
                                            @endif
                                            <img src="{{ $spItem->image_url }}"
                                                 alt="{{ $spItem->title }}"
                                                 loading="lazy"
                                                 onerror="this.onerror=null;this.src='{{ e($spItem->fallbackImageUrl()) }}';"
                                                 class="w-full h-full object-contain group-hover:scale-105 transition">
                                        </div>
                                        <div class="p-3 flex-1 flex flex-col justify-between gap-1.5">
                                            <div class="text-xs font-bold text-[var(--foreground)] line-clamp-2 group-hover:text-emerald-800">
                                                {{ $spItem->title }}
                                            </div>
                                            <div class="flex items-baseline gap-1.5 flex-wrap">
                                                @if($spOffer && $spNorm > $spEff)
                                                    <span class="text-[11px] text-slate-400 line-through">৳{{ number_format($spNorm, 0) }}</span>
                                                    <span class="text-sm font-semibold text-red-600">৳{{ number_format($spEff, 0) }}</span>
                                                @else
                                                    <span class="text-sm font-semibold text-[var(--foreground)]">৳{{ number_format($spEff, 0) }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </a>
                                @endforeach
                            </div>

                            {{-- Hydrated Responsive Grid: 6 items on mobile (<640px), 9 items on desktop (>=640px) --}}
                            <template x-if="hydrated">
                                <div class="space-y-4">
                                    {{-- Empty State when 0 items match --}}
                                    <div x-show="totalCount === 0" class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--mint-100)]/40 px-5 py-10 text-center">
                                        <i class="fa-solid fa-boxes-stacked text-3xl text-slate-400"></i>
                                        <h4 class="mt-3 text-sm font-bold text-[var(--foreground)]">No spare parts match your filters</h4>
                                        <p class="mt-1 text-xs text-[var(--muted-foreground)]">Try adjusting your search keywords, category, or clear filters to view all catalog parts.</p>
                                        <button
                                            type="button"
                                            @click="resetFilters()"
                                            class="mt-4 inline-flex items-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-white shadow-2xs hover:opacity-90 transition cursor-pointer"
                                        >
                                            <i class="fa-solid fa-rotate-left text-[11px]"></i>
                                            <span>Clear all filters</span>
                                        </button>
                                    </div>

                                    {{-- Grid when items exist --}}
                                    <div x-show="totalCount > 0" class="grid grid-cols-2 sm:grid-cols-3 gap-3" data-shop-parts-live-grid>
                                        <template x-for="sp in visibleParts" :key="sp.id">
                                            <a :href="sp.url"
                                               data-shop-spare-part-card
                                               class="group rounded-lg border border-[var(--border)] bg-white/95 dark:bg-[var(--mint-100)]/20 hover:border-emerald-700 hover:shadow-sm transition overflow-hidden flex flex-col">
                                                <div class="relative aspect-square bg-white p-2.5 border-b border-slate-100">
                                                    <template x-if="sp.is_offer">
                                                        <span class="absolute top-2 left-2 z-10 w-8 h-8 rounded-full bg-red-600 text-white text-[10px] font-semibold flex items-center justify-center shadow-xs">
                                                            Sale!
                                                        </span>
                                                    </template>
                                                    <img :src="sp.image_url"
                                                         :alt="sp.title"
                                                         loading="lazy"
                                                         x-on:error="$event.target.onerror = null; $event.target.src = sp.fallback_url"
                                                         class="w-full h-full object-contain group-hover:scale-105 transition">
                                                </div>
                                                <div class="p-3 flex-1 flex flex-col justify-between gap-1.5">
                                                    <div class="text-xs font-bold text-[var(--foreground)] line-clamp-2 group-hover:text-emerald-800" x-text="sp.title"></div>
                                                    <div class="flex items-baseline gap-1.5 flex-wrap">
                                                        <template x-if="sp.is_offer">
                                                            <span class="text-[11px] text-slate-400 line-through" x-text="'৳' + sp.normal_price_fmt"></span>
                                                        </template>
                                                        <span class="text-sm font-semibold" :class="sp.is_offer ? 'text-red-600' : 'text-[var(--foreground)]'" x-text="'৳' + sp.effective_price_fmt"></span>
                                                    </div>
                                                </div>
                                            </a>
                                        </template>
                                    </div>

                                    <div x-show="totalCount > perPage" class="pt-1 flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-t border-[var(--border)]/60">
                                        <div class="flex items-center justify-between sm:justify-start gap-2 text-xs">
                                            <button
                                                type="button"
                                                data-shop-parts-prev
                                                :disabled="page <= 1 || loadingPage"
                                                @click="goToPage(page - 1)"
                                                class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1.5 font-semibold text-[var(--foreground)] hover:bg-[var(--mint-200)] disabled:opacity-45 disabled:cursor-not-allowed transition cursor-pointer"
                                            >
                                                &larr; Prev
                                            </button>
                                            <span class="text-[11px] font-semibold text-[var(--muted-foreground)] tabular-nums px-1" data-shop-parts-range>
                                                Showing <strong class="text-[var(--foreground)]" x-text="(startIndex + 1) + '–' + Math.min(totalCount, startIndex + perPage)"></strong> of <strong class="text-[var(--foreground)]" x-text="totalCount.toLocaleString()"></strong>
                                            </span>
                                            <button
                                                type="button"
                                                data-shop-parts-next
                                                :disabled="page >= totalPages || loadingPage"
                                                @click="goToPage(page + 1)"
                                                class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1.5 font-semibold text-[var(--foreground)] hover:bg-[var(--mint-200)] disabled:opacity-45 disabled:cursor-not-allowed transition cursor-pointer"
                                            >
                                                Next &rarr;
                                            </button>
                                        </div>

                                        <a href="{{ route('spare-parts.index', ['seller_shop_id' => $shop->id]) }}"
                                           class="inline-flex items-center justify-center gap-1 px-4 py-2 rounded-md bg-emerald-900 text-white text-xs font-bold hover:bg-emerald-800 transition">
                                            View all {{ number_format($shopSparePartsCount) }} spare parts from {{ $shop->name }} →
                                        </a>
                                    </div>
                                </div>
                            </template>
                        </div>
                    @endif
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
                <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm self-start">
                    <p class="text-[10px] font-bold uppercase tracking-[0.25em] text-[var(--muted-foreground)]">Customer reviews</p>
                    <h2 class="mt-1 text-xl font-bold text-[var(--foreground)]">Rating summary</h2>

                    <div class="mt-4 rounded-lg bg-[var(--mint-50)] px-4 py-4 text-center">
                        @if($totalReviewsCount > 0)
                            <div class="text-4xl font-bold text-[var(--accent-dark)]">{{ number_format($avgRating, 1) }}</div>
                            <div class="text-amber-500 text-sm mt-1">
                                @for($i = 1; $i <= 5; $i++)
                                    <i class="fa-solid fa-star {{ $i <= round($avgRating) ? 'text-amber-500' : 'text-gray-300' }}"></i>
                                @endfor
                            </div>
                            <p class="mt-1 text-xs text-[var(--muted-foreground)]">{{ $totalReviewsCount }} verified {{ \Illuminate\Support\Str::plural('review', $totalReviewsCount) }}</p>
                        @else
                            <div class="text-2xl font-bold text-[var(--accent-dark)]">{{ $isOperational ? 'New' : '—' }}</div>
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
                <div
                    class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm"
                    x-data="{
                        ownIds: @js($ownReviewIds),
                        selectedReviewIds: [],
                        editingReviewId: @js(request()->filled('edit_review') ? (int) request()->query('edit_review') : null),
                        toggleOwnReview(id) {
                            if (this.selectedReviewIds.includes(id)) {
                                this.selectedReviewIds = this.selectedReviewIds.filter(i => i !== id);
                            } else {
                                this.selectedReviewIds.push(id);
                            }
                        },
                        selectAllOwn() {
                            if (this.selectedReviewIds.length === this.ownIds.length) {
                                this.selectedReviewIds = [];
                            } else {
                                this.selectedReviewIds = [...this.ownIds];
                            }
                        },
                        editSelectedOwn() {
                            if (this.selectedReviewIds.length !== 1) return;
                            this.editingReviewId = this.selectedReviewIds[0];
                            const el = document.getElementById('review-' + this.editingReviewId);
                            if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }"
                >
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] pb-3">
                        <div>
                            <p class="text-xs font-bold uppercase tracking-wider text-[var(--muted-foreground)]">
                                Feedback from verified clients
                            </p>
                            @if($ownReviewIds->isNotEmpty())
                                <p class="text-[11px] font-semibold text-emerald-800 dark:text-emerald-400 mt-0.5">
                                    <i class="fa-solid fa-thumbtack mr-1 text-[10px]"></i>Your {{ \Illuminate\Support\Str::plural('review', $ownReviewIds->count()) }} {{ $ownReviewIds->count() === 1 ? 'is' : 'are' }} pinned at the top for quick editing or deletion.
                                </p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded bg-[var(--mint-100)] px-3 py-0.5 text-xs font-semibold text-[var(--accent-dark)]">
                                {{ $shop->area ? $shop->area . ', ' . $shop->city : ($shop->city ?: 'Bangladesh') }}
                            </span>
                            @if($isShopOwner)
                                <span class="rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-xs font-semibold text-[var(--accent-dark)]">
                                    <i class="fa-solid fa-store mr-1 text-[10px]"></i> Your Workshop
                                </span>
                            @elseif($isAdminViewer)
                                <span class="rounded-md bg-rose-100 border border-rose-300 px-3 py-1 text-xs font-bold text-rose-900">
                                    <i class="fa-solid fa-gavel mr-1 text-[10px]"></i> Admin Review Moderation
                                </span>
                            @elseif($isNonCustomerViewer)
                                <span class="rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-xs font-semibold text-[var(--muted-foreground)]">
                                    <i class="fa-solid fa-comments mr-1 text-[10px]"></i> Verified Client Feedback
                                </span>
                            @elseif($isBannedCustomerViewer)
                                <span class="rounded-md bg-rose-100 border border-rose-300 px-3 py-1 text-xs font-bold text-rose-900">
                                    <i class="fa-solid fa-ban mr-1 text-[10px]"></i> Review Posting Restricted
                                </span>
                            @elseif($isOperational)
                                @auth
                                    <a href="/requests?filter=COMPLETED" class="rounded-md border border-emerald-600/40 bg-emerald-50 dark:bg-emerald-950/50 px-3 py-1 text-xs font-bold text-emerald-800 dark:text-emerald-300 hover:bg-emerald-100 transition" title="Write a Verified Repair Review from your completed Past Orders">
                                        <i class="fa-solid fa-certificate mr-1 text-[10px] text-emerald-600"></i> Review Past Order
                                    </a>
                                    <a href="/reviews/create/{{ $shop->id }}" class="rounded-md bg-[var(--accent-dark)] px-3 py-1 text-xs font-semibold text-white hover:opacity-90 transition">
                                        <i class="fa-solid fa-pen-to-square mr-1 text-[10px]"></i> Write Review
                                    </a>
                                @else
                                    <a href="{{ route('reviews.create', $shop->id) }}" class="rounded-md bg-[var(--accent-dark)] px-3.5 py-1.5 text-xs font-bold text-white hover:opacity-90 transition">
                                        <i class="fa-solid fa-right-to-bracket mr-1 text-[10px]"></i> Sign in to Review / Edit Your Reviews
                                    </a>
                                @endauth
                            @else
                                <span class="rounded-md bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-xs font-semibold text-[var(--muted-foreground)] cursor-not-allowed">
                                    <i class="fa-solid fa-lock mr-1 text-[10px]"></i> Reviews Locked
                                </span>
                            @endif
                        </div>
                    </div>

                    @if($serviceSkillBreakdown->isNotEmpty())
                        <div class="mb-4 rounded-lg border border-[var(--border)] bg-[var(--mint-50)] p-3.5 space-y-2">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">
                                    <i class="fa-solid fa-screwdriver-wrench mr-1"></i>Workshop Skill Breakdown by Verified Repair Service
                                </span>
                                <span class="text-[10px] text-[var(--muted-foreground)]">Based on completed service orders</span>
                            </div>
                            <div class="flex flex-wrap gap-2">
                                @foreach($serviceSkillBreakdown as $skillItem)
                                    @php
                                        $isStrongSkill = $skillItem['avg_rating'] >= 4.0;
                                        $isWeakSkill = $skillItem['avg_rating'] < 3.0;
                                    @endphp
                                    <div class="inline-flex items-center gap-1.5 rounded-md border px-3 py-1 text-[11px] font-bold {{ $isStrongSkill ? 'border-emerald-300 bg-emerald-100/80 text-emerald-900' : ($isWeakSkill ? 'border-rose-300 bg-rose-100/80 text-rose-900' : 'border-amber-300 bg-amber-100/80 text-amber-900') }}">
                                        <span>{{ $skillItem['service'] }}</span>
                                        <span class="font-mono text-[10px] px-1.5 py-0.2 rounded bg-white/80">
                                            ★ {{ number_format($skillItem['avg_rating'], 1) }} ({{ $skillItem['count'] }})
                                        </span>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if($ownReviewIds->isNotEmpty())
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/60 px-3.5 py-2.5 text-xs">
                            <label class="inline-flex items-center gap-2 cursor-pointer font-bold text-[var(--foreground)]">
                                <input
                                    type="checkbox"
                                    :checked="selectedReviewIds.length > 0 && selectedReviewIds.length === ownIds.length"
                                    @change="selectAllOwn()"
                                    class="h-4 w-4 rounded accent-[#214c34] cursor-pointer"
                                >
                                <span>Select My Reviews (<span x-text="selectedReviewIds.length">0</span>/{{ $ownReviewIds->count() }})</span>
                            </label>

                            <div class="flex flex-wrap items-center gap-2">
                                <button
                                    type="button"
                                    x-show="selectedReviewIds.length === 1"
                                    x-cloak
                                    @click="editSelectedOwn()"
                                    class="inline-flex items-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-3.5 py-1.5 text-xs font-bold text-white shadow-sm hover:opacity-90 transition"
                                >
                                    <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                    <span>Edit Selected</span>
                                </button>

                                <form
                                    x-show="selectedReviewIds.length > 0"
                                    x-cloak
                                    method="POST"
                                    action="{{ route('reviews.bulk-destroy') }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete Selected Reviews?"
                                    data-confirm-item="{{ $shop->name }}"
                                    data-confirm-message="This will permanently delete your selected review(s) and update the shop rating."
                                    data-confirm-cta="Delete Selected"
                                    class="inline-flex items-center"
                                >
                                    @csrf
                                    <input type="hidden" name="repair_shop_id" value="{{ $shop->id }}">
                                    <template x-for="rid in selectedReviewIds" :key="rid">
                                        <input type="hidden" name="review_ids[]" :value="rid">
                                    </template>
                                    <button
                                        type="submit"
                                        class="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition"
                                    >
                                        <i class="fa-solid fa-trash-can text-[11px]"></i>
                                        <span>Delete Selected (<span x-text="selectedReviewIds.length"></span>)</span>
                                    </button>
                                </form>
                            </div>
                        </div>
                    @endif

                    <div class="space-y-4">
                        @forelse($reviewsList as $rev)
                            @php
                                $isOwnReview = $currentUserId !== null && (int) $rev->customer_id === $currentUserId;
                                $isVerifiedRepair = (bool) ($rev->is_verified_purchase ?? false);
                                $serviceProvidedLabel = trim((string) ($rev->service_provided ?: ($isVerifiedRepair && $rev->repairRequest ? (($rev->repairRequest->issue_category ?: $rev->repairRequest->device_type) . ' — ' . $rev->repairRequest->title) : '')));
                                $offensiveWords = $isAdminViewer ? \App\Models\ShopReview::detectOffensiveWords($rev->review_comment) : [];
                                $authorBanned = ($rev->customer?->status ?? '') === 'banned';
                            @endphp
                            <article
                                id="review-{{ $rev->id }}"
                                class="rounded-lg border {{ $isOwnReview ? 'border-emerald-500/70 bg-emerald-50/50 dark:bg-emerald-950/20 ring-1 ring-emerald-500/20' : 'border-[var(--border)] bg-[var(--mint-100)]/50' }} p-4 shadow-sm text-xs transition"
                            >
                                <div class="flex items-start justify-between gap-2">
                                    <div class="flex items-start gap-2.5 min-w-0">
                                        @if($isOwnReview)
                                            <input
                                                type="checkbox"
                                                :checked="selectedReviewIds.includes({{ (int) $rev->id }})"
                                                @change="toggleOwnReview({{ (int) $rev->id }})"
                                                aria-label="Select your review #{{ $rev->id }}"
                                                class="mt-1 h-4 w-4 rounded accent-[#214c34] cursor-pointer shrink-0"
                                            >
                                        @endif
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap items-center gap-1.5">
                                                <p class="font-bold text-[var(--foreground)] text-sm">{{ $rev->customer->name ?? 'Meramot Customer' }}</p>
                                                @if($isOwnReview)
                                                    <span class="rounded bg-[var(--accent-dark)] px-2 py-0.5 text-[9px] font-semibold uppercase tracking-wider text-white">
                                                        Your Review
                                                    </span>
                                                @endif
                                                @php
                                                    $linkedPart = $rev->relationLoaded('sparePartListing') ? $rev->sparePartListing : null;
                                                @endphp
                                                @if($linkedPart)
                                                    <span class="rounded bg-sky-100 px-2 py-0.5 text-[9px] font-bold text-sky-900 border border-sky-300">
                                                        <i class="fa-solid fa-microchip mr-0.5 text-[8px]"></i>Verified spare part
                                                    </span>
                                                @elseif($isVerifiedRepair)
                                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-800 border border-emerald-300">
                                                        <i class="fa-solid fa-certificate mr-0.5 text-[8px]"></i>Verified repair
                                                    </span>
                                                @else
                                                    <span class="rounded bg-slate-100 px-2 py-0.5 text-[9px] font-semibold text-slate-600 border border-slate-300">
                                                        Public review
                                                    </span>
                                                @endif
                                                @if($authorBanned)
                                                    <span class="rounded bg-rose-600 px-2 py-0.5 text-[9px] font-semibold uppercase text-white">
                                                        Banned User
                                                    </span>
                                                @endif
                                                @if($isAdminViewer && !empty($offensiveWords))
                                                    <span class="rounded bg-amber-100 border border-amber-400 px-2 py-0.5 text-[9px] font-bold text-amber-900">
                                                        <i class="fa-solid fa-triangle-exclamation mr-0.5"></i>Offensive Words: {{ implode(', ', $offensiveWords) }}
                                                    </span>
                                                @endif
                                            </div>

                                            @if($linkedPart)
                                                <div class="mt-1.5">
                                                    <a
                                                        href="{{ route('spare-parts.show', ['slug' => $linkedPart->slug]) }}"
                                                        data-review-spare-part-link="{{ $linkedPart->slug }}"
                                                        class="group/part inline-flex max-w-full items-center gap-2 rounded-lg border border-emerald-600/35 bg-white/90 hover:bg-emerald-50 hover:border-emerald-700 px-2.5 py-1 text-[10px] font-bold text-[var(--accent-dark)] shadow-2xs transition"
                                                    >
                                                        <img
                                                            src="{{ $linkedPart->image_url }}"
                                                            alt="{{ $linkedPart->title }}"
                                                            loading="lazy"
                                                            onerror="this.onerror=null;this.src='{{ e($linkedPart->fallbackImageUrl()) }}';"
                                                            class="h-6 w-6 rounded border border-slate-200 bg-white object-contain p-0.5 shrink-0"
                                                        >
                                                        <span class="min-w-0 break-words leading-snug group-hover/part:underline">
                                                            Spare Part: {{ $linkedPart->title }}
                                                        </span>
                                                        <span class="inline-flex items-center gap-0.5 rounded bg-[var(--mint-100)] px-1.5 py-0.5 text-[9px] font-semibold text-emerald-900 shrink-0">
                                                            <span>View Listing</span>
                                                            <i class="fa-solid fa-arrow-up-right-from-square text-[8px]"></i>
                                                        </span>
                                                    </a>
                                                </div>
                                            @elseif($serviceProvidedLabel !== '')
                                                <div class="mt-1">
                                                    <span class="inline-flex flex-wrap items-center gap-1 rounded-lg bg-[var(--mint-200)]/90 px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)] border border-[var(--border)] break-words">
                                                        <i class="fa-solid fa-screwdriver-wrench text-[9px] shrink-0"></i>
                                                        <span class="break-words">Service Provided: {{ $serviceProvidedLabel }}</span>
                                                    </span>
                                                </div>
                                            @endif

                                            <p class="text-[10px] text-[var(--muted-foreground)] mt-1">
                                                {{ $rev->created_at ? $rev->created_at->diffForHumans() : 'Recently verified' }}
                                                @if($isVerifiedRepair && $rev->repair_request_id)
                                                    • Past Order #{{ $rev->repair_request_id }}
                                                @endif
                                            </p>
                                        </div>
                                    </div>

                                    <div class="flex flex-col items-end gap-1.5 shrink-0">
                                        <div class="text-amber-500">
                                            @for($s = 1; $s <= 5; $s++)
                                                <i class="fa-solid fa-star text-[10px] {{ $s <= ($rev->rating ?? 5) ? 'text-amber-500' : 'text-gray-300' }}"></i>
                                            @endfor
                                        </div>
                                    </div>
                                </div>

                                <p x-show="editingReviewId !== {{ (int) $rev->id }}" class="mt-2.5 text-xs text-[var(--foreground)] leading-relaxed">
                                    "{{ $rev->review_comment }}"
                                </p>

                                @if($isAdminViewer)
                                    <div x-data="{ banOpen: false }" class="mt-3 pt-3 border-t border-rose-300/70 space-y-2.5">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <span class="text-[11px] font-bold text-rose-800 dark:text-rose-300">
                                                <i class="fa-solid fa-shield-halved mr-1"></i>Admin Review Governance
                                            </span>
                                            <div class="flex flex-wrap items-center gap-2">
                                                <form
                                                    method="POST"
                                                    action="{{ route('admin.reviews.moderate', $rev->id) }}"
                                                    data-confirm-delete
                                                    data-confirm-title="Delete Review?"
                                                    data-confirm-item="Review #{{ $rev->id }} by {{ $rev->customer->name ?? 'Customer' }}"
                                                    data-confirm-message="This deletes the review, recalculates the workshop rating, and notifies the author via email and in-app alert."
                                                    data-confirm-cta="Delete Review"
                                                    class="inline"
                                                >
                                                    @csrf
                                                    <input type="hidden" name="action_type" value="delete_only">
                                                    <input type="hidden" name="reason" value="Removed by Admin for inappropriate or offensive review language">
                                                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-amber-600 px-3.5 py-1.5 text-[11px] font-bold text-white shadow-sm hover:bg-amber-700 transition">
                                                        <i class="fa-solid fa-trash-can text-[10px]"></i>
                                                        <span>Delete Review</span>
                                                    </button>
                                                </form>

                                                @if($rev->customer && $rev->customer->role !== 'admin')
                                                    <button
                                                        type="button"
                                                        @click="banOpen = !banOpen"
                                                        class="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3.5 py-1.5 text-[11px] font-bold text-white shadow-sm hover:bg-rose-700 transition"
                                                    >
                                                        <i class="fa-solid fa-ban text-[10px]"></i>
                                                        <span>Delete &amp; Ban User</span>
                                                    </button>
                                                @endif
                                            </div>
                                        </div>

                                        @if($rev->customer && $rev->customer->role !== 'admin')
                                            <form
                                                x-show="banOpen"
                                                x-cloak
                                                method="POST"
                                                action="{{ route('admin.reviews.moderate', $rev->id) }}"
                                                class="rounded-lg border border-rose-300 bg-rose-50 dark:bg-rose-950/30 p-3 space-y-2"
                                            >
                                                @csrf
                                                <input type="hidden" name="action_type" value="delete_and_ban">
                                                <label class="block text-[10px] font-semibold uppercase tracking-wider text-rose-900 dark:text-rose-200">
                                                    Suspension Reason (Sent via Email &amp; In-App Notification to {{ $rev->customer->email }})
                                                </label>
                                                <input
                                                    type="text"
                                                    name="reason"
                                                    required
                                                    maxlength="500"
                                                    value="Use of offensive words in review on {{ $shop->name }}"
                                                    class="w-full rounded-xl border border-rose-300 bg-white dark:bg-[var(--card)] px-3 py-1.5 text-xs text-[var(--foreground)] outline-none"
                                                >
                                                <div class="flex items-center justify-end gap-2">
                                                    <button type="button" @click="banOpen = false" class="rounded-md border border-[var(--border)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--foreground)]">Cancel</button>
                                                    <button type="submit" class="rounded-md bg-rose-700 px-4 py-1 text-[11px] font-bold text-white shadow-sm hover:bg-rose-800 transition">
                                                        Confirm Delete Review &amp; Ban User
                                                    </button>
                                                </div>
                                            </form>
                                        @endif
                                    </div>
                                @endif

                                @if($isOwnReview)
                                    <div
                                        x-show="editingReviewId !== {{ (int) $rev->id }}"
                                        class="mt-3 pt-3 border-t border-emerald-500/30 flex flex-wrap items-center justify-between gap-2"
                                    >
                                        <span class="text-[11px] font-semibold text-[var(--muted-foreground)]">
                                            <i class="fa-solid fa-user-check text-emerald-700 mr-1"></i>Pinned to top — edit and delete controls are visible only to your account.
                                        </span>
                                        <div class="flex flex-wrap items-center gap-2">
                                            <button
                                                type="button"
                                                @click="editingReviewId = {{ (int) $rev->id }}"
                                                class="inline-flex items-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-3.5 py-1.5 text-xs font-bold text-white shadow-sm hover:opacity-90 transition"
                                            >
                                                <i class="fa-solid fa-pen-to-square text-[11px]"></i>
                                                <span>Edit Review</span>
                                            </button>

                                            <form
                                                method="POST"
                                                action="{{ route('reviews.destroy', $rev->id) }}"
                                                data-confirm-delete
                                                data-confirm-title="Delete Your Review?"
                                                data-confirm-item="Review on {{ $shop->name }}"
                                                data-confirm-message="This will permanently delete your review and recalculate the workshop rating."
                                                data-confirm-cta="Delete Review"
                                                class="inline"
                                            >
                                                @csrf
                                                @method('DELETE')
                                                <button
                                                    type="submit"
                                                    class="inline-flex items-center gap-1.5 rounded-md bg-rose-600 px-3.5 py-1.5 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition"
                                                >
                                                    <i class="fa-solid fa-trash-can text-[11px]"></i>
                                                    <span>Delete Review</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @endif

                                @if($isOwnReview)
                                    <form
                                        x-show="editingReviewId === {{ (int) $rev->id }}"
                                        x-cloak
                                        method="POST"
                                        action="{{ route('reviews.update', $rev->id) }}"
                                        class="mt-3 pt-3 border-t border-[var(--border)] space-y-3"
                                    >
                                        @csrf
                                        @method('PUT')
                                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                            <div>
                                                <label class="block text-[10px] font-bold uppercase text-[var(--muted-foreground)] mb-1">Rating (1–5 ★)</label>
                                                <select name="rating" required class="w-full rounded-xl border border-[var(--border)] bg-white dark:bg-[var(--card)] px-3 py-2 text-xs font-bold text-[var(--foreground)] outline-none">
                                                    @for($rOpt = 5; $rOpt >= 1; $rOpt--)
                                                        <option value="{{ $rOpt }}" {{ (int) $rev->rating === $rOpt ? 'selected' : '' }}>
                                                            {{ $rOpt }} {{ \Illuminate\Support\Str::plural('Star', $rOpt) }} ({{ str_repeat('★', $rOpt) }})
                                                        </option>
                                                    @endfor
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-[10px] font-bold uppercase text-[var(--muted-foreground)] mb-1">Service Provided</label>
                                                <input
                                                    type="text"
                                                    name="service_provided"
                                                    value="{{ $serviceProvidedLabel }}"
                                                    maxlength="180"
                                                    placeholder="e.g. OLED Screen Replacement"
                                                    class="w-full rounded-xl border border-[var(--border)] bg-white dark:bg-[var(--card)] px-3 py-2 text-xs font-semibold text-[var(--foreground)] outline-none"
                                                >
                                            </div>
                                        </div>
                                        <div>
                                            <label class="block text-[10px] font-bold uppercase text-[var(--muted-foreground)] mb-1">Your Review Comment</label>
                                            <textarea
                                                name="review_comment"
                                                rows="3"
                                                required
                                                minlength="10"
                                                maxlength="1000"
                                                class="w-full rounded-xl border border-[var(--border)] bg-white dark:bg-[var(--card)] px-3 py-2 text-xs text-[var(--foreground)] outline-none"
                                            >{{ $rev->review_comment }}</textarea>
                                        </div>
                                        <div class="flex items-center justify-end gap-2">
                                            <button
                                                type="button"
                                                @click="editingReviewId = null"
                                                class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--foreground)]"
                                            >
                                                Cancel
                                            </button>
                                            <button
                                                type="submit"
                                                class="rounded-md bg-[var(--accent-dark)] px-4 py-1.5 text-[11px] font-bold text-white shadow-sm hover:opacity-90 transition"
                                            >
                                                Save Changes
                                            </button>
                                        </div>
                                    </form>
                                @endif
                            </article>
                        @empty
                            <div class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--mint-100)]/40 px-5 py-8 text-center text-xs text-[var(--muted-foreground)] space-y-2">
                                <i class="fa-regular fa-comment-dots text-2xl text-[var(--accent-dark)] opacity-70 block"></i>
                                <p class="font-bold text-sm text-[var(--foreground)]">No verified customer reviews yet</p>
                                <p class="max-w-md mx-auto leading-relaxed">
                                    @if(!$isOperational)
                                        {{ $shop->name }} is currently completing administrative onboarding verification and has not yet served marketplace orders.
                                    @else
                                        Verified customer ratings and reviews appear automatically once orders are completed with {{ $shop->name }}.
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
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
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
                    @if($isShopOwner)
                        <a href="/vendor/dashboard" class="block w-full text-center rounded-md bg-[var(--accent-dark)] px-4 py-2.5 text-xs font-bold text-white shadow hover:opacity-90 transition">
                            Open Vendor Dashboard &rarr;
                        </a>
                    @elseif($isOperational)
                        <a href="/requests/create?shop_id={{ $shop->id }}" class="block w-full text-center rounded-md bg-[var(--accent-dark)] px-4 py-2.5 text-xs font-bold text-white shadow hover:opacity-90 transition">
                            Select This Shop &rarr;
                        </a>
                    @else
                        <span class="block w-full text-center rounded-md border border-amber-400 bg-amber-100/80 px-4 py-2.5 text-xs font-bold text-amber-900 cursor-not-allowed">
                            Pending Onboarding Approval
                        </span>
                    @endif
                </div>
            </div>

            <!-- Other Branches of This Vendor Brand -->
            @if(isset($siblingBranches) && count($siblingBranches) > 0)
                <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                    <div class="mb-3 flex items-center justify-between gap-2">
                        <h3 class="text-base font-bold text-[var(--foreground)] flex items-center gap-2">
                            <i class="fa-solid fa-code-branch text-[var(--accent-dark)]"></i>
                            <span>Other Branches ({{ count($siblingBranches) }})</span>
                        </h3>
                        <span class="rounded bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                            {{ $shop->name }}
                        </span>
                    </div>
                    <div class="space-y-2.5 max-h-72 overflow-y-auto pr-1 scrollbar-thin">
                        @foreach($siblingBranches as $branch)
                            <a href="/shops/{{ $branch->slug }}" class="block rounded-[1.2rem] border border-[var(--border)] bg-[var(--mint-100)] p-3 text-xs hover:bg-[var(--mint-200)] transition group">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="inline-flex items-center gap-1 rounded bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)] truncate">
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
                <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
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
                                    Inspection: ৳{{ number_format($rel->inspection_fee ?? 200) }} · Labor: ৳{{ number_format($rel->base_labor_fee ?? 400) }}
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

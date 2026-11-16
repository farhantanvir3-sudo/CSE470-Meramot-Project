@extends('layouts.app')

@section('title', 'Fixed-Price Repair Packages | Meramot')

{{-- ==========================================================================
     FEATURE R8: Direct Service Package Catalog & Quickmatch Cart Flow
     Assigned Member: Shanid Sajjatuz Islam [23201465]
     UI & Workflow Merge: Farhan Tanvir [22299179] (Commit 8e44218 Mint System)
     Client JS Relocated To: frontend/js/marketplace-client.js
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6">

    <!-- Header -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
                <a href="/" class="hover:underline" data-i18n="home_nav">Home</a>
                <span>›</span>
                <span class="text-[var(--foreground)] font-semibold" data-i18n="service_packages">Service Packages</span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold text-[var(--foreground)]" data-i18n="fixed_price_packages_heading">
                Fixed-Price Repair Packages
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 flex flex-wrap items-center gap-2 leading-relaxed">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R8 • Lead: Shanid Sajjatuz Islam [23201465]
                </span>
                <span>Flat-rate software, cleaning, and maintenance services at verified workshops.</span>
            </p>
        </div>

        @php
            $catalogUser = auth()->user();
            $isBannedCatalogUser = $catalogUser && $catalogUser->isBanned();
            $isNonCustomerCatalogUser = $catalogUser && $catalogUser->role !== 'customer';
        @endphp
        <div class="flex flex-wrap items-center gap-2.5 shrink-0">
            <span class="text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-semibold px-3 py-1.5 rounded-md border border-[var(--border)] whitespace-nowrap">
                <i class="fa-solid fa-shield-halved text-emerald-700 mr-1"></i> <span data-i18n="warranty_protected">Warranty Protected</span>
            </span>
            @if(!$isBannedCatalogUser && !$isNonCustomerCatalogUser)
                <a href="{{ route('cart.view') }}" class="inline-flex items-center gap-2 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-semibold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition whitespace-nowrap">
                    <i class="fa-solid fa-cart-shopping"></i>
                    <span data-i18n="service_cart">Service Cart</span>
                    <span data-cart-badge class="{{ ($cartCount ?? 0) > 0 ? '' : 'hidden' }} inline-flex h-5 min-w-[20px] items-center justify-center rounded-md bg-[var(--mint-100)] px-1.5 text-[11px] font-bold text-[#203126]">
                        {{ $cartCount ?? 0 }}
                    </span>
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-lg border border-emerald-400 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-700 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-xs font-semibold text-rose-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-rose-700 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    <!-- Search & Multi-Parameter Filter Bar -->
    <div class="mb-5 rounded-lg border border-[var(--border)] bg-[var(--card)] p-3.5 md:p-4 shadow-sm">
        <form method="GET" action="{{ route('packages.catalog') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] gap-2.5 items-center text-xs">
            <input type="hidden" name="src" value="search">
            <div class="relative sm:col-span-2 lg:col-span-1">
                <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-[var(--muted-foreground)]"></i>
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Search OS setup, cleaning, tune-up..."
                    data-i18n-placeholder="search_packages_placeholder"
                    class="w-full rounded-md border border-[var(--border)] bg-white/85 dark:bg-[var(--mint-100)] pl-9 pr-4 py-2 text-xs text-[var(--foreground)] placeholder:text-[var(--muted-foreground)] outline-none transition"
                >
            </div>

            <select name="category" class="w-full min-w-0 max-w-full truncate rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs font-medium text-[var(--foreground)] outline-none">
                <option value="" data-i18n="all_categories">All Categories</option>
                @foreach($categories ?? [] as $cat)
                    <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>

            <select name="max_price" class="w-full min-w-0 max-w-full truncate rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs font-medium text-[var(--foreground)] outline-none">
                <option value="" data-i18n="any_budget">Any Budget</option>
                <option value="2000" {{ request('max_price') == '2000' ? 'selected' : '' }}>Under ৳2,000</option>
                <option value="3500" {{ request('max_price') == '3500' ? 'selected' : '' }}>Under ৳3,500</option>
                <option value="5000" {{ request('max_price') == '5000' ? 'selected' : '' }}>Under ৳5,000</option>
                <option value="10000" {{ request('max_price') == '10000' ? 'selected' : '' }}>Under ৳10,000</option>
            </select>

            <select name="sort" class="w-full min-w-0 max-w-full truncate rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs font-medium text-[var(--foreground)] outline-none">
                <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }} data-i18n="most_popular">Most Popular</option>
                <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }} data-i18n="price_low_high">Price: Low to High</option>
                <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }} data-i18n="price_high_low">Price: High to Low</option>
            </select>

            <div class="sm:col-span-2 lg:col-span-1 flex items-center gap-2">
                <button type="submit" class="flex-1 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-semibold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition whitespace-nowrap" data-i18n="apply_filters">
                    Apply Filters
                </button>
                @if(request()->hasAny(['q', 'category', 'max_price', 'sort']))
                    <a href="{{ route('packages.catalog') }}" class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition shrink-0" title="Reset filters">
                        ✕
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Package Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($packages ?? [] as $pkg)
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-5 min-w-0 shadow-sm hover:border-[var(--accent-dark)]/50 hover:shadow-md transition flex flex-col justify-between group">
                <div>
                    <!-- Category & Popularity Badges -->
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-semibold uppercase px-2 py-0.5 rounded bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] whitespace-nowrap">
                            {{ $pkg->device_category }}
                        </span>
                        @if($pkg->is_popular)
                            <span class="text-[10px] font-semibold bg-amber-100 text-amber-900 border border-amber-300 px-2 py-0.5 rounded inline-flex items-center gap-1 whitespace-nowrap">
                                <i class="fa-solid fa-star text-[9px] text-amber-600"></i> Popular
                            </span>
                        @endif
                    </div>

                    <!-- Title & Optional Description -->
                    <h3 class="font-semibold text-base text-[var(--foreground)] mt-2.5 leading-snug">
                        {{ $pkg->title }}
                    </h3>
                    @if($pkg->description)
                        <p class="mt-1 text-xs text-[var(--muted-foreground)] leading-relaxed">
                            {{ \Illuminate\Support\Str::limit($pkg->description, 110) }}
                        </p>
                    @endif

                    <!-- Fixed Price -->
                    <div class="mt-2.5 flex flex-wrap items-baseline gap-2 tabular-nums">
                        <span class="text-2xl font-bold text-[var(--accent-dark)]">
                            ৳{{ number_format($pkg->fixed_price) }}
                        </span>
                        <span class="text-xs text-[var(--muted-foreground)] whitespace-nowrap" data-i18n="fixed_price">Fixed price</span>
                    </div>

                    <!-- Turnaround & Warranty Specs -->
                    <div class="mt-2.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-[var(--muted-foreground)] py-2 border-y border-[var(--border)]/70 tabular-nums">
                        <span class="flex items-center gap-1.5 font-medium text-[var(--foreground)] whitespace-nowrap">
                            <i class="fa-solid fa-clock text-[var(--accent-dark)]"></i>
                            {{ $pkg->estimated_turnaround_hours }}h turnaround
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1.5 font-medium text-[var(--foreground)] whitespace-nowrap">
                            <i class="fa-solid fa-shield-halved text-emerald-700"></i>
                            {{ $pkg->warranty_months }}-mo warranty
                        </span>
                    </div>

                    <!-- Package Inclusions -->
                    @if(!empty($pkg->inclusions))
                        <div class="mt-3">
                            <h5 class="text-[10px] font-semibold text-[var(--muted-foreground)] uppercase tracking-wider mb-1.5" data-i18n="included_in_package">Included in Package:</h5>
                            <ul class="space-y-1 text-xs text-[var(--foreground)]">
                                @foreach($pkg->inclusions as $inc)
                                    <li class="flex items-start gap-2">
                                        <i class="fa-solid fa-check text-emerald-700 mt-1 text-[10px] shrink-0"></i>
                                        <span>{{ $inc }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>

                <!-- Quickmatch Workshop Selector + Add Service to Cart Action -->
                <div class="mt-5 pt-3.5 border-t border-[var(--border)]/70 space-y-2.5 min-w-0">
                    <div class="min-w-0">
                        <label for="shop-select-{{ $pkg->id }}" class="block text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)] mb-1" data-i18n="select_workshop">
                            Select Workshop
                        </label>
                        @php
                            $pkgShops = ($packageShopsMap ?? [])[$pkg->id] ?? ($verifiedShops ?? collect());
                        @endphp
                        <select
                            id="shop-select-{{ $pkg->id }}"
                            class="w-full min-w-0 max-w-full truncate rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs font-medium text-[var(--foreground)] outline-none"
                        >
                            @if($pkgShops->isNotEmpty())
                                <option value="">Nearest Verified Workshop ({{ $pkgShops->count() }} available)</option>
                                @foreach($pkgShops as $vShop)
                                    <option value="{{ $vShop->id }}" {{ session('cart_shop_id') == $vShop->id ? 'selected' : '' }}>
                                        {{ ($shopOptionLabels ?? [])[(int) $vShop->id] ?? (\Illuminate\Support\Str::limit($vShop->name, 26) . ($vShop->display_branch_tag ? ' [' . \Illuminate\Support\Str::limit($vShop->display_branch_tag, 18) . ']' : ' (' . \Illuminate\Support\Str::limit($vShop->area ?: $vShop->city, 18) . ')') . ' • ' . ((float) $vShop->rating > 0 ? '★' . number_format($vShop->rating, 1) : 'Verified') . (isset($vShop->distance_km) ? ' • ' . number_format($vShop->distance_km, 1) . ' km' : '')) }}
                                    </option>
                                @endforeach
                            @else
                                <option value="">Currently unavailable in your selected city</option>
                            @endif
                        </select>
                    </div>

                    @if($isBannedCatalogUser)
                        <span class="w-full rounded-md border border-rose-300 bg-rose-50 text-rose-700 py-2 px-4 text-xs font-semibold flex items-center justify-center gap-2">
                            <i class="fa-solid fa-ban"></i>
                            <span>Ordering Restricted (Banned)</span>
                        </span>
                    @elseif($isNonCustomerCatalogUser)
                        <span class="w-full rounded-md border border-[var(--border)] bg-[var(--mint-100)] text-[var(--muted-foreground)] py-2 px-4 text-xs font-semibold flex items-center justify-center gap-2">
                            <i class="fa-solid fa-user-shield"></i>
                            <span>Customer Booking Only</span>
                        </span>
                    @else
                        <button
                            type="button"
                            onclick="addToCart({{ $pkg->id }}, this)"
                            class="w-full rounded-md bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] py-2 px-4 text-xs font-semibold shadow-sm transition flex items-center justify-center gap-2 whitespace-nowrap"
                        >
                            <i class="fa-solid fa-cart-plus"></i>
                            <span data-i18n="add_to_cart">Add to Cart</span>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-12 rounded-lg border border-[var(--border)] bg-[var(--card)] p-8">
                <i class="fa-solid fa-box-open text-2xl text-[var(--muted-foreground)] mb-3"></i>
                <p class="text-sm font-semibold text-[var(--foreground)]" data-i18n="no_packages_found">No service packages match your filters.</p>
                <a href="{{ route('packages.catalog') }}" class="mt-4 inline-flex rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-semibold text-[var(--accent-foreground)]" data-i18n="view_all_packages">
                    View All Packages
                </a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    @if(isset($packages) && method_exists($packages, 'hasPages') && $packages->hasPages())
        <div class="mt-8">
            {{ $packages->links() }}
        </div>
    @endif

</div>
@endsection

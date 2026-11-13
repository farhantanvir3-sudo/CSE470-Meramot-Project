@extends('layouts.app')

@section('title', 'Direct Fixed Service Packages & Quickmatch | Meramot')

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
        <div>
            <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
                <a href="/" class="hover:underline">Home</a>
                <span>›</span>
                <span class="text-[var(--foreground)] font-semibold">Service Packages</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">
                Fixed-Price Service Packages & Quickmatch
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R8 • Lead: Shanid Sajjatuz Islam [23201465]
                </span>
                <span>Transparent fixed repair pricing — Quickmatch with a top-rated Dhaka workshop or choose your preferred shop</span>
            </p>
        </div>

        @php
            $catalogUser = auth()->user();
            $isBannedCatalogUser = $catalogUser && $catalogUser->isBanned();
            $isNonCustomerCatalogUser = $catalogUser && $catalogUser->role !== 'customer';
        @endphp
        <div class="flex flex-wrap items-center gap-3">
            <span class="text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-2 rounded-full border border-[var(--border)]">
                <i class="fa-solid fa-shield-halved text-emerald-700 mr-1"></i> Meramot Warranty Guaranteed
            </span>
            @if(!$isBannedCatalogUser && !$isNonCustomerCatalogUser)
                <a href="{{ route('cart.view') }}" class="inline-flex items-center gap-2 rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition">
                <i class="fa-solid fa-cart-shopping"></i>
                <span>Direct-Order Cart</span>
                <span data-cart-badge class="{{ ($cartCount ?? 0) > 0 ? '' : 'hidden' }} inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-[var(--mint-100)] px-1.5 text-[11px] font-black text-[#203126]">
                    {{ $cartCount ?? 0 }}
                </span>
            </a>
            @endif
        </div>
    </div>

    <!-- Search & Multi-Parameter Filter Bar (Merged from Shanid R8 + Meramot Mint Tokens) -->
    <div class="mb-6 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-4 md:p-5 shadow-sm">
        <form method="GET" action="{{ route('packages.catalog') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-[minmax(0,1.6fr)_1fr_1fr_1fr_auto] gap-3 items-center text-xs">
            <div class="relative">
                <i class="fa-solid fa-magnifying-glass absolute left-4 top-1/2 -translate-y-1/2 text-[var(--muted-foreground)]"></i>
                <input
                    type="text"
                    name="q"
                    value="{{ request('q') }}"
                    placeholder="Search iPhone screen, battery, thermal paste, micro-soldering..."
                    class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] pl-10 pr-4 py-2.5 text-xs text-[var(--foreground)] placeholder:text-[var(--muted-foreground)] outline-none transition"
                >
            </div>

            <select name="category" class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none">
                <option value="">All Device Categories</option>
                @foreach($categories ?? [] as $cat)
                    <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>

            <select name="max_price" class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none">
                <option value="">Any Budget</option>
                <option value="2000" {{ request('max_price') == '2000' ? 'selected' : '' }}>Under ৳2,000</option>
                <option value="3500" {{ request('max_price') == '3500' ? 'selected' : '' }}>Under ৳3,500</option>
                <option value="5000" {{ request('max_price') == '5000' ? 'selected' : '' }}>Under ৳5,000</option>
                <option value="10000" {{ request('max_price') == '10000' ? 'selected' : '' }}>Under ৳10,000</option>
            </select>

            <select name="sort" class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none">
                <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }}>Most Popular</option>
                <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
            </select>

            <div class="flex items-center gap-2">
                <button type="submit" class="flex-1 rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition whitespace-nowrap">
                    Apply Filters
                </button>
                @if(request()->hasAny(['q', 'category', 'max_price', 'sort']))
                    <a href="{{ route('packages.catalog') }}" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2.5 text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition" title="Reset filters">
                        ✕
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Package Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($packages ?? [] as $pkg)
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between group">
                <div>
                    <!-- Category & Popularity Pill -->
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[10px] font-extrabold uppercase px-3 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)]">
                            {{ $pkg->device_category }}
                        </span>
                        @if($pkg->is_popular)
                            <span class="text-[10px] font-bold bg-amber-100 text-amber-900 border border-amber-300 px-2.5 py-0.5 rounded-full flex items-center gap-1">
                                <i class="fa-solid fa-star text-[9px] text-amber-600"></i> Most Popular
                            </span>
                        @endif
                    </div>

                    <!-- Title & Optional Description -->
                    <h3 class="font-black text-lg text-[var(--foreground)] mt-3 leading-snug">
                        {{ $pkg->title }}
                    </h3>
                    @if($pkg->description)
                        <p class="mt-1.5 text-xs text-[var(--muted-foreground)] leading-relaxed">
                            {{ \Illuminate\Support\Str::limit($pkg->description, 110) }}
                        </p>
                    @endif

                    <!-- Fixed Price -->
                    <div class="mt-3 flex items-baseline gap-2">
                        <span class="text-3xl font-black text-[var(--accent-dark)]">
                            ৳{{ number_format($pkg->fixed_price) }}
                        </span>
                        <span class="text-xs text-[var(--muted-foreground)]">Fixed Direct Package</span>
                    </div>

                    <!-- Turnaround & Warranty Specs -->
                    <div class="mt-3 flex flex-wrap items-center gap-3 text-xs text-[var(--muted-foreground)] py-2.5 border-y border-[var(--border)]">
                        <span class="flex items-center gap-1.5 font-semibold text-[var(--foreground)]">
                            <i class="fa-solid fa-clock text-[var(--accent-dark)]"></i>
                            {{ $pkg->estimated_turnaround_hours }}h Turnaround
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1.5 font-semibold text-[var(--foreground)]">
                            <i class="fa-solid fa-shield-halved text-emerald-700"></i>
                            {{ $pkg->warranty_months }} Mo Warranty
                        </span>
                    </div>

                    <!-- Package Inclusions -->
                    @if(!empty($pkg->inclusions))
                        <div class="mt-4">
                            <h5 class="text-[10px] font-bold text-[var(--muted-foreground)] uppercase tracking-wider mb-2">Included in Service:</h5>
                            <ul class="space-y-1.5 text-xs text-[var(--foreground)]">
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
                <div class="mt-6 pt-4 border-t border-[var(--border)] space-y-2.5">
                    <div>
                        <label for="shop-select-{{ $pkg->id }}" class="block text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)] mb-1">
                            Dispatch Workshop (Quickmatch or Select)
                        </label>
                        <select
                            id="shop-select-{{ $pkg->id }}"
                            class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs font-semibold text-[var(--foreground)] outline-none"
                        >
                            <option value="">⚡ Auto-Quickmatch Top Dhaka Shop</option>
                            @foreach($verifiedShops ?? [] as $vShop)
                                <option value="{{ $vShop->id }}" {{ session('cart_shop_id') == $vShop->id ? 'selected' : '' }}>
                                    {{ $vShop->name }}{{ $vShop->display_branch_tag ? ' [' . $vShop->display_branch_tag . ']' : ' (' . $vShop->area . ')' }} • ★{{ number_format($vShop->rating, 1) }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    @if($isBannedCatalogUser)
                        <span class="w-full rounded-full border border-rose-300 bg-rose-50 text-rose-700 py-2.5 px-4 text-xs font-bold flex items-center justify-center gap-2">
                            <i class="fa-solid fa-ban"></i>
                            <span>Ordering Restricted (Banned)</span>
                        </span>
                    @elseif($isNonCustomerCatalogUser)
                        <span class="w-full rounded-full border border-[var(--border)] bg-[var(--mint-100)] text-[var(--muted-foreground)] py-2.5 px-4 text-xs font-bold flex items-center justify-center gap-2">
                            <i class="fa-solid fa-user-shield"></i>
                            <span>Customer Booking Only</span>
                        </span>
                    @else
                        <button
                            type="button"
                            onclick="addToCart({{ $pkg->id }}, this)"
                            class="w-full rounded-full bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] py-2.5 px-4 text-xs font-bold shadow-sm transition flex items-center justify-center gap-2"
                        >
                            <i class="fa-solid fa-cart-plus"></i>
                            <span>Add Service to Direct Cart</span>
                        </button>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full text-center py-14 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-8">
                <i class="fa-solid fa-box-open text-3xl text-[var(--muted-foreground)] mb-3"></i>
                <p class="text-sm font-bold text-[var(--foreground)]">No service packages matched your filter criteria.</p>
                <a href="{{ route('packages.catalog') }}" class="mt-4 inline-flex rounded-full bg-[var(--accent-dark)] px-5 py-2 text-xs font-bold text-[var(--accent-foreground)]">
                    View All Fixed Packages
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

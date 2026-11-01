@extends('layouts.app')

@section('title', 'Meramot (মেরামত) — Fast, Trusted Electronic Device Repair Marketplace')

@section('content')
<div class="mx-auto grid max-w-7xl gap-6 px-4 py-6 md:px-6 lg:grid-cols-[280px_minmax(0,1fr)]">
    
    <!-- Left Sidebar: Filters & Quick Selectors -->
    <aside class="space-y-4 hidden lg:block">
        
        <!-- Sort Card -->
        <div class="meramot-card p-5 shadow-sm">
            <h3 class="mb-4 text-sm font-bold text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-arrow-down-wide-short text-[var(--accent-dark)]"></i>
                Sort Shops
            </h3>
            <div class="space-y-2 text-xs">
                @php
                    $activeSort = request('sort', 'topRated');
                @endphp
                <a href="/shops?sort=topRated" class="flex w-full items-center gap-3 rounded-2xl px-3 py-2 text-left transition hover:bg-[var(--mint-50)] {{ $activeSort === 'topRated' ? 'font-bold bg-[var(--mint-100)]' : '' }}">
                    <span class="h-3.5 w-3.5 rounded-full border {{ $activeSort === 'topRated' ? 'border-[var(--accent-dark)] bg-[var(--accent-dark)]' : 'border-[var(--border)] bg-[var(--card)]' }}"></span>
                    <span>Top Rated</span>
                </a>
                <a href="/shops?sort=distance" class="flex w-full items-center gap-3 rounded-2xl px-3 py-2 text-left transition hover:bg-[var(--mint-50)] {{ $activeSort === 'distance' ? 'font-bold bg-[var(--mint-100)]' : '' }}">
                    <span class="h-3.5 w-3.5 rounded-full border {{ $activeSort === 'distance' ? 'border-[var(--accent-dark)] bg-[var(--accent-dark)]' : 'border-[var(--border)] bg-[var(--card)]' }}"></span>
                    <span>Nearest Distance</span>
                </a>
                <a href="/shops?sort=price" class="flex w-full items-center gap-3 rounded-2xl px-3 py-2 text-left transition hover:bg-[var(--mint-50)] {{ $activeSort === 'price' ? 'font-bold bg-[var(--mint-100)]' : '' }}">
                    <span class="h-3.5 w-3.5 rounded-full border {{ $activeSort === 'price' ? 'border-[var(--accent-dark)] bg-[var(--accent-dark)]' : 'border-[var(--border)] bg-[var(--card)]' }}"></span>
                    <span>Lowest Price</span>
                </a>
                <a href="/shops?sort=relevance" class="flex w-full items-center gap-3 rounded-2xl px-3 py-2 text-left transition hover:bg-[var(--mint-50)] {{ $activeSort === 'relevance' ? 'font-bold bg-[var(--mint-100)]' : '' }}">
                    <span class="h-3.5 w-3.5 rounded-full border {{ $activeSort === 'relevance' ? 'border-[var(--accent-dark)] bg-[var(--accent-dark)]' : 'border-[var(--border)] bg-[var(--card)]' }}"></span>
                    <span>Best Match</span>
                </a>
            </div>
        </div>

        <!-- Offers & Perks Card -->
        <div class="meramot-card p-5 shadow-sm">
            <h3 class="mb-4 text-sm font-bold text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-tags text-[var(--accent-dark)]"></i>
                Promotions
            </h3>
            <div class="space-y-2 text-xs">
                <a href="/shops?voucher=true" class="flex items-center justify-between rounded-2xl px-3 py-2 transition hover:bg-[var(--mint-50)]">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-ticket text-amber-700"></i>
                        <span>Vouchers</span>
                    </span>
                    <span class="meramot-pill px-2 py-0.5 rounded-full text-[10px]">Active</span>
                </a>
                <a href="/shops?freeDelivery=true" class="flex items-center justify-between rounded-2xl px-3 py-2 transition hover:bg-[var(--mint-50)]">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-truck-fast text-emerald-700"></i>
                        <span>Free Delivery</span>
                    </span>
                    <span class="meramot-pill px-2 py-0.5 rounded-full text-[10px]">Free</span>
                </a>
                <a href="/shops?deals=true" class="flex items-center justify-between rounded-2xl px-3 py-2 transition hover:bg-[var(--mint-50)]">
                    <span class="flex items-center gap-2">
                        <i class="fa-solid fa-fire text-rose-600"></i>
                        <span>Special Deals</span>
                    </span>
                    <span class="meramot-pill px-2 py-0.5 rounded-full text-[10px]">Hot</span>
                </a>
            </div>
        </div>

        <!-- Service Request CTA Box -->
        <div class="meramot-card p-5 bg-gradient-to-br from-[var(--mint-200)] to-[var(--mint-300)] shadow-sm">
            <div class="flex items-center gap-2 text-[var(--accent-dark)] font-bold text-xs uppercase tracking-wider mb-2">
                <i class="fa-solid fa-bolt"></i> Need Urgent Repair?
            </div>
            <p class="text-xs text-[var(--foreground)] mb-4">Post your device issue once and receive competitive reverse quotes from top repair shops across Dhaka.</p>
            <a href="/requests/create" class="block w-full text-center bg-[var(--accent-dark)] text-white text-xs font-bold py-2.5 rounded-2xl shadow hover:opacity-90 transition">
                Create Repair Request &rarr;
            </a>
        </div>
    </aside>

    <!-- Main Content Area -->
    <div class="space-y-8">
        
        <!-- Offer Banner Carousel Section -->
        <section class="grid grid-cols-1 md:grid-cols-3 gap-4">
            @foreach($offerCards as $card)
                <a href="{{ $card['href'] }}" class="meramot-card p-5 transition hover:-translate-y-1 hover:shadow-md flex flex-col justify-between group">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)]">
                            {{ $card['badge'] }}
                        </span>
                        <h3 class="mt-3 text-sm font-bold text-[var(--foreground)] group-hover:text-[var(--accent-dark)] transition">
                            {{ $card['title'] }}
                        </h3>
                        <p class="mt-1 text-xs text-[var(--muted-foreground)] leading-relaxed">
                            {{ $card['subtitle'] }}
                        </p>
                    </div>
                    <div class="mt-4 text-xs font-bold text-[var(--accent-dark)] flex items-center gap-1">
                        <span>Explore deal</span>
                        <i class="fa-solid fa-arrow-right text-[10px] group-hover:translate-x-1 transition-transform"></i>
                    </div>
                </a>
            @endforeach
        </section>

        <!-- Popular Categories Section (Matching legacy Meramot) -->
        <section>
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-lg font-extrabold text-[var(--foreground)] flex items-center gap-2">
                    <span>Popular Categories</span>
                </h2>
                <span class="text-xs text-[var(--muted-foreground)]">Dhaka Metro Market Demand</span>
            </div>
            
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
                @foreach($popularCategories as $cat)
                    <a href="/shops?q={{ urlencode($cat['query']) }}" class="meramot-card p-3 text-center transition hover:-translate-y-1 hover:shadow-md group flex flex-col items-center justify-between">
                        <div class="text-3xl mb-1 group-hover:scale-110 transition-transform">{{ $cat['sprite'] }}</div>
                        <h4 class="text-xs font-bold text-[var(--foreground)]">{{ $cat['label'] }}</h4>
                        <span class="text-[10px] text-[var(--muted-foreground)] mt-1 line-clamp-1 leading-tight">
                            {{ $cat['trend'] }}
                        </span>
                    </a>
                @endforeach
            </div>
        </section>

        <!-- Featured Shops Grid (Matching FeaturedShops.tsx & ShopCard.tsx) -->
        <section>
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-xl font-extrabold text-[var(--foreground)]">Featured Repair Hubs</h2>
                    <p class="text-xs text-[var(--muted-foreground)]">Highest rated and verified repair technicians near your area</p>
                </div>
                <a href="/shops" class="text-xs font-bold text-[var(--accent-dark)] hover:underline flex items-center gap-1">
                    <span>View all 32 shops</span>
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                @foreach($featuredShops as $shop)
                    <a href="/shops/{{ $shop->slug }}" class="meramot-card p-4 transition hover:-translate-y-1 hover:shadow-lg flex flex-col justify-between group">
                        <div>
                            <!-- Header with Avatar & Price Tag -->
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex items-start gap-3">
                                    <div class="h-12 w-12 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] flex items-center justify-center font-black text-xl text-[var(--accent-dark)] shrink-0 shadow-sm">
                                        {{ strtoupper(substr($shop->name, 0, 1)) }}
                                    </div>
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-bold text-[var(--foreground)] truncate group-hover:text-[var(--accent-dark)]">
                                            {{ $shop->name }}
                                        </h3>
                                        <p class="text-[11px] text-[var(--muted-foreground)] flex items-center gap-1 mt-0.5">
                                            <i class="fa-solid fa-location-dot text-[10px] text-[var(--accent-dark)]"></i>
                                            <span>{{ $shop->area }}, Dhaka</span>
                                        </p>
                                    </div>
                                </div>
                                
                                <span class="meramot-pill px-2 py-0.5 rounded-full text-[10px] font-bold shrink-0">
                                    {{ str_repeat('৳', $shop->price_level ?? 2) }}
                                </span>
                            </div>

                            <!-- Rating, Reviews & ETA -->
                            <div class="mt-3 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
                                <span class="font-bold text-[var(--foreground)] flex items-center gap-1 text-amber-700">
                                    ⭐ {{ number_format($shop->rating, 1) }}
                                </span>
                                <span>({{ number_format($shop->review_count) }})</span>
                                <span>•</span>
                                <span>{{ $shop->eta_minutes < 60 ? $shop->eta_minutes . ' min' : round($shop->eta_minutes / 60) . ' hr' }}</span>
                            </div>

                            <!-- Description Quote -->
                            <p class="mt-2 text-xs text-[var(--muted-foreground)] line-clamp-2 leading-relaxed">
                                "{{ $shop->description }}"
                            </p>

                            <!-- Specialties Tags -->
                            <div class="mt-3 flex flex-wrap gap-1.5">
                                @foreach(array_slice($shop->specialties ?? [], 0, 3) as $spec)
                                    <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] text-[var(--accent-dark)] font-medium">
                                        {{ $spec }}
                                    </span>
                                @endforeach
                            </div>
                        </div>

                        <!-- Footer Info: Inspection Fee & Perks -->
                        <div class="mt-4 pt-3 border-t border-[var(--border)] flex items-center justify-between text-xs">
                            <span class="font-bold text-[var(--accent-dark)]">
                                {{ $shop->offer_summary ?? 'Inspection ৳' . number_format($shop->inspection_fee ?? 200) }}
                            </span>
                            <div class="flex gap-1.5 text-[10px]">
                                @if($shop->has_voucher)
                                    <span class="bg-[var(--mint-50)] text-[var(--accent-dark)] px-2 py-0.5 rounded-full font-bold">Voucher</span>
                                @endif
                                @if($shop->free_delivery)
                                    <span class="bg-[var(--mint-50)] text-emerald-900 px-2 py-0.5 rounded-full font-bold">Free Del</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
        </section>

        <!-- Pre-Configured Fixed Packages (Feature R8 preview) -->
        <section class="meramot-card p-6 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h2 class="text-lg font-extrabold text-[var(--foreground)] flex items-center gap-2">
                        <i class="fa-solid fa-cube text-[var(--accent-dark)]"></i>
                        <span>Pre-Configured Service Packages</span>
                    </h2>
                    <p class="text-xs text-[var(--muted-foreground)]">Fixed rate repairs with warranty included — no bidding required</p>
                </div>
                <a href="/packages/catalog" class="text-xs font-bold text-[var(--accent-dark)] hover:underline">
                    View all packages &rarr;
                </a>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                @foreach($popularPackages as $pkg)
                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] p-4 flex flex-col justify-between">
                        <div>
                            <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-[var(--card)] text-[var(--accent-dark)]">
                                {{ $pkg->device_category }}
                            </span>
                            <h4 class="mt-2 text-xs font-bold text-[var(--foreground)]">{{ $pkg->title }}</h4>
                            <p class="text-lg font-black text-[var(--accent-dark)] mt-2">
                                ৳{{ number_format($pkg->fixed_price) }}
                            </p>
                            <div class="mt-2 text-[10px] text-[var(--muted-foreground)] flex items-center gap-2">
                                <span><i class="fa-solid fa-shield-halved text-[9px]"></i> {{ $pkg->warranty_months }} Mo Warranty</span>
                                <span>•</span>
                                <span><i class="fa-solid fa-clock text-[9px]"></i> {{ $pkg->estimated_turnaround_hours }}h Turnaround</span>
                            </div>
                        </div>
                        <a href="/packages/catalog" class="mt-3 block text-center bg-[var(--accent-dark)] text-white text-[11px] font-bold py-1.5 rounded-xl hover:opacity-90 transition">
                            Book Package
                        </a>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- Recently Searched Quick Links -->
        <section class="pt-2">
            <h3 class="text-xs font-bold text-[var(--muted-foreground)] uppercase tracking-wider mb-2">Recent Hot Searches</h3>
            <div class="flex flex-wrap gap-2">
                @foreach($recentlyViewed as $rv)
                    <a href="/shops?q={{ urlencode($rv['query']) }}" class="meramot-pill px-3 py-1.5 rounded-full text-xs hover:bg-[var(--mint-200)] transition flex items-center gap-1.5">
                        <i class="fa-solid fa-magnifying-glass text-[10px] opacity-60"></i>
                        <span>{{ $rv['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </section>

    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', $shop->name . ' — Verified Electronic Repair Shop | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>/</span>
        <a href="/shops" class="hover:underline">Repair Shops</a>
        <span>/</span>
        <a href="/shops?area={{ $shop->area }}" class="hover:underline">{{ $shop->area }}</a>
        <span>/</span>
        <span class="text-[var(--foreground)] font-semibold">{{ $shop->name }}</span>
    </div>

    <!-- Shop Profile Hero Header -->
    <div class="meramot-card p-6 md:p-8 shadow-sm mb-6">
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-6">
            
            <div class="flex items-start gap-5">
                <!-- Shop Large Avatar -->
                <div class="h-20 w-20 md:h-24 md:w-24 rounded-3xl bg-[var(--mint-100)] border-2 border-[var(--border)] flex items-center justify-center font-black text-4xl text-[var(--accent-dark)] shrink-0 shadow-md">
                    {{ strtoupper(substr($shop->name, 0, 1)) }}
                </div>

                <div>
                    <div class="flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">
                            {{ $shop->name }}
                        </h1>
                        @if($shop->is_featured)
                            <span class="rounded-full bg-emerald-800 text-white text-[10px] font-extrabold px-3 py-1 uppercase tracking-wider shadow-sm flex items-center gap-1">
                                <i class="fa-solid fa-circle-check"></i>
                                Verified Meramot Partner
                            </span>
                        @endif
                    </div>

                    <div class="mt-2 flex flex-wrap items-center gap-3 text-xs text-[var(--muted-foreground)]">
                        <span class="font-bold text-amber-700 flex items-center gap-1 text-sm">
                            ⭐ {{ number_format($shop->rating, 1) }}
                            <span class="text-[var(--muted-foreground)] font-normal text-xs">({{ number_format($shop->review_count) }} reviews)</span>
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1">
                            <i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i>
                            {{ $shop->address ?? $shop->area . ', Dhaka' }}
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1">
                            <i class="fa-solid fa-clock text-[var(--accent-dark)]"></i>
                            ETA: {{ $shop->eta_minutes < 60 ? $shop->eta_minutes . ' min' : round($shop->eta_minutes / 60) . ' hr' }}
                        </span>
                    </div>

                    <p class="mt-3 text-xs md:text-sm text-[var(--foreground)] max-w-2xl leading-relaxed italic">
                        "{{ $shop->description }}"
                    </p>

                    <!-- Perks & Badges -->
                    <div class="mt-4 flex flex-wrap gap-2 text-xs">
                        @if($shop->has_voucher)
                            <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-bold text-[var(--accent-dark)]">
                                <i class="fa-solid fa-ticket text-amber-600 mr-1"></i> Coupon Voucher Available
                            </span>
                        @endif
                        @if($shop->free_delivery)
                            <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-bold text-emerald-800">
                                <i class="fa-solid fa-truck text-emerald-600 mr-1"></i> Free Doorstep Courier Pickup
                            </span>
                        @endif
                        @if($shop->has_deals)
                            <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-bold text-rose-800">
                                <i class="fa-solid fa-fire text-rose-600 mr-1"></i> Promotional Deals Active
                            </span>
                        @endif
                        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 font-bold text-[var(--foreground)]">
                            Price Tier: {{ str_repeat('৳', $shop->price_level ?? 2) }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- Booking & Action Box -->
            <div class="shrink-0 meramot-card p-5 bg-[var(--mint-100)] border-2 border-[var(--border)] flex flex-col justify-between w-full md:w-64">
                <div>
                    <div class="text-[11px] font-bold text-[var(--muted-foreground)] uppercase tracking-wider">Inspection & Diagnostics</div>
                    <div class="text-2xl font-black text-[var(--accent-dark)] mt-1">
                        ৳{{ number_format($shop->inspection_fee ?? 200) }}
                    </div>
                    <div class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                        Base Labor: ৳{{ number_format($shop->base_labor_fee ?? 400) }}
                    </div>
                </div>

                <div class="mt-4 space-y-2">
                    <a href="/requests/create?shop_id={{ $shop->id }}" class="block w-full text-center rounded-2xl bg-[var(--accent-dark)] text-white text-xs font-bold py-3 shadow hover:opacity-90 transition">
                        Request Repair Quote
                    </a>
                    <a href="/ai-consultant/chat" class="block w-full text-center rounded-2xl border border-[var(--border)] bg-[var(--card)] text-[var(--accent-dark)] text-xs font-bold py-2 hover:bg-[var(--mint-200)] transition">
                        <i class="fa-solid fa-wand-magic-sparkles mr-1 text-amber-600"></i> AI Diagnose Device
                    </a>
                </div>
            </div>

        </div>
    </div>

    <!-- Main Content Columns -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        
        <!-- Left 2 Cols: Specialties, Estimated Services, Customer Reviews -->
        <div class="lg:col-span-2 space-y-6">
            
            <!-- Technical Specialties & Supported Hardware -->
            <div class="meramot-card p-6 shadow-sm">
                <h3 class="text-base font-extrabold text-[var(--foreground)] mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-microchip text-[var(--accent-dark)]"></i>
                    Technical Specialties & Device Capabilities
                </h3>
                <div class="flex flex-wrap gap-2">
                    @forelse($shop->specialties ?? [] as $spec)
                        <span class="rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] px-3.5 py-1.5 text-xs font-bold text-[var(--accent-dark)]">
                            <i class="fa-solid fa-check text-[10px] mr-1 text-emerald-700"></i> {{ $spec }}
                        </span>
                    @empty
                        <span class="text-xs text-[var(--muted-foreground)]">General consumer electronic repairs.</span>
                    @endforelse
                </div>

                <h4 class="text-xs font-bold text-[var(--muted-foreground)] uppercase tracking-wider mt-5 mb-2">Supported Categories</h4>
                <div class="flex flex-wrap gap-2 text-xs">
                    @foreach($shop->categories ?? ['IN_SHOP_REPAIR'] as $cat)
                        <span class="meramot-pill px-3 py-1 rounded-full text-[11px] font-semibold">
                            {{ str_replace('_', ' ', $cat) }}
                        </span>
                    @endforeach
                </div>
            </div>

            <!-- Estimated Common Repair Services -->
            <div class="meramot-card p-6 shadow-sm">
                <h3 class="text-base font-extrabold text-[var(--foreground)] mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-screwdriver-wrench text-[var(--accent-dark)]"></i>
                    Common Service Cost Estimates
                </h3>
                <div class="divide-y divide-[var(--border)] text-xs">
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-[var(--foreground)] text-sm">Display / Screen Replacement</div>
                            <div class="text-[var(--muted-foreground)] text-[11px] mt-0.5">Panel check, OEM fitting, calibration, and digitizer test.</div>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-[var(--accent-dark)] text-sm">~ ৳1,800</div>
                            <div class="text-[10px] text-[var(--muted-foreground)]">Parts + Labor</div>
                        </div>
                    </div>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-[var(--foreground)] text-sm">Battery Health Diagnostic & Replacement</div>
                            <div class="text-[var(--muted-foreground)] text-[11px] mt-0.5">Cell health verification, genuine battery installation, and charge cycle test.</div>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-[var(--accent-dark)] text-sm">~ ৳1,200</div>
                            <div class="text-[10px] text-[var(--muted-foreground)]">Grade-A cell</div>
                        </div>
                    </div>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-[var(--foreground)] text-sm">Logic Board & Ultrasonic Liquid Treatment</div>
                            <div class="text-[var(--muted-foreground)] text-[11px] mt-0.5">Oscilloscope short detection, corrosion wash, and thermal repasting.</div>
                        </div>
                        <div class="text-right">
                            <div class="font-black text-[var(--accent-dark)] text-sm">~ ৳2,500</div>
                            <div class="text-[10px] text-[var(--muted-foreground)]">3-Mo Warranty</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Customer Reviews (Feature R9) -->
            <div class="meramot-card p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <h3 class="text-base font-extrabold text-[var(--foreground)] flex items-center gap-2">
                            <i class="fa-solid fa-comments text-[var(--accent-dark)]"></i>
                            Customer Reviews & Ratings
                        </h3>
                        <p class="text-xs text-[var(--muted-foreground)]">Verified customer feedback from completed Meramot repairs</p>
                    </div>
                    <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-xs font-bold text-[var(--accent-dark)]">
                        ⭐ {{ number_format($shop->rating, 1) }} ({{ count($shop->reviews) }} Reviews)
                    </span>
                </div>

                <div class="space-y-3">
                    @forelse($shop->reviews as $rev)
                        <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] p-4 text-xs">
                            <div class="flex items-center justify-between mb-1">
                                <div class="font-bold text-[var(--foreground)] flex items-center gap-2">
                                    <span>{{ $rev->customer->name ?? 'Verified Customer' }}</span>
                                    @if($rev->is_verified_purchase)
                                        <span class="text-[9px] bg-emerald-100 text-emerald-800 font-extrabold px-2 py-0.5 rounded-full border border-emerald-300">
                                            Verified Repair
                                        </span>
                                    @endif
                                </div>
                                <div class="text-amber-700 font-bold">
                                    {{ str_repeat('★', $rev->rating) }}
                                </div>
                            </div>
                            <p class="text-[var(--muted-foreground)] leading-relaxed mt-1">
                                "{{ $rev->review_comment }}"
                            </p>
                            <div class="text-[10px] text-[var(--muted-foreground)] mt-2">
                                {{ $rev->created_at ? $rev->created_at->diffForHumans() : 'Recently' }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-6 text-xs text-[var(--muted-foreground)]">
                            <p>No customer reviews posted yet for this shop.</p>
                            <p class="mt-1">Completed repairs are eligible for verified reviews.</p>
                        </div>
                    @endforelse
                </div>
            </div>

        </div>

        <!-- Right 1 Col: Location, Hours, Related Shops -->
        <div class="space-y-6">
            
            <!-- Contact & Hours Card -->
            <div class="meramot-card p-6 shadow-sm">
                <h3 class="text-sm font-extrabold text-[var(--foreground)] mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-address-book text-[var(--accent-dark)]"></i>
                    Shop Information
                </h3>
                <div class="space-y-2.5 text-xs text-[var(--foreground)]">
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-phone text-[var(--accent-dark)] mt-0.5"></i>
                        <span>{{ $shop->phone ?? '+880 1711-000000' }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-envelope text-[var(--accent-dark)] mt-0.5"></i>
                        <span>{{ $shop->email ?? 'contact@' . $shop->slug . '.meramot.local' }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-map-pin text-[var(--accent-dark)] mt-0.5"></i>
                        <span>{{ $shop->address ?? $shop->area . ', Dhaka Metro' }}</span>
                    </div>
                    <div class="flex items-start gap-2">
                        <i class="fa-solid fa-calendar-check text-[var(--accent-dark)] mt-0.5"></i>
                        <span>Sat – Thu: 10:00 AM – 8:30 PM (Friday Closed)</span>
                    </div>
                </div>

                <div class="mt-5 pt-4 border-t border-[var(--border)]">
                    <a href="/requests/create?shop_id={{ $shop->id }}" class="block w-full text-center rounded-2xl bg-[var(--accent-dark)] text-white text-xs font-bold py-2.5 shadow hover:opacity-90 transition">
                        Select This Shop &rarr;
                    </a>
                </div>
            </div>

            <!-- Nearby Related Shops in Same Area -->
            @if(count($relatedShops) > 0)
                <div class="meramot-card p-6 shadow-sm">
                    <h3 class="text-sm font-extrabold text-[var(--foreground)] mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-store text-[var(--accent-dark)]"></i>
                        Other Shops in {{ $shop->area }}
                    </h3>
                    <div class="space-y-3">
                        @foreach($relatedShops as $rel)
                            <a href="/shops/{{ $rel->slug }}" class="block rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] p-3 text-xs hover:bg-[var(--mint-200)] transition group">
                                <div class="flex items-center justify-between">
                                    <div class="font-bold text-[var(--foreground)] group-hover:text-[var(--accent-dark)] truncate">
                                        {{ $rel->name }}
                                    </div>
                                    <span class="text-amber-700 font-bold shrink-0">⭐ {{ number_format($rel->rating, 1) }}</span>
                                </div>
                                <div class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                                    {{ $rel->offer_summary ?? 'Inspection ৳' . number_format($rel->inspection_fee ?? 200) }}
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

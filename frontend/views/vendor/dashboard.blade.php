@extends('layouts.app')

@section('title', 'Vendor Control Center & Multi-Branch Dashboard | Meramot')

{{-- ==========================================================================
     FEATURE R11 & R15: Vendor Control Center, Multi-Branch Expansion & Dual Locations
     Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
     ========================================================================== --}}

@section('content')
@php
    $initialTab = in_array(request('tab'), ['overview', 'marketplace', 'direct_orders', 'assigned_jobs', 'branches'], true)
        ? request('tab')
        : 'overview';
    $brandTitle = $vendorProfile->canonical_brand_name ?? 'ProTech Solutions';
    $publicPreviewShop = $activeBranch ?? ($branches->first() ?? null);
@endphp
<div
    class="mx-auto max-w-7xl px-4 py-6 md:px-6"
    x-data="{
        activeTab: @js($initialTab),
        mapTarget: 'new',
        editingBranchId: null,
        newBranch: {
            branchName: '',
            primaryBranchName: @js(($branches->first()->area ?? 'Banani') . ' Main Branch'),
            gpsAddress: '',
            vendorAddress: '',
            sameAsGps: false,
            lat: '23.793700',
            lng: '90.406600',
            city: 'Dhaka',
            area: 'Banani'
        },
        openMapFor(target) {
            this.mapTarget = target;
            this.$dispatch('meramot-open-location-modal');
        },
        handleMapPicked(loc) {
            if (!loc) return;
            if (this.mapTarget === 'new') {
                this.newBranch.gpsAddress = loc.address || '';
                this.newBranch.lat = typeof loc.lat === 'number' ? loc.lat.toFixed(6) : this.newBranch.lat;
                this.newBranch.lng = typeof loc.lng === 'number' ? loc.lng.toFixed(6) : this.newBranch.lng;
                if (loc.city) this.newBranch.city = loc.city;
                if (loc.area) this.newBranch.area = loc.area;
                if (this.newBranch.sameAsGps) {
                    this.newBranch.vendorAddress = this.newBranch.gpsAddress;
                }
            } else {
                window.dispatchEvent(new CustomEvent('vendor-branch-map-picked-' + this.mapTarget, { detail: loc }));
            }
        }
    }"
    @meramot-location-changed.window="handleMapPicked($event.detail)"
>

    <!-- Header -->
    <div class="mb-5 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
                <a href="/" class="hover:underline">Home</a>
                <span>/</span>
                <span class="text-[var(--foreground)] font-semibold">Vendor Portal</span>
                <span>/</span>
                <span class="text-[var(--accent-dark)] font-bold capitalize" x-text="activeTab.replace('_', ' ')">Overview</span>
            </div>
            <h1 class="text-2xl font-black text-[var(--foreground)] flex flex-wrap items-center gap-2">
                <i class="fa-solid fa-store text-[var(--accent-dark)]"></i>
                <span>{{ $brandTitle }} — Operations Dashboard</span>
                @if($vendorProfile->is_branched)
                    <span class="inline-flex items-center gap-1 rounded-full bg-[var(--accent-dark)] px-3 py-0.5 text-xs font-bold text-[var(--accent-foreground)]">
                        <i class="fa-solid fa-code-branch text-[10px]"></i>
                        <span>Multi-Branch Brand ({{ count($branches) }} Shops)</span>
                    </span>
                @endif
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-0.5 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R11 &amp; R15 • Assigned: Farhan Tanvir [22299179] (Lead)
                </span>
                <span>
                    Viewing:
                    <strong class="text-[var(--foreground)]">
                        {{ $activeBranch ? ($activeBranch->display_branch_tag ?: $activeBranch->area) . ' (' . $activeBranch->city . ')' : 'All Branches (' . count($branches) . ' Workshops)' }}
                    </strong>
                </span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button
                type="button"
                @click="activeTab = 'branches'"
                class="rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-xs font-bold px-4 py-2 flex items-center gap-1.5 shadow-sm hover:opacity-90 transition"
            >
                <i class="fa-solid fa-code-branch"></i>
                <span>Spread Business / Branches</span>
            </button>
            @if($publicPreviewShop)
                <a href="/shops/{{ $publicPreviewShop->slug }}" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-50)] transition">
                    View Public Shop &rarr;
                </a>
            @endif
        </div>
    </div>

    <!-- Flash & Error Messages -->
    @if(session('success'))
        <div class="mb-5 rounded-2xl border border-emerald-400 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900 flex items-center justify-between gap-2 shadow-sm">
            <div class="flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-700"></i>
                <span>{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-800 space-y-1">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <!-- ── Multi-Branch Switcher Bar ── -->
    <div class="mb-5 rounded-2xl border border-[var(--border)] bg-[var(--card)] p-3.5 shadow-sm flex flex-wrap items-center justify-between gap-3 text-xs">
        <div class="flex items-center gap-2 overflow-x-auto no-scrollbar max-w-full">
            <span class="font-bold uppercase tracking-wider text-[10px] text-[var(--muted-foreground)] shrink-0">
                <i class="fa-solid fa-building-circle-check text-[var(--accent-dark)] mr-1"></i>Active Scope:
            </span>
            <a
                href="/vendor/dashboard?shop_id=all"
                class="shrink-0 rounded-full px-3.5 py-1.5 font-bold transition {{ $selectedShopId === 'all' ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]' }}"
            >
                All Branches ({{ count($branches) }})
            </a>
            @foreach($branches as $bItem)
                <a
                    href="/vendor/dashboard?shop_id={{ $bItem->id }}"
                    class="shrink-0 rounded-full px-3.5 py-1.5 font-semibold transition flex items-center gap-1.5 {{ (string) $selectedShopId === (string) $bItem->id ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] font-bold shadow-sm' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]' }}"
                >
                    <i class="fa-solid fa-code-branch text-[10px]"></i>
                    <span>{{ $bItem->display_branch_tag ?: ($bItem->area . ' Shop') }}</span>
                    <span class="opacity-75 text-[10px]">({{ $bItem->city }})</span>
                </a>
            @endforeach
        </div>

        <button
            type="button"
            @click="activeTab = 'branches'"
            class="text-[11px] font-bold text-[var(--accent-dark)] hover:underline shrink-0"
        >
            + Add Branch / Edit Dual Locations
        </button>
    </div>

    <!-- ── Dashboard Navigation Tabs ── -->
    <div class="flex overflow-x-auto whitespace-nowrap gap-2 border-b border-[var(--border)] pb-2 mb-6 text-sm">
        <button
            type="button"
            @click="activeTab = 'overview'"
            :class="activeTab === 'overview' ? 'bg-white text-[var(--accent-dark)] font-bold border-t border-x border-[var(--border)] shadow-sm' : 'text-[var(--muted-foreground)] hover:bg-[var(--mint-50)]'"
            class="rounded-t-2xl px-5 py-2.5 transition flex items-center gap-2"
        >
            <i class="fa-solid fa-chart-pie text-xs"></i>
            <span>Overview</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'branches'"
            :class="activeTab === 'branches' ? 'bg-white text-[var(--accent-dark)] font-bold border-t border-x border-[var(--border)] shadow-sm' : 'text-[var(--muted-foreground)] hover:bg-[var(--mint-50)]'"
            class="rounded-t-2xl px-5 py-2.5 transition flex items-center gap-2"
        >
            <i class="fa-solid fa-code-branch text-xs text-emerald-700"></i>
            <span>Branches &amp; Dual Locations</span>
            <span class="rounded-full bg-[var(--accent-dark)] px-2 py-0.5 text-[10px] text-white">{{ count($branches) }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'marketplace'"
            :class="activeTab === 'marketplace' ? 'bg-white text-[var(--accent-dark)] font-bold border-t border-x border-[var(--border)] shadow-sm' : 'text-[var(--muted-foreground)] hover:bg-[var(--mint-50)]'"
            class="rounded-t-2xl px-5 py-2.5 transition flex items-center gap-2"
        >
            <i class="fa-solid fa-gavel text-xs text-amber-600"></i>
            <span>Marketplace Bids</span>
            <span class="rounded-full bg-[var(--accent-dark)] px-2 py-0.5 text-[10px] text-white">4 Live</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'direct_orders'"
            :class="activeTab === 'direct_orders' ? 'bg-white text-[var(--accent-dark)] font-bold border-t border-x border-[var(--border)] shadow-sm' : 'text-[var(--muted-foreground)] hover:bg-[var(--mint-50)]'"
            class="rounded-t-2xl px-5 py-2.5 transition flex items-center gap-2"
        >
            <i class="fa-solid fa-box text-xs text-emerald-700"></i>
            <span>Direct Orders</span>
            <span class="rounded-full bg-emerald-800 px-2 py-0.5 text-[10px] text-white">{{ isset($directOrders) ? count($directOrders) + 2 : 2 }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'assigned_jobs'"
            :class="activeTab === 'assigned_jobs' ? 'bg-white text-[var(--accent-dark)] font-bold border-t border-x border-[var(--border)] shadow-sm' : 'text-[var(--muted-foreground)] hover:bg-[var(--mint-50)]'"
            class="rounded-t-2xl px-5 py-2.5 transition flex items-center gap-2"
        >
            <i class="fa-solid fa-screwdriver-wrench text-xs text-indigo-700"></i>
            <span>My Repair Jobs</span>
            <span class="rounded-full bg-indigo-900 px-2 py-0.5 text-[10px] text-white">{{ $branchMetrics['active_jobs_count'] ?? 3 }} Active</span>
        </button>
    </div>

    <!-- ── TAB 1: OVERVIEW ── -->
    <div x-show="activeTab === 'overview'" class="space-y-6">
        <!-- 4 Summary KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="meramot-card p-5 shadow-sm">
                <span class="text-xs text-[var(--muted-foreground)] uppercase font-bold block mb-1">Gross Inflow</span>
                <span class="text-2xl font-black text-emerald-800">৳{{ number_format($branchMetrics['gross_inflow'] ?? 48500) }}</span>
                <span class="text-[10px] text-emerald-700 block mt-1"><i class="fa-solid fa-arrow-trend-up"></i> {{ $activeBranch ? ($activeBranch->display_branch_tag ?: $activeBranch->area) : 'Across ' . count($branches) . ' branches' }}</span>
            </div>
            <div class="meramot-card p-5 shadow-sm">
                <span class="text-xs text-[var(--muted-foreground)] uppercase font-bold block mb-1">Active Repair Jobs</span>
                <span class="text-2xl font-black text-indigo-900">{{ $branchMetrics['active_jobs_count'] ?? 8 }}</span>
                <span class="text-[10px] text-[var(--muted-foreground)] block mt-1">Bench &amp; rider queue</span>
            </div>
            <div class="meramot-card p-5 shadow-sm">
                <span class="text-xs text-[var(--muted-foreground)] uppercase font-bold block mb-1">Active Shop Branches</span>
                <span class="text-2xl font-black text-[var(--foreground)]">{{ count($branches) }}</span>
                <span class="text-[10px] text-emerald-800 block mt-1">{{ $vendorProfile->is_branched ? 'Branch Tags Active on Tiles' : 'Single Storefront Mode' }}</span>
            </div>
            <div class="meramot-card p-5 shadow-sm">
                <span class="text-xs text-[var(--muted-foreground)] uppercase font-bold block mb-1">Customer Rating</span>
                <span class="text-2xl font-black text-amber-600">★ {{ number_format($branchMetrics['avg_rating'] ?? 4.9, 1) }}</span>
                <span class="text-[10px] text-[var(--muted-foreground)] block mt-1">Based on {{ number_format($branchMetrics['total_reviews'] ?? 84) }} reviews</span>
            </div>
        </div>

        <!-- Recent Performance Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="meramot-card p-6 shadow-sm lg:col-span-2">
                <h3 class="font-bold text-base text-[var(--foreground)] mb-3 flex items-center justify-between">
                    <span>Recent Repair Activity &amp; Completed Milestones</span>
                    <a href="#" @click.prevent="activeTab = 'assigned_jobs'" class="text-xs text-[var(--accent-dark)] hover:underline">View All &rarr;</a>
                </h3>
                <div class="divide-y divide-[var(--border)] text-xs">
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-[var(--foreground)] block">Apple MacBook Pro M1 14"</span>
                            <span class="text-[var(--muted-foreground)]">Backlight IC replacement • {{ $publicPreviewShop->display_branch_tag ?? 'Main Workshop' }}</span>
                        </div>
                        <span class="rounded-full bg-emerald-100 text-emerald-900 font-bold px-3 py-1 text-[10px]">REPAIRING</span>
                    </div>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-[var(--foreground)] block">Lenovo ThinkPad T14 Gen 2</span>
                            <span class="text-[var(--muted-foreground)]">Keyboard replacement • Customer: Sadia N.</span>
                        </div>
                        <span class="rounded-full bg-indigo-100 text-indigo-900 font-bold px-3 py-1 text-[10px]">DIAGNOSING</span>
                    </div>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-[var(--foreground)] block">Samsung Galaxy S23 Ultra</span>
                            <span class="text-[var(--muted-foreground)]">AMOLED display swap • Customer: Nafis K.</span>
                        </div>
                        <span class="rounded-full bg-emerald-800 text-white font-bold px-3 py-1 text-[10px]">COMPLETED</span>
                    </div>
                </div>
            </div>

            <!-- Selected Shop Dual-Location Summary -->
            <div class="meramot-card p-6 shadow-sm space-y-4">
                <h3 class="font-bold text-base text-[var(--foreground)]">
                    {{ $publicPreviewShop ? ($publicPreviewShop->display_branch_tag ?: $publicPreviewShop->name) : 'Shop Settings' }}
                </h3>
                @if($publicPreviewShop)
                    <div class="space-y-2.5 text-xs">
                        <div class="rounded-xl bg-[var(--mint-100)] p-3 border border-[var(--border)]">
                            <span class="text-[10px] uppercase font-bold text-[var(--muted-foreground)] block">1. GPS Approximate Pin (Private)</span>
                            <span class="font-semibold text-[var(--foreground)] block mt-0.5">{{ $publicPreviewShop->gps_address ?: 'Pinned via OpenStreetMap' }}</span>
                            <span class="font-mono text-[10px] text-[var(--accent-dark)]">{{ number_format((float)$publicPreviewShop->latitude, 5) }}, {{ number_format((float)$publicPreviewShop->longitude, 5) }}</span>
                        </div>
                        <div class="rounded-xl bg-[var(--mint-100)] p-3 border border-[var(--border)]">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">2. Vendor Public Address</span>
                                @if($publicPreviewShop->same_as_gps_location)
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-900">Synced with GPS</span>
                                @endif
                            </div>
                            <span class="font-semibold text-[var(--foreground)] block mt-0.5">{{ $publicPreviewShop->public_address }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-1">
                            <span class="text-[var(--muted-foreground)]">Inspection / Labor:</span>
                            <span class="font-bold text-[var(--accent-dark)]">৳{{ number_format($publicPreviewShop->inspection_fee ?: 300) }} / ৳{{ number_format($publicPreviewShop->base_labor_fee ?: 500) }}</span>
                        </div>
                    </div>
                @endif
                <button
                    type="button"
                    @click="activeTab = 'branches'"
                    class="block w-full text-center rounded-xl bg-[var(--accent-dark)] py-2.5 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition"
                >
                    Manage Branches &amp; Dual Locations
                </button>
            </div>
        </div>
    </div>

    <!-- ── TAB 5: MULTI-BRANCH EXPANSION & DUAL SHOP ADDRESSES ── -->
    <div x-show="activeTab === 'branches'" x-cloak class="space-y-6">

        <!-- Section A: Branching Option Control Banner -->
        <div class="meramot-card p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)]">
                        Multi-Branch Architecture
                    </span>
                    <h2 class="text-lg font-black text-[var(--foreground)] mt-1.5">
                        Brand Expansion &amp; Branch Tag Control ({{ $brandTitle }})
                    </h2>
                    <p class="text-xs text-[var(--muted-foreground)] mt-1 max-w-2xl">
                        Operate multiple repair workshops across any neighborhood or district in Bangladesh under the same brand name <strong>{{ $brandTitle }}</strong>. When Branching is active, every shop tile displays its exact <strong>Branch Tag</strong> so customers can distinguish your branches immediately.
                    </p>
                </div>

                @if(!$vendorProfile->is_branched)
                    <form method="POST" action="{{ route('vendor.branches.toggle') }}" class="flex flex-col sm:flex-row items-stretch sm:items-center gap-2 shrink-0">
                        @csrf
                        <input type="hidden" name="vendor_profile_id" value="{{ $vendorProfile->id }}">
                        <input
                            type="text"
                            name="primary_branch_name"
                            value="{{ ($branches->first()->area ?? 'Main') . ' Flagship' }}"
                            placeholder="Primary Branch Tag (e.g. Banani HQ)"
                            required
                            class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2 text-xs font-semibold text-[var(--foreground)] outline-none"
                        >
                        <button type="submit" class="rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition whitespace-nowrap">
                            <i class="fa-solid fa-code-branch mr-1"></i> Enable Branching Option
                        </button>
                    </form>
                @else
                    <div class="rounded-2xl bg-emerald-100 border border-emerald-300 px-4 py-3 text-xs font-bold text-emerald-900 shrink-0">
                        <i class="fa-solid fa-circle-check text-emerald-700 mr-1"></i>
                        Branching Enabled • {{ count($branches) }} Active {{ count($branches) === 1 ? 'Branch' : 'Branches' }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Section B: Existing Shops / Branches & Dual-Address Editors -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">
            @foreach($branches as $branch)
                <div
                    class="meramot-card p-5 shadow-sm flex flex-col justify-between space-y-4"
                    x-data="{
                        editing: false,
                        bName: @js($branch->branch_name ?? ''),
                        gpsAddr: @js($branch->gps_address ?? $branch->address ?? ''),
                        pubAddr: @js($branch->address ?? ''),
                        sameGps: {{ $branch->same_as_gps_location ? 'true' : 'false' }},
                        lat: @js((string) ($branch->latitude ?? '23.790428')),
                        lng: @js((string) ($branch->longitude ?? '90.398003')),
                        city: @js($branch->city ?? 'Dhaka'),
                        area: @js($branch->area ?? 'Banani'),
                        phone: @js($branch->phone ?? ''),
                        init() {
                            window.addEventListener('vendor-branch-map-picked-{{ $branch->id }}', (e) => {
                                const loc = e.detail;
                                if (!loc) return;
                                this.gpsAddr = loc.address || '';
                                this.lat = typeof loc.lat === 'number' ? loc.lat.toFixed(6) : this.lat;
                                this.lng = typeof loc.lng === 'number' ? loc.lng.toFixed(6) : this.lng;
                                if (loc.city) this.city = loc.city;
                                if (loc.area) this.area = loc.area;
                                if (this.sameGps) this.pubAddr = this.gpsAddr;
                            });
                        }
                    }"
                >
                    <div>
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-black text-[var(--foreground)]">{{ $branch->name }}</h3>
                                    @if($branch->display_branch_tag)
                                        <span class="inline-flex items-center gap-1 rounded-full bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)]">
                                            <i class="fa-solid fa-code-branch text-[8px]"></i>
                                            <span>{{ $branch->display_branch_tag }}</span>
                                        </span>
                                    @else
                                        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-semibold text-[var(--muted-foreground)]">
                                            Standalone Storefront
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                                    {{ $branch->area }}, {{ $branch->city }} • ★ {{ number_format($branch->rating, 1) }} ({{ $branch->review_count }} reviews)
                                </p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <a href="/shops/{{ $branch->slug }}" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                                    View Tile
                                </a>
                                <button
                                    type="button"
                                    @click="editing = !editing"
                                    class="rounded-full bg-[var(--accent-dark)] px-3 py-1 text-[11px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition"
                                >
                                    <span x-text="editing ? 'Cancel' : 'Edit Locations'">Edit Locations</span>
                                </button>
                            </div>
                        </div>

                        <!-- Read-only Dual Address Summary -->
                        <div x-show="!editing" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/70 p-3">
                                <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)] block">
                                    1. GPS Approximate Location
                                </span>
                                <p class="font-semibold text-[var(--foreground)] mt-1">{{ $branch->gps_address ?: 'Pinned on Map' }}</p>
                                <p class="font-mono text-[10px] text-[var(--accent-dark)] mt-1">
                                    {{ number_format((float)$branch->latitude, 5) }}, {{ number_format((float)$branch->longitude, 5) }}
                                </p>
                            </div>

                            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/70 p-3">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">
                                        2. Vendor-Provided Location
                                    </span>
                                    @if($branch->same_as_gps_location)
                                        <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-900">1 &amp; 2 Same</span>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2 py-0.5 text-[9px] font-bold text-amber-900">Custom Storefront</span>
                                    @endif
                                </div>
                                <p class="font-semibold text-[var(--foreground)] mt-1">{{ $branch->public_address }}</p>
                            </div>
                        </div>

                        <!-- Inline Edit Dual Addresses Form -->
                        <form
                            x-show="editing"
                            x-cloak
                            method="POST"
                            action="{{ route('vendor.branches.update', $branch->id) }}"
                            class="mt-4 pt-4 border-t border-[var(--border)] space-y-3 text-xs"
                        >
                            @csrf
                            @method('PATCH')

                            @if($branch->is_branch)
                                <div>
                                    <label class="block font-bold text-[var(--foreground)] mb-1">Exact Branch Tag Name *</label>
                                    <input
                                        type="text"
                                        name="branch_name"
                                        x-model="bName"
                                        required
                                        class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs font-semibold outline-none"
                                    >
                                </div>
                            @endif

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label class="font-bold text-[var(--foreground)]">1. GPS Approximate Location *</label>
                                    <button
                                        type="button"
                                        @click="openMapFor({{ $branch->id }})"
                                        class="rounded-full bg-[var(--accent-dark)] px-2.5 py-1 text-[10px] font-bold text-[var(--accent-foreground)]"
                                    >
                                        <i class="fa-solid fa-map-location-dot mr-1"></i>Pick on Map
                                    </button>
                                </div>
                                <input
                                    type="text"
                                    name="gps_address"
                                    x-model="gpsAddr"
                                    @input="if (sameGps) pubAddr = gpsAddr"
                                    required
                                    class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs outline-none"
                                >
                                <input type="hidden" name="latitude" :value="lat">
                                <input type="hidden" name="longitude" :value="lng">
                            </div>

                            <label class="flex items-center gap-2 cursor-pointer rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 font-bold">
                                <input
                                    type="checkbox"
                                    name="same_as_gps_location"
                                    value="1"
                                    x-model="sameGps"
                                    @change="if (sameGps) pubAddr = gpsAddr"
                                    class="accent-[#244229]"
                                >
                                <span>Make Vendor-Provided Location same as GPS Approximate Location</span>
                            </label>

                            <div>
                                <label class="block font-bold text-[var(--foreground)] mb-1">2. Vendor-Provided Public Location *</label>
                                <input
                                    type="text"
                                    name="address"
                                    x-model="pubAddr"
                                    :readonly="sameGps"
                                    required
                                    class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] px-3 py-2 text-xs outline-none"
                                >
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <div>
                                    <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">District / City</label>
                                    <input type="text" name="city" x-model="city" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-2.5 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Area</label>
                                    <input type="text" name="area" x-model="area" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-2.5 py-1.5 text-xs">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Phone</label>
                                    <input type="text" name="phone" x-model="phone" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-2.5 py-1.5 text-xs">
                                </div>
                            </div>

                            <button type="submit" class="w-full rounded-full bg-[var(--accent-dark)] py-2 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                                Save Dual Locations &amp; Branch Info
                            </button>
                        </form>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Section C: Spread Business — Open a New Shop Branch Anywhere in Bangladesh -->
        <div class="meramot-card p-6 md:p-7 shadow-sm">
            <div class="mb-5 border-b border-[var(--border)] pb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--accent-dark)]">Business Expansion</span>
                    <h3 class="text-lg font-black text-[var(--foreground)]">Open a New Shop Branch Under "{{ $brandTitle }}"</h3>
                    <p class="text-xs text-[var(--muted-foreground)] mt-0.5">
                        Your new branch keeps the exact brand name <strong>{{ $brandTitle }}</strong> and displays its <strong>Branch Tag</strong> on all shop name tiles.
                    </p>
                </div>
                <button
                    type="button"
                    @click="openMapFor('new')"
                    class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition"
                >
                    <i class="fa-solid fa-map-location-dot"></i>
                    <span>Pin New Branch on OpenStreetMap</span>
                </button>
            </div>

            <form method="POST" action="{{ route('vendor.branches.store') }}" class="space-y-4 text-xs">
                @csrf
                <input type="hidden" name="vendor_profile_id" value="{{ $vendorProfile->id }}">

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Brand / Shop Name (Shared Across Branches)</label>
                        <input
                            type="text"
                            value="{{ $brandTitle }}"
                            readonly
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] p-3 text-xs font-black text-[var(--accent-dark)] cursor-not-allowed"
                        >
                    </div>

                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">New Branch Exact Tag Name * <span class="font-normal text-[var(--muted-foreground)]">(Shown on shop tiles)</span></label>
                        <input
                            type="text"
                            name="branch_name"
                            x-model="newBranch.branchName"
                            placeholder="e.g. Sylhet Zindabazar Hub or Dhanmondi 27"
                            required
                            class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] p-3 text-xs outline-none"
                        >
                    </div>
                </div>

                @if(!$vendorProfile->is_branched)
                    <div class="rounded-2xl border border-amber-300 bg-amber-50/80 p-3.5">
                        <label class="block font-bold text-amber-950 mb-1">
                            Branch Tag for Your Existing First Shop ({{ $branches->first()->area ?? 'HQ' }}) *
                        </label>
                        <input
                            type="text"
                            name="primary_branch_name"
                            x-model="newBranch.primaryBranchName"
                            required
                            class="w-full sm:w-96 rounded-xl border border-amber-300 bg-white px-3 py-2 text-xs font-semibold text-[var(--foreground)] outline-none"
                        >
                    </div>
                @endif

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-4 space-y-3">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">
                            1. GPS Approximate Location * <span class="font-normal text-[var(--muted-foreground)]">(Pin on OpenStreetMap or enter approximate road/area)</span>
                        </label>
                        <input
                            type="text"
                            name="gps_address"
                            x-model="newBranch.gpsAddress"
                            @input="if (newBranch.sameAsGps) newBranch.vendorAddress = newBranch.gpsAddress"
                            placeholder="e.g. Zindabazar Point, Sylhet"
                            required
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--card)] p-3 text-xs outline-none"
                        >
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mt-2.5">
                            <div>
                                <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-0.5">GPS Latitude *</label>
                                <input type="number" step="any" name="latitude" x-model="newBranch.lat" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-1.5 text-xs font-mono">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-0.5">GPS Longitude *</label>
                                <input type="number" step="any" name="longitude" x-model="newBranch.lng" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-1.5 text-xs font-mono">
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-0.5">District / City</label>
                                <select name="city" x-model="newBranch.city" class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-1.5 text-xs font-semibold">
                                    @foreach($bangladeshDistricts ?? ['Dhaka', 'Chattogram', 'Sylhet', 'Rajshahi', 'Khulna', 'Bogura', 'Gazipur', 'Narayanganj'] as $distName)
                                        <option value="{{ $distName }}">{{ $distName }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-0.5">Area / Hub</label>
                                <input type="text" name="area" x-model="newBranch.area" placeholder="e.g. Zindabazar" class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-1.5 text-xs">
                            </div>
                        </div>
                    </div>

                    <label class="flex items-center gap-2.5 cursor-pointer rounded-xl border border-[var(--border)] bg-[var(--card)] px-3.5 py-2.5 font-bold text-[var(--foreground)]">
                        <input
                            type="checkbox"
                            name="same_as_gps_location"
                            value="1"
                            x-model="newBranch.sameAsGps"
                            @change="if (newBranch.sameAsGps) newBranch.vendorAddress = newBranch.gpsAddress"
                            class="h-4 w-4 accent-[#244229]"
                        >
                        <span>Use GPS approximate location as Vendor-Provided Public Shop Address (1 &amp; 2 same)</span>
                    </label>

                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">
                            2. Vendor-Provided Public Storefront Address * <span class="font-normal text-[var(--muted-foreground)]">(Shown on shop profile &amp; cards)</span>
                        </label>
                        <input
                            type="text"
                            name="address"
                            x-model="newBranch.vendorAddress"
                            :readonly="newBranch.sameAsGps"
                            :class="newBranch.sameAsGps ? 'opacity-75 cursor-not-allowed bg-[var(--card)]' : 'bg-[var(--mint-300)] focus:bg-[var(--mint-100)]'"
                            placeholder="e.g. Level 2, Shop 218, Al-Hamra Shopping City, Zindabazar, Sylhet"
                            required
                            class="w-full rounded-2xl border-2 border-[var(--border)] p-3 text-xs outline-none"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Branch Contact Phone</label>
                        <input type="text" name="phone" placeholder="+88017XXXXXXXX" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] p-2.5 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Inspection Fee (৳)</label>
                        <input type="number" name="inspection_fee" value="250" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] p-2.5 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Base Labor Fee (৳)</label>
                        <input type="number" name="base_labor_fee" value="500" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] p-2.5 text-xs">
                    </div>
                </div>

                <button type="submit" class="rounded-full bg-[var(--accent-dark)] px-7 py-3 text-xs font-bold text-[var(--accent-foreground)] shadow-md hover:opacity-90 transition">
                    <i class="fa-solid fa-plus mr-1.5"></i> Launch New Branch Under {{ $brandTitle }}
                </button>
            </form>
        </div>
    </div>

    <!-- ── TAB 2: MARKETPLACE LIVE BIDDING ── -->
    <div x-show="activeTab === 'marketplace'" x-cloak class="space-y-6">
        <div class="rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] p-4 flex items-center justify-between text-xs">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-bullhorn text-amber-700 text-lg"></i>
                <span>Reverse Bidding Rules: Submit itemized parts cost, labor cost, and turnaround time. Customers choose based on warranty, price, and reputation.</span>
            </div>
            <a href="/vendor/bidding-board" class="rounded-full bg-[var(--accent-dark)] text-white px-4 py-1.5 font-bold hover:opacity-90 transition shrink-0">
                Full Board View
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Ticket 1 -->
            <div class="meramot-card p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-wider bg-emerald-800 text-white px-2.5 py-0.5 rounded-full">
                            Bidding Open • 3 bids active
                        </span>
                        <span class="text-xs text-[var(--muted-foreground)]"><i class="fa-regular fa-clock"></i> 20 mins ago</span>
                    </div>
                    <h3 class="font-black text-lg text-[var(--foreground)]">Apple MacBook Pro 14" (2021)</h3>
                    <p class="text-xs font-semibold text-[var(--accent-dark)] mt-1">Issue: Display backlight black after minor water spill on table</p>
                    <p class="text-xs text-[var(--muted-foreground)] mt-2 leading-relaxed">
                        "Device powers on, chime sounds, caps lock key lights up, but screen remains completely dark unless illuminated with external flashlight."
                    </p>
                    <div class="mt-3 text-[11px] text-[var(--muted-foreground)] flex items-center gap-3">
                        <span><i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i> Dhanmondi, Dhaka</span>
                        <span><i class="fa-solid fa-truck text-[var(--accent-dark)]"></i> Courier Pickup</span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-[var(--border)]">
                    <form class="grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Parts (৳)</label>
                            <input type="number" placeholder="1800" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Labor (৳)</label>
                            <input type="number" placeholder="1200" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Days</label>
                            <input type="number" placeholder="2" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div class="col-span-3 mt-2">
                            <button type="button" @click="window.MeramotToast?.show('Bid quote of ৳3,000 submitted successfully!')" class="w-full rounded-full bg-[var(--accent-dark)] py-2 font-bold text-white shadow hover:opacity-90 transition">
                                Submit Itemized Bid Offer &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Ticket 2 -->
            <div class="meramot-card p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-wider bg-emerald-800 text-white px-2.5 py-0.5 rounded-full">
                            Bidding Open • 1 bid active
                        </span>
                        <span class="text-xs text-[var(--muted-foreground)]"><i class="fa-regular fa-clock"></i> 45 mins ago</span>
                    </div>
                    <h3 class="font-black text-lg text-[var(--foreground)]">Sony PlayStation 5 Disc Edition</h3>
                    <p class="text-xs font-semibold text-[var(--accent-dark)] mt-1">Issue: HDMI port bent pins, no TV signal</p>
                    <p class="text-xs text-[var(--muted-foreground)] mt-2 leading-relaxed">
                        "Console dropped forward while plugged in, cable pulled HDMI connector loose. Console boots to white light but no TV output."
                    </p>
                    <div class="mt-3 text-[11px] text-[var(--muted-foreground)] flex items-center gap-3">
                        <span><i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i> Gulshan 2, Dhaka</span>
                        <span><i class="fa-solid fa-person-walking text-[var(--accent-dark)]"></i> Customer Drop-off</span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-[var(--border)]">
                    <form class="grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Parts (৳)</label>
                            <input type="number" placeholder="1200" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Labor (৳)</label>
                            <input type="number" placeholder="1500" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Days</label>
                            <input type="number" placeholder="1" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div class="col-span-3 mt-2">
                            <button type="button" @click="window.MeramotToast?.show('Bid quote of ৳2,700 submitted successfully!')" class="w-full rounded-full bg-[var(--accent-dark)] py-2 font-bold text-white shadow hover:opacity-90 transition">
                                Submit Itemized Bid Offer &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TAB 3: DIRECT ORDERS (Direct-Order Cart Queue) ── -->
    <div x-show="activeTab === 'direct_orders'" x-cloak class="space-y-4">
        <div class="meramot-card p-6 shadow-sm">
            <h3 class="font-bold text-base text-[var(--foreground)] mb-4">Direct-Order Cart &amp; Quickmatch Bookings (PENDING Acceptance)</h3>
            <div class="space-y-4 text-xs">
                @foreach($directOrders ?? [] as $order)
                    <div class="p-4 rounded-2xl bg-[var(--mint-100)] border-2 border-emerald-600 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded bg-emerald-800 text-white font-bold text-[10px] px-2 py-0.5 uppercase">Live Direct Order #{{ $order->id }}</span>
                                <span class="text-[11px] font-bold text-[var(--accent-dark)]">
                                    {{ $order->requestedShop->name ?? 'Assigned Workshop' }}{{ optional($order->requestedShop)->display_branch_tag ? ' [' . $order->requestedShop->display_branch_tag . ']' : '' }}
                                </span>
                            </div>
                            <h4 class="font-bold text-sm text-[var(--foreground)] mt-1">{{ $order->title }}</h4>
                            <p class="text-[var(--muted-foreground)]">
                                Customer: {{ $order->customer->name ?? 'Meramot Customer' }} • {{ $order->pickup_address ?? 'Dhaka' }} • {{ $order->delivery_type ?? 'REGULAR' }} Courier
                            </p>
                            <span class="font-extrabold text-[var(--accent-dark)] text-sm">৳{{ number_format($order->quoted_final_amount ?? 0, 2) }}</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <form method="POST" action="{{ route('vendor.direct-orders.respond', $order->id) }}">
                                @csrf
                                <input type="hidden" name="decision" value="accept">
                                <button type="submit" class="rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] font-bold px-4 py-2 hover:opacity-90 shadow-sm transition">
                                    Accept &amp; Dispatch Rider
                                </button>
                            </form>
                            <form method="POST" action="{{ route('vendor.direct-orders.respond', $order->id) }}">
                                @csrf
                                <input type="hidden" name="decision" value="decline">
                                <button type="submit" class="rounded-full border border-rose-300 bg-white text-rose-700 font-bold px-4 py-2 hover:bg-rose-50 transition">
                                    Decline
                                </button>
                            </form>
                        </div>
                    </div>
                @endforeach

                <div class="p-4 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="rounded bg-emerald-800 text-white font-bold text-[10px] px-2 py-0.5 uppercase">Direct Booking</span>
                        <h4 class="font-bold text-sm text-[var(--foreground)] mt-1">Liquid Damage Ultrasonic Clean &amp; Diagnostics</h4>
                        <p class="text-[var(--muted-foreground)]">Customer: Rafiqul Islam • Banani Road 11 • Scheduled Today 3:00 PM</p>
                        <span class="font-extrabold text-[var(--accent-dark)] text-sm">৳1,800.00</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="window.MeramotToast?.show('Order accepted! Rider dispatched for doorstep pickup.')" class="rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] font-bold px-4 py-2 hover:opacity-90 shadow-sm transition">
                            Accept &amp; Dispatch Rider
                        </button>
                        <button type="button" @click="window.MeramotToast?.show('Direct order declined.', 'warning')" class="rounded-full border border-rose-300 bg-white text-rose-700 font-bold px-4 py-2 hover:bg-rose-50 transition">
                            Decline
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TAB 4: MY REPAIR JOBS ── -->
    <div x-show="activeTab === 'assigned_jobs'" x-cloak class="space-y-4">
        <div class="meramot-card p-6 shadow-sm">
            <h3 class="font-bold text-base text-[var(--foreground)] mb-4">Active Repair Queue &amp; Workflow Milestones</h3>
            <div class="divide-y divide-[var(--border)] text-xs">
                <div class="py-4 flex flex-col md:flex-row md:items-center justify-between gap-4" x-data="{ status: 'REPAIRING' }">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-bold text-sm text-[var(--foreground)]">Job #RJ-2026-084: Apple MacBook Pro 14"</span>
                            <span class="rounded-full bg-emerald-100 text-emerald-900 border border-emerald-300 font-bold text-[10px] px-2 py-0.5" x-text="status"></span>
                        </div>
                        <p class="text-[var(--muted-foreground)]">Parts: Backlight IC + Fuse • Total: ৳3,200.00 • Turnaround: Tomorrow 5 PM</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <select x-model="status" class="rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2 font-bold text-xs outline-none">
                            <option value="AT_SHOP">At Shop (Received)</option>
                            <option value="DIAGNOSING">Diagnosing Bench</option>
                            <option value="REPAIRING">Repairing / Soldering</option>
                            <option value="COMPLETED">Completed (Ready for Rider)</option>
                        </select>
                        <button type="button" @click="window.MeramotToast?.show('Status updated to: ' + status)" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 font-bold text-white hover:opacity-90">
                            Update
                        </button>
                    </div>
                </div>

                <div class="py-4 flex flex-col md:flex-row md:items-center justify-between gap-4" x-data="{ status: 'DIAGNOSING' }">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-bold text-sm text-[var(--foreground)]">Job #RJ-2026-085: Lenovo ThinkPad T14</span>
                            <span class="rounded-full bg-indigo-100 text-indigo-900 border border-indigo-300 font-bold text-[10px] px-2 py-0.5" x-text="status"></span>
                        </div>
                        <p class="text-[var(--muted-foreground)]">Parts: OEM Backlit Keyboard • Total: ৳2,800.00 • Turnaround: Nov 14</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <select x-model="status" class="rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2 font-bold text-xs outline-none">
                            <option value="AT_SHOP">At Shop (Received)</option>
                            <option value="DIAGNOSING">Diagnosing Bench</option>
                            <option value="REPAIRING">Repairing / Soldering</option>
                            <option value="COMPLETED">Completed (Ready for Rider)</option>
                        </select>
                        <button type="button" @click="window.MeramotToast?.show('Status updated to: ' + status)" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 font-bold text-white hover:opacity-90">
                            Update
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

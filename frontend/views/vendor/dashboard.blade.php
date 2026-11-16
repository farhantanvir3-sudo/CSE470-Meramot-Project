@extends('layouts.app')

@section('title', 'Vendor Control Center & Multi-Branch Dashboard | Meramot')

{{-- ==========================================================================
     FEATURE R11 & R15: Vendor Control Center, Multi-Branch Expansion & Dual Locations
     Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
     ========================================================================== --}}

@section('content')
@php
    $requestedTab = request('tab');
    if (!$requestedTab && (request()->has('sp_page') || request()->has('sp_q') || request()->has('sp_filter'))) {
        $requestedTab = 'spare_parts';
    }
    $initialTab = in_array($requestedTab, ['overview', 'marketplace', 'direct_orders', 'assigned_jobs', 'branches', 'spare_parts', 'vouchers', 'payouts'], true)
        ? $requestedTab
        : 'overview';
    $brandTitle = $vendorProfile->canonical_brand_name ?? 'ProTech Solutions';
    $publicPreviewShop = $activeBranch ?? ($branches->first() ?? null);
@endphp
<div
    class="mx-auto max-w-7xl px-3 py-3 sm:px-4 sm:py-6 md:px-6"
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
                if (loc.city) {
                    this.newBranch.city = window.MeramotLocationCatalog
                        ? window.MeramotLocationCatalog.findDistrict(loc.city)
                        : loc.city;
                }
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
    <div class="mb-3 sm:mb-5 flex flex-col md:flex-row md:items-center md:justify-between gap-2.5 sm:gap-4">
        <div class="min-w-0">
            <div class="flex items-center justify-between gap-2 mb-1">
                <div class="flex items-center gap-1.5 sm:gap-2 text-[11px] sm:text-xs text-[var(--muted-foreground)] min-w-0 truncate">
                    <a href="/" class="hover:underline">Home</a>
                    <span>/</span>
                    <span class="text-[var(--foreground)] font-semibold">Vendor Portal</span>
                    <span>/</span>
                    <span class="text-[var(--accent-dark)] font-bold capitalize" x-text="activeTab.replace('_', ' ')">Overview</span>
                </div>
                @if($vendorProfile->is_branched)
                    <span class="sm:hidden inline-flex shrink-0 items-center gap-1 rounded bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)]">
                        <i class="fa-solid fa-code-branch text-[9px]"></i>
                        <span>{{ count($branches) }} Branches</span>
                    </span>
                @endif
            </div>
            <h1 class="text-lg sm:text-2xl font-bold text-[var(--foreground)] leading-tight flex flex-wrap items-center gap-x-2 gap-y-1">
                <span><i class="fa-solid fa-store text-[var(--accent-dark)] mr-1.5 text-base sm:text-xl"></i>{{ $brandTitle }} — Operations Dashboard</span>
                @if($vendorProfile->is_branched)
                    <span class="hidden sm:inline-flex items-center gap-1 rounded bg-[var(--accent-dark)] px-3 py-0.5 text-xs font-bold text-[var(--accent-foreground)]">
                        <i class="fa-solid fa-code-branch text-[10px]"></i>
                        <span>Multi-Branch Brand ({{ count($branches) }} Shops)</span>
                    </span>
                @endif
            </h1>
            <p class="text-[11px] sm:text-xs text-[var(--muted-foreground)] mt-0.5 flex flex-wrap items-center gap-1.5 sm:gap-2">
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

        <div class="grid grid-cols-3 sm:flex sm:flex-wrap items-center gap-1.5 sm:gap-2.5 shrink-0">
            <button
                type="button"
                @click="activeTab = 'branches'"
                class="justify-center rounded-md bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-[11px] sm:text-xs font-bold px-2.5 py-1.5 sm:px-4 sm:py-2 flex items-center gap-1 sm:gap-1.5 shadow-sm hover:opacity-90 transition whitespace-nowrap"
            >
                <i class="fa-solid fa-code-branch"></i>
                <span class="sm:hidden">Branches</span>
                <span class="hidden sm:inline">Manage Branches</span>
            </button>
            <a href="{{ $activeBranch ? '/vendor/setup-shop?shop_id=' . $activeBranch->id : '/vendor/setup-shop' }}" class="justify-center rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-2.5 py-1.5 sm:px-4 sm:py-2 text-[11px] sm:text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition flex items-center gap-1 whitespace-nowrap">
                <i class="fa-solid fa-sliders"></i>
                <span class="sm:hidden">Pricing</span>
                <span class="hidden sm:inline">Pricing &amp; Services</span>
            </a>
            @if($publicPreviewShop)
                <a href="/shops/{{ $publicPreviewShop->slug }}" class="justify-center rounded-md border border-[var(--border)] bg-[var(--card)] px-2.5 py-1.5 sm:px-4 sm:py-2 text-[11px] sm:text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-50)] transition flex items-center whitespace-nowrap">
                    <span class="sm:hidden">Public Shop &rarr;</span>
                    <span class="hidden sm:inline">View Public Shop &rarr;</span>
                </a>
            @endif
        </div>
    </div>

    <!-- Flash & Error Messages -->
    @if(session('success'))
        <div class="mb-2.5 sm:mb-5 rounded-xl sm:rounded-lg border border-emerald-400 bg-emerald-50 px-3 py-2 sm:px-4 sm:py-3 text-[11px] sm:text-xs font-bold text-emerald-900 flex items-center justify-between gap-2 shadow-sm">
            <div class="flex items-center gap-2 min-w-0">
                <i class="fa-solid fa-circle-check text-emerald-700 shrink-0"></i>
                <span class="truncate sm:whitespace-normal">{{ session('success') }}</span>
            </div>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-2.5 sm:mb-5 rounded-xl sm:rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 sm:px-4 sm:py-3 text-[11px] sm:text-xs text-rose-800 space-y-1">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <!-- ── Multi-Branch Switcher Bar ── -->
    <div class="mb-3 sm:mb-5 rounded-lg border border-[var(--border)] bg-[var(--card)] p-2.5 sm:p-3.5 shadow-sm text-xs">
        <div class="flex items-center justify-between gap-2 mb-1.5 md:hidden">
            <span class="font-bold uppercase tracking-wider text-[10px] text-[var(--muted-foreground)]">
                <i class="fa-solid fa-building-circle-check text-[var(--accent-dark)] mr-1"></i>Active Scope:
            </span>
            <button
                type="button"
                @click="activeTab = 'branches'"
                class="text-[11px] font-bold text-[var(--accent-dark)] hover:underline shrink-0"
            >
                + Add / Manage
            </button>
        </div>
        <div class="flex items-center justify-between gap-3">
            <div class="flex items-center gap-1.5 sm:gap-2 overflow-x-auto md:flex-wrap no-scrollbar max-w-full pb-0.5 md:pb-0">
                <span class="hidden md:inline-flex items-center font-bold uppercase tracking-wider text-[10px] text-[var(--muted-foreground)] shrink-0">
                    <i class="fa-solid fa-building-circle-check text-[var(--accent-dark)] mr-1"></i>Active Scope:
                </span>
                <a
                    href="/vendor/dashboard?shop_id=all"
                    :href="'/vendor/dashboard?shop_id=all&tab=' + activeTab"
                    class="shrink-0 whitespace-nowrap rounded-md px-3 sm:px-3.5 py-1 sm:py-1.5 text-[11px] sm:text-xs font-bold transition {{ $selectedShopId === 'all' ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]' }}"
                >
                    All Branches ({{ count($branches) }})
                </a>
                @foreach($branches as $bItem)
                    <a
                        href="/vendor/dashboard?shop_id={{ $bItem->id }}"
                        :href="'/vendor/dashboard?shop_id={{ $bItem->id }}&tab=' + activeTab"
                        class="shrink-0 whitespace-nowrap rounded-md px-3 sm:px-3.5 py-1 sm:py-1.5 text-[11px] sm:text-xs font-semibold transition flex items-center gap-1 sm:gap-1.5 {{ (string) $selectedShopId === (string) $bItem->id ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] font-bold shadow-sm' : 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]' }}"
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
                class="hidden md:inline-block text-[11px] font-bold text-[var(--accent-dark)] hover:underline shrink-0"
            >
                + Add Branch / Manage Locations
            </button>
        </div>
    </div>

    <!-- ── Dashboard Navigation Tabs ── -->
    <div class="grid grid-cols-2 gap-1.5 sm:flex sm:flex-wrap sm:gap-1.5 lg:gap-2 border-b border-[var(--border)] pb-2.5 sm:pb-3 mb-3.5 sm:mb-6 text-[11px] sm:text-xs xl:text-sm">
        <button
            type="button"
            @click="activeTab = 'overview'"
            :class="activeTab === 'overview' ? 'bg-[var(--card)] text-[var(--accent-dark)] font-bold border border-[var(--accent-dark)]/40 sm:border-[var(--border)] shadow-sm' : 'bg-[var(--mint-100)]/60 sm:bg-transparent text-[var(--muted-foreground)] hover:bg-[var(--mint-50)] border border-[var(--border)]/60 sm:border-transparent'"
            class="justify-between sm:justify-start rounded-xl sm:rounded-md md:rounded-t-2xl px-2.5 sm:px-3.5 lg:px-4 py-1.5 sm:py-2 transition flex items-center gap-1.5 min-w-0"
        >
            <span class="flex items-center gap-1.5 truncate">
                <i class="fa-solid fa-chart-pie text-xs shrink-0"></i>
                <span class="truncate">Overview</span>
            </span>
        </button>

        <button
            type="button"
            @click="activeTab = 'branches'"
            :class="activeTab === 'branches' ? 'bg-[var(--card)] text-[var(--accent-dark)] font-bold border border-[var(--accent-dark)]/40 sm:border-[var(--border)] shadow-sm' : 'bg-[var(--mint-100)]/60 sm:bg-transparent text-[var(--muted-foreground)] hover:bg-[var(--mint-50)] border border-[var(--border)]/60 sm:border-transparent'"
            class="justify-between sm:justify-start rounded-xl sm:rounded-md md:rounded-t-2xl px-2.5 sm:px-3.5 lg:px-4 py-1.5 sm:py-2 transition flex items-center gap-1.5 min-w-0"
        >
            <span class="flex items-center gap-1.5 truncate">
                <i class="fa-solid fa-code-branch text-xs text-[var(--accent-dark)] shrink-0"></i>
                <span class="sm:hidden truncate">Branches</span>
                <span class="hidden sm:inline">Branches &amp; Dual Locations</span>
            </span>
            <span class="shrink-0 rounded bg-[var(--accent-dark)] px-2 py-0.5 text-[10px] text-[var(--accent-foreground)] font-bold">{{ count($branches) }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'marketplace'"
            :class="activeTab === 'marketplace' ? 'bg-[var(--card)] text-[var(--accent-dark)] font-bold border border-[var(--accent-dark)]/40 sm:border-[var(--border)] shadow-sm' : 'bg-[var(--mint-100)]/60 sm:bg-transparent text-[var(--muted-foreground)] hover:bg-[var(--mint-50)] border border-[var(--border)]/60 sm:border-transparent'"
            class="justify-between sm:justify-start rounded-xl sm:rounded-md md:rounded-t-2xl px-2.5 sm:px-3.5 lg:px-4 py-1.5 sm:py-2 transition flex items-center gap-1.5 min-w-0"
        >
            <span class="flex items-center gap-1.5 truncate">
                <i class="fa-solid fa-gavel text-xs text-amber-600 shrink-0"></i>
                <span class="sm:hidden truncate">Market Bids</span>
                <span class="hidden sm:inline">Marketplace Bids</span>
            </span>
            <span class="shrink-0 rounded bg-[var(--accent-dark)] px-2 py-0.5 text-[10px] text-[var(--accent-foreground)] font-bold">{{ ($marketplaceRequests ?? collect())->count() }} Live</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'direct_orders'"
            :class="activeTab === 'direct_orders' ? 'bg-[var(--card)] text-[var(--accent-dark)] font-bold border border-[var(--accent-dark)]/40 sm:border-[var(--border)] shadow-sm' : 'bg-[var(--mint-100)]/60 sm:bg-transparent text-[var(--muted-foreground)] hover:bg-[var(--mint-50)] border border-[var(--border)]/60 sm:border-transparent'"
            class="justify-between sm:justify-start rounded-xl sm:rounded-md md:rounded-t-2xl px-2.5 sm:px-3.5 lg:px-4 py-1.5 sm:py-2 transition flex items-center gap-1.5 min-w-0"
        >
            <span class="flex items-center gap-1.5 truncate">
                <i class="fa-solid fa-box text-xs text-emerald-700 shrink-0"></i>
                <span class="truncate">Direct Orders</span>
            </span>
            <span class="shrink-0 rounded bg-emerald-800 px-2 py-0.5 text-[10px] text-white font-bold">{{ ($directOrders ?? collect())->count() }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'assigned_jobs'"
            :class="activeTab === 'assigned_jobs' ? 'bg-[var(--card)] text-[var(--accent-dark)] font-bold border border-[var(--accent-dark)]/40 sm:border-[var(--border)] shadow-sm' : 'bg-[var(--mint-100)]/60 sm:bg-transparent text-[var(--muted-foreground)] hover:bg-[var(--mint-50)] border border-[var(--border)]/60 sm:border-transparent'"
            class="justify-between sm:justify-start rounded-xl sm:rounded-md md:rounded-t-2xl px-2.5 sm:px-3.5 lg:px-4 py-1.5 sm:py-2 transition flex items-center gap-1.5 min-w-0"
        >
            <span class="flex items-center gap-1.5 truncate">
                <i class="fa-solid fa-screwdriver-wrench text-xs text-[var(--accent-dark)] shrink-0"></i>
                <span class="sm:hidden truncate">Repair Jobs</span>
                <span class="hidden sm:inline">My Repair Jobs</span>
            </span>
            <span class="shrink-0 rounded bg-indigo-800 px-2 py-0.5 text-[10px] text-white font-bold">{{ ($activeJobs ?? collect())->count() }}<span class="hidden sm:inline"> Active</span></span>
        </button>

        <button
            type="button"
            @click="activeTab = 'spare_parts'"
            :class="activeTab === 'spare_parts' ? 'bg-[var(--card)] text-[var(--accent-dark)] font-bold border border-[var(--accent-dark)]/40 sm:border-[var(--border)] shadow-sm' : 'bg-[var(--mint-100)]/60 sm:bg-transparent text-[var(--muted-foreground)] hover:bg-[var(--mint-50)] border border-[var(--border)]/60 sm:border-transparent'"
            class="justify-between sm:justify-start rounded-xl sm:rounded-md md:rounded-t-2xl px-2.5 sm:px-3.5 lg:px-4 py-1.5 sm:py-2 transition flex items-center gap-1.5 min-w-0"
        >
            <span class="flex items-center gap-1.5 truncate">
                <i class="fa-solid fa-microchip text-xs text-rose-600 shrink-0"></i>
                <span class="truncate">Spare Parts</span>
            </span>
            <span class="shrink-0 rounded bg-rose-600 px-2 py-0.5 text-[10px] text-white font-bold">{{ number_format($vendorSparePartStats['total_count'] ?? 0) }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'vouchers'"
            data-vendor-vouchers-tab-btn
            :class="activeTab === 'vouchers' ? 'bg-[var(--card)] text-[var(--accent-dark)] font-bold border border-[var(--accent-dark)]/40 sm:border-[var(--border)] shadow-sm' : 'bg-[var(--mint-100)]/60 sm:bg-transparent text-[var(--muted-foreground)] hover:bg-[var(--mint-50)] border border-[var(--border)]/60 sm:border-transparent'"
            class="justify-between sm:justify-start rounded-xl sm:rounded-md md:rounded-t-2xl px-2.5 sm:px-3.5 lg:px-4 py-1.5 sm:py-2 transition flex items-center gap-1.5 min-w-0"
        >
            <span class="flex items-center gap-1.5 truncate">
                <i class="fa-solid fa-ticket text-xs text-[var(--accent-dark)] shrink-0"></i>
                <span class="truncate">Vouchers</span>
            </span>
            <span class="shrink-0 rounded bg-[var(--accent-dark)] px-2 py-0.5 text-[10px] text-white font-bold">{{ ($vendorVouchers ?? collect())->count() }}</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'payouts'"
            :class="activeTab === 'payouts' ? 'bg-[var(--card)] text-[var(--accent-dark)] font-bold border border-[var(--accent-dark)]/40 sm:border-[var(--border)] shadow-sm' : 'bg-[var(--mint-100)]/60 sm:bg-transparent text-[var(--muted-foreground)] hover:bg-[var(--mint-50)] border border-[var(--border)]/60 sm:border-transparent'"
            class="justify-between sm:justify-start rounded-xl sm:rounded-md md:rounded-t-2xl px-2.5 sm:px-3.5 lg:px-4 py-1.5 sm:py-2 transition flex items-center gap-1.5 min-w-0"
        >
            <span class="flex items-center gap-1.5 truncate">
                <i class="fa-solid fa-money-bill-transfer text-xs text-emerald-700 shrink-0"></i>
                <span class="sm:hidden truncate">Payouts</span>
                <span class="hidden sm:inline">Earnings &amp; Payouts</span>
            </span>
            <span class="shrink-0 rounded bg-emerald-700 px-2 py-0.5 text-[10px] text-white font-bold">৳{{ number_format($branchMetrics['available_to_request'] ?? 0, 0) }}</span>
        </button>
    </div>

    <!-- ── TAB 1: OVERVIEW ── -->
    <div x-show="activeTab === 'overview'" class="space-y-4 sm:space-y-6">
        <!-- 4 Summary KPI Cards -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-4">
            <div class="meramot-card p-3 sm:p-5 min-w-0 shadow-sm">
                <div class="flex items-center justify-between gap-1.5 mb-1">
                    <span class="text-[10px] sm:text-xs text-[var(--muted-foreground)] uppercase font-bold leading-tight">Gross Inflow</span>
                    <span class="flex h-6 w-6 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-lg sm:rounded-xl bg-[var(--mint-100)] text-[var(--accent-dark)]"><i class="fa-solid fa-sack-dollar text-[10px] sm:text-xs"></i></span>
                </div>
                <span class="text-lg sm:text-2xl font-bold text-[var(--accent-dark)] whitespace-nowrap">৳{{ number_format($branchMetrics['gross_inflow'] ?? 48500) }}</span>
                <span class="text-[10px] text-[var(--muted-foreground)] block mt-0.5 sm:mt-1 truncate"><i class="fa-solid fa-arrow-trend-up text-[var(--accent-dark)]"></i> {{ $activeBranch ? ($activeBranch->display_branch_tag ?: $activeBranch->area) : 'Across ' . count($branches) . ' branches' }}</span>
            </div>
            <div class="meramot-card p-3 sm:p-5 min-w-0 shadow-sm">
                <div class="flex items-center justify-between gap-1.5 mb-1">
                    <span class="text-[10px] sm:text-xs text-[var(--muted-foreground)] uppercase font-bold leading-tight">Active Repair Jobs</span>
                    <span class="flex h-6 w-6 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-lg sm:rounded-xl bg-[var(--mint-100)] text-[var(--accent-dark)]"><i class="fa-solid fa-screwdriver-wrench text-[10px] sm:text-xs"></i></span>
                </div>
                <span class="text-lg sm:text-2xl font-bold text-[var(--foreground)]">{{ $branchMetrics['active_jobs_count'] ?? 8 }}</span>
                <span class="text-[10px] text-[var(--muted-foreground)] block mt-0.5 sm:mt-1 truncate">Bench &amp; rider queue</span>
            </div>
            <div class="meramot-card p-3 sm:p-5 min-w-0 shadow-sm">
                <div class="flex items-center justify-between gap-1.5 mb-1">
                    <span class="text-[10px] sm:text-xs text-[var(--muted-foreground)] uppercase font-bold leading-tight">Active Shop Branches</span>
                    <span class="flex h-6 w-6 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-lg sm:rounded-xl bg-[var(--mint-100)] text-[var(--accent-dark)]"><i class="fa-solid fa-code-branch text-[10px] sm:text-xs"></i></span>
                </div>
                <span class="text-lg sm:text-2xl font-bold text-[var(--foreground)]">{{ count($branches) }}</span>
                <span class="text-[10px] text-[var(--accent-dark)] font-semibold block mt-0.5 sm:mt-1 truncate">{{ $vendorProfile->is_branched ? 'Branch Tags Active on Tiles' : 'Single Storefront Mode' }}</span>
            </div>
            <div class="meramot-card p-3 sm:p-5 min-w-0 shadow-sm">
                <div class="flex items-center justify-between gap-1.5 mb-1">
                    <span class="text-[10px] sm:text-xs text-[var(--muted-foreground)] uppercase font-bold leading-tight">Customer Rating</span>
                    <span class="flex h-6 w-6 sm:h-8 sm:w-8 shrink-0 items-center justify-center rounded-lg sm:rounded-xl bg-amber-100 text-amber-700"><i class="fa-solid fa-star text-[10px] sm:text-xs"></i></span>
                </div>
                <span class="text-lg sm:text-2xl font-bold text-amber-500 whitespace-nowrap">★ {{ number_format($branchMetrics['avg_rating'] ?? 4.9, 1) }}</span>
                <span class="text-[10px] text-[var(--muted-foreground)] block mt-0.5 sm:mt-1 truncate">Based on {{ number_format($branchMetrics['total_reviews'] ?? 84) }} reviews</span>
            </div>
        </div>

        @php
            $va = $vendorAnalytics ?? [];
            $vaFeePct = rtrim(rtrim(number_format((float) ($va['commission_rate_pct'] ?? $vendorProfile->effectiveCommissionRatePct()), 2), '0'), '.');
            $vaTrend = $va['earnings_trend'] ?? [];
            $vaDir = $vaTrend['direction'] ?? 'STEADY';
            $vaDelta = (float) ($vaTrend['rate_delta_pct'] ?? 0);
            $vaSvc = $va['services'] ?? [];
            $vaParts = $va['spare_parts'] ?? [];
        @endphp

        <!-- Exclusive 5%–10% Fee Tier Banner & Chronological Workshop Earnings Graph -->
        <div class="meramot-card p-4 sm:p-6 shadow-sm space-y-4">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3 border-b border-[var(--border)] pb-3">
                <div class="space-y-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[var(--accent-foreground)]">
                            Workshop Base Rate: {{ $vaFeePct }}%
                        </span>
                        <span class="rounded px-2.5 py-0.5 text-[10px] font-bold border {{ $vaDir === 'UP' ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : ($vaDir === 'DOWN' ? 'bg-rose-100 text-rose-900 border-rose-300' : 'bg-[var(--mint-100)] text-[var(--accent-dark)] border-[var(--border)]') }}">
                            {{ $vaTrend['trend_label'] ?? 'Steady ●' }} ({{ $vaDelta > 0 ? '+' : '' }}{{ $vaDelta }}%)
                        </span>
                    </div>
                    <h2 class="text-sm sm:text-base font-bold text-[var(--foreground)]">Workshop Revenue Growth &amp; Performance Analytics</h2>
                    <p class="text-[11px] sm:text-xs text-[var(--muted-foreground)]">
                        Your listed service and spare part prices are settled in full to your workshop balance; customer service fees and courier logistics are billed separately at checkout.
                    </p>
                </div>
                <div class="grid grid-cols-2 gap-2 text-xs shrink-0">
                    <div class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/55 px-3 py-2 text-center">
                        <span class="block text-[9px] font-bold uppercase text-[var(--muted-foreground)]">Period Earnings</span>
                        <span class="font-mono font-bold text-[var(--accent-dark)]">৳{{ number_format($vaTrend['total_earning'] ?? 0, 0) }}</span>
                    </div>
                    <div class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/55 px-3 py-2 text-center">
                        <span class="block text-[9px] font-bold uppercase text-[var(--muted-foreground)]">Paid Orders</span>
                        <span class="font-mono font-bold text-[var(--foreground)]">{{ $vaTrend['total_orders'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/35 p-3">
                <svg viewBox="0 0 {{ $vaTrend['svg_width'] ?? 640 }} {{ $vaTrend['svg_height'] ?? 170 }}" class="w-full h-36 sm:h-44 overflow-visible" role="img" aria-label="Vendor Earnings Trend Chart">
                    <defs>
                        <linearGradient id="meramotVendorGrad" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stop-color="#047857" stop-opacity="0.28"/>
                            <stop offset="100%" stop-color="#047857" stop-opacity="0.02"/>
                        </linearGradient>
                    </defs>
                    @foreach([18, 60, 102, 146] as $gy)
                        <line x1="30" y1="{{ $gy }}" x2="610" y2="{{ $gy }}" stroke="currentColor" stroke-opacity="0.12" stroke-dasharray="4 4"/>
                    @endforeach
                    @if(!empty($vaTrend['area_earning']))
                        <polygon points="{{ $vaTrend['area_earning'] }}" fill="url(#meramotVendorGrad)"/>
                    @endif
                    @if(!empty($vaTrend['polyline_earning']))
                        <polyline fill="none" stroke="#047857" stroke-width="3" stroke-linecap="round" stroke-linejoin="round" points="{{ $vaTrend['polyline_earning'] }}"/>
                    @endif
                    @foreach(($vaTrend['points'] ?? []) as $pt)
                        <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="4.5" fill="#047857" stroke="#ffffff" stroke-width="1.5">
                            <title>{{ $pt['label'] }}: ৳{{ number_format($pt['earning'], 2) }} ({{ $pt['orders'] }} paid orders)</title>
                        </circle>
                        <text x="{{ $pt['x'] }}" y="164" text-anchor="middle" fill="currentColor" opacity="0.68" font-size="10" font-weight="700">{{ $pt['label'] }}</text>
                    @endforeach
                </svg>
            </div>
        </div>

        <!-- 2x2 Demand & Rating Matrix (Services & Spare Parts Improvement Insights) -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5 text-xs">
            <!-- Services: Most vs Least Demanding (Sales) -->
            <div class="meramot-card p-4 sm:p-5 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-[var(--border)] pb-2.5">
                    <div>
                        <h3 class="font-bold text-sm text-[var(--foreground)]">Services Demand (Sales Volume)</h3>
                        <p class="text-[11px] text-[var(--muted-foreground)]">Spot top-booked repairs and underperforming services to revise or upgrade.</p>
                    </div>
                    <span class="rounded bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">{{ $vaSvc['total_count'] ?? 0 }} Active</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 dark:bg-emerald-950/20 p-3 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">▲ Most Demanding</p>
                        @forelse($vaSvc['most_demanding'] ?? [] as $row)
                            <div class="border-b border-emerald-200/60 last:border-0 pb-1.5 last:pb-0">
                                <div class="font-bold text-[var(--foreground)] leading-snug">{{ $row['name'] }}</div>
                                <div class="text-[10px] text-[var(--muted-foreground)] flex justify-between mt-0.5">
                                    <span class="font-bold text-emerald-700">{{ $row['sales_count'] }} orders</span>
                                    <span class="font-mono">৳{{ number_format($row['price'], 0) }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[11px] text-[var(--muted-foreground)]">No service listings yet.</p>
                        @endforelse
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 dark:bg-amber-950/20 p-3 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-800 dark:text-amber-300">▼ Least Demanding (Upgrade/Reprice)</p>
                        @forelse($vaSvc['least_demanding'] ?? [] as $row)
                            <div class="border-b border-amber-200/60 last:border-0 pb-1.5 last:pb-0">
                                <div class="font-bold text-[var(--foreground)] leading-snug">{{ $row['name'] }}</div>
                                <div class="text-[10px] text-[var(--muted-foreground)] flex justify-between mt-0.5">
                                    <span class="font-bold text-amber-700">{{ $row['sales_count'] }} orders</span>
                                    <span class="font-mono">৳{{ number_format($row['price'], 0) }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[11px] text-[var(--muted-foreground)]">No service listings yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Services: Most vs Least Popular (Ratings) -->
            <div class="meramot-card p-4 sm:p-5 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-[var(--border)] pb-2.5">
                    <div>
                        <h3 class="font-bold text-sm text-[var(--foreground)]">Services Popularity (Customer Ratings)</h3>
                        <p class="text-[11px] text-[var(--muted-foreground)]">Identify highest-rated repairs vs lower-rated offerings that need QA upgrades.</p>
                    </div>
                    <span class="rounded bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold text-amber-800">★ Quality Score</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 dark:bg-emerald-950/20 p-3 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">★ Most Popular (Top Rated)</p>
                        @forelse($vaSvc['most_popular'] ?? [] as $row)
                            <div class="border-b border-emerald-200/60 last:border-0 pb-1.5 last:pb-0">
                                <div class="font-bold text-[var(--foreground)] leading-snug">{{ $row['name'] }}</div>
                                <div class="text-[10px] text-[var(--muted-foreground)] flex justify-between mt-0.5">
                                    <span class="font-bold text-amber-600">{{ $row['avg_rating'] !== null ? '★ ' . number_format($row['avg_rating'], 1) : 'No ratings' }}</span>
                                    <span>{{ $row['review_count'] }} reviews</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[11px] text-[var(--muted-foreground)]">No service reviews matched yet.</p>
                        @endforelse
                    </div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50/50 dark:bg-rose-950/20 p-3 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-rose-800 dark:text-rose-300">▼ Least Popular (Needs Improvement)</p>
                        @forelse($vaSvc['least_popular'] ?? [] as $row)
                            <div class="border-b border-rose-200/60 last:border-0 pb-1.5 last:pb-0">
                                <div class="font-bold text-[var(--foreground)] leading-snug">{{ $row['name'] }}</div>
                                <div class="text-[10px] text-[var(--muted-foreground)] flex justify-between mt-0.5">
                                    <span class="font-bold text-rose-700">{{ $row['avg_rating'] !== null ? '★ ' . number_format($row['avg_rating'], 1) : 'Unrated' }}</span>
                                    <span>{{ $row['review_count'] }} reviews</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[11px] text-[var(--muted-foreground)]">No service reviews matched yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Spare Parts: Most vs Least Demanding (Sales) -->
            <div class="meramot-card p-4 sm:p-5 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-[var(--border)] pb-2.5">
                    <div>
                        <h3 class="font-bold text-sm text-[var(--foreground)]">Spare Parts Demand (Units Sold)</h3>
                        <p class="text-[11px] text-[var(--muted-foreground)]">Track fast-moving SKUs to restock vs slow-moving parts to discount or swap.</p>
                    </div>
                    <span class="rounded bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">{{ $vaParts['total_count'] ?? 0 }} SKUs</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 dark:bg-emerald-950/20 p-3 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">▲ Most Demanding Parts</p>
                        @forelse($vaParts['most_demanding'] ?? [] as $row)
                            <div class="border-b border-emerald-200/60 last:border-0 pb-1.5 last:pb-0">
                                <div class="font-bold text-[var(--foreground)] leading-snug">{{ \Illuminate\Support\Str::limit($row['title'], 44) }}</div>
                                <div class="text-[10px] text-[var(--muted-foreground)] flex justify-between mt-0.5">
                                    <span class="font-bold text-emerald-700">{{ $row['sales_count'] }} sold</span>
                                    <span class="font-mono">৳{{ number_format($row['price'], 0) }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[11px] text-[var(--muted-foreground)]">No spare part listings yet.</p>
                        @endforelse
                    </div>
                    <div class="rounded-xl border border-amber-200 bg-amber-50/50 dark:bg-amber-950/20 p-3 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-amber-800 dark:text-amber-300">▼ Least Demanding Parts</p>
                        @forelse($vaParts['least_demanding'] ?? [] as $row)
                            <div class="border-b border-amber-200/60 last:border-0 pb-1.5 last:pb-0">
                                <div class="font-bold text-[var(--foreground)] leading-snug">{{ \Illuminate\Support\Str::limit($row['title'], 44) }}</div>
                                <div class="text-[10px] text-[var(--muted-foreground)] flex justify-between mt-0.5">
                                    <span class="font-bold text-amber-700">{{ $row['sales_count'] }} sold</span>
                                    <span class="font-mono">৳{{ number_format($row['price'], 0) }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[11px] text-[var(--muted-foreground)]">No spare part listings yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>

            <!-- Spare Parts: Most vs Least Popular (Ratings) -->
            <div class="meramot-card p-4 sm:p-5 shadow-sm space-y-3">
                <div class="flex items-center justify-between border-b border-[var(--border)] pb-2.5">
                    <div>
                        <h3 class="font-bold text-sm text-[var(--foreground)]">Spare Parts Popularity (Ratings)</h3>
                        <p class="text-[11px] text-[var(--muted-foreground)]">Compare customer-loved components vs low-rated batches to upgrade suppliers.</p>
                    </div>
                    <span class="rounded bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold text-amber-800">★ Part Feedback</span>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div class="rounded-xl border border-emerald-200 bg-emerald-50/50 dark:bg-emerald-950/20 p-3 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-800 dark:text-emerald-300">★ Most Popular Parts</p>
                        @forelse($vaParts['most_popular'] ?? [] as $row)
                            <div class="border-b border-emerald-200/60 last:border-0 pb-1.5 last:pb-0">
                                <div class="font-bold text-[var(--foreground)] leading-snug">{{ \Illuminate\Support\Str::limit($row['title'], 44) }}</div>
                                <div class="text-[10px] text-[var(--muted-foreground)] flex justify-between mt-0.5">
                                    <span class="font-bold text-amber-600">{{ $row['avg_rating'] !== null ? '★ ' . number_format($row['avg_rating'], 1) : 'No ratings' }}</span>
                                    <span>{{ $row['review_count'] }} reviews</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[11px] text-[var(--muted-foreground)]">No part feedback matched yet.</p>
                        @endforelse
                    </div>
                    <div class="rounded-xl border border-rose-200 bg-rose-50/50 dark:bg-rose-950/20 p-3 space-y-2">
                        <p class="text-[10px] font-semibold uppercase tracking-wider text-rose-800 dark:text-rose-300">▼ Least Popular Parts</p>
                        @forelse($vaParts['least_popular'] ?? [] as $row)
                            <div class="border-b border-rose-200/60 last:border-0 pb-1.5 last:pb-0">
                                <div class="font-bold text-[var(--foreground)] leading-snug">{{ \Illuminate\Support\Str::limit($row['title'], 44) }}</div>
                                <div class="text-[10px] text-[var(--muted-foreground)] flex justify-between mt-0.5">
                                    <span class="font-bold text-rose-700">{{ $row['avg_rating'] !== null ? '★ ' . number_format($row['avg_rating'], 1) : 'Unrated' }}</span>
                                    <span>{{ $row['review_count'] }} reviews</span>
                                </div>
                            </div>
                        @empty
                            <p class="text-[11px] text-[var(--muted-foreground)]">No part feedback matched yet.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Performance Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 sm:gap-6">
            <div class="meramot-card p-4 sm:p-6 shadow-sm lg:col-span-2">
                <h3 class="font-bold text-sm sm:text-base text-[var(--foreground)] mb-3 flex items-center justify-between gap-2">
                    <span>Recent Repair Activity &amp; Completed Milestones</span>
                    <button type="button" @click="activeTab = 'assigned_jobs'" class="text-xs font-bold text-[var(--accent-dark)] hover:underline whitespace-nowrap shrink-0">View All &rarr;</button>
                </h3>
                <div class="divide-y divide-[var(--border)] text-xs">
                    @forelse(($activeJobs ?? collect())->take(4) as $rJob)
                        <div class="py-3 flex flex-wrap sm:flex-nowrap items-start sm:items-center justify-between gap-2.5">
                            <div class="min-w-0 flex-1">
                                <span class="break-words font-bold text-[var(--foreground)] block">
                                    #{{ $rJob->id }} — {{ $rJob->repairRequest?->brand }} {{ $rJob->repairRequest?->model_name }} ({{ $rJob->repairRequest?->title ?? 'Hardware Service' }})
                                </span>
                                <span class="text-[var(--muted-foreground)]">
                                    {{ $rJob->shop?->display_branch_tag ?? ($rJob->shop?->name ?? 'Workshop') }} • Customer: {{ $rJob->repairRequest?->customer?->name ?? 'Meramot Customer' }}
                                </span>
                            </div>
                            <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] font-bold px-3 py-1 text-[10px] shrink-0">
                                {{ $rJob->status }}
                            </span>
                        </div>
                    @empty
                        <div class="py-6 text-center text-[var(--muted-foreground)]">
                            No active repair jobs under this branch scope yet. Submit a marketplace estimate or accept a direct order to begin.
                        </div>
                    @endforelse
                </div>
            </div>

            <!-- Selected Shop Location Summary -->
            <div class="meramot-card p-4 sm:p-6 shadow-sm space-y-3 sm:space-y-4">
                <h3 class="font-bold text-base text-[var(--foreground)]">
                    {{ $publicPreviewShop ? ($publicPreviewShop->display_branch_tag ?: $publicPreviewShop->name) : 'Shop Settings' }}
                </h3>
                @if($publicPreviewShop)
                    <div class="space-y-2.5 text-xs">
                        <div class="rounded-xl bg-[var(--mint-100)] p-3 border border-[var(--border)]">
                            <span class="text-[10px] uppercase font-bold text-[var(--muted-foreground)] block">1. Map Coordinates (Private)</span>
                            <span class="font-semibold text-[var(--foreground)] block mt-0.5">{{ $publicPreviewShop->gps_address ?: 'Pinned on Service Map' }}</span>
                            <span class="font-mono text-[10px] text-[var(--accent-dark)]">{{ number_format((float)$publicPreviewShop->latitude, 5) }}, {{ number_format((float)$publicPreviewShop->longitude, 5) }}</span>
                        </div>
                        <div class="rounded-xl bg-[var(--mint-100)] p-3 border border-[var(--border)]">
                            <div class="flex items-center justify-between">
                                <span class="text-[10px] uppercase font-bold text-[var(--muted-foreground)]">2. Public Storefront Address</span>
                                @if($publicPreviewShop->same_as_gps_location)
                                    <span class="rounded bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-900">Synced with Map</span>
                                @endif
                            </div>
                            <span class="font-semibold text-[var(--foreground)] block mt-0.5">{{ $publicPreviewShop->public_address }}</span>
                        </div>
                        <div class="flex justify-between items-center pt-1">
                            <span class="text-[var(--muted-foreground)]">Inspection / Labor:</span>
                            <span class="font-bold text-[var(--accent-dark)]">৳{{ number_format($publicPreviewShop->inspection_fee ?? 300) }} / ৳{{ number_format($publicPreviewShop->base_labor_fee ?? 500) }}</span>
                        </div>
                    </div>
                @endif
                <button
                    type="button"
                    @click="activeTab = 'branches'"
                    class="block w-full text-center rounded-xl bg-[var(--accent-dark)] py-2.5 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition"
                >
                    Manage Branches &amp; Locations
                </button>
            </div>
        </div>
    </div>

    <!-- ── TAB 5: MULTI-BRANCH EXPANSION & DUAL SHOP ADDRESSES ── -->
    <div x-show="activeTab === 'branches'" x-cloak class="space-y-4 sm:space-y-6">

        <!-- Section A: Branching Option Control Banner -->
        <div class="meramot-card p-4 sm:p-6 shadow-sm">
            <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
                <div>
                    <span class="text-[10px] font-semibold uppercase tracking-wider px-2.5 py-0.5 rounded bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)]">
                        Multi-Branch Architecture
                    </span>
                    <h2 class="text-lg font-bold text-[var(--foreground)] mt-1.5">
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
                        <button type="submit" class="rounded-md bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition whitespace-nowrap">
                            <i class="fa-solid fa-code-branch mr-1"></i> Enable Branching Option
                        </button>
                    </form>
                @else
                    <div class="rounded-lg bg-emerald-100 border border-emerald-300 px-4 py-3 text-xs font-bold text-emerald-900 shrink-0">
                        <i class="fa-solid fa-circle-check text-emerald-700 mr-1"></i>
                        Branching Enabled • {{ count($branches) }} Active {{ count($branches) === 1 ? 'Branch' : 'Branches' }}
                    </div>
                @endif
            </div>
        </div>

        <!-- Section B: Existing Shops / Branches & Dual-Address Editors -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5">
            @foreach($branches as $branch)
                <div
                    class="meramot-card p-3.5 sm:p-5 shadow-sm flex flex-col justify-between space-y-3 sm:space-y-4"
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
                                if (loc.city) {
                                    this.city = window.MeramotLocationCatalog
                                        ? window.MeramotLocationCatalog.findDistrict(loc.city)
                                        : loc.city;
                                }
                                if (loc.area) this.area = loc.area;
                                if (this.sameGps) this.pubAddr = this.gpsAddr;
                            });
                        }
                    }"
                >
                    <div>
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base font-bold text-[var(--foreground)]">{{ $branch->name }}</h3>
                                    @if($branch->display_branch_tag)
                                        <span class="inline-flex items-center gap-1 rounded bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)]">
                                            <i class="fa-solid fa-code-branch text-[8px]"></i>
                                            <span>{{ $branch->display_branch_tag }}</span>
                                        </span>
                                    @else
                                        <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-semibold text-[var(--muted-foreground)]">
                                            Standalone Storefront
                                        </span>
                                    @endif
                                </div>
                                <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                                    {{ $branch->area }}, {{ $branch->city }} • ★ {{ number_format($branch->rating, 1) }} ({{ $branch->review_count }} reviews)
                                </p>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <a href="/shops/{{ $branch->slug }}" class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                                    View Tile
                                </a>
                                <button
                                    type="button"
                                    @click="editing = !editing"
                                    class="rounded-md bg-[var(--accent-dark)] px-3 py-1 text-[11px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition"
                                >
                                    <span x-text="editing ? 'Cancel' : 'Edit Locations'">Edit Locations</span>
                                </button>
                            </div>
                        </div>

                        <!-- Read-only Dual Address Summary -->
                        <div x-show="!editing" class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/70 p-3">
                                <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)] block">
                                    1. GPS Approximate Location
                                </span>
                                <p class="font-semibold text-[var(--foreground)] mt-1">{{ $branch->gps_address ?: 'Pinned on Map' }}</p>
                                <p class="font-mono text-[10px] text-[var(--accent-dark)] mt-1">
                                    {{ number_format((float)$branch->latitude, 5) }}, {{ number_format((float)$branch->longitude, 5) }}
                                </p>
                            </div>

                            <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/70 p-3">
                                <div class="flex items-center justify-between gap-1">
                                    <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)]">
                                        2. Public Storefront Address
                                    </span>
                                    @if($branch->same_as_gps_location)
                                        <span class="rounded bg-emerald-100 px-2 py-0.5 text-[9px] font-bold text-emerald-900">Matches Map Pin</span>
                                    @else
                                        <span class="rounded bg-amber-100 px-2 py-0.5 text-[9px] font-bold text-amber-900">Custom Storefront</span>
                                    @endif
                                </div>
                                <p class="font-semibold text-[var(--foreground)] mt-1">{{ $branch->public_address }}</p>
                            </div>
                        </div>

                        <!-- Inline Edit Branch Addresses Form -->
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
                                    <label class="font-bold text-[var(--foreground)]">1. Map Pin Address *</label>
                                    <button
                                        type="button"
                                        @click="openMapFor({{ $branch->id }})"
                                        class="rounded bg-[var(--accent-dark)] px-2.5 py-1 text-[10px] font-bold text-[var(--accent-foreground)]"
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
                                <span>Use pinned map coordinates as my public storefront address</span>
                            </label>

                            <div>
                                <label class="block font-bold text-[var(--foreground)] mb-1">2. Public Storefront Address *</label>
                                <input
                                    type="text"
                                    name="address"
                                    x-model="pubAddr"
                                    :readonly="sameGps"
                                    required
                                    class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] px-3 py-2 text-xs outline-none"
                                >
                            </div>

                            <div class="space-y-3">
                                @include('vendor.partials.branch-location-picker-fields', [
                                    'cityExpr' => 'city',
                                    'areaExpr' => 'area',
                                    'latExpr' => 'lat',
                                    'lngExpr' => 'lng',
                                    'gpsAddressExpr' => 'gpsAddr',
                                    'addressExpr' => 'pubAddr',
                                    'sameAsGpsExpr' => 'sameGps',
                                    'contextKey' => 'edit-branch-' . $branch->id,
                                    'cityLabel' => 'District / City',
                                    'areaSelectLabel' => 'Narrowed-Down Location',
                                    'areaInputLabel' => 'Area / Place Name',
                                ])
                                <div>
                                    <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-1">Phone</label>
                                    <input
                                        type="tel"
                                        name="phone"
                                        x-model="phone"
                                        autocomplete="tel"
                                        inputmode="tel"
                                        maxlength="14"
                                        placeholder="01XXXXXXXXX"
                                        class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs outline-none focus:border-[var(--accent-dark)]"
                                    >
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
        <div class="meramot-card p-3.5 sm:p-6 md:p-7 shadow-sm">
            <div class="mb-4 sm:mb-5 border-b border-[var(--border)] pb-3 sm:pb-4 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--accent-dark)]">Business Expansion</span>
                    <h3 class="text-lg font-bold text-[var(--foreground)]">Open a New Shop Branch Under "{{ $brandTitle }}"</h3>
                    <p class="text-xs text-[var(--muted-foreground)] mt-0.5">
                        Your new branch keeps the exact brand name <strong>{{ $brandTitle }}</strong> and displays its <strong>Branch Tag</strong> on all shop name tiles.
                    </p>
                </div>
                <button
                    type="button"
                    @click="openMapFor('new')"
                    class="inline-flex items-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition"
                >
                    <i class="fa-solid fa-map-location-dot"></i>
                    <span>Pin New Branch on Map</span>
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
                            class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-100)] p-3 text-xs font-bold text-[var(--accent-dark)] cursor-not-allowed"
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
                            class="w-full rounded-lg border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] p-3 text-xs outline-none"
                        >
                    </div>
                </div>

                @if(!$vendorProfile->is_branched)
                    <div class="rounded-lg border border-amber-300 bg-amber-50/80 p-3.5">
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

                <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/60 p-4 space-y-3">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">
                            1. Map Pin Address * <span class="font-normal text-[var(--muted-foreground)]">(Pin on map or enter street and neighborhood)</span>
                        </label>
                        <input
                            type="text"
                            name="gps_address"
                            x-model="newBranch.gpsAddress"
                            @input="if (newBranch.sameAsGps) newBranch.vendorAddress = newBranch.gpsAddress"
                            placeholder="e.g. Zindabazar Point, Sylhet"
                            required
                            class="w-full rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 text-xs outline-none"
                        >
                        <div class="mt-2.5 space-y-3">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-0.5">GPS Latitude *</label>
                                    <input type="number" step="any" min="-90" max="90" name="latitude" x-model="newBranch.lat" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-1.5 text-xs font-mono">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-0.5">GPS Longitude *</label>
                                    <input type="number" step="any" min="-180" max="180" name="longitude" x-model="newBranch.lng" required class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-1.5 text-xs font-mono">
                                </div>
                            </div>
                            @include('vendor.partials.branch-location-picker-fields', [
                                'cityExpr' => 'newBranch.city',
                                'areaExpr' => 'newBranch.area',
                                'latExpr' => 'newBranch.lat',
                                'lngExpr' => 'newBranch.lng',
                                'gpsAddressExpr' => 'newBranch.gpsAddress',
                                'addressExpr' => 'newBranch.vendorAddress',
                                'sameAsGpsExpr' => 'newBranch.sameAsGps',
                                'contextKey' => 'new-branch',
                            ])
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
                            class="w-full rounded-lg border-2 border-[var(--border)] p-3 text-xs outline-none"
                        >
                    </div>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Branch Contact Phone</label>
                        <input type="tel" name="phone" autocomplete="tel" inputmode="tel" maxlength="14" placeholder="+88017XXXXXXXX" class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] p-2.5 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Inspection Fee (৳)</label>
                        <input type="number" min="0" step="0.01" name="inspection_fee" value="250" onwheel="this.blur()" class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] p-2.5 text-xs">
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Base Labor Fee (৳)</label>
                        <input type="number" min="0" step="0.01" name="base_labor_fee" value="500" onwheel="this.blur()" class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] p-2.5 text-xs">
                    </div>
                </div>

                <button type="submit" class="rounded-md bg-[var(--accent-dark)] px-7 py-3 text-xs font-bold text-[var(--accent-foreground)] shadow-md hover:opacity-90 transition">
                    <i class="fa-solid fa-plus mr-1.5"></i> Launch New Branch Under {{ $brandTitle }}
                </button>
            </form>
        </div>
    </div>

    <!-- ── TAB 2: MARKETPLACE LIVE BIDDING (Real Database Tickets & Bid Persistence) ── -->
    <div x-show="activeTab === 'marketplace'" x-cloak class="space-y-4 sm:space-y-6">
        <div class="rounded-lg bg-[var(--mint-100)] border border-[var(--border)] p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 text-xs">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-bullhorn text-amber-700 text-lg"></i>
                <span>Marketplace Estimating: Submit itemized parts cost, labor fees, and turnaround days directly to customer repair tickets.</span>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <a href="/vendor/alert-settings" class="rounded-md border border-[var(--border)] bg-[var(--card)] text-[var(--foreground)] px-3.5 py-1.5 font-bold hover:bg-[var(--mint-50)] transition">
                    SMS Alerts
                </a>
                <a href="/vendor/bidding-board" class="rounded-md bg-[var(--accent-dark)] text-white px-4 py-1.5 font-bold hover:opacity-90 transition">
                    Full Board View
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-5">
            @forelse($marketplaceRequests ?? [] as $mReq)
                <div class="meramot-card p-4 sm:p-6 shadow-sm flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between mb-2 gap-2">
                            <span class="text-[10px] font-bold uppercase tracking-wider bg-emerald-800 text-white px-2.5 py-0.5 rounded">
                                Bidding Open • #{{ $mReq->id }} ({{ $mReq->bids->count() }} {{ Str::plural('bid', $mReq->bids->count()) }})
                            </span>
                            <a href="{{ route('requests.track', $mReq->id) }}" class="text-[11px] font-bold text-[var(--accent-dark)] hover:underline shrink-0">
                                Customer View &rarr;
                            </a>
                        </div>
                        <h3 class="font-bold text-lg text-[var(--foreground)]">{{ $mReq->brand }} {{ $mReq->model_name }}</h3>
                        <p class="text-xs font-semibold text-[var(--accent-dark)] mt-1">{{ $mReq->title }}</p>
                        <p class="text-xs text-[var(--muted-foreground)] mt-2 leading-relaxed line-clamp-3">
                            {{ $mReq->description }}
                        </p>
                        <div class="mt-3 text-[11px] text-[var(--muted-foreground)] flex flex-wrap items-center gap-3">
                            <span><i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i> {{ $mReq->pickup_address ?? 'Dhaka' }}</span>
                            <span><i class="fa-solid fa-truck text-[var(--accent-dark)]"></i> {{ ucwords(str_replace('_', ' ', $mReq->preferred_service_type ?? 'Pickup & Dropoff')) }}</span>
                        </div>
                    </div>

                    <div class="mt-5 pt-4 border-t border-[var(--border)]">
                        @include('vendor.partials.bid-quote-widget', [
                            'req' => $mReq,
                            'shopOptions' => $branches,
                            'lockedShopId' => $activeBranch?->id,
                        ])
                    </div>
                </div>
            @empty
                <div class="lg:col-span-2 meramot-card p-8 text-center text-xs text-[var(--muted-foreground)]">
                    No open marketplace tickets awaiting estimates at this time.
                </div>
            @endforelse
        </div>
    </div>

    <!-- ── TAB 3: DIRECT ORDERS (Direct-Order Cart Queue) ── -->
    <div x-show="activeTab === 'direct_orders'" x-cloak class="space-y-4">
        <div class="meramot-card p-4 sm:p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <div>
                    <h3 class="font-bold text-base text-[var(--foreground)]">Incoming Direct Orders</h3>
                    <p class="text-xs text-[var(--muted-foreground)] mt-0.5">Fixed-price package and workshop service bookings awaiting confirmation.</p>
                </div>
                <a href="/packages/catalog" class="text-xs font-bold text-[var(--accent-dark)] hover:underline whitespace-nowrap">View Service Packages &rarr;</a>
            </div>
            <div class="space-y-3 sm:space-y-4 text-xs">
                @forelse($directOrders ?? [] as $order)
                    @php
                        $orderNeedsCourier = $order->requiresCourierLogistics();
                    @endphp
                    <div class="p-3.5 sm:p-4 rounded-lg bg-[var(--mint-100)] border-2 border-emerald-600 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded bg-emerald-800 text-white font-bold text-[10px] px-2 py-0.5 uppercase whitespace-nowrap">Direct Order #{{ $order->id }}</span>
                                <span class="rounded px-2 py-0.5 text-[10px] font-bold uppercase whitespace-nowrap {{ $orderNeedsCourier ? 'bg-sky-100 text-sky-900' : 'bg-emerald-200/80 text-emerald-950' }}">
                                    {{ $orderNeedsCourier ? 'Courier Pickup' : 'In-Shop Walk-In' }}
                                </span>
                                <span class="text-[11px] font-bold text-[var(--accent-dark)] break-words">
                                    {{ $order->requestedShop->name ?? 'Assigned Workshop' }}{{ optional($order->requestedShop)->display_branch_tag ? ' [' . $order->requestedShop->display_branch_tag . ']' : '' }}
                                </span>
                                <a href="{{ route('requests.track', $order->id) }}" class="text-[11px] font-semibold text-[var(--accent-dark)] underline whitespace-nowrap">View Order</a>
                            </div>
                            <h4 class="font-bold text-sm text-[var(--foreground)] mt-1 break-words">{{ $order->title }}</h4>
                            <p class="text-[var(--muted-foreground)] break-words">
                                Customer: {{ $order->customer->name ?? 'Meramot Customer' }}
                                @if($orderNeedsCourier)
                                    • {{ $order->pickup_address ?? 'Dhaka' }} • {{ strtoupper((string) ($order->delivery_type ?? 'REGULAR')) === 'EXPRESS' ? 'Express Courier' : 'Standard Courier' }}
                                @else
                                    • In-Shop Walk-In (৳0 Delivery — No Courier)
                                @endif
                            </p>
                            <span class="font-semibold text-[var(--accent-dark)] text-sm">৳{{ number_format($order->quoted_final_amount ?? 0, 2) }}</span>
                        </div>
                        <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-2 shrink-0 w-full sm:w-auto">
                            <form method="POST" action="{{ route('vendor.direct-orders.respond', $order->id) }}" class="w-full sm:w-auto">
                                @csrf
                                <input type="hidden" name="decision" value="accept">
                                <button type="submit" class="w-full sm:w-auto rounded-md bg-[var(--accent-dark)] text-[var(--accent-foreground)] font-bold px-3 sm:px-4 py-2 hover:opacity-90 shadow-sm transition whitespace-nowrap text-[11px] sm:text-xs">
                                    {{ $orderNeedsCourier ? 'Accept & Dispatch Rider' : 'Accept Walk-In Order' }}
                                </button>
                            </form>
                            <form method="POST" action="{{ route('vendor.direct-orders.respond', $order->id) }}" class="w-full sm:w-auto">
                                @csrf
                                <input type="hidden" name="decision" value="decline">
                                <button type="submit" class="w-full sm:w-auto rounded-md border border-rose-300 bg-white text-rose-700 font-bold px-3 sm:px-4 py-2 hover:bg-rose-50 transition whitespace-nowrap text-[11px] sm:text-xs">
                                    Decline
                                </button>
                            </form>
                        </div>
                    </div>
                @empty
                    <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/40 py-8 text-center text-[var(--muted-foreground)]">
                        No pending direct orders for this branch.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- ── TAB 4: MY REPAIR JOBS (Real Database Bench Workflow & Quote 2 Dispatch) ── -->
    <div x-show="activeTab === 'assigned_jobs'" x-cloak class="space-y-4">
        <div class="meramot-card p-3.5 sm:p-6 shadow-sm">
            <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                <div>
                    <h3 class="font-bold text-base text-[var(--foreground)]">Active Repair Queue &amp; Bench Milestones</h3>
                    <p class="text-xs text-[var(--muted-foreground)]">Update live bench status, issue diagnostic quotes, or mark jobs completed to credit your full quoted subtotal to workshop escrow.</p>
                </div>
            </div>
            <div class="divide-y divide-[var(--border)] text-xs">
                @include('vendor.partials.quote-builder-script')
                @forelse($activeJobs ?? [] as $job)
                    @php
                        $initialSelectStatus = $job->status === 'READY_TO_RETURN' ? 'RETURN_SCHEDULED' : $job->status;
                        $jobDeliveries = $job->repairRequest?->deliveryJobs?->sortBy('id') ?? collect();
                        $resolvedJobParts = $job->resolvedPartsItems();
                        $resolvedJobPartsCost = $job->resolvedPartsCost();
                        $resolvedJobLaborCost = $job->resolvedLaborCost();
                        $resolvedJobHasWarranty = $job->hasWarrantyCoverage();
                        $resolvedJobWarrantyDays = $job->resolvedWarrantyDays();
                        $savedQuotePayload = [
                            'parts_items' => $resolvedJobParts,
                            'parts_cost' => $resolvedJobPartsCost,
                            'labor_cost' => $resolvedJobLaborCost,
                            'final_quoted_amount' => round((float) ($job->final_quoted_amount ?? $job->repairRequest?->quoted_final_amount ?? ($resolvedJobPartsCost + $resolvedJobLaborCost)), 2),
                            'has_warranty' => $resolvedJobHasWarranty,
                            'warranty_days' => $resolvedJobWarrantyDays,
                            'diagnosis_notes' => (string) ($job->diagnosis_notes ?? ''),
                        ];
                    @endphp
                    <div
                        class="py-4 space-y-3"
                        x-data="window.meramotJobQuoteForm({
                            initialStatus: @js($initialSelectStatus),
                            openInitially: @js($initialSelectStatus === 'WAITING_APPROVAL'),
                            savedQuote: @js($savedQuotePayload)
                        })"
                    >
                        <form method="POST" action="{{ route('vendor.jobs.status', $job->id) }}" class="space-y-3">
                            @csrf
                            <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                                <div class="min-w-0 flex-1 space-y-1.5">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="break-words font-bold text-sm text-[var(--foreground)]">
                                            Job #{{ $job->id }}: {{ $job->repairRequest?->brand }} {{ $job->repairRequest?->model_name }} — {{ $job->repairRequest?->title ?? 'Hardware Service' }}
                                        </span>
                                        <span class="rounded bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] font-bold text-[10px] px-2.5 py-0.5">
                                            {{ $job->status }}
                                        </span>
                                        @if($job->shop?->display_branch_tag)
                                            <span class="rounded bg-emerald-800 text-white font-bold text-[10px] px-2 py-0.5">
                                                {{ $job->shop->display_branch_tag }}
                                            </span>
                                        @endif
                                    </div>

                                    <p class="text-xs text-[var(--muted-foreground)] break-words">
                                        Customer: {{ $job->repairRequest?->customer?->name ?? 'Meramot Customer' }}
                                        • Quoted: <strong>৳{{ number_format($savedQuotePayload['final_quoted_amount'], 2) }}</strong>
                                        @if($job->repairRequest)
                                            • <a href="{{ route('requests.track', $job->repairRequest->id) }}" class="font-bold text-[var(--accent-dark)] hover:underline">Track Ticket #{{ $job->repairRequest->id }} &rarr;</a>
                                        @endif
                                    </p>

                                    <!-- Saved Itemized Quote & Per-Part Warranty Preview Strip -->
                                    @php
                                        $resolvedJobWarrantySummary = \App\Models\Bid::formatPartsWarrantySummary($resolvedJobParts, $resolvedJobHasWarranty, $resolvedJobWarrantyDays);
                                    @endphp
                                    <div class="flex flex-wrap items-center gap-1.5 pt-0.5 text-[11px]">
                                        @foreach($resolvedJobParts as $pIdx => $pRow)
                                            @php
                                                $pRowDays = (int) ($pRow['warranty_days'] ?? 0);
                                                $pRowWarranty = (bool) ($pRow['has_warranty'] ?? ($pRowDays > 0)) && $pRowDays > 0;
                                            @endphp
                                            <span class="inline-flex flex-wrap items-center gap-1 rounded border border-[var(--border)] bg-[var(--mint-100)]/60 px-2.5 py-0.5 text-[var(--foreground)]">
                                                <strong class="text-[var(--accent-dark)]">{{ $pIdx + 1 }}.</strong>
                                                <span>{{ $pRow['name'] }}:</span>
                                                <strong>৳{{ number_format($pRow['cost'], 2) }}</strong>
                                                <span class="rounded px-1.5 py-0.5 text-[9px] font-bold {{ $pRowWarranty ? 'bg-emerald-100 text-emerald-900' : 'bg-[var(--card)] text-[var(--muted-foreground)]' }}">
                                                    {{ $pRowWarranty ? "{$pRowDays}d warranty" : 'No warranty' }}
                                                </span>
                                            </span>
                                        @endforeach
                                        <span class="inline-flex items-center gap-1 rounded border border-[var(--border)] bg-[var(--card)] px-2.5 py-0.5 text-[var(--foreground)]">
                                            Labor: <strong>৳{{ number_format($resolvedJobLaborCost, 2) }}</strong>
                                        </span>
                                        <span class="inline-flex items-center gap-1 rounded px-2.5 py-0.5 font-bold {{ $resolvedJobHasWarranty ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-[var(--card)] text-[var(--muted-foreground)] border border-[var(--border)]' }}">
                                            <i class="{{ $resolvedJobHasWarranty ? 'fa-solid fa-shield-halved text-emerald-700' : 'fa-regular fa-circle-xmark' }}"></i>
                                            {{ $resolvedJobWarrantySummary }}
                                        </span>
                                    </div>
                                </div>

                                <div class="flex flex-wrap sm:flex-nowrap items-center gap-2 w-full lg:w-auto shrink-0">
                                    @php $jobNeedsCourier = $job->repairRequest?->requiresCourierLogistics() ?? true; @endphp
                                    <select name="status" x-model="chosenStatus" class="w-full sm:w-auto min-w-0 max-w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2 font-bold text-xs outline-none">
                                        <option value="AT_SHOP">At Shop (Received)</option>
                                        <option value="DIAGNOSING">Diagnosing Bench</option>
                                        <option value="WAITING_APPROVAL">Send Final Quote (Await Approval)</option>
                                        <option value="REPAIRING">Repairing / Servicing</option>
                                        <option value="RETURN_SCHEDULED">{{ $jobNeedsCourier ? 'Ready for Return Delivery (Dispatch Courier)' : 'Ready for Customer Pickup (At Shop)' }}</option>
                                        <option value="COMPLETED">Completed (Settle Ledger)</option>
                                    </select>

                                    <button
                                        type="button"
                                        x-show="chosenStatus !== 'WAITING_APPROVAL'"
                                        @click="toggleQuoteEditor()"
                                        class="flex-1 sm:flex-initial rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition shrink-0 whitespace-nowrap"
                                        x-text="editingQuote ? 'Hide Quote Editor' : 'Edit Final Quote'"
                                    ></button>

                                    <button type="submit" class="flex-1 sm:flex-initial rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-white hover:opacity-90 transition shrink-0 whitespace-nowrap">
                                        Save Status
                                    </button>
                                </div>
                            </div>

                            <!-- Collapsible Bench Quote 2 Builder (Parts Breakdown + Warranty Tick & Days) -->
                            <template x-if="showQuoteBuilder">
                                <div class="rounded-lg border border-amber-400/80 bg-amber-50/70 dark:bg-[var(--mint-100)]/10 p-3.5 sm:p-4 space-y-3 text-xs">
                                    <div class="flex flex-wrap items-center justify-between gap-2">
                                        <div>
                                            <span class="text-[10px] font-bold uppercase tracking-wider text-amber-900 dark:text-amber-300">
                                                Bench Diagnosis — Final Quote Breakdown
                                            </span>
                                            <p class="text-[11px] text-[var(--muted-foreground)]">
                                                Update itemized replacement parts, bench labor, and customer warranty terms before saving.
                                            </p>
                                        </div>
                                        <div class="text-right">
                                            <span class="block text-[10px] font-bold uppercase text-[var(--muted-foreground)]">Final Bench Total</span>
                                            <span class="text-base font-bold text-[var(--accent-dark)]">৳<span x-text="formatMoney(totalQuote)">0.00</span></span>
                                        </div>
                                    </div>

                                    @include('vendor.partials.parts-and-warranty-fields', [
                                        'containerBg' => 'bg-white/80 dark:bg-[var(--card)]',
                                        'partFieldBg' => 'bg-[var(--mint-100)]/40 dark:bg-[var(--mint-300)]/20',
                                    ])

                                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                                        <div>
                                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-1">Bench Labor (৳)</label>
                                            <input
                                                type="number"
                                                step="0.01"
                                                min="0"
                                                name="labor_cost"
                                                x-model="laborCost"
                                                onwheel="this.blur()"
                                                placeholder="900"
                                                class="w-full rounded-xl border border-amber-400/70 bg-white dark:bg-[var(--mint-300)] px-3 py-2 text-xs font-bold text-[var(--foreground)] outline-none"
                                            >
                                        </div>
                                        <div class="sm:col-span-2">
                                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-1">Diagnosis Notes</label>
                                            <input
                                                type="text"
                                                name="diagnosis_notes"
                                                x-model="diagnosisNotes"
                                                placeholder="Bench findings & component health summary..."
                                                class="w-full rounded-xl border border-amber-400/70 bg-white dark:bg-[var(--mint-300)] px-3 py-2 text-xs text-[var(--foreground)] outline-none"
                                            >
                                        </div>
                                    </div>
                                    <input type="hidden" name="final_quoted_amount" :value="totalQuote.toFixed(2)">
                                </div>
                            </template>
                        </form>

                        @if($jobDeliveries->isNotEmpty())
                            <div class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/55 p-3 space-y-2">
                                @foreach($jobDeliveries as $trip)
                                    @php
                                        $isReturn = strtoupper((string) $trip->direction) === 'TO_CUSTOMER';
                                        $tripStatus = strtoupper((string) $trip->status);
                                        $shopHandoverCode = !$isReturn
                                            ? ($tripStatus !== 'DELIVERED' ? $trip->dropoff_otp : null)
                                            : (in_array($tripStatus, ['PENDING', 'SCHEDULED', 'DISPATCHED'], true) ? $trip->pickup_otp : null);
                                    @endphp
                                    <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
                                        <div class="flex flex-wrap items-center gap-2 min-w-0">
                                            <span class="rounded bg-white border border-[var(--border)] px-2 py-0.5 font-bold text-[var(--accent-dark)]">
                                                {{ $isReturn ? 'Return Courier' : 'Inbound Courier' }}
                                            </span>
                                            <span class="font-bold text-[var(--foreground)]">{{ $trip->milestone_label }}</span>
                                            @if($trip->rider_name || $trip->deliveryAgent?->user?->name)
                                                <span class="text-[var(--muted-foreground)]">&bull; {{ $trip->rider_name ?? $trip->deliveryAgent?->user?->name }}</span>
                                            @endif
                                        </div>
                                        @if($shopHandoverCode)
                                            <span class="rounded-lg border border-amber-300 bg-amber-50 px-2.5 py-0.5 font-mono font-bold text-amber-950">
                                                {{ $isReturn ? 'Workshop Release Code:' : 'Workshop Check-In Code:' }} {{ $shopHandoverCode }}
                                            </span>
                                        @endif
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="py-8 text-center text-[var(--muted-foreground)]">
                        No assigned repair jobs in this branch queue right now.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- ── TAB 6: EARNINGS & PAYOUTS (Feature R18) ── -->
    <div x-show="activeTab === 'payouts'" x-cloak class="space-y-4 sm:space-y-6">
        <div class="grid grid-cols-2 lg:grid-cols-5 gap-2.5 sm:gap-4 text-xs">
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 sm:p-4 shadow-sm min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)] leading-tight">Gross Customer Inflow</p>
                <p class="text-base sm:text-xl font-bold text-[var(--foreground)] mt-1 whitespace-nowrap">৳{{ number_format($branchMetrics['gross_inflow'] ?? 0, 2) }}</p>
            </div>
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 sm:p-4 shadow-sm min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)] leading-tight">Platform Service Fee</p>
                <p class="text-base sm:text-xl font-bold text-amber-800 mt-1 whitespace-nowrap">৳{{ number_format($branchMetrics['commission_deducted'] ?? 0, 2) }}</p>
            </div>
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 sm:p-4 shadow-sm min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)] leading-tight">100% Vendor Escrow Allocated</p>
                <p class="text-base sm:text-xl font-bold text-[var(--accent-dark)] mt-1 whitespace-nowrap">৳{{ number_format($branchMetrics['net_allocated'] ?? 0, 2) }}</p>
            </div>
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 sm:p-4 shadow-sm min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)] leading-tight">Already Disbursed</p>
                <p class="text-base sm:text-xl font-bold text-[var(--foreground)] mt-1 whitespace-nowrap">৳{{ number_format($branchMetrics['paid_out'] ?? 0, 2) }}</p>
            </div>
            <div class="col-span-2 lg:col-span-1 rounded-lg border border-emerald-400 bg-emerald-50 p-3 sm:p-4 shadow-sm min-w-0">
                <p class="text-[10px] font-semibold uppercase tracking-wider text-emerald-900 leading-tight">Available to Withdraw</p>
                <p class="text-lg sm:text-xl font-bold text-emerald-800 mt-1 whitespace-nowrap">৳{{ number_format($branchMetrics['available_to_request'] ?? 0, 2) }}</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-4 sm:gap-5 text-xs">
            <!-- Request Payout Form -->
            <div class="lg:col-span-6 rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm space-y-4">
                <div class="flex items-center justify-between gap-2">
                    <div>
                        <h2 class="text-base font-bold text-[var(--foreground)]">Request Net Escrow Withdrawal</h2>
                        <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">Withdraw 100% of your listed service &amp; spare part earnings to your registered MFS or bank account.</p>
                    </div>
                    <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-[10px] font-bold text-[var(--accent-dark)]">100% Vendor Share</span>
                </div>

                <form method="POST" action="{{ route('vendor.payouts.request') }}" class="space-y-3">
                    @csrf
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Workshop Branch</label>
                        <select name="shop_id" class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-bold text-[var(--foreground)]">
                            @foreach($branches as $bShop)
                                <option value="{{ $bShop->id }}" @selected($activeBranch && $activeBranch->id === $bShop->id)>
                                    {{ $bShop->name }} ({{ $bShop->area }}, {{ $bShop->city }})
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Withdrawal Amount (৳)</label>
                            <input type="number" step="0.01" min="100" name="amount"
                                   value="{{ number_format(max(100, (float) ($branchMetrics['available_to_request'] ?? 0)), 2, '.', '') }}"
                                   class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-mono font-bold text-[var(--foreground)]">
                        </div>
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Channel</label>
                            <select name="payout_method" class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-bold text-[var(--foreground)]">
                                @foreach(['BKASH' => 'bKash Merchant/Personal', 'NAGAD' => 'Nagad', 'ROCKET' => 'Rocket', 'BANK' => 'BEFTN Bank Transfer'] as $mCode => $mLbl)
                                    <option value="{{ $mCode }}" @selected(($vendorProfile->payout_method ?? 'BKASH') === $mCode)>{{ $mLbl }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Account / Wallet Number</label>
                        <input type="text" name="payout_account_number" required
                               value="{{ $vendorProfile->payout_account_number ?? $vendorProfile->phone ?? auth()->user()?->phone ?? '' }}"
                               placeholder="017XXXXXXXX or Bank Account Number"
                               class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-mono font-bold text-[var(--foreground)]">
                    </div>
                    <button type="submit" class="w-full rounded-md bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] py-3 px-5 font-bold transition shadow">
                        <i class="fa-solid fa-paper-plane mr-1"></i> Submit Payout Withdrawal Request
                    </button>
                </form>
            </div>

            <!-- Default Payout Account Settings -->
            <div class="lg:col-span-6 rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-bold text-[var(--foreground)]">Saved Settlement Destination</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">Used for automated batch settlements and 1-click withdrawals.</p>
                </div>

                <form method="POST" action="{{ route('vendor.payouts.settings') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Default Method</label>
                            <select name="payout_method" class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-bold text-[var(--foreground)]">
                                @foreach(['BKASH' => 'bKash', 'NAGAD' => 'Nagad', 'ROCKET' => 'Rocket', 'BANK' => 'Bank BEFTN'] as $mCode => $mLbl)
                                    <option value="{{ $mCode }}" @selected(($vendorProfile->payout_method ?? 'BKASH') === $mCode)>{{ $mLbl }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Account Holder Name</label>
                            <input type="text" name="payout_account_name"
                                   value="{{ $vendorProfile->payout_account_name ?? auth()->user()?->name ?? '' }}"
                                   class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-semibold text-[var(--foreground)]">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Wallet / Account No.</label>
                            <input type="text" name="payout_account_number" required
                                   value="{{ $vendorProfile->payout_account_number ?? $vendorProfile->phone ?? '' }}"
                                   class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-mono font-bold text-[var(--foreground)]">
                        </div>
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Bank Name (Optional)</label>
                            <input type="text" name="payout_bank_name" value="{{ $vendorProfile->payout_bank_name ?? '' }}" placeholder="BRAC / City Bank"
                                   class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 text-[var(--foreground)]">
                        </div>
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Branch (Optional)</label>
                            <input type="text" name="payout_branch_name" value="{{ $vendorProfile->payout_branch_name ?? '' }}" placeholder="Banani"
                                   class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 text-[var(--foreground)]">
                        </div>
                    </div>
                    <button type="submit" class="w-full rounded-md border border-[var(--accent-dark)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] py-2.5 px-5 font-bold text-[var(--accent-dark)] transition">
                        Save Settlement Destination
                    </button>
                </form>
            </div>
        </div>

        <!-- Workshop Payout History -->
        <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm space-y-3 text-xs">
            <h2 class="text-base font-bold text-[var(--foreground)]">Workshop Payout History</h2>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="border-b border-[var(--border)] text-[10px] font-bold uppercase text-[var(--muted-foreground)]">
                            <th class="py-2.5 pr-3">Reference</th>
                            <th class="py-2.5 pr-3">Branch</th>
                            <th class="py-2.5 pr-3">Destination</th>
                            <th class="py-2.5 pr-3">Status</th>
                            <th class="py-2.5 text-right">Net Amount</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                        @forelse($vendorPayoutHistory ?? [] as $vPyt)
                            <tr>
                                <td class="py-3 pr-3 font-mono font-bold text-[var(--foreground)]">
                                    {{ $vPyt->reference_code }}
                                    @if($vPyt->transaction_ref)
                                        <span class="block text-[10px] text-emerald-800">TXN: {{ $vPyt->transaction_ref }}</span>
                                    @endif
                                </td>
                                <td class="py-3 pr-3 font-semibold">{{ $vPyt->shop?->name ?? 'Workshop' }}</td>
                                <td class="py-3 pr-3">{{ $vPyt->payout_method ?? 'BKASH' }} • {{ $vPyt->payout_account ?? 'Default' }}</td>
                                <td class="py-3 pr-3">
                                    <span class="rounded px-2.5 py-0.5 text-[10px] font-semibold uppercase {{ strtoupper((string) $vPyt->status) === 'PAID' ? 'bg-emerald-100 text-emerald-900' : 'bg-amber-100 text-amber-900' }}">
                                        {{ $vPyt->status }}
                                    </span>
                                </td>
                                <td class="py-3 text-right font-mono font-bold text-[var(--accent-dark)]">৳{{ number_format((float) $vPyt->amount, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="py-6 text-center text-[var(--muted-foreground)]">No payout disbursements recorded for this branch scope yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── TAB 7: SPARE PARTS MARKETPLACE INVENTORY & OFFERS ── -->
    @include('vendor.partials.spare-parts-tab')

    <!-- ── TAB 8: VENDOR BRANCH & ALL-BRANCHES VOUCHERS ── -->
    @include('vendor.partials.vouchers-tab')

</div>
@endsection

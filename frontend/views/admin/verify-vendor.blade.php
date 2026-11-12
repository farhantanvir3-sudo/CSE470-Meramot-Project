{{--
=============================================================================
FEATURE R11: Admin Vendor Business Profile & Dual-Location Verification Desk
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
Controller: VendorOnboardingController@adminIndex, approve, reject, toggleFeatured
Models Touched: VendorProfile, RepairShop, User
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Admin — Verify Vendor Partners | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-8">

    @include('admin.partials.nav')

    <!-- Page Header -->
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">Partner Governance</p>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Vendor Business Verification &amp; Storefront Audit</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Audit submitted Trade Licenses, Owner NIDs, Dual Shop Locations (GPS Pin vs Public Address), and Multi-Branch tags before publishing workshops to customers.
            </p>
            <span class="dev-only-badge text-[10px] font-mono px-2.5 py-0.5 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mt-1.5">
                Feature R11 • Assigned to: Farhan Tanvir [22299179] (Team Lead)
            </span>
        </div>
        <a
            href="/admin/vendors/pending"
            title="Refresh Queue"
            aria-label="Refresh Queue"
            class="group mt-1 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] hover:shadow-[0_8px_20px_-4px_rgba(22,54,37,0.35)]"
        >
            <i class="fa-solid fa-rotate-right text-sm transition-transform duration-300 group-hover:rotate-180"></i>
        </a>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-5 gap-4">
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)] block">Pending Audits</span>
            <span class="text-2xl font-black text-amber-700 mt-1 block">{{ $stats['pendingCount'] }}</span>
        </div>
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)] block">Approved Brands</span>
            <span class="text-2xl font-black text-emerald-700 mt-1 block">{{ number_format($stats['approvedCount']) }}</span>
        </div>
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)] block">Public Storefronts</span>
            <span class="text-2xl font-black text-[var(--foreground)] mt-1 block">{{ number_format($stats['publicStorefrontsCount']) }}</span>
        </div>
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)] block">Multi-Branch Brands</span>
            <span class="text-2xl font-black text-[var(--accent-dark)] mt-1 block">{{ $stats['branchedBrandsCount'] }}</span>
        </div>
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 shadow-sm">
            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)] block">Rejected</span>
            <span class="text-2xl font-black text-rose-700 mt-1 block">{{ $stats['rejectedCount'] }}</span>
        </div>
    </div>

    <!-- Pending Vendor Verification Queue -->
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-5">
        <div class="flex items-center justify-between border-b border-[var(--border)] pb-3">
            <h2 class="text-base font-black text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-clipboard-check text-amber-600"></i>
                <span>Pending Vendor Onboarding Applications ({{ $pendingVendors->count() }})</span>
            </h2>
            <span class="rounded-full bg-amber-100 px-3 py-1 text-[11px] font-bold text-amber-900">
                Requires Admin Sign-Off
            </span>
        </div>

        @forelse($pendingVendors as $vendor)
            @php
                $primaryShop = $vendor->shops->first();
            @endphp
            <div class="rounded-2xl border-2 border-amber-300 bg-[var(--mint-100)]/50 p-5 space-y-4" x-data="{ rejectOpen: false }">
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div class="space-y-2 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="rounded-full bg-amber-500 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-white">Pending Review</span>
                            <h3 class="text-lg font-black text-[var(--foreground)]">{{ $vendor->shop_name }}</h3>
                            @if($vendor->is_branched && $primaryShop?->branch_name)
                                <span class="rounded-full bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)]">
                                    Branch: {{ $primaryShop->branch_name }}
                                </span>
                            @endif
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs pt-1">
                            <div class="rounded-xl bg-[var(--card)] p-3 border border-[var(--border)]">
                                <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Owner &amp; Contact</span>
                                <span class="font-bold text-[var(--foreground)] block mt-0.5">{{ $vendor->user->name ?? 'Vendor Applicant' }}</span>
                                <span class="text-[11px] text-[var(--muted-foreground)]">{{ $vendor->user->email ?? 'N/A' }} • {{ $vendor->user->phone ?? 'N/A' }}</span>
                            </div>

                            <div class="rounded-xl bg-[var(--card)] p-3 border border-[var(--border)]">
                                <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Trade License &amp; NID</span>
                                <span class="font-mono font-bold text-[var(--foreground)] block mt-0.5">{{ $vendor->business_license_no }}</span>
                                <span class="font-mono text-[11px] text-[var(--muted-foreground)]">NID: {{ $vendor->nid_number }}</span>
                            </div>

                            <div class="rounded-xl bg-[var(--card)] p-3 border border-[var(--border)]">
                                <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Dual Address Audit</span>
                                <span class="text-[11px] font-bold text-[var(--foreground)] block mt-0.5">Public: {{ $vendor->shop_address }}</span>
                                <span class="text-[11px] text-[var(--muted-foreground)] block">GPS Pin: {{ $vendor->gps_address ?? $vendor->shop_address }} ({{ $vendor->area }}, {{ $vendor->city }})</span>
                            </div>
                        </div>

                        @if(!empty($vendor->categories))
                            <div class="flex flex-wrap items-center gap-1.5 pt-1">
                                @foreach((array) $vendor->categories as $cat)
                                    <span class="rounded-full border border-[var(--border)] bg-[var(--card)] px-2.5 py-0.5 text-[10px] font-semibold text-[var(--foreground)]">
                                        {{ $cat }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        @if($primaryShop)
                            <a href="{{ route('shops.show', $primaryShop->slug) }}" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                                Preview Page
                            </a>
                        @endif

                        <form method="POST" action="{{ route('admin.vendors.approve', $vendor->id) }}">
                            @csrf
                            <button type="submit" class="rounded-full bg-emerald-700 hover:bg-emerald-800 px-5 py-2 text-xs font-bold text-white shadow transition">
                                <i class="fa-solid fa-check mr-1"></i> Approve &amp; Publish
                            </button>
                        </form>

                        <button
                            type="button"
                            @click="rejectOpen = !rejectOpen"
                            class="rounded-full border border-rose-300 bg-rose-50 hover:bg-rose-100 px-4 py-2 text-xs font-bold text-rose-800 transition"
                        >
                            <i class="fa-solid fa-xmark mr-1"></i> Reject
                        </button>
                    </div>
                </div>

                <!-- Inline Rejection Reason Form -->
                <div x-show="rejectOpen" x-cloak class="pt-3 border-t border-amber-300">
                    <form method="POST" action="{{ route('admin.vendors.reject', $vendor->id) }}" class="flex flex-col sm:flex-row gap-2">
                        @csrf
                        <input
                            type="text"
                            name="rejection_reason"
                            required
                            placeholder="Enter rejection reason (e.g. Expired DNCC trade license or mismatched storefront address)..."
                            class="flex-1 rounded-xl border border-rose-300 bg-white px-3.5 py-2 text-xs outline-none focus:border-rose-600"
                        >
                        <button type="submit" class="rounded-xl bg-rose-700 hover:bg-rose-800 px-5 py-2 text-xs font-bold text-white transition shrink-0">
                            Confirm Rejection
                        </button>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/40 py-8 text-center text-xs text-[var(--muted-foreground)]">
                <i class="fa-solid fa-circle-check text-2xl text-emerald-700 mb-2 block"></i>
                No pending vendor applications awaiting verification right now.
            </div>
        @endforelse
    </div>

    <!-- Rejected Applications History (if any) -->
    @if($rejectedVendors->count() > 0)
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-3">
            <h2 class="text-sm font-black uppercase tracking-wider text-rose-800">Recently Rejected Applications ({{ $rejectedVendors->count() }})</h2>
            <div class="divide-y divide-[var(--border)] text-xs">
                @foreach($rejectedVendors as $rej)
                    <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div>
                            <span class="font-bold text-[var(--foreground)]">{{ $rej->shop_name }}</span>
                            <span class="text-[var(--muted-foreground)] ml-2">({{ $rej->area }}, {{ $rej->city }})</span>
                            @if($rej->rejection_reason)
                                <p class="text-[11px] text-rose-700 mt-0.5">Reason: {{ $rej->rejection_reason }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.vendors.approve', $rej->id) }}">
                            @csrf
                            <button type="submit" class="rounded-full border border-emerald-600 bg-emerald-50 px-3.5 py-1.5 text-[11px] font-bold text-emerald-800 hover:bg-emerald-100 transition">
                                Override &amp; Approve
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Verified Partner Directory + Featured Curation -->
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-black text-[var(--foreground)]">Verified Partner Brands Directory</h2>
                <p class="text-xs text-[var(--muted-foreground)]">Search approved brands, inspect multi-branch storefronts, and curate Homepage Featured Shops.</p>
            </div>
            <form method="GET" action="/admin/vendors/pending" class="flex items-center gap-2">
                <input
                    type="text"
                    name="q"
                    value="{{ $search }}"
                    placeholder="Search brand, city, license..."
                    class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2 text-xs outline-none focus:border-[var(--accent-dark)]"
                >
                <button type="submit" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)]">
                    Search
                </button>
            </form>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-xs">
                <thead class="bg-[var(--mint-100)] text-[var(--muted-foreground)] uppercase font-bold text-[10px]">
                    <tr>
                        <th class="px-4 py-3 text-left">Brand / Shop</th>
                        <th class="px-4 py-3 text-left">City &amp; Area</th>
                        <th class="px-4 py-3 text-center">Branches</th>
                        <th class="px-4 py-3 text-left">Trade License</th>
                        <th class="px-4 py-3 text-center">Featured</th>
                        <th class="px-4 py-3 text-right">Storefront</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @foreach($approvedVendors as $vp)
                        @php
                            $firstShop = $vp->shops->first();
                        @endphp
                        <tr>
                            <td class="px-4 py-3 font-bold text-[var(--foreground)]">
                                {{ $vp->canonical_brand_name }}
                                @if($vp->is_branched)
                                    <span class="ml-1 rounded-full bg-[var(--mint-200)] px-2 py-0.5 text-[10px] font-extrabold text-[var(--accent-dark)]">Multi-Branch</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-[var(--muted-foreground)]">{{ $vp->area }}, {{ $vp->city }}</td>
                            <td class="px-4 py-3 text-center font-bold">{{ $vp->shops->count() }}</td>
                            <td class="px-4 py-3 font-mono text-[11px]">{{ $vp->business_license_no }}</td>
                            <td class="px-4 py-3 text-center">
                                @if($firstShop)
                                    <form method="POST" action="{{ route('admin.vendors.toggle-featured', $firstShop->id) }}" class="inline">
                                        @csrf
                                        <button type="submit" class="rounded-full px-3 py-1 text-[10px] font-bold transition {{ $firstShop->is_featured ? 'bg-emerald-700 text-white' : 'bg-[var(--mint-100)] text-[var(--muted-foreground)] border border-[var(--border)] hover:text-[var(--foreground)]' }}">
                                            {{ $firstShop->is_featured ? '★ Featured' : '☆ Feature' }}
                                        </button>
                                    </form>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                @if($firstShop)
                                    <a href="{{ route('shops.show', $firstShop->slug) }}" class="font-bold text-[var(--accent-dark)] hover:underline">
                                        View &rarr;
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="pt-2">
            {{ $approvedVendors->links() }}
        </div>
    </div>

</div>
@endsection

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
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-8" x-data="{ lightboxDoc: null, inspectVendor: null }">

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
                $vendorLogoRaw = (string) ($vendor->logo_url ?: ($primaryShop->logo_url ?? ''));
                $vendorDocRaw = (string) ($vendor->documents_path ?? '');
                $vendorDocIsPdf = str_starts_with($vendorDocRaw, 'data:application/pdf') || str_ends_with(strtolower($vendorDocRaw), '.pdf');
                $isUnderReview = $vendor->verification_status === 'under_review';
                $dossierPayload = [
                    'id' => $vendor->id,
                    'shop_name' => $vendor->shop_name,
                    'brand_name' => $vendor->canonical_brand_name,
                    'status' => $vendor->verification_status,
                    'owner_name' => $vendor->owner_name ?: ($vendor->user->name ?? 'Vendor Applicant'),
                    'email' => $vendor->business_email ?: ($vendor->user->email ?? 'N/A'),
                    'phone' => $vendor->phone ?: ($vendor->user->phone ?? 'N/A'),
                    'license' => $vendor->business_license_no ?: 'Not Provided',
                    'nid' => $vendor->nid_number ?: 'Not Provided',
                    'shop_address' => $vendor->shop_address ?: 'Same as GPS Location',
                    'gps_address' => $vendor->gps_address ?: ($vendor->shop_address ?: 'N/A'),
                    'city' => $vendor->city ?: 'Dhaka',
                    'area' => $vendor->area ?: 'Main',
                    'lat' => $vendor->shop_latitude,
                    'lng' => $vendor->shop_longitude,
                    'is_branched' => (bool) $vendor->is_branched,
                    'branch_name' => $primaryShop?->branch_name,
                    'specialties' => array_values((array) ($vendor->specialties ?: $vendor->categories ?: [])),
                    'courier_pickup' => (bool) $vendor->courier_pickup,
                    'in_shop_repair' => (bool) $vendor->in_shop_repair,
                    'spare_parts' => (bool) $vendor->spare_parts,
                    'notes' => $vendor->notes,
                    'rejection_reason' => $vendor->rejection_reason,
                    'has_logo' => $vendorLogoRaw !== '',
                    'has_doc' => $vendorDocRaw !== '',
                    'doc_is_pdf' => $vendorDocIsPdf,
                    'submitted_at' => optional($vendor->updated_at)->format('M d, Y h:i A'),
                ];
            @endphp
            <div class="rounded-2xl border-2 {{ $isUnderReview ? 'border-sky-300 bg-sky-50/40' : 'border-amber-300 bg-[var(--mint-100)]/50' }} p-5 space-y-4" x-data="{ rejectOpen: false }">
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4">
                    <div class="space-y-2 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($isUnderReview)
                                <span class="rounded-full bg-sky-600 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-white">Under Review</span>
                            @else
                                <span class="rounded-full bg-amber-500 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-white">Pending Review</span>
                            @endif
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
                                <span class="font-bold text-[var(--foreground)] block mt-0.5">{{ $vendor->owner_name ?: ($vendor->user->name ?? 'Vendor Applicant') }}</span>
                                <span class="text-[11px] text-[var(--muted-foreground)]">{{ $vendor->business_email ?: ($vendor->user->email ?? 'N/A') }} • {{ $vendor->phone ?: ($vendor->user->phone ?? 'N/A') }}</span>
                            </div>

                            <div class="rounded-xl bg-[var(--card)] p-3 border border-[var(--border)]">
                                <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Trade License &amp; NID</span>
                                <span class="font-mono font-bold text-[var(--foreground)] block mt-0.5">{{ $vendor->business_license_no ?: 'Pending Trade License' }}</span>
                                <span class="font-mono text-[11px] text-[var(--muted-foreground)]">NID: {{ $vendor->nid_number ?: 'Pending NID' }}</span>
                            </div>

                            <div class="rounded-xl bg-[var(--card)] p-3 border border-[var(--border)]">
                                <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Dual Address Audit</span>
                                <span class="text-[11px] font-bold text-[var(--foreground)] block mt-0.5">Public: {{ $vendor->shop_address ?: $vendor->gps_address }}</span>
                                <span class="text-[11px] text-[var(--muted-foreground)] block">GPS Pin: {{ $vendor->gps_address ?? $vendor->shop_address }} ({{ $vendor->area }}, {{ $vendor->city }})</span>
                            </div>
                        </div>

                        <!-- Inline Document & Logo Thumbnails (Click-to-Zoom Lightbox) -->
                        @if($vendorLogoRaw || $vendorDocRaw)
                            <div class="flex flex-wrap items-center gap-3 pt-1">
                                @if($vendorLogoRaw)
                                    <button
                                        type="button"
                                        @click="lightboxDoc = { url: '/admin/vendors/{{ $vendor->id }}/document/logo', title: @js($vendor->shop_name . ' — Shop Logo'), isPdf: false }"
                                        class="group flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--card)] p-1.5 pr-3 text-xs font-bold text-[var(--accent-dark)] shadow-sm hover:border-[var(--accent-dark)] transition"
                                        title="Click to zoom Shop Logo"
                                    >
                                        <img src="/admin/vendors/{{ $vendor->id }}/document/logo" alt="Logo" class="h-10 w-10 rounded-lg object-cover border border-[var(--border)]">
                                        <div class="text-left">
                                            <span class="block text-[11px] font-bold text-[var(--foreground)]">Shop Logo</span>
                                            <span class="text-[10px] text-[var(--accent-dark)]"><i class="fa-solid fa-magnifying-glass-plus mr-0.5"></i>Click to Zoom</span>
                                        </div>
                                    </button>
                                @endif

                                @if($vendorDocRaw)
                                    <button
                                        type="button"
                                        @click="lightboxDoc = { url: '/admin/vendors/{{ $vendor->id }}/document/document', title: @js($vendor->shop_name . ' — Trade License / NID Scan'), isPdf: {{ $vendorDocIsPdf ? 'true' : 'false' }} }"
                                        class="group flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--card)] p-1.5 pr-3 text-xs font-bold text-[var(--accent-dark)] shadow-sm hover:border-[var(--accent-dark)] transition"
                                        title="Click to zoom Trade License / NID Scan"
                                    >
                                        @if(!$vendorDocIsPdf)
                                            <img src="/admin/vendors/{{ $vendor->id }}/document/document" alt="License Scan" class="h-10 w-10 rounded-lg object-cover border border-[var(--border)]">
                                        @else
                                            <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-rose-100 text-rose-700"><i class="fa-solid fa-file-pdf text-lg"></i></span>
                                        @endif
                                        <div class="text-left">
                                            <span class="block text-[11px] font-bold text-[var(--foreground)]">Trade License / NID Scan</span>
                                            <span class="text-[10px] text-[var(--accent-dark)]"><i class="fa-solid fa-magnifying-glass-plus mr-0.5"></i>Click to Inspect</span>
                                        </div>
                                    </button>
                                @endif
                            </div>
                        @endif

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
                        <button
                            type="button"
                            @click="inspectVendor = @js($dossierPayload)"
                            class="rounded-full border border-[var(--border)] bg-[var(--card)] px-3.5 py-2 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition"
                        >
                            <i class="fa-solid fa-magnifying-glass mr-1 text-[var(--accent-dark)]"></i> Inspect Dossier
                        </button>

                        @if(!$isUnderReview)
                            <form method="POST" action="{{ route('admin.vendors.status', $vendor->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="under_review">
                                <button type="submit" class="rounded-full border border-sky-300 bg-sky-50 hover:bg-sky-100 px-3.5 py-2 text-xs font-bold text-sky-800 transition">
                                    <i class="fa-solid fa-eye mr-1"></i> Mark Under Review
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.vendors.status', $vendor->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="pending">
                                <button type="submit" class="rounded-full border border-amber-300 bg-amber-50 hover:bg-amber-100 px-3.5 py-2 text-xs font-bold text-amber-800 transition">
                                    Move to Pending
                                </button>
                            </form>
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

                        <form method="POST"
                              action="{{ route('admin.vendors.destroy', $vendor->id) }}"
                              data-confirm-delete
                              data-confirm-title="Delete Vendor Application?"
                              data-confirm-item="{{ $vendor->shop?->name ?? $vendor->name }} ({{ $vendor->personal_email ?? $vendor->email }})"
                              data-confirm-message="This will permanently delete the vendor application and its draft storefront. This action cannot be undone."
                              data-confirm-cta="Delete Application">
                            @csrf
                            @method('DELETE')
                            <button type="submit" title="Delete Application" class="rounded-full border border-[var(--border)] bg-[var(--card)] hover:bg-rose-50 hover:text-rose-700 px-3 py-2 text-xs font-bold text-[var(--muted-foreground)] transition">
                                <i class="fa-solid fa-trash-can"></i>
                            </button>
                        </form>
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
                    @php
                        $rejPrimaryShop = $rej->shops->first();
                        $rejLogoRaw = (string) ($rej->logo_url ?: ($rejPrimaryShop->logo_url ?? ''));
                        $rejDocRaw = (string) ($rej->documents_path ?? '');
                        $rejDocIsPdf = str_starts_with($rejDocRaw, 'data:application/pdf') || str_ends_with(strtolower($rejDocRaw), '.pdf');
                        $rejDossier = [
                            'id' => $rej->id,
                            'shop_name' => $rej->shop_name,
                            'brand_name' => $rej->canonical_brand_name,
                            'status' => $rej->verification_status,
                            'owner_name' => $rej->owner_name ?: ($rej->user->name ?? 'Vendor Applicant'),
                            'email' => $rej->business_email ?: ($rej->user->email ?? 'N/A'),
                            'phone' => $rej->phone ?: ($rej->user->phone ?? 'N/A'),
                            'license' => $rej->business_license_no ?: 'Not Provided',
                            'nid' => $rej->nid_number ?: 'Not Provided',
                            'shop_address' => $rej->shop_address ?: 'Same as GPS Location',
                            'gps_address' => $rej->gps_address ?: ($rej->shop_address ?: 'N/A'),
                            'city' => $rej->city ?: 'Dhaka',
                            'area' => $rej->area ?: 'Main',
                            'lat' => $rej->shop_latitude,
                            'lng' => $rej->shop_longitude,
                            'is_branched' => (bool) $rej->is_branched,
                            'branch_name' => $rejPrimaryShop?->branch_name,
                            'specialties' => array_values((array) ($rej->specialties ?: $rej->categories ?: [])),
                            'courier_pickup' => (bool) $rej->courier_pickup,
                            'in_shop_repair' => (bool) $rej->in_shop_repair,
                            'spare_parts' => (bool) $rej->spare_parts,
                            'notes' => $rej->notes,
                            'rejection_reason' => $rej->rejection_reason,
                            'has_logo' => $rejLogoRaw !== '',
                            'has_doc' => $rejDocRaw !== '',
                            'doc_is_pdf' => $rejDocIsPdf,
                            'submitted_at' => optional($rej->updated_at)->format('M d, Y h:i A'),
                        ];
                    @endphp
                    <div class="py-3.5 flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                        <div>
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="rounded-full bg-rose-100 px-2.5 py-0.5 text-[10px] font-extrabold uppercase text-rose-800">Rejected</span>
                                <span class="font-bold text-sm text-[var(--foreground)]">{{ $rej->shop_name }}</span>
                                <span class="text-[var(--muted-foreground)]">({{ $rej->area }}, {{ $rej->city }})</span>
                                <span class="text-[11px] text-[var(--muted-foreground)]">• {{ $rej->owner_name ?: ($rej->user->name ?? 'Applicant') }} ({{ $rej->business_email ?: ($rej->user->email ?? 'N/A') }})</span>
                            </div>
                            @if($rej->rejection_reason)
                                <p class="text-[11px] font-semibold text-rose-700 mt-1">Reason: {{ $rej->rejection_reason }}</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap items-center gap-2 shrink-0">
                            <button
                                type="button"
                                @click="inspectVendor = @js($rejDossier)"
                                class="rounded-full border border-[var(--border)] bg-[var(--card)] px-3 py-1.5 text-[11px] font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition"
                            >
                                <i class="fa-solid fa-magnifying-glass mr-1"></i> Dossier
                            </button>
                            <form method="POST" action="{{ route('admin.vendors.status', $rej->id) }}">
                                @csrf
                                <input type="hidden" name="status" value="pending">
                                <button type="submit" class="rounded-full border border-amber-500 bg-amber-50 px-3 py-1.5 text-[11px] font-bold text-amber-800 hover:bg-amber-100 transition">
                                    Move to Pending
                                </button>
                            </form>
                            <form method="POST" action="{{ route('admin.vendors.approve', $rej->id) }}">
                                @csrf
                                <button type="submit" class="rounded-full border border-emerald-600 bg-emerald-50 px-3.5 py-1.5 text-[11px] font-bold text-emerald-800 hover:bg-emerald-100 transition">
                                    Override &amp; Approve
                                </button>
                            </form>
                            <form method="POST"
                                  action="{{ route('admin.vendors.destroy', $rej->id) }}"
                                  data-confirm-delete
                                  data-confirm-title="Delete Rejected Application?"
                                  data-confirm-item="{{ $rej->shop?->name ?? $rej->name }} ({{ $rej->personal_email ?? $rej->email }})"
                                  data-confirm-message="This permanently removes the rejected vendor application and draft shop from the archive."
                                  data-confirm-cta="Delete Permanently">
                                @csrf
                                @method('DELETE')
                                <button type="submit" title="Delete Application" class="rounded-full border border-rose-200 bg-rose-50 hover:bg-rose-100 px-2.5 py-1.5 text-[11px] font-bold text-rose-700 transition">
                                    <i class="fa-solid fa-trash-can"></i>
                                </button>
                            </form>
                        </div>
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
                <p class="text-xs text-[var(--muted-foreground)]">Search approved brands, inspect multi-branch storefronts, curate Homepage Featured Shops, or revoke approval if needed.</p>
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
                        <th class="px-4 py-3 text-right">Governance &amp; Storefront</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @foreach($approvedVendors as $vp)
                        @php
                            $firstShop = $vp->shops->first();
                            $vpLogoRaw = (string) ($vp->logo_url ?: ($firstShop->logo_url ?? ''));
                            $vpDocRaw = (string) ($vp->documents_path ?? '');
                            $vpDocIsPdf = str_starts_with($vpDocRaw, 'data:application/pdf') || str_ends_with(strtolower($vpDocRaw), '.pdf');
                            $vpDossier = [
                                'id' => $vp->id,
                                'shop_name' => $vp->shop_name,
                                'brand_name' => $vp->canonical_brand_name,
                                'status' => $vp->verification_status,
                                'owner_name' => $vp->owner_name ?: ($vp->user->name ?? 'Verified Partner'),
                                'email' => $vp->business_email ?: ($vp->user->email ?? 'N/A'),
                                'phone' => $vp->phone ?: ($vp->user->phone ?? 'N/A'),
                                'license' => $vp->business_license_no ?: 'Verified',
                                'nid' => $vp->nid_number ?: 'Verified',
                                'shop_address' => $vp->shop_address ?: ($firstShop->address ?? 'Same as GPS'),
                                'gps_address' => $vp->gps_address ?: ($firstShop->gps_address ?? 'N/A'),
                                'city' => $vp->city ?: 'Dhaka',
                                'area' => $vp->area ?: 'Main',
                                'lat' => $vp->shop_latitude ?: $firstShop?->latitude,
                                'lng' => $vp->shop_longitude ?: $firstShop?->longitude,
                                'is_branched' => (bool) $vp->is_branched,
                                'branch_name' => $firstShop?->branch_name,
                                'specialties' => array_values((array) ($vp->specialties ?: $vp->categories ?: [])),
                                'courier_pickup' => (bool) $vp->courier_pickup,
                                'in_shop_repair' => (bool) $vp->in_shop_repair,
                                'spare_parts' => (bool) $vp->spare_parts,
                                'notes' => $vp->notes,
                                'rejection_reason' => null,
                                'has_logo' => $vpLogoRaw !== '',
                                'has_doc' => $vpDocRaw !== '',
                                'doc_is_pdf' => $vpDocIsPdf,
                                'submitted_at' => optional($vp->reviewed_at ?: $vp->updated_at)->format('M d, Y h:i A'),
                            ];
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
                                <div class="inline-flex items-center justify-end gap-2">
                                    <button
                                        type="button"
                                        @click="inspectVendor = @js($vpDossier)"
                                        class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-2.5 py-1 text-[10px] font-bold text-[var(--foreground)] hover:border-[var(--accent-dark)] transition"
                                    >
                                        Dossier &amp; Status
                                    </button>
                                    @if($firstShop)
                                        <a href="{{ route('shops.show', $firstShop->slug) }}" class="font-bold text-[var(--accent-dark)] hover:underline">
                                            View &rarr;
                                        </a>
                                    @endif
                                </div>
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

    <!-- ── Full Vendor Application Dossier & Status Control Modal ── -->
    <div
        x-show="inspectVendor"
        x-cloak
        @keydown.escape.window="inspectVendor = null"
        class="fixed inset-0 z-[150] flex items-center justify-center p-4"
    >
        <div class="fixed inset-0 bg-black/75 backdrop-blur-sm" @click="inspectVendor = null"></div>
        <div class="relative z-[151] w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-2xl space-y-5">
            <div class="flex items-start justify-between gap-3 border-b border-[var(--border)] pb-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[10px] font-black uppercase tracking-wider text-[var(--accent-dark)]">Vendor Application Dossier</span>
                        <span
                            class="rounded-full px-2.5 py-0.5 text-[10px] font-extrabold uppercase"
                            :class="{
                                'bg-emerald-100 text-emerald-900': inspectVendor?.status === 'approved',
                                'bg-sky-100 text-sky-900': inspectVendor?.status === 'under_review',
                                'bg-amber-100 text-amber-900': inspectVendor?.status === 'pending',
                                'bg-rose-100 text-rose-900': inspectVendor?.status === 'rejected'
                            }"
                            x-text="inspectVendor?.status?.replace('_', ' ')"
                        ></span>
                    </div>
                    <h3 class="text-xl font-black text-[var(--foreground)] mt-1" x-text="inspectVendor?.shop_name"></h3>
                    <p class="text-xs text-[var(--muted-foreground)]">
                        Application #<span x-text="inspectVendor?.id"></span> • Last updated <span x-text="inspectVendor?.submitted_at"></span>
                    </p>
                </div>
                <button type="button" @click="inspectVendor = null" class="h-9 w-9 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] font-bold">✕</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/40 p-3.5 space-y-1">
                    <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Owner &amp; Contact</span>
                    <p class="font-bold text-sm text-[var(--foreground)]" x-text="inspectVendor?.owner_name"></p>
                    <p class="text-[var(--muted-foreground)]">Email: <span class="font-semibold text-[var(--foreground)]" x-text="inspectVendor?.email"></span></p>
                    <p class="text-[var(--muted-foreground)]">Phone: <span class="font-semibold text-[var(--foreground)]" x-text="inspectVendor?.phone"></span></p>
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/40 p-3.5 space-y-1">
                    <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Trade License &amp; Identity</span>
                    <p class="text-[var(--muted-foreground)]">Trade License: <span class="font-mono font-bold text-[var(--foreground)]" x-text="inspectVendor?.license"></span></p>
                    <p class="text-[var(--muted-foreground)]">Owner NID: <span class="font-mono font-bold text-[var(--foreground)]" x-text="inspectVendor?.nid"></span></p>
                    <p class="text-[var(--muted-foreground)]">Branch Mode: <span class="font-bold text-[var(--foreground)]" x-text="inspectVendor?.is_branched ? ('Multi-Branch (' + (inspectVendor?.branch_name || 'Main') + ')') : 'Single Storefront'"></span></p>
                </div>

                <div class="sm:col-span-2 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/40 p-3.5 space-y-1">
                    <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Dual Location Verification (GPS vs Public Address)</span>
                    <p class="text-[var(--muted-foreground)]">Public Shop Address: <span class="font-bold text-[var(--foreground)]" x-text="inspectVendor?.shop_address"></span></p>
                    <p class="text-[var(--muted-foreground)]">Pinned GPS Address: <span class="font-semibold text-[var(--foreground)]" x-text="inspectVendor?.gps_address"></span> (<span x-text="inspectVendor?.area"></span>, <span x-text="inspectVendor?.city"></span>)</p>
                    <p class="font-mono text-[11px] text-[var(--muted-foreground)]" x-show="inspectVendor?.lat">Coordinates: <span x-text="inspectVendor?.lat"></span>, <span x-text="inspectVendor?.lng"></span></p>
                </div>
            </div>

            <!-- Uploaded Documents inside Dossier -->
            <template x-if="inspectVendor && (inspectVendor.has_logo || inspectVendor.has_doc)">
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/30 p-3.5 space-y-2">
                    <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Uploaded Verification Files</span>
                    <div class="flex flex-wrap gap-3">
                        <template x-if="inspectVendor.has_logo">
                            <button
                                type="button"
                                @click="lightboxDoc = { url: '/admin/vendors/' + inspectVendor.id + '/document/logo', title: inspectVendor.shop_name + ' — Shop Logo', isPdf: false }"
                                class="flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--card)] p-2 pr-3 text-xs font-bold text-[var(--foreground)] hover:border-[var(--accent-dark)]"
                            >
                                <img :src="'/admin/vendors/' + inspectVendor.id + '/document/logo'" class="h-10 w-10 rounded-lg object-cover border border-[var(--border)]">
                                <span>Zoom Shop Logo</span>
                            </button>
                        </template>
                        <template x-if="inspectVendor.has_doc">
                            <button
                                type="button"
                                @click="lightboxDoc = { url: '/admin/vendors/' + inspectVendor.id + '/document/document', title: inspectVendor.shop_name + ' — Trade License / NID Scan', isPdf: inspectVendor.doc_is_pdf }"
                                class="flex items-center gap-2 rounded-xl border border-[var(--border)] bg-[var(--card)] p-2 pr-3 text-xs font-bold text-[var(--foreground)] hover:border-[var(--accent-dark)]"
                            >
                                <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-[var(--mint-200)] text-[var(--accent-dark)]">
                                    <i class="fa-solid fa-file-shield"></i>
                                </span>
                                <span>Inspect Trade License / NID Scan</span>
                            </button>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Admin Status Control Form (Transition to any status) -->
            <form method="POST" :action="'/admin/vendors/' + inspectVendor?.id + '/status'" class="rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-100)]/50 p-4 space-y-3" x-data="{ targetStatus: 'approved' }" x-effect="if (inspectVendor) targetStatus = inspectVendor.status">
                @csrf
                <div class="flex items-center justify-between">
                    <span class="text-xs font-black uppercase tracking-wider text-[var(--foreground)]">
                        <i class="fa-solid fa-sliders mr-1 text-[var(--accent-dark)]"></i> Admin Application Status Control
                    </span>
                    <span class="text-[11px] text-[var(--muted-foreground)]">Audited in Governance Logs</span>
                </div>

                <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs">
                    <label class="cursor-pointer rounded-xl border p-2.5 text-center font-bold transition" :class="targetStatus === 'pending' ? 'border-amber-600 bg-amber-100 text-amber-950' : 'border-[var(--border)] bg-[var(--card)] text-[var(--foreground)]'">
                        <input type="radio" name="status" value="pending" x-model="targetStatus" class="sr-only">
                        Pending
                    </label>
                    <label class="cursor-pointer rounded-xl border p-2.5 text-center font-bold transition" :class="targetStatus === 'under_review' ? 'border-sky-600 bg-sky-100 text-sky-950' : 'border-[var(--border)] bg-[var(--card)] text-[var(--foreground)]'">
                        <input type="radio" name="status" value="under_review" x-model="targetStatus" class="sr-only">
                        Under Review
                    </label>
                    <label class="cursor-pointer rounded-xl border p-2.5 text-center font-bold transition" :class="targetStatus === 'approved' ? 'border-emerald-700 bg-emerald-100 text-emerald-950' : 'border-[var(--border)] bg-[var(--card)] text-[var(--foreground)]'">
                        <input type="radio" name="status" value="approved" x-model="targetStatus" class="sr-only">
                        Approved
                    </label>
                    <label class="cursor-pointer rounded-xl border p-2.5 text-center font-bold transition" :class="targetStatus === 'rejected' ? 'border-rose-700 bg-rose-100 text-rose-950' : 'border-[var(--border)] bg-[var(--card)] text-[var(--foreground)]'">
                        <input type="radio" name="status" value="rejected" x-model="targetStatus" class="sr-only">
                        Rejected / Revoke
                    </label>
                </div>

                <div x-show="targetStatus === 'rejected'" x-cloak>
                    <label class="block text-[11px] font-bold text-rose-800 mb-1">Mandatory Rejection / Revocation Reason</label>
                    <input
                        type="text"
                        name="rejection_reason"
                        :required="targetStatus === 'rejected'"
                        :value="inspectVendor?.rejection_reason || ''"
                        placeholder="Provide clear remediation reason for the vendor..."
                        class="w-full rounded-xl border border-rose-300 bg-white px-3.5 py-2 text-xs text-slate-900 outline-none focus:border-rose-600"
                    >
                </div>

                <div class="flex items-center justify-end gap-2 pt-1">
                    <button type="button" @click="inspectVendor = null" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--foreground)]">
                        Cancel
                    </button>
                    <button type="submit" class="rounded-full bg-[var(--accent-dark)] px-5 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-95">
                        Update Application Status
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Click-to-Zoom Document Lightbox Modal ── -->
    <div
        x-show="lightboxDoc"
        x-cloak
        @keydown.escape.window="lightboxDoc = null"
        class="fixed inset-0 z-[160] flex items-center justify-center p-4"
    >
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="lightboxDoc = null"></div>
        <div class="relative z-[161] w-full max-w-4xl rounded-[2rem] border border-[#c8dac2] bg-white p-4 md:p-6 shadow-2xl space-y-3">
            <div class="flex items-center justify-between gap-3 border-b border-[#e7efe2] pb-3">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-[#214c34]">Vendor Verification Lightbox</span>
                    <h3 class="text-base font-black text-[#163625]" x-text="lightboxDoc?.title"></h3>
                </div>
                <div class="flex items-center gap-2">
                    <a :href="lightboxDoc?.url" target="_blank" class="rounded-full border border-[#c8dac2] bg-[#eef5ea] px-3.5 py-1.5 text-xs font-bold text-[#163625] hover:bg-[#dcebd5]">
                        Open Original &nearr;
                    </a>
                    <button type="button" @click="lightboxDoc = null" class="h-9 w-9 rounded-full bg-[#163625] text-white font-bold">✕</button>
                </div>
            </div>
            <div class="flex max-h-[75vh] min-h-[320px] w-full items-center justify-center overflow-auto rounded-2xl bg-[#0f1c12] p-3">
                <template x-if="lightboxDoc && !lightboxDoc.isPdf">
                    <img :src="lightboxDoc.url" :alt="lightboxDoc.title" class="max-h-[70vh] max-w-full rounded-xl object-contain shadow-lg">
                </template>
                <template x-if="lightboxDoc && lightboxDoc.isPdf">
                    <iframe :src="lightboxDoc.url" class="h-[70vh] w-full rounded-xl bg-white"></iframe>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection

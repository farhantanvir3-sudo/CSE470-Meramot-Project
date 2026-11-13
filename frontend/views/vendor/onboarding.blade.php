{{--
=============================================================================
FEATURE R11: Vendor Onboarding Hub, OTP Verification & Dual Shop Locations
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
Architectural Layer: View (Vendor/Onboarding)
Controller: VendorOnboardingController@show, store, adminIndex, approve, reject
Models Touched: User, VendorProfile, RepairShop
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Vendor Onboarding & Verification | Meramot')

@section('content')
@php
    $authUser = auth()->user();
    $vStatus = strtolower($profile->verification_status ?? 'pending');
    $primaryShop = optional($profile?->shops)->sortBy('id')->first();
    $setupComplete = (bool) ($primaryShop->setup_complete ?? false);
    $isPublicShop = (bool) ($primaryShop->is_public ?? false);
    $emailVerified = (bool) ($authUser->is_email_verified ?? false);
    $phoneVerified = (bool) ($authUser->is_phone_verified ?? false);
    $displayPhone = $authUser->phone ?? $profile->phone ?? '';
@endphp

<div
    class="mx-auto max-w-3xl px-4 py-6 md:px-6 space-y-6"
    x-data="{
        gpsAddress: @js(old('gps_address', $profile->gps_address ?? '')),
        shopAddress: @js(old('shop_address', $profile->shop_address ?? '')),
        sameAsGps: {{ old('same_as_gps_location', $profile->same_as_gps_location ?? false) ? 'true' : 'false' }},
        shopLat: @js(old('shop_latitude', $profile->shop_latitude ?? '')),
        shopLng: @js(old('shop_longitude', $profile->shop_longitude ?? '')),
        city: @js(old('city', $profile->city ?? 'Dhaka')),
        area: @js(old('area', $profile->area ?? 'Banani')),
        isBranched: {{ old('is_branched', $profile->is_branched ?? false) ? 'true' : 'false' }},
        branchName: @js(old('branch_name', $primaryShop->branch_name ?? '')),
        otpModalOpen: false,
        otpChannel: 'email',
        otpCode: '',
        otpSending: false,
        otpVerifying: false,
        otpStepSent: false,
        otpFeedback: '',
        otpError: '',
        applyMapLocation(loc) {
            if (!loc) return;
            this.gpsAddress = loc.address || '';
            this.shopLat = typeof loc.lat === 'number' ? loc.lat.toFixed(6) : '';
            this.shopLng = typeof loc.lng === 'number' ? loc.lng.toFixed(6) : '';
            if (loc.city) this.city = loc.city;
            if (loc.area) this.area = loc.area;
            if (this.sameAsGps) {
                this.shopAddress = this.gpsAddress;
            }
        },
        syncSameAsGps() {
            if (this.sameAsGps && this.gpsAddress) {
                this.shopAddress = this.gpsAddress;
            }
        },
        openOtpModal(channel) {
            this.otpChannel = channel;
            this.otpCode = '';
            this.otpStepSent = false;
            this.otpFeedback = '';
            this.otpError = '';
            this.otpModalOpen = true;
        },
        async sendOtpNow() {
            this.otpSending = true;
            this.otpError = '';
            this.otpFeedback = '';
            try {
                const resp = await fetch('/api/verification/send-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ channel: this.otpChannel })
                });
                const data = await resp.json();
                if (!resp.ok) throw new Error(data.message || 'Could not send verification code.');
                this.otpFeedback = data.message || 'Verification code sent.';
                this.otpStepSent = true;
            } catch (err) {
                this.otpError = err.message || 'Failed to send verification code.';
            } finally {
                this.otpSending = false;
            }
        },
        async verifyOtpNow() {
            if (!this.otpCode || this.otpCode.trim().length < 4) {
                this.otpError = 'Please enter the 6-digit verification code.';
                return;
            }
            this.otpVerifying = true;
            this.otpError = '';
            try {
                const resp = await fetch('/api/verification/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ channel: this.otpChannel, otp: this.otpCode.trim() })
                });
                const data = await resp.json();
                if (!resp.ok) throw new Error(data.message || 'Invalid or expired verification code.');
                window.location.reload();
            } catch (err) {
                this.otpError = err.message || 'Verification failed.';
            } finally {
                this.otpVerifying = false;
            }
        }
    }"
    @meramot-location-changed.window="applyMapLocation($event.detail)"
>
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900 shadow-sm">
            <i class="fa-solid fa-circle-check mr-1.5"></i>{{ session('success') }}
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-900 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation mr-1.5"></i>{{ session('warning') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-800 space-y-1 shadow-sm">
            @foreach($errors->all() as $error)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- ===================================================================== --}}
    {{-- AUTHENTICATED VENDOR ONBOARDING HUB (Status + OTP + Next Steps)       --}}
    {{-- ===================================================================== --}}
    @if($profile)
        <div class="space-y-2">
            <h1 class="text-2xl font-black text-[var(--foreground)]">Vendor onboarding</h1>
            <p class="text-xs text-[var(--muted-foreground)]">
                We verify every shop before listing it publicly on Meramot.
            </p>
        </div>

        {{-- Card 1: Application & Storefront Status --}}
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <div class="flex items-center justify-between border-b border-[var(--border)] pb-3">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--accent-dark)]">Status</span>
                    <h2 class="text-base font-black text-[var(--foreground)]">{{ $profile->shop_name }}</h2>
                </div>
                @if($vStatus === 'approved')
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-700 px-3 py-1 text-[10px] font-extrabold uppercase text-white">
                        <i class="fa-solid fa-circle-check"></i> APPROVED
                    </span>
                @elseif($vStatus === 'rejected')
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-700 px-3 py-1 text-[10px] font-extrabold uppercase text-white">
                        <i class="fa-solid fa-triangle-exclamation"></i> REJECTED
                    </span>
                @elseif($vStatus === 'under_review')
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-600 px-3 py-1 text-[10px] font-extrabold uppercase text-white">
                        <i class="fa-solid fa-magnifying-glass"></i> UNDER REVIEW
                    </span>
                @else
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-600 px-3 py-1 text-[10px] font-extrabold uppercase text-white">
                        <i class="fa-solid fa-clock"></i> PENDING
                    </span>
                @endif
            </div>

            <div class="space-y-2.5 text-xs">
                <div class="flex items-center justify-between">
                    <span class="text-[var(--muted-foreground)]">Application status</span>
                    <span class="font-bold uppercase text-[var(--foreground)]">{{ strtoupper(str_replace('_', ' ', $vStatus)) }}</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[var(--muted-foreground)]">Shop setup</span>
                    <span class="font-bold {{ $setupComplete ? 'text-emerald-700' : 'text-amber-700' }}">
                        {{ $setupComplete ? 'Completed' : 'Not completed' }}
                    </span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-[var(--muted-foreground)]">Shop visibility</span>
                    <span class="font-bold {{ $isPublicShop ? 'text-emerald-700' : 'text-[var(--muted-foreground)]' }}">
                        {{ $isPublicShop ? 'Public' : 'Hidden' }}
                    </span>
                </div>
            </div>

            @if($vStatus === 'rejected')
                <div class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-900 space-y-1">
                    <div class="font-extrabold">Action Required — Application Rejected</div>
                    @if($profile->rejection_reason)
                        <div><strong>Admin Audit Note:</strong> {{ $profile->rejection_reason }}</div>
                    @endif
                    <div class="text-[11px] text-rose-800/90 pt-0.5">
                        Click <strong>Edit application</strong> below to update your trade credentials and resubmit for review.
                    </div>
                </div>
            @elseif(in_array($vStatus, ['pending', 'under_review'], true))
                <div class="rounded-2xl border border-amber-300 bg-amber-50/80 px-4 py-3 text-xs text-amber-950">
                    Your application is {{ $vStatus === 'under_review' ? 'currently being audited by an administrator' : 'under administrative review' }}. Shop visibility stays hidden until an administrator verifies your Trade License
                    @if($profile->business_license_no)
                        (<span class="font-mono font-bold">{{ $profile->business_license_no }}</span>)
                    @endif
                    and business details.
                </div>
            @elseif($vStatus === 'approved' && !$setupComplete)
                <div class="rounded-2xl border border-emerald-300 bg-emerald-50/80 px-4 py-3 text-xs text-emerald-950">
                    <strong>Congratulations!</strong> Your vendor application is approved. Complete your shop pricing and service setup below to list <strong>{{ $profile->shop_name }}</strong> publicly.
                </div>
            @elseif($vStatus === 'approved' && $setupComplete)
                <div class="rounded-2xl border border-emerald-300 bg-emerald-50/80 px-4 py-3 text-xs text-emerald-950">
                    <strong>{{ $profile->shop_name }}</strong> is verified and publicly listed on Meramot. Manage bids, branches, and direct orders from your Vendor Dashboard.
                </div>
            @endif
        </div>

        {{-- Card 2: Account Verification (Email + Phone OTP) --}}
        @if($authUser)
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
                <div>
                    <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--accent-dark)]">Security</span>
                    <h2 class="text-base font-black text-[var(--foreground)]">Account Verification</h2>
                    <p class="text-xs text-[var(--muted-foreground)] mt-0.5">
                        Verify your business email and mobile number to secure payout and order notifications.
                    </p>
                </div>

                <div class="space-y-3 text-xs">
                    {{-- Email Verification Row --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 px-4 py-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--card)] text-[var(--accent-dark)] border border-[var(--border)]">
                                <i class="fa-solid fa-envelope"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="font-bold text-[var(--foreground)]">Email</div>
                                <div class="truncate text-[var(--muted-foreground)]">{{ $authUser->email }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($emailVerified)
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-bold text-emerald-800">
                                    <i class="fa-solid fa-circle-check"></i> Verified
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-[11px] font-bold text-amber-800">
                                    <i class="fa-solid fa-circle-exclamation"></i> Not Verified
                                </span>
                                <button
                                    type="button"
                                    @click="openOtpModal('email')"
                                    class="rounded-full bg-[var(--accent-dark)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition"
                                >
                                    Verify
                                </button>
                            @endif
                        </div>
                    </div>

                    {{-- Phone Verification Row --}}
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 px-4 py-3">
                        <div class="flex items-center gap-3 min-w-0">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--card)] text-[var(--accent-dark)] border border-[var(--border)]">
                                <i class="fa-solid fa-phone"></i>
                            </span>
                            <div class="min-w-0">
                                <div class="font-bold text-[var(--foreground)]">Phone Number</div>
                                <div class="truncate text-[var(--muted-foreground)]">{{ $displayPhone ?: 'No phone number added yet' }}</div>
                            </div>
                        </div>
                        <div class="flex items-center gap-2">
                            @if($phoneVerified)
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-[11px] font-bold text-emerald-800">
                                    <i class="fa-solid fa-circle-check"></i> Verified
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-3 py-1 text-[11px] font-bold text-amber-800">
                                    <i class="fa-solid fa-circle-exclamation"></i> Not Verified
                                </span>
                                @if($displayPhone)
                                    <button
                                        type="button"
                                        @click="openOtpModal('phone')"
                                        class="rounded-full bg-[var(--accent-dark)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition"
                                    >
                                        Verify
                                    </button>
                                @endif
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        @endif

        {{-- Card 3: Next Step Actions --}}
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-base font-black text-[var(--foreground)]">Next step</h2>
                <p class="text-xs text-[var(--muted-foreground)] mt-0.5">
                    @if($vStatus === 'approved' && !$setupComplete)
                        Set your inspection &amp; labor fees, service options, and repair catalog to publish your shop.
                    @elseif($vStatus === 'approved' && $setupComplete)
                        Your workshop is active and publicly listed. Open your Vendor Dashboard to manage requests or update your storefront configuration anytime.
                    @else
                        Track your approval status or update your vendor application details if needed.
                    @endif
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <a href="/vendor/status" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                    View application status
                </a>

                @if($vStatus === 'approved' && !$setupComplete)
                    <a href="/vendor/setup-shop" class="rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                        Complete shop setup &rarr;
                    </a>
                @elseif($vStatus === 'approved' && $setupComplete)
                    <a href="/vendor/dashboard" class="rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                        Open vendor dashboard &rarr;
                    </a>
                    <a href="/vendor/setup-shop" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2.5 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                        Edit shop setup
                    </a>
                    @if($primaryShop)
                        <a href="{{ route('shops.show', $primaryShop->slug) }}" class="rounded-full border border-emerald-700 bg-emerald-50 px-4 py-2.5 text-xs font-bold text-emerald-900 hover:bg-emerald-100 transition">
                            View Shop Profile
                        </a>
                    @endif
                @else
                    <a href="/vendor/apply" class="rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                        Edit application
                    </a>
                    <a href="/" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2.5 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                        Go to home
                    </a>
                @endif
            </div>
        </div>
    @endif

    {{-- ===================================================================== --}}
    {{-- DIRECT DUAL-LOCATION ONBOARDING FORM (Open for Guests, Collapsible)   --}}
    {{-- ===================================================================== --}}
    <details {{ !$profile ? 'open' : '' }} class="group rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm">
        <summary class="cursor-pointer list-none flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <span class="dev-only-badge text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mb-2">
                    Feature R11 • Farhan Tanvir [22299179]
                </span>
                <h2 class="text-xl font-black text-[var(--foreground)]">
                    {{ $profile ? 'Quick Update Trade Credentials & Dual Shop Locations' : 'Vendor Onboarding & Dual-Location Setup' }}
                </h2>
                <p class="text-xs text-[var(--muted-foreground)] mt-1">
                    Register or update your electronic repair brand on Meramot with verified trade credentials, GPS map pin, and public storefront address.
                </p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                @if(!$profile)
                    <a href="/vendor/apply" class="rounded-full bg-[#D4AF37] px-4 py-2 text-xs font-bold text-[#1f2c21] shadow-sm hover:opacity-90 transition">
                        Full Vendor Application &rarr;
                    </a>
                @else
                    <span class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--accent-dark)]">
                        <span class="group-open:hidden">Expand Form &darr;</span>
                        <span class="hidden group-open:inline">Hide Form &uarr;</span>
                    </span>
                @endif
            </div>
        </summary>

        <form method="POST" action="/vendor/onboarding" enctype="multipart/form-data" class="space-y-5 text-xs mt-6 pt-5 border-t border-[var(--border)]">
            @csrf

            @guest
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-4 space-y-3">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-extrabold uppercase tracking-wider text-[var(--accent-dark)]">Owner Account Credentials</span>
                        <a href="/login" class="text-[11px] font-bold text-[var(--accent-dark)] hover:underline">Already have an account? Sign in</a>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Owner Full Name *</label>
                            <input
                                type="text"
                                name="owner_name"
                                value="{{ old('owner_name') }}"
                                placeholder="e.g. Tariqul Islam"
                                class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2.5 text-xs outline-none"
                                required
                            >
                        </div>
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Business Email *</label>
                            <input
                                type="email"
                                name="owner_email"
                                value="{{ old('owner_email') }}"
                                placeholder="owner@yourworkshop.bd"
                                class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2.5 text-xs outline-none"
                                required
                            >
                        </div>
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Contact Mobile *</label>
                            <input
                                type="tel"
                                name="owner_phone"
                                value="{{ old('owner_phone') }}"
                                placeholder="+88017XXXXXXXX"
                                class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2.5 text-xs outline-none"
                                required
                            >
                        </div>
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Account Password *</label>
                            <input
                                type="password"
                                name="owner_password"
                                placeholder="Min. 6 characters"
                                class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2.5 text-xs outline-none"
                                required
                            >
                        </div>
                    </div>
                </div>
            @endguest

            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">Repair Shop / Brand Business Name *</label>
                <input
                    type="text"
                    name="shop_name"
                    value="{{ old('shop_name', $profile->shop_name ?? '') }}"
                    placeholder="e.g. Dhaka Circuit Care"
                    class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] p-3 text-xs outline-none"
                    required
                >
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Trade License Number *</label>
                    <input
                        type="text"
                        name="business_license_no"
                        value="{{ old('business_license_no', $profile->business_license_no ?? '') }}"
                        placeholder="TRAD/DNCC/129482"
                        class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] p-3 text-xs outline-none"
                        required
                    >
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Owner National NID *</label>
                    <input
                        type="text"
                        name="nid_number"
                        value="{{ old('nid_number', $profile->nid_number ?? '') }}"
                        placeholder="199219293120"
                        class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] p-3 text-xs outline-none"
                        required
                    >
                </div>
            </div>

            <!-- ── Interactive Dashed Dropzone Cards (Shop Logo + Trade License / NID Scan) ── -->
            @php
                $existingLogoUrl = $profile->logo_url ?? $primaryShop->logo_url ?? null;
                $existingDocUrl = $profile->documents_path ?? null;
                $existingDocIsPdf = $existingDocUrl && str_starts_with((string) $existingDocUrl, 'data:application/pdf');
            @endphp
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <!-- Dropzone 1: Shop Logo / Storefront Photo -->
                <div
                    x-data="{
                        dragging: false,
                        fileName: '',
                        fileSize: '',
                        fileType: '',
                        previewUrl: null,
                        existingUrl: @js($existingLogoUrl),
                        formatBytes(bytes) {
                            if (!bytes) return '';
                            return bytes < 1048576 ? (bytes / 1024).toFixed(1) + ' KB' : (bytes / 1048576).toFixed(2) + ' MB';
                        },
                        handleFile(file) {
                            if (!file) return;
                            this.fileName = file.name;
                            this.fileSize = this.formatBytes(file.size);
                            this.fileType = (file.name.split('.').pop() || 'IMG').toUpperCase();
                            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                            this.previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
                        },
                        onDrop(e) {
                            this.dragging = false;
                            const files = e.dataTransfer.files;
                            if (files && files.length > 0) {
                                this.$refs.fileInput.files = files;
                                this.handleFile(files[0]);
                            }
                        },
                        clearFile() {
                            this.$refs.fileInput.value = '';
                            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                            this.fileName = '';
                            this.fileSize = '';
                            this.fileType = '';
                            this.previewUrl = null;
                        }
                    }"
                    class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-3.5 flex flex-col justify-between"
                >
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <label class="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                            <i class="fa-solid fa-store text-[var(--accent-dark)]"></i>
                            <span>Shop Logo / Storefront Photo</span>
                        </label>
                        <span class="rounded-full bg-[var(--card)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">JPG / PNG</span>
                    </div>

                    <input x-ref="fileInput" type="file" name="logo" accept="image/*" class="hidden" @change="handleFile($event.target.files[0])">

                    <div
                        x-show="!fileName && !existingUrl"
                        @click="$refs.fileInput.click()"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="onDrop($event)"
                        :class="dragging ? 'border-[var(--accent-dark)] bg-[var(--mint-200)]/60 scale-[1.01]' : 'border-[var(--border)] bg-[var(--card)]/90 hover:border-[var(--accent-dark)] hover:bg-[var(--card)]'"
                        class="cursor-pointer rounded-xl border-2 border-dashed p-4 text-center transition-all duration-150 flex flex-col items-center justify-center min-h-[120px]"
                    >
                        <div class="h-9 w-9 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] flex items-center justify-center mb-1.5 shadow-sm">
                            <i class="fa-solid fa-cloud-arrow-up text-sm"></i>
                        </div>
                        <p class="text-[11px] font-bold text-[var(--foreground)]">Drop shop logo or <span class="underline text-[var(--accent-dark)]">browse</span></p>
                        <p class="text-[10px] text-[var(--muted-foreground)] mt-0.5">Instant thumbnail preview • Max 5 MB</p>
                    </div>

                    <div x-show="!fileName && existingUrl" x-cloak class="rounded-xl border border-[var(--border)] bg-[var(--card)] p-3 space-y-2">
                        <div class="relative h-24 w-full overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/40 flex items-center justify-center">
                            <img :src="existingUrl" alt="Current Shop Logo" class="max-h-full max-w-full object-contain">
                            <span class="absolute top-1.5 left-1.5 rounded-md bg-emerald-800/90 px-2 py-0.5 text-[9px] font-bold uppercase text-white">Saved Logo</span>
                        </div>
                        <button type="button" @click="$refs.fileInput.click()" class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-100)] py-1.5 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                            <i class="fa-solid fa-arrows-rotate mr-1"></i> Replace Logo
                        </button>
                    </div>

                    <div x-show="fileName" x-cloak class="rounded-xl border-2 border-[var(--accent-dark)]/40 bg-[var(--card)] p-3 space-y-2">
                        <div class="relative h-24 w-full overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/40 flex items-center justify-center">
                            <template x-if="previewUrl">
                                <img :src="previewUrl" alt="New Logo Preview" class="max-h-full max-w-full object-contain">
                            </template>
                            <span class="absolute top-1.5 left-1.5 rounded-md bg-[var(--accent-dark)] px-2 py-0.5 text-[9px] font-bold uppercase text-white" x-text="fileType"></span>
                            <button type="button" @click="clearFile()" class="absolute top-1.5 right-1.5 h-6 w-6 rounded-full bg-rose-600 text-white text-xs font-bold shadow hover:bg-rose-700 flex items-center justify-center" title="Remove selected file">✕</button>
                        </div>
                        <div class="flex items-center justify-between gap-2 text-[11px]">
                            <span class="font-bold text-[var(--foreground)] truncate" x-text="fileName"></span>
                            <span class="shrink-0 rounded-md bg-[var(--mint-100)] px-2 py-0.5 font-mono text-[10px] font-semibold text-[var(--accent-dark)]" x-text="fileSize"></span>
                        </div>
                    </div>
                </div>

                <!-- Dropzone 2: Trade License / Owner NID Scan -->
                <div
                    x-data="{
                        dragging: false,
                        fileName: '',
                        fileSize: '',
                        fileType: '',
                        isPdf: false,
                        previewUrl: null,
                        existingUrl: @js($existingDocUrl),
                        existingIsPdf: {{ $existingDocIsPdf ? 'true' : 'false' }},
                        formatBytes(bytes) {
                            if (!bytes) return '';
                            return bytes < 1048576 ? (bytes / 1024).toFixed(1) + ' KB' : (bytes / 1048576).toFixed(2) + ' MB';
                        },
                        handleFile(file) {
                            if (!file) return;
                            this.fileName = file.name;
                            this.fileSize = this.formatBytes(file.size);
                            this.fileType = (file.name.split('.').pop() || 'DOC').toUpperCase();
                            this.isPdf = file.type === 'application/pdf' || this.fileType === 'PDF';
                            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                            this.previewUrl = file.type.startsWith('image/') ? URL.createObjectURL(file) : null;
                        },
                        onDrop(e) {
                            this.dragging = false;
                            const files = e.dataTransfer.files;
                            if (files && files.length > 0) {
                                this.$refs.fileInput.files = files;
                                this.handleFile(files[0]);
                            }
                        },
                        clearFile() {
                            this.$refs.fileInput.value = '';
                            if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                            this.fileName = '';
                            this.fileSize = '';
                            this.fileType = '';
                            this.isPdf = false;
                            this.previewUrl = null;
                        }
                    }"
                    class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-3.5 flex flex-col justify-between"
                >
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <label class="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                            <i class="fa-solid fa-file-shield text-[var(--accent-dark)]"></i>
                            <span>Trade License / NID Scan</span>
                        </label>
                        <span class="rounded-full bg-[var(--card)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">IMG / PDF</span>
                    </div>

                    <input x-ref="fileInput" type="file" name="verification_document" accept="image/*,.pdf" class="hidden" @change="handleFile($event.target.files[0])">

                    <div
                        x-show="!fileName && !existingUrl"
                        @click="$refs.fileInput.click()"
                        @dragover.prevent="dragging = true"
                        @dragleave.prevent="dragging = false"
                        @drop.prevent="onDrop($event)"
                        :class="dragging ? 'border-[var(--accent-dark)] bg-[var(--mint-200)]/60 scale-[1.01]' : 'border-[var(--border)] bg-[var(--card)]/90 hover:border-[var(--accent-dark)] hover:bg-[var(--card)]'"
                        class="cursor-pointer rounded-xl border-2 border-dashed p-4 text-center transition-all duration-150 flex flex-col items-center justify-center min-h-[120px]"
                    >
                        <div class="h-9 w-9 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] flex items-center justify-center mb-1.5 shadow-sm">
                            <i class="fa-solid fa-id-card text-sm"></i>
                        </div>
                        <p class="text-[11px] font-bold text-[var(--foreground)]">Drop license/NID scan or <span class="underline text-[var(--accent-dark)]">browse</span></p>
                        <p class="text-[10px] text-[var(--muted-foreground)] mt-0.5">JPG, PNG or PDF • Max 8 MB</p>
                    </div>

                    <div x-show="!fileName && existingUrl" x-cloak class="rounded-xl border border-[var(--border)] bg-[var(--card)] p-3 space-y-2">
                        <div class="relative h-24 w-full overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/40 flex items-center justify-center">
                            <template x-if="!existingIsPdf">
                                <img :src="existingUrl" alt="Verification Scan" class="max-h-full max-w-full object-contain">
                            </template>
                            <template x-if="existingIsPdf">
                                <div class="flex flex-col items-center text-[var(--accent-dark)]">
                                    <i class="fa-solid fa-file-pdf text-2xl text-rose-600"></i>
                                    <span class="text-[10px] font-bold mt-1">Uploaded PDF Document</span>
                                </div>
                            </template>
                            <span class="absolute top-1.5 left-1.5 rounded-md bg-emerald-800/90 px-2 py-0.5 text-[9px] font-bold uppercase text-white">On File</span>
                        </div>
                        <button type="button" @click="$refs.fileInput.click()" class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-100)] py-1.5 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                            <i class="fa-solid fa-arrows-rotate mr-1"></i> Replace Scan
                        </button>
                    </div>

                    <div x-show="fileName" x-cloak class="rounded-xl border-2 border-[var(--accent-dark)]/40 bg-[var(--card)] p-3 space-y-2">
                        <div class="relative h-24 w-full overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/40 flex items-center justify-center">
                            <template x-if="previewUrl && !isPdf">
                                <img :src="previewUrl" alt="New Scan Preview" class="max-h-full max-w-full object-contain">
                            </template>
                            <template x-if="isPdf">
                                <div class="flex flex-col items-center text-[var(--accent-dark)]">
                                    <i class="fa-solid fa-file-pdf text-2xl text-rose-600"></i>
                                    <span class="text-[10px] font-bold mt-1">PDF Document Ready</span>
                                </div>
                            </template>
                            <span class="absolute top-1.5 left-1.5 rounded-md bg-[var(--accent-dark)] px-2 py-0.5 text-[9px] font-bold uppercase text-white" x-text="fileType"></span>
                            <button type="button" @click="clearFile()" class="absolute top-1.5 right-1.5 h-6 w-6 rounded-full bg-rose-600 text-white text-xs font-bold shadow hover:bg-rose-700 flex items-center justify-center" title="Remove selected file">✕</button>
                        </div>
                        <div class="flex items-center justify-between gap-2 text-[11px]">
                            <span class="font-bold text-[var(--foreground)] truncate" x-text="fileName"></span>
                            <span class="shrink-0 rounded-md bg-[var(--mint-100)] px-2 py-0.5 font-mono text-[10px] font-semibold text-[var(--accent-dark)]" x-text="fileSize"></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Storefront & Coordinate Addressing Card -->
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/70 p-4 md:p-5 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-2 border-b border-[var(--border)] pb-3">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--accent-dark)]">Workshop Addressing</span>
                        <h3 class="text-sm font-black text-[var(--foreground)]">1. Service Map Coordinates &amp; 2. Public Storefront Address</h3>
                    </div>
                    <button
                        type="button"
                        @click="$dispatch('meramot-open-location-modal')"
                        class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] shadow-sm transition hover:opacity-90"
                    >
                        <i class="fa-solid fa-map-location-dot"></i>
                        <span>Pin Location on Map</span>
                    </button>
                </div>

                <!-- Address 1: Map Coordinates -->
                <div class="space-y-1.5">
                    <label class="block font-bold text-[var(--foreground)]">
                        1. Service Map Coordinates <span class="font-normal text-[var(--muted-foreground)]">(Powers distance matching &amp; courier routing — kept private unless synced below)</span>
                    </label>
                    <input
                        type="text"
                        name="gps_address"
                        x-model="gpsAddress"
                        @input="syncSameAsGps()"
                        placeholder="Click 'Pin Location on Map' or enter approximate street and area..."
                        class="w-full rounded-2xl border border-[var(--border)] bg-[var(--card)] p-3 text-xs outline-none"
                    >
                    <input type="hidden" name="shop_latitude" :value="shopLat">
                    <input type="hidden" name="shop_longitude" :value="shopLng">
                    <template x-if="shopLat && shopLng">
                        <p class="text-[11px] font-mono text-[var(--accent-dark)]">
                            <i class="fa-solid fa-satellite-dish mr-1"></i>Pinned Coordinates: <span x-text="shopLat + ', ' + shopLng"></span> (<span x-text="area + ', ' + city"></span>)
                        </p>
                    </template>
                </div>

                <!-- Option: Sync Coordinates to Storefront Address -->
                <label class="flex items-center gap-2.5 cursor-pointer rounded-xl border border-[var(--border)] bg-[var(--card)] px-3.5 py-2.5 font-bold text-[var(--foreground)]">
                    <input
                        type="checkbox"
                        name="same_as_gps_location"
                        value="1"
                        x-model="sameAsGps"
                        @change="syncSameAsGps()"
                        class="h-4 w-4 accent-[#244229]"
                    >
                    <span>Use pinned map coordinates as my public storefront address</span>
                </label>

                <!-- Address 2: Public Storefront Location -->
                <div class="space-y-2">
                    <label class="block font-bold text-[var(--foreground)]">
                        2. Public Storefront Address * <span class="font-normal text-[var(--muted-foreground)]">(Displayed publicly on workshop cards and storefront profile)</span>
                    </label>
                    <input
                        type="text"
                        name="shop_address"
                        x-model="shopAddress"
                        :readonly="sameAsGps"
                        :class="sameAsGps ? 'opacity-75 cursor-not-allowed bg-[var(--card)]' : 'bg-[var(--mint-300)] focus:bg-[var(--mint-100)]'"
                        placeholder="e.g. Level 4, Shop 412, Multiplan Center, Elephant Road, Dhaka"
                        class="w-full rounded-2xl border-2 border-[var(--border)] p-3 text-xs outline-none"
                        required
                    >

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
                        <div>
                            <label class="block text-[11px] font-bold text-[var(--muted-foreground)] mb-1">District / City</label>
                            <input
                                type="text"
                                name="city"
                                x-model="city"
                                placeholder="Dhaka, Chattogram, Sylhet..."
                                class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-xs outline-none"
                            >
                        </div>
                        <div>
                            <label class="block text-[11px] font-bold text-[var(--muted-foreground)] mb-1">Area / Neighborhood</label>
                            <input
                                type="text"
                                name="area"
                                x-model="area"
                                placeholder="Banani, Hatirpool, Agrabad..."
                                class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-xs outline-none"
                            >
                        </div>
                    </div>
                </div>
            </div>

            <!-- ── Optional Multi-Branch Mode at Onboarding ── -->
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-4 space-y-3">
                <label class="flex items-start gap-2.5 cursor-pointer">
                    <input
                        type="checkbox"
                        name="is_branched"
                        value="1"
                        x-model="isBranched"
                        class="mt-0.5 h-4 w-4 accent-[#244229]"
                    >
                    <div>
                        <span class="font-bold text-[var(--foreground)] block">Enable Multi-Branch Tagging (Operate Multiple Shops Under Same Brand)</span>
                        <span class="text-[11px] text-[var(--muted-foreground)]">You can also enable branching or add new city/countrywide branches anytime later from your Vendor Dashboard.</span>
                    </div>
                </label>

                <div x-show="isBranched" x-cloak class="pt-1">
                    <label class="block font-bold text-[var(--foreground)] mb-1">Exact Branch Tag Name for This Shop *</label>
                    <input
                        type="text"
                        name="branch_name"
                        x-model="branchName"
                        placeholder="e.g. Banani DOHS Flagship or Agrabad Branch"
                        class="w-full rounded-2xl border border-[var(--border)] bg-[var(--card)] p-2.5 text-xs outline-none"
                    >
                </div>
            </div>

            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">Service Categories Supported</label>
                @php
                    $selectedCats = old('categories', $profile->categories ?? ['Laptop', 'Phone']);
                    if (!is_array($selectedCats)) {
                        $selectedCats = ['Laptop', 'Phone'];
                    }
                @endphp
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mt-2 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-3.5">
                    <label class="flex items-center gap-2 font-medium text-[var(--foreground)]"><input type="checkbox" name="categories[]" value="Laptop" {{ in_array('Laptop', $selectedCats) ? 'checked' : '' }} class="accent-[#244229]"> Laptop &amp; PC Motherboards</label>
                    <label class="flex items-center gap-2 font-medium text-[var(--foreground)]"><input type="checkbox" name="categories[]" value="Phone" {{ in_array('Phone', $selectedCats) ? 'checked' : '' }} class="accent-[#244229]"> Smartphone Hardware</label>
                    <label class="flex items-center gap-2 font-medium text-[var(--foreground)]"><input type="checkbox" name="categories[]" value="Display" {{ in_array('Display', $selectedCats) ? 'checked' : '' }} class="accent-[#244229]"> Display Panel Replacement</label>
                    <label class="flex items-center gap-2 font-medium text-[var(--foreground)]"><input type="checkbox" name="categories[]" value="WaterDamage" {{ in_array('WaterDamage', $selectedCats) ? 'checked' : '' }} class="accent-[#244229]"> Water Damage Micro-soldering</label>
                </div>
            </div>

            <button type="submit" class="w-full rounded-full bg-[var(--accent-dark)] hover:opacity-95 text-[var(--accent-foreground)] font-bold py-3 text-xs mt-4 shadow-md transition">
                {{ $profile ? 'Save & Resubmit Application for Verification' : 'Submit Application for Verification' }}
            </button>
        </form>
    </details>

    {{-- ===================================================================== --}}
    {{-- OTP VERIFICATION MODAL (Email & SMS)                                  --}}
    {{-- ===================================================================== --}}
    <div
        x-show="otpModalOpen"
        x-cloak
        class="fixed inset-0 z-50 flex items-center justify-center bg-black/55 p-4"
        @keydown.escape.window="otpModalOpen = false"
    >
        <div
            @click.away="otpModalOpen = false"
            class="w-full max-w-md rounded-3xl border border-[var(--border)] bg-[var(--card)] p-6 shadow-2xl space-y-4"
        >
            <div class="flex items-center justify-between">
                <h3 class="text-base font-black text-[var(--foreground)]" x-text="otpChannel === 'phone' ? 'Verify Phone Number' : 'Verify Business Email'"></h3>
                <button type="button" @click="otpModalOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <p class="text-xs text-[var(--muted-foreground)]">
                <span x-show="otpChannel === 'email'">We will send a 6-digit verification code to <strong>{{ $authUser->email ?? '' }}</strong>.</span>
                <span x-show="otpChannel === 'phone'">We will send a 6-digit SMS code to <strong>{{ $displayPhone }}</strong>.</span>
            </p>

            <template x-if="otpFeedback">
                <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 text-xs font-semibold text-emerald-900" x-text="otpFeedback"></div>
            </template>

            <template x-if="otpError">
                <div class="rounded-xl border border-rose-300 bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-800" x-text="otpError"></div>
            </template>

            <div x-show="!otpStepSent" class="pt-1">
                <button
                    type="button"
                    @click="sendOtpNow()"
                    :disabled="otpSending"
                    class="w-full rounded-full bg-[var(--accent-dark)] py-2.5 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 disabled:opacity-50 transition"
                >
                    <span x-show="!otpSending">Send 6-digit OTP Code</span>
                    <span x-show="otpSending">Sending code...</span>
                </button>
            </div>

            <div x-show="otpStepSent" class="space-y-3 pt-1">
                <div>
                    <label class="block text-xs font-bold text-[var(--foreground)] mb-1">Enter 6-digit code</label>
                    <input
                        type="text"
                        maxlength="6"
                        x-model="otpCode"
                        placeholder="000000"
                        class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 text-center font-mono text-base tracking-[0.35em] font-black outline-none"
                    >
                </div>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="verifyOtpNow()"
                        :disabled="otpVerifying"
                        class="flex-1 rounded-full bg-[var(--accent-dark)] py-2.5 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 disabled:opacity-50 transition"
                    >
                        <span x-show="!otpVerifying">Verify Code</span>
                        <span x-show="otpVerifying">Verifying...</span>
                    </button>
                    <button
                        type="button"
                        @click="sendOtpNow()"
                        :disabled="otpSending"
                        class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition"
                    >
                        Resend
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

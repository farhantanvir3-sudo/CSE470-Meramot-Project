{{--
=============================================================================
FEATURE R11: Dedicated Vendor Partner Application Form (`/vendor/apply`)
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Architectural Layer: View (Vendor/Apply)
Controller: VendorOnboardingController@showApply, storeApply, checkEmail
=============================================================================
--}}
@extends('layouts.app')

@section('title', ($isEditMode ? 'Edit Vendor Application' : 'Vendor Application') . ' | Meramot')

@section('content')
@php
    $presetSpecialties = [
        'Smartphone Repair', 'Laptop Repair', 'Desktop Repair', 'Tablet Repair',
        'Smartwatch Repair', 'Console Repair', 'TV Repair', 'Audio Equipment Repair',
        'Camera Repair', 'Drone Repair', 'Appliance Repair', 'Micro-soldering',
        'Data Recovery', 'Screen Replacement', 'Battery Replacement', 'Water Damage Repair',
    ];
    $existingSpecs = old('specialties', $profile->specialties ?? []);
    if (is_string($existingSpecs)) {
        $existingSpecs = array_filter(array_map('trim', explode(',', $existingSpecs)));
    }
    if (!is_array($existingSpecs)) {
        $existingSpecs = [];
    }
@endphp

<div
    class="mx-auto max-w-4xl px-4 py-8 md:px-6"
    x-data="{
        isEditMode: {{ $isEditMode ? 'true' : 'false' }},
        logoPreview: @js($profile->logo_url ?? ''),
        ownerName: @js(old('owner_name', $profile->owner_name ?? '')),
        businessEmail: @js(old('business_email', $profile->business_email ?? '')),
        emailTakenMsg: '',
        emailCheckTimer: null,
        phone: @js(old('phone', $profile->phone ?? '')),
        shopName: @js(old('shop_name', $profile->shop_name ?? '')),
        password: '',
        confirmPassword: '',
        showPass: false,
        showConfirmPass: false,
        gpsAddress: @js(old('gps_address', $profile->gps_address ?? '')),
        shopAddress: @js(old('shop_address', $profile->shop_address ?? '')),
        sameAsGps: {{ old('same_as_gps_location', $profile->same_as_gps_location ?? false) ? 'true' : 'false' }},
        shopLat: @js(old('shop_latitude', $profile->shop_latitude ?? '')),
        shopLng: @js(old('shop_longitude', $profile->shop_longitude ?? '')),
        city: @js(old('city', $profile->city ?? '')),
        area: @js(old('area', $profile->area ?? '')),
        isBranched: {{ old('is_branched', $profile->is_branched ?? false) ? 'true' : 'false' }},
        branchName: @js(old('branch_name', optional(optional($profile)->shops)->first()->branch_name ?? '')),
        presetOptions: @js($presetSpecialties),
        selectedSpecialties: @js(array_values($existingSpecs)),
        customSpecialty: '',
        get passwordsMismatch() {
            return this.confirmPassword.length > 0 && this.password !== this.confirmPassword;
        },
        get phoneInvalid() {
            if (!this.phone || !this.phone.trim()) return false;
            const cleaned = this.phone.trim().replace(/[\s-]/g, '');
            return !/^(?:\+?8801|01)[3-9]\d{8}$/.test(cleaned);
        },
        get hasLength() { return this.password.length >= 8; },
        get hasUpper() { return /[A-Z]/.test(this.password); },
        get hasLower() { return /[a-z]/.test(this.password); },
        get hasNumber() { return /[0-9]/.test(this.password); },
        handleLogoFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.logoPreview = URL.createObjectURL(file);
        },
        checkEmailDebounced() {
            this.emailTakenMsg = '';
            clearTimeout(this.emailCheckTimer);
            const trimmed = (this.businessEmail || '').trim().toLowerCase();
            if (!trimmed || !trimmed.includes('@')) return;
            this.emailCheckTimer = setTimeout(async () => {
                try {
                    const resp = await fetch('/api/vendors/check-email?email=' + encodeURIComponent(trimmed), {
                        headers: { 'Accept': 'application/json' }
                    });
                    const data = await resp.json();
                    if (resp.ok && data.available === false) {
                        this.emailTakenMsg = data.message || 'Account already exists.';
                    }
                } catch (e) {}
            }, 450);
        },
        applyMapLocation(loc) {
            if (!loc) return;
            this.gpsAddress = loc.address || '';
            this.shopLat = typeof loc.lat === 'number' ? loc.lat.toFixed(6) : '';
            this.shopLng = typeof loc.lng === 'number' ? loc.lng.toFixed(6) : '';
            if (loc.city) this.city = loc.city;
            if (loc.area) this.area = loc.area;
            if (this.sameAsGps && this.gpsAddress) {
                this.shopAddress = this.gpsAddress;
            }
        },
        toggleSpecialty(tag) {
            if (this.selectedSpecialties.includes(tag)) {
                this.selectedSpecialties = this.selectedSpecialties.filter(s => s !== tag);
            } else {
                this.selectedSpecialties.push(tag);
            }
        },
        addCustomSpecialty() {
            const val = this.customSpecialty.trim();
            if (!val) return;
            if (!this.selectedSpecialties.some(s => s.toLowerCase() === val.toLowerCase())) {
                this.selectedSpecialties.push(val);
            }
            this.customSpecialty = '';
        }
    }"
    @meramot-location-changed.window="applyMapLocation($event.detail)"
>
    <div class="rounded-[1.5rem] md:rounded-[2rem] border border-white/60 bg-white/90 p-5 md:p-8 shadow-2xl backdrop-blur">
        <div class="mb-5 md:mb-6 flex items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl md:text-3xl font-bold text-[#244229]">
                    {{ $isEditMode ? 'Edit Vendor Application' : 'Vendor Application' }}
                </h1>
                <p class="mt-1.5 text-xs md:text-sm text-[#5e6d64]">
                    {{ $isEditMode
                        ? 'Update your submitted vendor information below.'
                        : 'Apply as a repair shop partner. Your application will be reviewed by the Meramot admin team.' }}
                </p>
            </div>
            <div class="flex items-center gap-2.5 shrink-0">
                @guest
                    <a href="/login" class="hidden sm:inline-flex items-center gap-2 rounded-full border border-[#c8dac2] bg-[var(--mint-100)] px-4 py-2 text-xs font-bold text-[#244229] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#244229] hover:bg-[var(--mint-200)] hover:shadow">
                        Already applied? Sign in
                    </a>
                @else
                    @if($isEditMode)
                        <a href="/vendor/status" class="hidden sm:inline-flex items-center gap-2 rounded-full border border-[#c8dac2] bg-[var(--mint-100)] px-4 py-2 text-xs font-bold text-[#244229] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#244229] hover:bg-[var(--mint-200)] hover:shadow">
                            Application Status
                        </a>
                    @endif
                @endguest
                <a
                    href="{{ $isEditMode ? '/vendor/onboarding' : (auth()->check() ? '/' : '/login') }}"
                    title="{{ $isEditMode ? 'Back to Vendor Onboarding' : (auth()->check() ? 'Back to Home' : 'Back to Sign In') }}"
                    aria-label="{{ $isEditMode ? 'Back to Vendor Onboarding' : (auth()->check() ? 'Back to Home' : 'Back to Sign In') }}"
                    class="group inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#244229] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#244229] hover:bg-[#244229] hover:text-[#E4FCD5] hover:shadow-md"
                >
                    <i class="fa-solid fa-arrow-left text-xs transition-transform duration-200 group-hover:-translate-x-0.5"></i>
                </a>
            </div>
        </div>

        @if($isEditMode)
            @if(($profile?->verification_status ?? '') === 'rejected')
                <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3.5 text-xs md:text-sm text-rose-900 space-y-1">
                    <p class="font-bold flex items-center gap-1.5">
                        <i class="fa-solid fa-circle-exclamation text-rose-600"></i>
                        <span>Application Returned for Revision</span>
                    </p>
                    @if(!empty($profile->rejection_reason))
                        <p class="text-rose-800"><strong>Admin Feedback:</strong> {{ $profile->rejection_reason }}</p>
                    @endif
                    <p class="text-[11px] text-rose-700">Update the requested details below and resubmit your application for administrative review.</p>
                </div>
            @else
                <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs md:text-sm text-amber-900">
                    Update your submitted application details below and resubmit for administrative review.
                </div>
            @endif
        @endif

        @if($errors->any())
            <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs md:text-sm text-rose-800 space-y-1.5">
                @foreach($errors->all() as $error)
                    <div class="font-medium">{{ $error }}</div>
                @endforeach
                @if(!auth()->check() && str_contains(strtolower(implode(' ', $errors->all())), 'already exists'))
                    <a href="/login" class="mt-2 inline-block rounded-xl bg-[#244229] px-4 py-2 text-xs font-semibold text-white hover:opacity-90">
                        Go to Sign in
                    </a>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('vendor.apply.store') }}" enctype="multipart/form-data" class="space-y-5" autocomplete="off">
            @csrf

            <!-- Shop Logo & Trade Verification Document Dashed Dropzone Cards -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <!-- Card 1: Shop Logo Interactive Dropzone -->
                <div
                    x-data="{
                        logoName: '',
                        logoSize: '',
                        logoType: 'IMAGE',
                        removeLogo: false,
                        draggingLogo: false,
                        pickLogo(file) {
                            if (!file) return;
                            this.removeLogo = false;
                            this.logoName = file.name;
                            const kb = file.size / 1024;
                            this.logoSize = kb >= 1024 ? (kb / 1024).toFixed(2) + ' MB' : Math.round(kb) + ' KB';
                            this.logoType = (file.type.split('/')[1] || 'IMAGE').toUpperCase();
                            logoPreview = URL.createObjectURL(file);
                        },
                        dropLogo(e) {
                            this.draggingLogo = false;
                            const file = e.dataTransfer?.files?.[0];
                            if (!file) return;
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            $refs.logoInput.files = dt.files;
                            this.pickLogo(file);
                        },
                        clearLogo() {
                            logoPreview = '';
                            this.logoName = '';
                            this.logoSize = '';
                            this.removeLogo = true;
                            $refs.logoInput.value = '';
                        }
                    }"
                    @dragover.prevent="draggingLogo = true"
                    @dragleave.prevent="draggingLogo = false"
                    @drop.prevent="dropLogo($event)"
                    :class="draggingLogo ? 'border-[#244229] bg-[#e4f3df] scale-[1.01]' : (logoPreview ? 'border-emerald-600/60 bg-white' : 'border-[#b9cfb2] bg-[var(--mint-50)]/60 hover:border-[#244229] hover:bg-white')"
                    class="relative rounded-2xl border-2 border-dashed p-4 transition-all duration-200 shadow-sm"
                >
                    <input type="hidden" name="remove_logo" :value="removeLogo ? '1' : '0'">
                    <input
                        x-ref="logoInput"
                        type="file"
                        name="logo"
                        accept="image/png,image/jpeg,image/webp"
                        class="hidden"
                        @change="pickLogo($event.target.files?.[0])"
                    >

                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-extrabold text-[#203126] flex items-center gap-1.5">
                            <i class="fa-solid fa-store text-[#244229]"></i>
                            <span>Shop Logo / Storefront Photo</span>
                        </span>
                        <template x-if="logoPreview">
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-800">Ready ✓</span>
                        </template>
                    </div>

                    <template x-if="!logoPreview">
                        <div @click="$refs.logoInput.click()" class="group flex cursor-pointer flex-col items-center justify-center py-4 text-center">
                            <div class="mb-2 flex h-11 w-11 items-center justify-center rounded-2xl border border-[#c8dac2] bg-white text-[#244229] shadow-sm transition group-hover:scale-105 group-hover:bg-[#244229] group-hover:text-[#E4FCD5]">
                                <i class="fa-solid fa-image text-base"></i>
                            </div>
                            <p class="text-xs font-bold text-[#203126]">Click to upload logo <span class="font-normal text-[#5e6d64]">or drag &amp; drop</span></p>
                            <p class="mt-0.5 text-[10px] text-[#5e6d64]">Square PNG, JPG, WebP • Auto-optimized up to 15MB</p>
                        </div>
                    </template>

                    <template x-if="logoPreview">
                        <div class="relative space-y-2">
                            <button
                                type="button"
                                @click="clearLogo()"
                                title="Remove logo"
                                class="absolute right-1.5 top-1.5 z-10 inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-600 text-xs font-bold text-white shadow transition hover:scale-105 hover:bg-rose-700"
                            >
                                ✕
                            </button>
                            <div class="flex items-center justify-center overflow-hidden rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] p-2">
                                <img :src="logoPreview" alt="Shop Logo Preview" class="max-h-32 w-auto rounded-lg object-contain shadow-sm">
                            </div>
                            <div class="flex items-center justify-between gap-2 text-[11px]">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="rounded bg-[#eef5ea] border border-[#c8dac2] px-2 py-0.5 text-[10px] font-extrabold text-[#163625]" x-text="logoType"></span>
                                    <span class="truncate font-semibold text-[#163625]" x-text="logoName || 'Saved shop logo'"></span>
                                    <span x-show="logoSize" class="text-[10px] text-[#5e6d64]" x-text="'(' + logoSize + ')'"></span>
                                </div>
                                <button type="button" @click="$refs.logoInput.click()" class="rounded-full border border-[#c8dac2] bg-[#eef5ea] px-2.5 py-1 text-[10px] font-bold text-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] transition">
                                    Replace
                                </button>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Card 2: Trade License / NID Verification Scan Interactive Dropzone -->
                <div
                    x-data="{
                        docPreview: @js($profile->documents_path ?? ''),
                        docName: '',
                        docSize: '',
                        docType: 'SCAN',
                        docIsPdf: {{ str_starts_with((string) ($profile->documents_path ?? ''), 'data:application/pdf') ? 'true' : 'false' }},
                        draggingDoc: false,
                        pickDoc(file) {
                            if (!file) return;
                            this.docName = file.name;
                            const kb = file.size / 1024;
                            this.docSize = kb >= 1024 ? (kb / 1024).toFixed(2) + ' MB' : Math.round(kb) + ' KB';
                            this.docIsPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
                            this.docType = this.docIsPdf ? 'PDF' : ((file.type.split('/')[1] || 'IMG').toUpperCase());
                            this.docPreview = URL.createObjectURL(file);
                        },
                        dropDoc(e) {
                            this.draggingDoc = false;
                            const file = e.dataTransfer?.files?.[0];
                            if (!file) return;
                            const dt = new DataTransfer();
                            dt.items.add(file);
                            this.$refs.docInput.files = dt.files;
                            this.pickDoc(file);
                        },
                        clearDoc() {
                            this.docPreview = '';
                            this.docName = '';
                            this.docSize = '';
                            this.docIsPdf = false;
                            this.$refs.docInput.value = '';
                        }
                    }"
                    @dragover.prevent="draggingDoc = true"
                    @dragleave.prevent="draggingDoc = false"
                    @drop.prevent="dropDoc($event)"
                    :class="draggingDoc ? 'border-[#244229] bg-[#e4f3df] scale-[1.01]' : (docPreview ? 'border-emerald-600/60 bg-white' : 'border-[#b9cfb2] bg-[var(--mint-50)]/60 hover:border-[#244229] hover:bg-white')"
                    class="relative rounded-2xl border-2 border-dashed p-4 transition-all duration-200 shadow-sm"
                >
                    <input
                        x-ref="docInput"
                        type="file"
                        name="verification_document"
                        accept="image/png,image/jpeg,image/webp,application/pdf,.pdf"
                        class="hidden"
                        @change="pickDoc($event.target.files?.[0])"
                    >

                    <div class="mb-2 flex items-center justify-between">
                        <span class="text-xs font-extrabold text-[#203126] flex items-center gap-1.5">
                            <i class="fa-solid fa-file-shield text-[#244229]"></i>
                            <span>Trade License / NID Scan <span class="font-normal text-[#5e6d64]">(Optional)</span></span>
                        </span>
                        <template x-if="docPreview">
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-800">Attached ✓</span>
                        </template>
                    </div>

                    <template x-if="!docPreview">
                        <div @click="$refs.docInput.click()" class="group flex cursor-pointer flex-col items-center justify-center py-4 text-center">
                            <div class="mb-2 flex h-11 w-11 items-center justify-center rounded-2xl border border-[#c8dac2] bg-white text-[#244229] shadow-sm transition group-hover:scale-105 group-hover:bg-[#244229] group-hover:text-[#E4FCD5]">
                                <i class="fa-solid fa-cloud-arrow-up text-base"></i>
                            </div>
                            <p class="text-xs font-bold text-[#203126]">Click to attach scan <span class="font-normal text-[#5e6d64]">or drag &amp; drop</span></p>
                            <p class="mt-0.5 text-[10px] text-[#5e6d64]">Trade License or NID Image/PDF • Max 4MB</p>
                        </div>
                    </template>

                    <template x-if="docPreview">
                        <div class="relative space-y-2">
                            <button
                                type="button"
                                @click="clearDoc()"
                                title="Remove document"
                                class="absolute right-1.5 top-1.5 z-10 inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-600 text-xs font-bold text-white shadow transition hover:scale-105 hover:bg-rose-700"
                            >
                                ✕
                            </button>

                            <template x-if="!docIsPdf">
                                <div class="flex items-center justify-center overflow-hidden rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] p-2">
                                    <img :src="docPreview" alt="Verification Document" class="max-h-32 w-auto rounded-lg object-contain shadow-sm">
                                </div>
                            </template>

                            <template x-if="docIsPdf">
                                <div class="flex items-center gap-3 rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] p-3.5">
                                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                                        <i class="fa-solid fa-file-pdf text-xl"></i>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate text-xs font-extrabold text-[#163625]" x-text="docName || 'Trade License / NID PDF'"></p>
                                        <a :href="docPreview" target="_blank" class="text-[11px] font-bold text-emerald-800 hover:underline">Preview PDF ↗</a>
                                    </div>
                                </div>
                            </template>

                            <div class="flex items-center justify-between gap-2 text-[11px]">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="rounded bg-[#eef5ea] border border-[#c8dac2] px-2 py-0.5 text-[10px] font-extrabold text-[#163625]" x-text="docType"></span>
                                    <span class="truncate font-semibold text-[#163625]" x-text="docName || 'Verification scan'"></span>
                                    <span x-show="docSize" class="text-[10px] text-[#5e6d64]" x-text="'(' + docSize + ')'"></span>
                                </div>
                                <button type="button" @click="$refs.docInput.click()" class="rounded-full border border-[#c8dac2] bg-[#eef5ea] px-2.5 py-1 text-[10px] font-bold text-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] transition">
                                    Replace
                                </button>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Core Applicant & Shop Fields -->
            <div class="grid gap-4 md:grid-cols-2">
                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-700 pl-1">Owner name *</label>
                    <input
                        type="text"
                        name="owner_name"
                        x-model="ownerName"
                        required
                        placeholder="Owner full name"
                        class="rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                    >
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-700 pl-1">Business email *</label>
                    <input
                        type="email"
                        name="business_email"
                        x-model="businessEmail"
                        @input="checkEmailDebounced()"
                        required
                        placeholder="owner@yourworkshop.bd"
                        :class="emailTakenMsg ? 'border-red-400' : 'border-[var(--border)]'"
                        class="rounded-2xl border bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                    >
                    <p x-show="emailTakenMsg" x-text="emailTakenMsg" class="text-[11px] font-medium text-red-600 pl-1"></p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-700 pl-1">Phone *</label>
                    <input
                        type="tel"
                        name="phone"
                        x-model="phone"
                        required
                        placeholder="01XXXXXXXXX"
                        :class="phoneInvalid ? 'border-red-400' : 'border-[var(--border)]'"
                        class="rounded-2xl border bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                    >
                    <p x-show="phoneInvalid" x-cloak class="text-[11px] font-medium text-red-600 pl-1">
                        Enter a valid Bangladeshi phone number (e.g. 01712345678).
                    </p>
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-700 pl-1">Shop name *</label>
                    <input
                        type="text"
                        name="shop_name"
                        x-model="shopName"
                        required
                        placeholder="e.g. Dhaka Circuit Care"
                        class="rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                    >
                </div>

                @if(!$isEditMode)
                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-700 pl-1">Password *</label>
                        <div class="relative">
                            <input
                                :type="showPass ? 'text' : 'password'"
                                name="password"
                                x-model="password"
                                required
                                minlength="8"
                                placeholder="Min. 8 characters"
                                class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                            >
                            <button type="button" @click="showPass = !showPass" class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-500">
                                <span x-text="showPass ? 'Hide' : 'Show'">Show</span>
                            </button>
                        </div>
                        <div x-show="password.length > 0" x-cloak class="mt-1 flex flex-wrap gap-2 pl-1 text-[10px]">
                            <span :class="hasLength ? 'text-emerald-700 font-bold' : 'text-slate-400'">• 8+ chars</span>
                            <span :class="hasUpper ? 'text-emerald-700 font-bold' : 'text-slate-400'">• Uppercase</span>
                            <span :class="hasLower ? 'text-emerald-700 font-bold' : 'text-slate-400'">• Lowercase</span>
                            <span :class="hasNumber ? 'text-emerald-700 font-bold' : 'text-slate-400'">• Number</span>
                        </div>
                    </div>

                    <div class="flex flex-col gap-1">
                        <label class="text-xs font-semibold text-slate-700 pl-1">Confirm password *</label>
                        <div class="relative">
                            <input
                                :type="showConfirmPass ? 'text' : 'password'"
                                name="password_confirmation"
                                x-model="confirmPassword"
                                required
                                placeholder="Confirm password"
                                class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                            >
                            <button type="button" @click="showConfirmPass = !showConfirmPass" class="absolute right-4 top-1/2 -translate-y-1/2 text-xs text-slate-500">
                                <span x-text="showConfirmPass ? 'Hide' : 'Show'">Show</span>
                            </button>
                        </div>
                        <p x-show="passwordsMismatch" x-cloak class="text-xs font-medium text-red-500 pl-1">Passwords do not match.</p>
                    </div>
                @endif

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-700 pl-1">Trade license number <span class="font-normal text-slate-400">(optional)</span></label>
                    <input
                        type="text"
                        name="business_license_no"
                        value="{{ old('business_license_no', $profile->business_license_no ?? '') }}"
                        placeholder="e.g. TRAD/DNCC/129482"
                        class="rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                    >
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-700 pl-1">National ID (NID) <span class="font-normal text-slate-400">(optional)</span></label>
                    <input
                        type="text"
                        name="nid_number"
                        value="{{ old('nid_number', $profile->nid_number ?? '') }}"
                        placeholder="Owner NID number"
                        class="rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                    >
                </div>

                <!-- 1. Map Location (Mandatory) -->
                <div class="flex flex-col gap-2 md:col-span-2 pt-1">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-location-dot text-[#244229]"></i>
                        <label class="text-xs font-bold text-[#244229]">Map Location <span class="text-red-500">*</span></label>
                    </div>
                    <p class="pl-1 text-[11px] leading-relaxed text-[#5e6d64]">
                        Pin your workshop location on the map for distance matching, local discovery, and courier routing. Private coordinates remain hidden unless matched to your public storefront address below.
                    </p>

                    <input type="hidden" name="shop_latitude" :value="shopLat">
                    <input type="hidden" name="shop_longitude" :value="shopLng">
                    <input type="hidden" name="gps_address" :value="gpsAddress">

                    <template x-if="shopLat && shopLng">
                        <div class="flex items-center justify-between gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3">
                            <div class="flex items-center gap-2 text-xs font-semibold text-emerald-800">
                                <i class="fa-solid fa-map-pin"></i>
                                <span>
                                    Location pinned —
                                    <span x-text="area && city ? (area + ', ' + city) : (shopLat + ', ' + shopLng)"></span>
                                </span>
                            </div>
                            <button
                                type="button"
                                @click="$dispatch('meramot-open-location-modal')"
                                class="shrink-0 rounded-xl bg-emerald-100 px-3 py-1.5 text-xs font-bold text-emerald-900 hover:bg-emerald-200 transition"
                            >
                                Change
                            </button>
                        </div>
                    </template>

                    <template x-if="!shopLat || !shopLng">
                        <button
                            type="button"
                            @click="$dispatch('meramot-open-location-modal')"
                            class="flex items-center justify-center gap-2 rounded-2xl border-2 border-dashed border-[var(--border)] bg-[var(--mint-50)] px-4 py-4 text-sm font-semibold text-[#244229] transition hover:border-[#244229] hover:bg-[var(--mint-100)]"
                        >
                            <i class="fa-solid fa-location-dot"></i>
                            <span>Pick shop location on map</span>
                        </button>
                    </template>
                </div>

                <!-- 2. Public Shop Address (Optional at apply stage, required at shop setup) -->
                <div class="flex flex-col gap-2 md:col-span-2">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <label class="text-xs font-semibold text-slate-700 pl-1">
                            Shop Address <span class="text-[11px] font-normal text-slate-400">(optional now — required at shop setup)</span>
                        </label>
                        <label class="inline-flex items-center gap-2 text-xs font-medium text-[#244229] cursor-pointer">
                            <input type="checkbox" name="same_as_gps_location" value="1" x-model="sameAsGps" @change="if(sameAsGps && gpsAddress) shopAddress = gpsAddress" class="accent-[#244229]">
                            <span>Same as map location</span>
                        </label>
                    </div>
                    <input
                        type="text"
                        name="shop_address"
                        x-model="shopAddress"
                        :readonly="sameAsGps"
                        placeholder="e.g. Shop 12, Level 3, Multiplan Center, Dhaka"
                        class="rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                    >
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-700 pl-1">City</label>
                    <input
                        type="text"
                        name="city"
                        x-model="city"
                        placeholder="Dhaka"
                        class="rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                    >
                </div>

                <div class="flex flex-col gap-1">
                    <label class="text-xs font-semibold text-slate-700 pl-1">Area</label>
                    <input
                        type="text"
                        name="area"
                        x-model="area"
                        placeholder="Banani, Mirpur, Agrabad..."
                        class="rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                    >
                </div>
            </div>

            <!-- Multi-Select & Creatable Specialties -->
            <div class="space-y-2">
                <label class="text-xs font-semibold text-slate-700 pl-1 block">Specialties</label>
                <div class="rounded-2xl border border-[var(--border)] bg-white p-3.5 space-y-3">
                    <div class="flex flex-wrap gap-1.5">
                        <template x-for="spec in selectedSpecialties" :key="spec">
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-900">
                                <input type="hidden" name="specialties[]" :value="spec">
                                <span x-text="spec"></span>
                                <button type="button" @click="toggleSpecialty(spec)" class="text-emerald-700 hover:text-red-600 font-bold">✕</button>
                            </span>
                        </template>
                        <span x-show="selectedSpecialties.length === 0" class="text-xs text-slate-400">Click tags below or type a custom specialty...</span>
                    </div>

                    <div class="flex gap-2">
                        <input
                            type="text"
                            x-model="customSpecialty"
                            @keydown.enter.prevent="addCustomSpecialty()"
                            placeholder="Add custom specialty (e.g. MacBook Logic Board)..."
                            class="flex-1 rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/40 px-3 py-2 text-xs outline-none focus:border-[#244229]"
                        >
                        <button type="button" @click="addCustomSpecialty()" class="rounded-xl bg-[#244229] px-3.5 py-2 text-xs font-semibold text-white">
                            + Add
                        </button>
                    </div>

                    <div class="flex flex-wrap gap-1.5 pt-1 border-t border-slate-100">
                        <template x-for="opt in presetOptions" :key="opt">
                            <button
                                type="button"
                                @click="toggleSpecialty(opt)"
                                :class="selectedSpecialties.includes(opt) ? 'bg-[#244229] text-white border-[#244229]' : 'bg-slate-50 text-slate-700 border-slate-200 hover:bg-emerald-50'"
                                class="rounded-full border px-3 py-1 text-[11px] font-medium transition"
                                x-text="opt"
                            ></button>
                        </template>
                    </div>
                </div>
            </div>

            <!-- Optional Multi-Branch Toggle -->
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/40 p-3.5 space-y-2">
                <label class="flex items-center gap-2.5 cursor-pointer text-xs font-semibold text-[#203126]">
                    <input type="checkbox" name="is_branched" value="1" x-model="isBranched" class="h-4 w-4 accent-[#244229]">
                    <span>Operate multiple branches under this brand (optional — can also enable later)</span>
                </label>
                <div x-show="isBranched" x-cloak class="pt-1">
                    <input
                        type="text"
                        name="branch_name"
                        x-model="branchName"
                        placeholder="Primary branch tag (e.g. Banani Flagship)"
                        class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2 text-xs outline-none"
                    >
                </div>
            </div>

            <!-- Additional Notes -->
            <div>
                <textarea
                    name="notes"
                    rows="3"
                    placeholder="Additional notes about your workshop, equipment, or experience (optional)"
                    class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] outline-none focus:border-[#244229]"
                >{{ old('notes', $profile->notes ?? '') }}</textarea>
            </div>

            <!-- Service Mode Checkboxes -->
            <div class="grid gap-3 sm:grid-cols-3 pt-1">
                <label class="flex items-center gap-2.5 rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] cursor-pointer">
                    <input
                        type="checkbox"
                        name="courier_pickup"
                        value="1"
                        {{ old('courier_pickup', $profile->courier_pickup ?? false) ? 'checked' : '' }}
                        class="h-4 w-4 rounded accent-[#244229]"
                    >
                    <span>Courier pickup</span>
                </label>

                <label class="flex items-center gap-2.5 rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] cursor-pointer">
                    <input
                        type="checkbox"
                        name="in_shop_repair"
                        value="1"
                        {{ old('in_shop_repair', $profile->in_shop_repair ?? true) ? 'checked' : '' }}
                        class="h-4 w-4 rounded accent-[#244229]"
                    >
                    <span>In-shop repair</span>
                </label>

                <label class="flex items-center gap-2.5 rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-[#203126] cursor-pointer">
                    <input
                        type="checkbox"
                        name="spare_parts"
                        value="1"
                        {{ old('spare_parts', $profile->spare_parts ?? false) ? 'checked' : '' }}
                        class="h-4 w-4 rounded accent-[#244229]"
                    >
                    <span>Spare parts</span>
                </label>
            </div>

            <button
                type="submit"
                :disabled="(!isEditMode && passwordsMismatch) || Boolean(emailTakenMsg)"
                class="w-full rounded-2xl bg-[#244229] px-5 py-3.5 text-sm font-semibold text-white shadow-md transition hover:opacity-95 disabled:opacity-60"
            >
                {{ $isEditMode ? 'Update submitted information' : 'Submit vendor application' }}
            </button>
        </form>
    </div>
</div>
@endsection

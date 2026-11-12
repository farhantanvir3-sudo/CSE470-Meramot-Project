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
                <a href="/login" class="hidden sm:inline-flex items-center gap-2 rounded-full border border-[#c8dac2] bg-[var(--mint-100)] px-4 py-2 text-xs font-bold text-[#244229] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#244229] hover:bg-[var(--mint-200)] hover:shadow">
                    Already applied? Sign in
                </a>
                <a
                    href="{{ $isEditMode ? '/vendor/status' : '/login' }}"
                    title="{{ $isEditMode ? 'Back to Vendor Status' : 'Back to Sign In' }}"
                    aria-label="{{ $isEditMode ? 'Back to Vendor Status' : 'Back to Sign In' }}"
                    class="group inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#244229] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#244229] hover:bg-[#244229] hover:text-[#E4FCD5] hover:shadow-md"
                >
                    <i class="fa-solid fa-arrow-left text-xs transition-transform duration-200 group-hover:-translate-x-0.5"></i>
                </a>
            </div>
        </div>

        @if($isEditMode)
            <div class="mb-5 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs md:text-sm text-amber-900">
                Update your previous vendor application and submit again. It will go back to pending admin review.
            </div>
        @endif

        @if($errors->any())
            <div class="mb-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs md:text-sm text-rose-800 space-y-1.5">
                @foreach($errors->all() as $error)
                    <div class="font-medium">{{ $error }}</div>
                @endforeach
                @if(str_contains(strtolower(implode(' ', $errors->all())), 'already exists'))
                    <a href="/login" class="mt-2 inline-block rounded-xl bg-[#244229] px-4 py-2 text-xs font-semibold text-white hover:opacity-90">
                        Go to Sign in
                    </a>
                @endif
            </div>
        @endif

        <form method="POST" action="{{ route('vendor.apply.store') }}" enctype="multipart/form-data" class="space-y-5" autocomplete="off">
            @csrf

            <!-- Shop Logo Upload -->
            <div class="flex flex-col sm:flex-row items-center gap-4 rounded-2xl border border-[var(--border)] bg-white p-4 md:p-5">
                <div class="relative h-24 w-24 md:h-28 md:w-28 shrink-0 overflow-hidden rounded-[1.5rem] border-2 border-dashed border-[var(--border)] bg-[var(--mint-50)] transition hover:border-[#244229]">
                    <template x-if="logoPreview">
                        <div class="h-full w-full relative">
                            <img :src="logoPreview" alt="Shop Logo" class="h-full w-full object-cover">
                            <button
                                type="button"
                                @click="logoPreview = ''; $refs.logoInput.value = ''"
                                class="absolute right-1 top-1 rounded-full bg-black/60 px-2 py-0.5 text-xs text-white hover:bg-red-600"
                            >
                                ✕
                            </button>
                        </div>
                    </template>
                    <template x-if="!logoPreview">
                        <label class="flex h-full w-full cursor-pointer flex-col items-center justify-center text-[var(--muted-foreground)] hover:text-[#244229]">
                            <i class="fa-solid fa-image text-xl mb-1"></i>
                            <span class="text-[10px] font-bold uppercase tracking-wider">Logo</span>
                            <input x-ref="logoInput" type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="hidden" @change="handleLogoFile($event)">
                        </label>
                    </template>
                </div>
                <div class="text-center sm:text-left">
                    <p class="text-sm md:text-lg font-bold text-[#203126]">Shop Logo</p>
                    <p class="mt-1 max-w-sm text-xs md:text-sm leading-relaxed text-[#5e6d64]">
                        Upload an optional logo or storefront photo to make your shop stand out. A square image works best.
                    </p>
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
                        Pin your shop's approximate location on the map. Used for distance calculations, sorting, and rider ETA — kept private unless you sync it below.
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

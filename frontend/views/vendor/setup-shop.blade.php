@extends('layouts.app')

@section('title', 'Set Up Your Shop | Meramot Vendor')

@section('content')
@php
    $isEditMode = (bool) optional($primaryShop)->setup_complete;
    $onboardingTags = $onboardingTags ?? [];
    $serviceSuggestions = $serviceSuggestions ?? [];

    // Fixed-price services & packages start EMPTY for a new vendor. They are only
    // pre-filled from items the vendor has already published (or a failed submit).
    $initialServices = [];
    if (is_array(old('skill_tags'))) {
        foreach ((array) old('skill_tags') as $item) {
            $trimmed = trim((string) $item);
            if ($trimmed === '') continue;
            $parts = explode('|', $trimmed, 3);
            $initialServices[] = [
                'name' => trim($parts[0]),
                'price' => trim($parts[1] ?? ''),
                'description' => trim($parts[2] ?? ''),
            ];
        }
    } elseif ($primaryShop && $primaryShop->relationLoaded('services')) {
        foreach ($primaryShop->services->sortBy('sort_order') as $svc) {
            $initialServices[] = [
                'name' => $svc->name,
                'price' => $svc->base_price !== null ? (string) (float) $svc->base_price : '',
                'description' => (string) ($svc->short_description ?? ''),
            ];
        }
    }
@endphp

<div
    class="mx-auto max-w-5xl px-4 py-8 md:px-6"
    x-data="{
        logoPreview: @js($primaryShop->logo_url ?? $profile->logo_url ?? ''),
        bannerPreview: @js($primaryShop->banner_url ?? ''),
        shopName: @js(old('shop_name', $primaryShop->name ?? $profile->shop_name ?? '')),
        phone: @js(old('phone', $primaryShop->phone ?? $profile->phone ?? $user->phone ?? '')),
        description: @js(old('description', $primaryShop->description ?? $profile->notes ?? '')),
        address: @js(old('address', $primaryShop->address ?? $profile->shop_address ?? '')),
        gpsAddress: @js(old('gps_address', $primaryShop->gps_address ?? $profile->gps_address ?? '')),
        sameAsGps: {{ old('same_as_gps_location', $primaryShop->same_as_gps_location ?? $profile->same_as_gps_location ?? false) ? 'true' : 'false' }},
        city: @js(old('city', $primaryShop->city ?? $profile->city ?? 'Dhaka')),
        area: @js(old('area', $primaryShop->area ?? $profile->area ?? '')),
        lat: @js(old('latitude', $primaryShop->latitude ?? $profile->shop_latitude ?? '')),
        lng: @js(old('longitude', $primaryShop->longitude ?? $profile->shop_longitude ?? '')),
        services: @js($initialServices),
        serviceName: '',
        servicePrice: '',
        serviceDescription: '',
        priceHint: '',
        serviceError: '',
        editingIndex: null,
        suggestions: @js($serviceSuggestions),
        showSuggestions: false,
        handleLogoFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.logoPreview = URL.createObjectURL(file);
        },
        handleBannerFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.bannerPreview = URL.createObjectURL(file);
        },
        applyMapLocation(loc) {
            if (!loc) return;
            this.gpsAddress = loc.address || this.gpsAddress;
            this.lat = typeof loc.lat === 'number' ? loc.lat.toFixed(6) : this.lat;
            this.lng = typeof loc.lng === 'number' ? loc.lng.toFixed(6) : this.lng;
            if (loc.city) this.city = loc.city;
            if (loc.area) this.area = loc.area;
            if (!this.address || this.sameAsGps) {
                this.address = loc.address || this.address;
            }
        },
        addService() {
            const name = this.serviceName.trim().replace(/\|/g, '/');
            const price = this.servicePrice.toString().trim();
            const description = this.serviceDescription.trim().replace(/\|/g, '/');
            this.serviceError = '';
            if (!name) { this.serviceError = 'Enter a service or package name.'; return; }
            if (price === '' || isNaN(Number(price)) || Number(price) <= 0) {
                this.serviceError = 'Set a fixed price (৳) greater than 0.';
                return;
            }
            const duplicateIdx = this.services.findIndex(s => s.name.toLowerCase() === name.toLowerCase());
            if (this.editingIndex !== null) {
                if (duplicateIdx !== -1 && duplicateIdx !== this.editingIndex) {
                    this.serviceError = 'This service is already in your list.';
                    return;
                }
                this.services.splice(this.editingIndex, 1, { name, price, description });
            } else {
                if (duplicateIdx !== -1) { this.serviceError = 'This service is already in your list.'; return; }
                this.services.push({ name, price, description });
            }
            this.resetServiceForm();
        },
        resetServiceForm() {
            this.serviceName = '';
            this.servicePrice = '';
            this.serviceDescription = '';
            this.priceHint = '';
            this.serviceError = '';
            this.editingIndex = null;
            this.showSuggestions = false;
        },
        useSuggestion(sug) {
            this.editingIndex = null;
            this.serviceName = sug.name;
            this.priceHint = sug.hint ? Number(sug.hint).toLocaleString() : '';
            this.serviceError = '';
            this.showSuggestions = false;
            this.$nextTick(() => this.$refs.servicePriceInput?.focus());
        },
        editService(index) {
            const svc = this.services[index];
            if (!svc) return;
            this.editingIndex = index;
            this.serviceName = svc.name;
            this.servicePrice = svc.price;
            this.serviceDescription = svc.description || '';
            this.serviceError = '';
            this.$nextTick(() => this.$refs.serviceNameInput?.focus());
        },
        removeService(index) {
            this.services.splice(index, 1);
            if (this.editingIndex === index) this.resetServiceForm();
        },
        isAdded(name) {
            return this.services.some(s => s.name.toLowerCase() === name.toLowerCase());
        },
        filteredSuggestions() {
            const q = this.serviceName.toLowerCase().trim();
            return this.suggestions.filter(s =>
                (!q || s.name.toLowerCase().includes(q)) && !this.isAdded(s.name)
            );
        }
    }"
    @meramot-location-changed.window="applyMapLocation($event.detail)"
>
    <div class="rounded-[2rem] border border-white/60 bg-white/90 p-4 sm:p-6 md:p-8 shadow-2xl backdrop-blur">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#58725f]">
            Vendor
        </p>
        <h1 class="mt-2 text-2xl md:text-3xl font-bold text-[#244229]">
            {{ $isEditMode ? 'Edit vendor details' : 'Set up your shop' }}
        </h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            {{ $isEditMode
                ? 'Update your workshop details, offered services, standard rates, and skill specializations.'
                : 'Complete your workshop profile before accepting marketplace orders. Your listing becomes publicly visible once pricing, services, and business details are confirmed.' }}
        </p>

        @if($errors->any())
            <div class="mt-5 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-xs md:text-sm text-rose-700 space-y-1">
                @foreach($errors->all() as $error)
                    <div>• {{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="{{ route('vendor.setup-shop.store') }}" enctype="multipart/form-data" class="mt-8 space-y-7">
            @csrf

            <!-- 1. Shop Identity -->
            <section class="rounded-3xl bg-[#f6faf4] p-5 md:p-6">
                <h2 class="text-lg font-semibold text-[#244229]">Shop identity</h2>

                <div class="mt-4 grid gap-4 md:grid-cols-[auto_1fr] items-center">
                    <div class="flex flex-col sm:flex-row items-center gap-4">
                        <div class="relative h-24 w-24 md:h-28 md:w-28 shrink-0 overflow-hidden rounded-[1.5rem] border-2 border-dashed border-[var(--border)] bg-[var(--mint-50)]">
                            <template x-if="logoPreview">
                                <div class="h-full w-full relative">
                                    <img :src="logoPreview" alt="Shop Logo" class="h-full w-full object-cover">
                                    <button type="button" @click="logoPreview = ''; if ($refs.setupLogo) $refs.setupLogo.value = ''" class="absolute right-1 top-1 rounded-full bg-black/60 px-2 py-0.5 text-xs text-white hover:bg-red-600">✕</button>
                                </div>
                            </template>
                            <template x-if="!logoPreview">
                                <label class="flex h-full w-full cursor-pointer flex-col items-center justify-center text-slate-500 hover:text-[#244229]">
                                    <i class="fa-solid fa-image text-xl mb-1"></i>
                                    <span class="text-[10px] font-bold uppercase tracking-wider">Logo</span>
                                    <input x-ref="setupLogo" type="file" name="logo" accept="image/png,image/jpeg,image/webp" class="hidden" @change="handleLogoFile($event)">
                                </label>
                            </template>
                        </div>
                        <div class="text-center sm:text-left">
                            <p class="text-sm font-semibold text-slate-800">Shop Logo (Optional)</p>
                            <p class="mt-0.5 max-w-[200px] text-xs text-slate-500">Square workshop badge shown on search tiles.</p>
                        </div>
                    </div>

                    <div class="relative h-24 md:h-28 w-full overflow-hidden rounded-[1.5rem] border-2 border-dashed border-[var(--border)] bg-white/80">
                        <template x-if="bannerPreview">
                            <div class="h-full w-full relative">
                                <img :src="bannerPreview" alt="Storefront Cover Banner" class="h-full w-full object-cover">
                                <button type="button" @click="bannerPreview = ''; if ($refs.setupBanner) $refs.setupBanner.value = ''" class="absolute right-2 top-2 rounded-full bg-black/65 px-2.5 py-0.5 text-xs font-bold text-white hover:bg-red-600">✕ Remove Cover</button>
                            </div>
                        </template>
                        <template x-if="!bannerPreview">
                            <label class="flex h-full w-full cursor-pointer flex-col items-center justify-center px-4 text-center text-slate-500 hover:text-[#244229]">
                                <i class="fa-solid fa-panorama text-lg mb-1"></i>
                                <span class="text-xs font-semibold text-slate-700">Storefront Cover Banner (Optional)</span>
                                <span class="text-[11px] text-slate-500">Wide workshop photo displayed at the top of your public shop page</span>
                                <input x-ref="setupBanner" type="file" name="banner" accept="image/png,image/jpeg,image/webp" class="hidden" @change="handleBannerFile($event)">
                            </label>
                        </template>
                    </div>
                </div>

                <div class="mt-5 grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Shop name *</span>
                        <input
                            type="text"
                            name="shop_name"
                            x-model="shopName"
                            required
                            placeholder="Enter your shop name"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Contact phone *</span>
                        <input
                            type="tel"
                            name="phone"
                            x-model="phone"
                            required
                            placeholder="01XXXXXXXXX"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >
                    </label>

                    <label class="block md:col-span-2">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Shop description</span>
                        <textarea
                            name="description"
                            x-model="description"
                            rows="3"
                            placeholder="Tell customers what your workshop specializes in"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        ></textarea>
                    </label>
                </div>
            </section>

            <!-- 2. Shop Location -->
            <section class="rounded-3xl bg-[#f6faf4] p-5 md:p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h2 class="text-lg font-semibold text-[#244229]">Shop location</h2>
                    <button
                        type="button"
                        @click="$dispatch('meramot-open-location-modal')"
                        class="inline-flex items-center gap-2 rounded-2xl bg-[#e4fcd5] border border-[var(--border)] px-4 py-2.5 text-xs font-bold text-[#1f3a2d] hover:bg-[#c8e7c5] transition"
                    >
                        <i class="fa-solid fa-location-dot"></i>
                        <span x-text="lat && lng ? 'Update location on map' : 'Pin exact location on map'"></span>
                    </button>
                </div>

                <input type="hidden" name="latitude" :value="lat">
                <input type="hidden" name="longitude" :value="lng">
                <input type="hidden" name="gps_address" :value="gpsAddress">

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                            <span class="text-sm font-medium text-slate-700">Public Shop Address *</span>
                            <label class="inline-flex items-center gap-2 text-xs font-medium text-[#244229] cursor-pointer">
                                <input type="checkbox" name="same_as_gps_location" value="1" x-model="sameAsGps" @change="if(sameAsGps && gpsAddress) address = gpsAddress" class="accent-[#244229]">
                                <span>Same as pinned map location</span>
                            </label>
                        </div>
                        <input
                            type="text"
                            name="address"
                            x-model="address"
                            required
                            placeholder="Full public storefront address"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >
                    </div>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">City</span>
                        <input
                            type="text"
                            name="city"
                            x-model="city"
                            placeholder="Dhaka"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Area</span>
                        <input
                            type="text"
                            name="area"
                            x-model="area"
                            placeholder="Banani / Dhanmondi / Uttara"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >
                    </label>
                </div>
            </section>

            <!-- 3. Services Offered -->
            <section class="rounded-3xl bg-[#f6faf4] p-5 md:p-6">
                <h2 class="text-lg font-semibold text-[#244229]">Services offered</h2>
                <div class="mt-4 grid gap-3 md:grid-cols-3">
                    <label class="flex items-center gap-3 rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="courier_pickup" value="1" {{ old('courier_pickup', $profile->courier_pickup ?? false) ? 'checked' : '' }} class="h-4 w-4 accent-[#244229]">
                        <span>Courier pickup</span>
                    </label>
                    <label class="flex items-center gap-3 rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="in_shop_repair" value="1" {{ old('in_shop_repair', $profile->in_shop_repair ?? true) ? 'checked' : '' }} class="h-4 w-4 accent-[#244229]">
                        <span>In-shop repair</span>
                    </label>
                    <label class="flex items-center gap-3 rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm text-slate-700 cursor-pointer">
                        <input type="checkbox" name="spare_parts" value="1" {{ old('spare_parts', $profile->spare_parts ?? false) ? 'checked' : '' }} class="h-4 w-4 accent-[#244229]">
                        <span>Spare parts available</span>
                    </label>
                </div>
            </section>

            <!-- 4. Your Pricing -->
            <section class="rounded-3xl bg-[#f6faf4] p-5 md:p-6">
                <h2 class="text-lg font-semibold text-[#244229]">Your pricing</h2>
                <p class="mt-1 text-xs md:text-sm text-slate-600">
                    Configure your standard rates below. Workshops are published only with merchant-confirmed pricing.
                </p>

                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Inspection / diagnosis fee (৳) *</span>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            name="inspection_fee"
                            value="{{ old('inspection_fee', $primaryShop->inspection_fee ?? '') }}"
                            required
                            placeholder="e.g. 300"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Base labor fee (৳) *</span>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            name="base_labor_fee"
                            value="{{ old('base_labor_fee', $primaryShop->base_labor_fee ?? '') }}"
                            required
                            placeholder="e.g. 500"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Pickup fee (৳)</span>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            name="pickup_fee"
                            value="{{ old('pickup_fee', $primaryShop->pickup_fee ?? '') }}"
                            placeholder="e.g. 120"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >
                    </label>

                    <label class="block">
                        <span class="mb-1.5 block text-sm font-medium text-slate-700">Express service fee (৳)</span>
                        <input
                            type="number"
                            min="0"
                            step="1"
                            name="express_fee"
                            value="{{ old('express_fee', $primaryShop->express_fee ?? '') }}"
                            placeholder="e.g. 250"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >
                    </label>
                </div>
            </section>

            <!-- 5. Fixed-Price Services & Packages -->
            <section class="rounded-3xl bg-[#f6faf4] p-5 md:p-6">
                <div class="flex flex-wrap items-start justify-between gap-2">
                    <div class="min-w-0">
                        <h2 class="text-lg font-semibold text-[#244229]">Fixed-price services &amp; packages</h2>
                        <p class="mt-1 text-xs md:text-sm text-slate-600">
                            Publish services customers can book instantly at a set price. This is optional — without fixed-price items, customers can still request custom quotes.
                        </p>
                    </div>
                    <span class="shrink-0 rounded-full border border-[var(--border)] bg-white px-3 py-1 text-xs font-bold text-[#244229]" x-text="services.length + (services.length === 1 ? ' item' : ' items')"></span>
                </div>

                <!-- Suggestions derived from onboarding specialty tags -->
                <div class="mt-4 rounded-2xl border border-dashed border-[#b9d3b6] bg-white/70 p-4" x-show="suggestions.length > 0">
                    <p class="text-xs font-semibold uppercase tracking-wide text-[#58725f]">
                        <i class="fa-solid fa-wand-magic-sparkles mr-1"></i>
                        Suggested for your specialties
                    </p>
                    @if(!empty($onboardingTags))
                        <p class="mt-1 text-xs text-slate-500 break-words">
                            Based on the tags you selected during onboarding: {{ implode(', ', array_slice($onboardingTags, 0, 6)) }}{{ count($onboardingTags) > 6 ? '…' : '' }}
                        </p>
                    @else
                        <p class="mt-1 text-xs text-slate-500">Common services to get you started.</p>
                    @endif
                    <div class="mt-3 flex flex-wrap gap-2">
                        <template x-for="sug in suggestions" :key="sug.name">
                            <button
                                type="button"
                                x-show="!isAdded(sug.name)"
                                @click="useSuggestion(sug)"
                                class="inline-flex max-w-full items-center gap-1.5 rounded-full border border-[var(--border)] bg-white px-3 py-1.5 text-left text-xs font-medium text-slate-700 transition hover:border-[#244229] hover:bg-[var(--mint-100)]"
                                :title="'Typical market price around ৳' + Number(sug.hint).toLocaleString()"
                            >
                                <i class="fa-solid fa-plus text-[10px] text-[#244229]"></i>
                                <span class="break-words" x-text="sug.name"></span>
                            </button>
                        </template>
                        <span x-show="suggestions.every(s => isAdded(s.name))" class="text-xs italic text-slate-400">All suggestions have been added.</span>
                    </div>
                </div>

                <!-- Add / edit form -->
                <div class="mt-4 rounded-2xl border border-[var(--border)] bg-white p-4">
                    <p class="text-sm font-semibold text-slate-800" x-text="editingIndex !== null ? 'Edit service' : 'Add a service or package'"></p>
                    <div class="mt-3 grid gap-3 md:grid-cols-[minmax(0,1fr)_10rem]">
                        <div class="relative min-w-0">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Name *</label>
                            <input
                                type="text"
                                x-ref="serviceNameInput"
                                x-model="serviceName"
                                maxlength="150"
                                @focus="showSuggestions = true"
                                @blur="setTimeout(() => showSuggestions = false, 180)"
                                @keydown.enter.prevent="addService()"
                                placeholder="e.g. iPhone 13 Screen Replacement"
                                class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                            >
                            <div x-show="showSuggestions && serviceName.trim() !== '' && filteredSuggestions().length > 0" x-cloak class="absolute left-0 right-0 top-full z-20 mt-1 max-h-48 overflow-y-auto rounded-xl border border-[var(--border)] bg-white p-1 shadow-lg">
                                <template x-for="sug in filteredSuggestions()" :key="'dd-' + sug.name">
                                    <button
                                        type="button"
                                        @mousedown.prevent="useSuggestion(sug)"
                                        class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-[var(--mint-100)]"
                                        x-text="sug.name"
                                    ></button>
                                </template>
                            </div>
                        </div>

                        <div class="min-w-0">
                            <label class="mb-1 block text-xs font-medium text-slate-600">Fixed price (৳) *</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">৳</span>
                                <input
                                    type="number"
                                    min="1"
                                    step="1"
                                    x-ref="servicePriceInput"
                                    x-model="servicePrice"
                                    @keydown.enter.prevent="addService()"
                                    :placeholder="priceHint ? ('e.g. ' + priceHint) : 'Price'"
                                    class="w-full rounded-2xl border border-[var(--border)] bg-white py-3 pl-8 pr-3 text-sm outline-none focus:border-[#244229]"
                                >
                            </div>
                        </div>
                    </div>

                    <label class="mt-3 block">
                        <span class="mb-1 block text-xs font-medium text-slate-600">What's included (optional)</span>
                        <input
                            type="text"
                            x-model="serviceDescription"
                            maxlength="400"
                            @keydown.enter.prevent="addService()"
                            placeholder="e.g. Genuine part, 90-day warranty, same-day service"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >
                    </label>

                    <p x-show="serviceError" x-cloak class="mt-2 text-xs font-medium text-rose-600" x-text="serviceError"></p>
                    <p x-show="priceHint && !serviceError" x-cloak class="mt-2 text-xs text-slate-500">
                        Typical market price is around ৳<span x-text="priceHint"></span>. Set the price you actually charge.
                    </p>

                    <div class="mt-3 flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                        <button
                            type="button"
                            x-show="editingIndex !== null || serviceName || servicePrice || serviceDescription"
                            @click="resetServiceForm()"
                            class="h-11 rounded-2xl border border-[var(--border)] bg-white px-5 text-sm font-semibold text-slate-700 hover:bg-[var(--mint-100)]"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            @click="addService()"
                            class="h-11 rounded-2xl bg-[#244229] px-5 text-sm font-bold text-white transition hover:opacity-95"
                            x-text="editingIndex !== null ? 'Save changes' : '+ Add to list'"
                        ></button>
                    </div>
                </div>

                <!-- Published list -->
                <div x-show="services.length === 0" class="mt-4 rounded-2xl border border-dashed border-[var(--border)] bg-white/60 px-4 py-6 text-center">
                    <i class="fa-solid fa-box-open text-xl text-slate-300"></i>
                    <p class="mt-2 text-sm font-medium text-slate-600">No fixed-price services yet</p>
                    <p class="mt-1 text-xs text-slate-500">Pick a suggestion above or add your own to start receiving direct orders.</p>
                </div>

                <div x-show="services.length > 0" class="mt-4 space-y-2">
                    <template x-for="(svc, index) in services" :key="svc.name">
                        <div class="flex flex-col gap-2 rounded-xl border bg-white px-4 py-3 shadow-sm sm:flex-row sm:items-center sm:justify-between"
                             :class="editingIndex === index ? 'border-[#244229] ring-1 ring-[#244229]' : 'border-[var(--border)]'">
                            <input type="hidden" name="skill_tags[]" :value="svc.name + '|' + (svc.price || '') + (svc.description ? ('|' + svc.description) : '')">
                            <div class="min-w-0 flex-1">
                                <p class="break-words text-sm font-semibold text-slate-800" x-text="svc.name"></p>
                                <p x-show="svc.description" class="mt-0.5 break-words text-xs text-slate-500" x-text="svc.description"></p>
                            </div>
                            <div class="flex shrink-0 items-center justify-between gap-3 sm:justify-end sm:gap-4">
                                <span x-show="svc.price" class="text-sm font-bold text-[#244229]" x-text="'৳' + Number(svc.price).toLocaleString()"></span>
                                <span x-show="!svc.price" class="text-xs font-medium text-amber-700">Price required — edit to set</span>
                                <div class="flex items-center gap-1">
                                    <button type="button" @click="editService(index)" class="rounded-lg px-2 py-1 text-xs font-semibold text-slate-600 hover:bg-[var(--mint-100)]" aria-label="Edit service">
                                        <i class="fa-solid fa-pen text-[11px]"></i> Edit
                                    </button>
                                    <button type="button" @click="removeService(index)" class="rounded-lg px-2 py-1 text-xs font-semibold text-rose-600 hover:bg-rose-50" aria-label="Remove service">
                                        <i class="fa-solid fa-trash text-[11px]"></i> Remove
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-end">
                <a
                    href="{{ route('vendor.onboarding') }}"
                    class="rounded-2xl border border-[var(--border)] bg-white px-6 py-3 text-center text-sm font-semibold text-slate-700 hover:bg-[var(--mint-100)]"
                >
                    Back to onboarding
                </a>
                <button
                    type="submit"
                    class="rounded-2xl bg-[#244229] px-6 py-3 text-sm font-semibold text-white shadow transition hover:opacity-95"
                >
                    {{ $isEditMode ? 'Save vendor details' : 'Save shop setup & Go Live' }}
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

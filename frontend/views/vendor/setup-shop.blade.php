@extends('layouts.app')

@section('title', 'Set Up Your Shop | Meramot Vendor')

@section('content')
@php
    $isEditMode = (bool) optional($primaryShop)->setup_complete;
    $rawSpecs = old('skill_tags', optional($primaryShop)->specialties ?? $profile->specialties ?? []);
    if (!is_array($rawSpecs)) {
        $rawSpecs = [];
    }
    $initialServices = [];
    if (old('skill_tags')) {
        foreach ((array) old('skill_tags') as $item) {
            $trimmed = trim((string) $item);
            if ($trimmed === '') continue;
            if (str_contains($trimmed, '|')) {
                [$sName, $sPrice] = explode('|', $trimmed, 2);
                $initialServices[] = ['name' => trim($sName), 'price' => trim($sPrice)];
            } elseif (preg_match('/^(.*?)\s*\(\s*(?:৳|BDT|Tk\.?)?\s*([\d,]+(?:\.\d+)?)\s*\)\s*$/iu', $trimmed, $m)) {
                $initialServices[] = ['name' => trim($m[1]), 'price' => str_replace(',', '', $m[2])];
            } else {
                $initialServices[] = ['name' => $trimmed, 'price' => ''];
            }
        }
    } elseif ($primaryShop && $primaryShop->relationLoaded('services') && $primaryShop->services->isNotEmpty()) {
        foreach ($primaryShop->services->sortBy('sort_order') as $svc) {
            $initialServices[] = [
                'name' => $svc->name,
                'price' => $svc->base_price !== null ? (string) (float) $svc->base_price : '',
            ];
        }
    } else {
        foreach ($rawSpecs as $item) {
            $trimmed = trim((string) $item);
            if ($trimmed === '') continue;
            if (str_contains($trimmed, '|')) {
                [$sName, $sPrice] = explode('|', $trimmed, 2);
                $initialServices[] = ['name' => trim($sName), 'price' => trim($sPrice)];
            } elseif (preg_match('/^(.*?)\s*\(\s*(?:৳|BDT|Tk\.?)?\s*([\d,]+(?:\.\d+)?)\s*\)\s*$/iu', $trimmed, $m)) {
                $initialServices[] = ['name' => trim($m[1]), 'price' => str_replace(',', '', $m[2])];
            } else {
                $initialServices[] = ['name' => $trimmed, 'price' => ''];
            }
        }
    }

    $presetSuggestions = [
        'Apple products', 'Android phones', 'Laptop motherboard', 'Gaming PC',
        'Printer repair', 'Phone screen', 'MacBook repair', 'Desktop servicing',
        'Custom build PC', 'Battery replacement', 'Logic board repair',
        'Screen replacement', 'Water damage micro-soldering', 'Data recovery',
    ];
@endphp

<div
    class="mx-auto max-w-5xl px-4 py-8 md:px-6"
    x-data="{
        logoPreview: @js($primaryShop->logo_url ?? $profile->logo_url ?? ''),
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
        suggestions: @js($presetSuggestions),
        showSuggestions: false,
        handleLogoFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.logoPreview = URL.createObjectURL(file);
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
            const name = this.serviceName.trim();
            if (!name) return;
            if (!this.services.some(s => s.name.toLowerCase() === name.toLowerCase())) {
                this.services.push({ name, price: this.servicePrice.toString().trim() });
            }
            this.serviceName = '';
            this.servicePrice = '';
            this.showSuggestions = false;
        },
        removeService(name) {
            this.services = this.services.filter(s => s.name !== name);
        },
        filteredSuggestions() {
            const q = this.serviceName.toLowerCase().trim();
            return this.suggestions.filter(s =>
                (!q || s.toLowerCase().includes(q)) &&
                !this.services.some(ex => ex.name.toLowerCase() === s.toLowerCase())
            );
        }
    }"
    @meramot-location-changed.window="applyMapLocation($event.detail)"
>
    <div class="rounded-[2rem] border border-white/60 bg-white/90 p-6 md:p-8 shadow-2xl backdrop-blur">
        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[#58725f]">
            Vendor
        </p>
        <h1 class="mt-2 text-2xl md:text-3xl font-bold text-[#244229]">
            {{ $isEditMode ? 'Edit vendor details' : 'Set up your shop' }}
        </h1>
        <p class="mt-2 max-w-3xl text-sm text-slate-600">
            {{ $isEditMode
                ? 'Update your shop information, services, pricing, and skill tags here.'
                : 'Complete your shop profile before operating as a vendor. Your shop goes live publicly right after you set your own pricing, services, and business details.' }}
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

                <div class="mt-4 flex flex-col sm:flex-row items-center gap-4">
                    <div class="relative h-24 w-24 md:h-28 md:w-28 shrink-0 overflow-hidden rounded-[1.5rem] border-2 border-dashed border-[var(--border)] bg-[var(--mint-50)]">
                        <template x-if="logoPreview">
                            <div class="h-full w-full relative">
                                <img :src="logoPreview" alt="Shop Logo" class="h-full w-full object-cover">
                                <button type="button" @click="logoPreview = ''; $refs.setupLogo.value = ''" class="absolute right-1 top-1 rounded-full bg-black/60 px-2 py-0.5 text-xs text-white hover:bg-red-600">✕</button>
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
                        <p class="text-sm md:text-base font-semibold text-slate-800">Shop Logo (Optional)</p>
                        <p class="mt-1 max-w-sm text-xs text-slate-500">Upload a square shop logo or workshop badge.</p>
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
                        <div class="flex items-center justify-between mb-1.5">
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
                    Set your own business rates here. Shops only go public with vendor-confirmed rates.
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

            <!-- 5. Available Services & Skill Tags -->
            <section class="rounded-3xl bg-[#f6faf4] p-5 md:p-6">
                <h2 class="text-lg font-semibold text-[#244229]">Available services</h2>
                <p class="mt-1 text-xs md:text-sm text-slate-600">
                    List the services your shop provides and optionally set a base starting estimate (৳) for each.
                </p>

                <div class="mt-4 flex flex-col gap-3 md:flex-row md:items-start">
                    <div class="relative flex-1">
                        <input
                            type="text"
                            x-model="serviceName"
                            @focus="showSuggestions = true"
                            @blur="setTimeout(() => showSuggestions = false, 180)"
                            @keydown.enter.prevent="addService()"
                            placeholder="e.g. Logic board repair, Screen replacement..."
                            class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3 text-sm outline-none focus:border-[#244229]"
                        >

                        <div x-show="showSuggestions && filteredSuggestions().length > 0" x-cloak class="absolute left-0 right-0 top-full z-20 mt-1 max-h-48 overflow-y-auto rounded-xl border border-[var(--border)] bg-white p-1 shadow-lg">
                            <template x-for="sug in filteredSuggestions()" :key="sug">
                                <button
                                    type="button"
                                    @mousedown.prevent="serviceName = sug; showSuggestions = false"
                                    class="w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-[var(--mint-100)]"
                                    x-text="sug"
                                ></button>
                            </template>
                        </div>
                    </div>

                    <div class="relative w-full md:w-36 shrink-0">
                        <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400">৳</span>
                        <input
                            type="number"
                            min="0"
                            x-model="servicePrice"
                            @keydown.enter.prevent="addService()"
                            placeholder="Price"
                            class="w-full rounded-2xl border border-[var(--border)] bg-white py-3 pl-8 pr-4 text-sm outline-none focus:border-[#244229]"
                        >
                    </div>

                    <button
                        type="button"
                        @click="addService()"
                        class="flex h-12 px-5 shrink-0 items-center justify-center rounded-2xl bg-[#244229] text-white font-bold transition hover:opacity-95"
                    >
                        + Add
                    </button>
                </div>

                <div x-show="services.length > 0" class="mt-4 space-y-2">
                    <template x-for="svc in services" :key="svc.name">
                        <div class="flex items-center justify-between rounded-xl border border-[var(--border)] bg-white px-4 py-3 shadow-sm">
                            <input type="hidden" name="skill_tags[]" :value="svc.price ? (svc.name + '|' + svc.price) : svc.name">
                            <span class="text-sm font-medium text-slate-800" x-text="svc.name"></span>
                            <div class="flex items-center gap-4">
                                <span x-show="svc.price" class="text-sm font-bold text-[#244229]" x-text="'৳' + Number(svc.price).toLocaleString()"></span>
                                <span x-show="!svc.price" class="text-xs italic text-slate-400">Inspection quote</span>
                                <button type="button" @click="removeService(svc.name)" class="text-slate-400 hover:text-red-600 font-bold">✕</button>
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

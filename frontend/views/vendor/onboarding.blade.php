{{--
=============================================================================
FEATURE R11: Vendor Onboarding, Dual Shop Locations & Multi-Branch Opt-In
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
Architectural Layer: View (Vendor/Onboarding)
Controller: VendorOnboardingController@show, store, verify
Models Touched: VendorProfile, RepairShop
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Vendor Onboarding & Verification | Meramot')

@section('content')
<div
    class="mx-auto max-w-3xl px-4 py-6 md:px-6"
    x-data="{
        gpsAddress: @js(old('gps_address', $profile->gps_address ?? '')),
        shopAddress: @js(old('shop_address', $profile->shop_address ?? '')),
        sameAsGps: {{ old('same_as_gps_location', $profile->same_as_gps_location ?? false) ? 'true' : 'false' }},
        shopLat: @js(old('shop_latitude', $profile->shop_latitude ?? '')),
        shopLng: @js(old('shop_longitude', $profile->shop_longitude ?? '')),
        city: @js(old('city', $profile->city ?? 'Dhaka')),
        area: @js(old('area', $profile->area ?? 'Banani')),
        isBranched: {{ old('is_branched', $profile->is_branched ?? false) ? 'true' : 'false' }},
        branchName: @js(old('branch_name', optional(optional($profile)->shops)->first()->branch_name ?? '')),
        applyMapLocation(loc) {
            if (!loc) return;
            this.gpsAddress = loc.address || '';
            this.shopLat = typeof loc.lat === 'number' ? loc.lat.toFixed(6) : '';
            this.shopLng = typeof loc.lng === 'number' ? loc.lng.toFixed(6) : '';
            if (loc.city) this.city = loc.city;
            if (loc.area) this.area = loc.area;
            if (this.sameAsGps || !this.shopAddress) {
                if (this.sameAsGps) this.shopAddress = this.gpsAddress;
            }
        },
        syncSameAsGps() {
            if (this.sameAsGps && this.gpsAddress) {
                this.shopAddress = this.gpsAddress;
            }
        }
    }"
    @meramot-location-changed.window="applyMapLocation($event.detail)"
>
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm">
        <div class="mb-6 border-b border-[var(--border)] pb-4">
            <span class="dev-only-badge text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mb-2">
                Feature R11 • Farhan Tanvir [22299179]
            </span>
            <h1 class="text-2xl font-black text-[var(--foreground)]">Vendor Onboarding & Dual-Location Setup</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Register your electronic repair brand on Meramot with verified trade credentials, a private GPS map pin for distance calculation, and your public storefront address.
            </p>
        </div>

        @if(session('success'))
            <div class="mb-5 rounded-2xl border border-emerald-400 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900 flex items-center gap-2">
                <i class="fa-solid fa-circle-check text-emerald-700"></i>
                <span>{{ session('success') }}</span>
            </div>
        @endif

        @if($errors->any())
            <div class="mb-5 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-800 space-y-1">
                @foreach($errors->all() as $error)
                    <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="/vendor/onboarding" class="space-y-5 text-xs">
            @csrf

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

            <!-- ── Dual Shop Addresses Card (1. GPS Approximate vs 2. Vendor-Provided) ── -->
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/70 p-4 md:p-5 space-y-4">
                <div class="flex flex-wrap items-start justify-between gap-2 border-b border-[var(--border)] pb-3">
                    <div>
                        <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--accent-dark)]">Dual Shop Addressing</span>
                        <h3 class="text-sm font-black text-[var(--foreground)]">1. GPS Approximate Location &amp; 2. Public Storefront Address</h3>
                    </div>
                    <button
                        type="button"
                        @click="$dispatch('meramot-open-location-modal')"
                        class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] shadow-sm transition hover:opacity-90"
                    >
                        <i class="fa-solid fa-map-location-dot"></i>
                        <span>Pin GPS on OpenStreetMap</span>
                    </button>
                </div>

                <!-- Address 1: GPS Approximate Location -->
                <div class="space-y-1.5">
                    <label class="block font-bold text-[var(--foreground)]">
                        1. GPS Approximate Location <span class="font-normal text-[var(--muted-foreground)]">(Powers distance &amp; rider ETA — kept private unless synced below)</span>
                    </label>
                    <input
                        type="text"
                        name="gps_address"
                        x-model="gpsAddress"
                        @input="syncSameAsGps()"
                        placeholder="Click 'Pin GPS on OpenStreetMap' or type approximate road/area..."
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

                <!-- Option: Make 1 & 2 the Same -->
                <label class="flex items-center gap-2.5 cursor-pointer rounded-xl border border-[var(--border)] bg-[var(--card)] px-3.5 py-2.5 font-bold text-[var(--foreground)]">
                    <input
                        type="checkbox"
                        name="same_as_gps_location"
                        value="1"
                        x-model="sameAsGps"
                        @change="syncSameAsGps()"
                        class="h-4 w-4 accent-[#244229]"
                    >
                    <span>Use GPS approximate location as my Vendor-Provided Public Shop Address (1 &amp; 2 same)</span>
                </label>

                <!-- Address 2: Vendor-Provided Public Location -->
                <div class="space-y-2">
                    <label class="block font-bold text-[var(--foreground)]">
                        2. Vendor-Provided Public Location * <span class="font-normal text-[var(--muted-foreground)]">(Displayed publicly on shop cards &amp; profile)</span>
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
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mt-2 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-3.5">
                    <label class="flex items-center gap-2 font-medium text-[var(--foreground)]"><input type="checkbox" name="categories[]" value="Laptop" checked class="accent-[#244229]"> Laptop &amp; PC Motherboards</label>
                    <label class="flex items-center gap-2 font-medium text-[var(--foreground)]"><input type="checkbox" name="categories[]" value="Phone" checked class="accent-[#244229]"> Smartphone Hardware</label>
                    <label class="flex items-center gap-2 font-medium text-[var(--foreground)]"><input type="checkbox" name="categories[]" value="Display" class="accent-[#244229]"> Display Panel Replacement</label>
                    <label class="flex items-center gap-2 font-medium text-[var(--foreground)]"><input type="checkbox" name="categories[]" value="WaterDamage" class="accent-[#244229]"> Water Damage Micro-soldering</label>
                </div>
            </div>

            <button type="submit" class="w-full rounded-full bg-[var(--accent-dark)] hover:opacity-95 text-[var(--accent-foreground)] font-bold py-3 text-xs mt-4 shadow-md transition">
                Submit Application for Verification
            </button>
        </form>
    </div>
</div>
@endsection

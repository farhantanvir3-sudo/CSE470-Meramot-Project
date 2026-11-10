{{--
=============================================================================
FEATURE R11: Vendor Onboarding & Business Profile Verification Engine
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
Architectural Layer: View (Vendor/Onboarding)
Controller: VendorOnboardingController@show, store, verify
Models Touched: VendorProfile, Document
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Vendor Onboarding & Verification | Meramot')

@section('content')
<div
    class="mx-auto max-w-3xl px-4 py-6 md:px-6"
    x-data="{
        shopAddress: '{{ old('shop_address', '') }}',
        shopLat: '',
        shopLng: '',
        applyMapLocation(loc) {
            if (!loc) return;
            this.shopAddress = loc.address || '';
            this.shopLat = typeof loc.lat === 'number' ? loc.lat.toFixed(5) : '';
            this.shopLng = typeof loc.lng === 'number' ? loc.lng.toFixed(5) : '';
        }
    }"
    @meramot-location-changed.window="applyMapLocation($event.detail)"
>
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm">
        <div class="mb-6 border-b border-[var(--border)] pb-4">
            <span class="dev-only-badge text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mb-2">
                Feature R11 • Farhan Tanvir [22299179]
            </span>
            <h1 class="text-2xl font-black text-[var(--foreground)]">Vendor Onboarding & Profile Verification</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Register your electronic repair workshop on Meramot with verified trade credentials and an exact OpenStreetMap shop pin.
            </p>
        </div>

        <form method="POST" action="/vendor/onboarding" class="space-y-4 text-xs">
            @csrf
            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">Repair Shop Legal Business Name *</label>
                <input type="text" name="shop_name" placeholder="e.g. Dhaka Circuit Care" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] p-3 text-xs outline-none" required>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Trade License Number *</label>
                    <input type="text" name="business_license_no" placeholder="TRAD/DNCC/129482" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] p-3 text-xs outline-none" required>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Owner National NID *</label>
                    <input type="text" name="nid_number" placeholder="199219293120" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] p-3 text-xs outline-none" required>
                </div>
            </div>

            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-4 space-y-2.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label class="block font-bold text-[var(--foreground)]">Physical Shop Address & OpenStreetMap Pin *</label>
                    <button
                        type="button"
                        @click="$dispatch('meramot-open-location-modal')"
                        class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] shadow-sm transition hover:opacity-90"
                    >
                        <i class="fa-solid fa-map-location-dot"></i>
                        <span>Pin Shop on OpenStreetMap</span>
                    </button>
                </div>
                <input
                    type="text"
                    name="shop_address"
                    x-model="shopAddress"
                    placeholder="Shop 4B, Multiplan Center, Elephant Road, Dhaka"
                    class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] p-3 text-xs outline-none"
                    required
                >
                <template x-if="shopLat && shopLng">
                    <p class="text-[11px] font-mono text-[var(--accent-dark)]">
                        <i class="fa-solid fa-location-dot mr-1"></i>Pinned GPS: <span x-text="shopLat + ', ' + shopLng"></span>
                    </p>
                </template>
            </div>

            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">Service Categories Supported</label>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 mt-2 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-3.5">
                    <label class="flex items-center gap-2 font-medium text-[var(--foreground)]"><input type="checkbox" name="categories[]" value="Laptop" checked class="accent-[#244229]"> Laptop & PC Motherboards</label>
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

@extends('layouts.app')

@section('title', 'Post Device Repair Request | Meramot')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6 md:px-6">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>/</span>
        <span class="text-[var(--foreground)] font-semibold">Post Repair Request</span>
    </div>

    <div class="meramot-card p-6 md:p-8 shadow-sm">
        
        <div class="mb-6 border-b border-[var(--border)] pb-4">
            <span class="dev-only-badge text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mb-2">
                Feature R7 • Shanid Sajjatuz Islam [23201465]
            </span>
            <h1 class="text-2xl font-black text-[var(--foreground)]">
                Post an Electronic Device Repair Request
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Verified repair vendors across Dhaka will review your hardware symptoms and submit competitive itemized quotes.
            </p>
        </div>

        <!-- AI Assistant Banner Hook -->
        <div class="mb-6 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] p-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-[var(--card)] border border-[var(--border)] flex items-center justify-center text-amber-600 font-bold text-lg shrink-0">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div>
                    <h4 class="font-bold text-xs text-[var(--foreground)]">Unsure what is wrong with your device?</h4>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Our FastAPI Python diagnostic model can analyze symptoms in real-time.</p>
                </div>
            </div>
            <a href="/ai-consultant/chat" class="rounded-full bg-[var(--accent-dark)] text-white text-[11px] font-bold px-4 py-2 hover:opacity-90 transition shrink-0">
                Open AI Consultant
            </a>
        </div>

        <form method="POST" action="/requests" enctype="multipart/form-data" class="space-y-5 text-xs">
            @csrf

            <!-- Pre-selected Shop alert if came from shop profile -->
            @if(request('shop_id'))
                <input type="hidden" name="preferred_shop_id" value="{{ request('shop_id') }}">
                <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 text-xs flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-700"></i>
                    <span>Direct quote requested from shop partner <strong>#{{ request('shop_id') }}</strong></span>
                </div>
            @endif

            <!-- 1. Device Type & Brand -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Device Category *</label>
                    <select name="device_type" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 font-medium outline-none" required>
                        @php
                            $deviceTypes = [
                                'Laptop', 'Desktop', 'Mobile Phone', 'Tablet', 'Smartwatch', 'Fitness Tracker',
                                'Headphones/Earbuds', 'Smart TV', 'Monitor', 'Speaker', 'Printer', 'Scanner',
                                'Camera', 'Action Camera', 'Game Console', 'VR Headset', 'Router/Modem',
                                'Drone', 'Projector', 'Power Bank', 'UPS', 'E-Reader', 'External Storage',
                                'Keyboard', 'Streaming Device', 'Dash Cam', 'GPS Device', 'Smart Home Device', 'Other'
                            ];
                        @endphp
                        @foreach($deviceTypes as $dtype)
                            <option value="{{ $dtype }}">{{ $dtype }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Brand Name *</label>
                    <input type="text" name="brand" placeholder="e.g. Apple, Dell, Lenovo, Asus, Samsung, HP..." class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                </div>
            </div>

            <!-- 2. Model & Distinguishing Hardware Specs (From commit 695ec21 / 5d858e6) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-1">
                    <label class="block font-bold text-[var(--foreground)] mb-1">Model Name / Number *</label>
                    <input type="text" name="model_name" placeholder="e.g. MacBook Pro, ThinkPad T14, Galaxy S23" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Release / Purchase Year</label>
                    <input type="text" name="year" placeholder="e.g. 2021, 2023" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Screen Size / Chipset</label>
                    <input type="text" name="specs" placeholder="e.g. 14-inch OLED, M1 Pro, Core i7" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none">
                </div>
            </div>

            <!-- 3. Issue Category & Issue Title -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Primary Problem Category *</label>
                    <select name="issue_category" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 font-medium outline-none" required>
                        @php
                            $issueCategories = [
                                'Screen or display', 'Battery or charging', 'Keyboard or touchpad',
                                'Performance or overheating', 'Water damage', 'Speaker or microphone',
                                'Camera issue', 'Software or OS', 'Network or connectivity',
                                'Data recovery', 'Parts replacement', 'Other'
                            ];
                        @endphp
                        @foreach($issueCategories as $icat)
                            <option value="{{ $icat }}">{{ $icat }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Issue Headline *</label>
                    <input type="text" name="issue_title" placeholder="e.g. Broken display glass, does not turn on" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                </div>
            </div>

            <!-- 4. Detailed Description -->
            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">Detailed Symptoms & Device History *</label>
                <textarea name="issue_description" rows="4" placeholder="Describe symptoms: Was there any liquid spill? Does the fan spin? Did it drop? Any smoke or burning odor?" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 leading-relaxed outline-none" required></textarea>
            </div>

            <!-- 4.5. Photo Upload -->
            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">Upload Device Photos (Optional)</label>
                <p class="text-[11px] text-[var(--muted-foreground)] mb-2">
                    Attach up to 5 photos showing the issue, cracks, water damage indicators, or error screens (Max 5MB each)
                </p>
                <input 
                    type="file" 
                    name="photos[]" 
                    multiple 
                    accept="image/jpeg,image/jpg,image/png,image/webp" 
                    class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-xs file:font-semibold file:bg-[var(--accent-dark)] file:text-white hover:file:opacity-90"
                >
                <p class="text-[10px] text-[var(--muted-foreground)] mt-1">
                    Supported: JPG, PNG, WebP • Max 5 files • 5MB per file
                </p>
            </div>

            <!-- 5. Logistics & OpenStreetMap Pickup Location Picker -->
            <div
                x-data="{
                    addressMode: 'MAP',
                    pinnedLocation: null,
                    pickupAddress: '{{ old('pickup_address', auth()->user()->address ?? '') }}',
                    selectedArea: '{{ old('area', auth()->user()->area ?? 'Banani') }}',
                    applyMapLocation(loc) {
                        if (!loc) return;
                        this.pinnedLocation = loc;
                        this.addressMode = 'MAP';
                        this.pickupAddress = loc.address || '';
                        if (loc.area) {
                            const match = ['Banani', 'Gulshan', 'Dhanmondi', 'Mirpur', 'Uttara', 'Bashundhara', 'Mohammadpur', 'Farmgate', 'Tejgaon', 'Mohakhali', 'Badda', 'Rampura', 'New Market', 'Wari'].find(a => a.toLowerCase() === loc.area.toLowerCase());
                            if (match) this.selectedArea = match;
                        }
                    },
                    useSavedOrOpenMap() {
                        const stored = window.MeramotLocationPicker ? window.MeramotLocationPicker.parseStoredLocation() : null;
                        if (stored) {
                            this.applyMapLocation(stored);
                            window.MeramotToast?.show('Map location attached to this request.', 'success');
                        } else {
                            this.$dispatch('meramot-open-location-modal');
                        }
                    },
                    init() {
                        setTimeout(() => {
                            const stored = window.MeramotLocationPicker ? window.MeramotLocationPicker.parseStoredLocation() : null;
                            if (stored) {
                                this.applyMapLocation(stored);
                            } else {
                                this.addressMode = 'MANUAL';
                            }
                        }, 60);
                    }
                }"
                @meramot-location-changed.window="applyMapLocation($event.detail)"
                class="space-y-4 rounded-[1.5rem] border border-[var(--border)] bg-[var(--mint-100)]/60 p-4 md:p-5"
            >
                <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--muted-foreground)]">
                            Pickup location & logistics
                        </p>
                        <h3 class="text-sm font-black text-[var(--foreground)]">Where should our rider pick up the device?</h3>
                    </div>
                    <button
                        type="button"
                        @click="$dispatch('meramot-open-location-modal')"
                        class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-4 py-2 text-[11px] font-bold text-[var(--accent-foreground)] shadow-sm transition hover:opacity-90"
                    >
                        <i class="fa-solid fa-map-location-dot"></i>
                        <span>Pin on OpenStreetMap</span>
                    </button>
                </div>

                <!-- 3-Mode Selector Cards (Matches requests/new/page.tsx) -->
                <div class="grid grid-cols-1 gap-2 sm:grid-cols-3">
                    <button
                        type="button"
                        @click="useSavedOrOpenMap()"
                        class="rounded-2xl border p-3 text-left transition"
                        :class="addressMode === 'MAP' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/50 hover:border-[var(--accent-dark)]/50'"
                    >
                        <div class="font-bold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                            <i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i>
                            <span>Use map location</span>
                        </div>
                        <div class="mt-0.5 text-[11px] text-[var(--muted-foreground)]">Pinned OpenStreetMap coordinates</div>
                    </button>

                    <button
                        type="button"
                        @click="addressMode = 'MANUAL'"
                        class="rounded-2xl border p-3 text-left transition"
                        :class="addressMode === 'MANUAL' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/50 hover:border-[var(--accent-dark)]/50'"
                    >
                        <div class="font-bold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                            <i class="fa-solid fa-keyboard text-[var(--accent-dark)]"></i>
                            <span>Manual address</span>
                        </div>
                        <div class="mt-0.5 text-[11px] text-[var(--muted-foreground)]">Type custom pickup street</div>
                    </button>

                    <button
                        type="button"
                        @click="addressMode = 'PROFILE'; pickupAddress = @js(auth()->user()->address ?? 'House 12, Road 11, Banani, Dhaka')"
                        class="rounded-2xl border p-3 text-left transition"
                        :class="addressMode === 'PROFILE' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/50 hover:border-[var(--accent-dark)]/50'"
                    >
                        <div class="font-bold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                            <i class="fa-solid fa-user-check text-[var(--accent-dark)]"></i>
                            <span>Profile address</span>
                        </div>
                        <div class="mt-0.5 text-[11px] text-[var(--muted-foreground)]">Saved account info</div>
                    </button>
                </div>

                <!-- Selected from Map Preview Banner -->
                <template x-if="pinnedLocation">
                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--muted-foreground)]">
                                Selected from OpenStreetMap
                            </p>
                            <p class="mt-1 text-xs font-bold text-[var(--foreground)]" x-text="pinnedLocation.address"></p>
                            <p class="mt-0.5 text-[11px] text-[var(--muted-foreground)]">
                                <span x-text="[pinnedLocation.area, pinnedLocation.city].filter(Boolean).join(', ')"></span>
                                <template x-if="typeof pinnedLocation.lat === 'number' && typeof pinnedLocation.lng === 'number'">
                                    <span class="ml-1 font-mono text-[10px]" x-text="'(' + pinnedLocation.lat.toFixed(4) + ', ' + pinnedLocation.lng.toFixed(4) + ')'"></span>
                                </template>
                            </p>
                        </div>
                        <button
                            type="button"
                            @click="$dispatch('meramot-open-location-modal')"
                            class="shrink-0 rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1.5 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition"
                        >
                            Change Pin
                        </button>
                    </div>
                </template>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Preferred Logistics Mode *</label>
                        <select name="preferred_service_type" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 font-medium outline-none" required>
                            <option value="pickup_dropoff">Rider Doorstep Pickup & Return (OTP Verified)</option>
                            <option value="walk_in">Customer Walk-in to Technician Shop</option>
                            <option value="technician_home_visit">Technician On-Site Home Diagnostic</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Dhaka Area / Pickup Neighborhood *</label>
                        <select name="area" x-model="selectedArea" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 font-medium outline-none" required>
                            @foreach(['Banani', 'Gulshan', 'Dhanmondi', 'Mirpur', 'Uttara', 'Bashundhara', 'Mohammadpur', 'Farmgate', 'Tejgaon', 'Mohakhali', 'Badda', 'Rampura', 'New Market', 'Wari'] as $a)
                                <option value="{{ $a }}">{{ $a }}, Dhaka</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <!-- 6. Pickup Address & Expected Budget -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div class="md:col-span-2">
                        <label class="block font-bold text-[var(--foreground)] mb-1">Full Pickup Address</label>
                        <input type="text" name="pickup_address" x-model="pickupAddress" placeholder="House #, Road #, Block/Sector, Landmarks" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Estimated Budget (৳)</label>
                        <input type="number" name="estimated_budget" placeholder="e.g. 2500" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none">
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-[var(--border)] flex items-center justify-between">
                <a href="/shops" class="text-xs font-semibold text-[var(--muted-foreground)] hover:underline">
                    Cancel & Return
                </a>
                <button type="submit" class="rounded-full bg-[var(--accent-dark)] px-8 py-3 text-xs font-bold text-white shadow-lg hover:opacity-90 transition">
                    Publish Request to Bidding Marketplace &rarr;
                </button>
            </div>

        </form>

    </div>
</div>
@endsection

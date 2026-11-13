@extends('layouts.app')

@section('title', 'Post Device Repair Request | Meramot')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6 md:px-6">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>/</span>
        <a href="/requests" class="hover:underline">My Repair Requests</a>
        <span>/</span>
        <span class="text-[var(--foreground)] font-semibold">Post Repair Request</span>
    </div>

    <div class="meramot-card p-4 sm:p-6 md:p-8 shadow-sm">
        
        <div class="mb-6 border-b border-[var(--border)] pb-4">
            <span class="dev-only-badge text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mb-2">
                Feature R7 • Shanid Sajjatuz Islam [23201465]
            </span>
            <h1 class="text-2xl font-black text-[var(--foreground)]">
                Post an Electronic Device Repair Request
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Verified repair workshops across your city will review your hardware symptoms and submit competitive itemized estimates.
            </p>
        </div>

        <!-- AI Assistant Banner Hook -->
        <div class="mb-6 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5">
            <div class="flex items-start sm:items-center gap-3 min-w-0">
                <div class="h-10 w-10 rounded-2xl bg-[var(--card)] border border-[var(--border)] flex items-center justify-center text-amber-600 font-bold text-lg shrink-0">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div class="min-w-0">
                    <h4 class="font-bold text-xs text-[var(--foreground)]">Unsure what is wrong with your device?</h4>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Our AI fault diagnostics assistant can evaluate your symptoms and estimate expected repair costs in real time.</p>
                </div>
            </div>
            <a href="/ai-consultant/chat" class="w-full sm:w-auto text-center rounded-full bg-[var(--accent-dark)] text-white text-[11px] font-bold px-4 py-2 hover:opacity-90 transition shrink-0">
                Open AI Consultant
            </a>
        </div>

        @if($errors->any())
            <div class="mb-6 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-800 space-y-1 shadow-sm">
                @foreach($errors->all() as $err)
                    <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1.5"></i>{{ $err }}</div>
                @endforeach
            </div>
        @endif

        <form method="POST" action="/requests" enctype="multipart/form-data" class="space-y-5 text-xs">
            @csrf

            <!-- Pre-selected Shop alert if came from shop profile -->
            @if(request('shop_id'))
                <input type="hidden" name="preferred_shop_id" value="{{ request('shop_id') }}">
                <div class="p-3.5 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 text-xs flex flex-col sm:flex-row sm:items-center justify-between gap-2.5">
                    <div class="flex items-center gap-2">
                        <i class="fa-solid fa-circle-check text-emerald-700"></i>
                        <span>
                            Direct quote requested from
                            <strong>{{ $selectedShop->name ?? ('Shop Partner #' . request('shop_id')) }}</strong>
                            @if(!empty($selectedShop?->display_branch_tag))
                                <span class="ml-1 rounded-full bg-emerald-800 px-2 py-0.5 text-[10px] font-bold text-white">{{ $selectedShop->display_branch_tag }}</span>
                            @endif
                        </span>
                    </div>
                    @if(!empty($selectedShop))
                        <a href="{{ route('shops.show', $selectedShop->slug) }}" class="font-bold text-emerald-800 hover:underline shrink-0">View Shop</a>
                    @endif
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
                            <option value="{{ $dtype }}" {{ old('device_type') === $dtype ? 'selected' : '' }}>{{ $dtype }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Brand Name *</label>
                    <input type="text" name="brand" value="{{ old('brand') }}" placeholder="e.g. Apple, Dell, Lenovo, Asus, Samsung, HP..." class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                </div>
            </div>

            <!-- 2. Model & Distinguishing Hardware Specs (From commit 695ec21 / 5d858e6) -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="sm:col-span-1">
                    <label class="block font-bold text-[var(--foreground)] mb-1">Model Name / Number *</label>
                    <input type="text" name="model_name" value="{{ old('model_name') }}" placeholder="e.g. MacBook Pro, ThinkPad T14, Galaxy S23" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Release / Purchase Year</label>
                    <input type="text" name="year" value="{{ old('year') }}" placeholder="e.g. 2021, 2023" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none">
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Screen Size / Chipset</label>
                    <input type="text" name="specs" value="{{ old('specs') }}" placeholder="e.g. 14-inch OLED, M1 Pro, Core i7" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none">
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
                            <option value="{{ $icat }}" {{ old('issue_category') === $icat ? 'selected' : '' }}>{{ $icat }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Issue Headline *</label>
                    <input type="text" name="issue_title" value="{{ old('issue_title', $prefilledIssue ?? request('issue', '')) }}" placeholder="e.g. Broken display glass, does not turn on" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                </div>
            </div>

            <!-- 4. Detailed Description -->
            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">Detailed Symptoms & Device History *</label>
                <textarea name="issue_description" rows="4" placeholder="Describe symptoms: Was there any liquid spill? Does the fan spin? Did it drop? Any smoke or burning odor?" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 leading-relaxed outline-none" required>{{ old('issue_description') }}</textarea>
            </div>

            <!-- 4.5. Interactive Dashed Dropzone Photo Upload -->
            <div
                x-data="{
                    files: [],
                    dragging: false,
                    formatSize(bytes) {
                        const kb = bytes / 1024;
                        return kb >= 1024 ? (kb / 1024).toFixed(2) + ' MB' : Math.round(kb) + ' KB';
                    },
                    syncInputFiles() {
                        const dt = new DataTransfer();
                        this.files.forEach(item => dt.items.add(item.raw));
                        this.$refs.photosInput.files = dt.files;
                    },
                    addFiles(fileList) {
                        if (!fileList || !fileList.length) return;
                        Array.from(fileList).forEach(file => {
                            if (this.files.length >= 5) return;
                            if (!file.type.startsWith('image/')) return;
                            const ext = (file.type.split('/')[1] || 'IMG').toUpperCase();
                            this.files.push({
                                id: Math.random().toString(36).slice(2),
                                raw: file,
                                name: file.name,
                                size: this.formatSize(file.size),
                                type: ext,
                                url: URL.createObjectURL(file)
                            });
                        });
                        this.syncInputFiles();
                    },
                    removePhoto(idx) {
                        const removed = this.files[idx];
                        if (removed && removed.url) URL.revokeObjectURL(removed.url);
                        this.files.splice(idx, 1);
                        this.syncInputFiles();
                    },
                    clearAll() {
                        this.files.forEach(f => f.url && URL.revokeObjectURL(f.url));
                        this.files = [];
                        this.$refs.photosInput.value = '';
                    }
                }"
            >
                <div class="mb-1.5 flex items-center justify-between">
                    <div>
                        <label class="block font-bold text-[var(--foreground)]">
                            <i class="fa-solid fa-images mr-1.5 text-[var(--accent-dark)]"></i>Upload Device Photos (Optional)
                        </label>
                        <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                            Attach up to 5 photos showing hardware symptoms, cracks, liquid indicators, or error screens (Max 5MB each)
                        </p>
                    </div>
                    <template x-if="files.length > 0">
                        <div class="flex items-center gap-2">
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-800" x-text="files.length + ' / 5 selected'"></span>
                            <button type="button" @click="clearAll()" class="text-[11px] font-bold text-rose-700 hover:underline">Clear all</button>
                        </div>
                    </template>
                </div>

                <input
                    x-ref="photosInput"
                    type="file"
                    name="photos[]"
                    multiple
                    accept="image/jpeg,image/jpg,image/png,image/webp"
                    class="hidden"
                    @change="addFiles($event.target.files)"
                >

                <!-- Interactive Dashed Dropzone Card -->
                <div
                    @dragover.prevent="dragging = true"
                    @dragleave.prevent="dragging = false"
                    @drop.prevent="dragging = false; addFiles($event.dataTransfer.files)"
                    :class="dragging ? 'border-[var(--accent-dark)] bg-[var(--mint-200)] scale-[1.005]' : (files.length > 0 ? 'border-emerald-600/60 bg-[var(--card)]' : 'border-[var(--border)] bg-[var(--mint-100)]/60 hover:border-[var(--accent-dark)] hover:bg-[var(--card)]')"
                    class="rounded-2xl border-2 border-dashed p-4 transition-all duration-200 shadow-sm space-y-3.5"
                >
                    <div
                        @click="$refs.photosInput.click()"
                        class="group flex cursor-pointer flex-col items-center justify-center py-3 text-center"
                    >
                        <div class="mb-2 flex h-11 w-11 items-center justify-center rounded-2xl border border-[#c8dac2] bg-white text-[#163625] shadow-sm transition-all duration-200 group-hover:scale-105 group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                            <i class="fa-solid fa-cloud-arrow-up text-base"></i>
                        </div>
                        <p class="text-xs font-extrabold text-[var(--foreground)]">
                            Click to choose device photos <span class="font-normal text-[var(--muted-foreground)]">or drag &amp; drop here</span>
                        </p>
                        <p class="mt-0.5 text-[10px] text-[var(--muted-foreground)]">
                            Supported: JPG, PNG, WebP &bull; Up to 5 photos &bull; Max 5MB per photo
                        </p>
                    </div>

                    <!-- Live Image Thumbnail Preview Grid -->
                    <template x-if="files.length > 0">
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3 pt-2 border-t border-[#e2ece0]">
                            <template x-for="(item, idx) in files" :key="item.id">
                                <div class="group relative overflow-hidden rounded-xl border border-[#c8dac2] bg-[#f8fbf6] p-1.5 shadow-sm">
                                    <!-- Top-right ✕ Remove Button -->
                                    <button
                                        type="button"
                                        @click.stop="removePhoto(idx)"
                                        title="Remove photo"
                                        class="absolute right-2 top-2 z-10 inline-flex h-6 w-6 items-center justify-center rounded-full bg-rose-600 text-[11px] font-bold text-white shadow transition hover:scale-105 hover:bg-rose-700"
                                    >
                                        ✕
                                    </button>
                                    <div class="h-24 w-full overflow-hidden rounded-lg bg-white flex items-center justify-center">
                                        <img :src="item.url" :alt="item.name" class="h-full w-full object-cover">
                                    </div>
                                    <div class="mt-1.5 flex items-center justify-between gap-1 px-0.5">
                                        <span class="shrink-0 rounded bg-[#eef5ea] border border-[#c8dac2] px-1.5 py-0.5 text-[9px] font-extrabold text-[#163625]" x-text="item.type"></span>
                                        <span class="truncate text-[10px] font-semibold text-[#163625]" x-text="item.name"></span>
                                    </div>
                                    <div class="px-0.5 text-[9px] text-[#5e6d64]" x-text="item.size"></div>
                                </div>
                            </template>
                        </div>
                    </template>
                </div>
            </div>

            <!-- 5. Logistics & OpenStreetMap Pickup Location Picker -->
            <div
                x-data="{
                    addressMode: 'MAP',
                    pinnedLocation: null,
                    pickupAddress: @js(old('pickup_address', auth()->user()->address ?? '')),
                    selectedArea: @js(old('area', auth()->user()->area ?? 'Banani')),
                    selectedCity: @js(old('city', 'Dhaka')),
                    applyMapLocation(loc) {
                        if (!loc) return;
                        this.pinnedLocation = loc;
                        this.addressMode = 'MAP';
                        this.pickupAddress = loc.address || '';
                        if (loc.area) this.selectedArea = loc.area;
                        if (loc.city) this.selectedCity = loc.city;
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
                        <span>Pin on Interactive Map</span>
                    </button>
                </div>

                <!-- 3-Mode Selector Cards -->
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
                        <div class="mt-0.5 text-[11px] text-[var(--muted-foreground)]">Pinned map coordinates</div>
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
                        @click="addressMode = 'PROFILE'; pickupAddress = @js(auth()->user()->address ?? ''); selectedArea = @js(auth()->user()->area ?? 'Banani'); selectedCity = @js(auth()->user()->city ?? 'Dhaka')"
                        class="rounded-2xl border p-3 text-left transition"
                        :class="addressMode === 'PROFILE' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/50 hover:border-[var(--accent-dark)]/50'"
                    >
                        <div class="font-bold text-xs text-[var(--foreground)] flex items-center gap-1.5">
                            <i class="fa-solid fa-user-check text-[var(--accent-dark)]"></i>
                            <span>Profile address</span>
                        </div>
                        <div class="mt-0.5 text-[11px] text-[var(--muted-foreground)]">
                            {{ !empty(auth()->user()?->address) ? Str::limit(auth()->user()->address, 26) : 'Saved account delivery address' }}
                        </div>
                    </button>
                </div>

                <!-- Selected from Map Preview Banner -->
                <template x-if="pinnedLocation">
                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--muted-foreground)]">
                                Selected Map Location
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
                        <label class="block font-bold text-[var(--foreground)] mb-1">Area / Pickup Neighborhood *</label>
                        <input
                            type="text"
                            name="area"
                            list="meramot-bangladesh-areas"
                            x-model="selectedArea"
                            placeholder="Banani, Hatirpool, Agrabad, Zindabazar..."
                            class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 font-medium outline-none"
                            required
                        >
                        <input type="hidden" name="city" :value="selectedCity">
                        <datalist id="meramot-bangladesh-areas">
                            @foreach([
                                'Banani', 'Gulshan', 'Dhanmondi', 'Hatirpool', 'Mirpur', 'Uttara', 'Bashundhara',
                                'Mohammadpur', 'Farmgate', 'Tejgaon', 'Mohakhali', 'Badda', 'Rampura', 'New Market', 'Wari',
                                'Agrabad, Chattogram', 'GEC Circle, Chattogram', 'Zindabazar, Sylhet', 'Joydebpur, Gazipur',
                                'Shaheb Bazar, Rajshahi', 'Shib Bari, Khulna', 'Satmatha, Bogura', 'Ganginarpar, Mymensingh'
                            ] as $a)
                                <option value="{{ $a }}"></option>
                            @endforeach
                        </datalist>
                    </div>
                </div>

                <!-- 6. Contact Phone, Pickup Address & Expected Budget -->
                @if($errors->has('contact_phone'))
                    <div class="rounded-2xl border border-rose-300 bg-rose-50 px-3.5 py-2.5 text-xs font-semibold text-rose-700">
                        {{ $errors->first('contact_phone') }}
                    </div>
                @endif

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-bold text-[var(--foreground)]">Contact Phone *</label>
                            @auth
                                @if(empty(auth()->user()->phone))
                                    <a href="/profile?edit=1" class="text-[10px] font-bold text-[var(--accent-dark)] hover:underline">Save in Profile</a>
                                @endif
                            @endauth
                        </div>
                        <input
                            type="tel"
                            name="contact_phone"
                            value="{{ old('contact_phone', auth()->user()->phone ?? '') }}"
                            placeholder="01XXXXXXXXX"
                            @auth required @endauth
                            class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none"
                        >
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Full Pickup / Delivery Address</label>
                        <input type="text" name="pickup_address" x-model="pickupAddress" placeholder="House #, Road #, Block/Sector, Landmarks" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none">
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Estimated Budget (৳)</label>
                        <input type="number" name="estimated_budget" value="{{ old('estimated_budget') }}" placeholder="e.g. 2500" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none">
                    </div>
                </div>
            </div>

            <!-- Submit Button -->
            <div class="pt-4 border-t border-[var(--border)] flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3">
                <a href="/shops" class="w-full sm:w-auto text-center text-xs font-semibold text-[var(--muted-foreground)] hover:underline py-2">
                    Cancel & Return
                </a>
                <button type="submit" class="w-full sm:w-auto text-center rounded-full bg-[var(--accent-dark)] px-8 py-3 text-xs font-bold text-white shadow-lg hover:opacity-90 transition">
                    Publish Request to Bidding Marketplace &rarr;
                </button>
            </div>

        </form>

    </div>
</div>
@endsection

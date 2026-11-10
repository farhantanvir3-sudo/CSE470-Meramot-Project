@extends('layouts.app')

@section('title', 'Direct-Order Repair Cart | Meramot')

{{-- ==========================================================================
     FEATURE R8: Unified Direct-Order & Quickmatch Cart Wizard
     Assigned Member: Shanid Sajjatuz Islam [23201465]
     Direct-Order Flow Merge: Farhan Tanvir [22299179]
     Client JS Relocated To: frontend/js/marketplace-client.js & geolocation-helper.js
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 md:px-6" x-data="{
    scheduleType: '{{ old('schedule_type', 'NOW') }}',
    deliveryType: '{{ old('delivery_type', 'REGULAR') }}',
    paymentMethod: '{{ old('payment_method', 'CASH') }}',
    addressMode: 'MAP',
    pinnedLocation: null,
    pickupAddress: @js(old('address', auth()->user()->address ?? '')),
    selectedArea: '{{ old('area', auth()->user()->area ?? 'Banani') }}',
    subtotal: {{ (float) ($total ?? 0) }},
    serviceCharge: {{ (float) ($serviceCharge ?? 0) }},
    get deliveryFee() {
        return this.deliveryType === 'EXPRESS' ? 150 : 80;
    },
    get grandTotal() {
        return this.subtotal + this.serviceCharge + (this.subtotal > 0 ? this.deliveryFee : 0);
    },
    applyMapLocation(loc) {
        if (!loc) return;
        this.pinnedLocation = loc;
        this.addressMode = 'MAP';
        this.pickupAddress = loc.address || '';
        if (loc.area) {
            const match = ['Banani', 'Gulshan', 'Dhanmondi', 'Mirpur', 'Uttara', 'Bashundhara', 'Mohammadpur', 'Farmgate', 'Tejgaon', 'Mohakhali', 'Badda', 'Wari'].find(a => a.toLowerCase() === loc.area.toLowerCase());
            if (match) this.selectedArea = match;
        }
    },
    useSavedOrOpenMap() {
        const stored = window.MeramotLocationPicker ? window.MeramotLocationPicker.parseStoredLocation() : null;
        if (stored) {
            this.applyMapLocation(stored);
            window.MeramotToast?.show('Map location attached to this order.', 'success');
        } else {
            this.$dispatch('meramot-open-location-modal');
        }
    },
    async fillGpsLocation() {
        if (!window.MeramotGeolocation || !window.MeramotLocationPicker) return;
        try {
            const pos = await window.MeramotGeolocation.getCurrentPosition();
            const resolved = await window.MeramotLocationPicker.reverseGeocode(pos.lat, pos.lng, 'gps');
            const saved = window.MeramotLocationPicker.saveLocation(resolved);
            this.applyMapLocation(saved);
            window.MeramotToast?.show('GPS coordinates reverse-geocoded & attached!', 'success');
        } catch (err) {
            window.MeramotToast?.show(err.message || 'Unable to read GPS coordinates.', 'warning');
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
}" @meramot-location-changed.window="applyMapLocation($event.detail)">

    <!-- Header -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
                <a href="/" class="hover:underline">Home</a>
                <span>›</span>
                <a href="{{ route('packages.catalog') }}" class="hover:underline">Packages & Shops</a>
                <span>›</span>
                <span class="text-[var(--foreground)] font-semibold">Direct-Order Cart</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">
                Direct-Order Service Cart
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R8 • Shanid Sajjatuz Islam [23201465]
                </span>
                <span>Configure doorstep courier pickup, schedule, and escrow checkout — dispatches directly to the workshop's Direct Orders queue</span>
            </p>
        </div>

        <div class="flex items-center gap-2">
            <a href="/shops" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                Browse Shops
            </a>
            <a href="{{ route('packages.catalog') }}" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)] transition">
                + Add More Packages
            </a>
        </div>
    </div>

    @if(empty($cart))
        <!-- Empty Cart State -->
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-12 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)]">
                <i class="fa-solid fa-cart-shopping text-2xl"></i>
            </div>
            <h2 class="text-xl font-black text-[var(--foreground)]">Your Direct-Order Cart is empty</h2>
            <p class="mt-2 text-xs text-[var(--muted-foreground)] max-w-md mx-auto">
                Add services directly from any verified Dhaka repair shop profile, or pick a fixed-price service package to Quickmatch with a top-rated workshop.
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="/shops" class="rounded-full bg-[var(--accent-dark)] px-6 py-3 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                    Explore Verified Shops
                </a>
                <a href="{{ route('packages.catalog') }}" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-6 py-3 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                    Browse Fixed Packages
                </a>
            </div>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1.65fr)_380px] items-start">

            <!-- ── Left Column: Workshop Target + Cart Items + Direct-Order Logistics Form ── -->
            <div class="space-y-6 min-w-0">

                <!-- 1. Target Repair Workshop Banner (Shop-Scoped Direct Order) -->
                @if(isset($activeShop) && $activeShop)
                    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5">
                                <div class="h-12 w-12 rounded-2xl bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center font-black text-lg shrink-0">
                                    {{ strtoupper(substr($activeShop->name, 0, 1)) }}
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)]">
                                            {{ !empty($shopLocked) ? 'Selected Workshop' : '⚡ Quickmatched Workshop' }}
                                        </span>
                                        <span class="text-xs font-bold text-amber-700">★ {{ number_format($activeShop->rating, 1) }}</span>
                                    </div>
                                    <div class="flex flex-wrap items-center gap-2 mt-0.5">
                                        <h3 class="text-base font-black text-[var(--foreground)]">{{ $activeShop->name }}</h3>
                                        @if($activeShop->display_branch_tag)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)]">
                                                <i class="fa-solid fa-code-branch text-[9px]"></i>
                                                <span>{{ $activeShop->display_branch_tag }}</span>
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-[var(--muted-foreground)]">{{ $activeShop->public_address }}</p>
                                </div>
                            </div>

                            @if(empty($shopLocked) && isset($verifiedShops) && count($verifiedShops) > 1)
                                <form action="{{ route('cart.shop') }}" method="POST" class="flex items-center gap-2">
                                    @csrf
                                    <select name="shop_id" onchange="this.form.submit()" class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3 py-2 text-xs font-semibold text-[var(--foreground)] outline-none">
                                        @foreach($verifiedShops as $vShop)
                                            <option value="{{ $vShop->id }}" {{ $activeShop->id === $vShop->id ? 'selected' : '' }}>
                                                {{ $vShop->name }}{{ $vShop->display_branch_tag ? ' [' . $vShop->display_branch_tag . ']' : ' (' . $vShop->area . ')' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            @endif
                        </div>
                    </div>
                @endif

                <!-- 2. Selected Repair Services List -->
                <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                    <div class="mb-4 flex items-center justify-between border-b border-[var(--border)] pb-3">
                        <h2 class="text-base font-black text-[var(--foreground)]">
                            Selected Repair Services ({{ count($cart) }})
                        </h2>
                        <form action="{{ route('cart.clear') }}" method="POST">
                            @csrf
                            <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline">
                                Clear Cart
                            </button>
                        </form>
                    </div>

                    <div class="space-y-3">
                        @foreach($cart as $id => $item)
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="rounded-full bg-[var(--card)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                                            {{ $item['category'] ?? 'Repair Service' }}
                                        </span>
                                        @if(!empty($item['duration']))
                                            <span class="text-[11px] text-[var(--muted-foreground)]">
                                                <i class="fa-regular fa-clock mr-1"></i>{{ $item['duration'] }}h est.
                                            </span>
                                        @endif
                                    </div>
                                    <h3 class="text-sm font-bold text-[var(--foreground)]">{{ $item['title'] }}</h3>
                                    <p class="mt-1 text-sm font-black text-[var(--accent-dark)]">
                                        ৳{{ number_format($item['price']) }} × {{ $item['quantity'] }}
                                        <span class="ml-1 text-xs font-semibold text-[var(--muted-foreground)]">
                                            (৳{{ number_format($item['price'] * $item['quantity']) }})
                                        </span>
                                    </p>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                                    <div class="inline-flex items-center rounded-full border border-[var(--border)] bg-[var(--card)] p-1">
                                        <button
                                            type="button"
                                            onclick="updateQuantity(@js((string) $id), {{ (int) $item['quantity'] - 1 }})"
                                            class="h-7 w-7 rounded-full text-xs font-black text-[var(--foreground)] hover:bg-[var(--mint-200)] transition"
                                        >−</button>
                                        <span class="w-8 text-center text-xs font-bold text-[var(--foreground)]">{{ $item['quantity'] }}</span>
                                        <button
                                            type="button"
                                            onclick="updateQuantity(@js((string) $id), {{ (int) $item['quantity'] + 1 }})"
                                            class="h-7 w-7 rounded-full text-xs font-black text-[var(--foreground)] hover:bg-[var(--mint-200)] transition"
                                        >+</button>
                                    </div>

                                    <button
                                        type="button"
                                        onclick="removeFromCart(@js((string) $id))"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-rose-100 text-rose-700 hover:bg-rose-200 transition"
                                        title="Remove service"
                                    >
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 3. Direct-Order Pickup, Schedule & Address Configuration -->
                <form id="direct-order-form" method="POST" action="{{ route('cart.place') }}" class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm space-y-5 text-xs">
                    @csrf
                    @if(isset($activeShop) && $activeShop)
                        <input type="hidden" name="shop_id" value="{{ $activeShop->id }}">
                    @endif

                    <h2 class="text-base font-black text-[var(--foreground)] border-b border-[var(--border)] pb-3">
                        Pickup Schedule, Courier Tier & Dhaka Address
                    </h2>

                    <!-- Schedule Type: NOW vs LATER -->
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-2">1. Doorstep Pickup Schedule *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="cursor-pointer rounded-2xl border-2 p-3.5 transition flex items-start gap-3" :class="scheduleType === 'NOW' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <input type="radio" name="schedule_type" value="NOW" x-model="scheduleType" class="mt-0.5 accent-[#244229]">
                                <div>
                                    <span class="font-bold text-[var(--foreground)] block">Dispatch Rider Now</span>
                                    <span class="text-[11px] text-[var(--muted-foreground)]">Immediate pickup once workshop accepts order</span>
                                </div>
                            </label>
                            <label class="cursor-pointer rounded-2xl border-2 p-3.5 transition flex items-start gap-3" :class="scheduleType === 'LATER' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <input type="radio" name="schedule_type" value="LATER" x-model="scheduleType" class="mt-0.5 accent-[#244229]">
                                <div>
                                    <span class="font-bold text-[var(--foreground)] block">Schedule for Later</span>
                                    <span class="text-[11px] text-[var(--muted-foreground)]">Pick a convenient pickup date & time window</span>
                                </div>
                            </label>
                        </div>
                        <div x-show="scheduleType === 'LATER'" x-cloak class="mt-3">
                            <input
                                type="datetime-local"
                                name="scheduled_at"
                                value="{{ old('scheduled_at') }}"
                                class="w-full sm:w-72 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none"
                            >
                            @error('scheduled_at')
                                <p class="mt-1 text-rose-700 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <!-- Delivery Type: REGULAR (৳80) vs EXPRESS (৳150) -->
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-2">2. Two-Way Courier Handling Tier *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="cursor-pointer rounded-2xl border-2 p-3.5 transition flex items-center justify-between gap-3" :class="deliveryType === 'REGULAR' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <div class="flex items-start gap-2.5">
                                    <input type="radio" name="delivery_type" value="REGULAR" x-model="deliveryType" class="mt-0.5 accent-[#244229]">
                                    <div>
                                        <span class="font-bold text-[var(--foreground)] block">Regular Secure Rider</span>
                                        <span class="text-[11px] text-[var(--muted-foreground)]">Standard anti-static pouch pickup</span>
                                    </div>
                                </div>
                                <span class="font-black text-sm text-[var(--accent-dark)]">৳80</span>
                            </label>

                            <label class="cursor-pointer rounded-2xl border-2 p-3.5 transition flex items-center justify-between gap-3" :class="deliveryType === 'EXPRESS' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <div class="flex items-start gap-2.5">
                                    <input type="radio" name="delivery_type" value="EXPRESS" x-model="deliveryType" class="mt-0.5 accent-[#244229]">
                                    <div>
                                        <span class="font-bold text-[var(--foreground)] block">Express Priority Rider</span>
                                        <span class="text-[11px] text-[var(--muted-foreground)]">Direct non-stop workshop dispatch</span>
                                    </div>
                                </div>
                                <span class="font-black text-sm text-[var(--accent-dark)]">৳150</span>
                            </label>
                        </div>
                    </div>

                    <!-- Contact Phone & Dhaka Hub -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">3. Contact Phone (OTP Verification) *</label>
                            <input
                                type="tel"
                                name="phone"
                                value="{{ old('phone', auth()->user()->phone ?? '01712345678') }}"
                                required
                                placeholder="017XXXXXXXX"
                                class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs text-[var(--foreground)] outline-none"
                            >
                        </div>
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Dhaka Pickup Neighborhood</label>
                            <select name="area" x-model="selectedArea" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none">
                                @foreach(['Banani', 'Gulshan', 'Dhanmondi', 'Mirpur', 'Uttara', 'Bashundhara', 'Mohammadpur', 'Farmgate', 'Tejgaon', 'Mohakhali', 'Badda', 'Wari'] as $hub)
                                    <option value="{{ $hub }}">{{ $hub }}, Dhaka</option>
                                @endforeach
                            </select>
                        </div>
                    </div>

                    <!-- Pickup Address + OpenStreetMap Location Mode Selector (Matches cart/page.tsx) -->
                    <div class="space-y-3 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="cart-pickup-address" class="font-bold text-[var(--foreground)]">4. Pickup Location (OpenStreetMap / Manual / Profile) *</label>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="$dispatch('meramot-open-location-modal')"
                                    class="inline-flex items-center gap-1 rounded-full bg-[var(--accent-dark)] px-3 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition"
                                >
                                    <i class="fa-solid fa-map-location-dot"></i> Pin on Map
                                </button>
                                <button
                                    type="button"
                                    @click="fillGpsLocation()"
                                    class="inline-flex items-center gap-1 rounded-full border border-[var(--border)] bg-[var(--card)] px-3 py-1.5 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-50)] transition"
                                >
                                    <i class="fa-solid fa-location-crosshairs"></i> Use GPS
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <button
                                type="button"
                                @click="useSavedOrOpenMap()"
                                class="rounded-xl border p-2.5 text-left transition"
                                :class="addressMode === 'MAP' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/40 hover:border-[var(--accent-dark)]/50'"
                            >
                                <div class="font-bold text-xs text-[var(--foreground)]">Use map location</div>
                                <div class="text-[10px] text-[var(--muted-foreground)]">Pulls from navbar map pin</div>
                            </button>
                            <button
                                type="button"
                                @click="addressMode = 'MANUAL'"
                                class="rounded-xl border p-2.5 text-left transition"
                                :class="addressMode === 'MANUAL' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/40 hover:border-[var(--accent-dark)]/50'"
                            >
                                <div class="font-bold text-xs text-[var(--foreground)]">Manual address</div>
                                <div class="text-[10px] text-[var(--muted-foreground)]">Type a pickup address</div>
                            </button>
                            <button
                                type="button"
                                @click="addressMode = 'PROFILE'; pickupAddress = @js(auth()->user()->address ?? 'House 12, Road 11, Banani, Dhaka')"
                                class="rounded-xl border p-2.5 text-left transition"
                                :class="addressMode === 'PROFILE' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/40 hover:border-[var(--accent-dark)]/50'"
                            >
                                <div class="font-bold text-xs text-[var(--foreground)]">Profile address</div>
                                <div class="text-[10px] text-[var(--muted-foreground)]">Use saved account info</div>
                            </button>
                        </div>

                        <template x-if="pinnedLocation">
                            <div class="rounded-xl border border-[var(--border)] bg-[var(--card)] p-3 text-xs">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)] block">Selected from OpenStreetMap</span>
                                <span class="font-bold text-[var(--foreground)] mt-0.5 block" x-text="pinnedLocation.address"></span>
                            </div>
                        </template>

                        <textarea
                            id="cart-pickup-address"
                            name="address"
                            x-model="pickupAddress"
                            rows="2"
                            required
                            placeholder="House #, Road #, Block/Sector, Apartment & Rider Landmarks"
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs text-[var(--foreground)] outline-none"
                        ></textarea>
                    </div>

                    <!-- Optional Device Model / Symptom Note -->
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">5. Device Model & Technician Notes (Optional)</label>
                        <textarea
                            name="problem_note"
                            rows="2"
                            placeholder="e.g. iPhone 13 Pro Max (Graphite), passcode lock disabled for display testing..."
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs text-[var(--foreground)] outline-none"
                        >{{ old('problem_note') }}</textarea>
                    </div>

                    <!-- Hidden Payment Input Synced from Right Card -->
                    <input type="hidden" name="payment_method" :value="paymentMethod">
                </form>
            </div>

            <!-- ── Right Column: Sticky Direct-Order & Escrow Summary ── -->
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm lg:sticky lg:top-6 space-y-5">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">Direct-Order Summary</p>
                    <h2 class="text-xl font-black text-[var(--foreground)] mt-0.5">Checkout & Escrow</h2>
                </div>

                <div class="space-y-2.5 text-xs border-y border-[var(--border)] py-4">
                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Services Subtotal</span>
                        <span class="font-bold text-[var(--foreground)]">৳{{ number_format($total) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Escrow & Service Charge (5%)</span>
                        <span class="font-bold text-[var(--foreground)]">৳{{ number_format($serviceCharge ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Two-Way Courier (<span x-text="deliveryType">REGULAR</span>)</span>
                        <span class="font-bold text-[var(--foreground)]">৳<span x-text="deliveryFee">80</span></span>
                    </div>
                    <div class="pt-2 border-t border-[var(--border)] flex items-center justify-between text-base">
                        <span class="font-black text-[var(--foreground)]">Total Payable</span>
                        <span class="text-2xl font-black text-[var(--accent-dark)]">৳<span x-text="grandTotal.toLocaleString()">{{ number_format($total + ($serviceCharge ?? 0) + 80) }}</span></span>
                    </div>
                </div>

                <!-- Payment Method Selector -->
                <div class="space-y-2 text-xs">
                    <label class="block font-bold text-[var(--foreground)]">Payment Method</label>
                    <label class="cursor-pointer rounded-2xl border p-3 flex items-center justify-between transition" :class="paymentMethod === 'CASH' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)] font-bold' : 'border-[var(--border)] bg-[var(--card)]'">
                        <span class="flex items-center gap-2 text-[var(--foreground)]">
                            <input type="radio" value="CASH" x-model="paymentMethod" class="accent-[#244229]">
                            <span>Cash on Device Return (COD)</span>
                        </span>
                        <i class="fa-solid fa-money-bill-wave text-emerald-700"></i>
                    </label>
                    <label class="cursor-pointer rounded-2xl border p-3 flex items-center justify-between transition" :class="paymentMethod === 'SSLCOMMERZ' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)] font-bold' : 'border-[var(--border)] bg-[var(--card)]'">
                        <span class="flex items-center gap-2 text-[var(--foreground)]">
                            <input type="radio" value="SSLCOMMERZ" x-model="paymentMethod" class="accent-[#244229]">
                            <span>SSLCommerz Online Escrow (bKash/Card)</span>
                        </span>
                        <i class="fa-solid fa-lock text-[var(--accent-dark)]"></i>
                    </label>
                </div>

                <div class="rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] p-3.5 text-[11px] text-[var(--muted-foreground)] leading-relaxed">
                    <i class="fa-solid fa-circle-info text-[var(--accent-dark)] mr-1"></i>
                    Creates a <strong class="text-[var(--foreground)]">PENDING Direct Order</strong> in the workshop's queue. If physical bench inspection reveals extra parts needed (Quote 2), you can approve or decline before repair begins.
                </div>

                <button
                    type="submit"
                    form="direct-order-form"
                    class="w-full rounded-full bg-[var(--accent-dark)] px-6 py-3.5 text-xs sm:text-sm font-bold text-[var(--accent-foreground)] shadow-lg hover:opacity-90 transition flex items-center justify-center gap-2"
                >
                    <span>Confirm Direct Order</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>

        </div>
    @endif

</div>
@endsection

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
    pickupAddress: @js(old('address', auth()->user()->address ?? ($customerLocation['address'] ?? ''))),
    selectedArea: @js(old('area', auth()->user()->area ?? ($customerLocation['area'] ?? 'Banani'))),
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
        if (loc.address) {
            this.pickupAddress = loc.address;
        }
        if (loc.area) {
            this.selectedArea = loc.area;
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
            window.MeramotToast?.show('Current GPS location detected and applied.', 'success');
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
    <div class="mb-6 flex flex-col sm:flex-row sm:flex-wrap sm:items-center justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
                <a href="/" class="hover:underline">Home</a>
                <span>›</span>
                <a href="{{ route('packages.catalog') }}" class="hover:underline">Service Packages</a>
                <span>›</span>
                <span class="text-[var(--foreground)] font-semibold">Cart</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">
                Direct-Order Cart
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 flex flex-wrap items-center gap-2 leading-relaxed">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R8 • Shanid Sajjatuz Islam [23201465]
                </span>
                <span>Review your selected services, confirm a local workshop, and schedule doorstep pickup.</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto shrink-0">
            <a href="/shops" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition whitespace-nowrap">
                Browse Shops
            </a>
            <a href="{{ route('packages.catalog') }}" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)] transition whitespace-nowrap">
                + Add Packages
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-2xl border border-emerald-400 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-700 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-950 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-amber-700 shrink-0"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-rose-700 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-900 space-y-1 shadow-sm">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    @if(empty($cart))
        <!-- Empty Cart State -->
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-8 sm:p-12 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)]">
                <i class="fa-solid fa-cart-shopping text-2xl"></i>
            </div>
            <h2 class="text-xl font-black text-[var(--foreground)]">Your cart is empty</h2>
            <p class="mt-2 text-xs text-[var(--muted-foreground)] max-w-md mx-auto leading-relaxed">
                Choose a fixed-price repair package or add services directly from a verified workshop.
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('packages.catalog') }}" class="rounded-full bg-[var(--accent-dark)] px-6 py-3 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                    Browse Fixed Packages
                </a>
                <a href="/shops" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-6 py-3 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                    Explore Shops
                </a>
            </div>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] xl:grid-cols-[minmax(0,1.65fr)_380px] items-start">

            <!-- ── Left Column: Workshop Target + Cart Items + Direct-Order Logistics Form ── -->
            <div class="space-y-6 min-w-0">

                <!-- 1. Target Repair Workshop Banner (Shop-Scoped Direct Order) -->
                @if(isset($activeShop) && $activeShop)
                    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm overflow-hidden">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="h-12 w-12 rounded-2xl bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center font-black text-lg shrink-0">
                                {{ strtoupper(substr($activeShop->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-0.5 text-[10px] font-extrabold uppercase tracking-wider text-[var(--accent-dark)]">
                                        @if(!empty($shopLocked) || !empty($shopManuallySelected))
                                            <i class="fa-solid fa-circle-check text-[9px]"></i>
                                            <span>Selected Workshop</span>
                                        @else
                                            <i class="fa-solid fa-location-crosshairs text-[9px]"></i>
                                            <span>Smart-Matched Workshop</span>
                                        @endif
                                    </span>

                                    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-[11px] font-bold text-amber-800">
                                        @if((float) $activeShop->rating > 0)
                                            <span>★ {{ number_format($activeShop->rating, 1) }}</span>
                                            @if((int) $activeShop->review_count > 0)
                                                <span class="font-medium text-amber-700/80">({{ $activeShop->review_count }})</span>
                                            @endif
                                        @else
                                            <span>Verified Partner</span>
                                        @endif
                                    </span>

                                    @if(isset($activeShop->distance_km) && $activeShop->distance_km !== null)
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[11px] font-semibold text-[var(--foreground)]">
                                            <i class="fa-solid fa-route text-[10px] text-[var(--accent-dark)]"></i>
                                            <span>{{ \App\Services\GeolocationService::formatDistance((float) $activeShop->distance_km) }} away</span>
                                        </span>
                                    @endif

                                    @if($activeShop->is_featured)
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">
                                            <i class="fa-solid fa-award text-[9px]"></i>
                                            <span>Featured</span>
                                        </span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base sm:text-lg font-black text-[var(--foreground)] leading-snug break-words">{{ $activeShop->name }}</h3>
                                    @if($activeShop->display_branch_tag)
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded-full bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)]">
                                            <i class="fa-solid fa-code-branch text-[9px]"></i>
                                            <span>{{ $activeShop->display_branch_tag }}</span>
                                        </span>
                                    @endif
                                </div>

                                <p class="text-xs text-[var(--muted-foreground)] leading-relaxed break-words">{{ $activeShop->public_address }}</p>
                            </div>
                        </div>

                        @if(empty($shopLocked) && isset($verifiedShops) && count($verifiedShops) > 1)
                            <div class="mt-4 pt-3.5 border-t border-[var(--border)] flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 min-w-0">
                                <label for="cart-workshop-select" class="text-[11px] font-bold text-[var(--muted-foreground)] shrink-0">
                                    Change Workshop ({{ $customerLocation['city'] ?? ($activeShop->city ?: 'Local') }})
                                </label>
                                <form action="{{ route('cart.shop') }}" method="POST" class="w-full sm:w-auto sm:min-w-[240px] sm:max-w-[340px] min-w-0">
                                    @csrf
                                    <select id="cart-workshop-select" name="shop_id" onchange="this.form.submit()" class="w-full max-w-full truncate rounded-xl border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2 text-xs font-semibold text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)] transition">
                                        @foreach($verifiedShops as $vShop)
                                            <option value="{{ $vShop->id }}" {{ $activeShop->id === $vShop->id ? 'selected' : '' }}>
                                                {{ \Illuminate\Support\Str::limit($vShop->name, 26) }}{{ $vShop->display_branch_tag ? ' [' . \Illuminate\Support\Str::limit($vShop->display_branch_tag, 18) . ']' : ($vShop->area ? ' (' . \Illuminate\Support\Str::limit($vShop->area, 16) . ')' : '') }}{{ isset($vShop->distance_km) && $vShop->distance_km !== null ? ' · ' . \App\Services\GeolocationService::formatDistance((float) $vShop->distance_km) : '' }}{{ (float) $vShop->rating > 0 ? ' · ★' . number_format($vShop->rating, 1) : ($vShop->is_featured ? ' · Featured' : '') }}
                                            </option>
                                        @endforeach
                                    </select>
                                </form>
                            </div>
                        @endif
                    </div>
                @else
                    <div class="rounded-[2rem] border border-amber-300 bg-amber-50/90 p-5 md:p-6 shadow-sm space-y-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-amber-100 text-amber-800 border border-amber-300">
                                <i class="fa-solid fa-location-dot"></i>
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-sm font-black text-amber-950">
                                    No verified workshop available in {{ $customerLocation['city'] ?? 'your area' }}
                                </h3>
                                <p class="mt-1 text-xs text-amber-900 leading-relaxed">
                                    Doorstep pickup is limited to verified workshops within your city. Update your pickup location or browse listed workshops.
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <button
                                type="button"
                                @click="$dispatch('meramot-open-location-modal')"
                                class="rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition"
                            >
                                Update Pickup Location
                            </button>
                            <a href="/shops" class="rounded-full border border-amber-300 bg-white px-4 py-2 text-xs font-bold text-amber-950 hover:bg-amber-100 transition">
                                Browse Workshops
                            </a>
                        </div>
                    </div>
                @endif

                <!-- 2. Selected Repair Services List -->
                <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] pb-3">
                        <h2 class="text-base font-black text-[var(--foreground)]">
                            Selected Services ({{ count($cart) }})
                        </h2>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('cart.checkout') }}" class="text-xs font-semibold text-[var(--accent-dark)] hover:underline whitespace-nowrap">
                                Standalone Checkout
                            </a>
                            <form action="{{ route('cart.clear') }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="text-xs font-semibold text-rose-700 hover:underline whitespace-nowrap">
                                    Clear Cart
                                </button>
                            </form>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach($cart as $id => $item)
                            @php
                                $qty = (int) ($item['quantity'] ?? 1);
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-4">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2 mb-1">
                                        <span class="rounded-full bg-[var(--card)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)] whitespace-nowrap">
                                            {{ $item['category'] ?? 'Repair Service' }}
                                        </span>
                                        @if(!empty($item['duration']))
                                            <span class="text-[11px] text-[var(--muted-foreground)] whitespace-nowrap">
                                                <i class="fa-regular fa-clock mr-1"></i>{{ $item['duration'] }}h est.
                                            </span>
                                        @endif
                                        @if(!empty($item['warranty_months']))
                                            <span class="text-[11px] text-emerald-700 font-semibold whitespace-nowrap">
                                                <i class="fa-solid fa-shield-halved mr-1 text-[10px]"></i>{{ $item['warranty_months'] }}-mo warranty
                                            </span>
                                        @endif
                                    </div>
                                    <h3 class="text-sm font-bold text-[var(--foreground)] break-words">{{ $item['title'] }}</h3>
                                    <p class="mt-1 text-sm font-black text-[var(--accent-dark)]">
                                        ৳{{ number_format($item['price']) }} × {{ $qty }}
                                        <span class="ml-1 text-xs font-semibold text-[var(--muted-foreground)]">
                                            (৳{{ number_format($item['price'] * $qty) }})
                                        </span>
                                    </p>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                                    <div class="inline-flex items-center rounded-full border border-[var(--border)] bg-[var(--card)] p-1">
                                        <button
                                            type="button"
                                            @disabled($qty <= 1)
                                            aria-label="Decrease quantity of {{ $item['title'] }}"
                                            onclick="updateQuantity(@js((string) $id), {{ $qty - 1 }})"
                                            class="h-7 w-7 rounded-full text-xs font-black text-[var(--foreground)] hover:bg-[var(--mint-200)] transition disabled:opacity-35 disabled:cursor-not-allowed"
                                        >−</button>
                                        <span class="w-8 text-center text-xs font-bold text-[var(--foreground)]">{{ $qty }}</span>
                                        <button
                                            type="button"
                                            @disabled($qty >= 10)
                                            aria-label="Increase quantity of {{ $item['title'] }}"
                                            onclick="updateQuantity(@js((string) $id), {{ $qty + 1 }})"
                                            class="h-7 w-7 rounded-full text-xs font-black text-[var(--foreground)] hover:bg-[var(--mint-200)] transition disabled:opacity-35 disabled:cursor-not-allowed"
                                        >+</button>
                                    </div>

                                    <button
                                        type="button"
                                        aria-label="Remove {{ $item['title'] }}"
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
                        Pickup Schedule, Courier &amp; Address
                    </h2>

                    <!-- Schedule Type: NOW vs LATER -->
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-2">1. Pickup Schedule *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="cursor-pointer rounded-2xl border-2 p-3.5 transition flex items-start gap-3" :class="scheduleType === 'NOW' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <input type="radio" name="schedule_type" value="NOW" x-model="scheduleType" class="mt-0.5 accent-[#244229]">
                                <div class="min-w-0">
                                    <span class="font-bold text-[var(--foreground)] block">Pickup Now</span>
                                    <span class="text-[11px] text-[var(--muted-foreground)]">Dispatch courier once workshop confirms</span>
                                </div>
                            </label>
                            <label class="cursor-pointer rounded-2xl border-2 p-3.5 transition flex items-start gap-3" :class="scheduleType === 'LATER' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <input type="radio" name="schedule_type" value="LATER" x-model="scheduleType" class="mt-0.5 accent-[#244229]">
                                <div class="min-w-0">
                                    <span class="font-bold text-[var(--foreground)] block">Schedule for Later</span>
                                    <span class="text-[11px] text-[var(--muted-foreground)]">Select a preferred date and time</span>
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
                        <label class="block font-bold text-[var(--foreground)] mb-2">2. Two-Way Courier Tier *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="cursor-pointer rounded-2xl border-2 p-3.5 transition flex items-center justify-between gap-3" :class="deliveryType === 'REGULAR' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <input type="radio" name="delivery_type" value="REGULAR" x-model="deliveryType" class="mt-0.5 accent-[#244229]">
                                    <div class="min-w-0">
                                        <span class="font-bold text-[var(--foreground)] block">Regular Courier</span>
                                        <span class="text-[11px] text-[var(--muted-foreground)]">Standard insured pickup &amp; return</span>
                                    </div>
                                </div>
                                <span class="font-black text-sm text-[var(--accent-dark)] shrink-0">৳80</span>
                            </label>

                            <label class="cursor-pointer rounded-2xl border-2 p-3.5 transition flex items-center justify-between gap-3" :class="deliveryType === 'EXPRESS' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <input type="radio" name="delivery_type" value="EXPRESS" x-model="deliveryType" class="mt-0.5 accent-[#244229]">
                                    <div class="min-w-0">
                                        <span class="font-bold text-[var(--foreground)] block">Express Courier</span>
                                        <span class="text-[11px] text-[var(--muted-foreground)]">Priority direct dispatch</span>
                                    </div>
                                </div>
                                <span class="font-black text-sm text-[var(--accent-dark)] shrink-0">৳150</span>
                            </label>
                        </div>
                    </div>

                    <!-- Contact Phone & Neighborhood -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">3. Contact Phone *</label>
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
                            <label for="cart-pickup-area" class="block font-bold text-[var(--foreground)] mb-1">Pickup Area</label>
                            <input
                                id="cart-pickup-area"
                                type="text"
                                name="area"
                                x-model="selectedArea"
                                list="meramot-area-suggestions"
                                placeholder="e.g. Panthapath, Banani, Agrabad"
                                class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none"
                            >
                            <datalist id="meramot-area-suggestions">
                                @foreach(['Panthapath', 'Banani', 'Gulshan', 'Dhanmondi', 'Mirpur', 'Uttara', 'Bashundhara', 'Mohammadpur', 'Farmgate', 'Tejgaon', 'Mohakhali', 'Badda', 'Wari', 'Agrabad', 'GEC Circle', 'Zindabazar', 'Shaheb Bazar', 'Shib Bari'] as $hub)
                                    <option value="{{ $hub }}">
                                @endforeach
                            </datalist>
                        </div>
                    </div>

                    <!-- Pickup Address Location Mode Selector -->
                    <div class="space-y-3 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="cart-pickup-address" class="font-bold text-[var(--foreground)]">4. Pickup Address *</label>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="$dispatch('meramot-open-location-modal')"
                                    class="inline-flex items-center gap-1 rounded-full bg-[var(--accent-dark)] px-3 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition whitespace-nowrap"
                                >
                                    <i class="fa-solid fa-map-location-dot"></i> Pin on Map
                                </button>
                                <button
                                    type="button"
                                    @click="fillGpsLocation()"
                                    class="inline-flex items-center gap-1 rounded-full border border-[var(--border)] bg-[var(--card)] px-3 py-1.5 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-50)] transition whitespace-nowrap"
                                >
                                    <i class="fa-solid fa-location-crosshairs"></i> Use GPS
                                </button>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <button
                                type="button"
                                @click="useSavedOrOpenMap()"
                                class="min-w-0 rounded-xl border p-2.5 text-left transition"
                                :class="addressMode === 'MAP' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/40 hover:border-[var(--accent-dark)]/50'"
                            >
                                <div class="font-bold text-xs text-[var(--foreground)]">Map Pin</div>
                                <div class="text-[10px] text-[var(--muted-foreground)]">Use pinned location</div>
                            </button>
                            <button
                                type="button"
                                @click="addressMode = 'MANUAL'"
                                class="min-w-0 rounded-xl border p-2.5 text-left transition"
                                :class="addressMode === 'MANUAL' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/40 hover:border-[var(--accent-dark)]/50'"
                            >
                                <div class="font-bold text-xs text-[var(--foreground)]">Manual Entry</div>
                                <div class="text-[10px] text-[var(--muted-foreground)]">Type street address</div>
                            </button>
                            <button
                                type="button"
                                @click="addressMode = 'PROFILE'; pickupAddress = @js(auth()->user()->address ?? 'House 12, Road 11, Banani, Dhaka')"
                                class="min-w-0 rounded-xl border p-2.5 text-left transition"
                                :class="addressMode === 'PROFILE' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/40 hover:border-[var(--accent-dark)]/50'"
                            >
                                <div class="font-bold text-xs text-[var(--foreground)]">Saved Profile</div>
                                <div class="text-[10px] text-[var(--muted-foreground)]">Use account address</div>
                            </button>
                        </div>

                        <template x-if="pinnedLocation">
                            <div class="rounded-xl border border-[var(--border)] bg-[var(--card)] p-3 text-xs">
                                <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)] block">Pinned Map Address</span>
                                <span class="font-bold text-[var(--foreground)] mt-0.5 block break-words" x-text="pinnedLocation.address"></span>
                            </div>
                        </template>

                        <textarea
                            id="cart-pickup-address"
                            name="address"
                            x-model="pickupAddress"
                            rows="2"
                            required
                            placeholder="House #, Road #, Floor/Apartment & Landmarks"
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs text-[var(--foreground)] outline-none"
                        ></textarea>
                    </div>

                    <!-- Optional Device Model / Symptom Note -->
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">5. Device Model &amp; Notes (Optional)</label>
                        <textarea
                            name="problem_note"
                            rows="2"
                            placeholder="e.g. iPhone 13 Pro Max (Graphite), issue notes or testing instructions..."
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs text-[var(--foreground)] outline-none"
                        >{{ old('problem_note') }}</textarea>
                    </div>

                    <!-- Hidden Payment Input Synced from Right Card -->
                    <input type="hidden" name="payment_method" :value="paymentMethod">
                </form>
            </div>

            <!-- ── Right Column: Sticky Direct-Order & Escrow Summary ── -->
            <div class="min-w-0 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm lg:sticky lg:top-6 space-y-5">
                <div>
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">Order Summary</p>
                    <h2 class="text-xl font-black text-[var(--foreground)] mt-0.5">Checkout &amp; Escrow</h2>
                </div>

                <div class="space-y-2.5 text-xs border-y border-[var(--border)] py-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[var(--muted-foreground)]">Services Subtotal</span>
                        <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($total) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[var(--muted-foreground)]">Escrow Fee (5%)</span>
                        <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($serviceCharge ?? 0) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[var(--muted-foreground)]">Two-Way Courier (<span x-text="deliveryType === 'EXPRESS' ? 'Express' : 'Regular'">Regular</span>)</span>
                        <span class="font-bold text-[var(--foreground)] shrink-0">৳<span x-text="deliveryFee">80</span></span>
                    </div>
                    <div class="pt-2 border-t border-[var(--border)] flex items-center justify-between gap-2 text-base">
                        <span class="font-black text-[var(--foreground)]">Total Payable</span>
                        <span class="text-2xl font-black text-[var(--accent-dark)] shrink-0">৳<span x-text="grandTotal.toLocaleString()">{{ number_format($total + ($serviceCharge ?? 0) + 80) }}</span></span>
                    </div>
                </div>

                <!-- Payment Method Selector -->
                <div class="space-y-2 text-xs">
                    <label class="block font-bold text-[var(--foreground)]">Payment Method</label>
                    <label class="cursor-pointer rounded-2xl border p-3 flex items-center justify-between gap-2 transition" :class="paymentMethod === 'CASH' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)] font-bold' : 'border-[var(--border)] bg-[var(--card)]'">
                        <span class="flex items-center gap-2 text-[var(--foreground)]">
                            <input type="radio" value="CASH" x-model="paymentMethod" class="accent-[#244229]">
                            <span>Cash on Return (COD)</span>
                        </span>
                        <i class="fa-solid fa-money-bill-wave text-emerald-700 shrink-0"></i>
                    </label>
                    <label class="cursor-pointer rounded-2xl border p-3 flex items-center justify-between gap-2 transition" :class="paymentMethod === 'SSLCOMMERZ' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)] font-bold' : 'border-[var(--border)] bg-[var(--card)]'">
                        <span class="flex items-center gap-2 text-[var(--foreground)]">
                            <input type="radio" value="SSLCOMMERZ" x-model="paymentMethod" class="accent-[#244229]">
                            <span>SSLCommerz Online Escrow (bKash/Card)</span>
                        </span>
                        <i class="fa-solid fa-lock text-[var(--accent-dark)] shrink-0"></i>
                    </label>
                </div>

                <div class="rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] p-3.5 text-[11px] text-[var(--muted-foreground)] leading-relaxed">
                    <i class="fa-solid fa-circle-info text-[var(--accent-dark)] mr-1"></i>
                    Sent directly to your chosen workshop. If bench diagnostics require extra parts, you'll approve any updated quote before work starts.
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

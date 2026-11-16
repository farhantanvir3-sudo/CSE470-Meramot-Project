@extends('layouts.app')

@section('title', 'Service Cart | Meramot')

{{-- ==========================================================================
     FEATURE R8: Unified Direct-Order & Quickmatch Cart Wizard
     Assigned Member: Shanid Sajjatuz Islam [23201465]
     Direct-Order Flow Merge: Farhan Tanvir [22299179]
     Client JS Relocated To: frontend/js/marketplace-client.js & geolocation-helper.js
     ========================================================================== --}}

@section('content')
@php
    $regCourierFee = (float) ($regularDeliveryFee ?? 80);
    $expCourierFee = (float) ($expressDeliveryFee ?? 150);
    $ratePctLabel = rtrim(rtrim(number_format((float) ($commissionRatePct ?? 8), 2), '0'), '.');
    $isVendorViewer = auth()->check() && auth()->user()->role === 'vendor';
    $allowCourier = (bool) ($canCourierPickup ?? true);
    $allowInShop = (bool) ($canInShopRepair ?? true);
    $initFulfillment = old('fulfillment_mode', $defaultFulfillmentMode ?? 'COURIER_PICKUP');
    if ($initFulfillment === 'COURIER_PICKUP' && !$allowCourier && $allowInShop) {
        $initFulfillment = 'IN_SHOP';
    } elseif ($initFulfillment === 'IN_SHOP' && !$allowInShop && $allowCourier) {
        $initFulfillment = 'COURIER_PICKUP';
    }
@endphp
<div class="mx-auto max-w-6xl px-4 py-6 md:px-6" x-data="{
    fulfillmentMode: @js($initFulfillment),
    isSparePartCart: @js(!empty($isSparePartCart)),
    canCourierPickup: @js($allowCourier),
    canInShopRepair: @js($allowInShop),
    scheduleType: '{{ old('schedule_type', 'NOW') }}',
    deliveryType: '{{ old('delivery_type', 'REGULAR') }}',
    paymentMethod: '{{ old('payment_method', 'CASH') }}',
    addressMode: 'MAP',
    locatingGps: false,
    pinnedLocation: null,
    pickupAddress: @js(old('address', auth()->user()->address ?? ($customerLocation['address'] ?? ''))),
    selectedArea: @js(old('area', auth()->user()->area ?? ($customerLocation['area'] ?? 'Banani'))),
    subtotal: {{ (float) ($total ?? 0) }},
    serviceCharge: {{ (float) ($serviceCharge ?? 0) }},
    regularFee: {{ $regCourierFee }},
    expressFee: {{ $expCourierFee }},
    appliedVoucher: @js($appliedVoucher ?? ''),
    baseDiscount: {{ (float) ($discountAmount ?? 0) }},
    get isCourierMode() {
        return this.isSparePartCart || this.fulfillmentMode === 'COURIER_PICKUP';
    },
    get deliveryFee() {
        if (!this.isCourierMode) return 0;
        return this.deliveryType === 'EXPRESS' ? this.expressFee : this.regularFee;
    },
    get activeDiscount() {
        if (this.appliedVoucher === 'FREEDELIVERY') return this.deliveryFee;
        if (this.appliedVoucher === 'SHOPFREE') return Math.min(this.deliveryFee, this.regularFee);
        return this.baseDiscount;
    },
    get grandTotal() {
        return Math.max(0, this.subtotal + this.serviceCharge + (this.subtotal > 0 ? this.deliveryFee : 0) - this.activeDiscount);
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
        if (!window.MeramotGeolocation || !window.MeramotLocationPicker || this.locatingGps) return;
        this.locatingGps = true;
        try {
            const pos = await window.MeramotGeolocation.getCurrentPosition({ desiredAccuracy: 15, maxWaitMs: 6500 });
            const resolved = await window.MeramotLocationPicker.reverseGeocode(pos.lat, pos.lng, 'gps');
            const saved = window.MeramotLocationPicker.saveLocation(resolved);
            this.applyMapLocation(saved);
            window.MeramotToast?.show('Current GPS location detected and applied.', 'success');
        } catch (err) {
            window.MeramotToast?.show(err.message || 'Unable to read GPS coordinates.', 'warning');
        } finally {
            this.locatingGps = false;
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
                <a href="/" class="hover:underline" data-i18n="home_nav">Home</a>
                <span>›</span>
                <a href="{{ route('packages.catalog') }}" class="hover:underline" data-i18n="service_packages">Service Packages</a>
                <span>›</span>
                <span class="text-[var(--foreground)] font-semibold" data-i18n="cart_label">Cart</span>
            </div>
            <h1 class="text-xl md:text-2xl font-bold tracking-tight text-[var(--foreground)]" data-i18n="your_service_cart">
                Your Service Cart
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 flex flex-wrap items-center gap-2 leading-relaxed">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R8 • Shanid Sajjatuz Islam [23201465]
                </span>
                <span>Review selected items, workshop assignment, and fulfillment mode.</span>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2 w-full sm:w-auto shrink-0">
            <a href="{{ route('spare-parts.index') }}" class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2 text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition whitespace-nowrap" data-i18n="add_spare_parts_btn">
                + Add Spare Parts
            </a>
            @if(!$isVendorViewer)
                <a href="{{ route('packages.catalog') }}" class="rounded-md border border-[var(--border)] bg-[var(--card)] px-3.5 py-2 text-xs font-semibold text-[var(--accent-dark)] hover:bg-[var(--mint-100)] transition whitespace-nowrap" data-i18n="add_packages_btn">
                    + Add Packages
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-lg border border-emerald-400 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-700 shrink-0"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-5 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-950 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-amber-700 shrink-0"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-5 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-rose-700 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-900 space-y-1 shadow-sm">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    @if(empty($cart))
        <!-- Empty Cart State -->
        <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-8 sm:p-12 text-center shadow-sm">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)]">
                <i class="fa-solid fa-cart-shopping text-2xl"></i>
            </div>
            <h2 class="text-xl font-bold text-[var(--foreground)]" data-i18n="cart_empty">Your cart is empty</h2>
            <p class="mt-2 text-xs text-[var(--muted-foreground)] max-w-md mx-auto leading-relaxed">
                Browse genuine spare parts, flat-rate service packages, or verified workshops to get started.
            </p>
            <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
                <a href="{{ route('spare-parts.index') }}" class="rounded-md bg-[var(--accent-dark)] px-6 py-3 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                    Browse Spare Parts Marketplace
                </a>
                @if(!$isVendorViewer)
                    <a href="{{ route('packages.catalog') }}" class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-6 py-3 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                        Browse Fixed Packages
                    </a>
                @endif
            </div>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_340px] xl:grid-cols-[minmax(0,1.65fr)_380px] items-start">

            <!-- ── Left Column: Workshop Target + Cart Items + Direct-Order Logistics Form ── -->
            <div class="space-y-6 min-w-0">

                <!-- 1. Target Repair Workshop Banner (Shop-Scoped Direct Order) -->
                @if(isset($activeShop) && $activeShop)
                    <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm overflow-hidden">
                        <div class="flex items-start gap-3.5 min-w-0">
                            <div class="h-12 w-12 rounded-lg bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center font-bold text-lg shrink-0">
                                {{ strtoupper(substr($activeShop->name, 0, 1)) }}
                            </div>
                            <div class="min-w-0 flex-1 space-y-1.5">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="inline-flex items-center gap-1.5 whitespace-nowrap rounded bg-[var(--mint-100)] border border-[var(--border)] px-3 py-0.5 text-[10px] font-semibold uppercase tracking-wider text-[var(--accent-dark)]">
                                        @if(!empty($shopLocked) || !empty($shopManuallySelected))
                                            <i class="fa-solid fa-circle-check text-[9px]"></i>
                                            <span data-i18n="selected_workshop">Selected Workshop</span>
                                        @else
                                            <i class="fa-solid fa-location-dot text-[9px]"></i>
                                            <span data-i18n="nearest_verified_workshop">Nearest Verified Workshop</span>
                                        @endif
                                    </span>

                                    <span class="inline-flex items-center gap-1 whitespace-nowrap rounded bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-[11px] font-bold text-amber-800">
                                        @if((float) $activeShop->rating > 0)
                                            <span>★ {{ number_format($activeShop->rating, 1) }}</span>
                                            @if((int) $activeShop->review_count > 0)
                                                <span class="font-medium text-amber-700/80">({{ $activeShop->review_count }})</span>
                                            @endif
                                        @else
                                            <span data-i18n="verified_partner">Verified Partner</span>
                                        @endif
                                    </span>

                                    @if(isset($activeShop->distance_km) && $activeShop->distance_km !== null)
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[11px] font-semibold text-[var(--foreground)]">
                                            <i class="fa-solid fa-route text-[10px] text-[var(--accent-dark)]"></i>
                                            <span>{{ \App\Services\GeolocationService::formatDistance((float) $activeShop->distance_km) }} away</span>
                                        </span>
                                    @endif

                                    @if($activeShop->is_featured)
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800">
                                            <i class="fa-solid fa-award text-[9px]"></i>
                                            <span data-i18n="featured_badge">Featured</span>
                                        </span>
                                    @endif
                                </div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-base sm:text-lg font-bold text-[var(--foreground)] leading-snug break-words">{{ $activeShop->name }}</h3>
                                    @if($activeShop->display_branch_tag)
                                        <span class="inline-flex items-center gap-1 whitespace-nowrap rounded bg-[var(--accent-dark)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-foreground)]">
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
                    <div class="rounded-lg border border-amber-300 bg-amber-50/90 p-5 md:p-6 shadow-sm space-y-3">
                        <div class="flex items-start gap-3">
                            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-amber-100 text-amber-800 border border-amber-300">
                                <i class="fa-solid fa-location-dot"></i>
                            </span>
                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-amber-950">
                                    No verified workshop available in {{ $customerLocation['city'] ?? 'your area' }}
                                </h3>
                                <p class="mt-1 text-xs text-amber-900 leading-relaxed">
                                    We couldn't find a verified workshop for these items in your selected city. Update your location or browse listed workshops directly.
                                </p>
                            </div>
                        </div>
                        <div class="flex flex-wrap items-center gap-2 pt-1">
                            <button
                                type="button"
                                @click="$dispatch('meramot-open-location-modal')"
                                class="rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition"
                            >
                                Update Location
                            </button>
                            <a href="/shops" class="rounded-md border border-amber-300 bg-white px-4 py-2 text-xs font-bold text-amber-950 hover:bg-amber-100 transition">
                                Browse Workshops
                            </a>
                        </div>
                    </div>
                @endif

                <!-- 2. Selected Repair Services List -->
                <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] pb-3">
                        <h2 class="text-base font-bold text-[var(--foreground)]">
                            Selected Services ({{ count($cart) }})
                        </h2>
                        <div class="flex items-center gap-3">
                            <a href="{{ route('cart.checkout') }}" class="text-xs font-semibold text-[var(--accent-dark)] hover:underline whitespace-nowrap">
                                Checkout Page
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
                                $isSpareItem = ($item['source'] ?? '') === 'SPARE_PART';
                                $maxStock = $isSpareItem ? max(1, (int) ($item['max_stock'] ?? 10000)) : 10;
                                $itemNormalUnit = (float) ($item['normal_unit_price'] ?? $item['price']);
                                $itemUnit = (float) ($item['price'] ?? 0);
                                $itemWsPct = (float) ($item['wholesale_discount_pct'] ?? 0);
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/60 p-4">
                                <div class="flex items-start gap-3 min-w-0 flex-1">
                                    @if($isSpareItem && !empty($item['image_url']))
                                        <div class="relative w-16 h-16 rounded-xl border border-[var(--border)] bg-white p-1.5 shrink-0 overflow-hidden">
                                            @if(!empty($item['has_active_offer']))
                                                <span class="absolute top-1 left-1 z-10 w-7 h-7 rounded-full bg-red-600 text-white text-[8px] font-semibold flex items-center justify-center shadow">
                                                    Sale!
                                                </span>
                                            @endif
                                            <img src="{{ $item['image_url'] }}" alt="{{ $item['title'] }}" class="w-full h-full object-contain">
                                        </div>
                                    @endif

                                    <div class="min-w-0 flex-1">
                                        <div class="flex flex-wrap items-center gap-2 mb-1">
                                            <span class="rounded bg-[var(--card)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)] whitespace-nowrap">
                                                {{ $item['category'] ?? 'Repair Service' }}
                                            </span>
                                            @if($itemWsPct > 0)
                                                <span class="rounded bg-emerald-900 text-white px-2.5 py-0.5 text-[10px] font-semibold whitespace-nowrap">
                                                    📦 -{{ rtrim(rtrim(number_format($itemWsPct, 2), '0'), '.') }}% Wholesale
                                                </span>
                                            @elseif($isSpareItem && !empty($item['wholesale_enabled']))
                                                <span class="text-[10px] font-bold text-amber-900 whitespace-nowrap">
                                                    📦 Wholesale at {{ (int) ($item['wholesale_min_qty'] ?? 100) }}+ pcs
                                                </span>
                                            @endif
                                            @if(!empty($item['duration']))
                                                <span class="text-[11px] text-[var(--muted-foreground)] whitespace-nowrap">
                                                    <i class="fa-regular fa-clock mr-1"></i>{{ $item['duration'] }}h {{ $isSpareItem ? 'prep' : 'est.' }}
                                                </span>
                                            @endif
                                            @if(!empty($item['warranty_months']))
                                                <span class="text-[11px] text-emerald-700 font-semibold whitespace-nowrap">
                                                    <i class="fa-solid fa-shield-halved mr-1 text-[10px]"></i>{{ $item['warranty_months'] }}-mo warranty
                                                </span>
                                            @endif
                                        </div>
                                        <h3 class="text-sm font-bold text-[var(--foreground)] break-words">{{ $item['title'] }}</h3>
                                        <p class="mt-1 text-sm font-bold {{ !empty($item['has_active_offer']) ? 'text-red-600' : 'text-[var(--accent-dark)]' }}">
                                            @if($itemNormalUnit > $itemUnit)
                                                <span class="text-xs font-semibold text-slate-400 line-through mr-1">৳{{ number_format($itemNormalUnit) }}</span>
                                            @endif
                                            ৳{{ number_format($itemUnit, 2) }} × {{ $qty }}
                                            <span class="ml-1 text-xs font-semibold text-[var(--muted-foreground)]">
                                                (৳{{ number_format($itemUnit * $qty, 2) }})
                                            </span>
                                        </p>
                                    </div>
                                </div>

                                <div class="flex items-center justify-between sm:justify-end gap-3 shrink-0">
                                    <div class="inline-flex items-center rounded-full border border-[var(--border)] bg-[var(--card)] p-1">
                                        <button
                                            type="button"
                                            @disabled($qty <= 1)
                                            aria-label="Decrease quantity of {{ $item['title'] }}"
                                            onclick="updateQuantity(@js((string) $id), {{ $qty - 1 }})"
                                            class="h-7 w-7 rounded-full text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition disabled:opacity-35 disabled:cursor-not-allowed"
                                        >−</button>
                                        @if($isSpareItem)
                                            <input type="number"
                                                   min="1"
                                                   max="{{ $maxStock }}"
                                                   value="{{ $qty }}"
                                                   aria-label="Quantity of {{ $item['title'] }}"
                                                   onchange="updateQuantity(@js((string) $id), this.value)"
                                                   class="w-16 text-center text-xs font-semibold text-[var(--foreground)] bg-transparent outline-none">
                                        @else
                                            <span class="w-8 text-center text-xs font-bold text-[var(--foreground)]">{{ $qty }}</span>
                                        @endif
                                        <button
                                            type="button"
                                            @disabled($qty >= $maxStock)
                                            aria-label="Increase quantity of {{ $item['title'] }}"
                                            onclick="updateQuantity(@js((string) $id), {{ $qty + 1 }})"
                                            class="h-7 w-7 rounded-full text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition disabled:opacity-35 disabled:cursor-not-allowed"
                                        >+</button>
                                    </div>

                                    <button
                                        type="button"
                                        aria-label="Remove {{ $item['title'] }}"
                                        onclick="removeFromCart(@js((string) $id))"
                                        class="flex h-8 w-8 items-center justify-center rounded-full bg-rose-100 text-rose-700 hover:bg-rose-200 transition"
                                        title="Remove item"
                                    >
                                        <i class="fa-solid fa-trash-can text-xs"></i>
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>

                <!-- 3. Fulfillment Mode, Schedule & Address Configuration -->
                <form id="direct-order-form" method="POST" action="{{ route('cart.place') }}" class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm space-y-5 text-xs">
                    @csrf
                    @if(isset($activeShop) && $activeShop)
                        <input type="hidden" name="shop_id" value="{{ $activeShop->id }}">
                    @endif

                    <h2 class="text-base font-bold text-[var(--foreground)] border-b border-[var(--border)] pb-3" x-text="isCourierMode ? 'Pickup Schedule, Courier & Address' : 'Walk-In Schedule & Workshop Details'">
                        Pickup Schedule, Courier &amp; Address
                    </h2>

                    @if(empty($isSparePartCart))
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-2" data-i18n="how_serviced_label">1. How would you like your device serviced? *</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                                <label
                                    class="rounded-lg border-2 p-3.5 transition flex items-start justify-between gap-2.5 {{ !$allowCourier ? 'opacity-55 cursor-not-allowed bg-slate-50/70' : 'cursor-pointer' }}"
                                    :class="fulfillmentMode === 'COURIER_PICKUP' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'"
                                >
                                    <div class="flex items-start gap-2.5 min-w-0">
                                        <input
                                            type="radio"
                                            name="fulfillment_mode"
                                            value="COURIER_PICKUP"
                                            x-model="fulfillmentMode"
                                            {{ !$allowCourier ? 'disabled' : '' }}
                                            class="mt-0.5 accent-[#244229]"
                                        >
                                        <div class="min-w-0">
                                            <span class="font-bold text-[var(--foreground)] block" data-i18n="courier_pickup_return">Courier Pickup &amp; Return</span>
                                            <span class="text-[11px] text-[var(--muted-foreground)] block leading-snug">
                                                {{ $allowCourier ? 'A rider collects your device from home and returns it after service.' : 'This workshop accepts in-shop visits only.' }}
                                            </span>
                                        </div>
                                    </div>
                                    <span class="font-bold text-xs text-[var(--accent-dark)] shrink-0">
                                        {{ $allowCourier ? '৳' . number_format($regCourierFee, 0) . '+' : 'N/A' }}
                                    </span>
                                </label>

                                <label
                                    class="rounded-lg border-2 p-3.5 transition flex items-start justify-between gap-2.5 {{ !$allowInShop ? 'opacity-55 cursor-not-allowed bg-slate-50/70' : 'cursor-pointer' }}"
                                    :class="fulfillmentMode === 'IN_SHOP' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'"
                                >
                                    <div class="flex items-start gap-2.5 min-w-0">
                                        <input
                                            type="radio"
                                            name="fulfillment_mode"
                                            value="IN_SHOP"
                                            x-model="fulfillmentMode"
                                            {{ !$allowInShop ? 'disabled' : '' }}
                                            class="mt-0.5 accent-[#244229]"
                                        >
                                        <div class="min-w-0">
                                            <span class="font-bold text-[var(--foreground)] block" data-i18n="inshop_walkin_repair">In-Shop Walk-In Repair</span>
                                            <span class="text-[11px] text-[var(--muted-foreground)] block leading-snug">
                                                {{ $allowInShop ? 'Drop by the workshop in person and pay on Meramot — zero delivery fee.' : 'This workshop operates by courier only.' }}
                                            </span>
                                        </div>
                                    </div>
                                    <span class="font-bold text-xs text-emerald-700 shrink-0">
                                        {{ $allowInShop ? '৳0 Fee' : 'N/A' }}
                                    </span>
                                </label>
                            </div>
                        </div>
                    @else
                        <input type="hidden" name="fulfillment_mode" value="COURIER_PICKUP">
                    @endif

                    <!-- Schedule Type: NOW vs LATER -->
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-2" x-text="isCourierMode ? '2. Pickup Schedule *' : '2. Workshop Visit Schedule *'">2. Pickup Schedule *</label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="cursor-pointer rounded-lg border-2 p-3.5 transition flex items-start gap-3" :class="scheduleType === 'NOW' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <input type="radio" name="schedule_type" value="NOW" x-model="scheduleType" class="mt-0.5 accent-[#244229]">
                                <div class="min-w-0">
                                    <span class="font-bold text-[var(--foreground)] block" x-text="isCourierMode ? 'Pickup Now' : 'Walk In Today'">Pickup Now</span>
                                    <span class="text-[11px] text-[var(--muted-foreground)]" x-text="isCourierMode ? 'Dispatch courier once workshop confirms' : 'Drop off once workshop confirms your slot'">Dispatch courier once workshop confirms</span>
                                </div>
                            </label>
                            <label class="cursor-pointer rounded-lg border-2 p-3.5 transition flex items-start gap-3" :class="scheduleType === 'LATER' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <input type="radio" name="schedule_type" value="LATER" x-model="scheduleType" class="mt-0.5 accent-[#244229]">
                                <div class="min-w-0">
                                    <span class="font-bold text-[var(--foreground)] block" data-i18n="schedule_later">Schedule for Later</span>
                                    <span class="text-[11px] text-[var(--muted-foreground)]" data-i18n="select_datetime">Select a preferred date and time</span>
                                </div>
                            </label>
                        </div>
                        <div x-show="scheduleType === 'LATER'" x-cloak class="mt-3">
                            <input
                                type="datetime-local"
                                name="scheduled_at"
                                value="{{ old('scheduled_at') }}"
                                class="w-full sm:w-72 rounded-lg border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none"
                            >
                            @error('scheduled_at')
                                <p class="mt-1 text-rose-700 font-semibold">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    @if(isset($activeShop) && $activeShop)
                        <div x-show="!isCourierMode" x-cloak class="rounded-lg border border-emerald-300 bg-emerald-50/75 p-4 text-xs space-y-1.5">
                            <div class="font-bold text-emerald-950 flex items-center gap-2">
                                <i class="fa-solid fa-store text-emerald-700"></i>
                                <span>In-Shop Service Counter (No Courier Needed)</span>
                            </div>
                            <p class="font-bold text-[var(--foreground)]">{{ $activeShop->name }}{{ $activeShop->display_branch_tag ? ' [' . $activeShop->display_branch_tag . ']' : '' }}</p>
                            <p class="text-[var(--muted-foreground)]">{{ $activeShop->public_address }}</p>
                            @if($activeShop->opening_hours_text)
                                <p class="text-[11px] font-semibold text-[var(--accent-dark)]">
                                    <i class="fa-regular fa-clock mr-1"></i>{{ $activeShop->opening_hours_text }}
                                </p>
                            @endif
                        </div>
                    @endif

                    <!-- Delivery Type: REGULAR vs EXPRESS -->
                    <div x-show="isCourierMode" x-cloak>
                        <label class="block font-bold text-[var(--foreground)] mb-2">
                            3. {{ !empty($isSparePartCart) ? 'Distance-Based Courier Delivery *' : 'Two-Way Courier Tier *' }}
                            @if(!empty($isSparePartCart) && !empty($deliveryQuote['distance_formatted']))
                                <span class="font-normal text-[var(--muted-foreground)]">
                                    ({{ $deliveryQuote['distance_formatted'] }} • ETA {{ $deliveryQuote['eta_label'] ?? 'Same day' }})
                                </span>
                            @endif
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <label class="cursor-pointer rounded-lg border-2 p-3.5 transition flex items-center justify-between gap-3" :class="deliveryType === 'REGULAR' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <input type="radio" name="delivery_type" value="REGULAR" x-model="deliveryType" :disabled="!isCourierMode" class="mt-0.5 accent-[#244229]">
                                    <div class="min-w-0">
                                        <span class="font-bold text-[var(--foreground)] block" data-i18n="regular_courier">Regular Courier</span>
                                        <span class="text-[11px] text-[var(--muted-foreground)]">{{ !empty($isSparePartCart) ? ($deliveryQuote['eta_label'] ?? 'Standard insured dispatch') : 'Standard insured pickup & return' }}</span>
                                    </div>
                                </div>
                                <span class="font-bold text-sm text-[var(--accent-dark)] shrink-0">৳{{ number_format($regCourierFee, 0) }}</span>
                            </label>

                            <label class="cursor-pointer rounded-lg border-2 p-3.5 transition flex items-center justify-between gap-3" :class="deliveryType === 'EXPRESS' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)]' : 'border-[var(--border)] bg-[var(--card)]'">
                                <div class="flex items-start gap-2.5 min-w-0">
                                    <input type="radio" name="delivery_type" value="EXPRESS" x-model="deliveryType" :disabled="!isCourierMode" class="mt-0.5 accent-[#244229]">
                                    <div class="min-w-0">
                                        <span class="font-bold text-[var(--foreground)] block" data-i18n="express_courier">Express Courier</span>
                                        <span class="text-[11px] text-[var(--muted-foreground)]" data-i18n="priority_dispatch">Priority direct dispatch</span>
                                    </div>
                                </div>
                                <span class="font-bold text-sm text-[var(--accent-dark)] shrink-0">৳{{ number_format($expCourierFee, 0) }}</span>
                            </label>
                        </div>
                    </div>

                    <!-- Contact Phone & Neighborhood -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1" data-i18n="contact_phone">Contact Phone *</label>
                            <input
                                type="tel"
                                name="phone"
                                autocomplete="tel"
                                inputmode="tel"
                                maxlength="14"
                                value="{{ old('phone', auth()->user()->phone ?? '01712345678') }}"
                                required
                                placeholder="017XXXXXXXX"
                                class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs text-[var(--foreground)] outline-none"
                            >
                        </div>
                        <div x-show="isCourierMode" x-cloak>
                            <label for="cart-pickup-area" class="block font-bold text-[var(--foreground)] mb-1" data-i18n="pickup_area">Pickup Area</label>
                            <input
                                id="cart-pickup-area"
                                type="text"
                                name="area"
                                autocomplete="address-level3"
                                x-model="selectedArea"
                                list="meramot-area-suggestions"
                                placeholder="e.g. Panthapath, Banani, Agrabad"
                                class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none"
                            >
                            <datalist id="meramot-area-suggestions">
                                @foreach(['Panthapath', 'Banani', 'Gulshan', 'Dhanmondi', 'Mirpur', 'Uttara', 'Bashundhara', 'Mohammadpur', 'Farmgate', 'Tejgaon', 'Mohakhali', 'Badda', 'Wari', 'Agrabad', 'GEC Circle', 'Zindabazar', 'Shaheb Bazar', 'Shib Bari'] as $hub)
                                    <option value="{{ $hub }}">
                                @endforeach
                            </datalist>
                        </div>
                    </div>

                    <!-- Pickup Address Location Mode Selector (Only when Courier mode) -->
                    <div x-show="isCourierMode" x-cloak class="space-y-3 rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/60 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="cart-pickup-address" class="font-bold text-[var(--foreground)]" data-i18n="pickup_address_req">Pickup Address *</label>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    @click="$dispatch('meramot-open-location-modal')"
                                    class="inline-flex items-center gap-1 rounded-md bg-[var(--accent-dark)] px-3 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition whitespace-nowrap"
                                >
                                    <i class="fa-solid fa-map-location-dot"></i> <span data-i18n="pin_on_map">Pin on Map</span>
                                </button>
                                <button
                                    type="button"
                                    @click="fillGpsLocation()"
                                    :disabled="locatingGps"
                                    class="inline-flex items-center gap-1 rounded-md border border-[var(--border)] bg-[var(--card)] px-3 py-1.5 text-[11px] font-semibold text-[var(--accent-dark)] hover:bg-[var(--mint-50)] disabled:opacity-60 transition whitespace-nowrap"
                                >
                                    <i class="fa-solid" :class="locatingGps ? 'fa-spinner fa-spin' : 'fa-location-crosshairs'"></i>
                                    <span x-text="locatingGps ? 'Detecting...' : 'Use GPS'">Use GPS</span>
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
                                <div class="font-bold text-xs text-[var(--foreground)]" data-i18n="map_pin">Map Pin</div>
                                <div class="text-[10px] text-[var(--muted-foreground)]">Use pinned location</div>
                            </button>
                            <button
                                type="button"
                                @click="addressMode = 'MANUAL'"
                                class="min-w-0 rounded-xl border p-2.5 text-left transition"
                                :class="addressMode === 'MANUAL' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/40 hover:border-[var(--accent-dark)]/50'"
                            >
                                <div class="font-bold text-xs text-[var(--foreground)]" data-i18n="manual_entry">Manual Entry</div>
                                <div class="text-[10px] text-[var(--muted-foreground)]">Type street address</div>
                            </button>
                            <button
                                type="button"
                                @click="addressMode = 'PROFILE'; pickupAddress = @js(auth()->user()->address ?? 'House 12, Road 11, Banani, Dhaka')"
                                class="min-w-0 rounded-xl border p-2.5 text-left transition"
                                :class="addressMode === 'PROFILE' ? 'border-[var(--accent-dark)] bg-[var(--card)] shadow-sm' : 'border-[var(--border)] bg-[var(--mint-100)]/40 hover:border-[var(--accent-dark)]/50'"
                            >
                                <div class="font-bold text-xs text-[var(--foreground)]" data-i18n="saved_profile">Saved Profile</div>
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
                            :required="isCourierMode"
                            placeholder="House #, Road #, Floor/Apartment & Landmarks"
                            class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs text-[var(--foreground)] outline-none"
                        ></textarea>
                    </div>

                    <!-- Optional Device Model / Symptom Note -->
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1" data-i18n="device_notes_optional">Device Model &amp; Notes (Optional)</label>
                        <textarea
                            name="problem_note"
                            rows="2"
                            placeholder="e.g. iPhone 13 Pro Max (Graphite), issue notes or testing instructions..."
                            class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-xs text-[var(--foreground)] outline-none"
                        >{{ old('problem_note') }}</textarea>
                    </div>

                    <!-- Hidden Payment & Voucher Inputs Synced from Right Card -->
                    <input type="hidden" name="payment_method" :value="paymentMethod">
                    <input type="hidden" name="voucher_code" value="{{ $appliedVoucher ?? '' }}">
                </form>
            </div>

            <!-- ── Right Column: Sticky Direct-Order & Payment Summary ── -->
            <div class="min-w-0 rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm lg:sticky lg:top-6 space-y-5">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)]" data-i18n="order_summary">Order Summary</p>
                    <h2 class="text-xl font-bold text-[var(--foreground)] mt-0.5" data-i18n="checkout_payment">Checkout &amp; Payment</h2>
                </div>

                <!-- Promo & Voucher Engine (Daraz / Foodpanda Style) -->
                <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/75 p-3.5 space-y-2.5 text-xs">
                    <div class="flex items-center justify-between gap-2">
                        <span class="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                            <i class="fa-solid fa-ticket text-[var(--accent-dark)]"></i>
                            <span data-i18n="promo_code">Promo / Voucher Code</span>
                        </span>
                        @if(!empty($appliedVoucher))
                            <form method="POST" action="{{ route('cart.voucher.remove') }}" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="text-[11px] font-bold text-rose-700 hover:underline">Remove</button>
                            </form>
                        @endif
                    </div>

                    @if(!empty($appliedVoucher))
                        <div class="rounded-xl border border-emerald-300 bg-emerald-50 px-3 py-2 flex items-center justify-between gap-2 text-emerald-950">
                            <div class="min-w-0">
                                <p class="font-mono font-bold text-xs">{{ $appliedVoucher }}</p>
                                <p class="text-[10px] text-emerald-800">{{ $voucherLabel ?? 'Promo discount active' }}</p>
                            </div>
                            <span class="font-bold text-xs text-emerald-800 shrink-0">-৳<span x-text="activeDiscount.toLocaleString()">{{ number_format($discountAmount ?? 0) }}</span></span>
                        </div>
                    @else
                        <form method="POST" action="{{ route('cart.voucher.apply') }}" class="flex gap-2">
                            @csrf
                            <input
                                type="text"
                                name="voucher_code"
                                maxlength="30"
                                required
                                placeholder="Enter promo or voucher code"
                                class="flex-1 min-w-0 rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-xs font-mono font-bold uppercase text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
                            >
                            <button type="submit" class="shrink-0 rounded-xl bg-[var(--accent-dark)] px-3.5 py-2 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition" data-i18n="apply_btn">
                                Apply
                            </button>
                        </form>
                        <div class="flex flex-wrap gap-1.5 pt-0.5">
                            @foreach(['MERAMOT10' => '10% Off', 'FIRSTFIX100' => '৳100 Off', 'FREEDELIVERY' => 'Free Courier'] as $vCode => $vHint)
                                <form method="POST" action="{{ route('cart.voucher.apply') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="voucher_code" value="{{ $vCode }}">
                                    <button type="submit" class="rounded border border-[var(--border)] bg-[var(--card)] px-2.5 py-1 text-[10px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                                        {{ $vCode }} · {{ $vHint }}
                                    </button>
                                </form>
                            @endforeach
                            @if(isset($activeShop) && $activeShop && $activeShop->free_delivery)
                                <form method="POST" action="{{ route('cart.voucher.apply') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="voucher_code" value="SHOPFREE">
                                    <button type="submit" class="rounded border border-emerald-400 bg-emerald-50 px-2.5 py-1 text-[10px] font-bold text-emerald-900 hover:bg-emerald-100 transition">
                                        SHOPFREE · Shop Free Pickup
                                    </button>
                                </form>
                            @endif
                            @if(!empty($firstShopPromoEligible) && ($total ?? 0) >= 500)
                                <form method="POST" action="{{ route('cart.voucher.apply') }}" class="inline">
                                    @csrf
                                    <input type="hidden" name="voucher_code" value="FIRSTSHOP1">
                                    <button type="submit" class="rounded border border-amber-400 bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-900 hover:bg-amber-100 transition">
                                        FIRSTSHOP1 · First Order at Shop
                                    </button>
                                </form>
                            @endif
                        </div>
                    @endif
                </div>

                <div class="space-y-2.5 text-xs border-y border-[var(--border)] py-4">
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[var(--muted-foreground)]">{{ !empty($isSparePartCart) ? 'Parts Subtotal' : 'Services Subtotal' }}</span>
                        <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($total, 2) }}</span>
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-[var(--muted-foreground)]" data-i18n="buyer_protection_fee">Service &amp; Buyer Protection Fee</span>
                        <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($serviceCharge ?? 0, 2) }}</span>
                    </div>
                    <template x-if="isCourierMode">
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[var(--muted-foreground)]">Doorstep Pickup &amp; Delivery &middot; <span x-text="deliveryType === 'EXPRESS' ? 'Express' : 'Standard'">Standard</span></span>
                            <span class="font-bold text-[var(--foreground)] shrink-0">৳<span x-text="deliveryFee">{{ number_format($regCourierFee, 0) }}</span></span>
                        </div>
                    </template>
                    <template x-if="!isCourierMode">
                        <div class="flex items-center justify-between gap-2 text-emerald-800 font-semibold">
                            <span>In-Shop Walk-In (No Delivery Needed)</span>
                            <span class="shrink-0">৳0</span>
                        </div>
                    </template>
                    <template x-if="activeDiscount > 0">
                        <div class="flex items-center justify-between gap-2 text-emerald-800 font-bold">
                            <span>Voucher Savings (<span class="font-mono" x-text="appliedVoucher"></span>)</span>
                            <span class="shrink-0">-৳<span x-text="activeDiscount.toLocaleString()"></span></span>
                        </div>
                    </template>
                    <div class="pt-2 border-t border-[var(--border)] flex items-center justify-between gap-2 text-base">
                        <span class="font-bold text-[var(--foreground)]" data-i18n="total_payable">Total Payable</span>
                        <span class="text-2xl font-bold text-[var(--accent-dark)] shrink-0">৳<span x-text="grandTotal.toLocaleString()">{{ number_format(max(0, $total + ($serviceCharge ?? 0) + ($initFulfillment === 'IN_SHOP' && empty($isSparePartCart) ? 0 : $regCourierFee) - ($discountAmount ?? 0))) }}</span></span>
                    </div>
                </div>

                <!-- Payment Method Selector -->
                <div class="space-y-2 text-xs">
                    <label class="block font-bold text-[var(--foreground)]" data-i18n="payment_method">Payment Method</label>
                    <label class="cursor-pointer rounded-lg border p-3 flex items-center justify-between gap-2 transition" :class="paymentMethod === 'CASH' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)] font-bold' : 'border-[var(--border)] bg-[var(--card)]'">
                        <span class="flex items-center gap-2 text-[var(--foreground)]">
                            <input type="radio" value="CASH" x-model="paymentMethod" class="accent-[#244229]">
                            <span x-text="isCourierMode ? 'Cash on Return (COD)' : 'Cash at Workshop (On Completion)'">Cash on Return (COD)</span>
                        </span>
                        <i class="fa-solid fa-money-bill-wave text-emerald-700 shrink-0"></i>
                    </label>
                    <label class="cursor-pointer rounded-lg border p-3 flex items-center justify-between gap-2 transition" :class="paymentMethod === 'SSLCOMMERZ' ? 'border-[var(--accent-dark)] bg-[var(--mint-100)] font-bold' : 'border-[var(--border)] bg-[var(--card)]'">
                        <span class="flex items-center gap-2 text-[var(--foreground)]">
                            <input type="radio" value="SSLCOMMERZ" x-model="paymentMethod" class="accent-[#244229]">
                            <span data-i18n="ssl_online_payment">SSLCommerz Online Payment (All Channels)</span>
                        </span>
                        <i class="fa-solid fa-lock text-[var(--accent-dark)] shrink-0"></i>
                    </label>
                    <div class="grid grid-cols-3 gap-1.5">
                        <label class="cursor-pointer rounded-xl border p-2 text-center transition" :class="paymentMethod === 'BKASH' ? 'border-pink-600 bg-pink-50 font-bold text-pink-900' : 'border-[var(--border)] bg-[var(--card)] text-[var(--foreground)]'">
                            <input type="radio" value="BKASH" x-model="paymentMethod" class="sr-only">
                            <span class="block text-[11px] font-bold">bKash</span>
                            <span class="block text-[9px] opacity-75">Wallet</span>
                        </label>
                        <label class="cursor-pointer rounded-xl border p-2 text-center transition" :class="paymentMethod === 'NAGAD' ? 'border-orange-600 bg-orange-50 font-bold text-orange-900' : 'border-[var(--border)] bg-[var(--card)] text-[var(--foreground)]'">
                            <input type="radio" value="NAGAD" x-model="paymentMethod" class="sr-only">
                            <span class="block text-[11px] font-bold">Nagad</span>
                            <span class="block text-[9px] opacity-75">Wallet</span>
                        </label>
                        <label class="cursor-pointer rounded-xl border p-2 text-center transition" :class="paymentMethod === 'CARD' ? 'border-sky-700 bg-sky-50 font-bold text-sky-900' : 'border-[var(--border)] bg-[var(--card)] text-[var(--foreground)]'">
                            <input type="radio" value="CARD" x-model="paymentMethod" class="sr-only">
                            <span class="block text-[11px] font-bold">Visa/MC</span>
                            <span class="block text-[9px] opacity-75">Card</span>
                        </label>
                    </div>
                </div>

                <div class="rounded-lg bg-[var(--mint-100)] border border-[var(--border)] p-3.5 text-[11px] text-[var(--muted-foreground)] leading-relaxed">
                    <i class="fa-solid fa-circle-info text-[var(--accent-dark)] mr-1"></i>
                    Your order goes straight to the selected workshop. Any extra work identified on the bench always requires your approval first.
                </div>

                <button
                    type="submit"
                    form="direct-order-form"
                    class="w-full rounded-md bg-[var(--accent-dark)] px-6 py-3.5 text-xs sm:text-sm font-bold text-[var(--accent-foreground)] shadow-lg hover:opacity-90 transition flex items-center justify-center gap-2"
                >
                    <span data-i18n="confirm_booking">Confirm Booking</span>
                    <i class="fa-solid fa-arrow-right"></i>
                </button>
            </div>

        </div>
    @endif

</div>
@endsection

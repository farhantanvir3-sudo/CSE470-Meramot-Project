@extends('layouts.app')

@section('title', 'Booking Checkout | Meramot')

{{-- ==========================================================================
     FEATURE R8: Direct-Order Checkout Confirmation Screen
     Assigned Member: Shanid Sajjatuz Islam [23201465]
     Merged UI & Direct-Order Flow: Farhan Tanvir [22299179]
     ========================================================================== --}}

@section('content')
@php
    $regCourierFee = (float) ($regularDeliveryFee ?? 80);
    $expCourierFee = (float) ($expressDeliveryFee ?? 150);
    $ratePctLabel = rtrim(rtrim(number_format((float) ($commissionRatePct ?? 8), 2), '0'), '.');
    $allowCourier = (bool) ($canCourierPickup ?? true);
    $allowInShop = (bool) ($canInShopRepair ?? true);
    $initFulfillment = old('fulfillment_mode', $defaultFulfillmentMode ?? 'COURIER_PICKUP');
    if ($initFulfillment === 'COURIER_PICKUP' && !$allowCourier && $allowInShop) {
        $initFulfillment = 'IN_SHOP';
    } elseif ($initFulfillment === 'IN_SHOP' && !$allowInShop && $allowCourier) {
        $initFulfillment = 'COURIER_PICKUP';
    }
@endphp
<div class="mx-auto max-w-5xl px-4 py-6 md:px-6" x-data="{
    fulfillmentMode: @js($initFulfillment),
    isSparePartCart: @js(!empty($isSparePartCart)),
    canCourierPickup: @js($allowCourier),
    canInShopRepair: @js($allowInShop),
    scheduleType: '{{ old('schedule_type', 'NOW') }}',
    deliveryType: '{{ old('delivery_type', 'REGULAR') }}',
    paymentMethod: '{{ old('payment_method', $appliedVoucherMeta['required_payment_method'] ?? 'SSLCOMMERZ') }}',
    checkoutAddress: @js(old('address', auth()->user()->address ?? ($customerLocation['address'] ?? ''))),
    checkoutArea: @js(old('area', auth()->user()->area ?? ($customerLocation['area'] ?? 'Banani'))),
    subtotal: {{ (float) ($total ?? 0) }},
    serviceCharge: {{ (float) ($serviceCharge ?? 0) }},
    regularFee: {{ $regCourierFee }},
    expressFee: {{ $expCourierFee }},
    appliedVoucher: @js($appliedVoucher ?? ''),
    appliedVoucherMeta: @js($appliedVoucherMeta ?? null),
    baseDiscount: {{ (float) ($discountAmount ?? 0) }},
    get isCourierMode() { return this.isSparePartCart || this.fulfillmentMode === 'COURIER_PICKUP'; },
    get deliveryFee() {
        if (!this.isCourierMode) return 0;
        return this.deliveryType === 'EXPRESS' ? this.expressFee : this.regularFee;
    },
    get activeDiscount() {
        if (!this.appliedVoucher) return 0;
        const m = this.appliedVoucherMeta;
        if (!m) return this.baseDiscount;
        if (m.required_payment_method && String(this.paymentMethod).toUpperCase() !== String(m.required_payment_method).toUpperCase()) {
            return 0;
        }
        if (m.min_order_amount && this.subtotal + 0.009 < Number(m.min_order_amount)) {
            return 0;
        }
        if (m.discount_type === 'FREE_COURIER') {
            if (!this.isCourierMode) return 0;
            const cap = m.max_discount_amount ? Number(m.max_discount_amount) : null;
            return cap ? Math.min(this.deliveryFee, cap) : this.deliveryFee;
        }
        if (m.discount_type === 'PERCENTAGE') {
            const raw = Math.round(this.subtotal * (Number(m.discount_value) / 100) * 100) / 100;
            const cap = m.max_discount_amount ? Number(m.max_discount_amount) : null;
            return Math.min(this.subtotal, cap ? Math.min(cap, raw) : raw);
        }
        return Math.min(this.subtotal, Number(m.discount_value || this.baseDiscount));
    },
    get paymentMismatchWarning() {
        const m = this.appliedVoucherMeta;
        if (m && m.required_payment_method && String(this.paymentMethod).toUpperCase() !== String(m.required_payment_method).toUpperCase()) {
            return `Voucher ${this.appliedVoucher} requires paying with ${m.required_payment_method}.`;
        }
        return null;
    },
    get grandTotal() { return Math.max(0, this.subtotal + this.serviceCharge + this.deliveryFee - this.activeDiscount); },
    applyMapLocation(loc) {
        if (!loc) return;
        if (loc.address) {
            this.checkoutAddress = loc.address;
        }
        if (loc.area) {
            this.checkoutArea = loc.area;
        }
    }
}" @meramot-location-changed.window="applyMapLocation($event.detail)">
    <div class="mb-6">
        <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
            <a href="{{ route('cart.view') }}" class="hover:underline" data-i18n="back_to_cart">&larr; Back to Cart</a>
        </div>
        <h1 class="text-xl md:text-2xl font-bold tracking-tight text-[var(--foreground)]" data-i18n="booking_checkout">Booking Checkout</h1>
        <p class="mt-1 text-xs text-[var(--muted-foreground)]" data-i18n="booking_checkout_sub">Confirm fulfillment, schedule, and payment details.</p>
    </div>

    @if(session('error'))
        <div class="mb-5 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-rose-700 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-5 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-950 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-amber-700 shrink-0"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-900 space-y-1 shadow-sm">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form action="{{ route('cart.place') }}" method="POST" class="grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)] items-start text-xs">
        @csrf

        <!-- Left: Order Items & Pickup Details -->
        <div class="min-w-0 space-y-6">
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm space-y-4">
                <h2 class="text-base font-bold text-[var(--foreground)] border-b border-[var(--border)] pb-3" data-i18n="workshop_pickup_details">
                    Workshop &amp; Pickup Details
                </h2>

                <div class="min-w-0">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        {{ !empty($isSparePartCart) ? 'Dispatched By Seller Workshop' : 'Assigned Local Workshop' }}
                    </label>
                    @if((!empty($shopLocked) || !empty($isSparePartCart)) && isset($activeShop) && $activeShop)
                        <input type="hidden" name="shop_id" value="{{ $activeShop->id }}">
                        <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-bold text-[var(--foreground)] flex flex-wrap items-center justify-between gap-2">
                            <span>
                                ✔ {{ $activeShop->name }}{{ $activeShop->display_branch_tag ? ' [' . $activeShop->display_branch_tag . ']' : '' }}
                            </span>
                            <span class="text-[11px] text-[var(--muted-foreground)]">
                                📍 {{ collect([$activeShop->area, $activeShop->city])->filter()->unique()->implode(', ') }}
                            </span>
                        </div>
                    @else
                        <select name="shop_id" class="w-full min-w-0 max-w-full truncate rounded-lg border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                            @forelse($verifiedShops ?? [] as $vShop)
                                <option value="{{ $vShop->id }}" {{ (isset($activeShop) && $activeShop && $activeShop->id === $vShop->id) ? 'selected' : '' }}>
                                    {{ \Illuminate\Support\Str::limit($vShop->name, 26) }}{{ $vShop->display_branch_tag ? ' [' . \Illuminate\Support\Str::limit($vShop->display_branch_tag, 18) . ']' : ' (' . \Illuminate\Support\Str::limit($vShop->area ?: $vShop->city, 16) . ')' }} • {{ (float) $vShop->rating > 0 ? '★' . number_format($vShop->rating, 1) : 'Verified' }}{{ isset($vShop->distance_km) ? ' • ' . number_format($vShop->distance_km, 1) . ' km' : '' }}
                                </option>
                            @empty
                                <option value="">Nearest Verified Workshop</option>
                            @endforelse
                        </select>
                    @endif
                </div>

                @if(empty($isSparePartCart))
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-2" data-i18n="fulfillment_option_req">Service Fulfillment Option *</label>
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
                                            {{ $allowCourier ? 'A rider picks up your device from home and returns it after repair.' : 'Unavailable for walk-in only shops.' }}
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
                                        <span class="font-bold text-[var(--foreground)] block" data-i18n="in_shop_walk_in">In-Shop Walk-In</span>
                                        <span class="text-[11px] text-[var(--muted-foreground)] block leading-snug">
                                            {{ $allowInShop ? 'Visit the workshop in person and pay via Meramot — zero courier fee.' : 'Courier-only service center.' }}
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

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1" x-text="isCourierMode ? 'Pickup Schedule *' : 'Visit Schedule *'">Pickup Schedule *</label>
                        <select name="schedule_type" x-model="scheduleType" class="w-full min-w-0 max-w-full rounded-lg border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                            <option value="NOW" x-text="isCourierMode ? 'Dispatch Now' : 'Drop Off Today'">Dispatch Now</option>
                            <option value="LATER" data-i18n="schedule_later">Schedule for Later</option>
                        </select>
                    </div>
                    <div x-show="isCourierMode" x-cloak>
                        <label class="block font-bold text-[var(--foreground)] mb-1" data-i18n="courier_tier_label">Courier Tier *</label>
                        <select name="delivery_type" x-model="deliveryType" :disabled="!isCourierMode" class="w-full min-w-0 max-w-full rounded-lg border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                            <option value="REGULAR">Regular Courier (৳{{ number_format($regCourierFee, 0) }})</option>
                            <option value="EXPRESS">Express Priority (৳{{ number_format($expCourierFee, 0) }})</option>
                        </select>
                    </div>
                </div>

                @if(isset($activeShop) && $activeShop)
                    <div x-show="!isCourierMode" x-cloak class="rounded-lg border border-emerald-300 bg-emerald-50/75 p-4 text-xs space-y-1">
                        <div class="font-bold text-emerald-950 flex items-center gap-2">
                            <i class="fa-solid fa-store text-emerald-700"></i>
                            <span>In-Shop Service Location (Zero Delivery Fee)</span>
                        </div>
                        <p class="font-bold text-[var(--foreground)]">{{ $activeShop->name }}{{ $activeShop->display_branch_tag ? ' [' . $activeShop->display_branch_tag . ']' : '' }}</p>
                        <p class="text-[var(--muted-foreground)]">{{ $activeShop->public_address }}</p>
                        <p class="text-[11px] text-emerald-800 pt-0.5">Bring your device to the counter during service hours. All payments remain protected through Meramot.</p>
                    </div>
                @endif

                <div x-show="scheduleType === 'LATER'" x-cloak>
                    <label class="block font-bold text-[var(--foreground)] mb-1" data-i18n="preferred_datetime_req">Preferred Date &amp; Time *</label>
                    <input
                        type="datetime-local"
                        name="scheduled_at"
                        value="{{ old('scheduled_at') }}"
                        class="w-full sm:w-72 rounded-lg border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none"
                    >
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <label class="block font-bold text-[var(--foreground)]" data-i18n="contact_phone">Contact Phone *</label>
                            @auth
                                @if(empty(auth()->user()->phone))
                                    <a href="/profile?edit=1" class="text-[10px] font-bold text-[var(--accent-dark)] hover:underline whitespace-nowrap">Manage Profile</a>
                                @endif
                            @endauth
                        </div>
                        <input
                            type="tel"
                            name="phone"
                            autocomplete="tel"
                            inputmode="tel"
                            maxlength="14"
                            required
                            placeholder="01XXXXXXXXX"
                            value="{{ old('phone', auth()->check() ? (auth()->user()->phone ?? '') : '01712345678') }}"
                            class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[var(--foreground)] outline-none"
                        >
                    </div>
                    <div x-show="isCourierMode" x-cloak>
                        <label class="block font-bold text-[var(--foreground)] mb-1" data-i18n="pickup_area">Pickup Area</label>
                        <input
                            type="text"
                            name="area"
                            autocomplete="address-level3"
                            x-model="checkoutArea"
                            placeholder="e.g. Banani, Panthapath"
                            class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[var(--foreground)] outline-none"
                        >
                    </div>
                </div>

                <div x-show="isCourierMode" x-cloak>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                        <label class="block font-bold text-[var(--foreground)]" data-i18n="pickup_street_address_req">Pickup Street Address *</label>
                        <div class="flex items-center gap-2">
                            @auth
                                @if(!empty(auth()->user()->address))
                                    <button
                                        type="button"
                                        @click="checkoutAddress = @js(auth()->user()->address)"
                                        class="rounded border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1 text-[10px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] whitespace-nowrap"
                                    >
                                        Use Profile Address
                                    </button>
                                @endif
                            @endauth
                            <button
                                type="button"
                                @click="$dispatch('meramot-open-location-modal')"
                                class="rounded bg-[var(--accent-dark)] px-3 py-1 text-[10px] font-bold text-white hover:opacity-90 whitespace-nowrap"
                            >
                                <i class="fa-solid fa-location-dot mr-1"></i>Pick on Map
                            </button>
                        </div>
                    </div>
                    <textarea name="address" x-model="checkoutAddress" rows="3" :required="isCourierMode" placeholder="House or Flat #, Road #, Area, City" class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[var(--foreground)] outline-none"></textarea>
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1" data-i18n="device_notes_optional">Device Model &amp; Notes (Optional)</label>
                    <textarea name="problem_note" rows="2" placeholder="Device model or bench handling notes..." class="w-full rounded-lg border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[var(--foreground)] outline-none">{{ old('problem_note') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Right: Summary & Payment -->
        <div class="min-w-0 rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm space-y-4">
            <h2 class="text-base font-bold text-[var(--foreground)]" data-i18n="order_summary">Order Summary</h2>

            <div class="space-y-2 border-b border-[var(--border)] pb-3">
                @foreach($cart as $item)
                    <div class="flex justify-between gap-2">
                        <span class="min-w-0 text-[var(--foreground)] break-words">{{ $item['title'] }} × {{ $item['quantity'] }}</span>
                        <span class="font-bold text-[var(--accent-dark)] shrink-0">৳{{ number_format($item['price'] * $item['quantity']) }}</span>
                    </div>
                @endforeach
            </div>

            @include('partials.voucher-modal', [
                'applyActionUrl' => route('cart.voucher.apply'),
                'removeActionUrl' => route('cart.voucher.remove'),
                'appliedCode' => $appliedVoucher ?? null,
                'appliedLabel' => $voucherLabel ?? null,
                'appliedDiscount' => $discountAmount ?? 0,
                'availableVouchers' => $availableCheckoutVouchers ?? [],
            ])
            <template x-if="paymentMismatchWarning">
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-3 py-2 text-[11px] font-semibold text-amber-950">
                    <i class="fa-solid fa-triangle-exclamation text-amber-700 mr-1"></i>
                    <span x-text="paymentMismatchWarning"></span>
                </div>
            </template>

            <div class="space-y-1.5">
                <div class="flex justify-between gap-2 text-[var(--muted-foreground)]">
                    <span>{{ !empty($isSparePartCart) ? 'Parts Subtotal' : 'Services Subtotal' }}</span>
                    <span class="shrink-0 font-semibold text-[var(--foreground)]">৳{{ number_format($total, 2) }}</span>
                </div>
                <div class="flex justify-between gap-2 text-[var(--muted-foreground)]">
                    <span data-i18n="buyer_protection_fee">Service &amp; Buyer Protection Fee</span>
                    <span class="shrink-0 font-semibold text-[var(--foreground)]">৳{{ number_format($serviceCharge ?? 0, 2) }}</span>
                </div>
                <template x-if="isCourierMode">
                    <div class="flex justify-between gap-2 text-[var(--muted-foreground)]">
                        <span>Doorstep Pickup &amp; Delivery &middot; <span x-text="deliveryType === 'EXPRESS' ? 'Express' : 'Standard'">Standard</span></span>
                        <span class="shrink-0 font-semibold text-[var(--foreground)]">৳<span x-text="deliveryFee">{{ number_format($regCourierFee, 0) }}</span></span>
                    </div>
                </template>
                <template x-if="!isCourierMode">
                    <div class="flex justify-between gap-2 text-emerald-800 font-semibold">
                        <span>In-Shop Walk-In (No Delivery Needed)</span>
                        <span class="shrink-0">৳0</span>
                    </div>
                </template>
                <template x-if="activeDiscount > 0">
                    <div class="flex justify-between gap-2 text-emerald-800 font-bold">
                        <span>Voucher (<span class="font-mono" x-text="appliedVoucher"></span>)</span>
                        <span class="shrink-0">-৳<span x-text="activeDiscount.toLocaleString()"></span></span>
                    </div>
                </template>
                <div class="pt-2 border-t border-[var(--border)] flex justify-between gap-2 text-base font-bold text-[var(--foreground)]">
                    <span data-i18n="total_label">Total</span>
                    <span class="text-[var(--accent-dark)] shrink-0">৳<span x-text="grandTotal.toLocaleString()">{{ number_format(max(0, $total + ($serviceCharge ?? 0) + ($initFulfillment === 'IN_SHOP' && empty($isSparePartCart) ? 0 : $regCourierFee) - ($discountAmount ?? 0))) }}</span></span>
                </div>
            </div>

            <input type="hidden" name="voucher_code" value="{{ $appliedVoucher ?? '' }}">

            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1" data-i18n="payment_method">Payment Method</label>
                <select name="payment_method" x-model="paymentMethod" class="w-full min-w-0 max-w-full rounded-lg border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                    <option value="SSLCOMMERZ" data-i18n="ssl_online_payment">SSLCommerz Online Payment (All Channels)</option>
                    <option value="BKASH" data-i18n="bkash_online_payment">bKash Online Payment</option>
                    <option value="NAGAD" data-i18n="nagad_online_payment">Nagad Online Payment</option>
                    <option value="CARD">Visa / Mastercard</option>
                    <option value="CASH" x-text="isCourierMode ? 'Cash on Device Return (COD)' : 'Cash at Workshop (On Completion)'">Cash on Device Return (COD)</option>
                </select>
            </div>

            <button type="submit" class="w-full rounded-md bg-[var(--accent-dark)] py-3 px-5 font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition" data-i18n="confirm_booking">
                Confirm Booking
            </button>
        </div>
    </form>
</div>
@endsection

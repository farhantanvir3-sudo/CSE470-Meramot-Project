@extends('layouts.app')

@section('title', 'Direct-Order Checkout | Meramot')

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
@endphp
<div class="mx-auto max-w-5xl px-4 py-6 md:px-6" x-data="{
    scheduleType: '{{ old('schedule_type', 'NOW') }}',
    deliveryType: '{{ old('delivery_type', 'REGULAR') }}',
    paymentMethod: '{{ old('payment_method', 'SSLCOMMERZ') }}',
    checkoutAddress: @js(old('address', auth()->user()->address ?? ($customerLocation['address'] ?? ''))),
    checkoutArea: @js(old('area', auth()->user()->area ?? ($customerLocation['area'] ?? 'Banani'))),
    subtotal: {{ (float) ($total ?? 0) }},
    serviceCharge: {{ (float) ($serviceCharge ?? 0) }},
    regularFee: {{ $regCourierFee }},
    expressFee: {{ $expCourierFee }},
    appliedVoucher: @js($appliedVoucher ?? ''),
    baseDiscount: {{ (float) ($discountAmount ?? 0) }},
    get deliveryFee() { return this.deliveryType === 'EXPRESS' ? this.expressFee : this.regularFee; },
    get activeDiscount() {
        if (this.appliedVoucher === 'FREEDELIVERY') return this.deliveryFee;
        if (this.appliedVoucher === 'SHOPFREE') return Math.min(this.deliveryFee, this.regularFee);
        return this.baseDiscount;
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
            <a href="{{ route('cart.view') }}" class="hover:underline">&larr; Back to Cart</a>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Direct-Order Checkout</h1>
        <p class="mt-1 text-xs text-[var(--muted-foreground)]">Confirm your workshop, pickup schedule, and payment preference.</p>
    </div>

    @if(session('error'))
        <div class="mb-5 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-exclamation text-rose-700 shrink-0"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div class="mb-5 rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-950 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation text-amber-700 shrink-0"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-900 space-y-1 shadow-sm">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form action="{{ route('cart.place') }}" method="POST" class="grid gap-6 lg:grid-cols-[minmax(0,1.55fr)_minmax(0,1fr)] items-start text-xs">
        @csrf

        <!-- Left: Order Items & Pickup Details -->
        <div class="min-w-0 space-y-6">
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm space-y-4">
                <h2 class="text-base font-black text-[var(--foreground)] border-b border-[var(--border)] pb-3">
                    Workshop &amp; Pickup Details
                </h2>

                <div class="min-w-0">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        {{ !empty($isSparePartCart) ? 'Dispatched By Seller Workshop' : 'Assigned Local Workshop' }}
                    </label>
                    @if((!empty($shopLocked) || !empty($isSparePartCart)) && isset($activeShop) && $activeShop)
                        <input type="hidden" name="shop_id" value="{{ $activeShop->id }}">
                        <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-bold text-[var(--foreground)] flex flex-wrap items-center justify-between gap-2">
                            <span>
                                ✔ {{ $activeShop->name }}{{ $activeShop->display_branch_tag ? ' [' . $activeShop->display_branch_tag . ']' : '' }}
                            </span>
                            <span class="text-[11px] text-[var(--muted-foreground)]">
                                📍 {{ collect([$activeShop->area, $activeShop->city])->filter()->unique()->implode(', ') }}
                            </span>
                        </div>
                    @else
                        <select name="shop_id" class="w-full min-w-0 max-w-full truncate rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                            @forelse($verifiedShops ?? [] as $vShop)
                                <option value="{{ $vShop->id }}" {{ (isset($activeShop) && $activeShop && $activeShop->id === $vShop->id) ? 'selected' : '' }}>
                                    {{ \Illuminate\Support\Str::limit($vShop->name, 26) }}{{ $vShop->display_branch_tag ? ' [' . \Illuminate\Support\Str::limit($vShop->display_branch_tag, 18) . ']' : ' (' . \Illuminate\Support\Str::limit($vShop->area ?: $vShop->city, 16) . ')' }} • ★{{ number_format($vShop->rating, 1) }}{{ isset($vShop->distance_km) ? ' • ' . number_format($vShop->distance_km, 1) . ' km' : '' }}
                                </option>
                            @empty
                                <option value="">Auto-match nearest workshop</option>
                            @endforelse
                        </select>
                    @endif
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Pickup / Dispatch Schedule *</label>
                        <select name="schedule_type" x-model="scheduleType" class="w-full min-w-0 max-w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                            <option value="NOW">Dispatch Now</option>
                            <option value="LATER">Schedule for Later</option>
                        </select>
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Courier Tier *</label>
                        <select name="delivery_type" x-model="deliveryType" class="w-full min-w-0 max-w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                            <option value="REGULAR">Regular Courier (৳{{ number_format($regCourierFee, 0) }})</option>
                            <option value="EXPRESS">Express Priority (৳{{ number_format($expCourierFee, 0) }})</option>
                        </select>
                    </div>
                </div>

                <div x-show="scheduleType === 'LATER'" x-cloak>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Preferred Date &amp; Time *</label>
                    <input
                        type="datetime-local"
                        name="scheduled_at"
                        value="{{ old('scheduled_at') }}"
                        class="w-full sm:w-72 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 text-xs font-semibold text-[var(--foreground)] outline-none"
                    >
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between gap-2 mb-1">
                            <label class="block font-bold text-[var(--foreground)]">Contact Phone *</label>
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
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[var(--foreground)] outline-none"
                        >
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Pickup Area</label>
                        <input
                            type="text"
                            name="area"
                            autocomplete="address-level3"
                            x-model="checkoutArea"
                            placeholder="e.g. Banani, Panthapath"
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[var(--foreground)] outline-none"
                        >
                    </div>
                </div>

                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                        <label class="block font-bold text-[var(--foreground)]">Pickup Street Address *</label>
                        <div class="flex items-center gap-2">
                            @auth
                                @if(!empty(auth()->user()->address))
                                    <button
                                        type="button"
                                        @click="checkoutAddress = @js(auth()->user()->address)"
                                        class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1 text-[10px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] whitespace-nowrap"
                                    >
                                        Use Profile Address
                                    </button>
                                @endif
                            @endauth
                            <button
                                type="button"
                                @click="$dispatch('meramot-open-location-modal')"
                                class="rounded-full bg-[var(--accent-dark)] px-3 py-1 text-[10px] font-bold text-white hover:opacity-90 whitespace-nowrap"
                            >
                                <i class="fa-solid fa-location-dot mr-1"></i>Pick on Map
                            </button>
                        </div>
                    </div>
                    <textarea name="address" x-model="checkoutAddress" rows="3" required placeholder="House or Flat #, Road #, Area, City" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[var(--foreground)] outline-none"></textarea>
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Device Model &amp; Notes (Optional)</label>
                    <textarea name="problem_note" rows="2" placeholder="Device model or bench handling notes..." class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[var(--foreground)] outline-none">{{ old('problem_note') }}</textarea>
                </div>
            </div>
        </div>

        <!-- Right: Summary & Payment -->
        <div class="min-w-0 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm space-y-4">
            <h2 class="text-base font-black text-[var(--foreground)]">Order Summary</h2>

            <div class="space-y-2 border-b border-[var(--border)] pb-3">
                @foreach($cart as $item)
                    <div class="flex justify-between gap-2">
                        <span class="min-w-0 text-[var(--foreground)] break-words">{{ $item['title'] }} × {{ $item['quantity'] }}</span>
                        <span class="font-bold text-[var(--accent-dark)] shrink-0">৳{{ number_format($item['price'] * $item['quantity']) }}</span>
                    </div>
                @endforeach
            </div>

            <div class="space-y-1.5">
                <div class="flex justify-between gap-2 text-[var(--muted-foreground)]">
                    <span>{{ !empty($isSparePartCart) ? 'Parts Subtotal' : 'Services Subtotal' }}</span>
                    <span class="shrink-0 font-semibold text-[var(--foreground)]">৳{{ number_format($total, 2) }}</span>
                </div>
                <div class="flex justify-between gap-2 text-[var(--muted-foreground)]">
                    <span>Service &amp; Buyer Protection Fee</span>
                    <span class="shrink-0 font-semibold text-[var(--foreground)]">৳{{ number_format($serviceCharge ?? 0, 2) }}</span>
                </div>
                <div class="flex justify-between gap-2 text-[var(--muted-foreground)]">
                    <span>Doorstep Pickup &amp; Delivery (<span x-text="deliveryType === 'EXPRESS' ? 'Express' : 'Regular'">Regular</span>)</span>
                    <span class="shrink-0 font-semibold text-[var(--foreground)]">৳<span x-text="deliveryFee">{{ number_format($regCourierFee, 0) }}</span></span>
                </div>
                <template x-if="activeDiscount > 0">
                    <div class="flex justify-between gap-2 text-emerald-800 font-bold">
                        <span>Voucher (<span class="font-mono" x-text="appliedVoucher"></span>)</span>
                        <span class="shrink-0">-৳<span x-text="activeDiscount.toLocaleString()"></span></span>
                    </div>
                </template>
                <div class="pt-2 border-t border-[var(--border)] flex justify-between gap-2 text-base font-black text-[var(--foreground)]">
                    <span>Total</span>
                    <span class="text-[var(--accent-dark)] shrink-0">৳<span x-text="grandTotal.toLocaleString()">{{ number_format(max(0, $total + ($serviceCharge ?? 0) + $regCourierFee - ($discountAmount ?? 0))) }}</span></span>
                </div>
            </div>

            <input type="hidden" name="voucher_code" value="{{ $appliedVoucher ?? '' }}">

            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">Payment Method</label>
                <select name="payment_method" x-model="paymentMethod" class="w-full min-w-0 max-w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                    <option value="SSLCOMMERZ">SSLCommerz Online Escrow (All Channels)</option>
                    <option value="BKASH">bKash Online Escrow</option>
                    <option value="NAGAD">Nagad Online Escrow</option>
                    <option value="CARD">Visa / Mastercard Escrow</option>
                    <option value="CASH">Cash on Device Return (COD)</option>
                </select>
            </div>

            <button type="submit" class="w-full rounded-full bg-[var(--accent-dark)] py-3 px-5 font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                Place Direct Order
            </button>
        </div>
    </form>
</div>
@endsection

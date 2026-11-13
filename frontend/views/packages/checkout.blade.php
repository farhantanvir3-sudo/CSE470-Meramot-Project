@extends('layouts.app')

@section('title', 'Direct-Order Checkout | Meramot')

{{-- ==========================================================================
     FEATURE R8: Direct-Order Checkout Confirmation Screen
     Assigned Member: Shanid Sajjatuz Islam [23201465]
     Merged UI & Direct-Order Flow: Farhan Tanvir [22299179]
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 md:px-6" x-data="{
    deliveryType: 'REGULAR',
    paymentMethod: 'SSLCOMMERZ',
    checkoutAddress: @js(old('address', auth()->user()->address ?? '')),
    subtotal: {{ (float) ($total ?? 0) }},
    serviceCharge: {{ (float) ($serviceCharge ?? 30) }},
    get deliveryFee() { return this.deliveryType === 'EXPRESS' ? 150 : 80; },
    get grandTotal() { return this.subtotal + this.serviceCharge + this.deliveryFee; },
    applyMapLocation(loc) {
        if (loc && loc.address) {
            this.checkoutAddress = loc.address;
        }
    }
}" @meramot-location-changed.window="applyMapLocation($event.detail)">
    <div class="mb-6">
        <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
            <a href="{{ route('cart.view') }}" class="hover:underline">&larr; Back to Cart</a>
        </div>
        <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Direct-Order Checkout</h1>
    </div>

    <form action="{{ route('cart.place') }}" method="POST" class="grid gap-6 lg:grid-cols-3 items-start text-xs">
        @csrf
        <input type="hidden" name="schedule_type" value="NOW">

        <!-- Left: Order Items & Pickup Details -->
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
                <h2 class="text-base font-black text-[var(--foreground)] border-b border-[var(--border)] pb-3">
                    Workshop & Pickup Details
                </h2>

                <div class="min-w-0">
                    <label class="block font-bold text-[var(--foreground)] mb-1">Selected Local Workshop</label>
                    <select name="shop_id" class="w-full max-w-full truncate rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                        @foreach($verifiedShops ?? [] as $vShop)
                            <option value="{{ $vShop->id }}" {{ (isset($activeShop) && $activeShop && $activeShop->id === $vShop->id) ? 'selected' : '' }}>
                                {{ $vShop->name }}{{ $vShop->display_branch_tag ? ' [' . $vShop->display_branch_tag . ']' : ' (' . $vShop->area . ')' }} • ★{{ number_format($vShop->rating, 1) }}{{ isset($vShop->distance_km) ? ' • ' . number_format($vShop->distance_km, 1) . ' km' : '' }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <div class="flex items-center justify-between mb-1">
                            <label class="block font-bold text-[var(--foreground)]">Contact Phone *</label>
                            @auth
                                @if(empty(auth()->user()->phone))
                                    <a href="/profile?edit=1" class="text-[10px] font-bold text-[var(--accent-dark)] hover:underline">Manage in Profile</a>
                                @endif
                            @endauth
                        </div>
                        <input
                            type="tel"
                            name="phone"
                            required
                            placeholder="01XXXXXXXXX"
                            value="{{ old('phone', auth()->check() ? (auth()->user()->phone ?? '') : '01712345678') }}"
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[var(--foreground)] outline-none"
                        >
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Courier Tier *</label>
                        <select name="delivery_type" x-model="deliveryType" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                            <option value="REGULAR">Regular Courier (৳80)</option>
                            <option value="EXPRESS">Express Priority (৳150)</option>
                        </select>
                    </div>
                </div>

                <div>
                    <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                        <label class="block font-bold text-[var(--foreground)]">Pickup &amp; Delivery Street Address *</label>
                        <div class="flex items-center gap-2">
                            @auth
                                @if(!empty(auth()->user()->address))
                                    <button
                                        type="button"
                                        @click="checkoutAddress = @js(auth()->user()->address)"
                                        class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1 text-[10px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)]"
                                    >
                                        Use Profile Address
                                    </button>
                                @endif
                            @endauth
                            <button
                                type="button"
                                @click="$dispatch('meramot-open-location-modal')"
                                class="rounded-full bg-[var(--accent-dark)] px-3 py-1 text-[10px] font-bold text-white hover:opacity-90"
                            >
                                <i class="fa-solid fa-location-dot mr-1"></i>Pick on Map
                            </button>
                        </div>
                    </div>
                    <textarea name="address" x-model="checkoutAddress" rows="3" required placeholder="House or Flat #, Road #, Area, City" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[var(--foreground)] outline-none"></textarea>
                </div>
            </div>
        </div>

        <!-- Right: Summary & Payment -->
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <h2 class="text-base font-black text-[var(--foreground)]">Order Summary</h2>

            <div class="space-y-2 border-b border-[var(--border)] pb-3">
                @foreach($cart as $item)
                    <div class="flex justify-between gap-2">
                        <span class="text-[var(--foreground)]">{{ $item['title'] }} × {{ $item['quantity'] }}</span>
                        <span class="font-bold text-[var(--accent-dark)]">৳{{ number_format($item['price'] * $item['quantity']) }}</span>
                    </div>
                @endforeach
            </div>

            <div class="space-y-1.5">
                <div class="flex justify-between text-[var(--muted-foreground)]">
                    <span>Subtotal</span>
                    <span>৳{{ number_format($total) }}</span>
                </div>
                <div class="flex justify-between text-[var(--muted-foreground)]">
                    <span>Escrow Fee (5%)</span>
                    <span>৳{{ number_format($serviceCharge ?? 30) }}</span>
                </div>
                <div class="flex justify-between text-[var(--muted-foreground)]">
                    <span>Courier Fee</span>
                    <span>৳<span x-text="deliveryFee">80</span></span>
                </div>
                <div class="pt-2 border-t border-[var(--border)] flex justify-between text-base font-black text-[var(--foreground)]">
                    <span>Total</span>
                    <span class="text-[var(--accent-dark)]">৳<span x-text="grandTotal.toLocaleString()">{{ number_format($total + ($serviceCharge ?? 30) + 80) }}</span></span>
                </div>
            </div>

            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">Payment Method</label>
                <select name="payment_method" x-model="paymentMethod" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-2.5 font-semibold text-[var(--foreground)] outline-none">
                    <option value="SSLCOMMERZ">SSLCommerz Online Escrow</option>
                    <option value="CASH">Cash on Device Return</option>
                </select>
            </div>

            <button type="submit" class="w-full rounded-full bg-[var(--accent-dark)] py-3 font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                Place Direct Order
            </button>
        </div>
    </form>
</div>
@endsection

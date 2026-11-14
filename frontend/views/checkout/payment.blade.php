{{--
=============================================================================
FEATURE R17: SSLCommerz/Stripe Online Payment Gateway & PDF Invoice
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
Architectural Layer: View (Checkout/Payment)
Controller: PaymentGatewayController@showCheckout, initiatePayment
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Secure Checkout & Payment | Meramot')

@section('content')
@php
    $isDirectService = ($repairRequest->source ?? '') === 'DIRECT_SERVICE';
    if ($isDirectService) {
        $deliveryFee = ($repairRequest->delivery_type === 'EXPRESS')
            ? 150.0
            : (($repairRequest->preferred_pickup ?? true) ? 80.0 : 0.0);
        $escrowFee = (float) ($repairRequest->checkup_fee ?? 0);
        $totalPayable = (float) ($repairRequest->quoted_final_amount ?? 0);
        $quoteItems = is_array($repairRequest->repairJob?->final_quote_items)
            ? $repairRequest->repairJob->final_quote_items
            : [];
        $servicesSubtotal = !empty($quoteItems)
            ? (float) collect($quoteItems)->sum(fn ($it) => ((float) ($it['price'] ?? 0)) * ((int) ($it['quantity'] ?? 1)))
            : max(0.0, $totalPayable - $escrowFee - $deliveryFee);
        if ($totalPayable <= 0) {
            $totalPayable = $servicesSubtotal + $escrowFee + $deliveryFee;
        }
    } else {
        $acceptedBidCost = $repairRequest->quoted_final_amount
            ?? optional($repairRequest->bids?->where('status', 'ACCEPTED')->first())->proposed_cost
            ?? optional($repairRequest->bids?->sortBy('proposed_cost')->first())->proposed_cost
            ?? 3500;
        $deliveryFee = ($repairRequest->preferred_pickup ?? true) ? 150 : 0;
        $totalPayable = (float) $acceptedBidCost + $deliveryFee;
        $quoteItems = [];
        $escrowFee = 0.0;
        $servicesSubtotal = (float) $acceptedBidCost;
    }
@endphp

<div class="mx-auto max-w-2xl px-4 py-6 md:px-6 space-y-5">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>›</span>
        <a href="{{ route('requests.track', $repairRequest->id) }}" class="hover:underline">Order #{{ $repairRequest->id }}</a>
        <span>›</span>
        <span class="text-[var(--foreground)] font-semibold">Escrow Payment Checkout</span>
    </div>

    <!-- Online Payment Notice -->
    <div class="rounded-[1.75rem] border border-amber-300 bg-amber-50/90 p-4 text-xs flex items-start gap-3">
        <i class="fa-solid fa-circle-info text-amber-800 mt-0.5 shrink-0"></i>
        <div>
            <p class="font-black text-amber-950">Online Card &amp; MFS Gateway — Upcoming Release</p>
            <p class="text-amber-900 mt-0.5">Hosted card, bKash, and Nagad checkout will be enabled soon. Cash on return with escrow protection is active now.</p>
        </div>
    </div>

    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm">
        <div class="mb-6 flex flex-wrap justify-between items-center gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-black text-[var(--foreground)]">Secure Escrow Checkout</h1>
                <p class="text-xs text-[var(--muted-foreground)] mt-1 break-words">Order #{{ $repairRequest->id }} — {{ $repairRequest->title }}</p>
                <p class="text-xs text-[var(--muted-foreground)] mt-1 dev-only-badge">Feature R17 | Assigned to: <strong class="text-[var(--accent-dark)]">Md. Nawshad Zaman Khan [23301622]</strong></p>
            </div>
            <span class="text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-1.5 rounded-full border border-[var(--border)] whitespace-nowrap">
                <i class="fa-solid fa-shield-halved mr-1"></i> Escrow Protected
            </span>
        </div>

        <div class="border border-[var(--border)] rounded-2xl p-5 bg-[var(--mint-100)] mb-6 text-xs space-y-2.5">
            @if($isDirectService && !empty($quoteItems))
                <div class="border-b border-[var(--border)] pb-3 mb-1 space-y-1.5">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">Booked Services</p>
                    @foreach($quoteItems as $item)
                        @php
                            $itemQty = (int) ($item['quantity'] ?? 1);
                            $itemLineTotal = ((float) ($item['price'] ?? 0)) * $itemQty;
                        @endphp
                        <div class="flex justify-between gap-3 text-[var(--foreground)]">
                            <span class="min-w-0 break-words">{{ $item['title'] ?? 'Repair Service' }} × {{ $itemQty }}</span>
                            <span class="font-bold shrink-0">৳{{ number_format($itemLineTotal, 2) }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($isDirectService)
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Services Subtotal:</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($servicesSubtotal, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Escrow Protection Fee (5%):</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($escrowFee, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Two-Way Courier ({{ $repairRequest->delivery_type === 'EXPRESS' ? 'Express' : 'Regular' }}):</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($deliveryFee, 2) }}</span>
                </div>
            @else
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Repair Quote / Order Subtotal:</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($acceptedBidCost, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Doorstep Courier Fee:</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($deliveryFee, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Platform Escrow Buyer Protection:</span>
                    <span class="font-bold text-emerald-700 shrink-0">Included</span>
                </div>
            @endif

            <div class="border-t border-[var(--border)] pt-2.5 flex justify-between gap-3 font-black text-base text-[var(--foreground)]">
                <span>Total Payable:</span>
                <span class="text-[var(--accent-dark)] shrink-0">৳{{ number_format($totalPayable, 2) }}</span>
            </div>
        </div>

        <form method="POST" action="/payment/initiate" class="space-y-3">
            @csrf
            <input type="hidden" name="repair_request_id" value="{{ $repairRequest->id }}">
            <input type="hidden" name="amount" value="{{ $totalPayable }}">

            <button type="submit" class="w-full bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] font-bold py-3.5 px-5 rounded-full text-xs transition flex items-center justify-center gap-2 shadow">
                <i class="fa-solid fa-lock"></i>
                <span>Authorize ৳{{ number_format($totalPayable, 0) }} via SSLCommerz Escrow</span>
            </button>

            <a href="{{ route('requests.track', $repairRequest->id) }}" class="block w-full text-center rounded-full border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] py-3 px-4 text-xs font-bold text-[var(--foreground)] transition">
                &larr; Return to Order Tracker (Pay Cash on Return)
            </a>
        </form>
    </div>
</div>
@endsection

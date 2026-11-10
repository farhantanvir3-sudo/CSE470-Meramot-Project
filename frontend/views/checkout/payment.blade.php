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
    $acceptedBidCost = $repairRequest->quoted_final_amount
        ?? optional($repairRequest->bids?->where('status', 'ACCEPTED')->first())->proposed_cost
        ?? optional($repairRequest->bids?->sortBy('proposed_cost')->first())->proposed_cost
        ?? 3500;
    $deliveryFee = ($repairRequest->preferred_pickup ?? true) ? 150 : 0;
    $totalPayable = (float) $acceptedBidCost + $deliveryFee;
@endphp

<div class="mx-auto max-w-2xl px-4 py-6 md:px-6 space-y-5">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>›</span>
        <a href="{{ route('requests.track', $repairRequest->id) }}" class="hover:underline">Request #{{ $repairRequest->id }}</a>
        <span>›</span>
        <span class="text-[var(--foreground)] font-semibold">Escrow Payment Checkout</span>
    </div>

    <!-- Sprint 2 Notice -->
    <div class="rounded-[1.75rem] border border-amber-300 bg-amber-50/90 p-4 text-xs flex items-start gap-3">
        <i class="fa-solid fa-circle-info text-amber-800 mt-0.5"></i>
        <div>
            <p class="font-black text-amber-950">SSLCommerz Online Gateway — Launching in Sprint 2</p>
            <p class="text-amber-900 mt-0.5">Hosted card / bKash / Nagad checkout and automated PDF tax invoices are scheduled for Sprint 2. Cash / Escrow on Pickup is active right now for Sprint 1 orders.</p>
        </div>
    </div>

    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm">
        <div class="mb-6 flex flex-wrap justify-between items-center gap-3">
            <div>
                <h1 class="text-2xl font-black text-[var(--foreground)]">Secure Escrow Checkout</h1>
                <p class="text-xs text-[var(--muted-foreground)] mt-1">Order #{{ $repairRequest->id }} — {{ $repairRequest->title }}</p>
                <p class="text-xs text-[var(--muted-foreground)] mt-1 dev-only-badge">Feature R17 | Assigned to: <strong class="text-[var(--accent-dark)]">Md. Nawshad Zaman Khan [23301622]</strong></p>
            </div>
            <span class="text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-1.5 rounded-full border border-[var(--border)]">
                <i class="fa-solid fa-shield-halved mr-1"></i> SSLCommerz Sandbox (Sprint 2)
            </span>
        </div>

        <div class="border border-[var(--border)] rounded-2xl p-5 bg-[var(--mint-100)] mb-6 text-xs space-y-2.5">
            <div class="flex justify-between text-[var(--muted-foreground)]">
                <span>Repair Quote / Order Subtotal:</span>
                <span class="font-bold text-[var(--foreground)]">৳{{ number_format($acceptedBidCost, 2) }}</span>
            </div>
            <div class="flex justify-between text-[var(--muted-foreground)]">
                <span>Doorstep Courier Fee:</span>
                <span class="font-bold text-[var(--foreground)]">৳{{ number_format($deliveryFee, 2) }}</span>
            </div>
            <div class="flex justify-between text-[var(--muted-foreground)]">
                <span>Platform Escrow Buyer Protection:</span>
                <span class="font-bold text-emerald-700">Included (0৳ Customer Surcharge)</span>
            </div>
            <div class="border-t border-[var(--border)] pt-2.5 flex justify-between font-black text-base text-[var(--foreground)]">
                <span>Total Payable:</span>
                <span class="text-[var(--accent-dark)]">৳{{ number_format($totalPayable, 2) }}</span>
            </div>
        </div>

        <form method="POST" action="/payment/initiate" class="space-y-3">
            @csrf
            <input type="hidden" name="repair_request_id" value="{{ $repairRequest->id }}">
            <input type="hidden" name="amount" value="{{ $totalPayable }}">

            <button type="submit" class="w-full bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] font-bold py-3.5 rounded-full text-xs transition flex items-center justify-center gap-2 shadow">
                <i class="fa-solid fa-lock"></i>
                <span>Pay ৳{{ number_format($totalPayable, 0) }} via SSLCommerz Gateway (Sprint 2 Preview)</span>
            </button>

            <a href="{{ route('requests.track', $repairRequest->id) }}" class="block w-full text-center rounded-full border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] py-3 text-xs font-bold text-[var(--foreground)] transition">
                &larr; Return to Live Order Tracker (Continue with Cash / Escrow on Pickup)
            </a>
        </form>
    </div>
</div>
@endsection

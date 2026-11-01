{{--
=============================================================================
FEATURE R17: SSLCommerz/Stripe Online Payment Gateway & PDF Invoice
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
Architectural Layer: View (Checkout/Payment)
Controller: PaymentGatewayController@showCheckout, initiatePayment
Models Touched: PaymentTransaction, Invoice

INSTRUCTIONS FOR NAWSHAD:
- Payment checkout summary (Accepted bid price + 10% platform fee + delivery charge).
- Direct integration button with SSLCommerz Gateway Sandbox.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Secure Checkout & Payment | Feature R17')

@section('content')
<div class="max-w-xl mx-auto bg-white rounded-xl border border-slate-200 p-8 shadow-sm">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Secure Payment Checkout</h1>
            <p class="text-xs text-slate-500 mt-1">Feature R17 | Assigned to: <strong class="text-teal-700">Md. Nawshad Zaman Khan [23301622]</strong></p>
        </div>
        <span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-3 py-1 rounded-full border border-indigo-200">
            SSLCommerz Secured
        </span>
    </div>

    <div class="border border-slate-200 rounded-lg p-5 bg-slate-50 mb-6 text-xs space-y-2">
        <div class="flex justify-between text-slate-600"><span>Accepted Repair Bid:</span><span class="font-bold text-slate-800">৳3,500.00</span></div>
        <div class="flex justify-between text-slate-600"><span>Delivery Courier Fee:</span><span class="font-bold text-slate-800">৳150.00</span></div>
        <div class="flex justify-between text-slate-600"><span>Platform Escrow Protection Fee:</span><span class="font-bold text-slate-800">৳50.00</span></div>
        <div class="border-t border-slate-200 pt-2 flex justify-between font-black text-sm text-slate-900">
            <span>Total Payable:</span><span>৳3,700.00</span>
        </div>
    </div>

    <form method="POST" action="/payment/initiate">
        @csrf
        <input type="hidden" name="repair_request_id" value="{{ $repairRequest->id ?? 1 }}">
        <input type="hidden" name="amount" value="3700.00">

        <button type="submit" class="w-full bg-emerald-600 hover:bg-emerald-700 text-white font-semibold py-3 rounded-lg text-xs transition flex items-center justify-center gap-2">
            <i class="fa-solid fa-lock"></i> Pay ৳3,700 via SSLCommerz Gateway
        </button>
    </form>
</div>
@endsection

{{--
=============================================================================
FEATURE R17: Bangladeshi SSLCommerz / MFS Escrow Sandbox Simulator
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Controller: PaymentGatewayController@showSandbox, handleSuccess, handleFail, handleCancel
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'SSLCommerz Secure Payment | Meramot')

@section('content')
@php
    $req = $payment->repairRequest;
    $inv = $payment->invoice;
    $initialTab = in_array($activeChannel ?? 'BKASH', ['BKASH', 'NAGAD', 'ROCKET', 'CARD'], true)
        ? $activeChannel
        : 'BKASH';
@endphp

<div class="mx-auto max-w-2xl px-4 py-8 md:px-6"
     x-data="{
        tab: '{{ $initialTab }}',
        walletNumber: '01700000000',
        cardNumber: '4321 8899 0011 4242',
        otpCode: '123456',
        pinCode: '12121'
     }">

    <div class="rounded-[2rem] border-2 border-[var(--accent-dark)]/25 bg-[var(--card)] shadow-xl overflow-hidden">
        <!-- Gateway Top Bar -->
        <div class="bg-[var(--accent-dark)] text-[var(--accent-foreground)] px-6 py-5 flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="h-11 w-11 rounded-2xl bg-white/15 flex items-center justify-center">
                    <i class="fa-solid fa-shield-halved text-lg text-[var(--mint-100)]"></i>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h1 class="text-base font-black tracking-tight">SSLCommerz × Meramot Secure Pay</h1>
                        <span class="rounded-full bg-amber-300/95 px-2.5 py-0.5 text-[10px] font-black uppercase text-amber-950">Sandbox Simulator</span>
                    </div>
                    <p class="text-[11px] text-white/80 font-mono mt-0.5">TXN: {{ $payment->transaction_reference }} • {{ $inv?->invoice_number ?? 'INV-PENDING' }}</p>
                </div>
            </div>
            <div class="text-right">
                <p class="text-[10px] uppercase tracking-wider text-white/75 font-bold">Amount to Pay</p>
                <p class="text-2xl font-black text-white">৳{{ number_format((float) $payment->amount, 2) }}</p>
            </div>
        </div>

        <!-- Channel Tabs -->
        <div class="grid grid-cols-4 border-b border-[var(--border)] bg-[var(--mint-100)] text-xs font-extrabold">
            <button type="button" @click="tab = 'BKASH'"
                    :class="tab === 'BKASH' ? 'bg-white text-pink-700 border-b-2 border-pink-600' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'"
                    class="py-3.5 px-2 text-center transition">
                <i class="fa-solid fa-mobile-screen-button mr-1"></i> bKash
            </button>
            <button type="button" @click="tab = 'NAGAD'"
                    :class="tab === 'NAGAD' ? 'bg-white text-orange-700 border-b-2 border-orange-600' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'"
                    class="py-3.5 px-2 text-center transition">
                <i class="fa-solid fa-wallet mr-1"></i> Nagad
            </button>
            <button type="button" @click="tab = 'ROCKET'"
                    :class="tab === 'ROCKET' ? 'bg-white text-purple-700 border-b-2 border-purple-600' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'"
                    class="py-3.5 px-2 text-center transition">
                <i class="fa-solid fa-bolt mr-1"></i> Rocket
            </button>
            <button type="button" @click="tab = 'CARD'"
                    :class="tab === 'CARD' ? 'bg-white text-sky-800 border-b-2 border-sky-700' : 'text-[var(--muted-foreground)] hover:text-[var(--foreground)]'"
                    class="py-3.5 px-2 text-center transition">
                <i class="fa-solid fa-credit-card mr-1"></i> Card
            </button>
        </div>

        <div class="p-6 md:p-7 space-y-5">
            <!-- Order Context -->
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/80 p-4 text-xs flex flex-wrap justify-between gap-3">
                <div>
                    <p class="font-black text-[var(--foreground)]">{{ $req?->title ?? 'Verified Electronics Repair' }}</p>
                    <p class="text-[var(--muted-foreground)] mt-0.5">
                        Customer: {{ $req?->customer?->name ?? 'Meramot Customer' }} • Workshop: {{ $inv?->shop?->name ?? 'Assigned Partner Shop' }}
                    </p>
                </div>
                <span class="self-start rounded-full border border-[var(--border)] bg-white px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
                    Order #{{ $payment->repair_request_id }}
                </span>
            </div>

            <!-- Interactive Sandbox Credentials Form -->
            <form method="POST" action="{{ route('payment.success') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="tran_id" value="{{ $payment->transaction_reference }}">
                <input type="hidden" name="channel" :value="tab">
                <input type="hidden" name="gateway_mode" value="meramot_sandbox_escrow">
                <input type="hidden" name="wallet_masked" :value="tab === 'CARD' ? '4321-****-****-4242' : walletNumber">

                <div x-show="tab !== 'CARD'" class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">
                            <span x-text="tab"></span> Account Number
                        </label>
                        <input type="text" x-model="walletNumber" required
                               class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-[var(--accent-dark)]">
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Sandbox OTP</label>
                        <input type="text" x-model="otpCode" maxlength="6" required
                               class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-mono text-xs tracking-widest focus:outline-none focus:ring-2 focus:ring-[var(--accent-dark)]">
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Sandbox Wallet PIN</label>
                        <input type="password" x-model="pinCode" maxlength="6" required
                               class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-mono text-xs tracking-widest focus:outline-none focus:ring-2 focus:ring-[var(--accent-dark)]">
                    </div>
                </div>

                <div x-show="tab === 'CARD'" style="display: none;" class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
                    <div class="sm:col-span-2">
                        <label class="block font-bold text-[var(--foreground)] mb-1">Sandbox Visa / Mastercard Number</label>
                        <input type="text" x-model="cardNumber"
                               class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-mono text-xs focus:outline-none focus:ring-2 focus:ring-[var(--accent-dark)]">
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">3DS OTP</label>
                        <input type="text" x-model="otpCode" maxlength="6"
                               class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-mono text-xs tracking-widest focus:outline-none focus:ring-2 focus:ring-[var(--accent-dark)]">
                    </div>
                </div>

                <div class="rounded-xl bg-emerald-50 border border-emerald-200 px-3.5 py-2.5 text-[11px] text-emerald-950 flex items-center justify-between gap-2">
                    <span><i class="fa-solid fa-flask mr-1 text-emerald-700"></i> <strong>Sandbox Test Mode:</strong> Pre-filled test wallet &amp; OTP <code class="font-mono font-bold">123456</code> simulate real SSLCommerz callback &amp; payment settlement.</span>
                </div>

                <button type="submit" class="w-full rounded-full bg-[var(--accent-dark)] hover:opacity-95 text-[var(--accent-foreground)] py-3.5 px-6 text-xs font-black tracking-wide transition shadow-md flex items-center justify-center gap-2">
                    <i class="fa-solid fa-circle-check"></i>
                    <span>Authorize Payment — ৳{{ number_format((float) $payment->amount, 2) }}</span>
                </button>
            </form>

            <!-- Secondary Sandbox Outcome Controls (Decline / Cancel) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 pt-2 border-t border-[var(--border)]">
                <form method="POST" action="{{ route('payment.fail') }}">
                    @csrf
                    <input type="hidden" name="tran_id" value="{{ $payment->transaction_reference }}">
                    <input type="hidden" name="error_reason" value="Simulated issuer decline / insufficient wallet balance">
                    <button type="submit" class="w-full rounded-full border border-red-300 bg-red-50 hover:bg-red-100 text-red-800 py-2.5 px-4 text-xs font-bold transition">
                        <i class="fa-solid fa-ban mr-1"></i> Simulate Declined Payment
                    </button>
                </form>

                <form method="POST" action="{{ route('payment.cancel') }}">
                    @csrf
                    <input type="hidden" name="tran_id" value="{{ $payment->transaction_reference }}">
                    <button type="submit" class="w-full rounded-full border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] text-[var(--foreground)] py-2.5 px-4 text-xs font-bold transition">
                        <i class="fa-solid fa-arrow-rotate-left mr-1"></i> Cancel &amp; Return to Checkout
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

{{--
=============================================================================
FEATURE R17: Online Payment Gateway (SSLCommerz / MFS Escrow) & PDF Invoice
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 2
Architectural Layer: View (Checkout/Payment)
Controller: PaymentGatewayController@showCheckout, initiatePayment
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Secure Escrow Checkout | Meramot')

@section('content')
@php
    $b = $breakdown ?? \App\Http\Controllers\PaymentGatewayController::computeCheckoutBreakdown($repairRequest);
    $isDirectService = $b['is_direct_service'];
    $servicesSubtotal = $b['services_subtotal'];
    $escrowFee = $b['escrow_fee'];
    $ratePctLabel = rtrim(rtrim(number_format((float) ($b['commission_rate_pct'] ?? 8), 2), '0'), '.');
    $deliveryFee = $b['delivery_fee'];
    $discountAmount = $b['discount_amount'];
    $totalPayable = $b['total_payable'];
    $quoteItems = $b['quote_items'];
    $acceptedBidCost = $servicesSubtotal;
    $paidTxn = $paidTransaction ?? null;
    $invoiceDoc = $latestInvoice ?? null;
    $defaultMethod = $preferredChannel ?? 'BKASH';
@endphp

<div class="mx-auto max-w-3xl px-4 py-6 md:px-6 space-y-5" x-data="{ selectedChannel: '{{ $defaultMethod }}' }">
    <!-- Breadcrumb -->
    <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>›</span>
        <a href="{{ route('requests.track', $repairRequest->id) }}" class="hover:underline">Order #{{ $repairRequest->id }}</a>
        <span>›</span>
        <span class="text-[var(--foreground)] font-semibold">Escrow Payment Checkout</span>
    </div>

    @if(session('error'))
        <div class="rounded-2xl border border-red-300 bg-red-50 p-4 text-xs text-red-900 flex items-start gap-3">
            <i class="fa-solid fa-circle-xmark text-red-600 mt-0.5"></i>
            <div>
                <p class="font-extrabold">Payment Authorization Failed</p>
                <p class="mt-0.5">{{ session('error') }}</p>
            </div>
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs text-amber-950 flex items-start gap-3">
            <i class="fa-solid fa-triangle-exclamation text-amber-700 mt-0.5"></i>
            <div>
                <p class="font-extrabold">Payment Session Interrupted</p>
                <p class="mt-0.5">{{ session('warning') }}</p>
            </div>
        </div>
    @endif

    @if($paidTxn)
        <div class="rounded-[2rem] border border-emerald-400 bg-emerald-50/95 p-6 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-emerald-700 text-white shadow-sm">
                        <i class="fa-solid fa-shield-check text-lg"></i>
                    </span>
                    <div>
                        <p class="text-sm font-black text-emerald-950">Escrow Payment Settled &amp; Protected</p>
                        <p class="text-xs text-emerald-900">Ref: <span class="font-mono font-bold">{{ $paidTxn->transaction_reference }}</span> • Method: <strong>{{ $paidTxn->payment_method }}</strong></p>
                    </div>
                </div>
                <span class="rounded-full bg-emerald-700 px-3.5 py-1 text-xs font-extrabold text-white">
                    PAID ৳{{ number_format((float) $paidTxn->amount, 2) }}
                </span>
            </div>
            <div class="flex flex-wrap gap-3 pt-1">
                @if($invoiceDoc)
                    <a href="{{ route('invoices.show', $invoiceDoc->id) }}" class="inline-flex items-center gap-2 rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                        <i class="fa-solid fa-file-invoice"></i> View Official Invoice
                    </a>
                    <a href="{{ route('invoices.pdf', $invoiceDoc->id) }}" class="inline-flex items-center gap-2 rounded-full border border-[var(--border)] bg-white px-5 py-2.5 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                        <i class="fa-solid fa-file-pdf text-red-600"></i> Download PDF Receipt
                    </a>
                @endif
                <a href="{{ route('requests.track', $repairRequest->id) }}" class="inline-flex items-center gap-2 rounded-full border border-emerald-300 bg-emerald-100/70 px-5 py-2.5 text-xs font-bold text-emerald-950 hover:bg-emerald-200/70 transition">
                    <i class="fa-solid fa-arrow-left"></i> Back to Order Tracker
                </a>
            </div>
        </div>
    @endif

    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm">
        <div class="mb-6 flex flex-wrap justify-between items-center gap-3">
            <div class="min-w-0">
                <h1 class="text-2xl font-black text-[var(--foreground)]">Secure Escrow Checkout</h1>
                <p class="text-xs text-[var(--muted-foreground)] mt-1 break-words">Order #{{ $repairRequest->id }} — {{ $repairRequest->title }}</p>
            </div>
            <span class="text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-1.5 rounded-full border border-[var(--border)] whitespace-nowrap">
                <i class="fa-solid fa-shield-halved mr-1"></i> 100% Escrow Protected
            </span>
        </div>

        <!-- Order Financial Breakdown -->
        <div class="border border-[var(--border)] rounded-2xl p-5 bg-[var(--mint-100)] mb-6 text-xs space-y-2.5">
            @if($isDirectService && !empty($quoteItems))
                <div class="border-b border-[var(--border)] pb-3 mb-1 space-y-1.5">
                    <p class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)]">Booked Services</p>
                    @foreach($quoteItems as $item)
                        @php
                            $itemQty = max(1, (int) ($item['quantity'] ?? 1));
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
                    <span>Services &amp; Parts Subtotal:</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($servicesSubtotal, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Service &amp; Escrow Protection Fee:</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($escrowFee, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Doorstep Pickup &amp; Delivery ({{ $repairRequest->delivery_type === 'EXPRESS' ? 'Express' : 'Regular' }}):</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($deliveryFee, 2) }}</span>
                </div>
            @else
                @php
                    $quoteSourceJob = $repairRequest->repairJob;
                    $quoteSourceBid = $repairRequest->bids->firstWhere('id', $repairRequest->selected_bid_id)
                        ?? $quoteSourceJob?->acceptedBid
                        ?? $repairRequest->bids->sortBy('proposed_cost')->first();
                    $checkoutPartsItems = $quoteSourceJob
                        ? $quoteSourceJob->resolvedPartsItems()
                        : ($quoteSourceBid ? $quoteSourceBid->resolvedPartsItems() : []);
                    $checkoutPartsCost = $quoteSourceJob
                        ? $quoteSourceJob->resolvedPartsCost()
                        : (float) ($quoteSourceBid->parts_cost ?? 0);
                    $checkoutLaborCost = $quoteSourceJob
                        ? $quoteSourceJob->resolvedLaborCost()
                        : (float) ($quoteSourceBid->labor_cost ?? 0);
                    $checkoutHasWarranty = $quoteSourceJob
                        ? $quoteSourceJob->hasWarrantyCoverage()
                        : ($quoteSourceBid ? $quoteSourceBid->hasWarrantyCoverage() : false);
                    $checkoutWarrantyDays = $quoteSourceJob
                        ? $quoteSourceJob->resolvedWarrantyDays()
                        : ($quoteSourceBid ? $quoteSourceBid->resolvedWarrantyDays() : 0);
                @endphp

                @if(count($checkoutPartsItems) > 0 || $checkoutLaborCost > 0)
                    <div class="pb-1">
                        @include('requests.partials.quote-breakdown-list', [
                            'partsItems' => $checkoutPartsItems,
                            'partsCost' => $checkoutPartsCost,
                            'laborCost' => $checkoutLaborCost,
                            'hasWarranty' => $checkoutHasWarranty,
                            'warrantyDays' => $checkoutWarrantyDays,
                            'compact' => true,
                        ])
                    </div>
                @endif

                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Approved Workshop Quote (Parts &amp; Labor):</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($acceptedBidCost, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Service &amp; Escrow Protection Fee:</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($escrowFee, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Doorstep Pickup &amp; Delivery:</span>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳{{ number_format($deliveryFee, 2) }}</span>
                </div>
                <div class="flex justify-between gap-3 text-[var(--muted-foreground)]">
                    <span>Escrow Buyer Protection Coverage:</span>
                    <span class="font-bold text-emerald-700 shrink-0">Included</span>
                </div>
            @endif

            @if($discountAmount > 0)
                <div class="flex justify-between gap-3 text-emerald-800 font-bold">
                    <span>Promo Discount {{ !empty($repairRequest->voucher_code) ? '(' . $repairRequest->voucher_code . ')' : '' }}:</span>
                    <span class="shrink-0">- ৳{{ number_format($discountAmount, 2) }}</span>
                </div>
            @endif

            <div class="border-t border-[var(--border)] pt-2.5 flex justify-between gap-3 font-black text-base text-[var(--foreground)]">
                <span>Total Payable:</span>
                <span class="text-[var(--accent-dark)] shrink-0">৳{{ number_format($totalPayable, 2) }}</span>
            </div>
        </div>

        @if(!$paidTxn)
            <form method="POST" action="{{ route('payment.initiate') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="repair_request_id" value="{{ $repairRequest->id }}">
                <input type="hidden" name="amount" value="{{ $totalPayable }}">
                <input type="hidden" name="payment_method" :value="selectedChannel">

                <div>
                    <p class="text-[11px] font-extrabold uppercase tracking-wider text-[var(--muted-foreground)] mb-2.5">
                        Choose Bangladeshi MFS or Card Gateway Channel
                    </p>
                    <div class="grid grid-cols-2 sm:grid-cols-5 gap-2.5 text-xs">
                        @php
                            $channels = [
                                ['code' => 'BKASH', 'label' => 'bKash', 'sub' => 'Instant MFS', 'icon' => 'fa-mobile-screen-button', 'accent' => 'text-pink-700'],
                                ['code' => 'NAGAD', 'label' => 'Nagad', 'sub' => 'Digital MFS', 'icon' => 'fa-wallet', 'accent' => 'text-orange-700'],
                                ['code' => 'ROCKET', 'label' => 'Rocket', 'sub' => 'DBBL MFS', 'icon' => 'fa-bolt', 'accent' => 'text-purple-700'],
                                ['code' => 'CARD', 'label' => 'Card', 'sub' => 'Visa / Master', 'icon' => 'fa-credit-card', 'accent' => 'text-sky-700'],
                                ['code' => 'SSLCOMMERZ', 'label' => 'SSLCommerz', 'sub' => 'All Gateways', 'icon' => 'fa-building-columns', 'accent' => 'text-emerald-800'],
                            ];
                        @endphp
                        @foreach($channels as $ch)
                            <button
                                type="button"
                                @click="selectedChannel = '{{ $ch['code'] }}'"
                                :class="selectedChannel === '{{ $ch['code'] }}'
                                    ? 'border-[var(--accent-dark)] bg-[var(--mint-100)] ring-2 ring-[var(--accent-dark)]/20'
                                    : 'border-[var(--border)] bg-white/80 hover:bg-[var(--mint-100)]/60'"
                                class="rounded-2xl border p-3 text-left transition flex flex-col justify-between gap-1"
                            >
                                <div class="flex items-center justify-between">
                                    <i class="fa-solid {{ $ch['icon'] }} {{ $ch['accent'] }} text-sm"></i>
                                    <span x-show="selectedChannel === '{{ $ch['code'] }}'" class="h-2 w-2 rounded-full bg-[var(--accent-dark)]"></span>
                                </div>
                                <div>
                                    <p class="font-black text-[var(--foreground)]">{{ $ch['label'] }}</p>
                                    <p class="text-[10px] text-[var(--muted-foreground)]">{{ $ch['sub'] }}</p>
                                </div>
                            </button>
                        @endforeach
                    </div>
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-white/70 px-4 py-3 text-[11px] text-[var(--muted-foreground)] flex items-start gap-2.5">
                    <i class="fa-solid fa-lock text-[var(--accent-dark)] mt-0.5"></i>
                    <span>Your payment is held in <strong>Meramot Escrow Custody</strong> and only released to the workshop after repair verification and device return.</span>
                </div>

                <button type="submit" class="w-full bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] font-bold py-3.5 px-5 rounded-full text-xs transition flex items-center justify-center gap-2 shadow">
                    <i class="fa-solid fa-lock"></i>
                    <span>Authorize ৳{{ number_format($totalPayable, 0) }} via SSLCommerz Escrow</span>
                </button>

                <a href="{{ route('requests.track', $repairRequest->id) }}" class="block w-full text-center rounded-full border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] py-3 px-4 text-xs font-bold text-[var(--foreground)] transition">
                    &larr; Return to Order Tracker (Pay Cash on Return)
                </a>
            </form>
        @endif
    </div>
</div>
@endsection

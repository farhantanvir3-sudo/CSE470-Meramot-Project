{{--
=============================================================================
FEATURE R17: Official Meramot Escrow Invoice & Pure-PHP PDF Receipt View
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Controller: PaymentGatewayController@showInvoice, downloadPdf
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Invoice ' . ($invoice->invoice_number ?? ('#' . $invoice->id)) . ' | Meramot')

@section('content')
@php
    $req = $invoice->repairRequest;
    $customer = $req?->customer;
    $shop = $invoice->shop ?? $invoice->repairJob?->shop ?? $req?->requestedShop;
    $warranty = $invoice->repairJob?->warranty ?? $req?->repairJob?->warranty;
    $txn = $paidTransaction ?? null;
    $isPaid = strtoupper((string) $invoice->status) === 'PAID';
@endphp

<div class="mx-auto max-w-4xl px-4 py-6 md:px-6 space-y-5">
    <!-- Top Actions Bar -->
    <div class="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
            @if($req)
                <a href="{{ route('requests.track', $req->id) }}" class="inline-flex items-center gap-1.5 font-bold text-[var(--accent-dark)] hover:underline">
                    <i class="fa-solid fa-arrow-left"></i> Order #{{ $req->id }} Tracker
                </a>
                <span>•</span>
            @endif
            <span>Official Tax &amp; Escrow Receipt</span>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <a href="{{ route('invoices.pdf', $invoice->id) }}" class="inline-flex items-center gap-2 rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                <i class="fa-solid fa-file-arrow-down"></i> Download Official PDF
            </a>
            <button type="button" onclick="window.print()" class="inline-flex items-center gap-2 rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2.5 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                <i class="fa-solid fa-print"></i> Print
            </button>
        </div>
    </div>

    @if(session('success') || request()->boolean('paid'))
        <div class="rounded-2xl border border-emerald-400 bg-emerald-50 p-4 text-xs text-emerald-950 flex items-center gap-3 print:hidden">
            <i class="fa-solid fa-circle-check text-emerald-700 text-base"></i>
            <div>
                <p class="font-black">{{ session('success') ?? 'Escrow payment verified and recorded in the Meramot Financial Ledger.' }}</p>
            </div>
        </div>
    @endif

    <!-- Printable Official Invoice Card -->
    <div class="rounded-[2rem] border border-[var(--border)] bg-white shadow-sm overflow-hidden">
        <!-- Forest Green Brand Header -->
        <div class="bg-[var(--accent-dark)] text-[var(--accent-foreground)] p-6 md:p-8 flex flex-wrap justify-between items-start gap-4">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-emerald-200">Meramot Verified Marketplace</p>
                <h1 class="text-2xl md:text-3xl font-black mt-1">OFFICIAL INVOICE</h1>
                <p class="text-xs text-white/80 mt-1">Escrow-Protected Electronics Diagnostics, Repair &amp; Doorstep Logistics</p>
            </div>
            <div class="text-left sm:text-right space-y-1">
                <p class="text-sm font-mono font-black text-white">{{ $invoice->invoice_number }}</p>
                <div>
                    <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-black {{ $isPaid ? 'bg-emerald-300 text-emerald-950' : 'bg-amber-300 text-amber-950' }}">
                        <i class="fa-solid {{ $isPaid ? 'fa-check-circle' : 'fa-clock' }}"></i>
                        {{ strtoupper((string) $invoice->status) }}
                    </span>
                </div>
                <p class="text-[11px] text-white/75">Issued: {{ ($invoice->issued_at ?? $invoice->created_at ?? now())->format('d M Y, h:i A') }}</p>
            </div>
        </div>

        <div class="p-6 md:p-8 space-y-6 text-xs text-[var(--foreground)]">
            <!-- Billed To & Workshop Meta -->
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/65 p-4 space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-wider text-[var(--accent-dark)]">Billed To Customer</p>
                    <p class="text-sm font-black">{{ $customer?->name ?? 'Meramot Customer' }}</p>
                    <p class="text-[var(--muted-foreground)]">{{ $customer?->email ?? 'N/A' }}</p>
                    <p class="text-[var(--muted-foreground)]">Phone: {{ $customer?->phone ?? ($req?->contact_phone ?? 'N/A') }}</p>
                    @if($req?->pickup_address)
                        <p class="text-[var(--muted-foreground)]">Address: {{ $req->pickup_address }}</p>
                    @endif
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/65 p-4 space-y-1">
                    <p class="text-[10px] font-black uppercase tracking-wider text-[var(--accent-dark)]">Partner Workshop &amp; Device</p>
                    <p class="text-sm font-black">{{ $shop?->name ?? 'Meramot Verified Partner Workshop' }}</p>
                    <p class="text-[var(--muted-foreground)]">Order Reference: <span class="font-mono font-bold">#{{ $invoice->repair_request_id }}</span> — {{ $req?->title ?? 'Device Repair' }}</p>
                    <p class="text-[var(--muted-foreground)]">Device: {{ trim(($req?->brand ?? '') . ' ' . ($req?->model_name ?? $req?->device_type ?? 'Electronics')) }}</p>
                    @if($txn)
                        <p class="text-[var(--muted-foreground)]">Gateway Ref: <span class="font-mono font-bold">{{ $txn->transaction_reference }}</span> ({{ $txn->payment_method }})</p>
                    @endif
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="overflow-x-auto rounded-2xl border border-[var(--border)]">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-[var(--mint-100)] text-[10px] font-black uppercase tracking-wider text-[var(--accent-dark)]">
                            <th class="py-3 px-4">Item / Service Title</th>
                            <th class="py-3 px-4">Specification</th>
                            <th class="py-3 px-4 text-center">Qty</th>
                            <th class="py-3 px-4 text-right">Unit Price</th>
                            <th class="py-3 px-4 text-right">Line Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                        @forelse($invoice->lineItems as $item)
                            <tr>
                                <td class="py-3 px-4 font-bold">{{ $item->title }}</td>
                                <td class="py-3 px-4 text-[var(--muted-foreground)]">{{ $item->description ?? 'Verified workshop service' }}</td>
                                <td class="py-3 px-4 text-center font-semibold">{{ $item->quantity }}</td>
                                <td class="py-3 px-4 text-right">৳{{ number_format((float) $item->unit_price, 2) }}</td>
                                <td class="py-3 px-4 text-right font-black">৳{{ number_format((float) $item->total_price, 2) }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td class="py-3 px-4 font-bold">{{ $req?->title ?? 'Verified Repair Service' }}</td>
                                <td class="py-3 px-4 text-[var(--muted-foreground)]">Workshop repair &amp; quality inspection</td>
                                <td class="py-3 px-4 text-center font-semibold">1</td>
                                <td class="py-3 px-4 text-right">৳{{ number_format((float) $invoice->subtotal_amount, 2) }}</td>
                                <td class="py-3 px-4 text-right font-black">৳{{ number_format((float) $invoice->subtotal_amount, 2) }}</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <!-- Totals & Governance Split -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-5 items-start">
                <div class="md:col-span-7 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-4 space-y-2">
                    <p class="text-[10px] font-black uppercase tracking-wider text-[var(--accent-dark)]">
                        <i class="fa-solid fa-scale-balanced mr-1"></i> Escrow &amp; Warranty Audit Record
                    </p>
                    <p class="text-[11px] text-[var(--muted-foreground)]">
                        <strong>Governance Split:</strong> 10% Meramot Platform Commission (৳{{ number_format((float) $invoice->commission_amount, 2) }}) • 90% Net Workshop Allocation (৳{{ number_format(max(0, (float) $invoice->subtotal_amount - (float) $invoice->commission_amount), 2) }}).
                    </p>
                    @if($warranty)
                        <div class="rounded-xl bg-emerald-50 border border-emerald-200 p-3 text-[11px] text-emerald-950">
                            <p class="font-black"><i class="fa-solid fa-award mr-1 text-emerald-700"></i> {{ $warranty->title }} ({{ $warranty->duration_days }} Days)</p>
                            <p class="mt-0.5">{{ $warranty->details }} • Valid until <strong>{{ optional($warranty->ends_at)->format('d M Y') }}</strong>.</p>
                        </div>
                    @else
                        <p class="text-[11px] text-[var(--muted-foreground)]">
                            <strong>Warranty Activation:</strong> Digital warranty certificate activates automatically upon workshop completion and handover verification.
                        </p>
                    @endif
                </div>

                <div class="md:col-span-5 rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 space-y-2">
                    <div class="flex justify-between text-[var(--muted-foreground)]">
                        <span>Service &amp; Parts Subtotal:</span>
                        <span class="font-bold text-[var(--foreground)]">৳{{ number_format((float) $invoice->subtotal_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-[var(--muted-foreground)]">
                        <span>Doorstep Logistics Fee:</span>
                        <span class="font-bold text-[var(--foreground)]">৳{{ number_format((float) $invoice->delivery_amount, 2) }}</span>
                    </div>
                    @if((float) $invoice->discount_amount > 0)
                        <div class="flex justify-between text-emerald-800 font-bold">
                            <span>Promo Discount {{ !empty($req?->voucher_code) ? '(' . $req->voucher_code . ')' : '' }}:</span>
                            <span>- ৳{{ number_format((float) $invoice->discount_amount, 2) }}</span>
                        </div>
                    @endif
                    <div class="border-t border-[var(--border)] pt-2.5 flex justify-between text-base font-black text-[var(--accent-dark)]">
                        <span>Grand Total:</span>
                        <span>৳{{ number_format((float) $invoice->total_amount, 2) }}</span>
                    </div>
                    @if(!$isPaid && $req)
                        <a href="{{ route('payment.checkout', $req->id) }}" class="mt-2 block w-full text-center rounded-full bg-[var(--accent-dark)] py-2.5 px-4 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition print:hidden">
                            Pay Now via Escrow Gateway
                        </a>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

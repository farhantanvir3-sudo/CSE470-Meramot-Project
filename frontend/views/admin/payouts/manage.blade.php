{{--
=============================================================================
FEATURE R18: Automated Vendor & Courier Payout & Net Commission Settlement
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 3
Architectural Layer: View (Admin/Payouts/Manage)
Controller: VendorPayoutController@index, disbursePayout, settleShopDirect
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Payout & Commission Settlements | Meramot Admin')

@section('content')
@php
    $k = $kpis ?? [
        'pending_count' => 0,
        'pending_amount' => 0,
        'unsettled_workshop_pool' => 0,
        'vendor_paid_total' => 0,
        'rider_paid_total' => 0,
    ];
@endphp

<div class="mx-auto max-w-7xl px-3.5 py-4 sm:px-6 sm:py-6 space-y-5">
    @include('admin.partials.nav')

    <!-- Header -->
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 sm:p-6 shadow-sm">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-100 px-3 py-1 text-[10px] font-extrabold uppercase tracking-wider text-emerald-900">
                <i class="fa-solid fa-money-bill-transfer"></i> Meramot Treasury Disbursement Desk
            </span>
            <h1 class="mt-2 text-xl sm:text-2xl md:text-3xl font-black text-[var(--foreground)]">Vendor &amp; Courier Payout Settlements</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Review 90% net workshop withdrawals (after 10% platform commission) and courier wallet payouts with automatic double-entry ledger posting.
            </p>
        </div>
        <a href="{{ route('admin.financial-overview') }}" class="rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition shadow-sm">
            <i class="fa-solid fa-scale-balanced mr-1"></i> Double-Entry Ledger &rarr;
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-400 bg-emerald-50 p-4 text-xs font-bold text-emerald-950 flex items-center gap-3">
            <i class="fa-solid fa-circle-check text-emerald-700 text-base"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('warning'))
        <div class="rounded-2xl border border-amber-300 bg-amber-50 p-4 text-xs font-bold text-amber-950 flex items-center gap-3">
            <i class="fa-solid fa-triangle-exclamation text-amber-700 text-base"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif

    <!-- 4 Treasury KPI Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="rounded-2xl border border-amber-300 bg-amber-50/85 p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-amber-900">Pending Queue ({{ $k['pending_count'] }})</p>
            <p class="text-2xl font-black text-amber-950 mt-1.5">৳{{ number_format($k['pending_amount'], 2) }}</p>
            <p class="text-[11px] text-amber-900/75 mt-1">Awaiting disbursement sign-off</p>
        </div>
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Unsettled Workshop Escrow</p>
            <p class="text-2xl font-black text-[var(--accent-dark)] mt-1.5">৳{{ number_format($k['unsettled_workshop_pool'], 2) }}</p>
            <p class="text-[11px] text-[var(--muted-foreground)] mt-1">90% net allocated after 10% fee</p>
        </div>
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Total Disbursed to Shops</p>
            <p class="text-2xl font-black text-emerald-800 mt-1.5">৳{{ number_format($k['vendor_paid_total'], 2) }}</p>
            <p class="text-[11px] text-[var(--muted-foreground)] mt-1">Posted as VENDOR_PAYOUT debits</p>
        </div>
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
            <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Total Disbursed to Riders</p>
            <p class="text-2xl font-black text-[var(--foreground)] mt-1.5">৳{{ number_format($k['rider_paid_total'], 2) }}</p>
            <p class="text-[11px] text-[var(--muted-foreground)] mt-1">Posted as DELIVERY_PAYOUT debits</p>
        </div>
    </div>

    <!-- Eligible Unsettled Workshops (1-Click Direct Net Settlement) -->
    @if(isset($shopsWithEscrow) && $shopsWithEscrow->count() > 0)
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 sm:p-6 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base font-black text-[var(--foreground)]">Workshops Ready for 90% Net Escrow Settlement</h2>
                    <p class="text-xs text-[var(--muted-foreground)]">Disburse completed repair earnings directly or batch-settle from the Financial Ledger.</p>
                </div>
                <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
                    {{ $shopsWithEscrow->count() }} Eligible Workshop(s)
                </span>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3 text-xs">
                @foreach($shopsWithEscrow as $shop)
                    @php $es = $shop->escrow_summary; @endphp
                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/65 p-4 flex flex-col justify-between gap-3">
                        <div class="space-y-1">
                            <p class="font-black text-sm text-[var(--foreground)]">{{ $shop->name }}</p>
                            <p class="text-[11px] text-[var(--muted-foreground)]">
                                Gross: ৳{{ number_format($es['gross_inflow'], 0) }} • 10% Fee: ৳{{ number_format($es['commission_deducted'], 0) }}
                            </p>
                            <p class="text-sm font-black text-emerald-800 pt-1">
                                Unsettled Net: ৳{{ number_format($es['unsettled_balance'], 2) }}
                            </p>
                        </div>

                        <form method="POST" action="{{ route('admin.payouts.settle-shop') }}">
                            @csrf
                            <input type="hidden" name="shop_id" value="{{ $shop->id }}">
                            <button type="submit" class="w-full rounded-full bg-[var(--accent-dark)] hover:opacity-90 py-2 px-4 text-xs font-bold text-[var(--accent-foreground)] transition">
                                <i class="fa-solid fa-bolt mr-1"></i> Disburse ৳{{ number_format($es['unsettled_balance'], 2) }} Now
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Filter Pills & Payout Requests Table -->
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-sm overflow-hidden">
        <div class="p-4 sm:p-5 border-b border-[var(--border)] flex flex-wrap items-center justify-between gap-3 text-xs">
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach(['ALL' => 'All Statuses', 'PENDING' => 'Pending Queue', 'PAID' => 'Disbursed (Paid)', 'CANCELLED' => 'Cancelled'] as $stCode => $stLabel)
                    <a href="{{ url('/admin/payouts/manage?status=' . $stCode . '&recipient=' . ($typeFilter ?? 'ALL')) }}"
                       class="rounded-full px-3.5 py-1.5 font-bold border transition {{ ($statusFilter ?? 'ALL') === $stCode ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] border-[var(--accent-dark)]' : 'bg-[var(--mint-100)] text-[var(--foreground)] border-[var(--border)]' }}">
                        {{ $stLabel }}
                    </a>
                @endforeach
            </div>

            <div class="flex items-center gap-1.5">
                @foreach(['ALL' => 'All Recipients', 'VENDOR' => 'Workshops', 'RIDER' => 'Couriers'] as $rcCode => $rcLabel)
                    <a href="{{ url('/admin/payouts/manage?status=' . ($statusFilter ?? 'ALL') . '&recipient=' . $rcCode) }}"
                       class="rounded-full px-3 py-1.5 font-bold border transition {{ ($typeFilter ?? 'ALL') === $rcCode ? 'bg-emerald-800 text-white border-emerald-800' : 'bg-white text-[var(--foreground)] border-[var(--border)]' }}">
                        {{ $rcLabel }}
                    </a>
                @endforeach
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-xs">
                <thead class="bg-[var(--mint-100)] text-[var(--muted-foreground)] uppercase font-bold text-[10px]">
                    <tr>
                        <th class="px-5 py-3.5 text-left">Recipient</th>
                        <th class="px-5 py-3.5 text-left">Channel &amp; Account</th>
                        <th class="px-5 py-3.5 text-right">Net Payout (৳)</th>
                        <th class="px-5 py-3.5 text-left">Reference</th>
                        <th class="px-5 py-3.5 text-center">Status</th>
                        <th class="px-5 py-3.5 text-right">Governance Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @forelse($payouts as $p)
                        @php
                            $isRider = !empty($p->delivery_agent_id);
                            $recipientTitle = $isRider
                                ? ($p->deliveryAgent?->user?->name ?? ('Courier #' . $p->delivery_agent_id))
                                : ($p->shop?->name ?? 'Verified Partner Workshop');
                            $st = strtoupper((string) ($p->status ?? 'PENDING'));
                        @endphp
                        <tr>
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-2">
                                    <span class="rounded-full px-2 py-0.5 text-[9px] font-black uppercase {{ $isRider ? 'bg-sky-100 text-sky-900' : 'bg-emerald-100 text-emerald-900' }}">
                                        {{ $isRider ? 'Courier' : 'Workshop' }}
                                    </span>
                                    <span class="font-bold text-[var(--foreground)]">{{ $recipientTitle }}</span>
                                </div>
                                @if($p->commission_deducted > 0)
                                    <p class="text-[10px] text-[var(--muted-foreground)] mt-0.5">
                                        Gross ৳{{ number_format((float) $p->gross_reference_amount, 2) }} • 10% Fee ৳{{ number_format((float) $p->commission_deducted, 2) }}
                                    </p>
                                @endif
                            </td>
                            <td class="px-5 py-4 font-semibold text-[var(--foreground)] whitespace-nowrap">
                                {{ $p->payout_method ?? 'BKASH' }} • <span class="font-mono">{{ $p->payout_account ?? 'Default Wallet' }}</span>
                            </td>
                            <td class="px-5 py-4 text-right font-mono font-black text-[var(--accent-dark)] whitespace-nowrap">
                                ৳{{ number_format((float) $p->amount, 2) }}
                            </td>
                            <td class="px-5 py-4 font-mono text-[var(--muted-foreground)] whitespace-nowrap">
                                {{ $p->reference_code ?? ('PYT-' . $p->id) }}
                                @if($p->transaction_ref)
                                    <span class="block text-[10px] text-emerald-800 font-bold">TXN: {{ $p->transaction_ref }}</span>
                                @endif
                            </td>
                            <td class="px-5 py-4 text-center whitespace-nowrap">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase {{ $st === 'PAID' ? 'bg-emerald-100 text-emerald-900' : ($st === 'PENDING' ? 'bg-amber-100 text-amber-900' : 'bg-rose-100 text-rose-900') }}">
                                    {{ $st }}
                                </span>
                            </td>
                            <td class="px-5 py-4 text-right whitespace-nowrap">
                                @if($st === 'PENDING')
                                    <div class="inline-flex items-center gap-1.5">
                                        <form method="POST" action="/admin/payouts/{{ $p->id }}/disburse" class="inline">
                                            @csrf
                                            <input type="hidden" name="decision" value="disburse">
                                            <button type="submit" class="bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] px-3.5 py-1.5 rounded-full text-xs font-bold transition">
                                                <i class="fa-solid fa-check mr-1"></i> Disburse
                                            </button>
                                        </form>
                                        <form method="POST" action="/admin/payouts/{{ $p->id }}/disburse" class="inline">
                                            @csrf
                                            <input type="hidden" name="decision" value="cancel">
                                            <button type="submit" class="border border-rose-300 bg-rose-50 hover:bg-rose-100 text-rose-800 px-3 py-1.5 rounded-full text-xs font-bold transition">
                                                Cancel
                                            </button>
                                        </form>
                                    </div>
                                @else
                                    <span class="text-[11px] text-[var(--muted-foreground)]">
                                        {{ optional($p->paid_at ?? $p->updated_at)->format('d M Y, h:i A') }}
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-10 text-center text-[var(--muted-foreground)]">
                                No payout records match the selected filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if(method_exists($payouts, 'hasPages') && $payouts->hasPages())
            <div class="p-4 border-t border-[var(--border)]">
                {{ $payouts->links() }}
            </div>
        @endif
    </div>
</div>
@endsection

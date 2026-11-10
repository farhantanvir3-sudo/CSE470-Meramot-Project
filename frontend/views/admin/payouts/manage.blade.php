{{--
=============================================================================
FEATURE R18: Automated Vendor Payout & Net Commission Settlement
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
Architectural Layer: View (Admin/Payouts/Manage)
Controller: VendorPayoutController@index, disbursePayout
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Manage Vendor Payout Settlements | Meramot')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 md:px-6 space-y-6">
    @include('admin.partials.nav')

    <!-- Sprint 3 Notice -->
    <div class="rounded-[1.75rem] border border-amber-300 bg-amber-50/90 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-money-bill-transfer text-amber-800 mt-0.5 text-base"></i>
            <div>
                <p class="font-black text-amber-950">Automated Payout Disbursement — Scheduled for Sprint 3</p>
                <p class="text-amber-900 mt-0.5">Live 10% platform commission & 90% vendor net double-entry accounting is active right now in the Financial Ledger Overview.</p>
            </div>
        </div>
        <a href="{{ route('admin.financial-overview') }}" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 font-bold text-[var(--accent-foreground)] hover:opacity-90 transition shrink-0">
            Open Live Financial Ledger (R16) &rarr;
        </a>
    </div>

    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Vendor Net Payout Settlements</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 dev-only-badge">Feature R18 | Assigned to: <strong class="text-[var(--accent-dark)]">Md. Nawshad Zaman Khan [23301622]</strong></p>
        </div>
        <span class="dev-only-badge text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-1.5 rounded-full border border-[var(--border)]">
            Sprint 3 Preview
        </span>
    </div>

    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-[var(--border)] text-xs">
            <thead class="bg-[var(--mint-100)] text-[var(--muted-foreground)] uppercase font-bold">
                <tr>
                    <th class="px-6 py-3.5 text-left">Vendor Shop</th>
                    <th class="px-6 py-3.5 text-right">Net Payout (৳)</th>
                    <th class="px-6 py-3.5 text-left">Reference</th>
                    <th class="px-6 py-3.5 text-center">Status</th>
                    <th class="px-6 py-3.5 text-right">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border)]">
                @forelse($payouts ?? [] as $p)
                    <tr>
                        <td class="px-6 py-4 font-bold text-[var(--foreground)]">{{ $p->shop->name ?? 'Verified Partner Workshop' }}</td>
                        <td class="px-6 py-4 text-right font-mono font-bold text-[var(--accent-dark)]">৳{{ number_format($p->amount ?? 0, 2) }}</td>
                        <td class="px-6 py-4 font-mono text-[var(--muted-foreground)]">{{ $p->reference_code ?? 'PENDING-REF' }}</td>
                        <td class="px-6 py-4 text-center">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ strtoupper($p->status ?? '') === 'PAID' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $p->status ?? 'PENDING' }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-right">
                            <form method="POST" action="/admin/payouts/{{ $p->id }}/disburse" class="inline">
                                @csrf
                                <button type="submit" class="bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] px-3.5 py-1.5 rounded-full text-xs font-bold">
                                    Disburse
                                </button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-[var(--muted-foreground)] space-y-3">
                            <p>No payout withdrawal requests queued yet. Automated vendor disbursement launches in Sprint 3.</p>
                            <a href="/coming-soon/vendor-payouts" class="inline-block rounded-full border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] px-4 py-2 text-xs font-bold text-[var(--foreground)] transition">
                                View Sprint 3 Payout Roadmap &rarr;
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

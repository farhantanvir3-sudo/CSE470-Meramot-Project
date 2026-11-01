{{--
=============================================================================
FEATURE R18: Automated Vendor Payout & Net Commission Settlement
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
Architectural Layer: View (Admin/Payouts/Manage)
Controller: VendorPayoutController@index, disbursePayout
Models Touched: VendorPayout, Settlement

INSTRUCTIONS FOR NAWSHAD:
- Review vendor payout requests.
- Automatic 10% platform commission calculation.
- One-click disbursement button triggering status transition to 'disbursed'.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Manage Vendor Payout Settlements | Feature R18')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Vendor Net Payout Settlements</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R18 | Assigned to: <strong class="text-teal-700">Md. Nawshad Zaman Khan [23301622]</strong></p>
    </div>
    <span class="text-xs bg-amber-100 text-amber-800 font-semibold px-3 py-1 rounded-full border border-amber-200">
        Sprint 3 Target
    </span>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-slate-500 uppercase font-semibold">
            <tr>
                <th class="px-6 py-3 text-left">Vendor Shop</th>
                <th class="px-6 py-3 text-right">Gross (৳)</th>
                <th class="px-6 py-3 text-right">10% Platform Fee</th>
                <th class="px-6 py-3 text-right">Net Payable</th>
                <th class="px-6 py-3 text-center">Status</th>
                <th class="px-6 py-3 text-right">Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 font-mono">
            @forelse($payouts ?? [] as $p)
                <tr>
                    <td class="px-6 py-4 font-sans font-semibold text-slate-800">{{ $p->vendor->shop_name ?? 'ProTech Solutions' }}</td>
                    <td class="px-6 py-4 text-right">৳{{ number_format($p->gross_earnings, 2) }}</td>
                    <td class="px-6 py-4 text-right text-rose-600">-৳{{ number_format($p->platform_commission_amount, 2) }}</td>
                    <td class="px-6 py-4 text-right font-bold text-emerald-600">৳{{ number_format($p->net_payout_amount, 2) }}</td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $p->status === 'disbursed' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                            {{ $p->status }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right">
                        @if($p->status !== 'disbursed')
                            <form method="POST" action="/admin/payouts/{{ $p->id }}/disburse" class="inline">
                                @csrf
                                <button type="submit" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded text-xs font-sans">
                                    Disburse
                                </button>
                            </form>
                        @else
                            <span class="text-slate-400 font-sans text-xs">Settled</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="px-6 py-8 text-center text-slate-400 font-sans">No pending payout withdrawal requests.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

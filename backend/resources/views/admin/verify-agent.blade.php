{{--
=============================================================================
FEATURE R1: Delivery Agent Onboarding & Credential Verification
=============================================================================
Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
Architectural Layer: View (Admin/VerifyAgent)
Controller: AdminDeliveryController@index, approve, reject
Models Touched: DeliveryAgent, User

INSTRUCTIONS FOR NAFIUL:
- Display a table of pending delivery applicants.
- Show NID number, vehicle type, license number, and applicant contact.
- Provide "Approve" (green button) and "Reject" (red button with modal for rejection reason).
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Admin — Verify Delivery Agents | Meramot')

@section('content')
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Delivery Agent Verification Desk</h1>
            <p class="text-xs text-slate-500 mt-1 dev-only-badge">Feature R1 | Assigned to: <strong class="text-teal-700">Md. Nafiul Islam [22299035]</strong></p>
        </div>
        <span class="dev-only-badge text-xs bg-amber-100 text-amber-800 font-semibold px-3 py-1 rounded-full border border-amber-200">
            Sprint 1 Target
        </span>
    </div>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 text-sm">
        <thead class="bg-slate-50 text-slate-600 text-xs uppercase font-semibold">
            <tr>
                <th class="px-6 py-3 text-left">Agent Name</th>
                <th class="px-6 py-3 text-left">Vehicle Type</th>
                <th class="px-6 py-3 text-left">NID / License No</th>
                <th class="px-6 py-3 text-left">Status</th>
                <th class="px-6 py-3 text-right">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            @forelse($pendingAgents ?? [] as $agent)
                <tr class="hover:bg-slate-50">
                    <td class="px-6 py-4 font-medium text-slate-900">{{ $agent->user->name ?? 'Demo Applicant' }}</td>
                    <td class="px-6 py-4 capitalize text-slate-600">{{ $agent->vehicle_type ?? 'Motorcycle' }}</td>
                    <td class="px-6 py-4 font-mono text-xs text-slate-500">{{ $agent->nid_number ?? '1998239012' }}</td>
                    <td class="px-6 py-4">
                        <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200">
                            {{ $agent->verification_status ?? 'pending' }}
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right space-x-2">
                        <form method="POST" action="/admin/delivery-agents/{{ $agent->id ?? 1 }}/approve" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-1 bg-emerald-600 text-white rounded text-xs hover:bg-emerald-700">Approve</button>
                        </form>
                        <form method="POST" action="/admin/delivery-agents/{{ $agent->id ?? 1 }}/reject" class="inline">
                            @csrf
                            <button type="submit" class="px-3 py-1 bg-rose-600 text-white rounded text-xs hover:bg-rose-700">Reject</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="5" class="px-6 py-8 text-center text-slate-400 text-xs">
                        No pending delivery agent onboarding applications.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

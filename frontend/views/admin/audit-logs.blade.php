{{--
=============================================================================
FEATURE R20: System Audit Logging & Security Compliance Monitor
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
Architectural Layer: View (Admin/AuditLogs)
Controller: AuditLogController@index
Models Touched: AuditLog

INSTRUCTIONS FOR NAWSHAD:
- Real-time immutable security log viewer.
- Displays action timestamp, IP address, user agent, before/after JSON states.
- Highlights suspicious activity flags in red.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'System Audit Trail & Security Monitor | Meramot')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Security & Compliance Audit Trail</h1>
        <p class="text-xs text-slate-500 mt-1 dev-only-badge">Feature R20 | Assigned to: <strong class="text-teal-700">Md. Nawshad Zaman Khan [23301622]</strong></p>
    </div>
    <span class="dev-only-badge text-xs bg-rose-100 text-rose-800 font-semibold px-3 py-1 rounded-full border border-rose-200">
        Sprint 4 Target
    </span>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-slate-500 uppercase font-semibold">
            <tr>
                <th class="px-6 py-3 text-left">Timestamp</th>
                <th class="px-6 py-3 text-left">Event Action</th>
                <th class="px-6 py-3 text-left">Actor User</th>
                <th class="px-6 py-3 text-left">IP Address</th>
                <th class="px-6 py-3 text-center">Threat Level</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 font-mono">
            @forelse($logs ?? [] as $audit)
                <tr class="{{ $audit->is_suspicious ? 'bg-rose-50' : '' }}">
                    <td class="px-6 py-4 text-slate-500">{{ $audit->created_at->format('Y-m-d H:i:s') }}</td>
                    <td class="px-6 py-4 font-bold text-slate-900">{{ $audit->event_action }}</td>
                    <td class="px-6 py-4 text-slate-700 font-sans">{{ $audit->user->email ?? 'System' }}</td>
                    <td class="px-6 py-4 text-slate-500">{{ $audit->ip_address }}</td>
                    <td class="px-6 py-4 text-center font-sans">
                        @if($audit->is_suspicious)
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">Suspicious</span>
                        @else
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Normal</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400 font-sans">No audit events logged.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

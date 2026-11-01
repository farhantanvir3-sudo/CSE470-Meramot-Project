{{--
=============================================================================
FEATURE R19: Sitewide User Moderation & Content Governance Panel
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
Architectural Layer: View (Admin/Moderation)
Controller: AdminModerationController@index, applySanction
Models Touched: ModerationLog, Flagged

INSTRUCTIONS FOR NAWSHAD:
- Content governance panel for flagged abusive reviews or fraudulent bidding.
- Sanctions: Warning, 7-day suspension, Permanent account ban.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Content Moderation & Governance | Feature R19')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">User Moderation & Governance Panel</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R19 | Assigned to: <strong class="text-teal-700">Md. Nawshad Zaman Khan [23301622]</strong></p>
    </div>
    <span class="text-xs bg-amber-100 text-amber-800 font-semibold px-3 py-1 rounded-full border border-amber-200">
        Sprint 3 Target
    </span>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <h3 class="font-bold text-sm text-slate-800 mb-4">Flagged System Activity</h3>
    <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-slate-500 uppercase font-semibold">
            <tr>
                <th class="px-4 py-3 text-left">Target User</th>
                <th class="px-4 py-3 text-left">Incident Type</th>
                <th class="px-4 py-3 text-left">Action Taken</th>
                <th class="px-4 py-3 text-right">Sanction Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            @forelse($logs ?? [] as $log)
                <tr>
                    <td class="px-4 py-3 font-semibold text-slate-800">{{ $log->targetUser->name ?? 'User #102' }}</td>
                    <td class="px-4 py-3 text-slate-600">{{ $log->flagged_entity_type ?? 'Spam Review' }}</td>
                    <td class="px-4 py-3 capitalize font-bold text-amber-600">{{ $log->action_taken }}</td>
                    <td class="px-4 py-3 text-right space-x-1">
                        <button class="px-2 py-1 bg-amber-500 text-white rounded text-[10px]">Warn</button>
                        <button class="px-2 py-1 bg-rose-600 text-white rounded text-[10px]">Ban</button>
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No open moderation incidents reported.</td></tr>
            @endforelse
        </tbody>
    </table>
</div>
@endsection

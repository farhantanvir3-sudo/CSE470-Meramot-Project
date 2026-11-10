{{--
=============================================================================
FEATURE R19: Sitewide User Moderation & Content Governance Panel
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
Architectural Layer: View (Admin/Moderation)
Controller: AdminModerationController@index, applySanction
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Content Moderation & Governance | Meramot')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 md:px-6 space-y-6">
    @include('admin.partials.nav')

    <!-- Sprint 3 Notice -->
    <div class="rounded-[1.75rem] border border-amber-300 bg-amber-50/90 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-gavel text-amber-800 mt-0.5 text-base"></i>
            <div>
                <p class="font-black text-amber-950">Content Governance & Sanction Engine — Scheduled for Sprint 3</p>
                <p class="text-amber-900 mt-0.5">Vendor Trade License / NID verification (R11) and Delivery Courier vetting (R1) are live right now in Sprint 1.</p>
            </div>
        </div>
        <a href="/coming-soon/content-moderation" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 font-bold text-[var(--accent-foreground)] hover:opacity-90 transition shrink-0">
            Moderation Roadmap &rarr;
        </a>
    </div>

    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">User Moderation & Governance Panel</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 dev-only-badge">Feature R19 | Assigned to: <strong class="text-[var(--accent-dark)]">Md. Nawshad Zaman Khan [23301622]</strong></p>
        </div>
        <span class="dev-only-badge text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-1.5 rounded-full border border-[var(--border)]">
            Sprint 3 Preview
        </span>
    </div>

    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-sm p-6">
        <h3 class="font-black text-sm text-[var(--foreground)] mb-4">Flagged System Activity</h3>
        <table class="min-w-full divide-y divide-[var(--border)] text-xs">
            <thead class="bg-[var(--mint-100)] text-[var(--muted-foreground)] uppercase font-bold">
                <tr>
                    <th class="px-4 py-3 text-left">Target Entity</th>
                    <th class="px-4 py-3 text-left">Violation Type</th>
                    <th class="px-4 py-3 text-left">Action Taken</th>
                    <th class="px-4 py-3 text-right">Sanction Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border)]">
                @forelse($logs ?? [] as $log)
                    <tr>
                        <td class="px-4 py-3 font-bold text-[var(--foreground)]">{{ strtoupper($log->target_type ?? 'USER') }} #{{ $log->target_id ?? 1 }}</td>
                        <td class="px-4 py-3 text-[var(--muted-foreground)]">{{ $log->violation_type ?? 'Flagged Content' }}</td>
                        <td class="px-4 py-3 capitalize font-bold text-amber-700">{{ $log->action_taken }}</td>
                        <td class="px-4 py-3 text-right space-x-1">
                            <a href="/coming-soon/content-moderation" class="inline-block px-3 py-1 bg-amber-500 hover:bg-amber-600 text-white rounded-full text-[10px] font-bold">Warn</a>
                            <a href="/coming-soon/content-moderation" class="inline-block px-3 py-1 bg-rose-600 hover:bg-rose-700 text-white rounded-full text-[10px] font-bold">Ban</a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4" class="px-4 py-10 text-center text-[var(--muted-foreground)] space-y-3">
                            <p>No open moderation incidents reported. Automated review flagging & sanction workflows launch in Sprint 3.</p>
                            <a href="/coming-soon/content-moderation" class="inline-block rounded-full border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] px-4 py-2 text-xs font-bold text-[var(--foreground)] transition">
                                Preview Sanction Controls &rarr;
                            </a>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

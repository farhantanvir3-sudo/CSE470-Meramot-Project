{{--
=============================================================================
FEATURE R20: System Audit Logging & Security Compliance Monitor
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
Architectural Layer: View (Admin/AuditLogs)
Controller: AuditLogController@index
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'System Audit Trail & Security Monitor | Meramot')

@section('content')
<div class="mx-auto max-w-6xl px-3.5 py-4 sm:px-4 sm:py-6 md:px-6 space-y-4 sm:space-y-6">
    @include('admin.partials.nav')

    <!-- Governance Notice -->
    <div class="rounded-2xl sm:rounded-[1.75rem] border border-amber-300 bg-amber-50/90 p-3.5 sm:p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 sm:gap-4 text-xs">
        <div class="flex items-start gap-2.5 sm:gap-3">
            <i class="fa-solid fa-shield-halved text-amber-800 mt-0.5 text-base shrink-0"></i>
            <div>
                <p class="font-black text-amber-950">Security &amp; Compliance Audit Monitor — Upcoming Release Expansion</p>
                <p class="text-amber-900 mt-0.5 leading-relaxed">Expanded state-diff auditing and suspicious access anomaly alerts are scheduled for an upcoming platform release.</p>
            </div>
        </div>
        <a href="/coming-soon/security-audit-logs" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 font-bold text-[var(--accent-foreground)] hover:opacity-90 transition shrink-0 text-center">
            Compliance Roadmap &rarr;
        </a>
    </div>

    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h1 class="text-xl sm:text-2xl md:text-3xl font-black text-[var(--foreground)]">Security &amp; Compliance Audit Trail</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 dev-only-badge">Feature R20 | Assigned to: <strong class="text-[var(--accent-dark)]">Md. Nawshad Zaman Khan [23301622]</strong></p>
        </div>
        <span class="dev-only-badge text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-1.5 rounded-full border border-[var(--border)]">
            Audit Monitor
        </span>
    </div>

    <div class="rounded-2xl sm:rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-xs">
                <thead class="bg-[var(--mint-100)] text-[var(--muted-foreground)] uppercase font-bold">
                    <tr>
                        <th class="px-6 py-3.5 text-left whitespace-nowrap">Timestamp</th>
                        <th class="px-6 py-3.5 text-left whitespace-nowrap">Action</th>
                        <th class="px-6 py-3.5 text-left whitespace-nowrap">Resource</th>
                        <th class="px-6 py-3.5 text-left whitespace-nowrap">Actor</th>
                        <th class="px-6 py-3.5 text-left whitespace-nowrap">IP Address</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)] font-mono">
                    @forelse($logs ?? [] as $audit)
                        <tr>
                            <td class="px-6 py-4 text-[var(--muted-foreground)] whitespace-nowrap">{{ $audit->created_at?->format('Y-m-d H:i:s') }}</td>
                            <td class="px-6 py-4 font-bold text-[var(--foreground)] whitespace-nowrap">{{ $audit->action ?? 'SYSTEM_EVENT' }}</td>
                            <td class="px-6 py-4 text-[var(--muted-foreground)] whitespace-nowrap">{{ $audit->target_resource ?? 'platform' }}</td>
                            <td class="px-6 py-4 text-[var(--foreground)] font-sans whitespace-nowrap">{{ $audit->actor->email ?? 'System' }}</td>
                            <td class="px-6 py-4 text-[var(--muted-foreground)] whitespace-nowrap">{{ $audit->ip_address ?? '127.0.0.1' }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-10 text-center text-[var(--muted-foreground)] font-sans space-y-3">
                                <p>No compliance audit events recorded yet. Automated state-diff logging will expand in an upcoming platform release.</p>
                                <a href="/coming-soon/security-audit-logs" class="inline-block rounded-full border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] px-4 py-2 text-xs font-bold text-[var(--foreground)] transition">
                                    View Security Compliance Roadmap &rarr;
                                </a>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

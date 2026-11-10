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
<div class="mx-auto max-w-6xl px-4 py-6 md:px-6 space-y-6">
    @include('admin.partials.nav')

    <!-- Sprint 4 Notice -->
    <div class="rounded-[1.75rem] border border-amber-300 bg-amber-50/90 p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 text-xs">
        <div class="flex items-start gap-3">
            <i class="fa-solid fa-shield-halved text-amber-800 mt-0.5 text-base"></i>
            <div>
                <p class="font-black text-amber-950">Security & Compliance Audit Monitor — Scheduled for Sprint 4</p>
                <p class="text-amber-900 mt-0.5">Immutable state-diff logging and suspicious IP anomaly alerts roll out in Sprint 4.</p>
            </div>
        </div>
        <a href="/coming-soon/security-audit-logs" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 font-bold text-[var(--accent-foreground)] hover:opacity-90 transition shrink-0">
            Audit Roadmap &rarr;
        </a>
    </div>

    <div class="flex flex-wrap justify-between items-center gap-3">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Security & Compliance Audit Trail</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 dev-only-badge">Feature R20 | Assigned to: <strong class="text-[var(--accent-dark)]">Md. Nawshad Zaman Khan [23301622]</strong></p>
        </div>
        <span class="dev-only-badge text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-1.5 rounded-full border border-[var(--border)]">
            Sprint 4 Preview
        </span>
    </div>

    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-sm overflow-hidden">
        <table class="min-w-full divide-y divide-[var(--border)] text-xs">
            <thead class="bg-[var(--mint-100)] text-[var(--muted-foreground)] uppercase font-bold">
                <tr>
                    <th class="px-6 py-3.5 text-left">Timestamp</th>
                    <th class="px-6 py-3.5 text-left">Action</th>
                    <th class="px-6 py-3.5 text-left">Resource</th>
                    <th class="px-6 py-3.5 text-left">Actor</th>
                    <th class="px-6 py-3.5 text-left">IP Address</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-[var(--border)] font-mono">
                @forelse($logs ?? [] as $audit)
                    <tr>
                        <td class="px-6 py-4 text-[var(--muted-foreground)]">{{ $audit->created_at?->format('Y-m-d H:i:s') }}</td>
                        <td class="px-6 py-4 font-bold text-[var(--foreground)]">{{ $audit->action ?? 'SYSTEM_EVENT' }}</td>
                        <td class="px-6 py-4 text-[var(--muted-foreground)]">{{ $audit->target_resource ?? 'platform' }}</td>
                        <td class="px-6 py-4 text-[var(--foreground)] font-sans">{{ $audit->actor->email ?? 'System' }}</td>
                        <td class="px-6 py-4 text-[var(--muted-foreground)]">{{ $audit->ip_address ?? '127.0.0.1' }}</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="px-6 py-10 text-center text-[var(--muted-foreground)] font-sans space-y-3">
                            <p>No compliance audit events recorded yet. Automated state-diff auditing activates in Sprint 4.</p>
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
@endsection

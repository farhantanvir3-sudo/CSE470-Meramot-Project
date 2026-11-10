@extends('layouts.app')

@section('title', 'Admin — Verify Delivery Agents | Meramot')

{{-- ==========================================================================
     FEATURE R1: Delivery Agent Onboarding & Credential Verification
     Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
     Commits by Nafiul:
       - c6c0781: Pending rider table + Approve & Reject actions
       - d71bb8f: NID & Driving License number display in verification table
       - fdf5e6f: Inline rejection_reason input & persistence on Reject
     Relocated To: frontend/views/admin/verify-agent.blade.php
     Merged with Original Meramot `DeliveryAdminDashboard`:
       - Farhan Tanvir [Student ID: 22299179]
       - 6 Operations KPI StatCards, Document Preview Links (Profile, NID,
         Education, CV), and All Delivery Partners Directory with Block/Suspend
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-8">

    <!-- Page Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">Delivery Operations</p>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Delivery Partner Management & Verification</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Verify pending rider NID, BRTA driving license, and uploaded documents before activating doorstep pickup & return credentials.
            </p>
            <span class="dev-only-badge text-[10px] font-mono px-2.5 py-0.5 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mt-1.5">
                Feature R1 • Assigned to: Md. Nafiul Islam [22299035]
            </span>
        </div>
        <div class="flex items-center gap-2">
            <a href="/delivery-agent/register" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                + Open Rider Application Form
            </a>
            <a href="/admin/delivery-agents/pending" class="rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] px-4 py-2 text-xs font-bold hover:opacity-90 transition">
                Refresh Desk
            </a>
        </div>
    </div>

    <!-- Flash Feedback Banners -->
    @if(session('success'))
        <div class="rounded-2xl border border-emerald-600/40 bg-[var(--mint-100)] px-4 py-3 text-xs font-bold text-[var(--accent-dark)] flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('warning'))
        <div class="rounded-2xl border border-amber-500/50 bg-amber-50/90 px-4 py-3 text-xs font-bold text-amber-900 flex items-center gap-2">
            <i class="fa-solid fa-triangle-exclamation text-amber-600"></i>
            <span>{{ session('warning') }}</span>
        </div>
    @endif
    @error('rejection_reason')
        <div class="rounded-2xl border border-rose-500/50 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-800">
            {{ $message }}
        </div>
    @enderror

    <!-- 6 Operations KPI StatCards (Ported from Original Meramot `DeliveryAdminDashboard`) -->
    @if(isset($stats))
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            <div class="rounded-2xl border border-amber-500/40 bg-[var(--card)] p-5 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Pending Approvals</p>
                <p class="mt-2 text-3xl font-black text-[var(--foreground)]">{{ $stats['pendingRegistrations'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-emerald-500/40 bg-[var(--card)] p-5 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Active Approved Partners</p>
                <p class="mt-2 text-3xl font-black text-[var(--foreground)]">{{ $stats['activeApprovedPartners'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-sky-500/40 bg-[var(--card)] p-5 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Completed Deliveries (All Time)</p>
                <p class="mt-2 text-3xl font-black text-[var(--foreground)]">{{ $stats['completedDeliveriesTotal'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-violet-500/40 bg-[var(--card)] p-5 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Partners With &ge;1 Completed Job</p>
                <p class="mt-2 text-3xl font-black text-[var(--foreground)]">{{ $stats['partnersWithCompletedDeliveries'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Total Partner Profiles</p>
                <p class="mt-2 text-3xl font-black text-[var(--foreground)]">{{ $stats['totalPartners'] ?? 0 }}</p>
            </div>
            <div class="rounded-2xl border border-rose-500/40 bg-[var(--card)] p-5 shadow-sm">
                <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Rejected Registrations</p>
                <p class="mt-2 text-3xl font-black text-[var(--foreground)]">{{ $stats['rejectedPartners'] ?? 0 }}</p>
            </div>
        </div>
    @endif

    {{-- ======================================================================
         RELOCATED & RESKINNED BLOCK 1: Pending Registration Approvals Table
         Original Author: Md. Nafiul Islam [22299035] (Commits c6c0781, d71bb8f, fdf5e6f)
         Merged with Original Meramot Document Links (Profile, NID, Education, CV)
         ====================================================================== --}}
    <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[var(--border)] flex items-center justify-between bg-[var(--mint-100)]/60">
            <h2 class="text-sm font-black text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-id-card-clip text-[var(--accent-dark)]"></i>
                Pending Registration Approvals ({{ ($pendingAgents ?? collect())->count() }})
            </h2>
            <span class="text-[11px] font-semibold text-[var(--muted-foreground)]">Awaiting Admin Credential Verification</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-xs">
                <thead class="bg-[var(--mint-100)] text-[var(--muted-foreground)] text-[11px] uppercase font-bold tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5 text-left">Partner</th>
                        <th class="px-5 py-3.5 text-left">Vehicle Type</th>
                        <th class="px-5 py-3.5 text-left">NID / License No</th>
                        <th class="px-5 py-3.5 text-left">Documents</th>
                        <th class="px-5 py-3.5 text-left">Status</th>
                        <th class="px-5 py-3.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @forelse($pendingAgents ?? [] as $agent)
                        <tr class="hover:bg-[var(--mint-100)]/50 transition">
                            <td class="px-5 py-4">
                                <div class="font-bold text-[var(--foreground)]">{{ $agent->user->name ?? 'Demo Applicant' }}</div>
                                <div class="text-[11px] text-[var(--muted-foreground)]">
                                    {{ $agent->user->email ?? '—' }}
                                    @if($agent->user?->phone)
                                        • {{ $agent->user->phone }}
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4 capitalize font-semibold text-[var(--foreground)]">
                                <span class="px-2.5 py-1 rounded-full bg-[var(--mint-100)] border border-[var(--border)]">
                                    {{ $agent->vehicle_type ?? 'Motorcycle' }}
                                </span>
                            </td>
                            {{-- NID & License Number block by Md. Nafiul Islam [22299035] (Commit d71bb8f) --}}
                            <td class="px-5 py-4 font-mono text-xs text-[var(--foreground)]">
                                <div>NID: {{ $agent->nid_number ?? '-' }}</div>
                                <div>License: {{ $agent->license_number ?? '-' }}</div>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex flex-wrap gap-1.5 text-[11px] font-semibold">
                                    @if($agent->profile_picture_url || $agent->user?->avatar_url)
                                        <a href="{{ $agent->profile_picture_url ?? $agent->user->avatar_url }}" target="_blank" class="px-2 py-0.5 rounded-md bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] hover:underline">Profile</a>
                                    @endif
                                    @if($agent->nid_document_url)
                                        <a href="{{ $agent->nid_document_url }}" target="_blank" class="px-2 py-0.5 rounded-md bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] hover:underline">Open NID</a>
                                    @endif
                                    @if($agent->education_document_url)
                                        <a href="{{ $agent->education_document_url }}" target="_blank" class="px-2 py-0.5 rounded-md bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] hover:underline">Education</a>
                                    @endif
                                    @if($agent->cv_document_url)
                                        <a href="{{ $agent->cv_document_url }}" target="_blank" class="px-2 py-0.5 rounded-md bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] hover:underline">Open CV</a>
                                    @endif
                                    @if(!$agent->profile_picture_url && !$agent->nid_document_url && !$agent->education_document_url && !$agent->cv_document_url)
                                        <span class="text-[var(--muted-foreground)]">ID numbers provided</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                <span class="px-2.5 py-1 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                    {{ $agent->registration_status ?? 'PENDING' }}
                                </span>
                            </td>
                            {{-- Approve & Reject with `rejection_reason` by Md. Nafiul Islam [22299035] (Commits c6c0781 & fdf5e6f) --}}
                            <td class="px-5 py-4 text-right">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <form method="POST" action="/admin/delivery-agents/{{ $agent->id ?? 1 }}/approve" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3.5 py-1.5 bg-[var(--accent-dark)] text-[var(--accent-foreground)] rounded-full text-xs font-bold hover:opacity-90 transition shadow-sm">
                                            Approve
                                        </button>
                                    </form>
                                    <form method="POST" action="/admin/delivery-agents/{{ $agent->id ?? 1 }}/reject" class="inline-flex items-center gap-2">
                                        @csrf
                                        <input type="text" name="rejection_reason" placeholder="Reason for rejection" required
                                               class="rounded-full border border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-3 py-1.5 text-xs text-[var(--foreground)] placeholder:text-[var(--muted-foreground)] w-44 outline-none">
                                        <button type="submit" class="px-3.5 py-1.5 bg-rose-700 text-white rounded-full text-xs font-bold hover:bg-rose-800 transition shadow-sm">
                                            Reject
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-8 text-center text-[var(--muted-foreground)] text-xs">
                                No pending delivery agent onboarding applications.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ======================================================================
         BLOCK 2: All Delivery Partners Directory
         Ported from Original Meramot `DeliveryAdminDashboard` + Nafiul's
         `rejection_reason` column (`fdf5e6f`)
         ====================================================================== --}}
    @if(isset($allPartners) && $allPartners->count() > 0)
        <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--border)] flex items-center justify-between bg-[var(--mint-100)]/40">
                <h2 class="text-sm font-black text-[var(--foreground)] flex items-center gap-2">
                    <i class="fa-solid fa-motorcycle text-[var(--accent-dark)]"></i>
                    All Delivery Partners Directory ({{ $allPartners->count() }})
                </h2>
                <span class="text-[11px] font-semibold text-[var(--muted-foreground)]">Includes Approved, Pending & Rejected Riders with Rejection Reasons</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--border)] text-xs">
                    <thead class="bg-[var(--mint-100)] text-[var(--muted-foreground)] text-[11px] uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-5 py-3 text-left">Partner</th>
                            <th class="px-5 py-3 text-left">Registration</th>
                            <th class="px-5 py-3 text-left">Agent Status</th>
                            <th class="px-5 py-3 text-left">NID / License No</th>
                            <th class="px-5 py-3 text-left">Completed Jobs</th>
                            <th class="px-5 py-3 text-left">Rejection Reason</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                        @foreach($allPartners as $partner)
                            <tr class="hover:bg-[var(--mint-100)]/40 transition">
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-[var(--foreground)]">{{ $partner->user->name ?? ('Rider #' . $partner->id) }}</div>
                                    <div class="text-[11px] text-[var(--muted-foreground)]">{{ $partner->user->email ?? '—' }}</div>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if($partner->registration_status === 'APPROVED')
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                            APPROVED
                                        </span>
                                    @elseif($partner->registration_status === 'PENDING')
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300">
                                            PENDING
                                        </span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-300">
                                            {{ $partner->registration_status }}
                                        </span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 font-semibold text-[var(--foreground)]">
                                    {{ $partner->status ?? 'OFFLINE' }}
                                    <span class="text-[10px] text-[var(--muted-foreground)]">({{ $partner->is_active ? 'Active' : 'Inactive' }})</span>
                                </td>
                                <td class="px-5 py-3.5 font-mono text-[11px] text-[var(--foreground)]">
                                    <div>NID: {{ $partner->nid_number ?? '-' }}</div>
                                    <div>License: {{ $partner->license_number ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-3.5 font-mono font-bold text-[var(--foreground)]">
                                    {{ $partner->completed_deliveries_count ?? 0 }}
                                </td>
                                <td class="px-5 py-3.5 text-[var(--foreground)]">
                                    @if($partner->rejection_reason)
                                        <span class="inline-flex items-center gap-1.5 rounded-xl bg-rose-50 border border-rose-200 px-3 py-1 text-rose-900 font-medium">
                                            <i class="fa-solid fa-circle-xmark text-rose-600"></i>
                                            {{ $partner->rejection_reason }}
                                        </span>
                                    @else
                                        <span class="text-[var(--muted-foreground)]">—</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($partner->is_active)
                                            <form method="POST" action="/admin/delivery-agents/{{ $partner->id }}/block" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 rounded-full border border-amber-300 bg-amber-50 text-amber-800 text-[11px] font-bold hover:bg-amber-100 transition">
                                                    Block
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="/admin/delivery-agents/{{ $partner->id }}/approve" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-[11px] font-bold hover:opacity-90 transition">
                                                    Activate
                                                </button>
                                            </form>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

</div>
@endsection

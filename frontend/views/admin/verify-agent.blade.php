@extends('layouts.app')

@section('title', 'Admin — Verify Delivery Agents | Meramot')

{{-- ==========================================================================
     FEATURE R1: Delivery Agent Onboarding & Credential Verification
     Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
     Commits by Nafiul:
       - c6c0781: Pending rider table + Approve & Reject actions
       - d71bb8f: NID & Driving License number display in verification table
       - fdf5e6f: Inline rejection_reason input & persistence on Reject
     Renewed Verification Inspector, Document Viewer, One-Time `@meramotbd.com`
     Credential Generation & Application Deletion:
       - Farhan Tanvir [Student ID: 22299179]
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-8" x-data="{ inspectAgent: null }">

    @include('admin.partials.nav')

    <!-- Page Header -->
    <div class="flex items-start justify-between gap-4">
        <div>
            <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">Delivery Operations</p>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Delivery Partner Management &amp; Verification</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Inspect applicant personal details, NID, BRTA driving license, and uploaded scans. Approving generates a unique <code class="font-mono font-bold text-[var(--accent-dark)]">@meramotbd.com</code> company email and secure password once and emails them to the applicant's personal inbox.
            </p>
            <span class="dev-only-badge text-[10px] font-mono px-2.5 py-0.5 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mt-1.5">
                Feature R1 &bull; Assigned to: Md. Nafiul Islam [22299035]
            </span>
        </div>
        <a
            href="/admin/delivery-agents/pending"
            title="Refresh Desk"
            aria-label="Refresh Desk"
            class="group mt-1 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] hover:shadow-[0_8px_20px_-4px_rgba(22,54,37,0.35)]"
        >
            <i class="fa-solid fa-rotate-right text-sm transition-transform duration-300 group-hover:rotate-180"></i>
        </a>
    </div>

    @error('rejection_reason')
        <div class="rounded-2xl border border-rose-500/50 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-800">
            <i class="fa-solid fa-circle-exclamation mr-1"></i> {{ $message }}
        </div>
    @enderror

    <!-- 6 Operations KPI StatCards -->
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
         BLOCK 1: Pending Registration Approvals Table
         ====================================================================== --}}
    <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-sm overflow-hidden">
        <div class="px-6 py-4 border-b border-[var(--border)] flex items-center justify-between bg-[var(--mint-100)]/60">
            <h2 class="text-sm font-black text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-id-card-clip text-[var(--accent-dark)]"></i>
                Pending Registration Approvals ({{ ($pendingAgents ?? collect())->count() }})
            </h2>
            <span class="text-[11px] font-semibold text-[var(--muted-foreground)]">Verify Details &amp; Documents Before Approving or Rejecting</span>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[var(--border)] text-xs">
                <thead class="bg-[var(--mint-100)] text-[var(--muted-foreground)] text-[11px] uppercase font-bold tracking-wider">
                    <tr>
                        <th class="px-5 py-3.5 text-left">Applicant &amp; Personal Email</th>
                        <th class="px-5 py-3.5 text-left">Vehicle</th>
                        <th class="px-5 py-3.5 text-left">NID / License No</th>
                        <th class="px-5 py-3.5 text-left">Documents &amp; Inspection</th>
                        <th class="px-5 py-3.5 text-right">Verify / Reject / Delete</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]">
                    @forelse($pendingAgents ?? [] as $agent)
                        @php
                            $dispName = $agent->applicant_name ?: ($agent->user?->name ?? 'Delivery Applicant');
                            $dispPersonalEmail = $agent->personal_email ?: ($agent->user && !str_ends_with((string) $agent->user->email, '.meramot.internal') ? $agent->user->email : '—');
                            $dispPhone = $agent->applicant_phone ?: ($agent->user?->phone ?? '—');
                            $inspectorPayload = [
                                'id' => $agent->id,
                                'name' => $dispName,
                                'personal_email' => $dispPersonalEmail,
                                'company_email' => $agent->company_email,
                                'phone' => $dispPhone,
                                'vehicle_type' => $agent->vehicle_type,
                                'nid_number' => $agent->nid_number,
                                'license_number' => $agent->license_number,
                                'status' => $agent->registration_status,
                                'rejection_reason' => $agent->rejection_reason,
                                'submitted_at' => optional($agent->updated_at)->format('d M Y, h:i A'),
                                'has_profile' => (bool) ($agent->profile_picture_url || $agent->user?->avatar_url),
                                'has_nid' => (bool) $agent->nid_document_url,
                                'has_license' => (bool) $agent->license_document_url,
                                'has_education' => (bool) $agent->education_document_url,
                                'has_cv' => (bool) $agent->cv_document_url,
                            ];
                        @endphp
                        <tr class="hover:bg-[var(--mint-100)]/50 transition align-top">
                            <td class="px-5 py-4">
                                <div class="font-bold text-[var(--foreground)]">{{ $dispName }}</div>
                                <div class="font-mono text-[11px] text-[var(--accent-dark)]">{{ $dispPersonalEmail }}</div>
                                <div class="text-[11px] text-[var(--muted-foreground)]">{{ $dispPhone }}</div>
                            </td>
                            <td class="px-5 py-4 capitalize font-semibold text-[var(--foreground)]">
                                <span class="px-2.5 py-1 rounded-full bg-[var(--mint-100)] border border-[var(--border)]">
                                    {{ $agent->vehicle_type ?? 'Motorcycle' }}
                                </span>
                            </td>
                            <td class="px-5 py-4 font-mono text-xs text-[var(--foreground)]">
                                <div>NID: {{ $agent->nid_number ?? '-' }}</div>
                                <div>License: {{ $agent->license_number ?? '-' }}</div>
                            </td>
                            <td class="px-5 py-4 space-y-2">
                                <button
                                    type="button"
                                    @click='inspectAgent = @json($inspectorPayload)'
                                    class="inline-flex items-center gap-1.5 rounded-full bg-white border border-[var(--accent-dark)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--accent-dark)] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-[var(--mint-100)] hover:shadow"
                                >
                                    <i class="fa-solid fa-magnifying-glass-plus"></i>
                                    <span>Inspect Info &amp; Docs</span>
                                </button>

                                <div class="flex flex-wrap gap-1.5 text-[11px] font-semibold">
                                    @if($agent->profile_picture_url || $agent->user?->avatar_url)
                                        <a href="/admin/delivery-agents/{{ $agent->id }}/document/profile" target="_blank" class="px-2 py-0.5 rounded-md bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] hover:underline">Photo</a>
                                    @endif
                                    @if($agent->nid_document_url)
                                        <a href="/admin/delivery-agents/{{ $agent->id }}/document/nid" target="_blank" class="px-2 py-0.5 rounded-md bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] hover:underline">NID Scan</a>
                                    @endif
                                    @if($agent->license_document_url)
                                        <a href="/admin/delivery-agents/{{ $agent->id }}/document/license" target="_blank" class="px-2 py-0.5 rounded-md bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] hover:underline">License Scan</a>
                                    @endif
                                    @if($agent->education_document_url)
                                        <a href="/admin/delivery-agents/{{ $agent->id }}/document/education" target="_blank" class="px-2 py-0.5 rounded-md bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] hover:underline">Education</a>
                                    @endif
                                    @if($agent->cv_document_url)
                                        <a href="/admin/delivery-agents/{{ $agent->id }}/document/cv" target="_blank" class="px-2 py-0.5 rounded-md bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] hover:underline">CV</a>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4 text-right space-y-2">
                                <div class="flex flex-wrap items-center justify-end gap-2">
                                    <form method="POST" action="/admin/delivery-agents/{{ $agent->id }}/approve" class="inline">
                                        @csrf
                                        <button type="submit" class="px-3.5 py-1.5 bg-[var(--accent-dark)] text-[var(--accent-foreground)] rounded-full text-xs font-bold shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-md">
                                            <i class="fa-solid fa-check mr-1"></i>Approve
                                        </button>
                                    </form>

                                    <form method="POST" action="/admin/delivery-agents/{{ $agent->id }}" onsubmit="return confirm('Permanently delete this application? Once deleted, the applicant can no longer edit it.');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="px-3 py-1.5 rounded-full border border-rose-300 bg-white text-rose-700 text-xs font-bold shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-rose-50 hover:shadow">
                                            <i class="fa-solid fa-trash-can"></i> Delete
                                        </button>
                                    </form>
                                </div>

                                <form method="POST" action="/admin/delivery-agents/{{ $agent->id }}/reject" class="flex flex-wrap items-center justify-end gap-2 pt-1">
                                    @csrf
                                    <input type="text" name="rejection_reason" placeholder="Required textual reason for rejection..." required
                                           class="rounded-xl border border-[var(--border)] bg-white px-3 py-1.5 text-xs text-[var(--foreground)] placeholder:text-[var(--muted-foreground)] w-60 outline-none focus:border-rose-600">
                                    <button type="submit" class="px-3.5 py-1.5 bg-rose-700 text-white rounded-full text-xs font-bold shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-rose-800 hover:shadow-md">
                                        Reject
                                    </button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-[var(--muted-foreground)] text-xs">
                                No pending delivery agent onboarding applications right now.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- ======================================================================
         BLOCK 2: All Delivery Partners Directory
         ====================================================================== --}}
    @if(isset($allPartners) && $allPartners->count() > 0)
        <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-[var(--border)] flex items-center justify-between bg-[var(--mint-100)]/40">
                <h2 class="text-sm font-black text-[var(--foreground)] flex items-center gap-2">
                    <i class="fa-solid fa-motorcycle text-[var(--accent-dark)]"></i>
                    All Delivery Partners Directory ({{ $allPartners->count() }})
                </h2>
                <span class="text-[11px] font-semibold text-[var(--muted-foreground)]">Approved (`@meramotbd.com`), Pending &amp; Rejected Applications</span>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-[var(--border)] text-xs">
                    <thead class="bg-[var(--mint-100)] text-[var(--muted-foreground)] text-[11px] uppercase font-bold tracking-wider">
                        <tr>
                            <th class="px-5 py-3 text-left">Partner &amp; Emails</th>
                            <th class="px-5 py-3 text-left">Registration</th>
                            <th class="px-5 py-3 text-left">NID / License No</th>
                            <th class="px-5 py-3 text-left">Completed</th>
                            <th class="px-5 py-3 text-left">Rejection Reason</th>
                            <th class="px-5 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[var(--border)]">
                        @foreach($allPartners as $partner)
                            @php
                                $pName = $partner->applicant_name ?: ($partner->user?->name ?? ('Rider #' . $partner->id));
                                $pPersonal = $partner->personal_email ?: ($partner->user && !str_ends_with((string) $partner->user->email, '.meramot.internal') ? $partner->user->email : '—');
                            @endphp
                            <tr class="hover:bg-[var(--mint-100)]/40 transition">
                                <td class="px-5 py-3.5">
                                    <div class="font-bold text-[var(--foreground)]">{{ $pName }}</div>
                                    @if($partner->company_email)
                                        <div class="font-mono text-[11px] font-bold text-emerald-800">Company: {{ $partner->company_email }}</div>
                                    @endif
                                    <div class="text-[11px] text-[var(--muted-foreground)]">Personal: {{ $pPersonal }}</div>
                                </td>
                                <td class="px-5 py-3.5">
                                    @if($partner->registration_status === 'APPROVED')
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">APPROVED</span>
                                    @elseif($partner->registration_status === 'PENDING')
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-amber-100 text-amber-800 border border-amber-300">PENDING</span>
                                    @else
                                        <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-rose-100 text-rose-800 border border-rose-300">{{ $partner->registration_status }}</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 font-mono text-[11px] text-[var(--foreground)]">
                                    <div>NID: {{ $partner->nid_number ?? '-' }}</div>
                                    <div>License: {{ $partner->license_number ?? '-' }}</div>
                                </td>
                                <td class="px-5 py-3.5 font-mono font-bold text-[var(--foreground)]">
                                    {{ $partner->completed_deliveries_count ?? 0 }}
                                </td>
                                <td class="px-5 py-3.5 text-[var(--foreground)] max-w-xs">
                                    @if($partner->rejection_reason)
                                        <span class="inline-flex items-start gap-1.5 rounded-xl bg-rose-50 border border-rose-200 px-3 py-1.5 text-rose-900 font-medium">
                                            <i class="fa-solid fa-circle-xmark text-rose-600 mt-0.5"></i>
                                            <span>{{ $partner->rejection_reason }}</span>
                                        </span>
                                    @else
                                        <span class="text-[var(--muted-foreground)]">&mdash;</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3.5 text-right">
                                    <div class="flex items-center justify-end gap-2">
                                        @if($partner->is_active)
                                            <form method="POST" action="/admin/delivery-agents/{{ $partner->id }}/block" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 rounded-full border border-amber-300 bg-amber-50 text-amber-800 text-[11px] font-bold hover:bg-amber-100 transition">
                                                    Suspend
                                                </button>
                                            </form>
                                        @else
                                            <form method="POST" action="/admin/delivery-agents/{{ $partner->id }}/approve" class="inline">
                                                @csrf
                                                <button type="submit" class="px-3 py-1 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-[11px] font-bold hover:opacity-90 transition">
                                                    {{ $partner->registration_status === 'APPROVED' ? 'Activate' : 'Approve' }}
                                                </button>
                                            </form>
                                        @endif

                                        <form method="POST" action="/admin/delivery-agents/{{ $partner->id }}" onsubmit="return confirm('Delete this delivery partner application permanently?');" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="px-2.5 py-1 rounded-full border border-rose-300 bg-white text-rose-700 text-[11px] font-bold hover:bg-rose-50 transition" title="Delete Application">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <!-- ── Applicant Info & Document Verification Modal ── -->
    <div x-show="inspectAgent" x-cloak class="fixed inset-0 z-[140] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" @click="inspectAgent = null"></div>
        <div class="relative z-[141] w-full max-w-2xl rounded-[2rem] border border-[var(--border)] bg-white p-6 md:p-8 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
            <div class="flex items-start justify-between gap-3 border-b border-[#e7efe2] pb-4">
                <div>
                    <span class="text-[10px] font-black uppercase tracking-wider text-[#214c34]">Admin Verification Dossier</span>
                    <h3 class="text-xl font-black text-[#163625]" x-text="inspectAgent?.name"></h3>
                    <p class="font-mono text-xs text-[#5e6d64]" x-text="'Personal Email: ' + (inspectAgent?.personal_email || '—')"></p>
                </div>
                <button type="button" @click="inspectAgent = null" class="h-9 w-9 rounded-full bg-[#eef4ea] text-[#163625] font-bold">✕</button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 rounded-2xl border border-[#d9e5d5] bg-[#f8fbf6] p-4 text-xs">
                <div>
                    <span class="text-[#5e6d64] block">Mobile Phone</span>
                    <span class="font-bold text-[#163625]" x-text="inspectAgent?.phone || '—'"></span>
                </div>
                <div>
                    <span class="text-[#5e6d64] block">Vehicle Type</span>
                    <span class="font-bold capitalize text-[#163625]" x-text="inspectAgent?.vehicle_type || '—'"></span>
                </div>
                <div>
                    <span class="text-[#5e6d64] block">National ID (NID)</span>
                    <span class="font-mono font-bold text-[#163625]" x-text="inspectAgent?.nid_number || '—'"></span>
                </div>
                <div>
                    <span class="text-[#5e6d64] block">BRTA Driving License</span>
                    <span class="font-mono font-bold text-[#163625]" x-text="inspectAgent?.license_number || '—'"></span>
                </div>
                <div class="sm:col-span-2">
                    <span class="text-[#5e6d64] block">Last Updated</span>
                    <span class="font-semibold text-[#163625]" x-text="inspectAgent?.submitted_at || '—'"></span>
                </div>
            </div>

            <div class="space-y-2">
                <h4 class="text-xs font-black uppercase tracking-wider text-[#163625]">Uploaded Verification Documents</h4>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2.5 text-xs">
                    <template x-if="inspectAgent?.has_profile">
                        <a :href="'/admin/delivery-agents/' + inspectAgent.id + '/document/profile'" target="_blank" class="flex items-center justify-between rounded-xl border border-[#c8dac2] bg-[#eef5ea] px-4 py-2.5 font-bold text-[#163625] hover:bg-[#dcebd5]">
                            <span><i class="fa-solid fa-camera mr-2"></i>Profile Photo</span>
                            <span>Open &nearr;</span>
                        </a>
                    </template>
                    <template x-if="inspectAgent?.has_nid">
                        <a :href="'/admin/delivery-agents/' + inspectAgent.id + '/document/nid'" target="_blank" class="flex items-center justify-between rounded-xl border border-[#c8dac2] bg-[#eef5ea] px-4 py-2.5 font-bold text-[#163625] hover:bg-[#dcebd5]">
                            <span><i class="fa-solid fa-id-card mr-2"></i>NID Scan</span>
                            <span>Open &nearr;</span>
                        </a>
                    </template>
                    <template x-if="inspectAgent?.has_license">
                        <a :href="'/admin/delivery-agents/' + inspectAgent.id + '/document/license'" target="_blank" class="flex items-center justify-between rounded-xl border border-[#c8dac2] bg-[#eef5ea] px-4 py-2.5 font-bold text-[#163625] hover:bg-[#dcebd5]">
                            <span><i class="fa-solid fa-id-badge mr-2"></i>Driving License Scan</span>
                            <span>Open &nearr;</span>
                        </a>
                    </template>
                    <template x-if="inspectAgent?.has_education">
                        <a :href="'/admin/delivery-agents/' + inspectAgent.id + '/document/education'" target="_blank" class="flex items-center justify-between rounded-xl border border-[#c8dac2] bg-[#eef5ea] px-4 py-2.5 font-bold text-[#163625] hover:bg-[#dcebd5]">
                            <span><i class="fa-solid fa-graduation-cap mr-2"></i>Education Certificate</span>
                            <span>Open &nearr;</span>
                        </a>
                    </template>
                    <template x-if="inspectAgent?.has_cv">
                        <a :href="'/admin/delivery-agents/' + inspectAgent.id + '/document/cv'" target="_blank" class="flex items-center justify-between rounded-xl border border-[#c8dac2] bg-[#eef5ea] px-4 py-2.5 font-bold text-[#163625] hover:bg-[#dcebd5] sm:col-span-2">
                            <span><i class="fa-solid fa-file-lines mr-2"></i>CV / Resume</span>
                            <span>Open &nearr;</span>
                        </a>
                    </template>
                </div>
                <template x-if="inspectAgent && !inspectAgent.has_profile && !inspectAgent.has_nid && !inspectAgent.has_license && !inspectAgent.has_education && !inspectAgent.has_cv">
                    <p class="rounded-xl border border-dashed border-[#d9e5d5] p-4 text-xs text-[#5e6d64]">No file scans attached — verify NID &amp; BRTA license numbers above or reject with a note requesting document scans.</p>
                </template>
            </div>
        </div>
    </div>

</div>
@endsection

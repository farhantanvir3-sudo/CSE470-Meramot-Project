@extends('layouts.app')

@section('title', 'Apply as a Delivery Agent | Meramot')

{{-- ==========================================================================
     FEATURE R1: Delivery Agent Onboarding & Credential Verification
     Original Author: Md. Nafiul Islam [Student ID: 22299035] (Commit c6c0781)
     Renewed Onboarding Workflow & Personal-Email Credential Delivery:
       - Farhan Tanvir [Student ID: 22299179]
     ========================================================================== --}}

@section('content')
@php
    $submittedPopup = session('application_submitted_popup');
@endphp

<div
    class="mx-auto max-w-3xl px-4 py-8 md:px-6 space-y-6"
    x-data="{ showSubmittedModal: {{ $submittedPopup ? 'true' : 'false' }} }"
>
    <!-- Top Header & Quick Links -->
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <span class="dev-only-badge text-[10px] font-mono px-2.5 py-0.5 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mb-2">
                Feature R1 &bull; Assigned to: Md. Nafiul Islam [22299035]
            </span>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Apply as a Delivery Agent</h1>
            <p class="text-xs md:text-sm text-[var(--muted-foreground)] mt-1">
                Submit your personal email, identity details, and verification documents for Admin review. Upon approval, your unique <code class="font-mono font-bold text-[var(--accent-dark)]">@meramotbd.com</code> company login and password will be emailed directly to your personal inbox.
            </p>
        </div>

        <div class="flex items-center gap-2.5 shrink-0">
            <a
                href="/delivery-agent/status"
                class="group inline-flex items-center gap-2 rounded-full border border-[#c8dac2] bg-white px-4 py-2.5 text-xs font-bold text-[#163625] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#163625] hover:bg-[#eef5ea] hover:shadow-md"
            >
                <i class="fa-solid fa-magnifying-glass-chart text-emerald-800 transition-transform duration-200 group-hover:scale-110"></i>
                <span>Check Application Status</span>
            </a>
            <a
                href="/delivery/login"
                title="Back to Delivery Partner Login"
                aria-label="Back to Delivery Partner Login"
                class="group inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] hover:shadow-[0_8px_20px_-4px_rgba(22,54,37,0.35)]"
            >
                <i class="fa-solid fa-arrow-left text-xs transition-transform duration-200 group-hover:-translate-x-0.5"></i>
            </a>
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-rose-300 bg-rose-50 px-5 py-4 text-xs text-rose-800 space-y-1">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1.5"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <!-- ── Immediate Post-Submission Popup Modal ── -->
    @if($submittedPopup)
        <div
            x-show="showSubmittedModal"
            x-cloak
            class="fixed inset-0 z-[140] flex items-center justify-center p-4"
        >
            <div class="fixed inset-0 bg-black/45 backdrop-blur-sm" @click="showSubmittedModal = false"></div>
            <div class="relative z-[141] w-full max-w-lg rounded-[2rem] border border-[var(--border)] bg-white p-7 shadow-2xl space-y-4">
                <div class="flex items-center gap-3">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-100 text-emerald-800 border border-emerald-300">
                        <i class="fa-solid fa-envelope-circle-check text-xl"></i>
                    </div>
                    <div>
                        <span class="text-[10px] font-black uppercase tracking-wider text-emerald-800">Application Submitted &bull; Pending Review</span>
                        <h2 class="text-xl font-black text-[#163625]">Check Your Personal Email for Updates</h2>
                    </div>
                </div>

                <div class="rounded-2xl border border-[#cbe0c2] bg-[#eef5ea] p-4 text-xs text-[#163625] space-y-2 leading-relaxed">
                    <p>
                        Thank you, <strong>{{ $submittedPopup['name'] ?? 'Applicant' }}</strong>! Your delivery agent application has been queued for Admin verification.
                    </p>
                    <p>
                        All official updates from <strong>meramotbd.official@gmail.com</strong> will be sent to your personal email:
                        <strong class="font-mono underline">{{ $submittedPopup['personal_email'] ?? '' }}</strong>
                    </p>
                    <ul class="list-disc pl-5 space-y-1 text-[#163625]/90">
                        <li><strong>If Accepted:</strong> Your auto-generated unique <code class="font-mono font-bold">@meramotbd.com</code> company email and secure system password will be emailed to your personal email.</li>
                        <li><strong>If Rejected:</strong> The rejection notice along with the Admin's reason will be emailed to you and displayed on the <strong>Check Application Status</strong> page so you can edit and re-submit anytime.</li>
                    </ul>
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-end gap-2.5 pt-2">
                    <a
                        href="/delivery-agent/status"
                        class="w-full sm:w-auto text-center rounded-full border-2 border-[#214c34] bg-[#eef5ea] px-5 py-2.5 text-xs font-bold text-[#214c34] hover:bg-[#dcebd5] transition"
                    >
                        Check Application Status
                    </a>
                    <button
                        type="button"
                        @click="showSubmittedModal = false"
                        class="w-full sm:w-auto rounded-full bg-[#214c34] px-6 py-2.5 text-xs font-bold text-white hover:opacity-95 transition"
                    >
                        Got It
                    </button>
                </div>
            </div>
        </div>
    @endif

    <form method="POST" action="/delivery-agent/register" enctype="multipart/form-data" class="space-y-6 text-xs">
        @csrf

        <!-- Step 1: Personal & Real Email Details -->
        <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3 border-b border-[var(--border)] pb-3">
                <div class="w-8 h-8 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center font-black">
                    1
                </div>
                <div>
                    <h2 class="text-sm font-black text-[var(--foreground)]">Personal Contact &amp; Real Email (Required)</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Crucial: Your approved <code class="font-mono">@meramotbd.com</code> login credentials, OTPs, or rejection notices are delivered to this personal email.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Full Legal Name *</label>
                    <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Kamal Hossain" required
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-[var(--foreground)] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Mobile Phone *</label>
                    <input type="text" name="phone" value="{{ old('phone') }}" placeholder="e.g. +8801711223344" required
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-[var(--foreground)] outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-[var(--foreground)] mb-1">Personal Real Email Address *</label>
                    <input type="email" name="personal_email" value="{{ old('personal_email') }}" placeholder="e.g. kamal.hossain@gmail.com" required
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-[var(--foreground)] outline-none">
                    <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">
                        Use your real personal inbox (Gmail, Outlook, Yahoo, etc.). Do not enter a <code class="font-mono">@meramotbd.com</code> address here.
                    </p>
                </div>
            </div>
        </section>

        <!-- Step 2: Core Identity & Vehicle Credentials (Original Author: Md. Nafiul Islam [22299035]) -->
        <section class="rounded-[2rem] border-2 border-[var(--accent-dark)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3 border-b border-[var(--border)] pb-3">
                <div class="w-8 h-8 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center font-black">
                    2
                </div>
                <div>
                    <h2 class="text-sm font-black text-[var(--foreground)]">Identity &amp; Vehicle Credentials (Required)</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Verified by Delivery Operations Admin prior to company credential generation.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block font-bold text-[var(--foreground)] mb-1">NID Number *</label>
                    <input type="text" name="nid_number" value="{{ old('nid_number') }}" placeholder="Enter 10, 13, or 17 digit National ID number"
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-3 text-[var(--foreground)] outline-none" required>
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Vehicle Type *</label>
                    <select name="vehicle_type"
                            class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-3 font-semibold text-[var(--foreground)] outline-none" required>
                        @php $selectedVehicle = old('vehicle_type', 'motorcycle'); @endphp
                        <option value="motorcycle" {{ $selectedVehicle === 'motorcycle' ? 'selected' : '' }}>Motorcycle</option>
                        <option value="bicycle" {{ $selectedVehicle === 'bicycle' ? 'selected' : '' }}>Bicycle</option>
                        <option value="scooter" {{ $selectedVehicle === 'scooter' ? 'selected' : '' }}>Scooter</option>
                        <option value="van" {{ $selectedVehicle === 'van' ? 'selected' : '' }}>Van</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">License Number *</label>
                    <input type="text" name="license_number" value="{{ old('license_number') }}" placeholder="e.g. DK-0492817-C (or N/A for Bicycle)"
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-3 text-[var(--foreground)] outline-none" required>
                </div>
            </div>
        </section>

        <!-- Step 3: Verification Documents Upload -->
        <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3 border-b border-[var(--border)] pb-3">
                <div class="w-8 h-8 rounded-full bg-[var(--mint-100)] border border-[var(--border)] flex items-center justify-center font-black text-[var(--accent-dark)]">
                    3
                </div>
                <div>
                    <h2 class="text-sm font-black text-[var(--foreground)]">Verification Documents</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Attach clear photos or PDFs (max 4MB each) so the Admin Verification Desk can inspect your credentials.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        <i class="fa-solid fa-camera mr-1.5 text-[var(--accent-dark)]"></i>Profile Picture
                    </label>
                    <input type="file" name="profile_picture" accept="image/*" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        <i class="fa-solid fa-id-card mr-1.5 text-[var(--accent-dark)]"></i>NID Document Scan
                    </label>
                    <input type="file" name="nid_document" accept="image/*,.pdf" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        <i class="fa-solid fa-id-badge mr-1.5 text-[var(--accent-dark)]"></i>Driving License Scan
                    </label>
                    <input type="file" name="license_document" accept="image/*,.pdf" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        <i class="fa-solid fa-graduation-cap mr-1.5 text-[var(--accent-dark)]"></i>Education Certificate
                    </label>
                    <input type="file" name="education_document" accept="image/*,.pdf" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5 sm:col-span-2">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        <i class="fa-solid fa-file-lines mr-1.5 text-[var(--accent-dark)]"></i>CV / Resume Document
                    </label>
                    <input type="file" name="cv_document" accept="image/*,.pdf" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>
            </div>
        </section>

        <div class="rounded-2xl border border-[#c8dac2] bg-gradient-to-r from-[#f8fbf6] via-white to-[#eef5ea] p-4 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <a
                href="/delivery-agent/status"
                class="group flex items-center gap-3 rounded-xl border border-[#d9e5d5] bg-white/90 px-3.5 py-2.5 transition-all duration-200 hover:-translate-y-0.5 hover:border-[#214c34] hover:shadow-sm"
            >
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-[#eef5ea] text-emerald-800 group-hover:bg-[#163625] group-hover:text-[#E4FCD5] transition-colors">
                    <i class="fa-solid fa-shield-halved text-sm"></i>
                </span>
                <span class="text-[11px] text-[var(--muted-foreground)]">
                    Already applied? <strong class="text-[var(--accent-dark)] group-hover:underline">Check Application Status &rarr;</strong>
                </span>
            </a>
            <button type="submit"
                    class="group shrink-0 inline-flex items-center justify-center gap-2 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] px-8 py-3.5 text-xs md:text-sm font-bold shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]">
                <span>Submit Application</span>
                <i class="fa-solid fa-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-1"></i>
            </button>
        </div>
    </form>
</div>
@endsection

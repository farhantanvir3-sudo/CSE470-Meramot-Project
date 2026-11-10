@extends('layouts.app')

@section('title', 'Apply as Delivery Agent | Meramot')

{{-- ==========================================================================
     FEATURE R1: Delivery Agent Onboarding & Credential Verification
     Original Author: Md. Nafiul Islam [Student ID: 22299035] (Commit c6c0781)
     Relocated From: backend/resources/views/delivery/register.blade.php
     Relocated To:   frontend/views/delivery/register.blade.php
     Merged with Original Meramot 3-Step Partner Onboarding (`DeliverySignupForm`
     & `DeliveryRegistration`): Farhan Tanvir [Student ID: 22299179]
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-3xl px-4 py-8 md:px-6 space-y-6">

    <!-- Header -->
    <div>
        <span class="dev-only-badge text-[10px] font-mono px-2.5 py-0.5 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mb-2">
            Feature R1 • Assigned to: Md. Nafiul Islam [22299035]
        </span>
        <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Apply as a Delivery Agent</h1>
        <p class="text-xs md:text-sm text-[var(--muted-foreground)] mt-1">
            Submit your profile, NID, and BRTA driving license credentials for approval. Once approved, your rider dispatch account is activated automatically.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-600/40 bg-[var(--mint-100)] px-4 py-3 text-xs font-bold text-[var(--accent-dark)] flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(isset($existingApplication) && $existingApplication)
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] p-4 text-xs space-y-2">
            <div class="flex items-center justify-between">
                <span class="font-bold text-[var(--foreground)]">Current Application Status:</span>
                <span class="px-2.5 py-0.5 rounded-full font-bold text-[11px]
                    {{ $existingApplication->registration_status === 'APPROVED' ? 'bg-emerald-100 text-emerald-800 border border-emerald-300' : ($existingApplication->registration_status === 'REJECTED' ? 'bg-rose-100 text-rose-800 border border-rose-300' : 'bg-amber-100 text-amber-800 border border-amber-300') }}">
                    {{ $existingApplication->registration_status }}
                </span>
            </div>
            @if($existingApplication->registration_status === 'REJECTED' && $existingApplication->rejection_reason)
                <div class="rounded-xl bg-rose-50 border border-rose-200 px-3 py-2 text-rose-900">
                    <span class="font-bold">Admin Rejection Reason:</span> {{ $existingApplication->rejection_reason }}
                </div>
            @endif
        </div>
    @endif

    <form method="POST" action="/delivery-agent/register" enctype="multipart/form-data" class="space-y-6 text-xs">
        @csrf

        <!-- Step 1: Personal Details (Merged from Original Meramot Partner Onboarding) -->
        <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3 border-b border-[var(--border)] pb-3">
                <div class="w-8 h-8 rounded-full bg-[var(--mint-100)] border border-[var(--border)] flex items-center justify-center font-black text-[var(--accent-dark)]">
                    1
                </div>
                <div>
                    <h2 class="text-sm font-black text-[var(--foreground)]">Personal & Contact Information</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Used for rider dispatch alerts and credential delivery.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Full Legal Name</label>
                    <input type="text" name="name" value="{{ old('name', auth()->user()->name ?? '') }}" placeholder="e.g. Kamal Hossain"
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-[var(--foreground)] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Mobile Phone</label>
                    <input type="text" name="phone" value="{{ old('phone', auth()->user()->phone ?? '') }}" placeholder="e.g. +8801711223344"
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-[var(--foreground)] outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-[var(--foreground)] mb-1">Email Address</label>
                    <input type="email" name="email" value="{{ old('email', auth()->user()->email ?? '') }}" placeholder="e.g. rider@meramot.demo"
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-[var(--foreground)] outline-none">
                </div>
            </div>
        </section>

        {{-- ==================================================================
             RELOCATED BLOCK: Core Rider Credentials (`nid_number`, `vehicle_type`, `license_number`)
             Original Author: Md. Nafiul Islam [22299035] (Commit c6c0781)
             ================================================================== --}}
        <section class="rounded-[2rem] border-2 border-[var(--accent-dark)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3 border-b border-[var(--border)] pb-3">
                <div class="w-8 h-8 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center font-black">
                    2
                </div>
                <div>
                    <h2 class="text-sm font-black text-[var(--foreground)]">Identity & Vehicle Credentials (Required)</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Verified by Delivery Operations Admin prior to account activation.</p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block font-bold text-[var(--foreground)] mb-1">NID Number *</label>
                    <input type="text" name="nid_number" value="{{ old('nid_number', $existingApplication->nid_number ?? '') }}" placeholder="Enter 10 or 13 digit National ID number"
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-3 text-[var(--foreground)] outline-none" required>
                    @error('nid_number') <p class="text-xs font-bold text-rose-700 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Vehicle Type *</label>
                    <select name="vehicle_type"
                            class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-3 font-semibold text-[var(--foreground)] outline-none" required>
                        @php $selectedVehicle = old('vehicle_type', $existingApplication->vehicle_type ?? 'motorcycle'); @endphp
                        <option value="motorcycle" {{ $selectedVehicle === 'motorcycle' ? 'selected' : '' }}>Motorcycle</option>
                        <option value="bicycle" {{ $selectedVehicle === 'bicycle' ? 'selected' : '' }}>Bicycle</option>
                        <option value="scooter" {{ $selectedVehicle === 'scooter' ? 'selected' : '' }}>Scooter</option>
                        <option value="van" {{ $selectedVehicle === 'van' ? 'selected' : '' }}>Van</option>
                    </select>
                    @error('vehicle_type') <p class="text-xs font-bold text-rose-700 mt-1">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">License Number *</label>
                    <input type="text" name="license_number" value="{{ old('license_number', $existingApplication->license_number ?? '') }}" placeholder="e.g. DK-0492817-C"
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-3 text-[var(--foreground)] outline-none" required>
                    @error('license_number') <p class="text-xs font-bold text-rose-700 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>
        </section>

        <!-- Step 3: Verification Documents Upload (Merged from Original Meramot `DeliverySignupForm`) -->
        <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3 border-b border-[var(--border)] pb-3">
                <div class="w-8 h-8 rounded-full bg-[var(--mint-100)] border border-[var(--border)] flex items-center justify-center font-black text-[var(--accent-dark)]">
                    3
                </div>
                <div>
                    <h2 class="text-sm font-black text-[var(--foreground)]">Verification Documents (Optional Uploads)</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Attach scans/photos of your Profile Picture, NID, Education Transcript, and CV for faster verification.</p>
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
                        <i class="fa-solid fa-graduation-cap mr-1.5 text-[var(--accent-dark)]"></i>Education Certificate
                    </label>
                    <input type="file" name="education_document" accept="image/*,.pdf" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        <i class="fa-solid fa-file-lines mr-1.5 text-[var(--accent-dark)]"></i>CV / Resume Document
                    </label>
                    <input type="file" name="cv_document" accept="image/*,.pdf" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>
            </div>
        </section>

        <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/70 p-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-shield-halved text-lg text-emerald-700"></i>
                <p class="text-[11px] text-[var(--muted-foreground)]">
                    Your identity documents are encrypted and reviewed strictly by Meramot Delivery Operations.
                </p>
            </div>
            <button type="submit"
                    class="shrink-0 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] px-6 py-3 text-xs font-bold shadow hover:opacity-90 transition">
                Submit Application &rarr;
            </button>
        </div>
    </form>
</div>
@endsection

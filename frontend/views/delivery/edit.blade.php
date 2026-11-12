@extends('layouts.app')

@section('title', 'Edit Delivery Agent Application | Meramot')

@section('content')
@php
    $applicantName = $agent->applicant_name ?: ($agent->user?->name ?? '');
    $applicantPhone = $agent->applicant_phone ?: ($agent->user?->phone ?? '');
    $isRejected = strtoupper((string) $agent->registration_status) === 'REJECTED';
@endphp

<div class="mx-auto max-w-3xl px-4 py-8 md:px-6 space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-0.5 text-[11px] font-bold text-[var(--accent-dark)] mb-2">
                <i class="fa-solid fa-pen-to-square"></i> Verified Edit Mode &bull; {{ $agent->personal_email }}
            </span>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Edit Delivery Agent Application</h1>
            <p class="text-xs md:text-sm text-[var(--muted-foreground)] mt-1">
                You may update your information and replace any verification documents as many times as needed prior to approval.
            </p>
        </div>
        <a href="/delivery-agent/status" class="self-start rounded-full border border-[var(--border)] bg-white px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)] transition">
            &larr; Back to Status
        </a>
    </div>

    @if($isRejected)
        <div class="rounded-2xl border-2 border-rose-300 bg-rose-50 p-5 text-xs space-y-1.5">
            <h3 class="font-black text-sm text-rose-900 flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-rose-700"></i>
                <span>Previous Rejection Reason from Admin</span>
            </h3>
            <p class="font-semibold text-rose-950">{{ $agent->rejection_reason ?: 'Please update your credentials and re-submit.' }}</p>
            <p class="text-rose-800">Saving your changes below will automatically move your application back to <strong>PENDING</strong> status for Admin review.</p>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-300 bg-rose-50 px-5 py-4 text-xs text-rose-800 space-y-1">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1.5"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="/delivery-agent/edit" enctype="multipart/form-data" class="space-y-6 text-xs">
        @csrf

        <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black text-[var(--foreground)] border-b border-[var(--border)] pb-2.5">
                1. Personal &amp; Contact Information
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Full Legal Name *</label>
                    <input type="text" name="name" value="{{ old('name', $applicantName) }}" required
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-[var(--foreground)] outline-none">
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Mobile Phone *</label>
                    <input type="text" name="phone" value="{{ old('phone', $applicantPhone) }}" required
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-[var(--foreground)] outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold text-[var(--foreground)] mb-1">Verified Personal Email (Locked)</label>
                    <input type="email" value="{{ $agent->personal_email }}" disabled
                           class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 font-mono text-[var(--muted-foreground)] cursor-not-allowed">
                </div>
            </div>
        </section>

        <section class="rounded-[2rem] border-2 border-[var(--accent-dark)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <h2 class="text-sm font-black text-[var(--foreground)] border-b border-[var(--border)] pb-2.5">
                2. Identity &amp; Vehicle Credentials
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="sm:col-span-2">
                    <label class="block font-bold text-[var(--foreground)] mb-1">NID Number *</label>
                    <input type="text" name="nid_number" value="{{ old('nid_number', $agent->nid_number) }}" required
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-3 text-[var(--foreground)] outline-none">
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Vehicle Type *</label>
                    <select name="vehicle_type"
                            class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-3 font-semibold text-[var(--foreground)] outline-none" required>
                        @php $selectedVehicle = old('vehicle_type', $agent->vehicle_type ?? 'motorcycle'); @endphp
                        <option value="motorcycle" {{ $selectedVehicle === 'motorcycle' ? 'selected' : '' }}>Motorcycle</option>
                        <option value="bicycle" {{ $selectedVehicle === 'bicycle' ? 'selected' : '' }}>Bicycle</option>
                        <option value="scooter" {{ $selectedVehicle === 'scooter' ? 'selected' : '' }}>Scooter</option>
                        <option value="van" {{ $selectedVehicle === 'van' ? 'selected' : '' }}>Van</option>
                    </select>
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">License Number *</label>
                    <input type="text" name="license_number" value="{{ old('license_number', $agent->license_number) }}" required
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-3 text-[var(--foreground)] outline-none">
                </div>
            </div>
        </section>

        <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <div>
                <h2 class="text-sm font-black text-[var(--foreground)]">3. Replace or Attach Verification Documents</h2>
                <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">Leave a file field blank to keep your previously uploaded document.</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        Profile Picture @if($agent->profile_picture_url) <span class="text-emerald-700 font-semibold">(Uploaded ✓)</span> @endif
                    </label>
                    <input type="file" name="profile_picture" accept="image/*" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        NID Document Scan @if($agent->nid_document_url) <span class="text-emerald-700 font-semibold">(Uploaded ✓)</span> @endif
                    </label>
                    <input type="file" name="nid_document" accept="image/*,.pdf" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        Driving License Scan @if($agent->license_document_url) <span class="text-emerald-700 font-semibold">(Uploaded ✓)</span> @endif
                    </label>
                    <input type="file" name="license_document" accept="image/*,.pdf" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        Education Certificate @if($agent->education_document_url) <span class="text-emerald-700 font-semibold">(Uploaded ✓)</span> @endif
                    </label>
                    <input type="file" name="education_document" accept="image/*,.pdf" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>

                <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3.5 sm:col-span-2">
                    <label class="block font-bold text-[var(--foreground)] mb-1">
                        CV / Resume Document @if($agent->cv_document_url) <span class="text-emerald-700 font-semibold">(Uploaded ✓)</span> @endif
                    </label>
                    <input type="file" name="cv_document" accept="image/*,.pdf" class="w-full text-[11px] text-[var(--muted-foreground)]">
                </div>
            </div>
        </section>

        <div class="flex flex-col sm:flex-row items-center justify-end gap-3">
            <a href="/delivery-agent/status" class="w-full sm:w-auto text-center rounded-full border border-[var(--border)] bg-white px-6 py-3 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                Cancel
            </a>
            <button type="submit" class="w-full sm:w-auto rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] px-7 py-3 text-xs font-bold shadow hover:opacity-90 transition">
                {{ $isRejected ? 'Save & Re-Submit for Admin Approval (PENDING)' : 'Save Updated Application' }} &rarr;
            </button>
        </div>
    </form>
</div>
@endsection

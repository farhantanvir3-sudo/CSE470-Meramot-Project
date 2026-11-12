@extends('layouts.app')

@section('title', 'Edit Delivery Agent Application | Meramot')

@section('content')
@php
    $applicantName = $agent->applicant_name ?: ($agent->user?->name ?? '');
    $applicantPhone = $agent->applicant_phone ?: ($agent->user?->phone ?? '');
    $isRejected = strtoupper((string) $agent->registration_status) === 'REJECTED';
@endphp

<div class="mx-auto max-w-3xl px-4 py-8 md:px-6 space-y-6">
    <div class="flex items-start justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-0.5 text-[11px] font-bold text-[var(--accent-dark)] mb-2 shadow-sm">
                <i class="fa-solid fa-pen-to-square"></i> Verified Edit Mode &bull; {{ $agent->personal_email }}
            </span>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Edit Delivery Agent Application</h1>
            <p class="text-xs md:text-sm text-[var(--muted-foreground)] mt-1">
                You may update your information and replace any verification documents as many times as needed prior to approval.
            </p>
        </div>
        <a
            href="/delivery-agent/status"
            title="Back to Application Status"
            aria-label="Back to Application Status"
            class="group mt-1 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] hover:shadow-[0_8px_20px_-4px_rgba(22,54,37,0.35)]"
        >
            <i class="fa-solid fa-arrow-left text-sm transition-transform duration-200 group-hover:-translate-x-0.5"></i>
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
                <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">Preview your existing scans or drag &amp; drop a new file to replace them. Leave untouched to keep your previously uploaded document.</p>
            </div>

            @php
                $editUploadCards = [
                    [
                        'name' => 'profile_picture',
                        'label' => 'Profile Picture',
                        'icon' => 'fa-camera',
                        'accept' => 'image/jpeg,image/jpg,image/png,image/webp',
                        'hint' => 'JPG, PNG, WebP • Max 4MB',
                        'span' => '',
                        'existingUrl' => $agent->profile_picture_url ?: ($agent->user?->avatar_url ?? ''),
                    ],
                    [
                        'name' => 'nid_document',
                        'label' => 'NID Document Scan',
                        'icon' => 'fa-id-card',
                        'accept' => 'image/jpeg,image/jpg,image/png,image/webp,application/pdf,.pdf',
                        'hint' => 'Image or PDF Scan • Max 4MB',
                        'span' => '',
                        'existingUrl' => $agent->nid_document_url ?? '',
                    ],
                    [
                        'name' => 'license_document',
                        'label' => 'Driving License Scan',
                        'icon' => 'fa-id-badge',
                        'accept' => 'image/jpeg,image/jpg,image/png,image/webp,application/pdf,.pdf',
                        'hint' => 'BRTA License Image or PDF • Max 4MB',
                        'span' => '',
                        'existingUrl' => $agent->license_document_url ?? '',
                    ],
                    [
                        'name' => 'education_document',
                        'label' => 'Education Certificate',
                        'icon' => 'fa-graduation-cap',
                        'accept' => 'image/jpeg,image/jpg,image/png,image/webp,application/pdf,.pdf',
                        'hint' => 'Certificate Image or PDF • Max 4MB',
                        'span' => '',
                        'existingUrl' => $agent->education_document_url ?? '',
                    ],
                    [
                        'name' => 'cv_document',
                        'label' => 'CV / Resume Document',
                        'icon' => 'fa-file-lines',
                        'accept' => 'image/jpeg,image/jpg,image/png,image/webp,application/pdf,.pdf',
                        'hint' => 'PDF or Image • Max 4MB',
                        'span' => 'sm:col-span-2',
                        'existingUrl' => $agent->cv_document_url ?? '',
                    ],
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($editUploadCards as $card)
                    @php
                        $existingRaw = (string) ($card['existingUrl'] ?? '');
                        $hasExisting = $existingRaw !== '';
                        $existingIsPdf = str_starts_with($existingRaw, 'data:application/pdf') || str_ends_with(strtolower($existingRaw), '.pdf');
                    @endphp
                    <div
                        class="{{ $card['span'] }}"
                        x-data="{
                            existingUrl: @js($existingRaw),
                            existingIsPdf: {{ $existingIsPdf ? 'true' : 'false' }},
                            fileName: '',
                            fileSize: '',
                            fileType: '',
                            previewUrl: '',
                            isPdf: false,
                            dragging: false,
                            handleFile(file) {
                                if (!file) return;
                                if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                                this.fileName = file.name;
                                const kb = file.size / 1024;
                                this.fileSize = kb >= 1024 ? (kb / 1024).toFixed(2) + ' MB' : Math.round(kb) + ' KB';
                                this.isPdf = file.type === 'application/pdf' || file.name.toLowerCase().endsWith('.pdf');
                                this.fileType = this.isPdf ? 'PDF' : ((file.type.split('/')[1] || 'IMAGE').toUpperCase());
                                this.previewUrl = URL.createObjectURL(file);
                            },
                            onInputChange(e) {
                                const file = e.target.files?.[0];
                                if (file) this.handleFile(file);
                            },
                            onDrop(e) {
                                this.dragging = false;
                                const file = e.dataTransfer?.files?.[0];
                                if (!file) return;
                                const dt = new DataTransfer();
                                dt.items.add(file);
                                this.$refs.fileInput.files = dt.files;
                                this.handleFile(file);
                            },
                            clearNewFile() {
                                if (this.previewUrl) URL.revokeObjectURL(this.previewUrl);
                                this.fileName = '';
                                this.fileSize = '';
                                this.fileType = '';
                                this.previewUrl = '';
                                this.isPdf = false;
                                this.$refs.fileInput.value = '';
                            }
                        }"
                    >
                        <div class="mb-1.5 flex items-center justify-between">
                            <label class="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                                <i class="fa-solid {{ $card['icon'] }} text-[var(--accent-dark)]"></i>
                                <span>{{ $card['label'] }}</span>
                            </label>
                            <template x-if="previewUrl">
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-800">
                                    <i class="fa-solid fa-sparkles"></i> New File Selected
                                </span>
                            </template>
                            <template x-if="!previewUrl && existingUrl">
                                <span class="inline-flex items-center gap-1 rounded-full bg-[#e4f3df] border border-[#c8dac2] px-2.5 py-0.5 text-[10px] font-extrabold text-[#163625]">
                                    <i class="fa-solid fa-circle-check text-emerald-700"></i> Uploaded ✓
                                </span>
                            </template>
                        </div>

                        <div
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="onDrop($event)"
                            :class="dragging ? 'border-[#163625] bg-[#e4f3df] scale-[1.01]' : ((previewUrl || existingUrl) ? 'border-emerald-600/60 bg-white' : 'border-[#b9cfb2] bg-[var(--mint-100)]/60 hover:border-[#163625] hover:bg-white/80')"
                            class="relative overflow-hidden rounded-2xl border-2 border-dashed p-3.5 transition-all duration-200 shadow-sm"
                        >
                            <input
                                x-ref="fileInput"
                                type="file"
                                name="{{ $card['name'] }}"
                                accept="{{ $card['accept'] }}"
                                class="hidden"
                                @change="onInputChange($event)"
                            >

                            <!-- State 1: New File Selected -->
                            <template x-if="previewUrl">
                                <div class="relative space-y-2.5">
                                    <button
                                        type="button"
                                        @click="clearNewFile()"
                                        title="Revert new file"
                                        class="absolute right-1.5 top-1.5 z-10 inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-600 text-xs font-bold text-white shadow-md transition hover:scale-105 hover:bg-rose-700"
                                    >
                                        ✕
                                    </button>

                                    <template x-if="!isPdf">
                                        <div class="flex items-center justify-center overflow-hidden rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] p-2">
                                            <img :src="previewUrl" :alt="fileName" class="max-h-36 w-auto rounded-lg object-contain shadow-sm">
                                        </div>
                                    </template>

                                    <template x-if="isPdf">
                                        <div class="flex items-center gap-3 rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] p-3.5">
                                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                                                <i class="fa-solid fa-file-pdf text-2xl"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-xs font-extrabold text-[#163625]" x-text="fileName"></p>
                                                <a :href="previewUrl" target="_blank" class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 hover:underline">
                                                    <span>Preview new PDF</span>
                                                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </template>

                                    <div class="flex items-center justify-between gap-2 pt-0.5 text-[11px]">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="shrink-0 rounded-md bg-emerald-100 px-2 py-0.5 text-[10px] font-extrabold text-emerald-900" x-text="fileType"></span>
                                            <span class="truncate font-semibold text-[#163625]" x-text="fileName"></span>
                                            <span class="shrink-0 text-[10px] text-[#5e6d64]" x-text="'(' + fileSize + ')'"></span>
                                        </div>
                                        <button
                                            type="button"
                                            @click="$refs.fileInput.click()"
                                            class="shrink-0 rounded-full border border-[#c8dac2] bg-[#eef5ea] px-2.5 py-1 text-[10px] font-bold text-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] transition"
                                        >
                                            Change
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- State 2: Existing Uploaded Document Preview -->
                            <template x-if="!previewUrl && existingUrl">
                                <div class="space-y-2.5">
                                    <template x-if="!existingIsPdf">
                                        <div class="flex items-center justify-center overflow-hidden rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] p-2">
                                            <img :src="existingUrl" alt="{{ $card['label'] }}" class="max-h-36 w-auto rounded-lg object-contain shadow-sm">
                                        </div>
                                    </template>

                                    <template x-if="existingIsPdf">
                                        <div class="flex items-center gap-3 rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] p-3.5">
                                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                                                <i class="fa-solid fa-file-pdf text-2xl"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-extrabold text-[#163625]">Saved PDF Document</p>
                                                <p class="text-[10px] text-[#5e6d64]">Currently attached to your application</p>
                                            </div>
                                        </div>
                                    </template>

                                    <div class="flex items-center justify-between gap-2 pt-0.5 text-[11px]">
                                        <span class="text-[11px] font-semibold text-emerald-800">
                                            <i class="fa-solid fa-check-double mr-1"></i>Saved on file
                                        </span>
                                        <button
                                            type="button"
                                            @click="$refs.fileInput.click()"
                                            class="rounded-full border border-[#c8dac2] bg-[#eef5ea] px-3 py-1 text-[10px] font-bold text-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] transition"
                                        >
                                            <i class="fa-solid fa-upload mr-1"></i>Replace File
                                        </button>
                                    </div>
                                </div>
                            </template>

                            <!-- State 3: Empty Dropzone -->
                            <template x-if="!previewUrl && !existingUrl">
                                <div
                                    @click="$refs.fileInput.click()"
                                    class="group flex cursor-pointer flex-col items-center justify-center py-4 text-center"
                                >
                                    <div class="mb-2 flex h-11 w-11 items-center justify-center rounded-2xl border border-[#c8dac2] bg-white text-[#163625] shadow-sm transition-all duration-200 group-hover:scale-105 group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                                        <i class="fa-solid fa-cloud-arrow-up text-base"></i>
                                    </div>
                                    <p class="text-xs font-extrabold text-[var(--foreground)]">
                                        Click to upload <span class="font-normal text-[var(--muted-foreground)]">or drag &amp; drop</span>
                                    </p>
                                    <p class="mt-0.5 text-[10px] text-[var(--muted-foreground)]">{{ $card['hint'] }}</p>
                                </div>
                            </template>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="flex flex-col sm:flex-row items-center justify-end gap-3">
            <a href="/delivery-agent/status" class="w-full sm:w-auto text-center rounded-full border border-[#c8dac2] bg-white px-6 py-3 text-xs font-bold text-[var(--foreground)] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#163625] hover:bg-[var(--mint-100)] hover:shadow">
                Cancel
            </a>
            <button type="submit" class="group w-full sm:w-auto inline-flex items-center justify-center gap-2 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] px-7 py-3.5 text-xs font-bold shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]">
                <span>{{ $isRejected ? 'Save & Re-Submit for Admin Approval (PENDING)' : 'Save Updated Application' }}</span>
                <i class="fa-solid fa-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-1"></i>
            </button>
        </div>
    </form>
</div>
@endsection

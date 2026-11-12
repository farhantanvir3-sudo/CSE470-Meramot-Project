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
    $isSignedInRider = auth()->check() && in_array(auth()->user()->role, ['delivery_agent', 'delivery'], true);
@endphp

<div
    class="mx-auto max-w-3xl px-4 py-8 md:px-6 space-y-6"
    x-data="{ showSubmittedModal: {{ $submittedPopup ? 'true' : 'false' }} }"
>
    @if($isSignedInRider)
        <div class="rounded-2xl border border-emerald-300 bg-emerald-50/95 px-5 py-3.5 text-xs text-emerald-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
            <span class="font-semibold">
                <i class="fa-solid fa-motorcycle mr-1.5 text-emerald-700"></i>
                You already have an active Delivery Partner session. Open your Rider Dashboard directly anytime.
            </span>
            <a href="/delivery" class="shrink-0 rounded-full bg-[#163625] px-4 py-2 text-xs font-bold text-white shadow hover:opacity-90 transition text-center">
                Go to Rider Dashboard &rarr;
            </a>
        </div>
    @endif

    <!-- Top Header & Quick Links -->
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
        <div>
            <span class="dev-only-badge text-[10px] font-mono px-2.5 py-0.5 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block mb-2">
                Feature R1 &bull; Assigned to: Md. Nafiul Islam [22299035]
            </span>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Apply as a Delivery Agent</h1>
            <p class="text-xs md:text-sm text-[var(--muted-foreground)] mt-1">
                Submit your personal email, identity details, and verification documents for Admin review. Upon approval, your unique <code class="font-mono font-bold text-[var(--accent-dark)]">@meramotbd.com</code> company email and password will be emailed directly to your personal inbox.
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
                href="{{ $isSignedInRider ? '/delivery' : '/delivery/login' }}"
                title="{{ $isSignedInRider ? 'Back to Rider Dashboard' : 'Back to Delivery Partner Login' }}"
                aria-label="{{ $isSignedInRider ? 'Back to Rider Dashboard' : 'Back to Delivery Partner Login' }}"
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

        <!-- Step 1: Personal & Email Details -->
        <section class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
            <div class="flex items-center gap-3 border-b border-[var(--border)] pb-3">
                <div class="w-8 h-8 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center font-black">
                    1
                </div>
                <div>
                    <h2 class="text-sm font-black text-[var(--foreground)]">Personal Contact &amp; Email (Required)</h2>
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
                    <label class="block font-bold text-[var(--foreground)] mb-1">Personal Email Address *</label>
                    <input type="email" name="personal_email" value="{{ old('personal_email') }}" placeholder="e.g. kamal.hossain@gmail.com" required
                           class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-2.5 text-[var(--foreground)] outline-none">
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
                    <p class="text-[11px] text-[var(--muted-foreground)]">Click or drag &amp; drop clear photos or PDFs (up to 15MB each) with live thumbnail preview so the Admin Verification Desk can inspect your credentials.</p>
                </div>
            </div>

            @php
                $uploadCards = [
                    [
                        'name' => 'profile_picture',
                        'label' => 'Profile Picture',
                        'icon' => 'fa-camera',
                        'accept' => 'image/*,.jpg,.jpeg,.jfif,.png,.webp',
                        'hint' => 'JPG, JPEG, PNG, WebP • Max 15MB',
                        'span' => '',
                    ],
                    [
                        'name' => 'nid_document',
                        'label' => 'NID Document Scan',
                        'icon' => 'fa-id-card',
                        'accept' => 'image/*,.jpg,.jpeg,.jfif,.png,.webp,application/pdf,.pdf',
                        'hint' => 'Image or PDF Scan • Max 15MB',
                        'span' => '',
                    ],
                    [
                        'name' => 'license_document',
                        'label' => 'Driving License Scan',
                        'icon' => 'fa-id-badge',
                        'accept' => 'image/*,.jpg,.jpeg,.jfif,.png,.webp,application/pdf,.pdf',
                        'hint' => 'BRTA License Image or PDF • Max 15MB',
                        'span' => '',
                    ],
                    [
                        'name' => 'education_document',
                        'label' => 'Education Certificate',
                        'icon' => 'fa-graduation-cap',
                        'accept' => 'image/*,.jpg,.jpeg,.jfif,.png,.webp,application/pdf,.pdf',
                        'hint' => 'SSC/HSC Certificate Image or PDF • Max 15MB',
                        'span' => '',
                    ],
                    [
                        'name' => 'cv_document',
                        'label' => 'CV / Resume Document',
                        'icon' => 'fa-file-lines',
                        'accept' => 'image/*,.jpg,.jpeg,.jfif,.png,.webp,application/pdf,.pdf',
                        'hint' => 'PDF or Image • Max 15MB',
                        'span' => 'sm:col-span-2',
                    ],
                ];
            @endphp

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                @foreach($uploadCards as $card)
                    <div
                        class="{{ $card['span'] }}"
                        x-data="{
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
                            clearFile() {
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
                            <template x-if="fileName">
                                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-extrabold text-emerald-800">
                                    <i class="fa-solid fa-circle-check"></i> Ready
                                </span>
                            </template>
                        </div>

                        <div
                            @dragover.prevent="dragging = true"
                            @dragleave.prevent="dragging = false"
                            @drop.prevent="onDrop($event)"
                            :class="dragging ? 'border-[#163625] bg-[#e4f3df] scale-[1.01]' : (previewUrl ? 'border-emerald-600/60 bg-white' : 'border-[#b9cfb2] bg-[var(--mint-100)]/60 hover:border-[#163625] hover:bg-white/80')"
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

                            <!-- Empty Dropzone State -->
                            <template x-if="!previewUrl">
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

                            <!-- Active Preview State -->
                            <template x-if="previewUrl">
                                <div class="relative space-y-2.5">
                                    <!-- Top-right ✕ Remove Button -->
                                    <button
                                        type="button"
                                        @click="clearFile()"
                                        title="Remove file"
                                        class="absolute right-1.5 top-1.5 z-10 inline-flex h-7 w-7 items-center justify-center rounded-full bg-rose-600 text-xs font-bold text-white shadow-md transition hover:scale-105 hover:bg-rose-700"
                                    >
                                        ✕
                                    </button>

                                    <!-- Image Thumbnail Preview -->
                                    <template x-if="!isPdf">
                                        <div class="flex items-center justify-center overflow-hidden rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] p-2">
                                            <img :src="previewUrl" :alt="fileName" class="max-h-36 w-auto rounded-lg object-contain shadow-sm">
                                        </div>
                                    </template>

                                    <!-- PDF Document Card Preview -->
                                    <template x-if="isPdf">
                                        <div class="flex items-center gap-3 rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] p-3.5">
                                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-rose-100 text-rose-700">
                                                <i class="fa-solid fa-file-pdf text-2xl"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="truncate text-xs font-extrabold text-[#163625]" x-text="fileName"></p>
                                                <a :href="previewUrl" target="_blank" class="mt-0.5 inline-flex items-center gap-1 text-[11px] font-bold text-emerald-800 hover:underline">
                                                    <span>Preview PDF in new tab</span>
                                                    <i class="fa-solid fa-arrow-up-right-from-square text-[9px]"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </template>

                                    <!-- File Info Footer + Replace Action -->
                                    <div class="flex items-center justify-between gap-2 pt-0.5 text-[11px]">
                                        <div class="flex items-center gap-1.5 min-w-0">
                                            <span class="shrink-0 rounded-md bg-[#eef5ea] border border-[#c8dac2] px-2 py-0.5 text-[10px] font-extrabold text-[#163625]" x-text="fileType"></span>
                                            <span class="truncate font-semibold text-[#163625]" x-text="fileName"></span>
                                            <span class="shrink-0 text-[10px] text-[#5e6d64]" x-text="'(' + fileSize + ')'"></span>
                                        </div>
                                        <button
                                            type="button"
                                            @click="$refs.fileInput.click()"
                                            class="shrink-0 rounded-full border border-[#c8dac2] bg-[#eef5ea] px-2.5 py-1 text-[10px] font-bold text-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] transition"
                                        >
                                            Replace
                                        </button>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </div>
                @endforeach
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

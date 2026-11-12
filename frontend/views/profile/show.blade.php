@extends('layouts.app')

@section('title', 'My Profile | Meramot')

@section('content')
<div
    class="mx-auto max-w-4xl px-4 py-8 md:px-6"
    x-data="{
        editing: {{ $errors->any() || request()->boolean('edit') ? 'true' : 'false' }},
        showDeleteConfirm: false,
        deleting: false,
        addressInputMode: @js(($user->latitude && $user->longitude) ? 'MAP' : 'MANUAL'),
        addressConfirmed: {{ !empty($user->address) ? 'true' : 'false' }},
        manualConfirmError: '',
        avatarPreview: @js($user->avatar_url ?? ''),
        draft: {
            name: @js(old('name', $user->name ?? '')),
            phone: @js(old('phone', $user->phone ?? '')),
            address: @js(old('address', $user->address ?? '')),
            city: @js(old('city', $user->city ?? 'Dhaka')),
            area: @js(old('area', $user->area ?? '')),
            latitude: @js(old('latitude', $user->latitude ?? '')),
            longitude: @js(old('longitude', $user->longitude ?? ''))
        },
        otpModalOpen: false,
        otpChannel: 'email',
        otpCode: '',
        otpSending: false,
        otpVerifying: false,
        otpStepSent: false,
        otpFeedback: '',
        otpError: '',
        handleAvatarFile(event) {
            const file = event.target.files?.[0];
            if (!file) return;
            this.avatarPreview = URL.createObjectURL(file);
        },
        applyMapLocation(loc) {
            if (!loc) return;
            this.editing = true;
            this.addressInputMode = 'MAP';
            this.draft.address = loc.address || this.draft.address;
            this.draft.city = loc.city || this.draft.city || 'Dhaka';
            this.draft.area = loc.area || this.draft.area || '';
            this.draft.latitude = typeof loc.lat === 'number' ? loc.lat.toFixed(6) : '';
            this.draft.longitude = typeof loc.lng === 'number' ? loc.lng.toFixed(6) : '';
            this.addressConfirmed = Boolean(this.draft.address);
            this.manualConfirmError = '';
        },
        confirmManualAddress() {
            this.manualConfirmError = '';
            if (!this.draft.address || !this.draft.address.trim()) {
                this.manualConfirmError = 'Please type your street / house / road delivery address first.';
                return;
            }
            if (!this.draft.city || !this.draft.city.trim()) {
                this.draft.city = 'Dhaka';
            }
            this.addressInputMode = 'MANUAL';
            this.draft.latitude = '';
            this.draft.longitude = '';
            this.addressConfirmed = true;
            window.dispatchEvent(new CustomEvent('meramot-toast', {
                detail: { message: 'Manual delivery address confirmed! Click Save changes to store it.', type: 'success' }
            }));
        },
        openOtpModal(channel) {
            this.otpChannel = channel;
            this.otpCode = '';
            this.otpStepSent = false;
            this.otpFeedback = '';
            this.otpError = '';
            this.otpModalOpen = true;
        },
        async sendOtpNow() {
            this.otpSending = true;
            this.otpError = '';
            this.otpFeedback = '';
            try {
                const resp = await fetch('/api/verification/send-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ channel: this.otpChannel })
                });
                const data = await resp.json();
                if (!resp.ok) throw new Error(data.message || 'Could not send OTP.');
                this.otpFeedback = data.message || 'Verification code sent!';
                this.otpStepSent = true;
            } catch (err) {
                this.otpError = err.message || 'Failed to send verification code.';
            } finally {
                this.otpSending = false;
            }
        },
        async verifyOtpNow() {
            if (!this.otpCode.trim()) {
                this.otpError = 'Please enter the 6-digit OTP code.';
                return;
            }
            this.otpVerifying = true;
            this.otpError = '';
            this.otpFeedback = '';
            try {
                const resp = await fetch('/api/verification/verify-otp', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                    },
                    body: JSON.stringify({ channel: this.otpChannel, otp: this.otpCode.trim() })
                });
                const data = await resp.json();
                if (!resp.ok) throw new Error(data.message || 'Invalid OTP code.');
                this.otpFeedback = data.message || 'Verified!';
                setTimeout(() => window.location.reload(), 700);
            } catch (err) {
                this.otpError = err.message || 'OTP verification failed.';
            } finally {
                this.otpVerifying = false;
            }
        }
    }"
    @meramot-location-changed.window="applyMapLocation($event.detail)"
>
    <!-- Top Header Row -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
            <a href="/" class="hover:underline">Home</a>
            <span>/</span>
            <span class="font-semibold text-[var(--foreground)]">Account Profile</span>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            @if(($user->role ?? '') === 'admin')
                <a
                    href="/admin"
                    class="rounded-full bg-[var(--accent-dark)] px-5 py-2 text-xs md:text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                >
                    <i class="fa-solid fa-shield-halved mr-1.5"></i>Admin Console
                </a>
            @elseif(($user->role ?? '') === 'vendor')
                <a
                    href="/vendor/dashboard"
                    class="rounded-full bg-[var(--accent-dark)] px-5 py-2 text-xs md:text-sm font-semibold text-white shadow-sm transition hover:opacity-90"
                >
                    <i class="fa-solid fa-store mr-1.5"></i>Vendor Dashboard
                </a>
            @endif
            <a
                href="/"
                class="rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-5 py-2 text-xs md:text-sm font-semibold text-[var(--accent-dark)] transition hover:bg-[var(--mint-100)]"
            >
                Back to home
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-2xl border border-emerald-300 bg-emerald-50 px-5 py-3.5 text-sm font-medium text-emerald-900 flex items-center gap-2.5 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-2xl border border-rose-300 bg-rose-50 px-5 py-3.5 text-xs text-rose-800 space-y-1">
            @foreach($errors->all() as $error)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    {{-- Prompt Banner if Phone or Delivery Address Not Yet Set (e.g., Fresh Google Sign-Up) --}}
    @if(empty($user->phone) || empty($user->address))
        <div class="mb-6 rounded-3xl border border-amber-300 bg-amber-50/95 p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div class="space-y-1">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-600 px-3 py-0.5 text-[10px] font-extrabold uppercase text-white">
                    <i class="fa-solid fa-truck-fast"></i> Ready for Doorstep Delivery
                </span>
                <h2 class="text-sm md:text-base font-bold text-amber-950">
                    Add your {{ empty($user->phone) && empty($user->address) ? 'phone number & delivery address' : (empty($user->phone) ? 'phone number' : 'delivery address') }}
                </h2>
                <p class="text-xs text-amber-900/80">
                    Save your contact mobile and pickup/delivery address here so they pre-fill automatically whenever you request a device repair, book a service package, or order spare parts.
                </p>
            </div>
            <button
                type="button"
                @click="editing = true"
                x-show="!editing"
                class="shrink-0 rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-white shadow transition hover:opacity-95"
            >
                Complete Details Now
            </button>
        </div>
    @endif

    <!-- Main Profile Card (Matches Reference Profile Dashboard + Dual Address Picker) -->
    <form method="POST" action="{{ route('profile.update') }}" enctype="multipart/form-data" class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm">
        @csrf

        <div class="flex flex-col gap-6 md:flex-row md:items-center md:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs md:text-sm font-semibold uppercase tracking-[0.2em] text-[var(--muted-foreground)]">
                        Profile
                    </p>
                    @if(!empty($user->google_id))
                        <span class="inline-flex items-center gap-1.5 rounded-full bg-white/90 border border-[var(--border)] px-3 py-0.5 text-[11px] font-semibold text-[#203126]">
                            <i class="fa-brands fa-google text-[#4285F4]"></i> Google Account (Passwordless)
                        </span>
                    @endif
                </div>
                <h1 class="mt-2 text-3xl font-bold text-[var(--foreground)]">
                    Hi, {{ $firstName }}
                </h1>
                <p class="mt-1.5 text-sm text-[var(--muted-foreground)]">
                    Manage your account details, contact phone, and delivery address anytime.
                </p>
            </div>

            <!-- Avatar Preview & Upload -->
            <div class="flex flex-col items-center gap-2.5">
                <template x-if="avatarPreview">
                    <div class="relative h-20 w-20 overflow-hidden rounded-3xl border border-[var(--border)] bg-white shadow-sm">
                        <img :src="avatarPreview" alt="Profile Avatar" class="h-full w-full object-cover">
                    </div>
                </template>
                <template x-if="!avatarPreview">
                    <div class="grid h-20 w-20 place-items-center rounded-3xl bg-[var(--mint-100)] border border-[var(--border)] text-3xl font-bold text-[var(--accent-dark)]">
                        {{ strtoupper(substr($firstName, 0, 1)) }}
                    </div>
                </template>

                <div x-show="editing" x-cloak class="flex justify-center">
                    <label class="cursor-pointer rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-4 py-1.5 text-xs font-semibold text-[var(--accent-dark)] transition hover:bg-[var(--mint-200)]">
                        <span x-text="avatarPreview ? 'Change picture' : 'Upload picture'">Upload picture</span>
                        <input
                            type="file"
                            name="avatar"
                            accept="image/png,image/jpeg,image/webp"
                            class="hidden"
                            @change="handleAvatarFile($event)"
                        >
                    </label>
                </div>
            </div>
        </div>

        <!-- Core Profile Fields Grid -->
        <div class="mt-8 grid gap-4 md:grid-cols-2">
            <!-- 1. Full Name -->
            <div class="rounded-3xl border border-[var(--border)] bg-[var(--mint-50)] p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--muted-foreground)]">
                    Full name
                </p>
                <template x-if="editing">
                    <input
                        type="text"
                        name="name"
                        x-model="draft.name"
                        required
                        class="mt-2 w-full rounded-2xl border border-[var(--border)] bg-[var(--card)] px-4 py-3 text-sm md:text-base font-medium text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
                        placeholder="Your full name"
                    >
                </template>
                <template x-if="!editing">
                    <p class="mt-2 text-base font-medium text-[var(--foreground)]">
                        {{ $user->name ?: 'Not provided' }}
                    </p>
                </template>
            </div>

            <!-- 2. Username (Read-Only) -->
            <div class="rounded-3xl border border-[var(--border)] bg-[var(--mint-50)] p-5">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--muted-foreground)]">
                    Username
                </p>
                <p class="mt-2 text-base font-medium text-[var(--foreground)]">
                    {{ $user->username ?: 'Not provided' }}
                </p>
            </div>

            <!-- 3. Email (Read-Only + Verification Status) -->
            <div class="rounded-3xl border border-[var(--border)] bg-[var(--mint-50)] p-5">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--muted-foreground)]">
                        Email
                    </p>
                    @if($user->is_email_verified)
                        <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                            <i class="fa-solid fa-circle-check mr-1"></i>Verified
                        </span>
                    @else
                        <button
                            type="button"
                            @click="openOtpModal('email')"
                            class="rounded-full bg-[var(--accent-dark)] px-3 py-1 text-[11px] font-bold text-white hover:opacity-90"
                        >
                            Verify Email
                        </button>
                    @endif
                </div>
                <p class="mt-2 text-base font-medium text-[var(--foreground)] break-all">
                    {{ $user->email }}
                </p>
            </div>

            <!-- 4. Phone Number (Editable Anytime — Required When Ordering) -->
            <div class="rounded-3xl border border-[var(--border)] bg-[var(--mint-50)] p-5">
                <div class="flex items-center justify-between gap-2">
                    <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--muted-foreground)]">
                        Phone
                    </p>
                    @if(!empty($user->phone))
                        @if($user->is_phone_verified)
                            <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                                <i class="fa-solid fa-circle-check mr-1"></i>Verified
                            </span>
                        @else
                            <button
                                type="button"
                                x-show="!editing"
                                @click="openOtpModal('phone')"
                                class="rounded-full bg-[var(--accent-dark)] px-3 py-1 text-[11px] font-bold text-white hover:opacity-90"
                            >
                                Verify Phone
                            </button>
                        @endif
                    @else
                        <span class="rounded-full bg-amber-100 px-2.5 py-0.5 text-[10px] font-bold text-amber-800">
                            Needed for orders
                        </span>
                    @endif
                </div>

                <template x-if="editing">
                    <div>
                        <input
                            type="tel"
                            name="phone"
                            x-model="draft.phone"
                            placeholder="01XXXXXXXXX"
                            class="mt-2 w-full rounded-2xl border border-[var(--border)] bg-[var(--card)] px-4 py-3 text-sm md:text-base font-medium text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
                        >
                        <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">
                            Used by riders &amp; workshops when you order repairs or spare parts.
                        </p>
                    </div>
                </template>
                <template x-if="!editing">
                    <p class="mt-2 text-base font-medium text-[var(--foreground)]">
                        {{ $user->phone ?: 'Not provided' }}
                    </p>
                </template>
            </div>
        </div>

        <!-- ── Delivery Address Section (Location Picker OR Manual Input & Confirm) ── -->
        <div class="mt-6 rounded-3xl border border-[var(--border)] bg-[var(--mint-50)] p-5 md:p-6 space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2">
                        <p class="text-xs font-semibold uppercase tracking-[0.2em] text-[var(--muted-foreground)]">
                            Delivery &amp; Pickup Address
                        </p>
                        <template x-if="addressConfirmed && draft.address">
                            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                                <i class="fa-solid fa-check"></i>
                                <span x-text="draft.latitude && draft.longitude ? 'Map Pinned' : 'Confirmed'"></span>
                            </span>
                        </template>
                    </div>
                    <p class="mt-1 text-xs text-[var(--muted-foreground)]">
                        Pre-filled automatically during service package checkout and device pickup requests.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        @click="editing = true; addressInputMode = 'MAP'; $dispatch('meramot-open-location-modal')"
                        class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-90"
                    >
                        <i class="fa-solid fa-location-dot"></i>
                        <span>Use Location Picker</span>
                    </button>
                    <button
                        type="button"
                        @click="editing = true; addressInputMode = 'MANUAL'"
                        class="inline-flex items-center gap-1.5 rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-4 py-2 text-xs font-semibold text-[var(--accent-dark)] transition hover:bg-[var(--mint-100)]"
                    >
                        <i class="fa-solid fa-keyboard"></i>
                        <span>Manual Input</span>
                    </button>
                </div>
            </div>

            <!-- View Mode Display -->
            <div x-show="!editing" class="grid gap-4 md:grid-cols-3 pt-1">
                <div class="md:col-span-3 rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)]">Street Address</p>
                    <p class="mt-1 text-base font-medium text-[var(--foreground)]">
                        {{ $user->address ?: 'Not provided' }}
                    </p>
                    @if($user->latitude && $user->longitude)
                        <p class="mt-1 text-[11px] font-mono text-[var(--accent-dark)]">
                            <i class="fa-solid fa-map-pin mr-1"></i>Map Coordinates: {{ number_format((float) $user->latitude, 4) }}, {{ number_format((float) $user->longitude, 4) }}
                        </p>
                    @endif
                </div>
                <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)]">City</p>
                    <p class="mt-1 text-base font-medium text-[var(--foreground)]">{{ $user->city ?: 'Not provided' }}</p>
                </div>
                <div class="md:col-span-2 rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4">
                    <p class="text-[11px] font-semibold uppercase tracking-wider text-[var(--muted-foreground)]">Area / Neighborhood</p>
                    <p class="mt-1 text-base font-medium text-[var(--foreground)]">{{ $user->area ?: 'Not provided' }}</p>
                </div>
            </div>

            <!-- Edit Mode Inputs (Supports Both Location Picker & Manual Input + Confirm) -->
            <div x-show="editing" x-cloak class="space-y-4 pt-1">
                <input type="hidden" name="latitude" :value="draft.latitude">
                <input type="hidden" name="longitude" :value="draft.longitude">

                <template x-if="addressInputMode === 'MAP' && draft.latitude && draft.longitude">
                    <div class="rounded-2xl border border-emerald-300 bg-emerald-50/90 px-4 py-3 text-xs text-emerald-900 flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <span class="font-bold"><i class="fa-solid fa-map-location-dot mr-1"></i>Selected via Location Picker:</span>
                            <span x-text="draft.address"></span>
                            <span class="font-mono text-[11px] opacity-80" x-text="'(' + draft.latitude + ', ' + draft.longitude + ')'"></span>
                        </div>
                        <button
                            type="button"
                            @click="$dispatch('meramot-open-location-modal')"
                            class="rounded-full bg-emerald-700 px-3 py-1 text-[11px] font-bold text-white hover:bg-emerald-800"
                        >
                            Change Pin
                        </button>
                    </div>
                </template>

                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="block text-xs font-semibold uppercase tracking-[0.15em] text-[var(--muted-foreground)] mb-1.5">
                            Street / House / Road Delivery Address
                        </label>
                        <input
                            type="text"
                            name="address"
                            x-model="draft.address"
                            @input="if (addressInputMode === 'MANUAL') addressConfirmed = false"
                            placeholder="e.g. House 14, Road 11, Block E, Banani, Dhaka"
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--card)] px-4 py-3 text-sm md:text-base font-medium text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-[0.15em] text-[var(--muted-foreground)] mb-1.5">
                            City
                        </label>
                        <input
                            type="text"
                            name="city"
                            x-model="draft.city"
                            placeholder="Dhaka"
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--card)] px-4 py-3 text-sm md:text-base font-medium text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
                        >
                    </div>

                    <div>
                        <label class="block text-xs font-semibold uppercase tracking-[0.15em] text-[var(--muted-foreground)] mb-1.5">
                            Area / Neighborhood
                        </label>
                        <input
                            type="text"
                            name="area"
                            x-model="draft.area"
                            placeholder="Banani, Dhanmondi, Mirpur, Uttara..."
                            class="w-full rounded-2xl border border-[var(--border)] bg-[var(--card)] px-4 py-3 text-sm md:text-base font-medium text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
                        >
                    </div>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <p x-show="manualConfirmError" x-text="manualConfirmError" class="text-xs font-semibold text-rose-600"></p>
                    <div class="flex items-center gap-2 ml-auto">
                        <button
                            type="button"
                            @click="confirmManualAddress()"
                            class="inline-flex items-center gap-1.5 rounded-full border border-[var(--accent-dark)] bg-white px-4 py-2 text-xs font-bold text-[var(--accent-dark)] transition hover:bg-[var(--mint-100)]"
                        >
                            <i class="fa-solid fa-circle-check"></i>
                            <span x-text="addressConfirmed && addressInputMode === 'MANUAL' ? 'Manual Address Confirmed' : 'Confirm Manual Address'"></span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons Row (Matches Reference Profile Actions) -->
        <div class="mt-8 flex flex-wrap items-center gap-3">
            <button
                type="button"
                x-show="!editing"
                @click="editing = true"
                class="rounded-full bg-[var(--accent-dark)] px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95"
            >
                Edit profile
            </button>

            <button
                type="submit"
                x-show="editing"
                x-cloak
                class="rounded-full bg-[var(--accent-dark)] px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:opacity-95"
            >
                Save changes
            </button>

            <button
                type="button"
                x-show="editing"
                x-cloak
                @click="window.location.href = '{{ route('profile.show') }}'"
                class="rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-6 py-3 text-sm font-semibold text-[var(--accent-dark)] transition hover:bg-[var(--mint-100)]"
            >
                Cancel
            </button>

            @if(($user->role ?? '') === 'vendor')
                <a
                    href="/vendor/dashboard"
                    class="rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-6 py-3 text-sm font-semibold text-[var(--accent-dark)] transition hover:bg-[var(--mint-100)]"
                >
                    Vendor dashboard
                </a>
            @elseif(($user->role ?? '') === 'admin')
                <a
                    href="/admin"
                    class="rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-6 py-3 text-sm font-semibold text-[var(--accent-dark)] transition hover:bg-[var(--mint-100)]"
                >
                    Admin console
                </a>
            @else
                <a
                    href="/orders"
                    class="rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-6 py-3 text-sm font-semibold text-[var(--accent-dark)] transition hover:bg-[var(--mint-100)]"
                >
                    My orders
                </a>
            @endif

            @if(($user->role ?? '') !== 'admin')
                <button
                    type="button"
                    @click="showDeleteConfirm = true"
                    class="rounded-full border border-red-500 bg-[var(--card)] px-6 py-3 text-sm font-semibold text-red-600 transition hover:bg-red-50"
                >
                    Delete profile
                </button>
            @endif
        </div>
    </form>

    <!-- Delete Profile Confirmation Modal -->
    <div x-show="showDeleteConfirm" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-black/35 backdrop-blur-sm" @click="showDeleteConfirm = false"></div>
        <div class="relative z-[121] w-full max-w-md rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-8 shadow-2xl">
            <h2 class="text-center text-2xl font-bold text-red-600">
                Delete profile?
            </h2>
            <p class="mt-3 text-center text-sm leading-6 text-[var(--muted-foreground)]">
                This will permanently delete your account and profile data. This action cannot be undone.
            </p>
            <div class="mt-6 flex items-center justify-center gap-4">
                <button
                    type="button"
                    @click="showDeleteConfirm = false"
                    class="rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-6 py-2.5 text-sm font-semibold text-[var(--accent-dark)] transition hover:bg-[var(--mint-50)]"
                >
                    Cancel
                </button>
                <form method="POST" action="{{ route('profile.destroy') }}" @submit="deleting = true">
                    @csrf
                    @method('DELETE')
                    <button
                        type="submit"
                        :disabled="deleting"
                        class="rounded-full bg-red-600 px-6 py-2.5 text-sm font-semibold text-white transition hover:bg-red-700 disabled:opacity-60"
                    >
                        <span x-text="deleting ? 'Deleting...' : 'Yes, delete'">Yes, delete</span>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- OTP Verification Modal (Email or Phone) -->
    <div x-show="otpModalOpen" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center bg-black/50 backdrop-blur-sm p-4">
        <div class="relative w-full max-w-md overflow-hidden rounded-3xl bg-white shadow-2xl">
            <button
                type="button"
                @click="otpModalOpen = false"
                class="absolute right-4 top-4 text-slate-500 hover:text-slate-800 text-lg font-bold"
            >
                ✕
            </button>
            <div class="bg-gradient-to-br from-[#b8d9b5] to-[#e4f3df] p-6 text-center">
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-full bg-white text-[#244229] shadow-sm text-xl">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>
                <h3 class="text-xl font-bold text-[#244229]">
                    Verify your <span x-text="otpChannel"></span>
                </h3>
                <p class="mt-1 text-xs text-slate-700">
                    We will send a 6-digit code to
                    <span class="font-bold" x-text="otpChannel === 'email' ? @js($user->email) : @js($user->phone ?? '')"></span>
                </p>
            </div>
            <div class="p-6">
                <template x-if="!otpStepSent">
                    <div class="text-center">
                        <p class="mb-5 text-sm text-slate-600">Click below to receive your one-time verification code.</p>
                        <button
                            type="button"
                            @click="sendOtpNow()"
                            :disabled="otpSending"
                            class="w-full rounded-2xl bg-[#244229] py-3 text-sm font-semibold text-white transition hover:opacity-95 disabled:opacity-50"
                        >
                            <span x-text="otpSending ? 'Sending...' : 'Send Verification Code'"></span>
                        </button>
                    </div>
                </template>
                <template x-if="otpStepSent">
                    <div>
                        <p class="mb-3 text-center text-sm text-slate-600">Enter the 6-digit OTP code</p>
                        <input
                            type="text"
                            x-model="otpCode"
                            maxlength="6"
                            placeholder="123456"
                            class="mb-4 w-full rounded-2xl border border-slate-300 px-4 py-3 text-center text-lg tracking-[0.25em] outline-none focus:border-[#244229]"
                        >
                        <button
                            type="button"
                            @click="verifyOtpNow()"
                            :disabled="otpVerifying"
                            class="w-full rounded-2xl bg-emerald-600 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:opacity-50"
                        >
                            <span x-text="otpVerifying ? 'Verifying...' : 'Verify Code'"></span>
                        </button>
                        <button
                            type="button"
                            @click="sendOtpNow()"
                            :disabled="otpSending"
                            class="mt-3 w-full text-center text-xs font-medium text-slate-500 hover:text-[#244229]"
                        >
                            Didn't receive the code? Resend
                        </button>
                    </div>
                </template>

                <p x-show="otpFeedback" x-text="otpFeedback" class="mt-4 rounded-xl bg-emerald-50 p-3 text-center text-xs font-semibold text-emerald-700"></p>
                <p x-show="otpError" x-text="otpError" class="mt-4 rounded-xl bg-rose-50 p-3 text-center text-xs font-semibold text-rose-600"></p>
            </div>
        </div>
    </div>
</div>
@endsection

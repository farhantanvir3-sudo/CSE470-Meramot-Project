@extends('layouts.app')

@section('title', 'Delivery Partner Login | Meramot')

@section('content')
@php
    $submittedPopup = session('application_submitted_popup');
    $currentUser = auth()->user();
    $backHref = !$currentUser
        ? '/login'
        : ($currentUser->role === 'admin'
            ? '/admin'
            : ($currentUser->role === 'vendor' ? '/vendor/dashboard' : '/'));
    $backLabel = !$currentUser
        ? 'Back to Customer & Vendor Sign In'
        : ($currentUser->role === 'admin'
            ? 'Back to Admin Console'
            : ($currentUser->role === 'vendor' ? 'Back to Vendor Workspace' : 'Back to Marketplace Home'));
@endphp
<div
    class="min-h-[80vh] flex items-center justify-center px-4 py-8"
    x-data="{ showSubmittedModal: {{ $submittedPopup ? 'true' : 'false' }} }"
>
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

    <div class="flex w-full max-w-[460px] flex-col gap-6">

        <!-- Top Logo -->
        <div class="flex w-full items-center justify-center">
            <a href="/" class="inline-block transition-transform hover:scale-105">
                <img
                    src="/images/meramot.svg"
                    alt="Meramot"
                    class="h-16 w-auto object-contain md:h-20"
                />
            </a>
        </div>

        <!-- ── Dedicated Delivery Partner Company Login Card ── -->
        <div class="relative rounded-[2rem] border border-white/60 bg-white/95 px-8 py-7 shadow-2xl backdrop-blur">
            <a
                href="{{ $backHref }}"
                title="{{ $backLabel }}"
                aria-label="{{ $backLabel }}"
                class="group absolute left-6 top-6 inline-flex h-10 w-10 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] hover:shadow-md"
            >
                <i class="fa-solid fa-arrow-left text-xs transition-transform duration-200 group-hover:-translate-x-0.5"></i>
            </a>

            <div class="mb-5 text-center px-8">
                <span class="inline-flex items-center gap-1.5 rounded-full bg-[#eef5ea] border border-[#cbe0c2] px-3 py-1 text-[11px] font-bold uppercase tracking-wider text-[#214c34] mb-2">
                    <i class="fa-solid fa-motorcycle"></i> Meramot Logistics
                </span>
                <h1 class="text-2xl md:text-3xl font-bold leading-tight text-[#214c34]">
                    Delivery Partner Login
                </h1>
                <p class="mt-1.5 text-xs md:text-sm text-[#5e6d64]">
                    Sign in using your approved <code class="font-mono font-semibold text-[#214c34]">@meramotbd.com</code> company email and system-generated password.
                </p>
            </div>

            @if($currentUser)
                <div class="mb-4 rounded-2xl border border-[#cbe0c2] bg-[#eef5ea] px-4 py-3 text-xs text-[#163625] flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <span>
                        Signed in as <strong>{{ $currentUser->name ?? 'User' }}</strong>. Sign in below with a <code class="font-mono font-bold">@meramotbd.com</code> account to switch to the Delivery Workspace.
                    </span>
                    <a href="{{ $backHref }}" class="shrink-0 rounded-full bg-[#214c34] px-3.5 py-1.5 text-[11px] font-bold text-white hover:opacity-90 transition text-center">
                        Return &rarr;
                    </a>
                </div>
            @endif

            @if(session('success'))
                <div class="mb-4 rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-xs text-emerald-900 font-semibold">
                    <i class="fa-solid fa-circle-check text-emerald-700 mr-1.5"></i>{{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-700 space-y-1">
                    @foreach($errors->all() as $err)
                        <div><i class="fa-solid fa-circle-exclamation mr-1"></i> {{ $err }}</div>
                    @endforeach
                </div>
            @endif

            <!-- Company Email & Immutable Password Form Box -->
            <form method="POST" action="/delivery/login" class="space-y-4 rounded-2xl border border-[#d9e5d5] bg-[#f8fbf6] p-5">
                @csrf

                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-[#214c34]">
                        Company Email
                    </label>
                    <input
                        type="email"
                        name="company_email"
                        value="{{ old('company_email') }}"
                        required
                        autocomplete="username"
                        placeholder="yourname@meramotbd.com"
                        class="w-full rounded-2xl border border-[#c8dac2] bg-white px-4 py-3 text-sm font-mono text-[#163625] outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                    />
                </div>

                <div x-data="{ show: false }">
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-[#214c34]">
                        Company Password
                    </label>
                    <div class="relative">
                        <input
                            :type="show ? 'text' : 'password'"
                            name="password"
                            required
                            autocomplete="current-password"
                            placeholder="Enter company-generated password"
                            class="w-full rounded-2xl border border-[#c8dac2] bg-white px-4 py-3 pr-14 text-sm text-[#163625] outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        />
                        <button
                            type="button"
                            @click="show = !show"
                            class="absolute right-4 top-1/2 -translate-y-1/2 text-xs font-semibold text-[#5e6d64] hover:text-[#214c34]"
                        >
                            <span x-text="show ? 'Hide' : 'Show'">Show</span>
                        </button>
                    </div>
                </div>

                <button
                    type="submit"
                    class="group w-full inline-flex items-center justify-center gap-2 rounded-full bg-[var(--accent-dark)] py-3.5 text-sm font-bold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]"
                >
                    <span>Sign in as Delivery Partner</span>
                    <i class="fa-solid fa-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-1"></i>
                </button>
            </form>

            <!-- ── 2 Required Onboarding & Verification Tiles Below Form Box ── -->
            <div class="mt-5 space-y-3">
                <a
                    href="/delivery-agent/status"
                    class="group flex w-full items-center justify-between gap-3.5 rounded-2xl border border-[#c8dac2] bg-gradient-to-br from-white via-[#f8fbf6] to-[#eef5ea] px-4 py-3.5 text-left shadow-sm transition-all duration-250 hover:-translate-y-0.5 hover:border-[#214c34] hover:shadow-[0_12px_24px_-8px_rgba(22,54,37,0.22)]"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#c8dac2] bg-[#eef5ea] text-[#163625] shadow-sm transition-all duration-200 group-hover:scale-105 group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                            <i class="fa-solid fa-magnifying-glass-chart text-sm"></i>
                        </div>
                        <div>
                            <span class="block text-xs md:text-sm font-extrabold text-[#163625]">Check Application Status</span>
                            <span class="block text-[11px] text-[#5e6d64]">Verify via OTP, view feedback, edit or resend credentials</span>
                        </div>
                    </div>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] transition-all duration-200 group-hover:translate-x-0.5 group-hover:border-[#163625] group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </span>
                </a>

                <a
                    href="/delivery-agent/register"
                    class="group flex w-full items-center justify-between gap-3.5 rounded-2xl border border-[#c8dac2] bg-gradient-to-br from-white via-[#f8fbf6] to-[#eef5ea] px-4 py-3.5 text-left shadow-sm transition-all duration-250 hover:-translate-y-0.5 hover:border-[#214c34] hover:shadow-[0_12px_24px_-8px_rgba(22,54,37,0.22)]"
                >
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-[#c8dac2] bg-[#eef5ea] text-[#163625] shadow-sm transition-all duration-200 group-hover:scale-105 group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                            <i class="fa-solid fa-id-card-clip text-sm"></i>
                        </div>
                        <div>
                            <span class="block text-xs md:text-sm font-extrabold text-[#163625]">Apply as a delivery agent</span>
                            <span class="block text-[11px] text-[#5e6d64]">New courier partner? Submit your NID &amp; license</span>
                        </div>
                    </div>
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] transition-all duration-200 group-hover:translate-x-0.5 group-hover:border-[#163625] group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </span>
                </a>
            </div>
        </div>

    </div>
</div>
<script>
    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            window.location.reload();
        }
    });
</script>
@endsection

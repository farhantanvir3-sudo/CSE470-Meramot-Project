@extends('layouts.app')

@section('title', 'Check Delivery Application Status | Meramot')

@section('content')
<div class="mx-auto max-w-2xl px-4 py-8 md:px-6 space-y-6">

    <div class="flex items-start justify-between gap-4">
        <div>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-0.5 text-[11px] font-bold text-[var(--accent-dark)] mb-2 shadow-sm">
                <i class="fa-solid fa-shield-check"></i> OTP-Verified Applicant Portal
            </span>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Check Application Status</h1>
            <p class="text-xs md:text-sm text-[var(--muted-foreground)] mt-1">
                Verify your personal email via OTP to inspect your application status, view Admin feedback, edit your application, or re-send your <code class="font-mono">@meramotbd.com</code> company credentials.
            </p>
        </div>
        <a
            href="/delivery/login"
            title="Back to Delivery Partner Login"
            aria-label="Back to Delivery Partner Login"
            class="group mt-1 inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#163625] hover:bg-[#163625] hover:text-[#E4FCD5] hover:shadow-[0_8px_20px_-4px_rgba(22,54,37,0.35)]"
        >
            <i class="fa-solid fa-arrow-left text-sm transition-transform duration-200 group-hover:-translate-x-0.5"></i>
        </a>
    </div>

    @if($errors->any())
        <div class="rounded-2xl border border-rose-300 bg-rose-50 px-5 py-4 text-xs text-rose-800 space-y-1">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1.5"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    @if(isset($agent) && $agent)
        {{-- ==================================================================
             STEP 3: OTP-VERIFIED APPLICATION STATUS & CREDENTIAL / EDIT PANEL
             ================================================================== --}}
        @php
            $status = strtoupper((string) $agent->registration_status);
            $applicantName = $agent->applicant_name ?: ($agent->user?->name ?? 'Delivery Partner Applicant');
        @endphp

        <div class="rounded-[2rem] border border-[var(--border)] bg-white p-6 md:p-8 shadow-lg space-y-6">
            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-[var(--border)] pb-4">
                <div>
                    <p class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Verified Personal Email</p>
                    <h2 class="text-xl font-black text-[#163625]">{{ $applicantName }}</h2>
                    <p class="font-mono text-xs text-[#5e6d64]">{{ $agent->personal_email }}</p>
                </div>

                <div class="flex items-center gap-2">
                    @if($status === 'APPROVED')
                        <span class="rounded-full bg-emerald-100 border border-emerald-300 px-4 py-1.5 text-xs font-black text-emerald-900">
                            <i class="fa-solid fa-circle-check mr-1"></i> APPROVED
                        </span>
                    @elseif($status === 'REJECTED')
                        <span class="rounded-full bg-rose-100 border border-rose-300 px-4 py-1.5 text-xs font-black text-rose-900">
                            <i class="fa-solid fa-circle-xmark mr-1"></i> REJECTED
                        </span>
                    @else
                        <span class="rounded-full bg-amber-100 border border-amber-300 px-4 py-1.5 text-xs font-black text-amber-900">
                            <i class="fa-solid fa-clock mr-1"></i> PENDING ADMIN APPROVAL
                        </span>
                    @endif
                </div>
            </div>

            {{-- Status-Specific Guidance Box --}}
            @if($status === 'REJECTED')
                <div class="rounded-2xl border-2 border-rose-300 bg-rose-50 p-5 space-y-2">
                    <h3 class="text-sm font-black text-rose-900 flex items-center gap-2">
                        <i class="fa-solid fa-triangle-exclamation text-rose-700"></i>
                        <span>Why Your Application Was Rejected</span>
                    </h3>
                    <p class="text-xs md:text-sm font-semibold text-rose-950 leading-relaxed">
                        {{ $agent->rejection_reason ?: 'Please review and update your submitted identity/license documents.' }}
                    </p>
                    <p class="text-[11px] text-rose-800 pt-1">
                        Click <strong>Edit Application</strong> below to correct your information or upload updated documents. Once saved, your application will automatically return to <strong>PENDING</strong> status for Admin review.
                    </p>
                </div>
            @elseif($status === 'APPROVED')
                <div class="rounded-2xl border-2 border-emerald-300 bg-emerald-50/90 p-5 space-y-2">
                    <h3 class="text-sm font-black text-emerald-950 flex items-center gap-2">
                        <i class="fa-solid fa-id-badge text-emerald-700"></i>
                        <span>Congratulations! Your Delivery Partner Account Is Approved</span>
                    </h3>
                    <p class="text-xs text-emerald-900 leading-relaxed">
                        Your assigned Meramot company login email is:
                        <code class="font-mono font-bold bg-white px-2 py-0.5 rounded border border-emerald-300">{{ $agent->company_email }}</code>
                    </p>
                    <p class="text-[11px] text-emerald-800">
                        Your unique <code class="font-mono">@meramotbd.com</code> email and system-generated password were sent to <strong>{{ $agent->personal_email }}</strong>. Lost that email? Click <strong>Forgot Company Credentials</strong> below to email them again!
                    </p>
                </div>
            @else
                <div class="rounded-2xl border border-amber-300 bg-amber-50/90 p-5 space-y-1.5">
                    <h3 class="text-sm font-black text-amber-950 flex items-center gap-2">
                        <i class="fa-solid fa-hourglass-half text-amber-700"></i>
                        <span>Application Under Admin Review</span>
                    </h3>
                    <p class="text-xs text-amber-900 leading-relaxed">
                        Meramot Delivery Operations is verifying your NID, driving license, and uploaded documents. Once approved, your <code class="font-mono font-bold">@meramotbd.com</code> company email and password will be sent to <strong>{{ $agent->personal_email }}</strong>. You can still edit your application anytime below before approval.
                    </p>
                </div>
            @endif

            {{-- Submitted Details Summary --}}
            <div class="rounded-2xl border border-[#d9e5d5] bg-[#f8fbf6] p-4 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div>
                    <span class="text-[#5e6d64] block">Phone Number</span>
                    <span class="font-bold text-[#163625]">{{ $agent->applicant_phone ?: ($agent->user?->phone ?? '—') }}</span>
                </div>
                <div>
                    <span class="text-[#5e6d64] block">Vehicle Type</span>
                    <span class="font-bold capitalize text-[#163625]">{{ $agent->vehicle_type ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-[#5e6d64] block">NID Number</span>
                    <span class="font-mono font-bold text-[#163625]">{{ $agent->nid_number ?? '—' }}</span>
                </div>
                <div>
                    <span class="text-[#5e6d64] block">Driving License Number</span>
                    <span class="font-mono font-bold text-[#163625]">{{ $agent->license_number ?? '—' }}</span>
                </div>
            </div>

            {{-- Primary Action Buttons: Edit Application (Pending/Rejected) & Forgot Company Credentials --}}
            <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center justify-between gap-3 pt-2">
                @if($status !== 'APPROVED')
                    <a
                        href="/delivery-agent/edit"
                        class="group inline-flex items-center justify-center gap-2.5 rounded-2xl bg-[var(--accent-dark)] px-6 py-3.5 text-xs md:text-sm font-bold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]"
                    >
                        <i class="fa-solid fa-pen-to-square transition-transform duration-200 group-hover:scale-110"></i>
                        <span>Edit Application</span>
                    </a>
                @else
                    <a
                        href="/delivery/login"
                        class="group inline-flex items-center justify-center gap-2.5 rounded-2xl bg-[var(--accent-dark)] px-6 py-3.5 text-xs md:text-sm font-bold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]"
                    >
                        <i class="fa-solid fa-right-to-bracket transition-transform duration-200 group-hover:translate-x-0.5"></i>
                        <span>Go to Delivery Partner Login</span>
                    </a>
                @endif

                @if($status === 'APPROVED')
                    <form method="POST" action="/delivery-agent/status/resend-credentials" class="inline-flex">
                        @csrf
                        <button
                            type="submit"
                            class="group w-full inline-flex items-center justify-center gap-2 rounded-2xl border-2 border-[#214c34] bg-[#eef5ea] px-5 py-3 text-xs font-bold text-[#214c34] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#dcebd5] hover:shadow-md"
                        >
                            <i class="fa-solid fa-key transition-transform duration-200 group-hover:rotate-12"></i>
                            <span>Forgot Company Credentials? Email Again</span>
                        </button>
                    </form>
                @endif

                <form method="POST" action="/delivery-agent/status/reset" class="inline-flex sm:ml-auto">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border border-[#d9e5d5] bg-[#f8fbf6] px-4 py-2 text-xs font-bold text-[#5e6d64] transition-all duration-200 hover:-translate-y-0.5 hover:border-[#214c34] hover:bg-white hover:text-[#163625] hover:shadow-sm">
                        <i class="fa-solid fa-rotate-left text-[11px]"></i>
                        <span>Check another email</span>
                    </button>
                </form>
            </div>
        </div>

    @elseif(!empty($pendingEmail))
        {{-- ==================================================================
             STEP 2: ENTER 6-DIGIT OTP SENT TO PERSONAL EMAIL
             ================================================================== --}}
        <div class="rounded-[2rem] border border-[var(--border)] bg-white p-6 md:p-8 shadow-lg space-y-5">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-800">Step 2 of 2 &bull; Verify Identity</span>
                <h2 class="text-xl font-black text-[#163625] mt-1">Enter 6-Digit Verification OTP</h2>
                <p class="text-xs text-[#5e6d64] mt-1">
                    We sent a 6-digit code from <strong>meramotbd.official@gmail.com</strong> to <strong class="font-mono text-[#163625]">{{ $pendingEmail }}</strong>.
                </p>
            </div>

            <form method="POST" action="/delivery-agent/status/verify-otp" class="space-y-4">
                @csrf
                <input type="hidden" name="personal_email" value="{{ $pendingEmail }}">
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-[#214c34]">6-Digit OTP Code</label>
                    <input
                        type="text"
                        name="otp"
                        maxlength="6"
                        required
                        autofocus
                        placeholder="000000"
                        class="w-full rounded-2xl border-2 border-[#c8dac2] bg-[#f8fbf6] px-4 py-3.5 text-center font-mono text-2xl font-black tracking-[0.4em] text-[#163625] outline-none transition focus:border-[#214c34] focus:bg-white focus:ring-4 focus:ring-[#214c34]/10"
                    />
                </div>

                <button
                    type="submit"
                    class="group w-full inline-flex items-center justify-center gap-2 rounded-full bg-[var(--accent-dark)] py-3.5 text-sm font-bold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]"
                >
                    <span>Verify OTP &amp; View Application Status</span>
                    <i class="fa-solid fa-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-1"></i>
                </button>
            </form>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-3 border-t border-[#e7efe2] text-xs">
                <form method="POST" action="/delivery-agent/status/send-otp">
                    @csrf
                    <input type="hidden" name="personal_email" value="{{ $pendingEmail }}">
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border border-[#c8dac2] bg-[#f3faf0] px-4 py-2 font-bold text-[#214c34] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#214c34] hover:bg-[#e4f5dc] hover:shadow">
                        <i class="fa-solid fa-paper-plane text-[11px]"></i>
                        <span>Resend OTP</span>
                    </button>
                </form>

                <form method="POST" action="/delivery-agent/status/reset">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-full border border-[#d9e5d5] bg-white px-4 py-2 font-semibold text-[#5e6d64] transition-all duration-200 hover:-translate-y-0.5 hover:border-[#214c34] hover:text-[#163625] hover:shadow-sm">
                        <i class="fa-solid fa-envelope text-[11px]"></i>
                        <span>Use a different personal email</span>
                    </button>
                </form>
            </div>
        </div>

    @else
        {{-- ==================================================================
             STEP 1: ENTER PERSONAL EMAIL TO RECEIVE OTP
             ================================================================== --}}
        <div class="rounded-[2rem] border border-[var(--border)] bg-white p-6 md:p-8 shadow-lg space-y-5">
            <div>
                <span class="text-[11px] font-bold uppercase tracking-wider text-[#214c34]">Step 1 of 2 &bull; Applicant Verification</span>
                <h2 class="text-xl font-black text-[#163625] mt-1">Verify Your Personal Email</h2>
                <p class="text-xs text-[#5e6d64] mt-1">
                    Enter the personal email address you used when submitting your Delivery Agent application. We will send a one-time 6-digit verification code to confirm it's you.
                </p>
            </div>

            <form method="POST" action="/delivery-agent/status/send-otp" class="space-y-4">
                @csrf
                <div>
                    <label class="mb-1 block text-xs font-bold uppercase tracking-wider text-[#214c34]">Personal Email Address</label>
                    <input
                        type="email"
                        name="personal_email"
                        value="{{ old('personal_email') }}"
                        required
                        placeholder="e.g. kamal.hossain@gmail.com"
                        class="w-full rounded-2xl border-2 border-[#c8dac2] bg-[#f8fbf6] px-4 py-3 text-sm text-[#163625] outline-none transition focus:border-[#214c34] focus:bg-white focus:ring-4 focus:ring-[#214c34]/10"
                    />
                </div>

                <button
                    type="submit"
                    class="group w-full inline-flex items-center justify-center gap-2 rounded-full bg-[var(--accent-dark)] py-3.5 text-sm font-bold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]"
                >
                    <span>Send Verification OTP</span>
                    <i class="fa-solid fa-arrow-right text-xs transition-transform duration-200 group-hover:translate-x-1"></i>
                </button>
            </form>

            <div class="border-t border-[#e7efe2] pt-4">
                <a
                    href="/delivery-agent/register"
                    class="group flex items-center justify-between gap-4 rounded-2xl border border-[#c8dac2] bg-gradient-to-br from-[#f8fbf6] via-white to-[#eef5ea] p-4 shadow-sm transition-all duration-300 hover:-translate-y-1 hover:border-[#214c34] hover:shadow-[0_14px_28px_-8px_rgba(22,54,37,0.22)]"
                >
                    <div class="flex items-center gap-3.5">
                        <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-[#163625] text-[#E4FCD5] shadow-sm transition-transform duration-300 group-hover:scale-105">
                            <i class="fa-solid fa-id-card-clip text-base"></i>
                        </div>
                        <div>
                            <span class="block text-[10px] font-bold uppercase tracking-wider text-[#5e6d64]">Haven't applied yet?</span>
                            <span class="block text-sm font-extrabold text-[#163625] group-hover:text-[#214c34]">Apply as a delivery agent</span>
                            <span class="block text-[11px] text-[#5e6d64]">Submit your personal email, NID &amp; license to get verified</span>
                        </div>
                    </div>
                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#163625] shadow-sm transition-all duration-300 group-hover:translate-x-0.5 group-hover:border-[#163625] group-hover:bg-[#163625] group-hover:text-[#E4FCD5]">
                        <i class="fa-solid fa-arrow-right text-xs"></i>
                    </span>
                </a>
            </div>
        </div>
    @endif

</div>
@endsection

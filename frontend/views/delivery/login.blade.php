@extends('layouts.app')

@section('title', 'Delivery Partner Login | Meramot')

@section('content')
<div class="min-h-[80vh] flex items-center justify-center px-4 py-8">
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
        <div class="rounded-[2rem] border border-white/60 bg-white/95 px-8 py-7 shadow-2xl backdrop-blur">
            <div class="mb-5 text-center">
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
                    <p class="mt-1 text-[11px] text-[#5e6d64]">
                        Format: <span class="font-mono font-semibold text-[#214c34]">_____@meramotbd.com</span> (emailed to your personal email after approval)
                    </p>
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
                    <p class="mt-1 text-[11px] text-[#5e6d64]">
                        System-generated once upon approval &bull; Unchangeable by rider. Forgot it? Use <strong>Check Application Status</strong> below.
                    </p>
                </div>

                <button
                    type="submit"
                    class="w-full rounded-full bg-[var(--accent-dark)] py-3 text-sm font-bold text-white shadow transition hover:opacity-95"
                >
                    Sign In as Delivery Partner &rarr;
                </button>
            </form>

            <!-- ── 2 Required Onboarding & Verification Buttons Below Form Box ── -->
            <div class="mt-5 space-y-2.5">
                <a
                    href="/delivery-agent/status"
                    class="flex w-full items-center justify-center gap-2 rounded-full border-2 border-[#214c34] bg-[#eef5ea] px-5 py-3 text-xs md:text-sm font-bold text-[#214c34] transition hover:bg-[#dcebd5]"
                >
                    <i class="fa-solid fa-magnifying-glass-chart"></i>
                    <span>Check Application Status</span>
                </a>

                <a
                    href="/delivery-agent/register"
                    class="flex w-full items-center justify-center gap-2 rounded-full border border-[#c8dac2] bg-white px-5 py-3 text-xs md:text-sm font-bold text-[#163625] shadow-sm transition hover:border-[#214c34] hover:bg-[#f8fbf6]"
                >
                    <i class="fa-solid fa-id-card-clip"></i>
                    <span>Apply as a delivery agent</span>
                </a>
            </div>

            <div class="mt-5 border-t border-[var(--border)]/40 pt-4 text-center text-xs text-[#5e6d64]">
                <a href="/login" class="font-semibold text-[#214c34] hover:underline">
                    &larr; Back to Customer &amp; Vendor Sign In
                </a>
            </div>
        </div>

    </div>
</div>
@endsection

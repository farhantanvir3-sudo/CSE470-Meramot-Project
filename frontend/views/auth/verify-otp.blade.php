@extends('layouts.app')

@section('title', 'Verify Your Email | OTP Authorization — Meramot')

@section('content')
<div class="min-h-[80vh] flex items-center justify-center px-4 py-8">
    <div class="flex w-full max-w-[460px] flex-col gap-6">
        <!-- Top Logo -->
        <div class="flex w-full items-center justify-center">
            <a href="/" class="inline-block transition-transform hover:scale-105">
                <img src="/images/meramot.svg" alt="Meramot" class="h-16 w-auto object-contain md:h-20" />
            </a>
        </div>

        <div class="rounded-[2rem] border border-white/60 bg-white/95 px-8 py-7 shadow-2xl backdrop-blur">
            <div class="mb-5 text-center">
                <span class="inline-flex h-12 w-12 items-center justify-center rounded-2xl bg-[#eef5ea] text-[#214c34] mb-3">
                    <i class="fa-solid fa-envelope-circle-check text-xl"></i>
                </span>
                <h1 class="text-2xl font-bold leading-tight text-[#214c34]">
                    Verify your email
                </h1>
                <p class="mt-1.5 text-sm text-[#5e6d64]">
                    We sent a 6-digit verification code from <strong class="text-[#214c34]">meramotbd.official@gmail.com</strong> to:
                </p>
                <p class="mt-1 inline-block rounded-full bg-[#eef5ea] px-3.5 py-1 text-xs font-semibold text-[#214c34]">
                    {{ $user->email }}
                </p>
            </div>

            @if(session('success'))
                <div class="mb-4 rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-3 text-xs font-medium text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if($errors->any())
                <div class="mb-4 rounded-2xl bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-600">
                    {{ $errors->first() }}
                </div>
            @endif

            <form method="POST" action="{{ route('auth.verify-otp.submit') }}" class="space-y-4">
                @csrf
                <input type="hidden" name="channel" value="email" />

                <div>
                    <label class="mb-1.5 block text-sm font-medium text-[#214c34] text-center">
                        Enter 6-Digit Verification OTP
                    </label>
                    <input
                        type="text"
                        name="otp"
                        maxlength="6"
                        inputmode="numeric"
                        pattern="[0-9]{6}"
                        required
                        autofocus
                        class="w-full rounded-2xl border border-[var(--border)] bg-white px-4 py-3.5 text-center text-2xl font-bold tracking-[0.45em] text-[#214c34] outline-none transition focus:border-[#214c34] focus:ring-2 focus:ring-[#214c34]/20"
                        placeholder="000000"
                    />
                </div>

                <button
                    type="submit"
                    class="w-full rounded-full bg-[var(--accent-dark)] py-3 text-sm font-semibold text-white shadow transition hover:opacity-95"
                >
                    Verify &amp; Continue
                </button>
            </form>

            <div class="mt-5 flex items-center justify-between border-t border-[var(--border)]/40 pt-4 text-xs text-[#5e6d64]">
                <form method="POST" action="{{ route('auth.verify-otp.send') }}">
                    @csrf
                    <input type="hidden" name="channel" value="email" />
                    <button type="submit" class="font-semibold text-[#214c34] hover:underline">
                        <i class="fa-solid fa-rotate-right mr-1"></i> Resend OTP code
                    </button>
                </form>

                <a href="/" class="text-[#5e6d64] hover:text-[#214c34] hover:underline">
                    Verify later &rarr;
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

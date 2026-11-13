@extends('layouts.app')

@section('title', 'Vendor Application Submitted | Meramot')

@section('content')
<div class="grid min-h-[75vh] place-items-center px-4 py-10">
    <div class="w-full max-w-xl rounded-[2rem] border border-white/60 bg-white/90 p-8 text-center shadow-2xl backdrop-blur">
        <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-[var(--mint-100)] text-2xl text-[#244229] shadow-sm">
            <i class="fa-solid fa-circle-check"></i>
        </div>

        <h1 class="text-3xl font-bold text-[#244229]">
            Application submitted
        </h1>

        <p class="mt-3 text-sm text-[#5e6d64]">
            Your vendor account has been created successfully.
        </p>

        <p class="mt-2 text-sm text-[#5e6d64] leading-relaxed">
            Your application has been queued for administrative verification. Sign in anytime using your registered account to verify your contact details and track your review status.
        </p>

        @if(!empty($applicationId))
            <p class="mt-5 rounded-xl bg-[var(--mint-100)] px-4 py-3 text-xs text-slate-800">
                Application ID: <span class="font-bold font-mono">#{{ $applicationId }}</span>
            </p>
        @endif

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-center">
            @auth
                <a
                    href="{{ auth()->user()->role === 'vendor' ? '/vendor/onboarding' : '/' }}"
                    class="rounded-2xl bg-[#244229] px-6 py-3 text-sm font-semibold text-white shadow transition hover:opacity-95"
                >
                    {{ auth()->user()->role === 'vendor' ? 'Check Application Status' : 'Go to Workspace' }}
                </a>
            @else
                <a
                    href="/login"
                    class="rounded-2xl bg-[#244229] px-6 py-3 text-sm font-semibold text-white shadow transition hover:opacity-95"
                >
                    Go to Login
                </a>
            @endauth

            <a
                href="/"
                class="rounded-2xl border border-[var(--border)] bg-white px-6 py-3 text-sm font-semibold text-slate-700 transition hover:bg-[var(--mint-100)]"
            >
                Back to Home
            </a>
        </div>
    </div>
</div>
@endsection

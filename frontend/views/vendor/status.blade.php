@extends('layouts.app')

@section('title', 'Vendor Application Status | Meramot')

@section('content')
@php
    $statusUpper = strtoupper($profile->verification_status ?? 'pending');
    $setupComplete = (bool) optional($primaryShop)->setup_complete;
    $isPublic = (bool) optional($primaryShop)->is_public;
@endphp

<div class="grid min-h-[75vh] place-items-center px-4 py-10">
    <div class="w-full max-w-3xl rounded-[1.5rem] md:rounded-[2rem] border border-white/60 bg-white/90 p-6 md:p-8 shadow-2xl backdrop-blur">
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl md:text-3xl font-bold text-[#244229]">
                Vendor application status
            </h1>
            <a
                href="{{ route('vendor.onboarding') }}"
                title="Back to Onboarding Hub"
                aria-label="Back to Onboarding Hub"
                class="group inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-[#c8dac2] bg-white text-[#244229] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#244229] hover:bg-[#244229] hover:text-[#E4FCD5] hover:shadow-md"
            >
                <i class="fa-solid fa-arrow-left text-xs transition-transform duration-200 group-hover:-translate-x-0.5"></i>
            </a>
        </div>

        @if(session('success'))
            <div class="mt-4 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs font-semibold text-emerald-800">
                {{ session('success') }}
            </div>
        @endif

        <div class="mt-6 grid gap-3 sm:grid-cols-2 rounded-3xl bg-[#f6faf4] p-5 text-sm text-slate-700">
            <p><span class="font-semibold text-slate-900">Application ID:</span> #{{ $profile->id }}</p>
            <p><span class="font-semibold text-slate-900">Owner:</span> {{ $profile->owner_name ?: $user->name }}</p>
            <p><span class="font-semibold text-slate-900">Business email:</span> {{ $profile->business_email ?: $user->email }}</p>
            <p><span class="font-semibold text-slate-900">Shop name:</span> {{ $profile->shop_name }}</p>
            <p><span class="font-semibold text-slate-900">Status:</span> {{ $statusUpper }}</p>
            <p><span class="font-semibold text-slate-900">Shop setup:</span> {{ $setupComplete ? 'Completed' : 'Pending' }}</p>
            <p><span class="font-semibold text-slate-900">Visibility:</span> {{ $isPublic ? 'Public' : 'Hidden' }}</p>
        </div>

        @if($statusUpper === 'PENDING')
            <div class="mt-6 rounded-2xl bg-amber-50 border border-amber-200 px-4 py-4 text-sm text-amber-900">
                <p class="font-semibold">Your application is under review.</p>
                <p class="mt-1 text-xs md:text-sm">
                    Please check back later. You can also update your submitted information if needed.
                </p>
            </div>
        @elseif($statusUpper === 'REJECTED')
            <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-4 text-sm text-red-900">
                <p class="font-semibold text-base">Your application was rejected.</p>
                <div class="mt-3 rounded-xl bg-white/80 px-4 py-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-red-700">Rejection reason</p>
                    <p class="mt-1.5">{{ $profile->rejection_reason ?: 'No rejection reason was provided.' }}</p>
                </div>
                @if($profile->rejection_visible_until)
                    <p class="mt-3 text-xs">
                        Visible until: <span class="font-semibold">{{ $profile->rejection_visible_until->format('M d, Y') }}</span>
                    </p>
                @endif
            </div>
        @elseif($statusUpper === 'APPROVED')
            <div class="mt-6 rounded-2xl bg-emerald-50 border border-emerald-200 px-4 py-4 text-sm text-emerald-900">
                <p class="font-semibold">Your application has been approved.</p>
                <p class="mt-1 text-xs md:text-sm">
                    {{ $setupComplete
                        ? 'Your shop is ready. You can now use the vendor dashboard for bidding, direct orders, and final quote handling.'
                        : 'Complete your shop profile, pricing, and available services before your shop goes live publicly.' }}
                </p>
            </div>
        @endif

        <div class="mt-8 flex flex-wrap gap-3">
            @if(in_array($statusUpper, ['PENDING', 'REJECTED'], true))
                <a
                    href="{{ route('vendor.apply') }}"
                    class="rounded-2xl border border-[#c8dac2] bg-white px-5 py-3 text-center text-sm font-semibold text-slate-700 shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#244229] hover:bg-[var(--mint-100)] hover:shadow-md"
                >
                    Edit submitted information
                </a>
            @endif

            @if($statusUpper === 'APPROVED' && !$setupComplete)
                <a
                    href="{{ route('vendor.setup-shop') }}"
                    class="rounded-2xl bg-[#244229] px-5 py-3 text-center text-sm font-semibold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]"
                >
                    Complete shop setup
                </a>
            @endif

            @if($statusUpper === 'APPROVED' && $setupComplete)
                <a
                    href="{{ route('vendor.dashboard') }}"
                    class="rounded-2xl bg-[#244229] px-5 py-3 text-center text-sm font-semibold text-white shadow-md transition-all duration-200 hover:-translate-y-0.5 hover:bg-[#1d4630] hover:shadow-[0_12px_24px_-6px_rgba(22,54,37,0.4)]"
                >
                    Open vendor dashboard
                </a>
            @endif

            <a
                href="{{ route('vendor.onboarding') }}"
                class="rounded-2xl border border-[#c8dac2] bg-[var(--mint-100)] px-5 py-3 text-center text-sm font-semibold text-[#244229] shadow-sm transition-all duration-200 hover:-translate-y-0.5 hover:border-[#244229] hover:bg-[var(--mint-200)] hover:shadow-md"
            >
                Back to onboarding
            </a>
        </div>
    </div>
</div>
@endsection

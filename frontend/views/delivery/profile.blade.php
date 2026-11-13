@extends('delivery.layout')

@section('title', 'Delivery Partner Profile | Meramot')

@section('content')
@php
    $deliveryName = $user->name ?? $user->username ?? 'Delivery Partner';
    $avatarUrl = $agent->profile_picture_url ?: $user->avatar_url;
    $companyEmail = $agent->company_email ?: $user->email;
@endphp
<div class="mx-auto flex w-full max-w-3xl flex-col py-2">
    <div class="rounded-3xl border border-[#d9e5d5] bg-white p-6 shadow-sm sm:p-8">
        <div class="mb-6 flex flex-col items-center gap-4 text-center sm:flex-row sm:items-center sm:text-left">
            <div class="flex h-24 w-24 shrink-0 items-center justify-center overflow-hidden rounded-full border-2 border-[#d9e5d5] bg-[#eef4ea]">
                @if($avatarUrl)
                    <img
                        src="{{ $avatarUrl }}"
                        alt="{{ $deliveryName }}"
                        class="h-full w-full object-cover"
                    />
                @else
                    <span class="text-3xl font-extrabold text-[#163625]">
                        {{ strtoupper(substr($deliveryName, 0, 1)) }}
                    </span>
                @endif
            </div>
            <div class="min-w-0">
                <h1 class="break-words text-2xl font-extrabold text-[#163625]">{{ $deliveryName }}</h1>
                <p class="mt-1 text-sm font-medium text-[#163625]/65">Courier Profile &bull; Official Meramot Fleet</p>
            </div>
        </div>

        <div class="space-y-3 rounded-2xl border border-[#d9e5d5] bg-[#f3faf0] p-5 text-sm text-[#163625]">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-[#d9e5d5]/70 pb-2.5">
                <span class="font-semibold text-[#163625]/75">Company Login Email:</span>
                <span class="font-mono font-bold break-all">{{ $companyEmail }}</span>
            </div>

            @if($agent->personal_email)
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-[#d9e5d5]/70 pb-2.5">
                    <span class="font-semibold text-[#163625]/75">Verified Personal Email:</span>
                    <span class="break-all">{{ $agent->personal_email }}</span>
                </div>
            @endif

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-[#d9e5d5]/70 pb-2.5">
                <span class="font-semibold text-[#163625]/75">Phone:</span>
                <span>{{ $user->phone ?? $agent->applicant_phone ?? '—' }}</span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-[#d9e5d5]/70 pb-2.5">
                <span class="font-semibold text-[#163625]/75">Vehicle:</span>
                <span class="capitalize font-semibold">{{ $agent->vehicle_type ?? '—' }}</span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1 border-b border-[#d9e5d5]/70 pb-2.5">
                <span class="font-semibold text-[#163625]/75">NID / BRTA License:</span>
                <span class="font-mono text-xs">{{ $agent->nid_number ?? '—' }} &bull; {{ $agent->license_number ?? '—' }}</span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                <span class="font-semibold text-[#163625]/75">Verification Status:</span>
                <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-0.5 text-xs font-bold text-emerald-800 border border-emerald-300">
                    {{ $agent->registration_status ?? 'APPROVED' }}
                </span>
            </div>
        </div>

        <div class="mt-4 rounded-2xl border border-[#d9e5d5] bg-[#f8fbf6] px-4 py-3 text-xs text-[#163625]/75 flex items-start gap-2.5">
            <i class="fa-solid fa-lock mt-0.5 text-[#163625]"></i>
            <span>
                Your <code class="font-mono font-semibold">@meramotbd.com</code> company email and system-generated password are fixed by Meramot Security Operations and cannot be edited manually.
            </span>
        </div>

        <form action="/delivery/logout" method="POST" class="mt-6">
            @csrf
            <button
                type="submit"
                class="flex w-full items-center justify-center gap-2 rounded-xl border border-red-200 bg-red-50 py-3 text-sm font-bold text-red-700 transition hover:bg-red-100"
            >
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Sign Out</span>
            </button>
        </form>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Coming Soon — ' . ($meta['title'] ?? 'Upcoming Feature') . ' | Meramot')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-10 md:px-6 md:py-14">
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-8 md:p-12 shadow-lg text-center relative overflow-hidden">

        <!-- Decorative Mint Glow -->
        <div class="pointer-events-none absolute -top-16 -right-16 h-48 w-48 rounded-full bg-[var(--mint-200)]/60 blur-2xl"></div>
        <div class="pointer-events-none absolute -bottom-16 -left-16 h-48 w-48 rounded-full bg-[var(--mint-200)]/60 blur-2xl"></div>

        <div class="relative z-10 flex flex-col items-center">
            <!-- Icon Badge -->
            <div class="mb-5 flex h-20 w-20 items-center justify-center rounded-[1.5rem] border border-[var(--border)] bg-[var(--mint-100)] text-[var(--accent-dark)] shadow-sm">
                <i class="fa-solid {{ $meta['icon'] ?? 'fa-hourglass-half' }} text-3xl"></i>
            </div>

            <!-- Roadmap Pill -->
            <div class="mb-3 flex flex-wrap items-center justify-center gap-2">
                <span class="rounded-full bg-[var(--accent-dark)] px-4 py-1 text-[11px] font-extrabold uppercase tracking-[0.2em] text-[var(--accent-foreground)]">
                    Coming Soon
                </span>
                @if(!empty($meta['sprint']))
                    <span class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-3.5 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
                        {{ $meta['sprint'] }} Roadmap
                    </span>
                @endif
            </div>

            @if(!empty($meta['code']))
                <span class="dev-only-badge mb-3 inline-block rounded-full border border-emerald-300 bg-emerald-50 px-3 py-0.5 font-mono text-[10px] font-bold text-emerald-900">
                    {{ $meta['code'] }} ({{ $meta['window'] ?? 'Upcoming' }})
                </span>
            @endif

            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)] leading-tight max-w-xl">
                {{ $meta['title'] ?? 'Feature Coming Soon' }}
            </h1>

            @if(!empty($wasFormSubmission))
                <div class="mt-4 inline-flex items-center gap-2 rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-2 text-xs font-bold text-emerald-900">
                    <i class="fa-solid fa-circle-info text-emerald-700"></i>
                    <span>Your submission was received. Full interactive execution for this module will unlock in an upcoming release.</span>
                </div>
            @endif

            <p class="mt-4 max-w-xl text-sm leading-relaxed text-[var(--muted-foreground)]">
                {{ $meta['summary'] ?? 'This feature is scheduled for an upcoming platform release.' }}
            </p>

            <!-- Live Platform Highlights Box -->
            <div class="mt-7 w-full max-w-xl rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/70 p-4 text-left text-xs">
                <p class="font-extrabold uppercase tracking-wider text-[10px] text-[var(--accent-dark)] mb-2">
                    <i class="fa-solid fa-circle-check mr-1"></i> Available Now on Meramot
                </p>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-[var(--foreground)] font-medium">
                    <a href="/shops" class="flex items-center gap-2 rounded-xl bg-[var(--card)] px-3 py-2 border border-[var(--border)] hover:border-[var(--accent-dark)] transition">
                        <i class="fa-solid fa-map-location-dot text-[var(--accent-dark)]"></i>
                        <span>Nationwide Shop Search &amp; Map</span>
                    </a>
                    <a href="/requests/create" class="flex items-center gap-2 rounded-xl bg-[var(--card)] px-3 py-2 border border-[var(--border)] hover:border-[var(--accent-dark)] transition">
                        <i class="fa-solid fa-screwdriver-wrench text-[var(--accent-dark)]"></i>
                        <span>Post &amp; Track Repair Requests</span>
                    </a>
                    <a href="/vendor/onboarding" class="flex items-center gap-2 rounded-xl bg-[var(--card)] px-3 py-2 border border-[var(--border)] hover:border-[var(--accent-dark)] transition">
                        <i class="fa-solid fa-store text-[var(--accent-dark)]"></i>
                        <span>Vendor Onboarding &amp; Branches</span>
                    </a>
                    <a href="/delivery-agent/register" class="flex items-center gap-2 rounded-xl bg-[var(--card)] px-3 py-2 border border-[var(--border)] hover:border-[var(--accent-dark)] transition">
                        <i class="fa-solid fa-id-badge text-[var(--accent-dark)]"></i>
                        <span>Delivery Partner Onboarding</span>
                    </a>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="mt-7 flex flex-wrap items-center justify-center gap-3">
                <button
                    type="button"
                    onclick="if (window.history.length > 1) { window.history.back(); } else { window.location.href = '/'; }"
                    class="inline-flex items-center gap-2 rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-5 py-2.5 text-xs font-bold text-[var(--accent-dark)] transition hover:bg-[var(--mint-100)]"
                >
                    <i class="fa-solid fa-arrow-left"></i>
                    <span>Go Back</span>
                </button>

                @if(!empty($relatedRequestId))
                    <a
                        href="{{ route('requests.track', $relatedRequestId) }}"
                        class="inline-flex items-center gap-2 rounded-full bg-[var(--accent-dark)] px-6 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow transition hover:opacity-90"
                    >
                        <i class="fa-solid fa-clipboard-check"></i>
                        <span>Track Order #{{ $relatedRequestId }}</span>
                    </a>
                @endif

                <a
                    href="/requests"
                    class="inline-flex items-center gap-2 rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-5 py-2.5 text-xs font-bold text-[var(--foreground)] transition hover:bg-[var(--mint-200)]"
                >
                    <i class="fa-solid fa-list-check"></i>
                    <span>My Repair Requests</span>
                </a>

                <a
                    href="/"
                    class="inline-flex items-center gap-2 rounded-full bg-[var(--accent-dark)] px-6 py-2.5 text-xs font-bold text-[var(--accent-foreground)] shadow transition hover:opacity-90"
                >
                    <i class="fa-solid fa-house"></i>
                    <span>Return to Homepage</span>
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

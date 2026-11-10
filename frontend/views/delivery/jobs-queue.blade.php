{{--
=============================================================================
FEATURE R2: Delivery Job Queue & Status Milestone Synchronization
=============================================================================
Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
Architectural Layer: View (Delivery/JobsQueue)
Controller: DeliveryJobController@index, claimJob, updateMilestone
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Rider Delivery Job Queue | Meramot')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 md:px-6 space-y-6">
    <!-- Sprint 2 Roadmap Banner -->
    <div class="rounded-[1.75rem] border border-amber-300 bg-amber-50/90 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="h-10 w-10 rounded-2xl bg-amber-100 border border-amber-300 flex items-center justify-center text-amber-800 shrink-0">
                <i class="fa-solid fa-motorcycle"></i>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-900">Scheduled for Sprint 2 • Coming Soon</span>
                <h2 class="text-sm font-black text-amber-950">Live Rider Job Claiming & 4-Digit OTP Handover</h2>
                <p class="text-xs text-amber-900 mt-0.5">Courier dispatch queue claiming launches in Sprint 2. Rider registration & Admin Verification (R1) are live now in Sprint 1.</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="/delivery-agent/register" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                Rider Registration (Live) &rarr;
            </a>
            <a href="/coming-soon/rider-dispatch-queue" class="rounded-full border border-amber-300 bg-white px-4 py-2 text-xs font-bold text-amber-950 hover:bg-amber-100 transition">
                Roadmap Details
            </a>
        </div>
    </div>

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">Rider Delivery Dispatch Queue</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 dev-only-badge">Feature R2 | Assigned to: <strong class="text-[var(--accent-dark)]">Md. Nafiul Islam [22299035]</strong></p>
        </div>
        <span class="dev-only-badge text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-1.5 rounded-full border border-[var(--border)] self-start">
            Sprint 2 Preview
        </span>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Unassigned Available Jobs -->
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
            <h2 class="font-black text-base text-[var(--foreground)] mb-4 flex items-center justify-between">
                <span>Available Jobs for Pickup</span>
                <span class="text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] px-2.5 py-1 rounded-full font-mono border border-[var(--border)]">{{ count($availableJobs ?? []) }} Open</span>
            </h2>

            <div class="space-y-4">
                @forelse($availableJobs ?? [] as $job)
                    <div class="p-4 border border-[var(--border)] rounded-2xl bg-[var(--mint-100)]">
                        <div class="flex justify-between items-start">
                            <span class="font-bold text-[var(--foreground)] text-sm">Trip #{{ $job->id }}</span>
                            <span class="text-xs font-bold text-emerald-800 bg-emerald-100 px-2.5 py-0.5 rounded-full border border-emerald-300">৳{{ number_format($job->fee ?? 150, 0) }} Delivery Fee</span>
                        </div>
                        <p class="text-xs text-[var(--muted-foreground)] mt-2"><strong>Pickup:</strong> {{ $job->pickup_address ?? 'Dhanmondi 27, Dhaka' }}</p>
                        <p class="text-xs text-[var(--muted-foreground)]"><strong>Dropoff:</strong> {{ $job->dropoff_address ?? 'Mirpur 10 (Repair Shop)' }}</p>

                        <form method="POST" action="/delivery/jobs/{{ $job->id }}/claim" class="mt-4">
                            @csrf
                            <button type="submit" class="w-full bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] font-bold text-xs py-2.5 rounded-full transition">
                                Claim Delivery Task (Sprint 2)
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[var(--border)] bg-[var(--mint-100)] p-6 text-center space-y-3">
                        <p class="text-xs text-[var(--muted-foreground)]">No unassigned delivery trips queued yet. Real-time dispatch activates in Sprint 2.</p>
                        <a href="/coming-soon/rider-dispatch-queue" class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                            <span>Preview Dispatch Workflow</span>
                            <i class="fa-solid fa-arrow-right text-[10px]"></i>
                        </a>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Active Claimed Tasks -->
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
            <h2 class="font-black text-base text-[var(--foreground)] mb-4">My In-Progress Deliveries</h2>
            <div class="space-y-4">
                @forelse($activeJobs ?? [] as $active)
                    <div class="p-4 border-l-4 border-l-amber-500 border border-[var(--border)] bg-[var(--mint-100)] rounded-2xl">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-xs text-[var(--foreground)]">Job #{{ $active->id }}</span>
                            <span class="text-xs uppercase font-mono font-bold text-amber-800">{{ $active->status }}</span>
                        </div>
                        <div class="text-xs text-[var(--muted-foreground)] space-y-1">
                            <div>Pickup OTP: <span class="font-mono font-bold text-[var(--foreground)]">{{ $active->pickup_otp ?? '4921' }}</span></div>
                        </div>
                        <a href="/tracking/{{ $active->id }}" class="mt-3 block text-center text-xs font-bold text-[var(--accent-dark)] hover:underline">
                            Open Live GPS Navigation &rarr;
                        </a>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[var(--border)] bg-[var(--mint-100)] p-6 text-center space-y-3">
                        <p class="text-xs text-[var(--muted-foreground)]">You have no active claimed delivery tasks right now.</p>
                        <a href="/delivery/earnings" class="inline-flex items-center gap-1.5 rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                            <i class="fa-solid fa-wallet text-[var(--accent-dark)]"></i>
                            <span>View Rider Earnings Wallet</span>
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

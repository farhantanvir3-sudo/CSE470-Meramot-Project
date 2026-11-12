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
@extends('delivery.layout')

@section('title', 'Rider Delivery Dispatch Pool | Meramot')

@section('content')
<div class="space-y-6">
    <!-- Sprint 2 Roadmap Banner -->
    <div class="rounded-3xl border border-amber-300 bg-amber-50/90 p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3">
            <div class="h-10 w-10 rounded-2xl bg-amber-100 border border-amber-300 flex items-center justify-center text-amber-800 shrink-0">
                <i class="fa-solid fa-motorcycle"></i>
            </div>
            <div>
                <span class="text-[10px] font-black uppercase tracking-wider text-amber-900">Scheduled for Sprint 2 &bull; Coming Soon</span>
                <h2 class="text-sm font-black text-amber-950">Open Dispatch Pool Claiming &amp; 4-Digit OTP Handover</h2>
                <p class="text-xs text-amber-900 mt-0.5">Unassigned pool claiming launches in Sprint 2. View your assigned active deliveries directly in your Delivery Dashboard.</p>
            </div>
        </div>
        <div class="flex flex-wrap gap-2 shrink-0">
            <a href="/delivery" class="rounded-full bg-[#163625] px-4 py-2 text-xs font-bold text-[#E4FCD5] hover:opacity-90 transition">
                Delivery Dashboard &rarr;
            </a>
        </div>
    </div>

    <div>
        <h1 class="text-2xl font-extrabold text-[#163625]">Rider Delivery Dispatch Pool</h1>
        <p class="text-xs text-[#163625]/70 mt-1">Open unassigned pickup jobs &amp; active rider trips</p>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <!-- Unassigned Available Jobs -->
        <div class="rounded-3xl border border-[#d9e5d5] bg-white p-6 shadow-sm">
            <h2 class="font-bold text-base text-[#163625] mb-4 flex items-center justify-between">
                <span>Available Jobs for Pickup</span>
                <span class="text-xs bg-[#eef4ea] text-[#163625] px-2.5 py-1 rounded-full font-mono border border-[#d9e5d5]">{{ count($availableJobs ?? []) }} Open</span>
            </h2>

            <div class="space-y-4">
                @forelse($availableJobs ?? [] as $job)
                    <div class="p-4 border border-[#d9e5d5] rounded-2xl bg-[#f8fbf6]">
                        <div class="flex justify-between items-start">
                            <span class="font-bold text-[#163625] text-sm">Trip #{{ $job->id }}</span>
                            <span class="text-xs font-bold text-emerald-800 bg-emerald-100 px-2.5 py-0.5 rounded-full border border-emerald-300">BDT {{ number_format($job->fee ?? 150, 0) }} Fee</span>
                        </div>
                        <p class="text-xs text-[#163625]/75 mt-2"><strong>Pickup:</strong> {{ $job->pickup_address ?? 'Dhanmondi 27, Dhaka' }}</p>
                        <p class="text-xs text-[#163625]/75"><strong>Dropoff:</strong> {{ $job->dropoff_address ?? 'Mirpur 10 (Repair Shop)' }}</p>

                        <form method="POST" action="/delivery/jobs/{{ $job->id }}/claim" class="mt-4">
                            @csrf
                            <button type="submit" class="w-full bg-[#163625] hover:opacity-90 text-[#E4FCD5] font-bold text-xs py-2.5 rounded-full transition">
                                Claim Delivery Task (Sprint 2)
                            </button>
                        </form>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#d9e5d5] bg-[#f8fbf6] p-6 text-center space-y-2">
                        <p class="text-xs text-[#163625]/70">No unassigned delivery trips queued right now.</p>
                    </div>
                @endforelse
            </div>
        </div>

        <!-- Active Claimed Tasks -->
        <div class="rounded-3xl border border-[#d9e5d5] bg-white p-6 shadow-sm">
            <h2 class="font-bold text-base text-[#163625] mb-4">My In-Progress Deliveries</h2>
            <div class="space-y-4">
                @forelse($activeJobs ?? [] as $active)
                    <div class="p-4 border-l-4 border-l-[#163625] border border-[#d9e5d5] bg-[#f8fbf6] rounded-2xl">
                        <div class="flex justify-between items-center mb-2">
                            <span class="font-bold text-xs text-[#163625]">Job #{{ $active->id }}</span>
                            <span class="text-xs uppercase font-mono font-bold text-amber-800">{{ $active->status }}</span>
                        </div>
                        <div class="text-xs text-[#163625]/75 space-y-1">
                            <div>Pickup OTP: <span class="font-mono font-bold text-[#163625]">{{ $active->pickup_otp ?? '4921' }}</span></div>
                        </div>
                        <a href="/delivery/order/{{ $active->id }}" class="mt-3 block text-center text-xs font-bold text-[#163625] hover:underline">
                            Open Order Details &amp; Milestones &rarr;
                        </a>
                    </div>
                @empty
                    <div class="rounded-2xl border border-dashed border-[#d9e5d5] bg-[#f8fbf6] p-6 text-center space-y-3">
                        <p class="text-xs text-[#163625]/70">You have no active claimed delivery tasks right now.</p>
                        <a href="/delivery/earnings" class="inline-flex items-center gap-1.5 rounded-full border border-[#d9e5d5] bg-white px-4 py-2 text-xs font-bold text-[#163625] hover:bg-[#eef4ea] transition">
                            <i class="fa-solid fa-wallet"></i>
                            <span>View Rider Earnings Wallet</span>
                        </a>
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

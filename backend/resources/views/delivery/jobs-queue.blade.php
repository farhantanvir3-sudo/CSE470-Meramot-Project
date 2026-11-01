{{--
=============================================================================
FEATURE R2: Delivery Job Queue & Status Milestone Synchronization
=============================================================================
Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
Architectural Layer: View (Delivery/JobsQueue)
Controller: DeliveryJobController@index, claimJob, updateMilestone
Models Touched: DeliveryJob, RepairRequest

INSTRUCTIONS FOR NAFIUL:
- Display list of open pickup/dropoff jobs in metro zones.
- Provide "Claim Delivery Job" button for active delivery riders.
- Display current milestone status (Assigned -> Picked Up -> In Transit -> Delivered).
- Prompt for 4-digit OTP code before milestone state changes to 'Delivered'.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Rider Delivery Job Queue | Feature R2')

@section('content')
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Rider Delivery Dispatch Queue</h1>
            <p class="text-xs text-slate-500 mt-1">Feature R2 | Assigned to: <strong class="text-teal-700">Md. Nafiul Islam [22299035]</strong></p>
        </div>
        <span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-3 py-1 rounded-full border border-indigo-200">
            Sprint 2 Target
        </span>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Unassigned Available Jobs -->
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <h2 class="font-bold text-base text-slate-900 mb-4 flex items-center justify-between">
            <span>Available Jobs for Pickup</span>
            <span class="text-xs bg-teal-100 text-teal-800 px-2 py-0.5 rounded-full font-mono">{{ count($availableJobs ?? []) }} Open</span>
        </h2>
        
        <div class="space-y-4">
            @forelse($availableJobs ?? [] as $job)
                <div class="p-4 border border-slate-200 rounded-lg bg-slate-50">
                    <div class="flex justify-between items-start">
                        <span class="font-semibold text-slate-900 text-sm">Trip #{{ $job->id }}</span>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">৳150 Delivery Fee</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-2"><strong>Pickup:</strong> {{ $job->pickup_address ?? 'Dhanmondi 27, Dhaka' }}</p>
                    <p class="text-xs text-slate-500"><strong>Dropoff:</strong> {{ $job->dropoff_address ?? 'Mirpur 10 (Repair Shop)' }}</p>

                    <form method="POST" action="/delivery/jobs/{{ $job->id }}/claim" class="mt-4">
                        @csrf
                        <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-medium text-xs py-2 rounded-lg transition">
                            Claim Delivery Task
                        </button>
                    </form>
                </div>
            @empty
                <p class="text-xs text-slate-400 py-6 text-center">No available unassigned delivery jobs in your sector right now.</p>
            @endforelse
        </div>
    </div>

    <!-- Active Claimed Tasks -->
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <h2 class="font-bold text-base text-slate-900 mb-4">My In-Progress Deliveries</h2>
        <div class="space-y-4">
            @forelse($activeJobs ?? [] as $active)
                <div class="p-4 border-l-4 border-l-amber-500 border border-slate-200 rounded-lg">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-semibold text-xs text-slate-800">Job #{{ $active->id }}</span>
                        <span class="text-xs uppercase font-mono font-bold text-amber-700">{{ $active->status }}</span>
                    </div>
                    <div class="text-xs text-slate-600 space-y-1">
                        <div>Pickup OTP: <span class="font-mono font-bold text-slate-900">{{ $active->pickup_otp ?? '4921' }}</span></div>
                    </div>
                    <a href="/tracking/{{ $active->id }}" class="mt-3 block text-center text-xs font-semibold text-teal-600 hover:underline">
                        Open Live GPS Navigation &rarr;
                    </a>
                </div>
            @empty
                <p class="text-xs text-slate-400 py-6 text-center">You have no active claimed delivery tasks.</p>
            @endforelse
        </div>
    </div>
</div>
@endsection

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

@section('title', 'Delivery Job Queue | Meramot')

@section('content')
@php
    use App\Services\DeliveryLifecycleService;

    $availableList = $availableJobs ?? collect();
    $activeList = $activeJobs ?? collect();
    $completedList = $completedJobs ?? collect();
    $currentTab = $tab ?? 'all';
    $canAct = (bool) ($isApprovedAgent ?? false);
@endphp

<div class="space-y-6">
    <!-- Page Header -->
    <section class="rounded-3xl border border-[#d9e5d5] bg-white p-5 sm:p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-[#163625]/65">Courier Dispatch</p>
                <h1 class="mt-1 text-2xl font-extrabold text-[#163625]">Delivery Job Queue</h1>
                <p class="mt-1 text-xs sm:text-sm text-[#163625]/70">
                    Accept open pickup or return tasks and keep handover milestones synchronized.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="/delivery" class="rounded-full border border-[#d9e5d5] bg-[#f8fbf6] px-4 py-2 text-xs font-bold text-[#163625] hover:bg-[#eef4ea] transition">
                    Dashboard
                </a>
                <a href="/delivery/map" class="inline-flex items-center gap-1.5 rounded-full bg-[#163625] px-4 py-2 text-xs font-bold text-[#E4FCD5] hover:opacity-90 transition">
                    <i class="fa-solid fa-map-location-dot"></i>
                    <span>Live Map</span>
                </a>
            </div>
        </div>
    </section>

    @if(!$canAct)
        <section class="rounded-2xl border border-amber-300 bg-amber-50/95 px-4 py-3.5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start gap-3 min-w-0">
                <i class="fa-solid fa-shield-halved text-amber-800 mt-0.5"></i>
                <div class="min-w-0 text-xs text-amber-950">
                    <p class="font-bold">Read-Only Queue Preview</p>
                    <p class="text-amber-900/85">Sign in with an approved <span class="font-mono">@meramotbd.com</span> courier account to accept jobs and verify handover codes.</p>
                </div>
            </div>
            <a href="/delivery/login" class="shrink-0 rounded-full bg-[#163625] px-4 py-2 text-xs font-bold text-[#E4FCD5] hover:opacity-90 transition text-center">
                Courier Sign In
            </a>
        </section>
    @endif

    <!-- Compact Summary KPIs -->
    <section class="grid grid-cols-1 sm:grid-cols-3 gap-3.5">
        <div class="rounded-2xl border border-[#d9e5d5] bg-white p-4 shadow-sm min-w-0">
            <p class="text-xs font-semibold text-[#163625]/65">Available to Accept</p>
            <p class="mt-1 text-2xl font-extrabold text-[#163625]">{{ $availableList->count() }}</p>
        </div>
        <div class="rounded-2xl border border-[#d9e5d5] bg-white p-4 shadow-sm min-w-0">
            <p class="text-xs font-semibold text-[#163625]/65">Active Trips</p>
            <p class="mt-1 text-2xl font-extrabold text-[#163625]">{{ $activeList->count() }}</p>
        </div>
        <div class="rounded-2xl border border-[#d9e5d5] bg-white p-4 shadow-sm min-w-0">
            <p class="text-xs font-semibold text-[#163625]/65">Completed Trips</p>
            <p class="mt-1 text-2xl font-extrabold text-emerald-700">{{ $completedList->count() }}</p>
        </div>
    </section>

    <!-- Queue Filter Tabs -->
    <div class="flex flex-wrap items-center gap-2">
        @php
            $tabLinks = [
                ['key' => 'all', 'label' => 'All Tasks'],
                ['key' => 'pool', 'label' => 'Available (' . $availableList->count() . ')'],
                ['key' => 'active', 'label' => 'Active (' . $activeList->count() . ')'],
                ['key' => 'completed', 'label' => 'Completed (' . $completedList->count() . ')'],
            ];
        @endphp
        @foreach($tabLinks as $t)
            <a
                href="/delivery/jobs-queue?tab={{ $t['key'] }}"
                class="rounded-full px-4 py-2 text-xs font-bold border transition {{ $currentTab === $t['key'] ? 'bg-[#163625] text-[#E4FCD5] border-[#163625]' : 'bg-white text-[#163625] border-[#d9e5d5] hover:bg-[#eef4ea]' }}"
            >
                {{ $t['label'] }}
            </a>
        @endforeach
    </div>

    <!-- Section 1: Open Dispatch Pool -->
    @if(in_array($currentTab, ['all', 'pool'], true))
        <section class="rounded-3xl border border-[#d9e5d5] bg-white p-5 sm:p-6 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-[#163625]">Available Pickup &amp; Return Tasks</h2>
                    <p class="text-xs text-[#163625]/70">Claim an unassigned job to lock dispatch and schedule courier pickup.</p>
                </div>
                <span class="rounded-full border border-[#d9e5d5] bg-[#eef4ea] px-3 py-1 font-mono text-xs font-bold text-[#163625]">
                    {{ $availableList->count() }} Open
                </span>
            </div>

            @if($availableList->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#d9e5d5] bg-[#f8fbf6] p-6 text-center">
                    <p class="text-xs font-semibold text-[#163625]/70">No unassigned pickup or return jobs waiting right now.</p>
                </div>
            @else
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    @foreach($availableList as $job)
                        @php
                            $req = $job->repairRequest ?? $job->repairJob?->repairRequest;
                            $shop = $job->repairJob?->shop;
                            $isReturn = strtoupper((string) $job->direction) === 'TO_CUSTOMER';
                            $isExpress = strtoupper((string) $job->type) === 'EXPRESS';
                        @endphp
                        <article class="rounded-2xl border border-[#d9e5d5] bg-[#f8fbf6] p-4 flex flex-col justify-between gap-4 min-w-0">
                            <div class="space-y-3 min-w-0">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                                        <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold border {{ $isReturn ? 'bg-sky-50 text-sky-900 border-sky-200' : 'bg-[#eef4ea] text-[#163625] border-[#d9e5d5]' }}">
                                            {{ $isReturn ? 'Shop → Customer' : 'Customer → Shop' }}
                                        </span>
                                        <span class="rounded-full px-2 py-0.5 text-[10px] font-bold border {{ $isExpress ? 'bg-amber-100 text-amber-900 border-amber-300' : 'bg-white text-[#163625]/75 border-[#d9e5d5]' }}">
                                            {{ $isExpress ? 'Express' : 'Standard' }}
                                        </span>
                                        @if($job->tracking_code)
                                            <span class="font-mono text-[11px] text-[#163625]/60">{{ $job->tracking_code }}</span>
                                        @endif
                                    </div>
                                    <div class="text-right shrink-0">
                                        <span class="rounded-full bg-emerald-100 border border-emerald-300 px-2.5 py-0.5 text-xs font-extrabold text-emerald-900">
                                            BDT {{ number_format((float) ($job->fee ?? 120), 0) }}
                                        </span>
                                        @if($job->distance_km)
                                            <p class="mt-0.5 text-[10px] font-semibold text-[#163625]/60">{{ number_format((float) $job->distance_km, 1) }} km est.</p>
                                        @endif
                                    </div>
                                </div>

                                <div class="min-w-0">
                                    <h3 class="text-sm font-bold text-[#163625] break-words">
                                        {{ $req?->title ?? ('Delivery Task #' . $job->id) }}
                                    </h3>
                                    @if($shop?->name)
                                        <p class="text-xs text-[#163625]/70 mt-0.5 break-words">Workshop: {{ $shop->name }}</p>
                                    @endif
                                </div>

                                <div class="rounded-xl border border-[#d9e5d5] bg-white p-3 space-y-1.5 text-xs text-[#163625]/80">
                                    <p class="break-words"><strong class="text-[#163625]">Pickup:</strong> {{ $job->pickup_address }}</p>
                                    <p class="break-words"><strong class="text-[#163625]">Dropoff:</strong> {{ $job->dropoff_address }}</p>
                                </div>
                            </div>

                            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                                @if($canAct)
                                    <form method="POST" action="/delivery/jobs/{{ $job->id }}/claim" class="flex-1 min-w-[140px]">
                                        @csrf
                                        <button type="submit" class="w-full rounded-full bg-[#163625] px-4 py-2.5 text-xs font-bold text-[#E4FCD5] hover:opacity-90 transition">
                                            Accept Job
                                        </button>
                                    </form>
                                @else
                                    <span class="text-[11px] font-semibold text-[#163625]/60">Courier sign-in required to accept</span>
                                @endif

                                @if($canAct)
                                    <a href="/delivery/order/{{ $job->id }}" class="rounded-full border border-[#d9e5d5] bg-white px-3.5 py-2 text-xs font-bold text-[#163625] hover:bg-[#eef4ea] transition">
                                        Details
                                    </a>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <!-- Section 2: Active Assigned Tasks & Milestone Synchronization -->
    @if(in_array($currentTab, ['all', 'active'], true))
        <section class="rounded-3xl border border-[#d9e5d5] bg-white p-5 sm:p-6 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-[#163625]">Active Tasks &amp; Milestone Sync</h2>
                    <p class="text-xs text-[#163625]/70">Update custody status from Pickup Scheduled through At Shop, Out for Delivery, and Delivered.</p>
                </div>
                <span class="rounded-full border border-[#d9e5d5] bg-[#eef4ea] px-3 py-1 font-mono text-xs font-bold text-[#163625]">
                    {{ $activeList->count() }} Active
                </span>
            </div>

            @if($activeList->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#d9e5d5] bg-[#f8fbf6] p-6 text-center space-y-3">
                    <p class="text-xs font-semibold text-[#163625]/70">No active deliveries assigned to your account right now.</p>
                    <a href="/delivery/earnings" class="inline-flex items-center gap-1.5 rounded-full border border-[#d9e5d5] bg-white px-4 py-2 text-xs font-bold text-[#163625] hover:bg-[#eef4ea] transition">
                        <i class="fa-solid fa-wallet"></i>
                        <span>View Earnings Ledger</span>
                    </a>
                </div>
            @else
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
                    @foreach($activeList as $active)
                        @php
                            $req = $active->repairRequest ?? $active->repairJob?->repairRequest;
                            $shop = $active->repairJob?->shop;
                            $cust = $req?->customer;
                            $isReturn = strtoupper((string) $active->direction) === 'TO_CUSTOMER';
                            $milestoneLabel = DeliveryLifecycleService::milestoneLabel($active);
                            $stages = DeliveryLifecycleService::canonicalStages($active);
                            $nextAction = DeliveryLifecycleService::nextQueueAction($active);
                            $statusUpper = strtoupper((string) $active->status);
                            $badgeClass = match ($statusUpper) {
                                'IN_TRANSIT', 'PICKED_UP' => 'bg-sky-100 text-sky-900 border-sky-300',
                                'DISPATCHED', 'SCHEDULED' => 'bg-amber-100 text-amber-900 border-amber-300',
                                default => 'bg-white text-[#163625] border-[#d9e5d5]',
                            };
                        @endphp
                        <article class="rounded-2xl border border-[#d9e5d5] border-l-4 border-l-[#163625] bg-[#f8fbf6] p-4 space-y-3.5 min-w-0">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-1.5 min-w-0">
                                    <span class="rounded-full px-2.5 py-0.5 text-[10px] font-bold border {{ $isReturn ? 'bg-sky-50 text-sky-900 border-sky-200' : 'bg-[#eef4ea] text-[#163625] border-[#d9e5d5]' }}">
                                        {{ $isReturn ? 'Return Delivery' : 'Inbound Pickup' }}
                                    </span>
                                    @if($active->tracking_code)
                                        <span class="font-mono text-[11px] font-semibold text-[#163625]/70">{{ $active->tracking_code }}</span>
                                    @endif
                                </div>
                                <span class="shrink-0 rounded-full border px-2.5 py-0.5 text-[11px] font-bold {{ $badgeClass }}">
                                    {{ $milestoneLabel }}
                                </span>
                            </div>

                            <div class="min-w-0">
                                <h3 class="text-sm font-bold text-[#163625] break-words">
                                    {{ $req?->title ?? ('Delivery Trip #' . $active->id) }}
                                </h3>
                                <p class="mt-0.5 text-xs text-[#163625]/70 break-words">
                                    @if($shop?->name)
                                        <span>{{ $shop->name }}</span>
                                    @endif
                                    @if($cust?->name)
                                        <span> &bull; {{ $cust->name }}</span>
                                    @endif
                                    <span> &bull; Fee: <strong>BDT {{ number_format((float) ($active->fee ?? 0), 0) }}</strong></span>
                                </p>
                            </div>

                            <!-- 4-Step Canonical Milestone Bar -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 xl:grid-cols-2 2xl:grid-cols-4 gap-1.5">
                                @foreach($stages as $stage)
                                    <div class="rounded-lg border px-2.5 py-1.5 text-center {{ $stage['state'] === 'done' ? 'bg-emerald-50 border-emerald-200 text-emerald-900' : ($stage['state'] === 'current' ? 'bg-[#163625] border-[#163625] text-[#E4FCD5]' : 'bg-white border-[#d9e5d5] text-[#163625]/55') }}">
                                        <p class="text-[10px] font-bold leading-tight">{{ $stage['label'] }}</p>
                                    </div>
                                @endforeach
                            </div>

                            <!-- Addresses -->
                            <div class="rounded-xl border border-[#d9e5d5] bg-white p-3 space-y-1 text-xs text-[#163625]/80">
                                <p class="break-words"><strong class="text-[#163625]">Pickup:</strong> {{ $active->pickup_address }}</p>
                                <p class="break-words"><strong class="text-[#163625]">Dropoff:</strong> {{ $active->dropoff_address }}</p>
                            </div>

                            <!-- Inline Milestone Advance Form -->
                            @if($canAct && $nextAction)
                                <form method="POST" action="/delivery/jobs/{{ $active->id }}/milestone" class="rounded-xl border border-[#d9e5d5] bg-white p-3 space-y-2.5">
                                    @csrf
                                    <input type="hidden" name="milestone" value="{{ $nextAction['target'] }}">

                                    @if($nextAction['requires_otp'])
                                        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
                                            <span class="font-semibold text-[#163625]">{{ $nextAction['otp_prompt'] }}</span>
                                            @if($nextAction['demo_otp'])
                                                <span class="rounded bg-[#eef4ea] px-2 py-0.5 font-mono font-bold text-[#163625] border border-[#d9e5d5]">Code: {{ $nextAction['demo_otp'] }}</span>
                                            @endif
                                        </div>
                                        <div class="flex flex-col sm:flex-row gap-2">
                                            <input
                                                type="text"
                                                name="otp"
                                                inputmode="numeric"
                                                pattern="[0-9]{4}"
                                                maxlength="4"
                                                required
                                                placeholder="4-digit OTP"
                                                class="w-full sm:w-32 rounded-xl border border-[#c8dac2] bg-[#f8fbf6] px-3 py-2 text-center font-mono text-sm font-bold tracking-widest text-[#163625] outline-none focus:border-[#163625]"
                                            >
                                            <button type="submit" class="flex-1 rounded-xl bg-[#163625] px-4 py-2 text-xs font-bold text-[#E4FCD5] hover:opacity-90 transition">
                                                {{ $nextAction['label'] }}
                                            </button>
                                        </div>
                                    @else
                                        <button type="submit" class="w-full rounded-xl bg-[#163625] px-4 py-2.5 text-xs font-bold text-[#E4FCD5] hover:opacity-90 transition">
                                            {{ $nextAction['label'] }} &rarr;
                                        </button>
                                    @endif
                                </form>
                            @endif

                            <div class="flex flex-wrap items-center justify-between gap-2 pt-0.5 text-xs">
                                <a href="/delivery/order/{{ $active->id }}" class="font-bold text-[#163625] hover:underline">
                                    Trip Timeline &amp; Chat &rarr;
                                </a>
                                <a href="/tracking/{{ $active->id }}" class="font-semibold text-[#163625]/75 hover:text-[#163625] hover:underline">
                                    <i class="fa-solid fa-location-dot mr-1"></i>Live Map
                                </a>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <!-- Section 3: Recent Completed Trips -->
    @if(in_array($currentTab, ['all', 'completed'], true) && ($currentTab === 'completed' || $completedList->isNotEmpty()))
        <section class="rounded-3xl border border-[#d9e5d5] bg-white p-5 sm:p-6 shadow-sm space-y-4">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div>
                    <h2 class="text-base sm:text-lg font-bold text-[#163625]">Completed Handovers</h2>
                    <p class="text-xs text-[#163625]/70">Verified deliveries with settled rider trip fees.</p>
                </div>
            </div>

            @if($completedList->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#d9e5d5] bg-[#f8fbf6] p-6 text-center">
                    <p class="text-xs font-semibold text-[#163625]/70">No completed delivery trips yet.</p>
                </div>
            @else
                <div class="divide-y divide-[#d9e5d5]">
                    @foreach($completedList as $done)
                        @php $req = $done->repairRequest ?? $done->repairJob?->repairRequest; @endphp
                        <div class="py-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs">
                            <div class="min-w-0">
                                <p class="font-bold text-[#163625] break-words">{{ $req?->title ?? ('Delivery #' . $done->id) }}</p>
                                <p class="text-[#163625]/65">
                                    {{ $done->tracking_code ?: ('Trip #' . $done->id) }}
                                    &bull; {{ DeliveryLifecycleService::milestoneLabel($done) }}
                                    @if($done->delivered_at)
                                        &bull; {{ $done->delivered_at->diffForHumans() }}
                                    @endif
                                </p>
                            </div>
                            <div class="flex items-center gap-3 shrink-0">
                                <span class="rounded-full bg-emerald-100 border border-emerald-300 px-2.5 py-0.5 font-bold text-emerald-900">
                                    {{ DeliveryLifecycleService::milestoneLabel($done) }}
                                </span>
                                <span class="font-extrabold text-[#163625]">BDT {{ number_format((float) ($done->fee ?? 0), 0) }}</span>
                                <a href="/delivery/order/{{ $done->id }}" class="font-bold text-[#163625] hover:underline">View</a>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
@endsection

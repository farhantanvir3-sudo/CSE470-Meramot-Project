@extends('layouts.app')

@section('title', 'Track Repair Request #' . $repairRequest->id . ' | Meramot')

{{-- ==========================================================================
     FEATURE R7: Customer Repair Request Lifecycle & Dual-Quote Tracking
     Assigned Member: Shanid Sajjatuz Islam [23201465]
     Merged Dual-Path & Dual-Quote Flow: Farhan Tanvir [22299179]
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 md:px-6">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>›</span>
        <a href="/requests/create" class="hover:underline">Repair Requests</a>
        <span>›</span>
        <span class="text-[var(--foreground)] font-semibold">Track Request #{{ $repairRequest->id }}</span>
    </div>

    <!-- Feature Badge -->
    <div class="mb-4">
        <span class="dev-only-badge text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block">
            Feature R7 • Shanid Sajjatuz Islam [23201465]
        </span>
    </div>

    <!-- Page Header -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">
                Track Your Repair Request
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Monitor real-time status updates, Quote 1 marketplace bids, and Quote 2 bench diagnostics
            </p>
        </div>

        @php
            $currentStatus = strtoupper($repairRequest->status ?? 'PENDING');
            if ($currentStatus === 'BIDDING_OPEN') {
                $currentStatus = 'BIDDING';
            }
        @endphp

        @if(in_array($currentStatus, ['PENDING', 'BIDDING', 'ASSIGNED', 'PICKUP_SCHEDULED'], true))
            <form method="POST" action="{{ route('requests.cancel', $repairRequest->id) }}">
                @csrf
                <button type="submit" class="rounded-full border border-rose-300 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-800 hover:bg-rose-100 transition">
                    Cancel Request
                </button>
            </form>
        @endif
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column: Request Details, Dual-Quote Actions & Status -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Direct-Order Workshop Banner (Path B: Cart Direct Order) -->
            @if(($repairRequest->source ?? '') === 'DIRECT_SERVICE' && $repairRequest->requestedShop)
                <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--mint-100)] p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5">
                        <div class="h-12 w-12 rounded-2xl bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center font-black text-lg shrink-0">
                            <i class="fa-solid fa-store"></i>
                        </div>
                        <div>
                            <span class="text-[10px] font-extrabold uppercase tracking-wider text-[var(--accent-dark)]">
                                Direct-Order Cart Dispatch
                            </span>
                            <h3 class="text-base font-black text-[var(--foreground)]">{{ $repairRequest->requestedShop->name }}</h3>
                            <p class="text-xs text-[var(--muted-foreground)]">
                                @if($currentStatus === 'PENDING')
                                    Sent to workshop's <strong>Direct Orders</strong> queue — awaiting merchant acceptance.
                                @elseif($currentStatus === 'REJECTED')
                                    Declined by workshop — escrow authorization automatically refunded.
                                @else
                                    Accepted by workshop — courier & bench workflow active.
                                @endif
                            </p>
                        </div>
                    </div>
                    <a href="/vendor/dashboard" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition whitespace-nowrap">
                        Open Vendor Portal &rarr;
                    </a>
                </div>
            @endif

            <!-- Quote 2: Final Physical Diagnosis Approval Card (WAITING_APPROVAL) -->
            @if($currentStatus === 'WAITING_APPROVAL')
                <div class="rounded-[2rem] border-2 border-amber-400 bg-amber-50/90 p-6 shadow-sm space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <span class="rounded-full bg-amber-200 px-3 py-0.5 text-[10px] font-extrabold uppercase text-amber-900">
                                Quote 2 • Action Required
                            </span>
                            <h3 class="mt-1 text-lg font-black text-[#203126]">Bench Diagnosis Complete — Approve Final Quote</h3>
                            <p class="mt-1 text-xs text-amber-950/80 leading-relaxed">
                                {{ $repairRequest->repairJob->diagnosis_notes ?? 'Physical bench inspection completed. Please review the final component & labor quote below before soldering/repair proceeds.' }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[10px] uppercase font-bold text-amber-900 block">Final Quote</span>
                            <span class="text-2xl font-black text-[#244229]">
                                ৳{{ number_format($repairRequest->repairJob->final_quoted_amount ?? $repairRequest->quoted_final_amount ?? 0, 2) }}
                            </span>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-3 pt-2">
                        <form method="POST" action="{{ route('requests.respond-final-quote', $repairRequest->id) }}">
                            @csrf
                            <input type="hidden" name="decision" value="approve">
                            <button type="submit" class="rounded-full bg-[#244229] px-5 py-2.5 text-xs font-bold text-white shadow hover:opacity-90 transition">
                                Approve Quote & Start Repair
                            </button>
                        </form>
                        <form method="POST" action="{{ route('requests.respond-final-quote', $repairRequest->id) }}">
                            @csrf
                            <input type="hidden" name="decision" value="decline">
                            <button type="submit" class="rounded-full border border-rose-300 bg-white px-5 py-2.5 text-xs font-bold text-rose-800 hover:bg-rose-50 transition">
                                Decline Quote & Schedule Device Return
                            </button>
                        </form>
                    </div>
                </div>
            @endif

            <!-- Status Progress Timeline -->
            <div class="meramot-card p-6 shadow-sm">
                <h2 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)] mb-4">
                    Repair Lifecycle Status
                </h2>

                @php
                    $statuses = [
                        'DRAFT' => ['label' => 'Draft', 'icon' => 'fa-file-lines'],
                        'PENDING' => ['label' => 'Pending Workshop Review', 'icon' => 'fa-clock'],
                        'BIDDING' => ['label' => 'Bidding Open (Quote 1)', 'icon' => 'fa-gavel'],
                        'ASSIGNED' => ['label' => 'Vendor Assigned', 'icon' => 'fa-handshake'],
                        'PICKUP_SCHEDULED' => ['label' => 'Pickup Scheduled', 'icon' => 'fa-calendar-check'],
                        'PICKED_UP' => ['label' => 'Device Picked Up', 'icon' => 'fa-box'],
                        'AT_SHOP' => ['label' => 'At Repair Shop', 'icon' => 'fa-store'],
                        'DIAGNOSING' => ['label' => 'Bench Diagnosing', 'icon' => 'fa-magnifying-glass'],
                        'WAITING_APPROVAL' => ['label' => 'Awaiting Quote 2 Approval', 'icon' => 'fa-hourglass-half'],
                        'REPAIRING' => ['label' => 'Under Repair', 'icon' => 'fa-wrench'],
                        'RETURN_SCHEDULED' => ['label' => 'Return Courier Scheduled', 'icon' => 'fa-truck'],
                        'RETURNING' => ['label' => 'Out for Return Delivery', 'icon' => 'fa-truck-fast'],
                        'RETURNED_TO_CUSTOMER' => ['label' => 'Returned Without Repair', 'icon' => 'fa-rotate-left'],
                        'COMPLETED' => ['label' => 'Completed', 'icon' => 'fa-circle-check'],
                        'CANCELLED' => ['label' => 'Cancelled', 'icon' => 'fa-circle-xmark'],
                        'REJECTED' => ['label' => 'Declined by Workshop', 'icon' => 'fa-ban'],
                    ];

                    $statusInfo = $statuses[$currentStatus] ?? ['label' => $currentStatus, 'icon' => 'fa-circle'];
                    $progressSteps = ($repairRequest->source ?? '') === 'DIRECT_SERVICE'
                        ? ['PENDING', 'ASSIGNED', 'AT_SHOP', 'DIAGNOSING', 'REPAIRING', 'COMPLETED']
                        : ['PENDING', 'BIDDING', 'ASSIGNED', 'AT_SHOP', 'REPAIRING', 'COMPLETED'];
                    $currentIndex = array_search($currentStatus, $progressSteps, true);
                    if ($currentIndex === false) {
                        $currentIndex = in_array($currentStatus, ['PICKUP_SCHEDULED', 'PICKED_UP'], true) ? 2 : (in_array($currentStatus, ['WAITING_APPROVAL'], true) ? 3 : -1);
                    }
                @endphp

                <!-- Current Status Badge -->
                <div class="mb-6 flex items-center gap-3 p-4 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)]">
                    <div class="h-12 w-12 rounded-xl bg-[var(--accent-dark)] flex items-center justify-center text-[var(--accent-foreground)] shrink-0">
                        <i class="fa-solid {{ $statusInfo['icon'] }} text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider text-[var(--muted-foreground)] font-bold">Current Status</p>
                        <h3 class="text-lg font-black text-[var(--foreground)]">{{ $statusInfo['label'] }}</h3>
                        <p class="text-xs text-[var(--muted-foreground)]">
                            Last updated: {{ $repairRequest->updated_at?->diffForHumans() ?? 'just now' }}
                        </p>
                    </div>
                </div>

                <!-- Progress Steps -->
                <div class="space-y-3">
                    @foreach($progressSteps as $index => $step)
                        @php
                            $stepInfo = $statuses[$step];
                            $isCompleted = $index < $currentIndex || $currentStatus === 'COMPLETED';
                            $isCurrent = $step === $currentStatus;
                        @endphp
                        <div class="flex items-center gap-3">
                            <div class="h-10 w-10 rounded-full flex items-center justify-center shrink-0 {{ $isCompleted ? 'bg-emerald-600 text-white' : ($isCurrent ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)]' : 'bg-[var(--mint-200)] text-[var(--muted-foreground)]') }}">
                                @if($isCompleted)
                                    <i class="fa-solid fa-check text-sm"></i>
                                @else
                                    <i class="fa-solid {{ $stepInfo['icon'] }} text-sm"></i>
                                @endif
                            </div>

                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold {{ $isCurrent ? 'text-[var(--accent-dark)]' : ($isCompleted ? 'text-emerald-700' : 'text-[var(--muted-foreground)]') }}">
                                    {{ $stepInfo['label'] }}
                                </p>
                                <p class="text-xs text-[var(--muted-foreground)]">
                                    {{ $isCurrent ? 'In progress' : ($isCompleted ? 'Completed' : 'Upcoming') }}
                                </p>
                            </div>

                            @if($isCurrent)
                                <span class="px-2.5 py-1 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-[10px] font-bold uppercase">
                                    Active
                                </span>
                            @endif
                        </div>

                        @if(!$loop->last)
                            <div class="ml-5 h-5 w-0.5 {{ $index < $currentIndex ? 'bg-emerald-600' : 'bg-[var(--border)]' }}"></div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Device Information Card -->
            <div class="meramot-card p-6 shadow-sm">
                <h2 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)] mb-4">
                    Device & Service Information
                </h2>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold mb-1">Device Type</p>
                        <p class="text-sm font-semibold text-[var(--foreground)]">{{ $repairRequest->device_type }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold mb-1">Brand / Workshop</p>
                        <p class="text-sm font-semibold text-[var(--foreground)]">{{ $repairRequest->brand }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold mb-1">Model</p>
                        <p class="text-sm font-semibold text-[var(--foreground)]">{{ $repairRequest->model_name }}</p>
                    </div>
                    @if($repairRequest->serial_number)
                    <div class="col-span-2">
                        <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold mb-1">Serial Number</p>
                        <p class="text-sm font-mono text-[var(--foreground)]">{{ $repairRequest->serial_number }}</p>
                    </div>
                    @endif
                </div>

                <div class="mt-4 pt-4 border-t border-[var(--border)]">
                    <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold mb-1">Issue / Order Details</p>
                    <h3 class="text-base font-bold text-[var(--foreground)] mb-2">{{ $repairRequest->issue_title }}</h3>
                    <p class="text-xs sm:text-sm text-[var(--muted-foreground)] leading-relaxed whitespace-pre-line">{{ $repairRequest->issue_description }}</p>
                </div>

                @if($repairRequest->photos && count($repairRequest->photos) > 0)
                <div class="mt-4 pt-4 border-t border-[var(--border)]">
                    <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold mb-3">Attached Photos</p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($repairRequest->photos as $photo)
                            <a href="{{ asset('storage/' . $photo) }}" target="_blank" class="block">
                                <img src="{{ asset('storage/' . $photo) }}" alt="Device photo" class="w-full h-24 object-cover rounded-xl border border-[var(--border)] hover:opacity-80 transition">
                            </a>
                        @endforeach
                    </div>
                </div>
                @endif

                @if($repairRequest->ai_diagnostic_summary)
                <div class="mt-4 pt-4 border-t border-[var(--border)]">
                    <div class="flex items-center gap-2 mb-2">
                        <i class="fa-solid fa-wand-magic-sparkles text-amber-600"></i>
                        <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold">AI Diagnostic Summary</p>
                    </div>
                    <p class="text-xs sm:text-sm text-[var(--foreground)] bg-[var(--mint-100)] border border-[var(--border)] p-3.5 rounded-2xl">
                        {{ $repairRequest->ai_diagnostic_summary }}
                    </p>
                </div>
                @endif
            </div>

            <!-- Vendor Bids Section (Path A: Marketplace Quote 1) -->
            @if(in_array($currentStatus, ['BIDDING', 'ASSIGNED'], true) && ($repairRequest->source ?? 'MARKETPLACE') !== 'DIRECT_SERVICE')
            <div class="meramot-card p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)]">
                        Vendor Bids ({{ $repairRequest->bids->count() }})
                    </h2>
                    @if($currentStatus === 'BIDDING')
                        <span class="px-3 py-1 rounded-full bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] text-xs font-bold">
                            Bidding Open
                        </span>
                    @endif
                </div>

                @if($repairRequest->bids->count() > 0)
                    <div class="space-y-3">
                        @foreach($repairRequest->bids as $bid)
                            <div class="p-4 rounded-2xl border border-[var(--border)] {{ $bid->id === $repairRequest->selected_bid_id ? 'bg-[var(--mint-100)] border-emerald-600' : 'bg-[var(--mint-100)]/40' }}">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-sm font-bold text-[var(--foreground)]">
                                            {{ $bid->shop->name ?? ('Vendor #' . ($bid->shop_id ?? $bid->vendor_id)) }}
                                        </h3>
                                        <p class="text-xs text-[var(--muted-foreground)]">
                                            {{ $bid->created_at?->diffForHumans() }}
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="text-lg font-black text-[var(--accent-dark)]">
                                            ৳{{ number_format($bid->proposed_cost, 2) }} ({{ (int) $bid->proposed_cost }})
                                        </p>
                                        <p class="text-xs text-[var(--muted-foreground)]">
                                            {{ $bid->estimated_days }} days
                                        </p>
                                    </div>
                                </div>

                                @if($bid->technician_note)
                                <p class="text-xs text-[var(--muted-foreground)] mb-3">
                                    {{ $bid->technician_note }}
                                </p>
                                @endif

                                <div class="flex flex-wrap items-center gap-2 text-xs">
                                    <span class="px-2.5 py-1 rounded-full bg-[var(--card)] border border-[var(--border)] text-[var(--foreground)]">
                                        Parts: ৳{{ number_format($bid->parts_cost ?? 0, 2) }}
                                    </span>
                                    <span class="px-2.5 py-1 rounded-full bg-[var(--card)] border border-[var(--border)] text-[var(--foreground)]">
                                        Labor: ৳{{ number_format($bid->labor_cost ?? 0, 2) }}
                                    </span>
                                    <span class="px-2.5 py-1 rounded-full bg-[var(--card)] border border-[var(--border)] text-[var(--foreground)]">
                                        Warranty: {{ $bid->warranty_days ?? 30 }} days
                                    </span>
                                </div>

                                @if($bid->id === $repairRequest->selected_bid_id)
                                    <div class="mt-3 flex items-center gap-2 text-emerald-700">
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span class="text-xs font-bold">Accepted Bid (Quote 1)</span>
                                    </div>
                                @elseif($currentStatus === 'BIDDING')
                                    <form method="POST" action="{{ route('requests.accept-bid', $repairRequest->id) }}" class="mt-3">
                                        @csrf
                                        <input type="hidden" name="bid_id" value="{{ $bid->id }}">
                                        <button type="submit" class="w-full py-2.5 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-xs font-bold hover:opacity-90 transition shadow-sm">
                                            Accept This Bid &rarr;
                                        </button>
                                    </form>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fa-solid fa-inbox text-3xl text-[var(--muted-foreground)] mb-3"></i>
                        <p class="text-sm text-[var(--muted-foreground)]">
                            No bids received yet. Verified workshops across Dhaka have been notified of your request.
                        </p>
                    </div>
                @endif
            </div>
            @endif

        </div>

        <!-- Right Column: Quick Actions & Summary -->
        <div class="space-y-6">

            <!-- Quick Actions Card -->
            <div class="meramot-card p-5 shadow-sm">
                <h3 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)] mb-4">
                    Quick Actions
                </h3>

                <div class="space-y-2.5">
                    @if(in_array($currentStatus, ['PICKED_UP', 'AT_SHOP', 'RETURNING'], true))
                        <a href="/tracking/{{ $repairRequest->id }}" class="block w-full py-2.5 rounded-full bg-[var(--accent-dark)] text-center text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                            <i class="fa-solid fa-location-dot mr-2"></i>Track Live Rider GPS
                        </a>
                    @endif

                    @if($currentStatus === 'COMPLETED' && ($repairRequest->requested_shop_id || $repairRequest->repairJob?->shop_id))
                        <a href="/reviews/create/{{ $repairRequest->requested_shop_id ?? $repairRequest->repairJob->shop_id }}" class="block w-full py-2.5 rounded-full bg-amber-500 text-center text-xs font-bold text-white hover:opacity-90 transition">
                            <i class="fa-solid fa-star mr-2"></i>Leave Verified Review
                        </a>
                    @endif

                    <a href="/ai-consultant/chat" class="block w-full py-2.5 rounded-full bg-[var(--mint-100)] border border-[var(--border)] text-center text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                        <i class="fa-solid fa-wand-magic-sparkles mr-2 text-amber-600"></i>Ask AI Consultant
                    </a>

                    <a href="/shops/explore" class="block w-full py-2.5 rounded-full bg-[var(--mint-100)] border border-[var(--border)] text-center text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                        <i class="fa-solid fa-store mr-2"></i>Browse Verified Shops
                    </a>
                </div>
            </div>

            <!-- Request Summary Card -->
            <div class="meramot-card p-5 shadow-sm">
                <h3 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)] mb-4">
                    Request Summary
                </h3>

                <div class="space-y-3 text-xs">
                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Request ID</span>
                        <span class="font-bold text-[var(--foreground)]">#{{ $repairRequest->id }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Entry Flow</span>
                        <span class="font-bold text-[var(--accent-dark)]">
                            {{ ($repairRequest->source ?? 'MARKETPLACE') === 'DIRECT_SERVICE' ? 'Direct-Order Cart' : 'Marketplace Bidding' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Submitted</span>
                        <span class="font-bold text-[var(--foreground)]">{{ $repairRequest->created_at?->format('M d, Y') }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Logistics Mode</span>
                        <span class="font-bold text-[var(--foreground)] capitalize">
                            {{ str_replace('_', ' ', $repairRequest->preferred_service_type ?? 'Pickup & Dropoff') }}
                        </span>
                    </div>

                    @if($repairRequest->pickup_address)
                    <div class="pt-3 border-t border-[var(--border)]">
                        <p class="text-[var(--muted-foreground)] mb-1">Pickup Address</p>
                        <p class="font-semibold text-[var(--foreground)] leading-relaxed">
                            {{ $repairRequest->pickup_address }}
                        </p>
                    </div>
                    @endif

                    @if($repairRequest->quoted_final_amount)
                    <div class="pt-3 border-t border-[var(--border)]">
                        <div class="flex items-center justify-between">
                            <span class="text-[var(--muted-foreground)]">Total Amount</span>
                            <span class="text-lg font-black text-[var(--accent-dark)]">
                                ৳{{ number_format($repairRequest->quoted_final_amount, 2) }}
                            </span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Mint Support Card -->
            <div class="rounded-[1.5rem] border border-[var(--border)] bg-[var(--mint-100)] p-5 shadow-sm">
                <h3 class="text-sm font-bold text-[var(--foreground)] mb-2">
                    <i class="fa-solid fa-headset mr-2 text-[var(--accent-dark)]"></i>Need Escrow or Courier Help?
                </h3>
                <p class="text-xs text-[var(--muted-foreground)] mb-3">
                    Our Dhaka rider & escrow dispute desk monitors every verified repair job.
                </p>
                <a href="/support/rider-chat/1" class="block w-full py-2.5 rounded-full bg-[var(--accent-dark)] text-center text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                    Open Support Desk
                </a>
            </div>

        </div>

    </div>

</div>
@endsection

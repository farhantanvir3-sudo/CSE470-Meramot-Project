@extends('layouts.app')

@section('title', 'Track Repair Request #' . $repairRequest->id . ' | Meramot')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 md:px-6">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>/</span>
        <a href="/requests/create" class="hover:underline">Repair Requests</a>
        <span>/</span>
        <span class="text-[var(--foreground)] font-semibold">Track Request #{{ $repairRequest->id }}</span>
    </div>

    <!-- Feature Badge -->
    <div class="mb-6">
        <span class="dev-only-badge text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block">
            Feature R7 • Shanid Sajjatuz Islam [23201465]
        </span>
    </div>

    <!-- Page Header -->
    <div class="mb-6">
        <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)]">
            Track Your Repair Request
        </h1>
        <p class="text-xs text-[var(--muted-foreground)] mt-1">
            Monitor real-time status updates and vendor bids for your device repair
        </p>
    </div>

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column: Request Details & Status -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Status Progress Timeline -->
            <div class="meramot-card p-6 shadow-sm">
                <h2 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)] mb-4">
                    Repair Lifecycle Status
                </h2>

                @php
                    $statuses = [
                        'DRAFT' => ['label' => 'Draft', 'icon' => 'fa-file-lines', 'color' => 'gray'],
                        'PENDING' => ['label' => 'Pending Review', 'icon' => 'fa-clock', 'color' => 'yellow'],
                        'BIDDING' => ['label' => 'Bidding Open', 'icon' => 'fa-gavel', 'color' => 'blue'],
                        'ASSIGNED' => ['label' => 'Vendor Assigned', 'icon' => 'fa-handshake', 'color' => 'purple'],
                        'PICKUP_SCHEDULED' => ['label' => 'Pickup Scheduled', 'icon' => 'fa-calendar-check', 'color' => 'indigo'],
                        'PICKED_UP' => ['label' => 'Device Picked Up', 'icon' => 'fa-box', 'color' => 'cyan'],
                        'AT_SHOP' => ['label' => 'At Repair Shop', 'icon' => 'fa-store', 'color' => 'teal'],
                        'DIAGNOSING' => ['label' => 'Diagnosing', 'icon' => 'fa-magnifying-glass', 'color' => 'orange'],
                        'WAITING_APPROVAL' => ['label' => 'Awaiting Your Approval', 'icon' => 'fa-hourglass-half', 'color' => 'amber'],
                        'REPAIRING' => ['label' => 'Under Repair', 'icon' => 'fa-wrench', 'color' => 'emerald'],
                        'RETURN_SCHEDULED' => ['label' => 'Return Scheduled', 'icon' => 'fa-truck', 'color' => 'lime'],
                        'RETURNING' => ['label' => 'Out for Delivery', 'icon' => 'fa-shipping-fast', 'color' => 'green'],
                        'COMPLETED' => ['label' => 'Completed', 'icon' => 'fa-circle-check', 'color' => 'green'],
                        'CANCELLED' => ['label' => 'Cancelled', 'icon' => 'fa-circle-xmark', 'color' => 'red'],
                        'REJECTED' => ['label' => 'Rejected', 'icon' => 'fa-ban', 'color' => 'red'],
                    ];

                    $currentStatus = strtoupper($repairRequest->status ?? 'PENDING');
                    $statusInfo = $statuses[$currentStatus] ?? ['label' => $currentStatus, 'icon' => 'fa-circle', 'color' => 'gray'];
                @endphp

                <!-- Current Status Badge -->
                <div class="mb-6 flex items-center gap-3 p-4 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)]">
                    <div class="h-12 w-12 rounded-xl bg-[var(--accent-dark)] flex items-center justify-center text-white shrink-0">
                        <i class="fa-solid {{ $statusInfo['icon'] }} text-lg"></i>
                    </div>
                    <div class="min-w-0">
                        <p class="text-[10px] uppercase tracking-wider text-[var(--muted-foreground)] font-bold">Current Status</p>
                        <h3 class="text-lg font-black text-[var(--foreground)]">{{ $statusInfo['label'] }}</h3>
                        <p class="text-xs text-[var(--muted-foreground)]">
                            Last updated: {{ $repairRequest->updated_at->diffForHumans() }}
                        </p>
                    </div>
                </div>

                <!-- Progress Steps -->
                <div class="space-y-3">
                    @php
                        $progressSteps = ['PENDING', 'BIDDING', 'ASSIGNED', 'AT_SHOP', 'REPAIRING', 'COMPLETED'];
                        $currentIndex = array_search($currentStatus, $progressSteps);
                        if ($currentIndex === false) $currentIndex = -1;
                    @endphp

                    @foreach($progressSteps as $index => $step)
                        @php
                            $stepInfo = $statuses[$step];
                            $isCompleted = $index < $currentIndex;
                            $isCurrent = $step === $currentStatus;
                            $isPending = $index > $currentIndex;
                        @endphp
                        <div class="flex items-center gap-3">
                            <!-- Step Icon -->
                            <div class="h-10 w-10 rounded-full flex items-center justify-center shrink-0 {{ $isCompleted ? 'bg-emerald-500 text-white' : ($isCurrent ? 'bg-[var(--accent-dark)] text-white' : 'bg-gray-200 text-gray-400') }}">
                                @if($isCompleted)
                                    <i class="fa-solid fa-check text-sm"></i>
                                @else
                                    <i class="fa-solid {{ $stepInfo['icon'] }} text-sm"></i>
                                @endif
                            </div>

                            <!-- Step Info -->
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-bold {{ $isCurrent ? 'text-[var(--accent-dark)]' : ($isCompleted ? 'text-emerald-600' : 'text-gray-400') }}">
                                    {{ $stepInfo['label'] }}
                                </p>
                                @if($isCurrent)
                                    <p class="text-xs text-[var(--muted-foreground)]">In progress</p>
                                @elseif($isCompleted)
                                    <p class="text-xs text-emerald-600">Completed</p>
                                @else
                                    <p class="text-xs text-gray-400">Pending</p>
                                @endif
                            </div>

                            <!-- Status Badge -->
                            @if($isCurrent)
                                <span class="px-2 py-1 rounded-full bg-[var(--accent-dark)] text-white text-[10px] font-bold uppercase">
                                    Active
                                </span>
                            @endif
                        </div>

                        @if(!$loop->last)
                            <div class="ml-5 h-6 w-0.5 {{ $index < $currentIndex ? 'bg-emerald-500' : 'bg-gray-200' }}"></div>
                        @endif
                    @endforeach
                </div>
            </div>

            <!-- Device Information Card -->
            <div class="meramot-card p-6 shadow-sm">
                <h2 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)] mb-4">
                    Device Information
                </h2>

                <div class="grid grid-cols-2 md:grid-cols-3 gap-4">
                    <div>
                        <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold mb-1">Device Type</p>
                        <p class="text-sm font-semibold text-[var(--foreground)]">{{ $repairRequest->device_type }}</p>
                    </div>
                    <div>
                        <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold mb-1">Brand</p>
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
                    <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold mb-2">Issue Description</p>
                    <h3 class="text-base font-bold text-[var(--foreground)] mb-2">{{ $repairRequest->issue_title }}</h3>
                    <p class="text-sm text-[var(--muted-foreground)] leading-relaxed">
                        {{ $repairRequest->issue_description }}
                    </p>
                </div>

                @if($repairRequest->photos && count($repairRequest->photos) > 0)
                <div class="mt-4 pt-4 border-t border-[var(--border)]">
                    <p class="text-xs text-[var(--muted-foreground)] uppercase tracking-wider font-bold mb-3">Attached Photos</p>
                    <div class="grid grid-cols-3 gap-2">
                        @foreach($repairRequest->photos as $photo)
                            <a href="{{ asset('storage/' . $photo) }}" target="_blank" class="block">
                                <img src="{{ asset('storage/' . $photo) }}" alt="Device photo" class="w-full h-24 object-cover rounded-lg border border-[var(--border)] hover:opacity-75 transition">
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
                    <p class="text-sm text-[var(--foreground)] bg-amber-50 p-3 rounded-lg">
                        {{ $repairRequest->ai_diagnostic_summary }}
                    </p>
                </div>
                @endif
            </div>

            <!-- Vendor Bids Section -->
            @if(in_array($currentStatus, ['BIDDING', 'ASSIGNED']))
            <div class="meramot-card p-6 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)]">
                        Vendor Bids ({{ $repairRequest->bids->count() }})
                    </h2>
                    @if($currentStatus === 'BIDDING')
                        <span class="px-3 py-1 rounded-full bg-blue-100 text-blue-700 text-xs font-bold">
                            Bidding Open
                        </span>
                    @endif
                </div>

                @if($repairRequest->bids->count() > 0)
                    <div class="space-y-3">
                        @foreach($repairRequest->bids as $bid)
                            <div class="p-4 rounded-xl border border-[var(--border)] {{ $bid->id === $repairRequest->selected_bid_id ? 'bg-emerald-50 border-emerald-300' : 'bg-[var(--card)]' }}">
                                <div class="flex items-start justify-between gap-3 mb-3">
                                    <div class="flex-1 min-w-0">
                                        <h3 class="text-sm font-bold text-[var(--foreground)]">
                                            Vendor #{{ $bid->shop_id ?? $bid->vendor_id }}
                                        </h3>
                                        <p class="text-xs text-[var(--muted-foreground)]">
                                            {{ $bid->created_at->diffForHumans() }}
                                        </p>
                                    </div>
                                    <div class="text-right shrink-0">
                                        <p class="text-lg font-black text-[var(--accent-dark)]">
                                            ৳{{ number_format($bid->proposed_cost, 2) }}
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
                                    <span class="px-2 py-1 rounded bg-gray-100 text-gray-700">
                                        Parts: ৳{{ number_format($bid->parts_cost ?? 0, 2) }}
                                    </span>
                                    <span class="px-2 py-1 rounded bg-gray-100 text-gray-700">
                                        Labor: ৳{{ number_format($bid->labor_cost ?? 0, 2) }}
                                    </span>
                                    <span class="px-2 py-1 rounded bg-gray-100 text-gray-700">
                                        Warranty: {{ $bid->warranty_days ?? 30 }} days
                                    </span>
                                </div>

                                @if($bid->id === $repairRequest->selected_bid_id)
                                    <div class="mt-3 flex items-center gap-2 text-emerald-700">
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span class="text-xs font-bold">Accepted Bid</span>
                                    </div>
                                @elseif($currentStatus === 'BIDDING')
                                    <button class="mt-3 w-full py-2 rounded-lg bg-[var(--accent-dark)] text-white text-xs font-bold hover:opacity-90 transition">
                                        Accept This Bid
                                    </button>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="text-center py-8">
                        <i class="fa-solid fa-inbox text-4xl text-gray-300 mb-3"></i>
                        <p class="text-sm text-[var(--muted-foreground)]">
                            No bids received yet. Vendors have been notified of your request.
                        </p>
                    </div>
                @endif
            </div>
            @endif

        </div>

        <!-- Right Column: Quick Actions & Info -->
        <div class="space-y-6">

            <!-- Quick Actions Card -->
            <div class="meramot-card p-5 shadow-sm">
                <h3 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)] mb-4">
                    Quick Actions
                </h3>

                <div class="space-y-2">
                    @if($currentStatus === 'BIDDING')
                        <a href="/requests/{{ $repairRequest->id }}/edit" class="block w-full py-2.5 rounded-lg bg-gray-100 text-center text-xs font-bold text-[var(--foreground)] hover:bg-gray-200 transition">
                            <i class="fa-solid fa-pen mr-2"></i>Edit Request
                        </a>
                    @endif

                    @if(in_array($currentStatus, ['PICKED_UP', 'AT_SHOP', 'RETURNING']))
                        <a href="/tracking/{{ $repairRequest->id }}" class="block w-full py-2.5 rounded-lg bg-[var(--accent-dark)] text-center text-xs font-bold text-white hover:opacity-90 transition">
                            <i class="fa-solid fa-location-dot mr-2"></i>Track Live Location
                        </a>
                    @endif

                    @if($currentStatus === 'COMPLETED')
                        <a href="/reviews/create/{{ $repairRequest->selected_bid_id ?? 0 }}" class="block w-full py-2.5 rounded-lg bg-amber-500 text-center text-xs font-bold text-white hover:opacity-90 transition">
                            <i class="fa-solid fa-star mr-2"></i>Leave Review
                        </a>
                    @endif

                    <a href="/ai-consultant/chat" class="block w-full py-2.5 rounded-lg bg-purple-100 text-center text-xs font-bold text-purple-700 hover:bg-purple-200 transition">
                        <i class="fa-solid fa-wand-magic-sparkles mr-2"></i>Ask AI Consultant
                    </a>

                    <a href="/shops/explore" class="block w-full py-2.5 rounded-lg bg-gray-100 text-center text-xs font-bold text-[var(--foreground)] hover:bg-gray-200 transition">
                        <i class="fa-solid fa-store mr-2"></i>Browse Shops
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
                        <span class="text-[var(--muted-foreground)]">Submitted</span>
                        <span class="font-bold text-[var(--foreground)]">{{ $repairRequest->created_at->format('M d, Y') }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Service Type</span>
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
                            <span class="text-[var(--muted-foreground)]">Quoted Amount</span>
                            <span class="text-lg font-black text-[var(--accent-dark)]">
                                ৳{{ number_format($repairRequest->quoted_final_amount, 2) }}
                            </span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Support Card -->
            <div class="meramot-card p-5 shadow-sm bg-gradient-to-br from-blue-50 to-purple-50 border-blue-200">
                <h3 class="text-sm font-bold text-[var(--foreground)] mb-2">
                    <i class="fa-solid fa-headset mr-2 text-blue-600"></i>Need Help?
                </h3>
                <p class="text-xs text-[var(--muted-foreground)] mb-3">
                    Our support team is available 24/7 to assist you.
                </p>
                <a href="/support/chat" class="block w-full py-2.5 rounded-lg bg-blue-600 text-center text-xs font-bold text-white hover:opacity-90 transition">
                    Contact Support
                </a>
            </div>

        </div>

    </div>

</div>
@endsection

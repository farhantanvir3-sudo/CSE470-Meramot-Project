@extends('layouts.app')

@section('title', 'Track Repair Request #' . $repairRequest->id . ' | Meramot')

{{-- ==========================================================================
     FEATURE R7: Customer Repair Request Lifecycle & Dual-Quote Tracking
     Assigned Member: Shanid Sajjatuz Islam [23201465]
     Merged Dual-Path & Dual-Quote Flow: Farhan Tanvir [22299179]
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-5xl px-4 py-6 md:px-6" x-data="{ lightboxPhoto: null }">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>›</span>
        <a href="/requests" class="hover:underline">My Repair Requests</a>
        <span>›</span>
        <span class="text-[var(--foreground)] font-semibold">Track Request #{{ $repairRequest->id }}</span>
    </div>

    <!-- Feature Badge -->
    <div class="mb-4">
        <span class="dev-only-badge text-[10px] font-semibold uppercase px-2.5 py-1 rounded bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] inline-block">
            Feature R7 • Shanid Sajjatuz Islam [23201465]
        </span>
    </div>

    <!-- Page Header -->
    <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-xl md:text-2xl font-bold tracking-tight text-[var(--foreground)]">
                Track Your Repair Request
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Monitor live repair status and workshop quotes.
            </p>
        </div>

        @php
            $currentStatus = strtoupper($repairRequest->status ?? 'PENDING');
            if ($currentStatus === 'BIDDING_OPEN') {
                $currentStatus = 'BIDDING';
            }
            $viewer = auth()->user();
            $viewerRole = $viewer?->role ?? 'guest';
            $canActAsCustomer = !$viewer
                || (int) $viewer->id === (int) $repairRequest->customer_id
                || $viewerRole === 'admin';
            $ownsRequestedShop = $viewer
                && $viewerRole === 'vendor'
                && $repairRequest->requestedShop
                && (int) ($repairRequest->requestedShop->vendorProfile?->user_id ?? 0) === (int) $viewer->id;
            $orderInvoice = $repairRequest->invoices()->orderByDesc('id')->first();
            $orderPaidTxn = $repairRequest->payments()->where('status', 'PAID')->orderByDesc('id')->first();
        @endphp

        <div class="flex flex-wrap items-center gap-2">
            @if($viewer && $canActAsCustomer)
                @if($orderPaidTxn && $orderInvoice)
                    <a href="{{ route('invoices.show', $orderInvoice->id) }}" class="rounded-md bg-emerald-700 px-4 py-2 text-xs font-bold text-white hover:bg-emerald-800 transition shadow-sm">
                        <i class="fa-solid fa-file-invoice mr-1"></i> Official Invoice (Paid)
                    </a>
                @elseif(!in_array($currentStatus, ['CANCELLED', 'REJECTED'], true))
                    <a href="{{ route('payment.checkout', $repairRequest->id) }}" class="rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition shadow-sm">
                        <i class="fa-solid fa-shield-halved mr-1"></i> Pay Online Securely
                    </a>
                @endif
                <a href="{{ url('/tracking/' . $repairRequest->id) }}" class="rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                    <i class="fa-solid fa-location-dot mr-1 text-[var(--accent-dark)]"></i> Live GPS Map
                </a>
                <a href="{{ url('/support?order_id=' . $repairRequest->id) }}" class="rounded-md border border-amber-300 bg-amber-50 px-3.5 py-2 text-xs font-bold text-amber-950 hover:bg-amber-100 transition">
                    <i class="fa-solid fa-scale-balanced mr-1"></i> Dispute / Help
                </a>
            @endif

            @if($canActAsCustomer && in_array($currentStatus, ['PENDING', 'BIDDING', 'ASSIGNED', 'PICKUP_SCHEDULED'], true))
                <form method="POST" action="{{ route('requests.cancel', $repairRequest->id) }}">
                    @csrf
                    <button type="submit" class="rounded-md border border-rose-300 bg-rose-50 px-4 py-2 text-xs font-bold text-rose-800 hover:bg-rose-100 transition">
                        Cancel Request
                    </button>
                </form>
            @elseif($viewer && $viewerRole === 'vendor')
                @if($orderInvoice)
                    <a href="{{ route('invoices.show', $orderInvoice->id) }}" class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                        <i class="fa-solid fa-file-pdf mr-1"></i> View Invoice
                    </a>
                @endif
                <a href="/vendor/dashboard" class="rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)] transition">
                    <i class="fa-solid fa-store mr-1.5"></i>Back to Vendor Dashboard
                </a>
            @elseif($viewer && in_array($viewerRole, ['delivery_agent', 'delivery'], true))
                <a href="/delivery" class="rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-100)] transition">
                    <i class="fa-solid fa-motorcycle mr-1.5"></i>Back to Rider Dashboard
                </a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="mb-5 rounded-lg border border-emerald-400 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="mb-5 rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-800 space-y-1 shadow-sm">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <!-- Main Content Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Left Column: Request Details, Dual-Quote Actions & Status -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Direct-Order Workshop Banner (Path B: Cart Direct Order) -->
            @if(($repairRequest->source ?? '') === 'DIRECT_SERVICE' && $repairRequest->requestedShop)
                <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)] p-5 shadow-sm flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-center gap-3.5 min-w-0">
                        <div class="h-12 w-12 rounded-lg bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center font-bold text-lg shrink-0">
                            <i class="fa-solid fa-store"></i>
                        </div>
                        <div class="min-w-0">
                            <span class="text-[10px] font-semibold uppercase tracking-wider text-[var(--accent-dark)]">
                                Workshop Booking
                            </span>
                            <h3 class="text-base font-bold text-[var(--foreground)] break-words">{{ $repairRequest->requestedShop->name }}</h3>
                            <p class="text-xs text-[var(--muted-foreground)]">
                                @if($currentStatus === 'PENDING')
                                    Sent to {{ $repairRequest->requestedShop->name }} — awaiting booking confirmation.
                                @elseif($currentStatus === 'REJECTED')
                                    Declined by workshop — any payment hold has been refunded.
                                @elseif($repairRequest->requiresCourierLogistics())
                                    Confirmed by workshop — courier dispatch and bench repair progress below.
                                @else
                                    Confirmed for in-shop service — bring your device to {{ $repairRequest->requestedShop->name }} when ready.
                                @endif
                            </p>
                        </div>
                    </div>
                    @if($ownsRequestedShop || $viewerRole === 'admin')
                        <a href="/vendor/dashboard" class="rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition whitespace-nowrap shrink-0">
                            Vendor Dashboard &rarr;
                        </a>
                    @else
                        <a href="{{ route('shops.show', $repairRequest->requestedShop->slug) }}" class="rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition whitespace-nowrap shrink-0">
                            View Workshop &rarr;
                        </a>
                    @endif
                </div>
            @endif

            @php
                $bookedItems = ($repairRequest->source ?? '') === 'DIRECT_SERVICE' && is_array($repairRequest->repairJob?->final_quote_items)
                    ? $repairRequest->repairJob->final_quote_items
                    : [];
                $needsCourierOrder = $repairRequest->requiresCourierLogistics();
            @endphp
            @if(!empty($bookedItems))
                @php
                    $bookedSubtotal = collect($bookedItems)->sum(fn ($it) => ((float) ($it['price'] ?? 0)) * ((int) ($it['quantity'] ?? 1)));
                    $bookedCourierFee = $needsCourierOrder
                        ? (($repairRequest->delivery_type === 'EXPRESS') ? 150 : 80)
                        : 0.0;
                    $bookedEscrowFee = (float) ($repairRequest->commission_amount ?? $repairRequest->checkup_fee ?? 0);
                    $bookedRatePct = $bookedSubtotal > 0 && $bookedEscrowFee > 0
                        ? rtrim(rtrim(number_format(($bookedEscrowFee / $bookedSubtotal) * 100, 2), '0'), '.')
                        : rtrim(rtrim(number_format(\App\Http\Controllers\ServicePackageController::resolveCommissionRatePct($repairRequest->requestedShop), 2), '0'), '.');
                @endphp
                <div class="meramot-card p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)]">
                            Booked Service Packages
                        </h2>
                        <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-3 py-0.5 text-[11px] font-bold text-[var(--accent-dark)] whitespace-nowrap">
                            {{ count($bookedItems) }} {{ \Illuminate\Support\Str::plural('Service', count($bookedItems)) }}
                        </span>
                    </div>

                    <div class="space-y-2.5">
                        @foreach($bookedItems as $bItem)
                            @php
                                $bQty = (int) ($bItem['quantity'] ?? 1);
                                $bUnit = (float) ($bItem['price'] ?? 0);
                            @endphp
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/55 px-4 py-3 text-xs">
                                <div class="min-w-0">
                                    <p class="font-bold text-[var(--foreground)] break-words">{{ $bItem['title'] ?? 'Repair Service' }}</p>
                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-[11px] text-[var(--muted-foreground)]">
                                        @if(!empty($bItem['category']))
                                            <span class="rounded bg-[var(--card)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                                                {{ $bItem['category'] }}
                                            </span>
                                        @endif
                                        @if(!empty($bItem['duration']))
                                            <span><i class="fa-regular fa-clock mr-1"></i>{{ $bItem['duration'] }}h est.</span>
                                        @endif
                                        @if(!empty($bItem['warranty_months']))
                                            <span class="text-emerald-700 font-semibold"><i class="fa-solid fa-shield-halved mr-1 text-[10px]"></i>{{ $bItem['warranty_months'] }}-mo warranty</span>
                                        @endif
                                    </div>
                                </div>
                                <div class="text-left sm:text-right shrink-0">
                                    <p class="font-bold text-sm text-[var(--accent-dark)]">৳{{ number_format($bUnit * $bQty, 2) }}</p>
                                    <p class="text-[11px] text-[var(--muted-foreground)]">৳{{ number_format($bUnit, 0) }} × {{ $bQty }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-50)]/80 px-4 py-3 text-xs space-y-1.5">
                        <div class="flex justify-between gap-2 text-[var(--muted-foreground)]">
                            <span>Services &amp; Parts Subtotal</span>
                            <span class="font-semibold text-[var(--foreground)] shrink-0">৳{{ number_format($bookedSubtotal, 2) }}</span>
                        </div>
                        <div class="flex justify-between gap-2 text-[var(--muted-foreground)]">
                            <span>Service &amp; Protection Fee</span>
                            <span class="font-semibold text-[var(--foreground)] shrink-0">৳{{ number_format($bookedEscrowFee, 2) }}</span>
                        </div>
                        @if($needsCourierOrder)
                            <div class="flex justify-between gap-2 text-[var(--muted-foreground)]">
                                <span>Doorstep Pickup &amp; Delivery ({{ $repairRequest->delivery_type === 'EXPRESS' ? 'Express' : 'Regular' }})</span>
                                <span class="font-semibold text-[var(--foreground)] shrink-0">৳{{ number_format($bookedCourierFee, 2) }}</span>
                            </div>
                        @else
                            <div class="flex justify-between gap-2 text-emerald-800 font-semibold">
                                <span>In-Shop Walk-In (No Delivery Needed)</span>
                                <span class="shrink-0">৳0.00</span>
                            </div>
                        @endif
                    </div>
                </div>
            @endif

            @if($repairRequest->repairJob && ($repairRequest->source ?? 'MARKETPLACE') !== 'DIRECT_SERVICE' && !in_array($currentStatus, ['PENDING', 'BIDDING', 'WAITING_APPROVAL'], true))
                @php
                    $activeJob = $repairRequest->repairJob;
                @endphp
                <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/70 p-5 sm:p-6 shadow-sm space-y-3">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">
                                Verified Workshop Quote &amp; Warranty Terms
                            </span>
                            <h3 class="text-base font-bold text-[var(--foreground)]">
                                Itemized Repair Breakdown
                            </h3>
                        </div>
                        <span class="text-lg font-bold text-[var(--accent-dark)] shrink-0">
                            ৳{{ number_format($activeJob->final_quoted_amount ?? $repairRequest->quoted_final_amount ?? 0, 2) }}
                        </span>
                    </div>
                    @include('requests.partials.quote-breakdown-list', [
                        'partsItems' => $activeJob->resolvedPartsItems(),
                        'partsCost' => $activeJob->resolvedPartsCost(),
                        'laborCost' => $activeJob->resolvedLaborCost(),
                        'hasWarranty' => $activeJob->hasWarrantyCoverage(),
                        'warrantyDays' => $activeJob->resolvedWarrantyDays(),
                    ])
                </div>
            @endif

            <!-- Quote 2: Final Physical Diagnosis Approval Card (WAITING_APPROVAL) -->
            @if($currentStatus === 'WAITING_APPROVAL')
                @php
                    $pendingQuoteJob = $repairRequest->repairJob;
                @endphp
                <div class="rounded-lg border-2 border-amber-400 bg-amber-50/90 p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                        <div>
                            <span class="rounded bg-amber-200 px-3 py-0.5 text-[10px] font-semibold uppercase text-amber-900">
                                Final Estimate • Action Required
                            </span>
                            <h3 class="mt-1 text-lg font-bold text-[#203126]">Bench Inspection Complete — Approve Final Quote</h3>
                            <p class="mt-1 text-xs text-amber-950/80 leading-relaxed">
                                {{ $pendingQuoteJob->diagnosis_notes ?? 'Physical bench inspection is complete. Review the itemized final parts and labor quote below before repair work begins.' }}
                            </p>
                        </div>
                        <div class="text-right shrink-0">
                            <span class="text-[10px] uppercase font-bold text-amber-900 block">Final Quote</span>
                            <span class="text-2xl font-bold text-[#244229]">
                                ৳{{ number_format($pendingQuoteJob->final_quoted_amount ?? $repairRequest->quoted_final_amount ?? 0, 2) }}
                            </span>
                        </div>
                    </div>

                    @if($pendingQuoteJob)
                        @include('requests.partials.quote-breakdown-list', [
                            'partsItems' => $pendingQuoteJob->resolvedPartsItems(),
                            'partsCost' => $pendingQuoteJob->resolvedPartsCost(),
                            'laborCost' => $pendingQuoteJob->resolvedLaborCost(),
                            'hasWarranty' => $pendingQuoteJob->hasWarrantyCoverage(),
                            'warrantyDays' => $pendingQuoteJob->resolvedWarrantyDays(),
                            'theme' => 'amber',
                        ])
                    @endif

                    @if($canActAsCustomer)
                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <form method="POST" action="{{ route('requests.respond-final-quote', $repairRequest->id) }}">
                                @csrf
                                <input type="hidden" name="decision" value="approve">
                                <button type="submit" class="rounded-md bg-[#244229] px-5 py-2.5 text-xs font-bold text-white shadow hover:opacity-90 transition">
                                    Approve Quote & Start Repair
                                </button>
                            </form>
                            <form method="POST" action="{{ route('requests.respond-final-quote', $repairRequest->id) }}">
                                @csrf
                                <input type="hidden" name="decision" value="decline">
                                <button type="submit" class="rounded-md border border-rose-300 bg-white px-5 py-2.5 text-xs font-bold text-rose-800 hover:bg-rose-50 transition">
                                    Decline Quote & Schedule Device Return
                                </button>
                            </form>
                        </div>
                    @endif
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
                        'BIDDING' => ['label' => 'Open for Estimates', 'icon' => 'fa-gavel'],
                        'ASSIGNED' => ['label' => 'Vendor Assigned', 'icon' => 'fa-handshake'],
                        'PICKUP_SCHEDULED' => ['label' => 'Pickup Scheduled', 'icon' => 'fa-calendar-check'],
                        'PICKED_UP' => ['label' => 'Device Picked Up', 'icon' => 'fa-box'],
                        'AT_SHOP' => ['label' => 'At Repair Shop', 'icon' => 'fa-store'],
                        'DIAGNOSING' => ['label' => 'Bench Diagnosing', 'icon' => 'fa-magnifying-glass'],
                        'WAITING_APPROVAL' => ['label' => 'Awaiting Final Quote Approval', 'icon' => 'fa-hourglass-half'],
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
                    $exactStepIndex = array_search($currentStatus, $progressSteps, true);
                    $currentIndex = $exactStepIndex;
                    if ($currentIndex === false) {
                        if (in_array($currentStatus, ['PICKUP_SCHEDULED', 'PICKED_UP'], true)) {
                            $currentIndex = array_search('ASSIGNED', $progressSteps, true);
                        } elseif (in_array($currentStatus, ['DIAGNOSING', 'WAITING_APPROVAL'], true)) {
                            $currentIndex = array_search('DIAGNOSING', $progressSteps, true) !== false
                                ? array_search('DIAGNOSING', $progressSteps, true)
                                : array_search('AT_SHOP', $progressSteps, true);
                        } elseif (in_array($currentStatus, ['RETURN_SCHEDULED', 'RETURNING'], true)) {
                            $currentIndex = array_search('REPAIRING', $progressSteps, true);
                        } elseif ($currentStatus === 'RETURNED_TO_CUSTOMER') {
                            $currentIndex = count($progressSteps) - 1;
                        } else {
                            $currentIndex = -1;
                        }
                    }
                @endphp

                <!-- Current Status Badge -->
                <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-4 rounded-lg bg-[var(--mint-100)] border border-[var(--border)]">
                    <div class="flex items-center gap-3 min-w-0">
                        <div class="h-12 w-12 rounded-xl bg-[var(--accent-dark)] flex items-center justify-center text-[var(--accent-foreground)] shrink-0">
                            <i class="fa-solid {{ $statusInfo['icon'] }} text-lg"></i>
                        </div>
                        <div class="min-w-0">
                            <p class="text-[10px] uppercase tracking-wider text-[var(--muted-foreground)] font-bold">Current Status</p>
                            <h3 class="text-lg font-bold text-[var(--foreground)]">{{ $statusInfo['label'] }}</h3>
                            <p class="text-xs text-[var(--muted-foreground)]">
                                Last updated: {{ $repairRequest->updated_at?->diffForHumans() ?? 'just now' }}
                            </p>
                        </div>
                    </div>

                    @php
                        $completedShop = $repairRequest->repairJob?->shop ?? $repairRequest->requestedShop;
                        $isOrderOwner = auth()->check() && (int) auth()->id() === (int) $repairRequest->customer_id;
                    @endphp
                    @if($isOrderOwner && in_array($currentStatus, ['COMPLETED', 'RETURNED_TO_CUSTOMER'], true) && $completedShop)
                        <div class="flex flex-col sm:flex-row flex-wrap items-stretch sm:items-center gap-2 shrink-0 w-full sm:w-auto">
                            @if($repairRequest->review)
                                <a
                                    href="{{ route('shops.show', $completedShop->slug) }}?edit_review={{ $repairRequest->review->id }}#review-{{ $repairRequest->review->id }}"
                                    class="w-full sm:w-auto inline-flex items-center justify-center whitespace-nowrap gap-1.5 rounded-md border border-emerald-300 bg-emerald-100 px-4 py-2 text-xs font-bold text-emerald-900 shadow-sm hover:bg-emerald-200 transition"
                                >
                                    <i class="fa-solid fa-pen-to-square text-emerald-700"></i>
                                    <span>Edit Review ({{ $repairRequest->review->rating }}★)</span>
                                </a>
                                <form
                                    method="POST"
                                    action="{{ route('reviews.destroy', $repairRequest->review->id) }}"
                                    data-confirm-delete
                                    data-confirm-title="Delete Verified Review?"
                                    data-confirm-item="Order #{{ $repairRequest->id }}"
                                    data-confirm-message="Your verified review will be deleted and you can write a new one anytime."
                                    data-confirm-cta="Delete Review"
                                    class="w-full sm:w-auto inline-flex"
                                >
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="redirect_back" value="1">
                                    <button
                                        type="submit"
                                        class="w-full sm:w-auto inline-flex items-center justify-center whitespace-nowrap gap-1.5 rounded-md bg-rose-600 px-4 py-2 text-xs font-bold text-white shadow-sm hover:bg-rose-700 transition"
                                    >
                                        <i class="fa-solid fa-trash-can text-[11px]"></i>
                                        <span>Delete Review</span>
                                    </button>
                                </form>
                            @else
                                <a
                                    href="{{ route('reviews.create', ['shopId' => $completedShop->id, 'order_id' => $repairRequest->id]) }}"
                                    class="w-full sm:w-auto inline-flex items-center justify-center whitespace-nowrap gap-1.5 rounded-md bg-emerald-600 px-4 py-2.5 text-xs font-bold text-white shadow-sm hover:bg-emerald-700 transition"
                                >
                                    <i class="fa-solid fa-certificate"></i>
                                    <span>Write Verified Repair Review</span>
                                </a>
                            @endif
                        </div>
                    @endif
                </div>

                <!-- Progress Steps -->
                <div class="space-y-3">
                    @foreach($progressSteps as $index => $step)
                        @php
                            $stepInfo = $statuses[$step];
                            $isCompleted = $currentStatus === 'COMPLETED'
                                || ($currentIndex !== false && $currentIndex >= 0 && ($index < $currentIndex || ($exactStepIndex === false && $index <= $currentIndex)));
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
                                <span class="px-2.5 py-1 rounded bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-[10px] font-bold uppercase">
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

            <!-- Courier & Handover Status Card -->
            @if($repairRequest->deliveryJobs && $repairRequest->deliveryJobs->isNotEmpty())
                @php
                    $orderedTrips = $repairRequest->deliveryJobs->sortBy('id');
                @endphp
                <div class="meramot-card p-6 shadow-sm space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)]">
                                Courier &amp; Handover Status
                            </h2>
                            <p class="text-xs text-[var(--muted-foreground)] mt-0.5">
                                Live pickup and return milestones synced directly with your assigned courier.
                            </p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        @foreach($orderedTrips as $trip)
                            @php
                                $isReturnTrip = strtoupper((string) $trip->direction) === 'TO_CUSTOMER';
                                $tripUpper = strtoupper((string) $trip->status);
                                $tripLabel = \App\Services\DeliveryLifecycleService::milestoneLabel($trip);
                                $tripStages = \App\Services\DeliveryLifecycleService::canonicalStages($trip);
                                $customerHandoverCode = !$isReturnTrip
                                    ? (in_array($tripUpper, ['PENDING', 'SCHEDULED', 'DISPATCHED'], true) ? $trip->pickup_otp : null)
                                    : (in_array($tripUpper, ['PENDING', 'SCHEDULED', 'DISPATCHED', 'PICKED_UP', 'IN_TRANSIT'], true) ? $trip->dropoff_otp : null);
                            @endphp
                            <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/55 p-4 space-y-3">
                                <div class="flex flex-wrap items-start justify-between gap-2">
                                    <div class="min-w-0">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <span class="rounded bg-[var(--card)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-bold uppercase text-[var(--accent-dark)]">
                                                {{ $isReturnTrip ? 'Return Delivery' : 'Pickup to Workshop' }}
                                            </span>
                                            @if($trip->tracking_code)
                                                <span class="font-mono text-xs text-[var(--muted-foreground)]">{{ $trip->tracking_code }}</span>
                                            @endif
                                        </div>
                                        <p class="mt-1.5 text-xs text-[var(--foreground)] break-words">
                                            @if($trip->rider_name || $trip->deliveryAgent?->user?->name)
                                                Courier: <strong>{{ $trip->rider_name ?? $trip->deliveryAgent?->user?->name }}</strong>
                                                @if($trip->rider_phone || $trip->deliveryAgent?->user?->phone)
                                                    &bull; {{ $trip->rider_phone ?? $trip->deliveryAgent?->user?->phone }}
                                                @endif
                                            @else
                                                <span class="text-[var(--muted-foreground)]">Awaiting courier acceptance from the dispatch queue.</span>
                                            @endif
                                        </p>
                                    </div>

                                    <div class="flex items-center gap-2 shrink-0">
                                        <span class="rounded-md px-3 py-1 text-[11px] font-bold border {{ $tripUpper === 'DELIVERED' ? 'bg-emerald-100 text-emerald-900 border-emerald-300' : 'bg-[var(--card)] text-[var(--accent-dark)] border-[var(--border)]' }}">
                                            {{ $tripLabel }}
                                        </span>
                                        <a href="/tracking/{{ $trip->id }}" class="rounded-md border border-[var(--border)] bg-[var(--card)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                                            Live Map
                                        </a>
                                    </div>
                                </div>

                                <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5">
                                    @foreach($tripStages as $st)
                                        <div class="rounded-lg border px-2 py-1.5 text-center {{ $st['state'] === 'done' ? 'bg-emerald-100/80 border-emerald-300 text-emerald-900' : ($st['state'] === 'current' ? 'bg-[var(--accent-dark)] border-[var(--accent-dark)] text-[var(--accent-foreground)]' : 'bg-[var(--card)] border-[var(--border)] text-[var(--muted-foreground)]') }}">
                                            <p class="text-[10px] font-bold truncate">{{ $st['label'] }}</p>
                                        </div>
                                    @endforeach
                                </div>

                                @if($canActAsCustomer && $customerHandoverCode)
                                    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-amber-300 bg-amber-50/90 px-3.5 py-2.5 text-xs">
                                        <span class="font-medium text-amber-950">
                                            {{ $isReturnTrip ? 'Share this delivery confirmation code when receiving your device:' : 'Share this pickup code with the courier at handover:' }}
                                        </span>
                                        <span class="rounded-lg bg-white border border-amber-300 px-3 py-1 font-mono text-sm font-bold tracking-widest text-[#163625]">
                                            {{ $customerHandoverCode }}
                                        </span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @elseif(!$repairRequest->requiresCourierLogistics())
                @php
                    $walkInShop = $repairRequest->repairJob?->shop ?? $repairRequest->requestedShop;
                @endphp
                <div class="meramot-card p-5 sm:p-6 shadow-sm border border-emerald-300 bg-emerald-50/60 space-y-2.5">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-bold uppercase tracking-wider text-emerald-950">
                            <i class="fa-solid fa-store mr-1.5 text-emerald-700"></i>In-Shop Walk-In Service
                        </h2>
                        <span class="rounded bg-emerald-100 border border-emerald-300 px-3 py-0.5 text-[11px] font-bold text-emerald-900">
                            No Courier Needed &bull; ৳0 Delivery
                        </span>
                    </div>
                    <p class="text-xs text-[var(--foreground)] leading-relaxed">
                        @if($currentStatus === 'RETURN_SCHEDULED' || $currentStatus === 'READY_TO_RETURN')
                            Your device repair is complete and ready for pickup at the workshop counter. Complete payment via Meramot and collect your device directly.
                        @else
                            Drop off your device in person at {{ $walkInShop?->name ?? 'the workshop' }} and settle payment securely via Meramot — no courier dispatch is required.
                        @endif
                    </p>
                    @if($walkInShop)
                        <div class="rounded-xl border border-[var(--border)] bg-[var(--card)] px-3.5 py-2.5 text-xs">
                            <span class="font-bold text-[var(--foreground)]">{{ $walkInShop->name }}{{ $walkInShop->display_branch_tag ? ' [' . $walkInShop->display_branch_tag . ']' : '' }}</span>
                            <span class="block text-[var(--muted-foreground)] mt-0.5">{{ $walkInShop->public_address }}</span>
                        </div>
                    @endif
                </div>
            @endif

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
                            @php
                                $photoUrl = (str_starts_with($photo, 'http') || str_starts_with($photo, 'data:') || str_starts_with($photo, '/'))
                                    ? $photo
                                    : asset('storage/' . $photo);
                            @endphp
                            <button
                                type="button"
                                @click="lightboxPhoto = @js($photoUrl)"
                                class="group relative block overflow-hidden rounded-xl border border-[var(--border)] focus:outline-none"
                            >
                                <img src="{{ $photoUrl }}" alt="Device photo" class="w-full h-24 object-cover transition group-hover:scale-105 group-hover:opacity-90">
                                <span class="absolute bottom-1.5 right-1.5 rounded bg-black/60 px-2 py-0.5 text-[10px] font-bold text-white">
                                    <i class="fa-solid fa-magnifying-glass-plus mr-0.5"></i>Zoom
                                </span>
                            </button>
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
                    <p class="text-xs sm:text-sm text-[var(--foreground)] bg-[var(--mint-100)] border border-[var(--border)] p-3.5 rounded-lg">
                        {{ $repairRequest->ai_diagnostic_summary }}
                    </p>
                </div>
                @endif
            </div>

            @if($repairRequest->issuedWarranties->isNotEmpty() || in_array($currentStatus, ['COMPLETED', 'RETURNED_TO_CUSTOMER'], true))
                <section id="warranty-section" class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 sm:p-6 shadow-sm space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] pb-3">
                        <div>
                            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">
                                <i class="fa-solid fa-shield-halved mr-1"></i>Meramot Warranty Protection
                            </span>
                            <h2 class="text-base sm:text-lg font-bold text-[var(--foreground)]">
                                Active Warranty Coverage &amp; Claim History
                            </h2>
                            <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">
                                File repeatable claims during your active warranty period with issue explanation and photos. Meramot Admin reviews each claim and issues Refund, Replacement, or Free Repair.
                            </p>
                        </div>
                        <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
                            {{ $repairRequest->issuedWarranties->filter(fn ($w) => $w->isCurrentlyActive())->count() }} Active
                        </span>
                    </div>

                    @if($repairRequest->issuedWarranties->isNotEmpty())
                        <div class="space-y-4">
                            @foreach($repairRequest->issuedWarranties as $orderWarranty)
                                @include('requests.partials.warranty-card', [
                                    'w' => $orderWarranty,
                                    'canActAsCustomer' => $canActAsCustomer,
                                    'showOrderLink' => false,
                                    'openByDefault' => $repairRequest->issuedWarranties->count() === 1 && $orderWarranty->isCurrentlyActive() && $orderWarranty->claims->isEmpty(),
                                ])
                            @endforeach
                        </div>
                    @else
                        <div class="rounded-md border border-dashed border-[var(--border)] bg-[var(--mint-50)]/45 p-4 text-xs text-[var(--muted-foreground)]">
                            No active extended item warranty certificates were issued on this completed order.
                        </div>
                    @endif
                </section>
            @endif

            <!-- Vendor Bids Section (Path A: Marketplace Quote 1) -->
            @if(($repairRequest->source ?? 'MARKETPLACE') !== 'DIRECT_SERVICE' && ($currentStatus === 'BIDDING' || $repairRequest->bids->count() > 0))
            <div class="meramot-card p-6 shadow-sm" x-data="{
                bidSort: 'price',
                sortBidCards(mode) {
                    this.bidSort = mode;
                    const container = this.$refs.bidsContainer;
                    if (!container) return;
                    const cards = Array.from(container.querySelectorAll('[data-bid-card]'));
                    cards.sort((a, b) => {
                        if (mode === 'turnaround') return Number(a.dataset.days || 99) - Number(b.dataset.days || 99);
                        if (mode === 'warranty') return Number(b.dataset.warranty || 0) - Number(a.dataset.warranty || 0);
                        if (mode === 'rating') return Number(b.dataset.rating || 0) - Number(a.dataset.rating || 0);
                        return Number(a.dataset.price || 0) - Number(b.dataset.price || 0);
                    });
                    cards.forEach((c) => container.appendChild(c));
                }
            }">
                <div class="flex flex-wrap items-center justify-between gap-2 mb-4">
                    <h2 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)]">
                        Vendor Bids ({{ $repairRequest->bids->count() }})
                    </h2>
                    <div class="flex flex-wrap items-center gap-1.5">
                        @if($repairRequest->bids->count() > 1)
                            @foreach(['price' => 'Lowest Price', 'turnaround' => 'Fastest', 'warranty' => 'Best Warranty', 'rating' => 'Top Rated'] as $bKey => $bLabel)
                                <button
                                    type="button"
                                    @click="sortBidCards('{{ $bKey }}')"
                                    class="rounded border px-2.5 py-1 text-[10px] font-bold transition"
                                    :class="bidSort === '{{ $bKey }}' ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] border-[var(--accent-dark)]' : 'bg-[var(--mint-100)] text-[var(--foreground)] border-[var(--border)]'"
                                >
                                    {{ $bLabel }}
                                </button>
                            @endforeach
                        @endif
                        @if($currentStatus === 'BIDDING')
                            <span class="px-3 py-1 rounded-md bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] text-xs font-bold">
                                Bidding Open
                            </span>
                        @endif
                    </div>
                </div>

                @if($repairRequest->bids->count() > 0)
                    <div class="space-y-3" x-ref="bidsContainer">
                        @foreach($repairRequest->bids as $bid)
                            @php
                                $bidPartsItems = $bid->resolvedPartsItems();
                                $bidHasWarranty = $bid->hasWarrantyCoverage();
                                $bidWarrantyDays = $bid->resolvedWarrantyDays();
                                $authOwnsBidShop = auth()->check()
                                    && auth()->user()->role === 'vendor'
                                    && auth()->user()->vendorProfile
                                    && (int) ($bid->shop?->vendor_profile_id ?? 0) === (int) auth()->user()->vendorProfile->id;
                            @endphp
                            <div
                                data-bid-card
                                data-price="{{ (float) $bid->proposed_cost }}"
                                data-days="{{ (int) ($bid->estimated_days ?? 99) }}"
                                data-warranty="{{ (int) $bidWarrantyDays }}"
                                data-rating="{{ (float) ($bid->shop?->rating ?? 0) }}"
                                class="p-4 rounded-lg border border-[var(--border)] {{ $bid->id === $repairRequest->selected_bid_id ? 'bg-[var(--mint-100)] border-emerald-600' : 'bg-[var(--mint-100)]/40' }}"
                            >
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
                                        <p class="text-lg font-bold text-[var(--accent-dark)]">
                                            ৳{{ number_format($bid->proposed_cost, 2) }}<span class="sr-only">{{ (int) $bid->proposed_cost }}</span>
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

                                <div class="mb-3">
                                    @include('requests.partials.quote-breakdown-list', [
                                        'partsItems' => $bidPartsItems,
                                        'partsCost' => $bid->parts_cost ?? 0,
                                        'laborCost' => $bid->labor_cost ?? 0,
                                        'hasWarranty' => $bidHasWarranty,
                                        'warrantyDays' => $bidWarrantyDays,
                                        'compact' => true,
                                    ])
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2 text-xs">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="px-2.5 py-1 rounded-md bg-[var(--card)] border border-[var(--border)] text-[var(--foreground)]">
                                            Parts: ৳{{ number_format($bid->parts_cost ?? 0, 2) }}
                                        </span>
                                        <span class="px-2.5 py-1 rounded-md bg-[var(--card)] border border-[var(--border)] text-[var(--foreground)]">
                                            Labor: ৳{{ number_format($bid->labor_cost ?? 0, 2) }}
                                        </span>
                                        @php
                                            $partDaysList = collect($bidPartsItems)
                                                ->map(fn ($p) => (bool) ($p['has_warranty'] ?? false) ? (int) ($p['warranty_days'] ?? 0) : 0);
                                            $warrantiedPartsCount = $partDaysList->filter(fn ($d) => $d > 0)->count();
                                            $allPartsSameDays = $warrantiedPartsCount > 0
                                                && $warrantiedPartsCount === $partDaysList->count()
                                                && $partDaysList->unique()->count() === 1;
                                            $bidWarrantyPillText = ($bidHasWarranty && $bidWarrantyDays > 0)
                                                ? ($allPartsSameDays ? "✓ Warranty: {$bidWarrantyDays} days" : "✓ Warranty: up to {$bidWarrantyDays} days")
                                                : 'No parts warranty';
                                        @endphp
                                        <span class="px-2.5 py-1 rounded-md {{ $bidHasWarranty ? 'bg-emerald-100 border border-emerald-300 text-emerald-900 font-semibold' : 'bg-[var(--card)] border border-[var(--border)] text-[var(--muted-foreground)]' }}">
                                            {{ $bidWarrantyPillText }}
                                        </span>
                                    </div>

                                    @if($currentStatus === 'BIDDING' && $authOwnsBidShop)
                                        <a href="{{ route('vendor.bidding-board') }}#ticket-{{ $repairRequest->id }}" class="font-bold text-[var(--accent-dark)] hover:underline">
                                            Edit Estimate &rarr;
                                        </a>
                                    @endif
                                </div>

                                @if($bid->id === $repairRequest->selected_bid_id)
                                    <div class="mt-3 flex items-center gap-2 text-emerald-700">
                                        <i class="fa-solid fa-circle-check"></i>
                                        <span class="text-xs font-bold">Accepted Initial Estimate</span>
                                    </div>
                                @elseif($currentStatus === 'BIDDING' && $canActAsCustomer)
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
                            No estimates received yet. Verified workshops across your service region have been notified of your request.
                        </p>
                    </div>
                @endif
            </div>
            @endif

        </div>

        <!-- Right Column: Quick Actions & Summary -->
        <div class="space-y-6">

            <!-- Quick Actions Card -->
            @php
                $activeDeliveryJob = $repairRequest->deliveryJobs?->sortByDesc('id')->first();
                $trackingTargetId = $activeDeliveryJob?->id ?? $repairRequest->id;
            @endphp
            <div class="meramot-card p-5 shadow-sm">
                <h3 class="text-sm font-bold uppercase tracking-wider text-[var(--muted-foreground)] mb-4">
                    Quick Actions
                </h3>

                <div class="space-y-2.5">
                    @if($canActAsCustomer && in_array($currentStatus, ['ASSIGNED', 'REPAIRING', 'WAITING_APPROVAL', 'COMPLETED'], true))
                        <a href="/checkout/payment/{{ $repairRequest->id }}" class="block w-full py-2.5 rounded-full bg-emerald-700 text-center text-xs font-bold text-white hover:opacity-90 transition">
                            <i class="fa-solid fa-credit-card mr-2"></i>Secure Online Checkout
                        </a>
                    @endif

                    @if($activeDeliveryJob || in_array($currentStatus, ['PICKUP_SCHEDULED', 'PICKED_UP', 'AT_SHOP', 'RETURN_SCHEDULED', 'RETURNING'], true))
                        <a href="/tracking/{{ $trackingTargetId }}" class="block w-full py-2.5 rounded-full bg-[var(--accent-dark)] text-center text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                            <i class="fa-solid fa-location-dot mr-2"></i>Track Live Rider GPS
                        </a>
                    @endif

                    @if($canActAsCustomer && $currentStatus === 'COMPLETED' && ($repairRequest->requested_shop_id || $repairRequest->repairJob?->shop_id))
                        <a href="/reviews/create/{{ $repairRequest->requested_shop_id ?? $repairRequest->repairJob->shop_id }}" class="block w-full py-2.5 rounded-full bg-amber-500 text-center text-xs font-bold text-white hover:opacity-90 transition">
                            <i class="fa-solid fa-star mr-2"></i>Leave Verified Review
                        </a>
                    @endif

                    @php
                        $hasReturnableSpareParts = collect($repairRequest->repairJob?->final_quote_items ?? [])
                            ->contains(fn ($i) => strtoupper((string) ($i['source'] ?? '')) === 'SPARE_PART' && !empty($i['spare_part_id']));
                    @endphp
                    @if($canActAsCustomer && $currentStatus === 'COMPLETED' && $hasReturnableSpareParts)
                        <form method="POST" action="{{ route('requests.return-parts', $repairRequest->id) }}"
                              onsubmit="return confirm('Return spare parts from this order and restore workshop stock?');">
                            @csrf
                            <button type="submit" class="block w-full py-2.5 rounded-md bg-rose-700 text-center text-xs font-bold text-white hover:bg-rose-800 transition">
                                <i class="fa-solid fa-rotate-left mr-2"></i>Return Spare Parts
                            </button>
                        </form>
                    @endif

                    <a href="/requests" class="block w-full py-2.5 rounded-full bg-[var(--mint-100)] border border-[var(--border)] text-center text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                        <i class="fa-solid fa-list-check mr-2 text-[var(--accent-dark)]"></i>All My Repair Requests
                    </a>

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

                    @php
                        $summaryNeedsCourier = $repairRequest->requiresCourierLogistics();
                    @endphp
                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Booking Type</span>
                        <span class="font-bold text-[var(--accent-dark)]">
                            {{ ($repairRequest->source ?? 'MARKETPLACE') === 'DIRECT_SERVICE' ? 'Workshop Booking' : 'Custom Quote Request' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Submitted</span>
                        <span class="font-bold text-[var(--foreground)]">{{ $repairRequest->created_at?->format('M d, Y') }}</span>
                    </div>

                    <div class="flex items-center justify-between">
                        <span class="text-[var(--muted-foreground)]">Service Mode</span>
                        <span class="font-bold text-[var(--foreground)]">
                            {{ $summaryNeedsCourier ? 'Courier Pickup & Return' : 'In-Shop Walk-In (৳0 Delivery)' }}
                        </span>
                    </div>

                    @if(!$summaryNeedsCourier)
                    <div class="pt-3 border-t border-[var(--border)]">
                        <p class="text-[var(--muted-foreground)] mb-1">Workshop Location</p>
                        <p class="font-semibold text-[var(--foreground)] leading-relaxed">
                            {{ $repairRequest->requestedShop?->address ?: ($repairRequest->requestedShop?->area ? ($repairRequest->requestedShop->area . ', ' . ($repairRequest->requestedShop->city ?? 'Dhaka')) : 'Workshop Counter') }}
                        </p>
                    </div>
                    @elseif($repairRequest->pickup_address)
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
                            <span class="text-lg font-bold text-[var(--accent-dark)]">
                                ৳{{ number_format($repairRequest->quoted_final_amount, 2) }}
                            </span>
                        </div>
                    </div>
                    @endif
                </div>
            </div>

            <!-- Mint Support Card -->
            <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)] p-5 shadow-sm">
                <h3 class="text-sm font-bold text-[var(--foreground)] mb-2">
                    <i class="fa-solid fa-headset mr-2 text-[var(--accent-dark)]"></i>Need Payment or Courier Help?
                </h3>
                <p class="text-xs text-[var(--muted-foreground)] mb-3">
                    Our dedicated operations and dispute resolution team monitors every verified repair order.
                </p>
                <a href="/support/rider-chat/1" class="block w-full py-2.5 rounded-full bg-[var(--accent-dark)] text-center text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                    Open Support Desk
                </a>
            </div>

        </div>

    </div>

    <!-- Attached Photo Click-to-Zoom Lightbox Modal -->
    <div
        x-show="lightboxPhoto"
        x-cloak
        @keydown.escape.window="lightboxPhoto = null"
        class="fixed inset-0 z-[150] flex items-center justify-center p-4"
    >
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="lightboxPhoto = null"></div>
        <div class="relative z-[151] w-full max-w-3xl rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 shadow-2xl space-y-3">
            <div class="flex items-center justify-between border-b border-[var(--border)] pb-2.5">
                <span class="text-xs font-bold uppercase tracking-wider text-[var(--accent-dark)]">Attached Device Photo Preview</span>
                <div class="flex items-center gap-2">
                    <a :href="lightboxPhoto" target="_blank" class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1 text-xs font-bold text-[var(--foreground)]">
                        Open Original &nearr;
                    </a>
                    <button type="button" @click="lightboxPhoto = null" class="h-8 w-8 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] font-bold">✕</button>
                </div>
            </div>
            <div class="flex max-h-[75vh] items-center justify-center overflow-auto rounded-lg bg-black/90 p-2">
                <img :src="lightboxPhoto" alt="Zoomed device photo" class="max-h-[70vh] max-w-full rounded-xl object-contain">
            </div>
        </div>
    </div>

</div>
@endsection

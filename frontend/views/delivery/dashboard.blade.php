@extends('delivery.layout')

@section('title', 'Delivery Dashboard | Meramot')

@section('content')
<div class="space-y-6">
    <!-- Welcome Banner -->
    <section class="rounded-3xl border border-[#d9e5d5] bg-white p-6 shadow-sm">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-[#163625]/65">Delivery Dashboard</p>
                <h1 class="mt-1 text-2xl font-extrabold text-[#163625]">
                    Welcome, {{ $user->name ?? $user->username ?? 'Partner' }}
                </h1>
                <p class="mt-1.5 text-sm text-[#163625]/70">
                    Track assigned orders, delivery status, and current earnings in one place.
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <a href="/delivery/map" class="inline-flex items-center gap-2 rounded-full bg-[#163625] px-4 py-2.5 text-xs font-bold text-[#E4FCD5] hover:opacity-95 transition">
                    <i class="fa-solid fa-map-location-dot"></i>
                    <span>Open Live Map</span>
                </a>
            </div>
        </div>
    </section>

    @if(($agent->registration_status ?? 'APPROVED') !== 'APPROVED')
        <section class="rounded-3xl border border-[#d9e5d5] bg-white p-6">
            <h2 class="text-lg font-bold text-[#163625]">Account review in progress</h2>
            <p class="mt-2 text-sm text-[#163625]/75">
                Your rider profile is currently <span class="font-semibold">{{ $agent->registration_status }}</span>. You can access order dashboard features after admin approval.
            </p>
        </section>
    @else
        <!-- 3 KPI Stat Cards -->
        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-2xl border border-[#d9e5d5] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold text-[#163625]/65">Active deliveries</p>
                <p class="mt-2 text-3xl font-extrabold text-[#163625]">{{ $activeCount }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9e5d5] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold text-[#163625]/65">Completed trips</p>
                <p class="mt-2 text-3xl font-extrabold text-[#163625]">{{ $completedCount }}</p>
            </div>
            <div class="rounded-2xl border border-[#d9e5d5] bg-white p-5 shadow-sm">
                <p class="text-xs font-semibold text-[#163625]/65">Completed earnings</p>
                <p class="mt-2 text-3xl font-extrabold text-[#163625]">BDT {{ number_format($completedEarnings, 2) }}</p>
            </div>
        </section>

        <!-- Delivery Queue -->
        <section class="rounded-3xl border border-[#d9e5d5] bg-white p-6 shadow-sm">
            <div class="mb-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-bold text-[#163625]">Delivery Queue</h2>
                    <p class="text-xs text-[#163625]/70">Switch between active and all assigned deliveries</p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-full border border-[#d9e5d5] bg-[#f3faf0] p-1 text-xs font-bold">
                        <a
                            href="/delivery?view=ACTIVE"
                            class="rounded-full px-3.5 py-1.5 transition {{ $viewMode === 'ACTIVE' ? 'bg-[#163625] text-[#E4FCD5]' : 'text-[#163625]/75 hover:text-[#163625]' }}"
                        >
                            Active ({{ $activeCount }})
                        </a>
                        <a
                            href="/delivery?view=ASSIGNED"
                            class="rounded-full px-3.5 py-1.5 transition {{ $viewMode === 'ASSIGNED' ? 'bg-[#163625] text-[#E4FCD5]' : 'text-[#163625]/75 hover:text-[#163625]' }}"
                        >
                            All Assigned ({{ $deliveries->count() }})
                        </a>
                    </div>

                    <a href="/delivery/map" class="text-xs sm:text-sm font-semibold text-[#163625] hover:underline ml-1">
                        Open live map &rarr;
                    </a>
                </div>
            </div>

            @if($visibleDeliveries->isEmpty())
                <div class="rounded-2xl border border-dashed border-[#d9e5d5] bg-[#f8fbf6] p-8 text-center">
                    <p class="text-sm font-semibold text-[#163625]/70">
                        {{ $viewMode === 'ACTIVE' ? 'No active deliveries right now.' : 'No deliveries assigned yet.' }}
                    </p>
                </div>
            @else
                <div class="space-y-3">
                    @foreach($visibleDeliveries as $d)
                        @php
                            $req = $d->repairRequest ?? $d->repairJob?->repairRequest;
                            $shop = $d->repairJob?->shop;
                            $title = $req?->title ?? ('Delivery Order #' . $d->id);
                            $statusUpper = strtoupper((string) $d->status);
                            $badgeClass = match ($statusUpper) {
                                'DELIVERED' => 'bg-emerald-100 text-emerald-900 border-emerald-300',
                                'IN_TRANSIT', 'PICKED_UP' => 'bg-sky-100 text-sky-900 border-sky-300',
                                'DISPATCHED', 'SCHEDULED' => 'bg-amber-100 text-amber-900 border-amber-300',
                                default => 'bg-white text-[#163625] border-[#d9e5d5]',
                            };
                        @endphp
                        <a
                            href="/delivery/order/{{ $d->id }}"
                            class="block rounded-2xl border border-[#d9e5d5] bg-[#f8fbf6] p-4 transition hover:bg-[#eef4ea] hover:border-[#b8d0b1]"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap items-center gap-2">
                                        <p class="text-sm font-bold text-[#163625]">{{ $title }}</p>
                                        @if($d->tracking_code)
                                            <span class="font-mono text-[11px] text-[#163625]/60">({{ $d->tracking_code }})</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs font-medium text-[#163625]/75">
                                        {{ strtoupper((string) $d->direction) === 'TO_SHOP' ? 'Customer to shop' : 'Shop to customer' }}
                                        @if($shop?->name)
                                            &bull; <span class="font-semibold">{{ $shop->name }}</span>
                                        @endif
                                    </p>
                                    <p class="mt-1.5 text-xs text-[#163625]/65">
                                        <span class="font-semibold">Pickup:</span> {{ $d->pickup_address }}
                                        <span class="mx-1.5">&rarr;</span>
                                        <span class="font-semibold">Dropoff:</span> {{ $d->dropoff_address }}
                                    </p>
                                </div>
                                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold border {{ $badgeClass }}">
                                    {{ $statusUpper }}
                                </span>
                            </div>
                            <div class="mt-3 pt-2.5 border-t border-[#d9e5d5]/70 flex items-center justify-between text-xs text-[#163625]/80">
                                <p class="font-semibold">Fee: BDT {{ number_format((float) ($d->fee ?? 0), 2) }}</p>
                                <p class="font-bold text-[#163625]">View details &rarr;</p>
                            </div>
                        </a>
                    @endforeach
                </div>
            @endif
        </section>
    @endif
</div>
@endsection

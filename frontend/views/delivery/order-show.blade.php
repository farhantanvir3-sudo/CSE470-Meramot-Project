@extends('delivery.layout')

@section('title', 'Delivery Order #' . $delivery->id . ' | Meramot')

@section('content')
@php
    $req = $delivery->repairRequest ?? $delivery->repairJob?->repairRequest;
    $shop = $delivery->repairJob?->shop;
    $cust = $req?->customer;
    $title = $req?->title ?? ('Delivery Order #' . $delivery->id);
    $deviceType = trim(($req?->brand ? $req->brand . ' ' : '') . ($req?->model_name ?: ($req?->device_type ?? 'Electronic Device')));
    $shopName = $shop?->name ?? 'Meramot Partner Service Center';
    $customerName = $cust?->name ?: 'Customer';
    $customerPhone = $req?->contact_phone ?: ($cust?->phone ?: 'Not provided');
@endphp

<div class="flex flex-col bg-white rounded-3xl overflow-hidden shadow-sm border border-[#d9e5d5] relative" x-data="{ chatOpen: false }">

    <!-- Top Action Bar -->
    <div class="p-6 border-b border-[#e7efe2]">
        <div class="flex justify-between items-center gap-3">
            <a
                href="/delivery"
                class="w-10 h-10 rounded-full bg-[#eef4ea] flex items-center justify-center text-[#163625] border border-[#d9e5d5] hover:bg-[#dcead6] transition"
                title="Back to Delivery Dashboard"
            >
                <i class="fa-solid fa-arrow-left"></i>
            </a>
            <div class="bg-[#163625] text-[#E4FCD5] px-3.5 py-1.5 rounded-full text-xs font-bold font-mono">
                Delivery #{{ $delivery->tracking_code ?: $delivery->id }}
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-2.5">
            <button
                type="button"
                @click="chatOpen = true"
                class="inline-flex items-center gap-2 rounded-xl border border-[#d9e5d5] bg-white px-3.5 py-2 text-xs font-bold text-[#163625] hover:bg-[#f8fbf6] transition"
            >
                <i class="fa-regular fa-comment-dots"></i>
                <span>Open chat ({{ $chatMessages->count() }})</span>
            </button>

            <a
                href="/tracking/{{ $delivery->id }}"
                class="inline-flex items-center gap-2 rounded-xl border border-[#d9e5d5] bg-[#f3faf0] px-3.5 py-2 text-xs font-bold text-[#163625] hover:bg-[#e3f2dd] transition"
            >
                <i class="fa-solid fa-location-crosshairs"></i>
                <span>Live GPS Tracking View</span>
            </a>
        </div>
    </div>

    <!-- Main Body -->
    <div class="p-6">
        <div class="mb-6 flex flex-col sm:flex-row sm:items-start justify-between gap-3">
            <div>
                <h1 class="text-xl md:text-2xl font-extrabold text-[#163625] mb-1">{{ $title }}</h1>
                <p class="text-sm text-[#163625]/70">
                    {{ strtoupper((string) $delivery->direction) === 'TO_SHOP' ? 'Customer to service center' : 'Service center to customer' }}
                </p>
            </div>
            <div class="sm:text-right">
                <h3 class="text-2xl font-extrabold text-emerald-700">BDT {{ number_format((float) ($delivery->fee ?? 0), 2) }}</h3>
                <span class="inline-block mt-1 text-[10px] uppercase font-bold tracking-wider text-[#163625] bg-[#eef4ea] border border-[#d9e5d5] px-2.5 py-0.5 rounded-full">
                    {{ strtoupper((string) $delivery->status) }}
                </span>
            </div>
        </div>

        <!-- Device, Shop & Customer Card -->
        <div class="mb-6 rounded-2xl border border-[#d9e5d5] bg-[#eef4ea] p-4">
            <div class="flex items-start gap-3.5">
                <div class="w-11 h-11 bg-white rounded-xl flex items-center justify-center shrink-0 border border-[#d9e5d5] text-[#163625]">
                    <i class="fa-solid fa-box-open text-lg"></i>
                </div>
                <div class="space-y-0.5">
                    <h4 class="font-bold text-[#163625]">{{ $deviceType }}</h4>
                    <p class="text-xs text-[#163625]/75"><strong>Shop:</strong> {{ $shopName }}</p>
                    <p class="text-xs text-[#163625]/75"><strong>Customer:</strong> {{ $customerName }}</p>
                    <p class="text-xs text-[#163625]/75"><strong>Customer phone:</strong> {{ $customerPhone }}</p>
                </div>
            </div>
        </div>

        <!-- Pickup & Dropoff Cards -->
        <div class="grid gap-4 sm:grid-cols-2 mb-8">
            <div class="rounded-2xl border border-[#d9e5d5] p-4 bg-white">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-[10px] font-bold text-[#163625]/60 uppercase tracking-wider">Pickup</p>
                    @if($delivery->pickup_otp)
                        <span class="font-mono text-[11px] font-bold bg-[#eef4ea] text-[#163625] px-2 py-0.5 rounded border border-[#d9e5d5]">OTP: {{ $delivery->pickup_otp }}</span>
                    @endif
                </div>
                <p class="text-sm font-semibold text-[#163625]">{{ $delivery->pickup_address }}</p>
            </div>

            <div class="rounded-2xl border border-[#d9e5d5] p-4 bg-white">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-[10px] font-bold text-[#163625]/60 uppercase tracking-wider">Dropoff</p>
                    @if($delivery->dropoff_otp)
                        <span class="font-mono text-[11px] font-bold bg-[#eef4ea] text-[#163625] px-2 py-0.5 rounded border border-[#d9e5d5]">OTP: {{ $delivery->dropoff_otp }}</span>
                    @endif
                </div>
                <p class="text-sm font-semibold text-[#163625]">{{ $delivery->dropoff_address }}</p>
            </div>
        </div>

        <!-- 6-Step Milestone Flow -->
        <div class="mb-8 rounded-2xl border border-[#d9e5d5] bg-[#f8fbf6] p-5">
            <p class="mb-3 text-xs font-bold uppercase tracking-wider text-[#163625]/70">
                {{ strtoupper((string) $delivery->direction) === 'TO_SHOP' ? 'Customer to shop flow' : 'Shop to customer flow' }}
            </p>
            <div class="space-y-2">
                @foreach($flowSteps as $idx => $step)
                    @php
                        $isDoneOrCurrent = $currentStepIndex >= $idx;
                        $isCurrent = $currentStepIndex === $idx;
                    @endphp
                    <div class="flex items-center gap-3 rounded-xl border {{ $isCurrent ? 'border-[#163625] bg-white shadow-sm' : 'border-[#d9e5d5] bg-white/85' }} px-3.5 py-2.5">
                        <span class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold {{ $isDoneOrCurrent ? 'bg-[#163625] text-[#E4FCD5]' : 'bg-[#eef4ea] text-[#163625]/60' }}">
                            {{ $idx + 1 }}
                        </span>
                        <div class="flex-1 flex items-center justify-between gap-2">
                            <div>
                                <p class="text-sm font-semibold {{ $isCurrent ? 'text-[#163625]' : 'text-[#163625]/75' }}">{{ $step['title'] }}</p>
                                <p class="text-[10px] font-bold font-mono text-[#163625]/50">{{ $step['code'] }}</p>
                            </div>
                            @if($isCurrent)
                                <span class="rounded-full bg-emerald-100 px-2.5 py-0.5 text-[10px] font-bold text-emerald-800 border border-emerald-200">Current</span>
                            @elseif($currentStepIndex > $idx)
                                <i class="fa-solid fa-check text-emerald-700 text-xs"></i>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex flex-col sm:flex-row gap-3">
            <a
                href="/delivery"
                class="flex-1 text-center rounded-xl border border-[#d9e5d5] bg-white py-3 text-sm font-bold text-[#163625] hover:bg-[#f8fbf6] transition"
            >
                Back to dashboard
            </a>

            @if(!$delivery->delivery_agent_id)
                <form method="POST" action="/delivery/order/{{ $delivery->id }}/status" class="flex-[2]">
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-[#163625] py-3 text-sm font-bold text-[#E4FCD5] hover:opacity-95 transition">
                        Accept this order
                    </button>
                </form>
            @elseif($nextStatus)
                <form method="POST" action="/delivery/order/{{ $delivery->id }}/status" class="flex-[2]">
                    @csrf
                    <button type="submit" class="w-full rounded-xl bg-[#163625] py-3 text-sm font-bold text-[#E4FCD5] hover:opacity-95 transition">
                        Move to {{ $nextStatus }}
                    </button>
                </form>
            @else
                <button type="button" disabled class="flex-[2] rounded-xl bg-[#163625]/50 py-3 text-sm font-bold text-[#E4FCD5] cursor-not-allowed">
                    Final status reached ({{ strtoupper((string) $delivery->status) }})
                </button>
            @endif
        </div>
    </div>

    <!-- Slide-Over Admin Support Chat Drawer -->
    <div x-show="chatOpen" x-cloak class="fixed inset-0 z-50">
        <div class="fixed inset-0 bg-black/40" @click="chatOpen = false"></div>
        <aside class="fixed right-0 top-0 z-50 flex h-full w-full max-w-md flex-col border-l border-[#d9e5d5] bg-white shadow-2xl">
            <div class="flex items-center justify-between border-b border-[#e7efe2] px-5 py-4">
                <div>
                    <p class="text-sm font-bold text-[#163625]">Chat with Delivery Admin</p>
                    <p class="text-xs font-mono text-[#163625]/65">Delivery #{{ $delivery->tracking_code ?: $delivery->id }}</p>
                </div>
                <button
                    type="button"
                    @click="chatOpen = false"
                    class="rounded-lg border border-[#d9e5d5] px-3 py-1.5 text-xs font-semibold text-[#163625] hover:bg-[#f8fbf6]"
                >
                    Close
                </button>
            </div>

            <div class="flex-1 space-y-2.5 overflow-y-auto bg-[#f8fbf6] px-4 py-4">
                @forelse($chatMessages as $msg)
                    @php $isMine = strtoupper((string) $msg->sender_role) === 'DELIVERY'; @endphp
                    <div class="flex {{ $isMine ? 'justify-end' : 'justify-start' }}">
                        <div class="max-w-[85%] rounded-2xl px-3.5 py-2.5 text-xs {{ $isMine ? 'bg-[#163625] text-[#E4FCD5]' : 'border border-[#d9e5d5] bg-white text-[#163625]' }}">
                            <p>{{ $msg->message }}</p>
                            <p class="mt-1 text-[10px] opacity-70">{{ optional($msg->created_at)->format('h:i A') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="text-xs text-[#163625]/65 text-center py-6">No dispatch messages yet for this trip.</p>
                @endforelse
            </div>

            <form method="POST" action="/support/rider-chat/send" class="border-t border-[#e7efe2] p-3 flex gap-2">
                @csrf
                <input type="hidden" name="delivery_job_id" value="{{ $delivery->id }}">
                <input
                    type="text"
                    name="message"
                    required
                    placeholder="Message Meramot Logistics Desk..."
                    class="flex-1 rounded-xl border border-[#d9e5d5] bg-white px-3 py-2 text-xs text-[#163625] outline-none focus:border-[#163625]"
                />
                <button
                    type="submit"
                    class="rounded-xl bg-[#163625] px-4 py-2 text-xs font-bold text-[#E4FCD5] hover:opacity-95"
                >
                    Send
                </button>
            </form>
        </aside>
    </div>
</div>
@endsection

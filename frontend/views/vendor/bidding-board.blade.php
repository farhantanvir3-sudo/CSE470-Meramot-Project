@extends('layouts.app')

@section('title', 'Vendor Live Bidding Board | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6 space-y-6">

    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--border)] pb-4">
        <div>
            <h1 class="text-2xl font-black text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-gavel text-amber-700"></i>
                Live Repair Bidding Marketplace
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R12 • Assigned: Farhan Tanvir [22299179] (Lead)
                </span>
                <span>Submit itemized diagnostic estimates on open customer repair tickets across Bangladesh.</span>
            </p>
        </div>
        <div class="flex items-center gap-2 self-start">
            <a href="/vendor/dashboard?tab=marketplace" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                &larr; Vendor Dashboard
            </a>
            <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] font-bold text-xs px-3.5 py-2">
                {{ ($openRequests ?? collect())->count() }} Open Tickets
            </span>
        </div>
    </div>

    <div class="space-y-6">
        @forelse($openRequests ?? [] as $req)
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm">
                <!-- Left: Active Request Card -->
                <div class="flex flex-col justify-between">
                    <div>
                        <div class="flex items-center justify-between gap-2">
                            <span class="text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-emerald-800 text-white">
                                Bidding Open • #{{ $req->id }}
                            </span>
                            <a href="{{ route('requests.track', $req->id) }}" class="text-[11px] font-bold text-[var(--accent-dark)] hover:underline">
                                View Customer Ticket &rarr;
                            </a>
                        </div>
                        <h3 class="font-black text-[var(--foreground)] text-base mt-3">
                            {{ $req->brand }} {{ $req->model_name }} ({{ $req->device_type }})
                        </h3>
                        <p class="text-xs text-[var(--accent-dark)] mt-1 font-bold">{{ $req->title }}</p>
                        <p class="text-xs text-[var(--muted-foreground)] mt-1.5 leading-relaxed line-clamp-3">{{ $req->description }}</p>

                        <div class="mt-3 text-[11px] text-[var(--muted-foreground)] space-y-1">
                            <div><i class="fa-solid fa-location-dot text-[var(--accent-dark)] mr-1"></i> {{ $req->pickup_address ?? 'Dhaka, Bangladesh' }}</div>
                            <div><i class="fa-solid fa-clock text-[var(--accent-dark)] mr-1"></i> Posted {{ $req->created_at?->diffForHumans() }} • {{ $req->bids->count() }} active bids</div>
                        </div>
                    </div>

                    <div class="mt-4 pt-3 border-t border-[var(--border)] flex items-center justify-between text-[11px]">
                        <span class="text-[var(--muted-foreground)]">Diagnostic summary</span>
                        <a href="/vendor/request-details/{{ $req->id }}" class="font-bold text-[var(--accent-dark)] hover:underline">
                            View Full Ticket Details &rarr;
                        </a>
                    </div>
                </div>

                <!-- Right: Submit Diagnostic Estimate Form -->
                <div class="lg:col-span-2 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-4 sm:p-5">
                    <h4 class="font-bold text-sm text-[var(--foreground)] mb-3 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[var(--accent-dark)]"></i>
                        Submit Verified Estimate on Ticket #{{ $req->id }}
                    </h4>

                    <form method="POST" action="{{ route('vendor.requests.bid', $req->id) }}" class="space-y-3.5 text-xs">
                        @csrf
                        @if(isset($vendorShops) && count($vendorShops) > 1)
                            <div>
                                <label class="block font-bold text-[var(--foreground)] mb-1">Bidding Storefront / Branch</label>
                                <select name="shop_id" class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] p-2.5 text-xs font-semibold outline-none">
                                    @foreach($vendorShops as $vShop)
                                        <option value="{{ $vShop->id }}">
                                            {{ $vShop->name }}{{ $vShop->display_branch_tag ? ' [' . $vShop->display_branch_tag . ']' : '' }} — {{ $vShop->area }}, {{ $vShop->city }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                        @elseif(isset($vendorShops) && count($vendorShops) === 1)
                            <input type="hidden" name="shop_id" value="{{ $vendorShops->first()->id }}">
                        @endif

                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block font-bold text-[var(--foreground)] mb-1">Parts (৳) *</label>
                                <input type="number" name="parts_cost" placeholder="1800" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] text-xs p-2.5 outline-none" required>
                            </div>
                            <div>
                                <label class="block font-bold text-[var(--foreground)] mb-1">Labor (৳) *</label>
                                <input type="number" name="labor_cost" placeholder="1200" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] text-xs p-2.5 outline-none" required>
                            </div>
                            <div>
                                <label class="block font-bold text-[var(--foreground)] mb-1">Days *</label>
                                <input type="number" name="estimated_days" value="2" min="1" max="30" class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] text-xs p-2.5 outline-none" required>
                            </div>
                            <div>
                                <label class="block font-bold text-[var(--foreground)] mb-1">Warranty (Days)</label>
                                <input type="number" name="warranty_days" value="90" min="0" class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] text-xs p-2.5 outline-none">
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Technician Diagnostic Note</label>
                            <textarea name="technician_note" rows="2" placeholder="Includes ultrasonic motherboard cleaning, OEM replacement parts, and bench stress testing." class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] text-xs p-2.5 leading-relaxed outline-none"></textarea>
                        </div>

                        <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                            <span class="text-[11px] text-[var(--muted-foreground)]">
                                10.00% Platform Escrow Commission automatically accounted on completion
                            </span>
                            <button type="submit" class="rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-xs font-bold px-6 py-2.5 shadow hover:opacity-90 transition">
                                Submit Estimate &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-10 text-center shadow-sm">
                <i class="fa-solid fa-gavel text-3xl text-[var(--muted-foreground)] mb-3 block"></i>
                <h3 class="text-lg font-black text-[var(--foreground)]">No open marketplace tickets awaiting estimates</h3>
                <p class="text-xs text-[var(--muted-foreground)] mt-1">New customer repair requests will appear here automatically as they are published.</p>
                <a href="/vendor/dashboard" class="mt-4 inline-flex items-center gap-2 rounded-full bg-[var(--accent-dark)] px-6 py-2.5 text-xs font-bold text-[var(--accent-foreground)]">
                    Return to Vendor Dashboard &rarr;
                </a>
            </div>
        @endforelse
    </div>

</div>
@endsection

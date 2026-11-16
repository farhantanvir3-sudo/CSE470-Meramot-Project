{{--
=============================================================================
FEATURE R13: FastAPI GROQ/Gemini AI Request Summary & Hardware Triage Workspace
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
Architectural Layer: View (Vendor/RequestDetails)
Controller: AISummaryController@showRequestDetails, regenerateSummary
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'AI Request Triage & Quote Workspace | Meramot')

@section('content')
@php
    $req = $repairRequest ?? null;
    $triage = is_array($triageData ?? null) ? $triageData : (is_array($req?->ai_triage_json) ? $req->ai_triage_json : []);
    $suspectedFaults = is_array($triage['suspected_faults'] ?? null) ? $triage['suspected_faults'] : [];
    $suggestedParts = is_array($triage['suggested_parts'] ?? null) ? $triage['suggested_parts'] : [];
    $riskLevel = strtoupper((string) ($triage['risk_level'] ?? 'MEDIUM'));
    $riskClasses = match ($riskLevel) {
        'HIGH' => 'bg-rose-100 text-rose-900 border-rose-300',
        'LOW' => 'bg-emerald-100 text-emerald-900 border-emerald-300',
        default => 'bg-amber-100 text-amber-900 border-amber-300',
    };
    $benchHours = $triage['estimated_bench_hours'] ?? 2.0;
    $costMin = $triage['estimated_cost_min'] ?? null;
    $costMax = $triage['estimated_cost_max'] ?? null;
    $providerLabel = $triage['provider'] ?? 'hardware-triage-engine';
    $modelLabel = $triage['model_name'] ?? 'meramot-hardware-triage-v2';
    $photos = is_array($req?->photos) ? $req->photos : [];
@endphp

<div class="mx-auto max-w-6xl px-4 py-6 md:px-6 space-y-6" x-data="{ lightboxPhoto: null }">
    <!-- Header Bar -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 border-b border-[var(--border)] pb-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
                <a href="/vendor/dashboard" class="hover:underline">Vendor Dashboard</a>
                <span>&rsaquo;</span>
                <a href="/vendor/bidding-board" class="hover:underline">Live Bidding Board</a>
                <span>&rsaquo;</span>
                <span class="font-semibold text-[var(--foreground)]">Ticket #{{ $req?->id ?? 'Preview' }}</span>
            </div>
            <h1 class="text-2xl md:text-3xl font-bold text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-microchip text-[var(--accent-dark)]"></i>
                <span>AI Hardware Fault Triage &amp; Quote Builder</span>
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R13 • Farhan Tanvir [22299179]
                </span>
                <span>Automated component-level symptom triage, bench labor estimation, and 1-click parts pre-fill.</span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            <a href="/vendor/bidding-board" class="rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
                &larr; Bidding Board
            </a>
            @if($req)
                <form method="POST" action="/vendor/request-details/{{ $req->id }}/regenerate" class="inline">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                        <i class="fa-solid fa-arrows-rotate"></i>
                        <span>Regenerate AI Triage</span>
                    </button>
                </form>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-400 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900 flex items-center gap-2 shadow-sm">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(!$req)
        <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-10 text-center">
            <h2 class="text-lg font-bold text-[var(--foreground)]">No repair tickets published yet</h2>
            <p class="mt-1 text-xs text-[var(--muted-foreground)]">Once a customer posts a diagnostic repair request, full AI hardware triage appears here.</p>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
            <!-- Left Column (7 cols): Customer Report + AI Hardware Fault Triage -->
            <div class="lg:col-span-7 space-y-6 min-w-0">
                <!-- Customer Fault Submission Card -->
                <section class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm space-y-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <span class="rounded bg-[var(--accent-dark)] px-3 py-0.5 text-[10px] font-bold uppercase text-[var(--accent-foreground)]">
                                Ticket #{{ $req->id }} • {{ $req->status }}
                            </span>
                            <h2 class="mt-2 text-xl font-bold text-[var(--foreground)] break-words">
                                {{ $req->brand }} {{ $req->model_name }} ({{ $req->device_type }})
                            </h2>
                            <p class="text-sm font-bold text-[var(--accent-dark)] mt-0.5 break-words">{{ $req->title }}</p>
                        </div>
                        <div class="text-right text-xs text-[var(--muted-foreground)]">
                            <p class="font-bold text-[var(--foreground)]">{{ $req->customer?->name ?? 'Verified Customer' }}</p>
                            <p>{{ $req->preferred_pickup ? 'Doorstep Courier Pickup' : 'In-Shop Walk-In' }}</p>
                            <p>{{ $req->created_at?->diffForHumans() }}</p>
                        </div>
                    </div>

                    <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/75 p-4 text-xs text-[var(--foreground)] leading-relaxed whitespace-pre-line">{{ $req->problem ?: $req->description }}</div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-3">
                            <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Pickup / Service Area</span>
                            <span class="font-bold text-[var(--foreground)] mt-0.5 block break-words">{{ $req->pickup_address ?: 'Dhaka Metropolitan Area' }}</span>
                        </div>
                        <div class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/50 p-3">
                            <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Category &amp; Serial</span>
                            <span class="font-bold text-[var(--foreground)] mt-0.5 block">{{ $req->issue_category ?: 'Hardware Diagnostics' }}{{ $req->serial_number ? ' • S/N: ' . $req->serial_number : '' }}</span>
                        </div>
                    </div>

                    @if(count($photos) > 0)
                        <div class="pt-2">
                            <span class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)] block mb-2">Customer Uploaded Evidence ({{ count($photos) }})</span>
                            <div class="grid grid-cols-3 sm:grid-cols-4 gap-2.5">
                                @foreach($photos as $photo)
                                    @php
                                        $pUrl = (str_starts_with($photo, 'http') || str_starts_with($photo, 'data:') || str_starts_with($photo, '/'))
                                            ? $photo
                                            : asset('storage/' . $photo);
                                    @endphp
                                    <button type="button" @click="lightboxPhoto = @js($pUrl)" class="overflow-hidden rounded-xl border border-[var(--border)] group">
                                        <img src="{{ $pUrl }}" alt="Device evidence" class="h-20 w-full object-cover group-hover:scale-105 transition">
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </section>

                <!-- Structured AI Hardware Triage Report Card -->
                <section class="rounded-lg border-2 border-[var(--accent-dark)]/25 bg-[var(--card)] p-5 md:p-6 shadow-sm space-y-4">
                    <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] pb-3">
                        <div class="flex items-center gap-2">
                            <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-[var(--accent-dark)] text-[var(--accent-foreground)]">
                                <i class="fa-solid fa-wand-magic-sparkles text-sm"></i>
                            </span>
                            <div>
                                <h3 class="text-base font-bold text-[var(--foreground)]">AI Diagnostic Brief</h3>
                                <p class="text-[11px] text-[var(--muted-foreground)]">Engine: <span class="font-mono font-semibold">{{ $providerLabel }} ({{ $modelLabel }})</span></p>
                            </div>
                        </div>
                        <span class="rounded-md border px-3 py-1 text-[11px] font-bold {{ $riskClasses }}">
                            {{ $riskLevel }} BENCH RISK
                        </span>
                    </div>

                    <p class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)] p-4 text-xs sm:text-sm font-semibold text-[var(--foreground)] leading-relaxed">
                        {{ $req->ai_summary ?: 'Hardware diagnostic triage ready.' }}
                    </p>

                    <!-- Triage KPI Strip -->
                    <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
                        <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/65 p-3">
                            <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Est. Bench Time</span>
                            <span class="text-base font-bold text-[var(--accent-dark)]">{{ number_format((float) $benchHours, 1) }} hrs</span>
                        </div>
                        <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/65 p-3">
                            <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Market Reference Band</span>
                            <span class="text-sm font-bold text-[var(--accent-dark)]">
                                @if($costMin && $costMax)
                                    ৳{{ number_format((int) $costMin) }} – ৳{{ number_format((int) $costMax) }}
                                @else
                                    Bench Quote
                                @endif
                            </span>
                        </div>
                        <div class="col-span-2 sm:col-span-1 rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/65 p-3">
                            <span class="text-[10px] font-bold uppercase text-[var(--muted-foreground)] block">Competing Bids</span>
                            <span class="text-base font-bold text-[var(--foreground)]">{{ $req->bids->count() }} Active</span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
                        <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/50 p-4">
                            <h4 class="font-bold text-[var(--foreground)] mb-2 flex items-center gap-1.5">
                                <i class="fa-solid fa-bolt text-amber-700"></i>
                                <span>Suspected Fault Points</span>
                            </h4>
                            <ul class="space-y-1.5 text-[var(--muted-foreground)]">
                                @forelse($suspectedFaults as $fault)
                                    @php
                                        $faultText = is_array($fault) ? ($fault['title'] ?? $fault['name'] ?? json_encode($fault)) : (string) $fault;
                                    @endphp
                                    <li class="flex items-start gap-2">
                                        <span class="text-[var(--accent-dark)] font-bold">&bull;</span>
                                        <span class="text-[var(--foreground)]">{{ $faultText }}</span>
                                    </li>
                                @empty
                                    <li>Physical board &amp; connector bench inspection recommended.</li>
                                @endforelse
                            </ul>
                        </div>

                        <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/50 p-4 flex flex-col justify-between gap-3">
                            <div>
                                <h4 class="font-bold text-[var(--foreground)] mb-2 flex items-center gap-1.5">
                                    <i class="fa-solid fa-screwdriver-wrench text-[var(--accent-dark)]"></i>
                                    <span>Candidate Replacement Parts</span>
                                </h4>
                                <ul class="space-y-1.5 text-[var(--muted-foreground)]">
                                    @forelse($suggestedParts as $part)
                                        @php
                                            $partName = is_array($part) ? ($part['name'] ?? $part['title'] ?? 'Replacement Component') : (string) $part;
                                            $partEst = is_array($part) ? ($part['estimated_cost'] ?? $part['cost'] ?? null) : null;
                                        @endphp
                                        <li class="flex items-start justify-between gap-2">
                                            <span class="flex items-start gap-2">
                                                <i class="fa-solid fa-check text-emerald-700 mt-0.5 text-[10px]"></i>
                                                <span class="text-[var(--foreground)] font-medium">{{ $partName }}</span>
                                            </span>
                                            @if($partEst)
                                                <span class="font-mono text-[11px] font-bold text-[var(--accent-dark)] shrink-0">~৳{{ number_format((float) $partEst) }}</span>
                                            @endif
                                        </li>
                                    @empty
                                        <li>Diagnostic bench consumables</li>
                                    @endforelse
                                </ul>
                            </div>

                            @if(count($suggestedParts) > 0 && in_array(strtoupper((string) $req->status), ['PENDING', 'BIDDING'], true))
                                <button
                                    type="button"
                                    @click="$dispatch('meramot-prefill-quote-parts', { requestId: {{ (int) $req->id }}, parts: @js($suggestedParts) })"
                                    class="w-full rounded-md bg-[var(--accent-dark)] px-3.5 py-2 text-[11px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition"
                                >
                                    <i class="fa-solid fa-wand-magic-sparkles mr-1"></i>1-Click Pre-Fill Parts into Quote Builder
                                </button>
                            @endif
                        </div>
                    </div>
                </section>
            </div>

            <!-- Right Column (5 cols): Itemized Quote Submission & Audit History -->
            <div class="lg:col-span-5 space-y-5 min-w-0 lg:sticky lg:top-6">
                <section class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 md:p-6 shadow-sm">
                    <h3 class="text-base font-bold text-[var(--foreground)] mb-1 flex items-center gap-2">
                        <i class="fa-solid fa-file-invoice-dollar text-[var(--accent-dark)]"></i>
                        <span>Submit Workshop Quote</span>
                    </h3>
                    <p class="text-xs text-[var(--muted-foreground)] mb-4">
                        Itemize parts, warranty days, and bench labor for Ticket #{{ $req->id }}.
                    </p>

                    @if(in_array(strtoupper((string) $req->status), ['PENDING', 'BIDDING'], true))
                        @include('vendor.partials.bid-quote-widget', [
                            'req' => $req,
                            'shopOptions' => $vendorShops ?? collect(),
                            'lockedShopId' => null,
                        ])
                    @else
                        <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)] p-4 text-xs text-[var(--foreground)]">
                            Bidding on this ticket is closed (Current Status: <strong>{{ $req->status }}</strong>).
                        </div>
                    @endif
                </section>

                @if(($summaryLogs ?? collect())->isNotEmpty())
                    <section class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm space-y-2.5 text-xs">
                        <h4 class="font-bold text-[var(--foreground)] uppercase tracking-wider text-[11px]">AI Triage Audit Trail</h4>
                        @foreach($summaryLogs as $log)
                            <div class="rounded-xl border border-[var(--border)] bg-[var(--mint-100)]/60 px-3 py-2 flex items-center justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="font-mono font-bold text-[11px] text-[var(--foreground)] truncate">{{ $log->provider }} • {{ $log->model_name }}</p>
                                    <p class="text-[10px] text-[var(--muted-foreground)]">{{ $log->created_at?->diffForHumans() }} • Risk: {{ $log->risk_level }}</p>
                                </div>
                                <span class="rounded bg-[var(--card)] border border-[var(--border)] px-2.5 py-0.5 font-mono text-[10px] font-bold text-[var(--accent-dark)] shrink-0">
                                    {{ $log->latency_ms ?? 0 }} ms
                                </span>
                            </div>
                        @endforeach
                    </section>
                @endif
            </div>
        </div>
    @endif

    <!-- Photo Lightbox -->
    <div x-show="lightboxPhoto" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/80 p-4" @click="lightboxPhoto = null">
        <img :src="lightboxPhoto" alt="Zoomed device photo" class="max-h-[88vh] max-w-[92vw] rounded-lg shadow-2xl">
    </div>
</div>
@endsection

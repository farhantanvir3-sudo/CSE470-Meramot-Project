{{--
=============================================================================
FEATURE R13: FastAPI GROQ/Gemini AI Request Summary Microservice
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
Architectural Layer: View (Vendor/RequestDetails)
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'AI Request Triage & Summary | Meramot')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6 md:px-6 space-y-5">
    <div class="rounded-[1.75rem] border border-amber-300 bg-amber-50/90 p-4 text-xs flex items-center justify-between gap-4">
        <div>
            <p class="font-black text-amber-950">FastAPI Technician Fault Triage — Scheduled for Sprint 2</p>
            <p class="text-amber-900 mt-0.5">Automated GROQ / Gemini motherboard symptom extraction launches in Sprint 2. Live Marketplace Bidding is active right now on the Vendor Dashboard.</p>
        </div>
        <a href="/vendor/dashboard" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 font-bold text-[var(--accent-foreground)] shrink-0">
            Vendor Dashboard &rarr;
        </a>
    </div>

    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm">
        <div class="mb-6 flex flex-wrap justify-between items-center gap-3">
            <div>
                <h1 class="text-2xl font-black text-[var(--foreground)]">AI Fault Diagnostic Summary</h1>
                <p class="text-xs text-[var(--muted-foreground)] mt-1 dev-only-badge">Feature R13 | Assigned to: <strong class="text-[var(--accent-dark)]">Farhan Tanvir [22299179]</strong></p>
            </div>
            <span class="dev-only-badge text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3.5 py-1.5 rounded-full border border-[var(--border)]">
                Sprint 2 Preview
            </span>
        </div>

        <div class="space-y-4 text-xs">
            <div class="p-4 bg-[var(--mint-100)] border border-[var(--border)] rounded-2xl">
                <span class="text-[var(--muted-foreground)] font-mono uppercase block mb-1">Sample Customer Submission</span>
                <p class="text-[var(--foreground)] font-semibold text-sm">"Laptop turned off suddenly while gaming, fan started spinning at maximum speed, now battery light blinks orange 3 times."</p>
            </div>

            <div class="p-5 bg-[var(--mint-100)] border border-[var(--border)] rounded-2xl space-y-3">
                <span class="text-[var(--accent-dark)] font-black block text-sm"><i class="fa-solid fa-wand-magic-sparkles mr-1"></i> Structured Hardware Triage Preview:</span>
                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <span class="text-[var(--foreground)] font-bold block">Probable Fault Points:</span>
                        <ul class="list-disc list-inside text-[var(--muted-foreground)] mt-1 space-y-0.5">
                            <li>MOSFET short on GPU Vcore rail</li>
                            <li>Overheating thermal cutoff triggered</li>
                        </ul>
                    </div>
                    <div>
                        <span class="text-[var(--foreground)] font-bold block">Recommended Bench Actions:</span>
                        <ul class="list-disc list-inside text-[var(--muted-foreground)] mt-1 space-y-0.5">
                            <li>Multimeter short check across power inductors</li>
                            <li>Thermal repaste and fan ultrasonic bath</li>
                        </ul>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <a href="/coming-soon/ai-request-triage" class="rounded-full bg-[var(--accent-dark)] px-5 py-2.5 font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                    Run Live FastAPI Summary (Sprint 2)
                </a>
                <a href="/vendor/bidding-board" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] px-5 py-2.5 font-bold text-[var(--foreground)] transition">
                    Back to Live Bidding Board
                </a>
            </div>
        </div>
    </div>
</div>
@endsection

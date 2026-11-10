{{--
=============================================================================
FEATURE R13: FastAPI GROQ/Gemini AI Request Summary Microservice
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
Architectural Layer: View (Vendor/RequestDetails)
Controller: AISummaryController@generateSummary
Models Touched: AISummaryLog

INSTRUCTIONS FOR FARHAN:
- Display technical triage view for technicians.
- Trigger AI microservice POST /api/ai/summarize-request to parse customer symptoms.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'AI Request Triage & Summary | Meramot')

@section('content')
<div class="max-w-3xl mx-auto bg-white rounded-xl border border-slate-200 p-8 shadow-sm">
    <div class="mb-6 flex justify-between items-center">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">AI Fault Diagnostic Summary</h1>
            <p class="text-xs text-slate-500 mt-1 dev-only-badge">Feature R13 | Assigned to: <strong class="text-teal-700">Farhan Tanvir [22299179]</strong></p>
        </div>
        <span class="text-xs bg-purple-100 text-purple-800 font-semibold px-3 py-1 rounded-full border border-purple-200">
            FastAPI AI Microservice
        </span>
    </div>

    <div class="space-y-4 text-xs">
        <div class="p-4 bg-slate-50 rounded-lg">
            <span class="text-slate-400 font-mono uppercase block mb-1">Raw Customer Submission</span>
            <p class="text-slate-800 font-medium text-sm">"Laptop turned off suddenly while gaming, fan started spinning at maximum speed, now battery light blinks orange 3 times."</p>
        </div>

        <div class="p-5 bg-purple-50 border border-purple-200 rounded-lg space-y-3">
            <span class="text-purple-800 font-bold block text-sm"><i class="fa-solid fa-wand-magic-sparkles mr-1"></i> AI Structured Hardware Triage:</span>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <span class="text-purple-700 font-semibold block">Probable Fault Points:</span>
                    <ul class="list-disc list-inside text-slate-700 mt-1 space-y-0.5">
                        <li>MOSFET short on GPU Vcore rail</li>
                        <li>Overheating thermal cutoff triggered</li>
                    </ul>
                </div>
                <div>
                    <span class="text-purple-700 font-semibold block">Recommended Bench Actions:</span>
                    <ul class="list-disc list-inside text-slate-700 mt-1 space-y-0.5">
                        <li>Multimeter short check across power inductors</li>
                        <li>Thermal repaste and fan ultrasonic bath</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

{{--
=============================================================================
FEATURE R15: Vendor Financial Analytics & Performance Dashboard
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
Architectural Layer: View (Vendor/Dashboard)
Controller: VendorAnalyticsController@getPerformanceMetrics
Models Touched: VendorAnalytics

INSTRUCTIONS FOR FARHAN:
- Vendor metrics: Bidding win rate percentage, net revenue, pending jobs, average customer rating.
- Recharts or Chart.js visual overview of monthly repair completions.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Vendor Performance Analytics | Meramot')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Vendor Performance Analytics</h1>
        <p class="text-xs text-slate-500 mt-1 dev-only-badge">Feature R15 | Assigned to: <strong class="text-teal-700">Farhan Tanvir [22299179]</strong></p>
    </div>
    <span class="dev-only-badge text-xs bg-rose-100 text-rose-800 font-semibold px-3 py-1 rounded-full border border-rose-200">
        Sprint 4 Target
    </span>
</div>

<div class="grid grid-cols-1 sm:grid-cols-4 gap-5 mb-8">
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <span class="text-xs text-slate-400 block uppercase font-medium">Bids Submitted</span>
        <span class="text-2xl font-black text-slate-900">48</span>
    </div>
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <span class="text-xs text-slate-400 block uppercase font-medium">Jobs Won</span>
        <span class="text-2xl font-black text-emerald-600">36</span>
    </div>
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <span class="text-xs text-slate-400 block uppercase font-medium">Win Rate</span>
        <span class="text-2xl font-black text-indigo-600">75.0%</span>
    </div>
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm">
        <span class="text-xs text-slate-400 block uppercase font-medium">Customer Rating</span>
        <span class="text-2xl font-black text-amber-500">★ 4.88</span>
    </div>
</div>
@endsection

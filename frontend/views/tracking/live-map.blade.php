{{--
=============================================================================
FEATURE R4: Live Delivery Agent GPS Location & Route Sharing
=============================================================================
Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
Architectural Layer: View (Tracking/LiveMap)
Controller: TrackingController@showMap, pingLocation
Models Touched: LocationLog

INSTRUCTIONS FOR NAFIUL:
- Embed Leaflet JS map container (#map).
- Plot current coordinates of active rider marker and customer destination marker.
- Listen to Pusher WebSocket channel 'tracking.{jobId}' to dynamically shift rider marker.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Live GPS Tracking Map | Meramot')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Real-Time Delivery Route Tracking</h1>
            <p class="text-xs text-slate-500 mt-1 dev-only-badge">Feature R4 | Assigned to: <strong class="text-teal-700">Md. Nafiul Islam [22299035]</strong></p>
        </div>
        <span class="dev-only-badge text-xs bg-amber-100 text-amber-800 font-semibold px-3 py-1 rounded-full border border-amber-200">
            Sprint 3 Target
        </span>
    </div>
</div>

@php
    $resolvedLat = (float) ($latestLocation->latitude ?? $job->deliveryAgent->current_latitude ?? 23.7461);
    $resolvedLng = (float) ($latestLocation->longitude ?? $job->deliveryAgent->current_longitude ?? 90.3742);
    $riderLabel = $job->rider_name ?? $job->deliveryAgent?->user?->name ?? 'Assigned Meramot Courier';
@endphp

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-600">
        <div>
            <span class="font-bold text-slate-900">{{ $job->tracking_code ?? ('Delivery Job #' . $job->id) }}</span>
            <span class="mx-1.5">•</span>
            <span>Courier: <strong>{{ $riderLabel }}</strong></span>
            <span class="mx-1.5">•</span>
            <span class="uppercase font-semibold text-teal-700">{{ $job->status }}</span>
        </div>
        @if($job->repairRequest)
            <a href="{{ route('requests.track', $job->repairRequest->id) }}" class="font-bold text-teal-700 hover:underline">
                Order #{{ $job->repairRequest->id }}: {{ $job->repairRequest->title }} &rarr;
            </a>
        @endif
    </div>

    <!-- Map Canvas Container -->
    <div id="map" class="w-full h-96 rounded-lg bg-slate-100 z-10"></div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-2 text-xs text-slate-600 bg-slate-50 rounded-lg p-3 border border-slate-100">
        <div><span class="font-bold text-slate-800">Pickup:</span> {{ $job->pickup_address }}</div>
        <div><span class="font-bold text-slate-800">Dropoff:</span> {{ $job->dropoff_address }}</div>
    </div>

    <div class="flex justify-between items-center text-xs text-slate-500 font-mono">
        <span>Delivery Job #{{ $job->id }} ({{ $job->direction }})</span>
        <span>Lat: {{ number_format($resolvedLat, 6) }} | Lng: {{ number_format($resolvedLng, 6) }}</span>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var lat = @json($resolvedLat);
        var lng = @json($resolvedLng);
        var popupLabel = @json($riderLabel . ' • ' . ($job->status ?? 'IN_TRANSIT'));

        var map = L.map('map').setView([lat, lng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        L.marker([lat, lng]).addTo(map).bindPopup(popupLabel).openPopup();
    });
</script>
@endsection

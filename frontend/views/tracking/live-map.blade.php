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

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-4">
    <!-- Map Canvas Container -->
    <div id="map" class="w-full h-96 rounded-lg bg-slate-100 z-10"></div>
    <div class="mt-4 flex justify-between items-center text-xs text-slate-500 font-mono">
        <span>Delivery Job #{{ $job->id ?? 'DEMO-101' }}</span>
        <span>Lat: {{ $latestLocation->latitude ?? 23.7461 }} | Lng: {{ $latestLocation->longitude ?? 90.3742 }}</span>
    </div>
</div>

<script>
    document.addEventListener("DOMContentLoaded", function () {
        var map = L.map('map').setView([23.7461, 90.3742], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            maxZoom: 19,
            attribution: '© OpenStreetMap'
        }).addTo(map);

        var riderMarker = L.marker([23.7461, 90.3742]).addTo(map).bindPopup('Delivery Agent in Transit').openPopup();

        // TODO (Nafiul): Connect to Pusher and update riderMarker coordinates dynamically
    });
</script>
@endsection

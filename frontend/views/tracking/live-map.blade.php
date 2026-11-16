{{--
=============================================================================
FEATURE R4: Live Delivery Agent GPS Location & Route Sharing
=============================================================================
Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
Sprint: Sprint 3
Architectural Layer: View (Tracking/LiveMap)
Controller: TrackingController@showMap, liveTelemetry, pingLocation
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Live Courier GPS Tracking | Meramot')

@section('content')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<div class="mx-auto max-w-6xl px-4 py-6 md:px-6 space-y-5">
    @if($notFoundId ?? null)
        <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-8 text-center space-y-4 shadow-sm">
            <div class="mx-auto h-14 w-14 rounded-lg bg-[var(--mint-100)] border border-[var(--border)] flex items-center justify-center text-[var(--accent-dark)]">
                <i class="fa-solid fa-location-crosshairs text-xl"></i>
            </div>
            <h1 class="text-2xl font-bold text-[var(--foreground)]">Live Courier GPS Radar</h1>
            <p class="text-xs text-[var(--muted-foreground)] max-w-md mx-auto">
                Dispatch reference <span class="font-mono font-bold">#{{ $notFoundId }}</span> has no active courier trip yet. Once a rider accepts your pickup or return leg, live GPS coordinates and ETA appear here automatically.
            </p>
            <div class="flex justify-center gap-3 pt-2">
                <a href="/requests" class="rounded-md bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                    View My Repair Orders
                </a>
                <a href="/" class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-5 py-2.5 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                    Back to Marketplace
                </a>
            </div>
        </div>
    @else
        @php
            $t = $telemetry;
            $milestones = [
                ['key' => 'PENDING', 'label' => 'Pool Queued', 'icon' => 'fa-clock'],
                ['key' => 'DISPATCHED', 'label' => 'Rider Assigned', 'icon' => 'fa-motorcycle'],
                ['key' => 'PICKED_UP', 'label' => 'Picked Up', 'icon' => 'fa-box-open'],
                ['key' => 'IN_TRANSIT', 'label' => 'En Route', 'icon' => 'fa-route'],
                ['key' => 'DELIVERED', 'label' => 'Handover Verified', 'icon' => 'fa-circle-check'],
            ];
            $statusOrder = ['PENDING' => 0, 'SCHEDULED' => 1, 'DISPATCHED' => 1, 'PICKED_UP' => 2, 'IN_TRANSIT' => 3, 'DELIVERED' => 4];
            $initialStep = $statusOrder[$t['status']] ?? 0;
        @endphp

        <div x-data="meramotLiveTrackingMap(@js($t), @js($statusOrder))" x-init="boot()" class="space-y-5">
            <!-- Header & Live Polling Badge -->
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
                        <a href="/" class="hover:underline">Home</a>
                        @if($repairRequest)
                            <span>›</span>
                            <a href="{{ route('requests.track', $repairRequest->id) }}" class="hover:underline font-semibold text-[var(--accent-dark)]">
                                Order #{{ $repairRequest->id }}
                            </a>
                        @endif
                        <span>›</span>
                        <span>Live GPS Telemetry</span>
                    </div>
                    <h1 class="text-2xl font-bold text-[var(--foreground)]">Real-Time Courier Route &amp; ETA</h1>
                    <p class="text-xs text-[var(--muted-foreground)] mt-0.5">
                        Ref: <span class="font-mono font-bold text-[var(--foreground)]" x-text="state.tracking_code"></span>
                        • Leg: <span class="font-bold" x-text="state.direction === 'TO_CUSTOMER' ? 'Workshop → Customer Return' : 'Customer → Workshop Pickup'"></span>
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2.5">
                    <button type="button" @click="toggleAutoRefresh()"
                            :class="autoRefresh ? 'bg-emerald-700 text-white' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)]'"
                            class="inline-flex items-center gap-2 rounded-md px-4 py-2 text-xs font-semibold transition shadow-sm">
                        <span class="h-2 w-2 rounded-full" :class="autoRefresh ? 'bg-emerald-200 animate-ping' : 'bg-slate-400'"></span>
                        <span x-text="autoRefresh ? 'Live 5s Radar ON' : 'Live Radar Paused'"></span>
                    </button>
                    <button type="button" @click="pollNow()"
                            class="inline-flex items-center gap-1.5 rounded-md border border-[var(--border)] bg-[var(--card)] hover:bg-[var(--mint-100)] px-4 py-2 text-xs font-bold text-[var(--foreground)] transition">
                        <i class="fa-solid fa-arrows-rotate" :class="loading ? 'fa-spin' : ''"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- 5-Step Courier Milestone Progress Strip -->
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 md:p-5 shadow-sm">
                <div class="grid grid-cols-5 gap-2 text-center">
                    @foreach($milestones as $idx => $ms)
                        <div class="flex flex-col items-center gap-1.5">
                            <div :class="currentStep >= {{ $idx }} ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow' : 'bg-[var(--mint-100)] text-[var(--muted-foreground)] border border-[var(--border)]'"
                                 class="h-9 w-9 rounded-full flex items-center justify-center text-xs transition">
                                <i class="fa-solid {{ $ms['icon'] }}"></i>
                            </div>
                            <span class="text-[10px] sm:text-xs font-bold leading-tight"
                                  :class="currentStep >= {{ $idx }} ? 'text-[var(--foreground)]' : 'text-[var(--muted-foreground)]'">
                                {{ $ms['label'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- Main Map + Telemetry Sidebar Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
                <div class="lg:col-span-8 rounded-lg border border-[var(--border)] bg-[var(--card)] p-3 shadow-sm space-y-3">
                    <div class="relative">
                        <div id="map" class="w-full h-[420px] rounded-lg bg-[var(--mint-100)] z-10"></div>

                        <!-- Floating ETA Pill -->
                        <div class="absolute top-3 right-3 z-[500] rounded-lg bg-[var(--accent-dark)]/95 text-white px-4 py-2.5 shadow-lg text-xs">
                            <p class="text-[10px] uppercase tracking-wider text-emerald-200 font-bold">Estimated Arrival</p>
                            <p class="text-base font-bold" x-text="state.status === 'DELIVERED' ? 'Delivered' : (state.eta_minutes + ' mins (' + state.distance_km + ' km)')"></p>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-2 px-2 py-1 text-[11px] text-[var(--muted-foreground)]">
                        <div class="flex flex-wrap items-center gap-3 font-semibold">
                            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-blue-600"></span> Pickup Point</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-emerald-700"></span> Destination</span>
                            <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-full bg-[#163625]"></span> Live Courier</span>
                        </div>
                        <span class="font-mono">Rider GPS: <span x-text="state.rider_lat.toFixed(5) + ', ' + state.rider_lng.toFixed(5)"></span></span>
                    </div>
                </div>

                <!-- Right Telemetry Column -->
                <div class="lg:col-span-4 space-y-4">
                    <!-- Courier Card -->
                    <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm space-y-3 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="text-[10px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">Assigned Courier</span>
                            <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-bold uppercase text-[var(--accent-dark)]" x-text="state.status"></span>
                        </div>
                        <div class="flex items-center gap-3">
                            <div class="h-11 w-11 rounded-lg bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center font-bold text-sm">
                                <i class="fa-solid fa-helmet-safety"></i>
                            </div>
                            <div class="min-w-0">
                                <p class="text-sm font-bold text-[var(--foreground)] truncate" x-text="state.rider_name"></p>
                                <p class="text-[11px] text-[var(--muted-foreground)]">
                                    <span x-text="state.vehicle_type"></span> • <span x-text="state.type"></span> Dispatch
                                </p>
                            </div>
                        </div>

                        @if($job)
                            <div class="flex flex-wrap gap-2 pt-1">
                                <a href="/support/rider-chat/{{ $job->id }}" class="flex-1 text-center rounded-md bg-[var(--accent-dark)] py-2.5 px-3 font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                                    <i class="fa-solid fa-comments mr-1"></i> Rider Chat
                                </a>
                                @if(!empty($t['rider_phone']))
                                    <a href="tel:{{ $t['rider_phone'] }}" class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] py-2.5 px-4 font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                                        <i class="fa-solid fa-phone"></i>
                                    </a>
                                @endif
                            </div>
                        @endif
                    </div>

                    <!-- Route Waypoints Card -->
                    <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm space-y-3 text-xs">
                        <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">Route Waypoints</p>
                        <div class="space-y-2.5">
                            <div class="rounded-xl bg-[var(--mint-100)] p-3 border border-[var(--border)]">
                                <p class="text-[10px] font-semibold text-blue-700 uppercase">Origin / Pickup</p>
                                <p class="font-bold text-[var(--foreground)] mt-0.5">{{ $t['pickup_address'] }}</p>
                            </div>
                            <div class="rounded-xl bg-[var(--mint-100)] p-3 border border-[var(--border)]">
                                <p class="text-[10px] font-semibold text-emerald-800 uppercase">Destination / Dropoff</p>
                                <p class="font-bold text-[var(--foreground)] mt-0.5">{{ $t['dropoff_address'] }}</p>
                            </div>
                        </div>
                        <p class="text-[11px] text-[var(--muted-foreground)] flex justify-between">
                            <span>Last GPS Ping:</span>
                            <span class="font-semibold text-[var(--foreground)]" x-text="state.last_ping_at"></span>
                        </p>
                    </div>

                    <!-- Owner-Only Handover OTP Security Card -->
                    @if($canViewOtp && $job)
                        <div class="rounded-lg border border-emerald-300 bg-emerald-50/90 p-5 shadow-sm space-y-2 text-xs">
                            <p class="text-[10px] font-bold uppercase tracking-wider text-emerald-900">
                                <i class="fa-solid fa-key mr-1"></i> Secure Chain-of-Custody OTP
                            </p>
                            <p class="text-[11px] text-emerald-900/85">Share this 6-digit PIN only when handing over or receiving your device in person:</p>
                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <div class="rounded-xl bg-white border border-emerald-200 p-2.5 text-center">
                                    <span class="text-[10px] text-emerald-800 font-bold block">Pickup OTP</span>
                                    <span class="font-mono text-base font-bold tracking-widest text-emerald-950">{{ $job->pickup_otp ?? '—' }}</span>
                                </div>
                                <div class="rounded-xl bg-white border border-emerald-200 p-2.5 text-center">
                                    <span class="text-[10px] text-emerald-800 font-bold block">Dropoff OTP</span>
                                    <span class="font-mono text-base font-bold tracking-widest text-emerald-950">{{ $job->dropoff_otp ?? '—' }}</span>
                                </div>
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        <script>
            function meramotLiveTrackingMap(initialState, statusOrder) {
                return {
                    state: initialState,
                    statusMap: statusOrder,
                    autoRefresh: true,
                    loading: false,
                    timer: null,
                    map: null,
                    riderMarker: null,
                    trailLine: null,
                    routeLine: null,

                    get currentStep() {
                        return this.statusMap[this.state.status] ?? 0;
                    },

                    boot() {
                        this.$nextTick(() => {
                            this.initLeaflet();
                            this.startPolling();
                        });
                    },

                    initLeaflet() {
                        const el = document.getElementById('map');
                        if (!el || !window.L) return;

                        this.map = L.map(el).setView([this.state.rider_lat, this.state.rider_lng], 14);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19,
                            attribution: '&copy; OpenStreetMap'
                        }).addTo(this.map);

                        const makeBadgeIcon = (bg, iconCls) => L.divIcon({
                            className: '',
                            html: `<div style="width:32px;height:32px;border-radius:999px;background:${bg};color:#fff;display:flex;align-items:center;justify-content:center;border:2px solid #fff;box-shadow:0 3px 8px rgba(0,0,0,.28);"><i class="fa-solid ${iconCls}" style="font-size:13px;"></i></div>`,
                            iconSize: [32, 32],
                            iconAnchor: [16, 16]
                        });

                        L.marker([this.state.pickup_lat, this.state.pickup_lng], { icon: makeBadgeIcon('#2563eb', 'fa-box') })
                            .addTo(this.map)
                            .bindPopup('<strong>Pickup Origin</strong><br>' + this.state.pickup_address);

                        L.marker([this.state.dropoff_lat, this.state.dropoff_lng], { icon: makeBadgeIcon('#15803d', 'fa-screwdriver-wrench') })
                            .addTo(this.map)
                            .bindPopup('<strong>Destination</strong><br>' + this.state.dropoff_address);

                        this.riderMarker = L.marker([this.state.rider_lat, this.state.rider_lng], {
                            icon: makeBadgeIcon('#163625', 'fa-motorcycle')
                        }).addTo(this.map).bindPopup('<strong>' + this.state.rider_name + '</strong> • ' + this.state.status);

                        this.drawPolylines(true);
                    },

                    async drawPolylines(fitView = false) {
                        if (!this.map || !window.L) return;

                        if (this.trailLine) this.trailLine.remove();
                        if (this.routeLine) this.routeLine.remove();

                        if (Array.isArray(this.state.breadcrumbs) && this.state.breadcrumbs.length > 1) {
                            const crumbs = this.state.breadcrumbs.map(p => [p.lat, p.lng]);
                            this.trailLine = L.polyline(crumbs, { color: '#059669', weight: 4, opacity: 0.85 }).addTo(this.map);
                        }

                        const destLat = ['PENDING', 'SCHEDULED', 'DISPATCHED'].includes(this.state.status)
                            ? this.state.pickup_lat
                            : this.state.dropoff_lat;
                        const destLng = ['PENDING', 'SCHEDULED', 'DISPATCHED'].includes(this.state.status)
                            ? this.state.pickup_lng
                            : this.state.dropoff_lng;

                        try {
                            const url = `https://router.project-osrm.org/route/v1/driving/${this.state.rider_lng},${this.state.rider_lat};${destLng},${destLat}?overview=full&geometries=geojson`;
                            const resp = await fetch(url);
                            const json = await resp.json();
                            const coords = json?.routes?.[0]?.geometry?.coordinates || [];
                            if (coords.length >= 2) {
                                this.routeLine = L.polyline(coords.map(([lng, lat]) => [lat, lng]), {
                                    color: '#163625', weight: 4, opacity: 0.85, dashArray: '8,6'
                                }).addTo(this.map);
                            } else {
                                throw new Error('fallback');
                            }
                        } catch (e) {
                            this.routeLine = L.polyline(
                                [[this.state.rider_lat, this.state.rider_lng], [destLat, destLng]],
                                { color: '#163625', weight: 3.5, opacity: 0.8, dashArray: '6,6' }
                            ).addTo(this.map);
                        }

                        if (fitView) {
                            this.map.fitBounds([
                                [this.state.pickup_lat, this.state.pickup_lng],
                                [this.state.dropoff_lat, this.state.dropoff_lng],
                                [this.state.rider_lat, this.state.rider_lng]
                            ], { padding: [45, 45] });
                        }
                    },

                    startPolling() {
                        if (this.timer) clearInterval(this.timer);
                        this.timer = setInterval(() => {
                            if (this.autoRefresh) this.pollNow();
                        }, 5000);
                    },

                    toggleAutoRefresh() {
                        this.autoRefresh = !this.autoRefresh;
                    },

                    async pollNow() {
                        const targetId = this.state.delivery_job_id || this.state.repair_request_id;
                        if (!targetId) return;
                        this.loading = true;
                        try {
                            const res = await fetch(`/tracking/${targetId}/live`, { headers: { 'Accept': 'application/json' } });
                            if (res.ok) {
                                const payload = await res.json();
                                if (payload?.telemetry) {
                                    this.state = payload.telemetry;
                                    if (this.riderMarker) {
                                        this.riderMarker.setLatLng([this.state.rider_lat, this.state.rider_lng]);
                                    }
                                    this.drawPolylines(false);
                                }
                            }
                        } catch (e) {
                            // Keep last rendered coordinates smoothly
                        } finally {
                            this.loading = false;
                        }
                    }
                };
            }
        </script>
    @endif
</div>
@endsection

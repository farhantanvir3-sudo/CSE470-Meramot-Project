@extends('delivery.layout')

@section('title', 'Live Delivery Map | Meramot')

@section('content')
<div
    class="flex flex-col rounded-3xl border border-[#d9e5d5] bg-white p-5 md:p-6 shadow-sm"
    x-data="meramotRiderMap()"
    x-init="initMap()"
>
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-xl font-extrabold text-[#163625]">Live Map</h1>
            <p class="text-xs text-[#163625]/70">Real-time OpenStreetMap dispatch pins &amp; turn-by-turn road routing</p>
        </div>
        <button
            type="button"
            @click="updateMyLocation()"
            :disabled="updatingLocation"
            class="inline-flex items-center gap-2 rounded-xl bg-[#163625] px-4 py-2.5 text-xs md:text-sm font-bold text-[#E4FCD5] transition hover:opacity-95 disabled:opacity-60"
        >
            <i class="fa-solid fa-rotate" :class="updatingLocation ? 'fa-spin' : ''"></i>
            <span x-text="updatingLocation ? 'Updating...' : 'Update my location'">Update my location</span>
        </button>
    </div>

    <div class="mb-4 grid gap-3 md:grid-cols-3">
        <div class="rounded-2xl border border-[#d9e5d5] bg-[#eef4ea] p-4">
            <p class="text-xs font-semibold text-[#163625]/70">Current coordinates</p>
            <p class="mt-1 font-mono text-sm font-bold text-[#163625]" x-text="coordsText"></p>
        </div>
        <div class="rounded-2xl border border-[#d9e5d5] bg-[#eef4ea] p-4">
            <p class="text-xs font-semibold text-[#163625]/70">Active deliveries</p>
            <p class="mt-1 text-sm font-bold text-[#163625]">{{ $activeDeliveries->count() }}</p>
        </div>
        <div class="rounded-2xl border border-[#d9e5d5] bg-[#eef4ea] p-4">
            <p class="text-xs font-semibold text-[#163625]/70">Rider status</p>
            <p class="mt-1 text-sm font-bold text-[#163625]">{{ $agent->status ?? 'AVAILABLE' }}</p>
        </div>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#d9e5d5] bg-[#eef4ea] p-3">
        <p class="text-xs font-semibold text-[#163625]/80">Direction view</p>
        <select
            x-model="directionFilter"
            @change="renderMarkers()"
            class="rounded-lg border border-[#d9e5d5] bg-white px-3 py-2 text-xs font-bold text-[#163625] outline-none"
        >
            <option value="ALL">All directions</option>
            <option value="TO_SHOP">To Shop</option>
            <option value="TO_CUSTOMER">To Customer</option>
        </select>
    </div>

    <div class="mb-4 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-[#d9e5d5] bg-[#eef4ea] p-3">
        <p class="text-xs font-semibold text-[#163625]/80" x-text="selectedPoint ? ('Selected: ' + selectedPoint.label + ' — ' + selectedPoint.title + ' (' + selectedPoint.lat.toFixed(5) + ', ' + selectedPoint.lng.toFixed(5) + ')') : 'Select any pickup or delivery marker on the map to draw route'"></p>
        <button
            type="button"
            :disabled="!selectedPoint"
            @click="navigateToSelected()"
            class="rounded-lg bg-[#163625] px-3.5 py-2 text-xs font-bold text-[#E4FCD5] transition hover:opacity-95 disabled:opacity-45"
        >
            Navigate to selected point
        </button>
    </div>

    <div x-show="errorMsg" x-cloak class="mb-3 rounded-xl border border-rose-200 bg-rose-50 px-4 py-2.5 text-xs font-semibold text-rose-700" x-text="errorMsg"></div>

    <div class="relative rounded-2xl border border-[#d9e5d5] bg-white p-2">
        <div id="rider-live-leaflet-map" style="height: 460px; width: 100%;" class="rounded-xl z-10"></div>

        <div class="pointer-events-none absolute right-4 top-4 z-[400] rounded-lg bg-white/95 px-2.5 py-1 text-[11px] font-semibold text-[#163625] shadow-sm border border-[#d9e5d5]">
            OpenStreetMap + Leaflet
        </div>

        <div class="pointer-events-none absolute left-4 bottom-4 z-[400] rounded-xl bg-white/95 px-3 py-2 text-[11px] font-semibold text-[#163625] shadow-sm border border-[#d9e5d5] flex flex-wrap gap-3">
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-[#2563eb]"></span> Customer pickup
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-[#16a34a]"></span> Shop pickup
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-[2px] bg-[#14532d]"></span> Shop delivery
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-[2px] bg-[#1e40af]"></span> Customer delivery
            </span>
            <span class="inline-flex items-center gap-1.5">
                <span class="inline-block h-2.5 w-2.5 rounded-full bg-[#163625]"></span> Rider
            </span>
        </div>
    </div>
</div>

<script>
function meramotRiderMap() {
    return {
        riderLat: @json($riderLat),
        riderLng: @json($riderLng),
        riderInitial: @json(strtoupper(substr($user->name ?? 'R', 0, 1))),
        allPoints: @json($mapPoints),
        directionFilter: 'ALL',
        selectedPoint: null,
        updatingLocation: false,
        errorMsg: '',
        map: null,
        riderMarker: null,
        markersGroup: null,
        routeLine: null,

        get coordsText() {
            if (typeof this.riderLat !== 'number' || typeof this.riderLng !== 'number') {
                return 'Location not set';
            }
            return this.riderLat.toFixed(5) + ', ' + this.riderLng.toFixed(5);
        },

        initMap() {
            setTimeout(() => {
                if (!window.L) return;
                const el = document.getElementById('rider-live-leaflet-map');
                if (!el) return;

                this.map = L.map(el, {
                    center: [this.riderLat || 23.7512, this.riderLng || 90.3789],
                    zoom: 13
                });

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    maxZoom: 19,
                    attribution: '&copy; OpenStreetMap contributors'
                }).addTo(this.map);

                this.markersGroup = L.layerGroup().addTo(this.map);
                this.renderRiderPin();
                this.renderMarkers();
            }, 50);
        },

        renderRiderPin() {
            if (!this.map || !window.L) return;
            if (this.riderMarker) {
                this.riderMarker.remove();
            }
            const icon = L.divIcon({
                className: '',
                html: `<div style="position:relative;width:38px;height:50px;">
                    <div style="position:absolute;top:0;left:50%;width:38px;height:38px;transform:translateX(-50%);display:flex;align-items:center;justify-content:center;border-radius:999px;border:2px solid #ffffff;box-shadow:0 3px 10px rgba(15,23,42,.35);background:#163625;color:#E4FCD5;font-weight:800;font-size:14px;">${this.riderInitial}</div>
                    <div style="position:absolute;bottom:0;left:50%;width:0;height:0;border-left:9px solid transparent;border-right:9px solid transparent;border-top:12px solid #163625;transform:translateX(-50%);"></div>
                </div>`,
                iconSize: [38, 50],
                iconAnchor: [19, 50],
                popupAnchor: [0, -46]
            });

            this.riderMarker = L.marker([this.riderLat, this.riderLng], { icon })
                .bindPopup(`<strong>Your Current Rider Location</strong><br/><span>${this.coordsText}</span>`)
                .addTo(this.map);
        },

        renderMarkers() {
            if (!this.map || !this.markersGroup) return;
            this.markersGroup.clearLayers();
            if (this.routeLine) {
                this.routeLine.remove();
                this.routeLine = null;
            }

            const filtered = this.directionFilter === 'ALL'
                ? this.allPoints
                : this.allPoints.filter(p => p.direction === this.directionFilter);

            const bounds = [[this.riderLat, this.riderLng]];

            const makeIcon = (bg, radius) => L.divIcon({
                className: '',
                html: `<div style="width:18px;height:18px;border-radius:${radius};background:${bg};border:2px solid #ffffff;box-shadow:0 2px 5px rgba(15,23,42,.28);"></div>`,
                iconSize: [18, 18],
                iconAnchor: [9, 9]
            });

            const icons = {
                CUSTOMER_PICKUP: makeIcon('#2563eb', '999px'),
                SHOP_PICKUP: makeIcon('#16a34a', '999px'),
                SHOP_DELIVERY: makeIcon('#14532d', '4px'),
                CUSTOMER_DELIVERY: makeIcon('#1e40af', '4px')
            };

            filtered.forEach(point => {
                bounds.push([point.lat, point.lng]);
                L.marker([point.lat, point.lng], { icon: icons[point.kind] || icons.CUSTOMER_PICKUP })
                    .on('click', () => {
                        this.selectedPoint = point;
                        this.drawRoute(point);
                    })
                    .bindPopup(
                        `<div style="min-width:200px;font-family:system-ui,sans-serif;color:#0f172a;">
                            <strong>${point.label}</strong> (${point.status})<br/>
                            <span style="font-size:12px;font-weight:600;">${point.title}</span><br/>
                            <span style="font-size:11px;color:#475569;">${point.address || ''}</span><br/>
                            <a href="/delivery/order/${point.deliveryId}" style="font-size:11px;font-weight:700;color:#163625;text-decoration:underline;">View order details &rarr;</a>
                        </div>`
                    )
                    .addTo(this.markersGroup);
            });

            if (bounds.length > 1) {
                this.map.fitBounds(bounds, { padding: [45, 45] });
            } else {
                this.map.setView([this.riderLat, this.riderLng], 14);
            }
        },

        async drawRoute(point) {
            if (!this.map || !point) return;
            if (this.routeLine) {
                this.routeLine.remove();
                this.routeLine = null;
            }
            try {
                const res = await fetch(`https://router.project-osrm.org/route/v1/driving/${this.riderLng},${this.riderLat};${point.lng},${point.lat}?overview=full&geometries=geojson`);
                if (!res.ok) throw new Error('OSRM unavailable');
                const data = await res.json();
                const coords = data?.routes?.[0]?.geometry?.coordinates || [];
                if (coords.length < 2) throw new Error('Empty route');
                const latLngs = coords.map(([lng, lat]) => [lat, lng]);
                this.routeLine = L.polyline(latLngs, { color: '#163625', weight: 5, opacity: 0.9 }).addTo(this.map);
            } catch (e) {
                this.routeLine = L.polyline([[this.riderLat, this.riderLng], [point.lat, point.lng]], {
                    color: '#163625', weight: 4, opacity: 0.85, dashArray: '8,6'
                }).addTo(this.map);
            }
        },

        navigateToSelected() {
            if (!this.selectedPoint) return;
            const url = `https://www.google.com/maps/dir/?api=1&origin=${this.riderLat},${this.riderLng}&destination=${this.selectedPoint.lat},${this.selectedPoint.lng}&travelmode=driving`;
            window.open(url, '_blank', 'noopener,noreferrer');
        },

        updateMyLocation() {
            if (!navigator.geolocation) {
                this.errorMsg = 'Browser geolocation is not supported on this device.';
                return;
            }
            this.updatingLocation = true;
            this.errorMsg = '';
            navigator.geolocation.getCurrentPosition(
                async (pos) => {
                    try {
                        const lat = pos.coords.latitude;
                        const lng = pos.coords.longitude;
                        const resp = await fetch('/delivery/location', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                            },
                            body: JSON.stringify({ latitude: lat, longitude: lng })
                        });
                        if (resp.ok) {
                            this.riderLat = lat;
                            this.riderLng = lng;
                            this.renderRiderPin();
                            this.map.flyTo([lat, lng], 14);
                        }
                    } catch (err) {
                        this.errorMsg = 'Could not sync GPS coordinates.';
                    } finally {
                        this.updatingLocation = false;
                    }
                },
                () => {
                    this.errorMsg = 'Location permission denied by browser.';
                    this.updatingLocation = false;
                },
                { enableHighAccuracy: true, timeout: 10000 }
            );
        }
    };
}
</script>
@endsection

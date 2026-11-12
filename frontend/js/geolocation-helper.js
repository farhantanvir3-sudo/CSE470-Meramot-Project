/* ============================================================================
 * CLIENT-SIDE MODULE: Geolocation, Haversine & OpenStreetMap Leaflet Picker
 * ============================================================================
 * Work Ownership & Attribution:
 *   1. Shanid Sajjatuz Islam [Student ID: 23201465] (Feature R6):
 *      - Browser GPS positioning, Haversine distance calculation, nearby shop
 *        API queries, and distance badge rendering.
 *   2. Farhan Tanvir [Student ID: 22299179] (Original Meramot Map Picker Port):
 *      - OpenStreetMap + Leaflet interactive map picker modal (`MapPicker`),
 *        Nominatim reverse/forward geocoding (`location-utils`), persistent
 *        location state (`meramot.selectedLocation`), and live form binding.
 *
 * Location: frontend/js/geolocation-helper.js (Served via /js/geolocation-helper.js)
 * Secret Key Required: NONE (100% Open-Source OpenStreetMap + Leaflet + Nominatim)
 * ============================================================================ */

const LOCATION_STORAGE_KEY = 'meramot.selectedLocation';
const DEFAULT_COORDS = [23.8103, 90.4125]; // Dhaka Center

const REVERSE_GEOCODE_ENDPOINT = 'https://nominatim.openstreetmap.org/reverse';
const FORWARD_GEOCODE_ENDPOINT = 'https://nominatim.openstreetmap.org/search';

const DHAKA_HUBS = [
    { area: 'Banani', city: 'Dhaka', lat: 23.7937, lng: 90.4066, address: 'Road 11, Banani, Dhaka 1213' },
    { area: 'Gulshan', city: 'Dhaka', lat: 23.7925, lng: 90.4158, address: 'Gulshan 2 Circle, Gulshan, Dhaka 1212' },
    { area: 'Dhanmondi', city: 'Dhaka', lat: 23.7461, lng: 90.3742, address: 'Satmasjid Road, Dhanmondi, Dhaka 1209' },
    { area: 'Hatirpool', city: 'Dhaka', lat: 23.7422, lng: 90.3918, address: 'Hatirpool Eastern Plaza Rd, Dhaka 1205' },
    { area: 'Mirpur', city: 'Dhaka', lat: 23.8071, lng: 90.3686, address: 'Mirpur 10 Roundabout, Mirpur, Dhaka 1216' },
    { area: 'Uttara', city: 'Dhaka', lat: 23.8759, lng: 90.3795, address: 'Sector 7, Uttara, Dhaka 1230' },
    { area: 'Bashundhara', city: 'Dhaka', lat: 23.8191, lng: 90.4526, address: 'Block C, Bashundhara R/A, Dhaka 1229' },
    { area: 'Mohammadpur', city: 'Dhaka', lat: 23.7662, lng: 90.3589, address: 'Town Hall Market, Mohammadpur, Dhaka 1207' },
    { area: 'Farmgate', city: 'Dhaka', lat: 23.7561, lng: 90.3872, address: 'Kazi Nazrul Islam Ave, Farmgate, Dhaka 1215' },
    { area: 'Tejgaon', city: 'Dhaka', lat: 23.7639, lng: 90.3995, address: 'Tejgaon Industrial Area, Dhaka 1208' },
    { area: 'Mohakhali', city: 'Dhaka', lat: 23.7778, lng: 90.4057, address: 'Mohakhali DOHS Gate, Dhaka 1206' },
    { area: 'Badda', city: 'Dhaka', lat: 23.7805, lng: 90.4267, address: 'Middle Badda, Pragati Sarani, Dhaka 1212' },
    { area: 'Rampura', city: 'Dhaka', lat: 23.7612, lng: 90.4219, address: 'Rampura TV Center Road, Dhaka 1219' },
    { area: 'New Market', city: 'Dhaka', lat: 23.7333, lng: 90.3836, address: 'Mirpur Road, New Market, Dhaka 1205' },
    { area: 'Wari', city: 'Dhaka', lat: 23.7176, lng: 90.4178, address: 'Rankin Street, Wari, Dhaka 1203' },
    { area: 'Agrabad', city: 'Chattogram', lat: 22.3264, lng: 91.8155, address: 'Agrabad Commercial Area, Chattogram 4100' },
    { area: 'Zindabazar', city: 'Sylhet', lat: 24.8949, lng: 91.8687, address: 'Zindabazar Main Road, Sylhet 3100' },
    { area: 'Shaheb Bazar', city: 'Rajshahi', lat: 24.3653, lng: 88.6003, address: 'Shaheb Bazar Zero Point, Rajshahi 6100' },
    { area: 'Shib Bari', city: 'Khulna', lat: 22.8206, lng: 89.5535, address: 'Shib Bari More, Khulna 9100' }
];

/* ============================================================================
 * SECTION 1: Geolocation & Haversine Helper
 * Work Ownership: Shanid Sajjatuz Islam [Student ID: 23201465] (Feature R6)
 * ============================================================================ */
const MeramotGeolocation = {
    /**
     * Check if browser geolocation is supported
     */
    isSupported() {
        return 'geolocation' in navigator;
    },

    /**
     * Get user's current GPS coordinates
     */
    async getCurrentPosition() {
        return new Promise((resolve, reject) => {
            if (!this.isSupported()) {
                reject(new Error('GPS is not available in this browser.'));
                return;
            }

            navigator.geolocation.getCurrentPosition(
                (position) => {
                    resolve({
                        lat: position.coords.latitude,
                        lng: position.coords.longitude,
                        accuracy: position.coords.accuracy
                    });
                },
                (error) => {
                    let message = 'Unable to get your location.';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            message = 'Location permission denied. You can still click the map to pin your address.';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = 'Location information unavailable. Click the map to pin your address.';
                            break;
                        case error.TIMEOUT:
                            message = 'Location request timed out. Click the map to pin your address.';
                            break;
                    }
                    reject(new Error(message));
                },
                {
                    enableHighAccuracy: true,
                    timeout: 10000,
                    maximumAge: 300000 // 5 minutes cache
                }
            );
        });
    },

    /**
     * Calculate distance between two points (Haversine formula in km)
     */
    calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371;
        const dLat = this.toRad(lat2 - lat1);
        const dLng = this.toRad(lng2 - lng1);

        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
                  Math.sin(dLng / 2) * Math.sin(dLng / 2);

        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    },

    toRad(degrees) {
        return degrees * (Math.PI / 180);
    },

    formatDistance(distanceKm) {
        if (distanceKm < 1) {
            return Math.round(distanceKm * 1000) + 'm';
        }
        return distanceKm.toFixed(1) + 'km';
    },

    estimateTravelTime(distanceKm) {
        const averageSpeedKmh = 15;
        const minutes = Math.round((distanceKm / averageSpeedKmh) * 60);
        return Math.max(10, minutes);
    },

    formatEta(minutes) {
        const mins = Math.max(10, Math.round(minutes));
        if (mins < 60) {
            return `${mins} min`;
        }
        const hours = Math.floor(mins / 60);
        const rem = mins % 60;
        if (rem === 0 || hours >= 4) {
            return `${Math.round(mins / 60)} hr`;
        }
        return `${hours} hr ${rem} min`;
    },

    async fetchNearbyShops(lat, lng, radiusKm = 10) {
        const response = await fetch(`/api/shops/nearby?lat=${lat}&lng=${lng}&radius=${radiusKm}`);
        return await response.json();
    },

    updateUrlWithLocation(lat, lng, maxDistanceKm = 12) {
        const url = new URL(window.location);
        url.searchParams.set('lat', Number(lat).toFixed(5));
        url.searchParams.set('lng', Number(lng).toFixed(5));
        url.searchParams.set('maxDistanceKm', maxDistanceKm);
        url.searchParams.set('sort', 'distance');
        window.location.href = url.toString();
    },

    async enableDistanceFilter() {
        try {
            const position = await this.getCurrentPosition();
            this.updateUrlWithLocation(position.lat, position.lng);
        } catch (error) {
            const stored = MeramotLocationPicker.parseStoredLocation();
            if (stored && typeof stored.lat === 'number' && typeof stored.lng === 'number') {
                this.updateUrlWithLocation(stored.lat, stored.lng);
                return;
            }
            if (window.MeramotToast) {
                window.MeramotToast.show(error.message, 'warning');
            }
        }
    }
};

/* ============================================================================
 * SECTION 2: OpenStreetMap + Leaflet Location Picker & Nominatim Geocoder
 * Work Ownership: Farhan Tanvir [Student ID: 22299179]
 * Ported from Meramot LocationPickerModal.tsx, MapPicker.tsx & location-utils.ts
 * ============================================================================ */
const MeramotLocationPicker = {
    mapInstance: null,
    markerInstance: null,
    resizeObserver: null,
    draftLocation: null,
    callbacks: {},
    DHAKA_HUBS,

    /**
     * Ensure Leaflet CSS & JS are loaded in the browser even if primary CDN was delayed
     */
    async ensureLeafletLoaded() {
        if (typeof window.L !== 'undefined') return window.L;

        return new Promise((resolve, reject) => {
            if (!document.querySelector('link[href*="leaflet"]')) {
                const link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css';
                document.head.appendChild(link);
            }

            const script = document.createElement('script');
            script.src = 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js';
            script.onload = () => resolve(window.L);
            script.onerror = () => {
                const fallback = document.createElement('script');
                fallback.src = 'https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js';
                fallback.onload = () => resolve(window.L);
                fallback.onerror = () => reject(new Error('Could not load Leaflet map library.'));
                document.head.appendChild(fallback);
            };
            document.head.appendChild(script);
        });
    },

    /**
     * Build compact navbar label from stored location object
     */
    buildLocationLabel(location) {
        if (!location) return 'Choose your location';

        const area = (location.area || '').trim();
        const city = (location.city || '').trim();
        const address = (location.address || '').trim();

        if (area && city) return `${area}, ${city}`;
        if (area) return area;
        if (city) return city;
        if (address) return address.length > 34 ? `${address.slice(0, 34)}...` : address;

        if (typeof location.lat === 'number' && typeof location.lng === 'number') {
            return `${location.lat.toFixed(4)}, ${location.lng.toFixed(4)}`;
        }

        return 'Choose your location';
    },

    /**
     * Read saved location from localStorage
     */
    parseStoredLocation() {
        if (typeof window === 'undefined') return null;

        try {
            const raw = localStorage.getItem(LOCATION_STORAGE_KEY);
            if (!raw) return null;

            const parsed = JSON.parse(raw);
            if (!parsed || typeof parsed !== 'object') return null;

            return parsed;
        } catch (e) {
            localStorage.removeItem(LOCATION_STORAGE_KEY);
            return null;
        }
    },

    /**
     * Match raw Nominatim area/address text to closest known Dhaka neighborhood
     */
    inferDhakaArea(rawArea, displayName, lat, lng) {
        const haystack = `${rawArea || ''} ${displayName || ''}`.toLowerCase();
        for (const hub of DHAKA_HUBS) {
            if (haystack.includes(hub.area.toLowerCase())) {
                return hub.area;
            }
        }

        if (rawArea && rawArea.trim()) {
            return rawArea.trim();
        }

        if (typeof lat === 'number' && typeof lng === 'number') {
            let bestHub = null;
            let bestDist = Infinity;
            for (const hub of DHAKA_HUBS) {
                const d = MeramotGeolocation.calculateDistance(lat, lng, hub.lat, hub.lng);
                if (d < bestDist) {
                    bestDist = d;
                    bestHub = hub;
                }
            }
            if (bestHub && bestDist <= 3.5) {
                return bestHub.area;
            }
        }

        return '';
    },

    /**
     * Reverse geocode [lat, lng] using OpenStreetMap Nominatim (No API key needed)
     */
    async reverseGeocode(lat, lng, source = 'map') {
        try {
            const res = await fetch(
                `${REVERSE_GEOCODE_ENDPOINT}?lat=${encodeURIComponent(lat)}&lon=${encodeURIComponent(lng)}&format=json&addressdetails=1`,
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            if (!res.ok) throw new Error('Reverse geocode failed');

            const data = await res.json();
            const addressParts = data.address || {};

            const city =
                addressParts.city ||
                addressParts.town ||
                addressParts.state_district ||
                addressParts.county ||
                'Dhaka';

            const rawArea =
                addressParts.suburb ||
                addressParts.neighbourhood ||
                addressParts.quarter ||
                addressParts.road ||
                '';

            const area = this.inferDhakaArea(rawArea, data.display_name, lat, lng);

            return {
                address: data.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
                city,
                area,
                lat: Number(lat),
                lng: Number(lng),
                source
            };
        } catch (e) {
            const inferredArea = this.inferDhakaArea('', '', lat, lng);
            return {
                address: inferredArea
                    ? `${inferredArea}, Dhaka (${lat.toFixed(5)}, ${lng.toFixed(5)})`
                    : `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
                city: 'Dhaka',
                area: inferredArea || 'Banani',
                lat: Number(lat),
                lng: Number(lng),
                source
            };
        }
    },

    /**
     * Forward geocode a search query anywhere in Bangladesh using OpenStreetMap Nominatim
     */
    async forwardGeocode(query) {
        if (!query || !query.trim()) return null;
        try {
            const q = query.toLowerCase().includes('bangladesh')
                ? query.trim()
                : `${query.trim()}, Bangladesh`;

            const res = await fetch(
                `${FORWARD_GEOCODE_ENDPOINT}?q=${encodeURIComponent(q)}&countrycodes=bd&format=json&limit=1&addressdetails=1`,
                {
                    headers: {
                        'Accept': 'application/json'
                    }
                }
            );

            if (!res.ok) throw new Error('Forward geocode failed');

            const data = await res.json();
            if (!Array.isArray(data) || data.length === 0) return null;

            const first = data[0];
            const lat = parseFloat(first.lat);
            const lng = parseFloat(first.lon);
            const addressParts = first.address || {};
            const city =
                addressParts.city ||
                addressParts.town ||
                addressParts.state_district ||
                'Dhaka';
            const rawArea =
                addressParts.suburb ||
                addressParts.neighbourhood ||
                addressParts.quarter ||
                addressParts.road ||
                '';
            const area = this.inferDhakaArea(rawArea, first.display_name, lat, lng);

            return {
                address: first.display_name || `${lat.toFixed(5)}, ${lng.toFixed(5)}`,
                city,
                area,
                lat,
                lng,
                source: 'map'
            };
        } catch (e) {
            return null;
        }
    },

    /**
     * Save confirmed location to localStorage and notify all pages/components
     */
    saveLocation(nextLocation) {
        const normalized = {
            address: nextLocation.address ||
                (typeof nextLocation.lat === 'number' && typeof nextLocation.lng === 'number'
                    ? `${nextLocation.lat.toFixed(5)}, ${nextLocation.lng.toFixed(5)}`
                    : 'Pinned location'),
            city: nextLocation.city || 'Dhaka',
            area: nextLocation.area || '',
            lat: typeof nextLocation.lat === 'number' ? Number(nextLocation.lat) : null,
            lng: typeof nextLocation.lng === 'number' ? Number(nextLocation.lng) : null,
            source: nextLocation.source || 'map'
        };

        localStorage.setItem(LOCATION_STORAGE_KEY, JSON.stringify(normalized));
        window.dispatchEvent(new CustomEvent('meramot-location-changed', { detail: normalized }));
        return normalized;
    },

    /**
     * Mount or refresh the interactive OpenStreetMap + Leaflet instance inside #meramot-leaflet-map
     */
    async initOrRefreshMap(containerId = 'meramot-leaflet-map', callbacks = {}) {
        this.callbacks = callbacks;

        try {
            await this.ensureLeafletLoaded();
        } catch (err) {
            callbacks.onError?.('Map could not be loaded. Please check your internet connection.');
            return;
        }

        const container = document.getElementById(containerId);
        if (!container || typeof window.L === 'undefined') {
            callbacks.onError?.('Map container could not be initialized.');
            return;
        }

        const L = window.L;
        const stored = this.parseStoredLocation();
        const initial = this.draftLocation || stored;
        const startLat = typeof initial?.lat === 'number' ? initial.lat : DEFAULT_COORDS[0];
        const startLng = typeof initial?.lng === 'number' ? initial.lng : DEFAULT_COORDS[1];
        const startZoom = (typeof initial?.lat === 'number' && typeof initial?.lng === 'number') ? 14 : 12;

        if (!this.mapInstance) {
            const map = L.map(container, {
                center: [startLat, startLng],
                zoom: startZoom,
                zoomControl: true
            });

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const pinIcon = L.divIcon({
                className: '',
                html: `<div style="position:relative;width:28px;height:38px;">
                    <div style="position:absolute;top:0;left:50%;width:28px;height:28px;background:#244229;border:3px solid #ffffff;border-radius:999px;transform:translateX(-50%);box-shadow:0 4px 10px rgba(15,23,42,.28);"></div>
                    <div style="position:absolute;bottom:1px;left:50%;width:0;height:0;border-left:8px solid transparent;border-right:8px solid transparent;border-top:12px solid #244229;transform:translateX(-50%);"></div>
                </div>`,
                iconSize: [28, 38],
                iconAnchor: [14, 38]
            });

            const marker = L.marker([startLat, startLng], {
                icon: pinIcon,
                draggable: true
            }).addTo(map);

            const handleCoordinatePick = async (lat, lng, source = 'map') => {
                marker.setLatLng([lat, lng]);
                this.callbacks.onLoading?.(true);
                const resolved = await this.reverseGeocode(lat, lng, source);
                this.draftLocation = resolved;
                this.callbacks.onDraftChange?.(resolved);
                this.callbacks.onLoading?.(false);
            };

            map.on('click', (event) => {
                handleCoordinatePick(event.latlng.lat, event.latlng.lng, 'map');
            });

            marker.on('dragend', () => {
                const pos = marker.getLatLng();
                handleCoordinatePick(pos.lat, pos.lng, 'map');
            });

            this.mapInstance = map;
            this.markerInstance = marker;

            if (typeof ResizeObserver !== 'undefined') {
                this.resizeObserver = new ResizeObserver(() => {
                    if (this.mapInstance) {
                        this.mapInstance.invalidateSize();
                    }
                });
                this.resizeObserver.observe(container);
            }
        } else {
            this.mapInstance.setView([startLat, startLng], startZoom);
            if (this.markerInstance) {
                this.markerInstance.setLatLng([startLat, startLng]);
            }
        }

        [40, 160, 350].forEach((delay) => {
            setTimeout(() => {
                if (this.mapInstance) {
                    this.mapInstance.invalidateSize();
                }
            }, delay);
        });
    },

    /**
     * Fly map & marker to specific coordinates
     */
    async flyToLocation(lat, lng, zoom = 15) {
        if (!this.mapInstance) {
            await this.initOrRefreshMap('meramot-leaflet-map', this.callbacks);
        }
        if (!this.mapInstance) return;
        this.mapInstance.invalidateSize();
        this.mapInstance.flyTo([lat, lng], zoom, { duration: 0.6 });
        if (this.markerInstance) {
            this.markerInstance.setLatLng([lat, lng]);
        }
    }
};

/* ============================================================================
 * DOM Ready Bindings (Distance Badges, Dynamic ETA & Distance Sort Sync)
 * ============================================================================ */
function renderShopDistanceBadges(userLat, userLng) {
    if (typeof userLat !== 'number' || typeof userLng !== 'number' || isNaN(userLat) || isNaN(userLng)) {
        return;
    }

    const cards = Array.from(document.querySelectorAll('[data-shop-lat][data-shop-lng]'));
    cards.forEach((shopCard) => {
        const shopLat = parseFloat(shopCard.dataset.shopLat);
        const shopLng = parseFloat(shopCard.dataset.shopLng);

        if (!isNaN(shopLat) && !isNaN(shopLng)) {
            const distance = MeramotGeolocation.calculateDistance(userLat, userLng, shopLat, shopLng);
            shopCard.dataset.computedDistanceKm = String(distance);

            const distanceEl = shopCard.querySelector('.distance-display');
            if (distanceEl) {
                distanceEl.textContent = MeramotGeolocation.formatDistance(distance);
                distanceEl.classList.remove('hidden');
            }

            const etaMinutes = MeramotGeolocation.estimateTravelTime(distance);
            const etaFormatted = MeramotGeolocation.formatEta(etaMinutes);
            shopCard.querySelectorAll('.eta-display').forEach((etaEl) => {
                etaEl.textContent = etaFormatted;
            });
        }
    });

    // Inject user's pinned coordinates into Nearest Distance sort links so server SQL sorts from exact pin
    document.querySelectorAll('a[data-sort-link="distance"]').forEach((link) => {
        try {
            const u = new URL(link.href, window.location.origin);
            u.searchParams.set('lat', userLat.toFixed(5));
            u.searchParams.set('lng', userLng.toFixed(5));
            link.href = u.pathname + '?' + u.searchParams.toString();
        } catch (e) {}
    });

    // Ensure search forms preserve lat/lng when sorting by distance
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('sort') === 'distance') {
        document.querySelectorAll('form[action="/shops"]').forEach((form) => {
            ['lat', 'lng', 'sort'].forEach((field) => {
                let input = form.querySelector(`input[name="${field}"]`);
                if (!input) {
                    input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = field;
                    form.appendChild(input);
                }
                if (field === 'lat') input.value = userLat.toFixed(5);
                if (field === 'lng') input.value = userLng.toFixed(5);
                if (field === 'sort') input.value = 'distance';
            });
        });

        // If the page loaded with sort=distance, ensure visible cards are ordered strictly ascending by distance
        if (cards.length > 1 && cards[0].parentElement) {
            const parent = cards[0].parentElement;
            const sorted = [...cards].sort((a, b) => {
                const da = parseFloat(a.dataset.computedDistanceKm ?? '999999');
                const db = parseFloat(b.dataset.computedDistanceKm ?? '999999');
                return da - db;
            });
            sorted.forEach((card) => parent.appendChild(card));
        }
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const useLocationBtn = document.getElementById('use-my-location-btn');
    if (useLocationBtn) {
        useLocationBtn.addEventListener('click', function (e) {
            e.preventDefault();
            const originalHtml = this.innerHTML;
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Locating...';
            this.disabled = true;

            MeramotGeolocation.enableDistanceFilter().finally(() => {
                this.innerHTML = originalHtml;
                this.disabled = false;
            });
        });
    }

    const urlParams = new URLSearchParams(window.location.search);
    const stored = MeramotLocationPicker.parseStoredLocation();

    if (urlParams.has('lat') && urlParams.has('lng')) {
        renderShopDistanceBadges(parseFloat(urlParams.get('lat')), parseFloat(urlParams.get('lng')));
    } else if (stored && typeof stored.lat === 'number' && typeof stored.lng === 'number') {
        // If user selected sort=distance or maxDistanceKm without lat/lng in URL, sync URL with their pinned coordinates
        if ((urlParams.get('sort') === 'distance' || urlParams.has('maxDistanceKm')) && window.location.pathname.startsWith('/shops')) {
            const nextUrl = new URL(window.location.href);
            nextUrl.searchParams.set('lat', stored.lat.toFixed(5));
            nextUrl.searchParams.set('lng', stored.lng.toFixed(5));
            window.location.replace(nextUrl.toString());
            return;
        }
        renderShopDistanceBadges(stored.lat, stored.lng);
    }

    window.addEventListener('meramot-location-changed', function (e) {
        const loc = e.detail;
        if (loc && typeof loc.lat === 'number' && typeof loc.lng === 'number') {
            const currentParams = new URLSearchParams(window.location.search);
            if ((currentParams.get('sort') === 'distance' || currentParams.has('maxDistanceKm')) && window.location.pathname.startsWith('/shops')) {
                const nextUrl = new URL(window.location.href);
                nextUrl.searchParams.set('lat', loc.lat.toFixed(5));
                nextUrl.searchParams.set('lng', loc.lng.toFixed(5));
                window.location.href = nextUrl.toString();
                return;
            }
            renderShopDistanceBadges(loc.lat, loc.lng);
        }
    });
});

window.MeramotGeolocation = MeramotGeolocation;
window.MeramotLocationPicker = MeramotLocationPicker;

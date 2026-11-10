/* ============================================================================
 * RELOCATED CLIENT-SIDE MODULE: Geolocation & Haversine Helper (Feature R6)
 * ============================================================================
 * Work Ownership: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Relocated From: backend/public/js/geolocation-helper.js -> frontend/js/geolocation-helper.js
 * Purpose: Strict root-level Frontend / Backend separation in Laravel 10 MVC.
 * Enhanced: Uses non-blocking MeramotToast instead of browser alert().
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
                reject(new Error('Geolocation is not supported by your browser'));
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
                    let message = 'Unable to get your location';
                    switch (error.code) {
                        case error.PERMISSION_DENIED:
                            message = 'Location permission denied';
                            break;
                        case error.POSITION_UNAVAILABLE:
                            message = 'Location information unavailable';
                            break;
                        case error.TIMEOUT:
                            message = 'Location request timed out';
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

    async fetchNearbyShops(lat, lng, radiusKm = 10) {
        const response = await fetch(`/api/shops/nearby?lat=${lat}&lng=${lng}&radius=${radiusKm}`);
        return await response.json();
    },

    updateUrlWithLocation(lat, lng, maxDistanceKm = 12) {
        const url = new URL(window.location);
        url.searchParams.set('lat', lat);
        url.searchParams.set('lng', lng);
        url.searchParams.set('maxDistanceKm', maxDistanceKm);
        url.searchParams.set('sort', 'distance');
        window.location.href = url.toString();
    },

    async enableDistanceFilter() {
        try {
            const position = await this.getCurrentPosition();
            this.updateUrlWithLocation(position.lat, position.lng);
        } catch (error) {
            if (window.MeramotToast) {
                window.MeramotToast.show(error.message + ' — using Dhaka area filter instead.', 'warning');
            }
        }
    }
};

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
    if (urlParams.has('lat') && urlParams.has('lng')) {
        const userLat = parseFloat(urlParams.get('lat'));
        const userLng = parseFloat(urlParams.get('lng'));

        document.querySelectorAll('[data-shop-lat][data-shop-lng]').forEach(shopCard => {
            const shopLat = parseFloat(shopCard.dataset.shopLat);
            const shopLng = parseFloat(shopCard.dataset.shopLng);

            if (shopLat && shopLng) {
                const distance = MeramotGeolocation.calculateDistance(userLat, userLng, shopLat, shopLng);
                const distanceEl = shopCard.querySelector('.distance-display');
                if (distanceEl) {
                    distanceEl.textContent = MeramotGeolocation.formatDistance(distance);
                }
            }
        });
    }
});

window.MeramotGeolocation = MeramotGeolocation;

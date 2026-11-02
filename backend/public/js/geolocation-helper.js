/**
 * Geolocation Helper for Meramot
 * Feature R6: Advanced Repair Shop Search & Multi-Parameter Filtering
 * Assigned: Shanid Sajjatuz Islam [23201465]
 */

const MeramotGeolocation = {
    /**
     * Check if geolocation is supported
     */
    isSupported() {
        return 'geolocation' in navigator;
    },

    /**
     * Get user's current location
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
                    switch(error.code) {
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
     * Calculate distance between two points (Haversine formula)
     */
    calculateDistance(lat1, lng1, lat2, lng2) {
        const R = 6371; // Earth's radius in kilometers
        const dLat = this.toRad(lat2 - lat1);
        const dLng = this.toRad(lng2 - lng1);
        
        const a = Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                  Math.cos(this.toRad(lat1)) * Math.cos(this.toRad(lat2)) *
                  Math.sin(dLng / 2) * Math.sin(dLng / 2);
        
        const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
        return R * c;
    },

    /**
     * Convert degrees to radians
     */
    toRad(degrees) {
        return degrees * (Math.PI / 180);
    },

    /**
     * Format distance for display
     */
    formatDistance(distanceKm) {
        if (distanceKm < 1) {
            return Math.round(distanceKm * 1000) + 'm';
        }
        return distanceKm.toFixed(1) + 'km';
    },

    /**
     * Fetch nearby shops from API
     */
    async fetchNearbyShops(lat, lng, radiusKm = 10) {
        try {
            const response = await fetch(`/api/shops/nearby?lat=${lat}&lng=${lng}&radius=${radiusKm}`);
            const data = await response.json();
            return data;
        } catch (error) {
            console.error('Error fetching nearby shops:', error);
            throw error;
        }
    },

    /**
     * Update URL with location parameters
     */
    updateUrlWithLocation(lat, lng, maxDistanceKm = 12) {
        const url = new URL(window.location);
        url.searchParams.set('lat', lat);
        url.searchParams.set('lng', lng);
        url.searchParams.set('maxDistanceKm', maxDistanceKm);
        url.searchParams.set('sort', 'distance');
        window.location.href = url.toString();
    },

    /**
     * Request location permission and redirect to distance-sorted results
     */
    async enableDistanceFilter() {
        try {
            const position = await this.getCurrentPosition();
            this.updateUrlWithLocation(position.lat, position.lng);
        } catch (error) {
            alert(error.message + '. Using area-based filtering instead.');
        }
    }
};

/**
 * Initialize location-aware features on page load
 */
document.addEventListener('DOMContentLoaded', function() {
    // Add "Use My Location" button functionality
    const useLocationBtn = document.getElementById('use-my-location-btn');
    if (useLocationBtn) {
        useLocationBtn.addEventListener('click', function(e) {
            e.preventDefault();
            this.innerHTML = '<i class="fa-solid fa-spinner fa-spin mr-2"></i>Getting location...';
            this.disabled = true;
            
            MeramotGeolocation.enableDistanceFilter();
        });
    }

    // Show geolocation prompt if supported and not already used
    const urlParams = new URLSearchParams(window.location.search);
    if (MeramotGeolocation.isSupported() && !urlParams.has('lat') && !urlParams.has('lng')) {
        const geoPrompt = document.getElementById('geolocation-prompt');
        if (geoPrompt) {
            geoPrompt.classList.remove('hidden');
        }
    }

    // Calculate and display distances for visible shops
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

// Expose to global scope
window.MeramotGeolocation = MeramotGeolocation;

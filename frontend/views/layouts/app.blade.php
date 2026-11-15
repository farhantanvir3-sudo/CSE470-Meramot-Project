<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Meramot (মেরামত) — Digital Electronic Repair Marketplace')</title>
    <link rel="icon" type="image/svg+xml" href="/favicon.svg?v=2">
    <link rel="shortcut icon" type="image/svg+xml" href="/favicon.svg?v=2">
    <link rel="apple-touch-icon" href="/favicon.svg?v=2">

    <!-- Zero-Flicker Theme Initializer (Applies Dark Mode at Frame 0) -->
    <script>
        try {
            if (localStorage.getItem('meramot_theme') === 'dark') {
                document.documentElement.classList.add('dark');
            }
        } catch (e) {}
    </script>
    
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        'background': 'var(--background)',
                        'foreground': 'var(--foreground)',
                        'card': 'var(--card)',
                        'border': 'var(--border)',
                        'muted-foreground': 'var(--muted-foreground)',
                        'mint-50': 'var(--mint-50)',
                        'mint-100': 'var(--mint-100)',
                        'mint-200': 'var(--mint-200)',
                        'mint-300': 'var(--mint-300)',
                        'mint-500': 'var(--mint-500)',
                        'mint-700': 'var(--mint-700)',
                        'accent-dark': 'var(--accent-dark)',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- OpenStreetMap + Leaflet Open-Source Map Styles & Script (No API Key Required) -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <!-- Shared UI Helpers (Must load BEFORE Alpine initializes x-data components) -->
    <script>
        window.meramotEvaluatePassword = function (password) {
            const p = String(password || '');
            const checks = {
                length: p.length >= 8,
                upper: /[A-Z]/.test(p),
                lower: /[a-z]/.test(p),
                number: /[0-9]/.test(p),
                special: /[^A-Za-z0-9]/.test(p)
            };
            const passed = Object.values(checks).filter(Boolean).length;
            let score = 0;
            let label = 'Weak';
            let color = 'text-red-600';
            let barColor = 'bg-red-500';
            if (p.length > 0) {
                if (!checks.length || passed <= 2) {
                    score = 1;
                    label = 'Weak';
                    color = 'text-red-600';
                    barColor = 'bg-red-500';
                } else if (passed === 3) {
                    score = 2;
                    label = 'Fair';
                    color = 'text-amber-600';
                    barColor = 'bg-amber-500';
                } else if (passed === 4) {
                    score = 3;
                    label = 'Good';
                    color = 'text-emerald-600';
                    barColor = 'bg-emerald-500';
                } else {
                    score = 4;
                    label = 'Strong';
                    color = 'text-[#1b5e36]';
                    barColor = 'bg-[#214c34]';
                }
            }
            return { checks, passed, score, label, color, barColor };
        };
    </script>
    <!-- Standalone Client Modules (Served from root `frontend/js/` with cache-busting version) -->
    @php
        $jsAssetVer = @filemtime(base_path('../frontend/js/geolocation-helper.js')) ?: time();
    @endphp
    <script defer src="/js/geolocation-helper.js?v={{ $jsAssetVer }}"></script>
    <script defer src="/js/marketplace-client.js?v={{ $jsAssetVer }}"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
        /* Exact CSS variables from commit 8e44218 (globals.css & mobile.css) */
        :root {
            --background: #E4FCD5;
            --foreground: #203126;
            --card: #c8e7c5;
            --card-foreground: #203126;
            --muted: #b1c9aa;
            --muted-foreground: #5e6d64;
            --border: #556c527a;
            --mint-50: #b5d4ad;
            --mint-100: #e4f3df;
            --mint-200: #c4ddbd;
            --mint-300: #b8d9b5;
            --mint-500: #7eae77;
            --mint-700: #436440;
            --accent-dark: #244229;
            --accent-foreground: #ffffff;
        }

        .dark {
            --background: #0f1512;
            --foreground: #f0fde8;
            --card: #141c16;
            --card-foreground: #c8e0c2;
            --muted: #2b342f;
            --muted-foreground: #b4c9b0;
            --border: #2a3d2f;
            --mint-50: #111814;
            --mint-100: #1a241e;
            --mint-200: #263830;
            --mint-300: #2f4e3a;
            --mint-500: #8ebe86;
            --mint-700: #c4dabb;
            --accent-dark: #b5e4a8;
            --accent-foreground: #0f1c12;
        }

        html, body {
            overflow-x: clip;
            max-width: 100vw;
            width: 100%;
            background-color: var(--background);
            color: var(--foreground);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        [x-cloak] { display: none !important; }

        /* Custom scrollbar utilities */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        .scrollbar-thin::-webkit-scrollbar {
            height: 4px;
            width: 4px;
        }
        .scrollbar-thin::-webkit-scrollbar-track {
            background: transparent;
        }
        .scrollbar-thin::-webkit-scrollbar-thumb {
            background: var(--border);
            border-radius: 9999px;
        }

        /* Core Meramot component utilities */
        .meramot-card {
            border-radius: 1.5rem;
            border: 1px solid var(--border);
            background-color: var(--card);
        }
        .meramot-pill {
            background-color: var(--mint-100);
            border: 1px solid var(--border);
            color: var(--foreground);
        }

        /* Dark Mode high-contrast safeguards for primary buttons & light-surface inputs */
        .dark [class*="bg-[var(--accent-dark)]"].text-white {
            color: var(--accent-foreground) !important;
        }
        .dark input.bg-white,
        .dark select.bg-white,
        .dark textarea.bg-white,
        .dark input[class*="bg-[#f"],
        .dark select[class*="bg-[#f"],
        .dark textarea[class*="bg-[#f"] {
            color: #163625;
        }
        .dark input.bg-white::placeholder,
        .dark textarea.bg-white::placeholder,
        .dark input[class*="bg-[#f"]::placeholder,
        .dark textarea[class*="bg-[#f"]::placeholder {
            color: #64748b;
        }

        /* Tactile focus ring across all interactive form controls */
        input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):focus-visible,
        select:focus-visible,
        textarea:focus-visible {
            box-shadow: 0 0 0 3px rgba(36, 66, 41, 0.14);
        }
        .dark input:not([type="checkbox"]):not([type="radio"]):not([type="range"]):focus-visible,
        .dark select:focus-visible,
        .dark textarea:focus-visible {
            box-shadow: 0 0 0 3px rgba(181, 228, 168, 0.22);
        }

        /* Cohesive Meramot styling for Laravel Tailwind pagination */
        nav[role="navigation"] span[aria-current="page"] > span {
            background-color: var(--accent-dark) !important;
            border-color: var(--accent-dark) !important;
            color: var(--accent-foreground) !important;
            font-weight: 800;
        }
        nav[role="navigation"] a,
        nav[role="navigation"] span[aria-disabled="true"] > span {
            background-color: var(--card) !important;
            border-color: var(--border) !important;
            color: var(--foreground) !important;
        }
        nav[role="navigation"] p {
            color: var(--muted-foreground) !important;
        }

        /* Mobile drawer overlay from mobile.css */
        .mobile-sidebar-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.35);
            backdrop-filter: blur(3px);
            z-index: 110;
        }

        .mobile-sidebar-panel {
            position: fixed;
            left: 0;
            top: 0;
            height: 100%;
            width: 86vw;
            max-width: 340px;
            overflow-y: auto;
            border-right: 1px solid var(--border);
            background: var(--background);
            padding: 1.25rem 1rem;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.35);
            z-index: 111;
            animation: drawerSlideIn 0.2s ease-out;
        }

        @keyframes drawerSlideIn {
            from { transform: translateX(-100%); }
            to { transform: translateX(0); }
        }

        /* Protect OpenStreetMap + Leaflet tiles from Tailwind Play CDN img reset */
        .leaflet-container {
            width: 100%;
            height: 400px;
            min-height: 360px;
            z-index: 10;
            font-family: inherit;
        }
        .leaflet-container img,
        .leaflet-tile,
        .leaflet-marker-icon,
        .leaflet-marker-shadow {
            max-width: none !important;
            max-height: none !important;
        }

        /* Radial subtle darker-green outline disk + outward spread on hover & mobile press */
        .meramot-cart-btn {
            position: relative;
            isolation: isolate;
            -webkit-tap-highlight-color: transparent;
            touch-action: manipulation;
            box-shadow: 0 0 0 3px rgba(27, 67, 50, 0.22);
        }
        .dark .meramot-cart-btn {
            box-shadow: 0 0 0 3px rgba(181, 228, 168, 0.26);
        }
        .meramot-cart-btn::before {
            content: "";
            position: absolute;
            inset: -10px;
            z-index: -1;
            border-radius: 9999px;
            pointer-events: none;
            background: radial-gradient(circle, rgba(22, 54, 37, 0.24) 0%, rgba(16, 185, 129, 0.16) 52%, rgba(16, 185, 129, 0) 74%);
            opacity: 0;
            transform: scale(0.62);
            transition: transform 280ms cubic-bezier(0.22, 1, 0.36, 1), opacity 220ms ease;
        }
        .dark .meramot-cart-btn::before {
            background: radial-gradient(circle, rgba(181, 228, 168, 0.34) 0%, rgba(110, 231, 183, 0.18) 52%, rgba(110, 231, 183, 0) 74%);
        }
        .meramot-cart-btn:hover::before,
        .meramot-cart-btn:focus-visible::before {
            opacity: 1;
            transform: scale(1.16);
        }
        .meramot-cart-btn:active::before {
            opacity: 1;
            transform: scale(1.28);
        }
        .meramot-cart-btn:active {
            transform: scale(0.96);
        }

        /* Academic defense mode toggle (Hidden by default unless ?dev=1 or Ctrl+Shift+D) */
        @php
            if (request()->has('dev')) {
                session(['dev_mode' => request()->boolean('dev')]);
            }
            $isDevMode = session('dev_mode', false);
            $navCartCount = count(session('cart', []));
        @endphp
        @if(!$isDevMode)
            .dev-only-badge, .cse470-dev-badge {
                display: none !important;
            }
        @endif
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased" x-data="{ 
    mobileMenuOpen: false, 
    miniCartOpen: false,
    locationModalOpen: false, 
    showLogoutConfirm: false,
    notifModalOpen: false,
    deleteConfirmOpen: false,
    deleteConfirmTitle: 'Delete permanently?',
    deleteConfirmItem: '',
    deleteConfirmMessage: 'This action cannot be undone.',
    deleteConfirmCta: 'Delete Permanently',
    deleteConfirmForm: null,
    deleteConfirmSubmitting: false,
    openDeleteConfirm(form) {
        this.deleteConfirmForm = form;
        this.deleteConfirmTitle = form.dataset.confirmTitle || 'Delete permanently?';
        this.deleteConfirmItem = form.dataset.confirmItem || '';
        this.deleteConfirmMessage = form.dataset.confirmMessage || 'This action is permanent and cannot be undone.';
        this.deleteConfirmCta = form.dataset.confirmCta || 'Delete Permanently';
        this.deleteConfirmSubmitting = false;
        this.deleteConfirmOpen = true;
    },
    cancelDeleteConfirm() {
        if (this.deleteConfirmSubmitting) return;
        this.deleteConfirmOpen = false;
        this.deleteConfirmForm = null;
    },
    proceedDeleteConfirm() {
        if (!this.deleteConfirmForm || this.deleteConfirmSubmitting) return;
        this.deleteConfirmSubmitting = true;
        this.deleteConfirmForm.dataset.confirmed = 'true';
        HTMLFormElement.prototype.submit.call(this.deleteConfirmForm);
    },
    selectedLocation: null,
    draftLocation: null,
    locationLabel: 'Choose your location',
    locationLoading: false,
    locationSaving: false,
    locationError: '',
    mapSearchQuery: '',
    mapDistrictFilter: 'Dhaka',
    mapAreaSelect: '',
    mapSuggestions: [],
    mapSuggestionsOpen: false,
    mapHighlightIdx: -1,
    mapDebounceTimer: null,
    toastVisible: false,
    toastMessage: '',
    toastType: 'success',
    toastTimer: null,
    showToast(detail) {
        this.toastMessage = detail.message || 'Action completed';
        this.toastType = detail.type || 'success';
        this.toastVisible = true;
        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toastVisible = false; }, 3200);
    },
    darkMode: localStorage.getItem('meramot_theme') === 'dark',
    toggleDark() {
        this.darkMode = !this.darkMode;
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
            localStorage.setItem('meramot_theme', 'dark');
        } else {
            document.documentElement.classList.remove('dark');
            localStorage.setItem('meramot_theme', 'light');
        }
    },
    syncStoredLocation() {
        if (!window.MeramotLocationPicker) return;
        const stored = window.MeramotLocationPicker.parseStoredLocation();
        if (stored) {
            this.selectedLocation = stored;
            this.draftLocation = stored;
            this.locationLabel = window.MeramotLocationPicker.buildLocationLabel(stored);
        }
    },
    openLocationModal() {
        this.locationError = '';
        this.draftLocation = this.selectedLocation || (window.MeramotLocationPicker ? window.MeramotLocationPicker.parseStoredLocation() : null) || {
            address: 'Road 11, Banani, Dhaka 1213',
            city: 'Dhaka',
            area: 'Banani',
            lat: 23.7937,
            lng: 90.4066,
            source: 'map'
        };
        if (window.MeramotLocationPicker) {
            window.MeramotLocationPicker.draftLocation = this.draftLocation;
        }
        this.mapDistrictFilter = (window.MeramotLocationCatalog && this.draftLocation?.city)
            ? window.MeramotLocationCatalog.findDistrict(this.draftLocation.city)
            : (this.draftLocation?.city || 'Dhaka');
        this.mapAreaSelect = this.draftLocation?.area || '';
        this.mapSuggestionsOpen = false;
        this.locationModalOpen = true;
        setTimeout(() => {
            if (window.MeramotLocationPicker) {
                window.MeramotLocationPicker.initOrRefreshMap('meramot-leaflet-map', {
                    onDraftChange: (loc) => {
                        this.locationError = '';
                        this.draftLocation = loc;
                    },
                    onLoading: (loading) => {
                        this.locationLoading = loading;
                    },
                    onError: (msg) => {
                        this.locationError = msg;
                    }
                });
            }
        }, 40);
    },
    async useGpsLocation() {
        if (!window.MeramotGeolocation || !window.MeramotLocationPicker) return;
        this.locationLoading = true;
        this.locationError = '';
        try {
            const pos = await window.MeramotGeolocation.getCurrentPosition();
            window.MeramotLocationPicker.flyToLocation(pos.lat, pos.lng, 15);
            const resolved = await window.MeramotLocationPicker.reverseGeocode(pos.lat, pos.lng, 'gps');
            this.draftLocation = resolved;
            window.MeramotLocationPicker.draftLocation = resolved;
        } catch (err) {
            this.locationError = err.message || 'Location access was declined. Please click directly on the map to pin your address.';
        } finally {
            this.locationLoading = false;
        }
    },
    get mapDistrictOptions() {
        return window.MeramotLocationCatalog ? window.MeramotLocationCatalog.getDistricts() : ['Dhaka', 'Chattogram', 'Sylhet'];
    },
    get mapAreaOptions() {
        if (!window.MeramotLocationCatalog) return [];
        return window.MeramotLocationCatalog.getPlacesByCity(this.mapDistrictFilter || 'Dhaka', this.draftLocation?.area || '');
    },
    onMapQueryInput() {
        const q = String(this.mapSearchQuery || '').trim();
        if (!window.MeramotLocationCatalog) return;
        this.mapSuggestions = window.MeramotLocationCatalog.search(q, this.mapDistrictFilter, 10);
        this.mapSuggestionsOpen = this.mapSuggestions.length > 0;
        this.mapHighlightIdx = this.mapSuggestions.length > 0 ? 0 : -1;
        clearTimeout(this.mapDebounceTimer);
        if (q.length >= 2 && window.MeramotLocationPicker?.searchPlaces) {
            this.mapDebounceTimer = setTimeout(async () => {
                const enriched = await window.MeramotLocationPicker.searchPlaces(q, this.mapDistrictFilter);
                if (Array.isArray(enriched) && enriched.length > 0 && this.mapSearchQuery.trim() === q) {
                    this.mapSuggestions = enriched;
                    this.mapSuggestionsOpen = true;
                }
            }, 260);
        }
    },
    pickMapSuggestion(item) {
        if (!item) return;
        this.mapSearchQuery = [item.area, item.city].filter(Boolean).join(', ');
        this.mapDistrictFilter = window.MeramotLocationCatalog ? window.MeramotLocationCatalog.findDistrict(item.city) : (item.city || 'Dhaka');
        this.mapAreaSelect = item.area || '';
        this.mapSuggestionsOpen = false;
        this.selectQuickHub(item);
    },
    onMapDistrictChange() {
        const places = this.mapAreaOptions;
        if (places.length > 0) {
            this.mapAreaSelect = places[0].area;
            this.selectQuickHub(places[0]);
        } else if (window.MeramotLocationCatalog) {
            const center = window.MeramotLocationCatalog.getDistrictCoords(this.mapDistrictFilter);
            this.selectQuickHub({
                area: center.city + ' Sadar',
                city: center.city,
                lat: center.lat,
                lng: center.lng,
                address: center.city + ' Sadar, Bangladesh'
            });
        }
    },
    onMapAreaChange() {
        const match = this.mapAreaOptions.find((p) => p.area === this.mapAreaSelect);
        if (match) {
            this.selectQuickHub(match);
        }
    },
    async searchMapAddress() {
        if (!this.mapSearchQuery.trim() || !window.MeramotLocationPicker) return;
        if (this.mapSuggestionsOpen && this.mapHighlightIdx >= 0 && this.mapSuggestions[this.mapHighlightIdx]) {
            this.pickMapSuggestion(this.mapSuggestions[this.mapHighlightIdx]);
            return;
        }
        const instant = window.MeramotLocationCatalog ? window.MeramotLocationCatalog.search(this.mapSearchQuery, this.mapDistrictFilter, 1) : [];
        if (instant.length > 0) {
            this.pickMapSuggestion(instant[0]);
            return;
        }
        this.locationLoading = true;
        this.locationError = '';
        try {
            const found = await window.MeramotLocationPicker.forwardGeocode(this.mapSearchQuery);
            if (!found) {
                this.locationError = 'Unable to locate that address automatically. Please pin your location on the map or select a service hub.';
            } else {
                this.draftLocation = found;
                this.mapDistrictFilter = window.MeramotLocationCatalog ? window.MeramotLocationCatalog.findDistrict(found.city) : (found.city || 'Dhaka');
                this.mapAreaSelect = found.area || '';
                window.MeramotLocationPicker.draftLocation = found;
                window.MeramotLocationPicker.flyToLocation(found.lat, found.lng, 15);
            }
        } catch (err) {
            this.locationError = 'Search failed. Please click directly on the map.';
        } finally {
            this.locationLoading = false;
        }
    },
    selectQuickHub(hub) {
        this.locationError = '';
        const canonicalCity = (window.MeramotLocationCatalog && hub.city)
            ? window.MeramotLocationCatalog.findDistrict(hub.city)
            : (hub.city || 'Dhaka');
        const next = {
            address: hub.address,
            city: canonicalCity,
            area: hub.area,
            lat: Number(hub.lat),
            lng: Number(hub.lng),
            source: 'map'
        };
        this.mapDistrictFilter = canonicalCity;
        this.mapAreaSelect = hub.area || '';
        this.draftLocation = next;
        if (window.MeramotLocationPicker) {
            window.MeramotLocationPicker.draftLocation = next;
            window.MeramotLocationPicker.flyToLocation(next.lat, next.lng, 14);
        }
    },
    confirmMapLocation() {
        if (!this.draftLocation) {
            this.locationError = 'Please select a location on the map before confirming.';
            return;
        }
        this.locationSaving = true;
        this.locationError = '';
        try {
            const saved = window.MeramotLocationPicker.saveLocation(this.draftLocation);
            this.selectedLocation = saved;
            this.locationLabel = window.MeramotLocationPicker.buildLocationLabel(saved);
            this.locationModalOpen = false;
            this.showToast({ message: 'Pinned location saved: ' + this.locationLabel, type: 'success' });
        } catch (err) {
            this.locationError = 'Could not save location.';
        } finally {
            this.locationSaving = false;
        }
    },
    init() {
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        }
        setTimeout(() => this.syncStoredLocation(), 50);
    }
}"
@meramot-toast.window="showToast($event.detail)"
@meramot-open-location-modal.window="openLocationModal()"
@meramot-open-notif-modal.window="if (window.innerWidth < 768) notifModalOpen = true"
@meramot-location-changed.window="selectedLocation = $event.detail; locationLabel = window.MeramotLocationPicker ? window.MeramotLocationPicker.buildLocationLabel($event.detail) : locationLabel">

    <!-- Non-Blocking Meramot Mint Toast Notification -->
    <div
        x-show="toastVisible"
        x-cloak
        x-transition:enter="transition ease-out duration-200"
        x-transition:enter-start="opacity-0 translate-y-2"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-150"
        x-transition:leave-start="opacity-100 translate-y-0"
        x-transition:leave-end="opacity-0 translate-y-2"
        class="fixed bottom-5 right-5 z-[150] max-w-sm rounded-2xl border border-[var(--border)] bg-[var(--accent-dark)] px-5 py-3.5 text-xs font-semibold text-[var(--accent-foreground)] shadow-2xl flex items-center gap-3"
    >
        <i class="fa-solid text-sm" :class="toastType === 'error' ? 'fa-circle-exclamation text-rose-300' : (toastType === 'warning' ? 'fa-triangle-exclamation text-amber-300' : 'fa-circle-check text-emerald-300')"></i>
        <span x-text="toastMessage" class="flex-1 leading-snug"></span>
        <a href="/cart" x-show="toastType === 'success' && toastMessage.toLowerCase().includes('cart')" class="rounded-full bg-[var(--mint-100)] px-3 py-1 text-[11px] font-bold text-[#203126] hover:opacity-90 transition">
            View Cart
        </a>
    </div>

    @php
        $navAuthUser = auth()->user();
        $navUserRole = $navAuthUser?->role ?? 'guest';
        $isRiderAuth = $navAuthUser && in_array($navUserRole, ['delivery_agent', 'delivery'], true);
        $isVendorAuth = $navAuthUser && $navUserRole === 'vendor';
        $isAdminAuth = $navAuthUser && $navUserRole === 'admin';
        $isCustomerAuth = $navAuthUser && $navUserRole === 'customer';
        $isBannedCustomer = $isCustomerAuth && method_exists($navAuthUser, 'isBanned') && $navAuthUser->isBanned();

        $userNotifications = ($navAuthUser && \Illuminate\Support\Facades\Schema::hasTable('user_notifications'))
            ? \App\Models\UserNotification::where('user_id', $navAuthUser->id)->latest()->take(8)->get()
            : collect();
        $unreadNotifCount = $userNotifications->where('is_read', false)->count();
        $unseenPopupNotif = $userNotifications->first(fn ($n) => !$n->is_read && $n->popup_shown_at === null);

        $navVendorProfile = $isVendorAuth
            ? \App\Models\VendorProfile::with('shops')->where('user_id', $navAuthUser->id)->first()
            : null;
        $navPrimaryShop = $navVendorProfile?->shops?->sortBy('id')->first();
        $navVendorApproved = ($navVendorProfile?->verification_status ?? '') === 'approved';
        $navVendorReady = $navVendorApproved && (!$navPrimaryShop || $navPrimaryShop->setup_complete || $navPrimaryShop->inspection_fee !== null);

        $vendorWorkspaceUrl = !$isVendorAuth
            ? '/vendor/apply'
            : (!$navVendorProfile
                ? '/vendor/apply'
                : (!$navVendorApproved
                    ? '/vendor/onboarding'
                    : ($navVendorReady ? '/vendor/dashboard' : '/vendor/setup-shop')));

        $vendorWorkspaceLabel = !$isVendorAuth
            ? 'Apply as Partner Shop'
            : (!$navVendorProfile
                ? 'Complete Vendor Form'
                : (!$navVendorApproved
                    ? 'Application Status'
                    : ($navVendorReady ? 'Vendor Dashboard' : 'Complete Shop Setup')));
    @endphp

    <!-- Top Academic Defense / Role Switcher Ribbon (Dev Mode Only) -->
    @if($isDevMode)
    <div class="bg-[#163625] text-white text-[11px] py-1.5 px-4 shadow-sm border-b border-emerald-950">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="bg-emerald-500/30 text-emerald-200 border border-emerald-400/40 px-2 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">CSE 470 Group 6 • Dev Mode</span>
                <span class="opacity-80">Faculty: Nazmul Islam [NZU]</span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="opacity-70">Switch Portal:</span>
                <a href="/shops/explore" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-store text-emerald-400"></i> Shops</a>
                <span class="opacity-40">|</span>
                @if($isAdminAuth)
                    <a href="/admin/moderation" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-gavel text-amber-300"></i> Moderation</a>
                @else
                    <a href="/requests" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-list-check text-teal-300"></i> Requests</a>
                @endif
                <span class="opacity-40">|</span>
                <a href="{{ $isVendorAuth ? $vendorWorkspaceUrl : '/vendor/dashboard' }}" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-gavel text-amber-300"></i> Vendor Portal</a>
                <span class="opacity-40">|</span>
                <a href="{{ $isRiderAuth ? '/delivery' : '/delivery/login' }}" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-motorcycle text-sky-300"></i> {{ $isRiderAuth ? 'Rider Dashboard' : 'Rider R1' }}</a>
                <span class="opacity-40">|</span>
                <a href="/admin" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-shield-halved text-rose-300"></i> Admin Console</a>
                <span class="opacity-40">|</span>
                <a href="{{ request()->fullUrlWithQuery(['dev' => 0]) }}" class="bg-rose-600/80 hover:bg-rose-600 text-white px-2 py-0.5 rounded text-[10px] font-semibold transition"><i class="fa-solid fa-xmark mr-1"></i> Exit Dev Mode</a>
            </div>
        </div>
    </div>
    @endif

    <!-- ── Authentic Meramot Header (Exact match to Navbar.tsx from commit 8e44218) ── -->
    <header class="w-full border-b border-[var(--border)] bg-[var(--mint-100)]">
        <div class="mx-auto flex max-w-7xl flex-col gap-8 px-4 py-6 md:gap-4 md:px-6 md:py-5">
            
            <!-- ── Row 1: Logo + hamburger (mobile) / Logo + location + actions (desktop) ── -->
            <div class="flex items-center justify-between gap-3 md:grid md:grid-cols-[auto_minmax(0,1fr)_auto] md:items-center md:gap-3 lg:gap-4">
                
                <!-- Hamburger — mobile only -->
                <button
                    type="button"
                    @click="mobileMenuOpen = !mobileMenuOpen"
                    class="flex h-[43px] w-[43px] shrink-0 items-center justify-center rounded-[10px] bg-[var(--card)] text-lg text-[var(--foreground)] shadow-sm md:hidden"
                    aria-label="Toggle navigation menu"
                >
                    <span x-text="mobileMenuOpen ? '✕' : '☰'">☰</span>
                </button>

                <!-- Brand Logo -->
                <div class="flex flex-1 justify-center md:justify-start">
                    <a href="/" class="inline-flex items-center">
                        <img
                            src="/images/meramot.svg"
                            alt="Meramot Logo"
                            class="h-[4.62rem] w-auto object-contain md:h-[5.1975rem] lg:h-[6.3525rem]"
                        />
                    </a>
                </div>

                <!-- Desktop-only: Location Button (Matches NavbarLocationButton.tsx) -->
                <div class="hidden md:flex md:justify-center">
                    <button
                        type="button"
                        @click="openLocationModal()"
                        :title="locationLabel"
                        aria-label="Choose your location"
                        class="group inline-flex max-w-full items-center gap-2 rounded-full border border-[var(--border)] bg-[var(--card)] px-3 lg:px-4 py-2.5 text-sm font-semibold text-[var(--foreground)] shadow-sm transition hover:border-[var(--accent-dark)] hover:bg-[var(--mint-50)]"
                    >
                        <i class="fa-solid fa-location-dot shrink-0 text-[var(--accent-dark)] text-base"></i>
                        <span class="hidden lg:inline max-w-[160px] xl:max-w-[220px] truncate" x-text="locationLabel">Choose your location</span>
                    </button>
                </div>

                <!-- Right side icons — always visible -->
                <div class="flex shrink-0 items-center gap-2 md:gap-3">
                    
                    <!-- Notification Bell Dropdown (Desktop Only; Mobile uses Drawer Notification Tile) -->
                    <div
                        class="relative hidden md:block"
                        x-data="{ notifOpen: {{ $unseenPopupNotif ? 'true' : 'false' }} }"
                        x-init="if ({{ $unseenPopupNotif ? 'true' : 'false' }}) { $dispatch('meramot-open-notif-modal'); fetch('/notifications/mark-read', { method: 'POST', headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content }, body: JSON.stringify({ popup_only: true }) }).catch(() => {}); }"
                    >
                        <button
                            type="button"
                            @click="notifOpen = !notifOpen"
                            class="relative flex h-10 w-10 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition hover:bg-[var(--mint-300)]"
                            title="Notifications"
                            aria-label="Notifications"
                        >
                            <i class="fa-regular fa-bell text-sm"></i>
                            @if($unreadNotifCount > 0)
                                <span class="absolute -top-1 -right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-rose-600 px-1 text-[9px] font-extrabold text-white shadow">
                                    {{ $unreadNotifCount }}
                                </span>
                            @else
                                <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-emerald-600"></span>
                            @endif
                        </button>

                        <div
                            x-show="notifOpen"
                            @click.away="notifOpen = false"
                            x-cloak
                            class="absolute right-0 z-40 mt-2 w-96 rounded-3xl border border-[var(--border)] bg-[var(--card)] p-4 shadow-2xl text-xs"
                        >
                            <div class="flex items-center justify-between border-b border-[var(--border)] pb-2.5 mb-2.5">
                                <span class="font-black text-sm text-[var(--foreground)]">Notifications &amp; Alerts</span>
                                <div class="flex items-center gap-2">
                                    @if($unreadNotifCount > 0)
                                        <form method="POST" action="{{ route('notifications.mark-read') }}" class="inline">
                                            @csrf
                                            <button type="submit" class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                                                Mark all read
                                            </button>
                                        </form>
                                    @endif
                                    <span class="rounded-full {{ $unreadNotifCount > 0 ? 'bg-rose-600 text-white' : 'bg-[var(--mint-100)] text-[var(--accent-dark)]' }} px-2.5 py-0.5 text-[10px] font-bold">
                                        {{ $unreadNotifCount > 0 ? $unreadNotifCount . ' New' : 'Live' }}
                                    </span>
                                </div>
                            </div>

                            <div class="space-y-2 max-h-80 overflow-y-auto pr-0.5">
                                @foreach($userNotifications as $notif)
                                    @php
                                        $notifDanger = ($notif->severity ?? '') === 'danger';
                                        $notifWarn = ($notif->severity ?? '') === 'warning';
                                        $notifCardCls = $notifDanger
                                            ? 'border border-rose-300 bg-rose-50/90 dark:bg-rose-950/40'
                                            : ($notifWarn
                                                ? 'border border-amber-300 bg-amber-50/90 dark:bg-amber-950/40'
                                                : 'border border-[var(--border)] bg-[var(--mint-100)]/90');
                                    @endphp
                                    <div class="rounded-2xl p-3 {{ $notifCardCls }}">
                                        <div class="font-extrabold flex items-center justify-between gap-2 {{ $notifDanger ? 'text-rose-900 dark:text-rose-200' : 'text-[var(--foreground)]' }}">
                                            <span class="flex items-center gap-1.5">
                                                <i class="fa-solid {{ $notifDanger ? 'fa-ban text-rose-600' : ($notifWarn ? 'fa-triangle-exclamation text-amber-600' : 'fa-circle-check text-emerald-600') }}"></i>
                                                <span>{{ $notif->title }}</span>
                                            </span>
                                            @if(!$notif->is_read)
                                                <span class="rounded-full bg-rose-600 px-1.5 py-0.5 text-[9px] font-black uppercase text-white">New</span>
                                            @endif
                                        </div>
                                        <p class="mt-1 text-[11px] leading-relaxed {{ $notifDanger ? 'text-rose-800 dark:text-rose-300' : 'text-[var(--muted-foreground)]' }}">
                                            {{ $notif->message }}
                                        </p>
                                        <div class="mt-1.5 flex items-center justify-between text-[10px] text-[var(--muted-foreground)]">
                                            <span>{{ $notif->created_at ? $notif->created_at->diffForHumans() : 'Just now' }}</span>
                                            @if($notif->action_url)
                                                <a href="{{ $notif->action_url }}" @click="notifOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">
                                                    Open &rarr;
                                                </a>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach

                                @php
                                    $notifQuoteUrl = $isAdminAuth ? '/admin' : ($isVendorAuth ? $vendorWorkspaceUrl : ($isRiderAuth ? '/delivery' : (auth()->check() ? '/requests' : '/login')));
                                @endphp
                                <a href="{{ $notifQuoteUrl }}" @click="notifOpen = false" class="block rounded-2xl bg-[var(--mint-100)]/70 p-3 hover:bg-[var(--mint-200)] transition">
                                    <div class="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                                        <i class="fa-solid fa-gavel text-amber-600"></i>
                                        <span>Live Estimates &amp; Diagnostics Active</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-[var(--muted-foreground)]">Compare incoming workshop estimates and review bench diagnostics in real time.</p>
                                </a>

                                <a href="/shops?branched=true" @click="notifOpen = false" class="block rounded-2xl bg-[var(--mint-100)]/70 p-3 hover:bg-[var(--mint-200)] transition">
                                    <div class="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                                        <i class="fa-solid fa-map-location-dot text-[var(--accent-dark)]"></i>
                                        <span>Nationwide Workshop Network</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-[var(--muted-foreground)]">Browse verified repair centers and multi-branch specialists across Bangladesh.</p>
                                </a>
                            </div>

                            <div class="mt-3 pt-2.5 border-t border-[var(--border)] flex items-center justify-between">
                                @if($isVendorAuth)
                                    <a href="{{ $vendorWorkspaceUrl }}" @click="notifOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">{{ $vendorWorkspaceLabel }}</a>
                                    <a href="/vendor/alert-settings" @click="notifOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">SMS Alert Settings &rarr;</a>
                                @elseif($isRiderAuth)
                                    <a href="/delivery" @click="notifOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">Rider Dashboard</a>
                                    <a href="/delivery/map" @click="notifOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">Live Delivery Map &rarr;</a>
                                @elseif($isAdminAuth)
                                    <a href="/admin" @click="notifOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">Admin Console</a>
                                    <a href="/admin/moderation" @click="notifOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">Moderation Desk &rarr;</a>
                                @else
                                    <a href="{{ auth()->check() ? '/requests' : '/login' }}" @click="notifOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">{{ auth()->check() ? ($isBannedCustomer ? 'Ongoing & Past Orders' : 'My Requests') : 'Sign in to Track Orders' }}</a>
                                    <a href="/shops/explore" @click="notifOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">Explore Workshops &rarr;</a>
                                @endif
                            </div>
                        </div>
                    </div>

                    <!-- Financial Ledger (Feature R16 - Nawshad) -->
                    @if($isDevMode)
                    <a href="/admin/financial-overview" class="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition hover:bg-[var(--mint-300)] hover:scale-105" title="Financial Ledger & Overview (Feature R16 - Nawshad)">
                        <i class="fa-solid fa-scale-balanced text-sm text-emerald-800"></i>
                    </a>
                    @endif

                    <!-- Mobile Quick Cart Icon (Visible on Mobile Header for Active Customers & Guests) -->
                    @if(!$isAdminAuth && !$isVendorAuth && !$isRiderAuth && !$isBannedCustomer)
                    <a
                        href="/cart"
                        class="meramot-cart-btn relative flex h-10 w-10 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition duration-200 hover:bg-[var(--mint-300)] md:hidden"
                        title="Direct-Order Cart"
                        aria-label="Direct-Order Cart"
                    >
                        <i class="fa-solid fa-cart-shopping text-sm"></i>
                        <span
                            data-cart-badge
                            class="{{ $navCartCount > 0 ? '' : 'hidden' }} absolute -top-1 -right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[var(--accent-dark)] px-1 text-[9px] font-bold text-[var(--accent-foreground)] shadow"
                        >{{ $navCartCount }}</span>
                    </a>
                    @endif

                    <!-- Desktop: Full Action Bar -->
                    <div class="hidden md:flex md:items-center md:gap-2 lg:gap-3">
                        
                        <!-- Theme Toggle Button -->
                        <button
                            type="button"
                            @click="toggleDark()"
                            class="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition hover:bg-[var(--mint-300)]"
                            title="Toggle Light / Dark mode"
                        >
                            <i :class="darkMode ? 'fa-solid fa-sun text-amber-400' : 'fa-solid fa-moon text-[var(--foreground)]'"></i>
                        </button>

                        <!-- Direct 1-Click Workspace Pill for Active Rider / Vendor / Admin Sessions -->
                        @if($isRiderAuth && !request()->is('delivery*'))
                            <a
                                href="/delivery"
                                class="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-full border border-[#c8dac2] bg-[var(--card)] px-3 lg:px-4 py-2 text-xs font-bold text-[var(--accent-dark)] shadow-sm transition hover:border-[var(--accent-dark)] hover:bg-[var(--mint-50)]"
                            >
                                <i class="fa-solid fa-motorcycle text-[var(--accent-dark)]"></i>
                                <span class="sr-only lg:not-sr-only">Rider Dashboard</span>
                            </a>
                        @elseif($isVendorAuth && !request()->is('vendor/dashboard*'))
                            <a
                                href="{{ $vendorWorkspaceUrl }}"
                                class="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-full border border-[#c8dac2] bg-[var(--card)] px-3 lg:px-4 py-2 text-xs font-bold text-[var(--accent-dark)] shadow-sm transition hover:border-[var(--accent-dark)] hover:bg-[var(--mint-50)]"
                            >
                                <i class="fa-solid fa-store text-[var(--accent-dark)]"></i>
                                <span class="sr-only lg:not-sr-only">{{ $vendorWorkspaceLabel }}</span>
                            </a>
                        @elseif($isAdminAuth && !request()->is('admin*'))
                            <a
                                href="/admin"
                                class="inline-flex h-10 items-center gap-2 whitespace-nowrap rounded-full border border-[#c8dac2] bg-[var(--card)] px-3 lg:px-4 py-2 text-xs font-bold text-[var(--accent-dark)] shadow-sm transition hover:border-[var(--accent-dark)] hover:bg-[var(--mint-50)]"
                            >
                                <i class="fa-solid fa-shield-halved text-[var(--accent-dark)]"></i>
                                <span class="sr-only lg:not-sr-only">Admin Console</span>
                            </a>
                        @endif

                        <!-- Sign In / Sign Up or User Dropdown -->
                        @auth
                            <div class="relative" x-data="{ userMenuOpen: false }">
                                <button
                                    @click="userMenuOpen = !userMenuOpen"
                                    type="button"
                                    class="inline-flex max-w-full items-center justify-center gap-2 whitespace-nowrap text-center rounded-full bg-[var(--accent-dark)] px-3.5 lg:px-5 py-2 text-sm font-semibold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition"
                                >
                                    @if(!empty(auth()->user()->avatar_url))
                                        <img src="{{ auth()->user()->avatar_url }}" alt="" class="h-6 w-6 rounded-full object-cover border border-white/40 shrink-0">
                                    @endif
                                    <span class="max-w-[96px] lg:max-w-[150px] xl:max-w-[210px] truncate">{{ auth()->user()->name ?? 'User' }}</span>
                                    <span class="ml-0.5 text-xs shrink-0">▼</span>
                                </button>

                                <div
                                    x-show="userMenuOpen"
                                    @click.away="userMenuOpen = false"
                                    x-cloak
                                    class="absolute right-0 z-30 mt-2 w-64 rounded-3xl border border-[var(--border)] bg-[var(--card)] p-3 shadow-xl text-sm"
                                >
                                    @if($isAdminAuth)
                                        <a href="/admin" class="block rounded-2xl bg-[var(--mint-100)] px-4 py-2.5 font-bold text-[var(--accent-dark)] transition hover:bg-[var(--mint-200)]">
                                            <i class="fa-solid fa-shield-halved mr-1.5"></i> Admin Console
                                        </a>
                                        <a href="/admin/vendors/pending" class="mt-1 block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Verify Vendors
                                        </a>
                                        <a href="/admin/delivery-agents/pending" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Verify Riders
                                        </a>
                                        <a href="/admin/financial-overview" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Financial Ledger
                                        </a>
                                        <div class="my-1.5 border-t border-[var(--border)]"></div>
                                        <a href="/shops" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Explore shops
                                        </a>
                                        <a href="/profile" class="block rounded-2xl px-4 py-2.5 font-semibold text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            <i class="fa-regular fa-user mr-1.5 text-[var(--accent-dark)]"></i> Account Profile
                                        </a>
                                    @elseif($isVendorAuth)
                                        <a href="{{ $vendorWorkspaceUrl }}" class="block rounded-2xl bg-[var(--mint-100)] px-4 py-2.5 font-bold text-[var(--accent-dark)] transition hover:bg-[var(--mint-200)]">
                                            <i class="fa-solid fa-store mr-1.5"></i> {{ $vendorWorkspaceLabel }}
                                        </a>
                                        @if($navVendorReady)
                                            <a href="/vendor/bidding-board" class="mt-1 block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                                Live Bidding Board
                                            </a>
                                        @endif
                                        @if($navVendorApproved)
                                            <a href="/vendor/setup-shop" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                                Shop Pricing &amp; Services
                                            </a>
                                        @endif
                                        <a href="/vendor/onboarding" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Verification &amp; Onboarding
                                        </a>
                                        <div class="my-1.5 border-t border-[var(--border)]"></div>
                                        <a href="/shops" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Explore shops
                                        </a>
                                        <a href="/profile" class="block rounded-2xl px-4 py-2.5 font-semibold text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            <i class="fa-regular fa-user mr-1.5 text-[var(--accent-dark)]"></i> Account Profile
                                        </a>
                                    @elseif($isRiderAuth)
                                        <a href="/delivery" class="block rounded-2xl bg-[var(--mint-100)] px-4 py-2.5 font-bold text-[var(--accent-dark)] transition hover:bg-[var(--mint-200)]">
                                            <i class="fa-solid fa-motorcycle mr-1.5"></i> Rider Dashboard
                                        </a>
                                        <a href="/delivery/map" class="mt-1 block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Live Delivery Map
                                        </a>
                                        <a href="/delivery/earnings" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Rider Earnings
                                        </a>
                                        <a href="/delivery/profile" class="block rounded-2xl px-4 py-2.5 font-semibold text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            <i class="fa-regular fa-id-badge mr-1.5 text-[var(--accent-dark)]"></i> Rider Profile
                                        </a>
                                        <div class="my-1.5 border-t border-[var(--border)]"></div>
                                        <a href="/shops" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Explore shops
                                        </a>
                                    @else
                                        <a href="/requests" class="block rounded-2xl px-4 py-2.5 font-semibold text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            {{ $isBannedCustomer ? 'Ongoing & Past Orders' : 'My Repair Requests' }}
                                        </a>
                                        @if(!$isBannedCustomer)
                                            <a href="/requests/create" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                                Make Repair Request
                                            </a>
                                            <a href="/cart" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                                View cart
                                            </a>
                                        @endif
                                        <a href="/shops" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Explore shops
                                        </a>
                                        <a href="/packages/catalog" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Service Packages
                                        </a>
                                        <a href="/ai-consultant/chat" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            AI help chat
                                        </a>
                                        <a href="/profile" class="block rounded-2xl px-4 py-2.5 font-semibold text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            <i class="fa-regular fa-user mr-1.5 text-[var(--accent-dark)]"></i> Account Profile
                                        </a>
                                    @endif
                                    <div class="my-1 border-t border-[var(--border)]"></div>
                                    <button
                                        type="button"
                                        @click="userMenuOpen = false; showLogoutConfirm = true"
                                        class="block w-full rounded-2xl px-4 py-2.5 text-left font-medium text-red-700 transition hover:bg-red-50"
                                    >
                                        Log out
                                    </button>
                                </div>
                            </div>
                        @else
                            <a
                                href="/login"
                                class="inline-flex items-center justify-center whitespace-nowrap text-center rounded-full bg-[var(--accent-dark)] px-4 lg:px-6 py-2.5 text-sm font-semibold text-[var(--accent-foreground)] shadow-sm transition hover:opacity-90"
                            >
                                Sign in/Sign up
                            </a>
                        @endauth

                        <!-- Language Switcher Pill -->
                        <div class="relative" x-data="{ langMenuOpen: false, currentLang: localStorage.getItem('meramot_lang') || 'en' }">
                            <button
                                type="button"
                                @click="langMenuOpen = !langMenuOpen"
                                class="whitespace-nowrap rounded-full bg-[var(--mint-200)] px-3 lg:px-5 py-2.5 text-sm font-medium text-[var(--foreground)] transition hover:bg-[var(--mint-300)]"
                            >
                                <span class="hidden lg:inline" x-text="currentLang === 'bn' ? 'বাংলা' : 'English'">English</span><span class="lg:hidden" x-text="currentLang === 'bn' ? 'বাং' : 'EN'">EN</span> ▼
                            </button>

                            <div
                                x-show="langMenuOpen"
                                @click.away="langMenuOpen = false"
                                x-cloak
                                class="absolute right-0 z-30 mt-2 w-44 rounded-2xl border border-[var(--border)] bg-[var(--card)] p-2 shadow-lg text-sm"
                            >
                                <button
                                    type="button"
                                    @click="currentLang = 'en'; localStorage.setItem('meramot_lang', 'en'); langMenuOpen = false; showToast({ message: 'Language set to English.', type: 'success' })"
                                    class="block w-full rounded-xl px-4 py-2 text-left text-[var(--foreground)] hover:bg-[var(--mint-50)]"
                                >
                                    English
                                </button>
                                <button
                                    type="button"
                                    @click="currentLang = 'bn'; localStorage.setItem('meramot_lang', 'bn'); langMenuOpen = false; showToast({ message: 'Bangla interface selected. Expanded localized copy is rolling out progressively.', type: 'success' })"
                                    class="block w-full rounded-xl px-4 py-2 text-left text-[var(--foreground)] hover:bg-[var(--mint-50)]"
                                >
                                    বাংলা
                                </button>
                            </div>
                        </div>

                        <!-- Cart Button (Opens Slide-Over Mini-Cart when populated or navigates to `/cart`) -->
                        @if(!$isAdminAuth && !$isVendorAuth && !$isRiderAuth && !$isBannedCustomer)
                        <a
                            href="/cart"
                            @click="if ({{ $navCartCount }} > 0 && !window.location.pathname.startsWith('/cart')) { $event.preventDefault(); miniCartOpen = true; }"
                            class="meramot-cart-btn relative flex h-10 w-10 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition duration-200 hover:scale-105 hover:bg-[var(--mint-300)]"
                            title="Direct-Order Cart"
                            aria-label="Direct-Order Cart"
                        >
                            <i class="fa-solid fa-cart-shopping text-sm"></i>
                            <span
                                data-cart-badge
                                class="{{ $navCartCount > 0 ? '' : 'hidden' }} absolute -top-1 -right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[var(--accent-dark)] px-1 text-[9px] font-bold text-[var(--accent-foreground)] shadow"
                            >{{ $navCartCount }}</span>
                        </a>
                        @endif
                    </div>
                </div>
            </div>

            <!-- ── Row 2: Mobile Location Button & Compact Search (Mobile Only) ── -->
            <div class="mt-4 md:mt-0 md:hidden">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="openLocationModal()"
                        class="shrink-0 p-1.5 text-[var(--accent-dark)]"
                        aria-label="Change location"
                    >
                        <i class="fa-solid fa-location-dot text-2xl"></i>
                    </button>
                    <form action="/shops" method="GET" class="relative flex-1">
                        @if(request('category'))
                            <input type="hidden" name="category" value="{{ request('category') }}">
                        @endif
                        @if(request('sort'))
                            <input type="hidden" name="sort" value="{{ request('sort') }}">
                        @endif
                        @if(request('lat') && request('lng'))
                            <input type="hidden" name="lat" value="{{ request('lat') }}">
                            <input type="hidden" name="lng" value="{{ request('lng') }}">
                        @endif
                        <input
                            type="text"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Search shops, parts..."
                            class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] py-2.5 pl-4 pr-16 text-[0.9rem] text-[var(--foreground)] shadow-sm outline-none placeholder:text-[var(--muted-foreground)] focus:border-[var(--accent-dark)]"
                        />
                        <div class="absolute right-2 top-1/2 -translate-y-1/2 flex items-center gap-1">
                            @if(request('q'))
                                <a href="/shops" class="flex h-7 w-7 items-center justify-center rounded-lg text-xs text-[var(--muted-foreground)] hover:text-[var(--foreground)]" title="Clear search">✕</a>
                            @endif
                            <button type="submit" aria-label="Search shops" class="flex h-7 w-7 items-center justify-center rounded-lg bg-[var(--accent-dark)] text-xs text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition">
                                <i class="fa-solid fa-magnifying-glass"></i>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- ── Row 3: Category Tabs + Search (Desktop) ── -->
            <div class="mt-2 flex flex-col gap-2 md:mt-0 md:flex-row md:items-center md:justify-between md:gap-4">
                @php
                    $activeCategory = request('category', '');
                @endphp
                <div class="flex flex-wrap items-center gap-x-3.5 gap-y-1.5 text-xs font-semibold text-[var(--foreground)] md:flex-nowrap md:shrink-0 md:items-end md:gap-4 md:text-sm lg:gap-6 lg:text-base">
                    <a
                        href="/shops?category=COURIER_PICKUP"
                        class="inline-flex border-b-[3px] pb-1 transition {{ $activeCategory === 'COURIER_PICKUP' ? 'border-[var(--accent-dark)] text-[var(--accent-dark)] font-bold' : 'border-transparent text-[var(--foreground)] hover:border-[var(--accent-dark)]/40' }}"
                    >
                        Courier Pickup
                    </a>
                    <a
                        href="/shops?category=IN_SHOP_REPAIR"
                        class="inline-flex border-b-[3px] pb-1 transition {{ $activeCategory === 'IN_SHOP_REPAIR' ? 'border-[var(--accent-dark)] text-[var(--accent-dark)] font-bold' : 'border-transparent text-[var(--foreground)] hover:border-[var(--accent-dark)]/40' }}"
                    >
                        In-shop Repair
                    </a>
                    <a
                        href="/shops?category=SPARE_PARTS"
                        class="inline-flex border-b-[3px] pb-1 transition {{ $activeCategory === 'SPARE_PARTS' ? 'border-[var(--accent-dark)] text-[var(--accent-dark)] font-bold' : 'border-transparent text-[var(--foreground)] hover:border-[var(--accent-dark)]/40' }}"
                    >
                        Spare Parts
                    </a>
                    <a
                        href="/packages/catalog"
                        class="inline-flex border-b-[3px] pb-1 transition {{ request()->is('packages*') ? 'border-[var(--accent-dark)] text-[var(--accent-dark)] font-bold' : 'border-transparent text-[var(--foreground)] hover:border-[var(--accent-dark)]/40' }}"
                    >
                        Fixed Packages
                    </a>
                </div>

                <form action="/shops" method="GET" class="relative hidden w-full min-w-0 md:block md:flex-1 md:max-w-[640px]">
                    @if(request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    @if(request('sort'))
                        <input type="hidden" name="sort" value="{{ request('sort') }}">
                    @endif
                    @if(request('lat') && request('lng'))
                        <input type="hidden" name="lat" value="{{ request('lat') }}">
                        <input type="hidden" name="lng" value="{{ request('lng') }}">
                    @endif
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search shops, parts, devices..."
                        class="w-full rounded-xl border-2 border-[var(--border)] bg-[var(--mint-300)] py-3 pl-5 pr-20 text-sm text-[var(--foreground)] shadow-sm outline-none placeholder:text-[var(--muted-foreground)] focus:border-[var(--accent-dark)] transition"
                    />
                    <div class="absolute right-2.5 top-1/2 -translate-y-1/2 flex items-center gap-1.5">
                        @if(request('q'))
                            <a href="/shops" class="flex h-8 w-8 items-center justify-center rounded-lg text-xs font-bold text-[var(--muted-foreground)] hover:bg-[var(--mint-100)] hover:text-[var(--foreground)] transition" title="Clear search">✕</a>
                        @endif
                        <button type="submit" aria-label="Search shops" class="flex h-8 w-8 items-center justify-center rounded-lg bg-[var(--accent-dark)] text-xs text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition">
                            <i class="fa-solid fa-magnifying-glass"></i>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </header>

    <!-- ── Mobile Drawer Panel (Exact match to Navbar.tsx mobile menu) ── -->
    <div x-show="mobileMenuOpen" x-cloak class="fixed inset-0 z-[110] md:hidden">
        <div @click="mobileMenuOpen = false" class="mobile-sidebar-backdrop"></div>
        <aside class="mobile-sidebar-panel">
            <div class="mb-4 flex items-center justify-between">
                <a href="/" @click="mobileMenuOpen = false">
                    <img src="/images/meramot.svg" alt="Meramot" class="h-14 w-auto object-contain">
                </a>
                <button
                    type="button"
                    @click="mobileMenuOpen = false"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--mint-100)] text-sm font-bold text-[var(--accent-dark)]"
                >
                    ✕
                </button>
            </div>

            <div class="mb-4 flex items-center gap-4">
                <button
                    type="button"
                    @click="mobileMenuOpen = false; notifModalOpen = true"
                    class="relative flex h-9 w-9 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition hover:bg-[var(--mint-300)]"
                    title="Notifications"
                    aria-label="Notifications"
                >
                    <i class="fa-regular fa-bell text-sm"></i>
                    @if($unreadNotifCount > 0)
                        <span class="absolute -top-1 -right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-rose-600 px-1 text-[9px] font-extrabold text-white shadow">{{ $unreadNotifCount }}</span>
                    @else
                        <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-emerald-600"></span>
                    @endif
                </button>
                <button
                    type="button"
                    @click="toggleDark()"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition hover:bg-[var(--mint-300)]"
                    title="Toggle theme"
                    aria-label="Toggle theme"
                >
                    <i :class="darkMode ? 'fa-solid fa-sun text-amber-400' : 'fa-solid fa-moon'"></i>
                </button>
            </div>

            <nav class="space-y-1">
                @if($isRiderAuth)
                    <a href="/delivery" @click="mobileMenuOpen = false" class="block rounded-xl bg-[var(--mint-100)] px-4 py-3 text-sm font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)]">
                        <i class="fa-solid fa-motorcycle mr-2"></i> Rider Dashboard
                    </a>
                    <a href="/delivery/map" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm font-semibold text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Live Delivery Map
                    </a>
                    <a href="/delivery/earnings" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Rider Earnings
                    </a>
                    <a href="/delivery/profile" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Rider Profile
                    </a>
                    <div class="my-2 border-t border-[var(--border)]"></div>
                    <a href="/shops" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Explore all shops
                    </a>
                @elseif($isVendorAuth)
                    <a href="{{ $vendorWorkspaceUrl }}" @click="mobileMenuOpen = false" class="block rounded-xl bg-[var(--mint-100)] px-4 py-3 text-sm font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)]">
                        <i class="fa-solid fa-store mr-2"></i> {{ $vendorWorkspaceLabel }}
                    </a>
                    @if($navVendorReady)
                        <a href="/vendor/bidding-board" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm font-semibold text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                            Live Bidding Board
                        </a>
                    @endif
                    @if($navVendorApproved)
                        <a href="/vendor/setup-shop" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                            Shop Pricing &amp; Services
                        </a>
                    @endif
                    <a href="/vendor/onboarding" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Verification &amp; Onboarding
                    </a>
                    <div class="my-2 border-t border-[var(--border)]"></div>
                    <a href="/shops" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Explore all shops
                    </a>
                @elseif($isAdminAuth)
                    <a href="/admin" @click="mobileMenuOpen = false" class="block rounded-xl bg-[var(--mint-100)] px-4 py-3 text-sm font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)]">
                        <i class="fa-solid fa-shield-halved mr-2"></i> Admin Console
                    </a>
                    <a href="/admin/vendors/pending" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm font-semibold text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Verify Vendors
                    </a>
                    <a href="/admin/delivery-agents/pending" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Verify Riders
                    </a>
                    <a href="/admin/financial-overview" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Financial Ledger
                    </a>
                    <div class="my-2 border-t border-[var(--border)]"></div>
                    <a href="/shops" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Explore all shops
                    </a>
                @else
                    <a href="/shops" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Explore all shops
                    </a>
                    <a href="/requests" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm font-semibold text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        {{ $isBannedCustomer ? 'Ongoing & Past Orders' : 'My Repair Requests' }}
                    </a>
                    @if(!$isBannedCustomer)
                        <a href="/requests/create" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                            Make Repair Request
                        </a>
                        <a href="/cart" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                            Direct-Order Cart
                        </a>
                    @endif
                    <a href="/packages/catalog" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Service Packages
                    </a>
                    <a href="/ai-consultant/chat" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        AI Diagnostic Help
                    </a>
                    <div class="my-1.5 border-t border-[var(--border)]"></div>
                    <a href="/vendor/apply" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-2.5 text-sm font-bold text-emerald-800 hover:bg-[var(--mint-50)]">
                        {{ $isCustomerAuth ? 'Become a Partner Shop' : 'Apply as Partner Shop' }}
                    </a>
                    <a href="{{ $isCustomerAuth ? '/delivery-agent/register' : '/delivery/login' }}" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-2.5 text-sm font-bold text-sky-900 hover:bg-[var(--mint-50)]">
                        {{ $isCustomerAuth ? 'Become a Delivery Partner' : 'Delivery Partner Login' }}
                    </a>
                    @if($isDevMode)
                        <a href="/admin" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-2.5 text-sm font-bold text-teal-800 hover:bg-[var(--mint-50)]">
                            <i class="fa-solid fa-shield-halved mr-2"></i> Admin Console
                        </a>
                    @endif
                @endif
                <div class="my-2 border-t border-[var(--border)]"></div>
                @auth
                    @if(!$isRiderAuth)
                        <a href="/profile" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm font-semibold text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                            <i class="fa-regular fa-user mr-2 text-[var(--accent-dark)]"></i> Account Profile
                        </a>
                    @endif
                    <button
                        type="button"
                        @click="mobileMenuOpen = false; showLogoutConfirm = true"
                        class="block w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-red-700 hover:bg-red-50"
                    >
                        Log out
                    </button>
                @else
                    <a href="/login" @click="mobileMenuOpen = false" class="block rounded-xl bg-[var(--accent-dark)] px-4 py-3 text-center text-sm font-semibold text-[var(--accent-foreground)]">
                        Sign In / Sign Up
                    </a>
                @endauth
            </nav>
        </aside>
    </div>

    <!-- ── OpenStreetMap + Leaflet Location Picker Modal (Exact match to LocationPickerModal.tsx & MapPicker.tsx) ── -->
    <div x-show="locationModalOpen" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center px-4 py-6">
        <div class="absolute inset-0 bg-black/35 backdrop-blur-sm" @click="locationModalOpen = false"></div>

        <div class="relative z-[121] flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-2xl">
            <!-- Modal Header -->
            <div class="flex items-start justify-between gap-4 border-b border-[var(--border)] p-5">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[0.25em] text-[var(--muted-foreground)]">
                        Delivery location
                    </p>
                    <h2 class="mt-1 text-2xl font-bold text-[var(--foreground)]">Choose your location</h2>
                    <p class="mt-1 text-sm text-[var(--muted-foreground)]">
                        Click or drag the map pin to set your service address anywhere in Bangladesh, search a landmark, or detect via GPS.
                    </p>
                </div>

                <button
                    type="button"
                    @click="locationModalOpen = false"
                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--mint-100)] text-[var(--foreground)] transition hover:bg-[var(--mint-200)]"
                    aria-label="Close location picker"
                >
                    <i class="fa-solid fa-xmark text-base"></i>
                </button>
            </div>

            <!-- Modal Body: MapPicker + Controls Column -->
            <div class="grid min-h-0 flex-1 gap-4 overflow-y-auto p-5 lg:grid-cols-[1fr_290px]">
                <!-- Left Column: Address Search + Interactive OpenStreetMap + Leaflet Canvas -->
                <div class="flex flex-col gap-3">
                    <form @submit.prevent="searchMapAddress()" class="relative flex items-center gap-2 z-[500]">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-[var(--muted-foreground)]"></i>
                            <input
                                type="text"
                                x-model="mapSearchQuery"
                                @input="onMapQueryInput()"
                                @focus="if (mapSearchQuery.trim().length > 0 || mapSuggestions.length > 0) onMapQueryInput()"
                                @keydown.arrow-down.prevent="if (mapSuggestions.length) mapHighlightIdx = (mapHighlightIdx + 1) % mapSuggestions.length"
                                @keydown.arrow-up.prevent="if (mapSuggestions.length) mapHighlightIdx = (mapHighlightIdx - 1 + mapSuggestions.length) % mapSuggestions.length"
                                @keydown.escape="mapSuggestionsOpen = false"
                                @blur="setTimeout(() => { mapSuggestionsOpen = false; }, 180)"
                                autocomplete="off"
                                data-map-search-input
                                placeholder="Type area, sector, plaza, or city (e.g. Uttara Sector 7, Multiplan, Zindabazar)..."
                                class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] pl-9 pr-4 py-2.5 text-xs font-medium text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
                            />
                            <div
                                x-show="mapSuggestionsOpen && mapSuggestions.length > 0"
                                x-cloak
                                data-map-search-suggestions
                                class="absolute left-0 right-0 top-full mt-1.5 max-h-60 overflow-y-auto rounded-2xl border border-[var(--border)] bg-[var(--card)] p-1.5 shadow-xl z-[900]"
                            >
                                <template x-for="(item, idx) in mapSuggestions" :key="item.city + '-' + item.area + '-' + idx">
                                    <button
                                        type="button"
                                        @mousedown.prevent="pickMapSuggestion(item)"
                                        :class="mapHighlightIdx === idx ? 'bg-[var(--mint-100)] text-[var(--accent-dark)]' : 'hover:bg-[var(--mint-50)] text-[var(--foreground)]'"
                                        class="flex w-full items-start justify-between gap-2 rounded-xl px-3 py-2 text-left transition"
                                    >
                                        <div class="min-w-0">
                                            <p class="truncate text-xs font-bold" x-text="item.area"></p>
                                            <p class="truncate text-[11px] text-[var(--muted-foreground)]" x-text="item.address"></p>
                                        </div>
                                        <span class="shrink-0 rounded-full bg-[var(--mint-200)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]" x-text="item.city"></span>
                                    </button>
                                </template>
                            </div>
                        </div>
                        <button
                            type="submit"
                            :disabled="locationLoading"
                            class="rounded-2xl bg-[var(--accent-dark)] px-4 py-2.5 text-xs font-bold text-[var(--accent-foreground)] transition hover:opacity-90 disabled:opacity-60"
                        >
                            Find
                        </button>
                    </form>

                    <div class="relative min-h-[360px] flex-1 overflow-hidden rounded-[1.5rem] border border-[var(--border)] bg-[var(--mint-50)]">
                        <div id="meramot-leaflet-map" style="height: 400px; min-height: 360px; width: 100%;" class="w-full z-10"></div>
                        <div class="pointer-events-none absolute left-4 top-4 z-[400] rounded-full bg-white/90 px-3 py-1.5 text-xs font-semibold text-[#244229] shadow-sm">
                            Interactive Service Map
                        </div>
                    </div>
                </div>

                <!-- Right Column: GPS Button, Selected Address Card, Quick Hubs, Confirm Button -->
                <div class="flex flex-col gap-3">
                    <button
                        type="button"
                        @click="useGpsLocation()"
                        :disabled="locationLoading || locationSaving"
                        class="inline-flex items-center justify-center gap-2 rounded-2xl bg-[var(--accent-dark)] px-4 py-3 text-sm font-bold text-[var(--accent-foreground)] transition hover:opacity-95 disabled:opacity-60 shadow-sm"
                    >
                        <i class="fa-solid fa-location-crosshairs" :class="locationLoading ? 'fa-spin' : ''"></i>
                        <span x-text="locationLoading ? 'Detecting...' : 'Use GPS location'">Use GPS location</span>
                    </button>

                    <!-- Selected Address Preview Box -->
                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-50)] p-4">
                        <p class="text-xs font-bold uppercase tracking-[0.2em] text-[var(--muted-foreground)]">
                            Selected address
                        </p>
                        <p class="mt-2 text-sm font-semibold leading-6 text-[var(--foreground)]" x-text="draftLocation?.address || 'No address selected yet. Click anywhere on the map to pin your location.'"></p>
                        <template x-if="draftLocation && (draftLocation.area || draftLocation.city)">
                            <p class="mt-1.5 text-xs font-semibold text-[var(--accent-dark)]" x-text="[draftLocation.area, draftLocation.city].filter(Boolean).join(', ')"></p>
                        </template>
                        <template x-if="draftLocation && typeof draftLocation.lat === 'number' && typeof draftLocation.lng === 'number'">
                            <p class="mt-1 font-mono text-[11px] text-[var(--muted-foreground)]" x-text="draftLocation.lat.toFixed(5) + ', ' + draftLocation.lng.toFixed(5)"></p>
                        </template>
                    </div>

                    <!-- Narrowed-Down District & Neighborhood Selector -->
                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-3 space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--muted-foreground)]">
                                Select narrowed-down location
                            </p>
                            <span class="text-[10px] font-semibold text-[var(--accent-dark)]" x-text="mapAreaOptions.length + ' hubs in ' + mapDistrictFilter"></span>
                        </div>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
                            <div>
                                <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-1">District (64)</label>
                                <select
                                    x-model="mapDistrictFilter"
                                    @change="onMapDistrictChange()"
                                    x-effect="$nextTick(() => { $el.value = mapDistrictFilter; })"
                                    data-map-district-select
                                    class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-50)] px-2.5 py-1.5 text-xs font-semibold text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
                                >
                                    @foreach(array_column(\App\Services\BangladeshLocationResolver::DISTRICTS, 'city') as $distOption)
                                        <option value="{{ $distOption }}">{{ $distOption }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-1">Narrowed-Down Area / Hub</label>
                                <select
                                    x-model="mapAreaSelect"
                                    @change="onMapAreaChange()"
                                    x-effect="const _opts = mapAreaOptions; $nextTick(() => { if (_opts.length) $el.value = mapAreaSelect; })"
                                    data-map-area-select
                                    class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-50)] px-2.5 py-1.5 text-xs font-semibold text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
                                >
                                    <template x-for="place in mapAreaOptions" :key="'map-place-' + place.city + '-' + place.area">
                                        <option :value="place.area" x-text="place.area"></option>
                                    </template>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Jump Bangladesh & Dhaka Hubs -->
                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-3 space-y-2">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--muted-foreground)] mb-1.5">
                                Quick jump • Dhaka hubs
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach([
                                    ['area' => 'Banani', 'city' => 'Dhaka', 'lat' => 23.7937, 'lng' => 90.4066, 'address' => 'Road 11, Banani, Dhaka 1213'],
                                    ['area' => 'Gulshan', 'city' => 'Dhaka', 'lat' => 23.7925, 'lng' => 90.4158, 'address' => 'Gulshan 2 Circle, Gulshan, Dhaka 1212'],
                                    ['area' => 'Dhanmondi', 'city' => 'Dhaka', 'lat' => 23.7461, 'lng' => 90.3742, 'address' => 'Satmasjid Road, Dhanmondi, Dhaka 1209'],
                                    ['area' => 'Hatirpool', 'city' => 'Dhaka', 'lat' => 23.7422, 'lng' => 90.3918, 'address' => 'Hatirpool Eastern Plaza Rd, Dhaka 1205'],
                                    ['area' => 'Mirpur', 'city' => 'Dhaka', 'lat' => 23.8071, 'lng' => 90.3686, 'address' => 'Mirpur 10 Roundabout, Mirpur, Dhaka 1216'],
                                    ['area' => 'Uttara', 'city' => 'Dhaka', 'lat' => 23.8759, 'lng' => 90.3795, 'address' => 'Sector 7, Uttara, Dhaka 1230'],
                                    ['area' => 'Bashundhara', 'city' => 'Dhaka', 'lat' => 23.8191, 'lng' => 90.4526, 'address' => 'Block C, Bashundhara R/A, Dhaka 1229'],
                                ] as $hub)
                                    <button
                                        type="button"
                                        @click="selectQuickHub(@js($hub))"
                                        class="rounded-full border border-[var(--border)] bg-[var(--card)] px-2.5 py-1 text-[11px] font-semibold text-[var(--foreground)] transition hover:border-[var(--accent-dark)] hover:bg-[var(--mint-50)]"
                                    >
                                        {{ $hub['area'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>

                        <div class="border-t border-[var(--border)] pt-2">
                            <p class="text-[10px] font-bold uppercase tracking-[0.18em] text-[var(--muted-foreground)] mb-1.5">
                                Nationwide Bangladesh cities
                            </p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach([
                                    ['area' => 'Agrabad', 'city' => 'Chattogram', 'lat' => 22.3264, 'lng' => 91.8155, 'address' => 'Agrabad Commercial Area, Chattogram 4100'],
                                    ['area' => 'Zindabazar', 'city' => 'Sylhet', 'lat' => 24.8949, 'lng' => 91.8687, 'address' => 'Zindabazar Main Road, Sylhet 3100'],
                                    ['area' => 'Joydebpur', 'city' => 'Gazipur', 'lat' => 23.9999, 'lng' => 90.4203, 'address' => 'Joydebpur Chowrasta, Gazipur'],
                                    ['area' => 'Shaheb Bazar', 'city' => 'Rajshahi', 'lat' => 24.3653, 'lng' => 88.6003, 'address' => 'Shaheb Bazar Zero Point, Rajshahi'],
                                    ['area' => 'Shib Bari', 'city' => 'Khulna', 'lat' => 22.8206, 'lng' => 89.5535, 'address' => 'Shib Bari More, Khulna'],
                                    ['area' => 'Satmatha', 'city' => 'Bogura', 'lat' => 24.8481, 'lng' => 89.3730, 'address' => 'Satmatha Zero Point, Bogura'],
                                    ['area' => 'Ganginarpar', 'city' => 'Mymensingh', 'lat' => 24.7564, 'lng' => 90.4065, 'address' => 'Ganginarpar, Mymensingh'],
                                ] as $cityHub)
                                    <button
                                        type="button"
                                        @click="selectQuickHub(@js($cityHub))"
                                        class="rounded-full border border-[var(--border)] bg-[var(--mint-50)] px-2.5 py-1 text-[11px] font-semibold text-[var(--accent-dark)] transition hover:border-[var(--accent-dark)] hover:bg-[var(--card)]"
                                    >
                                        {{ $cityHub['city'] }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <div x-show="locationError" x-cloak class="rounded-2xl bg-red-50 px-4 py-3 text-xs font-semibold text-red-700 border border-red-200" x-text="locationError"></div>

                    <div class="mt-auto flex flex-col gap-2 pt-1">
                        <button
                            type="button"
                            @click="confirmMapLocation()"
                            :disabled="locationSaving || !draftLocation"
                            class="inline-flex items-center justify-center gap-2 rounded-2xl bg-[var(--accent-dark)] px-4 py-3 text-sm font-bold text-[var(--accent-foreground)] transition hover:opacity-95 disabled:opacity-60 shadow-sm"
                        >
                            <i class="fa-solid fa-check"></i>
                            <span x-text="locationSaving ? 'Saving...' : 'Confirm location'">Confirm location</span>
                        </button>

                        <template x-if="draftLocation && typeof draftLocation.lat === 'number' && typeof draftLocation.lng === 'number'">
                            <button
                                type="button"
                                @click="confirmMapLocation(); window.MeramotGeolocation.updateUrlWithLocation(draftLocation.lat, draftLocation.lng, 12)"
                                class="inline-flex items-center justify-center gap-1.5 rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] transition hover:bg-[var(--mint-200)]"
                            >
                                <i class="fa-solid fa-compass"></i>
                                <span>Find Nearby Shops from Pin</span>
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── Logout Confirmation Modal (Exact match to Navbar.tsx) ── -->
    <div x-show="showLogoutConfirm" x-cloak class="fixed inset-0 z-[100] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/30 backdrop-blur-sm" @click="showLogoutConfirm = false"></div>
        <div class="relative z-[101] w-[90%] max-w-md rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-8 shadow-2xl text-center">
            <h2 class="text-2xl font-bold text-[var(--accent-dark)]">
                Are you sure?
            </h2>
            <p class="mt-3 text-sm text-[var(--muted-foreground)]">
                You will be logged out of your Meramot account.
            </p>
            <div class="mt-6 flex items-center justify-center gap-4">
                <button
                    type="button"
                    @click="showLogoutConfirm = false"
                    class="rounded-full border border-[var(--accent-dark)] bg-[var(--card)] px-6 py-2.5 text-sm font-semibold text-[var(--accent-dark)] transition hover:bg-[var(--mint-50)]"
                >
                    No
                </button>
                <form action="/logout" method="POST" class="inline">
                    @csrf
                    <button
                        type="submit"
                        class="rounded-full bg-[var(--accent-dark)] px-6 py-2.5 text-sm font-semibold text-[var(--accent-foreground)] transition hover:opacity-90"
                    >
                        Yes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- ── Mobile Notifications Pop-Up Tile ── -->
    <div
        x-show="notifModalOpen"
        x-cloak
        @keydown.escape.window="if (notifModalOpen) notifModalOpen = false"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="meramot-notif-modal-title"
    >
        <div
            x-show="notifModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-[#0b1f14]/50 backdrop-blur-sm"
            @click="notifModalOpen = false"
        ></div>

        <div
            x-show="notifModalOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            class="relative z-[111] w-full max-w-md overflow-hidden rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-2xl text-xs"
        >
            <div class="flex items-center justify-between border-b border-[var(--border)] pb-3 mb-3.5">
                <div class="flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--accent-dark)]">
                        <i class="fa-regular fa-bell text-sm"></i>
                    </span>
                    <div>
                        <h2 id="meramot-notif-modal-title" class="font-black text-base text-[var(--foreground)]">Notifications &amp; Alerts</h2>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold {{ $unreadNotifCount > 0 ? 'text-rose-600' : 'text-emerald-700' }}">
                            <span class="h-1.5 w-1.5 rounded-full {{ $unreadNotifCount > 0 ? 'bg-rose-600' : 'bg-emerald-600' }}"></span>
                            {{ $unreadNotifCount > 0 ? $unreadNotifCount . ' Unread' : 'Live' }}
                        </span>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    @if($unreadNotifCount > 0)
                        <form method="POST" action="{{ route('notifications.mark-read') }}" class="inline">
                            @csrf
                            <button type="submit" class="rounded-full bg-[var(--mint-100)] px-3 py-1 text-[10px] font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                                Mark read
                            </button>
                        </form>
                    @endif
                    <button
                        type="button"
                        @click="notifModalOpen = false"
                        class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-200)] transition"
                        aria-label="Close notifications"
                    >
                        ✕
                    </button>
                </div>
            </div>

            <div class="space-y-2.5 max-h-80 overflow-y-auto">
                @foreach($userNotifications as $notif)
                    @php
                        $notifDanger = ($notif->severity ?? '') === 'danger';
                        $notifWarn = ($notif->severity ?? '') === 'warning';
                        $notifCardCls = $notifDanger
                            ? 'border border-rose-300 bg-rose-50/90 dark:bg-rose-950/40'
                            : ($notifWarn
                                ? 'border border-amber-300 bg-amber-50/90 dark:bg-amber-950/40'
                                : 'border border-[var(--border)] bg-[var(--mint-100)]/90');
                    @endphp
                    <div class="rounded-2xl p-3.5 {{ $notifCardCls }}">
                        <div class="font-extrabold flex items-center justify-between gap-2 {{ $notifDanger ? 'text-rose-900 dark:text-rose-200' : 'text-[var(--foreground)]' }}">
                            <span class="flex items-center gap-1.5">
                                <i class="fa-solid {{ $notifDanger ? 'fa-ban text-rose-600' : ($notifWarn ? 'fa-triangle-exclamation text-amber-600' : 'fa-circle-check text-emerald-600') }}"></i>
                                <span>{{ $notif->title }}</span>
                            </span>
                            @if(!$notif->is_read)
                                <span class="rounded-full bg-rose-600 px-1.5 py-0.5 text-[9px] font-black uppercase text-white">New</span>
                            @endif
                        </div>
                        <p class="mt-1 text-[11px] leading-relaxed {{ $notifDanger ? 'text-rose-800 dark:text-rose-300' : 'text-[var(--muted-foreground)]' }}">{{ $notif->message }}</p>
                    </div>
                @endforeach

                <a href="{{ $isAdminAuth ? '/admin' : ($isVendorAuth ? $vendorWorkspaceUrl : ($isRiderAuth ? '/delivery' : (auth()->check() ? '/requests' : '/login'))) }}" @click="notifModalOpen = false" class="block rounded-2xl bg-[var(--mint-100)]/70 p-3.5 hover:bg-[var(--mint-200)] transition">
                    <div class="font-bold text-[var(--foreground)] flex items-center gap-2">
                        <i class="fa-solid fa-gavel text-amber-600"></i>
                        <span>Live Estimates &amp; Diagnostics Active</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-relaxed text-[var(--muted-foreground)]">Compare incoming workshop estimates and review bench diagnostics in real time.</p>
                </a>

                <a href="/shops?branched=true" @click="notifModalOpen = false" class="block rounded-2xl bg-[var(--mint-100)]/70 p-3.5 hover:bg-[var(--mint-200)] transition">
                    <div class="font-bold text-[var(--foreground)] flex items-center gap-2">
                        <i class="fa-solid fa-map-location-dot text-[var(--accent-dark)]"></i>
                        <span>Nationwide Workshop Network</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-relaxed text-[var(--muted-foreground)]">Browse verified repair centers and multi-branch specialists across Bangladesh.</p>
                </a>
            </div>

            <div class="mt-4 pt-3 border-t border-[var(--border)] flex items-center justify-between">
                @if($isVendorAuth)
                    <a href="{{ $vendorWorkspaceUrl }}" @click="notifModalOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">{{ $vendorWorkspaceLabel }}</a>
                    <a href="/vendor/alert-settings" @click="notifModalOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">SMS Alert Settings &rarr;</a>
                @elseif($isRiderAuth)
                    <a href="/delivery" @click="notifModalOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">Rider Dashboard</a>
                    <a href="/delivery/map" @click="notifModalOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">Live Delivery Map &rarr;</a>
                @elseif($isAdminAuth)
                    <a href="/admin" @click="notifModalOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">Admin Console</a>
                    <a href="/admin/moderation" @click="notifModalOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">Moderation Desk &rarr;</a>
                @else
                    <a href="{{ auth()->check() ? '/requests' : '/login' }}" @click="notifModalOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">{{ auth()->check() ? ($isBannedCustomer ? 'Ongoing & Past Orders' : 'My Requests') : 'Sign in to Track Orders' }}</a>
                    <a href="/shops/explore" @click="notifModalOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">Explore Workshops &rarr;</a>
                @endif
            </div>
        </div>
    </div>

    <!-- ── Classy Polished Delete Confirmation Pop-Up Tile ── -->
    <div
        x-show="deleteConfirmOpen"
        x-cloak
        @keydown.escape.window="if (deleteConfirmOpen) cancelDeleteConfirm()"
        @meramot-confirm-delete.window="openDeleteConfirm($event.detail.form)"
        class="fixed inset-0 z-[110] flex items-center justify-center p-4"
        role="dialog"
        aria-modal="true"
        aria-labelledby="meramot-delete-modal-title"
    >
        <div
            x-show="deleteConfirmOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0 bg-[#0b1f14]/55 backdrop-blur-sm"
            @click="cancelDeleteConfirm()"
        ></div>

        <div
            x-show="deleteConfirmOpen"
            x-transition:enter="transition ease-out duration-200"
            x-transition:enter-start="opacity-0 scale-95 translate-y-2"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-150"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-2"
            class="relative z-[111] w-full max-w-md overflow-hidden rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-[0_24px_64px_rgba(11,31,20,0.28)]"
        >
            <div class="h-1.5 w-full bg-gradient-to-r from-rose-500 via-rose-600 to-amber-500"></div>

            <div class="p-7 sm:p-8 text-center">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl border border-rose-200 bg-rose-50 text-rose-600 shadow-inner">
                    <i class="fa-solid fa-trash-can text-2xl"></i>
                </div>

                <h2 id="meramot-delete-modal-title" class="text-xl sm:text-2xl font-extrabold tracking-tight text-[var(--foreground)]" x-text="deleteConfirmTitle"></h2>

                <template x-if="deleteConfirmItem">
                    <div class="mt-3 inline-flex max-w-full items-center gap-2 rounded-full border border-[var(--border)] bg-[var(--background)] px-3.5 py-1.5 text-xs font-bold text-[var(--foreground)] shadow-sm">
                        <span class="h-2 w-2 shrink-0 rounded-full bg-rose-500"></span>
                        <span class="truncate" x-text="deleteConfirmItem"></span>
                    </div>
                </template>

                <p class="mt-3.5 text-sm leading-relaxed text-[var(--muted-foreground)]" x-text="deleteConfirmMessage"></p>

                <div class="mt-7 grid grid-cols-2 gap-3">
                    <button
                        type="button"
                        @click="cancelDeleteConfirm()"
                        :disabled="deleteConfirmSubmitting"
                        class="rounded-full border border-[var(--border)] bg-[var(--background)] px-5 py-2.5 text-sm font-bold text-[var(--foreground)] transition hover:bg-[var(--mint-50)] disabled:opacity-50"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        @click="proceedDeleteConfirm()"
                        :disabled="deleteConfirmSubmitting"
                        class="inline-flex items-center justify-center gap-2 rounded-full bg-rose-600 px-5 py-2.5 text-sm font-bold text-white shadow-md shadow-rose-600/20 transition hover:bg-rose-700 active:scale-[0.98] disabled:opacity-60"
                    >
                        <i class="fa-solid" :class="deleteConfirmSubmitting ? 'fa-spinner fa-spin' : 'fa-trash-can'"></i>
                        <span x-text="deleteConfirmSubmitting ? 'Deleting...' : deleteConfirmCta"></span>
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('submit', function (event) {
            const form = event.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-confirm-delete')) {
                return;
            }
            if (form.dataset.confirmed === 'true') {
                return;
            }
            event.preventDefault();
            window.dispatchEvent(new CustomEvent('meramot-confirm-delete', { detail: { form: form } }));
        });
    </script>

    <!-- Banned Customer Read-Only Account Restriction Banner -->
    @if($isBannedCustomer)
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
            <div class="p-4 text-xs font-semibold text-rose-950 rounded-2xl bg-rose-50 border border-rose-300 flex flex-col sm:flex-row sm:items-center justify-between gap-3 shadow-sm">
                <div class="space-y-0.5">
                    <div class="font-extrabold flex items-center gap-2 text-rose-900">
                        <i class="fa-solid fa-ban text-rose-600"></i>
                        <span>Account Restricted from New Orders &amp; Requests</span>
                    </div>
                    <p class="text-[11px] text-rose-800 leading-relaxed">
                        Reason: {{ $navAuthUser->ban_reason ?: 'Community guidelines violation in a submitted review' }}.
                        You can still view and track your <strong>ongoing and past orders</strong>, while new service bookings, cart orders, repair requests, and review submissions remain disabled.
                    </p>
                </div>
                <a href="/requests" class="shrink-0 inline-flex items-center gap-1.5 rounded-full bg-rose-700 px-4 py-2 text-[11px] font-bold text-white hover:bg-rose-800 transition">
                    <i class="fa-solid fa-list-check"></i>
                    <span>View Ongoing &amp; Past Orders</span>
                </a>
            </div>
        </div>
    @endif

    <!-- Flash Alert System -->
    @if(session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
            <div class="p-3.5 text-xs font-semibold text-[var(--foreground)] rounded-2xl bg-[var(--mint-200)] border border-[var(--border)] flex items-center justify-between shadow-sm">
                <span><i class="fa-solid fa-circle-check mr-2 text-emerald-700"></i> {{ session('success') }}</span>
            </div>
        </div>
    @endif
    @if(session('warning'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
            <div class="p-3.5 text-xs font-semibold text-amber-950 rounded-2xl bg-amber-100 border border-amber-300 flex items-center justify-between shadow-sm">
                <span><i class="fa-solid fa-triangle-exclamation mr-2 text-amber-700"></i> {{ session('warning') }}</span>
            </div>
        </div>
    @endif
    @if(session('error'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
            <div class="p-3.5 text-xs font-semibold text-rose-900 rounded-2xl bg-rose-100 border border-rose-300 flex items-center justify-between shadow-sm">
                <span><i class="fa-solid fa-circle-exclamation mr-2 text-rose-700"></i> {{ session('error') }}</span>
            </div>
        </div>
    @endif

    <!-- Main Dynamic Content Slot -->
    <main id="meramot-main-content" class="flex-grow w-full min-w-0">
        @yield('content')
    </main>

    <!-- Meramot Marketplace Footer -->
    <footer class="mt-14 border-t border-[var(--border)] bg-[var(--mint-100)] py-8 sm:py-10 text-xs text-[var(--muted-foreground)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-12 gap-x-6 gap-y-7 mb-8">
                <div class="col-span-2 sm:col-span-3 lg:col-span-4 pb-5 border-b border-[var(--border)]/60 lg:border-b-0 lg:pb-0 lg:pr-6">
                    <img src="/images/meramot.svg" alt="Meramot" class="h-9 sm:h-10 w-auto mb-2.5">
                    <p class="leading-relaxed max-w-md text-[var(--muted-foreground)]">
                        Bangladesh's premier digital electronics repair marketplace connecting device owners with vetted local workshops and tracked doorstep couriers.
                    </p>
                </div>
                <div class="col-span-1 lg:col-span-2">
                    <h5 class="font-bold text-[var(--foreground)] mb-2.5 uppercase tracking-wider text-[11px]">Popular Services</h5>
                    <ul class="space-y-1.5">
                        <li><a href="/shops?q=screen" class="hover:text-[var(--foreground)] hover:underline transition">Screen Replacement</a></li>
                        <li><a href="/shops?q=battery" class="hover:text-[var(--foreground)] hover:underline transition">Battery Replacement</a></li>
                        <li><a href="/shops?q=motherboard" class="hover:text-[var(--foreground)] hover:underline transition">Logic Board Diagnostics</a></li>
                        <li><a href="/shops?q=keyboard" class="hover:text-[var(--foreground)] hover:underline transition">Keyboard &amp; Port Repair</a></li>
                    </ul>
                </div>
                <div class="col-span-1 lg:col-span-3">
                    <h5 class="font-bold text-[var(--foreground)] mb-2.5 uppercase tracking-wider text-[11px]">Service Hubs</h5>
                    <ul class="space-y-1.5">
                        <li><a href="/shops?area=Banani" class="hover:text-[var(--foreground)] hover:underline transition">Banani DOHS &amp; Road 11</a></li>
                        <li><a href="/shops?area=Gulshan" class="hover:text-[var(--foreground)] hover:underline transition">Gulshan 1 &amp; 2 Circle</a></li>
                        <li><a href="/shops?area=Dhanmondi" class="hover:text-[var(--foreground)] hover:underline transition">Dhanmondi &amp; Panthapath</a></li>
                        <li><a href="/shops?area=Uttara" class="hover:text-[var(--foreground)] hover:underline transition">Uttara Sector 3 &amp; 7</a></li>
                    </ul>
                </div>
                <div class="col-span-2 sm:col-span-1 lg:col-span-3">
                    @if($isDevMode)
                        <h5 class="font-bold text-[var(--foreground)] mb-2.5 uppercase tracking-wider text-[11px]">Academic Course Work</h5>
                        <p class="mb-1.5">CSE 470: Software Engineering (Section 06)</p>
                        <p>Faculty: <strong>Nazmul Islam [NZU]</strong></p>
                        <p class="mt-2 text-[10px] text-emerald-800 font-bold">Strict MVC Architecture • Laravel 10 • FastAPI Microservice • PostgreSQL</p>
                    @else
                        <h5 class="font-bold text-[var(--foreground)] mb-2.5 uppercase tracking-wider text-[11px]">Customer &amp; Partner Hub</h5>
                        <ul class="grid grid-cols-2 sm:grid-cols-1 gap-x-4 gap-y-1.5">
                            @if(!$isAdminAuth && !$isVendorAuth && !$isRiderAuth && !$isBannedCustomer)
                                <li><a href="/requests/create" class="hover:text-[var(--foreground)] hover:underline transition">Post Repair Request</a></li>
                            @endif
                            <li><a href="/ai-consultant/chat" class="hover:text-[var(--foreground)] hover:underline transition">AI Fault Diagnostics</a></li>
                            <li><a href="/packages/catalog" class="hover:text-[var(--foreground)] hover:underline transition">Fixed-Price Packages</a></li>
                            @if($isVendorAuth)
                                <li><a href="{{ $vendorWorkspaceUrl }}" class="font-semibold text-[var(--accent-dark)] hover:underline">{{ $vendorWorkspaceLabel }}</a></li>
                            @elseif($isRiderAuth)
                                <li><a href="/delivery" class="font-semibold text-[var(--accent-dark)] hover:underline">Rider Dashboard</a></li>
                            @elseif($isAdminAuth)
                                <li><a href="/admin" class="font-semibold text-[var(--accent-dark)] hover:underline">Admin Console</a></li>
                            @else
                                <li><a href="/vendor/apply" class="hover:text-[var(--foreground)] hover:underline transition">Register as Partner Shop</a></li>
                                <li><a href="{{ $isCustomerAuth ? '/delivery-agent/register' : '/delivery/login' }}" class="hover:text-[var(--foreground)] hover:underline transition">{{ $isCustomerAuth ? 'Become a Delivery Partner' : 'Delivery Partner Portal' }}</a></li>
                            @endif
                        </ul>
                    @endif
                </div>
            </div>
            <div class="border-t border-[var(--border)] pt-5 flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px] text-center sm:text-left">
                <p>&copy; 2026 Meramot (মেরামত) Digital Marketplace. All rights reserved.</p>
                <div class="flex flex-wrap items-center justify-center sm:justify-end gap-x-4 gap-y-2">
                    <a href="/shops" class="hover:text-[var(--foreground)] hover:underline transition">Explore All Shops</a>
                    <a href="/packages/catalog" class="hover:text-[var(--foreground)] hover:underline transition">Service Packages</a>
                    @if(!$isAdminAuth && !$isVendorAuth && !$isRiderAuth && !$isBannedCustomer)
                        <a href="/requests/create" class="hover:text-[var(--foreground)] hover:underline transition">Request Repair</a>
                    @endif
                    <a href="/ai-consultant/chat" class="hover:text-[var(--foreground)] hover:underline transition">AI Diagnostics</a>
                    <a href="/support" class="hover:text-[var(--foreground)] hover:underline transition">Support &amp; Disputes</a>
                    <a href="{{ request()->fullUrlWithQuery(['dev' => $isDevMode ? '0' : '1']) }}" class="text-[10px] text-[var(--muted-foreground)] opacity-40 hover:opacity-100 transition" title="Toggle developer presentation mode (Ctrl+Shift+D)">
                        <i class="fa-solid fa-code"></i> {{ $isDevMode ? 'Dev Mode (ON)' : 'Dev' }}
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Slide-Over Mini-Cart Drawer (Feature R8 E-Commerce Quick Cart) -->
    @if(!$isAdminAuth && !$isVendorAuth && !$isRiderAuth && !$isBannedCustomer)
        @php
            $miniCartItems = session('cart', []);
            $miniCartSubtotal = array_reduce($miniCartItems, fn ($s, $it) => $s + ((float) ($it['price'] ?? 0) * (int) ($it['quantity'] ?? 1)), 0.0);
        @endphp
        <div x-show="miniCartOpen" x-cloak class="fixed inset-0 z-[120]" @keydown.escape.window="miniCartOpen = false">
            <div class="fixed inset-0 bg-black/45 backdrop-blur-xs" @click="miniCartOpen = false"></div>
            <aside class="fixed right-0 top-0 z-[121] flex h-full w-full max-w-md flex-col border-l border-[var(--border)] bg-[var(--background)] shadow-2xl">
                <div class="flex items-center justify-between border-b border-[var(--border)] bg-[var(--card)] px-5 py-4">
                    <div class="flex items-center gap-2.5">
                        <span class="flex h-9 w-9 items-center justify-center rounded-2xl bg-[var(--accent-dark)] text-[var(--accent-foreground)]">
                            <i class="fa-solid fa-bag-shopping text-sm"></i>
                        </span>
                        <div>
                            <h3 class="text-sm font-black text-[var(--foreground)]">Direct-Order Cart</h3>
                            <p class="text-[11px] text-[var(--muted-foreground)]">{{ count($miniCartItems) }} {{ count($miniCartItems) === 1 ? 'service' : 'services' }} selected</p>
                        </div>
                    </div>
                    <button type="button" @click="miniCartOpen = false" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)]">
                        Close
                    </button>
                </div>

                <div class="flex-1 overflow-y-auto p-4 space-y-3">
                    @forelse($miniCartItems as $mKey => $mItem)
                        <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-3.5 flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                                    {{ $mItem['category'] ?? 'Repair Service' }}
                                </span>
                                <p class="mt-1 text-xs font-extrabold text-[var(--foreground)] break-words">{{ $mItem['title'] ?? 'Service' }}</p>
                                <p class="mt-0.5 text-xs font-bold text-[var(--accent-dark)]">
                                    ৳{{ number_format((float) ($mItem['price'] ?? 0)) }} &times; {{ (int) ($mItem['quantity'] ?? 1) }}
                                </p>
                            </div>
                            <span class="font-black text-xs text-[var(--foreground)] shrink-0">
                                ৳{{ number_format((float) ($mItem['price'] ?? 0) * (int) ($mItem['quantity'] ?? 1)) }}
                            </span>
                        </div>
                    @empty
                        <div class="rounded-2xl border border-dashed border-[var(--border)] bg-[var(--card)] p-8 text-center">
                            <p class="text-xs font-semibold text-[var(--muted-foreground)]">Your cart is currently empty.</p>
                        </div>
                    @endforelse
                </div>

                <div class="border-t border-[var(--border)] bg-[var(--card)] p-4 space-y-3">
                    <div class="flex items-center justify-between text-xs">
                        <span class="font-semibold text-[var(--muted-foreground)]">Services Subtotal</span>
                        <span class="text-base font-black text-[var(--accent-dark)]">৳{{ number_format($miniCartSubtotal) }}</span>
                    </div>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Apply promo codes (<span class="font-mono font-bold">MERAMOT10</span>, <span class="font-mono font-bold">FREEDELIVERY</span>) on the cart page.</p>
                    <div class="grid grid-cols-2 gap-2">
                        <a href="/packages/catalog" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-4 py-2.5 text-center text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                            + Add Services
                        </a>
                        <a href="/cart" class="rounded-full bg-[var(--accent-dark)] px-4 py-2.5 text-center text-xs font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                            Open Cart &amp; Checkout &rarr;
                        </a>
                    </div>
                </div>
            </aside>
        </div>
    @endif

    <script>
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && (e.key === 'D' || e.key === 'd')) {
                e.preventDefault();
                const targetUrl = new URL(window.location.href);
                targetUrl.searchParams.set('dev', '{{ $isDevMode ? "0" : "1" }}');
                window.location.href = targetUrl.toString();
            }
        });

        // Automatic client-side image compression for multipart uploads (> 800 KB -> crisp <= 1600px JPEG)
        (function () {
            const MAX_DIMENSION = 1600;
            const JPEG_QUALITY = 0.84;
            const COMPRESS_THRESHOLD_BYTES = 800 * 1024;
            const IMAGE_MIME_RE = /^image\/(jpeg|jpg|pjpeg|png|webp|bmp)$/i;

            function shouldCompress(file) {
                return file && !file._meramotOptimized && IMAGE_MIME_RE.test(file.type) && file.size > COMPRESS_THRESHOLD_BYTES;
            }

            function compressImageFile(file) {
                return new Promise(function (resolve) {
                    const objectUrl = URL.createObjectURL(file);
                    const img = new Image();
                    img.onload = function () {
                        URL.revokeObjectURL(objectUrl);
                        let targetW = img.naturalWidth || img.width;
                        let targetH = img.naturalHeight || img.height;
                        if (!targetW || !targetH) {
                            file._meramotOptimized = true;
                            return resolve(file);
                        }
                        if (targetW > MAX_DIMENSION || targetH > MAX_DIMENSION) {
                            const ratio = Math.min(MAX_DIMENSION / targetW, MAX_DIMENSION / targetH);
                            targetW = Math.max(1, Math.round(targetW * ratio));
                            targetH = Math.max(1, Math.round(targetH * ratio));
                        }
                        const canvas = document.createElement('canvas');
                        canvas.width = targetW;
                        canvas.height = targetH;
                        const ctx = canvas.getContext('2d');
                        if (!ctx) {
                            file._meramotOptimized = true;
                            return resolve(file);
                        }
                        ctx.fillStyle = '#ffffff';
                        ctx.fillRect(0, 0, targetW, targetH);
                        ctx.drawImage(img, 0, 0, targetW, targetH);
                        canvas.toBlob(function (blob) {
                            if (!blob || blob.size >= file.size) {
                                file._meramotOptimized = true;
                                return resolve(file);
                            }
                            const baseName = file.name.replace(/\.[^.]+$/, '') || 'photo';
                            const optimized = new File([blob], baseName + '.jpg', {
                                type: 'image/jpeg',
                                lastModified: Date.now()
                            });
                            optimized._meramotOptimized = true;
                            resolve(optimized);
                        }, 'image/jpeg', JPEG_QUALITY);
                    };
                    img.onerror = function () {
                        URL.revokeObjectURL(objectUrl);
                        file._meramotOptimized = true;
                        resolve(file);
                    };
                    img.src = objectUrl;
                });
            }

            async function optimizeInputFiles(input) {
                if (!input || !input.files || !input.files.length || typeof DataTransfer === 'undefined') {
                    return;
                }
                const files = Array.from(input.files);
                if (!files.some(shouldCompress)) {
                    return;
                }
                const dt = new DataTransfer();
                for (const f of files) {
                    const nextFile = shouldCompress(f) ? await compressImageFile(f) : f;
                    dt.items.add(nextFile);
                }
                input.files = dt.files;
            }

            document.addEventListener('change', function (e) {
                if (e.target && e.target.matches && e.target.matches('input[type="file"]')) {
                    optimizeInputFiles(e.target);
                }
            });

            document.addEventListener('submit', function (e) {
                const form = e.target;
                if (!form || form._meramotSubmitting || String(form.enctype || '').toLowerCase() !== 'multipart/form-data') {
                    return;
                }
                const fileInputs = Array.from(form.querySelectorAll('input[type="file"]'));
                const needsOptimization = fileInputs.some(function (inp) {
                    return inp.files && Array.from(inp.files).some(shouldCompress);
                });
                if (!needsOptimization) {
                    return;
                }
                e.preventDefault();
                const submitBtn = e.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
                const originalHtml = submitBtn && submitBtn.tagName === 'BUTTON' ? submitBtn.innerHTML : null;
                if (submitBtn) {
                    submitBtn.disabled = true;
                    if (originalHtml !== null) {
                        submitBtn.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin mr-1.5"></i> Optimizing & Uploading...';
                    }
                }
                Promise.all(fileInputs.map(optimizeInputFiles)).finally(function () {
                    form._meramotSubmitting = true;
                    if (submitBtn) {
                        submitBtn.disabled = false;
                    }
                    if (typeof form.requestSubmit === 'function') {
                        form.requestSubmit(submitBtn || undefined);
                    } else {
                        HTMLFormElement.prototype.submit.call(form);
                    }
                });
            });
            document.addEventListener('wheel', function (e) {
                const active = document.activeElement;
                if (
                    active &&
                    active.tagName === 'INPUT' &&
                    active.type === 'number' &&
                    (e.target === active || active.contains(e.target))
                ) {
                    active.blur();
                }
            }, { capture: true, passive: true });
        })();

        // Strict Input Sanitizer & Phone-Paste Isolation Guard
        (function () {
            if (window.MeramotInputGuard) return;

            const INVISIBLE_RE = /[\u200B-\u200F\u202A-\u202E\u2066-\u2069\uFEFF]/g;
            const BN_MAP = { '০': '0', '১': '1', '২': '2', '৩': '3', '৪': '4', '৫': '5', '৬': '6', '৭': '7', '৮': '8', '৯': '9' };
            const PURE_PHONE_FRAGMENT_RE = /^\s*(?:\+?880?1?|01\d{2,}|\+?\d{4,})[\d\s\-()]*$/;

            let lastPhonePasteTs = 0;

            function cleanUnicodeDigits(str) {
                return String(str || '')
                    .replace(INVISIBLE_RE, '')
                    .replace(/[০-৯]/g, (d) => BN_MAP[d] || d);
            }

            function syncInputValue(el, nextValue) {
                if (!el || el.value === nextValue) return;
                el._meramotSanitizing = true;
                el.value = nextValue;
                el.dispatchEvent(new Event('input', { bubbles: true }));
                el._meramotSanitizing = false;
            }

            function isPhoneInput(el) {
                if (!el || el.tagName !== 'INPUT') return false;
                const name = (el.getAttribute('name') || '').toLowerCase();
                return el.type === 'tel' || name === 'phone' || name === 'contact_phone' || name === 'owner_phone';
            }

            function isStrictDigitsInput(el) {
                if (!el || el.tagName !== 'INPUT') return false;
                const name = (el.getAttribute('name') || '').toLowerCase();
                return el.hasAttribute('data-digits-only') || name === 'nid_number' || name === 'otp' || name === 'year';
            }

            function isStrictNumberInput(el) {
                return Boolean(el && el.tagName === 'INPUT' && (el.type === 'number' || el.hasAttribute('data-strict-numeric')));
            }

            function isIntegerOnlyNumber(el) {
                const mode = el.dataset.numericMode || '';
                const step = el.getAttribute('step');
                const name = (el.getAttribute('name') || '').toLowerCase();
                return mode === 'integer'
                    || step === '1'
                    || name === 'estimated_days'
                    || name === 'warranty_days'
                    || name.endsWith('[warranty_days]')
                    || name === 'limit'
                    || name === 'estimated_budget';
            }

            function allowsNegativeNumber(el) {
                const minAttr = el.getAttribute('min');
                const name = (el.getAttribute('name') || '').toLowerCase();
                return (minAttr !== null && parseFloat(minAttr) < 0) || name === 'latitude' || name === 'longitude';
            }

            function sanitizePhoneString(raw) {
                let text = cleanUnicodeDigits(raw).trim();
                if (!text) return '';

                // Extract a Bangladeshi mobile token if the user pasted text with labels or an address.
                const bdToken = text.match(/(?:\+?880|00880)?[\s.\-()]*0?(1[3-9](?:[\s.\-()]*\d){8})/);
                if (bdToken && bdToken[1]) {
                    const subscriber = bdToken[1].replace(/\D/g, '');
                    const hasCountry = /^(?:\+?880|00880)/.test(bdToken[0].replace(/[\s.\-()]/g, ''));
                    return hasCountry ? ('+880' + subscriber) : ('0' + subscriber);
                }

                // Fallback for partial typing/pasting
                if (text.startsWith('00880')) {
                    text = '+' + text.slice(2);
                }
                const hasLeadingPlus = text.startsWith('+');
                const digitsOnly = text.replace(/\D/g, '');
                if (!digitsOnly) return hasLeadingPlus ? '+' : '';
                const combined = (hasLeadingPlus ? '+' : '') + digitsOnly;
                return combined.slice(0, hasLeadingPlus ? 14 : 13);
            }

            function sanitizeNumericString(raw, allowNeg, intOnly) {
                const cleaned = cleanUnicodeDigits(raw)
                    .replace(/[,৳$€£]/g, '')
                    .replace(/\b(?:bdt|tk)\b/gi, '')
                    .trim();
                const match = cleaned.match(allowNeg ? /-?\d+(?:\.\d+)?/ : /\d+(?:\.\d+)?/);
                if (!match) return '';
                let token = match[0];
                if (!allowNeg && token.startsWith('-')) {
                    token = token.replace(/^-+/, '');
                }
                if (intOnly && token.includes('.')) {
                    token = token.split('.')[0];
                }
                return token;
            }

            function applyFieldAttributes(root = document) {
                root.querySelectorAll('input[type="tel"], input[name="phone"], input[name="contact_phone"], input[name="owner_phone"]').forEach((el) => {
                    if (!el.getAttribute('autocomplete')) el.setAttribute('autocomplete', 'tel');
                    if (!el.getAttribute('inputmode')) el.setAttribute('inputmode', 'tel');
                    if (!el.getAttribute('maxlength')) el.setAttribute('maxlength', '14');
                });
                root.querySelectorAll('input[name="area"]').forEach((el) => {
                    if (!el.getAttribute('autocomplete')) el.setAttribute('autocomplete', 'address-level3');
                    if (el.dataset.lastValidArea === undefined) el.dataset.lastValidArea = el.value || '';
                });
                root.querySelectorAll('input[name="city"], select[name="city"]').forEach((el) => {
                    if (!el.getAttribute('autocomplete')) el.setAttribute('autocomplete', 'address-level2');
                });
                root.querySelectorAll('input[name="address"], input[name="pickup_address"], input[name="gps_address"], input[name="shop_address"]').forEach((el) => {
                    if (!el.getAttribute('autocomplete')) el.setAttribute('autocomplete', 'street-address');
                });
                root.querySelectorAll('input[name="nid_number"], input[name="otp"], input[name="year"], input[data-digits-only]').forEach((el) => {
                    if (!el.getAttribute('inputmode')) el.setAttribute('inputmode', 'numeric');
                    if (!el.getAttribute('pattern')) el.setAttribute('pattern', '[0-9]*');
                    const name = (el.getAttribute('name') || '').toLowerCase();
                    if (!el.getAttribute('maxlength')) {
                        if (name === 'nid_number') el.setAttribute('maxlength', '17');
                        else if (name === 'otp') el.setAttribute('maxlength', '6');
                        else if (name === 'year') el.setAttribute('maxlength', '4');
                    }
                });
            }

            document.addEventListener('keydown', function (e) {
                const el = e.target;
                if (!el || el.tagName !== 'INPUT') return;
                if (e.ctrlKey || e.metaKey || e.altKey) return;
                const navKeys = ['Backspace', 'Delete', 'Tab', 'Escape', 'Enter', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'];
                if (navKeys.includes(e.key)) return;

                if (isPhoneInput(el)) {
                    const isDigit = /^[0-9০-৯]$/.test(e.key);
                    if (isDigit) return;
                    if (e.key === '+') {
                        const selStart = el.selectionStart ?? 0;
                        const alreadyHasPlus = el.value.includes('+') && !(selStart === 0 && (el.selectionEnd ?? 0) > 0);
                        if (selStart === 0 && !alreadyHasPlus) return;
                    }
                    e.preventDefault();
                    return;
                }

                if (isStrictDigitsInput(el)) {
                    if (!/^[0-9০-৯]$/.test(e.key)) {
                        e.preventDefault();
                    }
                    return;
                }

                if (isStrictNumberInput(el)) {
                    if (['e', 'E', '+', ','].includes(e.key)) {
                        e.preventDefault();
                        return;
                    }
                    if (e.key === '-') {
                        if (!allowsNegativeNumber(el)) e.preventDefault();
                        return;
                    }
                    if (e.key === '.') {
                        if (isIntegerOnlyNumber(el) || String(el.value || '').includes('.')) {
                            e.preventDefault();
                        }
                        return;
                    }
                    if (e.key.length === 1 && !/^[0-9০-৯]$/.test(e.key)) {
                        e.preventDefault();
                    }
                }
            }, { capture: true });

            document.addEventListener('paste', function (e) {
                const el = e.target;
                if (!el || el.tagName !== 'INPUT') return;
                const rawClip = e.clipboardData ? e.clipboardData.getData('text/plain') : '';

                if (isPhoneInput(el)) {
                    e.preventDefault();
                    e.stopPropagation();
                    lastPhonePasteTs = Date.now();
                    const cleaned = sanitizePhoneString(rawClip);
                    syncInputValue(el, cleaned);
                    return;
                }

                if (isStrictDigitsInput(el)) {
                    e.preventDefault();
                    e.stopPropagation();
                    const maxLen = parseInt(el.getAttribute('maxlength') || '32', 10);
                    let digits = cleanUnicodeDigits(rawClip).replace(/\D/g, '');
                    if (!Number.isNaN(maxLen) && maxLen > 0) digits = digits.slice(0, maxLen);
                    syncInputValue(el, digits);
                    return;
                }

                if (isStrictNumberInput(el)) {
                    e.preventDefault();
                    e.stopPropagation();
                    const cleanedNum = sanitizeNumericString(rawClip, allowsNegativeNumber(el), isIntegerOnlyNumber(el));
                    if (cleanedNum !== '') {
                        syncInputValue(el, cleanedNum);
                    }
                }
            }, { capture: true });

            document.addEventListener('input', function (e) {
                const el = e.target;
                if (!el || el.tagName !== 'INPUT' || el._meramotSanitizing) return;

                if (isPhoneInput(el)) {
                    const cleaned = sanitizePhoneString(el.value);
                    if (cleaned !== el.value) {
                        syncInputValue(el, cleaned);
                    }
                    return;
                }

                if (isStrictDigitsInput(el)) {
                    const maxLen = parseInt(el.getAttribute('maxlength') || '32', 10);
                    let digits = cleanUnicodeDigits(el.value).replace(/\D/g, '');
                    if (!Number.isNaN(maxLen) && maxLen > 0) digits = digits.slice(0, maxLen);
                    if (digits !== el.value) {
                        syncInputValue(el, digits);
                    }
                    return;
                }

                if (el.getAttribute('name') === 'area') {
                    const isAutomatedSplit = e.inputType === 'insertReplacementText'
                        || e.inputType === 'insertFromPaste'
                        || (Date.now() - lastPhonePasteTs) < 120;
                    if (isAutomatedSplit && PURE_PHONE_FRAGMENT_RE.test(el.value)) {
                        syncInputValue(el, el.dataset.lastValidArea || '');
                        return;
                    }
                    el.dataset.lastValidArea = el.value;
                    return;
                }

                if (isStrictNumberInput(el)) {
                    if (!allowsNegativeNumber(el) && String(el.value).includes('-')) {
                        syncInputValue(el, String(el.value).replace(/-/g, ''));
                    }
                }
            }, { capture: true });

            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', () => applyFieldAttributes(document));
            } else {
                applyFieldAttributes(document);
            }

            if (typeof MutationObserver !== 'undefined') {
                new MutationObserver((mutations) => {
                    for (const m of mutations) {
                        if (m.addedNodes && m.addedNodes.length > 0) {
                            applyFieldAttributes(document);
                            break;
                        }
                    }
                }).observe(document.documentElement, { childList: true, subtree: true });
            }

            window.MeramotInputGuard = {
                sanitizePhoneString,
                sanitizeNumericString,
                cleanUnicodeDigits,
                applyFieldAttributes
            };
        })();

        /**
         * In-place Soft Navigation Engine (No Forced Full-Page Reload / Scroll-to-Top Jumps)
         * Keeps page filters, branch scopes, sorting, pagination, and cart actions responsive in place.
         */
        (function () {
            if (window.MeramotSoftNav) return;

            const MAIN_SELECTOR = '#meramot-main-content';
            const SCROLL_RESTORE_KEY = 'meramot.scrollRestore';
            let activeController = null;
            let navSequence = 0;

            function isSoftNavExcludedPath(pathname) {
                return /^\/(auth|login|register|logout|delivery\/login|vendor\/apply)/i.test(pathname)
                    || /\/(receipt|invoice|export|download|pdf)(\/|$)/i.test(pathname);
            }

            function canSoftHandleUrl(rawHref) {
                if (!rawHref) return null;
                let targetUrl;
                try {
                    targetUrl = new URL(rawHref, window.location.href);
                } catch (e) {
                    return null;
                }
                if (targetUrl.origin !== window.location.origin) return null;
                if (isSoftNavExcludedPath(targetUrl.pathname)) return null;

                const currentUrl = new URL(window.location.href);
                // Only skip if purely an in-page hash jump with identical path & query
                if (targetUrl.pathname === currentUrl.pathname
                    && targetUrl.search === currentUrl.search
                    && targetUrl.hash) {
                    return null;
                }

                // Swap in place when filtering/sorting/paginating on the same route
                if (targetUrl.pathname === currentUrl.pathname) {
                    return targetUrl;
                }

                return null;
            }

            function syncHeaderState(doc, resolvedUrl) {
                try {
                    const currentCatLinks = document.querySelectorAll('header a[href^="/shops?category="], header a[href="/packages/catalog"]');
                    const nextCatLinks = doc.querySelectorAll('header a[href^="/shops?category="], header a[href="/packages/catalog"]');
                    const nextByHref = new Map();
                    nextCatLinks.forEach((link) => {
                        nextByHref.set(link.getAttribute('href'), link.className);
                    });
                    currentCatLinks.forEach((link) => {
                        const cls = nextByHref.get(link.getAttribute('href'));
                        if (typeof cls === 'string') {
                            link.className = cls;
                        }
                    });

                    const parsed = new URL(resolvedUrl, window.location.href);
                    document.querySelectorAll('header form[action="/shops"]').forEach((form) => {
                        ['category', 'sort', 'lat', 'lng'].forEach((param) => {
                            const val = parsed.searchParams.get(param);
                            let input = form.querySelector(`input[name="${param}"]`);
                            if (val) {
                                if (!input) {
                                    input = document.createElement('input');
                                    input.type = 'hidden';
                                    input.name = param;
                                    form.prepend(input);
                                }
                                input.value = val;
                            } else if (input && input.type === 'hidden') {
                                input.remove();
                            }
                        });
                        const qInput = form.querySelector('input[name="q"]');
                        if (qInput && document.activeElement !== qInput) {
                            qInput.value = parsed.searchParams.get('q') || '';
                        }
                    });
                } catch (e) {}
            }

            function captureAlpineTabs(mainEl) {
                const tabs = [];
                if (!window.Alpine || typeof window.Alpine.$data !== 'function') return tabs;
                mainEl.querySelectorAll('[x-data]').forEach((el, index) => {
                    try {
                        const data = window.Alpine.$data(el);
                        if (data && typeof data === 'object' && 'activeTab' in data) {
                            tabs.push({ index, activeTab: data.activeTab });
                        }
                    } catch (e) {}
                });
                return tabs;
            }

            function restoreAlpineTabs(mainEl, savedTabs) {
                if (!savedTabs.length || !window.Alpine || typeof window.Alpine.$data !== 'function') return;
                const nodes = mainEl.querySelectorAll('[x-data]');
                savedTabs.forEach((entry) => {
                    const el = nodes[entry.index];
                    if (!el) return;
                    try {
                        const data = window.Alpine.$data(el);
                        if (data && typeof data === 'object' && 'activeTab' in data && data.activeTab !== entry.activeTab) {
                            data.activeTab = entry.activeTab;
                        }
                    } catch (e) {}
                });
            }

            function setScrollYInstant(y) {
                const maxScroll = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
                const targetY = Math.min(Math.max(0, y), maxScroll);
                window.scrollTo({ top: targetY, left: 0, behavior: 'instant' });
            }

            async function visit(targetHref, options = {}) {
                const mainEl = document.querySelector(MAIN_SELECTOR);
                if (!mainEl) {
                    window.location.href = targetHref;
                    return false;
                }

                let targetUrl;
                try {
                    targetUrl = new URL(targetHref, window.location.href);
                } catch (e) {
                    window.location.href = targetHref;
                    return false;
                }

                if (targetUrl.origin !== window.location.origin || isSoftNavExcludedPath(targetUrl.pathname)) {
                    window.location.href = targetUrl.toString();
                    return false;
                }

                if (activeController) {
                    activeController.abort();
                }
                activeController = new AbortController();
                const seq = ++navSequence;

                const currentUrl = new URL(window.location.href);
                const samePath = targetUrl.pathname === currentUrl.pathname;
                const preserveScroll = options.preserveScroll !== undefined ? options.preserveScroll : samePath;

                const prevScrollY = typeof options.scrollY === 'number' ? options.scrollY : window.scrollY;
                const prevHScrolls = Array.from(mainEl.querySelectorAll('.overflow-x-auto')).map((el) => el.scrollLeft);
                const shouldKeepTab = samePath && !targetUrl.searchParams.has('tab') && !targetUrl.hash;
                const savedTabs = shouldKeepTab ? captureAlpineTabs(mainEl) : [];

                mainEl.style.transition = 'opacity 120ms ease';
                mainEl.style.opacity = '0.72';

                try {
                    const response = await fetch(targetUrl.toString(), {
                        method: 'GET',
                        headers: {
                            'Accept': 'text/html,application/xhtml+xml',
                            'X-Meramot-SoftNav': '1'
                        },
                        credentials: 'same-origin',
                        signal: activeController.signal
                    });

                    if (seq !== navSequence) return false;

                    const contentType = response.headers.get('content-type') || '';
                    if (!response.ok || !contentType.includes('text/html')) {
                        window.location.href = targetUrl.toString();
                        return false;
                    }

                    const resolvedUrl = response.url || targetUrl.toString();
                    const resolvedParsed = new URL(resolvedUrl, window.location.href);
                    if (isSoftNavExcludedPath(resolvedParsed.pathname)) {
                        window.location.href = resolvedUrl;
                        return false;
                    }

                    const html = await response.text();
                    const doc = new DOMParser().parseFromString(html, 'text/html');
                    const incomingMain = doc.querySelector(MAIN_SELECTOR);
                    if (!incomingMain) {
                        window.location.href = resolvedUrl;
                        return false;
                    }

                    mainEl.style.minHeight = mainEl.offsetHeight + 'px';
                    mainEl.innerHTML = incomingMain.innerHTML;
                    if (preserveScroll) {
                        setScrollYInstant(prevScrollY);
                    }
                    if (doc.title) {
                        document.title = doc.title;
                    }

                    if (!options.skipHistory) {
                        if (options.replace) {
                            window.history.replaceState({ meramotSoftNav: true, url: resolvedUrl }, '', resolvedUrl);
                        } else if (resolvedUrl !== window.location.href) {
                            window.history.pushState({ meramotSoftNav: true, url: resolvedUrl }, '', resolvedUrl);
                        }
                    }

                    syncHeaderState(doc, resolvedUrl);

                    if (window.MeramotGeolocation && typeof window.MeramotGeolocation.refreshPageBindings === 'function') {
                        window.MeramotGeolocation.refreshPageBindings();
                    }
                    if (window.MeramotInputGuard && typeof window.MeramotInputGuard.applyFieldAttributes === 'function') {
                        window.MeramotInputGuard.applyFieldAttributes(mainEl);
                    }

                    requestAnimationFrame(() => {
                        requestAnimationFrame(() => {
                            if (seq !== navSequence) return;
                            if (shouldKeepTab && savedTabs.length) {
                                restoreAlpineTabs(mainEl, savedTabs);
                            }
                            mainEl.style.minHeight = '';
                            if (preserveScroll) {
                                setScrollYInstant(prevScrollY);
                                const nextHScrolls = mainEl.querySelectorAll('.overflow-x-auto');
                                prevHScrolls.forEach((left, i) => {
                                    const container = nextHScrolls[i];
                                    if (container && left > 0) {
                                        container.scrollLeft = Math.min(left, Math.max(0, container.scrollWidth - container.clientWidth));
                                    }
                                });
                            } else {
                                setScrollYInstant(0);
                            }
                            mainEl.style.opacity = '1';
                        });
                    });

                    window.dispatchEvent(new CustomEvent('meramot-softnav-complete', { detail: { url: resolvedUrl } }));
                    return true;
                } catch (err) {
                    if (err && err.name === 'AbortError') {
                        return false;
                    }
                    mainEl.style.minHeight = '';
                    mainEl.style.opacity = '1';
                    window.location.href = targetUrl.toString();
                    return false;
                }
            }

            function buildGetFormUrl(form, submitter = null) {
                const actionAttr = form.getAttribute('action') || window.location.href;
                const url = new URL(actionAttr, window.location.href);
                const formData = new FormData(form);
                if (submitter && submitter.name) {
                    formData.append(submitter.name, submitter.value || '');
                }
                const params = new URLSearchParams();
                for (const [k, v] of formData.entries()) {
                    if (typeof v === 'string') {
                        params.append(k, v);
                    }
                }
                url.search = params.toString();
                return url;
            }

            function saveScrollRestore() {
                try {
                    sessionStorage.setItem(SCROLL_RESTORE_KEY, JSON.stringify({
                        path: window.location.pathname,
                        y: window.scrollY,
                        ts: Date.now()
                    }));
                } catch (e) {}
            }

            // Intercept same-page links (filters, sort tabs, areas, branches, pagination, resets)
            document.addEventListener('click', function (e) {
                if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) {
                    return;
                }
                const anchor = e.target.closest('a[href]');
                if (!anchor) return;
                if (anchor.hasAttribute('download') || anchor.hasAttribute('data-no-softnav') || anchor.hasAttribute('data-hard-reload')) {
                    return;
                }
                if (anchor.target && anchor.target !== '_self') return;

                const href = anchor.getAttribute('href');
                if (!href || href.startsWith('#') || href.startsWith('javascript:') || href.startsWith('mailto:') || href.startsWith('tel:')) {
                    return;
                }

                const targetUrl = canSoftHandleUrl(anchor.href);
                if (!targetUrl) return;

                const capturedScrollY = window.scrollY;
                e.preventDefault();
                visit(targetUrl.toString(), { scrollY: capturedScrollY });
            });

            // Intercept same-page GET forms & preserve scroll across POST/PATCH/DELETE redirects
            document.addEventListener('submit', function (e) {
                if (e.defaultPrevented) return;
                const form = e.target;
                if (!form || form.tagName !== 'FORM' || form.hasAttribute('data-no-softnav')) return;

                const method = (form.getAttribute('method') || 'GET').toUpperCase();
                if (method === 'GET') {
                    const targetUrl = buildGetFormUrl(form, e.submitter || null);
                    const softTarget = canSoftHandleUrl(targetUrl.toString());
                    if (softTarget) {
                        const capturedScrollY = window.scrollY;
                        e.preventDefault();
                        visit(softTarget.toString(), { scrollY: capturedScrollY });
                    }
                } else {
                    saveScrollRestore();
                }
            });

            // Also catch inline onchange="this.form.submit()" calls used on filter checkboxes/selects
            const nativeFormSubmit = HTMLFormElement.prototype.submit;
            HTMLFormElement.prototype.submit = function () {
                const method = (this.getAttribute('method') || 'GET').toUpperCase();
                if (!this.hasAttribute('data-no-softnav')) {
                    if (method === 'GET') {
                        const targetUrl = buildGetFormUrl(this);
                        const softTarget = canSoftHandleUrl(targetUrl.toString());
                        if (softTarget) {
                            visit(softTarget.toString(), { scrollY: window.scrollY });
                            return;
                        }
                    } else {
                        saveScrollRestore();
                    }
                }
                return nativeFormSubmit.apply(this, arguments);
            };

            window.addEventListener('popstate', function () {
                visit(window.location.href, { skipHistory: true, preserveScroll: true });
            });

            document.addEventListener('DOMContentLoaded', function () {
                try {
                    const raw = sessionStorage.getItem(SCROLL_RESTORE_KEY);
                    if (!raw) return;
                    sessionStorage.removeItem(SCROLL_RESTORE_KEY);
                    const saved = JSON.parse(raw);
                    if (saved && saved.path === window.location.pathname && (Date.now() - saved.ts) < 15000 && saved.y > 0) {
                        requestAnimationFrame(() => {
                            setScrollYInstant(saved.y);
                        });
                    }
                } catch (e) {}
            });

            window.MeramotSoftNav = {
                visit
            };
        })();
    </script>
</body>
</html>

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
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <!-- Standalone Client Modules (Served from root `frontend/js/` with cache-busting version) -->
    @php
        $jsAssetVer = @filemtime(base_path('../frontend/js/geolocation-helper.js')) ?: time();
    @endphp
    <script defer src="/js/geolocation-helper.js?v={{ $jsAssetVer }}"></script>
    <script defer src="/js/marketplace-client.js?v={{ $jsAssetVer }}"></script>
    
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
            overflow-x: hidden;
            max-width: 100vw;
            width: 100%;
            background-color: var(--background);
            color: var(--foreground);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            scroll-behavior: smooth;
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
            this.locationError = err.message || 'Location permission denied. You can still click the map to pin your address.';
        } finally {
            this.locationLoading = false;
        }
    },
    async searchMapAddress() {
        if (!this.mapSearchQuery.trim() || !window.MeramotLocationPicker) return;
        this.locationLoading = true;
        this.locationError = '';
        try {
            const found = await window.MeramotLocationPicker.forwardGeocode(this.mapSearchQuery);
            if (!found) {
                this.locationError = 'Could not find that address on OpenStreetMap. Try clicking the map or picking a Dhaka hub.';
            } else {
                this.draftLocation = found;
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
        const next = {
            address: hub.address,
            city: hub.city || 'Dhaka',
            area: hub.area,
            lat: hub.lat,
            lng: hub.lng,
            source: 'map'
        };
        this.draftLocation = next;
        if (window.MeramotLocationPicker) {
            window.MeramotLocationPicker.draftLocation = next;
            window.MeramotLocationPicker.flyToLocation(hub.lat, hub.lng, 14);
        }
    },
    confirmMapLocation() {
        if (!this.draftLocation) {
            this.locationError = 'Choose a point on the map first.';
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
                <a href="/requests" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-list-check text-teal-300"></i> Requests</a>
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
            <div class="flex items-center justify-between gap-3 md:grid md:grid-cols-[auto_minmax(280px,1fr)_auto] md:items-center md:gap-4">
                
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
                        class="group inline-flex max-w-full items-center gap-2 rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2.5 text-sm font-semibold text-[var(--foreground)] shadow-sm transition hover:border-[var(--accent-dark)] hover:bg-[var(--mint-50)]"
                    >
                        <i class="fa-solid fa-location-dot shrink-0 text-[var(--accent-dark)] text-base"></i>
                        <span class="max-w-[260px] truncate" x-text="locationLabel">Choose your location</span>
                    </button>
                </div>

                <!-- Right side icons — always visible -->
                <div class="flex shrink-0 items-center gap-2 md:gap-3">
                    
                    <!-- Notification Bell Dropdown (Desktop Only; Mobile uses Drawer Notification Tile) -->
                    <div class="relative hidden md:block" x-data="{ notifOpen: false }">
                        <button
                            type="button"
                            @click="notifOpen = !notifOpen"
                            class="relative flex h-10 w-10 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition hover:bg-[var(--mint-300)]"
                            title="Notifications"
                            aria-label="Notifications"
                        >
                            <i class="fa-regular fa-bell text-sm"></i>
                            <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-emerald-600"></span>
                        </button>

                        <div
                            x-show="notifOpen"
                            @click.away="notifOpen = false"
                            x-cloak
                            class="absolute right-0 z-40 mt-2 w-80 rounded-3xl border border-[var(--border)] bg-[var(--card)] p-4 shadow-2xl text-xs"
                        >
                            <div class="flex items-center justify-between border-b border-[var(--border)] pb-2.5 mb-2.5">
                                <span class="font-black text-sm text-[var(--foreground)]">Platform Updates</span>
                                <span class="rounded-full bg-[var(--mint-100)] px-2.5 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">Live</span>
                            </div>

                            <div class="space-y-2">
                                <a href="/requests" @click="notifOpen = false" class="block rounded-2xl bg-[var(--mint-100)]/70 p-3 hover:bg-[var(--mint-200)] transition">
                                    <div class="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                                        <i class="fa-solid fa-gavel text-amber-600"></i>
                                        <span>Quote 1 &amp; Quote 2 Lifecycle Active</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-[var(--muted-foreground)]">Track incoming workshop bids and bench diagnostics in real time.</p>
                                </a>

                                <a href="/shops?branched=true" @click="notifOpen = false" class="block rounded-2xl bg-[var(--mint-100)]/70 p-3 hover:bg-[var(--mint-200)] transition">
                                    <div class="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                                        <i class="fa-solid fa-map-location-dot text-[var(--accent-dark)]"></i>
                                        <span>Nationwide Bangladesh Directory Live</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-[var(--muted-foreground)]">Explore verified workshops &amp; multi-branch brands across Bangladesh.</p>
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
                                    <a href="/admin/financial-overview" @click="notifOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">Financial Ledger &rarr;</a>
                                @else
                                    <a href="/requests" @click="notifOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">My Requests</a>
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

                    <!-- Mobile Quick Cart Icon (Visible on Mobile Header) -->
                    <a
                        href="/cart"
                        class="relative flex h-10 w-10 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition hover:bg-[var(--mint-300)] md:hidden"
                        title="Direct-Order Cart"
                        aria-label="Direct-Order Cart"
                    >
                        <i class="fa-solid fa-cart-shopping text-sm"></i>
                        <span
                            data-cart-badge
                            class="{{ $navCartCount > 0 ? '' : 'hidden' }} absolute -top-1 -right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[var(--accent-dark)] px-1 text-[9px] font-bold text-[var(--accent-foreground)] shadow"
                        >{{ $navCartCount }}</span>
                    </a>

                    <!-- Desktop: Full Action Bar -->
                    <div class="hidden md:flex md:items-center md:gap-3">
                        
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
                                class="inline-flex items-center gap-2 rounded-full border border-[#c8dac2] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] shadow-sm transition hover:border-[var(--accent-dark)] hover:bg-[var(--mint-50)]"
                            >
                                <i class="fa-solid fa-motorcycle text-[var(--accent-dark)]"></i>
                                <span>Rider Dashboard</span>
                            </a>
                        @elseif($isVendorAuth && !request()->is('vendor/dashboard*'))
                            <a
                                href="{{ $vendorWorkspaceUrl }}"
                                class="inline-flex items-center gap-2 rounded-full border border-[#c8dac2] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] shadow-sm transition hover:border-[var(--accent-dark)] hover:bg-[var(--mint-50)]"
                            >
                                <i class="fa-solid fa-store text-[var(--accent-dark)]"></i>
                                <span>{{ $vendorWorkspaceLabel }}</span>
                            </a>
                        @elseif($isAdminAuth && !request()->is('admin*'))
                            <a
                                href="/admin"
                                class="inline-flex items-center gap-2 rounded-full border border-[#c8dac2] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--accent-dark)] shadow-sm transition hover:border-[var(--accent-dark)] hover:bg-[var(--mint-50)]"
                            >
                                <i class="fa-solid fa-shield-halved text-[var(--accent-dark)]"></i>
                                <span>Admin Console</span>
                            </a>
                        @endif

                        <!-- Sign In / Sign Up or User Dropdown -->
                        @auth
                            <div class="relative" x-data="{ userMenuOpen: false }">
                                <button
                                    @click="userMenuOpen = !userMenuOpen"
                                    type="button"
                                    class="inline-flex items-center justify-center gap-2 text-center rounded-full bg-[var(--accent-dark)] px-5 py-2 text-sm font-semibold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition"
                                >
                                    @if(!empty(auth()->user()->avatar_url))
                                        <img src="{{ auth()->user()->avatar_url }}" alt="" class="h-6 w-6 rounded-full object-cover border border-white/40">
                                    @endif
                                    <span>{{ auth()->user()->name ?? 'User' }}</span>
                                    <span class="ml-0.5 text-xs">▼</span>
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
                                            My Repair Requests
                                        </a>
                                        <a href="/requests/create" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Make Repair Request
                                        </a>
                                        <a href="/shops" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Explore shops
                                        </a>
                                        <a href="/packages/catalog" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            Service Packages
                                        </a>
                                        <a href="/cart" class="block rounded-2xl px-4 py-2.5 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                            View cart
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
                                class="inline-flex items-center justify-center text-center rounded-full bg-[var(--accent-dark)] px-6 py-2.5 text-sm font-semibold text-[var(--accent-foreground)] shadow-sm transition hover:opacity-90"
                            >
                                Sign in/Sign up
                            </a>
                        @endauth

                        <!-- Language Switcher Pill -->
                        <div class="relative" x-data="{ langMenuOpen: false, currentLang: localStorage.getItem('meramot_lang') || 'en' }">
                            <button
                                type="button"
                                @click="langMenuOpen = !langMenuOpen"
                                class="rounded-full bg-[var(--mint-200)] px-5 py-2.5 text-sm font-medium text-[var(--foreground)] transition hover:bg-[var(--mint-300)]"
                            >
                                <span x-text="currentLang === 'bn' ? 'বাংলা' : 'English'">English</span> ▼
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
                                    @click="currentLang = 'bn'; localStorage.setItem('meramot_lang', 'bn'); langMenuOpen = false; showToast({ message: 'বাংলা নির্বাচন করা হয়েছে (Full Bangla translations coming soon).', type: 'success' })"
                                    class="block w-full rounded-xl px-4 py-2 text-left text-[var(--foreground)] hover:bg-[var(--mint-50)]"
                                >
                                    বাংলা
                                </button>
                            </div>
                        </div>

                        <!-- Cart Button (Links directly to Unified Direct-Order Cart `/cart`) -->
                        <a
                            href="/cart"
                            class="relative flex h-10 w-10 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition hover:scale-105 hover:bg-[var(--mint-300)]"
                            title="Direct-Order Cart"
                            aria-label="Direct-Order Cart"
                        >
                            <i class="fa-solid fa-cart-shopping text-sm"></i>
                            <span
                                data-cart-badge
                                class="{{ $navCartCount > 0 ? '' : 'hidden' }} absolute -top-1 -right-1 flex h-4 min-w-[16px] items-center justify-center rounded-full bg-[var(--accent-dark)] px-1 text-[9px] font-bold text-[var(--accent-foreground)] shadow"
                            >{{ $navCartCount }}</span>
                        </a>
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
                <div class="flex items-end gap-3 overflow-x-auto whitespace-nowrap text-xs font-semibold text-[var(--foreground)] md:gap-6 md:text-base">
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

                <form action="/shops" method="GET" class="relative hidden w-full md:block md:flex-1 md:max-w-[640px]">
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
                        placeholder="Search shops, parts, devices, or repair types"
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
                    <span class="absolute top-1.5 right-1.5 h-2 w-2 rounded-full bg-emerald-600"></span>
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
                        My Repair Requests
                    </a>
                    <a href="/requests/create" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Make Repair Request
                    </a>
                    <a href="/packages/catalog" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Service Packages
                    </a>
                    <a href="/cart" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                        Direct-Order Cart
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
                        Click or drag the pin on the map to select an address anywhere in Bangladesh, search a landmark, or use GPS.
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
                    <form @submit.prevent="searchMapAddress()" class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <i class="fa-solid fa-magnifying-glass absolute left-3.5 top-1/2 -translate-y-1/2 text-xs text-[var(--muted-foreground)]"></i>
                            <input
                                type="text"
                                x-model="mapSearchQuery"
                                placeholder="Search road, area, or city in Bangladesh (e.g. Banani, Hatirpool, Agrabad, Sylhet)..."
                                class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] pl-9 pr-4 py-2.5 text-xs font-medium text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
                            />
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
                            OpenStreetMap + Leaflet
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
                        <p class="mt-2 text-sm font-semibold leading-6 text-[var(--foreground)]" x-text="draftLocation?.address || 'No location selected yet. Click anywhere on the map.'"></p>
                        <template x-if="draftLocation && (draftLocation.area || draftLocation.city)">
                            <p class="mt-1.5 text-xs font-semibold text-[var(--accent-dark)]" x-text="[draftLocation.area, draftLocation.city].filter(Boolean).join(', ')"></p>
                        </template>
                        <template x-if="draftLocation && typeof draftLocation.lat === 'number' && typeof draftLocation.lng === 'number'">
                            <p class="mt-1 font-mono text-[11px] text-[var(--muted-foreground)]" x-text="draftLocation.lat.toFixed(5) + ', ' + draftLocation.lng.toFixed(5)"></p>
                        </template>
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
                        <h2 id="meramot-notif-modal-title" class="font-black text-base text-[var(--foreground)]">Platform Updates</h2>
                        <span class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700">
                            <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span> Live
                        </span>
                    </div>
                </div>
                <button
                    type="button"
                    @click="notifModalOpen = false"
                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[var(--mint-100)] text-[var(--foreground)] hover:bg-[var(--mint-200)] transition"
                    aria-label="Close notifications"
                >
                    ✕
                </button>
            </div>

            <div class="space-y-2.5">
                <a href="/requests" @click="notifModalOpen = false" class="block rounded-2xl bg-[var(--mint-100)]/70 p-3.5 hover:bg-[var(--mint-200)] transition">
                    <div class="font-bold text-[var(--foreground)] flex items-center gap-2">
                        <i class="fa-solid fa-gavel text-amber-600"></i>
                        <span>Quote 1 &amp; Quote 2 Lifecycle Active</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-relaxed text-[var(--muted-foreground)]">Track incoming workshop bids and bench diagnostics in real time.</p>
                </a>

                <a href="/shops?branched=true" @click="notifModalOpen = false" class="block rounded-2xl bg-[var(--mint-100)]/70 p-3.5 hover:bg-[var(--mint-200)] transition">
                    <div class="font-bold text-[var(--foreground)] flex items-center gap-2">
                        <i class="fa-solid fa-map-location-dot text-[var(--accent-dark)]"></i>
                        <span>Nationwide Bangladesh Directory Live</span>
                    </div>
                    <p class="mt-1 text-[11px] leading-relaxed text-[var(--muted-foreground)]">Explore verified workshops &amp; multi-branch brands across Bangladesh.</p>
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
                    <a href="/admin/financial-overview" @click="notifModalOpen = false" class="text-[var(--muted-foreground)] hover:text-[var(--foreground)]">Financial Ledger &rarr;</a>
                @else
                    <a href="/requests" @click="notifModalOpen = false" class="font-bold text-[var(--accent-dark)] hover:underline">My Requests</a>
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
    <main class="flex-grow w-full min-w-0">
        @yield('content')
    </main>

    <!-- Authentic Meramot Footer -->
    <footer class="mt-16 border-t border-[var(--border)] bg-[var(--mint-100)] py-10 text-xs text-[var(--muted-foreground)]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8 mb-8">
                <div>
                    <img src="/images/meramot.svg" alt="Meramot" class="h-10 w-auto mb-3">
                    <p class="leading-relaxed">Bangladesh's premiere digital electronic repair ecosystem connecting gadget owners with vetted repair shops and doorstep riders.</p>
                </div>
                <div>
                    <h5 class="font-bold text-[var(--foreground)] mb-2 uppercase tracking-wider text-[11px]">Popular Services</h5>
                    <ul class="space-y-1.5">
                        <li><a href="/shops?q=screen" class="hover:underline">Screen Replacement</a></li>
                        <li><a href="/shops?q=battery" class="hover:underline">Battery Health Service</a></li>
                        <li><a href="/shops?q=motherboard" class="hover:underline">Logic Board Diagnostics</a></li>
                        <li><a href="/shops?q=keyboard" class="hover:underline">Keyboard & Port Repair</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="font-bold text-[var(--foreground)] mb-2 uppercase tracking-wider text-[11px]">Key Metro Hubs</h5>
                    <ul class="space-y-1.5">
                        <li><a href="/shops?area=Banani" class="hover:underline">Banani DOHS & Road 11</a></li>
                        <li><a href="/shops?area=Gulshan" class="hover:underline">Gulshan 1 & 2 Circle</a></li>
                        <li><a href="/shops?area=Dhanmondi" class="hover:underline">Dhanmondi Satmasjid</a></li>
                        <li><a href="/shops?area=Uttara" class="hover:underline">Uttara Sector 3 & 7</a></li>
                    </ul>
                </div>
                <div>
                    @if($isDevMode)
                        <h5 class="font-bold text-[var(--foreground)] mb-2 uppercase tracking-wider text-[11px]">Academic Course Work</h5>
                        <p class="mb-2">CSE 470: Software Engineering (Section 06)</p>
                        <p>Faculty: <strong>Nazmul Islam [NZU]</strong></p>
                        <p class="mt-2 text-[10px] text-emerald-800 font-bold">Strict MVC Architecture • Laravel 10 • FastAPI Microservice • PostgreSQL</p>
                    @else
                        <h5 class="font-bold text-[var(--foreground)] mb-2 uppercase tracking-wider text-[11px]">Customer &amp; Partner Hub</h5>
                        <ul class="space-y-1.5">
                            <li><a href="/requests/create" class="hover:underline">Post Repair Request</a></li>
                            <li><a href="/ai-consultant/chat" class="hover:underline">AI Fault Diagnostics</a></li>
                            <li><a href="/packages/catalog" class="hover:underline">Standard Fixed Packages</a></li>
                            @if($isVendorAuth)
                                <li><a href="{{ $vendorWorkspaceUrl }}" class="font-semibold text-[var(--accent-dark)] hover:underline">{{ $vendorWorkspaceLabel }}</a></li>
                            @elseif($isRiderAuth)
                                <li><a href="/delivery" class="font-semibold text-[var(--accent-dark)] hover:underline">Rider Dashboard</a></li>
                            @elseif($isAdminAuth)
                                <li><a href="/admin" class="font-semibold text-[var(--accent-dark)] hover:underline">Admin Console</a></li>
                            @else
                                <li><a href="/vendor/apply" class="hover:underline">Register as Partner Shop</a></li>
                                <li><a href="{{ $isCustomerAuth ? '/delivery-agent/register' : '/delivery/login' }}" class="hover:underline">{{ $isCustomerAuth ? 'Become a Delivery Partner' : 'Delivery Partner Portal' }}</a></li>
                            @endif
                        </ul>
                    @endif
                </div>
            </div>
            <div class="border-t border-[var(--border)] pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px]">
                <p>&copy; 2026 Meramot (মেরামত) Digital Marketplace. All rights reserved.</p>
                <div class="flex items-center gap-4">
                    <a href="/shops" class="hover:underline">Explore All Shops</a>
                    <a href="/packages/catalog" class="hover:underline">Service Packages</a>
                    <a href="/requests/create" class="hover:underline">Request Repair</a>
                    <a href="/ai-consultant/chat" class="hover:underline">AI Diagnostics</a>
                    <a href="{{ request()->fullUrlWithQuery(['dev' => $isDevMode ? '0' : '1']) }}" class="text-[10px] text-[var(--muted-foreground)] opacity-40 hover:opacity-100 transition" title="Press Ctrl+Shift+D or click to toggle academic defense mode">
                        <i class="fa-solid fa-code"></i> {{ $isDevMode ? 'Dev Mode (ON)' : 'Dev' }}
                    </a>
                </div>
            </div>
        </div>
    </footer>

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
        })();
    </script>
</body>
</html>

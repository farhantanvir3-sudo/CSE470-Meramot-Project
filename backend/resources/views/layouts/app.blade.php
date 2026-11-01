<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Meramot (মেরামত) — Digital Electronic Repair Marketplace')</title>
    
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

        /* Academic defense mode toggle */
        @php
            if (request()->has('dev')) {
                session(['dev_mode' => request()->boolean('dev')]);
            }
            $isDevMode = session('dev_mode', false) || request()->boolean('dev');
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
    locationLabel: 'Banani, Dhaka',
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
    init() {
        if (this.darkMode) {
            document.documentElement.classList.add('dark');
        }
    }
}">

    <!-- Top Academic Defense / Role Switcher Ribbon (Dev Mode Only) -->
    @if($isDevMode)
    <div class="bg-accent-dark text-white text-[11px] py-1.5 px-4 shadow-sm border-b border-emerald-950">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="bg-emerald-500/30 text-emerald-200 border border-emerald-400/40 px-2 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">CSE 470 Group 6 • Dev Mode</span>
                <span class="opacity-80">Faculty: Nazmul Islam [NZU]</span>
            </div>
            <div class="flex items-center gap-3">
                <span class="opacity-70">Switch Mode:</span>
                <a href="/shops/explore" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-store text-emerald-400"></i> Customer</a>
                <span class="opacity-40">|</span>
                <a href="/vendor/bidding-board" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-gavel text-amber-300"></i> Vendor Portal</a>
                <span class="opacity-40">|</span>
                <a href="/delivery/jobs-queue" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-motorcycle text-sky-300"></i> Rider Board</a>
                <span class="opacity-40">|</span>
                <a href="/admin/financial-overview" class="hover:underline flex items-center gap-1"><i class="fa-solid fa-shield-halved text-rose-300"></i> Admin Console</a>
                <span class="opacity-40">|</span>
                <a href="?dev=0" class="bg-rose-600/80 hover:bg-rose-600 text-white px-2 py-0.5 rounded text-[10px] font-semibold transition"><i class="fa-solid fa-xmark mr-1"></i> Exit Dev Mode</a>
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

                <!-- Brand Logo (Scaled with exact desktop and mobile dimensions) -->
                <div class="flex flex-1 justify-center md:justify-start">
                    <a href="/" class="inline-flex items-center">
                        <img
                            src="/images/meramot.svg"
                            alt="Meramot Logo"
                            class="h-[4.62rem] w-auto object-contain md:h-[5.1975rem] lg:h-[6.3525rem]"
                        />
                    </a>
                </div>

                <!-- Desktop-only: Location Button -->
                <div class="hidden md:flex md:justify-center">
                    <button
                        type="button"
                        @click="locationModalOpen = true"
                        class="inline-flex items-center gap-2 rounded-full bg-[var(--mint-200)] px-5 py-2.5 text-sm font-medium text-[var(--foreground)] transition hover:bg-[var(--mint-300)] shadow-sm"
                    >
                        <i class="fa-solid fa-location-dot text-[var(--accent-dark)] text-base"></i>
                        <span x-text="locationLabel">Banani, Dhaka</span>
                    </button>
                </div>

                <!-- Right side icons — always visible -->
                <div class="flex shrink-0 items-center gap-2 md:gap-3">
                    
                    <!-- Notification Bell -->
                    <button class="relative flex h-10 w-10 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)] transition hover:bg-[var(--mint-300)]" title="Notifications">
                        <i class="fa-regular fa-bell text-sm"></i>
                        <span class="absolute top-2 right-2 h-2 w-2 rounded-full bg-emerald-600"></span>
                    </button>

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

                        <!-- Sign In / Sign Up or User Dropdown -->
                        @auth
                            <div class="relative" x-data="{ userMenuOpen: false }">
                                <button
                                    @click="userMenuOpen = !userMenuOpen"
                                    type="button"
                                    class="inline-flex items-center justify-center text-center rounded-full bg-[var(--accent-dark)] px-6 py-2.5 text-sm font-semibold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition"
                                >
                                    <span>{{ auth()->user()->name ?? 'User' }}</span>
                                    <span class="ml-1 text-xs">▼</span>
                                </button>

                                <div
                                    x-show="userMenuOpen"
                                    @click.away="userMenuOpen = false"
                                    x-cloak
                                    class="absolute right-0 z-30 mt-2 w-56 rounded-3xl border border-[var(--border)] bg-[var(--card)] p-3 shadow-xl text-sm"
                                >
                                    <a href="/shops" class="block rounded-2xl px-4 py-3 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                        Explore shops
                                    </a>
                                    <a href="/requests/create" class="block rounded-2xl px-4 py-3 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                        Make Repair Request
                                    </a>
                                    <a href="/packages/catalog" class="block rounded-2xl px-4 py-3 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                        View cart
                                    </a>
                                    <a href="/ai-consultant/chat" class="block rounded-2xl px-4 py-3 text-[var(--foreground)] transition hover:bg-[var(--mint-50)]">
                                        AI help chat
                                    </a>
                                    <div class="my-1 border-t border-[var(--border)]"></div>
                                    <button
                                        @click="userMenuOpen = false; showLogoutConfirm = true"
                                        class="block w-full rounded-2xl px-4 py-3 text-left font-medium text-red-700 transition hover:bg-red-50"
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
                        <div class="relative" x-data="{ langMenuOpen: false, currentLang: 'en' }">
                            <button
                                @click="langMenuOpen = !langMenuOpen"
                                class="rounded-full bg-[var(--mint-200)] px-5 py-2.5 text-sm font-medium text-[var(--foreground)] transition hover:bg-[var(--mint-300)]"
                            >
                                <span x-text="currentLang === 'bn' ? 'বাংলা' : 'English'">English</span> ▼
                            </button>

                            <div
                                x-show="langMenuOpen"
                                @click.away="langMenuOpen = false"
                                x-cloak
                                class="absolute right-0 z-30 mt-2 w-40 rounded-2xl border border-[var(--border)] bg-[var(--card)] p-2 shadow-lg text-sm"
                            >
                                <button
                                    @click="currentLang = 'en'; langMenuOpen = false"
                                    class="block w-full rounded-xl px-4 py-2 text-left text-[var(--foreground)] hover:bg-[var(--mint-50)]"
                                >
                                    English
                                </button>
                                <button
                                    @click="currentLang = 'bn'; langMenuOpen = false"
                                    class="block w-full rounded-xl px-4 py-2 text-left text-[var(--foreground)] hover:bg-[var(--mint-50)]"
                                >
                                    বাংলা
                                </button>
                            </div>
                        </div>

                        <!-- Cart Button (Matching cart.svg h-[45px] w-[45px]) -->
                        <a
                            href="/packages/catalog"
                            class="flex items-center justify-center rounded-full bg-[var(--mint-100)] p-2 transition hover:scale-105 hover:bg-[var(--mint-300)]"
                            title="Cart & Packages"
                        >
                            <img
                                src="/images/cart.svg"
                                alt="Cart"
                                class="h-[45px] w-[45px] object-contain"
                            />
                        </a>
                    </div>
                </div>
            </div>

            <!-- ── Row 2: Mobile Location Button & Compact Search (Mobile Only) ── -->
            <div class="mt-4 md:mt-0 md:hidden">
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        @click="locationModalOpen = true"
                        class="shrink-0 p-1.5 text-[var(--accent-dark)]"
                        aria-label="Change location"
                    >
                        <i class="fa-solid fa-location-dot text-2xl"></i>
                    </button>
                    <form action="/shops" method="GET" class="flex-1">
                        <input
                            type="text"
                            name="q"
                            value="{{ request('q') }}"
                            placeholder="Search shops, parts..."
                            class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] px-4 py-2.5 text-[0.9rem] text-[var(--foreground)] shadow-sm outline-none placeholder:text-[var(--muted-foreground)] focus:border-[var(--accent-dark)]"
                        />
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
                </div>

                <form action="/shops" method="GET" class="hidden w-full md:block md:flex-1 md:max-w-[640px]">
                    @if(request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    <input
                        type="text"
                        name="q"
                        value="{{ request('q') }}"
                        placeholder="Search shops, parts, devices, or repair types"
                        class="w-full rounded-xl border-2 border-[var(--border)] bg-[var(--mint-300)] px-5 py-3 text-sm text-[var(--foreground)] shadow-sm outline-none placeholder:text-[var(--muted-foreground)] focus:border-[var(--accent-dark)] transition"
                    />
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
                    @click="toggleDark()"
                    class="flex h-9 w-9 items-center justify-center rounded-full bg-[var(--mint-200)] text-[var(--foreground)]"
                >
                    <i :class="darkMode ? 'fa-solid fa-sun text-amber-400' : 'fa-solid fa-moon'"></i>
                </button>
                <a href="/packages/catalog" @click="mobileMenuOpen = false" class="relative">
                    <img src="/images/cart.svg" alt="Cart" class="h-9 w-9">
                </a>
            </div>

            <nav class="space-y-1">
                <a href="/shops" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                    Explore all shops
                </a>
                <a href="/requests/create" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                    Make Repair Request
                </a>
                <a href="/packages/catalog" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                    Service Packages
                </a>
                <a href="/ai-consultant/chat" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm text-[var(--foreground)] hover:bg-[var(--mint-50)]">
                    AI Diagnostic Help
                </a>
                <a href="/vendor/bidding-board" @click="mobileMenuOpen = false" class="block rounded-xl px-4 py-3 text-sm font-bold text-emerald-800 hover:bg-[var(--mint-50)]">
                    Vendor Reverse Bidding Board
                </a>
                <div class="my-2 border-t border-[var(--border)]"></div>
                @auth
                    <button
                        @click="mobileMenuOpen = false; showLogoutConfirm = true"
                        class="block w-full rounded-xl px-4 py-3 text-left text-sm font-medium text-red-700 hover:bg-red-50"
                    >
                        Log out
                    </button>
                @else
                    <a href="/login" @click="mobileMenuOpen = false" class="block rounded-xl bg-[var(--accent-dark)] px-4 py-3 text-center text-sm font-semibold text-white">
                        Sign In / Sign Up
                    </a>
                @endauth
            </nav>
        </aside>
    </div>

    <!-- ── Location Picker Modal ── -->
    <div x-show="locationModalOpen" x-cloak class="fixed inset-0 z-[120] flex items-center justify-center p-4">
        <div class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="locationModalOpen = false"></div>
        <div class="relative z-10 w-full max-w-md rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-2xl">
            <h3 class="text-xl font-bold text-[var(--foreground)] mb-3 flex items-center gap-2">
                <i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i>
                <span>Choose Your Dhaka Hub</span>
            </h3>
            <p class="text-xs text-[var(--muted-foreground)] mb-4">Shops and estimated courier pickup times will calibrate to your selected neighborhood.</p>
            <div class="space-y-2 text-sm">
                @foreach(['Banani, Dhaka', 'Gulshan, Dhaka', 'Dhanmondi, Dhaka', 'Mirpur, Dhaka', 'Uttara, Dhaka', 'Bashundhara, Dhaka', 'Mohammadpur, Dhaka', 'All Dhaka'] as $loc)
                    <button
                        type="button"
                        @click="locationLabel = '{{ $loc }}'; locationModalOpen = false; window.location.href = '/shops?area=' + ('{{ $loc }}' === 'All Dhaka' ? 'All' : '{{ explode(',', $loc)[0] }}')"
                        class="flex w-full items-center justify-between rounded-xl px-4 py-2.5 text-left transition hover:bg-[var(--mint-100)]"
                    >
                        <span class="font-medium text-[var(--foreground)]">{{ $loc }}</span>
                        <i class="fa-solid fa-chevron-right text-xs text-[var(--muted-foreground)]"></i>
                    </button>
                @endforeach
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

    <!-- Flash Alert System -->
    @if(session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
            <div class="p-3 text-xs text-emerald-900 rounded-2xl bg-[var(--mint-200)] border border-[var(--border)] flex items-center justify-between shadow-sm">
                <span><i class="fa-solid fa-circle-check mr-2 text-emerald-700"></i> {{ session('success') }}</span>
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
                        <h5 class="font-bold text-[var(--foreground)] mb-2 uppercase tracking-wider text-[11px]">Customer Support</h5>
                        <ul class="space-y-1.5">
                            <li><a href="/requests/create" class="hover:underline">Post Repair Request</a></li>
                            <li><a href="/ai-consultant/chat" class="hover:underline">AI Fault Diagnostics</a></li>
                            <li><a href="/packages/catalog" class="hover:underline">Standard Fixed Packages</a></li>
                            <li><a href="/vendor/onboarding" class="hover:underline">Register as Partner Shop</a></li>
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
                    <a href="?dev={{ $isDevMode ? '0' : '1' }}" class="text-[10px] text-[var(--muted-foreground)] opacity-40 hover:opacity-100 transition" title="Press Ctrl+Shift+D or click to toggle academic defense mode">
                        <i class="fa-solid fa-code"></i> {{ $isDevMode ? 'Dev Mode (ON)' : 'Dev' }}
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <script>
        // Press Ctrl + Shift + D to toggle Developer / Academic Defense Mode
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.shiftKey && (e.key === 'D' || e.key === 'd')) {
                e.preventDefault();
                window.location.href = window.location.pathname + '?dev={{ $isDevMode ? "0" : "1" }}';
            }
        });
    </script>
</body>
</html>

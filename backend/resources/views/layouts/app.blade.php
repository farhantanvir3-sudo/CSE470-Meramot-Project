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
            theme: {
                extend: {
                    colors: {
                        'm-bg': '#E4FCD5',
                        'm-fg': '#203126',
                        'm-card': '#c8e7c5',
                        'm-border': '#556c527a',
                        'm-muted': '#5e6d64',
                        'mint-50': '#b5d4ad',
                        'mint-100': '#e4f3df',
                        'mint-200': '#c4ddbd',
                        'mint-300': '#b8d9b5',
                        'mint-500': '#7eae77',
                        'mint-700': '#436440',
                        'accent-dark': '#244229',
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    
    <style>
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
        body {
            background-color: var(--background);
            color: var(--foreground);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            overflow-x: hidden;
        }
        [x-cloak] { display: none !important; }
        .meramot-card {
            background-color: var(--card);
            border: 1px solid var(--border);
            border-radius: 1.5rem;
        }
        .meramot-input {
            background-color: var(--mint-300);
            border: 2px solid var(--border);
            color: var(--foreground);
            transition: all 0.2s;
        }
        .meramot-input:focus {
            background-color: var(--mint-100);
            border-color: var(--accent-dark);
        }
        .meramot-pill {
            background-color: var(--mint-100);
            color: var(--accent-dark);
            border: 1px solid var(--border);
        }
    </style>
</head>
<body class="min-h-screen flex flex-col antialiased">

    <!-- Top Dev & Role Switcher Ribbon -->
    <div class="bg-accent-dark text-white text-[11px] py-1.5 px-4 shadow-sm">
        <div class="max-w-7xl mx-auto flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                <span class="bg-white/20 px-2 py-0.5 rounded font-bold uppercase tracking-wider text-[10px]">CSE 470 Group 6</span>
                <span class="opacity-80">BRAC University • Faculty Nazmul Islam [NZU]</span>
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
            </div>
        </div>
    </div>

    <!-- Main Meramot Header (Faithful to Original Design) -->
    <header class="w-full border-b border-[var(--border)] bg-[var(--mint-100)] sticky top-0 z-50 backdrop-blur-md">
        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-4 py-3 md:px-6">
            
            <!-- Header Top Row -->
            <div class="grid grid-cols-[auto_1fr] gap-4 md:grid-cols-[auto_minmax(280px,1fr)_auto] md:items-center">
                
                <!-- Logo -->
                <a href="/" class="inline-flex items-center gap-3 transition hover:opacity-90">
                    <img src="/images/meramot.svg" alt="Meramot Logo" class="h-12 w-auto md:h-14 object-contain">
                </a>

                <!-- Location Selector -->
                <div class="flex justify-end md:justify-center" x-data="{ open: false, location: 'Banani, Dhaka' }">
                    <div class="relative">
                        <button 
                            @click="open = !open" 
                            type="button"
                            class="inline-flex items-center gap-2 rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-semibold text-[var(--foreground)] transition hover:bg-[var(--mint-200)] shadow-sm">
                            <i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i>
                            <span x-text="location">Banani, Dhaka</span>
                            <i class="fa-solid fa-chevron-down text-[10px] opacity-70"></i>
                        </button>
                        
                        <!-- Location Dropdown -->
                        <div 
                            x-show="open" 
                            @click.away="open = false" 
                            x-cloak
                            class="absolute left-1/2 -translate-x-1/2 mt-2 w-48 rounded-2xl border border-[var(--border)] bg-[var(--card)] p-2 shadow-xl z-50 text-xs">
                            <div class="font-bold px-3 py-1.5 text-[var(--muted-foreground)] uppercase text-[10px]">Select Area</div>
                            <template x-for="loc in ['All Dhaka', 'Banani, Dhaka', 'Gulshan, Dhaka', 'Dhanmondi, Dhaka', 'Mirpur, Dhaka', 'Uttara, Dhaka', 'Bashundhara, Dhaka']">
                                <a 
                                    :href="'/shops?area=' + (loc === 'All Dhaka' ? 'All' : loc.split(',')[0])" 
                                    @click="location = loc; open = false"
                                    class="block rounded-xl px-3 py-2 text-[var(--foreground)] hover:bg-[var(--mint-50)] transition"
                                    x-text="loc">
                                </a>
                            </template>
                        </div>
                    </div>
                </div>

                <!-- Right Action Icons & Auth -->
                <div class="col-span-2 flex flex-wrap items-center justify-end gap-3 md:col-span-1">
                    
                    <!-- AI Diagnostic Shortcut Button -->
                    <a href="/ai-consultant/chat" class="inline-flex items-center gap-1.5 rounded-full border border-[var(--border)] bg-[var(--card)] px-3.5 py-2 text-xs font-bold text-[var(--accent-dark)] transition hover:bg-[var(--mint-200)] shadow-sm">
                        <i class="fa-solid fa-wand-magic-sparkles text-amber-600"></i>
                        <span class="hidden sm:inline">AI Help</span>
                    </a>

                    <!-- Make Request CTA -->
                    <a href="/requests/create" class="inline-flex items-center gap-1 rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-white shadow-sm transition hover:opacity-90">
                        <i class="fa-solid fa-plus text-[10px]"></i>
                        <span>New Request</span>
                    </a>

                    <!-- Cart Icon Button -->
                    <a href="/packages/catalog" class="flex items-center justify-center rounded-full bg-[var(--card)] border border-[var(--border)] p-2 transition hover:scale-105 hover:bg-[var(--mint-200)]" title="Service Packages & Cart">
                        <img src="/images/cart.svg" alt="Cart" class="h-6 w-6">
                    </a>

                    <!-- Profile / Login -->
                    <div class="relative" x-data="{ menuOpen: false }">
                        <button 
                            @click="menuOpen = !menuOpen"
                            class="inline-flex items-center gap-1.5 rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-semibold text-white shadow-sm transition hover:opacity-90">
                            <i class="fa-solid fa-circle-user text-sm"></i>
                            <span>Demo User</span>
                            <i class="fa-solid fa-chevron-down text-[9px] opacity-80"></i>
                        </button>
                        
                        <div 
                            x-show="menuOpen" 
                            @click.away="menuOpen = false" 
                            x-cloak
                            class="absolute right-0 mt-2 w-56 rounded-3xl border border-[var(--border)] bg-[var(--card)] p-2 shadow-2xl z-50 text-xs">
                            <div class="px-4 py-2 border-b border-[var(--border)]">
                                <div class="font-bold text-[var(--foreground)]">Farhan Tanvir</div>
                                <div class="text-[10px] text-[var(--muted-foreground)]">customer@meramot.demo</div>
                            </div>
                            <a href="/shops/explore" class="block rounded-2xl px-4 py-2.5 hover:bg-[var(--mint-50)] text-[var(--foreground)]">Explore Shops</a>
                            <a href="/requests/create" class="block rounded-2xl px-4 py-2.5 hover:bg-[var(--mint-50)] text-[var(--foreground)]">Create Repair Request</a>
                            <a href="/packages/catalog" class="block rounded-2xl px-4 py-2.5 hover:bg-[var(--mint-50)] text-[var(--foreground)]">Fixed Service Packages</a>
                            <a href="/ai-consultant/chat" class="block rounded-2xl px-4 py-2.5 hover:bg-[var(--mint-50)] text-[var(--foreground)]">AI Device Diagnostics</a>
                            <div class="border-t border-[var(--border)] my-1"></div>
                            <a href="/vendor/bidding-board" class="block rounded-2xl px-4 py-2.5 hover:bg-[var(--mint-50)] text-emerald-800 font-bold">Go to Vendor Portal</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Header Bottom Row: Category Tabs & Search Bar -->
            <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between pt-1">
                
                <!-- Category Tabs -->
                @php
                    $currCat = request('category', '');
                @endphp
                <div class="flex items-end gap-5 overflow-x-auto whitespace-nowrap text-xs font-bold text-[var(--foreground)]">
                    <a href="/shops" class="inline-flex border-b-[3px] pb-1 transition {{ empty($currCat) ? 'border-[var(--accent-dark)] text-[var(--accent-dark)]' : 'border-transparent hover:border-[var(--accent-dark)]/40' }}">
                        All Repairs
                    </a>
                    <a href="/shops?category=COURIER_PICKUP" class="inline-flex border-b-[3px] pb-1 transition {{ $currCat === 'COURIER_PICKUP' ? 'border-[var(--accent-dark)] text-[var(--accent-dark)]' : 'border-transparent hover:border-[var(--accent-dark)]/40' }}">
                        Courier Pickup
                    </a>
                    <a href="/shops?category=IN_SHOP_REPAIR" class="inline-flex border-b-[3px] pb-1 transition {{ $currCat === 'IN_SHOP_REPAIR' ? 'border-[var(--accent-dark)] text-[var(--accent-dark)]' : 'border-transparent hover:border-[var(--accent-dark)]/40' }}">
                        In-shop Repair
                    </a>
                    <a href="/shops?category=SPARE_PARTS" class="inline-flex border-b-[3px] pb-1 transition {{ $currCat === 'SPARE_PARTS' ? 'border-[var(--accent-dark)] text-[var(--accent-dark)]' : 'border-transparent hover:border-[var(--accent-dark)]/40' }}">
                        Spare Parts
                    </a>
                    <a href="/packages/catalog" class="inline-flex border-b-[3px] pb-1 transition text-emerald-900 border-transparent hover:border-emerald-800">
                        <i class="fa-solid fa-cube mr-1 text-[11px]"></i> Packages
                    </a>
                </div>

                <!-- Live Search Bar -->
                <form action="/shops" method="GET" class="relative w-full md:w-[480px]">
                    @if(request('category'))
                        <input type="hidden" name="category" value="{{ request('category') }}">
                    @endif
                    <input 
                        type="text" 
                        name="q" 
                        value="{{ request('q', '') }}" 
                        placeholder="Search shops, parts, devices, or repair types..." 
                        class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] pl-4 pr-11 py-2 text-xs text-[var(--foreground)] outline-none placeholder:text-[var(--muted-foreground)] shadow-sm transition">
                    <button type="submit" class="absolute right-3 top-1/2 -translate-y-1/2 text-[var(--accent-dark)] hover:opacity-80 p-1">
                        <i class="fa-solid fa-magnifying-glass text-sm"></i>
                    </button>
                </form>
            </div>
        </div>
    </header>

    <!-- Flash Alert System -->
    @if(session('success'))
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
            <div class="p-3 text-xs text-emerald-900 rounded-2xl bg-[var(--mint-200)] border border-[var(--border)] flex items-center justify-between shadow-sm">
                <span><i class="fa-solid fa-circle-check mr-2 text-emerald-700"></i> {{ session('success') }}</span>
            </div>
        </div>
    @endif

    <!-- Main Dynamic Content Slot -->
    <main class="flex-grow">
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
                    <h5 class="font-bold text-[var(--foreground)] mb-2 uppercase tracking-wider text-[11px]">Academic Course Work</h5>
                    <p class="mb-2">CSE 470: Software Engineering (Section 06)</p>
                    <p>Faculty: <strong>Nazmul Islam [NZU]</strong></p>
                    <p class="mt-2 text-[10px] text-emerald-800 font-bold">Strict MVC Architecture • Laravel 10 • FastAPI Microservice • PostgreSQL</p>
                </div>
            </div>
            <div class="border-t border-[var(--border)] pt-6 flex flex-col sm:flex-row items-center justify-between gap-3 text-[11px]">
                <p>&copy; 2026 Meramot (মেরামত) Digital Marketplace. All rights reserved.</p>
                <div class="flex gap-4">
                    <a href="/shops" class="hover:underline">Explore All Shops</a>
                    <a href="/packages/catalog" class="hover:underline">Service Packages</a>
                    <a href="/requests/create" class="hover:underline">Request Repair</a>
                    <a href="/ai-consultant/chat" class="hover:underline">AI Diagnostics</a>
                </div>
            </div>
        </div>
    </footer>

</body>
</html>

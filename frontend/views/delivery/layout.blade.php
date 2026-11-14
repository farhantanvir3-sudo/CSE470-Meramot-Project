<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Delivery Workspace | Meramot')</title>

    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.9.4/leaflet.min.js"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        :root {
            --background: #E4FCD5;
            --foreground: #163625;
            --card: #ffffff;
            --border: #d9e5d5;
            --mint-50: #f3faf0;
            --mint-100: #eef4ea;
            --muted-foreground: #4c6657;
            --accent-dark: #163625;
        }
        html, body {
            background-color: var(--background);
            color: var(--foreground);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            min-height: 100vh;
        }
        [x-cloak] { display: none !important; }
        .leaflet-container img,
        .leaflet-tile,
        .leaflet-marker-icon,
        .leaflet-marker-shadow {
            max-width: none !important;
            max-height: none !important;
        }
    </style>
</head>
<body class="min-h-screen flex flex-col md:flex-row bg-[#E4FCD5] text-[#163625] antialiased" x-data="{ sidebarOpen: false }">

    <!-- Delivery Workspace Sidebar -->
    @php
        $currentPath = '/' . trim(request()->path(), '/');
        $authRider = auth()->user()?->deliveryAgent;
        $isApprovedPartner = ($authRider?->registration_status ?? 'APPROVED') === 'APPROVED';
        $openPoolCountBadge = \App\Models\DeliveryJob::whereNull('delivery_agent_id')
            ->whereIn('status', ['PENDING', 'unassigned', 'UNASSIGNED'])
            ->where(function ($query) {
                $query->whereDoesntHave('repairRequest')
                    ->orWhereHas('repairRequest', fn ($rq) => $rq->whereNotIn('status', ['CANCELLED', 'REJECTED']));
            })
            ->count();
        $navItems = [
            ['name' => 'Dashboard', 'icon' => 'fa-house', 'path' => '/delivery', 'active' => $currentPath === '/delivery' || str_starts_with($currentPath, '/delivery/order'), 'badge' => 0],
            ['name' => 'Job Queue', 'icon' => 'fa-boxes-packing', 'path' => '/delivery/jobs-queue', 'active' => $currentPath === '/delivery/jobs-queue', 'badge' => $openPoolCountBadge],
            ['name' => 'Live Map', 'icon' => 'fa-map-location-dot', 'path' => '/delivery/map', 'active' => $currentPath === '/delivery/map', 'badge' => 0],
            ['name' => 'Earnings', 'icon' => 'fa-wallet', 'path' => '/delivery/earnings', 'active' => $currentPath === '/delivery/earnings', 'badge' => 0],
            ['name' => 'Profile', 'icon' => 'fa-user-shield', 'path' => '/delivery/profile', 'active' => $currentPath === '/delivery/profile', 'badge' => 0],
        ];
    @endphp

    <!-- Sticky Mobile Header Bar -->
    <header class="sticky top-0 z-30 flex items-center justify-between border-b border-[#d9e5d5] bg-white/95 px-4 py-3 shadow-sm backdrop-blur md:hidden">
        <div class="flex items-center gap-3">
            <button
                type="button"
                @click="sidebarOpen = !sidebarOpen"
                aria-label="Toggle Delivery Workspace Menu"
                class="flex h-10 w-10 items-center justify-center rounded-xl border border-[#d9e5d5] bg-[#f8fbf6] text-[#163625] shadow-sm transition hover:bg-[#eef4ea]"
            >
                <i class="fa-solid" :class="sidebarOpen ? 'fa-xmark' : 'fa-bars'"></i>
            </button>
            <a href="/delivery" class="flex items-center gap-2">
                <img src="/images/meramot.svg" alt="Meramot" class="h-8 w-auto object-contain">
                <span class="text-xs font-extrabold text-[#163625]">Delivery Workspace</span>
            </a>
        </div>
        @auth
            <span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2.5 py-1 text-[10px] font-bold text-emerald-800 border border-emerald-200">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                {{ $authRider?->status ?? 'AVAILABLE' }}
            </span>
        @endauth
    </header>

    <aside
        class="fixed left-0 top-0 z-40 flex h-screen w-[85vw] max-w-[290px] flex-col border-r border-[#d9e5d5] bg-white transition-transform duration-300 md:sticky md:top-0 md:w-[216px] lg:w-[256px] shrink-0 md:max-w-none md:translate-x-0"
        :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
    >
        <div class="border-b border-[#d9e5d5] px-6 py-6 flex items-center justify-between gap-3">
            <a href="/delivery" class="block">
                <img src="/images/meramot.svg" alt="Meramot" class="h-11 w-auto object-contain">
                <p class="mt-1 text-xs font-semibold text-[#163625]/65">Delivery Workspace</p>
            </a>
        </div>

        @auth
            <div class="mx-4 mt-4 rounded-2xl border border-[#d9e5d5] bg-[#f8fbf6] px-3.5 py-3">
                <p class="truncate text-xs font-bold text-[#163625]">{{ auth()->user()->name }}</p>
                <p class="truncate font-mono text-[11px] text-[#163625]/70">{{ $authRider?->company_email ?: auth()->user()->email }}</p>
                <span class="mt-1.5 inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold text-emerald-800 border border-emerald-200">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-600"></span>
                    {{ $authRider?->status ?? 'AVAILABLE' }}
                </span>
            </div>
        @endauth

        <nav class="flex-1 space-y-1.5 overflow-y-auto px-3 lg:px-4 py-5">
            @foreach($navItems as $item)
                <a
                    href="{{ $item['path'] }}"
                    @click="sidebarOpen = false"
                    class="flex items-center justify-between gap-2 rounded-xl px-3 py-2.5 lg:px-3.5 lg:py-3 text-sm font-semibold transition {{ $item['active'] ? 'border border-[#d9e5d5] bg-[#eef4ea] text-[#163625]' : 'text-[#163625]/75 hover:bg-[#f3faf0] hover:text-[#163625]' }}"
                >
                    <span class="flex items-center gap-2.5 min-w-0">
                        <i class="fa-solid {{ $item['icon'] }} w-5 shrink-0 text-center {{ $item['active'] ? 'text-[#163625]' : 'text-[#163625]/65' }}"></i>
                        <span class="truncate">{{ $item['name'] }}</span>
                    </span>
                    @if(!empty($item['badge']))
                        <span class="shrink-0 rounded-full bg-[#163625] px-2 py-0.5 text-[10px] font-bold text-[#E4FCD5]">
                            {{ $item['badge'] }}
                        </span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="border-t border-[#d9e5d5] p-4 space-y-2">
            <a
                href="/"
                class="flex items-center gap-3 rounded-xl px-3.5 py-2.5 text-xs font-semibold text-[#163625]/70 transition hover:bg-[#f3faf0] hover:text-[#163625]"
            >
                <i class="fa-solid fa-store w-5 text-center"></i>
                <span>Marketplace Home</span>
            </a>

            <form action="/delivery/logout" method="POST">
                @csrf
                <button
                    type="submit"
                    class="flex w-full items-center gap-3 rounded-xl px-3.5 py-3 text-sm font-semibold text-red-700/85 transition hover:bg-red-50 hover:text-red-700"
                >
                    <i class="fa-solid fa-right-from-bracket w-5 text-center"></i>
                    <span>Sign Out</span>
                </button>
            </form>
        </div>
    </aside>

    <!-- Mobile Backdrop -->
    <div
        x-show="sidebarOpen"
        x-cloak
        @click="sidebarOpen = false"
        class="fixed inset-0 z-30 bg-black/30 md:hidden"
    ></div>

    <!-- Main Workspace Content -->
    <main class="min-h-screen flex-1 min-w-0">
        <div class="mx-auto w-full max-w-6xl px-4 py-5 pb-10 sm:px-5 md:px-5 lg:px-8 lg:py-8 space-y-5">
            @if(session('success'))
                <div class="rounded-2xl border border-emerald-300 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-900 flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-check text-emerald-700"></i>
                    <span>{{ session('success') }}</span>
                </div>
            @endif

            @if(session('warning'))
                <div class="rounded-2xl border border-amber-300 bg-amber-50 px-4 py-3 text-xs font-bold text-amber-950 flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-triangle-exclamation text-amber-700"></i>
                    <span>{{ session('warning') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-900 flex items-center gap-2 shadow-sm">
                    <i class="fa-solid fa-circle-exclamation text-rose-700"></i>
                    <span>{{ session('error') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="rounded-2xl border border-rose-300 bg-rose-50 px-4 py-3 text-xs text-rose-900 space-y-1 shadow-sm">
                    @foreach($errors->all() as $err)
                        <div class="flex items-center gap-2 font-bold">
                            <i class="fa-solid fa-circle-exclamation text-rose-700"></i>
                            <span>{{ $err }}</span>
                        </div>
                    @endforeach
                </div>
            @endif

            @yield('content')
        </div>
    </main>

</body>
</html>

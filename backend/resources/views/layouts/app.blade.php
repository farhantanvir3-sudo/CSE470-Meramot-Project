<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Meramot — Digital Electronic Repair Marketplace')</title>
    <!-- Tailwind CSS CDN for instant prototype styling -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        [x-cloak] { display: none !important; }
    </style>
</head>
<body class="bg-slate-50 text-slate-800 min-h-screen flex flex-col font-sans antialiased">

    <!-- Global App Header -->
    <header class="bg-white border-b border-slate-200 sticky top-0 z-50 shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between h-16 items-center">
                <!-- Branding -->
                <div class="flex items-center gap-3">
                    <a href="/" class="flex items-center gap-2">
                        <img src="/images/meramot.svg" alt="Meramot Logo" class="h-8 w-auto">
                        <span class="text-2xl font-black text-teal-700 tracking-tight">মেরামত</span>
                    </a>
                    <span class="text-xs font-semibold px-2 py-0.5 rounded-full bg-teal-100 text-teal-800 border border-teal-200">
                        CSE470
                    </span>
                </div>

                <!-- Role Portals Switcher -->
                <nav class="flex items-center gap-4 text-xs font-medium">
                    <a href="/shops/explore" class="px-3 py-1.5 rounded-lg hover:bg-slate-100 transition text-slate-700">
                        <i class="fa-solid fa-store mr-1 text-teal-600"></i> Customer
                    </a>
                    <a href="/vendor/bidding-board" class="px-3 py-1.5 rounded-lg hover:bg-slate-100 transition text-slate-700">
                        <i class="fa-solid fa-gavel mr-1 text-indigo-600"></i> Vendor
                    </a>
                    <a href="/delivery/jobs-queue" class="px-3 py-1.5 rounded-lg hover:bg-slate-100 transition text-slate-700">
                        <i class="fa-solid fa-motorcycle mr-1 text-amber-600"></i> Rider
                    </a>
                    <a href="/admin/financial-overview" class="px-3 py-1.5 rounded-lg hover:bg-slate-100 transition text-slate-700">
                        <i class="fa-solid fa-shield-halved mr-1 text-rose-600"></i> Admin
                    </a>
                </nav>
            </div>
        </div>
    </header>

    <!-- Flash Notifications -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 mt-4 w-full">
        @if(session('success'))
            <div class="p-4 mb-4 text-sm text-emerald-800 rounded-lg bg-emerald-50 border border-emerald-200 flex items-center justify-between">
                <span><i class="fa-solid fa-circle-check mr-2"></i> {{ session('success') }}</span>
            </div>
        @endif
        @if(session('warning'))
            <div class="p-4 mb-4 text-sm text-amber-800 rounded-lg bg-amber-50 border border-amber-200 flex items-center justify-between">
                <span><i class="fa-solid fa-triangle-exclamation mr-2"></i> {{ session('warning') }}</span>
            </div>
        @endif
    </div>

    <!-- Main Content Slot -->
    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6 w-full">
        @yield('content')
    </main>

    <!-- Footer -->
    <footer class="bg-white border-t border-slate-200 py-6 text-center text-xs text-slate-500">
        <p>Meramot Digital Marketplace &copy; 2026 BRAC University CSE 470 (Section 06 - Group 06)</p>
    </footer>

</body>
</html>

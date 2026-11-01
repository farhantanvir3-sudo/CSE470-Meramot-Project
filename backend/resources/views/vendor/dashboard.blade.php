@extends('layouts.app')

@section('title', 'Vendor Control Center & Bidding Dashboard | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6" x-data="{ activeTab: 'overview' }">

    <!-- Header -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
                <a href="/" class="hover:underline">Home</a>
                <span>/</span>
                <span class="text-[var(--foreground)] font-semibold">Vendor Portal</span>
                <span>/</span>
                <span class="text-[var(--accent-dark)] font-bold capitalize" x-text="activeTab">Overview</span>
            </div>
            <h1 class="text-2xl font-black text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-store text-[var(--accent-dark)]"></i>
                <span>Vendor Operations Dashboard</span>
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-0.5 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R15 • Assigned: Farhan Tanvir [22299179] (Lead)
                </span>
                <span>ProTech Solutions (Dhaka Banani Hub) • Verified Merchant</span>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <span class="rounded-full bg-emerald-100 border border-emerald-300 text-emerald-900 text-xs font-bold px-3 py-1.5 flex items-center gap-1.5 shadow-sm">
                <span class="h-2 w-2 rounded-full bg-emerald-600 animate-pulse"></span>
                <span>Live Bidding Active</span>
            </span>
            <a href="/shops/explore" class="rounded-full border border-[var(--border)] bg-[var(--card)] px-4 py-1.5 text-xs font-semibold text-[var(--foreground)] hover:bg-[var(--mint-50)] transition">
                View Public Shop
            </a>
        </div>
    </div>

    <!-- ── Dashboard Navigation Tabs (Exact match to commit aff5aff / 8e44218) ── -->
    <div class="flex overflow-x-auto whitespace-nowrap gap-2 border-b border-[var(--border)] pb-2 mb-6 text-sm">
        <button
            type="button"
            @click="activeTab = 'overview'"
            :class="activeTab === 'overview' ? 'bg-white text-[var(--accent-dark)] font-bold border-t border-x border-[var(--border)] shadow-sm' : 'text-[var(--muted-foreground)] hover:bg-[var(--mint-50)]'"
            class="rounded-t-2xl px-5 py-2.5 transition flex items-center gap-2"
        >
            <i class="fa-solid fa-chart-pie text-xs"></i>
            <span>Overview</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'marketplace'"
            :class="activeTab === 'marketplace' ? 'bg-white text-[var(--accent-dark)] font-bold border-t border-x border-[var(--border)] shadow-sm' : 'text-[var(--muted-foreground)] hover:bg-[var(--mint-50)]'"
            class="rounded-t-2xl px-5 py-2.5 transition flex items-center gap-2"
        >
            <i class="fa-solid fa-gavel text-xs text-amber-600"></i>
            <span>Marketplace Bids</span>
            <span class="rounded-full bg-[var(--accent-dark)] px-2 py-0.5 text-[10px] text-white">4 Live</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'direct_orders'"
            :class="activeTab === 'direct_orders' ? 'bg-white text-[var(--accent-dark)] font-bold border-t border-x border-[var(--border)] shadow-sm' : 'text-[var(--muted-foreground)] hover:bg-[var(--mint-50)]'"
            class="rounded-t-2xl px-5 py-2.5 transition flex items-center gap-2"
        >
            <i class="fa-solid fa-box text-xs text-emerald-700"></i>
            <span>Direct Orders</span>
            <span class="rounded-full bg-emerald-800 px-2 py-0.5 text-[10px] text-white">2</span>
        </button>

        <button
            type="button"
            @click="activeTab = 'assigned_jobs'"
            :class="activeTab === 'assigned_jobs' ? 'bg-white text-[var(--accent-dark)] font-bold border-t border-x border-[var(--border)] shadow-sm' : 'text-[var(--muted-foreground)] hover:bg-[var(--mint-50)]'"
            class="rounded-t-2xl px-5 py-2.5 transition flex items-center gap-2"
        >
            <i class="fa-solid fa-screwdriver-wrench text-xs text-indigo-700"></i>
            <span>My Repair Jobs</span>
            <span class="rounded-full bg-indigo-900 px-2 py-0.5 text-[10px] text-white">3 Active</span>
        </button>
    </div>

    <!-- ── TAB 1: OVERVIEW ── -->
    <div x-show="activeTab === 'overview'" class="space-y-6">
        <!-- 4 Summary KPI Cards -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <div class="meramot-card p-5 shadow-sm">
                <span class="text-xs text-[var(--muted-foreground)] uppercase font-bold block mb-1">Gross Inflow</span>
                <span class="text-2xl font-black text-emerald-800">৳48,500</span>
                <span class="text-[10px] text-emerald-700 block mt-1"><i class="fa-solid fa-arrow-trend-up"></i> +18% this sprint</span>
            </div>
            <div class="meramot-card p-5 shadow-sm">
                <span class="text-xs text-[var(--muted-foreground)] uppercase font-bold block mb-1">Active Repair Jobs</span>
                <span class="text-2xl font-black text-indigo-900">8</span>
                <span class="text-[10px] text-[var(--muted-foreground)] block mt-1">3 awaiting bench parts</span>
            </div>
            <div class="meramot-card p-5 shadow-sm">
                <span class="text-xs text-[var(--muted-foreground)] uppercase font-bold block mb-1">Quotes Submitted</span>
                <span class="text-2xl font-black text-[var(--foreground)]">48</span>
                <span class="text-[10px] text-emerald-800 block mt-1">36 bids accepted</span>
            </div>
            <div class="meramot-card p-5 shadow-sm">
                <span class="text-xs text-[var(--muted-foreground)] uppercase font-bold block mb-1">Customer Rating</span>
                <span class="text-2xl font-black text-amber-600">★ 4.9</span>
                <span class="text-[10px] text-[var(--muted-foreground)] block mt-1">Based on 84 reviews</span>
            </div>
        </div>

        <!-- Recent Performance Grid -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="meramot-card p-6 shadow-sm lg:col-span-2">
                <h3 class="font-bold text-base text-[var(--foreground)] mb-3 flex items-center justify-between">
                    <span>Recent Repair Activity & Completed Milestones</span>
                    <a href="#" @click.prevent="activeTab = 'assigned_jobs'" class="text-xs text-[var(--accent-dark)] hover:underline">View All &rarr;</a>
                </h3>
                <div class="divide-y divide-[var(--border)] text-xs">
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-[var(--foreground)] block">Apple MacBook Pro M1 14"</span>
                            <span class="text-[var(--muted-foreground)]">Backlight IC replacement • Customer: Farhan T.</span>
                        </div>
                        <span class="rounded-full bg-emerald-100 text-emerald-900 font-bold px-3 py-1 text-[10px]">REPAIRING</span>
                    </div>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-[var(--foreground)] block">Lenovo ThinkPad T14 Gen 2</span>
                            <span class="text-[var(--muted-foreground)]">Keyboard replacement • Customer: Sadia N.</span>
                        </div>
                        <span class="rounded-full bg-indigo-100 text-indigo-900 font-bold px-3 py-1 text-[10px]">DIAGNOSING</span>
                    </div>
                    <div class="py-3 flex items-center justify-between">
                        <div>
                            <span class="font-bold text-[var(--foreground)] block">Samsung Galaxy S23 Ultra</span>
                            <span class="text-[var(--muted-foreground)]">AMOLED display swap • Customer: Nafis K.</span>
                        </div>
                        <span class="rounded-full bg-emerald-800 text-white font-bold px-3 py-1 text-[10px]">COMPLETED</span>
                    </div>
                </div>
            </div>

            <!-- Shop Hours & Settlement Settings -->
            <div class="meramot-card p-6 shadow-sm space-y-4">
                <h3 class="font-bold text-base text-[var(--foreground)]">Shop Profile & Settings</h3>
                <div class="space-y-3 text-xs">
                    <div class="flex justify-between items-center pb-2 border-b border-[var(--border)]">
                        <span class="text-[var(--muted-foreground)]">Opening Hours:</span>
                        <span class="font-bold text-[var(--foreground)]">10:00 AM – 09:00 PM</span>
                    </div>
                    <div class="flex justify-between items-center pb-2 border-b border-[var(--border)]">
                        <span class="text-[var(--muted-foreground)]">Inspection Fee:</span>
                        <span class="font-bold text-[var(--accent-dark)]">৳350.00</span>
                    </div>
                    <div class="flex justify-between items-center pb-2 border-b border-[var(--border)]">
                        <span class="text-[var(--muted-foreground)]">Base Labor Fee:</span>
                        <span class="font-bold text-[var(--accent-dark)]">৳500.00</span>
                    </div>
                    <div class="flex justify-between items-center pb-2 border-b border-[var(--border)]">
                        <span class="text-[var(--muted-foreground)]">BulkSMS BD Alerts:</span>
                        <span class="font-bold text-emerald-800"><i class="fa-solid fa-circle-check"></i> Enabled</span>
                    </div>
                </div>
                <a href="/vendor/alert-settings" class="block w-full text-center rounded-xl border border-[var(--border)] bg-[var(--mint-100)] py-2 text-xs font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] transition">
                    Configure SMS Notifications
                </a>
            </div>
        </div>
    </div>

    <!-- ── TAB 2: MARKETPLACE LIVE BIDDING ── -->
    <div x-show="activeTab === 'marketplace'" x-cloak class="space-y-6">
        <div class="rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] p-4 flex items-center justify-between text-xs">
            <div class="flex items-center gap-3">
                <i class="fa-solid fa-bullhorn text-amber-700 text-lg"></i>
                <span>Reverse Bidding Rules: Submit itemized parts cost, labor cost, and turnaround time. Customers choose based on warranty, price, and reputation.</span>
            </div>
            <a href="/vendor/bidding-board" class="rounded-full bg-[var(--accent-dark)] text-white px-4 py-1.5 font-bold hover:opacity-90 transition shrink-0">
                Full Board View
            </a>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- Ticket 1 -->
            <div class="meramot-card p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-wider bg-emerald-800 text-white px-2.5 py-0.5 rounded-full">
                            Bidding Open • 3 bids active
                        </span>
                        <span class="text-xs text-[var(--muted-foreground)]"><i class="fa-regular fa-clock"></i> 20 mins ago</span>
                    </div>
                    <h3 class="font-black text-lg text-[var(--foreground)]">Apple MacBook Pro 14" (2021)</h3>
                    <p class="text-xs font-semibold text-[var(--accent-dark)] mt-1">Issue: Display backlight black after minor water spill on table</p>
                    <p class="text-xs text-[var(--muted-foreground)] mt-2 leading-relaxed">
                        "Device powers on, chime sounds, caps lock key lights up, but screen remains completely dark unless illuminated with external flashlight."
                    </p>
                    <div class="mt-3 text-[11px] text-[var(--muted-foreground)] flex items-center gap-3">
                        <span><i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i> Dhanmondi, Dhaka</span>
                        <span><i class="fa-solid fa-truck text-[var(--accent-dark)]"></i> Courier Pickup</span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-[var(--border)]">
                    <form class="grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Parts (৳)</label>
                            <input type="number" placeholder="1800" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Labor (৳)</label>
                            <input type="number" placeholder="1200" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Days</label>
                            <input type="number" placeholder="2" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div class="col-span-3 mt-2">
                            <button type="button" @click="alert('Bid quote of ৳3,000 submitted successfully!')" class="w-full rounded-full bg-[var(--accent-dark)] py-2 font-bold text-white shadow hover:opacity-90 transition">
                                Submit Itemized Bid Offer &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Ticket 2 -->
            <div class="meramot-card p-6 shadow-sm flex flex-col justify-between">
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <span class="text-[10px] font-black uppercase tracking-wider bg-emerald-800 text-white px-2.5 py-0.5 rounded-full">
                            Bidding Open • 1 bid active
                        </span>
                        <span class="text-xs text-[var(--muted-foreground)]"><i class="fa-regular fa-clock"></i> 45 mins ago</span>
                    </div>
                    <h3 class="font-black text-lg text-[var(--foreground)]">Sony PlayStation 5 Disc Edition</h3>
                    <p class="text-xs font-semibold text-[var(--accent-dark)] mt-1">Issue: HDMI port bent pins, no TV signal</p>
                    <p class="text-xs text-[var(--muted-foreground)] mt-2 leading-relaxed">
                        "Console dropped forward while plugged in, cable pulled HDMI connector loose. Console boots to white light but no TV output."
                    </p>
                    <div class="mt-3 text-[11px] text-[var(--muted-foreground)] flex items-center gap-3">
                        <span><i class="fa-solid fa-location-dot text-[var(--accent-dark)]"></i> Gulshan 2, Dhaka</span>
                        <span><i class="fa-solid fa-person-walking text-[var(--accent-dark)]"></i> Customer Drop-off</span>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-[var(--border)]">
                    <form class="grid grid-cols-3 gap-2 text-xs">
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Parts (৳)</label>
                            <input type="number" placeholder="1200" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Labor (৳)</label>
                            <input type="number" placeholder="1500" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-[var(--muted-foreground)]">Days</label>
                            <input type="number" placeholder="1" class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2">
                        </div>
                        <div class="col-span-3 mt-2">
                            <button type="button" @click="alert('Bid quote of ৳2,700 submitted successfully!')" class="w-full rounded-full bg-[var(--accent-dark)] py-2 font-bold text-white shadow hover:opacity-90 transition">
                                Submit Itemized Bid Offer &rarr;
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TAB 3: DIRECT ORDERS ── -->
    <div x-show="activeTab === 'direct_orders'" x-cloak class="space-y-4">
        <div class="meramot-card p-6 shadow-sm">
            <h3 class="font-bold text-base text-[var(--foreground)] mb-4">Direct Service Package Bookings</h3>
            <div class="space-y-4 text-xs">
                <div class="p-4 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="rounded bg-emerald-800 text-white font-bold text-[10px] px-2 py-0.5 uppercase">Direct Booking</span>
                        <h4 class="font-bold text-sm text-[var(--foreground)] mt-1">Liquid Damage Ultrasonic Clean & Diagnostics</h4>
                        <p class="text-[var(--muted-foreground)]">Customer: Rafiqul Islam • Banani Road 11 • Scheduled Today 3:00 PM</p>
                        <span class="font-extrabold text-[var(--accent-dark)] text-sm">৳1,800.00</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="alert('Order accepted! Rider dispatched for doorstep pickup.')" class="rounded-full bg-[var(--accent-dark)] text-white font-bold px-4 py-2 hover:opacity-90 shadow-sm transition">
                            Accept & Dispatch Rider
                        </button>
                        <button type="button" @click="alert('Order declined.')" class="rounded-full border border-rose-300 bg-white text-rose-700 font-bold px-4 py-2 hover:bg-rose-50 transition">
                            Decline
                        </button>
                    </div>
                </div>

                <div class="p-4 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div>
                        <span class="rounded bg-emerald-800 text-white font-bold text-[10px] px-2 py-0.5 uppercase">Direct Booking</span>
                        <h4 class="font-bold text-sm text-[var(--foreground)] mt-1">Laptop Thermal Paste & Deep Dust Cleaning</h4>
                        <p class="text-[var(--muted-foreground)]">Customer: Fahim Chowdhury • Gulshan 1 • Tomorrow 11:00 AM</p>
                        <span class="font-extrabold text-[var(--accent-dark)] text-sm">৳1,200.00</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <button type="button" @click="alert('Order accepted! Rider dispatched for doorstep pickup.')" class="rounded-full bg-[var(--accent-dark)] text-white font-bold px-4 py-2 hover:opacity-90 shadow-sm transition">
                            Accept & Dispatch Rider
                        </button>
                        <button type="button" @click="alert('Order declined.')" class="rounded-full border border-rose-300 bg-white text-rose-700 font-bold px-4 py-2 hover:bg-rose-50 transition">
                            Decline
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TAB 4: MY REPAIR JOBS ── -->
    <div x-show="activeTab === 'assigned_jobs'" x-cloak class="space-y-4">
        <div class="meramot-card p-6 shadow-sm">
            <h3 class="font-bold text-base text-[var(--foreground)] mb-4">Active Repair Queue & Workflow Milestones</h3>
            <div class="divide-y divide-[var(--border)] text-xs">
                
                <!-- Job Row 1 -->
                <div class="py-4 flex flex-col md:flex-row md:items-center justify-between gap-4" x-data="{ status: 'REPAIRING' }">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-bold text-sm text-[var(--foreground)]">Job #RJ-2026-084: Apple MacBook Pro 14"</span>
                            <span class="rounded-full bg-emerald-100 text-emerald-900 border border-emerald-300 font-bold text-[10px] px-2 py-0.5" x-text="status"></span>
                        </div>
                        <p class="text-[var(--muted-foreground)]">Parts: Backlight IC + Fuse • Total: ৳3,200.00 • Turnaround: Tomorrow 5 PM</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <select x-model="status" class="rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2 font-bold text-xs outline-none">
                            <option value="AT_SHOP">At Shop (Received)</option>
                            <option value="DIAGNOSING">Diagnosing Bench</option>
                            <option value="REPAIRING">Repairing / Soldering</option>
                            <option value="COMPLETED">Completed (Ready for Rider)</option>
                        </select>
                        <button type="button" @click="alert('Status updated to: ' + status)" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 font-bold text-white hover:opacity-90">
                            Update
                        </button>
                    </div>
                </div>

                <!-- Job Row 2 -->
                <div class="py-4 flex flex-col md:flex-row md:items-center justify-between gap-4" x-data="{ status: 'DIAGNOSING' }">
                    <div>
                        <div class="flex items-center gap-2 mb-1">
                            <span class="font-bold text-sm text-[var(--foreground)]">Job #RJ-2026-085: Lenovo ThinkPad T14</span>
                            <span class="rounded-full bg-indigo-100 text-indigo-900 border border-indigo-300 font-bold text-[10px] px-2 py-0.5" x-text="status"></span>
                        </div>
                        <p class="text-[var(--muted-foreground)]">Parts: OEM Backlit Keyboard • Total: ৳2,800.00 • Turnaround: Nov 4</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <select x-model="status" class="rounded-xl border border-[var(--border)] bg-[var(--mint-300)] p-2 font-bold text-xs outline-none">
                            <option value="AT_SHOP">At Shop (Received)</option>
                            <option value="DIAGNOSING">Diagnosing Bench</option>
                            <option value="REPAIRING">Repairing / Soldering</option>
                            <option value="COMPLETED">Completed (Ready for Rider)</option>
                        </select>
                        <button type="button" @click="alert('Status updated to: ' + status)" class="rounded-full bg-[var(--accent-dark)] px-4 py-2 font-bold text-white hover:opacity-90">
                            Update
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>

</div>
@endsection

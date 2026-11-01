@extends('layouts.app')

@section('title', 'Post Device Repair Request | Meramot (Feature R7)')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6 md:px-6">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>/</span>
        <span class="text-[var(--foreground)] font-semibold">Post Repair Request</span>
    </div>

    <div class="meramot-card p-6 md:p-8 shadow-sm">
        
        <div class="mb-6 border-b border-[var(--border)] pb-4">
            <span class="text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)]">
                Feature R7 • Shanid Sajjatuz Islam [23201465]
            </span>
            <h1 class="text-2xl font-black text-[var(--foreground)] mt-2">
                Post an Electronic Device Repair Request
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Verified repair vendors across Dhaka will review your hardware symptoms and submit competitive itemized quotes.
            </p>
        </div>

        <!-- AI Assistant Banner Hook -->
        <div class="mb-6 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] p-4 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-2xl bg-[var(--card)] border border-[var(--border)] flex items-center justify-center text-amber-600 font-bold text-lg shrink-0">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                </div>
                <div>
                    <h4 class="font-bold text-xs text-[var(--foreground)]">Unsure what is wrong with your device?</h4>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Our FastAPI Python diagnostic model can analyze symptoms in real-time.</p>
                </div>
            </div>
            <a href="/ai-consultant/chat" class="rounded-full bg-[var(--accent-dark)] text-white text-[11px] font-bold px-4 py-2 hover:opacity-90 transition shrink-0">
                Open AI Consultant
            </a>
        </div>

        <form method="POST" action="/requests" class="space-y-5 text-xs">
            @csrf

            <!-- Pre-selected Shop alert if came from shop profile -->
            @if(request('shop_id'))
                <input type="hidden" name="preferred_shop_id" value="{{ request('shop_id') }}">
                <div class="p-3 rounded-2xl bg-emerald-50 border border-emerald-300 text-emerald-900 text-xs flex items-center gap-2">
                    <i class="fa-solid fa-circle-check text-emerald-700"></i>
                    <span>Direct quote requested from shop partner <strong>#{{ request('shop_id') }}</strong></span>
                </div>
            @endif

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Device Category *</label>
                    <select name="device_type" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 font-medium outline-none" required>
                        <option value="Laptop">Laptop / Ultrabook / MacBook</option>
                        <option value="Mobile Phone">Smartphone / iPhone / Android</option>
                        <option value="Tablet">Tablet / iPad</option>
                        <option value="Gaming Console">Gaming Console (PS5, Switch, Xbox)</option>
                        <option value="Audio Equipment">Smartwatch / Wearable / Audio</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Brand Name *</label>
                    <input type="text" name="brand" placeholder="Apple, Lenovo, Asus, Samsung, Dell..." class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Model Name / Number *</label>
                    <input type="text" name="model_name" placeholder="e.g. MacBook Air M2 2022, Galaxy S23 Ultra" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Issue Headline *</label>
                    <input type="text" name="issue_title" placeholder="e.g. Broken display glass, does not power on" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                </div>
            </div>

            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">Detailed Symptoms & History *</label>
                <textarea name="issue_description" rows="4" placeholder="Describe symptoms: Was there any liquid spill? Does the fan spin? Did it drop? Any smoke or burning odor?" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 leading-relaxed outline-none" required></textarea>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Preferred Logistics Mode *</label>
                    <select name="preferred_service_type" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 font-medium outline-none" required>
                        <option value="pickup_dropoff">Rider Doorstep Pickup & Return (OTP Verified)</option>
                        <option value="walk_in">Customer Walk-in to Technician Shop</option>
                        <option value="technician_home_visit">Technician On-Site Home Diagnostic</option>
                    </select>
                </div>
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Dhaka Area / Pickup Address</label>
                    <input type="text" name="pickup_address" placeholder="e.g. Road 11, Banani, Dhaka" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none">
                </div>
            </div>

            <div class="pt-4 border-t border-[var(--border)] flex items-center justify-between">
                <span class="text-[11px] text-[var(--muted-foreground)]">
                    <i class="fa-solid fa-lock text-[10px] mr-1"></i> Customer payments held in secure escrow until job completion
                </span>
                <button type="submit" class="rounded-full bg-[var(--accent-dark)] text-white text-xs font-bold px-6 py-3 shadow hover:opacity-90 transition">
                    Submit Request to Live Bidding &rarr;
                </button>
            </div>
        </form>

    </div>

</div>
@endsection

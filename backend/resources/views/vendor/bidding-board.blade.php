@extends('layouts.app')

@section('title', 'Vendor Live Bidding Board | Meramot (Feature R12)')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6">

    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-gavel text-amber-700"></i>
                Live Repair Bidding Marketplace
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-0.5">
                Feature R12 • Assigned: <strong class="text-[var(--accent-dark)]">Farhan Tanvir [22299179] (Lead)</strong> • Real-time reverse bidding on hardware tickets
            </p>
        </div>
        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] text-[var(--accent-dark)] font-bold text-xs px-3.5 py-1.5 self-start">
            Reverse-Quote Engine
        </span>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Active Request Card -->
        <div class="meramot-card p-6 shadow-sm">
            <span class="text-[10px] font-extrabold uppercase px-2.5 py-1 rounded-full bg-emerald-800 text-white">
                Bidding Open
            </span>
            <h3 class="font-black text-[var(--foreground)] text-base mt-3">Apple MacBook Pro M1 (14-inch)</h3>
            <p class="text-xs text-[var(--foreground)] mt-1 font-medium">Issue: No display backlight after accidental liquid contact</p>
            <div class="mt-3 text-[11px] text-[var(--muted-foreground)] space-y-1">
                <div><i class="fa-solid fa-location-dot text-[var(--accent-dark)] mr-1"></i> Customer in Dhanmondi, Dhaka</div>
                <div><i class="fa-solid fa-clock text-[var(--accent-dark)] mr-1"></i> Ticket posted 20 mins ago</div>
            </div>

            <!-- AI Diagnostic Microservice Hook (Feature R13) -->
            <div class="mt-4 p-3.5 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] text-xs text-[var(--foreground)]">
                <span class="font-bold flex items-center gap-1.5 text-amber-700 mb-1">
                    <i class="fa-solid fa-wand-magic-sparkles"></i>
                    FastAPI AI Hardware Summary:
                </span>
                <p class="text-[11px] text-[var(--muted-foreground)] leading-relaxed">
                    Probable Backlight Driver IC short or blown fuse on motherboard 50V rail. Recommended step: Test backlight boost diode with multimeter.
                </p>
            </div>
        </div>

        <!-- Submit Diagnostic Bid Form -->
        <div class="lg:col-span-2 meramot-card p-6 shadow-sm">
            <h3 class="font-bold text-sm text-[var(--foreground)] mb-4 flex items-center gap-2">
                <i class="fa-solid fa-file-invoice-dollar text-[var(--accent-dark)]"></i>
                Submit Verified Diagnostic Quote
            </h3>
            
            <form method="POST" action="/requests/1/bids" class="space-y-4 text-xs">
                @csrf
                <input type="hidden" name="vendor_id" value="1">
                
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Proposed Cost (৳) *</label>
                        <input type="number" name="proposed_cost" placeholder="3500" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Turnaround Days *</label>
                        <input type="number" name="estimated_days" value="2" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Warranty (Days) *</label>
                        <input type="number" name="warranty_days" value="90" class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 outline-none" required>
                    </div>
                </div>

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Technician Diagnostic Note</label>
                    <textarea name="technician_note" rows="3" placeholder="Includes ultrasonic motherboard cleaning and OEM chip replacement with oscilloscope testing." class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs p-3 leading-relaxed outline-none"></textarea>
                </div>

                <div class="pt-2 flex items-center justify-between">
                    <span class="text-[11px] text-[var(--muted-foreground)]">
                        Platform Commission: 10.00% automatically accounted for
                    </span>
                    <button type="submit" class="rounded-full bg-[var(--accent-dark)] text-white text-xs font-bold px-6 py-2.5 shadow hover:opacity-90 transition">
                        Place Diagnostic Bid &rarr;
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>
@endsection

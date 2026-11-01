<?php $__env->startSection('title', 'Vendor Live Bidding Board | Feature R12'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Live Repair Bidding Marketplace</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R12 | Assigned to: <strong class="text-teal-700">Farhan Tanvir [22299179]</strong></p>
    </div>
    <span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-3 py-1 rounded-full border border-indigo-200">
        Sprint 2 Target
    </span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <!-- Active Request Card -->
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-emerald-100 text-emerald-800 uppercase font-mono">Bidding Open</span>
        <h3 class="font-bold text-slate-900 text-base mt-2">Apple MacBook Pro M1 (14-inch)</h3>
        <p class="text-xs text-slate-600 mt-1 font-medium">Issue: No display backlight after accidental liquid contact</p>
        <p class="text-xs text-slate-400 mt-2">Customer: Dhanmondi, Dhaka | Posted 20 mins ago</p>

        <!-- AI Diagnostic Insight (Feature R13 link) -->
        <div class="mt-4 p-3 bg-purple-50 rounded-lg border border-purple-200 text-xs text-purple-900">
            <span class="font-bold block mb-1"><i class="fa-solid fa-microchip"></i> AI Microservice Summary:</span>
            Probable Backlight Driver IC short or blown fuse on motherboard 50V rail.
        </div>
    </div>

    <!-- Submit Diagnostic Bid Form -->
    <div class="lg:col-span-2 bg-white p-6 rounded-xl border border-slate-200 shadow-sm">
        <h3 class="font-bold text-sm text-slate-900 mb-4">Submit Verified Diagnostic Quote</h3>
        
        <form method="POST" action="/requests/1/bids" class="space-y-4 text-xs">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="vendor_id" value="1">
            
            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Proposed Total Cost (৳)</label>
                    <input type="number" name="proposed_cost" placeholder="3500" class="w-full rounded-lg border-slate-300 text-xs py-2" required>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Estimated Days</label>
                    <input type="number" name="estimated_days" value="2" class="w-full rounded-lg border-slate-300 text-xs py-2" required>
                </div>
                <div>
                    <label class="block font-semibold text-slate-700 mb-1">Warranty (Days)</label>
                    <input type="number" name="warranty_days" value="90" class="w-full rounded-lg border-slate-300 text-xs py-2" required>
                </div>
            </div>

            <div>
                <label class="block font-semibold text-slate-700 mb-1">Technician Diagnostic Note</label>
                <textarea name="technician_note" rows="3" placeholder="Includes ultrasonic motherboard cleaning and OEM chip replacement." class="w-full rounded-lg border-slate-300 text-xs p-3"></textarea>
            </div>

            <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-6 rounded-lg text-xs transition">
                Place Diagnostic Bid
            </button>
        </form>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\USER\Desktop\CSE 470\CSE470-Meramot-Project\backend\resources\views/vendor/bidding-board.blade.php ENDPATH**/ ?>
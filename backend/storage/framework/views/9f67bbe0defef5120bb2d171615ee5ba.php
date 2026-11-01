<?php $__env->startSection('title', 'Post Repair Request | Feature R7'); ?>

<?php $__env->startSection('content'); ?>
<div class="max-w-2xl mx-auto bg-white rounded-xl border border-slate-200 p-8 shadow-sm">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Post a Device Repair Request</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R7 | Assigned to: <strong class="text-teal-700">Shanid Sajjatuz Islam [23201465]</strong></p>
    </div>

    <form method="POST" action="/requests" class="space-y-5 text-sm">
        <?php echo csrf_field(); ?>
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block font-semibold text-slate-700 text-xs mb-1">Device Category</label>
                <select name="device_type" class="w-full rounded-lg border-slate-300 text-xs py-2" required>
                    <option value="Laptop">Laptop / Notebook</option>
                    <option value="Mobile Phone">Mobile Phone</option>
                    <option value="Tablet">Tablet / iPad</option>
                    <option value="Gaming Console">Gaming Console</option>
                </select>
            </div>
            <div>
                <label class="block font-semibold text-slate-700 text-xs mb-1">Brand Name</label>
                <input type="text" name="brand" placeholder="Apple, Dell, Samsung..." class="w-full rounded-lg border-slate-300 text-xs py-2" required>
            </div>
        </div>

        <div>
            <label class="block font-semibold text-slate-700 text-xs mb-1">Model Name / Number</label>
            <input type="text" name="model_name" placeholder="MacBook Air M2, iPhone 14 Pro, ThinkPad X1..." class="w-full rounded-lg border-slate-300 text-xs py-2" required>
        </div>

        <div>
            <label class="block font-semibold text-slate-700 text-xs mb-1">Fault Headline</label>
            <input type="text" name="issue_title" placeholder="Broken display glass after drop..." class="w-full rounded-lg border-slate-300 text-xs py-2" required>
        </div>

        <div>
            <label class="block font-semibold text-slate-700 text-xs mb-1">Detailed Symptoms & History</label>
            <textarea name="issue_description" rows="4" placeholder="Describe when the issue started, whether there was liquid exposure, error beeps, etc." class="w-full rounded-lg border-slate-300 text-xs p-3" required></textarea>
        </div>

        <div>
            <label class="block font-semibold text-slate-700 text-xs mb-1">Preferred Service Mode</label>
            <select name="preferred_service_type" class="w-full rounded-lg border-slate-300 text-xs py-2" required>
                <option value="pickup_dropoff">Rider Courier Pickup & Return</option>
                <option value="walk_in">Customer Walk-in to Repair Shop</option>
                <option value="technician_home_visit">Technician Home Diagnostic Visit</option>
            </select>
        </div>

        <div>
            <label class="block font-semibold text-slate-700 text-xs mb-1">Pickup Address (Optional)</label>
            <input type="text" name="pickup_address" placeholder="House, Road, Area, Dhaka" class="w-full rounded-lg border-slate-300 text-xs py-2">
        </div>

        <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2.5 rounded-lg text-sm transition">
            Publish Request to Bidding Board
        </button>
    </form>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\USER\Desktop\CSE 470\CSE470-Meramot-Project\backend\resources\views/requests/create.blade.php ENDPATH**/ ?>
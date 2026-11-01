<?php $__env->startSection('title', 'Explore Verified Repair Shops | Feature R6'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Explore Verified Repair Shops</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R6 | Assigned to: <strong class="text-teal-700">Shanid Sajjatuz Islam [23201465]</strong></p>
    </div>
    <span class="text-xs bg-amber-100 text-amber-800 font-semibold px-3 py-1 rounded-full border border-amber-200">
        Sprint 1 Target
    </span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Filter Sidebar -->
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm h-fit">
        <h3 class="font-bold text-sm text-slate-800 mb-4">Search & Filters</h3>
        <form method="GET" action="/shops/explore" class="space-y-4 text-xs">
            <div>
                <label class="block font-semibold text-slate-600 mb-1">Dhaka Metro Area</label>
                <select name="area" class="w-full rounded-lg border-slate-300 text-xs py-2">
                    <option value="">All Areas</option>
                    <option value="Dhanmondi">Dhanmondi</option>
                    <option value="Gulshan">Gulshan</option>
                    <option value="Banani">Banani</option>
                    <option value="Mirpur">Mirpur</option>
                    <option value="Uttara">Uttara</option>
                </select>
            </div>
            <div>
                <label class="block font-semibold text-slate-600 mb-1">Minimum Customer Rating</label>
                <select name="min_rating" class="w-full rounded-lg border-slate-300 text-xs py-2">
                    <option value="">Any Rating</option>
                    <option value="4.5">★ 4.5 & Above</option>
                    <option value="4.8">★ 4.8 & Above</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="has_voucher" value="1" id="voucherCheck" class="rounded text-teal-600">
                <label for="voucherCheck" class="text-slate-700">Has Promotional Voucher</label>
            </div>
            <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white py-2 rounded-lg font-semibold transition">
                Apply Filters
            </button>
        </form>
    </div>

    <!-- Shop Cards Grid -->
    <div class="lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
        <?php $__empty_1 = true; $__currentLoopData = $shops ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $shop): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-start">
                        <h4 class="font-bold text-slate-900 text-sm"><?php echo e($shop->name); ?></h4>
                        <span class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded font-bold border border-amber-200">
                            ★ <?php echo e($shop->rating); ?>

                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1"><i class="fa-solid fa-location-dot mr-1"></i> <?php echo e($shop->area); ?>, Dhaka</p>
                    <div class="mt-3 flex flex-wrap gap-1">
                        <?php $__currentLoopData = array_slice($shop->specialties ?? ['Screen repair', 'Battery replacement'], 0, 2); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $spec): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded"><?php echo e($spec); ?></span>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </div>
                </div>
                <a href="/shops/<?php echo e($shop->slug); ?>" class="mt-4 block text-center bg-slate-50 hover:bg-teal-50 hover:text-teal-700 text-slate-700 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 transition">
                    View Shop Profile &rarr;
                </a>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
            <div class="col-span-3 py-10 text-center text-xs text-slate-400">
                No repair shops matched your filter criteria.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\USER\Desktop\CSE 470\CSE470-Meramot-Project\backend\resources\views/shops/explore.blade.php ENDPATH**/ ?>
<?php $__env->startSection('title', 'Service Package Catalog | Feature R8'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Standard Repair Packages</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R8 | Assigned to: <strong class="text-teal-700">Shanid Sajjatuz Islam [23201465]</strong></p>
    </div>
    <span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-3 py-1 rounded-full border border-indigo-200">
        Sprint 2 Target
    </span>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6">
    <?php $__empty_1 = true; $__currentLoopData = $packages ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $pkg): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
        <div class="bg-white rounded-xl border border-slate-200 p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between">
            <div>
                <span class="text-xs font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-700 uppercase font-mono"><?php echo e($pkg->device_category); ?></span>
                <h3 class="font-bold text-lg text-slate-900 mt-2"><?php echo e($pkg->title); ?></h3>
                <p class="text-2xl font-black text-slate-900 mt-2">৳<?php echo e(number_format($pkg->fixed_price, 2)); ?></p>
            </div>
            <form method="POST" action="/cart/add" class="mt-6">
                <?php echo csrf_field(); ?>
                <input type="hidden" name="package_id" value="<?php echo e($pkg->id); ?>">
                <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white py-2 rounded-lg text-xs font-semibold">
                    Add to Cart
                </button>
            </form>
        </div>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
        <div class="col-span-3 text-center py-12 text-slate-400 text-xs">
            Standard service packages will be loaded here.
        </div>
    <?php endif; ?>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\USER\Desktop\CSE 470\CSE470-Meramot-Project\backend\resources\views/packages/catalog.blade.php ENDPATH**/ ?>
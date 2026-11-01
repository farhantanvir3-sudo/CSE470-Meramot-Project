<?php $__env->startSection('title', 'Rider Delivery Job Queue | Feature R2'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Rider Delivery Dispatch Queue</h1>
            <p class="text-xs text-slate-500 mt-1">Feature R2 | Assigned to: <strong class="text-teal-700">Md. Nafiul Islam [22299035]</strong></p>
        </div>
        <span class="text-xs bg-indigo-100 text-indigo-800 font-semibold px-3 py-1 rounded-full border border-indigo-200">
            Sprint 2 Target
        </span>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <!-- Unassigned Available Jobs -->
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <h2 class="font-bold text-base text-slate-900 mb-4 flex items-center justify-between">
            <span>Available Jobs for Pickup</span>
            <span class="text-xs bg-teal-100 text-teal-800 px-2 py-0.5 rounded-full font-mono"><?php echo e(count($availableJobs ?? [])); ?> Open</span>
        </h2>
        
        <div class="space-y-4">
            <?php $__empty_1 = true; $__currentLoopData = $availableJobs ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $job): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="p-4 border border-slate-200 rounded-lg bg-slate-50">
                    <div class="flex justify-between items-start">
                        <span class="font-semibold text-slate-900 text-sm">Trip #<?php echo e($job->id); ?></span>
                        <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded border border-emerald-200">৳150 Delivery Fee</span>
                    </div>
                    <p class="text-xs text-slate-500 mt-2"><strong>Pickup:</strong> <?php echo e($job->pickup_address ?? 'Dhanmondi 27, Dhaka'); ?></p>
                    <p class="text-xs text-slate-500"><strong>Dropoff:</strong> <?php echo e($job->dropoff_address ?? 'Mirpur 10 (Repair Shop)'); ?></p>

                    <form method="POST" action="/delivery/jobs/<?php echo e($job->id); ?>/claim" class="mt-4">
                        <?php echo csrf_field(); ?>
                        <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-medium text-xs py-2 rounded-lg transition">
                            Claim Delivery Task
                        </button>
                    </form>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="text-xs text-slate-400 py-6 text-center">No available unassigned delivery jobs in your sector right now.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Active Claimed Tasks -->
    <div class="bg-white rounded-xl border border-slate-200 p-5 shadow-sm">
        <h2 class="font-bold text-base text-slate-900 mb-4">My In-Progress Deliveries</h2>
        <div class="space-y-4">
            <?php $__empty_1 = true; $__currentLoopData = $activeJobs ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $active): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <div class="p-4 border-l-4 border-l-amber-500 border border-slate-200 rounded-lg">
                    <div class="flex justify-between items-center mb-2">
                        <span class="font-semibold text-xs text-slate-800">Job #<?php echo e($active->id); ?></span>
                        <span class="text-xs uppercase font-mono font-bold text-amber-700"><?php echo e($active->status); ?></span>
                    </div>
                    <div class="text-xs text-slate-600 space-y-1">
                        <div>Pickup OTP: <span class="font-mono font-bold text-slate-900"><?php echo e($active->pickup_otp ?? '4921'); ?></span></div>
                    </div>
                    <a href="/tracking/<?php echo e($active->id); ?>" class="mt-3 block text-center text-xs font-semibold text-teal-600 hover:underline">
                        Open Live GPS Navigation &rarr;
                    </a>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <p class="text-xs text-slate-400 py-6 text-center">You have no active claimed delivery tasks.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\USER\Desktop\CSE 470\CSE470-Meramot-Project\backend\resources\views/delivery/jobs-queue.blade.php ENDPATH**/ ?>
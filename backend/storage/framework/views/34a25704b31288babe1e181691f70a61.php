<?php $__env->startSection('title', 'Content Moderation & Governance | Feature R19'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">User Moderation & Governance Panel</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R19 | Assigned to: <strong class="text-teal-700">Md. Nawshad Zaman Khan [23301622]</strong></p>
    </div>
    <span class="text-xs bg-amber-100 text-amber-800 font-semibold px-3 py-1 rounded-full border border-amber-200">
        Sprint 3 Target
    </span>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm p-6">
    <h3 class="font-bold text-sm text-slate-800 mb-4">Flagged System Activity</h3>
    <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-slate-500 uppercase font-semibold">
            <tr>
                <th class="px-4 py-3 text-left">Target User</th>
                <th class="px-4 py-3 text-left">Incident Type</th>
                <th class="px-4 py-3 text-left">Action Taken</th>
                <th class="px-4 py-3 text-right">Sanction Action</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200">
            <?php $__empty_1 = true; $__currentLoopData = $logs ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td class="px-4 py-3 font-semibold text-slate-800"><?php echo e($log->targetUser->name ?? 'User #102'); ?></td>
                    <td class="px-4 py-3 text-slate-600"><?php echo e($log->flagged_entity_type ?? 'Spam Review'); ?></td>
                    <td class="px-4 py-3 capitalize font-bold text-amber-600"><?php echo e($log->action_taken); ?></td>
                    <td class="px-4 py-3 text-right space-x-1">
                        <button class="px-2 py-1 bg-amber-500 text-white rounded text-[10px]">Warn</button>
                        <button class="px-2 py-1 bg-rose-600 text-white rounded text-[10px]">Ban</button>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No open moderation incidents reported.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\USER\Desktop\CSE 470\CSE470-Meramot-Project\backend\resources\views/admin/moderation.blade.php ENDPATH**/ ?>
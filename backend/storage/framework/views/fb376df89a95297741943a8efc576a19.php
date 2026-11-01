<?php $__env->startSection('title', 'System Audit Trail & Security Monitor | Feature R20'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Security & Compliance Audit Trail</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R20 | Assigned to: <strong class="text-teal-700">Md. Nawshad Zaman Khan [23301622]</strong></p>
    </div>
    <span class="text-xs bg-rose-100 text-rose-800 font-semibold px-3 py-1 rounded-full border border-rose-200">
        Sprint 4 Target
    </span>
</div>

<div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
    <table class="min-w-full divide-y divide-slate-200 text-xs">
        <thead class="bg-slate-50 text-slate-500 uppercase font-semibold">
            <tr>
                <th class="px-6 py-3 text-left">Timestamp</th>
                <th class="px-6 py-3 text-left">Event Action</th>
                <th class="px-6 py-3 text-left">Actor User</th>
                <th class="px-6 py-3 text-left">IP Address</th>
                <th class="px-6 py-3 text-center">Threat Level</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-slate-200 font-mono">
            <?php $__empty_1 = true; $__currentLoopData = $logs ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $audit): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr class="<?php echo e($audit->is_suspicious ? 'bg-rose-50' : ''); ?>">
                    <td class="px-6 py-4 text-slate-500"><?php echo e($audit->created_at->format('Y-m-d H:i:s')); ?></td>
                    <td class="px-6 py-4 font-bold text-slate-900"><?php echo e($audit->event_action); ?></td>
                    <td class="px-6 py-4 text-slate-700 font-sans"><?php echo e($audit->user->email ?? 'System'); ?></td>
                    <td class="px-6 py-4 text-slate-500"><?php echo e($audit->ip_address); ?></td>
                    <td class="px-6 py-4 text-center font-sans">
                        <?php if($audit->is_suspicious): ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-rose-100 text-rose-800">Suspicious</span>
                        <?php else: ?>
                            <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-100 text-emerald-800">Normal</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr><td colspan="5" class="px-6 py-8 text-center text-slate-400 font-sans">No audit events logged.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?><?php /**PATH C:\Users\USER\Desktop\CSE 470\CSE470-Meramot-Project\backend\resources\views/admin/audit-logs.blade.php ENDPATH**/ ?>
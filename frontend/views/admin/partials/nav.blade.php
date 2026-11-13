@php
    $adminPendingVendorsCount = \App\Models\VendorProfile::where('verification_status', 'pending')->count();
    $adminPendingRidersCount = \App\Models\DeliveryAgent::where('registration_status', 'PENDING')->count();
    $currentAdminPath = '/' . ltrim(request()->path(), '/');
@endphp

<div class="rounded-[1.75rem] border border-[var(--border)] bg-[var(--mint-100)]/90 p-3 shadow-sm">
    <div class="flex flex-wrap items-center justify-between gap-2">
        <div class="flex flex-wrap items-center gap-1.5 text-xs">
            <a
                href="/admin"
                class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 font-bold transition {{ $currentAdminPath === '/admin' ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <i class="fa-solid fa-gauge-high text-[11px]"></i>
                <span>Admin Hub</span>
            </a>

            <a
                href="/admin/vendors/pending"
                class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 font-bold transition {{ str_starts_with($currentAdminPath, '/admin/vendors') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <i class="fa-solid fa-store text-[11px]"></i>
                <span>Verify Vendors</span>
                @if($adminPendingVendorsCount > 0)
                    <span class="ml-0.5 rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-black text-white">{{ $adminPendingVendorsCount }}</span>
                @endif
            </a>

            <a
                href="/admin/delivery-agents/pending"
                class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 font-bold transition {{ str_starts_with($currentAdminPath, '/admin/delivery-agents') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <i class="fa-solid fa-motorcycle text-[11px]"></i>
                <span>Verify Riders</span>
                @if($adminPendingRidersCount > 0)
                    <span class="ml-0.5 rounded-full bg-amber-500 px-2 py-0.5 text-[10px] font-black text-white">{{ $adminPendingRidersCount }}</span>
                @endif
            </a>

            <a
                href="/admin/financial-overview"
                class="inline-flex items-center gap-1.5 rounded-full px-4 py-2 font-bold transition {{ str_starts_with($currentAdminPath, '/admin/financial-overview') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <i class="fa-solid fa-scale-balanced text-[11px]"></i>
                <span>Financial Ledger</span>
            </a>
        </div>

        <div class="flex flex-wrap items-center gap-1.5 text-[11px]">
            <a
                href="/admin/payouts/manage"
                class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 font-semibold transition {{ str_starts_with($currentAdminPath, '/admin/payouts') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)]' : 'bg-[var(--card)]/80 text-[var(--muted-foreground)] border border-[var(--border)] hover:text-[var(--foreground)]' }}"
            >
                <span>Payouts</span>
                <span class="text-[9px] uppercase opacity-75">Soon</span>
            </a>
            <a
                href="/admin/moderation"
                class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 font-semibold transition {{ str_starts_with($currentAdminPath, '/admin/moderation') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)]' : 'bg-[var(--card)]/80 text-[var(--muted-foreground)] border border-[var(--border)] hover:text-[var(--foreground)]' }}"
            >
                <span>Moderation</span>
            </a>
            <a
                href="/admin/audit-logs"
                class="inline-flex items-center gap-1 rounded-full px-3 py-1.5 font-semibold transition {{ str_starts_with($currentAdminPath, '/admin/audit-logs') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)]' : 'bg-[var(--card)]/80 text-[var(--muted-foreground)] border border-[var(--border)] hover:text-[var(--foreground)]' }}"
            >
                <span>Audit Logs</span>
                <span class="text-[9px] uppercase opacity-75">Soon</span>
            </a>
        </div>
    </div>
</div>

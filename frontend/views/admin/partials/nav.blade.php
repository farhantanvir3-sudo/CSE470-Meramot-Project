@php
    $adminPendingVendorsCount = \App\Models\VendorProfile::where('verification_status', 'pending')->count();
    $adminPendingRidersCount = \App\Models\DeliveryAgent::where('registration_status', 'PENDING')->count();
    $currentAdminPath = '/' . ltrim(request()->path(), '/');
@endphp

<div class="rounded-2xl sm:rounded-[1.75rem] border border-[var(--border)] bg-[var(--mint-100)]/90 p-2 sm:p-3 shadow-sm">
    <div class="grid grid-cols-12 gap-1.5 md:flex md:flex-wrap md:items-center md:justify-between md:gap-2">
        <div class="contents md:flex md:flex-wrap md:items-center md:gap-1.5 text-xs">
            <a
                href="/admin"
                class="col-span-4 inline-flex items-center justify-center md:justify-start whitespace-nowrap gap-1 sm:gap-1.5 rounded-full px-2 py-2 sm:px-3.5 text-[11px] sm:text-xs font-bold transition {{ $currentAdminPath === '/admin' ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <i class="fa-solid fa-gauge-high text-[10px] sm:text-[11px]"></i>
                <span>Admin Hub</span>
            </a>

            <a
                href="/admin/vendors/pending"
                class="col-span-4 inline-flex items-center justify-center md:justify-start whitespace-nowrap gap-1 sm:gap-1.5 rounded-full px-2 py-2 sm:px-3.5 text-[11px] sm:text-xs font-bold transition {{ str_starts_with($currentAdminPath, '/admin/vendors') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <i class="fa-solid fa-store text-[10px] sm:text-[11px]"></i>
                <span class="sm:hidden">Vendors</span>
                <span class="hidden sm:inline">Verify Vendors</span>
                @if($adminPendingVendorsCount > 0)
                    <span class="rounded-full bg-amber-500 px-1.5 py-0.5 text-[9px] sm:px-2 sm:text-[10px] font-black leading-none text-white">{{ $adminPendingVendorsCount }}</span>
                @endif
            </a>

            <a
                href="/admin/delivery-agents/pending"
                class="col-span-4 inline-flex items-center justify-center md:justify-start whitespace-nowrap gap-1 sm:gap-1.5 rounded-full px-2 py-2 sm:px-3.5 text-[11px] sm:text-xs font-bold transition {{ str_starts_with($currentAdminPath, '/admin/delivery-agents') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <i class="fa-solid fa-motorcycle text-[10px] sm:text-[11px]"></i>
                <span class="sm:hidden">Riders</span>
                <span class="hidden sm:inline">Verify Riders</span>
                @if($adminPendingRidersCount > 0)
                    <span class="rounded-full bg-amber-500 px-1.5 py-0.5 text-[9px] sm:px-2 sm:text-[10px] font-black leading-none text-white">{{ $adminPendingRidersCount }}</span>
                @endif
            </a>

            <a
                href="/admin/financial-overview"
                class="col-span-3 inline-flex items-center justify-center md:justify-start whitespace-nowrap gap-1 sm:gap-1.5 rounded-full px-1.5 py-2 sm:px-3.5 text-[10px] sm:text-xs font-bold transition {{ str_starts_with($currentAdminPath, '/admin/financial-overview') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <i class="fa-solid fa-scale-balanced hidden sm:inline text-[11px]"></i>
                <span class="sm:hidden">Ledger</span>
                <span class="hidden sm:inline">Financial Ledger</span>
            </a>
        </div>

        <div class="contents md:flex md:flex-wrap md:items-center md:gap-1.5 text-xs">
            <a
                href="/admin/payouts/manage"
                class="col-span-3 inline-flex items-center justify-center md:justify-start whitespace-nowrap gap-1 sm:gap-1.5 rounded-full px-1.5 py-2 sm:px-3.5 text-[10px] sm:text-xs font-bold transition {{ str_starts_with($currentAdminPath, '/admin/payouts') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <span>Payouts</span>
                <span class="hidden sm:inline text-[9px] uppercase opacity-75">Soon</span>
            </a>
            <a
                href="/admin/moderation"
                class="col-span-3 inline-flex items-center justify-center md:justify-start whitespace-nowrap gap-1 sm:gap-1.5 rounded-full px-1.5 py-2 sm:px-3.5 text-[10px] sm:text-xs font-bold transition {{ str_starts_with($currentAdminPath, '/admin/moderation') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <span>Moderation</span>
            </a>
            <a
                href="/admin/audit-logs"
                class="col-span-3 inline-flex items-center justify-center md:justify-start whitespace-nowrap gap-1 sm:gap-1.5 rounded-full px-1.5 py-2 sm:px-3.5 text-[10px] sm:text-xs font-bold transition {{ str_starts_with($currentAdminPath, '/admin/audit-logs') ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] shadow-sm' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:border-[var(--accent-dark)]' }}"
            >
                <span class="sm:hidden">Audits</span>
                <span class="hidden sm:inline">Audit Logs</span>
                <span class="hidden sm:inline text-[9px] uppercase opacity-75">Soon</span>
            </a>
        </div>
    </div>
</div>

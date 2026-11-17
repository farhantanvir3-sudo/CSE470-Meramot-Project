@php
    $adminPendingVendorsCount = \App\Models\VendorProfile::where('verification_status', 'pending')->count();
    $adminPendingRidersCount = \App\Models\DeliveryAgent::where('registration_status', 'PENDING')->count();
    $adminPendingPayoutsCount = \App\Models\VendorPayout::where('status', 'PENDING')->count();
    $adminPendingWarrantiesCount = \Illuminate\Support\Facades\Schema::hasTable('warranty_claims')
        ? \App\Models\WarrantyClaim::where('status', \App\Models\WarrantyClaim::STATUS_PENDING)->count()
        : 0;
    $adminVoucherNavHealth = \Illuminate\Support\Facades\Schema::hasTable('vouchers')
        ? app(\App\Services\VoucherService::class)->getAdminFeaturedStackHealth()
        : null;
    $currentAdminPath = '/' . ltrim(request()->path(), '/');

    $navItems = [
        [
            'title' => 'Admin Hub',
            'short_title' => 'Hub',
            'href' => '/admin',
            'active' => $currentAdminPath === '/admin',
            'icon' => 'fa-gauge-high',
            'badge' => null,
            'badge_color' => null,
        ],
        [
            'title' => 'Verify Vendors',
            'short_title' => 'Vendors',
            'href' => '/admin/vendors/pending',
            'active' => str_starts_with($currentAdminPath, '/admin/vendors'),
            'icon' => 'fa-store',
            'badge' => $adminPendingVendorsCount > 0 ? (string) $adminPendingVendorsCount : null,
            'badge_color' => 'bg-amber-500 text-white',
        ],
        [
            'title' => 'Verify Riders',
            'short_title' => 'Riders',
            'href' => '/admin/delivery-agents/pending',
            'active' => str_starts_with($currentAdminPath, '/admin/delivery-agents'),
            'icon' => 'fa-motorcycle',
            'badge' => $adminPendingRidersCount > 0 ? (string) $adminPendingRidersCount : null,
            'badge_color' => 'bg-amber-500 text-white',
        ],
        [
            'title' => 'Financial Ledger',
            'short_title' => 'Ledger',
            'href' => '/admin/financial-overview',
            'active' => str_starts_with($currentAdminPath, '/admin/financial-overview'),
            'icon' => 'fa-scale-balanced',
            'badge' => null,
            'badge_color' => null,
        ],
        [
            'title' => 'Payouts',
            'short_title' => 'Payouts',
            'href' => '/admin/payouts/manage',
            'active' => str_starts_with($currentAdminPath, '/admin/payouts'),
            'icon' => 'fa-money-bill-transfer',
            'badge' => $adminPendingPayoutsCount > 0 ? (string) $adminPendingPayoutsCount : null,
            'badge_color' => 'bg-amber-500 text-white',
        ],
        [
            'title' => 'Moderation',
            'short_title' => 'Moderation',
            'href' => '/admin/moderation',
            'active' => str_starts_with($currentAdminPath, '/admin/moderation'),
            'icon' => 'fa-shield-halved',
            'badge' => null,
            'badge_color' => null,
        ],
        [
            'title' => 'Warranties',
            'short_title' => 'Warranties',
            'href' => '/admin/warranties',
            'active' => str_starts_with($currentAdminPath, '/admin/warranties'),
            'icon' => 'fa-award',
            'badge' => $adminPendingWarrantiesCount > 0 ? (string) $adminPendingWarrantiesCount : null,
            'badge_color' => 'bg-amber-500 text-white',
        ],
        [
            'title' => 'Featured Vouchers',
            'short_title' => 'Vouchers',
            'href' => '/admin/vouchers',
            'active' => str_starts_with($currentAdminPath, '/admin/vouchers'),
            'icon' => 'fa-ticket',
            'badge' => !empty($adminVoucherNavHealth['is_empty']) ? '!' : (!empty($adminVoucherNavHealth['is_low_stack']) ? 'Low' : null),
            'badge_color' => !empty($adminVoucherNavHealth['is_empty']) ? 'bg-rose-600 text-white' : 'bg-amber-500 text-white',
        ],
        [
            'title' => 'Audit Logs',
            'short_title' => 'Audits',
            'href' => '/admin/audit-logs',
            'active' => str_starts_with($currentAdminPath, '/admin/audit-logs'),
            'icon' => 'fa-clipboard-list',
            'badge' => 'Soon',
            'badge_color' => 'bg-[var(--mint-200)] text-[var(--accent-dark)] opacity-90',
        ],
    ];
@endphp

<!-- Admin Navigation: Single-Row Responsive Slider + Quick Navigation Dropdown -->
<nav
    aria-label="Admin Navigation"
    x-data="{
        canScrollLeft: false,
        canScrollRight: false,
        dropdownOpen: false,
        updateScrollState() {
            const el = this.$refs.sliderTrack;
            if (!el) return;
            this.canScrollLeft = el.scrollLeft > 6;
            this.canScrollRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 6;
        },
        scrollLeft() {
            if (!this.$refs.sliderTrack) return;
            this.$refs.sliderTrack.scrollBy({ left: -260, behavior: 'smooth' });
            setTimeout(() => this.updateScrollState(), 220);
        },
        scrollRight() {
            if (!this.$refs.sliderTrack) return;
            this.$refs.sliderTrack.scrollBy({ left: 260, behavior: 'smooth' });
            setTimeout(() => this.updateScrollState(), 220);
        }
    }"
    x-init="
        $nextTick(() => {
            updateScrollState();
            const activeEl = $refs.sliderTrack?.querySelector('[data-active=true]');
            if (activeEl) {
                setTimeout(() => {
                    activeEl.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
                    updateScrollState();
                }, 80);
            }
        });
    "
    @resize.window.debounce.100ms="updateScrollState()"
    class="relative rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/90 p-1.5 sm:p-2 shadow-sm flex items-center gap-1.5 min-w-0"
>
    <!-- Scroll Left Arrow Button (Appears only when track is scrolled right) -->
    <button
        type="button"
        @click="scrollLeft()"
        x-show="canScrollLeft"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-90"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-90"
        class="shrink-0 h-8 w-8 rounded-md bg-[var(--mint-50)] border border-[var(--border)] text-[var(--foreground)] hover:bg-[var(--card)] hover:text-[var(--accent-dark)] flex items-center justify-center transition shadow-2xs cursor-pointer"
        aria-label="Scroll admin navigation left"
    >
        <i class="fa-solid fa-chevron-left text-[10px]"></i>
    </button>

    <!-- Horizontal Slider Track: Single-Row Never-Wrapping Pill Track -->
    <div
        x-ref="sliderTrack"
        @scroll.passive="updateScrollState()"
        class="relative flex-1 min-w-0 flex items-center gap-1.5 overflow-x-auto no-scrollbar scroll-smooth py-0.5"
    >
        @foreach($navItems as $item)
            <a
                href="{{ $item['href'] }}"
                @if($item['active']) data-active="true" @endif
                class="shrink-0 whitespace-nowrap select-none inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-xs font-bold transition {{ $item['active'] ? 'bg-[var(--accent-dark)] text-white shadow-xs' : 'bg-[var(--card)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-200)]/80 hover:border-[var(--accent-dark)]/50' }}"
            >
                <i class="fa-solid {{ $item['icon'] }} text-[11px] {{ $item['active'] ? 'text-emerald-300' : 'text-[var(--accent-dark)]' }}"></i>
                <span>{{ $item['title'] }}</span>
                @if($item['badge'])
                    <span class="rounded px-1.5 py-0.2 text-[9.5px] font-extrabold leading-none {{ $item['badge_color'] }}">
                        {{ $item['badge'] }}
                    </span>
                @endif
            </a>
        @endforeach
    </div>

    <!-- Scroll Right Arrow Button (Appears only when track has overflow content to the right) -->
    <button
        type="button"
        @click="scrollRight()"
        x-show="canScrollRight"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-90"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-90"
        class="shrink-0 h-8 w-8 rounded-md bg-[var(--mint-50)] border border-[var(--border)] text-[var(--foreground)] hover:bg-[var(--card)] hover:text-[var(--accent-dark)] flex items-center justify-center transition shadow-2xs cursor-pointer"
        aria-label="Scroll admin navigation right"
    >
        <i class="fa-solid fa-chevron-right text-[10px]"></i>
    </button>

    <!-- Subtle Vertical Divider -->
    <div class="h-6 w-px bg-[var(--border)] shrink-0 hidden sm:block"></div>

    <!-- Quick Navigation Dropdown (Instant Jump to Any Module) -->
    <div class="relative shrink-0" @click.outside="dropdownOpen = false">
        <button
            type="button"
            @click="dropdownOpen = !dropdownOpen"
            class="shrink-0 inline-flex items-center gap-1.5 rounded-md bg-[var(--card)] border border-[var(--border)] px-2.5 sm:px-3 py-1.5 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)]/80 hover:border-[var(--accent-dark)]/50 transition shadow-2xs cursor-pointer"
            :class="dropdownOpen ? 'ring-2 ring-[var(--accent-dark)]/30 border-[var(--accent-dark)]' : ''"
            aria-label="Admin quick jump dropdown menu"
            aria-haspopup="true"
            :aria-expanded="dropdownOpen"
        >
            <i class="fa-solid fa-layer-group text-[11px] text-[var(--accent-dark)]"></i>
            <span class="hidden sm:inline">Sections</span>
            <i class="fa-solid fa-chevron-down text-[9px] text-[var(--muted-foreground)] transition-transform duration-200" :class="dropdownOpen ? 'rotate-180' : ''"></i>
        </button>

        <!-- Dropdown Popover Menu -->
        <div
            x-show="dropdownOpen"
            x-cloak
            x-transition:enter="transition ease-out duration-150"
            x-transition:enter-start="opacity-0 scale-95 translate-y-1"
            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
            x-transition:leave="transition ease-in duration-100"
            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
            x-transition:leave-end="opacity-0 scale-95 translate-y-1"
            class="absolute right-0 top-full mt-1.5 w-60 rounded-lg border border-[var(--border)] bg-[var(--card)] p-1.5 shadow-xl z-50 divide-y divide-[var(--border)]/40"
        >
            <div class="px-2.5 py-1.5 text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)] flex items-center justify-between">
                <span>Admin Modules</span>
                <span class="text-[9px] font-semibold text-[var(--accent-dark)] bg-[var(--mint-100)] px-1.5 py-0.5 rounded">9 Total</span>
            </div>
            <div class="py-1 space-y-0.5 max-h-80 overflow-y-auto no-scrollbar">
                @foreach($navItems as $item)
                    <a
                        href="{{ $item['href'] }}"
                        class="flex items-center justify-between gap-2 px-2.5 py-2 rounded-md text-xs font-semibold transition {{ $item['active'] ? 'bg-[var(--accent-dark)] text-white font-bold' : 'text-[var(--foreground)] hover:bg-[var(--mint-100)]' }}"
                    >
                        <span class="flex items-center gap-2.5 truncate">
                            <i class="fa-solid {{ $item['icon'] }} text-[11px] w-4 text-center {{ $item['active'] ? 'text-emerald-300' : 'text-[var(--accent-dark)]' }}"></i>
                            <span class="truncate">{{ $item['title'] }}</span>
                        </span>
                        @if($item['badge'])
                            <span class="shrink-0 rounded px-1.5 py-0.2 text-[9px] font-extrabold leading-none {{ $item['badge_color'] }}">
                                {{ $item['badge'] }}
                            </span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    </div>
</nav>

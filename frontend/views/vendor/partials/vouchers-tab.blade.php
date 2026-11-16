@php
    $myVouchers = $vendorVouchers ?? collect();
    $featuredForVendor = $platformFeaturedVouchers ?? collect();
    $vendorBranchIds = ($branches ?? collect())->pluck('id')->map(fn ($v) => (int) $v)->all();
    $vDayShort = \App\Models\Voucher::DAY_SHORT_NAMES;
@endphp

<div x-show="activeTab === 'vouchers'" x-cloak class="space-y-5 text-xs" x-data="{ editingVendorVoucherId: null, vCreateOpen: false, vDiscType: 'FIXED', vBranchScope: 'ALL_BRANCHES' }">

    <!-- Header & Create Toggle -->
    <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-5 shadow-sm space-y-4">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div>
                <h2 class="text-base sm:text-lg font-bold text-[var(--foreground)]">Shop &amp; Branch Vouchers</h2>
                <p class="text-[11px] sm:text-xs text-[var(--muted-foreground)] mt-0.5">
                    Create custom voucher codes for all of your branches or specific branches. Active vouchers are advertised directly on your shop tiles, profile, and checkout dialog.
                </p>
            </div>
            <button
                type="button"
                data-toggle-vendor-voucher-form
                @click="vCreateOpen = !vCreateOpen"
                class="shrink-0 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-xs hover:opacity-90 cursor-pointer"
            >
                <span x-text="vCreateOpen ? 'Close Form' : '+ Create Voucher'">+ Create Voucher</span>
            </button>
        </div>

        <!-- Create Vendor Voucher Form (Manual Code Required) -->
        <form
            x-show="vCreateOpen"
            x-cloak
            method="POST"
            action="{{ route('vendor.vouchers.store') }}"
            data-vendor-create-voucher-form
            class="space-y-4 border-t border-[var(--border)] pt-4"
        >
            @csrf
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block font-bold mb-1">Voucher Code (Manual) *</label>
                    <input type="text" name="code" required maxlength="32" placeholder="e.g. WINTER150, BKASH10" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 font-mono font-bold uppercase">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold mb-1">Voucher Title *</label>
                    <input type="text" name="title" required maxlength="140" placeholder="e.g. Winter Bench Discount — ৳150 Off" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 font-semibold">
                </div>
                <div>
                    <label class="block font-bold mb-1">Voucher Type *</label>
                    <select name="voucher_type" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 font-semibold">
                        <option value="LIMITED_TIME">Limited Time (Repeatable in Window)</option>
                        <option value="USE_ONCE">Use Once (1 Time per Customer)</option>
                    </select>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <label class="block font-bold mb-1">Discount Type *</label>
                    <select name="discount_type" x-model="vDiscType" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 font-semibold">
                        <option value="FIXED">Flat Amount Off (৳)</option>
                        <option value="PERCENTAGE">Percentage Off (%)</option>
                        <option value="FREE_COURIER">Free Courier (Pickup &amp; Delivery)</option>
                    </select>
                </div>
                <div x-show="vDiscType !== 'FREE_COURIER'">
                    <label class="block font-bold mb-1" x-text="vDiscType === 'PERCENTAGE' ? 'Discount (%) *' : 'Amount Off (৳) *'">Amount *</label>
                    <input type="number" step="0.01" min="0" name="discount_value" placeholder="150" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 font-semibold">
                </div>
                <div>
                    <label class="block font-bold mb-1">Min. Spend (৳)</label>
                    <input type="number" step="1" min="0" name="min_order_amount" value="500" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                </div>
                <div x-show="vDiscType !== 'FIXED'">
                    <label class="block font-bold mb-1">Max Discount Cap (৳)</label>
                    <input type="number" step="1" min="0" name="max_discount_amount" placeholder="Optional" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                </div>
                <div>
                    <label class="block font-bold mb-1">Payment Method Rule</label>
                    <select name="required_payment_method" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                        <option value="">Any Payment Method</option>
                        <option value="BKASH">bKash Only</option>
                        <option value="NAGAD">Nagad Only</option>
                        <option value="CARD">Card Only</option>
                        <option value="SSLCOMMERZ">SSLCommerz Only</option>
                        <option value="CASH">Cash Only</option>
                    </select>
                </div>
            </div>

            <!-- Time Period & Scheduled Recurring Days -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 rounded-lg border border-[var(--border)] bg-[var(--mint-50)] p-3.5">
                <div>
                    <label class="block font-bold mb-1">Starts At (Time Period)</label>
                    <input type="datetime-local" name="starts_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                </div>
                <div>
                    <label class="block font-bold mb-1">Ends At (Time Period)</label>
                    <input type="datetime-local" name="expires_at" value="{{ now()->addDays(21)->format('Y-m-d\TH:i') }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold mb-1">Scheduled Days (Optional — e.g. Fri or Sun)</label>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach($vDayShort as $dIdx => $dName)
                            <label class="inline-flex items-center gap-1 rounded border border-[var(--border)] bg-white px-2 py-1 cursor-pointer">
                                <input type="checkbox" name="valid_days_of_week[]" value="{{ $dIdx }}" class="accent-[#244229]">
                                <span class="font-semibold">{{ $dName }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Branch Scope Selection -->
            <div class="space-y-2 rounded-lg border border-[var(--border)] bg-white p-3.5">
                <label class="block font-bold">Branch Scope *</label>
                <div class="flex flex-wrap gap-4">
                    <label class="inline-flex items-center gap-2 font-semibold cursor-pointer">
                        <input type="radio" name="branch_scope" value="ALL_BRANCHES" x-model="vBranchScope" class="accent-[#244229]">
                        <span>All My Branches ({{ count($branches) }})</span>
                    </label>
                    <label class="inline-flex items-center gap-2 font-semibold cursor-pointer">
                        <input type="radio" name="branch_scope" value="SPECIFIC_BRANCHES" x-model="vBranchScope" class="accent-[#244229]">
                        <span>Specific Branch(es) Only</span>
                    </label>
                </div>

                <div x-show="vBranchScope === 'SPECIFIC_BRANCHES'" x-cloak class="pt-2 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2">
                    @foreach($branches as $br)
                        <label class="flex items-center gap-2 rounded border border-[var(--border)] bg-[var(--mint-50)] px-3 py-2 cursor-pointer">
                            <input type="checkbox" name="shop_ids[]" value="{{ $br->id }}" {{ $activeBranch && $activeBranch->id === $br->id ? 'checked' : '' }} class="accent-[#244229]">
                            <span class="font-semibold truncate">{{ $br->display_branch_tag ?: $br->name }} ({{ $br->area }}, {{ $br->city }})</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end">
                <button type="submit" class="rounded-md bg-[var(--accent-dark)] px-5 py-2.5 font-bold text-[var(--accent-foreground)] hover:opacity-90 cursor-pointer">
                    Publish Voucher
                </button>
            </div>
        </form>
    </div>

    <!-- Active & Past Vendor Vouchers -->
    <div class="space-y-3">
        <h3 class="text-sm sm:text-base font-bold text-[var(--foreground)]">My Vouchers ({{ $myVouchers->count() }})</h3>

        @forelse($myVouchers as $vv)
            @php
                $vvExpired = $vv->expires_at && now()->gt($vv->expires_at);
                $vvShopIds = $vv->includedShops->pluck('id')->all();
            @endphp
            <div data-vendor-voucher-card="{{ $vv->code }}" class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 shadow-xs space-y-3">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-[11px] font-bold text-rose-800">
                                <i class="fa-solid fa-ticket text-[9px] text-rose-600"></i>
                                <span>{{ $vv->advertisedBadgeText() }}</span>
                            </span>

                            <span class="rounded border px-2 py-0.5 text-[10px] font-bold {{ !$vv->is_active ? 'bg-slate-100 text-slate-700 border-slate-300' : ($vvExpired ? 'bg-rose-100 text-rose-900 border-rose-300' : 'bg-emerald-50 text-emerald-900 border-emerald-200') }}">
                                {{ !$vv->is_active ? 'Paused' : ($vvExpired ? 'Ended' : ($vv->voucher_type === 'USE_ONCE' ? 'Use Once' : 'Limited Time')) }}
                            </span>

                            <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                                @if($vv->branch_scope === 'SPECIFIC_BRANCHES')
                                    Branch: {{ $vv->includedShops->map(fn ($s) => $s->display_branch_tag ?: $s->area)->implode(', ') ?: 'Selected' }}
                                @else
                                    All Branches
                                @endif
                            </span>

                            <span class="text-[11px] text-[var(--muted-foreground)]">Redemptions: <strong>{{ $vv->redemptions_count }}</strong></span>
                        </div>

                        <h4 class="text-sm font-bold text-[var(--foreground)]">{{ $vv->title }}</h4>
                        <p class="text-[11px] text-[var(--muted-foreground)]">
                            Time Period:
                            <strong>{{ $vv->starts_at ? $vv->starts_at->format('M j, Y H:i') : 'Immediate' }}</strong>
                            &rarr;
                            <strong>{{ $vv->expires_at ? $vv->expires_at->format('M j, Y H:i') : 'No Expiry' }}</strong>
                            • {{ $vv->conditionsPillLabel() }}
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        <button
                            type="button"
                            @click="editingVendorVoucherId = (editingVendorVoucherId === {{ $vv->id }} ? null : {{ $vv->id }})"
                            class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1.5 font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] cursor-pointer"
                        >
                            Edit Time Period &amp; Scope
                        </button>
                        <form method="POST" action="{{ route('vendor.vouchers.toggle', $vv->id) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-[var(--border)] bg-white px-3 py-1.5 font-bold {{ $vv->is_active ? 'text-amber-800' : 'text-emerald-800' }} cursor-pointer">
                                {{ $vv->is_active ? 'Pause' : 'Activate' }}
                            </button>
                        </form>
                        <form method="POST" action="{{ route('vendor.vouchers.destroy', $vv->id) }}" onsubmit="return confirm('Delete Voucher {{ $vv->code }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 font-bold text-rose-800 hover:bg-rose-100 cursor-pointer">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Inline Edit Form -->
                <div x-show="editingVendorVoucherId === {{ $vv->id }}" x-cloak class="pt-2 border-t border-[var(--border)]" x-data="{ editScope: @js($vv->branch_scope) }">
                    <form method="POST" action="{{ route('vendor.vouchers.update', $vv->id) }}" class="space-y-3 rounded-lg border border-[var(--border)] bg-[var(--mint-50)] p-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <label class="block font-bold mb-1">Code *</label>
                                <input type="text" name="code" value="{{ $vv->code }}" required class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 font-mono font-bold uppercase">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block font-bold mb-1">Title *</label>
                                <input type="text" name="title" value="{{ $vv->title }}" required class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 font-semibold">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Voucher Type *</label>
                                <select name="voucher_type" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                                    <option value="LIMITED_TIME" {{ $vv->voucher_type === 'LIMITED_TIME' ? 'selected' : '' }}>Limited Time</option>
                                    <option value="USE_ONCE" {{ $vv->voucher_type === 'USE_ONCE' ? 'selected' : '' }}>Use Once</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            <div>
                                <label class="block font-bold mb-1">Discount Type *</label>
                                <select name="discount_type" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                                    <option value="FIXED" {{ $vv->discount_type === 'FIXED' ? 'selected' : '' }}>Fixed (৳)</option>
                                    <option value="PERCENTAGE" {{ $vv->discount_type === 'PERCENTAGE' ? 'selected' : '' }}>Percentage (%)</option>
                                    <option value="FREE_COURIER" {{ $vv->discount_type === 'FREE_COURIER' ? 'selected' : '' }}>Free Courier</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Value</label>
                                <input type="number" step="0.01" min="0" name="discount_value" value="{{ (float) $vv->discount_value }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Min. Spend (৳)</label>
                                <input type="number" step="1" min="0" name="min_order_amount" value="{{ (float) $vv->min_order_amount }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Max Cap (৳)</label>
                                <input type="number" step="1" min="0" name="max_discount_amount" value="{{ $vv->max_discount_amount !== null ? (float) $vv->max_discount_amount : '' }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Payment Rule</label>
                                <select name="required_payment_method" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                                    <option value="">Any Method</option>
                                    @foreach(['BKASH' => 'bKash Only', 'NAGAD' => 'Nagad Only', 'CARD' => 'Card Only', 'SSLCOMMERZ' => 'SSLCommerz Only', 'CASH' => 'Cash Only'] as $pmKey => $pmLbl)
                                        <option value="{{ $pmKey }}" {{ $vv->required_payment_method === $pmKey ? 'selected' : '' }}>{{ $pmLbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold mb-1">Starts At</label>
                                <input type="datetime-local" name="starts_at" value="{{ $vv->starts_at ? $vv->starts_at->format('Y-m-d\TH:i') : '' }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Ends At</label>
                                <input type="datetime-local" name="expires_at" value="{{ $vv->expires_at ? $vv->expires_at->format('Y-m-d\TH:i') : '' }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold mb-1">Scheduled Days of Week</label>
                            <div class="flex flex-wrap gap-1.5">
                                @php $curVDays = $vv->normalizedDaysOfWeek(); @endphp
                                @foreach($vDayShort as $dIdx => $dName)
                                    <label class="inline-flex items-center gap-1 rounded border border-[var(--border)] bg-white px-2 py-1 cursor-pointer">
                                        <input type="checkbox" name="valid_days_of_week[]" value="{{ $dIdx }}" {{ in_array($dIdx, $curVDays, true) ? 'checked' : '' }} class="accent-[#244229]">
                                        <span>{{ $dName }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="block font-bold">Branch Scope</label>
                            <div class="flex flex-wrap gap-4">
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="branch_scope" value="ALL_BRANCHES" x-model="editScope" class="accent-[#244229]">
                                    <span>All My Branches</span>
                                </label>
                                <label class="inline-flex items-center gap-1.5 cursor-pointer">
                                    <input type="radio" name="branch_scope" value="SPECIFIC_BRANCHES" x-model="editScope" class="accent-[#244229]">
                                    <span>Specific Branch(es)</span>
                                </label>
                            </div>
                            <div x-show="editScope === 'SPECIFIC_BRANCHES'" x-cloak class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 pt-1">
                                @foreach($branches as $br)
                                    <label class="flex items-center gap-2 rounded border border-[var(--border)] bg-white px-2.5 py-1.5 cursor-pointer">
                                        <input type="checkbox" name="shop_ids[]" value="{{ $br->id }}" {{ in_array($br->id, $vvShopIds, true) ? 'checked' : '' }} class="accent-[#244229]">
                                        <span class="truncate">{{ $br->display_branch_tag ?: $br->name }} ({{ $br->area }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editingVendorVoucherId = null" class="rounded-md border border-[var(--border)] bg-white px-3 py-1.5 font-semibold">Cancel</button>
                            <button type="submit" class="rounded-md bg-[var(--accent-dark)] px-4 py-1.5 font-bold text-white cursor-pointer">Save Voucher</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-6 text-center text-[var(--muted-foreground)]">
                You have not created any shop vouchers yet. Click "+ Create Voucher" above to publish one.
            </div>
        @endforelse
    </div>

    <!-- Informational Panel: Active Featured Vouchers on Meramot -->
    @if($featuredForVendor->isNotEmpty())
        <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-50)]/75 p-4 space-y-2.5">
            <h3 class="text-xs font-bold uppercase tracking-wider text-[var(--accent-dark)]">Active Featured Vouchers Across Meramot</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-2.5">
                @foreach($featuredForVendor as $pfv)
                    @php
                        $excludedMyBranches = $pfv->excludedShops->whereIn('id', $vendorBranchIds);
                    @endphp
                    <div class="rounded-md border border-[var(--border)] bg-white p-3 flex items-center justify-between gap-2">
                        <div class="min-w-0">
                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2 py-0.5 text-[10px] font-bold text-rose-800">
                                <i class="fa-solid fa-ticket text-[8px] text-rose-600"></i>
                                <span>{{ $pfv->advertisedBadgeText() }}</span>
                            </span>
                            <p class="font-semibold text-[var(--foreground)] mt-1 truncate">{{ $pfv->title }}</p>
                        </div>
                        <span class="shrink-0 rounded px-2 py-0.5 text-[10px] font-bold {{ $excludedMyBranches->count() === count($vendorBranchIds) ? 'bg-slate-100 text-slate-600' : 'bg-emerald-50 text-emerald-800' }}">
                            {{ $excludedMyBranches->isEmpty() ? 'Active on your branches' : ($excludedMyBranches->count() === count($vendorBranchIds) ? 'Excluded' : 'Partial branches') }}
                        </span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

</div>

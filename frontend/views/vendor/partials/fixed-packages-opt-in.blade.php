@php
    $pkgList = ($availablePackages ?? collect())->values();
    $groupedPackages = $pkgList->groupBy('device_category');

    $pivotIds = ($targetShop && $targetShop->relationLoaded('servicePackages'))
        ? $targetShop->servicePackages->pluck('id')->map(fn ($id) => (int) $id)->all()
        : [];
    $profileIds = is_array($profile?->offered_package_ids)
        ? array_map('intval', $profile->offered_package_ids)
        : [];

    $initialPackageIds = old('service_package_ids') !== null
        ? array_values(array_unique(array_filter(array_map('intval', (array) old('service_package_ids')), fn ($id) => $id > 0)))
        : (!empty($pivotIds) ? $pivotIds : $profileIds);

    $oldProvides = old('provides_fixed_packages');
    if ($oldProvides !== null) {
        $initialProvides = in_array((string) $oldProvides, ['1', 'true', 'on', 'yes'], true) ? '1' : '0';
    } elseif ($profile && $profile->provides_fixed_packages !== null) {
        $initialProvides = ($profile->provides_fixed_packages || !empty($initialPackageIds)) ? '1' : '0';
    } else {
        $initialProvides = !empty($initialPackageIds) ? '1' : '0';
    }

    $allPkgIds = $pkgList->pluck('id')->map(fn ($id) => (int) $id)->all();
@endphp

@if($pkgList->isNotEmpty())
<div
    class="rounded-3xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-5 sm:p-6 space-y-4"
    x-data="{
        provides: @js($initialProvides),
        selectedIds: @js(array_values(array_map('intval', $initialPackageIds))),
        allIds: @js($allPkgIds),
        isSelected(id) {
            return this.selectedIds.includes(Number(id));
        },
        togglePackage(id) {
            const num = Number(id);
            if (this.selectedIds.includes(num)) {
                this.selectedIds = this.selectedIds.filter(i => i !== num);
            } else {
                this.selectedIds.push(num);
            }
        },
        selectAll() {
            this.provides = '1';
            this.selectedIds = [...this.allIds];
        },
        clearAll() {
            this.selectedIds = [];
        }
    }"
>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div class="space-y-1 max-w-2xl">
            <div class="inline-flex items-center gap-1.5 rounded-full border border-[#b9d3b6] bg-white px-2.5 py-0.5 text-[11px] font-bold text-[#244229]">
                <i class="fa-solid fa-box-open text-[10px]"></i>
                <span>Platform Fixed-Price Repair Packages</span>
            </div>
            <h3 class="text-base sm:text-lg font-bold text-[#203126]">
                Can your workshop provide model-independent Fixed-Price Repair Packages?
            </h3>
            <p class="text-xs text-[#4a6650] leading-relaxed">
                These flat-rate software, diagnostics, OS, thermal, and maintenance services do not depend on a customer's specific device model. Opted-in workshops are auto-matched for local Fixed Package orders and have selected packages listed automatically on their shop profile's <strong>Services</strong> tab.
            </p>
        </div>
        <span
            class="shrink-0 rounded-full border border-[var(--border)] bg-white px-3 py-1 text-xs font-bold text-[#244229]"
            x-text="provides === '1' ? (selectedIds.length + ' selected') : 'Not offered'"
        ></span>
    </div>

    <div class="grid gap-3 sm:grid-cols-2">
        <label
            :class="provides === '1' ? 'border-[#244229] bg-white ring-2 ring-[#244229]/20' : 'border-[var(--border)] bg-white/80 hover:bg-white'"
            class="flex items-start gap-3 rounded-2xl border p-3.5 cursor-pointer transition"
        >
            <input
                type="radio"
                name="provides_fixed_packages"
                value="1"
                x-model="provides"
                class="mt-1 h-4 w-4 accent-[#244229]"
            >
            <div>
                <p class="text-sm font-bold text-[#203126]">Yes, enable selected Fixed-Price Packages</p>
                <p class="text-xs text-[#58725f] mt-0.5">Auto-match local customer bookings &amp; publish on our Services tab.</p>
            </div>
        </label>

        <label
            :class="provides === '0' ? 'border-[#244229] bg-white ring-2 ring-[#244229]/20' : 'border-[var(--border)] bg-white/80 hover:bg-white'"
            class="flex items-start gap-3 rounded-2xl border p-3.5 cursor-pointer transition"
        >
            <input
                type="radio"
                name="provides_fixed_packages"
                value="0"
                x-model="provides"
                class="mt-1 h-4 w-4 accent-[#244229]"
            >
            <div>
                <p class="text-sm font-bold text-[#203126]">No, custom quotes &amp; hardware specialties only</p>
                <p class="text-xs text-[#58725f] mt-0.5">Opt out of platform Fixed Package auto-matching for now.</p>
            </div>
        </label>
    </div>

    @error('service_package_ids')
        <div class="rounded-2xl border border-rose-300 bg-rose-50 px-3.5 py-2 text-xs font-semibold text-rose-800">
            {{ $message }}
        </div>
    @enderror

    <div x-show="provides === '1'" x-cloak class="space-y-4 pt-1">
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] pb-2.5">
            <span class="text-xs font-semibold text-[#3f5946]">
                Select the specific Fixed-Price Repair Packages your technicians can fulfill:
            </span>
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="selectAll()"
                    class="rounded-full border border-[#244229]/30 bg-white px-3 py-1 text-[11px] font-bold text-[#244229] hover:bg-[#edf6ea] transition"
                >
                    Select All ({{ $pkgList->count() }})
                </button>
                <button
                    type="button"
                    @click="clearAll()"
                    class="rounded-full border border-[var(--border)] bg-white px-3 py-1 text-[11px] font-semibold text-[#58725f] hover:text-[#203126] transition"
                >
                    Clear
                </button>
            </div>
        </div>

        <div class="space-y-4">
            @foreach($groupedPackages as $catLabel => $catPackages)
                <div class="space-y-2">
                    <div class="flex items-center justify-between">
                        <span class="text-[11px] font-bold uppercase tracking-wider text-[#3f5946]">
                            {{ $catLabel }}
                        </span>
                        <span class="text-[11px] text-[#58725f]">{{ $catPackages->count() }} {{ \Illuminate\Support\Str::plural('package', $catPackages->count()) }}</span>
                    </div>

                    <div class="grid gap-2 sm:grid-cols-2">
                        @foreach($catPackages as $pkg)
                            <label
                                :class="isSelected({{ $pkg->id }}) ? 'border-[#244229] bg-white shadow-sm' : 'border-[var(--border)] bg-white/75 hover:bg-white'"
                                class="flex items-start gap-2.5 rounded-2xl border p-3 cursor-pointer transition"
                            >
                                <input
                                    type="checkbox"
                                    name="service_package_ids[]"
                                    value="{{ $pkg->id }}"
                                    :checked="isSelected({{ $pkg->id }})"
                                    :disabled="provides !== '1'"
                                    @change="togglePackage({{ $pkg->id }})"
                                    class="mt-0.5 h-4 w-4 rounded accent-[#244229] shrink-0"
                                >
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <span class="text-xs font-bold text-[#203126] leading-snug">{{ $pkg->title }}</span>
                                        <span class="shrink-0 text-xs font-extrabold text-[#163a24]">৳{{ number_format((float) $pkg->fixed_price, 0) }}</span>
                                    </div>
                                    <p class="mt-0.5 text-[11px] text-[#58725f]">
                                        ~{{ (int) $pkg->estimated_turnaround_hours }}h turnaround &bull; {{ (int) $pkg->warranty_months }}-mo warranty
                                    </p>
                                </div>
                            </label>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endif

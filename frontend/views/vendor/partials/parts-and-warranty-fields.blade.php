@php
    $partFieldBg = $partFieldBg ?? 'bg-[var(--card)]';
    $containerBg = $containerBg ?? 'bg-[var(--mint-100)]/60';
@endphp

<!-- Separate Section: Itemized Parts Pricing & Per-Part Warranty Breakdown -->
<div class="rounded-lg border border-[var(--border)] {{ $containerBg }} p-3 sm:p-3.5 space-y-2.5">
    <div class="flex items-center justify-between gap-2">
        <div class="min-w-0">
            <span class="block text-[10px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">
                Parts Pricing &amp; Warranty Breakdown
            </span>
            <span class="block text-[10px] text-[var(--muted-foreground)]">
                Itemize each replacement component and configure its individual warranty coverage
            </span>
        </div>
        <button
            type="button"
            @click="addPart()"
            class="inline-flex items-center gap-1 rounded-md bg-[var(--accent-dark)] px-2.5 py-1 text-[11px] font-bold text-white shadow-sm hover:opacity-90 transition shrink-0"
            title="Add another part line item"
        >
            <i class="fa-solid fa-plus text-[10px]"></i>
            <span>Add Part</span>
        </button>
    </div>

    <div class="space-y-2">
        <template x-for="(part, idx) in parts" :key="idx">
            <div class="rounded-xl border border-[var(--border)]/60 {{ $partFieldBg }} p-2.5 space-y-2">
                <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                    <div class="flex items-center gap-2 min-w-0 flex-1">
                        <span
                            class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[var(--mint-200)] text-[11px] font-bold text-[var(--accent-dark)]"
                            x-text="(idx + 1) + '.'"
                        ></span>
                        <input
                            type="text"
                            :name="`parts_items[${idx}][name]`"
                            x-model="part.name"
                            placeholder="Part / Component name (e.g. OLED Display, Charging IC)"
                            class="w-full min-w-0 rounded-lg border border-[var(--border)] bg-white/80 dark:bg-[var(--mint-300)] px-2.5 py-1.5 text-xs text-[var(--foreground)] outline-none focus:ring-1 focus:ring-[var(--accent-dark)]"
                        >
                    </div>

                    <div class="flex items-center justify-between sm:justify-end gap-2 pl-8 sm:pl-0 shrink-0">
                        <div class="flex items-center rounded-lg border border-[var(--border)] bg-white/80 dark:bg-[var(--mint-300)] px-2 py-1.5 w-32 sm:w-28">
                            <span class="text-[11px] font-bold text-[var(--muted-foreground)] mr-1">৳</span>
                            <input
                                type="number"
                                step="0.01"
                                min="0"
                                :name="`parts_items[${idx}][cost]`"
                                x-model="part.cost"
                                onwheel="this.blur()"
                                placeholder="0"
                                aria-label="Part cost in BDT"
                                class="w-full min-w-0 bg-transparent text-xs font-bold text-[var(--foreground)] outline-none"
                            >
                        </div>
                        <button
                            type="button"
                            @click="removePart(idx)"
                            class="inline-flex h-7 w-7 items-center justify-center rounded-lg border border-[var(--border)] bg-[var(--mint-100)] text-[var(--muted-foreground)] hover:text-rose-700 hover:border-rose-300 transition shrink-0"
                            :title="parts.length > 1 ? 'Remove part row' : 'Clear part row'"
                            aria-label="Remove part row"
                        >
                            <i class="fa-solid fa-xmark text-xs"></i>
                        </button>
                    </div>
                </div>

                <!-- Per-Part Warranty Checkbox & Days Entry -->
                <div class="flex flex-wrap items-center justify-between gap-2 pl-8 pt-1.5 border-t border-[var(--border)]/40">
                    <input type="hidden" :name="`parts_items[${idx}][warranty_configured]`" value="1">
                    <label class="inline-flex items-center gap-2 cursor-pointer select-none min-w-0">
                        <input
                            type="checkbox"
                            :name="`parts_items[${idx}][has_warranty]`"
                            value="1"
                            x-model="part.has_warranty"
                            aria-label="Part warranty coverage"
                            class="h-4 w-4 rounded border-[var(--border)] text-[var(--accent-dark)] accent-[var(--accent-dark)] shrink-0"
                        >
                        <span class="text-[11px] font-bold text-[var(--foreground)]">Part Warranty</span>
                        <span
                            class="hidden sm:inline text-[10px] text-[var(--muted-foreground)]"
                            x-text="part.has_warranty ? 'Verified component warranty' : 'No warranty for this part'"
                        ></span>
                    </label>

                    <div class="flex items-center gap-1.5 shrink-0" x-show="part.has_warranty" x-cloak>
                        <label class="text-[10px] font-bold text-[var(--muted-foreground)] whitespace-nowrap">Days</label>
                        <input
                            type="number"
                            :name="`parts_items[${idx}][warranty_days]`"
                            x-model="part.warranty_days"
                            :disabled="!part.has_warranty"
                            step="1"
                            inputmode="numeric"
                            data-numeric-mode="integer"
                            onwheel="this.blur()"
                            min="1"
                            max="730"
                            aria-label="Part warranty days"
                            class="w-20 rounded-lg border border-[var(--border)] bg-white/90 dark:bg-[var(--mint-300)] px-2 py-1 text-xs font-bold text-[var(--foreground)] outline-none focus:ring-1 focus:ring-[var(--accent-dark)]"
                        >
                    </div>
                    <span
                        x-show="!part.has_warranty"
                        x-cloak
                        class="inline-flex items-center gap-1 rounded border border-[var(--border)] bg-[var(--mint-100)]/60 px-2 py-0.5 text-[10px] font-semibold text-[var(--muted-foreground)]"
                    >
                        No warranty
                    </span>
                </div>
            </div>
        </template>
    </div>

    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[var(--border)]/60 pt-2 text-[11px]">
        <div class="flex flex-wrap items-center gap-2">
            <span class="font-semibold text-[var(--muted-foreground)]">Parts Subtotal</span>
            <span class="inline-flex items-center gap-1 rounded border border-[var(--border)] bg-[var(--card)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]" x-text="warrantySummaryText"></span>
        </div>
        <span class="font-bold text-[var(--foreground)]">৳<span x-text="formatMoney(partsSubtotal)">0.00</span></span>
    </div>
    <input type="hidden" name="parts_cost" :value="partsSubtotal.toFixed(2)">
</div>

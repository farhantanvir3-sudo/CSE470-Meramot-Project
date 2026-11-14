@include('vendor.partials.quote-builder-script')

@php
    $shopCollection = collect($shopOptions ?? []);
    $shopIds = $shopCollection->pluck('id')->map(fn ($id) => (int) $id)->all();
    $lockedShopIdStr = isset($lockedShopId) && $lockedShopId ? (string) $lockedShopId : '';
    $initialShopIdStr = $lockedShopIdStr !== '' ? $lockedShopIdStr : (string) ($shopIds[0] ?? '');

    $existingBidsByShop = ($req->bids ?? collect())
        ->filter(fn ($b) => in_array((int) $b->shop_id, $shopIds, true))
        ->mapWithKeys(fn ($b) => [(string) $b->shop_id => $b->toFrontendPayload()])
        ->all();

    $shopNamesMap = $shopCollection->mapWithKeys(function ($s) {
        $label = $s->name . ($s->display_branch_tag ? ' [' . $s->display_branch_tag . ']' : '');
        return [(string) $s->id => $label];
    })->all();
@endphp

<div
    x-data="window.meramotBidQuoteForm({
        initialShopId: @js($initialShopIdStr),
        existingBidsByShop: @js($existingBidsByShop)
    })"
    @meramot-prefill-quote-parts.window="if (Number($event.detail?.requestId) === {{ (int) $req->id }}) prefillSuggestedParts($event.detail?.parts || [])"
    class="space-y-3 text-xs"
>
    <!-- Active Branch Selector (shown when vendor operates multiple branches and dashboard isn't filtered to one) -->
    @if($lockedShopIdStr === '' && $shopCollection->count() > 1)
        <div>
            <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-1">Quoting From Branch</label>
            <select
                x-model="selectedShopId"
                @change="syncForShop(false)"
                class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] p-2 text-xs font-semibold outline-none"
            >
                @foreach($shopCollection as $vShop)
                    <option value="{{ $vShop->id }}">
                        {{ $vShop->name }}{{ $vShop->display_branch_tag ? ' [' . $vShop->display_branch_tag . ']' : '' }}
                    </option>
                @endforeach
            </select>
        </div>
    @endif

    <!-- View Mode: Pre-submitted Itemized Quote Summary + Edit Toggle -->
    <div x-show="existingBid && !editing" x-cloak class="rounded-2xl border border-emerald-600/40 bg-[var(--mint-100)]/70 p-3.5 space-y-2.5">
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div>
                <span class="inline-flex items-center gap-1 rounded-full bg-emerald-800 px-2.5 py-0.5 text-[10px] font-black uppercase text-white">
                    <i class="fa-solid fa-check"></i> Submitted Estimate
                </span>
                <p class="mt-1 text-[11px] font-semibold text-[var(--muted-foreground)]" x-text="(@js($shopNamesMap)[selectedShopId] || 'Your Branch') + ' • ' + estimatedDays + 'd turnaround'"></p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <span class="text-base font-black text-[var(--accent-dark)]">৳<span x-text="formatMoney(existingBid ? existingBid.proposed_cost : 0)">0.00</span></span>
                <button
                    type="button"
                    @click="startEdit()"
                    class="rounded-full border border-[var(--accent-dark)] bg-white px-3 py-1 text-[11px] font-bold text-[var(--accent-dark)] hover:bg-[var(--accent-dark)] hover:text-white transition"
                >
                    <i class="fa-solid fa-pen-to-square mr-1"></i>Edit Quote
                </button>
                @if(in_array(strtoupper((string) ($req->status ?? 'BIDDING')), ['PENDING', 'BIDDING'], true))
                    <form
                        x-show="existingBid && existingBid.id"
                        method="POST"
                        :action="existingBid ? ('/vendor/bids/' + existingBid.id + '/withdraw') : '#'"
                        onsubmit="return confirm('Withdraw your diagnostic estimate from this ticket?');"
                        class="inline"
                    >
                        @csrf
                        <button type="submit" class="rounded-full border border-rose-300 bg-rose-50 px-3 py-1 text-[11px] font-bold text-rose-800 hover:bg-rose-100 transition">
                            Withdraw
                        </button>
                    </form>
                @endif
            </div>
        </div>

        <div class="rounded-xl border border-[var(--border)]/60 bg-[var(--card)] p-2.5 space-y-1.5">
            <span class="block text-[10px] font-black uppercase tracking-wider text-[var(--accent-dark)]">Parts Breakdown</span>
            <template x-for="(part, idx) in (existingBid ? existingBid.parts_items : [])" :key="idx">
                <div class="flex flex-wrap items-center justify-between gap-x-2 gap-y-1 text-[11px]">
                    <div class="flex flex-wrap items-center gap-1.5 min-w-0 flex-1">
                        <span class="text-[var(--foreground)] break-words" x-text="(idx + 1) + '. ' + part.name"></span>
                        <span
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold shrink-0"
                            :class="(part.has_warranty && Number(part.warranty_days) > 0) ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-[var(--mint-100)] text-[var(--muted-foreground)] border border-[var(--border)]'"
                        >
                            <i :class="(part.has_warranty && Number(part.warranty_days) > 0) ? 'fa-solid fa-shield-halved text-[9px] text-emerald-700' : 'fa-regular fa-circle-xmark text-[9px]'"></i>
                            <span x-text="(part.has_warranty && Number(part.warranty_days) > 0) ? (part.warranty_days + 'd warranty') : 'No warranty'"></span>
                        </span>
                    </div>
                    <span class="font-bold text-[var(--foreground)] shrink-0">৳<span x-text="formatMoney(part.cost)">0.00</span></span>
                </div>
            </template>
            <div x-show="!(existingBid && existingBid.parts_items && existingBid.parts_items.length)" class="text-[11px] text-[var(--muted-foreground)]">
                No replacement parts itemized (labor-only service).
            </div>
            <div class="flex items-center justify-between border-t border-[var(--border)]/50 pt-1.5 text-[11px]">
                <span class="text-[var(--muted-foreground)]">Parts Subtotal</span>
                <span class="font-bold text-[var(--foreground)]">৳<span x-text="formatMoney(existingBid ? existingBid.parts_cost : 0)">0.00</span></span>
            </div>
            <div class="flex items-center justify-between text-[11px]">
                <span class="text-[var(--muted-foreground)]">Bench Labor</span>
                <span class="font-bold text-[var(--foreground)]">৳<span x-text="formatMoney(existingBid ? existingBid.labor_cost : 0)">0.00</span></span>
            </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px]">
            <span
                class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 font-bold"
                :class="(existingBid && existingBid.has_warranty) ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-[var(--card)] text-[var(--muted-foreground)] border border-[var(--border)]'"
            >
                <i :class="(existingBid && existingBid.has_warranty) ? 'fa-solid fa-shield-halved text-emerald-700' : 'fa-regular fa-circle-xmark'"></i>
                <span x-text="existingBid ? (existingBid.warranty_summary || (existingBid.has_warranty ? (existingBid.warranty_days + '-Day Warranty Included') : 'No Parts Warranty')) : ''"></span>
            </span>
            <span x-show="existingBid && existingBid.technician_note" class="text-[var(--muted-foreground)] italic truncate max-w-full" x-text="existingBid ? existingBid.technician_note : ''"></span>
        </div>
    </div>

    <!-- Create / Edit Itemized Estimate Form -->
    <form
        x-show="!existingBid || editing"
        method="POST"
        action="{{ route('vendor.requests.bid', $req->id) }}"
        class="space-y-3"
    >
        @csrf
        <input type="hidden" name="shop_id" :value="selectedShopId">

        @include('vendor.partials.parts-and-warranty-fields')

        <div class="grid grid-cols-2 gap-2.5">
            <div>
                <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-1">Bench Labor (৳) *</label>
                <input
                    type="number"
                    step="0.01"
                    min="0"
                    name="labor_cost"
                    x-model="laborCost"
                    onwheel="this.blur()"
                    placeholder="900"
                    class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] px-3 py-2 text-xs font-bold text-[var(--foreground)] outline-none"
                >
            </div>
            <div>
                <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-1">Turnaround (Days) *</label>
                <input
                    type="number"
                    name="estimated_days"
                    x-model="estimatedDays"
                    step="1"
                    inputmode="numeric"
                    data-numeric-mode="integer"
                    onwheel="this.blur()"
                    min="1"
                    max="60"
                    class="w-full rounded-xl border border-[var(--border)] bg-[var(--mint-300)] px-3 py-2 text-xs font-bold text-[var(--foreground)] outline-none"
                    required
                >
            </div>
        </div>

        <div>
            <label class="block text-[10px] font-bold text-[var(--muted-foreground)] mb-1">Technician Diagnostic Note</label>
            <input
                type="text"
                name="technician_note"
                x-model="technicianNote"
                placeholder="e.g. Genuine OEM assembly, thermal paste refresh & bench stress test"
                class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-xs outline-none"
            >
        </div>

        <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-[var(--border)]/70 bg-[var(--mint-100)]/70 px-3 py-2">
            <div>
                <span class="block text-[10px] font-bold uppercase text-[var(--muted-foreground)]">Total Quote Estimate</span>
                <span class="text-sm font-black text-[var(--accent-dark)]">৳<span x-text="formatMoney(totalEstimate)">0.00</span></span>
            </div>
            <div class="flex items-center gap-2">
                <button
                    x-show="existingBid"
                    x-cloak
                    type="button"
                    @click="cancelEdit()"
                    class="rounded-full border border-[var(--border)] bg-[var(--card)] px-3.5 py-2 text-[11px] font-bold text-[var(--foreground)] hover:opacity-80 transition"
                >
                    Cancel
                </button>
                <button
                    type="submit"
                    class="rounded-full bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-white shadow hover:opacity-90 transition"
                    x-text="existingBid ? 'Update Itemized Quote' : 'Submit Itemized Estimate →'"
                ></button>
            </div>
        </div>
    </form>
</div>

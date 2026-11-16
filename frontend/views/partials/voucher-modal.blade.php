@php
    $vModalApplyUrl = $applyActionUrl ?? route('cart.voucher.apply');
    $vModalRemoveUrl = $removeActionUrl ?? route('cart.voucher.remove');
    $vModalAppliedCode = $appliedCode ?? null;
    $vModalAppliedLabel = $appliedLabel ?? null;
    $vModalAppliedDiscount = (float) ($appliedDiscount ?? 0);
    $vModalCatalog = is_array($availableVouchers ?? null) ? $availableVouchers : [];
    $vModalEligibleCount = collect($vModalCatalog)->where('eligible', true)->count();
@endphp

<div
    x-data="{
        voucherModalOpen: false,
        manualVoucherCode: '',
        voucherSubmitting: false,
        voucherError: null,
        catalog: @js($vModalCatalog),
        currentAppliedCode: @js($vModalAppliedCode ?? ''),
        openVoucherDialog() {
            this.voucherError = null;
            this.manualVoucherCode = '';
            this.voucherModalOpen = true;
        },
        async submitVoucherCode(codeToApply) {
            const cleaned = String(codeToApply || '').trim().toUpperCase();
            if (!cleaned || this.voucherSubmitting) return;
            this.voucherSubmitting = true;
            this.voucherError = null;

            const payload = { voucher_code: cleaned };
            if (typeof this.fulfillmentMode !== 'undefined') {
                payload.fulfillment_mode = this.fulfillmentMode;
            }
            if (typeof this.deliveryType !== 'undefined') {
                payload.delivery_type = this.deliveryType;
            }
            if (typeof this.paymentMethod !== 'undefined') {
                payload.payment_method = this.paymentMethod;
            } else if (typeof this.selectedChannel !== 'undefined') {
                payload.payment_method = this.selectedChannel;
            }

            try {
                const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || @js(csrf_token());
                const res = await fetch(@js($vModalApplyUrl), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                    },
                    body: JSON.stringify(payload),
                });
                const data = await res.json().catch(() => ({}));
                if (!res.ok || !data.success) {
                    this.voucherError = data.message || (data.errors ? Object.values(data.errors).flat().join(' ') : 'Unable to apply this voucher.');
                    this.voucherSubmitting = false;
                    return;
                }

                if (data.required_payment_method) {
                    try {
                        sessionStorage.setItem('meramot_auto_payment_method', data.required_payment_method);
                    } catch {}
                }
                window.location.reload();
            } catch (err) {
                this.voucherError = 'Network error while verifying voucher code. Please try again.';
                this.voucherSubmitting = false;
            }
        }
    }"
    @keydown.escape.window="if (voucherModalOpen) voucherModalOpen = false"
    class="text-xs"
>
    <!-- Checkout Voucher Trigger / Active Single-Voucher Box -->
    <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/75 p-3.5 space-y-2">
        @if(!empty($vModalAppliedCode))
            <div class="flex items-center justify-between gap-2">
                <span class="font-bold text-[var(--foreground)] flex items-center gap-1.5">
                    <i class="fa-solid fa-ticket text-[var(--accent-dark)]"></i>
                    <span>Voucher Applied (1 max)</span>
                </span>
                <div class="flex items-center gap-2.5">
                    <button
                        type="button"
                        data-open-voucher-modal
                        @click="openVoucherDialog()"
                        class="text-[11px] font-bold text-[var(--accent-dark)] hover:underline cursor-pointer"
                    >
                        Change
                    </button>
                    <form method="POST" action="{{ $vModalRemoveUrl }}" class="inline" data-no-softnav="true">
                        @csrf
                        <button type="submit" data-remove-voucher-btn class="text-[11px] font-bold text-rose-700 hover:underline cursor-pointer">
                            Remove
                        </button>
                    </form>
                </div>
            </div>

            <div class="rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-2 flex items-center justify-between gap-2 text-emerald-950" data-applied-voucher-summary>
                <div class="min-w-0">
                    <p class="font-mono font-bold text-xs flex items-center gap-1.5">
                        <i class="fa-solid fa-tag text-emerald-700 text-[10px]"></i>
                        <span>{{ $vModalAppliedCode }}</span>
                    </p>
                    <p class="text-[10px] text-emerald-800 truncate">{{ $vModalAppliedLabel ?? 'Voucher discount active' }}</p>
                </div>
                <span class="font-bold text-xs text-emerald-800 shrink-0">
                    -৳<span x-text="typeof activeDiscount !== 'undefined' ? activeDiscount.toLocaleString() : @js(number_format($vModalAppliedDiscount, 0))">{{ number_format($vModalAppliedDiscount, 0) }}</span>
                </span>
            </div>
        @else
            <button
                type="button"
                data-open-voucher-modal
                @click="openVoucherDialog()"
                class="w-full rounded-lg border border-[var(--accent-dark)]/35 bg-[var(--card)] hover:bg-[var(--mint-50)] px-3.5 py-2.5 flex items-center justify-between gap-2 transition shadow-xs cursor-pointer"
            >
                <span class="flex items-center gap-2 font-bold text-[var(--foreground)]">
                    <span class="flex h-6 w-6 items-center justify-center rounded bg-[var(--mint-100)] text-[var(--accent-dark)]">
                        <i class="fa-solid fa-ticket text-xs"></i>
                    </span>
                    <span data-i18n="apply_a_voucher">Apply a voucher</span>
                </span>
                <span class="flex items-center gap-1.5 text-[11px] font-semibold text-[var(--accent-dark)] shrink-0">
                    @if(count($vModalCatalog) > 0)
                        <span class="rounded-full bg-[var(--mint-200)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                            {{ $vModalEligibleCount > 0 ? $vModalEligibleCount . ' available' : count($vModalCatalog) . ' listed' }}
                        </span>
                    @endif
                    <i class="fa-solid fa-chevron-right text-[10px]"></i>
                </span>
            </button>
        @endif
    </div>

    <!-- Foodpanda-Inspired "Apply a voucher" Popup Dialog -->
    <template x-teleport="body">
        <div
            x-show="voucherModalOpen"
            x-cloak
            data-voucher-modal
            role="dialog"
            aria-modal="true"
            aria-label="Apply a voucher"
            class="fixed inset-0 z-[120] flex items-end sm:items-center justify-center bg-black/55 backdrop-blur-[1px] p-0 sm:p-4"
            @click.self="voucherModalOpen = false"
        >
            <div class="w-full sm:max-w-md max-h-[88vh] flex flex-col rounded-t-2xl sm:rounded-xl border border-[var(--border)] bg-[var(--card)] shadow-2xl overflow-hidden">
                <!-- Modal Header -->
                <div class="flex items-center justify-between gap-3 border-b border-[var(--border)] bg-white px-4 py-3.5 shrink-0">
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            @click="voucherModalOpen = false"
                            aria-label="Close voucher dialog"
                            class="flex h-8 w-8 items-center justify-center rounded-full text-[var(--foreground)] hover:bg-[var(--mint-100)] transition cursor-pointer"
                        >
                            <i class="fa-solid fa-arrow-left text-sm"></i>
                        </button>
                        <h3 class="text-base font-bold text-[var(--foreground)]">Apply a voucher</h3>
                    </div>
                    <span class="text-[10px] font-semibold text-[var(--muted-foreground)]">1 voucher per order</span>
                </div>

                <!-- Manual Code Input + Full-Width Apply Button Below -->
                <div class="bg-white px-4 py-4 border-b border-[var(--border)] space-y-3 shrink-0">
                    <form @submit.prevent="submitVoucherCode(manualVoucherCode)" class="space-y-3">
                        <div class="relative">
                            <label class="absolute -top-2 left-3 bg-white px-1 text-[10px] font-semibold text-[var(--muted-foreground)]">
                                Enter a voucher code
                            </label>
                            <input
                                type="text"
                                x-model="manualVoucherCode"
                                data-voucher-code-input
                                maxlength="32"
                                placeholder="e.g. FREECOURIER, BATTERY15"
                                class="w-full rounded-lg border border-[var(--border)] bg-white px-3.5 py-3 text-xs font-mono font-bold uppercase tracking-wide text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)] focus:ring-1 focus:ring-[var(--accent-dark)]"
                            >
                        </div>

                        <button
                            type="submit"
                            data-voucher-manual-submit
                            :disabled="voucherSubmitting || !manualVoucherCode.trim()"
                            class="w-full rounded-lg bg-[var(--accent-dark)] px-4 py-3 text-xs font-bold text-[var(--accent-foreground)] shadow-sm transition hover:opacity-90 disabled:opacity-50 cursor-pointer"
                        >
                            <span x-text="voucherSubmitting ? 'Applying...' : 'Apply'">Apply</span>
                        </button>
                    </form>

                    <template x-if="voucherError">
                        <div data-voucher-modal-error class="rounded-lg border border-rose-300 bg-rose-50 px-3 py-2 text-[11px] font-semibold text-rose-900 flex items-start gap-2">
                            <i class="fa-solid fa-circle-exclamation text-rose-600 mt-0.5 shrink-0"></i>
                            <span x-text="voucherError"></span>
                        </div>
                    </template>
                </div>

                <!-- Scrollable "Select a voucher" Ticket List -->
                <div class="flex-1 overflow-y-auto bg-[var(--mint-50)]/70 px-4 py-4 space-y-3">
                    <h4 class="text-sm font-bold text-[var(--foreground)]">Select a voucher</h4>

                    @if(empty($vModalCatalog))
                        <div class="rounded-lg border border-[var(--border)] bg-white p-6 text-center text-xs text-[var(--muted-foreground)]">
                            No active vouchers are currently listed for this workshop. Enter a code above if you have one.
                        </div>
                    @else
                        <div class="space-y-3">
                            <template x-for="v in catalog" :key="v.code">
                                <div
                                    :data-voucher-ticket="v.code"
                                    class="rounded-xl border bg-white shadow-xs overflow-hidden transition"
                                    :class="currentAppliedCode === v.code ? 'border-emerald-500 ring-1 ring-emerald-500/30' : (v.eligible ? 'border-[var(--border)]' : 'border-[var(--border)]/80 opacity-80')"
                                >
                                    <!-- Conditional Status Strip (Foodpanda style) -->
                                    <template x-if="v.status_banner">
                                        <div
                                            class="px-3.5 py-1.5 text-[11px] font-bold border-b"
                                            :class="v.status_tone === 'warning' ? 'bg-rose-50 text-rose-900 border-rose-200/70' : 'bg-emerald-50/80 text-emerald-900 border-emerald-200/70'"
                                            x-text="v.status_banner"
                                        ></div>
                                    </template>

                                    <div class="p-3.5 space-y-2.5">
                                        <!-- Ticket Icon + Title + Bold Discount & Code -->
                                        <div class="flex items-start gap-3">
                                            <div class="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-[var(--accent-dark)] text-[var(--accent-foreground)]">
                                                <i class="fa-solid fa-ticket text-sm"></i>
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <p class="text-xs font-medium text-[var(--foreground)] leading-snug" x-text="v.title"></p>
                                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                                    <span class="text-sm font-bold text-[var(--foreground)]" x-text="v.discount_highlight"></span>
                                                    <i class="fa-regular fa-circle-question text-[11px] text-[var(--muted-foreground)]"></i>
                                                    <span class="font-mono text-xs font-semibold uppercase tracking-wide text-[var(--muted-foreground)]" x-text="v.code"></span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Perforated Divider + Conditions Pill + Apply Action -->
                                        <div class="pt-2.5 border-t border-dashed border-[var(--border)] flex items-center justify-between gap-2">
                                            <span
                                                class="inline-block rounded-full border border-[var(--border)] bg-[var(--mint-50)] px-2.5 py-0.5 text-[10px] font-medium text-[var(--muted-foreground)] truncate max-w-[74%]"
                                                x-text="v.conditions_pill"
                                            ></span>

                                            <template x-if="currentAppliedCode === v.code">
                                                <span class="text-xs font-bold text-emerald-700 shrink-0">Applied ✓</span>
                                            </template>
                                            <template x-if="currentAppliedCode !== v.code">
                                                <button
                                                    type="button"
                                                    :data-select-voucher-code="v.code"
                                                    :disabled="!v.eligible || voucherSubmitting"
                                                    @click="submitVoucherCode(v.code)"
                                                    class="text-xs font-bold shrink-0 transition"
                                                    :class="v.eligible ? 'text-[var(--accent-dark)] hover:underline cursor-pointer' : 'text-[var(--muted-foreground)]/50 cursor-not-allowed'"
                                                >
                                                    Apply
                                                </button>
                                            </template>
                                        </div>
                                    </div>
                                </div>
                            </template>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </template>
</div>

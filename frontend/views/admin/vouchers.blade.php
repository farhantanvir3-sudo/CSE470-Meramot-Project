@extends('layouts.app')

@section('title', 'Featured Vouchers & Promo Stack | Admin Console')

@section('content')
@php
    $defaultFreeVoucher = $adminVouchers->firstWhere('is_system_default', true);
    $featuredVouchersList = $adminVouchers->where('is_system_default', false)->values();
    $currentHomepageVoucher = $stackHealth['current_homepage_voucher'] ?? null;
    $nextInLineVoucher = $stackHealth['next_in_line_voucher'] ?? null;
    $dayLabels = \App\Models\Voucher::DAY_SHORT_NAMES;
@endphp

<div class="mx-auto max-w-7xl px-3.5 py-4 sm:px-4 sm:py-6 md:px-6 space-y-5 text-xs" x-data="{ editingVoucherId: null, shopFilter: '' }">

    @include('admin.partials.nav')

    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-[var(--border)] pb-4">
        <div>
            <p class="text-[10px] font-bold uppercase tracking-[0.2em] text-[var(--accent-dark)]">Homepage Promo Stack &amp; Marketplace Offers</p>
            <h1 class="text-xl sm:text-2xl font-bold text-[var(--foreground)] mt-0.5">Featured Vouchers Governance</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">
                Manage the Homepage Featured Voucher stack, time periods, branch exclusions, and the First-Order Free Courier welcome offer.
            </p>
        </div>
        <a href="/" class="shrink-0 inline-flex items-center justify-center rounded-md border border-[var(--border)] bg-[var(--card)] px-4 py-2 text-xs font-bold text-[var(--foreground)] hover:bg-[var(--mint-100)] transition">
            Preview Homepage Tiles &rarr;
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-400 bg-emerald-50 px-4 py-3 font-bold text-emerald-950 flex items-center gap-2 shadow-xs">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 font-bold text-rose-900 flex items-center gap-2 shadow-xs">
            <i class="fa-solid fa-circle-exclamation text-rose-700"></i>
            <span>{{ session('error') }}</span>
        </div>
    @endif
    @if($errors->any())
        <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-rose-900 space-y-1 shadow-xs">
            @foreach($errors->all() as $err)
                <div class="font-semibold"><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    <!-- Stack Health Alert Banner -->
    @if(!empty($stackHealth['is_empty']))
        <div data-featured-voucher-empty-alert class="rounded-lg border border-rose-300 bg-rose-50 p-4 text-rose-950 flex items-start gap-3 shadow-xs">
            <i class="fa-solid fa-triangle-exclamation text-rose-600 text-base mt-0.5 shrink-0"></i>
            <div>
                <h2 class="font-bold text-sm">{{ $stackHealth['alert_title'] }}</h2>
                <p class="mt-0.5 leading-relaxed">{{ $stackHealth['alert_message'] }}</p>
            </div>
        </div>
    @elseif(!empty($stackHealth['is_low_stack']))
        <div data-featured-voucher-low-alert class="rounded-lg border border-amber-300 bg-amber-50 p-4 text-amber-950 flex items-start gap-3 shadow-xs">
            <i class="fa-solid fa-clock-rotate-left text-amber-700 text-base mt-0.5 shrink-0"></i>
            <div>
                <h2 class="font-bold text-sm">{{ $stackHealth['alert_title'] }}</h2>
                <p class="mt-0.5 leading-relaxed">{{ $stackHealth['alert_message'] }}</p>
            </div>
        </div>
    @else
        <div class="rounded-lg border border-emerald-300 bg-emerald-50/75 p-4 text-emerald-950 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
            <div class="flex items-start gap-2.5">
                <i class="fa-solid fa-layer-group text-emerald-700 text-sm mt-0.5 shrink-0"></i>
                <div>
                    <p class="font-bold">
                        Homepage Tile #2 Live Now:
                        <span class="font-mono">{{ $currentHomepageVoucher?->code }}</span> — {{ $currentHomepageVoucher?->title }}
                    </p>
                    <p class="text-[11px] text-emerald-900/85 mt-0.5">
                        @if($nextInLineVoucher)
                            Next in stack when #1 is about to end: <strong>{{ $nextInLineVoucher->code }}</strong> ({{ $nextInLineVoucher->title }}) • Total live stack: {{ $stackHealth['live_stack']->count() }}
                        @else
                            1 Featured Voucher active in stack.
                        @endif
                    </p>
                </div>
            </div>
        </div>
    @endif

    <!-- 1. Default First-Order Free Courier Voucher (Tile #1) -->
    @if($defaultFreeVoucher)
        <section class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-5 shadow-sm space-y-3" data-default-free-courier-card>
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 border-b border-[var(--border)] pb-3">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="rounded bg-emerald-100 border border-emerald-300 px-2 py-0.5 text-[10px] font-bold text-emerald-900">Homepage Tile #1 • System Default Code</span>
                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-[11px] font-bold text-rose-800">
                            <i class="fa-solid fa-ticket text-[9px] text-rose-600"></i>
                            <span>{{ $defaultFreeVoucher->advertisedBadgeText() }}</span>
                        </span>
                        <span class="text-[11px] text-[var(--muted-foreground)]">Redemptions: <strong>{{ $defaultFreeVoucher->redemptions_count }}</strong></span>
                    </div>
                    <h2 class="text-base font-bold text-[var(--foreground)] mt-1">{{ $defaultFreeVoucher->title }}</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)]">
                        Covers doorstep courier pickup &amp; return delivery for free, strictly once on each customer's very first order on Meramot. Excluded shop branches: <strong>{{ $defaultFreeVoucher->excludedShops->count() }}</strong>.
                    </p>
                </div>
                <div class="flex items-center gap-2 shrink-0">
                    <button
                        type="button"
                        @click="editingVoucherId = (editingVoucherId === {{ $defaultFreeVoucher->id }} ? null : {{ $defaultFreeVoucher->id }})"
                        class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1.5 font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] cursor-pointer"
                    >
                        Edit Banner / Exclusions
                    </button>
                    <form method="POST" action="{{ route('admin.vouchers.toggle', $defaultFreeVoucher->id) }}">
                        @csrf
                        <button type="submit" class="rounded-md border border-[var(--border)] bg-white px-3 py-1.5 font-bold {{ $defaultFreeVoucher->is_active ? 'text-amber-800' : 'text-emerald-800' }} cursor-pointer">
                            {{ $defaultFreeVoucher->is_active ? 'Pause' : 'Activate' }}
                        </button>
                    </form>
                </div>
            </div>

            <div x-show="editingVoucherId === {{ $defaultFreeVoucher->id }}" x-cloak class="pt-2">
                <form method="POST" action="{{ route('admin.vouchers.update', $defaultFreeVoucher->id) }}" class="space-y-3 rounded-lg border border-[var(--border)] bg-[var(--mint-50)] p-4">
                    @csrf
                    @method('PUT')
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div>
                            <label class="block font-bold mb-1">Default Code (Locked)</label>
                            <input type="text" value="{{ $defaultFreeVoucher->code }}" disabled class="w-full rounded-md border border-[var(--border)] bg-slate-100 px-3 py-2 font-mono font-bold text-slate-600">
                        </div>
                        <div>
                            <label class="block font-bold mb-1">Homepage Banner Title *</label>
                            <input type="text" name="title" value="{{ $defaultFreeVoucher->title }}" required class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 font-semibold">
                        </div>
                        <div>
                            <label class="block font-bold mb-1">Homepage Subtitle</label>
                            <input type="text" name="banner_subtitle" value="{{ $defaultFreeVoucher->banner_subtitle }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                        </div>
                    </div>

                    <div>
                        <label class="block font-bold mb-1">Exclude Specific Vendor Shop Branches (Optional)</label>
                        <div class="max-h-40 overflow-y-auto rounded-md border border-[var(--border)] bg-white p-2.5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                            @php $defExIds = $defaultFreeVoucher->excludedShops->pluck('id')->all(); @endphp
                            @foreach($selectableShops as $sh)
                                <label class="flex items-center gap-1.5 text-[11px] truncate cursor-pointer">
                                    <input type="checkbox" name="excluded_shop_ids[]" value="{{ $sh->id }}" {{ in_array($sh->id, $defExIds) ? 'checked' : '' }} class="accent-[#244229]">
                                    <span class="truncate">{{ $sh->name }}{{ $sh->display_branch_tag ? ' [' . $sh->display_branch_tag . ']' : '' }} ({{ $sh->area }})</span>
                                </label>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex justify-end gap-2">
                        <button type="button" @click="editingVoucherId = null" class="rounded-md border border-[var(--border)] bg-white px-3 py-1.5 font-semibold">Cancel</button>
                        <button type="submit" class="rounded-md bg-[var(--accent-dark)] px-4 py-1.5 font-bold text-white cursor-pointer">Save Changes</button>
                    </div>
                </form>
            </div>
        </section>
    @endif

    <!-- 2. Create New Featured Voucher (Manual Code Required) -->
    <section
        class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-5 shadow-sm space-y-4"
        x-data="{
            createOpen: {{ ($errors->any() || old('code') !== null) ? 'true' : 'false' }},
            discType: @js(old('discount_type', 'PERCENTAGE')),
            vType: @js(old('voucher_type', 'LIMITED_TIME')),
            submitting: false,
            submitErrors: {},
            generalError: '',
            async submitCreateForm(event) {
                this.submitting = true;
                this.submitErrors = {};
                this.generalError = '';

                const form = event.target;
                const formData = new FormData(form);

                try {
                    const response = await fetch(form.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': formData.get('_token') || '{{ csrf_token() }}'
                        },
                        body: formData
                    });

                    const data = await response.json();

                    if (!response.ok) {
                        if (response.status === 422 && data.errors) {
                            this.submitErrors = data.errors;
                            this.generalError = data.message || 'Please fix the highlighted errors below.';
                            this.$nextTick(() => {
                                const firstErrorEl = form.querySelector('[data-error-field]');
                                if (firstErrorEl) {
                                    firstErrorEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
                                }
                            });
                            return;
                        }
                        this.generalError = data.message || ('Server error: ' + response.statusText);
                        return;
                    }

                    if (data.redirect) {
                        window.location.href = data.redirect;
                    } else {
                        window.location.reload();
                    }
                } catch (err) {
                    console.error('AJAX voucher store failed, falling back to standard submit:', err);
                    form.submit();
                } finally {
                    this.submitting = false;
                }
            }
        }"
    >
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="text-base font-bold text-[var(--foreground)]">Create Featured Voucher</h2>
                <p class="text-[11px] text-[var(--muted-foreground)]">Manually set a unique voucher code, benefit, time period, optional payment condition (e.g. bKash), and any excluded shop branches.</p>
            </div>
            <button
                type="button"
                data-toggle-create-featured-voucher
                @click="createOpen = !createOpen"
                class="rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-xs hover:opacity-90 cursor-pointer shrink-0"
            >
                <span x-text="createOpen ? 'Hide Form' : '+ New Featured Voucher'">+ New Featured Voucher</span>
            </button>
        </div>

        <form
            x-show="createOpen"
            x-cloak
            method="POST"
            action="{{ route('admin.vouchers.store') }}"
            @submit.prevent="submitCreateForm($event)"
            data-admin-create-voucher-form
            class="space-y-4 border-t border-[var(--border)] pt-4"
        >
            @csrf

            <!-- Form-level validation error banner -->
            <div x-show="generalError" x-cloak class="rounded-lg border border-rose-300 bg-rose-50 p-3.5 text-xs text-rose-950 flex items-start gap-2.5 shadow-2xs">
                <i class="fa-solid fa-circle-exclamation text-rose-600 mt-0.5 shrink-0 text-sm"></i>
                <div class="flex-1">
                    <p class="font-bold text-rose-900" x-text="generalError"></p>
                    <ul class="mt-1 list-disc list-inside space-y-0.5 text-[11px] text-rose-800" x-show="Object.keys(submitErrors).length > 0">
                        <template x-for="(errList, field) in submitErrors" :key="field">
                            <template x-for="errMsg in errList" :key="errMsg">
                                <li x-text="errMsg"></li>
                            </template>
                        </template>
                    </ul>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                <div>
                    <label class="block font-bold mb-1">Voucher Code (Manual) *</label>
                    <input
                        type="text"
                        name="code"
                        value="{{ old('code') }}"
                        required
                        maxlength="32"
                        placeholder="e.g. WINTER15, FREESHIP"
                        :class="submitErrors.code ? 'border-rose-500 bg-rose-50/50 ring-1 ring-rose-500' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 font-mono font-bold uppercase transition"
                    >
                    <p x-show="submitErrors.code" x-text="submitErrors.code ? submitErrors.code[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600" data-error-field></p>
                    @error('code')
                        <p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="block font-bold mb-1">Title / Campaign Name *</label>
                    <input
                        type="text"
                        name="title"
                        value="{{ old('title') }}"
                        required
                        maxlength="140"
                        placeholder="e.g. Winter Sale — 15% Off"
                        :class="submitErrors.title ? 'border-rose-500 bg-rose-50/50 ring-1 ring-rose-500' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 font-semibold transition"
                    >
                    <p x-show="submitErrors.title" x-text="submitErrors.title ? submitErrors.title[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600" data-error-field></p>
                    @error('title')
                        <p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block font-bold mb-1">Voucher Type *</label>
                    <select
                        name="voucher_type"
                        x-model="vType"
                        :class="submitErrors.voucher_type ? 'border-rose-500 bg-rose-50/50 ring-1 ring-rose-500' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 font-semibold transition cursor-pointer"
                    >
                        <option value="LIMITED_TIME" {{ old('voucher_type', 'LIMITED_TIME') === 'LIMITED_TIME' ? 'selected' : '' }}>Limited Time (Repeatable in Window)</option>
                        <option value="USE_ONCE" {{ old('voucher_type') === 'USE_ONCE' ? 'selected' : '' }}>Use Once (1 Time per Customer)</option>
                    </select>
                    <p x-show="submitErrors.voucher_type" x-text="submitErrors.voucher_type ? submitErrors.voucher_type[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600"></p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold mb-1">Homepage Banner Subtitle (Shown when #1 in Stack)</label>
                    <input
                        type="text"
                        name="banner_subtitle"
                        value="{{ old('banner_subtitle') }}"
                        maxlength="240"
                        placeholder="e.g. Save up to 15% at participating workshops."
                        :class="submitErrors.banner_subtitle ? 'border-rose-500 bg-rose-50/50' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 transition"
                    >
                    <p x-show="submitErrors.banner_subtitle" x-text="submitErrors.banner_subtitle ? submitErrors.banner_subtitle[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600"></p>
                </div>
                <div>
                    <label class="block font-bold mb-1">Banner Explore Link (Optional)</label>
                    <input
                        type="text"
                        name="cta_href"
                        value="{{ old('cta_href', '/shops?voucher=true') }}"
                        maxlength="240"
                        placeholder="/shops?voucher=true"
                        :class="submitErrors.cta_href ? 'border-rose-500 bg-rose-50/50' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 transition"
                    >
                    <p x-show="submitErrors.cta_href" x-text="submitErrors.cta_href ? submitErrors.cta_href[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600"></p>
                </div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                <div>
                    <label class="block font-bold mb-1">Benefit Type *</label>
                    <select
                        name="discount_type"
                        x-model="discType"
                        :class="submitErrors.discount_type ? 'border-rose-500 bg-rose-50/50 ring-1 ring-rose-500' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 font-semibold transition cursor-pointer"
                    >
                        <option value="PERCENTAGE" {{ old('discount_type', 'PERCENTAGE') === 'PERCENTAGE' ? 'selected' : '' }}>Percentage Off (%)</option>
                        <option value="FIXED" {{ old('discount_type') === 'FIXED' ? 'selected' : '' }}>Flat Amount Off (৳)</option>
                        <option value="FREE_COURIER" {{ old('discount_type') === 'FREE_COURIER' ? 'selected' : '' }}>Free Courier (Pickup &amp; Delivery)</option>
                    </select>
                    <p x-show="submitErrors.discount_type" x-text="submitErrors.discount_type ? submitErrors.discount_type[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600"></p>
                </div>
                <div x-show="discType !== 'FREE_COURIER'">
                    <label class="block font-bold mb-1" x-text="discType === 'PERCENTAGE' ? 'Discount (%) *' : 'Discount Amount (৳) *'">Discount *</label>
                    <input
                        type="number"
                        step="0.01"
                        min="0"
                        name="discount_value"
                        value="{{ old('discount_value', 15) }}"
                        placeholder="15"
                        :class="submitErrors.discount_value ? 'border-rose-500 bg-rose-50/50 ring-1 ring-rose-500' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 font-semibold transition"
                    >
                    <p x-show="submitErrors.discount_value" x-text="submitErrors.discount_value ? submitErrors.discount_value[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600" data-error-field></p>
                    @error('discount_value')
                        <p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block font-bold mb-1">Min. Order Subtotal (৳)</label>
                    <input
                        type="number"
                        step="1"
                        min="0"
                        name="min_order_amount"
                        value="{{ old('min_order_amount', 0) }}"
                        :class="submitErrors.min_order_amount ? 'border-rose-500 bg-rose-50/50' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 transition"
                    >
                    <p x-show="submitErrors.min_order_amount" x-text="submitErrors.min_order_amount ? submitErrors.min_order_amount[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600"></p>
                </div>
                <div x-show="discType !== 'FIXED'">
                    <label class="block font-bold mb-1">Max Discount Cap (৳)</label>
                    <input
                        type="number"
                        step="1"
                        min="0"
                        name="max_discount_amount"
                        value="{{ old('max_discount_amount') }}"
                        placeholder="Optional"
                        :class="submitErrors.max_discount_amount ? 'border-rose-500 bg-rose-50/50' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 transition"
                    >
                    <p x-show="submitErrors.max_discount_amount" x-text="submitErrors.max_discount_amount ? submitErrors.max_discount_amount[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600"></p>
                </div>
                <div>
                    <label class="block font-bold mb-1">Payment Method Rule</label>
                    <select
                        name="required_payment_method"
                        :class="submitErrors.required_payment_method ? 'border-rose-500 bg-rose-50/50' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 transition cursor-pointer"
                    >
                        <option value="" {{ old('required_payment_method') === '' ? 'selected' : '' }}>Any Payment Method</option>
                        <option value="BKASH" {{ old('required_payment_method') === 'BKASH' ? 'selected' : '' }}>bKash Only</option>
                        <option value="NAGAD" {{ old('required_payment_method') === 'NAGAD' ? 'selected' : '' }}>Nagad Only</option>
                        <option value="CARD" {{ old('required_payment_method') === 'CARD' ? 'selected' : '' }}>Card Only</option>
                        <option value="SSLCOMMERZ" {{ old('required_payment_method') === 'SSLCOMMERZ' ? 'selected' : '' }}>SSLCommerz Only</option>
                        <option value="CASH" {{ old('required_payment_method') === 'CASH' ? 'selected' : '' }}>Cash Only</option>
                    </select>
                    <p x-show="submitErrors.required_payment_method" x-text="submitErrors.required_payment_method ? submitErrors.required_payment_method[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600"></p>
                </div>
            </div>

            <!-- Time Period & Scheduled Recurring Days -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 rounded-lg border border-[var(--border)] bg-[var(--mint-50)] p-3.5">
                <div>
                    <label class="block font-bold mb-1">Starts At (Time Period)</label>
                    <input
                        type="datetime-local"
                        name="starts_at"
                        value="{{ old('starts_at', now()->format('Y-m-d\TH:i')) }}"
                        :class="submitErrors.starts_at ? 'border-rose-500 bg-rose-50/50' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 transition"
                    >
                    <p x-show="submitErrors.starts_at" x-text="submitErrors.starts_at ? submitErrors.starts_at[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600"></p>
                </div>
                <div>
                    <label class="block font-bold mb-1">Ends At (Time Period)</label>
                    <input
                        type="datetime-local"
                        name="expires_at"
                        value="{{ old('expires_at', now()->addDays(14)->format('Y-m-d\TH:i')) }}"
                        :class="submitErrors.expires_at ? 'border-rose-500 bg-rose-50/50 ring-1 ring-rose-500' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 transition"
                    >
                    <p x-show="submitErrors.expires_at" x-text="submitErrors.expires_at ? submitErrors.expires_at[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600" data-error-field></p>
                    @error('expires_at')
                        <p class="mt-1 text-[11px] font-bold text-rose-600">{{ $message }}</p>
                    @enderror
                </div>
                <div>
                    <label class="block font-bold mb-1">Stack Priority Order</label>
                    <input
                        type="number"
                        min="1"
                        max="9999"
                        name="featured_order"
                        value="{{ old('featured_order', 5) }}"
                        :class="submitErrors.featured_order ? 'border-rose-500 bg-rose-50/50' : 'border-[var(--border)] bg-white'"
                        class="w-full rounded-md border px-3 py-2 transition"
                    >
                    <p x-show="submitErrors.featured_order" x-text="submitErrors.featured_order ? submitErrors.featured_order[0] : ''" class="mt-1 text-[11px] font-bold text-rose-600"></p>
                </div>
                <div class="flex flex-col justify-center gap-2 pt-2">
                    <label class="inline-flex items-center gap-2 font-bold cursor-pointer">
                        <input type="hidden" name="is_featured" value="0">
                        <input type="checkbox" name="is_featured" value="1" {{ old('is_featured', '1') == '1' ? 'checked' : '' }} class="accent-[#244229]">
                        <span>Include in Homepage Stack</span>
                    </label>
                    <label x-show="vType === 'USE_ONCE'" class="inline-flex items-center gap-2 font-semibold cursor-pointer">
                        <input type="checkbox" name="is_first_order_only" value="1" {{ old('is_first_order_only') ? 'checked' : '' }} class="accent-[#244229]">
                        <span>Customer's 1st Meramot order only</span>
                    </label>
                </div>

                <div class="sm:col-span-2 lg:col-span-4">
                    <label class="block font-bold mb-1">Scheduled Days of Week (Leave all unchecked for Every Day, or select e.g. Fri / Sun)</label>
                    <div class="flex flex-wrap gap-2">
                        @php $oldDays = array_map('intval', old('valid_days_of_week', [])); @endphp
                        @foreach($dayLabels as $dayIdx => $dayShort)
                            <label class="inline-flex items-center gap-1.5 rounded border border-[var(--border)] bg-white px-2.5 py-1 cursor-pointer hover:bg-[var(--mint-100)] transition">
                                <input type="checkbox" name="valid_days_of_week[]" value="{{ $dayIdx }}" {{ in_array($dayIdx, $oldDays, true) ? 'checked' : '' }} class="accent-[#244229]">
                                <span class="font-semibold">{{ $dayShort }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <!-- Exclude Specific Vendor Shop Branches -->
            <div>
                <div class="flex flex-wrap items-center justify-between gap-2 mb-1.5">
                    <label class="font-bold">Exclude Specific Vendor Shop Branches (Optional)</label>
                    <input type="text" x-model="shopFilter" placeholder="Filter shops or branches..." class="rounded-md border border-[var(--border)] bg-white px-2.5 py-1 text-[11px]">
                </div>
                <div class="max-h-48 overflow-y-auto rounded-md border border-[var(--border)] bg-white p-2.5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                    @php $oldExcluded = array_map('intval', old('excluded_shop_ids', [])); @endphp
                    @foreach($selectableShops as $sh)
                        @php $searchHay = strtolower($sh->name . ' ' . ($sh->branch_name ?? '') . ' ' . $sh->area . ' ' . $sh->city); @endphp
                        <label x-show="!shopFilter || @js($searchHay).includes(shopFilter.toLowerCase())" class="flex items-center gap-1.5 text-[11px] truncate cursor-pointer hover:bg-slate-50 p-0.5 rounded">
                            <input type="checkbox" name="excluded_shop_ids[]" value="{{ $sh->id }}" {{ in_array($sh->id, $oldExcluded, true) ? 'checked' : '' }} class="accent-[#244229]">
                            <span class="truncate">{{ $sh->name }}{{ $sh->display_branch_tag ? ' [' . $sh->display_branch_tag . ']' : '' }} ({{ $sh->area }}, {{ $sh->city }})</span>
                        </label>
                    @endforeach
                </div>
            </div>

            <div class="flex justify-end gap-3 items-center pt-2">
                <button
                    type="submit"
                    :disabled="submitting"
                    class="rounded-md bg-[var(--accent-dark)] px-5 py-2.5 font-bold text-[var(--accent-foreground)] hover:opacity-90 disabled:opacity-50 transition cursor-pointer flex items-center gap-2 shadow-xs"
                >
                    <span x-show="!submitting">Publish Featured Voucher</span>
                    <span x-show="submitting" class="inline-flex items-center gap-1.5" x-cloak>
                        <i class="fa-solid fa-circle-notch fa-spin text-xs"></i>
                        <span>Publishing...</span>
                    </span>
                </button>
            </div>
        </form>
    </section>

    <!-- 3. Active & Scheduled Featured Vouchers Stack -->
    <section class="space-y-3">
        <h2 class="text-base font-bold text-[var(--foreground)]">Featured Vouchers Stack ({{ $featuredVouchersList->count() }})</h2>

        @forelse($featuredVouchersList as $fv)
            @php
                $isTopLive = $currentHomepageVoucher && (int) $currentHomepageVoucher->id === (int) $fv->id;
                $isExpired = $fv->expires_at && now()->gt($fv->expires_at);
                $exIds = $fv->excludedShops->pluck('id')->all();
            @endphp
            <div data-admin-voucher-card="{{ $fv->code }}" class="rounded-lg border {{ $isTopLive ? 'border-emerald-500 bg-[var(--card)] ring-1 ring-emerald-500/20' : 'border-[var(--border)] bg-[var(--card)]' }} p-4 shadow-xs space-y-3">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-3">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($isTopLive)
                                <span class="rounded bg-emerald-700 px-2 py-0.5 text-[10px] font-bold text-white">#1 Live on Homepage</span>
                            @elseif($fv->is_featured)
                                <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">Stack #{{ $fv->featured_order }}</span>
                            @endif

                            <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-[11px] font-bold text-rose-800">
                                <i class="fa-solid fa-ticket text-[9px] text-rose-600"></i>
                                <span>{{ $fv->advertisedBadgeText() }}</span>
                            </span>

                            <span class="rounded border px-2 py-0.5 text-[10px] font-bold {{ !$fv->is_active ? 'bg-slate-100 text-slate-700 border-slate-300' : ($isExpired ? 'bg-rose-100 text-rose-900 border-rose-300' : 'bg-emerald-50 text-emerald-900 border-emerald-200') }}">
                                {{ !$fv->is_active ? 'Paused' : ($isExpired ? 'Ended' : ($fv->voucher_type === 'USE_ONCE' ? 'Use Once' : 'Limited Time')) }}
                            </span>

                            <span class="text-[11px] text-[var(--muted-foreground)]">
                                Redemptions: <strong>{{ $fv->redemptions_count }}</strong>
                            </span>
                        </div>

                        <h3 class="text-sm font-bold text-[var(--foreground)]">{{ $fv->title }}</h3>
                        <p class="text-[11px] text-[var(--muted-foreground)]">
                            Time Period:
                            <strong>{{ $fv->starts_at ? $fv->starts_at->format('M j, Y H:i') : 'Immediate' }}</strong>
                            &rarr;
                            <strong>{{ $fv->expires_at ? $fv->expires_at->format('M j, Y H:i') : 'No Expiry' }}</strong>
                            • {{ $fv->conditionsPillLabel() }}
                            @if($fv->excludedShops->isNotEmpty())
                                • Excluded branches: <strong>{{ $fv->excludedShops->map(fn ($s) => $s->name . ($s->display_branch_tag ? ' [' . $s->display_branch_tag . ']' : ''))->implode(', ') }}</strong>
                            @endif
                        </p>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 shrink-0">
                        @if(!$isTopLive && !$isExpired)
                            <form method="POST" action="{{ route('admin.vouchers.promote-top', $fv->id) }}">
                                @csrf
                                <button type="submit" class="rounded-md border border-emerald-400 bg-emerald-50 px-3 py-1.5 font-bold text-emerald-900 hover:bg-emerald-100 cursor-pointer">
                                    Promote to #1
                                </button>
                            </form>
                        @endif

                        <button
                            type="button"
                            @click="editingVoucherId = (editingVoucherId === {{ $fv->id }} ? null : {{ $fv->id }})"
                            class="rounded-md border border-[var(--border)] bg-[var(--mint-100)] px-3 py-1.5 font-bold text-[var(--accent-dark)] hover:bg-[var(--mint-200)] cursor-pointer"
                        >
                            Edit Time Period &amp; Rules
                        </button>

                        <form method="POST" action="{{ route('admin.vouchers.toggle', $fv->id) }}">
                            @csrf
                            <button type="submit" class="rounded-md border border-[var(--border)] bg-white px-3 py-1.5 font-bold {{ $fv->is_active ? 'text-amber-800' : 'text-emerald-800' }} cursor-pointer">
                                {{ $fv->is_active ? 'Pause' : 'Activate' }}
                            </button>
                        </form>

                        <form method="POST" action="{{ route('admin.vouchers.destroy', $fv->id) }}" onsubmit="return confirm('Delete Featured Voucher {{ $fv->code }}?')">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-md border border-rose-200 bg-rose-50 px-3 py-1.5 font-bold text-rose-800 hover:bg-rose-100 cursor-pointer">
                                Delete
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Inline Edit Form (Time Period, Code, Discount & Branch Exclusions) -->
                <div x-show="editingVoucherId === {{ $fv->id }}" x-cloak class="pt-2 border-t border-[var(--border)]">
                    <form method="POST" action="{{ route('admin.vouchers.update', $fv->id) }}" class="space-y-3 rounded-lg border border-[var(--border)] bg-[var(--mint-50)] p-4">
                        @csrf
                        @method('PUT')
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
                            <div>
                                <label class="block font-bold mb-1">Code *</label>
                                <input type="text" name="code" value="{{ $fv->code }}" required class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 font-mono font-bold uppercase">
                            </div>
                            <div class="sm:col-span-2">
                                <label class="block font-bold mb-1">Title *</label>
                                <input type="text" name="title" value="{{ $fv->title }}" required class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 font-semibold">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Voucher Type *</label>
                                <select name="voucher_type" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                                    <option value="LIMITED_TIME" {{ $fv->voucher_type === 'LIMITED_TIME' ? 'selected' : '' }}>Limited Time</option>
                                    <option value="USE_ONCE" {{ $fv->voucher_type === 'USE_ONCE' ? 'selected' : '' }}>Use Once</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold mb-1">Homepage Banner Subtitle</label>
                                <input type="text" name="banner_subtitle" value="{{ $fv->banner_subtitle }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Banner Link</label>
                                <input type="text" name="cta_href" value="{{ $fv->cta_href }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
                            <div>
                                <label class="block font-bold mb-1">Benefit Type *</label>
                                <select name="discount_type" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                                    <option value="PERCENTAGE" {{ $fv->discount_type === 'PERCENTAGE' ? 'selected' : '' }}>Percentage (%)</option>
                                    <option value="FIXED" {{ $fv->discount_type === 'FIXED' ? 'selected' : '' }}>Fixed (৳)</option>
                                    <option value="FREE_COURIER" {{ $fv->discount_type === 'FREE_COURIER' ? 'selected' : '' }}>Free Courier</option>
                                </select>
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Value</label>
                                <input type="number" step="0.01" min="0" name="discount_value" value="{{ (float) $fv->discount_value }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Min. Spend (৳)</label>
                                <input type="number" step="1" min="0" name="min_order_amount" value="{{ (float) $fv->min_order_amount }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Max Cap (৳)</label>
                                <input type="number" step="1" min="0" name="max_discount_amount" value="{{ $fv->max_discount_amount !== null ? (float) $fv->max_discount_amount : '' }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Payment Rule</label>
                                <select name="required_payment_method" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                                    <option value="">Any Method</option>
                                    @foreach(['BKASH' => 'bKash Only', 'NAGAD' => 'Nagad Only', 'CARD' => 'Card Only', 'SSLCOMMERZ' => 'SSLCommerz Only', 'CASH' => 'Cash Only'] as $pmKey => $pmLbl)
                                        <option value="{{ $pmKey }}" {{ $fv->required_payment_method === $pmKey ? 'selected' : '' }}>{{ $pmLbl }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3">
                            <div>
                                <label class="block font-bold mb-1">Starts At</label>
                                <input type="datetime-local" name="starts_at" value="{{ $fv->starts_at ? $fv->starts_at->format('Y-m-d\TH:i') : '' }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Ends At</label>
                                <input type="datetime-local" name="expires_at" value="{{ $fv->expires_at ? $fv->expires_at->format('Y-m-d\TH:i') : '' }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div>
                                <label class="block font-bold mb-1">Stack Order</label>
                                <input type="number" min="1" max="9999" name="featured_order" value="{{ $fv->featured_order }}" class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2">
                            </div>
                            <div class="flex items-center pt-5">
                                <label class="inline-flex items-center gap-2 font-bold cursor-pointer">
                                    <input type="hidden" name="is_featured" value="0">
                                    <input type="checkbox" name="is_featured" value="1" {{ $fv->is_featured ? 'checked' : '' }} class="accent-[#244229]">
                                    <span>In Homepage Stack</span>
                                </label>
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold mb-1">Scheduled Days of Week</label>
                            <div class="flex flex-wrap gap-2">
                                @php $curDays = $fv->normalizedDaysOfWeek(); @endphp
                                @foreach($dayLabels as $dayIdx => $dayShort)
                                    <label class="inline-flex items-center gap-1.5 rounded border border-[var(--border)] bg-white px-2.5 py-1 cursor-pointer">
                                        <input type="checkbox" name="valid_days_of_week[]" value="{{ $dayIdx }}" {{ in_array($dayIdx, $curDays, true) ? 'checked' : '' }} class="accent-[#244229]">
                                        <span>{{ $dayShort }}</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold mb-1">Excluded Shop Branches</label>
                            <div class="max-h-36 overflow-y-auto rounded-md border border-[var(--border)] bg-white p-2.5 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-1.5">
                                @foreach($selectableShops as $sh)
                                    <label class="flex items-center gap-1.5 text-[11px] truncate cursor-pointer">
                                        <input type="checkbox" name="excluded_shop_ids[]" value="{{ $sh->id }}" {{ in_array($sh->id, $exIds) ? 'checked' : '' }} class="accent-[#244229]">
                                        <span class="truncate">{{ $sh->name }}{{ $sh->display_branch_tag ? ' [' . $sh->display_branch_tag . ']' : '' }} ({{ $sh->area }})</span>
                                    </label>
                                @endforeach
                            </div>
                        </div>

                        <div class="flex justify-end gap-2">
                            <button type="button" @click="editingVoucherId = null" class="rounded-md border border-[var(--border)] bg-white px-3 py-1.5 font-semibold">Cancel</button>
                            <button type="submit" class="rounded-md bg-[var(--accent-dark)] px-4 py-1.5 font-bold text-white cursor-pointer">Update Featured Voucher</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-6 text-center text-[var(--muted-foreground)]">
                No Featured Vouchers configured yet. Click "+ New Featured Voucher" above to add one to the homepage stack.
            </div>
        @endforelse
    </section>

    <!-- 4. Active Vendor Vouchers Overview -->
    <section class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-5 shadow-xs space-y-3">
        <h2 class="text-base font-bold text-[var(--foreground)]">Merchant Vouchers Overview ({{ $vendorVouchers->count() }})</h2>
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
            @forelse($vendorVouchers as $vv)
                <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-50)]/60 p-3 flex items-center justify-between gap-2">
                    <div class="min-w-0">
                        <span class="inline-flex items-center gap-1 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-[10px] font-bold text-rose-800">
                            <i class="fa-solid fa-ticket text-[9px] text-rose-600"></i>
                            <span>{{ $vv->advertisedBadgeText() }}</span>
                        </span>
                        <p class="font-bold text-[var(--foreground)] mt-1 truncate">{{ $vv->title }}</p>
                        <p class="text-[11px] text-[var(--muted-foreground)] truncate">
                            Vendor: <strong>{{ $vv->vendorProfile?->canonical_brand_name ?? 'Workshop' }}</strong>
                            • {{ $vv->branch_scope === 'SPECIFIC_BRANCHES' ? ($vv->includedShops->count() . ' selected branch(es)') : 'All branches' }}
                            • Redemptions: {{ $vv->redemptions_count }}
                        </p>
                    </div>
                    <span class="rounded px-2 py-0.5 text-[10px] font-bold {{ $vv->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-700' }}">
                        {{ $vv->is_active ? 'Active' : 'Paused' }}
                    </span>
                </div>
            @empty
                <p class="text-[var(--muted-foreground)] col-span-2">No vendor vouchers created yet.</p>
            @endforelse
        </div>
    </section>

</div>
@endsection

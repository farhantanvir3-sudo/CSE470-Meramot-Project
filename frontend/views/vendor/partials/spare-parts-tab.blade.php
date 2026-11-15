{{-- ==========================================================================
     VENDOR DASHBOARD: DEDICATED SPARE PARTS MARKETPLACE INVENTORY & PROMO ENGINE
     Supports:
     - Multi-Branch Seller Attachment (`shop_id`)
     - Dynamic Spec Options (`has_specs`, `spec_label`, `spec_names[]`, `spec_prices[]`)
     - Limited-Time & Indefinite Offer Prices (`From ___ , To ___` optional + Circular Red "Sale!" badge)
     - AliExpress-Style Progressive Wholesale Tiers (e.g. 5% @ 100+, +2% per extra 100)
     ========================================================================== --}}
@php
    $spStats = $vendorSparePartStats ?? [
        'total_count' => 0,
        'active_count' => 0,
        'wholesale_count' => 0,
        'offer_count' => 0,
    ];
    $defaultShopId = $activeBranch ? $activeBranch->id : ($branches->first()->id ?? null);
    $spFilterVal = $spFilter ?? 'all';
    $spSearchVal = $spSearch ?? '';
@endphp

<div x-show="activeTab === 'spare_parts'" x-cloak class="space-y-4 sm:space-y-6">
    {{-- Top Summary Banner & Quick Actions --}}
    <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-4 sm:p-5 shadow-sm">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3 mb-4">
            <div>
                <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-emerald-900 text-white text-[10px] font-extrabold uppercase tracking-wider mb-1">
                    🔩 Multi-Vendor Spare Parts Center
                </div>
                <h2 class="text-base sm:text-xl font-black text-[var(--foreground)]">
                    Spare Parts Catalog, Sale Offers &amp; Wholesale Tiers
                </h2>
                <p class="text-xs text-[var(--muted-foreground)] mt-0.5">
                    List mobile, laptop &amp; PC spare parts attached to any of your branches — configure specification grades, time-windowed or indefinite <strong class="text-red-600">"Sale!"</strong> offers, and progressive 100+ wholesale bulk discounts.
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2 shrink-0">
                <a href="{{ url('/spare-parts') }}"
                   class="px-3.5 py-2 rounded-full border border-[var(--border)] bg-white hover:bg-[var(--mint-50)] text-[var(--foreground)] text-xs font-bold transition">
                    🛒 Buy Parts from Other Vendors
                </a>
                <a href="{{ url('/orders') }}"
                   class="px-3.5 py-2 rounded-full border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] text-[var(--accent-dark)] text-xs font-bold transition">
                    📦 My Spare Part Purchases
                </a>
                <button type="button"
                        onclick="window.vspOpenCreateForm()"
                        class="px-4 py-2 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-xs font-extrabold shadow-sm hover:opacity-90 transition">
                    + List New Spare Part
                </button>
            </div>
        </div>

        {{-- 4 KPI Stat Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-2.5 sm:gap-3.5">
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-50)]/70 p-3 sm:p-4">
                <div class="text-[10px] sm:text-xs font-bold uppercase text-[var(--muted-foreground)]">Total Spare Parts</div>
                <div class="text-xl sm:text-2xl font-black text-[var(--foreground)] mt-0.5">{{ number_format($spStats['total_count']) }}</div>
                <div class="text-[10px] text-[var(--muted-foreground)] mt-0.5">In selected branch scope</div>
            </div>
            <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-50)]/70 p-3 sm:p-4">
                <div class="text-[10px] sm:text-xs font-bold uppercase text-[var(--muted-foreground)]">Active in Marketplace</div>
                <div class="text-xl sm:text-2xl font-black text-emerald-800 mt-0.5">{{ number_format($spStats['active_count']) }}</div>
                <div class="text-[10px] text-[var(--muted-foreground)] mt-0.5">Live on /spare-parts</div>
            </div>
            <div class="rounded-2xl border border-red-200 bg-red-50/60 p-3 sm:p-4">
                <div class="text-[10px] sm:text-xs font-bold uppercase text-red-800">Active "Sale!" Offers</div>
                <div class="text-xl sm:text-2xl font-black text-red-600 mt-0.5">{{ number_format($spStats['offer_count']) }}</div>
                <div class="text-[10px] text-red-800/80 mt-0.5">Circular Red Sale! banner</div>
            </div>
            <div class="rounded-2xl border border-amber-200 bg-amber-50/60 p-3 sm:p-4">
                <div class="text-[10px] sm:text-xs font-bold uppercase text-amber-900">Wholesale Enabled</div>
                <div class="text-xl sm:text-2xl font-black text-amber-900 mt-0.5">{{ number_format($spStats['wholesale_count']) }}</div>
                <div class="text-[10px] text-amber-900/80 mt-0.5">Progressive 100+ bulk tiers</div>
            </div>
        </div>
    </div>

    {{-- Create / Edit Spare Part Listing Builder Drawer --}}
    <div id="vspFormWrapper" class="{{ $errors->any() ? '' : 'hidden' }} rounded-2xl border-2 border-[var(--accent-dark)]/35 bg-[var(--card)] p-4 sm:p-6 shadow-md">
        <div class="flex items-center justify-between gap-2 pb-3 mb-4 border-b border-[var(--border)]">
            <div>
                <h3 id="vspFormTitle" class="text-base sm:text-lg font-black text-[var(--foreground)]">
                    + Publish New Spare Part Listing
                </h3>
                <p class="text-xs text-[var(--muted-foreground)]">
                    Attach to a workshop branch, set retail or multi-spec prices, schedule an optional <strong>Sale!</strong> offer window, and enable AliExpress wholesale tiers.
                </p>
            </div>
            <button type="button"
                    onclick="window.vspCloseForm()"
                    class="px-3 py-1.5 rounded-full border border-[var(--border)] bg-white text-xs font-bold text-[var(--foreground)] hover:bg-rose-50 hover:text-rose-700 transition">
                ✕ Close
            </button>
        </div>

        <form id="vspBuilderForm"
              action="{{ route('vendor.spare-parts.store') }}"
              method="POST"
              enctype="multipart/form-data"
              class="space-y-5 text-xs">
            @csrf
            <input type="hidden" name="_method" id="vspMethodInput" value="POST">
            <input type="hidden" name="return_shop_id" value="{{ $selectedShopId }}">

            {{-- Row 1: Seller Branch, Part Category, Brand --}}
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3.5">
                <div>
                    <label for="vspShopId" class="block font-extrabold text-[var(--foreground)] mb-1">
                        Attached Seller Shop / Branch <span class="text-red-600">*</span>
                    </label>
                    <select name="shop_id" id="vspShopId" required
                            class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-bold text-[var(--foreground)]">
                        @foreach($branches as $b)
                            <option value="{{ $b->id }}" {{ (int) old('shop_id', $defaultShopId) === (int) $b->id ? 'selected' : '' }}>
                                {{ $b->name }}{{ $b->display_branch_tag ? (' [' . $b->display_branch_tag . ']') : '' }} — {{ $b->area }}, {{ $b->city }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="vspCategory" class="block font-extrabold text-[var(--foreground)] mb-1">
                        Part Category <span class="text-red-600">*</span>
                    </label>
                    <select name="part_category" id="vspCategory" required
                            class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-bold text-[var(--foreground)]">
                        @foreach($sparePartCategories ?? [] as $cat)
                            <option value="{{ $cat }}" {{ old('part_category') === $cat ? 'selected' : '' }}>{{ $cat }}</option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="vspBrand" class="block font-extrabold text-[var(--foreground)] mb-1">
                        Device / Component Brand <span class="text-red-600">*</span>
                    </label>
                    <input type="text" name="brand" id="vspBrand" list="vspBrandDatalist" required
                           value="{{ old('brand', 'Samsung') }}"
                           placeholder="e.g. Samsung, Apple, Asus, Dell, HP"
                           class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-semibold text-[var(--foreground)]">
                    <datalist id="vspBrandDatalist">
                        @foreach($sparePartBrands ?? [] as $br)
                            <option value="{{ $br }}"></option>
                        @endforeach
                    </datalist>
                </div>
            </div>

            {{-- Row 2: Listing Title, Compatible Model, Condition & SKU --}}
            <div class="grid grid-cols-1 md:grid-cols-12 gap-3.5">
                <div class="md:col-span-6">
                    <label for="vspTitle" class="block font-extrabold text-[var(--foreground)] mb-1">
                        Spare Part Title <span class="text-red-600">*</span>
                    </label>
                    <input type="text" name="title" id="vspTitle" required
                           value="{{ old('title') }}"
                           placeholder="e.g. Samsung Galaxy S21 FE 5G Display or Dell 4GVMP Latitude Battery"
                           class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-bold text-[var(--foreground)]">
                </div>
                <div class="md:col-span-3">
                    <label for="vspModel" class="block font-extrabold text-[var(--foreground)] mb-1">
                        Compatible Model
                    </label>
                    <input type="text" name="compatible_model" id="vspModel"
                           value="{{ old('compatible_model') }}"
                           placeholder="e.g. Galaxy S21 FE / X515EA"
                           class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 text-[var(--foreground)]">
                </div>
                <div class="md:col-span-3">
                    <label for="vspCondition" class="block font-extrabold text-[var(--foreground)] mb-1">
                        Condition Grade
                    </label>
                    <input type="text" name="condition_label" id="vspCondition"
                           value="{{ old('condition_label', 'Brand New') }}"
                           placeholder="Brand New / Original / OEM"
                           class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 text-[var(--foreground)]">
                </div>
            </div>

            {{-- Row 3: Stock, Handling Hours, Warranty, Optional Fitting Fee, SKU --}}
            <div class="grid grid-cols-2 md:grid-cols-5 gap-3">
                <div>
                    <label for="vspStock" class="block font-extrabold text-[var(--foreground)] mb-1">
                        Total Stock Qty <span class="text-red-600">*</span>
                    </label>
                    <input type="number" name="stock_quantity" id="vspStock" min="0" max="100000" required
                           value="{{ old('stock_quantity', 250) }}"
                           class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2 font-bold text-[var(--foreground)]">
                </div>
                <div>
                    <label for="vspHandling" class="block font-extrabold text-[var(--foreground)] mb-1">
                        Dispatch Prep (Hrs)
                    </label>
                    <input type="number" name="handling_hours" id="vspHandling" min="1" max="72"
                           value="{{ old('handling_hours', 2) }}"
                           class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2 text-[var(--foreground)]">
                </div>
                <div>
                    <label for="vspWarranty" class="block font-extrabold text-[var(--foreground)] mb-1">
                        Warranty (Days)
                    </label>
                    <input type="number" name="warranty_days" id="vspWarranty" min="0" max="1095"
                           value="{{ old('warranty_days', 90) }}"
                           class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2 text-[var(--foreground)]">
                </div>
                <div>
                    <label for="vspAssemblyFee" class="block font-extrabold text-[var(--foreground)] mb-1">
                        Fitting Fee (Optional ৳)
                    </label>
                    <input type="number" step="0.01" name="assembly_fee" id="vspAssemblyFee" min="0"
                           value="{{ old('assembly_fee', 0) }}"
                           class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2 text-[var(--foreground)]">
                </div>
                <div class="col-span-2 md:col-span-1">
                    <label for="vspSku" class="block font-extrabold text-[var(--foreground)] mb-1">
                        SKU (Optional)
                    </label>
                    <input type="text" name="sku" id="vspSku"
                           value="{{ old('sku') }}"
                           placeholder="Auto if blank"
                           class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2 font-mono text-[var(--foreground)]">
                </div>
            </div>

            {{-- ==============================================================
                 MODULE A: SINGLE PRICE vs SPECIFICATION OPTIONS (`has_specs`)
                 ============================================================== --}}
            <div class="rounded-2xl border border-emerald-900/20 bg-emerald-50/55 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer font-extrabold text-sm text-emerald-950">
                        <input type="checkbox" name="has_specs" id="vspHasSpecs" value="1"
                               {{ old('has_specs') ? 'checked' : '' }}
                               onchange="window.vspSyncPricingMode()"
                               class="w-4 h-4 rounded text-emerald-900 focus:ring-emerald-800">
                        <span>🎛️ Item Requires Specification / Quality Options (Prices vary by selected spec)</span>
                    </label>
                    <span class="text-[11px] font-semibold text-emerald-900/75">
                        e.g. IPS (৳1,749), OLED (৳5,399), Original Service Pack (৳11,099)
                    </span>
                </div>

                {{-- Single-Price Inputs (Shown when `has_specs` is OFF) --}}
                <div id="vspSinglePriceBox" class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                    <div>
                        <label for="vspBasePrice" class="block font-extrabold text-emerald-950 mb-1">
                            Normal Selling Price (BDT ৳) <span class="text-red-600">*</span>
                        </label>
                        <input type="number" step="0.01" min="1" name="base_price" id="vspBasePrice"
                               value="{{ old('base_price', 3000) }}"
                               oninput="window.vspUpdatePromoPreview()"
                               placeholder="3000"
                               class="w-full rounded-xl border border-emerald-900/25 bg-white px-3 py-2.5 text-sm font-extrabold text-emerald-950">
                    </div>
                    <div>
                        <label for="vspGroupKey" class="block font-extrabold text-emerald-950 mb-1">
                            Product Comparison Group Key (Optional)
                        </label>
                        <input type="text" name="product_group_key" id="vspGroupKey"
                               value="{{ old('product_group_key') }}"
                               placeholder="Auto-matched by title so buyers compare sellers"
                               class="w-full rounded-xl border border-emerald-900/20 bg-white px-3 py-2.5 text-emerald-950">
                    </div>
                </div>

                {{-- Multi-Spec Builder (Shown when `has_specs` is ON) --}}
                <div id="vspSpecBuilderBox" class="hidden space-y-3 pt-1">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                        <div>
                            <label for="vspSpecLabel" class="block font-extrabold text-emerald-950 mb-1">
                                Specification Dropdown Label
                            </label>
                            <input type="text" name="spec_label" id="vspSpecLabel"
                                   value="{{ old('spec_label', 'Quality') }}"
                                   placeholder="Quality / Specification / Capacity"
                                   class="w-full rounded-xl border border-emerald-900/25 bg-white px-3 py-2 font-bold text-emerald-950">
                        </div>
                        <div class="flex items-end">
                            <button type="button"
                                    onclick="window.vspAddSpecRow()"
                                    class="px-3.5 py-2 rounded-xl bg-emerald-900 text-white text-xs font-extrabold hover:bg-emerald-800 transition">
                                + Add Specification Option
                            </button>
                        </div>
                    </div>

                    <div id="vspSpecRowsContainer" class="space-y-2">
                        {{-- Populated dynamically by JS --}}
                    </div>
                </div>
            </div>

            {{-- ==============================================================
                 MODULE B: PROMOTIONAL OFFER PRICE & DATE-FIXING SYSTEM (`From ___ , To ___`)
                 Circular Red "Sale!" Banner + Strikethrough Normal Price
                 ============================================================== --}}
            <div class="rounded-2xl border border-red-300 bg-red-50/55 p-4 space-y-3.5">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer font-extrabold text-sm text-red-900">
                        <input type="checkbox" name="offer_enabled" id="vspOfferEnabled" value="1"
                               {{ old('offer_enabled') ? 'checked' : '' }}
                               onchange="window.vspSyncOfferBox()"
                               class="w-4 h-4 rounded text-red-600 focus:ring-red-600">
                        <span>🔴 Enable Promotional Offer Price (Circular Red "Sale!" Banner + Strikethrough Normal Price)</span>
                    </label>
                    <span class="px-2.5 py-0.5 rounded-full bg-red-600 text-white text-[10px] font-extrabold">
                        Leave "To" empty for indefinite offer
                    </span>
                </div>

                <div id="vspOfferConfigBox" class="hidden grid grid-cols-1 lg:grid-cols-12 gap-4 pt-1">
                    <div class="lg:col-span-8 space-y-3">
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div id="vspOfferPriceWrap">
                                <label for="vspOfferPrice" class="block font-extrabold text-red-950 mb-1">
                                    Offer Sale Price (BDT ৳)
                                </label>
                                <input type="number" step="0.01" min="1" name="offer_price" id="vspOfferPrice"
                                       value="{{ old('offer_price', 2850) }}"
                                       oninput="window.vspUpdatePromoPreview()"
                                       placeholder="e.g. 2850 (cuts 3000 with a line)"
                                       class="w-full rounded-xl border border-red-300 bg-white px-3 py-2 text-sm font-extrabold text-red-700">
                                <span class="text-[10px] text-red-800/80 block mt-0.5">Must be lower than normal price.</span>
                            </div>

                            <div>
                                <label for="vspOfferPct" class="block font-extrabold text-red-950 mb-1">
                                    Or Offer Discount % (Required for Multi-Spec)
                                </label>
                                <input type="number" step="0.5" min="0.5" max="90" name="offer_discount_pct" id="vspOfferPct"
                                       value="{{ old('offer_discount_pct') }}"
                                       oninput="window.vspUpdatePromoPreview()"
                                       placeholder="e.g. 10 (% off normal/spec prices)"
                                       class="w-full rounded-xl border border-red-300 bg-white px-3 py-2 text-sm font-bold text-red-900">
                            </div>
                        </div>

                        {{-- Date Fixing System: From ___ , To ___ (To can be empty for indefinite!) --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label for="vspOfferStart" class="block font-extrabold text-red-950 mb-1">
                                    Offer Period — From:
                                </label>
                                <input type="datetime-local" name="offer_starts_at" id="vspOfferStart"
                                       value="{{ old('offer_starts_at') }}"
                                       onchange="window.vspUpdatePromoPreview()"
                                       class="w-full rounded-xl border border-red-300 bg-white px-3 py-2 text-red-950 font-semibold">
                                <span class="text-[10px] text-red-800/80 block mt-0.5">Leave blank to activate immediately.</span>
                            </div>

                            <div>
                                <div class="flex items-center justify-between mb-1">
                                    <label for="vspOfferEnd" class="font-extrabold text-red-950">
                                        To (Optional — Empty = Indefinite):
                                    </label>
                                    <button type="button"
                                            onclick="document.getElementById('vspOfferEnd').value=''; window.vspUpdatePromoPreview();"
                                            class="text-[10px] font-extrabold text-red-700 underline">
                                        Keep Indefinite
                                    </button>
                                </div>
                                <input type="datetime-local" name="offer_ends_at" id="vspOfferEnd"
                                       value="{{ old('offer_ends_at') }}"
                                       onchange="window.vspUpdatePromoPreview()"
                                       class="w-full rounded-xl border border-red-300 bg-white px-3 py-2 text-red-950 font-semibold">
                                <span class="text-[10px] text-red-800/80 block mt-0.5">Leave empty so the Sale! offer stays active indefinitely.</span>
                            </div>
                        </div>
                    </div>

                    {{-- Live Visual Preview of Circular Red "Sale!" Banner & Strikethrough Price --}}
                    <div class="lg:col-span-4 rounded-2xl bg-white border border-red-200 p-3 flex items-center gap-3 shadow-xs">
                        <div class="relative w-20 h-20 rounded-xl border border-slate-200 bg-slate-50 flex items-center justify-center shrink-0 overflow-hidden">
                            <span class="absolute top-1.5 left-1.5 w-9 h-9 rounded-full bg-red-600 text-white text-[9px] font-extrabold flex items-center justify-center shadow">
                                Sale!
                            </span>
                            <span class="text-2xl opacity-40">🔩</span>
                        </div>
                        <div class="min-w-0">
                            <div class="text-[10px] font-extrabold uppercase text-red-700">Live Card Preview</div>
                            <div class="flex items-baseline gap-1.5 flex-wrap mt-0.5">
                                <span id="vspPrevNormal" class="text-xs text-slate-400 line-through font-semibold">৳3,000</span>
                                <span id="vspPrevOffer" class="text-base font-extrabold text-red-600">৳2,850</span>
                            </div>
                            <div id="vspPrevWindow" class="text-[10px] font-bold text-emerald-800 mt-1">
                                Special Offer · Ongoing (Indefinite)
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- ==============================================================
                 MODULE C: ALIEXPRESS PROGRESSIVE WHOLESALE TIERS (`wholesale_enabled`)
                 ============================================================== --}}
            <div class="rounded-2xl border border-amber-300 bg-amber-50/55 p-4 space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <label class="inline-flex items-center gap-2 cursor-pointer font-extrabold text-sm text-amber-950">
                        <input type="checkbox" name="wholesale_enabled" id="vspWholesaleEnabled" value="1"
                               {{ old('wholesale_enabled', true) ? 'checked' : '' }}
                               onchange="window.vspSyncWholesaleBox()"
                               class="w-4 h-4 rounded text-emerald-900 focus:ring-emerald-800">
                        <span>📦 Enable AliExpress-Style Progressive Wholesale Bulk Discount</span>
                    </label>
                    <span class="text-[11px] font-bold text-amber-900">
                        Default: 5% off at 100+ qty, +2% extra for every additional 100 qty
                    </span>
                </div>

                <div id="vspWholesaleConfigBox" class="grid grid-cols-2 sm:grid-cols-5 gap-3 pt-1">
                    <div>
                        <label class="block font-extrabold text-amber-950 mb-1">Min Wholesale Qty</label>
                        <input type="number" name="wholesale_min_qty" id="vspWsMin" min="2" max="10000"
                               value="{{ old('wholesale_min_qty', 100) }}"
                               oninput="window.vspRenderWholesalePreview()"
                               class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 font-bold text-amber-950">
                    </div>
                    <div>
                        <label class="block font-extrabold text-amber-950 mb-1">Base Discount %</label>
                        <input type="number" step="0.5" name="wholesale_base_discount_pct" id="vspWsBasePct" min="0.5" max="75"
                               value="{{ old('wholesale_base_discount_pct', 5) }}"
                               oninput="window.vspRenderWholesalePreview()"
                               class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 font-bold text-amber-950">
                    </div>
                    <div>
                        <label class="block font-extrabold text-amber-950 mb-1">Step Every X Qty</label>
                        <input type="number" name="wholesale_step_qty" id="vspWsStepQty" min="1" max="10000"
                               value="{{ old('wholesale_step_qty', 100) }}"
                               oninput="window.vspRenderWholesalePreview()"
                               class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 font-bold text-amber-950">
                    </div>
                    <div>
                        <label class="block font-extrabold text-amber-950 mb-1">+Extra % / Step</label>
                        <input type="number" step="0.5" name="wholesale_step_discount_pct" id="vspWsStepPct" min="0" max="50"
                               value="{{ old('wholesale_step_discount_pct', 2) }}"
                               oninput="window.vspRenderWholesalePreview()"
                               class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 font-bold text-amber-950">
                    </div>
                    <div class="col-span-2 sm:col-span-1">
                        <label class="block font-extrabold text-amber-950 mb-1">Max Discount Cap %</label>
                        <input type="number" step="0.5" name="wholesale_max_discount_pct" id="vspWsMaxPct" min="1" max="80"
                               value="{{ old('wholesale_max_discount_pct', 25) }}"
                               oninput="window.vspRenderWholesalePreview()"
                               class="w-full rounded-xl border border-amber-300 bg-white px-3 py-2 font-bold text-amber-950">
                    </div>
                </div>
                <div id="vspWholesalePreviewText" class="text-[11px] font-bold text-emerald-900 pt-0.5"></div>
            </div>

            {{-- Row 4: Image Upload / URL + Bullet Highlights + Description --}}
            <div class="grid grid-cols-1 md:grid-cols-2 gap-3.5">
                <div class="space-y-2.5">
                    <div>
                        <label class="block font-extrabold text-[var(--foreground)] mb-1">Upload Product Photo (JPG/PNG/WEBP)</label>
                        <input type="file" name="image_file" accept="image/*"
                               class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2 text-[var(--foreground)]">
                    </div>
                    <div>
                        <label for="vspImageUrl" class="block font-extrabold text-[var(--foreground)] mb-1">Or Direct Image URL</label>
                        <input type="text" name="image_url" id="vspImageUrl"
                               value="{{ old('image_url') }}"
                               placeholder="https://... or /images/spare-parts/..."
                               class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2 text-[var(--foreground)]">
                    </div>
                </div>

                <div class="space-y-2.5">
                    <div>
                        <label for="vspHighlights" class="block font-extrabold text-[var(--foreground)] mb-1">Key Bullet Highlights (One per line)</label>
                        <textarea name="highlights_text" id="vspHighlights" rows="3"
                                  placeholder="Condition: Brand New&#10;Quality: Tested 100% Working&#10;Warranty: 180 Days"
                                  class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2 text-[var(--foreground)]">{{ old('highlights_text') }}</textarea>
                    </div>
                    <div>
                        <label for="vspDescription" class="block font-extrabold text-[var(--foreground)] mb-1">Compatibility &amp; Technical Description</label>
                        <textarea name="description" id="vspDescription" rows="2"
                                  placeholder="Detailed part numbers, supported laptop/phone sub-models, connector pin count..."
                                  class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2 text-[var(--foreground)]">{{ old('description') }}</textarea>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap items-center justify-end gap-2.5 pt-2 border-t border-[var(--border)]">
                <button type="button"
                        onclick="window.vspCloseForm()"
                        class="px-5 py-2.5 rounded-full border border-[var(--border)] bg-white text-xs font-bold text-[var(--foreground)] hover:bg-slate-50 transition">
                    Cancel
                </button>
                <button type="submit"
                        id="vspSubmitBtn"
                        class="px-6 py-2.5 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-xs font-black shadow-sm hover:opacity-90 transition">
                    Publish Spare Part Listing
                </button>
            </div>
        </form>
    </div>

    {{-- Search & Filter Bar for Vendor's Own Spare Part Inventory --}}
    <div class="rounded-2xl border border-[var(--border)] bg-[var(--card)] p-3.5 sm:p-4 shadow-sm space-y-3">
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-2.5">
            <form action="{{ route('vendor.dashboard') }}" method="GET" class="flex-1 flex items-center gap-2">
                <input type="hidden" name="tab" value="spare_parts">
                <input type="hidden" name="shop_id" value="{{ $selectedShopId }}">
                @if($spFilterVal !== 'all')
                    <input type="hidden" name="sp_filter" value="{{ $spFilterVal }}">
                @endif
                <input type="text" name="sp_q" value="{{ $spSearchVal }}"
                       placeholder="Search your spare parts by title, SKU, brand, category..."
                       class="flex-1 rounded-full border border-[var(--border)] bg-white px-4 py-2 text-xs font-semibold text-[var(--foreground)]">
                <button type="submit"
                        class="px-4 py-2 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] text-xs font-bold shrink-0">
                    Search Inventory
                </button>
            </form>

            {{-- Filter Pills --}}
            <div class="flex items-center gap-1.5 overflow-x-auto no-scrollbar text-xs">
                @foreach([
                    'all' => 'All (' . number_format($spStats['total_count']) . ')',
                    'on_sale' => '🔴 On Sale! (' . number_format($spStats['offer_count']) . ')',
                    'wholesale' => '📦 Wholesale (' . number_format($spStats['wholesale_count']) . ')',
                    'specs' => '🎛️ Multi-Spec',
                    'paused' => '⏸️ Paused',
                ] as $fKey => $fLabel)
                    <a href="{{ route('vendor.dashboard', array_filter(['tab' => 'spare_parts', 'shop_id' => $selectedShopId, 'sp_q' => $spSearchVal ?: null, 'sp_filter' => $fKey === 'all' ? null : $fKey])) }}"
                       class="shrink-0 px-3 py-1.5 rounded-full font-bold border transition {{ $spFilterVal === $fKey ? 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] border-[var(--accent-dark)]' : 'bg-[var(--mint-50)] text-[var(--foreground)] border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                        {{ $fLabel }}
                    </a>
                @endforeach
            </div>
        </div>

        {{-- Paginated Vendor Spare Part Listings --}}
        @if(($vendorSpareParts ?? collect())->isEmpty())
            <div class="py-10 text-center text-xs text-[var(--muted-foreground)]">
                <div class="text-2xl mb-1.5">🔩</div>
                <p class="font-bold text-[var(--foreground)]">No spare part listings found in this filter.</p>
                <button type="button"
                        onclick="window.vspOpenCreateForm()"
                        class="mt-3 px-4 py-2 rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] font-bold">
                    + Create Your First Spare Part Listing
                </button>
            </div>
        @else
            <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3.5 pt-1">
                @foreach($vendorSpareParts as $vItem)
                    @php
                        $vOfferActive = $vItem->isOfferActive();
                        $vEffBase = $vItem->effectiveBasePrice();
                        $vEffMax = $vItem->effectiveMaxPrice();
                        $vNormBase = $vItem->resolvedNormalBasePrice();
                        $vShop = $vItem->shop;
                        $editPayload = [
                            'id' => $vItem->id,
                            'update_url' => route('vendor.spare-parts.update', ['id' => $vItem->id]),
                            'shop_id' => $vItem->shop_id,
                            'title' => $vItem->title,
                            'part_category' => $vItem->part_category,
                            'brand' => $vItem->brand,
                            'compatible_model' => $vItem->compatible_model,
                            'condition_label' => $vItem->condition_label,
                            'sku' => $vItem->sku,
                            'product_group_key' => $vItem->product_group_key,
                            'stock_quantity' => (int) $vItem->stock_quantity,
                            'handling_hours' => (int) $vItem->handling_hours,
                            'warranty_days' => (int) $vItem->warranty_days,
                            'assembly_fee' => (float) $vItem->assembly_fee,
                            'base_price' => (float) $vNormBase,
                            'has_specs' => (bool) $vItem->has_specs,
                            'spec_label' => $vItem->spec_label ?: 'Quality',
                            'spec_options' => $vItem->spec_options ?? [],
                            'offer_enabled' => (bool) $vItem->offer_enabled,
                            'offer_price' => $vItem->offer_price !== null ? (float) $vItem->offer_price : '',
                            'offer_discount_pct' => $vItem->offer_discount_pct !== null ? (float) $vItem->offer_discount_pct : '',
                            'offer_starts_at' => $vItem->offer_starts_at ? $vItem->offer_starts_at->format('Y-m-d\TH:i') : '',
                            'offer_ends_at' => $vItem->offer_ends_at ? $vItem->offer_ends_at->format('Y-m-d\TH:i') : '',
                            'wholesale_enabled' => (bool) $vItem->wholesale_enabled,
                            'wholesale_min_qty' => (int) ($vItem->wholesale_min_qty ?: 100),
                            'wholesale_base_discount_pct' => (float) ($vItem->wholesale_base_discount_pct ?? 5),
                            'wholesale_step_qty' => (int) ($vItem->wholesale_step_qty ?: 100),
                            'wholesale_step_discount_pct' => (float) ($vItem->wholesale_step_discount_pct ?? 2),
                            'wholesale_max_discount_pct' => (float) ($vItem->wholesale_max_discount_pct ?? 25),
                            'image_url' => $vItem->image_url,
                            'highlights_text' => is_array($vItem->highlights) ? implode("\n", $vItem->highlights) : '',
                            'description' => $vItem->description ?? '',
                        ];
                    @endphp

                    <div class="rounded-2xl border {{ $vItem->is_active ? 'border-[var(--border)] bg-white' : 'border-slate-300 bg-slate-50 opacity-80' }} p-3.5 flex flex-col justify-between gap-3 shadow-xs">
                        <div class="flex items-start gap-3">
                            {{-- Thumbnail with Circular Red "Sale!" Badge --}}
                            <div class="relative w-20 h-20 rounded-xl border border-emerald-900/10 bg-white p-1.5 shrink-0 overflow-hidden">
                                @if($vOfferActive)
                                    <span class="absolute top-1 left-1 z-10 w-8 h-8 rounded-full bg-red-600 text-white text-[9px] font-extrabold flex items-center justify-center shadow">
                                        Sale!
                                    </span>
                                @endif
                                <img src="{{ $vItem->image_url }}"
                                     alt="{{ $vItem->title }}"
                                     loading="lazy"
                                     onerror="this.onerror=null;this.src='/images/spare-parts/samsung-galaxy-s21-fe-5g-display.webp';"
                                     class="w-full h-full object-contain">
                            </div>

                            <div class="min-w-0 flex-1 space-y-1">
                                <div class="flex items-center gap-1.5 flex-wrap text-[10px]">
                                    <span class="px-2 py-0.5 rounded-full font-bold {{ $vItem->is_active ? 'bg-emerald-100 text-emerald-900' : 'bg-slate-200 text-slate-700' }}">
                                        {{ $vItem->is_active ? '● Live' : '⏸ Paused' }}
                                    </span>
                                    <span class="font-bold text-emerald-800 truncate">{{ $vItem->part_category }}</span>
                                    @if($vShop?->display_branch_tag)
                                        <span class="px-1.5 py-0.5 rounded bg-emerald-900 text-white font-bold">
                                            {{ $vShop->display_branch_tag }}
                                        </span>
                                    @endif
                                </div>

                                <h4 class="text-xs sm:text-sm font-extrabold text-[var(--foreground)] line-clamp-2 leading-snug">
                                    {{ $vItem->title }}
                                </h4>

                                {{-- Price & Offer Status --}}
                                <div class="flex items-baseline gap-1.5 flex-wrap">
                                    @if($vOfferActive && $vNormBase > $vEffBase)
                                        <span class="text-[11px] text-slate-400 line-through font-semibold">৳{{ number_format($vNormBase, 0) }}</span>
                                    @endif
                                    <span class="text-sm font-black {{ $vOfferActive ? 'text-red-600' : 'text-[var(--foreground)]' }}">
                                        ৳{{ number_format($vEffBase, 0) }}{{ ($vItem->has_specs && $vEffMax && $vEffMax > $vEffBase) ? (' – ৳' . number_format($vEffMax, 0)) : '' }}
                                    </span>
                                </div>

                                <div class="flex flex-wrap items-center gap-1.5 text-[10px] text-[var(--muted-foreground)]">
                                    <span>Stock: <strong>{{ number_format((int) $vItem->stock_quantity) }}</strong></span>
                                    <span>• Sold: <strong>{{ number_format((int) $vItem->sold_count) }}</strong></span>
                                    @if($vItem->has_specs)
                                        <span class="text-emerald-800 font-bold">• {{ count($vItem->spec_options ?? []) }} Specs</span>
                                    @endif
                                    @if($vItem->wholesale_enabled)
                                        <span class="text-amber-900 font-bold">• -{{ number_format((float) $vItem->wholesale_base_discount_pct, 0) }}% @ {{ (int) $vItem->wholesale_min_qty }}+</span>
                                    @endif
                                </div>

                                @if($vItem->offer_enabled)
                                    <div class="text-[10px] font-bold {{ $vOfferActive ? 'text-red-700' : 'text-amber-700' }}">
                                        @if($vOfferActive)
                                            🔴 {{ $vItem->offerWindowLabel() }}
                                        @elseif($vItem->offer_starts_at && now()->lt($vItem->offer_starts_at))
                                            🕒 Offer Scheduled: Starts {{ $vItem->offer_starts_at->format('M j, Y H:i') }}
                                        @else
                                            ⌛ Offer Window Ended ({{ $vItem->offer_ends_at?->format('M j, Y') }})
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>

                        {{-- Card Footer Actions --}}
                        <div class="flex flex-wrap items-center justify-between gap-1.5 pt-2 border-t border-[var(--border)] text-[11px]">
                            <div class="flex items-center gap-1.5">
                                <button type="button"
                                        onclick='window.vspOpenEditForm(@json($editPayload))'
                                        class="px-3 py-1 rounded-full bg-[var(--mint-100)] hover:bg-[var(--mint-200)] text-[var(--accent-dark)] font-extrabold transition">
                                    ✏️ Edit / Offer
                                </button>

                                <form action="{{ route('vendor.spare-parts.toggle', ['id' => $vItem->id]) }}" method="POST" class="inline">
                                    @csrf
                                    <input type="hidden" name="return_shop_id" value="{{ $selectedShopId }}">
                                    <button type="submit"
                                            class="px-2.5 py-1 rounded-full border border-[var(--border)] bg-white hover:bg-slate-50 font-bold text-[var(--foreground)] transition">
                                        {{ $vItem->is_active ? 'Pause' : 'Activate' }}
                                    </button>
                                </form>
                            </div>

                            <div class="flex items-center gap-1.5">
                                @if($vItem->is_active)
                                    <a href="{{ route('spare-parts.show', ['slug' => $vItem->slug]) }}"
                                       class="px-2.5 py-1 rounded-full text-emerald-900 font-bold hover:underline">
                                        View ↗
                                    </a>
                                @endif

                                <form action="{{ route('vendor.spare-parts.destroy', ['id' => $vItem->id]) }}"
                                      method="POST"
                                      onsubmit="return confirm('Delete spare part listing \'{{ addslashes($vItem->title) }}\'?');"
                                      class="inline">
                                    @csrf
                                    @method('DELETE')
                                    <input type="hidden" name="return_shop_id" value="{{ $selectedShopId }}">
                                    <button type="submit"
                                            class="px-2.5 py-1 rounded-full text-rose-700 hover:bg-rose-50 font-bold transition">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if($vendorSpareParts->hasPages())
                <div class="pt-4">
                    {{ $vendorSpareParts->appends(['tab' => 'spare_parts', 'shop_id' => $selectedShopId])->links() }}
                </div>
            @endif
        @endif
    </div>
</div>

<script>
(function () {
    const storeUrl = @json(route('vendor.spare-parts.store'));

    function createSpecRowHtml(opt = {}) {
        const name = (opt.name || '').replace(/"/g, '&quot;');
        const price = opt.normal_price ?? opt.price ?? '';
        const sub = (opt.subtitle || '').replace(/"/g, '&quot;');
        const stock = opt.stock ?? '';
        return `
            <div class="vsp-spec-row grid grid-cols-12 gap-2 items-center bg-white p-2 rounded-xl border border-emerald-900/15">
                <div class="col-span-4">
                    <input type="text" name="spec_names[]" value="${name}" placeholder="Option Name (e.g. IPS / Original)"
                           class="w-full rounded-lg border border-emerald-900/20 px-2.5 py-1.5 text-xs font-bold text-emerald-950">
                </div>
                <div class="col-span-3">
                    <input type="number" step="0.01" min="1" name="spec_prices[]" value="${price}" placeholder="Price ৳"
                           oninput="window.vspUpdatePromoPreview()"
                           class="vsp-spec-price-input w-full rounded-lg border border-emerald-900/20 px-2.5 py-1.5 text-xs font-extrabold text-emerald-950">
                </div>
                <div class="col-span-3">
                    <input type="text" name="spec_subtitles[]" value="${sub}" placeholder="Note (e.g. 100% Genuine)"
                           class="w-full rounded-lg border border-emerald-900/20 px-2.5 py-1.5 text-xs text-emerald-950">
                </div>
                <div class="col-span-1">
                    <input type="number" min="0" name="spec_stocks[]" value="${stock}" placeholder="Qty"
                           class="w-full rounded-lg border border-emerald-900/20 px-2 py-1.5 text-xs text-emerald-950">
                </div>
                <div class="col-span-1 text-right">
                    <button type="button" onclick="this.closest('.vsp-spec-row').remove(); window.vspUpdatePromoPreview();"
                            class="w-7 h-7 rounded-lg bg-rose-50 text-rose-700 font-bold hover:bg-rose-100">✕</button>
                </div>
            </div>
        `;
    }

    window.vspAddSpecRow = function (opt = {}) {
        const container = document.getElementById('vspSpecRowsContainer');
        if (!container) return;
        container.insertAdjacentHTML('beforeend', createSpecRowHtml(opt));
    };

    window.vspSyncPricingMode = function () {
        const hasSpecs = document.getElementById('vspHasSpecs')?.checked;
        const singleBox = document.getElementById('vspSinglePriceBox');
        const specBox = document.getElementById('vspSpecBuilderBox');
        const container = document.getElementById('vspSpecRowsContainer');

        if (hasSpecs) {
            singleBox?.classList.add('hidden');
            specBox?.classList.remove('hidden');
            if (container && container.children.length === 0) {
                window.vspAddSpecRow({ name: 'High Quality (IPS)', price: 1850, subtitle: 'Tested Grade A', stock: 120 });
                window.vspAddSpecRow({ name: 'Original Service Pack', price: 3200, subtitle: '100% OEM Genuine', stock: 60 });
            }
        } else {
            singleBox?.classList.remove('hidden');
            specBox?.classList.add('hidden');
        }
        window.vspUpdatePromoPreview();
    };

    window.vspSyncOfferBox = function () {
        const enabled = document.getElementById('vspOfferEnabled')?.checked;
        const box = document.getElementById('vspOfferConfigBox');
        if (enabled) {
            box?.classList.remove('hidden');
            window.vspUpdatePromoPreview();
        } else {
            box?.classList.add('hidden');
        }
    };

    window.vspSyncWholesaleBox = function () {
        const enabled = document.getElementById('vspWholesaleEnabled')?.checked;
        const box = document.getElementById('vspWholesaleConfigBox');
        const prev = document.getElementById('vspWholesalePreviewText');
        if (enabled) {
            box?.classList.remove('hidden');
            prev?.classList.remove('hidden');
            window.vspRenderWholesalePreview();
        } else {
            box?.classList.add('hidden');
            prev?.classList.add('hidden');
        }
    };

    window.vspUpdatePromoPreview = function () {
        const hasSpecs = document.getElementById('vspHasSpecs')?.checked;
        let normalPrice = Number(document.getElementById('vspBasePrice')?.value || 3000);

        if (hasSpecs) {
            const specPrices = Array.from(document.querySelectorAll('.vsp-spec-price-input'))
                .map(el => Number(el.value || 0))
                .filter(v => v > 0);
            if (specPrices.length > 0) {
                normalPrice = Math.min(...specPrices);
            }
        }

        const rawOffer = Number(document.getElementById('vspOfferPrice')?.value || 0);
        const rawPct = Number(document.getElementById('vspOfferPct')?.value || 0);
        let offerPrice = normalPrice;

        if (!hasSpecs && rawOffer > 0 && rawOffer < normalPrice) {
            offerPrice = rawOffer;
        } else if (rawPct > 0 && rawPct < 95) {
            offerPrice = Math.round(normalPrice * (1 - rawPct / 100));
        } else if (rawOffer > 0 && rawOffer < normalPrice) {
            offerPrice = rawOffer;
        }

        const normEl = document.getElementById('vspPrevNormal');
        const offEl = document.getElementById('vspPrevOffer');
        const winEl = document.getElementById('vspPrevWindow');

        if (normEl) normEl.textContent = '৳' + normalPrice.toLocaleString('en-BD');
        if (offEl) offEl.textContent = '৳' + offerPrice.toLocaleString('en-BD');

        const startVal = document.getElementById('vspOfferStart')?.value || '';
        const endVal = document.getElementById('vspOfferEnd')?.value || '';
        if (winEl) {
            if (!endVal) {
                winEl.textContent = startVal
                    ? `From ${startVal.replace('T', ' ')} · Indefinite`
                    : 'Special Offer · Ongoing (Indefinite)';
            } else {
                winEl.textContent = `Valid ${startVal ? startVal.split('T')[0] : 'Now'} → ${endVal.split('T')[0]}`;
            }
        }
    };

    window.vspRenderWholesalePreview = function () {
        const prev = document.getElementById('vspWholesalePreviewText');
        if (!prev) return;
        const minQ = Math.max(2, Number(document.getElementById('vspWsMin')?.value || 100));
        const baseP = Number(document.getElementById('vspWsBasePct')?.value || 5);
        const stepQ = Math.max(1, Number(document.getElementById('vspWsStepQty')?.value || 100));
        const stepP = Number(document.getElementById('vspWsStepPct')?.value || 2);
        const maxP = Math.max(baseP, Number(document.getElementById('vspWsMaxPct')?.value || 25));

        const t1 = `${minQ}–${minQ + stepQ - 1} pcs: -${baseP}%`;
        const t2 = `${minQ + stepQ}–${minQ + 2 * stepQ - 1} pcs: -${Math.min(maxP, baseP + stepP)}%`;
        const t3 = `≥${minQ + 2 * stepQ} pcs: -${Math.min(maxP, baseP + 2 * stepP)}% (cap ${maxP}%)`;
        prev.textContent = `Live Tier Preview: ${t1}  |  ${t2}  |  ${t3}`;
    };

    window.vspOpenCreateForm = function () {
        const wrap = document.getElementById('vspFormWrapper');
        const form = document.getElementById('vspBuilderForm');
        if (!wrap || !form) return;

        form.action = storeUrl;
        document.getElementById('vspMethodInput').value = 'POST';
        document.getElementById('vspFormTitle').textContent = '+ Publish New Spare Part Listing';
        document.getElementById('vspSubmitBtn').textContent = 'Publish Spare Part Listing';

        form.reset();
        document.getElementById('vspSpecRowsContainer').innerHTML = '';
        document.getElementById('vspWholesaleEnabled').checked = true;
        window.vspSyncPricingMode();
        window.vspSyncOfferBox();
        window.vspSyncWholesaleBox();

        wrap.classList.remove('hidden');
        wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.vspOpenEditForm = function (data) {
        const wrap = document.getElementById('vspFormWrapper');
        const form = document.getElementById('vspBuilderForm');
        if (!wrap || !form || !data) return;

        form.action = data.update_url;
        document.getElementById('vspMethodInput').value = 'PUT';
        document.getElementById('vspFormTitle').textContent = '✏️ Edit Spare Part: ' + (data.title || '');
        document.getElementById('vspSubmitBtn').textContent = 'Save Listing Updates';

        document.getElementById('vspShopId').value = data.shop_id;
        document.getElementById('vspCategory').value = data.part_category || 'Display & Touch';
        document.getElementById('vspBrand').value = data.brand || '';
        document.getElementById('vspTitle').value = data.title || '';
        document.getElementById('vspModel').value = data.compatible_model || '';
        document.getElementById('vspCondition').value = data.condition_label || 'Brand New';
        document.getElementById('vspSku').value = data.sku || '';
        document.getElementById('vspGroupKey').value = data.product_group_key || '';
        document.getElementById('vspStock').value = data.stock_quantity ?? 100;
        document.getElementById('vspHandling').value = data.handling_hours ?? 2;
        document.getElementById('vspWarranty').value = data.warranty_days ?? 90;
        document.getElementById('vspAssemblyFee').value = data.assembly_fee ?? 0;
        document.getElementById('vspBasePrice').value = data.base_price ?? 1000;
        document.getElementById('vspImageUrl').value = data.image_url || '';
        document.getElementById('vspHighlights').value = data.highlights_text || '';
        document.getElementById('vspDescription').value = data.description || '';

        const hasSpecsEl = document.getElementById('vspHasSpecs');
        hasSpecsEl.checked = !!data.has_specs;
        document.getElementById('vspSpecLabel').value = data.spec_label || 'Quality';
        const specContainer = document.getElementById('vspSpecRowsContainer');
        specContainer.innerHTML = '';
        if (Array.isArray(data.spec_options) && data.spec_options.length > 0) {
            data.spec_options.forEach(opt => window.vspAddSpecRow(opt));
        }
        window.vspSyncPricingMode();

        const offerEl = document.getElementById('vspOfferEnabled');
        offerEl.checked = !!data.offer_enabled;
        document.getElementById('vspOfferPrice').value = data.offer_price ?? '';
        document.getElementById('vspOfferPct').value = data.offer_discount_pct ?? '';
        document.getElementById('vspOfferStart').value = data.offer_starts_at || '';
        document.getElementById('vspOfferEnd').value = data.offer_ends_at || '';
        window.vspSyncOfferBox();

        const wsEl = document.getElementById('vspWholesaleEnabled');
        wsEl.checked = !!data.wholesale_enabled;
        document.getElementById('vspWsMin').value = data.wholesale_min_qty ?? 100;
        document.getElementById('vspWsBasePct').value = data.wholesale_base_discount_pct ?? 5;
        document.getElementById('vspWsStepQty').value = data.wholesale_step_qty ?? 100;
        document.getElementById('vspWsStepPct').value = data.wholesale_step_discount_pct ?? 2;
        document.getElementById('vspWsMaxPct').value = data.wholesale_max_discount_pct ?? 25;
        window.vspSyncWholesaleBox();

        wrap.classList.remove('hidden');
        wrap.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    window.vspCloseForm = function () {
        document.getElementById('vspFormWrapper')?.classList.add('hidden');
    };

    window.vspSyncPricingMode();
    window.vspSyncOfferBox();
    window.vspSyncWholesaleBox();
})();
</script>

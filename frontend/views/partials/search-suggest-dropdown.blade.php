{{-- Shared Live Search Typeahead Dropdown (used by header desktop, header mobile, and spare-parts catalog search) --}}
<div
    x-show="open && ((!(query && query.trim().length >= 2) && recentSearches.length > 0) || loading || hasResults || (query && query.trim().length >= 2 && fetchedOnce))"
    x-cloak
    x-transition.opacity.duration.120ms
    role="listbox"
    aria-label="Search suggestions"
    class="absolute left-0 right-0 top-full mt-1.5 z-[95] rounded-lg bg-white text-slate-900 border border-emerald-900/20 shadow-2xl overflow-hidden max-h-[70vh] overflow-y-auto"
>
    {{-- Empty-query Focus State: User/Demo Recent Searches --}}
    <template x-if="!(query && query.trim().length >= 2) && recentSearches.length > 0">
        <div class="py-1.5">
            <div class="px-3.5 py-1.5 flex items-center justify-between gap-2 border-b border-emerald-900/10 bg-emerald-50/40">
                <span class="text-[10px] font-semibold uppercase tracking-wider text-emerald-900/75"
                      x-text="window.MeramotI18n ? window.MeramotI18n.t('recently_viewed', 'Recent Searches') : 'Recent Searches'"></span>
                <button type="button"
                        @mousedown.prevent.stop="clearAllRecent()"
                        class="text-[11px] font-medium text-slate-500 hover:text-emerald-900 transition cursor-pointer"
                        x-text="window.MeramotI18n ? window.MeramotI18n.t('clear_recent_searches', 'Clear all') : 'Clear all'"></button>
            </div>
            <div class="divide-y divide-emerald-900/5">
                <template x-for="(item, idx) in recentSearches" :key="'recent-' + (item.normalized || item.label)">
                    <div :class="isItemActive('recent', idx) ? 'bg-emerald-50' : 'hover:bg-emerald-50/70'"
                         class="flex items-center justify-between gap-2 px-3.5 py-2 transition">
                        <a :href="item.href"
                           :data-recent-search-query="item.label"
                           data-no-prefetch="true"
                           @click="selectRecentItem(item)"
                           class="flex items-center gap-2.5 min-w-0 flex-1 text-left text-xs sm:text-sm font-medium text-slate-800">
                            <svg class="h-3.5 w-3.5 shrink-0 text-slate-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="9"/>
                                <polyline points="12 7 12 12 15.5 14"/>
                            </svg>
                            <span class="truncate" x-text="item.label"></span>
                        </a>
                        <button type="button"
                                @mousedown.prevent.stop="removeRecent(item.label)"
                                :aria-label="'Remove recent search ' + item.label"
                                class="inline-flex h-6 w-6 shrink-0 items-center justify-center rounded text-[11px] text-slate-400 hover:bg-slate-200/70 hover:text-slate-700 transition cursor-pointer">
                            ✕
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </template>

    <template x-if="(query && query.trim().length >= 2) && loading && !hasResults">
        <div class="px-4 py-3 text-xs text-slate-500 flex items-center gap-2">
            <span class="inline-block w-3 h-3 rounded-full border-2 border-emerald-700 border-t-transparent animate-spin"></span>
            <span>Searching Meramot catalog...</span>
        </div>
    </template>

    <template x-if="!loading && fetchedOnce && !hasResults">
        <div class="px-4 py-3 text-xs text-slate-600 flex items-center justify-between gap-2">
            <span>No quick matches found for "<strong x-text="query.trim()"></strong>".</span>
            <span class="text-emerald-800 font-semibold shrink-0">Press Enter for full search →</span>
        </div>
    </template>

    <div class="flex flex-col">
        {{-- Matching Fixed Packages --}}
        <template x-if="packages.length > 0">
            <div
                :class="primarySection === 'packages' ? 'order-1' : 'order-4 border-t border-emerald-900/10'"
                class="py-1.5 bg-emerald-50/30"
            >
                <div class="px-3.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-900/70">
                    Fixed Service Packages
                </div>
                <template x-for="(pkg, idx) in packages" :key="'pkg-' + pkg.id + '-' + idx">
                    <a
                        :href="pkg.url"
                        @click="close()"
                        :class="isItemActive('pkg', idx) ? 'bg-emerald-50' : 'hover:bg-emerald-50/70'"
                        class="flex items-center justify-between gap-3 px-3.5 py-2 transition text-left"
                    >
                        <div class="min-w-0">
                            <div class="text-xs sm:text-sm font-bold text-slate-900 truncate" x-text="pkg.title"></div>
                            <div class="text-[11px] text-slate-500 truncate">
                                <span x-text="pkg.device_category"></span> · Flat-rate service
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-emerald-900 shrink-0 tabular-nums">
                            ৳<span x-text="Number(pkg.fixed_price || 0).toLocaleString()"></span>
                        </span>
                    </a>
                </template>
            </div>
        </template>

        {{-- Matching Categories --}}
        <template x-if="categories.length > 0">
            <div
                :class="primarySection === 'shops' || primarySection === 'packages' ? 'order-2' : 'order-1'"
                class="px-3 pt-2.5 pb-2 border-b border-emerald-900/10 bg-emerald-50/40"
            >
                <div class="text-[10px] font-semibold uppercase tracking-wider text-emerald-900/70 mb-1.5">
                    Categories
                </div>
                <div class="flex flex-wrap gap-1.5">
                    <template x-for="(cat, idx) in categories" :key="'cat-' + idx">
                        <a
                            :href="cat.url"
                            @click="close()"
                            :class="isItemActive('cat', idx) ? 'bg-emerald-900 text-white border-emerald-900' : 'bg-white text-emerald-900 border-emerald-900/20 hover:bg-emerald-100'"
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md border text-[11px] font-semibold transition"
                        >
                            <span x-text="cat.label"></span>
                        </a>
                    </template>
                </div>
            </div>
        </template>

        {{-- Matching Spare Parts --}}
        <template x-if="parts.length > 0">
            <div
                :class="primarySection === 'shops' || primarySection === 'packages' ? 'order-3' : 'order-2'"
                class="py-1.5"
            >
                <div class="px-3.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-900/70">
                    Spare Parts
                </div>
                <template x-for="(part, idx) in parts" :key="'part-' + part.slug + '-' + idx">
                    <a
                        :href="part.url"
                        @click="close()"
                        :class="isItemActive('part', idx) ? 'bg-emerald-50' : 'hover:bg-emerald-50/70'"
                        class="flex items-center gap-3 px-3.5 py-2 transition text-left"
                    >
                        <img
                            :src="part.image_url || '/images/meramot.svg'"
                            :alt="part.title"
                            class="w-9 h-9 rounded-lg object-contain bg-slate-50 border border-slate-200 shrink-0 p-0.5"
                            loading="lazy"
                            onerror="this.style.display='none'"
                        >
                        <div class="min-w-0 flex-1">
                            <div class="text-xs sm:text-sm font-bold text-slate-900 truncate" x-text="part.title"></div>
                            <div class="text-[11px] text-slate-500 truncate">
                                <span x-text="part.brand"></span>
                                <template x-if="part.brand && part.part_category">
                                    <span> · </span>
                                </template>
                                <span x-text="part.part_category"></span>
                            </div>
                        </div>
                        <span class="text-xs font-semibold text-emerald-900 shrink-0 tabular-nums">
                            ৳<span x-text="Number(part.price || 0).toLocaleString()"></span>
                        </span>
                    </a>
                </template>
            </div>
        </template>

        {{-- Matching Repair Shops --}}
        <template x-if="shops.length > 0">
            <div
                :class="primarySection === 'shops' ? 'order-1' : (primarySection === 'packages' ? 'order-4 border-t border-emerald-900/10' : 'order-3 border-t border-emerald-900/10')"
                class="py-1.5"
            >
                <div class="px-3.5 py-1 text-[10px] font-semibold uppercase tracking-wider text-emerald-900/70">
                    Verified Repair Shops
                </div>
                <template x-for="(shop, idx) in shops" :key="'shop-' + shop.slug + '-' + idx">
                    <a
                        :href="shop.url"
                        @click="close()"
                        :class="isItemActive('shop', idx) ? 'bg-emerald-50' : 'hover:bg-emerald-50/70'"
                        class="flex items-center justify-between gap-3 px-3.5 py-2 transition text-left"
                    >
                        <div class="min-w-0">
                            <div class="text-xs sm:text-sm font-bold text-slate-900 truncate" x-text="shop.name"></div>
                            <div class="text-[11px] text-slate-500 truncate">
                                <span x-text="[shop.area, shop.city].filter(Boolean).join(', ')"></span>
                            </div>
                        </div>
                        <span class="text-[11px] font-bold text-amber-700 shrink-0">
                            ★ <span x-text="Number(shop.rating || 4.8).toFixed(1)"></span>
                        </span>
                    </a>
                </template>
            </div>
        </template>
    </div>

    {{-- Footer submit CTA --}}
    <template x-if="hasResults">
        <button
            type="button"
            @mousedown.prevent="submitParentForm($el)"
            class="w-full px-4 py-2 text-xs font-semibold text-emerald-900 bg-emerald-50/70 hover:bg-emerald-100/80 border-t border-emerald-900/10 text-left flex items-center justify-between transition"
        >
            <span>See all results for "<span x-text="query.trim()"></span>"</span>
            <span x-text="scope === 'all' ? (primarySection === 'packages' ? 'Fixed Packages ↵' : (primarySection === 'shops' ? 'Repair Shops ↵' : 'Spare Parts ↵')) : 'Enter ↵'"></span>
        </button>
    </template>
</div>

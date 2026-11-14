@php
    $cityExpr = $cityExpr ?? 'city';
    $areaExpr = $areaExpr ?? 'area';
    $latExpr = $latExpr ?? 'lat';
    $lngExpr = $lngExpr ?? 'lng';
    $gpsAddressExpr = $gpsAddressExpr ?? null;
    $addressExpr = $addressExpr ?? null;
    $sameAsGpsExpr = $sameAsGpsExpr ?? null;
    $cityName = $cityName ?? 'city';
    $areaName = $areaName ?? 'area';
    $contextKey = $contextKey ?? 'branchLoc';
    $cityLabel = $cityLabel ?? 'District / City *';
    $areaSelectLabel = $areaSelectLabel ?? 'Narrowed-Down Location *';
    $areaInputLabel = $areaInputLabel ?? 'Area / Sector / Place Name *';
@endphp

<div
    x-data="{
        suggestionsOpen: false,
        suggestions: [],
        highlightIdx: -1,
        searchTimer: null,
        loadingOsm: false,

        get districts() {
            return window.MeramotLocationCatalog ? window.MeramotLocationCatalog.getDistricts() : ['Dhaka', 'Chattogram', 'Sylhet'];
        },

        get areasForSelectedCity() {
            const c = ({{ $cityExpr }}) || 'Dhaka';
            const a = ({{ $areaExpr }}) || '';
            return window.MeramotLocationCatalog ? window.MeramotLocationCatalog.getPlacesByCity(c, a) : [];
        },

        applyPlaceChoice(place, fillAddress = true) {
            if (!place) return;
            if (place.city) {
                {{ $cityExpr }} = window.MeramotLocationCatalog
                    ? window.MeramotLocationCatalog.findDistrict(place.city)
                    : place.city;
            }
            if (place.area) {
                {{ $areaExpr }} = place.area;
            }
            if (typeof place.lat === 'number' && !Number.isNaN(place.lat)) {
                {{ $latExpr }} = place.lat.toFixed(6);
            }
            if (typeof place.lng === 'number' && !Number.isNaN(place.lng)) {
                {{ $lngExpr }} = place.lng.toFixed(6);
            }
            @if($gpsAddressExpr)
                if (fillAddress && place.address) {
                    {{ $gpsAddressExpr }} = place.address;
                }
            @endif
            @if($addressExpr && $sameAsGpsExpr)
                if (fillAddress && place.address && ({{ $sameAsGpsExpr }} || !({{ $addressExpr }}))) {
                    {{ $addressExpr }} = place.address;
                }
            @elseif($addressExpr)
                if (fillAddress && place.address && !({{ $addressExpr }})) {
                    {{ $addressExpr }} = place.address;
                }
            @endif
            this.suggestionsOpen = false;
        },

        onDistrictSelectChange() {
            const c = ({{ $cityExpr }}) || 'Dhaka';
            const list = this.areasForSelectedCity;
            if (list.length > 0) {
                const currentLower = String({{ $areaExpr }} || '').toLowerCase();
                const exists = list.some((p) => p.area.toLowerCase() === currentLower);
                if (!exists) {
                    this.applyPlaceChoice(list[0], false);
                    return;
                }
            }
            if (window.MeramotLocationCatalog) {
                const center = window.MeramotLocationCatalog.getDistrictCoords(c);
                {{ $latExpr }} = center.lat.toFixed(6);
                {{ $lngExpr }} = center.lng.toFixed(6);
            }
        },

        onAreaSelectChange(val) {
            if (!val) return;
            if (val === '__custom__') {
                this.$nextTick(() => this.$refs.areaInput?.focus());
                return;
            }
            const match = this.areasForSelectedCity.find((p) => p.area === val);
            if (match) {
                this.applyPlaceChoice(match, true);
            } else if (val) {
                {{ $areaExpr }} = val;
            }
        },

        onAreaTypeInput() {
            const q = String({{ $areaExpr }} || '').trim();
            const c = ({{ $cityExpr }}) || 'Dhaka';
            if (!window.MeramotLocationCatalog) return;

            this.suggestions = q
                ? window.MeramotLocationCatalog.search(q, c, 10)
                : this.areasForSelectedCity.slice(0, 10);
            this.suggestionsOpen = this.suggestions.length > 0;
            this.highlightIdx = this.suggestions.length > 0 ? 0 : -1;

            clearTimeout(this.searchTimer);
            if (q.length >= 2 && window.MeramotLocationPicker?.searchPlaces) {
                this.loadingOsm = true;
                this.searchTimer = setTimeout(async () => {
                    const merged = await window.MeramotLocationPicker.searchPlaces(q, c);
                    if (Array.isArray(merged) && merged.length > 0 && String({{ $areaExpr }} || '').trim() === q) {
                        this.suggestions = merged;
                        this.suggestionsOpen = true;
                    }
                    this.loadingOsm = false;
                }, 260);
            }
        }
    }"
    data-branch-location-selector="{{ $contextKey }}"
    class="grid grid-cols-1 sm:grid-cols-3 gap-3"
>
    {{-- 1. District (All 64 Districts) --}}
    <div>
        <label class="block text-[11px] font-bold text-[var(--muted-foreground)] mb-1">
            {{ $cityLabel }}
        </label>
        <select
            name="{{ $cityName }}"
            x-model="{{ $cityExpr }}"
            @change="onDistrictSelectChange()"
            x-effect="$nextTick(() => { if ({{ $cityExpr }}) $el.value = {{ $cityExpr }}; })"
            autocomplete="address-level2"
            data-district-select
            class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-xs font-semibold text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
        >
            @foreach(array_column(\App\Services\BangladeshLocationResolver::DISTRICTS, 'city') as $distOption)
                <option value="{{ $distOption }}">{{ $distOption }}</option>
            @endforeach
        </select>
    </div>

    {{-- 2. Narrowed-Down Neighborhood / Hub Selector --}}
    <div>
        <label class="block text-[11px] font-bold text-[var(--muted-foreground)] mb-1">
            {{ $areaSelectLabel }}
            <span class="font-normal text-[10px]" x-text="'(' + areasForSelectedCity.length + ' choices)'"></span>
        </label>
        <select
            @change="onAreaSelectChange($event.target.value)"
            x-effect="const _list = areasForSelectedCity; const _cur = String({{ $areaExpr }} || '').trim().toLowerCase(); const _hit = _list.find((p) => p.area.toLowerCase() === _cur); $nextTick(() => { $el.value = _hit ? _hit.area : (_cur === '' ? '' : '__custom__'); })"
            data-narrowed-location-select
            class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 text-xs font-semibold text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
        >
            <option value="">Select area / sector / hub...</option>
            <template x-for="place in areasForSelectedCity" :key="'{{ $contextKey }}-p-' + place.city + '-' + place.area">
                <option :value="place.area" x-text="place.area"></option>
            </template>
            <option value="__custom__">+ Type custom location...</option>
        </select>
    </div>

    {{-- 3. Live Typeahead Area Input with Auto-Suggestions Popup --}}
    <div class="relative">
        <label class="block text-[11px] font-bold text-[var(--muted-foreground)] mb-1">
            {{ $areaInputLabel }}
        </label>
        <div class="relative">
            <input
                x-ref="areaInput"
                type="text"
                name="{{ $areaName }}"
                x-model="{{ $areaExpr }}"
                @input="onAreaTypeInput()"
                @focus="onAreaTypeInput()"
                @keydown.arrow-down.prevent="if (suggestions.length) highlightIdx = (highlightIdx + 1) % suggestions.length"
                @keydown.arrow-up.prevent="if (suggestions.length) highlightIdx = (highlightIdx - 1 + suggestions.length) % suggestions.length"
                @keydown.enter="if (suggestionsOpen && highlightIdx >= 0 && suggestions[highlightIdx]) { $event.preventDefault(); applyPlaceChoice(suggestions[highlightIdx], true); }"
                @keydown.escape="suggestionsOpen = false"
                @blur="setTimeout(() => { suggestionsOpen = false; }, 180)"
                autocomplete="address-level3"
                data-area-typeahead-input
                placeholder="Type place name for suggestions..."
                class="w-full rounded-xl border border-[var(--border)] bg-[var(--card)] px-3 py-2 pr-7 text-xs font-medium text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"
            >
            <i
                class="fa-solid text-[10px] text-[var(--muted-foreground)] absolute right-2.5 top-1/2 -translate-y-1/2 pointer-events-none"
                :class="loadingOsm ? 'fa-spinner fa-spin' : 'fa-magnifying-glass-location'"
            ></i>
        </div>

        <div
            x-show="suggestionsOpen && suggestions.length > 0"
            x-cloak
            data-area-suggestions
            class="absolute left-0 right-0 top-full mt-1 max-h-60 overflow-y-auto rounded-2xl border border-[var(--border)] bg-[var(--card)] p-1.5 shadow-xl z-[700]"
        >
            <template x-for="(item, idx) in suggestions" :key="'{{ $contextKey }}-s-' + item.city + '-' + item.area + '-' + idx">
                <button
                    type="button"
                    @mousedown.prevent="applyPlaceChoice(item, true)"
                    :class="highlightIdx === idx ? 'bg-[var(--mint-100)] text-[var(--accent-dark)]' : 'hover:bg-[var(--mint-50)] text-[var(--foreground)]'"
                    class="flex w-full items-start justify-between gap-2 rounded-xl px-2.5 py-2 text-left transition"
                >
                    <div class="min-w-0">
                        <p class="truncate text-xs font-bold" x-text="item.area"></p>
                        <p class="truncate text-[10px] text-[var(--muted-foreground)]" x-text="item.address"></p>
                    </div>
                    <span class="shrink-0 rounded-full bg-[var(--mint-200)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]" x-text="item.city"></span>
                </button>
            </template>
        </div>
    </div>
</div>

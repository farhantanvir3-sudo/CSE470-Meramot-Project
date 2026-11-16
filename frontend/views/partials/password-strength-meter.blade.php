@php
    $passwordExpr = $passwordExpr ?? 'password';
    $confirmExpr = $confirmExpr ?? null;
    $focusedExpr = $focusedExpr ?? 'false';
    $evalVar = $evalVar ?? 'pwEval';
@endphp

<div
    x-data="{
        get {{ $evalVar }}() {
            return window.meramotEvaluatePassword ? window.meramotEvaluatePassword({{ $passwordExpr }}) : {
                checks: { length: false, upper: false, lower: false, number: false, special: false },
                passed: 0,
                score: 0,
                label: 'Weak',
                color: 'text-red-600',
                barColor: 'bg-red-500'
            };
        }
    }"
    x-show="({{ $passwordExpr }} || '').length > 0 || ({{ $focusedExpr }})"
    x-cloak
    data-password-strength-meter
    :data-strength-tier="{{ $evalVar }}.label"
    class="mt-2.5 rounded-lg border border-[var(--border)] bg-[var(--mint-50)] px-4 py-3 text-xs transition-all"
>
    <div class="flex items-center justify-between gap-2 mb-2">
        <span class="font-bold text-[var(--muted-foreground)] uppercase tracking-wider text-[10px]">
            Password Strength
        </span>
        <span
            class="inline-flex items-center gap-1 rounded bg-[var(--card)] px-2.5 py-0.5 text-[11px] font-semibold border border-[var(--border)]"
            :class="{{ $evalVar }}.color"
            x-text="({{ $passwordExpr }} || '').length === 0 ? 'Enter password' : {{ $evalVar }}.label"
            data-strength-label
        ></span>
    </div>

    <div class="grid grid-cols-4 gap-1.5 mb-2.5" aria-hidden="true">
        <template x-for="barIdx in [1, 2, 3, 4]" :key="barIdx">
            <div class="h-1.5 w-full overflow-hidden rounded-full bg-[var(--mint-200)]">
                <div
                    class="h-full w-full rounded-full transition-all duration-200"
                    :class="{{ $evalVar }}.score >= barIdx ? {{ $evalVar }}.barColor : 'bg-transparent'"
                ></div>
            </div>
        </template>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 gap-x-3 gap-y-1 text-[11px] text-[var(--muted-foreground)]">
        <div class="flex items-center gap-1.5" :class="{{ $evalVar }}.checks.length ? 'text-[#1b5e36] font-semibold' : ''">
            <i class="fa-solid text-[10px]" :class="{{ $evalVar }}.checks.length ? 'fa-circle-check' : 'fa-circle'"></i>
            <span>8+ characters</span>
        </div>
        <div class="flex items-center gap-1.5" :class="{{ $evalVar }}.checks.upper ? 'text-[#1b5e36] font-semibold' : ''">
            <i class="fa-solid text-[10px]" :class="{{ $evalVar }}.checks.upper ? 'fa-circle-check' : 'fa-circle'"></i>
            <span>Uppercase (A-Z)</span>
        </div>
        <div class="flex items-center gap-1.5" :class="{{ $evalVar }}.checks.lower ? 'text-[#1b5e36] font-semibold' : ''">
            <i class="fa-solid text-[10px]" :class="{{ $evalVar }}.checks.lower ? 'fa-circle-check' : 'fa-circle'"></i>
            <span>Lowercase (a-z)</span>
        </div>
        <div class="flex items-center gap-1.5" :class="{{ $evalVar }}.checks.number ? 'text-[#1b5e36] font-semibold' : ''">
            <i class="fa-solid text-[10px]" :class="{{ $evalVar }}.checks.number ? 'fa-circle-check' : 'fa-circle'"></i>
            <span>Number (0-9)</span>
        </div>
        <div class="flex items-center gap-1.5 col-span-2 sm:col-span-2" :class="{{ $evalVar }}.checks.special ? 'text-[#1b5e36] font-semibold' : ''">
            <i class="fa-solid text-[10px]" :class="{{ $evalVar }}.checks.special ? 'fa-circle-check' : 'fa-circle'"></i>
            <span>Symbol (!@#$%...)</span>
        </div>
    </div>

    @if(!empty($confirmExpr))
        <template x-if="({{ $confirmExpr }} || '').length > 0">
            <p
                class="mt-2 pt-1.5 border-t border-[var(--border)] flex items-center gap-1.5 text-[11px] font-semibold"
                :class="{{ $passwordExpr }} === {{ $confirmExpr }} ? 'text-[#1b5e36]' : 'text-red-600'"
            >
                <i class="fa-solid" :class="{{ $passwordExpr }} === {{ $confirmExpr }} ? 'fa-check-double' : 'fa-triangle-exclamation'"></i>
                <span x-text="{{ $passwordExpr }} === {{ $confirmExpr }} ? 'Passwords match' : 'Confirmation password does not match'"></span>
            </p>
        </template>
    @endif
</div>

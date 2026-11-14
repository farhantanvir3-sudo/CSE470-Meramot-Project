@php
    $partsList = is_array($partsItems ?? null) ? $partsItems : [];
    $partsTotal = round((float) ($partsCost ?? collect($partsList)->sum('cost')), 2);
    $laborTotal = round((float) ($laborCost ?? 0), 2);
    $warrantyActive = (bool) ($hasWarranty ?? false) && (int) ($warrantyDays ?? 0) > 0;
    $warrantyPeriod = $warrantyActive ? (int) ($warrantyDays ?? 0) : 0;
    $theme = $theme ?? 'mint';
    $boxBorder = $theme === 'amber' ? 'border-amber-300/90 bg-white/80 dark:bg-[var(--card)]' : 'border-[var(--border)] bg-[var(--card)]/90';
    $compact = (bool) ($compact ?? false);
@endphp

<div class="rounded-xl border {{ $boxBorder }} {{ $compact ? 'p-2.5 space-y-1.5' : 'p-3.5 space-y-2' }} text-xs">
    <div class="flex items-center justify-between gap-2">
        <span class="text-[10px] font-black uppercase tracking-wider text-[var(--accent-dark)]">
            Parts Pricing Breakdown
        </span>
        <span class="text-[11px] font-bold text-[var(--foreground)]">
            Parts Subtotal: ৳{{ number_format($partsTotal, 2) }}
        </span>
    </div>

    @if(count($partsList) > 0)
        <ol class="space-y-1">
            @foreach($partsList as $index => $part)
                <li class="flex items-start justify-between gap-3 text-[11px]">
                    <span class="text-[var(--foreground)] min-w-0 break-words">
                        <span class="font-bold text-[var(--accent-dark)] mr-1">{{ $index + 1 }}.</span>{{ $part['name'] ?? ('Part #' . ($index + 1)) }}
                    </span>
                    <span class="font-semibold text-[var(--foreground)] shrink-0">
                        ৳{{ number_format((float) ($part['cost'] ?? 0), 2) }}
                    </span>
                </li>
            @endforeach
        </ol>
    @else
        <p class="text-[11px] text-[var(--muted-foreground)] italic">
            No separate replacement parts required (bench service &amp; labor only).
        </p>
    @endif

    <div class="flex flex-wrap items-center justify-between gap-2 border-t border-[var(--border)]/50 pt-1.5 text-[11px]">
        <span class="text-[var(--muted-foreground)]">
            Bench Labor: <strong class="text-[var(--foreground)]">৳{{ number_format($laborTotal, 2) }}</strong>
        </span>
        <span class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[10px] font-bold {{ $warrantyActive ? 'bg-emerald-100 text-emerald-900 border border-emerald-300' : 'bg-[var(--mint-100)] text-[var(--muted-foreground)] border border-[var(--border)]' }}">
            <i class="{{ $warrantyActive ? 'fa-solid fa-circle-check text-emerald-700' : 'fa-regular fa-circle-xmark' }}"></i>
            {{ $warrantyActive ? "{$warrantyPeriod}-Day Warranty Included" : 'No Extended Warranty' }}
        </span>
    </div>
</div>

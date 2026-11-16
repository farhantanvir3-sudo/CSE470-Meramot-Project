@php
    /** @var \App\Models\IssuedWarranty $w */
    $isActive = $w->isCurrentlyActive();
    $daysLeft = $w->daysRemaining();
    $pctElapsed = $w->progressPercent();
    $remedies = $w->remedyBadges();
    $canClaim = $canActAsCustomer ?? (auth()->check() && (int) auth()->id() === (int) $w->customer_id);
    $showOrderLink = $showOrderLink ?? true;
    $claimsList = $w->claims ?? collect();
@endphp

<div x-data="{
        claimOpen: {{ ($openByDefault ?? false) ? 'true' : 'false' }},
        previews: [],
        handleFiles(event) {
            const files = Array.from(event.target.files || []).slice(0, 5);
            this.previews = [];
            files.forEach((file) => {
                const reader = new FileReader();
                reader.onload = (e) => {
                    this.previews.push({ name: file.name, url: e.target.result });
                };
                reader.readAsDataURL(file);
            });
        }
    }"
    class="rounded-lg border {{ $isActive ? 'border-emerald-600/35 bg-[var(--card)]' : 'border-[var(--border)] bg-[var(--card)]/85' }} p-4 sm:p-5 shadow-xs space-y-3.5"
    data-issued-warranty-id="{{ $w->id }}"
>
    {{-- Top Header Row --}}
    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
        <div class="space-y-1 min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-1.5 text-[10px]">
                @if($isActive)
                    <span class="inline-flex items-center gap-1 rounded bg-emerald-100 border border-emerald-300 px-2.5 py-0.5 font-bold text-emerald-900">
                        <i class="fa-solid fa-shield-halved text-[10px]"></i>
                        ACTIVE WARRANTY • {{ $daysLeft }} {{ \Illuminate\Support\Str::plural('Day', $daysLeft) }} Left
                    </span>
                @elseif($w->status === \App\Models\IssuedWarranty::STATUS_REFUNDED_CLOSED)
                    <span class="inline-flex items-center gap-1 rounded bg-sky-100 border border-sky-300 px-2.5 py-0.5 font-bold text-sky-900">
                        <i class="fa-solid fa-rotate-left text-[10px]"></i>
                        CLOSED BY REFUND (৳{{ number_format((float) $w->refunded_amount, 0) }})
                    </span>
                @else
                    <span class="inline-flex items-center gap-1 rounded bg-slate-100 border border-slate-300 px-2.5 py-0.5 font-bold text-slate-700">
                        WARRANTY EXPIRED ({{ $w->expires_at?->format('M j, Y') }})
                    </span>
                @endif

                <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 font-semibold text-[var(--accent-dark)]">
                    {{ $w->item_type === 'SPARE_PART' ? 'Spare Part Coverage' : 'Workshop Repair Coverage' }}
                </span>

                @if($w->claims_count > 0)
                    <span class="rounded bg-amber-100 border border-amber-300 px-2 py-0.5 font-bold text-amber-900">
                        {{ $w->claims_count }} {{ \Illuminate\Support\Str::plural('Claim', $w->claims_count) }} Filed
                    </span>
                @endif
            </div>

            <h3 class="text-sm sm:text-base font-bold text-[var(--foreground)] break-words">
                {{ $w->item_title }}
                @if((int) $w->quantity > 1)
                    <span class="text-xs font-semibold text-[var(--muted-foreground)]">(×{{ $w->quantity }} units)</span>
                @endif
            </h3>

            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-[var(--muted-foreground)]">
                @if($showOrderLink)
                    <a href="{{ route('requests.track', $w->repair_request_id) }}#warranty-section" class="font-bold text-[var(--accent-dark)] hover:underline">
                        Order #{{ $w->repair_request_id }}
                    </a>
                @endif
                @if($w->shop)
                    <span><i class="fa-solid fa-store mr-1 text-[10px]"></i>{{ $w->shop->name }}</span>
                @endif
                <span>Valid: <strong>{{ $w->starts_at?->format('M j, Y') }}</strong> &rarr; <strong>{{ $w->expires_at?->format('M j, Y') }}</strong> ({{ $w->duration_days }} days)</span>
            </div>

            {{-- Remedies & Progress --}}
            <div class="pt-1.5 flex flex-wrap items-center gap-1.5 text-[10px]">
                <span class="text-[var(--muted-foreground)] font-semibold">Eligible remedies:</span>
                @foreach($remedies as $rLabel)
                    <span class="rounded bg-[var(--mint-50)] border border-[var(--border)] px-2 py-0.5 font-semibold text-[var(--accent-dark)]">
                        ✓ {{ $rLabel }}
                    </span>
                @endforeach
                @if($w->replacements_issued > 0)
                    <span class="rounded bg-emerald-50 border border-emerald-200 px-2 py-0.5 font-semibold text-emerald-800">
                        Replacements issued: {{ $w->replacements_issued }}
                    </span>
                @endif
                @if($w->repairs_issued > 0)
                    <span class="rounded bg-indigo-50 border border-indigo-200 px-2 py-0.5 font-semibold text-indigo-800">
                        Free repairs issued: {{ $w->repairs_issued }}
                    </span>
                @endif
            </div>
        </div>

        <div class="flex flex-col items-stretch sm:items-end gap-2 shrink-0">
            @if($isActive && $canClaim)
                <button type="button"
                        @click="claimOpen = !claimOpen"
                        data-toggle-warranty-claim="{{ $w->id }}"
                        class="inline-flex items-center justify-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition cursor-pointer whitespace-nowrap">
                    <i class="fa-solid fa-shield-halved text-[11px]"></i>
                    <span x-show="!claimOpen">
                        {{ $w->claims_count > 0 ? ('Submit Another Claim (#' . ($w->claims_count + 1) . ')') : 'Claim Warranty' }}
                    </span>
                    <span x-show="claimOpen" x-cloak>Close Claim Form</span>
                </button>
            @endif
        </div>
    </div>

    {{-- Bounded Period Progress Bar --}}
    <div class="space-y-1">
        <div class="h-1.5 w-full overflow-hidden rounded-full bg-[var(--mint-100)] border border-[var(--border)]/60">
            <div class="h-full rounded-full transition-all {{ $isActive ? 'bg-[var(--accent-dark)]' : 'bg-slate-400' }}"
                 style="width: {{ $pctElapsed }}%"></div>
        </div>
        @if($w->terms)
            <p class="text-[11px] text-[var(--muted-foreground)] leading-snug">
                <strong class="text-[var(--foreground)]">Terms:</strong> {{ $w->terms }}
            </p>
        @endif
    </div>

    {{-- Repeatable Claim Submission Form --}}
    @if($isActive && $canClaim)
        <form x-show="claimOpen"
              x-cloak
              method="POST"
              action="{{ route('warranties.claims.store', $w->id) }}"
              enctype="multipart/form-data"
              data-no-softnav="true"
              data-warranty-claim-form="{{ $w->id }}"
              class="rounded-lg border border-emerald-700/25 bg-[var(--mint-50)]/75 p-4 space-y-3 text-xs">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-[var(--border)] pb-2">
                <h4 class="font-bold text-[var(--foreground)]">
                    <i class="fa-solid fa-file-shield mr-1 text-[var(--accent-dark)]"></i>
                    Submit Warranty Claim #{{ $w->claims_count + 1 }} • {{ $w->item_title }}
                </h4>
                <span class="text-[10px] font-semibold text-[var(--muted-foreground)]">
                    Reviewed by Meramot Platform Admin
                </span>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Requested Resolution</label>
                    <select name="preferred_remedy"
                            class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-xs font-semibold text-[var(--foreground)]">
                        <option value="AUTO">Let Admin Assess (Refund / Replacement / Free Repair)</option>
                        <option value="REPLACEMENT">Replacement Part</option>
                        <option value="FREE_REPAIR">Free Workshop Repair</option>
                        <option value="REFUND">Full / Partial Refund</option>
                    </select>
                </div>

                @if((int) $w->quantity > 1)
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Affected Units (max {{ $w->quantity }})</label>
                        <input type="number" name="claimed_quantity" min="1" max="{{ $w->quantity }}" value="1"
                               class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-xs font-bold text-[var(--foreground)]">
                    </div>
                @else
                    <input type="hidden" name="claimed_quantity" value="1">
                @endif
            </div>

            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">
                    Explain the Fault / Defect <span class="text-rose-600">*</span>
                </label>
                <textarea name="explanation"
                          rows="3"
                          required
                          minlength="15"
                          maxlength="3000"
                          placeholder="Describe the exact defect or recurring fault observed (min 15 chars). You can submit another claim if a replaced part fails again within your warranty period."
                          class="w-full rounded-md border border-[var(--border)] bg-white p-2.5 text-xs text-[var(--foreground)] focus:border-[var(--accent-dark)] outline-none">{{ old('explanation') }}</textarea>
            </div>

            <div>
                <label class="block font-bold text-[var(--foreground)] mb-1">
                    Upload Fault Evidence Photos <span class="font-normal text-[var(--muted-foreground)]">(Up to 5 images: JPG/PNG/WEBP)</span>
                </label>
                <input type="file"
                       name="evidence_images[]"
                       accept="image/*"
                       multiple
                       @change="handleFiles($event)"
                       class="block w-full rounded-md border border-dashed border-[var(--border)] bg-white px-3 py-2 text-xs text-[var(--foreground)] file:mr-3 file:rounded file:border-0 file:bg-[var(--mint-100)] file:px-3 file:py-1 file:text-xs file:font-bold file:text-[var(--accent-dark)]">

                <template x-if="previews.length > 0">
                    <div class="mt-2 flex flex-wrap gap-2">
                        <template x-for="(p, idx) in previews" :key="idx">
                            <div class="relative w-16 h-16 rounded-md border border-[var(--border)] bg-white overflow-hidden">
                                <img :src="p.url" :alt="p.name" class="w-full h-full object-cover">
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                <span class="text-[10px] text-[var(--muted-foreground)]">
                    Admin checks each claim for repeated defects or policy misuse before ruling.
                </span>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] shadow-sm hover:opacity-90 transition cursor-pointer">
                    <i class="fa-solid fa-paper-plane text-[10px]"></i>
                    <span>Submit Warranty Claim</span>
                </button>
            </div>
        </form>
    @endif

    {{-- Claim History & Admin Responses --}}
    @if($claimsList->isNotEmpty())
        <div class="border-t border-[var(--border)]/70 pt-3 space-y-2.5">
            <div class="text-[11px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">
                Claim History ({{ $claimsList->count() }})
            </div>
            @foreach($claimsList as $cl)
                @php
                    $clStatus = strtoupper((string) $cl->status);
                    $clBadgeCls = match ($clStatus) {
                        'APPROVED_REFUND', 'APPROVED_REPLACEMENT', 'APPROVED_FREE_REPAIR' => 'bg-emerald-100 text-emerald-900 border-emerald-300',
                        'REJECTED' => 'bg-rose-100 text-rose-900 border-rose-300',
                        default => 'bg-amber-100 text-amber-950 border-amber-300',
                    };
                @endphp
                <div class="rounded-md border border-[var(--border)] bg-[var(--mint-50)]/45 p-3 space-y-2 text-xs">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-bold text-[var(--foreground)]">Claim #{{ $cl->claim_number }} (ID #{{ $cl->id }})</span>
                            <span class="rounded border px-2 py-0.5 text-[10px] font-bold {{ $clBadgeCls }}">
                                {{ $cl->statusBadgeLabel() }}
                            </span>
                            @if($cl->preferred_remedy && $cl->preferred_remedy !== 'AUTO')
                                <span class="text-[10px] text-[var(--muted-foreground)]">Requested: {{ str_replace('_', ' ', $cl->preferred_remedy) }}</span>
                            @endif
                        </div>
                        <span class="text-[10px] text-[var(--muted-foreground)]">{{ $cl->created_at?->format('M j, Y H:i') }}</span>
                    </div>

                    <p class="text-[var(--foreground)] whitespace-pre-line">{{ $cl->customer_explanation }}</p>

                    @if(!empty($cl->customer_image_urls))
                        <div class="flex flex-wrap gap-2 pt-0.5">
                            @foreach($cl->customer_image_urls as $cImg)
                                <button type="button"
                                        @click="lightboxPhoto = @js($cImg)"
                                        class="w-16 h-16 rounded border border-[var(--border)] overflow-hidden bg-white hover:opacity-90 transition cursor-pointer">
                                    <img src="{{ $cImg }}" alt="Customer warranty evidence" class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif

                    @if($cl->admin_reply)
                        <div class="rounded border {{ $clStatus === 'REJECTED' ? 'border-rose-300 bg-rose-50/70' : 'border-emerald-300 bg-emerald-50/70' }} p-2.5 space-y-1.5">
                            <div class="flex flex-wrap items-center justify-between gap-1 text-[10px] font-bold {{ $clStatus === 'REJECTED' ? 'text-rose-900' : 'text-emerald-900' }}">
                                <span>
                                    <i class="fa-solid fa-gavel mr-1"></i>Admin Assessment{{ $cl->reviewer ? (' • ' . $cl->reviewer->name) : '' }}
                                </span>
                                @if($cl->reviewed_at)
                                    <span>{{ $cl->reviewed_at->format('M j, Y H:i') }}</span>
                                @endif
                            </div>
                            <p class="text-[11px] {{ $clStatus === 'REJECTED' ? 'text-rose-950' : 'text-emerald-950' }} whitespace-pre-line">{{ $cl->admin_reply }}</p>
                            @if($cl->refund_amount)
                                <div class="text-[11px] font-bold text-emerald-900">Refund Issued: ৳{{ number_format((float) $cl->refund_amount, 2) }}</div>
                            @endif
                            @if($cl->followup_repair_request_id)
                                <div>
                                    <a href="{{ route('requests.track', $cl->followup_repair_request_id) }}" class="inline-flex items-center gap-1 text-[11px] font-bold text-[var(--accent-dark)] hover:underline">
                                        View Free Repair Ticket #{{ $cl->followup_repair_request_id }} &rarr;
                                    </a>
                                </div>
                            @endif
                            @if(!empty($cl->admin_image_urls))
                                <div class="flex flex-wrap gap-2 pt-1">
                                    @foreach($cl->admin_image_urls as $aImg)
                                        <button type="button"
                                                @click="lightboxPhoto = @js($aImg)"
                                                class="w-16 h-16 rounded border border-[var(--border)] overflow-hidden bg-white hover:opacity-90 transition cursor-pointer">
                                            <img src="{{ $aImg }}" alt="Admin assessment photo" class="w-full h-full object-cover">
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

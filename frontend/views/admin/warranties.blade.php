@extends('layouts.app')

@section('title', 'Warranty Registry & Claims Arbitration | Meramot Admin')

@section('content')
<div class="mx-auto max-w-7xl px-3.5 py-4 sm:px-6 sm:py-8 lg:px-8 space-y-5 sm:space-y-7" x-data="{ lightboxPhoto: null }">
    @include('admin.partials.nav')

    {{-- Header & KPIs --}}
    <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-6 shadow-sm space-y-4">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
            <div>
                <span class="inline-flex items-center gap-1.5 rounded bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-[10px] font-bold uppercase tracking-wider text-[var(--accent-dark)]">
                    <i class="fa-solid fa-shield-halved"></i> Separate Warranty Registry &amp; Arbitration
                </span>
                <h1 class="mt-2 text-xl sm:text-2xl font-bold text-[var(--foreground)]">
                    Warranty Claims Assessment &amp; Coverage Registry
                </h1>
                <p class="mt-1 text-xs text-[var(--muted-foreground)] max-w-3xl leading-relaxed">
                    Assess customer warranty claims during active coverage periods and issue <strong>Replacement</strong>, <strong>Free Repair</strong>, or <strong>Refund</strong> — or decline invalid claims with mandatory explanation and supporting inspection photos.
                </p>
            </div>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-2.5 text-center">
            <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-50)] p-3">
                <div class="text-lg font-bold text-[var(--foreground)] tabular-nums">{{ number_format($kpis['warrantied_listings']) }}</div>
                <div class="text-[10px] font-bold uppercase text-[var(--muted-foreground)]">Warrantied Listings</div>
            </div>
            <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-50)] p-3">
                <div class="text-lg font-bold text-[var(--accent-dark)] tabular-nums">{{ number_format($kpis['active_warranties']) }}</div>
                <div class="text-[10px] font-bold uppercase text-[var(--muted-foreground)]">Active Order Certs</div>
            </div>
            <div class="rounded-lg border border-amber-300 bg-amber-50 p-3">
                <div class="text-lg font-bold text-amber-800 tabular-nums">{{ number_format($kpis['pending_claims']) }}</div>
                <div class="text-[10px] font-bold uppercase text-amber-900">Pending Review</div>
            </div>
            <div class="rounded-lg border border-rose-300 bg-rose-50 p-3">
                <div class="text-lg font-bold text-rose-700 tabular-nums">{{ number_format($kpis['high_risk_pending']) }}</div>
                <div class="text-[10px] font-bold uppercase text-rose-800">Misuse Flagged</div>
            </div>
            <div class="rounded-lg border border-emerald-300 bg-emerald-50 p-3">
                <div class="text-lg font-bold text-emerald-800 tabular-nums">{{ number_format($kpis['approved_claims']) }}</div>
                <div class="text-[10px] font-bold uppercase text-emerald-900">Approved</div>
            </div>
            <div class="rounded-lg border border-slate-300 bg-slate-50 p-3">
                <div class="text-lg font-bold text-slate-700 tabular-nums">{{ number_format($kpis['rejected_claims']) }}</div>
                <div class="text-[10px] font-bold uppercase text-slate-800">Rejected</div>
            </div>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-400 bg-emerald-50 px-4 py-3 text-xs font-bold text-emerald-950 flex items-center gap-2">
            <i class="fa-solid fa-circle-check text-emerald-700"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error') || $errors->any())
        <div class="rounded-lg border border-rose-300 bg-rose-50 px-4 py-3 text-xs font-bold text-rose-900 space-y-1">
            @if(session('error'))
                <div>{{ session('error') }}</div>
            @endif
            @foreach($errors->all() as $err)
                <div><i class="fa-solid fa-circle-exclamation mr-1"></i>{{ $err }}</div>
            @endforeach
        </div>
    @endif

    {{-- Filter & Search Controls --}}
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-3 rounded-lg border border-[var(--border)] bg-[var(--card)] p-3.5 shadow-xs text-xs">
        <div class="flex flex-wrap items-center gap-1.5">
            @foreach([
                'ALL' => 'All Claims',
                'PENDING' => 'Pending Review',
                'HIGH_RISK' => 'Flagged / Repeat',
                'APPROVED' => 'Approved Remedies',
                'REJECTED' => 'Rejected',
            ] as $fKey => $fLabel)
                <a href="{{ route('admin.warranties', array_filter(['status' => $fKey, 'q' => $search ?: null])) }}"
                   class="rounded-md px-3 py-1.5 font-bold transition {{ $statusFilter === $fKey ? 'bg-[var(--accent-dark)] text-white shadow-2xs' : 'bg-[var(--mint-50)] text-[var(--foreground)] border border-[var(--border)] hover:bg-[var(--mint-100)]' }}">
                    {{ $fLabel }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('admin.warranties') }}" class="flex items-center gap-2">
            <input type="hidden" name="status" value="{{ $statusFilter }}">
            <input type="text"
                   name="q"
                   value="{{ $search }}"
                   placeholder="Claim #, order #, customer, item..."
                   class="rounded-md border border-[var(--border)] bg-white px-3 py-1.5 text-xs text-[var(--foreground)] w-56">
            <button type="submit" class="rounded-md bg-[var(--accent-dark)] px-3 py-1.5 text-xs font-bold text-white hover:opacity-90 transition">
                Filter
            </button>
        </form>
    </div>

    {{-- Warranty Claims Queue --}}
    <div class="space-y-4">
        @forelse($claims as $claim)
            @php
                $w = $claim->issuedWarranty;
                $cStat = $customerStats[(int) $claim->customer_id] ?? ['total_claims' => 1, 'rejected_claims' => 0, 'completed_orders' => 1];
                $riskLvl = $claim->riskLevel();
                $riskBadgeCls = match ($riskLvl) {
                    'HIGH' => 'bg-rose-100 text-rose-900 border-rose-300',
                    'MEDIUM' => 'bg-amber-100 text-amber-950 border-amber-300',
                    default => 'bg-emerald-100 text-emerald-900 border-emerald-300',
                };
                $defaultRefundAmt = $w
                    ? min(
                        max(1, round((float) ($w->unit_price ?: 0) * max(1, (int) $claim->claimed_quantity), 2)),
                        max(1, round((float) ($claim->repairRequest?->quoted_final_amount ?: $w->unit_price ?: 500), 2))
                    )
                    : 500;
                $initDecision = old('claim_id') == $claim->id ? old('decision', 'REPLACEMENT') : 'REPLACEMENT';
            @endphp

            <div class="rounded-lg border {{ $claim->status === 'PENDING' ? 'border-amber-300 bg-[var(--card)]' : 'border-[var(--border)] bg-[var(--card)]' }} p-4 sm:p-5 shadow-sm space-y-4 text-xs"
                 data-admin-claim-card="{{ $claim->id }}">
                <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-3 border-b border-[var(--border)] pb-3">
                    <div class="space-y-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="font-mono font-bold text-[var(--foreground)]">Claim Ticket #{{ $claim->id }}</span>
                            <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2 py-0.5 text-[10px] font-bold text-[var(--accent-dark)]">
                                Filing #{{ $claim->claim_number }} on this Item
                            </span>
                            <span class="rounded border px-2 py-0.5 text-[10px] font-bold {{ $claim->status === 'PENDING' ? 'bg-amber-100 text-amber-950 border-amber-300' : ($claim->status === 'REJECTED' ? 'bg-rose-100 text-rose-900 border-rose-300' : 'bg-emerald-100 text-emerald-900 border-emerald-300') }}">
                                {{ $claim->statusBadgeLabel() }}
                            </span>
                            <span class="rounded border px-2 py-0.5 text-[10px] font-bold {{ $riskBadgeCls }}">
                                Misuse Risk: {{ (int) $claim->misuse_risk_score }}/100 ({{ $riskLvl }})
                            </span>
                        </div>
                        <h2 class="text-base font-bold text-[var(--foreground)]">
                            {{ $w?->item_title ?? 'Warrantied Order Item' }}
                            @if((int) $claim->claimed_quantity > 1)
                                <span class="text-xs font-normal text-[var(--muted-foreground)]">(×{{ $claim->claimed_quantity }} units)</span>
                            @endif
                        </h2>
                        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px] text-[var(--muted-foreground)]">
                            <span>Customer: <strong class="text-[var(--foreground)]">{{ $claim->customer?->name }}</strong> ({{ $claim->customer?->email }})</span>
                            <span>Seller: <strong class="text-[var(--foreground)]">{{ $claim->shop?->name ?? 'Meramot Shop' }}</strong></span>
                            <a href="{{ route('requests.track', $claim->repair_request_id) }}#warranty-section" class="font-bold text-[var(--accent-dark)] hover:underline">
                                Order #{{ $claim->repair_request_id }} &rarr;
                            </a>
                            <span>Submitted: {{ $claim->created_at?->format('M j, Y H:i') }}</span>
                        </div>
                    </div>

                    @if($w)
                        <div class="rounded-md border border-[var(--border)] bg-[var(--mint-50)] px-3 py-2 text-[11px] shrink-0">
                            <div>Warranty Window: <strong>{{ $w->starts_at?->format('M j, Y') }}</strong> &rarr; <strong>{{ $w->expires_at?->format('M j, Y') }}</strong></div>
                            <div>
                                Item History: <strong>{{ $w->claims_count }}</strong> claims •
                                <strong>{{ $w->replacements_issued }}</strong> replacements •
                                <strong>{{ $w->repairs_issued }}</strong> free repairs
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Anti-Misuse Assessment Telemetry --}}
                <div class="rounded-md border border-[var(--border)] bg-[var(--mint-50)]/60 p-3 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                    <div class="space-y-1">
                        <div class="text-[10px] font-bold uppercase tracking-wider text-[var(--foreground)]">
                            <i class="fa-solid fa-radar mr-1 text-[var(--accent-dark)]"></i> Anti-Misuse Inspection Summary
                        </div>
                        <div class="text-[11px] text-[var(--muted-foreground)]">
                            Customer lifetime: <strong>{{ $cStat['total_claims'] }}</strong> claims across <strong>{{ $cStat['completed_orders'] }}</strong> completed orders
                            • Prior rejections: <strong class="{{ $cStat['rejected_claims'] > 0 ? 'text-rose-700' : '' }}">{{ $cStat['rejected_claims'] }}</strong>
                            @if($claim->sparePartListing)
                                • Listing current stock: <strong>{{ $claim->sparePartListing->stock_quantity }}</strong> units
                            @endif
                        </div>
                        @if(!empty($claim->misuse_flags))
                            <div class="flex flex-wrap gap-1.5 pt-1">
                                @foreach($claim->misuse_flags as $flag)
                                    <span class="rounded bg-rose-100 border border-rose-300 px-2 py-0.5 text-[10px] font-semibold text-rose-900">
                                        ⚠ {{ $flag }}
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>

                {{-- Customer Fault Description & Photos --}}
                <div class="space-y-2">
                    <div class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">
                        Customer Statement (Preferred Remedy: {{ str_replace('_', ' ', $claim->preferred_remedy) }})
                    </div>
                    <p class="rounded-md border border-[var(--border)] bg-white p-3 text-xs text-[var(--foreground)] whitespace-pre-line">
                        {{ $claim->customer_explanation }}
                    </p>
                    @if(!empty($claim->customer_image_urls))
                        <div class="flex flex-wrap gap-2">
                            @foreach($claim->customer_image_urls as $imgUrl)
                                <button type="button"
                                        @click="lightboxPhoto = @js($imgUrl)"
                                        class="w-20 h-20 rounded-md border border-[var(--border)] overflow-hidden bg-white hover:opacity-90 transition cursor-pointer">
                                    <img src="{{ $imgUrl }}" alt="Customer warranty photo" class="w-full h-full object-cover">
                                </button>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Admin Decision Section --}}
                @if($claim->status === \App\Models\WarrantyClaim::STATUS_PENDING)
                    <form method="POST"
                          action="{{ route('admin.warranties.assess', $claim->id) }}"
                          enctype="multipart/form-data"
                          data-no-softnav="true"
                          x-data="{
                              decision: @js($initDecision),
                              adminPreviews: [],
                              previewAdminFiles(ev) {
                                  const files = Array.from(ev.target.files || []).slice(0, 5);
                                  this.adminPreviews = [];
                                  files.forEach((f) => {
                                      const r = new FileReader();
                                      r.onload = (e) => this.adminPreviews.push({ name: f.name, url: e.target.result });
                                      r.readAsDataURL(f);
                                  });
                              }
                          }"
                          class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/45 p-4 space-y-3">
                        @csrf
                        <input type="hidden" name="claim_id" value="{{ $claim->id }}">

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block font-bold text-[var(--foreground)] mb-1">Admin Assessment Ruling <span class="text-rose-600">*</span></label>
                                <select name="decision"
                                        x-model="decision"
                                        class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-xs font-bold text-[var(--foreground)]">
                                    <option value="REPLACEMENT">Approve Replacement Dispatch (Deduct Stock)</option>
                                    <option value="FREE_REPAIR">Approve Free Repair (Create ৳0 Follow-up Ticket)</option>
                                    <option value="REFUND">Approve Warranty Refund (Ledger Clawback &amp; Return)</option>
                                    <option value="REJECT">Reject Claim (Requires Explanation + Proof Images)</option>
                                </select>
                            </div>

                            <div x-show="decision === 'REFUND'" x-cloak>
                                <label class="block font-bold text-[var(--foreground)] mb-1">Refund Amount (BDT ৳)</label>
                                <input type="number"
                                       step="0.01"
                                       min="1"
                                       name="refund_amount"
                                       value="{{ $defaultRefundAmt }}"
                                       class="w-full rounded-md border border-[var(--border)] bg-white px-3 py-2 text-xs font-bold text-[var(--foreground)]">
                            </div>
                        </div>

                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">
                                Admin Assessment Explanation / Reply
                                <span x-show="decision === 'REJECT'" x-cloak class="text-rose-700">(Required &bull; min 20 chars for rejection)</span>
                            </label>
                            <textarea name="admin_reply"
                                      rows="3"
                                      required
                                      :minlength="decision === 'REJECT' ? 20 : 5"
                                      maxlength="3000"
                                      placeholder="Explain your assessment finding and decision to the customer (for rejections, detail exact inspection findings & policy basis)..."
                                      class="w-full rounded-md border border-[var(--border)] bg-white p-2.5 text-xs text-[var(--foreground)] outline-none focus:border-[var(--accent-dark)]"></textarea>
                        </div>

                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">
                                Admin Inspection / Proof Images
                                <span x-show="decision === 'REJECT'" x-cloak class="text-rose-700">* Required when rejecting a warranty claim</span>
                                <span x-show="decision !== 'REJECT'" class="font-normal text-[var(--muted-foreground)]">(Optional on approvals)</span>
                            </label>
                            <input type="file"
                                   name="admin_images[]"
                                   accept="image/*"
                                   multiple
                                   :required="decision === 'REJECT'"
                                   @change="previewAdminFiles($event)"
                                   class="block w-full rounded-md border border-dashed border-[var(--border)] bg-white px-3 py-2 text-xs text-[var(--foreground)] file:mr-3 file:rounded file:border-0 file:bg-[var(--mint-100)] file:px-3 file:py-1 file:text-xs file:font-bold file:text-[var(--accent-dark)]">

                            <template x-if="adminPreviews.length > 0">
                                <div class="mt-2 flex flex-wrap gap-2">
                                    <template x-for="(p, idx) in adminPreviews" :key="idx">
                                        <div class="w-16 h-16 rounded-md border border-[var(--border)] bg-white overflow-hidden">
                                            <img :src="p.url" :alt="p.name" class="w-full h-full object-cover">
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>

                        <div class="flex justify-end">
                            <button type="submit"
                                    :class="decision === 'REJECT' ? 'bg-rose-700 hover:bg-rose-800' : 'bg-[var(--accent-dark)] hover:opacity-90'"
                                    class="inline-flex items-center gap-1.5 rounded-md px-5 py-2 text-xs font-bold text-white shadow-sm transition cursor-pointer">
                                <i class="fa-solid fa-gavel text-[11px]"></i>
                                <span x-text="decision === 'REJECT' ? 'Reject Warranty Claim' : 'Confirm &amp; Execute Ruling'">Confirm &amp; Execute Ruling</span>
                            </button>
                        </div>
                    </form>
                @else
                    <div class="rounded-md border {{ $claim->status === 'REJECTED' ? 'border-rose-300 bg-rose-50/70' : 'border-emerald-300 bg-emerald-50/70' }} p-3 space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2 text-[11px] font-bold">
                            <span>Ruling by {{ $claim->reviewer?->name ?? 'Admin' }} ({{ $claim->admin_decision }})</span>
                            <span>{{ $claim->reviewed_at?->format('M j, Y H:i') }}</span>
                        </div>
                        <p class="text-xs whitespace-pre-line">{{ $claim->admin_reply }}</p>
                        @if($claim->refund_amount)
                            <div class="text-xs font-bold text-emerald-900">Refund Processed: ৳{{ number_format((float) $claim->refund_amount, 2) }}</div>
                        @endif
                        @if($claim->followup_repair_request_id)
                            <a href="{{ route('requests.track', $claim->followup_repair_request_id) }}" class="inline-flex items-center gap-1 text-xs font-bold text-[var(--accent-dark)] hover:underline">
                                Free Repair Ticket #{{ $claim->followup_repair_request_id }} &rarr;
                            </a>
                        @endif
                        @if(!empty($claim->admin_image_urls))
                            <div class="flex flex-wrap gap-2">
                                @foreach($claim->admin_image_urls as $aImg)
                                    <button type="button" @click="lightboxPhoto = @js($aImg)" class="w-16 h-16 rounded border border-[var(--border)] overflow-hidden bg-white cursor-pointer">
                                        <img src="{{ $aImg }}" alt="Admin proof image" class="w-full h-full object-cover">
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        @empty
            <div class="rounded-lg border border-dashed border-[var(--border)] bg-[var(--card)] p-8 text-center text-xs text-[var(--muted-foreground)]">
                No warranty claims match the selected filter.
            </div>
        @endforelse
    </div>

    @if($claims->hasPages())
        <div>{{ $claims->links() }}</div>
    @endif

    {{-- Separate Warranty Registry Snapshot (`spare_part_warranties`) --}}
    <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 sm:p-5 shadow-sm space-y-3 text-xs">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div>
                <h2 class="text-base font-bold text-[var(--foreground)]">Separate Warrantied Listings Registry</h2>
                <p class="text-[11px] text-[var(--muted-foreground)]">Exclusively stores spare part listings backed by active warranty coverage (`spare_part_warranties`).</p>
            </div>
            <span class="rounded bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-1 text-[11px] font-bold text-[var(--accent-dark)]">
                {{ number_format($kpis['warrantied_listings']) }} Warrantied Listings
            </span>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-[var(--border)] text-[10px] font-bold uppercase text-[var(--muted-foreground)]">
                        <th class="py-2 pr-3">Listing</th>
                        <th class="py-2 pr-3">Shop</th>
                        <th class="py-2 pr-3">Duration</th>
                        <th class="py-2 pr-3">Coverage</th>
                        <th class="py-2">Stock</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[var(--border)]/60">
                    @forelse($registryPreview as $reg)
                        <tr>
                            <td class="py-2 pr-3 font-semibold text-[var(--foreground)]">
                                @if($reg->listing)
                                    <a href="{{ route('spare-parts.show', $reg->listing->slug) }}" class="hover:underline text-[var(--accent-dark)]">
                                        {{ \Illuminate\Support\Str::limit($reg->listing->title, 65) }}
                                    </a>
                                @else
                                    Listing #{{ $reg->spare_part_listing_id }}
                                @endif
                            </td>
                            <td class="py-2 pr-3 text-[var(--muted-foreground)]">{{ $reg->shop?->name }}</td>
                            <td class="py-2 pr-3 font-bold tabular-nums">{{ $reg->duration_days }} days</td>
                            <td class="py-2 pr-3">{{ $reg->coverageLabel() }}</td>
                            <td class="py-2 font-mono">{{ $reg->listing?->stock_quantity ?? 0 }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="py-4 text-center text-[var(--muted-foreground)]">No warrantied listings recorded yet.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Photo Zoom Lightbox --}}
    <div x-show="lightboxPhoto" x-cloak @keydown.escape.window="lightboxPhoto = null" class="fixed inset-0 z-[150] flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-black/80 backdrop-blur-sm" @click="lightboxPhoto = null"></div>
        <div class="relative z-[151] w-full max-w-3xl rounded-lg border border-[var(--border)] bg-[var(--card)] p-4 shadow-2xl space-y-3">
            <div class="flex items-center justify-between border-b border-[var(--border)] pb-2">
                <span class="text-xs font-bold uppercase text-[var(--accent-dark)]">Warranty Evidence Photo</span>
                <button type="button" @click="lightboxPhoto = null" class="h-8 w-8 rounded-full bg-[var(--accent-dark)] text-white font-bold">✕</button>
            </div>
            <div class="flex max-h-[75vh] items-center justify-center overflow-auto rounded bg-black/90 p-2">
                <img :src="lightboxPhoto" alt="Zoomed warranty evidence" class="max-h-[70vh] max-w-full object-contain">
            </div>
        </div>
    </div>
</div>
@endsection

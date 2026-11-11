@extends('layouts.app')

@section('title', 'Write Verified Review — ' . $shop->name . ' | Meramot')

{{-- ==========================================================================
     FEATURE R9: Vendor Rating & Verified Repair Review System
     Assigned Member: Shanid Sajjatuz Islam [23201465]
     Mint-Green UI Merge: Farhan Tanvir [22299179]
     Client Star Helper Relocated To: frontend/js/marketplace-client.js
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-2xl px-4 py-6 md:px-6">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>›</span>
        <a href="{{ route('shops.show', $shop->slug) }}" class="hover:underline">{{ $shop->name }}</a>
        <span>›</span>
        <span class="text-[var(--foreground)] font-semibold">Write Verified Review</span>
    </div>

    <!-- Shop Summary Banner -->
    <div class="mb-6 flex items-center justify-between gap-4 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
        <div class="flex items-center gap-3.5 min-w-0">
            <div class="h-12 w-12 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] flex items-center justify-center font-black text-xl text-[var(--accent-dark)] shrink-0">
                {{ strtoupper(substr($shop->name, 0, 1)) }}
            </div>
            <div class="min-w-0">
                <span class="dev-only-badge text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 border border-emerald-300 inline-block mb-1">
                    Feature R9 • Shanid Sajjatuz Islam [23201465]
                </span>
                <h2 class="text-lg font-black text-[var(--foreground)] truncate">{{ $shop->name }}</h2>
                <p class="text-xs text-[var(--muted-foreground)] truncate">{{ $shop->area }} • {{ $shop->address }}</p>
            </div>
        </div>
        <div class="text-right shrink-0 rounded-2xl bg-[var(--mint-100)] border border-[var(--border)] px-4 py-2">
            @if((int) ($shop->review_count ?? 0) > 0)
                <div class="text-xl font-black text-[var(--accent-dark)]">★ {{ number_format($shop->rating, 1) }}</div>
                <div class="text-[10px] text-[var(--muted-foreground)]">{{ $shop->review_count }} {{ \Illuminate\Support\Str::plural('review', $shop->review_count) }}</div>
            @else
                <div class="text-base font-black text-[var(--accent-dark)]">First Review</div>
                <div class="text-[10px] text-[var(--muted-foreground)]">0 reviews yet</div>
            @endif
        </div>
    </div>

    <!-- Review Submission Card -->
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm">
        <h1 class="mb-6 text-2xl font-black text-[var(--foreground)]">Share Your Repair Experience</h1>

        <form action="/reviews" method="POST" class="space-y-6 text-xs">
            @csrf
            <input type="hidden" name="repair_shop_id" value="{{ $shop->id }}">

            <!-- Interactive Star Selector (Powered by frontend/js/marketplace-client.js) -->
            <div>
                <label class="mb-2 block font-bold text-[var(--foreground)]">Overall Repair Rating *</label>
                <div class="flex items-center gap-2" id="star-rating">
                    @for($i = 1; $i <= 5; $i++)
                        <button
                            type="button"
                            onclick="setRating({{ $i }})"
                            class="star text-4xl text-[var(--muted)] opacity-50 transition transform hover:scale-110 hover:text-amber-500 hover:opacity-100"
                            data-rating="{{ $i }}"
                        >★</button>
                    @endfor
                </div>
                <input type="hidden" name="rating" id="rating-input" value="{{ old('rating') }}" required>
                <p class="mt-2 text-xs font-semibold text-[var(--muted-foreground)]" id="rating-text">Click a star to rate your experience (1–5)</p>
                @error('rating')
                    <p class="mt-1 text-xs font-bold text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <!-- Review Comment -->
            <div>
                <label for="review_comment" class="mb-1.5 block font-bold text-[var(--foreground)]">Detailed Feedback *</label>
                <textarea
                    name="review_comment"
                    id="review_comment"
                    rows="5"
                    required
                    minlength="10"
                    maxlength="1000"
                    placeholder="How was the diagnostic accuracy, spare part quality, pricing transparency, and doorstep courier turnaround?"
                    class="w-full rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] px-4 py-3 text-xs text-[var(--foreground)] placeholder:text-[var(--muted-foreground)] outline-none leading-relaxed transition"
                >{{ old('review_comment') }}</textarea>
                <p class="mt-1 text-[11px] text-[var(--muted-foreground)]">Minimum 10 characters • Verified repair review</p>
                @error('review_comment')
                    <p class="mt-1 text-xs font-bold text-rose-700">{{ $message }}</p>
                @enderror
            </div>

            <!-- Action Buttons -->
            <div class="flex items-center gap-3 pt-2 border-t border-[var(--border)]">
                <a href="{{ route('shops.show', $shop->slug) }}" class="flex-1 rounded-full border border-[var(--border)] bg-[var(--mint-100)] px-5 py-3 text-center font-bold text-[var(--foreground)] hover:bg-[var(--mint-200)] transition">
                    Cancel
                </a>
                <button type="submit" class="flex-1 rounded-full bg-[var(--accent-dark)] px-5 py-3 font-bold text-[var(--accent-foreground)] shadow hover:opacity-90 transition">
                    Submit Verified Review
                </button>
            </div>
        </form>
    </div>

    <!-- Mint Review Guidelines -->
    <div class="mt-6 rounded-[1.5rem] border border-[var(--border)] bg-[var(--mint-100)] p-5 text-xs">
        <h3 class="mb-2 font-black text-[var(--foreground)] flex items-center gap-1.5">
            <i class="fa-solid fa-shield-check text-emerald-700"></i>
            <span>Verified Review Guidelines</span>
        </h3>
        <ul class="space-y-1 text-[var(--muted-foreground)]">
            <li>✓ Be specific about the repaired device and parts replaced</li>
            <li>✓ Highlight technician communication, warranty adherence, and turnaround speed</li>
            <li>✓ Keep feedback respectful and constructive for Dhaka gadget owners</li>
        </ul>
    </div>
</div>
@endsection

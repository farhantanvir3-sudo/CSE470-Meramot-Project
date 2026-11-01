{{--
=============================================================================
FEATURE R9: Vendor Rating & Verified Repair Review System
=============================================================================
Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
Architectural Layer: View (Reviews/WriteReview)
Controller: ReviewController@create, store
Models Touched: ShopReview, Order

INSTRUCTIONS FOR SHANID:
- Star rating selector (1 to 5 stars).
- Textarea for detailed repair quality review.
- Submission verified against completed repair requests.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Write Verified Review | Feature R9')

@section('content')
<div class="max-w-xl mx-auto bg-white rounded-xl border border-slate-200 p-8 shadow-sm">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Leave a Verified Review</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R9 | Assigned to: <strong class="text-teal-700">Shanid Sajjatuz Islam [23201465]</strong></p>
    </div>

    <form method="POST" action="/reviews" class="space-y-5 text-sm">
        @csrf
        <input type="hidden" name="repair_shop_id" value="{{ $shop->id ?? 1 }}">

        <div>
            <label class="block font-semibold text-slate-700 text-xs mb-1">Select Star Rating</label>
            <select name="rating" class="w-full rounded-lg border-slate-300 text-xs py-2" required>
                <option value="5">★★★★★ 5 - Outstanding Quality</option>
                <option value="4">★★★★☆ 4 - Good & Timely</option>
                <option value="3">★★★☆☆ 3 - Average Service</option>
                <option value="2">★★☆☆☆ 2 - Below Expectations</option>
                <option value="1">★☆☆☆☆ 1 - Poor / Unsatisfactory</option>
            </select>
        </div>

        <div>
            <label class="block font-semibold text-slate-700 text-xs mb-1">Your Detailed Experience</label>
            <textarea name="review_comment" rows="4" placeholder="How did the technician perform? Were genuine replacement parts used?" class="w-full rounded-lg border-slate-300 text-xs p-3" required></textarea>
        </div>

        <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white font-semibold py-2 rounded-lg text-xs">
            Submit Verified Review
        </button>
    </form>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Write Review - ' . $shop->name)

@section('content')
<div class="mx-auto max-w-2xl px-4 py-6 md:px-6 md:py-8">
    <!-- Shop Info -->
    <div class="mb-6 flex items-center gap-4 rounded-lg border border-gray-200 bg-white p-4">
        <div class="flex-1">
            <h2 class="text-xl font-bold text-gray-900">{{ $shop->name }}</h2>
            <p class="text-sm text-gray-600">{{ $shop->area }} • {{ $shop->address }}</p>
        </div>
        <div class="text-right">
            <div class="text-2xl font-bold text-gray-900">{{ number_format($shop->rating, 1) }}</div>
            <div class="text-xs text-gray-500">{{ $shop->review_count }} reviews</div>
        </div>
    </div>

    <!-- Review Form -->
    <div class="rounded-lg border border-gray-200 bg-white p-6 shadow-sm">
        <h1 class="mb-6 text-2xl font-bold text-gray-900">Write Your Review</h1>

        <form action="/reviews" method="POST">
            @csrf
            <input type="hidden" name="repair_shop_id" value="{{ $shop->id }}">

            <!-- Star Rating -->
            <div class="mb-6">
                <label class="mb-2 block text-sm font-medium text-gray-700">Rating *</label>
                <div class="flex gap-2" id="star-rating">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button" onclick="setRating({{ $i }})" class="star text-4xl text-gray-300 transition hover:text-yellow-400" data-rating="{{ $i }}">
                            ★
                        </button>
                    @endfor
                </div>
                <input type="hidden" name="rating" id="rating-input" required>
                <p class="mt-2 text-sm text-gray-500" id="rating-text">Click stars to rate</p>
                @error('rating')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Review Comment -->
            <div class="mb-6">
                <label for="review_comment" class="mb-2 block text-sm font-medium text-gray-700">Your Review *</label>
                <textarea 
                    name="review_comment" 
                    id="review_comment" 
                    rows="6" 
                    required 
                    minlength="10"
                    maxlength="1000"
                    class="w-full rounded-lg border border-gray-300 px-4 py-2 focus:border-blue-500 focus:ring-blue-500"
                    placeholder="Share your experience with this repair shop. What did you like? How was the service quality?"
                ></textarea>
                <p class="mt-1 text-sm text-gray-500">Minimum 10 characters required</p>
                @error('review_comment')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <!-- Submit Button -->
            <div class="flex gap-3">
                <a href="{{ route('shops.show', $shop->slug) }}" class="flex-1 rounded-lg border border-gray-300 px-4 py-3 text-center text-gray-700 hover:bg-gray-50">
                    Cancel
                </a>
                <button type="submit" class="flex-1 rounded-lg bg-blue-600 px-4 py-3 font-semibold text-white hover:bg-blue-700">
                    Submit Review
                </button>
            </div>
        </form>
    </div>

    <!-- Review Guidelines -->
    <div class="mt-6 rounded-lg border border-blue-100 bg-blue-50 p-4">
        <h3 class="mb-2 font-semibold text-blue-900">Review Guidelines</h3>
        <ul class="space-y-1 text-sm text-blue-800">
            <li>✓ Be honest and specific about your experience</li>
            <li>✓ Focus on the repair service quality and professionalism</li>
            <li>✓ Avoid personal attacks or offensive language</li>
            <li>✓ Reviews help other customers make informed decisions</li>
        </ul>
    </div>
</div>

<script>
const stars = document.querySelectorAll('.star');
const ratingInput = document.getElementById('rating-input');
const ratingText = document.getElementById('rating-text');

const ratingLabels = {
    1: 'Poor',
    2: 'Fair',
    3: 'Good',
    4: 'Very Good',
    5: 'Excellent'
};

function setRating(rating) {
    ratingInput.value = rating;
    ratingText.textContent = `${rating} star${rating > 1 ? 's' : ''} - ${ratingLabels[rating]}`;
    
    stars.forEach((star, index) => {
        if (index < rating) {
            star.classList.remove('text-gray-300');
            star.classList.add('text-yellow-400');
        } else {
            star.classList.remove('text-yellow-400');
            star.classList.add('text-gray-300');
        }
    });
}

// Hover effect
stars.forEach((star, index) => {
    star.addEventListener('mouseenter', () => {
        stars.forEach((s, i) => {
            if (i <= index) {
                s.classList.add('text-yellow-400');
                s.classList.remove('text-gray-300');
            }
        });
    });
});

document.getElementById('star-rating').addEventListener('mouseleave', () => {
    const currentRating = parseInt(ratingInput.value) || 0;
    stars.forEach((star, index) => {
        if (index < currentRating) {
            star.classList.add('text-yellow-400');
            star.classList.remove('text-gray-300');
        } else {
            star.classList.remove('text-yellow-400');
            star.classList.add('text-gray-300');
        }
    });
});
</script>
@endsection

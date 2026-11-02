@extends('layouts.app')

@section('title', 'Service Packages - Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6 md:py-8">
    <!-- Header -->
    <div class="mb-6 flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900">Service Packages</h1>
            <p class="mt-1 text-sm text-gray-600">Fixed-price repair packages with guaranteed quality</p>
        </div>
        <a href="{{ route('cart.view') }}" class="relative inline-flex items-center rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-700">
            <svg class="mr-2 h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            Cart
            @if($cartCount > 0)
                <span class="ml-2 inline-flex h-5 w-5 items-center justify-center rounded-full bg-white text-xs font-bold text-blue-600">{{ $cartCount }}</span>
            @endif
        </a>
    </div>

    <!-- Filters -->
    <div class="mb-6 flex flex-wrap gap-3">
        <form method="GET" action="{{ route('packages.catalog') }}" class="flex w-full flex-wrap gap-3">
            <!-- Search -->
            <input type="text" name="q" value="{{ request('q') }}" placeholder="Search packages..." class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
            
            <!-- Category Filter -->
            <select name="category" class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">All Categories</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" {{ request('category') == $cat ? 'selected' : '' }}>{{ $cat }}</option>
                @endforeach
            </select>

            <!-- Price Filter -->
            <select name="max_price" class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">Any Price</option>
                <option value="2000" {{ request('max_price') == '2000' ? 'selected' : '' }}>Under ৳2,000</option>
                <option value="5000" {{ request('max_price') == '5000' ? 'selected' : '' }}>Under ৳5,000</option>
                <option value="10000" {{ request('max_price') == '10000' ? 'selected' : '' }}>Under ৳10,000</option>
            </select>

            <!-- Sort -->
            <select name="sort" class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="popular" {{ request('sort') == 'popular' ? 'selected' : '' }}>Most Popular</option>
                <option value="price_low" {{ request('sort') == 'price_low' ? 'selected' : '' }}>Price: Low to High</option>
                <option value="price_high" {{ request('sort') == 'price_high' ? 'selected' : '' }}>Price: High to Low</option>
            </select>

            <button type="submit" class="rounded-lg bg-gray-900 px-6 py-2 text-sm font-semibold text-white hover:bg-gray-800">
                Apply Filters
            </button>
        </form>
    </div>

    <!-- Package Grid -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($packages as $package)
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm transition hover:shadow-md">
                @if($package->is_popular)
                    <div class="bg-blue-600 px-3 py-1 text-xs font-semibold text-white">
                        ⭐ Most Popular
                    </div>
                @endif
                
                <div class="p-5">
                    <div class="mb-2 inline-block rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-800">
                        {{ $package->device_category }}
                    </div>
                    
                    <h3 class="text-lg font-bold text-gray-900">{{ $package->title }}</h3>
                    <p class="mt-2 text-sm text-gray-600">{{ Str::limit($package->description, 100) }}</p>
                    
                    <div class="mt-4 flex items-baseline justify-between">
                        <div>
                            <span class="text-2xl font-bold text-gray-900">৳{{ number_format($package->fixed_price) }}</span>
                            <span class="ml-2 text-sm text-gray-500">{{ $package->estimated_duration_hours }}h</span>
                        </div>
                    </div>

                    @if($package->included_items)
                        <ul class="mt-3 space-y-1 text-sm text-gray-600">
                            @foreach(array_slice($package->included_items, 0, 3) as $item)
                                <li class="flex items-center">
                                    <svg class="mr-2 h-4 w-4 text-green-500" fill="currentColor" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/>
                                    </svg>
                                    {{ $item }}
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <button 
                        onclick="addToCart({{ $package->id }})"
                        class="mt-4 w-full rounded-lg bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-700"
                    >
                        Add to Cart
                    </button>
                </div>
            </div>
        @empty
            <div class="col-span-full rounded-lg border-2 border-dashed border-gray-300 p-12 text-center">
                <p class="text-gray-500">No packages found matching your criteria.</p>
                <a href="{{ route('packages.catalog') }}" class="mt-4 inline-block text-blue-600 hover:underline">Clear filters</a>
            </div>
        @endforelse
    </div>

    <!-- Pagination -->
    <div class="mt-8">
        {{ $packages->links() }}
    </div>
</div>

<script>
function addToCart(packageId) {
    fetch('/cart/add', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ package_id: packageId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('✅ Package added to cart!');
            location.reload();
        }
    })
    .catch(err => console.error(err));
}
</script>
@endsection

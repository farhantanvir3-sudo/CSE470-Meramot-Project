@extends('layouts.app')

@section('title', 'Shopping Cart - Meramot')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6 md:px-6 md:py-8">
    <h1 class="mb-6 text-3xl font-bold text-gray-900">Shopping Cart</h1>

    @if(empty($cart))
        <div class="rounded-lg border-2 border-dashed border-gray-300 p-12 text-center">
            <svg class="mx-auto h-16 w-16 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/>
            </svg>
            <p class="mt-4 text-lg text-gray-500">Your cart is empty</p>
            <a href="{{ route('packages.catalog') }}" class="mt-6 inline-block rounded-lg bg-blue-600 px-6 py-3 text-white hover:bg-blue-700">
                Browse Packages
            </a>
        </div>
    @else
        <div class="space-y-4">
            @foreach($cart as $id => $item)
                <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white p-4 shadow-sm">
                    <div class="flex-1">
                        <h3 class="font-semibold text-gray-900">{{ $item['title'] }}</h3>
                        <p class="text-sm text-gray-600">{{ $item['category'] }} • {{ $item['duration'] }} hours</p>
                        <p class="mt-1 text-lg font-bold text-gray-900">৳{{ number_format($item['price']) }} × {{ $item['quantity'] }}</p>
                    </div>

                    <div class="flex items-center gap-4">
                        <div class="flex items-center gap-2">
                            <button onclick="updateQuantity({{ $id }}, {{ $item['quantity'] - 1 }})" class="rounded border px-2 py-1 hover:bg-gray-100">−</button>
                            <span class="w-8 text-center">{{ $item['quantity'] }}</span>
                            <button onclick="updateQuantity({{ $id }}, {{ $item['quantity'] + 1 }})" class="rounded border px-2 py-1 hover:bg-gray-100">+</button>
                        </div>

                        <button onclick="removeFromCart({{ $id }})" class="text-red-600 hover:text-red-800">
                            <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                            </svg>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Summary -->
        <div class="mt-8 rounded-lg border border-gray-200 bg-gray-50 p-6">
            <div class="flex items-center justify-between text-lg">
                <span class="font-semibold text-gray-700">Total:</span>
                <span class="text-2xl font-bold text-gray-900">৳{{ number_format($total) }}</span>
            </div>

            <div class="mt-6 flex gap-3">
                <form action="{{ route('cart.clear') }}" method="POST" class="flex-1">
                    @csrf
                    <button type="submit" class="w-full rounded-lg border border-gray-300 px-4 py-3 text-gray-700 hover:bg-gray-100">
                        Clear Cart
                    </button>
                </form>
                <a href="{{ route('cart.checkout') }}" class="flex-1 rounded-lg bg-blue-600 px-4 py-3 text-center font-semibold text-white hover:bg-blue-700">
                    Proceed to Checkout
                </a>
            </div>
        </div>
    @endif
</div>

<script>
function updateQuantity(packageId, newQuantity) {
    if (newQuantity < 1) return;
    
    fetch(`/cart/update/${packageId}`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        },
        body: JSON.stringify({ quantity: newQuantity })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
    });
}

function removeFromCart(packageId) {
    if (!confirm('Remove this item from cart?')) return;
    
    fetch(`/cart/remove/${packageId}`, {
        method: 'DELETE',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) location.reload();
    });
}
</script>
@endsection

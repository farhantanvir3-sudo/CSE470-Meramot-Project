@extends('layouts.app')

@section('title', 'Checkout - Meramot')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6 md:px-6 md:py-8">
    <h1 class="mb-6 text-3xl font-bold text-gray-900">Checkout</h1>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Order Summary -->
        <div class="lg:col-span-2">
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-xl font-semibold">Order Summary</h2>
                
                <div class="space-y-3">
                    @foreach($cart as $item)
                        <div class="flex justify-between border-b pb-3">
                            <div>
                                <p class="font-medium">{{ $item['title'] }}</p>
                                <p class="text-sm text-gray-600">Qty: {{ $item['quantity'] }}</p>
                            </div>
                            <p class="font-semibold">৳{{ number_format($item['price'] * $item['quantity']) }}</p>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4 flex justify-between text-lg font-bold">
                    <span>Total:</span>
                    <span>৳{{ number_format($total) }}</span>
                </div>
            </div>
        </div>

        <!-- Payment Form -->
        <div class="lg:col-span-1">
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <h2 class="mb-4 text-xl font-semibold">Payment</h2>
                
                <form action="/payment/initiate" method="POST">
                    @csrf
                    <input type="hidden" name="total_amount" value="{{ $total }}">
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Contact Phone</label>
                        <input type="tel" name="phone" required class="mt-1 w-full rounded-lg border px-3 py-2" placeholder="01712345678">
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700">Service Address</label>
                        <textarea name="address" required class="mt-1 w-full rounded-lg border px-3 py-2" rows="3" placeholder="Your address"></textarea>
                    </div>

                    <button type="submit" class="w-full rounded-lg bg-green-600 px-4 py-3 font-semibold text-white hover:bg-green-700">
                        Pay ৳{{ number_format($total) }}
                    </button>
                </form>

                <p class="mt-4 text-xs text-gray-500 text-center">
                    Secure payment via SSLCommerz
                </p>
            </div>
        </div>
    </div>
</div>
@endsection

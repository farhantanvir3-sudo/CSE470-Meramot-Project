@extends('layouts.app')

@section('title', 'Direct Fixed Service Packages Catalog | Meramot')

@section('content')
<div class="mx-auto max-w-7xl px-4 py-6 md:px-6">

    <!-- Header -->
    <div class="mb-6 flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div>
            <div class="flex items-center gap-2 text-xs text-[var(--muted-foreground)] mb-1">
                <a href="/" class="hover:underline">Home</a>
                <span>/</span>
                <span class="text-[var(--foreground)] font-semibold">Service Packages</span>
            </div>
            <h1 class="text-2xl font-black text-[var(--foreground)]">
                Pre-Configured Service Packages
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-0.5 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R8 • Lead: Shanid Sajjatuz Islam [23201465]
                </span>
                <span>Fixed transparent pricing, repair warranty included, and doorstep dispatch</span>
            </p>
        </div>

        <div class="flex items-center gap-3">
            <span class="text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3 py-1.5 rounded-full border border-[var(--border)]">
                <i class="fa-solid fa-shield-halved text-emerald-700 mr-1"></i> Meramot Warranty Guaranteed
            </span>
        </div>
    </div>

    <!-- Active Cart Preview (If session cart has items) -->
    @if(session('cart') && count(session('cart')) > 0)
        <div class="mb-6 meramot-card p-4 border-2 border-emerald-600 bg-[var(--mint-100)] flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <div class="h-10 w-10 rounded-full bg-emerald-800 text-white flex items-center justify-center font-bold text-sm">
                    {{ count(session('cart')) }}
                </div>
                <div>
                    <h4 class="font-bold text-xs text-[var(--foreground)]">Packages Ready in Cart</h4>
                    <p class="text-[11px] text-[var(--muted-foreground)]">Proceed to doorstep pickup scheduling & escrow checkout</p>
                </div>
            </div>
            <div class="flex items-center gap-3">
                <a href="/requests/create" class="rounded-full bg-[var(--accent-dark)] text-white text-xs font-bold px-5 py-2 hover:opacity-90 transition shadow">
                    Checkout Packages &rarr;
                </a>
            </div>
        </div>
    @endif

    <!-- Package Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($packages ?? [] as $pkg)
            <div class="meramot-card p-6 shadow-sm hover:shadow-md transition flex flex-col justify-between group">
                <div>
                    <!-- Badge & Category -->
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-extrabold uppercase px-3 py-1 rounded-full bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)]">
                            {{ $pkg->device_category }}
                        </span>
                        @if($pkg->is_popular)
                            <span class="text-[10px] font-bold bg-amber-100 text-amber-800 border border-amber-300 px-2.5 py-0.5 rounded-full flex items-center gap-1">
                                <i class="fa-solid fa-star text-[9px]"></i> Most Popular
                            </span>
                        @endif
                    </div>

                    <!-- Title & Price -->
                    <h3 class="font-black text-lg text-[var(--foreground)] mt-3 group-hover:text-[var(--accent-dark)] transition">
                        {{ $pkg->title }}
                    </h3>

                    <div class="mt-2 flex items-baseline gap-2">
                        <span class="text-3xl font-black text-[var(--accent-dark)]">
                            ৳{{ number_format($pkg->fixed_price) }}
                        </span>
                        <span class="text-xs text-[var(--muted-foreground)]">Fixed All-Inclusive</span>
                    </div>

                    <!-- Meta specs -->
                    <div class="mt-3 flex items-center gap-3 text-xs text-[var(--muted-foreground)] py-2 border-y border-[var(--border)]">
                        <span class="flex items-center gap-1 font-semibold text-[var(--foreground)]">
                            <i class="fa-solid fa-clock text-[var(--accent-dark)]"></i>
                            {{ $pkg->estimated_turnaround_hours }} Hours Turnaround
                        </span>
                        <span>•</span>
                        <span class="flex items-center gap-1 font-semibold text-[var(--foreground)]">
                            <i class="fa-solid fa-shield-halved text-emerald-700"></i>
                            {{ $pkg->warranty_months }} Months Warranty
                        </span>
                    </div>

                    <!-- Inclusions List -->
                    <div class="mt-4">
                        <h5 class="text-[11px] font-bold text-[var(--foreground)] uppercase tracking-wider mb-2">Package Inclusions:</h5>
                        <ul class="space-y-1.5 text-xs text-[var(--muted-foreground)]">
                            @foreach($pkg->inclusions ?? [] as $inc)
                                <li class="flex items-start gap-2">
                                    <i class="fa-solid fa-check text-emerald-700 mt-1 text-[10px]"></i>
                                    <span>{{ $inc }}</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <!-- Add to Cart Form -->
                <form method="POST" action="/cart/add" class="mt-6 pt-4 border-t border-[var(--border)]">
                    @csrf
                    <input type="hidden" name="package_id" value="{{ $pkg->id }}">
                    <button type="submit" class="w-full rounded-2xl bg-[var(--accent-dark)] hover:opacity-90 text-white py-2.5 text-xs font-bold shadow transition flex items-center justify-center gap-2">
                        <i class="fa-solid fa-cart-plus"></i>
                        <span>Select Package & Add to Cart</span>
                    </button>
                </form>
            </div>
        @empty
            <div class="col-span-3 text-center py-16 meramot-card p-8">
                <p class="text-xs text-[var(--muted-foreground)]">No standard service packages loaded in database.</p>
            </div>
        @endforelse
    </div>

</div>
@endsection

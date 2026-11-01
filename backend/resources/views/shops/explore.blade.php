{{--
=============================================================================
FEATURE R6: Advanced Repair Shop Search & Multi-Parameter Filtering
=============================================================================
Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
Architectural Layer: View (Shops/Explore)
Controller: ShopDiscoveryController@index, show
Models Touched: RepairShop, Category

INSTRUCTIONS FOR SHANID:
- Render shop search filters sidebar: Area dropdown (Dhanmondi, Gulshan, Banani, etc.),
  minimum customer rating slider/buttons, and special voucher toggles.
- Display responsive grid of verified repair shops with ratings, badges, and specialties.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Explore Verified Repair Shops | Feature R6')

@section('content')
<div class="mb-6 flex items-center justify-between">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">Explore Verified Repair Shops</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R6 | Assigned to: <strong class="text-teal-700">Shanid Sajjatuz Islam [23201465]</strong></p>
    </div>
    <span class="text-xs bg-amber-100 text-amber-800 font-semibold px-3 py-1 rounded-full border border-amber-200">
        Sprint 1 Target
    </span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-4 gap-6">
    <!-- Filter Sidebar -->
    <div class="bg-white p-5 rounded-xl border border-slate-200 shadow-sm h-fit">
        <h3 class="font-bold text-sm text-slate-800 mb-4">Search & Filters</h3>
        <form method="GET" action="/shops/explore" class="space-y-4 text-xs">
            <div>
                <label class="block font-semibold text-slate-600 mb-1">Dhaka Metro Area</label>
                <select name="area" class="w-full rounded-lg border-slate-300 text-xs py-2">
                    <option value="">All Areas</option>
                    <option value="Dhanmondi">Dhanmondi</option>
                    <option value="Gulshan">Gulshan</option>
                    <option value="Banani">Banani</option>
                    <option value="Mirpur">Mirpur</option>
                    <option value="Uttara">Uttara</option>
                </select>
            </div>
            <div>
                <label class="block font-semibold text-slate-600 mb-1">Minimum Customer Rating</label>
                <select name="min_rating" class="w-full rounded-lg border-slate-300 text-xs py-2">
                    <option value="">Any Rating</option>
                    <option value="4.5">★ 4.5 & Above</option>
                    <option value="4.8">★ 4.8 & Above</option>
                </select>
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="has_voucher" value="1" id="voucherCheck" class="rounded text-teal-600">
                <label for="voucherCheck" class="text-slate-700">Has Promotional Voucher</label>
            </div>
            <button type="submit" class="w-full bg-teal-600 hover:bg-teal-700 text-white py-2 rounded-lg font-semibold transition">
                Apply Filters
            </button>
        </form>
    </div>

    <!-- Shop Cards Grid -->
    <div class="lg:col-span-3 grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4">
        @forelse($shops ?? [] as $shop)
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-sm hover:shadow-md transition flex flex-col justify-between">
                <div>
                    <div class="flex justify-between items-start">
                        <h4 class="font-bold text-slate-900 text-sm">{{ $shop->name }}</h4>
                        <span class="text-xs bg-amber-50 text-amber-700 px-2 py-0.5 rounded font-bold border border-amber-200">
                            ★ {{ $shop->rating }}
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-1"><i class="fa-solid fa-location-dot mr-1"></i> {{ $shop->area }}, Dhaka</p>
                    <div class="mt-3 flex flex-wrap gap-1">
                        @foreach(array_slice($shop->specialties ?? ['Screen repair', 'Battery replacement'], 0, 2) as $spec)
                            <span class="text-[10px] bg-slate-100 text-slate-600 px-2 py-0.5 rounded">{{ $spec }}</span>
                        @endforeach
                    </div>
                </div>
                <a href="/shops/{{ $shop->slug }}" class="mt-4 block text-center bg-slate-50 hover:bg-teal-50 hover:text-teal-700 text-slate-700 py-1.5 rounded-lg text-xs font-semibold border border-slate-200 transition">
                    View Shop Profile &rarr;
                </a>
            </div>
        @empty
            <div class="col-span-3 py-10 text-center text-xs text-slate-400">
                No repair shops matched your filter criteria.
            </div>
        @endforelse
    </div>
</div>
@endsection

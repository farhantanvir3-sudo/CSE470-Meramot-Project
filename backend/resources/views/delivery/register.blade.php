@extends('layouts.app')

@section('title', 'Apply as Delivery Agent | Meramot')

@section('content')
<div class="max-w-lg mx-auto">
    <h1 class="text-2xl font-bold text-slate-900 mb-1">Apply as a Delivery Agent</h1>
    <p class="text-sm text-slate-500 mb-6">Fill in your details. An admin will review your application.</p>

    <form method="POST" action="/delivery-agent/register"
          class="bg-white rounded-xl border border-slate-200 shadow-sm p-6 space-y-4">
        @csrf

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">NID Number</label>
            <input type="text" name="nid_number" value="{{ old('nid_number') }}"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" required>
            @error('nid_number') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">Vehicle Type</label>
            <select name="vehicle_type"
                    class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" required>
                <option value="motorcycle">Motorcycle</option>
                <option value="bicycle">Bicycle</option>
                <option value="scooter">Scooter</option>
                <option value="van">Van</option>
            </select>
            @error('vehicle_type') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <div>
            <label class="block text-sm font-medium text-slate-700 mb-1">License Number</label>
            <input type="text" name="license_number" value="{{ old('license_number') }}"
                   class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm" required>
            @error('license_number') <p class="text-xs text-rose-600 mt-1">{{ $message }}</p> @enderror
        </div>

        <button type="submit"
                class="w-full bg-emerald-600 text-white rounded-lg py-2 text-sm font-semibold hover:bg-emerald-700">
            Submit Application
        </button>
    </form>
</div>
@endsection
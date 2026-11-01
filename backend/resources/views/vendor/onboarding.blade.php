{{--
=============================================================================
FEATURE R11: Vendor Onboarding & Business Profile Verification Engine
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
Architectural Layer: View (Vendor/Onboarding)
Controller: VendorOnboardingController@show, store, verify
Models Touched: VendorProfile, Document

INSTRUCTIONS FOR FARHAN:
- Vendor profile application form (Trade License, NID, Shop GPS address, categories).
- Status monitor for vendor applicants (Pending Review / Approved / Rejected).
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Vendor Onboarding & Verification | Meramot')

@section('content')
<div class="max-w-2xl mx-auto bg-white rounded-xl border border-slate-200 p-8 shadow-sm">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Vendor Onboarding & Profile Verification</h1>
        <p class="text-xs text-slate-500 mt-1 dev-only-badge">Feature R11 | Assigned to: <strong class="text-teal-700">Farhan Tanvir [22299179]</strong></p>
    </div>

    <form method="POST" action="/vendors/onboarding" class="space-y-4 text-xs">
        @csrf
        <div>
            <label class="block font-semibold text-slate-700 mb-1">Repair Shop Legal Business Name</label>
            <input type="text" name="shop_name" placeholder="e.g. Dhaka Circuit Care" class="w-full rounded-lg border-slate-300 text-xs py-2" required>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Trade License Number</label>
                <input type="text" name="business_license_no" placeholder="TRAD/DNCC/129482" class="w-full rounded-lg border-slate-300 text-xs py-2" required>
            </div>
            <div>
                <label class="block font-semibold text-slate-700 mb-1">Owner National NID</label>
                <input type="text" name="nid_number" placeholder="199219293120" class="w-full rounded-lg border-slate-300 text-xs py-2" required>
            </div>
        </div>

        <div>
            <label class="block font-semibold text-slate-700 mb-1">Physical Shop Address</label>
            <input type="text" name="shop_address" placeholder="Shop 4B, Multiplan Center, Elephant Road, Dhaka" class="w-full rounded-lg border-slate-300 text-xs py-2" required>
        </div>

        <div>
            <label class="block font-semibold text-slate-700 mb-1">Service Categories Supported</label>
            <div class="grid grid-cols-2 gap-2 mt-2">
                <label class="flex items-center gap-2"><input type="checkbox" name="categories[]" value="Laptop" checked> Laptop & PC Motherboards</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="categories[]" value="Phone" checked> Smartphone Hardware</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="categories[]" value="Display"> Display Panel Replacement</label>
                <label class="flex items-center gap-2"><input type="checkbox" name="categories[]" value="WaterDamage"> Water Damage Micro-soldering</label>
            </div>
        </div>

        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2.5 rounded-lg text-xs mt-4">
            Submit Application for Verification
        </button>
    </form>
</div>
@endsection

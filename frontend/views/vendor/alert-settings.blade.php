{{--
=============================================================================
FEATURE R14: Automated Multi-Channel BulkSMS & Email Notification
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
Architectural Layer: View (Vendor/AlertSettings)
Controller: VendorNotificationController@dispatchBiddingAlert
Models Touched: NotificationLog

INSTRUCTIONS FOR FARHAN:
- Vendor notification preferences (SMS alert threshold, Email instant alerts).
- Dispatches via BulkSMS BD API gateway and Mailgun/SMTP.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Vendor Alert & SMS Settings | Meramot')

@section('content')
<div class="max-w-xl mx-auto bg-white rounded-xl border border-slate-200 p-8 shadow-sm">
    <div class="mb-6">
        <h1 class="text-2xl font-bold text-slate-900">Multi-Channel Alert Engine</h1>
        <p class="text-xs text-slate-500 mt-1 dev-only-badge">Feature R14 | Assigned to: <strong class="text-teal-700">Farhan Tanvir [22299179]</strong></p>
    </div>

    <form class="space-y-4 text-xs">
        <div class="space-y-3">
            <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
                <input type="checkbox" checked class="rounded text-indigo-600">
                <div>
                    <span class="font-bold text-slate-800 block">Instant BulkSMS BD Alerts</span>
                    <span class="text-slate-500 text-[11px]">Receive instant SMS notification on registered mobile when a device in your category opens for bidding.</span>
                </div>
            </label>

            <label class="flex items-center gap-3 p-3 bg-slate-50 rounded-lg border border-slate-200 cursor-pointer">
                <input type="checkbox" checked class="rounded text-indigo-600">
                <div>
                    <span class="font-bold text-slate-800 block">Email Dispatch Notification</span>
                    <span class="text-slate-500 text-[11px]">Receive complete PDF diagnostic summary and customer issue attachments via email.</span>
                </div>
            </label>
        </div>

        <button type="button" class="w-full bg-indigo-600 text-white font-semibold py-2 rounded-lg text-xs mt-4">
            Save Notification Preferences
        </button>
    </form>
</div>
@endsection

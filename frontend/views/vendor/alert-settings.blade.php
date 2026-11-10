{{--
=============================================================================
FEATURE R14: Automated Multi-Channel BulkSMS & Email Notification
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
Architectural Layer: View (Vendor/AlertSettings)
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Vendor Alert & SMS Settings | Meramot')

@section('content')
<div class="mx-auto max-w-xl px-4 py-6 md:px-6 space-y-5">
    <div class="rounded-[1.75rem] border border-amber-300 bg-amber-50/90 p-4 text-xs flex items-start gap-3">
        <i class="fa-solid fa-bell text-amber-800 mt-0.5"></i>
        <div>
            <p class="font-black text-amber-950">Multi-Channel BulkSMS BD & Email Alerts — Launching in Sprint 3</p>
            <p class="text-amber-900 mt-0.5">Saving preferences previews the Sprint 3 gateway configuration.</p>
        </div>
    </div>

    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm">
        <div class="mb-6 flex items-center justify-between gap-3">
            <div>
                <h1 class="text-2xl font-black text-[var(--foreground)]">Multi-Channel Alert Engine</h1>
                <p class="text-xs text-[var(--muted-foreground)] mt-1 dev-only-badge">Feature R14 | Assigned to: <strong class="text-[var(--accent-dark)]">Farhan Tanvir [22299179]</strong></p>
            </div>
            <span class="dev-only-badge text-xs bg-[var(--mint-100)] text-[var(--accent-dark)] font-bold px-3 py-1 rounded-full border border-[var(--border)]">
                Sprint 3 Preview
            </span>
        </div>

        <form method="POST" action="/coming-soon/multi-channel-alerts" class="space-y-4 text-xs">
            @csrf
            <div class="space-y-3">
                <label class="flex items-center gap-3 p-4 bg-[var(--mint-100)] rounded-2xl border border-[var(--border)] cursor-pointer">
                    <input type="checkbox" name="sms_alerts" value="1" checked class="rounded text-[var(--accent-dark)]">
                    <div>
                        <span class="font-bold text-[var(--foreground)] block">Instant BulkSMS BD Alerts</span>
                        <span class="text-[var(--muted-foreground)] text-[11px]">Receive instant SMS notification on registered mobile when a device in your category opens for bidding.</span>
                    </div>
                </label>

                <label class="flex items-center gap-3 p-4 bg-[var(--mint-100)] rounded-2xl border border-[var(--border)] cursor-pointer">
                    <input type="checkbox" name="email_alerts" value="1" checked class="rounded text-[var(--accent-dark)]">
                    <div>
                        <span class="font-bold text-[var(--foreground)] block">Email Dispatch Notification</span>
                        <span class="text-[var(--muted-foreground)] text-[11px]">Receive structured diagnostic summary and customer issue attachments via email.</span>
                    </div>
                </label>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button type="submit" class="flex-1 bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] font-bold py-3 rounded-full text-xs transition shadow">
                    Save Notification Preferences (Sprint 3)
                </button>
                <a href="/vendor/dashboard" class="rounded-full border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] px-5 py-3 font-bold text-[var(--foreground)] transition text-center">
                    Back to Dashboard
                </a>
            </div>
        </form>
    </div>
</div>
@endsection

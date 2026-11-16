{{--
=============================================================================
FEATURE R14: Automated Multi-Channel BulkSMS, Email & In-App Alert Settings
=============================================================================
Assigned Member: Farhan Tanvir [Student ID: 22299179] (Team Lead)
Sprint: Sprint 3
Architectural Layer: View (Vendor/AlertSettings)
Controller: VendorNotificationController@settings, updateSettings, sendTestAlert
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Multi-Channel Alert & SMS Settings | Meramot')

@section('content')
@php
    $vp = $vendorProfile ?? null;
    $gw = $gatewayStatus ?? ['simulated' => true, 'balance' => 'Sandbox Mode', 'sender_id' => '8809648908088', 'note' => ''];
@endphp

<div class="mx-auto max-w-4xl px-4 py-6 md:px-6 space-y-6">
    <!-- Top Header -->
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-2xl font-bold text-[var(--foreground)]">Multi-Channel Alert &amp; BulkSMS Engine</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1">Configure real-time BulkSMS BD, Transactional Email, and In-App Bell notifications for workshop leads, bids, and payouts.</p>
        </div>
        <div class="flex items-center gap-2">
            <a href="/notifications" class="rounded-md border border-[var(--border)] bg-[var(--card)] hover:bg-[var(--mint-100)] px-4 py-2 text-xs font-bold text-[var(--foreground)] transition">
                <i class="fa-solid fa-bell mr-1"></i> Notification Center
            </a>
            <a href="/vendor/dashboard" class="rounded-md bg-[var(--accent-dark)] px-4 py-2 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                Workshop Hub &rarr;
            </a>
        </div>
    </div>

    @if(session('success'))
        <div class="rounded-lg border border-emerald-400 bg-emerald-50 p-4 text-xs font-bold text-emerald-950 flex items-center gap-3">
            <i class="fa-solid fa-circle-check text-emerald-700 text-base"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error') || $errors->any())
        <div class="rounded-lg border border-rose-300 bg-rose-50 p-4 text-xs font-bold text-rose-900">
            {{ session('error') ?? $errors->first() }}
        </div>
    @endif

    <!-- BulkSMS BD Gateway Telemetry Banner -->
    <div class="rounded-lg border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm flex flex-wrap items-center justify-between gap-4 text-xs">
        <div class="flex items-start gap-3">
            <div class="h-11 w-11 rounded-lg bg-[var(--accent-dark)] text-[var(--accent-foreground)] flex items-center justify-center shrink-0">
                <i class="fa-solid fa-tower-broadcast text-base"></i>
            </div>
            <div>
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-bold text-sm text-[var(--foreground)]">BulkSMS BD Telecom Gateway</span>
                    <span class="rounded px-2.5 py-0.5 text-[10px] font-semibold {{ !empty($gw['simulated']) ? 'bg-amber-100 text-amber-950 border border-amber-300' : 'bg-emerald-100 text-emerald-950 border border-emerald-300' }}">
                        {{ $gw['balance'] ?? 'Active' }}
                    </span>
                </div>
                <p class="text-[var(--muted-foreground)] mt-0.5">
                    Sender ID: <span class="font-mono font-bold text-[var(--foreground)]">{{ $gw['sender_id'] ?? '8809648908088' }}</span>
                    @if(!empty($gw['note'])) • {{ $gw['note'] }} @endif
                </p>
            </div>
        </div>

        <form method="POST" action="{{ route('vendor.alert-settings.test') }}">
            @csrf
            <button type="submit" class="rounded-md border border-[var(--accent-dark)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] px-4 py-2.5 text-xs font-bold text-[var(--accent-dark)] transition">
                <i class="fa-solid fa-paper-plane mr-1"></i> Send Live Diagnostic Alert
            </button>
        </form>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-5">
        <!-- Preferences Form -->
        <div class="lg:col-span-7 rounded-lg border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm">
            <h2 class="text-base font-bold text-[var(--foreground)] mb-4">Channel &amp; Event Subscriptions</h2>

            <form method="POST" action="{{ route('vendor.alert-settings.update') }}" class="space-y-4 text-xs">
                @csrf

                <div>
                    <label class="block font-bold text-[var(--foreground)] mb-1">Dedicated Workshop SMS Alert Number (Bangladesh)</label>
                    <input type="text" name="alert_phone"
                           value="{{ old('alert_phone', $vp?->alert_phone ?? $vp?->phone ?? auth()->user()?->phone ?? '') }}"
                           placeholder="017XXXXXXXX"
                           class="w-full rounded-xl border border-[var(--border)] bg-white px-4 py-2.5 font-mono font-bold text-[var(--foreground)] focus:outline-none focus:ring-2 focus:ring-[var(--accent-dark)]">
                    <p class="text-[11px] text-[var(--muted-foreground)] mt-1">Normalized automatically to <code class="font-mono">8801XXXXXXXXX</code> for BulkSMS BD dispatch.</p>
                </div>

                <div class="space-y-2.5 pt-1">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Delivery Channels</p>

                    <label class="flex items-start gap-3 p-3.5 bg-[var(--mint-100)] rounded-lg border border-[var(--border)] cursor-pointer">
                        <input type="checkbox" name="notify_sms_enabled" value="1" @checked($vp?->notify_sms_enabled ?? true) class="mt-0.5 rounded text-[var(--accent-dark)]">
                        <div>
                            <span class="font-bold text-[var(--foreground)] block">Instant BulkSMS Mobile Alerts</span>
                            <span class="text-[var(--muted-foreground)] text-[11px]">SMS push when new repair leads match your city or when a customer accepts your bid.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 bg-[var(--mint-100)] rounded-lg border border-[var(--border)] cursor-pointer">
                        <input type="checkbox" name="notify_email_enabled" value="1" @checked($vp?->notify_email_enabled ?? true) class="mt-0.5 rounded text-[var(--accent-dark)]">
                        <div>
                            <span class="font-bold text-[var(--foreground)] block">Transactional Email Summaries</span>
                            <span class="text-[var(--muted-foreground)] text-[11px]">Structured HTML digests with AI fault triage, Quote 2 approvals, and payout receipts.</span>
                        </div>
                    </label>

                    <label class="flex items-start gap-3 p-3.5 bg-[var(--mint-100)] rounded-lg border border-[var(--border)] cursor-pointer">
                        <input type="checkbox" name="notify_in_app_enabled" value="1" @checked($vp?->notify_in_app_enabled ?? true) class="mt-0.5 rounded text-[var(--accent-dark)]">
                        <div>
                            <span class="font-bold text-[var(--foreground)] block">In-App Real-Time Bell Alerts</span>
                            <span class="text-[var(--muted-foreground)] text-[11px]">Instant unread badge alerts inside the Meramot top navigation bar.</span>
                        </div>
                    </label>
                </div>

                <div class="space-y-2.5 pt-2">
                    <p class="text-[10px] font-bold uppercase tracking-wider text-[var(--muted-foreground)]">Trigger Events</p>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5">
                        <label class="flex items-center gap-2 rounded-xl border border-[var(--border)] bg-white/80 p-3 cursor-pointer font-bold">
                            <input type="checkbox" name="notify_new_requests" value="1" @checked($vp?->notify_new_requests ?? true)>
                            <span>New Repair Leads</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-[var(--border)] bg-white/80 p-3 cursor-pointer font-bold">
                            <input type="checkbox" name="notify_bid_updates" value="1" @checked($vp?->notify_bid_updates ?? true)>
                            <span>Quote Approvals</span>
                        </label>
                        <label class="flex items-center gap-2 rounded-xl border border-[var(--border)] bg-white/80 p-3 cursor-pointer font-bold">
                            <input type="checkbox" name="notify_payout_updates" value="1" @checked($vp?->notify_payout_updates ?? true)>
                            <span>Payout Releases</span>
                        </label>
                    </div>
                </div>

                <div class="pt-3">
                    <button type="submit" class="w-full bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] font-semibold py-3.5 rounded-full text-xs transition shadow">
                        Save Multi-Channel Alert Preferences
                    </button>
                </div>
            </form>
        </div>

        <!-- Recent Dispatch Audit Trail -->
        <div class="lg:col-span-5 rounded-lg border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm flex flex-col">
            <div class="flex items-center justify-between mb-4">
                <h2 class="text-base font-bold text-[var(--foreground)]">Recent Dispatch Audit Log</h2>
                <span class="text-[10px] font-semibold uppercase rounded bg-[var(--mint-100)] border border-[var(--border)] px-2.5 py-0.5 text-[var(--accent-dark)]">
                    {{ isset($recentLogs) ? $recentLogs->count() : 0 }} Logged
                </span>
            </div>

            <div class="space-y-2.5 max-h-[460px] overflow-y-auto pr-1 text-xs">
                @forelse($recentLogs ?? [] as $log)
                    <div class="rounded-lg border border-[var(--border)] bg-[var(--mint-100)]/75 p-3.5 space-y-1">
                        <div class="flex items-center justify-between gap-2">
                            <span class="rounded bg-[var(--accent-dark)] text-[var(--accent-foreground)] px-2.5 py-0.5 text-[10px] font-bold">
                                {{ $log->channel }}
                            </span>
                            <span class="text-[10px] font-bold text-emerald-800">{{ $log->status }} • {{ optional($log->created_at)->diffForHumans() }}</span>
                        </div>
                        <p class="font-bold text-[var(--foreground)] truncate">{{ $log->subject ?: 'Marketplace Dispatch' }}</p>
                        <p class="text-[11px] text-[var(--muted-foreground)] line-clamp-2">{{ $log->body }}</p>
                        <p class="text-[10px] font-mono text-[var(--muted-foreground)] truncate">
                            To: {{ $log->recipient }} • {{ $log->provider ?? 'Gateway' }} ({{ $log->provider_ref ?? 'N/A' }})
                        </p>
                    </div>
                @empty
                    <div class="rounded-lg border border-dashed border-[var(--border)] bg-white/60 p-6 text-center text-[var(--muted-foreground)]">
                        Click <strong>Send Live Diagnostic Alert</strong> above to verify SMS, Email, and In-App dispatch logging!
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

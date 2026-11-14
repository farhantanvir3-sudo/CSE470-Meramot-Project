{{--
=============================================================================
FEATURE R19: Unified Customer & Partner Support & Escrow Dispute Desk
=============================================================================
Assigned Member: Md. Nawshad Zaman Khan [Student ID: 23301622]
Sprint: Sprint 3
Controller: AdminModerationController@supportDesk, storeDispute, storeSupportTicket
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Help, Support & Escrow Dispute Desk | Meramot')

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 md:px-6 space-y-6">
    <!-- Hero Banner -->
    <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 md:p-8 shadow-sm flex flex-wrap items-center justify-between gap-4">
        <div class="max-w-2xl">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-[10px] font-black uppercase tracking-wider text-[var(--accent-dark)]">
                <i class="fa-solid fa-headset"></i> Meramot Trust &amp; Resolution Center
            </span>
            <h1 class="mt-2 text-2xl md:text-3xl font-black text-[var(--foreground)]">Support &amp; Escrow Dispute Desk</h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 leading-relaxed">
                Protected by Meramot Escrow Custody: file an order arbitration claim if a repair differs from the approved quote, or open a support ticket with our engineering &amp; operations team.
            </p>
        </div>
        @if(auth()->user()?->role === 'admin')
            <a href="/admin/moderation" class="rounded-full bg-[var(--accent-dark)] px-5 py-2.5 text-xs font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                Open Admin Governance Panel &rarr;
            </a>
        @endif
    </div>

    @if(session('success'))
        <div class="rounded-2xl border border-emerald-400 bg-emerald-50 p-4 text-xs font-bold text-emerald-950 flex items-center gap-3">
            <i class="fa-solid fa-circle-check text-emerald-700 text-base"></i>
            <span>{{ session('success') }}</span>
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-2xl border border-rose-300 bg-rose-50 p-4 text-xs font-bold text-rose-900">
            {{ $errors->first() }}
        </div>
    @endif

    @guest
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--mint-100)] p-6 text-center space-y-3 text-xs">
            <p class="text-sm font-black text-[var(--foreground)]">Sign in to file an Escrow Dispute or track your Support Tickets</p>
            <p class="text-[var(--muted-foreground)]">Emergency hotline: <strong>+880 9648-908088</strong> • Email: <strong>support@meramot.bd</strong></p>
            <div class="pt-1">
                <a href="/login" class="inline-flex items-center gap-2 rounded-full bg-[var(--accent-dark)] px-6 py-2.5 font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                    Sign In to Continue &rarr;
                </a>
            </div>
        </div>
    @else
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 text-xs">
            <!-- Left: File Order Dispute -->
            <div class="lg:col-span-6 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-black text-[var(--foreground)]"><i class="fa-solid fa-scale-balanced mr-1.5 text-amber-700"></i> Open Order / Escrow Dispute</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">Freezes workshop payout release while Meramot Governance reviews bench diagnostics and handover evidence.</p>
                </div>

                @if(($myOrders ?? collect())->isNotEmpty())
                    <form method="POST" action="{{ route('disputes.store') }}" class="space-y-3">
                        @csrf
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Select Repair Order</label>
                            <select name="repair_request_id" required class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-bold text-[var(--foreground)]">
                                @foreach($myOrders as $ord)
                                    <option value="{{ $ord->id }}" @selected((string) ($preselectedOrderId ?? '') === (string) $ord->id)>
                                        Order #{{ $ord->id }} — {{ $ord->title }} ({{ $ord->status }})
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Dispute Evidence &amp; Claim Details</label>
                            <textarea name="reason" rows="3" required minlength="10"
                                      placeholder="Describe the issue with parts quality, warranty terms, or courier handover..."
                                      class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 text-[var(--foreground)]"></textarea>
                        </div>
                        <button type="submit" class="w-full rounded-full bg-amber-700 hover:bg-amber-800 text-white font-black py-3 px-5 transition shadow">
                            Submit Escrow Arbitration Claim
                        </button>
                    </form>
                @else
                    <div class="rounded-2xl border border-dashed border-[var(--border)] bg-[var(--mint-100)]/60 p-6 text-center text-[var(--muted-foreground)]">
                        You have no repair orders to attach to an escrow dispute yet. Use the General Support form on the right for account or platform inquiries.
                    </div>
                @endif
            </div>

            <!-- Right: General Support Ticket -->
            <div class="lg:col-span-6 rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-4">
                <div>
                    <h2 class="text-base font-black text-[var(--foreground)]"><i class="fa-solid fa-life-ring mr-1.5 text-[var(--accent-dark)]"></i> Open Help &amp; Support Ticket</h2>
                    <p class="text-[11px] text-[var(--muted-foreground)] mt-0.5">Ask about billing, courier scheduling, workshop onboarding, or technical assistance.</p>
                </div>

                <form method="POST" action="{{ route('support.tickets.store') }}" class="space-y-3">
                    @csrf
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="sm:col-span-2">
                            <label class="block font-bold text-[var(--foreground)] mb-1">Subject</label>
                            <input type="text" name="subject" required placeholder="e.g., Courier reschedule or invoice question"
                                   class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 font-semibold text-[var(--foreground)]">
                        </div>
                        <div>
                            <label class="block font-bold text-[var(--foreground)] mb-1">Priority</label>
                            <select name="priority" class="w-full rounded-xl border border-[var(--border)] bg-white px-3 py-2.5 font-bold text-[var(--foreground)]">
                                <option value="normal">Normal</option>
                                <option value="high">High</option>
                                <option value="urgent">Urgent</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block font-bold text-[var(--foreground)] mb-1">Message</label>
                        <textarea name="message" rows="3" required minlength="8"
                                  placeholder="How can our support engineers help you today?"
                                  class="w-full rounded-xl border border-[var(--border)] bg-white px-3.5 py-2.5 text-[var(--foreground)]"></textarea>
                    </div>
                    <button type="submit" class="w-full rounded-full bg-[var(--accent-dark)] hover:opacity-90 text-[var(--accent-foreground)] font-black py-3 px-5 transition shadow">
                        Create Support Ticket
                    </button>
                </form>
            </div>
        </div>

        <!-- Active Disputes & Support Threads -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 text-xs">
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-3">
                <h3 class="text-base font-black text-[var(--foreground)]">My Escrow Dispute Cases ({{ ($myDisputes ?? collect())->count() }})</h3>
                @forelse($myDisputes ?? [] as $dc)
                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-4 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-black text-[var(--foreground)]">Dispute #{{ $dc->id }} • Order #{{ $dc->repair_request_id }}</span>
                            <span class="rounded-full bg-white border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-black">{{ $dc->status }}</span>
                        </div>
                        @foreach($dc->notes as $n)
                            <p class="text-[11px] text-[var(--muted-foreground)]"><strong>{{ $n->author?->name ?? 'Participant' }}:</strong> {{ $n->note }}</p>
                        @endforeach
                        @if(in_array($dc->status, ['OPEN', 'INVESTIGATING', 'WAITING_EVIDENCE', 'WAITING_RESPONSE'], true))
                            <form method="POST" action="{{ route('disputes.notes.store', $dc->id) }}" class="flex gap-2 pt-1">
                                @csrf
                                <input type="text" name="note" required placeholder="Add follow-up note..."
                                       class="flex-1 rounded-xl border border-[var(--border)] bg-white px-3 py-1.5">
                                <button type="submit" class="rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] px-3.5 py-1.5 font-bold">Post</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-[var(--muted-foreground)]">No dispute cases on file.</p>
                @endforelse
            </div>

            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-6 shadow-sm space-y-3">
                <h3 class="text-base font-black text-[var(--foreground)]">My Support Tickets ({{ ($myTickets ?? collect())->count() }})</h3>
                @forelse($myTickets ?? [] as $tk)
                    <div class="rounded-2xl border border-[var(--border)] bg-[var(--mint-100)]/60 p-4 space-y-2">
                        <div class="flex items-center justify-between gap-2">
                            <span class="font-black text-[var(--foreground)]">#{{ $tk->id }} — {{ $tk->subject }}</span>
                            <span class="rounded-full bg-white border border-[var(--border)] px-2.5 py-0.5 text-[10px] font-black">{{ $tk->status }}</span>
                        </div>
                        @foreach($tk->messages as $msg)
                            <p class="text-[11px] {{ $msg->sender_type === 'admin' ? 'text-emerald-900 font-semibold' : 'text-[var(--muted-foreground)]' }}">
                                <strong>{{ $msg->sender_type === 'admin' ? 'Meramot Support' : ($msg->author?->name ?? 'You') }}:</strong> {{ $msg->message }}
                            </p>
                        @endforeach
                        @if($tk->status !== 'CLOSED')
                            <form method="POST" action="{{ route('support.tickets.reply', $tk->id) }}" class="flex gap-2 pt-1">
                                @csrf
                                <input type="text" name="message" required placeholder="Write a reply..."
                                       class="flex-1 rounded-xl border border-[var(--border)] bg-white px-3 py-1.5">
                                <button type="submit" class="rounded-full bg-[var(--accent-dark)] text-[var(--accent-foreground)] px-3.5 py-1.5 font-bold">Reply</button>
                            </form>
                        @endif
                    </div>
                @empty
                    <p class="text-[var(--muted-foreground)]">No support tickets opened yet.</p>
                @endforelse
            </div>
        </div>
    @endguest
</div>
@endsection

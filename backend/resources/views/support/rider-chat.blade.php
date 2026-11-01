{{--
=============================================================================
FEATURE R5: Real-Time Admin-to-Rider WebSocket Support Chat
=============================================================================
Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
Architectural Layer: View (Support/RiderChat)
Controller: RiderChatController@index, sendMessage
Models Touched: RiderChatMessage

INSTRUCTIONS FOR NAFIUL:
- Display live chat message stream between Rider and Admin Operations Desk.
- Use Pusher JS to append new messages without browser page refresh.
- Provide input form with attachment support.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'Admin-to-Rider Support Chat | Feature R5')

@section('content')
<div class="mb-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-slate-900">Rider Support Live Chat</h1>
            <p class="text-xs text-slate-500 mt-1">Feature R5 | Assigned to: <strong class="text-teal-700">Md. Nafiul Islam [22299035]</strong></p>
        </div>
        <span class="text-xs bg-rose-100 text-rose-800 font-semibold px-3 py-1 rounded-full border border-rose-200">
            Sprint 4 Target
        </span>
    </div>
</div>

<div class="max-w-2xl mx-auto bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col h-[500px]">
    <div class="p-4 border-b border-slate-200 font-semibold text-sm text-slate-800">
        Active Chat Channel (Rider ID: #{{ $riderId ?? 1 }})
    </div>
    
    <div class="flex-grow p-4 overflow-y-auto space-y-3" id="chat-messages">
        @forelse($messages ?? [] as $msg)
            <div class="flex {{ $msg->sender_role === 'admin' ? 'justify-start' : 'justify-end' }}">
                <div class="max-w-xs rounded-xl px-4 py-2 text-xs {{ $msg->sender_role === 'admin' ? 'bg-slate-100 text-slate-800' : 'bg-teal-600 text-white' }}">
                    <p>{{ $msg->message_text }}</p>
                    <span class="text-[10px] opacity-75 block text-right mt-1">{{ $msg->created_at->format('H:i') }}</span>
                </div>
            </div>
        @empty
            <p class="text-xs text-slate-400 text-center py-10">No chat messages yet. Send a message to initiate support.</p>
        @endforelse
    </div>

    <form method="POST" action="/api/support/rider-chat/send" class="p-4 border-t border-slate-200 flex gap-2">
        @csrf
        <input type="hidden" name="delivery_agent_id" value="{{ $riderId ?? 1 }}">
        <input type="hidden" name="sender_role" value="rider">
        <input type="text" name="message_text" placeholder="Type support message..." class="flex-grow rounded-lg border-slate-300 text-xs px-3 py-2">
        <button type="submit" class="bg-teal-600 hover:bg-teal-700 text-white px-4 py-2 rounded-lg text-xs font-semibold">Send</button>
    </form>
</div>
@endsection

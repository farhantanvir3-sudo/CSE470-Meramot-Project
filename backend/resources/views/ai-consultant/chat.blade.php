{{--
=============================================================================
FEATURE R10: AI-Powered Repair Diagnostic Conversational Consultant
=============================================================================
Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
Architectural Layer: View (AIConsultant/Chat)
Controller: AIChatController@index, sendMessage
Models Touched: AIChatSession, Message

INSTRUCTIONS FOR SHANID:
- Interactive conversational AI assistant powered by the Python FastAPI microservice.
- Customer types device symptom and receives instant diagnostic guidance.
=============================================================================
--}}
@extends('layouts.app')

@section('title', 'AI Diagnostic Consultant | Feature R10')

@section('content')
<div class="mb-6 flex justify-between items-center">
    <div>
        <h1 class="text-2xl font-bold text-slate-900">AI Diagnostic Hardware Consultant</h1>
        <p class="text-xs text-slate-500 mt-1">Feature R10 | Assigned to: <strong class="text-teal-700">Shanid Sajjatuz Islam [23201465]</strong></p>
    </div>
    <span class="text-xs bg-purple-100 text-purple-800 font-semibold px-3 py-1 rounded-full border border-purple-200">
        Sprint 4 Target (FastAPI Backed)
    </span>
</div>

<div class="max-w-2xl mx-auto bg-white rounded-xl border border-slate-200 shadow-sm flex flex-col h-[520px]" x-data="{
    messages: [
        { sender: 'ai', text: 'Hello! I am your Meramot AI Diagnostic Consultant. What device issue are you experiencing today?' }
    ],
    userInput: '',
    category: 'Laptop',
    loading: false,
    async sendMsg() {
        if (!this.userInput.trim()) return;
        const text = this.userInput;
        this.messages.push({ sender: 'user', text });
        this.userInput = '';
        this.loading = true;

        try {
            const res = await fetch('/api/ai-consultant/message', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: JSON.stringify({ device_category: this.category, user_message: text })
            });
            const data = await res.json();
            this.messages.push({ sender: 'ai', text: data.reply });
        } catch(e) {
            this.messages.push({ sender: 'ai', text: 'Error contacting AI microservice.' });
        } finally {
            this.loading = false;
        }
    }
}">
    <!-- Device selector header -->
    <div class="p-4 border-b border-slate-200 flex justify-between items-center bg-slate-50 rounded-t-xl text-xs">
        <span class="font-semibold text-slate-700">Target Device Category:</span>
        <select x-model="category" class="rounded border-slate-300 text-xs py-1">
            <option value="Laptop">Laptop</option>
            <option value="Mobile Phone">Mobile Phone</option>
            <option value="Tablet">Tablet</option>
            <option value="Console">Console</option>
        </select>
    </div>

    <!-- Messages Container -->
    <div class="flex-grow p-4 overflow-y-auto space-y-3">
        <template x-for="(m, idx) in messages" :key="idx">
            <div :class="m.sender === 'ai' ? 'flex justify-start' : 'flex justify-end'">
                <div :class="m.sender === 'ai' ? 'bg-purple-50 text-purple-950 border border-purple-200' : 'bg-teal-600 text-white'" class="max-w-md rounded-xl px-4 py-2 text-xs">
                    <p x-text="m.text"></p>
                </div>
            </div>
        </template>
        <div x-show="loading" class="text-xs text-purple-600 italic">AI Consultant is diagnosing...</div>
    </div>

    <!-- Input Form -->
    <form @submit.prevent="sendMsg" class="p-4 border-t border-slate-200 flex gap-2">
        <input type="text" x-model="userInput" placeholder="Describe fault symptoms (e.g. laptop shuts down under load)..." class="flex-grow rounded-lg border-slate-300 text-xs px-3 py-2">
        <button type="submit" class="bg-purple-700 hover:bg-purple-800 text-white px-5 py-2 rounded-lg text-xs font-semibold">Ask AI</button>
    </form>
</div>
@endsection

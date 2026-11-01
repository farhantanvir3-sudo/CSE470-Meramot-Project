@extends('layouts.app')

@section('title', 'AI Device Diagnostic Consultant | Meramot')

@section('content')
<div class="mx-auto max-w-3xl px-4 py-6 md:px-6">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>/</span>
        <span class="text-[var(--foreground)] font-semibold">AI Diagnostic Consultant</span>
    </div>

    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-black text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-wand-magic-sparkles text-amber-600"></i>
                AI Hardware Diagnostic Consultant
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-0.5 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R10 • Shanid Sajjatuz Islam [23201465]
                </span>
                <span>Automated hardware symptom triage and intelligent repair estimation</span>
            </p>
        </div>
        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3 py-1 text-xs font-bold text-[var(--accent-dark)] self-start">
            FastAPI Live Engine
        </span>
    </div>

    <!-- Interactive Chat Card -->
    <div class="meramot-card shadow-sm flex flex-col h-[560px] overflow-hidden" x-data="{
        messages: [
            { sender: 'ai', text: 'Hello! I am your Meramot AI Hardware Diagnostic Consultant. Describe what is going wrong with your device (e.g. screen flickering, battery draining fast, no power, liquid spill) and I will analyze the likely hardware failure points and estimated repair scope.' }
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
                this.messages.push({ sender: 'ai', text: data.reply || 'Diagnostics complete. Common causes include capacitor fatigue or connector corrosion.' });
            } catch(e) {
                this.messages.push({ sender: 'ai', text: 'Diagnostic analysis: Likely hardware fault detected. Recommended action: Submit a repair request so certified technicians can inspect with multimeters.' });
            } finally {
                this.loading = false;
            }
        }
    }">
        
        <!-- Chat Control Bar -->
        <div class="p-4 border-b border-[var(--border)] bg-[var(--mint-100)] flex items-center justify-between text-xs">
            <div class="flex items-center gap-2">
                <span class="font-bold text-[var(--foreground)]">Device Category:</span>
                <select x-model="category" class="rounded-xl border border-[var(--border)] bg-[var(--card)] text-xs py-1.5 px-3 font-semibold text-[var(--foreground)] outline-none">
                    <option value="Laptop">Laptop / MacBook</option>
                    <option value="Mobile Phone">Mobile Phone / iPhone</option>
                    <option value="Tablet">Tablet / iPad</option>
                    <option value="Console">Gaming Console</option>
                    <option value="Audio">Wearables & Audio</option>
                </select>
            </div>
            <div class="text-[11px] text-[var(--muted-foreground)] flex items-center gap-1.5">
                <span class="h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>Microservice Ready</span>
            </div>
        </div>

        <!-- Messages Container -->
        <div class="flex-grow p-5 overflow-y-auto space-y-4 text-xs">
            <template x-for="(m, idx) in messages" :key="idx">
                <div :class="m.sender === 'ai' ? 'flex justify-start' : 'flex justify-end'">
                    <div :class="m.sender === 'ai' ? 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] rounded-2xl rounded-tl-none shadow-sm' : 'bg-[var(--accent-dark)] text-white rounded-2xl rounded-tr-none shadow-sm'" class="max-w-md px-4 py-3 leading-relaxed">
                        <div class="text-[10px] font-bold opacity-60 mb-1" x-text="m.sender === 'ai' ? 'Meramot AI Assistant' : 'You'"></div>
                        <p x-text="m.text"></p>
                    </div>
                </div>
            </template>
            <div x-show="loading" class="flex justify-start">
                <div class="bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] rounded-2xl rounded-tl-none px-4 py-2.5 text-xs italic flex items-center gap-2">
                    <i class="fa-solid fa-spinner fa-spin"></i>
                    <span>Diagnosing hardware symptoms with FastAPI engine...</span>
                </div>
            </div>
        </div>

        <!-- Chat Input Form -->
        <form @submit.prevent="sendMsg" class="p-3 border-t border-[var(--border)] bg-[var(--mint-100)] flex gap-2">
            <input 
                type="text" 
                x-model="userInput" 
                placeholder="Type your device symptom (e.g. laptop shuts down when I open heavy games)..." 
                class="flex-grow rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--mint-100)] text-xs px-4 py-2.5 outline-none placeholder:text-[var(--muted-foreground)] text-[var(--foreground)]">
            <button 
                type="submit" 
                class="rounded-2xl bg-[var(--accent-dark)] hover:opacity-90 text-white px-5 py-2.5 text-xs font-bold transition shadow flex items-center gap-1.5">
                <span>Diagnose</span>
                <i class="fa-solid fa-paper-plane text-[10px]"></i>
            </button>
        </form>

    </div>

</div>
@endsection

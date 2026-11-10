@extends('layouts.app')

@section('title', 'AI Device Diagnostic Consultant | Meramot')

{{-- ==========================================================================
     FEATURE R10: AI-Powered Repair Diagnostic Conversational Consultant
     Assigned Member: Shanid Sajjatuz Islam [23201465]
     Best-of-Both-Worlds Merge: Farhan Tanvir [22299179]
       - Commit 8e44218 Mint-Green & Dark-Mode Alpine.js Reactive Chat
       - Merged with Shanid's Quick Symptom Prompts & Diagnostic Tips Sidebar
     ========================================================================== --}}

@section('content')
<div class="mx-auto max-w-6xl px-4 py-6 md:px-6" x-data="{
    messages: [
        {
            sender: 'ai',
            text: 'Hello! I am your Meramot AI Hardware Diagnostic Consultant. Select your device category and describe what is going wrong (e.g. screen flickering, battery draining fast, liquid spill, overheating) — I will analyze likely component faults and repair options.'
        }
    ],
    userInput: '',
    category: 'Laptop',
    loading: false,
    async sendMsg() {
        const text = this.userInput.trim();
        if (!text || this.loading) return;

        this.messages.push({ sender: 'user', text });
        this.userInput = '';
        this.loading = true;
        this.$nextTick(() => {
            const box = document.getElementById('ai-chat-scroll');
            if (box) box.scrollTop = box.scrollHeight;
        });

        try {
            const csrf = document.querySelector('meta[name=csrf-token]')?.getAttribute('content') || '';
            const res = await fetch('/api/ai-consultant/message', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': csrf
                },
                body: JSON.stringify({
                    device_category: this.category,
                    user_message: text
                })
            });
            const data = await res.json().catch(() => ({}));
            if (res.ok && data.status === 'success' && data.reply) {
                this.messages.push({
                    sender: 'ai',
                    text: data.reply
                });
            } else {
                const lower = text.toLowerCase();
                let hint = `Preliminary ${this.category} triage: Symptom profile indicates a component-level power, display, or thermal issue.`;
                if (lower.includes('screen') || lower.includes('display') || lower.includes('touch') || lower.includes('flicker')) {
                    hint = `Preliminary ${this.category} Display Triage:\n• Probable Cause: Cracked OLED/LCD digitizer assembly or loose LVDS/eDP display flex cable.\n• Recommended Action: Book a Screen Replacement Package or post a Marketplace Request for OEM panel quotes.`;
                } else if (lower.includes('battery') || lower.includes('drain') || lower.includes('charg')) {
                    hint = `Preliminary ${this.category} Power & Battery Triage:\n• Probable Cause: Degraded lithium cell cycle capacity or USB-C/charging IC regulation fault.\n• Recommended Action: Check charging port debris first, or book a Certified Battery Replacement.`;
                } else if (lower.includes('heat') || lower.includes('shut') || lower.includes('fan')) {
                    hint = `Preliminary ${this.category} Thermal Triage:\n• Probable Cause: Dried thermal compound, clogged heatsink fins, or VRM MOSFET thermal cutoff.\n• Recommended Action: Stop heavy loads immediately and schedule an Ultrasonic Cooling & Thermal Repaste service.`;
                } else if (lower.includes('water') || lower.includes('liquid') || lower.includes('spill') || lower.includes('key')) {
                    hint = `Preliminary ${this.category} Liquid Exposure Triage:\n• Probable Cause: Corrosion risk across keyboard membrane and motherboard power rails.\n• Recommended Action: Keep the device powered OFF (do not charge!) and request urgent Ultrasonic Board Cleaning.`;
                }
                this.messages.push({
                    sender: 'ai',
                    text: hint + `\n\n(Note: Live FastAPI LLM neural inference launches in Sprint 4 — you can post a live Repair Request right now using the button above!)`
                });
            }
        } catch (e) {
            this.messages.push({
                sender: 'ai',
                text: `Preliminary ${this.category} hardware triage: Symptom pattern suggests power-rail, display flex, or thermal degradation. Post a Marketplace Repair Request to receive itemized Quote 1 bids from verified workshops. (Live FastAPI LLM microservice scheduled for Sprint 4.)`
            });
        } finally {
            this.loading = false;
            this.$nextTick(() => {
                const box = document.getElementById('ai-chat-scroll');
                if (box) box.scrollTop = box.scrollHeight;
            });
        }
    },
    quickPrompt(device, symptom) {
        this.category = device;
        this.userInput = symptom;
        this.sendMsg();
    }
}">

    <!-- Breadcrumb -->
    <div class="mb-4 flex items-center gap-2 text-xs text-[var(--muted-foreground)]">
        <a href="/" class="hover:underline">Home</a>
        <span>›</span>
        <span class="text-[var(--foreground)] font-semibold">AI Diagnostic Consultant</span>
    </div>

    <!-- Header -->
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl md:text-3xl font-black text-[var(--foreground)] flex items-center gap-2">
                <i class="fa-solid fa-wand-magic-sparkles text-amber-600"></i>
                <span>AI Hardware Diagnostic Consultant</span>
            </h1>
            <p class="text-xs text-[var(--muted-foreground)] mt-1 flex flex-wrap items-center gap-2">
                <span class="dev-only-badge px-2 py-0.5 rounded bg-emerald-100 text-emerald-900 font-mono text-[10px] border border-emerald-300">
                    Feature R10 • Shanid Sajjatuz Islam [23201465]
                </span>
                <span>Automated hardware symptom triage powered by the Meramot FastAPI microservice</span>
            </p>
        </div>
        <span class="rounded-full bg-[var(--mint-100)] border border-[var(--border)] px-3.5 py-1.5 text-xs font-bold text-[var(--accent-dark)] self-start flex items-center gap-2">
            <span class="h-2 w-2 rounded-full bg-emerald-600 animate-pulse"></span>
            <span>FastAPI Triage Engine</span>
        </span>
    </div>

    <div class="grid gap-6 lg:grid-cols-[minmax(0,1fr)_320px] items-start">

        <!-- Left: Interactive Mint Chat Card -->
        <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] shadow-sm flex flex-col h-[560px] overflow-hidden">

            <!-- Chat Control Bar -->
            <div class="p-4 border-b border-[var(--border)] bg-[var(--mint-100)] flex flex-wrap items-center justify-between gap-3 text-xs">
                <div class="flex items-center gap-2">
                    <span class="font-bold text-[var(--foreground)]">Device Category:</span>
                    <select x-model="category" class="rounded-xl border border-[var(--border)] bg-[var(--card)] text-xs py-1.5 px-3 font-semibold text-[var(--foreground)] outline-none">
                        <option value="Laptop">Laptop / MacBook</option>
                        <option value="Phone">Mobile Phone / iPhone</option>
                        <option value="Tablet">Tablet / iPad</option>
                        <option value="Console">Gaming Console</option>
                        <option value="Smartwatch">Smartwatch & Audio</option>
                    </select>
                </div>
                <a href="/requests/create" class="rounded-full bg-[var(--accent-dark)] px-3.5 py-1.5 text-[11px] font-bold text-[var(--accent-foreground)] hover:opacity-90 transition">
                    Post Repair Request &rarr;
                </a>
            </div>

            <!-- Messages Stream -->
            <div id="ai-chat-scroll" class="flex-grow p-5 overflow-y-auto space-y-4 text-xs">
                <template x-for="(m, idx) in messages" :key="idx">
                    <div :class="m.sender === 'ai' ? 'flex justify-start' : 'flex justify-end'">
                        <div
                            :class="m.sender === 'ai'
                                ? 'bg-[var(--mint-100)] text-[var(--foreground)] border border-[var(--border)] rounded-2xl rounded-tl-none shadow-sm'
                                : 'bg-[var(--accent-dark)] text-[var(--accent-foreground)] rounded-2xl rounded-tr-none shadow-sm'"
                            class="max-w-lg px-4 py-3 leading-relaxed"
                        >
                            <div class="text-[10px] font-bold opacity-70 mb-1" x-text="m.sender === 'ai' ? 'Meramot AI Consultant' : 'You'"></div>
                            <p x-text="m.text" class="whitespace-pre-line"></p>
                        </div>
                    </div>
                </template>

                <div x-show="loading" x-cloak class="flex justify-start">
                    <div class="bg-[var(--mint-100)] text-[var(--accent-dark)] border border-[var(--border)] rounded-2xl rounded-tl-none px-4 py-2.5 text-xs italic flex items-center gap-2">
                        <i class="fa-solid fa-spinner fa-spin"></i>
                        <span>Analyzing hardware symptom tree via FastAPI...</span>
                    </div>
                </div>
            </div>

            <!-- Chat Input Bar -->
            <form @submit.prevent="sendMsg()" class="p-3.5 border-t border-[var(--border)] bg-[var(--mint-100)] flex gap-2">
                <input
                    type="text"
                    x-model="userInput"
                    placeholder="Describe your device fault (e.g. MacBook powers on with chime, screen stays black)..."
                    class="flex-grow rounded-2xl border-2 border-[var(--border)] bg-[var(--mint-300)] focus:bg-[var(--card)] text-xs px-4 py-2.5 outline-none placeholder:text-[var(--muted-foreground)] text-[var(--foreground)] transition"
                >
                <button
                    type="submit"
                    :disabled="loading"
                    class="rounded-2xl bg-[var(--accent-dark)] hover:opacity-90 disabled:opacity-60 text-[var(--accent-foreground)] px-5 py-2.5 text-xs font-bold transition shadow flex items-center gap-1.5"
                >
                    <span x-text="loading ? 'Thinking...' : 'Diagnose'">Diagnose</span>
                    <i class="fa-solid fa-paper-plane text-[10px]"></i>
                </button>
            </form>
        </div>

        <!-- Right Sidebar: Shanid's Quick Examples & Tips (Restyled in Mint Tokens) -->
        <div class="space-y-5">
            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--card)] p-5 shadow-sm">
                <h3 class="text-sm font-black text-[var(--foreground)] mb-3 flex items-center gap-2">
                    <i class="fa-solid fa-bolt text-amber-600"></i>
                    <span>Quick Symptom Prompts</span>
                </h3>
                <div class="space-y-2 text-xs">
                    <button type="button" @click="quickPrompt('Phone', 'My iPhone screen is cracked and touch is unresponsive in the top corner')" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] p-3 text-left font-medium text-[var(--foreground)] transition">
                        📱 "My iPhone screen is cracked & unresponsive"
                    </button>
                    <button type="button" @click="quickPrompt('Laptop', 'Laptop overheating above 95°C and shutting down during heavy workloads')" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] p-3 text-left font-medium text-[var(--foreground)] transition">
                        💻 "Laptop overheating and shutting down"
                    </button>
                    <button type="button" @click="quickPrompt('Phone', 'Smartphone battery drains from 100% to 20% in two hours and feels warm')" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] p-3 text-left font-medium text-[var(--foreground)] transition">
                        🔋 "Phone battery drains fast & warms up"
                    </button>
                    <button type="button" @click="quickPrompt('Laptop', 'Spilled water near keyboard, several keys stopped registering')" class="w-full rounded-2xl border border-[var(--border)] bg-[var(--mint-100)] hover:bg-[var(--mint-200)] p-3 text-left font-medium text-[var(--foreground)] transition">
                        ⌨️ "Liquid spill — keyboard keys not working"
                    </button>
                </div>
            </div>

            <div class="rounded-[2rem] border border-[var(--border)] bg-[var(--mint-100)] p-5 shadow-sm text-xs">
                <h4 class="font-black text-[var(--foreground)] mb-2 flex items-center gap-1.5">
                    <i class="fa-solid fa-lightbulb text-[var(--accent-dark)]"></i>
                    <span>Diagnostic Tips</span>
                </h4>
                <ul class="space-y-1.5 text-[var(--muted-foreground)] leading-relaxed">
                    <li>• Mention exact brand & model (e.g., ThinkPad T14, Galaxy S23)</li>
                    <li>• Note any drops, water exposure, or charging sparks</li>
                    <li>• Ready for repair? Compare bids via <a href="/requests/create" class="font-bold text-[var(--accent-dark)] underline">Marketplace</a> or book a <a href="/packages/catalog" class="font-bold text-[var(--accent-dark)] underline">Fixed Package</a>.</li>
                </ul>
            </div>
        </div>

    </div>
</div>
@endsection

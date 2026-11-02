@extends('layouts.app')

@section('title', 'AI Repair Consultant - Meramot')

@section('content')
<div class="mx-auto max-w-4xl px-4 py-6 md:px-6 md:py-8">
    <div class="mb-6">
        <h1 class="text-3xl font-bold text-gray-900">AI Repair Consultant</h1>
        <p class="mt-2 text-gray-600">Get instant diagnostic help and repair cost estimates powered by AI</p>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <!-- Chat Interface -->
        <div class="lg:col-span-2">
            <div class="flex h-[600px] flex-col rounded-lg border border-gray-200 bg-white shadow-sm">
                <!-- Chat Messages -->
                <div id="chat-messages" class="flex-1 space-y-4 overflow-y-auto p-6">
                    <!-- Welcome Message -->
                    <div class="flex gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white">
                            🤖
                        </div>
                        <div class="flex-1">
                            <div class="rounded-lg bg-blue-50 p-4">
                                <p class="text-sm text-gray-900">Hello! I'm your AI repair consultant. Tell me about your device issue and I'll help diagnose the problem and estimate repair costs.</p>
                            </div>
                            <p class="mt-1 text-xs text-gray-500">Just now</p>
                        </div>
                    </div>
                </div>

                <!-- Input Area -->
                <div class="border-t border-gray-200 p-4">
                    <form id="chat-form" class="flex gap-3">
                        <select id="device-category" required class="rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="">Device Type</option>
                            <option value="Phone">Phone</option>
                            <option value="Laptop">Laptop</option>
                            <option value="Tablet">Tablet</option>
                            <option value="Smartwatch">Smartwatch</option>
                            <option value="Gaming Console">Gaming Console</option>
                            <option value="Printer">Printer</option>
                        </select>
                        
                        <input 
                            type="text" 
                            id="user-message" 
                            required 
                            placeholder="Describe your device issue..." 
                            class="flex-1 rounded-lg border border-gray-300 px-4 py-2 text-sm focus:border-blue-500 focus:ring-blue-500"
                        >
                        
                        <button type="submit" id="send-btn" class="rounded-lg bg-blue-600 px-6 py-2 font-semibold text-white hover:bg-blue-700">
                            Send
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Quick Suggestions -->
        <div class="lg:col-span-1">
            <div class="rounded-lg border border-gray-200 bg-white p-6">
                <h3 class="mb-4 font-semibold text-gray-900">Quick Examples</h3>
                <div class="space-y-2">
                    <button onclick="quickMessage('Phone', 'My iPhone screen is cracked')" class="w-full rounded-lg border border-gray-200 p-3 text-left text-sm hover:bg-gray-50">
                        📱 "My iPhone screen is cracked"
                    </button>
                    <button onclick="quickMessage('Laptop', 'Laptop overheating and slow')" class="w-full rounded-lg border border-gray-200 p-3 text-left text-sm hover:bg-gray-50">
                        💻 "Laptop overheating and slow"
                    </button>
                    <button onclick="quickMessage('Phone', 'Phone battery drains fast')" class="w-full rounded-lg border border-gray-200 p-3 text-left text-sm hover:bg-gray-50">
                        🔋 "Phone battery drains fast"
                    </button>
                    <button onclick="quickMessage('Laptop', 'Laptop keyboard not working')" class="w-full rounded-lg border border-gray-200 p-3 text-left text-sm hover:bg-gray-50">
                        ⌨️ "Laptop keyboard not working"
                    </button>
                </div>

                <div class="mt-6 rounded-lg bg-blue-50 p-4">
                    <h4 class="mb-2 text-sm font-semibold text-blue-900">💡 Tips</h4>
                    <ul class="space-y-1 text-xs text-blue-800">
                        <li>• Be specific about symptoms</li>
                        <li>• Mention error messages if any</li>
                        <li>• Include device model if known</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const chatMessages = document.getElementById('chat-messages');
const chatForm = document.getElementById('chat-form');
const deviceCategory = document.getElementById('device-category');
const userMessage = document.getElementById('user-message');
const sendBtn = document.getElementById('send-btn');

chatForm.addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const device = deviceCategory.value;
    const message = userMessage.value.trim();
    
    if (!device || !message) return;
    
    // Add user message to chat
    addMessage('user', message);
    
    // Clear input
    userMessage.value = '';
    sendBtn.disabled = true;
    sendBtn.textContent = 'Thinking...';
    
    try {
        const response = await fetch('/api/ai-consultant/message', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({
                device_category: device,
                user_message: message
            })
        });
        
        const data = await response.json();
        
        // Add AI response to chat
        addMessage('ai', data.reply || data.message || 'Sorry, I could not process your request.');
        
    } catch (error) {
        addMessage('ai', 'Sorry, the AI service is currently unavailable. Please try again later or create a direct repair request.');
    }
    
    sendBtn.disabled = false;
    sendBtn.textContent = 'Send';
});

function addMessage(sender, text) {
    const messageDiv = document.createElement('div');
    messageDiv.className = 'flex gap-3';
    
    if (sender === 'user') {
        messageDiv.innerHTML = `
            <div class="flex-1"></div>
            <div class="flex-1">
                <div class="rounded-lg bg-gray-100 p-4">
                    <p class="text-sm text-gray-900">${escapeHtml(text)}</p>
                </div>
                <p class="mt-1 text-right text-xs text-gray-500">Just now</p>
            </div>
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-600 text-white">
                👤
            </div>
        `;
    } else {
        messageDiv.innerHTML = `
            <div class="flex h-10 w-10 items-center justify-center rounded-full bg-blue-600 text-white">
                🤖
            </div>
            <div class="flex-1">
                <div class="rounded-lg bg-blue-50 p-4">
                    <p class="text-sm text-gray-900">${escapeHtml(text)}</p>
                </div>
                <p class="mt-1 text-xs text-gray-500">Just now</p>
            </div>
        `;
    }
    
    chatMessages.appendChild(messageDiv);
    chatMessages.scrollTop = chatMessages.scrollHeight;
}

function quickMessage(device, message) {
    deviceCategory.value = device;
    userMessage.value = message;
    chatForm.dispatchEvent(new Event('submit'));
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}
</script>
@endsection

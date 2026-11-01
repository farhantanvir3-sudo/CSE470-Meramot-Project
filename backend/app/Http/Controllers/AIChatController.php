<?php

namespace App\Http\Controllers;

use App\Models\AIChatSession;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * ============================================================================
 * FEATURE R10: AI-Powered Repair Diagnostic Conversational Consultant
 * ============================================================================
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
 * Architectural Layer: Controller (AIChatController)
 * Models Touched: AIChatSession, Message
 * View Rendered: resources/views/ai-consultant/chat.blade.php
 *
 * INSTRUCTIONS FOR SHANID:
 * 1. Implement index(): Render the interactive AI troubleshooting chat interface.
 * 2. Implement sendMessage(Request $request): Forward customer message to the
 *    FastAPI microservice endpoint (/api/ai/chat) and return AI triage suggestions.
 * ============================================================================
 */
class AIChatController extends Controller
{
    /**
     * Show AI Diagnostic Consultant interface.
     * Route: GET /ai-consultant/chat
     */
    public function index()
    {
        return view('ai-consultant.chat');
    }

    /**
     * Send message to Python FastAPI AI microservice.
     * Route: POST /api/ai-consultant/message
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'device_category' => 'required|string',
            'user_message' => 'required|string',
        ]);

        $aiUrl = env('AI_MICROSERVICE_URL', 'http://127.0.0.1:5000');

        try {
            $response = Http::post("{$aiUrl}/api/ai/chat", [
                'device_category' => $validated['device_category'],
                'user_message' => $validated['user_message'],
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'status' => 'error',
                'reply' => 'Our AI consultant is temporarily busy. Please create a direct repair request.',
            ], 500);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'reply' => 'AI Service unavailable: ' . $e->getMessage(),
            ], 503);
        }
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\RiderChatMessage;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R5: Real-Time Admin-to-Rider WebSocket Support Chat
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 4 (Dec 10 – Dec 20, 2026)
 * Architectural Layer: Controller (RiderChatController)
 * Models Touched: RiderChatMessage
 * View Rendered: resources/views/support/rider-chat.blade.php
 *
 * INSTRUCTIONS FOR NAFIUL:
 * 1. Implement index($riderId): Retrieve chat history between platform support desk
 *    and delivery rider.
 * 2. Implement sendMessage(Request $request): Save message to database and trigger
 *    Pusher WebSocket event broadcast to update chat UI in real time without reload.
 * ============================================================================
 */
class RiderChatController extends Controller
{
    /**
     * Show rider support chat view.
     * Route: GET /support/rider-chat/{riderId}
     */
    public function index($riderId)
    {
        // TODO (Nafiul): Fetch chat messages where delivery_agent_id = $riderId
        $messages = RiderChatMessage::where('delivery_agent_id', $riderId)->orderBy('created_at', 'asc')->get();

        return view('support.rider-chat', compact('messages', 'riderId'));
    }

    /**
     * Send chat message over WebSocket.
     * Route: POST /api/support/rider-chat/send
     */
    public function sendMessage(Request $request)
    {
        $validated = $request->validate([
            'delivery_agent_id' => 'required|exists:delivery_agents,id',
            'sender_role' => 'required|in:admin,rider',
            'message_text' => 'required|string|max:1000',
        ]);

        $message = RiderChatMessage::create(array_merge($validated, [
            'is_read' => false,
        ]));

        // TODO (Nafiul): Broadcast event to Pusher channel 'rider-chat.{delivery_agent_id}'

        return response()->json([
            'status' => 'success',
            'data' => $message,
        ]);
    }
}

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
    public function index(Request $request, $riderId)
    {
        return app(ComingSoonController::class)->show($request, 'rider-live-chat');
    }

    /**
     * Send chat message over WebSocket (Sprint 4 — routes to Coming Soon during Sprint 1).
     * Route: POST /api/support/rider-chat/send
     */
    public function sendMessage(Request $request)
    {
        return app(ComingSoonController::class)->show($request, 'rider-live-chat');
    }
}

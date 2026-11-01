<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R1: Delivery Agent Onboarding & Credential Verification
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 1 (Nov 1 – Nov 12, 2026)
 * Architectural Layer: Controller (AdminDeliveryController)
 * Models Touched: DeliveryAgent, User
 * View Rendered: resources/views/admin/verify-agent.blade.php
 *
 * INSTRUCTIONS FOR NAFIUL:
 * 1. Implement verifyAgentList(): Fetch all delivery agents with status 'pending'
 *    including user relation (name, email, phone, NID, license).
 * 2. Implement approveAgent($id): Update status to 'verified', generate random
 *    agent credentials, and trigger credential email via Mail/SMTP.
 * 3. Implement rejectAgent($id, Request $request): Validate rejection_reason
 *    and update agent status to 'rejected'.
 * ============================================================================
 */
class AdminDeliveryController extends Controller
{
    /**
     * Display list of delivery agents awaiting admin verification.
     * Route: GET /admin/delivery-agents/pending
     */
    public function index()
    {
        // TODO (Nafiul): Replace with DeliveryAgent::with('user')->where('verification_status', 'pending')->paginate(15);
        $pendingAgents = DeliveryAgent::with('user')->where('verification_status', 'pending')->get();

        return view('admin.verify-agent', compact('pendingAgents'));
    }

    /**
     * Store new delivery agent application submitted by applicant.
     * Route: POST /delivery-agent/register
     */
    public function store(Request $request)
    {
        // TODO (Nafiul): Add validation for nid_number, vehicle_type, license_number, driving_license_doc
        $validated = $request->validate([
            'nid_number' => 'required|string|max:30',
            'vehicle_type' => 'required|in:motorcycle,bicycle,scooter,van',
            'license_number' => 'required|string|max:50',
        ]);

        $agent = DeliveryAgent::create(array_merge($validated, [
            'user_id' => $request->user()->id ?? 1,
            'verification_status' => 'pending',
            'is_available' => false,
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Delivery agent registration submitted for admin approval.',
            'data' => $agent,
        ], 201);
    }

    /**
     * Admin approval action.
     * Route: POST /admin/delivery-agents/{id}/approve
     */
    public function approve($id)
    {
        // TODO (Nafiul): Fetch agent, set verification_status = 'verified', dispatch verification email
        $agent = DeliveryAgent::findOrFail($id);
        $agent->update(['verification_status' => 'verified']);

        return redirect()->back()->with('success', "Delivery agent {$agent->id} approved.");
    }

    /**
     * Admin rejection action.
     * Route: POST /admin/delivery-agents/{id}/reject
     */
    public function reject(Request $request, $id)
    {
        // TODO (Nafiul): Validate rejection reason and update status
        $agent = DeliveryAgent::findOrFail($id);
        $agent->update([
            'verification_status' => 'rejected',
        ]);

        return redirect()->back()->with('warning', "Delivery agent application rejected.");
    }
}

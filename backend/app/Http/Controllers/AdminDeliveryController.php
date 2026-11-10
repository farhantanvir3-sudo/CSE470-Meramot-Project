<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use Illuminate\Http\Request;

/**
 * FEATURE R1: Delivery Agent Onboarding & Credential Verification
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 */
class AdminDeliveryController extends Controller
{
    /**
     * Admin: list delivery agents awaiting verification.
     * Route: GET /admin/delivery-agents/pending
     */
    public function index()
    {
        $pendingAgents = DeliveryAgent::with('user')
            ->where('registration_status', 'PENDING')
            ->get();

        return view('admin.verify-agent', compact('pendingAgents'));
    }

    /**
     * Show the delivery agent application form.
     * Route: GET /delivery-agent/register
     */
    public function create()
    {
        return view('delivery.register');
    }

    /**
     * Save a new delivery agent application.
     * Route: POST /delivery-agent/register
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'nid_number' => 'required|string|max:30',
            'vehicle_type' => 'required|in:motorcycle,bicycle,scooter,van',
            'license_number' => 'required|string|max:50',
        ]);

        $agent = DeliveryAgent::create(array_merge($validated, [
            'user_id' => $request->user()->id ?? 1,
            'registration_status' => 'PENDING',
            'status' => 'OFFLINE',
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Delivery agent registration submitted for admin approval.',
            'data' => $agent,
        ], 201);
    }

    /**
     * Admin: approve an application.
     * Route: POST /admin/delivery-agents/{id}/approve
     */
    public function approve($id)
    {
        $agent = DeliveryAgent::findOrFail($id);
        $agent->update(['registration_status' => 'APPROVED']);

        return redirect()->back()->with('success', "Delivery agent {$agent->id} approved.");
    }

    /**
     * Admin: reject an application.
     * Route: POST /admin/delivery-agents/{id}/reject
     */
        public function reject(Request $request, $id)
        {
            $validated = $request->validate(['rejection_reason' => 'required|string|max:500',]);
            $agent = DeliveryAgent::findOrFail($id);
            $agent->update(['registration_status' => 'REJECTED','rejection_reason' => $validated['rejection_reason'],]);
            return redirect()->back()->with('warning', "Delivery agent application rejected.");
        }
}
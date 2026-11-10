<?php

namespace App\Http\Controllers;

use App\Models\DeliveryJob;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R2: Delivery Job Queue & Status Milestone Synchronization
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
 * Architectural Layer: Controller (DeliveryJobController)
 * Models Touched: DeliveryJob, RepairRequest
 * View Rendered: resources/views/delivery/jobs-queue.blade.php
 *
 * INSTRUCTIONS FOR NAFIUL:
 * 1. Implement queue(): Display all unassigned delivery jobs to available riders.
 * 2. Implement claimJob($id): Atomically assign the selected job to the authenticated
 *    delivery rider, changing status from 'unassigned' to 'assigned'.
 * 3. Implement updateMilestone($id, Request $request): Verify OTP code for pickup
 *    and dropoff, and update milestone timestamps (picked_up, in_transit, delivered).
 * ============================================================================
 */
class DeliveryJobController extends Controller
{
    /**
     * Display the active delivery job queue for riders.
     * Route: GET /delivery/jobs-queue
     */
    public function index(Request $request)
    {
        $availableJobs = DeliveryJob::with('repairRequest')
            ->whereIn('status', ['PENDING', 'unassigned'])
            ->latest()
            ->get();

        $agentId = $request->user()?->deliveryAgent?->id;
        $activeJobs = $agentId
            ? DeliveryJob::with('repairRequest')
                ->where('delivery_agent_id', $agentId)
                ->whereIn('status', ['SCHEDULED', 'DISPATCHED', 'PICKED_UP', 'IN_TRANSIT', 'assigned', 'picked_up', 'in_transit'])
                ->get()
            : collect();

        return view('delivery.jobs-queue', compact('availableJobs', 'activeJobs'));
    }

    /**
     * Rider claims an unassigned job (Sprint 2 — routes to Coming Soon during Sprint 1).
     * Route: POST /delivery/jobs/{id}/claim
     */
    public function claimJob(Request $request, $id)
    {
        return app(ComingSoonController::class)->show($request, 'rider-dispatch-queue');
    }

    /**
     * Update milestone status with OTP verification (Sprint 2 — routes to Coming Soon during Sprint 1).
     * Route: POST /delivery/jobs/{id}/milestone
     */
    public function updateMilestone(Request $request, $id)
    {
        return app(ComingSoonController::class)->show($request, 'rider-dispatch-queue');
    }
}

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
        // TODO (Nafiul): Fetch available delivery jobs where status is 'unassigned'
        $availableJobs = DeliveryJob::with('repairRequest')
            ->where('status', 'unassigned')
            ->latest()
            ->get();

        $activeJobs = DeliveryJob::with('repairRequest')
            ->where('delivery_agent_id', $request->user()->deliveryAgent->id ?? null)
            ->whereIn('status', ['assigned', 'picked_up', 'in_transit'])
            ->get();

        return view('delivery.jobs-queue', compact('availableJobs', 'activeJobs'));
    }

    /**
     * Rider claims an unassigned job.
     * Route: POST /delivery/jobs/{id}/claim
     */
    public function claimJob(Request $request, $id)
    {
        // TODO (Nafiul): Check if job is still 'unassigned', then assign to agent and generate 4-digit pickup OTP
        $job = DeliveryJob::where('id', $id)->where('status', 'unassigned')->firstOrFail();
        
        $job->update([
            'delivery_agent_id' => $request->user()->deliveryAgent->id ?? 1,
            'status' => 'assigned',
            'pickup_otp' => str_pad((string)random_int(1000, 9999), 4, '0', STR_PAD_LEFT),
        ]);

        return redirect()->back()->with('success', 'Job claimed successfully.');
    }

    /**
     * Update milestone status with OTP verification.
     * Route: POST /delivery/jobs/{id}/milestone
     */
    public function updateMilestone(Request $request, $id)
    {
        // TODO (Nafiul): Verify pickup_otp or dropoff_otp and transition job status
        $job = DeliveryJob::findOrFail($id);
        $nextStatus = $request->input('next_status'); // 'picked_up' or 'delivered'

        $job->update([
            'status' => $nextStatus,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => "Milestone updated to {$nextStatus}",
            'data' => $job,
        ]);
    }
}

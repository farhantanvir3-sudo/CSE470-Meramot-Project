<?php

namespace App\Http\Controllers;

use App\Models\DeliveryJob;
use App\Models\LocationLog;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R4: Live Delivery Agent GPS Location & Route Sharing
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 3 (Nov 28 – Dec 9, 2026)
 * Architectural Layer: Controller (TrackingController)
 * Models Touched: LocationLog
 * View Rendered: resources/views/tracking/live-map.blade.php
 *
 * INSTRUCTIONS FOR NAFIUL:
 * 1. Implement showMap($jobId): Render interactive Leaflet map centered at
 *    the active rider's latest coordinates and the destination dropoff point.
 * 2. Implement pingLocation(Request $request): Periodic GPS broadcast from rider's
 *    mobile device, updating current location in database and broadcasting to Pusher.
 * ============================================================================
 */
class TrackingController extends Controller
{
    /**
     * Render live GPS map tracking view.
     * Route: GET /tracking/{jobId}
     * Resolves either a DeliveryJob ID directly or a RepairRequest ID / RepairJob ID.
     */
    public function showMap(Request $request, $jobId)
    {
        $job = DeliveryJob::with(['deliveryAgent.user', 'repairRequest', 'repairJob.shop'])->find($jobId);

        if (!$job) {
            $job = DeliveryJob::with(['deliveryAgent.user', 'repairRequest', 'repairJob.shop'])
                ->where('repair_request_id', $jobId)
                ->orWhere('repair_job_id', $jobId)
                ->latest('id')
                ->first();
        }

        if (!$job) {
            return app(ComingSoonController::class)->show(
                $request->merge(['request_id' => $jobId]),
                'live-rider-gps'
            );
        }

        $latestLocation = LocationLog::where('delivery_job_id', $job->id)->latest()->first();

        return view('tracking.live-map', compact('job', 'latestLocation'));
    }

    /**
     * Ingest real-time GPS coordinate ping from rider client.
     * Route: POST /api/tracking/ping
     */
    public function pingLocation(Request $request)
    {
        $validated = $request->validate([
            'delivery_job_id' => 'required|exists:delivery_jobs,id',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $log = LocationLog::create(array_merge($validated, [
            'delivery_agent_id' => $request->user()->deliveryAgent->id ?? 1,
            'recorded_at' => now(),
        ]));

        // TODO (Nafiul): Broadcast location update on Pusher channel "tracking.{delivery_job_id}"

        return response()->json([
            'status' => 'success',
            'data' => $log,
        ]);
    }
}

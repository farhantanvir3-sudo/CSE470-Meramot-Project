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
     */
    public function showMap($jobId)
    {
        // TODO (Nafiul): Load job details along with latest GPS coordinates
        $job = DeliveryJob::with(['deliveryAgent', 'repairRequest'])->findOrFail($jobId);
        $latestLocation = LocationLog::where('delivery_job_id', $jobId)->latest()->first();

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

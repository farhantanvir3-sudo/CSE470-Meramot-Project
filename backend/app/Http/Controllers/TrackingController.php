<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\LocationLog;
use App\Models\RepairRequest;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R4: Live Delivery Agent GPS Location & Route Sharing
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 3
 * Architectural Layer: Controller (TrackingController)
 * Models Touched: DeliveryJob, LocationLog, DeliveryAgent, RepairRequest
 * View Rendered: frontend/views/tracking/live-map.blade.php
 * ============================================================================
 */
class TrackingController extends Controller
{
    protected const DHAKA_LANDMARKS = [
        'dhanmondi' => [23.7461, 90.3742],
        'elephant road' => [23.7389, 90.3882],
        'multiplan' => [23.7392, 90.3875],
        'gulshan' => [23.7925, 90.4078],
        'banani' => [23.7937, 90.4066],
        'uttara' => [23.8759, 90.3795],
        'mirpur' => [23.8042, 90.3667],
        'mohammadpur' => [23.7662, 90.3589],
        'bashundhara' => [23.8191, 90.4526],
        'badda' => [23.7805, 90.4267],
        'rampura' => [23.7612, 90.4219],
        'khilgaon' => [23.7517, 90.4264],
        'motijheel' => [23.7330, 90.4172],
        'farmgate' => [23.7561, 90.3872],
        'tejgaon' => [23.7639, 90.3994],
        'shahbag' => [23.7381, 90.3958],
        'mohakhali' => [23.7778, 90.4057],
        'baridhara' => [23.8035, 90.4193],
        'lalmatia' => [23.7571, 90.3688],
        'wari' => [23.7174, 90.4178],
        'jatrabari' => [23.7104, 90.4349],
    ];

    /**
     * Render live GPS map tracking view.
     * Route: GET /tracking/{jobId}
     * Resolves DeliveryJob ID, RepairRequest ID, or RepairJob ID seamlessly.
     */
    public function showMap(Request $request, $jobId)
    {
        $job = $this->resolveDeliveryJob($jobId);
        $repairRequest = $job?->repairRequest;

        if (!$job && is_numeric($jobId)) {
            $repairRequest = RepairRequest::with(['customer', 'requestedShop', 'repairJob.shop'])->find((int) $jobId);
        }

        if (!$job && !$repairRequest) {
            return view('tracking.live-map', [
                'job' => null,
                'repairRequest' => null,
                'telemetry' => null,
                'notFoundId' => $jobId,
                'canViewOtp' => false,
            ]);
        }

        $telemetry = $this->buildTelemetryPayload($job, $repairRequest);
        $viewer = $request->user();
        $canViewOtp = $viewer && (
            $viewer->role === 'admin'
            || ($repairRequest && (int) $viewer->id === (int) $repairRequest->customer_id)
        );

        return view('tracking.live-map', [
            'job' => $job,
            'repairRequest' => $repairRequest,
            'telemetry' => $telemetry,
            'notFoundId' => null,
            'canViewOtp' => $canViewOtp,
        ]);
    }

    /**
     * Real-time JSON telemetry endpoint for 5s live map polling.
     * Route: GET /api/tracking/{jobId}/live & GET /tracking/{jobId}/live
     */
    public function liveTelemetry(Request $request, $jobId)
    {
        $job = $this->resolveDeliveryJob($jobId);
        $repairRequest = $job?->repairRequest;

        if (!$job && is_numeric($jobId)) {
            $repairRequest = RepairRequest::with(['customer', 'requestedShop', 'repairJob.shop'])->find((int) $jobId);
        }

        if (!$job && !$repairRequest) {
            return response()->json([
                'ok' => false,
                'message' => 'Tracking record not found.',
            ], 404);
        }

        return response()->json([
            'ok' => true,
            'telemetry' => $this->buildTelemetryPayload($job, $repairRequest),
        ]);
    }

    /**
     * Ingest real-time GPS coordinate ping from rider client / simulator.
     * Route: POST /api/tracking/ping
     */
    public function pingLocation(Request $request)
    {
        $validated = $request->validate([
            'delivery_job_id' => 'required|exists:delivery_jobs,id',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $job = DeliveryJob::with(['deliveryAgent', 'repairRequest', 'repairJob.shop'])
            ->findOrFail($validated['delivery_job_id']);

        $agentId = $request->user()?->deliveryAgent?->id
            ?? $job->delivery_agent_id
            ?? DeliveryAgent::value('id');

        $lat = round((float) $validated['latitude'], 6);
        $lng = round((float) $validated['longitude'], 6);

        $log = null;
        if ($agentId) {
            $log = LocationLog::create([
                'delivery_job_id' => $job->id,
                'delivery_agent_id' => $agentId,
                'latitude' => $lat,
                'longitude' => $lng,
                'recorded_at' => now(),
            ]);

            DeliveryAgent::where('id', $agentId)->update([
                'current_latitude' => $lat,
                'current_longitude' => $lng,
            ]);
        }

        $job->refresh();

        return response()->json([
            'status' => 'success',
            'data' => $log,
            'telemetry' => $this->buildTelemetryPayload($job, $job->repairRequest),
        ]);
    }

    protected function resolveDeliveryJob($jobId): ?DeliveryJob
    {
        if (!is_numeric($jobId)) {
            return DeliveryJob::with(['deliveryAgent.user', 'repairRequest.customer', 'repairJob.shop'])
                ->where('tracking_code', (string) $jobId)
                ->latest('id')
                ->first();
        }

        $job = DeliveryJob::with(['deliveryAgent.user', 'repairRequest.customer', 'repairJob.shop'])
            ->find((int) $jobId);

        if (!$job) {
            $job = DeliveryJob::with(['deliveryAgent.user', 'repairRequest.customer', 'repairJob.shop'])
                ->where('repair_request_id', (int) $jobId)
                ->orWhere('repair_job_id', (int) $jobId)
                ->latest('id')
                ->first();
        }

        return $job;
    }

    protected function buildTelemetryPayload(?DeliveryJob $job, ?RepairRequest $repairRequest): array
    {
        $shop = $job?->repairJob?->shop ?? $repairRequest?->repairJob?->shop ?? $repairRequest?->requestedShop;
        $shopLat = $shop?->latitude ? (float) $shop->latitude : null;
        $shopLng = $shop?->longitude ? (float) $shop->longitude : null;

        $pickupAddress = $job?->pickup_address ?? $repairRequest?->pickup_address ?? 'Dhanmondi 27, Dhaka';
        $dropoffAddress = $job?->dropoff_address ?? $shop?->address ?? 'Multiplan Center, Elephant Road, Dhaka';

        $isToCustomer = ($job?->direction ?? 'TO_SHOP') === 'TO_CUSTOMER';

        $pickupCoords = $isToCustomer && $shopLat && $shopLng
            ? [$shopLat, $shopLng]
            : $this->resolveCoordinatesFromAddress($pickupAddress, [23.7461, 90.3742]);

        $dropoffCoords = !$isToCustomer && $shopLat && $shopLng
            ? [$shopLat, $shopLng]
            : $this->resolveCoordinatesFromAddress($dropoffAddress, [23.7392, 90.3875]);

        $breadcrumbs = [];
        $latestLocation = null;

        if ($job) {
            $logs = LocationLog::where('delivery_job_id', $job->id)
                ->orderBy('id', 'asc')
                ->take(50)
                ->get();

            $latestLocation = $logs->last();
            foreach ($logs as $row) {
                $breadcrumbs[] = [
                    'lat' => round((float) $row->latitude, 6),
                    'lng' => round((float) $row->longitude, 6),
                    'recorded_at' => optional($row->recorded_at ?? $row->created_at)->format('h:i:s A'),
                ];
            }
        }

        $riderLat = $latestLocation
            ? (float) $latestLocation->latitude
            : ($job?->deliveryAgent?->current_latitude
                ? (float) $job->deliveryAgent->current_latitude
                : (($pickupCoords[0] + $dropoffCoords[0]) / 2));

        $riderLng = $latestLocation
            ? (float) $latestLocation->longitude
            : ($job?->deliveryAgent?->current_longitude
                ? (float) $job->deliveryAgent->current_longitude
                : (($pickupCoords[1] + $dropoffCoords[1]) / 2));

        $status = strtoupper((string) ($job?->status ?? $repairRequest?->status ?? 'PENDING'));
        $targetCoords = in_array($status, ['PENDING', 'SCHEDULED', 'DISPATCHED'], true)
            ? $pickupCoords
            : $dropoffCoords;

        $distanceKm = $status === 'DELIVERED'
            ? 0.0
            : round($this->haversineKm($riderLat, $riderLng, $targetCoords[0], $targetCoords[1]), 2);

        $etaMinutes = $status === 'DELIVERED'
            ? 0
            : max(3, (int) ceil(($distanceKm / 22.0) * 60));

        $riderName = $job?->rider_name
            ?? $job?->deliveryAgent?->user?->name
            ?? ($job ? 'Awaiting Courier Assignment' : 'Pre-Dispatch Queue');

        $riderPhone = $job?->rider_phone
            ?? $job?->deliveryAgent?->user?->phone
            ?? null;

        return [
            'delivery_job_id' => $job?->id,
            'repair_request_id' => $repairRequest?->id,
            'tracking_code' => $job?->tracking_code ?? ($repairRequest ? ('MRM-REQ-' . $repairRequest->id) : 'N/A'),
            'direction' => $job?->direction ?? 'TO_SHOP',
            'type' => $job?->type ?? ($repairRequest?->delivery_type ?? 'REGULAR'),
            'status' => $status,
            'rider_name' => $riderName,
            'rider_phone' => $riderPhone,
            'vehicle_type' => strtoupper((string) ($job?->deliveryAgent?->vehicle_type ?? 'MOTORCYCLE')),
            'rider_lat' => round($riderLat, 6),
            'rider_lng' => round($riderLng, 6),
            'pickup_address' => $pickupAddress,
            'pickup_lat' => round($pickupCoords[0], 6),
            'pickup_lng' => round($pickupCoords[1], 6),
            'dropoff_address' => $dropoffAddress,
            'dropoff_lat' => round($dropoffCoords[0], 6),
            'dropoff_lng' => round($dropoffCoords[1], 6),
            'distance_km' => $distanceKm,
            'eta_minutes' => $etaMinutes,
            'breadcrumbs' => $breadcrumbs,
            'last_ping_at' => $latestLocation
                ? optional($latestLocation->recorded_at ?? $latestLocation->created_at)->diffForHumans()
                : 'Live telemetry ready',
        ];
    }

    protected function resolveCoordinatesFromAddress(?string $address, array $fallback): array
    {
        $needle = strtolower((string) $address);
        foreach (self::DHAKA_LANDMARKS as $area => $coords) {
            if (str_contains($needle, $area)) {
                return $coords;
            }
        }
        return $fallback;
    }

    protected function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return 2 * $earthRadius * asin(min(1.0, sqrt($a)));
    }
}

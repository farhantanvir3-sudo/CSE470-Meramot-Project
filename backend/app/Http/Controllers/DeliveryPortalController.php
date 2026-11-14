<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\LocationLog;
use App\Models\RiderChatMessage;
use App\Services\DeliveryLifecycleService;
use App\Services\GmailApiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * ============================================================================
 * FEATURE R1 / R2 / R3 / R4: Delivery Partner Company Auth & Workspace Portal
 * ============================================================================
 * Assigned Developer: Md. Nafiul Islam [Student ID: 22299035]
 * Portal Architecture & Renewal Integration: Farhan Tanvir [Student ID: 22299179]
 *
 * Implements:
 *   1. Dedicated Delivery Partner Login (`GET/POST /delivery/login`) strictly
 *      validating `@meramotbd.com` company emails & system-generated passwords,
 *      with direct links to "Check Application Status" & "Apply as a delivery agent".
 *   2. Delivery Partner Dashboard (`GET /delivery`) with Active/Completed KPIs,
 *      Active vs All Assigned queue filter, and clickable delivery cards.
 *   3. Live OpenStreetMap + Leaflet Rider Map (`GET /delivery/map`, `POST /delivery/location`).
 *   4. Delivery Order Details & 6-Step Milestone Tracker (`GET /delivery/order/{id}`).
 *   5. Read-Only Delivery Partner Profile (`GET /delivery/profile`) with Sign Out.
 * ============================================================================
 */
class DeliveryPortalController extends Controller
{
    public const ACTIVE_DELIVERY_STATUSES = [
        'PENDING',
        'SCHEDULED',
        'DISPATCHED',
        'PICKED_UP',
        'IN_TRANSIT',
    ];

    public const NEXT_STATUS_MAP = [
        'PENDING' => 'SCHEDULED',
        'SCHEDULED' => 'DISPATCHED',
        'DISPATCHED' => 'PICKED_UP',
        'PICKED_UP' => 'IN_TRANSIT',
        'IN_TRANSIT' => 'DELIVERED',
        'DELIVERED' => null,
        'FAILED' => null,
        'CANCELLED' => null,
    ];

    /**
     * Ensure current request is authenticated as an approved, active delivery agent.
     */
    public function resolveAuthenticatedAgent(Request $request): ?DeliveryAgent
    {
        $user = $request->user();
        if (!$user || !in_array($user->role, ['delivery_agent', 'delivery'], true)) {
            return null;
        }

        return DeliveryAgent::with('user')->where('user_id', $user->id)->first();
    }

    /**
     * Show Dedicated Delivery Partner Login (`GET /delivery/login`)
     */
    public function showLogin(Request $request)
    {
        $user = $request->user();
        $agent = $this->resolveAuthenticatedAgent($request);
        if (
            $user
            && $user->is_active
            && $agent
            && strtoupper((string) $agent->registration_status) === 'APPROVED'
            && $agent->is_active
        ) {
            return redirect('/delivery');
        }

        return response()
            ->view('delivery.login')
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache');
    }

    /**
     * Authenticate Delivery Partner via `@meramotbd.com` Company Email (`POST /delivery/login`)
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'company_email' => 'required|email|max:180',
            'password' => 'required|string',
        ]);

        $companyEmail = strtolower(trim($validated['company_email']));

        if (!str_ends_with($companyEmail, '@meramotbd.com')) {
            return back()->withInput($request->only('company_email'))->withErrors([
                'company_email' => 'Please enter a valid @meramotbd.com company email address (e.g. yourname@meramotbd.com).',
            ]);
        }

        $agent = DeliveryAgent::with('user')
            ->where(function ($query) use ($companyEmail) {
                $query->whereRaw('LOWER(company_email) = ?', [$companyEmail])
                    ->orWhereHas('user', function ($q) use ($companyEmail) {
                        $q->whereRaw('LOWER(email) = ?', [$companyEmail])
                          ->whereIn('role', ['delivery_agent', 'delivery']);
                    });
            })
            ->first();

        if (!$agent || !$agent->user) {
            return back()->withInput($request->only('company_email'))->withErrors([
                'company_email' => 'This @meramotbd.com company email is not registered in our Delivery Partner database.',
            ]);
        }

        if ($agent->registration_status !== 'APPROVED' || !$agent->is_active || !$agent->user->is_active) {
            return back()->withInput($request->only('company_email'))->withErrors([
                'company_email' => 'This delivery partner account is not active or approved. Please use "Check Application Status" below.',
            ]);
        }

        if (empty($agent->user->password) || !Hash::check($validated['password'], $agent->user->password)) {
            return back()->withInput($request->only('company_email'))->withErrors([
                'company_email' => 'Invalid @meramotbd.com company email or system password.',
            ]);
        }

        AuthController::clearTransientAuthSession($request);
        Auth::login($agent->user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect('/delivery')->with(
            'success',
            'Signed in as ' . ($agent->user->name ?: $companyEmail) . '.'
        );
    }

    /**
     * Sign out of Delivery Partner Workspace (`POST /delivery/logout`)
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/delivery/login')->with('success', 'You have signed out of the Delivery Partner Workspace.');
    }

    /**
     * Delivery Partner Dashboard (`GET /delivery`)
     */
    public function dashboard(Request $request)
    {
        $agent = $this->resolveAuthenticatedAgent($request);
        if (!$agent) {
            return redirect('/delivery/login')->with('warning', 'Please sign in with your @meramotbd.com company credentials.');
        }

        $user = $agent->user;
        $viewMode = strtoupper((string) $request->query('view', 'ACTIVE')) === 'ASSIGNED' ? 'ASSIGNED' : 'ACTIVE';

        $deliveries = DeliveryJob::with([
            'repairRequest.customer',
            'repairJob.shop',
            'repairJob.repairRequest.customer',
        ])
            ->where('delivery_agent_id', $agent->id)
            ->orderByRaw("CASE WHEN status IN ('IN_TRANSIT','PICKED_UP','DISPATCHED','SCHEDULED','PENDING') THEN 0 ELSE 1 END")
            ->orderByDesc('updated_at')
            ->get();

        $activeDeliveries = $deliveries->filter(
            fn (DeliveryJob $d) => in_array(strtoupper((string) $d->status), self::ACTIVE_DELIVERY_STATUSES, true)
        )->values();

        $activeCount = $activeDeliveries->count();
        $completedCount = $deliveries->filter(
            fn (DeliveryJob $d) => strtoupper((string) $d->status) === 'DELIVERED'
        )->count();
        $completedEarnings = (float) $deliveries->filter(
            fn (DeliveryJob $d) => strtoupper((string) $d->status) === 'DELIVERED'
        )->sum('fee');

        $visibleDeliveries = $viewMode === 'ASSIGNED' ? $deliveries : $activeDeliveries;

        $openPoolQuery = DeliveryJob::with([
            'repairRequest.customer',
            'repairJob.shop',
        ])
            ->whereNull('delivery_agent_id')
            ->whereIn('status', DeliveryLifecycleService::OPEN_POOL_STATUSES)
            ->where(function ($query) {
                $query->whereDoesntHave('repairRequest')
                    ->orWhereHas('repairRequest', function ($reqQuery) {
                        $reqQuery->whereNotIn('status', ['CANCELLED', 'REJECTED']);
                    });
            });

        $openPoolCount = (clone $openPoolQuery)->count();
        $openPoolJobs = $openPoolQuery->latest()->take(3)->get();

        return view('delivery.dashboard', compact(
            'user',
            'agent',
            'deliveries',
            'visibleDeliveries',
            'viewMode',
            'activeCount',
            'completedCount',
            'completedEarnings',
            'openPoolCount',
            'openPoolJobs'
        ));
    }

    /**
     * Live OpenStreetMap + Leaflet Rider Map (`GET /delivery/map`)
     */
    public function map(Request $request)
    {
        $agent = $this->resolveAuthenticatedAgent($request);
        if (!$agent) {
            return redirect('/delivery/login');
        }

        $user = $agent->user;
        $activeDeliveries = DeliveryJob::with([
            'repairRequest.customer',
            'repairJob.shop',
            'repairJob.repairRequest.customer',
        ])
            ->where('delivery_agent_id', $agent->id)
            ->whereIn('status', self::ACTIVE_DELIVERY_STATUSES)
            ->orderByDesc('updated_at')
            ->get();

        // Build map marker points with reliable Dhaka coordinates for instant Leaflet rendering
        $dhakaAreaCoords = [
            'banani' => [23.7937, 90.4066],
            'gulshan' => [23.7925, 90.4158],
            'dhanmondi' => [23.7461, 90.3742],
            'mohammadpur' => [23.7662, 90.3589],
            'kalabagan' => [23.7494, 90.3831],
            'mirpur' => [23.8071, 90.3686],
            'uttara' => [23.8759, 90.3795],
            'bashundhara' => [23.8191, 90.4526],
            'mohakhali' => [23.7778, 90.4057],
            'hatirpool' => [23.7422, 90.3918],
        ];

        $resolveFallbackCoords = function (?string $address, int $seedOffset = 0) use ($dhakaAreaCoords): array {
            $lower = strtolower((string) $address);
            foreach ($dhakaAreaCoords as $area => [$lat, $lng]) {
                if (str_contains($lower, $area)) {
                    $jitterLat = (($seedOffset % 5) - 2) * 0.0014;
                    $jitterLng = ((($seedOffset * 3) % 5) - 2) * 0.0014;
                    return [round($lat + $jitterLat, 6), round($lng + $jitterLng, 6)];
                }
            }
            return [23.7515 + (($seedOffset % 5) * 0.004), 90.3840 + (($seedOffset % 4) * 0.004)];
        };

        $mapPoints = [];
        foreach ($activeDeliveries as $idx => $delivery) {
            $req = $delivery->repairRequest ?? $delivery->repairJob?->repairRequest;
            $shop = $delivery->repairJob?->shop;
            $cust = $req?->customer;
            $isToShop = strtoupper((string) $delivery->direction) === 'TO_SHOP';
            $title = $req?->title ?? ('Delivery #' . $delivery->id);

            if ($isToShop) {
                [$custLat, $custLng] = ($cust && $cust->latitude && $cust->longitude)
                    ? [(float) $cust->latitude, (float) $cust->longitude]
                    : $resolveFallbackCoords($delivery->pickup_address, $delivery->id);

                [$shopLat, $shopLng] = ($shop && $shop->latitude && $shop->longitude)
                    ? [(float) $shop->latitude, (float) $shop->longitude]
                    : $resolveFallbackCoords($delivery->dropoff_address, $delivery->id + 10);

                $mapPoints[] = [
                    'key' => $delivery->id . '-pickup',
                    'deliveryId' => $delivery->id,
                    'direction' => 'TO_SHOP',
                    'status' => $delivery->status,
                    'title' => $title,
                    'lat' => $custLat,
                    'lng' => $custLng,
                    'label' => 'Customer Pickup',
                    'address' => $delivery->pickup_address,
                    'kind' => 'CUSTOMER_PICKUP',
                ];
                $mapPoints[] = [
                    'key' => $delivery->id . '-drop',
                    'deliveryId' => $delivery->id,
                    'direction' => 'TO_SHOP',
                    'status' => $delivery->status,
                    'title' => $title,
                    'lat' => $shopLat,
                    'lng' => $shopLng,
                    'label' => 'Shop Delivery',
                    'address' => $delivery->dropoff_address,
                    'kind' => 'SHOP_DELIVERY',
                ];
            } else {
                [$shopLat, $shopLng] = ($shop && $shop->latitude && $shop->longitude)
                    ? [(float) $shop->latitude, (float) $shop->longitude]
                    : $resolveFallbackCoords($delivery->pickup_address, $delivery->id + 10);

                [$custLat, $custLng] = ($cust && $cust->latitude && $cust->longitude)
                    ? [(float) $cust->latitude, (float) $cust->longitude]
                    : $resolveFallbackCoords($delivery->dropoff_address, $delivery->id);

                $mapPoints[] = [
                    'key' => $delivery->id . '-pickup',
                    'deliveryId' => $delivery->id,
                    'direction' => 'TO_CUSTOMER',
                    'status' => $delivery->status,
                    'title' => $title,
                    'lat' => $shopLat,
                    'lng' => $shopLng,
                    'label' => 'Shop Pickup',
                    'address' => $delivery->pickup_address,
                    'kind' => 'SHOP_PICKUP',
                ];
                $mapPoints[] = [
                    'key' => $delivery->id . '-drop',
                    'deliveryId' => $delivery->id,
                    'direction' => 'TO_CUSTOMER',
                    'status' => $delivery->status,
                    'title' => $title,
                    'lat' => $custLat,
                    'lng' => $custLng,
                    'label' => 'Customer Delivery',
                    'address' => $delivery->dropoff_address,
                    'kind' => 'CUSTOMER_DELIVERY',
                ];
            }
        }

        $riderLat = $agent->current_latitude !== null ? (float) $agent->current_latitude : 23.7512;
        $riderLng = $agent->current_longitude !== null ? (float) $agent->current_longitude : 90.3789;

        return view('delivery.map', compact('user', 'agent', 'activeDeliveries', 'mapPoints', 'riderLat', 'riderLng'));
    }

    /**
     * Sync Rider GPS Coordinates (`POST /delivery/location`)
     */
    public function updateLocation(Request $request)
    {
        $agent = $this->resolveAuthenticatedAgent($request);
        if (!$agent) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthorized'], 401)
                : redirect('/delivery/login');
        }

        $validated = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ]);

        $lat = (float) $validated['latitude'];
        $lng = (float) $validated['longitude'];

        $agent->update([
            'current_latitude' => $lat,
            'current_longitude' => $lng,
        ]);

        $activeJob = DeliveryJob::where('delivery_agent_id', $agent->id)
            ->whereIn('status', ['DISPATCHED', 'PICKED_UP', 'IN_TRANSIT'])
            ->latest('updated_at')
            ->first();

        if ($activeJob) {
            LocationLog::create([
                'delivery_agent_id' => $agent->id,
                'delivery_job_id' => $activeJob->id,
                'latitude' => $lat,
                'longitude' => $lng,
                'recorded_at' => now(),
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'latitude' => $lat,
                'longitude' => $lng,
            ]);
        }

        return back()->with('success', 'Rider GPS coordinates updated.');
    }

    /**
     * Delivery Order Details & Milestone Flow (`GET /delivery/order/{id}`)
     */
    public function orderDetails(Request $request, $id)
    {
        $agent = $this->resolveAuthenticatedAgent($request);
        if (!$agent) {
            return redirect('/delivery/login');
        }

        $user = $agent->user;
        $delivery = DeliveryJob::with([
            'repairRequest.customer',
            'repairJob.shop',
            'repairJob.repairRequest.customer',
        ])->findOrFail($id);

        if ($delivery->delivery_agent_id !== null && (int) $delivery->delivery_agent_id !== (int) $agent->id) {
            abort(403, 'This delivery order is assigned to another courier partner.');
        }

        $flowByDirection = [
            'TO_SHOP' => [
                ['code' => 'PENDING', 'title' => 'Awaiting Courier Acceptance'],
                ['code' => 'SCHEDULED', 'title' => 'Pickup Scheduled'],
                ['code' => 'DISPATCHED', 'title' => 'En Route to Customer'],
                ['code' => 'PICKED_UP', 'title' => 'Picked Up from Customer'],
                ['code' => 'IN_TRANSIT', 'title' => 'En Route to Workshop'],
                ['code' => 'DELIVERED', 'title' => 'At Shop (Checked In)'],
            ],
            'TO_CUSTOMER' => [
                ['code' => 'PENDING', 'title' => 'Awaiting Courier Acceptance'],
                ['code' => 'SCHEDULED', 'title' => 'Pickup Scheduled'],
                ['code' => 'DISPATCHED', 'title' => 'En Route to Workshop'],
                ['code' => 'PICKED_UP', 'title' => 'Picked Up from Shop'],
                ['code' => 'IN_TRANSIT', 'title' => 'Out for Delivery'],
                ['code' => 'DELIVERED', 'title' => 'Delivered to Customer'],
            ],
        ];

        $direction = strtoupper((string) $delivery->direction) === 'TO_CUSTOMER' ? 'TO_CUSTOMER' : 'TO_SHOP';
        $flowSteps = $flowByDirection[$direction];
        $statusUpper = strtoupper((string) $delivery->status);
        $currentStepIndex = -1;
        foreach ($flowSteps as $i => $step) {
            if ($step['code'] === $statusUpper) {
                $currentStepIndex = $i;
                break;
            }
        }

        $nextStatus = self::NEXT_STATUS_MAP[$statusUpper] ?? null;
        $canonicalLabel = DeliveryLifecycleService::milestoneLabel($delivery);
        $requiresOtp = (
            ($nextStatus === 'PICKED_UP' && !empty($delivery->pickup_otp))
            || ($nextStatus === 'DELIVERED' && !empty($delivery->dropoff_otp))
        );

        $chatMessages = RiderChatMessage::where('delivery_job_id', $delivery->id)
            ->orderBy('created_at')
            ->get();

        return view('delivery.order-show', compact(
            'user',
            'agent',
            'delivery',
            'flowSteps',
            'currentStepIndex',
            'nextStatus',
            'canonicalLabel',
            'requiresOtp',
            'chatMessages'
        ));
    }

    /**
     * Advance Delivery Milestone Status (`POST /delivery/order/{id}/status`)
     */
    public function advanceOrderStatus(Request $request, $id)
    {
        $agent = $this->resolveAuthenticatedAgent($request);
        if (!$agent || !$agent->is_active || strtoupper((string) $agent->registration_status) !== 'APPROVED') {
            return redirect('/delivery/login')->with('warning', 'Approved active courier credentials are required.');
        }

        $validated = $request->validate([
            'milestone' => 'nullable|string|max:40',
            'otp' => 'nullable|string|max:10',
        ]);

        // Security invariant: always enforce 4-digit handover OTP on physical custody transitions
        $updated = app(DeliveryLifecycleService::class)->advanceMilestone(
            (int) $id,
            $agent,
            $validated['milestone'] ?? null,
            $validated['otp'] ?? null,
            true
        );

        return redirect("/delivery/order/{$updated->id}")
            ->with('success', 'Milestone updated to ' . DeliveryLifecycleService::milestoneLabel($updated) . '.');
    }

    /**
     * Feature R4: Simulate Rider Advancing Along Active Route (`POST /delivery/location/simulate`)
     * Useful when browser geolocation is unavailable or testing live tracking on desktop.
     */
    public function simulateRouteStep(Request $request)
    {
        $agent = $this->resolveAuthenticatedAgent($request);
        if (!$agent || !$agent->is_active || strtoupper((string) $agent->registration_status) !== 'APPROVED') {
            return $request->expectsJson()
                ? response()->json(['message' => 'Unauthorized courier'], 403)
                : redirect('/delivery/login');
        }

        $jobQuery = DeliveryJob::with(['repairRequest.customer', 'repairJob.shop'])
            ->where('delivery_agent_id', $agent->id)
            ->whereIn('status', self::ACTIVE_DELIVERY_STATUSES);

        if ($request->filled('delivery_job_id')) {
            $jobQuery->where('id', (int) $request->input('delivery_job_id'));
        }

        $activeJob = $jobQuery->latest('updated_at')->first();

        $curLat = $agent->current_latitude !== null ? (float) $agent->current_latitude : 23.7808;
        $curLng = $agent->current_longitude !== null ? (float) $agent->current_longitude : 90.4042;
        $targetLat = 23.7937;
        $targetLng = 90.4066;
        $targetLabel = 'Dhaka Dispatch Corridor';

        if ($activeJob) {
            $req = $activeJob->repairRequest ?? $activeJob->repairJob?->repairRequest;
            $shop = $activeJob->repairJob?->shop;
            $cust = $req?->customer;
            $isToShop = strtoupper((string) $activeJob->direction) !== 'TO_CUSTOMER';
            $statusUpper = strtoupper((string) $activeJob->status);
            $headingToDropoff = in_array($statusUpper, ['PICKED_UP', 'IN_TRANSIT'], true);

            $custCoords = ($cust && $cust->latitude && $cust->longitude)
                ? [(float) $cust->latitude, (float) $cust->longitude]
                : [23.7937, 90.4066];
            $shopCoords = ($shop && $shop->latitude && $shop->longitude)
                ? [(float) $shop->latitude, (float) $shop->longitude]
                : [23.7509, 90.3842];

            if ($isToShop) {
                [$targetLat, $targetLng] = $headingToDropoff ? $shopCoords : $custCoords;
                $targetLabel = $headingToDropoff ? ($shop?->name ?: 'Workshop Dropoff') : 'Customer Pickup';
            } else {
                [$targetLat, $targetLng] = $headingToDropoff ? $custCoords : $shopCoords;
                $targetLabel = $headingToDropoff ? 'Customer Dropoff' : ($shop?->name ?: 'Workshop Pickup');
            }
        }

        $dLat = $targetLat - $curLat;
        $dLng = $targetLng - $curLng;

        if (abs($dLat) < 0.0006 && abs($dLng) < 0.0006) {
            // Already at target pin — apply slight realistic patrol jitter
            $newLat = round($targetLat + ((random_int(-3, 3)) * 0.0002), 6);
            $newLng = round($targetLng + ((random_int(-3, 3)) * 0.0002), 6);
        } else {
            $newLat = round($curLat + ($dLat * 0.38), 6);
            $newLng = round($curLng + ($dLng * 0.38), 6);
        }

        $heading = round(rad2deg(atan2($newLng - $curLng, $newLat - $curLat)) + 360, 1) % 360;

        $agent->update([
            'current_latitude' => $newLat,
            'current_longitude' => $newLng,
        ]);

        LocationLog::create([
            'delivery_agent_id' => $agent->id,
            'delivery_job_id' => $activeJob?->id,
            'latitude' => $newLat,
            'longitude' => $newLng,
            'speed_kmh' => 27.5,
            'heading' => $heading,
            'recorded_at' => now(),
        ]);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'ok' => true,
                'latitude' => $newLat,
                'longitude' => $newLng,
                'delivery_job_id' => $activeJob?->id,
                'target_label' => $targetLabel,
            ]);
        }

        return back()->with('success', "Simulated GPS waypoint recorded toward {$targetLabel} ({$newLat}, {$newLng}).");
    }

    /**
     * Read-Only Deliveryman Profile (`GET /delivery/profile`)
     */
    public function profile(Request $request)
    {
        $agent = $this->resolveAuthenticatedAgent($request);
        if (!$agent) {
            return redirect('/delivery/login');
        }

        $user = $agent->user;

        return view('delivery.profile', compact('user', 'agent'));
    }

    /**
     * Re-send approved `@meramotbd.com` company email & system-generated password
     * from the Delivery Partner Login page (`POST /delivery/forgot-credentials`).
     */
    public function sendForgotCredentials(Request $request, GmailApiService $gmailService)
    {
        $validated = $request->validate([
            'lookup_email' => 'required|email|max:180',
        ], [
            'lookup_email.required' => 'Please enter your registered personal email or @meramotbd.com company email.',
            'lookup_email.email' => 'Please enter a valid email address.',
        ]);

        $lookup = strtolower(trim($validated['lookup_email']));

        $agent = DeliveryAgent::with('user')
            ->where(function ($query) use ($lookup) {
                $query->whereRaw('LOWER(personal_email) = ?', [$lookup])
                    ->orWhereRaw('LOWER(company_email) = ?', [$lookup])
                    ->orWhereHas('user', function ($q) use ($lookup) {
                        $q->whereRaw('LOWER(email) = ?', [$lookup])
                            ->whereIn('role', ['delivery_agent', 'delivery']);
                    });
            })
            ->first();

        if ($agent && strtoupper((string) $agent->registration_status) !== 'APPROVED') {
            $statusLabel = strtoupper((string) $agent->registration_status);
            throw ValidationException::withMessages([
                'lookup_email' => "Application status is {$statusLabel}. Credentials are issued upon approval — open Check Application Status below.",
            ]);
        }

        $maskedDestination = $lookup;

        if ($agent && strtoupper((string) $agent->registration_status) === 'APPROVED' && !empty($agent->company_email) && !empty($agent->company_password_vault)) {
            $recipientEmail = $agent->personal_email ?: ($agent->user?->email ?? '');
            $applicantName = $agent->applicant_name ?: ($agent->user?->name ?? 'Delivery Partner');

            try {
                $plainPassword = Crypt::decryptString($agent->company_password_vault);
                if ($recipientEmail !== '') {
                    $gmailService->sendDeliveryApprovalCredentials(
                        $recipientEmail,
                        $applicantName,
                        $agent->company_email,
                        $plainPassword,
                        true
                    );
                }
            } catch (\Throwable $e) {
                // Keep non-enumerating response consistent if decryption/mail transport fails
            }

            if ($recipientEmail !== '' && strtolower($recipientEmail) !== $lookup && str_contains($recipientEmail, '@')) {
                [$local, $domain] = explode('@', $recipientEmail, 2);
                $prefix = substr($local, 0, min(2, strlen($local)));
                $maskedDestination = $prefix . '***@' . $domain;
            } elseif ($recipientEmail !== '') {
                $maskedDestination = $recipientEmail;
            }
        }

        $message = "If an approved delivery partner account matches {$lookup}, credentials were sent to the registered personal email.";

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'destination' => $maskedDestination,
                'message' => $message,
            ]);
        }

        return redirect('/delivery/login')
            ->with('delivery_credentials_sent', $maskedDestination)
            ->with('success', $message);
    }
}

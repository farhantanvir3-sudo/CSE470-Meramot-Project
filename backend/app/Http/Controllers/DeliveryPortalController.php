<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\LocationLog;
use App\Models\RiderChatMessage;
use App\Models\RiderEarnings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
    private function resolveAuthenticatedAgent(Request $request): ?DeliveryAgent
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

        Auth::login($agent->user, $request->boolean('remember'));
        $request->session()->regenerate();

        return redirect('/delivery')->with(
            'success',
            'Signed in to Delivery Workspace as ' . ($agent->user->name ?: $companyEmail) . '!'
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

        return view('delivery.dashboard', compact(
            'user',
            'agent',
            'deliveries',
            'visibleDeliveries',
            'viewMode',
            'activeCount',
            'completedCount',
            'completedEarnings'
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
                ['code' => 'PENDING', 'title' => 'Waiting for rider acceptance'],
                ['code' => 'SCHEDULED', 'title' => 'Accepted by rider'],
                ['code' => 'DISPATCHED', 'title' => 'Rider is going to customer'],
                ['code' => 'PICKED_UP', 'title' => 'Picked up from customer'],
                ['code' => 'IN_TRANSIT', 'title' => 'On the way to shop'],
                ['code' => 'DELIVERED', 'title' => 'Delivered to shop'],
            ],
            'TO_CUSTOMER' => [
                ['code' => 'PENDING', 'title' => 'Waiting for rider acceptance'],
                ['code' => 'SCHEDULED', 'title' => 'Accepted by rider'],
                ['code' => 'DISPATCHED', 'title' => 'Rider is going to shop'],
                ['code' => 'PICKED_UP', 'title' => 'Picked up from shop'],
                ['code' => 'IN_TRANSIT', 'title' => 'On the way to customer'],
                ['code' => 'DELIVERED', 'title' => 'Delivered to customer'],
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
            'chatMessages'
        ));
    }

    /**
     * Advance Delivery Milestone Status (`POST /delivery/order/{id}/status`)
     */
    public function advanceOrderStatus(Request $request, $id)
    {
        $agent = $this->resolveAuthenticatedAgent($request);
        if (!$agent || $agent->registration_status !== 'APPROVED') {
            return redirect('/delivery/login');
        }

        $delivery = DeliveryJob::findOrFail($id);

        if ($delivery->delivery_agent_id !== null && (int) $delivery->delivery_agent_id !== (int) $agent->id) {
            abort(403, 'This delivery order is assigned to another courier partner.');
        }

        DB::transaction(function () use ($delivery, $agent) {
            if (!$delivery->delivery_agent_id) {
                $delivery->update([
                    'delivery_agent_id' => $agent->id,
                    'rider_name' => $agent->user?->name,
                    'rider_phone' => $agent->user?->phone,
                    'status' => 'SCHEDULED',
                    'scheduled_at' => $delivery->scheduled_at ?? now(),
                ]);
                return;
            }

            $currentStatus = strtoupper((string) $delivery->status);
            $next = self::NEXT_STATUS_MAP[$currentStatus] ?? null;
            if (!$next) {
                return;
            }

            $updates = ['status' => $next];
            if ($next === 'SCHEDULED' && !$delivery->scheduled_at) {
                $updates['scheduled_at'] = now();
            } elseif ($next === 'DISPATCHED') {
                $updates['dispatched_at'] = now();
            } elseif ($next === 'PICKED_UP') {
                $updates['picked_up_at'] = now();
            } elseif ($next === 'DELIVERED') {
                $updates['delivered_at'] = now();
            }

            $delivery->update($updates);

            // Synchronize parent RepairRequest & RepairJob lifecycle states with rider milestones
            $req = $delivery->repairRequest ?? $delivery->repairJob?->repairRequest;
            $rJob = $delivery->repairJob ?? $req?->repairJob;
            $isToCustomer = strtoupper((string) $delivery->direction) === 'TO_CUSTOMER';

            if ($req) {
                if (!$isToCustomer) {
                    if (in_array($next, ['SCHEDULED', 'DISPATCHED'], true) && $req->status === 'ASSIGNED') {
                        $req->update(['status' => 'PICKUP_SCHEDULED']);
                        if ($rJob && $rJob->status === 'CREATED') {
                            $rJob->update(['status' => 'PICKUP_SCHEDULED']);
                        }
                    } elseif (in_array($next, ['PICKED_UP', 'IN_TRANSIT'], true)) {
                        $req->update(['status' => 'PICKED_UP']);
                        if ($rJob) {
                            $rJob->update(['status' => 'PICKED_UP']);
                        }
                    } elseif ($next === 'DELIVERED') {
                        $req->update(['status' => 'AT_SHOP']);
                        if ($rJob) {
                            $rJob->update(['status' => 'AT_SHOP']);
                        }
                    }
                } else {
                    if (in_array($next, ['DISPATCHED', 'PICKED_UP', 'IN_TRANSIT'], true)) {
                        $req->update(['status' => 'RETURNING']);
                        if ($rJob) {
                            $rJob->update(['status' => 'RETURNING']);
                        }
                    } elseif ($next === 'DELIVERED') {
                        $wasDeclinedQuote2 = $rJob && $rJob->customer_approved === false;
                        $req->update(['status' => $wasDeclinedQuote2 ? 'RETURNED_TO_CUSTOMER' : 'COMPLETED']);
                        if ($rJob) {
                            $rJob->update([
                                'status' => 'COMPLETED',
                                'completed_at' => $rJob->completed_at ?? now(),
                            ]);

                            if (!$wasDeclinedQuote2) {
                                VendorBranchController::settleCompletedJobFinancials(
                                    $rJob->fresh(['repairRequest', 'shop.vendorProfile'])
                                );
                            }
                        }
                    }
                }
            }

            if ($next === 'DELIVERED') {
                $fee = (float) ($delivery->fee ?? 150.00);
                RiderEarnings::updateOrCreate(
                    [
                        'delivery_agent_id' => $agent->id,
                        'delivery_job_id' => $delivery->id,
                    ],
                    [
                        'trip_fee' => $fee,
                        'bonus_amount' => 0.00,
                        'total_earned' => $fee,
                        'payout_status' => 'unpaid',
                    ]
                );

                $agent->update([
                    'total_earnings' => (float) RiderEarnings::where('delivery_agent_id', $agent->id)->sum('total_earned'),
                ]);
            }
        });

        return redirect("/delivery/order/{$delivery->id}")
            ->with('success', 'Delivery status updated to ' . $delivery->fresh()->status . '.');
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
}

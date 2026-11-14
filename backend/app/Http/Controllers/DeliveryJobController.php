<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Services\DeliveryLifecycleService;
use Illuminate\Http\Request;

/**
 * ============================================================================
 * FEATURE R2: Delivery Job Queue & Status Milestone Synchronization
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Sprint: Sprint 2 (Nov 13 – Nov 27, 2026)
 * Architectural Layer: Controller (DeliveryJobController)
 * Models Touched: DeliveryJob, RepairRequest, RepairJob, RiderEarnings
 * View Rendered: frontend/views/delivery/jobs-queue.blade.php
 * ============================================================================
 */
class DeliveryJobController extends Controller
{
    public function __construct(private readonly DeliveryLifecycleService $lifecycle)
    {
    }

    /**
     * Display the Delivery Job Queue (open unassigned pool, active trips, and completed history).
     * Route: GET /delivery/jobs-queue | GET /api/delivery/jobs
     */
    public function index(Request $request)
    {
        $agent = app(DeliveryPortalController::class)->resolveAuthenticatedAgent($request);
        $user = $request->user();
        $isApprovedAgent = (bool) (
            $agent
            && strtoupper((string) $agent->registration_status) === 'APPROVED'
            && $agent->is_active
        );

        $tab = strtolower((string) $request->query('tab', 'all'));
        if (!in_array($tab, ['all', 'pool', 'active', 'completed'], true)) {
            $tab = 'all';
        }

        $search = trim((string) $request->query('q', ''));
        $directionFilter = strtoupper(trim((string) $request->query('direction', '')));
        $typeFilter = strtoupper(trim((string) $request->query('type', '')));

        $applyFilters = function ($query) use ($search, $directionFilter, $typeFilter) {
            if (in_array($directionFilter, ['TO_SHOP', 'TO_CUSTOMER'], true)) {
                $query->whereRaw('UPPER(direction) = ?', [$directionFilter]);
            }
            if (in_array($typeFilter, ['EXPRESS', 'REGULAR'], true)) {
                $query->whereRaw('UPPER(type) = ?', [$typeFilter]);
            }
            if ($search !== '') {
                $like = '%' . strtolower($search) . '%';
                $query->where(function ($sub) use ($like) {
                    $sub->whereRaw('LOWER(COALESCE(tracking_code, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(pickup_address, \'\')) LIKE ?', [$like])
                        ->orWhereRaw('LOWER(COALESCE(dropoff_address, \'\')) LIKE ?', [$like])
                        ->orWhereHas('repairRequest', function ($rq) use ($like) {
                            $rq->whereRaw('LOWER(COALESCE(title, \'\')) LIKE ?', [$like])
                                ->orWhereRaw('LOWER(COALESCE(tracking_code, \'\')) LIKE ?', [$like]);
                        });
                });
            }
            return $query;
        };

        $availableJobs = $applyFilters(
            DeliveryJob::with([
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
                })
        )
            ->latest()
            ->get();

        $activeJobs = $agent
            ? $applyFilters(
                DeliveryJob::with([
                    'repairRequest.customer',
                    'repairJob.shop',
                    'repairJob.repairRequest.customer',
                ])
                    ->where('delivery_agent_id', $agent->id)
                    ->whereIn('status', [
                        'PENDING',
                        'SCHEDULED',
                        'DISPATCHED',
                        'PICKED_UP',
                        'IN_TRANSIT',
                        'assigned',
                        'picked_up',
                        'in_transit',
                    ])
            )
                ->orderByDesc('updated_at')
                ->get()
            : collect();

        $completedJobs = $agent
            ? $applyFilters(
                DeliveryJob::with([
                    'repairRequest.customer',
                    'repairJob.shop',
                ])
                    ->where('delivery_agent_id', $agent->id)
                    ->where('status', 'DELIVERED')
            )
                ->latest('delivered_at')
                ->take(15)
                ->get()
            : collect();

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'is_approved_agent' => $isApprovedAgent,
                'available_jobs' => $availableJobs,
                'active_jobs' => $activeJobs,
                'completed_jobs' => $completedJobs,
            ]);
        }

        return view('delivery.jobs-queue', compact(
            'user',
            'agent',
            'isApprovedAgent',
            'tab',
            'search',
            'directionFilter',
            'typeFilter',
            'availableJobs',
            'activeJobs',
            'completedJobs'
        ));
    }

    /**
     * Atomically accept an unassigned delivery job into the courier's active queue.
     * Route: POST /delivery/jobs/{id}/claim
     */
    public function claimJob(Request $request, $id)
    {
        $agent = $this->requireApprovedAgent($request, 'Sign in with an approved @meramotbd.com courier account to accept jobs.');
        if ($agent instanceof \Illuminate\Http\Response || $agent instanceof \Illuminate\Http\JsonResponse || $agent instanceof \Illuminate\Http\RedirectResponse) {
            return $agent;
        }

        $delivery = $this->lifecycle->acceptJob((int) $id, $agent);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Delivery task accepted. Status synchronized to Pickup Scheduled.',
                'data' => $delivery,
            ]);
        }

        $redirectTo = (string) $request->input('redirect_to', '/delivery/jobs-queue?tab=active');
        $targetUrl = str_starts_with($redirectTo, '/delivery') ? $redirectTo : '/delivery/jobs-queue?tab=active';

        return redirect($targetUrl)->with(
            'success',
            'Job #' . ($delivery->tracking_code ?: $delivery->id) . ' accepted — status updated to Pickup Scheduled.'
        );
    }

    /**
     * Advance delivery milestone with mandatory handover OTP verification and end-to-end sync.
     * Route: POST /delivery/jobs/{id}/milestone
     */
    public function updateMilestone(Request $request, $id)
    {
        $agent = $this->requireApprovedAgent($request, 'Sign in with an approved @meramotbd.com courier account to update milestones.');
        if ($agent instanceof \Illuminate\Http\Response || $agent instanceof \Illuminate\Http\JsonResponse || $agent instanceof \Illuminate\Http\RedirectResponse) {
            return $agent;
        }

        $validated = $request->validate([
            'milestone' => 'nullable|string|max:40',
            'otp' => 'nullable|string|max:10',
        ]);

        $delivery = $this->lifecycle->advanceMilestone(
            (int) $id,
            $agent,
            $validated['milestone'] ?? null,
            $validated['otp'] ?? null,
            true
        );

        $label = DeliveryLifecycleService::milestoneLabel($delivery);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => "Milestone updated to {$label}.",
                'milestone_label' => $label,
                'data' => $delivery,
            ]);
        }

        return back()->with(
            'success',
            'Trip #' . ($delivery->tracking_code ?: $delivery->id) . " updated to {$label}."
        );
    }

    /**
     * Release a claimed trip back to the open pool before physical handover.
     * Route: POST /delivery/jobs/{id}/release
     */
    public function releaseJob(Request $request, $id)
    {
        $agent = $this->requireApprovedAgent($request, 'Sign in with an approved @meramotbd.com courier account to release trips.');
        if ($agent instanceof \Illuminate\Http\Response || $agent instanceof \Illuminate\Http\JsonResponse || $agent instanceof \Illuminate\Http\RedirectResponse) {
            return $agent;
        }

        $validated = $request->validate([
            'reason' => 'nullable|string|max:250',
        ]);

        $delivery = $this->lifecycle->releaseJob((int) $id, $agent, $validated['reason'] ?? null);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Trip released back to the open dispatch pool.',
                'data' => $delivery,
            ]);
        }

        return redirect('/delivery/jobs-queue?tab=pool')->with(
            'success',
            'Trip #' . ($delivery->tracking_code ?: $delivery->id) . ' released back to the open dispatch pool.'
        );
    }

    /**
     * Report a field exception (customer unreachable, traffic delay, address mismatch) to Admin Dispatch.
     * Route: POST /delivery/jobs/{id}/exception
     */
    public function reportException(Request $request, $id)
    {
        $agent = $this->requireApprovedAgent($request, 'Sign in with an approved @meramotbd.com courier account to report trip exceptions.');
        if ($agent instanceof \Illuminate\Http\Response || $agent instanceof \Illuminate\Http\JsonResponse || $agent instanceof \Illuminate\Http\RedirectResponse) {
            return $agent;
        }

        $validated = $request->validate([
            'reason' => 'required|string|min:5|max:400',
        ]);

        $msg = $this->lifecycle->reportException((int) $id, $agent, $validated['reason']);

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'status' => 'success',
                'message' => 'Field exception logged to Logistics Operations.',
                'data' => $msg,
            ]);
        }

        return back()->with('success', 'Field exception sent to Meramot Logistics Admin dispatch chat.');
    }

    private function requireApprovedAgent(Request $request, string $unauthMessage): DeliveryAgent|\Illuminate\Http\JsonResponse|\Illuminate\Http\RedirectResponse
    {
        $agent = app(DeliveryPortalController::class)->resolveAuthenticatedAgent($request);

        if (
            !$agent
            || strtoupper((string) $agent->registration_status) !== 'APPROVED'
            || !$agent->is_active
        ) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'status' => 'error',
                    'message' => $unauthMessage,
                ], 403);
            }

            return redirect('/delivery/login')->with('warning', $unauthMessage);
        }

        return $agent;
    }
}

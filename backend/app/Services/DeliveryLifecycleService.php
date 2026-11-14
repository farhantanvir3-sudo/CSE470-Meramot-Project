<?php

namespace App\Services;

use App\Http\Controllers\VendorBranchController;
use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\RiderEarnings;
use App\Models\UserNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Centralized lifecycle coordinator for courier job creation, atomic job acceptance,
 * OTP-verified handover progression, parent RepairRequest/RepairJob sync,
 * rider earnings settlement, and in-app notifications.
 */
class DeliveryLifecycleService
{
    public const ACTIVE_STATUSES = [
        'PENDING',
        'SCHEDULED',
        'DISPATCHED',
        'PICKED_UP',
        'IN_TRANSIT',
    ];

    public const OPEN_POOL_STATUSES = [
        'PENDING',
        'unassigned',
        'UNASSIGNED',
    ];

    public const NEXT_STATUS_MAP = [
        'PENDING' => 'SCHEDULED',
        'UNASSIGNED' => 'SCHEDULED',
        'SCHEDULED' => 'DISPATCHED',
        'DISPATCHED' => 'PICKED_UP',
        'PICKED_UP' => 'IN_TRANSIT',
        'IN_TRANSIT' => 'DELIVERED',
        'DELIVERED' => null,
        'FAILED' => null,
        'CANCELLED' => null,
    ];

    private const MILESTONE_ALIASES = [
        'SCHEDULED' => 'SCHEDULED',
        'PICKUP_SCHEDULED' => 'SCHEDULED',
        'ACCEPTED' => 'SCHEDULED',
        'ASSIGNED' => 'SCHEDULED',
        'DISPATCHED' => 'DISPATCHED',
        'PICKED_UP' => 'PICKED_UP',
        'IN_TRANSIT' => 'IN_TRANSIT',
        'OUT_FOR_DELIVERY' => 'IN_TRANSIT',
        'AT_SHOP' => 'DELIVERED',
        'DELIVERED' => 'DELIVERED',
    ];

    /**
     * Resolve concise, customer- and courier-friendly canonical milestone label.
     */
    public static function milestoneLabel(DeliveryJob $job): string
    {
        $status = strtoupper((string) $job->status);
        $isToCustomer = strtoupper((string) $job->direction) === 'TO_CUSTOMER';

        if (in_array($status, ['PENDING', 'UNASSIGNED'], true)) {
            return 'Pending Acceptance';
        }
        if ($status === 'CANCELLED') {
            return 'Cancelled';
        }
        if ($status === 'FAILED') {
            return 'Delivery Failed';
        }

        if ($isToCustomer) {
            return match ($status) {
                'SCHEDULED' => 'Pickup Scheduled',
                'DISPATCHED' => 'En Route to Shop',
                'PICKED_UP' => 'Picked Up from Shop',
                'IN_TRANSIT' => 'Out for Delivery',
                'DELIVERED' => 'Delivered',
                default => ucwords(strtolower(str_replace('_', ' ', $status))),
            };
        }

        return match ($status) {
            'SCHEDULED' => 'Pickup Scheduled',
            'DISPATCHED' => 'En Route to Customer',
            'PICKED_UP' => 'Picked Up',
            'IN_TRANSIT' => 'En Route to Shop',
            'DELIVERED' => 'At Shop',
            default => ucwords(strtolower(str_replace('_', ' ', $status))),
        };
    }

    /**
     * Build the 4 concise canonical stages displayed across queue & customer tracking cards.
     *
     * @return array<int, array{key: string, label: string, state: string}>
     */
    public static function canonicalStages(DeliveryJob $job): array
    {
        $status = strtoupper((string) $job->status);
        $isToCustomer = strtoupper((string) $job->direction) === 'TO_CUSTOMER';

        $stageDefinitions = $isToCustomer
            ? [
                ['key' => 'SCHEDULED', 'label' => 'Pickup Scheduled'],
                ['key' => 'PICKED_UP', 'label' => 'Picked Up'],
                ['key' => 'OUT_FOR_DELIVERY', 'label' => 'Out for Delivery'],
                ['key' => 'DELIVERED', 'label' => 'Delivered'],
            ]
            : [
                ['key' => 'SCHEDULED', 'label' => 'Pickup Scheduled'],
                ['key' => 'PICKED_UP', 'label' => 'Picked Up'],
                ['key' => 'IN_TRANSIT', 'label' => 'En Route to Shop'],
                ['key' => 'AT_SHOP', 'label' => 'At Shop'],
            ];

        $currentTier = match ($status) {
            'SCHEDULED', 'DISPATCHED' => 0,
            'PICKED_UP' => 1,
            'IN_TRANSIT' => 2,
            'DELIVERED' => 3,
            default => -1,
        };

        $stages = [];
        foreach ($stageDefinitions as $idx => $def) {
            if ($status === 'DELIVERED' || $idx < $currentTier) {
                $state = 'done';
            } elseif ($idx === $currentTier) {
                $state = 'current';
            } else {
                $state = 'upcoming';
            }

            $stages[] = [
                'key' => $def['key'],
                'label' => $def['label'],
                'state' => $state,
            ];
        }

        return $stages;
    }

    /**
     * Resolve the next interactive queue action for an active delivery task.
     *
     * @return array{target: string, label: string, requires_otp: bool, otp_prompt: ?string, demo_otp: ?string}|null
     */
    public static function nextQueueAction(DeliveryJob $job): ?array
    {
        $status = strtoupper((string) $job->status);
        $isToCustomer = strtoupper((string) $job->direction) === 'TO_CUSTOMER';

        if (!$isToCustomer) {
            return match ($status) {
                'SCHEDULED' => [
                    'target' => 'DISPATCHED',
                    'label' => 'Start Pickup Route',
                    'requires_otp' => false,
                    'otp_prompt' => null,
                    'demo_otp' => null,
                ],
                'DISPATCHED' => [
                    'target' => 'PICKED_UP',
                    'label' => 'Confirm Pickup',
                    'requires_otp' => !empty($job->pickup_otp),
                    'otp_prompt' => 'Customer 4-digit pickup code',
                    'demo_otp' => $job->pickup_otp,
                ],
                'PICKED_UP' => [
                    'target' => 'IN_TRANSIT',
                    'label' => 'En Route to Shop',
                    'requires_otp' => false,
                    'otp_prompt' => null,
                    'demo_otp' => null,
                ],
                'IN_TRANSIT' => [
                    'target' => 'AT_SHOP',
                    'label' => 'Confirm At Shop',
                    'requires_otp' => !empty($job->dropoff_otp),
                    'otp_prompt' => 'Workshop 4-digit check-in code',
                    'demo_otp' => $job->dropoff_otp,
                ],
                default => null,
            };
        }

        return match ($status) {
            'SCHEDULED' => [
                'target' => 'DISPATCHED',
                'label' => 'Head to Workshop',
                'requires_otp' => false,
                'otp_prompt' => null,
                'demo_otp' => null,
            ],
            'DISPATCHED' => [
                'target' => 'PICKED_UP',
                'label' => 'Confirm Shop Pickup',
                'requires_otp' => !empty($job->pickup_otp),
                'otp_prompt' => 'Workshop 4-digit handover code',
                'demo_otp' => $job->pickup_otp,
            ],
            'PICKED_UP' => [
                'target' => 'OUT_FOR_DELIVERY',
                'label' => 'Mark Out for Delivery',
                'requires_otp' => false,
                'otp_prompt' => null,
                'demo_otp' => null,
            ],
            'IN_TRANSIT' => [
                'target' => 'DELIVERED',
                'label' => 'Confirm Delivered',
                'requires_otp' => !empty($job->dropoff_otp),
                'otp_prompt' => 'Customer 4-digit delivery code',
                'demo_otp' => $job->dropoff_otp,
            ],
            default => null,
        };
    }

    /**
     * Create or return a pending unassigned DeliveryJob for inbound (`TO_SHOP`) or outbound (`TO_CUSTOMER`) logistics.
     */
    public function ensureDeliveryJob(RepairRequest $repairRequest, RepairJob $job, string $direction): DeliveryJob
    {
        $normalizedDirection = strtoupper($direction) === 'TO_CUSTOMER' ? 'TO_CUSTOMER' : 'TO_SHOP';
        $isToShop = $normalizedDirection === 'TO_SHOP';

        $shop = RepairShop::find($job->shop_id);
        $shopAddress = $shop?->public_address
            ?: $shop?->address
            ?: (($shop?->area ?: 'Banani') . ', ' . ($shop?->city ?: 'Dhaka'));
        $customerAddress = $repairRequest->pickup_address ?: 'Banani, Dhaka';

        $deliveryType = strtoupper((string) ($repairRequest->delivery_type ?? 'REGULAR')) === 'EXPRESS'
            ? 'EXPRESS'
            : 'REGULAR';
        $regularFee = ($shop && (float) $shop->pickup_fee > 0) ? (float) $shop->pickup_fee : 120.00;
        $fee = $deliveryType === 'EXPRESS' ? 240.00 : $regularFee;

        return DeliveryJob::firstOrCreate(
            [
                'repair_request_id' => $repairRequest->id,
                'direction' => $normalizedDirection,
            ],
            [
                'repair_job_id' => $job->id,
                'delivery_agent_id' => null,
                'type' => $deliveryType,
                'status' => 'PENDING',
                'partner_name' => 'Meramot Logistics',
                'tracking_code' => 'MRM-' . ($isToShop ? 'PKP' : 'RTN') . '-' . $repairRequest->id . '-' . strtoupper(Str::random(4)),
                'rider_name' => null,
                'rider_phone' => null,
                'pickup_address' => $isToShop ? $customerAddress : $shopAddress,
                'dropoff_address' => $isToShop ? $shopAddress : $customerAddress,
                'pickup_otp' => (string) random_int(1000, 9999),
                'dropoff_otp' => (string) random_int(1000, 9999),
                'distance_km' => 4.20,
                'fee' => $fee,
                'scheduled_at' => null,
                'milestone_timestamps' => [
                    'PENDING' => now()->toIso8601String(),
                ],
            ]
        );
    }

    /**
     * Atomically accept a pending delivery task and synchronize Pickup Scheduled state.
     */
    public function acceptJob(int $deliveryJobId, DeliveryAgent $agent): DeliveryJob
    {
        return DB::transaction(function () use ($deliveryJobId, $agent) {
            $delivery = DeliveryJob::with([
                'repairRequest.customer',
                'repairJob.shop.vendorProfile',
            ])->where('id', $deliveryJobId)->lockForUpdate()->firstOrFail();

            if ($delivery->delivery_agent_id !== null && (int) $delivery->delivery_agent_id !== (int) $agent->id) {
                throw ValidationException::withMessages([
                    'job' => 'This delivery task has already been accepted by another courier.',
                ]);
            }

            $currentStatus = strtoupper((string) $delivery->status);
            if (!in_array($currentStatus, ['PENDING', 'UNASSIGNED'], true)) {
                throw ValidationException::withMessages([
                    'job' => 'This delivery task is no longer open for acceptance.',
                ]);
            }

            $agent->loadMissing('user');
            $now = now();
            $timestamps = is_array($delivery->milestone_timestamps) ? $delivery->milestone_timestamps : [];
            $timestamps['SCHEDULED'] = $now->toIso8601String();

            $delivery->update([
                'delivery_agent_id' => $agent->id,
                'rider_name' => $agent->user?->name ?: $agent->applicant_name,
                'rider_phone' => $agent->user?->phone ?: $agent->applicant_phone,
                'status' => 'SCHEDULED',
                'scheduled_at' => $delivery->scheduled_at ?? $now,
                'milestone_timestamps' => $timestamps,
            ]);

            $agent->update(['status' => 'BUSY']);

            $req = $delivery->repairRequest ?? $delivery->repairJob?->repairRequest;
            $rJob = $delivery->repairJob ?? $req?->repairJob;
            $isToCustomer = strtoupper((string) $delivery->direction) === 'TO_CUSTOMER';

            if ($req) {
                if (!$isToCustomer) {
                    if (in_array(strtoupper((string) $req->status), ['ASSIGNED', 'PENDING', 'BIDDING'], true)) {
                        $req->update(['status' => 'PICKUP_SCHEDULED']);
                    }
                    if ($rJob && in_array(strtoupper((string) $rJob->status), ['CREATED', 'ASSIGNED'], true)) {
                        $rJob->update(['status' => 'PICKUP_SCHEDULED']);
                    }
                } else {
                    if (!in_array(strtoupper((string) $req->status), ['RETURN_SCHEDULED', 'RETURNING', 'COMPLETED', 'RETURNED_TO_CUSTOMER'], true)) {
                        $req->update(['status' => 'RETURN_SCHEDULED']);
                    }
                    if ($rJob && !in_array(strtoupper((string) $rJob->status), ['READY_TO_RETURN', 'RETURNING', 'COMPLETED'], true)) {
                        $rJob->update(['status' => 'READY_TO_RETURN']);
                    }
                }

                $courierName = $delivery->rider_name ?: 'Meramot Courier';
                $code = $delivery->tracking_code ?: ('Trip #' . $delivery->id);

                $this->notifyUser(
                    $req->customer_id,
                    'delivery_milestone',
                    'info',
                    $isToCustomer ? 'Return Pickup Scheduled' : 'Pickup Scheduled',
                    $isToCustomer
                        ? "{$courierName} ({$code}) accepted your return delivery and is heading to the workshop."
                        : "{$courierName} ({$code}) accepted your pickup request. Have pickup code {$delivery->pickup_otp} ready.",
                    '/requests/' . $req->id . '/track'
                );
            }

            return $delivery->fresh(['repairRequest.customer', 'repairJob.shop.vendorProfile', 'deliveryAgent.user']);
        });
    }

    /**
     * Advance delivery milestone status with OTP validation and full RepairRequest/RepairJob/Finance sync.
     */
    public function advanceMilestone(
        int $deliveryJobId,
        DeliveryAgent $agent,
        ?string $targetMilestone = null,
        ?string $otp = null,
        bool $requireHandoverOtp = false
    ): DeliveryJob {
        return DB::transaction(function () use ($deliveryJobId, $agent, $targetMilestone, $otp, $requireHandoverOtp) {
            $delivery = DeliveryJob::with([
                'repairRequest.customer',
                'repairJob.shop.vendorProfile',
            ])->where('id', $deliveryJobId)->lockForUpdate()->firstOrFail();

            if ($delivery->delivery_agent_id !== null && (int) $delivery->delivery_agent_id !== (int) $agent->id) {
                abort(403, 'This delivery order is assigned to another courier partner.');
            }

            $currentStatus = strtoupper((string) $delivery->status);
            $requestedTarget = $targetMilestone ? strtoupper(trim($targetMilestone)) : null;

            // Allow unassigned claim via generic progression if no target or SCHEDULED requested
            if (!$delivery->delivery_agent_id && in_array($currentStatus, ['PENDING', 'UNASSIGNED'], true)) {
                if ($requestedTarget === null || in_array($requestedTarget, ['SCHEDULED', 'PICKUP_SCHEDULED', 'ACCEPTED', 'ASSIGNED'], true)) {
                    return $this->acceptJob($deliveryJobId, $agent);
                }

                throw ValidationException::withMessages([
                    'job' => 'Please accept this job before updating pickup or dropoff milestones.',
                ]);
            }

            $isToCustomer = strtoupper((string) $delivery->direction) === 'TO_CUSTOMER';

            if ($requestedTarget !== null) {
                if ($requestedTarget === 'AT_SHOP' && $isToCustomer) {
                    throw ValidationException::withMessages([
                        'milestone' => 'At Shop milestone applies only to inbound workshop pickups.',
                    ]);
                }

                $next = self::MILESTONE_ALIASES[$requestedTarget] ?? null;
                if (!$next) {
                    throw ValidationException::withMessages([
                        'milestone' => 'Unsupported delivery milestone selected.',
                    ]);
                }

                if ($next === $currentStatus) {
                    return $delivery;
                }

                $allowedTargets = match ($currentStatus) {
                    'PENDING', 'UNASSIGNED' => ['SCHEDULED'],
                    'SCHEDULED' => ['DISPATCHED', 'PICKED_UP'],
                    'DISPATCHED' => ['PICKED_UP'],
                    'PICKED_UP' => $isToCustomer ? ['IN_TRANSIT', 'DELIVERED'] : ['IN_TRANSIT', 'DELIVERED'],
                    'IN_TRANSIT' => ['DELIVERED'],
                    default => [],
                };

                if (!in_array($next, $allowedTargets, true)) {
                    throw ValidationException::withMessages([
                        'milestone' => "Cannot transition from " . self::milestoneLabel($delivery) . " directly to {$requestedTarget}.",
                    ]);
                }
            } else {
                $next = self::NEXT_STATUS_MAP[$currentStatus] ?? null;
                if (!$next) {
                    return $delivery;
                }
            }

            // Enforce 4-digit handover OTP verification on physical custody changes when required or supplied
            if ($next === 'PICKED_UP' && !empty($delivery->pickup_otp) && ($requireHandoverOtp || $otp !== null)) {
                if (trim((string) $otp) !== trim((string) $delivery->pickup_otp)) {
                    throw ValidationException::withMessages([
                        'otp' => 'Invalid 4-digit pickup code. Confirm the handover code before marking picked up.',
                    ]);
                }
            }

            if ($next === 'DELIVERED' && !empty($delivery->dropoff_otp) && ($requireHandoverOtp || $otp !== null)) {
                if (trim((string) $otp) !== trim((string) $delivery->dropoff_otp)) {
                    throw ValidationException::withMessages([
                        'otp' => 'Invalid 4-digit dropoff code. Confirm the recipient handover code before completing delivery.',
                    ]);
                }
            }

            $now = now();
            $timestamps = is_array($delivery->milestone_timestamps) ? $delivery->milestone_timestamps : [];
            $timestamps[$next] = $now->toIso8601String();

            $updates = [
                'status' => $next,
                'milestone_timestamps' => $timestamps,
            ];

            if ($next === 'SCHEDULED' && !$delivery->scheduled_at) {
                $updates['scheduled_at'] = $now;
            } elseif ($next === 'DISPATCHED') {
                $updates['dispatched_at'] = $delivery->dispatched_at ?? $now;
            } elseif ($next === 'PICKED_UP') {
                $updates['dispatched_at'] = $delivery->dispatched_at ?? $now;
                $updates['picked_up_at'] = $delivery->picked_up_at ?? $now;
            } elseif ($next === 'IN_TRANSIT') {
                $updates['dispatched_at'] = $delivery->dispatched_at ?? $now;
                $updates['picked_up_at'] = $delivery->picked_up_at ?? $now;
            } elseif ($next === 'DELIVERED') {
                $updates['delivered_at'] = $delivery->delivered_at ?? $now;
            }

            $delivery->update($updates);

            $this->syncParentLifecycle($delivery, $next, $isToCustomer, $now);

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

            $hasRemainingActiveTrips = DeliveryJob::where('delivery_agent_id', $agent->id)
                ->whereIn('status', self::ACTIVE_STATUSES)
                ->exists();
            $agent->update(['status' => $hasRemainingActiveTrips ? 'BUSY' : 'AVAILABLE']);

            return $delivery->fresh(['repairRequest.customer', 'repairJob.shop.vendorProfile', 'deliveryAgent.user']);
        });
    }

    /**
     * Keep parent RepairRequest, RepairJob, escrow financials, and notifications synchronized.
     */
    private function syncParentLifecycle(DeliveryJob $delivery, string $next, bool $isToCustomer, $now): void
    {
        $req = $delivery->repairRequest ?? $delivery->repairJob?->repairRequest;
        $rJob = $delivery->repairJob ?? $req?->repairJob;
        if (!$req) {
            return;
        }

        $vendorUserId = $rJob?->shop?->vendorProfile?->user_id;
        $shopName = $rJob?->shop?->name ?? 'the workshop';
        $code = $delivery->tracking_code ?: ('Trip #' . $delivery->id);

        if (!$isToCustomer) {
            if (in_array($next, ['SCHEDULED', 'DISPATCHED'], true) && strtoupper((string) $req->status) === 'ASSIGNED') {
                $req->update(['status' => 'PICKUP_SCHEDULED']);
                if ($rJob && in_array(strtoupper((string) $rJob->status), ['CREATED', 'ASSIGNED'], true)) {
                    $rJob->update(['status' => 'PICKUP_SCHEDULED']);
                }
            } elseif (in_array($next, ['PICKED_UP', 'IN_TRANSIT'], true)) {
                $req->update(['status' => 'PICKED_UP']);
                if ($rJob) {
                    $rJob->update(['status' => 'PICKED_UP']);
                }
                if ($next === 'PICKED_UP') {
                    $this->notifyUser(
                        $req->customer_id,
                        'delivery_milestone',
                        'info',
                        'Device Picked Up',
                        "Courier picked up your device ({$code}) and is heading to {$shopName}.",
                        '/requests/' . $req->id . '/track'
                    );
                }
            } elseif ($next === 'DELIVERED') {
                $req->update(['status' => 'AT_SHOP']);
                if ($rJob) {
                    $rJob->update(['status' => 'AT_SHOP']);
                }

                $this->notifyUser(
                    $req->customer_id,
                    'delivery_milestone',
                    'success',
                    'Device Arrived at Shop',
                    "Your device ({$code}) has safely checked in at {$shopName} for inspection.",
                    '/requests/' . $req->id . '/track'
                );

                $this->notifyUser(
                    $vendorUserId,
                    'delivery_milestone',
                    'info',
                    'Device Checked In at Shop',
                    "Courier handed over Request #{$req->id} ({$req->title}) at your reception bench.",
                    '/vendor/dashboard?tab=assigned_jobs'
                );
            }

            return;
        }

        if (in_array($next, ['SCHEDULED'], true)) {
            $req->update(['status' => 'RETURN_SCHEDULED']);
            if ($rJob) {
                $rJob->update(['status' => 'READY_TO_RETURN']);
            }
        } elseif (in_array($next, ['DISPATCHED', 'PICKED_UP', 'IN_TRANSIT'], true)) {
            $req->update(['status' => 'RETURNING']);
            if ($rJob) {
                $rJob->update(['status' => 'RETURNING']);
            }

            if ($next === 'IN_TRANSIT') {
                $this->notifyUser(
                    $req->customer_id,
                    'delivery_milestone',
                    'info',
                    'Out for Delivery',
                    "Your repaired device ({$code}) is out for delivery. Share code {$delivery->dropoff_otp} on arrival.",
                    '/requests/' . $req->id . '/track'
                );
            }
        } elseif ($next === 'DELIVERED') {
            $wasDeclinedQuote2 = $rJob && $rJob->customer_approved === false;
            $req->update(['status' => $wasDeclinedQuote2 ? 'RETURNED_TO_CUSTOMER' : 'COMPLETED']);

            if ($rJob) {
                $rJob->update([
                    'status' => 'COMPLETED',
                    'completed_at' => $rJob->completed_at ?? $now,
                ]);

                if (!$wasDeclinedQuote2) {
                    VendorBranchController::settleCompletedJobFinancials(
                        $rJob->fresh(['repairRequest', 'shop.vendorProfile'])
                    );
                }
            }

            $this->notifyUser(
                $req->customer_id,
                'delivery_milestone',
                'success',
                'Device Delivered',
                $wasDeclinedQuote2
                    ? "Your un-repaired device ({$code}) has been returned and signed off."
                    : "Your repaired device ({$code}) has been delivered and your warranty & invoice are ready.",
                '/requests/' . $req->id . '/track'
            );

            $this->notifyUser(
                $vendorUserId,
                'delivery_milestone',
                'success',
                'Return Delivery Completed',
                "Request #{$req->id} ({$code}) was delivered to the customer and order records updated.",
                '/vendor/dashboard?tab=assigned_jobs'
            );
        }
    }

    private function notifyUser(?int $userId, string $type, string $severity, string $title, string $message, string $actionUrl): void
    {
        if (!$userId) {
            return;
        }

        UserNotification::create([
            'user_id' => $userId,
            'type' => $type,
            'severity' => $severity,
            'title' => $title,
            'message' => $message,
            'action_url' => $actionUrl,
            'is_read' => false,
        ]);
    }
}

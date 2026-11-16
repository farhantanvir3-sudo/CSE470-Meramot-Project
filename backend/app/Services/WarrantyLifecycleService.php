<?php

namespace App\Services;

use App\Http\Controllers\AdminLedgerController;
use App\Models\AuditLog;
use App\Models\IssuedWarranty;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\SparePartListing;
use App\Models\SparePartWarranty;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WarrantyClaim;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * End-to-end warranty lifecycle coordinator:
 * 1) Separate spare-part warranty registry (`spare_part_warranties`)
 * 2) Order-completion certificate issuance (`issued_warranties`)
 * 3) Repeatable multi-claim submission with image evidence & anti-misuse scoring
 * 4) Admin assessment: Replacement / Free Repair / Refund / Reject (with explanation + image proof)
 */
class WarrantyLifecycleService
{
    public const DEFAULT_SPARE_PART_TERMS = 'Covers manufacturing defects, premature hardware failure, and functional faults within the warranty window; excludes physical impact or liquid damage.';

    public function syncListingWarranty(
        SparePartListing $listing,
        bool $hasWarranty,
        int $durationDays,
        string $coverageType = SparePartWarranty::COVERAGE_FULL_PROTECTION,
        ?string $terms = null
    ): ?SparePartWarranty {
        if (!Schema::hasTable('spare_part_warranties')) {
            return null;
        }

        $cleanDays = $hasWarranty ? max(0, min(1095, $durationDays)) : 0;
        if (!$hasWarranty || $cleanDays <= 0) {
            SparePartWarranty::where('spare_part_listing_id', $listing->id)->delete();
            if ((int) $listing->warranty_days !== 0) {
                $listing->update(['warranty_days' => 0]);
            }
            return null;
        }

        $normType = SparePartWarranty::normalizeCoverageType($coverageType);
        $meta = SparePartWarranty::metaForType($normType);
        $cleanTerms = trim((string) $terms) !== ''
            ? Str::limit(trim((string) $terms), 1000, '')
            : self::DEFAULT_SPARE_PART_TERMS;

        if ((int) $listing->warranty_days !== $cleanDays) {
            $listing->update(['warranty_days' => $cleanDays]);
        }

        return SparePartWarranty::updateOrCreate(
            ['spare_part_listing_id' => $listing->id],
            [
                'shop_id' => $listing->shop_id,
                'vendor_profile_id' => $listing->vendor_profile_id,
                'duration_days' => $cleanDays,
                'coverage_type' => $normType,
                'covers_refund' => $meta['covers_refund'],
                'covers_replacement' => $meta['covers_replacement'],
                'covers_free_repair' => $meta['covers_free_repair'],
                'terms' => $cleanTerms,
            ]
        );
    }

    /**
     * Issue `issued_warranties` records for a completed RepairJob/order idempotently.
     *
     * @return array<int, IssuedWarranty>
     */
    public function issueOrderWarrantiesForCompletedJob(RepairJob $job): array
    {
        if (!Schema::hasTable('issued_warranties')) {
            return [];
        }

        $job->loadMissing(['repairRequest', 'shop', 'acceptedBid']);
        $req = $job->repairRequest;
        if (!$req) {
            return [];
        }

        $startsAt = $job->completed_at ?? $job->updated_at ?? now();
        $items = is_array($job->final_quote_items) ? $job->final_quote_items : [];
        $issued = [];
        $hasSparePartItems = false;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (strtoupper((string) ($item['source'] ?? '')) !== 'SPARE_PART' || empty($item['spare_part_id'])) {
                continue;
            }
            $hasSparePartItems = true;
            $listingId = (int) $item['spare_part_id'];
            $rawSpec = trim((string) ($item['spec_code'] ?? ''));
            $specNorm = $rawSpec !== '' ? strtolower($rawSpec) : '__default';
            $lineKey = "sp:{$listingId}:spec:{$specNorm}";

            $listing = SparePartListing::with('warrantyCoverage')->find($listingId);
            $spWarranty = $listing?->warrantyCoverage;

            // Honor purchase-time warranty snapshot if captured at cart/checkout; fallback to separate warranty registry
            if (array_key_exists('has_warranty', $item) && !$item['has_warranty']) {
                $days = 0;
            } elseif (isset($item['warranty_days']) && is_numeric($item['warranty_days'])) {
                $days = max(0, (int) $item['warranty_days']);
            } elseif ($spWarranty) {
                $days = max(0, (int) $spWarranty->duration_days);
            } elseif (isset($item['warranty_months']) && (int) $item['warranty_months'] > 0) {
                $days = (int) $item['warranty_months'] * 30;
            } elseif ($listing && (int) $listing->warranty_days > 0) {
                $days = (int) $listing->warranty_days;
            } else {
                $days = 0;
            }

            if ($days <= 0) {
                continue;
            }

            $coverageType = SparePartWarranty::normalizeCoverageType(
                $item['warranty_coverage_type'] ?? $spWarranty?->coverage_type ?? SparePartWarranty::COVERAGE_FULL_PROTECTION
            );
            $terms = trim((string) ($item['warranty_terms'] ?? $spWarranty?->terms ?? self::DEFAULT_SPARE_PART_TERMS));
            $expiresAt = $startsAt->copy()->addDays($days);

            $issued[] = $this->createIssuedWarrantyIfMissing([
                'repair_request_id' => $req->id,
                'repair_job_id' => $job->id,
                'customer_id' => $req->customer_id,
                'shop_id' => $job->shop_id ?? $req->requested_shop_id,
                'spare_part_listing_id' => $listing?->id,
                'spare_part_warranty_id' => $spWarranty?->id,
                'line_key' => $lineKey,
                'spec_code' => $rawSpec !== '' ? $rawSpec : null,
                'item_title' => Str::limit((string) ($item['title'] ?? $listing?->title ?? 'Spare Part'), 220, ''),
                'item_type' => 'SPARE_PART',
                'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
                'unit_price' => max(0, round((float) ($item['part_unit_price'] ?? $item['price'] ?? 0), 2)),
                'coverage_type' => $coverageType,
                'terms' => $terms,
                'duration_days' => $days,
                'starts_at' => $startsAt,
                'expires_at' => $expiresAt,
                'status' => now()->lte($expiresAt) ? IssuedWarranty::STATUS_ACTIVE : IssuedWarranty::STATUS_EXPIRED,
            ]);
        }

        if (!$hasSparePartItems && $job->hasWarrantyCoverage()) {
            $days = $job->resolvedWarrantyDays();
            if ($days > 0) {
                $lineKey = "job:{$job->id}";
                $expiresAt = $startsAt->copy()->addDays($days);
                $issued[] = $this->createIssuedWarrantyIfMissing([
                    'repair_request_id' => $req->id,
                    'repair_job_id' => $job->id,
                    'customer_id' => $req->customer_id,
                    'shop_id' => $job->shop_id ?? $req->requested_shop_id,
                    'spare_part_listing_id' => null,
                    'spare_part_warranty_id' => null,
                    'line_key' => $lineKey,
                    'spec_code' => null,
                    'item_title' => Str::limit((string) ($req->title ?: 'Workshop Repair Warranty'), 220, ''),
                    'item_type' => 'REPAIR_SERVICE',
                    'quantity' => 1,
                    'unit_price' => max(0, round((float) ($job->final_quoted_amount ?? $req->quoted_final_amount ?? 0), 2)),
                    'coverage_type' => SparePartWarranty::COVERAGE_FULL_PROTECTION,
                    'terms' => "Covers replaced hardware components and bench workmanship for {$days} days from completion.",
                    'duration_days' => $days,
                    'starts_at' => $startsAt,
                    'expires_at' => $expiresAt,
                    'status' => now()->lte($expiresAt) ? IssuedWarranty::STATUS_ACTIVE : IssuedWarranty::STATUS_EXPIRED,
                ]);
            }
        }

        return $issued;
    }

    public function ensureCompletedOrderWarrantiesForCustomer(int $customerId): void
    {
        if (!Schema::hasTable('issued_warranties')) {
            return;
        }

        $completedReqs = RepairRequest::with(['repairJob.acceptedBid', 'repairJob.shop', 'requestedShop'])
            ->where('customer_id', $customerId)
            ->where('status', RepairRequest::STATUS_COMPLETED)
            ->whereHas('repairJob')
            ->whereDoesntHave('issuedWarranties')
            ->limit(25)
            ->get();

        foreach ($completedReqs as $req) {
            if ($req->repairJob) {
                $this->issueOrderWarrantiesForCompletedJob($req->repairJob);
            }
        }
    }

    /**
     * @param  array<int, mixed>  $files
     * @return array{urls: array<int, string>, hashes: array<int, string>}
     */
    public function encodeImages(array $files): array
    {
        $urls = [];
        $hashes = [];

        foreach ($files as $file) {
            if (!$file instanceof UploadedFile || !$file->isValid()) {
                continue;
            }
            $realPath = $file->getRealPath();
            if ($realPath && is_readable($realPath)) {
                $hashes[] = hash_file('sha256', $realPath);
            }
            $encoded = MediaUploadService::encode($file, 1400, 82);
            if ($encoded !== null) {
                $urls[] = $encoded;
            }
        }

        return [
            'urls' => $urls,
            'hashes' => array_values(array_unique($hashes)),
        ];
    }

    /**
     * @param  array<int, string>  $newHashes
     * @return array{score: int, flags: array<int, string>}
     */
    public function evaluateMisuseTelemetry(IssuedWarranty $warranty, User $customer, array $newHashes): array
    {
        $score = 0;
        $flags = [];

        $priorOnItem = (int) WarrantyClaim::where('issued_warranty_id', $warranty->id)->count();
        if ($priorOnItem >= 1) {
            $nextNum = $priorOnItem + 1;
            $score += 20;
            $flags[] = "Repeat claim on same item (Claim #{$nextNum})";
        }
        if ($priorOnItem >= 2) {
            $score += 20;
            $flags[] = '3+ cumulative claims filed on this single item';
        }

        $priorRemedies = (int) $warranty->replacements_issued + (int) $warranty->repairs_issued;
        if ($priorRemedies > 0) {
            $score += 10;
            $flags[] = "Prior warranty remedy already granted ({$priorRemedies}x)";
        }

        $rejectedCount = (int) WarrantyClaim::where('customer_id', $customer->id)
            ->where('status', WarrantyClaim::STATUS_REJECTED)
            ->count();
        if ($rejectedCount >= 1) {
            $score += 25 * min(2, $rejectedCount);
            $flags[] = "Customer has {$rejectedCount} previously rejected warranty " . Str::plural('claim', $rejectedCount);
        }

        $totalClaims = (int) WarrantyClaim::where('customer_id', $customer->id)->count() + 1;
        $completedOrders = max(1, (int) RepairRequest::where('customer_id', $customer->id)
            ->where('status', RepairRequest::STATUS_COMPLETED)
            ->count());
        if ($totalClaims >= 3 && $totalClaims > $completedOrders) {
            $score += 15;
            $flags[] = "High claim frequency ({$totalClaims} claims across {$completedOrders} completed " . Str::plural('order', $completedOrders) . ')';
        }

        if (!empty($newHashes)) {
            $earlierHashes = WarrantyClaim::where('customer_id', $customer->id)
                ->whereNotNull('customer_image_hashes')
                ->pluck('customer_image_hashes')
                ->flatten()
                ->filter(fn ($h) => is_string($h) && $h !== '')
                ->all();

            if (!empty(array_intersect($newHashes, $earlierHashes))) {
                $score += 35;
                $flags[] = 'Uploaded photo matches an image used in an earlier claim';
            }
        }

        if ($warranty->starts_at && $warranty->expires_at && $warranty->duration_days > 0) {
            $totalSec = max(1, $warranty->starts_at->diffInSeconds($warranty->expires_at));
            $elapsedSec = max(0, $warranty->starts_at->diffInSeconds(now()));
            if (($elapsedSec / $totalSec) >= 0.85) {
                $score += 10;
                $flags[] = 'Filed in final 15% of warranty period';
            }
        }

        return [
            'score' => min(100, $score),
            'flags' => $flags,
        ];
    }

    /**
     * Submit a customer warranty claim (unlimited repeatable filings during bounded warranty period).
     *
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile>  $uploadedFiles
     */
    public function submitClaim(
        IssuedWarranty $warranty,
        User $customer,
        array $validated,
        array $uploadedFiles = []
    ): WarrantyClaim {
        if ((int) $warranty->customer_id !== (int) $customer->id) {
            abort(403, 'You are not authorized to file a warranty claim on another customer\'s order.');
        }

        if ($warranty->status === IssuedWarranty::STATUS_REFUNDED_CLOSED) {
            throw ValidationException::withMessages([
                'warranty' => 'This order line has already been closed via full warranty refund.',
            ]);
        }

        if (!$warranty->expires_at || now()->gt($warranty->expires_at)) {
            if ($warranty->status === IssuedWarranty::STATUS_ACTIVE) {
                $warranty->update(['status' => IssuedWarranty::STATUS_EXPIRED]);
            }
            throw ValidationException::withMessages([
                'warranty' => 'This item\'s warranty period expired on ' . ($warranty->expires_at?->format('M j, Y') ?? 'record') . '.',
            ]);
        }

        $maxQty = max(1, (int) $warranty->quantity);
        $claimedQty = max(1, min($maxQty, (int) ($validated['claimed_quantity'] ?? 1)));
        $encoded = $this->encodeImages($uploadedFiles);

        $claim = DB::transaction(function () use ($warranty, $customer, $validated, $claimedQty, $encoded) {
            /** @var IssuedWarranty $lockedWarranty */
            $lockedWarranty = IssuedWarranty::with('shop.vendorProfile.user')
                ->where('id', $warranty->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedWarranty->status === IssuedWarranty::STATUS_REFUNDED_CLOSED || now()->gt($lockedWarranty->expires_at)) {
                throw ValidationException::withMessages([
                    'warranty' => 'Warranty coverage is no longer active for this item.',
                ]);
            }

            $telemetry = $this->evaluateMisuseTelemetry($lockedWarranty, $customer, $encoded['hashes']);
            $nextNumber = ((int) WarrantyClaim::where('issued_warranty_id', $lockedWarranty->id)->max('claim_number')) + 1;

            $claim = WarrantyClaim::create([
                'issued_warranty_id' => $lockedWarranty->id,
                'repair_request_id' => $lockedWarranty->repair_request_id,
                'customer_id' => $customer->id,
                'shop_id' => $lockedWarranty->shop_id,
                'spare_part_listing_id' => $lockedWarranty->spare_part_listing_id,
                'claim_number' => $nextNumber,
                'claimed_quantity' => $claimedQty,
                'preferred_remedy' => strtoupper((string) ($validated['preferred_remedy'] ?? 'AUTO')),
                'customer_explanation' => trim((string) $validated['explanation']),
                'customer_image_urls' => $encoded['urls'],
                'customer_image_hashes' => $encoded['hashes'],
                'status' => WarrantyClaim::STATUS_PENDING,
                'misuse_risk_score' => $telemetry['score'],
                'misuse_flags' => $telemetry['flags'],
            ]);

            $lockedWarranty->increment('claims_count');

            return $claim;
        });

        $this->notifyNewClaimSubmitted($warranty->fresh('shop.vendorProfile.user'), $customer, $claim);

        return $claim;
    }

    /**
     * Platform Admin assesses a pending warranty claim and issues:
     * REFUND | REPLACEMENT | FREE_REPAIR | REJECT (with explanation & image proof).
     *
     * @param  array<string, mixed>  $validated
     * @param  array<int, UploadedFile>  $adminFiles
     */
    public function assessClaim(
        WarrantyClaim $claim,
        User $admin,
        array $validated,
        array $adminFiles = []
    ): WarrantyClaim {
        $decision = strtoupper(trim((string) $validated['decision']));
        $adminReply = trim((string) ($validated['admin_reply'] ?? ''));
        $encodedAdmin = $this->encodeImages($adminFiles);

        if ($decision === WarrantyClaim::DECISION_REJECT) {
            $errors = [];
            if (mb_strlen($adminReply) < 20) {
                $errors['admin_reply'] = 'When rejecting a warranty claim, admin must provide a sufficient explanation (at least 20 characters).';
            }
            if (empty($encodedAdmin['urls'])) {
                $errors['admin_images'] = 'When rejecting a warranty claim, at least 1 supporting inspection/proof image must be uploaded.';
            }
            if (!empty($errors)) {
                throw ValidationException::withMessages($errors);
            }
        } elseif ($adminReply === '') {
            throw ValidationException::withMessages([
                'admin_reply' => 'Please include an assessment note for the customer.',
            ]);
        }

        $inventoryService = app(SparePartInventoryService::class);

        $resolvedClaim = DB::transaction(function () use ($claim, $admin, $decision, $adminReply, $encodedAdmin, $validated, $inventoryService) {
            /** @var WarrantyClaim $lockedClaim */
            $lockedClaim = WarrantyClaim::lockForUpdate()->findOrFail($claim->id);
            if ($lockedClaim->status !== WarrantyClaim::STATUS_PENDING) {
                throw ValidationException::withMessages([
                    'claim' => 'This warranty claim has already been reviewed.',
                ]);
            }

            /** @var IssuedWarranty $warranty */
            $warranty = IssuedWarranty::with(['shop.vendorProfile.user', 'repairRequest', 'customer'])
                ->lockForUpdate()
                ->findOrFail($lockedClaim->issued_warranty_id);

            $now = now();
            $claimUpdates = [
                'admin_decision' => $decision,
                'admin_reply' => $adminReply,
                'admin_image_urls' => $encodedAdmin['urls'],
                'reviewed_by_admin_id' => $admin->id,
                'reviewed_at' => $now,
            ];

            if ($decision === WarrantyClaim::DECISION_REJECT) {
                $claimUpdates['status'] = WarrantyClaim::STATUS_REJECTED;
                $warranty->increment('rejected_claims_count');
            } elseif ($decision === WarrantyClaim::DECISION_REPLACEMENT) {
                if ($warranty->spare_part_listing_id) {
                    $movement = $inventoryService->dispatchWarrantyReplacement(
                        (int) $warranty->spare_part_listing_id,
                        $warranty->spec_code,
                        max(1, (int) $lockedClaim->claimed_quantity),
                        (int) $warranty->repair_request_id,
                        (int) $lockedClaim->id,
                        $admin->id
                    );
                    $claimUpdates['replacement_movement_id'] = $movement->id;
                }
                $claimUpdates['status'] = WarrantyClaim::STATUS_APPROVED_REPLACEMENT;
                $warranty->increment('approved_claims_count');
                $warranty->increment('replacements_issued', max(1, (int) $lockedClaim->claimed_quantity));
            } elseif ($decision === WarrantyClaim::DECISION_FREE_REPAIR) {
                $followup = $this->createFreeRepairFollowupOrder($warranty, $lockedClaim, $adminReply);
                $claimUpdates['followup_repair_request_id'] = $followup->id;
                $claimUpdates['status'] = WarrantyClaim::STATUS_APPROVED_FREE_REPAIR;
                $warranty->increment('approved_claims_count');
                $warranty->increment('repairs_issued');
            } elseif ($decision === WarrantyClaim::DECISION_REFUND) {
                $refundAmt = $this->processWarrantyRefund($warranty, $lockedClaim, $validated['refund_amount'] ?? null, $adminReply, $admin, $inventoryService);
                $claimUpdates['refund_amount'] = $refundAmt;
                $claimUpdates['status'] = WarrantyClaim::STATUS_APPROVED_REFUND;
                $warranty->increment('approved_claims_count');
            } else {
                throw ValidationException::withMessages(['decision' => 'Unsupported warranty assessment decision.']);
            }

            $lockedClaim->update($claimUpdates);

            AuditLog::create([
                'actor_id' => $admin->id,
                'action' => 'warranty_claim_' . strtolower($decision),
                'target_resource' => "warranty_claims:{$lockedClaim->id}",
                'payload_before' => ['status' => WarrantyClaim::STATUS_PENDING],
                'payload_after' => [
                    'status' => $claimUpdates['status'],
                    'decision' => $decision,
                    'refund_amount' => $claimUpdates['refund_amount'] ?? null,
                    'followup_repair_request_id' => $claimUpdates['followup_repair_request_id'] ?? null,
                ],
                'ip_address' => request()->ip() ?? '127.0.0.1',
            ]);

            return $lockedClaim->fresh(['issuedWarranty', 'reviewer']);
        });

        $this->notifyClaimAssessed($resolvedClaim, $claim->customer, $resolvedClaim->issuedWarranty);

        return $resolvedClaim;
    }

    private function createIssuedWarrantyIfMissing(array $attributes): IssuedWarranty
    {
        $existing = IssuedWarranty::where('repair_request_id', $attributes['repair_request_id'])
            ->where('line_key', $attributes['line_key'])
            ->first();
        if ($existing) {
            return $existing;
        }

        try {
            return IssuedWarranty::create($attributes);
        } catch (QueryException $e) {
            if (($e->errorInfo[0] ?? null) === '23505') {
                return IssuedWarranty::where('repair_request_id', $attributes['repair_request_id'])
                    ->where('line_key', $attributes['line_key'])
                    ->firstOrFail();
            }
            throw $e;
        }
    }

    private function createFreeRepairFollowupOrder(
        IssuedWarranty $warranty,
        WarrantyClaim $claim,
        string $adminReply
    ): RepairRequest {
        $origReq = $warranty->repairRequest;
        $shop = $warranty->shop;
        $customer = $warranty->customer;

        $followup = RepairRequest::create([
            'customer_id' => $warranty->customer_id,
            'source' => 'DIRECT_SERVICE',
            'requested_shop_id' => $warranty->shop_id,
            'title' => Str::limit('[Warranty Free Repair] ' . $warranty->item_title, 220, ''),
            'description' => "Authorized free warranty repair under Warranty Claim #{$claim->id} (Original Order #{$warranty->repair_request_id}).\n"
                . "Customer Fault Report: {$claim->customer_explanation}\n"
                . "Admin Assessment: {$adminReply}",
            'problem' => $claim->customer_explanation,
            'device_type' => $origReq?->device_type ?? 'Warranty Repair',
            'brand' => $origReq?->brand ?? ($shop?->name ?? 'Meramot'),
            'model_name' => $origReq?->model_name ?? $warranty->item_title,
            'issue_category' => 'Warranty Free Repair',
            'mode' => 'DIRECT_REPAIR',
            'status' => RepairRequest::STATUS_ASSIGNED,
            'preferred_pickup' => false,
            'preferred_service_type' => 'walk_in',
            'contact_phone' => $origReq?->contact_phone ?? ($customer?->phone ?? '01700000000'),
            'dropoff_address' => $shop?->public_address ?: ($shop?->address ?? 'Workshop Counter'),
            'checkup_fee' => 0,
            'quoted_final_amount' => 0,
        ]);

        RepairJob::create([
            'repair_request_id' => $followup->id,
            'shop_id' => $warranty->shop_id,
            'status' => 'ASSIGNED',
            'diagnosis_notes' => "Warranty free repair ticket generated from Claim #{$claim->id}.",
            'parts_cost' => 0,
            'labor_cost' => 0,
            'final_quoted_amount' => 0,
            'has_warranty' => false,
            'warranty_days' => 0,
            'customer_approved' => true,
            'started_at' => now(),
        ]);

        return $followup;
    }

    private function processWarrantyRefund(
        IssuedWarranty $warranty,
        WarrantyClaim $claim,
        mixed $requestedRaw,
        string $adminReply,
        User $admin,
        SparePartInventoryService $inventoryService
    ): float {
        $origReq = $warranty->repairRequest;
        $payment = PaymentTransaction::where('repair_request_id', $warranty->repair_request_id)
            ->orderByRaw("CASE WHEN status IN ('PAID', 'PARTIALLY_REFUNDED') THEN 0 ELSE 1 END")
            ->orderByDesc('id')
            ->first();

        $fallbackOrderTotal = max(
            1.0,
            round((float) ($origReq?->quoted_final_amount ?: ((float) $warranty->unit_price * (int) $warranty->quantity) ?: 500), 2)
        );

        if (!$payment) {
            $payment = PaymentTransaction::create([
                'user_id' => $warranty->customer_id,
                'repair_request_id' => $warranty->repair_request_id,
                'amount' => $fallbackOrderTotal,
                'currency' => 'BDT',
                'payment_method' => 'CASH',
                'status' => 'PAID',
                'paid_at' => $origReq?->updated_at ?? now(),
                'transaction_reference' => 'MRM-WC-' . $claim->id . '-' . strtoupper(Str::random(4)),
            ]);
        } elseif (!in_array($payment->status, ['PAID', 'PARTIALLY_REFUNDED', 'REFUNDED'], true)) {
            $payment->update([
                'status' => 'PAID',
                'paid_at' => $payment->paid_at ?? now(),
                'amount' => (float) $payment->amount > 0 ? $payment->amount : $fallbackOrderTotal,
            ]);
        }

        $alreadyRefunded = (float) DB::table('refunds')->where('payment_id', $payment->id)->sum('amount');
        $remainingRefundable = max(0.0, round((float) $payment->amount - $alreadyRefunded, 2));

        if ($remainingRefundable <= 0.01) {
            throw ValidationException::withMessages([
                'refund_amount' => 'No remaining paid balance is available on this order to refund. Issue a Replacement or Free Repair instead.',
            ]);
        }

        $lineMax = round((float) $warranty->unit_price * (int) $claim->claimed_quantity, 2);
        $defaultRefund = $lineMax > 0 ? min($remainingRefundable, $lineMax) : $remainingRefundable;
        $refundAmount = ($requestedRaw !== null && $requestedRaw !== '' && is_numeric($requestedRaw))
            ? round((float) $requestedRaw, 2)
            : $defaultRefund;

        if ($refundAmount <= 0 || $refundAmount > $remainingRefundable + 0.01) {
            throw ValidationException::withMessages([
                'refund_amount' => "Refund amount (৳{$refundAmount}) must be between ৳1 and the remaining paid balance (৳{$remainingRefundable}).",
            ]);
        }

        $now = now();
        $isFullyRefundedOrder = ($alreadyRefunded + $refundAmount) >= round((float) $payment->amount - 0.01, 2);
        $shopId = $warranty->shop_id ? (int) $warranty->shop_id : null;
        $noteReason = "Warranty Claim #{$claim->id}: " . Str::limit($adminReply, 220);

        DB::table('refunds')->insert([
            'payment_id' => $payment->id,
            'dispute_case_id' => null,
            'approved_by_id' => $admin->id,
            'amount' => $refundAmount,
            'reason' => Str::limit($noteReason, 295, ''),
            'status' => 'PROCESSED',
            'processed_at' => $now,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $payment->update([
            'status' => $isFullyRefundedOrder ? 'REFUNDED' : 'PARTIALLY_REFUNDED',
        ]);

        if ($payment->invoice_id && $isFullyRefundedOrder) {
            DB::table('invoices')->where('id', $payment->invoice_id)->update([
                'status' => 'REFUNDED',
                'updated_at' => $now,
            ]);
        }

        if ($warranty->spare_part_listing_id) {
            $inventoryService->recordWarrantyDefectReturn(
                (int) $warranty->spare_part_listing_id,
                $warranty->spec_code,
                max(1, (int) $claim->claimed_quantity),
                (int) $warranty->repair_request_id,
                (int) $claim->id,
                $admin->id
            );
        }

        $split = AdminLedgerController::computeRefundSplit($payment, $refundAmount, $shopId);
        DB::table('escrow_ledgers')->insert([
            'payment_id' => $payment->id,
            'repair_request_id' => $warranty->repair_request_id,
            'customer_user_id' => $warranty->customer_id,
            'vendor_user_id' => $warranty->shop?->vendorProfile?->user_id,
            'amount' => $refundAmount,
            'gross_amount' => $refundAmount,
            'platform_commission_amount' => $split['commission_reversal'],
            'vendor_net_amount' => $split['vendor_clawback'],
            'action' => 'REFUND',
            'note' => Str::limit($noteReason, 250),
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        AdminLedgerController::logEntry(
            'REFUND',
            'DEBIT',
            $refundAmount,
            "Warranty refund issued ({$payment->transaction_reference}) — Claim #{$claim->id}",
            [
                'shop_id' => $shopId,
                'payment_id' => $payment->id,
                'invoice_id' => $payment->invoice_id,
            ]
        );

        if ($split['commission_reversal'] > 0.009) {
            AdminLedgerController::logEntry(
                'PLATFORM_COMMISSION',
                'DEBIT',
                $split['commission_reversal'],
                "Platform commission reversal for warranty refund ({$payment->transaction_reference})",
                [
                    'shop_id' => $shopId,
                    'payment_id' => $payment->id,
                    'invoice_id' => $payment->invoice_id,
                ]
            );
        }

        $newRefundedTotal = round((float) $warranty->refunded_amount + $refundAmount, 2);
        $lineTotal = round((float) $warranty->unit_price * max(1, (int) $warranty->quantity), 2);
        $closeWarranty = $isFullyRefundedOrder || ($lineTotal > 0 && $newRefundedTotal >= ($lineTotal - 0.01));

        $warranty->update([
            'refunded_amount' => $newRefundedTotal,
            'status' => $closeWarranty ? IssuedWarranty::STATUS_REFUNDED_CLOSED : IssuedWarranty::STATUS_ACTIVE,
        ]);

        return $refundAmount;
    }

    private function notifyNewClaimSubmitted(?IssuedWarranty $warranty, User $customer, WarrantyClaim $claim): void
    {
        if (!Schema::hasTable('user_notifications') || !$warranty) {
            return;
        }

        $admins = User::where('role', 'admin')->limit(10)->get();
        foreach ($admins as $admin) {
            UserNotification::create([
                'user_id' => $admin->id,
                'type' => 'warranty_claim_new',
                'severity' => ((int) $claim->misuse_risk_score >= 50) ? 'warning' : 'info',
                'title' => "Warranty Claim #{$claim->id} (Order #{$claim->repair_request_id})",
                'message' => "{$customer->name} filed Claim #{$claim->claim_number} for {$warranty->item_title}."
                    . (((int) $claim->misuse_risk_score >= 25) ? " Risk score: {$claim->misuse_risk_score}/100." : ''),
                'action_url' => '/admin/warranties?status=PENDING',
                'is_read' => false,
            ]);
        }

        if ($vendorUser = $warranty->shop?->vendorProfile?->user) {
            UserNotification::create([
                'user_id' => $vendorUser->id,
                'type' => 'warranty_claim_vendor_notice',
                'severity' => 'info',
                'title' => "Warranty Claim Filed on Order #{$claim->repair_request_id}",
                'message' => "Customer filed warranty Claim #{$claim->claim_number} on {$warranty->item_title}. Meramot Admin is reviewing.",
                'action_url' => "/requests/{$claim->repair_request_id}/track#warranty-section",
                'is_read' => false,
            ]);
        }
    }

    private function notifyClaimAssessed(WarrantyClaim $claim, ?User $customer, ?IssuedWarranty $warranty): void
    {
        if (!Schema::hasTable('user_notifications') || !$customer || !$warranty) {
            return;
        }

        $decisionText = match ($claim->admin_decision) {
            WarrantyClaim::DECISION_REPLACEMENT => "Replacement approved and queued from stock for {$warranty->item_title}.",
            WarrantyClaim::DECISION_FREE_REPAIR => "Free warranty repair approved (Follow-up Order #{$claim->followup_repair_request_id}).",
            WarrantyClaim::DECISION_REFUND => 'Warranty refund of ৳' . number_format((float) $claim->refund_amount, 2) . " approved for {$warranty->item_title}.",
            default => "Warranty Claim #{$claim->claim_number} was declined after admin assessment.",
        };

        UserNotification::create([
            'user_id' => $customer->id,
            'type' => 'warranty_claim_assessed',
            'severity' => $claim->admin_decision === WarrantyClaim::DECISION_REJECT ? 'warning' : 'info',
            'title' => "Warranty Update • Claim #{$claim->claim_number} ({$claim->statusBadgeLabel()})",
            'message' => "{$decisionText} Note: " . Str::limit((string) $claim->admin_reply, 180),
            'action_url' => "/requests/{$claim->repair_request_id}/track#warranty-section",
            'is_read' => false,
        ]);

        if (
            in_array($claim->admin_decision, [WarrantyClaim::DECISION_REPLACEMENT, WarrantyClaim::DECISION_FREE_REPAIR, WarrantyClaim::DECISION_REFUND], true)
            && ($vendorUser = $warranty->shop?->vendorProfile?->user)
        ) {
            UserNotification::create([
                'user_id' => $vendorUser->id,
                'type' => 'warranty_claim_ruling',
                'severity' => 'info',
                'title' => "Admin Warranty Ruling on Order #{$claim->repair_request_id}",
                'message' => "Platform Admin issued {$claim->admin_decision} on Claim #{$claim->claim_number} ({$warranty->item_title}).",
                'action_url' => $claim->followup_repair_request_id
                    ? '/vendor/dashboard?tab=assigned_jobs'
                    : '/vendor/dashboard?tab=spare_parts',
                'is_read' => false,
            ]);
        }
    }
}

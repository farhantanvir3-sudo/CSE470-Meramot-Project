<?php

namespace App\Services;

use App\Http\Controllers\SparePartController;
use App\Models\RepairRequest;
use App\Models\SparePartListing;
use App\Models\SparePartStockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * ============================================================================
 * CONCURRENCY-SAFE SPARE PART INVENTORY & RETURN TRACKING SERVICE
 * ============================================================================
 * Serializes concurrent checkouts, cancellations, returns, and full refunds on
 * `spare_part_listings` rows using ascending-ID pessimistic row locks
 * (`ORDER BY id ASC ... FOR UPDATE`) and idempotent movement ledger records.
 * ============================================================================
 */
class SparePartInventoryService
{
    /**
     * Atomically validate and deduct stock (plus per-spec variant stock) and increment `sold_count`
     * for all spare-part lines in a checked-out order.
     *
     * @param  array<int|string, array<string, mixed>>  $cartItems
     * @return array<int, SparePartStockMovement>
     *
     * @throws ValidationException when listing/shop is inactive or available stock is insufficient
     */
    public function reserveForOrder(RepairRequest $repairRequest, array $cartItems, ?int $actorUserId = null): array
    {
        [$perListingTotals, $aggregatedLines] = $this->aggregateSparePartLines($cartItems);
        if (empty($perListingTotals)) {
            return [];
        }

        $listingIds = array_keys($perListingTotals);
        sort($listingIds, SORT_NUMERIC);

        return $this->runInTransaction(function () use ($repairRequest, $perListingTotals, $aggregatedLines, $listingIds, $actorUserId) {
            $lockedListings = SparePartListing::with('shop')
                ->whereIn('id', $listingIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            foreach ($perListingTotals as $listingId => $totalListingQty) {
                /** @var SparePartListing|null $listing */
                $listing = $lockedListings->get($listingId);
                if (!$listing || !$listing->is_active || !$listing->shop || !$listing->shop->is_active) {
                    throw ValidationException::withMessages([
                        'stock' => 'One or more spare part listings in your cart are no longer active.',
                    ]);
                }

                $availableOverall = max(0, (int) $listing->stock_quantity);
                if ($availableOverall < $totalListingQty) {
                    throw ValidationException::withMessages([
                        'stock' => "Insufficient stock for '{$listing->title}': requested {$totalListingQty}, only {$availableOverall} available.",
                    ]);
                }
            }

            $movements = [];
            foreach ($aggregatedLines as $line) {
                $listingId = $line['listing_id'];
                $specNorm = $line['spec_norm'];
                $specCode = $line['spec_code'];
                $lineQty = $line['quantity'];

                /** @var SparePartListing $listing */
                $listing = $lockedListings->get($listingId);

                $saleKey = "req:{$repairRequest->id}:sp:{$listingId}:spec:{$specNorm}:SALE";
                if (SparePartStockMovement::where('idempotency_key', $saleKey)->exists()) {
                    continue;
                }

                $specOptions = is_array($listing->spec_options) ? $listing->spec_options : [];
                $matchedOptIdx = null;
                if ($listing->has_specs) {
                    $matchedOptIdx = $this->findSpecOptionIndex($specOptions, $specCode);
                    if ($matchedOptIdx === null) {
                        throw ValidationException::withMessages([
                            'spec_code' => "Selected variant for '{$listing->title}' is no longer available.",
                        ]);
                    }
                    $opt = $specOptions[$matchedOptIdx];
                    $optStock = isset($opt['stock']) ? max(0, (int) $opt['stock']) : max(0, (int) $listing->stock_quantity);
                    if ($optStock < $lineQty) {
                        $specLabel = $opt['name'] ?? ($line['spec_name'] ?: $specCode);
                        throw ValidationException::withMessages([
                            'stock' => "Insufficient stock for '{$listing->title}' ({$specLabel}): requested {$lineQty}, only {$optStock} available.",
                        ]);
                    }
                }

                $stockBefore = max(0, (int) $listing->stock_quantity);
                if ($stockBefore < $lineQty) {
                    throw ValidationException::withMessages([
                        'stock' => "Insufficient stock for '{$listing->title}': requested {$lineQty}, only {$stockBefore} available.",
                    ]);
                }

                $stockAfter = $stockBefore - $lineQty;
                $soldAfter = max(0, (int) $listing->sold_count) + $lineQty;
                $returnedAfter = max(0, (int) ($listing->returned_count ?? 0));

                $listing->stock_quantity = $stockAfter;
                $listing->sold_count = $soldAfter;
                if ($listing->has_specs && $matchedOptIdx !== null) {
                    $prevOptStock = isset($specOptions[$matchedOptIdx]['stock'])
                        ? max(0, (int) $specOptions[$matchedOptIdx]['stock'])
                        : $stockBefore;
                    $specOptions[$matchedOptIdx]['stock'] = max(0, $prevOptStock - $lineQty);
                    $listing->spec_options = $specOptions;
                }
                $listing->save();

                $movements[] = SparePartStockMovement::create([
                    'spare_part_listing_id' => $listing->id,
                    'repair_request_id' => $repairRequest->id,
                    'actor_user_id' => $actorUserId,
                    'spec_code' => $specCode,
                    'movement_type' => SparePartStockMovement::TYPE_SALE_DEDUCT,
                    'quantity' => $lineQty,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'sold_after' => $soldAfter,
                    'returned_after' => $returnedAfter,
                    'idempotency_key' => $saleKey,
                    'reason' => "Order #{$repairRequest->id} checkout",
                ]);
            }

            if (!empty($movements)) {
                SparePartController::flushCatalogCache();
            }

            return $movements;
        });
    }

    /**
     * Atomically restore `stock_quantity` (plus variant stock), decrement `sold_count`, and
     * (for post-dispatch returns/refunds) increment `returned_count` exactly once per order item line.
     */
    public function restoreForOrder(
        RepairRequest $repairRequest,
        string $movementType,
        ?string $reason = null,
        ?int $actorUserId = null
    ): int {
        if (!in_array($movementType, [
            SparePartStockMovement::TYPE_ORDER_CANCEL_RESTORE,
            SparePartStockMovement::TYPE_RETURN_RESTORE,
        ], true)) {
            throw new \InvalidArgumentException("Unsupported restore movement type: {$movementType}");
        }

        $repairRequest->loadMissing('repairJob');
        $items = $repairRequest->repairJob?->final_quote_items ?? [];
        if (!is_array($items) || empty($items)) {
            return 0;
        }

        [$perListingTotals, $aggregatedLines] = $this->aggregateSparePartLines($items);
        if (empty($perListingTotals)) {
            return 0;
        }

        $listingIds = array_keys($perListingTotals);
        sort($listingIds, SORT_NUMERIC);

        return $this->runInTransaction(function () use ($repairRequest, $aggregatedLines, $listingIds, $movementType, $reason, $actorUserId) {
            $lockedListings = SparePartListing::whereIn('id', $listingIds)
                ->orderBy('id', 'asc')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $restoredUnits = 0;
            $cleanReason = $reason !== null && trim($reason) !== ''
                ? Str::limit(trim($reason), 250, '')
                : ($movementType === SparePartStockMovement::TYPE_RETURN_RESTORE
                    ? "Order #{$repairRequest->id} returned"
                    : "Order #{$repairRequest->id} cancelled");

            foreach ($aggregatedLines as $line) {
                $listingId = $line['listing_id'];
                $specNorm = $line['spec_norm'];
                $specCode = $line['spec_code'];
                $lineQty = $line['quantity'];

                /** @var SparePartListing|null $listing */
                $listing = $lockedListings->get($listingId);
                if (!$listing) {
                    continue;
                }

                $reverseKey = "req:{$repairRequest->id}:sp:{$listingId}:spec:{$specNorm}:REVERSE";
                if (SparePartStockMovement::where('idempotency_key', $reverseKey)->exists()) {
                    continue;
                }

                $stockBefore = max(0, (int) $listing->stock_quantity);
                $stockAfter = $stockBefore + $lineQty;
                $soldAfter = max(0, (int) $listing->sold_count - $lineQty);
                $returnedAfter = max(0, (int) ($listing->returned_count ?? 0))
                    + ($movementType === SparePartStockMovement::TYPE_RETURN_RESTORE ? $lineQty : 0);

                $listing->stock_quantity = $stockAfter;
                $listing->sold_count = $soldAfter;
                $listing->returned_count = $returnedAfter;

                if ($listing->has_specs && is_array($listing->spec_options)) {
                    $specOptions = $listing->spec_options;
                    $matchedOptIdx = $this->findSpecOptionIndex($specOptions, $specCode);
                    if ($matchedOptIdx !== null) {
                        $prevOptStock = isset($specOptions[$matchedOptIdx]['stock'])
                            ? max(0, (int) $specOptions[$matchedOptIdx]['stock'])
                            : $stockBefore;
                        $specOptions[$matchedOptIdx]['stock'] = $prevOptStock + $lineQty;
                        $listing->spec_options = $specOptions;
                    }
                }
                $listing->save();

                SparePartStockMovement::create([
                    'spare_part_listing_id' => $listing->id,
                    'repair_request_id' => $repairRequest->id,
                    'actor_user_id' => $actorUserId,
                    'spec_code' => $specCode,
                    'movement_type' => $movementType,
                    'quantity' => $lineQty,
                    'stock_before' => $stockBefore,
                    'stock_after' => $stockAfter,
                    'sold_after' => $soldAfter,
                    'returned_after' => $returnedAfter,
                    'idempotency_key' => $reverseKey,
                    'reason' => $cleanReason,
                ]);

                $restoredUnits += $lineQty;
            }

            if ($restoredUnits > 0) {
                SparePartController::flushCatalogCache();
            }

            return $restoredUnits;
        });
    }

    /**
     * Atomically deduct sellable stock for a warranty replacement dispatch and increment `returned_count`
     * without changing `sold_count`.
     *
     * @throws ValidationException when listing is missing or available stock is insufficient
     */
    public function dispatchWarrantyReplacement(
        int $listingId,
        ?string $specCode,
        int $qty,
        int $repairRequestId,
        int $claimId,
        ?int $actorUserId = null
    ): SparePartStockMovement {
        $qty = max(1, $qty);
        $rawSpec = trim((string) $specCode);
        $specNorm = $rawSpec !== '' ? strtolower($rawSpec) : '__default';
        $idempKey = "warranty_claim:{$claimId}:sp:{$listingId}:spec:{$specNorm}:REPLACEMENT";

        return $this->runInTransaction(function () use ($listingId, $rawSpec, $qty, $repairRequestId, $claimId, $actorUserId, $idempKey) {
            if ($existing = SparePartStockMovement::where('idempotency_key', $idempKey)->first()) {
                return $existing;
            }

            /** @var SparePartListing|null $listing */
            $listing = SparePartListing::where('id', $listingId)->lockForUpdate()->first();
            if (!$listing) {
                throw ValidationException::withMessages([
                    'decision' => 'Referenced spare part listing no longer exists for replacement dispatch.',
                ]);
            }

            $avail = $listing->availableStock($rawSpec !== '' ? $rawSpec : null);
            if ($avail < $qty) {
                throw ValidationException::withMessages([
                    'decision' => "Insufficient stock on '{$listing->title}' to dispatch a replacement (requested {$qty}, available {$avail}). Restock inventory first or issue a Warranty Refund / Free Repair.",
                ]);
            }

            $stockBefore = max(0, (int) $listing->stock_quantity);
            $stockAfter = max(0, $stockBefore - $qty);
            $soldAfter = max(0, (int) $listing->sold_count);
            $returnedAfter = max(0, (int) ($listing->returned_count ?? 0)) + $qty;

            $listing->stock_quantity = $stockAfter;
            $listing->returned_count = $returnedAfter;

            if ($listing->has_specs && is_array($listing->spec_options)) {
                $specOptions = $listing->spec_options;
                $matchedOptIdx = $this->findSpecOptionIndex($specOptions, $rawSpec);
                if ($matchedOptIdx !== null) {
                    $prevOptStock = isset($specOptions[$matchedOptIdx]['stock'])
                        ? max(0, (int) $specOptions[$matchedOptIdx]['stock'])
                        : $stockBefore;
                    $specOptions[$matchedOptIdx]['stock'] = max(0, $prevOptStock - $qty);
                    $listing->spec_options = $specOptions;
                }
            }
            $listing->save();

            $movement = SparePartStockMovement::create([
                'spare_part_listing_id' => $listing->id,
                'repair_request_id' => $repairRequestId,
                'actor_user_id' => $actorUserId,
                'spec_code' => $rawSpec !== '' ? $rawSpec : null,
                'movement_type' => SparePartStockMovement::TYPE_WARRANTY_REPLACEMENT,
                'quantity' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockAfter,
                'sold_after' => $soldAfter,
                'returned_after' => $returnedAfter,
                'idempotency_key' => $idempKey,
                'reason' => "Warranty replacement issued (Claim #{$claimId})",
            ]);

            SparePartController::flushCatalogCache();

            return $movement;
        });
    }

    /**
     * Record a defective item returned under an approved warranty refund without restoring sellable stock.
     */
    public function recordWarrantyDefectReturn(
        int $listingId,
        ?string $specCode,
        int $qty,
        int $repairRequestId,
        int $claimId,
        ?int $actorUserId = null
    ): ?SparePartStockMovement {
        $qty = max(1, $qty);
        $rawSpec = trim((string) $specCode);
        $specNorm = $rawSpec !== '' ? strtolower($rawSpec) : '__default';
        $idempKey = "warranty_claim:{$claimId}:sp:{$listingId}:spec:{$specNorm}:DEFECT_RETURN";

        return $this->runInTransaction(function () use ($listingId, $rawSpec, $qty, $repairRequestId, $claimId, $actorUserId, $idempKey) {
            if ($existing = SparePartStockMovement::where('idempotency_key', $idempKey)->first()) {
                return $existing;
            }

            /** @var SparePartListing|null $listing */
            $listing = SparePartListing::where('id', $listingId)->lockForUpdate()->first();
            if (!$listing) {
                return null;
            }

            $stockBefore = max(0, (int) $listing->stock_quantity);
            $soldAfter = max(0, (int) $listing->sold_count);
            $returnedAfter = max(0, (int) ($listing->returned_count ?? 0)) + $qty;

            $listing->returned_count = $returnedAfter;
            $listing->save();

            return SparePartStockMovement::create([
                'spare_part_listing_id' => $listing->id,
                'repair_request_id' => $repairRequestId,
                'actor_user_id' => $actorUserId,
                'spec_code' => $rawSpec !== '' ? $rawSpec : null,
                'movement_type' => SparePartStockMovement::TYPE_WARRANTY_DEFECT_RETURN,
                'quantity' => $qty,
                'stock_before' => $stockBefore,
                'stock_after' => $stockBefore,
                'sold_after' => $soldAfter,
                'returned_after' => $returnedAfter,
                'idempotency_key' => $idempKey,
                'reason' => "Defective warranty refund return (Claim #{$claimId})",
            ]);
        });
    }

    /**
     * Audit manual vendor dashboard inventory edits.
     */
    public function recordVendorAdjustment(SparePartListing $listing, int $stockBefore, int $stockAfter, ?int $actorUserId = null): void
    {
        if ($stockBefore === $stockAfter) {
            return;
        }

        SparePartStockMovement::create([
            'spare_part_listing_id' => $listing->id,
            'repair_request_id' => null,
            'actor_user_id' => $actorUserId,
            'spec_code' => null,
            'movement_type' => SparePartStockMovement::TYPE_VENDOR_ADJUST,
            'quantity' => abs($stockAfter - $stockBefore),
            'stock_before' => max(0, $stockBefore),
            'stock_after' => max(0, $stockAfter),
            'sold_after' => max(0, (int) $listing->sold_count),
            'returned_after' => max(0, (int) ($listing->returned_count ?? 0)),
            'idempotency_key' => 'vendor_adj:' . $listing->id . ':' . Str::uuid(),
            'reason' => 'Vendor dashboard stock update',
        ]);
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $cartItems
     * @return array{0: array<int, int>, 1: array<int, array<string, mixed>>}
     */
    private function aggregateSparePartLines(array $cartItems): array
    {
        $perListingTotals = [];
        $groupedLines = [];

        foreach ($cartItems as $item) {
            if (!is_array($item)) {
                continue;
            }
            if (strtoupper((string) ($item['source'] ?? '')) !== 'SPARE_PART' || empty($item['spare_part_id'])) {
                continue;
            }
            $listingId = (int) $item['spare_part_id'];
            $qty = max(1, (int) ($item['quantity'] ?? 1));
            $rawSpec = trim((string) ($item['spec_code'] ?? ''));
            $specNorm = $rawSpec !== '' ? strtolower($rawSpec) : '__default';
            $lineKey = "{$listingId}::{$specNorm}";

            $perListingTotals[$listingId] = ($perListingTotals[$listingId] ?? 0) + $qty;
            if (!isset($groupedLines[$lineKey])) {
                $groupedLines[$lineKey] = [
                    'listing_id' => $listingId,
                    'spec_code' => $rawSpec !== '' ? $rawSpec : null,
                    'spec_norm' => $specNorm,
                    'spec_name' => trim((string) ($item['spec_name'] ?? '')),
                    'quantity' => 0,
                ];
            }
            $groupedLines[$lineKey]['quantity'] += $qty;
        }

        ksort($perListingTotals, SORT_NUMERIC);
        $lines = array_values($groupedLines);
        usort($lines, function (array $a, array $b) {
            return ($a['listing_id'] <=> $b['listing_id'])
                ?: strcmp($a['spec_norm'], $b['spec_norm']);
        });

        return [$perListingTotals, $lines];
    }

    /**
     * @param  array<int, array<string, mixed>>  $specOptions
     */
    private function findSpecOptionIndex(array $specOptions, ?string $specCode): ?int
    {
        $needle = strtolower(trim((string) $specCode));
        if ($needle === '') {
            return null;
        }

        foreach ($specOptions as $idx => $opt) {
            if (!is_array($opt)) {
                continue;
            }
            $rawName = trim((string) ($opt['name'] ?? ''));
            $code = strtolower(trim((string) ($opt['code'] ?? '')));
            if ($code === '' && $rawName !== '') {
                $code = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $rawName));
            }
            $nameLower = strtolower($rawName);

            if ($code === $needle || $nameLower === $needle) {
                return (int) $idx;
            }
        }

        return null;
    }

    private function runInTransaction(\Closure $callback)
    {
        if (DB::transactionLevel() > 0) {
            return $callback();
        }

        return DB::transaction($callback);
    }
}

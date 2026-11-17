<?php

namespace App\Services;

use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class VoucherService
{
    /**
     * When the top-most Featured Voucher has <= 2 hours left before `expires_at`
     * and another Featured Voucher is ready in the stack, automatically rotate
     * the homepage banner to the next Featured Voucher in line.
     */
    public const HOMEPAGE_ROTATION_BUFFER_HOURS = 2;

    /**
     * Warn Admin proactively when <= 1 Featured Voucher has > 72 hours runway
     * or when all remaining Featured Vouchers expire within 72 hours.
     */
    public const LOW_STACK_WARNING_HOURS = 72;

    /**
     * Ensure the single default First-Order Free Courier voucher exists.
     */
    public function ensureDefaultFreeCourierVoucher(?int $adminUserId = null): Voucher
    {
        return Voucher::updateOrCreate(
            ['code' => Voucher::DEFAULT_FREE_COURIER_CODE],
            [
                'title' => 'Free courier on your first repair',
                'banner_subtitle' => 'Complimentary doorstep courier and return on eligible first bookings.',
                'description' => 'One-time welcome voucher covering doorstep courier pickup and return delivery for free on your very first order on Meramot.',
                'cta_href' => '/shops?sort=distance&freeDelivery=true',
                'voucher_type' => Voucher::TYPE_USE_ONCE,
                'issuer_type' => Voucher::ISSUER_ADMIN,
                'branch_scope' => Voucher::SCOPE_UNIVERSAL,
                'created_by_user_id' => $adminUserId ?? User::where('role', 'admin')->orderBy('id')->value('id'),
                'vendor_profile_id' => null,
                'discount_type' => Voucher::DISCOUNT_FREE_COURIER,
                'discount_value' => 0.00,
                'min_order_amount' => 0.00,
                'max_discount_amount' => null,
                'required_payment_method' => null,
                'is_first_order_only' => true,
                'is_system_default' => true,
                'is_featured' => false,
                'featured_order' => 0,
                'is_active' => true,
            ]
        );
    }

    /**
     * Resolve the active Featured Voucher stack for Homepage Tile 2 & Admin telemetry.
     *
     * @return array{
     *   live_stack: Collection<int, Voucher>,
     *   upcoming_stack: Collection<int, Voucher>,
     *   current_homepage_voucher: ?Voucher,
     *   next_in_line_voucher: ?Voucher,
     *   rotated_from_expiring: ?Voucher,
     *   is_empty: bool,
     *   is_low_stack: bool,
     *   healthy_runway_count: int,
     *   expiring_soon_count: int,
     *   alert_level: string,
     *   alert_title: ?string,
     *   alert_message: ?string
     * }
     */
    public function getAdminFeaturedStackHealth(): array
    {
        if (!Schema::hasTable('vouchers')) {
            return [
                'live_stack' => collect(),
                'upcoming_stack' => collect(),
                'current_homepage_voucher' => null,
                'next_in_line_voucher' => null,
                'rotated_from_expiring' => null,
                'is_empty' => true,
                'is_low_stack' => false,
                'healthy_runway_count' => 0,
                'expiring_soon_count' => 0,
                'alert_level' => 'EMPTY',
                'alert_title' => 'All Featured Vouchers Have Ended',
                'alert_message' => 'No active Featured Vouchers remain in the homepage stack.',
            ];
        }

        $now = now();
        $rotationCutoff = $now->copy()->addHours(self::HOMEPAGE_ROTATION_BUFFER_HOURS);
        $lowStackCutoff = $now->copy()->addHours(self::LOW_STACK_WARNING_HOURS);

        $allFeaturedCandidates = Voucher::where('issuer_type', Voucher::ISSUER_ADMIN)
            ->where('is_system_default', false)
            ->where('is_featured', true)
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->orderBy('featured_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        $liveStack = $allFeaturedCandidates->filter(function (Voucher $v) use ($now) {
            return !$v->starts_at || $now->gte($v->starts_at);
        })->values();

        $upcomingStack = $allFeaturedCandidates->filter(function (Voucher $v) use ($now) {
            return $v->starts_at && $now->lt($v->starts_at);
        })->values();

        $currentHomepage = null;
        $nextInLine = null;
        $rotatedFrom = null;

        if ($liveStack->isNotEmpty()) {
            $first = $liveStack->first();
            $firstAboutToEnd = $first->expires_at !== null && $first->expires_at->lte($rotationCutoff);

            if ($firstAboutToEnd && $liveStack->count() > 1) {
                $replacement = $liveStack->slice(1)->first(function (Voucher $v) use ($rotationCutoff) {
                    return $v->expires_at === null || $v->expires_at->gt($rotationCutoff);
                }) ?? $liveStack->get(1);

                $currentHomepage = $replacement;
                $rotatedFrom = $first;
                $remainingAfterReplacement = $liveStack->filter(fn (Voucher $v) => $v->id !== $first->id && $v->id !== $replacement->id)->values();
                $nextInLine = $remainingAfterReplacement->first();
            } else {
                $currentHomepage = $first;
                $nextInLine = $liveStack->get(1);
            }
        }

        $healthyRunway = $allFeaturedCandidates->filter(function (Voucher $v) use ($lowStackCutoff) {
            return $v->expires_at === null || $v->expires_at->gt($lowStackCutoff);
        });

        $expiringSoon = $liveStack->filter(function (Voucher $v) use ($lowStackCutoff) {
            return $v->expires_at !== null && $v->expires_at->lte($lowStackCutoff);
        });

        $isEmpty = ($currentHomepage === null);
        $isLowStack = !$isEmpty && (
            $allFeaturedCandidates->count() <= 1
            || $healthyRunway->count() === 0
            || ($currentHomepage->expires_at !== null && $currentHomepage->expires_at->lte($lowStackCutoff) && $healthyRunway->count() < 1)
        );

        $alertLevel = 'OK';
        $alertTitle = null;
        $alertMessage = null;

        if ($isEmpty) {
            $alertLevel = 'EMPTY';
            $alertTitle = 'All Featured Vouchers Have Ended';
            $alertMessage = 'Every Featured Voucher in your homepage stack has ended or been paused. On the homepage, the First-Order Free Courier tile is now taking up the full banner space alone. Create or extend a Featured Voucher to restore the second homepage promo tile.';
        } elseif ($isLowStack) {
            $alertLevel = 'LOW';
            $alertTitle = 'Featured Voucher Stack Running Low';
            if ($allFeaturedCandidates->count() === 1) {
                $expNote = $currentHomepage->expires_at
                    ? ' (ends ' . $currentHomepage->expires_at->format('M j, Y H:i') . ')'
                    : '';
                $alertMessage = "Only 1 Featured Voucher ({$currentHomepage->code}{$expNote}) remains in the homepage stack with no backup queued behind it. Add or schedule another Featured Voucher soon so the homepage banner stack never runs empty.";
            } else {
                $latestExp = $allFeaturedCandidates->max('expires_at');
                $expLabel = $latestExp ? Carbon::parse($latestExp)->format('M j, Y H:i') : 'soon';
                $alertMessage = "Your remaining Featured Vouchers in the homepage stack are scheduled to end by {$expLabel}. Extend their time periods or add a backup Featured Voucher beforehand.";
            }
        }

        return [
            'live_stack' => $liveStack,
            'upcoming_stack' => $upcomingStack,
            'current_homepage_voucher' => $currentHomepage,
            'next_in_line_voucher' => $nextInLine,
            'rotated_from_expiring' => $rotatedFrom,
            'is_empty' => $isEmpty,
            'is_low_stack' => $isLowStack,
            'healthy_runway_count' => $healthyRunway->count(),
            'expiring_soon_count' => $expiringSoon->count(),
            'alert_level' => $alertLevel,
            'alert_title' => $alertTitle,
            'alert_message' => $alertMessage,
        ];
    }

    /**
     * Build the 1 or 2 Homepage Promotional Banner cards:
     *   - Tile 1: First-Order Free Courier voucher (`FREECOURIER`).
     *   - Tile 2: Top-most active Featured Voucher from the Admin stack.
     *     When the Featured Voucher stack is empty, ONLY Tile 1 is returned so it spans full width!
     */
    public function resolveHomepageOfferCards(): array
    {
        $cards = [];

        $defaultCourierVoucher = Schema::hasTable('vouchers')
            ? Voucher::where('is_system_default', true)->first()
            : null;

        if (!$defaultCourierVoucher || $defaultCourierVoucher->is_active) {
            $cards[] = [
                'badge_label' => 'Featured Offer',
                'badge_key' => 'offer_available',
                'title' => $defaultCourierVoucher?->title ?: 'Free courier on your first repair',
                'title_key' => 'offer_pickup_title',
                'subtitle' => $defaultCourierVoucher?->banner_subtitle ?: 'Complimentary doorstep courier and return on eligible first bookings.',
                'subtitle_key' => 'offer_pickup_sub',
                'voucher_code' => $defaultCourierVoucher?->code ?: Voucher::DEFAULT_FREE_COURIER_CODE,
                'voucher_pill' => $defaultCourierVoucher
                    ? $defaultCourierVoucher->advertisedBadgeText()
                    : ('Free Courier (1st Order): ' . Voucher::DEFAULT_FREE_COURIER_CODE),
                'expires_label' => null,
                'href' => $defaultCourierVoucher?->cta_href ?: '/shops?sort=distance&freeDelivery=true',
            ];
        }

        if (!Schema::hasTable('vouchers')) {
            return $cards;
        }

        $stackHealth = $this->getAdminFeaturedStackHealth();
        $topFeatured = $stackHealth['current_homepage_voucher'];

        if ($topFeatured) {
            $isDefaultBatteryBanner = strtoupper($topFeatured->code) === 'BATTERY15'
                && stripos($topFeatured->title, 'Laptop battery week') !== false;

            $defaultSubtitle = $topFeatured->banner_subtitle;
            if (!$defaultSubtitle) {
                $defaultSubtitle = $topFeatured->description ?: ('Apply voucher code ' . $topFeatured->code . ' at checkout (' . $topFeatured->conditionsPillLabel() . ').');
            }

            $cards[] = [
                'badge_label' => 'Featured Voucher',
                'badge_key' => 'featured_voucher_badge',
                'title' => $topFeatured->title,
                'title_key' => $isDefaultBatteryBanner ? 'offer_battery_title' : null,
                'subtitle' => $defaultSubtitle,
                'subtitle_key' => $isDefaultBatteryBanner ? 'offer_battery_sub' : null,
                'voucher_code' => $topFeatured->code,
                'voucher_pill' => $topFeatured->advertisedBadgeText(),
                'expires_label' => $topFeatured->expires_at ? ('Ends ' . $topFeatured->expires_at->format('M j, Y')) : null,
                'href' => $topFeatured->cta_href ?: '/shops?voucher=true',
            ];
        }

        return $cards;
    }

    /**
     * Check whether a given Voucher applies to a specific RepairShop branch.
     */
    public function isApplicableToShop(Voucher $voucher, RepairShop $shop): bool
    {
        if (!$voucher->isWithinDateWindow()) {
            return false;
        }

        // Free Courier vouchers: Vendor vouchers require shop courier support; Admin platform vouchers apply to all shops not excluded by admin
        if (
            $voucher->issuer_type === Voucher::ISSUER_VENDOR
            && $voucher->discount_type === Voucher::DISCOUNT_FREE_COURIER
            && !$shop->supportsCourierPickup()
            && !$shop->supportsSpareParts()
        ) {
            return false;
        }

        if ($voucher->issuer_type === Voucher::ISSUER_ADMIN) {
            $excludedIds = $voucher->relationLoaded('excludedShops')
                ? $voucher->excludedShops->pluck('id')->map(fn ($v) => (int) $v)->all()
                : $voucher->excludedShops()->pluck('repair_shops.id')->map(fn ($v) => (int) $v)->all();

            return !in_array((int) $shop->id, $excludedIds, true);
        }

        // Vendor-set voucher
        if ((int) $voucher->vendor_profile_id !== (int) $shop->vendor_profile_id) {
            return false;
        }

        if ($voucher->branch_scope === Voucher::SCOPE_SPECIFIC_BRANCHES) {
            $includedIds = $voucher->relationLoaded('includedShops')
                ? $voucher->includedShops->pluck('id')->map(fn ($v) => (int) $v)->all()
                : $voucher->includedShops()->pluck('repair_shops.id')->map(fn ($v) => (int) $v)->all();

            return in_array((int) $shop->id, $includedIds, true);
        }

        return true;
    }

    /**
     * Batch-attach `available_vouchers` relation onto a collection/paginator of RepairShop models
     * using at most 1–2 queries (zero N+1 queries).
     */
    public function attachAvailableVouchersToShops(iterable $shops): void
    {
        $shopList = collect($shops)->filter(fn ($s) => $s instanceof RepairShop)->values();
        if ($shopList->isEmpty()) {
            return;
        }

        if (!Schema::hasTable('vouchers')) {
            foreach ($shopList as $shop) {
                $shop->setRelation('available_vouchers', collect());
            }
            return;
        }

        $now = now();
        $vendorProfileIds = $shopList->pluck('vendor_profile_id')->filter()->unique()->values()->all();

        $activeVouchers = Voucher::with(['includedShops:id', 'excludedShops:id'])
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->where(function ($q) use ($vendorProfileIds) {
                $q->where('issuer_type', Voucher::ISSUER_ADMIN);
                if (!empty($vendorProfileIds)) {
                    $q->orWhere(function ($vq) use ($vendorProfileIds) {
                        $vq->where('issuer_type', Voucher::ISSUER_VENDOR)
                            ->whereIn('vendor_profile_id', $vendorProfileIds);
                    });
                }
            })
            ->orderByRaw("CASE WHEN issuer_type = 'VENDOR' THEN 0 WHEN is_featured = true THEN 1 WHEN is_system_default = true THEN 2 ELSE 3 END ASC")
            ->orderBy('featured_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($shopList as $shop) {
            $matched = $activeVouchers->filter(fn (Voucher $v) => $this->isApplicableToShop($v, $shop))->values();
            $shop->setRelation('available_vouchers', $matched);
        }
    }

    /**
     * Single-shop helper returning active Vouchers applicable to `$shop`.
     */
    public function getAvailableVouchersForShop(RepairShop $shop): Collection
    {
        $this->attachAvailableVouchersToShops([$shop]);
        return $shop->getRelation('available_vouchers') ?? collect();
    }

    /**
     * Evaluate a single voucher code against order parameters.
     *
     * @return array{
     *   valid: bool,
     *   code: ?string,
     *   voucher_id: ?int,
     *   voucher: ?Voucher,
     *   discount: float,
     *   discount_type: ?string,
     *   discount_value: float,
     *   max_discount_amount: ?float,
     *   min_order_amount: float,
     *   required_payment_method: ?string,
     *   label: string,
     *   error: ?string,
     *   shortfall: float
     * }
     */
    public function evaluate(
        ?string $rawCode,
        float $subtotal,
        float $deliveryFee = 80.0,
        ?RepairShop $shop = null,
        ?User $user = null,
        ?float $activeFeeRatePct = null,
        ?string $paymentMethod = null,
        bool $isCourierFulfillment = true,
        bool $enforcePaymentMethod = false,
        ?int $currentRepairRequestId = null
    ): array {
        $empty = [
            'valid' => false,
            'code' => null,
            'voucher_id' => null,
            'voucher' => null,
            'discount' => 0.0,
            'discount_type' => null,
            'discount_value' => 0.0,
            'max_discount_amount' => null,
            'min_order_amount' => 0.0,
            'required_payment_method' => null,
            'label' => '',
            'error' => null,
            'shortfall' => 0.0,
        ];

        $code = strtoupper(trim((string) $rawCode));
        if ($code === '' || $subtotal <= 0) {
            return $empty;
        }

        $voucher = Schema::hasTable('vouchers')
            ? Voucher::with(['includedShops:id', 'excludedShops:id'])->where('code', $code)->first()
            : null;

        // Preserve isolated fee-floor test helper (`FIRSTSHOP1` / `WELCOME1`) when not overridden by a DB Voucher row
        if (!$voucher && in_array($code, ['FIRSTSHOP1', 'WELCOME1'], true)) {
            return $this->evaluateLegacyFirstShopFeeOffer($code, $subtotal, $shop, $user, $activeFeeRatePct, $empty);
        }

        if (!$voucher) {
            return array_merge($empty, [
                'error' => "Voucher code '{$code}' does not exist.",
            ]);
        }

        if (!$voucher->is_active) {
            return array_merge($empty, [
                'error' => "Voucher '{$code}' is currently inactive.",
            ]);
        }

        $now = now();
        if ($voucher->starts_at && $now->lt($voucher->starts_at)) {
            return array_merge($empty, [
                'error' => "Voucher '{$code}' becomes active on " . $voucher->starts_at->format('M j, Y H:i') . '.',
            ]);
        }
        if ($voucher->expires_at && $now->gt($voucher->expires_at)) {
            return array_merge($empty, [
                'error' => "Voucher '{$code}' expired on " . $voucher->expires_at->format('M j, Y') . '.',
            ]);
        }

        // Scheduled day-of-week & daily window check
        $days = $voucher->normalizedDaysOfWeek();
        if (!empty($days) && !in_array((int) $now->dayOfWeek, $days, true)) {
            $dayLabels = implode(', ', array_map(fn ($d) => Voucher::DAY_NAMES[$d] ?? '', $days));
            return array_merge($empty, [
                'error' => "Voucher '{$code}' is valid only on {$dayLabels}.",
            ]);
        }
        if (!empty($voucher->daily_start_time) && !empty($voucher->daily_end_time)) {
            $hhmm = $now->format('H:i');
            if ($hhmm < $voucher->daily_start_time || $hhmm > $voucher->daily_end_time) {
                return array_merge($empty, [
                    'error' => "Voucher '{$code}' is active daily between {$voucher->daily_start_time} and {$voucher->daily_end_time}.",
                ]);
            }
        }

        // Shop & branch scope verification
        if ($shop) {
            if ($voucher->issuer_type === Voucher::ISSUER_ADMIN) {
                $excludedIds = $voucher->excludedShops->pluck('id')->map(fn ($v) => (int) $v)->all();
                if (in_array((int) $shop->id, $excludedIds, true)) {
                    $branchLabel = $shop->display_branch_tag ? " [{$shop->display_branch_tag}]" : '';
                    return array_merge($empty, [
                        'error' => "Voucher '{$code}' is not valid at {$shop->name}{$branchLabel}.",
                    ]);
                }
            } else {
                if ((int) $voucher->vendor_profile_id !== (int) $shop->vendor_profile_id) {
                    return array_merge($empty, [
                        'error' => "Voucher '{$code}' belongs to another workshop and cannot be applied here.",
                    ]);
                }
                if ($voucher->branch_scope === Voucher::SCOPE_SPECIFIC_BRANCHES) {
                    $includedIds = $voucher->includedShops->pluck('id')->map(fn ($v) => (int) $v)->all();
                    if (!in_array((int) $shop->id, $includedIds, true)) {
                        $branchLabel = $shop->display_branch_tag ? " [{$shop->display_branch_tag}]" : '';
                        return array_merge($empty, [
                            'error' => "Voucher '{$code}' is valid only at selected {$shop->name} branches (not{$branchLabel}).",
                        ]);
                    }
                }
            }
        } elseif ($voucher->issuer_type === Voucher::ISSUER_VENDOR) {
            return array_merge($empty, [
                'error' => "Select an eligible workshop first to apply '{$code}'.",
            ]);
        }

        // Use-Once & First-Order-Ever checks
        if ($voucher->voucher_type === Voucher::TYPE_USE_ONCE || $voucher->is_first_order_only) {
            if (!$user) {
                return array_merge($empty, [
                    'error' => "Please sign in to redeem one-time voucher '{$code}'.",
                ]);
            }

            $alreadyRedeemed = VoucherRedemption::where('voucher_id', $voucher->id)
                ->where('user_id', $user->id)
                ->when($currentRepairRequestId, fn ($q) => $q->where('repair_request_id', '!=', $currentRepairRequestId))
                ->exists();

            if ($alreadyRedeemed) {
                return array_merge($empty, [
                    'error' => "You have already used one-time voucher '{$code}'.",
                ]);
            }

            if ($voucher->is_first_order_only) {
                $hasPriorOrder = RepairRequest::where('customer_id', $user->id)
                    ->whereNotIn('status', [RepairRequest::STATUS_CANCELLED])
                    ->when($currentRepairRequestId, fn ($q) => $q->where('id', '!=', $currentRepairRequestId))
                    ->exists();

                if ($hasPriorOrder) {
                    return array_merge($empty, [
                        'error' => "'{$code}' is valid only once for your very first order on Meramot.",
                    ]);
                }
            }
        }

        // Free Courier fulfillment check
        if ($voucher->discount_type === Voucher::DISCOUNT_FREE_COURIER) {
            if ($voucher->issuer_type === Voucher::ISSUER_VENDOR && $shop && !$shop->supportsCourierPickup() && !$shop->supportsSpareParts()) {
                return array_merge($empty, [
                    'error' => "'{$code}' covers courier pickup & delivery, which {$shop->name} does not offer.",
                ]);
            }
            if (!$isCourierFulfillment) {
                return array_merge($empty, [
                    'error' => "'{$code}' covers doorstep courier pickup & delivery — switch fulfillment to Courier Pickup to use it.",
                ]);
            }
        }

        // Minimum spend check
        $minSpend = (float) $voucher->min_order_amount;
        if ($minSpend > 0 && ($subtotal + 0.009) < $minSpend) {
            $shortfall = ceil($minSpend - $subtotal);
            return array_merge($empty, [
                'shortfall' => (float) $shortfall,
                'error' => 'Add ৳' . number_format($shortfall, 0) . " more to use '{$code}' (min. spend ৳" . number_format($minSpend, 0) . ').',
            ]);
        }

        // Payment channel restriction check
        $reqMethod = $voucher->required_payment_method ? strtoupper(trim($voucher->required_payment_method)) : null;
        if ($enforcePaymentMethod && $reqMethod !== null) {
            $chosen = strtoupper(trim((string) $paymentMethod));
            if ($chosen !== $reqMethod) {
                $payLabel = $voucher->paymentMethodLabel() ?: $reqMethod;
                return array_merge($empty, [
                    'required_payment_method' => $reqMethod,
                    'error' => "Voucher '{$code}' is valid only when paying with {$payLabel}. Please select {$payLabel} or remove the voucher.",
                ]);
            }
        }

        // Compute monetary discount
        $discount = 0.0;
        if ($voucher->discount_type === Voucher::DISCOUNT_FREE_COURIER) {
            $cap = $voucher->max_discount_amount !== null && (float) $voucher->max_discount_amount > 0
                ? (float) $voucher->max_discount_amount
                : null;
            $discount = $cap !== null ? min($deliveryFee, $cap) : round($deliveryFee, 2);
        } elseif ($voucher->discount_type === Voucher::DISCOUNT_PERCENTAGE) {
            $raw = round($subtotal * ((float) $voucher->discount_value / 100.0), 2);
            $cap = $voucher->max_discount_amount !== null && (float) $voucher->max_discount_amount > 0
                ? (float) $voucher->max_discount_amount
                : null;
            $discount = $cap !== null ? min($cap, $raw) : $raw;
            $discount = min($subtotal, round($discount, 2));
        } else {
            $discount = min($subtotal, round((float) $voucher->discount_value, 2));
        }

        return [
            'valid' => true,
            'code' => $voucher->code,
            'voucher_id' => $voucher->id,
            'voucher' => $voucher,
            'discount' => round(max(0.0, $discount), 2),
            'discount_type' => $voucher->discount_type,
            'discount_value' => (float) $voucher->discount_value,
            'max_discount_amount' => $voucher->max_discount_amount !== null ? (float) $voucher->max_discount_amount : null,
            'min_order_amount' => $minSpend,
            'required_payment_method' => $reqMethod,
            'label' => $voucher->title,
            'error' => null,
            'shortfall' => 0.0,
        ];
    }

    /**
     * Build the uniform customer-facing list of vouchers for the Foodpanda-style
     * "Apply a voucher" dialog on `/cart`, `/cart/checkout`, and `/checkout/payment/{id}`.
     * Customers see no distinction between Admin Featured Vouchers and Vendor Vouchers.
     */
    public function buildCheckoutVoucherCatalog(
        ?RepairShop $shop,
        ?User $user,
        float $subtotal,
        float $deliveryFee,
        bool $isCourierMode = true,
        ?int $currentRepairRequestId = null
    ): array {
        if (!$shop || !Schema::hasTable('vouchers')) {
            return [];
        }

        $vouchers = $this->getAvailableVouchersForShop($shop);
        $catalog = [];

        foreach ($vouchers as $voucher) {
            $eval = $this->evaluate(
                $voucher->code,
                $subtotal,
                $deliveryFee,
                $shop,
                $user,
                null,
                null,
                $isCourierMode,
                false,
                $currentRepairRequestId
            );

            $statusBanner = null;
            $statusTone = 'info';
            if (!$eval['valid']) {
                $statusTone = 'warning';
                if ($eval['shortfall'] > 0) {
                    $statusBanner = 'Add ৳' . number_format($eval['shortfall'], 0) . ' more to use this voucher';
                } else {
                    $statusBanner = $eval['error'];
                }
            } elseif (!empty($voucher->required_payment_method)) {
                $statusBanner = 'Pay with ' . $voucher->paymentMethodLabel() . ' to redeem this discount';
                $statusTone = 'info';
            }

            $catalog[] = [
                'id' => $voucher->id,
                'code' => $voucher->code,
                'title' => $voucher->title,
                'discount_highlight' => $voucher->discountHighlightLabel(),
                'badge_text' => $voucher->advertisedBadgeText(),
                'conditions_pill' => $voucher->conditionsPillLabel(),
                'discount_type' => $voucher->discount_type,
                'discount_value' => (float) $voucher->discount_value,
                'min_order_amount' => (float) $voucher->min_order_amount,
                'max_discount_amount' => $voucher->max_discount_amount !== null ? (float) $voucher->max_discount_amount : null,
                'required_payment_method' => $voucher->required_payment_method,
                'payment_label' => $voucher->paymentMethodLabel(),
                'eligible' => $eval['valid'],
                'discount_preview' => $eval['discount'],
                'status_banner' => $statusBanner,
                'status_tone' => $statusTone,
            ];
        }

        // Sort eligible vouchers first so immediately-usable offers appear at the top of "Select a voucher"
        usort($catalog, function (array $a, array $b) {
            if ($a['eligible'] !== $b['eligible']) {
                return $a['eligible'] ? -1 : 1;
            }
            return 0;
        });

        return $catalog;
    }

    /**
     * Atomically lock & record a voucher redemption inside the order transaction.
     * Prevents race conditions on `USE_ONCE` and `is_first_order_only` vouchers.
     */
    public function recordRedemption(
        Voucher $voucher,
        User $user,
        RepairRequest $repairRequest,
        ?RepairShop $shop,
        float $discountAmount
    ): VoucherRedemption {
        return DB::transaction(function () use ($voucher, $user, $repairRequest, $shop, $discountAmount) {
            /** @var Voucher $locked */
            $locked = Voucher::where('id', $voucher->id)->lockForUpdate()->firstOrFail();

            if ($locked->voucher_type === Voucher::TYPE_USE_ONCE || $locked->is_first_order_only) {
                $existing = VoucherRedemption::where('voucher_id', $locked->id)
                    ->where('user_id', $user->id)
                    ->where('repair_request_id', '!=', $repairRequest->id)
                    ->lockForUpdate()
                    ->exists();

                if ($existing) {
                    throw ValidationException::withMessages([
                        'voucher_code' => "You have already used one-time voucher '{$locked->code}'.",
                    ]);
                }

                if ($locked->is_first_order_only) {
                    $priorOrder = RepairRequest::where('customer_id', $user->id)
                        ->where('id', '!=', $repairRequest->id)
                        ->whereNotIn('status', [RepairRequest::STATUS_CANCELLED])
                        ->exists();

                    if ($priorOrder) {
                        throw ValidationException::withMessages([
                            'voucher_code' => "'{$locked->code}' is valid only once for your very first order on Meramot.",
                        ]);
                    }
                }
            }

            // Replace any prior voucher redemption on the same unpaid RepairRequest (strictly 1 voucher per order!)
            $previousOnRequest = VoucherRedemption::where('repair_request_id', $repairRequest->id)->first();
            if ($previousOnRequest && (int) $previousOnRequest->voucher_id !== (int) $locked->id) {
                Voucher::where('id', $previousOnRequest->voucher_id)
                    ->where('usage_count', '>', 0)
                    ->decrement('usage_count');
                $previousOnRequest->delete();
            }

            $redemption = VoucherRedemption::updateOrCreate(
                ['repair_request_id' => $repairRequest->id],
                [
                    'voucher_id' => $locked->id,
                    'user_id' => $user->id,
                    'shop_id' => $shop?->id,
                    'voucher_code' => $locked->code,
                    'discount_amount' => round(max(0.0, $discountAmount), 2),
                    'redeemed_at' => now(),
                ]
            );

            if ($redemption->wasRecentlyCreated) {
                $locked->increment('usage_count');
            }

            return $redemption;
        });
    }

    /**
     * Release a voucher redemption if the customer cancels an unpaid/early order,
     * restoring `USE_ONCE` eligibility cleanly.
     */
    public function releaseRedemptionForCancelledOrder(RepairRequest $repairRequest): void
    {
        if (!Schema::hasTable('voucher_redemptions')) {
            return;
        }

        $redemption = VoucherRedemption::where('repair_request_id', $repairRequest->id)->first();
        if (!$redemption) {
            return;
        }

        DB::transaction(function () use ($redemption) {
            Voucher::where('id', $redemption->voucher_id)
                ->where('usage_count', '>', 0)
                ->decrement('usage_count');
            $redemption->delete();
        });
    }

    /**
     * Synchronize `has_voucher` and `free_delivery` flags on `repair_shops`
     * so legacy SQL & API filters stay consistent with active `Voucher` records.
     */
    public function syncShopVoucherFlags(?int $vendorProfileId = null): void
    {
        if (!Schema::hasTable('vouchers') || !Schema::hasTable('repair_shops')) {
            return;
        }

        $shopsQuery = RepairShop::query();
        if ($vendorProfileId !== null) {
            $shopsQuery->where('vendor_profile_id', $vendorProfileId);
        }

        // For large nationwide datasets, compute sets in memory via IDs rather than 1,300 Eloquent updates
        $now = now();
        $activeVouchers = Voucher::with(['includedShops:id', 'excludedShops:id'])
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', $now);
            })
            ->get();

        $adminDiscountVouchers = $activeVouchers->filter(
            fn (Voucher $v) => $v->issuer_type === Voucher::ISSUER_ADMIN && $v->discount_type !== Voucher::DISCOUNT_FREE_COURIER
        );
        $adminFreeCourierVouchers = $activeVouchers->filter(
            fn (Voucher $v) => $v->issuer_type === Voucher::ISSUER_ADMIN && $v->discount_type === Voucher::DISCOUNT_FREE_COURIER
        );
        $vendorVouchersByProfile = $activeVouchers
            ->where('issuer_type', Voucher::ISSUER_VENDOR)
            ->groupBy('vendor_profile_id');

        $shops = $shopsQuery->select(['id', 'vendor_profile_id', 'supports_pickup', 'categories', 'has_voucher', 'free_delivery'])->get();

        $enableVoucherIds = [];
        $disableVoucherIds = [];
        $enableFreeIds = [];
        $disableFreeIds = [];

        foreach ($shops as $shop) {
            $sid = (int) $shop->id;
            $vpId = (int) ($shop->vendor_profile_id ?? 0);
            $cats = is_array($shop->categories) ? $shop->categories : [];
            $supportsCourier = (bool) $shop->supports_pickup || in_array('COURIER_PICKUP', $cats, true) || in_array('SPARE_PARTS', $cats, true);

            $hasDiscountVoucher = false;
            foreach ($adminDiscountVouchers as $av) {
                if (!$av->excludedShops->contains('id', $sid)) {
                    $hasDiscountVoucher = true;
                    break;
                }
            }

            $hasFreeCourier = false;
            if ($supportsCourier) {
                foreach ($adminFreeCourierVouchers as $fv) {
                    if (!$fv->excludedShops->contains('id', $sid)) {
                        $hasFreeCourier = true;
                        break;
                    }
                }
            }

            foreach ($vendorVouchersByProfile->get($vpId, collect()) as $vv) {
                $applies = $vv->branch_scope !== Voucher::SCOPE_SPECIFIC_BRANCHES
                    || $vv->includedShops->contains('id', $sid);
                if (!$applies) {
                    continue;
                }
                if ($vv->discount_type === Voucher::DISCOUNT_FREE_COURIER) {
                    if ($supportsCourier) {
                        $hasFreeCourier = true;
                    }
                } else {
                    $hasDiscountVoucher = true;
                }
            }

            if ($hasDiscountVoucher !== (bool) $shop->has_voucher) {
                if ($hasDiscountVoucher) {
                    $enableVoucherIds[] = $sid;
                } else {
                    $disableVoucherIds[] = $sid;
                }
            }

            if ($hasFreeCourier !== (bool) $shop->free_delivery) {
                if ($hasFreeCourier) {
                    $enableFreeIds[] = $sid;
                } else {
                    $disableFreeIds[] = $sid;
                }
            }
        }

        foreach (array_chunk($enableVoucherIds, 500) as $chunk) {
            DB::table('repair_shops')->whereIn('id', $chunk)->update(['has_voucher' => true]);
        }
        foreach (array_chunk($disableVoucherIds, 500) as $chunk) {
            DB::table('repair_shops')->whereIn('id', $chunk)->update(['has_voucher' => false]);
        }
        foreach (array_chunk($enableFreeIds, 500) as $chunk) {
            DB::table('repair_shops')->whereIn('id', $chunk)->update(['free_delivery' => true]);
        }
        foreach (array_chunk($disableFreeIds, 500) as $chunk) {
            DB::table('repair_shops')->whereIn('id', $chunk)->update(['free_delivery' => false]);
        }
    }

    private function evaluateLegacyFirstShopFeeOffer(
        string $code,
        float $subtotal,
        ?RepairShop $shop,
        ?User $user,
        ?float $activeFeeRatePct,
        array $empty
    ): array {
        if (!$user) {
            return array_merge($empty, ['error' => 'Sign in to redeem this first-order workshop offer.']);
        }
        if (!$shop) {
            return array_merge($empty, ['error' => 'Select a workshop first to verify first-order eligibility.']);
        }
        if ($subtotal < MarketplacePricingService::FIRST_SHOP_PROMO_MIN_SUBTOTAL) {
            return array_merge($empty, [
                'error' => $code . ' requires a minimum order subtotal of ৳' . number_format(MarketplacePricingService::FIRST_SHOP_PROMO_MIN_SUBTOTAL, 0) . '.',
            ]);
        }
        if (!MarketplacePricingService::isEligibleForFirstShopOrderPromo($user, $shop)) {
            return array_merge($empty, [
                'error' => 'This welcome offer is valid only on your very first order with this workshop.',
            ]);
        }

        $ratePct = $activeFeeRatePct ?? \App\Http\Controllers\ServicePackageController::resolveCommissionRatePct($shop);
        $promo = MarketplacePricingService::calculateFirstShopPromoDiscount($subtotal, $ratePct);

        return array_merge($empty, [
            'valid' => true,
            'code' => $code,
            'discount' => (float) $promo['discount_amount'],
            'discount_type' => Voucher::DISCOUNT_FIXED,
            'discount_value' => (float) $promo['discount_amount'],
            'label' => 'First Order at Workshop — Reduced Service Fee Offer',
        ]);
    }
}

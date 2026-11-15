<?php

namespace App\Services;

use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\SparePartListing;
use App\Models\User;
use App\Models\VendorProfile;

/**
 * Dynamic 4-Factor Marketplace Pricing & Rare First-Shop Promo Policy.
 *
 * Customer-facing service/protection fee adapts across four real marketplace dimensions:
 *   1. Shop base rate (admin-configured 5.00% – 10.00%, default 8.00%)
 *   2. Product / repair category complexity (-0.75% to +0.75%)
 *   3. City & cross-city logistics tier (0.00% to +1.05%)
 *   4. Micro-zone / repair hub vs outer delivery area (-0.35% to +0.35%)
 *
 * Standard customer fee is clamped to [3.50%, 10.00%].
 * Rare first-order promo codes (FIRSTSHOP1 / WELCOME1) can lower the effective fee
 * down to a strict 1.00% floor strictly on a customer's very first order at that shop.
 */
class MarketplacePricingService
{
    public const MIN_STANDARD_FEE_PCT = 3.50;
    public const MAX_STANDARD_FEE_PCT = 10.00;
    public const PROMO_FLOOR_FEE_PCT = 1.00;
    public const FIRST_SHOP_PROMO_MIN_SUBTOTAL = 500.00;

    private const HIGH_COMPLEXITY_CATEGORIES = [
        'Display & Touch',
        'PC Display, Cable & Board',
    ];

    private const MID_TIER_CATEGORIES = [
        'Battery',
        'Laptop Battery',
        'SSD, RAM & Storage',
        'Camera & Lens',
    ];

    private const LOW_TICKET_CATEGORIES = [
        'SIM Tray & Small Parts',
        'Tools, Consumables & Soldering',
    ];

    private const REPAIR_HUB_AREA_KEYWORDS = [
        'panthapath',
        'hatirpool',
        'multiplan',
        'elephant road',
        'new market',
        'paltan',
        'motijheel',
        'gulistan',
        'idb',
        'agargaon',
        'farmgate',
        'chawk bazar',
        'agrabad',
    ];

    private const EXTENDED_AREA_KEYWORDS = [
        'uttara',
        'bashundhara',
        'aftabnagar',
        'badda',
        'demra',
        'jatrabari',
        'keraniganj',
        'savar',
        'tongi',
        'ashulia',
        'halishahar',
        'patenga',
        'airport',
        'sector',
    ];

    private const TIER2_CITIES = [
        'chattogram',
        'chittagong',
        'gazipur',
        'narayanganj',
        'sylhet',
        'khulna',
        'rajshahi',
    ];

    /**
     * Resolve customer-facing marketplace fee percentage using Shop + Product + Location + Area.
     *
     * @param  array  $customerLocation  ['city' => ..., 'area' => ..., ...]
     * @param  array  $context           ['item_type' => ..., 'part_category' => ..., 'cart' => [...]]
     */
    public static function resolveOrderFeeRatePct(
        ?RepairShop $shop = null,
        array $customerLocation = [],
        array $context = []
    ): float {
        $shopBase = $shop?->effectiveCommissionRatePct() ?? VendorProfile::DEFAULT_COMMISSION_RATE;
        $productAdj = self::resolveProductCategoryAdjustment($context);
        $locationAdj = self::resolveLocationAdjustment($shop, $customerLocation);
        $areaAdj = self::resolveAreaAdjustment($shop, $customerLocation);

        $computed = $shopBase + $productAdj + $locationAdj + $areaAdj;

        return round(
            max(self::MIN_STANDARD_FEE_PCT, min(self::MAX_STANDARD_FEE_PCT, $computed)),
            2
        );
    }

    public static function resolveProductCategoryAdjustment(array $context = []): float
    {
        $itemType = strtolower((string) ($context['item_type'] ?? ''));
        if ($itemType === 'service_package') {
            return 0.00;
        }

        $singleCategory = trim((string) ($context['part_category'] ?? ''));
        if ($singleCategory !== '') {
            return self::adjustmentForCategoryName($singleCategory);
        }

        $cart = $context['cart'] ?? null;
        if (is_array($cart) && !empty($cart)) {
            $hasSparePart = false;
            $weightedSum = 0.0;
            $weightTotal = 0.0;
            $unresolvedSparePartIds = [];

            foreach ($cart as $item) {
                $type = strtolower((string) ($item['item_type'] ?? ''));
                $source = strtoupper((string) ($item['source'] ?? ''));
                if ($type !== 'spare_part' && $source !== 'SPARE_PART') {
                    continue;
                }
                $hasSparePart = true;
                $qty = (int) ($item['quantity'] ?? $item['qty'] ?? 1);
                $lineTotal = max(1.0, (float) ($item['line_total'] ?? ((float) ($item['price'] ?? 100) * max(1, $qty))));

                $cat = trim((string) ($item['part_category'] ?? ''));
                if ($cat === '' && !empty($item['category']) && str_starts_with((string) $item['category'], 'Spare Parts • ')) {
                    $cat = trim(substr((string) $item['category'], strlen('Spare Parts • ')));
                }

                $sparePartId = $item['spare_part_id'] ?? $item['spare_part_listing_id'] ?? null;
                if ($cat !== '') {
                    $weightedSum += self::adjustmentForCategoryName($cat) * $lineTotal;
                    $weightTotal += $lineTotal;
                } elseif (!empty($sparePartId)) {
                    $idKey = (int) $sparePartId;
                    $unresolvedSparePartIds[$idKey] = ($unresolvedSparePartIds[$idKey] ?? 0.0) + $lineTotal;
                } else {
                    $weightedSum += self::adjustmentForTitle((string) ($item['title'] ?? '')) * $lineTotal;
                    $weightTotal += $lineTotal;
                }
            }

            if (!$hasSparePart) {
                return 0.00;
            }

            if (!empty($unresolvedSparePartIds)) {
                $categoriesById = SparePartListing::whereIn('id', array_keys($unresolvedSparePartIds))
                    ->pluck('part_category', 'id');

                foreach ($unresolvedSparePartIds as $id => $lineTotal) {
                    $cat = (string) ($categoriesById[$id] ?? '');
                    if ($cat !== '') {
                        $weightedSum += self::adjustmentForCategoryName($cat) * $lineTotal;
                        $weightTotal += $lineTotal;
                    }
                }
            }

            if ($weightTotal > 0.0) {
                return round($weightedSum / $weightTotal, 2);
            }
        }

        $title = trim((string) ($context['title'] ?? ''));
        if ($title !== '') {
            return self::adjustmentForTitle($title);
        }

        return 0.00;
    }

    public static function resolveLocationAdjustment(?RepairShop $shop = null, array $customerLocation = []): float
    {
        $customerCity = self::normalizeGeoString((string) ($customerLocation['city'] ?? ''));
        $shopCity = self::normalizeGeoString((string) ($shop?->city ?? ''));

        $activeCity = $customerCity !== '' ? $customerCity : $shopCity;
        if ($activeCity === '' || $activeCity === 'dhaka') {
            $cityAdj = 0.00;
        } elseif (in_array($activeCity, self::TIER2_CITIES, true)) {
            $cityAdj = 0.35;
        } else {
            $cityAdj = 0.65;
        }

        $crossCityAdj = ($customerCity !== '' && $shopCity !== '' && $customerCity !== $shopCity)
            ? 0.40
            : 0.00;

        return round($cityAdj + $crossCityAdj, 2);
    }

    public static function resolveAreaAdjustment(?RepairShop $shop = null, array $customerLocation = []): float
    {
        $customerArea = self::normalizeGeoString((string) ($customerLocation['area'] ?? ''));
        $shopArea = self::normalizeGeoString((string) ($shop?->area ?? ''));

        $customerAdj = $customerArea !== '' ? self::adjustmentForAreaName($customerArea) : null;
        $shopAdj = $shopArea !== '' ? self::adjustmentForAreaName($shopArea) : null;

        if ($customerAdj !== null && $shopAdj !== null) {
            return round(($customerAdj + $shopAdj) * 0.5, 2);
        }

        return $customerAdj ?? $shopAdj ?? 0.00;
    }

    /**
     * Strictly rare first-shop promo check: true ONLY when an authenticated customer
     * has zero non-cancelled, non-rejected, non-draft orders with the target shop.
     */
    public static function isEligibleForFirstShopOrderPromo(?User $user, ?RepairShop $shop = null): bool
    {
        if (!$user || !$shop || !$shop->id) {
            return false;
        }

        $hasPriorOrderAtShop = RepairRequest::query()
            ->where('customer_id', $user->id)
            ->whereNotIn('status', [
                RepairRequest::STATUS_CANCELLED,
                RepairRequest::STATUS_REJECTED,
                RepairRequest::STATUS_DRAFT,
            ])
            ->where(function ($query) use ($shop) {
                $query->where('requested_shop_id', $shop->id)
                    ->orWhereHas('selectedBid', function ($bidQuery) use ($shop) {
                        $bidQuery->where('shop_id', $shop->id);
                    });
            })
            ->exists();

        return !$hasPriorOrderAtShop;
    }

    /**
     * Compute savings when a rare first-shop promo lowers the effective fee rate to 1.00%.
     *
     * @return array{effective_fee_rate_pct: float, standard_fee_amount: float, discounted_fee_amount: float, discount_amount: float}
     */
    public static function calculateFirstShopPromoDiscount(float $subtotal, float $activeFeeRatePct): array
    {
        $subtotal = max(0.0, $subtotal);
        $activeFeeRatePct = max(self::PROMO_FLOOR_FEE_PCT, $activeFeeRatePct);

        $standardFeeAmount = round($subtotal * ($activeFeeRatePct / 100), 2);
        $discountedFeeAmount = round($subtotal * (self::PROMO_FLOOR_FEE_PCT / 100), 2);
        $discountAmount = max(0.0, round($standardFeeAmount - $discountedFeeAmount, 2));

        return [
            'effective_fee_rate_pct' => self::PROMO_FLOOR_FEE_PCT,
            'standard_fee_amount' => $standardFeeAmount,
            'discounted_fee_amount' => $discountedFeeAmount,
            'discount_amount' => $discountAmount,
        ];
    }

    private static function adjustmentForCategoryName(string $category): float
    {
        if (in_array($category, self::HIGH_COMPLEXITY_CATEGORIES, true)) {
            return 0.75;
        }
        if (in_array($category, self::MID_TIER_CATEGORIES, true)) {
            return 0.25;
        }
        if (in_array($category, self::LOW_TICKET_CATEGORIES, true)) {
            return -0.75;
        }

        return 0.00;
    }

    private static function adjustmentForTitle(string $title): float
    {
        $lower = strtolower($title);
        if (str_contains($lower, 'display') || str_contains($lower, 'amoled') || str_contains($lower, 'oled') || str_contains($lower, 'lcd') || str_contains($lower, 'motherboard')) {
            return 0.75;
        }
        if (str_contains($lower, 'battery') || str_contains($lower, 'ssd') || str_contains($lower, 'nvme') || str_contains($lower, 'ram') || str_contains($lower, 'camera')) {
            return 0.25;
        }
        if (str_contains($lower, 'sim tray') || str_contains($lower, 'adhesive') || str_contains($lower, 'tweezers') || str_contains($lower, 'spudger') || str_contains($lower, 'flux')) {
            return -0.75;
        }

        return 0.00;
    }

    private static function adjustmentForAreaName(string $normalizedArea): float
    {
        foreach (self::REPAIR_HUB_AREA_KEYWORDS as $keyword) {
            if (str_contains($normalizedArea, $keyword)) {
                return -0.35;
            }
        }

        foreach (self::EXTENDED_AREA_KEYWORDS as $keyword) {
            if (str_contains($normalizedArea, $keyword)) {
                return 0.35;
            }
        }

        return 0.00;
    }

    private static function normalizeGeoString(string $value): string
    {
        $normalized = strtolower(trim($value));
        $normalized = str_replace(['chittagong'], ['chattogram'], $normalized);

        return $normalized;
    }
}

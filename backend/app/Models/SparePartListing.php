<?php

namespace App\Models;

use App\Services\GeolocationService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * ============================================================================
 * SPARE PART MARKETPLACE LISTING MODEL
 * ============================================================================
 * Dedicated multi-vendor spare parts marketplace (eBay / AliExpress / Etel / LaptopWorld UX):
 * - Attaches each listing to a specific seller shop/branch (`shop_id`) & vendor
 * - Groups identical parts across competing sellers (`product_group_key`)
 * - Supports Etel & LaptopWorld specification options (`has_specs`, `spec_options`)
 * - Supports Limited-Time & Indefinite Offer Prices (`offer_enabled`, `offer_starts_at`,
 *   `offer_ends_at` nullable for indefinite offers, strikethrough `normal_price`,
 *   and circular red `"Sale!"` product badge)
 * - Supports AliExpress-style progressive wholesale quantity discounts
 * - Computes location-aware courier delivery fee, ETA & landed "Best Deal" total
 * ============================================================================
 */
class SparePartListing extends Model
{
    use HasFactory;

    protected $fillable = [
        'shop_id',
        'vendor_profile_id',
        'slug',
        'product_group_key',
        'sku',
        'title',
        'part_category',
        'brand',
        'compatible_model',
        'condition_label',
        'image_url',
        'gallery_urls',
        'highlights',
        'description',
        'has_specs',
        'spec_label',
        'spec_options',
        'base_price',
        'normal_price',
        'max_price',
        'normal_max_price',
        'regular_price',
        'offer_enabled',
        'offer_price',
        'offer_discount_pct',
        'offer_starts_at',
        'offer_ends_at',
        'assembly_fee',
        'warranty_days',
        'stock_quantity',
        'sold_count',
        'wholesale_enabled',
        'wholesale_min_qty',
        'wholesale_base_discount_pct',
        'wholesale_step_qty',
        'wholesale_step_discount_pct',
        'wholesale_max_discount_pct',
        'handling_hours',
        'is_active',
    ];

    protected $casts = [
        'gallery_urls' => 'array',
        'highlights' => 'array',
        'has_specs' => 'boolean',
        'spec_options' => 'array',
        'base_price' => 'decimal:2',
        'normal_price' => 'decimal:2',
        'max_price' => 'decimal:2',
        'normal_max_price' => 'decimal:2',
        'regular_price' => 'decimal:2',
        'offer_enabled' => 'boolean',
        'offer_price' => 'decimal:2',
        'offer_discount_pct' => 'decimal:2',
        'offer_starts_at' => 'datetime',
        'offer_ends_at' => 'datetime',
        'assembly_fee' => 'decimal:2',
        'warranty_days' => 'integer',
        'stock_quantity' => 'integer',
        'sold_count' => 'integer',
        'wholesale_enabled' => 'boolean',
        'wholesale_min_qty' => 'integer',
        'wholesale_base_discount_pct' => 'decimal:2',
        'wholesale_step_qty' => 'integer',
        'wholesale_step_discount_pct' => 'decimal:2',
        'wholesale_max_discount_pct' => 'decimal:2',
        'handling_hours' => 'integer',
        'is_active' => 'boolean',
    ];

    public function shop()
    {
        return $this->belongsTo(RepairShop::class, 'shop_id');
    }

    public function vendorProfile()
    {
        return $this->belongsTo(VendorProfile::class, 'vendor_profile_id');
    }

    /**
     * Determine whether a limited-time or indefinite promotional offer is currently active.
     * - `offer_starts_at`: "From ___" (if null, active immediately)
     * - `offer_ends_at`: "To ___" (if null/empty, stays active indefinitely!)
     */
    public function isOfferActive(?Carbon $at = null): bool
    {
        if (!$this->offer_enabled) {
            return false;
        }

        $now = $at ?: now();

        if ($this->offer_starts_at && $now->lt($this->offer_starts_at)) {
            return false;
        }

        if ($this->offer_ends_at && $now->gt($this->offer_ends_at)) {
            return false;
        }

        $normal = $this->resolvedNormalBasePrice();
        if ($this->offer_price !== null && (float) $this->offer_price > 0 && (float) $this->offer_price < $normal) {
            return true;
        }

        if ($this->offer_discount_pct !== null && (float) $this->offer_discount_pct > 0) {
            return true;
        }

        return false;
    }

    public function getHasActiveOfferAttribute(): bool
    {
        return $this->isOfferActive();
    }

    /**
     * Normal (non-sale) base price before any active offer.
     */
    public function resolvedNormalBasePrice(): float
    {
        if ($this->normal_price !== null && (float) $this->normal_price > 0) {
            return round((float) $this->normal_price, 2);
        }

        if ($this->regular_price !== null && (float) $this->regular_price > (float) $this->base_price) {
            return round((float) $this->regular_price, 2);
        }

        return round((float) $this->base_price, 2);
    }

    /**
     * Effective starting price right now (applies active offer if within From..To window, else normal price).
     */
    public function effectiveBasePrice(?Carbon $at = null): float
    {
        $normal = (float) ($this->normal_price !== null && (float) $this->normal_price > 0
            ? $this->normal_price
            : $this->base_price);

        if (!$this->isOfferActive($at)) {
            return round($normal, 2);
        }

        if ($this->offer_price !== null && (float) $this->offer_price > 0 && (float) $this->offer_price < $normal) {
            return round((float) $this->offer_price, 2);
        }

        if ($this->offer_discount_pct !== null && (float) $this->offer_discount_pct > 0) {
            return round(max(1.0, $normal * (1 - ((float) $this->offer_discount_pct / 100))), 2);
        }

        return round((float) $this->base_price, 2);
    }

    /**
     * Effective maximum spec price right now (when `has_specs` is true).
     */
    public function effectiveMaxPrice(?Carbon $at = null): ?float
    {
        if (!$this->has_specs) {
            return null;
        }

        $options = $this->normalizedSpecOptions($at);
        if (empty($options)) {
            return $this->max_price !== null ? round((float) $this->max_price, 2) : null;
        }

        return round((float) max(array_column($options, 'price')), 2);
    }

    /**
     * Normalized array of specification options when `has_specs` is true.
     * Automatically applies active offer discount if `isOfferActive()` is true.
     */
    public function normalizedSpecOptions(?Carbon $at = null): array
    {
        if (!$this->has_specs || !is_array($this->spec_options)) {
            return [];
        }

        $offerActive = $this->isOfferActive($at);
        $offerPct = $offerActive ? $this->effectiveOfferPercent() : 0.0;

        $normalized = [];
        foreach ($this->spec_options as $opt) {
            if (!is_array($opt)) {
                continue;
            }
            $name = trim((string) ($opt['name'] ?? ''));
            $rawNormalPrice = round((float) ($opt['normal_price'] ?? $opt['price'] ?? 0), 2);
            if ($name === '' || $rawNormalPrice <= 0) {
                continue;
            }
            $code = trim((string) ($opt['code'] ?? ''));
            if ($code === '') {
                $code = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $name));
            }

            $effectivePrice = $rawNormalPrice;
            if ($offerActive) {
                if (isset($opt['offer_price']) && (float) $opt['offer_price'] > 0 && (float) $opt['offer_price'] < $rawNormalPrice) {
                    $effectivePrice = round((float) $opt['offer_price'], 2);
                } elseif ($offerPct > 0) {
                    $effectivePrice = round(max(1.0, $rawNormalPrice * (1 - ($offerPct / 100))), 2);
                }
            }

            $normalized[] = [
                'code' => $code,
                'name' => $name,
                'subtitle' => trim((string) ($opt['subtitle'] ?? '')),
                'price' => $effectivePrice,
                'normal_price' => $rawNormalPrice,
                'has_offer' => $effectivePrice < $rawNormalPrice,
                'regular_price' => isset($opt['regular_price']) && (float) $opt['regular_price'] > $effectivePrice
                    ? round((float) $opt['regular_price'], 2)
                    : ($rawNormalPrice > $effectivePrice ? $rawNormalPrice : null),
                'stock' => isset($opt['stock']) ? max(0, (int) $opt['stock']) : (int) $this->stock_quantity,
            ];
        }

        return $normalized;
    }

    /**
     * Percentage discount implied by the active offer (`offer_discount_pct` or `(normal - offer)/normal`).
     */
    public function effectiveOfferPercent(): float
    {
        if ($this->offer_discount_pct !== null && (float) $this->offer_discount_pct > 0) {
            return round((float) $this->offer_discount_pct, 2);
        }

        $normal = $this->resolvedNormalBasePrice();
        if ($normal > 0 && $this->offer_price !== null && (float) $this->offer_price > 0 && (float) $this->offer_price < $normal) {
            return round((($normal - (float) $this->offer_price) / $normal) * 100, 2);
        }

        return 0.0;
    }

    /**
     * Human-readable offer validity window label for UI badges.
     */
    public function offerWindowLabel(): ?string
    {
        if (!$this->isOfferActive()) {
            return null;
        }

        if ($this->offer_ends_at) {
            return 'Limited Offer · Ends ' . $this->offer_ends_at->format('M j, Y');
        }

        return 'Special Offer · Ongoing';
    }

    /**
     * Resolve a specific spec option by code or case-insensitive name.
     */
    public function resolveSpecOption(?string $specCode): ?array
    {
        if (!$this->has_specs) {
            return null;
        }

        $options = $this->normalizedSpecOptions();
        if (empty($options)) {
            return null;
        }

        $needle = strtolower(trim((string) $specCode));
        if ($needle === '') {
            return null;
        }

        foreach ($options as $opt) {
            if (strtolower($opt['code']) === $needle || strtolower($opt['name']) === $needle) {
                return $opt;
            }
        }

        return null;
    }

    /**
     * Resolve active unit price before wholesale discount (respecting active offer & spec selection).
     */
    public function resolveUnitPrice(?string $specCode = null): float
    {
        if ($this->has_specs && $specCode !== null && trim($specCode) !== '') {
            $opt = $this->resolveSpecOption($specCode);
            if ($opt) {
                return (float) $opt['price'];
            }
        }

        return $this->effectiveBasePrice();
    }

    /**
     * AliExpress-style progressive wholesale discount percentage:
     * Example default: 5% at 100+ qty, +2% more for every additional 100 qty, capped at max%.
     */
    public function calculateWholesaleDiscountPct(int $quantity): float
    {
        if (!$this->wholesale_enabled) {
            return 0.0;
        }

        $minQty = max(2, (int) ($this->wholesale_min_qty ?: 100));
        if ($quantity < $minQty) {
            return 0.0;
        }

        $basePct = max(0.0, (float) ($this->wholesale_base_discount_pct ?? 5.0));
        $stepQty = max(1, (int) ($this->wholesale_step_qty ?: 100));
        $stepPct = max(0.0, (float) ($this->wholesale_step_discount_pct ?? 2.0));
        $maxPct = max($basePct, min(80.0, (float) ($this->wholesale_max_discount_pct ?: 25.0)));

        $extraSteps = intdiv($quantity - $minQty, $stepQty);
        $pct = $basePct + ($extraSteps * $stepPct);

        return round(min($maxPct, $pct), 2);
    }

    /**
     * Full pricing breakdown for a given quantity & optional spec code.
     */
    public function calculatePricingBreakdown(int $quantity = 1, ?string $specCode = null): array
    {
        $qty = max(1, $quantity);
        $selectedSpec = $this->resolveSpecOption($specCode);
        $offerActive = $this->isOfferActive();

        $normalUnitPrice = $selectedSpec
            ? (float) ($selectedSpec['normal_price'] ?? $selectedSpec['price'])
            : $this->resolvedNormalBasePrice();

        $rawUnitPrice = $selectedSpec
            ? (float) $selectedSpec['price']
            : $this->effectiveBasePrice();

        $discountPct = $this->calculateWholesaleDiscountPct($qty);
        $effectiveUnitPrice = $discountPct > 0
            ? round($rawUnitPrice * (1 - ($discountPct / 100)), 2)
            : $rawUnitPrice;

        $rawSubtotal = round($rawUnitPrice * $qty, 2);
        $subtotal = round($effectiveUnitPrice * $qty, 2);
        $wholesaleSavings = max(0.0, round($rawSubtotal - $subtotal, 2));
        $offerSavings = ($offerActive && $normalUnitPrice > $rawUnitPrice)
            ? round(($normalUnitPrice - $rawUnitPrice) * $qty, 2)
            : 0.0;

        return [
            'quantity' => $qty,
            'spec_code' => $selectedSpec['code'] ?? null,
            'spec_name' => $selectedSpec['name'] ?? null,
            'normal_unit_price' => $normalUnitPrice,
            'raw_unit_price' => $rawUnitPrice,
            'has_active_offer' => $offerActive && ($normalUnitPrice > $rawUnitPrice),
            'offer_savings' => $offerSavings,
            'wholesale_discount_pct' => $discountPct,
            'effective_unit_price' => $effectiveUnitPrice,
            'raw_subtotal' => $rawSubtotal,
            'subtotal' => $subtotal,
            'wholesale_savings' => $wholesaleSavings,
            'is_wholesale_applied' => $discountPct > 0,
        ];
    }

    /**
     * Build AliExpress-style visual wholesale tier cards for product detail & vendor preview.
     */
    public function wholesaleTiersPreview(?float $unitPrice = null): array
    {
        if (!$this->wholesale_enabled) {
            return [];
        }

        $price = $unitPrice !== null && $unitPrice > 0 ? $unitPrice : $this->effectiveBasePrice();
        $minQty = max(2, (int) ($this->wholesale_min_qty ?: 100));
        $stepQty = max(1, (int) ($this->wholesale_step_qty ?: 100));

        $tiers = [
            [
                'min_qty' => 1,
                'max_qty' => $minQty - 1,
                'range_label' => '1 – ' . ($minQty - 1) . ' pcs',
                'discount_pct' => 0.0,
                'unit_price' => round($price, 2),
            ],
        ];

        for ($i = 0; $i < 4; $i++) {
            $tierStart = $minQty + ($i * $stepQty);
            $tierEnd = $i < 3 ? ($tierStart + $stepQty - 1) : null;
            $pct = $this->calculateWholesaleDiscountPct($tierStart);
            $tiers[] = [
                'min_qty' => $tierStart,
                'max_qty' => $tierEnd,
                'range_label' => $tierEnd
                    ? ($tierStart . ' – ' . $tierEnd . ' pcs')
                    : ('≥ ' . $tierStart . ' pcs'),
                'discount_pct' => $pct,
                'unit_price' => round($price * (1 - ($pct / 100)), 2),
            ];
        }

        return $tiers;
    }

    /**
     * Calculate Haversine distance, courier delivery charge, and estimated delivery ETA
     * between the seller shop and customer's active location.
     */
    public static function calculateDeliveryQuote(
        ?RepairShop $shop,
        array $customerLocation = [],
        string $deliveryType = 'REGULAR',
        int $handlingHours = 1
    ): array {
        $custLat = isset($customerLocation['lat']) && is_numeric($customerLocation['lat'])
            ? (float) $customerLocation['lat']
            : null;
        $custLng = isset($customerLocation['lng']) && is_numeric($customerLocation['lng'])
            ? (float) $customerLocation['lng']
            : null;

        if ($custLat === null || $custLng === null) {
            $fallbackLookup = ($customerLocation['area'] ?? '') ?: (($customerLocation['city'] ?? '') ?: 'Banani');
            $coords = GeolocationService::getAreaCoordinates((string) $fallbackLookup)
                ?? GeolocationService::getAreaCoordinates((string) ($customerLocation['city'] ?? 'Dhaka'))
                ?? ['lat' => 23.7937, 'lng' => 90.4066];
            $custLat = (float) $coords['lat'];
            $custLng = (float) $coords['lng'];
        }

        $shopLat = ($shop && $shop->latitude !== null) ? (float) $shop->latitude : null;
        $shopLng = ($shop && $shop->longitude !== null) ? (float) $shop->longitude : null;

        if ($shopLat === null || $shopLng === null) {
            $shopLookup = ($shop?->area ?: $shop?->city) ?: 'Dhaka';
            $shopCoords = GeolocationService::getAreaCoordinates((string) $shopLookup)
                ?? ['lat' => 23.8103, 'lng' => 90.4125];
            $shopLat = (float) $shopCoords['lat'];
            $shopLng = (float) $shopCoords['lng'];
        }

        $distanceKm = GeolocationService::calculateDistance($custLat, $custLng, $shopLat, $shopLng);
        $prepMinutes = max(15, $handlingHours * 30);

        if ($distanceKm <= 3.5) {
            $regularFee = 50.0;
            $transitMinutes = 35;
            $zoneLabel = 'Hyperlocal Courier';
        } elseif ($distanceKm <= 8.5) {
            $regularFee = 70.0;
            $transitMinutes = 55;
            $zoneLabel = 'Same-City Rider';
        } elseif ($distanceKm <= 18.0) {
            $regularFee = 95.0;
            $transitMinutes = 90;
            $zoneLabel = 'Metro Dispatch';
        } elseif ($distanceKm <= 45.0) {
            $regularFee = 130.0;
            $transitMinutes = 210;
            $zoneLabel = 'Suburban Hub';
        } elseif ($distanceKm <= 150.0) {
            $regularFee = 175.0;
            $transitMinutes = 720;
            $zoneLabel = 'Regional Linehaul';
        } else {
            $regularFee = 220.0;
            $transitMinutes = 1200;
            $zoneLabel = 'Inter-District Courier';
        }

        $expressFee = $regularFee + 70.0;
        $isExpress = strtoupper($deliveryType) === 'EXPRESS';
        $selectedFee = $isExpress ? $expressFee : $regularFee;

        $totalMinutes = $isExpress
            ? max(25, (int) round(($prepMinutes + $transitMinutes) * 0.65))
            : ($prepMinutes + $transitMinutes);

        if ($totalMinutes <= 90) {
            $etaLabel = $totalMinutes . ' min';
        } elseif ($totalMinutes <= 360) {
            $etaLabel = 'Same day · ~' . max(2, (int) round($totalMinutes / 60)) . ' hrs';
        } elseif ($totalMinutes <= 900) {
            $etaLabel = 'Next day · ~' . (int) round($totalMinutes / 60) . ' hrs';
        } else {
            $etaLabel = '1–2 days · ~' . (int) round($totalMinutes / 60) . ' hrs';
        }

        return [
            'distance_km' => round($distanceKm, 2),
            'distance_formatted' => GeolocationService::formatDistance($distanceKm),
            'delivery_fee' => round($selectedFee, 2),
            'regular_delivery_fee' => round($regularFee, 2),
            'express_delivery_fee' => round($expressFee, 2),
            'eta_minutes' => $totalMinutes,
            'eta_label' => $etaLabel,
            'zone_label' => $zoneLabel,
            'is_same_city' => $shop && !empty($customerLocation['city'])
                ? strcasecmp((string) $shop->city, (string) $customerLocation['city']) === 0
                : ($distanceKm <= 25.0),
        ];
    }

    /**
     * Convenience landed-cost calculation (`effective subtotal + delivery fee`) for a location.
     */
    public function calculateLandedQuote(
        array $customerLocation = [],
        int $quantity = 1,
        ?string $specCode = null,
        string $deliveryType = 'REGULAR'
    ): array {
        $pricing = $this->calculatePricingBreakdown($quantity, $specCode);
        $delivery = self::calculateDeliveryQuote(
            $this->relationLoaded('shop') ? $this->shop : $this->shop()->first(),
            $customerLocation,
            $deliveryType,
            (int) ($this->handling_hours ?: 1)
        );

        return array_merge($pricing, $delivery, [
            'landed_total' => round($pricing['subtotal'] + $delivery['delivery_fee'], 2),
            'landed_unit_equivalent' => round(($pricing['subtotal'] + $delivery['delivery_fee']) / max(1, $quantity), 2),
        ]);
    }

    /**
     * Allowed "very close" tolerance (in BDT) around the lowest landed total so two or more
     * competing deals whose total landed prices match or are nearly identical both earn "Best Deal".
     * Uses 2% of lowest landed total, bounded between ৳10 and ৳50.
     */
    public static function bestDealTolerance(float $bestLandedTotal): float
    {
        if ($bestLandedTotal <= 0) {
            return 10.0;
        }

        return min(50.0, max(10.0, round($bestLandedTotal * 0.02, 2)));
    }

    /**
     * Returns true when a candidate landed total matches or is within the close-price window of the best deal.
     */
    public static function isWithinBestDealWindow(float $candidateLandedTotal, float $bestLandedTotal): bool
    {
        return ($candidateLandedTotal - $bestLandedTotal) <= (self::bestDealTolerance($bestLandedTotal) + 0.001);
    }

    /**
     * Normalizes product titles into a canonical group key so competing vendor listings
     * automatically match for multi-seller Best Deal tagging.
     */
    public static function canonicalizeGroupKey(string $rawTitle): string
    {
        $clean = trim($rawTitle);
        $clean = preg_replace('/^(Original|Genuine|Brand\s+New|OEM|100%\s+Original|Replacement)\s+/i', '', (string) $clean);
        $clean = preg_replace('/\s+(Price\s+in\s+Bangladesh|In\s+Bangladesh|In\s+BD).*$/i', '', (string) $clean);

        return \Illuminate\Support\Str::slug((string) $clean);
    }
}

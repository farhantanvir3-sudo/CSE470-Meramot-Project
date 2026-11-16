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

    public const MAX_INSTALLATION_QTY = 3;

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
     * Whether this spare part supports optional branch/workshop installation.
     * Excludes raw tools/consumables/adhesives/chargers/cables that do not require repair bench fitting.
     */
    public function supportsWorkshopInstallation(): bool
    {
        if ((float) ($this->assembly_fee ?? 0) <= 0) {
            return false;
        }

        $category = trim((string) ($this->part_category ?? ''));
        if ($category === 'Tools, Consumables & Soldering') {
            return false;
        }

        $title = (string) ($this->title ?? '');
        if (preg_match('/\b(flux|solder|paste|screwdriver|tweezer|tape|glue|adhesive|multimeter|cleaner|isopropyl|mat|cable|adapter|charger)\b/i', $title)) {
            return false;
        }

        $shop = $this->relationLoaded('shop')
            ? $this->shop
            : ($this->shop_id ? $this->shop()->with('vendorProfile', 'servicePackages')->first() : null);

        if ($shop && $shop->isPureSparePartsWholesaler()) {
            return false;
        }

        return true;
    }

    /**
     * Maximum retail order quantity eligible for optional workshop installation.
     * Bulk/wholesale quantities (> 3 units) must order parts-only supply.
     */
    public function maxInstallationQuantity(): int
    {
        return self::MAX_INSTALLATION_QTY;
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

    /**
     * Clean scraped marketplace noise prefixes/suffixes, fix common catalog typos,
     * and append missing component nouns when scraped listings only named compatible device models.
     */
    public static function cleanCatalogTitle(string $rawTitle, string $partCategory = ''): string
    {
        $title = trim(preg_replace('/\s+/u', ' ', $rawTitle) ?? '');
        if ($title === '') {
            return '';
        }

        // Strip scraped storefront wrappers
        $title = preg_replace('/^buy\s+/i', '', $title) ?? $title;
        $title = preg_replace(
            '/(\.?\s*semsotai\s+ssd\s+price\s+in\s+bd|\s+laptopbd\.net\s+laptopbd|\s+at\s+laptop\s*bd|\s+price\s+in\s+bangladesh|\s+in\s+bangladesh|\s+in\s+bd)\.?\s*$/iu',
            '',
            $title
        ) ?? $title;

        // Fix common scraped catalog typos cleanly
        $replacements = [
            '/\bmohterboard\b/i' => 'Motherboard',
            '/\bspekaer\b/i' => 'Speaker',
            '/\bspeakr\b/i' => 'Speaker',
            '/\bspeker\b/i' => 'Speaker',
            '/\bkeybaord\b/i' => 'Keyboard',
            '/\bkeybord\b/i' => 'Keyboard',
            '/\bbatter\b/i' => 'Battery',
            '/\bscrow\s+driver\b/i' => 'Screwdriver',
            '/\bduble\s+fan\b/i' => 'Dual Fan',
            '/\bnew\s+coolin\b/i' => 'Cooling Fan',
            '/\bfron\s+part\b/i' => 'FRONT PART',
            '/\bhings\s+cover\b/i' => 'Hinge Cover',
            '/\bverticle\b/i' => 'Vertical',
            '/^00hw(\d+)/i' => '00HW$1',
        ];

        $title = preg_replace(array_keys($replacements), array_values($replacements), $title) ?? $title;
        $title = trim(preg_replace('/\s+/u', ' ', $title) ?? $title);

        if ($partCategory === 'Laptop Battery' && preg_match('/\b(battery|cell)\b/i', $title) !== 1) {
            $title = trim(preg_replace('/\s+laptops$/i', '', $title) ?? $title) . ' Laptop Battery';
        } elseif ($partCategory === 'Camera & Lens' && preg_match('/\b(camera|lens|glass)\b/i', $title) !== 1) {
            $title .= ' Camera Glass Lens';
        } elseif ($partCategory === 'Laptop Speaker' && preg_match('/\bspeaker\b/i', $title) !== 1) {
            $title .= ' Laptop Speaker';
        } elseif ($partCategory === 'Laptop Cooling Fan' && preg_match('/\b(fan|cooler|heatsink)\b/i', $title) !== 1) {
            $title .= ' Laptop Cooling Fan';
        }

        return trim($title);
    }

    /**
     * Strictly reject entire devices (phones, tablets, full laptops, Mini PCs, Bluetooth speakers),
     * external non-repair consumer accessories (stands, USB cooling pads, USB hubs), and QA/service placeholders.
     */
    public static function isWholeDeviceOrInvalidSeedItem(
        string $rawTitle,
        string $rawCategory = '',
        float $basePrice = 0.0
    ): bool {
        $title = self::cleanCatalogTitle($rawTitle);
        if ($title === '' || $basePrice <= 10.0) {
            return true;
        }

        // Exclude QA / POS dummy records & repair service ads
        if (
            preg_match('/^QA\s/i', $title) === 1
            || preg_match('/\b(mobile_pos|mobile_erp|tablet_pos|tablet_erp|desktop_pos|desktop_erp|test\s+product|service\s+in\s+bd|repair\s+service)\b/i', $title) === 1
        ) {
            return true;
        }

        // Exclude external desk stands, cooling pads, rechargeable desk fans, USB hubs, docks, Bose Bluetooth speakers, and laptop bags
        if (preg_match('/\b(laptop\s+stand|notebook\s+stand|desk\s+stand|tray\s+stand|folding\s+laptop\s+table|laptop\s+table|cooling\s+pad|cooler\s+pad|notepal|wind\s+pal|multi\s+core\s+x6|u\s+pal|rechargeable\s+fan|mini\s+fan|table\s+fan|desk\s+fan|stand\s+fan|usb\s+hub|docking\s+station|bose\s+mini|bose\s+soundlink|laptop\s+bag|backpack|sleeve|earbuds|neckband|smart\s*watch|power\s+bank|mouse|headphone|router)\b/i', $title) === 1) {
            return true;
        }

        // External aftermarket USB laptop cooler pads inside Laptop Cooling Fan category
        if (
            strcasecmp($rawCategory, 'Laptop Cooling Fan') === 0
            && preg_match('/\b(havit|fantech|redragon|gamdias|rapoo|cooler\s+master|deepcool|xtrike|aptech|blackcat|suntech|xtreme|logic\s+x1|a\.?tech|orico\s+nb15|wiwu\s+s400|nb339|n-191|phone\s+stand|fan\s+for\s+laptop\s+cooler)\b/i', $title) === 1
        ) {
            return true;
        }

        $isBoardOrCaddy = preg_match('/\b(motherboard|mainboard|logic\s+board|sub\s+board|caddy|enclosure|hdd\s+connector|display\s+part|casing|bottom\s+case)\b/i', $title) === 1;

        // Exclude full computers / laptops / Mini PCs / used full-box units unless it is a motherboard or drive caddy
        if (
            !$isBoardOrCaddy
            && preg_match('/\b(core[\s-]*i[3579]|core\s+[357]\b|dual\s+core|core\s*2\s*duo|quad\s+core|ryzen\s*[3579]?|celeron|pentium|ultra\s+[579]\b|rtx\s*\d+|gtx\s*\d+|radeon|grafix|dedicated\s+gpu|mac\s*mini|macmini|mini\s+pc|compaq\s+cq|touch\s+screen|full\s+box|battery\s+\d+%|8\/256|16\/512|16\/256|512\/16|256\/16)\b/i', $title) === 1
        ) {
            return true;
        }

        // Exclude titles bundling BOTH system RAM and SSD/HDD storage (full laptop listings)
        if (
            !$isBoardOrCaddy
            && preg_match('/\b(\d+\s*gb\s+(?:ddr\d\w*\s+)?ram|ram\s+\d+\s*gb)\b/i', $title) === 1
            && preg_match('/\b(\d+\s*(gb|tb)\s*(ssd|hdd|nvme|storage)|(ssd|hdd|nvme|storage)\s+\d+\s*(gb|tb))\b/i', $title) === 1
        ) {
            return true;
        }

        // Exclude whole laptops misfiled under SSD, RAM & Storage (unless it's explicitly a motherboard or caddy)
        if (
            strcasecmp($rawCategory, 'SSD, RAM & Storage') === 0
            && !$isBoardOrCaddy
            && preg_match('/\b(macbook|thinkpad|elitebook|probook|zbook|latitude|inspiron|vostro|xps|pavilion|spectre|envy|vivobook|zenbook|expertbook|legion|ideapad|yoga|surface\s+laptop|surface\s+pro)\b/i', $title) === 1
        ) {
            return true;
        }

        // Reject entire phones/tablets or uncategorized hub pages lacking any part or repair-tool keyword
        $partOrToolPattern = '/\b(display|lcd|oled|amoled|screen|touch|digitizer|glass|battery|cell|charging|charger|adapter|power\s+cord|power\s+cable|dc\s+port|dc\s+jack|power\s+jack|power\s+socket|usb\s*port|connector|flex|ribbon|cable|lvds|logic|board|pcb|motherboard|mainboard|ic|chip|camera|webcam|lens|backshell|back\s+cover|housing|frame|bezel|palmrest|hinge|hinges|casing|case\s+cover|touchpad|trackpad|sim|tray|holder|slot|speaker|loudspeaker|ringer|earpiece|buzzer|mic|microphone|vibrator|motor|sensor|fingerprint|button|key|keypad|keyboard|fan|cooler|heatsink|ssd|nvme|hdd|hard\s+disk|hard\s+drive|caddy|enclosure|ram|ddr[2345]|so-?dimm|screwdriver|solder|flux|paste|tweezer|tape|glue|adhesive|multimeter|tool|spudger|b-?7000|separator|rework)\b/i';
        $oemPartCodePattern = '/\b(b21n\d+|b31n\d+|c21n\d+|c31n\d+|a32n\d+|pa50\d+|00hw\d+|01av\d+|g3hta\d+|0chnhw|chnhw|pk230\d+|mm02xl|pl02xl|sd03xl|ss03xl|tf03xl|tt03xl|vr03xl|sp04xl|cm03xl|bt04xl|sn03xl|st03xl|33ydh|wdxor|f3ygt|4gvmp|3hwpp|3dddg|trhff|5sb0\w+|01yn\d+|al14a\d+)\b/i';

        if (
            in_array($rawCategory, ['Mobile Spare Parts', 'Accessories & Chargers', 'PC & Laptop Parts', ''], true)
            && preg_match($partOrToolPattern, $title) !== 1
            && preg_match($oemPartCodePattern, $title) !== 1
        ) {
            return true;
        }

        // Even inside specific categories like PC & Laptop Parts, reject bare laptop model pages with only Satellite A200 etc. unless battery/part context exists
        if (strcasecmp($rawCategory, 'Mobile Spare Parts') === 0) {
            // Remaining valid Mobile Spare Parts items must match a concrete component/tool keyword
            return preg_match($partOrToolPattern, $title) !== 1;
        }

        return false;
    }

    /**
     * Normalize noisy/duplicate catalog categories into clean canonical spare-part & tool categories.
     */
    public static function normalizePartCategory(string $rawCategory, string $title = ''): string
    {
        $cleanTitle = self::cleanCatalogTitle($title);
        $cat = trim($rawCategory);

        // Repair tools, soldering & adhesives always belong to Tools, Consumables & Soldering
        if (preg_match('/\b(screwdriver|solder|soldering|flux|tweezer|tweezers|multimeter|spudger|pry|b-?7000|separator|rework|repair\s+mat|mounting\s+tape|repair\s+kit)\b/i', $cleanTitle) === 1) {
            return 'Tools, Consumables & Soldering';
        }

        // Misplaced motherboards or HDD connectors inside SSD, RAM & Storage
        if ($cat === 'SSD, RAM & Storage' && preg_match('/\b(motherboard|mainboard|hdd\s+connector)\b/i', $cleanTitle) === 1) {
            return 'PC Display, Cable & Board';
        }

        // Misplaced loudspeakers inside Display & Touch
        if ($cat === 'Display & Touch' && preg_match('/\bloudspeaker\b/i', $cleanTitle) === 1) {
            return 'SIM Tray & Small Parts';
        }

        // Misplaced pure batteries inside Display & Touch
        if (
            $cat === 'Display & Touch'
            && preg_match('/\bbattery\b/i', $cleanTitle) === 1
            && preg_match('/\b(display|touch|lcd|oled|amoled|digitizer|screen)\b/i', $cleanTitle) !== 1
        ) {
            return 'Battery';
        }

        // Misplaced mobile backshells, SIM trays & charging logic across sibling mobile categories
        $mobileCats = ['Battery', 'Display & Touch', 'Charging Port & Flex', 'Backshell & Housing', 'Mobile Spare Parts'];
        if (in_array($cat, $mobileCats, true)) {
            if (
                preg_match('/\b(backshell|back\s*shell|middle\s*frame|back\s*cover)\b/i', $cleanTitle) === 1
                && preg_match('/\b(display|lcd|oled|amoled|screen|touch|digitizer)\b/i', $cleanTitle) !== 1
            ) {
                return 'Backshell & Housing';
            }
            if (
                preg_match('/\b(sim\s*tray|sim\s*card\s*tray|sim\s*holder)\b/i', $cleanTitle) === 1
                && preg_match('/\b(display|lcd|screen|backshell|housing|flex)\b/i', $cleanTitle) !== 1
            ) {
                return 'SIM Tray & Small Parts';
            }
            if (
                preg_match('/\bcharging\s*(?:logic|port\s*flex|flex|sub\s*board)\b/i', $cleanTitle) === 1
                && preg_match('/\bbattery\b/i', $cleanTitle) !== 1
            ) {
                return 'Charging Port & Flex';
            }
        }

        // Reclassify legacy catch-all categories
        if (in_array($cat, ['', 'Accessories & Chargers', 'PC & Laptop Parts', 'Mobile Spare Parts', 'Spare Part'], true)) {
            if (preg_match('/\b(dc\s+port|dc\s+power\s+jack|charging\s+port\s+dc|power\s+jack|lvds|display\s+cable|motherboard|mainboard|camera|webcam|hdd\s+connector|power\s+cable|power\s+cord)\b/i', $cleanTitle) === 1) {
                return 'PC Display, Cable & Board';
            }
            if (preg_match('/\b(ssd|nvme|m\.2|hdd|hard\s+disk|hard\s+drive|sata|ddr[2345]|so-?dimm|ram|caddy|enclosure)\b/i', $cleanTitle) === 1) {
                return 'SSD, RAM & Storage';
            }
            if (preg_match('/\b(battery|cell|b21n\d+|b31n\d+|c31n\d+|pa50\d+|00hw\d+|01av\d+|g3hta\d+|satellite\s+a200)\b/i', $cleanTitle) === 1) {
                return 'Laptop Battery';
            }
            if (preg_match('/\b(keyboard|backlight)\b/i', $cleanTitle) === 1) {
                return 'Laptop Keyboard';
            }
            if (preg_match('/\b(fan|cooler|heatsink)\b/i', $cleanTitle) === 1) {
                return 'Laptop Cooling Fan';
            }
            if (preg_match('/\b(speaker)\b/i', $cleanTitle) === 1) {
                return 'Laptop Speaker';
            }
            if (preg_match('/\b(casing|hinge|touchpad|trackpad|palmrest|bezel|bottom\s+case)\b/i', $cleanTitle) === 1) {
                return 'Laptop Casing & Touchpad';
            }
            if (preg_match('/\b(display|lcd|oled|amoled|digitizer)\b/i', $cleanTitle) === 1) {
                return 'Display & Touch';
            }
            if (preg_match('/\b(adapter|charger|magsafe)\b/i', $cleanTitle) === 1) {
                return 'Adapter & Charger';
            }

            return 'PC Display, Cable & Board';
        }

        return $cat;
    }

    /**
     * Resolve an accurate category- and brand-matched local product image when an item has no valid image URL
     * or previously fell back to an unrelated display thumbnail.
     */
    public static function resolveCategoryFallbackImage(string $category, string $title = '', string $brand = ''): string
    {
        $normCat = self::normalizePartCategory($category, $title);
        $cleanTitle = self::cleanCatalogTitle($title);

        if ($normCat === 'Tools, Consumables & Soldering') {
            if (preg_match('/\b(hot\s*air|rework|861dw)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/tool-hot-air-station.webp';
            }
            if (preg_match('/\bflux\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/tool-solder-flux.webp';
            }
            if (preg_match('/\b(solder\s*wire|rosin\s*core)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/tool-solder-wire.webp';
            }
            if (preg_match('/\bmultimeter\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/tool-multimeter.webp';
            }
            if (preg_match('/\b(b-?7000|adhesive|glue)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/tool-b7000-adhesive.webp';
            }
            if (preg_match('/\b(spudger|pry|opening)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/tool-pry-spudger-set.webp';
            }
            if (preg_match('/\b(separator|heating|s-918e)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/tool-screen-separator.webp';
            }
            if (preg_match('/\b(tweezer|tweezers)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/tool-precision-tweezers.webp';
            }
            if (preg_match('/\b(silicone|repair\s*mat|s-160)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/tool-silicone-repair-mat.webp';
            }
            if (preg_match('/\b(tape|300lse)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/tool-mounting-tape.webp';
            }
            return '/images/spare-parts/clean/tool-screwdriver-kit.webp';
        }

        if ($normCat === 'Adapter & Charger') {
            if (stripos($cleanTitle, 'surface') !== false) {
                return '/images/spare-parts/clean/charger-surface-magnetic.webp';
            }
            if (preg_match('/\b(type-c|usb-c|pd|magsafe)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/charger-usbc-adapter.webp';
            }
            return '/images/spare-parts/clean/charger-barrel-adapter.webp';
        }

        if ($normCat === 'Laptop Battery') {
            return '/images/spare-parts/clean/laptop-battery-internal.webp';
        }

        if ($normCat === 'Laptop Keyboard') {
            return '/images/spare-parts/clean/laptop-keyboard-us.webp';
        }

        if ($normCat === 'Laptop Cooling Fan') {
            return '/images/spare-parts/clean/laptop-cooling-fan.webp';
        }

        if ($normCat === 'Laptop Speaker') {
            return '/images/spare-parts/clean/laptop-speaker-set.webp';
        }

        if ($normCat === 'SSD, RAM & Storage') {
            if (preg_match('/\b(ram|ddr\d|ddr3l|so-?dimm|memory)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/ram-sodimm-module.webp';
            }
            if (preg_match('/\b(hdd|hard\s*disk|hard\s*drive|2\.5\s*sata|sata\s*hdd|caddy|enclosure)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/hdd-sata-25.webp';
            }
            return '/images/spare-parts/clean/ssd-nvme-m2.webp';
        }

        if ($normCat === 'Laptop Casing & Touchpad') {
            if (preg_match('/\b(touchpad|trackpad)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/laptop-trackpad.webp';
            }
            return '/images/spare-parts/clean/laptop-casing-bezel.webp';
        }

        if ($normCat === 'PC Display, Cable & Board') {
            if (preg_match('/\b(dc|jack|charging\s+port|power\s+port|power\s+socket)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/laptop-dc-jack-cable.webp';
            }
            if (preg_match('/\b(cable|lvds|edp|ribbon|flex|connector)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/laptop-lvds-cable.webp';
            }
            if (preg_match('/\b(motherboard|mainboard|logic\s+board)\b/i', $cleanTitle) === 1) {
                return '/images/spare-parts/clean/laptop-motherboard.webp';
            }
            return '/images/spare-parts/clean/laptop-lcd-panel.webp';
        }

        if ($normCat === 'Battery') {
            return '/images/spare-parts/clean/mobile-battery-cell.webp';
        }

        if ($normCat === 'Camera & Lens') {
            return '/images/spare-parts/clean/mobile-camera-module.webp';
        }

        if ($normCat === 'Backshell & Housing') {
            return '/images/spare-parts/clean/mobile-backshell-housing.webp';
        }

        if ($normCat === 'Charging Port & Flex') {
            return '/images/spare-parts/clean/mobile-charging-flex.webp';
        }

        if ($normCat === 'SIM Tray & Small Parts') {
            return '/images/spare-parts/clean/mobile-sim-tray.webp';
        }

        return '/images/spare-parts/clean/mobile-display-assembly.webp';
    }

    /**
     * Detect broken relative paths, missing local assets, generic/placeholder graphics,
     * cross-brand image filenames, and wrong-component filenames (e.g. Backshell photo on a Display listing).
     */
    public static function isMismatchedOrInvalidImageUrl(
        string $imageUrl,
        string $category,
        string $title,
        string $brand = ''
    ): bool {
        $url = trim($imageUrl);
        if ($url === '' || (!str_starts_with($url, 'http') && !str_starts_with($url, '/images/'))) {
            return true;
        }

        if (str_starts_with($url, '/images/')) {
            $localFile = public_path(ltrim(explode('?', $url, 2)[0], '/'));
            if (!is_file($localFile) || filesize($localFile) < 300) {
                return true;
            }
        }

        $cleanTitle = self::cleanCatalogTitle($title);
        $path = (string) (parse_url($url, PHP_URL_PATH) ?: $url);
        $base = strtolower(basename($path));

        if (
            str_contains($base, 'brandlogo')
            || str_contains($base, 'placeholder')
            || str_contains($base, 'products-frame')
            || preg_match('/^untitled-design/i', $base) === 1
            || preg_match('/^download\./i', $base) === 1
            || str_contains($base, 'no-image')
        ) {
            return true;
        }

        if (str_contains($base, 's21-fe-5g-display') && stripos($cleanTitle, 'S21 FE') === false) {
            return true;
        }

        $inferredBrand = strtolower(trim(self::inferSpecificBrandFromTitle($cleanTitle, $brand)));
        $exclusiveBrands = [
            'apple', 'samsung', 'xiaomi', 'oppo', 'vivo', 'realme', 'oneplus',
            'huawei', 'google', 'motorola', 'nothing', 'sony',
            'hp', 'dell', 'lenovo', 'asus', 'acer', 'msi', 'microsoft', 'toshiba', 'fujitsu',
        ];
        foreach ($exclusiveBrands as $otherBrand) {
            if ($otherBrand === $inferredBrand) {
                continue;
            }
            if (preg_match('/(?:^|[-_])' . preg_quote($otherBrand, '/') . '(?:[-_.]|$)/i', $base) === 1) {
                $brandMentionsTitle = stripos($cleanTitle, $otherBrand) !== false
                    || ($otherBrand === 'apple' && (stripos($cleanTitle, 'iphone') !== false || stripos($cleanTitle, 'macbook') !== false || stripos($cleanTitle, 'ipad') !== false))
                    || ($otherBrand === 'xiaomi' && (stripos($cleanTitle, 'redmi') !== false || stripos($cleanTitle, 'poco') !== false))
                    || ($otherBrand === 'google' && stripos($cleanTitle, 'pixel') !== false)
                    || ($otherBrand === 'hp' && (stripos($cleanTitle, 'compaq') !== false || stripos($cleanTitle, 'elitebook') !== false || stripos($cleanTitle, 'probook') !== false || stripos($cleanTitle, 'pavilion') !== false));
                if (!$brandMentionsTitle) {
                    return true;
                }
            }
        }

        if (
            str_contains($base, 'backshell')
            && preg_match('/\b(backshell|back\s*shell|back\s*glass|housing|middle\s*frame|back\s*cover|frame)\b/i', $cleanTitle) !== 1
        ) {
            return true;
        }
        if (
            preg_match('/(?:^|[-_])display(?:[-_.]|$)/i', $base) === 1
            && preg_match('/\bbattery\b/i', $cleanTitle) === 1
            && preg_match('/\b(display|lcd|oled|amoled|screen|touch|digitizer)\b/i', $cleanTitle) !== 1
        ) {
            return true;
        }
        if (str_contains($base, 'sim-tray') && stripos($cleanTitle, 'sim') === false) {
            return true;
        }

        return false;
    }

    /**
     * Resolve a verified product image URL for seeding: recovers comma-truncated DearIT photos,
     * attaches clean unbranded studio assets to repair tools, and swaps any mismatched URL.
     */
    public static function resolveListingImageUrl(
        string $rawImageUrl,
        string $category,
        string $title,
        string $brand = '',
        string $src = '',
        string $extId = ''
    ): string {
        $normCat = self::normalizePartCategory($category, $title);
        $trimmed = trim($rawImageUrl);

        if ($src === 'meramot_tools' || $normCat === 'Tools, Consumables & Soldering') {
            if ($trimmed === '' || self::isMismatchedOrInvalidImageUrl($trimmed, $normCat, $title, $brand)) {
                return self::resolveCategoryFallbackImage($normCat, $title, $brand);
            }
        }

        if ($trimmed !== '' && !str_starts_with($trimmed, 'http') && !str_starts_with($trimmed, '/images/') && $extId !== '') {
            $recoveredRel = '/images/spare-parts/dearit/dearit-' . preg_replace('/[^A-Za-z0-9_-]/', '', $extId) . '.webp';
            $recoveredDisk = public_path(ltrim($recoveredRel, '/'));
            if (is_file($recoveredDisk) && filesize($recoveredDisk) > 300) {
                return $recoveredRel;
            }
        }

        if (self::isMismatchedOrInvalidImageUrl($trimmed, $normCat, $title, $brand)) {
            return self::resolveCategoryFallbackImage($normCat, $title, $brand);
        }

        return $trimmed;
    }

    /**
     * Instance fallback image URL safe for `<img>` onerror handlers and gallery defaults.
     */
    public function fallbackImageUrl(): string
    {
        return self::resolveCategoryFallbackImage(
            (string) ($this->part_category ?? 'Display & Touch'),
            (string) ($this->title ?? ''),
            (string) ($this->brand ?? '')
        );
    }

    /**
     * Extract specific hardware/component brands from product titles when `brand` was left generic
     * (e.g., Sunon, Toshiba, Sony, Fujitsu, Gigabyte, Delta, Kingston, HTC, BlackBerry, Clevo, etc.).
     */
    public static function inferSpecificBrandFromTitle(string $title, string $currentBrand = ''): string
    {
        $trimmedCurrent = trim($currentBrand);
        $genericBrands = ['', 'PC / Laptop', 'PC Component', 'Universal', 'Generic', 'Other', 'Unknown'];
        if (!in_array($trimmedCurrent, $genericBrands, true)) {
            return $trimmedCurrent;
        }

        $brandPatterns = [
            'Sunon' => '/\bsunon\b/i',
            'Delta' => '/\bdelta\b/i',
            'Forcecon' => '/\bforcecon\b/i',
            'ADDA' => '/\badda\b/i',
            'Toshiba' => '/\b(toshiba|dynabook|pa50\d+)\b/i',
            'Sony' => '/\bsony\b/i',
            'Fujitsu' => '/\b(fujitsu|ah544|fpcbp\d+)\b/i',
            'Gigabyte' => '/\bgigabyte\b/i',
            'Samsung' => '/\bsamsung\b/i',
            'Apple' => '/\b(apple|macbook|magsafe|iphone|ipad|a1[23479]\d{2})\b/i',
            'Asus' => '/\b(asus|rog|zenbook|vivobook|expertbook|b21n\d+|b31n\d+|c21n\d+|c31n\d+|a32n\d+)\b/i',
            'Dell' => '/\b(dell|inspiron|latitude|vostro|xps|alienware|33ydh|wdxor|f3ygt|4gvmp|3hwpp|3dddg|trhff|m5y0x|t54fj|0chnhw)\b/i',
            'HP' => '/\b(hp|pavilion|elitebook|probook|envy|spectre|compaq|hstnn-|tpn-|mm02xl|pl02xl|sd03xl|ss03xl|tf03xl|tt03xl|vr03xl|sp04xl|cm03xl|bt04xl|sn03xl|st03xl|jc04|oa04|ri04|vi04)\b/i',
            'Lenovo' => '/\b(lenovo|thinkpad|ideapad|legion|yoga|00hw\d+|01av\d+|45n\d+|l20[a-z]\d+)\b/i',
            'Acer' => '/\b(acer|aspire|nitro|predator|swift|al14a\d+|ac14b\w+|ap18e\w+)\b/i',
            'MSI' => '/\bmsi\b/i',
            'Microsoft' => '/\b(microsoft|surface|g3hta\d+)\b/i',
            'Clevo' => '/\b(clevo|hasee)\b/i',
            'Avita' => '/\bavita\b/i',
            'Maibenben' => '/\bmaibenben\b/i',
            'Thunderobot' => '/\bthunderobot\b/i',
            'NEC' => '/\bnec\b/i',
            'Getac' => '/\b(getac|mitac)\b/i',
            'Razer' => '/\brazer\b/i',
            'Logitech' => '/\blogitech\b/i',
            'Corsair' => '/\bcorsair\b/i',
            'Kingston' => '/\bkingston\b/i',
            'Crucial' => '/\bcrucial\b/i',
            'WD' => '/\b(wd|western\s+digital)\b/i',
            'Seagate' => '/\bseagate\b/i',
            'Transcend' => '/\btranscend\b/i',
            'Adata' => '/\badata\b/i',
            'Orico' => '/\borico\b/i',
            'SK Hynix' => '/\b(sk\s*hynix|skhynix)\b/i',
            'Netac' => '/\bnetac\b/i',
            'SemsoTai' => '/\bsems?o?tai\b/i',
            'OCPC' => '/\bocpc\b/i',
            'KingSpec' => '/\bkingspec\b/i',
            'G.Skill' => '/\bg\.?\s*skill\b/i',
            'Patriot' => '/\bpatriot\b/i',
            'TeamGroup' => '/\b(team\s*elite|teamgroup|team\s+group)\b/i',
            'Apacer' => '/\bapacer\b/i',
            'PNY' => '/\bpny\b/i',
            'Warrior' => '/\bwarrior\b/i',
            'SanDisk' => '/\bsandisk\b/i',
            'Lexar' => '/\blexar\b/i',
            'Hikvision' => '/\bhikvision\b/i',
            'Baseus' => '/\bbaseus\b/i',
            'Ugreen' => '/\bugreen\b/i',
            'Anker' => '/\banker\b/i',
            'HTC' => '/\bhtc\b/i',
            'BlackBerry' => '/\bblackberry\b/i',
            'Xiaomi' => '/\b(xiaomi|redmi|poco|mi\s+|mijia)\b/i',
            'Oppo' => '/\boppo\b/i',
            'Vivo' => '/\bvivo\b/i',
            'Realme' => '/\brealme\b/i',
            'OnePlus' => '/\b(oneplus|one\s+plus)\b/i',
            'Google' => '/\b(google|pixel)\b/i',
            'Motorola' => '/\b(motorola|moto\s+)\b/i',
            'Huawei' => '/\bhuawei\b/i',
            'Nokia' => '/\bnokia\b/i',
            'Infinix' => '/\binfinix\b/i',
            'Tecno' => '/\btecno\b/i',
            'Itel' => '/\bitel\b/i',
            'Nothing' => '/\bnothing\b/i',
            'Walton' => '/\bwalton\b/i',
            'Symphony' => '/\bsymphony\b/i',
            'ZTE' => '/\bzte\b/i',
            'Meizu' => '/\bmeizu\b/i',
            'LG' => '/\blg\b/i',
            'Honor' => '/\bhonor\b/i',
            'Kaisi' => '/\bkaisi\b/i',
            'Mechanic' => '/\bmechanic\b/i',
            'Quick' => '/\bquick\b/i',
            'UNI-T' => '/\buni-?t\b/i',
            'Relife' => '/\brelife\b/i',
            'Sunshine' => '/\bsunshine\b/i',
            'Zhanlida' => '/\bzhanlida\b/i',
        ];

        foreach ($brandPatterns as $canonicalBrand => $regex) {
            if (preg_match($regex, $title)) {
                return $canonicalBrand;
            }
        }

        // Never leak "PC / Laptop" or "PC Component" as a brand string
        if (in_array($trimmedCurrent, ['', 'PC / Laptop', 'PC Component', 'Other', 'Unknown'], true)) {
            return 'Universal';
        }

        return $trimmedCurrent;
    }

    /**
     * Normalize generic brands across existing `spare_part_listings` rows so the hierarchical
     * directory sidebar exposes real hardware & device brands instead of "PC / Laptop".
     */
    public static function normalizeGenericBrandsInDatabase(): int
    {
        $updated = 0;
        static::query()
            ->whereIn('brand', ['', 'PC / Laptop', 'PC Component', 'Universal', 'Generic', 'Other', 'Unknown'])
            ->select(['id', 'title', 'brand'])
            ->chunkById(500, function ($rows) use (&$updated) {
                $byNewBrand = [];
                foreach ($rows as $row) {
                    $inferred = self::inferSpecificBrandFromTitle((string) $row->title, (string) $row->brand);
                    if ($inferred !== (string) $row->brand) {
                        $byNewBrand[$inferred][] = $row->id;
                    }
                }
                foreach ($byNewBrand as $brandName => $ids) {
                    $updated += static::whereIn('id', $ids)->update(['brand' => substr($brandName, 0, 80)]);
                }
            });

        return $updated;
    }
}

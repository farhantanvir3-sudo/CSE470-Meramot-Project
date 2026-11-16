<?php

namespace Database\Seeders;

use App\Models\RepairShop;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * SPARE PARTS MARKETPLACE SEEDER
 * ============================================================================
 * Seeds the complete cloned catalogs from:
 *  1) `etel.com.bd` (~22,046 Mobile & Gadget Spare Parts with exact spec options)
 *  2) `laptopworlddhaka.com` (~859 Laptop & PC Spare Parts with exact spec options)
 *
 * Key Marketplace Scenarios Seeded:
 *  - Multi-vendor competing listings across Dhaka, Chattogram, Sylhet, Rajshahi,
 *    Khulna, Gazipur, and Cumilla workshops (with varying prices & specs so
 *    location-based Haversine courier fees flip "Best Deal" by customer location).
 *  - Limited-Time (`From ___ To ___`) & Indefinite (`From ___ To [empty]`) Offer
 *    Prices (`offer_enabled`, strikethrough `normal_price`, circular red `"Sale!"` badge).
 *  - AliExpress-style progressive wholesale quantity discounts (`5%` at `100+`,
 *    `+2%` per `100` extra units).
 * ============================================================================
 */
class SparePartSeeder extends Seeder
{
    public function run(): void
    {
        @ini_set('memory_limit', '512M');
        DB::disableQueryLog();

        $useCompactTestSample = app()->runningUnitTests();
        $gzPath = database_path('seeders/data/full_spare_parts_catalog.json.gz');
        $samplePath = database_path('seeders/data/etel_spare_parts_catalog.json');

        if (!$useCompactTestSample && file_exists($gzPath)) {
            $rawGz = file_get_contents($gzPath);
            $decodedStr = $rawGz !== false ? @gzdecode($rawGz) : false;
            unset($rawGz);
            $catalog = is_string($decodedStr) ? json_decode($decodedStr, true) : null;
            unset($decodedStr);
        } elseif (file_exists($samplePath)) {
            $catalog = json_decode((string) file_get_contents($samplePath), true);
        } else {
            $this->command?->warn('Spare parts catalog seed files not found.');
            return;
        }

        if (!is_array($catalog) || empty($catalog)) {
            $this->command?->warn('Spare parts catalog JSON is empty or invalid.');
            return;
        }

        // Resolve verified Meramot workshops across Dhaka & regional districts
        $bananiProtech = RepairShop::where('slug', 'protech-solutions')->first();
        $uttaraProtech = RepairShop::where('slug', 'protech-solutions-uttara')->first();
        $ctgProtech = RepairShop::where('slug', 'protech-solutions-agrabad')->first();

        $dhakaDoctors = RepairShop::where('slug', 'dhaka-device-doctors')->first()
            ?? RepairShop::where('city', 'Dhaka')->where('area', 'Dhanmondi')->first();
        $gulshanCare = RepairShop::where('slug', 'gulshan-gadget-care')->first()
            ?? RepairShop::where('slug', 'the-gadget-den')->first();
        $budgetFixer = RepairShop::where('slug', 'budget-fixer')->first();
        $mirpurLab = RepairShop::where('slug', 'mirpur-laptop-lab')->first()
            ?? RepairShop::where('city', 'Dhaka')->where('area', 'Mirpur')->first();
        $elephantWholesale = RepairShop::where('slug', 'elephant-road-parts-wholesale')->first();

        $sylhetShop = RepairShop::where('city', 'Sylhet')->where('is_active', true)->orderByDesc('rating')->first();
        $rajshahiShop = RepairShop::where('city', 'Rajshahi')->where('is_active', true)->orderByDesc('rating')->first();
        $khulnaShop = RepairShop::where('city', 'Khulna')->where('is_active', true)->orderByDesc('rating')->first();
        $gazipurShop = RepairShop::where('city', 'Gazipur')->where('is_active', true)->orderByDesc('rating')->first();
        $cumillaShop = RepairShop::where('city', 'Cumilla')->where('is_active', true)->orderByDesc('rating')->first();
        $ctgAltShop = RepairShop::where('city', 'Chattogram')
            ->where('slug', '!=', 'protech-solutions-agrabad')
            ->where('is_active', true)
            ->orderByDesc('rating')
            ->first();

        $dhakaPool = array_values(array_filter([
            $elephantWholesale,
            $bananiProtech,
            $dhakaDoctors,
            $uttaraProtech,
            $gulshanCare,
            $mirpurLab,
            $budgetFixer,
        ]));

        $regionalPool = array_values(array_filter([
            $ctgProtech,
            $sylhetShop,
            $rajshahiShop,
            $khulnaShop,
            $ctgAltShop,
            $cumillaShop,
        ]));

        $thirdPool = array_values(array_filter([
            $dhakaDoctors,
            $uttaraProtech,
            $gazipurShop,
            $gulshanCare,
            $mirpurLab,
            $bananiProtech,
        ]));

        if (empty($dhakaPool)) {
            $fallbackShops = RepairShop::where('is_active', true)->limit(6)->get()->all();
            if (empty($fallbackShops)) {
                $this->command?->warn('No active RepairShops found to attach spare part listings.');
                return;
            }
            $dhakaPool = $fallbackShops;
            $regionalPool = $fallbackShops;
            $thirdPool = $fallbackShops;
        }
        if (empty($regionalPool)) {
            $regionalPool = $dhakaPool;
        }
        if (empty($thirdPool)) {
            $thirdPool = $dhakaPool;
        }

        // Ensure every active RepairShop has a valid rating and SPARE_PARTS capability
        RepairShop::where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('rating')->orWhere('rating', '<=', 0);
            })
            ->update(['rating' => 4.7]);

        $participatingShops = collect(array_merge($dhakaPool, $regionalPool, $thirdPool))->unique('id');
        foreach ($participatingShops as $pShop) {
            $cats = is_array($pShop->categories) ? $pShop->categories : [];
            if (!in_array('SPARE_PARTS', $cats, true)) {
                $cats[] = 'SPARE_PARTS';
                $pShop->update(['categories' => array_values($cats)]);
            }
        }

        // Supplement with curated workshop repair tools, soldering consumables & adhesives
        $curatedRepairTools = [
            ['title' => 'Kaisi K-8126 126-in-1 Magnetic Precision Repair Screwdriver Kit', 'brand' => 'Kaisi', 'base_price' => 1450, 'warranty_days' => 90, 'ext_id' => 990001],
            ['title' => 'Quick 861DW 1000W Hot Air BGA Rework & Soldering Station', 'brand' => 'Quick', 'base_price' => 16500, 'warranty_days' => 180, 'ext_id' => 990002],
            ['title' => 'Mechanic UV50 BGA Solder Flux Paste (50g Jar)', 'brand' => 'Mechanic', 'base_price' => 380, 'warranty_days' => 30, 'ext_id' => 990003],
            ['title' => 'Mechanic 0.3mm Lead-Free Rosin Core Solder Wire Roll', 'brand' => 'Mechanic', 'base_price' => 520, 'warranty_days' => 30, 'ext_id' => 990004],
            ['title' => 'UNI-T UT89X True RMS Digital Multimeter for PCB Diagnostics', 'brand' => 'UNI-T', 'base_price' => 2850, 'warranty_days' => 180, 'ext_id' => 990005],
            ['title' => 'Zhanlida B-7000 Frame & Display Bonding Adhesive (110ml)', 'brand' => 'Zhanlida', 'base_price' => 240, 'warranty_days' => 30, 'ext_id' => 990006],
            ['title' => 'Relife RL-049B Anti-Static IC & Motherboard Pry Spudger Set', 'brand' => 'Relife', 'base_price' => 350, 'warranty_days' => 30, 'ext_id' => 990007],
            ['title' => 'Sunshine S-918E LCD & OLED Screen Heating Separator Pad', 'brand' => 'Sunshine', 'base_price' => 4200, 'warranty_days' => 180, 'ext_id' => 990008],
            ['title' => 'ESD-15 Stainless Steel Anti-Static Precision Micro Tweezers Set', 'brand' => 'Mechanic', 'base_price' => 290, 'warranty_days' => 30, 'ext_id' => 990009],
            ['title' => 'Mechanic S-160 Heat-Resistant Silicone Soldering Repair Mat', 'brand' => 'Mechanic', 'base_price' => 680, 'warranty_days' => 30, 'ext_id' => 990010],
            ['title' => '3M 300LSE Double-Sided Display & Battery Mounting Tape Roll', 'brand' => 'Universal', 'base_price' => 220, 'warranty_days' => 30, 'ext_id' => 990011],
        ];
        foreach ($curatedRepairTools as $toolRow) {
            $catalog[] = [
                'ext_id' => $toolRow['ext_id'],
                'src' => 'meramot_tools',
                'title' => $toolRow['title'],
                'part_category' => 'Tools, Consumables & Soldering',
                'brand' => $toolRow['brand'],
                'compatible_model' => 'Smartphones, Tablets & Laptop Motherboard Repair',
                'base_price' => $toolRow['base_price'],
                'regular_price' => round($toolRow['base_price'] * 1.15),
                'assembly_fee' => 0.00,
                'warranty_days' => $toolRow['warranty_days'],
                'image_url' => '',
                'highlights' => [
                    'Category: Tools, Consumables & Soldering',
                    'Workshop Grade Bench Tool / Consumable',
                    'Warranty: ' . $toolRow['warranty_days'] . ' Days',
                ],
                'description' => $toolRow['title'] . ' — professional electronics repair & micro-soldering tool for certified Meramot workshops.',
            ];
        }

        $now = now();
        $offerStartDefault = $now->copy()->subDays(3)->toDateTimeString();
        $offerEndFinite = $now->copy()->addDays(21)->endOfDay()->toDateTimeString();
        $nowStr = $now->toDateTimeString();

        $batchRows = [];
        $totalSeeded = 0;

        $upsertCols = [
            'shop_id',
            'vendor_profile_id',
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
            'updated_at',
        ];

        foreach ($catalog as $idx => $item) {
            $rawItemTitle = trim((string) ($item['title'] ?? ''));
            $rawItemCategory = trim((string) ($item['part_category'] ?? ''));
            $rawItemBase = (float) ($item['base_price'] ?? 0);

            // Strictly exclude entire phones/laptops/tablets, external stands/cooling pads, QA dummy rows & service ads
            if (\App\Models\SparePartListing::isWholeDeviceOrInvalidSeedItem($rawItemTitle, $rawItemCategory, $rawItemBase)) {
                continue;
            }

            $cleanTitle = \App\Models\SparePartListing::cleanCatalogTitle($rawItemTitle);
            $normalizedCategory = \App\Models\SparePartListing::normalizePartCategory($rawItemCategory, $cleanTitle);
            $cleanTitle = \App\Models\SparePartListing::cleanCatalogTitle($cleanTitle, $normalizedCategory);

            $groupKey = substr((string) ($item['product_group_key'] ?? Str::slug($cleanTitle ?: ('part-' . $idx))), 0, 160);
            $isPcItem = in_array(($item['src'] ?? ''), ['laptopworld', 'dearit', 'laptopbd', 'meramot_tools'], true);
            $hasSpecs = (bool) ($item['has_specs'] ?? false);

            // Seed 3 competing sellers for flagship items, all PC/Laptop items, and first 1,600 mobile items
            // (so ~2,500+ products have multi-vendor Best Deal competition across cities, while ALL 22,905 products are seeded!)
            $multiSeller = $useCompactTestSample || $isPcItem || $hasSpecs || $idx < 1600;

            if ($idx === 0 && $bananiProtech && $ctgProtech) {
                $sellers = array_values(array_filter([
                    ['shop' => $bananiProtech, 'role' => 'metro_exact'],
                    ['shop' => $ctgProtech, 'role' => 'regional_cheaper'],
                    ['shop' => $dhakaDoctors ?: $uttaraProtech, 'role' => 'alt_competitor'],
                ]));
            } elseif ($multiSeller) {
                $s1 = $dhakaPool[$idx % count($dhakaPool)];
                $s2 = $regionalPool[$idx % count($regionalPool)];
                $s3 = ($idx < 600 || $isPcItem) ? $thirdPool[($idx + 1) % count($thirdPool)] : null;

                $chosenIds = [];
                $sellers = [];
                foreach ([
                    ['shop' => $s1, 'role' => 'metro_exact'],
                    ['shop' => $s2, 'role' => 'regional_cheaper'],
                    ['shop' => $s3, 'role' => 'alt_competitor'],
                ] as $cand) {
                    if ($cand['shop'] && !in_array($cand['shop']->id, $chosenIds, true)) {
                        $chosenIds[] = $cand['shop']->id;
                        $sellers[] = $cand;
                    }
                }
            } else {
                // Distribute single-seller long-tail items across all Dhaka + Regional shops
                $allSellerRotate = array_merge($dhakaPool, $regionalPool);
                $picked = $allSellerRotate[$idx % count($allSellerRotate)];
                $sellers = [
                    ['shop' => $picked, 'role' => 'metro_exact'],
                ];
            }

            foreach ($sellers as $sIdx => $sellerMeta) {
                /** @var RepairShop $shop */
                $shop = $sellerMeta['shop'];
                $role = $sellerMeta['role'];

                $rawBase = (float) ($item['base_price'] ?? 500);
                $rawReg = isset($item['regular_price']) && (float) $item['regular_price'] > $rawBase
                    ? (float) $item['regular_price']
                    : round($rawBase * 1.16);

                if ($role === 'regional_cheaper') {
                    $discountAmount = min(150, max(35, round($rawBase * 0.055)));
                    if ($idx === 0) {
                        $sellerBase = 1650.0;
                    } else {
                        $sellerBase = max(79.0, round($rawBase - $discountAmount));
                    }
                    $multiplier = $rawBase > 0 ? ($sellerBase / $rawBase) : 0.95;
                } elseif ($role === 'alt_competitor') {
                    $markup = min(190, max(25, round($rawBase * 0.03)));
                    $sellerBase = round($rawBase + $markup);
                    $multiplier = $rawBase > 0 ? ($sellerBase / $rawBase) : 1.03;
                } else {
                    $sellerBase = $rawBase;
                    $multiplier = 1.0;
                }

                $specOptions = [];
                $sellerMaxPrice = null;

                if ($hasSpecs && !empty($item['spec_options']) && is_array($item['spec_options'])) {
                    foreach ($item['spec_options'] as $optIdx => $opt) {
                        $optRawPrice = (float) ($opt['price'] ?? $rawBase);
                        if ($role === 'metro_exact') {
                            $adjPrice = $optRawPrice;
                        } elseif ($optIdx === 0) {
                            $adjPrice = $sellerBase;
                        } else {
                            $adjPrice = max($sellerBase + 80, round(($optRawPrice * $multiplier) / 10) * 10 - ($isPcItem ? 0 : 1));
                        }

                        $adjRegular = isset($opt['regular_price']) && (float) $opt['regular_price'] > $adjPrice
                            ? round((float) $opt['regular_price'])
                            : round($adjPrice * 1.16);

                        $specOptions[] = [
                            'code' => (string) ($opt['code'] ?? Str::slug($opt['name'] ?? 'opt-' . $optIdx)),
                            'name' => (string) ($opt['name'] ?? 'Standard'),
                            'subtitle' => (string) ($opt['subtitle'] ?? ''),
                            'price' => $adjPrice,
                            'normal_price' => $adjPrice,
                            'regular_price' => $adjRegular,
                            'stock' => max(60, (int) (($opt['stock'] ?? 240) + ($sIdx * 35))),
                        ];
                    }

                    $prices = array_column($specOptions, 'price');
                    $sellerBase = (float) min($prices);
                    $sellerMaxPrice = (float) max($prices);
                }

                // Offer ("Sale!") Configuration:
                // LaptopWorld items with authentic strikethrough prices (e.g. VivoBook X515 Speaker 1500 -> 1250,
                // Dell 4GVMP Battery 3000 -> 2850) AND ~35% of marketplace listings feature active Sale offers
                // (half with a finite From..To window, half with indefinite `offer_ends_at = null`!).
                $hasAuthenticSale = $isPcItem && !$hasSpecs && $rawReg > $rawBase && $role === 'metro_exact';
                $enableOffer = $hasAuthenticSale || (!$hasSpecs && (($idx + $sIdx) % 3 === 0));

                $normalPrice = $sellerBase;
                $normalMaxPrice = $sellerMaxPrice;
                $offerPrice = null;
                $offerDiscountPct = null;
                $offerStartsAt = null;
                $offerEndsAt = null;

                if ($enableOffer) {
                    if ($hasAuthenticSale) {
                        $normalPrice = $rawReg;
                        $offerPrice = $sellerBase;
                    } else {
                        // Seller's normal price is higher, cut down to active offer price `sellerBase`
                        $normalPrice = max($sellerBase + 50, round($sellerBase * 1.15 / 10) * 10);
                        $offerPrice = $sellerBase;
                    }
                    $offerDiscountPct = round((($normalPrice - $offerPrice) / $normalPrice) * 100, 2);
                    $offerStartsAt = $offerStartDefault;
                    // Demonstrate both limited-time ("From ___ To ___") AND indefinite ("From ___ To [empty]") offers!
                    $offerEndsAt = (($idx + $sIdx) % 2 === 0) ? $offerEndFinite : null;
                }

                $wholesaleEnabled = ($idx === 0) || ((($idx + $sIdx) % 5) !== 4);
                $wholesaleBasePct = $role === 'alt_competitor' ? 6.00 : 5.00;

                $listingSlug = $sIdx === 0
                    ? $groupKey
                    : substr($groupKey, 0, 140) . '--' . $shop->slug;

                $resolvedBrand = \App\Models\SparePartListing::inferSpecificBrandFromTitle(
                    $cleanTitle,
                    (string) ($item['brand'] ?? 'Universal')
                );
                $skuPrefix = strtoupper(substr((string) preg_replace('/[^A-Za-z0-9]/', '', $resolvedBrand), 0, 3) ?: 'MRM');
                $sku = sprintf('%s-%d-S%d', $skuPrefix, (int) ($item['ext_id'] ?? $item['etel_id'] ?? ($idx + 1000)), $shop->id);

                $rawImg = trim((string) ($item['image_url'] ?? ''));
                $itemSrc = (string) ($item['src'] ?? '');
                $itemExtId = (string) ($item['ext_id'] ?? $item['etel_id'] ?? '');
                $resolvedImageUrl = \App\Models\SparePartListing::resolveListingImageUrl(
                    $rawImg,
                    $normalizedCategory,
                    $cleanTitle,
                    $resolvedBrand,
                    $itemSrc,
                    $itemExtId
                );

                $rawGallery = is_array($item['gallery_urls'] ?? null) && !empty($item['gallery_urls'])
                    ? $item['gallery_urls']
                    : [$resolvedImageUrl];
                $gallery = [];
                foreach ($rawGallery as $gEntry) {
                    $gStr = trim((string) $gEntry);
                    $gBroken = $gStr === ''
                        || (!str_starts_with($gStr, 'http') && !str_starts_with($gStr, '/images/'))
                        || \App\Models\SparePartListing::isMismatchedOrInvalidImageUrl($gStr, $normalizedCategory, $cleanTitle, $resolvedBrand);
                    $gallery[] = $gBroken ? $resolvedImageUrl : $gStr;
                }
                $gallery = array_values(array_unique(array_filter($gallery)));
                if (empty($gallery)) {
                    $gallery = [$resolvedImageUrl];
                }

                $merchantTitle = self::buildMerchantListingTitle(
                    $cleanTitle,
                    $resolvedBrand,
                    $normalizedCategory,
                    $groupKey,
                    $idx,
                    $sIdx,
                    $shop->slug
                );

                $batchRows[] = [
                    'slug' => $listingSlug,
                    'shop_id' => $shop->id,
                    'vendor_profile_id' => $shop->vendor_profile_id,
                    'product_group_key' => $groupKey,
                    'sku' => $sku,
                    'title' => substr($merchantTitle, 0, 250),
                    'part_category' => substr($normalizedCategory, 0, 80),
                    'brand' => substr($resolvedBrand, 0, 80),
                    'compatible_model' => substr((string) ($item['compatible_model'] ?? $cleanTitle), 0, 140),
                    'condition_label' => 'Brand New',
                    'image_url' => substr($resolvedImageUrl, 0, 500),
                    'gallery_urls' => json_encode($gallery, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'highlights' => json_encode(is_array($item['highlights'] ?? null) ? $item['highlights'] : [], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
                    'description' => (string) ($item['description'] ?? ''),
                    'has_specs' => $hasSpecs,
                    'spec_label' => $hasSpecs ? substr((string) ($item['spec_label'] ?? 'Quality'), 0, 60) : null,
                    'spec_options' => $hasSpecs ? json_encode($specOptions, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : null,
                    'base_price' => $sellerBase,
                    'normal_price' => $normalPrice,
                    'max_price' => $sellerMaxPrice,
                    'normal_max_price' => $normalMaxPrice,
                    'regular_price' => max($normalPrice, $rawReg),
                    'offer_enabled' => $enableOffer,
                    'offer_price' => $offerPrice,
                    'offer_discount_pct' => $offerDiscountPct,
                    'offer_starts_at' => $offerStartsAt,
                    'offer_ends_at' => $offerEndsAt,
                    'assembly_fee' => (isset($item['assembly_fee']) && $item['assembly_fee'] !== null && $normalizedCategory !== 'Tools, Consumables & Soldering')
                        ? (float) $item['assembly_fee']
                        : 0.00,
                    'warranty_days' => (int) ($item['warranty_days'] ?? 30),
                    'stock_quantity' => 500 + (($idx * 37 + $sIdx * 85) % 900),
                    'sold_count' => 12 + (($idx * 29 + $sIdx * 41) % 320),
                    'wholesale_enabled' => $wholesaleEnabled,
                    'wholesale_min_qty' => 100,
                    'wholesale_base_discount_pct' => $wholesaleBasePct,
                    'wholesale_step_qty' => 100,
                    'wholesale_step_discount_pct' => 2.00,
                    'wholesale_max_discount_pct' => 25.00,
                    'handling_hours' => $role === 'metro_exact' ? 1 : 2,
                    'is_active' => true,
                    'created_at' => $nowStr,
                    'updated_at' => $nowStr,
                ];

                $totalSeeded++;

                if (count($batchRows) >= 500) {
                    DB::table('spare_part_listings')->upsert($batchRows, ['slug'], $upsertCols);
                    $batchRows = [];
                }
            }
        }

        if (!empty($batchRows)) {
            DB::table('spare_part_listings')->upsert($batchRows, ['slug'], $upsertCols);
        }

        // Remove any legacy whole-device or excluded items (e.g. rechargeable fans) from pre-existing databases
        $removedInvalidCount = 0;
        \App\Models\SparePartListing::query()
            ->select(['id', 'title', 'part_category', 'base_price'])
            ->chunkById(1000, function ($rows) use (&$removedInvalidCount) {
                $invalidIds = [];
                foreach ($rows as $row) {
                    if (\App\Models\SparePartListing::isWholeDeviceOrInvalidSeedItem(
                        (string) $row->title,
                        (string) $row->part_category,
                        (float) $row->base_price
                    )) {
                        $invalidIds[] = $row->id;
                    }
                }
                if (!empty($invalidIds)) {
                    $removedInvalidCount += \App\Models\SparePartListing::whereIn('id', $invalidIds)->delete();
                }
            });

        $normalizedBrandsCount = \App\Models\SparePartListing::normalizeGenericBrandsInDatabase();
        if (\Illuminate\Support\Facades\Schema::hasTable('spare_part_warranties')) {
            if (DB::getDriverName() === 'pgsql') {
                DB::statement("
                    INSERT INTO spare_part_warranties (
                        spare_part_listing_id, shop_id, vendor_profile_id, duration_days,
                        coverage_type, covers_refund, covers_replacement, covers_free_repair,
                        terms, created_at, updated_at
                    )
                    SELECT
                        id, shop_id, vendor_profile_id, warranty_days,
                        'FULL_PROTECTION', TRUE, TRUE, TRUE,
                        ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
                    FROM spare_part_listings
                    WHERE warranty_days > 0
                    ON CONFLICT (spare_part_listing_id) DO NOTHING
                ", [\App\Services\WarrantyLifecycleService::DEFAULT_SPARE_PART_TERMS]);
            }
        }
        $this->command?->info("Seeded {$totalSeeded} multi-vendor Mobile & PC spare part listings (from " . count($catalog) . " cloned catalog items; removed {$removedInvalidCount} invalid items; normalized {$normalizedBrandsCount} specific brand names).");
    }

    /**
     * Build a realistic merchant title per competing vendor by shuffling the catalog title's
     * own words (no invented filler words), so identical items read like separately typed
     * listings and scatter cleanly under alphabetical sorting. Seller #0 keeps the catalog title.
     */
    public static function buildMerchantListingTitle(
        string $cleanTitle,
        string $brand,
        string $category,
        string $groupKey,
        int $itemIndex,
        int $sellerIndex,
        string $shopSlug = ''
    ): string {
        $baseTitle = trim(preg_replace('/\s+/u', ' ', $cleanTitle) ?? '');
        if ($sellerIndex === 0 || strlen($baseTitle) < 4) {
            return self::truncateListingTitle($baseTitle, 250);
        }

        $candidates = self::buildShuffledTitleCandidates($baseTitle, $brand);
        if (count($candidates) <= 1) {
            return self::truncateListingTitle($baseTitle, 250);
        }

        // A shuffled title must never change how the listing is filtered or classified
        $baseInvalid = \App\Models\SparePartListing::isWholeDeviceOrInvalidSeedItem($baseTitle, $category, 1000.0);
        $baseSubKey = self::subCategoryKeyForTitle($baseTitle, $category);
        $validity = [0 => true];
        $isValid = static function (int $cIdx) use (&$validity, $candidates, $category, $baseInvalid, $baseSubKey): bool {
            if (!array_key_exists($cIdx, $validity)) {
                $title = $candidates[$cIdx];
                $validity[$cIdx] = \App\Models\SparePartListing::isWholeDeviceOrInvalidSeedItem($title, $category, 1000.0) === $baseInvalid
                    && self::subCategoryKeyForTitle($title, $category) === $baseSubKey;
            }
            return $validity[$cIdx];
        };

        $leadCharFn = static function (string $title): string {
            $norm = preg_replace('/^[^a-z0-9]+/i', '', strtolower(trim($title))) ?? '';
            return $norm !== '' ? substr($norm, 0, 1) : '';
        };
        $firstWordFn = static function (string $title): string {
            $parts = preg_split('/\s+/u', strtolower(trim($title)), 2);
            return preg_replace('/[^a-z0-9-]/', '', $parts[0] ?? '') ?? '';
        };

        // Greedy slot assignment for sellers 0..sellerIndex: each seller of the same product
        // gets a distinct word order, preferring a distinct leading character.
        $usedIndices = [];
        $usedFirstChars = [];
        $usedFirstWords = [];
        $chosen = $candidates[0];

        for ($slot = 0; $slot <= $sellerIndex; $slot++) {
            $pickedIdx = null;
            $passes = [
                fn (string $c) => !isset($usedFirstChars[$leadCharFn($c)]) && !isset($usedFirstWords[$firstWordFn($c)]),
                fn (string $c) => !isset($usedFirstWords[$firstWordFn($c)]),
                fn (string $c) => true,
            ];
            foreach ($passes as $accept) {
                foreach ($candidates as $cIdx => $cand) {
                    if (!isset($usedIndices[$cIdx]) && $accept($cand) && $isValid($cIdx)) {
                        $pickedIdx = $cIdx;
                        break 2;
                    }
                }
            }
            if ($pickedIdx === null) {
                $pickedIdx = 0;
            }

            $chosen = $candidates[$pickedIdx];
            $usedIndices[$pickedIdx] = true;
            $usedFirstChars[$leadCharFn($chosen)] = true;
            $usedFirstWords[$firstWordFn($chosen)] = true;
        }

        return self::truncateListingTitle($chosen, 250);
    }

    /**
     * Generate word-order variants of a title by rotating it at natural phrase boundaries
     * (component phrase, "for" clause, brand, part code, separators), keeping every word.
     *
     * @return array<int, string> Index 0 is always the original title.
     */
    private static function buildShuffledTitleCandidates(string $baseTitle, string $brand): array
    {
        $words = preg_split('/\s+/u', $baseTitle) ?: [];
        $n = count($words);
        if ($n < 2) {
            return [$baseTitle];
        }

        $lower = array_map(static fn ($w) => strtolower(trim($w, " ,.;:()[]")), $words);
        $brandLower = strtolower(trim($brand));
        $hasSpecificBrand = $brandLower !== '' && !in_array($brandLower, ['universal', 'generic', 'other', 'pc / laptop', 'pc component'], true);

        $componentPhrase = '/^(?:(?:OEM|Original|Genuine|Quality|Laptop|Notebook|CPU|GPU|USB|USB-C|Type-C|Internal)\s+)*'
            . '(?:Motherboard|Mainboard|Logic\s+Board|Charging|Flex|LVDS|Display|Screen|LCD|Keyboard|Battery|Cooling\s+Fan|Cooler|Fan|Heatsink|'
            . 'Speaker|Loudspeaker|Ear\s+Speaker|Adapter|Charger|Power\s+Adapter|Casing|Bottom\s+Casing|Backshell|Back\s+Cover|Back\s+Glass|Housing|'
            . 'Middle\s+Frame|Camera|Front\s+Camera|Back\s+Camera|SIM\s+Tray|Touchpad|Trackpad|Hinges?|SSD|RAM|HDD|DC\s+Jack|Fingerprint|Ribbon)\b/iu';
        $phraseAt = static fn (int $i): bool => $i >= 0 && $i < $n
            && preg_match($componentPhrase, implode(' ', array_slice($words, $i))) === 1;
        $isPartCode = static fn (string $w): bool => strlen($w) >= 4
            && preg_match('/\d/', $w) === 1
            && preg_match('/[a-z]/i', $w) === 1
            && preg_match('/^\d+(?:\.\d+)?(?:mah|mhz|ghz|gb|tb|hz|wh|w|v|a|mm|inch|in|pin|g)$/i', $w) !== 1;

        // Ordered cut points: most natural rotations first
        $cuts = [];
        for ($i = 1; $i < $n; $i++) {
            if ($phraseAt($i) && !$phraseAt($i - 1)) {
                $cuts[] = $i;
            }
        }
        for ($i = 1; $i < $n; $i++) {
            if ($lower[$i - 1] === 'for' || $lower[$i] === 'for') {
                $cuts[] = $i;
            }
        }
        if ($hasSpecificBrand) {
            foreach ($lower as $i => $w) {
                if ($w === $brandLower) {
                    $cuts[] = $i;
                    $cuts[] = $i + 1;
                }
            }
        }
        for ($i = 1; $i < $n; $i++) {
            if ($isPartCode($lower[$i])) {
                $cuts[] = $i;
            }
        }
        for ($i = 1; $i < $n; $i++) {
            if (in_array($lower[$i - 1], ['-', '–', '—', '|', '/'], true)) {
                $cuts[] = $i;
            }
        }
        $weakLeads = ['series', 'laptops', "laptop's", 'plus', 'pro', 'max', 'ultra', 'mini', 'lite', 'set', 'left', 'right', 'edition', 'gen', 'version', 'color', 'colour', 'layout'];
        for ($i = 1; $i < $n; $i++) {
            if (!in_array($lower[$i], $weakLeads, true)) {
                $cuts[] = $i;
            }
        }

        // Never cut inside a parenthesized group like "(ZBW00 LA-B481P)"
        $insideParens = [];
        $depth = 0;
        foreach ($words as $i => $w) {
            $insideParens[$i] = $depth > 0;
            $depth = max(0, $depth + substr_count($w, '(') - substr_count($w, ')'));
        }

        // Word orders: rotations at each cut, plus "first word + component + rest" swaps
        // so short titles like "Acer 3830 4830 Battery" still get a third variant.
        $orders = [];
        foreach (array_unique($cuts) as $cut) {
            if ($cut > 0 && $cut < $n && !$insideParens[$cut]) {
                $orders[] = array_merge(array_slice($words, $cut), array_slice($words, 0, $cut));
            }
        }
        for ($i = 2; $i < $n; $i++) {
            if (!$insideParens[$i] && $phraseAt($i) && !$phraseAt($i - 1)) {
                $orders[] = array_merge([$words[0]], array_slice($words, $i), array_slice($words, 1, $i - 1));
            }
        }

        $candidates = [$baseTitle];
        $connectors = ['for', 'with', 'and', '&', '-', '–', '—', '|', '/', '+', ',', 'of', 'in', 'to'];
        foreach ($orders as $rot) {
            while (!empty($rot) && in_array(strtolower(trim($rot[0], ',')), $connectors, true)) {
                array_shift($rot);
            }
            while (!empty($rot) && in_array(strtolower(trim(end($rot), ',')), $connectors, true)) {
                array_pop($rot);
            }
            if (count($rot) < 2) {
                continue;
            }

            // Unwrap a parenthesized code that now leads the title, and drop dangling punctuation
            $rot[0] = preg_replace('/^\((.+)\)$/u', '$1', $rot[0]) ?? $rot[0];
            if (str_starts_with($rot[0], '(') && !str_contains($rot[0], ')')) {
                $rot[0] = substr($rot[0], 1);
                foreach ($rot as $k => $tok) {
                    if ($k > 0 && str_contains($tok, ')')) {
                        $rot[$k] = preg_replace('/\)/', '', $tok, 1) ?? $tok;
                        break;
                    }
                }
            }
            $lastIdx = count($rot) - 1;
            $rot[$lastIdx] = rtrim($rot[$lastIdx], ',;:-–');

            // Lead word must be a real word or model number, never a dangling list item like "5290,"
            $lead = trim($rot[0], " .;:()[]");
            if ($lead === '' || preg_match('/^[a-z0-9]/i', $lead) !== 1 || str_ends_with($rot[0], ',')) {
                continue;
            }
            // Capitalize a plain lowercase lead word (keeps camel-case names like "iPhone" intact)
            if (preg_match('/^[a-z]+$/', $rot[0]) === 1) {
                $rot[0] = ucfirst($rot[0]);
            }

            $candidates[] = self::polishHumanTitleTypography(implode(' ', $rot), $brand);
        }

        return self::dedupeCleanTitles($candidates);
    }

    private static function subCategoryKeyForTitle(string $title, string $category): ?string
    {
        $probe = new \App\Models\SparePartListing();
        $probe->title = $title;
        $probe->part_category = $category;

        return \App\Http\Controllers\SparePartController::inferSubCategoryForListing($probe)['key'] ?? null;
    }

    /**
     * Normalize ALL CAPS / noisy lowercase scraped titles into realistic merchant casing
     * while preserving technical acronyms and part codes.
     */
    private static function polishHumanTitleTypography(string $title, string $brand = ''): string
    {
        $text = trim(preg_replace('/\s+/u', ' ', $title) ?? '');
        $text = preg_replace('/\bLaptop\'s\b/i', 'Laptops', $text) ?? $text;
        $text = preg_replace('/\bNotebook\'s\b/i', 'Notebooks', $text) ?? $text;
        $text = preg_replace('/\s+\(Copy\)$/i', '', $text) ?? $text;
        $text = trim($text, " \t\n\r\0\x0B.");

        $letters = preg_replace('/[^A-Za-z]/', '', $text) ?? '';
        if ($letters === '') {
            return $text;
        }
        $upperCount = strlen(preg_replace('/[^A-Z]/', '', $letters) ?? '');
        $lowerCount = strlen(preg_replace('/[^a-z]/', '', $letters) ?? '');
        $totalLetters = strlen($letters);

        $isScreamingOrFlat = ($upperCount / max($totalLetters, 1)) > 0.65 || ($lowerCount / max($totalLetters, 1)) > 0.85;
        if (!$isScreamingOrFlat) {
            return $text;
        }

        $exactMap = [
            'hp' => 'HP', 'lg' => 'LG', 'msi' => 'MSI', 'nec' => 'NEC', 'oem' => 'OEM',
            'ips' => 'IPS', 'oled' => 'OLED', 'amoled' => 'AMOLED', 'lcd' => 'LCD', 'led' => 'LED',
            'fhd' => 'FHD', 'uhd' => 'UHD', 'qhd' => 'QHD', 'hd' => 'HD', 'lvds' => 'LVDS',
            'cpu' => 'CPU', 'gpu' => 'GPU', 'ssd' => 'SSD', 'hdd' => 'HDD', 'ram' => 'RAM',
            'sodimm' => 'SODIMM', 'so-dimm' => 'SO-DIMM', 'nvme' => 'NVMe', 'pcie' => 'PCIe',
            'sata' => 'SATA', 'usb' => 'USB', 'usb-c' => 'USB-C', 'type-c' => 'Type-C',
            'us' => 'US', 'uk' => 'UK', 'dc' => 'DC', 'ac' => 'AC', 'sim' => 'SIM',
            'ic' => 'IC', 'pcb' => 'PCB', 'bga' => 'BGA', 'esd' => 'ESD', 'rgb' => 'RGB',
            'asus' => 'ASUS', 'lenovo' => 'Lenovo', 'dell' => 'Dell', 'acer' => 'Acer',
            'apple' => 'Apple', 'samsung' => 'Samsung', 'xiaomi' => 'Xiaomi', 'redmi' => 'Redmi',
            'poco' => 'Poco', 'oppo' => 'Oppo', 'vivo' => 'Vivo', 'realme' => 'Realme',
            'oneplus' => 'OnePlus', 'huawei' => 'Huawei', 'honor' => 'Honor', 'infinix' => 'Infinix',
            'tecno' => 'Tecno', 'google' => 'Google', 'microsoft' => 'Microsoft', 'adata' => 'ADATA',
            'semsotai' => 'SemsoTai', 'kingspec' => 'KingSpec', 'transcend' => 'Transcend',
            'thinkpad' => 'ThinkPad', 'ideapad' => 'IdeaPad', 'thinkbook' => 'ThinkBook',
            'vivobook' => 'VivoBook', 'zenbook' => 'ZenBook', 'expertbook' => 'ExpertBook',
            'probook' => 'ProBook', 'elitebook' => 'EliteBook', 'zbook' => 'ZBook',
            'macbook' => 'MacBook', 'iphone' => 'iPhone', 'ipad' => 'iPad', 'imac' => 'iMac',
            'for' => 'for', 'and' => 'and', 'with' => 'with', 'in' => 'in', 'of' => 'of',
        ];

        $tokens = preg_split('/(\s+)/u', $text, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [$text];
        $wordIndex = 0;
        foreach ($tokens as &$tok) {
            if (trim($tok) === '') {
                continue;
            }
            $lowerTok = strtolower($tok);
            if (isset($exactMap[$lowerTok])) {
                $tok = ($wordIndex === 0 && in_array($lowerTok, ['for', 'and', 'with', 'in', 'of'], true))
                    ? ucfirst($lowerTok)
                    : $exactMap[$lowerTok];
            } elseif (preg_match('/\d/', $tok) && preg_match('/[a-z]/i', $tok)) {
                // Preserve technical model/part codes with normalized metric suffixes
                $tok = preg_replace_callback('/^(\d+)(mhz|ghz|gb|tb|mah|hz|wh)$/i', fn ($m) => $m[1] . match (strtolower($m[2])) {
                    'mhz' => 'MHz',
                    'ghz' => 'GHz',
                    'gb' => 'GB',
                    'tb' => 'TB',
                    'mah' => 'mAh',
                    'hz' => 'Hz',
                    'wh' => 'Wh',
                }, strtoupper($tok)) ?? strtoupper($tok);
            } else {
                $tok = ucfirst($lowerTok);
            }
            $wordIndex++;
        }
        unset($tok);

        return trim(implode('', $tokens));
    }

    /**
     * @param array<int, string> $titles
     * @return array<int, string>
     */
    private static function dedupeCleanTitles(array $titles): array
    {
        $seen = [];
        $out = [];
        foreach ($titles as $t) {
            $cleaned = trim(preg_replace('/\s+/u', ' ', $t) ?? '');
            $cleaned = preg_replace('/\s+([,\.])/', '$1', $cleaned) ?? $cleaned;
            if ($cleaned === '' || strlen($cleaned) < 4) {
                continue;
            }
            $key = strtolower($cleaned);
            if (!isset($seen[$key])) {
                $seen[$key] = true;
                $out[] = self::truncateListingTitle($cleaned, 250);
            }
        }

        return $out;
    }

    private static function truncateListingTitle(string $title, int $maxLen = 250): string
    {
        if (strlen($title) <= $maxLen) {
            return $title;
        }
        $cut = substr($title, 0, $maxLen);
        $lastSpace = strrpos($cut, ' ');
        if ($lastSpace !== false && $lastSpace >= (int) ($maxLen * 0.6)) {
            $cut = substr($cut, 0, $lastSpace);
        }

        return rtrim($cut, " \t\n\r\0\x0B-–|,(");
    }
}

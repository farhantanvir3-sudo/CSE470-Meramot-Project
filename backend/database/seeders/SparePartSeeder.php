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

                $batchRows[] = [
                    'slug' => $listingSlug,
                    'shop_id' => $shop->id,
                    'vendor_profile_id' => $shop->vendor_profile_id,
                    'product_group_key' => $groupKey,
                    'sku' => $sku,
                    'title' => substr($cleanTitle, 0, 250),
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
        $this->command?->info("Seeded {$totalSeeded} multi-vendor Mobile & PC spare part listings (from " . count($catalog) . " cloned catalog items; removed {$removedInvalidCount} invalid items; normalized {$normalizedBrandsCount} specific brand names).");
    }
}

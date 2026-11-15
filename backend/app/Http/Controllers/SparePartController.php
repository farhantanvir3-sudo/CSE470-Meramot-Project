<?php

namespace App\Http\Controllers;

use App\Models\RepairShop;
use App\Models\SparePartListing;
use App\Models\VendorProfile;
use App\Services\BangladeshLocationResolver;
use App\Services\GeolocationService;
use App\Services\MarketplaceSearchEngine;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * SPARE PARTS DEDICATED MARKETPLACE CONTROLLER
 * ============================================================================
 * Powers:
 * 1. Public Spare Parts E-Commerce Marketplace (`GET /spare-parts`, `GET /spare-parts/{slug}`)
 *    - Full cloned catalogs from `etel.com.bd` (Mobile/Gadget parts) &
 *      `laptopworlddhaka.com` (PC/Laptop parts) across competing Meramot sellers
 *    - SQL-accelerated Haversine distance + Landed Best Deal (`Part + Delivery`)
 *    - Etel & LaptopWorld specification options (`Quality`: IPS / High Quality / OEM / Original)
 *    - AliExpress-style progressive wholesale discounts (e.g. 5% at 100+, +2% / 100)
 * 2. Vendor Dashboard Spare Parts Management (`POST/PUT/DELETE /vendor/spare-parts`)
 * ============================================================================
 */
class SparePartController extends Controller
{
    public const MOBILE_CATEGORIES = [
        'Display & Touch',
        'Battery',
        'Charging Port & Flex',
        'Camera & Lens',
        'Backshell & Housing',
        'SIM Tray & Small Parts',
    ];

    public const PC_CATEGORIES = [
        'Laptop Battery',
        'Laptop Keyboard',
        'Laptop Cooling Fan',
        'PC Display, Cable & Board',
        'SSD, RAM & Storage',
        'Laptop Speaker',
        'Laptop Casing & Touchpad',
        'Adapter & Charger',
    ];

    public const OTHER_CATEGORIES = [
        'Tools, Consumables & Soldering',
    ];

    public const PART_CATEGORIES = [
        'Display & Touch',
        'Battery',
        'Charging Port & Flex',
        'Camera & Lens',
        'Backshell & Housing',
        'SIM Tray & Small Parts',
        'Laptop Battery',
        'Laptop Keyboard',
        'Laptop Cooling Fan',
        'PC Display, Cable & Board',
        'SSD, RAM & Storage',
        'Laptop Speaker',
        'Laptop Casing & Touchpad',
        'Adapter & Charger',
        'Tools, Consumables & Soldering',
    ];

    /**
     * Root directory metadata + clean display labels + sub-categories for the hierarchical explorer sidebar.
     */
    public static function directorySchema(): array
    {
        return [
            [
                'key' => 'mobile',
                'label' => 'Mobile Replacement Parts',
                'categories' => [
                    'Display & Touch' => [
                        'label' => 'Display & Touch',
                        'sub_categories' => [
                            'amoled_oled' => 'AMOLED & OLED Screens',
                            'fold_pro' => 'Pro Max, Ultra & Fold Panels',
                            'ips_lcd' => 'IPS & LCD Assemblies',
                        ],
                    ],
                    'Battery' => [
                        'label' => 'Phone & Tablet Battery',
                        'sub_categories' => [
                            'apple_iphone' => 'iPhone & iPad Cells',
                            'flagship_series' => 'Galaxy S / Note / Pixel / OnePlus',
                            'android_series' => 'Xiaomi, Oppo, Vivo & Realme',
                        ],
                    ],
                    'Charging Port & Flex' => [
                        'label' => 'Charging Port & Flex',
                        'sub_categories' => [
                            'charging_pcb' => 'Charging Logic Boards & Ports',
                            'flex_ribbon' => 'Mainboard, Power & Volume Flex',
                        ],
                    ],
                    'Camera & Lens' => [
                        'label' => 'Camera & Lens',
                        'sub_categories' => [
                            'camera_module' => 'Front & Rear Camera Modules',
                            'camera_glass' => 'Camera Lens Glass & Bezels',
                        ],
                    ],
                    'Backshell & Housing' => [
                        'label' => 'Backshell & Housing',
                        'sub_categories' => [
                            'back_glass' => 'Back Glass & Battery Covers',
                            'housing_frame' => 'Middle Frames & Full Housing',
                        ],
                    ],
                    'SIM Tray & Small Parts' => [
                        'label' => 'SIM Tray & Small Parts',
                        'sub_categories' => [
                            'sim_holder' => 'SIM & SD Card Trays',
                            'audio_speaker' => 'Loudspeaker, Ringer & Earpiece',
                            'small_parts' => 'Side Keys, Sensors & Small Parts',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'pc',
                'label' => 'Laptop & PC Replacement Parts',
                'categories' => [
                    'Laptop Battery' => [
                        'label' => 'Laptop Battery',
                        'sub_categories' => [
                            'business_pro' => 'ThinkPad, Latitude & EliteBook',
                            'mac_surface_ultra' => 'MacBook, Surface, ZenBook & XPS',
                            'everyday_gaming' => 'Pavilion, IdeaPad & Inspiron',
                        ],
                    ],
                    'Laptop Keyboard' => [
                        'label' => 'Laptop Keyboard',
                        'sub_categories' => [
                            'backlit_keyboard' => 'Backlit Keyboards',
                            'standard_keyboard' => 'Standard US / UK Layout',
                        ],
                    ],
                    'Laptop Cooling Fan' => [
                        'label' => 'Laptop Cooling Fan',
                        'sub_categories' => [
                            'blower_fan' => 'Gaming & Dual-Fan Blowers',
                            'internal_cpu_gpu' => 'CPU & Heatsink Fans',
                        ],
                    ],
                    'PC Display, Cable & Board' => [
                        'label' => 'Display, Cable & Board',
                        'sub_categories' => [
                            'laptop_panel' => 'FHD / HD LED Laptop Screens',
                            'dc_power_jack' => 'DC Power Jacks & Ports',
                            'cable_board_cam' => 'LVDS Cables, Boards & Webcams',
                        ],
                    ],
                    'SSD, RAM & Storage' => [
                        'label' => 'SSD, RAM & Storage',
                        'sub_categories' => [
                            'nvme_ssd' => 'M.2 NVMe PCIe SSDs',
                            'so_dimm_ram' => 'DDR3L / DDR4 / DDR5 SO-DIMM RAM',
                            'sata_hdd_caddy' => '2.5" SATA SSD, HDD & Caddies',
                        ],
                    ],
                    'Laptop Speaker' => [
                        'label' => 'Laptop Speaker',
                        'sub_categories' => [
                            'left_right_pair' => 'Left + Right Speaker Pairs',
                            'internal_speaker' => 'Internal Speaker Modules',
                        ],
                    ],
                    'Laptop Casing & Touchpad' => [
                        'label' => 'Laptop Casing & Touchpad',
                        'sub_categories' => [
                            'touchpad_trackpad' => 'Trackpads & Touchpads',
                            'shell_hinge_bezel' => 'Bezels, Bottom Base & Hinges',
                        ],
                    ],
                    'Adapter & Charger' => [
                        'label' => 'Adapter & Charger',
                        'sub_categories' => [
                            'usb_c_surface_mag' => 'USB-C PD, Surface & MagSafe',
                            'oem_barrel_adapter' => 'OEM Barrel-Pin Adapters',
                        ],
                    ],
                ],
            ],
            [
                'key' => 'others',
                'label' => 'Repair Tools & Soldering',
                'categories' => [
                    'Tools, Consumables & Soldering' => [
                        'label' => 'Tools, Soldering & Adhesives',
                        'sub_categories' => [
                            'screwdrivers_pry' => 'Precision Screwdrivers & Kits',
                            'soldering_bga' => 'Soldering Stations, Flux & Wire',
                            'diagnostics_adhesives' => 'Multimeters, Adhesives & Tapes',
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Build a mutually-exclusive SQL expression returning each listing's sub-category slug,
     * plus flat lookup metadata so directory tree counts and `?sub_category=` filters stay 100% consistent.
     */
    private static function subCategorySqlCase(bool $isPg): string
    {
        $op = $isPg ? 'ILIKE' : 'LIKE';
        $specsCast = $isPg ? "COALESCE(spare_part_listings.spec_options::text, '')" : "COALESCE(spare_part_listings.spec_options, '')";
        $t = 'spare_part_listings.title';
        $b = 'spare_part_listings.brand';
        $c = 'spare_part_listings.part_category';

        $likeAny = function (array $terms, bool $includeSpecs = false) use ($op, $t, $specsCast): string {
            $parts = [];
            foreach ($terms as $term) {
                $safe = str_replace("'", "''", $term);
                $parts[] = "{$t} {$op} '%{$safe}%'";
                if ($includeSpecs) {
                    $parts[] = "{$specsCast} {$op} '%{$safe}%'";
                }
            }
            return '(' . implode(' OR ', $parts) . ')';
        };

        return "(CASE
            WHEN {$c} = 'Display & Touch' AND " . $likeAny(['AMOLED', 'OLED'], true) . " THEN 'amoled_oled'
            WHEN {$c} = 'Display & Touch' AND " . $likeAny(['Ultra', 'Pro Max', 'Fold', 'Flip', 'Pad', 'Tab']) . " THEN 'fold_pro'
            WHEN {$c} = 'Display & Touch' THEN 'ips_lcd'

            WHEN {$c} = 'Battery' AND {$b} = 'Apple' THEN 'apple_iphone'
            WHEN {$c} = 'Battery' AND ({$b} IN ('Google', 'OnePlus') OR " . $likeAny(['Galaxy S', 'Galaxy Note', 'Galaxy Z', 'Pixel']) . ") THEN 'flagship_series'
            WHEN {$c} = 'Battery' THEN 'android_series'

            WHEN {$c} = 'Charging Port & Flex' AND " . $likeAny(['Logic', 'Board', 'Sub', 'PCB', 'Connector', 'Port']) . " THEN 'charging_pcb'
            WHEN {$c} = 'Charging Port & Flex' THEN 'flex_ribbon'

            WHEN {$c} = 'Camera & Lens' AND " . $likeAny(['Glass', 'Lens', 'Ring', 'Bezel']) . " THEN 'camera_glass'
            WHEN {$c} = 'Camera & Lens' THEN 'camera_module'

            WHEN {$c} = 'Backshell & Housing' AND " . $likeAny(['Housing', 'Frame', 'Chassis', 'Body']) . " THEN 'housing_frame'
            WHEN {$c} = 'Backshell & Housing' THEN 'back_glass'

            WHEN {$c} = 'SIM Tray & Small Parts' AND " . $likeAny(['SIM', 'Tray', 'Holder', 'Slot']) . " THEN 'sim_holder'
            WHEN {$c} = 'SIM Tray & Small Parts' AND " . $likeAny(['Loudspeaker', 'Speaker', 'Ringer', 'Earpiece', 'Buzzer']) . " THEN 'audio_speaker'
            WHEN {$c} = 'SIM Tray & Small Parts' THEN 'small_parts'

            WHEN {$c} = 'Laptop Battery' AND " . $likeAny(['ThinkPad', 'Latitude', 'EliteBook', 'ProBook', 'Precision', 'X1']) . " THEN 'business_pro'
            WHEN {$c} = 'Laptop Battery' AND ({$b} IN ('Apple', 'Microsoft') OR " . $likeAny(['MacBook', 'Surface', 'ZenBook', 'XPS', 'Spectre', 'Envy', 'Yoga']) . ") THEN 'mac_surface_ultra'
            WHEN {$c} = 'Laptop Battery' THEN 'everyday_gaming'

            WHEN {$c} = 'Laptop Keyboard' AND " . $likeAny(['Backlit', 'Backlight', 'RGB', 'Illuminated']) . " THEN 'backlit_keyboard'
            WHEN {$c} = 'Laptop Keyboard' THEN 'standard_keyboard'

            WHEN {$c} = 'Laptop Cooling Fan' AND " . $likeAny(['Predator', 'ROG', 'Legion', 'Nitro', 'Gaming', 'Dual', 'GPU']) . " THEN 'blower_fan'
            WHEN {$c} = 'Laptop Cooling Fan' THEN 'internal_cpu_gpu'

            WHEN {$c} = 'PC Display, Cable & Board' AND (" . $likeAny(['Display', 'Screen', 'LED', 'LCD', 'FHD', 'UHD', 'Inch']) . " AND NOT " . $likeAny(['Cable', 'LVDS']) . ") THEN 'laptop_panel'
            WHEN {$c} = 'PC Display, Cable & Board' AND " . $likeAny(['DC ', 'Jack', 'Charging Port', 'Power Socket', 'Power Cable', 'Power Cord', 'Female Connector', 'HDD Connector']) . " THEN 'dc_power_jack'
            WHEN {$c} = 'PC Display, Cable & Board' THEN 'cable_board_cam'

            WHEN {$c} = 'SSD, RAM & Storage' AND " . $likeAny(['NVMe', 'M.2', 'PCIe']) . " THEN 'nvme_ssd'
            WHEN {$c} = 'SSD, RAM & Storage' AND " . $likeAny(['RAM', 'DDR', 'SO-DIMM', 'SODIMM', 'MHz', 'PC3', 'PC4']) . " THEN 'so_dimm_ram'
            WHEN {$c} = 'SSD, RAM & Storage' THEN 'sata_hdd_caddy'

            WHEN {$c} = 'Laptop Speaker' AND " . $likeAny(['Left', 'Right', 'Pair', 'Set', 'L&R', 'L+R']) . " THEN 'left_right_pair'
            WHEN {$c} = 'Laptop Speaker' THEN 'internal_speaker'

            WHEN {$c} = 'Laptop Casing & Touchpad' AND " . $likeAny(['Touchpad', 'Trackpad']) . " THEN 'touchpad_trackpad'
            WHEN {$c} = 'Laptop Casing & Touchpad' THEN 'shell_hinge_bezel'

            WHEN {$c} = 'Adapter & Charger' AND " . $likeAny(['USB-C', 'Type-C', 'Surface', 'MagSafe', 'Apple', ' PD']) . " THEN 'usb_c_surface_mag'
            WHEN {$c} = 'Adapter & Charger' THEN 'oem_barrel_adapter'

            WHEN {$c} = 'Tools, Consumables & Soldering' AND " . $likeAny(['Screwdriver', 'Pry', 'Spudger', 'Tweezer', 'Separator']) . " THEN 'screwdrivers_pry'
            WHEN {$c} = 'Tools, Consumables & Soldering' AND " . $likeAny(['Solder', 'Flux', 'Hot Air', 'Rework', 'Mat']) . " THEN 'soldering_bga'
            WHEN {$c} = 'Tools, Consumables & Soldering' THEN 'diagnostics_adhesives'

            ELSE 'general'
        END)";
    }

    public const POPULAR_BRANDS = [
        'Samsung',
        'Apple',
        'Xiaomi',
        'Oppo',
        'Vivo',
        'Realme',
        'OnePlus',
        'Asus',
        'Dell',
        'HP',
        'Lenovo',
        'Acer',
        'MSI',
        'Microsoft',
        'Google',
        'Infinix',
        'Tecno',
        'Motorola',
        'Huawei',
        'Nokia',
    ];

    /**
     * Public Spare Parts Marketplace Index (Items, NOT Shops).
     * SQL-accelerated to paginate 50,000+ multi-vendor listings in milliseconds.
     * Route: GET /spare-parts
     */
    public function index(Request $request)
    {
        $customerLocation = $this->resolveAndSyncCustomerLocation($request);
        $custLat = (float) ($customerLocation['lat'] ?? 23.7937);
        $custLng = (float) ($customerLocation['lng'] ?? 90.4066);

        $isPg = DB::connection()->getDriverName() === 'pgsql';
        $likeOp = $isPg ? 'ILIKE' : 'LIKE';

        $query = SparePartListing::query()
            ->select('spare_part_listings.*')
            ->join('repair_shops', 'repair_shops.id', '=', 'spare_part_listings.shop_id')
            ->where('spare_part_listings.is_active', true)
            ->where('repair_shops.is_active', true);

        // 1. Device Type segment (`all` | `mobile` | `pc` | `others` / `tools`)
        $deviceType = strtolower(trim((string) $request->input('device_type', 'all')));
        if ($deviceType === 'tools') {
            $deviceType = 'others';
        }
        if ($deviceType === 'pc') {
            $query->whereIn('spare_part_listings.part_category', self::PC_CATEGORIES);
        } elseif ($deviceType === 'mobile') {
            $query->whereIn('spare_part_listings.part_category', self::MOBILE_CATEGORIES);
        } elseif ($deviceType === 'others') {
            $query->whereIn('spare_part_listings.part_category', self::OTHER_CATEGORIES);
        } else {
            $deviceType = 'all';
        }

        // 2. Fuzzy, Tokenized & Typo-Tolerant Search query (`q`)
        $search = trim((string) $request->input('q', ''));
        $searchRelevanceOrder = MarketplaceSearchEngine::applySparePartSearch($query, $search);

        // 3. Part Category & Sub-Category filters (`part_category`, `sub_category`)
        $subCategoryCaseSql = self::subCategorySqlCase($isPg);
        $selectedCategory = trim((string) $request->input('part_category', 'All'));
        if ($selectedCategory !== '' && strcasecmp($selectedCategory, 'All') !== 0) {
            $query->where('spare_part_listings.part_category', $selectedCategory);
        } else {
            $selectedCategory = 'All';
        }

        $selectedSubCategory = strtolower(trim((string) $request->input('sub_category', '')));
        $selectedSubCategoryLabel = null;
        if ($selectedSubCategory !== '' && $selectedSubCategory !== 'all') {
            $validSubMap = [];
            foreach (self::directorySchema() as $rootDef) {
                foreach ($rootDef['categories'] as $catKey => $catMeta) {
                    foreach ($catMeta['sub_categories'] as $subKey => $subLabel) {
                        $validSubMap[$subKey] = ['category' => $catKey, 'label' => $subLabel];
                    }
                }
            }
            if (isset($validSubMap[$selectedSubCategory])) {
                $selectedSubCategoryLabel = $validSubMap[$selectedSubCategory]['label'];
                if ($selectedCategory === 'All') {
                    $selectedCategory = $validSubMap[$selectedSubCategory]['category'];
                    $query->where('spare_part_listings.part_category', $selectedCategory);
                }
                $query->whereRaw("{$subCategoryCaseSql} = ?", [$selectedSubCategory]);
            } else {
                $selectedSubCategory = '';
            }
        } else {
            $selectedSubCategory = '';
        }

        // 4. Brand filter (`brand`)
        $selectedBrand = trim((string) $request->input('brand', 'All'));
        if ($selectedBrand !== '' && strcasecmp($selectedBrand, 'All') !== 0) {
            $query->where('spare_part_listings.brand', $selectedBrand);
        } else {
            $selectedBrand = 'All';
        }

        // 5. Canonical product group comparison filter (`group`)
        $selectedGroup = trim((string) $request->input('group', ''));
        if ($selectedGroup !== '') {
            $query->where('spare_part_listings.product_group_key', $selectedGroup);
        }

        // 6. Specific seller shop filter (`seller_shop_id`)
        if ($request->filled('seller_shop_id') && ctype_digit((string) $request->input('seller_shop_id'))) {
            $query->where('spare_part_listings.shop_id', (int) $request->input('seller_shop_id'));
        }

        // 7. Quick Feature Toggles (`wholesale`, `has_specs`, `local_only`, `on_sale`)
        $wholesaleOnly = $request->boolean('wholesale');
        if ($wholesaleOnly) {
            $query->where('spare_part_listings.wholesale_enabled', true);
        }

        $specsOnly = $request->boolean('has_specs');
        if ($specsOnly) {
            $query->where('spare_part_listings.has_specs', true);
        }

        $localOnly = $request->boolean('local_only');
        if ($localOnly && !empty($customerLocation['city'])) {
            $query->where('repair_shops.city', $customerLocation['city']);
        }

        $onSaleOnly = $request->boolean('on_sale');
        $isOfferActiveSql = "(spare_part_listings.offer_enabled = true
            AND (spare_part_listings.offer_starts_at IS NULL OR spare_part_listings.offer_starts_at <= NOW())
            AND (spare_part_listings.offer_ends_at IS NULL OR spare_part_listings.offer_ends_at >= NOW())
            AND (
                (spare_part_listings.offer_price IS NOT NULL AND spare_part_listings.offer_price > 0)
                OR (spare_part_listings.offer_discount_pct IS NOT NULL AND spare_part_listings.offer_discount_pct > 0)
            ))";

        if ($onSaleOnly) {
            if ($isPg) {
                $query->whereRaw($isOfferActiveSql);
            } else {
                $query->where('spare_part_listings.offer_enabled', true);
            }
        }

        // Distinct product count before pagination
        $distinctProductCount = (clone $query)->distinct('spare_part_listings.product_group_key')->count('spare_part_listings.product_group_key');

        // Determine search context: "Best Deal" winner tags highlight when searching,
        // while ALL sort options (including lowest-first and highest-first prices) are always available!
        $hasSearchContext = ($search !== '' || $selectedGroup !== '');
        $requestedSort = strtolower(trim((string) $request->input('sort', '')));
        if ($requestedSort === 'fastest_delivery') {
            $requestedSort = 'fastest_eta';
        } elseif ($requestedSort === 'top_sold') {
            $requestedSort = 'popular';
        } elseif ($requestedSort === 'price_low') {
            $requestedSort = 'lowest_price';
        } elseif ($requestedSort === 'price_high') {
            $requestedSort = 'highest_price';
        }

        $allowedSorts = ['best_deal', 'lowest_price', 'highest_price', 'distance', 'fastest_eta', 'popular', 'wholesale', 'alphabetical'];
        $defaultSort = $hasSearchContext ? 'best_deal' : 'alphabetical';
        $sort = in_array($requestedSort, $allowedSorts, true) ? $requestedSort : $defaultSort;

        if ($searchRelevanceOrder !== null && ($sort === 'best_deal' || !($searchRelevanceOrder['all_matched'] ?? true))) {
            $query->orderByRaw($searchRelevanceOrder['sql'], $searchRelevanceOrder['bindings']);
        }

        if ($isPg) {
            $distSql = sprintf(
                '(6371.0 * acos(LEAST(1.0, GREATEST(-1.0, cos(radians(%F)) * cos(radians(COALESCE(repair_shops.latitude, 23.8103))) * cos(radians(COALESCE(repair_shops.longitude, 90.4125)) - radians(%F)) + sin(radians(%F)) * sin(radians(COALESCE(repair_shops.latitude, 23.8103)))))))',
                $custLat,
                $custLng,
                $custLat
            );

            $effectivePriceSql = "(CASE
                WHEN {$isOfferActiveSql} AND spare_part_listings.offer_price IS NOT NULL AND spare_part_listings.offer_price > 0
                    THEN spare_part_listings.offer_price
                WHEN {$isOfferActiveSql} AND spare_part_listings.offer_discount_pct IS NOT NULL AND spare_part_listings.offer_discount_pct > 0
                    THEN ROUND(COALESCE(spare_part_listings.normal_price, spare_part_listings.base_price) * (1.0 - (spare_part_listings.offer_discount_pct / 100.0)), 2)
                ELSE COALESCE(spare_part_listings.normal_price, spare_part_listings.base_price)
            END)";

            $feeSql = "(CASE WHEN {$distSql} <= 3.5 THEN 50.0 WHEN {$distSql} <= 8.5 THEN 70.0 WHEN {$distSql} <= 18.0 THEN 95.0 WHEN {$distSql} <= 45.0 THEN 130.0 WHEN {$distSql} <= 150.0 THEN 175.0 ELSE 220.0 END)";
            $etaSql = "(GREATEST(15, COALESCE(spare_part_listings.handling_hours, 1) * 30) + CASE WHEN {$distSql} <= 3.5 THEN 35 WHEN {$distSql} <= 8.5 THEN 55 WHEN {$distSql} <= 18.0 THEN 90 WHEN {$distSql} <= 45.0 THEN 210 WHEN {$distSql} <= 150.0 THEN 720 ELSE 1200 END)";
            $landedSql = "({$effectivePriceSql} + {$feeSql})";

            if ($sort === 'alphabetical') {
                $query->orderByRaw('LOWER(TRIM(spare_part_listings.title)) ASC')
                      ->orderBy('spare_part_listings.id', 'asc');
            } elseif ($sort === 'lowest_price') {
                $query->orderByRaw("{$effectivePriceSql} ASC")
                      ->orderByRaw("{$landedSql} ASC");
            } elseif ($sort === 'highest_price') {
                $query->orderByRaw("{$landedSql} DESC")
                      ->orderBy('spare_part_listings.id', 'asc');
            } elseif ($sort === 'distance') {
                $query->orderByRaw("{$distSql} ASC")
                      ->orderByRaw("{$landedSql} ASC");
            } elseif ($sort === 'fastest_eta') {
                $query->orderByRaw("{$etaSql} ASC")
                      ->orderByRaw("{$landedSql} ASC");
            } elseif ($sort === 'popular') {
                $query->orderByDesc('spare_part_listings.sold_count')
                      ->orderByRaw("{$landedSql} ASC");
            } elseif ($sort === 'wholesale') {
                $query->orderByDesc('spare_part_listings.wholesale_enabled')
                      ->orderByDesc('spare_part_listings.wholesale_base_discount_pct')
                      ->orderByRaw("{$landedSql} ASC");
            } else {
                $query->orderByRaw("{$landedSql} ASC")
                      ->orderByRaw("{$etaSql} ASC");
            }
        } else {
            if ($sort === 'alphabetical') {
                $query->orderByRaw('LOWER(TRIM(spare_part_listings.title)) ASC')
                      ->orderBy('spare_part_listings.id', 'asc');
            } elseif ($sort === 'highest_price') {
                $query->orderByDesc('spare_part_listings.base_price');
            } elseif ($sort === 'popular') {
                $query->orderByDesc('spare_part_listings.sold_count')
                      ->orderBy('spare_part_listings.base_price');
            } elseif ($sort === 'wholesale') {
                $query->orderByDesc('spare_part_listings.wholesale_enabled')
                      ->orderBy('spare_part_listings.base_price');
            } else {
                $query->orderBy('spare_part_listings.base_price');
            }
        }

        $listings = $query->with(['shop.vendorProfile', 'vendorProfile'])
            ->paginate(24)
            ->withQueryString();

        // Enrich ONLY the 24 current-page listings with exact group seller benchmarks
        $pageItems = $listings->getCollection();
        $pageGroupKeys = $pageItems->pluck('product_group_key')->unique()->values()->all();

        $groupSiblings = empty($pageGroupKeys)
            ? collect()
            : SparePartListing::with('shop')
                ->where('is_active', true)
                ->whereIn('product_group_key', $pageGroupKeys)
                ->whereHas('shop', fn ($sq) => $sq->where('is_active', true))
                ->get();

        $groupBenchmarks = [];
        foreach ($groupSiblings->groupBy('product_group_key') as $gKey => $groupItems) {
            $bestId = null;
            $bestLanded = PHP_FLOAT_MAX;
            $bestEta = PHP_INT_MAX;
            $minRawPrice = PHP_FLOAT_MAX;
            $cheapestRawListingIds = [];
            $quotesById = [];

            foreach ($groupItems as $gItem) {
                $quote = $gItem->calculateLandedQuote($customerLocation, 1);
                $quotesById[$gItem->id] = $quote;
                $landed = (float) $quote['landed_total'];
                $eta = (int) $quote['eta_minutes'];
                $raw = $gItem->effectiveBasePrice();

                if ($raw < $minRawPrice) {
                    $minRawPrice = $raw;
                }

                if ($landed < $bestLanded || ($landed === $bestLanded && $eta < $bestEta)) {
                    $bestLanded = $landed;
                    $bestEta = $eta;
                    $bestId = $gItem->id;
                }
            }

            $bestDealIds = [];
            $resolvedBestLanded = $bestLanded === PHP_FLOAT_MAX ? 0.0 : $bestLanded;
            $resolvedMinRaw = $minRawPrice === PHP_FLOAT_MAX ? 0.0 : $minRawPrice;
            $rawCloseTolerance = min(25.0, max(5.0, round($resolvedMinRaw * 0.015, 2)));

            foreach ($groupItems as $gItem) {
                $q = $quotesById[$gItem->id];
                $landed = (float) $q['landed_total'];
                $raw = $gItem->effectiveBasePrice();

                if (SparePartListing::isWithinBestDealWindow($landed, $resolvedBestLanded)) {
                    $bestDealIds[$gItem->id] = true;
                }
                if (($raw - $resolvedMinRaw) <= ($rawCloseTolerance + 0.001)) {
                    $cheapestRawListingIds[$gItem->id] = true;
                }
            }

            $groupBenchmarks[$gKey] = [
                'seller_count' => $groupItems->count(),
                'best_listing_id' => $bestId,
                'best_deal_ids' => $bestDealIds,
                'best_landed_total' => $resolvedBestLanded,
                'min_raw_price' => $resolvedMinRaw,
                'cheapest_raw_listing_ids' => $cheapestRawListingIds,
            ];
        }

        foreach ($pageItems as $item) {
            $quote = $item->calculateLandedQuote($customerLocation, 1);
            $bench = $groupBenchmarks[$item->product_group_key] ?? [
                'seller_count' => 1,
                'best_listing_id' => $item->id,
                'best_deal_ids' => [$item->id => true],
                'best_landed_total' => $quote['landed_total'],
                'min_raw_price' => (float) $item->base_price,
                'cheapest_raw_listing_ids' => [$item->id => true],
            ];

            $isBestDeal = $hasSearchContext && !empty($bench['best_deal_ids'][$item->id]);
            $isBestRawPrice = $hasSearchContext && !empty($bench['cheapest_raw_listing_ids'][$item->id]);

            $item->setAttribute('landed_quote', $quote);
            $item->setAttribute('seller_count', $bench['seller_count']);
            $item->setAttribute('is_best_deal', $isBestDeal);
            $item->setAttribute('is_best_raw_price', $isBestRawPrice);
            $item->setAttribute('best_landed_total', $bench['best_landed_total']);
            $item->setAttribute('min_raw_price', $bench['min_raw_price']);
            $item->setAttribute('is_cheapest_raw_but_far', ($hasSearchContext && $isBestRawPrice && !$isBestDeal));
            $item->setAttribute('commission_rate_pct', $item->shop?->effectiveCommissionRatePct() ?? VendorProfile::DEFAULT_COMMISSION_RATE);
        }

        // Minimalistic 3-level Hierarchical Folder Directory: Root Group -> Category Folder -> Sub-Categories & Brands
        $treeBase = SparePartListing::query()
            ->join('repair_shops', 'repair_shops.id', '=', 'spare_part_listings.shop_id')
            ->where('spare_part_listings.is_active', true)
            ->where('repair_shops.is_active', true);

        if ($deviceType === 'pc') {
            $treeBase->whereIn('spare_part_listings.part_category', self::PC_CATEGORIES);
        } elseif ($deviceType === 'mobile') {
            $treeBase->whereIn('spare_part_listings.part_category', self::MOBILE_CATEGORIES);
        } elseif ($deviceType === 'others') {
            $treeBase->whereIn('spare_part_listings.part_category', self::OTHER_CATEGORIES);
        }

        $rawBrandRows = (clone $treeBase)
            ->select('spare_part_listings.part_category', 'spare_part_listings.brand', DB::raw('COUNT(*) as total'))
            ->groupBy('spare_part_listings.part_category', 'spare_part_listings.brand')
            ->orderBy('spare_part_listings.part_category')
            ->orderBy('spare_part_listings.brand')
            ->get();

        $rawSubRows = (clone $treeBase)
            ->select(
                'spare_part_listings.part_category',
                DB::raw("{$subCategoryCaseSql} as sub_key"),
                DB::raw('COUNT(*) as total')
            )
            ->groupBy('spare_part_listings.part_category', DB::raw($subCategoryCaseSql))
            ->get();

        $subCountsByCat = [];
        foreach ($rawSubRows as $sr) {
            $subCountsByCat[(string) $sr->part_category][(string) $sr->sub_key] = (int) $sr->total;
        }

        $genericBrands = ['PC / Laptop', 'PC Component', 'Universal', 'Generic', 'Other', 'Unknown'];
        $categoryNodesByKey = [];
        foreach ($rawBrandRows->groupBy('part_category') as $catName => $rows) {
            $catTotal = (int) $rows->sum('total');
            $brandsList = $rows->map(fn ($r) => [
                'name' => in_array((string) $r->brand, ['PC / Laptop', 'PC Component'], true) ? 'Universal' : (string) $r->brand,
                'count' => (int) $r->total,
                'is_generic' => in_array((string) $r->brand, $genericBrands, true),
            ])
                ->groupBy('name')
                ->map(fn ($grp, $bName) => [
                    'name' => $bName,
                    'count' => (int) $grp->sum('count'),
                    'is_generic' => in_array($bName, $genericBrands, true),
                ])
                ->sort(function ($a, $b) {
                    if ($a['is_generic'] !== $b['is_generic']) {
                        return $a['is_generic'] <=> $b['is_generic'];
                    }
                    return strcasecmp($a['name'], $b['name']);
                })
                ->values()
                ->all();

            $categoryNodesByKey[(string) $catName] = [
                'name' => (string) $catName,
                'total' => $catTotal,
                'brands' => $brandsList,
                'sub_counts' => $subCountsByCat[(string) $catName] ?? [],
            ];
        }

        $directoryTree = [];
        $categoryBrandTree = [];
        $placedCategories = [];
        foreach (self::directorySchema() as $rootDef) {
            $rootCatNodes = [];
            $rootTotal = 0;
            foreach ($rootDef['categories'] as $catKey => $catMeta) {
                if (!isset($categoryNodesByKey[$catKey])) {
                    continue;
                }
                $placedCategories[$catKey] = true;
                $node = $categoryNodesByKey[$catKey];
                $rootTotal += $node['total'];

                $subNodes = [];
                foreach ($catMeta['sub_categories'] as $subKey => $subLabel) {
                    $subCount = (int) ($node['sub_counts'][$subKey] ?? 0);
                    if ($subCount > 0) {
                        $subNodes[] = [
                            'key' => $subKey,
                            'label' => $subLabel,
                            'count' => $subCount,
                        ];
                    }
                }

                $builtCat = [
                    'name' => $catKey,
                    'label' => $catMeta['label'],
                    'total' => $node['total'],
                    'sub_categories' => $subNodes,
                    'brands' => $node['brands'],
                ];
                $rootCatNodes[] = $builtCat;
                $categoryBrandTree[] = $builtCat;
            }

            if (!empty($rootCatNodes)) {
                $directoryTree[] = [
                    'key' => $rootDef['key'],
                    'label' => $rootDef['label'],
                    'total' => $rootTotal,
                    'categories' => $rootCatNodes,
                ];
            }
        }

        // Preserve any custom vendor-entered category cleanly under a fallback folder
        $extraCatNodes = [];
        $extraTotal = 0;
        foreach ($categoryNodesByKey as $catKey => $node) {
            if (!isset($placedCategories[$catKey])) {
                $extraTotal += $node['total'];
                $builtExtra = [
                    'name' => $catKey,
                    'label' => $catKey,
                    'total' => $node['total'],
                    'sub_categories' => [],
                    'brands' => $node['brands'],
                ];
                $extraCatNodes[] = $builtExtra;
                $categoryBrandTree[] = $builtExtra;
            }
        }
        if (!empty($extraCatNodes)) {
            $directoryTree[] = [
                'key' => 'custom',
                'label' => 'Workshop Custom Parts',
                'total' => $extraTotal,
                'categories' => $extraCatNodes,
            ];
        }

        $quickLocations = [
            ['city' => 'Dhaka', 'area' => 'Banani', 'label' => 'Banani, Dhaka', 'lat' => 23.7937, 'lng' => 90.4066],
            ['city' => 'Dhaka', 'area' => 'Uttara', 'label' => 'Uttara, Dhaka', 'lat' => 23.8759, 'lng' => 90.3795],
            ['city' => 'Dhaka', 'area' => 'Dhanmondi', 'label' => 'Dhanmondi, Dhaka', 'lat' => 23.7461, 'lng' => 90.3742],
            ['city' => 'Chattogram', 'area' => 'Agrabad', 'label' => 'Agrabad, Chattogram', 'lat' => 22.3264, 'lng' => 91.8155],
            ['city' => 'Sylhet', 'area' => 'Zindabazar', 'label' => 'Zindabazar, Sylhet', 'lat' => 24.8949, 'lng' => 91.8687],
            ['city' => 'Rajshahi', 'area' => 'Shaheb Bazar', 'label' => 'Shaheb Bazar, Rajshahi', 'lat' => 24.3653, 'lng' => 88.6003],
            ['city' => 'Khulna', 'area' => 'Shib Bari', 'label' => 'Shib Bari, Khulna', 'lat' => 22.8206, 'lng' => 89.5535],
            ['city' => 'Gazipur', 'area' => 'Joydebpur', 'label' => 'Joydebpur, Gazipur', 'lat' => 23.9999, 'lng' => 90.4203],
        ];

        $activeCategories = match ($deviceType) {
            'pc' => self::PC_CATEGORIES,
            'mobile' => self::MOBILE_CATEGORIES,
            'others' => self::OTHER_CATEGORIES,
            default => self::PART_CATEGORIES,
        };

        return view('spare-parts.index', [
            'listings' => $listings,
            'totalListingsCount' => $listings->total(),
            'distinctProductCount' => $distinctProductCount,
            'customerLocation' => $customerLocation,
            'quickLocations' => $quickLocations,
            'partCategories' => $activeCategories,
            'categoryBrandTree' => $categoryBrandTree,
            'directoryTree' => $directoryTree,
            'popularBrands' => self::POPULAR_BRANDS,
            'deviceType' => $deviceType,
            'selectedCategory' => $selectedCategory,
            'selectedSubCategory' => $selectedSubCategory,
            'selectedSubCategoryLabel' => $selectedSubCategoryLabel,
            'selectedBrand' => $selectedBrand,
            'selectedGroup' => $selectedGroup,
            'sort' => $sort,
            'search' => $search,
            'hasSearchContext' => $hasSearchContext,
            'wholesaleOnly' => $wholesaleOnly,
            'specsOnly' => $specsOnly,
            'localOnly' => $localOnly,
            'onSaleOnly' => $onSaleOnly,
        ]);
    }

    /**
     * Public Spare Part Listing Detail Page (Etel/LaptopWorld Spec Picker + AliExpress Wholesale + Multi-Seller Comparison).
     * Route: GET /spare-parts/{slug}
     */
    public function show(Request $request, string $slug)
    {
        $customerLocation = $this->resolveAndSyncCustomerLocation($request);

        $listing = SparePartListing::with(['shop.vendorProfile', 'vendorProfile'])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->first();

        if (!$listing) {
            $candidates = SparePartListing::with(['shop.vendorProfile', 'vendorProfile'])
                ->where('product_group_key', $slug)
                ->where('is_active', true)
                ->whereHas('shop', fn ($sq) => $sq->where('is_active', true))
                ->get();

            abort_if($candidates->isEmpty(), 404);

            $listing = $candidates->sortBy(function (SparePartListing $c) use ($customerLocation) {
                return $c->calculateLandedQuote($customerLocation, 1)['landed_total'];
            })->first();
        }

        $specOptions = $listing->normalizedSpecOptions();
        $requestedSpecCode = trim((string) $request->input('spec', ''));
        $selectedSpec = $requestedSpecCode !== '' ? $listing->resolveSpecOption($requestedSpecCode) : null;

        // Competing seller offers for the exact same canonical spare part (`product_group_key`)
        $competingListings = SparePartListing::with(['shop.vendorProfile', 'vendorProfile'])
            ->where('product_group_key', $listing->product_group_key)
            ->where('is_active', true)
            ->whereHas('shop', fn ($sq) => $sq->where('is_active', true))
            ->get()
            ->map(function (SparePartListing $sellerItem) use ($customerLocation, $selectedSpec) {
                $quote = $sellerItem->calculateLandedQuote(
                    $customerLocation,
                    1,
                    $selectedSpec['code'] ?? null
                );
                $sellerItem->setAttribute('landed_quote', $quote);
                return $sellerItem;
            })
            ->sortBy([
                fn ($a, $b) => ((float) $a->landed_quote['landed_total']) <=> ((float) $b->landed_quote['landed_total']),
                fn ($a, $b) => ((int) $a->landed_quote['eta_minutes']) <=> ((int) $b->landed_quote['eta_minutes']),
            ])
            ->values();

        $bestDealListing = $competingListings->first();
        $minCompetingLanded = $bestDealListing
            ? (float) $bestDealListing->landed_quote['landed_total']
            : 0.0;

        foreach ($competingListings as $comp) {
            $compLanded = (float) $comp->landed_quote['landed_total'];
            $comp->setAttribute(
                'is_best_deal',
                SparePartListing::isWithinBestDealWindow($compLanded, $minCompetingLanded)
            );
        }

        if ($request->boolean('best_deal') && $bestDealListing && $bestDealListing->id !== $listing->id) {
            $listing = $bestDealListing;
            $specOptions = $listing->normalizedSpecOptions();
            $selectedSpec = $requestedSpecCode !== '' ? $listing->resolveSpecOption($requestedSpecCode) : null;
        }

        $activeQuote = $listing->calculateLandedQuote(
            $customerLocation,
            max(1, (int) $request->input('qty', 1)),
            $selectedSpec['code'] ?? null
        );
        $unitQuoteForBestDeal = $listing->calculateLandedQuote(
            $customerLocation,
            1,
            $selectedSpec['code'] ?? null
        );
        $listing->setAttribute('landed_quote', $activeQuote);
        $listing->setAttribute(
            'is_best_deal',
            $bestDealListing
                ? SparePartListing::isWithinBestDealWindow((float) $unitQuoteForBestDeal['landed_total'], $minCompetingLanded)
                : true
        );

        $previewUnitPrice = $selectedSpec ? (float) $selectedSpec['price'] : $listing->effectiveBasePrice();
        $wholesaleTiers = $listing->wholesaleTiersPreview($previewUnitPrice);

        // Related spare parts (same category & brand first)
        $relatedListings = SparePartListing::with('shop')
            ->where('is_active', true)
            ->where('product_group_key', '!=', $listing->product_group_key)
            ->where('part_category', $listing->part_category)
            ->whereHas('shop', fn ($sq) => $sq->where('is_active', true))
            ->limit(16)
            ->get()
            ->groupBy('product_group_key')
            ->map(function ($group) use ($customerLocation) {
                return $group->map(function (SparePartListing $r) use ($customerLocation) {
                    $r->setAttribute('landed_quote', $r->calculateLandedQuote($customerLocation, 1));
                    return $r;
                })->sortBy(fn ($r) => $r->landed_quote['landed_total'])->first();
            })
            ->take(4)
            ->values();

        $quickLocations = [
            ['city' => 'Dhaka', 'area' => 'Banani', 'label' => 'Banani, Dhaka', 'lat' => 23.7937, 'lng' => 90.4066],
            ['city' => 'Chattogram', 'area' => 'Agrabad', 'label' => 'Agrabad, Chattogram', 'lat' => 22.3264, 'lng' => 91.8155],
            ['city' => 'Sylhet', 'area' => 'Zindabazar', 'label' => 'Zindabazar, Sylhet', 'lat' => 24.8949, 'lng' => 91.8687],
            ['city' => 'Rajshahi', 'area' => 'Shaheb Bazar', 'label' => 'Shaheb Bazar, Rajshahi', 'lat' => 24.3653, 'lng' => 88.6003],
            ['city' => 'Khulna', 'area' => 'Shib Bari', 'label' => 'Shib Bari, Khulna', 'lat' => 22.8206, 'lng' => 89.5535],
        ];

        $commissionRatePct = $listing->shop?->effectiveCommissionRatePct() ?? VendorProfile::DEFAULT_COMMISSION_RATE;
        $deviceSegment = in_array($listing->part_category, self::PC_CATEGORIES, true)
            ? ['key' => 'pc', 'label' => 'Laptop & PC Parts']
            : (in_array($listing->part_category, self::MOBILE_CATEGORIES, true)
                ? ['key' => 'mobile', 'label' => 'Mobile Parts']
                : ['key' => 'others', 'label' => 'Repair Tools & Soldering']);

        return view('spare-parts.show', [
            'listing' => $listing,
            'specOptions' => $specOptions,
            'selectedSpec' => $selectedSpec,
            'wholesaleTiers' => $wholesaleTiers,
            'activeQuote' => $activeQuote,
            'competingListings' => $competingListings,
            'bestDealListing' => $bestDealListing,
            'relatedListings' => $relatedListings,
            'customerLocation' => $customerLocation,
            'quickLocations' => $quickLocations,
            'commissionRatePct' => $commissionRatePct,
            'deviceSegment' => $deviceSegment,
        ]);
    }

    /**
     * Create a new Spare Part Listing from Vendor Dashboard.
     * Route: POST /vendor/spare-parts
     */
    public function store(Request $request)
    {
        $vendorProfile = $this->resolveAuthorizedVendorProfile($request);
        if (!$vendorProfile instanceof VendorProfile) {
            return $vendorProfile;
        }

        $payload = $this->validateVendorListingPayload($request, $vendorProfile);

        /** @var RepairShop $shop */
        $shop = $vendorProfile->shops()->where('id', $payload['shop_id'])->firstOrFail();

        $shopCats = is_array($shop->categories) ? $shop->categories : [];
        if (!in_array('SPARE_PARTS', $shopCats, true)) {
            $shopCats[] = 'SPARE_PARTS';
            $shop->update(['categories' => array_values($shopCats)]);
        }
        if (!$vendorProfile->spare_parts) {
            $vendorProfile->update(['spare_parts' => true]);
        }

        $baseSlug = Str::slug($payload['product_group_key'] . '--' . $shop->slug);
        $slug = $this->ensureUniqueSlug($baseSlug ?: ('spare-part-' . $shop->id . '-' . time()));

        $listing = SparePartListing::create(array_merge($payload, [
            'vendor_profile_id' => $vendorProfile->id,
            'slug' => $slug,
            'is_active' => true,
        ]));

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Spare part '{$listing->title}' listed under {$shop->name}" . ($shop->display_branch_tag ? " [{$shop->display_branch_tag}]" : '') . '.',
                'listing' => $listing,
            ], 201);
        }

        return redirect()->route('vendor.dashboard', [
            'tab' => 'spare_parts',
            'shop_id' => $request->input('return_shop_id', 'all'),
        ])->with('success', "Spare part '{$listing->title}' published under {$shop->name}" . ($shop->display_branch_tag ? " [{$shop->display_branch_tag}]" : '') . '!');
    }

    /**
     * Update an existing Spare Part Listing from Vendor Dashboard.
     * Route: PUT /vendor/spare-parts/{id}
     */
    public function update(Request $request, int $id)
    {
        $vendorProfile = $this->resolveAuthorizedVendorProfile($request);
        if (!$vendorProfile instanceof VendorProfile) {
            return $vendorProfile;
        }

        $ownedShopIds = $vendorProfile->shops()->pluck('id')->all();
        $listing = SparePartListing::whereIn('shop_id', $ownedShopIds)->where('id', $id)->firstOrFail();

        $payload = $this->validateVendorListingPayload($request, $vendorProfile, $listing);
        $listing->update($payload);

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Updated spare part '{$listing->title}'.",
                'listing' => $listing->fresh(),
            ]);
        }

        return redirect()->route('vendor.dashboard', [
            'tab' => 'spare_parts',
            'shop_id' => $request->input('return_shop_id', 'all'),
        ])->with('success', "Updated spare part listing '{$listing->title}'.");
    }

    /**
     * Toggle active/paused state of a Vendor Spare Part Listing.
     * Route: POST /vendor/spare-parts/{id}/toggle
     */
    public function toggleStatus(Request $request, int $id)
    {
        $vendorProfile = $this->resolveAuthorizedVendorProfile($request);
        if (!$vendorProfile instanceof VendorProfile) {
            return $vendorProfile;
        }

        $ownedShopIds = $vendorProfile->shops()->pluck('id')->all();
        $listing = SparePartListing::whereIn('shop_id', $ownedShopIds)->where('id', $id)->firstOrFail();

        $listing->update(['is_active' => !$listing->is_active]);
        $stateText = $listing->is_active ? 'activated' : 'paused';

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'is_active' => $listing->is_active,
                'message' => "Listing '{$listing->title}' {$stateText}.",
            ]);
        }

        return redirect()->route('vendor.dashboard', [
            'tab' => 'spare_parts',
            'shop_id' => $request->input('return_shop_id', 'all'),
        ])->with('success', "Listing '{$listing->title}' {$stateText}.");
    }

    /**
     * Delete a Vendor Spare Part Listing.
     * Route: DELETE /vendor/spare-parts/{id}
     */
    public function destroy(Request $request, int $id)
    {
        $vendorProfile = $this->resolveAuthorizedVendorProfile($request);
        if (!$vendorProfile instanceof VendorProfile) {
            return $vendorProfile;
        }

        $ownedShopIds = $vendorProfile->shops()->pluck('id')->all();
        $listing = SparePartListing::whereIn('shop_id', $ownedShopIds)->where('id', $id)->firstOrFail();
        $title = $listing->title;
        $listing->delete();

        if ($request->expectsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => "Removed spare part listing '{$title}'.",
            ]);
        }

        return redirect()->route('vendor.dashboard', [
            'tab' => 'spare_parts',
            'shop_id' => $request->input('return_shop_id', 'all'),
        ])->with('success', "Removed spare part listing '{$title}'.");
    }

    private function resolveAndSyncCustomerLocation(Request $request): array
    {
        $stored = $request->session()->get('customer_location', [
            'city' => 'Dhaka',
            'area' => 'Banani',
            'address' => 'Banani, Dhaka',
            'lat' => 23.7937,
            'lng' => 90.4066,
        ]);

        $hasExplicitCoords = $request->filled('lat') && $request->filled('lng')
            && is_numeric($request->input('lat')) && is_numeric($request->input('lng'));
        $hasExplicitCity = $request->filled('city') && strcasecmp((string) $request->input('city'), 'All') !== 0;
        $hasExplicitArea = $request->filled('area') && strcasecmp((string) $request->input('area'), 'All') !== 0;

        if (!$hasExplicitCoords && !$hasExplicitCity && !$hasExplicitArea) {
            if (empty($stored['lat']) || empty($stored['lng'])) {
                $coords = GeolocationService::getAreaCoordinates((string) ($stored['area'] ?? $stored['city'] ?? 'Banani'))
                    ?? ['lat' => 23.7937, 'lng' => 90.4066];
                $stored['lat'] = $coords['lat'];
                $stored['lng'] = $coords['lng'];
            }
            return $stored;
        }

        $city = $hasExplicitCity ? trim((string) $request->input('city')) : ($stored['city'] ?? 'Dhaka');
        $area = $hasExplicitArea ? trim((string) $request->input('area')) : ($hasExplicitCity ? '' : ($stored['area'] ?? ''));

        if ($hasExplicitCoords) {
            $lat = (float) $request->input('lat');
            $lng = (float) $request->input('lng');
            if (!$hasExplicitCity) {
                $resolved = BangladeshLocationResolver::resolve($lat, $lng);
                $city = $resolved['city'] ?? $city;
                if (!$hasExplicitArea) {
                    $area = $resolved['area'] ?? '';
                }
            }
        } else {
            $lookup = $area !== '' ? $area : $city;
            $coords = GeolocationService::getAreaCoordinates($lookup)
                ?? GeolocationService::getAreaCoordinates($city)
                ?? ['lat' => 23.7937, 'lng' => 90.4066];
            $lat = (float) $coords['lat'];
            $lng = (float) $coords['lng'];
        }

        $address = collect([$area, $city])->filter()->unique()->implode(', ') ?: 'Dhaka';

        $updated = [
            'city' => $city,
            'area' => $area ?: $city,
            'address' => $address,
            'lat' => $lat,
            'lng' => $lng,
        ];

        $request->session()->put('customer_location', $updated);

        return $updated;
    }

    private function validateVendorListingPayload(
        Request $request,
        VendorProfile $vendorProfile,
        ?SparePartListing $existing = null
    ): array {
        $ownedShopIds = $vendorProfile->shops()->pluck('id')->all();

        $validated = $request->validate([
            'shop_id' => ['required', 'integer', function ($attr, $val, $fail) use ($ownedShopIds) {
                if (!in_array((int) $val, $ownedShopIds, true)) {
                    $fail('Selected shop branch does not belong to your vendor profile.');
                }
            }],
            'title' => 'required|string|max:180',
            'product_group_key' => 'nullable|string|max:160',
            'sku' => 'nullable|string|max:80',
            'part_category' => 'required|string|max:80',
            'brand' => 'required|string|max:80',
            'compatible_model' => 'nullable|string|max:140',
            'condition_label' => 'nullable|string|max:60',
            'base_price' => 'nullable|numeric|min:1|max:500000',
            'regular_price' => 'nullable|numeric|min:1|max:600000',
            'assembly_fee' => 'nullable|numeric|min:0|max:50000',
            'warranty_days' => 'nullable|integer|min:0|max:1095',
            'stock_quantity' => 'required|integer|min:0|max:100000',
            'handling_hours' => 'nullable|integer|min:1|max:72',
            'has_specs' => 'nullable|boolean',
            'spec_label' => 'nullable|string|max:60',
            'spec_names' => 'nullable|array',
            'spec_names.*' => 'nullable|string|max:80',
            'spec_prices' => 'nullable|array',
            'spec_prices.*' => 'nullable|numeric|min:1|max:500000',
            'spec_subtitles' => 'nullable|array',
            'spec_subtitles.*' => 'nullable|string|max:120',
            'spec_stocks' => 'nullable|array',
            'spec_stocks.*' => 'nullable|integer|min:0|max:100000',
            'spec_options' => 'nullable|array',
            'highlights_text' => 'nullable|string|max:2000',
            'description' => 'nullable|string|max:4000',
            'image_url' => 'nullable|string|max:500',
            'image_file' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:4096',
            'wholesale_enabled' => 'nullable|boolean',
            'wholesale_min_qty' => 'nullable|integer|min:2|max:10000',
            'wholesale_base_discount_pct' => 'nullable|numeric|min:0.5|max:75',
            'wholesale_step_qty' => 'nullable|integer|min:1|max:10000',
            'wholesale_step_discount_pct' => 'nullable|numeric|min:0|max:50',
            'wholesale_max_discount_pct' => 'nullable|numeric|min:1|max:80',
            'offer_enabled' => 'nullable|boolean',
            'offer_price' => 'nullable|numeric|min:1|max:500000',
            'offer_discount_pct' => 'nullable|numeric|min:0.5|max:90',
            'offer_starts_at' => 'nullable|date',
            'offer_ends_at' => 'nullable|date|after_or_equal:offer_starts_at',
        ]);

        $hasSpecs = $request->boolean('has_specs');
        $specOptions = [];

        if ($hasSpecs) {
            if (!empty($validated['spec_options']) && is_array($validated['spec_options'])) {
                foreach ($validated['spec_options'] as $opt) {
                    if (!is_array($opt)) {
                        continue;
                    }
                    $name = trim((string) ($opt['name'] ?? ''));
                    $price = round((float) ($opt['price'] ?? 0), 2);
                    if ($name === '' || $price <= 0) {
                        continue;
                    }
                    $specOptions[] = [
                        'code' => Str::slug((string) ($opt['code'] ?? $name)) ?: 'option-' . (count($specOptions) + 1),
                        'name' => $name,
                        'subtitle' => trim((string) ($opt['subtitle'] ?? '')),
                        'price' => $price,
                        'regular_price' => isset($opt['regular_price']) && (float) $opt['regular_price'] > $price
                            ? round((float) $opt['regular_price'], 2)
                            : round($price * 1.15, 2),
                        'stock' => isset($opt['stock']) ? max(0, (int) $opt['stock']) : (int) $validated['stock_quantity'],
                    ];
                }
            } elseif (!empty($validated['spec_names']) && is_array($validated['spec_names'])) {
                $pricesArr = $validated['spec_prices'] ?? [];
                $subsArr = $validated['spec_subtitles'] ?? [];
                $stocksArr = $validated['spec_stocks'] ?? [];

                foreach ($validated['spec_names'] as $idx => $rawName) {
                    $name = trim((string) $rawName);
                    $price = round((float) ($pricesArr[$idx] ?? 0), 2);
                    if ($name === '' || $price <= 0) {
                        continue;
                    }
                    $specOptions[] = [
                        'code' => Str::slug($name) ?: ('option-' . ($idx + 1)),
                        'name' => $name,
                        'subtitle' => trim((string) ($subsArr[$idx] ?? '')),
                        'price' => $price,
                        'regular_price' => round($price * 1.15, 2),
                        'stock' => isset($stocksArr[$idx]) && $stocksArr[$idx] !== null && $stocksArr[$idx] !== ''
                            ? max(0, (int) $stocksArr[$idx])
                            : (int) $validated['stock_quantity'],
                    ];
                }
            }

            if (empty($specOptions)) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'spec_options' => ['Please add at least one valid specification option with name and price, or uncheck "Requires Specification Options".'],
                ]);
            }

            usort($specOptions, fn ($a, $b) => $a['price'] <=> $b['price']);
            $basePrice = (float) min(array_column($specOptions, 'price'));
            $maxPrice = (float) max(array_column($specOptions, 'price'));
        } else {
            $basePrice = round((float) ($validated['base_price'] ?? 0), 2);
            if ($basePrice <= 0) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'base_price' => ['Please enter a valid item price in BDT.'],
                ]);
            }
            $maxPrice = null;
        }

        $regularPrice = !empty($validated['regular_price']) && (float) $validated['regular_price'] > $basePrice
            ? round((float) $validated['regular_price'], 2)
            : round($basePrice * 1.15, 2);

        $normalPrice = $basePrice;
        $normalMaxPrice = $maxPrice;

        // Promotional Offer System ("From ___ , To ___", where `To` can be empty for indefinite offer!)
        $offerEnabled = $request->boolean('offer_enabled');
        $offerPrice = null;
        $offerDiscountPct = null;
        $offerStartsAt = null;
        $offerEndsAt = null;

        if ($offerEnabled) {
            if (!$hasSpecs) {
                $rawOfferPrice = isset($validated['offer_price']) && $validated['offer_price'] !== ''
                    ? round((float) $validated['offer_price'], 2)
                    : 0.0;
                $rawOfferPct = isset($validated['offer_discount_pct']) && $validated['offer_discount_pct'] !== ''
                    ? round((float) $validated['offer_discount_pct'], 2)
                    : 0.0;

                if ($rawOfferPrice <= 0 && $rawOfferPct > 0) {
                    $rawOfferPrice = round(max(1.0, $normalPrice * (1 - ($rawOfferPct / 100))), 2);
                }

                if ($rawOfferPrice <= 0 || $rawOfferPrice >= $normalPrice) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'offer_price' => ['Offer price must be greater than 0 and strictly lower than the normal price (৳' . number_format($normalPrice, 0) . ').'],
                    ]);
                }

                $offerPrice = $rawOfferPrice;
                $offerDiscountPct = round((($normalPrice - $offerPrice) / $normalPrice) * 100, 2);
            } else {
                // Spec-based product offer: accepts either `offer_discount_pct` OR `offer_price` for lowest tier
                $rawOfferPct = isset($validated['offer_discount_pct']) && $validated['offer_discount_pct'] !== ''
                    ? round((float) $validated['offer_discount_pct'], 2)
                    : 0.0;
                $rawOfferPrice = isset($validated['offer_price']) && $validated['offer_price'] !== ''
                    ? round((float) $validated['offer_price'], 2)
                    : 0.0;

                if ($rawOfferPct <= 0 && $rawOfferPrice > 0 && $rawOfferPrice < $normalPrice) {
                    $rawOfferPct = round((($normalPrice - $rawOfferPrice) / $normalPrice) * 100, 2);
                }

                if ($rawOfferPct <= 0 || $rawOfferPct >= 95) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'offer_discount_pct' => ['For multi-spec items, enter an Offer Discount % (e.g. 10%) or an Offer Price lower than the starting spec price (৳' . number_format($normalPrice, 0) . ').'],
                    ]);
                }

                $offerDiscountPct = $rawOfferPct;
                $offerPrice = $rawOfferPrice > 0 && $rawOfferPrice < $normalPrice
                    ? $rawOfferPrice
                    : round(max(1.0, $normalPrice * (1 - ($offerDiscountPct / 100))), 2);

                foreach ($specOptions as &$sOpt) {
                    $sOpt['normal_price'] = $sOpt['price'];
                    $sOpt['offer_price'] = round(max(1.0, $sOpt['price'] * (1 - ($offerDiscountPct / 100))), 2);
                }
                unset($sOpt);
            }

            if (!empty($validated['offer_starts_at'])) {
                $offerStartsAt = \Illuminate\Support\Carbon::parse((string) $validated['offer_starts_at'])->startOfMinute();
            } else {
                $offerStartsAt = now();
            }

            // `offer_ends_at` ("To ___") is optional — leaving it blank keeps the offer active indefinitely!
            if (!empty($validated['offer_ends_at'])) {
                $rawEnd = trim((string) $validated['offer_ends_at']);
                $parsedEnd = \Illuminate\Support\Carbon::parse($rawEnd);
                // If only a date YYYY-MM-DD was provided without time, keep valid through end of that day
                if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawEnd)) {
                    $parsedEnd = $parsedEnd->endOfDay();
                }
                $offerEndsAt = $parsedEnd;
            }
        }

        $highlights = $existing?->highlights ?? [];
        if ($request->has('highlights_text')) {
            $lines = preg_split('/\r\n|\r|\n/', (string) $validated['highlights_text']);
            $highlights = array_values(array_filter(array_map(
                fn ($line) => trim(ltrim(trim((string) $line), "•-* \t")),
                $lines ?: []
            )));
        }
        if (empty($highlights)) {
            $highlights = [
                '100% bench-tested replacement spare part',
                'Covered by Meramot Escrow Inspection & Return Protection',
            ];
        }

        $imageUrl = $existing?->image_url;
        if ($request->hasFile('image_file')) {
            $file = $request->file('image_file');
            $uploadDir = public_path('images/spare-parts/uploads');
            if (!is_dir($uploadDir)) {
                @mkdir($uploadDir, 0775, true);
            }
            $ext = strtolower($file->getClientOriginalExtension() ?: 'webp');
            $filename = Str::slug($validated['title']) . '-' . time() . '-' . Str::lower(Str::random(4)) . '.' . $ext;
            $file->move($uploadDir, $filename);
            $imageUrl = '/images/spare-parts/uploads/' . $filename;
        } elseif (!empty($validated['image_url'])) {
            $imageUrl = trim((string) $validated['image_url']);
        } elseif (!$imageUrl) {
            $imageUrl = SparePartListing::resolveCategoryFallbackImage(
                (string) $validated['part_category'],
                (string) $validated['title'],
                (string) $validated['brand']
            );
        }

        $rawGroupKey = trim((string) ($validated['product_group_key'] ?? ''));
        if ($rawGroupKey === '') {
            $rawGroupKey = SparePartListing::canonicalizeGroupKey((string) $validated['title']);
        } else {
            $rawGroupKey = Str::slug($rawGroupKey);
        }

        $wholesaleEnabled = $request->boolean('wholesale_enabled');
        $wholesaleMinQty = max(2, (int) ($validated['wholesale_min_qty'] ?? 100));
        $wholesaleBasePct = round((float) ($validated['wholesale_base_discount_pct'] ?? 5.00), 2);
        $wholesaleStepQty = max(1, (int) ($validated['wholesale_step_qty'] ?? 100));
        $wholesaleStepPct = round((float) ($validated['wholesale_step_discount_pct'] ?? 2.00), 2);
        $wholesaleMaxPct = max($wholesaleBasePct, round((float) ($validated['wholesale_max_discount_pct'] ?? 25.00), 2));

        return [
            'shop_id' => (int) $validated['shop_id'],
            'product_group_key' => $rawGroupKey ?: Str::slug($validated['title']),
            'sku' => !empty($validated['sku'])
                ? trim((string) $validated['sku'])
                : ($existing?->sku ?: ('MRM-SP-' . strtoupper(Str::random(6)))),
            'title' => trim((string) $validated['title']),
            'part_category' => trim((string) $validated['part_category']),
            'brand' => trim((string) $validated['brand']),
            'compatible_model' => !empty($validated['compatible_model'])
                ? trim((string) $validated['compatible_model'])
                : trim((string) $validated['title']),
            'condition_label' => !empty($validated['condition_label'])
                ? trim((string) $validated['condition_label'])
                : 'Brand New',
            'image_url' => $imageUrl,
            'gallery_urls' => [$imageUrl],
            'highlights' => $highlights,
            'description' => !empty($validated['description'])
                ? trim((string) $validated['description'])
                : ("Replacement {$validated['part_category']} compatible with " . (($validated['compatible_model'] ?? null) ?: $validated['title']) . '.'),
            'has_specs' => $hasSpecs,
            'spec_label' => $hasSpecs ? (trim((string) ($validated['spec_label'] ?? '')) ?: 'Quality') : null,
            'spec_options' => $hasSpecs ? $specOptions : null,
            'base_price' => $offerEnabled && $offerPrice ? $offerPrice : $normalPrice,
            'normal_price' => $normalPrice,
            'max_price' => $maxPrice,
            'normal_max_price' => $normalMaxPrice,
            'regular_price' => $regularPrice,
            'offer_enabled' => $offerEnabled,
            'offer_price' => $offerPrice,
            'offer_discount_pct' => $offerDiscountPct,
            'offer_starts_at' => $offerStartsAt,
            'offer_ends_at' => $offerEndsAt,
            'assembly_fee' => isset($validated['assembly_fee']) && $validated['assembly_fee'] !== '' && $validated['assembly_fee'] !== null
                ? round((float) $validated['assembly_fee'], 2)
                : null,
            'warranty_days' => isset($validated['warranty_days']) && $validated['warranty_days'] !== '' && $validated['warranty_days'] !== null
                ? (int) $validated['warranty_days']
                : 30,
            'stock_quantity' => (int) $validated['stock_quantity'],
            'wholesale_enabled' => $wholesaleEnabled,
            'wholesale_min_qty' => $wholesaleMinQty,
            'wholesale_base_discount_pct' => $wholesaleBasePct,
            'wholesale_step_qty' => $wholesaleStepQty,
            'wholesale_step_discount_pct' => $wholesaleStepPct,
            'wholesale_max_discount_pct' => $wholesaleMaxPct,
            'handling_hours' => (int) ($validated['handling_hours'] ?? ($existing?->handling_hours ?? 1)),
        ];
    }

    private function resolveAuthorizedVendorProfile(Request $request)
    {
        $user = $request->user();

        if ($user) {
            if ($user->role === 'customer') {
                $ownedCustomerVendor = VendorProfile::where('user_id', $user->id)->first();
                if (!$ownedCustomerVendor) {
                    if ($request->expectsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Vendor partner account required.'], 403);
                    }
                    return redirect()->route('vendor.apply');
                }
                if ($ownedCustomerVendor->verification_status !== 'approved') {
                    if ($request->expectsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Vendor verification pending.'], 403);
                    }
                    return redirect()->route('vendor.onboarding');
                }
                return $ownedCustomerVendor;
            }

            if ($user->role === 'vendor') {
                $owned = VendorProfile::where('user_id', $user->id)->first();
                if (!$owned) {
                    if ($request->expectsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Submit vendor application first.'], 403);
                    }
                    return redirect()->route('vendor.apply');
                }
                if ($owned->verification_status !== 'approved') {
                    if ($request->expectsJson()) {
                        return response()->json(['status' => 'error', 'message' => 'Vendor approval required.'], 403);
                    }
                    return redirect()->route('vendor.onboarding');
                }
                return $owned;
            }

            if ($user->role !== 'admin') {
                if ($request->expectsJson()) {
                    return response()->json(['status' => 'error', 'message' => 'Unauthorized role.'], 403);
                }
                return redirect('/');
            }
        }

        if ($request->filled('vendor_profile_id')) {
            $explicit = VendorProfile::find($request->input('vendor_profile_id'));
            if ($explicit) {
                return $explicit;
            }
        }

        return VendorProfile::where('shop_name', 'ProTech Solutions')->first()
            ?? VendorProfile::orderBy('id')->firstOrFail();
    }

    private function ensureUniqueSlug(string $baseSlug): string
    {
        $slug = $baseSlug;
        $counter = 2;
        while (SparePartListing::where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . $counter;
            $counter++;
        }
        return $slug;
    }
}

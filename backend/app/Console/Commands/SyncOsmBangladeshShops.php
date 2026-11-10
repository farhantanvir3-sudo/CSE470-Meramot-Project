<?php

namespace App\Console\Commands;

use App\Services\BangladeshLocationResolver;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * ARTISAN COMMAND: Sync Nationwide Bangladesh OpenStreetMap Tech & Electronics Shops
 * ============================================================================
 * Author: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Fetches every `shop=electronics` store (the TV symbol on OpenStreetMap/Leaflet)
 * across the entire Bangladesh region (`ISO3166-1="BD"`), normalizes brands,
 * computes coherent Dual Addresses & Multi-Branch metadata, and saves the snapshot
 * to `database/seeders/data/bangladesh_osm_electronics_shops.json`.
 * ============================================================================
 */
class SyncOsmBangladeshShops extends Command
{
    protected $signature = 'osm:sync-shops
                            {--from-file= : Optional path to a raw Overpass JSON file instead of live HTTP query}
                            {--seed : Automatically run ShopSeeder after updating the snapshot}';

    protected $description = 'Fetch all shop=electronics stores across Bangladesh from OpenStreetMap Overpass API and build coherent seed data';

    /**
     * Generic placeholder names that should NOT be auto-grouped into multi-branch corporate chains.
     */
    private const GENERIC_PLACEHOLDER_NAMES = [
        'electronics shop',
        'electronic shop',
        'electronics',
        'electronic',
        'electronics store',
        'electric shop',
        'electrical shop',
        'mobile shop',
        'mobile store',
        'mobile servicing',
        'computer shop',
        'telecom',
        'tv repair',
        'repair shop',
    ];

    /**
     * Canonical brand name consolidations for major Bangladesh electronics chains.
     */
    private const BRAND_CONSOLIDATIONS = [
        'walton plaza' => 'Walton Plaza',
        'walton showroom' => 'Walton Showroom',
        'walton exclusive showroom' => 'Walton Exclusive Showroom',
        'walton' => 'Walton',
        'singer plus' => 'Singer Plus',
        'singer pro' => 'Singer Pro',
        'singer mega' => 'Singer Mega',
        'singer showroom' => 'Singer Showroom',
        'singer' => 'Singer',
        'vision emporium' => 'Vision Emporium',
        'transcom digital' => 'Transcom Digital',
        'best electronics' => 'Best Electronics',
        'samsung' => 'Samsung',
        'samsung smartplaza' => 'Samsung SmartPlaza',
        'sony showroom' => 'Sony Showroom',
        'sony' => 'Sony',
        'rangs emart' => 'Rangs eMart',
        'rangs' => 'Rangs',
        'lg' => 'LG',
        'lg butterfly' => 'Butterfly',
        'butterfly' => 'Butterfly',
        'minister' => 'Minister Hi-Tech Park',
        'jamuna electronics' => 'Jamuna Electronics',
        'esquire electronics' => 'Esquire Electronics',
        'star tech' => 'Star Tech',
        'ryans computers' => 'Ryans Computers',
        'miraz telecom' => 'Miraz Telecom',
    ];

    public function handle(): int
    {
        $fromFile = $this->option('from-file');

        if ($fromFile) {
            if (!is_file($fromFile)) {
                $this->error("Raw Overpass JSON file not found: {$fromFile}");
                return self::FAILURE;
            }
            $this->info("Reading raw Bangladesh OpenStreetMap dataset from {$fromFile}...");
            $payload = json_decode((string) file_get_contents($fromFile), true);
        } else {
            $this->info('Querying OpenStreetMap Overpass API for all shop=electronics across Bangladesh (ISO3166-1=BD)...');
            $overpassQuery = <<<'QL'
[out:json][timeout:90];
area["ISO3166-1"="BD"][admin_level=2]->.bd;
(
  node["shop"="electronics"](area.bd);
  way["shop"="electronics"](area.bd);
  relation["shop"="electronics"](area.bd);
);
out center tags;
QL;
            $response = Http::timeout(95)
                ->withHeaders([
                    'User-Agent' => 'MeramotBangladeshSeeder/1.0 (https://github.com/farhantanvir3-sudo/CSE470-Meramot-Project)',
                    'Accept' => 'application/json',
                ])
                ->asForm()
                ->post('https://overpass-api.de/api/interpreter', [
                    'data' => $overpassQuery,
                ]);

            if (!$response->successful()) {
                $this->error('Overpass API request failed with HTTP ' . $response->status());
                return self::FAILURE;
            }

            $payload = $response->json();
        }

        $elements = $payload['elements'] ?? [];
        if (empty($elements)) {
            $this->error('No OSM elements found in payload.');
            return self::FAILURE;
        }

        $parsedRecords = [];
        $usedSlugs = [];

        foreach ($elements as $el) {
            $tags = $el['tags'] ?? [];
            $rawName = trim((string) ($tags['name:en'] ?? $tags['name'] ?? ''));
            if ($rawName === '') {
                continue; // Skip unnamed raw pins for marketplace quality
            }

            $lat = isset($el['lat']) ? (float) $el['lat'] : (isset($el['center']['lat']) ? (float) $el['center']['lat'] : null);
            $lng = isset($el['lon']) ? (float) $el['lon'] : (isset($el['center']['lon']) ? (float) $el['center']['lon'] : null);
            if ($lat === null || $lng === null) {
                continue;
            }

            $osmId = ($el['type'] ?? 'node') . '/' . ($el['id'] ?? '');
            [$canonicalName, $secondaryUnit] = $this->extractCanonicalNameAndUnit($rawName, $tags);
            $brandKey = $this->normalizeBrandKey($canonicalName);

            $resolvedGeo = BangladeshLocationResolver::resolve($lat, $lng, $tags);

            // Deterministic realistic shop metrics seeded from OSM element ID so re-runs are 100% stable
            $hashSeed = crc32($osmId);
            $rating = round(4.2 + (($hashSeed % 8) * 0.1), 1); // 4.2 to 4.9
            $reviewCount = 12 + ($hashSeed % 185);
            $priceLevel = 1 + ($hashSeed % 3);
            $inspectionFee = [150, 200, 250, 300][$hashSeed % 4];
            $baseLaborFee = [350, 400, 500, 600][$hashSeed % 4];
            $etaMinutes = [45, 60, 75, 90, 120][$hashSeed % 5];

            $phone = $this->normalizeBangladeshPhone($tags['phone'] ?? $tags['contact:phone'] ?? null, $hashSeed);
            $openingHours = trim((string) ($tags['opening_hours'] ?? '')) ?: 'Sat – Thu: 10:00 AM – 8:30 PM';

            $categories = ['IN_SHOP_REPAIR', 'SPARE_PARTS'];
            if (($hashSeed % 2) === 0) {
                array_unshift($categories, 'COURIER_PICKUP');
            }

            $specialties = $this->inferSpecialties($canonicalName, $secondaryUnit, $tags);

            $descriptionParts = [];
            if ($secondaryUnit) {
                $descriptionParts[] = "{$canonicalName} ({$secondaryUnit})";
            } else {
                $descriptionParts[] = "Verified electronics & hardware service hub in {$resolvedGeo['area']}, {$resolvedGeo['city']}";
            }
            if ($rawName !== $canonicalName && !$secondaryUnit) {
                $descriptionParts[] = "Listed as {$rawName}";
            }
            $descriptionParts[] = 'Diagnostics, genuine spare parts, and bench repair support.';
            $description = implode(' — ', $descriptionParts);

            $baseSlug = Str::slug($canonicalName . '-' . $resolvedGeo['area'] . '-' . $resolvedGeo['city']);
            if ($baseSlug === '') {
                $baseSlug = 'bd-tech-shop-' . ($el['id'] ?? abs($hashSeed));
            }
            $slug = $baseSlug;
            if (isset($usedSlugs[$slug])) {
                $slug = $baseSlug . '-' . ($el['id'] ?? ($usedSlugs[$baseSlug] + 1));
            }
            $usedSlugs[$baseSlug] = ($usedSlugs[$baseSlug] ?? 0) + 1;
            $usedSlugs[$slug] = 1;

            $parsedRecords[] = [
                'osm_id' => $osmId,
                'osm_raw_name' => $rawName,
                'name' => $canonicalName,
                'brand_key' => $brandKey,
                'secondary_unit' => $secondaryUnit,
                'slug' => $slug,
                'description' => $description,
                'address' => $resolvedGeo['address'],
                'gps_address' => $resolvedGeo['gps_address'],
                'same_as_gps_location' => $resolvedGeo['same_as_gps_location'],
                'city' => $resolvedGeo['city'],
                'area' => $resolvedGeo['area'],
                'lat' => round($lat, 7),
                'lng' => round($lng, 7),
                'street_hint' => trim((string) ($tags['addr:housenumber'] ?? $tags['addr:street'] ?? '')),
                'rating' => $rating,
                'review_count' => $reviewCount,
                'price_level' => $priceLevel,
                'inspection_fee' => $inspectionFee,
                'base_labor_fee' => $baseLaborFee,
                'eta_minutes' => $etaMinutes,
                'has_voucher' => ($hashSeed % 5) === 0,
                'free_delivery' => ($hashSeed % 4) === 0,
                'has_deals' => ($hashSeed % 6) === 0,
                'phone' => $phone,
                'opening_hours_text' => $openingHours,
                'categories' => $categories,
                'specialties' => $specialties,
            ];
        }

        // Group by brand_key to identify Multi-Branch Vendors coherently
        $brandGroups = [];
        foreach ($parsedRecords as $idx => $rec) {
            $brandGroups[$rec['brand_key']][] = $idx;
        }

        $multiBranchBrands = 0;
        $multiBranchShops = 0;

        foreach ($brandGroups as $brandKey => $indices) {
            $isGeneric = in_array($brandKey, self::GENERIC_PLACEHOLDER_NAMES, true);
            $isMultiBranch = !$isGeneric && count($indices) >= 2;

            if (!$isMultiBranch) {
                foreach ($indices as $idx) {
                    $parsedRecords[$idx]['is_branch'] = false;
                    $parsedRecords[$idx]['branch_name'] = null;
                    $parsedRecords[$idx]['vendor_group_key'] = 'standalone:' . $parsedRecords[$idx]['osm_id'];
                }
                continue;
            }

            $multiBranchBrands++;
            $multiBranchShops += count($indices);

            // Unify exact brand display name across all branches of this vendor
            // so every branch strictly shares VendorProfile::canonical_brand_name
            $unifiedBrandName = $this->selectPreferredBrandCasing(
                array_map(fn (int $i) => $parsedRecords[$i]['name'], $indices)
            );

            // Check if this brand spans multiple cities
            $distinctCities = [];
            foreach ($indices as $idx) {
                $distinctCities[$parsedRecords[$idx]['city']] = true;
            }
            $spansMultipleCities = count($distinctCities) > 1;

            // Build unique branch_name for every shop under this brand
            $usedBranchNames = [];
            foreach ($indices as $idx) {
                $parsedRecords[$idx]['name'] = $unifiedBrandName;
                $rec = $parsedRecords[$idx];
                $area = $rec['area'];
                $city = $rec['city'];

                if ($spansMultipleCities && !str_contains(mb_strtolower($area), mb_strtolower($city))) {
                    $candidate = "{$area}, {$city}";
                } else {
                    $candidate = $area;
                }

                if (!empty($rec['secondary_unit'])) {
                    $candidate .= ' (' . Str::limit($rec['secondary_unit'], 22, '') . ')';
                } elseif (isset($usedBranchNames[mb_strtolower($candidate)]) && !empty($rec['street_hint'])) {
                    $shortStreet = trim(explode(',', $rec['street_hint'])[0]);
                    if ($shortStreet !== '' && mb_strtolower($shortStreet) !== mb_strtolower($area)) {
                        $candidate .= " ({$shortStreet})";
                    }
                }

                $baseCandidate = $candidate;
                $suffix = 2;
                while (isset($usedBranchNames[mb_strtolower($candidate)])) {
                    $candidate = "{$baseCandidate} #{$suffix}";
                    $suffix++;
                }
                $usedBranchNames[mb_strtolower($candidate)] = true;

                $parsedRecords[$idx]['is_branch'] = true;
                $parsedRecords[$idx]['branch_name'] = $candidate;
                $parsedRecords[$idx]['vendor_group_key'] = 'brand:' . $brandKey;
            }
        }

        // Remove temporary helper keys before writing clean snapshot JSON
        $cleanSnapshot = array_map(function (array $rec) {
            unset($rec['brand_key'], $rec['secondary_unit'], $rec['street_hint']);
            return $rec;
        }, $parsedRecords);

        $outputDir = database_path('seeders/data');
        if (!is_dir($outputDir)) {
            mkdir($outputDir, 0775, true);
        }

        $outputPath = $outputDir . '/bangladesh_osm_electronics_shops.json';
        file_put_contents(
            $outputPath,
            json_encode([
                'generated_at' => now()->toIso8601String(),
                'source' => 'OpenStreetMap Overpass API (ISO3166-1=BD, shop=electronics)',
                'total_shops' => count($cleanSnapshot),
                'multi_branch_brands' => $multiBranchBrands,
                'multi_branch_shops' => $multiBranchShops,
                'shops' => $cleanSnapshot,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $this->info(sprintf(
            'Saved %d Bangladesh OSM electronics shops (%d multi-branch brands covering %d branches) to %s',
            count($cleanSnapshot),
            $multiBranchBrands,
            $multiBranchShops,
            $outputPath
        ));

        if ($this->option('seed')) {
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\ShopSeeder', '--force' => true]);
        }

        return self::SUCCESS;
    }

    /**
     * Clean semicolon compound names (e.g. "Miraz Telecom;Mobile Net") and bilingual suffixes
     * (e.g. "Walton Plaza ওয়ালটন প্লাজা" -> "Walton Plaza").
     */
    private function extractCanonicalNameAndUnit(string $rawName, array $tags): array
    {
        $secondaryUnit = null;
        $working = trim($rawName);

        if (str_contains($working, ';')) {
            $parts = array_values(array_filter(array_map('trim', explode(';', $working))));
            $working = $parts[0] ?? $working;
            if (count($parts) > 1) {
                $secondaryUnit = implode(' / ', array_slice($parts, 1));
            }
        }

        // If the string has both ASCII Latin letters and Bengali script, extract the clean English name
        if (preg_match('/[A-Za-z]{2,}/', $working) && preg_match('/[\x{0980}-\x{09FF}]/u', $working)) {
            $asciiOnly = trim((string) preg_replace('/[\x{0980}-\x{09FF}\(\)\[\]\|\/\-]+/u', ' ', $working));
            $asciiOnly = trim((string) preg_replace('/\s+/', ' ', $asciiOnly));
            if (mb_strlen($asciiOnly) >= 3) {
                $working = $asciiOnly;
            }
        }

        // Check brand tag fallback or consolidation map
        $lower = mb_strtolower($working);
        if (isset(self::BRAND_CONSOLIDATIONS[$lower])) {
            $working = self::BRAND_CONSOLIDATIONS[$lower];
        } elseif (preg_match('/^[a-z0-9\s&\.\-\']+$/i', $working) && $working === strtolower($working)) {
            $working = ucwords($working);
        }

        return [$working, $secondaryUnit];
    }

    private function normalizeBrandKey(string $canonicalName): string
    {
        $lower = mb_strtolower(trim($canonicalName));
        $lower = trim((string) preg_replace('/\s+/', ' ', $lower));
        return $lower;
    }

    /**
     * Pick the cleanest Title-Case / mixed-case brand string among branches
     * so all branches under one VendorProfile share an identical brand name.
     */
    private function selectPreferredBrandCasing(array $names): string
    {
        $best = $names[0] ?? '';
        $bestScore = -999;

        foreach ($names as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate === '') {
                continue;
            }

            $score = 0;
            $isAllUpper = ($candidate === mb_strtoupper($candidate) && mb_strlen($candidate) > 4);
            $isAllLower = ($candidate === mb_strtolower($candidate));

            if (!$isAllUpper) {
                $score += 10;
            }
            if (!$isAllLower) {
                $score += 10;
            }

            // Reward capitalized word starts
            if (preg_match_all('/\b\p{Lu}/u', $candidate, $m)) {
                $score += count($m[0]) * 2;
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = $candidate;
            }
        }

        return $best;
    }

    private function normalizeBangladeshPhone(?string $rawPhone, int $seed): string
    {
        if ($rawPhone) {
            $first = trim(explode(';', $rawPhone)[0]);
            if ($first !== '' && mb_strlen($first) <= 24) {
                return $first;
            }
        }

        $prefixes = ['017', '018', '019', '016', '013'];
        $prefix = $prefixes[$seed % count($prefixes)];
        $digits = str_pad((string) (($seed % 89999999) + 10000000), 8, '0', STR_PAD_LEFT);
        return '+88' . $prefix . substr($digits, 0, 8);
    }

    private function inferSpecialties(string $name, ?string $secondaryUnit, array $tags): array
    {
        $haystack = mb_strtolower($name . ' ' . ($secondaryUnit ?? '') . ' ' . ($tags['brand'] ?? ''));
        $specs = [];

        if (str_contains($haystack, 'telecom') || str_contains($haystack, 'mobile') || str_contains($haystack, 'phone') || str_contains($haystack, 'samsung')) {
            $specs = ['Smartphone Hardware', 'Display Replacement', 'Charging Port & Battery'];
        } elseif (str_contains($haystack, 'computer') || str_contains($haystack, 'laptop') || str_contains($haystack, 'tech') || str_contains($haystack, 'ryans')) {
            $specs = ['Laptop Diagnostics', 'Motherboard Repair', 'SSD & RAM Upgrade'];
        } elseif (str_contains($haystack, 'walton') || str_contains($haystack, 'singer') || str_contains($haystack, 'vision') || str_contains($haystack, 'transcom') || str_contains($haystack, 'sony') || str_contains($haystack, 'rangs')) {
            $specs = ['Authorized Electronics Service', 'TV & Display Panel', 'Appliance & Board Repair'];
        } else {
            $specs = ['Electronics Diagnostics', 'Circuit & Component Repair', 'Genuine Spare Parts'];
        }

        if ($secondaryUnit && !in_array($secondaryUnit, $specs, true)) {
            $specs[] = $secondaryUnit;
        }

        return $specs;
    }
}

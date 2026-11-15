<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tokenized, synonym-aware & typo-tolerant marketplace search engine.
 *
 * Ensures customers and vendors find relevant listings, workshops, service
 * packages, and repair requests even when their query contains typos,
 * partial phrases, reordered words, missing spaces around model numbers,
 * or non-exact terminology (e.g. "screen" vs "OLED display", "iphne 13 battry").
 */
class MarketplaceSearchEngine
{
    private const FILLER_WORDS = [
        'for', 'my', 'the', 'with', 'and', 'in', 'on', 'at', 'of', 'to', 'a', 'an',
        'need', 'want', 'buy', 'get', 'find', 'looking', 'replace', 'replacement',
        'original', 'genuine', 'oem', 'new', 'fix', 'repair', 'repairs', 'service',
        'services', 'part', 'parts', 'shop', 'shops', 'store', 'near', 'me', 'please',
    ];

    /**
     * Canonical synonym groups keyed cleanly so any alias or frequent misspelling
     * expands to every equivalent catalog keyword without drifting off-topic.
     */
    private const SYNONYM_GROUPS = [
        ['iphone', 'iphne', 'ipone', 'ifone', 'iphon', 'ipohne', 'apple'],
        ['macbook', 'macbok', 'mcbook', 'makbook', 'mac', 'apple'],
        ['imac', 'ipad', 'apple'],
        ['samsung', 'samsng', 'smasung', 'samung', 'sumsung', 'samsug', 'galaxy', 'galxy', 'galaxi'],
        ['xiaomi', 'xaomi', 'xiomi', 'xioami', 'xiami', 'redmi', 'redmy', 'poco'],
        ['oneplus', '1plus', 'onepls', 'nord'],
        ['huawei', 'huwei', 'hawaei', 'huawey'],
        ['motorola', 'motrola', 'moto'],
        ['lenovo', 'lenvo', 'lonovo', 'thinkpad', 'thinkpd', 'ideapad', 'thinkbook'],
        ['asus', 'assus', 'azus', 'zenbook', 'vivobook', 'rog', 'tuf'],
        ['dell', 'del', 'inspiron', 'latitude', 'xps', 'vostro'],
        ['hp', 'hewlett', 'pavilion', 'spectre', 'envy', 'probook', 'elitebook', 'victus'],
        ['acer', 'acr', 'aspire', 'nitro', 'swift', 'predator'],
        ['realme', 'relme', 'realmy'],
        ['oppo', 'opo', 'reno', 'find'],
        ['vivo', 'vvo', 'iqoo'],
        ['infinix', 'infnx'],
        ['tecno', 'tekno', 'camon', 'spark'],
        ['google', 'pixel', 'pixl'],
        ['sony', 'playstation', 'ps4', 'ps5', 'dualsense'],
        ['canon', 'pixma', 'printer', 'prntr'],
        ['screen', 'scren', 'scrn', 'display', 'disply', 'dispaly', 'lcd', 'oled', 'amoled', 'panel', 'digitizer', 'touch', 'glass'],
        ['battery', 'battry', 'batery', 'battrey', 'bettery', 'btry', 'cell', 'power'],
        ['keyboard', 'keybord', 'kyboard', 'keybaord', 'keypad', 'keycap', 'backlit'],
        ['charger', 'chrger', 'chargr', 'adapter', 'adaptor', 'psu', 'magsafe'],
        ['charging', 'charge', 'chrgng', 'port', 'connector', 'flex', 'usbc', 'usb-c', 'typec', 'type-c', 'lightning', 'jack'],
        ['ssd', 'nvme', 'm2nvme', 'm.2', 'storage', 'hdd', 'pcie', 'sata'],
        ['ram', 'memory', 'ddr4', 'ddr5', 'sodimm', 'so-dimm'],
        ['motherboard', 'mtherboard', 'mobo', 'logicboard', 'mainboard', 'board', 'pcb', 'ic', 'pmic', 'rework'],
        ['fan', 'cooler', 'cooling', 'heatsink', 'thermal', 'overheat', 'overheating', 'paste', 'repaste'],
        ['solder', 'soldering', 'solderng', 'flux', 'iron', 'tools', 'tool', 'workstation', 'microscope', 'screwdriver'],
        ['speaker', 'earpiece', 'mic', 'microphone', 'audio', 'buzzer', 'sound'],
        ['camera', 'camra', 'webcam', 'lens', 'sensor'],
        ['hinge', 'bezel', 'casing', 'housing', 'backshell', 'cover', 'frame', 'trackpad', 'touchpad'],
        ['water', 'liquid', 'spill', 'ultrasonic', 'corrosion'],
        ['protech', 'protek', 'protch'],
        ['dhaka', 'banani', 'dhanmondi', 'gulshan', 'uttara', 'mirpur', 'mohakhali', 'bashundhara', 'farmgate', 'hatirpool'],
        ['chattogram', 'chittagong', 'ctg', 'agrabad', 'nasirabad', 'khulshi'],
    ];

    /**
     * High-signal electronics marketplace vocabulary for Levenshtein typo correction (edit distance <= 2).
     */
    private const CATALOG_VOCABULARY = [
        'apple', 'samsung', 'xiaomi', 'oppo', 'vivo', 'realme', 'oneplus', 'asus', 'dell', 'hp',
        'lenovo', 'acer', 'msi', 'microsoft', 'google', 'infinix', 'tecno', 'motorola', 'huawei',
        'nokia', 'sony', 'canon', 'universal',
        'iphone', 'macbook', 'imac', 'ipad', 'galaxy', 'thinkpad', 'ideapad', 'thinkbook',
        'pavilion', 'spectre', 'envy', 'probook', 'elitebook', 'zenbook', 'vivobook', 'inspiron',
        'latitude', 'pixel', 'redmi', 'playstation', 'pixma',
        'display', 'screen', 'amoled', 'digitizer', 'battery', 'keyboard', 'backlit', 'motherboard',
        'charging', 'charger', 'adapter', 'connector', 'thermal', 'cooling', 'heatsink', 'overheating',
        'soldering', 'workstation', 'speaker', 'earpiece', 'microphone', 'camera', 'hinge', 'trackpad',
        'touchpad', 'backlight', 'ultrasonic', 'assembly', 'original', 'genuine', 'controller', 'housing',
        'backshell', 'storage', 'memory', 'ribbon', 'calibration', 'diagnostic', 'replacement',
        'protech', 'doctors', 'gadget', 'budget', 'macmaster', 'garage', 'bytesize', 'gulshan',
        'applecore', 'precision', 'mirpur', 'uttara', 'bashundhara', 'farmgate', 'miraz', 'telecom',
        'dhaka', 'chattogram', 'chittagong', 'banani', 'dhanmondi', 'mohakhali', 'agrabad', 'hatirpool',
    ];

    /**
     * Normalize & split raw search input into clean semantic tokens.
     *
     * @return array<int, string>
     */
    public static function tokenize(string $rawQuery): array
    {
        $normalized = mb_strtolower(trim($rawQuery), 'UTF-8');
        if ($normalized === '') {
            return [];
        }

        // Preserve compound connectivity specs before stripping punctuation
        $normalized = str_replace(
            ['usb-c', 'usb type-c', 'type-c', 'so-dimm', 'm.2'],
            ['usbc', 'typec', 'typec', 'sodimm', 'm2nvme'],
            $normalized
        );

        // Insert boundary spaces for concatenated model strings (e.g. "iphone13", "14pro", "s23ultra")
        $normalized = preg_replace('/([a-z]{2,})(\d+)/i', '$1 $2', $normalized) ?? $normalized;
        $normalized = preg_replace('/([a-z]\d+)([a-z]{2,})/i', '$1 $2', $normalized) ?? $normalized;
        $normalized = preg_replace('/(\d+)([a-z]{2,})/i', '$1 $2', $normalized) ?? $normalized;

        $parts = preg_split('/[^a-z0-9]+/i', $normalized, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $parts = array_values(array_unique(array_filter($parts, function ($tok) {
            if (is_numeric($tok)) {
                return strlen($tok) >= 1;
            }
            return strlen($tok) >= 2;
        })));

        if (count($parts) > 1) {
            $meaningful = array_values(array_filter(
                $parts,
                fn (string $tok) => !in_array($tok, self::FILLER_WORDS, true)
            ));
            if (!empty($meaningful)) {
                $parts = $meaningful;
            }
        }

        return array_slice($parts, 0, 7);
    }

    /**
     * Expand a single normalized token into exact aliases, stems, vocabulary typos, and tight `_` edits.
     *
     * @return array{terms: array<int, string>, wildcards: array<int, string>}
     */
    public static function expandTokenGroup(string $token): array
    {
        $token = strtolower(trim($token));
        $terms = [$token];

        // Basic plural/verb stemming for alphabetic words >= 4 chars
        if (ctype_alpha($token) && strlen($token) >= 4) {
            $stem = null;
            if (str_ends_with($token, 'ies') && strlen($token) >= 5) {
                $stem = substr($token, 0, -3) . 'y';
            } elseif (str_ends_with($token, 'es') && strlen($token) >= 5) {
                $stem = substr($token, 0, -2);
            } elseif (str_ends_with($token, 'ing') && strlen($token) >= 6) {
                $stem = substr($token, 0, -3);
            } elseif (str_ends_with($token, 's') && !str_ends_with($token, 'ss') && strlen($token) >= 4) {
                $stem = substr($token, 0, -1);
            }
            if ($stem !== null && strlen($stem) >= 3) {
                $terms[] = $stem;
            }
        }

        // Synonym & known common typo group lookup
        foreach (self::SYNONYM_GROUPS as $group) {
            foreach ($terms as $candidate) {
                if (in_array($candidate, $group, true)) {
                    foreach ($group as $syn) {
                        $terms[] = $syn;
                    }
                    break;
                }
            }
        }

        // Vocabulary Levenshtein typo correction (only for alphabetic words >= 4 chars; never blur numeric models)
        if (ctype_alpha($token) && strlen($token) >= 4 && strlen($token) <= 16) {
            $maxDist = strlen($token) >= 6 ? 2 : 1;
            foreach (self::CATALOG_VOCABULARY as $vocab) {
                if (abs(strlen($vocab) - strlen($token)) > $maxDist) {
                    continue;
                }
                if (levenshtein($token, $vocab) <= $maxDist) {
                    $terms[] = $vocab;
                    // Also include synonyms of the matched vocabulary word
                    foreach (self::SYNONYM_GROUPS as $group) {
                        if (in_array($vocab, $group, true)) {
                            foreach ($group as $syn) {
                                $terms[] = $syn;
                            }
                        }
                    }
                }
            }
        }

        // Tighten deduplication on literal terms
        $terms = array_values(array_unique(array_filter(
            $terms,
            fn ($t) => is_string($t) && $t !== ''
        )));

        // Compact 1-edit generators for arbitrary un-listed store/part names (Norvig style without multi-word % spans)
        $wildcards = [];
        if (ctype_alpha($token) && strlen($token) >= 4 && strlen($token) <= 14) {
            $len = strlen($token);

            // Deduplicate double typed characters (e.g. "orbitt" -> "orbit", "samsuung" -> "samsun")
            $deduped = preg_replace('/(.)\1+/', '$1', $token);
            if (is_string($deduped) && strlen($deduped) >= 4 && $deduped !== $token) {
                $terms[] = $deduped;
            }

            // Omit 1 extra typed character when token length >= 5
            if ($len >= 5) {
                for ($i = 0; $i < $len; $i++) {
                    $terms[] = substr($token, 0, $i) . substr($token, $i + 1);
                }
            }

            // Adjacent character transposition (e.g. "dispaly" -> "display", "ipohne" -> "iphone")
            for ($i = 0; $i < $len - 1; $i++) {
                if ($token[$i] !== $token[$i + 1]) {
                    $terms[] = substr($token, 0, $i) . $token[$i + 1] . $token[$i] . substr($token, $i + 2);
                }
            }

            // Single-character SQL `_` insertion / substitution (strictly 1 char, never spans across words)
            for ($i = 1; $i < $len; $i++) {
                $wildcards[] = substr($token, 0, $i) . '_' . substr($token, $i);
            }
            if ($len >= 5) {
                for ($i = 1; $i < $len; $i++) {
                    $wildcards[] = substr_replace($token, '_', $i, 1);
                }
            }
        }

        $terms = array_values(array_unique($terms));
        $wildcards = array_values(array_unique($wildcards));

        return [
            'terms' => array_slice($terms, 0, 22),
            'wildcards' => array_slice($wildcards, 0, 14),
        ];
    }

    /**
     * Build a DB-driver-aware LIKE SQL snippet and apply all group patterns onto a Builder.
     */
    private static function applyGroupToQuery(
        Builder $q,
        array $group,
        array $columns,
        bool $isPg,
        ?callable $extraGroupMatcher = null
    ): void {
        $patterns = [];
        foreach (array_merge($group['terms'], $group['wildcards']) as $pat) {
            $patterns[] = '%' . $pat . '%';
        }
        $patterns = array_values(array_unique($patterns));

        if (empty($patterns)) {
            return;
        }

        foreach ($columns as $col) {
            $sql = self::columnLikeSql($col, $isPg);
            foreach ($patterns as $pattern) {
                $q->orWhereRaw($sql, [$pattern]);
            }
        }

        if ($extraGroupMatcher !== null) {
            $extraGroupMatcher($q, $patterns, $isPg);
        }
    }

    /**
     * Apply two-stage adaptive token search + compute a parameterized SQL relevance ORDER BY expression.
     *
     * Stage 1 matches ALL token groups (`AND` across groups — high precision).
     * Stage 2 automatically activates if Stage 1 returns 0 rows (`OR` across token groups — partial match tolerance).
     *
     * @return array{sql: string, bindings: array<int, mixed>}|null
     */
    public static function applySearch(
        Builder $query,
        ?string $rawSearch,
        array $primaryColumns,
        array $secondaryColumns = [],
        ?callable $extraGroupMatcher = null
    ): ?array {
        $rawTrimmed = trim((string) $rawSearch);
        if ($rawTrimmed === '') {
            return null;
        }

        $tokens = self::tokenize($rawTrimmed);
        if (empty($tokens)) {
            return null;
        }

        $isPg = DB::connection()->getDriverName() === 'pgsql';
        $allColumns = array_values(array_unique(array_merge($primaryColumns, $secondaryColumns)));
        $groups = array_map(fn (string $t) => self::expandTokenGroup($t), $tokens);

        // Test Stage 1 (all token groups must match) on a clean clone before mutating $query
        $stage1Probe = clone $query;
        $stage1Probe->where(function (Builder $outer) use ($groups, $allColumns, $isPg, $extraGroupMatcher) {
            foreach ($groups as $group) {
                $outer->where(function (Builder $inner) use ($group, $allColumns, $isPg, $extraGroupMatcher) {
                    self::applyGroupToQuery($inner, $group, $allColumns, $isPg, $extraGroupMatcher);
                });
            }
        });

        $hasAllTokenMatches = count($groups) <= 1 || $stage1Probe->limit(1)->exists();

        if ($hasAllTokenMatches) {
            // Apply strict Stage 1 filter (each group requires at least one expanded/typo alias hit)
            $query->where(function (Builder $outer) use ($groups, $allColumns, $isPg, $extraGroupMatcher) {
                foreach ($groups as $group) {
                    $outer->where(function (Builder $inner) use ($group, $allColumns, $isPg, $extraGroupMatcher) {
                        self::applyGroupToQuery($inner, $group, $allColumns, $isPg, $extraGroupMatcher);
                    });
                }
            });
        } else {
            // Stage 2 Fallback: at least one token group matches when combined phrase yields 0 results
            $query->where(function (Builder $outer) use ($groups, $allColumns, $isPg, $extraGroupMatcher) {
                foreach ($groups as $group) {
                    $outer->orWhere(function (Builder $inner) use ($group, $allColumns, $isPg, $extraGroupMatcher) {
                        self::applyGroupToQuery($inner, $group, $allColumns, $isPg, $extraGroupMatcher);
                    });
                }
            });
        }

        // Build relevance scoring SQL (prioritizes exact phrase, more matched token groups, and primary column hits)
        $scoreTerms = [];
        $scoreBindings = [];

        $phraseClean = preg_replace('/[^a-z0-9 ]+/i', ' ', strtolower($rawTrimmed));
        $phraseClean = trim((string) preg_replace('/\s+/', ' ', (string) $phraseClean));
        if ($phraseClean !== '' && isset($primaryColumns[0])) {
            $scoreTerms[] = '(CASE WHEN ' . self::columnLikeSql($primaryColumns[0], $isPg) . ' THEN 6 ELSE 0 END)';
            $scoreBindings[] = '%' . $phraseClean . '%';
        }

        foreach ($groups as $group) {
            // Score primary column hits (+3) vs secondary column hits (+1) for each token group
            $primaryCheckParts = [];
            $secondaryCheckParts = [];
            $groupPats = array_values(array_unique(array_map(
                fn (string $t) => '%' . $t . '%',
                array_slice($group['terms'], 0, 8)
            )));

            foreach ($primaryColumns as $pCol) {
                $colSql = self::columnLikeSql($pCol, $isPg);
                foreach ($groupPats as $pat) {
                    $primaryCheckParts[] = $colSql;
                    $scoreBindings[] = $pat;
                }
            }
            if (!empty($primaryCheckParts)) {
                $scoreTerms[] = '(CASE WHEN (' . implode(' OR ', $primaryCheckParts) . ') THEN 3 ELSE 0 END)';
            }

            if (!empty($secondaryColumns)) {
                foreach ($secondaryColumns as $sCol) {
                    $colSql = self::columnLikeSql($sCol, $isPg);
                    foreach ($groupPats as $pat) {
                        $secondaryCheckParts[] = $colSql;
                        $scoreBindings[] = $pat;
                    }
                }
                if (!empty($secondaryCheckParts)) {
                    $scoreTerms[] = '(CASE WHEN (' . implode(' OR ', $secondaryCheckParts) . ') THEN 1 ELSE 0 END)';
                }
            }
        }

        if (empty($scoreTerms)) {
            return null;
        }

        return [
            'sql' => '(' . implode(' + ', $scoreTerms) . ') DESC',
            'bindings' => $scoreBindings,
            'all_matched' => $hasAllTokenMatches,
        ];
    }

    /**
     * Convenience wrapper for Spare Parts Marketplace joined with `repair_shops`.
     *
     * @return array{sql: string, bindings: array<int, mixed>}|null
     */
    public static function applySparePartSearch(Builder $query, ?string $rawSearch): ?array
    {
        return self::applySearch(
            $query,
            $rawSearch,
            [
                'spare_part_listings.title',
                'spare_part_listings.compatible_model',
                'spare_part_listings.brand',
                'spare_part_listings.part_category',
            ],
            [
                'spare_part_listings.sku',
                'repair_shops.name',
                'repair_shops.branch_name',
                'repair_shops.city',
                'repair_shops.area',
            ]
        );
    }

    /**
     * Convenience wrapper for Repair Shops directory, matching workshop fields plus offered services and stocked parts.
     *
     * @return array{sql: string, bindings: array<int, mixed>}|null
     */
    public static function applyShopSearch(Builder $query, ?string $rawSearch): ?array
    {
        return self::applySearch(
            $query,
            $rawSearch,
            [
                'repair_shops.name',
                'repair_shops.branch_name',
                'CAST(repair_shops.specialties AS TEXT)',
            ],
            [
                'repair_shops.description',
                'repair_shops.address',
                'repair_shops.area',
                'repair_shops.city',
            ],
            function (Builder $groupQuery, array $patterns, bool $isPg) {
                $groupQuery->orWhereHas('services', function (Builder $svcQuery) use ($patterns, $isPg) {
                    $svcQuery->where('is_active', true)->where(function (Builder $sub) use ($patterns, $isPg) {
                        foreach (['name', 'issue_category', 'device_type'] as $col) {
                            $sql = self::columnLikeSql($col, $isPg);
                            foreach ($patterns as $pat) {
                                $sub->orWhereRaw($sql, [$pat]);
                            }
                        }
                    });
                })->orWhereExists(function ($partExists) use ($patterns, $isPg) {
                    $partExists->select(DB::raw(1))
                        ->from('spare_part_listings')
                        ->whereColumn('spare_part_listings.shop_id', 'repair_shops.id')
                        ->where('spare_part_listings.is_active', true)
                        ->where(function ($sub) use ($patterns, $isPg) {
                            foreach (['title', 'compatible_model', 'brand', 'part_category'] as $col) {
                                $sql = self::columnLikeSql($col, $isPg);
                                foreach ($patterns as $pat) {
                                    $sub->orWhereRaw($sql, [$pat]);
                                }
                            }
                        });
                });
            }
        );
    }

    public static function columnLikeSql(string $columnExpr, bool $isPg): string
    {
        if ($isPg) {
            return "{$columnExpr} ILIKE ?";
        }
        return "LOWER(COALESCE({$columnExpr}, '')) LIKE ?";
    }
}

<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Tokenized, synonym-aware & typo-tolerant marketplace search engine.
 *
 * Balances strict hardware model/spec precision (no cross-brand pollution,
 * no capacity/generation drift, no numeric SKU false positives) with
 * multi-signal relevance ranking and tiered typo tolerance.
 */
class MarketplaceSearchEngine
{
    private const FILLER_WORDS = [
        'for', 'my', 'the', 'with', 'and', 'in', 'on', 'at', 'of', 'to', 'a', 'an',
        'need', 'want', 'buy', 'get', 'find', 'looking', 'replace', 'replacement',
        'original', 'genuine', 'oem', 'new', 'fix', 'repair', 'repairs', 'service',
        'services', 'part', 'parts', 'shop', 'shops', 'store', 'near', 'me', 'please',
        'price', 'bd', 'bangladesh',
    ];

    /**
     * Symmetric true synonym / common-typo groups.
     * Deliberately excludes parent-brand <-> sub-line expansions (e.g. macbook <-> apple)
     * and non-interchangeable component tiers (e.g. nvme <-> sata, screen <-> glass).
     */
    private const SYNONYM_GROUPS = [
        ['iphone', 'iphne', 'ipone', 'ifone', 'iphon', 'ipohne'],
        ['macbook', 'macbok', 'mcbook', 'makbook'],
        ['ipad', 'ipd'],
        ['imac'],
        ['samsung', 'samsng', 'smasung', 'samung', 'sumsung', 'samsug', 'galaxy', 'galxy', 'galaxi'],
        ['xiaomi', 'xaomi', 'xiomi', 'xioami', 'xiami', 'redmi', 'redmy', 'poco'],
        ['oneplus', '1plus', 'onepls', 'nord'],
        ['huawei', 'huwei', 'hawaei', 'huawey'],
        ['motorola', 'motrola', 'moto'],
        ['lenovo', 'lenvo', 'lonovo'],
        ['thinkpad', 'thinkpd'],
        ['ideapad', 'ideapd'],
        ['thinkbook'],
        ['asus', 'assus', 'azus'],
        ['zenbook', 'zenbok'],
        ['vivobook', 'vivobok'],
        ['dell', 'del'],
        ['inspiron'],
        ['latitude'],
        ['xps'],
        ['vostro'],
        ['hp', 'hewlett'],
        ['pavilion'],
        ['spectre'],
        ['envy'],
        ['probook'],
        ['elitebook'],
        ['victus'],
        ['acer', 'acr'],
        ['aspire'],
        ['nitro'],
        ['swift'],
        ['predator'],
        ['realme', 'relme', 'realmy'],
        ['oppo', 'opo'],
        ['vivo', 'vvo', 'iqoo'],
        ['infinix', 'infnx'],
        ['tecno', 'tekno', 'camon', 'spark'],
        ['google', 'pixel', 'pixl'],
        ['playstation', 'ps4', 'ps5', 'dualsense'],
        ['canon', 'pixma', 'printer', 'prntr'],
        ['screen', 'scren', 'scrn', 'display', 'disply', 'dispaly', 'lcd', 'oled', 'amoled', 'panel', 'digitizer', 'incell'],
        ['battery', 'battry', 'batery', 'battrey', 'bettery', 'btry'],
        ['keyboard', 'keybord', 'kyboard', 'keybaord', 'keypad', 'keycap'],
        ['charger', 'chrger', 'chargr', 'adapter', 'adaptor', 'psu', 'magsafe'],
        ['charging', 'charge', 'chrgng', 'port', 'connector', 'logic', 'sub board', 'dock', 'jack'],
        ['typec', 'type-c', 'usb-c', 'usbc'],
        ['flex', 'ribbon'],
        ['nvme', 'm.2', 'm2', 'pcie'],
        ['ddr4', 'ddr4l'],
        ['ddr3', 'ddr3l'],
        ['motherboard', 'mtherboard', 'mobo', 'logicboard', 'mainboard', 'pcb', 'ic', 'pmic', 'rework'],
        ['fan', 'cooler', 'cooling', 'heatsink', 'thermal', 'overheat', 'overheating', 'paste', 'repaste'],
        ['solder', 'soldering', 'solderng', 'flux', 'iron', 'tools', 'tool', 'workstation', 'microscope', 'screwdriver'],
        ['speaker', 'earpiece', 'loudspeaker', 'ringer', 'buzzer'],
        ['mic', 'microphone', 'audio', 'sound'],
        ['camera', 'camra', 'webcam', 'lens'],
        ['trackpad', 'touchpad'],
        ['hinge', 'hinges'],
        ['backshell', 'back glass', 'back cover', 'housing', 'chassis', 'frame', 'bezel', 'casing'],
        ['water', 'liquid', 'spill', 'ultrasonic', 'corrosion'],
        ['protech', 'protek', 'protch'],
        ['dhaka', 'banani', 'dhanmondi', 'gulshan', 'uttara', 'mirpur', 'mohakhali', 'bashundhara', 'farmgate', 'hatirpool'],
        ['chattogram', 'chittagong', 'ctg', 'agrabad', 'nasirabad', 'khulshi'],
    ];

    /**
     * Asymmetric umbrella expansions so broad terms match specifics without
     * polluting specific sub-type queries in reverse (e.g. "ssd" -> "nvme" / "sata",
     * while "nvme" never expands back to "sata").
     */
    private const ONE_WAY_EXPANSIONS = [
        'ssd' => ['nvme', 'm.2', 'm2', 'sata'],
        'sony' => ['xperia', 'playstation', 'ps4', 'ps5', 'dualsense'],
        'virus' => ['malware', 'adware', 'spyware', 'troubleshoot', 'security'],
        'malware' => ['virus', 'adware', 'spyware', 'troubleshoot'],
        'antivirus' => ['virus', 'malware', 'adware', 'spyware', 'troubleshoot', 'security'],
        'hacked' => ['virus', 'malware', 'adware', 'spyware', 'troubleshoot', 'security'],
        'adware' => ['virus', 'malware', 'spyware', 'troubleshoot'],
        'spyware' => ['virus', 'malware', 'adware', 'troubleshoot'],
        'troubleshoot' => ['virus', 'malware', 'adware', 'recovery'],
        'root' => ['rooting', 'bootloader', 'magisk', 'kernelsu', 'firmware', 'rom'],
        'rooting' => ['root', 'bootloader', 'magisk', 'kernelsu', 'firmware', 'rom'],
        'bootloader' => ['rooting', 'root', 'firmware', 'rom', 'flash'],
        'bootloop' => ['root', 'rooting', 'bootloader', 'firmware', 'rom', 'recovery'],
        'unbrick' => ['root', 'rooting', 'bootloader', 'firmware', 'rom'],
        'rom' => ['root', 'rooting', 'bootloader', 'firmware', 'flash'],
        'os' => ['windows', 'linux', 'macos', 'drivers', 'bios', 'installation'],
        'windows' => ['os', 'linux', 'macos', 'drivers', 'bios', 'installation'],
        'linux' => ['os', 'windows', 'drivers', 'bios', 'installation'],
        'macos' => ['os', 'drivers', 'bios', 'installation'],
        'ubuntu' => ['os', 'linux', 'drivers', 'installation'],
        'format' => ['os', 'windows', 'drivers', 'installation', 'backup'],
        'reinstall' => ['os', 'windows', 'linux', 'drivers', 'installation'],
        'bios' => ['uefi', 'os', 'drivers', 'firmware', 'boot'],
        'uefi' => ['bios', 'os', 'drivers', 'firmware', 'boot'],
        'cloning' => ['clone', 'migration', 'partition', 'os', 'storage'],
        'clone' => ['cloning', 'migration', 'partition', 'os', 'storage'],
        'migration' => ['clone', 'cloning', 'partition', 'transfer', 'backup'],
        'partition' => ['cloning', 'clone', 'migration', 'os'],
        'reflash' => ['flash', 'firmware', 'bootloop', 'os', 'restore'],
        'fastboot' => ['flash', 'reflash', 'bootloader', 'firmware', 'bootloop'],
        'dfu' => ['flash', 'reflash', 'firmware', 'restore'],
        'muffled' => ['clean', 'cleaning', 'mesh', 'lint', 'speaker', 'mic'],
        'lint' => ['clean', 'cleaning', 'mesh', 'muffled', 'port'],
        'transfer' => ['backup', 'cloud', 'migration', 'data'],
        'drift' => ['controller', 'analog', 'calibration', 'cleaning'],
        'router' => ['wifi', 'mesh', 'network', 'configuration', 'security'],
        'wifi' => ['router', 'mesh', 'network', 'configuration'],
        'mesh' => ['wifi', 'router', 'network', 'clean', 'cleaning'],
        'jam' => ['roller', 'feed', 'paper', 'printer'],
        'roller' => ['jam', 'feed', 'paper', 'printer'],
        'backup' => ['recovery', 'data', 'migration', 'encrypted', 'transfer', 'cloud'],
        'recovery' => ['backup', 'data', 'migration', 'encrypted', 'corrupted'],
        'corrupted' => ['recovery', 'backup', 'os', 'troubleshoot'],
        'liquid' => ['water', 'spill', 'ultrasonic', 'corrosion', 'chemical'],
        'water' => ['liquid', 'spill', 'ultrasonic', 'corrosion', 'chemical'],
        'wet' => ['liquid', 'water', 'spill', 'ultrasonic', 'corrosion'],
        'spill' => ['water', 'liquid', 'ultrasonic', 'corrosion', 'chemical'],
        'corrosion' => ['liquid', 'water', 'ultrasonic', 'chemical', 'spill'],
        'thermal' => ['repasting', 'dust', 'cooling', 'overheating'],
        'repasting' => ['thermal', 'dust', 'cooling'],
        'repaste' => ['repasting', 'thermal', 'dust', 'cooling'],
        'overheating' => ['thermal', 'repasting', 'dust', 'cooling'],
        'heating' => ['thermal', 'repasting', 'dust', 'cooling', 'overheating'],
        'dust' => ['thermal', 'repasting', 'cooling', 'fan'],
        'maintenance' => ['preventive', 'tune-up', 'tuneup', 'optimization', 'thermal', 'clean', 'cleaning'],
        'tuneup' => ['tune-up', 'maintenance', 'optimization'],
        'tune' => ['tune-up', 'maintenance'],
        'cleaning' => ['clean', 'dust', 'ultrasonic'],
        'clean' => ['cleaning', 'dust', 'ultrasonic'],
        'cartridges' => ['cartridge', 'toner', 'ink', 'refill', 'printhead'],
        'cartridge' => ['toner', 'ink', 'refill', 'printhead'],
        'toner' => ['cartridge', 'refill', 'printhead'],
        'ink' => ['cartridge', 'refill', 'printhead'],
        'refill' => ['cartridge', 'toner', 'ink', 'printhead'],
        'hdd' => ['sata', 'caddy', 'hard drive'],
        'storage' => ['ssd', 'nvme', 'hdd', 'sata'],
        'ram' => ['memory', 'sodimm', 'so-dimm', 'ddr3', 'ddr3l', 'ddr4', 'ddr4l', 'ddr5'],
        'memory' => ['ram', 'sodimm', 'so-dimm', 'ddr3', 'ddr4', 'ddr5'],
        'sata' => ['hdd', 'caddy'],
        'battery' => ['cell'],
        'display' => ['assembly', 'glass'],
        'screen' => ['assembly', 'glass'],
        'b7000' => ['b-7000', 'adhesive', 'glue'],
        't7000' => ['t-7000', 'adhesive', 'glue'],
        'glue' => ['adhesive', 'b-7000', 'b7000', 't-7000'],
        'adhesive' => ['glue', 'b-7000', 'b7000', 'tape'],
    ];

    /**
     * High-signal electronics marketplace vocabulary for Levenshtein typo correction.
     */
    private const CATALOG_VOCABULARY = [
        'apple', 'samsung', 'xiaomi', 'oppo', 'vivo', 'realme', 'oneplus', 'asus', 'dell', 'hp',
        'lenovo', 'acer', 'msi', 'microsoft', 'google', 'infinix', 'tecno', 'motorola', 'huawei',
        'nokia', 'sony', 'canon', 'universal', 'transcend', 'kingspec', 'semsotai', 'gskill',
        'kaisi', 'mechanic', 'zhanlida', 'relife', 'sunshine', 'quick',
        'iphone', 'macbook', 'imac', 'ipad', 'galaxy', 'thinkpad', 'ideapad', 'thinkbook',
        'pavilion', 'spectre', 'envy', 'probook', 'elitebook', 'zenbook', 'vivobook', 'inspiron',
        'latitude', 'pixel', 'redmi', 'playstation', 'pixma', 'surface', 'book', 'ultra', 'plus', 'mini',
        'display', 'screen', 'amoled', 'oled', 'lcd', 'ips', 'digitizer', 'battery', 'keyboard',
        'backlit', 'motherboard', 'charging', 'charger', 'adapter', 'connector', 'thermal', 'cooling',
        'heatsink', 'overheating', 'soldering', 'workstation', 'speaker', 'earpiece', 'microphone',
        'camera', 'hinge', 'trackpad', 'touchpad', 'backlight', 'ultrasonic', 'assembly', 'original',
        'genuine', 'controller', 'housing', 'backshell', 'storage', 'memory', 'ribbon', 'calibration',
        'diagnostic', 'replacement', 'nvme', 'sata', 'pcie', 'sodimm', 'screwdriver', 'multimeter',
        'adhesive', 'tweezers', 'spudger', 'flux', 'tray', 'sim', 'glass', 'upper', 'outer', 'replacement',
        'sony', 'xperia', 'virus', 'malware', 'adware', 'troubleshoot', 'rooting', 'bootloader', 'firmware',
        'drivers', 'bios', 'uefi', 'cloning', 'clone', 'migration', 'partition', 'reflash', 'fastboot', 'dfu',
        'muffled', 'lint', 'transfer', 'drift', 'router', 'wifi', 'mesh', 'jam', 'roller',
        'thermal', 'repasting', 'ultrasonic', 'liquid', 'spill', 'backup', 'recovery', 'encrypted',
        'protech', 'doctors', 'gadget', 'budget', 'macmaster', 'garage', 'bytesize', 'gulshan',
        'applecore', 'precision', 'mirpur', 'uttara', 'bashundhara', 'farmgate', 'miraz', 'telecom',
        'dhaka', 'chattogram', 'chittagong', 'banani', 'dhanmondi', 'mohakhali', 'agrabad', 'hatirpool',
    ];

    /**
     * Cached set of known dictionary tokens for fast exact checks.
     *
     * @var array<string, true>|null
     */
    private static ?array $knownWordSet = null;

    private static function knownWordSet(): array
    {
        if (self::$knownWordSet !== null) {
            return self::$knownWordSet;
        }

        $set = [];
        foreach (self::CATALOG_VOCABULARY as $w) {
            $set[$w] = true;
        }
        foreach (self::SYNONYM_GROUPS as $group) {
            foreach ($group as $w) {
                $set[$w] = true;
            }
        }
        foreach (self::ONE_WAY_EXPANSIONS as $k => $vals) {
            $set[$k] = true;
            foreach ($vals as $v) {
                $set[$v] = true;
            }
        }

        return self::$knownWordSet = $set;
    }

    /**
     * Normalize & split raw search input into clean semantic tokens while keeping
     * atomic hardware specs (ddr4, 8gb, 512gb, 102w) and compact model codes (s22, a54, t480, b7000) intact.
     *
     * @return array<int, string>
     */
    public static function tokenize(string $rawQuery): array
    {
        $normalized = mb_strtolower(trim($rawQuery), 'UTF-8');
        if ($normalized === '') {
            return [];
        }

        // Preserve compound connectivity / memory specs before punctuation cleanup
        $normalized = str_replace(
            ['usb type-c', 'usb-c', 'type-c', 'so-dimm', 'li-po', 'li-ion', 'promax'],
            ['typec', 'typec', 'typec', 'sodimm', 'lipo', 'liion', 'pro max'],
            $normalized
        );
        $normalized = preg_replace('/\bm\.2\b/i', 'm2', $normalized) ?? $normalized;

        // Combine spaced capacity/power units ("8 gb" -> "8gb", "512 gb" -> "512gb", "102 w" -> "102w")
        $normalized = preg_replace('/\b(\d+)\s+(gb|tb|mb|mah|wh|mhz|ghz|hz|w|v)\b/i', '$1$2', $normalized) ?? $normalized;

        // Split concatenated word+digits for multi-char words >= 3 letters EXCEPT hardware/spec prefixes
        $normalized = preg_replace_callback('/\b([a-z]{3,})(\d+)([a-z]*)\b/i', function (array $m) {
            $prefix = strtolower($m[1]);
            $skipPrefixes = ['ddr', 'ssd', 'ram', 'usb', 'led', 'lcd', 'ips', 'pcie', 'sata', 'nvme', 'gen', 'win', 'pro', 'tab', 'pad', 'amd', 'ati', 'cpu', 'gpu', 'hdd'];
            if (in_array($prefix, $skipPrefixes, true)) {
                return $m[0];
            }
            return trim($m[1] . ' ' . $m[2] . ($m[3] !== '' ? (' ' . $m[3]) : ''));
        }, $normalized) ?? $normalized;

        // Split concatenated variant suffix after model numbers (e.g. "s23ultra" -> "s23 ultra", "14pro" -> "14 pro")
        // while keeping storage/memory units (8gb, 512gb) and short suffix letters (a54s, 858d) intact
        $normalized = preg_replace(
            '/(\d+)(?!(?:gb|tb|mb|kb|mah|wh|hz|mhz|ghz|th|nd|rd|st)\b)([a-z]{2,})/i',
            '$1 $2',
            $normalized
        ) ?? $normalized;

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
     * Expand a normalized token into structured matching tiers:
     * exact variants, true synonyms/typos, word-boundary regexes, and fallback wildcards.
     *
     * @return array{
     *   token: string,
     *   is_numeric: bool,
     *   has_digits: bool,
     *   is_compact_alnum: bool,
     *   allow_secondary: bool,
     *   exact_terms: array<int, string>,
     *   synonym_terms: array<int, string>,
     *   wildcards: array<int, string>,
     *   boundary_regex: ?string
     * }
     */
    public static function expandTokenGroup(string $token): array
    {
        $token = strtolower(trim($token));
        $isNumeric = ctype_digit($token);
        $hasDigits = preg_match('/\d/', $token) === 1;
        $isUnitSpec = preg_match('/^(\d+)(gb|tb|mb|mah|wh|hz|mhz|ghz|w|v)$/i', $token, $unitMatch) === 1;
        $isCompactAlnum = !$isNumeric && $hasDigits && strlen($token) <= 6;

        $exactTerms = [$token];
        $synonymTerms = [];

        // Basic plural/verb stem for alphabetic tokens >= 4 chars
        $stem = null;
        if (ctype_alpha($token) && strlen($token) >= 4) {
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
                $exactTerms[] = $stem;
            }
        }

        // Hyphen/space/dot variants for compact model & spare-part codes (b7000 -> b-7000, t480 -> t-480)
        if (preg_match('/^([a-z]{1,4})(\d{2,5}[a-z]?)$/i', $token, $codeMatch) === 1 && !$isUnitSpec) {
            $alpha = strtolower($codeMatch[1]);
            $numPart = strtolower($codeMatch[2]);
            if (!in_array($alpha, ['ddr'], true)) {
                $exactTerms[] = $alpha . '-' . $numPart;
                $exactTerms[] = $alpha . ' ' . $numPart;
            }
        } elseif ($isUnitSpec) {
            $exactTerms[] = strtolower($unitMatch[1]) . ' ' . strtolower($unitMatch[2]);
        }

        // Build word-boundary regexes (POSIX ERE compatible for PostgreSQL `~*`)
        $boundaryParts = [];
        if ($isNumeric) {
            // Match exact standalone model numbers and single-letter suffix variants (7, 7a, 13, 13s), never Pixel 2 or SKU 741
            $boundaryParts[] = '(^|[^a-z0-9])' . $token . '([a-z]?([^a-z0-9]|$))';
        } elseif ($isUnitSpec) {
            $boundaryParts[] = '(^|[^a-z0-9])' . $unitMatch[1] . '[[:space:]]*' . strtolower($unitMatch[2]) . '([^a-z0-9]|$)';
        } elseif ($hasDigits) {
            $segPattern = preg_replace('/([a-z]+)(\d+)/i', '$1[- .]?$2', $token) ?? $token;
            $segPattern = preg_replace('/(\d+)([a-z]+)/i', '$1[- .]?$2', $segPattern) ?? $segPattern;
            $boundaryParts[] = '(^|[^a-z0-9])' . $segPattern . '([a-z]?([^a-z0-9]|$))';
        } elseif (ctype_alpha($token) && strlen($token) >= 2) {
            $boundaryParts[] = '(^|[^a-z0-9])' . $token . '([^a-z0-9]|$)';
        }

        // Match symmetric synonym groups
        $seedCandidates = $exactTerms;
        foreach (self::SYNONYM_GROUPS as $group) {
            foreach ($seedCandidates as $cand) {
                if (in_array($cand, $group, true)) {
                    foreach ($group as $syn) {
                        if ($syn !== $token) {
                            $synonymTerms[] = $syn;
                        }
                    }
                    break;
                }
            }
        }

        // Match one-way broad expansions (only for the queried token itself or its stem)
        foreach ($seedCandidates as $cand) {
            if (isset(self::ONE_WAY_EXPANSIONS[$cand])) {
                foreach (self::ONE_WAY_EXPANSIONS[$cand] as $exp) {
                    $synonymTerms[] = $exp;
                }
            }
        }

        // Tiered typo correction ONLY when unknown alphabetic word (skip for clean known words & model codes)
        $known = self::knownWordSet();
        $isKnown = isset($known[$token]) || ($stem !== null && isset($known[$stem]));
        $wildcards = [];

        if (!$hasDigits && !$isKnown && ctype_alpha($token) && strlen($token) >= 4 && strlen($token) <= 16) {
            // 1. Collapsed duplicate chars ("samsuung" -> "samsun", "orbitt" -> "orbit")
            $deduped = preg_replace('/(.)\1+/', '$1', $token);
            if (is_string($deduped) && strlen($deduped) >= 4 && $deduped !== $token) {
                $synonymTerms[] = $deduped;
            }

            // 2. Levenshtein against all known catalog words + synonym groups
            $maxDist = strlen($token) >= 6 ? 2 : 1;
            $levHits = [];
            foreach (array_keys($known) as $vocab) {
                if (!ctype_alpha($vocab) || abs(strlen($vocab) - strlen($token)) > $maxDist) {
                    continue;
                }
                if (levenshtein($token, $vocab) <= $maxDist) {
                    $levHits[] = $vocab;
                    $synonymTerms[] = $vocab;
                }
            }

            // Expand symmetric synonyms for top Levenshtein correction hits
            if (!empty($levHits)) {
                foreach (self::SYNONYM_GROUPS as $group) {
                    foreach ($levHits as $hit) {
                        if (in_array($hit, $group, true)) {
                            foreach ($group as $syn) {
                                $synonymTerms[] = $syn;
                            }
                            break;
                        }
                    }
                }
            }

            // 3. Only if Levenshtein found ZERO hits, add compact transpositions & max 6 single-char `_` wildcards
            if (empty($levHits)) {
                $len = strlen($token);
                for ($i = 0; $i < $len - 1; $i++) {
                    if ($token[$i] !== $token[$i + 1]) {
                        $synonymTerms[] = substr($token, 0, $i) . $token[$i + 1] . $token[$i] . substr($token, $i + 2);
                    }
                }
                for ($i = 1; $i < $len && count($wildcards) < 6; $i++) {
                    $wildcards[] = substr($token, 0, $i) . '_' . substr($token, $i);
                }
            }
        }

        // Include synonym terms like "m.2" into PG boundary regexes when token expands to short ambiguous aliases
        foreach ($synonymTerms as $syn) {
            if (strlen($syn) <= 3 && !in_array($syn, ['ssd', 'ram', 'hdd', 'psu', 'lcd', 'mic'], true)) {
                $escaped = str_replace('.', '\\.', preg_replace('/[^a-z0-9.]/', '', $syn) ?? '');
                if ($escaped !== '') {
                    $boundaryParts[] = '(^|[^a-z0-9])' . $escaped . '([^a-z0-9]|$)';
                }
            }
        }

        $exactTerms = array_values(array_unique(array_filter($exactTerms, fn ($t) => is_string($t) && $t !== '')));
        $synonymTerms = array_values(array_unique(array_diff(
            array_filter($synonymTerms, fn ($t) => is_string($t) && $t !== ''),
            $exactTerms
        )));
        $wildcards = array_values(array_unique($wildcards));

        // Do not match pure digits, unit specs (8gb/512gb/15v), or short alphanumeric model codes (s22, a54, ddr4, m2)
        // against secondary columns like repair_shops.city / name / random SKUs.
        $allowSecondary = !$isNumeric && !$isUnitSpec && !$isCompactAlnum;

        return [
            'token' => $token,
            'is_numeric' => $isNumeric,
            'has_digits' => $hasDigits,
            'is_compact_alnum' => $isCompactAlnum,
            'allow_secondary' => $allowSecondary,
            'exact_terms' => array_slice($exactTerms, 0, 8),
            'synonym_terms' => array_slice($synonymTerms, 0, 14),
            'wildcards' => array_slice($wildcards, 0, 6),
            'boundary_regex' => !empty($boundaryParts) ? implode('|', array_unique($boundaryParts)) : null,
        ];
    }

    /**
     * Build precision-aware SQL WHERE constraints for one token group.
     */
    private static function applyGroupToQuery(
        Builder $q,
        array $group,
        array $primaryColumns,
        array $secondaryColumns,
        bool $isPg,
        ?callable $extraGroupMatcher = null
    ): void {
        $useBoundaryOnlyOnPrimary = $isPg
            && ($group['is_numeric'] || $group['has_digits'])
            && $group['boundary_regex'] !== null;

        if ($useBoundaryOnlyOnPrimary) {
            foreach ($primaryColumns as $col) {
                $q->orWhereRaw("COALESCE({$col}, '') ~* ?", [$group['boundary_regex']]);
            }
            foreach ($group['synonym_terms'] as $syn) {
                if (strlen($syn) > 3) {
                    $pat = '%' . $syn . '%';
                    foreach ($primaryColumns as $col) {
                        $q->orWhereRaw(self::columnLikeSql($col, true), [$pat]);
                    }
                }
            }
        } else {
            $patterns = [];
            foreach (array_merge($group['exact_terms'], $group['synonym_terms'], $group['wildcards']) as $pat) {
                $patterns[] = '%' . $pat . '%';
            }
            $patterns = array_values(array_unique($patterns));

            foreach ($primaryColumns as $col) {
                $sql = self::columnLikeSql($col, $isPg);
                foreach ($patterns as $pattern) {
                    $q->orWhereRaw($sql, [$pattern]);
                }
            }

            if ($group['allow_secondary'] && !empty($secondaryColumns)) {
                $secTerms = array_values(array_unique(array_merge(
                    $group['exact_terms'],
                    array_slice($group['synonym_terms'], 0, 6)
                )));
                foreach ($secondaryColumns as $col) {
                    $sql = self::columnLikeSql($col, $isPg);
                    foreach ($secTerms as $st) {
                        $q->orWhereRaw($sql, ['%' . $st . '%']);
                    }
                }
            }
        }

        if ($extraGroupMatcher !== null) {
            $extraPatterns = [];
            foreach (array_merge($group['exact_terms'], $group['synonym_terms'], $group['wildcards']) as $pat) {
                $extraPatterns[] = '%' . $pat . '%';
            }
            $extraPatterns = array_values(array_unique($extraPatterns));
            if (!empty($extraPatterns)) {
                $extraGroupMatcher($q, $extraPatterns, $isPg);
            }
        }
    }

    /**
     * Apply two-stage adaptive token search + compute a multi-signal relevance ORDER BY expression.
     *
     * @return array{sql: string, bindings: array<int, mixed>, all_matched: bool}|null
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
        if (count($tokens) > 1) {
            $softIntentWords = [
                'replacement', 'replace', 'replacing', 'change', 'changing',
                'broken', 'cracked', 'damage', 'damaged', 'dead', 'issue', 'issues', 'problem', 'problems',
            ];
            $coreTokens = array_values(array_diff($tokens, $softIntentWords));
            if (!empty($coreTokens)) {
                $tokens = $coreTokens;
            }
        }
        if (empty($tokens)) {
            return null;
        }

        $isPg = DB::connection()->getDriverName() === 'pgsql';
        $groups = array_map(fn (string $t) => self::expandTokenGroup($t), $tokens);

        // Stage 1: Probe all token groups (`AND` across groups) on a clone before mutating $query
        $stage1Probe = clone $query;
        $stage1Probe->where(function (Builder $outer) use ($groups, $primaryColumns, $secondaryColumns, $isPg, $extraGroupMatcher) {
            foreach ($groups as $group) {
                $outer->where(function (Builder $inner) use ($group, $primaryColumns, $secondaryColumns, $isPg, $extraGroupMatcher) {
                    self::applyGroupToQuery($inner, $group, $primaryColumns, $secondaryColumns, $isPg, $extraGroupMatcher);
                });
            }
        });

        $hasAllTokenMatches = count($groups) <= 1 || $stage1Probe->limit(1)->exists();

        if ($hasAllTokenMatches) {
            $query->where(function (Builder $outer) use ($groups, $primaryColumns, $secondaryColumns, $isPg, $extraGroupMatcher) {
                foreach ($groups as $group) {
                    $outer->where(function (Builder $inner) use ($group, $primaryColumns, $secondaryColumns, $isPg, $extraGroupMatcher) {
                        self::applyGroupToQuery($inner, $group, $primaryColumns, $secondaryColumns, $isPg, $extraGroupMatcher);
                    });
                }
            });
        } else {
            // Stage 2 Fallback: at least one token group matches when combined phrase yields 0 results
            $query->where(function (Builder $outer) use ($groups, $primaryColumns, $secondaryColumns, $isPg, $extraGroupMatcher) {
                foreach ($groups as $group) {
                    $outer->orWhere(function (Builder $inner) use ($group, $primaryColumns, $secondaryColumns, $isPg, $extraGroupMatcher) {
                        self::applyGroupToQuery($inner, $group, $primaryColumns, $secondaryColumns, $isPg, $extraGroupMatcher);
                    });
                }
            });
        }

        // Build tiered multi-signal relevance scoring SQL
        $scoreTerms = [];
        $scoreBindings = [];
        $titleCol = $primaryColumns[0] ?? null;

        $phraseClean = preg_replace('/[^a-z0-9 ]+/i', ' ', strtolower($rawTrimmed));
        $phraseClean = trim((string) preg_replace('/\s+/', ' ', (string) $phraseClean));
        if ($phraseClean !== '' && $titleCol !== null) {
            $scoreTerms[] = '(CASE WHEN ' . self::columnLikeSql($titleCol, $isPg) . ' THEN 18 ELSE 0 END)';
            $scoreBindings[] = '%' . $phraseClean . '%';
        }

        foreach ($groups as $group) {
            $exactTitleClauses = [];
            $exactPrimaryClauses = [];
            $synPrimaryClauses = [];
            $secClauses = [];

            // Tier A (+14): Strict word-boundary / exact token hit in title
            if ($titleCol !== null) {
                if ($isPg && $group['boundary_regex'] !== null) {
                    $exactTitleClauses[] = "COALESCE({$titleCol}, '') ~* ?";
                    $scoreBindings[] = $group['boundary_regex'];
                } else {
                    foreach (array_slice($group['exact_terms'], 0, 3) as $et) {
                        $exactTitleClauses[] = self::columnLikeSql($titleCol, $isPg);
                        $scoreBindings[] = '%' . $et . '%';
                    }
                }
            }

            // Tier B (+8): Exact terms in any primary column
            foreach ($primaryColumns as $pCol) {
                if ($isPg && $group['boundary_regex'] !== null && ($group['is_numeric'] || $group['has_digits'])) {
                    $exactPrimaryClauses[] = "COALESCE({$pCol}, '') ~* ?";
                    $scoreBindings[] = $group['boundary_regex'];
                } else {
                    $colSql = self::columnLikeSql($pCol, $isPg);
                    foreach (array_slice($group['exact_terms'], 0, 3) as $et) {
                        $exactPrimaryClauses[] = $colSql;
                        $scoreBindings[] = '%' . $et . '%';
                    }
                }
            }

            // Tier C (+5): Synonym / typo terms in primary columns
            $synSlice = array_slice($group['synonym_terms'], 0, 6);
            if (!empty($synSlice)) {
                foreach ($primaryColumns as $pCol) {
                    $colSql = self::columnLikeSql($pCol, $isPg);
                    foreach ($synSlice as $st) {
                        $synPrimaryClauses[] = $colSql;
                        $scoreBindings[] = '%' . $st . '%';
                    }
                }
            }

            // Tier D (+2): Secondary column hit (shop / area / SKU) when allowed
            if ($group['allow_secondary'] && !empty($secondaryColumns)) {
                foreach ($secondaryColumns as $sCol) {
                    $colSql = self::columnLikeSql($sCol, $isPg);
                    foreach (array_slice($group['exact_terms'], 0, 2) as $et) {
                        $secClauses[] = $colSql;
                        $scoreBindings[] = '%' . $et . '%';
                    }
                }
            }

            $whenBranches = [];
            if (!empty($exactTitleClauses)) {
                $whenBranches[] = 'WHEN (' . implode(' OR ', $exactTitleClauses) . ') THEN 14';
            }
            if (!empty($exactPrimaryClauses)) {
                $whenBranches[] = 'WHEN (' . implode(' OR ', $exactPrimaryClauses) . ') THEN 8';
            }
            if (!empty($synPrimaryClauses)) {
                $whenBranches[] = 'WHEN (' . implode(' OR ', $synPrimaryClauses) . ') THEN 5';
            }
            if (!empty($secClauses)) {
                $whenBranches[] = 'WHEN (' . implode(' OR ', $secClauses) . ') THEN 2';
            }

            if (!empty($whenBranches)) {
                $scoreTerms[] = '(CASE ' . implode(' ', $whenBranches) . ' ELSE 0 END)';
            }
        }

        if (empty($scoreTerms)) {
            return null;
        }

        $expr = '(' . implode(' + ', $scoreTerms) . ')';

        return [
            'sql' => $expr . ' DESC',
            'expr' => $expr,
            'bindings' => $scoreBindings,
            'all_matched' => $hasAllTokenMatches,
            'tokens' => $tokens,
            'is_pg' => $isPg,
        ];
    }

    /**
     * Convenience wrapper for Spare Parts Marketplace joined with `repair_shops`,
     * adding base-model preference and outer-glass / cable demotion for cleaner rankings.
     *
     * @return array{sql: string, bindings: array<int, mixed>, all_matched: bool}|null
     */
    public static function applySparePartSearch(Builder $query, ?string $rawSearch): ?array
    {
        $tokens = self::tokenize((string) $rawSearch);
        $coreTokens = $tokens;
        if (count($coreTokens) > 1) {
            $softIntentWords = [
                'replacement', 'replace', 'replacing', 'change', 'changing',
                'broken', 'cracked', 'damage', 'damaged', 'dead', 'issue', 'issues', 'problem', 'problems',
            ];
            $stripped = array_values(array_diff($coreTokens, $softIntentWords));
            if (!empty($stripped)) {
                $coreTokens = $stripped;
            }
        }

        $primaryCols = [
            'spare_part_listings.title',
            'spare_part_listings.compatible_model',
            'spare_part_listings.brand',
        ];
        // Include part_category ONLY for single-core-token browsing (e.g. "Battery", "Display replacement")
        // so multi-word queries like "sim tray s23" never pull unrelated "SIM Tray & Small Parts" items (Ear Speaker).
        if (count($coreTokens) <= 1) {
            $primaryCols[] = 'spare_part_listings.part_category';
        }

        $result = self::applySearch(
            $query,
            $rawSearch,
            $primaryCols,
            [
                'spare_part_listings.sku',
                'repair_shops.name',
                'repair_shops.branch_name',
                'repair_shops.city',
                'repair_shops.area',
            ]
        );

        if ($result === null) {
            return null;
        }

        $isPg = (bool) ($result['is_pg'] ?? false);
        $extraTerms = [];
        $extraBindings = [];

        // Variant precision penalty: when searching a base model (e.g. "iPhone 13 Display", "Pixel 7 Battery"),
        // demote Pro / Max / Mini / Plus / Ultra / FE / Lite variants so exact base model leads.
        $variantTokens = ['mini', 'pro', 'max', 'plus', 'ultra', 'fe', 'lite', 'air'];
        $asksVariant = !empty(array_intersect($coreTokens, $variantTokens));
        if (!$asksVariant && $isPg) {
            $extraTerms[] = "(CASE WHEN COALESCE(spare_part_listings.title, '') ~* ? THEN -8 ELSE 0 END)";
            $extraBindings[] = '(^|[^a-z0-9])(mini|pro|max|plus|ultra|fe|lite)([^a-z0-9]|$)';
        }

        // Component precision penalty: when searching "Display" / "Screen" / "OLED", demote Upper/Outer Glass, Bezels & Flex Cables
        $screenTokens = ['display', 'screen', 'lcd', 'oled', 'amoled', 'panel', 'digitizer'];
        $glassOrCableTokens = ['glass', 'upper', 'outer', 'oca', 'cable', 'lvds', 'frame', 'bezel', 'front'];
        $asksDisplay = !empty(array_intersect($coreTokens, $screenTokens));
        if ($asksDisplay && empty(array_intersect($coreTokens, $glassOrCableTokens))) {
            $titleLike = self::columnLikeSql('spare_part_listings.title', $isPg);
            $glassPats = ['%Upper Glass%', '%Outer Glass%', '%Touch Glass%', '%OCA%', '%Display Cable%', '%LVDS%', '%Front Part%', '%Bezel%'];
            $glassConds = array_fill(0, count($glassPats), $titleLike);
            $extraTerms[] = '(CASE WHEN (' . implode(' OR ', $glassConds) . ') THEN -10 ELSE 0 END)';
            foreach ($glassPats as $gp) {
                $extraBindings[] = $gp;
            }
        }

        // Category alignment boost & consumable demotion so repair adhesives/tapes never outrank real replacement parts
        $consumableTokens = [
            'tape', 'glue', 'adhesive', 'b7000', 't7000', 'spudger', 'tweezers', 'solder',
            'soldering', 'flux', 'paste', 'multimeter', 'screwdriver', 'pry', 'tool', 'tools',
            'mounting', 'bonding',
        ];
        if (empty(array_intersect($coreTokens, $consumableTokens))) {
            $titleLike = self::columnLikeSql('spare_part_listings.title', $isPg);
            $extraTerms[] = "(CASE WHEN spare_part_listings.part_category IN ('Tools, Consumables & Soldering', 'Repair Tools & Adhesives') OR {$titleLike} OR {$titleLike} OR {$titleLike} OR {$titleLike} OR {$titleLike} THEN -18 ELSE 0 END)";
            array_push($extraBindings, '%Mounting%', '%Adhesive%', '%Double-Sided%', '%B-7000%', '%T-7000%');

            $pcContext = !empty(array_intersect($coreTokens, ['pc', 'monitor', 'desktop', 'motherboard', 'board', 'lvds', 'edp', 'cable']));
            $categoryBoostRules = [
                [['display', 'screen', 'screens', 'lcd', 'oled', 'amoled', 'panel', 'digitizer', 'touchscreen'], "spare_part_listings.part_category = 'Display & Touch'", 12],
                [['display', 'screen', 'screens', 'lcd', 'oled', 'amoled', 'panel', 'monitor', 'lvds', 'edp'], "spare_part_listings.part_category = 'PC Display, Cable & Board'", $pcContext ? 12 : 8],
                [['motherboard', 'mainboard', 'logic', 'board', 'hdmi', 'vga'], "spare_part_listings.part_category = 'PC Display, Cable & Board'", 12],
                [['battery', 'batteries', 'cell', 'cmos'], "spare_part_listings.part_category IN ('Battery', 'Laptop Battery')", 12],
                [['charging', 'port', 'flex', 'ribbon', 'connector', 'jack', 'dock', 'typec', 'usb'], "spare_part_listings.part_category = 'Charging Port & Flex'", 12],
                [['keyboard', 'keyboards', 'keycap', 'keycaps'], "spare_part_listings.part_category = 'Laptop Keyboard'", 12],
                [['fan', 'fans', 'cooling', 'heatsink', 'blower'], "spare_part_listings.part_category = 'Laptop Cooling Fan'", 12],
                [['camera', 'cameras', 'lens', 'lenses', 'webcam'], "spare_part_listings.part_category = 'Camera & Lens'", 12],
                [['backshell', 'housing', 'backglass', 'frame', 'chassis', 'midframe'], "spare_part_listings.part_category = 'Backshell & Housing'", 12],
                [['sim', 'tray', 'earpiece', 'earspeaker', 'mic', 'microphone', 'vibrator', 'buzzer', 'button', 'antenna', 'sensor'], "spare_part_listings.part_category = 'SIM Tray & Small Parts'", 12],
                [['charger', 'chargers', 'adapter', 'adapters', 'psu', 'magsafe'], "spare_part_listings.part_category = 'Adapter & Charger'", 12],
                [['speaker', 'speakers', 'audio', 'woofer'], "spare_part_listings.part_category = 'Laptop Speaker'", 12],
                [['ssd', 'nvme', 'm2', 'sata', 'hdd', 'ram', 'memory', 'sodimm', 'ddr3', 'ddr4', 'ddr5', 'storage', 'caddy'], "spare_part_listings.part_category = 'SSD, RAM & Storage'", 12],
                [['casing', 'touchpad', 'trackpad', 'hinge', 'hinges', 'bezel', 'palmrest', 'topcase'], "spare_part_listings.part_category = 'Laptop Casing & Touchpad'", 12],
            ];

            foreach ($categoryBoostRules as [$keywords, $conditionSql, $boost]) {
                if (!empty(array_intersect($coreTokens, $keywords))) {
                    $extraTerms[] = "(CASE WHEN {$conditionSql} THEN {$boost} ELSE 0 END)";
                }
            }
        } else {
            $extraTerms[] = "(CASE WHEN spare_part_listings.part_category IN ('Tools, Consumables & Soldering', 'Repair Tools & Adhesives') THEN 12 ELSE 0 END)";
        }

        $fullExpr = $result['expr'];
        $fullBindings = $result['bindings'];
        if (!empty($extraTerms)) {
            $fullExpr = '(' . $fullExpr . ' + ' . implode(' + ', $extraTerms) . ')';
            $fullBindings = array_merge($fullBindings, $extraBindings);
        }

        return [
            'sql' => $fullExpr . ' DESC',
            'bindings' => $fullBindings,
            'all_matched' => $result['all_matched'],
        ];
    }

    /**
     * Convenience wrapper for Repair Shops directory, matching workshop fields plus offered services and stocked parts.
     *
     * @return array{sql: string, bindings: array<int, mixed>, all_matched: bool}|null
     */
    public static function applyShopSearch(Builder $query, ?string $rawSearch): ?array
    {
        $result = self::applySearch(
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
                })->orWhereHas('servicePackages', function (Builder $pkgQuery) use ($patterns, $isPg) {
                    $pkgQuery->where('service_packages.is_active', true)->where(function (Builder $sub) use ($patterns, $isPg) {
                        foreach (['service_packages.title', 'service_packages.device_category', 'service_packages.description'] as $col) {
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

        if ($result === null) {
            return null;
        }

        return [
            'sql' => $result['sql'],
            'bindings' => $result['bindings'],
            'all_matched' => $result['all_matched'],
        ];
    }

    public static function columnLikeSql(string $columnExpr, bool $isPg): string
    {
        if ($isPg) {
            return "{$columnExpr} ILIKE ?";
        }
        return "LOWER(COALESCE({$columnExpr}, '')) LIKE ?";
    }

    /**
     * Match a model-independent Fixed ServicePackage against a search query using token + synonym expansion.
     */
    public static function matchesServicePackageQuery(\App\Models\ServicePackage $pkg, ?string $rawQuery): bool
    {
        $rawTrimmed = trim((string) $rawQuery);
        if ($rawTrimmed === '') {
            return false;
        }

        // Guard against any un-migrated legacy hardware replacement package row
        if (preg_match('/\b(display|battery|motherboard|port\s+microsoldering|keyboard\s+replacement)\b/i', (string) ($pkg->title ?? '')) === 1) {
            return false;
        }

        $tokens = self::tokenize($rawTrimmed);
        if (empty($tokens)) {
            return false;
        }

        $strictHardwareTokens = [
            'display', 'displays', 'screen', 'screens', 'lcd', 'oled', 'amoled', 'digitizer', 'touchscreen',
            'battery', 'batteries', 'cell', 'motherboard', 'mainboard', 'ram',
            'charger', 'chargers', 'adapter', 'adapters', 'magsafe', 'camera', 'lens',
            'hinge', 'backshell', 'housing', 'trackpad', 'touchpad', 'sim', 'paste', 'flux', 'solder',
            'multimeter', 'screwdriver', 'spudger', 'tweezers',
        ];
        if (!empty(array_intersect($tokens, $strictHardwareTokens))) {
            return false;
        }

        if (!empty(array_intersect($tokens, ['ssd', 'nvme', 'hdd']))) {
            if (!empty(array_intersect($tokens, ['replacement', 'replace', 'replacing']))
                || empty(array_intersect($tokens, ['cloning', 'clone', 'migration', 'partition']))) {
                return false;
            }
        }

        // Route peripheral/fan/speaker queries to Fixed Packages only when cleaning/maintenance intent is explicit
        $cleaningOrMaintWords = [
            'clean', 'cleaning', 'cleanup', 'maintenance', 'tune', 'tuneup', 'dust',
            'thermal', 'repaste', 'repasting', 'sanitize', 'sanitization', 'mesh', 'lint', 'muffled', 'drift', 'calibration',
        ];
        if (!empty(array_intersect($tokens, ['keyboard', 'keyboards', 'keycap', 'fan', 'fans', 'mouse', 'mice', 'speaker', 'speakers', 'controller', 'mic', 'charging', 'port', 'ports']))) {
            if (!empty(array_intersect($tokens, ['replacement', 'replace', 'replacing'])) || empty(array_intersect($tokens, $cleaningOrMaintWords))) {
                return false;
            }
        }

        $fillerWords = [
            'package', 'packages', 'fixed', 'replacement', 'replace', 'issue', 'issues', 'problem', 'problems',
            'device', 'devices', 'phone', 'phones', 'smartphone', 'laptop', 'computer', 'pc', 'tablet',
            'printer', 'console', 'smartwatch', 'fix', 'repair', 'service', 'services', 'damage', 'damaged',
            'for', 'my', 'on', 'in',
        ];
        if (array_intersect($tokens, ['windows', 'os'])) {
            $fillerWords[] = '10';
            $fillerWords[] = '11';
        }

        $intentTokens = array_values(array_diff($tokens, $fillerWords));
        if (empty($intentTokens)) {
            return false;
        }

        $secondaryActionWords = ['clean', 'cleaning', 'cleanup', 'troubleshoot', 'troubleshooting', 'install', 'installation', 'setup', 'remove', 'removal'];
        $specificTokens = array_values(array_diff($intentTokens, $secondaryActionWords));
        $matchTokens = !empty($specificTokens) ? $specificTokens : $intentTokens;

        // Exclude device_category from haystack so generic device names never create false package matches
        $haystack = strtolower(trim(
            ($pkg->title ?? '') . ' ' .
            ($pkg->description ?? '') . ' ' .
            (is_array($pkg->inclusions) ? implode(' ', $pkg->inclusions) : (string) ($pkg->inclusions ?? ''))
        ));

        foreach ($matchTokens as $token) {
            $group = self::expandTokenGroup($token);
            $candidates = array_merge($group['exact_terms'], $group['synonym_terms']);
            $hit = false;
            foreach ($candidates as $cand) {
                $candLower = strtolower($cand);
                if ($candLower !== '' && preg_match('/(?<![a-z0-9])' . preg_quote($candLower, '/') . '(?![a-z0-9])/', $haystack) === 1) {
                    $hit = true;
                    break;
                }
            }
            if (!$hit) {
                return false;
            }
        }

        return true;
    }

    /**
     * Relevance score for matched Fixed ServicePackages so title matches rank ahead of incidental description mentions.
     */
    public static function scoreServicePackageQuery(\App\Models\ServicePackage $pkg, ?string $rawQuery): int
    {
        $titleLower = strtolower((string) ($pkg->title ?? ''));
        $tokens = self::tokenize((string) $rawQuery);
        $score = 0;

        foreach ($tokens as $tok) {
            $group = self::expandTokenGroup($tok);
            foreach ($group['exact_terms'] as $et) {
                $etLower = strtolower($et);
                if ($etLower !== '' && preg_match('/(?<![a-z0-9])' . preg_quote($etLower, '/') . '(?![a-z0-9])/', $titleLower) === 1) {
                    $score += 20;
                    break;
                }
            }
            foreach ($group['synonym_terms'] as $st) {
                $stLower = strtolower($st);
                if ($stLower !== '' && preg_match('/(?<![a-z0-9])' . preg_quote($stLower, '/') . '(?![a-z0-9])/', $titleLower) === 1) {
                    $score += 10;
                    break;
                }
            }
        }

        if ($pkg->is_popular) {
            $score += 3;
        }

        return $score;
    }

    public static function isHardwarePartQuery(string $rawQuery): bool
    {
        $tokens = self::tokenize($rawQuery);
        if (empty($tokens)) {
            return false;
        }

        $hardwareKeywords = [
            'display', 'displays', 'screen', 'screens', 'lcd', 'amoled', 'oled', 'digitizer', 'touchscreen',
            'touch', 'glass', 'panel', 'monitor', 'lvds', 'edp', 'vga', 'hdmi',
            'motherboard', 'mainboard', 'logic', 'board', 'boards',
            'battery', 'batteries', 'cell', 'cells', 'cmos',
            'keyboard', 'keyboards', 'keycap', 'keycaps', 'touchpad', 'trackpad', 'palmrest', 'hinge', 'hinges',
            'bezel', 'bezels', 'casing', 'topcase',
            'charger', 'chargers', 'adapter', 'adapters', 'charging', 'port', 'ports', 'flex', 'cable', 'cables',
            'ribbon', 'connector', 'jack', 'dc', 'typec', 'usb', 'magsafe', 'psu', 'dock',
            'speaker', 'speakers', 'fan', 'fans', 'cooling', 'heatsink', 'blower',
            'backshell', 'housing', 'backglass', 'frame', 'chassis',
            'camera', 'cameras', 'lens', 'lenses', 'webcam',
            'sim', 'tray', 'earpiece', 'earspeaker', 'mic', 'microphone', 'vibrator', 'buzzer', 'antenna', 'button',
            'ssd', 'nvme', 'm2', 'sata', 'hdd', 'ram', 'memory', 'sodimm', 'ddr3', 'ddr4', 'ddr5', 'caddy', 'storage',
            'spudger', 'adhesive', 'glue', 'tape', 'b7000', 't7000', 'tweezers', 'solder', 'soldering',
            'flux', 'multimeter', 'screwdriver', 'pry', 'paste', 'tool', 'tools',
        ];

        foreach ($tokens as $tok) {
            if (in_array($tok, $hardwareKeywords, true)) {
                return true;
            }
        }

        return false;
    }

    public static function isFixedPackageIntentQuery(string $rawQuery): bool
    {
        $words = preg_split('/[^a-z0-9]+/i', strtolower(trim($rawQuery)), -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($words) || empty($words)) {
            return false;
        }

        // Physical thermal compound purchases ("thermal paste") belong in Spare Parts when a strict consumable part exists
        // and no cleanup/overheating service intent words are present.
        $serviceWords = ['repasting', 'repaste', 'overheating', 'heating', 'dust', 'clean', 'cleaning', 'cleanup', 'deep', 'service', 'package', 'packages', 'application'];
        if (in_array('paste', $words, true) && empty(array_intersect($words, $serviceWords)) && self::hasStrictSparePartMatch($rawQuery)) {
            return false;
        }

        $packageKeywords = [
            'virus', 'malware', 'adware', 'spyware', 'antivirus', 'hacked', 'troubleshoot', 'troubleshooting',
            'root', 'rooting', 'bootloader', 'bootloop', 'unbrick', 'magisk', 'kernelsu', 'rom', 'firmware', 'flash', 'flashing', 'reflash', 'fastboot', 'dfu',
            'os', 'windows', 'linux', 'macos', 'ubuntu', 'bios', 'uefi', 'driver', 'drivers', 'format', 'reinstall',
            'cloning', 'clone', 'partition', 'office', 'productivity', 'transfer', 'drift', 'calibration', 'router', 'wifi', 'mesh', 'jam', 'roller', 'muffled', 'lint',
            'repasting', 'repaste', 'thermal', 'overheating', 'heating', 'dust', 'clean', 'cleaning', 'cleanup',
            'maintenance', 'tuneup', 'tune', 'preventive', 'sanitize', 'sanitization',
            'cartridge', 'cartridges', 'toner', 'ink', 'refill', 'printhead',
            'backup', 'recovery', 'encrypted', 'corrupted', 'migration',
            'ultrasonic', 'liquid', 'water', 'wet', 'spill', 'chemical', 'corrosion', 'package', 'packages',
        ];

        foreach ($words as $w) {
            if (in_array($w, $packageKeywords, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Exact shop/vendor name or distinctive multi-token shop entity (e.g. "Motherboard Masters",
     * "Toshiba Display Center", "Navana Battery"), disambiguated from device brand + part queries.
     */
    public static function isExactOrEntityShopQuery(string $rawQuery): bool
    {
        $rawTrimmed = trim($rawQuery);
        if ($rawTrimmed === '') {
            return false;
        }

        $isPg = DB::connection()->getDriverName() === 'pgsql';
        $exactLower = mb_strtolower($rawTrimmed, 'UTF-8');

        $exactExists = \App\Models\RepairShop::query()
            ->where('repair_shops.is_active', true)
            ->where('repair_shops.is_public', true)
            ->leftJoin('vendor_profiles', 'vendor_profiles.id', '=', 'repair_shops.vendor_profile_id')
            ->where(function (Builder $q) use ($exactLower) {
                $q->whereRaw('LOWER(repair_shops.name) = ?', [$exactLower])
                    ->orWhereRaw("LOWER(COALESCE(vendor_profiles.shop_name, '')) = ?", [$exactLower]);
            })
            ->limit(1)
            ->exists();

        if ($exactExists) {
            return true;
        }

        $tokens = self::tokenize($rawTrimmed);
        if (count($tokens) < 2) {
            return false;
        }

        $nonDistinctiveWords = [
            'apple', 'iphone', 'ipad', 'macbook', 'samsung', 'galaxy', 'google', 'pixel',
            'xiaomi', 'redmi', 'poco', 'oneplus', 'realme', 'oppo', 'vivo', 'iqoo', 'sony', 'xperia',
            'playstation', 'ps4', 'ps5', 'dell', 'inspiron', 'latitude', 'xps', 'hp', 'pavilion',
            'elitebook', 'probook', 'lenovo', 'thinkpad', 'ideapad', 'asus', 'zenbook', 'vivobook', 'rog',
            'acer', 'nitro', 'aspire', 'msi', 'toshiba', 'lg', 'huawei', 'honor', 'motorola', 'nokia',
            'blackberry', 'tecno', 'infinix', 'itel', 'walton', 'symphony', 'kingston', 'crucial', 'wd', 'sandisk',
            'pro', 'max', 'mini', 'plus', 'ultra', 'fe', 'lite', 'air', 'se', 'neo', 'pop', 'note', 'tab',
            'display', 'screen', 'lcd', 'oled', 'amoled', 'digitizer', 'touch', 'glass', 'panel', 'monitor',
            'battery', 'cell', 'keyboard', 'keycap', 'charger', 'adapter', 'charging', 'port', 'flex',
            'cable', 'ribbon', 'speaker', 'fan', 'cooling', 'heatsink', 'hinge', 'backshell', 'housing',
            'casing', 'touchpad', 'camera', 'lens', 'ssd', 'nvme', 'm2', 'sata', 'hdd', 'ram', 'memory',
            'ddr3', 'ddr4', 'ddr5', 'motherboard', 'mainboard', 'logic', 'board', 'sim', 'tray', 'paste',
            'adhesive', 'glue', 'tape', 'flux', 'solder', 'soldering', 'tool', 'tools',
            'replacement', 'replace', 'replacing', 'change', 'changing', 'broken', 'cracked', 'damage',
            'damaged', 'dead', 'issue', 'issues', 'problem', 'problems', 'repair', 'repairs', 'fix', 'device',
            'phone', 'mobile', 'laptop', 'computer', 'pc', 'original', 'oem',
        ];

        $hasDistinctiveShopToken = false;
        foreach ($tokens as $tok) {
            if (!in_array($tok, $nonDistinctiveWords, true) && !ctype_digit($tok)) {
                $hasDistinctiveShopToken = true;
                break;
            }
        }
        if (!$hasDistinctiveShopToken) {
            return false;
        }

        $shopEntityQuery = \App\Models\RepairShop::query()
            ->where('repair_shops.is_active', true)
            ->where('repair_shops.is_public', true)
            ->leftJoin('vendor_profiles', 'vendor_profiles.id', '=', 'repair_shops.vendor_profile_id');

        $shopEntityQuery->where(function (Builder $outer) use ($tokens, $isPg) {
            foreach ($tokens as $tok) {
                $pattern = '%' . strtolower($tok) . '%';
                $outer->where(function (Builder $inner) use ($pattern, $isPg) {
                    $inner->whereRaw(self::columnLikeSql('repair_shops.name', $isPg), [$pattern])
                        ->orWhereRaw(self::columnLikeSql('repair_shops.branch_name', $isPg), [$pattern])
                        ->orWhereRaw(self::columnLikeSql('vendor_profiles.shop_name', $isPg), [$pattern])
                        ->orWhereRaw(self::columnLikeSql('vendor_profiles.brand_name', $isPg), [$pattern]);
                });
            }
        });

        return $shopEntityQuery->limit(1)->exists();
    }

    /**
     * Strict product-only match (`AND` across all core tokens on title, compatible_model, brand, sku),
     * excluding shop name/city/area so shop or neighborhood searches never collide with spare parts.
     */
    public static function hasStrictSparePartMatch(string $rawQuery): bool
    {
        $rawTrimmed = trim($rawQuery);
        if ($rawTrimmed === '') {
            return false;
        }

        $tokens = self::tokenize($rawTrimmed);
        if (count($tokens) > 1) {
            $softIntentWords = [
                'replacement', 'replace', 'replacing', 'change', 'changing',
                'broken', 'cracked', 'damage', 'damaged', 'dead', 'issue', 'issues', 'problem', 'problems',
            ];
            $stripped = array_values(array_diff($tokens, $softIntentWords));
            if (!empty($stripped)) {
                $tokens = $stripped;
            }
        }
        if (empty($tokens)) {
            return false;
        }

        $isPg = DB::connection()->getDriverName() === 'pgsql';
        $primaryCols = [
            'spare_part_listings.title',
            'spare_part_listings.compatible_model',
            'spare_part_listings.brand',
            'spare_part_listings.sku',
        ];
        if (count($tokens) <= 1) {
            $primaryCols[] = 'spare_part_listings.part_category';
        }

        $groups = array_map(fn (string $t) => self::expandTokenGroup($t), $tokens);

        $probe = \App\Models\SparePartListing::query()
            ->where('spare_part_listings.is_active', true)
            ->join('repair_shops', 'repair_shops.id', '=', 'spare_part_listings.shop_id')
            ->where('repair_shops.is_active', true)
            ->where(function (Builder $outer) use ($groups, $primaryCols, $isPg) {
                foreach ($groups as $group) {
                    $outer->where(function (Builder $inner) use ($group, $primaryCols, $isPg) {
                        self::applyGroupToQuery($inner, $group, $primaryCols, [], $isPg, null);
                    });
                }
            });

        return $probe->limit(1)->exists();
    }

    /**
     * Check whether query directly matches a workshop or vendor brand/branch name (or metro hub/area).
     * Intentionally ignores free-text shop descriptions so brand queries like "google" fall through cleanly
     * unless an actual shop/vendor carries that name.
     */
    public static function matchesShopOrVendorName(?string $rawQuery): bool
    {
        $rawTrimmed = trim((string) $rawQuery);
        if ($rawTrimmed === '') {
            return false;
        }

        $tokens = self::tokenize($rawTrimmed);
        if (empty($tokens)) {
            $tokens = [strtolower($rawTrimmed)];
        }
        $isPg = DB::connection()->getDriverName() === 'pgsql';

        $shopQuery = \App\Models\RepairShop::query()
            ->where('repair_shops.is_active', true)
            ->where('repair_shops.is_public', true)
            ->leftJoin('vendor_profiles', 'vendor_profiles.id', '=', 'repair_shops.vendor_profile_id');

        $shopQuery->where(function (Builder $outer) use ($tokens, $isPg) {
            foreach ($tokens as $tok) {
                $pattern = '%' . strtolower($tok) . '%';
                $outer->where(function (Builder $inner) use ($pattern, $isPg) {
                    $inner->whereRaw(self::columnLikeSql('repair_shops.name', $isPg), [$pattern])
                        ->orWhereRaw(self::columnLikeSql('repair_shops.branch_name', $isPg), [$pattern])
                        ->orWhereRaw(self::columnLikeSql('repair_shops.area', $isPg), [$pattern])
                        ->orWhereRaw(self::columnLikeSql('repair_shops.city', $isPg), [$pattern])
                        ->orWhereRaw(self::columnLikeSql('vendor_profiles.shop_name', $isPg), [$pattern])
                        ->orWhereRaw(self::columnLikeSql('vendor_profiles.brand_name', $isPg), [$pattern]);
                });
            }
        });

        return $shopQuery->limit(1)->exists();
    }

    /**
     * Smart cross-section router used by both `/search` and `/api/search/suggest` from Home & header:
     *   0. Explicit vertical / fulfillment tab shortcuts ("courier pickup" -> /shops?category=COURIER_PICKUP, etc.)
     *   1. Exact / multi-token entity shops ("Motherboard Masters", "Toshiba Display Center", "Navana Battery")
     *   2. Model-independent Fixed Packages ("virus removal", "water damage", "windows 11 install", "data recovery")
     *   3. Hardware part queries across all 15 categories OR multi-token strict device/model queries
     *   4. Brand-only or shop/vendor/area queries check Shops & Vendors FIRST ("sony" -> Shops; "google" -> falls through)
     *   5. Spare Parts fallback ("google", "xiaomi", SKU)
     *   6. Broad Shop search fallback
     *
     * @return array{section: string, route: string, params: array<string, mixed>, clean_q: string}
     */
    public static function resolveGlobalSearchDestination(?string $rawQuery, array $signals = []): array
    {
        $rawTrimmed = trim((string) $rawQuery);
        if ($rawTrimmed === '') {
            return ['section' => 'spare-parts', 'route' => 'spare-parts.index', 'params' => [], 'clean_q' => ''];
        }

        $normalized = strtolower((string) preg_replace('/\s+/', ' ', $rawTrimmed));

        // 0. Explicit vertical / fulfillment tab shortcuts
        $tabShortcuts = [
            [
                'pattern' => '/\b(courier\s+pickup|home\s+pickup|doorstep\s+(?:pickup|repair)|pickup\s*(?:&|and)?\s*delivery|courier)\b/i',
                'section' => 'shops',
                'route' => 'shops.index',
                'params' => ['category' => 'COURIER_PICKUP'],
            ],
            [
                'pattern' => '/\b(in[\s\-]*shop\s+repair|in[\s\-]*shop|inshop|walk[\s\-]*in(?:\s+repair)?|walkin|in[\s\-]*store(?:\s+repair)?)\b/i',
                'section' => 'shops',
                'route' => 'shops.index',
                'params' => ['category' => 'IN_SHOP_REPAIR'],
            ],
            [
                'pattern' => '/\b(fixed\s+packages?|service\s+packages?|flat[\s\-]*rate\s+packages?|packages)\b/i',
                'section' => 'packages',
                'route' => 'packages.catalog',
                'params' => [],
            ],
            [
                'pattern' => '/\b(spare\s+parts?|parts\s+catalog)\b/i',
                'section' => 'spare-parts',
                'route' => 'spare-parts.index',
                'params' => [],
            ],
            [
                'pattern' => '/\b(repair\s+shops?|verified\s+shops?|workshops?|all\s+shops)\b/i',
                'section' => 'shops',
                'route' => 'shops.index',
                'params' => [],
            ],
        ];

        foreach ($tabShortcuts as $shortcut) {
            if (preg_match($shortcut['pattern'], $normalized) === 1) {
                $cleaned = (string) preg_replace($shortcut['pattern'], ' ', $rawTrimmed);
                $cleaned = trim((string) preg_replace('/^[\s,&-]+|[\s,&-]+$/', '', (string) preg_replace('/\s+/', ' ', $cleaned)));
                return [
                    'section' => $shortcut['section'],
                    'route' => $shortcut['route'],
                    'params' => $shortcut['params'],
                    'clean_q' => $cleaned,
                ];
            }
        }

        $tokens = self::tokenize($rawTrimmed);
        $coreTokens = $tokens;
        if (count($coreTokens) > 1) {
            $softIntentWords = [
                'replacement', 'replace', 'replacing', 'change', 'changing',
                'broken', 'cracked', 'damage', 'damaged', 'dead', 'issue', 'issues', 'problem', 'problems',
            ];
            $stripped = array_values(array_diff($coreTokens, $softIntentWords));
            if (!empty($stripped)) {
                $coreTokens = $stripped;
            }
        }

        $hasHardwareKeyword = self::isHardwarePartQuery($rawTrimmed);
        $hasPackageIntent = self::isFixedPackageIntentQuery($rawTrimmed);

        // 1. Exact or distinctive multi-token entity Shop / Vendor match
        if (self::isExactOrEntityShopQuery($rawTrimmed)) {
            return ['section' => 'shops', 'route' => 'shops.index', 'params' => [], 'clean_q' => $rawTrimmed];
        }

        // 2. Model-independent Fixed Packages
        $hasPackageMatch = array_key_exists('has_packages', $signals)
            ? (bool) $signals['has_packages']
            : \App\Models\ServicePackage::where('is_active', true)
                ->get()
                ->contains(fn (\App\Models\ServicePackage $pkg) => self::matchesServicePackageQuery($pkg, $rawTrimmed));

        if ($hasPackageMatch && ($hasPackageIntent || !$hasHardwareKeyword)) {
            return ['section' => 'packages', 'route' => 'packages.catalog', 'params' => [], 'clean_q' => $rawTrimmed];
        }

        // 3. Hardware part keyword or multi-token strict product/model match
        $hasStrictPart = array_key_exists('has_strict_parts', $signals)
            ? (bool) $signals['has_strict_parts']
            : self::hasStrictSparePartMatch($rawTrimmed);

        $partProbe = \App\Models\SparePartListing::query()
            ->where('spare_part_listings.is_active', true)
            ->join('repair_shops', 'repair_shops.id', '=', 'spare_part_listings.shop_id')
            ->where('repair_shops.is_active', true);

        $hasPartMatch = array_key_exists('has_parts', $signals)
            ? (bool) $signals['has_parts']
            : ($hasStrictPart || (self::applySparePartSearch($partProbe, $rawTrimmed) !== null && $partProbe->limit(1)->exists()));

        if (($hasHardwareKeyword && $hasPartMatch) || (count($coreTokens) >= 2 && $hasStrictPart)) {
            return ['section' => 'spare-parts', 'route' => 'spare-parts.index', 'params' => [], 'clean_q' => $rawTrimmed];
        }

        // 4. Single-brand, shop/vendor, or location discovery ("sony", "Banani") -> check Shops/Vendors FIRST
        $hasShopNameMatch = self::matchesShopOrVendorName($rawTrimmed);
        if (!$hasHardwareKeyword && $hasShopNameMatch) {
            return ['section' => 'shops', 'route' => 'shops.index', 'params' => [], 'clean_q' => $rawTrimmed];
        }

        // 5. Spare parts fallback (covers "google", "xiaomi", SKUs, parts/models without matching shop)
        if ($hasPartMatch) {
            return ['section' => 'spare-parts', 'route' => 'spare-parts.index', 'params' => [], 'clean_q' => $rawTrimmed];
        }

        // 6. Broad shop search fallback (specialties/descriptions)
        $hasBroadShopMatch = array_key_exists('has_shops', $signals)
            ? (bool) $signals['has_shops']
            : (function () use ($rawTrimmed) {
                $shopProbe = \App\Models\RepairShop::where('is_active', true)->where('is_public', true);
                return self::applyShopSearch($shopProbe, $rawTrimmed) !== null && $shopProbe->limit(1)->exists();
            })();

        if ($hasBroadShopMatch) {
            return ['section' => 'shops', 'route' => 'shops.index', 'params' => [], 'clean_q' => $rawTrimmed];
        }

        return ['section' => 'spare-parts', 'route' => 'spare-parts.index', 'params' => [], 'clean_q' => $rawTrimmed];
    }
}

<?php

namespace App\Services;

/**
 * Builds fixed-price service and package suggestions for a vendor based on the
 * specialty tags selected during onboarding.
 *
 * Suggestions are only prompts: nothing is published to a shop until the
 * vendor explicitly adds an item and sets its own price.
 */
class ServiceSuggestionCatalog
{
    /**
     * Onboarding specialty tag (lower-case) => suggested services.
     * Each entry: [name, typical market price used only as an input hint].
     */
    private const CATALOG = [
        'smartphone repair' => [
            ['Smartphone Screen Replacement', 2500],
            ['Smartphone Battery Replacement', 1200],
            ['Charging Port Replacement', 800],
            ['Back Glass Replacement', 1500],
            ['Smartphone Camera Module Replacement', 1800],
        ],
        'laptop repair' => [
            ['Laptop Screen Replacement', 6500],
            ['Laptop Battery Replacement', 3500],
            ['Laptop Keyboard Replacement', 2500],
            ['Thermal Paste & Cooling Service', 1200],
            ['SSD Upgrade & OS Installation', 1500],
        ],
        'desktop repair' => [
            ['Desktop Diagnostic & Tune-Up', 800],
            ['Power Supply (PSU) Replacement', 1500],
            ['RAM & Storage Upgrade Installation', 700],
            ['OS Installation & Driver Setup', 800],
        ],
        'tablet repair' => [
            ['Tablet Screen Replacement', 4000],
            ['Tablet Battery Replacement', 2500],
            ['Tablet Charging Port Replacement', 1200],
        ],
        'smartwatch repair' => [
            ['Smartwatch Screen Replacement', 2500],
            ['Smartwatch Battery Replacement', 1500],
            ['Strap & Sensor Servicing', 600],
        ],
        'console repair' => [
            ['Console HDMI Port Replacement', 2500],
            ['Controller Drift Repair', 900],
            ['Console Deep Cleaning & Thermal Service', 1500],
        ],
        'tv repair' => [
            ['TV Backlight Strip Replacement', 3500],
            ['TV Power Board Repair', 2500],
            ['TV Wall Mount Installation', 800],
        ],
        'audio equipment repair' => [
            ['Speaker Driver Replacement', 1500],
            ['Headphone Jack & Cable Repair', 600],
            ['Amplifier Board Servicing', 2000],
        ],
        'camera repair' => [
            ['Camera Sensor Cleaning', 1500],
            ['Lens Calibration & Servicing', 2500],
            ['Camera Shutter Replacement', 4000],
        ],
        'drone repair' => [
            ['Drone Propeller & Motor Replacement', 2000],
            ['Gimbal Calibration & Repair', 3000],
        ],
        'appliance repair' => [
            ['Appliance Diagnostic Visit', 500],
            ['Microwave Magnetron Replacement', 2500],
            ['Refrigerator Gas Recharge', 3000],
        ],
        'micro-soldering' => [
            ['Charging IC Replacement', 2500],
            ['Logic Board Micro-Soldering Repair', 3500],
            ['Backlight Circuit Repair', 2000],
        ],
        'data recovery' => [
            ['HDD / SSD Data Recovery (Basic)', 3000],
            ['Memory Card & USB Data Recovery', 1500],
            ['Phone Data Backup & Transfer', 800],
        ],
        'screen replacement' => [
            ['Phone Screen Replacement', 2500],
            ['Laptop Screen Replacement', 6500],
            ['Tablet Screen Replacement', 4000],
        ],
        'battery replacement' => [
            ['Phone Battery Replacement', 1200],
            ['Laptop Battery Replacement', 3500],
            ['Battery Health Diagnostic', 300],
        ],
        'water damage repair' => [
            ['Water Damage Ultrasonic Cleaning', 1800],
            ['Corrosion Treatment & Board Recovery', 2500],
        ],
    ];

    /** Keyword fallbacks for custom (non-preset) onboarding tags. */
    private const KEYWORD_MAP = [
        'phone' => 'smartphone repair',
        'iphone' => 'smartphone repair',
        'android' => 'smartphone repair',
        'mobile' => 'smartphone repair',
        'laptop' => 'laptop repair',
        'macbook' => 'laptop repair',
        'notebook' => 'laptop repair',
        'desktop' => 'desktop repair',
        'pc' => 'desktop repair',
        'computer' => 'desktop repair',
        'tablet' => 'tablet repair',
        'ipad' => 'tablet repair',
        'watch' => 'smartwatch repair',
        'console' => 'console repair',
        'playstation' => 'console repair',
        'xbox' => 'console repair',
        'tv' => 'tv repair',
        'television' => 'tv repair',
        'audio' => 'audio equipment repair',
        'speaker' => 'audio equipment repair',
        'camera' => 'camera repair',
        'drone' => 'drone repair',
        'appliance' => 'appliance repair',
        'solder' => 'micro-soldering',
        'logic board' => 'micro-soldering',
        'motherboard' => 'micro-soldering',
        'data' => 'data recovery',
        'screen' => 'screen replacement',
        'display' => 'screen replacement',
        'battery' => 'battery replacement',
        'water' => 'water damage repair',
        'liquid' => 'water damage repair',
    ];

    /** Generic suggestions shown when the vendor selected no recognisable tags. */
    private const GENERIC = [
        ['Device Diagnostic & Health Check', 300],
        ['Screen Replacement', 2500],
        ['Battery Replacement', 1200],
        ['Charging Port Replacement', 800],
    ];

    /**
     * @param  array<int, string>|null  $tags  Onboarding specialty tags.
     * @return array<int, array{name: string, hint: int, source: string}>
     */
    public static function forTags(?array $tags): array
    {
        $suggestions = [];
        $seen = [];

        $push = function (array $item, string $source) use (&$suggestions, &$seen) {
            $key = strtolower($item[0]);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $suggestions[] = ['name' => $item[0], 'hint' => (int) $item[1], 'source' => $source];
        };

        foreach ((array) $tags as $rawTag) {
            $tag = trim((string) $rawTag);
            if ($tag === '') {
                continue;
            }
            // Strip any legacy "Name|price" or "Name (৳price)" encoding.
            $tag = trim(preg_replace('/\s*(\|.*|\(\s*(?:৳|BDT|Tk\.?)?\s*[\d,.]+\s*\))\s*$/iu', '', $tag));
            $key = strtolower($tag);

            $catalogKey = isset(self::CATALOG[$key]) ? $key : null;
            if ($catalogKey === null) {
                foreach (self::KEYWORD_MAP as $keyword => $mapped) {
                    if (preg_match('/\b' . preg_quote($keyword, '/') . '/i', $key)) {
                        $catalogKey = $mapped;
                        break;
                    }
                }
            }

            if ($catalogKey !== null) {
                foreach (self::CATALOG[$catalogKey] as $item) {
                    $push($item, $tag);
                }
            }
        }

        if (empty($suggestions)) {
            foreach (self::GENERIC as $item) {
                $push($item, 'General');
            }
        }

        return $suggestions;
    }
}

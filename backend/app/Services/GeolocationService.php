<?php

namespace App\Services;

/**
 * ============================================================================
 * GEOLOCATION SERVICE (Supporting Feature R6)
 * ============================================================================
 * Provides distance calculation utilities using Haversine formula
 * Assigned Member: Shanid Sajjatuz Islam [Student ID: 23201465]
 * ============================================================================
 */
class GeolocationService
{
    /**
     * Calculate distance between two coordinates using Haversine formula
     * 
     * @param float $lat1 Latitude of first point
     * @param float $lng1 Longitude of first point
     * @param float $lat2 Latitude of second point
     * @param float $lng2 Longitude of second point
     * @return float Distance in kilometers
     */
    public static function calculateDistance($lat1, $lng1, $lat2, $lng2): float
    {
        $earthRadiusKm = 6371;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLng / 2) * sin($dLng / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }

    /**
     * Get approximate coordinates for major Dhaka areas & Bangladesh districts
     * 
     * @param string $area Area or city name
     * @return array|null ['lat' => float, 'lng' => float] or null
     */
    public static function getAreaCoordinates(string $area): ?array
    {
        $coordinates = [
            'Banani' => ['lat' => 23.7937, 'lng' => 90.4066],
            'Gulshan' => ['lat' => 23.7808, 'lng' => 90.4177],
            'Dhanmondi' => ['lat' => 23.7461, 'lng' => 90.3742],
            'Hatirpool' => ['lat' => 23.7422, 'lng' => 90.3918],
            'Mirpur' => ['lat' => 23.8223, 'lng' => 90.3654],
            'Uttara' => ['lat' => 23.8759, 'lng' => 90.3795],
            'Mohammadpur' => ['lat' => 23.7654, 'lng' => 90.3568],
            'Bashundhara' => ['lat' => 23.8223, 'lng' => 90.4254],
            'Farmgate' => ['lat' => 23.7582, 'lng' => 90.3885],
            'Tejgaon' => ['lat' => 23.7574, 'lng' => 90.3909],
            'Mohakhali' => ['lat' => 23.7808, 'lng' => 90.4067],
            'Badda' => ['lat' => 23.7805, 'lng' => 90.4267],
            'Rampura' => ['lat' => 23.7633, 'lng' => 90.4251],
            'New Market' => ['lat' => 23.7339, 'lng' => 90.3868],
            'Motijheel' => ['lat' => 23.7330, 'lng' => 90.4172],
            'Wari' => ['lat' => 23.7185, 'lng' => 90.4114],
            'Panthapath' => ['lat' => 23.7511, 'lng' => 90.3865],
            'Shahbag' => ['lat' => 23.7389, 'lng' => 90.3958],
            'Kalabagan' => ['lat' => 23.7494, 'lng' => 90.3831],
            'Khilgaon' => ['lat' => 23.7516, 'lng' => 90.4253],
            'Malibagh' => ['lat' => 23.7503, 'lng' => 90.4132],
            'Banasree' => ['lat' => 23.7629, 'lng' => 90.4326],
            'Agrabad' => ['lat' => 22.3264, 'lng' => 91.8155],
            'GEC Circle' => ['lat' => 22.3591, 'lng' => 91.8215],
            'Nasirabad' => ['lat' => 22.3636, 'lng' => 91.8214],
            'Panchlaish' => ['lat' => 22.3619, 'lng' => 91.8330],
            'Halishahar' => ['lat' => 22.3338, 'lng' => 91.7820],
            'Zindabazar' => ['lat' => 24.8949, 'lng' => 91.8687],
            'Amberkhana' => ['lat' => 24.9056, 'lng' => 91.8700],
            'Shaheb Bazar' => ['lat' => 24.3653, 'lng' => 88.6003],
            'Shib Bari' => ['lat' => 22.8206, 'lng' => 89.5535],
            'Sonadanga' => ['lat' => 22.8170, 'lng' => 89.5444],
            'Joydebpur' => ['lat' => 23.9999, 'lng' => 90.4203],
            'Tongi' => ['lat' => 23.8915, 'lng' => 90.4023],
            'Satmatha' => ['lat' => 24.8481, 'lng' => 89.3730],
            'Ganginarpar' => ['lat' => 24.7564, 'lng' => 90.4065],
            'Dhaka' => ['lat' => 23.8103, 'lng' => 90.4125],
            'Chattogram' => ['lat' => 22.3569, 'lng' => 91.7832],
            'Gazipur' => ['lat' => 23.9999, 'lng' => 90.4203],
            'Sylhet' => ['lat' => 24.8949, 'lng' => 91.8687],
            'Mymensingh' => ['lat' => 24.7564, 'lng' => 90.4065],
            'Narayanganj' => ['lat' => 23.6238, 'lng' => 90.5000],
            'Bogura' => ['lat' => 24.8465, 'lng' => 89.3773],
            'Rajshahi' => ['lat' => 24.3745, 'lng' => 88.6042],
            'Khulna' => ['lat' => 22.8456, 'lng' => 89.5403],
            'Cumilla' => ['lat' => 23.4607, 'lng' => 91.1809],
            'Rangpur' => ['lat' => 25.7439, 'lng' => 89.2752],
            'Barishal' => ['lat' => 22.7010, 'lng' => 90.3535],
            'Jashore' => ['lat' => 23.1664, 'lng' => 89.2081],
            "Cox's Bazar" => ['lat' => 21.4272, 'lng' => 92.0058],
            'Narail' => ['lat' => 23.1725, 'lng' => 89.5127],
            'Tangail' => ['lat' => 24.2513, 'lng' => 89.9167],
            'Moulvibazar' => ['lat' => 24.4829, 'lng' => 91.7774],
            'Sreemangal' => ['lat' => 24.3065, 'lng' => 91.7296],
        ];

        $normalized = trim($area);
        if (isset($coordinates[$normalized])) {
            return $coordinates[$normalized];
        }

        foreach ($coordinates as $key => $coords) {
            if (strcasecmp($key, $normalized) === 0) {
                return $coords;
            }
        }

        return null;
    }

    /**
     * Format distance for display
     * 
     * @param float $distanceKm Distance in kilometers
     * @return string Formatted distance string
     */
    public static function formatDistance(float $distanceKm): string
    {
        if ($distanceKm < 1) {
            return round($distanceKm * 1000) . 'm';
        }

        return round($distanceKm, 1) . 'km';
    }

    /**
     * Estimate travel time based on distance (Dhaka & inter-district traffic conditions)
     * 
     * @param float $distanceKm Distance in kilometers
     * @return int Estimated time in minutes (minimum 10 min)
     */
    public static function estimateTravelTime(float $distanceKm): int
    {
        // Assume average 15 km/h in city traffic (4 min per km), minimum 10 minutes dispatch prep
        $averageSpeedKmh = 15;
        $minutes = (int) round(($distanceKm / $averageSpeedKmh) * 60);
        return max(10, $minutes);
    }

    /**
     * Format ETA minutes into a readable label (e.g. "15 min", "1 hr 20 min", "10 hr")
     */
    public static function formatEta(int $minutes): string
    {
        $minutes = max(10, $minutes);
        if ($minutes < 60) {
            return $minutes . ' min';
        }
        $hours = intdiv($minutes, 60);
        $rem = $minutes % 60;
        if ($rem === 0 || $hours >= 4) {
            return round($minutes / 60) . ' hr';
        }
        return $hours . ' hr ' . $rem . ' min';
    }

    /**
     * Check if coordinates are within Dhaka city boundaries
     * 
     * @param float $lat Latitude
     * @param float $lng Longitude
     * @return bool True if within Dhaka, false otherwise
     */
    public static function isWithinDhaka(float $lat, float $lng): bool
    {
        // Approximate boundaries for Dhaka metropolitan area
        $dhakaBounds = [
            'lat_min' => 23.7,
            'lat_max' => 23.9,
            'lng_min' => 90.3,
            'lng_max' => 90.5,
        ];

        return $lat >= $dhakaBounds['lat_min'] && 
               $lat <= $dhakaBounds['lat_max'] &&
               $lng >= $dhakaBounds['lng_min'] && 
               $lng <= $dhakaBounds['lng_max'];
    }

    /**
     * Normalize user-supplied or Nominatim city/area strings into canonical
     * Bangladesh district/city names matching `repair_shops.city`.
     */
    public static function normalizeCityName(
        ?string $rawCity = null,
        ?string $rawArea = null,
        ?string $rawAddress = null,
        ?float $lat = null,
        ?float $lng = null
    ): string {
        $haystack = strtolower(trim(implode(' ', array_filter([$rawCity, $rawArea, $rawAddress]))));

        $cityAliases = [
            'Dhaka' => [
                'dhaka', 'dhaka north', 'dhaka south', 'dacca', 'panthapath',
                'banani', 'gulshan', 'dhanmondi', 'uttara', 'mirpur', 'mohammadpur',
                'bashundhara', 'farmgate', 'tejgaon', 'mohakhali', 'badda', 'rampura',
                'motijheel', 'new market', 'wari', 'hatirpool', 'shahbag', 'kalabagan',
                'khilgaon', 'malibagh', 'banasree',
            ],
            'Chattogram' => [
                'chattogram', 'chittagong', 'agrabad', 'gec circle', 'nasirabad',
                'panchlaish', 'halishahar', 'chawkbazar', 'patenga',
            ],
            'Sylhet' => ['sylhet', 'zindabazar', 'amberkhana', 'upashahar'],
            'Rajshahi' => ['rajshahi', 'shaheb bazar', 'boalia'],
            'Khulna' => ['khulna', 'shib bari', 'sonadanga'],
            'Gazipur' => ['gazipur', 'joydebpur', 'tongi'],
            'Narayanganj' => ['narayanganj'],
            'Mymensingh' => ['mymensingh', 'ganginarpar'],
            'Bogura' => ['bogura', 'bogra', 'satmatha'],
            'Cumilla' => ['cumilla', 'comilla'],
            'Rangpur' => ['rangpur'],
            'Barishal' => ['barishal', 'barisal'],
            'Jashore' => ['jashore', 'jessore'],
            "Cox's Bazar" => ['cox'],
        ];

        $rawCityLower = strtolower(trim((string) $rawCity));
        if ($rawCityLower !== '') {
            foreach ($cityAliases as $canonical => $aliases) {
                if (in_array($rawCityLower, $aliases, true)) {
                    return $canonical;
                }
            }
        }

        if ($haystack !== '') {
            foreach ($cityAliases as $canonical => $aliases) {
                foreach ($aliases as $alias) {
                    if (str_contains($haystack, $alias)) {
                        return $canonical;
                    }
                }
            }
        }

        if ($lat !== null && $lng !== null) {
            if ($lat >= 23.66 && $lat <= 23.92 && $lng >= 90.30 && $lng <= 90.52) {
                return 'Dhaka';
            }

            $districts = [
                'Dhaka', 'Chattogram', 'Sylhet', 'Rajshahi', 'Khulna',
                'Gazipur', 'Narayanganj', 'Mymensingh', 'Bogura', 'Cumilla',
                'Rangpur', 'Barishal', 'Jashore', "Cox's Bazar", 'Narail',
                'Tangail', 'Moulvibazar',
            ];
            $closestCity = null;
            $closestDist = INF;
            foreach ($districts as $district) {
                $coords = self::getAreaCoordinates($district);
                if ($coords) {
                    $dist = self::calculateDistance($lat, $lng, $coords['lat'], $coords['lng']);
                    if ($dist < $closestDist) {
                        $closestDist = $dist;
                        $closestCity = $district;
                    }
                }
            }
            if ($closestCity !== null && $closestDist <= 30.0) {
                return $closestCity;
            }
        }

        if (trim((string) $rawCity) !== '') {
            return ucwords(strtolower(trim((string) $rawCity)));
        }

        return 'Dhaka';
    }
}

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
     * Get approximate coordinates for major Dhaka areas
     * 
     * @param string $area Area name
     * @return array|null ['lat' => float, 'lng' => float] or null
     */
    public static function getAreaCoordinates(string $area): ?array
    {
        $coordinates = [
            'Banani' => ['lat' => 23.7937, 'lng' => 90.4066],
            'Gulshan' => ['lat' => 23.7808, 'lng' => 90.4177],
            'Dhanmondi' => ['lat' => 23.7461, 'lng' => 90.3742],
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
            'Wari' => ['lat' => 23.7185, 'lng' => 90.4114],
        ];

        return $coordinates[$area] ?? null;
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
     * Estimate travel time based on distance (Dhaka traffic conditions)
     * 
     * @param float $distanceKm Distance in kilometers
     * @return int Estimated time in minutes
     */
    public static function estimateTravelTime(float $distanceKm): int
    {
        // Assume average 15 km/h in Dhaka traffic
        $averageSpeedKmh = 15;
        return (int) round(($distanceKm / $averageSpeedKmh) * 60);
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
}

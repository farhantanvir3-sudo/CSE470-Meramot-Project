<?php

namespace App\Services;

/**
 * ============================================================================
 * NATIONWIDE BANGLADESH GEOLOCATION & OSM ADDRESS RESOLVER
 * ============================================================================
 * Author: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Resolves any (latitude, longitude) and optional OpenStreetMap address tags
 * anywhere in Bangladesh (all 64 Districts + major Metro Hubs) into coherent:
 *   - `city` (Canonical District / Metro name)
 *   - `area` (Neighborhood / Thana / Sadar Upazila)
 *   - `gps_address` (Address 1: GPS Approximate Location)
 *   - `address` (Address 2: Vendor-Provided Public Storefront Location)
 *   - `same_as_gps_location` (true iff Address 1 and Address 2 are identical)
 * ============================================================================
 */
class BangladeshLocationResolver
{
    /**
     * All 64 District Headquarters of Bangladesh with coordinates.
     */
    public const DISTRICTS = [
        // Dhaka Division (13)
        ['city' => 'Dhaka', 'lat' => 23.8103, 'lng' => 90.4125, 'division' => 'Dhaka'],
        ['city' => 'Gazipur', 'lat' => 23.9999, 'lng' => 90.4203, 'division' => 'Dhaka'],
        ['city' => 'Narayanganj', 'lat' => 23.6238, 'lng' => 90.5000, 'division' => 'Dhaka'],
        ['city' => 'Narsingdi', 'lat' => 23.9322, 'lng' => 90.7151, 'division' => 'Dhaka'],
        ['city' => 'Manikganj', 'lat' => 23.8644, 'lng' => 90.0047, 'division' => 'Dhaka'],
        ['city' => 'Munshiganj', 'lat' => 23.5422, 'lng' => 90.5305, 'division' => 'Dhaka'],
        ['city' => 'Tangail', 'lat' => 24.2513, 'lng' => 89.9167, 'division' => 'Dhaka'],
        ['city' => 'Kishoreganj', 'lat' => 24.4449, 'lng' => 90.7766, 'division' => 'Dhaka'],
        ['city' => 'Faridpur', 'lat' => 23.6071, 'lng' => 89.8429, 'division' => 'Dhaka'],
        ['city' => 'Gopalganj', 'lat' => 23.0051, 'lng' => 89.8266, 'division' => 'Dhaka'],
        ['city' => 'Madaripur', 'lat' => 23.1641, 'lng' => 90.1897, 'division' => 'Dhaka'],
        ['city' => 'Rajbari', 'lat' => 23.7574, 'lng' => 89.6445, 'division' => 'Dhaka'],
        ['city' => 'Shariatpur', 'lat' => 23.2423, 'lng' => 90.4348, 'division' => 'Dhaka'],

        // Chattogram Division (11)
        ['city' => 'Chattogram', 'lat' => 22.3569, 'lng' => 91.7832, 'division' => 'Chattogram'],
        ['city' => "Cox's Bazar", 'lat' => 21.4272, 'lng' => 92.0058, 'division' => 'Chattogram'],
        ['city' => 'Cumilla', 'lat' => 23.4607, 'lng' => 91.1809, 'division' => 'Chattogram'],
        ['city' => 'Brahmanbaria', 'lat' => 23.9571, 'lng' => 91.1119, 'division' => 'Chattogram'],
        ['city' => 'Chandpur', 'lat' => 23.2333, 'lng' => 90.6713, 'division' => 'Chattogram'],
        ['city' => 'Feni', 'lat' => 23.0159, 'lng' => 91.3976, 'division' => 'Chattogram'],
        ['city' => 'Lakshmipur', 'lat' => 22.9425, 'lng' => 90.8412, 'division' => 'Chattogram'],
        ['city' => 'Noakhali', 'lat' => 22.8696, 'lng' => 91.0994, 'division' => 'Chattogram'],
        ['city' => 'Khagrachhari', 'lat' => 23.1193, 'lng' => 91.9847, 'division' => 'Chattogram'],
        ['city' => 'Rangamati', 'lat' => 22.6533, 'lng' => 92.1753, 'division' => 'Chattogram'],
        ['city' => 'Bandarban', 'lat' => 22.1953, 'lng' => 92.2184, 'division' => 'Chattogram'],

        // Sylhet Division (4)
        ['city' => 'Sylhet', 'lat' => 24.8949, 'lng' => 91.8687, 'division' => 'Sylhet'],
        ['city' => 'Moulvibazar', 'lat' => 24.4829, 'lng' => 91.7774, 'division' => 'Sylhet'],
        ['city' => 'Habiganj', 'lat' => 24.3749, 'lng' => 91.4155, 'division' => 'Sylhet'],
        ['city' => 'Sunamganj', 'lat' => 25.0658, 'lng' => 91.3950, 'division' => 'Sylhet'],

        // Rajshahi Division (8)
        ['city' => 'Rajshahi', 'lat' => 24.3745, 'lng' => 88.6042, 'division' => 'Rajshahi'],
        ['city' => 'Bogura', 'lat' => 24.8465, 'lng' => 89.3773, 'division' => 'Rajshahi'],
        ['city' => 'Pabna', 'lat' => 24.0064, 'lng' => 89.2372, 'division' => 'Rajshahi'],
        ['city' => 'Sirajganj', 'lat' => 24.4534, 'lng' => 89.7007, 'division' => 'Rajshahi'],
        ['city' => 'Natore', 'lat' => 24.4206, 'lng' => 89.0003, 'division' => 'Rajshahi'],
        ['city' => 'Naogaon', 'lat' => 24.7936, 'lng' => 88.9318, 'division' => 'Rajshahi'],
        ['city' => 'Chapainawabganj', 'lat' => 24.5965, 'lng' => 88.2775, 'division' => 'Rajshahi'],
        ['city' => 'Joypurhat', 'lat' => 25.0968, 'lng' => 89.0227, 'division' => 'Rajshahi'],

        // Khulna Division (10)
        ['city' => 'Khulna', 'lat' => 22.8456, 'lng' => 89.5403, 'division' => 'Khulna'],
        ['city' => 'Jashore', 'lat' => 23.1664, 'lng' => 89.2081, 'division' => 'Khulna'],
        ['city' => 'Kushtia', 'lat' => 23.9013, 'lng' => 89.1204, 'division' => 'Khulna'],
        ['city' => 'Satkhira', 'lat' => 22.7185, 'lng' => 89.0705, 'division' => 'Khulna'],
        ['city' => 'Bagerhat', 'lat' => 22.6516, 'lng' => 89.7859, 'division' => 'Khulna'],
        ['city' => 'Jhenaidah', 'lat' => 23.5450, 'lng' => 89.1726, 'division' => 'Khulna'],
        ['city' => 'Magura', 'lat' => 23.4873, 'lng' => 89.4199, 'division' => 'Khulna'],
        ['city' => 'Narail', 'lat' => 23.1725, 'lng' => 89.5127, 'division' => 'Khulna'],
        ['city' => 'Chuadanga', 'lat' => 23.6402, 'lng' => 88.8418, 'division' => 'Khulna'],
        ['city' => 'Meherpur', 'lat' => 23.7622, 'lng' => 88.6318, 'division' => 'Khulna'],

        // Barishal Division (6)
        ['city' => 'Barishal', 'lat' => 22.7010, 'lng' => 90.3535, 'division' => 'Barishal'],
        ['city' => 'Patuakhali', 'lat' => 22.3596, 'lng' => 90.3299, 'division' => 'Barishal'],
        ['city' => 'Bhola', 'lat' => 22.6859, 'lng' => 90.6482, 'division' => 'Barishal'],
        ['city' => 'Pirojpur', 'lat' => 22.5841, 'lng' => 89.9720, 'division' => 'Barishal'],
        ['city' => 'Barguna', 'lat' => 22.1592, 'lng' => 90.1256, 'division' => 'Barishal'],
        ['city' => 'Jhalokati', 'lat' => 22.6406, 'lng' => 90.1987, 'division' => 'Barishal'],

        // Rangpur Division (8)
        ['city' => 'Rangpur', 'lat' => 25.7439, 'lng' => 89.2752, 'division' => 'Rangpur'],
        ['city' => 'Dinajpur', 'lat' => 25.6217, 'lng' => 88.6354, 'division' => 'Rangpur'],
        ['city' => 'Kurigram', 'lat' => 25.8054, 'lng' => 89.6362, 'division' => 'Rangpur'],
        ['city' => 'Gaibandha', 'lat' => 25.3288, 'lng' => 89.5281, 'division' => 'Rangpur'],
        ['city' => 'Nilphamari', 'lat' => 25.9318, 'lng' => 88.8560, 'division' => 'Rangpur'],
        ['city' => 'Lalmonirhat', 'lat' => 25.9923, 'lng' => 89.2847, 'division' => 'Rangpur'],
        ['city' => 'Thakurgaon', 'lat' => 26.0337, 'lng' => 88.4617, 'division' => 'Rangpur'],
        ['city' => 'Panchagarh', 'lat' => 26.3411, 'lng' => 88.5542, 'division' => 'Rangpur'],

        // Mymensingh Division (4)
        ['city' => 'Mymensingh', 'lat' => 24.7471, 'lng' => 90.4203, 'division' => 'Mymensingh'],
        ['city' => 'Jamalpur', 'lat' => 24.9375, 'lng' => 89.9378, 'division' => 'Mymensingh'],
        ['city' => 'Netrokona', 'lat' => 24.8710, 'lng' => 90.7277, 'division' => 'Mymensingh'],
        ['city' => 'Sherpur', 'lat' => 25.0205, 'lng' => 90.0153, 'division' => 'Mymensingh'],
    ];

    /**
     * Granular Neighborhood & Upazila Hubs across major Bangladesh cities.
     */
    public const NEIGHBORHOOD_HUBS = [
        // Dhaka Metro
        ['area' => 'Banani', 'city' => 'Dhaka', 'lat' => 23.7937, 'lng' => 90.4066, 'radius_km' => 2.2],
        ['area' => 'Gulshan', 'city' => 'Dhaka', 'lat' => 23.7925, 'lng' => 90.4158, 'radius_km' => 2.5],
        ['area' => 'Dhanmondi', 'city' => 'Dhaka', 'lat' => 23.7461, 'lng' => 90.3742, 'radius_km' => 2.2],
        ['area' => 'Hatirpool', 'city' => 'Dhaka', 'lat' => 23.7422, 'lng' => 90.3918, 'radius_km' => 1.4],
        ['area' => 'New Market', 'city' => 'Dhaka', 'lat' => 23.7333, 'lng' => 90.3836, 'radius_km' => 1.8],
        ['area' => 'Shahbagh', 'city' => 'Dhaka', 'lat' => 23.7389, 'lng' => 90.3958, 'radius_km' => 1.5],
        ['area' => 'Farmgate', 'city' => 'Dhaka', 'lat' => 23.7561, 'lng' => 90.3872, 'radius_km' => 1.8],
        ['area' => 'Tejgaon', 'city' => 'Dhaka', 'lat' => 23.7639, 'lng' => 90.3995, 'radius_km' => 2.2],
        ['area' => 'Mohakhali', 'city' => 'Dhaka', 'lat' => 23.7778, 'lng' => 90.4057, 'radius_km' => 2.0],
        ['area' => 'Mirpur', 'city' => 'Dhaka', 'lat' => 23.8071, 'lng' => 90.3686, 'radius_km' => 4.2],
        ['area' => 'Uttara', 'city' => 'Dhaka', 'lat' => 23.8759, 'lng' => 90.3795, 'radius_km' => 4.5],
        ['area' => 'Bashundhara', 'city' => 'Dhaka', 'lat' => 23.8155, 'lng' => 90.4285, 'radius_km' => 3.0],
        ['area' => 'Khilkhet', 'city' => 'Dhaka', 'lat' => 23.8312, 'lng' => 90.4197, 'radius_km' => 2.2],
        ['area' => 'Mohammadpur', 'city' => 'Dhaka', 'lat' => 23.7662, 'lng' => 90.3589, 'radius_km' => 2.6],
        ['area' => 'Agargaon', 'city' => 'Dhaka', 'lat' => 23.7776, 'lng' => 90.3752, 'radius_km' => 2.0],
        ['area' => 'Badda', 'city' => 'Dhaka', 'lat' => 23.7805, 'lng' => 90.4267, 'radius_km' => 2.6],
        ['area' => 'Rampura', 'city' => 'Dhaka', 'lat' => 23.7612, 'lng' => 90.4219, 'radius_km' => 2.2],
        ['area' => 'Malibagh', 'city' => 'Dhaka', 'lat' => 23.7495, 'lng' => 90.4128, 'radius_km' => 1.8],
        ['area' => 'Moghbazar', 'city' => 'Dhaka', 'lat' => 23.7494, 'lng' => 90.4032, 'radius_km' => 1.6],
        ['area' => 'Khilgaon', 'city' => 'Dhaka', 'lat' => 23.7517, 'lng' => 90.4288, 'radius_km' => 2.5],
        ['area' => 'Motijheel', 'city' => 'Dhaka', 'lat' => 23.7330, 'lng' => 90.4172, 'radius_km' => 1.8],
        ['area' => 'Paltan', 'city' => 'Dhaka', 'lat' => 23.7362, 'lng' => 90.4120, 'radius_km' => 1.4],
        ['area' => 'Wari', 'city' => 'Dhaka', 'lat' => 23.7176, 'lng' => 90.4178, 'radius_km' => 1.8],
        ['area' => 'Old Dhaka', 'city' => 'Dhaka', 'lat' => 23.7115, 'lng' => 90.4071, 'radius_km' => 2.5],
        ['area' => 'Jatrabari', 'city' => 'Dhaka', 'lat' => 23.7104, 'lng' => 90.4348, 'radius_km' => 3.2],
        ['area' => 'Demra', 'city' => 'Dhaka', 'lat' => 23.7231, 'lng' => 90.4932, 'radius_km' => 3.5],
        ['area' => 'Keraniganj', 'city' => 'Dhaka', 'lat' => 23.6942, 'lng' => 90.3540, 'radius_km' => 4.5],
        ['area' => 'Savar', 'city' => 'Dhaka', 'lat' => 23.8583, 'lng' => 90.2667, 'radius_km' => 6.0],
        ['area' => 'Ashulia', 'city' => 'Dhaka', 'lat' => 23.9010, 'lng' => 90.3220, 'radius_km' => 5.0],
        ['area' => 'Dhamrai', 'city' => 'Dhaka', 'lat' => 23.9134, 'lng' => 90.2156, 'radius_km' => 6.0],

        // Gazipur & Narayanganj Hubs
        ['area' => 'Tongi', 'city' => 'Gazipur', 'lat' => 23.8973, 'lng' => 90.4031, 'radius_km' => 4.0],
        ['area' => 'Joydebpur', 'city' => 'Gazipur', 'lat' => 23.9999, 'lng' => 90.4203, 'radius_km' => 5.0],
        ['area' => 'Konabari', 'city' => 'Gazipur', 'lat' => 24.0085, 'lng' => 90.3350, 'radius_km' => 4.5],
        ['area' => 'Chashara', 'city' => 'Narayanganj', 'lat' => 23.6238, 'lng' => 90.5000, 'radius_km' => 3.5],
        ['area' => 'Siddhirganj', 'city' => 'Narayanganj', 'lat' => 23.6793, 'lng' => 90.5146, 'radius_km' => 3.5],

        // Chattogram Metro
        ['area' => 'Agrabad', 'city' => 'Chattogram', 'lat' => 22.3264, 'lng' => 91.8155, 'radius_km' => 2.8],
        ['area' => 'GEC Circle', 'city' => 'Chattogram', 'lat' => 22.3591, 'lng' => 91.8215, 'radius_km' => 2.2],
        ['area' => 'Nasirabad', 'city' => 'Chattogram', 'lat' => 22.3675, 'lng' => 91.8235, 'radius_km' => 2.5],
        ['area' => 'Panchlaish', 'city' => 'Chattogram', 'lat' => 22.3631, 'lng' => 91.8351, 'radius_km' => 2.2],
        ['area' => 'Chawkbazar', 'city' => 'Chattogram', 'lat' => 22.3522, 'lng' => 91.8367, 'radius_km' => 2.0],
        ['area' => 'Kotwali', 'city' => 'Chattogram', 'lat' => 22.3375, 'lng' => 91.8364, 'radius_km' => 2.2],
        ['area' => 'Halishahar', 'city' => 'Chattogram', 'lat' => 22.3360, 'lng' => 91.7820, 'radius_km' => 3.5],
        ['area' => 'Bahaddarhat', 'city' => 'Chattogram', 'lat' => 22.3702, 'lng' => 91.8452, 'radius_km' => 2.8],
        ['area' => 'Patenga', 'city' => 'Chattogram', 'lat' => 22.2498, 'lng' => 91.7972, 'radius_km' => 4.5],

        // Sylhet Metro
        ['area' => 'Zindabazar', 'city' => 'Sylhet', 'lat' => 24.8949, 'lng' => 91.8687, 'radius_km' => 2.2],
        ['area' => 'Amberkhana', 'city' => 'Sylhet', 'lat' => 24.9058, 'lng' => 91.8698, 'radius_km' => 2.2],
        ['area' => 'Upashahar', 'city' => 'Sylhet', 'lat' => 24.8851, 'lng' => 91.8842, 'radius_km' => 2.5],

        // Rajshahi Metro
        ['area' => 'Shaheb Bazar', 'city' => 'Rajshahi', 'lat' => 24.3653, 'lng' => 88.6003, 'radius_km' => 2.8],
        ['area' => 'Motihar', 'city' => 'Rajshahi', 'lat' => 24.3696, 'lng' => 88.6375, 'radius_km' => 3.0],

        // Khulna Metro
        ['area' => 'Shib Bari', 'city' => 'Khulna', 'lat' => 22.8206, 'lng' => 89.5535, 'radius_km' => 3.0],
        ['area' => 'Sonadanga', 'city' => 'Khulna', 'lat' => 22.8168, 'lng' => 89.5403, 'radius_km' => 3.0],
        ['area' => 'Khalishpur', 'city' => 'Khulna', 'lat' => 22.8597, 'lng' => 89.5326, 'radius_km' => 3.2],

        // Other Major City Centers
        ['area' => 'Kandirpar', 'city' => 'Cumilla', 'lat' => 23.4607, 'lng' => 91.1809, 'radius_km' => 3.5],
        ['area' => 'Satmatha', 'city' => 'Bogura', 'lat' => 24.8481, 'lng' => 89.3730, 'radius_km' => 3.5],
        ['area' => 'Jahaj Company More', 'city' => 'Rangpur', 'lat' => 25.7466, 'lng' => 89.2517, 'radius_km' => 3.5],
        ['area' => 'Ganginarpar', 'city' => 'Mymensingh', 'lat' => 24.7564, 'lng' => 90.4065, 'radius_km' => 3.5],
        ['area' => 'Sadar Road', 'city' => 'Barishal', 'lat' => 22.7022, 'lng' => 90.3696, 'radius_km' => 3.5],
    ];

    /**
     * Legacy / variant spelling normalization for Bangladesh districts.
     */
    private const CITY_ALIASES = [
        'chittagong' => 'Chattogram',
        'চট্টগ্রাম' => 'Chattogram',
        'comilla' => 'Cumilla',
        'কুমিল্লা' => 'Cumilla',
        'bogra' => 'Bogura',
        'বগুড়া' => 'Bogura',
        'jessore' => 'Jashore',
        'যশোর' => 'Jashore',
        'barisal' => 'Barishal',
        'বরিশাল' => 'Barishal',
        'dacca' => 'Dhaka',
        'ঢাকা' => 'Dhaka',
        'সিলেট' => 'Sylhet',
        'রাজশাহী' => 'Rajshahi',
        'খুলনা' => 'Khulna',
        'রংপুর' => 'Rangpur',
        'ময়মনসিংহ' => 'Mymensingh',
        'গাজীপুর' => 'Gazipur',
        'নারায়ণগঞ্জ' => 'Narayanganj',
        'coxs bazar' => "Cox's Bazar",
        "cox's bazar" => "Cox's Bazar",
        'chapai nawabganj' => 'Chapainawabganj',
        'nawabganj' => 'Chapainawabganj',
    ];

    /**
     * Resolve coordinates and optional OSM tags into coherent City, Area, and Dual Addresses.
     */
    public static function resolve(float $lat, float $lng, array $tags = []): array
    {
        // 1. Find nearest Neighborhood Hub within its radius
        $matchedHub = null;
        $bestHubDistance = PHP_FLOAT_MAX;
        foreach (self::NEIGHBORHOOD_HUBS as $hub) {
            $dist = GeolocationService::calculateDistance($lat, $lng, $hub['lat'], $hub['lng']);
            if ($dist <= $hub['radius_km'] && $dist < $bestHubDistance) {
                $bestHubDistance = $dist;
                $matchedHub = $hub;
            }
        }

        // 2. Find nearest Bangladesh District HQ (always matches across all 64 districts)
        $nearestDistrict = self::DISTRICTS[0];
        $bestDistrictDistance = PHP_FLOAT_MAX;
        foreach (self::DISTRICTS as $district) {
            $dist = GeolocationService::calculateDistance($lat, $lng, $district['lat'], $district['lng']);
            if ($dist < $bestDistrictDistance) {
                $bestDistrictDistance = $dist;
                $nearestDistrict = $district;
            }
        }

        // 3. Determine coherent `city` (strictly canonical Bangladesh 64 Districts)
        $rawCity = trim((string) ($tags['addr:city'] ?? $tags['addr:district'] ?? ''));
        $knownDistrictCity = self::matchCanonicalDistrict($rawCity);

        if ($matchedHub) {
            $city = $matchedHub['city'];
        } elseif ($knownDistrictCity !== null) {
            // Sanity-check: only trust OSM `addr:city` if within 65 km of that district HQ
            $city = $nearestDistrict['city'];
            foreach (self::DISTRICTS as $d) {
                if ($d['city'] === $knownDistrictCity) {
                    if (GeolocationService::calculateDistance($lat, $lng, $d['lat'], $d['lng']) <= 65.0) {
                        $city = $knownDistrictCity;
                    }
                    break;
                }
            }
        } else {
            $city = $nearestDistrict['city'];
        }

        // 4. Determine coherent `area` (preserve Upazila/Town if `addr:city` held a non-district town like Kalia)
        $subDistrictFromCityTag = ($knownDistrictCity === null && $rawCity !== '') ? $rawCity : '';
        $rawSuburb = trim((string) (
            $tags['addr:suburb']
            ?? $tags['addr:quarter']
            ?? $tags['addr:neighbourhood']
            ?? $tags['addr:upazila']
            ?? $subDistrictFromCityTag
        ));
        $rawStreet = trim((string) ($tags['addr:street'] ?? ''));

        if ($matchedHub) {
            $area = $matchedHub['area'];
        } elseif ($rawSuburb !== '' && !preg_match('/\b(hwy|highway)\b/i', $rawSuburb)) {
            $area = self::cleanLabel($rawSuburb);
        } elseif ($rawStreet !== '' && mb_strlen($rawStreet) <= 32 && !preg_match('/\b(hwy|highway)\b/i', $rawStreet)) {
            $area = self::cleanLabel($rawStreet);
        } else {
            $area = "{$city} Sadar";
        }

        // 5. Build Address 1: GPS Approximate Location (`gps_address`)
        $gpsParts = [];
        if ($rawStreet !== '' && mb_strtolower($rawStreet) !== mb_strtolower($area)) {
            $gpsParts[] = $rawStreet;
        }
        $gpsParts[] = $area;
        if (!str_contains(mb_strtolower($area), mb_strtolower($city))) {
            $gpsParts[] = $city;
        }
        $gpsAddress = implode(', ', array_unique(array_filter($gpsParts)));

        // 6. Build Address 2: Vendor-Provided Public Storefront Location (`address`)
        $detailTokens = [];
        if (!empty($tags['addr:unit']) || !empty($tags['addr:flats'])) {
            $unit = trim((string) ($tags['addr:unit'] ?? $tags['addr:flats']));
            $detailTokens[] = "Shop/Unit {$unit}";
        }
        if (!empty($tags['level']) && empty($tags['addr:floor'])) {
            $detailTokens[] = 'Level ' . trim((string) $tags['level']);
        }
        if (!empty($tags['addr:floor'])) {
            $detailTokens[] = 'Floor ' . trim((string) $tags['addr:floor']);
        }
        if (!empty($tags['addr:place']) || !empty($tags['addr:mall'])) {
            $detailTokens[] = trim((string) ($tags['addr:mall'] ?? $tags['addr:place']));
        }
        if (!empty($tags['addr:housenumber'])) {
            $detailTokens[] = trim((string) $tags['addr:housenumber']);
        }
        if (!empty($tags['addr:full'])) {
            $detailTokens[] = trim((string) $tags['addr:full']);
        }

        if (!empty($detailTokens)) {
            $storefrontParts = $detailTokens;
            if ($rawStreet !== '' && !str_contains(mb_strtolower(implode(' ', $storefrontParts)), mb_strtolower($rawStreet))) {
                $storefrontParts[] = $rawStreet;
            }
            if (!str_contains(mb_strtolower(implode(' ', $storefrontParts)), mb_strtolower($area))) {
                $storefrontParts[] = $area;
            }
            if (!str_contains(mb_strtolower(implode(' ', $storefrontParts)), mb_strtolower($city))) {
                $storefrontParts[] = $city;
            }
            $vendorAddress = implode(', ', array_unique(array_filter($storefrontParts)));
            $sameAsGps = ($vendorAddress === $gpsAddress);
        } else {
            // Vendor did not specify separate floor/building details -> synced with GPS location
            $vendorAddress = $gpsAddress;
            $sameAsGps = true;
        }

        return [
            'city' => $city,
            'area' => $area,
            'gps_address' => $gpsAddress,
            'address' => $vendorAddress,
            'same_as_gps_location' => $sameAsGps,
        ];
    }

    /**
     * Match raw text strictly against one of Bangladesh's 64 canonical districts (or null).
     */
    public static function matchCanonicalDistrict(string $rawCity): ?string
    {
        if ($rawCity === '') {
            return null;
        }

        $cleaned = trim((string) preg_replace('/\s+(District|Zila|Sadar|City Corporation|City)$/iu', '', $rawCity));
        $lower = mb_strtolower($cleaned);

        if (isset(self::CITY_ALIASES[$lower])) {
            return self::CITY_ALIASES[$lower];
        }

        foreach (self::DISTRICTS as $d) {
            if (mb_strtolower($d['city']) === $lower) {
                return $d['city'];
            }
        }

        return null;
    }

    /**
     * Normalize legacy/Bengali Bangladesh district names into canonical English names.
     */
    public static function normalizeCity(string $rawCity): string
    {
        if ($rawCity === '') {
            return '';
        }

        return self::matchCanonicalDistrict($rawCity) ?? self::cleanLabel($rawCity);
    }

    private static function cleanLabel(string $value): string
    {
        $trimmed = trim(preg_replace('/\s+/', ' ', $value));
        if (preg_match('/^[a-z0-9\s\-\.\']+$/i', $trimmed)) {
            return ucwords(strtolower($trimmed));
        }
        return $trimmed;
    }
}

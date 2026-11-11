<?php

namespace Database\Seeders;

use App\Models\RepairShop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * ============================================================================
 * MERAMOT NATIONWIDE SHOP & MULTI-BRANCH VENDOR SEEDER (Features R6 & R11)
 * ============================================================================
 * Authors:
 *   - Shanid Sajjatuz Islam [Student ID: 23201465] (Feature R6 Shop Discovery)
 *   - Farhan Tanvir [Student ID: 22299179] (Multi-Branch Vendors, Dual Shop
 *     Addresses & Nationwide Bangladesh OpenStreetMap `shop=electronics` Sync)
 *
 * Coherence Invariants Enforced Across All Seeded Records:
 *   1. Dual Shop Addresses:
 *      - `gps_address` + (`latitude`, `longitude`) = GPS Approximate Location.
 *      - `address` = Vendor-Provided Storefront Location.
 *      - `same_as_gps_location === ($address === $gps_address)` for 100% of rows.
 *   2. Multi-Branch Vendor Integrity:
 *      - `VendorProfile.is_branched === true` iff its shops have `is_branch === true`
 *        and a non-empty unique `branch_name`.
 *      - Demo vendor `sakib.tech@gmail.com` exclusively owns `ProTech Solutions`
 *        across 3 branches (Banani DOHS Flagship, Uttara Sector 7, Agrabad Chattogram).
 *      - Demo vendor `dhaka.devices@meramot.demo` exclusively owns standalone
 *        `Dhaka Device Doctors`.
 *   3. Nationwide Bangladesh Coverage:
 *      - Seeds all 34 curated Meramot hubs + all 1,260 named `shop=electronics`
 *        stores across 59 Bangladesh districts using fast PostgreSQL batch upserts.
 * ============================================================================
 */
class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $reviewedAt = $now->copy()->subDays(45);

        $meramotAdmin = User::where('email', 'admin@meramot.demo')->first()
            ?? User::where('role', 'admin')->orderBy('id')->first();

        // Dedicated demo vendor accounts
        $sakibVendorUser = User::where('email', 'sakib.tech@gmail.com')->first()
            ?? User::where('role', 'vendor')->first()
            ?? User::first();

        $dhakaDoctorsUser = User::where('email', 'dhaka.devices@meramot.demo')->first()
            ?? $sakibVendorUser;

        // Dedicated directory merchant carrier account so demo users keep 1:1 VendorProfile ownership
        $directoryVendorUser = User::updateOrCreate(
            ['email' => 'directory.merchants@meramot.local'],
            [
                'name' => 'Meramot Verified Directory Merchants',
                'password' => Hash::make('Directory@Meramot2026'),
                'phone' => '+8801700999999',
                'role' => 'vendor',
                'is_active' => true,
            ]
        );

        // ---------------------------------------------------------------------
        // PART 1: 34 Curated Meramot Flagship & Metro Repair Hubs
        // (Includes 3 Multi-Branch locations for ProTech Solutions)
        // ---------------------------------------------------------------------
        $curatedShops = [
            // ProTech Solutions — Branch 1 (Dhaka Banani Flagship)
            [
                'name' => 'ProTech Solutions',
                'slug' => 'protech-solutions',
                'is_branch' => true,
                'branch_name' => 'Banani DOHS Flagship',
                'vendor_group_key' => 'curated_brand:protech-solutions',
                'owner_user_id' => $sakibVendorUser->id,
                'description' => 'OEM parts only. Certified Apple technicians. 12-month warranty included.',
                'address' => 'Level 3, House 42, Banani DOHS, Dhaka',
                'gps_address' => 'Road 9, Banani DOHS, Banani, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'Banani',
                'lat' => 23.790428,
                'lng' => 90.398003,
                'rating' => 4.9,
                'review_count' => 1216,
                'price_level' => 2,
                'has_voucher' => true,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => true,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['MacBook Air M2 repair', 'Laptop Battery Replacement', 'Logic Board Micro-Soldering', 'Smartphone OLED Display Repair'],
                'eta_minutes' => 140,
                'result_tag' => 'Best Value',
                'offer_summary' => 'Inspection ৳300',
                'inspection_fee' => 300,
                'base_labor_fee' => 500,
                'phone' => '+8801711000001',
                'email' => 'protech@meramot.local',
            ],
            // ProTech Solutions — Branch 2 (Dhaka Uttara Expansion Branch)
            [
                'name' => 'ProTech Solutions',
                'slug' => 'protech-solutions-uttara',
                'is_branch' => true,
                'branch_name' => 'Uttara Sector 7',
                'vendor_group_key' => 'curated_brand:protech-solutions',
                'owner_user_id' => $sakibVendorUser->id,
                'description' => 'Northern Dhaka certified Apple & ultrabook bench with express courier pickup.',
                'address' => 'Suite 502, North Tower, Rabindra Sarani, Sector 7, Uttara, Dhaka',
                'gps_address' => 'Sector 7, Uttara, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'Uttara',
                'lat' => 23.873810,
                'lng' => 90.398410,
                'rating' => 4.8,
                'review_count' => 318,
                'price_level' => 2,
                'has_voucher' => true,
                'free_delivery' => true,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['MacBook Air M2 repair', 'Laptop Battery Replacement', 'Ultrabook Screen Replacement', 'Smartphone Display & Port Repair'],
                'eta_minutes' => 75,
                'result_tag' => 'North Hub',
                'offer_summary' => 'Inspection ৳300',
                'inspection_fee' => 300,
                'base_labor_fee' => 500,
                'phone' => '+8801711000101',
                'email' => 'uttara.protech@meramot.local',
            ],
            // ProTech Solutions — Branch 3 (Chattogram Agrabad Regional Branch, GPS = Public Address)
            [
                'name' => 'ProTech Solutions',
                'slug' => 'protech-solutions-agrabad',
                'is_branch' => true,
                'branch_name' => 'Agrabad, Chattogram',
                'vendor_group_key' => 'curated_brand:protech-solutions',
                'owner_user_id' => $sakibVendorUser->id,
                'description' => 'Port city flagship workshop serving Chattogram corporate & consumer Apple/PC devices.',
                'address' => 'Sheikh Mujib Road, Agrabad, Chattogram',
                'gps_address' => 'Sheikh Mujib Road, Agrabad, Chattogram',
                'same_as_gps_location' => true,
                'city' => 'Chattogram',
                'area' => 'Agrabad',
                'lat' => 22.326410,
                'lng' => 91.815520,
                'rating' => 4.9,
                'review_count' => 204,
                'price_level' => 2,
                'has_voucher' => true,
                'free_delivery' => true,
                'has_deals' => true,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR', 'SPARE_PARTS'],
                'specialties' => ['MacBook Air M2 repair', 'Logic Board Micro-Soldering', 'Smartphone OLED Display Swap', 'Corporate Laptop Diagnostics'],
                'eta_minutes' => 90,
                'result_tag' => 'Chattogram Hub',
                'offer_summary' => 'Inspection ৳300',
                'inspection_fee' => 300,
                'base_labor_fee' => 500,
                'phone' => '+8801711000102',
                'email' => 'ctg.protech@meramot.local',
            ],
            [
                'name' => 'The Gadget Den',
                'slug' => 'the-gadget-den',
                'description' => 'Express service available. Right around the corner from Main St. Mall.',
                'address' => 'Shop 204, Navana Tower, Gulshan 1 Circle, Dhaka',
                'gps_address' => 'Gulshan 1, Gulshan, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'Gulshan',
                'lat' => 23.791281,
                'lng' => 90.407776,
                'rating' => 4.7,
                'review_count' => 84,
                'price_level' => 3,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => true,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Smartphone Screen Replacement', 'Laptop Keyboard Repair', 'Tablet Digitizer Replacement', 'Smartwatch Glass & Sensor Repair'],
                'eta_minutes' => 60,
                'result_tag' => 'Lowest Price',
                'offer_summary' => 'Inspection ৳500',
                'inspection_fee' => 500,
                'base_labor_fee' => 800,
                'phone' => '+8801711000002',
                'email' => 'gadgetden@meramot.local',
            ],
            [
                'name' => 'Budget Fixer',
                'slug' => 'budget-fixer',
                'description' => 'Affordable high-quality generic replacement screens. 30-day warranty.',
                'address' => 'Dhanmondi 27, Dhaka',
                'gps_address' => 'Dhanmondi 27, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'lat' => 23.744013,
                'lng' => 90.379081,
                'rating' => 4.2,
                'review_count' => 210,
                'price_level' => 1,
                'has_voucher' => true,
                'free_delivery' => false,
                'has_deals' => true,
                'is_featured' => true,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Smartphone Display Replacement', 'Laptop Screen Repair', 'Gaming Console HDMI & Fan Servicing', 'Printer Roller & Feed Repair'],
                'eta_minutes' => 1440,
                'result_tag' => 'Most Affordable',
                'offer_summary' => 'Inspection ৳100',
                'inspection_fee' => 100,
                'base_labor_fee' => 300,
                'phone' => '+8801711000003',
                'email' => 'budgetfixer@meramot.local',
            ],
            [
                'name' => 'MacMaster Repairs',
                'slug' => 'macmaster-repairs',
                'description' => 'OEM parts only. Certified Apple replacement tools in stock.',
                'address' => 'Level 2, Tropical Plaza, Mohakhali Wireless, Dhaka',
                'gps_address' => 'Mohakhali Wireless, Mohakhali, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'Mohakhali',
                'lat' => 23.776075,
                'lng' => 90.403162,
                'rating' => 4.9,
                'review_count' => 94,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => true,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['MacBook Air M2 repair', 'certified apple support', 'logic board diagnostics'],
                'eta_minutes' => 60,
                'result_tag' => 'Highest Rating',
                'offer_summary' => 'Inspection ৳250',
                'inspection_fee' => 250,
                'base_labor_fee' => 600,
                'phone' => '+8801711000004',
                'email' => 'macmaster@meramot.local',
            ],
            [
                'name' => 'The iDoc Garage',
                'slug' => 'the-idoc-garage',
                'description' => 'Silicon Valley fixers. Bravest service, and rapid local courier pickup.',
                'address' => 'Uttara Sector 11, Dhaka',
                'gps_address' => 'Uttara Sector 11, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Uttara',
                'lat' => 23.876491,
                'lng' => 90.374663,
                'rating' => 4.7,
                'review_count' => 152,
                'price_level' => 3,
                'has_voucher' => false,
                'free_delivery' => true,
                'has_deals' => false,
                'is_featured' => true,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['MacBook Air M2 repair', 'battery replacement', 'water damage'],
                'eta_minutes' => 180,
                'result_tag' => 'Fastest Service',
                'offer_summary' => 'Inspection ৳500',
                'inspection_fee' => 500,
                'base_labor_fee' => 700,
                'phone' => '+8801711000005',
                'email' => 'idoc@meramot.local',
            ],
            [
                'name' => 'ByteSize Repairs',
                'slug' => 'bytesize-repairs',
                'description' => 'Express screen replacement with ultra quick turnaround time.',
                'address' => 'House 114, Avenue 3, Mirpur DOHS, Dhaka',
                'gps_address' => 'Mirpur DOHS, Mirpur, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'Mirpur',
                'lat' => 23.799254,
                'lng' => 90.373466,
                'rating' => 4.9,
                'review_count' => 84,
                'price_level' => 3,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => true,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Express Phone Screen Replacement', 'Laptop Display Panel Swap', 'Keyboard Ribbon & Switch Repair'],
                'eta_minutes' => 1440,
                'result_tag' => 'Generic Screen Option',
                'offer_summary' => 'Inspection ৳400',
                'inspection_fee' => 400,
                'base_labor_fee' => 700,
                'phone' => '+8801711000006',
                'email' => 'bytesize@meramot.local',
            ],
            [
                'name' => 'AppleCore Solutions',
                'slug' => 'applecore-solutions',
                'description' => 'Expert Apple device repair. Same-day service available.',
                'address' => 'Middle Badda, Dhaka',
                'gps_address' => 'Middle Badda, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Badda',
                'lat' => 23.778462,
                'lng' => 90.430006,
                'rating' => 4.7,
                'review_count' => 134,
                'price_level' => 3,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['MacBook Air M2 repair', 'logic board repair', 'water damage'],
                'eta_minutes' => 60,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳450',
                'inspection_fee' => 450,
                'base_labor_fee' => 800,
                'phone' => '+8801711000007',
                'email' => 'applecore@meramot.local',
            ],
            [
                'name' => 'Main Street Tech',
                'slug' => 'main-street-tech',
                'description' => 'Reliable everyday repairs at fair prices. Walk-ins welcome.',
                'address' => 'Tejgaon Commercial Area, Dhaka',
                'gps_address' => 'Tejgaon Commercial Area, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Tejgaon',
                'lat' => 23.763010,
                'lng' => 90.397188,
                'rating' => 4.9,
                'review_count' => 171,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Laptop Trackpad Replacement', 'Battery Cell Replacement', 'USB-C & Charging Port Repair'],
                'eta_minutes' => 120,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳200',
                'inspection_fee' => 200,
                'base_labor_fee' => 400,
                'phone' => '+8801711000008',
                'email' => 'mainstreet@meramot.local',
            ],
            [
                'name' => 'Precision Fix',
                'slug' => 'precision-fix',
                'description' => 'Affordable high-quality generic replacement battery. 90-day warranty.',
                'address' => 'DIT Road, Rampura, Dhaka',
                'gps_address' => 'DIT Road, Rampura, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Rampura',
                'lat' => 23.759799,
                'lng' => 90.416242,
                'rating' => 4.3,
                'review_count' => 210,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => true,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['Laptop Battery Replacement', 'Smartphone Battery Calibration', 'Charging Circuit Diagnostics'],
                'eta_minutes' => 60,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳200',
                'inspection_fee' => 200,
                'base_labor_fee' => 450,
                'phone' => '+8801711000009',
                'email' => 'precisionfix@meramot.local',
            ],
            [
                'name' => 'Micro-Maestro',
                'slug' => 'micro-maestro',
                'description' => 'Specialist in micro soldering and board-level repair. 30-day warranty.',
                'address' => 'Shop 19, Tokyo Square Annex, Ring Road, Mohammadpur, Dhaka',
                'gps_address' => 'Ring Road, Mohammadpur, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'Mohammadpur',
                'lat' => 23.768931,
                'lng' => 90.359862,
                'rating' => 4.2,
                'review_count' => 2116,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['SMD Micro-Soldering', 'Motherboard Short-Circuit Repair', 'BGA Chip Rework'],
                'eta_minutes' => 60,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳350',
                'inspection_fee' => 350,
                'base_labor_fee' => 600,
                'phone' => '+8801711000010',
                'email' => 'micromaestro@meramot.local',
            ],
            [
                'name' => 'Westside Computery',
                'slug' => 'westside-computery',
                'description' => 'CPU, storage, and keyboard replacement specialists. 30-day warranty.',
                'address' => 'Rankin Street, Wari, Dhaka',
                'gps_address' => 'Rankin Street, Wari, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Wari',
                'lat' => 23.721946,
                'lng' => 90.420248,
                'rating' => 4.7,
                'review_count' => 125,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['NVMe SSD & Storage Replacement', 'Laptop Keyboard Replacement', 'Desktop CPU & Thermal Servicing'],
                'eta_minutes' => 60,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳150',
                'inspection_fee' => 150,
                'base_labor_fee' => 400,
                'phone' => '+8801711000011',
                'email' => 'westside@meramot.local',
            ],
            [
                'name' => 'The Gear Lab',
                'slug' => 'the-gear-lab',
                'description' => 'Screen repair and genuine spare parts available. Warranty included.',
                'address' => 'Block C, Bashundhara R/A, Dhaka',
                'gps_address' => 'Block C, Bashundhara R/A, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Bashundhara',
                'lat' => 23.808686,
                'lng' => 90.421096,
                'rating' => 4.2,
                'review_count' => 145,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR', 'SPARE_PARTS'],
                'specialties' => ['Smartphone Screen Repair', 'Genuine Spare Parts Installation', 'Battery & Housing Replacement'],
                'eta_minutes' => 120,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳250',
                'inspection_fee' => 250,
                'base_labor_fee' => 500,
                'phone' => '+8801711000012',
                'email' => 'gearlab@meramot.local',
            ],
            // Standalone Demo Vendor Account #2: Dhaka Device Doctors
            [
                'name' => 'Dhaka Device Doctors',
                'slug' => 'dhaka-device-doctors',
                'owner_user_id' => $dhakaDoctorsUser->id,
                'description' => 'Pioneer electronic repair clinic specializing in motherboard re-soldering and IC replacements.',
                'address' => 'Suite 3B, House 58, Road 11/A, Dhanmondi, Dhaka',
                'gps_address' => 'Road 11/A, Dhanmondi, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'lat' => 23.746100,
                'lng' => 90.374200,
                'rating' => 4.8,
                'review_count' => 340,
                'price_level' => 2,
                'has_voucher' => true,
                'free_delivery' => true,
                'has_deals' => true,
                'is_featured' => true,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['Motherboard Chip-Level Repair', 'Power IC Replacement', 'Smartphone Board Diagnostics', 'Laptop Logic Board Servicing'],
                'eta_minutes' => 90,
                'result_tag' => 'Top Pick',
                'offer_summary' => 'Inspection ৳200',
                'inspection_fee' => 200,
                'base_labor_fee' => 500,
                'phone' => '+8801711000013',
                'email' => 'devicedoctors@meramot.local',
            ],
            [
                'name' => 'Gulshan Gadget Care',
                'slug' => 'gulshan-gadget-care',
                'description' => 'Premium boutique servicing Apple, Dell XPS, and flagship Samsung Galaxy devices with authentic parts.',
                'address' => 'Level 4, Pink City Shopping Complex, Gulshan Avenue, Dhaka',
                'gps_address' => 'Gulshan Avenue, Gulshan, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'Gulshan',
                'lat' => 23.792500,
                'lng' => 90.407800,
                'rating' => 4.9,
                'review_count' => 512,
                'price_level' => 3,
                'has_voucher' => true,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => true,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR', 'SPARE_PARTS'],
                'specialties' => ['Flagship phone repair', 'Ultrabook servicing', 'Liquid damage'],
                'eta_minutes' => 60,
                'result_tag' => 'Premium Tier',
                'offer_summary' => 'Inspection ৳400',
                'inspection_fee' => 400,
                'base_labor_fee' => 850,
                'phone' => '+8801711000014',
                'email' => 'gulshangadget@meramot.local',
            ],
            [
                'name' => 'Banani Tech Bench',
                'slug' => 'banani-tech-bench',
                'description' => 'Expert workstation & gaming rig diagnostics, GPU repasting, and display calibration.',
                'address' => 'Road 11, Banani, Dhaka',
                'gps_address' => 'Road 11, Banani, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Banani',
                'lat' => 23.794000,
                'lng' => 90.402300,
                'rating' => 4.7,
                'review_count' => 198,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => true,
                'has_deals' => true,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Gaming laptop repair', 'GPU repair', 'Thermal overhaul'],
                'eta_minutes' => 120,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳300',
                'inspection_fee' => 300,
                'base_labor_fee' => 600,
                'phone' => '+8801711000015',
                'email' => 'bananitech@meramot.local',
            ],
            [
                'name' => 'Mirpur Laptop Lab',
                'slug' => 'mirpur-laptop-lab',
                'description' => 'Largest inventory of OEM laptop parts, keyboards, hinges, and screen panels in Mirpur.',
                'address' => 'Shop 312, Shah Ali Plaza, Mirpur 10 Roundabout, Dhaka',
                'gps_address' => 'Mirpur 10 Roundabout, Mirpur, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'Mirpur',
                'lat' => 23.804200,
                'lng' => 90.368800,
                'rating' => 4.6,
                'review_count' => 620,
                'price_level' => 1,
                'has_voucher' => true,
                'free_delivery' => true,
                'has_deals' => true,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR', 'SPARE_PARTS'],
                'specialties' => ['Hinge repair', 'Display replacement', 'Keyboard swapping'],
                'eta_minutes' => 90,
                'result_tag' => 'Budget Friendly',
                'offer_summary' => 'Inspection ৳150',
                'inspection_fee' => 150,
                'base_labor_fee' => 350,
                'phone' => '+8801711000016',
                'email' => 'mirpurlab@meramot.local',
            ],
            [
                'name' => 'Uttara FixPoint',
                'slug' => 'uttara-fixpoint',
                'description' => 'Complete electronic restoration hub catering to Northern Dhaka with doorstep pickup.',
                'address' => 'Sector 7, Uttara, Dhaka',
                'gps_address' => 'Sector 7, Uttara, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Uttara',
                'lat' => 23.875900,
                'lng' => 90.379500,
                'rating' => 4.7,
                'review_count' => 280,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => true,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['Tablet repair', 'Charging port fix', 'Battery replacement'],
                'eta_minutes' => 75,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳200',
                'inspection_fee' => 200,
                'base_labor_fee' => 450,
                'phone' => '+8801711000017',
                'email' => 'uttarafix@meramot.local',
            ],
            [
                'name' => 'Mohammadpur Device Depot',
                'slug' => 'mohammadpur-device-depot',
                'description' => 'Reliable neighborhood hardware mechanics with over 10 years of trusted electronics experience.',
                'address' => 'Taj Mahal Road, Mohammadpur, Dhaka',
                'gps_address' => 'Taj Mahal Road, Mohammadpur, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Mohammadpur',
                'lat' => 23.766200,
                'lng' => 90.358700,
                'rating' => 4.5,
                'review_count' => 175,
                'price_level' => 1,
                'has_voucher' => true,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Smartphone glass replacement', 'Speaker/Mic repair'],
                'eta_minutes' => 60,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳100',
                'inspection_fee' => 100,
                'base_labor_fee' => 300,
                'phone' => '+8801711000018',
                'email' => 'devicedepot@meramot.local',
            ],
            [
                'name' => 'New Market Repair Works',
                'slug' => 'new-market-repair-works',
                'description' => 'Historic repair hub known for wholesale electronic spare parts and express circuit tracing.',
                'address' => 'Elephant Road, New Market, Dhaka',
                'gps_address' => 'Elephant Road, New Market, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'New Market',
                'lat' => 23.732700,
                'lng' => 90.385400,
                'rating' => 4.4,
                'review_count' => 410,
                'price_level' => 1,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => true,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR', 'SPARE_PARTS'],
                'specialties' => ['Wholesale spare parts', 'Soldering', 'Desktop power supply'],
                'eta_minutes' => 120,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳100',
                'inspection_fee' => 100,
                'base_labor_fee' => 250,
                'phone' => '+8801711000019',
                'email' => 'newmarketrepair@meramot.local',
            ],
            [
                'name' => 'Farmgate Tech Rescue',
                'slug' => 'farmgate-tech-rescue',
                'description' => 'Central Dhaka transit hub repair station. Fast 1-hour screen and battery swaps while you wait.',
                'address' => 'Shop 14, Sezan Point Level 3, Green Road, Farmgate, Dhaka',
                'gps_address' => 'Green Road, Farmgate, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'Farmgate',
                'lat' => 23.756500,
                'lng' => 90.390300,
                'rating' => 4.5,
                'review_count' => 310,
                'price_level' => 2,
                'has_voucher' => true,
                'free_delivery' => false,
                'has_deals' => true,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Express phone repair', 'Battery swap', 'Screen replacement'],
                'eta_minutes' => 45,
                'result_tag' => 'Quick Turnaround',
                'offer_summary' => 'Inspection ৳150',
                'inspection_fee' => 150,
                'base_labor_fee' => 400,
                'phone' => '+8801711000020',
                'email' => 'farmgaterescue@meramot.local',
            ],
            [
                'name' => 'Tejgaon Circuit Care',
                'slug' => 'tejgaon-circuit-care',
                'description' => 'Industrial and enterprise device maintenance workshop with oscilloscope-guided diagnostics.',
                'address' => 'Tejgaon Industrial Area, Dhaka',
                'gps_address' => 'Tejgaon Industrial Area, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Tejgaon',
                'lat' => 23.759400,
                'lng' => 90.398800,
                'rating' => 4.6,
                'review_count' => 189,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => true,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['Power surge repair', 'Logic board repair', 'Printer servicing'],
                'eta_minutes' => 90,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳250',
                'inspection_fee' => 250,
                'base_labor_fee' => 500,
                'phone' => '+8801711000021',
                'email' => 'circuitcare@meramot.local',
            ],
            [
                'name' => 'Bashundhara Laptop Lounge',
                'slug' => 'bashundhara-laptop-lounge',
                'description' => 'Student-friendly repair workshop offering student discounts, software installs, and hardware repairs.',
                'address' => 'Gate 1, Bashundhara R/A, Dhaka',
                'gps_address' => 'Gate 1, Bashundhara R/A, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Bashundhara',
                'lat' => 23.813000,
                'lng' => 90.425000,
                'rating' => 4.8,
                'review_count' => 420,
                'price_level' => 1,
                'has_voucher' => true,
                'free_delivery' => true,
                'has_deals' => true,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['Campus Laptop Diagnostic & Tune-Up', 'NVMe SSD Upgrade', 'RAM Capacity Expansion', 'Thermal Servicing'],
                'eta_minutes' => 60,
                'result_tag' => 'Campus Favorite',
                'offer_summary' => 'Inspection ৳150',
                'inspection_fee' => 150,
                'base_labor_fee' => 300,
                'phone' => '+8801711000022',
                'email' => 'laptoplounge@meramot.local',
            ],
            [
                'name' => 'ByteBack Solutions',
                'slug' => 'byteback-solutions',
                'description' => 'Data recovery, corrupted NAND flash repair, and precision water damage treatment.',
                'address' => 'Satmasjid Road, Dhanmondi, Dhaka',
                'gps_address' => 'Satmasjid Road, Dhanmondi, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Dhanmondi',
                'lat' => 23.751200,
                'lng' => 90.378900,
                'rating' => 4.7,
                'review_count' => 160,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Data recovery', 'Liquid damage treatment', 'Storage upgrade'],
                'eta_minutes' => 120,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳300',
                'inspection_fee' => 300,
                'base_labor_fee' => 600,
                'phone' => '+8801711000023',
                'email' => 'byteback@meramot.local',
            ],
            [
                'name' => 'Pixel Repair Station',
                'slug' => 'pixel-repair-station',
                'description' => 'OLED and Retina display specialists. Dust-free clean room lamination and glass bonding.',
                'address' => 'Gulshan 2 Circle, Dhaka',
                'gps_address' => 'Gulshan 2 Circle, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Gulshan',
                'lat' => 23.790100,
                'lng' => 90.412000,
                'rating' => 4.8,
                'review_count' => 290,
                'price_level' => 3,
                'has_voucher' => true,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['OLED panel lamination', 'Display repair', 'Flex cable fix'],
                'eta_minutes' => 90,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳350',
                'inspection_fee' => 350,
                'base_labor_fee' => 750,
                'phone' => '+8801711000024',
                'email' => 'pixelstation@meramot.local',
            ],
            [
                'name' => 'Motherboard Masters',
                'slug' => 'motherboard-masters',
                'description' => 'Advanced BGA chip reballing, power sequence troubleshooting, and BIOS chip reprogramming.',
                'address' => 'Mirpur 2 Stadium Market, Dhaka',
                'gps_address' => 'Mirpur 2 Stadium Market, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Mirpur',
                'lat' => 23.810500,
                'lng' => 90.365400,
                'rating' => 4.9,
                'review_count' => 480,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => true,
                'has_deals' => true,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['BGA reballing', 'BIOS flash', 'Short circuit diagnosis'],
                'eta_minutes' => 150,
                'result_tag' => 'Chip-Level Master',
                'offer_summary' => 'Inspection ৳250',
                'inspection_fee' => 250,
                'base_labor_fee' => 650,
                'phone' => '+8801711000025',
                'email' => 'mbmasters@meramot.local',
            ],
            [
                'name' => 'QuickFix Electronics',
                'slug' => 'quickfix-electronics',
                'description' => 'Fast turnaround for daily workhorse gadgets: audio gear, chargers, ports, and keyboards.',
                'address' => 'Indira Road, Farmgate, Dhaka',
                'gps_address' => 'Indira Road, Farmgate, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Farmgate',
                'lat' => 23.758000,
                'lng' => 90.389000,
                'rating' => 4.3,
                'review_count' => 140,
                'price_level' => 1,
                'has_voucher' => true,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Audio headphone repair', 'Charging port cleaning', 'Cable fix'],
                'eta_minutes' => 45,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳100',
                'inspection_fee' => 100,
                'base_labor_fee' => 250,
                'phone' => '+8801711000026',
                'email' => 'quickfix@meramot.local',
            ],
            [
                'name' => 'Urban Device Clinic',
                'slug' => 'urban-device-clinic',
                'description' => 'Modern tech care center providing warranty-backed replacements and transparent pricing.',
                'address' => 'Kemal Ataturk Avenue, Banani, Dhaka',
                'gps_address' => 'Kemal Ataturk Avenue, Banani, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Banani',
                'lat' => 23.791500,
                'lng' => 90.405500,
                'rating' => 4.6,
                'review_count' => 215,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['Smartwatch glass fix', 'Tablet display', 'Camera glass repair'],
                'eta_minutes' => 60,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳250',
                'inspection_fee' => 250,
                'base_labor_fee' => 500,
                'phone' => '+8801711000027',
                'email' => 'urbanclinic@meramot.local',
            ],
            [
                'name' => 'Prime Laptop Care',
                'slug' => 'prime-laptop-care',
                'description' => 'Lenovo ThinkPad, HP EliteBook, and Asus ROG dedicated maintenance facility.',
                'address' => 'Sector 3, Uttara, Dhaka',
                'gps_address' => 'Sector 3, Uttara, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Uttara',
                'lat' => 23.871000,
                'lng' => 90.382000,
                'rating' => 4.7,
                'review_count' => 310,
                'price_level' => 2,
                'has_voucher' => true,
                'free_delivery' => true,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR', 'SPARE_PARTS'],
                'specialties' => ['ThinkPad repairs', 'Fan replacement', 'Thermal pad servicing'],
                'eta_minutes' => 80,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳200',
                'inspection_fee' => 200,
                'base_labor_fee' => 450,
                'phone' => '+8801711000028',
                'email' => 'primelaptop@meramot.local',
            ],
            [
                'name' => 'Screen & Circuit',
                'slug' => 'screen-and-circuit',
                'description' => 'Precision screen refurbishment, polarizer replacement, and OCA vacuum bonding.',
                'address' => 'Salimullah Road, Mohammadpur, Dhaka',
                'gps_address' => 'Salimullah Road, Mohammadpur, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Mohammadpur',
                'lat' => 23.764000,
                'lng' => 90.360100,
                'rating' => 4.5,
                'review_count' => 195,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => false,
                'has_deals' => true,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR'],
                'specialties' => ['OCA bonding', 'Touch digitizer replacement', 'Display restoration'],
                'eta_minutes' => 90,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳150',
                'inspection_fee' => 150,
                'base_labor_fee' => 400,
                'phone' => '+8801711000029',
                'email' => 'screencircuit@meramot.local',
            ],
            [
                'name' => 'Trusted Tech Service',
                'slug' => 'trusted-tech-service',
                'description' => 'Corporate IT and consumer gadget support providing genuine manufacturer warranty parts.',
                'address' => 'Nabisco Junction, Tejgaon, Dhaka',
                'gps_address' => 'Nabisco Junction, Tejgaon, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Tejgaon',
                'lat' => 23.761000,
                'lng' => 90.395000,
                'rating' => 4.4,
                'review_count' => 165,
                'price_level' => 2,
                'has_voucher' => false,
                'free_delivery' => true,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['Corporate fleet servicing', 'Printer maintenance', 'Power supply'],
                'eta_minutes' => 120,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳200',
                'inspection_fee' => 200,
                'base_labor_fee' => 400,
                'phone' => '+8801711000030',
                'email' => 'trustedtech@meramot.local',
            ],
            [
                'name' => 'CoreFix Bangladesh',
                'slug' => 'corefix-bangladesh',
                'description' => 'Consoles, handhelds, and legacy gaming devices (PlayStation, Nintendo Switch, Xbox).',
                'address' => 'Level 6, Shop 619, Multiplan Center, Elephant Road, Dhaka',
                'gps_address' => 'Elephant Road, New Market, Dhaka',
                'same_as_gps_location' => false,
                'city' => 'Dhaka',
                'area' => 'New Market',
                'lat' => 23.734000,
                'lng' => 90.384000,
                'rating' => 4.6,
                'review_count' => 260,
                'price_level' => 2,
                'has_voucher' => true,
                'free_delivery' => false,
                'has_deals' => true,
                'is_featured' => false,
                'categories' => ['IN_SHOP_REPAIR', 'SPARE_PARTS'],
                'specialties' => ['Console repair', 'HDMI port soldering', 'Joy-Con stick drift fix'],
                'eta_minutes' => 90,
                'result_tag' => 'Console Specialist',
                'offer_summary' => 'Inspection ৳250',
                'inspection_fee' => 250,
                'base_labor_fee' => 500,
                'phone' => '+8801711000031',
                'email' => 'corefix@meramot.local',
            ],
            [
                'name' => 'Laptop Harbor',
                'slug' => 'laptop-harbor',
                'description' => 'Comprehensive multi-brand laptop repair hub with direct courier pickup for Bashundhara.',
                'address' => 'Apollo Gate, Bashundhara R/A, Dhaka',
                'gps_address' => 'Apollo Gate, Bashundhara R/A, Dhaka',
                'same_as_gps_location' => true,
                'city' => 'Dhaka',
                'area' => 'Bashundhara',
                'lat' => 23.815000,
                'lng' => 90.428000,
                'rating' => 4.7,
                'review_count' => 310,
                'price_level' => 2,
                'has_voucher' => true,
                'free_delivery' => true,
                'has_deals' => false,
                'is_featured' => false,
                'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
                'specialties' => ['Laptop battery replacement', 'Keyboard fixing', 'OS & firmware recovery'],
                'eta_minutes' => 60,
                'result_tag' => null,
                'offer_summary' => 'Inspection ৳200',
                'inspection_fee' => 200,
                'base_labor_fee' => 400,
                'phone' => '+8801711000032',
                'email' => 'laptopharbor@meramot.local',
            ],
        ];

        // ---------------------------------------------------------------------
        // PART 2: Load Nationwide Bangladesh OpenStreetMap `shop=electronics` Snapshot
        // ---------------------------------------------------------------------
        $osmSnapshotPath = database_path('seeders/data/bangladesh_osm_electronics_shops.json');
        $osmShops = [];
        if (is_file($osmSnapshotPath)) {
            $decoded = json_decode((string) file_get_contents($osmSnapshotPath), true);
            $osmShops = $decoded['shops'] ?? [];
        }

        // Combine curated + OSM records into unified normalized entries
        $combinedEntries = [];
        $usedSlugs = [];

        foreach ($curatedShops as $idx => $s) {
            $slug = $s['slug'];
            $usedSlugs[$slug] = true;
            $groupKey = $s['vendor_group_key'] ?? ('curated_standalone:' . $slug);

            $combinedEntries[] = [
                'osm_id' => null,
                'vendor_group_key' => $groupKey,
                'owner_user_id' => $s['owner_user_id'] ?? $directoryVendorUser->id,
                'name' => $s['name'],
                'slug' => $slug,
                'is_branch' => (bool) ($s['is_branch'] ?? false),
                'branch_name' => $s['branch_name'] ?? null,
                'description' => $s['description'],
                'address' => $s['address'],
                'gps_address' => $s['gps_address'],
                'same_as_gps_location' => (bool) $s['same_as_gps_location'],
                'city' => $s['city'] ?? 'Dhaka',
                'area' => $s['area'],
                'lat' => (float) $s['lat'],
                'lng' => (float) $s['lng'],
                'rating' => 0.00,
                'review_count' => 0,
                'price_level' => (int) ($s['price_level'] ?? 2),
                'inspection_fee' => (float) ($s['inspection_fee'] ?? 200),
                'base_labor_fee' => (float) ($s['base_labor_fee'] ?? 400),
                'offer_summary' => $s['offer_summary'] ?? ('Inspection ৳' . ($s['inspection_fee'] ?? 200)),
                'result_tag' => $s['result_tag'] ?? null,
                'eta_minutes' => (int) ($s['eta_minutes'] ?? 60),
                'has_deals' => (bool) ($s['has_deals'] ?? false),
                'has_voucher' => (bool) ($s['has_voucher'] ?? false),
                'free_delivery' => (bool) ($s['free_delivery'] ?? false),
                'is_featured' => (bool) ($s['is_featured'] ?? false),
                'categories' => $s['categories'] ?? ['IN_SHOP_REPAIR'],
                'specialties' => $s['specialties'] ?? ['Laptop repair', 'Phone repair'],
                'phone' => $s['phone'] ?? null,
                'email' => $s['email'] ?? ($slug . '@meramot.local'),
                'opening_hours_text' => 'Sat – Thu: 10:00 AM – 8:30 PM',
                'is_curated' => true,
            ];
        }

        foreach ($osmShops as $osm) {
            $slug = $osm['slug'];
            if (isset($usedSlugs[$slug])) {
                $slug .= '-' . str_replace('/', '-', $osm['osm_id']);
            }
            $usedSlugs[$slug] = true;

            $inspFee = (float) ($osm['inspection_fee'] ?? 200);
            $combinedEntries[] = [
                'osm_id' => $osm['osm_id'],
                'vendor_group_key' => $osm['vendor_group_key'] ?? ('standalone:' . $osm['osm_id']),
                'owner_user_id' => $directoryVendorUser->id,
                'name' => $osm['name'],
                'slug' => $slug,
                'is_branch' => (bool) ($osm['is_branch'] ?? false),
                'branch_name' => $osm['branch_name'] ?? null,
                'description' => $osm['description'],
                'address' => $osm['address'],
                'gps_address' => $osm['gps_address'],
                'same_as_gps_location' => (bool) $osm['same_as_gps_location'],
                'city' => $osm['city'],
                'area' => $osm['area'],
                'lat' => (float) $osm['lat'],
                'lng' => (float) $osm['lng'],
                'rating' => 0.00,
                'review_count' => 0,
                'price_level' => (int) ($osm['price_level'] ?? 2),
                'inspection_fee' => $inspFee,
                'base_labor_fee' => (float) ($osm['base_labor_fee'] ?? 450),
                'offer_summary' => 'Inspection ৳' . (int) $inspFee,
                'result_tag' => !empty($osm['is_branch']) ? 'Verified Chain' : null,
                'eta_minutes' => (int) ($osm['eta_minutes'] ?? 60),
                'has_deals' => (bool) ($osm['has_deals'] ?? false),
                'has_voucher' => (bool) ($osm['has_voucher'] ?? false),
                'free_delivery' => (bool) ($osm['free_delivery'] ?? false),
                'is_featured' => false,
                'categories' => $osm['categories'] ?? ['IN_SHOP_REPAIR', 'SPARE_PARTS'],
                'specialties' => $osm['specialties'] ?? ['Electronics Diagnostics', 'Circuit & Component Repair'],
                'phone' => !empty($osm['phone']) ? $osm['phone'] : null,
                'email' => Str::limit($slug, 42, '') . '@osm.meramot.bd',
                'opening_hours_text' => $osm['opening_hours_text'] ?? 'Sat – Thu: 10:00 AM – 8:30 PM',
                'is_curated' => false,
            ];
        }

        // ---------------------------------------------------------------------
        // PART 3: Create / Update Coherent `VendorProfile` Per Distinct Vendor Group
        // ---------------------------------------------------------------------
        $vendorBlueprints = [];
        $vendorIndex = 1;

        foreach ($combinedEntries as $entry) {
            $gKey = $entry['vendor_group_key'];
            if (!isset($vendorBlueprints[$gKey])) {
                $licenseCode = 'TRAD/BD/' . str_pad((string) $vendorIndex, 6, '0', STR_PAD_LEFT);
                $nidNumber = '199' . str_pad((string) $vendorIndex, 10, '0', STR_PAD_LEFT);
                $vendorIndex++;

                $vendorBlueprints[$gKey] = [
                    'user_id' => $entry['owner_user_id'],
                    'shop_name' => $entry['name'],
                    'brand_name' => $entry['name'],
                    'is_branched' => $entry['is_branch'],
                    'business_license_no' => $licenseCode,
                    'nid_number' => $nidNumber,
                    'verification_status' => 'approved',
                    'shop_address' => $entry['address'],
                    'gps_address' => $entry['gps_address'],
                    'same_as_gps_location' => $entry['same_as_gps_location'],
                    'city' => $entry['city'],
                    'area' => $entry['area'],
                    'shop_latitude' => $entry['lat'],
                    'shop_longitude' => $entry['lng'],
                    'phone' => $entry['phone'],
                    'business_email' => $entry['email'],
                    'categories' => json_encode($entry['specialties'], JSON_UNESCAPED_UNICODE),
                    'specialties' => json_encode($entry['specialties'], JSON_UNESCAPED_UNICODE),
                    'courier_pickup' => in_array('COURIER_PICKUP', $entry['categories'], true),
                    'in_shop_repair' => in_array('IN_SHOP_REPAIR', $entry['categories'], true),
                    'spare_parts' => in_array('SPARE_PARTS', $entry['categories'], true),
                    'commission_rate' => 10.00,
                    'rating' => 0.00,
                    'reviewed_by_admin_id' => $meramotAdmin?->id,
                    'reviewed_at' => $reviewedAt,
                    'notes' => 'group_key:' . $gKey,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            } elseif ($entry['is_branch']) {
                $vendorBlueprints[$gKey]['is_branched'] = true;
            }
        }

        // Fetch existing VendorProfiles keyed by notes (`group_key:...`) or `shop_name` for idempotency
        $existingByGroupKey = VendorProfile::whereNotNull('notes')
            ->where('notes', 'LIKE', 'group_key:%')
            ->pluck('id', 'notes')
            ->all();

        $profilesToInsert = [];
        foreach ($vendorBlueprints as $gKey => $bp) {
            $noteTag = 'group_key:' . $gKey;
            if (isset($existingByGroupKey[$noteTag])) {
                VendorProfile::where('id', $existingByGroupKey[$noteTag])->update([
                    'user_id' => $bp['user_id'],
                    'shop_name' => $bp['shop_name'],
                    'brand_name' => $bp['brand_name'],
                    'is_branched' => $bp['is_branched'],
                    'shop_address' => $bp['shop_address'],
                    'gps_address' => $bp['gps_address'],
                    'same_as_gps_location' => $bp['same_as_gps_location'],
                    'city' => $bp['city'],
                    'area' => $bp['area'],
                    'rating' => 0.00,
                    'reviewed_by_admin_id' => $meramotAdmin?->id,
                    'reviewed_at' => $reviewedAt,
                    'updated_at' => $now,
                ]);
            } else {
                $profilesToInsert[] = $bp;
            }
        }

        foreach (array_chunk($profilesToInsert, 250) as $chunk) {
            VendorProfile::insert($chunk);
        }

        $vendorIdByNote = VendorProfile::whereNotNull('notes')
            ->where('notes', 'LIKE', 'group_key:%')
            ->pluck('id', 'notes')
            ->all();

        // ---------------------------------------------------------------------
        // PART 4: Batch Upsert All `RepairShop` Rows (Curated + Nationwide OSM)
        //         Ratings & review counts start at 0.00 / 0 until ReviewSeeder
        //         computes them strictly from verified completed-order reviews!
        // ---------------------------------------------------------------------
        $shopRows = [];
        foreach ($combinedEntries as $entry) {
            $gKey = $entry['vendor_group_key'];
            $vendorProfileId = $vendorIdByNote['group_key:' . $gKey] ?? null;
            $canonicalBrandName = $vendorBlueprints[$gKey]['brand_name'] ?? $entry['name'];

            $shopRows[] = [
                'vendor_profile_id' => $vendorProfileId,
                'osm_id' => $entry['osm_id'],
                'name' => $canonicalBrandName,
                'slug' => $entry['slug'],
                'is_branch' => $entry['is_branch'],
                'branch_name' => $entry['branch_name'],
                'description' => $entry['description'],
                'address' => $entry['address'],
                'gps_address' => $entry['gps_address'],
                'same_as_gps_location' => $entry['same_as_gps_location'],
                'city' => $entry['city'],
                'area' => $entry['area'],
                'latitude' => $entry['lat'],
                'longitude' => $entry['lng'],
                'rating' => 0.00,
                'review_count' => 0,
                'price_level' => $entry['price_level'],
                'inspection_fee' => $entry['inspection_fee'],
                'base_labor_fee' => $entry['base_labor_fee'],
                'offer_summary' => $entry['offer_summary'],
                'result_tag' => $entry['result_tag'],
                'eta_minutes' => $entry['eta_minutes'],
                'has_deals' => $entry['has_deals'],
                'has_voucher' => $entry['has_voucher'],
                'free_delivery' => $entry['free_delivery'],
                'is_featured' => $entry['is_featured'],
                'is_active' => true,
                'is_public' => true,
                'setup_complete' => true,
                'accepts_direct_orders' => true,
                'supports_pickup' => in_array('COURIER_PICKUP', $entry['categories'], true),
                'categories' => json_encode($entry['categories'], JSON_UNESCAPED_UNICODE),
                'specialties' => json_encode($entry['specialties'], JSON_UNESCAPED_UNICODE),
                'phone' => $entry['phone'],
                'email' => $entry['email'],
                'opening_hours_text' => $entry['opening_hours_text'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($shopRows, 250) as $chunk) {
            RepairShop::upsert(
                $chunk,
                ['slug'],
                [
                    'vendor_profile_id', 'osm_id', 'name', 'is_branch', 'branch_name',
                    'description', 'address', 'gps_address', 'same_as_gps_location',
                    'city', 'area', 'latitude', 'longitude', 'rating', 'review_count',
                    'price_level', 'inspection_fee', 'base_labor_fee', 'offer_summary',
                    'result_tag', 'eta_minutes', 'has_deals', 'has_voucher', 'free_delivery',
                    'is_featured', 'is_active', 'is_public', 'setup_complete',
                    'accepts_direct_orders', 'supports_pickup', 'categories', 'specialties',
                    'phone', 'email', 'opening_hours_text', 'updated_at',
                ]
            );
        }

        // ---------------------------------------------------------------------
        // PART 5: Batch Upsert Bookable Direct-Order `ShopService` Rows
        // ---------------------------------------------------------------------
        ShopService::query()->delete();

        $shopIdBySlug = RepairShop::pluck('id', 'slug')->all();
        $serviceRows = [];

        foreach ($combinedEntries as $entry) {
            $shopId = $shopIdBySlug[$entry['slug']] ?? null;
            if (!$shopId) {
                continue;
            }

            $specsToSeed = $entry['is_curated']
                ? $entry['specialties']
                : array_slice($entry['specialties'], 0, 2);

            $baseLabor = (float) $entry['base_labor_fee'];
            $includesPickup = in_array('COURIER_PICKUP', $entry['categories'], true);
            $seenSvcSlugs = [];

            foreach ($specsToSeed as $svcIdx => $specName) {
                $normalizedSvc = $this->normalizeBookableService((string) $specName);
                $svcSlug = Str::slug($normalizedSvc['name']) ?: ('service-' . ($svcIdx + 1));
                if (isset($seenSvcSlugs[$svcSlug])) {
                    $svcSlug .= '-' . ($svcIdx + 1);
                }
                $seenSvcSlugs[$svcSlug] = true;

                $serviceRows[] = [
                    'shop_id' => $shopId,
                    'slug' => $svcSlug,
                    'name' => $normalizedSvc['name'],
                    'short_description' => $normalizedSvc['description'],
                    'device_type' => $normalizedSvc['device_type'],
                    'issue_category' => $normalizedSvc['issue_category'],
                    'pricing_type' => 'STARTING_FROM',
                    'base_price' => $baseLabor + ($svcIdx * 250),
                    'estimated_days_min' => 1,
                    'estimated_days_max' => 3,
                    'includes_pickup' => $includesPickup,
                    'sort_order' => $svcIdx,
                    'is_featured' => $svcIdx === 0,
                    'is_active' => true,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($serviceRows, 400) as $chunk) {
            ShopService::upsert(
                $chunk,
                ['shop_id', 'slug'],
                [
                    'name', 'short_description', 'device_type', 'issue_category',
                    'pricing_type', 'base_price', 'estimated_days_min', 'estimated_days_max',
                    'includes_pickup', 'sort_order', 'is_featured', 'is_active', 'updated_at',
                ]
            );
        }

        // ---------------------------------------------------------------------
        // PART 6: Batch Upsert `shop_staff` OWNER Links (Shop <-> Vendor User)
        // ---------------------------------------------------------------------
        $staffRows = [];
        $vendorUserByProfileId = VendorProfile::pluck('user_id', 'id')->all();

        foreach (RepairShop::whereNotNull('vendor_profile_id')->get(['id', 'vendor_profile_id']) as $s) {
            $ownerUserId = $vendorUserByProfileId[$s->vendor_profile_id] ?? null;
            if (!$ownerUserId) {
                continue;
            }
            $staffRows[] = [
                'shop_id' => $s->id,
                'user_id' => $ownerUserId,
                'role' => 'OWNER',
                'is_active' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($staffRows, 400) as $chunk) {
            DB::table('shop_staff')->upsert(
                $chunk,
                ['shop_id', 'user_id'],
                ['role', 'is_active', 'updated_at']
            );
        }
    }

    /**
     * Normalize any specialty tag into a genuine bookable hardware repair service item.
     */
    private function normalizeBookableService(string $rawSpec): array
    {
        $clean = trim($rawSpec);
        $lower = strtolower($clean);

        $exactMap = [
            'laptop' => [
                'name' => 'Laptop Full Hardware Diagnostic & Servicing',
                'device_type' => 'Laptop',
                'issue_category' => 'Hardware Diagnostics',
                'description' => 'Full motherboard rail check, thermal repasting, cooling fan cleaning, and storage health inspection.',
            ],
            'phone' => [
                'name' => 'Smartphone Component & Display Repair',
                'device_type' => 'Phone',
                'issue_category' => 'Screen / Display',
                'description' => 'Precision screen digitizer check, charging port reflow, battery calibration, and board inspection.',
            ],
            'tablet' => [
                'name' => 'Tablet Digitizer & Charging Port Repair',
                'device_type' => 'Tablet',
                'issue_category' => 'Screen / Display',
                'description' => 'Touch glass lamination, USB-C charging port replacement, and battery health restoration.',
            ],
            'smartwatch' => [
                'name' => 'Smartwatch Glass & Sensor Calibration',
                'device_type' => 'Smartwatch',
                'issue_category' => 'Screen / Display',
                'description' => 'Micro-sealed OLED glass bonding, crown/button repair, and waterproof gasket resealing.',
            ],
            'console' => [
                'name' => 'Gaming Console HDMI & Thermal Servicing',
                'device_type' => 'Gaming Console',
                'issue_category' => 'Overheating / Port',
                'description' => 'HDMI 2.1 port micro-soldering, liquid metal / thermal paste renewal, and PSU diagnostics.',
            ],
            'printer' => [
                'name' => 'Printer Roller, Fuser & Ink System Servicing',
                'device_type' => 'Printer',
                'issue_category' => 'Mechanical Servicing',
                'description' => 'Paper feed roller replacement, printhead ultrasonic cleaning, and formatter board repair.',
            ],
            'student discount' => [
                'name' => 'Campus Laptop Diagnostic & Tune-Up',
                'device_type' => 'Laptop',
                'issue_category' => 'Preventive Maintenance',
                'description' => 'Comprehensive laptop inspection, dust removal, thermal repasting, and OS optimization.',
            ],
            'pickup service' => [
                'name' => 'Express Bench Hardware Diagnostic',
                'device_type' => 'Electronics',
                'issue_category' => 'Hardware Diagnostics',
                'description' => 'Priority bench fault isolation with itemized component repair estimate and doorstep courier option.',
            ],
            'replacement parts' => [
                'name' => 'Genuine Spare Parts Fitting & Calibration',
                'device_type' => 'Electronics',
                'issue_category' => 'Parts Replacement',
                'description' => 'Certified installation of OEM-grade replacement modules with post-repair functional verification.',
            ],
        ];

        if (isset($exactMap[$lower])) {
            return $exactMap[$lower];
        }

        $deviceType = 'Electronics';
        if (str_contains($lower, 'macbook') || str_contains($lower, 'laptop') || str_contains($lower, 'thinkpad') || str_contains($lower, 'ultrabook') || str_contains($lower, 'ssd') || str_contains($lower, 'ram')) {
            $deviceType = 'Laptop';
        } elseif (str_contains($lower, 'phone') || str_contains($lower, 'iphone') || str_contains($lower, 'oled')) {
            $deviceType = 'Phone';
        } elseif (str_contains($lower, 'tablet') || str_contains($lower, 'ipad')) {
            $deviceType = 'Tablet';
        } elseif (str_contains($lower, 'console') || str_contains($lower, 'hdmi') || str_contains($lower, 'joy-con')) {
            $deviceType = 'Gaming Console';
        } elseif (str_contains($lower, 'printer')) {
            $deviceType = 'Printer';
        } elseif (str_contains($lower, 'smartwatch')) {
            $deviceType = 'Smartwatch';
        }

        $title = preg_match('/^[a-z]/', $clean) ? ucwords($clean) : $clean;

        return [
            'name' => $title,
            'device_type' => $deviceType,
            'issue_category' => $title,
            'description' => 'Certified workshop service with genuine replacement components, bench testing, and repair warranty.',
        ];
    }
}

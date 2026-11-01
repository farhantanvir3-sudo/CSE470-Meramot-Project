<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\RepairShop;
use App\Models\VendorProfile;
use App\Models\User;

/**
 * Meramot Shop Seeder — Ports all 32 original repair shops from previous Meramot
 * Covers features R6 (Shop Search & Filters) & R11 (Vendor Profiles)
 */
class ShopSeeder extends Seeder
{
    public function run(): void
    {
        $defaultVendor = User::where('role', 'vendor')->first() ?? User::first();

        $originalShops = [
            // 20 Core Metro Shops
            ['name' => 'Dhaka Device Doctors', 'slug' => 'dhaka-device-doctors', 'area' => 'Dhanmondi', 'lat' => 23.7461, 'lng' => 90.3742, 'rating' => 4.8],
            ['name' => 'Gulshan Gadget Care', 'slug' => 'gulshan-gadget-care', 'area' => 'Gulshan', 'lat' => 23.7925, 'lng' => 90.4078, 'rating' => 4.9],
            ['name' => 'Banani Tech Bench', 'slug' => 'banani-tech-bench', 'area' => 'Banani', 'lat' => 23.7940, 'lng' => 90.4023, 'rating' => 4.7],
            ['name' => 'Mirpur Laptop Lab', 'slug' => 'mirpur-laptop-lab', 'area' => 'Mirpur', 'lat' => 23.8042, 'lng' => 90.3688, 'rating' => 4.6],
            ['name' => 'Uttara FixPoint', 'slug' => 'uttara-fixpoint', 'area' => 'Uttara', 'lat' => 23.8759, 'lng' => 90.3795, 'rating' => 4.7],
            ['name' => 'Mohammadpur Device Depot', 'slug' => 'mohammadpur-device-depot', 'area' => 'Mohammadpur', 'lat' => 23.7662, 'lng' => 90.3587, 'rating' => 4.5],
            ['name' => 'New Market Repair Works', 'slug' => 'new-market-repair-works', 'area' => 'New Market', 'lat' => 23.7327, 'lng' => 90.3854, 'rating' => 4.4],
            ['name' => 'Farmgate Tech Rescue', 'slug' => 'farmgate-tech-rescue', 'area' => 'Farmgate', 'lat' => 23.7565, 'lng' => 90.3903, 'rating' => 4.5],
            ['name' => 'Tejgaon Circuit Care', 'slug' => 'tejgaon-circuit-care', 'area' => 'Tejgaon', 'lat' => 23.7594, 'lng' => 90.3988, 'rating' => 4.6],
            ['name' => 'Bashundhara Laptop Lounge', 'slug' => 'bashundhara-laptop-lounge', 'area' => 'Bashundhara', 'lat' => 23.8130, 'lng' => 90.4250, 'rating' => 4.8],
            ['name' => 'ByteBack Solutions', 'slug' => 'byteback-solutions', 'area' => 'Dhanmondi', 'lat' => 23.7512, 'lng' => 90.3789, 'rating' => 4.7],
            ['name' => 'Pixel Repair Station', 'slug' => 'pixel-repair-station', 'area' => 'Gulshan', 'lat' => 23.7901, 'lng' => 90.4120, 'rating' => 4.8],
            ['name' => 'Motherboard Masters', 'slug' => 'motherboard-masters', 'area' => 'Mirpur', 'lat' => 23.8105, 'lng' => 90.3654, 'rating' => 4.9],
            ['name' => 'QuickFix Electronics', 'slug' => 'quickfix-electronics', 'area' => 'Farmgate', 'lat' => 23.7580, 'lng' => 90.3890, 'rating' => 4.3],
            ['name' => 'Urban Device Clinic', 'slug' => 'urban-device-clinic', 'area' => 'Banani', 'lat' => 23.7915, 'lng' => 90.4055, 'rating' => 4.6],
            ['name' => 'Prime Laptop Care', 'slug' => 'prime-laptop-care', 'area' => 'Uttara', 'lat' => 23.8710, 'lng' => 90.3820, 'rating' => 4.7],
            ['name' => 'Screen & Circuit', 'slug' => 'screen-and-circuit', 'area' => 'Mohammadpur', 'lat' => 23.7640, 'lng' => 90.3601, 'rating' => 4.5],
            ['name' => 'Trusted Tech Service', 'slug' => 'trusted-tech-service', 'area' => 'Tejgaon', 'lat' => 23.7610, 'lng' => 90.3950, 'rating' => 4.4],
            ['name' => 'CoreFix Bangladesh', 'slug' => 'corefix-bangladesh', 'area' => 'New Market', 'lat' => 23.7340, 'lng' => 90.3840, 'rating' => 4.6],
            ['name' => 'Laptop Harbor', 'slug' => 'laptop-harbor', 'area' => 'Bashundhara', 'lat' => 23.8150, 'lng' => 90.4280, 'rating' => 4.7],

            // 12 Featured & Specialty Repair Hubs
            ['name' => 'ProTech Solutions', 'slug' => 'protech-solutions', 'area' => 'Banani', 'lat' => 23.7938, 'lng' => 90.4048, 'rating' => 4.95, 'specialties' => ['Battery replacement', 'Logic board repair', 'Laptop', 'Phone']],
            ['name' => 'The Gadget Den', 'slug' => 'the-gadget-den', 'area' => 'Gulshan', 'lat' => 23.7806, 'lng' => 90.4168, 'rating' => 4.9, 'specialties' => ['Screen replacement', 'Keyboard repair', 'Tablet', 'Smartwatch']],
            ['name' => 'Budget Fixer', 'slug' => 'budget-fixer', 'area' => 'Dhanmondi', 'lat' => 23.7461, 'lng' => 90.3742, 'rating' => 4.5, 'specialties' => ['Display replacement', 'Screen repair', 'Console', 'Printer']],
            ['name' => 'MacMaster Repairs', 'slug' => 'macmaster-repairs', 'area' => 'Mohakhali', 'lat' => 23.7776, 'lng' => 90.3995, 'rating' => 4.85, 'specialties' => ['Certified Apple support', 'MacBook repair']],
            ['name' => 'The iDoc Garage', 'slug' => 'the-idoc-garage', 'area' => 'Uttara', 'lat' => 23.8759, 'lng' => 90.3888, 'rating' => 4.8, 'specialties' => ['Battery replacement', 'Water damage recovery']],
            ['name' => 'ByteSize Repairs', 'slug' => 'bytesize-repairs', 'area' => 'Mirpur', 'lat' => 23.8373, 'lng' => 90.3668, 'rating' => 4.7, 'specialties' => ['Screen replacement', 'Keyboard repair']],
            ['name' => 'AppleCore Solutions', 'slug' => 'applecore-solutions', 'area' => 'Badda', 'lat' => 23.7805, 'lng' => 90.4267, 'rating' => 4.9, 'specialties' => ['Logic board repair', 'Water damage recovery']],
            ['name' => 'Main Street Tech', 'slug' => 'main-street-tech', 'area' => 'Tejgaon', 'lat' => 23.7603, 'lng' => 90.3905, 'rating' => 4.4, 'specialties' => ['Trackpad replacement', 'Battery replacement']],
            ['name' => 'Precision Fix', 'slug' => 'precision-fix', 'area' => 'Rampura', 'lat' => 23.7612, 'lng' => 90.4208, 'rating' => 4.65, 'specialties' => ['Battery replacement', 'Pickup service']],
            ['name' => 'Micro-Maestro', 'slug' => 'micro-maestro', 'area' => 'Mohammadpur', 'lat' => 23.7658, 'lng' => 90.3584, 'rating' => 4.75, 'specialties' => ['Micro soldering', 'Board repair']],
            ['name' => 'Westside Computery', 'slug' => 'westside-computery', 'area' => 'Wari', 'lat' => 23.7174, 'lng' => 90.4183, 'rating' => 4.55, 'specialties' => ['Storage replacement', 'Keyboard repair']],
            ['name' => 'The Gear Lab', 'slug' => 'the-gear-lab', 'area' => 'Bashundhara', 'lat' => 23.8183, 'lng' => 90.4328, 'rating' => 4.8, 'specialties' => ['Replacement parts', 'Screen repair']],
        ];

        foreach ($originalShops as $index => $s) {
            VendorProfile::updateOrCreate(
                ['shop_name' => $s['name']],
                [
                    'user_id' => $defaultVendor->id,
                    'business_license_no' => 'TRAD/DNCC/' . (100000 + $index),
                    'nid_number' => '199' . str_pad((string)$index, 7, '0', STR_PAD_LEFT),
                    'verification_status' => 'approved',
                    'shop_address' => $s['area'] . ', Dhaka',
                    'shop_latitude' => $s['lat'],
                    'shop_longitude' => $s['lng'],
                    'categories' => ['Laptop', 'Mobile Phone', 'Hardware Diagnostic', 'Display Panel'],
                    'commission_rate' => 10.00,
                    'rating' => $s['rating'],
                ]
            );
        }
    }
}

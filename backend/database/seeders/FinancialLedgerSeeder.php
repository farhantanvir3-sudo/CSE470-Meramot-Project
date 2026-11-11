<?php

namespace Database\Seeders;

use App\Models\Bid;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\FinancialLedger;
use App\Models\LocationLog;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\RiderEarnings;
use App\Models\ServicePackage;
use App\Models\ShopService;
use App\Models\User;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * ============================================================================
 * FEATURE R16 / R7 / R8 / R18: End-to-End Interconnected Marketplace Order,
 * Logistics, Invoice, Escrow, Warranty, Dispute & Double-Entry Ledger Seeder
 * ============================================================================
 * Original Ledger Author: Md. Nawshad Zaman Khan [Student ID: 23301622] (Commit 4742c9c)
 * Full Relational Graph Interconnection: Farhan Tanvir [Student ID: 22299179]
 *
 * Guarantees 100% end-to-end relational causality across every seeded order:
 *   - RecentlyViewed -> (Cart + CartItem + ServicePackage.order_count for Direct Orders)
 *   - RepairRequest (COMPLETED) -> Accepted Bid + Declined Competing Bid (for Marketplace)
 *   - RepairJob (COMPLETED) -> 90-Day Warranty (`warranties`)
 *   - Inbound TO_SHOP & Outbound TO_CUSTOMER DeliveryJobs + LocationLogs + RiderEarnings
 *     (strictly on pickup-capable shops, served by the matching city's approved rider)
 *   - PAID Invoice + InvoiceLineItems (linked to ShopService)
 *   - PAID PaymentTransaction + EscrowLedger (HOLD -> RELEASE)
 *   - FinancialLedger (CUSTOMER_PAYMENT, PLATFORM_COMMISSION, VENDOR_EARNING,
 *     DELIVERY_FEE, DELIVERY_PAYOUT, VENDOR_PAYOUT, REFUND)
 *   - 1 Resolved DisputeCase -> DisputeNote -> Refund -> REFUND Ledger entry
 * ============================================================================
 */
class FinancialLedgerSeeder extends Seeder
{
    public function run(): void
    {
        if (FinancialLedger::count() > 0) {
            return;
        }

        $customers = User::where('role', 'customer')
            ->where('is_active', true)
            ->orderBy('id')
            ->get()
            ->values();

        if ($customers->isEmpty()) {
            return;
        }

        $customerByEmail = $customers->keyBy('email');
        $defaultCustomer = $customerByEmail['customer@meramot.demo'] ?? $customers[0];

        $meramotAdmin = User::where('email', 'admin@meramot.demo')->first()
            ?? User::where('role', 'admin')->orderBy('id')->first();

        // Approved city-matched riders
        $dhakaRider = DeliveryAgent::with('user')
            ->whereHas('user', fn ($q) => $q->where('email', 'rider.demo@meramot.test'))
            ->first()
            ?? DeliveryAgent::with('user')
                ->where('registration_status', 'APPROVED')
                ->where('is_active', true)
                ->orderBy('id')
                ->first();

        $ctgRider = DeliveryAgent::with('user')
            ->whereHas('user', fn ($q) => $q->where('email', 'ctg.rider@meramot.test'))
            ->first()
            ?? $dhakaRider;

        $zones = DB::table('coverage_zones')->get();
        $zoneByCityArea = [];
        $defaultDhakaZoneId = null;
        $defaultCtgZoneId = null;
        foreach ($zones as $z) {
            $zoneByCityArea[$z->city . ':' . $z->area] = $z->id;
            if ($z->city === 'Dhaka' && !$defaultDhakaZoneId) {
                $defaultDhakaZoneId = $z->id;
            }
            if ($z->city === 'Chattogram' && !$defaultCtgZoneId) {
                $defaultCtgZoneId = $z->id;
            }
        }

        // Reset ServicePackage order_count so it reflects exact seeded Direct Orders
        ServicePackage::query()->update(['order_count' => 0]);
        $packagesByTitle = ServicePackage::all()->keyBy('title');

        $publicShops = RepairShop::where('is_active', true)
            ->where('is_public', true)
            ->orderBy('id')
            ->get();

        if ($publicShops->isEmpty()) {
            return;
        }

        $shopsBySlug = $publicShops->keyBy('slug');
        $mirazBranches = $publicShops->where('name', 'Miraz Telecom')->values();
        $vendorUserByProfileId = VendorProfile::pluck('user_id', 'id')->all();

        $primaryServiceByShopId = ShopService::where('is_active', true)
            ->orderBy('sort_order')
            ->get(['id', 'shop_id'])
            ->unique('shop_id')
            ->pluck('id', 'shop_id')
            ->all();

        $customerAddresses = [
            'Dhaka' => [
                'House 18, Road 11, Banani, Dhaka 1213',
                'Road 24, House 9, Gulshan 1, Dhaka 1212',
                'House 32, Road 15, Dhanmondi, Dhaka 1209',
                'Sector 7, Road 18, Uttara, Dhaka 1230',
                'Avenue 3, Mirpur DOHS, Dhaka 1216',
                'Block C, Road 5, Bashundhara R/A, Dhaka 1229',
                'DOHS Mohakhali, Road 4, Dhaka 1206',
                'Satmasjid Road, Dhanmondi, Dhaka 1209',
            ],
            'Chattogram' => [
                'CDA Avenue, Nasirabad, Chattogram 4000',
                'Agrabad Commercial Area, Road 3, Chattogram 4100',
                'Khulshi Hills Road 2, Chattogram 4225',
            ],
        ];

        $paymentMethods = ['SSLCOMMERZ', 'BKASH', 'NAGAD', 'CARD', 'CASH'];

        // 50 deterministic historical completed orders across flagship, multi-branch & curated hubs
        // Every (shop, customer) pair is strictly unique so each order maps 1:1 to a verified ShopReview!
        $orderBlueprints = [
            // --- ProTech Solutions [Banani DOHS Flagship] (7 completed orders: 6 visible reviews + 1 moderated review) ---
            ['slug' => 'protech-solutions', 'cust' => 'customer@meramot.demo', 'days' => 34, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'Apple', 'model' => 'iPhone 13 Pro', 'cat' => 'Screen / Display', 'title' => 'iPhone 13 Pro OLED Screen Replacement', 'parts' => 3700, 'labor' => 800, 'pm' => 'BKASH'],
            ['slug' => 'protech-solutions', 'cust' => 'nusrat.jahan@meramot.demo', 'days' => 29, 'source' => 'DIRECT_SERVICE', 'pkg' => 'Laptop High-Capacity Battery Replacement', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Air M2', 'cat' => 'Battery / Power', 'title' => 'Laptop High-Capacity Battery Replacement', 'parts' => 2400, 'labor' => 800, 'pm' => 'NAGAD'],
            ['slug' => 'protech-solutions', 'cust' => 'sadia.afrin@meramot.demo', 'days' => 25, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Pro 14 M1', 'cat' => 'Water Damage', 'title' => 'Thunderbolt Logic Board Short & Ultrasonic Bath', 'parts' => 4200, 'labor' => 1600, 'pm' => 'SSLCOMMERZ'],
            ['slug' => 'protech-solutions', 'cust' => 'mehedi.hasan@meramot.demo', 'days' => 21, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Pro 13 M2', 'cat' => 'Battery / Power', 'title' => 'MacBook Pro OEM Battery Cell & Cycle Calibration', 'parts' => 3900, 'labor' => 900, 'pm' => 'CARD'],
            ['slug' => 'protech-solutions', 'cust' => 'tahmid.chowdhury@meramot.demo', 'days' => 17, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Dell', 'model' => 'XPS 15 (9520)', 'cat' => 'Overheating / Fan', 'title' => 'Dell XPS Dual Cooling Fan & Vapor Chamber Repaste', 'parts' => 2100, 'labor' => 900, 'pm' => 'BKASH'],
            ['slug' => 'protech-solutions', 'cust' => 'tanvir.hasan@meramot.demo', 'days' => 12, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'Apple', 'model' => 'iPhone 14 Pro Max', 'cat' => 'Charging Port', 'title' => 'iPhone 14 Pro Max Charging Flex & Tristar IC Fix', 'parts' => 2600, 'labor' => 800, 'pm' => 'SSLCOMMERZ'],
            ['slug' => 'protech-solutions', 'cust' => 'imran.kabir@meramot.demo', 'days' => 9, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Pro 14', 'cat' => 'Screen / Display', 'title' => 'MacBook Backlight Driver IC Restoration', 'parts' => 3100, 'labor' => 1200, 'pm' => 'CARD'],

            // --- ProTech Solutions [Uttara Sector 7] (4 completed orders) ---
            ['slug' => 'protech-solutions-uttara', 'cust' => 'sadia.afrin@meramot.demo', 'days' => 27, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'HP', 'model' => 'Spectre x360', 'cat' => 'Motherboard / IC', 'title' => 'HP Spectre USB-C PD Controller IC Replacement', 'parts' => 2800, 'labor' => 1100, 'pm' => 'SSLCOMMERZ'],
            ['slug' => 'protech-solutions-uttara', 'cust' => 'mehedi.hasan@meramot.demo', 'days' => 20, 'source' => 'DIRECT_SERVICE', 'pkg' => 'Laptop High-Capacity Battery Replacement', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Air M1', 'cat' => 'Battery / Power', 'title' => 'Laptop High-Capacity Battery Replacement', 'parts' => 2400, 'labor' => 800, 'pm' => 'BKASH'],
            ['slug' => 'protech-solutions-uttara', 'cust' => 'tahmid.chowdhury@meramot.demo', 'days' => 14, 'source' => 'DIRECT_SERVICE', 'pkg' => 'Mechanical & Chiclet Keyboard Replacement', 'device' => 'Laptop', 'brand' => 'Lenovo', 'model' => 'Yoga Slim 7i', 'cat' => 'Keyboard', 'title' => 'Mechanical & Chiclet Keyboard Replacement', 'parts' => 1600, 'labor' => 600, 'pm' => 'NAGAD'],
            ['slug' => 'protech-solutions-uttara', 'cust' => 'tasnim.akter@meramot.demo', 'days' => 8, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'Samsung', 'model' => 'Galaxy S23+', 'cat' => 'Screen / Display', 'title' => 'Galaxy S23+ Dynamic AMOLED Calibration & Seal', 'parts' => 4400, 'labor' => 900, 'pm' => 'CARD'],

            // --- ProTech Solutions [Agrabad, Chattogram] (3 completed orders — Chattogram courier!) ---
            ['slug' => 'protech-solutions-agrabad', 'cust' => 'rafiul.alam@meramot.demo', 'days' => 24, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Lenovo', 'model' => 'ThinkPad X1 Carbon', 'cat' => 'Motherboard / IC', 'title' => 'ThinkPad X1 Carbon Power Rail Diagnostics & Repair', 'parts' => 3200, 'labor' => 1200, 'pm' => 'SSLCOMMERZ'],
            ['slug' => 'protech-solutions-agrabad', 'cust' => 'nusrat.jahan@meramot.demo', 'days' => 16, 'source' => 'DIRECT_SERVICE', 'pkg' => 'Original Display Panel Replacement', 'device' => 'Phone', 'brand' => 'Samsung', 'model' => 'Galaxy S22', 'cat' => 'Screen / Display', 'title' => 'Original Display Panel Replacement', 'parts' => 2100, 'labor' => 700, 'pm' => 'BKASH'],
            ['slug' => 'protech-solutions-agrabad', 'cust' => 'farzana.yasmin@meramot.demo', 'days' => 10, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Pro 13', 'cat' => 'SSD / Storage', 'title' => 'Corporate MacBook Controller & Thermal Service', 'parts' => 2200, 'labor' => 800, 'pm' => 'CARD'],

            // --- Dhaka Device Doctors (5 completed orders) ---
            ['slug' => 'dhaka-device-doctors', 'cust' => 'rafiul.alam@meramot.demo', 'days' => 26, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Dell', 'model' => 'XPS 13 (9315)', 'cat' => 'Motherboard / IC', 'title' => 'Dell XPS 13 Motherboard Power IC & Trackpad Repair', 'parts' => 4700, 'labor' => 1500, 'pm' => 'SSLCOMMERZ'],
            ['slug' => 'dhaka-device-doctors', 'cust' => 'customer@meramot.demo', 'days' => 20, 'source' => 'DIRECT_SERVICE', 'pkg' => 'Motherboard Micro-Soldering & IC Diagnostic', 'device' => 'Laptop', 'brand' => 'ASUS', 'model' => 'ZenBook 14 OLED', 'cat' => 'Motherboard / IC', 'title' => 'Motherboard Micro-Soldering & IC Diagnostic', 'parts' => 3100, 'labor' => 1400, 'pm' => 'BKASH'],
            ['slug' => 'dhaka-device-doctors', 'cust' => 'nusrat.jahan@meramot.demo', 'days' => 15, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'Google', 'model' => 'Pixel 7 Pro', 'cat' => 'Motherboard / IC', 'title' => 'Pixel 7 Pro Baseband PMIC Re-Balling', 'parts' => 2900, 'labor' => 1200, 'pm' => 'NAGAD'],
            ['slug' => 'dhaka-device-doctors', 'cust' => 'mahmudul.karim@meramot.demo', 'days' => 11, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'ASUS', 'model' => 'TUF Gaming A15', 'cat' => 'Motherboard / IC', 'title' => 'ASUS TUF Charging MOSFET & VRM Rail Replacement', 'parts' => 2600, 'labor' => 1100, 'pm' => 'CARD'],
            ['slug' => 'dhaka-device-doctors', 'cust' => 'imran.kabir@meramot.demo', 'days' => 5, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'MSI', 'model' => 'Modern 15', 'cat' => 'Motherboard / IC', 'title' => 'MSI Modern Embedded Controller BGA Rework', 'parts' => 2800, 'labor' => 1200, 'pm' => 'SSLCOMMERZ'],

            // --- The Gadget Den (4 walk-in completed orders) ---
            ['slug' => 'the-gadget-den', 'cust' => 'tanvir.hasan@meramot.demo', 'days' => 28, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'Samsung', 'model' => 'Galaxy S22 Ultra', 'cat' => 'Battery / Power', 'title' => 'Samsung S22 Ultra Battery Replacement', 'parts' => 2000, 'labor' => 800, 'pm' => 'SSLCOMMERZ'],
            ['slug' => 'the-gadget-den', 'cust' => 'customer@meramot.demo', 'days' => 22, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'HP', 'model' => 'Envy 13', 'cat' => 'Keyboard', 'title' => 'HP Envy Backlit Keyboard Matrix Replacement', 'parts' => 1800, 'labor' => 800, 'pm' => 'CARD'],
            ['slug' => 'the-gadget-den', 'cust' => 'sadia.afrin@meramot.demo', 'days' => 15, 'source' => 'MARKETPLACE', 'device' => 'Smartwatch', 'brand' => 'Apple', 'model' => 'Watch Series 8', 'cat' => 'Screen / Display', 'title' => 'Apple Watch Series 8 Front Glass Lamination', 'parts' => 2200, 'labor' => 900, 'pm' => 'BKASH'],
            ['slug' => 'the-gadget-den', 'cust' => 'mehedi.hasan@meramot.demo', 'days' => 7, 'source' => 'MARKETPLACE', 'device' => 'Tablet', 'brand' => 'Apple', 'model' => 'iPad Air 5', 'cat' => 'Screen / Display', 'title' => 'iPad Air 5 Touch Digitizer & Frame Alignment', 'parts' => 3400, 'labor' => 1000, 'pm' => 'CASH'],

            // --- Budget Fixer (4 walk-in completed orders) ---
            ['slug' => 'budget-fixer', 'cust' => 'mahmudul.karim@meramot.demo', 'days' => 25, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Lenovo', 'model' => 'IdeaPad Slim 5', 'cat' => 'Screen / Display', 'title' => 'Lenovo IdeaPad Display Ribbon & Hinge Fix', 'parts' => 750, 'labor' => 450, 'pm' => 'CASH'],
            ['slug' => 'budget-fixer', 'cust' => 'tasnim.akter@meramot.demo', 'days' => 19, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'Xiaomi', 'model' => 'Redmi Note 12', 'cat' => 'Screen / Display', 'title' => 'Redmi Note 12 Replacement IPS Display Swap', 'parts' => 1100, 'labor' => 400, 'pm' => 'BKASH'],
            ['slug' => 'budget-fixer', 'cust' => 'imran.kabir@meramot.demo', 'days' => 13, 'source' => 'DIRECT_SERVICE', 'pkg' => 'Gaming Console Thermal Overhaul & Fan Deep Clean', 'device' => 'Gaming Console', 'brand' => 'Sony', 'model' => 'PlayStation 4 Slim', 'cat' => 'Overheating / Fan', 'title' => 'Gaming Console Thermal Overhaul & Fan Deep Clean', 'parts' => 1200, 'labor' => 600, 'pm' => 'NAGAD'],
            ['slug' => 'budget-fixer', 'cust' => 'rafiul.alam@meramot.demo', 'days' => 6, 'source' => 'MARKETPLACE', 'device' => 'Printer', 'brand' => 'Canon', 'model' => 'PIXMA G2020', 'cat' => 'Mechanical Servicing', 'title' => 'Canon PIXMA Paper Feed Roller & Ink Pad Reset', 'parts' => 650, 'labor' => 350, 'pm' => 'CASH'],

            // --- MacMaster Repairs (4 walk-in completed orders) ---
            ['slug' => 'macmaster-repairs', 'cust' => 'farzana.yasmin@meramot.demo', 'days' => 27, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Pro 16 M1 Pro', 'cat' => 'Overheating / Fan', 'title' => 'MacBook Pro M1 Thermal Repasting & Fan Repair', 'parts' => 6500, 'labor' => 2000, 'pm' => 'CARD'],
            ['slug' => 'macmaster-repairs', 'cust' => 'customer@meramot.demo', 'days' => 21, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'iMac 24 M1', 'cat' => 'Motherboard / IC', 'title' => 'iMac 24 Power Supply Rail Restoration', 'parts' => 4100, 'labor' => 1400, 'pm' => 'SSLCOMMERZ'],
            ['slug' => 'macmaster-repairs', 'cust' => 'sadia.afrin@meramot.demo', 'days' => 14, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Air M2', 'cat' => 'Screen / Display', 'title' => 'MacBook Air M2 True Tone Display Swap', 'parts' => 5800, 'labor' => 1400, 'pm' => 'CARD'],
            ['slug' => 'macmaster-repairs', 'cust' => 'tahmid.chowdhury@meramot.demo', 'days' => 8, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Pro 14', 'cat' => 'Charging Port', 'title' => 'MagSafe 3 I/O Board & Ultrasonic Contact Service', 'parts' => 1900, 'labor' => 700, 'pm' => 'BKASH'],

            // --- The iDoc Garage (3 courier completed orders) ---
            ['slug' => 'the-idoc-garage', 'cust' => 'farzana.yasmin@meramot.demo', 'days' => 23, 'source' => 'DIRECT_SERVICE', 'pkg' => 'Original Display Panel Replacement', 'device' => 'Phone', 'brand' => 'Apple', 'model' => 'iPhone 13', 'cat' => 'Screen / Display', 'title' => 'Original Display Panel Replacement', 'parts' => 2100, 'labor' => 700, 'pm' => 'SSLCOMMERZ'],
            ['slug' => 'the-idoc-garage', 'cust' => 'mahmudul.karim@meramot.demo', 'days' => 16, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Air M1', 'cat' => 'Water Damage', 'title' => 'MacBook Air Trackpad Flex & Spill Recovery', 'parts' => 2500, 'labor' => 900, 'pm' => 'BKASH'],
            ['slug' => 'the-idoc-garage', 'cust' => 'tasnim.akter@meramot.demo', 'days' => 9, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'Apple', 'model' => 'iPhone 12 Pro', 'cat' => 'Battery / Power', 'title' => 'iPhone 12 Pro Zero-Cycle Battery Swap', 'parts' => 1900, 'labor' => 700, 'pm' => 'NAGAD'],

            // --- ByteSize Repairs (3 walk-in completed orders) ---
            ['slug' => 'bytesize-repairs', 'cust' => 'tanvir.hasan@meramot.demo', 'days' => 24, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'OnePlus', 'model' => 'OnePlus 11R', 'cat' => 'Screen / Display', 'title' => 'OnePlus 11R Express AMOLED Panel Swap', 'parts' => 3100, 'labor' => 800, 'pm' => 'CARD'],
            ['slug' => 'bytesize-repairs', 'cust' => 'nusrat.jahan@meramot.demo', 'days' => 15, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Acer', 'model' => 'Swift 3', 'cat' => 'Screen / Display', 'title' => 'Acer Swift 3 FHD IPS Panel Replacement', 'parts' => 3400, 'labor' => 800, 'pm' => 'BKASH'],
            ['slug' => 'bytesize-repairs', 'cust' => 'imran.kabir@meramot.demo', 'days' => 7, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'HP', 'model' => 'Pavilion 15', 'cat' => 'Keyboard', 'title' => 'HP Pavilion Keyboard Ribbon & Top-Case Service', 'parts' => 1500, 'labor' => 700, 'pm' => 'CASH'],

            // --- Gulshan Gadget Care (3 courier completed orders) ---
            ['slug' => 'gulshan-gadget-care', 'cust' => 'farzana.yasmin@meramot.demo', 'days' => 22, 'source' => 'DIRECT_SERVICE', 'pkg' => 'Type-C / Lightning Port Microsoldering Fix', 'device' => 'Phone', 'brand' => 'Samsung', 'model' => 'Galaxy Z Flip 5', 'cat' => 'Charging Port', 'title' => 'Type-C / Lightning Port Microsoldering Fix', 'parts' => 800, 'labor' => 400, 'pm' => 'SSLCOMMERZ'],
            ['slug' => 'gulshan-gadget-care', 'cust' => 'tahmid.chowdhury@meramot.demo', 'days' => 14, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Dell', 'model' => 'XPS 13 Plus', 'cat' => 'Water Damage', 'title' => 'Dell XPS Haptic Row & Liquid Corrosion Cleanup', 'parts' => 3600, 'labor' => 1200, 'pm' => 'CARD'],
            ['slug' => 'gulshan-gadget-care', 'cust' => 'sadia.afrin@meramot.demo', 'days' => 6, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'Apple', 'model' => 'iPhone 15 Pro', 'cat' => 'Screen / Display', 'title' => 'iPhone 15 Pro Rear Glass & MagSafe Coil Fitment', 'parts' => 3200, 'labor' => 1100, 'pm' => 'BKASH'],

            // --- 8 Additional Curated Metro Hubs (1 completed order each) ---
            ['slug' => 'applecore-solutions', 'cust' => 'mehedi.hasan@meramot.demo', 'days' => 18, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Apple', 'model' => 'MacBook Air M1', 'cat' => 'Motherboard / IC', 'title' => 'MacBook Air Audio Board & Sleep Sensor Fix', 'parts' => 1800, 'labor' => 800, 'pm' => 'BKASH'],
            ['slug' => 'main-street-tech', 'cust' => 'mahmudul.karim@meramot.demo', 'days' => 17, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'Lenovo', 'model' => 'ThinkBook 15', 'cat' => 'Charging Port', 'title' => 'Lenovo ThinkBook USB-C Port & Trackpad Service', 'parts' => 1200, 'labor' => 500, 'pm' => 'CASH'],
            ['slug' => 'precision-fix', 'cust' => 'tasnim.akter@meramot.demo', 'days' => 16, 'source' => 'MARKETPLACE', 'device' => 'Laptop', 'brand' => 'HP', 'model' => 'ProBook 450 G8', 'cat' => 'Battery / Power', 'title' => 'HP ProBook Battery Cell Replacement & Calibration', 'parts' => 2100, 'labor' => 500, 'pm' => 'NAGAD'],
            ['slug' => 'mirpur-laptop-lab', 'cust' => 'rafiul.alam@meramot.demo', 'days' => 15, 'source' => 'DIRECT_SERVICE', 'pkg' => 'Mechanical & Chiclet Keyboard Replacement', 'device' => 'Laptop', 'brand' => 'ASUS', 'model' => 'VivoBook 15', 'cat' => 'Keyboard', 'title' => 'Mechanical & Chiclet Keyboard Replacement', 'parts' => 1600, 'labor' => 600, 'pm' => 'BKASH'],
            ['slug' => 'uttara-fixpoint', 'cust' => 'tanvir.hasan@meramot.demo', 'days' => 13, 'source' => 'MARKETPLACE', 'device' => 'Tablet', 'brand' => 'Samsung', 'model' => 'Galaxy Tab S8', 'cat' => 'Charging Port', 'title' => 'Galaxy Tab S8 Fast-Charge USB-C Port Replacement', 'parts' => 1300, 'labor' => 500, 'pm' => 'SSLCOMMERZ'],
            ['slug' => 'bashundhara-laptop-lounge', 'cust' => 'imran.kabir@meramot.demo', 'days' => 11, 'source' => 'DIRECT_SERVICE', 'pkg' => 'Gaming Console Thermal Overhaul & Fan Deep Clean', 'device' => 'Gaming Console', 'brand' => 'Sony', 'model' => 'PlayStation 5', 'cat' => 'Overheating / Fan', 'title' => 'Gaming Console Thermal Overhaul & Fan Deep Clean', 'parts' => 1200, 'labor' => 600, 'pm' => 'BKASH'],
            ['slug' => 'farmgate-tech-rescue', 'cust' => 'farzana.yasmin@meramot.demo', 'days' => 10, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'Realme', 'model' => 'Realme GT Neo 3', 'cat' => 'Battery / Power', 'title' => 'Express Dual-Cell Battery Swap', 'parts' => 1500, 'labor' => 450, 'pm' => 'CASH'],
            ['slug' => 'pixel-repair-station', 'cust' => 'nusrat.jahan@meramot.demo', 'days' => 8, 'source' => 'MARKETPLACE', 'device' => 'Phone', 'brand' => 'Google', 'model' => 'Pixel 8', 'cat' => 'Screen / Display', 'title' => 'Pixel 8 Cleanroom OLED Glass Bonding', 'parts' => 2800, 'labor' => 900, 'pm' => 'CARD'],
        ];

        // Also connect both OpenStreetMap multi-branch storefronts of Miraz Telecom (Hatirpool, Dhaka)
        foreach ($mirazBranches as $bIdx => $mirazShop) {
            $orderBlueprints[] = [
                'slug' => $mirazShop->slug,
                'cust' => $bIdx === 0 ? 'tanvir.hasan@meramot.demo' : 'mahmudul.karim@meramot.demo',
                'days' => 12 - ($bIdx * 3),
                'source' => 'MARKETPLACE',
                'device' => 'Phone',
                'brand' => 'Samsung',
                'model' => $bIdx === 0 ? 'Galaxy A54 5G' : 'Galaxy M34',
                'cat' => 'Screen / Display',
                'title' => "Smartphone Display & Charging Sub-Board Service ({$mirazShop->branch_name})",
                'parts' => 1600,
                'labor' => 500,
                'pm' => $paymentMethods[$bIdx % count($paymentMethods)],
            ];
        }

        $dlvSeq = 1001;

        foreach ($orderBlueprints as $idx => $bp) {
            $shop = $shopsBySlug[$bp['slug']] ?? null;
            if (!$shop) {
                continue;
            }

            $customer = $customerByEmail[$bp['cust']] ?? $defaultCustomer;
            $pkg = !empty($bp['pkg']) ? ($packagesByTitle[$bp['pkg']] ?? null) : null;

            $partsCost = (float) $bp['parts'];
            $laborCost = (float) $bp['labor'];
            $gross = $pkg ? (float) $pkg->fixed_price : ($partsCost + $laborCost);
            if ($pkg && ($partsCost + $laborCost) != $gross) {
                $partsCost = round($gross * 0.72, 2);
                $laborCost = round($gross - $partsCost, 2);
            }

            $commission = round($gross * 0.10, 2);
            $vendorNet = round($gross - $commission, 2);

            $cityKey = $shop->city === 'Chattogram' ? 'Chattogram' : 'Dhaka';
            $rider = $cityKey === 'Chattogram' ? $ctgRider : $dhakaRider;
            $usesCourier = (bool) $shop->supports_pickup && $rider !== null;
            $deliveryFee = $usesCourier ? 150.00 : 0.00;

            $addrPool = $customerAddresses[$cityKey];
            $custAddress = $addrPool[$idx % count($addrPool)];

            // Strict real-world chronological progression:
            // Viewed Shop (T-20m) -> Request Created (T0) -> Accepted (T+2h) -> Inbound Pickup (T+3h..T+4h) ->
            // Bench Started (T+5h) -> Completed (T+26h) -> Outbound Return & Paid/Settled (T+27h)
            $tCreated = now()->subDays((int) $bp['days'])->setTime(10, 15, 0);
            $tApproved = (clone $tCreated)->addHours(2);
            $tStarted = (clone $tCreated)->addHours(5);
            $tCompleted = (clone $tCreated)->addHours(26);
            $tPaid = (clone $tCreated)->addHours(27);

            $shopDisplayName = ($shop->is_branch && $shop->branch_name)
                ? "{$shop->name} [{$shop->branch_name}]"
                : $shop->name;

            $primarySvcId = $primaryServiceByShopId[$shop->id] ?? null;

            // 1. Customer viewed shop before placing order (`recently_viewed`)
            DB::table('recently_viewed')->insert([
                'user_id' => $customer->id,
                'shop_id' => $shop->id,
                'viewed_at' => (clone $tCreated)->subMinutes(20),
                'created_at' => (clone $tCreated)->subMinutes(20),
                'updated_at' => (clone $tCreated)->subMinutes(20),
            ]);

            // 2. If Direct Service order, persist CHECKED_OUT Cart + CartItem & increment ServicePackage.order_count
            if ($bp['source'] === 'DIRECT_SERVICE') {
                $checkedOutCart = Cart::create([
                    'user_id' => $customer->id,
                    'shop_id' => $shop->id,
                    'status' => 'CHECKED_OUT',
                    'created_at' => (clone $tCreated)->subMinutes(10),
                    'updated_at' => $tCreated,
                ]);

                CartItem::create([
                    'cart_id' => $checkedOutCart->id,
                    'service_name' => $pkg ? $pkg->title : $bp['title'],
                    'description' => "Direct-Order fixed package at {$shopDisplayName}",
                    'price' => $gross,
                    'quantity' => 1,
                    'metadata' => [
                        'package_id' => $pkg?->id,
                        'source' => 'QUICKMATCH_PACKAGE',
                    ],
                    'created_at' => (clone $tCreated)->subMinutes(10),
                    'updated_at' => $tCreated,
                ]);

                if ($pkg) {
                    $pkg->increment('order_count');
                }
            }

            // 3. Create COMPLETED RepairRequest
            $repairRequest = RepairRequest::create([
                'customer_id' => $customer->id,
                'source' => $bp['source'],
                'requested_shop_id' => $shop->id,
                'requested_service_id' => $pkg ? $pkg->id : $primarySvcId,
                'title' => $bp['title'],
                'description' => "Verified completed repair order for {$bp['brand']} {$bp['model']} at {$shopDisplayName}.",
                'device_type' => $bp['device'],
                'brand' => $bp['brand'],
                'model_name' => $bp['model'],
                'issue_category' => $bp['cat'],
                'problem' => "{$bp['title']} — completed with genuine replacement components and bench QC warranty.",
                'mode' => $bp['source'] === 'DIRECT_SERVICE' ? 'DIRECT_REPAIR' : 'CHECKUP_AND_REPAIR',
                'status' => 'COMPLETED',
                'preferred_pickup' => $usesCourier,
                'preferred_service_type' => $usesCourier ? 'pickup_dropoff' : 'walk_in',
                'delivery_type' => $usesCourier ? 'REGULAR' : null,
                'contact_phone' => $customer->phone ?? '+8801700000000',
                'pickup_address' => $usesCourier ? $custAddress : null,
                'dropoff_address' => $usesCourier ? $custAddress : $shop->public_address,
                'quoted_final_amount' => $gross,
                'approved_at' => $tApproved,
                'created_at' => $tCreated,
                'updated_at' => $tPaid,
            ]);

            // 4. Marketplace Bids (Accepted winning bid + 1 Declined competing bid)
            $acceptedBid = null;
            if ($bp['source'] === 'MARKETPLACE') {
                $acceptedBid = Bid::create([
                    'repair_request_id' => $repairRequest->id,
                    'shop_id' => $shop->id,
                    'parts_cost' => $partsCost,
                    'labor_cost' => $laborCost,
                    'proposed_cost' => $gross,
                    'estimated_days' => 1,
                    'warranty_days' => 90,
                    'line_items' => [
                        ['item' => 'Replacement Hardware Components', 'cost' => $partsCost],
                        ['item' => 'Certified Bench Diagnostics & Labor', 'cost' => $laborCost],
                    ],
                    'technician_note' => "Bench-tested replacement and calibration for {$bp['brand']} {$bp['model']}.",
                    'status' => 'ACCEPTED',
                    'created_at' => $tApproved,
                    'updated_at' => $tApproved,
                ]);

                $repairRequest->update(['selected_bid_id' => $acceptedBid->id]);

                $competitor = $publicShops->first(fn ($s) => $s->id !== $shop->id && $s->city === $shop->city)
                    ?? $publicShops->first(fn ($s) => $s->id !== $shop->id);

                if ($competitor) {
                    Bid::create([
                        'repair_request_id' => $repairRequest->id,
                        'shop_id' => $competitor->id,
                        'parts_cost' => $partsCost + 300,
                        'labor_cost' => $laborCost + 150,
                        'proposed_cost' => $gross + 450,
                        'estimated_days' => 2,
                        'warranty_days' => 60,
                        'technician_note' => "Standard bench quote for {$bp['brand']} {$bp['model']}.",
                        'status' => 'DECLINED',
                        'created_at' => (clone $tApproved)->subMinutes(25),
                        'updated_at' => $tApproved,
                    ]);
                }
            }

            // 5. Create COMPLETED RepairJob + 1:1 90-Day Warranty
            $repairJob = RepairJob::create([
                'repair_request_id' => $repairRequest->id,
                'shop_id' => $shop->id,
                'accepted_bid_id' => $acceptedBid?->id,
                'status' => 'COMPLETED',
                'diagnosis_notes' => "Completed {$bp['title']} and passed 12-point post-repair QC verification.",
                'final_quoted_amount' => $gross,
                'final_quote_items' => [
                    ['title' => 'OEM Replacement Components', 'amount' => $partsCost],
                    ['title' => 'Certified Technician Labor & QC', 'amount' => $laborCost],
                ],
                'customer_approved' => true,
                'started_at' => $tStarted,
                'completed_at' => $tCompleted,
                'created_at' => $tApproved,
                'updated_at' => $tCompleted,
            ]);

            $warrantyDays = $pkg ? ($pkg->warranty_months * 30) : 90;
            DB::table('warranties')->insert([
                'repair_job_id' => $repairJob->id,
                'title' => "{$warrantyDays}-Day Meramot Verified Repair Warranty",
                'details' => "Covers {$bp['title']} parts & technician workmanship at {$shopDisplayName}.",
                'duration_days' => $warrantyDays,
                'starts_at' => $tCompleted,
                'ends_at' => (clone $tCompleted)->addDays($warrantyDays),
                'created_at' => $tCompleted,
                'updated_at' => $tCompleted,
            ]);

            // 6. PAID Invoice + InvoiceLineItems (linked to ShopService)
            $invoiceNo = 'INV-2026-' . (1001 + $idx);
            $trxRef = 'TRX-MRM-' . (20261001 + $idx);

            $invoiceId = DB::table('invoices')->insertGetId([
                'repair_request_id' => $repairRequest->id,
                'repair_job_id' => $repairJob->id,
                'shop_id' => $shop->id,
                'type' => 'FINAL',
                'status' => 'PAID',
                'invoice_number' => $invoiceNo,
                'subtotal_amount' => $gross,
                'delivery_amount' => $deliveryFee,
                'discount_amount' => 0.00,
                'commission_amount' => $commission,
                'total_amount' => $gross + $deliveryFee,
                'issued_at' => $tCompleted,
                'due_at' => $tPaid,
                'paid_at' => $tPaid,
                'created_at' => $tCompleted,
                'updated_at' => $tPaid,
            ]);

            DB::table('invoice_line_items')->insert([
                [
                    'invoice_id' => $invoiceId,
                    'shop_service_id' => $primarySvcId,
                    'title' => "{$bp['title']} — Replacement Components",
                    'description' => "Verified hardware parts for {$bp['brand']} {$bp['model']}",
                    'quantity' => 1,
                    'unit_price' => $partsCost,
                    'total_price' => $partsCost,
                    'created_at' => $tCompleted,
                    'updated_at' => $tCompleted,
                ],
                [
                    'invoice_id' => $invoiceId,
                    'shop_service_id' => $primarySvcId,
                    'title' => 'Certified Bench Labor & Calibration',
                    'description' => "Diagnostic, reassembly & QC warranty validation at {$shopDisplayName}",
                    'quantity' => 1,
                    'unit_price' => $laborCost,
                    'total_price' => $laborCost,
                    'created_at' => $tCompleted,
                    'updated_at' => $tCompleted,
                ],
            ]);

            // 7. PAID PaymentTransaction + EscrowLedger (HOLD -> RELEASE)
            $payment = PaymentTransaction::create([
                'user_id' => $customer->id,
                'repair_request_id' => $repairRequest->id,
                'invoice_id' => $invoiceId,
                'amount' => $gross + $deliveryFee,
                'currency' => 'BDT',
                'payment_method' => $bp['pm'],
                'status' => 'PAID',
                'transaction_reference' => $trxRef,
                'gateway_response' => json_encode(['status' => 'VALIDATED', 'invoice' => $invoiceNo]),
                'paid_at' => $tPaid,
                'created_at' => $tPaid,
                'updated_at' => $tPaid,
            ]);

            $vendorUserId = $vendorUserByProfileId[$shop->vendor_profile_id] ?? null;
            DB::table('escrow_ledgers')->insert([
                [
                    'payment_id' => $payment->id,
                    'repair_request_id' => $repairRequest->id,
                    'customer_user_id' => $customer->id,
                    'vendor_user_id' => $vendorUserId,
                    'amount' => $gross,
                    'gross_amount' => $gross,
                    'platform_commission_amount' => $commission,
                    'vendor_net_amount' => $vendorNet,
                    'action' => 'HOLD',
                    'note' => "Escrow hold authorized for {$invoiceNo}",
                    'created_at' => $tApproved,
                    'updated_at' => $tApproved,
                ],
                [
                    'payment_id' => $payment->id,
                    'repair_request_id' => $repairRequest->id,
                    'customer_user_id' => $customer->id,
                    'vendor_user_id' => $vendorUserId,
                    'amount' => $gross,
                    'gross_amount' => $gross,
                    'platform_commission_amount' => $commission,
                    'vendor_net_amount' => $vendorNet,
                    'action' => 'RELEASE',
                    'note' => "Escrow released upon verified completion of {$invoiceNo}",
                    'created_at' => $tPaid,
                    'updated_at' => $tPaid,
                ],
            ]);

            // 8. Immutable Double-Entry FinancialLedger Rows
            FinancialLedger::create([
                'shop_id' => $shop->id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoiceId,
                'entry_type' => 'CUSTOMER_PAYMENT',
                'direction' => 'CREDIT',
                'amount' => $gross,
                'currency' => 'BDT',
                'description' => "Customer payment received: {$bp['title']} ({$shopDisplayName})",
                'effective_at' => $tPaid,
            ]);

            FinancialLedger::create([
                'shop_id' => $shop->id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoiceId,
                'entry_type' => 'PLATFORM_COMMISSION',
                'direction' => 'CREDIT',
                'amount' => $commission,
                'currency' => 'BDT',
                'description' => "10% platform commission retained on {$invoiceNo} (Gross: ৳{$gross})",
                'effective_at' => (clone $tPaid)->addMinute(),
            ]);

            FinancialLedger::create([
                'shop_id' => $shop->id,
                'payment_id' => $payment->id,
                'invoice_id' => $invoiceId,
                'entry_type' => 'VENDOR_EARNING',
                'direction' => 'DEBIT',
                'amount' => $vendorNet,
                'currency' => 'BDT',
                'description' => "Net earning allocated to {$shopDisplayName} (90% of ৳{$gross})",
                'effective_at' => (clone $tPaid)->addMinutes(2),
            ]);

            // 9. Round-Trip Doorstep Courier Jobs (TO_SHOP + TO_CUSTOMER) + LocationLogs + RiderEarnings + Ledger
            if ($usesCourier) {
                $zoneId = $zoneByCityArea[$shop->city . ':' . $shop->area]
                    ?? ($cityKey === 'Chattogram' ? $defaultCtgZoneId : $defaultDhakaZoneId);

                $shopLat = (float) ($shop->latitude ?: ($cityKey === 'Chattogram' ? 22.3264 : 23.7904));
                $shopLng = (float) ($shop->longitude ?: ($cityKey === 'Chattogram' ? 91.8155 : 90.3980));

                $inboundJob = DeliveryJob::create([
                    'repair_job_id' => $repairJob->id,
                    'repair_request_id' => $repairRequest->id,
                    'delivery_agent_id' => $rider->id,
                    'coverage_zone_id' => $zoneId,
                    'direction' => 'TO_SHOP',
                    'type' => 'REGULAR',
                    'status' => 'DELIVERED',
                    'tracking_code' => 'MRM-DLV-' . ($dlvSeq++),
                    'rider_name' => $rider->user?->name ?? 'Meramot Courier',
                    'rider_phone' => $rider->user?->phone ?? '+8801799998888',
                    'pickup_address' => $custAddress,
                    'dropoff_address' => $shop->public_address,
                    'pickup_otp' => (string) (3100 + $idx),
                    'dropoff_otp' => (string) (5200 + $idx),
                    'distance_km' => 4.20,
                    'fee' => 75.00,
                    'scheduled_at' => (clone $tApproved)->addMinutes(30),
                    'dispatched_at' => (clone $tApproved)->addMinutes(45),
                    'picked_up_at' => (clone $tApproved)->addHours(1),
                    'delivered_at' => (clone $tApproved)->addHours(2),
                    'created_at' => $tApproved,
                    'updated_at' => (clone $tApproved)->addHours(2),
                ]);

                LocationLog::create([
                    'delivery_agent_id' => $rider->id,
                    'delivery_job_id' => $inboundJob->id,
                    'latitude' => $shopLat,
                    'longitude' => $shopLng,
                    'recorded_at' => (clone $tApproved)->addHours(2),
                ]);

                RiderEarnings::create([
                    'delivery_agent_id' => $rider->id,
                    'delivery_job_id' => $inboundJob->id,
                    'trip_fee' => 75.00,
                    'bonus_amount' => 0.00,
                    'total_earned' => 75.00,
                    'payout_status' => 'settled',
                    'payout_reference' => 'RDR-STL-' . $inboundJob->id,
                    'created_at' => $tPaid,
                    'updated_at' => $tPaid,
                ]);

                $outboundJob = DeliveryJob::create([
                    'repair_job_id' => $repairJob->id,
                    'repair_request_id' => $repairRequest->id,
                    'delivery_agent_id' => $rider->id,
                    'coverage_zone_id' => $zoneId,
                    'direction' => 'TO_CUSTOMER',
                    'type' => 'REGULAR',
                    'status' => 'DELIVERED',
                    'tracking_code' => 'MRM-DLV-' . ($dlvSeq++),
                    'rider_name' => $rider->user?->name ?? 'Meramot Courier',
                    'rider_phone' => $rider->user?->phone ?? '+8801799998888',
                    'pickup_address' => $shop->public_address,
                    'dropoff_address' => $custAddress,
                    'pickup_otp' => (string) (6300 + $idx),
                    'dropoff_otp' => (string) (8400 + $idx),
                    'distance_km' => 4.20,
                    'fee' => 75.00,
                    'scheduled_at' => $tCompleted,
                    'dispatched_at' => (clone $tCompleted)->addMinutes(15),
                    'picked_up_at' => (clone $tCompleted)->addMinutes(30),
                    'delivered_at' => $tPaid,
                    'created_at' => $tCompleted,
                    'updated_at' => $tPaid,
                ]);

                LocationLog::create([
                    'delivery_agent_id' => $rider->id,
                    'delivery_job_id' => $outboundJob->id,
                    'latitude' => round($shopLat + 0.0042, 8),
                    'longitude' => round($shopLng + 0.0035, 8),
                    'recorded_at' => $tPaid,
                ]);

                RiderEarnings::create([
                    'delivery_agent_id' => $rider->id,
                    'delivery_job_id' => $outboundJob->id,
                    'trip_fee' => 75.00,
                    'bonus_amount' => 0.00,
                    'total_earned' => 75.00,
                    'payout_status' => 'settled',
                    'payout_reference' => 'RDR-STL-' . $outboundJob->id,
                    'created_at' => $tPaid,
                    'updated_at' => $tPaid,
                ]);

                FinancialLedger::create([
                    'shop_id' => $shop->id,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                    'entry_type' => 'DELIVERY_FEE',
                    'direction' => 'CREDIT',
                    'amount' => 150.00,
                    'currency' => 'BDT',
                    'description' => "Round-trip courier pickup & return fee collected ({$inboundJob->tracking_code} / {$outboundJob->tracking_code})",
                    'effective_at' => (clone $tPaid)->addMinutes(3),
                ]);

                FinancialLedger::create([
                    'shop_id' => $shop->id,
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoiceId,
                    'entry_type' => 'DELIVERY_PAYOUT',
                    'direction' => 'DEBIT',
                    'amount' => 150.00,
                    'currency' => 'BDT',
                    'description' => "Round-trip courier payout settled to {$inboundJob->rider_name}",
                    'effective_at' => (clone $tPaid)->addMinutes(4),
                ]);
            }
        }

        // ---------------------------------------------------------------------
        // 10. Interconnected Resolved Dispute & Full Escrow Refund Case
        //     (`dispute_cases` -> `dispute_notes` -> `refunds` -> `REFUND` Ledger)
        // ---------------------------------------------------------------------
        $gearLab = $shopsBySlug['the-gear-lab'] ?? $publicShops->first();
        if ($gearLab && $meramotAdmin) {
            $tDispute = now()->subDays(6)->setTime(11, 0, 0);
            $gearOwnerId = $vendorUserByProfileId[$gearLab->vendor_profile_id] ?? $meramotAdmin->id;

            $cancelledReq = RepairRequest::create([
                'customer_id' => $defaultCustomer->id,
                'source' => 'MARKETPLACE',
                'requested_shop_id' => $gearLab->id,
                'title' => 'Severely Saltwater-Corroded GoPro Hero 11 Board Checkup',
                'description' => 'Submerged action camera submitted for board recovery; PCB layers delaminated beyond repair.',
                'device_type' => 'Camera',
                'brand' => 'GoPro',
                'model_name' => 'Hero 11 Black',
                'issue_category' => 'Water Damage',
                'problem' => 'Unrepairable multi-layer PCB delamination; escrow checkup deposit refunded in full.',
                'mode' => 'CHECKUP_ONLY',
                'status' => 'CANCELLED',
                'preferred_pickup' => false,
                'preferred_service_type' => 'walk_in',
                'contact_phone' => $defaultCustomer->phone ?? '+8801700000000',
                'dropoff_address' => $gearLab->public_address,
                'checkup_fee' => 500.00,
                'quoted_final_amount' => 500.00,
                'rejected_at' => (clone $tDispute)->addHours(8),
                'created_at' => $tDispute,
                'updated_at' => (clone $tDispute)->addHours(10),
            ]);

            $cancelledJob = RepairJob::create([
                'repair_request_id' => $cancelledReq->id,
                'shop_id' => $gearLab->id,
                'status' => 'CANCELLED',
                'diagnosis_notes' => 'Main SoC pads corroded through inner copper layer; advised full deposit refund.',
                'final_quoted_amount' => 500.00,
                'customer_approved' => false,
                'started_at' => (clone $tDispute)->addHours(2),
                'created_at' => (clone $tDispute)->addHours(1),
                'updated_at' => (clone $tDispute)->addHours(8),
            ]);

            $refundInvoiceId = DB::table('invoices')->insertGetId([
                'repair_request_id' => $cancelledReq->id,
                'repair_job_id' => $cancelledJob->id,
                'shop_id' => $gearLab->id,
                'type' => 'CHECKUP',
                'status' => 'REFUNDED',
                'invoice_number' => 'INV-2026-RFD01',
                'subtotal_amount' => 500.00,
                'delivery_amount' => 0.00,
                'discount_amount' => 0.00,
                'commission_amount' => 0.00,
                'total_amount' => 500.00,
                'issued_at' => $tDispute,
                'due_at' => $tDispute,
                'paid_at' => $tDispute,
                'created_at' => $tDispute,
                'updated_at' => (clone $tDispute)->addHours(10),
            ]);

            DB::table('invoice_line_items')->insert([
                'invoice_id' => $refundInvoiceId,
                'shop_service_id' => $primaryServiceByShopId[$gearLab->id] ?? null,
                'title' => 'Escrow Diagnostic Deposit (Refunded)',
                'description' => 'Full refund issued after unrepairable board assessment',
                'quantity' => 1,
                'unit_price' => 500.00,
                'total_price' => 500.00,
                'created_at' => $tDispute,
                'updated_at' => (clone $tDispute)->addHours(10),
            ]);

            $refundedPayment = PaymentTransaction::create([
                'user_id' => $defaultCustomer->id,
                'repair_request_id' => $cancelledReq->id,
                'invoice_id' => $refundInvoiceId,
                'amount' => 500.00,
                'currency' => 'BDT',
                'payment_method' => 'BKASH',
                'status' => 'REFUNDED',
                'transaction_reference' => 'TRX-MRM-RFD202601',
                'gateway_response' => json_encode(['status' => 'REFUNDED', 'invoice' => 'INV-2026-RFD01']),
                'paid_at' => $tDispute,
                'created_at' => $tDispute,
                'updated_at' => (clone $tDispute)->addHours(10),
            ]);

            $disputeId = DB::table('dispute_cases')->insertGetId([
                'repair_request_id' => $cancelledReq->id,
                'repair_job_id' => $cancelledJob->id,
                'payment_id' => $refundedPayment->id,
                'opened_by_id' => $defaultCustomer->id,
                'against_id' => $gearOwnerId,
                'assigned_admin_id' => $meramotAdmin->id,
                'status' => 'REFUNDED',
                'resolution' => 'Workshop confirmed unrepairable saltwater PCB delamination; 100% of ৳500 escrow deposit refunded to customer bKash wallet.',
                'resolved_at' => (clone $tDispute)->addHours(10),
                'created_at' => (clone $tDispute)->addHours(8),
                'updated_at' => (clone $tDispute)->addHours(10),
            ]);

            DB::table('dispute_notes')->insert([
                'dispute_case_id' => $disputeId,
                'author_id' => $meramotAdmin->id,
                'note' => 'Verified microscope photos uploaded by The Gear Lab; mutual no-fix full refund approved by Meramot Admin.',
                'is_internal' => false,
                'created_at' => (clone $tDispute)->addHours(9),
                'updated_at' => (clone $tDispute)->addHours(9),
            ]);

            DB::table('refunds')->insert([
                'payment_id' => $refundedPayment->id,
                'dispute_case_id' => $disputeId,
                'approved_by_id' => $meramotAdmin->id,
                'amount' => 500.00,
                'reason' => 'Unrepairable board — mutual full escrow deposit return',
                'status' => 'PROCESSED',
                'processed_at' => (clone $tDispute)->addHours(10),
                'created_at' => (clone $tDispute)->addHours(9),
                'updated_at' => (clone $tDispute)->addHours(10),
            ]);

            DB::table('escrow_ledgers')->insert([
                [
                    'payment_id' => $refundedPayment->id,
                    'repair_request_id' => $cancelledReq->id,
                    'customer_user_id' => $defaultCustomer->id,
                    'vendor_user_id' => $gearOwnerId,
                    'amount' => 500.00,
                    'gross_amount' => 500.00,
                    'platform_commission_amount' => 0.00,
                    'vendor_net_amount' => 0.00,
                    'action' => 'HOLD',
                    'note' => 'Escrow deposit held for GoPro Hero 11 checkup',
                    'created_at' => $tDispute,
                    'updated_at' => $tDispute,
                ],
                [
                    'payment_id' => $refundedPayment->id,
                    'repair_request_id' => $cancelledReq->id,
                    'customer_user_id' => $defaultCustomer->id,
                    'vendor_user_id' => $gearOwnerId,
                    'amount' => 500.00,
                    'gross_amount' => 500.00,
                    'platform_commission_amount' => 0.00,
                    'vendor_net_amount' => 0.00,
                    'action' => 'REFUND',
                    'note' => "Escrow deposit refunded via Dispute #{$disputeId}",
                    'created_at' => (clone $tDispute)->addHours(10),
                    'updated_at' => (clone $tDispute)->addHours(10),
                ],
            ]);

            FinancialLedger::create([
                'shop_id' => $gearLab->id,
                'payment_id' => $refundedPayment->id,
                'invoice_id' => $refundInvoiceId,
                'entry_type' => 'CUSTOMER_PAYMENT',
                'direction' => 'CREDIT',
                'amount' => 500.00,
                'currency' => 'BDT',
                'description' => 'Checkup escrow deposit received (INV-2026-RFD01)',
                'effective_at' => $tDispute,
            ]);

            FinancialLedger::create([
                'shop_id' => $gearLab->id,
                'payment_id' => $refundedPayment->id,
                'invoice_id' => $refundInvoiceId,
                'entry_type' => 'REFUND',
                'direction' => 'DEBIT',
                'amount' => 500.00,
                'currency' => 'BDT',
                'description' => "Full escrow refund processed for Dispute #{$disputeId} (INV-2026-RFD01)",
                'effective_at' => (clone $tDispute)->addHours(10),
            ]);
        }

        // ---------------------------------------------------------------------
        // 11. Synchronize All Riders' `total_earnings` Strictly from `rider_earnings`
        // ---------------------------------------------------------------------
        foreach (DeliveryAgent::all() as $agent) {
            $agent->update([
                'total_earnings' => (float) RiderEarnings::where('delivery_agent_id', $agent->id)->sum('total_earned'),
            ]);
        }

        // ---------------------------------------------------------------------
        // 12. Coherent Vendor Payouts (Feature R18 + R16 Ledger Link)
        //     Strictly bounded by each shop's actual `VENDOR_EARNING` ledger sum!
        // ---------------------------------------------------------------------
        $protechMain = $shopsBySlug['protech-solutions'] ?? null;
        if ($protechMain) {
            $protechEarned = (float) FinancialLedger::where('shop_id', $protechMain->id)
                ->where('entry_type', 'VENDOR_EARNING')
                ->sum('amount');
            $disburseAmount = min(15000.00, round($protechEarned * 0.60, 2));

            if ($disburseAmount > 0) {
                $payoutTime = now()->subDays(2)->setTime(16, 0, 0);
                $primaryLabel = "{$protechMain->name} [{$protechMain->branch_name}]";

                $paidPayout = VendorPayout::create([
                    'shop_id' => $protechMain->id,
                    'amount' => $disburseAmount,
                    'currency' => 'BDT',
                    'status' => 'PAID',
                    'reference_code' => 'PYT-2026-0001',
                    'notes' => "Weekly automated bank settlement disbursed to {$primaryLabel}",
                    'paid_at' => $payoutTime,
                    'created_at' => (clone $payoutTime)->subHours(3),
                    'updated_at' => $payoutTime,
                ]);

                FinancialLedger::create([
                    'shop_id' => $protechMain->id,
                    'vendor_payout_id' => $paidPayout->id,
                    'entry_type' => 'VENDOR_PAYOUT',
                    'direction' => 'DEBIT',
                    'amount' => $disburseAmount,
                    'currency' => 'BDT',
                    'description' => "Weekly automated bank payout disbursed to {$primaryLabel} ({$paidPayout->reference_code})",
                    'effective_at' => $payoutTime,
                ]);
            }
        }

        $dhakaDoctors = $shopsBySlug['dhaka-device-doctors'] ?? null;
        if ($dhakaDoctors) {
            $doctorsEarned = (float) FinancialLedger::where('shop_id', $dhakaDoctors->id)
                ->where('entry_type', 'VENDOR_EARNING')
                ->sum('amount');

            if ($doctorsEarned > 0) {
                VendorPayout::create([
                    'shop_id' => $dhakaDoctors->id,
                    'amount' => round($doctorsEarned * 0.50, 2),
                    'currency' => 'BDT',
                    'status' => 'PENDING',
                    'reference_code' => 'PYT-2026-0002',
                    'notes' => 'Pending bi-weekly net commission settlement queued for Admin Payouts Desk',
                    'paid_at' => null,
                    'created_at' => now()->subDay(),
                    'updated_at' => now()->subDay(),
                ]);
            }
        }
    }
}

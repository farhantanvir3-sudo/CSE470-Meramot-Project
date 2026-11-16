<?php

namespace Database\Seeders;

use App\Models\RepairShop;
use App\Models\User;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Database\Seeder;

class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $voucherService = app(VoucherService::class);

        $adminUser = User::where('email', 'admin@meramot.demo')->first()
            ?? User::where('role', 'admin')->orderBy('id')->first();
        $adminId = $adminUser?->id;

        // 1. Default First-Order Free Courier Voucher (ONLY voucher with a system default code)
        $defaultFreeCourier = $voucherService->ensureDefaultFreeCourierVoucher($adminId);

        // Exclude one budget walk-in/economy shop to showcase Admin branch-exclusion governance
        $excludedDemoShopIds = RepairShop::whereIn('slug', ['budget-fixer'])
            ->pluck('id')
            ->map(fn ($v) => (int) $v)
            ->all();
        $defaultFreeCourier->excludedShops()->sync($excludedDemoShopIds);

        // 2. Admin Featured Voucher Stack (ordered by `featured_order` for Homepage Tile 2)
        // #1 Top-Most Featured Voucher on Homepage: "Laptop battery week"
        $batteryWeek = Voucher::updateOrCreate(
            ['code' => 'BATTERY15'],
            [
                'title' => 'Laptop battery week',
                'banner_subtitle' => 'Save up to 15% on replacement batteries at participating shops.',
                'description' => 'Featured limited-time offer saving 15% (up to ৳500) on laptop battery replacements and hardware bookings at participating workshops.',
                'cta_href' => '/shops?q=battery&sort=price&voucher=true',
                'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                'issuer_type' => Voucher::ISSUER_ADMIN,
                'branch_scope' => Voucher::SCOPE_UNIVERSAL,
                'created_by_user_id' => $adminId,
                'vendor_profile_id' => null,
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 15.00,
                'min_order_amount' => 800.00,
                'max_discount_amount' => 500.00,
                'required_payment_method' => null,
                'is_first_order_only' => false,
                'is_system_default' => false,
                'is_featured' => true,
                'featured_order' => 1,
                'starts_at' => $now->copy()->subDays(2),
                'expires_at' => $now->copy()->addDays(14),
                'valid_days_of_week' => null,
                'daily_start_time' => null,
                'daily_end_time' => null,
                'is_active' => true,
            ]
        );
        $batteryWeek->excludedShops()->sync($excludedDemoShopIds);

        // #2 Featured Voucher in Stack: 10% off if you pay with bKash
        $bkash10 = Voucher::updateOrCreate(
            ['code' => 'BKASH10'],
            [
                'title' => '10% off if you pay with bKash',
                'banner_subtitle' => 'Enjoy 10% instant savings (up to ৳400) when paying via bKash at checkout.',
                'description' => 'Pay with bKash Wallet at checkout to unlock 10% off orders of ৳500 or more.',
                'cta_href' => '/shops?voucher=true',
                'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                'issuer_type' => Voucher::ISSUER_ADMIN,
                'branch_scope' => Voucher::SCOPE_UNIVERSAL,
                'created_by_user_id' => $adminId,
                'vendor_profile_id' => null,
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 10.00,
                'min_order_amount' => 500.00,
                'max_discount_amount' => 400.00,
                'required_payment_method' => 'BKASH',
                'is_first_order_only' => false,
                'is_system_default' => false,
                'is_featured' => true,
                'featured_order' => 2,
                'starts_at' => $now->copy()->subDays(2),
                'expires_at' => $now->copy()->addDays(25),
                'valid_days_of_week' => null,
                'daily_start_time' => null,
                'daily_end_time' => null,
                'is_active' => true,
            ]
        );
        $bkash10->excludedShops()->sync($excludedDemoShopIds);

        // #3 Featured Voucher in Stack: Winter Sale (10% off up to ৳500)
        $meramot10 = Voucher::updateOrCreate(
            ['code' => 'MERAMOT10'],
            [
                'title' => 'Winter Sale — 10% Off Repairs & Parts',
                'banner_subtitle' => 'Save 10% up to ৳500 on verified workshop repairs and spare parts.',
                'description' => 'Seasonal Winter Sale across participating Meramot workshops.',
                'cta_href' => '/shops?voucher=true',
                'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                'issuer_type' => Voucher::ISSUER_ADMIN,
                'branch_scope' => Voucher::SCOPE_UNIVERSAL,
                'created_by_user_id' => $adminId,
                'vendor_profile_id' => null,
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 10.00,
                'min_order_amount' => 0.00,
                'max_discount_amount' => 500.00,
                'required_payment_method' => null,
                'is_first_order_only' => false,
                'is_system_default' => false,
                'is_featured' => true,
                'featured_order' => 3,
                'starts_at' => $now->copy()->subDays(5),
                'expires_at' => $now->copy()->addDays(40),
                'valid_days_of_week' => null,
                'daily_start_time' => null,
                'daily_end_time' => null,
                'is_active' => true,
            ]
        );
        $meramot10->excludedShops()->sync([]);

        // #4 Featured Voucher in Stack: Flat ৳100 Off Direct Repair Booking (min ৳800)
        $firstFix100 = Voucher::updateOrCreate(
            ['code' => 'FIRSTFIX100'],
            [
                'title' => '৳100 Off Direct Repair & Package Bookings',
                'banner_subtitle' => 'Instant ৳100 discount on workshop bookings of ৳800 or more.',
                'description' => 'Flat ৳100 savings on eligible repair packages and spare part orders.',
                'cta_href' => '/packages/catalog',
                'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                'issuer_type' => Voucher::ISSUER_ADMIN,
                'branch_scope' => Voucher::SCOPE_UNIVERSAL,
                'created_by_user_id' => $adminId,
                'vendor_profile_id' => null,
                'discount_type' => Voucher::DISCOUNT_FIXED,
                'discount_value' => 100.00,
                'min_order_amount' => 800.00,
                'max_discount_amount' => null,
                'required_payment_method' => null,
                'is_first_order_only' => false,
                'is_system_default' => false,
                'is_featured' => true,
                'featured_order' => 4,
                'starts_at' => $now->copy()->subDays(5),
                'expires_at' => $now->copy()->addDays(50),
                'valid_days_of_week' => null,
                'daily_start_time' => null,
                'daily_end_time' => null,
                'is_active' => true,
            ]
        );
        $firstFix100->excludedShops()->sync([]);

        // 3. Vendor-Set Vouchers (All-Branches & Branch-Specific)
        $protechBanani = RepairShop::where('slug', 'protech-solutions')->first();
        $protechAgrabad = RepairShop::where('slug', 'protech-solutions-agrabad')->first();

        if ($protechBanani && $protechBanani->vendor_profile_id) {
            $protechOwnerId = $protechBanani->vendorProfile?->user_id;

            // All-Branches voucher for ProTech Solutions (applies to Banani, Uttara & Agrabad)
            $protechAll = Voucher::updateOrCreate(
                ['code' => 'PROTECH150'],
                [
                    'title' => 'ProTech All-Branch Care Offer',
                    'description' => '৳150 off orders of ৳1,000+ across all ProTech Solutions branches.',
                    'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                    'issuer_type' => Voucher::ISSUER_VENDOR,
                    'branch_scope' => Voucher::SCOPE_ALL_BRANCHES,
                    'created_by_user_id' => $protechOwnerId,
                    'vendor_profile_id' => $protechBanani->vendor_profile_id,
                    'discount_type' => Voucher::DISCOUNT_FIXED,
                    'discount_value' => 150.00,
                    'min_order_amount' => 1000.00,
                    'max_discount_amount' => null,
                    'required_payment_method' => null,
                    'is_first_order_only' => false,
                    'is_system_default' => false,
                    'is_featured' => false,
                    'featured_order' => 100,
                    'starts_at' => $now->copy()->subDays(3),
                    'expires_at' => $now->copy()->addDays(30),
                    'valid_days_of_week' => null,
                    'is_active' => true,
                ]
            );
            $protechAll->includedShops()->detach();

            // Branch-Specific voucher ONLY for Banani DOHS Flagship
            $bananiOnly = Voucher::updateOrCreate(
                ['code' => 'BANANI200'],
                [
                    'title' => 'Banani Flagship Bench Discount',
                    'description' => '৳200 off Apple & ultrabook servicing exclusively at Banani DOHS Flagship.',
                    'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                    'issuer_type' => Voucher::ISSUER_VENDOR,
                    'branch_scope' => Voucher::SCOPE_SPECIFIC_BRANCHES,
                    'created_by_user_id' => $protechOwnerId,
                    'vendor_profile_id' => $protechBanani->vendor_profile_id,
                    'discount_type' => Voucher::DISCOUNT_FIXED,
                    'discount_value' => 200.00,
                    'min_order_amount' => 1500.00,
                    'max_discount_amount' => null,
                    'required_payment_method' => null,
                    'is_first_order_only' => false,
                    'is_system_default' => false,
                    'is_featured' => false,
                    'featured_order' => 100,
                    'starts_at' => $now->copy()->subDays(2),
                    'expires_at' => $now->copy()->addDays(28),
                    'valid_days_of_week' => null,
                    'is_active' => true,
                ]
            );
            $bananiOnly->includedShops()->sync([$protechBanani->id]);

            // Branch-Specific scheduled Friday voucher ONLY for Agrabad, Chattogram
            if ($protechAgrabad) {
                $ctgFriday = Voucher::updateOrCreate(
                    ['code' => 'FRIDAYCTG'],
                    [
                        'title' => 'Agrabad Friday Repair Saver',
                        'description' => '12% off every Friday at ProTech Solutions Agrabad branch.',
                        'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                        'issuer_type' => Voucher::ISSUER_VENDOR,
                        'branch_scope' => Voucher::SCOPE_SPECIFIC_BRANCHES,
                        'created_by_user_id' => $protechOwnerId,
                        'vendor_profile_id' => $protechAgrabad->vendor_profile_id,
                        'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                        'discount_value' => 12.00,
                        'min_order_amount' => 700.00,
                        'max_discount_amount' => 350.00,
                        'required_payment_method' => null,
                        'is_first_order_only' => false,
                        'is_system_default' => false,
                        'is_featured' => false,
                        'featured_order' => 100,
                        'starts_at' => $now->copy()->subDays(2),
                        'expires_at' => $now->copy()->addDays(45),
                        'valid_days_of_week' => [5], // Every Friday
                        'is_active' => true,
                    ]
                );
                $ctgFriday->includedShops()->sync([$protechAgrabad->id]);
            }
        }

        // Additional realistic vendor vouchers across featured curated hubs
        $curatedVendorPromos = [
            'dhaka-device-doctors' => [
                'code' => 'DOCTORS120',
                'title' => 'Device Doctors Bench Discount',
                'discount_type' => Voucher::DISCOUNT_FIXED,
                'discount_value' => 120.00,
                'min_order_amount' => 600.00,
                'max_discount_amount' => null,
                'days' => null,
            ],
            'dragon-tech' => [
                'code' => 'BLKFRI20',
                'title' => 'Black Friday Sale',
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 20.00,
                'min_order_amount' => 1000.00,
                'max_discount_amount' => 500.00,
                'days' => null,
            ],
            'bytesize-repairs' => [
                'code' => 'BYTESIZE80',
                'title' => 'ByteSize Diagnostic Saver',
                'discount_type' => Voucher::DISCOUNT_FIXED,
                'discount_value' => 80.00,
                'min_order_amount' => 500.00,
                'max_discount_amount' => null,
                'days' => null,
            ],
            'mirpur-laptop-lab' => [
                'code' => 'SUNDAY10',
                'title' => 'Sunday Laptop Lab Offer',
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 10.00,
                'min_order_amount' => 500.00,
                'max_discount_amount' => 300.00,
                'days' => [0], // Every Sunday
            ],
        ];

        foreach ($curatedVendorPromos as $slug => $cfg) {
            $shop = RepairShop::where('slug', $slug)->first();
            if (!$shop || !$shop->vendor_profile_id) {
                continue;
            }
            $isSharedCarrier = $shop->vendorProfile?->user?->email === 'directory.merchants@meramot.local';
            $scope = $isSharedCarrier ? Voucher::SCOPE_SPECIFIC_BRANCHES : Voucher::SCOPE_ALL_BRANCHES;

            $v = Voucher::updateOrCreate(
                ['code' => $cfg['code']],
                [
                    'title' => $cfg['title'],
                    'description' => $cfg['title'] . ' at ' . $shop->name . '.',
                    'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                    'issuer_type' => Voucher::ISSUER_VENDOR,
                    'branch_scope' => $scope,
                    'created_by_user_id' => $shop->vendorProfile?->user_id,
                    'vendor_profile_id' => $shop->vendor_profile_id,
                    'discount_type' => $cfg['discount_type'],
                    'discount_value' => $cfg['discount_value'],
                    'min_order_amount' => $cfg['min_order_amount'],
                    'max_discount_amount' => $cfg['max_discount_amount'],
                    'required_payment_method' => null,
                    'is_first_order_only' => false,
                    'is_system_default' => false,
                    'is_featured' => false,
                    'featured_order' => 100,
                    'starts_at' => $now->copy()->subDays(3),
                    'expires_at' => $now->copy()->addDays(35),
                    'valid_days_of_week' => $cfg['days'],
                    'is_active' => true,
                ]
            );

            if ($scope === Voucher::SCOPE_SPECIFIC_BRANCHES) {
                $v->includedShops()->sync([$shop->id]);
            } else {
                $v->includedShops()->detach();
            }
        }

        $voucherService->syncShopVoucherFlags();
    }
}

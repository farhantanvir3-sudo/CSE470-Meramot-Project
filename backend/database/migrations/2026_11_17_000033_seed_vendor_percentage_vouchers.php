<?php

use App\Models\RepairShop;
use App\Models\Voucher;
use App\Services\VoucherService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('vouchers') || !Schema::hasTable('repair_shops')) {
            return;
        }

        $now = now();

        // 1. ProTech Solutions (Multi-Branch)
        $protechBanani = RepairShop::where('slug', 'protech-solutions')->first();
        $protechUttara = RepairShop::where('slug', 'protech-solutions-uttara')->first();

        if ($protechBanani && $protechBanani->vendor_profile_id) {
            $protechOwnerId = $protechBanani->vendorProfile?->user_id;

            // 20% Off All ProTech Branches
            $protech20 = Voucher::updateOrCreate(
                ['code' => 'PROTECH20'],
                [
                    'title' => '20% Off: ProTech All-Branch Care',
                    'description' => '20% off comprehensive Apple & PC repair bookings across all ProTech Solutions branches.',
                    'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                    'issuer_type' => Voucher::ISSUER_VENDOR,
                    'branch_scope' => Voucher::SCOPE_ALL_BRANCHES,
                    'created_by_user_id' => $protechOwnerId,
                    'vendor_profile_id' => $protechBanani->vendor_profile_id,
                    'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                    'discount_value' => 20.00,
                    'min_order_amount' => 1000.00,
                    'max_discount_amount' => 600.00,
                    'required_payment_method' => null,
                    'is_first_order_only' => false,
                    'is_system_default' => false,
                    'is_featured' => false,
                    'featured_order' => 100,
                    'starts_at' => $now->copy()->subDays(3),
                    'expires_at' => $now->copy()->addDays(35),
                    'valid_days_of_week' => null,
                    'is_active' => true,
                ]
            );
            $protech20->includedShops()->detach();

            // 25% Off Banani Branch Exclusive
            $banani25 = Voucher::updateOrCreate(
                ['code' => 'BANANI25'],
                [
                    'title' => '25% Off: Banani Flagship Tech Fest',
                    'description' => '25% off Apple MacBook & ultrabook repairs exclusively at Banani DOHS Flagship.',
                    'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                    'issuer_type' => Voucher::ISSUER_VENDOR,
                    'branch_scope' => Voucher::SCOPE_SPECIFIC_BRANCHES,
                    'created_by_user_id' => $protechOwnerId,
                    'vendor_profile_id' => $protechBanani->vendor_profile_id,
                    'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                    'discount_value' => 25.00,
                    'min_order_amount' => 1500.00,
                    'max_discount_amount' => 1000.00,
                    'required_payment_method' => null,
                    'is_first_order_only' => false,
                    'is_system_default' => false,
                    'is_featured' => false,
                    'featured_order' => 100,
                    'starts_at' => $now->copy()->subDays(2),
                    'expires_at' => $now->copy()->addDays(30),
                    'valid_days_of_week' => null,
                    'is_active' => true,
                ]
            );
            $banani25->includedShops()->sync([$protechBanani->id]);

            // 30% Off Uttara Branch Exclusive
            if ($protechUttara) {
                $uttara30 = Voucher::updateOrCreate(
                    ['code' => 'PROTECH30'],
                    [
                        'title' => '30% Off: Uttara Northern Hub Special',
                        'description' => '30% off express bench repairs exclusively at ProTech Solutions Uttara Sector 7 branch.',
                        'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                        'issuer_type' => Voucher::ISSUER_VENDOR,
                        'branch_scope' => Voucher::SCOPE_SPECIFIC_BRANCHES,
                        'created_by_user_id' => $protechOwnerId,
                        'vendor_profile_id' => $protechBanani->vendor_profile_id,
                        'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                        'discount_value' => 30.00,
                        'min_order_amount' => 1200.00,
                        'max_discount_amount' => 800.00,
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
                $uttara30->includedShops()->sync([$protechUttara->id]);
            }
        }

        // 2. Sony Multi-Branch Network
        $sonyDhanmondi = RepairShop::where('slug', 'sony-dhanmondi-dhaka')->first();
        if ($sonyDhanmondi && $sonyDhanmondi->vendor_profile_id) {
            $sonyOwnerId = $sonyDhanmondi->vendorProfile?->user_id;

            $sony20 = Voucher::updateOrCreate(
                ['code' => 'SONY20'],
                [
                    'title' => '20% Off: Sony Multi-Branch Audio & Display Care',
                    'description' => '20% off audio gear and display repairs across all Sony branches nationwide.',
                    'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                    'issuer_type' => Voucher::ISSUER_VENDOR,
                    'branch_scope' => Voucher::SCOPE_ALL_BRANCHES,
                    'created_by_user_id' => $sonyOwnerId,
                    'vendor_profile_id' => $sonyDhanmondi->vendor_profile_id,
                    'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                    'discount_value' => 20.00,
                    'min_order_amount' => 1000.00,
                    'max_discount_amount' => 750.00,
                    'required_payment_method' => null,
                    'is_first_order_only' => false,
                    'is_system_default' => false,
                    'is_featured' => false,
                    'featured_order' => 100,
                    'starts_at' => $now->copy()->subDays(3),
                    'expires_at' => $now->copy()->addDays(35),
                    'valid_days_of_week' => null,
                    'is_active' => true,
                ]
            );
            $sony20->includedShops()->detach();

            $sonyDhm25 = Voucher::updateOrCreate(
                ['code' => 'SONYDHM25'],
                [
                    'title' => '25% Off: Sony Dhanmondi Branch Exclusive',
                    'description' => '25% off audio and display repair exclusively at Sony Dhanmondi service hub.',
                    'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                    'issuer_type' => Voucher::ISSUER_VENDOR,
                    'branch_scope' => Voucher::SCOPE_SPECIFIC_BRANCHES,
                    'created_by_user_id' => $sonyOwnerId,
                    'vendor_profile_id' => $sonyDhanmondi->vendor_profile_id,
                    'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                    'discount_value' => 25.00,
                    'min_order_amount' => 800.00,
                    'max_discount_amount' => 600.00,
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
            $sonyDhm25->includedShops()->sync([$sonyDhanmondi->id]);
        }

        // 3. Butterfly Multi-Branch Network
        $butterflyMirpur = RepairShop::where('slug', 'butterfly-mirpur-dhaka')->first();
        if ($butterflyMirpur && $butterflyMirpur->vendor_profile_id) {
            $bfOwnerId = $butterflyMirpur->vendorProfile?->user_id;

            $butterfly20 = Voucher::updateOrCreate(
                ['code' => 'BUTTERFLY20'],
                [
                    'title' => '20% Off: Butterfly Multi-Branch Servicing',
                    'description' => '20% off electronics servicing across all Butterfly branches nationwide.',
                    'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                    'issuer_type' => Voucher::ISSUER_VENDOR,
                    'branch_scope' => Voucher::SCOPE_ALL_BRANCHES,
                    'created_by_user_id' => $bfOwnerId,
                    'vendor_profile_id' => $butterflyMirpur->vendor_profile_id,
                    'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                    'discount_value' => 20.00,
                    'min_order_amount' => 1000.00,
                    'max_discount_amount' => 800.00,
                    'required_payment_method' => null,
                    'is_first_order_only' => false,
                    'is_system_default' => false,
                    'is_featured' => false,
                    'featured_order' => 100,
                    'starts_at' => $now->copy()->subDays(3),
                    'expires_at' => $now->copy()->addDays(35),
                    'valid_days_of_week' => null,
                    'is_active' => true,
                ]
            );
            $butterfly20->includedShops()->detach();

            $butterfly30 = Voucher::updateOrCreate(
                ['code' => 'BUTTERFLY30'],
                [
                    'title' => '30% Off: Butterfly Mirpur Tech Special',
                    'description' => '30% off electronics repair exclusively at Butterfly Mirpur branch.',
                    'voucher_type' => Voucher::TYPE_LIMITED_TIME,
                    'issuer_type' => Voucher::ISSUER_VENDOR,
                    'branch_scope' => Voucher::SCOPE_SPECIFIC_BRANCHES,
                    'created_by_user_id' => $bfOwnerId,
                    'vendor_profile_id' => $butterflyMirpur->vendor_profile_id,
                    'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                    'discount_value' => 30.00,
                    'min_order_amount' => 1200.00,
                    'max_discount_amount' => 900.00,
                    'required_payment_method' => null,
                    'is_first_order_only' => false,
                    'is_system_default' => false,
                    'is_featured' => false,
                    'featured_order' => 100,
                    'starts_at' => $now->copy()->subDays(2),
                    'expires_at' => $now->copy()->addDays(30),
                    'valid_days_of_week' => null,
                    'is_active' => true,
                ]
            );
            $butterfly30->includedShops()->sync([$butterflyMirpur->id]);
        }

        // 4. Prominent Curated Workshops
        $curatedVendorPromos = [
            [
                'shop_slug' => 'the-gadget-den',
                'code' => 'GADGETDEN25',
                'title' => '25% Off: Gadget Den Workshop Special',
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 25.00,
                'min_order_amount' => 800.00,
                'max_discount_amount' => 500.00,
                'days' => null,
            ],
            [
                'shop_slug' => 'macmaster-repairs',
                'code' => 'MACMASTER20',
                'title' => '20% Off: MacMaster Logic Board & Screen Care',
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 20.00,
                'min_order_amount' => 1500.00,
                'max_discount_amount' => 1000.00,
                'days' => null,
            ],
            [
                'shop_slug' => 'bytesize-repairs',
                'code' => 'BYTESIZE25',
                'title' => '25% Off: Express Screen & Display Swap',
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 25.00,
                'min_order_amount' => 700.00,
                'max_discount_amount' => 450.00,
                'days' => null,
            ],
            [
                'shop_slug' => 'dhaka-device-doctors',
                'code' => 'DOC30',
                'title' => '30% Off: Complete Diagnostics & Overhaul',
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 30.00,
                'min_order_amount' => 600.00,
                'max_discount_amount' => 500.00,
                'days' => null,
            ],
            [
                'shop_slug' => 'mirpur-laptop-lab',
                'code' => 'LAPTOPLAB25',
                'title' => '25% Off: Laptop Lab Board & Screen Special',
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 25.00,
                'min_order_amount' => 800.00,
                'max_discount_amount' => 600.00,
                'days' => null,
            ],
            [
                'shop_slug' => 'electrobench-diagnostics',
                'code' => 'BENCH20',
                'title' => '20% Off: ElectroBench Precision Testing',
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 20.00,
                'min_order_amount' => 500.00,
                'max_discount_amount' => 300.00,
                'days' => null,
            ],
            [
                'shop_slug' => 'karnaphuli-microsolder-lab',
                'code' => 'KARNAPHULI30',
                'title' => '30% Off: Port City IC Micro-Soldering',
                'discount_type' => Voucher::DISCOUNT_PERCENTAGE,
                'discount_value' => 30.00,
                'min_order_amount' => 1000.00,
                'max_discount_amount' => 800.00,
                'days' => null,
            ],
        ];

        foreach ($curatedVendorPromos as $cfg) {
            $shop = RepairShop::where('slug', $cfg['shop_slug'])->first();
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

        if (class_exists(VoucherService::class)) {
            app(VoucherService::class)->syncShopVoucherFlags();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Voucher::whereIn('code', [
            'PROTECH20',
            'BANANI25',
            'PROTECH30',
            'SONY20',
            'SONYDHM25',
            'BUTTERFLY20',
            'BUTTERFLY30',
            'GADGETDEN25',
            'MACMASTER20',
            'BYTESIZE25',
            'DOC30',
            'LAPTOPLAB25',
            'BENCH20',
            'KARNAPHULI30',
        ])->delete();

        if (class_exists(VoucherService::class)) {
            app(VoucherService::class)->syncShopVoucherFlags();
        }
    }
};

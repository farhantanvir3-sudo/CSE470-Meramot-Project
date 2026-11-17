<?php

use App\Models\RepairShop;
use App\Services\VoucherService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('repair_shops')) {
            return;
        }

        // Enable COURIER_PICKUP and IN_SHOP_REPAIR capabilities across all active repair workshops
        // (preserving pure spare parts wholesalers that have zero repair services).
        $shops = RepairShop::query()->select(['id', 'slug', 'categories', 'supports_pickup', 'supports_onsite_repair', 'free_delivery'])->get();

        foreach ($shops as $shop) {
            if ($shop->slug === 'elephant-parts-wholesale' || $shop->slug === 'quickpatch-kiosk') {
                continue;
            }

            $rawCats = $shop->categories;
            $cats = is_array($rawCats) ? $rawCats : (json_decode((string) $rawCats, true) ?: []);

            $changed = false;
            if (!in_array('COURIER_PICKUP', $cats, true)) {
                $cats[] = 'COURIER_PICKUP';
                $changed = true;
            }
            if (!in_array('IN_SHOP_REPAIR', $cats, true)) {
                $cats[] = 'IN_SHOP_REPAIR';
                $changed = true;
            }

            $update = [];
            if ($changed) {
                $update['categories'] = json_encode(array_values(array_unique($cats)), JSON_UNESCAPED_UNICODE);
            }
            if (!$shop->supports_pickup) {
                $update['supports_pickup'] = true;
            }
            if (!$shop->supports_onsite_repair) {
                $update['supports_onsite_repair'] = true;
            }

            if (!empty($update)) {
                DB::table('repair_shops')->where('id', $shop->id)->update($update);
            }
        }

        // Synchronize `has_voucher` and `free_delivery` flags across all workshops
        if (class_exists(VoucherService::class)) {
            app(VoucherService::class)->syncShopVoucherFlags();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Safe no-op
    }
};

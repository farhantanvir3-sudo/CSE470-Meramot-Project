<?php

use App\Models\SparePartListing;
use App\Models\VendorProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Synchronize model-independent Fixed Packages, workshop base fee tiers, and
     * categorized Spare Parts catalog on live PostgreSQL deployments (including Render/Neon)
     * that were initially seeded prior to catalog normalization.
     */
    public function up(): void
    {
        if (app()->runningUnitTests()) {
            return;
        }

        if (!Schema::hasTable('users') || DB::table('users')->count() === 0) {
            return;
        }

        // 1. Replace any legacy device-dependent fixed packages with model-independent software/maintenance packages
        if (Schema::hasTable('service_packages')) {
            (new \Database\Seeders\ServicePackageSeeder())->run();
        }

        // 2. Apply varied shop performance base commission tiers without overriding custom admin overrides
        if (Schema::hasTable('repair_shops') && Schema::hasTable('vendor_profiles')) {
            $shopCommissionTiers = [
                'protech-solutions' => 6.00,
                'dhaka-device-doctors' => 6.50,
                'macmaster-repairs' => 7.00,
                'the-gadget-den' => 7.50,
                'the-idoc-garage' => 8.00,
                'gulshan-gadget-care' => 8.00,
                'bytesize-repairs' => 8.50,
                'budget-fixer' => 9.00,
                'applecore-solutions' => 8.50,
                'main-street-tech' => 9.00,
                'precision-fix' => 9.00,
                'mirpur-laptop-lab' => 9.50,
                'uttara-fixpoint' => 9.50,
                'bashundhara-laptop-lounge' => 9.00,
                'farmgate-tech-rescue' => 10.00,
                'pixel-repair-station' => 8.50,
            ];

            $profileBySlug = DB::table('repair_shops')
                ->whereIn('slug', array_keys($shopCommissionTiers))
                ->whereNotNull('vendor_profile_id')
                ->pluck('vendor_profile_id', 'slug');

            foreach ($shopCommissionTiers as $slug => $rate) {
                $profileId = $profileBySlug[$slug] ?? null;
                if ($profileId) {
                    VendorProfile::where('id', $profileId)
                        ->where('commission_rate', 10.00)
                        ->update(['commission_rate' => $rate]);
                }
            }
        }

        // 3. Re-sync spare parts if missing tools category, using legacy broad buckets, or holding excluded rechargeable fans
        if (Schema::hasTable('spare_part_listings')) {
            $needsSparePartsReseed = SparePartListing::where('part_category', 'Tools, Consumables & Soldering')->count() === 0
                || SparePartListing::whereIn('part_category', ['Mobile Spare Parts', 'Accessories & Chargers', 'PC & Laptop Parts'])->exists()
                || SparePartListing::whereRaw('LOWER(title) LIKE ?', ['%rechargeable fan%'])->exists();

            if ($needsSparePartsReseed) {
                (new \Database\Seeders\SparePartSeeder())->run();
            }
        }
    }

    public function down(): void
    {
        // Irreversible catalog normalization
    }
};

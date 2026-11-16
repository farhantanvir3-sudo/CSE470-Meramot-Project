<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('CREATE INDEX IF NOT EXISTS idx_spl_active_alpha_title ON spare_part_listings (LOWER(TRIM(title)), id) WHERE is_active = true');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_spl_active_cat_brand ON spare_part_listings (is_active, part_category, brand)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_spl_group_active ON spare_part_listings (product_group_key, is_active)');
        DB::statement('CREATE INDEX IF NOT EXISTS idx_rs_active_public_featured ON repair_shops (is_active, is_public, is_featured, rating DESC)');
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS idx_spl_active_alpha_title');
        DB::statement('DROP INDEX IF EXISTS idx_spl_active_cat_brand');
        DB::statement('DROP INDEX IF EXISTS idx_spl_group_active');
        DB::statement('DROP INDEX IF EXISTS idx_rs_active_public_featured');
    }
};

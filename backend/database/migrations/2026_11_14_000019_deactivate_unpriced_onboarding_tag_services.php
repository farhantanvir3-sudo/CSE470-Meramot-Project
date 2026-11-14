<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Shop setup previously pre-filled the "Available services" list with the
 * specialty tags chosen during onboarding, publishing them without a price
 * (rendered on the storefront with a placeholder estimate).
 *
 * Fixed-price services are now strictly vendor-defined, so these unpriced,
 * tag-derived rows are deactivated. Rows are kept (not deleted) so existing
 * order history stays intact, and vendors can re-add them with a real price.
 */
return new class extends Migration
{
    private const ONBOARDING_TAGS = [
        'smartphone repair', 'laptop repair', 'desktop repair', 'tablet repair',
        'smartwatch repair', 'console repair', 'tv repair', 'audio equipment repair',
        'camera repair', 'drone repair', 'appliance repair', 'micro-soldering',
        'data recovery', 'screen replacement', 'battery replacement', 'water damage repair',
    ];

    public function up(): void
    {
        if (!Schema::hasTable('shop_services')) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count(self::ONBOARDING_TAGS), '?'));

        DB::table('shop_services')
            ->where('is_active', true)
            ->whereNull('base_price')
            ->whereRaw("LOWER(TRIM(name)) IN ({$placeholders})", self::ONBOARDING_TAGS)
            ->update(['is_active' => false, 'updated_at' => now()]);
    }

    public function down(): void
    {
        // Irreversible data clean-up: vendors re-publish services with their own prices.
    }
};

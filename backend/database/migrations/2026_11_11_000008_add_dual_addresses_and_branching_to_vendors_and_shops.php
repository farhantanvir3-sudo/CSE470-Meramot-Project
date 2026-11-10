<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * FEATURE R6 & R11 ENHANCEMENT: Dual Shop Addresses & Multi-Branch Vendors
 * ============================================================================
 * Author / Lead Architect: Farhan Tanvir [Student ID: 22299179]
 * Coherent Schema Extension for:
 *   1. Dual Vendor Shop Addresses:
 *      - GPS Approximate Location (`latitude`, `longitude`, `gps_address`)
 *      - Vendor-Provided Storefront Location (`address`, `city`, `area`)
 *      - Optional Sync (`same_as_gps_location` = true when 1 & 2 are identical)
 *   2. Multi-Branch Business Expansion:
 *      - Single `VendorProfile` (`brand_name`, `is_branched`) owning multiple
 *        `RepairShop` branches (`is_branch`, `branch_name`) nationwide.
 *   3. Nationwide Bangladesh OpenStreetMap (`shop=electronics`) Sync (`osm_id`).
 * ============================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->string('brand_name')->nullable()->after('shop_name');
            $table->boolean('is_branched')->default(false)->after('brand_name');
            $table->text('gps_address')->nullable()->after('shop_address');
            $table->boolean('same_as_gps_location')->default(false)->after('gps_address');

            $table->index('is_branched');
        });

        // Allow full storefront & OSM street addresses on PostgreSQL without length truncation
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE repair_shops ALTER COLUMN address TYPE TEXT');
        }

        Schema::table('repair_shops', function (Blueprint $table) {
            $table->text('gps_address')->nullable()->after('address');
            $table->boolean('same_as_gps_location')->default(false)->after('gps_address');
            $table->boolean('is_branch')->default(false)->after('slug');
            $table->string('branch_name')->nullable()->after('is_branch');
            $table->string('osm_id')->nullable()->unique()->after('vendor_profile_id');

            $table->index('city');
            $table->index('area');
            $table->index('is_branch');
        });

        // Ensure shop_services has a unique composite index on (shop_id, slug) for fast bulk upserts
        Schema::table('shop_services', function (Blueprint $table) {
            $table->unique(['shop_id', 'slug'], 'shop_services_shop_id_slug_unique');
        });
    }

    public function down(): void
    {
        Schema::table('shop_services', function (Blueprint $table) {
            $table->dropUnique('shop_services_shop_id_slug_unique');
        });

        Schema::table('repair_shops', function (Blueprint $table) {
            $table->dropIndex(['city']);
            $table->dropIndex(['area']);
            $table->dropIndex(['is_branch']);
            $table->dropUnique(['osm_id']);
            $table->dropColumn([
                'gps_address',
                'same_as_gps_location',
                'is_branch',
                'branch_name',
                'osm_id',
            ]);
        });

        Schema::table('vendor_profiles', function (Blueprint $table) {
            $table->dropIndex(['is_branched']);
            $table->dropColumn([
                'brand_name',
                'is_branched',
                'gps_address',
                'same_as_gps_location',
            ]);
        });
    }
};

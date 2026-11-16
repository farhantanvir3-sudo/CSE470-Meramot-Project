<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('shop_reviews') && !Schema::hasColumn('shop_reviews', 'spare_part_listing_id')) {
            Schema::table('shop_reviews', function (Blueprint $table) {
                $table->foreignId('spare_part_listing_id')
                    ->nullable()
                    ->after('repair_request_id')
                    ->constrained('spare_part_listings')
                    ->nullOnDelete();

                $table->index('spare_part_listing_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_reviews') && Schema::hasColumn('shop_reviews', 'spare_part_listing_id')) {
            Schema::table('shop_reviews', function (Blueprint $table) {
                $table->dropConstrainedForeignId('spare_part_listing_id');
            });
        }
    }
};

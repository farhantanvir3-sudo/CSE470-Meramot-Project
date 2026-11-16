<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('shop_services', function (Blueprint $table) {
            $table->decimal('regular_price', 10, 2)->nullable()->after('base_price');
            $table->boolean('deal_enabled')->default(false)->index()->after('regular_price');
            $table->decimal('deal_price', 10, 2)->nullable()->after('deal_enabled');
            $table->unsignedSmallInteger('deal_discount_pct')->nullable()->after('deal_price');
            $table->timestamp('deal_starts_at')->nullable()->after('deal_discount_pct');
            $table->timestamp('deal_ends_at')->nullable()->after('deal_starts_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_services', function (Blueprint $table) {
            $table->dropIndex(['deal_enabled']);
            $table->dropColumn([
                'regular_price',
                'deal_enabled',
                'deal_price',
                'deal_discount_pct',
                'deal_starts_at',
                'deal_ends_at',
            ]);
        });
    }
};

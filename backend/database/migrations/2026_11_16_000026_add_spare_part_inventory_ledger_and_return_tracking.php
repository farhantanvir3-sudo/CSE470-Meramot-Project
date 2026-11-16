<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasColumn('spare_part_listings', 'returned_count')) {
            Schema::table('spare_part_listings', function (Blueprint $table) {
                $table->unsignedInteger('returned_count')->default(0)->after('sold_count');
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE spare_part_listings DROP CONSTRAINT IF EXISTS chk_spare_part_non_negative_inventory');
            DB::statement('ALTER TABLE spare_part_listings ADD CONSTRAINT chk_spare_part_non_negative_inventory CHECK (stock_quantity >= 0 AND sold_count >= 0 AND returned_count >= 0)');
        }

        if (!Schema::hasTable('spare_part_stock_movements')) {
            Schema::create('spare_part_stock_movements', function (Blueprint $table) {
                $table->id();
                $table->foreignId('spare_part_listing_id')->constrained('spare_part_listings')->cascadeOnDelete();
                $table->foreignId('repair_request_id')->nullable()->constrained('repair_requests')->nullOnDelete();
                $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('spec_code', 100)->nullable();
                $table->string('movement_type', 32);
                $table->unsignedInteger('quantity');
                $table->unsignedInteger('stock_before');
                $table->unsignedInteger('stock_after');
                $table->unsignedInteger('sold_after');
                $table->unsignedInteger('returned_after');
                $table->string('idempotency_key', 160)->unique();
                $table->string('reason', 255)->nullable();
                $table->timestamps();

                $table->index(['spare_part_listing_id', 'created_at'], 'idx_spsm_listing_created');
                $table->index(['repair_request_id', 'movement_type'], 'idx_spsm_req_type');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spare_part_stock_movements');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE spare_part_listings DROP CONSTRAINT IF EXISTS chk_spare_part_non_negative_inventory');
        }

        if (Schema::hasColumn('spare_part_listings', 'returned_count')) {
            Schema::table('spare_part_listings', function (Blueprint $table) {
                $table->dropColumn('returned_count');
            });
        }
    }
};

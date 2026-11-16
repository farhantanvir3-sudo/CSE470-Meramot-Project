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
        if (!Schema::hasTable('spare_part_warranties')) {
            Schema::create('spare_part_warranties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('spare_part_listing_id')->unique()->constrained('spare_part_listings')->cascadeOnDelete();
                $table->foreignId('shop_id')->constrained('repair_shops')->cascadeOnDelete();
                $table->foreignId('vendor_profile_id')->nullable()->constrained('vendor_profiles')->nullOnDelete();
                $table->unsignedInteger('duration_days');
                $table->string('coverage_type', 40)->default('FULL_PROTECTION');
                $table->boolean('covers_refund')->default(true);
                $table->boolean('covers_replacement')->default(true);
                $table->boolean('covers_free_repair')->default(true);
                $table->text('terms')->nullable();
                $table->timestamps();

                $table->index(['shop_id', 'coverage_type'], 'idx_sp_warranties_shop_cov');
            });
        }

        if (!Schema::hasTable('issued_warranties')) {
            Schema::create('issued_warranties', function (Blueprint $table) {
                $table->id();
                $table->foreignId('repair_request_id')->constrained('repair_requests')->cascadeOnDelete();
                $table->foreignId('repair_job_id')->nullable()->constrained('repair_jobs')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('shop_id')->nullable()->constrained('repair_shops')->nullOnDelete();
                $table->foreignId('spare_part_listing_id')->nullable()->constrained('spare_part_listings')->nullOnDelete();
                $table->foreignId('spare_part_warranty_id')->nullable()->constrained('spare_part_warranties')->nullOnDelete();
                $table->string('line_key', 140);
                $table->string('spec_code', 100)->nullable();
                $table->string('item_title', 220);
                $table->string('item_type', 32)->default('SPARE_PART');
                $table->unsignedInteger('quantity')->default(1);
                $table->decimal('unit_price', 12, 2)->default(0);
                $table->string('coverage_type', 40)->default('FULL_PROTECTION');
                $table->text('terms')->nullable();
                $table->unsignedInteger('duration_days');
                $table->timestamp('starts_at');
                $table->timestamp('expires_at');
                $table->string('status', 32)->default('ACTIVE');
                $table->unsignedInteger('claims_count')->default(0);
                $table->unsignedInteger('approved_claims_count')->default(0);
                $table->unsignedInteger('rejected_claims_count')->default(0);
                $table->unsignedInteger('replacements_issued')->default(0);
                $table->unsignedInteger('repairs_issued')->default(0);
                $table->decimal('refunded_amount', 12, 2)->default(0);
                $table->timestamps();

                $table->unique(['repair_request_id', 'line_key'], 'uq_issued_warranties_req_line');
                $table->index(['customer_id', 'expires_at'], 'idx_issued_warranties_cust_exp');
                $table->index(['status', 'expires_at'], 'idx_issued_warranties_status_exp');
            });
        }

        if (!Schema::hasTable('warranty_claims')) {
            Schema::create('warranty_claims', function (Blueprint $table) {
                $table->id();
                $table->foreignId('issued_warranty_id')->constrained('issued_warranties')->cascadeOnDelete();
                $table->foreignId('repair_request_id')->constrained('repair_requests')->cascadeOnDelete();
                $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('shop_id')->nullable()->constrained('repair_shops')->nullOnDelete();
                $table->foreignId('spare_part_listing_id')->nullable()->constrained('spare_part_listings')->nullOnDelete();
                $table->unsignedInteger('claim_number');
                $table->unsignedInteger('claimed_quantity')->default(1);
                $table->string('preferred_remedy', 32)->default('AUTO');
                $table->text('customer_explanation');
                $table->json('customer_image_urls')->nullable();
                $table->json('customer_image_hashes')->nullable();
                $table->string('status', 32)->default('PENDING');
                $table->string('admin_decision', 32)->nullable();
                $table->text('admin_reply')->nullable();
                $table->json('admin_image_urls')->nullable();
                $table->decimal('refund_amount', 12, 2)->nullable();
                $table->foreignId('replacement_movement_id')->nullable()->constrained('spare_part_stock_movements')->nullOnDelete();
                $table->foreignId('followup_repair_request_id')->nullable()->constrained('repair_requests')->nullOnDelete();
                $table->unsignedSmallInteger('misuse_risk_score')->default(0);
                $table->json('misuse_flags')->nullable();
                $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('reviewed_at')->nullable();
                $table->timestamps();

                $table->index(['status', 'created_at'], 'idx_warranty_claims_status_created');
                $table->index(['customer_id', 'created_at'], 'idx_warranty_claims_cust_created');
                $table->index(['issued_warranty_id', 'claim_number'], 'idx_warranty_claims_item_num');
            });
        }

        $isPg = DB::getDriverName() === 'pgsql';
        if ($isPg) {
            DB::statement("
                UPDATE spare_part_listings
                SET warranty_days = 0
                WHERE title ILIKE '%Flux%'
                   OR title ILIKE '%Paste%'
                   OR title ILIKE '%Adhesive%'
                   OR title ILIKE '%Tape%'
            ");
            DB::statement("
                UPDATE spare_part_listings
                SET warranty_days = 180
                WHERE warranty_days = 30
                  AND (part_category ILIKE '%Battery%' OR title ILIKE '%Battery%')
            ");
            DB::statement("
                UPDATE spare_part_listings
                SET warranty_days = 90
                WHERE warranty_days = 30
                  AND (
                      part_category ILIKE '%Display%'
                      OR part_category ILIKE '%Keyboard%'
                      OR part_category ILIKE '%Charger%'
                      OR part_category ILIKE '%Motherboard%'
                      OR part_category ILIKE '%Board%'
                  )
            ");
            DB::statement("
                INSERT INTO spare_part_warranties (
                    spare_part_listing_id,
                    shop_id,
                    vendor_profile_id,
                    duration_days,
                    coverage_type,
                    covers_refund,
                    covers_replacement,
                    covers_free_repair,
                    terms,
                    created_at,
                    updated_at
                )
                SELECT
                    id,
                    shop_id,
                    vendor_profile_id,
                    warranty_days,
                    'FULL_PROTECTION',
                    TRUE,
                    TRUE,
                    TRUE,
                    'Covers manufacturing defects, premature hardware failure, and functional faults within the warranty window; excludes physical impact or liquid damage.',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                FROM spare_part_listings
                WHERE warranty_days > 0
                ON CONFLICT (spare_part_listing_id) DO NOTHING
            ");
        } else {
            DB::table('spare_part_listings')
                ->where(function ($q) {
                    $q->where('title', 'LIKE', '%Flux%')
                        ->orWhere('title', 'LIKE', '%Paste%')
                        ->orWhere('title', 'LIKE', '%Adhesive%')
                        ->orWhere('title', 'LIKE', '%Tape%');
                })
                ->update(['warranty_days' => 0]);

            DB::table('spare_part_listings')
                ->where('warranty_days', 30)
                ->where(function ($q) {
                    $q->where('part_category', 'LIKE', '%Battery%')
                        ->orWhere('title', 'LIKE', '%Battery%');
                })
                ->update(['warranty_days' => 180]);

            DB::table('spare_part_listings')
                ->where('warranty_days', 30)
                ->where(function ($q) {
                    $q->where('part_category', 'LIKE', '%Display%')
                        ->orWhere('part_category', 'LIKE', '%Keyboard%')
                        ->orWhere('part_category', 'LIKE', '%Charger%')
                        ->orWhere('part_category', 'LIKE', '%Motherboard%')
                        ->orWhere('part_category', 'LIKE', '%Board%');
                })
                ->update(['warranty_days' => 90]);

            DB::statement("
                INSERT INTO spare_part_warranties (
                    spare_part_listing_id,
                    shop_id,
                    vendor_profile_id,
                    duration_days,
                    coverage_type,
                    covers_refund,
                    covers_replacement,
                    covers_free_repair,
                    terms,
                    created_at,
                    updated_at
                )
                SELECT
                    id,
                    shop_id,
                    vendor_profile_id,
                    warranty_days,
                    'FULL_PROTECTION',
                    1,
                    1,
                    1,
                    'Covers manufacturing defects, premature hardware failure, and functional faults within the warranty window; excludes physical impact or liquid damage.',
                    CURRENT_TIMESTAMP,
                    CURRENT_TIMESTAMP
                FROM spare_part_listings
                WHERE warranty_days > 0
                  AND NOT EXISTS (
                      SELECT 1 FROM spare_part_warranties sw WHERE sw.spare_part_listing_id = spare_part_listings.id
                  )
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warranty_claims');
        Schema::dropIfExists('issued_warranties');
        Schema::dropIfExists('spare_part_warranties');
    }
};

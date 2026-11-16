<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vouchers')) {
            Schema::create('vouchers', function (Blueprint $table) {
                $table->id();
                $table->string('code', 40)->unique();
                $table->string('title', 140);
                $table->string('banner_subtitle', 240)->nullable();
                $table->text('description')->nullable();
                $table->string('cta_href', 240)->nullable();

                // LIMITED_TIME (unlimited uses within window/schedule) | USE_ONCE (1 time per customer)
                $table->string('voucher_type', 24)->default('LIMITED_TIME')->index();

                // ADMIN (featured platform-wide minus exclusions) | VENDOR (vendor-owned branches)
                $table->string('issuer_type', 20)->default('VENDOR')->index();

                // UNIVERSAL | ALL_BRANCHES | SPECIFIC_BRANCHES
                $table->string('branch_scope', 24)->default('ALL_BRANCHES');

                $table->foreignId('created_by_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();

                $table->foreignId('vendor_profile_id')
                    ->nullable()
                    ->constrained('vendor_profiles')
                    ->cascadeOnDelete();

                // PERCENTAGE | FIXED | FREE_COURIER
                $table->string('discount_type', 24)->default('PERCENTAGE');
                $table->decimal('discount_value', 10, 2)->default(0.00);
                $table->decimal('min_order_amount', 10, 2)->default(0.00);
                $table->decimal('max_discount_amount', 10, 2)->nullable();

                // Optional payment channel restriction: BKASH, NAGAD, ROCKET, CARD, SSLCOMMERZ, CASH
                $table->string('required_payment_method', 24)->nullable();

                // True for platform-wide 1st-order-ever vouchers (e.g. default Free Courier)
                $table->boolean('is_first_order_only')->default(false);

                // True ONLY for the single default First-Order Free Courier voucher
                $table->boolean('is_system_default')->default(false);

                // Homepage Featured Voucher stack ordering (for Admin Featured Vouchers)
                $table->boolean('is_featured')->default(false)->index();
                $table->unsignedInteger('featured_order')->default(100);

                $table->timestamp('starts_at')->nullable();
                $table->timestamp('expires_at')->nullable();

                // Optional recurring day-of-week schedule (JSON array of ints 0=Sun..6=Sat)
                $table->json('valid_days_of_week')->nullable();
                $table->string('daily_start_time', 5)->nullable();
                $table->string('daily_end_time', 5)->nullable();

                $table->boolean('is_active')->default(true)->index();
                $table->unsignedInteger('usage_count')->default(0);
                $table->timestamps();

                $table->index(['issuer_type', 'is_active', 'is_featured', 'featured_order'], 'vouchers_featured_stack_idx');
                $table->index(['vendor_profile_id', 'is_active']);
            });
        }

        if (!Schema::hasTable('voucher_shops')) {
            Schema::create('voucher_shops', function (Blueprint $table) {
                $table->id();
                $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
                $table->foreignId('shop_id')->constrained('repair_shops')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['voucher_id', 'shop_id']);
                $table->index('shop_id');
            });
        }

        if (!Schema::hasTable('voucher_excluded_shops')) {
            Schema::create('voucher_excluded_shops', function (Blueprint $table) {
                $table->id();
                $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
                $table->foreignId('shop_id')->constrained('repair_shops')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['voucher_id', 'shop_id']);
                $table->index('shop_id');
            });
        }

        if (!Schema::hasTable('voucher_redemptions')) {
            Schema::create('voucher_redemptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('voucher_id')->constrained('vouchers')->cascadeOnDelete();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('shop_id')->nullable()->constrained('repair_shops')->nullOnDelete();
                $table->foreignId('repair_request_id')->nullable()->constrained('repair_requests')->nullOnDelete();
                $table->string('voucher_code', 40);
                $table->decimal('discount_amount', 10, 2)->default(0.00);
                $table->timestamp('redeemed_at');
                $table->timestamps();

                $table->unique('repair_request_id');
                $table->index(['voucher_id', 'user_id']);
            });
        }

        if (Schema::hasTable('repair_requests') && !Schema::hasColumn('repair_requests', 'voucher_id')) {
            Schema::table('repair_requests', function (Blueprint $table) {
                $table->foreignId('voucher_id')
                    ->nullable()
                    ->after('quoted_final_amount')
                    ->constrained('vouchers')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_requests') && Schema::hasColumn('repair_requests', 'voucher_id')) {
            Schema::table('repair_requests', function (Blueprint $table) {
                $table->dropConstrainedForeignId('voucher_id');
            });
        }

        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('voucher_excluded_shops');
        Schema::dropIfExists('voucher_shops');
        Schema::dropIfExists('vouchers');
    }
};

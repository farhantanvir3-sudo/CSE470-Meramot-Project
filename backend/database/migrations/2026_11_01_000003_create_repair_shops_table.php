<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Repair Shops Table (Matching legacy Meramot Shop entity)
        Schema::create('repair_shops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_profile_id')->nullable()->constrained('vendor_profiles')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('banner_url')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->default('Dhaka');
            $table->string('area')->nullable();
            $table->decimal('latitude', 10, 8)->nullable();
            $table->decimal('longitude', 11, 8)->nullable();
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->decimal('rating', 3, 2)->default(5.00); // ratingAvg
            $table->integer('review_count')->default(0);
            $table->integer('price_level')->default(2);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->boolean('setup_complete')->default(true);
            $table->decimal('inspection_fee', 10, 2)->nullable();
            $table->decimal('base_labor_fee', 10, 2)->nullable();
            $table->decimal('pickup_fee', 10, 2)->nullable();
            $table->decimal('express_fee', 10, 2)->nullable();
            $table->string('opening_days')->nullable();
            $table->string('opening_hours')->nullable();
            $table->string('opening_hours_text')->nullable();
            $table->string('offer_summary')->nullable();
            $table->string('result_tag')->nullable();
            $table->integer('eta_minutes')->default(60);
            $table->boolean('has_voucher')->default(false);
            $table->boolean('free_delivery')->default(false);
            $table->boolean('has_deals')->default(false);
            $table->boolean('accepts_direct_orders')->default(true);
            $table->boolean('supports_pickup')->default(false);
            $table->boolean('supports_onsite_repair')->default(false);
            $table->decimal('delivery_radius_km', 5, 2)->nullable();
            $table->json('categories')->nullable();
            $table->json('specialties')->nullable();
            $table->timestamps();
        });

        // Shop Services Table (Matching legacy Meramot ShopService entity)
        Schema::create('shop_services', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('repair_shops')->cascadeOnDelete();
            $table->string('slug');
            $table->string('name');
            $table->string('short_description')->nullable();
            $table->text('description')->nullable();
            $table->string('device_type')->nullable();
            $table->string('issue_category')->nullable();
            $table->enum('pricing_type', ['FIXED', 'STARTING_FROM', 'INSPECTION_REQUIRED'])->default('INSPECTION_REQUIRED');
            $table->decimal('base_price', 10, 2)->nullable();
            $table->decimal('price_max', 10, 2)->nullable();
            $table->integer('estimated_days_min')->nullable();
            $table->integer('estimated_days_max')->nullable();
            $table->boolean('includes_pickup')->default(false);
            $table->boolean('includes_delivery')->default(false);
            $table->integer('sort_order')->default(0);
            $table->boolean('is_featured')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Shop Staff Table (Matching legacy Meramot ShopStaff entity)
        Schema::create('shop_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('repair_shops')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['OWNER', 'MANAGER', 'STAFF'])->default('STAFF');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['shop_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_staff');
        Schema::dropIfExists('shop_services');
        Schema::dropIfExists('repair_shops');
    }
};

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
        Schema::create('spare_part_listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('repair_shops')->cascadeOnDelete();
            $table->foreignId('vendor_profile_id')->nullable()->constrained('vendor_profiles')->nullOnDelete();
            $table->string('slug')->unique();
            $table->string('product_group_key')->index();
            $table->string('sku')->nullable();
            $table->string('title');
            $table->string('part_category', 80)->index();
            $table->string('brand', 80)->index();
            $table->string('compatible_model', 140)->nullable();
            $table->string('condition_label', 60)->default('Brand New');
            $table->string('image_url', 500)->nullable();
            $table->json('gallery_urls')->nullable();
            $table->json('highlights')->nullable();
            $table->text('description')->nullable();

            // Etel & LaptopWorld specification / quality options
            $table->boolean('has_specs')->default(false);
            $table->string('spec_label', 60)->nullable()->default('Quality');
            $table->json('spec_options')->nullable();

            // Pricing & Limited-Time / Indefinite Offer System ("Sale!" circular red badge + strikethrough normal price)
            $table->decimal('base_price', 12, 2);
            $table->decimal('normal_price', 12, 2)->nullable();
            $table->decimal('max_price', 12, 2)->nullable();
            $table->decimal('normal_max_price', 12, 2)->nullable();
            $table->decimal('regular_price', 12, 2)->nullable();
            $table->boolean('offer_enabled')->default(false)->index();
            $table->decimal('offer_price', 12, 2)->nullable();
            $table->decimal('offer_discount_pct', 5, 2)->nullable();
            $table->timestamp('offer_starts_at')->nullable();
            $table->timestamp('offer_ends_at')->nullable();

            // Optional workshop installation & warranty
            $table->decimal('assembly_fee', 10, 2)->nullable();
            $table->unsignedInteger('warranty_days')->default(30);

            // Inventory & popularity
            $table->unsignedInteger('stock_quantity')->default(250);
            $table->unsignedInteger('sold_count')->default(0);

            // AliExpress-style progressive wholesale discount tiers
            $table->boolean('wholesale_enabled')->default(false)->index();
            $table->unsignedInteger('wholesale_min_qty')->default(100);
            $table->decimal('wholesale_base_discount_pct', 5, 2)->default(5.00);
            $table->unsignedInteger('wholesale_step_qty')->default(100);
            $table->decimal('wholesale_step_discount_pct', 5, 2)->default(2.00);
            $table->decimal('wholesale_max_discount_pct', 5, 2)->default(25.00);

            // Dispatch prep & visibility
            $table->unsignedSmallInteger('handling_hours')->default(1);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['product_group_key', 'is_active']);
            $table->index(['part_category', 'brand', 'is_active']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('spare_part_listings');
    }
};

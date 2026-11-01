<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Vendor Profiles / Vendor Application Table (Matching legacy Meramot VendorApplication)
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('owner_name')->nullable();
            $table->string('business_email')->nullable();
            $table->string('phone')->nullable();
            $table->string('shop_name');
            $table->string('business_license_no')->nullable(); // tradeLicenseNo
            $table->string('nid_number')->nullable();
            $table->string('verification_status')->default('pending'); // 'pending', 'under_review', 'approved', 'rejected'
            $table->text('rejection_reason')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->text('shop_address')->nullable();
            $table->string('city')->nullable()->default('Dhaka');
            $table->string('area')->nullable();
            $table->decimal('shop_latitude', 10, 8)->nullable();
            $table->decimal('shop_longitude', 11, 8)->nullable();
            $table->json('categories')->nullable();
            $table->json('specialties')->nullable();
            $table->boolean('courier_pickup')->default(false);
            $table->boolean('in_shop_repair')->default(true);
            $table->boolean('spare_parts')->default(false);
            $table->text('notes')->nullable();
            $table->string('logo_url')->nullable();
            $table->string('documents_path')->nullable();
            $table->foreignId('reviewed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->decimal('commission_rate', 5, 2)->default(10.00);
            $table->decimal('rating', 3, 2)->default(5.00);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_profiles');
    }
};

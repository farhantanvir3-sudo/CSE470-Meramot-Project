<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vendor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('shop_name');
            $table->string('business_license_no')->nullable();
            $table->string('nid_number')->nullable();
            $table->string('verification_status')->default('pending'); // 'pending', 'approved', 'rejected'
            $table->text('rejection_reason')->nullable();
            $table->text('shop_address')->nullable();
            $table->decimal('shop_latitude', 10, 8)->nullable();
            $table->decimal('shop_longitude', 11, 8)->nullable();
            $table->json('categories')->nullable();
            $table->string('documents_path')->nullable();
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

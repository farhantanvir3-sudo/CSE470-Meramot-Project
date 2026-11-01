<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('repair_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->string('device_type');
            $table->string('brand');
            $table->string('model_name');
            $table->string('serial_number')->nullable();
            $table->string('issue_title');
            $table->text('issue_description');
            $table->json('photos')->nullable();
            $table->string('status')->default('pending_review');
            $table->string('preferred_service_type')->default('pickup_dropoff');
            $table->text('ai_diagnostic_summary')->nullable();
            $table->text('pickup_address')->nullable();
            $table->unsignedBigInteger('selected_bid_id')->nullable();
            $table->timestamps();
        });

        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->decimal('proposed_cost', 10, 2);
            $table->integer('estimated_days')->default(2);
            $table->integer('warranty_days')->default(30);
            $table->json('line_items')->nullable();
            $table->text('technician_note')->nullable();
            $table->string('status')->default('submitted');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bids');
        Schema::dropIfExists('repair_requests');
    }
};

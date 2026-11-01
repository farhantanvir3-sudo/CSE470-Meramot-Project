<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Repair Requests Table (Matching legacy Meramot RepairRequest entity)
        Schema::create('repair_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->enum('source', ['MARKETPLACE', 'DIRECT_SERVICE', 'DIRECT_CUSTOM_SHOP'])->default('MARKETPLACE');
            $table->foreignId('requested_shop_id')->nullable()->constrained('repair_shops')->nullOnDelete();
            $table->unsignedBigInteger('requested_service_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('device_type');
            $table->string('brand')->nullable();
            $table->string('model_name')->nullable();
            $table->string('serial_number')->nullable();
            $table->string('issue_category')->nullable();
            $table->text('problem')->nullable();
            $table->json('image_urls')->nullable();
            $table->enum('mode', ['CHECKUP_ONLY', 'DIRECT_REPAIR', 'CHECKUP_AND_REPAIR'])->default('CHECKUP_AND_REPAIR');
            $table->string('status')->default('PENDING'); // DRAFT, PENDING, BIDDING, ASSIGNED, PICKUP_SCHEDULED, PICKED_UP, AT_SHOP, DIAGNOSING, WAITING_APPROVAL, REPAIRING, RETURN_SCHEDULED, RETURNING, COMPLETED, CANCELLED, REJECTED
            $table->boolean('preferred_pickup')->default(false);
            $table->string('preferred_service_type')->default('pickup_dropoff');
            $table->enum('delivery_type', ['REGULAR', 'EXPRESS'])->nullable();
            $table->string('contact_phone')->nullable();
            $table->text('pickup_address')->nullable();
            $table->text('dropoff_address')->nullable();
            $table->decimal('checkup_fee', 10, 2)->nullable();
            $table->decimal('quoted_final_amount', 10, 2)->nullable();
            $table->text('ai_summary')->nullable(); // Cached AI diagnosis summary
            $table->unsignedBigInteger('selected_bid_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamps();
        });

        // Bids Table (Matching legacy Meramot Bid entity)
        Schema::create('bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained('repair_requests')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('repair_shops')->cascadeOnDelete();
            $table->decimal('parts_cost', 10, 2)->default(0.00);
            $table->decimal('labor_cost', 10, 2)->default(0.00);
            $table->decimal('proposed_cost', 10, 2); // totalCost
            $table->integer('estimated_days')->default(2);
            $table->integer('warranty_days')->default(30);
            $table->json('line_items')->nullable();
            $table->text('technician_note')->nullable(); // notes
            $table->string('status')->default('ACTIVE'); // ACTIVE, ACCEPTED, DECLINED, EXPIRED, WITHDRAWN
            $table->timestamps();
        });

        // Repair Jobs Table (Matching legacy Meramot RepairJob entity)
        Schema::create('repair_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained('repair_requests')->cascadeOnDelete()->unique();
            $table->foreignId('shop_id')->constrained('repair_shops')->cascadeOnDelete();
            $table->foreignId('accepted_bid_id')->nullable()->constrained('bids')->nullOnDelete();
            $table->string('status')->default('CREATED'); // CREATED, PICKUP_SCHEDULED, PICKED_UP, AT_SHOP, DIAGNOSING, WAITING_APPROVAL, REPAIRING, READY_TO_RETURN, RETURNING, COMPLETED, CANCELLED
            $table->text('diagnosis_notes')->nullable();
            $table->decimal('final_quoted_amount', 10, 2)->nullable();
            $table->json('final_quote_items')->nullable();
            $table->boolean('customer_approved')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_jobs');
        Schema::dropIfExists('bids');
        Schema::dropIfExists('repair_requests');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coverage Zones Table (Matching legacy Meramot CoverageZone)
        Schema::create('coverage_zones', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('city')->nullable()->default('Dhaka');
            $table->string('area')->nullable();
            $table->string('postal_code')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Delivery Agents / Rider Profiles Table (Matching legacy Meramot RiderProfile)
        Schema::create('delivery_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete()->unique();
            $table->string('vehicle_type')->nullable()->default('motorcycle');
            $table->string('nid_number')->nullable();
            $table->string('license_number')->nullable();
            $table->string('nid_document_url')->nullable();
            $table->string('education_document_url')->nullable();
            $table->string('cv_document_url')->nullable();
            $table->string('profile_picture_url')->nullable();
            $table->enum('status', ['OFFLINE', 'AVAILABLE', 'BUSY', 'SUSPENDED'])->default('OFFLINE');
            $table->boolean('is_active')->default(true);
            $table->enum('registration_status', ['PENDING', 'APPROVED', 'REJECTED'])->default('APPROVED');
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->decimal('total_earnings', 10, 2)->default(0.00);
            $table->timestamps();
        });

        // Rider Coverage Junction Table (Matching legacy Meramot RiderCoverage)
        Schema::create('rider_coverages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained('delivery_agents')->cascadeOnDelete();
            $table->foreignId('coverage_zone_id')->constrained('coverage_zones')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['delivery_agent_id', 'coverage_zone_id']);
        });

        // Delivery Jobs Table (Matching legacy Meramot Delivery entity)
        Schema::create('delivery_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_job_id')->nullable()->constrained('repair_jobs')->cascadeOnDelete();
            $table->foreignId('repair_request_id')->nullable()->constrained('repair_requests')->cascadeOnDelete();
            $table->foreignId('delivery_agent_id')->nullable()->constrained('delivery_agents')->nullOnDelete();
            $table->foreignId('coverage_zone_id')->nullable()->constrained('coverage_zones')->nullOnDelete();
            $table->enum('direction', ['TO_SHOP', 'TO_CUSTOMER'])->default('TO_SHOP');
            $table->enum('type', ['REGULAR', 'EXPRESS'])->default('REGULAR');
            $table->string('status')->default('PENDING'); // PENDING, SCHEDULED, DISPATCHED, PICKED_UP, IN_TRANSIT, DELIVERED, FAILED, CANCELLED
            $table->string('partner_name')->nullable();
            $table->string('external_order_id')->nullable();
            $table->string('tracking_code')->nullable();
            $table->string('rider_name')->nullable();
            $table->string('rider_phone')->nullable();
            $table->text('pickup_address');
            $table->text('dropoff_address');
            $table->string('pickup_otp', 6)->nullable();
            $table->string('dropoff_otp', 6)->nullable();
            $table->decimal('distance_km', 6, 2)->nullable();
            $table->decimal('fee', 8, 2)->nullable()->default(150.00);
            $table->timestamp('scheduled_at')->nullable();
            $table->timestamp('dispatched_at')->nullable();
            $table->timestamp('picked_up_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->json('milestone_timestamps')->nullable();
            $table->timestamps();
        });

        // Rider Chat Messages Table (Matching legacy Meramot DeliveryChatMessage entity)
        Schema::create('rider_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_job_id')->constrained('delivery_jobs')->cascadeOnDelete();
            $table->foreignId('sender_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('sender_role')->default('DELIVERY');
            $table->foreignId('recipient_user_id')->constrained('users')->cascadeOnDelete();
            $table->text('message');
            $table->timestamps();
        });

        // Rider Earnings Table
        Schema::create('rider_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained('delivery_agents')->cascadeOnDelete();
            $table->foreignId('delivery_job_id')->constrained('delivery_jobs')->cascadeOnDelete();
            $table->decimal('trip_fee', 8, 2)->default(150.00);
            $table->decimal('bonus_amount', 8, 2)->default(0.00);
            $table->decimal('total_earned', 8, 2)->default(150.00);
            $table->string('payout_status')->default('unpaid');
            $table->string('payout_reference')->nullable();
            $table->timestamps();
        });

        // Location Logs Table
        Schema::create('location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained('delivery_agents')->cascadeOnDelete();
            $table->foreignId('delivery_job_id')->constrained('delivery_jobs')->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_logs');
        Schema::dropIfExists('rider_earnings');
        Schema::dropIfExists('rider_chat_messages');
        Schema::dropIfExists('delivery_jobs');
        Schema::dropIfExists('rider_coverages');
        Schema::dropIfExists('delivery_agents');
        Schema::dropIfExists('coverage_zones');
    }
};

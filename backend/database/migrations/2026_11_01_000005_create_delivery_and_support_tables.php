<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_agents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('nid_number');
            $table->string('vehicle_type')->default('motorcycle');
            $table->string('license_number');
            $table->string('verification_status')->default('pending');
            $table->decimal('current_latitude', 10, 8)->nullable();
            $table->decimal('current_longitude', 11, 8)->nullable();
            $table->boolean('is_available')->default(false);
            $table->decimal('total_earnings', 10, 2)->default(0.00);
            $table->timestamps();
        });

        Schema::create('delivery_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('job_type')->default('pickup_from_customer');
            $table->string('status')->default('unassigned');
            $table->text('pickup_address')->nullable();
            $table->text('dropoff_address')->nullable();
            $table->string('pickup_otp', 6)->nullable();
            $table->string('dropoff_otp', 6)->nullable();
            $table->json('milestone_timestamps')->nullable();
            $table->timestamps();
        });

        Schema::create('rider_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_job_id')->constrained()->cascadeOnDelete();
            $table->decimal('trip_fee', 8, 2)->default(150.00);
            $table->decimal('bonus_amount', 8, 2)->default(0.00);
            $table->decimal('total_earned', 8, 2)->default(150.00);
            $table->string('payout_status')->default('unpaid');
            $table->string('payout_reference')->nullable();
            $table->timestamps();
        });

        Schema::create('location_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained()->cascadeOnDelete();
            $table->foreignId('delivery_job_id')->constrained()->cascadeOnDelete();
            $table->decimal('latitude', 10, 8);
            $table->decimal('longitude', 11, 8);
            $table->timestamp('recorded_at')->useCurrent();
            $table->timestamps();
        });

        Schema::create('rider_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_agent_id')->constrained()->cascadeOnDelete();
            $table->string('sender_role')->default('rider');
            $table->text('message_text');
            $table->boolean('is_read')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rider_chat_messages');
        Schema::dropIfExists('location_logs');
        Schema::dropIfExists('rider_earnings');
        Schema::dropIfExists('delivery_jobs');
        Schema::dropIfExists('delivery_agents');
    }
};

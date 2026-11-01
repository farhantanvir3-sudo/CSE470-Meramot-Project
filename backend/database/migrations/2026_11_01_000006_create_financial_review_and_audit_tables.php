<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('device_category');
            $table->decimal('fixed_price', 10, 2);
            $table->integer('estimated_turnaround_hours')->default(24);
            $table->integer('warranty_months')->default(3);
            $table->json('inclusions')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->timestamps();
        });

        Schema::create('shop_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_shop_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedBigInteger('repair_request_id')->nullable();
            $table->integer('rating')->default(5);
            $table->text('review_comment');
            $table->boolean('is_verified_purchase')->default(true);
            $table->timestamps();
        });

        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('session_token')->unique();
            $table->string('device_category')->default('Laptop');
            $table->json('conversation_history')->nullable();
            $table->boolean('is_resolved')->default(false);
            $table->timestamps();
        });

        Schema::create('financial_ledgers', function (Blueprint $table) {
            $table->id();
            $table->enum('entry_type', ['credit', 'debit']);
            $table->string('account_category');
            $table->decimal('amount', 12, 2);
            $table->string('currency')->default('BDT');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->decimal('balance_after', 12, 2);
            $table->string('description')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->string('transaction_id')->unique();
            $table->foreignId('repair_request_id')->constrained()->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('gateway')->default('sslcommerz');
            $table->string('payment_status')->default('pending');
            $table->string('invoice_number')->unique();
            $table->timestamps();
        });

        Schema::create('vendor_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendor_profiles')->cascadeOnDelete();
            $table->decimal('gross_earnings', 10, 2);
            $table->decimal('platform_commission_amount', 10, 2);
            $table->decimal('net_payout_amount', 10, 2);
            $table->string('payout_method')->default('bKash');
            $table->string('status')->default('pending');
            $table->timestamp('disbursed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('moderator_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action_taken');
            $table->text('reason');
            $table->string('flagged_entity_type')->nullable();
            $table->unsignedBigInteger('flagged_entity_id')->nullable();
            $table->timestamps();
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('event_action');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->json('payload_before')->nullable();
            $table->json('payload_after')->nullable();
            $table->boolean('is_suspicious')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('moderation_logs');
        Schema::dropIfExists('vendor_payouts');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('financial_ledgers');
        Schema::dropIfExists('ai_chat_sessions');
        Schema::dropIfExists('shop_reviews');
        Schema::dropIfExists('service_packages');
    }
};

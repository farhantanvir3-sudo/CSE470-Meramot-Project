<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Invoices Table (Matching legacy Meramot Invoice entity)
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->nullable()->constrained('repair_requests')->nullOnDelete();
            $table->foreignId('repair_job_id')->nullable()->constrained('repair_jobs')->nullOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('repair_shops')->nullOnDelete();
            $table->enum('type', ['CHECKUP', 'REPAIR', 'DELIVERY', 'FINAL'])->default('FINAL');
            $table->enum('status', ['DRAFT', 'ISSUED', 'PARTIALLY_PAID', 'PAID', 'VOID', 'REFUNDED'])->default('DRAFT');
            $table->string('invoice_number')->unique();
            $table->decimal('subtotal_amount', 10, 2)->default(0.00);
            $table->decimal('delivery_amount', 10, 2)->default(0.00);
            $table->decimal('discount_amount', 10, 2)->default(0.00);
            $table->decimal('commission_amount', 10, 2)->default(0.00);
            $table->decimal('total_amount', 10, 2)->default(0.00);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 2. Invoice Line Items Table (Matching legacy Meramot InvoiceLineItem entity)
        Schema::create('invoice_line_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('invoices')->cascadeOnDelete();
            $table->unsignedBigInteger('shop_service_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->integer('quantity')->default(1);
            $table->decimal('unit_price', 10, 2)->default(0.00);
            $table->decimal('total_price', 10, 2)->default(0.00);
            $table->timestamps();
        });

        // 3. Payment Transactions Table (Matching legacy Meramot Payment entity)
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('repair_request_id')->nullable()->constrained('repair_requests')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('BDT');
            $table->enum('payment_method', ['BKASH', 'NAGAD', 'CARD', 'CASH', 'SSLCOMMERZ'])->default('SSLCOMMERZ');
            $table->enum('status', ['PENDING', 'AUTHORIZED', 'PAID', 'FAILED', 'REFUNDED', 'PARTIALLY_REFUNDED'])->default('PENDING');
            $table->string('transaction_reference')->unique()->nullable();
            $table->text('gateway_response')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 4. Vendor Payouts Table (Matching legacy Meramot VendorPayout entity)
        Schema::create('vendor_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained('repair_shops')->nullOnDelete();
            $table->foreignId('delivery_agent_id')->nullable()->constrained('delivery_agents')->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BDT');
            $table->enum('status', ['PENDING', 'PROCESSING', 'PAID', 'FAILED', 'CANCELLED'])->default('PENDING');
            $table->string('reference_code')->unique()->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        // 5. Financial Ledgers Table (Matching legacy Meramot LedgerEntry entity)
        Schema::create('financial_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->nullable()->constrained('repair_shops')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('invoices')->nullOnDelete();
            $table->foreignId('vendor_payout_id')->nullable()->constrained('vendor_payouts')->nullOnDelete();
            $table->enum('entry_type', [
                'CUSTOMER_PAYMENT',
                'PLATFORM_COMMISSION',
                'VENDOR_EARNING',
                'VENDOR_PAYOUT',
                'DELIVERY_FEE',
                'DELIVERY_PAYOUT',
                'REFUND',
                'ADJUSTMENT'
            ]);
            $table->enum('direction', ['CREDIT', 'DEBIT']);
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('BDT');
            $table->text('description')->nullable();
            $table->timestamp('effective_at')->useCurrent();
            $table->timestamps();
        });

        // 6. Escrow Ledgers Table (Matching legacy Meramot EscrowLedger entity)
        Schema::create('escrow_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->foreignId('repair_request_id')->nullable()->constrained('repair_requests')->nullOnDelete();
            $table->foreignId('customer_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('vendor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->decimal('gross_amount', 10, 2)->nullable();
            $table->decimal('platform_commission_amount', 10, 2)->nullable();
            $table->decimal('vendor_net_amount', 10, 2)->nullable();
            $table->string('action')->default('HOLD');
            $table->text('note')->nullable();
            $table->timestamps();
        });

        // 7. Shop Reviews / Ratings Table (Matching legacy Meramot Rating entity)
        Schema::create('shop_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_shop_id')->constrained('repair_shops')->cascadeOnDelete();
            $table->foreignId('customer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('repair_request_id')->nullable()->constrained('repair_requests')->nullOnDelete();
            $table->integer('rating'); // score 1 to 5
            $table->text('review_comment')->nullable(); // review text
            $table->boolean('is_verified_purchase')->default(true);
            $table->boolean('is_hidden')->default(false);
            $table->timestamp('moderated_at')->nullable();
            $table->foreignId('moderated_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('moderation_note')->nullable();
            $table->timestamps();
        });

        // 8. Service Packages Table (Feature R8)
        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('device_category');
            $table->decimal('fixed_price', 10, 2);
            $table->integer('estimated_turnaround_hours')->default(24);
            $table->integer('warranty_months')->default(6);
            $table->json('inclusions')->nullable();
            $table->boolean('is_popular')->default(false);
            $table->timestamps();
        });

        // 9. Carts & Cart Items Tables (Matching legacy Meramot Cart and CartItem)
        Schema::create('carts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('repair_shops')->cascadeOnDelete();
            $table->enum('status', ['ACTIVE', 'CHECKED_OUT', 'ABANDONED'])->default('ACTIVE');
            $table->timestamps();
        });

        Schema::create('cart_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cart_id')->constrained('carts')->cascadeOnDelete();
            $table->string('service_name');
            $table->text('description')->nullable();
            $table->decimal('price', 10, 2);
            $table->integer('quantity')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        // 10. AI Chat Sessions & Messages (Matching legacy Meramot AiChatSession)
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('title')->default('Device Hardware Diagnostic');
            $table->string('device_category')->default('Laptop');
            $table->timestamps();
        });

        Schema::create('ai_chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_chat_session_id')->constrained('ai_chat_sessions')->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant'])->default('user');
            $table->text('text');
            $table->timestamps();
        });

        // 11. Support Tickets & Messages (Matching legacy Meramot SupportTicket)
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('repair_shops')->nullOnDelete();
            $table->foreignId('repair_request_id')->nullable()->constrained('repair_requests')->nullOnDelete();
            $table->foreignId('repair_job_id')->nullable()->constrained('repair_jobs')->nullOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('subject');
            $table->text('message');
            $table->enum('status', ['OPEN', 'IN_PROGRESS', 'RESOLVED', 'CLOSED', 'ESCALATED'])->default('OPEN');
            $table->string('priority')->nullable()->default('normal');
            $table->text('admin_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('support_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ticket_id')->constrained('support_tickets')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->string('sender_type')->default('user');
            $table->text('message');
            $table->json('attachment_urls')->nullable();
            $table->timestamps();
        });

        // 12. Warranties Table (Matching legacy Meramot Warranty entity)
        Schema::create('warranties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_job_id')->constrained('repair_jobs')->cascadeOnDelete()->unique();
            $table->string('title');
            $table->text('details')->nullable();
            $table->integer('duration_days')->default(90);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->timestamps();
        });

        // 13. Recently Viewed Table (Matching legacy Meramot RecentlyViewed entity)
        Schema::create('recently_viewed', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('repair_shops')->cascadeOnDelete();
            $table->timestamp('viewed_at')->useCurrent();
            $table->timestamps();
        });

        // 14. Notification Logs Table (Feature R14 - Matching NotificationLog)
        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->nullable()->constrained('repair_requests')->nullOnDelete();
            $table->unsignedBigInteger('delivery_job_id')->nullable();
            $table->enum('channel', ['SMS', 'EMAIL', 'PUSH', 'IN_APP'])->default('SMS');
            $table->string('recipient');
            $table->string('subject')->nullable();
            $table->text('body');
            $table->string('provider')->nullable(); // BulkSMS BD / Resend
            $table->string('provider_ref')->nullable();
            $table->enum('status', ['QUEUED', 'SENT', 'FAILED'])->default('QUEUED');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        // 15. Offer Banners Table (Matching legacy Meramot OfferBanner)
        Schema::create('offer_banners', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('subtitle')->nullable();
            $table->string('image_url')->nullable();
            $table->string('cta_label')->nullable();
            $table->string('cta_href')->nullable();
            $table->string('background_color')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('position')->default(0);
            $table->string('section_type')->default('HERO_OFFER');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();
        });

        // 16. Dispute Cases & Notes Tables (Matching legacy Meramot DisputeCase)
        Schema::create('dispute_cases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('repair_request_id')->nullable()->constrained('repair_requests')->nullOnDelete();
            $table->foreignId('repair_job_id')->nullable()->constrained('repair_jobs')->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained('payment_transactions')->nullOnDelete();
            $table->foreignId('opened_by_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('against_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('assigned_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'OPEN',
                'INVESTIGATING',
                'WAITING_EVIDENCE',
                'WAITING_RESPONSE',
                'RESOLVED',
                'REFUNDED',
                'PARTIALLY_REFUNDED',
                'REJECTED'
            ])->default('OPEN');
            $table->text('resolution')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('dispute_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dispute_case_id')->constrained('dispute_cases')->cascadeOnDelete();
            $table->foreignId('author_id')->constrained('users')->cascadeOnDelete();
            $table->text('note');
            $table->boolean('is_internal')->default(true);
            $table->timestamps();
        });

        // 17. Refunds Table (Matching legacy Meramot Refund)
        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained('payment_transactions')->cascadeOnDelete();
            $table->foreignId('dispute_case_id')->nullable()->constrained('dispute_cases')->nullOnDelete();
            $table->foreignId('approved_by_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('amount', 10, 2);
            $table->text('reason')->nullable();
            $table->enum('status', ['PENDING', 'APPROVED', 'PROCESSED', 'REJECTED'])->default('PENDING');
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
        });

        // 18. Moderation Logs Table (Feature R19 - Nawshad)
        Schema::create('moderation_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->string('target_type'); // 'shop', 'user', 'review'
            $table->unsignedBigInteger('target_id');
            $table->string('violation_type'); // 'fraudulent_quote', 'abusive_chat', 'fake_review'
            $table->string('action_taken'); // 'suspended', 'warned', 'content_removed'
            $table->text('reason');
            $table->timestamps();
        });

        // 19. System Audit Logs Table (Feature R20 - Nawshad)
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // 'override_milestone', 'disburse_payout', 'privelege_escalation'
            $table->string('target_resource');
            $table->json('payload_before')->nullable();
            $table->json('payload_after')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('moderation_logs');
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('dispute_notes');
        Schema::dropIfExists('dispute_cases');
        Schema::dropIfExists('offer_banners');
        Schema::dropIfExists('notification_logs');
        Schema::dropIfExists('recently_viewed');
        Schema::dropIfExists('warranties');
        Schema::dropIfExists('support_messages');
        Schema::dropIfExists('support_tickets');
        Schema::dropIfExists('ai_chat_messages');
        Schema::dropIfExists('ai_chat_sessions');
        Schema::dropIfExists('cart_items');
        Schema::dropIfExists('carts');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('shop_reviews');
        Schema::dropIfExists('escrow_ledgers');
        Schema::dropIfExists('financial_ledgers');
        Schema::dropIfExists('vendor_payouts');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('invoice_line_items');
        Schema::dropIfExists('invoices');
    }
};

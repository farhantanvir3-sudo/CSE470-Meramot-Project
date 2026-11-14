<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('ai_summary_logs')) {
            Schema::create('ai_summary_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('repair_request_id')->constrained('repair_requests')->cascadeOnDelete();
                $table->foreignId('triggered_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('provider', 40)->default('laravel-heuristic');
                $table->string('model_name', 80)->nullable();
                $table->text('summary');
                $table->json('suspected_faults')->nullable();
                $table->json('suggested_parts')->nullable();
                $table->string('risk_level', 20)->default('MEDIUM');
                $table->decimal('estimated_bench_hours', 5, 2)->default(2.00);
                $table->decimal('estimated_cost_min', 10, 2)->nullable();
                $table->decimal('estimated_cost_max', 10, 2)->nullable();
                $table->integer('latency_ms')->default(0);
                $table->timestamps();

                $table->index(['repair_request_id', 'created_at']);
            });
        }

        Schema::table('repair_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('repair_requests', 'ai_triage_json')) {
                $table->json('ai_triage_json')->nullable()->after('ai_summary');
            }
            if (!Schema::hasColumn('repair_requests', 'voucher_code')) {
                $table->string('voucher_code', 40)->nullable()->after('quoted_final_amount');
            }
            if (!Schema::hasColumn('repair_requests', 'discount_amount')) {
                $table->decimal('discount_amount', 10, 2)->default(0.00)->after('voucher_code');
            }
            if (!Schema::hasColumn('repair_requests', 'scheduled_pickup_at')) {
                $table->timestamp('scheduled_pickup_at')->nullable()->after('preferred_pickup');
            }
        });

        Schema::table('vendor_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_profiles', 'alert_phone')) {
                $table->string('alert_phone', 25)->nullable()->after('phone');
            }
            if (!Schema::hasColumn('vendor_profiles', 'notify_sms_enabled')) {
                $table->boolean('notify_sms_enabled')->default(true)->after('rating');
            }
            if (!Schema::hasColumn('vendor_profiles', 'notify_email_enabled')) {
                $table->boolean('notify_email_enabled')->default(true)->after('notify_sms_enabled');
            }
            if (!Schema::hasColumn('vendor_profiles', 'notify_in_app_enabled')) {
                $table->boolean('notify_in_app_enabled')->default(true)->after('notify_email_enabled');
            }
            if (!Schema::hasColumn('vendor_profiles', 'notify_new_requests')) {
                $table->boolean('notify_new_requests')->default(true)->after('notify_in_app_enabled');
            }
            if (!Schema::hasColumn('vendor_profiles', 'notify_bid_updates')) {
                $table->boolean('notify_bid_updates')->default(true)->after('notify_new_requests');
            }
            if (!Schema::hasColumn('vendor_profiles', 'notify_payout_updates')) {
                $table->boolean('notify_payout_updates')->default(true)->after('notify_bid_updates');
            }
            if (!Schema::hasColumn('vendor_profiles', 'payout_method')) {
                $table->string('payout_method', 30)->default('BKASH')->after('notify_payout_updates');
            }
            if (!Schema::hasColumn('vendor_profiles', 'payout_account_name')) {
                $table->string('payout_account_name', 120)->nullable()->after('payout_method');
            }
            if (!Schema::hasColumn('vendor_profiles', 'payout_account_number')) {
                $table->string('payout_account_number', 60)->nullable()->after('payout_account_name');
            }
            if (!Schema::hasColumn('vendor_profiles', 'payout_bank_name')) {
                $table->string('payout_bank_name', 120)->nullable()->after('payout_account_number');
            }
            if (!Schema::hasColumn('vendor_profiles', 'payout_branch_name')) {
                $table->string('payout_branch_name', 120)->nullable()->after('payout_bank_name');
            }
        });

        Schema::table('delivery_agents', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_agents', 'payout_method')) {
                $table->string('payout_method', 30)->default('BKASH')->after('total_earnings');
            }
            if (!Schema::hasColumn('delivery_agents', 'payout_account_number')) {
                $table->string('payout_account_number', 60)->nullable()->after('payout_method');
            }
        });

        Schema::table('vendor_payouts', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_payouts', 'payout_method')) {
                $table->string('payout_method', 30)->nullable()->after('currency');
            }
            if (!Schema::hasColumn('vendor_payouts', 'payout_account')) {
                $table->string('payout_account', 100)->nullable()->after('payout_method');
            }
            if (!Schema::hasColumn('vendor_payouts', 'gross_reference_amount')) {
                $table->decimal('gross_reference_amount', 12, 2)->nullable()->after('amount');
            }
            if (!Schema::hasColumn('vendor_payouts', 'commission_deducted')) {
                $table->decimal('commission_deducted', 12, 2)->nullable()->after('gross_reference_amount');
            }
            if (!Schema::hasColumn('vendor_payouts', 'processed_by_admin_id')) {
                $table->foreignId('processed_by_admin_id')->nullable()->after('notes')->constrained('users')->nullOnDelete();
            }
            if (!Schema::hasColumn('vendor_payouts', 'transaction_ref')) {
                $table->string('transaction_ref', 100)->nullable()->after('reference_code');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_summary_logs');

        Schema::table('repair_requests', function (Blueprint $table) {
            $cols = array_filter(
                ['ai_triage_json', 'voucher_code', 'discount_amount', 'scheduled_pickup_at'],
                fn ($c) => Schema::hasColumn('repair_requests', $c)
            );
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('vendor_profiles', function (Blueprint $table) {
            $cols = array_filter([
                'alert_phone',
                'notify_sms_enabled',
                'notify_email_enabled',
                'notify_in_app_enabled',
                'notify_new_requests',
                'notify_bid_updates',
                'notify_payout_updates',
                'payout_method',
                'payout_account_name',
                'payout_account_number',
                'payout_bank_name',
                'payout_branch_name',
            ], fn ($c) => Schema::hasColumn('vendor_profiles', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('delivery_agents', function (Blueprint $table) {
            $cols = array_filter(
                ['payout_method', 'payout_account_number'],
                fn ($c) => Schema::hasColumn('delivery_agents', $c)
            );
            if ($cols) {
                $table->dropColumn($cols);
            }
        });

        Schema::table('vendor_payouts', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_payouts', 'processed_by_admin_id')) {
                $table->dropForeign(['processed_by_admin_id']);
            }
            $cols = array_filter([
                'payout_method',
                'payout_account',
                'gross_reference_amount',
                'commission_deducted',
                'processed_by_admin_id',
                'transaction_ref',
            ], fn ($c) => Schema::hasColumn('vendor_payouts', $c));
            if ($cols) {
                $table->dropColumn($cols);
            }
        });
    }
};

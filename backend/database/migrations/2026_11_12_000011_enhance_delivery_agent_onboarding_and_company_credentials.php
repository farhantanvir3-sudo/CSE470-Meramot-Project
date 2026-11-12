<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            if (!Schema::hasColumn('delivery_agents', 'applicant_name')) {
                $table->string('applicant_name')->nullable()->after('user_id');
            }
            if (!Schema::hasColumn('delivery_agents', 'applicant_phone')) {
                $table->string('applicant_phone', 30)->nullable()->after('applicant_name');
            }
            if (!Schema::hasColumn('delivery_agents', 'personal_email')) {
                $table->string('personal_email')->nullable()->unique()->after('applicant_phone');
            }
            if (!Schema::hasColumn('delivery_agents', 'company_email')) {
                $table->string('company_email')->nullable()->unique()->after('personal_email');
            }
            if (!Schema::hasColumn('delivery_agents', 'company_password_vault')) {
                $table->text('company_password_vault')->nullable()->after('company_email');
            }
            if (!Schema::hasColumn('delivery_agents', 'license_document_url')) {
                $table->text('license_document_url')->nullable()->after('nid_document_url');
            }
            if (!Schema::hasColumn('delivery_agents', 'status_otp_code')) {
                $table->string('status_otp_code', 10)->nullable()->after('rejection_reason');
            }
            if (!Schema::hasColumn('delivery_agents', 'status_otp_expires_at')) {
                $table->timestamp('status_otp_expires_at')->nullable()->after('status_otp_code');
            }
            if (!Schema::hasColumn('delivery_agents', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('status_otp_expires_at');
            }
        });

        // Support inline base64 data-URI document previews and detailed rejection notes on PostgreSQL
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE delivery_agents ALTER COLUMN profile_picture_url TYPE TEXT');
            DB::statement('ALTER TABLE delivery_agents ALTER COLUMN nid_document_url TYPE TEXT');
            DB::statement('ALTER TABLE delivery_agents ALTER COLUMN education_document_url TYPE TEXT');
            DB::statement('ALTER TABLE delivery_agents ALTER COLUMN cv_document_url TYPE TEXT');
            if (Schema::hasColumn('delivery_agents', 'rejection_reason')) {
                DB::statement('ALTER TABLE delivery_agents ALTER COLUMN rejection_reason TYPE TEXT');
            }
        }
    }

    public function down(): void
    {
        Schema::table('delivery_agents', function (Blueprint $table) {
            $cols = array_filter([
                'applicant_name',
                'applicant_phone',
                'personal_email',
                'company_email',
                'company_password_vault',
                'license_document_url',
                'status_otp_code',
                'status_otp_expires_at',
                'approved_at',
            ], fn ($col) => Schema::hasColumn('delivery_agents', $col));

            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

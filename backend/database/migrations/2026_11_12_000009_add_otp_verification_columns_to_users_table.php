<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'email_otp_code')) {
                $table->string('email_otp_code', 10)->nullable()->after('is_phone_verified');
            }
            if (!Schema::hasColumn('users', 'email_otp_expires_at')) {
                $table->timestamp('email_otp_expires_at')->nullable()->after('email_otp_code');
            }
            if (!Schema::hasColumn('users', 'phone_otp_code')) {
                $table->string('phone_otp_code', 10)->nullable()->after('email_otp_expires_at');
            }
            if (!Schema::hasColumn('users', 'phone_otp_expires_at')) {
                $table->timestamp('phone_otp_expires_at')->nullable()->after('phone_otp_code');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(
                ['email_otp_code', 'email_otp_expires_at', 'phone_otp_code', 'phone_otp_expires_at'],
                fn ($col) => Schema::hasColumn('users', $col)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

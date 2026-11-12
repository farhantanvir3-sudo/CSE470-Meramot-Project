<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'google_id')) {
                $table->string('google_id')->nullable()->unique()->after('email');
            }
            if (!Schema::hasColumn('users', 'auth_provider')) {
                $table->string('auth_provider', 32)->default('local')->after('google_id');
            }
        });

        Schema::table('vendor_profiles', function (Blueprint $table) {
            if (!Schema::hasColumn('vendor_profiles', 'rejection_visible_until')) {
                $table->timestamp('rejection_visible_until')->nullable()->after('rejected_at');
            }
        });

        // Allow Google-registered users to have null password and support long avatar/logo URLs or data URIs
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN password DROP NOT NULL');
            DB::statement('ALTER TABLE users ALTER COLUMN avatar_url TYPE TEXT');
            DB::statement('ALTER TABLE vendor_profiles ALTER COLUMN logo_url TYPE TEXT');
            DB::statement('ALTER TABLE repair_shops ALTER COLUMN logo_url TYPE TEXT');
        }
    }

    public function down(): void
    {
        Schema::table('vendor_profiles', function (Blueprint $table) {
            if (Schema::hasColumn('vendor_profiles', 'rejection_visible_until')) {
                $table->dropColumn('rejection_visible_until');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $cols = array_filter(
                ['google_id', 'auth_provider'],
                fn ($col) => Schema::hasColumn('users', $col)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

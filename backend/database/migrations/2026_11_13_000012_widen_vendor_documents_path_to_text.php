<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql' && Schema::hasColumn('vendor_profiles', 'documents_path')) {
            DB::statement('ALTER TABLE vendor_profiles ALTER COLUMN documents_path TYPE TEXT');
        }
    }

    public function down(): void
    {
        // No-op: retain TEXT column capacity for base64 document data URIs
    }
};

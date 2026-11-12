<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $textColumns = [
            ['repair_shops', 'banner_url'],
            ['repair_shops', 'logo_url'],
            ['offer_banners', 'image_url'],
            ['users', 'avatar_url'],
            ['vendor_profiles', 'documents_path'],
        ];

        foreach ($textColumns as [$table, $column]) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, $column)) {
                DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE TEXT");
            }
        }

        $tablesWithSerialId = DB::select("
            SELECT table_name
            FROM information_schema.columns
            WHERE table_schema = 'public'
              AND column_name = 'id'
              AND column_default LIKE 'nextval%'
        ");

        foreach ($tablesWithSerialId as $row) {
            $table = $row->table_name;
            DB::statement("
                SELECT setval(
                    pg_get_serial_sequence('\"{$table}\"', 'id'),
                    COALESCE((SELECT MAX(id) FROM \"{$table}\"), 0) + 1,
                    false
                )
            ");
        }
    }

    public function down(): void
    {
        // Retain widened TEXT media columns and synchronized PostgreSQL sequences
    }
};

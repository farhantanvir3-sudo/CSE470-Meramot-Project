<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('financial_ledgers', 'hidden_at')) {
            Schema::table('financial_ledgers', function (Blueprint $table) {
                $table->timestamp('hidden_at')->nullable()->after('effective_at');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('financial_ledgers', 'hidden_at')) {
            Schema::table('financial_ledgers', function (Blueprint $table) {
                $table->dropColumn('hidden_at');
            });
        }
    }
};

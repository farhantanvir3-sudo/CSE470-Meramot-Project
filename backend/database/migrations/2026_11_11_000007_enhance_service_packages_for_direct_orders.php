<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('service_packages', function (Blueprint $table) {
            if (!Schema::hasColumn('service_packages', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (!Schema::hasColumn('service_packages', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('is_popular');
            }
            if (!Schema::hasColumn('service_packages', 'order_count')) {
                $table->integer('order_count')->default(0)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        Schema::table('service_packages', function (Blueprint $table) {
            $cols = array_filter(
                ['description', 'is_active', 'order_count'],
                fn ($col) => Schema::hasColumn('service_packages', $col)
            );
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};

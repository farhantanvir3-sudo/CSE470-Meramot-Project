<?php

use Database\Seeders\ServicePackageSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('repair_shop_service_package')) {
            Schema::create('repair_shop_service_package', function (Blueprint $table) {
                $table->id();
                $table->foreignId('repair_shop_id')->constrained('repair_shops')->cascadeOnDelete();
                $table->foreignId('service_package_id')->constrained('service_packages')->cascadeOnDelete();
                $table->timestamps();

                $table->unique(['repair_shop_id', 'service_package_id'], 'repair_shop_service_package_unique');
                $table->index('service_package_id');
            });
        }

        if (Schema::hasTable('vendor_profiles')) {
            Schema::table('vendor_profiles', function (Blueprint $table) {
                if (!Schema::hasColumn('vendor_profiles', 'provides_fixed_packages')) {
                    $table->boolean('provides_fixed_packages')->nullable()->after('spare_parts');
                }
                if (!Schema::hasColumn('vendor_profiles', 'offered_package_ids')) {
                    $table->json('offered_package_ids')->nullable()->after('provides_fixed_packages');
                }
            });
        }

        if (
            !app()->runningUnitTests()
            && Schema::hasTable('users') && DB::table('users')->count() > 0
            && Schema::hasTable('repair_shops') && DB::table('repair_shops')->count() > 0
            && Schema::hasTable('service_packages')
        ) {
            (new ServicePackageSeeder())->run();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('repair_shop_service_package');

        if (Schema::hasTable('vendor_profiles')) {
            Schema::table('vendor_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('vendor_profiles', 'offered_package_ids')) {
                    $table->dropColumn('offered_package_ids');
                }
                if (Schema::hasColumn('vendor_profiles', 'provides_fixed_packages')) {
                    $table->dropColumn('provides_fixed_packages');
                }
            });
        }
    }
};

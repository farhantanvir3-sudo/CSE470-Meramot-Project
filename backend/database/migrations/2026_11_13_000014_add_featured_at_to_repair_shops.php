<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('repair_shops', 'featured_at')) {
            Schema::table('repair_shops', function (Blueprint $table) {
                $table->timestamp('featured_at')->nullable()->after('is_featured');
            });
        }

        // Backfill featured_at for already-featured shops so LIFO stack ordering works cleanly
        DB::table('repair_shops')
            ->where('is_featured', true)
            ->whereNull('featured_at')
            ->update(['featured_at' => DB::raw('updated_at')]);

        // Ensure any already-featured shop belonging to an approved vendor is active & public
        $approvedVendorIds = DB::table('vendor_profiles')
            ->where('verification_status', 'approved')
            ->pluck('id');

        if ($approvedVendorIds->isNotEmpty()) {
            DB::table('repair_shops')
                ->where('is_featured', true)
                ->whereIn('vendor_profile_id', $approvedVendorIds)
                ->update([
                    'is_active' => true,
                    'is_public' => true,
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('repair_shops', 'featured_at')) {
            Schema::table('repair_shops', function (Blueprint $table) {
                $table->dropColumn('featured_at');
            });
        }
    }
};

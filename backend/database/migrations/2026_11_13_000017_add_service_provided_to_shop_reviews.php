<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('shop_reviews')) {
            return;
        }

        if (!Schema::hasColumn('shop_reviews', 'service_provided')) {
            Schema::table('shop_reviews', function (Blueprint $table) {
                $table->string('service_provided', 180)->nullable()->after('rating');
            });
        }

        if (Schema::hasTable('repair_requests')) {
            $reviews = DB::table('shop_reviews')
                ->whereNull('service_provided')
                ->whereNotNull('repair_request_id')
                ->get(['id', 'repair_request_id']);

            foreach ($reviews as $rev) {
                $req = DB::table('repair_requests')
                    ->where('id', $rev->repair_request_id)
                    ->first();

                if ($req) {
                    $category = trim((string) ($req->issue_category ?: $req->device_type ?: 'Repair Service'));
                    $title = trim((string) ($req->title ?: 'Bench Repair'));
                    $label = Str::limit("{$category} — {$title}", 175, '');

                    DB::table('shop_reviews')
                        ->where('id', $rev->id)
                        ->update(['service_provided' => $label]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('shop_reviews') && Schema::hasColumn('shop_reviews', 'service_provided')) {
            Schema::table('shop_reviews', function (Blueprint $table) {
                $table->dropColumn('service_provided');
            });
        }
    }
};

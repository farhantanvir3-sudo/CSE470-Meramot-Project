<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bids')) {
            Schema::table('bids', function (Blueprint $table) {
                if (!Schema::hasColumn('bids', 'parts_items')) {
                    $table->json('parts_items')->nullable()->after('parts_cost');
                }
                if (!Schema::hasColumn('bids', 'has_warranty')) {
                    $table->boolean('has_warranty')->default(true)->after('estimated_days');
                }
            });

            $bids = DB::table('bids')
                ->whereNull('parts_items')
                ->get(['id', 'parts_cost', 'warranty_days', 'line_items']);

            foreach ($bids as $bid) {
                $partsItems = [];
                $decoded = is_string($bid->line_items)
                    ? json_decode($bid->line_items, true)
                    : (is_array($bid->line_items) ? $bid->line_items : []);

                if (is_array($decoded)) {
                    foreach ($decoded as $line) {
                        if (!is_array($line)) {
                            continue;
                        }
                        $type = strtolower((string) ($line['type'] ?? ''));
                        $label = trim((string) ($line['item'] ?? $line['name'] ?? $line['title'] ?? ''));
                        $cost = round((float) ($line['cost'] ?? $line['amount'] ?? 0), 2);
                        if ($type === 'labor' || stripos($label, 'labor') !== false) {
                            continue;
                        }
                        if ($label !== '' || $cost > 0) {
                            $partsItems[] = [
                                'name' => $label !== '' ? $label : 'Component / Parts Estimate',
                                'cost' => max(0.0, $cost),
                            ];
                        }
                    }
                }

                $partsCost = round((float) ($bid->parts_cost ?? 0), 2);
                if (empty($partsItems) && $partsCost > 0) {
                    $partsItems = [
                        ['name' => 'Replacement Hardware Components', 'cost' => $partsCost],
                    ];
                }

                DB::table('bids')
                    ->where('id', $bid->id)
                    ->update([
                        'parts_items' => json_encode($partsItems),
                        'has_warranty' => ((int) ($bid->warranty_days ?? 0)) > 0,
                    ]);
            }
        }

        if (Schema::hasTable('repair_jobs')) {
            Schema::table('repair_jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('repair_jobs', 'parts_cost')) {
                    $table->decimal('parts_cost', 10, 2)->nullable()->after('diagnosis_notes');
                }
                if (!Schema::hasColumn('repair_jobs', 'labor_cost')) {
                    $table->decimal('labor_cost', 10, 2)->nullable()->after('parts_cost');
                }
                if (!Schema::hasColumn('repair_jobs', 'parts_items')) {
                    $table->json('parts_items')->nullable()->after('final_quote_items');
                }
                if (!Schema::hasColumn('repair_jobs', 'has_warranty')) {
                    $table->boolean('has_warranty')->default(true)->after('parts_items');
                }
                if (!Schema::hasColumn('repair_jobs', 'warranty_days')) {
                    $table->integer('warranty_days')->default(90)->after('has_warranty');
                }
            });

            $jobs = DB::table('repair_jobs')
                ->whereNull('parts_items')
                ->whereNotNull('accepted_bid_id')
                ->get(['id', 'accepted_bid_id']);

            foreach ($jobs as $job) {
                $bid = DB::table('bids')->where('id', $job->accepted_bid_id)->first();
                if ($bid) {
                    DB::table('repair_jobs')
                        ->where('id', $job->id)
                        ->update([
                            'parts_cost' => $bid->parts_cost,
                            'labor_cost' => $bid->labor_cost,
                            'parts_items' => $bid->parts_items,
                            'has_warranty' => (bool) ($bid->has_warranty ?? (((int) $bid->warranty_days) > 0)),
                            'warranty_days' => (int) ($bid->warranty_days ?? 90),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('repair_jobs')) {
            Schema::table('repair_jobs', function (Blueprint $table) {
                $cols = array_values(array_filter(
                    ['parts_cost', 'labor_cost', 'parts_items', 'has_warranty', 'warranty_days'],
                    fn ($col) => Schema::hasColumn('repair_jobs', $col)
                ));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }

        if (Schema::hasTable('bids')) {
            Schema::table('bids', function (Blueprint $table) {
                $cols = array_values(array_filter(
                    ['parts_items', 'has_warranty'],
                    fn ($col) => Schema::hasColumn('bids', $col)
                ));
                if (!empty($cols)) {
                    $table->dropColumn($cols);
                }
            });
        }
    }
};

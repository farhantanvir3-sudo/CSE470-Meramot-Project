<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('user_recent_searches')) {
            Schema::create('user_recent_searches', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('query', 120);
                $table->string('normalized_query', 120);
                $table->string('target_section', 32)->nullable();
                $table->unsignedInteger('search_count')->default(1);
                $table->timestamp('last_searched_at')->useCurrent();
                $table->timestamps();

                $table->unique(['user_id', 'normalized_query'], 'user_recent_searches_user_norm_unique');
                $table->index(['user_id', 'last_searched_at'], 'user_recent_searches_user_mru_idx');
            });
        }

        $demoCustomerId = DB::table('users')
            ->where('email', 'customer@meramot.demo')
            ->value('id');

        if ($demoCustomerId) {
            $now = now();
            $defaults = [
                ['query' => 'ThinkPad keyboard repair', 'target_section' => 'spare-parts', 'offset_minutes' => 2],
                ['query' => 'iPhone 13 Pro OLED display', 'target_section' => 'spare-parts', 'offset_minutes' => 9],
                ['query' => 'MacBook Air M2 battery replacement', 'target_section' => 'spare-parts', 'offset_minutes' => 16],
                ['query' => 'Samsung S22 Ultra display', 'target_section' => 'spare-parts', 'offset_minutes' => 23],
                ['query' => 'Thermal repasting', 'target_section' => 'packages', 'offset_minutes' => 30],
            ];

            foreach ($defaults as $item) {
                $normalized = mb_strtolower(preg_replace('/\s+/u', ' ', trim($item['query'])), 'UTF-8');
                $timestamp = $now->copy()->subMinutes($item['offset_minutes']);

                DB::table('user_recent_searches')->updateOrInsert(
                    [
                        'user_id' => $demoCustomerId,
                        'normalized_query' => $normalized,
                    ],
                    [
                        'query' => $item['query'],
                        'target_section' => $item['target_section'],
                        'search_count' => 1,
                        'last_searched_at' => $timestamp,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('user_recent_searches');
    }
};

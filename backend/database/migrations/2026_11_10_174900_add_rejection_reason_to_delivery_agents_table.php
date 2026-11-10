<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * FEATURE R1: Delivery Agent Rejection Reason Column Migration
 * ============================================================================
 * Author: Md. Nafiul Islam [Student ID: 22299035] (Commit fdf5e6f)
 * Purpose: Adds nullable `rejection_reason` column to `delivery_agents`
 *          so admins can record why a rider onboarding application was rejected.
 * ============================================================================
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('delivery_agents', 'rejection_reason')) {
            Schema::table('delivery_agents', function (Blueprint $table) {
                $table->text('rejection_reason')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('delivery_agents', 'rejection_reason')) {
            Schema::table('delivery_agents', function (Blueprint $table) {
                $table->dropColumn('rejection_reason');
            });
        }
    }
};

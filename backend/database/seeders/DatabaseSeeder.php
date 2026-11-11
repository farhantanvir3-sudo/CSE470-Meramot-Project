<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with baseline & Sprint 1 demo data.
     * Order guarantees referential & e-commerce causality:
     *   1. Users & Riders -> 2. Verified Shops & Services -> 3. Fixed Packages ->
     *   4. Completed Historical Orders, Deliveries & Ledger ->
     *   5. Verified Customer Reviews + Exact Rating/Count Sync ->
     *   6. Live Sprint 1 Pending Audits & Active Demo Workflows.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ShopSeeder::class,
            ServicePackageSeeder::class,
            FinancialLedgerSeeder::class,
            ReviewSeeder::class,
            SprintOneDemoSeeder::class,
        ]);
    }
}

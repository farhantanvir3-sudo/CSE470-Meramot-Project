<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with baseline & Sprint 1 demo data.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ShopSeeder::class,
            ServicePackageSeeder::class,
            ReviewSeeder::class,
            FinancialLedgerSeeder::class,
            SprintOneDemoSeeder::class,
        ]);
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database with previous Meramot baseline data.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            ShopSeeder::class,
        ]);
    }
}

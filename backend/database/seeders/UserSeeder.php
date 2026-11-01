<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * Meramot User Seeder — Ports original user accounts from previous Meramot
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $passwordHash = Hash::make('password123');
        $adminPasswordHash = Hash::make(env('ADMIN_PASSWORD', 'Admin@Meramot2026'));

        // 1. System Admin
        User::updateOrCreate(
            ['email' => 'mustahid000@gmail.com'],
            [
                'name' => 'Mustahid (System Admin)',
                'password' => $adminPasswordHash,
                'phone' => '+8801700000001',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // 2. Delivery Admin
        User::updateOrCreate(
            ['email' => 'delivery.admin.demo@meeramoot.test'],
            [
                'name' => 'Delivery Operations Admin',
                'password' => Hash::make('DeliveryAdmin@123'),
                'phone' => '+8801700000002',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // 3. Customer Demo
        User::updateOrCreate(
            ['email' => 'customer@meramot.demo'],
            [
                'name' => 'Customer Demo User',
                'password' => $passwordHash,
                'phone' => '+8801700000000',
                'role' => 'customer',
                'is_active' => true,
            ]
        );

        // 4. Test Demo Vendors
        User::updateOrCreate(
            ['email' => 'sakib.tech@gmail.com'],
            [
                'name' => 'Sakib Al Hasan (Vendor)',
                'password' => $passwordHash,
                'phone' => '+8801712345001',
                'role' => 'vendor',
                'is_active' => true,
            ]
        );

        User::updateOrCreate(
            ['email' => 'dhaka.devices@meramot.demo'],
            [
                'name' => 'Kazi Tanvir (Dhaka Device Doctors)',
                'password' => $passwordHash,
                'phone' => '+8801712345002',
                'role' => 'vendor',
                'is_active' => true,
            ]
        );

        // 5. Test Delivery Agent
        User::updateOrCreate(
            ['email' => 'rider.demo@meramot.test'],
            [
                'name' => 'Rahim Uddin (Express Rider)',
                'password' => $passwordHash,
                'phone' => '+8801799998888',
                'role' => 'delivery_agent',
                'is_active' => true,
            ]
        );
    }
}

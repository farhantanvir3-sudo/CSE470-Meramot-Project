<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DeliveryAgent;
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

        // 5. Test Delivery Agents & Pending Onboarding Applications (Feature R1 — Md. Nafiul Islam [22299035])
        $approvedRiderUser = User::updateOrCreate(
            ['email' => 'rider.demo@meramot.test'],
            [
                'name' => 'Rahim Uddin (Express Rider)',
                'password' => $passwordHash,
                'phone' => '+8801799998888',
                'role' => 'delivery_agent',
                'is_active' => true,
            ]
        );

        DeliveryAgent::updateOrCreate(
            ['user_id' => $approvedRiderUser->id],
            [
                'nid_number' => '1996269481011',
                'vehicle_type' => 'motorcycle',
                'license_number' => 'DK-0492811-A',
                'registration_status' => 'APPROVED',
                'status' => 'AVAILABLE',
                'is_active' => true,
                'rejection_reason' => null,
            ]
        );

        $pendingRider1 = User::updateOrCreate(
            ['email' => 'kamal.rider@meramot.test'],
            [
                'name' => 'Kamal Hossain (Rider Applicant)',
                'password' => $passwordHash,
                'phone' => '+8801711223344',
                'role' => 'customer',
                'is_active' => true,
            ]
        );

        DeliveryAgent::updateOrCreate(
            ['user_id' => $pendingRider1->id],
            [
                'nid_number' => '1998269482045',
                'vehicle_type' => 'motorcycle',
                'license_number' => 'DK-0837421-M',
                'registration_status' => 'PENDING',
                'status' => 'OFFLINE',
                'is_active' => false,
                'rejection_reason' => null,
            ]
        );

        $pendingRider2 = User::updateOrCreate(
            ['email' => 'tarek.scooter@meramot.test'],
            [
                'name' => 'Tarek Mahmud (Rider Applicant)',
                'password' => $passwordHash,
                'phone' => '+8801811556677',
                'role' => 'customer',
                'is_active' => true,
            ]
        );

        DeliveryAgent::updateOrCreate(
            ['user_id' => $pendingRider2->id],
            [
                'nid_number' => '2000269483099',
                'vehicle_type' => 'scooter',
                'license_number' => 'DK-0912345-S',
                'registration_status' => 'PENDING',
                'status' => 'OFFLINE',
                'is_active' => false,
                'rejection_reason' => null,
            ]
        );

        $rejectedRider = User::updateOrCreate(
            ['email' => 'sohag.van@meramot.test'],
            [
                'name' => 'Sohag Mia (Rider Applicant)',
                'password' => $passwordHash,
                'phone' => '+8801911889900',
                'role' => 'customer',
                'is_active' => true,
            ]
        );

        DeliveryAgent::updateOrCreate(
            ['user_id' => $rejectedRider->id],
            [
                'nid_number' => '1995269489900',
                'vehicle_type' => 'van',
                'license_number' => 'DK-0112233-V',
                'registration_status' => 'REJECTED',
                'status' => 'OFFLINE',
                'is_active' => false,
                'rejection_reason' => 'BRTA driving license number expired; please renew and re-apply.',
            ]
        );
    }
}

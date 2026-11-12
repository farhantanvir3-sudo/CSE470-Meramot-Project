<?php

namespace Database\Seeders;

use App\Models\DeliveryAgent;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Meramot User Seeder — Baseline System, Customer, Vendor & Delivery Accounts
 */
class UserSeeder extends Seeder
{
    public function run(): void
    {
        $passwordHash = Hash::make('password123');
        $adminPasswordHash = Hash::make(env('ADMIN_PASSWORD', 'Admin@Meramot2026'));

        // Remove any stale placeholder admin accounts if re-seeding without migrate:fresh
        User::where('role', 'admin')
            ->whereNotIn('email', ['admin@meramot.demo', 'delivery.admin@meramot.demo'])
            ->delete();

        // 1. System Admin (Meramot)
        User::updateOrCreate(
            ['email' => 'admin@meramot.demo'],
            [
                'name' => 'Meramot',
                'username' => 'meramot_admin',
                'password' => $adminPasswordHash,
                'phone' => '+8801700000001',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // 2. Delivery Operations Admin
        User::updateOrCreate(
            ['email' => 'delivery.admin@meramot.demo'],
            [
                'name' => 'Meramot Logistics',
                'username' => 'meramot_logistics',
                'password' => Hash::make('DeliveryAdmin@123'),
                'phone' => '+8801700000002',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        // 3. Primary Customer Demo Account
        User::updateOrCreate(
            ['email' => 'customer@meramot.demo'],
            [
                'name' => 'Customer Demo User',
                'username' => 'customer_demo',
                'password' => $passwordHash,
                'phone' => '+8801700000000',
                'role' => 'customer',
                'is_active' => true,
            ]
        );

        // 3B. Distinct Bangladesh Customer Accounts (For Realistic Historical Orders & Verified Reviews)
        $verifiedCustomers = [
            ['name' => 'Nusrat Jahan', 'email' => 'nusrat.jahan@meramot.demo', 'phone' => '+8801714100201'],
            ['name' => 'Tanvir Hasan', 'email' => 'tanvir.hasan@meramot.demo', 'phone' => '+8801714100202'],
            ['name' => 'Mahmudul Karim', 'email' => 'mahmudul.karim@meramot.demo', 'phone' => '+8801714100203'],
            ['name' => 'Farzana Yasmin', 'email' => 'farzana.yasmin@meramot.demo', 'phone' => '+8801714100204'],
            ['name' => 'Rafiul Alam', 'email' => 'rafiul.alam@meramot.demo', 'phone' => '+8801714100205'],
            ['name' => 'Sadia Afrin', 'email' => 'sadia.afrin@meramot.demo', 'phone' => '+8801816200306'],
            ['name' => 'Mehedi Hasan', 'email' => 'mehedi.hasan@meramot.demo', 'phone' => '+8801816200307'],
            ['name' => 'Tahmid Chowdhury', 'email' => 'tahmid.chowdhury@meramot.demo', 'phone' => '+8801816200308'],
            ['name' => 'Tasnim Akter', 'email' => 'tasnim.akter@meramot.demo', 'phone' => '+8801912300409'],
            ['name' => 'Imran Kabir', 'email' => 'imran.kabir@meramot.demo', 'phone' => '+8801912300410'],
        ];

        foreach ($verifiedCustomers as $idx => $cust) {
            User::updateOrCreate(
                ['email' => $cust['email']],
                [
                    'name' => $cust['name'],
                    'username' => 'customer_bd_' . ($idx + 1),
                    'password' => $passwordHash,
                    'phone' => $cust['phone'],
                    'role' => 'customer',
                    'is_active' => true,
                ]
            );
        }

        // 4. Test Demo Vendors
        User::updateOrCreate(
            ['email' => 'sakib.tech@gmail.com'],
            [
                'name' => 'Sakib Al Hasan (Vendor)',
                'username' => 'protech_vendor',
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
                'username' => 'dhaka_device_doctors',
                'password' => $passwordHash,
                'phone' => '+8801712345002',
                'role' => 'vendor',
                'is_active' => true,
            ]
        );

        // 5. Coverage Zones & Delivery Agents (Feature R1 — Md. Nafiul Islam [22299035])
        $zones = [
            ['name' => 'Banani Metro Hub', 'city' => 'Dhaka', 'area' => 'Banani', 'postal_code' => '1213'],
            ['name' => 'Gulshan Diplomatic Zone', 'city' => 'Dhaka', 'area' => 'Gulshan', 'postal_code' => '1212'],
            ['name' => 'Dhanmondi Residential Zone', 'city' => 'Dhaka', 'area' => 'Dhanmondi', 'postal_code' => '1209'],
            ['name' => 'Uttara North Corridor', 'city' => 'Dhaka', 'area' => 'Uttara', 'postal_code' => '1230'],
            ['name' => 'Mirpur Central Hub', 'city' => 'Dhaka', 'area' => 'Mirpur', 'postal_code' => '1216'],
            ['name' => 'Mohakhali & Tejgaon Belt', 'city' => 'Dhaka', 'area' => 'Mohakhali', 'postal_code' => '1206'],
            ['name' => 'Bashundhara R/A Zone', 'city' => 'Dhaka', 'area' => 'Bashundhara', 'postal_code' => '1229'],
            ['name' => 'Agrabad Commercial Zone', 'city' => 'Chattogram', 'area' => 'Agrabad', 'postal_code' => '4100'],
        ];

        $zoneIdsByArea = [];
        foreach ($zones as $z) {
            DB::table('coverage_zones')->updateOrInsert(
                ['city' => $z['city'], 'area' => $z['area']],
                [
                    'name' => $z['name'],
                    'postal_code' => $z['postal_code'],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]
            );
            $zoneIdsByArea[$z['city'] . ':' . $z['area']] = DB::table('coverage_zones')
                ->where('city', $z['city'])
                ->where('area', $z['area'])
                ->value('id');
        }

        // Migrate any legacy demo rider email in-place when re-seeding an existing database
        $legacyDemoRider = User::where('email', 'rider.demo@meramot.test')->first();
        if ($legacyDemoRider && !User::where('email', 'rahim.uddin@meramotbd.com')->exists()) {
            $legacyDemoRider->update(['email' => 'rahim.uddin@meramotbd.com']);
        }
        $legacyCtgRider = User::where('email', 'ctg.rider@meramot.test')->first();
        if ($legacyCtgRider && !User::where('email', 'tariqul.ctg@meramotbd.com')->exists()) {
            $legacyCtgRider->update(['email' => 'tariqul.ctg@meramotbd.com']);
        }

        $demoRiderPlainPassword = 'MRM#DemoRider2026';
        $ctgRiderPlainPassword = 'MRM#CtgRider2026';

        $approvedRiderUser = User::updateOrCreate(
            ['email' => 'rahim.uddin@meramotbd.com'],
            [
                'name' => 'Rahim Uddin (Express Rider)',
                'username' => 'rahim_rider',
                'password' => Hash::make($demoRiderPlainPassword),
                'phone' => '+8801799998888',
                'role' => 'delivery_agent',
                'status' => 'active',
                'is_active' => true,
                'is_email_verified' => true,
            ]
        );

        $approvedDhakaAgent = DeliveryAgent::updateOrCreate(
            ['user_id' => $approvedRiderUser->id],
            [
                'applicant_name' => 'Rahim Uddin (Express Rider)',
                'applicant_phone' => '+8801799998888',
                'personal_email' => 'rahim.uddin@gmail.com',
                'company_email' => 'rahim.uddin@meramotbd.com',
                'company_password_vault' => Crypt::encryptString($demoRiderPlainPassword),
                'nid_number' => '1996269481011',
                'vehicle_type' => 'motorcycle',
                'license_number' => 'DK-0492811-A',
                'registration_status' => 'APPROVED',
                'status' => 'AVAILABLE',
                'is_active' => true,
                'rejection_reason' => null,
                'approved_at' => now()->subDays(35),
                'current_latitude' => 23.79250000,
                'current_longitude' => 90.40520000,
                'total_earnings' => 0.00,
            ]
        );

        foreach ($zoneIdsByArea as $cityArea => $zoneId) {
            if (str_starts_with($cityArea, 'Dhaka:') && $zoneId) {
                DB::table('rider_coverages')->updateOrInsert(
                    ['delivery_agent_id' => $approvedDhakaAgent->id, 'coverage_zone_id' => $zoneId],
                    ['created_at' => now(), 'updated_at' => now()]
                );
            }
        }

        $approvedCtgRiderUser = User::updateOrCreate(
            ['email' => 'tariqul.ctg@meramotbd.com'],
            [
                'name' => 'Tariqul Islam (Chattogram Rider)',
                'username' => 'tariqul_ctg_rider',
                'password' => Hash::make($ctgRiderPlainPassword),
                'phone' => '+8801819776655',
                'role' => 'delivery_agent',
                'status' => 'active',
                'is_active' => true,
                'is_email_verified' => true,
            ]
        );

        $approvedCtgAgent = DeliveryAgent::updateOrCreate(
            ['user_id' => $approvedCtgRiderUser->id],
            [
                'applicant_name' => 'Tariqul Islam (Chattogram Rider)',
                'applicant_phone' => '+8801819776655',
                'personal_email' => 'tariqul.ctg@gmail.com',
                'company_email' => 'tariqul.ctg@meramotbd.com',
                'company_password_vault' => Crypt::encryptString($ctgRiderPlainPassword),
                'nid_number' => '1997319485522',
                'vehicle_type' => 'motorcycle',
                'license_number' => 'CTG-0519204-M',
                'registration_status' => 'APPROVED',
                'status' => 'AVAILABLE',
                'is_active' => true,
                'rejection_reason' => null,
                'approved_at' => now()->subDays(20),
                'current_latitude' => 22.32900000,
                'current_longitude' => 91.81400000,
                'total_earnings' => 0.00,
            ]
        );

        if (!empty($zoneIdsByArea['Chattogram:Agrabad'])) {
            DB::table('rider_coverages')->updateOrInsert(
                ['delivery_agent_id' => $approvedCtgAgent->id, 'coverage_zone_id' => $zoneIdsByArea['Chattogram:Agrabad']],
                ['created_at' => now(), 'updated_at' => now()]
            );
        }

        $pendingRider1 = User::updateOrCreate(
            ['email' => 'kamal.rider@meramot.test'],
            [
                'name' => 'Kamal Hossain (Rider Applicant)',
                'username' => 'kamal_applicant',
                'password' => null,
                'phone' => '+8801711223344',
                'role' => 'delivery_agent',
                'status' => 'suspended',
                'is_active' => false,
            ]
        );

        DeliveryAgent::updateOrCreate(
            ['user_id' => $pendingRider1->id],
            [
                'applicant_name' => 'Kamal Hossain (Rider Applicant)',
                'applicant_phone' => '+8801711223344',
                'personal_email' => 'kamal.rider@gmail.com',
                'company_email' => null,
                'company_password_vault' => null,
                'nid_number' => '1998269482045',
                'vehicle_type' => 'motorcycle',
                'license_number' => 'DK-0837421-M',
                'registration_status' => 'PENDING',
                'status' => 'OFFLINE',
                'is_active' => false,
                'rejection_reason' => null,
                'total_earnings' => 0.00,
            ]
        );

        $pendingRider2 = User::updateOrCreate(
            ['email' => 'tarek.scooter@meramot.test'],
            [
                'name' => 'Tarek Mahmud (Rider Applicant)',
                'username' => 'tarek_applicant',
                'password' => null,
                'phone' => '+8801811556677',
                'role' => 'delivery_agent',
                'status' => 'suspended',
                'is_active' => false,
            ]
        );

        DeliveryAgent::updateOrCreate(
            ['user_id' => $pendingRider2->id],
            [
                'applicant_name' => 'Tarek Mahmud (Rider Applicant)',
                'applicant_phone' => '+8801811556677',
                'personal_email' => 'tarek.scooter@gmail.com',
                'company_email' => null,
                'company_password_vault' => null,
                'nid_number' => '2000269483099',
                'vehicle_type' => 'scooter',
                'license_number' => 'DK-0912345-S',
                'registration_status' => 'PENDING',
                'status' => 'OFFLINE',
                'is_active' => false,
                'rejection_reason' => null,
                'total_earnings' => 0.00,
            ]
        );

        $rejectedRider = User::updateOrCreate(
            ['email' => 'sohag.van@meramot.test'],
            [
                'name' => 'Sohag Mia (Rider Applicant)',
                'username' => 'sohag_applicant',
                'password' => null,
                'phone' => '+8801911889900',
                'role' => 'delivery_agent',
                'status' => 'suspended',
                'is_active' => false,
            ]
        );

        DeliveryAgent::updateOrCreate(
            ['user_id' => $rejectedRider->id],
            [
                'applicant_name' => 'Sohag Mia (Rider Applicant)',
                'applicant_phone' => '+8801911889900',
                'personal_email' => 'sohag.van@gmail.com',
                'company_email' => null,
                'company_password_vault' => null,
                'nid_number' => '1995269489900',
                'vehicle_type' => 'van',
                'license_number' => 'DK-0112233-V',
                'registration_status' => 'REJECTED',
                'status' => 'OFFLINE',
                'is_active' => false,
                'rejection_reason' => 'BRTA driving license number expired; please renew and re-apply.',
                'total_earnings' => 0.00,
            ]
        );
    }
}

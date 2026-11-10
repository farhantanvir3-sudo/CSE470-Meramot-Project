<?php

namespace Tests\Feature;

use App\Models\RepairShop;
use App\Models\User;
use App\Models\VendorProfile;
use Database\Seeders\ShopSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ============================================================================
 * FEATURE R6, R11 & R15: Dual Shop Addresses, Multi-Branch Vendors &
 * Nationwide Bangladesh OpenStreetMap Seeding Coherence Tests
 * ============================================================================
 * Author: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * ============================================================================
 */
class VendorBranchAndDualAddressTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function vendor_onboarding_supports_distinct_gps_and_public_addresses_without_exposing_raw_gps_on_profile()
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        $response = $this->actingAs($vendor)->postJson('/api/vendors/onboarding', [
            'shop_name' => 'Circuit Pulse Lab',
            'business_license_no' => 'TRAD/DNCC/778899',
            'nid_number' => '1995123456789',
            'gps_address' => 'Private Map Pin Near Road 19, Banani, Dhaka',
            'same_as_gps_location' => false,
            'shop_address' => 'Level 5, Suite 504, Harbor Plaza, Banani, Dhaka',
            'shop_latitude' => 23.7939,
            'shop_longitude' => 90.4042,
            'city' => 'Dhaka',
            'area' => 'Banani',
            'categories' => ['Laptop', 'Phone'],
        ]);

        $response->assertStatus(201);

        $shop = RepairShop::where('name', 'Circuit Pulse Lab')->firstOrFail();
        $this->assertFalse($shop->same_as_gps_location);
        $this->assertEquals('Private Map Pin Near Road 19, Banani, Dhaka', $shop->gps_address);
        $this->assertEquals('Level 5, Suite 504, Harbor Plaza, Banani, Dhaka', $shop->address);

        // Public profile page shows ONLY Vendor-Provided Public Location, never private GPS text
        $profileResponse = $this->get('/shops/' . $shop->slug);
        $profileResponse->assertStatus(200);
        $profileResponse->assertSee('Level 5, Suite 504, Harbor Plaza, Banani, Dhaka');
        $profileResponse->assertDontSee('Private Map Pin Near Road 19, Banani, Dhaka');
    }

    /** @test */
    public function vendor_can_choose_same_as_gps_location_during_onboarding_and_dashboard_updates()
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        $this->actingAs($vendor)->postJson('/api/vendors/onboarding', [
            'shop_name' => 'Sylhet Tech Corner',
            'business_license_no' => 'TRAD/SCC/445566',
            'nid_number' => '1994987654321',
            'gps_address' => 'Zindabazar Main Point, Sylhet',
            'same_as_gps_location' => true,
            'shop_latitude' => 24.8949,
            'shop_longitude' => 91.8687,
            'city' => 'Sylhet',
            'area' => 'Zindabazar',
            'categories' => ['Phone'],
        ])->assertStatus(201);

        $shop = RepairShop::where('name', 'Sylhet Tech Corner')->firstOrFail();
        $this->assertTrue($shop->same_as_gps_location);
        $this->assertEquals('Zindabazar Main Point, Sylhet', $shop->address);
        $this->assertEquals($shop->gps_address, $shop->address);

        // Later from Vendor Dashboard, vendor unchecks same_as_gps_location and sets a detailed mall floor address
        $this->actingAs($vendor)->patchJson("/api/vendors/branches/{$shop->id}", [
            'latitude' => 24.8952,
            'longitude' => 91.8690,
            'gps_address' => 'Zindabazar Main Point, Sylhet',
            'same_as_gps_location' => false,
            'address' => 'Floor 3, Shop 310, Al-Hamra Shopping City, Zindabazar, Sylhet',
            'city' => 'Sylhet',
            'area' => 'Zindabazar',
        ])->assertStatus(200);

        $shop->refresh();
        $this->assertFalse($shop->same_as_gps_location);
        $this->assertEquals('Floor 3, Shop 310, Al-Hamra Shopping City, Zindabazar, Sylhet', $shop->address);
    }

    /** @test */
    public function vendor_can_spread_business_across_branches_with_exact_branch_tags_on_tiles()
    {
        $vendor = User::factory()->create(['role' => 'vendor']);

        $profile = VendorProfile::create([
            'user_id' => $vendor->id,
            'shop_name' => 'Quantum Fix BD',
            'brand_name' => 'Quantum Fix BD',
            'is_branched' => false,
            'business_license_no' => 'TRAD/BD/990011',
            'nid_number' => '1992000111222',
            'verification_status' => 'approved',
            'shop_address' => 'House 12, Dhanmondi 27, Dhaka',
            'gps_address' => 'Dhanmondi 27, Dhaka',
            'same_as_gps_location' => false,
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'shop_latitude' => 23.7461,
            'shop_longitude' => 90.3742,
            'categories' => ['Laptop', 'Phone'],
        ]);

        $hqShop = RepairShop::factory()->create([
            'vendor_profile_id' => $profile->id,
            'name' => 'Quantum Fix BD',
            'slug' => 'quantum-fix-bd',
            'is_branch' => false,
            'branch_name' => null,
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'is_featured' => true,
        ]);

        // Vendor expands to a 2nd branch in Chattogram from Dashboard
        $response = $this->actingAs($vendor)->postJson('/api/vendors/branches', [
            'vendor_profile_id' => $profile->id,
            'primary_branch_name' => 'Dhanmondi HQ',
            'branch_name' => 'GEC Circle Branch',
            'latitude' => 22.3591,
            'longitude' => 91.8215,
            'gps_address' => 'GEC Circle, Chattogram',
            'same_as_gps_location' => false,
            'address' => 'Level 2, Sanmar Ocean Annex, GEC Circle, Chattogram',
            'city' => 'Chattogram',
            'area' => 'GEC Circle',
        ]);

        $response->assertStatus(201);

        $profile->refresh();
        $hqShop->refresh();
        $ctgBranch = RepairShop::where('slug', 'quantum-fix-bd-gec-circle-branch')->firstOrFail();

        $this->assertTrue($profile->is_branched);
        $this->assertTrue($hqShop->is_branch);
        $this->assertEquals('Dhanmondi HQ', $hqShop->branch_name);
        $this->assertEquals('Quantum Fix BD', $ctgBranch->name);
        $this->assertTrue($ctgBranch->is_branch);
        $this->assertEquals('GEC Circle Branch', $ctgBranch->branch_name);

        // Duplicate branch name under same vendor is rejected
        $this->actingAs($vendor)->postJson('/api/vendors/branches', [
            'vendor_profile_id' => $profile->id,
            'branch_name' => 'GEC Circle Branch',
            'latitude' => 22.3300,
            'longitude' => 91.8100,
            'gps_address' => 'Agrabad, Chattogram',
            'same_as_gps_location' => true,
        ])->assertStatus(422);

        // Homepage, Explore Tiles, and Shop Profile display exact Branch Tags
        $this->get('/')->assertStatus(200)->assertSee('Dhanmondi HQ');
        $this->get('/shops/explore?q=Quantum')->assertStatus(200)
            ->assertSee('Dhanmondi HQ')
            ->assertSee('GEC Circle Branch');
        $this->get('/shops/' . $hqShop->slug)->assertStatus(200)
            ->assertSee('Branch: Dhanmondi HQ')
            ->assertSee('GEC Circle Branch');
    }

    /** @test */
    public function nationwide_bangladesh_nearby_api_and_osm_seed_data_are_fully_coherent()
    {
        $this->seed([
            UserSeeder::class,
            ShopSeeder::class,
        ]);

        // 1. Verify total seeded scale (34 curated + 1,260 Bangladesh OSM shops = 1,294)
        $totalShops = RepairShop::count();
        $this->assertGreaterThanOrEqual(1290, $totalShops);

        // 2. Verify Miraz Telecom from OpenStreetMap Bangladesh dataset is seeded as multi-branch in Hatirpool
        $mirazBranches = RepairShop::where('name', 'Miraz Telecom')->get();
        $this->assertCount(2, $mirazBranches);
        foreach ($mirazBranches as $mb) {
            $this->assertTrue($mb->is_branch);
            $this->assertNotEmpty($mb->branch_name);
            $this->assertEquals('Dhaka', $mb->city);
            $this->assertEquals('Hatirpool', $mb->area);
        }

        // 3. Verify ProTech Solutions has 3 coherent branches across Dhaka & Chattogram
        $protechBranches = RepairShop::where('name', 'ProTech Solutions')->pluck('branch_name')->all();
        $this->assertCount(3, $protechBranches);
        $this->assertContains('Banani DOHS Flagship', $protechBranches);
        $this->assertContains('Uttara Sector 7', $protechBranches);
        $this->assertContains('Agrabad, Chattogram', $protechBranches);

        // 4. Verify Nationwide Nearby API works in Chattogram and hides private `gps_address`
        $nearbyCtg = $this->getJson('/api/shops/nearby?lat=22.3264&lng=91.8155&radius=15');
        $nearbyCtg->assertStatus(200);
        $this->assertGreaterThan(0, $nearbyCtg->json('total_found'));
        $firstShopJson = $nearbyCtg->json('shops.0');
        $this->assertArrayNotHasKey('gps_address', $firstShopJson);

        // 5. Verify strict global coherence invariants across EVERY seeded shop & vendor profile
        $incoherentDualAddressCount = RepairShop::get()->filter(function (RepairShop $s) {
            $addressesEqual = ($s->address === $s->gps_address);
            if ($s->same_as_gps_location && !$addressesEqual) {
                return true;
            }
            if ($s->is_branch && empty($s->branch_name)) {
                return true;
            }
            if (!$s->is_branch && !empty($s->branch_name)) {
                return true;
            }
            return false;
        })->count();

        $this->assertEquals(0, $incoherentDualAddressCount, 'Every seeded shop must satisfy dual-address and branch-tag invariants.');

        $incoherentVendorBranchFlags = RepairShop::with('vendorProfile')->get()->filter(function (RepairShop $s) {
            return $s->vendorProfile && ($s->vendorProfile->is_branched !== $s->is_branch);
        })->count();

        $this->assertEquals(0, $incoherentVendorBranchFlags, 'VendorProfile.is_branched must strictly match RepairShop.is_branch.');
    }
}

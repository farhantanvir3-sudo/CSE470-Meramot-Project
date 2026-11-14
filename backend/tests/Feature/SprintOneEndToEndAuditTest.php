<?php

namespace Tests\Feature;

use App\Http\Controllers\AdminLedgerController;
use App\Models\Bid;
use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\FinancialLedger;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\VendorPayout;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ============================================================================
 * SPRINT 1 END-TO-END HARDENING & AUTHORIZATION REGRESSION SUITE
 * ============================================================================
 * Covers cross-role authorization gates, ownership boundaries, R11 shop setup
 * skill/price parsing, R7 completion settlement, R1 rider order isolation, and
 * R16 per-shop escrow batch settlement with VendorPayout linkage.
 * ============================================================================
 */
class SprintOneEndToEndAuditTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function non_admin_authenticated_roles_cannot_access_admin_governance_or_financial_routes()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'vendor']);
        $rider = User::factory()->create(['role' => 'delivery_agent']);
        $admin = User::factory()->create(['role' => 'admin']);

        $pendingVendorProfile = VendorProfile::create([
            'user_id' => $vendor->id,
            'shop_name' => 'Self Approve Attempt Lab',
            'business_license_no' => 'TRAD/DNCC/500900',
            'nid_number' => '1997000111999',
            'verification_status' => 'pending',
            'shop_address' => 'Road 11, Banani, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
        ]);

        $pendingRiderUser = User::factory()->create(['role' => 'delivery_agent']);
        $pendingAgent = DeliveryAgent::create([
            'user_id' => $pendingRiderUser->id,
            'applicant_name' => 'Test Rider Applicant',
            'applicant_phone' => '+8801711223344',
            'personal_email' => 'rider.applicant@gmail.com',
            'nid_number' => '1998000222333',
            'license_number' => 'DL-BD-889900',
            'vehicle_type' => 'motorcycle',
            'registration_status' => 'PENDING',
            'is_active' => false,
        ]);

        foreach ([$customer, $vendor, $rider] as $nonAdmin) {
            $this->actingAs($nonAdmin)->get('/admin')->assertStatus(403);
            $this->actingAs($nonAdmin)->get('/admin/vendors/pending')->assertStatus(403);
            $this->actingAs($nonAdmin)->post("/admin/vendors/{$pendingVendorProfile->id}/approve")->assertStatus(403);
            $this->actingAs($nonAdmin)->get('/admin/delivery-agents/pending')->assertStatus(403);
            $this->actingAs($nonAdmin)->post("/admin/delivery-agents/{$pendingAgent->id}/approve")->assertStatus(403);
            $this->actingAs($nonAdmin)->get('/admin/financial-overview')->assertStatus(403);
            $this->actingAs($nonAdmin)->post('/admin/financial-overview/auto-settle')->assertStatus(403);
        }

        $this->assertSame('pending', $pendingVendorProfile->fresh()->verification_status);
        $this->assertSame('PENDING', $pendingAgent->fresh()->registration_status);

        // Verified Admin has full access
        $this->actingAs($admin)->get('/admin')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/vendors/pending')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/delivery-agents/pending')->assertStatus(200);
        $this->actingAs($admin)->get('/admin/financial-overview')->assertStatus(200);
    }

    /** @test */
    public function vendor_setup_shop_parses_pipe_and_currency_formatted_skill_prices_and_guards_bidding_board()
    {
        $unapprovedVendor = User::factory()->create(['role' => 'vendor']);
        VendorProfile::create([
            'user_id' => $unapprovedVendor->id,
            'shop_name' => 'Pending Review Workshop',
            'business_license_no' => 'TRAD/DNCC/102030',
            'nid_number' => '1994112233445',
            'verification_status' => 'under_review',
            'shop_address' => 'Mirpur 10, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Mirpur',
        ]);

        // Unapproved vendor is redirected away from Bidding Board to onboarding status tracker
        $this->actingAs($unapprovedVendor)
            ->get('/vendor/bidding-board')
            ->assertRedirect('/vendor/onboarding');

        // Approved vendor completes shop setup with mixed skill formats
        $approvedVendor = User::factory()->create(['role' => 'vendor']);
        $profile = VendorProfile::create([
            'user_id' => $approvedVendor->id,
            'shop_name' => 'Precision Logic BD',
            'brand_name' => 'Precision Logic BD',
            'business_license_no' => 'TRAD/DNCC/405060',
            'nid_number' => '1993556677889',
            'verification_status' => 'approved',
            'shop_address' => 'House 14, Road 7, Dhanmondi, Dhaka',
            'gps_address' => 'Dhanmondi 7, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'shop_latitude' => 23.7461,
            'shop_longitude' => 90.3742,
        ]);

        $shop = RepairShop::factory()->create([
            'vendor_profile_id' => $profile->id,
            'name' => 'Precision Logic BD',
            'slug' => 'precision-logic-bd',
            'is_active' => true,
            'is_public' => false,
            'setup_complete' => false,
        ]);

        $this->actingAs($approvedVendor)->post('/vendor/setup-shop', [
            'shop_name' => 'Precision Logic BD',
            'description' => 'Component-level MacBook & smartphone board repair center.',
            'phone' => '+8801711998877',
            'gps_address' => 'Dhanmondi 7, Dhaka',
            'same_as_gps_location' => '0',
            'address' => 'Level 3, Suite 302, Dhanmondi Plaza, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'latitude' => 23.7461,
            'longitude' => 90.3742,
            'courier_pickup' => '1',
            'in_shop_repair' => '1',
            'inspection_fee' => 300,
            'base_labor_fee' => 600,
            'skill_tags' => [
                'OLED Display Assembly|4500',
                'Motherboard IC Microsoldering (৳3200)',
                'Diagnostics & Thermal Servicing',
            ],
        ])->assertRedirect('/vendor/dashboard');

        $shop->refresh();
        $this->assertTrue($shop->is_public);
        $this->assertTrue($shop->setup_complete);
        $this->assertEquals([
            'OLED Display Assembly',
            'Motherboard IC Microsoldering',
            'Diagnostics & Thermal Servicing',
        ], $shop->specialties);

        $oledService = ShopService::where('shop_id', $shop->id)->where('name', 'OLED Display Assembly')->firstOrFail();
        $this->assertSame('FIXED', $oledService->pricing_type);
        $this->assertEquals(4500.00, (float) $oledService->base_price);

        $icService = ShopService::where('shop_id', $shop->id)->where('name', 'Motherboard IC Microsoldering')->firstOrFail();
        $this->assertSame('FIXED', $icService->pricing_type);
        $this->assertEquals(3200.00, (float) $icService->base_price);

        $diagService = ShopService::where('shop_id', $shop->id)->where('name', 'Diagnostics & Thermal Servicing')->firstOrFail();
        $this->assertSame('INSPECTION_REQUIRED', $diagService->pricing_type);
        $this->assertNull($diagService->base_price);
    }

    /** @test */
    public function cross_vendor_cross_customer_and_cross_rider_ownership_boundaries_are_enforced()
    {
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);

        $vendorA = User::factory()->create(['role' => 'vendor']);
        $profileA = VendorProfile::create([
            'user_id' => $vendorA->id,
            'shop_name' => 'Alpha Fix Hub',
            'business_license_no' => 'TRAD/A/1001',
            'nid_number' => '1991000001001',
            'verification_status' => 'approved',
            'shop_address' => 'Banani, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
        ]);
        $shopA = RepairShop::factory()->create(['vendor_profile_id' => $profileA->id, 'is_active' => true, 'is_public' => true]);

        $vendorB = User::factory()->create(['role' => 'vendor']);
        $profileB = VendorProfile::create([
            'user_id' => $vendorB->id,
            'shop_name' => 'Beta Fix Hub',
            'business_license_no' => 'TRAD/B/2002',
            'nid_number' => '1992000002002',
            'verification_status' => 'approved',
            'shop_address' => 'Uttara, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Uttara',
        ]);
        $shopB = RepairShop::factory()->create(['vendor_profile_id' => $profileB->id, 'is_active' => true, 'is_public' => true]);

        $requestA = RepairRequest::create([
            'customer_id' => $customerA->id,
            'title' => 'iPhone 14 Pro Screen Replacement',
            'device_type' => 'Phone',
            'brand' => 'Apple',
            'model_name' => 'iPhone 14 Pro',
            'problem' => 'Cracked OLED panel after drop.',
            'preferred_service_type' => 'pickup_dropoff',
            'pickup_address' => 'Road 11, Banani, Dhaka',
            'status' => RepairRequest::STATUS_PENDING,
        ]);

        // Vendor A cannot submit a bid impersonating Vendor B's shop_id
        $this->actingAs($vendorA)->postJson("/api/requests/{$requestA->id}/bids", [
            'shop_id' => $shopB->id,
            'proposed_cost' => 9500,
            'estimated_days' => 2,
        ])->assertStatus(403);

        // Vendor A cannot mutate Vendor B's branch address
        $this->actingAs($vendorA)->patchJson("/api/vendors/branches/{$shopB->id}", [
            'latitude' => 23.8759,
            'longitude' => 90.3795,
            'gps_address' => 'Uttara, Dhaka',
            'same_as_gps_location' => true,
        ])->assertStatus(403);

        // Legitimate bid from Vendor A on Shop A succeeds
        $this->actingAs($vendorA)->postJson("/api/requests/{$requestA->id}/bids", [
            'shop_id' => $shopA->id,
            'proposed_cost' => 9500,
            'estimated_days' => 2,
        ])->assertStatus(201);

        $bidA = Bid::where('repair_request_id', $requestA->id)->firstOrFail();

        // Customer B cannot cancel or accept bids on Customer A's request
        $this->actingAs($customerB)->post("/requests/{$requestA->id}/cancel")->assertStatus(403);
        $this->actingAs($customerB)->post("/requests/{$requestA->id}/accept-bid", [
            'bid_id' => $bidA->id,
        ])->assertStatus(403);

        // Customer A accepts bid -> request becomes ASSIGNED + RepairJob + DeliveryJob created
        $this->actingAs($customerA)->post("/requests/{$requestA->id}/accept-bid", [
            'bid_id' => $bidA->id,
        ])->assertRedirect(route('requests.track', $requestA->id));

        // Vendor B cannot bid on an already ASSIGNED request
        $this->actingAs($vendorB)->postJson("/api/requests/{$requestA->id}/bids", [
            'shop_id' => $shopB->id,
            'proposed_cost' => 8900,
            'estimated_days' => 1,
        ])->assertStatus(422);

        // Rider isolation: Rider B cannot view or advance Rider A's assigned DeliveryJob
        $riderUserA = User::factory()->create(['role' => 'delivery_agent']);
        $agentA = DeliveryAgent::create([
            'user_id' => $riderUserA->id,
            'applicant_name' => 'Rider Alpha',
            'personal_email' => 'alpha.rider@gmail.com',
            'company_email' => 'alpha@meramotbd.com',
            'nid_number' => '1995000111222',
            'license_number' => 'DL-ALPHA-01',
            'vehicle_type' => 'motorcycle',
            'registration_status' => 'APPROVED',
            'is_active' => true,
        ]);

        $riderUserB = User::factory()->create(['role' => 'delivery_agent']);
        DeliveryAgent::create([
            'user_id' => $riderUserB->id,
            'applicant_name' => 'Rider Beta',
            'personal_email' => 'beta.rider@gmail.com',
            'company_email' => 'beta@meramotbd.com',
            'nid_number' => '1995000333444',
            'license_number' => 'DL-BETA-02',
            'vehicle_type' => 'motorcycle',
            'registration_status' => 'APPROVED',
            'is_active' => true,
        ]);

        $deliveryJob = DeliveryJob::where('repair_request_id', $requestA->id)->firstOrFail();
        $deliveryJob->update(['delivery_agent_id' => $agentA->id, 'status' => 'SCHEDULED']);

        $this->actingAs($riderUserB)->get("/delivery/order/{$deliveryJob->id}")->assertStatus(403);
        $this->actingAs($riderUserB)->post("/delivery/order/{$deliveryJob->id}/status")->assertStatus(403);

        // Assigned Rider A can view and advance milestone
        $this->actingAs($riderUserA)->get("/delivery/order/{$deliveryJob->id}")->assertStatus(200);
        $this->actingAs($riderUserA)->post("/delivery/order/{$deliveryJob->id}/status")
            ->assertRedirect("/delivery/order/{$deliveryJob->id}");
        $this->assertSame('DISPATCHED', $deliveryJob->fresh()->status);
    }

    /** @test */
    public function status_transition_completion_and_batch_auto_settle_maintain_double_entry_and_vendor_payout_invariants()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $vendor = User::factory()->create(['role' => 'vendor']);

        $profile = VendorProfile::create([
            'user_id' => $vendor->id,
            'shop_name' => 'Quantum Repair Studio',
            'business_license_no' => 'TRAD/DNCC/887766',
            'nid_number' => '1990112233445',
            'verification_status' => 'approved',
            'shop_address' => 'Gulshan 2, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Gulshan',
        ]);
        $shop = RepairShop::factory()->create(['vendor_profile_id' => $profile->id, 'is_active' => true, 'is_public' => true]);

        // Complete a ৳3,000 repair via StatusTransitionController@updateStatus
        $req1 = RepairRequest::create([
            'customer_id' => $customer->id,
            'requested_shop_id' => $shop->id,
            'title' => 'MacBook Battery Service #1',
            'device_type' => 'Laptop',
            'problem' => 'Swollen battery pack.',
            'preferred_service_type' => 'in_shop',
            'quoted_final_amount' => 3000.00,
            'status' => RepairRequest::STATUS_REPAIRING,
        ]);
        $job1 = RepairJob::create([
            'repair_request_id' => $req1->id,
            'shop_id' => $shop->id,
            'status' => 'REPAIRING',
            'final_quoted_amount' => 3000.00,
        ]);

        $this->actingAs($customer)
            ->postJson("/requests/{$req1->id}/update-status", ['status' => 'COMPLETED'])
            ->assertStatus(200)
            ->assertJsonPath('new_status', 'COMPLETED');

        $this->assertTrue(DB::table('invoices')->where('repair_job_id', $job1->id)->exists());
        $this->assertEquals(1, FinancialLedger::where('shop_id', $shop->id)->where('entry_type', 'VENDOR_EARNING')->count());

        // Post a second identical ৳3,000 order settlement for the SAME shop (edge case: identical earning amounts)
        AdminLedgerController::settleOrderWithCommission(3000.00, $shop->id);
        $this->assertEquals(2, FinancialLedger::where('shop_id', $shop->id)->where('entry_type', 'VENDOR_EARNING')->count());
        $this->assertSame(1, AdminLedgerController::countUnsettledShops());

        // Trigger batch auto-settlement: must disburse full ৳5,400 (2 x ৳2,700) AND link a PAID VendorPayout
        $this->actingAs($admin)
            ->post('/admin/financial-overview/auto-settle', ['limit' => 20])
            ->assertRedirect(route('admin.financial-overview'));

        $this->assertSame(0, AdminLedgerController::countUnsettledShops());

        $payoutLedger = FinancialLedger::where('shop_id', $shop->id)
            ->where('entry_type', 'VENDOR_PAYOUT')
            ->firstOrFail();

        $this->assertEquals(5400.00, (float) $payoutLedger->amount);
        $this->assertNotNull($payoutLedger->vendor_payout_id);

        $vendorPayout = VendorPayout::findOrFail($payoutLedger->vendor_payout_id);
        $this->assertSame('PAID', $vendorPayout->status);
        $this->assertEquals(5400.00, (float) $vendorPayout->amount);

        // Zero orphan VENDOR_PAYOUT ledger rows across the entire ledger
        $this->assertSame(
            0,
            FinancialLedger::where('entry_type', 'VENDOR_PAYOUT')->whereNull('vendor_payout_id')->count()
        );
    }

    /** @test */
    public function authenticated_rider_vendor_customer_and_admin_sessions_route_directly_to_proper_workspaces()
    {
        // 1. Approved Active Delivery Partner session
        $riderUser = User::factory()->create([
            'name' => 'Kamal Rider',
            'email' => 'kamal.rider@meramotbd.com',
            'role' => 'delivery_agent',
            'is_active' => true,
        ]);
        DeliveryAgent::create([
            'user_id' => $riderUser->id,
            'applicant_name' => 'Kamal Rider',
            'personal_email' => 'kamal.personal@gmail.com',
            'company_email' => 'kamal.rider@meramotbd.com',
            'nid_number' => '1994000999888',
            'license_number' => 'DL-KAMAL-77',
            'vehicle_type' => 'motorcycle',
            'registration_status' => 'APPROVED',
            'is_active' => true,
        ]);

        // Staying logged in as Rider: /delivery/login, /login, /register, /auth/google, /vendor/apply, /vendor/dashboard ALL go straight to /delivery!
        $this->actingAs($riderUser)->get('/delivery/login')->assertRedirect('/delivery');
        $this->actingAs($riderUser)->get('/login')->assertRedirect('/delivery');
        $this->actingAs($riderUser)->get('/register')->assertRedirect('/delivery');
        $this->actingAs($riderUser)->get('/auth/google')->assertRedirect('/delivery');
        $this->actingAs($riderUser)->get('/vendor/apply')->assertRedirect('/delivery');
        $this->actingAs($riderUser)->get('/vendor/dashboard')->assertRedirect('/delivery');
        $this->actingAs($riderUser)->get('/vendor/bidding-board')->assertRedirect('/delivery');

        // Rider browsing Marketplace Home sees direct Rider Dashboard pill and NO Vendor Portal / Delivery Partner Login confusion
        $riderHome = $this->actingAs($riderUser)->get('/');
        $riderHome->assertStatus(200);
        $riderHome->assertSee('Rider Dashboard');
        $riderHome->assertDontSee('Delivery Partner Login');
        $riderHome->assertDontSee('Vendor Portal &amp; Direct Orders', false);

        // 2. Approved Configured Vendor session
        $vendorUser = User::factory()->create([
            'name' => 'Tariq Workshop',
            'email' => 'tariq@workshop.bd',
            'role' => 'vendor',
            'is_active' => true,
        ]);
        $vendorProfile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Tariq Logic Lab',
            'business_license_no' => 'TRAD/DNCC/551122',
            'nid_number' => '1989000111222',
            'verification_status' => 'approved',
            'shop_address' => 'Road 11, Banani, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
        ]);
        $vendorShop = RepairShop::factory()->create([
            'vendor_profile_id' => $vendorProfile->id,
            'name' => 'Tariq Logic Lab',
            'slug' => 'tariq-logic-lab',
            'setup_complete' => true,
            'inspection_fee' => 300,
            'base_labor_fee' => 600,
            'is_active' => true,
            'is_public' => true,
        ]);

        // Staying logged in as Approved Vendor: /login, /register, /auth/google, /vendor/apply ALL go straight to /vendor/dashboard!
        $this->actingAs($vendorUser)->get('/login')->assertRedirect('/vendor/dashboard');
        $this->actingAs($vendorUser)->get('/register')->assertRedirect('/vendor/dashboard');
        $this->actingAs($vendorUser)->get('/auth/google')->assertRedirect('/vendor/dashboard');
        $this->actingAs($vendorUser)->get('/vendor/apply')->assertRedirect('/vendor/dashboard');

        // Vendor browsing Marketplace Home sees direct Vendor Dashboard pill
        $vendorHome = $this->actingAs($vendorUser)->get('/');
        $vendorHome->assertStatus(200);
        $vendorHome->assertSee('Vendor Dashboard');
        $vendorHome->assertDontSee('Delivery Partner Login');

        // Vendor viewing their own shop storefront sees owner management controls and cannot self-review
        $this->actingAs($vendorUser)->get('/shops/' . $vendorShop->slug)
            ->assertStatus(200)
            ->assertSee('Manage in Vendor Dashboard')
            ->assertDontSee('Write Review');
        $this->actingAs($vendorUser)->get('/reviews/create/' . $vendorShop->id)
            ->assertRedirect(route('shops.show', $vendorShop->slug));

        // 3. Logged-in Customer & Guest isolation from Vendor Dashboard
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $this->post('/logout');

        // Guest hitting /vendor/dashboard redirects to /login instead of exposing another shop's dashboard
        $this->get('/vendor/dashboard')->assertRedirect(route('login'));

        // Logged-in Customer hitting /vendor/dashboard redirects to /vendor/apply (never leaks ProTech Solutions)
        $this->actingAs($customer)->get('/vendor/dashboard')->assertRedirect(route('vendor.apply'));
        $this->actingAs($customer)->get('/vendor/bidding-board')->assertRedirect(route('vendor.apply'));

        // 4. Admin account cannot self-delete via /profile
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);
        $this->actingAs($admin)->delete('/profile')->assertRedirect(route('profile.show'));
        $this->assertDatabaseHas('users', ['id' => $admin->id, 'is_active' => true]);
    }

    /** @test */
    public function vendor_setup_shop_supports_cover_banner_and_sprint_one_ui_polish_invariants_hold()
    {
        $vendorUser = User::factory()->create([
            'role' => 'vendor',
            'phone' => '01712345678',
            'is_active' => true,
        ]);
        $profile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'owner_name' => 'Nadia Karim',
            'business_email' => $vendorUser->email,
            'phone' => '01712345678',
            'shop_name' => 'Dhaka Micro Lab',
            'brand_name' => 'Dhaka Micro Lab',
            'business_license_no' => 'TL-DML-2026',
            'nid_number' => '1993123456789',
            'verification_status' => 'approved',
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
        ]);
        $shop = RepairShop::factory()->create([
            'vendor_profile_id' => $profile->id,
            'name' => 'Dhaka Micro Lab',
            'slug' => 'dhaka-micro-lab',
            'setup_complete' => false,
            'is_active' => false,
            'is_public' => false,
        ]);

        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $bannerFile = UploadedFile::fake()->createWithContent('cover-banner.png', $tinyPng);
        $logoFile = UploadedFile::fake()->createWithContent('shop-logo.png', $tinyPng);

        $this->actingAs($vendorUser)->post('/vendor/setup-shop', [
            'shop_name' => 'Dhaka Micro Lab',
            'phone' => '01712345678',
            'description' => 'Precision Apple & Android micro-soldering workshop.',
            'address' => 'Road 27, Dhanmondi, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'courier_pickup' => '1',
            'in_shop_repair' => '1',
            'inspection_fee' => 250,
            'base_labor_fee' => 550,
            'skill_tags' => ['Logic board repair|2500', 'Screen replacement|1800'],
            'logo' => $logoFile,
            'banner' => $bannerFile,
        ])->assertRedirect(route('vendor.dashboard'));

        $shop->refresh();
        $this->assertTrue((bool) $shop->setup_complete);
        $this->assertNotEmpty($shop->logo_url);
        $this->assertNotEmpty($shop->banner_url);

        // Public storefront renders the uploaded cover banner and zero-flicker dark mode theme initializer
        $storefront = $this->get('/shops/' . $shop->slug);
        $storefront->assertStatus(200);
        $storefront->assertSee('Dhaka Micro Lab Cover Banner');
        $storefront->assertSee("localStorage.getItem('meramot_theme') === 'dark'", false);

        // Bid tracking renders clean formatted currency without duplicate parenthetical integer clutter
        $customer = User::factory()->create(['role' => 'customer']);
        $req = RepairRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'BIDDING',
        ]);
        Bid::factory()->create([
            'repair_request_id' => $req->id,
            'shop_id' => $shop->id,
            'proposed_cost' => 3400,
            'estimated_days' => 2,
            'status' => 'ACTIVE',
        ]);

        $trackResp = $this->actingAs($customer)->get(route('requests.track', $req->id));
        $trackResp->assertStatus(200);
        $trackResp->assertSee('৳3,400.00');
        $trackResp->assertDontSee('(3400)');
    }

    /** @test */
    public function featuring_approved_shop_publishes_it_and_orders_featured_shops_as_lifo_stack()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // Create an older high-rated featured shop
        $olderVendor = User::factory()->create(['role' => 'vendor']);
        $olderProfile = VendorProfile::create([
            'user_id' => $olderVendor->id,
            'owner_name' => 'Older Owner',
            'shop_name' => 'Older High Rated Lab',
            'brand_name' => 'Older High Rated Lab',
            'phone' => '01700000111',
            'address' => 'Banani, Dhaka',
            'verification_status' => 'approved',
        ]);
        $olderShop = RepairShop::factory()->create([
            'vendor_profile_id' => $olderProfile->id,
            'name' => 'Older High Rated Lab',
            'slug' => 'older-high-rated-lab',
            'rating' => 4.95,
            'is_featured' => true,
            'featured_at' => now()->subDays(2),
            'is_active' => true,
            'is_public' => true,
        ]);

        // Create a newly approved shop that hasn't completed /vendor/setup-shop yet (is_public=false, rating=0)
        $dragonVendor = User::factory()->create(['role' => 'vendor']);
        $dragonProfile = VendorProfile::create([
            'user_id' => $dragonVendor->id,
            'owner_name' => 'Dragon Owner',
            'shop_name' => 'Dragon Tech',
            'brand_name' => 'Dragon Tech',
            'phone' => '01700000222',
            'address' => 'Panthapath, Dhaka',
            'verification_status' => 'approved',
        ]);
        $dragonShop = RepairShop::factory()->create([
            'vendor_profile_id' => $dragonProfile->id,
            'name' => 'Dragon Tech',
            'slug' => 'dragon-tech',
            'city' => 'Dhaka',
            'area' => 'Panthapath',
            'rating' => 0.00,
            'is_featured' => false,
            'is_active' => false,
            'is_public' => false,
            'setup_complete' => false,
        ]);

        // Admin clicks ★ Feature on Dragon Tech
        $this->actingAs($admin)
            ->post(route('admin.vendors.toggle-featured', $dragonShop->id))
            ->assertRedirect();

        $dragonShop->refresh();
        $this->assertTrue((bool) $dragonShop->is_featured);
        $this->assertTrue((bool) $dragonShop->is_active);
        $this->assertTrue((bool) $dragonShop->is_public);
        $this->assertNotNull($dragonShop->featured_at);

        // Dragon Tech is immediately searchable via /shops?q=dragon
        $this->get('/shops?q=dragon')
            ->assertStatus(200)
            ->assertSee('Dragon Tech');

        // Homepage Featured Shops behaves as a LIFO stack: Dragon Tech appears BEFORE Older High Rated Lab
        $homeResp = $this->get('/');
        $homeResp->assertStatus(200);
        $homeResp->assertSeeInOrder(['Dragon Tech', 'Older High Rated Lab']);
    }
}

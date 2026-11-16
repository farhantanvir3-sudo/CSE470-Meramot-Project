<?php

namespace Tests\Feature;

use App\Models\DeliveryJob;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ServicePackage;
use App\Models\SparePartListing;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class StoreCapabilitiesAndFulfillmentModesTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedVendorWithShop(array $shopOverrides = [], array $profileOverrides = []): array
    {
        $name = $shopOverrides['name'] ?? ($profileOverrides['shop_name'] ?? 'Metro Circuit Workshop');

        $vendor = User::factory()->create([
            'role' => 'vendor',
            'is_active' => true,
            'city' => $shopOverrides['city'] ?? 'Dhaka',
            'area' => $shopOverrides['area'] ?? 'Banani',
            'phone' => '017' . rand(10000000, 99999999),
        ]);

        $profile = VendorProfile::create(array_merge([
            'user_id' => $vendor->id,
            'shop_name' => $name,
            'brand_name' => $name,
            'owner_name' => $vendor->name,
            'business_email' => $vendor->email,
            'phone' => $vendor->phone,
            'business_license_no' => 'TRAD/DNCC/' . rand(100000, 999999),
            'nid_number' => '1994' . rand(100000000, 999999999),
            'verification_status' => 'approved',
            'shop_address' => 'Road 11, Banani, Dhaka',
            'gps_address' => 'Banani, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'shop_latitude' => 23.7937,
            'shop_longitude' => 90.4066,
            'courier_pickup' => false,
            'in_shop_repair' => true,
            'spare_parts' => false,
            'provides_fixed_packages' => false,
        ], $profileOverrides));

        $shop = RepairShop::factory()->create(array_merge([
            'vendor_profile_id' => $profile->id,
            'name' => $name,
            'slug' => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $name)) . '-' . uniqid(),
            'city' => 'Dhaka',
            'area' => 'Banani',
            'address' => 'Road 11, Banani, Dhaka',
            'latitude' => 23.7937,
            'longitude' => 90.4066,
            'categories' => ['IN_SHOP_REPAIR'],
            'supports_pickup' => false,
            'supports_onsite_repair' => true,
            'free_delivery' => false,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'is_active' => true,
            'is_public' => true,
        ], $shopOverrides));

        return [$vendor, $profile, $shop];
    }

    private function validApplyPayload(array $overrides = []): array
    {
        Storage::fake('public');
        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');

        return array_merge([
            'owner_name' => 'Farhan Merchant',
            'business_email' => 'merchant-' . uniqid() . '@workshop.bd',
            'phone' => '01755667788',
            'shop_name' => 'Dhaka Hardware & Parts ' . rand(10, 99),
            'password' => 'VendorSecure123',
            'password_confirmation' => 'VendorSecure123',
            'business_license_no' => 'TRAD/DNCC/' . rand(100000, 999999),
            'nid_number' => '1994001122334',
            'shop_latitude' => 23.7940,
            'shop_longitude' => 90.4043,
            'gps_address' => 'Banani, Dhaka',
            'same_as_gps_location' => '0',
            'shop_address' => 'Shop 12, Multiplan Center, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'specialties' => json_encode(['Laptop Repair']),
            'courier_pickup' => '0',
            'in_shop_repair' => '0',
            'spare_parts' => '0',
            'provides_fixed_packages' => '0',
            'notes' => 'Store capabilities application test.',
            'logo' => UploadedFile::fake()->createWithContent('logo.png', $tinyPng),
        ], $overrides);
    }

    /** @test */
    public function vendor_apply_and_setup_shop_reject_all_four_capabilities_disabled(): void
    {
        $applyEmail = 'nocaps-' . uniqid() . '@workshop.bd';
        $this->post('/vendor/apply', $this->validApplyPayload([
            'business_email' => $applyEmail,
            'courier_pickup' => '0',
            'in_shop_repair' => '0',
            'spare_parts' => '0',
            'provides_fixed_packages' => '0',
        ]))->assertSessionHasErrors();

        $this->assertDatabaseMissing('vendor_profiles', [
            'business_email' => $applyEmail,
        ]);

        [$vendor, $profile, $shop] = $this->makeApprovedVendorWithShop([
            'setup_complete' => false,
            'is_public' => false,
        ]);

        $this->actingAs($vendor)->post('/vendor/setup-shop', [
            'shop_name' => 'Empty Capability Shop',
            'phone' => '01711223344',
            'address' => 'House 10, Banani, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'latitude' => 23.7937,
            'longitude' => 90.4066,
            'courier_pickup' => '0',
            'in_shop_repair' => '0',
            'spare_parts' => '0',
            'provides_fixed_packages' => '0',
            'inspection_fee' => 300,
            'base_labor_fee' => 500,
        ])->assertSessionHasErrors();

        $this->assertFalse((bool) $shop->fresh()->setup_complete);
    }

    /** @test */
    public function pure_spare_parts_wholesaler_and_fixed_packages_only_vendors_onboard_cleanly(): void
    {
        // 1. Pure Spare Parts Wholesaler on /vendor/apply
        $wholesalerEmail = 'wholesaler-' . uniqid() . '@parts.bd';
        $applyResp = $this->post('/vendor/apply', $this->validApplyPayload([
            'business_email' => $wholesalerEmail,
            'shop_name' => 'Mega Silicon Spare Parts Wholesale',
            'spare_parts' => '1',
            'courier_pickup' => '0',
            'in_shop_repair' => '0',
            'provides_fixed_packages' => '0',
        ]));

        $wholesalerProfile = VendorProfile::where('business_email', $wholesalerEmail)->first();
        $this->assertNotNull($wholesalerProfile);
        $applyResp->assertRedirect(route('vendor.apply.success', ['id' => $wholesalerProfile->id]));

        // Pure Spare Parts Wholesaler completing /vendor/setup-shop without repair fees (fees default to 0)
        [$wsVendor, $wsProfile, $wsShop] = $this->makeApprovedVendorWithShop([
            'name' => 'Elephant Parts Wholesale',
            'setup_complete' => false,
            'is_public' => false,
            'free_delivery' => true,
        ], [
            'spare_parts' => true,
            'in_shop_repair' => false,
            'courier_pickup' => false,
            'provides_fixed_packages' => false,
        ]);

        $this->actingAs($wsVendor)->post('/vendor/setup-shop', [
            'shop_name' => 'Elephant Parts Wholesale',
            'phone' => '01711223344',
            'address' => 'Shop 45, Multiplan Center, Dhaka',
            'city' => 'Dhaka',
            'area' => 'New Market',
            'latitude' => 23.7392,
            'longitude' => 90.3887,
            'courier_pickup' => '0',
            'in_shop_repair' => '0',
            'spare_parts' => '1',
            'provides_fixed_packages' => '0',
            'inspection_fee' => '',
            'base_labor_fee' => '',
        ])->assertRedirect('/vendor/dashboard');

        $wsShop->refresh();
        $this->assertTrue((bool) $wsShop->setup_complete);
        $this->assertTrue($wsShop->isPureSparePartsWholesaler());
        $this->assertFalse($wsShop->supportsCourierPickup());
        $this->assertFalse($wsShop->supportsInShopRepair());
        $this->assertTrue($wsShop->supportsSpareParts());
        $this->assertFalse((bool) $wsShop->free_delivery);
        $this->assertEquals(0.0, (float) $wsShop->inspection_fee);
        $this->assertEquals(0.0, (float) $wsShop->base_labor_fee);
        $this->assertSame('Verified Spare Parts Store', $wsShop->offer_summary);

        // 2. Fixed-Packages-Only vendor succeeds on both /vendor/apply and /vendor/setup-shop
        $pkg = ServicePackage::factory()->create(['title' => 'Windows 11 Clean OS Install', 'fixed_price' => 800]);
        $pkgEmail = 'pkgonly-' . uniqid() . '@workshop.bd';
        $pkgApplyResp = $this->post('/vendor/apply', $this->validApplyPayload([
            'business_email' => $pkgEmail,
            'shop_name' => 'QuickOS Bench',
            'courier_pickup' => '0',
            'in_shop_repair' => '0',
            'spare_parts' => '0',
            'provides_fixed_packages' => '1',
            'service_package_ids' => [$pkg->id],
        ]));
        $pkgProfile = VendorProfile::where('business_email', $pkgEmail)->first();
        $this->assertNotNull($pkgProfile);
        $this->assertTrue((bool) $pkgProfile->provides_fixed_packages);
        $pkgApplyResp->assertRedirect(route('vendor.apply.success', ['id' => $pkgProfile->id]));

        [$fpVendor, $fpProfile, $fpShop] = $this->makeApprovedVendorWithShop([
            'name' => 'QuickOS Studio',
            'setup_complete' => false,
            'is_public' => false,
            'categories' => [],
        ]);

        $this->actingAs($fpVendor)->post('/vendor/setup-shop', [
            'shop_name' => 'QuickOS Studio',
            'phone' => '01799887766',
            'address' => 'Banani Road 7, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'latitude' => 23.7940,
            'longitude' => 90.4043,
            'courier_pickup' => '0',
            'in_shop_repair' => '0',
            'spare_parts' => '0',
            'provides_fixed_packages' => '1',
            'service_package_ids' => [$pkg->id],
            'inspection_fee' => 200,
            'base_labor_fee' => 400,
        ])->assertRedirect('/vendor/dashboard');

        $fpShop->refresh();
        $this->assertTrue($fpShop->supportsFixedPackages());
        $this->assertTrue($fpShop->supportsRepairBookings());
    }

    /** @test */
    public function pure_spare_parts_wholesaler_is_isolated_from_repair_discovery_and_bookings(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true, 'city' => 'Dhaka', 'area' => 'Banani']);

        [, $wsProfile, $wholesaler] = $this->makeApprovedVendorWithShop([
            'name' => 'Pure Parts Wholesale House',
            'slug' => 'pure-parts-wholesale-house',
            'categories' => ['SPARE_PARTS'],
            'supports_pickup' => false,
            'supports_onsite_repair' => false,
            'free_delivery' => false,
            'inspection_fee' => 0,
            'base_labor_fee' => 0,
        ], [
            'courier_pickup' => false,
            'in_shop_repair' => false,
            'spare_parts' => true,
            'provides_fixed_packages' => false,
        ]);

        [, , $repairShop] = $this->makeApprovedVendorWithShop([
            'name' => 'Banani Master Workshop',
            'slug' => 'banani-master-workshop',
            'categories' => ['IN_SHOP_REPAIR'],
            'supports_pickup' => false,
            'supports_onsite_repair' => true,
        ]);

        $part = SparePartListing::create([
            'shop_id' => $wholesaler->id,
            'vendor_profile_id' => $wsProfile->id,
            'slug' => 'wholesaler-ssd-kit-' . uniqid(),
            'product_group_key' => 'wholesaler-ssd-kit',
            'sku' => 'WS-SSD-512',
            'title' => 'NVMe 512GB OEM Module',
            'part_category' => 'Storage',
            'brand' => 'Samsung',
            'compatible_model' => 'Universal M.2',
            'condition_label' => 'Brand New',
            'image_url' => '/images/spare-parts/samsung-galaxy-s21-fe-5g-display.webp',
            'base_price' => 4200,
            'normal_price' => 4200,
            'stock_quantity' => 50,
            'sold_count' => 8,
            'wholesale_enabled' => true,
            'is_active' => true,
        ]);

        $this->assertFalse($part->supportsWorkshopInstallation());

        // Excluded from default /shops repair directory, included in explicit ?q= search
        $this->get('/shops')
            ->assertOk()
            ->assertSee($repairShop->name)
            ->assertDontSee($wholesaler->name);

        $this->get('/shops?q=' . urlencode('Pure Parts Wholesale'))
            ->assertOk()
            ->assertSee($wholesaler->name);

        // Storefront renders parts inventory directly without workshop installation CTA
        $this->get('/shops/' . $wholesaler->slug)
            ->assertOk()
            ->assertSee($wholesaler->name)
            ->assertSee($part->title)
            ->assertDontSee('Book Fixed-Price Workshop Installation')
            ->assertDontSee('Free Courier Pickup')
            ->assertDontSee('Open Direct Cart');

        // Package catalog never lists pure wholesaler among verified service workshops
        $pkg = ServicePackage::factory()->create(['title' => 'General Thermal Service', 'fixed_price' => 1100]);
        $catalogResp = $this->get('/packages/catalog');
        $catalogResp->assertOk()
            ->assertDontSee($wholesaler->name);
        $verifiedShops = $catalogResp->viewData('verifiedShops');
        if ($verifiedShops) {
            $this->assertFalse($verifiedShops->contains('id', $wholesaler->id));
        }

        // Repair request page redirects away from pure wholesaler, and direct POST /requests fails
        $this->actingAs($customer)
            ->get('/requests/create?shop_id=' . $wholesaler->id)
            ->assertRedirect('/shops/' . $wholesaler->slug);

        $this->actingAs($customer)->post('/requests', [
            'preferred_shop_id' => $wholesaler->id,
            'device_type' => 'Laptop',
            'brand' => 'HP',
            'model_name' => 'Pavilion 15',
            'issue_title' => 'Hinge replacement',
            'issue_description' => 'Left laptop hinge snapped cleanly near bezel.',
            'preferred_service_type' => 'walk_in',
        ])->assertSessionHasErrors('preferred_shop_id');

        // Direct cart package checkout against pure wholesaler is rejected
        $this->actingAs($customer)->postJson('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $wholesaler->id,
            'quantity' => 1,
        ])->assertStatus(422);
    }

    /** @test */
    public function in_shop_walk_in_booking_has_zero_delivery_fee_and_creates_zero_delivery_jobs_across_full_lifecycle(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'city' => 'Dhaka',
            'area' => 'Banani',
            'phone' => '01711223344',
        ]);

        [$vendorUser, , $inShopOnly] = $this->makeApprovedVendorWithShop([
            'name' => 'WalkIn Pro Bench',
            'categories' => ['IN_SHOP_REPAIR'],
            'supports_pickup' => false,
            'supports_onsite_repair' => true,
            'free_delivery' => false,
        ], [
            'courier_pickup' => false,
            'in_shop_repair' => true,
            'provides_fixed_packages' => true,
        ]);

        $pkg = ServicePackage::factory()->create([
            'title' => 'MacBook Thermal Deep Clean',
            'fixed_price' => 1500,
        ]);
        $inShopOnly->servicePackages()->syncWithoutDetaching([$pkg->id]);

        $this->actingAs($customer)->postJson('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $inShopOnly->id,
            'quantity' => 1,
        ])->assertOk();

        // /cart and /cart/checkout present In-Shop Walk-In option cleanly
        $this->actingAs($customer)->get('/cart')
            ->assertOk()
            ->assertSee('In-Shop Walk-In')
            ->assertDontSee('Smart-Matched Workshop');

        // Place In-Shop order with SSLCOMMERZ and empty street address
        $placeResp = $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $inShopOnly->id,
            'fulfillment_mode' => 'IN_SHOP',
            'schedule_type' => 'NOW',
            'payment_method' => 'SSLCOMMERZ',
            'area' => 'Banani',
            'address' => '',
            'phone' => '01711223344',
        ]);

        $req = RepairRequest::where('source', 'DIRECT_SERVICE')->latest('id')->firstOrFail();
        $placeResp->assertRedirect("/checkout/payment/{$req->id}");

        $this->assertTrue($req->isWalkIn());
        $this->assertFalse($req->requiresCourierLogistics());
        $this->assertFalse((bool) $req->preferred_pickup);
        $this->assertSame('walk_in', $req->preferred_service_type);
        $this->assertNull($req->delivery_type);
        // Zero delivery fee: total = 1500 + 8% platform fee (120) = 1620.00
        $this->assertEquals(1620.0, (float) $req->quoted_final_amount);

        $this->actingAs($customer)->get("/checkout/payment/{$req->id}")
            ->assertOk()
            ->assertSee('In-Shop Walk-In (No Delivery)')
            ->assertSee('0.00')
            ->assertSee('1,620.00');

        // Complete online payment & verify invoice renders zero delivery
        $this->actingAs($customer)->post('/payment/initiate', [
            'repair_request_id' => $req->id,
            'payment_method' => 'BKASH',
        ])->assertStatus(302);

        $txn = PaymentTransaction::where('repair_request_id', $req->id)->latest('id')->firstOrFail();
        $this->postJson('/payment/ipn', [
            'tran_id' => $txn->transaction_reference,
            'status' => 'VALID',
            'val_id' => 'VAL-WALKIN-1',
        ])->assertOk();

        $invoice = Invoice::findOrFail($txn->fresh()->invoice_id);
        $this->actingAs($customer)->get("/invoices/{$invoice->id}")
            ->assertOk()
            ->assertSee('In-Shop Walk-In (No Delivery)');

        // Vendor accepts Direct Order -> zero DeliveryJobs created
        $this->actingAs($vendorUser)->post("/vendor/direct-orders/{$req->id}/respond", [
            'decision' => 'accept',
        ])->assertRedirect();

        $this->assertSame('ASSIGNED', $req->fresh()->status);
        $this->assertSame(0, DeliveryJob::where('repair_request_id', $req->id)->count());

        // Vendor moves bench job through AT_SHOP -> REPAIRING -> RETURN_SCHEDULED -> still ZERO DeliveryJobs
        $job = RepairJob::where('repair_request_id', $req->id)->firstOrFail();
        foreach (['AT_SHOP', 'REPAIRING', 'RETURN_SCHEDULED'] as $status) {
            $this->actingAs($vendorUser)->post("/vendor/jobs/{$job->id}/status", [
                'status' => $status,
            ])->assertRedirect();
        }

        $this->assertSame('RETURN_SCHEDULED', $req->fresh()->status);
        $this->assertSame(0, DeliveryJob::where('repair_request_id', $req->id)->count());

        // Customer tracker displays In-Shop Walk-In and hides Pickup Address
        $this->actingAs($customer)->get("/requests/{$req->id}/track")
            ->assertOk()
            ->assertSee('In-Shop Walk-In Service')
            ->assertSee('Workshop Location')
            ->assertDontSee('Pickup Address');
    }

    /** @test */
    public function courier_pickup_creates_both_delivery_jobs_and_mode_mismatches_are_rejected(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'is_active' => true,
            'city' => 'Dhaka',
            'area' => 'Banani',
            'phone' => '01722334455',
        ]);

        [$courierVendor, , $courierOnly] = $this->makeApprovedVendorWithShop([
            'name' => 'CourierOnly Circuit Lab',
            'categories' => ['COURIER_PICKUP'],
            'supports_pickup' => true,
            'supports_onsite_repair' => false,
        ], [
            'courier_pickup' => true,
            'in_shop_repair' => false,
            'provides_fixed_packages' => true,
        ]);

        [, , $inShopOnly] = $this->makeApprovedVendorWithShop([
            'name' => 'WalkInOnly Studio',
            'categories' => ['IN_SHOP_REPAIR'],
            'supports_pickup' => false,
            'supports_onsite_repair' => true,
        ], [
            'courier_pickup' => false,
            'in_shop_repair' => true,
            'provides_fixed_packages' => true,
        ]);

        $pkg = ServicePackage::factory()->create([
            'title' => 'SSD OS Migration Package',
            'fixed_price' => 1000,
        ]);
        $courierOnly->servicePackages()->syncWithoutDetaching([$pkg->id]);
        $inShopOnly->servicePackages()->syncWithoutDetaching([$pkg->id]);

        // Mismatch 1: Walk-in mode rejected on Courier-only shop (cart & custom request)
        $this->actingAs($customer)->postJson('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $courierOnly->id,
            'quantity' => 1,
        ])->assertOk();

        $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $courierOnly->id,
            'fulfillment_mode' => 'IN_SHOP',
            'schedule_type' => 'NOW',
            'payment_method' => 'CASH',
            'phone' => '01722334455',
        ])->assertSessionHasErrors('fulfillment_mode');

        $this->actingAs($customer)->post('/requests', [
            'preferred_shop_id' => $courierOnly->id,
            'device_type' => 'Laptop',
            'brand' => 'Lenovo',
            'model_name' => 'ThinkPad T14',
            'issue_title' => 'NVMe controller issue',
            'issue_description' => 'Drive disconnects under moderate disk write load.',
            'preferred_service_type' => 'walk_in',
        ])->assertSessionHasErrors('preferred_service_type');

        // Happy Courier Pickup flow on Courier-only shop -> TO_SHOP + TO_CUSTOMER jobs
        $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $courierOnly->id,
            'fulfillment_mode' => 'COURIER_PICKUP',
            'delivery_type' => 'EXPRESS',
            'schedule_type' => 'NOW',
            'payment_method' => 'CASH',
            'area' => 'Banani',
            'address' => 'Road 11, House 14, Banani, Dhaka',
            'phone' => '01722334455',
        ])->assertRedirect();

        $courierReq = RepairRequest::where('requested_shop_id', $courierOnly->id)
            ->where('source', 'DIRECT_SERVICE')
            ->latest('id')
            ->firstOrFail();

        $this->assertTrue($courierReq->requiresCourierLogistics());
        // 1000 + 80 (8%) + 150 (express) = 1230.00
        $this->assertEquals(1230.0, (float) $courierReq->quoted_final_amount);

        $this->actingAs($courierVendor)->post("/vendor/direct-orders/{$courierReq->id}/respond", [
            'decision' => 'accept',
        ])->assertRedirect();

        $this->assertDatabaseHas('delivery_jobs', [
            'repair_request_id' => $courierReq->id,
            'direction' => 'TO_SHOP',
        ]);

        $job = RepairJob::where('repair_request_id', $courierReq->id)->firstOrFail();
        $this->actingAs($courierVendor)->post("/vendor/jobs/{$job->id}/status", ['status' => 'AT_SHOP'])->assertRedirect();
        $this->actingAs($courierVendor)->post("/vendor/jobs/{$job->id}/status", ['status' => 'REPAIRING'])->assertRedirect();
        $this->actingAs($courierVendor)->post("/vendor/jobs/{$job->id}/status", ['status' => 'RETURN_SCHEDULED'])->assertRedirect();

        $this->assertDatabaseHas('delivery_jobs', [
            'repair_request_id' => $courierReq->id,
            'direction' => 'TO_CUSTOMER',
        ]);
        $this->assertSame(2, DeliveryJob::where('repair_request_id', $courierReq->id)->count());

        // Mismatch 2: Courier Pickup mode rejected on Walk-in-only shop (cart & custom request)
        $this->actingAs($customer)->postJson('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $inShopOnly->id,
            'quantity' => 1,
        ])->assertOk();

        $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $inShopOnly->id,
            'fulfillment_mode' => 'COURIER_PICKUP',
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'area' => 'Banani',
            'address' => 'Road 11, House 14, Banani',
            'phone' => '01722334455',
        ])->assertSessionHasErrors('fulfillment_mode');

        $this->actingAs($customer)->post('/requests', [
            'preferred_shop_id' => $inShopOnly->id,
            'device_type' => 'Phone',
            'brand' => 'Apple',
            'model_name' => 'iPhone 13',
            'issue_title' => 'Charging port clean',
            'issue_description' => 'Lightning cable sits loosely inside charging port.',
            'preferred_service_type' => 'pickup_dropoff',
            'pickup_address' => 'Banani Road 11, Dhaka',
        ])->assertSessionHasErrors('preferred_service_type');
    }

    /** @test */
    public function customer_facing_views_never_expose_internal_logic_subtext(): void
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        [, , $dualShop] = $this->makeApprovedVendorWithShop([
            'name' => 'Dhaka Premier Workshop',
            'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
            'supports_pickup' => true,
            'supports_onsite_repair' => true,
        ], [
            'courier_pickup' => true,
            'in_shop_repair' => true,
            'provides_fixed_packages' => true,
        ]);

        $pkg = ServicePackage::factory()->create(['title' => 'Battery Calibration Tuneup', 'fixed_price' => 950]);
        $dualShop->servicePackages()->syncWithoutDetaching([$pkg->id]);
        $req = RepairRequest::create([
            'customer_id' => $customer->id,
            'source' => 'DIRECT_SERVICE',
            'requested_shop_id' => $dualShop->id,
            'title' => 'Battery Calibration Tuneup',
            'description' => 'Walk-in battery check.',
            'device_type' => 'Laptop',
            'brand' => 'Direct Package',
            'model_name' => 'Standard',
            'mode' => 'DIRECT_REPAIR',
            'status' => 'PENDING',
            'preferred_pickup' => false,
            'preferred_service_type' => 'walk_in',
            'contact_phone' => '01711223344',
            'checkup_fee' => 0,
            'quoted_final_amount' => 1026,
        ]);

        $this->actingAs($customer)->postJson('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $dualShop->id,
            'quantity' => 1,
        ])->assertOk();

        $forbiddenPhrases = [
            'quoted by model',
            'quoted after inspection',
            'Auto-match nearest capable workshop',
            'Auto-match nearest workshop',
            'Smart-Matched Workshop',
            'Entry Flow',
            'Direct-Order Cart',
            'Marketplace Bidding',
            'Logistics Mode',
            'Open Direct Cart',
        ];

        $urls = [
            '/',
            '/shops',
            '/shops/' . $dualShop->slug,
            '/packages/catalog',
            '/cart',
            '/cart/checkout',
            '/requests',
            "/requests/{$req->id}/track",
        ];

        foreach ($urls as $url) {
            $resp = $this->actingAs($customer)->get($url);
            $resp->assertOk();
            $body = $resp->getContent();
            foreach ($forbiddenPhrases as $phrase) {
                $this->assertStringNotContainsString(
                    $phrase,
                    $body,
                    "Unexpected internal subtext '{$phrase}' exposed on {$url}"
                );
            }
        }
    }
}

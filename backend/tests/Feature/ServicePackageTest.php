<?php

namespace Tests\Feature;

use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\PaymentTransaction;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ServicePackage;
use App\Models\ShopService;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature R8: Service Package Catalog & Unified Direct-Order Cart Tests
 * Assigned: Shanid Sajjatuz Islam [23201465]
 * Merged Direct-Order & Quickmatch Tests: Farhan Tanvir [22299179]
 */
class ServicePackageTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_displays_service_package_catalog()
    {
        ServicePackage::factory()->count(5)->create();

        $response = $this->get('/packages/catalog');

        $response->assertStatus(200);
        $response->assertViewIs('packages.catalog');
        $response->assertViewHas('packages');
    }

    /** @test */
    public function it_filters_packages_by_category()
    {
        ServicePackage::factory()->create(['device_category' => 'Phone', 'is_active' => true]);
        ServicePackage::factory()->create(['device_category' => 'Laptop', 'is_active' => true]);

        $response = $this->get('/packages/catalog?category=Phone');

        $response->assertStatus(200);
        $response->assertSee('Phone');
    }

    /** @test */
    public function it_filters_packages_by_price()
    {
        ServicePackage::factory()->create(['fixed_price' => 1500, 'is_active' => true]);
        ServicePackage::factory()->create(['fixed_price' => 3500, 'is_active' => true]);

        $response = $this->get('/packages/catalog?max_price=2000');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_searches_packages_by_title()
    {
        ServicePackage::factory()->create(['title' => 'iPhone Screen Replacement', 'is_active' => true]);
        ServicePackage::factory()->create(['title' => 'Laptop Thermal Paste', 'is_active' => true]);

        $response = $this->get('/packages/catalog?q=iPhone');

        $response->assertStatus(200);
    }

    /** @test */
    public function it_adds_package_to_cart()
    {
        $package = ServicePackage::factory()->create();

        $response = $this->postJson('/cart/add', [
            'package_id' => $package->id,
            'quantity' => 2
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals(1, count(session('cart', [])));
    }

    /** @test */
    public function it_adds_shop_service_to_cart()
    {
        $shop = RepairShop::factory()->create(['name' => 'ProTech Lab']);
        ShopService::create([
            'shop_id' => $shop->id,
            'slug' => 'display-touchscreen-replacement',
            'name' => 'Display & Touchscreen Replacement',
            'pricing_type' => 'FIXED',
            'base_price' => 1800,
            'is_active' => true,
        ]);

        $response = $this->postJson('/cart/add', [
            'shop_id' => $shop->id,
            'service_name' => 'Display & Touchscreen Replacement',
            'price' => 1800,
            'quantity' => 1,
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals(1, count(session('cart', [])));
    }

    /** @test */
    public function it_charges_the_vendor_published_price_and_rejects_unpublished_shop_services()
    {
        $shop = RepairShop::factory()->create(['name' => 'Price Guard Lab']);
        ShopService::create([
            'shop_id' => $shop->id,
            'slug' => 'battery-replacement',
            'name' => 'Battery Replacement',
            'pricing_type' => 'FIXED',
            'base_price' => 1200,
            'is_active' => true,
        ]);
        ShopService::create([
            'shop_id' => $shop->id,
            'slug' => 'board-diagnosis',
            'name' => 'Board Diagnosis',
            'pricing_type' => 'INSPECTION_REQUIRED',
            'base_price' => null,
            'is_active' => true,
        ]);

        // Tampered client price is ignored — the workshop's fixed price is charged.
        $this->postJson('/cart/add', [
            'shop_id' => $shop->id,
            'service_name' => 'battery replacement',
            'price' => 1,
        ])->assertStatus(200)->assertJson(['success' => true]);

        $item = collect(session('cart', []))->first();
        $this->assertSame('Battery Replacement', $item['title']);
        $this->assertEquals(1200.0, (float) $item['price']);

        // Services the workshop never published (or that need inspection) cannot be added.
        $this->postJson('/cart/add', [
            'shop_id' => $shop->id,
            'service_name' => 'Imaginary Service',
            'price' => 10,
        ])->assertStatus(422)->assertJson(['success' => false]);

        $this->postJson('/cart/add', [
            'shop_id' => $shop->id,
            'service_name' => 'Board Diagnosis',
            'price' => 10,
        ])->assertStatus(422);

        $this->assertCount(1, session('cart', []));
    }

    /** @test */
    public function it_updates_cart_quantity()
    {
        $package = ServicePackage::factory()->create();

        session()->put('cart', [
            $package->id => [
                'id' => $package->id,
                'title' => $package->title,
                'price' => $package->fixed_price,
                'quantity' => 1
            ]
        ]);

        $response = $this->patchJson("/cart/update/{$package->id}", [
            'quantity' => 3
        ]);

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
    }

    /** @test */
    public function it_removes_item_from_cart()
    {
        $package = ServicePackage::factory()->create();

        session()->put('cart', [
            $package->id => [
                'id' => $package->id,
                'title' => $package->title,
                'price' => $package->fixed_price,
                'quantity' => 1
            ]
        ]);

        $response = $this->deleteJson("/cart/remove/{$package->id}");

        $response->assertStatus(200);
        $response->assertJson(['success' => true]);
        $this->assertEquals(0, count(session('cart', [])));
    }

    /** @test */
    public function it_displays_cart_view()
    {
        $package = ServicePackage::factory()->create();

        session()->put('cart', [
            $package->id => [
                'id' => $package->id,
                'title' => $package->title,
                'price' => $package->fixed_price,
                'category' => $package->device_category,
                'quantity' => 2
            ]
        ]);

        $response = $this->get('/cart');

        $response->assertStatus(200);
        $response->assertViewIs('packages.cart');
        $response->assertViewHas('cart');
        $response->assertViewHas('total');
    }

    /** @test */
    public function it_clears_entire_cart()
    {
        $package = ServicePackage::factory()->create();

        session()->put('cart', [
            $package->id => [
                'id' => $package->id,
                'title' => $package->title,
                'price' => $package->fixed_price,
                'quantity' => 1
            ]
        ]);

        $response = $this->post('/cart/clear');

        $response->assertRedirect(route('packages.catalog'));
        $this->assertNull(session('cart'));
    }

    /** @test */
    public function it_proceeds_to_checkout_with_cart_items()
    {
        $package = ServicePackage::factory()->create();

        session()->put('cart', [
            $package->id => [
                'id' => $package->id,
                'title' => $package->title,
                'price' => $package->fixed_price,
                'quantity' => 1
            ]
        ]);

        $response = $this->get('/cart/checkout');

        $response->assertStatus(200);
        $response->assertViewIs('packages.checkout');
        $response->assertViewHas('cart');
        $response->assertViewHas('total');
    }

    /** @test */
    public function it_redirects_to_catalog_if_cart_empty_at_checkout()
    {
        $response = $this->get('/cart/checkout');

        $response->assertRedirect(route('packages.catalog'));
        $response->assertSessionHas('error', 'Your cart is empty');
    }

    /** @test */
    public function it_places_direct_order_into_vendor_pending_queue_and_creates_repair_job()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $shop = RepairShop::factory()->create(['name' => 'ProTech Banani', 'rating' => 4.9]);
        $package = ServicePackage::factory()->create(['title' => 'iPhone 13 OLED Swap', 'fixed_price' => 2800]);

        $this->actingAs($customer)->postJson('/cart/add', [
            'package_id' => $package->id,
            'shop_id' => $shop->id,
            'quantity' => 1,
        ])->assertOk();

        $this->assertDatabaseHas('carts', [
            'user_id' => $customer->id,
            'shop_id' => $shop->id,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'area' => 'Banani',
            'address' => 'Road 11, House 42, Banani',
            'phone' => '01711223344',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('repair_requests', [
            'customer_id' => $customer->id,
            'source' => 'DIRECT_SERVICE',
            'requested_shop_id' => $shop->id,
            'status' => 'PENDING',
        ]);

        $createdReq = RepairRequest::where('source', 'DIRECT_SERVICE')->first();
        $this->assertNotNull($createdReq);

        $this->assertDatabaseHas('repair_jobs', [
            'repair_request_id' => $createdReq->id,
            'shop_id' => $shop->id,
            'status' => 'CREATED',
        ]);

        // Vendor accepts Direct Order -> PENDING transitions to ASSIGNED
        $this->post("/vendor/direct-orders/{$createdReq->id}/respond", [
            'decision' => 'accept',
        ])->assertRedirect();

        $this->assertEquals('ASSIGNED', $createdReq->fresh()->status);
    }

    /** @test */
    public function it_quickmatches_nearest_highest_rated_workshop_first()
    {
        $customer = \App\Models\User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        // Closer (0.6 km) but lower rated (4.2) inside same 1.0 km proximity band as Shop A
        $closerLowerRated = \App\Models\RepairShop::factory()->create([
            'name' => 'Kalabagan Bench',
            'slug' => 'kalabagan-bench-' . uniqid(),
            'city' => 'Dhaka',
            'area' => 'Kalabagan',
            'latitude' => 23.748500,
            'longitude' => 90.385800,
            'rating' => 4.20,
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'delivery_radius_km' => 20,
            'is_featured' => false,
        ]);

        // Near (0.8 km) and higher rated (4.8) inside nearest 1.0 km proximity band -> MUST WIN
        $nearestTopRated = \App\Models\RepairShop::factory()->create([
            'name' => 'Dhanmondi Pro Repair',
            'slug' => 'dhanmondi-pro-repair-' . uniqid(),
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'latitude' => 23.752000,
            'longitude' => 90.383500,
            'rating' => 4.80,
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'delivery_radius_km' => 20,
            'is_featured' => false,
        ]);

        // Farther (13.7 km away in Uttara) with 5.0 rating -> loses to closer Dhanmondi distance band
        \App\Models\RepairShop::factory()->create([
            'name' => 'Uttara Apex Center',
            'slug' => 'uttara-apex-' . uniqid(),
            'city' => 'Dhaka',
            'area' => 'Uttara',
            'latitude' => 23.873810,
            'longitude' => 90.398410,
            'rating' => 5.00,
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'delivery_radius_km' => 20,
            'is_featured' => true,
            'featured_at' => now(),
        ]);

        $package = \App\Models\ServicePackage::create([
            'title' => 'Display Replacement Package',
            'description' => 'Certified OLED assembly replacement.',
            'device_category' => 'Smartphone',
            'fixed_price' => 3500,
            'estimated_turnaround_hours' => 4,
            'warranty_months' => 3,
            'is_active' => true,
        ]);

        $this->actingAs($customer);

        $syncRes = $this->postJson('/location/sync', [
            'lat' => 23.751000,
            'lng' => 90.391000,
            'city' => 'Dhaka',
            'area' => 'Panthapath',
            'address' => 'Panthapath Signal, Dhaka',
        ]);

        $syncRes->assertOk()
            ->assertJson([
                'success' => true,
                'shop_id' => $nearestTopRated->id,
            ]);

        $this->postJson('/cart/add', [
            'package_id' => $package->id,
            'quantity' => 1,
            'customer_lat' => 23.751000,
            'customer_lng' => 90.391000,
            'customer_city' => 'Dhaka',
            'customer_area' => 'Panthapath',
        ])->assertOk();

        $this->assertEquals($nearestTopRated->id, session('cart_shop_id'));
        $this->assertNotEquals($closerLowerRated->id, session('cart_shop_id'));
    }

    /** @test */
    public function it_falls_back_to_featured_workshop_when_distance_or_ratings_do_not_resolve()
    {
        $customer = \App\Models\User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        // Unfeatured Sylhet shop with same rating as featured Sylhet shop
        \App\Models\RepairShop::factory()->create([
            'name' => 'Sylhet Standard Electronics',
            'slug' => 'sylhet-standard-' . uniqid(),
            'city' => 'Sylhet',
            'area' => 'Zindabazar',
            'latitude' => 24.894900,
            'longitude' => 91.868700,
            'rating' => 4.70,
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'delivery_radius_km' => 20,
            'is_featured' => false,
        ]);

        // Featured Sylhet shop -> MUST WIN Tier 2 when no coordinates/area disambiguate
        $featuredSylhet = \App\Models\RepairShop::factory()->create([
            'name' => 'Sylhet Featured Care',
            'slug' => 'sylhet-featured-' . uniqid(),
            'city' => 'Sylhet',
            'area' => 'Amberkhana',
            'latitude' => 24.904500,
            'longitude' => 91.869800,
            'rating' => 4.70,
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'delivery_radius_km' => 20,
            'is_featured' => true,
            'featured_at' => now(),
        ]);

        // Higher-rated featured shop in another city (Dhaka) must NOT override local Sylhet featured shop
        \App\Models\RepairShop::factory()->create([
            'name' => 'Dhaka Flagship Center',
            'slug' => 'dhaka-flagship-' . uniqid(),
            'city' => 'Dhaka',
            'area' => 'Banani',
            'latitude' => 23.790428,
            'longitude' => 90.398003,
            'rating' => 5.00,
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'delivery_radius_km' => 20,
            'is_featured' => true,
            'featured_at' => now(),
        ]);

        $this->actingAs($customer);

        // City-only sync (no coordinates or specific neighborhood) triggers Tier 2 Featured fallback within city
        $syncRes = $this->postJson('/location/sync', [
            'city' => 'Sylhet',
        ]);

        $syncRes->assertOk()
            ->assertJson([
                'success' => true,
                'city' => 'Sylhet',
                'shop_id' => $featuredSylhet->id,
            ]);
    }

    /** @test */
    public function it_never_quickmatches_far_away_or_cross_city_workshops()
    {
        $customer = \App\Models\User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
        ]);

        // Cross-city Chattogram workshop with 5.0 rating
        $chattogramShop = \App\Models\RepairShop::factory()->create([
            'name' => 'ProTech Solutions Agrabad',
            'slug' => 'protech-agrabad-' . uniqid(),
            'city' => 'Chattogram',
            'area' => 'Agrabad',
            'latitude' => 22.326410,
            'longitude' => 91.815520,
            'rating' => 5.00,
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'delivery_radius_km' => 20,
            'is_featured' => true,
            'featured_at' => now(),
        ]);

        // Same city label ("Dhaka") but far away (> 25 km in outer Gazipur)
        $farShop = \App\Models\RepairShop::factory()->create([
            'name' => 'Distant Outer Hub',
            'slug' => 'distant-outer-' . uniqid(),
            'city' => 'Dhaka',
            'area' => 'Gazipur Outskirts',
            'latitude' => 24.020000,
            'longitude' => 90.420000,
            'rating' => 5.00,
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'delivery_radius_km' => 15,
            'is_featured' => true,
            'featured_at' => now(),
        ]);

        $package = \App\Models\ServicePackage::create([
            'title' => 'Battery Replacement Package',
            'description' => 'Certified replacement battery.',
            'device_category' => 'Laptop',
            'fixed_price' => 4500,
            'estimated_turnaround_hours' => 6,
            'warranty_months' => 6,
            'is_active' => true,
        ]);

        $this->actingAs($customer);

        $syncRes = $this->postJson('/location/sync', [
            'lat' => 23.751000,
            'lng' => 90.391000,
            'city' => 'Dhaka',
            'area' => 'Panthapath',
        ]);

        $syncRes->assertOk()
            ->assertJson([
                'success' => true,
                'shop_id' => null,
            ]);

        $this->postJson('/cart/add', [
            'package_id' => $package->id,
            'quantity' => 1,
            'customer_lat' => 23.751000,
            'customer_lng' => 90.391000,
            'customer_city' => 'Dhaka',
            'customer_area' => 'Panthapath',
        ])->assertOk();

        $this->assertNull(session('cart_shop_id'));
        $this->assertNotEquals($chattogramShop->id, session('cart_shop_id'));
        $this->assertNotEquals($farShop->id, session('cart_shop_id'));

        $cartResponse = $this->get('/cart');
        $cartResponse->assertOk();
        $cartResponse->assertDontSee('ProTech Solutions Agrabad');
    }

    /** @test */
    public function it_searches_packages_by_inclusion_keywords_and_sorts_by_price()
    {
        ServicePackage::create([
            'title' => 'Phone Glass Care',
            'description' => 'Fast display swap',
            'device_category' => 'Smartphone',
            'fixed_price' => 3800,
            'estimated_turnaround_hours' => 2,
            'warranty_months' => 6,
            'inclusions' => ['Genuine OLED panel assembly', 'Free screen guard'],
            'is_active' => true,
        ]);

        ServicePackage::create([
            'title' => 'Port Micro-Fix',
            'description' => 'Quick charging port fix',
            'device_category' => 'Smartphone',
            'fixed_price' => 1100,
            'estimated_turnaround_hours' => 1,
            'warranty_months' => 3,
            'inclusions' => ['OEM Type-C port replacement'],
            'is_active' => true,
        ]);

        $incRes = $this->get('/packages/catalog?q=OLED');
        $incRes->assertOk()
            ->assertSee('Phone Glass Care')
            ->assertDontSee('Port Micro-Fix');

        $lowRes = $this->get('/packages/catalog?sort=price_low');
        $lowRes->assertOk();
        $lowPackages = $lowRes->viewData('packages')->items();
        $this->assertSame('Port Micro-Fix', $lowPackages[0]->title);

        $highRes = $this->get('/packages/catalog?sort=price_high');
        $highRes->assertOk();
        $highPackages = $highRes->viewData('packages')->items();
        $this->assertSame('Phone Glass Care', $highPackages[0]->title);
    }

    /** @test */
    public function it_completes_end_to_end_direct_package_order_to_vendor_accept_and_courier_queue_sync()
    {
        $customer = User::factory()->create([
            'name' => 'Samira Customer',
            'role' => 'customer',
            'is_active' => true,
        ]);

        $vendorUser = User::factory()->create([
            'name' => 'Kamrul Workshop Owner',
            'role' => 'vendor',
            'is_active' => true,
        ]);

        $vendorProfile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Panthapath Fixers',
            'business_license_no' => 'TRAD/DNCC/991100',
            'nid_number' => '1992112233445',
            'verification_status' => 'approved',
            'shop_address' => 'Green Road, Panthapath, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Panthapath',
            'is_setup_completed' => true,
        ]);

        $shop = RepairShop::factory()->create([
            'vendor_profile_id' => $vendorProfile->id,
            'name' => 'Panthapath Fixers',
            'address' => 'Green Road, Panthapath, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Panthapath',
            'rating' => 4.9,
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
        ]);

        $riderUser = User::factory()->create([
            'name' => 'Arif Courier',
            'email' => 'arif.rider@meramotbd.com',
            'role' => 'delivery_agent',
            'is_active' => true,
        ]);

        DeliveryAgent::create([
            'user_id' => $riderUser->id,
            'applicant_name' => 'Arif Courier',
            'personal_email' => 'arif.rider@gmail.com',
            'company_email' => 'arif.rider@meramotbd.com',
            'nid_number' => '1995001122334',
            'license_number' => 'DL-DHK-9910',
            'vehicle_type' => 'motorcycle',
            'registration_status' => 'APPROVED',
            'status' => 'AVAILABLE',
            'is_active' => true,
        ]);

        $package = ServicePackage::create([
            'title' => 'Original Display Panel Replacement',
            'description' => 'OLED assembly fitted in a dust-free bench.',
            'device_category' => 'Smartphone',
            'fixed_price' => 2800,
            'estimated_turnaround_hours' => 2,
            'warranty_months' => 6,
            'inclusions' => ['OLED display assembly'],
            'is_active' => true,
            'order_count' => 0,
        ]);

        // 1. Customer adds package, updates quantity, and places EXPRESS direct order
        $this->actingAs($customer)
            ->postJson('/cart/add', [
                'package_id' => $package->id,
                'shop_id' => $shop->id,
                'quantity' => 1,
            ])->assertOk();

        $this->actingAs($customer)
            ->patchJson("/cart/update/{$package->id}", ['quantity' => 2])
            ->assertOk()
            ->assertJson(['success' => true, 'subtotal' => 5600]);

        $placeRes = $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'schedule_type' => 'NOW',
            'delivery_type' => 'EXPRESS',
            'payment_method' => 'CASH',
            'area' => 'Panthapath',
            'address' => 'House 19, Green Road, Panthapath',
            'phone' => '01711009988',
            'problem_note' => 'Cracked OLED glass',
        ]);

        $createdReq = RepairRequest::where('source', 'DIRECT_SERVICE')->latest('id')->first();
        $this->assertNotNull($createdReq);
        $placeRes->assertRedirect(route('requests.track', $createdReq->id));

        // Expected total: (2800 * 2 = 5600) + 5% escrow (280) + EXPRESS (150) = 6030
        $this->assertEquals(6030.0, (float) $createdReq->quoted_final_amount);
        $this->assertEquals(2, $package->fresh()->order_count);
        $this->assertDatabaseHas('carts', [
            'user_id' => $customer->id,
            'status' => 'CHECKED_OUT',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $vendorUser->id,
            'type' => 'direct_order_new',
        ]);

        // 2. Vendor sees booking in Direct Orders tab and accepts -> queues TO_SHOP DeliveryJob
        $vendorDash = $this->actingAs($vendorUser)->get('/vendor/dashboard?tab=direct_orders');
        $vendorDash->assertOk()->assertSee('Original Display Panel Replacement');

        $this->actingAs($vendorUser)
            ->post("/vendor/direct-orders/{$createdReq->id}/respond", ['decision' => 'accept'])
            ->assertRedirect();

        $this->assertEquals('ASSIGNED', $createdReq->fresh()->status);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'type' => 'direct_order_accepted',
        ]);

        $pickupJob = DeliveryJob::where('repair_request_id', $createdReq->id)
            ->where('direction', 'TO_SHOP')
            ->first();
        $this->assertNotNull($pickupJob);
        $this->assertNull($pickupJob->delivery_agent_id);

        // 3. Courier sees task in queue, claims it -> RepairRequest syncs to PICKUP_SCHEDULED
        $this->actingAs($riderUser)
            ->get('/delivery/jobs-queue')
            ->assertOk()
            ->assertSee($pickupJob->tracking_code);

        $this->actingAs($riderUser)
            ->post("/delivery/jobs/{$pickupJob->id}/claim")
            ->assertRedirect('/delivery/jobs-queue?tab=active');

        $this->assertEquals('PICKUP_SCHEDULED', $createdReq->fresh()->status);

        // 4. Customer tracker displays booked package breakdown & live courier milestone
        $this->actingAs($customer)
            ->get("/requests/{$createdReq->id}/track")
            ->assertOk()
            ->assertSee('Booked Service Packages')
            ->assertSee('Original Display Panel Replacement')
            ->assertSee($pickupJob->tracking_code)
            ->assertSee('Pickup Scheduled');
    }

    /** @test */
    public function it_calculates_exact_direct_order_total_on_sslcommerz_checkout_without_double_delivery_fee()
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $shop = RepairShop::factory()->create(['name' => 'Banani Tech Lab']);
        $package = ServicePackage::factory()->create([
            'title' => 'Type-C Port Microsoldering Fix',
            'fixed_price' => 1200,
        ]);

        $this->actingAs($customer)->postJson('/cart/add', [
            'package_id' => $package->id,
            'shop_id' => $shop->id,
            'quantity' => 1,
        ])->assertOk();

        // Subtotal: 1200, Escrow (5%): 60, EXPRESS courier: 150 -> Total = 1410.00
        $res = $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'schedule_type' => 'NOW',
            'delivery_type' => 'EXPRESS',
            'payment_method' => 'SSLCOMMERZ',
            'area' => 'Banani',
            'address' => 'Road 11, House 5, Banani',
            'phone' => '01722334455',
        ]);

        $createdReq = RepairRequest::where('source', 'DIRECT_SERVICE')->latest('id')->first();
        $this->assertNotNull($createdReq);
        $this->assertEquals(1410.0, (float) $createdReq->quoted_final_amount);
        $res->assertRedirect("/checkout/payment/{$createdReq->id}");

        $checkoutView = $this->actingAs($customer)->get("/checkout/payment/{$createdReq->id}");
        $checkoutView->assertOk()
            ->assertSee('1,200.00')
            ->assertSee('60.00')
            ->assertSee('150.00')
            ->assertSee('1,410.00')
            ->assertDontSee('1,560.00');
    }

    /** @test */
    public function it_refunds_and_notifies_customer_when_vendor_declines_direct_order()
    {
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);
        $vendorUser = User::factory()->create(['role' => 'vendor', 'is_active' => true]);
        $vendorProfile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Dhanmondi Micro Repair',
            'business_license_no' => 'TRAD/DNCC/887766',
            'nid_number' => '1993556677889',
            'verification_status' => 'approved',
            'shop_address' => 'Satmasjid Road, Dhanmondi',
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'is_setup_completed' => true,
        ]);
        $shop = RepairShop::factory()->create([
            'vendor_profile_id' => $vendorProfile->id,
            'name' => 'Dhanmondi Micro Repair',
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
        ]);
        $package = ServicePackage::factory()->create(['fixed_price' => 2000]);

        $this->actingAs($customer)->postJson('/cart/add', [
            'package_id' => $package->id,
            'shop_id' => $shop->id,
        ])->assertOk();

        $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'address' => 'Road 4, Dhanmondi',
            'phone' => '01733445566',
        ])->assertRedirect();

        $createdReq = RepairRequest::where('source', 'DIRECT_SERVICE')->latest('id')->first();
        $this->assertNotNull($createdReq);

        $this->actingAs($vendorUser)
            ->post("/vendor/direct-orders/{$createdReq->id}/respond", [
                'decision' => 'decline',
                'reason' => 'Replacement OEM part out of stock',
            ])
            ->assertRedirect();

        $this->assertEquals('REJECTED', $createdReq->fresh()->status);
        $this->assertDatabaseHas('payment_transactions', [
            'repair_request_id' => $createdReq->id,
            'status' => 'REFUNDED',
        ]);
        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $customer->id,
            'type' => 'direct_order_declined',
        ]);
    }
}

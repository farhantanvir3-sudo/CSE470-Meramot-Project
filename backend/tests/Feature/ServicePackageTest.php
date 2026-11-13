<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ServicePackage;
use App\Models\User;
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
}

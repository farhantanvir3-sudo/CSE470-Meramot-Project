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
}

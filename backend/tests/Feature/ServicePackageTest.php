<?php

namespace Tests\Feature;

use App\Models\ServicePackage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature R8: Service Package Catalog & Shopping Cart Tests
 * Assigned: Shanid Sajjatuz Islam [23201465]
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
}

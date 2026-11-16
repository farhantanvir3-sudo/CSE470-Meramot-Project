<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\RepairShop;
use App\Models\SparePartListing;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;

/**
 * Feature R6: Advanced Repair Shop Search & Multi-Parameter Filtering Tests
 * Assigned: Shanid Sajjatuz Islam [23201465]
 */
class ShopDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Seed test data
        RepairShop::factory()->create([
            'name' => 'ProTech Solutions',
            'area' => 'Banani',
            'rating' => 4.9,
            'price_level' => 2,
            'has_voucher' => true,
            'free_delivery' => false,
            'has_deals' => true,
            'latitude' => 23.7937,
            'longitude' => 90.4066,
            'specialties' => ['laptop', 'MacBook repair'],
        ]);

        RepairShop::factory()->create([
            'name' => 'Budget Fix Center',
            'area' => 'Mirpur',
            'rating' => 4.2,
            'price_level' => 1,
            'has_voucher' => false,
            'free_delivery' => true,
            'has_deals' => false,
            'latitude' => 23.8223,
            'longitude' => 90.3654,
        ]);
    }

    /** @test */
    public function it_displays_shop_explore_page()
    {
        $response = $this->get('/shops/explore');
        
        $response->assertStatus(200);
        $response->assertViewIs('shops.explore');
        $response->assertViewHas('shops');
        $response->assertSee('Explore Verified Repair Shops');
    }

    /** @test */
    public function it_filters_shops_by_text_search()
    {
        $response = $this->get('/shops/explore?q=ProTech');
        
        $response->assertStatus(200);
        $response->assertSee('ProTech Solutions');
        $response->assertDontSee('Budget Fix Center');
    }

    /** @test */
    public function it_filters_shops_by_area()
    {
        $response = $this->get('/shops/explore?area=Banani');
        
        $response->assertStatus(200);
        $response->assertSee('ProTech Solutions');
        $response->assertDontSee('Budget Fix Center');
    }

    /** @test */
    public function it_filters_shops_by_minimum_rating()
    {
        $response = $this->get('/shops/explore?min_rating=4.5');
        
        $response->assertStatus(200);
        $response->assertSee('ProTech Solutions');
        $response->assertDontSee('Budget Fix Center');
    }

    /** @test */
    public function it_filters_shops_by_promotional_toggles()
    {
        $response = $this->get('/shops/explore?has_voucher=1');
        
        $response->assertStatus(200);
        $response->assertSee('ProTech Solutions');
        $response->assertDontSee('Budget Fix Center');
    }

    /** @test */
    public function it_sorts_shops_by_price()
    {
        $response = $this->get('/shops/explore?sort=price');
        
        $response->assertStatus(200);
        $shops = $response->viewData('shops');
        
        // Budget Fix Center (price_level=1) should come before ProTech (price_level=2)
        $this->assertEquals('Budget Fix Center', $shops->first()->name);
    }

    /** @test */
    public function it_sorts_shops_by_top_rated()
    {
        $response = $this->get('/shops/explore?sort=topRated');
        
        $response->assertStatus(200);
        $shops = $response->viewData('shops');
        
        // ProTech (4.9 rating) should come before Budget Fix (4.2 rating)
        $this->assertEquals('ProTech Solutions', $shops->first()->name);
    }

    /** @test */
    public function it_sorts_shops_by_nearest_distance_and_calculates_distance_based_eta()
    {
        RepairShop::factory()->create([
            'name' => 'Far Distant Sony Rangs',
            'area' => 'Sreemangal',
            'city' => 'Sylhet',
            'latitude' => 24.3065,
            'longitude' => 91.7296,
            'eta_minutes' => 45,
        ]);

        RepairShop::factory()->create([
            'name' => 'Nearest Sony New Market',
            'area' => 'New Market',
            'city' => 'Dhaka',
            'latitude' => 23.7339,
            'longitude' => 90.3868,
            'eta_minutes' => 120,
        ]);

        // Sort by distance from New Market coordinates (even when static eta_minutes was misleadingly 120 vs 45)
        $response = $this->get('/shops/explore?q=Sony&sort=distance&lat=23.7333&lng=90.3836');
        $response->assertStatus(200);

        $shops = $response->viewData('shops');
        $this->assertEquals('Nearest Sony New Market', $shops->first()->name);
        $this->assertLessThan($shops->last()->distance_km, $shops->first()->distance_km);
        // Dynamic ETA must be calculated from Haversine distance (minimum 10 min for <2.5km, ~10+ hr for >150km)
        $this->assertEquals(10, $shops->first()->eta_minutes);
        $this->assertGreaterThan(400, $shops->last()->eta_minutes);
    }

    /** @test */
    public function it_filters_shops_by_max_distance_km_and_renders_mobile_distance_slider()
    {
        $response = $this->get('/shops?lat=23.7937&lng=90.4066&maxDistanceKm=3');

        $response->assertStatus(200);
        $response->assertSee('ProTech Solutions');
        $response->assertDontSee('Budget Fix Center');
        $response->assertSee('data-mobile-distance-slider', false);
        $response->assertSee('&le; 3 km', false);
    }

    /** @test */
    public function it_paginates_results()
    {
        // Create more shops to trigger pagination
        RepairShop::factory()->count(15)->create();
        
        $response = $this->get('/shops/explore');
        
        $response->assertStatus(200);
        $shops = $response->viewData('shops');
        
        $this->assertEquals(12, $shops->perPage());
    }

    /** @test */
    public function it_displays_shop_profile_page()
    {
        $shop = RepairShop::first();
        
        $response = $this->get('/shops/' . $shop->slug);
        
        $response->assertStatus(200);
        $response->assertViewIs('shops.show');
        $response->assertSee($shop->name);
        $response->assertSee($shop->description);
    }

    /** @test */
    public function api_returns_nearby_shops()
    {
        $response = $this->getJson('/api/shops/nearby?lat=23.7937&lng=90.4066&radius=5');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'user_location',
                     'radius_km',
                     'total_found',
                     'shops'
                 ]);
    }

    /** @test */
    public function api_search_filters_correctly()
    {
        $response = $this->getJson('/api/shops/search?min_rating=4.5&has_voucher=1');
        
        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'success',
                     'filters_applied',
                     'data'
                 ]);
        
        $shops = $response->json('data.data');
        foreach ($shops as $shop) {
            $this->assertGreaterThanOrEqual(4.5, $shop['rating']);
        }
    }

    /** @test */
    public function it_filters_shops_by_spare_parts_sale_price()
    {
        $proTech = RepairShop::where('name', 'ProTech Solutions')->first();
        $budget = RepairShop::where('name', 'Budget Fix Center')->first();

        // Create an active spare part listing on sale price for ProTech Solutions
        SparePartListing::create([
            'shop_id' => $proTech->id,
            'title' => 'ThinkPad T480 Battery',
            'product_group_key' => 'pgk-lenovo-t480-battery-test',
            'slug' => 'thinkpad-t480-battery-test-sale',
            'brand' => 'Lenovo',
            'part_category' => 'Battery',
            'base_price' => 2000,
            'normal_price' => 2500,
            'regular_price' => 2500,
            'offer_price' => 2000,
            'offer_enabled' => true,
            'is_active' => true,
            'stock_quantity' => 10,
        ]);

        // 1. Web Explore View with sale=true
        $response = $this->get('/shops/explore?sale=true');
        $response->assertStatus(200);
        $response->assertSee('ProTech Solutions');
        $response->assertDontSee('Budget Fix Center');
        $response->assertSee('Sale');

        // 2. Also with has_sale=true
        $altResponse = $this->get('/shops/explore?has_sale=true');
        $altResponse->assertStatus(200);
        $altResponse->assertSee('ProTech Solutions');
        $altResponse->assertDontSee('Budget Fix Center');

        // 3. API Search filters by sale=true
        $apiResponse = $this->getJson('/api/shops/search?sale=true');
        $apiResponse->assertStatus(200);
        $apiShops = collect($apiResponse->json('data.data'));
        $this->assertTrue($apiShops->contains('name', 'ProTech Solutions'));
        $this->assertFalse($apiShops->contains('name', 'Budget Fix Center'));
    }
}

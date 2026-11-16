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
        $response->assertSee('Active Sale');

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

    /** @test */
    public function it_filters_shops_by_active_service_deals_and_renders_deal_badges_and_strikethrough()
    {
        $proTech = RepairShop::where('name', 'ProTech Solutions')->first();
        $budget = RepairShop::where('name', 'Budget Fix Center')->first();

        // ProTech publishes a service with an active promotional deal
        \App\Models\ShopService::create([
            'shop_id' => $proTech->id,
            'slug' => 'thermal-paste-deal',
            'name' => 'Thermal Paste & Deep Cleaning',
            'short_description' => 'Component-level thermal paste replacement and fan tuning.',
            'device_type' => 'Laptop',
            'issue_category' => 'Cooling',
            'pricing_type' => 'STARTING_FROM',
            'base_price' => 500,
            'regular_price' => 800,
            'deal_enabled' => true,
            'deal_price' => 500,
            'deal_discount_pct' => 38,
            'deal_starts_at' => now()->subDays(2),
            'deal_ends_at' => now()->addDays(7),
            'is_active' => true,
        ]);

        // Budget Fix Center has a service, but NO active deal
        \App\Models\ShopService::create([
            'shop_id' => $budget->id,
            'slug' => 'budget-screen-repair',
            'name' => 'Standard Screen Repair',
            'short_description' => 'Regular screen repair service.',
            'device_type' => 'Smartphone',
            'issue_category' => 'Screen',
            'pricing_type' => 'FIXED',
            'base_price' => 1200,
            'regular_price' => null,
            'deal_enabled' => false,
            'is_active' => true,
        ]);

        $this->assertTrue($proTech->fresh()->hasActiveServiceDeals());
        $this->assertFalse($budget->fresh()->hasActiveServiceDeals());

        // 1. Explore page with deals=true filter strictly returns shops with active service deals
        $dealResp = $this->get('/shops/explore?deals=true');
        $dealResp->assertStatus(200);
        $dealResp->assertSee('ProTech Solutions');
        $dealResp->assertDontSee('Budget Fix Center');
        $dealResp->assertSee('Active Deal');
        $dealResp->assertDontSee('>Deal<', false); // No arbitrary bare 'Deal' tag

        // 2. Explore page with has_deals=true also works
        $hasDealResp = $this->get('/shops/explore?has_deals=true');
        $hasDealResp->assertStatus(200);
        $hasDealResp->assertSee('ProTech Solutions');
        $hasDealResp->assertDontSee('Budget Fix Center');

        // 3. API search with deals=true
        $apiResp = $this->getJson('/api/shops/search?deals=true');
        $apiResp->assertStatus(200);
        $apiShops = collect($apiResp->json('data.data'));
        $this->assertTrue($apiShops->contains('name', 'ProTech Solutions'));
        $this->assertFalse($apiShops->contains('name', 'Budget Fix Center'));

        // 4. Shop Profile page displays Active Deal badge in capability row AND strikethrough in services tab
        $profileResp = $this->get("/shops/{$proTech->slug}");
        $profileResp->assertStatus(200);
        $profileResp->assertSee('data-badge="active-deal"', false);
        $profileResp->assertSee('data-service-deal-badge', false);
        $profileResp->assertSee('38% OFF');
        $profileResp->assertSee('800'); // strikethrough regular price
        $profileResp->assertSee('500'); // promotional deal price
        $profileResp->assertDontSee('>Coupon Voucher<', false); // No arbitrary meaningless tag

        // 5. When deal ends, shop no longer matches deals filter
        \App\Models\ShopService::where('slug', 'thermal-paste-deal')->update([
            'deal_ends_at' => now()->subMinute(),
        ]);
        $this->assertFalse($proTech->fresh()->hasActiveServiceDeals());
        $expiredResp = $this->get('/shops/explore?deals=true');
        $expiredResp->assertStatus(200);
        $expiredResp->assertDontSee('ProTech Solutions');
    }

    public function test_shop_profile_renders_in_profile_search_and_filters_for_services_and_spare_parts(): void
    {
        $shop = RepairShop::where('name', 'ProTech Solutions')->first();

        // Add a spare part with sale
        SparePartListing::create([
            'shop_id' => $shop->id,
            'title' => 'ThinkPad T480 Battery',
            'product_group_key' => 'pgk-lenovo-t480-test-1',
            'slug' => 'thinkpad-t480-battery-profile-test',
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

        // Add a shop service with deal
        \App\Models\ShopService::create([
            'shop_id' => $shop->id,
            'slug' => 'laptop-deep-clean-deal',
            'name' => 'Laptop Thermal Paste & Deep Clean',
            'short_description' => 'Cooling overhaul with arctic thermal paste.',
            'device_type' => 'Laptop',
            'issue_category' => 'Thermal',
            'pricing_type' => 'FIXED',
            'base_price' => 500,
            'regular_price' => 800,
            'deal_enabled' => true,
            'deal_price' => 500,
            'deal_discount_pct' => 38,
            'is_active' => true,
        ]);

        // 1. Visit shop profile page
        $response = $this->get("/shops/{$shop->slug}");
        $response->assertStatus(200);

        // 2. Services tab search & filter controls are rendered
        $response->assertSee('data-service-search-input', false);
        $response->assertSee('data-service-filter-deals', false);
        $response->assertSee('All Services');
        $response->assertSee('Active Deals');

        // 3. Spare parts tab search & filter controls are rendered
        $response->assertSee('data-parts-search-input', false);
        $response->assertSee('data-parts-filter-sale', false);
        $response->assertSee('Sale Offers');
        $response->assertSee('parts-sort-select', false);
    }

    public function test_shop_spare_parts_api_supports_search_sale_category_and_sorting(): void
    {
        $shop = RepairShop::where('name', 'ProTech Solutions')->first();

        // Create parts: 1 battery on sale, 1 screen normal, 1 charger on sale
        SparePartListing::create([
            'shop_id' => $shop->id,
            'title' => 'ThinkPad T480 Battery 24Wh',
            'product_group_key' => 'pgk-t480-batt',
            'slug' => 'thinkpad-t480-batt-24wh',
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

        SparePartListing::create([
            'shop_id' => $shop->id,
            'title' => 'MacBook Air M1 Retina Display Panel',
            'product_group_key' => 'pgk-m1-display',
            'slug' => 'macbook-air-m1-display-panel',
            'brand' => 'Apple',
            'part_category' => 'Display & Touch',
            'base_price' => 12000,
            'normal_price' => 12000,
            'regular_price' => 12000,
            'offer_enabled' => false,
            'is_active' => true,
            'stock_quantity' => 5,
        ]);

        SparePartListing::create([
            'shop_id' => $shop->id,
            'title' => 'Dell 65W USB-C Fast Charger Battery Pack',
            'product_group_key' => 'pgk-dell-charger',
            'slug' => 'dell-65w-usb-c-charger',
            'brand' => 'Dell',
            'part_category' => 'Charger',
            'base_price' => 1500,
            'normal_price' => 1800,
            'regular_price' => 1800,
            'offer_price' => 1500,
            'offer_enabled' => true,
            'is_active' => true,
            'stock_quantity' => 8,
        ]);

        // 1. In-shop text search for 'battery'
        $searchResp = $this->getJson("/api/shops/{$shop->slug}/spare-parts?q=battery");
        $searchResp->assertStatus(200);
        $searchResp->assertJson(['ok' => true]);
        $this->assertEquals(2, $searchResp->json('total')); // T480 Battery and Dell Charger Battery Pack
        foreach ($searchResp->json('items') as $item) {
            $matched = stripos($item['title'], 'battery') !== false || stripos($item['url'], 'battery') !== false;
            $this->assertTrue($matched, "Item {$item['title']} should match 'battery'");
        }

        // 2. In-shop promotional sale filter
        $saleResp = $this->getJson("/api/shops/{$shop->slug}/spare-parts?sale=1");
        $saleResp->assertStatus(200);
        $saleResp->assertJson(['ok' => true]);
        $this->assertEquals(2, $saleResp->json('total')); // 2 items on sale
        foreach ($saleResp->json('items') as $item) {
            $this->assertTrue($item['is_offer'], "Item {$item['title']} must be on sale");
            $this->assertLessThanOrEqual($item['normal_price'], $item['effective_price']);
        }

        // 3. Combined search + sale filter
        $combResp = $this->getJson("/api/shops/{$shop->slug}/spare-parts?q=dell&sale=1");
        $combResp->assertStatus(200);
        $this->assertEquals(1, $combResp->json('total'));
        $this->assertEquals('Dell 65W USB-C Fast Charger Battery Pack', $combResp->json('items.0.title'));

        // 4. In-shop Category filter
        $catResp = $this->getJson("/api/shops/{$shop->slug}/spare-parts?category=" . urlencode('Display & Touch'));
        $catResp->assertStatus(200);
        $this->assertEquals(1, $catResp->json('total'));
        $this->assertEquals('MacBook Air M1 Retina Display Panel', $catResp->json('items.0.title'));

        // 5. In-shop Sorting (price_asc)
        $sortResp = $this->getJson("/api/shops/{$shop->slug}/spare-parts?sort=price_asc&limit=10");
        $sortResp->assertStatus(200);
        $items = $sortResp->json('items');
        $this->assertCount(3, $items);
        $this->assertEquals(1500, $items[0]['effective_price']); // Dell 1500
        $this->assertEquals(2000, $items[1]['effective_price']); // Lenovo 2000
        $this->assertEquals(12000, $items[2]['effective_price']); // Apple 12000
    }
}

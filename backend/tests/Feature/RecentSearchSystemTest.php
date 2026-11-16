<?php

namespace Tests\Feature;

use App\Models\RepairShop;
use App\Models\SparePartListing;
use App\Models\User;
use App\Models\UserRecentSearch;
use App\Models\VendorProfile;
use App\Services\RecentSearchService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecentSearchSystemTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_guest_has_no_fake_hardcoded_recent_searches(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertDontSee('MacBook Air M2 battery replacement');
        $response->assertDontSee('iPhone 13 display set');
        $response->assertDontSee('Sony PlayStation 5 HDMI port');
        $response->assertDontSee('iPad Pro 11-inch digitizer');
        $response->assertDontSee('data-recent-searches-section', false);
        $response->assertSee("data-recent-searches='[]'", false);
    }

    public function test_guest_search_persists_in_session_and_cookie_with_mru_dedup_and_cap(): void
    {
        $searchResp = $this->get('/search?q=ThinkPad+keyboard');
        $searchResp->assertRedirect();
        $location = (string) $searchResp->headers->get('Location');
        $this->assertStringContainsString('/spare-parts', $location);
        $this->assertStringContainsString('src=smart', $location);
        $searchResp->assertCookie(RecentSearchService::COOKIE_NAME);

        $homeResp = $this->get('/');
        $homeResp->assertOk();
        $homeResp->assertSee('data-recent-searches-section', false);
        $homeResp->assertSee('ThinkPad keyboard');
        $homeResp->assertSee('/search?q=ThinkPad%20keyboard', false);

        $queries = [
            'Galaxy S22 battery',
            'HP EliteBook charger',
            'Dell Latitude cooling fan',
            'MacBook Pro Type-C port',
            'Xiaomi LCD display',
            'Asus VivoBook hinge',
            'Pixel 7 screen replacement',
            'Lenovo IdeaPad keyboard',
            'thinkpad keyboard',
        ];
        foreach ($queries as $q) {
            $this->get('/search?q=' . urlencode($q))->assertRedirect();
        }

        $recent = $this->getJson('/api/search/recent')->assertOk()->json('recent_searches');
        $this->assertCount(RecentSearchService::MAX_ITEMS, $recent);
        $this->assertSame('thinkpad keyboard', $recent[0]['label']);
        $labels = array_column($recent, 'label');
        $this->assertNotContains('Galaxy S22 battery', $labels);
    }

    public function test_guest_cookie_survives_session_flush(): void
    {
        $res = $this->get('/search?q=MacBook+M2+battery');
        $res->assertRedirect();
        $res->assertCookie(RecentSearchService::COOKIE_NAME);

        $cookie = collect($res->headers->getCookies())
            ->first(fn ($c) => $c->getName() === RecentSearchService::COOKIE_NAME);
        $this->assertNotNull($cookie);

        $this->flushSession();

        $freshResp = $this->call(
            'GET',
            '/api/search/recent',
            [],
            [RecentSearchService::COOKIE_NAME => $cookie->getValue()],
            [],
            ['HTTP_ACCEPT' => 'application/json']
        );

        $freshResp->assertOk();
        $recent = $freshResp->json('recent_searches');
        $this->assertCount(1, $recent);
        $this->assertSame('MacBook M2 battery', $recent[0]['label']);
    }

    public function test_demo_customer_gets_reserved_realistic_defaults_and_thinkpad_routes_to_spare_parts(): void
    {
        $demoCustomer = User::where('email', RecentSearchService::DEMO_CUSTOMER_EMAIL)->first();
        if (!$demoCustomer) {
            $demoCustomer = User::factory()->create([
                'name' => 'Customer Demo User',
                'email' => RecentSearchService::DEMO_CUSTOMER_EMAIL,
                'role' => 'customer',
            ]);
        } else {
            UserRecentSearch::where('user_id', $demoCustomer->id)->delete();
        }

        $vendorUser = User::factory()->create([
            'role' => 'vendor',
            'city' => 'Dhaka',
            'area' => 'Banani',
        ]);
        $profile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'ThinkParts BD',
            'brand_name' => 'ThinkParts BD',
            'owner_name' => $vendorUser->name,
            'business_email' => $vendorUser->email,
            'phone' => $vendorUser->phone,
            'city' => 'Dhaka',
            'area' => 'Banani',
            'shop_address' => 'Road 11, Banani, Dhaka',
            'verification_status' => 'approved',
            'spare_parts' => true,
        ]);
        $shop = RepairShop::create([
            'vendor_profile_id' => $profile->id,
            'name' => 'ThinkParts BD',
            'slug' => 'thinkparts-bd-' . uniqid(),
            'city' => 'Dhaka',
            'area' => 'Banani',
            'address' => 'Road 11, Banani, Dhaka',
            'latitude' => 23.7937,
            'longitude' => 90.4066,
            'categories' => ['SPARE_PARTS', 'IN_SHOP_REPAIR'],
            'rating' => 4.8,
            'review_count' => 10,
            'inspection_fee' => 250,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'is_active' => true,
            'is_public' => true,
        ]);
        SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'lenovo-thinkpad-e15-keyboard-' . uniqid(),
            'product_group_key' => 'lenovo-thinkpad-e15-keyboard',
            'sku' => 'SKU-TP-E15-' . uniqid(),
            'title' => 'Lenovo ThinkPad E15 Laptop Keyboard',
            'part_category' => 'Keyboard & Touchpad',
            'brand' => 'Lenovo',
            'base_price' => 2400,
            'stock_quantity' => 5,
            'is_active' => true,
        ]);

        $response = $this->get('/auth/demo?role=customer');
        $response->assertRedirect();
        $this->assertAuthenticated();
        $this->assertSame(RecentSearchService::DEMO_CUSTOMER_EMAIL, auth()->user()->email);

        $home = $this->get('/');
        $home->assertOk();
        $home->assertSee('data-recent-searches-section', false);
        $home->assertSee('ThinkPad keyboard repair');
        $home->assertSee('/search?q=ThinkPad%20keyboard%20repair', false);

        // Clicking ThinkPad keyboard repair routes to spare-parts (ThinkPad keyboards), NOT /shops
        $clickResp = $this->get('/search?q=ThinkPad%20keyboard%20repair');
        $clickResp->assertRedirect();
        $dest = (string) $clickResp->headers->get('Location');
        $this->assertStringContainsString('/spare-parts', $dest);
        $this->assertStringNotContainsString('/shops', parse_url($dest, PHP_URL_PATH) ?: '');

        $partsPage = $this->get($dest);
        $partsPage->assertOk();
        $partsPage->assertSee('Lenovo ThinkPad E15 Laptop Keyboard');

        // Logging out must NOT leak Demo Customer's searches to guest sessions
        $this->post('/logout')->assertRedirect();
        $guestHome = $this->get('/');
        $guestHome->assertOk();
        $guestHome->assertDontSee('data-recent-searches-section', false);
    }

    public function test_newly_created_user_starts_empty_and_persists_db_searches_isolated_by_user(): void
    {
        // Guest records a search before registering
        $this->get('/search?q=iPhone+14+glass')->assertRedirect();

        $userA = User::factory()->create(['role' => 'customer']);
        $userB = User::factory()->create(['role' => 'customer']);

        // Newly created user A must NOT inherit guest cookie or demo customer defaults
        $this->actingAs($userA);
        $homeA = $this->get('/');
        $homeA->assertOk();
        $homeA->assertDontSee('data-recent-searches-section', false);
        $homeA->assertDontSee('iPhone 14 glass');

        // User A searches -> persisted in DB under user_id A
        $this->get('/search?q=HP+Pavilion+keyboard')->assertRedirect();
        $this->assertDatabaseHas('user_recent_searches', [
            'user_id' => $userA->id,
            'normalized_query' => 'hp pavilion keyboard',
        ]);

        // Prefetch & page=2 must NOT record
        $this->withHeaders(['X-Meramot-Prefetch' => '1'])
            ->get('/search?q=Prefetch+Noise')
            ->assertRedirect();
        $this->get('/spare-parts?q=PageTwo+Noise&page=2')->assertOk();

        $this->assertDatabaseMissing('user_recent_searches', [
            'user_id' => $userA->id,
            'normalized_query' => 'prefetch noise',
        ]);
        $this->assertDatabaseMissing('user_recent_searches', [
            'user_id' => $userA->id,
            'normalized_query' => 'pagetwo noise',
        ]);

        // User B remains isolated
        $this->actingAs($userB);
        $homeB = $this->get('/');
        $homeB->assertOk();
        $homeB->assertDontSee('data-recent-searches-section', false);
        $homeB->assertDontSee('HP Pavilion keyboard');
        $this->assertSame([], $this->getJson('/api/search/recent')->json('recent_searches'));
    }

    public function test_single_delete_and_clear_all_work_for_guest_and_authenticated_user(): void
    {
        // Guest flow
        $this->get('/search?q=ThinkPad+keyboard')->assertRedirect();
        $this->get('/search?q=Thermal+repasting')->assertRedirect();

        $delResp = $this->deleteJson('/recent-searches/item', ['q' => 'ThinkPad keyboard']);
        $delResp->assertOk();
        $labels = array_column($delResp->json('recent_searches'), 'label');
        $this->assertSame(['Thermal repasting'], $labels);

        $this->deleteJson('/recent-searches')->assertOk()->assertExactJson([
            'status' => 'ok',
            'recent_searches' => [],
        ]);
        $this->get('/')->assertOk()->assertDontSee('data-recent-searches-section', false);

        // Logged-in DB flow
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user);
        $this->get('/search?q=Dell+XPS+battery')->assertRedirect();
        $this->get('/search?q=iPhone+13+display')->assertRedirect();

        $this->deleteJson('/recent-searches/item', ['q' => 'Dell XPS battery'])
            ->assertOk();
        $this->assertDatabaseMissing('user_recent_searches', [
            'user_id' => $user->id,
            'normalized_query' => 'dell xps battery',
        ]);
        $this->assertSame(1, UserRecentSearch::where('user_id', $user->id)->count());

        $this->deleteJson('/recent-searches')->assertOk();
        $this->assertSame(0, UserRecentSearch::where('user_id', $user->id)->count());
    }
}

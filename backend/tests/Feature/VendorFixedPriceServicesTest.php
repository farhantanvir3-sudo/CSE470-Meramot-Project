<?php

namespace Tests\Feature;

use App\Models\RepairShop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Vendor-defined fixed-price services & packages:
 *  - the list starts empty for a new vendor (nothing auto-generated from onboarding tags),
 *  - suggestions are derived from the specialty tags chosen during onboarding,
 *  - only vendor-published services appear on the public storefront.
 */
class VendorFixedPriceServicesTest extends TestCase
{
    use RefreshDatabase;

    private function makeApprovedVendorWithUnsetShop(): array
    {
        $vendor = User::factory()->create(['role' => 'vendor']);
        $profile = VendorProfile::create([
            'user_id' => $vendor->id,
            'shop_name' => 'Fresh Fix Studio',
            'brand_name' => 'Fresh Fix Studio',
            'business_license_no' => 'TRAD/DNCC/778899',
            'nid_number' => '1995112233445',
            'verification_status' => 'approved',
            'shop_address' => 'House 9, Road 2, Dhanmondi, Dhaka',
            'gps_address' => 'Dhanmondi 2, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'shop_latitude' => 23.7400,
            'shop_longitude' => 90.3800,
            'categories' => ['Smartphone Repair', 'Laptop Repair'],
            'specialties' => ['Smartphone Repair', 'Laptop Repair'],
        ]);

        $shop = RepairShop::factory()->create([
            'vendor_profile_id' => $profile->id,
            'name' => 'Fresh Fix Studio',
            'slug' => 'fresh-fix-studio',
            'is_active' => true,
            'is_public' => false,
            'setup_complete' => false,
        ]);

        return [$vendor, $profile, $shop];
    }

    private function setupPayload(array $services = []): array
    {
        return array_filter([
            'shop_name' => 'Fresh Fix Studio',
            'phone' => '01711223344',
            'description' => 'Smartphone and laptop repair studio.',
            'address' => 'House 9, Road 2, Dhanmondi, Dhaka',
            'gps_address' => 'Dhanmondi 2, Dhaka',
            'same_as_gps_location' => '0',
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'latitude' => 23.7400,
            'longitude' => 90.3800,
            'courier_pickup' => '1',
            'in_shop_repair' => '1',
            'inspection_fee' => 300,
            'base_labor_fee' => 500,
            'skill_tags' => $services ?: null,
        ], fn ($v) => $v !== null);
    }

    /** @test */
    public function new_vendor_starts_with_an_empty_service_list_and_tag_based_suggestions()
    {
        [$vendor, $profile, $shop] = $this->makeApprovedVendorWithUnsetShop();

        $response = $this->actingAs($vendor)->get('/vendor/setup-shop');
        $response->assertOk()
            ->assertSee('Fixed-price services & packages')
            ->assertSee('Suggested for your specialties')
            ->assertSee('No fixed-price services yet')
            ->assertViewHas('onboardingTags', ['Smartphone Repair', 'Laptop Repair'])
            ->assertViewHas('serviceSuggestions', function (array $suggestions) {
                $names = array_column($suggestions, 'name');
                return in_array('Smartphone Screen Replacement', $names, true)
                    && in_array('Laptop Battery Replacement', $names, true)
                    && !in_array('Camera Sensor Cleaning', $names, true);
            });

        // The Alpine service list is rendered empty — nothing is pre-filled from onboarding tags.
        $this->assertMatchesRegularExpression(
            "/services:\s*(\[\]|JSON\.parse\('\[\]'\))/",
            $response->getContent()
        );

        // Shop can go live without any fixed-price services.
        $this->actingAs($vendor)->post('/vendor/setup-shop', $this->setupPayload())
            ->assertRedirect('/vendor/dashboard');

        $shop->refresh();
        $profile->refresh();
        $this->assertTrue((bool) $shop->is_public);
        $this->assertSame(0, ShopService::where('shop_id', $shop->id)->where('is_active', true)->count());
        $this->assertEquals(['Smartphone Repair', 'Laptop Repair'], $profile->specialties);
        $this->assertEquals(['Smartphone Repair', 'Laptop Repair'], $shop->specialties);

        // Storefront shows an honest empty state instead of fabricated services/prices.
        $this->get('/shops/fresh-fix-studio')
            ->assertOk()
            ->assertSee("This workshop hasn't published fixed-price services yet.", false)
            ->assertDontSee('Smartphone Repair</h3>', false)
            ->assertDontSee('৳1,500');
    }

    /** @test */
    public function vendor_publishes_edits_and_removes_fixed_price_services()
    {
        [$vendor, $profile, $shop] = $this->makeApprovedVendorWithUnsetShop();

        $this->actingAs($vendor)->post('/vendor/setup-shop', $this->setupPayload([
            'Smartphone Screen Replacement|2400|Genuine panel, 90-day warranty',
            'Laptop Battery Replacement|3600',
        ]))->assertRedirect('/vendor/dashboard');

        $screen = ShopService::where('shop_id', $shop->id)->where('name', 'Smartphone Screen Replacement')->firstOrFail();
        $this->assertSame('FIXED', $screen->pricing_type);
        $this->assertEquals(2400.0, (float) $screen->base_price);
        $this->assertSame('Genuine panel, 90-day warranty', $screen->short_description);
        $this->assertTrue((bool) $screen->is_active);

        $shop->refresh();
        $this->assertEquals(
            ['Smartphone Repair', 'Laptop Repair', 'Smartphone Screen Replacement', 'Laptop Battery Replacement'],
            $shop->specialties
        );

        $this->get('/shops/fresh-fix-studio')
            ->assertOk()
            ->assertSee('Smartphone Screen Replacement')
            ->assertSee('৳2,400')
            ->assertSee('Genuine panel, 90-day warranty')
            ->assertSee('fixed price');

        // Edit the price and remove the battery service.
        $this->actingAs($vendor)->post('/vendor/setup-shop', $this->setupPayload([
            'Smartphone Screen Replacement|2200',
        ]))->assertRedirect('/vendor/dashboard');

        $this->assertEquals(2200.0, (float) $screen->fresh()->base_price);
        $battery = ShopService::where('shop_id', $shop->id)->where('name', 'Laptop Battery Replacement')->firstOrFail();
        $this->assertFalse((bool) $battery->is_active);

        // Edit page is pre-filled only with the vendor's active published services.
        $this->actingAs($vendor)->get('/vendor/setup-shop')
            ->assertOk()
            ->assertSee('Smartphone Screen Replacement')
            ->assertViewHas('primaryShop', fn ($s) => $s->services->pluck('name')->all() === ['Smartphone Screen Replacement']);

        $this->get('/shops/fresh-fix-studio')
            ->assertOk()
            ->assertSee('৳2,200')
            ->assertDontSee('৳3,600');
    }
}

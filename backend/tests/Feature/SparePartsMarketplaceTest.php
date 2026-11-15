<?php

namespace Tests\Feature;

use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\ServicePackage;
use App\Models\SparePartListing;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SparePartsMarketplaceTest extends TestCase
{
    use RefreshDatabase;

    private function createApprovedVendorWithShop(array $shopOverrides = [], string $brandName = 'TechParts BD'): array
    {
        $vendorUser = User::factory()->create([
            'role' => 'vendor',
            'phone' => '017' . rand(10000000, 99999999),
            'city' => $shopOverrides['city'] ?? 'Dhaka',
            'area' => $shopOverrides['area'] ?? 'Banani',
        ]);

        $profile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'shop_name' => $brandName,
            'brand_name' => $brandName,
            'owner_name' => $vendorUser->name,
            'business_email' => $vendorUser->email,
            'phone' => $vendorUser->phone,
            'city' => $shopOverrides['city'] ?? 'Dhaka',
            'area' => $shopOverrides['area'] ?? 'Banani',
            'shop_address' => ($shopOverrides['area'] ?? 'Banani') . ', ' . ($shopOverrides['city'] ?? 'Dhaka'),
            'verification_status' => 'approved',
            'spare_parts' => true,
            'is_branched' => !empty($shopOverrides['branch_name']),
        ]);

        $shop = RepairShop::create(array_merge([
            'vendor_profile_id' => $profile->id,
            'name' => $brandName,
            'branch_name' => 'Main Branch',
            'slug' => strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $brandName)) . '-' . uniqid(),
            'city' => 'Dhaka',
            'area' => 'Banani',
            'address' => 'Road 11, Banani, Dhaka',
            'latitude' => 23.7937,
            'longitude' => 90.4066,
            'categories' => ['SPARE_PARTS', 'IN_SHOP_REPAIR'],
            'rating' => 4.9,
            'review_count' => 42,
            'inspection_fee' => 300,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'is_active' => true,
            'is_public' => true,
        ], $shopOverrides));

        return [$vendorUser, $profile, $shop];
    }

    /** @test */
    public function spare_parts_marketplace_displays_items_and_redirects_legacy_shops_category_filter(): void
    {
        [, $profile, $shop] = $this->createApprovedVendorWithShop([
            'branch_name' => 'Banani Flagship',
        ], 'Etel Parts Hub');

        $part = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'asus-vivobook-x515-speaker--etel',
            'product_group_key' => 'asus-vivobook-x515-speaker',
            'sku' => 'SP-X515-SPK',
            'title' => 'ASUS VivoBook 15 X515EA Laptop Speaker Pair',
            'part_category' => 'Laptop Speaker',
            'brand' => 'Asus',
            'compatible_model' => 'VivoBook 15 X515',
            'condition_label' => 'Brand New',
            'image_url' => '/images/spare-parts/samsung-galaxy-s21-fe-5g-display.webp',
            'has_specs' => false,
            'base_price' => 1500,
            'normal_price' => 1500,
            'offer_enabled' => true,
            'offer_price' => 1250,
            'offer_discount_pct' => 16.67,
            'offer_starts_at' => now()->subDay(),
            'offer_ends_at' => null,
            'stock_quantity' => 80,
            'sold_count' => 12,
            'wholesale_enabled' => true,
            'wholesale_min_qty' => 100,
            'wholesale_base_discount_pct' => 5,
            'wholesale_step_qty' => 100,
            'wholesale_step_discount_pct' => 2,
            'wholesale_max_discount_pct' => 25,
            'is_active' => true,
        ]);

        // Legacy /shops?category=SPARE_PARTS redirects cleanly to dedicated /spare-parts marketplace
        $legacyResp = $this->get('/shops?category=SPARE_PARTS&q=VivoBook&city=Dhaka');
        $legacyResp->assertRedirect();
        $this->assertStringContainsString('/spare-parts', $legacyResp->headers->get('Location'));
        $this->assertStringContainsString('q=VivoBook', $legacyResp->headers->get('Location'));

        // Dedicated /spare-parts lists items (NOT shops), circular red Sale! badge, and seller attachment
        $resp = $this->get('/spare-parts?device_type=pc');
        $resp->assertStatus(200);
        $resp->assertViewIs('spare-parts.index');
        $resp->assertSee('ASUS VivoBook 15 X515EA Laptop Speaker Pair');
        $resp->assertSee('Sale!');
        $resp->assertSee('1,250');
        $resp->assertSee('1,500');
        $resp->assertSee('Etel Parts Hub');

        // Detail page renders Sale!, Total sold, seller card & wholesale tiers
        $detailResp = $this->get('/spare-parts/' . $part->slug);
        $detailResp->assertStatus(200);
        $detailResp->assertViewIs('spare-parts.show');
        $detailResp->assertSee('Sale!');
        $detailResp->assertSee('Total sold - 12');
        $detailResp->assertSee('1,250.00');
    }

    /** @test */
    public function location_based_best_deal_flips_winner_between_dhaka_and_chattogram_based_on_part_plus_courier_cost(): void
    {
        [, $dhakaProfile, $dhakaShop] = $this->createApprovedVendorWithShop([
            'city' => 'Dhaka',
            'area' => 'Banani',
            'latitude' => 23.7937,
            'longitude' => 90.4066,
            'branch_name' => 'Banani Branch',
        ], 'Dhaka Parts Center');

        [, $ctgProfile, $ctgShop] = $this->createApprovedVendorWithShop([
            'city' => 'Chattogram',
            'area' => 'Agrabad',
            'latitude' => 22.3264,
            'longitude' => 91.8155,
            'branch_name' => 'Agrabad Branch',
        ], 'Chattogram Parts Depot');

        // Dhaka seller has higher part price (৳1,749), Chattogram seller has cheaper raw part price (৳1,650)
        $dhakaPart = SparePartListing::create([
            'shop_id' => $dhakaShop->id,
            'vendor_profile_id' => $dhakaProfile->id,
            'slug' => 's21-fe-display--dhaka',
            'product_group_key' => 's21-fe-display-canonical',
            'title' => 'Samsung Galaxy S21 FE 5G Display',
            'part_category' => 'Display & Touch',
            'brand' => 'Samsung',
            'base_price' => 1749,
            'normal_price' => 1749,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $ctgPart = SparePartListing::create([
            'shop_id' => $ctgShop->id,
            'vendor_profile_id' => $ctgProfile->id,
            'slug' => 's21-fe-display--ctg',
            'product_group_key' => 's21-fe-display-canonical',
            'title' => 'Samsung Galaxy S21 FE 5G Display',
            'part_category' => 'Display & Touch',
            'brand' => 'Samsung',
            'base_price' => 1650,
            'normal_price' => 1650,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        // 1. Customer in Banani, Dhaka: Dhaka = 1749 + 50 = 1799 < Chattogram = 1650 + 220 = 1870
        $dhakaView = $this->get('/spare-parts?group=s21-fe-display-canonical&city=Dhaka&area=Banani&lat=23.7937&lng=90.4066');
        $dhakaView->assertStatus(200);
        $dhakaItems = $dhakaView->viewData('listings')->getCollection()->keyBy('id');

        $this->assertTrue($dhakaItems[$dhakaPart->id]->is_best_deal, 'Dhaka seller should win Best Deal in Dhaka once delivery fee is added.');
        $this->assertFalse($dhakaItems[$ctgPart->id]->is_best_deal);
        $this->assertTrue($dhakaItems[$ctgPart->id]->is_cheapest_raw_but_far);

        // 2. Customer switches to Agrabad, Chattogram: Chattogram = 1650 + 50 = 1700 < Dhaka = 1749 + 220 = 1969
        $ctgView = $this->get('/spare-parts?group=s21-fe-display-canonical&city=Chattogram&area=Agrabad&lat=22.3264&lng=91.8155');
        $ctgView->assertStatus(200);
        $ctgItems = $ctgView->viewData('listings')->getCollection()->keyBy('id');

        $this->assertTrue($ctgItems[$ctgPart->id]->is_best_deal, 'Chattogram seller should win Best Deal when customer is in Chattogram.');
        $this->assertFalse($ctgItems[$dhakaPart->id]->is_best_deal);
    }

    /** @test */
    public function spec_options_enforce_selection_and_apply_exact_spec_grade_prices(): void
    {
        [, $profile, $shop] = $this->createApprovedVendorWithShop([], 'Spec Master Parts');
        $customer = User::factory()->create(['role' => 'customer']);

        $part = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 's21-fe-multi-spec--spec-master',
            'product_group_key' => 's21-fe-multi-spec',
            'title' => 'Samsung Galaxy S21 FE 5G Display',
            'part_category' => 'Display & Touch',
            'brand' => 'Samsung',
            'has_specs' => true,
            'spec_label' => 'Quality',
            'spec_options' => [
                ['code' => 'ips', 'name' => 'IPS', 'subtitle' => 'LCD Replacement', 'price' => 1749, 'stock' => 40],
                ['code' => 'oled', 'name' => 'OLED High Quality', 'subtitle' => '120Hz Smooth', 'price' => 5399, 'stock' => 25],
                ['code' => 'original-sp', 'name' => '100% Original Service Pack', 'subtitle' => 'Genuine', 'price' => 11099, 'stock' => 10],
            ],
            'base_price' => 1749,
            'normal_price' => 1749,
            'max_price' => 11099,
            'normal_max_price' => 11099,
            'stock_quantity' => 75,
            'is_active' => true,
        ]);

        // Missing spec_code must be rejected with 422
        $missingResp = $this->actingAs($customer)->postJson('/cart/add', [
            'spare_part_id' => $part->id,
            'quantity' => 1,
        ]);
        $missingResp->assertStatus(422);

        // Selecting IPS -> ৳1,749
        $ipsResp = $this->actingAs($customer)->postJson('/cart/add', [
            'spare_part_id' => $part->id,
            'spec_code' => 'ips',
            'quantity' => 1,
        ]);
        $ipsResp->assertStatus(200)->assertJsonPath('item.price', 1749);

        // Selecting 100% Original Service Pack -> ৳11,099
        $origResp = $this->actingAs($customer)->postJson('/cart/add', [
            'spare_part_id' => $part->id,
            'spec_code' => 'original-sp',
            'quantity' => 1,
        ]);
        $origResp->assertStatus(200)->assertJsonPath('item.price', 11099);
    }

    /** @test */
    public function promotional_offer_price_supports_fixed_date_range_indefinite_null_end_and_automatic_expiry(): void
    {
        [, $profile, $shop] = $this->createApprovedVendorWithShop([], 'LaptopWorld BD');
        $customer = User::factory()->create(['role' => 'customer']);

        // 1. Indefinite Offer (`offer_ends_at = null`)
        $indefinitePart = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'dell-4gvmp-battery--indefinite',
            'product_group_key' => 'dell-4gvmp-battery',
            'title' => 'Dell 4GVMP 3HWPP Battery for Latitude',
            'part_category' => 'Laptop Battery',
            'brand' => 'Dell',
            'base_price' => 3000,
            'normal_price' => 3000,
            'offer_enabled' => true,
            'offer_price' => 2850,
            'offer_starts_at' => Carbon::now()->subDays(3),
            'offer_ends_at' => null, // Indefinite!
            'stock_quantity' => 100,
            'is_active' => true,
        ]);

        $this->assertTrue($indefinitePart->isOfferActive());
        $this->assertSame(2850.0, $indefinitePart->effectiveBasePrice());
        $this->assertSame(3000.0, $indefinitePart->resolvedNormalBasePrice());

        $addIndef = $this->actingAs($customer)->postJson('/cart/add', [
            'spare_part_id' => $indefinitePart->id,
            'quantity' => 1,
        ]);
        $addIndef->assertStatus(200)
            ->assertJsonPath('item.price', 2850)
            ->assertJsonPath('item.normal_unit_price', 3000)
            ->assertJsonPath('item.has_active_offer', true);

        // 2. Expired Offer (`offer_ends_at` in the past) -> reverts automatically to normal_price (৳3,000)
        $indefinitePart->update([
            'offer_starts_at' => Carbon::now()->subDays(10),
            'offer_ends_at' => Carbon::now()->subMinute(),
        ]);
        $indefinitePart->refresh();

        $this->assertFalse($indefinitePart->isOfferActive());
        $this->assertSame(3000.0, $indefinitePart->effectiveBasePrice());

        // 3. Future Scheduled Offer (`offer_starts_at` in the future) -> normal_price until start date
        $indefinitePart->update([
            'offer_starts_at' => Carbon::now()->addDays(2),
            'offer_ends_at' => Carbon::now()->addDays(7),
        ]);
        $indefinitePart->refresh();

        $this->assertFalse($indefinitePart->isOfferActive());
        $this->assertSame(3000.0, $indefinitePart->effectiveBasePrice());
    }

    /** @test */
    public function aliexpress_progressive_wholesale_tiers_apply_across_cart_add_update_and_checkout(): void
    {
        [, $profile, $shop] = $this->createApprovedVendorWithShop([], 'Wholesale Cell Parts');
        $customer = User::factory()->create([
            'role' => 'customer',
            'phone' => '01711223344',
            'address' => 'House 12, Road 11, Banani, Dhaka',
            'area' => 'Banani',
            'city' => 'Dhaka',
        ]);

        // ৳1,000 normal, 5% at 100+, +2% per extra 100, capped at 25%
        $part = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'charging-flex-bulk--wholesale',
            'product_group_key' => 'charging-flex-bulk',
            'title' => 'iPhone 13 Charging Port Flex Ribbon',
            'part_category' => 'Charging Port & Flex',
            'brand' => 'Apple',
            'base_price' => 1000,
            'normal_price' => 1000,
            'stock_quantity' => 1000,
            'wholesale_enabled' => true,
            'wholesale_min_qty' => 100,
            'wholesale_base_discount_pct' => 5.0,
            'wholesale_step_qty' => 100,
            'wholesale_step_discount_pct' => 2.0,
            'wholesale_max_discount_pct' => 25.0,
            'is_active' => true,
        ]);

        $this->assertSame(0.0, $part->calculateWholesaleDiscountPct(99));
        $this->assertSame(5.0, $part->calculateWholesaleDiscountPct(100));
        $this->assertSame(7.0, $part->calculateWholesaleDiscountPct(200));
        $this->assertSame(9.0, $part->calculateWholesaleDiscountPct(350));

        // Start with 10 pcs -> 0% discount (৳1,000/pc)
        $addResp = $this->actingAs($customer)->postJson('/cart/add', [
            'spare_part_id' => $part->id,
            'quantity' => 10,
        ]);
        $addResp->assertStatus(200)->assertJsonPath('item.price', 1000);

        $itemKey = $addResp->json('item.id');
        $this->assertSame('sp_' . $part->id, $itemKey);

        // Update cart to 100 pcs -> 5% discount (৳950/pc)
        $upd100 = $this->actingAs($customer)->patchJson('/cart/update/' . urlencode($itemKey), [
            'quantity' => 100,
        ]);
        $upd100->assertStatus(200)
            ->assertJsonPath('item.price', 950)
            ->assertJsonPath('item.wholesale_discount_pct', 5);

        // Update cart to 250 pcs -> 7% discount (৳930/pc)
        $upd250 = $this->actingAs($customer)->patchJson('/cart/update/' . urlencode($itemKey), [
            'quantity' => 250,
        ]);
        $upd250->assertStatus(200)
            ->assertJsonPath('item.price', 930)
            ->assertJsonPath('item.wholesale_discount_pct', 7);

        // Checkout & place order -> stock decrements by 250 & sold_count increments by 250
        $placeResp = $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'phone' => '01711223344',
            'area' => 'Banani',
            'address' => 'House 12, Road 11, Banani, Dhaka',
        ]);
        $placeResp->assertRedirect();

        $part->refresh();
        $this->assertSame(750, $part->stock_quantity);
        $this->assertSame(250, $part->sold_count);
    }

    /** @test */
    public function vendor_cannot_order_repair_services_or_own_parts_but_can_order_spare_parts_from_other_vendors(): void
    {
        [$vendorA, $profileA, $shopA] = $this->createApprovedVendorWithShop([
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
        ], 'Alpha Repair Lab');

        [$vendorB, $profileB, $shopB] = $this->createApprovedVendorWithShop([
            'city' => 'Dhaka',
            'area' => 'Banani',
        ], 'Beta Parts Distributor');

        $servicePackage = ServicePackage::factory()->create(['is_active' => true]);

        $ownPartA = SparePartListing::create([
            'shop_id' => $shopA->id,
            'vendor_profile_id' => $profileA->id,
            'slug' => 'own-battery--alpha',
            'product_group_key' => 'own-battery',
            'title' => 'Alpha Own Shop Battery',
            'part_category' => 'Battery',
            'brand' => 'Samsung',
            'base_price' => 1200,
            'normal_price' => 1200,
            'stock_quantity' => 50,
            'is_active' => true,
        ]);

        $otherVendorPartB = SparePartListing::create([
            'shop_id' => $shopB->id,
            'vendor_profile_id' => $profileB->id,
            'slug' => 'vivobook-fan--beta',
            'product_group_key' => 'vivobook-fan',
            'title' => 'ASUS VivoBook CPU Cooling Fan',
            'part_category' => 'Laptop Cooling Fan',
            'brand' => 'Asus',
            'base_price' => 1000,
            'normal_price' => 1000,
            'stock_quantity' => 300,
            'wholesale_enabled' => true,
            'wholesale_min_qty' => 100,
            'wholesale_base_discount_pct' => 5,
            'wholesale_step_qty' => 100,
            'wholesale_step_discount_pct' => 2,
            'wholesale_max_discount_pct' => 25,
            'is_active' => true,
        ]);

        // 1. Vendor A CANNOT order repair service packages from other vendors (403)
        $this->actingAs($vendorA)->postJson('/cart/add', [
            'package_id' => $servicePackage->id,
            'quantity' => 1,
        ])->assertStatus(403);

        // 2. Vendor A CANNOT order custom shop repair services from Vendor B (403)
        $this->actingAs($vendorA)->postJson('/cart/add', [
            'shop_id' => $shopB->id,
            'service_name' => 'Motherboard BGA Reballing',
            'price' => 3500,
            'quantity' => 1,
        ])->assertStatus(403);

        // 3. Vendor A CANNOT order spare parts from their OWN workshop (422)
        $this->actingAs($vendorA)->postJson('/cart/add', [
            'spare_part_id' => $ownPartA->id,
            'quantity' => 2,
        ])->assertStatus(422);

        // 4. Vendor A CAN order spare parts (including 100+ wholesale!) from Vendor B!
        $addOther = $this->actingAs($vendorA)->postJson('/cart/add', [
            'spare_part_id' => $otherVendorPartB->id,
            'quantity' => 120,
        ]);
        $addOther->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('item.price', 950); // 5% wholesale off ৳1,000

        // Vendor A can view /cart, /cart/checkout, place the spare parts order, and view /orders
        $this->actingAs($vendorA)->get('/cart')->assertStatus(200)->assertSee('ASUS VivoBook CPU Cooling Fan');
        $this->actingAs($vendorA)->get('/cart/checkout')->assertStatus(200);

        $place = $this->actingAs($vendorA)->post('/cart/checkout/place', [
            'shop_id' => $shopB->id,
            'schedule_type' => 'NOW',
            'delivery_type' => 'REGULAR',
            'payment_method' => 'CASH',
            'phone' => '01755667788',
            'area' => 'Dhanmondi',
            'address' => 'Road 27, Dhanmondi, Dhaka',
        ]);
        $place->assertRedirect();

        $createdOrder = RepairRequest::where('customer_id', $vendorA->id)
            ->where('requested_shop_id', $shopB->id)
            ->first();
        $this->assertNotNull($createdOrder, 'Vendor A should have a saved spare-part order placed with Vendor B.');

        $ordersPage = $this->actingAs($vendorA)->get('/orders');
        $ordersPage->assertStatus(200)->assertSee('ASUS VivoBook CPU Cooling Fan');
    }

    /** @test */
    public function vendor_dashboard_spare_parts_crud_supports_specs_indefinite_sale_offers_and_wholesale(): void
    {
        [$vendor, , $shop] = $this->createApprovedVendorWithShop([
            'branch_name' => 'Uttara Branch',
        ], 'Prime Laptop Spares');

        // 1. Create single-price listing with indefinite Sale! offer (`offer_ends_at` empty)
        $createResp = $this->actingAs($vendor)->post('/vendor/spare-parts', [
            'shop_id' => $shop->id,
            'title' => 'HP Pavilion 15 HT03XL Original Battery',
            'part_category' => 'Laptop Battery',
            'brand' => 'HP',
            'compatible_model' => 'HP Pavilion 15-CS / 15-DA',
            'condition_label' => 'Original',
            'base_price' => 3200,
            'stock_quantity' => 150,
            'handling_hours' => 2,
            'warranty_days' => 180,
            'offer_enabled' => '1',
            'offer_price' => 2790,
            'offer_starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'offer_ends_at' => '', // Indefinite offer!
            'wholesale_enabled' => '1',
            'wholesale_min_qty' => 100,
            'wholesale_base_discount_pct' => 5,
            'wholesale_step_qty' => 100,
            'wholesale_step_discount_pct' => 2,
            'wholesale_max_discount_pct' => 25,
            'highlights_text' => "Condition: Brand New\nCells: 3-Cell Li-Ion\nWarranty: 180 Days",
        ]);
        $createResp->assertRedirect();

        $listing = SparePartListing::where('shop_id', $shop->id)->first();
        $this->assertNotNull($listing);
        $this->assertTrue($listing->offer_enabled);
        $this->assertNull($listing->offer_ends_at);
        $this->assertTrue($listing->isOfferActive());
        $this->assertSame(2790.0, $listing->effectiveBasePrice());
        $this->assertSame(3200.0, $listing->resolvedNormalBasePrice());

        // Vendor dashboard tab renders the new listing
        $dashResp = $this->actingAs($vendor)->get('/vendor/dashboard?tab=spare_parts');
        $dashResp->assertStatus(200)->assertSee('HP Pavilion 15 HT03XL Original Battery');

        // 2. Update listing to multi-spec with 10% offer discount
        $updateResp = $this->actingAs($vendor)->put('/vendor/spare-parts/' . $listing->id, [
            'shop_id' => $shop->id,
            'title' => 'HP Pavilion 15 HT03XL Battery (Grade Options)',
            'part_category' => 'Laptop Battery',
            'brand' => 'HP',
            'stock_quantity' => 200,
            'has_specs' => '1',
            'spec_label' => 'Quality',
            'spec_names' => ['Grade A Copy', '100% OEM Original'],
            'spec_prices' => [2200, 3600],
            'spec_subtitles' => ['90 Days Warranty', '180 Days Warranty'],
            'spec_stocks' => [120, 80],
            'offer_enabled' => '1',
            'offer_discount_pct' => 10,
            'offer_starts_at' => now()->subHour()->format('Y-m-d H:i:s'),
            'offer_ends_at' => now()->addDays(14)->format('Y-m-d H:i:s'),
            'wholesale_enabled' => '1',
            'wholesale_min_qty' => 100,
            'wholesale_base_discount_pct' => 5,
            'wholesale_step_qty' => 100,
            'wholesale_step_discount_pct' => 2,
            'wholesale_max_discount_pct' => 25,
        ]);
        $updateResp->assertRedirect();

        $listing->refresh();
        $this->assertTrue($listing->has_specs);
        $this->assertCount(2, $listing->normalizedSpecOptions());
        $this->assertSame(1980.0, $listing->effectiveBasePrice()); // 10% off 2200

        // 3. Toggle active/paused & delete
        $this->actingAs($vendor)->post('/vendor/spare-parts/' . $listing->id . '/toggle')->assertRedirect();
        $this->assertFalse($listing->fresh()->is_active);

        $this->actingAs($vendor)->delete('/vendor/spare-parts/' . $listing->id)->assertRedirect();
        $this->assertDatabaseMissing('spare_part_listings', ['id' => $listing->id]);
    }

    /** @test */
    public function matching_or_very_close_landed_totals_both_earn_auto_best_deal_tag_and_tiles_are_minimalistic(): void
    {
        // Seller A in Banani (23.7937, 90.4066): part ৳1,749 + courier ৳50 = ৳1,799
        [, $profA, $shopA] = $this->createApprovedVendorWithShop([
            'city' => 'Dhaka',
            'area' => 'Banani',
            'latitude' => 23.7937,
            'longitude' => 90.4066,
        ], 'Banani Parts One');

        // Seller B in Dhanmondi (~5.9 km away -> courier ৳70): part ৳1,740 + courier ৳70 = ৳1,810 (within 2% / ৳11 of ৳1,799!)
        [, $profB, $shopB] = $this->createApprovedVendorWithShop([
            'city' => 'Dhaka',
            'area' => 'Dhanmondi',
            'latitude' => 23.7461,
            'longitude' => 90.3742,
        ], 'Dhanmondi Parts Two');

        // Seller C in Banani: part ৳1,920 + courier ৳50 = ৳1,970 (noticeably higher -> NOT Best Deal)
        [, $profC, $shopC] = $this->createApprovedVendorWithShop([
            'city' => 'Dhaka',
            'area' => 'Banani',
            'latitude' => 23.7940,
            'longitude' => 90.4070,
        ], 'Premium Markup Parts');

        $partA = SparePartListing::create([
            'shop_id' => $shopA->id,
            'vendor_profile_id' => $profA->id,
            'slug' => 'tie-close-display--a',
            'product_group_key' => 'tie-close-display',
            'title' => 'iPhone 13 Pro Display Assembly',
            'part_category' => 'Display & Touch',
            'brand' => 'Apple',
            'has_specs' => true,
            'spec_options' => [
                ['code' => 'oled', 'name' => 'OLED', 'price' => 1749, 'stock' => 25],
                ['code' => 'orig', 'name' => 'Original', 'price' => 9500, 'stock' => 10],
            ],
            'base_price' => 1749,
            'normal_price' => 1749,
            'max_price' => 9500,
            'normal_max_price' => 9500,
            'stock_quantity' => 35,
            'is_active' => true,
        ]);

        $partB = SparePartListing::create([
            'shop_id' => $shopB->id,
            'vendor_profile_id' => $profB->id,
            'slug' => 'tie-close-display--b',
            'product_group_key' => 'tie-close-display',
            'title' => 'iPhone 13 Pro Display Assembly',
            'part_category' => 'Display & Touch',
            'brand' => 'Apple',
            'base_price' => 1740,
            'normal_price' => 1740,
            'stock_quantity' => 35,
            'is_active' => true,
        ]);

        $partC = SparePartListing::create([
            'shop_id' => $shopC->id,
            'vendor_profile_id' => $profC->id,
            'slug' => 'tie-close-display--c',
            'product_group_key' => 'tie-close-display',
            'title' => 'iPhone 13 Pro Display Assembly',
            'part_category' => 'Display & Touch',
            'brand' => 'Apple',
            'base_price' => 1920,
            'normal_price' => 1920,
            'stock_quantity' => 35,
            'is_active' => true,
        ]);

        $indexResp = $this->get('/spare-parts?group=tie-close-display&city=Dhaka&area=Banani&lat=23.7937&lng=90.4066');
        $indexResp->assertStatus(200);

        // Outside tiles must NOT show View / Buy or Select Specs buttons, and must show minimalistic Total breakdown
        $indexResp->assertDontSee('Select Specs');
        $indexResp->assertDontSee('View / Buy');
        $indexResp->assertDontSee('View Now');
        $indexResp->assertSee('Delivery: ৳50');
        $indexResp->assertSee('Est. Total');

        $items = $indexResp->viewData('listings')->getCollection()->keyBy('id');
        $this->assertTrue($items[$partA->id]->is_best_deal, 'Lowest landed deal (৳1,799) must be tagged Best Deal.');
        $this->assertTrue($items[$partB->id]->is_best_deal, 'Very-close landed deal (৳1,810 vs ৳1,799) must also be tagged Best Deal.');
        $this->assertFalse($items[$partC->id]->is_best_deal, 'Higher landed deal (৳1,970) must not be tagged Best Deal.');

        // Product detail page for Seller B should ALSO recognize Seller B as a Best Deal winner alongside Seller A
        $showB = $this->get('/spare-parts/' . $partB->slug . '?city=Dhaka&area=Banani&lat=23.7937&lng=90.4066');
        $showB->assertStatus(200);
        $this->assertTrue($showB->viewData('listing')->is_best_deal);
    }

    /** @test */
    public function optional_workshop_installation_is_capped_at_three_units_fuzzy_search_works_and_vendor_bi_renders(): void
    {
        [$vendorUser, $profile, $shop] = $this->createApprovedVendorWithShop([], 'Biometrics Repair Co');
        $customer = User::factory()->create(['role' => 'customer', 'is_active' => true]);

        $displayPart = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'iphone-13-pro-display--bio',
            'product_group_key' => 'iphone-13-pro-display',
            'title' => 'iPhone 13 Pro Display Assembly',
            'part_category' => 'Display & Touch',
            'brand' => 'Apple',
            'compatible_model' => 'iPhone 13 Pro',
            'base_price' => 8500,
            'normal_price' => 8500,
            'assembly_fee' => 300,
            'stock_quantity' => 200,
            'sold_count' => 25,
            'wholesale_enabled' => true,
            'wholesale_min_qty' => 100,
            'wholesale_base_discount_pct' => 5,
            'wholesale_step_qty' => 100,
            'wholesale_step_discount_pct' => 2,
            'wholesale_max_discount_pct' => 25,
            'is_active' => true,
        ]);

        SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 's23-battery--bio',
            'product_group_key' => 's23-battery',
            'title' => 'Samsung Galaxy S23 Battery Pack',
            'part_category' => 'Battery',
            'brand' => 'Samsung',
            'compatible_model' => 'Galaxy S23',
            'base_price' => 2100,
            'normal_price' => 2100,
            'assembly_fee' => 250,
            'stock_quantity' => 100,
            'sold_count' => 4,
            'is_active' => true,
        ]);

        \App\Models\ShopService::create([
            'shop_id' => $shop->id,
            'slug' => 'oled-display-calibration-' . uniqid(),
            'name' => 'OLED Display Calibration',
            'description' => 'Precision color & TrueTone calibration',
            'device_type' => 'Smartphone',
            'issue_category' => 'Display',
            'pricing_type' => 'FIXED',
            'base_price' => 1500,
            'is_active' => true,
        ]);

        \App\Models\ShopReview::create([
            'repair_shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'rating' => 5,
            'service_provided' => 'OLED Display Calibration',
            'review_comment' => 'Crisp OLED replacement calibration with zero dust.',
            'is_hidden' => false,
        ]);

        // 1. Clean wholesale copy without ambiguous + / - signs
        $detail = $this->get('/spare-parts/' . $displayPart->slug);
        $detail->assertStatus(200)
            ->assertSee('Save 5% on 100+ pcs')
            ->assertDontSee('-5% at 100+, +2% / 100 pcs')
            ->assertSee('Include Certified Workshop Installation')
            ->assertSee('Limited to retail orders of 1–3 units.');

        // 2. Installation allowed up to 3 units, blocked (422) for bulk quantities (100 pcs)
        $okAdd = $this->actingAs($customer)->postJson('/cart/add', [
            'spare_part_id' => $displayPart->id,
            'quantity' => 2,
            'include_installation' => true,
        ]);
        $okAdd->assertStatus(200)
            ->assertJsonPath('item.include_installation', true);

        $itemKey = $okAdd->json('item.id');

        $bulkAdd = $this->actingAs($customer)->postJson('/cart/add', [
            'spare_part_id' => $displayPart->id,
            'quantity' => 100,
            'include_installation' => true,
        ]);
        $bulkAdd->assertStatus(422)
            ->assertJsonValidationErrors('include_installation');

        // 3. Updating cart qty > 3 automatically clears include_installation to false
        $updBulk = $this->actingAs($customer)->patchJson('/cart/update/' . urlencode($itemKey), [
            'quantity' => 10,
        ]);
        $updBulk->assertStatus(200)
            ->assertJsonPath('item.include_installation', false)
            ->assertJsonPath('item.installation_fee_per_unit', 0);

        // 4. Fuzzy & synonym search matches compound names and typos
        $this->get('/spare-parts?q=iphone13+screen')
            ->assertStatus(200)
            ->assertSee('iPhone 13 Pro Display Assembly');

        $this->get('/spare-parts?q=s23+battry')
            ->assertStatus(200)
            ->assertSee('Samsung Galaxy S23 Battery Pack');

        // 5. Vendor Dashboard BI graphs, demand & rating matrices render cleanly
        $this->actingAs($vendorUser)
            ->get('/vendor/dashboard')
            ->assertStatus(200)
            ->assertSee('Vendor Earnings Trend Chart')
            ->assertSee('Services Demand (Sales Volume)')
            ->assertSee('Spare Parts Demand (Units Sold)')
            ->assertSee('Most Demanding')
            ->assertSee('Least Popular');
    }

    public function test_marketplace_directory_sorting_normalization_and_device_exclusion(): void
    {
        // 1. Whole devices & non-repair accessories are excluded from seed ingestion
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('Apple iPhone 11', 'Mobile Spare Parts', 44999));
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('Apple iPhone 12', 'Mobile Spare Parts', 60499));
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('HP Pavilion 15-eg2000 Core i5 Laptop', 'SSD, RAM & Storage', 78000));
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('Hp ProBook 4440s Core-i5 3rd Gen 256GB SSD 8GB DDR3 RAM', 'SSD, RAM & Storage', 11990));
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('Lenovo ThinkPad T14s Gen 3 512GB NVMe SSD', 'SSD, RAM & Storage', 65500));
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('Used Apple Macbook Air A2337 M1 2020 Full Box Battery 84%', 'Laptop Battery', 55500));
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('Aluminium Folding Laptop Desk Stand', 'Accessories & Chargers', 1500));
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('HV-F2056 External Laptop Cooling Pad', 'Laptop Cooling Fan', 1200));
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('QA partial dummy item', 'Mobile Spare Parts', 100));

        $this->assertSame('Samsung J7 (2016) Camera Glass Lens', SparePartListing::cleanCatalogTitle('Samsung J7 (2016)', 'Camera & Lens'));

        $this->assertFalse(SparePartListing::isWholeDeviceOrInvalidSeedItem('102W 15V 6.33A Charger for Microsoft Surface Book 3', 'Accessories & Chargers', 3450));
        $this->assertFalse(SparePartListing::isWholeDeviceOrInvalidSeedItem('Samsung Galaxy S21 FE 5G Display', 'Display & Screen', 6200));
        $this->assertFalse(SparePartListing::isWholeDeviceOrInvalidSeedItem('Precision SMD Hot Air Rework Station 858D', 'Tools, Consumables & Soldering', 4200));

        // 2. Category, brand & category fallback image normalization
        $this->assertSame('Adapter & Charger', SparePartListing::normalizePartCategory('Accessories & Chargers', '102W 15V 6.33A Charger for Microsoft Surface Book 3'));
        $this->assertSame('Microsoft', SparePartListing::inferSpecificBrandFromTitle('102W 15V 6.33A Charger for Microsoft Surface Book 3', 'PC / Laptop'));
        $chargerImg = SparePartListing::resolveCategoryFallbackImage('Adapter & Charger', '102W 15V 6.33A Charger for Microsoft Surface Book 3', 'Microsoft');
        $this->assertNotSame('/images/spare-parts/samsung-galaxy-s21-fe-5g-display.webp', $chargerImg);
        $this->assertSame('Display & Touch', SparePartListing::normalizePartCategory('Mobile Spare Parts', 'Samsung Galaxy S21 Display Assembly'));

        // 3. Create seller with rating 0.00 + parts across OLED sub-category and Tools category
        [$vendor, $profile, $shop] = $this->createApprovedVendorWithShop([], 'Directory Test Lab');
        $shop->update(['rating' => 0.00]);

        SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'oled-s22-display-' . uniqid(),
            'product_group_key' => 's22-oled',
            'title' => 'Samsung S22 Super AMOLED Display Panel',
            'part_category' => 'Display & Touch',
            'brand' => 'Samsung',
            'base_price' => 8500,
            'normal_price' => 8500,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'lcd-j7-display-' . uniqid(),
            'product_group_key' => 'j7-lcd',
            'title' => 'Samsung J7 IPS LCD Screen',
            'part_category' => 'Display & Touch',
            'brand' => 'Samsung',
            'base_price' => 1400,
            'normal_price' => 1400,
            'stock_quantity' => 20,
            'is_active' => true,
        ]);

        SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'hot-air-858d-' . uniqid(),
            'product_group_key' => 'hot-air-858d',
            'title' => 'Precision SMD Hot Air Rework Station 858D',
            'part_category' => 'Tools, Consumables & Soldering',
            'brand' => 'Quick',
            'base_price' => 4200,
            'normal_price' => 4200,
            'stock_quantity' => 15,
            'is_active' => true,
        ]);

        // 4. Unsearched index shows CATEGORIES sidebar, both lowest & highest sort options, no redundant badges, and defaults 0.00 shop rating to 4.8
        $indexRes = $this->get('/spare-parts');
        $indexRes->assertStatus(200)
            ->assertSee('Catalog')
            ->assertSee('CATEGORIES')
            ->assertDontSee('<span>Directory</span>', false)
            ->assertSee('Tools &amp; Soldering', false)
            ->assertSee('Price + Shipping: lowest first (Best Deal)')
            ->assertSee('Item Price: lowest first')
            ->assertSee('Price + Shipping: highest first')
            ->assertDontSee('Live Listings')
            ->assertDontSee('★ 0.0')
            ->assertSee('★ 4.8');

        // 5. Sub-category filter (?part_category=Display & Touch&sub_category=amoled_oled) isolates AMOLED/OLED from IPS/LCD
        $oledRes = $this->get('/spare-parts?part_category=' . urlencode('Display & Touch') . '&sub_category=amoled_oled');
        $oledRes->assertStatus(200)
            ->assertSee('Samsung S22 Super AMOLED Display Panel')
            ->assertDontSee('Samsung J7 IPS LCD Screen');

        // 6. Lowest-first vs Highest-first price sorting works cleanly when unsearched
        $lowRes = $this->get('/spare-parts?sort=lowest_price');
        $lowRes->assertStatus(200);
        $lowTitles = $lowRes->viewData('listings')->getCollection()->pluck('title')->values()->all();
        $this->assertTrue(
            array_search('Samsung J7 IPS LCD Screen', $lowTitles, true) < array_search('Samsung S22 Super AMOLED Display Panel', $lowTitles, true)
        );

        $highRes = $this->get('/spare-parts?sort=highest_price');
        $highRes->assertStatus(200);
        $highTitles = $highRes->viewData('listings')->getCollection()->pluck('title')->values()->all();
        $this->assertTrue(
            array_search('Samsung S22 Super AMOLED Display Panel', $highTitles, true) < array_search('Samsung J7 IPS LCD Screen', $highTitles, true)
        );
    }

    /** @test */
    public function image_validation_rejects_mismatched_urls_and_resolves_verified_clean_studio_assets(): void
    {
        // Cross-brand, wrong-component, template/placeholder & legacy S21 FE fallbacks must be rejected
        $this->assertTrue(SparePartListing::isMismatchedOrInvalidImageUrl(
            '/images/spare-parts/samsung-galaxy-s21-fe-5g-display.webp',
            'Adapter & Charger',
            '102W 15V 6.33A Charger for Microsoft Surface Book 3',
            'Microsoft'
        ));
        $this->assertTrue(SparePartListing::isMismatchedOrInvalidImageUrl(
            'https://etel.com.bd/wp-content/uploads/2026/08/Sony-Xperia-1-V-Backshell.webp',
            'Display & Touch',
            'Sony Xperia 1 V Display',
            'Sony'
        ));
        $this->assertTrue(SparePartListing::isMismatchedOrInvalidImageUrl(
            'https://etel.com.bd/wp-content/uploads/2025/01/Huawei-Mate-70-Pro-Display-1.webp',
            'Battery',
            'Huawei Mate 70 Pro Battery',
            'Huawei'
        ));
        $this->assertTrue(SparePartListing::isMismatchedOrInvalidImageUrl(
            'https://etel.com.bd/wp-content/uploads/2025/01/oneplus-9rt-original-display.jpg',
            'Display & Touch',
            'Original Realme GT Neo2T Display',
            'Realme'
        ));
        $this->assertTrue(SparePartListing::isMismatchedOrInvalidImageUrl(
            'https://laptopworlddhaka.com/wp-content/uploads/2022/10/products-frame-2.png',
            'Laptop Battery',
            'F3YGT DELL LAPTOP ORIGINAL BATTERY',
            'Dell'
        ));
        $this->assertTrue(SparePartListing::isMismatchedOrInvalidImageUrl(
            'https://laptopbd.net/wp-content/uploads/2025/12/Untitled-design-1.jpg',
            'Laptop Keyboard',
            'ACER 4710 Laptop Keyboard',
            'Acer'
        ));
        $this->assertTrue(SparePartListing::isMismatchedOrInvalidImageUrl(
            'https://dearit.com.bd/assets/c/dspythone3/placeholder-500x500fill.webp',
            'Laptop Battery',
            'Lenovo ThinkPad T430s Laptop Battery',
            'Lenovo'
        ));

        // Legitimate back glass + clean studio asset URLs pass validation cleanly
        $this->assertFalse(SparePartListing::isMismatchedOrInvalidImageUrl(
            'https://etel.com.bd/wp-content/uploads/2025/01/iPhone-13-Pro-Max-Backshell.webp',
            'Backshell & Housing',
            'iPhone 13 Pro Max Back Glass',
            'Apple'
        ));

        // Clean studio assets exist on disk and resolve accurately by part sub-type
        $surfaceChargerUrl = SparePartListing::resolveListingImageUrl(
            '/images/spare-parts/samsung-galaxy-s21-fe-5g-display.webp',
            'Adapter & Charger',
            '102W 15V 6.33A Charger for Microsoft Surface Book 3',
            'Microsoft'
        );
        $this->assertSame('/images/spare-parts/clean/charger-surface-magnetic.webp', $surfaceChargerUrl);
        $this->assertFileExists(public_path(ltrim($surfaceChargerUrl, '/')));

        $ramUrl = SparePartListing::resolveListingImageUrl(
            '/images/spare-parts/samsung-galaxy-s21-fe-5g-display.webp',
            'SSD, RAM & Storage',
            'DDR3L 4GB 1333MHz SemsoTai Laptop RAM',
            'SemsoTai'
        );
        $this->assertSame('/images/spare-parts/clean/ram-sodimm-module.webp', $ramUrl);
        $this->assertFileExists(public_path(ltrim($ramUrl, '/')));

        // All 11 curated Tools, Consumables & Soldering listings resolve to distinct clean studio assets on disk
        $toolTitles = [
            'Kaisi K-8126 126-in-1 Magnetic Precision Repair Screwdriver Kit',
            'Quick 861DW 1000W Hot Air BGA Rework & Soldering Station',
            'Mechanic UV50 BGA Solder Flux Paste (50g Jar)',
            'Mechanic 0.3mm Lead-Free Rosin Core Solder Wire Roll',
            'UNI-T UT89X True RMS Digital Multimeter for PCB Diagnostics',
            'Zhanlida B-7000 Frame & Display Bonding Adhesive (110ml)',
            'Relife RL-049B Anti-Static IC & Motherboard Pry Spudger Set',
            'Sunshine S-918E LCD & OLED Screen Heating Separator Pad',
            'ESD-15 Stainless Steel Anti-Static Precision Micro Tweezers Set',
            'Mechanic S-160 Heat-Resistant Silicone Soldering Repair Mat',
            '3M 300LSE Double-Sided Display & Battery Mounting Tape Roll',
        ];
        $resolvedTools = [];
        foreach ($toolTitles as $toolTitle) {
            $url = SparePartListing::resolveListingImageUrl('', 'Tools, Consumables & Soldering', $toolTitle, 'Universal', 'meramot_tools');
            $this->assertStringStartsWith('/images/spare-parts/clean/tool-', $url);
            $this->assertFileExists(public_path(ltrim($url, '/')));
            $resolvedTools[] = $url;
        }
        $this->assertCount(11, array_unique($resolvedTools));

        // Comma-truncated DearIT image recovers local WebP on disk when present
        $dearitUrl = SparePartListing::resolveListingImageUrl(
            '-l20d4p71-laptop-battery-500x500fill.webp?v=1793617423',
            'Laptop Battery',
            'Original Lenovo ThinkPad X1 Carbon L20C4P71, L20D4P71 Laptop Battery',
            'Lenovo',
            'dearit',
            '8160'
        );
        $this->assertSame('/images/spare-parts/dearit/dearit-8160.webp', $dearitUrl);
        $this->assertFileExists(public_path(ltrim($dearitUrl, '/')));

        // Category misfiling fixes
        $this->assertSame('Backshell & Housing', SparePartListing::normalizePartCategory('Battery', 'Oppo F33 Backshell'));
        $this->assertSame('SIM Tray & Small Parts', SparePartListing::normalizePartCategory('Charging Port & Flex', 'Samsung Galaxy S25 Plus SIM Tray'));
        $this->assertSame('Charging Port & Flex', SparePartListing::normalizePartCategory('Battery', 'Tecno Camon 30 Charging Logic'));
        $this->assertSame('Battery', SparePartListing::normalizePartCategory('Display & Touch', 'Huawei Pura 90 Pro Battery'));
    }

    /** @test */
    public function categories_sidebar_nests_brands_inside_sub_categories_and_rejects_rechargeable_fans(): void
    {
        // 1. Consumer rechargeable desk/mini fans are rejected while laptop cooling fans pass
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('JYSUPER JY-2218 Rechargeable Fan', 'Laptop Cooling Fan', 1500.0));
        $this->assertTrue(SparePartListing::isWholeDeviceOrInvalidSeedItem('DP-7639 4000mAh Rechargeable Fan', 'Laptop Cooling Fan', 1500.0));
        $this->assertFalse(SparePartListing::isWholeDeviceOrInvalidSeedItem('ASUS ROG Strix G512 CPU+GPU Cooling Fan', 'Laptop Cooling Fan', 1500.0));

        // 2. Every sub-category label is concise (<= 26 chars) and free of brand/model-series names
        $forbiddenBrandSeries = ['iPhone', 'iPad', 'Galaxy', 'Pixel', 'OnePlus', 'Xiaomi', 'Oppo', 'Vivo', 'Realme', 'ThinkPad', 'Latitude', 'EliteBook', 'MacBook', 'Surface', 'ZenBook', 'XPS', 'Pavilion', 'IdeaPad', 'Inspiron', 'MagSafe'];
        foreach (\App\Http\Controllers\SparePartController::directorySchema() as $rootDef) {
            foreach ($rootDef['categories'] as $catMeta) {
                foreach ($catMeta['sub_categories'] as $subLabel) {
                    $this->assertLessThanOrEqual(26, strlen($subLabel), "Sub-category label '{$subLabel}' exceeds 26 chars");
                    foreach ($forbiddenBrandSeries as $term) {
                        $this->assertStringNotContainsStringIgnoringCase($term, $subLabel, "Sub-category label '{$subLabel}' must not contain brand/series '{$term}'");
                    }
                }
            }
        }

        // 3. Nested Display & Touch > AMOLED & OLED Screens > Brands > Google isolates Google AMOLED screens
        [, $profile, $shop] = $this->createApprovedVendorWithShop();

        SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'title' => 'Google Pixel 7 Pro AMOLED Display',
            'slug' => 'google-pixel-7-pro-amoled-display-' . uniqid(),
            'product_group_key' => 'pixel-7-pro-amoled',
            'part_category' => 'Display & Touch',
            'brand' => 'Google',
            'is_active' => true,
            'stock_quantity' => 5,
            'base_price' => 12500,
            'normal_price' => 12500,
        ]);

        SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'title' => 'Samsung S22 Super AMOLED Panel',
            'slug' => 'samsung-s22-super-amoled-panel-' . uniqid(),
            'product_group_key' => 's22-amoled',
            'part_category' => 'Display & Touch',
            'brand' => 'Samsung',
            'is_active' => true,
            'stock_quantity' => 5,
            'base_price' => 11500,
            'normal_price' => 11500,
        ]);

        $charger = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'title' => '102W 15V 6.33A Charger for Microsoft Surface Book 3',
            'slug' => '102w-15v-surface-book-3-charger-' . uniqid(),
            'product_group_key' => 'surface-book-3-102w',
            'part_category' => 'Adapter & Charger',
            'brand' => 'Microsoft',
            'is_active' => true,
            'stock_quantity' => 4,
            'base_price' => 3450,
            'normal_price' => 3450,
        ]);

        $res = $this->get('/spare-parts?part_category=' . urlencode('Display & Touch') . '&sub_category=amoled_oled&brand=Google');
        $res->assertStatus(200)
            ->assertSee('CATEGORIES')
            ->assertSee('AMOLED &amp; OLED Screens', false)
            ->assertSee('Brands (2)')
            ->assertSee('Google Pixel 7 Pro AMOLED Display')
            ->assertDontSee('Samsung S22 Super AMOLED Panel');

        // 4. Show page breadcrumb includes inferred sub-category and preserves sub_category in Brand link
        $showRes = $this->get('/spare-parts/' . $charger->slug);
        $showRes->assertStatus(200)
            ->assertSee('Magnetic &amp; Slim-Tip PSU', false)
            ->assertSee('sub_category=magnetic_slim_adapter', false);
    }
}

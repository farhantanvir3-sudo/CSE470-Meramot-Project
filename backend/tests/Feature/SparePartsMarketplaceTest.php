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
}

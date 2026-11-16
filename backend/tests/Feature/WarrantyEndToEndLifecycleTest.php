<?php

namespace Tests\Feature;

use App\Models\FinancialLedger;
use App\Models\IssuedWarranty;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\SparePartListing;
use App\Models\SparePartStockMovement;
use App\Models\SparePartWarranty;
use App\Models\User;
use App\Models\VendorProfile;
use App\Models\WarrantyClaim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class WarrantyEndToEndLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createSellerWithShop(): array
    {
        $vendorUser = User::factory()->create([
            'role' => 'vendor',
            'city' => 'Dhaka',
            'area' => 'Banani',
        ]);

        $profile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Warranty Parts Hub',
            'brand_name' => 'Warranty Parts Hub',
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
            'name' => 'Warranty Parts Hub',
            'slug' => 'warranty-parts-hub-' . uniqid(),
            'city' => 'Dhaka',
            'area' => 'Banani',
            'address' => 'Road 11, Banani, Dhaka',
            'latitude' => 23.7937,
            'longitude' => 90.4066,
            'categories' => ['SPARE_PARTS', 'IN_SHOP_REPAIR', 'COURIER_PICKUP'],
            'rating' => 4.9,
            'review_count' => 20,
            'inspection_fee' => 200,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
            'is_active' => true,
            'is_public' => true,
        ]);

        return [$vendorUser, $profile, $shop];
    }

    public function test_vendor_warranty_registry_stores_only_warrantied_listings(): void
    {
        [$vendorUser, , $shop] = $this->createSellerWithShop();

        // 1. Vendor lists a spare part WITH warranty -> row created in `spare_part_warranties`
        $this->actingAs($vendorUser)->post('/vendor/spare-parts', [
            'shop_id' => $shop->id,
            'part_category' => 'Battery',
            'brand' => 'Acer',
            'title' => 'AC14B18J Laptop Battery Pack',
            'base_price' => 2000,
            'stock_quantity' => 15,
            'has_warranty' => '1',
            'warranty_days' => 180,
            'warranty_coverage_type' => SparePartWarranty::COVERAGE_FULL_PROTECTION,
            'warranty_terms' => 'Covers cell swelling and premature discharge within 180 days.',
        ])->assertRedirect();

        $battery = SparePartListing::where('title', 'AC14B18J Laptop Battery Pack')->firstOrFail();
        $this->assertSame(180, (int) $battery->warranty_days);
        $this->assertDatabaseHas('spare_part_warranties', [
            'spare_part_listing_id' => $battery->id,
            'duration_days' => 180,
            'coverage_type' => SparePartWarranty::COVERAGE_FULL_PROTECTION,
            'covers_refund' => true,
            'covers_replacement' => true,
            'covers_free_repair' => true,
        ]);

        // Public product detail shows Verified Warranty Protection card
        $this->get('/spare-parts/' . $battery->slug)
            ->assertOk()
            ->assertSee('data-listing-warranty-card', false)
            ->assertSee('180-Day Warranty');

        // 2. Vendor lists a consumable WITHOUT warranty -> ZERO row in `spare_part_warranties`
        $this->actingAs($vendorUser)->post('/vendor/spare-parts', [
            'shop_id' => $shop->id,
            'part_category' => 'Tools, Consumables & Soldering',
            'brand' => 'Mechanic',
            'title' => 'Mechanic UV50 Solder Flux Paste',
            'base_price' => 350,
            'stock_quantity' => 40,
            'has_warranty' => '0',
            'warranty_days' => 0,
        ])->assertRedirect();

        $flux = SparePartListing::where('title', 'Mechanic UV50 Solder Flux Paste')->firstOrFail();
        $this->assertSame(0, (int) $flux->warranty_days);
        $this->assertDatabaseMissing('spare_part_warranties', [
            'spare_part_listing_id' => $flux->id,
        ]);

        // 3. Disabling warranty during update removes separate registry row
        $this->actingAs($vendorUser)->put('/vendor/spare-parts/' . $battery->id, [
            'shop_id' => $shop->id,
            'part_category' => 'Battery',
            'brand' => 'Acer',
            'title' => 'AC14B18J Laptop Battery Pack',
            'base_price' => 2000,
            'stock_quantity' => 15,
            'has_warranty' => '0',
        ])->assertRedirect();

        $this->assertSame(0, (int) $battery->fresh()->warranty_days);
        $this->assertDatabaseMissing('spare_part_warranties', [
            'spare_part_listing_id' => $battery->id,
        ]);
    }

    public function test_end_to_end_warranty_purchase_repeatable_claims_misuse_detection_and_admin_resolutions(): void
    {
        [, $profile, $shop] = $this->createSellerWithShop();
        $customer = User::factory()->create(['role' => 'customer', 'city' => 'Dhaka', 'area' => 'Banani']);
        $admin = User::factory()->create(['role' => 'admin']);

        $listing = SparePartListing::create([
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'slug' => 'ac14b18j-battery-' . uniqid(),
            'product_group_key' => 'ac14b18j-battery',
            'sku' => 'SKU-AC14-' . uniqid(),
            'title' => 'AC14B18J Acer Aspire Replacement Battery',
            'part_category' => 'Battery',
            'brand' => 'Acer',
            'base_price' => 2000,
            'warranty_days' => 180,
            'stock_quantity' => 10,
            'sold_count' => 1,
            'returned_count' => 0,
            'is_active' => true,
        ]);
        $spWarranty = SparePartWarranty::create([
            'spare_part_listing_id' => $listing->id,
            'shop_id' => $shop->id,
            'vendor_profile_id' => $profile->id,
            'duration_days' => 180,
            'coverage_type' => SparePartWarranty::COVERAGE_FULL_PROTECTION,
            'covers_refund' => true,
            'covers_replacement' => true,
            'covers_free_repair' => true,
            'terms' => '180-day cell and BMS controller warranty.',
        ]);

        // Place completed spare part order and settle via VendorBranchController
        $req = RepairRequest::create([
            'customer_id' => $customer->id,
            'source' => 'DIRECT_SERVICE',
            'requested_shop_id' => $shop->id,
            'title' => 'AC14B18J Acer Aspire Replacement Battery',
            'description' => 'Direct Spare Part Order',
            'problem' => 'Order battery pack',
            'device_type' => 'Spare Part Order',
            'brand' => 'Acer',
            'model_name' => 'Aspire E5',
            'mode' => 'DIRECT_REPAIR',
            'status' => RepairRequest::STATUS_REPAIRING,
            'preferred_pickup' => false,
            'preferred_service_type' => 'walk_in',
            'contact_phone' => '01711112222',
            'quoted_final_amount' => 2220.00,
        ]);

        $job = RepairJob::create([
            'repair_request_id' => $req->id,
            'shop_id' => $shop->id,
            'status' => 'REPAIRING',
            'parts_cost' => 2000.00,
            'labor_cost' => 0,
            'has_warranty' => true,
            'warranty_days' => 180,
            'final_quoted_amount' => 2000.00,
            'final_quote_items' => [[
                'id' => 'sp_' . $listing->id,
                'spare_part_id' => $listing->id,
                'title' => $listing->title,
                'price' => 2000.00,
                'part_unit_price' => 2000.00,
                'quantity' => 1,
                'source' => 'SPARE_PART',
                'has_warranty' => true,
                'warranty_days' => 180,
                'warranty_coverage_type' => SparePartWarranty::COVERAGE_FULL_PROTECTION,
            ]],
        ]);

        // Mark request COMPLETED (triggers warranty issuance)
        $req->update(['status' => RepairRequest::STATUS_COMPLETED]);
        $job->update(['status' => 'COMPLETED', 'completed_at' => now()]);
        \App\Http\Controllers\VendorBranchController::settleCompletedJobFinancials($job->fresh());

        $issued = IssuedWarranty::where('repair_request_id', $req->id)->firstOrFail();
        $this->assertSame($spWarranty->id, (int) $issued->spare_part_warranty_id);
        $this->assertSame(180, (int) $issued->duration_days);
        $this->assertTrue($issued->isCurrentlyActive());

        // Customer Past Completed Orders & Warranties tab both show Active Warranty Period
        $this->actingAs($customer)->get('/requests?filter=COMPLETED')
            ->assertOk()
            ->assertSee('Active Warranty Period')
            ->assertSee($listing->title);
        $this->actingAs($customer)->get('/requests?filter=WARRANTY')
            ->assertOk()
            ->assertSee('Claim Warranty');

        // ── Claim #1: Customer submits fault text + uploaded image -> Admin approves REPLACEMENT ──
        $photoBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
        $tmpImg1 = UploadedFile::fake()->createWithContent('defect1.png', $photoBytes);

        $this->actingAs($customer)->postJson("/warranties/{$issued->id}/claims", [
            'preferred_remedy' => 'REPLACEMENT',
            'claimed_quantity' => 1,
            'explanation' => 'Battery stops charging at 12% and shuts down the Acer laptop immediately.',
            'evidence_images' => [$tmpImg1],
        ])->assertCreated();

        $claim1 = WarrantyClaim::where('issued_warranty_id', $issued->id)->firstOrFail();
        $this->assertSame(1, (int) $claim1->claim_number);
        $this->assertNotEmpty($claim1->customer_image_urls);
        $this->assertStringStartsWith('data:image/png;base64,', $claim1->customer_image_urls[0]);

        $this->actingAs($admin)->get('/admin/warranties')
            ->assertOk()
            ->assertSee("Claim Ticket #{$claim1->id}");

        $this->actingAs($admin)->postJson("/admin/warranties/{$claim1->id}/assess", [
            'decision' => 'REPLACEMENT',
            'admin_reply' => 'Verified BMS fault from evidence; fresh AC14B18J unit dispatched from shop stock.',
        ])->assertOk();

        $this->assertSame(WarrantyClaim::STATUS_APPROVED_REPLACEMENT, $claim1->fresh()->status);
        $this->assertSame(9, (int) $listing->fresh()->stock_quantity);
        $this->assertSame(1, (int) $listing->fresh()->returned_count);
        $this->assertSame(1, (int) $listing->fresh()->sold_count);
        $this->assertDatabaseHas('spare_part_stock_movements', [
            'spare_part_listing_id' => $listing->id,
            'movement_type' => SparePartStockMovement::TYPE_WARRANTY_REPLACEMENT,
            'stock_after' => 9,
            'returned_after' => 1,
        ]);
        $this->assertTrue($issued->fresh()->isCurrentlyActive());

        // ── Claim #2: Repeatable claim reusing same image -> triggers misuse flags; Admin REJECT requires explanation + image ──
        $dupImg = UploadedFile::fake()->createWithContent('defect1.png', $photoBytes);
        $this->actingAs($customer)->postJson("/warranties/{$issued->id}/claims", [
            'preferred_remedy' => 'REFUND',
            'claimed_quantity' => 1,
            'explanation' => 'Second replacement battery also drains within ten minutes of booting.',
            'evidence_images' => [$dupImg],
        ])->assertCreated();

        $claim2 = WarrantyClaim::where('issued_warranty_id', $issued->id)
            ->where('claim_number', 2)
            ->firstOrFail();
        $this->assertGreaterThanOrEqual(50, (int) $claim2->misuse_risk_score);
        $this->assertNotEmpty($claim2->misuse_flags);

        // Rejection without image & detailed explanation must fail with 422
        $this->actingAs($admin)->postJson("/admin/warranties/{$claim2->id}/assess", [
            'decision' => 'REJECT',
            'admin_reply' => 'No.',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['admin_reply', 'admin_images']);

        // Valid Rejection with detailed explanation + proof photo succeeds
        $adminProofImg = UploadedFile::fake()->createWithContent('admin_proof.png', $photoBytes . 'proof');
        $this->actingAs($admin)->postJson("/admin/warranties/{$claim2->id}/assess", [
            'decision' => 'REJECT',
            'admin_reply' => 'Submitted duplicate photo from prior ticket and bench test image shows physical puncture outside warranty.',
            'admin_images' => [$adminProofImg],
        ])->assertOk();

        $claim2->refresh();
        $this->assertSame(WarrantyClaim::STATUS_REJECTED, $claim2->status);
        $this->assertNotEmpty($claim2->admin_image_urls);

        // Customer still sees admin rejection explanation + image on tracking page
        $this->actingAs($customer)->get('/requests/' . $req->id . '/track')
            ->assertOk()
            ->assertSee('Submitted duplicate photo from prior ticket');

        // ── Claim #3: Repeatable claim #3 -> Admin approves FREE_REPAIR ──
        $freshImg3 = UploadedFile::fake()->createWithContent('fault3.png', $photoBytes . 'fault3');
        $this->actingAs($customer)->postJson("/warranties/{$issued->id}/claims", [
            'preferred_remedy' => 'FREE_REPAIR',
            'claimed_quantity' => 1,
            'explanation' => 'Connector pin inside the new unit is loose and cuts power when moved.',
            'evidence_images' => [$freshImg3],
        ])->assertCreated();

        $claim3 = WarrantyClaim::where('issued_warranty_id', $issued->id)
            ->where('claim_number', 3)
            ->firstOrFail();

        $this->actingAs($admin)->postJson("/admin/warranties/{$claim3->id}/assess", [
            'decision' => 'FREE_REPAIR',
            'admin_reply' => 'Authorized zero-cost connector resolder at shop branch under active warranty.',
        ])->assertOk();

        $claim3->refresh();
        $this->assertSame(WarrantyClaim::STATUS_APPROVED_FREE_REPAIR, $claim3->status);
        $this->assertNotNull($claim3->followup_repair_request_id);
        $followupOrder = RepairRequest::findOrFail($claim3->followup_repair_request_id);
        $this->assertSame(0.0, (float) $followupOrder->quoted_final_amount);

        // ── Claim #4: Repeatable claim #4 -> Admin approves REFUND (ledger clawback + defect return without re-stocking faulty unit) ──
        $freshImg4 = UploadedFile::fake()->createWithContent('fault4.png', $photoBytes . 'fault4');
        $this->actingAs($customer)->postJson("/warranties/{$issued->id}/claims", [
            'preferred_remedy' => 'REFUND',
            'claimed_quantity' => 1,
            'explanation' => 'Battery still loses charge rapidly after bench connector repair.',
            'evidence_images' => [$freshImg4],
        ])->assertCreated();

        $claim4 = WarrantyClaim::where('issued_warranty_id', $issued->id)
            ->where('claim_number', 4)
            ->firstOrFail();

        $this->actingAs($admin)->postJson("/admin/warranties/{$claim4->id}/assess", [
            'decision' => 'REFUND',
            'refund_amount' => 2000,
            'admin_reply' => 'Recurring hardware fault confirmed; full item value refunded via platform ledger.',
        ])->assertOk();

        $claim4->refresh();
        $issued->refresh();
        $listing->refresh();
        $this->assertSame(WarrantyClaim::STATUS_APPROVED_REFUND, $claim4->status);
        $this->assertSame(2000.0, (float) $claim4->refund_amount);
        $this->assertSame(IssuedWarranty::STATUS_REFUNDED_CLOSED, $issued->status);
        $this->assertSame(9, (int) $listing->stock_quantity);
        $this->assertSame(2, (int) $listing->returned_count);
        $this->assertTrue(FinancialLedger::where('entry_type', 'REFUND')->where('direction', 'DEBIT')->exists());
        $this->assertSame(1, (int) DB::table('refunds')->count());
    }

    public function test_expired_warranty_blocks_new_claim_submissions(): void
    {
        [, , $shop] = $this->createSellerWithShop();
        $customer = User::factory()->create(['role' => 'customer']);

        $req = RepairRequest::create([
            'customer_id' => $customer->id,
            'source' => 'DIRECT_SERVICE',
            'requested_shop_id' => $shop->id,
            'title' => 'Expired Order',
            'description' => 'Old Order',
            'problem' => 'Old repair',
            'device_type' => 'Phone',
            'brand' => 'Samsung',
            'model_name' => 'A52',
            'mode' => 'DIRECT_REPAIR',
            'status' => RepairRequest::STATUS_COMPLETED,
            'preferred_pickup' => false,
            'preferred_service_type' => 'walk_in',
            'contact_phone' => '01711113333',
            'quoted_final_amount' => 1500,
        ]);

        $expiredWarranty = IssuedWarranty::create([
            'repair_request_id' => $req->id,
            'customer_id' => $customer->id,
            'shop_id' => $shop->id,
            'line_key' => 'job:999',
            'item_title' => 'Samsung A52 Screen Replacement',
            'item_type' => 'REPAIR_SERVICE',
            'quantity' => 1,
            'unit_price' => 1500,
            'coverage_type' => SparePartWarranty::COVERAGE_FULL_PROTECTION,
            'duration_days' => 90,
            'starts_at' => now()->subDays(120),
            'expires_at' => now()->subDays(30),
            'status' => IssuedWarranty::STATUS_ACTIVE,
        ]);

        $this->actingAs($customer)->postJson("/warranties/{$expiredWarranty->id}/claims", [
            'explanation' => 'Trying to claim after warranty period already expired thirty days ago.',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['warranty']);
    }
}

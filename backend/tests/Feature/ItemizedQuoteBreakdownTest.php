<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ItemizedQuoteBreakdownTest extends TestCase
{
    use RefreshDatabase;

    private function createVerifiedVendor(): array
    {
        $vendorUser = User::factory()->create([
            'name' => 'Farhan Workshop',
            'role' => 'vendor',
            'is_active' => true,
        ]);

        $profile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'owner_name' => 'Farhan Workshop',
            'business_email' => $vendorUser->email,
            'phone' => '+8801711223344',
            'shop_name' => 'Precision MicroLab',
            'business_license_no' => 'TRAD/DNCC/909090',
            'nid_number' => '1990112233445',
            'verification_status' => 'approved',
            'shop_address' => 'Road 11, Banani, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'is_setup_completed' => true,
        ]);

        $shop = RepairShop::factory()->create([
            'vendor_profile_id' => $profile->id,
            'name' => 'Precision MicroLab',
            'address' => 'Road 11, Banani, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'inspection_fee' => 300.00,
            'pickup_fee' => 140.00,
            'setup_complete' => true,
            'is_active' => true,
            'is_public' => true,
        ]);

        return [$vendorUser, $profile, $shop];
    }

    /** @test */
    public function vendor_and_customer_quote_lifecycle_supports_itemized_parts_warranty_edit_and_settlement()
    {
        $customer = User::factory()->create([
            'name' => 'Sadia Customer',
            'role' => 'customer',
            'is_active' => true,
        ]);
        [$vendorUser, $profile, $shop] = $this->createVerifiedVendor();

        $repairRequest = RepairRequest::factory()->create([
            'customer_id' => $customer->id,
            'source' => 'MARKETPLACE',
            'status' => 'BIDDING',
            'device_type' => 'Laptop',
            'brand' => 'Apple',
            'model_name' => 'MacBook Pro 14',
            'title' => 'MacBook Pro Backlight Failure',
            'pickup_address' => 'House 12, Road 4, Dhanmondi, Dhaka',
            'delivery_type' => 'REGULAR',
            'preferred_pickup' => true,
        ]);

        // 1. Vendor submits initial 3-part itemized Quote 1 with distinct per-part warranty checkboxes & days
        $this->actingAs($vendorUser)
            ->post("/vendor/requests/{$repairRequest->id}/bid", [
                'shop_id' => $shop->id,
                'parts_items' => [
                    [
                        'name' => 'LP8550 Backlight Driver IC',
                        'cost' => 1650,
                        'warranty_configured' => 1,
                        'has_warranty' => 1,
                        'warranty_days' => 180,
                    ],
                    [
                        'name' => 'LVDS Display Flex Harness',
                        'cost' => 850,
                        'warranty_configured' => 1,
                        'has_warranty' => 1,
                        'warranty_days' => 60,
                    ],
                    [
                        'name' => 'ESD Foil & Kapton Tape Kit',
                        'cost' => 300,
                        'warranty_configured' => 1,
                    ],
                ],
                'labor_cost' => 900,
                'estimated_days' => 3,
                'technician_note' => 'Full microscope rework and backlight rail test.',
            ])
            ->assertRedirect();

        $bid = Bid::where('repair_request_id', $repairRequest->id)
            ->where('shop_id', $shop->id)
            ->firstOrFail();

        $this->assertEquals(2800.00, (float) $bid->parts_cost);
        $this->assertEquals(900.00, (float) $bid->labor_cost);
        $this->assertEquals(3700.00, (float) $bid->proposed_cost);
        $this->assertTrue($bid->hasWarrantyCoverage());
        $this->assertSame(180, $bid->resolvedWarrantyDays());

        $initialParts = $bid->resolvedPartsItems();
        $this->assertCount(3, $initialParts);
        $this->assertTrue($initialParts[0]['has_warranty']);
        $this->assertSame(180, $initialParts[0]['warranty_days']);
        $this->assertTrue($initialParts[1]['has_warranty']);
        $this->assertSame(60, $initialParts[1]['warranty_days']);
        $this->assertFalse($initialParts[2]['has_warranty']);
        $this->assertSame(0, $initialParts[2]['warranty_days']);

        // Vendor Bidding Board & Dashboard Marketplace both render itemized per-part View/Edit payloads
        $this->actingAs($vendorUser)
            ->get('/vendor/bidding-board')
            ->assertStatus(200)
            ->assertSee('LP8550 Backlight Driver IC')
            ->assertSee('LVDS Display Flex Harness')
            ->assertSee('180')
            ->assertSee('60');

        $this->actingAs($vendorUser)
            ->get('/vendor/dashboard?tab=marketplace')
            ->assertStatus(200)
            ->assertSee('LP8550 Backlight Driver IC')
            ->assertSee('180');

        // 2. Vendor edits same Quote 1 and unchecks ALL per-part warranty checkboxes
        $this->actingAs($vendorUser)
            ->post("/vendor/requests/{$repairRequest->id}/bid", [
                'shop_id' => $shop->id,
                'parts_items' => [
                    [
                        'name' => 'LP8550 Backlight Driver IC',
                        'cost' => 1500,
                        'warranty_configured' => 1,
                        'warranty_days' => 90,
                    ],
                    [
                        'name' => 'Shielding & Thermal Pad Set',
                        'cost' => 250,
                        'warranty_configured' => 1,
                        'warranty_days' => 30,
                    ],
                ],
                'labor_cost' => 750,
                'estimated_days' => 2,
                'technician_note' => 'Parts-only bench pricing without extended warranty.',
            ])
            ->assertRedirect();

        $this->assertSame(1, Bid::where('repair_request_id', $repairRequest->id)->where('shop_id', $shop->id)->count());
        $bid->refresh();
        $this->assertEquals(1750.00, (float) $bid->parts_cost);
        $this->assertEquals(2500.00, (float) $bid->proposed_cost);
        $this->assertFalse($bid->hasWarrantyCoverage());
        $this->assertSame(0, $bid->resolvedWarrantyDays());
        $uncheckedParts = $bid->resolvedPartsItems();
        $this->assertCount(2, $uncheckedParts);
        $this->assertFalse($uncheckedParts[0]['has_warranty']);
        $this->assertSame(0, $uncheckedParts[0]['warranty_days']);
        $this->assertFalse($uncheckedParts[1]['has_warranty']);
        $this->assertSame(0, $uncheckedParts[1]['warranty_days']);

        // 3. Vendor edits again to enable 150-day warranty on Part 1 only while leaving Part 2 unchecked
        $this->actingAs($vendorUser)
            ->post("/vendor/requests/{$repairRequest->id}/bid", [
                'shop_id' => $shop->id,
                'parts_items' => [
                    [
                        'name' => 'LP8550 Backlight Driver IC',
                        'cost' => 1500,
                        'warranty_configured' => 1,
                        'has_warranty' => 1,
                        'warranty_days' => 150,
                    ],
                    [
                        'name' => 'Shielding & Thermal Pad Set',
                        'cost' => 250,
                        'warranty_configured' => 1,
                    ],
                ],
                'labor_cost' => 750,
                'estimated_days' => 2,
                'technician_note' => 'Updated with 150-day warranty on backlight driver IC.',
            ])
            ->assertRedirect();

        $bid->refresh();
        $this->assertTrue($bid->hasWarrantyCoverage());
        $this->assertSame(150, $bid->resolvedWarrantyDays());
        $mixedParts = $bid->resolvedPartsItems();
        $this->assertTrue($mixedParts[0]['has_warranty']);
        $this->assertSame(150, $mixedParts[0]['warranty_days']);
        $this->assertFalse($mixedParts[1]['has_warranty']);
        $this->assertSame(0, $mixedParts[1]['warranty_days']);

        // 4. Customer tracking view reflects per-part warranty badges & summary
        $expectedBidSummary = Bid::formatPartsWarrantySummary($mixedParts, true, 150);
        $this->actingAs($customer)
            ->get("/requests/{$repairRequest->id}/track")
            ->assertStatus(200)
            ->assertSee('LP8550 Backlight Driver IC')
            ->assertSee('Shielding &amp; Thermal Pad Set', false)
            ->assertSee('Parts Subtotal')
            ->assertSee('Bench Labor')
            ->assertSee('150d warranty')
            ->assertSee('No warranty')
            ->assertSee($expectedBidSummary)
            ->assertSee('up to 150 days');

        // 5. Customer accepts bid -> copies per-part breakdown & warranty to RepairJob
        $this->actingAs($customer)
            ->post("/requests/{$repairRequest->id}/accept-bid", [
                'bid_id' => $bid->id,
            ])
            ->assertRedirect();

        $job = RepairJob::where('repair_request_id', $repairRequest->id)->firstOrFail();
        $this->assertSame($bid->id, $job->accepted_bid_id);
        $this->assertEquals(1750.00, $job->resolvedPartsCost());
        $this->assertEquals(750.00, $job->resolvedLaborCost());
        $this->assertTrue($job->hasWarrantyCoverage());
        $this->assertSame(150, $job->resolvedWarrantyDays());
        $jobInitialParts = $job->resolvedPartsItems();
        $this->assertCount(2, $jobInitialParts);
        $this->assertSame(150, $jobInitialParts[0]['warranty_days']);
        $this->assertSame(0, $jobInitialParts[1]['warranty_days']);

        // Checkout page renders per-part warranty & summary for Escrow payment
        $this->actingAs($customer)
            ->get("/checkout/payment/{$repairRequest->id}")
            ->assertStatus(200)
            ->assertSee('LP8550 Backlight Driver IC')
            ->assertSee('150d warranty')
            ->assertSee('No warranty')
            ->assertSee($expectedBidSummary);

        // 6. Vendor submits updated Quote 2 from bench with mixed per-part warranties (120d, 45d, 0d)
        $this->actingAs($vendorUser)
            ->post("/vendor/jobs/{$job->id}/status", [
                'status' => 'WAITING_APPROVAL',
                'parts_items' => [
                    [
                        'name' => 'LP8550 Backlight Driver IC',
                        'cost' => 1500,
                        'warranty_configured' => 1,
                        'has_warranty' => 1,
                        'warranty_days' => 120,
                    ],
                    [
                        'name' => 'Boost Diode & Filter Capacitors',
                        'cost' => 600,
                        'warranty_configured' => 1,
                        'has_warranty' => 1,
                        'warranty_days' => 45,
                    ],
                    [
                        'name' => 'Display Gasket Seal Kit',
                        'cost' => 300,
                        'warranty_configured' => 1,
                    ],
                ],
                'labor_cost' => 1100,
                'diagnosis_notes' => 'Additional power IC filter caps damaged on inspection.',
            ])
            ->assertRedirect('/vendor/dashboard?tab=assigned_jobs');

        $job->refresh();
        $repairRequest->refresh();
        $this->assertSame('WAITING_APPROVAL', $job->status);
        $this->assertEquals(2400.00, (float) $job->parts_cost);
        $this->assertEquals(1100.00, (float) $job->labor_cost);
        $this->assertEquals(3500.00, (float) $job->final_quoted_amount);
        $this->assertEquals(3500.00, (float) $repairRequest->quoted_final_amount);
        $this->assertTrue($job->hasWarrantyCoverage());
        $this->assertSame(120, $job->resolvedWarrantyDays());
        $jobUpdatedParts = $job->resolvedPartsItems();
        $this->assertCount(3, $jobUpdatedParts);
        $this->assertSame(120, $jobUpdatedParts[0]['warranty_days']);
        $this->assertSame(45, $jobUpdatedParts[1]['warranty_days']);
        $this->assertFalse($jobUpdatedParts[2]['has_warranty']);
        $this->assertSame(0, $jobUpdatedParts[2]['warranty_days']);

        // Customer tracking WAITING_APPROVAL card displays per-part warranty badges & notes
        $expectedJobSummary = Bid::formatPartsWarrantySummary($jobUpdatedParts, true, 120);
        $this->actingAs($customer)
            ->get("/requests/{$repairRequest->id}/track")
            ->assertStatus(200)
            ->assertSee('Boost Diode &amp; Filter Capacitors', false)
            ->assertSee('Display Gasket Seal Kit')
            ->assertSee('120d warranty')
            ->assertSee('45d warranty')
            ->assertSee('No warranty')
            ->assertSee($expectedJobSummary)
            ->assertSee('Additional power IC filter caps damaged on inspection.');

        // 7. Customer accepts final Quote 2 and vendor completes repair -> verifies invoice line items & warranty details
        $this->actingAs($customer)
            ->post("/requests/{$repairRequest->id}/respond-final-quote", [
                'decision' => 'accept',
            ])
            ->assertRedirect(route('requests.track', $repairRequest->id));

        $this->actingAs($vendorUser)
            ->post("/vendor/jobs/{$job->id}/status", [
                'status' => 'COMPLETED',
            ])
            ->assertRedirect('/vendor/dashboard?tab=assigned_jobs');

        $invoice = DB::table('invoices')->where('repair_job_id', $job->id)->first();
        $this->assertNotNull($invoice);
        $this->assertEquals(3500.00, (float) $invoice->subtotal_amount);

        $lineItems = DB::table('invoice_line_items')->where('invoice_id', $invoice->id)->get();
        $this->assertCount(4, $lineItems); // 3 parts + 1 technician labor line
        $this->assertEquals(3500.00, round((float) $lineItems->sum('total_price'), 2));
        $this->assertTrue($lineItems->contains('title', 'Boost Diode & Filter Capacitors'));

        $icLine = $lineItems->firstWhere('title', 'LP8550 Backlight Driver IC');
        $capsLine = $lineItems->firstWhere('title', 'Boost Diode & Filter Capacitors');
        $sealLine = $lineItems->firstWhere('title', 'Display Gasket Seal Kit');
        $this->assertStringContainsString('120', (string) ($icLine->description ?? ''));
        $this->assertStringContainsString('45', (string) ($capsLine->description ?? ''));
        $this->assertNotNull($sealLine);

        $warranty = DB::table('warranties')->where('repair_job_id', $job->id)->first();
        $this->assertNotNull($warranty);
        $this->assertSame(120, (int) $warranty->duration_days);
        $this->assertStringContainsString('LP8550 Backlight Driver IC', (string) $warranty->details);
        $this->assertStringContainsString('120', (string) $warranty->details);
        $this->assertStringContainsString('Boost Diode & Filter Capacitors', (string) $warranty->details);
        $this->assertStringContainsString('45', (string) $warranty->details);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\FinancialLedger;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\User;
use App\Models\VendorProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ============================================================================
 * SPRINT 1 FULL DEMO READINESS & ZERO-DEAD-BUTTON INTEGRATION TEST SUITE
 * ============================================================================
 * Author: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Verifies:
 *   1. Unified Admin Console (/admin) & R11 Vendor Verification Desk (Approve / Reject / Featured).
 *   2. End-to-End Customer -> Vendor Bid -> Customer Accept -> Quote 2 -> Completed -> R16 Ledger.
 *   3. Customer Requests History Hub (/requests & /orders).
 *   4. Role-aware Demo Logins (?role=customer|vendor|delivery|admin).
 *   5. Every Sprint 2–4 & auxiliary button/form routes cleanly to "Coming Soon" (HTTP 200, zero 500s).
 *   6. Complete DatabaseSeeder (including SprintOneDemoSeeder) preserves all dual-address & branch invariants.
 * ============================================================================
 */
class SprintOneDemoReadinessTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function admin_can_audit_reject_and_approve_vendor_applications_end_to_end()
    {
        $admin = User::factory()->create(['role' => 'admin']);

        // 1. Guest submits Vendor Onboarding application
        $this->post('/vendor/onboarding', [
            'owner_name' => 'Nabil Hasan',
            'owner_email' => 'nabil.lab@meramot.test',
            'owner_phone' => '+8801711998877',
            'owner_password' => 'secret123',
            'shop_name' => 'Orbit Logic Rework',
            'business_license_no' => 'TRAD/DNCC/2026-5501',
            'nid_number' => '1993112233445',
            'gps_address' => 'Mirpur 10 Roundabout, Dhaka',
            'same_as_gps_location' => '0',
            'shop_address' => 'Floor 3, Suite 305, Shah Ali Plaza, Mirpur 10, Dhaka',
            'shop_latitude' => 23.8069,
            'shop_longitude' => 90.3687,
            'city' => 'Dhaka',
            'area' => 'Mirpur',
            'categories' => ['Laptop', 'Phone'],
        ])->assertRedirect(route('vendor.onboarding'));

        $profile = VendorProfile::where('shop_name', 'Orbit Logic Rework')->firstOrFail();
        $shop = RepairShop::where('vendor_profile_id', $profile->id)->firstOrFail();

        $this->assertEquals('pending', $profile->verification_status);
        $this->assertFalse($shop->is_public);

        // Hidden from public discovery while pending
        $this->get('/shops/explore?q=Orbit')->assertStatus(200)->assertDontSee('Orbit Logic Rework');

        // 2. Admin visits /admin and /admin/vendors/pending
        $this->actingAs($admin)->get('/admin')->assertStatus(200)->assertSee('Meramot Platform Admin Console');
        $this->actingAs($admin)->get('/admin/vendors/pending')
            ->assertStatus(200)
            ->assertSee('Orbit Logic Rework')
            ->assertSee('TRAD/DNCC/2026-5501');

        // 3. Admin rejects first with remediation reason
        $this->actingAs($admin)->post(route('admin.vendors.reject', $profile->id), [
            'rejection_reason' => 'Trade license scan blurry; please re-verify serial.',
        ])->assertRedirect();

        $profile->refresh();
        $this->assertEquals('rejected', $profile->verification_status);
        $this->assertEquals('Trade license scan blurry; please re-verify serial.', $profile->rejection_reason);

        // Vendor sees rejection reason on onboarding status banner
        $this->actingAs($profile->user)->get('/vendor/onboarding')
            ->assertStatus(200)
            ->assertSee('Trade license scan blurry; please re-verify serial.');

        // 4. Admin approves vendor -> storefront becomes public & searchable
        $this->actingAs($admin)->post(route('admin.vendors.approve', $profile->id))->assertRedirect();

        $profile->refresh();
        $shop->refresh();
        $this->assertEquals('approved', $profile->verification_status);
        $this->assertTrue($shop->is_public);

        $this->get('/shops/explore?q=Orbit')->assertStatus(200)->assertSee('Orbit Logic Rework');

        // 5. Admin toggles Featured status
        $this->actingAs($admin)->post(route('admin.vendors.toggle-featured', $shop->id))->assertRedirect();
        $this->assertTrue($shop->fresh()->is_featured);
    }

    /** @test */
    public function full_marketplace_request_bid_quote2_and_completed_ledger_settlement_works_end_to_end()
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $vendorUser = User::factory()->create(['role' => 'vendor']);

        $vendorProfile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Apex Board Lab',
            'brand_name' => 'Apex Board Lab',
            'is_branched' => false,
            'business_license_no' => 'TRAD/BD/100200',
            'nid_number' => '1991000222333',
            'verification_status' => 'approved',
            'gps_address' => 'Banani Road 11, Dhaka',
            'same_as_gps_location' => true,
            'shop_address' => 'Banani Road 11, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'shop_latitude' => 23.7937,
            'shop_longitude' => 90.4066,
            'categories' => ['Laptop'],
        ]);

        $shop = RepairShop::factory()->create([
            'vendor_profile_id' => $vendorProfile->id,
            'name' => 'Apex Board Lab',
            'slug' => 'apex-board-lab',
            'is_branch' => false,
            'branch_name' => null,
            'gps_address' => 'Banani Road 11, Dhaka',
            'same_as_gps_location' => true,
            'address' => 'Banani Road 11, Dhaka',
            'is_public' => true,
            'is_active' => true,
        ]);

        // 1. Customer submits Marketplace Repair Request
        $this->actingAs($customer)->post('/requests', [
            'device_type' => 'Laptop',
            'brand' => 'ASUS',
            'model_name' => 'ROG Zephyrus G14',
            'issue_category' => 'Motherboard / IC',
            'issue_title' => 'ASUS ROG Zephyrus G14 — No Power After Surge',
            'issue_description' => 'No power after power surge; charging LED does not light up at all.',
            'preferred_service_type' => 'pickup_dropoff',
            'pickup_address' => 'House 10, Road 11, Banani, Dhaka',
            'area' => 'Banani',
            'city' => 'Dhaka',
        ])->assertRedirect();

        $repairRequest = RepairRequest::latest('id')->firstOrFail();

        // Customer sees request in /requests hub
        $this->actingAs($customer)->get('/requests')
            ->assertStatus(200)
            ->assertSee('ASUS ROG Zephyrus G14');

        // 2. Vendor submits real itemized Quote 1 bid via web form
        $this->actingAs($vendorUser)->post(route('vendor.requests.bid', $repairRequest->id), [
            'shop_id' => $shop->id,
            'parts_cost' => 2800,
            'labor_cost' => 1200,
            'estimated_days' => 2,
            'warranty_days' => 90,
            'technician_note' => 'USB-C PD controller IC + MOSFET rail replacement.',
        ])->assertRedirect();

        $bid = Bid::where('repair_request_id', $repairRequest->id)->firstOrFail();
        $this->assertEquals(4000.00, (float) $bid->proposed_cost);
        $this->assertEquals('BIDDING', $repairRequest->fresh()->status);

        // 3. Customer accepts Quote 1 bid -> creates RepairJob
        $this->actingAs($customer)->post(route('requests.accept-bid', $repairRequest->id), [
            'bid_id' => $bid->id,
        ])->assertRedirect();

        $job = RepairJob::where('repair_request_id', $repairRequest->id)->firstOrFail();
        $this->assertEquals($shop->id, $job->shop_id);

        // 4. Vendor submits Bench Quote 2 (WAITING_APPROVAL)
        $this->actingAs($vendorUser)->post(route('vendor.jobs.status', $job->id), [
            'status' => 'WAITING_APPROVAL',
            'final_quoted_amount' => 4500,
            'diagnosis_notes' => 'Additional shorted input capacitor replaced alongside PD controller IC.',
        ])->assertRedirect();

        $this->assertEquals('WAITING_APPROVAL', $repairRequest->fresh()->status);
        $this->assertEquals(4500.00, (float) $job->fresh()->final_quoted_amount);

        // 5. Customer approves Final Quote 2
        $this->actingAs($customer)->post(route('requests.respond-final-quote', $repairRequest->id), [
            'decision' => 'approve',
        ])->assertRedirect();

        $this->assertEquals('REPAIRING', $repairRequest->fresh()->status);

        // 6. Vendor marks RepairJob COMPLETED -> triggers automatic 10% / 90% R16 Ledger Settlement!
        $this->actingAs($vendorUser)->post(route('vendor.jobs.status', $job->id), [
            'status' => 'COMPLETED',
        ])->assertRedirect();

        $this->assertEquals('COMPLETED', $job->fresh()->status);
        $this->assertEquals('COMPLETED', $repairRequest->fresh()->status);

        $this->assertDatabaseHas('financial_ledgers', [
            'shop_id' => $shop->id,
            'entry_type' => 'CUSTOMER_PAYMENT',
            'direction' => 'CREDIT',
            'amount' => 4500.00,
        ]);
        $this->assertDatabaseHas('financial_ledgers', [
            'shop_id' => $shop->id,
            'entry_type' => 'PLATFORM_COMMISSION',
            'direction' => 'CREDIT',
            'amount' => 450.00,
        ]);
        $this->assertDatabaseHas('financial_ledgers', [
            'shop_id' => $shop->id,
            'entry_type' => 'VENDOR_EARNING',
            'direction' => 'DEBIT',
            'amount' => 4050.00,
        ]);
    }

    /** @test */
    public function demo_logins_and_later_sprint_coming_soon_endpoints_work_without_errors()
    {
        $this->seed(DatabaseSeeder::class);

        // Role-aware 1-click demo login redirects
        $this->get('/auth/demo?role=admin')->assertRedirect('/admin');
        $this->get('/auth/demo?role=vendor')->assertRedirect('/vendor/dashboard');
        $this->get('/auth/demo?role=delivery')->assertRedirect('/delivery-agent/register');
        $this->get('/auth/demo?role=customer')->assertRedirect('/');

        // Later-sprint GET pages & Coming Soon placeholders all return HTTP 200
        $getEndpoints = [
            '/delivery/jobs-queue',
            '/delivery/earnings',
            '/tracking/99999',
            '/support/rider-chat/1',
            '/vendor/request-details/1',
            '/vendor/alert-settings',
            '/admin/payouts/manage',
            '/admin/moderation',
            '/admin/audit-logs',
            '/forgot-password',
            '/profile',
            '/settings',
            '/support',
            '/coming-soon/sslcommerz-payment',
            '/unrouted-future-sprint-page',
        ];

        foreach ($getEndpoints as $uri) {
            $this->get($uri)->assertStatus(200, "Expected HTTP 200 for GET {$uri}");
        }

        // Later-sprint POST actions route cleanly to Coming Soon (HTTP 200, zero 500s)
        $firstReq = RepairRequest::firstOrFail();
        $this->post('/payment/initiate', [
            'repair_request_id' => $firstReq->id,
            'amount' => 3500,
        ])->assertStatus(200)->assertSee('Coming Soon');

        $this->post('/delivery/jobs/1/claim')->assertStatus(200)->assertSee('Coming Soon');
        $this->post('/delivery/payout/request')->assertStatus(200)->assertSee('Coming Soon');
        $this->post('/admin/payouts/1/disburse')->assertStatus(200)->assertSee('Coming Soon');
        $this->post('/admin/moderation/sanction')->assertStatus(200)->assertSee('Coming Soon');
        $this->post('/coming-soon/multi-channel-alerts')->assertStatus(200)->assertSee('Coming Soon');

        // Verify full DatabaseSeeder (including SprintOneDemoSeeder) preserves all dual-address & branch invariants
        $invalidShops = RepairShop::with('vendorProfile')->get()->filter(function (RepairShop $s) {
            if ($s->same_as_gps_location && $s->address !== $s->gps_address) {
                return true;
            }
            if ($s->is_branch !== !empty($s->branch_name)) {
                return true;
            }
            if ($s->vendorProfile && ($s->vendorProfile->is_branched !== $s->is_branch)) {
                return true;
            }
            return false;
        });

        $this->assertCount(0, $invalidShops);
    }
}

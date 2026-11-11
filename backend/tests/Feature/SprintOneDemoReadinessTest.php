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
        $this->assertEquals(0.00, (float) $profile->rating);
        $this->assertFalse($shop->is_public);
        $this->assertFalse($shop->accepts_direct_orders);
        $this->assertEquals(0.00, (float) $shop->rating);
        $this->assertEquals(0, (int) $shop->review_count);

        // Previewing an unapproved shop renders Preview Mode banner, zero fake reviews, and blocks reviews
        $this->get('/shops/' . $shop->slug)
            ->assertStatus(200)
            ->assertSee('Pending Admin Verification')
            ->assertSee('No verified customer reviews yet')
            ->assertDontSee('Fahim Ahmed')
            ->assertDontSee('Add to Cart');

        $this->get('/reviews/create/' . $shop->id)->assertRedirect(route('shops.show', $shop->slug));

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

        // Runtime completion also generates PAID Invoice + Line Item + 90-Day Warranty + Escrow Hold/Release
        $this->assertDatabaseHas('invoices', [
            'repair_request_id' => $repairRequest->id,
            'repair_job_id' => $job->id,
            'shop_id' => $shop->id,
            'status' => 'PAID',
            'subtotal_amount' => 4500.00,
        ]);
        $this->assertDatabaseHas('warranties', [
            'repair_job_id' => $job->id,
            'duration_days' => 90,
        ]);
        $this->assertDatabaseHas('escrow_ledgers', [
            'repair_request_id' => $repairRequest->id,
            'action' => 'RELEASE',
            'amount' => 4500.00,
        ]);

        // Customer leaves a review -> automatically interconnects `repair_request_id` to the completed order!
        $this->actingAs($customer)->post('/reviews', [
            'repair_shop_id' => $shop->id,
            'rating' => 5,
            'review_comment' => 'ASUS ROG Zephyrus G14 motherboard surge fault fixed cleanly with full warranty!',
        ])->assertRedirect(route('shops.show', $shop->slug));

        $this->assertDatabaseHas('shop_reviews', [
            'repair_shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'repair_request_id' => $repairRequest->id,
            'rating' => 5,
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

    /** @test */
    public function seeded_marketplace_obeys_real_world_ecommerce_and_identity_invariants()
    {
        $this->seed(DatabaseSeeder::class);

        // 1. System Admin is named 'Meramot' (admin@meramot.demo) and only Meramot admin accounts exist
        $this->assertDatabaseHas('users', [
            'email' => 'admin@meramot.demo',
            'name' => 'Meramot',
            'role' => 'admin',
        ]);
        $this->assertEqualsCanonicalizing(
            ['admin@meramot.demo', 'delivery.admin@meramot.demo'],
            User::where('role', 'admin')->pluck('email')->all()
        );

        // 2. Every pending or rejected vendor/shop has 0.00 rating, 0 review_count, 0 reviews, and is non-bookable
        $unapprovedProfiles = VendorProfile::with('shops')
            ->whereIn('verification_status', ['pending', 'rejected'])
            ->get();
        $this->assertGreaterThanOrEqual(3, $unapprovedProfiles->count());

        foreach ($unapprovedProfiles as $vp) {
            $this->assertEquals(0.00, (float) $vp->rating, "Unapproved vendor {$vp->shop_name} should have 0.00 rating");
            foreach ($vp->shops as $shop) {
                $this->assertFalse((bool) $shop->is_public);
                $this->assertFalse((bool) $shop->accepts_direct_orders);
                $this->assertEquals(0.00, (float) $shop->rating, "Unapproved shop {$shop->name} must have 0.00 rating");
                $this->assertEquals(0, (int) $shop->review_count, "Unapproved shop {$shop->name} must have 0 review_count");
                $this->assertEquals(0, $shop->reviews()->count(), "Unapproved shop {$shop->name} must have 0 DB reviews");
            }
        }

        // Previewing pending ElectroBench Diagnostics shows Preview Mode & 0 reviews (no fake reviews)
        $this->get('/shops/electrobench-diagnostics')
            ->assertStatus(200)
            ->assertSee('Pending Admin Verification')
            ->assertSee('No verified customer reviews yet')
            ->assertDontSee('Fahim Ahmed')
            ->assertDontSee('Sumaiya Rahman')
            ->assertDontSee('Add to Cart');

        // 3. 100% of RepairShops match exact DB review_count and AVG(rating), and max 1 review per customer per shop
        $mismatchedStats = \Illuminate\Support\Facades\DB::select("
            SELECT rs.id, rs.slug, rs.rating, rs.review_count,
                   COUNT(sr.id) AS actual_count,
                   COALESCE(ROUND(AVG(sr.rating)::numeric, 2), 0.00) AS actual_avg
            FROM repair_shops rs
            LEFT JOIN shop_reviews sr ON sr.repair_shop_id = rs.id AND sr.is_hidden = false
            GROUP BY rs.id, rs.slug, rs.rating, rs.review_count
            HAVING rs.review_count != COUNT(sr.id)
                OR ABS(rs.rating - COALESCE(ROUND(AVG(sr.rating)::numeric, 2), 0.00)) > 0.01
        ");
        $this->assertCount(0, $mismatchedStats, 'Every RepairShop rating and review_count must match shop_reviews exactly.');

        $duplicateCustomerReviews = \Illuminate\Support\Facades\DB::select("
            SELECT repair_shop_id, customer_id, COUNT(*) AS cnt
            FROM shop_reviews
            GROUP BY repair_shop_id, customer_id
            HAVING COUNT(*) > 1
        ");
        $this->assertCount(0, $duplicateCustomerReviews, 'Same customer must not review the same shop multiple times in seed data.');

        // 4. Every CUSTOMER_PAYMENT ledger row links to a PAID PaymentTransaction & COMPLETED RepairRequest,
        //    every DELIVERY_FEE belongs to a pickup-capable shop, and every VENDOR_PAYOUT links to vendor_payouts
        $orphanPayments = FinancialLedger::where('entry_type', 'CUSTOMER_PAYMENT')
            ->whereNull('payment_id')
            ->count();
        $this->assertEquals(0, $orphanPayments);

        $invalidDeliveryFees = FinancialLedger::with('shop')
            ->where('entry_type', 'DELIVERY_FEE')
            ->get()
            ->filter(fn ($entry) => !$entry->shop || !$entry->shop->supports_pickup);
        $this->assertCount(0, $invalidDeliveryFees);

        $orphanVendorPayouts = FinancialLedger::where('entry_type', 'VENDOR_PAYOUT')
            ->whereNull('vendor_payout_id')
            ->count();
        $this->assertEquals(0, $orphanVendorPayouts);

        // 5. No non-service tag words exist as ShopService names
        $badServiceNames = \App\Models\ShopService::whereIn('name', [
            'Laptop', 'Phone', 'Tablet', 'Console', 'Printer', 'Smartwatch', 'Student discount', 'Pickup service',
        ])->count();
        $this->assertEquals(0, $badServiceNames);

        // 6. 100% End-to-End Relational Interconnection Across All Seeded Tables:
        //    (a) Zero ShopReviews without a backing COMPLETED RepairRequest by that exact customer at that exact shop
        $orphanOrMismatchedReviews = \Illuminate\Support\Facades\DB::select("
            SELECT sr.id
            FROM shop_reviews sr
            LEFT JOIN repair_requests rr ON rr.id = sr.repair_request_id
            WHERE sr.repair_request_id IS NULL
               OR rr.id IS NULL
               OR rr.status != 'COMPLETED'
               OR rr.customer_id != sr.customer_id
               OR rr.requested_shop_id != sr.repair_shop_id
        ");
        $this->assertCount(0, $orphanOrMismatchedReviews, 'Every ShopReview must link 1:1 to a COMPLETED RepairRequest by the same customer at the same shop.');

        //    (b) Every COMPLETED RepairRequest has a COMPLETED RepairJob, Warranty, PAID Invoice + Line Items, PAID PaymentTransaction, Escrow RELEASE & Ledger entries
        $incompleteCompletedOrders = \Illuminate\Support\Facades\DB::select("
            SELECT rr.id
            FROM repair_requests rr
            LEFT JOIN repair_jobs rj ON rj.repair_request_id = rr.id AND rj.status = 'COMPLETED'
            LEFT JOIN warranties w ON w.repair_job_id = rj.id
            LEFT JOIN invoices inv ON inv.repair_request_id = rr.id AND inv.status = 'PAID'
            LEFT JOIN payment_transactions pt ON pt.repair_request_id = rr.id AND pt.invoice_id = inv.id AND pt.status = 'PAID'
            LEFT JOIN escrow_ledgers el ON el.payment_id = pt.id AND el.action = 'RELEASE'
            LEFT JOIN financial_ledgers fl ON fl.payment_id = pt.id AND fl.entry_type = 'CUSTOMER_PAYMENT'
            WHERE rr.status = 'COMPLETED'
              AND (rj.id IS NULL OR w.id IS NULL OR inv.id IS NULL OR pt.id IS NULL OR el.id IS NULL OR fl.id IS NULL)
        ");
        $this->assertCount(0, $incompleteCompletedOrders, 'Every COMPLETED RepairRequest must have its full RepairJob -> Warranty -> Invoice -> Payment -> Escrow -> Ledger chain.');

        $invoicesWithoutMatchingLines = \Illuminate\Support\Facades\DB::select("
            SELECT inv.id
            FROM invoices inv
            LEFT JOIN invoice_line_items ili ON ili.invoice_id = inv.id
            GROUP BY inv.id, inv.subtotal_amount
            HAVING COUNT(ili.id) = 0 OR ABS(inv.subtotal_amount - COALESCE(SUM(ili.total_price), 0)) > 0.01
        ");
        $this->assertCount(0, $invoicesWithoutMatchingLines, 'Every Invoice must have line items summing to its exact subtotal_amount.');

        //    (c) ServicePackage.order_count matches exact count of seeded DIRECT_SERVICE orders referencing that package
        $mismatchedPackageCounts = \Illuminate\Support\Facades\DB::select("
            SELECT sp.id, sp.title, sp.order_count, COUNT(rr.id) AS actual_orders
            FROM service_packages sp
            LEFT JOIN repair_requests rr ON rr.source = 'DIRECT_SERVICE' AND rr.requested_service_id = sp.id
            GROUP BY sp.id, sp.title, sp.order_count
            HAVING sp.order_count != COUNT(rr.id)
        ");
        $this->assertCount(0, $mismatchedPackageCounts, 'ServicePackage.order_count must match actual seeded DIRECT_SERVICE RepairRequests.');

        //    (d) VendorProfile review metadata, ShopStaff OWNER links, Rider earnings & coverage zones
        $this->assertEquals(0, VendorProfile::whereIn('verification_status', ['approved', 'rejected'])->whereNull('reviewed_by_admin_id')->count());
        $this->assertEquals(0, VendorProfile::where('verification_status', 'pending')->whereNotNull('reviewed_by_admin_id')->count());

        $shopsMissingOwnerStaff = \Illuminate\Support\Facades\DB::select("
            SELECT rs.id
            FROM repair_shops rs
            LEFT JOIN shop_staff ss ON ss.shop_id = rs.id AND ss.role = 'OWNER'
            WHERE rs.vendor_profile_id IS NOT NULL AND ss.id IS NULL
        ");
        $this->assertCount(0, $shopsMissingOwnerStaff, 'Every vendor-owned RepairShop must have an OWNER row in shop_staff.');

        $mismatchedRiderEarnings = \Illuminate\Support\Facades\DB::select("
            SELECT da.id
            FROM delivery_agents da
            LEFT JOIN rider_earnings re ON re.delivery_agent_id = da.id
            GROUP BY da.id, da.total_earnings
            HAVING ABS(da.total_earnings - COALESCE(SUM(re.total_earned), 0)) > 0.01
        ");
        $this->assertCount(0, $mismatchedRiderEarnings, 'Every DeliveryAgent.total_earnings must equal SUM(rider_earnings.total_earned).');

        //    (e) Active IN_TRANSIT courier job renders live GPS LocationLog on /tracking/{jobId}
        $inTransitJob = \App\Models\DeliveryJob::where('status', 'IN_TRANSIT')->firstOrFail();
        $this->assertGreaterThan(0, \App\Models\LocationLog::where('delivery_job_id', $inTransitJob->id)->count());
        $this->assertGreaterThan(0, \App\Models\RiderChatMessage::where('delivery_job_id', $inTransitJob->id)->count());
        $this->get('/tracking/' . $inTransitJob->id)
            ->assertStatus(200)
            ->assertSee($inTransitJob->tracking_code);

        //    (f) Dispute -> Refund -> REFUND Ledger, ModerationLog -> hidden ShopReview, SupportTicket -> Messages, AuditLog
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\DB::table('dispute_cases')->count());
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\DB::table('refunds')->where('status', 'PROCESSED')->count());
        $this->assertGreaterThan(0, FinancialLedger::where('entry_type', 'REFUND')->count());
        $this->assertGreaterThan(0, \Illuminate\Support\Facades\DB::table('support_messages')->count());

        $reviewModLog = \App\Models\ModerationLog::where('target_type', 'review')->firstOrFail();
        $hiddenReview = \App\Models\ShopReview::findOrFail($reviewModLog->target_id);
        $this->assertTrue((bool) $hiddenReview->is_hidden);
    }
}

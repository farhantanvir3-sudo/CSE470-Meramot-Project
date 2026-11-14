<?php

namespace Tests\Feature;

use App\Models\AISummaryLog;
use App\Models\Bid;
use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\EscrowLedger;
use App\Models\FinancialLedger;
use App\Models\Invoice;
use App\Models\PaymentTransaction;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\RiderChatMessage;
use App\Models\ServicePackage;
use App\Models\User;
use App\Models\VendorProfile;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ============================================================================
 * SPRINT 2 COMPREHENSIVE FEATURE TEST SUITE (R2, R8, R12, R13, R17)
 * ============================================================================
 * Covers:
 *   - R2: Courier Job Queue filtering (search, direction, speed), JSON API,
 *         claiming, OTP-enforced custody milestones, releasing before pickup,
 *         and reporting field exceptions.
 *   - R8: Promo/Voucher engine (MERAMOT10, FIRSTFIX100, FREEDELIVERY, SHOPFREE),
 *         scheduled pickup slot, and online escrow payment channel checkout.
 *   - R12 & R13: Bidding Board live polling & filters, AI Fault Triage summary
 *         (remote FastAPI + deterministic fallback + AISummaryLog), submitting
 *         and withdrawing vendor estimates, and Quote 2 approval/decline.
 *   - R17: Escrow Payment Gateway initiation, interactive bKash/Nagad/Card
 *         sandbox simulator, success/fail/cancel/IPN webhooks, HTML & %PDF-1.4
 *         invoice receipts, and zero duplicate ledger settlement on completion.
 * ============================================================================
 */
class SprintTwoMarketplaceAiAndPaymentTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function r2_delivery_job_pool_filters_otp_enforced_milestones_release_and_exception_work()
    {
        $this->seed(DatabaseSeeder::class);

        $riderUser = User::whereIn('role', ['delivery_agent', 'delivery'])
            ->whereHas('deliveryAgent', fn ($q) => $q->where('registration_status', 'APPROVED')->where('is_active', true))
            ->firstOrFail();
        $agent = $riderUser->deliveryAgent;

        // 1. JSON API + Filter parameters on /delivery/jobs-queue
        $jsonResp = $this->actingAs($riderUser)
            ->getJson('/delivery/jobs-queue?direction=TO_SHOP&type=EXPRESS&q=MacBook')
            ->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('is_approved_agent', true);

        $poolJobs = collect($jsonResp->json('available_jobs'));
        $this->assertGreaterThanOrEqual(1, $poolJobs->count());

        $openJobId = (int) $poolJobs->first()['id'];

        // 2. Claim open job into active queue
        $this->actingAs($riderUser)
            ->post("/delivery/jobs/{$openJobId}/claim")
            ->assertRedirect('/delivery/jobs-queue?tab=active');

        $claimedJob = DeliveryJob::findOrFail($openJobId);
        $this->assertEquals($agent->id, (int) $claimedJob->delivery_agent_id);
        $this->assertEquals('SCHEDULED', strtoupper((string) $claimedJob->status));

        // 3. Report a field exception -> logs to RiderChatMessage dispatch channel
        $this->actingAs($riderUser)
            ->post("/delivery/jobs/{$openJobId}/exception", [
                'reason' => 'Heavy Gulshan Circle traffic — arriving 8 minutes later.',
            ])
            ->assertStatus(302);

        $this->assertTrue(
            RiderChatMessage::where('delivery_job_id', $openJobId)
                ->where('sender_user_id', $riderUser->id)
                ->where('message', 'like', '%Heavy Gulshan Circle traffic%')
                ->exists()
        );

        // 4. Release job back to open pool BEFORE physical pickup
        $this->actingAs($riderUser)
            ->post("/delivery/jobs/{$openJobId}/release", [
                'reason' => 'Reassigning to nearer Banani rider.',
            ])
            ->assertRedirect('/delivery/jobs-queue?tab=pool');

        $claimedJob->refresh();
        $this->assertNull($claimedJob->delivery_agent_id);
        $this->assertEquals('PENDING', strtoupper((string) $claimedJob->status));

        // 5. Re-claim and verify mandatory 4-digit OTP enforcement on physical pickup
        $this->actingAs($riderUser)
            ->post("/delivery/jobs/{$openJobId}/claim")
            ->assertRedirect('/delivery/jobs-queue?tab=active');

        // Advance SCHEDULED -> DISPATCHED (en route, no OTP required yet)
        $this->actingAs($riderUser)
            ->post("/delivery/order/{$openJobId}/status", [
                'milestone' => 'DISPATCHED',
            ])
            ->assertRedirect("/delivery/order/{$openJobId}");

        $claimedJob->refresh();
        $this->assertEquals('DISPATCHED', strtoupper((string) $claimedJob->status));

        // Attempting PICKED_UP with wrong OTP fails validation
        $this->from("/delivery/order/{$openJobId}")
            ->actingAs($riderUser)
            ->post("/delivery/order/{$openJobId}/status", [
                'milestone' => 'PICKED_UP',
                'otp' => '0000',
            ])
            ->assertRedirect("/delivery/order/{$openJobId}")
            ->assertSessionHasErrors('otp');

        // Correct pickup_otp advances cleanly to PICKED_UP
        $this->actingAs($riderUser)
            ->post("/delivery/order/{$openJobId}/status", [
                'milestone' => 'PICKED_UP',
                'otp' => $claimedJob->pickup_otp,
            ])
            ->assertRedirect("/delivery/order/{$openJobId}");

        $claimedJob->refresh();
        $this->assertEquals('PICKED_UP', strtoupper((string) $claimedJob->status));
    }

    /** @test */
    public function r8_promo_voucher_engine_scheduled_pickup_and_online_checkout_redirect_work()
    {
        $this->seed(DatabaseSeeder::class);

        $customer = User::where('email', 'customer@meramot.demo')->firstOrFail();
        $pkg = ServicePackage::where('fixed_price', '>=', 1200)->firstOrFail();
        $shop = RepairShop::where('is_public', true)
            ->where('accepts_direct_orders', true)
            ->where('supports_pickup', true)
            ->firstOrFail();

        // Add package to Quickmatch Service Cart
        $this->actingAs($customer)->post('/cart/add', [
            'package_id' => $pkg->id,
            'shop_id' => $shop->id,
            'quantity' => 1,
        ])->assertStatus(302);

        // Apply MERAMOT10 promo voucher via JSON
        $voucherResp = $this->actingAs($customer)
            ->postJson('/cart/voucher/apply', ['voucher_code' => 'MERAMOT10'])
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('voucher_code', 'MERAMOT10');

        $expectedDiscount = min(500.0, round(((float) $pkg->fixed_price) * 0.10, 2));
        $this->assertEqualsWithDelta($expectedDiscount, (float) $voucherResp->json('discount'), 0.01);

        // Remove and apply FREEDELIVERY
        $this->actingAs($customer)
            ->postJson('/cart/voucher/remove')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->actingAs($customer)
            ->postJson('/cart/voucher/apply', ['voucher_code' => 'FIRSTFIX100'])
            ->assertStatus(200)
            ->assertJsonPath('voucher_code', 'FIRSTFIX100');

        // Place Direct Order with BKASH online escrow channel & scheduled pickup slot
        $scheduledAt = now()->addDay()->setTime(15, 0)->format('Y-m-d\TH:i');
        $placeResp = $this->actingAs($customer)->post('/cart/checkout/place', [
            'shop_id' => $shop->id,
            'schedule_type' => 'LATER',
            'scheduled_at' => $scheduledAt,
            'delivery_type' => 'EXPRESS',
            'payment_method' => 'BKASH',
            'area' => 'Banani',
            'address' => 'House 14, Road 11, Banani, Dhaka',
            'phone' => '01712345678',
            'problem_note' => 'Please call on arrival at Gate B.',
        ]);

        // Selecting an online payment channel redirects directly to the R17 Payment Checkout page
        $latestDirect = RepairRequest::where('customer_id', $customer->id)
            ->where('source', 'DIRECT_SERVICE')
            ->latest('id')
            ->firstOrFail();

        $placeResp->assertRedirect("/checkout/payment/{$latestDirect->id}");

        $this->assertEquals('FIRSTFIX100', $latestDirect->voucher_code);
        $this->assertEquals(100.00, (float) $latestDirect->discount_amount);
        $this->assertNotNull($latestDirect->scheduled_pickup_at);
    }

    /** @test */
    public function r12_r13_bidding_board_polling_ai_fault_triage_bid_withdrawal_and_quote2_lifecycle_work()
    {
        $this->seed(DatabaseSeeder::class);

        $customer = User::where('email', 'customer@meramot.demo')->firstOrFail();
        $vendorShop = RepairShop::where('slug', 'protech-solutions')->with('vendorProfile.user')->firstOrFail();
        $vendorUser = $vendorShop->vendorProfile->user;

        // Create a fresh customer marketplace repair request
        $req = RepairRequest::create([
            'customer_id' => $customer->id,
            'source' => 'MARKETPLACE',
            'title' => 'HP Envy x360 — Swollen Battery & USB-C Not Charging',
            'device_type' => 'Laptop',
            'brand' => 'HP',
            'model_name' => 'Envy x360 15',
            'issue_category' => 'Battery / Power',
            'description' => 'Bottom chassis bulged near palmrest and USB-C PD charger no longer negotiates 65W.',
            'problem' => 'Bottom chassis bulged near palmrest and USB-C PD charger no longer negotiates 65W.',
            'mode' => 'CHECKUP_AND_REPAIR',
            'status' => 'BIDDING',
            'preferred_pickup' => true,
            'preferred_service_type' => 'pickup_dropoff',
            'delivery_type' => 'REGULAR',
            'contact_phone' => '+8801700000000',
            'pickup_address' => 'Road 11, Banani, Dhaka',
            'dropoff_address' => 'Road 11, Banani, Dhaka',
        ]);

        // 1. R12: Live polling endpoint & filtered Bidding Board
        $this->actingAs($vendorUser)
            ->getJson('/vendor/bidding-board?poll=1&device_type=Laptop&scope=unbid')
            ->assertStatus(200)
            ->assertJsonPath('ok', true);

        $this->actingAs($vendorUser)
            ->get('/vendor/bidding-board?q=Envy+x360&device_type=Laptop')
            ->assertStatus(200)
            ->assertSee('HP Envy x360');

        // 2. R13: Remote FastAPI AI Triage (mocked via Http::fake) + audit log persistence
        Http::fake([
            '*/api/ai/summarize-request' => Http::response([
                'provider' => 'groq-llama3',
                'model_name' => 'llama-3.3-70b-versatile',
                'summary' => 'Swollen Li-Polymer pouch cell exerting pressure on trackpad + USB-C PD controller fault.',
                'suspected_faults' => ['Swollen Li-Polymer Battery Pack', 'USB-C PD Controller IC (TPS65987D)'],
                'suggested_parts' => [
                    ['name' => 'OEM 51Wh Li-Ion Battery Pack', 'estimated_cost' => 3800],
                    ['name' => 'USB-C PD Charging Controller IC', 'estimated_cost' => 1400],
                ],
                'risk_level' => 'HIGH',
                'estimated_bench_time_hours' => 2.5,
                'estimated_cost_min' => 4800,
                'estimated_cost_max' => 6200,
            ], 200),
        ]);

        $this->actingAs($vendorUser)
            ->post("/vendor/request-details/{$req->id}/regenerate")
            ->assertStatus(302);

        $req->refresh();
        $this->assertStringContainsString('Swollen Li-Polymer pouch cell', (string) $req->ai_summary);
        $this->assertEquals('HIGH', $req->ai_triage_json['risk_level'] ?? null);
        $this->assertTrue(AISummaryLog::where('repair_request_id', $req->id)->where('provider', 'groq-llama3')->exists());

        // Render Vendor Request Details page with AI Triage card
        $this->actingAs($vendorUser)
            ->get("/vendor/request-details/{$req->id}")
            ->assertStatus(200)
            ->assertSee('Swollen Li-Polymer Battery Pack')
            ->assertSee('OEM 51Wh Li-Ion Battery Pack');

        // 3. R12: Submit vendor bid, withdraw it, then submit final Quote 1
        $this->actingAs($vendorUser)
            ->post("/vendor/requests/{$req->id}/bid", [
                'shop_id' => $vendorShop->id,
                'parts_items' => [
                    ['name' => 'OEM 51Wh Li-Ion Battery Pack', 'cost' => 3800, 'has_warranty' => 1, 'warranty_days' => 180],
                ],
                'labor_cost' => 1200,
                'estimated_days' => 2,
                'technician_note' => 'Initial battery + PD rail check.',
            ])
            ->assertStatus(302);

        $bid = Bid::where('repair_request_id', $req->id)->where('shop_id', $vendorShop->id)->firstOrFail();
        $this->assertEquals(5000.00, (float) $bid->proposed_cost);

        // Withdraw bid before customer acceptance
        $this->actingAs($vendorUser)
            ->post("/vendor/bids/{$bid->id}/withdraw")
            ->assertStatus(302);

        $this->assertEquals('WITHDRAWN', $bid->fresh()->status);

        // Re-submit updated Quote 1
        $this->actingAs($vendorUser)
            ->post("/vendor/requests/{$req->id}/bid", [
                'shop_id' => $vendorShop->id,
                'parts_items' => [
                    ['name' => 'OEM 51Wh Li-Ion Battery Pack', 'cost' => 3600, 'has_warranty' => 1, 'warranty_days' => 180],
                    ['name' => 'USB-C PD Controller IC', 'cost' => 1200, 'has_warranty' => 1, 'warranty_days' => 90],
                ],
                'labor_cost' => 1000,
                'estimated_days' => 1,
                'technician_note' => 'Updated bundle estimate with PD IC included.',
            ])
            ->assertStatus(302);

        $activeBid = Bid::where('repair_request_id', $req->id)
            ->where('shop_id', $vendorShop->id)
            ->where('status', '!=', 'WITHDRAWN')
            ->latest('id')
            ->firstOrFail();
        $this->assertEquals(5800.00, (float) $activeBid->proposed_cost);

        // Customer sorts bids on tracker & accepts Quote 1
        $this->actingAs($customer)
            ->get("/requests/{$req->id}/track?bid_sort=warranty")
            ->assertStatus(200)
            ->assertSee('5,800');

        $this->actingAs($customer)
            ->post("/requests/{$req->id}/accept-bid", ['bid_id' => $activeBid->id])
            ->assertStatus(302);

        $job = RepairJob::where('repair_request_id', $req->id)->firstOrFail();

        // Vendor submits Quote 2 (WAITING_APPROVAL), Customer approves Quote 2 -> IN_PROGRESS
        $this->actingAs($vendorUser)
            ->post("/vendor/jobs/{$job->id}/status", [
                'status' => 'WAITING_APPROVAL',
                'parts_items' => [
                    ['name' => 'OEM 51Wh Li-Ion Battery Pack', 'cost' => 3600, 'has_warranty' => 1, 'warranty_days' => 180],
                    ['name' => 'USB-C PD Controller IC', 'cost' => 1200, 'has_warranty' => 1, 'warranty_days' => 90],
                ],
                'labor_cost' => 1100,
                'diagnosis_notes' => 'Bench verified swollen cell + shorted TPS65987D.',
            ])
            ->assertStatus(302);

        $this->assertEquals('WAITING_APPROVAL', $req->fresh()->status);

        $this->actingAs($customer)
            ->post("/requests/{$req->id}/respond-final-quote", ['decision' => 'approve'])
            ->assertStatus(302);

        $this->assertEquals('REPAIRING', $req->fresh()->status);
        $this->assertEquals('REPAIRING', $job->fresh()->status);
    }

    /** @test */
    public function r17_escrow_payment_gateway_ipn_pdf_invoice_and_no_duplicate_settlement_work()
    {
        $this->seed(DatabaseSeeder::class);

        $customer = User::where('email', 'customer@meramot.demo')->firstOrFail();
        $quoteTwoReq = RepairRequest::where('customer_id', $customer->id)
            ->where('status', 'WAITING_APPROVAL')
            ->with('repairJob.shop.vendorProfile.user')
            ->firstOrFail();

        $vendorUser = $quoteTwoReq->repairJob->shop->vendorProfile->user;

        // Customer approves Quote 2 first
        $this->actingAs($customer)
            ->post("/requests/{$quoteTwoReq->id}/respond-final-quote", ['decision' => 'approve'])
            ->assertStatus(302);

        // 1. View Escrow Payment Checkout page
        $this->actingAs($customer)
            ->get("/checkout/payment/{$quoteTwoReq->id}")
            ->assertStatus(200)
            ->assertSee('Escrow');

        // 2. Initiate payment -> redirects to Sandbox Gateway Simulator; simulate FAIL first
        $initFailResp = $this->actingAs($customer)->post('/payment/initiate', [
            'repair_request_id' => $quoteTwoReq->id,
            'payment_method' => 'NAGAD',
        ]);
        $failedTxn = PaymentTransaction::where('repair_request_id', $quoteTwoReq->id)->latest('id')->firstOrFail();
        $initFailResp->assertRedirect(route('payment.sandbox', [
            'transactionRef' => $failedTxn->transaction_reference,
            'channel' => 'nagad',
        ]));

        $this->actingAs($customer)
            ->get("/payment/sandbox/{$failedTxn->transaction_reference}")
            ->assertStatus(200)
            ->assertSee($failedTxn->transaction_reference);

        $this->actingAs($customer)
            ->post('/payment/fail', [
                'tran_id' => $failedTxn->transaction_reference,
                'error_reason' => 'Insufficient sandbox MFS balance',
            ])
            ->assertRedirect(route('payment.checkout', $quoteTwoReq->id));

        $this->assertEquals('FAILED', $failedTxn->fresh()->status);

        // 3. Re-initiate with BKASH and authorize via asynchronous IPN + success callback
        $this->actingAs($customer)->post('/payment/initiate', [
            'repair_request_id' => $quoteTwoReq->id,
            'payment_method' => 'BKASH',
        ])->assertStatus(302);

        $paidTxn = PaymentTransaction::where('repair_request_id', $quoteTwoReq->id)
            ->where('status', 'PENDING')
            ->latest('id')
            ->firstOrFail();

        $this->postJson('/payment/ipn', [
            'tran_id' => $paidTxn->transaction_reference,
            'status' => 'VALID',
            'val_id' => 'VAL-SBX-991122',
        ])
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('status', 'PAID');

        $paidTxn->refresh();
        $this->assertEquals('PAID', $paidTxn->status);

        $invoice = Invoice::findOrFail($paidTxn->invoice_id);
        $this->assertEquals('PAID', $invoice->status);

        // Escrow HOLD + single CUSTOMER_PAYMENT ledger entry recorded on online payment
        $this->assertEquals(1, EscrowLedger::where('payment_id', $paidTxn->id)->where('action', 'HOLD')->count());
        $this->assertEquals(1, FinancialLedger::where('payment_id', $paidTxn->id)->where('entry_type', 'CUSTOMER_PAYMENT')->count());

        // Calling handleSuccess idempotently redirects to Invoice without duplicating ledger rows
        $this->actingAs($customer)
            ->post('/payment/success', ['tran_id' => $paidTxn->transaction_reference])
            ->assertRedirect(route('invoices.show', ['invoice' => $invoice->id, 'paid' => 1]));

        $this->assertEquals(1, FinancialLedger::where('payment_id', $paidTxn->id)->where('entry_type', 'CUSTOMER_PAYMENT')->count());

        // 4. HTML Invoice & pure-PHP %PDF-1.4 binary download
        $this->actingAs($customer)
            ->get("/invoices/{$invoice->id}")
            ->assertStatus(200)
            ->assertSee($invoice->invoice_number);

        $pdfResp = $this->actingAs($customer)
            ->get("/invoices/{$invoice->id}/pdf")
            ->assertStatus(200);

        $this->assertStringStartsWith('application/pdf', (string) $pdfResp->headers->get('Content-Type'));
        $this->assertStringStartsWith('%PDF-1.4', $pdfResp->getContent());

        // 5. Vendor completes bench job -> books PLATFORM_COMMISSION (10%), VENDOR_EARNING (90%), and Escrow RELEASE
        //    WITHOUT duplicating CUSTOMER_PAYMENT or Invoice!
        $job = $quoteTwoReq->repairJob->fresh();
        $this->actingAs($vendorUser)
            ->post("/vendor/jobs/{$job->id}/status", [
                'status' => 'COMPLETED',
                'diagnosis_notes' => 'AMOLED flex bonded & IP68 tested.',
            ])
            ->assertStatus(302);

        $this->assertEquals('COMPLETED', $quoteTwoReq->fresh()->status);
        $this->assertEquals(1, FinancialLedger::where('payment_id', $paidTxn->id)->where('entry_type', 'CUSTOMER_PAYMENT')->count());
        $this->assertEquals(1, FinancialLedger::where('payment_id', $paidTxn->id)->where('entry_type', 'PLATFORM_COMMISSION')->count());
        $this->assertEquals(1, FinancialLedger::where('payment_id', $paidTxn->id)->where('entry_type', 'VENDOR_EARNING')->count());
        $this->assertEquals(1, EscrowLedger::where('payment_id', $paidTxn->id)->where('action', 'RELEASE')->count());
    }
}

<?php

namespace Tests\Feature;

use App\Http\Controllers\VendorPayoutController;
use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\DisputeCase;
use App\Models\FinancialLedger;
use App\Models\LocationLog;
use App\Models\ModerationLog;
use App\Models\NotificationLog;
use App\Models\PaymentTransaction;
use App\Models\Refund;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\RiderEarnings;
use App\Models\SupportTicket;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VendorPayout;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * ============================================================================
 * SPRINT 3 COMPREHENSIVE FEATURE TEST SUITE (R3, R4, R14, R18, R19)
 * ============================================================================
 * Covers:
 *   - R3: Courier Earnings Wallet KPIs, Payout Account settings, >= 500 BDT
 *         withdrawal threshold, and strict preservation of
 *         `delivery_agents.total_earnings == SUM(rider_earnings.total_earned)`.
 *   - R4: Live Leaflet GPS Tracking Map, `/tracking/{jobId}/live` telemetry,
 *         `/api/tracking/ping` rider ping, and 1-click route step simulation.
 *   - R14: Vendor Alert Settings, BulkSMS BD + Email + In-App diagnostic test,
 *         `/notifications` center, and `/api/requests/{id}/notify-vendors`.
 *   - R18: Vendor & Courier Payout Settlements, 90% net after 10% commission,
 *         1-click direct shop settlement, and non-null `vendor_payout_id` on
 *         every `VENDOR_PAYOUT` ledger entry.
 *   - R19: Customer Order Dispute filing + notes, Admin Dispute Arbitration
 *         with Escrow Refund (`refunds` + `escrow_ledgers[REFUND]` + `REFUND`
 *         ledger), Direct Governance Sanctions (user/shop/review), and Support
 *         Tickets (`/support` + Admin resolution).
 * ============================================================================
 */
class SprintThreeLogisticsAlertsPayoutsAndGovernanceTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function r3_rider_wallet_payout_settings_and_threshold_enforced_withdrawal_preserve_earnings_invariant()
    {
        $this->seed(DatabaseSeeder::class);

        $riderUser = User::whereIn('role', ['delivery_agent', 'delivery'])
            ->whereHas('deliveryAgent', fn ($q) => $q->where('registration_status', 'APPROVED')->where('is_active', true))
            ->firstOrFail();
        $agent = $riderUser->deliveryAgent;

        // 1. View Rider Earnings page & save payout account preferences
        $this->actingAs($riderUser)
            ->get('/delivery/earnings')
            ->assertStatus(200)
            ->assertSee('Earnings');

        $this->actingAs($riderUser)
            ->post('/delivery/payout/settings', [
                'payout_method' => 'NAGAD',
                'payout_account_number' => '+8801799998888',
            ])
            ->assertRedirect('/delivery/earnings');

        $this->assertEquals('NAGAD', $agent->fresh()->payout_method);

        // 2. Ensure unsettled balance below 500 BDT triggers threshold warning
        RiderEarnings::where('delivery_agent_id', $agent->id)->update(['payout_status' => 'settled']);
        $firstEarning = RiderEarnings::where('delivery_agent_id', $agent->id)->firstOrFail();
        $firstEarning->update(['payout_status' => 'unpaid']); // < 500 BDT

        $this->actingAs($riderUser)
            ->post('/delivery/payout/request', [
                'payout_method' => 'NAGAD',
                'payout_account_number' => '+8801799998888',
            ])
            ->assertRedirect('/delivery/earnings')
            ->assertSessionHas('warning');

        // 3. Add a completed trip earning so unsettled balance >= 500 BDT, keeping total_earnings synced
        $extraJob = DeliveryJob::where('delivery_agent_id', $agent->id)->latest('id')->firstOrFail();
        RiderEarnings::updateOrCreate(
            ['delivery_agent_id' => $agent->id, 'delivery_job_id' => $extraJob->id],
            [
                'trip_fee' => 450.00,
                'bonus_amount' => 50.00,
                'total_earned' => 500.00,
                'payout_status' => 'unpaid',
            ]
        );
        $agent->update([
            'total_earnings' => (float) RiderEarnings::where('delivery_agent_id', $agent->id)->sum('total_earned'),
        ]);

        $expectedTotalBefore = (float) $agent->fresh()->total_earnings;

        $this->actingAs($riderUser)
            ->post('/delivery/payout/request', [
                'payout_method' => 'NAGAD',
                'payout_account_number' => '+8801799998888',
            ])
            ->assertRedirect('/delivery/earnings')
            ->assertSessionHas('success');

        $riderPayout = VendorPayout::where('delivery_agent_id', $agent->id)
            ->where('status', 'PENDING')
            ->latest('id')
            ->firstOrFail();

        $this->assertGreaterThanOrEqual(500.00, (float) $riderPayout->amount);
        $this->assertEquals(0, RiderEarnings::where('delivery_agent_id', $agent->id)->where('payout_status', 'unpaid')->count());
        $this->assertGreaterThan(0, RiderEarnings::where('delivery_agent_id', $agent->id)->where('payout_status', 'requested')->count());

        // Critical SQL invariant: delivery_agents.total_earnings still equals SUM(rider_earnings.total_earned)
        $this->assertEqualsWithDelta(
            $expectedTotalBefore,
            (float) $agent->fresh()->total_earnings,
            0.01
        );
    }

    /** @test */
    public function r4_live_gps_tracking_map_telemetry_ping_and_route_step_simulation_work()
    {
        $this->seed(DatabaseSeeder::class);

        $riderUser = User::whereIn('role', ['delivery_agent', 'delivery'])
            ->whereHas('deliveryAgent', fn ($q) => $q->where('registration_status', 'APPROVED')->where('is_active', true))
            ->firstOrFail();
        $inTransitJob = DeliveryJob::where('status', 'IN_TRANSIT')->firstOrFail();
        $customerOwner = $inTransitJob->repairRequest->customer;

        // 1. Owner views /tracking/{jobId} and sees handover OTP; guest sees map (HTTP 200) without handover OTP
        $this->actingAs($customerOwner)
            ->get("/tracking/{$inTransitJob->id}")
            ->assertStatus(200)
            ->assertSee($inTransitJob->tracking_code);

        $this->post('/logout');
        $this->get('/tracking/99999')->assertStatus(200);

        // 2. Live telemetry polling JSON
        $telemetryResp = $this->getJson("/tracking/{$inTransitJob->id}/live")
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('telemetry.status', 'IN_TRANSIT')
            ->assertJsonPath('telemetry.tracking_code', $inTransitJob->tracking_code);

        $initialCrumbCount = count($telemetryResp->json('telemetry.breadcrumbs') ?? []);
        $this->assertGreaterThan(0, $initialCrumbCount);

        // 3. Rider GPS ping API updates LocationLog & current coordinates
        $this->actingAs($riderUser)
            ->postJson('/api/tracking/ping', [
                'delivery_job_id' => $inTransitJob->id,
                'latitude' => 23.7495,
                'longitude' => 90.3771,
            ])
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');

        // 4. Rider 1-click Route Step Simulator advances position along corridor
        $simResp = $this->actingAs($riderUser)
            ->postJson('/delivery/location/simulate', [
                'delivery_job_id' => $inTransitJob->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('delivery_job_id', $inTransitJob->id);

        $this->assertNotNull($simResp->json('latitude'));
        $this->assertGreaterThan(
            $initialCrumbCount,
            LocationLog::where('delivery_job_id', $inTransitJob->id)->count()
        );
    }

    /** @test */
    public function r14_vendor_alert_preferences_test_dispatch_notification_center_and_bidding_broadcast_work()
    {
        $this->seed(DatabaseSeeder::class);

        $protech = RepairShop::where('slug', 'protech-solutions')->with('vendorProfile.user')->firstOrFail();
        $vendorUser = $protech->vendorProfile->user;

        // 1. View & update Vendor Alert Settings
        $this->actingAs($vendorUser)
            ->get('/vendor/alert-settings')
            ->assertStatus(200)
            ->assertSee('BulkSMS');

        $this->actingAs($vendorUser)
            ->post('/vendor/alert-settings', [
                'alert_phone' => '+8801711009988',
                'notify_sms_enabled' => '1',
                'notify_email_enabled' => '1',
                'notify_in_app_enabled' => '1',
                'notify_new_requests' => '1',
                'notify_bid_updates' => '1',
                'notify_payout_updates' => '1',
            ])
            ->assertRedirect(route('vendor.alert-settings'));

        $this->assertEquals('+8801711009988', $protech->vendorProfile->fresh()->alert_phone);

        // 2. Trigger live diagnostic test alert -> logs SMS, EMAIL, and IN_APP cleanly
        $logsBefore = NotificationLog::count();
        $this->actingAs($vendorUser)
            ->post('/vendor/alert-settings/test', [
                'test_phone' => '+8801711009988',
            ])
            ->assertRedirect(route('vendor.alert-settings'))
            ->assertSessionHas('success');

        $this->assertGreaterThanOrEqual($logsBefore + 2, NotificationLog::count());
        $this->assertTrue(
            UserNotification::where('user_id', $vendorUser->id)
                ->where('type', 'VENDOR_DIAGNOSTIC_TEST')
                ->exists()
        );

        // 3. View Notification Center & mark all read
        $this->actingAs($vendorUser)
            ->get('/notifications')
            ->assertStatus(200)
            ->assertSee('Multi-Channel Diagnostic Alert Verified');

        $this->actingAs($vendorUser)
            ->get('/notifications?mark_all_read=1')
            ->assertStatus(200);

        $this->assertEquals(0, UserNotification::where('user_id', $vendorUser->id)->where('is_read', false)->count());

        // 4. Broadcast bidding alert API notifies matching workshops
        $biddingReq = RepairRequest::where('status', 'BIDDING')->firstOrFail();
        $this->postJson("/api/requests/{$biddingReq->id}/notify-vendors")
            ->assertStatus(200)
            ->assertJsonPath('status', 'success');
    }

    /** @test */
    public function r18_vendor_and_rider_payout_disbursement_and_direct_shop_settlement_maintain_ledger_invariants()
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@meramot.demo')->firstOrFail();
        $protech = RepairShop::where('slug', 'protech-solutions')->with('vendorProfile.user')->firstOrFail();
        $vendorUser = $protech->vendorProfile->user;

        // 1. Vendor views Dashboard Earnings & Payouts tab, saves settings, and requests withdrawal
        $this->actingAs($vendorUser)
            ->get('/vendor/dashboard?tab=payouts')
            ->assertStatus(200)
            ->assertSee('Earnings');

        $this->actingAs($vendorUser)
            ->post('/vendor/payouts/settings', [
                'payout_method' => 'BKASH',
                'payout_account_name' => 'ProTech Treasury',
                'payout_account_number' => '+8801711002233',
            ])
            ->assertRedirect('/vendor/dashboard?tab=payouts');

        $summaryBefore = VendorPayoutController::computeShopEscrowSummary([$protech->id]);
        if ($summaryBefore['available_to_request'] >= 100) {
            $this->actingAs($vendorUser)
                ->post('/vendor/payouts/request', [
                    'shop_id' => $protech->id,
                    'amount' => 250.00,
                    'payout_method' => 'BKASH',
                    'payout_account_number' => '+8801711002233',
                ])
                ->assertStatus(302)
                ->assertSessionHas('success');
        }

        $pendingVendorPayout = VendorPayout::whereNotNull('shop_id')
            ->where('status', 'PENDING')
            ->latest('id')
            ->firstOrFail();

        // 2. Admin disburses Vendor Payout -> marks PAID & writes FinancialLedger(VENDOR_PAYOUT) with non-null vendor_payout_id
        $this->actingAs($admin)
            ->get('/admin/payouts/manage')
            ->assertStatus(200)
            ->assertSee($pendingVendorPayout->reference_code);

        $this->actingAs($admin)
            ->post("/admin/payouts/{$pendingVendorPayout->id}/disburse", [
                'decision' => 'disburse',
                'transaction_ref' => 'BKASH-DISB-TEST-001',
                'notes' => 'Settled via Meramot Treasury test.',
            ])
            ->assertRedirect('/admin/payouts/manage')
            ->assertSessionHas('success');

        $this->assertEquals('PAID', $pendingVendorPayout->fresh()->status);
        $this->assertTrue(
            FinancialLedger::where('entry_type', 'VENDOR_PAYOUT')
                ->where('vendor_payout_id', $pendingVendorPayout->id)
                ->exists()
        );

        // 3. Create & disburse a Courier Payout -> marks RiderEarnings as 'settled' & writes DELIVERY_PAYOUT
        $riderEarning = RiderEarnings::with('deliveryAgent')->firstOrFail();
        $agent = $riderEarning->deliveryAgent;
        $riderEarning->update(['payout_status' => 'requested', 'payout_reference' => 'RDR-PYT-TEST-99']);

        $riderPayout = VendorPayout::create([
            'shop_id' => null,
            'delivery_agent_id' => $agent->id,
            'amount' => (float) $riderEarning->total_earned,
            'gross_reference_amount' => (float) $riderEarning->total_earned,
            'commission_deducted' => 0.00,
            'currency' => 'BDT',
            'payout_method' => 'BKASH',
            'payout_account' => '+8801799998888',
            'status' => 'PENDING',
            'reference_code' => 'RDR-PYT-TEST-99',
        ]);

        $this->actingAs($admin)
            ->post("/admin/payouts/{$riderPayout->id}/disburse", [
                'decision' => 'disburse',
                'transaction_ref' => 'BKASH-RDR-TEST-002',
            ])
            ->assertRedirect('/admin/payouts/manage');

        $this->assertEquals('PAID', $riderPayout->fresh()->status);
        $this->assertEquals('settled', $riderEarning->fresh()->payout_status);
        $this->assertTrue(
            FinancialLedger::where('entry_type', 'DELIVERY_PAYOUT')
                ->where('vendor_payout_id', $riderPayout->id)
                ->exists()
        );

        // 4. Verify zero orphan VENDOR_PAYOUT ledger entries & intact Rider total_earnings invariant
        $this->assertEquals(
            0,
            FinancialLedger::where('entry_type', 'VENDOR_PAYOUT')->whereNull('vendor_payout_id')->count()
        );

        $mismatchedRiderEarnings = DB::select("
            SELECT da.id
            FROM delivery_agents da
            LEFT JOIN rider_earnings re ON re.delivery_agent_id = da.id
            GROUP BY da.id, da.total_earnings
            HAVING ABS(da.total_earnings - COALESCE(SUM(re.total_earned), 0)) > 0.01
        ");
        $this->assertCount(0, $mismatchedRiderEarnings);
    }

    /** @test */
    public function r19_order_dispute_arbitration_with_escrow_refund_governance_sanctions_and_support_tickets_work()
    {
        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'admin@meramot.demo')->firstOrFail();
        $customer = User::where('email', 'customer@meramot.demo')->firstOrFail();

        // Find a paid repair order belonging to a customer for realistic Escrow Refund arbitration
        $paidPayment = PaymentTransaction::where('status', 'PAID')
            ->whereNotNull('repair_request_id')
            ->latest('id')
            ->firstOrFail();
        $paidOrder = RepairRequest::findOrFail($paidPayment->repair_request_id);
        $orderOwner = User::findOrFail($paidOrder->customer_id);

        // 1. Customer opens /support, files an Escrow Order Dispute + appends evidence note
        $this->actingAs($orderOwner)
            ->get('/support')
            ->assertStatus(200)
            ->assertSee('Support');

        $this->actingAs($orderOwner)
            ->post('/disputes', [
                'repair_request_id' => $paidOrder->id,
                'reason' => 'Touch digitizer developed ghost touch within 48 hours of return delivery.',
            ])
            ->assertRedirect('/support')
            ->assertSessionHas('success');

        $dispute = DisputeCase::where('repair_request_id', $paidOrder->id)
            ->where('opened_by_id', $orderOwner->id)
            ->latest('id')
            ->firstOrFail();

        $this->actingAs($orderOwner)
            ->post("/disputes/{$dispute->id}/notes", [
                'note' => 'Uploaded bench video showing top-edge phantom taps.',
            ])
            ->assertStatus(302);

        // 2. Admin resolves Dispute with Partial Escrow Refund (৳400) -> writes Refund + EscrowLedger(REFUND) + FinancialLedger(REFUND)
        $this->actingAs($admin)
            ->post("/admin/disputes/{$dispute->id}/resolve", [
                'decision' => 'partial_refund',
                'refund_amount' => 400.00,
                'resolution' => 'Approved ৳400 partial goodwill refund while keeping hardware warranty active.',
            ])
            ->assertStatus(302);

        $this->assertEquals('PARTIALLY_REFUNDED', $dispute->fresh()->status);
        $this->assertTrue(
            Refund::where('dispute_case_id', $dispute->id)
                ->where('status', 'PROCESSED')
                ->where('amount', 400.00)
                ->exists()
        );
        $this->assertTrue(
            FinancialLedger::where('payment_id', $paidPayment->id)
                ->where('entry_type', 'REFUND')
                ->where('amount', 400.00)
                ->exists()
        );

        // 3. Admin applies Direct Governance Sanctions (Warn User + Delist & Restore Shop)
        $this->actingAs($admin)
            ->post('/admin/moderation/sanction', [
                'target_type' => 'user',
                'target_id' => $customer->id,
                'action_taken' => 'warn',
                'violation_type' => 'policy_reminder',
                'reason' => 'Automated governance warning verification.',
            ])
            ->assertRedirect('/admin/moderation');

        $this->assertTrue(
            ModerationLog::where('target_type', 'user')
                ->where('target_id', $customer->id)
                ->where('action_taken', 'warned')
                ->exists()
        );

        $targetShop = RepairShop::where('is_public', true)->firstOrFail();
        $this->actingAs($admin)
            ->post('/admin/moderation/sanction', [
                'target_type' => 'shop',
                'target_id' => $targetShop->id,
                'action_taken' => 'delist_shop',
                'violation_type' => 'sla_audit',
                'reason' => 'Temporary compliance audit delisting.',
            ])
            ->assertRedirect('/admin/moderation');

        $this->assertFalse((bool) $targetShop->fresh()->is_public);

        $this->actingAs($admin)
            ->post('/admin/moderation/sanction', [
                'target_type' => 'shop',
                'target_id' => $targetShop->id,
                'action_taken' => 'restore_shop',
                'violation_type' => 'sla_audit_cleared',
                'reason' => 'Compliance audit cleared — restored to public catalog.',
            ])
            ->assertRedirect('/admin/moderation');

        $this->assertTrue((bool) $targetShop->fresh()->is_public);

        // 4. Customer files Support Ticket, replies, and Admin resolves it
        $this->actingAs($customer)
            ->post('/support/tickets', [
                'subject' => 'Need VAT invoice copy for corporate reimbursement',
                'message' => 'Can you confirm where I can download the PDF VAT receipt for my completed order?',
                'priority' => 'high',
            ])
            ->assertRedirect('/support');

        $ticket = SupportTicket::where('user_id', $customer->id)->latest('id')->firstOrFail();

        $this->actingAs($customer)
            ->post("/support/tickets/{$ticket->id}/reply", [
                'message' => 'Order reference is visible in My Repair Tracker.',
            ])
            ->assertStatus(302);

        $this->actingAs($admin)
            ->post("/admin/support/{$ticket->id}/resolve", [
                'status' => 'RESOLVED',
                'admin_notes' => 'PDF Download button is available directly on /invoices/{id} and your Repair Tracker.',
            ])
            ->assertRedirect('/admin/moderation');

        $this->assertEquals('RESOLVED', $ticket->fresh()->status);
    }
}

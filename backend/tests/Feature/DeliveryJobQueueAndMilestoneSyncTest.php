<?php

namespace Tests\Feature;

use App\Models\Bid;
use App\Models\DeliveryAgent;
use App\Models\DeliveryJob;
use App\Models\FinancialLedger;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\RepairShop;
use App\Models\RiderEarnings;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DeliveryJobQueueAndMilestoneSyncTest extends TestCase
{
    use RefreshDatabase;

    private function createApprovedCourier(string $name, string $companyEmail, string $nid, string $license): array
    {
        $user = User::factory()->create([
            'name' => $name,
            'email' => $companyEmail,
            'role' => 'delivery_agent',
            'is_active' => true,
        ]);

        $agent = DeliveryAgent::create([
            'user_id' => $user->id,
            'applicant_name' => $name,
            'personal_email' => str_replace('@meramotbd.com', '@gmail.com', $companyEmail),
            'company_email' => $companyEmail,
            'nid_number' => $nid,
            'license_number' => $license,
            'vehicle_type' => 'motorcycle',
            'registration_status' => 'APPROVED',
            'status' => 'AVAILABLE',
            'is_active' => true,
        ]);

        return [$user, $agent];
    }

    private function createVerifiedVendorShop(): array
    {
        $vendorUser = User::factory()->create([
            'name' => 'Workshop Owner',
            'role' => 'vendor',
            'is_active' => true,
        ]);

        $profile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'shop_name' => 'Banani Micro Soldering Lab',
            'business_license_no' => 'TRAD/DNCC/551199',
            'nid_number' => '1991223344556',
            'verification_status' => 'approved',
            'shop_address' => 'Road 11, Banani, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'is_setup_completed' => true,
        ]);

        $shop = RepairShop::factory()->create([
            'vendor_profile_id' => $profile->id,
            'name' => 'Banani Micro Soldering Lab',
            'address' => 'Road 11, Banani, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'pickup_fee' => 140.00,
            'is_active' => true,
            'is_public' => true,
        ]);

        return [$vendorUser, $profile, $shop];
    }

    /** @test */
    public function open_pickup_and_return_jobs_sync_end_to_end_across_courier_vendor_and_customer()
    {
        $customer = User::factory()->create([
            'name' => 'Tariqul Customer',
            'role' => 'customer',
            'is_active' => true,
        ]);

        [$vendorUser, $profile, $shop] = $this->createVerifiedVendorShop();
        [$riderOneUser, $riderOne] = $this->createApprovedCourier('Kamal Courier', 'kamal@meramotbd.com', '1996001122331', 'DL-KAMAL-01');
        [$riderTwoUser, $riderTwo] = $this->createApprovedCourier('Jamal Courier', 'jamal@meramotbd.com', '1996001122332', 'DL-JAMAL-02');

        // 1. Customer accepts vendor quote -> creates open unassigned TO_SHOP DeliveryJob
        $repairRequest = RepairRequest::factory()->create([
            'customer_id' => $customer->id,
            'status' => 'BIDDING',
            'pickup_address' => 'House 14, Road 4, Banani, Dhaka',
            'delivery_type' => 'REGULAR',
        ]);

        $bid = Bid::factory()->create([
            'repair_request_id' => $repairRequest->id,
            'shop_id' => $shop->id,
            'proposed_cost' => 3800.00,
            'status' => 'ACTIVE',
        ]);

        $this->actingAs($customer)
            ->post("/requests/{$repairRequest->id}/accept-bid", ['bid_id' => $bid->id])
            ->assertRedirect(route('requests.track', $repairRequest->id));

        $inboundJob = DeliveryJob::where('repair_request_id', $repairRequest->id)
            ->where('direction', 'TO_SHOP')
            ->firstOrFail();

        $this->assertNull($inboundJob->delivery_agent_id);
        $this->assertSame('PENDING', $inboundJob->status);
        $this->assertNotEmpty($inboundJob->pickup_otp);
        $this->assertNotEmpty($inboundJob->dropoff_otp);

        // Rider sees unassigned job in Delivery Job Queue
        $this->actingAs($riderOneUser)
            ->get('/delivery/jobs-queue')
            ->assertStatus(200)
            ->assertSee($inboundJob->tracking_code)
            ->assertSee('Accept Job');

        // 2. Rider 1 claims job atomically -> syncs RepairRequest to PICKUP_SCHEDULED & blocks Rider 2
        $this->actingAs($riderOneUser)
            ->post("/delivery/jobs/{$inboundJob->id}/claim")
            ->assertRedirect('/delivery/jobs-queue?tab=active');

        $inboundJob->refresh();
        $repairRequest->refresh();
        $repairJob = RepairJob::where('repair_request_id', $repairRequest->id)->firstOrFail();

        $this->assertSame($riderOne->id, $inboundJob->delivery_agent_id);
        $this->assertSame('SCHEDULED', $inboundJob->status);
        $this->assertSame('PICKUP_SCHEDULED', $repairRequest->status);
        $this->assertSame('PICKUP_SCHEDULED', $repairJob->status);
        $this->assertSame('BUSY', $riderOne->fresh()->status);

        // Second rider cannot steal already claimed job
        $this->actingAs($riderTwoUser)
            ->postJson("/delivery/jobs/{$inboundJob->id}/claim")
            ->assertStatus(422);

        // Customer tracker displays assigned courier and pickup code
        $this->actingAs($customer)
            ->get(route('requests.track', $repairRequest->id))
            ->assertStatus(200)
            ->assertSee('Pickup Scheduled')
            ->assertSee($inboundJob->pickup_otp);

        // 3. Rider starts pickup route -> rejects invalid OTP -> confirms pickup with valid pickup_otp
        $this->actingAs($riderOneUser)
            ->post("/delivery/jobs/{$inboundJob->id}/milestone", ['milestone' => 'DISPATCHED'])
            ->assertSessionHasNoErrors();
        $this->assertSame('DISPATCHED', $inboundJob->fresh()->status);

        $this->actingAs($riderOneUser)
            ->post("/delivery/jobs/{$inboundJob->id}/milestone", [
                'milestone' => 'PICKED_UP',
                'otp' => '0000',
            ])
            ->assertSessionHasErrors('otp');
        $this->assertSame('DISPATCHED', $inboundJob->fresh()->status);

        $this->actingAs($riderOneUser)
            ->post("/delivery/jobs/{$inboundJob->id}/milestone", [
                'milestone' => 'PICKED_UP',
                'otp' => $inboundJob->pickup_otp,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('PICKED_UP', $inboundJob->fresh()->status);
        $this->assertSame('PICKED_UP', $repairRequest->fresh()->status);

        // Rider progresses en route & checks device in At Shop with workshop dropoff_otp
        $this->actingAs($riderOneUser)
            ->post("/delivery/jobs/{$inboundJob->id}/milestone", ['milestone' => 'IN_TRANSIT'])
            ->assertSessionHasNoErrors();

        $this->actingAs($riderOneUser)
            ->post("/delivery/jobs/{$inboundJob->id}/milestone", [
                'milestone' => 'AT_SHOP',
                'otp' => '9999',
            ])
            ->assertSessionHasErrors('otp');

        $this->actingAs($riderOneUser)
            ->post("/delivery/jobs/{$inboundJob->id}/milestone", [
                'milestone' => 'AT_SHOP',
                'otp' => $inboundJob->dropoff_otp,
            ])
            ->assertSessionHasNoErrors();

        $inboundJob->refresh();
        $this->assertSame('DELIVERED', $inboundJob->status);
        $this->assertSame('AT_SHOP', $repairRequest->fresh()->status);
        $this->assertSame('AT_SHOP', $repairJob->fresh()->status);
        $this->assertSame('AVAILABLE', $riderOne->fresh()->status);
        $this->assertDatabaseHas('rider_earnings', [
            'delivery_agent_id' => $riderOne->id,
            'delivery_job_id' => $inboundJob->id,
        ]);

        // 4. Vendor completes bench repair & schedules return courier (`RETURN_SCHEDULED`)
        $this->actingAs($vendorUser)
            ->post(route('vendor.jobs.status', $repairJob->id), [
                'status' => 'RETURN_SCHEDULED',
            ])
            ->assertRedirect('/vendor/dashboard?tab=assigned_jobs');

        $this->assertSame('RETURN_SCHEDULED', $repairRequest->fresh()->status);
        $this->assertSame('READY_TO_RETURN', $repairJob->fresh()->status);

        $outboundJob = DeliveryJob::where('repair_request_id', $repairRequest->id)
            ->where('direction', 'TO_CUSTOMER')
            ->firstOrFail();

        $this->assertNull($outboundJob->delivery_agent_id);
        $this->assertSame('PENDING', $outboundJob->status);

        // Vendor dashboard displays return workshop release code
        $this->actingAs($vendorUser)
            ->get('/vendor/dashboard?tab=assigned_jobs')
            ->assertStatus(200)
            ->assertSee($outboundJob->pickup_otp);

        // 5. Rider Two accepts outbound return trip, verifies workshop pickup, marks Out for Delivery, confirms Delivered
        $this->actingAs($riderTwoUser)
            ->post("/delivery/jobs/{$outboundJob->id}/claim")
            ->assertRedirect('/delivery/jobs-queue?tab=active');

        $this->actingAs($riderTwoUser)
            ->post("/delivery/jobs/{$outboundJob->id}/milestone", ['milestone' => 'DISPATCHED'])
            ->assertSessionHasNoErrors();

        $this->actingAs($riderTwoUser)
            ->post("/delivery/jobs/{$outboundJob->id}/milestone", [
                'milestone' => 'PICKED_UP',
                'otp' => $outboundJob->pickup_otp,
            ])
            ->assertSessionHasNoErrors();

        $this->actingAs($riderTwoUser)
            ->post("/delivery/jobs/{$outboundJob->id}/milestone", ['milestone' => 'OUT_FOR_DELIVERY'])
            ->assertSessionHasNoErrors();

        $this->assertSame('IN_TRANSIT', $outboundJob->fresh()->status);
        $this->assertSame('RETURNING', $repairRequest->fresh()->status);
        $this->assertSame('RETURNING', $repairJob->fresh()->status);

        $this->actingAs($customer)
            ->get(route('requests.track', $repairRequest->id))
            ->assertStatus(200)
            ->assertSee('Out for Delivery')
            ->assertSee($outboundJob->dropoff_otp);

        $this->actingAs($riderTwoUser)
            ->post("/delivery/jobs/{$outboundJob->id}/milestone", [
                'milestone' => 'DELIVERED',
                'otp' => $outboundJob->dropoff_otp,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('DELIVERED', $outboundJob->fresh()->status);
        $this->assertSame('COMPLETED', $repairRequest->fresh()->status);
        $this->assertSame('COMPLETED', $repairJob->fresh()->status);
        $this->assertSame('AVAILABLE', $riderTwo->fresh()->status);

        // Verify settlement: rider earnings, invoice, warranty, and 10%/90% ledger entries created
        $this->assertEquals(
            (float) RiderEarnings::where('delivery_agent_id', $riderTwo->id)->sum('total_earned'),
            (float) $riderTwo->fresh()->total_earnings
        );
        $this->assertDatabaseHas('invoices', [
            'repair_job_id' => $repairJob->id,
            'status' => 'PAID',
        ]);
        $this->assertDatabaseHas('warranties', [
            'repair_job_id' => $repairJob->id,
        ]);
        $this->assertTrue(FinancialLedger::where('entry_type', 'PLATFORM_COMMISSION')->exists());
        $this->assertGreaterThanOrEqual(4, UserNotification::where('user_id', $customer->id)->count());
    }

    /** @test */
    public function admin_and_guest_can_view_jobs_queue_read_only_without_errors()
    {
        $admin = User::factory()->create(['role' => 'admin', 'is_active' => true]);

        $this->get('/delivery/jobs-queue')
            ->assertStatus(200)
            ->assertSee('Read-Only Queue Preview');

        $this->actingAs($admin)
            ->get('/delivery/jobs-queue')
            ->assertStatus(200)
            ->assertSee('Read-Only Queue Preview');

        $this->actingAs($admin)
            ->post('/delivery/jobs/1/claim')
            ->assertRedirect('/delivery/login');
    }
}

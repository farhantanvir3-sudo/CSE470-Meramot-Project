<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\RepairJob;
use App\Models\RepairRequest;
use App\Models\Bid;
use App\Models\User;
use App\Models\RepairShop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Feature R7: Customer Repair Request Posting & Dual-Quote Lifecycle Tests
 * Assigned: Shanid Sajjatuz Islam [23201465]
 * Merged Dual-Quote Lifecycle: Farhan Tanvir [22299179]
 */
class RepairRequestTest extends TestCase
{
    use RefreshDatabase, WithFaker;

    protected $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->customer = User::factory()->create([
            'role' => 'customer',
            'email' => 'customer@test.com',
        ]);
    }

    /** @test */
    public function it_displays_request_creation_form()
    {
        $response = $this->get('/requests/create');

        $response->assertStatus(200);
        $response->assertViewIs('requests.create');
        $response->assertSee('Post an Electronic Device Repair Request');
    }

    /** @test */
    public function it_creates_a_repair_request_successfully()
    {
        $requestData = [
            'device_type' => 'Laptop',
            'brand' => 'Dell',
            'model_name' => 'XPS 13',
            'issue_title' => 'Screen flickering',
            'issue_description' => 'The screen flickers randomly, especially when opening heavy applications.',
            'preferred_service_type' => 'pickup_dropoff',
            'pickup_address' => 'House 12, Road 5, Banani, Dhaka',
        ];

        $response = $this->actingAs($this->customer)->post('/requests', $requestData);

        $response->assertRedirect();
        $this->assertDatabaseHas('repair_requests', [
            'device_type' => 'Laptop',
            'brand' => 'Dell',
            'model_name' => 'XPS 13',
            'title' => 'Screen flickering',
            'status' => 'BIDDING',
        ]);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->actingAs($this->customer)->post('/requests', []);

        $response->assertSessionHasErrors([
            'device_type',
            'brand',
            'model_name',
            'issue_title',
            'issue_description',
            'preferred_service_type',
        ]);
    }

    /** @test */
    public function it_handles_photo_uploads()
    {
        Storage::fake('public');

        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $photo1 = UploadedFile::fake()->createWithContent('device-photo-1.png', $tinyPng);
        $photo2 = UploadedFile::fake()->createWithContent('device-photo-2.png', $tinyPng);

        $requestData = [
            'device_type' => 'Mobile Phone',
            'brand' => 'Samsung',
            'model_name' => 'Galaxy S21',
            'issue_title' => 'Cracked screen',
            'issue_description' => 'Phone dropped, screen has cracks.',
            'preferred_service_type' => 'pickup_dropoff',
            'photos' => [$photo1, $photo2],
        ];

        $this->actingAs($this->customer)->post('/requests', $requestData);

        $repairRequest = RepairRequest::latest()->first();

        $this->assertNotNull($repairRequest->photos);
        $this->assertIsArray($repairRequest->photos);
        $this->assertCount(2, $repairRequest->photos);

        Storage::disk('public')->assertExists($repairRequest->photos[0]);
        Storage::disk('public')->assertExists($repairRequest->photos[1]);
    }

    /** @test */
    public function it_displays_repair_request_tracking_page()
    {
        $repairRequest = RepairRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'BIDDING',
        ]);

        $response = $this->get(route('requests.track', $repairRequest->id));

        $response->assertStatus(200);
        $response->assertViewIs('requests.track');
        $response->assertSee('Track Your Repair Request');
        $response->assertSee($repairRequest->device_type);
    }

    /** @test */
    public function it_shows_bids_on_tracking_page()
    {
        $shop = RepairShop::factory()->create();

        $repairRequest = RepairRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'BIDDING',
        ]);

        Bid::factory()->create([
            'repair_request_id' => $repairRequest->id,
            'shop_id' => $shop->id,
            'proposed_cost' => 2500,
            'estimated_days' => 3,
            'status' => 'ACTIVE',
        ]);

        $response = $this->get(route('requests.track', $repairRequest->id));

        $response->assertSee('2500');
        $response->assertSee('3 days');
    }

    /** @test */
    public function it_transitions_status_correctly()
    {
        $repairRequest = RepairRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'PENDING',
        ]);

        $result = $repairRequest->transitionTo('BIDDING');
        $this->assertTrue($result);
        $this->assertEquals('BIDDING', $repairRequest->fresh()->status);
    }

    /** @test */
    public function it_prevents_invalid_status_transitions()
    {
        $repairRequest = RepairRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'PENDING',
        ]);

        $result = $repairRequest->transitionTo('COMPLETED');
        $this->assertFalse($result);
        $this->assertEquals('PENDING', $repairRequest->fresh()->status);
    }

    /** @test */
    public function it_accepts_a_bid_and_creates_repair_job()
    {
        $shop = RepairShop::factory()->create();

        $repairRequest = RepairRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'BIDDING',
        ]);

        $bid = Bid::factory()->create([
            'repair_request_id' => $repairRequest->id,
            'shop_id' => $shop->id,
            'proposed_cost' => 2500,
            'status' => 'ACTIVE',
        ]);

        $response = $this->actingAs($this->customer)->post("/requests/{$repairRequest->id}/accept-bid", [
            'bid_id' => $bid->id,
        ]);

        $response->assertRedirect(route('requests.track', $repairRequest->id));

        $repairRequest->refresh();
        $this->assertEquals($bid->id, $repairRequest->selected_bid_id);
        $this->assertEquals('ASSIGNED', $repairRequest->status);
        $this->assertEquals(2500, $repairRequest->quoted_final_amount);
        $this->assertDatabaseHas('repair_jobs', [
            'repair_request_id' => $repairRequest->id,
            'shop_id' => $shop->id,
            'accepted_bid_id' => $bid->id,
        ]);
        $this->assertDatabaseHas('delivery_jobs', [
            'repair_request_id' => $repairRequest->id,
            'direction' => 'TO_SHOP',
        ]);

        // Live GPS tracking route works when accessed by repair_request_id
        $this->get("/tracking/{$repairRequest->id}")->assertStatus(200);

        $bid->refresh();
        $this->assertEquals('ACCEPTED', $bid->status);
    }

    /** @test */
    public function it_transitions_quote_two_decline_to_return_scheduled()
    {
        $shop = RepairShop::factory()->create();

        $repairRequest = RepairRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'WAITING_APPROVAL',
        ]);

        RepairJob::create([
            'repair_request_id' => $repairRequest->id,
            'shop_id' => $shop->id,
            'status' => 'WAITING_APPROVAL',
            'final_quoted_amount' => 4200,
            'diagnosis_notes' => 'Additional PMIC chip replacement required.',
        ]);

        $response = $this->actingAs($this->customer)->post("/requests/{$repairRequest->id}/respond-final-quote", [
            'decision' => 'decline',
        ]);

        $response->assertRedirect(route('requests.track', $repairRequest->id));
        $this->assertEquals('RETURN_SCHEDULED', $repairRequest->fresh()->status);
        $this->assertDatabaseHas('delivery_jobs', [
            'repair_request_id' => $repairRequest->id,
            'direction' => 'TO_CUSTOMER',
        ]);
    }

    /** @test */
    public function it_can_cancel_a_repair_request()
    {
        $repairRequest = RepairRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'BIDDING',
        ]);

        $response = $this->actingAs($this->customer)->post("/requests/{$repairRequest->id}/cancel", [
            'reason' => 'Found another service provider',
        ]);

        $response->assertRedirect(route('requests.track', $repairRequest->id));

        $repairRequest->refresh();
        $this->assertEquals('CANCELLED', $repairRequest->status);
        $this->assertNotNull($repairRequest->rejected_at);
    }

    /** @test */
    public function it_provides_available_transitions_via_api()
    {
        $repairRequest = RepairRequest::factory()->create([
            'customer_id' => $this->customer->id,
            'status' => 'BIDDING',
        ]);

        $response = $this->getJson("/requests/{$repairRequest->id}/available-transitions");

        $response->assertStatus(200)
                 ->assertJson([
                     'current_status' => 'BIDDING',
                     'available_transitions' => ['ASSIGNED', 'CANCELLED'],
                 ]);
    }
}

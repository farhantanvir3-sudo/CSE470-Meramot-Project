<?php

namespace Tests\Feature;

use App\Models\DeliveryAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * ============================================================================
 * FEATURE R1: Delivery Agent Onboarding & Verification Tests
 * ============================================================================
 * Assigned Member: Md. Nafiul Islam [Student ID: 22299035]
 * Covers commits c6c0781, d71bb8f, and fdf5e6f (`rejection_reason`).
 * ============================================================================
 */
class DeliveryAgentOnboardingTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_displays_delivery_agent_application_form()
    {
        $response = $this->get('/delivery-agent/register');

        $response->assertStatus(200);
        $response->assertSee('Apply as a Delivery Agent');
        $response->assertSee('NID Number');
        $response->assertSee('License Number');
    }

    /** @test */
    public function it_submits_delivery_agent_application_as_pending()
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->postJson('/delivery-agent/register', [
            'nid_number' => '1999123456789',
            'vehicle_type' => 'motorcycle',
            'license_number' => 'DK-998877-M',
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('delivery_agents', [
            'user_id' => $user->id,
            'nid_number' => '1999123456789',
            'vehicle_type' => 'motorcycle',
            'license_number' => 'DK-998877-M',
            'registration_status' => 'PENDING',
        ]);
    }

    /** @test */
    public function it_displays_pending_applications_with_nid_and_license_on_admin_page()
    {
        $user = User::factory()->create(['name' => 'Kamal Rider']);
        DeliveryAgent::create([
            'user_id' => $user->id,
            'nid_number' => '1998765432100',
            'vehicle_type' => 'scooter',
            'license_number' => 'DK-554433-S',
            'registration_status' => 'PENDING',
            'status' => 'OFFLINE',
        ]);

        $response = $this->get('/admin/delivery-agents/pending');

        $response->assertStatus(200);
        $response->assertSee('Kamal Rider');
        $response->assertSee('1998765432100');
        $response->assertSee('DK-554433-S');
    }

    /** @test */
    public function admin_can_approve_delivery_agent_application()
    {
        $user = User::factory()->create(['role' => 'customer']);
        $agent = DeliveryAgent::create([
            'user_id' => $user->id,
            'nid_number' => '1998111222333',
            'vehicle_type' => 'motorcycle',
            'license_number' => 'DK-112233-M',
            'registration_status' => 'PENDING',
            'status' => 'OFFLINE',
        ]);

        $response = $this->post("/admin/delivery-agents/{$agent->id}/approve");

        $response->assertRedirect();
        $this->assertDatabaseHas('delivery_agents', [
            'id' => $agent->id,
            'registration_status' => 'APPROVED',
        ]);
    }

    /** @test */
    public function admin_can_reject_delivery_agent_application_with_rejection_reason()
    {
        $user = User::factory()->create(['role' => 'customer']);
        $agent = DeliveryAgent::create([
            'user_id' => $user->id,
            'nid_number' => '1998444555666',
            'vehicle_type' => 'van',
            'license_number' => 'DK-445566-V',
            'registration_status' => 'PENDING',
            'status' => 'OFFLINE',
        ]);

        $response = $this->post("/admin/delivery-agents/{$agent->id}/reject", [
            'rejection_reason' => 'License number does not match BRTA registry.',
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('delivery_agents', [
            'id' => $agent->id,
            'registration_status' => 'REJECTED',
            'rejection_reason' => 'License number does not match BRTA registry.',
        ]);
    }

    /** @test */
    public function admin_rejection_requires_rejection_reason()
    {
        $user = User::factory()->create(['role' => 'customer']);
        $agent = DeliveryAgent::create([
            'user_id' => $user->id,
            'nid_number' => '1998777888999',
            'vehicle_type' => 'bicycle',
            'license_number' => 'DK-778899-B',
            'registration_status' => 'PENDING',
            'status' => 'OFFLINE',
        ]);

        $response = $this->post("/admin/delivery-agents/{$agent->id}/reject", []);

        $response->assertSessionHasErrors(['rejection_reason']);
        $this->assertDatabaseHas('delivery_agents', [
            'id' => $agent->id,
            'registration_status' => 'PENDING',
        ]);
    }
}

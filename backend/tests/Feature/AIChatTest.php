<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Feature R10: AI-Powered Repair Diagnostic Chat Tests
 * Assigned: Shanid Sajjatuz Islam [23201465]
 */
class AIChatTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_displays_ai_chat_interface()
    {
        $response = $this->get('/ai-consultant/chat');

        $response->assertStatus(200);
        $response->assertViewIs('ai-consultant.chat');
    }

    /** @test */
    public function it_sends_message_to_ai_microservice()
    {
        Http::fake([
            '*/api/ai/chat' => Http::response([
                'status' => 'success',
                'reply' => 'Your screen issue might require a display replacement. Estimated cost: ৳5000-৳8000.',
                'estimated_cost' => '৳5000-৳8000',
                'suggested_action' => 'Visit a certified repair shop'
            ], 200)
        ]);

        $response = $this->postJson('/api/ai-consultant/message', [
            'device_category' => 'Phone',
            'user_message' => 'My iPhone screen is cracked and not responding'
        ]);

        $response->assertStatus(200);
        $response->assertJson([
            'status' => 'success',
            'reply' => 'Your screen issue might require a display replacement. Estimated cost: ৳5000-৳8000.'
        ]);
    }

    /** @test */
    public function it_validates_required_fields()
    {
        $response = $this->postJson('/api/ai-consultant/message', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['device_category', 'user_message']);
    }

    /** @test */
    public function it_validates_device_category_is_string()
    {
        $response = $this->postJson('/api/ai-consultant/message', [
            'device_category' => 123,
            'user_message' => 'Screen broken'
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('device_category');
    }

    /** @test */
    public function it_validates_user_message_is_string()
    {
        $response = $this->postJson('/api/ai-consultant/message', [
            'device_category' => 'Phone',
            'user_message' => ['not a string']
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('user_message');
    }

    /** @test */
    public function it_handles_ai_service_unavailable()
    {
        Http::fake([
            '*/api/ai/chat' => Http::response([], 503)
        ]);

        $response = $this->postJson('/api/ai-consultant/message', [
            'device_category' => 'Laptop',
            'user_message' => 'Laptop overheating'
        ]);

        $response->assertStatus(500);
        $response->assertJson([
            'status' => 'error'
        ]);
    }

    /** @test */
    public function it_handles_network_timeout()
    {
        Http::fake(function () {
            throw new \Exception('Connection timeout');
        });

        $response = $this->postJson('/api/ai-consultant/message', [
            'device_category' => 'Tablet',
            'user_message' => 'Screen not turning on'
        ]);

        $response->assertStatus(503);
        $response->assertJson([
            'status' => 'error'
        ]);
    }

    /** @test */
    public function it_forwards_correct_data_to_ai_microservice()
    {
        Http::fake([
            '*/api/ai/chat' => Http::response([
                'status' => 'success',
                'reply' => 'Thermal paste replacement recommended'
            ], 200)
        ]);

        $this->postJson('/api/ai-consultant/message', [
            'device_category' => 'Laptop',
            'user_message' => 'Overheating during gaming'
        ]);

        Http::assertSent(function ($request) {
            return $request['device_category'] === 'Laptop' &&
                   $request['user_message'] === 'Overheating during gaming';
        });
    }
}

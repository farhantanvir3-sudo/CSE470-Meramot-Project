<?php

namespace Tests\Feature;

use App\Models\RepairShop;
use App\Models\ShopReview;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature R9: Vendor Rating & Review System Tests
 * Assigned: Shanid Sajjatuz Islam [23201465]
 */
class ReviewTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_displays_review_form_for_shop()
    {
        $shop = RepairShop::factory()->create();

        $response = $this->get("/reviews/create/{$shop->id}");

        $response->assertStatus(200);
        $response->assertViewIs('reviews.write-review');
        $response->assertViewHas('shop');
    }

    /** @test */
    public function it_stores_verified_review_successfully()
    {
        $user = User::factory()->create(['role' => 'customer']);
        $shop = RepairShop::factory()->create(['rating' => 4.0]);

        $completedOrder = \App\Models\RepairRequest::create([
            'customer_id' => $user->id,
            'requested_shop_id' => $shop->id,
            'title' => 'OLED Screen Replacement',
            'device_type' => 'Phone',
            'brand' => 'Apple',
            'model_name' => 'iPhone 15 Pro',
            'issue_category' => 'Screen Issue',
            'problem' => 'Cracked display panel',
            'preferred_service_type' => 'walk_in',
            'status' => 'COMPLETED',
        ]);

        // 1. Submitting from Past Orders (`repair_request_id`) marks review as Verified Repair + records service_provided
        $response = $this->actingAs($user)->post('/reviews', [
            'repair_shop_id' => $shop->id,
            'repair_request_id' => $completedOrder->id,
            'from_past_orders' => 1,
            'service_provided' => 'Screen Issue — OLED Screen Replacement',
            'rating' => 5,
            'review_comment' => 'Excellent service! Very professional and quick repair.',
        ]);

        $response->assertRedirect(route('shops.show', $shop->slug));
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('shop_reviews', [
            'repair_shop_id' => $shop->id,
            'customer_id' => $user->id,
            'repair_request_id' => $completedOrder->id,
            'service_provided' => 'Screen Issue — OLED Screen Replacement',
            'rating' => 5,
            'is_verified_purchase' => true,
        ]);

        // 2. Submitting directly without a past order is NOT marked as a Verified Repair Review
        $user2 = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user2)->post('/reviews', [
            'repair_shop_id' => $shop->id,
            'rating' => 4,
            'review_comment' => 'Walk-in inquiry experience was helpful and polite.',
        ])->assertRedirect(route('shops.show', $shop->slug));

        $this->assertDatabaseHas('shop_reviews', [
            'repair_shop_id' => $shop->id,
            'customer_id' => $user2->id,
            'rating' => 4,
            'is_verified_purchase' => false,
        ]);
    }

    /** @test */
    public function it_validates_rating_between_1_and_5()
    {
        $user = User::factory()->create(['role' => 'customer']);
        $shop = RepairShop::factory()->create();

        $response = $this->actingAs($user)->post('/reviews', [
            'repair_shop_id' => $shop->id,
            'rating' => 6, // Invalid rating
            'review_comment' => 'Good service'
        ]);

        $response->assertSessionHasErrors('rating');
    }

    /** @test */
    public function it_requires_minimum_comment_length()
    {
        $user = User::factory()->create(['role' => 'customer']);
        $shop = RepairShop::factory()->create();

        $response = $this->actingAs($user)->post('/reviews', [
            'repair_shop_id' => $shop->id,
            'rating' => 4,
            'review_comment' => 'Good' // Too short
        ]);

        $response->assertSessionHasErrors('review_comment');
    }

    /** @test */
    public function it_updates_shop_average_rating_after_review()
    {
        $user = User::factory()->create(['role' => 'customer']);
        $shop = RepairShop::factory()->create(['rating' => 4.0]);
        
        // Existing review with rating 4
        ShopReview::factory()->create([
            'repair_shop_id' => $shop->id,
            'rating' => 4
        ]);

        // New review with rating 5
        $this->actingAs($user)->post('/reviews', [
            'repair_shop_id' => $shop->id,
            'rating' => 5,
            'review_comment' => 'Excellent repair service, highly recommended!'
        ]);

        $shop->refresh();
        
        // Average should be (4 + 5) / 2 = 4.5
        $this->assertEquals(4.5, $shop->rating);
    }

    /** @test */
    public function it_validates_shop_exists_before_review()
    {
        $user = User::factory()->create(['role' => 'customer']);

        $response = $this->actingAs($user)->post('/reviews', [
            'repair_shop_id' => 999, // Non-existent shop
            'rating' => 5,
            'review_comment' => 'Great service'
        ]);

        $response->assertSessionHasErrors('repair_shop_id');
    }

    /** @test */
    public function it_enforces_maximum_comment_length()
    {
        $user = User::factory()->create(['role' => 'customer']);
        $shop = RepairShop::factory()->create();

        $response = $this->actingAs($user)->post('/reviews', [
            'repair_shop_id' => $shop->id,
            'rating' => 5,
            'review_comment' => str_repeat('a', 1001) // Over 1000 chars
        ]);

        $response->assertSessionHasErrors('review_comment');
    }

    /** @test */
    public function it_pins_users_own_reviews_on_top_and_allows_editing_and_deleting_own_reviews()
    {
        $me = User::factory()->create(['name' => 'My Customer Account', 'role' => 'customer']);
        $other = User::factory()->create(['name' => 'Another Reviewer', 'role' => 'customer']);
        $shop = RepairShop::factory()->create(['name' => 'Precision Solder Lab']);

        // Older review by me, newer review by another customer
        $myReview = ShopReview::factory()->create([
            'repair_shop_id' => $shop->id,
            'customer_id' => $me->id,
            'rating' => 4,
            'service_provided' => 'Battery Replacement',
            'review_comment' => 'My own older review that should be pinned first.',
            'is_verified_purchase' => true,
            'created_at' => now()->subDays(5),
        ]);

        $otherReview = ShopReview::factory()->create([
            'repair_shop_id' => $shop->id,
            'customer_id' => $other->id,
            'rating' => 5,
            'service_provided' => 'Logic Board Micro-Soldering',
            'review_comment' => 'Other customer newer review that appears below mine.',
            'is_verified_purchase' => true,
            'created_at' => now()->subHour(),
        ]);

        // 1. Viewing shop as $me shows $me's review BEFORE $other's review
        $page = $this->actingAs($me)->get(route('shops.show', $shop->slug));
        $page->assertStatus(200);
        $page->assertSeeInOrder([
            'My own older review that should be pinned first.',
            'Other customer newer review that appears below mine.',
        ]);
        $page->assertSee('Service Provided: Battery Replacement');

        // 2. Editing own review updates rating, service_provided, comment, and recalculates shop average
        $this->actingAs($me)->put("/reviews/{$myReview->id}", [
            'rating' => 5,
            'service_provided' => 'MacBook Battery Swap',
            'review_comment' => 'Updated my review to 5 stars after 1 week battery test!',
        ])->assertRedirect(route('shops.show', $shop->slug));

        $this->assertDatabaseHas('shop_reviews', [
            'id' => $myReview->id,
            'rating' => 5,
            'service_provided' => 'MacBook Battery Swap',
        ]);
        $this->assertEquals(5.0, (float) $shop->fresh()->rating);

        // 3. Cannot delete another user's review
        $this->actingAs($me)->delete("/reviews/{$otherReview->id}")->assertStatus(403);

        // 4. Deleting own review succeeds and updates shop review_count
        $this->actingAs($me)->delete("/reviews/{$myReview->id}")
            ->assertRedirect(route('shops.show', $shop->slug));

        $this->assertDatabaseMissing('shop_reviews', ['id' => $myReview->id]);
        $this->assertEquals(1, (int) $shop->fresh()->review_count);
    }
}

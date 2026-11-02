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

        $response = $this->actingAs($user)->post('/reviews', [
            'repair_shop_id' => $shop->id,
            'rating' => 5,
            'review_comment' => 'Excellent service! Very professional and quick repair.'
        ]);

        $response->assertRedirect(route('shops.show', $shop->slug));
        $response->assertSessionHas('success');
        
        $this->assertDatabaseHas('shop_reviews', [
            'repair_shop_id' => $shop->id,
            'customer_id' => $user->id,
            'rating' => 5,
            'is_verified_purchase' => true
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
}

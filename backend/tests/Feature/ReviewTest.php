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

        // Unauthenticated guests must be redirected to login
        $this->get("/reviews/create/{$shop->id}")->assertRedirect(route('login'));

        $user = User::factory()->create(['role' => 'customer']);
        $response = $this->actingAs($user)->get("/reviews/create/{$shop->id}");

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

        // 1. Viewing shop as $me shows $me's review BEFORE $other's review + prominent Edit/Delete buttons
        $page = $this->actingAs($me)->get(route('shops.show', $shop->slug));
        $page->assertStatus(200);
        $page->assertSeeInOrder([
            'My own older review that should be pinned first.',
            'Other customer newer review that appears below mine.',
        ]);
        $page->assertSee('Service Provided: Battery Replacement');
        $page->assertSee('Edit Review');
        $page->assertSee('Delete Review');

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

        // 4. Deleting own review with redirect_back=1 works cleanly from Past Orders / Track pages
        $this->actingAs($me)->from('/requests?filter=COMPLETED')->delete("/reviews/{$myReview->id}", [
            'redirect_back' => 1,
        ])->assertRedirect('/requests?filter=COMPLETED');

        $this->assertDatabaseMissing('shop_reviews', ['id' => $myReview->id]);
        $this->assertEquals(1, (int) $shop->fresh()->review_count);
    }

    /** @test */
    public function it_blocks_guests_from_viewing_past_orders_and_isolates_customer_orders()
    {
        $customerA = User::factory()->create(['role' => 'customer']);
        $customerB = User::factory()->create(['role' => 'customer']);
        $shop = RepairShop::factory()->create(['name' => 'Privacy Check Lab']);

        \App\Models\RepairRequest::create([
            'customer_id' => $customerA->id,
            'requested_shop_id' => $shop->id,
            'title' => 'Secret Customer A Past Order Title',
            'device_type' => 'Laptop',
            'brand' => 'Lenovo',
            'model_name' => 'ThinkPad X1',
            'issue_category' => 'Display',
            'problem' => 'Backlight fuse failure',
            'preferred_service_type' => 'walk_in',
            'status' => 'COMPLETED',
        ]);

        // Unauthenticated guests must be redirected to login and never see Customer A's orders
        $this->get('/requests')->assertRedirect(route('login'));
        $this->get('/requests?filter=COMPLETED')->assertRedirect(route('login'));
        $this->get('/orders')->assertRedirect(route('login'));

        // Logged-in Customer B (with 0 orders) must NOT see Customer A's orders
        $respB = $this->actingAs($customerB)->get('/requests?filter=COMPLETED');
        $respB->assertStatus(200);
        $respB->assertSee('No repair requests match this filter yet');
        $respB->assertDontSee('Secret Customer A Past Order Title');

        // Logged-in Customer A sees their own completed order and the Verified Review CTA
        $respA = $this->actingAs($customerA)->get('/requests?filter=COMPLETED');
        $respA->assertStatus(200);
        $respA->assertSee('Secret Customer A Past Order Title');
        $respA->assertSee('Write Verified Repair Review');
    }

    /** @test */
    public function admin_is_strictly_isolated_from_customer_orders_cart_and_review_submission()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $customer = User::factory()->create(['role' => 'customer']);
        $shop = RepairShop::factory()->create();

        \App\Models\RepairRequest::create([
            'customer_id' => $customer->id,
            'requested_shop_id' => $shop->id,
            'title' => 'Customer Private Order',
            'device_type' => 'Phone',
            'brand' => 'Apple',
            'model_name' => 'iPhone 14',
            'issue_category' => 'Battery',
            'problem' => 'Fast drain',
            'preferred_service_type' => 'walk_in',
            'status' => 'COMPLETED',
        ]);

        ShopReview::create([
            'repair_shop_id' => $shop->id,
            'customer_id' => $customer->id,
            'rating' => 4,
            'review_comment' => 'Clean work by technicians.',
            'is_verified_purchase' => true,
            'service_provided' => 'Battery Replacement',
        ]);

        // Admin must never view customer orders, cart, or booking/review creation routes
        $this->actingAs($admin)->get('/requests')->assertRedirect('/admin');
        $this->actingAs($admin)->get('/orders')->assertRedirect('/admin');
        $this->actingAs($admin)->get('/requests/create')->assertRedirect('/admin');
        $this->actingAs($admin)->post('/requests', [])->assertRedirect('/admin');
        $this->actingAs($admin)->get('/cart')->assertRedirect('/admin');
        $this->actingAs($admin)->post('/cart/checkout/place', [])->assertRedirect('/admin');
        $this->actingAs($admin)->postJson('/cart/add', ['package_id' => 1])->assertStatus(403);
        $this->actingAs($admin)->get("/reviews/create/{$shop->id}")->assertRedirect('/admin');
        $this->actingAs($admin)->post('/reviews', [])->assertRedirect('/admin');

        // Shop profile hides customer booking/review CTAs for Admin and shows Admin governance tools
        $shopResponse = $this->actingAs($admin)->get("/shops/{$shop->slug}");
        $shopResponse->assertStatus(200);
        $shopResponse->assertDontSee('Review Past Order');
        $shopResponse->assertDontSee('Write Review');
        $shopResponse->assertDontSee('Post Repair Request');
        $shopResponse->assertSee('Admin Review Governance');
    }

    /** @test */
    public function admin_can_delete_offensive_review_and_ban_user_sending_email_and_bell_popup_while_allowing_login_and_past_order_viewing()
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $offender = User::factory()->create([
            'role' => 'customer',
            'status' => 'active',
            'password' => \Illuminate\Support\Facades\Hash::make('SecretPass123!'),
        ]);
        $shop = RepairShop::factory()->create([
            'rating' => 1.0,
            'review_count' => 1,
        ]);

        $pastOrder = \App\Models\RepairRequest::create([
            'customer_id' => $offender->id,
            'requested_shop_id' => $shop->id,
            'title' => 'Past Order Secret Repair',
            'device_type' => 'Laptop',
            'brand' => 'Lenovo',
            'model_name' => 'ThinkPad T14',
            'issue_category' => 'Display',
            'problem' => 'Flickering display cable',
            'preferred_service_type' => 'walk_in',
            'status' => 'COMPLETED',
        ]);

        \App\Models\RepairRequest::create([
            'customer_id' => $offender->id,
            'title' => 'Ongoing Bid Request',
            'device_type' => 'Phone',
            'brand' => 'Samsung',
            'model_name' => 'Galaxy S23',
            'issue_category' => 'Charging Port',
            'problem' => 'Loose USB-C connector',
            'preferred_service_type' => 'courier',
            'status' => 'BIDDING',
        ]);

        $review = ShopReview::create([
            'repair_shop_id' => $shop->id,
            'customer_id' => $offender->id,
            'repair_request_id' => $pastOrder->id,
            'rating' => 1,
            'review_comment' => 'Total scam and fraud workshop! Idiot technicians!',
            'is_verified_purchase' => true,
            'service_provided' => 'Display Repair',
        ]);

        // 1. Admin sees flagged offensive review in Moderation Console
        $modPage = $this->actingAs($admin)->get('/admin/moderation');
        $modPage->assertStatus(200);
        $modPage->assertSee('Total scam and fraud workshop!');
        $modPage->assertSee('Offensive Words:');

        // 2. Admin deletes review and bans user with reason
        $banReason = 'Use of offensive and abusive words in workshop review.';
        $banResp = $this->actingAs($admin)->post(route('admin.reviews.moderate', $review->id), [
            'action_type' => 'delete_and_ban',
            'reason' => $banReason,
        ]);
        $banResp->assertSessionHas('success');

        $this->assertDatabaseMissing('shop_reviews', ['id' => $review->id]);

        $offender->refresh();
        $this->assertSame('banned', $offender->status);
        $this->assertSame($banReason, $offender->ban_reason);
        $this->assertNotNull($offender->banned_at);

        $this->assertDatabaseHas('moderation_logs', [
            'target_type' => 'user',
            'target_id' => $offender->id,
            'action_taken' => 'banned_and_review_deleted',
        ]);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $offender->id,
            'type' => 'ACCOUNT_BANNED',
            'is_read' => false,
        ]);

        $this->assertDatabaseHas('notification_logs', [
            'recipient' => $offender->email,
            'channel' => 'EMAIL',
            'status' => 'SENT',
        ]);
        $this->assertDatabaseHas('notification_logs', [
            'recipient' => $offender->email,
            'channel' => 'IN_APP',
            'status' => 'SENT',
        ]);

        // 3. Log out admin and verify the banned user CAN log in and goes to /requests
        $this->actingAs($admin)->post('/logout');

        $loginResp = $this->post('/login', [
            'identifier' => $offender->email,
            'password' => 'SecretPass123!',
        ]);
        $loginResp->assertRedirect('/requests');
        $this->assertAuthenticatedAs($offender);

        // 4. Banned user can view ongoing & past orders + track lifecycle + sees bell pop-up & restriction banner
        $ongoingPage = $this->actingAs($offender)->get('/requests');
        $ongoingPage->assertStatus(200);
        $ongoingPage->assertSee('Ongoing Bid Request');
        $ongoingPage->assertSee('Account Restricted');
        $ongoingPage->assertSee('New Requests Restricted');
        $ongoingPage->assertDontSee('+ Post New Repair Request');
        $ongoingPage->assertSee('meramot-open-notif-modal');

        $pastPage = $this->actingAs($offender)->get('/requests?filter=COMPLETED');
        $pastPage->assertStatus(200);
        $pastPage->assertSee('Past Order Secret Repair');
        $pastPage->assertSee('Reviews Restricted (Banned)');
        $pastPage->assertDontSee('Write Verified Repair Review');

        $this->actingAs($offender)->get("/requests/{$pastOrder->id}/track")->assertStatus(200);

        // 5. Banned user cannot create requests, order services, use cart, or post reviews
        $this->actingAs($offender)->get('/requests/create')->assertRedirect('/requests');
        $this->actingAs($offender)->post('/requests', [
            'title' => 'Blocked Request',
            'device_type' => 'Phone',
            'problem' => 'Blocked while banned',
            'preferred_service_type' => 'walk_in',
        ])->assertRedirect('/requests');
        $this->assertDatabaseMissing('repair_requests', ['title' => 'Blocked Request']);

        $this->actingAs($offender)->get('/cart')->assertRedirect('/requests');
        $this->actingAs($offender)->post('/cart/checkout/place', [])->assertRedirect('/requests');
        $this->actingAs($offender)->postJson('/cart/add', ['package_id' => 1])->assertStatus(403);
        $this->actingAs($offender)->get("/reviews/create/{$shop->id}")->assertRedirect(route('shops.show', $shop->slug));
        $this->actingAs($offender)->post('/reviews', [
            'repair_shop_id' => $shop->id,
            'rating' => 5,
            'review_comment' => 'Blocked review',
        ])->assertRedirect('/requests');
        $this->assertDatabaseMissing('shop_reviews', ['customer_id' => $offender->id]);

        // 6. Marking notifications read / popup_only works via endpoint
        $this->actingAs($offender)
            ->postJson(route('notifications.mark-read'), ['popup_only' => true])
            ->assertOk()
            ->assertJson(['ok' => true]);

        // 7. Admin can unban the user and restore full customer permissions
        $this->actingAs($admin)->post(route('admin.users.unban', $offender->id))
            ->assertSessionHas('success');

        $offender->refresh();
        $this->assertSame('active', $offender->status);
        $this->assertNull($offender->ban_reason);

        $this->assertDatabaseHas('user_notifications', [
            'user_id' => $offender->id,
            'type' => 'ACCOUNT_UNBANNED',
        ]);

        $this->actingAs($offender)->get('/requests/create')->assertStatus(200);
        $this->actingAs($offender)->get("/reviews/create/{$shop->id}")->assertStatus(200);
    }
}

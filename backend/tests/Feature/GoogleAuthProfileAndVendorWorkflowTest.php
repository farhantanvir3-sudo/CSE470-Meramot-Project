<?php

namespace Tests\Feature;

use App\Models\RepairShop;
use App\Models\ShopService;
use App\Models\User;
use App\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * ============================================================================
 * Google OAuth Distinct Sign-Up / Sign-In, Customer Profile Dashboard &
 * Dedicated Vendor Sign-Up + Post-Approval Shop Setup Feature Tests
 * ============================================================================
 * Author: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * ============================================================================
 */
class GoogleAuthProfileAndVendorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function fakeGoogleOAuthResponses(string $email, string $sub, string $name = 'Google Test Customer'): void
    {
        config([
            'services.google.client_id' => 'test-google-client-id.apps.googleusercontent.com',
            'services.google.client_secret' => 'test-google-client-secret',
        ]);

        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'fake-google-access-token',
                'expires_in' => 3600,
                'token_type' => 'Bearer',
            ], 200),
            'https://www.googleapis.com/oauth2/v3/userinfo' => Http::response([
                'sub' => $sub,
                'email' => $email,
                'email_verified' => true,
                'name' => $name,
                'picture' => 'https://lh3.googleusercontent.com/a/default-user=s96-c',
            ], 200),
        ]);
    }

    /** @test */
    public function login_and_register_pages_render_distinct_google_buttons_and_vendor_signup_cta()
    {
        $loginResp = $this->get('/login');
        $loginResp->assertStatus(200);
        $loginResp->assertSee('Sign in with Google');
        $loginResp->assertDontSee('Continue with Google');
        $loginResp->assertSee('/auth/google?intent=login', false);

        $registerResp = $this->get('/register');
        $registerResp->assertStatus(200);
        $registerResp->assertSee('Sign up with Google');
        $registerResp->assertDontSee('Continue with Google');
        $registerResp->assertSee('/auth/google?intent=signup', false);
        $registerResp->assertSee('Register as a vendor');
        $registerResp->assertSee('/vendor/apply', false);
    }

    /** @test */
    public function sign_in_with_google_blocks_unregistered_gmail_and_password_only_accounts()
    {
        // Case 1: Unregistered Gmail attempting "Sign in with Google" (`intent=login`)
        $this->fakeGoogleOAuthResponses('new.visitor@gmail.com', 'google-sub-1001', 'New Visitor');

        $redirect = $this->get('/auth/google?intent=login');
        $redirect->assertRedirect();
        parse_str((string) parse_url($redirect->headers->get('Location'), PHP_URL_QUERY), $oauthQuery);
        $this->assertStringStartsWith('login:', $oauthQuery['state'] ?? '');

        $callback = $this->get('/auth/google/callback?code=fake-auth-code&state=' . urlencode($oauthQuery['state']));
        $callback->assertRedirect('/register');
        $callback->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'new.visitor@gmail.com']);

        // Case 2: Local password-registered user (`google_id = null`) attempting "Sign in with Google"
        User::create([
            'name' => 'Local Password Customer',
            'username' => 'localcustomer',
            'email' => 'local.customer@gmail.com',
            'phone' => '01711223344',
            'password' => Hash::make('SecretPass123'),
            'role' => 'customer',
            'auth_provider' => 'local',
            'google_id' => null,
            'is_email_verified' => true,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->fakeGoogleOAuthResponses('local.customer@gmail.com', 'google-sub-1002', 'Local Password Customer');

        $redirect2 = $this->get('/auth/google?intent=login');
        parse_str((string) parse_url($redirect2->headers->get('Location'), PHP_URL_QUERY), $oauthQuery2);

        $callback2 = $this->get('/auth/google/callback?code=fake-auth-code-2&state=' . urlencode($oauthQuery2['state']));
        $callback2->assertRedirect('/register');
        $callback2->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    /** @test */
    public function sign_up_with_google_creates_passwordless_customer_and_allows_subsequent_sign_in_with_google()
    {
        $this->fakeGoogleOAuthResponses('farhan.customer@gmail.com', 'google-sub-9999', 'Farhan Customer');

        // Step 1: Sign up with Google (`intent=signup`)
        $signupRedirect = $this->get('/auth/google?intent=signup');
        $signupRedirect->assertRedirect();
        parse_str((string) parse_url($signupRedirect->headers->get('Location'), PHP_URL_QUERY), $signupQuery);
        $this->assertStringStartsWith('signup:', $signupQuery['state'] ?? '');

        $signupCallback = $this->get('/auth/google/callback?code=signup-code&state=' . urlencode($signupQuery['state']));
        $signupCallback->assertRedirect('/');

        $user = User::where('email', 'farhan.customer@gmail.com')->first();
        $this->assertNotNull($user);
        $this->assertEquals('customer', $user->role);
        $this->assertEquals('google-sub-9999', $user->google_id);
        $this->assertEquals('google', $user->auth_provider);
        $this->assertNull($user->password);
        $this->assertNull($user->phone);
        $this->assertTrue((bool) $user->is_email_verified);
        $this->assertAuthenticatedAs($user);

        // Log out and verify "Sign up with Google" a second time redirects cleanly to /login
        $this->post('/logout');
        $this->assertGuest();

        $dupSignupRedirect = $this->get('/auth/google?intent=signup');
        parse_str((string) parse_url($dupSignupRedirect->headers->get('Location'), PHP_URL_QUERY), $dupQuery);
        $dupCallback = $this->get('/auth/google/callback?code=dup-code&state=' . urlencode($dupQuery['state']));
        $dupCallback->assertRedirect('/login');
        $dupCallback->assertSessionHasErrors('google');
        $this->assertGuest();

        // Step 2: Subsequent "Sign in with Google" (`intent=login`) succeeds
        $loginRedirect = $this->get('/auth/google?intent=login');
        parse_str((string) parse_url($loginRedirect->headers->get('Location'), PHP_URL_QUERY), $loginQuery);
        $loginCallback = $this->get('/auth/google/callback?code=login-code&state=' . urlencode($loginQuery['state']));
        $loginCallback->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function customer_can_edit_profile_phone_and_delivery_address_modes_and_sync_during_orders()
    {
        $googleCustomer = User::create([
            'name' => 'Nadia Rahman',
            'username' => 'nadiarahman',
            'email' => 'nadia.google@gmail.com',
            'google_id' => 'google-sub-nadia',
            'auth_provider' => 'google',
            'password' => null,
            'phone' => null,
            'address' => null,
            'role' => 'customer',
            'is_email_verified' => true,
            'is_phone_verified' => false,
            'status' => 'active',
            'is_active' => true,
        ]);

        // 1. Visiting /profile shows dashboard & missing-phone/address prompt
        $profilePage = $this->actingAs($googleCustomer)->get('/profile');
        $profilePage->assertStatus(200);
        $profilePage->assertSee('Nadia Rahman');
        $profilePage->assertSee('Use Location Picker');
        $profilePage->assertSee('Manual Input');

        // 2. Posting a repair request without phone when profile phone is null fails cleanly
        $missingPhoneOrder = $this->actingAs($googleCustomer)->post('/requests', [
            'device_type' => 'Smartphone',
            'brand' => 'Samsung',
            'model_name' => 'Galaxy S23',
            'issue_title' => 'Cracked OLED Display',
            'issue_description' => 'Dropped phone, touch not responding on lower half.',
            'preferred_service_type' => 'pickup_dropoff',
            'contact_phone' => '',
            'pickup_address' => 'House 14, Road 7, Dhanmondi',
            'area' => 'Dhanmondi',
            'city' => 'Dhaka',
        ]);
        $missingPhoneOrder->assertSessionHasErrors('contact_phone');

        // 3. Submitting phone & pickup address during order syncs back to customer profile automatically
        $validOrder = $this->actingAs($googleCustomer)->post('/requests', [
            'device_type' => 'Smartphone',
            'brand' => 'Samsung',
            'model_name' => 'Galaxy S23',
            'issue_title' => 'Cracked OLED Display',
            'issue_description' => 'Dropped phone, touch not responding on lower half.',
            'preferred_service_type' => 'pickup_dropoff',
            'contact_phone' => '01712345678',
            'pickup_address' => 'House 14, Road 7, Dhanmondi',
            'area' => 'Dhanmondi',
            'city' => 'Dhaka',
        ]);
        $validOrder->assertRedirect();

        $googleCustomer->refresh();
        $this->assertEquals('01712345678', $googleCustomer->phone);
        $this->assertStringContainsString('House 14, Road 7', (string) $googleCustomer->address);

        // 4. Editing profile anytime: Manual Input & Confirm mode (no coordinates)
        $manualUpdate = $this->actingAs($googleCustomer)->post('/profile', [
            'name' => 'Nadia Rahman Updated',
            'phone' => '01898765432',
            'address' => 'Flat 4B, House 22, Uttara Sector 7, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Uttara',
            'latitude' => '',
            'longitude' => '',
        ]);
        $manualUpdate->assertRedirect('/profile');

        $googleCustomer->refresh();
        $this->assertEquals('Nadia Rahman Updated', $googleCustomer->name);
        $this->assertEquals('01898765432', $googleCustomer->phone);
        $this->assertEquals('Flat 4B, House 22, Uttara Sector 7, Dhaka', $googleCustomer->address);
        $this->assertEquals('Uttara', $googleCustomer->area);

        // 5. Editing profile anytime: Location Picker mode (with coordinates) + persistent Avatar upload
        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $avatarFile = UploadedFile::fake()->createWithContent('avatar.png', $tinyPng);
        $mapUpdate = $this->actingAs($googleCustomer)->post('/profile', [
            'name' => 'Nadia Rahman Updated',
            'phone' => '01898765432',
            'address' => 'Road 11, Banani, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'latitude' => 23.7937,
            'longitude' => 90.4066,
            'avatar' => $avatarFile,
        ]);
        $mapUpdate->assertRedirect('/profile');

        $googleCustomer->refresh();
        $this->assertEquals('Road 11, Banani, Dhaka', $googleCustomer->address);
        $this->assertEqualsWithDelta(23.7937, (float) $googleCustomer->latitude, 0.001);
        $this->assertEqualsWithDelta(90.4066, (float) $googleCustomer->longitude, 0.001);
        $this->assertStringStartsWith('data:image/', (string) $googleCustomer->avatar_url);

        // 6. Verify Phone via SMS OTP from Profile Dashboard
        $sendOtp = $this->actingAs($googleCustomer)->postJson('/api/verification/send-otp', [
            'channel' => 'phone',
        ]);
        $sendOtp->assertStatus(200);

        $googleCustomer->refresh();
        $this->assertNotEmpty($googleCustomer->phone_otp_code);

        $verifyOtp = $this->actingAs($googleCustomer)->postJson('/api/verification/verify-otp', [
            'channel' => 'phone',
            'otp' => $googleCustomer->phone_otp_code,
        ]);
        $verifyOtp->assertStatus(200);
        $this->assertTrue((bool) $googleCustomer->fresh()->is_phone_verified);

        // 7. Delete profile works safely even when customer has past repair requests
        $deleteResp = $this->actingAs($googleCustomer)->delete('/profile');
        $deleteResp->assertRedirect('/');
        $this->assertGuest();
    }

    /** @test */
    public function dedicated_vendor_signup_onboarding_otp_admin_approval_and_shop_setup_workflow_works_end_to_end()
    {
        // Step 1: Live business email check returns available = true
        $emailCheck = $this->getJson('/api/vendors/check-email?email=quantum.repairs@workshop.bd');
        $emailCheck->assertStatus(200)->assertJson(['available' => true]);

        // Step 2: Render dedicated /vendor/apply form
        $this->get('/vendor/apply')->assertStatus(200)->assertSee('Vendor Application');

        // Step 3: Submit dedicated vendor application (`POST /vendor/apply`)
        $tinyPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
        $logoFile = UploadedFile::fake()->createWithContent('shop-logo.png', $tinyPng);
        $applyResp = $this->post('/vendor/apply', [
            'owner_name' => 'Tanvir Ahmed',
            'business_email' => 'quantum.repairs@workshop.bd',
            'phone' => '01755667788',
            'shop_name' => 'Quantum Micro Lab',
            'password' => 'VendorSecure123',
            'password_confirmation' => 'VendorSecure123',
            'business_license_no' => 'TRAD/DNCC/554433',
            'nid_number' => '1994001122334',
            'shop_latitude' => 23.7940,
            'shop_longitude' => 90.4043,
            'gps_address' => 'Map Pin Block E, Banani, Dhaka',
            'same_as_gps_location' => '0',
            'shop_address' => 'Level 3, Shop 309, Banani Super Market, Dhaka',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'specialties' => json_encode(['Motherboard Micro-soldering', 'OLED Screen Refurb']),
            'courier_pickup' => '1',
            'in_shop_repair' => '1',
            'spare_parts' => '0',
            'notes' => 'Authorized BGA rework bench with thermal imaging.',
            'logo' => $logoFile,
        ]);

        $vendorUser = User::where('email', 'quantum.repairs@workshop.bd')->first();
        $this->assertNotNull($vendorUser);
        $this->assertEquals('vendor', $vendorUser->role);

        $profile = VendorProfile::where('user_id', $vendorUser->id)->first();
        $this->assertNotNull($profile);
        $this->assertEquals('pending', $profile->verification_status);

        $applyResp->assertRedirect(route('vendor.apply.success', ['id' => $profile->id]));
        $this->get('/vendor/apply/success?id=' . $profile->id)
            ->assertStatus(200)
            ->assertSee('Application submitted');

        // Email check now reports unavailable
        $this->getJson('/api/vendors/check-email?email=quantum.repairs@workshop.bd')
            ->assertStatus(200)
            ->assertJson(['available' => false]);

        $shop = RepairShop::where('vendor_profile_id', $profile->id)->first();
        $this->assertNotNull($shop);
        $this->assertFalse((bool) $shop->setup_complete);
        $this->assertFalse((bool) $shop->is_public);
        $this->assertNull($shop->inspection_fee);

        // Step 4: Vendor logs in -> redirected to /vendor/onboarding hub
        $loginResp = $this->post('/login', [
            'identifier' => 'quantum.repairs@workshop.bd',
            'password' => 'VendorSecure123',
        ]);
        $loginResp->assertRedirect('/vendor/onboarding');

        $this->actingAs($vendorUser)->get('/vendor/onboarding')
            ->assertStatus(200)
            ->assertSee('Quantum Micro Lab')
            ->assertSee('PENDING')
            ->assertSee('Not completed')
            ->assertSee('Account Verification');

        $this->actingAs($vendorUser)->get('/vendor/status')
            ->assertStatus(200)
            ->assertSee('Quantum Micro Lab');

        // Unapproved vendor cannot access /vendor/setup-shop yet
        $this->actingAs($vendorUser)->get('/vendor/setup-shop')
            ->assertRedirect('/vendor/onboarding');

        // Step 5: Vendor verifies Email & Phone OTP
        $this->actingAs($vendorUser)->postJson('/api/verification/send-otp', ['channel' => 'email'])->assertStatus(200);
        $vendorUser->refresh();
        $this->actingAs($vendorUser)->postJson('/api/verification/verify-otp', [
            'channel' => 'email',
            'otp' => $vendorUser->email_otp_code,
        ])->assertStatus(200);

        $this->actingAs($vendorUser)->postJson('/api/verification/send-otp', ['channel' => 'phone'])->assertStatus(200);
        $vendorUser->refresh();
        $this->actingAs($vendorUser)->postJson('/api/verification/verify-otp', [
            'channel' => 'phone',
            'otp' => $vendorUser->phone_otp_code,
        ])->assertStatus(200);

        $vendorUser->refresh();
        $this->assertTrue((bool) $vendorUser->is_email_verified);
        $this->assertTrue((bool) $vendorUser->is_phone_verified);

        // Step 6: Admin approves vendor application -> shop stays hidden until setup-shop is completed
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->post('/admin/vendors/' . $profile->id . '/approve')
            ->assertRedirect();

        $profile->refresh();
        $shop->refresh();
        $this->assertEquals('approved', $profile->verification_status);
        $this->assertFalse((bool) $shop->setup_complete);
        $this->assertFalse((bool) $shop->is_public);

        // Step 7: Approved vendor completes `/vendor/setup-shop` with pricing & services -> goes LIVE!
        $this->actingAs($vendorUser)->get('/vendor/setup-shop')
            ->assertStatus(200)
            ->assertSee('Set up your shop');

        $setupResp = $this->actingAs($vendorUser)->post('/vendor/setup-shop', [
            'shop_name' => 'Quantum Micro Lab',
            'phone' => '01755667788',
            'description' => 'Precision board-level diagnostics and genuine display replacements.',
            'address' => 'Level 3, Shop 309, Banani Super Market, Dhaka',
            'gps_address' => 'Map Pin Block E, Banani, Dhaka',
            'same_as_gps_location' => '0',
            'city' => 'Dhaka',
            'area' => 'Banani',
            'latitude' => 23.7940,
            'longitude' => 90.4043,
            'courier_pickup' => '1',
            'in_shop_repair' => '1',
            'spare_parts' => '1',
            'inspection_fee' => 300,
            'base_labor_fee' => 650,
            'pickup_fee' => 120,
            'express_fee' => 450,
            'skill_tags' => [
                'Motherboard IC Rework (৳2500)',
                'OLED Screen Replacement (৳4500)',
            ],
        ]);
        $setupResp->assertRedirect('/vendor/dashboard');

        $shop->refresh();
        $this->assertTrue((bool) $shop->setup_complete);
        $this->assertTrue((bool) $shop->is_public);
        $this->assertEquals(300.0, (float) $shop->inspection_fee);
        $this->assertEquals(650.0, (float) $shop->base_labor_fee);
        $this->assertGreaterThanOrEqual(2, ShopService::where('shop_id', $shop->id)->count());

        // Shop is now publicly discoverable on /shops!
        $this->get('/shops')->assertStatus(200)->assertSee('Quantum Micro Lab');
    }
}

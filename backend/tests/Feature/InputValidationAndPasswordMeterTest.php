<?php

namespace Tests\Feature;

use App\Models\DeliveryAgent;
use App\Models\RepairShop;
use App\Models\User;
use App\Models\VendorProfile;
use App\Support\InputSanitizer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class InputValidationAndPasswordMeterTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function input_sanitizer_normalizes_and_validates_phone_and_nid_strictly(): void
    {
        // Phone normalization across whitespace, dashes, parens, invisible bidi marks, Bengali numerals, 00880 prefix
        $this->assertSame('+8801712345678', InputSanitizer::sanitizePhone('  +880 1712-345678  '));
        $this->assertSame('01712345678', InputSanitizer::sanitizePhone("\u{202A}(017) 1234-5678\u{202C}"));
        $this->assertSame('01712345678', InputSanitizer::sanitizePhone('০১৭১২-৩৪৫৬৭৮'));
        $this->assertSame('+8801812345678', InputSanitizer::sanitizePhone('00880 1812-345678'));

        $this->assertTrue(InputSanitizer::isValidBdPhone('+880 1712-345678'));
        $this->assertTrue(InputSanitizer::isValidBdPhone('01911223344'));
        $this->assertFalse(InputSanitizer::isValidBdPhone('12345'));
        $this->assertFalse(InputSanitizer::isValidBdPhone('01112345678'));
        $this->assertFalse(InputSanitizer::isValidBdPhone('01712abc678'));

        // NID normalization & 10/13/17-digit check
        $this->assertSame('1995123456789', InputSanitizer::sanitizeDigits("\u{200B}1995-1234 56789"));
        $this->assertTrue(InputSanitizer::isValidNid('1234567890'));
        $this->assertTrue(InputSanitizer::isValidNid('1995-1234 56789'));
        $this->assertTrue(InputSanitizer::isValidNid('19952612345678901'));
        $this->assertFalse(InputSanitizer::isValidNid('123456789'));
        $this->assertFalse(InputSanitizer::isValidNid('199512345678x'));
    }

    /** @test */
    public function password_strength_meter_appears_strictly_on_setup_and_change_pages_never_on_signin(): void
    {
        // Sign-in pages must NEVER display a password strength meter, MUST offer distinct right-aligned recovery triggers
        $this->get('/login')
            ->assertStatus(200)
            ->assertSee('data-forgot-credentials-trigger="main"', false)
            ->assertSee('Forgot password?')
            ->assertDontSee('data-password-strength-meter', false);

        $this->get('/delivery/login')
            ->assertStatus(200)
            ->assertSee('data-forgot-credentials-trigger="delivery"', false)
            ->assertSee('Forgot credentials?')
            ->assertDontSee('data-password-strength-meter', false);

        // New registration / setup / reset pages MUST include password strength meter
        $this->get('/register')
            ->assertStatus(200)
            ->assertSee('data-password-strength-meter', false);

        $this->get('/forgot-password?step=reset')
            ->assertStatus(200)
            ->assertSee('data-password-strength-meter', false);

        $this->get('/vendor/apply')
            ->assertStatus(200)
            ->assertSee('data-password-strength-meter', false);

        $this->get('/vendor/onboarding')
            ->assertStatus(200)
            ->assertSee('data-password-strength-meter', false);

        // Profile Password & Security section MUST include strength meter (for both local & OAuth accounts)
        $localCustomer = User::factory()->create([
            'role' => 'customer',
            'password' => Hash::make('OldSecret123!'),
        ]);
        $this->actingAs($localCustomer)->get('/profile')
            ->assertStatus(200)
            ->assertSee('Password &amp; Security', false)
            ->assertSee('name="current_password"', false)
            ->assertSee('data-password-strength-meter', false);

        $googleCustomer = User::factory()->create([
            'role' => 'customer',
            'password' => null,
            'auth_provider' => 'google',
            'google_id' => 'google-oauth-sub-4001',
        ]);
        $this->actingAs($googleCustomer)->get('/profile')
            ->assertStatus(200)
            ->assertSee('Set Account Password')
            ->assertDontSee('name="current_password"', false)
            ->assertSee('data-password-strength-meter', false);
    }

    /** @test */
    public function profile_password_update_supports_both_existing_password_change_and_google_initial_setup(): void
    {
        $user = User::factory()->create([
            'role' => 'customer',
            'password' => Hash::make('OldPass123!'),
        ]);

        // Wrong current password rejected
        $this->actingAs($user)
            ->post('/profile/password', [
                'current_password' => 'WrongCurrent123',
                'password' => 'NewStrong123!',
                'password_confirmation' => 'NewStrong123!',
            ])
            ->assertSessionHasErrors(['current_password']);

        // Too short new password rejected
        $this->actingAs($user)
            ->post('/profile/password', [
                'current_password' => 'OldPass123!',
                'password' => 'Short1!',
                'password_confirmation' => 'Short1!',
            ])
            ->assertSessionHasErrors(['password']);

        // Mismatched confirmation rejected
        $this->actingAs($user)
            ->post('/profile/password', [
                'current_password' => 'OldPass123!',
                'password' => 'ValidPass99!',
                'password_confirmation' => 'MismatchPass99!',
            ])
            ->assertSessionHasErrors(['password']);

        // Valid change updates password cleanly
        $this->actingAs($user)
            ->post('/profile/password', [
                'current_password' => 'OldPass123!',
                'password' => 'StrongNewPass99!',
                'password_confirmation' => 'StrongNewPass99!',
            ])
            ->assertRedirect('/profile')
            ->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertTrue(Hash::check('StrongNewPass99!', $user->password));

        // Passwordless Google user can set initial password without current_password
        $googleUser = User::factory()->create([
            'role' => 'customer',
            'password' => null,
            'auth_provider' => 'google',
            'google_id' => 'google-oauth-sub-9991',
        ]);

        $this->actingAs($googleUser)
            ->post('/profile/password', [
                'password' => 'FirstLocalPass88!',
                'password_confirmation' => 'FirstLocalPass88!',
            ])
            ->assertRedirect('/profile')
            ->assertSessionHasNoErrors();

        $googleUser->refresh();
        $this->assertTrue(Hash::check('FirstLocalPass88!', $googleUser->password));
    }

    /** @test */
    public function controllers_reject_malformed_phone_nid_and_year_and_persist_sanitized_values(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer',
            'password' => Hash::make('SecretPass123'),
        ]);

        // Profile phone normalization & strict rejection
        $this->actingAs($customer)
            ->post('/profile', [
                'name' => $customer->name,
                'phone' => 'not-a-phone',
            ])
            ->assertSessionHasErrors(['phone']);

        $this->actingAs($customer)
            ->post('/profile', [
                'name' => $customer->name,
                'phone' => "\u{202A}+880 1712-345678\u{202C}",
                'city' => 'Dhaka',
                'area' => 'Banani',
            ])
            ->assertSessionHasNoErrors();

        $customer->refresh();
        $this->assertSame('+8801712345678', $customer->phone);

        // RepairRequest rejects invalid year or invalid phone, normalizes valid Bengali phone & year
        $this->actingAs($customer)
            ->post('/requests', [
                'device_type' => 'Laptop',
                'brand' => 'Lenovo',
                'model_name' => 'ThinkPad T14',
                'year' => 'abcd',
                'issue_title' => 'Charging Port Issue',
                'issue_description' => 'USB-C port loose.',
                'preferred_service_type' => 'pickup_dropoff',
                'contact_phone' => '01712345678',
                'area' => 'Banani',
                'city' => 'Dhaka',
            ])
            ->assertSessionHasErrors(['year']);

        $this->actingAs($customer)
            ->post('/requests', [
                'device_type' => 'Laptop',
                'brand' => 'Lenovo',
                'model_name' => 'ThinkPad T14',
                'year' => '1950',
                'issue_title' => 'Charging Port Issue',
                'issue_description' => 'USB-C port loose.',
                'preferred_service_type' => 'pickup_dropoff',
                'contact_phone' => '01199887766',
                'area' => 'Banani',
                'city' => 'Dhaka',
            ])
            ->assertSessionHasErrors(['year', 'contact_phone']);

        // Vendor apply rejects malformed NID while accepting hyphenated/spaced 13-digit NID
        $this->post('/vendor/apply', [
            'owner_name' => 'Karim Uddin',
            'business_email' => 'karim.vendor.test@example.com',
            'phone' => '01712345678',
            'shop_name' => 'Karim Care Lab',
            'password' => 'StrongPass99!',
            'password_confirmation' => 'StrongPass99!',
            'nid_number' => '12345-bad',
            'shop_latitude' => 23.7937,
            'shop_longitude' => 90.4066,
            'city' => 'Dhaka',
            'area' => 'Banani',
        ])->assertSessionHasErrors(['nid_number']);

        // Delivery registration rejects malformed NID
        $this->post('/delivery-agent/register', [
            'name' => 'Salman Rider',
            'phone' => '01712345678',
            'personal_email' => 'salman.rider.test@gmail.com',
            'nid_number' => '999',
            'vehicle_type' => 'motorcycle',
            'license_number' => 'DL-DHK-9999',
        ])->assertSessionHasErrors(['nid_number']);
    }

    /** @test */
    public function vendor_views_render_all_64_districts_narrowed_selector_and_typeahead_input(): void
    {
        $vendorUser = User::factory()->create([
            'role' => 'vendor',
            'is_email_verified' => true,
            'is_phone_verified' => true,
        ]);

        $profile = VendorProfile::create([
            'user_id' => $vendorUser->id,
            'owner_name' => $vendorUser->name,
            'business_email' => $vendorUser->email,
            'phone' => '01755667788',
            'shop_name' => 'Selectronics Lab',
            'brand_name' => 'Selectronics Lab',
            'is_branched' => false,
            'business_license_no' => 'TRAD/DNCC/55511',
            'nid_number' => '1990112233445',
            'verification_status' => 'approved',
            'shop_address' => 'Banani Road 11, Dhaka',
            'gps_address' => 'Banani Road 11, Dhaka',
            'same_as_gps_location' => true,
            'city' => 'Dhaka',
            'area' => 'Banani',
            'shop_latitude' => 23.7937,
            'shop_longitude' => 90.4066,
            'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
            'specialties' => ['Smartphone Repair', 'Laptop Repair'],
        ]);

        RepairShop::create([
            'vendor_profile_id' => $profile->id,
            'name' => 'Selectronics Lab',
            'slug' => 'selectronics-lab-test',
            'is_branch' => false,
            'description' => 'Testing vendor shop',
            'address' => 'Banani Road 11, Dhaka',
            'gps_address' => 'Banani Road 11, Dhaka',
            'same_as_gps_location' => true,
            'city' => 'Dhaka',
            'area' => 'Banani',
            'latitude' => 23.7937,
            'longitude' => 90.4066,
            'phone' => '01755667788',
            'email' => $vendorUser->email,
            'categories' => ['COURIER_PICKUP', 'IN_SHOP_REPAIR'],
            'specialties' => ['Smartphone Repair'],
            'supports_pickup' => true,
            'inspection_fee' => 300,
            'base_labor_fee' => 600,
            'is_active' => true,
            'is_public' => true,
            'setup_complete' => true,
            'accepts_direct_orders' => true,
        ]);

        foreach ([
            '/vendor/apply',
            '/vendor/dashboard?tab=branches',
            '/vendor/setup-shop',
        ] as $url) {
            $resp = ($url === '/vendor/apply') ? $this->get($url) : $this->actingAs($vendorUser)->get($url);
            $resp->assertStatus(200)
                ->assertSee('data-district-select', false)
                ->assertSee('data-narrowed-location-select', false)
                ->assertSee('data-area-typeahead-input', false)
                ->assertSee('data-area-suggestions', false)
                ->assertSee('<option value="Dhaka">Dhaka</option>', false)
                ->assertSee('<option value="Chattogram">Chattogram</option>', false)
                ->assertSee('<option value="Sylhet">Sylhet</option>', false)
                ->assertSee('<option value="Sherpur">Sherpur</option>', false);
        }
    }

    /** @test */
    public function customer_and_vendor_can_recover_credentials_and_reset_password_with_email_otp(): void
    {
        $user = User::factory()->create([
            'username' => 'tariq_repair_user',
            'email' => 'tariq.customer@example.com',
            'role' => 'customer',
            'password' => Hash::make('OldPassword123!'),
        ]);

        // Request reset by username via JSON modal endpoint
        $resp = $this->postJson('/forgot-password', [
            'identifier' => 'tariq_repair_user',
        ]);
        $resp->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'email' => 'ta***@example.com',
            ]);

        $user->refresh();
        $this->assertNotNull($user->email_otp_code);
        $this->assertEquals(6, strlen((string) $user->email_otp_code));

        $emailLog = DB::table('notification_logs')
            ->where('channel', 'EMAIL')
            ->where('recipient', 'tariq.customer@example.com')
            ->orderByDesc('id')
            ->first();
        $this->assertNotNull($emailLog);

        // Wrong OTP is rejected
        $this->post('/forgot-password/reset', [
            'email' => 'tariq_repair_user',
            'otp' => '000000',
            'password' => 'ResetStrong99!',
            'password_confirmation' => 'ResetStrong99!',
        ])->assertSessionHasErrors(['otp']);

        // Valid 6-digit OTP resets password and signs in cleanly
        $this->post('/forgot-password/reset', [
            'email' => 'tariq.customer@example.com',
            'otp' => $user->email_otp_code,
            'password' => 'ResetStrong99!',
            'password_confirmation' => 'ResetStrong99!',
        ])->assertRedirect('/login')->assertSessionHasNoErrors();

        $user->refresh();
        $this->assertNull($user->email_otp_code);
        $this->assertTrue(Hash::check('ResetStrong99!', $user->password));

        $this->post('/login', [
            'identifier' => 'tariq.customer@example.com',
            'password' => 'ResetStrong99!',
        ])->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
    }

    /** @test */
    public function delivery_login_forgot_credentials_resends_immutable_company_credentials_to_personal_email(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_email_verified' => true,
            'status' => 'active',
            'is_active' => true,
        ]);

        $this->post('/delivery-agent/register', [
            'name' => 'Kamal Rider',
            'phone' => '+8801712345679',
            'personal_email' => 'kamal.recover.test@gmail.com',
            'nid_number' => '1997123456789',
            'vehicle_type' => 'motorcycle',
            'license_number' => 'DL-DHK-554433',
        ])->assertRedirect('/delivery/login');

        $agent = DeliveryAgent::where('personal_email', 'kamal.recover.test@gmail.com')->firstOrFail();

        // Pending applicant receives actionable 422 telling them to check application status
        $this->postJson('/delivery/forgot-credentials', [
            'lookup_email' => 'kamal.recover.test@gmail.com',
        ])->assertStatus(422)->assertJsonValidationErrors(['lookup_email']);

        // Approve rider so immutable @meramotbd.com credentials are issued
        $this->actingAs($admin)->post("/admin/delivery-agents/{$agent->id}/approve")->assertRedirect();
        $this->post('/logout');

        $agent = $agent->fresh('user');
        $this->assertSame('APPROVED', $agent->registration_status);
        $originalPasswordHash = $agent->user->password;

        $beforeCount = DB::table('notification_logs')
            ->where('channel', 'EMAIL')
            ->where('recipient', 'kamal.recover.test@gmail.com')
            ->count();

        // Lookup by company @meramotbd.com email masks destination personal email & logs email resend
        $recoverCompany = $this->postJson('/delivery/forgot-credentials', [
            'lookup_email' => $agent->company_email,
        ]);
        $recoverCompany->assertStatus(200)
            ->assertJson([
                'ok' => true,
                'destination' => 'ka***@gmail.com',
            ]);

        $afterCount = DB::table('notification_logs')
            ->where('channel', 'EMAIL')
            ->where('recipient', 'kamal.recover.test@gmail.com')
            ->count();
        $this->assertGreaterThan($beforeCount, $afterCount);

        // System-generated rider password hash must stay unchanged
        $this->assertSame($originalPasswordHash, $agent->user->fresh()->password);

        // Non-AJAX POST by personal email redirects cleanly with session popup trigger
        $this->post('/delivery/forgot-credentials', [
            'lookup_email' => 'kamal.recover.test@gmail.com',
        ])->assertRedirect('/delivery/login')
            ->assertSessionHas('delivery_credentials_sent', 'kamal.recover.test@gmail.com');
    }
}

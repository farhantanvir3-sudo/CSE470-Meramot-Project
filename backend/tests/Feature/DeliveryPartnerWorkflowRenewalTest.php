<?php

namespace Tests\Feature;

use App\Models\DeliveryAgent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * ============================================================================
 * FEATURE R1 RENEWAL: Delivery Partner Onboarding, OTP Status Verification,
 * `@meramotbd.com` One-Time Company Credentials & Rider Workspace Portal Tests
 * ============================================================================
 * Assigned Feature Owner: Md. Nafiul Islam [Student ID: 22299035]
 * Renewal Integration & Test Suite: Farhan Tanvir [Student ID: 22299179]
 * ============================================================================
 */
class DeliveryPartnerWorkflowRenewalTest extends TestCase
{
    use RefreshDatabase;

    private function fakePngUpload(string $filename = 'scan.png'): UploadedFile
    {
        $pngBytes = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg=='
        );

        return UploadedFile::fake()->createWithContent($filename, $pngBytes);
    }

    private function createAdminUser(): User
    {
        return User::create([
            'name' => 'Meramot Operations Admin',
            'username' => 'ops_admin_' . random_int(100, 999),
            'email' => 'ops.admin.' . random_int(100, 999) . '@meramot.demo',
            'phone' => '+8801700000099',
            'password' => Hash::make('AdminPass123!'),
            'role' => 'admin',
            'status' => 'active',
            'is_active' => true,
            'is_email_verified' => true,
        ]);
    }

    /** @test */
    public function login_page_confines_demo_logins_to_pills_and_one_click_rider_opens_seeded_workspace()
    {
        $this->seed();

        $loginResp = $this->get('/login');
        $loginResp->assertStatus(200);
        $loginResp->assertDontSee('customer@meramot.demo');
        $loginResp->assertDontSee('password123');
        $loginResp->assertSee('1-Click Demo Login');
        $loginResp->assertSee('/delivery/login', false);

        // 1. Customer 1-Click Demo -> Direct Home
        $this->get('/auth/demo?role=customer')->assertRedirect('/');
        $this->post('/logout');

        // 2. Vendor 1-Click Demo -> Direct Vendor Dashboard
        $this->get('/auth/demo?role=vendor')->assertRedirect('/vendor/dashboard');
        $this->post('/logout');

        // 3. Admin 1-Click Demo -> Direct Admin Console
        $this->get('/auth/demo?role=admin')->assertRedirect('/admin');
        $this->post('/logout');

        // 4. Rider 1-Click Demo -> Direct Delivery Partner Workspace (`/delivery`) with seeded jobs
        $riderDemo = $this->get('/auth/demo?role=delivery');
        $riderDemo->assertRedirect('/delivery');

        $riderUser = User::where('email', 'rahim.uddin@meramotbd.com')->first();
        $this->assertNotNull($riderUser);
        $this->assertAuthenticatedAs($riderUser);

        $dashboard = $this->get('/delivery');
        $dashboard->assertStatus(200);
        $dashboard->assertSee('Delivery Dashboard');
        $dashboard->assertSee('Rahim Uddin');
        $dashboard->assertSee('Active deliveries');
        $dashboard->assertSee('Completed trips');

        $allAssigned = $this->get('/delivery?view=ASSIGNED');
        $allAssigned->assertStatus(200);
        $allAssigned->assertSee('DELIVERED');

        $this->get('/delivery/map')->assertStatus(200)->assertSee('Delivery Map');
        $this->get('/delivery/earnings')->assertStatus(200);
        $this->get('/delivery/profile')->assertStatus(200)->assertSee('rahim.uddin@meramotbd.com');
    }

    /** @test */
    public function apply_as_delivery_agent_is_always_blank_requires_personal_email_and_triggers_popup_modal()
    {
        $this->seed();

        // Even while signed in as demo rider, `/delivery-agent/register` must never pre-fill demo data
        $riderUser = User::where('email', 'rahim.uddin@meramotbd.com')->firstOrFail();
        $blankForm = $this->actingAs($riderUser)->get('/delivery-agent/register');
        $blankForm->assertStatus(200);
        $blankForm->assertDontSee('rahim.uddin@meramotbd.com');
        $blankForm->assertDontSee('1996269481011');

        $this->post('/logout');

        // Reject `@meramotbd.com` as personal email
        $badEmailResp = $this->post('/delivery-agent/register', [
            'name' => 'Arif Hossain',
            'phone' => '+8801712345678',
            'personal_email' => 'arif@meramotbd.com',
            'nid_number' => '1999123456789',
            'vehicle_type' => 'motorcycle',
            'license_number' => 'DK-778899-M',
        ]);
        $badEmailResp->assertSessionHasErrors('personal_email');

        // Valid personal email + document uploads -> PENDING + immediate popup modal
        $submitResp = $this->post('/delivery-agent/register', [
            'name' => 'Arif Hossain',
            'phone' => '+8801712345678',
            'personal_email' => 'arif.hossain@gmail.com',
            'nid_number' => '1999123456789',
            'vehicle_type' => 'motorcycle',
            'license_number' => 'DK-778899-M',
            'profile_picture' => $this->fakePngUpload('profile.png'),
            'nid_document' => $this->fakePngUpload('nid.png'),
            'license_document' => $this->fakePngUpload('license.png'),
            'education_document' => $this->fakePngUpload('ssc.png'),
            'cv_document' => $this->fakePngUpload('cv.png'),
        ]);

        $submitResp->assertRedirect('/delivery/login');
        $submitResp->assertSessionHas('application_submitted_popup', function (array $popup) {
            return $popup['personal_email'] === 'arif.hossain@gmail.com'
                && $popup['name'] === 'Arif Hossain';
        });

        $agent = DeliveryAgent::where('personal_email', 'arif.hossain@gmail.com')->first();
        $this->assertNotNull($agent);
        $this->assertEquals('PENDING', $agent->registration_status);
        $this->assertNull($agent->company_email);
        $this->assertNull($agent->company_password_vault);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $agent->nid_document_url);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $agent->license_document_url);

        $this->assertTrue(
            DB::table('notification_logs')
                ->where('channel', 'EMAIL')
                ->where('recipient', 'arif.hossain@gmail.com')
                ->exists()
        );
    }

    /** @test */
    public function check_application_status_verifies_otp_shows_rejection_reason_and_editing_resets_to_pending()
    {
        $admin = $this->createAdminUser();

        // Dedicated Delivery Partner Login has both required buttons below login form
        $partnerLoginPage = $this->get('/delivery/login');
        $partnerLoginPage->assertStatus(200);
        $partnerLoginPage->assertSee('Check Application Status');
        $partnerLoginPage->assertSee('Apply as a delivery agent');

        // Applicant submits application
        $this->post('/delivery-agent/register', [
            'name' => 'Nayeem Islam',
            'phone' => '+8801812345678',
            'personal_email' => 'nayeem.rider@gmail.com',
            'nid_number' => '1998765432100',
            'vehicle_type' => 'scooter',
            'license_number' => 'DK-554433-S',
            'nid_document' => $this->fakePngUpload('nid.png'),
        ])->assertRedirect('/delivery/login');

        $agent = DeliveryAgent::where('personal_email', 'nayeem.rider@gmail.com')->firstOrFail();

        // Admin cannot reject without providing a textual reason
        $this->actingAs($admin)
            ->post("/admin/delivery-agents/{$agent->id}/reject", ['rejection_reason' => ''])
            ->assertSessionHasErrors('rejection_reason');

        // Admin rejects with clear textual reason
        $rejectionReason = 'NID scan corner was cropped and BRTA license expiry date was unreadable.';
        $this->actingAs($admin)
            ->post("/admin/delivery-agents/{$agent->id}/reject", [
                'rejection_reason' => $rejectionReason,
            ])
            ->assertRedirect();

        $agent->refresh();
        $this->assertEquals('REJECTED', $agent->registration_status);
        $this->assertEquals($rejectionReason, $agent->rejection_reason);

        // Log out admin so applicant tests public OTP status check
        $this->post('/logout');

        // Step 1: Request OTP via personal email
        $this->post('/delivery-agent/status/send-otp', [
            'personal_email' => 'nayeem.rider@gmail.com',
        ])->assertRedirect('/delivery-agent/status');

        $agent->refresh();
        $this->assertNotNull($agent->status_otp_code);
        $otp = $agent->status_otp_code;

        // Invalid OTP fails
        $this->post('/delivery-agent/status/verify-otp', [
            'personal_email' => 'nayeem.rider@gmail.com',
            'otp' => '000000',
        ])->assertSessionHasErrors('otp');

        // Valid OTP verifies and shows REJECTED + exact admin rejection reason + Edit Application button
        $this->post('/delivery-agent/status/verify-otp', [
            'personal_email' => 'nayeem.rider@gmail.com',
            'otp' => $otp,
        ])->assertRedirect('/delivery-agent/status');

        $statusPage = $this->get('/delivery-agent/status');
        $statusPage->assertStatus(200);
        $statusPage->assertSee('REJECTED');
        $statusPage->assertSee($rejectionReason);
        $statusPage->assertSee('/delivery-agent/edit', false);

        // First edit: REJECTED -> automatically resets to PENDING and clears rejection_reason
        $this->get('/delivery-agent/edit')->assertStatus(200)->assertSee('Nayeem Islam');

        $this->post('/delivery-agent/edit', [
            'name' => 'Nayeem Islam Updated',
            'phone' => '+8801812345699',
            'nid_number' => '1998765432100',
            'vehicle_type' => 'motorcycle',
            'license_number' => 'DK-554433-M-NEW',
            'nid_document' => $this->fakePngUpload('clear-nid.png'),
            'license_document' => $this->fakePngUpload('clear-license.png'),
        ])->assertRedirect('/delivery-agent/status');

        $agent->refresh();
        $this->assertEquals('PENDING', $agent->registration_status);
        $this->assertNull($agent->rejection_reason);
        $this->assertEquals('DK-554433-M-NEW', $agent->license_number);

        // Second edit in a row (confirming unlimited edits while not deleted)
        $this->post('/delivery-agent/edit', [
            'name' => 'Nayeem Islam Final',
            'phone' => '+8801812345699',
            'nid_number' => '1998765432100',
            'vehicle_type' => 'motorcycle',
            'license_number' => 'DK-554433-M-V2',
        ])->assertRedirect('/delivery-agent/status');

        $agent->refresh();
        $this->assertEquals('PENDING', $agent->registration_status);
        $this->assertEquals('Nayeem Islam Final', $agent->applicant_name);
    }

    /** @test */
    public function admin_approval_generates_unique_company_credentials_once_enables_portal_login_and_supports_forgot_credentials()
    {
        $admin = $this->createAdminUser();

        // Applicant 1: sabbir.khan@gmail.com -> sabbir.khan@meramotbd.com
        $this->post('/delivery-agent/register', [
            'name' => 'Sabbir Khan',
            'phone' => '+8801911223344',
            'personal_email' => 'sabbir.khan@gmail.com',
            'nid_number' => '1997001122334',
            'vehicle_type' => 'motorcycle',
            'license_number' => 'DK-998877-M',
        ]);

        // Applicant 2 with colliding local-part: sabbir.khan@yahoo.com -> sabbir.khan2@meramotbd.com
        $this->post('/delivery-agent/register', [
            'name' => 'Sabbir Khan Two',
            'phone' => '+8801911223355',
            'personal_email' => 'sabbir.khan@yahoo.com',
            'nid_number' => '1997001122335',
            'vehicle_type' => 'bicycle',
            'license_number' => 'DK-998878-B',
        ]);

        $agent1 = DeliveryAgent::where('personal_email', 'sabbir.khan@gmail.com')->firstOrFail();
        $agent2 = DeliveryAgent::where('personal_email', 'sabbir.khan@yahoo.com')->firstOrFail();

        // Approve both applicants
        $this->actingAs($admin)->post("/admin/delivery-agents/{$agent1->id}/approve")->assertRedirect();
        $this->actingAs($admin)->post("/admin/delivery-agents/{$agent2->id}/approve")->assertRedirect();

        $agent1->refresh();
        $agent2->refresh();

        $this->assertEquals('APPROVED', $agent1->registration_status);
        $this->assertEquals('sabbir.khan@meramotbd.com', $agent1->company_email);
        $this->assertEquals('sabbir.khan2@meramotbd.com', $agent2->company_email);

        $plainPassword1 = Crypt::decryptString($agent1->company_password_vault);
        $this->assertStringStartsWith('MRM#', $plainPassword1);
        $this->assertTrue(Hash::check($plainPassword1, $agent1->user->password));
        $originalPasswordHash = $agent1->user->password;

        // If suspended (`block`) and re-approved, company email & password must NOT be regenerated
        $this->actingAs($admin)->post("/admin/delivery-agents/{$agent1->id}/block")->assertRedirect();
        $this->actingAs($admin)->post("/admin/delivery-agents/{$agent1->id}/approve")->assertRedirect();

        $agent1->refresh();
        $this->assertEquals('sabbir.khan@meramotbd.com', $agent1->company_email);
        $this->assertEquals($plainPassword1, Crypt::decryptString($agent1->company_password_vault));
        $this->assertEquals($originalPasswordHash, $agent1->user->password);

        $this->post('/logout');

        // Customer `/login` blocks `@meramotbd.com` accounts and directs to `/delivery/login`
        $this->post('/login', [
            'identifier' => 'sabbir.khan@meramotbd.com',
            'password' => $plainPassword1,
        ])->assertSessionHasErrors('identifier');
        $this->assertGuest();

        // `/delivery/login` rejects `@gmail.com` personal email
        $this->post('/delivery/login', [
            'company_email' => 'sabbir.khan@gmail.com',
            'password' => $plainPassword1,
        ])->assertSessionHasErrors('company_email');
        $this->assertGuest();

        // `/delivery/login` authenticates exact `@meramotbd.com` company email + system password -> `/delivery`
        $riderLogin = $this->post('/delivery/login', [
            'company_email' => 'sabbir.khan@meramotbd.com',
            'password' => $plainPassword1,
        ]);
        $riderLogin->assertRedirect('/delivery');
        $this->assertAuthenticatedAs($agent1->user);

        // Rider cannot change password or profile via `/profile`
        $this->post('/profile', [
            'name' => 'Hacked Name',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ])->assertRedirect('/delivery/profile');

        $agent1->user->refresh();
        $this->assertEquals('Sabbir Khan', $agent1->user->name);
        $this->assertTrue(Hash::check($plainPassword1, $agent1->user->password));

        $this->post('/delivery/logout')->assertRedirect('/delivery/login');

        // OTP-verified Check Application Status displays APPROVED + "Forgot Company Credentials" resends exact password
        $this->post('/delivery-agent/status/send-otp', ['personal_email' => 'sabbir.khan@gmail.com']);
        $agent1->refresh();
        $this->post('/delivery-agent/status/verify-otp', [
            'personal_email' => 'sabbir.khan@gmail.com',
            'otp' => $agent1->status_otp_code,
        ])->assertRedirect('/delivery-agent/status');

        $emailLogsBefore = DB::table('notification_logs')
            ->where('channel', 'EMAIL')
            ->where('recipient', 'sabbir.khan@gmail.com')
            ->count();

        $this->post('/delivery-agent/status/resend-credentials')
            ->assertRedirect('/delivery-agent/status')
            ->assertSessionHas('success');

        $latestEmailLog = DB::table('notification_logs')
            ->where('channel', 'EMAIL')
            ->where('recipient', 'sabbir.khan@gmail.com')
            ->orderByDesc('id')
            ->first();

        $this->assertNotNull($latestEmailLog);
        $this->assertGreaterThan(
            $emailLogsBefore,
            DB::table('notification_logs')
                ->where('channel', 'EMAIL')
                ->where('recipient', 'sabbir.khan@gmail.com')
                ->count()
        );
        $decodedBody = html_entity_decode((string) $latestEmailLog->body, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $this->assertStringContainsString('sabbir.khan@meramotbd.com', $decodedBody);
        $this->assertStringContainsString($plainPassword1, $decodedBody);
        $agent1->refresh();
        $this->assertEquals($plainPassword1, Crypt::decryptString($agent1->company_password_vault));
    }

    /** @test */
    public function admin_can_inspect_uploaded_documents_and_permanently_delete_application()
    {
        $admin = $this->createAdminUser();

        $this->post('/delivery-agent/register', [
            'name' => 'deleted Applicant',
            'phone' => '+8801611223344',
            'personal_email' => 'temp.applicant@gmail.com',
            'nid_number' => '2001001122334',
            'vehicle_type' => 'bicycle',
            'license_number' => 'DK-121212-B',
            'nid_document' => $this->fakePngUpload('nid-card.png'),
        ]);

        $agent = DeliveryAgent::where('personal_email', 'temp.applicant@gmail.com')->firstOrFail();

        // Applicant verifies OTP first
        $this->post('/delivery-agent/status/send-otp', ['personal_email' => 'temp.applicant@gmail.com']);
        $agent->refresh();
        $this->post('/delivery-agent/status/verify-otp', [
            'personal_email' => 'temp.applicant@gmail.com',
            'otp' => $agent->status_otp_code,
        ]);

        // Admin views uploaded NID document inline
        $docPreview = $this->actingAs($admin)->get("/admin/delivery-agents/{$agent->id}/document/nid");
        $docPreview->assertStatus(200);
        $this->assertStringContainsString('image/png', (string) $docPreview->headers->get('Content-Type'));

        // Admin permanently deletes the application
        $this->actingAs($admin)
            ->delete("/admin/delivery-agents/{$agent->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('delivery_agents', ['id' => $agent->id]);

        // Applicant can no longer view or edit the deleted application
        $this->post('/logout');
        $this->get('/delivery-agent/edit')
            ->assertRedirect('/delivery-agent/status');
    }
}

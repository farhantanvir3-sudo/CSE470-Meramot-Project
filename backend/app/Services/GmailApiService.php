<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * ============================================================================
 * OFFICIAL MERAMOT GMAIL REST API & OTP NOTIFICATION SERVICE (HTTPS PORT 443)
 * ============================================================================
 * Author: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Purpose:
 *   Sends official Meramot emails from `meramotbd.official@gmail.com` via the
 *   Google Gmail REST API over HTTPS (port 443), completely bypassing cloud
 *   hosting outbound SMTP port 587/465 blocks on Render while recording every
 *   outbound notification in PostgreSQL `notification_logs`.
 * ============================================================================
 */
class GmailApiService
{
    /**
     * Exchange OAuth2 refresh token for a short-lived Gmail API access token.
     */
    public function getAccessToken(): ?string
    {
        $clientId = (string) config('services.gmail.client_id', env('GMAIL_CLIENT_ID', ''));
        $clientSecret = (string) config('services.gmail.client_secret', env('GMAIL_CLIENT_SECRET', ''));
        $refreshToken = (string) config('services.gmail.refresh_token', env('GMAIL_REFRESH_TOKEN', ''));

        if ($clientId === '' || $clientSecret === '' || $refreshToken === '') {
            return null;
        }

        $cacheKey = 'meramot_gmail_api_access_token_' . substr(md5($clientId . $refreshToken), 0, 10);
        $cached = Cache::get($cacheKey);
        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->timeout(12)
                ->post('https://oauth2.googleapis.com/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'refresh_token' => $refreshToken,
                    'grant_type' => 'refresh_token',
                ]);

            if (!$response->successful()) {
                Log::warning('[GmailApiService] Token refresh failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                return null;
            }

            $data = $response->json();
            $accessToken = $data['access_token'] ?? null;
            $expiresIn = max(60, ((int) ($data['expires_in'] ?? 3599)) - 300);

            if (is_string($accessToken) && $accessToken !== '') {
                Cache::put($cacheKey, $accessToken, now()->addSeconds($expiresIn));
                return $accessToken;
            }
        } catch (\Throwable $e) {
            Log::warning('[GmailApiService] Exception refreshing token: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * Build RFC 2822 MIME message and encode as URL-safe Base64 (base64url).
     */
    protected function buildRawMimeMessage(string $from, string $to, string $subject, string $html): string
    {
        $boundary = 'boundary_' . bin2hex(random_bytes(8));
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $lines = [
            "From: {$from}",
            "To: {$to}",
            "Subject: {$encodedSubject}",
            'MIME-Version: 1.0',
            "Content-Type: multipart/alternative; boundary=\"{$boundary}\"",
            '',
            "--{$boundary}",
            'Content-Type: text/html; charset="UTF-8"',
            'Content-Transfer-Encoding: base64',
            '',
            chunk_split(base64_encode($html)),
            "--{$boundary}--",
        ];

        $raw = implode("\r\n", $lines);

        return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
    }

    /**
     * Send an HTML email via `meramotbd.official@gmail.com` and log to `notification_logs`.
     */
    public function sendEmail(
        string $to,
        string $subject,
        string $html,
        ?int $repairRequestId = null,
        ?int $deliveryJobId = null
    ): array {
        $gmailUser = (string) config('services.gmail.user', env('GMAIL_USER', 'meramotbd.official@gmail.com'));
        $fromHeader = (string) config('services.gmail.from', env('GMAIL_FROM', "Meramot Official <{$gmailUser}>"));
        $enabled = filter_var(config('services.gmail.enabled', env('ENABLE_EMAIL_NOTIFICATIONS', true)), FILTER_VALIDATE_BOOLEAN);

        $providerRef = null;
        $status = 'QUEUED';
        $errorMsg = null;

        if ($enabled && !app()->environment('testing')) {
            $accessToken = $this->getAccessToken();

            if ($accessToken) {
                try {
                    $raw = $this->buildRawMimeMessage($fromHeader, $to, $subject, $html);
                    $response = Http::withToken($accessToken)
                        ->timeout(15)
                        ->post('https://gmail.googleapis.com/gmail/v1/users/me/messages/send', [
                            'raw' => $raw,
                        ]);

                    if ($response->successful()) {
                        $providerRef = $response->json('id') ?? ('gmail_' . uniqid());
                        $status = 'SENT';
                    } else {
                        $status = 'FAILED';
                        $errorMsg = $response->body();
                        Log::error('[GmailApiService] Gmail API send failed', [
                            'to' => $to,
                            'status' => $response->status(),
                            'body' => $errorMsg,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $status = 'FAILED';
                    $errorMsg = $e->getMessage();
                    Log::error('[GmailApiService] Gmail API exception: ' . $errorMsg);
                }
            } else {
                $status = 'FAILED';
                $errorMsg = 'Gmail OAuth2 access token unavailable';
            }
        } else {
            // In PHPUnit testing or when external email dispatch is disabled
            $providerRef = 'test_gmail_' . uniqid();
            $status = 'SENT';
        }

        // Record in PostgreSQL `notification_logs` table (Feature R14)
        try {
            DB::table('notification_logs')->insert([
                'repair_request_id' => $repairRequestId,
                'delivery_job_id' => $deliveryJobId,
                'channel' => 'EMAIL',
                'recipient' => $to,
                'subject' => $subject,
                'body' => strip_tags($html),
                'provider' => 'GmailAPI',
                'provider_ref' => $providerRef,
                'status' => $status,
                'sent_at' => $status === 'SENT' ? now() : null,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[GmailApiService] Could not insert notification_logs row: ' . $e->getMessage());
        }

        return [
            'ok' => $status === 'SENT',
            'status' => $status,
            'message_id' => $providerRef,
            'error' => $errorMsg,
        ];
    }

    /**
     * Send 6-Digit Email Verification / Authorization OTP.
     */
    public function sendVerificationOtpEmail(string $toEmail, string $recipientName, string $otp): array
    {
        $subject = "Meramot Security: Your Verification Code ({$otp})";
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #D7E2D2; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #214c34; margin-top: 0; margin-bottom: 8px;\">Verify Your Meramot Account</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Use the 6-digit verification code below to authorize your email address on Meramot:</p>
                <div style=\"background-color: #eef5ea; padding: 18px; border-radius: 12px; margin: 22px 0; text-align: center; border: 1px solid #cbe0c2;\">
                    <span style=\"font-size: 12px; text-transform: uppercase; letter-spacing: 0.15em; color: #5a7566; display: block; margin-bottom: 6px;\">Your Verification OTP</span>
                    <span style=\"font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #214c34;\">{$otp}</span>
                </div>
                <p style=\"font-size: 13px; color: #5e6d64;\">This code expires in <strong>10 minutes</strong>. If you did not request this code, you can safely ignore this email.</p>
                <p style=\"margin-top: 24px; font-size: 13px; color: #214c34;\">Thanks,<br/><strong>Meramot Official Team</strong></p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
    }

    /**
     * Feature R1: Send Delivery Agent Application Received / Resubmitted Acknowledgement.
     */
    public function sendDeliveryRegistrationAcknowledgement(
        string $personalEmail,
        string $recipientName,
        bool $isResubmission = false
    ): array {
        $statusUrl = rtrim((string) config('app.url', 'https://cse470-meramot-project.onrender.com'), '/') . '/delivery-agent/status';
        $subject = $isResubmission
            ? 'Updated Delivery Application Received - Pending Review | Meramot'
            : 'Delivery Registration Received - Under Review | Meramot';
        $heading = $isResubmission
            ? 'Updated Delivery Partner Application Received'
            : 'Delivery Partner Registration Received';

        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #D7E2D2; border-radius: 16px;\">
                <h2 style=\"color: #214c34; margin-top: 0;\">{$heading}</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>We have received your delivery partner onboarding application and verification documents.</p>
                <div style=\"background-color: #eef5ea; padding: 14px 18px; border-radius: 12px; margin: 18px 0; border: 1px solid #cbe0c2;\">
                    <p style=\"margin: 4px 0;\"><strong>What happens next:</strong></p>
                    <ul style=\"margin: 6px 0 0 18px; padding: 0;\">
                        <li>If <strong>accepted</strong>, your auto-generated unique <code>@meramotbd.com</code> company email and secure system password will be emailed here to <strong>" . e($personalEmail) . "</strong>.</li>
                        <li>If <strong>rejected</strong>, our admin verification team's reason will be emailed here and shown on the Check Application Status page so you can edit and resubmit anytime.</li>
                    </ul>
                </div>
                <p>You can check your application status or edit your application anytime at <a href=\"{$statusUrl}\">{$statusUrl}</a> using OTP verification on this personal email.</p>
                <p style=\"margin-top: 24px;\">Thank you,<br/><strong>Meramot Delivery Operations</strong></p>
            </div>
        ";

        return $this->sendEmail($personalEmail, $subject, $html);
    }

    /**
     * Feature R1: Send Official `@meramotbd.com` Company Email & System Password to Personal Email.
     * Used both upon one-time Admin Approval and on OTP-verified "Forgot Company Credentials".
     */
    public function sendDeliveryApprovalCredentials(
        string $personalEmail,
        string $recipientName,
        string $companyEmail,
        string $plainPassword,
        bool $isResend = false
    ): array {
        $loginUrl = rtrim((string) config('app.url', 'https://cse470-meramot-project.onrender.com'), '/') . '/delivery/login';
        $subject = $isResend
            ? 'Your Meramot Delivery Partner Company Credentials (Reminder)'
            : 'Delivery Partner Approved - Your @meramotbd.com Company Credentials';
        $heading = $isResend
            ? 'Your Meramot Company Login Credentials'
            : 'Delivery Partner Registration Approved!';
        $intro = $isResend
            ? 'As requested from the verified Check Application Status page, here are your assigned Meramot Delivery Partner company credentials:'
            : 'Congratulations! Your identity and driving license documents have been verified and approved by Meramot Logistics. Use your official company credentials below to sign in to the Delivery Partner Portal:';

        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #D7E2D2; border-radius: 16px;\">
                <h2 style=\"color: #214c34; margin-top: 0;\">{$heading}</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>{$intro}</p>
                <div style=\"background-color: #eef5ea; padding: 16px 20px; border-radius: 12px; margin: 20px 0; border: 1px solid #cbe0c2;\">
                    <p style=\"margin: 6px 0;\"><strong>Company Email:</strong> <code>" . e($companyEmail) . "</code></p>
                    <p style=\"margin: 6px 0;\"><strong>Company Password:</strong> <code>" . e($plainPassword) . "</code></p>
                </div>
                <p style=\"font-size: 12px; color: #5e6d64;\">Note: Your <code>@meramotbd.com</code> company email and system-generated password are fixed for security and cannot be changed manually. Keep this email safe.</p>
                <div style=\"margin: 24px 0; text-align: center;\">
                    <a href=\"{$loginUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 26px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Sign In to Delivery Partner Portal</a>
                </div>
                <p style=\"margin-top: 20px;\">Safe riding,<br/><strong>Meramot Logistics Team</strong></p>
            </div>
        ";

        return $this->sendEmail($personalEmail, $subject, $html);
    }

    /**
     * Feature R1: Send Delivery Agent Rejection Notice with Reason to Personal Email.
     */
    public function sendDeliveryRejectionNotice(string $personalEmail, string $recipientName, string $reason): array
    {
        $statusUrl = rtrim((string) config('app.url', 'https://cse470-meramot-project.onrender.com'), '/') . '/delivery-agent/status';
        $subject = 'Update on Your Meramot Delivery Partner Application';
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #f3d4d4; border-radius: 16px;\">
                <h2 style=\"color: #991b1b; margin-top: 0;\">Action Required: Delivery Partner Application</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Our verification team reviewed your delivery partner application and requires the following correction before approval:</p>
                <div style=\"background-color: #fef2f2; padding: 14px 18px; border-radius: 10px; margin: 18px 0; color: #7f1d1d; border: 1px solid #fecaca;\">
                    <strong>Reason for Rejection:</strong> " . e($reason) . "
                </div>
                <p>Verify your personal email via OTP on the <strong>Check Application Status</strong> page to edit and resubmit your application for fresh admin review:</p>
                <div style=\"margin: 22px 0; text-align: center;\">
                    <a href=\"{$statusUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Check Status &amp; Edit Application</a>
                </div>
                <p style=\"margin-top: 20px;\">Regards,<br/><strong>Meramot Logistics Team</strong></p>
            </div>
        ";

        return $this->sendEmail($personalEmail, $subject, $html);
    }

    /**
     * Feature R1: Send 6-Digit OTP to Personal Email for "Check Application Status" Verification.
     */
    public function sendDeliveryStatusOtpEmail(string $personalEmail, string $recipientName, string $otp): array
    {
        $subject = "Meramot Delivery Partner: Application Status OTP ({$otp})";
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #D7E2D2; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #214c34; margin-top: 0; margin-bottom: 8px;\">Verify Your Application Status Check</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Enter the 6-digit OTP below to view your Meramot Delivery Partner application status, edit your application, or retrieve your company credentials:</p>
                <div style=\"background-color: #eef5ea; padding: 18px; border-radius: 12px; margin: 22px 0; text-align: center; border: 1px solid #cbe0c2;\">
                    <span style=\"font-size: 12px; text-transform: uppercase; letter-spacing: 0.15em; color: #5a7566; display: block; margin-bottom: 6px;\">Application Status OTP</span>
                    <span style=\"font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #214c34;\">{$otp}</span>
                </div>
                <p style=\"font-size: 13px; color: #5e6d64;\">This code expires in <strong>10 minutes</strong>.</p>
                <p style=\"margin-top: 24px; font-size: 13px; color: #214c34;\">Thanks,<br/><strong>Meramot Delivery Operations</strong></p>
            </div>
        ";

        return $this->sendEmail($personalEmail, $subject, $html);
    }

    /**
     * Feature R11: Send Vendor Onboarding Approval Email.
     */
    public function sendVendorApprovalEmail(string $toEmail, string $vendorName, string $shopName): array
    {
        $loginUrl = rtrim((string) config('app.url', 'https://cse470-meramot-project.onrender.com'), '/') . '/login';
        $subject = "Welcome to Meramot — {$shopName} is Approved!";
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #D7E2D2; border-radius: 16px;\">
                <h2 style=\"color: #214c34; margin-top: 0;\">Welcome to Meramot, " . e($vendorName) . "!</h2>
                <p>Your repair workshop <strong>" . e($shopName) . "</strong> has passed administrative trade license verification and is now live on the Meramot Marketplace.</p>
                <div style=\"margin: 24px 0; text-align: center;\">
                    <a href=\"{$loginUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 26px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Open Vendor Dashboard</a>
                </div>
                <p style=\"margin-top: 20px;\">Thanks,<br/><strong>Meramot Official Team</strong></p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
    }

    /**
     * Feature R11: Send Vendor Application Rejection / Remediation Notice Email.
     */
    public function sendVendorRejectionEmail(string $toEmail, string $vendorName, string $shopName, string $reason): array
    {
        $onboardingUrl = rtrim((string) config('app.url', 'https://cse470-meramot-project.onrender.com'), '/') . '/vendor/onboarding';
        $subject = "Action Required: Update on Your Meramot Vendor Application ({$shopName})";
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #f3d4d4; border-radius: 16px;\">
                <h2 style=\"color: #991b1b; margin-top: 0;\">Action Required: Vendor Application Review</h2>
                <p>Hello <strong>" . e($vendorName) . "</strong>,</p>
                <p>Our verification team reviewed your vendor onboarding application for <strong>" . e($shopName) . "</strong> and requires the following correction before approval:</p>
                <div style=\"background-color: #fef2f2; padding: 14px 18px; border-radius: 10px; margin: 18px 0; color: #7f1d1d; border: 1px solid #fecaca;\">
                    <strong>Reason / Remediation Note:</strong> " . e($reason) . "
                </div>
                <p>Sign in to your Meramot Vendor Onboarding page to update your trade credentials or location details and resubmit for fresh admin review:</p>
                <div style=\"margin: 22px 0; text-align: center;\">
                    <a href=\"{$onboardingUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Edit &amp; Resubmit Application</a>
                </div>
                <p style=\"margin-top: 20px;\">Regards,<br/><strong>Meramot Official Team</strong></p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
    }

    /**
     * Feature R19: Send Moderation Notice / Account Ban Alert Email (offensive review language).
     */
    public function sendModerationSanctionEmail(
        string $toEmail,
        string $recipientName,
        string $shopName,
        string $reason,
        bool $isBanned,
        ?string $reviewExcerpt = null
    ): array {
        $requestsUrl = rtrim((string) config('app.url', 'https://cse470-meramot-project.onrender.com'), '/') . '/requests';
        $subject = $isBanned
            ? 'Meramot Governance Alert: Offensive Review Deleted & Account Restricted'
            : 'Meramot Governance Notice: Your Review Was Removed';
        $heading = $isBanned
            ? 'Review Removed & Ordering Privileges Restricted'
            : 'Workshop Review Removed by Moderation';

        $excerptHtml = $reviewExcerpt
            ? "<p style=\"font-size: 12px; color: #5e6d64; margin-top: 8px;\"><strong>Removed review excerpt:</strong> &ldquo;" . e($reviewExcerpt) . "&rdquo;</p>"
            : '';

        $restrictionHtml = $isBanned
            ? "
                <div style=\"background-color: #fff7ed; padding: 14px 18px; border-radius: 10px; margin: 16px 0; color: #7c2d12; border: 1px solid #fed7aa;\">
                    <p style=\"margin: 0 0 6px 0;\"><strong>Your account ordering and posting privileges have been restricted</strong> due to offensive language in a workshop review.</p>
                    <p style=\"margin: 0;\">You can still sign in to Meramot anytime to view and track your <strong>ongoing repair orders and past orders</strong> at <a href=\"{$requestsUrl}\">/requests</a>, but placing new service orders, adding items to cart, posting repair requests, and submitting reviews are disabled.</p>
                </div>
            "
            : '';

        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #f3d4d4; border-radius: 16px;\">
                <h2 style=\"color: #991b1b; margin-top: 0;\">{$heading}</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>A Meramot platform administrator reviewed your feedback on <strong>" . e($shopName) . "</strong> and removed the review under our Community Guidelines.</p>
                <div style=\"background-color: #fef2f2; padding: 14px 18px; border-radius: 10px; margin: 16px 0; color: #7f1d1d; border: 1px solid #fecaca;\">
                    <strong>Moderation Reason:</strong> " . e($reason) . "
                    {$excerptHtml}
                </div>
                {$restrictionHtml}
                <div style=\"margin: 22px 0; text-align: center;\">
                    <a href=\"{$requestsUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">View Ongoing &amp; Past Orders</a>
                </div>
                <p style=\"margin-top: 20px;\">Regards,<br/><strong>Meramot Moderation &amp; Governance Team</strong></p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
    }

    /**
     * Feature R19: Send Account Restriction Lifted / Unban Email.
     */
    public function sendAccountUnbannedEmail(string $toEmail, string $recipientName): array
    {
        $loginUrl = rtrim((string) config('app.url', 'https://cse470-meramot-project.onrender.com'), '/') . '/login';
        $subject = 'Meramot Governance Update: Account Restrictions Lifted';
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #D7E2D2; border-radius: 16px;\">
                <h2 style=\"color: #214c34; margin-top: 0;\">Your Meramot Ordering &amp; Request Access Is Restored</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>An administrator has lifted the moderation restriction on your Meramot account. Full access to order repair services, post repair requests, and use the Direct-Order Cart has been restored.</p>
                <div style=\"margin: 22px 0; text-align: center;\">
                    <a href=\"{$loginUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Open Meramot Marketplace</a>
                </div>
                <p style=\"margin-top: 20px;\">Thanks,<br/><strong>Meramot Moderation &amp; Governance Team</strong></p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
    }
}

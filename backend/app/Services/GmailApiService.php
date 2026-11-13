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
     * Send 6-Digit Email Verification / Authorization Code.
     */
    public function sendVerificationOtpEmail(string $toEmail, string $recipientName, string $otp): array
    {
        $subject = "Meramot — Email Verification Code ({$otp})";
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 580px; margin: 0 auto; padding: 26px; border: 1px solid #D7E2D2; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #214c34; margin-top: 0; margin-bottom: 10px;\">Verify Your Email Address</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Please enter the six-digit verification code below to confirm your email address on Meramot:</p>
                <div style=\"background-color: #eef5ea; padding: 18px; border-radius: 12px; margin: 22px 0; text-align: center; border: 1px solid #cbe0c2;\">
                    <span style=\"font-size: 11px; text-transform: uppercase; letter-spacing: 0.16em; color: #5a7566; display: block; margin-bottom: 6px; font-weight: bold;\">Verification Code</span>
                    <span style=\"font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #214c34;\">{$otp}</span>
                </div>
                <p style=\"font-size: 13px; color: #5e6d64;\">This code remains valid for <strong>10 minutes</strong>. For your security, never share this code with anyone. If you did not initiate this request, no further action is required.</p>
                <p style=\"margin-top: 22px; font-size: 13px; color: #214c34;\">Sincerely,<br/><strong>Meramot Trust &amp; Security</strong></p>
                <p style=\"margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;\">This is an automated operational message from Meramot. Please do not reply directly unless instructed.</p>
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
            ? 'Meramot Logistics — Updated Partner Application Received'
            : 'Meramot Logistics — Delivery Partner Application Received';
        $heading = $isResubmission
            ? 'Updated Application Received'
            : 'Application Received for Verification';

        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 580px; margin: 0 auto; padding: 26px; border: 1px solid #D7E2D2; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #214c34; margin-top: 0;\">{$heading}</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Thank you for submitting your Delivery Partner application and supporting credentials. Our verification team has queued your submission for compliance review.</p>
                <div style=\"background-color: #eef5ea; padding: 14px 18px; border-radius: 12px; margin: 18px 0; border: 1px solid #cbe0c2;\">
                    <p style=\"margin: 4px 0;\"><strong>Next Steps:</strong></p>
                    <ul style=\"margin: 6px 0 0 18px; padding: 0;\">
                        <li><strong>Upon Approval:</strong> Your official <code>@meramotbd.com</code> account credentials will be issued and delivered to <strong>" . e($personalEmail) . "</strong>.</li>
                        <li><strong>If Revisions Are Needed:</strong> Verification notes will be sent to this email address and made available on the Application Status portal so you may update and resubmit your documents.</li>
                    </ul>
                </div>
                <p>You may track your review progress at any time via <a href=\"{$statusUrl}\" style=\"color: #214c34; font-weight: bold;\">{$statusUrl}</a> using email verification.</p>
                <p style=\"margin-top: 22px;\">Sincerely,<br/><strong>Meramot Logistics Operations</strong></p>
                <p style=\"margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;\">This is an automated operational message from Meramot. Please do not reply directly unless instructed.</p>
            </div>
        ";

        return $this->sendEmail($personalEmail, $subject, $html);
    }

    /**
     * Feature R1: Send Official `@meramotbd.com` Company Email & System Password to Personal Email.
     * Used both upon one-time Admin Approval and on OTP-verified Credential Retrieval.
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
            ? 'Meramot Logistics — Your Delivery Partner Credentials'
            : 'Meramot Logistics — Delivery Partner Application Approved';
        $heading = $isResend
            ? 'Your Delivery Partner Login Credentials'
            : 'Welcome to Meramot Logistics';
        $intro = $isResend
            ? 'Per your verified request on the Application Status portal, here are your assigned Meramot Logistics partner credentials:'
            : 'Your identity documentation and driving credentials have been verified and approved by Meramot Logistics. Please use your assigned partner credentials below to sign in to the Delivery Partner Portal:';

        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 580px; margin: 0 auto; padding: 26px; border: 1px solid #D7E2D2; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #214c34; margin-top: 0;\">{$heading}</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>{$intro}</p>
                <div style=\"background-color: #eef5ea; padding: 16px 20px; border-radius: 12px; margin: 20px 0; border: 1px solid #cbe0c2;\">
                    <p style=\"margin: 6px 0;\"><strong>Official Login Email:</strong> <code>" . e($companyEmail) . "</code></p>
                    <p style=\"margin: 6px 0;\"><strong>System Password:</strong> <code>" . e($plainPassword) . "</code></p>
                </div>
                <p style=\"font-size: 12px; color: #5e6d64;\">For fleet security, your assigned <code>@meramotbd.com</code> credentials are managed centrally by Meramot Logistics. Please store this message securely.</p>
                <div style=\"margin: 24px 0; text-align: center;\">
                    <a href=\"{$loginUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 26px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Sign In to Partner Portal</a>
                </div>
                <p style=\"margin-top: 20px;\">Sincerely,<br/><strong>Meramot Logistics Operations</strong></p>
                <p style=\"margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;\">This is an automated operational message from Meramot. Please do not reply directly unless instructed.</p>
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
        $subject = 'Meramot Logistics — Action Required on Your Partner Application';
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 580px; margin: 0 auto; padding: 26px; border: 1px solid #f3d4d4; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #991b1b; margin-top: 0;\">Verification Update: Additional Information Required</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Our compliance team has reviewed your Delivery Partner application and requires the following update before approval can be completed:</p>
                <div style=\"background-color: #fef2f2; padding: 14px 18px; border-radius: 10px; margin: 18px 0; color: #7f1d1d; border: 1px solid #fecaca;\">
                    <strong>Verification Feedback:</strong> " . e($reason) . "
                </div>
                <p>Please verify your email address on the <strong>Check Application Status</strong> portal to update your details and resubmit your application for review.</p>
                <div style=\"margin: 22px 0; text-align: center;\">
                    <a href=\"{$statusUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Review &amp; Update Application</a>
                </div>
                <p style=\"margin-top: 20px;\">Sincerely,<br/><strong>Meramot Logistics Operations</strong></p>
                <p style=\"margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;\">This is an automated operational message from Meramot. Please do not reply directly unless instructed.</p>
            </div>
        ";

        return $this->sendEmail($personalEmail, $subject, $html);
    }

    /**
     * Feature R1: Send 6-Digit Verification Code to Personal Email for "Check Application Status".
     */
    public function sendDeliveryStatusOtpEmail(string $personalEmail, string $recipientName, string $otp): array
    {
        $subject = "Meramot Logistics — Application Status Verification Code ({$otp})";
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 580px; margin: 0 auto; padding: 26px; border: 1px solid #D7E2D2; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #214c34; margin-top: 0; margin-bottom: 10px;\">Verify Your Identity</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Enter the six-digit verification code below to view your Delivery Partner application status, review compliance notes, or retrieve your account credentials:</p>
                <div style=\"background-color: #eef5ea; padding: 18px; border-radius: 12px; margin: 22px 0; text-align: center; border: 1px solid #cbe0c2;\">
                    <span style=\"font-size: 11px; text-transform: uppercase; letter-spacing: 0.16em; color: #5a7566; display: block; margin-bottom: 6px; font-weight: bold;\">Verification Code</span>
                    <span style=\"font-size: 32px; font-weight: 800; letter-spacing: 8px; color: #214c34;\">{$otp}</span>
                </div>
                <p style=\"font-size: 13px; color: #5e6d64;\">This code remains valid for <strong>10 minutes</strong>. If you did not request this code, please disregard this email.</p>
                <p style=\"margin-top: 22px; font-size: 13px; color: #214c34;\">Sincerely,<br/><strong>Meramot Logistics Operations</strong></p>
                <p style=\"margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;\">This is an automated operational message from Meramot. Please do not reply directly unless instructed.</p>
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
        $subject = "Meramot Partner Network — {$shopName} Has Been Approved";
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 580px; margin: 0 auto; padding: 26px; border: 1px solid #D7E2D2; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #214c34; margin-top: 0;\">Your Workshop Application Is Approved</h2>
                <p>Congratulations, <strong>" . e($vendorName) . "</strong>.</p>
                <p>Your workshop, <strong>" . e($shopName) . "</strong>, has completed administrative trade license and identity verification. Sign in to finalize your service catalog, pricing, and storefront settings.</p>
                <div style=\"margin: 24px 0; text-align: center;\">
                    <a href=\"{$loginUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 26px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Access Vendor Portal</a>
                </div>
                <p style=\"margin-top: 20px;\">Sincerely,<br/><strong>Meramot Partner Operations</strong></p>
                <p style=\"margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;\">This is an automated operational message from Meramot. Please do not reply directly unless instructed.</p>
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
        $subject = "Meramot Partner Network — Action Required for {$shopName}";
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 580px; margin: 0 auto; padding: 26px; border: 1px solid #f3d4d4; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #991b1b; margin-top: 0;\">Verification Update: Application Revision Required</h2>
                <p>Hello <strong>" . e($vendorName) . "</strong>,</p>
                <p>Our compliance team has reviewed the partner application for <strong>" . e($shopName) . "</strong> and requires the following clarification or document update before approval:</p>
                <div style=\"background-color: #fef2f2; padding: 14px 18px; border-radius: 10px; margin: 18px 0; color: #7f1d1d; border: 1px solid #fecaca;\">
                    <strong>Verification Notes:</strong> " . e($reason) . "
                </div>
                <p>Please sign in to your Vendor Onboarding portal to update the requested details and resubmit your application for review.</p>
                <div style=\"margin: 22px 0; text-align: center;\">
                    <a href=\"{$onboardingUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Review &amp; Resubmit Application</a>
                </div>
                <p style=\"margin-top: 20px;\">Sincerely,<br/><strong>Meramot Partner Operations</strong></p>
                <p style=\"margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;\">This is an automated operational message from Meramot. Please do not reply directly unless instructed.</p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
    }

    /**
     * Feature R19: Send Moderation Notice / Account Restriction Alert Email.
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
            ? 'Meramot Trust & Safety — Review Removed & Account Restricted'
            : 'Meramot Trust & Safety — Notice of Review Removal';
        $heading = $isBanned
            ? 'Notice of Content Removal & Account Restriction'
            : 'Notice of Content Removal';

        $excerptHtml = $reviewExcerpt
            ? "<p style=\"font-size: 12px; color: #5e6d64; margin: 8px 0 0 0;\"><strong>Removed Content Excerpt:</strong> &ldquo;" . e($reviewExcerpt) . "&rdquo;</p>"
            : '';

        $restrictionHtml = $isBanned
            ? "
                <div style=\"background-color: #fff7ed; padding: 14px 18px; border-radius: 10px; margin: 16px 0; color: #7c2d12; border: 1px solid #fed7aa;\">
                    <p style=\"margin: 0 0 6px 0;\"><strong>Account Restriction Applied:</strong> New ordering and posting privileges have been suspended on your account.</p>
                    <p style=\"margin: 0;\">You may continue to sign in to Meramot at any time to view and track your <strong>ongoing and completed orders</strong> via <a href=\"{$requestsUrl}\" style=\"color: #7c2d12; font-weight: bold;\">My Orders &amp; Repair Requests</a>. However, placing new service bookings, using the cart, submitting repair requests, and posting reviews are disabled while this restriction remains in effect.</p>
                </div>
            "
            : '';

        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 580px; margin: 0 auto; padding: 26px; border: 1px solid #f3d4d4; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #991b1b; margin-top: 0;\">{$heading}</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Following a Trust &amp; Safety review, your customer review submitted for <strong>" . e($shopName) . "</strong> has been removed in accordance with the Meramot Community &amp; Marketplace Guidelines.</p>
                <div style=\"background-color: #fef2f2; padding: 14px 18px; border-radius: 10px; margin: 16px 0; color: #7f1d1d; border: 1px solid #fecaca;\">
                    <strong>Reason for Action:</strong> " . e($reason) . "
                    {$excerptHtml}
                </div>
                {$restrictionHtml}
                <div style=\"margin: 22px 0; text-align: center;\">
                    <a href=\"{$requestsUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">View Ongoing &amp; Past Orders</a>
                </div>
                <p style=\"margin-top: 20px;\">Sincerely,<br/><strong>Meramot Trust &amp; Safety Team</strong></p>
                <p style=\"margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;\">This is an automated operational message from Meramot. Please do not reply directly unless instructed.</p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
    }

    /**
     * Feature R19: Send Account Restriction Lifted Confirmation Email.
     */
    public function sendAccountUnbannedEmail(string $toEmail, string $recipientName): array
    {
        $loginUrl = rtrim((string) config('app.url', 'https://cse470-meramot-project.onrender.com'), '/') . '/login';
        $subject = 'Meramot Trust & Safety — Account Access Restored';
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.65; max-width: 580px; margin: 0 auto; padding: 26px; border: 1px solid #D7E2D2; border-radius: 16px; background: #ffffff;\">
                <h2 style=\"color: #214c34; margin-top: 0;\">Your Account Privileges Have Been Restored</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Following an administrative review, the restriction on your Meramot account has been lifted. Full access to book repair services, submit custom repair requests, use the cart, and publish verified reviews is now active.</p>
                <div style=\"margin: 22px 0; text-align: center;\">
                    <a href=\"{$loginUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 24px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Sign In to Meramot</a>
                </div>
                <p style=\"margin-top: 20px;\">Sincerely,<br/><strong>Meramot Trust &amp; Safety Team</strong></p>
                <p style=\"margin-top: 18px; font-size: 11px; color: #7a8a80; border-top: 1px solid #e5ede2; padding-top: 12px;\">This is an automated operational message from Meramot. Please do not reply directly unless instructed.</p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
    }
}

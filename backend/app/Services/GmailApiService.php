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
     * Feature R1: Send Delivery Agent Application Received Acknowledgement.
     */
    public function sendDeliveryRegistrationAcknowledgement(string $toEmail, string $recipientName): array
    {
        $subject = 'Delivery Registration Received - Under Review | Meramot';
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #D7E2D2; border-radius: 16px;\">
                <h2 style=\"color: #214c34; margin-top: 0;\">Delivery Partner Registration Received</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>We have received your delivery partner onboarding application and NID/license documents.</p>
                <p>Our logistics verification desk will review your submission and email your official Rider Portal login credentials once approved.</p>
                <p style=\"margin-top: 24px;\">Thank you,<br/><strong>Meramot Delivery Operations</strong></p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
    }

    /**
     * Feature R1: Send Automated Delivery Partner Login Credentials on Admin Approval.
     */
    public function sendDeliveryApprovalCredentials(
        string $toEmail,
        string $recipientName,
        string $username,
        string $plainPassword
    ): array {
        $loginUrl = rtrim((string) config('app.url', 'https://cse470-meramot-project.onrender.com'), '/') . '/login';
        $subject = 'Delivery Partner Approved - Your Meramot Login Credentials';
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #D7E2D2; border-radius: 16px;\">
                <h2 style=\"color: #214c34; margin-top: 0;\">Delivery Partner Registration Approved!</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Congratulations! Your NID and driving license credentials have been verified and approved by Meramot Logistics.</p>
                <p>Use the generated credentials below to sign in to the Meramot Delivery Partner Portal:</p>
                <div style=\"background-color: #eef5ea; padding: 16px 20px; border-radius: 12px; margin: 20px 0; border: 1px solid #cbe0c2;\">
                    <p style=\"margin: 4px 0;\"><strong>Login Email:</strong> " . e($toEmail) . "</p>
                    <p style=\"margin: 4px 0;\"><strong>Rider Username:</strong> <code>" . e($username) . "</code></p>
                    <p style=\"margin: 4px 0;\"><strong>Generated Password:</strong> <code>" . e($plainPassword) . "</code></p>
                </div>
                <div style=\"margin: 24px 0; text-align: center;\">
                    <a href=\"{$loginUrl}\" style=\"background-color: #214c34; color: #ffffff; padding: 12px 26px; text-decoration: none; border-radius: 999px; font-weight: bold; display: inline-block;\">Sign In to Rider Portal</a>
                </div>
                <p style=\"margin-top: 20px;\">Safe riding,<br/><strong>Meramot Logistics Team</strong></p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
    }

    /**
     * Feature R1: Send Delivery Agent Rejection Notice with Reason.
     */
    public function sendDeliveryRejectionNotice(string $toEmail, string $recipientName, string $reason): array
    {
        $reapplyUrl = rtrim((string) config('app.url', 'https://cse470-meramot-project.onrender.com'), '/') . '/delivery-agent/register';
        $subject = 'Update on Your Meramot Delivery Partner Application';
        $html = "
            <div style=\"font-family: Arial, sans-serif; color: #173626; line-height: 1.6; max-width: 580px; margin: 0 auto; padding: 24px; border: 1px solid #f3d4d4; border-radius: 16px;\">
                <h2 style=\"color: #991b1b; margin-top: 0;\">Action Required: Delivery Partner Application</h2>
                <p>Hello <strong>" . e($recipientName) . "</strong>,</p>
                <p>Our verification team reviewed your delivery partner application and requires the following correction before approval:</p>
                <div style=\"background-color: #fef2f2; padding: 14px 18px; border-radius: 10px; margin: 18px 0; color: #7f1d1d; border: 1px solid #fecaca;\">
                    <strong>Reason:</strong> " . e($reason) . "
                </div>
                <p>You can update your documents and re-submit your application anytime at <a href=\"{$reapplyUrl}\">{$reapplyUrl}</a>.</p>
                <p style=\"margin-top: 20px;\">Regards,<br/><strong>Meramot Logistics Team</strong></p>
            </div>
        ";

        return $this->sendEmail($toEmail, $subject, $html);
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
}

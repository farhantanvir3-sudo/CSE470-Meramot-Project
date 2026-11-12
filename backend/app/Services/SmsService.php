<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * BulkSMS BD Transactional SMS & Phone OTP Service
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Dispatches 6-digit phone verification OTPs and marketplace SMS alerts via
 * BulkSMS BD HTTP API, recording audit rows in `notification_logs`.
 * ============================================================================
 */
class SmsService
{
    private const BULKSMS_BASE_URL = 'http://bulksmsbd.net/api/smsapi';

    public function sendSms(string $toPhone, string $message, string $eventKey = 'SMS_NOTIFICATION'): array
    {
        $rawPhone = trim($toPhone);
        if ($rawPhone === '') {
            return ['sent' => false, 'reason' => 'missing_phone'];
        }

        // Normalize Bangladeshi mobile numbers (01XXXXXXXXX -> 8801XXXXXXXXX)
        $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            $digits = '88' . $digits;
        }

        if (app()->environment('testing')) {
            $this->recordLog($digits, $eventKey, $message, 'SENT', 'test_sms_' . uniqid());
            return ['sent' => true, 'simulated' => true];
        }

        $apiKey = (string) config('services.bulksms.api_key', '');
        $senderId = (string) config('services.bulksms.sender_id', '8809648908088');

        if ($apiKey === '') {
            $this->recordLog($digits, $eventKey, $message, 'SKIPPED', null);
            return ['sent' => false, 'reason' => 'missing_bulksms_api_key'];
        }

        try {
            $response = Http::timeout(10)->get(self::BULKSMS_BASE_URL, [
                'api_key' => $apiKey,
                'type' => 'text',
                'number' => $digits,
                'senderid' => $senderId,
                'message' => $message,
            ]);

            $status = $response->successful() ? 'SENT' : 'FAILED';
            $this->recordLog($digits, $eventKey, $message, $status, 'bulksms_' . $response->status());

            return [
                'sent' => $response->successful(),
                'response' => $response->json() ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning('[SmsService] SMS dispatch failed: ' . $e->getMessage());
            $this->recordLog($digits, $eventKey, $message, 'FAILED', null);
            return ['sent' => false, 'reason' => $e->getMessage()];
        }
    }

    public function sendVerificationOtpSms(string $toPhone, string $otp): array
    {
        $msg = "Your Meramot phone verification OTP is {$otp}. Valid for 10 minutes. Do not share this code.";
        return $this->sendSms($toPhone, $msg, 'AUTH_PHONE_OTP');
    }

    private function recordLog(string $recipient, string $eventKey, string $message, string $status, ?string $providerRef): void
    {
        try {
            if (!Schema::hasTable('notification_logs')) {
                return;
            }

            DB::transaction(function () use ($recipient, $eventKey, $message, $status, $providerRef) {
                DB::table('notification_logs')->insert([
                    'repair_request_id' => null,
                    'delivery_job_id' => null,
                    'channel' => 'SMS',
                    'recipient' => $recipient,
                    'subject' => $eventKey,
                    'body' => mb_substr($message, 0, 500),
                    'provider' => 'BulkSMSBD',
                    'provider_ref' => $providerRef,
                    'status' => $status,
                    'sent_at' => $status === 'SENT' ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            // Savepoint-protected best-effort logging
            Log::warning('[SmsService] Could not insert notification_logs row: ' . $e->getMessage());
        }
    }
}

<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * ============================================================================
 * BulkSMS BD Transactional SMS & Phone OTP Service (Feature R14)
 * ============================================================================
 * Assigned Developer: Farhan Tanvir [Student ID: 22299179] (Team Lead)
 * Dispatches 6-digit phone verification OTPs and marketplace SMS alerts via
 * BulkSMS BD HTTP API (`http://bulksmsbd.net/api/smsapi` & `getBalanceApi`),
 * with automatic sandbox simulation when testing, on demo numbers, or when
 * IP whitelisting / credit balance prevents live carrier delivery.
 * ============================================================================
 */
class SmsService
{
    private const BULKSMS_BASE_URL = 'http://bulksmsbd.net/api/smsapi';
    private const BULKSMS_BALANCE_URL = 'http://bulksmsbd.net/api/getBalanceApi';

    public const ERROR_CODE_MAP = [
        202  => 'SMS Submitted Successfully',
        1001 => 'Invalid Number',
        1002 => 'Sender ID not correct or disabled',
        1003 => 'Please Required all fields / Contact System Administrator',
        1005 => 'Internal Error',
        1006 => 'Balance Validity Not Available',
        1007 => 'Balance Insufficient',
        1011 => 'User ID not found',
        1012 => 'Masking SMS must be sent in Bengali',
        1013 => 'Sender ID has not found Gateway by API key',
        1014 => 'Sender Type Name not found using this sender by API key',
        1015 => 'Sender ID has not found Any Valid Gateway by API key',
        1016 => 'Sender Type Name Active Price Info not found by this sender ID',
        1017 => 'Sender Type Name Price Info not found by this sender ID',
        1018 => 'The Owner of this Account is disabled',
        1019 => 'The Sender Type Name Price of this Account is disabled',
        1020 => 'The parent of this account is not found',
        1021 => 'The parent active Sender Type Name price of this account is not found',
        1031 => 'Your Account Not Verified, Please Contact Administrator',
        1032 => 'IP Not whitelisted',
    ];

    public function normalizeBangladeshiPhone(string $rawPhone): string
    {
        $digits = preg_replace('/\D+/', '', trim($rawPhone)) ?? '';
        if (str_starts_with($digits, '01') && strlen($digits) === 11) {
            return '88' . $digits;
        }
        return $digits;
    }

    public function isDemoOrPlaceholderPhone(string $normalizedDigits): bool
    {
        if ($normalizedDigits === '') {
            return true;
        }

        $local = str_starts_with($normalizedDigits, '88')
            ? substr($normalizedDigits, 2)
            : $normalizedDigits;

        $demoPrefixes = [
            '0170000',
            '0171100',
            '0180000',
            '0190000',
            '0160000',
            '0150000',
            '0130000',
        ];

        foreach ($demoPrefixes as $prefix) {
            if (str_starts_with($local, $prefix)) {
                return true;
            }
        }

        return false;
    }

    protected function isPlaceholderKey(string $apiKey): bool
    {
        $trimmed = strtolower(trim($apiKey));
        return $trimmed === ''
            || str_starts_with($trimmed, 'your_')
            || str_contains($trimmed, 'placeholder')
            || $trimmed === 'changeme';
    }

    public function sendSms(
        string $toPhone,
        string $message,
        string $eventKey = 'SMS_NOTIFICATION',
        ?int $repairRequestId = null,
        ?int $deliveryJobId = null,
        bool $forceSimulate = false
    ): array {
        $digits = $this->normalizeBangladeshiPhone($toPhone);
        if ($digits === '') {
            return ['sent' => false, 'simulated' => false, 'reason' => 'missing_phone'];
        }

        $apiKey = (string) config('services.bulksms.api_key', '');
        $senderId = (string) config('services.bulksms.sender_id', '8809648908088');
        $simulateConfig = (bool) config('services.bulksms.simulate', false);

        $shouldSimulate = $forceSimulate
            || app()->environment('testing')
            || $simulateConfig
            || $this->isPlaceholderKey($apiKey)
            || $this->isDemoOrPlaceholderPhone($digits);

        if ($shouldSimulate) {
            $simRef = 'SIM-SMS-' . strtoupper(substr(md5($digits . microtime(true) . $eventKey), 0, 10));
            $this->recordLog(
                $digits,
                $eventKey,
                $message,
                'SENT',
                $simRef,
                'BulkSMSBD (Simulated)',
                $repairRequestId,
                $deliveryJobId
            );

            return [
                'sent' => true,
                'simulated' => true,
                'code' => 202,
                'provider_ref' => $simRef,
                'reason' => $this->isPlaceholderKey($apiKey) ? 'simulated_no_api_key' : 'simulated_sandbox_mode',
            ];
        }

        try {
            $response = Http::timeout(8)->get(self::BULKSMS_BASE_URL, [
                'api_key' => $apiKey,
                'type' => 'text',
                'number' => $digits,
                'senderid' => $senderId,
                'message' => $message,
            ]);

            $json = $response->json();
            $code = is_array($json) && isset($json['response_code'])
                ? (int) $json['response_code']
                : ($response->successful() ? 202 : 1005);

            if ($code === 202) {
                $messageId = (string) ($json['message_id'] ?? ('bulksms_' . uniqid()));
                $this->recordLog(
                    $digits,
                    $eventKey,
                    $message,
                    'SENT',
                    $messageId,
                    'BulkSMSBD',
                    $repairRequestId,
                    $deliveryJobId
                );

                return [
                    'sent' => true,
                    'simulated' => false,
                    'code' => 202,
                    'provider_ref' => $messageId,
                    'response' => $json ?? $response->body(),
                ];
            }

            // Graceful fallback to simulated sandbox delivery when account lacks IP whitelist (1032) or credits (1007)
            if (in_array($code, [1006, 1007, 1031, 1032], true)) {
                $fallbackReason = self::ERROR_CODE_MAP[$code] ?? ('Code ' . $code);
                $simRef = 'SIM-SMS-' . $code . '-' . strtoupper(substr(md5($digits . microtime(true)), 0, 8));
                Log::info("[SmsService] BulkSMS BD returned {$code} ({$fallbackReason}); recorded simulated fallback {$simRef}.");

                $this->recordLog(
                    $digits,
                    $eventKey,
                    $message,
                    'SENT',
                    $simRef,
                    'BulkSMSBD (Simulated)',
                    $repairRequestId,
                    $deliveryJobId
                );

                return [
                    'sent' => true,
                    'simulated' => true,
                    'code' => $code,
                    'provider_ref' => $simRef,
                    'reason' => $fallbackReason,
                ];
            }

            $errorDetail = self::ERROR_CODE_MAP[$code] ?? ($json['error_message'] ?? 'SMS gateway rejected request');
            $this->recordLog(
                $digits,
                $eventKey,
                $message,
                'FAILED',
                'ERR-' . $code,
                'BulkSMSBD',
                $repairRequestId,
                $deliveryJobId
            );

            return [
                'sent' => false,
                'simulated' => false,
                'code' => $code,
                'reason' => $errorDetail,
                'response' => $json ?? $response->body(),
            ];
        } catch (\Throwable $e) {
            Log::warning('[SmsService] Live SMS connection failed, falling back to simulated log: ' . $e->getMessage());
            $simRef = 'SIM-SMS-OFFLINE-' . strtoupper(substr(md5($digits . microtime(true)), 0, 8));
            $this->recordLog(
                $digits,
                $eventKey,
                $message,
                'SENT',
                $simRef,
                'BulkSMSBD (Simulated)',
                $repairRequestId,
                $deliveryJobId
            );

            return [
                'sent' => true,
                'simulated' => true,
                'code' => 202,
                'provider_ref' => $simRef,
                'reason' => 'network_fallback_simulated',
            ];
        }
    }

    public function sendVerificationOtpSms(string $toPhone, string $otp): array
    {
        $msg = "Your Meramot phone verification OTP is {$otp}. Valid for 10 minutes. Do not share this code.";
        return $this->sendSms($toPhone, $msg, 'AUTH_PHONE_OTP');
    }

    /**
     * Query BulkSMS BD account balance (`http://bulksmsbd.net/api/getBalanceApi`).
     */
    public function getCreditBalance(): array
    {
        $apiKey = (string) config('services.bulksms.api_key', '');
        $senderId = (string) config('services.bulksms.sender_id', '8809648908088');
        $simulateConfig = (bool) config('services.bulksms.simulate', false);

        if (app()->environment('testing') || $simulateConfig || $this->isPlaceholderKey($apiKey)) {
            return [
                'ok' => true,
                'simulated' => true,
                'balance' => 'Sandbox Simulated Mode',
                'sender_id' => $senderId ?: '8809648908088',
                'note' => $this->isPlaceholderKey($apiKey)
                    ? 'Configure BULKSMS_API_KEY & BULKSMS_SENDER_ID for live telecom dispatch.'
                    : 'BULKSMS_SIMULATE=true — SMS logs are captured locally without consuming carrier credits.',
            ];
        }

        try {
            $response = Http::timeout(5)->get(self::BULKSMS_BALANCE_URL, [
                'api_key' => $apiKey,
            ]);

            $json = $response->json();
            $code = is_array($json) && isset($json['response_code']) ? (int) $json['response_code'] : null;

            if ($response->successful() && ($code === 202 || isset($json['balance']))) {
                return [
                    'ok' => true,
                    'simulated' => false,
                    'balance' => 'BDT ' . ($json['balance'] ?? '0.00'),
                    'sender_id' => $senderId,
                    'note' => 'Connected to BulkSMS BD live gateway (demo numbers remain protected in sandbox mode).',
                ];
            }

            $err = $code ? (self::ERROR_CODE_MAP[$code] ?? ('Code ' . $code)) : 'Gateway unreachable';
            return [
                'ok' => false,
                'simulated' => true,
                'balance' => 'Simulated Fallback Active',
                'sender_id' => $senderId,
                'note' => "BulkSMS BD returned: {$err}. Auto-simulation active so alerts never fail.",
            ];
        } catch (\Throwable $e) {
            return [
                'ok' => false,
                'simulated' => true,
                'balance' => 'Simulated Fallback Active',
                'sender_id' => $senderId,
                'note' => 'Live gateway timeout — local SMS simulation fallback active.',
            ];
        }
    }

    private function recordLog(
        string $recipient,
        string $eventKey,
        string $message,
        string $status,
        ?string $providerRef,
        string $provider = 'BulkSMSBD',
        ?int $repairRequestId = null,
        ?int $deliveryJobId = null
    ): void {
        $safeStatus = in_array($status, ['QUEUED', 'SENT', 'FAILED'], true) ? $status : 'FAILED';

        try {
            if (!Schema::hasTable('notification_logs')) {
                return;
            }

            DB::transaction(function () use ($recipient, $eventKey, $message, $safeStatus, $providerRef, $provider, $repairRequestId, $deliveryJobId) {
                DB::table('notification_logs')->insert([
                    'repair_request_id' => $repairRequestId,
                    'delivery_job_id' => $deliveryJobId,
                    'channel' => 'SMS',
                    'recipient' => $recipient,
                    'subject' => $eventKey,
                    'body' => mb_substr($message, 0, 500),
                    'provider' => $provider,
                    'provider_ref' => $providerRef,
                    'status' => $safeStatus,
                    'sent_at' => $safeStatus === 'SENT' ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
        } catch (\Throwable $e) {
            Log::warning('[SmsService] Could not insert notification_logs row: ' . $e->getMessage());
        }
    }
}

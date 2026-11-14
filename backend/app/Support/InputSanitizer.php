<?php

namespace App\Support;

class InputSanitizer
{
    private const BENGALI_DIGITS = ['০', '১', '২', '৩', '৪', '৫', '৬', '৭', '৮', '৯'];
    private const ASCII_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * Remove invisible Unicode directional/zero-width characters and convert Bengali numerals.
     */
    public static function cleanUnicode(?string $raw): string
    {
        if ($raw === null) {
            return '';
        }

        $cleaned = preg_replace('/[\x{200B}-\x{200F}\x{202A}-\x{202E}\x{2066}-\x{2069}\x{FEFF}]/u', '', $raw) ?? $raw;
        $cleaned = str_replace(self::BENGALI_DIGITS, self::ASCII_DIGITS, $cleaned);

        return trim($cleaned);
    }

    /**
     * Normalize a phone input string (strips spaces, dashes, parens, invisible bidi marks).
     */
    public static function sanitizePhone(?string $raw): ?string
    {
        $cleaned = self::cleanUnicode($raw);
        if ($cleaned === '') {
            return null;
        }

        // Convert international 00880 prefix to +880 when present.
        if (str_starts_with($cleaned, '00880')) {
            $cleaned = '+' . substr($cleaned, 2);
        }

        // Strip common visual phone formatting punctuation while preserving unexpected letters
        // so malformed strings fail strict validation rather than silently passing.
        $normalized = str_replace([' ', '-', '(', ')', "\t", "\r", "\n", "\u{00A0}", '.'], '', $cleaned);

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Validate a normalized or raw Bangladeshi mobile number.
     */
    public static function isValidBdPhone(?string $raw): bool
    {
        $phone = self::sanitizePhone($raw);
        if ($phone === null) {
            return false;
        }

        return preg_match('/^(?:\+?8801|01)[3-9]\d{8}$/', $phone) === 1;
    }

    /**
     * Strip whitespace, dashes, and invisible marks around digit-based IDs (NID, OTP, etc.).
     */
    public static function sanitizeDigits(?string $raw): ?string
    {
        $cleaned = self::cleanUnicode($raw);
        if ($cleaned === '') {
            return null;
        }

        $normalized = preg_replace('/[\s\-_]+/', '', $cleaned) ?? $cleaned;

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Validate Bangladesh National ID format (10-digit Smart NID, 13-digit, or 17-digit).
     */
    public static function isValidNid(?string $raw): bool
    {
        $nid = self::sanitizeDigits($raw);
        if ($nid === null) {
            return false;
        }

        return preg_match('/^(?:\d{10}|\d{13}|\d{17})$/', $nid) === 1;
    }
}

<?php

namespace App\Support;

use InvalidArgumentException;

class IndiaMobile
{
    /**
     * Normalise to digits-only E.164 without plus: 91XXXXXXXXXX (12 digits).
     *
     * @throws InvalidArgumentException
     */
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = substr($digits, 1);
        }

        if (strlen($digits) === 10 && preg_match('/^[6-9]\d{9}$/', $digits) === 1) {
            return '91'.$digits;
        }

        if (strlen($digits) === 12 && str_starts_with($digits, '91') && preg_match('/^91[6-9]\d{9}$/', $digits) === 1) {
            return $digits;
        }

        throw new InvalidArgumentException('Only Indian mobile numbers (+91) are accepted for OTP login.');
    }

    public static function isIndia(string $raw): bool
    {
        try {
            self::normalize($raw);

            return true;
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    public static function display(string $normalized): string
    {
        if (strlen($normalized) === 12 && str_starts_with($normalized, '91')) {
            return '+91 '.substr($normalized, 2);
        }

        return $normalized;
    }
}

<?php

namespace App\Support;

class PhoneNumbers
{
    public static function normalize(?string $input): ?string
    {
        $raw = trim((string) $input);
        if ($raw === '') {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $raw) ?? '';
        if ($digits === '' || strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (str_starts_with($digits, '243')) {
            return self::valid('+'.$digits) ? '+'.$digits : null;
        }

        if (str_starts_with($raw, '+')) {
            return self::valid('+'.$digits) ? '+'.$digits : null;
        }

        if (str_starts_with($digits, '0')) {
            $digits = substr($digits, 1);
        }

        $local = '+243'.$digits;

        return self::valid($local) ? $local : null;
    }

    public static function valid(?string $phone): bool
    {
        return is_string($phone) && preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1;
    }

    public static function digits(?string $phone): string
    {
        return preg_replace('/\D+/', '', (string) $phone) ?? '';
    }

    public static function matches(?string $left, ?string $right): bool
    {
        $given = self::digits(self::normalize($left) ?? $left);
        $stored = self::digits(self::normalize($right) ?? $right);

        if ($given === '' || $stored === '' || strlen($given) < 9 || strlen($stored) < 9) {
            return false;
        }

        if ($given === $stored) {
            return true;
        }

        $short = strlen($given) < strlen($stored) ? $given : $stored;
        $long = $short === $given ? $stored : $given;

        return strlen($short) >= 9 && str_ends_with($long, $short);
    }
}

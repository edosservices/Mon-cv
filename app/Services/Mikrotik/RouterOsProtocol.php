<?php

namespace App\Services\Mikrotik;

class RouterOsProtocol
{
    public static function encodeLength(int $length): string
    {
        if ($length < 0x80) {
            return chr($length);
        }

        if ($length < 0x4000) {
            return chr(($length >> 8) | 0x80).chr($length & 0xFF);
        }

        if ($length < 0x200000) {
            return chr(($length >> 16) | 0xC0).chr(($length >> 8) & 0xFF).chr($length & 0xFF);
        }

        if ($length < 0x10000000) {
            return chr(($length >> 24) | 0xE0)
                .chr(($length >> 16) & 0xFF)
                .chr(($length >> 8) & 0xFF)
                .chr($length & 0xFF);
        }

        return chr(0xF0)
            .chr(($length >> 24) & 0xFF)
            .chr(($length >> 16) & 0xFF)
            .chr(($length >> 8) & 0xFF)
            .chr($length & 0xFF);
    }

    public static function encodeWord(string $word): string
    {
        return self::encodeLength(strlen($word)).$word;
    }

    public static function encodeSentence(array $words): string
    {
        $payload = '';

        foreach ($words as $word) {
            $payload .= self::encodeWord($word);
        }

        return $payload.chr(0);
    }

    public static function secondsToRouterTime(int $seconds): string
    {
        $weeks = intdiv($seconds, 604800);
        $seconds %= 604800;
        $days = intdiv($seconds, 86400);
        $seconds %= 86400;
        $hours = intdiv($seconds, 3600);
        $seconds %= 3600;
        $minutes = intdiv($seconds, 60);
        $seconds %= 60;

        $value = '';
        if ($weeks) {
            $value .= $weeks.'w';
        }
        if ($days) {
            $value .= $days.'d';
        }
        if ($hours) {
            $value .= $hours.'h';
        }
        if ($minutes) {
            $value .= $minutes.'m';
        }
        if ($seconds || $value === '') {
            $value .= $seconds.'s';
        }

        return $value;
    }
}

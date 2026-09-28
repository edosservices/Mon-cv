<?php

namespace App\Support;

class Money
{
    public static function format(mixed $amount, ?string $currency = null): string
    {
        if ($amount === null || $amount === '') {
            return 'À définir';
        }

        return number_format((float) $amount, 0, ',', ' ').' '.($currency ?: config('limete.currency'));
    }

    public static function shop(mixed $amount, ?string $currency = null): string
    {
        if ($amount === null || $amount === '') {
            return 'À définir';
        }

        $code = $currency ?: config('limete.currency');

        return number_format((float) $amount, 0, ',', ' ').' '.($code === 'CDF' ? 'FC' : $code);
    }
}

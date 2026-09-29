<?php

namespace App\Support;

class TicketTemplates
{
    public const CLASSIC = 'classique';

    public const MODERN = 'moderne';

    public const COMPACT = 'compact';

    public const PREMIUM = 'premium';

    /**
     * @return list<string>
     */
    public static function keys(): array
    {
        return [self::CLASSIC, self::MODERN, self::COMPACT, self::PREMIUM];
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::CLASSIC => 'Classique',
            self::MODERN => 'Moderne',
            self::COMPACT => 'Compact',
            self::PREMIUM => 'Premium',
        ];
    }

    public static function normalize(?string $template): string
    {
        return in_array($template, self::keys(), true) ? $template : self::MODERN;
    }
}

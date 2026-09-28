<?php

namespace App\Enums;

enum VoucherStatus: string
{
    case Available = 'available';
    case Active = 'active';
    case Expired = 'expired';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Available => 'Disponible',
            self::Active => 'Actif',
            self::Expired => 'Expiré',
            self::Disabled => 'Désactivé',
        };
    }
}

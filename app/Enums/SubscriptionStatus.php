<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case Active = 'active';
    case Trial = 'trial';
    case Expired = 'expired';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';

    public function allowsAccess(): bool
    {
        return $this === self::Active || $this === self::Trial;
    }

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Actif',
            self::Trial => 'Essai',
            self::Expired => 'Expiré',
            self::Suspended => 'Suspendu',
            self::Cancelled => 'Annulé',
        };
    }
}

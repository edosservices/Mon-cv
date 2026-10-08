<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Processing => 'En cours',
            self::Success => 'Payé',
            self::Failed => 'Échoué',
            self::Cancelled => 'Annulé',
            self::Refunded => 'Remboursé',
            self::Expired => 'Expiré',
        };
    }

    public function canTransitionTo(self $next): bool
    {
        if ($this === $next) {
            return true;
        }

        return match ($this) {
            self::Pending => in_array($next, [self::Processing, self::Success, self::Failed, self::Cancelled, self::Expired], true),
            self::Processing => in_array($next, [self::Success, self::Failed, self::Cancelled, self::Expired], true),
            self::Success => $next === self::Refunded,
            self::Failed, self::Cancelled, self::Refunded, self::Expired => false,
        };
    }
}

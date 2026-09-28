<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Success => 'Réussi',
            self::Failed => 'Échoué',
            self::Cancelled => 'Annulé',
            self::Refunded => 'Remboursé',
        };
    }
}

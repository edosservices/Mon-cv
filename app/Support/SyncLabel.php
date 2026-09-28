<?php

namespace App\Support;

class SyncLabel
{
    public static function for(?string $status): string
    {
        return match ($status) {
            'synced' => 'Synchronisé',
            'failed' => 'Erreur',
            default => 'Non synchronisé',
        };
    }
}

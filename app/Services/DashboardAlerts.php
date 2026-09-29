<?php

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Sale;
use App\Models\User;
use App\Notifications\PlatformNotification;

class DashboardAlerts
{
    public function sync(User $user, array $report): void
    {
        $offline = collect($report['routers'])->contains(fn (array $card) => in_array($card['router']->status, ['offline', 'error'], true));
        $this->once(
            $user,
            'mikrotik.offline',
            $offline && $report['routers'] !== [],
            'MikroTik hors ligne',
            'Au moins un routeur n’est pas connecté. Ouvrez le tableau de bord pour vérifier.',
        );

        $unsynced = (int) collect($report['kpis'])->firstWhere('label', 'Tickets non synchronisés')['value'];
        $this->once(
            $user,
            'voucher.unsynced',
            $unsynced > 0,
            'Tickets à synchroniser',
            $unsynced.' ticket(s) ne sont pas encore sur le MikroTik.',
        );

        $pending = Payment::query()
            ->where('payable_type', Sale::class)
            ->where('status', PaymentStatus::Pending->value)
            ->count();
        $this->once(
            $user,
            'payment.pending',
            $pending > 0,
            'Paiement à confirmer',
            $pending.' paiement(s) sont encore en attente. Ils ne comptent pas dans le chiffre d’affaires.',
        );
    }

    private function once(User $user, string $code, bool $needed, string $title, string $body): void
    {
        if (! $needed) {
            return;
        }

        $exists = $user->unreadNotifications()->where('data->code', $code)->exists();
        if ($exists) {
            return;
        }

        $user->notify(new PlatformNotification($code, $title, $body));
    }
}

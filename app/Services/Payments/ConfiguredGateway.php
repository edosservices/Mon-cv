<?php

namespace App\Services\Payments;

use App\Models\Payment;
use RuntimeException;

class ConfiguredGateway implements PaymentGateway
{
    public function __construct(private string $name, private string $envKey) {}

    public function provider(): string
    {
        return $this->name;
    }

    public function initiate(Payment $payment, array $context = []): Payment
    {
        if (! filled(env($this->envKey))) {
            throw new RuntimeException('La clé '.$this->envKey.' est absente. Ajoutez-la dans le fichier .env du serveur, jamais dans le code.');
        }

        throw new RuntimeException('Le connecteur '.$this->name.' est prévu, mais son appel opérateur n’est pas encore branché.');
    }
}

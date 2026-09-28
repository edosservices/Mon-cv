<?php

namespace App\Services\Payments;

class PaymentNotice
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $internalReference,
        public string $providerReference,
        public string $amount,
        public string $currency,
        public string $status,
        public array $raw,
    ) {}
}

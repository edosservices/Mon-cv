<?php

namespace Tests\Support;

use App\Services\Production\EndpointProbe;

class FakeEndpointProbe implements EndpointProbe
{
    public int $tcpCalls = 0;

    public int $resolveCalls = 0;

    public function __construct(
        private array $tcp = ['ok' => false, 'error' => 'timed out', 'milliseconds' => 4],
        private array $resolve = ['checked' => true, 'addresses' => [], 'error' => null],
    ) {}

    public function resolve(string $host): array
    {
        $this->resolveCalls++;
        if ($this->resolve['addresses'] === [] && ($this->resolve['error'] ?? null) === null && filter_var($host, FILTER_VALIDATE_IP)) {
            return ['checked' => true, 'addresses' => [$host], 'error' => null];
        }

        return $this->resolve;
    }

    public function tcp(string $host, int $port, int $timeout): array
    {
        $this->tcpCalls++;

        return $this->tcp;
    }
}

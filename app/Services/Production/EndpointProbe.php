<?php

namespace App\Services\Production;

interface EndpointProbe
{
    /**
     * @return array{checked: bool, addresses: array<int, string>, error: ?string}
     */
    public function resolve(string $host): array;

    /**
     * @return array{ok: bool, error: ?string, milliseconds: int}
     */
    public function tcp(string $host, int $port, int $timeout): array;
}

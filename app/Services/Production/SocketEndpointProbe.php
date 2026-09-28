<?php

namespace App\Services\Production;

use App\Services\Mikrotik\HostResolver;

class SocketEndpointProbe implements EndpointProbe
{
    public function __construct(private HostResolver $names) {}

    public function resolve(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return ['checked' => true, 'addresses' => [$host], 'error' => null];
        }

        $found = $this->names->resolve($host);
        if (! $found['checked']) {
            return ['checked' => false, 'addresses' => [], 'error' => $found['label']];
        }

        if ($found['addresses'] === []) {
            return ['checked' => true, 'addresses' => [], 'error' => $found['label']];
        }

        return ['checked' => true, 'addresses' => $found['addresses'], 'error' => null];
    }

    public function tcp(string $host, int $port, int $timeout): array
    {
        $timeout = max(1, min(3, $timeout));
        $started = microtime(true);
        $errno = 0;
        $error = '';
        $socket = @fsockopen($host, $port, $errno, $error, $timeout);
        $milliseconds = (int) round((microtime(true) - $started) * 1000);

        if ($socket === false) {
            $detail = trim($error) !== '' ? trim($error) : 'Connexion impossible';

            return ['ok' => false, 'error' => $detail.' ('.$errno.')', 'milliseconds' => $milliseconds];
        }

        fclose($socket);

        return ['ok' => true, 'error' => null, 'milliseconds' => $milliseconds];
    }
}

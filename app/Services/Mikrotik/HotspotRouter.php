<?php

namespace App\Services\Mikrotik;

interface HotspotRouter
{
    /**
     * @param  array<int, string>  $words
     * @return array<int, array<string, string>>
     */
    public function command(string $host, int $port, string $username, string $password, array $words, int $timeout = 5): array;
}

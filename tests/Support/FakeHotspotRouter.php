<?php

namespace Tests\Support;

use App\Services\Mikrotik\HotspotRouter;
use RuntimeException;

class FakeHotspotRouter implements HotspotRouter
{
    public array $commands = [];

    public function __construct(private $handler = null) {}

    public function command(string $host, int $port, string $username, string $password, array $words, int $timeout = 5): array
    {
        $this->commands[] = compact('host', 'port', 'username', 'password', 'words');

        if ($this->handler instanceof \Throwable) {
            throw $this->handler;
        }

        if ($this->handler instanceof \Closure) {
            return ($this->handler)($words);
        }

        return [['!type' => '!done']];
    }

    public function fail(string $message): void
    {
        $this->handler = new RuntimeException($message);
    }
}

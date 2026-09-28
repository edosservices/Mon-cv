<?php

namespace App\Services\Mikrotik;

use App\Models\Mikrotik;
use App\Models\MikrotikProfile;
use App\Models\Voucher;
use RuntimeException;

class MikrotikService
{
    public function __construct(private HotspotRouter $router) {}

    public function testConnection(Mikrotik $router): Mikrotik
    {
        try {
            $rows = $this->command($router, ['/system/resource/print']);
            $version = $rows[0]['version'] ?? null;
            $router->forceFill([
                'status' => 'online',
                'routeros_version' => $version,
                'last_seen_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (RuntimeException $exception) {
            $offline = str_contains(strtolower($exception->getMessage()), 'connexion impossible')
                || str_contains(strtolower($exception->getMessage()), 'timed out')
                || str_contains(strtolower($exception->getMessage()), 'connection refused');

            $router->forceFill([
                'status' => $offline ? 'offline' : 'error',
                'last_error' => $exception->getMessage(),
            ])->save();
        }

        return $router;
    }

    public function testCredentials(string $host, int $port, string $username, string $password): array
    {
        $rows = $this->router->command($host, $port, $username, $password, ['/system/resource/print']);

        return [
            'status' => 'online',
            'version' => $rows[0]['version'] ?? null,
        ];
    }

    public function createUser(Mikrotik $router, Voucher $voucher): void
    {
        $profile = $voucher->plan?->mikrotik_profile ?: 'default';
        $this->command($router, [
            '/ip/hotspot/user/add',
            '=name='.$voucher->username,
            '=password='.$voucher->password,
            '=profile='.$profile,
            '=limit-uptime='.RouterOsProtocol::secondsToRouterTime($voucher->plan?->duration_seconds ?? 0),
            '=comment=limete-manager',
        ]);

        $voucher->forceFill([
            'sync_status' => 'synced',
            'sync_error' => null,
            'mikrotik_id' => $router->id,
        ])->save();
    }

    public function updateUser(Mikrotik $router, string $username, array $attributes): void
    {
        $id = $this->findUserId($router, $username);
        $words = ['/ip/hotspot/user/set', '=.id='.$id];

        foreach ($attributes as $key => $value) {
            $words[] = '='.$key.'='.$value;
        }

        $this->command($router, $words);
    }

    public function deleteUser(Mikrotik $router, string $username): void
    {
        $id = $this->findUserId($router, $username);
        $this->command($router, ['/ip/hotspot/user/remove', '=.id='.$id]);
    }

    public function disconnectUser(Mikrotik $router, string $activeId): void
    {
        $this->command($router, ['/ip/hotspot/active/remove', '=.id='.$activeId]);
    }

    public function getActiveUsers(Mikrotik $router): array
    {
        return $this->records($this->command($router, ['/ip/hotspot/active/print']));
    }

    public function getUsers(Mikrotik $router): array
    {
        return $this->records($this->command($router, ['/ip/hotspot/user/print']));
    }

    public function getProfiles(Mikrotik $router): array
    {
        $profiles = $this->records($this->command($router, ['/ip/hotspot/user/profile/print']));

        foreach ($profiles as $profile) {
            if (! isset($profile['name'])) {
                continue;
            }

            MikrotikProfile::updateOrCreate(
                ['mikrotik_id' => $router->id, 'name' => $profile['name']],
                [
                    'tenant_id' => $router->tenant_id,
                    'rate_limit' => $profile['rate-limit'] ?? null,
                    'shared_users' => isset($profile['shared-users']) ? (int) $profile['shared-users'] : null,
                    'raw' => $profile,
                ]
            );
        }

        return $profiles;
    }

    public function getActiveSessions(Mikrotik $router): array
    {
        return $this->getActiveUsers($router);
    }

    public function getSystemResource(Mikrotik $router): array
    {
        $rows = $this->records($this->command($router, ['/system/resource/print']));

        return $rows[0] ?? [];
    }

    private function findUserId(Mikrotik $router, string $username): string
    {
        $rows = $this->records($this->command($router, ['/ip/hotspot/user/print', '?name='.$username]));
        $id = $rows[0]['.id'] ?? null;

        if (! $id) {
            throw new RuntimeException('Utilisateur introuvable sur le routeur.');
        }

        return $id;
    }

    private function command(Mikrotik $router, array $words): array
    {
        return $this->router->command(
            $router->host,
            (int) $router->api_port,
            $router->username,
            $router->password,
            $words,
        );
    }

    private function records(array $rows): array
    {
        return array_values(array_filter($rows, fn (array $row) => ($row['!type'] ?? '') === '!re'));
    }
}

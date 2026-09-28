<?php

namespace App\Services\Mikrotik;

use App\Models\Mikrotik;
use App\Models\MikrotikProfile;
use App\Models\Voucher;
use App\Support\TenantManager;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class MikrotikService
{
    public function __construct(private HotspotRouter $router) {}

    public function connect(Mikrotik $router): array
    {
        return $this->getIdentity($router);
    }

    public function testConnection(Mikrotik $router): Mikrotik
    {
        $this->assertOwned($router);

        try {
            $identity = $this->getIdentity($router);
            $resource = $this->getSystemResource($router);
            $router->forceFill([
                'status' => 'online',
                'identity' => $identity['name'] ?? $router->identity,
                'routeros_version' => $resource['version'] ?? $router->routeros_version,
                'last_seen_at' => now(),
                'last_error' => null,
            ])->save();
        } catch (RuntimeException $exception) {
            $router->forceFill([
                'status' => $this->isOffline($exception) ? 'offline' : 'error',
                'last_error' => $this->redact($exception->getMessage(), [$router->password]),
            ])->save();
        }

        return $router->refresh();
    }

    public function testCredentials(string $host, int $port, string $username, string $password): array
    {
        try {
            $rows = $this->router->command($host, $port, $username, $password, ['/system/resource/print']);
        } catch (RuntimeException $exception) {
            throw new RuntimeException($this->redact($exception->getMessage(), [$password]));
        }

        Log::info('mikrotik.credentials_tested', [
            'host' => $host,
            'port' => $port,
            'username' => $username,
        ]);

        return [
            'status' => 'online',
            'version' => $this->records($rows)[0]['version'] ?? null,
        ];
    }

    public function getIdentity(Mikrotik $router): array
    {
        $this->assertOwned($router);

        return $this->records($this->command($router, ['/system/identity/print']))[0] ?? [];
    }

    public function getSystemResource(Mikrotik $router): array
    {
        $this->assertOwned($router);

        return $this->records($this->command($router, ['/system/resource/print']))[0] ?? [];
    }

    public function getHotspotUsers(Mikrotik $router): array
    {
        $this->assertOwned($router);

        return $this->records($this->command($router, ['/ip/hotspot/user/print']));
    }

    public function getHotspotActiveUsers(Mikrotik $router): array
    {
        $this->assertOwned($router);

        return $this->records($this->command($router, ['/ip/hotspot/active/print']));
    }

    public function getHotspotProfiles(Mikrotik $router): array
    {
        $this->assertOwned($router);
        $profiles = $this->records($this->command($router, ['/ip/hotspot/user/profile/print']));

        foreach ($profiles as $profile) {
            if (! isset($profile['name']) || $profile['name'] === '') {
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

    public function createHotspotUser(Mikrotik $router, Voucher $voucher): Voucher
    {
        $this->assertPair($router, $voucher);
        $voucher->loadMissing('plan');
        $profile = $voucher->plan?->mikrotik_profile;

        if (! filled($profile)) {
            throw new RuntimeException('Le forfait n’a pas de profil MikroTik. Le compte n’a pas été créé sur le routeur.');
        }

        $this->command($router, [
            '/ip/hotspot/user/add',
            '=name='.$voucher->username,
            '=password='.$voucher->password,
            '=profile='.$profile,
            '=limit-uptime='.RouterOsProtocol::secondsToRouterTime((int) $voucher->plan->duration_seconds),
            '=comment=limete-manager',
        ], [$voucher->password]);

        $voucher->forceFill([
            'mikrotik_id' => $router->id,
            'sync_status' => 'synced',
            'sync_error' => null,
        ])->save();

        Log::info('mikrotik.hotspot_user_created', $this->context($router, '/ip/hotspot/user/add') + [
            'username' => $voucher->username,
            'profile' => $profile,
            'voucher_id' => $voucher->id,
        ]);

        return $voucher->refresh();
    }

    public function updateHotspotUser(Mikrotik $router, string $username, array $attributes): void
    {
        $this->assertOwned($router);
        $id = $this->findHotspotUserId($router, $username);
        $words = ['/ip/hotspot/user/set', '=.id='.$id];
        $secrets = [$router->password];

        foreach ($attributes as $key => $value) {
            $words[] = '='.$key.'='.$value;
            if ($key === 'password') {
                $secrets[] = (string) $value;
            }
        }

        $this->command($router, $words, $secrets);
    }

    public function disableHotspotUser(Mikrotik $router, string $username): void
    {
        $this->assertOwned($router);
        $id = $this->findHotspotUserId($router, $username);
        $this->command($router, ['/ip/hotspot/user/disable', '=.id='.$id]);
    }

    public function deleteHotspotUser(Mikrotik $router, string $username): void
    {
        $this->assertOwned($router);
        $id = $this->findHotspotUserId($router, $username);
        $this->command($router, ['/ip/hotspot/user/remove', '=.id='.$id]);
    }

    public function disconnectActiveUser(Mikrotik $router, string $activeId): void
    {
        $this->assertOwned($router);
        $this->command($router, ['/ip/hotspot/active/remove', '=.id='.$activeId]);
    }

    public function provisionVoucher(Voucher $voucher): Voucher
    {
        if ($voucher->sync_status === 'synced' && $voucher->mikrotik_id) {
            return $voucher;
        }

        $voucher->loadMissing('plan', 'wifiZone.mikrotiks');
        $router = $this->routerForZone($voucher);

        if (! $router) {
            return $this->markUnsynced($voucher, 'pending', 'Aucun MikroTik associé à cette WiFi Zone. Le ticket est enregistré, mais il n’a pas été créé sur un routeur.');
        }

        try {
            return $this->createHotspotUser($router, $voucher);
        } catch (Throwable $exception) {
            return $this->markUnsynced($voucher, 'failed', $this->redact($exception->getMessage(), [$router->password, $voucher->password]));
        }
    }

    /**
     * @param  array<int, Voucher>  $vouchers
     * @return array{synced: int, unsynced: int, error: ?string}
     */
    public function provisionMany(array $vouchers): array
    {
        $synced = 0;
        $error = null;

        foreach ($vouchers as $voucher) {
            $result = $this->provisionVoucher($voucher);
            if ($result->sync_status === 'synced') {
                $synced++;
            } else {
                $error = $result->sync_error;
            }
        }

        return [
            'synced' => $synced,
            'unsynced' => count($vouchers) - $synced,
            'error' => $error,
        ];
    }

    public function createUser(Mikrotik $router, Voucher $voucher): void
    {
        $this->createHotspotUser($router, $voucher);
    }

    public function updateUser(Mikrotik $router, string $username, array $attributes): void
    {
        $this->updateHotspotUser($router, $username, $attributes);
    }

    public function deleteUser(Mikrotik $router, string $username): void
    {
        $this->deleteHotspotUser($router, $username);
    }

    public function disconnectUser(Mikrotik $router, string $activeId): void
    {
        $this->disconnectActiveUser($router, $activeId);
    }

    public function getActiveUsers(Mikrotik $router): array
    {
        return $this->getHotspotActiveUsers($router);
    }

    public function getUsers(Mikrotik $router): array
    {
        return $this->getHotspotUsers($router);
    }

    public function getProfiles(Mikrotik $router): array
    {
        return $this->getHotspotProfiles($router);
    }

    public function getActiveSessions(Mikrotik $router): array
    {
        return $this->getHotspotActiveUsers($router);
    }

    public function statusLabel(Mikrotik $router): string
    {
        return match ($router->status) {
            'online' => '🟢 Connecté',
            'error' => '⚠️ Erreur',
            default => '🔴 Hors ligne',
        };
    }

    private function routerForZone(Voucher $voucher): ?Mikrotik
    {
        $routers = $voucher->wifiZone?->mikrotiks ?? collect();

        return $routers->firstWhere('status', 'online') ?? $routers->first();
    }

    private function markUnsynced(Voucher $voucher, string $status, string $message): Voucher
    {
        $voucher->forceFill([
            'mikrotik_id' => null,
            'sync_status' => $status,
            'sync_error' => $message,
        ])->save();

        Log::warning('mikrotik.hotspot_user_not_synced', [
            'voucher_id' => $voucher->id,
            'tenant_id' => $voucher->tenant_id,
            'username' => $voucher->username,
            'sync_status' => $status,
            'message' => $message,
        ]);

        return $voucher->refresh();
    }

    private function findHotspotUserId(Mikrotik $router, string $username): string
    {
        $rows = $this->records($this->command($router, ['/ip/hotspot/user/print', '?name='.$username]));
        $id = $rows[0]['.id'] ?? null;

        if (! $id) {
            throw new RuntimeException('Utilisateur introuvable sur le routeur.');
        }

        return $id;
    }

    private function command(Mikrotik $router, array $words, array $secrets = []): array
    {
        $path = $words[0] ?? 'inconnue';
        $secrets[] = $router->password;

        try {
            $rows = $this->router->command(
                $router->host,
                (int) $router->api_port,
                $router->username,
                $router->password,
                $words,
            );
        } catch (RuntimeException $exception) {
            $message = $this->redact($exception->getMessage(), $secrets);
            Log::warning('mikrotik.command_failed', $this->context($router, $path) + ['message' => $message]);

            throw new RuntimeException($message, 0, $exception);
        }

        Log::info('mikrotik.command', $this->context($router, $path));

        return $rows;
    }

    private function assertOwned(Mikrotik $router): void
    {
        $tenantId = app(TenantManager::class)->id();

        if ($tenantId && (int) $router->tenant_id !== (int) $tenantId) {
            throw new RuntimeException('Ce MikroTik n’appartient pas à cette entreprise.');
        }
    }

    private function assertPair(Mikrotik $router, Voucher $voucher): void
    {
        $this->assertOwned($router);

        if ((int) $voucher->tenant_id !== (int) $router->tenant_id) {
            throw new RuntimeException('Ce ticket et ce MikroTik n’appartiennent pas au même entrepreneur.');
        }
    }

    private function records(array $rows): array
    {
        return array_values(array_filter($rows, fn (array $row) => ($row['!type'] ?? '') === '!re'));
    }

    private function isOffline(Throwable $exception): bool
    {
        $message = strtolower($exception->getMessage());

        foreach (['connexion impossible', 'timed out', 'time out', 'connection refused', 'no route to host', 'network is unreachable', 'name or service not known'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function context(Mikrotik $router, string $command): array
    {
        return [
            'mikrotik_id' => $router->id,
            'tenant_id' => $router->tenant_id,
            'host' => $router->host,
            'command' => $command,
        ];
    }

    private function redact(string $message, array $secrets): string
    {
        $message = (string) preg_replace('/=password=[^\s,]*/', '=password=[masqué]', $message);

        foreach ($secrets as $secret) {
            if (is_string($secret) && $secret !== '') {
                $message = str_replace($secret, '[masqué]', $message);
            }
        }

        return $message;
    }
}

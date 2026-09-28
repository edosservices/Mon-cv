<?php

namespace App\Services\Mikrotik;

use App\Models\Mikrotik;
use App\Models\MikrotikProfile;
use App\Models\PlanMikrotikProfile;
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
                'last_seen_at' => now(),
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

    public function discover(string $host, int $port, string $username, string $password, int $timeout = 5, bool $secure = false): array
    {
        $identity = $this->ask($host, $port, $username, $password, ['/system/identity/print'], $timeout, $secure);
        $resource = $this->ask($host, $port, $username, $password, ['/system/resource/print'], $timeout, $secure);
        $hotspots = $this->askOptional($host, $port, $username, $password, ['/ip/hotspot/print'], $timeout, $secure);
        $serverProfiles = $this->askOptional($host, $port, $username, $password, ['/ip/hotspot/profile/print'], $timeout, $secure);
        $userProfiles = $this->askOptional($host, $port, $username, $password, ['/ip/hotspot/user/profile/print'], $timeout, $secure);
        $active = $this->askOptional($host, $port, $username, $password, ['/ip/hotspot/active/print'], $timeout, $secure);
        $interfaces = $this->askOptional($host, $port, $username, $password, ['/interface/print'], $timeout, $secure);
        $addresses = $this->askOptional($host, $port, $username, $password, ['/ip/address/print'], $timeout, $secure);
        $pools = $this->askOptional($host, $port, $username, $password, ['/ip/pool/print'], $timeout, $secure);
        $users = $this->askOptional($host, $port, $username, $password, ['/ip/hotspot/user/print'], $timeout, $secure);
        $row = $resource[0] ?? [];
        $servers = array_map(fn (array $item) => $this->pick($item, ['name', 'interface', 'profile', 'address-pool', 'addresses', 'disabled']), $hotspots);
        $dnsName = $this->firstFilled($hotspots, 'dns-name') ?? $this->firstFilled($serverProfiles, 'dns-name');

        Log::info('mikrotik.discovered', [
            'host' => $host,
            'port' => $port,
            'username' => $username,
            'secure' => $secure,
        ]);

        return [
            'identity' => $identity[0]['name'] ?? null,
            'version' => $row['version'] ?? null,
            'uptime' => $row['uptime'] ?? null,
            'cpu' => $row['cpu-load'] ?? null,
            'memory' => $this->memoryLabel($row),
            'memory_percent' => $this->memoryPercent($row),
            'architecture' => $row['architecture-name'] ?? $row['board-name'] ?? null,
            'board' => $row['board-name'] ?? null,
            'disk' => $this->diskLabel($row),
            'hotspot' => $hotspots !== [],
            'server_profiles' => $this->names($serverProfiles),
            'user_profiles' => $this->names($userProfiles),
            'active_users' => count($active),
            'hotspot_servers' => $servers,
            'interfaces' => array_map(fn (array $item) => $this->pick($item, ['name', 'type', 'running', 'mac-address', 'rx-byte', 'tx-byte']), $interfaces),
            'addresses' => array_map(fn (array $item) => $this->pick($item, ['address', 'interface', 'network']), $addresses),
            'pools' => array_map(fn (array $item) => $this->pick($item, ['name', 'ranges']), $pools),
            'dns_name' => $dnsName,
            'profile_rows' => array_map(fn (array $item) => $this->pick($item, ['name', 'rate-limit', 'session-timeout', 'idle-timeout', 'keepalive-timeout', 'shared-users', 'disabled']), $userProfiles),
            'users' => array_map(fn (array $item) => $this->pick($item, ['name', 'profile', 'uptime', 'bytes-in', 'bytes-out', 'disabled', 'comment', 'limit-uptime']), $users),
            'sessions' => array_map(fn (array $item) => $this->pick($item, ['.id', 'user', 'address', 'mac-address', 'uptime', 'session-time-left', 'bytes-in', 'bytes-out', 'server']), $active),
        ];
    }

    public function syncRouter(Mikrotik $router): Mikrotik
    {
        $this->assertOwned($router);

        try {
            $found = $this->discover(
                $router->host,
                $router->connectionPort(),
                $router->username,
                $router->password,
                (int) ($router->timeout ?: 5),
                $router->usesSecureApi(),
            );
            $userCount = null;
            try {
                $this->replaceProfiles($router, $this->getHotspotProfiles($router));
                $userCount = count($found['users']);
            } catch (RuntimeException) {
                $userCount = null;
            }
            $fill = [
                'status' => 'online',
                'identity' => $found['identity'] ?: $router->identity,
                'routeros_version' => $found['version'] ?: $router->routeros_version,
                'architecture' => $found['architecture'] ?: $router->architecture,
                'board' => $found['board'] ?: $router->board,
                'last_seen_at' => now(),
                'last_error' => null,
                'details' => [
                    'uptime' => $found['uptime'],
                    'cpu' => $found['cpu'],
                    'memory' => $found['memory'],
                    'memory_percent' => $found['memory_percent'],
                    'architecture' => $found['architecture'],
                    'board' => $found['board'],
                    'disk' => $found['disk'],
                    'hotspot' => $found['hotspot'],
                    'server_profiles' => $found['server_profiles'],
                    'active_users' => $found['active_users'],
                    'hotspot_users' => $userCount,
                    'hotspot_servers' => $found['hotspot_servers'],
                    'interfaces' => $found['interfaces'],
                    'addresses' => $found['addresses'],
                    'pools' => $found['pools'],
                    'dns_name' => $found['dns_name'],
                    'profile_rows' => $found['profile_rows'],
                    'users' => $found['users'],
                    'sessions' => $found['sessions'],
                ],
            ];
            if (! filled($router->dns) && filled($found['dns_name'])) {
                $fill['dns'] = $found['dns_name'];
            }
            if (! filled($router->hotspot_server) && count($found['hotspot_servers']) === 1) {
                $fill['hotspot_server'] = $found['hotspot_servers'][0]['name'] ?? null;
            }
            $router->forceFill($fill)->save();
        } catch (RuntimeException $exception) {
            $router->forceFill([
                'status' => $this->isOffline($exception) ? 'offline' : 'error',
                'last_seen_at' => now(),
                'last_error' => $this->redact($exception->getMessage(), [$router->password]),
            ])->save();
        }

        return $router->refresh();
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

        return array_map(fn (array $row) => $this->stripSecrets($row), $this->records($this->command($router, ['/ip/hotspot/user/print'])));
    }

    public function getHotspotActiveUsers(Mikrotik $router): array
    {
        $this->assertOwned($router);

        return array_map(fn (array $row) => $this->stripSecrets($row), $this->records($this->command($router, ['/ip/hotspot/active/print'])));
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
                    'raw' => $this->stripSecrets($profile),
                ]
            );
        }

        return $profiles;
    }

    public function createHotspotUser(Mikrotik $router, Voucher $voucher): Voucher
    {
        $this->assertPair($router, $voucher);
        $voucher->loadMissing('plan');
        $profile = $this->profileNameFor($router, $voucher);

        if (! filled($profile)) {
            throw new RuntimeException('Profil non associé. Le forfait n’a pas de profil MikroTik. Le compte n’a pas été créé sur le routeur.');
        }

        $known = MikrotikProfile::query()->where('mikrotik_id', $router->id)->pluck('name');
        if ($known->isNotEmpty() && ! $known->contains($profile)) {
            throw new RuntimeException('Profil non associé. Le profil RouterOS est introuvable sur ce MikroTik. Le compte n’a pas été créé sur le routeur.');
        }

        $existing = array_values(array_filter(
            $this->records($this->command($router, ['/ip/hotspot/user/print', '?name='.$voucher->username], [$voucher->password])),
            fn (array $row) => ($row['name'] ?? '') === $voucher->username,
        ));
        if ($existing === []) {
            $this->command($router, [
                '/ip/hotspot/user/add',
                '=name='.$voucher->username,
                '=password='.$voucher->password,
                '=profile='.$profile,
                '=limit-uptime='.RouterOsProtocol::secondsToRouterTime((int) $voucher->plan->duration_seconds),
                '=comment=limete-manager',
            ], [$voucher->password]);
        }

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
        app(\App\Services\AuditLogger::class)->record('voucher.synced', $voucher, null, [
            'username' => $voucher->username,
            'profile' => $profile,
            'mikrotik_id' => $router->id,
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

    /**
     * @return array{synced: int, unsynced: int, error: ?string, online: bool}
     */
    public function syncPending(Mikrotik $router): array
    {
        $this->assertOwned($router);
        $checked = $this->testConnection($router);
        if ($checked->status !== 'online') {
            return ['synced' => 0, 'unsynced' => 0, 'error' => $checked->last_error, 'online' => false];
        }

        $vouchers = Voucher::query()
            ->where('wifi_zone_id', $router->wifi_zone_id)
            ->where('sync_status', '!=', 'synced')
            ->get()
            ->all();
        $summary = $this->provisionMany($vouchers);
        $summary['online'] = true;

        return $summary;
    }

    /**
     * @return array<int, string>
     */
    public function suggestedPortalCommands(Mikrotik $router): array
    {
        $commands = [];
        foreach ($this->portalHosts($router) as $host) {
            $commands[] = '/ip hotspot walled-garden add dst-host='.$host;
        }
        if ($this->safeHost((string) $router->dns)) {
            $commands[] = '/ip hotspot profile set [find] dns-name='.$router->dns;
        }

        return $commands;
    }

    public function applyPortalConfiguration(Mikrotik $router): int
    {
        $this->assertOwned($router);
        $applied = 0;
        foreach ($this->portalHosts($router) as $host) {
            $this->command($router, ['/ip/hotspot/walled-garden/add', '=dst-host='.$host]);
            $applied++;
        }

        if ($this->safeHost((string) $router->dns)) {
            $profiles = $this->records($this->command($router, ['/ip/hotspot/profile/print']));
            $id = $profiles[0]['.id'] ?? null;
            if (is_string($id) && $id !== '') {
                $this->command($router, ['/ip/hotspot/profile/set', '=.id='.$id, '=dns-name='.$router->dns]);
                $applied++;
            }
        }

        return $applied;
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
        $routers = ($voucher->wifiZone?->mikrotiks ?? collect())->where('is_active', true);
        if ($routers->isEmpty() && ($voucher->wifiZone?->mikrotiks?->isNotEmpty() ?? false)) {
            return null;
        }

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
                $router->connectionPort(),
                $router->username,
                $router->password,
                $words,
                (int) ($router->timeout ?: 5),
                $router->usesSecureApi(),
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

    private function ask(string $host, int $port, string $username, string $password, array $words, int $timeout = 5, bool $secure = false): array
    {
        try {
            return $this->records($this->router->command($host, $port, $username, $password, $words, $timeout, $secure));
        } catch (RuntimeException $exception) {
            throw new RuntimeException($this->redact($exception->getMessage(), [$password]), 0, $exception);
        }
    }

    private function askOptional(string $host, int $port, string $username, string $password, array $words, int $timeout = 5, bool $secure = false): array
    {
        try {
            return $this->ask($host, $port, $username, $password, $words, $timeout, $secure);
        } catch (RuntimeException) {
            return [];
        }
    }

    private function profileNameFor(Mikrotik $router, Voucher $voucher): ?string
    {
        $link = PlanMikrotikProfile::query()
            ->where('mikrotik_id', $router->id)
            ->where('plan_id', $voucher->plan_id)
            ->with('profile')
            ->first();

        if (filled($link?->profile?->name)) {
            return $link->profile->name;
        }

        $fallback = $voucher->plan?->mikrotik_profile;

        return filled($fallback) ? (string) $fallback : null;
    }

    /**
     * @return array<int, string>
     */
    private function portalHosts(Mikrotik $router): array
    {
        $router->loadMissing('wifiZone');
        $hosts = [];
        foreach ([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            $router->dns,
            $router->detail('dns_name'),
        ] as $host) {
            if ($this->safeHost((string) $host)) {
                $hosts[] = strtolower((string) $host);
            }
        }
        foreach (['wa.me', 'api.whatsapp.com', 'web.whatsapp.com'] as $host) {
            $hosts[] = $host;
        }

        return array_values(array_unique($hosts));
    }

    private function safeHost(string $host): bool
    {
        return $host !== '' && (bool) preg_match('/^[a-z0-9][a-z0-9.-]{0,158}[a-z0-9]$/i', $host);
    }

    private function pick(array $row, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $row) && $row[$key] !== '') {
                $out[$key] = $row[$key];
            }
        }

        return $out;
    }

    private function firstFilled(array $rows, string $key): ?string
    {
        foreach ($rows as $row) {
            if (filled($row[$key] ?? null)) {
                return (string) $row[$key];
            }
        }

        return null;
    }

    private function stripSecrets(array $row): array
    {
        $clean = [];
        foreach ($row as $key => $value) {
            if (is_string($key) && $this->secretKey($key)) {
                continue;
            }
            $clean[$key] = is_array($value) ? $this->stripSecrets($value) : $value;
        }

        return $clean;
    }

    private function secretKey(string $key): bool
    {
        $key = strtolower($key);

        return str_contains($key, 'password') || str_contains($key, 'secret');
    }

    private function memoryPercent(array $resource): ?int
    {
        $free = $resource['free-memory'] ?? null;
        $total = $resource['total-memory'] ?? null;
        if (! is_numeric($free) || ! is_numeric($total) || (int) $total <= 0) {
            return null;
        }

        return (int) round((((int) $total - (int) $free) / (int) $total) * 100);
    }

    private function diskLabel(array $resource): ?string
    {
        $free = $resource['free-hdd-space'] ?? null;
        $total = $resource['total-hdd-space'] ?? null;
        if (! is_numeric($free) && ! is_numeric($total)) {
            return null;
        }

        $format = fn ($bytes) => round(((int) $bytes) / 1048576, 1).' Mo';
        if (is_numeric($free) && is_numeric($total)) {
            return $format($free).' libres / '.$format($total);
        }

        return $format(is_numeric($total) ? $total : $free);
    }

    private function names(array $rows): array
    {
        return array_values(array_filter(array_map(
            fn (array $row) => isset($row['name']) && $row['name'] !== '' ? (string) $row['name'] : null,
            $rows,
        )));
    }

    private function memoryLabel(array $resource): ?string
    {
        $free = $resource['free-memory'] ?? null;
        $total = $resource['total-memory'] ?? null;
        if (! is_numeric($free) && ! is_numeric($total)) {
            return null;
        }

        $format = fn ($bytes) => round(((int) $bytes) / 1048576, 1).' Mo';

        if (is_numeric($free) && is_numeric($total)) {
            return $format($free).' libres / '.$format($total);
        }

        return $format(is_numeric($total) ? $total : $free);
    }

    private function replaceProfiles(Mikrotik $router, array $profiles): void
    {
        $names = $this->names($profiles);
        $query = MikrotikProfile::query()->where('mikrotik_id', $router->id);

        if ($names === []) {
            $query->delete();

            return;
        }

        $query->whereNotIn('name', $names)->delete();
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

<?php

namespace App\Services\Mikrotik;

use App\Models\Mikrotik;
use App\Models\MikrotikProfile;
use App\Models\Plan;
use App\Models\PlanMikrotikProfile;
use App\Models\Voucher;
use App\Support\Correlation;
use App\Support\TenantManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MikrotikService
{
    /** @var array<string, string> */
    private array $unreachable = [];

    private bool $batching = false;

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
        $garden = $this->askOptional($host, $port, $username, $password, ['/ip/hotspot/walled-garden/print'], $timeout, $secure);
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
            'walled_garden' => array_map(fn (array $item) => $this->pick($item, ['.id', 'dst-host', 'action']), $garden),
            'hotspot_profile_rows' => array_map(fn (array $item) => $this->pick($item, ['.id', 'name', 'dns-name', 'hotspot-address', 'html-directory']), $serverProfiles),
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
                'last_synced_at' => now(),
                'last_error' => null,
                'details' => [
                    'connection_mode' => $this->connectionMode($router->host),
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
                    'walled_garden' => $found['walled_garden'],
                    'hotspot_profile_rows' => $found['hotspot_profile_rows'],
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

    public function createHotspotUser(Mikrotik $router, Voucher $voucher, bool $remember = true, ?string $profile = null, ?string $server = null): Voucher
    {
        $this->assertPair($router, $voucher);
        $voucher->loadMissing('plan');
        $explicit = filled($profile);
        $profile = $explicit ? $profile : $this->profileNameFor($router, $voucher);

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
            $words = [
                '/ip/hotspot/user/add',
                '=name='.$voucher->username,
                '=password='.$voucher->password,
                '=profile='.$profile,
            ];
            if (filled($server) && $server !== 'all') {
                if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/', $server)) {
                    throw new RuntimeException('Paramètres incompatibles.');
                }
                $words[] = '=server='.$server;
            }
            if (! $explicit) {
                $words[] = '=limit-uptime='.RouterOsProtocol::secondsToRouterTime((int) $voucher->plan->duration_seconds);
            }
            $words[] = '=comment=limete-manager';
            $this->command($router, $words, [$voucher->password]);
        }

        if ($remember) {
            $voucher->forceFill([
                'mikrotik_id' => $router->id,
                'sync_status' => 'synced',
                'sync_error' => null,
            ])->save();
        }

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

    /**
     * Routeurs autorisés : même entrepreneur, même WiFi Zone, routeur actif.
     * Le choix ne dépend pas de l'adresse IP du client.
     * Plusieurs routeurs en ligne reçoivent le même compte. mikrotik_id garde le premier succès.
     */
    public function authorizedRouters(Voucher $voucher)
    {
        $voucher->loadMissing('wifiZone.mikrotiks');

        return ($voucher->wifiZone?->mikrotiks ?? collect())
            ->filter(fn (Mikrotik $router) => $router->is_active
                && (int) $router->tenant_id === (int) $voucher->tenant_id
                && (int) $router->wifi_zone_id === (int) $voucher->wifi_zone_id)
            ->values();
    }

    /**
     * État réseau lu sur un routeur déjà en ligne de la zone du ticket.
     * session-time-left n'est pas la durée commerciale.
     *
     * @return array{state: string, ip: ?string, mac: ?string, session_time_left: ?string}
     */
    public function networkSession(Voucher $voucher): array
    {
        $unavailable = [
            'state' => 'session non disponible',
            'ip' => null,
            'mac' => null,
            'session_time_left' => null,
        ];
        $online = $this->authorizedRouters($voucher)
            ->filter(fn (Mikrotik $router) => $router->status === 'online')
            ->values();

        if ($online->isEmpty()) {
            return $unavailable;
        }

        $router = $online->firstWhere('id', $voucher->mikrotik_id) ?? $online->first();

        try {
            $rows = $this->getHotspotActiveUsers($router);
        } catch (RuntimeException) {
            return $unavailable;
        }

        foreach ($rows as $row) {
            if (($row['user'] ?? '') === $voucher->username) {
                return [
                    'state' => 'connecté',
                    'ip' => $row['address'] ?? null,
                    'mac' => $row['mac-address'] ?? null,
                    'session_time_left' => $row['session-time-left'] ?? null,
                ];
            }
        }

        return [
            'state' => 'déconnecté',
            'ip' => null,
            'mac' => null,
            'session_time_left' => null,
        ];
    }

    public function provisionVoucher(Voucher $voucher, ?string $profile = null, ?string $server = null): Voucher
    {
        if ($voucher->sync_status === 'synced' && $voucher->mikrotik_id) {
            return $voucher;
        }

        $voucher->loadMissing('plan', 'wifiZone.mikrotiks');
        $routers = $this->authorizedRouters($voucher);

        if ($routers->isEmpty()) {
            return $this->markUnsynced($voucher, 'pending', 'Aucun MikroTik associé à cette WiFi Zone. Le ticket est enregistré, mais il n’a pas été créé sur un routeur.');
        }

        $online = $routers->filter(fn (Mikrotik $router) => $router->status === 'online')->values();
        $targets = $online->isNotEmpty() ? $online : collect([$routers->first()]);
        $remembered = false;
        $error = null;

        foreach ($targets as $router) {
            try {
                $this->createHotspotUser($router, $voucher, ! $remembered, $profile, $server);
                $remembered = true;
                $this->bindVerifiedDevice($router, $voucher);
            } catch (Throwable $exception) {
                $error = $this->redact($exception->getMessage(), [$router->password, $voucher->password]);
            }
        }

        if ($remembered) {
            return $voucher->refresh();
        }

        return $this->markUnsynced($voucher, 'failed', $error ?? 'Le compte n’a pas été créé sur le MikroTik.');
    }

    /**
     * @param  array<int, Voucher>  $vouchers
     * @return array{synced: int, unsynced: int, error: ?string}
     */
    public function provisionMany(array $vouchers): array
    {
        $synced = 0;
        $error = null;
        $stopped = false;
        $this->batching = true;
        $this->unreachable = [];
        $this->openBatchSessions($vouchers);

        try {
            foreach ($vouchers as $voucher) {
                if ($stopped) {
                    $this->markUnsynced($voucher, 'failed', (string) $error);
                    continue;
                }

                $result = $this->provisionVoucher($voucher);
                if ($result->sync_status === 'synced') {
                    $synced++;
                    continue;
                }

                $error = $result->sync_error;
                if ($this->isTransient($error)) {
                    $stopped = true;
                }
            }
        } finally {
            $this->closeBatchSessions();
            $this->unreachable = [];
            $this->batching = false;
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
        $router->forceFill(['last_synced_at' => now()])->save();

        return $summary;
    }

    /**
     * Quelques essais bornés. On s'arrête dès que le routeur répond
     * ou que l'erreur n'est pas un simple problème de réseau.
     *
     * @return array{synced: int, unsynced: int, error: ?string, online: bool, attempts: int}
     */
    public function retryPending(Mikrotik $router, int $limit = 3): array
    {
        $limit = max(1, min(3, $limit));
        $summary = ['synced' => 0, 'unsynced' => 0, 'error' => null, 'online' => false, 'attempts' => 0];

        for ($attempt = 1; $attempt <= $limit; $attempt++) {
            $summary = $this->syncPending($router);
            $summary['attempts'] = $attempt;

            if (($summary['online'] ?? false) && (int) ($summary['unsynced'] ?? 0) === 0) {
                break;
            }

            if (! $this->isTransient($summary['error'] ?? null)) {
                break;
            }
        }

        return $summary;
    }

    public function isDocumentationHost(string $host): bool
    {
        $host = strtolower(rtrim(trim($host), '.'));

        if (in_array($host, [
            'localhost', 'example.com', 'example.net', 'example.org', 'example.edu',
            'invalid', 'test', 'metadata.google.internal',
        ], true) || preg_match('/\.(example|invalid|localhost|test)$/', $host)) {
            return true;
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $long = ip2long($host);
            foreach ([
                ['0.0.0.0', '0.255.255.255'],
                ['127.0.0.0', '127.255.255.255'],
                ['169.254.0.0', '169.254.255.255'],
                ['192.0.2.0', '192.0.2.255'],
                ['198.51.100.0', '198.51.100.255'],
                ['203.0.113.0', '203.0.113.255'],
                ['224.0.0.0', '239.255.255.255'],
            ] as [$start, $end]) {
                if ($long >= ip2long($start) && $long <= ip2long($end)) {
                    return true;
                }
            }
        }

        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = inet_pton($host);
            $loopback = inet_pton('::1');
            $documentation = inet_pton('2001:db8::');
            if ($packed === false) {
                return true;
            }
            if ($packed === $loopback || substr($packed, 0, 4) === substr((string) $documentation, 0, 4)) {
                return true;
            }
        }

        return false;
    }

    public function connectionMode(string $host): string
    {
        if ($this->isDocumentationHost($host) || $this->router::class !== RouterOsClient::class) {
            return 'simulation';
        }

        return 'real';
    }

    public function explainFailure(string $message): string
    {
        $lower = strtolower($message);

        if (str_contains($lower, 'password') || str_contains($lower, 'invalid user') || str_contains($lower, 'bad credentials')) {
            return 'Le nom d’utilisateur ou le mot de passe est incorrect.';
        }

        if (str_contains($lower, 'timed out') || str_contains($lower, 'time out') || str_contains($lower, 'timeout')) {
            return 'Le routeur ne répond pas à temps. Vérifiez qu’il est allumé et que l’adresse est la bonne.';
        }

        return 'Connexion impossible. Le routeur ne répond pas.';
    }

    /**
     * @return array{name: string, session_timeout: string, duration_label: string, data_label: string, rate_label: string, shared_users: int}
     */
    public function profileBlueprint(Plan $plan): array
    {
        return [
            'name' => $this->suggestProfileName($plan),
            'session_timeout' => RouterOsProtocol::secondsToRouterTime(max(1, (int) $plan->duration_seconds)),
            'duration_label' => $plan->durationLabel(),
            'data_label' => $plan->unlimited_data ? 'Sans limite' : 'Selon le forfait',
            'rate_label' => 'Non défini',
            'shared_users' => 1,
        ];
    }

    public function suggestProfileName(Plan $plan): string
    {
        $name = mb_strtolower($plan->name);

        if (preg_match('/(\d+)\s*heure/', $name, $matches)) {
            return $matches[1].'h';
        }

        if (preg_match('/(\d+)\s*jour/', $name, $matches)) {
            return $matches[1].'j';
        }

        if (preg_match('/(\d+)\s*minute/', $name, $matches)) {
            return $matches[1].'m';
        }

        $slug = strtolower((string) preg_replace('/[^a-z0-9]+/i', '', Str::ascii($plan->name)));
        $slug = substr($slug !== '' ? $slug : 'forfait', 0, 20);

        return $slug;
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

    public function hotspotHostMatches(Mikrotik $router, string $mac, string $ip): bool
    {
        $this->assertOwned($router);
        $mac = strtoupper($mac);
        $rows = $this->records($this->command($router, ['/ip/hotspot/host/print', '?mac-address='.$mac]));

        foreach ($rows as $row) {
            $rowMac = strtoupper(str_replace('-', ':', (string) ($row['mac-address'] ?? '')));
            $rowIp = (string) ($row['address'] ?? '');
            if ($rowMac === $mac && $rowIp === $ip) {
                return true;
            }
        }

        return false;
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

    /**
     * @param  array<int, string>  $queries
     * @return array<int, array<string, string>>
     */
    public function readPrint(Mikrotik $router, string $path, array $queries = []): array
    {
        $this->assertOwned($router);
        $allowed = [
            '/system/identity/print',
            '/system/resource/print',
            '/ip/hotspot/print',
            '/ip/hotspot/profile/print',
            '/ip/hotspot/user/profile/print',
            '/ip/hotspot/user/print',
            '/ip/hotspot/active/print',
            '/ip/hotspot/walled-garden/print',
            '/interface/print',
            '/ip/address/print',
            '/ip/dns/print',
            '/ip/pool/print',
            '/queue/simple/print',
        ];
        if (! in_array($path, $allowed, true)) {
            throw new RuntimeException('Lecture non autorisée.');
        }
        foreach ($queries as $query) {
            if (! is_string($query) || ! str_starts_with($query, '?') || str_contains($query, ' ')) {
                throw new RuntimeException('Lecture non autorisée.');
            }
        }

        return $this->records($this->command($router, array_merge([$path], $queries)));
    }

    /**
     * @param  array<int, string>  $words
     * @param  array<int, string>  $secrets
     * @param  array<string, mixed>  $context
     * @return array<int, array<string, string>>
     */
    public function guardedCommand(Mikrotik $router, array $words, array $secrets = [], array $context = []): array
    {
        $this->assertOwned($router);
        $this->assertSafeWords($words, $context);

        return $this->command($router, $words, $secrets);
    }

    /**
     * @return array<int, string>
     */
    public function portalHostsFor(Mikrotik $router): array
    {
        return $this->portalHosts($router);
    }

    public function isSafeDns(string $host): bool
    {
        return $this->safeHost($host);
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

    /**
     * @param  array<int, Voucher>  $vouchers
     */
    private function openBatchSessions(array $vouchers): void
    {
        if (! method_exists($this->router, 'open')) {
            return;
        }

        foreach ($this->batchTargets($vouchers) as $router) {
            try {
                $this->router->open(
                    $router->host,
                    $router->connectionPort(),
                    $router->username,
                    (string) $router->password,
                    (int) ($router->timeout ?: 5),
                    $router->usesSecureApi(),
                );
            } catch (Throwable $exception) {
                $message = $this->redact($exception->getMessage(), [$router->password]);
                $this->unreachable[$this->routerKey($router)] = $message !== ''
                    ? $message
                    : 'Connexion impossible au routeur.';
            }
        }
    }

    private function closeBatchSessions(): void
    {
        if (method_exists($this->router, 'close')) {
            $this->router->close();
        }
    }

    /**
     * @param  array<int, Voucher>  $vouchers
     * @return array<int, Mikrotik>
     */
    private function batchTargets(array $vouchers): array
    {
        $voucher = $vouchers[0] ?? null;
        if (! $voucher instanceof Voucher) {
            return [];
        }

        $voucher->loadMissing('wifiZone.mikrotiks');
        $routers = $this->authorizedRouters($voucher);
        if ($routers->isEmpty()) {
            return [];
        }

        $online = $routers->filter(fn (Mikrotik $router) => $router->status === 'online')->values();

        return ($online->isNotEmpty() ? $online : collect([$routers->first()]))->all();
    }

    private function routerKey(Mikrotik $router): string
    {
        return strtolower((string) $router->host).'|'.$router->connectionPort().'|'.($router->usesSecureApi() ? '1' : '0');
    }

    private function command(Mikrotik $router, array $words, array $secrets = []): array
    {
        $path = $words[0] ?? 'inconnue';
        $secrets[] = $router->password;
        $key = $this->routerKey($router);
        if ($this->batching && isset($this->unreachable[$key])) {
            throw new RuntimeException($this->unreachable[$key]);
        }
        $started = microtime(true);

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
            if ($this->batching && ($this->isOffline($exception) || $this->isTransient($message))) {
                $this->unreachable[$key] = $message;
            }
            Log::warning('mikrotik.command_failed', array_merge($this->trace($router, $path, $started), [
                'success' => false,
                'message' => $message,
            ]));

            throw new RuntimeException($message, 0, $exception);
        }

        Log::info('mikrotik.command', $this->trace($router, $path, $started));

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

        if ((int) $voucher->wifi_zone_id !== (int) $router->wifi_zone_id) {
            throw new RuntimeException('Ce ticket n’est pas autorisé sur ce MikroTik. Il appartient à une autre WiFi Zone.');
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
     * Hôtes autorisés avant paiement : portail Limete, DNS du routeur, API et widget iKeePay.
     * Aucun accès Internet général, et aucun hôte qui n'est pas nécessaire au paiement.
     *
     * @return array<int, string>
     */
    private function portalHosts(Mikrotik $router): array
    {
        $router->loadMissing('wifiZone');
        $hosts = [];
        foreach ([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('services.ikeepay.base_url'), PHP_URL_HOST),
            parse_url((string) config('services.ikeepay.checkout_url'), PHP_URL_HOST),
            $router->dns,
            $router->detail('dns_name'),
        ] as $host) {
            if ($this->safeHost((string) $host)) {
                $hosts[] = strtolower((string) $host);
            }
        }
        return array_values(array_unique($hosts));
    }

    private function bindVerifiedDevice(Mikrotik $router, Voucher $voucher): void
    {
        $mac = strtoupper(str_replace('-', ':', (string) $voucher->mac_address));
        if (! preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac)) {
            return;
        }

        try {
            $this->updateHotspotUser($router, $voucher->username, ['mac-address' => $mac]);
        } catch (Throwable) {
            // Le compte hotspot existe déjà. L'accès reste possible avec l'identifiant du ticket.
        }
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

        foreach (['connexion impossible', 'timed out', 'time out', 'connection refused', 'no route to host', 'network is unreachable', 'name or service not known', 'failed to respond', 'connection attempt failed', 'n’a pas répondu', 'tentative de connexion'] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function isTransient(?string $message): bool
    {
        $message = strtolower((string) $message);

        foreach (['connexion impossible', 'timed out', 'time out', 'timeout', 'connection refused', 'no route', 'unreachable', 'name or service not known', 'failed to respond', 'connection attempt failed', 'n’a pas répondu', 'tentative de connexion'] as $needle) {
            if ($message !== '' && str_contains($message, $needle)) {
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
            'correlation_id' => app(Correlation::class)->id(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trace(Mikrotik $router, string $command, float $started): array
    {
        return $this->context($router, $command) + [
            'duration_ms' => (int) round((microtime(true) - $started) * 1000),
            'success' => true,
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

    /**
     * @param  array<int, string>  $words
     * @param  array<string, mixed>  $context
     */
    private function assertSafeWords(array $words, array $context): void
    {
        $path = $words[0] ?? '';
        $joined = strtolower(implode(' ', $words));
        foreach (['reset-configuration', '/ip/address/', '/ip/route/', '/ip/firewall/', '/system/reboot', '/system/shutdown'] as $denied) {
            if (str_contains($joined, $denied)) {
                throw new RuntimeException('Commande refusée.');
            }
        }

        $allowed = [
            '/ip/hotspot/profile/set',
            '/ip/hotspot/walled-garden/add',
            '/ip/hotspot/walled-garden/remove',
            '/ip/hotspot/add',
            '/ip/hotspot/user/profile/add',
            '/ip/hotspot/user/profile/remove',
            '/ip/hotspot/user/add',
            '/ip/hotspot/user/remove',
        ];
        if (! in_array($path, $allowed, true)) {
            throw new RuntimeException('Commande refusée.');
        }

        $keys = $this->wordKeys($words);
        if ($path === '/ip/hotspot/profile/set' && array_diff($keys, ['.id', 'dns-name']) !== []) {
            throw new RuntimeException('Commande refusée.');
        }
        if ($path === '/ip/hotspot/add' && (array_diff($keys, ['name', 'interface', 'address-pool', 'profile']) !== [] || ! in_array('name', $keys, true) || ! in_array('interface', $keys, true))) {
            throw new RuntimeException('Commande refusée.');
        }
        if ($path === '/ip/hotspot/user/profile/add' && array_diff($keys, ['name', 'session-timeout', 'rate-limit', 'idle-timeout', 'shared-users', 'address-pool', 'parent-queue']) !== []) {
            throw new RuntimeException('Commande refusée.');
        }
        if ($path === '/ip/hotspot/user/add') {
            $name = (string) $this->wordValue($words, 'name');
            if (! str_starts_with($name, 'LIMETE_TEST_') || array_diff($keys, ['name', 'password', 'profile', 'comment', 'limit-uptime']) !== []) {
                throw new RuntimeException('Commande refusée.');
            }
        }
        if (in_array($path, ['/ip/hotspot/walled-garden/remove', '/ip/hotspot/user/profile/remove'], true) && ($context['rollback'] ?? false) !== true) {
            throw new RuntimeException('Commande refusée.');
        }
        if ($path === '/ip/hotspot/user/remove' && ! str_starts_with((string) ($context['username'] ?? ''), 'LIMETE_TEST_')) {
            throw new RuntimeException('Commande refusée.');
        }
        if ($path === '/ip/hotspot/remove') {
            throw new RuntimeException('Commande refusée.');
        }
    }

    /**
     * @param  array<int, string>  $words
     * @return array<int, string>
     */
    private function wordKeys(array $words): array
    {
        $keys = [];
        foreach (array_slice($words, 1) as $word) {
            if (preg_match('/^=([^=]+)=/', $word, $matches)) {
                $keys[] = $matches[1];
            }
        }

        return $keys;
    }

    /**
     * @param  array<int, string>  $words
     */
    private function wordValue(array $words, string $key): ?string
    {
        $prefix = '='.$key.'=';
        foreach (array_slice($words, 1) as $word) {
            if (str_starts_with($word, $prefix)) {
                return substr($word, strlen($prefix));
            }
        }

        return null;
    }
}

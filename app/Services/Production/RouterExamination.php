<?php

namespace App\Services\Production;

use App\Models\Mikrotik;
use App\Models\Plan;
use App\Services\Mikrotik\MikrotikService;
use App\Support\Correlation;
use RuntimeException;

class RouterExamination
{
    public function __construct(
        private EndpointProbe $endpoints,
        private MikrotikService $mikrotik,
        private Correlation $correlation,
    ) {}

    public function isDocumentationHost(string $host): bool
    {
        if (! filter_var($host, FILTER_VALIDATE_IP)) {
            return false;
        }

        return str_starts_with($host, '192.0.2.')
            || str_starts_with($host, '198.51.100.')
            || str_starts_with($host, '203.0.113.');
    }

    /**
     * @return array<string, mixed>
     */
    public function run(Mikrotik $router): array
    {
        $correlationId = $this->correlation->reset();
        $router->loadMissing('wifiZone.plans', 'planLinks.profile');

        if ($this->isDocumentationHost((string) $router->host)) {
            return $this->remember($router, $this->untouched($router, $correlationId, 'L’hôte '.$router->host.' est une adresse de documentation (RFC 5737), pas un routeur enregistré pour un test réel. Aucune commande API n’a été envoyée.'));
        }

        $resolved = $this->endpoints->resolve((string) $router->host);
        if ($resolved['addresses'] !== []) {
            foreach ($resolved['addresses'] as $address) {
                if ($this->isDocumentationHost($address)) {
                    return $this->remember($router, $this->untouched($router, $correlationId, 'Le nom se résout vers une adresse de documentation. REAL MIKROTIK NOT TESTED.'));
                }
            }
        }

        if (! filter_var($router->host, FILTER_VALIDATE_IP) && ($resolved['addresses'] === [] || ! $resolved['checked'])) {
            $report = $this->untouched($router, $correlationId, $resolved['error'] ?: 'Résolution non vérifiée');
            $report['reads']['resolve'] = $this->item('FAIL', $resolved['error'] ?: 'Résolution impossible');

            return $this->remember($router, $report);
        }

        $target = $resolved['addresses'][0] ?? (string) $router->host;
        $apiPort = (int) ($router->api_port ?: 8728);
        $tcpApi = $this->endpoints->tcp($target, $apiPort, 3);
        $tcpSsl = $router->usesSecureApi()
            ? $this->endpoints->tcp($target, (int) ($router->api_ssl_port ?: 8729), 3)
            : null;
        $usedTcp = $router->usesSecureApi() ? $tcpSsl : $tcpApi;
        $report = $this->untouched($router, $correlationId, null);
        $report['documentation'] = false;
        $report['reads']['resolve'] = $this->item('PASS', implode(', ', $resolved['addresses'] ?: [$target]));
        $report['reads']['tcp'] = $tcpApi['ok']
            ? $this->item('PASS', 'TCP '.$target.':'.$apiPort.' en '.$tcpApi['milliseconds'].' ms')
            : $this->item('FAIL', 'TCP '.$target.':'.$apiPort.' — '.$tcpApi['error']);
        $report['reads']['api_ssl'] = $tcpSsl === null
            ? $this->item('NOT TESTED', 'API-SSL non configuré')
            : ($tcpSsl['ok']
                ? $this->item('PASS', 'TCP API-SSL en '.$tcpSsl['milliseconds'].' ms')
                : $this->item('FAIL', 'TCP API-SSL — '.$tcpSsl['error']));

        if (! $usedTcp || ! $usedTcp['ok']) {
            $report['banner'] = 'REAL MIKROTIK NOT TESTED';
            $report['error'] = 'Le serveur Laravel ne peut pas joindre le MikroTik.';
            foreach (['authenticate', 'identity', 'version', 'uptime', 'cpu', 'memory', 'interfaces', 'addresses', 'dns', 'hotspot_users', 'sessions'] as $key) {
                $report['reads'][$key] = $this->item('NOT TESTED', $report['error']);
            }
            $report['hotspot']['status'] = 'NOT TESTED';
            $report['hotspot']['detail'] = $report['error'];
            $router->forceFill([
                'status' => 'offline',
                'last_seen_at' => now(),
                'last_error' => $report['error'],
            ])->save();

            return $this->remember($router, $report);
        }

        $identity = $this->read($router, '/system/identity/print');
        if ($identity['status'] === 'FAIL') {
            $report['reads']['authenticate'] = $this->item('FAIL', $identity['detail']);
            $report['banner'] = 'REAL MIKROTIK NOT TESTED';
            $report['error'] = $identity['detail'];
            $router->forceFill([
                'status' => 'error',
                'last_seen_at' => now(),
                'last_error' => $this->redact($identity['detail'], $router),
            ])->save();

            return $this->remember($router, $report);
        }

        $report['authenticated'] = true;
        $report['banner'] = null;
        $name = $identity['rows'][0]['name'] ?? null;
        $report['reads']['authenticate'] = $this->item('PASS', 'API authentifiée');
        $report['reads']['identity'] = filled($name)
            ? $this->item('PASS', (string) $name)
            : $this->item('WARN', 'Non détecté');

        $resource = $this->read($router, '/system/resource/print');
        $row = $resource['rows'][0] ?? [];
        $report['reads']['version'] = $this->field($resource, $row['version'] ?? null);
        $report['reads']['uptime'] = $this->field($resource, $row['uptime'] ?? null);
        $report['reads']['cpu'] = $this->field($resource, $row['cpu-load'] ?? null);
        $report['reads']['memory'] = $this->field($resource, $this->memory($row));
        $report['reads']['interfaces'] = $this->listRead($this->read($router, '/interface/print'), 'name');
        $report['reads']['addresses'] = $this->listRead($this->read($router, '/ip/address/print'), 'address');
        $dns = $this->read($router, '/ip/dns/print');
        $report['reads']['dns'] = $this->field($dns, $dns['rows'][0]['servers'] ?? $dns['rows'][0]['dynamic-servers'] ?? null);

        $hotspots = $this->read($router, '/ip/hotspot/print');
        $serverProfiles = $this->read($router, '/ip/hotspot/profile/print');
        $userProfiles = $this->read($router, '/ip/hotspot/user/profile/print');
        $users = $this->read($router, '/ip/hotspot/user/print');
        $active = $this->read($router, '/ip/hotspot/active/print');
        $report['hotspot'] = $this->hotspot($hotspots, $serverProfiles);
        $report['reads']['hotspot_users'] = $users['status'] === 'FAIL'
            ? $this->item('FAIL', $users['detail'])
            : $this->item('PASS', count($this->publicUsers($users['rows'])).' utilisateur(s) lu(s)');
        $report['reads']['sessions'] = $active['status'] === 'FAIL'
            ? $this->item('FAIL', $active['detail'])
            : $this->item('PASS', count($active['rows']).' session(s) lue(s)');
        $report['sessions'] = array_map(fn (array $session) => $this->only($session, ['user', 'address', 'mac-address', 'uptime', 'session-time-left']), $active['rows']);
        $report['profile_names'] = array_values(array_filter(array_map(fn (array $item) => $item['name'] ?? null, $userProfiles['rows'])));
        $report['plans'] = $userProfiles['status'] === 'FAIL'
            ? []
            : $this->plans($router, $report['profile_names']);

        $router->forceFill([
            'status' => 'online',
            'identity' => filled($name) ? $name : $router->identity,
            'routeros_version' => filled($row['version'] ?? null) ? $row['version'] : $router->routeros_version,
            'last_seen_at' => now(),
            'last_error' => null,
        ])->save();

        return $this->remember($router, $report);
    }

    /**
     * @param  array<string, mixed>  $report
     * @return array<string, mixed>
     */
    private function remember(Mikrotik $router, array $report): array
    {
        $details = $router->details ?? [];
        $details['production_probe'] = [
            'at' => $report['at'],
            'correlation_id' => $report['correlation_id'],
            'banner' => $report['banner'],
            'authenticated' => $report['authenticated'],
            'documentation' => $report['documentation'],
            'error' => $report['error'],
            'reads' => $report['reads'],
            'hotspot' => $report['hotspot'],
            'plans' => $report['plans'],
        ];
        $router->forceFill(['details' => $details])->save();

        return $report;
    }

    /**
     * @return array<string, mixed>
     */
    private function untouched(Mikrotik $router, string $correlationId, ?string $reason): array
    {
        $pending = $this->item('NOT TESTED', $reason ?: 'Aucun test réel n’a été lancé.');

        return [
            'router_id' => $router->id,
            'router' => $router->name,
            'host' => $router->host,
            'at' => now()->toIso8601String(),
            'correlation_id' => $correlationId,
            'documentation' => $reason !== null && str_contains((string) $reason, 'documentation'),
            'authenticated' => false,
            'banner' => 'REAL MIKROTIK NOT TESTED',
            'error' => $reason,
            'reads' => [
                'resolve' => $pending,
                'tcp' => $pending,
                'api_ssl' => $router->usesSecureApi() ? $pending : $this->item('NOT TESTED', 'API-SSL non configuré'),
                'authenticate' => $pending,
                'identity' => $pending,
                'version' => $pending,
                'uptime' => $pending,
                'cpu' => $pending,
                'memory' => $pending,
                'interfaces' => $pending,
                'addresses' => $pending,
                'dns' => $pending,
                'hotspot_users' => $pending,
                'sessions' => $pending,
            ],
            'hotspot' => [
                'status' => 'NOT TESTED',
                'detail' => $reason ?: 'HotSpot non lu.',
                'present' => null,
                'disabled' => null,
                'interface' => null,
                'profile' => null,
                'dns_name' => null,
                'pool' => null,
                'login_by' => null,
                'http_pap' => 'NOT TESTED',
                'cookie' => 'NOT TESTED',
                'https' => 'NOT TESTED',
                'action' => null,
            ],
            'plans' => [],
            'profile_names' => [],
            'sessions' => [],
        ];
    }

    /**
     * @return array{status: string, detail: string, rows: array<int, array<string, string>>}
     */
    private function read(Mikrotik $router, string $path): array
    {
        try {
            $rows = array_map(fn (array $row) => $this->strip($row), $this->mikrotik->readPrint($router, $path));

            return ['status' => 'PASS', 'detail' => count($rows).' ligne(s)', 'rows' => $rows];
        } catch (RuntimeException $exception) {
            return ['status' => 'FAIL', 'detail' => $this->redact($exception->getMessage(), $router), 'rows' => []];
        }
    }

    /**
     * @param  array{status: string, detail: string, rows: array<int, array<string, string>>}  $read
     * @return array{status: string, detail: string}
     */
    private function field(array $read, mixed $value): array
    {
        if ($read['status'] === 'FAIL') {
            return $this->item('FAIL', $read['detail']);
        }

        return filled($value) ? $this->item('PASS', (string) $value) : $this->item('WARN', 'Non détecté');
    }

    /**
     * @param  array{status: string, detail: string, rows: array<int, array<string, string>>}  $read
     * @return array{status: string, detail: string}
     */
    private function listRead(array $read, string $key): array
    {
        if ($read['status'] === 'FAIL') {
            return $this->item('FAIL', $read['detail']);
        }

        $names = array_values(array_filter(array_map(fn (array $row) => $row[$key] ?? null, $read['rows'])));

        return $names === []
            ? $this->item('WARN', 'Non détecté')
            : $this->item('PASS', implode(', ', $names));
    }

    /**
     * @param  array{status: string, detail: string, rows: array<int, array<string, string>>}  $hotspots
     * @param  array{status: string, detail: string, rows: array<int, array<string, string>>}  $profiles
     * @return array<string, mixed>
     */
    private function hotspot(array $hotspots, array $profiles): array
    {
        if ($hotspots['status'] === 'FAIL') {
            return [
                'status' => 'FAIL',
                'detail' => $hotspots['detail'],
                'present' => null,
                'action' => 'Lecture impossible. Aucune configuration n’a été modifiée.',
            ];
        }

        $server = $hotspots['rows'][0] ?? null;
        if ($server === null) {
            return [
                'status' => 'FAIL',
                'detail' => 'Configuration manquante',
                'present' => false,
                'action' => 'Ouvrir Préparer mon MikroTik et confirmer explicitement la création du HotSpot. Cette page ne modifie rien.',
            ];
        }

        $profile = $profiles['rows'][0] ?? [];
        $loginBy = (string) ($server['login-by'] ?? $profile['login-by'] ?? '');
        $disabled = ($server['disabled'] ?? 'false') === 'true';

        return [
            'status' => $disabled ? 'WARN' : 'PASS',
            'detail' => $disabled
                ? 'Le serveur HotSpot est désactivé. Aucune activation n’a été envoyée.'
                : ($server['name'] ?? 'HotSpot lu'),
            'present' => true,
            'disabled' => $disabled,
            'interface' => $server['interface'] ?? null,
            'profile' => $server['profile'] ?? ($profile['name'] ?? null),
            'dns_name' => $server['dns-name'] ?? ($profile['dns-name'] ?? null),
            'pool' => $server['address-pool'] ?? null,
            'login_by' => $loginBy !== '' ? $loginBy : null,
            'http_pap' => $loginBy === '' ? 'NOT TESTED' : (str_contains($loginBy, 'http-pap') ? 'PASS' : 'WARN'),
            'cookie' => $loginBy === '' ? 'NOT TESTED' : (str_contains($loginBy, 'cookie') ? 'PASS' : 'WARN'),
            'https' => isset($profile['ssl-certificate']) && $profile['ssl-certificate'] !== 'none'
                ? 'PASS'
                : 'NOT TESTED',
            'action' => $disabled
                ? 'Ouvrir Préparer mon MikroTik et confirmer explicitement l’activation. Cette page ne modifie rien.'
                : null,
        ];
    }

    /**
     * @param  array<int, string>  $live
     * @return array<int, array<string, string>>
     */
    private function plans(Mikrotik $router, array $live): array
    {
        $rows = [];
        foreach ($router->wifiZone?->plans ?? [] as $plan) {
            if (! $plan instanceof Plan) {
                continue;
            }
            $link = $router->planLinks->firstWhere('plan_id', $plan->id);
            $profile = $link?->profile?->name ?: $plan->mikrotik_profile;
            if (! filled($profile)) {
                $rows[] = ['plan' => $plan->name, 'profile' => 'Profil non associé', 'status' => 'WARN', 'detail' => 'Aucun profil RouterOS associé. Aucun profil n’a été créé.'];

                continue;
            }
            if (! in_array($profile, $live, true)) {
                $rows[] = ['plan' => $plan->name, 'profile' => (string) $profile, 'status' => 'FAIL', 'detail' => 'FAIL — profil inexistant sur le MikroTik.'];

                continue;
            }
            $rows[] = ['plan' => $plan->name, 'profile' => (string) $profile, 'status' => 'PASS', 'detail' => 'Profil lu sur le routeur.'];
        }

        return $rows;
    }

    /**
     * @param  array<int, array<string, string>>  $rows
     * @return array<int, array<string, string>>
     */
    private function publicUsers(array $rows): array
    {
        return array_map(fn (array $row) => $this->only($row, ['name', 'profile', 'disabled', 'comment']), $rows);
    }

    /**
     * @param  array<string, string>  $row
     * @param  array<int, string>  $keys
     * @return array<string, string>
     */
    private function only(array $row, array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            if (isset($row[$key]) && $row[$key] !== '') {
                $out[$key] = $row[$key];
            }
        }

        return $out;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, string>
     */
    private function strip(array $row): array
    {
        $clean = [];
        foreach ($row as $key => $value) {
            $name = strtolower((string) $key);
            if (str_contains($name, 'password') || str_contains($name, 'secret')) {
                continue;
            }
            $clean[$key] = $value;
        }

        return $clean;
    }

    private function memory(array $row): ?string
    {
        if (! isset($row['free-memory'], $row['total-memory'])) {
            return null;
        }

        return $row['free-memory'].' libres / '.$row['total-memory'];
    }

    /**
     * @return array{status: string, detail: string}
     */
    private function item(string $status, string $detail): array
    {
        return ['status' => $status, 'detail' => $detail];
    }

    private function redact(string $message, Mikrotik $router): string
    {
        $secret = (string) $router->password;
        if ($secret !== '') {
            $message = str_replace($secret, '[masqué]', $message);
        }

        return (string) preg_replace('/=password=[^\s,]*/', '=password=[masqué]', $message);
    }
}

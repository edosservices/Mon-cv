<?php

namespace App\Services\Mikrotik;

use App\Models\Mikrotik;
use App\Models\MikrotikSnapshot;
use App\Services\AuditLogger;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class MikrotikPreparation
{
    public function __construct(
        private MikrotikService $mikrotik,
        private HostResolver $resolver,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function present(Mikrotik $router): array
    {
        $router->loadMissing('profiles', 'planLinks.profile', 'wifiZone');
        $details = $router->details ?? [];
        $servers = $details['hotspot_servers'] ?? [];
        $dnsCurrent = $details['dns_name'] ?? null;
        $dnsProposed = $router->dns ?: $dnsCurrent;
        $garden = collect($details['walled_garden'] ?? [])->pluck('dst-host')->filter()->values()->all();
        $wanted = $this->mikrotik->portalHostsFor($router);
        $missingHosts = array_values(array_diff($wanted, $garden));
        $profiles = $router->profiles;
        $resolution = $details['dns_resolution']['label'] ?? 'Résolution non vérifiée';

        return [
            'steps' => [
                ['label' => 'Connectivité API', 'ok' => $router->status === 'online', 'detail' => $router->status === 'online' ? 'CONNECTÉ' : ($router->last_error ?: 'Non détecté')],
                ['label' => 'Identity', 'ok' => filled($router->identity), 'detail' => $router->identity ?: 'Non détecté'],
                ['label' => 'RouterOS', 'ok' => filled($router->routeros_version), 'detail' => $router->routeros_version ?: 'Non détecté'],
                ['label' => 'HotSpot', 'ok' => $servers !== [], 'detail' => $servers === [] ? 'HotSpot non configuré' : collect($servers)->pluck('name')->implode(', ')],
                ['label' => 'DNS', 'ok' => filled($dnsCurrent) || filled($router->dns), 'detail' => filled($dnsCurrent) || filled($router->dns) ? ($dnsCurrent ?: $router->dns) : 'DNS non configuré'],
                ['label' => 'Walled Garden', 'ok' => $missingHosts === [] && $garden !== [], 'detail' => $garden === [] ? 'Aucune règle lue' : implode(', ', $garden)],
                ['label' => 'Portail captif', 'ok' => collect($this->portalFiles())->every(fn (array $file) => $file['present'] && ! $file['secret']), 'detail' => 'Dossier hotspot/'],
                ['label' => 'Profils HotSpot', 'ok' => $profiles->isNotEmpty(), 'detail' => $profiles->isEmpty() ? 'Non détecté' : $profiles->pluck('name')->implode(', ')],
                ['label' => 'Vérification finale', 'ok' => false, 'detail' => 'À lancer explicitement'],
            ],
            'dns' => [
                'current' => filled($dnsCurrent) ? $dnsCurrent : 'DNS non configuré',
                'proposed' => $dnsProposed ?: '',
                'impact' => 'Met à jour uniquement le DNS Name du profil HotSpot. L’adresse IP et la route par défaut ne sont pas modifiées.',
                'command' => filled($dnsProposed) ? '/ip hotspot profile set [find] dns-name='.$dnsProposed : 'Aucune commande : DNS non renseigné.',
                'resolution' => $resolution,
                'portal_url' => filled($dnsProposed) ? 'https://'.$dnsProposed.'/login' : null,
            ],
            'garden' => [
                'current' => $garden === [] ? 'Aucune règle lue' : implode(', ', $garden),
                'proposed' => $missingHosts === [] ? 'Aucun hôte à ajouter' : implode(', ', $missingHosts),
                'impact' => 'Ajoute seulement les hôtes absents. Aucune règle existante n’est supprimée.',
                'command' => $missingHosts === [] ? 'Aucune commande.' : implode("\n", array_map(fn (string $host) => '/ip hotspot walled-garden add dst-host='.$host, $missingHosts)),
            ],
            'hotspot' => $this->hotspotProposal($details),
            'profiles' => $profiles,
            'files' => $this->portalFiles(),
            'snapshots' => MikrotikSnapshot::query()->where('mikrotik_id', $router->id)->latest()->limit(8)->get(),
        ];
    }

    /**
     * @return array<int, array{file: string, present: bool, secret: bool}>
     */
    public function portalFiles(?string $directory = null): array
    {
        $directory ??= base_path('hotspot');
        $required = [
            'login.html', 'status.html', 'logout.html', 'error.html',
            'css/style.css', 'js/app.js', 'js/plan-data.js', 'js/limete-bridge.js', 'md5.js',
        ];
        $rows = [];
        foreach ($required as $file) {
            $path = $directory.DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $file);
            $rows[] = [
                'file' => $file,
                'present' => is_file($path),
                'secret' => is_file($path) && $this->fileHasEmbeddedSecret($path),
            ];
        }

        return $rows;
    }

    /**
     * @return array{checked: bool, addresses: array<int, string>, label: string}
     */
    public function checkDns(Mikrotik $router, string $host): array
    {
        $result = $this->resolver->resolve($host);
        $details = $router->details ?? [];
        $details['dns_resolution'] = [
            'host' => $host,
            'checked' => $result['checked'],
            'addresses' => $result['addresses'],
            'label' => $result['label'],
        ];
        $router->forceFill(['details' => $details])->save();
        $this->audit->record('mikrotik.dns_checked', $router, null, [
            'host' => $host,
            'result' => $result['label'],
            'change' => 'lecture DNS',
            'error' => null,
        ]);

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function apply(Mikrotik $router, string $group, array $input): string
    {
        try {
            $message = match ($group) {
                'dns' => $this->applyDns($router, (string) ($input['dns'] ?? '')),
                'walled_garden' => $this->applyGarden($router),
                'hotspot' => $this->applyHotspot($router, $input),
                'profile' => $this->applyProfile($router, $input),
                default => throw new RuntimeException('Groupe inconnu.'),
            };
            $this->audit->record('mikrotik.prepare_applied', $router, null, [
                'group' => $group,
                'change' => $message,
                'result' => 'appliqué',
                'error' => null,
            ]);

            return $message;
        } catch (Throwable $exception) {
            $message = $this->clean($exception->getMessage(), $router);
            $this->audit->record('mikrotik.prepare_refused', $router, null, [
                'group' => $group,
                'change' => null,
                'result' => 'refusé',
                'error' => $message,
            ]);

            throw new RuntimeException($message, 0, $exception);
        }
    }

    public function restore(Mikrotik $router, MikrotikSnapshot $snapshot): string
    {
        if ((int) $snapshot->mikrotik_id !== (int) $router->id) {
            throw new RuntimeException('Snapshot introuvable.');
        }
        if ($snapshot->restored_at) {
            throw new RuntimeException('Ce snapshot a déjà été restauré.');
        }

        try {
            $message = match ($snapshot->group) {
                'dns' => $this->restoreDns($router, $snapshot),
                'walled_garden' => $this->restoreGarden($router, $snapshot),
                'profile' => $this->restoreProfile($router, $snapshot),
                default => throw new RuntimeException('Restauration non disponible pour ce groupe.'),
            };
            $snapshot->forceFill(['restored_at' => now()])->save();
            $this->audit->record('mikrotik.prepare_restored', $router, null, [
                'group' => $snapshot->group,
                'change' => $message,
                'result' => 'restauré',
                'error' => null,
            ]);

            return $message;
        } catch (Throwable $exception) {
            $message = $this->clean($exception->getMessage(), $router);
            $this->audit->record('mikrotik.prepare_restore_failed', $router, null, [
                'group' => $snapshot->group,
                'change' => null,
                'result' => 'échec',
                'error' => $message,
            ]);

            throw new RuntimeException($message, 0, $exception);
        }
    }

    /**
     * @return array<int, array{label: string, ok: bool, detail: string}>
     */
    public function verify(Mikrotik $router): array
    {
        $steps = [];
        $username = 'LIMETE_TEST_'.now()->format('YmdHis');
        $password = Str::lower(Str::random(10));
        $created = false;

        try {
            $checked = $this->mikrotik->testConnection($router);
            $steps[] = ['label' => 'API', 'ok' => $checked->status === 'online', 'detail' => $checked->status === 'online' ? 'CONNECTÉ' : ($checked->last_error ?: 'Non détecté')];
            if ($checked->status === 'online') {
                $identity = $this->mikrotik->readPrint($router, '/system/identity/print');
                $resource = $this->mikrotik->readPrint($router, '/system/resource/print');
                $hotspots = $this->mikrotik->readPrint($router, '/ip/hotspot/print');
                $serverProfiles = $this->mikrotik->readPrint($router, '/ip/hotspot/profile/print');
                $profiles = $this->mikrotik->readPrint($router, '/ip/hotspot/user/profile/print');
                $steps[] = ['label' => 'Identity', 'ok' => filled($identity[0]['name'] ?? null), 'detail' => $identity[0]['name'] ?? 'Non détecté'];
                $steps[] = ['label' => 'RouterOS', 'ok' => filled($resource[0]['version'] ?? null), 'detail' => $resource[0]['version'] ?? 'Non détecté'];
                $steps[] = ['label' => 'HotSpot', 'ok' => $hotspots !== [], 'detail' => $hotspots === [] ? 'HotSpot non configuré' : ($hotspots[0]['name'] ?? 'Non détecté')];
                $dns = $hotspots[0]['dns-name'] ?? $serverProfiles[0]['dns-name'] ?? null;
                $steps[] = ['label' => 'DNS', 'ok' => filled($dns), 'detail' => filled($dns) ? $dns : 'DNS non configuré'];
                $steps[] = ['label' => 'Profils', 'ok' => $profiles !== [], 'detail' => $profiles === [] ? 'Non détecté' : implode(', ', array_filter(array_column($profiles, 'name')))];

                $profileName = $profiles[0]['name'] ?? null;
                if (! filled($profileName)) {
                    $steps[] = ['label' => 'Utilisateur test', 'ok' => false, 'detail' => 'Aucun profil RouterOS. Le compte de test n’a pas été créé.'];
                } else {
                    $this->mikrotik->guardedCommand($router, [
                        '/ip/hotspot/user/add',
                        '=name='.$username,
                        '=password='.$password,
                        '=profile='.$profileName,
                        '=limit-uptime=5m',
                        '=comment=limete-test',
                    ], [$password]);
                    $created = true;
                    $found = $this->mikrotik->readPrint($router, '/ip/hotspot/user/print', ['?name='.$username]);
                    $match = collect($found)->first(fn (array $row) => ($row['name'] ?? '') === $username);
                    $steps[] = ['label' => 'Utilisateur test', 'ok' => $match !== null, 'detail' => $match ? $username.' lu sur le routeur' : 'Introuvable après création'];
                    $active = $this->mikrotik->readPrint($router, '/ip/hotspot/active/print');
                    $session = collect($active)->first(fn (array $row) => ($row['user'] ?? '') === $username);
                    $steps[] = ['label' => 'Session', 'ok' => true, 'detail' => $session ? 'Session lue' : 'Aucune session pour ce compte de test.'];
                }
            }
        } catch (Throwable $exception) {
            $steps[] = ['label' => 'Erreur', 'ok' => false, 'detail' => $this->clean($exception->getMessage(), $router, [$password])];
        }

        if ($created) {
            $steps[] = $this->deleteTestUser($router, $username, $password);
        }

        return $this->finishVerify($router, $steps, $username);
    }

    /**
     * @param  array<string, mixed>  $details
     * @return array<string, string>
     */
    private function hotspotProposal(array $details): array
    {
        $servers = $details['hotspot_servers'] ?? [];
        if ($servers !== []) {
            $server = $servers[0];

            return [
                'state' => 'present',
                'current' => trim(implode(' · ', array_filter([
                    $server['name'] ?? null,
                    $server['interface'] ?? null,
                    $server['address-pool'] ?? null,
                ]))) ?: 'Non détecté',
                'proposed' => 'Aucune création. Le HotSpot existant est conservé.',
                'impact' => 'Aucune modification. Un HotSpot existant n’est ni supprimé ni recréé.',
                'command' => 'Aucune commande.',
            ];
        }

        $interfaces = collect($details['interfaces'] ?? [])->pluck('name')->filter()->implode(', ');
        $pools = collect($details['pools'] ?? [])->pluck('name')->filter()->implode(', ');

        return [
            'state' => 'missing',
            'current' => 'HotSpot non configuré',
            'proposed' => ($interfaces !== '' && $pools !== '') ? 'Création possible après choix d’une interface et d’un pool déjà lus.' : 'Création non proposée : interface ou pool non détecté.',
            'impact' => 'Crée un serveur HotSpot seulement après confirmation. L’adresse IP principale et la route par défaut ne sont pas modifiées.',
            'command' => '/ip hotspot add name=… interface=… address-pool=…',
        ];
    }

    private function applyDns(Mikrotik $router, string $proposed): string
    {
        if (! $this->mikrotik->isSafeDns($proposed)) {
            throw new RuntimeException('DNS refusé.');
        }
        $profiles = $this->mikrotik->readPrint($router, '/ip/hotspot/profile/print');
        $current = $profiles[0]['dns-name'] ?? null;
        $id = $profiles[0]['.id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Profil HotSpot serveur non détecté. Aucune commande envoyée.');
        }
        if ($current === $proposed) {
            return 'Le DNS Name est déjà '.$proposed.'.';
        }

        $this->snapshot($router, 'dns', [
            'dns-name' => $current,
            'profile_id' => $id,
        ], ['dns-name' => $proposed]);
        $this->mikrotik->guardedCommand($router, [
            '/ip/hotspot/profile/set',
            '=.id='.$id,
            '=dns-name='.$proposed,
        ]);
        $router->forceFill(['dns' => $proposed])->save();

        return 'DNS Name passé de '.($current ?: 'vide').' à '.$proposed.'.';
    }

    private function applyGarden(Mikrotik $router): string
    {
        $rows = $this->mikrotik->readPrint($router, '/ip/hotspot/walled-garden/print');
        $current = array_values(array_filter(array_map(fn (array $row) => $row['dst-host'] ?? null, $rows)));
        $missing = array_values(array_diff($this->mikrotik->portalHostsFor($router), $current));
        if ($missing === []) {
            return 'Walled garden déjà complet pour les hôtes proposés.';
        }
        foreach ($missing as $host) {
            if (! $this->mikrotik->isSafeDns($host)) {
                throw new RuntimeException('Hôte refusé.');
            }
        }
        $this->snapshot($router, 'walled_garden', ['hosts' => $current], ['added' => $missing]);
        foreach ($missing as $host) {
            $this->mikrotik->guardedCommand($router, ['/ip/hotspot/walled-garden/add', '=dst-host='.$host]);
        }

        return count($missing).' hôte(s) ajouté(s) au walled garden.';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    /**
     * @param  array<string, mixed>  $input
     */
    private function applyHotspot(Mikrotik $router, array $input): string
    {
        $existing = $this->mikrotik->readPrint($router, '/ip/hotspot/print');
        if ($existing !== []) {
            throw new RuntimeException('Un HotSpot existe déjà. Il n’est pas modifié.');
        }
        $name = $this->token((string) ($input['hotspot_name'] ?? ''));
        $interface = $this->token((string) ($input['interface'] ?? ''));
        $pool = $this->token((string) ($input['address_pool'] ?? ''));
        $interfaces = array_column($this->mikrotik->readPrint($router, '/interface/print'), 'name');
        $pools = array_column($this->mikrotik->readPrint($router, '/ip/pool/print'), 'name');
        if (! in_array($interface, $interfaces, true) || ! in_array($pool, $pools, true)) {
            throw new RuntimeException('Interface ou pool non détecté. Aucune commande envoyée.');
        }
        $this->snapshot($router, 'hotspot', ['existed' => false], ['name' => $name, 'interface' => $interface, 'address_pool' => $pool]);
        $this->mikrotik->guardedCommand($router, [
            '/ip/hotspot/add',
            '=name='.$name,
            '=interface='.$interface,
            '=address-pool='.$pool,
        ]);

        return 'HotSpot '.$name.' créé sur '.$interface.'.';
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function applyProfile(Mikrotik $router, array $input): string
    {
        $name = $this->token((string) ($input['profile_name'] ?? ''));
        $existing = $this->mikrotik->readPrint($router, '/ip/hotspot/user/profile/print');
        foreach ($existing as $profile) {
            if (($profile['name'] ?? '') === $name) {
                throw new RuntimeException('Un profil porte déjà ce nom. Il n’est pas écrasé.');
            }
        }
        $words = ['/ip/hotspot/user/profile/add', '=name='.$name];
        $session = $this->routerTime($input['session_timeout'] ?? null);
        $idle = $this->routerTime($input['idle_timeout'] ?? null);
        $rate = $this->rateLimit($input['rate_limit'] ?? null);
        $shared = $input['shared_users'] ?? null;
        if ($session) {
            $words[] = '=session-timeout='.$session;
        }
        if ($idle) {
            $words[] = '=idle-timeout='.$idle;
        }
        if ($rate) {
            $words[] = '=rate-limit='.$rate;
        }
        if ($shared !== null && $shared !== '') {
            $words[] = '=shared-users='.(int) $shared;
        }
        $this->snapshot($router, 'profile', ['existed' => false, 'name' => $name], ['name' => $name]);
        $this->mikrotik->guardedCommand($router, $words);

        return 'Profil '.$name.' créé.';
    }

    private function restoreDns(Mikrotik $router, MikrotikSnapshot $snapshot): string
    {
        $profiles = $this->mikrotik->readPrint($router, '/ip/hotspot/profile/print');
        $id = $profiles[0]['.id'] ?? ($snapshot->before['profile_id'] ?? null);
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Restauration impossible : profil HotSpot introuvable.');
        }
        $previous = (string) ($snapshot->before['dns-name'] ?? '');
        if ($previous !== '' && ! $this->mikrotik->isSafeDns($previous)) {
            throw new RuntimeException('Restauration impossible.');
        }
        $this->mikrotik->guardedCommand($router, [
            '/ip/hotspot/profile/set',
            '=.id='.$id,
            '=dns-name='.$previous,
        ]);

        return 'DNS Name restauré.';
    }

    private function restoreGarden(Mikrotik $router, MikrotikSnapshot $snapshot): string
    {
        $before = $snapshot->before['hosts'] ?? [];
        $added = $snapshot->applied['added'] ?? [];
        $rows = $this->mikrotik->readPrint($router, '/ip/hotspot/walled-garden/print');
        $removed = 0;
        foreach ($rows as $row) {
            $host = $row['dst-host'] ?? '';
            $id = $row['.id'] ?? '';
            if ($host !== '' && in_array($host, $added, true) && ! in_array($host, $before, true) && $id !== '') {
                $this->mikrotik->guardedCommand($router, ['/ip/hotspot/walled-garden/remove', '=.id='.$id], [], ['rollback' => true]);
                $removed++;
            }
        }

        return $removed.' règle(s) ajoutée(s) par LIMETE retirée(s).';
    }

    private function restoreProfile(Mikrotik $router, MikrotikSnapshot $snapshot): string
    {
        if (($snapshot->before['existed'] ?? true) !== false) {
            throw new RuntimeException('Restauration non disponible.');
        }
        $name = (string) ($snapshot->before['name'] ?? '');
        $rows = $this->mikrotik->readPrint($router, '/ip/hotspot/user/profile/print');
        $match = collect($rows)->first(fn (array $row) => ($row['name'] ?? '') === $name);
        $id = $match['.id'] ?? null;
        if (! is_string($id) || $id === '') {
            throw new RuntimeException('Le profil créé n’est plus lisible.');
        }
        $this->mikrotik->guardedCommand($router, ['/ip/hotspot/user/profile/remove', '=.id='.$id], [], ['rollback' => true]);

        return 'Profil '.$name.' retiré.';
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $applied
     */
    private function snapshot(Mikrotik $router, string $group, array $before, array $applied): MikrotikSnapshot
    {
        return MikrotikSnapshot::create([
            'mikrotik_id' => $router->id,
            'group' => $group,
            'before' => $before,
            'applied' => $applied,
        ]);
    }

    /**
     * @return array{label: string, ok: bool, detail: string}
     */
    private function deleteTestUser(Mikrotik $router, string $username, string $password): array
    {
        try {
            $rows = $this->mikrotik->readPrint($router, '/ip/hotspot/user/print', ['?name='.$username]);
            $match = collect($rows)->first(fn (array $row) => ($row['name'] ?? '') === $username);
            $id = $match['.id'] ?? null;
            if (! is_string($id) || $id === '') {
                return ['label' => 'Suppression utilisateur test', 'ok' => false, 'detail' => 'Compte de test introuvable, suppression non envoyée.'];
            }
            $this->mikrotik->guardedCommand($router, ['/ip/hotspot/user/remove', '=.id='.$id], [$password], ['username' => $username]);

            return ['label' => 'Suppression utilisateur test', 'ok' => true, 'detail' => $username.' supprimé'];
        } catch (Throwable $exception) {
            return ['label' => 'Suppression utilisateur test', 'ok' => false, 'detail' => $this->clean($exception->getMessage(), $router, [$password])];
        }
    }

    /**
     * @param  array<int, array{label: string, ok: bool, detail: string}>  $steps
     * @return array<int, array{label: string, ok: bool, detail: string}>
     */
    private function finishVerify(Mikrotik $router, array $steps, string $username): array
    {
        $this->audit->record('mikrotik.configuration_verified', $router, null, [
            'username' => $username,
            'result' => collect($steps)->contains(fn (array $step) => $step['ok'] === false) ? 'incomplet' : 'terminé',
            'change' => 'compte de test temporaire',
            'error' => collect($steps)->first(fn (array $step) => $step['ok'] === false)['detail'] ?? null,
        ]);

        return $steps;
    }

    private function fileHasEmbeddedSecret(string $path): bool
    {
        $content = (string) file_get_contents($path);

        return (bool) preg_match('/(api[_-]?password|client_secret|BEGIN PRIVATE KEY)\s*[:=]/i', $content);
    }

    /**
     * @param  array<int, string>  $extra
     */
    private function clean(string $message, Mikrotik $router, array $extra = []): string
    {
        foreach (array_merge([$router->password], $extra) as $secret) {
            if (is_string($secret) && $secret !== '') {
                $message = str_replace($secret, '[masqué]', $message);
            }
        }

        return (string) preg_replace('/=password=[^\s,]*/', '=password=[masqué]', $message);
    }

    private function token(string $value): string
    {
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/', $value)) {
            throw new RuntimeException('Valeur refusée.');
        }

        return $value;
    }

    private function routerTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        if (! preg_match('/^(\d+[wdhms])+$/', $value)) {
            throw new RuntimeException('Durée refusée.');
        }

        return $value;
    }

    private function rateLimit(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $value = (string) $value;
        if (! preg_match('/^(\d+[kKmMgG]?)(\/\d+[kKmMgG]?)?$/', $value)) {
            throw new RuntimeException('Débit refusé.');
        }

        return $value;
    }
}

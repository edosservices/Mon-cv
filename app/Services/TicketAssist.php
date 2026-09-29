<?php

namespace App\Services;

use App\Models\Mikrotik;
use App\Models\MikrotikProfile;
use App\Models\Plan;
use App\Models\PlanMikrotikProfile;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\Mikrotik\MikrotikPreparation;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Mikrotik\RouterOsProtocol;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class TicketAssist
{
    public const DATA = [0, 2, 5, 10, 20];

    private const ATTEMPTS = 12;

    /** @var array<int, string> */
    private const DURATIONS = [
        900 => '15 minutes',
        3600 => '1 heure',
        86400 => '1 jour',
        172800 => '2 jours',
        259200 => '3 jours',
        604800 => '7 jours',
        1296000 => '15 jours',
        2592000 => '30 jours',
    ];

    /** @var array<int, string> */
    private const PROFILES = [
        900 => '15MIN',
        3600 => '1HEURE',
        86400 => '1JOUR',
        172800 => '2JOURS',
        259200 => '3JOURS',
        604800 => '7JOURS',
        1296000 => '15JOURS',
        2592000 => '30JOURS',
    ];

    public function __construct(
        private MikrotikService $mikrotik,
        private MikrotikPreparation $preparation,
        private VoucherGenerator $vouchers,
        private AuditLogger $audit,
    ) {}

    /**
     * @return list<array{plan_id: int, seconds: int, label: string, plan_name: string}>
     */
    public function durations(WifiZone $zone): array
    {
        return Plan::query()
            ->where('status', 'active')
            ->where(function ($query) use ($zone) {
                $query->whereNull('wifi_zone_id')->orWhere('wifi_zone_id', $zone->id);
            })
            ->orderBy('duration_seconds')
            ->get()
            ->filter(fn (Plan $plan) => isset(self::DURATIONS[(int) $plan->duration_seconds]))
            ->unique(fn (Plan $plan) => (int) $plan->duration_seconds)
            ->map(fn (Plan $plan) => [
                'plan_id' => $plan->id,
                'seconds' => (int) $plan->duration_seconds,
                'label' => self::DURATIONS[(int) $plan->duration_seconds],
                'plan_name' => $plan->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{profile: string, time_limit: string, duration_label: string, data_label: string, data_router: string, data_bytes: ?int, rate_limit: string, rate_short: string, mbps: int}
     */
    public function parameters(Plan $plan, int $mbps, int $gigabytes): array
    {
        $seconds = (int) $plan->duration_seconds;
        if (! isset(self::DURATIONS[$seconds]) || $mbps < 1 || $mbps > 1000 || ! in_array($gigabytes, self::DATA, true)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }

        $profile = filled($plan->mikrotik_profile)
            ? (string) $plan->mikrotik_profile
            : self::PROFILES[$seconds];

        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/', $profile)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }

        return [
            'profile' => $profile,
            'time_limit' => RouterOsProtocol::secondsToRouterTime($seconds),
            'duration_label' => self::DURATIONS[$seconds],
            'data_label' => $gigabytes === 0 ? 'Sans limite' : $gigabytes.' GB',
            'data_router' => $gigabytes === 0 ? 'Sans limite' : $gigabytes.'G',
            'data_bytes' => $gigabytes === 0 ? null : $gigabytes * 1073741824,
            'rate_limit' => $mbps.'M/'.$mbps.'M',
            'rate_short' => $mbps.'M',
            'mbps' => $mbps,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function inspect(WifiZone $zone, Plan $plan, int $mbps, int $gigabytes, ?string $username, ?string $password, bool $regenerate, ?string $alternate = null): array
    {
        $this->assertZonePlan($zone, $plan);
        $parameters = $this->parameters($plan, $mbps, $gigabytes);
        if (filled($alternate)) {
            if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/', $alternate)) {
                throw new RuntimeException('Paramètres incompatibles.');
            }
            $parameters['profile'] = $alternate;
        }

        $reading = $this->readRouter($zone);
        $identity = $this->identity($zone, $reading['passwords'], $reading['names'], $username, $password, $regenerate);
        $profile = $this->matchProfile($reading['profiles'], $parameters);

        return [
            'parameters' => $parameters,
            'router' => $reading['router'],
            'offline' => $reading['offline'],
            'notice' => $reading['notice'],
            'users' => $reading['users'],
            'identity' => $identity,
            'profile' => $profile,
        ];
    }

    /**
     * @param  array{username: string, password: string}  $identity
     */
    public function confirm(WifiZone $zone, Plan $plan, array $parameters, array $identity, string $choice, string $draft, ?string $alternate = null): Voucher
    {
        $this->assertZonePlan($zone, $plan);
        $draft = trim($draft);
        if (! preg_match('/^[A-Za-z0-9-]{16,80}$/', $draft)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }

        $key = 'ticket-assist.'.$zone->tenant_id.'.'.$draft;
        $lock = Cache::lock($key.'.lock', 15);
        $lock->block(5);

        try {
            $existing = Cache::get($key);
            if (is_numeric($existing)) {
                $voucher = Voucher::query()->find((int) $existing);
                if ($voucher) {
                    return $voucher;
                }
            }

            if ($choice === 'cancel') {
                throw new RuntimeException('Création annulée.');
            }

            $preview = $this->inspect($zone, $plan, (int) $parameters['mbps'], $this->gigabytes($parameters), $identity['username'], $identity['password'], false, filled($alternate) ? $alternate : null);
            if ($preview['identity']['collision']) {
                throw new RuntimeException($preview['identity']['message']);
            }

            $profileName = $preview['parameters']['profile'];
            $state = $preview['profile']['state'];
            if ($preview['offline']) {
                $state = 'offline';
            } elseif ($state === 'different' && ! in_array($choice, ['reuse', 'rename'], true)) {
                throw new RuntimeException('Un profil portant ce nom existe déjà avec des paramètres différents.');
            } elseif ($state === 'missing' && $choice === 'reuse') {
                throw new RuntimeException('Profil introuvable.');
            } elseif ($state === 'same' || $state === 'different') {
                $choice = $choice === 'rename' ? 'rename' : 'reuse';
            }

            $router = $preview['router'];
            if ($router && ! $preview['offline'] && $state === 'missing' && $choice !== 'reuse') {
                $this->preparation->apply($router, 'profile', [
                    'profile_name' => $profileName,
                    'session_timeout' => $preview['parameters']['time_limit'],
                    'rate_limit' => $preview['parameters']['rate_limit'],
                    'shared_users' => 1,
                ]);
                $this->mikrotik->getHotspotProfiles($router);
            }

            if (! filled($plan->mikrotik_profile)) {
                $plan->forceFill(['mikrotik_profile' => $profileName])->save();
            }

            if ($router && ! $preview['offline']) {
                $this->mikrotik->getHotspotProfiles($router);
                $stored = MikrotikProfile::query()->where('mikrotik_id', $router->id)->where('name', $profileName)->first();
                $linked = PlanMikrotikProfile::query()
                    ->where('plan_id', $plan->id)
                    ->where('mikrotik_id', $router->id)
                    ->exists();
                if ($stored && ! $linked) {
                    PlanMikrotikProfile::create([
                        'plan_id' => $plan->id,
                        'mikrotik_id' => $router->id,
                        'mikrotik_profile_id' => $stored->id,
                    ]);
                }
            }

            $voucher = $this->vouchers->issue($zone, $plan, $preview['identity']['username'], $preview['identity']['password']);
            $voucher = $this->mikrotik->provisionVoucher($voucher, $profileName);

            if ($router && $voucher->sync_status === 'synced' && $preview['parameters']['data_bytes']) {
                try {
                    $this->mikrotik->updateHotspotUser($router, $voucher->username, [
                        'limit-bytes-total' => (string) $preview['parameters']['data_bytes'],
                    ]);
                } catch (Throwable $exception) {
                    $voucher->forceFill([
                        'sync_status' => 'failed',
                        'sync_error' => $this->readable($exception),
                    ])->save();
                    $voucher = $voucher->refresh();
                }
            }

            Cache::put($key, $voucher->id, now()->addHour());
            Cache::put('ticket-assist.card.'.$voucher->id, [
                'profile' => $profileName,
                'duration' => $preview['parameters']['duration_label'],
                'data' => $preview['parameters']['data_label'],
                'rate' => $preview['parameters']['mbps'].' Mbps',
                'time_limit' => $preview['parameters']['time_limit'],
            ], now()->addDay());
            $this->audit->record('voucher.assisted', $voucher, null, [
                'username' => $voucher->username,
                'profile' => $profileName,
                'duration' => $preview['parameters']['duration_label'],
                'sync_status' => $voucher->sync_status,
            ]);

            return $voucher->refresh();
        } finally {
            $lock->release();
        }
    }

    public function durationLabel(Plan $plan): string
    {
        $seconds = (int) $plan->duration_seconds;

        return self::DURATIONS[$seconds] ?? $plan->durationLabel();
    }

    public function readable(Throwable $exception): string
    {
        $message = $exception->getMessage();
        $lower = strtolower($message);
        if (str_contains($lower, 'un profil porte déjà ce nom')) {
            return 'Un profil portant ce nom existe déjà avec des paramètres différents.';
        }
        if (str_contains($lower, 'valeur refusée') || str_contains($lower, 'débit refusé') || str_contains($lower, 'durée refusée')) {
            return 'Paramètres incompatibles.';
        }
        if (str_contains($lower, 'profil') && str_contains($lower, 'introuvable')) {
            return 'Profil introuvable.';
        }
        foreach (['password', 'secret', 'token', 'api_key'] as $secret) {
            if (str_contains($lower, $secret) && ! str_contains($lower, 'mot de passe est déjà')) {
                return 'Erreur RouterOS';
            }
        }

        foreach ([
            'Cet utilisateur existe déjà.',
            'Ce mot de passe est déjà utilisé.',
            'Un profil portant ce nom existe déjà avec des paramètres différents.',
            'Profil introuvable.',
            'Paramètres incompatibles.',
            'Routeur hors ligne.',
            'Permission refusée.',
            'Création annulée.',
            'Impossible de proposer un identifiant libre.',
            'Ce profil n’a pas de forfait LIMETE.',
            'Aperçu expiré.',
        ] as $known) {
            if (str_contains($message, $known)) {
                return $known;
            }
        }

        if (str_contains($lower, 'timed out') || str_contains($lower, 'connection refused') || str_contains($lower, 'hors ligne') || str_contains($lower, 'connexion impossible')) {
            return 'Routeur hors ligne.';
        }

        if (str_contains($lower, 'refus') || str_contains($lower, 'permission')) {
            return 'Permission refusée.';
        }

        return 'Erreur RouterOS';
    }

    private function assertZonePlan(WifiZone $zone, Plan $plan): void
    {
        if ((int) $plan->tenant_id !== (int) $zone->tenant_id) {
            throw new RuntimeException('Permission refusée.');
        }
        if ($plan->wifi_zone_id && (int) $plan->wifi_zone_id !== (int) $zone->id) {
            throw new RuntimeException('Paramètres incompatibles.');
        }
        if ($plan->status !== 'active') {
            throw new RuntimeException('Paramètres incompatibles.');
        }
    }

    /**
     * @param  array{data_bytes: ?int}  $parameters
     */
    private function gigabytes(array $parameters): int
    {
        if ($parameters['data_bytes'] === null) {
            return 0;
        }

        return (int) round($parameters['data_bytes'] / 1073741824);
    }

    /**
     * @return array{router: ?Mikrotik, offline: bool, notice: ?string, profiles: array<int, array<string, string>>, users: array<int, array<string, string>>, names: array<string, bool>, passwords: array<string, bool>}
     */
    private function readRouter(WifiZone $zone): array
    {
        $router = $zone->mikrotiks()->where('is_active', true)->orderBy('id')->first();
        $empty = [
            'router' => $router,
            'offline' => $router === null,
            'notice' => $router === null ? 'Routeur hors ligne.' : null,
            'profiles' => [],
            'users' => [],
            'names' => [],
            'passwords' => [],
        ];
        if (! $router) {
            return $empty;
        }

        try {
            $profiles = $this->mikrotik->readPrint($router, '/ip/hotspot/user/profile/print');
            $accounts = $this->mikrotik->readPrint($router, '/ip/hotspot/user/print');
        } catch (Throwable $exception) {
            $empty['offline'] = true;
            $empty['notice'] = $this->readable($exception);

            return $empty;
        }

        $names = [];
        $passwords = [];
        $users = [];
        foreach ($accounts as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $names[$name] = true;
            }
            if (filled($row['password'] ?? null)) {
                $passwords[(string) $row['password']] = true;
            }
            if ($name === '') {
                continue;
            }
            $users[] = [
                'name' => $name,
                'profile' => (string) ($row['profile'] ?? '—'),
                'status' => (($row['disabled'] ?? 'false') === 'true') ? 'Désactivé' : 'Actif',
                'activity' => (string) (($row['last-logged-out'] ?? '') !== '' ? $row['last-logged-out'] : (($row['uptime'] ?? '') !== '' ? $row['uptime'] : '—')),
            ];
        }

        return [
            'router' => $router,
            'offline' => false,
            'notice' => null,
            'profiles' => $profiles,
            'users' => array_slice($users, 0, 100),
            'names' => $names,
            'passwords' => $passwords,
        ];
    }

    /**
     * @param  array<string, bool>  $routerPasswords
     * @param  array<string, bool>  $routerNames
     * @return array{username: string, password: string, collision: bool, message: ?string}
     */
    private function identity(WifiZone $zone, array $routerPasswords, array $routerNames, ?string $username, ?string $password, bool $regenerate): array
    {
        $names = $routerNames;
        $passwords = $routerPasswords;
        Voucher::withoutGlobalScope('tenant')->withTrashed()
            ->where('tenant_id', $zone->tenant_id)
            ->orderBy('id')
            ->each(function (Voucher $voucher) use (&$names, &$passwords) {
                $names[$voucher->username] = true;
                $plain = $voucher->password;
                if (is_string($plain) && $plain !== '') {
                    $passwords[$plain] = true;
                }
            });

        $username = trim((string) $username);
        $password = trim((string) $password);

        if ($regenerate || ($username === '' && $password === '')) {
            [$username, $password] = $this->generate($names, $passwords);

            return ['username' => $username, 'password' => $password, 'collision' => false, 'message' => null];
        }

        if ($username !== '' && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,31}$/', $username)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }
        if ($password !== '' && ! preg_match('/^\d{3,8}$/', $password)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }
        if ($username !== '' && isset($names[$username])) {
            return ['username' => $username, 'password' => $password, 'collision' => true, 'message' => 'Cet utilisateur existe déjà.'];
        }
        if ($password !== '' && isset($passwords[$password])) {
            return ['username' => $username, 'password' => $password, 'collision' => true, 'message' => 'Ce mot de passe est déjà utilisé.'];
        }
        if ($username === '' || $password === '') {
            [$generatedUser, $generatedPassword] = $this->generate($names, $passwords);
            $username = $username !== '' ? $username : $generatedUser;
            $password = $password !== '' ? $password : $generatedPassword;
            if (isset($names[$username]) || isset($passwords[$password])) {
                [$username, $password] = $this->generate($names, $passwords);
            }
        }

        return ['username' => $username, 'password' => $password, 'collision' => false, 'message' => null];
    }

    /**
     * @param  array<string, bool>  $names
     * @param  array<string, bool>  $passwords
     * @return array{0: string, 1: string}
     */
    private function generate(array $names, array $passwords): array
    {
        $letters = 'abcdefghjkmnpqrstuvwxyz';
        $max = strlen($letters) - 1;
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $username = '';
            for ($i = 0; $i < 3; $i++) {
                $username .= $letters[random_int(0, $max)];
            }
            $password = (string) random_int($attempt < 8 ? 100 : 1000, $attempt < 8 ? 999 : 9999);
            if (! isset($names[$username]) && ! isset($passwords[$password]) && $username !== $password) {
                return [$username, $password];
            }
        }

        throw new RuntimeException('Impossible de proposer un identifiant libre.');
    }

    /**
     * @param  array<int, array<string, string>>  $profiles
     * @param  array{profile: string, time_limit: string, rate_limit: string, rate_short: string, data_router: string}  $parameters
     * @return array{state: string, name: string, time_limit: string, data_limit: string, rate_limit: string, message: string}
     */
    private function matchProfile(array $profiles, array $parameters): array
    {
        $found = null;
        foreach ($profiles as $profile) {
            if (($profile['name'] ?? '') === $parameters['profile']) {
                $found = $profile;
                break;
            }
        }

        if ($found === null) {
            return [
                'state' => 'missing',
                'name' => $parameters['profile'],
                'time_limit' => $parameters['time_limit'],
                'data_limit' => $parameters['data_router'],
                'rate_limit' => $parameters['rate_short'],
                'message' => 'Profil introuvable. Il peut être créé.',
            ];
        }

        $time = (string) ($found['session-timeout'] ?? '');
        $rate = (string) ($found['rate-limit'] ?? '');
        $same = $this->sameTime($time, $parameters['time_limit']) && $this->sameRate($rate, $parameters['rate_limit']);

        return [
            'state' => $same ? 'same' : 'different',
            'name' => $parameters['profile'],
            'time_limit' => $same ? $parameters['time_limit'] : ($time !== '' ? $time : '—'),
            'data_limit' => $parameters['data_router'],
            'rate_limit' => $same ? $parameters['rate_short'] : ($rate !== '' ? $rate : '—'),
            'message' => $same
                ? 'Profil existant trouvé'
                : 'Un profil portant ce nom existe déjà avec des paramètres différents.',
        ];
    }

    private function sameTime(string $existing, string $wanted): bool
    {
        if ($existing === '') {
            return false;
        }

        return $this->routerSeconds($existing) === $this->routerSeconds($wanted);
    }

    private function sameRate(string $existing, string $wanted): bool
    {
        if ($existing === '') {
            return false;
        }
        $left = explode('/', strtoupper($existing));
        $right = explode('/', strtoupper($wanted));
        $left = array_values(array_filter($left, fn ($part) => $part !== ''));
        $right = array_values(array_filter($right, fn ($part) => $part !== ''));
        if ($left === [] || $right === []) {
            return false;
        }
        if (count($left) === 1) {
            $left = [$left[0], $left[0]];
        }
        if (count($right) === 1) {
            $right = [$right[0], $right[0]];
        }

        return $left[0] === $right[0] && $left[1] === $right[1];
    }

    private function routerSeconds(string $value): ?int
    {
        return RouterOsProtocol::routerTimeToSeconds($value);
    }
}

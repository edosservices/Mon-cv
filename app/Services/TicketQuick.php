<?php

namespace App\Services;

use App\Models\Mikrotik;
use App\Models\MikrotikProfile;
use App\Models\Plan;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Mikrotik\RouterOsProtocol;
use App\Support\Money;
use Illuminate\Support\Facades\Cache;
use RuntimeException;
use Throwable;

class TicketQuick
{
    private const ATTEMPTS = 12;

    public const MISSING_PLAN = 'Ce profil MikroTik existe, mais aucun forfait LIMETE ne lui est encore associé.';

    /** @var array<string, mixed>|null */
    private ?array $reading = null;

    private ?int $readingZone = null;

    /** @var array<string, bool>|null */
    private ?array $localPasswords = null;

    public function __construct(
        private MikrotikService $mikrotik,
        private VoucherGenerator $vouchers,
        private TicketAssist $assist,
        private AuditLogger $audit,
    ) {}

    /**
     * @return array{profiles: list<array<string, mixed>>, servers: list<string>, offline: bool, notice: ?string, last: ?string}
     */
    public function catalog(WifiZone $zone): array
    {
        $reading = $this->read($zone);
        $profiles = $this->merge($zone, $reading['profiles'], $reading['live']);
        foreach ($profiles as $index => $profile) {
            $profiles[$index]['plans'] = $profile['ready'] ? [] : $this->compatiblePlans($zone, $profile);
        }

        return [
            'profiles' => $profiles,
            'servers' => $reading['servers'],
            'offline' => $reading['offline'],
            'notice' => $reading['notice'],
            'last' => $this->lastName($zone, $profiles),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $profiles
     * @return list<array<string, mixed>>
     */
    public function search(array $profiles, string $query): array
    {
        $query = mb_strtolower(trim($query));
        if ($query === '') {
            return $profiles;
        }

        return array_values(array_filter($profiles, fn (array $profile) => str_contains(mb_strtolower($profile['name']), $query)));
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function preview(WifiZone $zone, array $input): array
    {
        $built = $this->assemble($zone, $input, true);
        if (! empty($built['needs_plan'])) {
            return $built;
        }
        Cache::put($this->draftKey($zone, $built['draft']), [
            'hash' => $built['hash'],
            'identities' => $built['identities'],
        ], now()->addMinutes(20));

        return $built;
    }

    /**
     * Aperçu puis enregistrement, pour un choix de profil et une quantité.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function commit(WifiZone $zone, array $input): array
    {
        $built = $this->preview($zone, $input);
        if (! empty($built['needs_plan'])) {
            return $built;
        }

        return [
            'needs_plan' => false,
            'vouchers' => $this->confirm($zone, $input),
            'snapshot' => $built['snapshot'],
            'offline' => $built['offline'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<Voucher>
     */
    public function confirm(WifiZone $zone, array $input): array
    {
        $draft = $this->draft($input['draft'] ?? null);
        $key = 'ticket-quick.result.'.$zone->tenant_id.'.'.$draft;
        $lock = Cache::lock($key.'.lock', 90);
        $lock->block(8);

        try {
            $cached = Cache::get($this->draftKey($zone, $draft));
            $built = $this->assemble($zone, $input, false);
            if (! is_array($cached) || ($cached['hash'] ?? '') !== $built['hash'] || ! is_array($cached['identities'] ?? null)) {
                throw new RuntimeException('Aperçu expiré.');
            }

            $existing = Cache::get($key);
            if (is_array($existing) && $existing !== []) {
                $vouchers = Voucher::query()->whereIn('id', $existing)->orderBy('id')->get()->all();
                if (count($vouchers) === count($existing)) {
                    return $vouchers;
                }
            }

            foreach ($cached['identities'] as $identity) {
                if ($this->nameTaken($zone, (string) $identity['username']) || $this->passwordTaken($zone, (string) $identity['password'])) {
                    throw new RuntimeException('Cet utilisateur existe déjà.');
                }
            }
            $built['identities'] = $cached['identities'];

            $plan = Plan::query()->findOrFail($built['profile']['plan_id']);
            $created = $this->vouchers->issueMany($zone, $plan, $built['identities'], $built['snapshot']);
            Cache::put($key, array_map(fn (Voucher $voucher) => $voucher->id, $created), now()->addHour());
            Cache::put($this->lastKey($zone), $built['profile']['name'], now()->addDays(30));

            $profileName = $built['profile']['name'];
            $server = $built['server'];
            $router = $built['router'];
            if ($router && ! $built['offline']) {
                try {
                    $this->mikrotik->getHotspotProfiles($router);
                } catch (Throwable) {
                    // La création locale reste valable. La synchronisation indiquera l'échec.
                }
            }

            foreach ($created as $index => $voucher) {
                $voucher = $this->mikrotik->provisionVoucher($voucher, $profileName, $server === 'all' ? null : $server);
                if ($router && $voucher->sync_status === 'synced' && $built['snapshot']['data_bytes']) {
                    try {
                        $this->mikrotik->updateHotspotUser($router, $voucher->username, [
                            'limit-bytes-total' => (string) $built['snapshot']['data_bytes'],
                        ]);
                    } catch (Throwable $exception) {
                        $voucher->forceFill([
                            'sync_status' => 'failed',
                            'sync_error' => $this->assist->readable($exception),
                        ])->save();
                        $voucher = $voucher->refresh();
                    }
                }
                $created[$index] = $voucher->refresh();
                $this->audit->record('voucher.quick', $voucher, null, [
                    'username' => $voucher->username,
                    'profile' => $profileName,
                    'sync_status' => $voucher->sync_status,
                ]);
            }

            return $created;
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array{taken: bool, message: ?string, suggestions: list<string>, prefixes: list<string>}
     */
    public function usernameHint(WifiZone $zone, string $typed): array
    {
        $typed = trim($typed);
        $prefixes = $this->recentPrefixes($zone);
        $taken = $typed !== '' && $this->nameTaken($zone, $typed);
        $suggestions = [];
        if ($taken || $typed === '') {
            $seed = $taken ? (preg_replace('/\d+$/', '', $typed) ?: ($prefixes[0] ?? 'LM')) : ($prefixes[0] ?? 'LM');
            $suggestions = $this->freeNames($zone, (string) $seed, 4, 'mixed', 3);
        }

        return [
            'taken' => $taken,
            'message' => $taken ? 'Nom déjà utilisé' : null,
            'suggestions' => $suggestions,
            'prefixes' => $prefixes,
        ];
    }

    public function dataBytes(int $value, string $unit): int
    {
        if ($value < 1 || $value > 100000 || ! in_array($unit, ['MB', 'GB'], true)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }

        $bytes = $unit === 'MB' ? $value * 1048576 : $value * 1073741824;
        if ($bytes < 1) {
            throw new RuntimeException('Paramètres incompatibles.');
        }

        return $bytes;
    }

    /**
     * @param  array<string, mixed>  $commercial
     */
    public function storeLinkedPlan(WifiZone $zone, string $name, array $commercial): Plan
    {
        $profile = $this->technicalProfile($zone, $name);
        if ($profile['ready']) {
            throw new RuntimeException('Ce profil a déjà un forfait LIMETE.');
        }

        $snapshot = $profile['snapshot'];
        $validity = (string) $commercial['validity'];
        $timeLimit = filled($commercial['time_limit'] ?? null) ? (string) $commercial['time_limit'] : $validity;
        $seconds = RouterOsProtocol::routerTimeToSeconds($validity);
        if ($seconds === null) {
            throw new RuntimeException('Paramètres incompatibles.');
        }

        $currency = strtoupper((string) $commercial['price_currency']);
        $sellingCurrency = filled($commercial['selling_price_currency'] ?? null)
            ? strtoupper((string) $commercial['selling_price_currency'])
            : $currency;
        $price = $commercial['price_amount'];
        $selling = ($commercial['selling_price_amount'] ?? '') === '' ? $price : $commercial['selling_price_amount'];

        $plan = Plan::create([
            'wifi_zone_id' => $zone->id,
            'name' => $profile['name'],
            'duration_seconds' => $seconds,
            'price' => $price,
            'currency' => $currency,
            'selling_price' => $selling,
            'selling_currency' => $sellingCurrency,
            'mikrotik_profile' => $profile['name'],
            'unlimited_data' => true,
            'status' => 'active',
            'hotspot' => [
                'address_pool' => $snapshot['address_pool'],
                'shared_users' => $snapshot['shared_users'],
                'rate_limit' => $snapshot['rate_limit'],
                'expired_mode' => $snapshot['expired_mode'],
                'lock_user' => $snapshot['lock_user'],
                'parent_queue' => $snapshot['parent_queue'],
                'validity' => $validity,
                'time_limit' => $timeLimit,
            ],
        ]);
        $this->forgetReading();

        return $plan;
    }

    public function attachPlan(WifiZone $zone, string $name, Plan $plan): Plan
    {
        $profile = $this->technicalProfile($zone, $name);
        if ($profile['ready']) {
            throw new RuntimeException('Ce profil a déjà un forfait LIMETE.');
        }
        if ((int) $plan->tenant_id !== (int) $zone->tenant_id || ! $this->planMatchesProfile($zone, $plan, $profile)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }

        $plan->forceFill([
            'mikrotik_profile' => $profile['name'],
        ])->save();
        $this->forgetReading();

        return $plan->fresh();
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    private function assemble(WifiZone $zone, array $input, bool $withIdentities): array
    {
        $this->draft($input['draft'] ?? null);
        $catalog = $this->catalog($zone);
        $name = trim((string) ($input['profile'] ?? ''));
        $profile = null;
        foreach ($catalog['profiles'] as $candidate) {
            if ($candidate['name'] === $name) {
                $profile = $candidate;
                break;
            }
        }
        if ($profile === null) {
            throw new RuntimeException('Profil introuvable.');
        }
        if (! $catalog['offline'] && ! $profile['on_router']) {
            throw new RuntimeException('Profil introuvable.');
        }
        if (! $profile['ready']) {
            if (! $withIdentities) {
                throw new RuntimeException(self::MISSING_PLAN);
            }

            return [
                'needs_plan' => true,
                'draft' => $this->draft($input['draft'] ?? null),
                'hash' => '',
                'profile' => $profile,
                'plans' => $profile['plans'] ?? [],
                'snapshot' => $profile['snapshot'],
                'server' => '',
                'identities' => [],
                'samples' => [],
                'offline' => $catalog['offline'],
                'notice' => self::MISSING_PLAN,
                'router' => $this->router($zone),
                'mode' => 'generate',
                'qty' => 0,
            ];
        }

        $server = trim((string) ($input['server'] ?? ''));
        if ($server === '') {
            $server = 'all';
        }
        if ($server !== 'all' && ! in_array($server, $catalog['servers'], true)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }

        $unlimited = (bool) ($input['data_unlimited'] ?? false);
        $bytes = $unlimited ? 0 : $this->dataBytes((int) $input['data_value'], (string) $input['data_unit']);
        $mode = ($input['mode'] ?? 'generate') === 'add' ? 'add' : 'generate';
        $snapshot = $profile['snapshot'];
        $snapshot['server'] = $server;
        $snapshot['data_bytes'] = $unlimited ? null : $bytes;
        $snapshot['data_label'] = $unlimited ? null : ((int) $input['data_value']).' '.$input['data_unit'];
        $snapshot['mode'] = $mode;

        $identities = [];
        if ($withIdentities) {
            $identities = $mode === 'add'
                ? [$this->singleIdentity($zone, $input)]
                : $this->batchIdentities($zone, $input);
        }

        $draft = $this->draft($input['draft'] ?? null);
        $hash = sha1(json_encode([
            $name,
            $server,
            $bytes,
            $mode,
            $mode === 'add'
                ? [trim((string) ($input['username'] ?? '')), trim((string) ($input['password'] ?? ''))]
                : [(int) ($input['qty'] ?? 0), trim((string) ($input['prefix'] ?? '')), (int) ($input['length'] ?? 4), (string) ($input['charset'] ?? 'mixed')],
        ]));

        return [
            'draft' => $draft,
            'hash' => $hash,
            'profile' => $profile,
            'snapshot' => $snapshot,
            'server' => $server,
            'identities' => $identities,
            'samples' => array_slice(array_column($identities, 'username'), 0, 3),
            'offline' => $catalog['offline'],
            'notice' => $catalog['notice'],
            'router' => $this->router($zone),
            'mode' => $mode,
            'qty' => count($identities),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{username: string, password: string}
     */
    private function singleIdentity(WifiZone $zone, array $input): array
    {
        $username = trim((string) ($input['username'] ?? ''));
        $password = trim((string) ($input['password'] ?? ''));
        if ($username === '' || $password === '') {
            $pair = $this->freeNames($zone, $username !== '' ? $username : 'LM', 4, 'digits', 1);
            $username = $username !== '' ? $username : ($pair[0] ?? '');
            if ($password === '') {
                $password = $this->freePassword($zone);
            }
        }
        if (! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,31}$/', $username) || ! preg_match('/^\d{3,8}$/', $password)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }
        if ($this->nameTaken($zone, $username)) {
            throw new RuntimeException('Cet utilisateur existe déjà.');
        }
        if ($this->passwordTaken($zone, $password)) {
            throw new RuntimeException('Ce mot de passe est déjà utilisé.');
        }

        return ['username' => $username, 'password' => $password];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array{username: string, password: string}>
     */
    private function batchIdentities(WifiZone $zone, array $input): array
    {
        $qty = (int) ($input['qty'] ?? 0);
        $length = (int) ($input['length'] ?? 4);
        $prefix = trim((string) ($input['prefix'] ?? ''));
        $charset = (string) ($input['charset'] ?? 'mixed');
        if ($qty < 1 || $qty > 100 || $length < 3 || $length > 8 || strlen($prefix) + $length > 32) {
            throw new RuntimeException('Paramètres incompatibles.');
        }
        if ($prefix !== '' && ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,11}$/', $prefix)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }
        if (! in_array($charset, ['mixed', 'digits', 'lower', 'upper'], true)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }

        app(PlanLimiter::class)->assertVoucherBatch($qty);
        $names = $this->freeNames($zone, $prefix, $length, $charset, $qty);
        if (count($names) !== $qty) {
            throw new RuntimeException('Impossible de proposer un identifiant libre.');
        }

        $rows = [];
        $passwords = [];
        foreach ($names as $name) {
            $password = $this->freePassword($zone, $passwords);
            $passwords[$password] = true;
            $rows[] = ['username' => $name, 'password' => $password];
        }

        return $rows;
    }

    /**
     * @param  array<string, bool>  $reserved
     */
    private function freePassword(WifiZone $zone, array $reserved = []): string
    {
        for ($attempt = 0; $attempt < self::ATTEMPTS; $attempt++) {
            $password = (string) random_int($attempt < 8 ? 100 : 1000, $attempt < 8 ? 999 : 9999);
            if (! isset($reserved[$password]) && ! $this->passwordTaken($zone, $password)) {
                return $password;
            }
        }

        throw new RuntimeException('Impossible de proposer un identifiant libre.');
    }

    /**
     * @return list<string>
     */
    private function freeNames(WifiZone $zone, string $prefix, int $length, string $charset, int $count): array
    {
        $alphabet = match ($charset) {
            'digits' => '23456789',
            'lower' => 'abcdefghjkmnpqrstuvwxyz',
            'upper' => 'ABCDEFGHJKLMNPQRSTUVWXYZ',
            default => 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789',
        };
        $max = strlen($alphabet) - 1;
        $names = [];
        $guard = 0;
        $limit = max(self::ATTEMPTS, $count * 8);
        while (count($names) < $count && $guard < $limit) {
            $guard++;
            $suffix = '';
            for ($i = 0; $i < $length; $i++) {
                $suffix .= $alphabet[random_int(0, $max)];
            }
            $name = $prefix.$suffix;
            if (isset($names[$name]) || $this->nameTaken($zone, $name)) {
                continue;
            }
            $names[$name] = $name;
        }

        return array_values($names);
    }

    private function nameTaken(WifiZone $zone, string $name): bool
    {
        if ($name === '') {
            return true;
        }
        $database = Voucher::withoutGlobalScope('tenant')->withTrashed()
            ->where('tenant_id', $zone->tenant_id)
            ->where('username', $name)
            ->exists();
        if ($database) {
            return true;
        }

        return isset($this->routerNames($zone)[$name]);
    }

    private function passwordTaken(WifiZone $zone, string $password): bool
    {
        if ($password === '') {
            return true;
        }
        if ($this->localPasswords === null) {
            $this->localPasswords = [];
            Voucher::withoutGlobalScope('tenant')->withTrashed()
                ->where('tenant_id', $zone->tenant_id)
                ->orderBy('id')
                ->each(function (Voucher $voucher) {
                    $plain = $voucher->password;
                    if (is_string($plain) && $plain !== '') {
                        $this->localPasswords[$plain] = true;
                    }
                });
        }
        if (isset($this->localPasswords[$password])) {
            return true;
        }

        return isset($this->routerPasswords($zone)[$password]);
    }

    /**
     * @return array<string, bool>
     */
    private function routerNames(WifiZone $zone): array
    {
        return $this->routerSecrets($zone)['names'];
    }

    /**
     * @return array<string, bool>
     */
    private function routerPasswords(WifiZone $zone): array
    {
        return $this->routerSecrets($zone)['passwords'];
    }

    /**
     * @return array{names: array<string, bool>, passwords: array<string, bool>}
     */
    private function routerSecrets(WifiZone $zone): array
    {
        $reading = $this->read($zone);

        return ['names' => $reading['names'], 'passwords' => $reading['passwords']];
    }

    /**
     * @return list<string>
     */
    private function recentPrefixes(WifiZone $zone): array
    {
        $prefixes = [];
        $names = Voucher::query()->latest('id')->limit(30)->pluck('username');
        foreach ($names as $name) {
            if (preg_match('/^([A-Za-z]{1,6})/', (string) $name, $match)) {
                $prefixes[$match[1]] = $match[1];
            }
        }

        return array_slice(array_values($prefixes), 0, 5);
    }

    /**
     * @param  list<array<string, string>>  $routerProfiles
     * @return list<array<string, mixed>>
     */
    private function merge(WifiZone $zone, array $routerProfiles, bool $live): array
    {
        $plans = Plan::query()
            ->where('status', 'active')
            ->where(function ($query) use ($zone) {
                $query->whereNull('wifi_zone_id')->orWhere('wifi_zone_id', $zone->id);
            })
            ->orderBy('duration_seconds')
            ->get();

        $byName = [];
        foreach ($routerProfiles as $row) {
            $name = trim((string) ($row['name'] ?? ''));
            if (! $this->token($name)) {
                continue;
            }
            $byName[$name] = $this->fromRouter($name, $row, $live);
        }

        foreach ($plans as $plan) {
            $name = trim((string) $plan->mikrotik_profile);
            if (! $this->token($name)) {
                continue;
            }
            $current = $byName[$name] ?? $this->fromRouter($name, [], false);
            $byName[$name] = $this->applyPlan($current, $plan);
        }

        $profiles = array_values($byName);
        usort($profiles, fn (array $left, array $right) => strcasecmp($left['name'], $right['name']));

        return $profiles;
    }

    /**
     * @param  array<string, string>  $row
     * @return array<string, mixed>
     */
    private function fromRouter(string $name, array $row, bool $live): array
    {
        $shared = isset($row['shared-users']) && $row['shared-users'] !== '' ? (int) $row['shared-users'] : null;

        return [
            'name' => $name,
            'plan_id' => null,
            'ready' => false,
            'on_router' => $live && $row !== [],
            'summary' => $name,
            'snapshot' => [
                'profile' => $name,
                'validity' => null,
                'validity_label' => null,
                'time_limit' => $this->filled($row['session-timeout'] ?? null),
                'time_label' => null,
                'price_amount' => null,
                'price_currency' => null,
                'selling_price_amount' => null,
                'selling_price_currency' => null,
                'rate_limit' => $this->filled($row['rate-limit'] ?? null),
                'shared_users' => $shared,
                'lock_user' => $this->filled($row['lock-user'] ?? null),
                'address_pool' => $this->filled($row['address-pool'] ?? null),
                'parent_queue' => $this->filled($row['parent-queue'] ?? null),
                'expired_mode' => $this->filled($row['expired-mode'] ?? null),
                'data_bytes' => null,
                'data_label' => null,
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return array<string, mixed>
     */
    private function applyPlan(array $profile, Plan $plan): array
    {
        $seconds = (int) $plan->duration_seconds;
        $validity = $seconds > 0 ? RouterOsProtocol::secondsToRouterTime($seconds) : null;
        $currency = $plan->currency ?: config('limete.currency');
        $amount = $plan->price;
        $snapshot = $profile['snapshot'];
        $snapshot['validity'] = $validity;
        $snapshot['validity_label'] = $plan->durationLabel();
        $snapshot['time_label'] = $plan->validityLabel();
        if (! filled($snapshot['time_limit'])) {
            $snapshot['time_limit'] = $validity;
        } elseif ($validity !== null && $snapshot['time_limit'] !== $validity) {
            $snapshot['time_label'] = null;
        }
        $sellingAmount = $plan->selling_price !== null && $plan->selling_price !== '' ? $plan->selling_price : $amount;
        $sellingCurrency = $plan->selling_currency ?: $currency;
        $snapshot['price_amount'] = $amount;
        $snapshot['price_currency'] = $currency;
        $snapshot['selling_price_amount'] = $sellingAmount;
        $snapshot['selling_price_currency'] = $sellingCurrency;
        $hotspot = is_array($plan->hotspot) ? $plan->hotspot : [];
        foreach (['rate_limit' => 'rate_limit', 'lock_user' => 'lock_user', 'address_pool' => 'address_pool', 'parent_queue' => 'parent_queue', 'expired_mode' => 'expired_mode'] as $from => $to) {
            if (! filled($snapshot[$to] ?? null) && filled($hotspot[$from] ?? null) && ($hotspot[$from] ?? null) !== 'none') {
                $snapshot[$to] = (string) $hotspot[$from];
            }
        }
        if (! filled($snapshot['shared_users'] ?? null) && filled($hotspot['shared_users'] ?? null)) {
            $snapshot['shared_users'] = (int) $hotspot['shared_users'];
        }
        if (filled($hotspot['time_limit'] ?? null)) {
            $snapshot['time_limit'] = (string) $hotspot['time_limit'];
            $limitSeconds = RouterOsProtocol::routerTimeToSeconds((string) $hotspot['time_limit']);
            $validitySeconds = $validity ? RouterOsProtocol::routerTimeToSeconds($validity) : null;
            if ($limitSeconds !== null && $validitySeconds !== null && $limitSeconds !== $validitySeconds) {
                $snapshot['time_label'] = null;
            }
        }
        $snapshot['plan_id'] = $plan->id;
        $profile['snapshot'] = $snapshot;
        $profile['plan_id'] = $plan->id;
        $profile['ready'] = true;
        $profile['summary'] = $this->summary($snapshot);

        return $profile;
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    private function summary(array $snapshot): string
    {
        $parts = array_filter([
            $snapshot['validity_label'] ?? null,
            $snapshot['time_label'] ?? $snapshot['time_limit'] ?? null,
            $snapshot['rate_limit'] ?? null,
            $snapshot['price_amount'] !== null ? Money::shop($snapshot['price_amount'], $snapshot['price_currency']) : null,
        ]);

        return implode(' · ', $parts);
    }

    /**
     * @return array{profiles: list<array<string, string>>, servers: list<string>, names: array<string, bool>, passwords: array<string, bool>, offline: bool, notice: ?string, live: bool}
     */
    private function read(WifiZone $zone): array
    {
        if ($this->reading !== null && $this->readingZone === $zone->id) {
            return $this->reading;
        }

        $router = $this->router($zone);
        $empty = [
            'profiles' => [],
            'servers' => [],
            'names' => [],
            'passwords' => [],
            'offline' => $router === null,
            'notice' => $router === null ? 'Routeur hors ligne.' : null,
            'live' => false,
        ];
        if (! $router) {
            $empty['profiles'] = $this->storedProfiles($zone);
            $this->reading = $empty;
            $this->readingZone = $zone->id;

            return $empty;
        }

        try {
            $profiles = $this->mikrotik->readPrint($router, '/ip/hotspot/user/profile/print');
            $servers = $this->mikrotik->readPrint($router, '/ip/hotspot/print');
            $accounts = $this->mikrotik->readPrint($router, '/ip/hotspot/user/print');
        } catch (Throwable $exception) {
            $empty['offline'] = true;
            $empty['notice'] = $this->assist->readable($exception);
            $empty['profiles'] = $this->storedProfiles($zone);
            $empty['servers'] = $this->storedServers($router);
            $this->reading = $empty;
            $this->readingZone = $zone->id;

            return $empty;
        }

        $names = [];
        $passwords = [];
        foreach ($accounts as $row) {
            $name = (string) ($row['name'] ?? '');
            if ($name !== '') {
                $names[$name] = true;
            }
            if (filled($row['password'] ?? null)) {
                $passwords[(string) $row['password']] = true;
            }
        }

        $serverNames = [];
        foreach ($servers as $server) {
            $name = trim((string) ($server['name'] ?? ''));
            if ($this->token($name)) {
                $serverNames[] = $name;
            }
        }
        if ($serverNames !== []) {
            array_unshift($serverNames, 'all');
        }

        $this->reading = [
            'profiles' => $profiles,
            'servers' => $serverNames,
            'names' => $names,
            'passwords' => $passwords,
            'offline' => false,
            'notice' => null,
            'live' => true,
        ];
        $this->readingZone = $zone->id;

        return $this->reading;
    }

    /**
     * @return list<array<string, string>>
     */
    private function storedProfiles(WifiZone $zone): array
    {
        $router = $this->router($zone);
        if (! $router) {
            return [];
        }

        return MikrotikProfile::query()
            ->where('mikrotik_id', $router->id)
            ->get()
            ->map(function (MikrotikProfile $profile) {
                $raw = is_array($profile->raw) ? $profile->raw : [];
                $raw['name'] = $profile->name;
                if (! isset($raw['rate-limit']) && filled($profile->rate_limit)) {
                    $raw['rate-limit'] = $profile->rate_limit;
                }
                if (! isset($raw['shared-users']) && $profile->shared_users) {
                    $raw['shared-users'] = (string) $profile->shared_users;
                }

                return $raw;
            })
            ->all();
    }

    /**
     * @return list<string>
     */
    private function storedServers(Mikrotik $router): array
    {
        $names = [];
        foreach ($router->details['hotspot_servers'] ?? [] as $server) {
            $name = trim((string) (is_array($server) ? ($server['name'] ?? '') : ''));
            if ($this->token($name)) {
                $names[] = $name;
            }
        }
        if ($names !== []) {
            array_unshift($names, 'all');
        }

        return $names;
    }

    private function router(WifiZone $zone): ?Mikrotik
    {
        return $zone->mikrotiks()->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * @param  list<array<string, mixed>>  $profiles
     */
    private function lastName(WifiZone $zone, array $profiles): ?string
    {
        $last = Cache::get($this->lastKey($zone));
        if (! is_string($last)) {
            return null;
        }
        foreach ($profiles as $profile) {
            if ($profile['name'] === $last && $profile['ready']) {
                return $last;
            }
        }

        return null;
    }

    private function lastKey(WifiZone $zone): string
    {
        return 'ticket-quick.last.'.$zone->tenant_id;
    }

    private function draftKey(WifiZone $zone, string $draft): string
    {
        return 'ticket-quick.draft.'.$zone->tenant_id.'.'.$draft;
    }

    private function draft(mixed $value): string
    {
        $draft = trim((string) $value);
        if (! preg_match('/^[A-Za-z0-9-]{16,80}$/', $draft)) {
            throw new RuntimeException('Paramètres incompatibles.');
        }

        return $draft;
    }

    /**
     * @return array<string, mixed>
     */
    private function technicalProfile(WifiZone $zone, string $name): array
    {
        $catalog = $this->catalog($zone);
        foreach ($catalog['profiles'] as $candidate) {
            if ($candidate['name'] !== $name) {
                continue;
            }
            if (! $catalog['offline'] && ! $candidate['on_router']) {
                throw new RuntimeException('Profil introuvable.');
            }

            return $candidate;
        }

        throw new RuntimeException('Profil introuvable.');
    }

    /**
     * @param  array<string, mixed>  $profile
     * @return list<array{id: int, name: string, summary: string}>
     */
    private function compatiblePlans(WifiZone $zone, array $profile): array
    {
        $rows = [];
        $plans = Plan::query()
            ->where('status', 'active')
            ->where(function ($query) use ($zone) {
                $query->whereNull('wifi_zone_id')->orWhere('wifi_zone_id', $zone->id);
            })
            ->orderBy('name')
            ->get();

        foreach ($plans as $plan) {
            if (! $this->planMatchesProfile($zone, $plan, $profile)) {
                continue;
            }
            $rows[] = [
                'id' => $plan->id,
                'name' => $plan->name,
                'summary' => trim($plan->durationLabel().' · '.Money::shop($plan->price, $plan->currency)),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $profile
     */
    private function planMatchesProfile(WifiZone $zone, Plan $plan, array $profile): bool
    {
        if ($plan->status !== 'active' || (int) $plan->tenant_id !== (int) $zone->tenant_id) {
            return false;
        }
        if ($plan->wifi_zone_id && (int) $plan->wifi_zone_id !== (int) $zone->id) {
            return false;
        }

        $linked = trim((string) $plan->mikrotik_profile);
        if ($linked !== '' && $linked !== $profile['name']) {
            return false;
        }

        $hotspot = is_array($plan->hotspot) ? $plan->hotspot : [];
        $snapshot = $profile['snapshot'];
        foreach (['rate_limit', 'lock_user', 'address_pool', 'parent_queue', 'expired_mode'] as $key) {
            $left = $this->filled($hotspot[$key] ?? null);
            $right = $this->filled($snapshot[$key] ?? null);
            if ($left === null || $right === null || strcasecmp($left, 'none') === 0 || strcasecmp($right, 'none') === 0) {
                continue;
            }
            if (strcasecmp($left, $right) !== 0) {
                return false;
            }
        }
        if (filled($hotspot['shared_users'] ?? null) && filled($snapshot['shared_users'] ?? null) && (int) $hotspot['shared_users'] !== (int) $snapshot['shared_users']) {
            return false;
        }

        return true;
    }

    private function forgetReading(): void
    {
        $this->reading = null;
        $this->readingZone = null;
    }

    private function token(string $value): bool
    {
        return $value !== '' && (bool) preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/', $value);
    }

    private function filled(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}

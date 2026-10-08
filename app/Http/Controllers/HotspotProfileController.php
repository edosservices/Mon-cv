<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Models\Plan;
use App\Models\WifiZone;
use App\Services\Mikrotik\MikrotikPreparation;
use App\Services\Mikrotik\MikrotikService;
use App\Services\Mikrotik\RouterOsProtocol;
use App\Services\TicketAssist;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class HotspotProfileController extends Controller
{
    /** @var array<string, string> */
    private const EXPIRED = [
        'none' => 'None — aucune action supplémentaire',
        'remove' => 'Remove — retirer le compte à l’expiration',
        'notice' => 'Notice — afficher un avis',
        'remove-record' => 'Remove & Record — retirer et garder une trace',
        'notice-record' => 'Notice & Record — avis et trace',
    ];

    public function index()
    {
        return view('profiles.index', [
            'plans' => Plan::with('wifiZone')->orderBy('name')->get(),
        ]);
    }

    public function create(MikrotikService $mikrotik)
    {
        return view('profiles.form', $this->formData($mikrotik, null));
    }

    public function store(Request $request, MikrotikService $mikrotik, MikrotikPreparation $preparation, TicketAssist $assist)
    {
        $data = $this->validated($request, $mikrotik, $preparation);
        $warning = null;
        $notice = null;
        if ($data['sync'] ?? false) {
            $result = $this->sync($data, $mikrotik, $preparation, $assist);
            if ($result['blocked']) {
                return back()->with('warning', $result['message'])->withInput();
            }
            $warning = $result['warning'];
            $notice = $result['notice'];
        }
        Plan::create($this->attributes($data));

        return redirect()->route('entrepreneur.profiles')->with($warning ? 'warning' : 'status', $warning ?? $notice ?? 'Profil enregistré.');
    }

    public function edit(Plan $plan, MikrotikService $mikrotik)
    {
        return view('profiles.form', $this->formData($mikrotik, $plan));
    }

    public function update(Request $request, Plan $plan, MikrotikService $mikrotik, MikrotikPreparation $preparation)
    {
        $data = $this->validated($request, $mikrotik, $preparation);
        $plan->update($this->attributes($data));
        $warning = $request->boolean('sync')
            ? 'Le profil LIMETE est mis à jour. Un profil déjà présent sur le MikroTik n’est pas écrasé.'
            : null;

        return redirect()->route('entrepreneur.profiles')->with($warning ? 'warning' : 'status', $warning ?? 'Profil mis à jour.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(MikrotikService $mikrotik, ?Plan $plan): array
    {
        $zones = WifiZone::orderBy('name')->get();
        $zoneId = (int) old('wifi_zone_id', $plan?->wifi_zone_id ?: $zones->first()?->id);
        $router = $this->router($zoneId);
        $offline = $router === null;

        return [
            'plan' => $plan,
            'zones' => $zones,
            'expired' => self::EXPIRED,
            'pools' => $this->names($mikrotik, $router, '/ip/pool/print'),
            'queues' => $this->names($mikrotik, $router, '/queue/simple/print'),
            'offline' => $offline,
            'notice' => $offline ? 'Routeur hors ligne.' : null,
            'routerProfiles' => $this->routerProfiles($mikrotik, $router),
        ];
    }

    /**
     * @return list<array{name: string, rate: string, shared: string, timeout: string, pool: string}>
     */
    private function routerProfiles(MikrotikService $mikrotik, ?Mikrotik $router): array
    {
        if (! $router) {
            return [];
        }

        try {
            $rows = [];
            foreach ($mikrotik->getHotspotProfiles($router) as $profile) {
                $name = trim((string) ($profile['name'] ?? ''));
                if ($name === '' || str_starts_with($name, '*')) {
                    continue;
                }
                $rows[] = [
                    'name' => $name,
                    'rate' => (string) ($profile['rate-limit'] ?? ''),
                    'shared' => (string) ($profile['shared-users'] ?? ''),
                    'timeout' => (string) ($profile['session-timeout'] ?? ''),
                    'pool' => (string) ($profile['address-pool'] ?? ''),
                ];
            }

            return $rows;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, MikrotikService $mikrotik, MikrotikPreparation $preparation): array
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'name' => ['required', 'string', 'max:32', 'regex:/^[A-Za-z0-9][A-Za-z0-9._-]{0,31}$/'],
            'shared_users' => ['required', 'integer', 'min:1', 'max:100'],
            'rate_limit' => ['required', 'string', 'max:32'],
            'expired_mode' => ['required', 'in:'.implode(',', array_keys(self::EXPIRED))],
            'price_amount' => ['required', 'numeric', 'min:0'],
            'price_currency' => ['required', 'string', 'min:2', 'max:8', 'regex:/^[A-Za-z]{2,8}$/'],
            'selling_price_amount' => ['nullable', 'numeric', 'min:0'],
            'selling_price_currency' => ['nullable', 'string', 'min:2', 'max:8', 'regex:/^[A-Za-z]{2,8}$/'],
            'lock_user' => ['required', 'in:disable,enable'],
            'validity' => ['required', 'string', 'max:32'],
            'time_limit' => ['nullable', 'string', 'max:32'],
            'address_pool' => ['nullable', 'string', 'max:32'],
            'parent_queue' => ['nullable', 'string', 'max:32'],
            'sync' => ['nullable', 'boolean'],
        ], [
            'name.regex' => 'Paramètres incompatibles.',
        ]);

        WifiZone::findOrFail($data['wifi_zone_id']);
        try {
            $data['validity'] = $preparation->normalizedTime($data['validity']);
            $data['time_limit'] = $preparation->normalizedTime($data['time_limit'] ?? null);
            $data['rate_limit'] = $preparation->normalizedRate($data['rate_limit']);
        } catch (RuntimeException) {
            throw ValidationException::withMessages(['validity' => 'Paramètres incompatibles.']);
        }
        if ($data['rate_limit'] === null || $data['validity'] === null) {
            throw ValidationException::withMessages(['validity' => 'Paramètres incompatibles.']);
        }

        $validitySeconds = RouterOsProtocol::routerTimeToSeconds($data['validity']);
        $limitSeconds = $data['time_limit'] ? RouterOsProtocol::routerTimeToSeconds($data['time_limit']) : null;
        if ($validitySeconds === null || ($limitSeconds !== null && $limitSeconds > $validitySeconds)) {
            throw ValidationException::withMessages([
                'time_limit' => 'Time Limit doit être inférieur à Validity.',
            ]);
        }

        $router = $this->router((int) $data['wifi_zone_id']);
        $pools = $this->names($mikrotik, $router, '/ip/pool/print');
        $queues = $this->names($mikrotik, $router, '/queue/simple/print');
        $pool = $data['address_pool'] ?? 'none';
        $queue = $data['parent_queue'] ?? 'none';
        if ($pool !== 'none' && $pool !== '' && ! in_array($pool, $pools, true)) {
            throw ValidationException::withMessages(['address_pool' => 'Paramètres incompatibles.']);
        }
        if ($queue !== 'none' && $queue !== '' && ! in_array($queue, $queues, true)) {
            throw ValidationException::withMessages(['parent_queue' => 'Paramètres incompatibles.']);
        }
        $data['address_pool'] = $pool === '' ? 'none' : $pool;
        $data['parent_queue'] = $queue === '' ? 'none' : $queue;
        $data['price_currency'] = strtoupper($data['price_currency']);
        $data['selling_price_currency'] = filled($data['selling_price_currency'] ?? null)
            ? strtoupper($data['selling_price_currency'])
            : $data['price_currency'];
        if ($data['selling_price_amount'] === null || $data['selling_price_amount'] === '') {
            $data['selling_price_amount'] = $data['price_amount'];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'wifi_zone_id' => (int) $data['wifi_zone_id'],
            'name' => $data['name'],
            'duration_seconds' => RouterOsProtocol::routerTimeToSeconds($data['validity']),
            'price' => $data['price_amount'],
            'currency' => $data['price_currency'],
            'selling_price' => $data['selling_price_amount'],
            'selling_currency' => $data['selling_price_currency'],
            'mikrotik_profile' => $data['name'],
            'unlimited_data' => true,
            'status' => 'active',
            'hotspot' => [
                'address_pool' => $data['address_pool'],
                'shared_users' => (int) $data['shared_users'],
                'rate_limit' => $data['rate_limit'],
                'expired_mode' => $data['expired_mode'],
                'lock_user' => $data['lock_user'] === 'enable' ? 'Enable' : 'Disable',
                'parent_queue' => $data['parent_queue'],
                'validity' => $data['validity'],
                'time_limit' => $data['time_limit'] ?: $data['validity'],
            ],
        ];
    }

    /**
     * La synchro est faite avant l’enregistrement local.
     * Un routeur injoignable n’est pas annoncé comme synchronisé.
     *
     * @param  array<string, mixed>  $data
     * @return array{blocked: bool, warning: ?string, notice: ?string, message: ?string}
     */
    private function sync(array $data, MikrotikService $mikrotik, MikrotikPreparation $preparation, TicketAssist $assist): array
    {
        $router = $this->router((int) $data['wifi_zone_id']);
        if (! $router) {
            return [
                'blocked' => false,
                'warning' => 'Routeur hors ligne. Le profil est enregistré dans LIMETE.',
                'notice' => null,
                'message' => null,
            ];
        }

        $input = [
            'profile_name' => $data['name'],
            'session_timeout' => $data['time_limit'] ?: $data['validity'],
            'rate_limit' => $data['rate_limit'],
            'shared_users' => max(1, (int) $data['shared_users']),
            'address_pool' => $data['address_pool'] ?? 'none',
            'parent_queue' => $data['parent_queue'] ?? 'none',
        ];

        try {
            $notice = $preparation->apply($router, 'profile', $input);
            $found = null;
            foreach ($mikrotik->getHotspotProfiles($router) as $row) {
                if (($row['name'] ?? '') === $data['name']) {
                    $found = $row;
                    break;
                }
            }
            if ($found === null || ! $preparation->echoedProfileMatches($found, $input)) {
                throw new RuntimeException('Profil introuvable.');
            }

            return ['blocked' => false, 'warning' => null, 'notice' => $notice, 'message' => null];
        } catch (Throwable $exception) {
            $message = $assist->readable($exception);
            if ($message === 'Routeur hors ligne.') {
                return [
                    'blocked' => false,
                    'warning' => 'Routeur hors ligne. Le profil est enregistré dans LIMETE.',
                    'notice' => null,
                    'message' => null,
                ];
            }

            return ['blocked' => true, 'warning' => null, 'notice' => null, 'message' => $message];
        }
    }

    private function router(int $zoneId): ?Mikrotik
    {
        if ($zoneId < 1) {
            return null;
        }

        return Mikrotik::query()->where('wifi_zone_id', $zoneId)->where('is_active', true)->orderBy('id')->first();
    }

    /**
     * @return list<string>
     */
    private function names(MikrotikService $mikrotik, ?Mikrotik $router, string $path): array
    {
        if (! $router) {
            return [];
        }

        try {
            $names = [];
            foreach ($mikrotik->readPrint($router, $path) as $row) {
                $name = trim((string) ($row['name'] ?? ''));
                if ($name !== '') {
                    $names[] = $name;
                }
            }

            return $names;
        } catch (Throwable) {
            return [];
        }
    }
}

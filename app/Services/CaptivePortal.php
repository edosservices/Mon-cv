<?php

namespace App\Services;

use App\Models\CaptiveSession;
use App\Models\Mikrotik;
use App\Models\WifiZone;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Http\Request;
use Throwable;

/**
 * Retient un appareil seulement si un MikroTik de la zone confirme le couple MAC + IP.
 * Une valeur envoyée par le navigateur, seule, n'est jamais enregistrée.
 */
class CaptivePortal
{
    public function __construct(private MikrotikService $mikrotik) {}

    public function capture(Request $request, WifiZone $zone): ?CaptiveSession
    {
        $mac = $this->mac($request->query('mac', $request->query('mac-address')));
        $ip = $this->ip($request->query('ip', $request->query('ip-address')));

        if ($mac === null || $ip === null) {
            return $this->current($zone);
        }

        $zone->loadMissing('mikrotiks');
        foreach ($zone->mikrotiks as $router) {
            if (! $router->is_active || (int) $router->wifi_zone_id !== (int) $zone->id) {
                continue;
            }

            if (! $this->confirmed($router, $mac, $ip)) {
                continue;
            }

            $session = CaptiveSession::create([
                'wifi_zone_id' => $zone->id,
                'mikrotik_id' => $router->id,
                'mac_address' => $mac,
                'ip_address' => $ip,
                'hotspot_username' => $this->label($request->query('username'), 64),
                'hotspot_server' => $this->label($request->query('server', $request->query('server-name')), 32),
                'verified_at' => now(),
            ]);
            session(['captive.'.$zone->id => $session->id]);

            return $session;
        }

        return $this->current($zone);
    }

    public function current(WifiZone $zone): ?CaptiveSession
    {
        $id = session('captive.'.$zone->id);
        if (! is_numeric($id)) {
            return null;
        }

        return CaptiveSession::query()
            ->where('wifi_zone_id', $zone->id)
            ->whereNotNull('verified_at')
            ->find((int) $id);
    }

    private function confirmed(Mikrotik $router, string $mac, string $ip): bool
    {
        try {
            return $this->mikrotik->hotspotHostMatches($router, $mac, $ip);
        } catch (Throwable) {
            return false;
        }
    }

    private function mac(mixed $value): ?string
    {
        $mac = strtoupper(str_replace('-', ':', trim((string) $value)));

        return preg_match('/^([0-9A-F]{2}:){5}[0-9A-F]{2}$/', $mac) ? $mac : null;
    }

    private function ip(mixed $value): ?string
    {
        $ip = trim((string) $value);

        return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
    }

    private function label(mixed $value, int $limit): ?string
    {
        $label = trim((string) $value);
        if ($label === '' || strlen($label) > $limit || ! preg_match('/^[A-Za-z0-9][A-Za-z0-9._@-]{0,63}$/', $label)) {
            return null;
        }

        return $label;
    }
}

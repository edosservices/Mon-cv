<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Models\Voucher;
use App\Models\WifiSession;
use App\Services\AuditLogger;
use App\Services\Mikrotik\MikrotikService;
use Illuminate\Http\Request;
use RuntimeException;

class ActiveUserController extends Controller
{
    public function index(MikrotikService $service)
    {
        $groups = [];

        foreach (Mikrotik::with('wifiZone')->get() as $router) {
            try {
                $users = $service->getActiveUsers($router);
                $router->forceFill(['status' => 'online', 'last_seen_at' => now(), 'last_error' => null])->save();
                $this->syncSessions($router, $users);
            } catch (RuntimeException $exception) {
                $users = [];
                $router->forceFill(['status' => 'offline', 'last_error' => $exception->getMessage()])->save();
            }

            $groups[] = ['router' => $router, 'users' => $users];
        }

        return view('active-users.index', ['groups' => $this->withCommercialClock($groups)]);
    }

    public function disconnect(Request $request, MikrotikService $service, AuditLogger $audit)
    {
        $data = $request->validate([
            'mikrotik_id' => ['required', 'integer'],
            'active_id' => ['required', 'string', 'max:40'],
            'username' => ['nullable', 'string', 'max:80'],
        ]);

        $router = Mikrotik::findOrFail($data['mikrotik_id']);
        $service->disconnectUser($router, $data['active_id']);
        $audit->record('user.disconnected', $router, null, ['username' => $data['username'] ?? null]);

        WifiSession::where('mikrotik_id', $router->id)
            ->where('router_session_id', $data['active_id'])
            ->whereNull('ended_at')
            ->update(['ended_at' => now()]);

        return back()->with('status', 'Utilisateur déconnecté.');
    }

    private function withCommercialClock(array $groups): array
    {
        $names = [];
        foreach ($groups as $group) {
            foreach ($group['users'] as $user) {
                if (! empty($user['user'])) {
                    $names[] = $user['user'];
                }
            }
        }

        $vouchers = $names === []
            ? collect()
            : Voucher::with('plan:id,name')->whereIn('username', array_values(array_unique($names)))->get()->keyBy('username');
        $vouchers->each->refreshExpiry();

        foreach ($groups as &$group) {
            foreach ($group['users'] as &$user) {
                $voucher = $vouchers->get($user['user'] ?? '');
                $user['plan_name'] = $voucher?->plan?->name;
                $user['commercial_start'] = $voucher?->activated_at;
                $user['commercial_expires'] = $voucher?->expires_at;
            }
        }
        unset($group, $user);

        return $groups;
    }

    private function syncSessions(Mikrotik $router, array $users): void
    {
        $ids = [];

        foreach ($users as $user) {
            $sessionId = $user['.id'] ?? null;
            if (! $sessionId) {
                continue;
            }
            $ids[] = $sessionId;

            WifiSession::firstOrCreate(
                ['mikrotik_id' => $router->id, 'router_session_id' => $sessionId, 'ended_at' => null],
                [
                    'wifi_zone_id' => $router->wifi_zone_id,
                    'username' => $user['user'] ?? 'inconnu',
                    'ip_address' => $user['address'] ?? null,
                    'mac_address' => $user['mac-address'] ?? null,
                    'started_at' => now(),
                ]
            );
        }

        WifiSession::where('mikrotik_id', $router->id)
            ->whereNull('ended_at')
            ->when($ids !== [], fn ($query) => $query->whereNotIn('router_session_id', $ids))
            ->update(['ended_at' => now()]);
    }
}

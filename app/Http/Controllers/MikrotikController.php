<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Mikrotik;
use App\Models\Plan;
use App\Models\MikrotikSnapshot;
use App\Models\PlanMikrotikProfile;
use App\Models\WifiZone;
use App\Services\AuditLogger;
use App\Services\Mikrotik\MikrotikPreparation;
use App\Services\Mikrotik\MikrotikService;
use App\Services\PlanLimiter;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class MikrotikController extends Controller
{
    public function index()
    {
        return view('mikrotiks.index', [
            'routers' => Mikrotik::with(['wifiZone', 'profiles', 'planLinks.profile'])->latest()->get(),
            'plans' => Plan::with('wifiZone')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('mikrotiks.form', [
            'router' => new Mikrotik([
                'api_port' => 8728,
                'api_ssl_port' => 8729,
                'connection_type' => 'api',
                'timeout' => 5,
                'is_active' => true,
            ]),
            'zones' => WifiZone::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, PlanLimiter $limits, AuditLogger $audit, MikrotikService $service)
    {
        $data = $this->validated($request, true);
        $limits->assertMikrotik();
        $router = Mikrotik::create($data);
        $router = $service->syncRouter($router);
        $audit->record('mikrotik.created', $router, null, $router->only(['name', 'host', 'status', 'dns']));
        $message = $router->status === 'online'
            ? 'MikroTik enregistré.'
            : 'MikroTik enregistré. Impossible de joindre le MikroTik.';

        return redirect()->route('mikrotiks.show', $router)->with($router->status === 'online' ? 'status' : 'warning', $message);
    }

    public function probe(Request $request, MikrotikService $service)
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:160', 'regex:/^\S+$/'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'api_ssl_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'connection_type' => ['nullable', Rule::in(['api', 'api-ssl'])],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:60'],
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'max:120'],
        ]);
        $secure = ($data['connection_type'] ?? 'api') === 'api-ssl';
        $port = $secure ? (int) ($data['api_ssl_port'] ?? 8729) : (int) $data['api_port'];

        try {
            $found = $service->discover(
                $data['host'],
                $port,
                $data['username'],
                $data['password'],
                (int) ($data['timeout'] ?? 5),
                $secure,
            );
        } catch (RuntimeException $exception) {
            return back()->withInput($request->except('password'))->with(
                'probe_error',
                $service->explainFailure($exception->getMessage(), $data['host'], $secure),
            );
        }

        return back()->withInput($request->except('password'))->with('probe', $found);
    }

    public function show(Request $request, Mikrotik $mikrotik, MikrotikService $service, MikrotikPreparation $preparation)
    {
        $tabs = ['overview', 'connection', 'hotspot', 'profiles', 'plans', 'users', 'sessions', 'interfaces', 'ip', 'portal', 'prepare', 'journal'];
        $tab = $request->string('tab')->toString();
        if (! in_array($tab, $tabs, true)) {
            $tab = 'overview';
        }

        $mikrotik->load(['wifiZone', 'profiles', 'planLinks.profile']);
        $users = collect($mikrotik->detail('users', []));
        if ($request->filled('q')) {
            $q = mb_strtolower($request->string('q')->toString());
            $users = $users->filter(fn (array $user) => str_contains(mb_strtolower((string) ($user['name'] ?? '')), $q));
        }
        if ($request->filled('profile')) {
            $users = $users->where('profile', $request->string('profile')->toString());
        }
        if ($request->string('state')->toString() === 'active') {
            $users = $users->filter(fn (array $user) => ($user['disabled'] ?? 'false') !== 'true');
        }
        if ($request->string('state')->toString() === 'disabled') {
            $users = $users->filter(fn (array $user) => ($user['disabled'] ?? 'false') === 'true');
        }

        return view('mikrotiks.show', [
            'router' => $mikrotik,
            'tab' => $tab,
            'zones' => WifiZone::orderBy('name')->get(),
            'plans' => $this->zonePlans($mikrotik),
            'users' => $users->values(),
            'sessions' => $mikrotik->detail('sessions', []),
            'commands' => $service->suggestedPortalCommands($mikrotik),
            'logs' => AuditLog::with('user')
                ->where('resource_type', Mikrotik::class)
                ->where('resource_id', $mikrotik->id)
                ->orderByDesc('created_at')
                ->limit(40)
                ->get(),
            'plan' => $tab === 'prepare' ? $preparation->present($mikrotik) : null,
            'verifyReport' => session('verify_report'),
        ]);
    }

    public function edit(Mikrotik $mikrotik)
    {
        return view('mikrotiks.form', [
            'router' => $mikrotik,
            'zones' => WifiZone::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Mikrotik $mikrotik, AuditLogger $audit)
    {
        $data = $this->validated($request, false);
        if (! filled($data['password'] ?? null)) {
            unset($data['password']);
        }
        $mikrotik->update($data);
        $audit->record('mikrotik.updated', $mikrotik, null, $mikrotik->only(['name', 'host', 'dns', 'hotspot_server']));

        return redirect()->route('mikrotiks.show', $mikrotik)->with('status', 'MikroTik mis à jour.');
    }

    public function destroy(Mikrotik $mikrotik, AuditLogger $audit)
    {
        $audit->record('mikrotik.deleted', $mikrotik, $mikrotik->only(['name', 'host']));
        $mikrotik->delete();

        return redirect()->route('mikrotiks.index')->with('status', 'MikroTik archivé.');
    }

    public function test(Mikrotik $mikrotik, MikrotikService $service, AuditLogger $audit)
    {
        $service->testConnection($mikrotik);
        $audit->record('mikrotik.tested', $mikrotik, null, [
            'status' => $mikrotik->status,
            'last_error' => $mikrotik->last_error,
        ]);
        if ($mikrotik->status === 'online') {
            return back()->with('status', 'Connexion réussie');
        }

        $detail = $mikrotik->last_error ? ' — '.$mikrotik->last_error : '';

        return back()->with('warning', $service->statusLabel($mikrotik).$detail);
    }

    public function sync(Mikrotik $mikrotik, MikrotikService $service, AuditLogger $audit)
    {
        $router = $service->syncRouter($mikrotik);
        $audit->record('mikrotik.synced', $router, null, [
            'status' => $router->status,
            'identity' => $router->identity,
            'routeros_version' => $router->routeros_version,
        ]);
        if ($router->status !== 'online') {
            return back()->with('warning', 'Impossible de joindre le MikroTik.'.($router->last_error ? ' '.$router->last_error : ''));
        }

        return back()->with('status', 'Synchronisation terminée ✓ Système ✓ HotSpot ✓ Profils ✓ Utilisateurs ✓ Sessions');
    }

    public function syncPending(Mikrotik $mikrotik, MikrotikService $service, AuditLogger $audit)
    {
        $summary = $service->syncPending($mikrotik);
        $audit->record('mikrotik.pending_synced', $mikrotik, null, [
            'synced' => $summary['synced'],
            'unsynced' => $summary['unsynced'],
        ]);
        if (! $summary['online']) {
            return back()->with('warning', 'Impossible de joindre le MikroTik. Ticket créé, synchronisation MikroTik en attente.');
        }

        return back()->with('status', $summary['synced'].' ticket(s) synchronisé(s).');
    }

    public function assignProfile(Request $request, Mikrotik $mikrotik, AuditLogger $audit)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'mikrotik_profile' => ['required', 'string', 'max:80'],
        ]);
        $plan = Plan::query()->findOrFail($data['plan_id']);
        if ($plan->wifi_zone_id && $mikrotik->wifi_zone_id && (int) $plan->wifi_zone_id !== (int) $mikrotik->wifi_zone_id) {
            abort(404);
        }
        $profile = $mikrotik->profiles()->where('name', $data['mikrotik_profile'])->first();
        if (! $profile) {
            return back()->with('warning', 'Profil non trouvé');
        }
        PlanMikrotikProfile::updateOrCreate(
            ['plan_id' => $plan->id, 'mikrotik_id' => $mikrotik->id],
            ['mikrotik_profile_id' => $profile->id],
        );
        $plan->forceFill(['mikrotik_profile' => $profile->name])->save();
        $audit->record('mikrotik.profile_mapped', $mikrotik, null, [
            'plan' => $plan->name,
            'profile' => $profile->name,
        ]);

        return back()->with('status', 'Le forfait '.$plan->name.' utilise le profil '.$profile->name.'.');
    }

    public function syncProfiles(Mikrotik $mikrotik, MikrotikService $service)
    {
        try {
            $profiles = $service->getHotspotProfiles($mikrotik);
        } catch (RuntimeException $exception) {
            return back()->with('warning', 'Synchronisation impossible. '.$exception->getMessage());
        }

        return back()->with('status', count($profiles).' profil(s) HotSpot synchronisé(s).');
    }

    public function selectHotspot(Request $request, Mikrotik $mikrotik, AuditLogger $audit)
    {
        $data = $request->validate([
            'hotspot_server' => ['required', 'string', 'max:80'],
        ]);
        $known = collect($mikrotik->detail('hotspot_servers', []))->pluck('name');
        if (! $known->contains($data['hotspot_server'])) {
            return back()->with('warning', 'Non détecté');
        }
        $mikrotik->forceFill(['hotspot_server' => $data['hotspot_server']])->save();
        $audit->record('mikrotik.hotspot_selected', $mikrotik, null, ['hotspot_server' => $data['hotspot_server']]);

        return back()->with('status', 'HotSpot sélectionné.');
    }

    public function disconnectSession(Request $request, Mikrotik $mikrotik, MikrotikService $service, AuditLogger $audit)
    {
        $data = $request->validate([
            'active_id' => ['required', 'string', 'max:40'],
            'username' => ['nullable', 'string', 'max:80'],
        ]);

        try {
            $service->disconnectActiveUser($mikrotik, $data['active_id']);
        } catch (RuntimeException $exception) {
            return back()->with('warning', 'Déconnexion impossible. '.$exception->getMessage());
        }

        $audit->record('session.disconnected', $mikrotik, null, [
            'username' => $data['username'] ?? null,
        ]);

        return back()->with('status', 'Session déconnectée.');
    }

    public function applyPortal(Mikrotik $mikrotik, MikrotikService $service, AuditLogger $audit)
    {
        try {
            $applied = $service->applyPortalConfiguration($mikrotik);
        } catch (RuntimeException $exception) {
            return back()->with('warning', 'Configuration non appliquée. '.$exception->getMessage());
        }

        $audit->record('mikrotik.portal_applied', $mikrotik, null, ['commands' => $applied]);

        return back()->with('status', $applied.' élément(s) appliqué(s) au MikroTik.');
    }

    public function prepareRead(Mikrotik $mikrotik, MikrotikService $service)
    {
        $router = $service->syncRouter($mikrotik);
        if ($router->status !== 'online') {
            return back()->with('warning', 'Impossible de joindre le MikroTik.');
        }

        return back()->with('status', 'Configuration lue sur le routeur. Aucune modification n’a été appliquée.');
    }

    public function prepareApply(Request $request, Mikrotik $mikrotik, MikrotikPreparation $preparation)
    {
        $data = $request->validate([
            'group' => ['required', Rule::in(['dns', 'walled_garden', 'hotspot', 'profile'])],
            'confirm' => ['accepted'],
            'dns' => ['nullable', 'string', 'max:160'],
            'hotspot_name' => ['nullable', 'string', 'max:32'],
            'interface' => ['nullable', 'string', 'max:32'],
            'address_pool' => ['nullable', 'string', 'max:32'],
            'profile_name' => ['nullable', 'string', 'max:32'],
            'session_timeout' => ['nullable', 'string', 'max:20'],
            'rate_limit' => ['nullable', 'string', 'max:40'],
            'idle_timeout' => ['nullable', 'string', 'max:20'],
            'shared_users' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        try {
            $message = $preparation->apply($mikrotik, $data['group'], $data);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        return back()->with('status', $message);
    }

    public function prepareVerify(Mikrotik $mikrotik, MikrotikPreparation $preparation)
    {
        $report = $preparation->verify($mikrotik);

        return back()->with('verify_report', $report);
    }

    public function prepareDns(Request $request, Mikrotik $mikrotik, MikrotikPreparation $preparation)
    {
        $data = $request->validate(['dns' => ['required', 'string', 'max:160']]);
        $result = $preparation->checkDns($mikrotik, $data['dns']);

        return back()->with('status', $result['label']);
    }

    public function restoreSnapshot(Mikrotik $mikrotik, MikrotikSnapshot $snapshot, MikrotikPreparation $preparation)
    {
        if ((int) $snapshot->mikrotik_id !== (int) $mikrotik->id) {
            abort(404);
        }

        try {
            $message = $preparation->restore($mikrotik, $snapshot);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        return back()->with('status', $message);
    }

    private function validated(Request $request, bool $creating): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'description' => ['nullable', 'string', 'max:2000'],
            'wifi_zone_id' => ['required', Rule::exists('wifi_zones', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'host' => ['required', 'string', 'max:160', 'regex:/^\S+$/'],
            'dns' => ['nullable', 'string', 'max:160', 'regex:/^[A-Za-z0-9.-]*$/'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'api_ssl_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'connection_type' => ['nullable', Rule::in(['api', 'api-ssl'])],
            'timeout' => ['nullable', 'integer', 'min:1', 'max:60'],
            'username' => ['required', 'string', 'max:80'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'max:120'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['api_ssl_port'] = $data['api_ssl_port'] ?? 8729;
        $data['connection_type'] = $data['connection_type'] ?? 'api';
        $data['timeout'] = $data['timeout'] ?? 5;
        $data['is_active'] = $request->exists('is_active') ? $request->boolean('is_active') : true;
        $data['dns'] = filled($data['dns'] ?? null) ? $data['dns'] : null;

        return $data;
    }

    private function zonePlans(Mikrotik $router)
    {
        return Plan::with('wifiZone')->orderBy('name')->get()->filter(function (Plan $plan) use ($router) {
            if (! $router->wifi_zone_id) {
                return $plan->wifi_zone_id === null;
            }

            return $plan->wifi_zone_id === null || (int) $plan->wifi_zone_id === (int) $router->wifi_zone_id;
        });
    }
}

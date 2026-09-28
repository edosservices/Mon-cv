<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Models\Plan;
use App\Models\WifiZone;
use App\Services\AuditLogger;
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
            'routers' => Mikrotik::with(['wifiZone', 'profiles'])->latest()->get(),
            'plans' => Plan::with('wifiZone')->orderBy('name')->get(),
        ]);
    }

    public function create()
    {
        return view('mikrotiks.form', [
            'router' => new Mikrotik(['api_port' => 8728]),
            'zones' => WifiZone::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, PlanLimiter $limits, AuditLogger $audit, MikrotikService $service)
    {
        $data = $this->validated($request, true);
        $limits->assertMikrotik();
        $router = Mikrotik::create($data);
        $router = $service->syncRouter($router);
        $audit->record('mikrotik.created', $router, null, $router->only(['name', 'host', 'status']));
        $message = $router->status === 'online'
            ? 'MikroTik enregistré.'
            : 'MikroTik enregistré. Impossible de joindre le MikroTik.';

        return redirect()->route('mikrotiks.index')->with($router->status === 'online' ? 'status' : 'warning', $message);
    }

    public function probe(Request $request, MikrotikService $service)
    {
        $data = $request->validate([
            'host' => ['required', 'string', 'max:160', 'regex:/^\S+$/'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:80'],
            'password' => ['required', 'string', 'max:120'],
        ]);

        try {
            $found = $service->discover($data['host'], (int) $data['api_port'], $data['username'], $data['password']);
        } catch (RuntimeException $exception) {
            return back()->withInput($request->except('password'))->with('probe_error', $exception->getMessage());
        }

        return back()->withInput($request->except('password'))->with('probe', $found);
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
        $audit->record('mikrotik.updated', $mikrotik, null, $mikrotik->only(['name', 'host']));

        return redirect()->route('mikrotiks.index')->with('status', 'MikroTik mis à jour.');
    }

    public function destroy(Mikrotik $mikrotik, AuditLogger $audit)
    {
        $audit->record('mikrotik.deleted', $mikrotik, $mikrotik->only(['name', 'host']));
        $mikrotik->delete();

        return redirect()->route('mikrotiks.index')->with('status', 'MikroTik archivé.');
    }

    public function test(Mikrotik $mikrotik, MikrotikService $service)
    {
        $service->testConnection($mikrotik);
        $label = $service->statusLabel($mikrotik);
        $detail = $mikrotik->last_error ? ' — '.$mikrotik->last_error : '';

        return back()->with($mikrotik->status === 'online' ? 'status' : 'warning', $label.$detail);
    }

    public function sync(Mikrotik $mikrotik, MikrotikService $service)
    {
        $router = $service->syncRouter($mikrotik);
        if ($router->status !== 'online') {
            return back()->with('warning', 'Impossible de joindre le MikroTik.'.($router->last_error ? ' '.$router->last_error : ''));
        }

        return back()->with('status', 'MikroTik synchronisé.');
    }

    public function assignProfile(Request $request, Mikrotik $mikrotik)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer'],
            'mikrotik_profile' => ['required', 'string', 'max:80'],
        ]);
        $plan = Plan::query()->findOrFail($data['plan_id']);
        if ($plan->wifi_zone_id && $mikrotik->wifi_zone_id && (int) $plan->wifi_zone_id !== (int) $mikrotik->wifi_zone_id) {
            abort(404);
        }
        $known = $mikrotik->profiles()->pluck('name');
        if (! $known->contains($data['mikrotik_profile'])) {
            return back()->with('warning', 'Profil non trouvé');
        }
        $plan->forceFill(['mikrotik_profile' => $data['mikrotik_profile']])->save();

        return back()->with('status', 'Le forfait '.$plan->name.' utilise le profil '.$data['mikrotik_profile'].'.');
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

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'wifi_zone_id' => ['required', Rule::exists('wifi_zones', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'host' => ['required', 'string', 'max:160', 'regex:/^\S+$/'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:80'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'max:120'],
        ]);
    }
}

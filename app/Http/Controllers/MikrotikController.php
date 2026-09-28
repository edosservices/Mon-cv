<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
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
            'routers' => Mikrotik::with('wifiZone')->latest()->get(),
        ]);
    }

    public function create()
    {
        return view('mikrotiks.form', [
            'router' => new Mikrotik(['api_port' => 8728]),
            'zones' => WifiZone::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, PlanLimiter $limits, AuditLogger $audit)
    {
        $limits->assertMikrotik();
        $router = Mikrotik::create($this->validated($request, true));
        $audit->record('mikrotik.created', $router, null, $router->only(['name', 'host']));

        return redirect()->route('mikrotiks.index')->with('status', 'MikroTik enregistré.');
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

        return back()->with('status', 'Test terminé : '.$mikrotik->status.'.');
    }

    public function syncProfiles(Mikrotik $mikrotik, MikrotikService $service)
    {
        try {
            $service->getProfiles($mikrotik);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        return back()->with('status', 'Profils synchronisés.');
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'wifi_zone_id' => ['required', Rule::exists('wifi_zones', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'host' => ['required', 'string', 'max:160'],
            'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'username' => ['required', 'string', 'max:80'],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'max:120'],
        ]);
    }
}

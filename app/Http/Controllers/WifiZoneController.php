<?php

namespace App\Http\Controllers;

use App\Models\WifiZone;
use App\Services\AuditLogger;
use App\Services\PlanLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WifiZoneController extends Controller
{
    public function index()
    {
        return view('wifi-zones.index', ['zones' => WifiZone::latest()->get()]);
    }

    public function create()
    {
        return view('wifi-zones.form', ['zone' => new WifiZone]);
    }

    public function store(Request $request, PlanLimiter $limits, AuditLogger $audit)
    {
        $limits->assertZone();
        $data = $this->validated($request);
        $data['slug'] = $this->slug($data['name']);
        $zone = WifiZone::create($data);
        $audit->record('wifi_zone.created', $zone, null, $zone->only(['name', 'slug']));

        return redirect()->route('wifi-zones.index')->with('status', 'WiFi Zone créée.');
    }

    public function edit(WifiZone $wifiZone)
    {
        return view('wifi-zones.form', ['zone' => $wifiZone]);
    }

    public function update(Request $request, WifiZone $wifiZone, AuditLogger $audit)
    {
        $old = $wifiZone->only(['name', 'location', 'status']);
        $wifiZone->update($this->validated($request, $wifiZone));
        $audit->record('wifi_zone.updated', $wifiZone, $old, $wifiZone->only(['name', 'location', 'status']));

        return redirect()->route('wifi-zones.index')->with('status', 'WiFi Zone mise à jour.');
    }

    public function destroy(WifiZone $wifiZone, AuditLogger $audit)
    {
        $audit->record('wifi_zone.deleted', $wifiZone, $wifiZone->only(['name']));
        $wifiZone->delete();

        return redirect()->route('wifi-zones.index')->with('status', 'WiFi Zone archivée.');
    }

    private function validated(Request $request, ?WifiZone $zone = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'logo' => ['nullable', 'image', 'max:1024'],
        ]);

        if ($request->hasFile('logo')) {
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        unset($data['logo']);

        return $data;
    }

    private function slug(string $name): string
    {
        $base = Str::slug($name) ?: 'zone';
        $slug = $base;
        $i = 2;
        while (WifiZone::withoutGlobalScope('tenant')->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i;
            $i++;
        }

        return $slug;
    }
}

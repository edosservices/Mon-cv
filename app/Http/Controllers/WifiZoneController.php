<?php

namespace App\Http\Controllers;

use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\AuditLogger;
use App\Services\PlanLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class WifiZoneController extends Controller
{
    public function index()
    {
        $zones = WifiZone::withCount('vouchers')->latest()->get();
        $clients = Voucher::query()
            ->whereNotNull('customer_id')
            ->selectRaw('wifi_zone_id, count(distinct customer_id) as aggregate')
            ->groupBy('wifi_zone_id')
            ->pluck('aggregate', 'wifi_zone_id');

        return view('wifi-zones.index', [
            'zones' => $zones,
            'clientCounts' => $clients,
            'first' => $zones->isEmpty(),
        ]);
    }

    public function create()
    {
        return view('wifi-zones.form', [
            'zone' => new WifiZone(['status' => 'active']),
            'first' => WifiZone::query()->doesntExist(),
        ]);
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
        return view('wifi-zones.form', ['zone' => $wifiZone, 'first' => false]);
    }

    public function status(WifiZone $wifiZone, AuditLogger $audit)
    {
        $wifiZone->update(['status' => $wifiZone->status === 'active' ? 'inactive' : 'active']);
        $audit->record('wifi_zone.updated', $wifiZone, null, $wifiZone->only(['status']));

        return back()->with('status', $wifiZone->status === 'active' ? 'WiFi Zone activée.' : 'WiFi Zone désactivée.');
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
            'display_name' => ['nullable', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'slogan' => ['nullable', 'string', 'max:200'],
            'phone' => ['nullable', 'string', 'max:30'],
            'whatsapp' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:160'],
            'primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
            'logo' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
            'banner' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif', 'max:1024'],
        ]);

        if ($request->hasFile('logo')) {
            $this->deleteStored($zone?->logo_path, 'logos');
            $data['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        if ($request->hasFile('banner')) {
            $this->deleteStored($zone?->banner_path, 'banners');
            $data['banner_path'] = $request->file('banner')->store('banners', 'public');
        }

        unset($data['logo'], $data['banner']);

        return $data;
    }

    private function deleteStored(?string $path, string $directory): void
    {
        if (! is_string($path) || ! str_starts_with($path, $directory.'/') || str_contains($path, '..')) {
            return;
        }

        Storage::disk('public')->delete($path);
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

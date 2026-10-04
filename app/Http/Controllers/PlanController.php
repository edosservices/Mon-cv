<?php

namespace App\Http\Controllers;

use App\Models\MikrotikProfile;
use App\Models\Plan;
use App\Models\PlanMikrotikProfile;
use App\Models\WifiZone;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        $profiles = MikrotikProfile::query()->with('mikrotik:id,wifi_zone_id,name')->get();

        $zones = WifiZone::orderBy('name')->get();
        $createZoneId = $zones->count() === 1 ? $zones->first()->id : null;

        return view('plans.index', [
            'plans' => Plan::with('wifiZone')->withCount([
                'vouchers as sold_count' => fn ($query) => $query->whereHas('saleItem'),
            ])->latest()->get(),
            'syncedProfiles' => $profiles,
            'createZoneId' => $createZoneId,
        ]);
    }

    public function create()
    {
        return view('plans.form', [
            'plan' => new Plan(['unlimited_data' => true, 'currency' => config('limete.currency'), 'status' => 'active']),
            'zones' => WifiZone::orderBy('name')->get(),
            'profiles' => $this->profiles(),
        ]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $plan = Plan::create($this->validated($request));
        $this->rememberProfile($plan);
        $audit->record('plan.created', $plan, null, $plan->only(['name', 'price', 'duration_seconds']));

        return redirect()->route('plans.index')->with('status', 'Forfait créé.');
    }

    public function edit(Plan $plan)
    {
        return view('plans.form', [
            'plan' => $plan,
            'zones' => WifiZone::orderBy('name')->get(),
            'profiles' => $this->profiles(),
        ]);
    }

    public function duplicate(Plan $plan, AuditLogger $audit)
    {
        $copy = $plan->replicate();
        $copy->name = Str::limit('Copie de '.$plan->name, 120, '');
        $copy->save();
        $audit->record('plan.created', $copy, null, $copy->only(['name', 'price', 'duration_seconds']));

        return redirect()->route('plans.edit', $copy)->with('status', 'Forfait dupliqué. Ajustez-le puis enregistrez.');
    }

    public function status(Plan $plan, AuditLogger $audit)
    {
        $plan->update(['status' => $plan->status === 'active' ? 'inactive' : 'active']);
        $audit->record('plan.updated', $plan, null, $plan->only(['status']));

        return back()->with('status', $plan->status === 'active' ? 'Forfait activé.' : 'Forfait désactivé.');
    }

    public function update(Request $request, Plan $plan, AuditLogger $audit)
    {
        $old = $plan->only(['name', 'price', 'duration_seconds', 'status']);
        $plan->update($this->validated($request));
        $this->rememberProfile($plan);
        $audit->record('plan.updated', $plan, $old, $plan->only(['name', 'price', 'duration_seconds', 'status']));

        return redirect()->route('plans.index')->with('status', 'Forfait mis à jour.');
    }

    public function destroy(Plan $plan, AuditLogger $audit)
    {
        $audit->record('plan.deleted', $plan, $plan->only(['name']));
        $plan->delete();

        return redirect()->route('plans.index')->with('status', 'Forfait archivé.');
    }

    private function validated(Request $request): array
    {
        if ($request->filled('duration_value')) {
            $value = (int) $request->input('duration_value');
            $seconds = match ((string) $request->input('duration_unit', 'hours')) {
                'minutes' => $value * 60,
                'days' => $value * 86400,
                default => $value * 3600,
            };
            $request->merge(['duration_seconds' => $seconds]);
        }

        if (! $request->filled('currency')) {
            $request->merge(['currency' => config('limete.currency', 'CDF')]);
        }

        if (! $request->filled('status')) {
            $request->merge(['status' => 'active']);
        }

        if ($request->input('price') === '') {
            $request->merge(['price' => null]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'wifi_zone_id' => ['nullable', Rule::exists('wifi_zones', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'duration_seconds' => ['required', 'integer', 'min:60', 'max:31536000'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'selling_price' => ['nullable', 'numeric', 'min:0'],
            'selling_currency' => ['nullable', 'string', 'size:3'],
            'mikrotik_profile' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'badge' => ['nullable', Rule::in(['populaire', 'meilleure_offre'])],
            'unlimited_data' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $data['unlimited_data'] = $request->boolean('unlimited_data');
        $data['badge'] = ($data['badge'] ?? null) ?: null;
        $data['selling_price'] = $request->filled('selling_price') ? $data['selling_price'] : null;
        $data['selling_currency'] = $request->filled('selling_currency') ? $data['selling_currency'] : ($data['currency'] ?? null);
        $this->assertKnownProfile($request, $data['mikrotik_profile'] ?? null, $data['wifi_zone_id'] ?? null);

        return $data;
    }

    private function profiles()
    {
        return MikrotikProfile::query()->with('mikrotik:id,name,wifi_zone_id')->orderBy('name')->get();
    }

    private function assertKnownProfile(Request $request, ?string $name, mixed $zoneId): void
    {
        if (! filled($name) || ! filled($zoneId)) {
            return;
        }

        $known = MikrotikProfile::query()
            ->whereHas('mikrotik', fn ($query) => $query->where('wifi_zone_id', $zoneId))
            ->pluck('name');
        if ($known->isEmpty() || $known->contains($name)) {
            return;
        }

        $current = $request->route('plan');
        if ($current instanceof Plan && $current->mikrotik_profile === $name) {
            return;
        }

        throw \Illuminate\Validation\ValidationException::withMessages([
            'mikrotik_profile' => 'Choisissez un profil lu sur le routeur de cette WiFi Zone.',
        ]);
    }

    private function rememberProfile(Plan $plan): void
    {
        if (! filled($plan->mikrotik_profile) || ! $plan->wifi_zone_id) {
            return;
        }

        $profile = MikrotikProfile::query()
            ->where('name', $plan->mikrotik_profile)
            ->whereHas('mikrotik', fn ($query) => $query->where('wifi_zone_id', $plan->wifi_zone_id))
            ->first();
        if (! $profile) {
            return;
        }

        PlanMikrotikProfile::updateOrCreate(
            ['plan_id' => $plan->id, 'mikrotik_id' => $profile->mikrotik_id],
            ['mikrotik_profile_id' => $profile->id],
        );
    }
}

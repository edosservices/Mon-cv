<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\WifiZone;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return view('plans.index', [
            'plans' => Plan::with('wifiZone')->withCount([
                'vouchers as sold_count' => fn ($query) => $query->whereHas('saleItem'),
            ])->latest()->get(),
        ]);
    }

    public function create()
    {
        return view('plans.form', ['plan' => new Plan(['unlimited_data' => true, 'currency' => config('limete.currency'), 'status' => 'active']), 'zones' => WifiZone::orderBy('name')->get()]);
    }

    public function store(Request $request, AuditLogger $audit)
    {
        $plan = Plan::create($this->validated($request));
        $audit->record('plan.created', $plan, null, $plan->only(['name', 'price', 'duration_seconds']));

        return redirect()->route('plans.index')->with('status', 'Forfait créé.');
    }

    public function edit(Plan $plan)
    {
        return view('plans.form', ['plan' => $plan, 'zones' => WifiZone::orderBy('name')->get()]);
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
            'mikrotik_profile' => ['nullable', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:1000'],
            'badge' => ['nullable', Rule::in(['populaire', 'meilleure_offre'])],
            'unlimited_data' => ['nullable', 'boolean'],
            'status' => ['required', Rule::in(['active', 'inactive'])],
        ]);

        $data['unlimited_data'] = $request->boolean('unlimited_data');
        $data['badge'] = ($data['badge'] ?? null) ?: null;

        return $data;
    }
}

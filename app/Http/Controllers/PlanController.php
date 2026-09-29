<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\WifiZone;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PlanController extends Controller
{
    public function index()
    {
        return view('plans.index', ['plans' => Plan::with('wifiZone')->latest()->get()]);
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
        $data['badge'] = $data['badge'] ?: null;

        return $data;
    }
}

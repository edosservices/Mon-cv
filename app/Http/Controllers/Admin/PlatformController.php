<?php

namespace App\Http\Controllers\Admin;

use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentProviderSetting;
use App\Models\SaasPlan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Notifications\PlatformNotification;
use App\Services\AuditLogger;
use App\Services\DashboardMetrics;
use App\Services\Payments\PaymentManager;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;

class PlatformController extends Controller
{
    public function dashboard(DashboardMetrics $metrics)
    {
        return view('admin.dashboard', ['metrics' => $metrics->platform()]);
    }

    public function tenants()
    {
        return view('admin.tenants', [
            'tenants' => Tenant::with('currentSubscription.saasPlan')->latest()->paginate(20),
        ]);
    }

    public function updateTenant(Request $request, Tenant $tenant, AuditLogger $audit)
    {
        $data = $request->validate(['status' => ['required', 'in:active,suspended']]);
        $old = $tenant->status;
        $tenant->update($data);

        if ($data['status'] === 'suspended') {
            $tenant->currentSubscription?->forceFill(['status' => SubscriptionStatus::Suspended->value])->save();
        }

        $audit->record('tenant.status', $tenant, ['status' => $old], $data, $tenant->id);
        $tenant->users()->whereHas('role', fn ($query) => $query->where('slug', 'entrepreneur'))->first()
            ?->notify(new PlatformNotification('tenant.status', 'Compte '.$data['status'], 'Le statut de votre entreprise a changé.'));

        return back()->with('status', 'Entreprise mise à jour.');
    }

    public function destroyTenant(Tenant $tenant, AuditLogger $audit)
    {
        $audit->record('tenant.archived', $tenant, ['name' => $tenant->name], null, $tenant->id);
        $tenant->delete();

        return back()->with('status', 'Entreprise archivée. Les données sont conservées.');
    }

    public function plans()
    {
        return view('admin.plans', ['plans' => SaasPlan::orderBy('id')->get()]);
    }

    public function updatePlan(Request $request, SaasPlan $saasPlan, AuditLogger $audit)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'size:3'],
            'interval_days' => ['required', 'integer', 'min:1', 'max:365'],
            'max_zones' => ['nullable', 'integer', 'min:1'],
            'max_mikrotiks' => ['nullable', 'integer', 'min:1'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        $old = $saasPlan->only(['price', 'max_zones', 'max_mikrotiks']);
        $saasPlan->update($data);
        $audit->record('saas_plan.updated', $saasPlan, $old, $saasPlan->only(['price', 'max_zones', 'max_mikrotiks']));

        return back()->with('status', 'Plan plateforme mis à jour.');
    }

    public function logs()
    {
        return view('admin.logs', [
            'logs' => AuditLog::with('user')->latest('created_at')->paginate(40),
        ]);
    }

    public function payments(PaymentManager $payments)
    {
        $providers = collect(config('limete.payment_providers'))->map(function (string $label, string $key) use ($payments) {
            $setting = PaymentProviderSetting::query()->where('provider', $key)->first();

            return [
                'key' => $key,
                'label' => $label,
                'configured' => $payments->configured($key),
                'enabled' => $setting?->enabled ?? true,
            ];
        });

        return view('admin.payments', [
            'payments' => Payment::withoutGlobalScope('tenant')->with('tenant')->latest()->paginate(30),
            'providers' => $providers,
        ]);
    }

    public function updateProvider(Request $request, string $provider, AuditLogger $audit)
    {
        abort_unless(array_key_exists($provider, config('limete.payment_providers')), 404);
        $enabled = $request->boolean('enabled');
        PaymentProviderSetting::query()->updateOrCreate(
            ['provider' => $provider],
            ['enabled' => $enabled],
        );
        $audit->record('payment.provider', null, null, [
            'provider' => $provider,
            'enabled' => $enabled,
        ]);

        return back()->with('status', 'Fournisseur mis à jour.');
    }

    public function confirmPayment(int $paymentId, SubscriptionService $service, AuditLogger $audit)
    {
        $payment = Payment::withoutGlobalScope('tenant')->findOrFail($paymentId);
        abort_unless($payment->payable_type === Subscription::class, 422);
        $service->confirm($payment);
        $audit->record('platform.payment_confirmed', $payment, null, ['status' => 'success'], $payment->tenant_id);

        return back()->with('status', 'Paiement plateforme confirmé.');
    }
}

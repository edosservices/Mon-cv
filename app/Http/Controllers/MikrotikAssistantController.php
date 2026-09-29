<?php

namespace App\Http\Controllers;

use App\Models\Mikrotik;
use App\Models\Plan;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\AuditLogger;
use App\Services\Mikrotik\MikrotikPreparation;
use App\Services\Mikrotik\MikrotikService;
use App\Services\PlanLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Validation\Rule;
use RuntimeException;

class MikrotikAssistantController extends Controller
{
    public function index()
    {
        $draft = session('mikrotik.assistant');

        return view('mikrotiks.assistant', [
            'draft' => is_array($draft) ? $draft : null,
            'routers' => Mikrotik::with('wifiZone')->latest()->get(),
            'zones' => WifiZone::orderBy('name')->get(),
            'pending' => Voucher::query()->where('sync_status', '!=', 'synced')->count(),
        ]);
    }

    public function start()
    {
        session(['mikrotik.assistant' => $this->blankDraft()]);

        return redirect()->route('mikrotiks.assistant');
    }

    public function step(Request $request)
    {
        $draft = $this->draft();
        $step = (int) $request->input('step');

        if ($step === 1) {
            $data = $request->validate(['name' => ['required', 'string', 'max:160']]);
            $draft['name'] = $data['name'];
            $draft['step'] = 2;
        } elseif ($step === 2) {
            $data = $request->validate([
                'host' => ['required', 'string', 'max:160', 'regex:/^\S+$/'],
                'api_port' => ['required', 'integer', 'min:1', 'max:65535'],
                'api_ssl_port' => ['nullable', 'integer', 'min:1', 'max:65535'],
                'connection_type' => ['nullable', Rule::in(['api', 'api-ssl'])],
                'timeout' => ['nullable', 'integer', 'min:1', 'max:15'],
            ]);
            if (app(MikrotikService::class)->isDocumentationHost($data['host'])) {
                return back()->withInput($request->except('password'))->with('warning', 'Cette adresse ne correspond pas à un routeur réel.');
            }
            $draft['host'] = $data['host'];
            $draft['api_port'] = (int) $data['api_port'];
            $draft['api_ssl_port'] = (int) ($data['api_ssl_port'] ?? 8729);
            $draft['connection_type'] = $data['connection_type'] ?? 'api';
            $draft['timeout'] = (int) ($data['timeout'] ?? 5);
            $draft['step'] = 3;
        } elseif ($step === 3) {
            $data = $request->validate([
                'username' => ['required', 'string', 'max:80'],
                'password' => ['required', 'string', 'max:120'],
            ]);
            $draft['username'] = $data['username'];
            $draft['secret'] = Crypt::encryptString($data['password']);
            $draft['step'] = 4;
        } else {
            return redirect()->route('mikrotiks.assistant');
        }

        session(['mikrotik.assistant' => $draft]);

        return redirect()->route('mikrotiks.assistant');
    }

    public function test(MikrotikService $service)
    {
        $draft = $this->draft();
        if (! filled($draft['host'] ?? null) || ! filled($draft['username'] ?? null) || ! filled($draft['secret'] ?? null)) {
            return redirect()->route('mikrotiks.assistant')->with('warning', 'Indiquez l’adresse et les identifiants avant de tester.');
        }

        if ($service->isDocumentationHost($draft['host'])) {
            return redirect()->route('mikrotiks.assistant')->with('warning', 'Cette adresse ne correspond pas à un routeur réel.');
        }

        $secure = ($draft['connection_type'] ?? 'api') === 'api-ssl';
        $port = $secure ? (int) $draft['api_ssl_port'] : (int) $draft['api_port'];
        $password = $this->password($draft);
        $mode = $service->connectionMode($draft['host']);

        try {
            $found = $service->discover($draft['host'], $port, $draft['username'], $password, (int) ($draft['timeout'] ?? 5), $secure);
        } catch (RuntimeException $exception) {
            $draft['probe'] = null;
            $draft['mode'] = $mode;
            $draft['step'] = 4;
            session(['mikrotik.assistant' => $draft]);

            return redirect()->route('mikrotiks.assistant')->with('warning', '✕ Connexion impossible. '.$service->explainFailure($exception->getMessage()));
        }

        $draft['probe'] = $found;
        $draft['mode'] = $mode;
        $draft['step'] = 4;
        session(['mikrotik.assistant' => $draft]);

        $message = $mode === 'real'
            ? '✓ Routeur connecté'
            : 'Simulation réussie. Aucun routeur réel n’est connecté.';

        return redirect()->route('mikrotiks.assistant')->with('status', $message);
    }

    public function advance()
    {
        $draft = $this->draft();
        if (! is_array($draft['probe'] ?? null)) {
            return redirect()->route('mikrotiks.assistant')->with('warning', 'Testez la connexion avant de continuer.');
        }

        $draft['step'] = 5;
        session(['mikrotik.assistant' => $draft]);

        return redirect()->route('mikrotiks.assistant');
    }

    public function save(Request $request, PlanLimiter $limits, MikrotikService $service, AuditLogger $audit)
    {
        $draft = $this->draft();
        if (! is_array($draft['probe'] ?? null) || ! filled($draft['secret'] ?? null)) {
            return redirect()->route('mikrotiks.assistant')->with('warning', 'Testez la connexion avant d’enregistrer.');
        }

        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer', Rule::exists('wifi_zones', 'id')->where('tenant_id', auth()->user()->tenant_id)],
            'auto_sync' => ['nullable', 'boolean'],
        ]);

        if ($service->isDocumentationHost($draft['host'])) {
            return redirect()->route('mikrotiks.assistant')->with('warning', 'Cette adresse ne correspond pas à un routeur réel.');
        }

        $limits->assertMikrotik();
        $router = Mikrotik::create([
            'name' => $draft['name'],
            'wifi_zone_id' => $data['wifi_zone_id'],
            'host' => $draft['host'],
            'api_port' => $draft['api_port'],
            'api_ssl_port' => $draft['api_ssl_port'] ?? 8729,
            'connection_type' => $draft['connection_type'] ?? 'api',
            'timeout' => min(15, (int) ($draft['timeout'] ?? 5)),
            'username' => $draft['username'],
            'password' => $this->password($draft),
            'is_active' => true,
            'auto_sync' => $request->boolean('auto_sync', true),
        ]);
        $router = $service->syncRouter($router);
        $audit->record('mikrotik.created', $router, null, $router->only(['name', 'host', 'status']));
        session()->forget('mikrotik.assistant');

        $message = ($router->detail('connection_mode') === 'real' && $router->status === 'online')
            ? '✓ Routeur connecté'
            : ($router->status === 'online'
                ? 'Routeur enregistré en mode simulation. Aucun routeur réel n’est connecté.'
                : 'Routeur enregistré. Connexion impossible pour le moment.');

        return redirect()->route('mikrotiks.assistant.show', $router)->with($router->status === 'online' ? 'status' : 'warning', $message);
    }

    public function show(Mikrotik $mikrotik)
    {
        return view('mikrotiks.assistant-board', $this->board($mikrotik));
    }

    public function read(Mikrotik $mikrotik, MikrotikService $service)
    {
        $router = $service->syncRouter($mikrotik);
        if ($router->status !== 'online') {
            return back()->with('warning', '✕ Connexion impossible. '.$service->explainFailure((string) $router->last_error));
        }

        $message = $router->detail('connection_mode') === 'real'
            ? 'Le routeur a été relu.'
            : 'Lecture en simulation. Aucun routeur réel n’est connecté.';

        return back()->with('status', $message);
    }

    public function previewProfile(Request $request, Mikrotik $mikrotik, MikrotikPreparation $preparation)
    {
        $plan = $this->zonePlan($request, $mikrotik);
        $preview = $preparation->previewPlanProfile($plan);

        return back()->with('profile_preview', $preview)->with('preview_plan_id', $plan->id);
    }

    public function createProfile(Request $request, Mikrotik $mikrotik, MikrotikPreparation $preparation)
    {
        abort_unless(auth()->user()->hasPermission('plans.manage'), 403);
        $request->validate(['confirm' => ['accepted']]);
        $plan = $this->zonePlan($request, $mikrotik);

        try {
            $message = $preparation->createPlanProfile($mikrotik, $plan, true);
        } catch (RuntimeException $exception) {
            return back()->with('warning', $exception->getMessage());
        }

        return back()->with('status', $message);
    }

    public function retry(Mikrotik $mikrotik, MikrotikService $service)
    {
        $summary = $service->retryPending($mikrotik, 3);
        if (! $summary['online'] || (int) $summary['unsynced'] > 0) {
            return back()->with('warning', 'Synchronisation en attente. Tentative '.$summary['attempts'].' sur 3.');
        }

        return back()->with('status', $summary['synced'].' ticket(s) synchronisé(s).');
    }

    public function autoSync(Request $request, Mikrotik $mikrotik, AuditLogger $audit)
    {
        $mikrotik->forceFill(['auto_sync' => $request->boolean('auto_sync')])->save();
        $audit->record('mikrotik.auto_sync', $mikrotik, null, ['auto_sync' => $mikrotik->auto_sync]);

        return back()->with('status', $mikrotik->auto_sync
            ? 'Synchronisation automatique activée. Seuls les comptes des tickets sont envoyés.'
            : 'Synchronisation automatique désactivée. Le bouton Synchroniser reste disponible.');
    }

    private function board(Mikrotik $router): array
    {
        $router->load(['wifiZone', 'profiles', 'planLinks.profile']);
        $plans = Plan::with('wifiZone')->orderBy('name')->get()->filter(function (Plan $plan) use ($router) {
            return $plan->wifi_zone_id === null || (int) $plan->wifi_zone_id === (int) $router->wifi_zone_id;
        });
        $pending = Voucher::query()
            ->with('plan:id,name')
            ->where('wifi_zone_id', $router->wifi_zone_id)
            ->where('sync_status', '!=', 'synced')
            ->latest()
            ->limit(20)
            ->get();

        return [
            'router' => $router,
            'plans' => $plans,
            'pending' => $pending,
            'profiles' => $router->detail('profile_rows', []),
            'sessions' => $router->detail('sessions', []),
            'preview' => session('profile_preview'),
            'previewPlanId' => session('preview_plan_id'),
        ];
    }

    private function zonePlan(Request $request, Mikrotik $router): Plan
    {
        $data = $request->validate(['plan_id' => ['required', 'integer']]);
        $plan = Plan::query()->findOrFail($data['plan_id']);
        if ($plan->wifi_zone_id && $router->wifi_zone_id && (int) $plan->wifi_zone_id !== (int) $router->wifi_zone_id) {
            abort(404);
        }

        return $plan;
    }

    private function draft(): array
    {
        $draft = session('mikrotik.assistant');

        return is_array($draft) ? array_merge($this->blankDraft(), $draft) : $this->blankDraft();
    }

    private function blankDraft(): array
    {
        return [
            'step' => 1,
            'name' => '',
            'host' => '',
            'api_port' => 8728,
            'api_ssl_port' => 8729,
            'connection_type' => 'api',
            'timeout' => 5,
            'username' => '',
            'secret' => null,
            'probe' => null,
            'mode' => null,
        ];
    }

    private function password(array $draft): string
    {
        return Crypt::decryptString((string) $draft['secret']);
    }
}

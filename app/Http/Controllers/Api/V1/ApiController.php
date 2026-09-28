<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Mikrotik;
use App\Models\Plan;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\DashboardMetrics;
use App\Services\Mikrotik\MikrotikService;
use App\Services\PlanLimiter;
use App\Services\VoucherGenerator;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;

class ApiController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($data)) {
            return response()->json(['message' => 'Identifiants incorrects.'], 422);
        }

        $user = $request->user();
        $token = $user->createToken('api')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => ['id' => $user->id, 'name' => $user->name, 'role' => $user->roleSlug()],
        ]);
    }

    public function dashboard(DashboardMetrics $metrics)
    {
        return response()->json($metrics->entrepreneur());
    }

    public function zones()
    {
        return WifiZone::latest()->get();
    }

    public function storeZone(Request $request, PlanLimiter $limits)
    {
        $limits->assertZone();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'location' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:active,inactive'],
        ]);
        $data['slug'] = Str::slug($data['name']).'-'.Str::lower(Str::random(4));
        $data['status'] = $data['status'] ?? 'active';

        return WifiZone::create($data);
    }

    public function mikrotiks()
    {
        return Mikrotik::latest()->get()->map(fn (Mikrotik $router) => $this->routerPayload($router));
    }

    public function storeMikrotik(Request $request, PlanLimiter $limits)
    {
        $limits->assertMikrotik();
        $data = $request->validate([
            'name' => ['required', 'string'],
            'wifi_zone_id' => ['required', 'integer'],
            'host' => ['required', 'string'],
            'api_port' => ['required', 'integer'],
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);
        WifiZone::findOrFail($data['wifi_zone_id']);

        return $this->routerPayload(Mikrotik::create($data));
    }

    public function testMikrotik(Request $request, MikrotikService $service)
    {
        $data = $request->validate([
            'mikrotik_id' => ['nullable', 'integer'],
            'host' => ['required_without:mikrotik_id', 'string'],
            'api_port' => ['nullable', 'integer'],
            'username' => ['required_without:mikrotik_id', 'string'],
            'password' => ['required_without:mikrotik_id', 'string'],
        ]);

        try {
            if (! empty($data['mikrotik_id'])) {
                $router = Mikrotik::findOrFail($data['mikrotik_id']);
                $service->testConnection($router);

                return $this->routerPayload($router->fresh());
            }

            return $service->testCredentials($data['host'], (int) ($data['api_port'] ?? 8728), $data['username'], $data['password']);
        } catch (ModelNotFoundException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['status' => 'error', 'message' => $exception->getMessage()], 422);
        }
    }

    public function activeUsers(Mikrotik $mikrotik, MikrotikService $service)
    {
        try {
            return $service->getActiveUsers($mikrotik);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }

    public function disconnect(Request $request, Mikrotik $mikrotik, MikrotikService $service)
    {
        $data = $request->validate(['active_id' => ['required', 'string']]);
        $service->disconnectActiveUser($mikrotik, $data['active_id']);

        return response()->json(['status' => 'disconnected']);
    }

    public function vouchers()
    {
        return Voucher::with('plan:id,name')->latest()->paginate(50);
    }

    public function storeVoucher(Request $request, VoucherGenerator $generator, MikrotikService $mikrotik)
    {
        $data = $request->validate([
            'wifi_zone_id' => ['required', 'integer'],
            'plan_id' => ['required', 'integer'],
            'count' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $created = $generator->create(WifiZone::findOrFail($data['wifi_zone_id']), Plan::findOrFail($data['plan_id']), (int) ($data['count'] ?? 1));
        $summary = $mikrotik->provisionMany($created);

        return response()->json([
            'count' => count($created),
            'synced' => $summary['synced'],
            'unsynced' => $summary['unsynced'],
            'message' => $summary['unsynced'] > 0
                ? 'Tickets enregistrés. '.$summary['unsynced'].' compte(s) non créé(s) sur le MikroTik.'
                : 'Tickets créés sur le MikroTik.',
        ], 201);
    }

    public function bulkVouchers(Request $request, VoucherGenerator $generator)
    {
        $request->merge(['count' => $request->input('count', 10)]);

        return $this->storeVoucher($request, $generator);
    }

    public function plans()
    {
        return Plan::latest()->get();
    }

    public function storePlan(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string'],
            'duration_seconds' => ['required', 'integer', 'min:60'],
            'price' => ['nullable', 'numeric', 'min:0'],
            'currency' => ['nullable', 'string', 'size:3'],
            'wifi_zone_id' => ['nullable', 'integer'],
        ]);
        $data['currency'] = $data['currency'] ?? config('limete.currency');
        $data['unlimited_data'] = true;
        $data['status'] = 'active';
        if (! empty($data['wifi_zone_id'])) {
            WifiZone::findOrFail($data['wifi_zone_id']);
        }

        return Plan::create($data);
    }

    public function sales()
    {
        return Sale::with('items')->latest()->paginate(50);
    }

    public function statistics(Request $request, DashboardMetrics $metrics)
    {
        abort_unless($request->user()->tenant->currentSubscription?->saasPlan?->allows('advanced_statistics'), 403);

        return $metrics->statistics(now()->subDays(30), now());
    }

    private function routerPayload(Mikrotik $router): array
    {
        return $router->only(['id', 'name', 'host', 'api_port', 'username', 'status', 'routeros_version', 'identity', 'wifi_zone_id', 'last_seen_at', 'last_error']);
    }
}

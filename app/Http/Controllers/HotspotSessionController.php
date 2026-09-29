<?php

namespace App\Http\Controllers;

use App\Enums\VoucherStatus;
use App\Models\Mikrotik;
use App\Models\Voucher;
use App\Services\Mikrotik\MikrotikService;
use App\Support\TenantManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use RuntimeException;

class HotspotSessionController extends Controller
{
    public function show(string $username): JsonResponse
    {
        $voucher = $this->voucher($username);
        abort_unless($voucher, 404);

        return response()->json($this->payload($voucher))
            ->header('Cache-Control', 'no-store')
            ->header('Access-Control-Allow-Origin', '*');
    }

    public function script(Request $request): Response
    {
        $username = (string) $request->query('username', '');
        $voucher = preg_match('/^[A-Za-z0-9_-]{1,80}$/', $username) ? $this->voucher($username) : null;
        $lines = ['window.LIMETE_SESSION = window.LIMETE_SESSION || null;'];

        if ($voucher) {
            $payload = $this->payload($voucher);
            $lines = [
                'window.LIMETE_SESSION = '.json_encode([
                    'planLabel' => $payload['planLabel'],
                    'planSeconds' => $payload['planSeconds'],
                    'startedAt' => $payload['startedAt'],
                    'expiresAt' => $payload['expiresAt'],
                    'unlimited' => $payload['unlimited'],
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).';',
            ];
            if (is_string($payload['ticketUrl']) && $payload['ticketUrl'] !== '') {
                $lines[] = 'window.LIMETE_TICKET = '.json_encode($payload['ticketUrl'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).';';
            }
        }

        return response(implode("\n", $lines)."\n", 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    private function voucher(string $username): ?Voucher
    {
        $matches = Voucher::withoutGlobalScope('tenant')
            ->with('plan:id,name,duration_seconds,unlimited_data')
            ->where('username', $username)
            ->get();

        if ($matches->count() !== 1) {
            return null;
        }

        $voucher = $matches->first();
        $voucher->refreshExpiry();
        $voucher = $voucher->fresh('plan');
        $this->denyExpiredAccess($voucher);

        return $voucher;
    }

    private function denyExpiredAccess(Voucher $voucher): void
    {
        if ($voucher->status !== VoucherStatus::Expired->value || $voucher->sync_status !== 'synced' || ! $voucher->mikrotik_id) {
            return;
        }

        $router = Mikrotik::withoutGlobalScope('tenant')->find($voucher->mikrotik_id);
        if (! $router || $router->status !== 'online') {
            return;
        }

        if ((int) $router->tenant_id !== (int) $voucher->tenant_id || (int) $router->wifi_zone_id !== (int) $voucher->wifi_zone_id) {
            return;
        }

        app(TenantManager::class)->set($voucher->tenant_id);

        try {
            app(MikrotikService::class)->disableHotspotUser($router, $voucher->username);
        } catch (RuntimeException) {
            // L'erreur routeur est déjà journalisée sans secret. Le ticket reste expiré.
        }
    }

    private function payload(Voucher $voucher): array
    {
        return [
            'planLabel' => $voucher->plan?->name,
            'planSeconds' => (int) ($voucher->plan?->duration_seconds ?? 0),
            'startedAt' => $voucher->activated_at?->toIso8601String(),
            'expiresAt' => $voucher->expires_at?->toIso8601String(),
            'ticketUrl' => $voucher->public_token ? route('tickets.public', $voucher->public_token) : null,
            'status' => $voucher->status,
            'unlimited' => (bool) $voucher->plan?->unlimited_data,
        ];
    }
}

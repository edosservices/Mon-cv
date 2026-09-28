<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditLogger
{
    public function record(string $action, ?Model $resource = null, ?array $old = null, ?array $new = null, ?int $tenantId = null): void
    {
        $tenantId ??= app(TenantManager::class)->id() ?? ($resource->tenant_id ?? null);

        AuditLog::withoutGlobalScope('tenant')->create([
            'tenant_id' => $tenantId,
            'user_id' => Auth::id(),
            'action' => $action,
            'ip_address' => request()?->ip(),
            'resource_type' => $resource ? $resource::class : null,
            'resource_id' => $resource?->getKey(),
            'old_values' => $this->scrub($old),
            'new_values' => $this->scrub($new),
            'created_at' => now(),
        ]);
    }

    private function scrub(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        $clean = [];
        foreach ($values as $key => $value) {
            if (is_string($key) && $this->sensitive($key)) {
                $clean[$key] = '[masqué]';

                continue;
            }
            $clean[$key] = is_array($value) ? $this->scrub($value) : $value;
        }

        return $clean;
    }

    private function sensitive(string $key): bool
    {
        $key = strtolower($key);
        foreach (['password', 'secret', 'token', 'api_key', 'authorization', 'cvv', 'pin', 'client_secret'] as $needle) {
            if (str_contains($key, $needle)) {
                return true;
            }
        }

        return false;
    }
}

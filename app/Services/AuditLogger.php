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

        foreach (['password', 'remember_token'] as $secret) {
            if (array_key_exists($secret, $values)) {
                $values[$secret] = '[masqué]';
            }
        }

        return $values;
    }
}

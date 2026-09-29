<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Mikrotik extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'wifi_zone_id', 'name', 'description', 'host', 'dns', 'api_port',
        'api_ssl_port', 'connection_type', 'username', 'password', 'timeout',
        'routeros_version', 'architecture', 'board', 'identity', 'hotspot_server',
        'status', 'is_active', 'auto_sync', 'last_seen_at', 'last_synced_at', 'last_error', 'details',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'last_seen_at' => 'datetime',
            'details' => 'array',
            'is_active' => 'boolean',
            'auto_sync' => 'boolean',
            'last_synced_at' => 'datetime',
            'api_port' => 'integer',
            'api_ssl_port' => 'integer',
            'timeout' => 'integer',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function wifiZone(): BelongsTo
    {
        return $this->belongsTo(WifiZone::class);
    }

    public function profiles(): HasMany
    {
        return $this->hasMany(MikrotikProfile::class);
    }

    public function planLinks(): HasMany
    {
        return $this->hasMany(PlanMikrotikProfile::class);
    }

    public function usesSecureApi(): bool
    {
        return $this->connection_type === 'api-ssl';
    }

    public function connectionPort(): int
    {
        if ($this->usesSecureApi()) {
            return (int) ($this->api_ssl_port ?: 8729);
        }

        return (int) ($this->api_port ?: 8728);
    }

    public function detail(string $key, mixed $default = null): mixed
    {
        return $this->details[$key] ?? $default;
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CaptiveSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'wifi_zone_id', 'mikrotik_id', 'mac_address', 'ip_address',
        'hotspot_username', 'hotspot_server', 'verified_at',
    ];

    protected function casts(): array
    {
        return [
            'verified_at' => 'datetime',
        ];
    }

    public function wifiZone(): BelongsTo
    {
        return $this->belongsTo(WifiZone::class);
    }

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }
}

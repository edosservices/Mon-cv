<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WifiSession extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'wifi_zone_id', 'mikrotik_id', 'voucher_id', 'plan_id',
        'username', 'ip_address', 'mac_address', 'started_at', 'ended_at',
        'duration_seconds', 'router_session_id',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }

    public function wifiZone(): BelongsTo
    {
        return $this->belongsTo(WifiZone::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }
}

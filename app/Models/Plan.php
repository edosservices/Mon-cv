<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Plan extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'wifi_zone_id', 'name', 'duration_seconds', 'price',
        'currency', 'mikrotik_profile', 'description', 'unlimited_data', 'status',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'unlimited_data' => 'boolean',
        ];
    }

    public function wifiZone(): BelongsTo
    {
        return $this->belongsTo(WifiZone::class);
    }

    public function durationLabel(): string
    {
        $seconds = $this->duration_seconds;
        if ($seconds % 86400 === 0) {
            $days = (int) ($seconds / 86400);

            return $days.' jour'.($days > 1 ? 's' : '');
        }
        if ($seconds % 3600 === 0) {
            $hours = (int) ($seconds / 3600);

            return $hours.' heure'.($hours > 1 ? 's' : '');
        }

        return $seconds.' secondes';
    }
}

<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikrotikProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'mikrotik_id', 'name', 'rate_limit', 'shared_users', 'raw',
    ];

    protected function casts(): array
    {
        return ['raw' => 'array'];
    }

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }
}

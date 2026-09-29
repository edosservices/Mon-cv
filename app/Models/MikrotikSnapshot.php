<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MikrotikSnapshot extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'mikrotik_id', 'group', 'before', 'applied', 'restored_at',
    ];

    protected function casts(): array
    {
        return [
            'before' => 'array',
            'applied' => 'array',
            'restored_at' => 'datetime',
        ];
    }

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }
}

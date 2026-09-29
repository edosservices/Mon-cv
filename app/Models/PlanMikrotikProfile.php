<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlanMikrotikProfile extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'plan_id', 'mikrotik_id', 'mikrotik_profile_id',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(MikrotikProfile::class, 'mikrotik_profile_id');
    }
}

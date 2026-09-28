<?php

namespace App\Models\Concerns;

use App\Support\TenantManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $manager = app(TenantManager::class);

            if ($manager->bypassed()) {
                return;
            }

            $tenantId = $manager->id();

            if ($tenantId) {
                $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);

                return;
            }

            $builder->whereRaw('1 = 0');
        });

        static::creating(function (Model $model): void {
            $tenantId = app(TenantManager::class)->id();

            if ($tenantId) {
                $model->setAttribute('tenant_id', $tenantId);
            }
        });
    }
}

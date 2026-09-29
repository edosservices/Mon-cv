<?php

namespace App\Policies;

use App\Models\User;

class TenantOwnedPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->tenant_id !== null || $user->isSuperAdmin();
    }

    public function view(User $user, object $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function create(User $user): bool
    {
        return $user->tenant_id !== null || $user->isSuperAdmin();
    }

    public function update(User $user, object $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    public function delete(User $user, object $model): bool
    {
        return $this->sameTenant($user, $model);
    }

    private function sameTenant(User $user, object $model): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        return (int) $user->tenant_id === (int) ($model->tenant_id ?? 0);
    }
}

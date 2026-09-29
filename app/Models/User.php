<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'phone', 'client_phone', 'status', 'tenant_id', 'role_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class);
    }

    public function roleSlug(): ?string
    {
        return $this->role?->slug;
    }

    public function isSuperAdmin(): bool
    {
        return $this->roleSlug() === UserRole::SuperAdmin->value;
    }

    public function isEntrepreneur(): bool
    {
        return $this->roleSlug() === UserRole::Entrepreneur->value;
    }

    public function isStaff(): bool
    {
        return $this->roleSlug() === UserRole::Staff->value;
    }

    public function isClient(): bool
    {
        return $this->roleSlug() === UserRole::Client->value;
    }

    public function hasPermission(string $slug): bool
    {
        if ($this->isSuperAdmin() || $this->isEntrepreneur()) {
            return true;
        }

        return $this->permissions()->where('slug', $slug)->exists();
    }
}

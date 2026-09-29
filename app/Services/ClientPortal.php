<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\PhonePasswordReset;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Models\Voucher;
use App\Models\WifiZone;
use App\Services\Sms\SmsSender;
use App\Support\PhoneNumbers;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ClientPortal
{
    public function __construct(private SmsSender $sms) {}

    public function register(string $phone, string $password, ?string $name, ?string $email): User
    {
        $phone = $this->phone($phone);
        $this->assertPhoneAvailable($phone);

        $role = Role::where('slug', UserRole::Client->value)->firstOrFail();

        return User::create([
            'name' => filled($name) ? $name : 'Client',
            'email' => filled($email) ? $email : null,
            'phone' => $phone,
            'client_phone' => $phone,
            'password' => $password,
            'role_id' => $role->id,
            'tenant_id' => null,
            'status' => 'active',
        ]);
    }

    public function find(string $phone): ?User
    {
        $phone = PhoneNumbers::normalize($phone);
        if (! PhoneNumbers::valid($phone)) {
            return null;
        }

        return User::query()
            ->where('client_phone', $phone)
            ->whereHas('role', fn ($query) => $query->where('slug', UserRole::Client->value))
            ->first();
    }

    public function requestReset(string $phone): void
    {
        $user = $this->find($phone);
        if (! $user) {
            return;
        }

        $code = (string) random_int(100000, 999999);
        PhonePasswordReset::query()->where('phone', $user->client_phone)->delete();
        PhonePasswordReset::create([
            'phone' => $user->client_phone,
            'token' => Hash::make($code),
            'expires_at' => now()->addMinutes(15),
        ]);

        $this->sms->send($user->client_phone, 'LIMETE WIFI : votre code de réinitialisation est '.$code.'. Il expire dans 15 minutes.');
    }

    public function resetPassword(string $phone, string $code, string $password): void
    {
        $user = $this->find($phone);
        $reset = $user
            ? PhonePasswordReset::query()->where('phone', $user->client_phone)->latest('id')->first()
            : null;

        if (! $user || ! $reset || $reset->expires_at->isPast() || ! Hash::check($code, $reset->token)) {
            throw ValidationException::withMessages([
                'code' => 'Code incorrect ou expiré.',
            ]);
        }

        $user->forceFill(['password' => $password])->save();
        PhonePasswordReset::query()->where('phone', $user->client_phone)->delete();
    }

    /**
     * @return Collection<int, Customer>
     */
    public function customers(User $user): Collection
    {
        $phone = (string) $user->client_phone;
        $tail = substr(PhoneNumbers::digits($phone), -9);

        return Customer::withoutGlobalScope('tenant')
            ->whereNotNull('phone')
            ->when($tail !== '', fn ($query) => $query->where('phone', 'like', '%'.$tail))
            ->get()
            ->filter(fn (Customer $customer) => PhoneNumbers::matches($phone, $customer->phone))
            ->values();
    }

    /**
     * @return Collection<int, Voucher>
     */
    public function tickets(User $user): Collection
    {
        $ids = $this->customers($user)->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        $tickets = Voucher::withoutGlobalScope('tenant')
            ->with([
                'plan' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'wifiZone' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'saleItem.sale' => fn ($query) => $query->withoutGlobalScope('tenant'),
            ])
            ->whereIn('customer_id', $ids)
            ->latest()
            ->get();

        $tickets->each->refreshExpiry();

        return $tickets;
    }

    /**
     * @return Collection<int, Sale>
     */
    public function sales(User $user, int $limit = 5): Collection
    {
        $ids = $this->customers($user)->pluck('id');
        if ($ids->isEmpty()) {
            return collect();
        }

        return Sale::withoutGlobalScope('tenant')
            ->with([
                'wifiZone' => fn ($query) => $query->withoutGlobalScope('tenant'),
                'items.plan' => fn ($query) => $query->withoutGlobalScope('tenant'),
            ])
            ->whereIn('customer_id', $ids)
            ->latest()
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, WifiZone>
     */
    public function zones(): Collection
    {
        return WifiZone::withoutGlobalScope('tenant')
            ->where('status', 'active')
            ->whereHas('tenant', fn ($query) => $query->where('status', 'active'))
            ->orderBy('name')
            ->get(['id', 'tenant_id', 'name', 'slug', 'location', 'status']);
    }

    public function changePhone(User $user, string $phone): void
    {
        $phone = $this->phone($phone);
        if ($phone === $user->client_phone) {
            return;
        }

        $this->assertPhoneAvailable($phone, $user->id);
        $user->forceFill(['phone' => $phone, 'client_phone' => $phone])->save();
    }

    private function phone(string $phone): string
    {
        $normalized = PhoneNumbers::normalize($phone);
        if (! PhoneNumbers::valid($normalized)) {
            throw ValidationException::withMessages([
                'phone' => 'Entrez un numéro valide, par exemple +243 812 345 678.',
            ]);
        }

        return $normalized;
    }

    private function assertPhoneAvailable(string $phone, ?int $ignoreId = null): void
    {
        $taken = User::withTrashed()
            ->where('client_phone', $phone)
            ->when($ignoreId, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'phone' => 'Ce numéro est déjà utilisé.',
            ]);
        }
    }
}

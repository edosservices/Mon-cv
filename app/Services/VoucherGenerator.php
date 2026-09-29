<?php

namespace App\Services;

use App\Enums\VoucherStatus;
use App\Models\Plan;
use App\Models\Voucher;
use App\Models\WifiZone;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VoucherGenerator
{
    public function create(WifiZone $zone, Plan $plan, int $count = 1, bool $activate = false): array
    {
        $count = max(1, min($count, 100));
        $attempts = 0;

        while ($attempts < 4) {
            $attempts++;

            try {
                return DB::transaction(function () use ($zone, $plan, $count, $activate) {
                    $rows = $this->reserve($zone, $plan, $count, $activate);
                    foreach (array_chunk($rows, 40) as $chunk) {
                        Voucher::insert($chunk);
                    }

                    $tokens = array_column($rows, 'public_token');

                    return Voucher::withoutGlobalScope('tenant')
                        ->where('tenant_id', $zone->tenant_id)
                        ->whereIn('public_token', $tokens)
                        ->orderBy('id')
                        ->get()
                        ->all();
                });
            } catch (QueryException $exception) {
                if (! $this->isDuplicate($exception) || $attempts >= 4) {
                    throw $exception;
                }
            }
        }

        throw new RuntimeException('Impossible de générer des identifiants uniques pour tous les tickets.');
    }

    public function issue(WifiZone $zone, Plan $plan, string $username, string $password): Voucher
    {
        $username = trim($username);
        $taken = Voucher::withoutGlobalScope('tenant')->withTrashed()
            ->where('tenant_id', $zone->tenant_id)
            ->where('username', $username)
            ->exists();

        if ($taken || $username === '' || $password === '' || $username === $password) {
            throw new RuntimeException('Cet utilisateur existe déjà.');
        }

        $rows = $this->rows($zone, $plan, [[
            'username' => $username,
            'password' => $password,
            'public_token' => $this->makeToken(),
        ]], false);
        Voucher::insert($rows);

        return Voucher::withoutGlobalScope('tenant')
            ->where('tenant_id', $zone->tenant_id)
            ->where('public_token', $rows[0]['public_token'])
            ->firstOrFail();
    }

    public function activate(Voucher $voucher): Voucher
    {
        if ($voucher->status === VoucherStatus::Disabled->value) {
            return $voucher;
        }

        if ($voucher->expires_at) {
            $voucher->refreshExpiry();

            return $voucher->refresh();
        }

        $start = $voucher->activated_at ?? now();

        $voucher->forceFill([
            'status' => VoucherStatus::Active->value,
            'activated_at' => $start,
            'expires_at' => $start->copy()->addSeconds($voucher->plan->duration_seconds),
        ])->save();

        return $voucher->refresh();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function reserve(WifiZone $zone, Plan $plan, int $count, bool $activate): array
    {
        $identities = [];
        $rounds = 0;

        while (count($identities) < $count) {
            if (++$rounds > 8) {
                throw new RuntimeException('Impossible de générer des identifiants uniques pour tous les tickets.');
            }

            $need = $count - count($identities);
            $candidates = [];
            for ($i = 0; $i < $need; $i++) {
                $candidates[] = $this->freshIdentity($identities, $candidates);
            }

            $takenUsers = array_flip(Voucher::withoutGlobalScope('tenant')->withTrashed()
                ->where('tenant_id', $zone->tenant_id)
                ->whereIn('username', array_column($candidates, 'username'))
                ->pluck('username')
                ->all());
            $takenTokens = array_flip(Voucher::withoutGlobalScope('tenant')->withTrashed()
                ->whereIn('public_token', array_column($candidates, 'public_token'))
                ->pluck('public_token')
                ->all());

            foreach ($candidates as $candidate) {
                if (isset($takenUsers[$candidate['username']]) || isset($takenTokens[$candidate['public_token']])) {
                    continue;
                }
                $identities[] = $candidate;
            }
        }

        return $this->rows($zone, $plan, $identities, $activate);
    }

    /**
     * @param  list<array{username: string, password: string, public_token: string}>  $accepted
     * @param  list<array{username: string, password: string, public_token: string}>  $pending
     * @return array{username: string, password: string, public_token: string}
     */
    private function freshIdentity(array $accepted, array $pending): array
    {
        $users = [];
        $passwords = [];
        $tokens = [];
        foreach ([...$accepted, ...$pending] as $row) {
            $users[$row['username']] = true;
            $passwords[$row['password']] = true;
            $tokens[$row['public_token']] = true;
        }

        for ($attempt = 0; $attempt < 12; $attempt++) {
            $identity = [
                'username' => $this->makeUsername(),
                'password' => $this->makePassword(),
                'public_token' => $this->makeToken(),
            ];

            if ($identity['username'] === '' || $identity['password'] === '' || $identity['public_token'] === '') {
                continue;
            }
            if ($identity['password'] === $identity['username']) {
                continue;
            }
            if (isset($users[$identity['username']]) || isset($passwords[$identity['password']]) || isset($tokens[$identity['public_token']])) {
                continue;
            }

            return $identity;
        }

        throw new RuntimeException('Collision répétée sur un identifiant de ticket.');
    }

    /**
     * @param  list<array{username: string, password: string, public_token: string}>  $identities
     * @return list<array<string, mixed>>
     */
    private function rows(WifiZone $zone, Plan $plan, array $identities, bool $activate): array
    {
        $now = now();
        $rows = [];

        foreach ($identities as $identity) {
            $model = new Voucher;
            $model->password = $identity['password'];
            $rows[] = [
                'tenant_id' => $zone->tenant_id,
                'wifi_zone_id' => $zone->id,
                'plan_id' => $plan->id,
                'mikrotik_id' => null,
                'customer_id' => null,
                'public_token' => $identity['public_token'],
                'username' => $identity['username'],
                'password' => $model->getAttributes()['password'],
                'status' => $activate ? VoucherStatus::Active->value : VoucherStatus::Available->value,
                'activated_at' => $activate ? $now : null,
                'expires_at' => $activate ? $now->copy()->addSeconds((int) $plan->duration_seconds) : null,
                'price_amount' => $plan->price,
                'currency' => $plan->currency ?: config('limete.currency'),
                'sync_status' => 'pending',
                'sync_error' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    protected function makeUsername(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $max = strlen($alphabet) - 1;
        $suffix = '';
        for ($i = 0; $i < 8; $i++) {
            $suffix .= $alphabet[random_int(0, $max)];
        }

        return 'LW'.$suffix;
    }

    protected function makePassword(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghjkmnpqrstuvwxyz23456789';
        $max = strlen($alphabet) - 1;
        $password = '';
        for ($i = 0; $i < 10; $i++) {
            $password .= $alphabet[random_int(0, $max)];
        }

        return $password;
    }

    protected function makeToken(): string
    {
        return bin2hex(random_bytes(20));
    }

    private function isDuplicate(QueryException $exception): bool
    {
        $state = (string) ($exception->errorInfo[0] ?? '');

        return $state === '23000' || str_contains($exception->getMessage(), 'UNIQUE');
    }
}

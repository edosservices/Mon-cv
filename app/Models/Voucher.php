<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'wifi_zone_id', 'plan_id', 'mikrotik_id', 'customer_id',
        'public_token', 'username', 'password', 'status', 'activated_at',
        'expires_at', 'price_amount', 'currency', 'profile_snapshot', 'sync_status', 'sync_error',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'profile_snapshot' => 'array',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'price_amount' => 'decimal:2',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function wifiZone(): BelongsTo
    {
        return $this->belongsTo(WifiZone::class);
    }

    public function mikrotik(): BelongsTo
    {
        return $this->belongsTo(Mikrotik::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function saleItem(): HasOne
    {
        return $this->hasOne(SaleItem::class);
    }

    public function shareText(): string
    {
        $expires = $this->expires_at
            ? $this->expires_at->timezone(config('app.timezone'))->format('d/m/Y H:i')
            : 'après activation';

        $label = $this->wifiZone?->displayLabel() ?: 'WiFi';
        $lines = [$label];
        if ($this->wifiZone && $this->wifiZone->name !== $label) {
            $lines[] = $this->wifiZone->name;
        }
        $lines[] = 'Forfait : '.($this->plan->name ?? 'Forfait');
        $lines[] = 'Code : '.$this->username;
        $lines[] = 'Expiration : '.$expires;
        $lines[] = 'Ticket : '.route('tickets.public', $this->public_token);

        return implode("\n", $lines);
    }

    public function clientShareText(): string
    {
        $snapshot = is_array($this->profile_snapshot) ? $this->profile_snapshot : [];
        $lines = [
            'LIMETE WIFI',
            'Mon ticket WiFi',
            'Durée : '.($snapshot['validity_label'] ?? $this->plan?->durationLabel() ?? '—'),
        ];
        if (filled($snapshot['data_label'] ?? null)) {
            $lines[] = 'Data : '.$snapshot['data_label'];
        }
        if (filled($snapshot['rate_limit'] ?? null)) {
            $lines[] = 'Débit : '.$snapshot['rate_limit'];
        }
        $lines[] = 'Se connecter : '.route('tickets.public', $this->public_token);

        return implode("\n", $lines);
    }

    public function refreshExpiry(): void
    {
        if ($this->status === VoucherStatus::Active->value && $this->expires_at && $this->expires_at->isPast()) {
            $this->forceFill(['status' => VoucherStatus::Expired->value])->save();
        }
    }

    public function statusLabel(): string
    {
        return VoucherStatus::tryFrom((string) $this->status)?->label() ?? (string) $this->status;
    }

    public function remainingLabel(): string
    {
        if (! $this->expires_at) {
            return 'Démarre à l’activation';
        }

        $seconds = $this->expires_at->getTimestamp() - now()->getTimestamp();

        if ($seconds <= 0 || $this->status === VoucherStatus::Expired->value) {
            return 'Expiré';
        }

        $days = intdiv($seconds, 86400);
        $hours = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($days > 0) {
            return $days.'j '.$hours.'h '.$minutes.'min';
        }

        return $hours.'h '.$minutes.'min';
    }

    public function isSynced(): bool
    {
        return $this->sync_status === 'synced' && filled($this->mikrotik_id);
    }
}

<?php

namespace App\Models;

use App\Enums\VoucherStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Voucher extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'wifi_zone_id', 'plan_id', 'mikrotik_id', 'customer_id',
        'public_token', 'username', 'password', 'status', 'activated_at',
        'expires_at', 'price_amount', 'currency', 'sync_status', 'sync_error',
    ];

    protected $hidden = ['password'];

    protected function casts(): array
    {
        return [
            'password' => 'encrypted',
            'activated_at' => 'datetime',
            'expires_at' => 'datetime',
            'price_amount' => 'decimal:2',
        ];
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

    public function refreshExpiry(): void
    {
        if ($this->status === VoucherStatus::Active->value && $this->expires_at && $this->expires_at->isPast()) {
            $this->forceFill(['status' => VoucherStatus::Expired->value])->save();
        }
    }
}

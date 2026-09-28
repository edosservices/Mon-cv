<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use RuntimeException;

class Payment extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'payable_type', 'payable_id', 'amount', 'currency',
        'provider', 'internal_reference', 'transaction_reference', 'provider_reference',
        'status', 'paid_at', 'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class);
    }

    public function transitionTo(PaymentStatus $next): void
    {
        $current = PaymentStatus::from($this->status);
        if ($current === $next) {
            return;
        }

        if (! $current->canTransitionTo($next)) {
            throw new RuntimeException('Transition de paiement interdite.');
        }

        $this->status = $next->value;
        if ($next === PaymentStatus::Success) {
            $this->paid_at ??= now();
        }
    }
}

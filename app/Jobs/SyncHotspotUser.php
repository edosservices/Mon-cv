<?php

namespace App\Jobs;

use App\Models\Voucher;
use App\Services\Mikrotik\MikrotikService;
use App\Support\TenantManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncHotspotUser implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $voucherId) {}

    public function handle(MikrotikService $mikrotik): void
    {
        $voucher = Voucher::withoutGlobalScope('tenant')->find($this->voucherId);
        if (! $voucher) {
            return;
        }

        app(TenantManager::class)->set($voucher->tenant_id);
        $mikrotik->provisionVoucher($voucher);
    }
}

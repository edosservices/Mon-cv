<?php

namespace App\Support;

class TenantManager
{
    private ?int $tenantId = null;

    private bool $bypass = false;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }

    public function bypass(bool $bypass = true): void
    {
        $this->bypass = $bypass;
    }

    public function bypassed(): bool
    {
        return $this->bypass;
    }

    public function forget(): void
    {
        $this->tenantId = null;
        $this->bypass = false;
    }
}

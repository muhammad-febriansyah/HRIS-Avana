<?php

namespace App\Support;

final class TenantContext
{
    private ?int $tenantId = null;

    public function set(?int $tenantId): void
    {
        $this->tenantId = $tenantId;
    }

    public function forget(): void
    {
        $this->tenantId = null;
    }

    public function id(): ?int
    {
        return $this->tenantId;
    }
}

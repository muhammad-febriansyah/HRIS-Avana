<?php

namespace App\Support;

use App\Models\Tenant;
use App\Models\TenantAddon;
use App\Models\TenantGroup;

final class MultiCompanyEntitlement
{
    public function companyLimit(Tenant|TenantGroup $subject): int
    {
        $group = $subject instanceof Tenant ? $subject->tenantGroup : $subject;
        $addon = $group?->addons()
            ->where('code', TenantAddon::MULTI_COMPANY)
            ->currentlyActive()
            ->first();

        return max(1, (int) ($addon?->company_limit ?? 1));
    }

    public function usedCompanies(TenantGroup $group): int
    {
        return $group->tenants()
            ->whereNotIn('status', ['inactive'])
            ->count();
    }

    public function canCreate(Tenant $tenant): bool
    {
        $group = $tenant->tenantGroup;

        return $group !== null
            && $this->usedCompanies($group) < $this->companyLimit($group);
    }

    public function activeAddon(TenantGroup $group): ?TenantAddon
    {
        return $group->addons()
            ->where('code', TenantAddon::MULTI_COMPANY)
            ->currentlyActive()
            ->first();
    }
}

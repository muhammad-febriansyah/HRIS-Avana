<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * The tenant-level switches that govern how a salary change is written.
 */
final class SalarySettings
{
    /**
     * Whether a new salary version has to be approved before it pays.
     *
     * Read straight from the tenant row rather than through the signed-in
     * user's relation, so a setting changed moments ago is honoured instead of
     * whatever was loaded with the session.
     */
    public static function requiresApproval(int $tenantId): bool
    {
        return (bool) Tenant::query()->whereKey($tenantId)->value('require_salary_approval');
    }

    /**
     * The status a freshly written salary version starts in.
     */
    public static function statusFor(int $tenantId): string
    {
        return self::requiresApproval($tenantId) ? 'pending_approval' : 'active';
    }
}

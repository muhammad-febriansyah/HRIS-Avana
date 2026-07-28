<?php

namespace App\Support;

use App\Models\Tenant;
use App\Services\SubscriptionRenewalService;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the headcount limits a tenant's subscription package sells it:
 * `max_users`, `max_employees` and `max_branches`.
 *
 * The limits live on the tenant row, copied from the package when the client is
 * created or renewed ({@see SubscriptionRenewalService}), so a
 * super admin can still grant one client a special allowance without inventing a
 * package for it.
 *
 * A null or zero limit means "unlimited": clients created before the wizard
 * asked for quotas carry 0, and locking those out retroactively would break
 * working tenants rather than protect anything.
 */
final class TenantQuota
{
    /**
     * The countable resources, keyed by the slug callers pass in.
     *
     * @var array<string, array{column: string, relation: string, label: string}>
     */
    private const RESOURCES = [
        'users' => ['column' => 'max_users', 'relation' => 'users', 'label' => 'pengguna'],
        'employees' => ['column' => 'max_employees', 'relation' => 'employees', 'label' => 'karyawan'],
        'branches' => ['column' => 'max_branches', 'relation' => 'branches', 'label' => 'cabang'],
    ];

    /**
     * How many more of a resource the tenant may create, or null when unlimited.
     */
    public static function remaining(Tenant $tenant, string $resource): ?int
    {
        $limit = self::limit($tenant, $resource);

        if ($limit === null) {
            return null;
        }

        return max(0, $limit - self::used($tenant, $resource));
    }

    /**
     * Fail validation unless the tenant has room for `$adding` more.
     *
     * Passing a null tenant is a no-op: a platform super admin acts outside any
     * one client's subscription and is not metered by it.
     *
     * @param  string  $field  The form field the error is attached to.
     *
     * @throws ValidationException
     */
    public static function assertRoom(?Tenant $tenant, string $resource, int $adding = 1, string $field = 'quota'): void
    {
        if ($tenant === null) {
            return;
        }

        $remaining = self::remaining($tenant, $resource);

        if ($remaining === null || $remaining >= $adding) {
            return;
        }

        $config = self::config($resource);
        $limit = (int) self::limit($tenant, $resource);
        $used = self::used($tenant, $resource);

        throw ValidationException::withMessages([
            $field => $remaining === 0
                ? sprintf(
                    'Kuota %s pada paket langganan sudah penuh (%s dari %s). Naikkan paket untuk menambah %s.',
                    $config['label'],
                    number_format($used, 0, ',', '.'),
                    number_format($limit, 0, ',', '.'),
                    $config['label'],
                )
                : sprintf(
                    'Sisa kuota %s tinggal %s, tidak cukup untuk menambah %s sekaligus.',
                    $config['label'],
                    number_format($remaining, 0, ',', '.'),
                    number_format($adding, 0, ',', '.'),
                ),
        ]);
    }

    /**
     * The tenant's ceiling for a resource, null when it is not capped.
     */
    private static function limit(Tenant $tenant, string $resource): ?int
    {
        $limit = $tenant->{self::config($resource)['column']};

        return $limit === null || (int) $limit <= 0 ? null : (int) $limit;
    }

    /**
     * How many of the resource the tenant already holds.
     *
     * "Pengguna" counts staff logins only. An employee's ESS account is a `users`
     * row too, but it is already paid for by the `max_employees` line — counting
     * it twice would make a 50-karyawan package unusable at 25 pengguna.
     */
    public static function used(Tenant $tenant, string $resource): int
    {
        $query = $tenant->{self::config($resource)['relation']}();

        if ($resource === 'users') {
            $query->whereDoesntHave('employee');
        }

        return (int) $query->count();
    }

    /**
     * @return array{column: string, relation: string, label: string}
     */
    private static function config(string $resource): array
    {
        return self::RESOURCES[$resource]
            ?? throw new \InvalidArgumentException("Unknown tenant quota resource [{$resource}].");
    }
}

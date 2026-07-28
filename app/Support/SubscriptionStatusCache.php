<?php

namespace App\Support;

/**
 * Request-scoped memo behind {@see SubscriptionStatus}. The lock gate asks for a
 * tenant's standing on every request (and the Inertia banner asks again), so the
 * answer is resolved once and held here.
 *
 * A container-scoped object rather than a static array: the container is rebuilt
 * per request (and per test), so the memo can never outlive the end date it was
 * derived from.
 */
final class SubscriptionStatusCache
{
    /**
     * @var array<int, array{end_date: string, end_date_label: string, days_left: int, level: string, package: string|null}|null>
     */
    private array $entries = [];

    public function has(int $tenantId): bool
    {
        return array_key_exists($tenantId, $this->entries);
    }

    /**
     * @return array{end_date: string, end_date_label: string, days_left: int, level: string, package: string|null}|null
     */
    public function get(int $tenantId): ?array
    {
        return $this->entries[$tenantId] ?? null;
    }

    /**
     * @param  array{end_date: string, end_date_label: string, days_left: int, level: string, package: string|null}|null  $status
     */
    public function put(int $tenantId, ?array $status): void
    {
        $this->entries[$tenantId] = $status;
    }

    public function forget(?int $tenantId = null): void
    {
        if ($tenantId === null) {
            $this->entries = [];

            return;
        }

        unset($this->entries[$tenantId]);
    }
}

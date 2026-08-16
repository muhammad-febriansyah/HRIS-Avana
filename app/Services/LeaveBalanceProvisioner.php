<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Creates the yearly `leave_balances` rows every leave feature reads from.
 *
 * Nothing used to write those rows outside the demo seeders, so a real tenant
 * had none: the mobile Cuti screen said "Belum ada data saldo", and
 * {@see LeaveApproval::finalize()} — which only decrements a balance that
 * already exists — quietly deducted nothing, leaving quotas effectively
 * unlimited.
 *
 * Only quota-owning types get a row. A sub-type never has a balance of its own
 * ({@see LeaveType::quotaOwnerId()}), so its days are counted against the
 * parent's row instead.
 *
 * Provisioning never overwrites a row that already exists: an HR-adjusted quota
 * must survive the next run. Recomputing what was already taken is what
 * {@see self::syncUsed()} is for.
 */
class LeaveBalanceProvisioner
{
    /**
     * Create the missing balance rows for every active employee of a tenant.
     *
     * @return int how many rows were created
     */
    public static function forTenant(int $tenantId, int $year): int
    {
        $types = self::quotaOwners($tenantId);

        if ($types->isEmpty()) {
            return 0;
        }

        $employees = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->pluck('id');

        if ($employees->isEmpty()) {
            return 0;
        }

        $usedByEmployee = self::usedDays($tenantId, $year, $employees->all());
        $existing = self::existingKeys($tenantId, $year, $employees->all());
        $created = 0;

        foreach ($employees as $employeeId) {
            foreach ($types as $type) {
                if (isset($existing[$employeeId.':'.$type->getKey()])) {
                    continue;
                }

                self::write(
                    $tenantId,
                    (int) $employeeId,
                    $type,
                    $year,
                    (float) ($usedByEmployee[$employeeId][$type->getKey()] ?? 0),
                );

                $created++;
            }
        }

        return $created;
    }

    /**
     * Create the missing balance rows for one employee — used when somebody is
     * hired mid-year, so they do not have to wait for the next tenant-wide run.
     *
     * @return int how many rows were created
     */
    public static function forEmployee(Employee $employee, int $year): int
    {
        $tenantId = (int) $employee->tenant_id;
        $employeeId = (int) $employee->getKey();
        $usedByEmployee = self::usedDays($tenantId, $year, [$employeeId]);
        $existing = self::existingKeys($tenantId, $year, [$employeeId]);
        $created = 0;

        foreach (self::quotaOwners($tenantId) as $type) {
            if (isset($existing[$employeeId.':'.$type->getKey()])) {
                continue;
            }

            self::write(
                $tenantId,
                $employeeId,
                $type,
                $year,
                (float) ($usedByEmployee[$employeeId][$type->getKey()] ?? 0),
            );

            $created++;
        }

        return $created;
    }

    /**
     * Roll last year's leftovers into this year's quota, capped at `$maxDays`
     * when the tenant limits how much may be carried.
     *
     * Written as "base quota + carried" rather than "+= carried" so running it
     * twice lands on the same number instead of stacking.
     *
     * @return int how many rows were adjusted
     */
    public static function carryOver(int $tenantId, int $fromYear, int $toYear, ?float $maxDays = null): int
    {
        $previous = LeaveBalance::forTenant($tenantId)
            ->where('year', $fromYear)
            ->get()
            ->keyBy(fn (LeaveBalance $balance): string => $balance->employee_id.':'.$balance->leave_type_id);

        if ($previous->isEmpty()) {
            return 0;
        }

        $baseQuota = self::quotaOwners($tenantId)
            ->mapWithKeys(fn (LeaveType $type): array => [$type->getKey() => (float) $type->default_quota]);

        $adjusted = 0;

        LeaveBalance::forTenant($tenantId)
            ->where('year', $toYear)
            ->get()
            ->each(function (LeaveBalance $balance) use ($previous, $baseQuota, $maxDays, &$adjusted): void {
                $key = $balance->employee_id.':'.$balance->leave_type_id;
                $leftover = (float) ($previous->get($key)?->remaining ?? 0);
                $base = $baseQuota->get($balance->leave_type_id);

                // A type that no longer owns a quota has no base to rebuild
                // from; leaving it untouched beats guessing.
                if ($base === null || $leftover <= 0) {
                    return;
                }

                $carried = $maxDays === null ? $leftover : min($leftover, $maxDays);
                $quota = $base + $carried;
                $used = (float) $balance->used;

                if ((float) $balance->quota === $quota) {
                    return;
                }

                $balance->update([
                    'quota' => $quota,
                    'remaining' => max(0, $quota - $used),
                ]);

                $adjusted++;
            });

        return $adjusted;
    }

    /**
     * Recompute `used` (and therefore `remaining`) from the approved requests
     * on record. Repairs rows that drifted — leave approved while no balance
     * existed yet, or a request deleted after the fact.
     *
     * @return int how many rows were corrected
     */
    public static function syncUsed(int $tenantId, int $year): int
    {
        $balances = LeaveBalance::forTenant($tenantId)->where('year', $year)->get();

        if ($balances->isEmpty()) {
            return 0;
        }

        $used = self::usedDays($tenantId, $year, $balances->pluck('employee_id')->unique()->all());
        $corrected = 0;

        foreach ($balances as $balance) {
            $actual = (float) ($used[$balance->employee_id][$balance->leave_type_id] ?? 0);

            if ((float) $balance->used === $actual) {
                continue;
            }

            $balance->update([
                'used' => $actual,
                'remaining' => max(0, (float) $balance->quota - $actual),
            ]);

            $corrected++;
        }

        return $corrected;
    }

    /**
     * The tenant's active quota-owning types: roots that carry days. A root
     * with no quota (a container for unpaid leave, say) gets no balance row —
     * a counter that starts and stays at zero only adds noise.
     *
     * @return Collection<int, LeaveType>
     */
    private static function quotaOwners(int $tenantId): Collection
    {
        return LeaveType::forTenant($tenantId)
            ->roots()
            ->where('status', 'active')
            ->where('default_quota', '>', 0)
            ->get();
    }

    /**
     * Approved days in the year per employee, bucketed by QUOTA OWNER — a
     * sub-type's days land on its parent's row.
     *
     * @param  array<int, mixed>  $employeeIds
     * @return array<int, array<int, float>> employee id => [type id => days]
     */
    private static function usedDays(int $tenantId, int $year, array $employeeIds): array
    {
        if ($employeeIds === []) {
            return [];
        }

        $ownerOf = LeaveType::forTenant($tenantId)
            ->get(['id', 'parent_id'])
            ->mapWithKeys(fn (LeaveType $type): array => [
                (int) $type->getKey() => (int) ($type->parent_id ?? $type->getKey()),
            ]);

        $rows = LeaveRequest::forTenant($tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'approved')
            ->whereYear('start_date', $year)
            ->selectRaw('employee_id, leave_type_id, SUM(total_days) as days')
            ->groupBy('employee_id', 'leave_type_id')
            ->get();

        $used = [];

        foreach ($rows as $row) {
            $ownerId = $ownerOf->get((int) $row->leave_type_id, (int) $row->leave_type_id);
            $employeeId = (int) $row->employee_id;

            $used[$employeeId][$ownerId] = ($used[$employeeId][$ownerId] ?? 0) + (float) $row->days;
        }

        return $used;
    }

    /**
     * Lookup of the balance rows already on file, as "employeeId:typeId".
     *
     * @param  array<int, mixed>  $employeeIds
     * @return array<string, true>
     */
    private static function existingKeys(int $tenantId, int $year, array $employeeIds): array
    {
        return LeaveBalance::forTenant($tenantId)
            ->where('year', $year)
            ->whereIn('employee_id', $employeeIds)
            ->get(['employee_id', 'leave_type_id'])
            ->mapWithKeys(fn (LeaveBalance $balance): array => [
                $balance->employee_id.':'.$balance->leave_type_id => true,
            ])
            ->all();
    }

    /**
     * Insert one balance row, ignoring a race with a concurrent run.
     */
    private static function write(int $tenantId, int $employeeId, LeaveType $type, int $year, float $used): void
    {
        $quota = (float) $type->default_quota;

        DB::table('leave_balances')->insertOrIgnore([
            'tenant_id' => $tenantId,
            'employee_id' => $employeeId,
            'leave_type_id' => $type->getKey(),
            'year' => $year,
            'quota' => $quota,
            'used' => $used,
            'remaining' => max(0, $quota - $used),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}

<?php

namespace App\Support;

use App\Models\Employee;
use Illuminate\Validation\Rule;

/**
 * Rules for the identity numbers that may name only one employee.
 *
 * A KTP number belongs to exactly one person, so two employee rows carrying the
 * same NIK are always a data-entry mistake — the pair then splits that person's
 * payroll, tax and attendance history between two records. The rule lives here
 * because the same number can be typed from four places: the admin form, the
 * bulk import, the ESS profile and the mobile profile.
 */
final class EmployeeIdentity
{
    /**
     * Validation rules for an employee's NIK.
     *
     * Uniqueness is per tenant, not global: two companies on the platform each
     * hire the same person often enough, and one tenant must not learn that the
     * other has them on the books.
     *
     * @return array<int, mixed>
     */
    public static function nikRules(int $tenantId, ?int $ignoreEmployeeId = null, bool $required = true): array
    {
        $unique = Rule::unique('employees', 'nik')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at');

        if ($ignoreEmployeeId !== null) {
            $unique->ignore($ignoreEmployeeId);
        }

        return [$required ? 'required' : 'nullable', 'digits:16', $unique];
    }

    /**
     * The employee already holding a NIK within the tenant, if any.
     */
    public static function employeeHolding(string $nik, int $tenantId, ?int $ignoreEmployeeId = null): ?Employee
    {
        return Employee::forTenant($tenantId)
            ->where('nik', $nik)
            ->when($ignoreEmployeeId !== null, fn ($query) => $query->whereKeyNot($ignoreEmployeeId))
            ->first(['id', 'full_name', 'employee_number']);
    }

    /**
     * The message shown when a NIK is already on another employee.
     */
    public static function takenMessage(Employee $holder): string
    {
        return 'NIK ini sudah dipakai karyawan lain: '.$holder->full_name.
            ($holder->employee_number !== null ? ' ('.$holder->employee_number.')' : '').'.';
    }
}

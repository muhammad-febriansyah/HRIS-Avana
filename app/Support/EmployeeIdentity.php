<?php

namespace App\Support;

use App\Models\Employee;
use Closure;

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
        // A closure rather than Rule::unique: the NIK is encrypted at rest and
        // its ciphertext differs on every write, so a unique rule against the
        // column can never find the duplicate it is looking for. The lookup
        // goes through the keyed hash instead — and naming the colleague who
        // already holds the number beats "NIK sudah dipakai".
        $unique = static function (string $attribute, mixed $value, Closure $fail) use ($tenantId, $ignoreEmployeeId): void {
            if (! is_string($value) || $value === '') {
                return;
            }

            $holder = self::employeeHolding($value, $tenantId, $ignoreEmployeeId);

            if ($holder !== null) {
                $fail(self::takenMessage($holder));
            }
        };

        return [$required ? 'required' : 'nullable', 'digits:16', $unique];
    }

    /**
     * The employee already holding a NIK within the tenant, if any.
     */
    public static function employeeHolding(string $nik, int $tenantId, ?int $ignoreEmployeeId = null): ?Employee
    {
        return Employee::forTenant($tenantId)
            ->where('nik_hash', Pii::hash($nik))
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

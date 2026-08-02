<?php

namespace App\Support;

use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryGrade;
use App\Models\SalaryMasterComponent;
use App\Models\UmrRate;

/**
 * Judges a salary against the two references the payroll setup keeps: the
 * regional minimum wage (UMR) for where the employee works, and the band their
 * Struktur & Skala Upah grade allows.
 *
 * Both are advisory — a salary below UMR is a finding, not a save error, since
 * legitimate cases exist (probation on a shorter week, a mid-year UMR revision
 * not yet entered). The point is that the number is checked and the verdict is
 * visible where the salary is set.
 */
final class SalaryCompliance
{
    /**
     * The fixed monthly wage an employee is paid: Gaji Pokok plus every fixed
     * allowance, with attendance-variable components (per present day, per
     * overtime hour) left out. This is the figure UMR is measured against.
     *
     * `effective_from` is the date the employee's own Gaji Pokok row took
     * effect, or null when the figure still comes from the Master Gaji template.
     *
     * @return array{basic: float, allowances: float, total: float, effective_from: string|null}
     */
    public static function monthlyWage(Employee $employee, int $tenantId): array
    {
        $basic = 0.0;
        $allowances = 0.0;
        $effectiveFrom = null;
        $handled = [];

        $salaryComponents = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->effectiveOn()
            ->with('component')
            ->get();

        foreach ($salaryComponents as $row) {
            $component = $row->component;

            if ($component === null || $component->type === 'deduction' || $component->component_group === 'potongan') {
                continue;
            }

            $handled[$component->id] = true;

            if ($component->code === 'BASIC') {
                $basic += (float) $row->amount;
                $effectiveFrom = $row->effective_start_date?->toDateString();
            } elseif (! in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true)) {
                $allowances += (float) $row->amount;
            }
        }

        if ($employee->salary_master_id !== null) {
            $masterComponents = SalaryMasterComponent::query()
                ->where('salary_master_id', $employee->salary_master_id)
                ->where('included', true)
                ->with('component')
                ->get();

            foreach ($masterComponents as $row) {
                $component = $row->component;

                if ($component === null
                    || isset($handled[$component->id])
                    || $component->type === 'deduction'
                    || $component->component_group === 'potongan'
                    || in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true)) {
                    continue;
                }

                if ($component->code === 'BASIC') {
                    $basic += (float) $row->amount;
                } else {
                    $allowances += (float) $row->amount;
                }
            }
        }

        return [
            'basic' => $basic,
            'allowances' => $allowances,
            'total' => $basic + $allowances,
            'effective_from' => $effectiveFrom,
        ];
    }

    /**
     * The UMR that applies to an employee: their branch's rate for the year,
     * falling back to the tenant-wide default row, and to the most recent year
     * when the current one has not been entered yet.
     *
     * @return array{amount: float, region: string|null, year: int}|null
     */
    public static function umrFor(Employee $employee, int $tenantId, ?int $year = null): ?array
    {
        $year ??= (int) now()->year;

        $rate = UmrRate::forTenant($tenantId)
            ->where(fn ($query) => $query->where('branch_id', $employee->branch_id)->orWhereNull('branch_id'))
            ->where('year', '<=', $year)
            // A branch-specific row beats the tenant default; a newer year beats
            // an older one.
            ->orderByRaw('branch_id IS NULL')
            ->orderByDesc('year')
            ->first();

        if ($rate === null) {
            return null;
        }

        return [
            'amount' => (float) $rate->amount,
            'region' => $rate->region,
            'year' => (int) $rate->year,
        ];
    }

    /**
     * Compare a monthly wage against the UMR and the grade band.
     *
     * @param  array{amount: float, region: string|null, year: int}|null  $umr
     * @return array{
     *     umr_status: string,
     *     umr_label: string,
     *     umr_amount: float|null,
     *     umr_region: string|null,
     *     grade_status: string,
     *     grade_label: string,
     *     grade_min: float|null,
     *     grade_max: float|null,
     * }
     */
    public static function verdict(float $monthlyWage, ?array $umr, ?SalaryGrade $grade): array
    {
        return [
            ...self::umrVerdict($monthlyWage, $umr),
            ...self::gradeVerdict($monthlyWage, $grade),
        ];
    }

    /**
     * @param  array{amount: float, region: string|null, year: int}|null  $umr
     * @return array{umr_status: string, umr_label: string, umr_amount: float|null, umr_region: string|null}
     */
    private static function umrVerdict(float $monthlyWage, ?array $umr): array
    {
        if ($umr === null || $umr['amount'] <= 0.0) {
            return [
                'umr_status' => 'unknown',
                'umr_label' => 'UMR belum diatur',
                'umr_amount' => null,
                'umr_region' => null,
            ];
        }

        $status = match (true) {
            $monthlyWage < $umr['amount'] => 'below',
            $monthlyWage > $umr['amount'] => 'above',
            default => 'equal',
        };

        return [
            'umr_status' => $status,
            'umr_label' => match ($status) {
                'below' => 'Di bawah UMR',
                'above' => 'Di atas UMR',
                default => 'Sesuai UMR',
            },
            'umr_amount' => $umr['amount'],
            'umr_region' => $umr['region'],
        ];
    }

    /**
     * @return array{grade_status: string, grade_label: string, grade_min: float|null, grade_max: float|null}
     */
    private static function gradeVerdict(float $monthlyWage, ?SalaryGrade $grade): array
    {
        if ($grade === null) {
            return [
                'grade_status' => 'unknown',
                'grade_label' => 'Grade belum diatur',
                'grade_min' => null,
                'grade_max' => null,
            ];
        }

        $min = (float) $grade->min_salary;
        $max = (float) $grade->max_salary;

        $status = match (true) {
            $min > 0 && $monthlyWage < $min => 'below_min',
            $max > 0 && $monthlyWage > $max => 'above_max',
            default => 'within',
        };

        return [
            'grade_status' => $status,
            'grade_label' => match ($status) {
                'below_min' => 'Di bawah rentang grade',
                'above_max' => 'Di atas rentang grade',
                default => 'Sesuai rentang grade',
            },
            'grade_min' => $min,
            'grade_max' => $max,
        ];
    }
}

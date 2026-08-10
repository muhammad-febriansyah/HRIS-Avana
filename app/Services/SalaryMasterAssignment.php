<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryMaster;
use App\Models\SalaryMasterComponent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Applying a Master Gaji to employees.
 *
 * Pointing an employee at a template is not enough: payroll would then read the
 * template live, so editing it later would silently re-price everyone assigned
 * from it — the documentation is explicit that a change to Master Gaji must not
 * reach salaries already set up. Assignment therefore copies the template's
 * nominals onto each employee as their own dated salary rows.
 */
final class SalaryMasterAssignment
{
    /**
     * @param  iterable<Employee>  $employees
     * @return array{written: int, kept: int}
     */
    public static function apply(
        int $tenantId,
        SalaryMaster $master,
        iterable $employees,
        Carbon $from,
        ?string $reason = null,
        ?int $actorId = null,
        bool $overwriteOwnFigures = false,
        string $status = 'active',
    ): array {
        $components = self::templateComponents($tenantId, (int) $master->id);

        $written = 0;
        $kept = 0;

        DB::transaction(function () use ($employees, $components, $master, $from, $reason, $actorId, $overwriteOwnFigures, $status, $tenantId, &$written, &$kept): void {
            foreach ($employees as $employee) {
                $employee->update(['salary_master_id' => $master->id]);

                $own = EmployeeSalaryComponent::forTenant($tenantId)
                    ->where('employee_id', $employee->id)
                    ->inForce()
                    ->effectiveOn($from)
                    ->pluck('payroll_component_id')
                    ->flip();

                foreach ($components as $componentId => $amount) {
                    // An employee already carrying their own figure for this
                    // component is an exception someone set deliberately; the
                    // run keeps it unless told otherwise.
                    if (! $overwriteOwnFigures && $own->has($componentId)) {
                        $kept++;

                        continue;
                    }

                    EmployeeSalaryWriter::record(
                        $tenantId,
                        (int) $employee->id,
                        (int) $componentId,
                        (float) $amount,
                        $from,
                        $reason ?? 'Penetapan Master Gaji '.$master->code,
                        $actorId,
                        (int) $master->id,
                        $status,
                    );
                    $written++;
                }
            }
        });

        return ['written' => $written, 'kept' => $kept];
    }

    /**
     * The fixed component nominals a template pays, keyed by component id.
     *
     * Variable components (per present day, per overtime hour) are left out:
     * their value comes from attendance, so copying a figure onto the employee
     * would freeze what should be computed.
     *
     * @return array<int, float>
     */
    public static function templateComponents(int $tenantId, int $masterId): array
    {
        return SalaryMasterComponent::query()
            ->where('salary_master_id', $masterId)
            ->where('included', true)
            ->with('component')
            ->get()
            ->filter(fn (SalaryMasterComponent $row): bool => $row->component !== null
                && (int) $row->component->tenant_id === $tenantId
                && in_array($row->component->calc_basis, [null, 'fixed'], true)
                && ($row->component->status === null || $row->component->status === 'active'))
            ->mapWithKeys(fn (SalaryMasterComponent $row): array => [(int) $row->payroll_component_id => (float) $row->amount])
            ->all();
    }
}

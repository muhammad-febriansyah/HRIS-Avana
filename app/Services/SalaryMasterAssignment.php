<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\SalaryChangeSet;
use App\Models\SalaryMaster;
use App\Models\SalaryMasterComponent;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        // One id for the whole run, so its change sets can be reviewed and
        // signed off as the single decision they are.
        $batchId = (string) Str::ulid();

        DB::transaction(function () use ($employees, $components, $master, $from, $reason, $actorId, $overwriteOwnFigures, $status, $tenantId, $batchId, &$written, &$kept): void {
            foreach ($employees as $employee) {
                $employee = Employee::forTenant($tenantId)
                    ->whereKey($employee->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $changeSet = SalaryChangeSet::create([
                    'tenant_id' => $tenantId,
                    'employee_id' => $employee->id,
                    'salary_master_id' => $master->id,
                    'batch_id' => $batchId,
                    'change_type' => 'master_assignment',
                    'existing_strategy' => $overwriteOwnFigures ? 'overwrite' : 'skip',
                    'effective_start_date' => $from->toDateString(),
                    'status' => $status,
                    'reason' => $reason ?? 'Penetapan Master Gaji '.$master->code,
                    'created_by' => $actorId,
                ]);

                $own = EmployeeSalaryComponent::forTenant($tenantId)
                    ->where('employee_id', $employee->id)
                    ->where('source_type', 'employee_override')
                    ->inForce()
                    ->effectiveOn($from)
                    ->pluck('payroll_component_id')
                    ->flip();

                if ($status === 'active') {
                    self::retireExistingRows(
                        $tenantId,
                        (int) $employee->id,
                        $from,
                        $overwriteOwnFigures,
                    );
                }

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
                        $changeSet->id,
                        'master_copy',
                    );
                    $written++;
                }

                if ($status === 'active' && $from->lte(today())) {
                    $employee->update(['salary_master_id' => $master->id]);
                }
            }
        });

        return ['written' => $written, 'kept' => $kept];
    }

    public static function approveChangeSet(SalaryChangeSet $changeSet, int $approverId): void
    {
        $from = Carbon::parse($changeSet->effective_start_date ?? now())->startOfDay();

        Employee::forTenant((int) $changeSet->tenant_id)
            ->whereKey($changeSet->employee_id)
            ->lockForUpdate()
            ->firstOrFail();

        if ($changeSet->change_type === 'master_assignment') {
            self::retireExistingRows(
                (int) $changeSet->tenant_id,
                (int) $changeSet->employee_id,
                $from,
                $changeSet->existing_strategy === 'overwrite',
            );
        }

        $changeSet->components()
            ->where('status', 'pending_approval')
            ->orderBy('id')
            ->lockForUpdate()
            ->get()
            ->each(fn (EmployeeSalaryComponent $component) => EmployeeSalaryWriter::approve($component, $approverId));

        $changeSet->update([
            'status' => 'active',
            'approved_by' => $approverId,
            'approved_at' => now(),
        ]);

        if ($from->lte(today())) {
            $changeSet->employee()->update(['salary_master_id' => $changeSet->salary_master_id]);
        }
    }

    public static function effectiveMasterId(Employee $employee, CarbonInterface|string|null $on = null): ?int
    {
        $date = $on instanceof CarbonInterface ? $on->toDateString() : ($on ?? now()->toDateString());

        $changeSet = SalaryChangeSet::forTenant((int) $employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->whereNull('effective_start_date')
                ->orWhereDate('effective_start_date', '<=', $date))
            ->orderByDesc('effective_start_date')
            ->orderByDesc('id')
            ->first(['salary_master_id']);

        return $changeSet === null
            ? ($employee->salary_master_id === null ? null : (int) $employee->salary_master_id)
            : ($changeSet->salary_master_id === null ? null : (int) $changeSet->salary_master_id);
    }

    public static function retireExistingRows(
        int $tenantId,
        int $employeeId,
        Carbon $from,
        bool $overwriteOwnFigures,
    ): void {
        $scope = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employeeId)
            ->inForce()
            ->effectiveOn($from)
            ->when(! $overwriteOwnFigures, fn ($query) => $query->where('source_type', 'master_copy'));

        (clone $scope)
            ->whereDate('effective_start_date', $from->toDateString())
            ->update(['status' => 'cancelled']);

        (clone $scope)
            ->where(fn ($query) => $query
                ->whereNull('effective_start_date')
                ->orWhereDate('effective_start_date', '<', $from->toDateString()))
            ->update(['effective_end_date' => $from->copy()->subDay()->toDateString()]);
    }

    /**
     * Every active component rate a template pays, keyed by component id.
     * Variable values are unit rates, not payroll totals. They are copied too,
     * so a later Master Gaji edit cannot re-price an earlier assignment.
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
                && ($row->component->status === null || $row->component->status === 'active'))
            ->mapWithKeys(fn (SalaryMasterComponent $row): array => [(int) $row->payroll_component_id => (float) $row->amount])
            ->all();
    }
}

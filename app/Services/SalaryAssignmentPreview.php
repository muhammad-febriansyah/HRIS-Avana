<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\SalaryChangeSet;
use App\Models\SalaryMaster;
use App\Models\SalaryMasterComponent;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

final class SalaryAssignmentPreview
{
    /**
     * Build the exact result that a mass assignment would produce and sign the
     * inputs used to build it. Apply recomputes this payload under row locks;
     * a changed template, employee, or salary row therefore forces a preview.
     *
     * @param  Collection<int, Employee>  $employees
     * @return array{rows: list<array<string, mixed>>, employee_ids: list<int>, token: string}
     */
    public function build(
        int $tenantId,
        SalaryMaster $master,
        Collection $employees,
        Carbon $from,
        bool $overwriteOwnFigures,
    ): array {
        $employees = $employees->sortBy('id')->values();
        $employeeIds = $employees->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $templateRates = SalaryMasterAssignment::templateComponents($tenantId, (int) $master->id);
        $components = PayrollComponent::forTenant($tenantId)
            ->get(['id', 'type', 'component_group', 'calc_basis', 'status', 'updated_at'])
            ->keyBy('id');
        $fixedTemplate = collect($templateRates)
            ->filter(fn (float $amount, int $componentId): bool => in_array($components[$componentId]?->calc_basis, [null, 'fixed'], true))
            ->all();
        $deductionIds = PayrollComponent::forTenant($tenantId)
            ->where(fn ($query) => $query->where('type', 'deduction')->orWhere('component_group', 'potongan'))
            ->pluck('id')
            ->mapWithKeys(fn (int $id): array => [$id => true])
            ->all();

        $salaryRows = $employeeIds === []
            ? collect()
            : EmployeeSalaryComponent::forTenant($tenantId)
                ->whereIn('employee_id', $employeeIds)
                ->inForce()
                ->effectiveOn($from)
                ->with('component:id,calc_basis,status')
                ->orderBy('id')
                ->get()
                ->groupBy('employee_id');

        $latestAssignments = SalaryChangeSet::forTenant($tenantId)
            ->whereIn('employee_id', $employeeIds)
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->whereNull('effective_start_date')
                ->orWhereDate('effective_start_date', '<=', $from->toDateString()))
            ->orderBy('employee_id')
            ->orderByDesc('effective_start_date')
            ->orderByDesc('id')
            ->get(['employee_id', 'salary_master_id'])
            ->groupBy('employee_id')
            ->map(fn (Collection $assignments): ?int => $assignments->first()->salary_master_id === null
                ? null
                : (int) $assignments->first()->salary_master_id);
        $currentMasterIds = $employees->mapWithKeys(function (Employee $employee) use ($latestAssignments): array {
            $masterId = $latestAssignments->has($employee->id)
                ? $latestAssignments->get($employee->id)
                : $employee->salary_master_id;

            return [(int) $employee->id => $masterId === null ? null : (int) $masterId];
        });
        $currentTemplateRates = SalaryMasterComponent::query()
            ->whereIn('salary_master_id', $currentMasterIds->filter()->unique()->values())
            ->where('included', true)
            ->with('component:id,tenant_id,calc_basis,status')
            ->get()
            ->filter(fn (SalaryMasterComponent $row): bool => $row->component !== null
                && (int) $row->component->tenant_id === $tenantId
                && in_array($row->component->calc_basis, [null, 'fixed'], true)
                && in_array($row->component->status, [null, 'active'], true))
            ->groupBy('salary_master_id')
            ->map(fn (Collection $rates): array => $rates->mapWithKeys(fn (SalaryMasterComponent $rate): array => [
                (int) $rate->payroll_component_id => (float) $rate->amount,
            ])->all());
        $masterCodes = SalaryMaster::forTenant($tenantId)
            ->whereIn('id', $currentMasterIds->filter()->unique()->values())
            ->pluck('code', 'id');
        $rows = $employees->map(function (Employee $employee) use (
            $salaryRows,
            $fixedTemplate,
            $deductionIds,
            $overwriteOwnFigures,
            $masterCodes,
            $currentMasterIds,
            $currentTemplateRates,
        ): array {
            /** @var Collection<int, EmployeeSalaryComponent> $effectiveRows */
            $effectiveRows = $salaryRows->get($employee->id, collect());
            $fixedRows = $effectiveRows->filter(fn (EmployeeSalaryComponent $row): bool => $row->component !== null
                && in_array($row->component->calc_basis, [null, 'fixed'], true)
                && in_array($row->component->status, [null, 'active'], true));
            $overrides = $fixedRows->where('source_type', 'employee_override');
            $effectiveMasterId = $currentMasterIds->get($employee->id);
            $currentAmounts = $effectiveMasterId === null
                ? []
                : $currentTemplateRates->get($effectiveMasterId, []);

            foreach ($fixedRows as $fixedRow) {
                $currentAmounts[(int) $fixedRow->payroll_component_id] = (float) $fixedRow->amount;
            }

            $projectedAmounts = $fixedTemplate;

            if (! $overwriteOwnFigures) {
                foreach ($overrides as $override) {
                    $projectedAmounts[(int) $override->payroll_component_id] = (float) $override->amount;
                }
            }

            return [
                'id' => (int) $employee->id,
                'name' => $employee->full_name,
                'nik' => $employee->employee_number,
                'position' => $employee->position?->name,
                'branch' => $employee->branch?->name,
                'current_master' => $effectiveMasterId === null ? null : $masterCodes->get($effectiveMasterId),
                'current_total' => $this->netTotal($currentAmounts, $deductionIds),
                'template_total' => $this->netTotal($projectedAmounts, $deductionIds),
                'has_own_figures' => $overrides->isNotEmpty(),
                'override_count' => $overrides->count(),
            ];
        })->values()->all();

        $fingerprint = [
            'tenant_id' => $tenantId,
            'master' => [
                'id' => (int) $master->id,
                'active' => (bool) $master->is_active,
                'updated_at' => $master->updated_at?->format('Y-m-d H:i:s.u'),
                'rates' => collect($templateRates)->map(fn (float $amount, int $id): array => [
                    'component_id' => $id,
                    'amount' => number_format($amount, 2, '.', ''),
                    'basis' => $components[$id]?->calc_basis,
                    'status' => $components[$id]?->status,
                    'updated_at' => $components[$id]?->updated_at?->format('Y-m-d H:i:s.u'),
                ])->values()->all(),
            ],
            'effective_start_date' => $from->toDateString(),
            'existing' => $overwriteOwnFigures ? 'overwrite' : 'skip',
            'employees' => $employees->map(fn (Employee $employee): array => [
                'id' => (int) $employee->id,
                'status' => $employee->status,
                'updated_at' => $employee->updated_at?->format('Y-m-d H:i:s.u'),
                'salary_rows' => collect($salaryRows->get($employee->id, collect()))->map(fn (EmployeeSalaryComponent $row): array => [
                    'id' => (int) $row->id,
                    'component_id' => (int) $row->payroll_component_id,
                    'amount' => (string) $row->amount,
                    'source' => $row->source_type,
                    'status' => $row->status,
                    'start' => $row->effective_start_date?->toDateString(),
                    'end' => $row->effective_end_date?->toDateString(),
                    'updated_at' => $row->updated_at?->format('Y-m-d H:i:s.u'),
                ])->all(),
            ])->all(),
            'result' => $rows,
        ];

        $json = json_encode($fingerprint, JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        return [
            'rows' => $rows,
            'employee_ids' => $employeeIds,
            'token' => hash_hmac('sha256', $json, (string) config('app.key')),
        ];
    }

    /**
     * @param  array<int, float>  $amounts
     * @param  array<int, true>  $deductionIds
     */
    private function netTotal(array $amounts, array $deductionIds): float
    {
        $total = 0.0;

        foreach ($amounts as $componentId => $amount) {
            $total += isset($deductionIds[$componentId]) ? -$amount : $amount;
        }

        return $total;
    }
}

<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Requests\Avana\StoreEmployeeSalaryRequest;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\SalaryChangeSet;
use App\Models\SalaryMaster;
use App\Models\SalaryMasterComponent;
use App\Models\User;
use App\Services\EmployeeSalaryWriter;
use App\Services\SalaryMasterAssignment;
use App\Support\SalaryCompliance;
use App\Support\SalaryPeriodLock;
use App\Support\SalarySettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Gaji Karyawan": setting up one employee's salary.
 *
 * The documented individual flow is pick the employee, pick an existing Master
 * Gaji, let the template's nominals fill the form, adjust the ones that differ
 * for this person, then date the change — not build a fresh template per
 * employee. Every component in the template is editable here, so a different
 * transport or meal allowance no longer needs a Master Gaji of its own.
 */
class EmployeeSalaryController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'payroll';

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $employee = $this->selectedEmployee($request, $tenantId);
        $selectedMasterId = $employee === null
            ? null
            : $this->selectedMasterId($request, $employee, $tenantId);

        return Inertia::render('avana/payroll-master-gaji/index', [
            'tab' => 'karyawan',
            'employee' => $employee === null ? null : [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'nik' => $employee->employee_number,
                'salary_master_id' => $selectedMasterId,
                'position' => $employee->position?->name,
                'branch' => $employee->branch?->name,
            ],
            'rows' => $employee === null ? [] : $this->rows($employee, $tenantId, $selectedMasterId),
            'compliance' => $employee === null ? null : $this->compliance($employee, $tenantId),
            'employeeOptions' => Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'employee_number'])
                ->map(fn (Employee $e): array => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'nik' => $e->employee_number,
                ])->all(),
            'masterOptions' => SalaryMaster::forTenant($tenantId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'category'])
                ->map(fn (SalaryMaster $m): array => [
                    'id' => $m->id,
                    'label' => $m->code.' · '.$m->category,
                ])->all(),
            'salaryFloor' => SalaryPeriodLock::lockedThrough($tenantId)?->addDay()->toDateString(),
            'suggestedEffectiveDate' => SalaryPeriodLock::suggestedDate($tenantId)->toDateString(),
        ]);
    }

    /**
     * Save one employee's salary: the Master Gaji they follow plus the nominal
     * of every component that differs from it, as one dated version.
     */
    public function store(StoreEmployeeSalaryRequest $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validated();
        $from = Carbon::createFromFormat('Y-m-d', $data['effective_start_date'])->startOfDay();

        $refusal = SalaryPeriodLock::refusal($tenantId, $from);

        if ($refusal !== null) {
            return back()->withErrors(['effective_start_date' => $refusal]);
        }

        $employee = Employee::forTenant($tenantId)->findOrFail($data['employee_id']);

        $status = SalarySettings::statusFor($tenantId);
        $masterId = array_key_exists('salary_master_id', $data)
            ? ($data['salary_master_id'] === null ? null : (int) $data['salary_master_id'])
            : $employee->salary_master_id;
        $masterAmounts = $masterId === null
            ? []
            : SalaryMasterAssignment::templateComponents($tenantId, $masterId);

        DB::transaction(function () use ($data, $employee, $tenantId, $from, $request, $status, $masterId, $masterAmounts): void {
            Employee::forTenant($tenantId)
                ->whereKey($employee->id)
                ->lockForUpdate()
                ->firstOrFail();

            $changeSet = SalaryChangeSet::create([
                'tenant_id' => $tenantId,
                'employee_id' => $employee->id,
                'salary_master_id' => $masterId,
                'change_type' => 'master_assignment',
                'existing_strategy' => 'skip',
                'effective_start_date' => $from->toDateString(),
                'status' => $status,
                'reason' => $data['reason'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            if ($status === 'active') {
                SalaryMasterAssignment::retireExistingRows(
                    $tenantId,
                    (int) $employee->id,
                    $from,
                    false,
                );
            }

            $salaryRows = collect($masterAmounts)
                ->mapWithKeys(fn (float $amount, int $componentId): array => [$componentId => [
                    'payroll_component_id' => $componentId,
                    'amount' => $amount,
                ]]);

            foreach ($data['components'] as $row) {
                $salaryRows->put((int) $row['payroll_component_id'], $row);
            }

            foreach ($salaryRows as $row) {
                $componentId = (int) $row['payroll_component_id'];
                $amount = (float) $row['amount'];
                $sourceType = array_key_exists($componentId, $masterAmounts)
                    && round((float) $masterAmounts[$componentId], 2) === round($amount, 2)
                        ? 'master_copy'
                        : 'employee_override';

                EmployeeSalaryWriter::record(
                    $tenantId,
                    (int) $employee->id,
                    $componentId,
                    $amount,
                    $from,
                    $data['reason'] ?? null,
                    (int) $request->user()->id,
                    $masterId,
                    $status,
                    $changeSet->id,
                    $sourceType,
                );
            }

            if ($status === 'active' && $from->lte(today()) && array_key_exists('salary_master_id', $data)) {
                $employee->update(['salary_master_id' => $masterId]);
            }
        });

        if ($status === 'pending_approval') {
            return back()->with('success', 'Gaji diajukan dan menunggu persetujuan');
        }

        $warnings = $this->complianceWarnings($employee->fresh(), $tenantId);

        if ($warnings !== []) {
            return back()->with('warning', 'Gaji tersimpan, tapi: '.implode(' · ', $warnings));
        }

        return back()->with('success', 'Gaji karyawan disimpan');
    }

    private function selectedEmployee(Request $request, int $tenantId): ?Employee
    {
        $employeeId = $request->integer('employee_id') ?: null;

        if ($employeeId === null) {
            return null;
        }

        return Employee::forTenant($tenantId)
            ->with(['position:id,name', 'branch:id,name', 'salaryGrade'])
            ->find($employeeId);
    }

    /**
     * One row per component the employee is paid: the template nominal it comes
     * from, and the employee's own figure when they have one.
     *
     * Components come from the assigned Master Gaji, plus any the employee
     * already carries a row for — a component dropped from the template later
     * must still be visible, otherwise it would keep paying invisibly.
     *
     * @return list<array<string, mixed>>
     */
    private function rows(Employee $employee, int $tenantId, ?int $masterId): array
    {
        $masterAmounts = $masterId === null
            ? collect()
            : SalaryMasterComponent::query()
                ->where('salary_master_id', $masterId)
                ->where('included', true)
                ->get()
                ->keyBy('payroll_component_id');

        $own = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->where(fn ($query) => $query
                ->where('source_type', 'employee_override')
                ->when($masterId !== null, fn ($nested) => $nested
                    ->orWhere(fn ($masterCopy) => $masterCopy
                        ->where('source_type', 'master_copy')
                        ->where('salary_master_id', $masterId))))
            ->inForce()
            ->effectiveOn()
            ->get()
            ->keyBy('payroll_component_id');

        $componentIds = $masterAmounts->keys()->merge($own->keys())->unique();

        if ($componentIds->isEmpty()) {
            return [];
        }

        return PayrollComponent::forTenant($tenantId)
            ->whereIn('id', $componentIds)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))
            ->orderBy('component_group')
            ->orderBy('name')
            ->get()
            ->map(fn (PayrollComponent $component): array => [
                'id' => $component->id,
                'name' => $component->name,
                'group' => $component->component_group ?? 'penerimaan',
                'calc_basis' => $component->calc_basis,
                // Variable components (per present day, per overtime hour) are
                // shown but not set here: their rupiah value comes from
                // attendance, not from a figure typed against the employee.
                'is_fixed' => in_array($component->calc_basis, [null, 'fixed'], true),
                'master_amount' => (float) ($masterAmounts[$component->id]->amount ?? 0),
                'employee_amount' => $own->has($component->id) ? (float) $own[$component->id]->amount : null,
                'effective_from' => $own->has($component->id)
                    ? $own[$component->id]->effective_start_date?->toDateString()
                    : null,
            ])
            ->values()
            ->all();
    }

    private function selectedMasterId(Request $request, Employee $employee, int $tenantId): ?int
    {
        if ($request->has('salary_master_id')) {
            $masterId = $request->integer('salary_master_id') ?: null;

            if ($masterId === null) {
                return null;
            }

            return (int) SalaryMaster::forTenant($tenantId)->findOrFail($masterId)->id;
        }

        return SalaryMasterAssignment::effectiveMasterId($employee);
    }

    /**
     * @return array<string, mixed>
     */
    private function compliance(Employee $employee, int $tenantId): array
    {
        $wage = SalaryCompliance::monthlyWage($employee, $tenantId);

        return [
            ...$wage,
            ...SalaryCompliance::verdict(
                $wage['total'],
                SalaryCompliance::umrFor($employee, $tenantId),
                $employee->salaryGrade,
            ),
        ];
    }

    /**
     * @return list<string>
     */
    private function complianceWarnings(Employee $employee, int $tenantId): array
    {
        $verdict = $this->compliance($employee, $tenantId);

        return array_values(array_filter([
            $verdict['umr_status'] === 'below' ? $verdict['umr_label'] : null,
            in_array($verdict['grade_status'], ['below_min', 'above_max'], true) ? $verdict['grade_label'] : null,
        ]));
    }

    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}

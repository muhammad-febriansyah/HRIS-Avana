<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\Position;
use App\Models\SalaryGrade;
use App\Models\SalaryMaster;
use App\Models\User;
use App\Services\SalaryMasterAssignment;
use App\Support\SalaryPeriodLock;
use App\Support\SalarySettings;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Penetapan Gaji Massal": apply one Master Gaji to many employees at once.
 *
 * The documented flow is filter → preview → apply, in that order: a mass
 * assignment that cannot be reviewed first is a company-wide pay change made
 * blind. The preview therefore reports, per employee, what they are on now and
 * what the template would pay them, and marks anyone already carrying their own
 * figures so an exception is not silently flattened.
 */
class SalaryAssignmentController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'payroll';

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $master = $request->integer('salary_master_id') ?: null;

        $filters = [
            'salary_master_id' => $master,
            'branch_id' => $request->integer('branch_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'position_id' => $request->integer('position_id') ?: null,
            'salary_grade_id' => $request->integer('salary_grade_id') ?: null,
            'employment_status' => $request->string('employment_status')->toString() ?: null,
            'assignment' => $request->string('assignment')->toString() ?: 'all',
        ];

        return Inertia::render('avana/payroll-mass-assignment/index', [
            'filters' => $filters,
            'preview' => $master === null ? [] : $this->preview($tenantId, $master, $filters),
            'template' => $master === null ? null : $this->template($tenantId, $master),
            'masterOptions' => SalaryMaster::forTenant($tenantId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'category'])
                ->map(fn (SalaryMaster $m): array => ['id' => $m->id, 'label' => $m->code.' · '.$m->category])
                ->all(),
            'branchOptions' => Branch::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Branch $b): array => ['id' => $b->id, 'label' => $b->name])->all(),
            'departmentOptions' => Department::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Department $d): array => ['id' => $d->id, 'label' => $d->name])->all(),
            'positionOptions' => Position::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Position $p): array => ['id' => $p->id, 'label' => $p->name])->all(),
            'gradeOptions' => SalaryGrade::forTenant($tenantId)->orderBy('level')->get(['id', 'grade_code', 'grade_name'])
                ->map(fn (SalaryGrade $g): array => ['id' => $g->id, 'label' => $g->grade_code.' · '.$g->grade_name])->all(),
            'salaryFloor' => SalaryPeriodLock::lockedThrough($tenantId)?->addDay()->toDateString(),
        ]);
    }

    /**
     * Apply the template to the employees HR ticked in the preview.
     *
     * The employees are not merely pointed at the template: each one gets their
     * own dated salary rows copied from it, so editing the template later does
     * not silently re-price everybody who was assigned from it.
     */
    public function apply(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'salary_master_id' => ['required', 'integer', Rule::exists('salary_masters', 'id')->where('tenant_id', $tenantId)],
            'employee_ids' => ['required', 'array', 'min:1'],
            'employee_ids.*' => ['integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'effective_start_date' => ['nullable', 'date'],
            'reason' => ['nullable', 'string', 'max:255'],
            // What to do with an employee who already has their own figures:
            // keep them (the default, so exceptions survive) or replace them
            // with the template's.
            'existing' => ['nullable', Rule::in(['skip', 'overwrite'])],
        ]);

        $from = Carbon::parse($data['effective_start_date'] ?? now())->startOfDay();

        $refusal = SalaryPeriodLock::refusal($tenantId, $from);

        if ($refusal !== null) {
            return back()->withErrors(['effective_start_date' => $refusal]);
        }

        $master = SalaryMaster::forTenant($tenantId)->findOrFail($data['salary_master_id']);

        if (SalaryMasterAssignment::templateComponents($tenantId, (int) $master->id) === []) {
            return back()->withErrors(['salary_master_id' => 'Master Gaji ini belum punya komponen tetap yang dicentang.']);
        }

        $employees = Employee::forTenant($tenantId)->whereIn('id', $data['employee_ids'])->get();
        $status = SalarySettings::statusFor($tenantId);

        ['written' => $written, 'kept' => $kept] = SalaryMasterAssignment::apply(
            $tenantId,
            $master,
            $employees,
            $from,
            $data['reason'] ?? null,
            (int) $request->user()->id,
            ($data['existing'] ?? 'skip') === 'overwrite',
            $status,
        );

        $note = $employees->count().' karyawan ditetapkan ke '.$master->code
            .' ('.$written.($status === 'pending_approval' ? ' nominal menunggu persetujuan' : ' nominal ditulis');

        return back()->with(
            'success',
            $kept > 0
                ? $note.', '.$kept.' nominal khusus dipertahankan)'
                : $note.')',
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function template(int $tenantId, int $masterId): array
    {
        $master = SalaryMaster::forTenant($tenantId)->findOrFail($masterId);
        $components = SalaryMasterAssignment::templateComponents($tenantId, $masterId);

        return [
            'id' => $master->id,
            'code' => $master->code,
            'category' => $master->category,
            'component_count' => count($components),
            'total' => $this->netTotal($components, $this->deductionIds($tenantId)),
        ];
    }

    /**
     * The employees a filter selects, with what they are paid now and what the
     * template would pay them — the review step before Apply.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private function preview(int $tenantId, int $masterId, array $filters): array
    {
        $template = SalaryMasterAssignment::templateComponents($tenantId, $masterId);
        $deductionIds = $this->deductionIds($tenantId);
        $templateTotal = $this->netTotal($template, $deductionIds);

        $employees = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->when($filters['branch_id'] !== null, fn (Builder $q) => $q->where('branch_id', $filters['branch_id']))
            ->when($filters['department_id'] !== null, fn (Builder $q) => $q->where('department_id', $filters['department_id']))
            ->when($filters['position_id'] !== null, fn (Builder $q) => $q->where('position_id', $filters['position_id']))
            ->when($filters['salary_grade_id'] !== null, fn (Builder $q) => $q->where('salary_grade_id', $filters['salary_grade_id']))
            ->when($filters['employment_status'] !== null, fn (Builder $q) => $q->where('employment_status', $filters['employment_status']))
            ->when($filters['assignment'] === 'unassigned', fn (Builder $q) => $q->whereNull('salary_master_id'))
            ->when($filters['assignment'] === 'other', fn (Builder $q) => $q->whereNotNull('salary_master_id')->where('salary_master_id', '!=', $masterId))
            ->when($filters['assignment'] === 'assigned', fn (Builder $q) => $q->where('salary_master_id', $masterId))
            ->with(['position:id,name', 'branch:id,name', 'salaryMaster:id,code'])
            ->orderBy('full_name')
            ->get();

        if ($employees->isEmpty()) {
            return [];
        }

        $ownTotals = EmployeeSalaryComponent::forTenant($tenantId)
            ->whereIn('employee_id', $employees->pluck('id'))
            ->inForce()
            ->effectiveOn()
            ->get()
            ->groupBy('employee_id');

        return $employees
            ->map(function (Employee $employee) use ($ownTotals, $template, $templateTotal, $deductionIds): array {
                $own = $ownTotals[$employee->id] ?? collect();
                $clashes = $own->whereIn('payroll_component_id', array_keys($template));

                return [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'nik' => $employee->employee_number,
                    'position' => $employee->position?->name,
                    'branch' => $employee->branch?->name,
                    'current_master' => $employee->salaryMaster?->code,
                    'current_total' => $this->netTotal(
                        $own->mapWithKeys(fn (EmployeeSalaryComponent $row): array => [
                            (int) $row->payroll_component_id => (float) $row->amount,
                        ])->all(),
                        $deductionIds,
                    ),
                    'template_total' => $templateTotal,
                    // Someone already carrying their own figure for a component
                    // the template also pays: the exception this run would
                    // either keep or flatten.
                    'has_own_figures' => $clashes->isNotEmpty(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * A take-home figure, not a pile of numbers: a potongan subtracts.
     *
     * @param  array<int, float>  $amounts  keyed by component id
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

    /**
     * The tenant's deduction components, as a lookup by id.
     *
     * @return array<int, true>
     */
    private function deductionIds(int $tenantId): array
    {
        return PayrollComponent::forTenant($tenantId)
            ->where(fn ($query) => $query->where('type', 'deduction')->orWhere('component_group', 'potongan'))
            ->pluck('id')
            ->mapWithKeys(fn (int $id): array => [$id => true])
            ->all();
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

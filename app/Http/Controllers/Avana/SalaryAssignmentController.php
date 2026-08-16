<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Http\Requests\Avana\ApplySalaryAssignmentRequest;
use App\Models\Branch;
use App\Models\CustomField;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\Position;
use App\Models\SalaryGrade;
use App\Models\SalaryMaster;
use App\Models\SalaryMasterComponent;
use App\Models\User;
use App\Services\SalaryAssignmentPreview;
use App\Services\SalaryMasterAssignment;
use App\Support\SalaryPeriodLock;
use App\Support\SalarySettings;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

        $masterId = $request->integer('salary_master_id') ?: null;
        $requestedDate = $request->string('effective_start_date')->toString();
        $from = SalaryPeriodLock::suggestedDate($tenantId);

        if ($requestedDate !== '' && validator(
            ['effective_start_date' => $requestedDate],
            ['effective_start_date' => ['date_format:Y-m-d']],
        )->passes()) {
            $from = Carbon::createFromFormat('Y-m-d', $requestedDate)->startOfDay();
        }
        $existing = in_array($request->string('existing')->toString(), ['skip', 'overwrite'], true)
            ? $request->string('existing')->toString()
            : 'skip';

        $filters = [
            'salary_master_id' => $masterId,
            'branch_id' => $request->integer('branch_id') ?: null,
            'department_id' => $request->integer('department_id') ?: null,
            'position_id' => $request->integer('position_id') ?: null,
            'salary_grade_id' => $request->integer('salary_grade_id') ?: null,
            'employment_status' => $request->string('employment_status')->toString() ?: null,
            'assignment' => $request->string('assignment')->toString() ?: 'all',
            // Client, Placement and NPO are per-tenant facts, so they are kept
            // as Field Kustom rather than columns only some tenants would use:
            // whatever the tenant defined shows up here as a filter on its own.
            'custom' => $this->customFilters($request, $tenantId),
            // "Kontrak aktif" is asked on the date the change takes effect, not
            // today: assigning a salary to somebody whose contract ends before
            // it starts is the mistake this filter exists to prevent.
            'contract' => in_array($request->string('contract')->toString(), ['active', 'none'], true)
                ? $request->string('contract')->toString()
                : 'all',
            'effective_start_date' => $from->toDateString(),
            'existing' => $existing,
        ];

        // A template that exists but is switched off is a different answer from
        // one that does not exist: 404 told HR "no such page" when the truth is
        // "this Master Gaji is nonaktif". The screen stays, the reason is said
        // out loud, and no preview is built from a template nobody may assign.
        $requestedMaster = $masterId === null
            ? null
            : SalaryMaster::forTenant($tenantId)->findOrFail($masterId);

        $master = $requestedMaster?->is_active ? $requestedMaster : null;

        $masterNotice = $requestedMaster !== null && ! $requestedMaster->is_active
            ? 'Master Gaji '.$requestedMaster->code.' berstatus nonaktif, jadi tidak bisa ditetapkan. Aktifkan dulu di Master Gaji, atau pilih master lain.'
            : null;

        if ($master === null) {
            $filters['salary_master_id'] = null;
        }

        $employees = $master === null ? collect() : $this->employees($tenantId, (int) $master->id, $filters);
        $preview = $master === null
            ? ['rows' => [], 'employee_ids' => [], 'token' => null]
            : app(SalaryAssignmentPreview::class)->build($tenantId, $master, $employees, $from, $existing === 'overwrite');

        return Inertia::render('avana/payroll-master-gaji/index', [
            'tab' => 'massal',
            'filters' => $filters,
            'masterNotice' => $masterNotice,
            'preview' => $preview['rows'],
            'previewEmployeeIds' => $preview['employee_ids'],
            'previewToken' => $preview['token'],
            'template' => $master === null ? null : $this->template($tenantId, (int) $master->id),
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
            // Client / Placement / NPO and anything else this tenant tracks.
            'customFieldOptions' => $this->customFieldOptions($tenantId)->all(),
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
    public function apply(ApplySalaryAssignmentRequest $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validated();
        $from = Carbon::createFromFormat('Y-m-d', $data['effective_start_date'])->startOfDay();

        $refusal = SalaryPeriodLock::refusal($tenantId, $from);

        if ($refusal !== null) {
            return back()->withErrors(['effective_start_date' => $refusal]);
        }

        $status = SalarySettings::statusFor($tenantId);
        $result = DB::transaction(function () use ($data, $tenantId, $from, $request, $status): array {
            $master = SalaryMaster::forTenant($tenantId)
                ->where('is_active', true)
                ->whereKey($data['salary_master_id'])
                ->lockForUpdate()
                ->firstOrFail();

            SalaryMasterComponent::query()
                ->where('salary_master_id', $master->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (SalaryMasterAssignment::templateComponents($tenantId, (int) $master->id) === []) {
                throw ValidationException::withMessages([
                    'salary_master_id' => 'Master Gaji ini belum punya komponen aktif yang dicentang.',
                ]);
            }

            $refusal = SalaryPeriodLock::refusal($tenantId, $from);

            if ($refusal !== null) {
                throw ValidationException::withMessages(['effective_start_date' => $refusal]);
            }

            $previewEmployees = Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->whereIn('id', $data['preview_employee_ids'])
                ->with(['position:id,name', 'branch:id,name'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if ($previewEmployees->count() !== count($data['preview_employee_ids'])) {
                throw ValidationException::withMessages([
                    'preview_token' => 'Daftar karyawan berubah. Tampilkan preview ulang sebelum menerapkan.',
                ]);
            }

            EmployeeSalaryComponent::forTenant($tenantId)
                ->whereIn('employee_id', $data['preview_employee_ids'])
                ->inForce()
                ->effectiveOn($from)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $preview = app(SalaryAssignmentPreview::class)->build(
                $tenantId,
                $master,
                $previewEmployees,
                $from,
                $data['existing'] === 'overwrite',
            );

            if (! hash_equals($preview['token'], $data['preview_token'])) {
                throw ValidationException::withMessages([
                    'preview_token' => 'Data Master Gaji atau gaji karyawan berubah. Tampilkan preview ulang sebelum menerapkan.',
                ]);
            }

            $selectedIds = collect($data['employee_ids'])->map(fn (mixed $id): int => (int) $id);

            if ($selectedIds->diff($preview['employee_ids'])->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'employee_ids' => 'Karyawan yang dipilih tidak ada dalam preview yang disetujui.',
                ]);
            }

            $employees = $previewEmployees->whereIn('id', $selectedIds)->values();
            $writeResult = SalaryMasterAssignment::apply(
                $tenantId,
                $master,
                $employees,
                $from,
                $data['reason'] ?? null,
                (int) $request->user()->id,
                $data['existing'] === 'overwrite',
                $status,
            );

            return ['master' => $master, 'employees' => $employees, ...$writeResult];
        }, 3);

        /** @var SalaryMaster $master */
        $master = $result['master'];
        $employees = $result['employees'];
        $written = $result['written'];
        $kept = $result['kept'];

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
        $fixedComponentIds = PayrollComponent::forTenant($tenantId)
            ->whereIn('id', array_keys($components))
            ->where(fn ($query) => $query->whereNull('calc_basis')->orWhere('calc_basis', 'fixed'))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
        $fixedComponents = array_intersect_key($components, array_flip($fixedComponentIds));

        return [
            'id' => $master->id,
            'code' => $master->code,
            'category' => $master->category,
            'component_count' => count($components),
            'variable_count' => count($components) - count($fixedComponents),
            'total' => $this->netTotal($fixedComponents, $this->deductionIds($tenantId)),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Collection<int, Employee>
     */
    private function employees(int $tenantId, int $masterId, array $filters): Collection
    {
        return Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->when($filters['branch_id'] !== null, fn (Builder $q) => $q->where('branch_id', $filters['branch_id']))
            ->when($filters['department_id'] !== null, fn (Builder $q) => $q->where('department_id', $filters['department_id']))
            ->when($filters['position_id'] !== null, fn (Builder $q) => $q->where('position_id', $filters['position_id']))
            ->when($filters['salary_grade_id'] !== null, fn (Builder $q) => $q->where('salary_grade_id', $filters['salary_grade_id']))
            ->when($filters['employment_status'] !== null, fn (Builder $q) => $q->where('employment_status', $filters['employment_status']))
            ->when($filters['assignment'] === 'unassigned', fn (Builder $q) => $q->whereNull('salary_master_id'))
            ->when($filters['assignment'] === 'other', fn (Builder $q) => $q->whereNotNull('salary_master_id')->where('salary_master_id', '!=', $masterId))
            ->when($filters['assignment'] === 'assigned', fn (Builder $q) => $q->where('salary_master_id', $masterId))
            ->when($filters['contract'] === 'active', fn (Builder $q) => $q->whereHas(
                'contracts',
                fn (Builder $contract) => $this->activeContractOn($contract, $filters['effective_start_date']),
            ))
            ->when($filters['contract'] === 'none', fn (Builder $q) => $q->whereDoesntHave(
                'contracts',
                fn (Builder $contract) => $this->activeContractOn($contract, $filters['effective_start_date']),
            ))
            ->where(function (Builder $query) use ($filters): void {
                foreach ($filters['custom'] as $key => $value) {
                    $query->where('custom_data->'.$key, $value);
                }
            })
            ->with(['position:id,name', 'branch:id,name'])
            ->orderBy('full_name')
            ->get();
    }

    /** A contract in force on the date the salary change takes effect. */
    private function activeContractOn(Builder $query, string $on): Builder
    {
        return $query
            ->where('status', 'active')
            ->whereDate('start_date', '<=', $on)
            ->where(fn (Builder $inner) => $inner
                ->whereNull('end_date')
                ->orWhereDate('end_date', '>=', $on));
    }

    /**
     * The Field Kustom filters the request carried, keyed by field key. Only
     * keys the tenant actually defined are honoured — a filter is a query on the
     * employee's own JSON, and unknown keys have no business there.
     *
     * @return array<string, string>
     */
    private function customFilters(Request $request, int $tenantId): array
    {
        $requested = $request->input('custom');

        if (! is_array($requested) || $requested === []) {
            return [];
        }

        $known = $this->customFieldOptions($tenantId)->pluck('key')->all();
        $filters = [];

        foreach ($requested as $key => $value) {
            if (! in_array($key, $known, true) || ! is_string($value) || $value === '') {
                continue;
            }

            $filters[$key] = $value;
        }

        return $filters;
    }

    /**
     * The tenant's employee Field Kustom, which is where Client / Placement /
     * NPO live for the tenants that track them.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function customFieldOptions(int $tenantId): \Illuminate\Support\Collection
    {
        return CustomField::forTenant($tenantId)
            ->where('entity', 'employee')
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))
            ->orderBy('sort_order')
            ->get(['key', 'label', 'type', 'options'])
            ->map(fn (CustomField $field): array => [
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type,
                'options' => is_array($field->options) ? array_values($field->options) : [],
            ]);
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

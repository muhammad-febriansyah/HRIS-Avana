<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\JobLevel;
use App\Models\Position;
use App\Models\Shift;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Generic CRUD controller for the AvanaHR "Perusahaan" (company setup) module.
 *
 * A single controller drives every org-structure master entity (branches,
 * departments, positions, job levels, work locations, shifts) through a
 * whitelist keyed by URL slug. Every action is tenant-scoped and guarded.
 */
class CompanySetupController extends Controller
{
    /**
     * Roles that may always manage the company setup master data.
     *
     * @var array<int, string>
     */
    private const MANAGER_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Whitelist mapping each entity slug to its model and mass-assignable
     * columns. Validation rules are resolved separately because they depend on
     * the current tenant and (for updates) the record being ignored.
     *
     * @var array<string, array{model: class-string<Model>, fillable: array<int, string>}>
     */
    private const ENTITIES = [
        'branches' => [
            'model' => Branch::class,
            'fillable' => ['code', 'name', 'phone', 'address', 'timezone', 'status'],
        ],
        'departments' => [
            'model' => Department::class,
            'fillable' => ['code', 'name', 'parent_id', 'status'],
        ],
        'positions' => [
            'model' => Position::class,
            'fillable' => ['code', 'name', 'department_id', 'status'],
        ],
        'job-levels' => [
            'model' => JobLevel::class,
            'fillable' => ['code', 'name', 'level_order', 'status'],
        ],
        'work-locations' => [
            'model' => WorkLocation::class,
            'fillable' => ['code', 'name', 'branch_id', 'latitude', 'longitude', 'radius_meter', 'address', 'status'],
        ],
        'shifts' => [
            'model' => Shift::class,
            'fillable' => ['code', 'name', 'start_time', 'end_time', 'late_tolerance_minutes', 'work_days', 'status'],
        ],
    ];

    /**
     * Render the company setup screen with every org-structure collection,
     * tenant-scoped and ordered by name, plus FK option lists.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManageCompany($request);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/perusahaan/index', [
            'branches' => Branch::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'company_id', 'code', 'name', 'phone', 'address', 'timezone', 'status']),
            'departments' => Department::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'parent_id', 'status']),
            'positions' => Position::forTenant($tenantId)
                ->with('department:id,name')
                ->orderBy('name')
                ->get(['id', 'department_id', 'code', 'name', 'status'])
                ->map(fn (Position $position): array => [
                    'id' => $position->id,
                    'code' => $position->code,
                    'name' => $position->name,
                    'department_id' => $position->department_id,
                    'department_name' => $position->department?->name,
                    'status' => $position->status,
                ]),
            'jobLevels' => JobLevel::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'level_order', 'status']),
            'workLocations' => WorkLocation::forTenant($tenantId)
                ->with('branch:id,name')
                ->orderBy('name')
                ->get(['id', 'branch_id', 'code', 'name', 'latitude', 'longitude', 'radius_meter', 'address', 'status'])
                ->map(fn (WorkLocation $location): array => [
                    'id' => $location->id,
                    'code' => $location->code,
                    'name' => $location->name,
                    'branch_id' => $location->branch_id,
                    'branch_name' => $location->branch?->name,
                    'latitude' => $location->latitude,
                    'longitude' => $location->longitude,
                    'radius_meter' => $location->radius_meter,
                    'address' => $location->address,
                    'status' => $location->status,
                ]),
            'shifts' => Shift::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'code', 'name', 'start_time', 'end_time', 'late_tolerance_minutes', 'work_days', 'status']),
            'options' => [
                'departments' => Department::forTenant($tenantId)
                    ->orderBy('name')
                    ->get(['id', 'name']),
                'branches' => Branch::forTenant($tenantId)
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * Validate and create a new record for the given entity under the tenant.
     */
    public function store(Request $request, string $entity): RedirectResponse
    {
        $this->ensureCanManageCompany($request);

        $config = $this->resolveEntity($entity);
        $tenantId = $request->user()->tenant_id;

        $data = $request->validate($this->rulesFor($entity, $tenantId), $this->messages());

        $model = $config['model'];
        $model::create(array_merge(
            Arr::only($data, $config['fillable']),
            ['tenant_id' => $tenantId],
        ));

        return back()->with('success', 'Data disimpan');
    }

    /**
     * Validate and update an existing record belonging to the tenant.
     */
    public function update(Request $request, string $entity, int $record): RedirectResponse
    {
        $this->ensureCanManageCompany($request);

        $config = $this->resolveEntity($entity);
        $tenantId = $request->user()->tenant_id;

        $model = $config['model'];
        $instance = $model::forTenant($tenantId)->findOrFail($record);

        $data = $request->validate(
            $this->rulesFor($entity, $tenantId, (int) $instance->getKey()),
            $this->messages(),
        );

        $instance->update(Arr::only($data, $config['fillable']));

        return back()->with('success', 'Data diperbarui');
    }

    /**
     * Soft delete a record belonging to the tenant.
     */
    public function destroy(Request $request, string $entity, int $record): RedirectResponse
    {
        $this->ensureCanManageCompany($request);

        $config = $this->resolveEntity($entity);
        $tenantId = $request->user()->tenant_id;

        $model = $config['model'];
        $instance = $model::forTenant($tenantId)->findOrFail($record);
        $instance->delete();

        return back()->with('success', 'Data dihapus');
    }

    /**
     * Resolve the entity configuration or abort with a 404.
     *
     * @return array{model: class-string<Model>, fillable: array<int, string>}
     */
    private function resolveEntity(string $entity): array
    {
        return self::ENTITIES[$entity] ?? abort(404);
    }

    /**
     * Build the tenant-scoped validation rules for the given entity.
     *
     * @return array<string, array<int, mixed>>
     */
    private function rulesFor(string $entity, int $tenantId, ?int $recordId = null): array
    {
        return match ($entity) {
            'branches' => [
                'code' => $this->codeRule('branches', $tenantId, $recordId),
                'name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'],
                'address' => ['nullable', 'string', 'max:1000'],
                'timezone' => ['nullable', 'string', 'max:255'],
                'status' => $this->statusRule(),
            ],
            'departments' => [
                'code' => $this->codeRule('departments', $tenantId, $recordId),
                'name' => ['required', 'string', 'max:255'],
                'parent_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
                'status' => $this->statusRule(),
            ],
            'positions' => [
                'code' => $this->codeRule('positions', $tenantId, $recordId),
                'name' => ['required', 'string', 'max:255'],
                'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
                'status' => $this->statusRule(),
            ],
            'job-levels' => [
                'code' => $this->codeRule('job_levels', $tenantId, $recordId),
                'name' => ['required', 'string', 'max:255'],
                'level_order' => ['nullable', 'integer', 'min:0'],
                'status' => $this->statusRule(),
            ],
            'work-locations' => [
                'code' => ['nullable', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
                'latitude' => ['nullable', 'numeric', 'between:-90,90'],
                'longitude' => ['nullable', 'numeric', 'between:-180,180'],
                'radius_meter' => ['nullable', 'integer', 'min:0'],
                'address' => ['nullable', 'string', 'max:1000'],
                'status' => $this->statusRule(),
            ],
            'shifts' => [
                'code' => ['nullable', 'string', 'max:255'],
                'name' => ['required', 'string', 'max:255'],
                'start_time' => ['nullable', 'date_format:H:i'],
                'end_time' => ['nullable', 'date_format:H:i'],
                'late_tolerance_minutes' => ['nullable', 'integer', 'min:0'],
                'work_days' => ['nullable', 'array'],
                'work_days.*' => ['integer', 'between:0,6'],
                'status' => $this->statusRule(),
            ],
            default => abort(404),
        };
    }

    /**
     * A required, tenant-unique code rule that ignores the edited record.
     *
     * @return array<int, mixed>
     */
    private function codeRule(string $table, int $tenantId, ?int $recordId): array
    {
        $unique = Rule::unique($table, 'code')->where('tenant_id', $tenantId);

        if ($recordId !== null) {
            $unique->ignore($recordId);
        }

        return ['required', 'string', 'max:255', $unique];
    }

    /**
     * The shared active/inactive status rule.
     *
     * @return array<int, mixed>
     */
    private function statusRule(): array
    {
        return ['required', Rule::in(['active', 'inactive'])];
    }

    /**
     * Indonesian validation messages shared across every entity.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'code.required' => 'Kode wajib diisi.',
            'code.unique' => 'Kode sudah digunakan.',
            'name.required' => 'Nama wajib diisi.',
            'status.required' => 'Status wajib dipilih.',
            'status.in' => 'Status tidak valid.',
            'parent_id.exists' => 'Departemen induk yang dipilih tidak valid.',
            'department_id.exists' => 'Departemen yang dipilih tidak valid.',
            'branch_id.exists' => 'Cabang yang dipilih tidak valid.',
            'level_order.integer' => 'Urutan jenjang harus berupa angka.',
            'latitude.numeric' => 'Latitude harus berupa angka.',
            'latitude.between' => 'Latitude harus di antara -90 dan 90.',
            'longitude.numeric' => 'Longitude harus berupa angka.',
            'longitude.between' => 'Longitude harus di antara -180 dan 180.',
            'radius_meter.integer' => 'Radius harus berupa angka.',
            'start_time.date_format' => 'Jam masuk tidak valid.',
            'end_time.date_format' => 'Jam keluar tidak valid.',
            'late_tolerance_minutes.integer' => 'Toleransi keterlambatan harus berupa angka.',
        ];
    }

    /**
     * Abort with 403 unless the user is privileged or holds branch.manage.
     */
    private function ensureCanManageCompany(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::MANAGER_ROLES)->isNotEmpty();

        $hasManagePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains('branch.manage');

        abort_unless($isPrivileged || $hasManagePermission, 403);
    }
}

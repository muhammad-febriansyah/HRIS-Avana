<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Avana\StoreEmployeeRequest;
use App\Http\Requests\Avana\UpdateEmployeeRequest;
use App\Http\Resources\Avana\EmployeeResource;
use App\Models\Branch;
use App\Models\CustomField;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobLevel;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkLocation;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    use AppliesBranchScope;
    use AuthorizesRequests;

    /**
     * Columns selected for the employee DataTable (avoids loading wide rows).
     *
     * @var array<int, string>
     */
    private const LIST_COLUMNS = [
        'id', 'tenant_id', 'user_id', 'branch_id', 'department_id', 'position_id',
        'job_level_id', 'manager_id', 'employee_number', 'full_name', 'email',
        'phone', 'nik', 'gender', 'employment_status', 'join_date', 'status', 'created_at',
    ];

    /**
     * Sortable columns whitelist for the index DataTable.
     *
     * @var array<int, string>
     */
    private const SORTABLE = ['full_name', 'employee_number', 'join_date', 'created_at'];

    /**
     * Display a server-side paginated, filterable list of employees.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $tenantId = $request->user()->tenant_id;

        $sort = in_array($request->query('sort'), self::SORTABLE, true)
            ? $request->query('sort')
            : 'created_at';

        $direction = $request->query('direction') === 'asc' ? 'asc' : 'desc';

        $query = Employee::query()
            ->forTenant($tenantId)
            ->select(self::LIST_COLUMNS)
            ->with([
                'branch:id,name',
                'department:id,name',
                'position:id,name',
                'jobLevel:id,name',
                'manager:id,full_name,employee_number',
            ])
            ->when($request->query('search'), function ($query, $search): void {
                $query->where(function ($q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%")
                        ->orWhere('employee_number', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                });
            })
            ->when($request->query('branch_id'), fn ($q, $id) => $q->where('branch_id', $id))
            ->when($request->query('department_id'), fn ($q, $id) => $q->where('department_id', $id))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->query('employment_status'), fn ($q, $value) => $q->where('employment_status', $value));

        $this->applyBranchScope($query, $request->user());

        $employees = $query
            ->orderBy($sort, $direction)
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('avana/employees/index', [
            'employees' => EmployeeResource::collection($employees),
            'filters' => $request->only([
                'search', 'branch_id', 'department_id', 'status',
                'employment_status', 'sort', 'direction', 'per_page',
            ]),
            'branches' => Branch::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
            'departments' => Department::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new employee.
     */
    public function create(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('avana/employees/create', [
            'options' => $this->formOptions($request),
            'customFields' => $this->customFields($request->user()->tenant_id),
        ]);
    }

    /**
     * Persist a new employee under the authenticated user's tenant.
     */
    public function store(StoreEmployeeRequest $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validated();
        $password = $data['password'] ?? null;
        unset($data['password']);
        $data['tenant_id'] = $tenantId;

        if (empty($data['employee_number'])) {
            $data['employee_number'] = $this->generateEmployeeNumber($tenantId);
        }

        $employee = Employee::create($data);

        $this->syncEmployeeLogin($employee, $password, $tenantId);

        return redirect()->route('avana.employees.index')
            ->with('success', 'Karyawan berhasil ditambahkan');
    }

    /**
     * Show the multi-row form for adding several employees at once.
     */
    public function bulkCreate(Request $request): Response
    {
        $this->authorize('create', Employee::class);

        return Inertia::render('avana/employees/bulk-create', [
            'options' => $this->formOptions($request),
        ]);
    }

    /**
     * Persist a batch of employees in one transaction.
     */
    public function bulkStore(Request $request): RedirectResponse
    {
        $this->authorize('create', Employee::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'employees' => ['required', 'array', 'min:1', 'max:200'],
            'employees.*.full_name' => ['required', 'string', 'max:255'],
            'employees.*.email' => ['nullable', 'email', 'max:255'],
            'employees.*.employment_status' => ['required', 'in:probation,contract,permanent,resigned'],
            'employees.*.status' => ['required', 'in:active,inactive'],
            'employees.*.branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'employees.*.work_location_id' => ['nullable', Rule::exists('work_locations', 'id')->where('tenant_id', $tenantId)],
            'employees.*.department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'employees.*.position_id' => ['nullable', Rule::exists('positions', 'id')->where('tenant_id', $tenantId)],
            'employees.*.password' => ['nullable', 'string', 'min:8'],
        ], [
            'employees.*.full_name.required' => 'Nama lengkap wajib diisi.',
            'employees.*.employment_status.required' => 'Status kepegawaian wajib dipilih.',
            'employees.*.email.email' => 'Format email tidak valid.',
        ]);

        $this->validateBulkLogins($validated['employees']);

        DB::transaction(function () use ($validated, $tenantId): void {
            foreach ($validated['employees'] as $row) {
                $employee = Employee::create([
                    'tenant_id' => $tenantId,
                    'employee_number' => $this->generateEmployeeNumber($tenantId),
                    'full_name' => $row['full_name'],
                    'email' => $row['email'] ?? null,
                    'employment_status' => $row['employment_status'],
                    'status' => $row['status'],
                    'branch_id' => $row['branch_id'] ?? null,
                    'work_location_id' => $row['work_location_id'] ?? null,
                    'department_id' => $row['department_id'] ?? null,
                    'position_id' => $row['position_id'] ?? null,
                ]);

                $this->syncEmployeeLogin($employee, $row['password'] ?? null, $tenantId);
            }
        });

        $count = count($validated['employees']);

        return redirect()->route('avana.employees.index')
            ->with('success', "{$count} karyawan berhasil ditambahkan");
    }

    /**
     * Render the visual organisation chart from the reporting hierarchy.
     */
    public function orgChart(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $tenantId = $request->user()->tenant_id;

        $employees = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->with(['position:id,name', 'department:id,name'])
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'position_id', 'department_id', 'manager_id']);

        return Inertia::render('avana/employees/org-chart', [
            'nodes' => $employees->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'position' => $employee->position?->name,
                'department' => $employee->department?->name,
                'manager_id' => $employee->manager_id,
            ])->values(),
        ]);
    }

    /**
     * Display the specified employee.
     */
    public function show(Request $request, Employee $employee): Response
    {
        $this->ensureTenantOwnership($request, $employee);
        $this->authorize('view', $employee);

        $employee->load([
            'branch:id,name',
            'department:id,name',
            'position:id,name',
            'jobLevel:id,name',
            'workLocation:id,name,radius_meter,status',
            'manager:id,full_name,employee_number',
        ]);

        return Inertia::render('avana/employees/show', [
            'employee' => new EmployeeResource($employee),
            'customFields' => $this->customFields($request->user()->tenant_id),
        ]);
    }

    /**
     * Show the form for editing an existing employee.
     */
    public function edit(Request $request, Employee $employee): Response
    {
        $this->ensureTenantOwnership($request, $employee);
        $this->authorize('update', $employee);

        $employee->load([
            'branch:id,name',
            'department:id,name',
            'position:id,name',
            'jobLevel:id,name',
            'workLocation:id,name',
            'manager:id,full_name,employee_number',
        ]);

        return Inertia::render('avana/employees/edit', [
            'employee' => new EmployeeResource($employee),
            'options' => $this->formOptions($request),
            'customFields' => $this->customFields($request->user()->tenant_id),
        ]);
    }

    /**
     * Update an existing employee.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $employee);
        $this->authorize('update', $employee);

        $data = $request->validated();
        $password = $data['password'] ?? null;
        unset($data['password']);

        $employee->update($data);

        $this->syncEmployeeLogin($employee, $password, $request->user()->tenant_id);

        return redirect()->route('avana.employees.index')
            ->with('success', 'Karyawan berhasil diperbarui');
    }

    /**
     * A row that sets a password must carry a unique email to log in with,
     * unique both against existing accounts and within the batch itself.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private function validateBulkLogins(array $rows): void
    {
        $errors = [];
        $seen = [];

        foreach ($rows as $index => $row) {
            if (blank($row['password'] ?? null)) {
                continue;
            }

            $email = $row['email'] ?? null;

            if (blank($email)) {
                $errors["employees.{$index}.email"] = 'Email wajib diisi untuk membuat akun login.';

                continue;
            }

            if (isset($seen[$email]) || User::where('email', $email)->exists()) {
                $errors["employees.{$index}.email"] = 'Email sudah digunakan akun lain.';

                continue;
            }

            $seen[$email] = true;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Create or reset the employee's mobile-app login account. A blank password
     * is a no-op; an existing account has its password reset, otherwise a new
     * user is created (email + employee role) and linked to the employee.
     */
    private function syncEmployeeLogin(Employee $employee, ?string $password, int $tenantId): void
    {
        if (blank($password)) {
            return;
        }

        if ($employee->user_id !== null) {
            $employee->user?->update(['password' => $password]);

            return;
        }

        $user = User::create([
            'tenant_id' => $tenantId,
            'name' => $employee->full_name,
            'email' => $employee->email,
            'password' => $password,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);

        $role = Role::where('tenant_id', $tenantId)->where('code', 'employee')->first();

        if ($role !== null) {
            $user->roles()->syncWithoutDetaching([$role->id]);
        }

        $employee->forceFill(['user_id' => $user->id])->save();
    }

    /**
     * Soft delete (archive) an employee.
     */
    public function destroy(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $employee);
        $this->authorize('delete', $employee);

        $employee->delete();

        return back()->with('success', 'Karyawan dihapus');
    }

    /**
     * Build the option lists shared by the create and edit forms.
     *
     * @return array<string, mixed>
     */
    private function formOptions(Request $request): array
    {
        $tenantId = $request->user()->tenant_id;

        return [
            'branches' => Branch::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
            'workLocations' => WorkLocation::forTenant($tenantId)
                ->where('status', 'active')
                ->select('id', 'name', 'branch_id')
                ->orderBy('name')
                ->get(),
            'departments' => Department::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
            'positions' => Position::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
            'jobLevels' => JobLevel::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
            'managers' => Employee::forTenant($tenantId)
                ->select('id', 'full_name', 'employee_number')
                ->orderBy('full_name')
                ->get()
                ->map(fn (Employee $manager): array => [
                    'id' => $manager->id,
                    'name' => $manager->full_name,
                    'employee_number' => $manager->employee_number,
                ]),
            'genders' => [
                ['value' => 'male', 'label' => 'Laki-laki'],
                ['value' => 'female', 'label' => 'Perempuan'],
                ['value' => 'unspecified', 'label' => 'Tidak ditentukan'],
            ],
            'statuses' => [
                ['value' => 'active', 'label' => 'Aktif'],
                ['value' => 'inactive', 'label' => 'Nonaktif'],
            ],
            'employmentStatuses' => [
                ['value' => 'probation', 'label' => 'Masa Percobaan'],
                ['value' => 'contract', 'label' => 'Kontrak'],
                ['value' => 'permanent', 'label' => 'Tetap'],
                ['value' => 'resigned', 'label' => 'Resign'],
            ],
        ];
    }

    /**
     * The tenant's active custom employee field definitions.
     *
     * @return array<int, array<string, mixed>>
     */
    private function customFields(int $tenantId): array
    {
        return CustomField::forTenant($tenantId)
            ->where('entity', 'employee')
            ->where('status', 'active')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get(['key', 'label', 'type', 'options', 'is_required'])
            ->map(fn (CustomField $field): array => [
                'key' => $field->key,
                'label' => $field->label,
                'type' => $field->type,
                'options' => $field->options ?? [],
                'is_required' => $field->is_required,
            ])
            ->all();
    }

    /**
     * Generate the next tenant-scoped employee number (e.g. EMP-0001).
     */
    private function generateEmployeeNumber(int $tenantId): string
    {
        $sequence = Employee::withTrashed()->forTenant($tenantId)->count();

        do {
            $sequence++;
            $candidate = 'EMP-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
        } while (
            Employee::withTrashed()
                ->forTenant($tenantId)
                ->where('employee_number', $candidate)
                ->exists()
        );

        return $candidate;
    }

    /**
     * Abort with 404 when the employee does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Employee $employee): void
    {
        abort_if((int) $employee->tenant_id !== (int) $request->user()->tenant_id, 404);
    }
}

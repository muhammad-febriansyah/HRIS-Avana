<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\Avana\StoreEmployeeRequest;
use App\Http\Requests\Avana\UpdateEmployeeRequest;
use App\Http\Resources\Avana\EmployeeResource;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\JobLevel;
use App\Models\Position;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
        $data['tenant_id'] = $tenantId;

        if (empty($data['employee_number'])) {
            $data['employee_number'] = $this->generateEmployeeNumber($tenantId);
        }

        Employee::create($data);

        return redirect()->route('avana.employees.index')
            ->with('success', 'Karyawan berhasil ditambahkan');
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
            'workLocation:id,name',
            'manager:id,full_name,employee_number',
        ]);

        return Inertia::render('avana/employees/show', [
            'employee' => new EmployeeResource($employee),
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
            'manager:id,full_name,employee_number',
        ]);

        return Inertia::render('avana/employees/edit', [
            'employee' => new EmployeeResource($employee),
            'options' => $this->formOptions($request),
        ]);
    }

    /**
     * Update an existing employee.
     */
    public function update(UpdateEmployeeRequest $request, Employee $employee): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $employee);
        $this->authorize('update', $employee);

        $employee->update($request->validated());

        return redirect()->route('avana.employees.index')
            ->with('success', 'Karyawan berhasil diperbarui');
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

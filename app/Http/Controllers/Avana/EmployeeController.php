<?php

namespace App\Http\Controllers\Avana;

use App\Concerns\AppliesBranchScope;
use App\Exports\EmployeeBulkTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Avana\StoreEmployeeRequest;
use App\Http\Requests\Avana\UpdateEmployeeRequest;
use App\Http\Resources\Avana\EmployeeResource;
use App\Imports\EmployeeBulkRowsImport;
use App\Models\AttendancePolicy;
use App\Models\Branch;
use App\Models\CustomField;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeContract;
use App\Models\JobLevel;
use App\Models\Position;
use App\Models\Role;
use App\Models\SalaryMaster;
use App\Models\TaxProfile;
use App\Models\User;
use App\Models\UserDevice;
use App\Models\WorkLocation;
use App\Support\ContractType;
use App\Support\SalaryCompliance;
use App\Support\TenantQuota;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
        'id', 'public_id', 'tenant_id', 'user_id', 'branch_id', 'department_id', 'position_id',
        'job_level_id', 'manager_id', 'employee_number', 'full_name', 'email',
        'phone', 'nik', 'gender', 'employment_status', 'join_date', 'status', 'photo_path', 'created_at',
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
                'user:id,status',
                'user.activeDevice',
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
            // With "1 perangkat 1 akun" off nothing is bound to reset, so the
            // action does not belong on the screen.
            'device_binding_enabled' => (bool) AttendancePolicy::resolve($tenantId)->device_binding_enabled,
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

        TenantQuota::assertRoom($request->user()->tenant, 'employees', 1, 'full_name');

        $data = $request->validated();
        $password = $data['password'] ?? null;
        $roleId = $data['role_id'] ?? null;
        $bpjs = $this->pullBpjsNumbers($data);
        $ptkp = $this->pullPtkpStatus($data);
        $contract = $this->pullContract($data);
        unset($data['password'], $data['role_id']);
        $data['tenant_id'] = $tenantId;

        if (empty($data['employee_number'])) {
            $data['employee_number'] = $this->generateEmployeeNumber($tenantId);
        }

        $employee = Employee::create($data);

        $this->syncBpjsNumbers($employee, $bpjs);
        $this->syncPtkpStatus($employee, $ptkp);
        $this->syncContract($employee, $contract);
        $this->syncEmployeeLogin($employee, $password, $tenantId, $roleId !== null ? (int) $roleId : null);

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
            // One role for the whole batch: the import screen picks it once
            // rather than repeating it on every row.
            'role_id' => ['nullable', Rule::exists('roles', 'id')->where('tenant_id', $tenantId)],
        ], [
            'employees.*.full_name.required' => 'Nama lengkap wajib diisi.',
            'employees.*.employment_status.required' => 'Status kepegawaian wajib dipilih.',
            'employees.*.email.email' => 'Format email tidak valid.',
        ]);

        $this->validateBulkLogins($validated['employees']);

        $roleId = isset($validated['role_id']) ? (int) $validated['role_id'] : null;

        // Required as soon as any row creates a login, for the same reason as on
        // the single-employee form: the tenant names its own roles, so there is
        // no built-in one to fall back on and a role-less account sees nothing.
        if ($roleId === null && collect($validated['employees'])->contains(fn (array $row): bool => filled($row['password'] ?? null))) {
            throw ValidationException::withMessages([
                'role_id' => 'Pilih peran untuk akun login yang dibuat dari impor ini.',
            ]);
        }

        TenantQuota::assertRoom(
            $request->user()->tenant,
            'employees',
            count($validated['employees']),
            'employees',
        );

        DB::transaction(function () use ($validated, $tenantId, $roleId): void {
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

                $this->syncEmployeeLogin($employee, $row['password'] ?? null, $tenantId, $roleId);
            }
        });

        $count = count($validated['employees']);

        return redirect()->route('avana.employees.index')
            ->with('success', "{$count} karyawan berhasil ditambahkan");
    }

    /**
     * Download the bulk-import Excel template (data sheet + reference sheet of
     * the tenant's valid branch / department / position / status values).
     */
    public function bulkTemplate(Request $request): BinaryFileResponse
    {
        $this->authorize('create', Employee::class);

        $tenantId = $request->user()->tenant_id;

        return Excel::download(
            new EmployeeBulkTemplateExport(
                Branch::forTenant($tenantId)->orderBy('name')->pluck('name')->all(),
                Department::forTenant($tenantId)->orderBy('name')->pluck('name')->all(),
                Position::forTenant($tenantId)->orderBy('name')->pluck('name')->all(),
            ),
            'template-karyawan.xlsx',
        );
    }

    /**
     * Parse an uploaded Excel/CSV, resolve branch/department/position/status by
     * name (tenant-scoped, case-insensitive) and validate every row. Returns a
     * preview (never writes) so the user can review and fix before importing.
     */
    public function bulkPreview(Request $request): JsonResponse
    {
        $this->authorize('create', Employee::class);

        $request->validate([
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:8192'],
        ]);

        $tenantId = $request->user()->tenant_id;

        $branches = $this->nameIdMap(Branch::forTenant($tenantId)->pluck('id', 'name'));
        $departments = $this->nameIdMap(Department::forTenant($tenantId)->pluck('id', 'name'));
        $positions = $this->nameIdMap(Position::forTenant($tenantId)->pluck('id', 'name'));

        $statusMap = [
            'masa percobaan' => 'probation', 'probation' => 'probation',
            'kontrak' => 'contract', 'contract' => 'contract',
            'tetap' => 'permanent', 'permanent' => 'permanent',
            'resign' => 'resigned', 'resigned' => 'resigned',
        ];

        $sheets = Excel::toArray(new EmployeeBulkRowsImport, $request->file('file'));
        $sheet = $sheets[0] ?? [];

        if ($sheet !== [] && $this->looksLikeHeader($sheet[0])) {
            array_shift($sheet);
        }

        $existingEmails = User::query()->pluck('email')
            ->merge(Employee::forTenant($tenantId)->whereNotNull('email')->pluck('email'))
            ->map(fn ($e): string => mb_strtolower((string) $e))
            ->flip();
        $seen = [];
        $rows = [];

        foreach ($sheet as $raw) {
            $cells = array_map(static fn ($v): string => trim((string) ($v ?? '')), array_values((array) $raw));

            if (implode('', $cells) === '') {
                continue; // skip blank rows
            }

            [$name, $email, $branch, $dept, $pos, $status, $password] = array_pad($cells, 7, '');

            $errors = [];

            if ($name === '') {
                $errors['full_name'] = 'Nama wajib diisi';
            }

            $branchId = $this->resolveName($branch, $branches, $errors, 'branch', 'Cabang tak dikenal');
            $departmentId = $this->resolveName($dept, $departments, $errors, 'department', 'Departemen tak dikenal');
            $positionId = $this->resolveName($pos, $positions, $errors, 'position', 'Jabatan tak dikenal');

            $employment = 'permanent';
            if ($status !== '') {
                $mapped = $statusMap[mb_strtolower($status)] ?? null;
                if ($mapped === null) {
                    $errors['status'] = 'Status tak dikenal';
                } else {
                    $employment = $mapped;
                }
            }

            if ($email !== '') {
                $key = mb_strtolower($email);
                if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors['email'] = 'Format email tidak valid';
                } elseif (isset($seen[$key])) {
                    $errors['email'] = 'Email duplikat di file';
                } elseif ($existingEmails->has($key)) {
                    $errors['email'] = 'Email sudah terpakai';
                }
                $seen[$key] = true;
            }

            if ($password !== '' && mb_strlen($password) < 8) {
                $errors['password'] = 'Kata sandi minimal 8 karakter';
            }

            $rows[] = [
                'full_name' => $name,
                'email' => $email,
                'branch_id' => $branchId,
                'branch_name' => $branch,
                'department_id' => $departmentId,
                'department_name' => $dept,
                'position_id' => $positionId,
                'position_name' => $pos,
                'employment_status' => $employment,
                'status' => 'active',
                'password' => $password,
                'errors' => $errors,
            ];
        }

        return response()->json([
            'rows' => $rows,
            'summary' => [
                'total' => count($rows),
                'valid' => collect($rows)->filter(fn (array $r): bool => $r['errors'] === [])->count(),
            ],
        ]);
    }

    /**
     * Build a case-insensitive name → id lookup from an id-keyed-by-name map.
     *
     * @param  Collection<string, int>  $pairs
     * @return array<string, int>
     */
    private function nameIdMap($pairs): array
    {
        $map = [];
        foreach ($pairs as $name => $id) {
            $map[mb_strtolower(trim((string) $name))] = (int) $id;
        }

        return $map;
    }

    /**
     * Resolve a provided name to an id; records an error when it is non-empty
     * but unknown. An empty value resolves to null with no error (optional).
     *
     * @param  array<string, int>  $map
     * @param  array<string, string>  $errors
     */
    private function resolveName(string $value, array $map, array &$errors, string $key, string $message): ?int
    {
        if ($value === '') {
            return null;
        }

        $id = $map[mb_strtolower($value)] ?? null;
        if ($id === null) {
            $errors[$key] = $message;
        }

        return $id;
    }

    /**
     * Whether a parsed row is the template's header row rather than data.
     *
     * @param  array<int, mixed>  $row
     */
    private function looksLikeHeader(array $row): bool
    {
        $first = mb_strtolower(trim((string) (array_values($row)[0] ?? '')));

        return $first === 'nama_lengkap' || $first === 'nama lengkap' || $first === 'nama';
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
            ->with(['position:id,name', 'department:id,name', 'branch:id,name'])
            ->orderBy('full_name')
            ->get(['id', 'public_id', 'full_name', 'employee_number', 'email', 'phone', 'position_id', 'department_id', 'branch_id', 'manager_id', 'join_date', 'is_top_approver']);

        $names = $employees->pluck('full_name', 'id');

        return Inertia::render('avana/employees/org-chart', [
            'nodes' => $employees->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'route_key' => $employee->public_id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
                'email' => $employee->email,
                'phone' => $employee->phone,
                'position' => $employee->position?->name,
                'department' => $employee->department?->name,
                'branch' => $employee->branch?->name,
                'join_date' => $employee->join_date?->locale('id')->translatedFormat('d M Y'),
                'manager_id' => $employee->manager_id,
                // The company head legitimately reports to nobody; anyone else
                // without a manager is a gap the chart should point out.
                'is_top_approver' => (bool) $employee->is_top_approver,
                'manager_name' => $employee->manager_id !== null ? $names->get($employee->manager_id) : null,
            ])->values(),
            // Whether the reporting line can be rearranged from here, rather
            // than one employee form at a time. Checked by permission code
            // because EmployeePolicy::update needs a concrete employee, and the
            // answer here is about the screen, not about one row.
            'canManage' => $request->user()->hasPermissionTo('employee.update'),
        ]);
    }

    /**
     * Move one employee under a different manager, straight from the chart.
     *
     * Building the reporting line by opening each employee's form in turn made
     * the shape of the company invisible while you worked on it. Here the chart
     * redraws as you go, which is the whole point of the screen.
     *
     * Accepts the same three answers the employee form does: a colleague, the
     * "Tidak ada" sentinel that declares an approver puncak, or "Belum
     * ditentukan" for somebody whose line is not decided yet.
     */
    public function updateManager(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $employee);
        $this->authorize('update', $employee);

        $validated = $request->validate([
            'manager_id' => ['nullable', 'string'],
        ]);

        $choice = $validated['manager_id'] ?? Employee::UNASSIGNED_MANAGER;

        if ($choice === Employee::NO_MANAGER) {
            $employee->update(['manager_id' => null, 'is_top_approver' => true]);

            return back()->with('success', $employee->full_name.' ditandai sebagai approver puncak.');
        }

        if ($choice === Employee::UNASSIGNED_MANAGER || $choice === '') {
            $employee->update(['manager_id' => null, 'is_top_approver' => false]);

            return back()->with('success', 'Atasan '.$employee->full_name.' dikosongkan.');
        }

        $manager = Employee::forTenant($employee->tenant_id)->whereKey((int) $choice)->first();

        abort_if($manager === null, 404);

        // The one move with no sensible reading: reporting to yourself.
        if ($manager->id === $employee->id) {
            return back()->with('error', $employee->full_name.' tidak bisa jadi atasannya sendiri.');
        }

        // Picking somebody already below you is how you swap two people, and it
        // is the move an org chart is most often opened for — promoting the
        // deputy over the person they reported to. Naively pointing the arrow
        // would close the chain into a loop, so the new manager first takes the
        // place the employee is leaving. Their ancestors become exactly the
        // employee's old ancestors, which by definition exclude the employee and
        // everyone under them, so the result cannot loop.
        if ($this->reportsTo($manager, $employee)) {
            DB::transaction(function () use ($employee, $manager): void {
                $manager->update([
                    'manager_id' => $employee->manager_id,
                    'is_top_approver' => $employee->is_top_approver,
                ]);

                $employee->update(['manager_id' => $manager->id, 'is_top_approver' => false]);
            });

            return back()->with('success', $manager->full_name.' dan '.$employee->full_name.' bertukar posisi.');
        }

        $employee->update(['manager_id' => $manager->id, 'is_top_approver' => false]);

        return back()->with('success', 'Atasan '.$employee->full_name.' sekarang '.$manager->full_name.'.');
    }

    /**
     * Whether $candidate sits anywhere below $ancestor in the reporting chain.
     *
     * Walks upward from the candidate rather than downward from the ancestor:
     * one row per level instead of the whole subtree. The visited set stops a
     * loop that already exists in the data from hanging the request.
     */
    private function reportsTo(Employee $candidate, Employee $ancestor): bool
    {
        $seen = [];
        $current = $candidate->manager_id;

        while ($current !== null && ! isset($seen[$current])) {
            if ($current === $ancestor->id) {
                return true;
            }

            $seen[$current] = true;
            $current = Employee::whereKey($current)->value('manager_id');
        }

        return false;
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
            'salaryMaster:id,code,category',
            'bpjsProfile',
            'taxProfile',
            'contracts' => fn ($query) => $query->latest('start_date')->latest('id'),
            'assetAssignments' => fn ($query) => $query
                ->whereNull('returned_date')
                ->with('asset:id,code,name,category,condition')
                ->latest('assigned_date'),
            'documents' => fn ($query) => $query->latest('id'),
            'leaveRequests' => fn ($query) => $query
                ->with('leaveType:id,name')
                ->latest('start_date')
                ->limit(20),
            'payrollItems' => fn ($query) => $query
                ->with(['period:id,name,pay_date', 'run:id,status'])
                ->latest('id')
                ->limit(12),
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
            'user:id,status',
            'user.roles:id',
            // Everything below lives on its own table, and the resource drops a
            // field whose relation was not loaded. Leave them out and the BPJS
            // numbers, the PTKP status and the contract all render blank on a
            // form meant to correct them — which reads as "it was never saved",
            // and re-typing a contract number to fix that opens a second
            // contract instead of mending the first, since the sync is keyed on
            // the number.
            'bpjsProfile',
            'taxProfile',
            'contracts' => fn ($query) => $query->latest('start_date')->latest('id'),
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
        $roleId = $data['role_id'] ?? null;
        $bpjs = $this->pullBpjsNumbers($data);
        $ptkp = $this->pullPtkpStatus($data);
        $contract = $this->pullContract($data);
        unset($data['password'], $data['role_id']);

        $employee->update($data);

        $this->syncBpjsNumbers($employee, $bpjs);
        $this->syncPtkpStatus($employee, $ptkp);
        $this->syncContract($employee, $contract);
        $this->syncEmployeeLogin($employee, $password, $request->user()->tenant_id, $roleId !== null ? (int) $roleId : null);

        return redirect()->route('avana.employees.index')
            ->with('success', 'Karyawan berhasil diperbarui');
    }

    /**
     * Take the BPJS membership numbers out of the employee payload — they are
     * stored on the employee's BPJS profile, not on the employee row.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>|null null when the form did not carry them
     */
    private function pullBpjsNumbers(array &$data): ?array
    {
        $numbers = [];

        // Only the numbers the payload actually carried. Defaulting the missing
        // one to null let a request that sent a single number erase the other,
        // which the employee form never intends — it always sends both, so a
        // partial payload comes from somewhere that simply does not manage the
        // field it left out.
        foreach (['bpjs_kesehatan_number', 'bpjs_ketenagakerjaan_number'] as $key) {
            if (array_key_exists($key, $data)) {
                $numbers[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        return $numbers === [] ? null : $numbers;
    }

    /**
     * Write the membership numbers onto the employee's BPJS profile, creating
     * the profile when the employee did not have one yet.
     *
     * @param  array<string, string|null>|null  $numbers
     */
    /**
     * Take the PTKP status out of the employee payload.
     *
     * It lives on the tax profile, not the employee row, but it is the one
     * tax field HR fills in while hiring — every PPh 21 figure is computed
     * from it, so leaving it to a separate screen means payroll runs on a
     * default nobody chose.
     *
     * @param  array<string, mixed>  $data
     */
    private function pullPtkpStatus(array &$data): ?string
    {
        if (! array_key_exists('ptkp_status', $data)) {
            return null;
        }

        $status = $data['ptkp_status'];
        unset($data['ptkp_status']);

        return is_string($status) && $status !== '' ? $status : null;
    }

    /**
     * Write the PTKP status onto the employee's tax profile, creating the
     * profile when the employee did not have one yet.
     */
    private function syncPtkpStatus(Employee $employee, ?string $status): void
    {
        if ($status === null) {
            return;
        }

        TaxProfile::updateOrCreate(
            ['tenant_id' => $employee->tenant_id, 'employee_id' => $employee->id],
            ['ptkp_status' => $status],
        );
    }

    private function syncBpjsNumbers(Employee $employee, ?array $numbers): void
    {
        if ($numbers === null) {
            return;
        }

        EmployeeBpjsProfile::updateOrCreate(
            ['tenant_id' => $employee->tenant_id, 'employee_id' => $employee->id],
            $numbers,
        );
    }

    /**
     * Take the contract details out of the employee payload.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>|null null when the form carried no contract
     */
    private function pullContract(array &$data): ?array
    {
        $keys = ['contract_number', 'contract_type', 'contract_start_date', 'contract_end_date'];
        $contract = [];

        foreach ($keys as $key) {
            if (array_key_exists($key, $data)) {
                $contract[$key] = $data[$key];
                unset($data[$key]);
            }
        }

        return $contract === [] ? null : $contract;
    }

    /**
     * Record the contract typed on the employee form so it shows on the Kontrak
     * screen without being entered a second time.
     *
     * Keyed on the contract number, so editing an employee corrects the same
     * contract rather than stacking duplicates; a blank number leaves whatever
     * the Kontrak screen already holds untouched.
     *
     * @param  array<string, mixed>|null  $contract
     */
    private function syncContract(Employee $employee, ?array $contract): void
    {
        $number = trim((string) ($contract['contract_number'] ?? ''));

        if ($contract === null || $number === '') {
            return;
        }

        EmployeeContract::updateOrCreate(
            ['tenant_id' => $employee->tenant_id, 'contract_number' => $number],
            [
                'employee_id' => $employee->id,
                'contract_type' => ContractType::normalise($contract['contract_type'] ?? null),
                'start_date' => $contract['contract_start_date'] ?? null,
                'end_date' => $contract['contract_end_date'] ?? null,
                // The Kontrak screen requires a figure; the employee's own basic
                // wage is the honest one, and it stays editable there.
                'basic_salary' => SalaryCompliance::monthlyWage($employee, (int) $employee->tenant_id)['basic'],
                'status' => 'active',
            ],
        );
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
     * Create or reset the employee's mobile-app login account and bind its
     * access role. The role determines the employee's Hak Akses (menu/permission)
     * — picking one on the form assigns it directly, so a whole team can inherit
     * the same access just by being given the same role.
     *
     * Rules: an existing account keeps its login; a password resets it and a
     * chosen role replaces its role. A brand-new account is only created when a
     * password is set (a login needs one), and it takes the role picked on the
     * form — the form request makes that pick mandatory, because a tenant names
     * its own roles and there is no built-in "employee" role to fall back on.
     */
    private function syncEmployeeLogin(Employee $employee, ?string $password, int $tenantId, ?int $roleId = null): void
    {
        $role = $roleId !== null
            ? Role::where('tenant_id', $tenantId)->whereKey($roleId)->first()
            : null;

        $employee->loadMissing('user.roles');

        if ($employee->user_id !== null) {
            if (filled($password)) {
                $employee->user?->update(['password' => $password]);
            }

            // The form shows one role, but an account may hold several — the
            // director carries manager and employee both. Saving the employee
            // with the role the form happened to display would sync the account
            // down to that one and drop the rest, rewriting someone's access
            // nobody asked to change. Only a role the account does not already
            // hold is a real choice, so only that is written.
            $user = $employee->user;

            if ($role !== null && $user !== null && ! $user->roles->contains('id', $role->id)) {
                $user->roles()->sync([$role->id]);
            }

            return;
        }

        if (blank($password)) {
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

        if ($role !== null) {
            $user->roles()->sync([$role->id]);
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
     * Reset (release) the employee's bound mobile device so they can sign in
     * from a new phone. The next login re-binds whatever device signs in first.
     */
    public function resetDevice(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $employee);
        $this->authorize('update', $employee);

        $tenantId = (int) $employee->tenant_id;

        // Hiding the button is not a control: with binding off there is no
        // slot to free, and the reset would still revoke every token the
        // account holds.
        if (! AttendancePolicy::resolve($tenantId)->device_binding_enabled) {
            return back()->with('error', 'Kebijakan "1 perangkat 1 akun" sedang nonaktif, tidak ada perangkat yang perlu direset.');
        }

        // The mobile app binds the device to the login it authenticates by
        // EMAIL, which is not always the same users row as employees.user_id.
        // Reset the device for the linked login AND any login sharing the
        // employee's email, so a mismatched link can never leave an account
        // device-locked with no way to reset it.
        $userIds = User::where('tenant_id', $employee->tenant_id)
            ->where(function ($query) use ($employee): void {
                $query->where('id', $employee->user_id)
                    ->when($employee->email !== null, fn ($q) => $q->orWhere('email', $employee->email));
            })
            ->pluck('id');

        if ($userIds->isEmpty()) {
            return back()->with('error', 'Karyawan ini belum punya akun login.');
        }

        UserDevice::whereIn('user_id', $userIds)
            ->where('status', 'active')
            ->update([
                'status' => 'reset',
                'reset_by' => $request->user()->id,
                'reset_at' => now(),
            ]);

        // Freeing the binding slot is not the same as revoking access: the old
        // phone still holds a valid token and could refresh it for weeks. Bump
        // the token version so every token it issued stops working now.
        User::whereIn('id', $userIds)->increment('token_version');

        return back()->with('success', 'Perangkat direset. HP lama otomatis keluar, karyawan dapat login dari HP baru.');
    }

    /**
     * Activate or deactivate the employee's login account. A deactivated account
     * cannot sign in to the mobile app.
     */
    public function toggleAccount(Request $request, Employee $employee): RedirectResponse
    {
        $this->ensureTenantOwnership($request, $employee);
        $this->authorize('update', $employee);

        $user = $employee->user;

        if ($user === null) {
            return back()->with('error', 'Karyawan ini belum punya akun login.');
        }

        abort_if($user->id === $request->user()->id, 403, 'Tidak dapat menonaktifkan akun sendiri.');

        $user->update([
            'status' => $user->status === 'active' ? 'inactive' : 'active',
        ]);

        return back()->with('success', $user->status === 'active'
            ? 'Akun karyawan diaktifkan'
            : 'Akun karyawan dinonaktifkan');
    }

    /**
     * Delete several employees at once from the list's selection checkboxes.
     * Only rows the caller may delete within their own tenant are removed.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
        ]);

        $employees = Employee::forTenant($request->user()->tenant_id)
            ->whereIn('id', $data['ids'])
            ->get();

        $deleted = 0;
        foreach ($employees as $employee) {
            $this->authorize('delete', $employee);
            $employee->delete();
            $deleted++;
        }

        return back()->with('success', "{$deleted} karyawan dihapus");
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
            // Which Master Gaji the employee is paid from — payroll reads it,
            // so it belongs on the employee form rather than only on the
            // Master Gaji screen's assign panel.
            'salaryMasters' => SalaryMaster::forTenant($tenantId)
                ->where('is_active', true)
                ->orderBy('code')
                ->get(['id', 'code', 'category'])
                ->map(fn (SalaryMaster $master): array => [
                    'id' => $master->id,
                    'name' => $master->code.($master->category !== null ? ' · '.$master->category : ''),
                ]),
            // `can_access_mobile` rides along so the picker can warn that the role
            // has no phone access — the dropdown sits in the mobile-account section.
            'roles' => Role::where('tenant_id', $tenantId)->select('id', 'name', 'can_access_mobile')->orderBy('name')->get(),
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
            // The same three kinds the Kontrak screen offers, so a contract
            // typed here is the contract that screen can read back.
            'contractTypes' => ContractType::options(),
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

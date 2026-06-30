<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeCareerHistory;
use App\Models\Position;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MovementController extends Controller
{
    use AuthorizesRequests;

    /**
     * Allowed movement types persisted on the career history row.
     *
     * @var array<int, string>
     */
    private const MOVEMENT_TYPES = [
        'mutation', 'promotion', 'demotion', 'transfer', 'resign', 'terminate',
    ];

    /**
     * Movement types that deactivate the employee on apply.
     *
     * @var array<int, string>
     */
    private const EXIT_TYPES = ['resign', 'terminate'];

    /**
     * Display a server-side paginated list of employee career movements.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $tenantId = $request->user()->tenant_id;

        $positionNames = Position::forTenant($tenantId)->pluck('name', 'id');
        $departmentNames = Department::forTenant($tenantId)->pluck('name', 'id');
        $branchNames = Branch::forTenant($tenantId)->pluck('name', 'id');

        $movements = EmployeeCareerHistory::query()
            ->forTenant($tenantId)
            ->with('employee:id,full_name,employee_number')
            ->when($request->query('search'), function ($query, $search): void {
                $query->whereHas('employee', function ($q) use ($search): void {
                    $q->where('full_name', 'like', "%{$search}%");
                });
            })
            ->when($request->query('movement_type'), fn ($q, $type) => $q->where('movement_type', $type))
            ->orderByDesc('effective_date')
            ->orderByDesc('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return Inertia::render('avana/mutasi/index', [
            'movements' => [
                'data' => collect($movements->items())
                    ->map(fn (EmployeeCareerHistory $movement): array => $this->transformMovement(
                        $movement,
                        $positionNames,
                        $departmentNames,
                        $branchNames,
                    ))
                    ->all(),
                'meta' => [
                    'current_page' => $movements->currentPage(),
                    'last_page' => $movements->lastPage(),
                    'per_page' => $movements->perPage(),
                    'total' => $movements->total(),
                    'from' => $movements->firstItem(),
                    'to' => $movements->lastItem(),
                ],
            ],
            'employees' => Employee::forTenant($tenantId)
                ->select('id', 'full_name', 'employee_number', 'position_id', 'department_id', 'branch_id')
                ->orderBy('full_name')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                    'position_id' => $employee->position_id,
                    'department_id' => $employee->department_id,
                    'branch_id' => $employee->branch_id,
                ])
                ->all(),
            'positions' => Position::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
            'departments' => Department::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
            'branches' => Branch::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
            'filters' => $request->only(['search', 'movement_type', 'per_page']),
        ]);
    }

    /**
     * Show the form for recording a new employee career movement.
     */
    public function create(Request $request): Response
    {
        $this->authorize('viewAny', Employee::class);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/mutasi/create', [
            'employees' => Employee::forTenant($tenantId)
                ->select('id', 'full_name', 'employee_number', 'position_id', 'department_id', 'branch_id')
                ->orderBy('full_name')
                ->get()
                ->map(fn (Employee $employee): array => [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'employee_number' => $employee->employee_number,
                    'position_id' => $employee->position_id,
                    'department_id' => $employee->department_id,
                    'branch_id' => $employee->branch_id,
                ])
                ->all(),
            'positions' => Position::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
            'departments' => Department::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
            'branches' => Branch::forTenant($tenantId)->select('id', 'name')->orderBy('name')->get(),
        ]);
    }

    /**
     * Record a movement, apply it to the employee, and log the career history.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize('viewAny', Employee::class);

        $tenantId = $request->user()->tenant_id;

        $validated = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'movement_type' => ['required', Rule::in(self::MOVEMENT_TYPES)],
            'effective_date' => ['required', 'date'],
            'position_id' => ['nullable', Rule::exists('positions', 'id')->where('tenant_id', $tenantId)],
            'department_id' => ['nullable', Rule::exists('departments', 'id')->where('tenant_id', $tenantId)],
            'branch_id' => ['nullable', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'employment_status' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        $employee = Employee::forTenant($tenantId)->findOrFail($validated['employee_id']);

        EmployeeCareerHistory::create([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'movement_type' => $validated['movement_type'],
            'effective_date' => $validated['effective_date'],
            'previous_position_id' => $employee->position_id,
            'position_id' => $validated['position_id'] ?? $employee->position_id,
            'previous_department_id' => $employee->department_id,
            'department_id' => $validated['department_id'] ?? $employee->department_id,
            'previous_branch_id' => $employee->branch_id,
            'branch_id' => $validated['branch_id'] ?? $employee->branch_id,
            'previous_employment_status' => $employee->employment_status,
            'employment_status' => $validated['employment_status'] ?? $employee->employment_status,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $this->applyToEmployee($employee, $validated);

        return redirect()->route('avana.mutasi')
            ->with('success', 'Mutasi karyawan dicatat');
    }

    /**
     * Apply the validated movement onto the employee record.
     *
     * @param  array<string, mixed>  $validated
     */
    private function applyToEmployee(Employee $employee, array $validated): void
    {
        if (! empty($validated['position_id'])) {
            $employee->position_id = $validated['position_id'];
        }

        if (! empty($validated['department_id'])) {
            $employee->department_id = $validated['department_id'];
        }

        if (! empty($validated['branch_id'])) {
            $employee->branch_id = $validated['branch_id'];
        }

        if (in_array($validated['movement_type'], self::EXIT_TYPES, true)) {
            $employee->status = 'inactive';

            if (! empty($validated['employment_status'])) {
                $employee->employment_status = $validated['employment_status'];
            }
        } elseif (! empty($validated['employment_status'])) {
            $employee->employment_status = $validated['employment_status'];
        }

        $employee->save();
    }

    /**
     * Build the row shape consumed by the Mutasi DataTable.
     *
     * @param  Collection<int, string>  $positionNames
     * @param  Collection<int, string>  $departmentNames
     * @param  Collection<int, string>  $branchNames
     * @return array<string, mixed>
     */
    private function transformMovement(
        EmployeeCareerHistory $movement,
        Collection $positionNames,
        Collection $departmentNames,
        Collection $branchNames,
    ): array {
        $changes = [];
        $this->appendChange($changes, 'Posisi', $positionNames, $movement->previous_position_id, $movement->position_id);
        $this->appendChange($changes, 'Departemen', $departmentNames, $movement->previous_department_id, $movement->department_id);
        $this->appendChange($changes, 'Cabang', $branchNames, $movement->previous_branch_id, $movement->branch_id);

        return [
            'id' => $movement->id,
            'employee' => [
                'name' => $movement->employee?->full_name,
                'employee_number' => $movement->employee?->employee_number,
            ],
            'movement_type' => $movement->movement_type,
            'effective_date' => $movement->effective_date?->toDateString(),
            'changes' => $changes,
            'employment_status' => $movement->employment_status,
            'notes' => $movement->notes,
            'created_at' => $movement->created_at?->toDateTimeString(),
        ];
    }

    /**
     * Append a from→to change entry when the value actually changed.
     *
     * @param  array<int, array<string, ?string>>  $changes
     * @param  Collection<int, string>  $names
     */
    private function appendChange(array &$changes, string $label, Collection $names, ?int $fromId, ?int $toId): void
    {
        if ((int) $fromId === (int) $toId) {
            return;
        }

        $changes[] = [
            'label' => $label,
            'from' => $fromId !== null ? $names->get($fromId) : null,
            'to' => $toId !== null ? $names->get($toId) : null,
        ];
    }
}

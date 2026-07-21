<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\OnboardingProgram;
use App\Models\OnboardingTask;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    /**
     * Roles that may always manage onboarding within their tenant.
     *
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'onboarding';

    /**
     * Display the onboarding programs for new hires with their checklists.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = $request->user()->tenant_id;

        $programs = OnboardingProgram::forTenant($tenantId)
            ->with([
                'employee:id,full_name,employee_number',
                'tasks' => fn ($query) => $query->orderBy('id'),
            ])
            ->latest('id')
            ->get()
            ->map(fn (OnboardingProgram $program): array => $this->transformProgram($program));

        return Inertia::render('avana/onboarding/index', [
            'programs' => $programs,
            'employees' => $this->employeeOptions($tenantId),
            'kpis' => [
                'active' => $programs->where('status', 'in_progress')->count(),
                'completed' => $programs->where('status', 'completed')->count(),
                'pending_tasks' => OnboardingTask::forTenant($tenantId)->where('is_done', false)->count(),
            ],
        ]);
    }

    /**
     * Create a new onboarding program for an employee.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'start_date' => ['nullable', 'date'],
        ]);

        OnboardingProgram::create([
            'tenant_id' => $tenantId,
            'employee_id' => $data['employee_id'],
            'start_date' => $data['start_date'] ?? null,
            'status' => 'in_progress',
        ]);

        return redirect()->route('avana.onboarding')
            ->with('success', 'Program onboarding berhasil dibuat');
    }

    /**
     * Add a task to an onboarding program and recompute its status.
     */
    public function storeTask(Request $request, OnboardingProgram $program): RedirectResponse
    {
        $this->ensureCan($request, 'create');
        $this->ensureTenantOwnership($request, $program);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
        ]);

        $program->tasks()->create([
            'tenant_id' => $program->tenant_id,
            'title' => $data['title'],
            'category' => $data['category'] ?? null,
            'due_date' => $data['due_date'] ?? null,
            'is_done' => false,
        ]);

        $this->recomputeProgramStatus($program);

        return back()->with('success', 'Tugas onboarding ditambahkan');
    }

    /**
     * Toggle a task's done flag and recompute the parent program status.
     */
    public function toggleTask(Request $request, OnboardingTask $task): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $task);

        $task->update(['is_done' => ! $task->is_done]);

        $this->recomputeProgramStatus($task->program);

        return back()->with('success', 'Status tugas diperbarui');
    }

    /**
     * Delete an onboarding program together with its tasks.
     */
    public function destroy(Request $request, OnboardingProgram $program): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $program);

        $program->delete();

        return back()->with('success', 'Program onboarding dihapus');
    }

    /**
     * Recompute a program's status: completed when every task is done.
     */
    private function recomputeProgramStatus(OnboardingProgram $program): void
    {
        $total = $program->tasks()->count();
        $done = $program->tasks()->where('is_done', true)->count();

        $program->update([
            'status' => $total > 0 && $done === $total ? 'completed' : 'in_progress',
        ]);
    }

    /**
     * Build the card shape consumed by the onboarding board.
     *
     * @return array<string, mixed>
     */
    private function transformProgram(OnboardingProgram $program): array
    {
        $tasks = $program->tasks->map(fn (OnboardingTask $task): array => [
            'id' => $task->id,
            'title' => $task->title,
            'category' => $task->category,
            'due_date' => $task->due_date?->toDateString(),
            'is_done' => (bool) $task->is_done,
        ]);

        $doneCount = $tasks->where('is_done', true)->count();

        return [
            'id' => $program->id,
            'employee_id' => $program->employee_id,
            'employee' => $program->employee ? [
                'name' => $program->employee->full_name,
                'employee_number' => $program->employee->employee_number,
            ] : null,
            'start_date' => $program->start_date?->toDateString(),
            'status' => $program->status,
            'tasks' => $tasks->all(),
            'tasks_total' => $tasks->count(),
            'tasks_done' => $doneCount,
        ];
    }

    /**
     * Build the tenant's selectable employee options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function employeeOptions(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'employee_number' => $employee->employee_number,
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, OnboardingProgram|OnboardingTask $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
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

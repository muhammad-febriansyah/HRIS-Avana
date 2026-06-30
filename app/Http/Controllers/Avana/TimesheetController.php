<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\Timesheet;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TimesheetController extends Controller
{
    /**
     * Roles that may always manage timesheets within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Display projects together with their logged timesheet entries.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $projectFilter = $request->query('project_id');
        $employeeFilter = $request->query('employee_id');

        $entries = Timesheet::forTenant($tenantId)
            ->with(['employee:id,full_name,employee_number', 'project:id,name,code'])
            ->when($projectFilter, fn ($query, $value) => $query->where('project_id', $value))
            ->when($employeeFilter, fn ($query, $value) => $query->where('employee_id', $value))
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get()
            ->map(fn (Timesheet $timesheet): array => $this->shapeEntry($timesheet));

        $projects = Project::forTenant($tenantId)
            ->withCount('timesheets')
            ->orderBy('name')
            ->get()
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'status' => $project->status,
                'timesheets_count' => $project->timesheets_count,
            ]);

        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $weekEnd = $weekStart->copy()->addDays(6);

        $weekHours = (float) Timesheet::forTenant($tenantId)
            ->whereBetween('date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->sum('hours');

        return Inertia::render('avana/timesheet/index', [
            'entries' => $entries,
            'projects' => $projects,
            'employees' => $this->employeeOptions($tenantId),
            'filters' => [
                'project_id' => $projectFilter,
                'employee_id' => $employeeFilter,
            ],
            'kpis' => [
                'week_hours' => $weekHours,
                'active_projects' => $projects->where('status', 'active')->count(),
                'total_hours' => round((float) $entries->sum('hours'), 2),
                'total_entries' => $entries->count(),
            ],
        ]);
    }

    /**
     * Persist a new project under the acting user's tenant.
     */
    public function storeProject(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:50'],
            'status' => ['required', 'in:active,archived'],
        ]);

        Project::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.timesheet')
            ->with('success', 'Proyek berhasil ditambahkan');
    }

    /**
     * Log a timesheet entry for an employee on a project under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'project_id' => ['required', Rule::exists('projects', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'task' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        Timesheet::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.timesheet')
            ->with('success', 'Entri timesheet ditambahkan');
    }

    /**
     * Delete a timesheet entry.
     */
    public function destroy(Request $request, Timesheet $timesheet): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $timesheet);

        $timesheet->delete();

        return back()->with('success', 'Entri timesheet dihapus');
    }

    /**
     * Build the row shape consumed by the timesheet entries table.
     *
     * @return array<string, mixed>
     */
    private function shapeEntry(Timesheet $timesheet): array
    {
        return [
            'id' => $timesheet->id,
            'employee' => $timesheet->employee?->full_name,
            'employee_id' => $timesheet->employee_id,
            'project' => $timesheet->project?->name,
            'project_id' => $timesheet->project_id,
            'date' => $timesheet->date?->toDateString(),
            'hours' => (float) $timesheet->hours,
            'task' => $timesheet->task,
            'notes' => $timesheet->notes,
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
    private function ensureTenantOwnership(Request $request, Timesheet $timesheet): void
    {
        abort_if((int) $timesheet->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds an employee permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasEmployeePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'employee.'));

        abort_unless($isPrivileged || $hasEmployeePermission, 403);
    }
}

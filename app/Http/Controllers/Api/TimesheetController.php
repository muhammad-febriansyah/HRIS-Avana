<?php

namespace App\Http\Controllers\Api;

use App\Concerns\ResolvesApiEmployee;
use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Timesheet;
use App\Services\ApprovalEngine;
use App\Services\TimesheetApproval;
use App\Support\FeatureGate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Employee self-service timesheets: the hours a person logs against a project
 * from their phone, and the projects they are allowed to log them against.
 *
 * Filings arrive pending and are decided by the manager — in the MSS queue on
 * the phone, or in the web approval centre. Only an approved entry is priced,
 * so nothing an employee types can move a project's reported margin on its own.
 */
class TimesheetController extends Controller
{
    use ResolvesApiEmployee;

    /** A day cannot hold more logged hours than it has. */
    private const MAX_DAILY_HOURS = 24.0;

    private const FEATURE_MESSAGE = 'Fitur timesheet tidak aktif untuk perusahaan Anda.';

    /**
     * The caller's own entries, newest first, with the running totals the list
     * header shows.
     */
    public function index(Request $request): JsonResponse
    {
        FeatureGate::ensure($request->user(), 'timesheet', self::FEATURE_MESSAGE);

        $employee = $this->currentEmployee($request);

        $entries = Timesheet::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->with('project:id,name,code')
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->limit(200)
            ->get();

        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);
        $monthStart = Carbon::today()->startOfMonth();

        return response()->json([
            'data' => $entries->map(fn (Timesheet $timesheet): array => $this->shape($timesheet))->all(),
            'summary' => [
                'week_hours' => round((float) $entries
                    ->where('status', '!=', Timesheet::STATUS_REJECTED)
                    ->filter(fn (Timesheet $row): bool => $row->date !== null && $row->date->betweenIncluded($weekStart, $weekStart->copy()->addDays(6)))
                    ->sum('hours'), 2),
                'month_hours' => round((float) $entries
                    ->where('status', '!=', Timesheet::STATUS_REJECTED)
                    ->filter(fn (Timesheet $row): bool => $row->date !== null && $row->date->greaterThanOrEqualTo($monthStart))
                    ->sum('hours'), 2),
                'pending' => $entries->where('status', Timesheet::STATUS_PENDING)->count(),
            ],
        ]);
    }

    /**
     * The active projects this employee may log against: the ones they are
     * assigned to, plus every project that names no members at all — an
     * unassigned project is open to the whole company by design.
     */
    public function projects(Request $request): JsonResponse
    {
        FeatureGate::ensure($request->user(), 'timesheet', self::FEATURE_MESSAGE);

        $employee = $this->currentEmployee($request);

        $projects = $this->assignableProjects($employee)
            ->map(fn (Project $project): array => [
                'id' => $project->id,
                'name' => $project->name,
                'code' => $project->code,
                'client_name' => $project->client_name,
                // Rates stay on the server: an employee logging hours has no
                // business reading what the company sells them for.
                'is_billable' => (bool) $project->is_billable,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $projects]);
    }

    /** File one entry against a project. */
    public function store(Request $request): JsonResponse
    {
        FeatureGate::ensure($request->user(), 'timesheet', self::FEATURE_MESSAGE);

        $employee = $this->currentEmployee($request);
        $data = $this->validated($request, $employee);
        $project = $this->resolveProject($employee, (int) $data['project_id']);

        $this->ensureDailyHoursFit($employee, $data['date'], (float) $data['hours']);

        $timesheet = Timesheet::create([
            'tenant_id' => $employee->tenant_id,
            'employee_id' => $employee->id,
            'branch_id' => $employee->branch_id,
            'project_id' => $project->id,
            'date' => $data['date'],
            'hours' => $data['hours'],
            'task' => $data['task'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_billable' => $project->is_billable,
            'status' => Timesheet::STATUS_PENDING,
            'current_approver_id' => $employee->manager_id,
            'source' => 'mobile',
        ]);

        // A top approver (director) has nobody above them, so their own filing
        // is approved — and priced — on the spot rather than left waiting.
        if ($employee->is_top_approver) {
            TimesheetApproval::finalize($timesheet, $request->user()->id);
            $timesheet->refresh();

            return response()->json([
                'message' => 'Timesheet langsung disetujui (approver puncak)',
                'data' => $this->shape($timesheet),
            ], 201);
        }

        ApprovalEngine::start($timesheet, $employee);
        $timesheet->refresh();

        return response()->json([
            'message' => 'Timesheet terkirim, menunggu persetujuan',
            'data' => $this->shape($timesheet),
        ], 201);
    }

    /** Correct an entry that has not been decided yet. */
    public function update(Request $request, Timesheet $timesheet): JsonResponse
    {
        FeatureGate::ensure($request->user(), 'timesheet', self::FEATURE_MESSAGE);

        $employee = $this->currentEmployee($request);
        $this->ensureOwnPending($timesheet, $employee);

        $data = $this->validated($request, $employee);
        $project = $this->resolveProject($employee, (int) $data['project_id']);

        $this->ensureDailyHoursFit($employee, $data['date'], (float) $data['hours'], $timesheet->id);

        $timesheet->update([
            'project_id' => $project->id,
            'date' => $data['date'],
            'hours' => $data['hours'],
            'task' => $data['task'] ?? null,
            'notes' => $data['notes'] ?? null,
            'is_billable' => $project->is_billable,
        ]);

        $timesheet->refresh();

        return response()->json([
            'message' => 'Timesheet diperbarui',
            'data' => $this->shape($timesheet),
        ]);
    }

    /** Withdraw an entry that has not been decided yet. */
    public function destroy(Request $request, Timesheet $timesheet): JsonResponse
    {
        FeatureGate::ensure($request->user(), 'timesheet', self::FEATURE_MESSAGE);

        $employee = $this->currentEmployee($request);
        $this->ensureOwnPending($timesheet, $employee);

        $timesheet->delete();

        return response()->json(['message' => 'Timesheet dihapus']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, Employee $employee): array
    {
        return $request->validate([
            'project_id' => ['required', Rule::exists('projects', 'id')->where('tenant_id', $employee->tenant_id)],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'task' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    /**
     * The active projects the employee may file against.
     *
     * @return Collection<int, Project>
     */
    private function assignableProjects(Employee $employee)
    {
        $assignedIds = ProjectMember::query()
            ->where('employee_id', $employee->id)
            ->pluck('project_id');

        $projectsWithMembers = ProjectMember::query()
            ->where('tenant_id', $employee->tenant_id)
            ->distinct()
            ->pluck('project_id');

        return Project::forTenant($employee->tenant_id)
            ->where('status', 'active')
            ->where(fn ($query) => $query
                ->whereIn('id', $assignedIds)
                ->orWhereNotIn('id', $projectsWithMembers))
            ->orderBy('name')
            ->get();
    }

    /**
     * Resolve the project the filing names, refusing one the employee is not
     * assigned to — the assignment is what authorises the filing.
     */
    private function resolveProject(Employee $employee, int $projectId): Project
    {
        $project = $this->assignableProjects($employee)->firstWhere('id', $projectId);

        if ($project === null) {
            throw ValidationException::withMessages([
                'project_id' => 'Anda tidak terdaftar di proyek ini.',
            ]);
        }

        return $project;
    }

    /**
     * Abort unless the entry is the caller's own and still undecided — an
     * approved entry has already been priced and reported on.
     */
    private function ensureOwnPending(Timesheet $timesheet, Employee $employee): void
    {
        abort_if(
            (int) $timesheet->tenant_id !== (int) $employee->tenant_id
                || (int) $timesheet->employee_id !== (int) $employee->id,
            404,
            'Entri timesheet tidak ditemukan.',
        );

        abort_if(
            $timesheet->status !== Timesheet::STATUS_PENDING,
            422,
            'Entri yang sudah diputuskan tidak bisa diubah. Hubungi HR untuk koreksi.',
        );
    }

    /**
     * Refuse a filing that would push the day past 24 hours.
     */
    private function ensureDailyHoursFit(Employee $employee, string $date, float $hours, ?int $ignoreId = null): void
    {
        $logged = (float) Timesheet::forTenant($employee->tenant_id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date)
            ->where('status', '!=', Timesheet::STATUS_REJECTED)
            ->when($ignoreId !== null, fn ($query) => $query->where('id', '!=', $ignoreId))
            ->sum('hours');

        if ($logged + $hours > self::MAX_DAILY_HOURS) {
            throw ValidationException::withMessages([
                'hours' => 'Total jam pada tanggal ini jadi '.($logged + $hours).' jam — maksimal '.self::MAX_DAILY_HOURS.' jam sehari.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function shape(Timesheet $timesheet): array
    {
        return [
            'id' => $timesheet->id,
            'project_id' => $timesheet->project_id,
            'project' => $timesheet->project?->name,
            'project_code' => $timesheet->project?->code,
            'date' => $timesheet->date?->toDateString(),
            'hours' => (float) $timesheet->hours,
            'task' => $timesheet->task,
            'notes' => $timesheet->notes,
            'status' => $timesheet->status,
            'is_billable' => (bool) $timesheet->is_billable,
            'rejection_reason' => $timesheet->rejection_reason,
        ];
    }
}

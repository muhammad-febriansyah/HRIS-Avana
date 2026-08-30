<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\Timesheet;
use App\Models\User;
use App\Services\ApprovalEngine;
use App\Services\TimesheetApproval;
use App\Services\TimesheetCosting;
use App\Support\FeatureGate;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Project time tracking: the projects a tenant sells or runs internally, the
 * hours logged against them from this screen or from the phone, and the
 * profitability those hours add up to.
 *
 * Hours filed here by an HR desk are approved on the spot — the person typing
 * them has already accepted them. Hours filed from the phone arrive pending and
 * are decided here or in the approval centre, and only approved hours are
 * costed, billed or reported.
 */
class TimesheetController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'timesheet';

    /** A day cannot hold more logged hours than it has. */
    private const MAX_DAILY_HOURS = 24.0;

    /**
     * Display projects, their logged entries and the profitability the approved
     * hours add up to.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;
        $filters = $this->resolveFilters($request);

        $entries = $this->entryQuery($tenantId, $filters)
            ->with(['employee:id,full_name,employee_number', 'project:id,name,code', 'approver:id,name'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();

        $projects = Project::forTenant($tenantId)
            ->with(['manager:id,full_name', 'members.employee:id,full_name,employee_number'])
            ->withCount('timesheets')
            ->orderBy('name')
            ->get();

        // The report is drawn from the same window the entry filter uses, minus
        // the project/employee narrowing: an approver filtering to one person
        // still wants the project totals they are judging that person against.
        $reportEntries = $this->entryQuery($tenantId, [...$filters, 'project_id' => null, 'employee_id' => null, 'status' => null])
            ->approved()
            ->with(['employee:id,full_name', 'project:id,name,code'])
            ->get();

        $weekStart = Carbon::today()->startOfWeek(Carbon::MONDAY);

        return Inertia::render('avana/timesheet/index', [
            'entries' => $entries->map(fn (Timesheet $timesheet): array => $this->shapeEntry($timesheet))->all(),
            'projects' => $projects->map(fn (Project $project): array => $this->shapeProject($project))->all(),
            'employees' => $this->employeeOptions($tenantId),
            'filters' => $filters,
            'kpis' => [
                'week_hours' => round((float) Timesheet::forTenant($tenantId)
                    ->approved()
                    ->whereBetween('date', [$weekStart->toDateString(), $weekStart->copy()->addDays(6)->toDateString()])
                    ->sum('hours'), 2),
                'active_projects' => $projects->where('status', 'active')->count(),
                'pending_entries' => Timesheet::forTenant($tenantId)->where('status', Timesheet::STATUS_PENDING)->count(),
                'total_hours' => round((float) $entries->where('status', Timesheet::STATUS_APPROVED)->sum('hours'), 2),
                'total_entries' => $entries->count(),
                'bill_amount' => round((float) $reportEntries->sum('bill_amount'), 2),
                'cost_amount' => round((float) $reportEntries->sum('cost_amount'), 2),
                'margin' => round((float) $reportEntries->sum('bill_amount') - (float) $reportEntries->sum('cost_amount'), 2),
            ],
            'report' => $this->buildReport($reportEntries, $projects),
            'can' => [
                'create' => $this->can($request, 'create'),
                'update' => $this->can($request, 'update'),
                'archive' => $this->can($request, 'archive'),
                'approve' => $this->can($request, 'approve'),
                'export' => $this->can($request, 'export'),
            ],
        ]);
    }

    /**
     * Persist a new project under the acting user's tenant.
     */
    public function storeProject(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;
        $data = $this->validateProject($request, $tenantId);
        $members = $data['members'] ?? [];
        unset($data['members']);

        $project = Project::create([...$data, 'tenant_id' => $tenantId]);

        $this->syncMembers($project, $members);

        return redirect()->route('avana.timesheet')
            ->with('success', 'Proyek berhasil ditambahkan');
    }

    /**
     * Update a project and its member assignments.
     */
    public function updateProject(Request $request, Project $project): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureProjectOwnership($request, $project);

        $tenantId = (int) $request->user()->tenant_id;
        $data = $this->validateProject($request, $tenantId, $project);
        $members = $data['members'] ?? [];
        unset($data['members']);

        $project->update($data);

        $this->syncMembers($project, $members);

        return redirect()->route('avana.timesheet')
            ->with('success', 'Proyek diperbarui');
    }

    /**
     * Delete a project. Refused while entries still point at it: deleting would
     * take their hours — and the margin already reported on them — with it.
     */
    public function destroyProject(Request $request, Project $project): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureProjectOwnership($request, $project);

        if ($project->timesheets()->exists()) {
            return back()->with('error', 'Proyek masih punya entri timesheet. Arsipkan proyek ini, jangan dihapus.');
        }

        $project->delete();

        return redirect()->route('avana.timesheet')
            ->with('success', 'Proyek dihapus');
    }

    /**
     * Log a timesheet entry for an employee on a project under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = (int) $request->user()->tenant_id;
        $data = $this->validateEntry($request, $tenantId);

        $employee = Employee::forTenant($tenantId)->findOrFail($data['employee_id']);
        $project = Project::forTenant($tenantId)->findOrFail($data['project_id']);

        $this->ensureDailyHoursFit($tenantId, (int) $data['employee_id'], $data['date'], (float) $data['hours']);

        Timesheet::create([
            ...$data,
            'tenant_id' => $tenantId,
            'branch_id' => $employee->branch_id,
            'source' => 'web',
            // Typed by the desk that would otherwise be approving it, so it is
            // approved and priced immediately.
            'status' => Timesheet::STATUS_APPROVED,
            'approved_by' => $request->user()->id,
            'approved_at' => Carbon::now(),
            ...TimesheetCosting::priceFor($employee, $project, (float) $data['hours'], (bool) ($data['is_billable'] ?? true)),
        ]);

        return redirect()->route('avana.timesheet')
            ->with('success', 'Entri timesheet ditambahkan');
    }

    /**
     * Update an existing entry, re-pricing it when it is already approved.
     */
    public function update(Request $request, Timesheet $timesheet): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $timesheet);

        $tenantId = (int) $request->user()->tenant_id;
        $data = $this->validateEntry($request, $tenantId);

        $employee = Employee::forTenant($tenantId)->findOrFail($data['employee_id']);
        $project = Project::forTenant($tenantId)->findOrFail($data['project_id']);

        $this->ensureDailyHoursFit($tenantId, (int) $data['employee_id'], $data['date'], (float) $data['hours'], $timesheet->id);

        $timesheet->update([
            ...$data,
            'branch_id' => $employee->branch_id,
            ...($timesheet->status === Timesheet::STATUS_APPROVED
                ? TimesheetCosting::priceFor($employee, $project, (float) $data['hours'], (bool) ($data['is_billable'] ?? true))
                : []),
        ]);

        return redirect()->route('avana.timesheet')
            ->with('success', 'Entri timesheet diperbarui');
    }

    /**
     * Delete a timesheet entry.
     */
    public function destroy(Request $request, Timesheet $timesheet): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $timesheet);

        $timesheet->delete();

        return back()->with('success', 'Entri timesheet dihapus');
    }

    /**
     * Approve one pending entry, or every pending entry named in `ids`.
     *
     * Routed through the approval engine first, so a tenant that configured a
     * multi-step flow for timesheets advances a step here rather than jumping
     * to the end of its own workflow.
     */
    public function approve(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'approve');

        $decided = $this->decidePending($request, 'approve');

        return back()->with('success', $decided === 1
            ? 'Entri timesheet disetujui'
            : "$decided entri timesheet disetujui");
    }

    /**
     * Reject one pending entry, or every pending entry named in `ids`.
     */
    public function reject(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'approve');

        $decided = $this->decidePending($request, 'reject');

        return back()->with('success', $decided === 1
            ? 'Entri timesheet ditolak'
            : "$decided entri timesheet ditolak");
    }

    /**
     * Stream the filtered entries as CSV, for the finance desk that invoices
     * from them.
     */
    public function export(Request $request): StreamedResponse
    {
        $this->ensureCan($request, 'export');

        $tenantId = (int) $request->user()->tenant_id;
        $filters = $this->resolveFilters($request);

        $entries = $this->entryQuery($tenantId, $filters)
            ->with(['employee:id,full_name,employee_number', 'project:id,name,code'])
            ->orderBy('date')
            ->get();

        $filename = 'timesheet-'.Carbon::today()->format('Y-m-d').'.csv';

        return response()->streamDownload(function () use ($entries): void {
            $handle = fopen('php://output', 'wb');

            fputcsv($handle, [
                'Tanggal', 'NIK', 'Karyawan', 'Proyek', 'Kode Proyek', 'Tugas',
                'Jam', 'Status', 'Billable', 'Tarif Jual', 'Tarif Biaya', 'Nilai Jual', 'Nilai Biaya',
            ]);

            foreach ($entries as $entry) {
                fputcsv($handle, [
                    $entry->date?->toDateString(),
                    $entry->employee?->employee_number,
                    $entry->employee?->full_name,
                    $entry->project?->name,
                    $entry->project?->code,
                    $entry->task,
                    (float) $entry->hours,
                    $entry->status,
                    $entry->is_billable ? 'Ya' : 'Tidak',
                    (float) $entry->bill_rate,
                    (float) $entry->cost_rate,
                    (float) $entry->bill_amount,
                    (float) $entry->cost_amount,
                ]);
            }

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * Decide every pending entry the request names, returning how many changed.
     */
    private function decidePending(Request $request, string $action): int
    {
        $tenantId = (int) $request->user()->tenant_id;
        $actorId = (int) $request->user()->id;

        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $reason = $data['reason'] ?? null;

        $entries = Timesheet::forTenant($tenantId)
            ->whereIn('id', $data['ids'])
            ->where('status', Timesheet::STATUS_PENDING)
            ->get();

        foreach ($entries as $entry) {
            if (ApprovalEngine::decide($entry, $actorId, $action, $reason)) {
                // The engine may have advanced a step rather than finished the
                // request; only a rejection is final either way.
                if ($action === 'reject') {
                    $entry->update(['approved_by' => $actorId, 'rejection_reason' => $reason, 'current_approver_id' => null]);
                }

                continue;
            }

            $action === 'approve'
                ? TimesheetApproval::finalize($entry, $actorId)
                : TimesheetApproval::reject($entry, $actorId, $reason);
        }

        return $entries->count();
    }

    /**
     * The entry query for the active filters (no ordering, no eager loads).
     *
     * @param  array<string, mixed>  $filters
     * @return Builder<Timesheet>
     */
    private function entryQuery(int $tenantId, array $filters)
    {
        return Timesheet::forTenant($tenantId)
            ->when($filters['project_id'] ?? null, fn ($query, $value) => $query->where('project_id', $value))
            ->when($filters['employee_id'] ?? null, fn ($query, $value) => $query->where('employee_id', $value))
            ->when($filters['status'] ?? null, fn ($query, $value) => $query->where('status', $value))
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('date', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('date', '<=', $value));
    }

    /**
     * The active filters, defaulting the window to the current month so the
     * report opens on a period rather than on everything ever logged.
     *
     * @return array{project_id: string|null, employee_id: string|null, status: string|null, from: string, to: string}
     */
    private function resolveFilters(Request $request): array
    {
        $status = $request->query('status');

        return [
            'project_id' => $request->query('project_id') ?: null,
            'employee_id' => $request->query('employee_id') ?: null,
            'status' => in_array($status, [Timesheet::STATUS_PENDING, Timesheet::STATUS_APPROVED, Timesheet::STATUS_REJECTED], true)
                ? $status
                : null,
            'from' => $request->query('from') ?: Carbon::today()->startOfMonth()->toDateString(),
            'to' => $request->query('to') ?: Carbon::today()->endOfMonth()->toDateString(),
        ];
    }

    /**
     * Profitability per project and per employee over the approved entries in
     * the active window.
     *
     * @param  Collection<int, Timesheet>  $entries
     * @param  Collection<int, Project>  $projects
     * @return array{projects: array<int, array<string, mixed>>, employees: array<int, array<string, mixed>>, totals: array<string, float>}
     */
    private function buildReport(Collection $entries, Collection $projects): array
    {
        $byProject = $entries->groupBy('project_id')
            ->map(function (Collection $rows, int|string $projectId) use ($projects): array {
                $project = $projects->firstWhere('id', (int) $projectId);
                $hours = round((float) $rows->sum('hours'), 2);
                $bill = round((float) $rows->sum('bill_amount'), 2);
                $cost = round((float) $rows->sum('cost_amount'), 2);
                $budget = (float) ($project?->budget_amount ?? 0);
                $budgetHours = (float) ($project?->budget_hours ?? 0);

                return [
                    'project_id' => (int) $projectId,
                    'project' => $project?->name ?? $rows->first()?->project?->name ?? '—',
                    'code' => $project?->code,
                    'client_name' => $project?->client_name,
                    'hours' => $hours,
                    'billable_hours' => round((float) $rows->where('is_billable', true)->sum('hours'), 2),
                    'bill_amount' => $bill,
                    'cost_amount' => $cost,
                    'margin' => round($bill - $cost, 2),
                    'margin_pct' => $bill > 0.0 ? round(($bill - $cost) / $bill * 100, 1) : null,
                    'budget_amount' => $budget ?: null,
                    'budget_used_pct' => $budget > 0.0 ? round($cost / $budget * 100, 1) : null,
                    'budget_hours' => $budgetHours ?: null,
                    'budget_hours_used_pct' => $budgetHours > 0.0 ? round($hours / $budgetHours * 100, 1) : null,
                    'entries' => $rows->count(),
                ];
            })
            ->sortByDesc('hours')
            ->values()
            ->all();

        $byEmployee = $entries->groupBy('employee_id')
            ->map(fn (Collection $rows, int|string $employeeId): array => [
                'employee_id' => (int) $employeeId,
                'employee' => $rows->first()?->employee?->full_name ?? '—',
                'hours' => round((float) $rows->sum('hours'), 2),
                'bill_amount' => round((float) $rows->sum('bill_amount'), 2),
                'cost_amount' => round((float) $rows->sum('cost_amount'), 2),
                'entries' => $rows->count(),
            ])
            ->sortByDesc('hours')
            ->values()
            ->all();

        $bill = round((float) $entries->sum('bill_amount'), 2);
        $cost = round((float) $entries->sum('cost_amount'), 2);

        return [
            'projects' => $byProject,
            'employees' => $byEmployee,
            'totals' => [
                'hours' => round((float) $entries->sum('hours'), 2),
                'bill_amount' => $bill,
                'cost_amount' => $cost,
                'margin' => round($bill - $cost, 2),
                'margin_pct' => $bill > 0.0 ? round(($bill - $cost) / $bill * 100, 1) : 0.0,
            ],
        ];
    }

    /**
     * Validate the project form, including the member assignment rows.
     *
     * @return array<string, mixed>
     */
    private function validateProject(Request $request, int $tenantId, ?Project $project = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('projects', 'code')
                    ->where(fn ($query) => $query->where('tenant_id', $tenantId))
                    ->ignore($project?->id),
            ],
            'client_name' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', 'in:active,archived'],
            'manager_id' => ['nullable', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'budget_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'budget_hours' => ['nullable', 'numeric', 'min:0', 'max:999999'],
            'is_billable' => ['required', 'boolean'],
            'default_bill_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'default_cost_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'members' => ['nullable', 'array'],
            'members.*.employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'members.*.bill_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'members.*.cost_rate' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
        ]);
    }

    /**
     * Validate an entry form.
     *
     * @return array<string, mixed>
     */
    private function validateEntry(Request $request, int $tenantId): array
    {
        return $request->validate([
            'employee_id' => ['required', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'project_id' => ['required', Rule::exists('projects', 'id')->where('tenant_id', $tenantId)],
            'date' => ['required', 'date'],
            'hours' => ['required', 'numeric', 'min:0.5', 'max:24'],
            'task' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_billable' => ['nullable', 'boolean'],
        ]);
    }

    /**
     * Replace a project's member assignments with the submitted rows.
     *
     * @param  array<int, array<string, mixed>>  $members
     */
    private function syncMembers(Project $project, array $members): void
    {
        $keep = [];

        foreach ($members as $member) {
            $row = ProjectMember::updateOrCreate(
                ['project_id' => $project->id, 'employee_id' => $member['employee_id']],
                [
                    'tenant_id' => $project->tenant_id,
                    'bill_rate' => $member['bill_rate'] ?? null,
                    'cost_rate' => $member['cost_rate'] ?? null,
                ],
            );

            $keep[] = $row->id;
        }

        ProjectMember::where('project_id', $project->id)
            ->whereNotIn('id', $keep ?: [0])
            ->delete();
    }

    /**
     * Refuse an entry that would push a person's day past 24 hours — the one
     * arithmetic mistake a timesheet must not accept.
     */
    private function ensureDailyHoursFit(int $tenantId, int $employeeId, string $date, float $hours, ?int $ignoreId = null): void
    {
        $logged = (float) Timesheet::forTenant($tenantId)
            ->where('employee_id', $employeeId)
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
            'status' => $timesheet->status,
            'status_label' => $this->statusLabel($timesheet->status),
            'is_billable' => (bool) $timesheet->is_billable,
            'bill_rate' => $timesheet->bill_rate !== null ? (float) $timesheet->bill_rate : null,
            'cost_rate' => $timesheet->cost_rate !== null ? (float) $timesheet->cost_rate : null,
            'bill_amount' => (float) $timesheet->bill_amount,
            'cost_amount' => (float) $timesheet->cost_amount,
            'source' => $timesheet->source,
            'approved_by' => $timesheet->approver?->name,
            'approved_at' => $timesheet->approved_at?->format('d M Y H:i'),
            'rejection_reason' => $timesheet->rejection_reason,
        ];
    }

    /**
     * Build the row shape consumed by the projects table and the project form.
     *
     * @return array<string, mixed>
     */
    private function shapeProject(Project $project): array
    {
        return [
            'id' => $project->id,
            'name' => $project->name,
            'code' => $project->code,
            'client_name' => $project->client_name,
            'description' => $project->description,
            'status' => $project->status,
            'manager_id' => $project->manager_id,
            'manager' => $project->manager?->full_name,
            'start_date' => $project->start_date?->toDateString(),
            'end_date' => $project->end_date?->toDateString(),
            'budget_amount' => $project->budget_amount !== null ? (float) $project->budget_amount : null,
            'budget_hours' => $project->budget_hours !== null ? (float) $project->budget_hours : null,
            'is_billable' => (bool) $project->is_billable,
            'default_bill_rate' => $project->default_bill_rate !== null ? (float) $project->default_bill_rate : null,
            'default_cost_rate' => $project->default_cost_rate !== null ? (float) $project->default_cost_rate : null,
            'timesheets_count' => $project->timesheets_count,
            'members' => $project->members->map(fn (ProjectMember $member): array => [
                'employee_id' => $member->employee_id,
                'employee' => $member->employee?->full_name,
                'bill_rate' => $member->bill_rate !== null ? (float) $member->bill_rate : null,
                'cost_rate' => $member->cost_rate !== null ? (float) $member->cost_rate : null,
            ])->all(),
        ];
    }

    /** Indonesian label for an entry status. */
    private function statusLabel(string $status): string
    {
        return match ($status) {
            Timesheet::STATUS_APPROVED => 'Disetujui',
            Timesheet::STATUS_REJECTED => 'Ditolak',
            default => 'Menunggu',
        };
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
     * Abort with 404 when the project does not belong to the user's tenant.
     */
    private function ensureProjectOwnership(Request $request, Project $project): void
    {
        abort_if((int) $project->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds the permission.
     */
    private function ensureCan(Request $request, string $action): void
    {
        FeatureGate::ensure($request->user(), self::MODULE, 'Fitur timesheet tidak aktif untuk perusahaan Anda.');

        abort_unless($this->can($request, $action), 403);
    }

    /**
     * Whether the user holds the module action (super admins always do).
     */
    private function can(Request $request, string $action): bool
    {
        /** @var User $user */
        $user = $request->user();

        return $user->isSuperAdmin() || $user->hasPermissionTo(self::MODULE.'.'.$action);
    }
}

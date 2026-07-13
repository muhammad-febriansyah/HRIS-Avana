<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\CrmActivity;
use App\Models\CrmContact;
use App\Models\CrmDeal;
use App\Models\CrmDealMember;
use App\Models\CrmTask;
use App\Models\Employee;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CrmController extends Controller
{
    /**
     * Roles that may always manage the CRM pipeline within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Allowed deal pipeline stages, in display order.
     *
     * @var array<int, string>
     */
    private const STAGES = ['lead', 'qualified', 'proposal', 'won', 'lost'];

    /**
     * Win-probability weight per open stage, used to weight the forecast.
     *
     * @var array<string, float>
     */
    private const STAGE_PROBABILITY = [
        'lead' => 0.10,
        'qualified' => 0.35,
        'proposal' => 0.65,
        'won' => 1.0,
        'lost' => 0.0,
    ];

    /**
     * Allowed follow-up activity types.
     *
     * @var array<int, string>
     */
    private const ACTIVITY_TYPES = ['call', 'email', 'meeting', 'whatsapp', 'visit', 'note'];

    /**
     * Display the sales pipeline grouped by stage with its contacts.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $deals = CrmDeal::forTenant($tenantId)
            ->with(['contact:id,name,company', 'owner:id,full_name'])
            ->withCount([
                'activities',
                'tasks as open_tasks_count' => fn ($query) => $query->where('status', 'pending'),
            ])
            ->latest('id')
            ->get()
            ->map(fn (CrmDeal $deal): array => $this->transformDeal($deal));

        $pipeline = collect(self::STAGES)
            ->mapWithKeys(fn (string $stage): array => [
                $stage => $deals->where('stage', $stage)->values()->all(),
            ])
            ->all();

        $contacts = CrmContact::forTenant($tenantId)
            ->withCount('deals')
            ->orderBy('name')
            ->get()
            ->map(fn (CrmContact $contact): array => $this->transformContact($contact));

        return Inertia::render('avana/crm/index', [
            'pipeline' => $pipeline,
            'contacts' => $contacts,
            'contactOptions' => $contacts->map(fn (array $contact): array => [
                'id' => $contact['id'],
                'name' => $contact['name'],
            ])->all(),
            'owners' => $this->ownerOptions($tenantId),
            'stages' => $this->stageOptions(),
            'kpis' => [
                'total_deals' => $deals->count(),
                'pipeline_value' => (float) $deals->whereNotIn('stage', ['won', 'lost'])->sum('value'),
                'won_value' => (float) $deals->where('stage', 'won')->sum('value'),
            ],
        ]);
    }

    /**
     * Persist a new CRM contact under the acting user's tenant.
     */
    public function storeContact(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        CrmContact::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.crm')
            ->with('success', 'Kontak berhasil ditambahkan');
    }

    /**
     * Persist a new deal under the acting user's tenant.
     */
    public function storeDeal(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateDeal($request, $tenantId);

        CrmDeal::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.crm')
            ->with('success', 'Deal berhasil ditambahkan');
    }

    /**
     * Update an existing deal.
     */
    public function updateDeal(Request $request, CrmDeal $deal): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $deal);

        $data = $this->validateDeal($request, $request->user()->tenant_id);

        $deal->update($data);

        return redirect()->route('avana.crm')
            ->with('success', 'Deal berhasil diperbarui');
    }

    /**
     * Move a deal to a different pipeline stage.
     */
    public function moveStage(Request $request, CrmDeal $deal): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $deal);

        $data = $request->validate([
            'stage' => ['required', Rule::in(self::STAGES)],
        ]);

        $deal->update(['stage' => $data['stage']]);

        return back()->with('success', 'Tahap deal diperbarui');
    }

    /**
     * Delete a deal.
     */
    public function destroyDeal(Request $request, CrmDeal $deal): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $deal);

        $deal->delete();

        return back()->with('success', 'Deal dihapus');
    }

    /**
     * Display a single deal with its follow-up activities, tasks, and team.
     */
    public function show(Request $request, CrmDeal $deal): Response
    {
        $this->ensureCanManage($request);
        $this->guardTenant($request, $deal);

        $tenantId = $request->user()->tenant_id;

        $deal->load([
            'contact:id,name,company,email,phone',
            'owner:id,full_name',
            'project:id,name',
            'activities' => fn ($query) => $query->with('creator:id,name')->latest('activity_date')->latest('id'),
            'tasks' => fn ($query) => $query->with('assignee:id,full_name')->orderBy('status')->orderByRaw('due_date is null')->orderBy('due_date'),
            'members' => fn ($query) => $query->with('employee:id,full_name')->latest('id'),
        ]);

        return Inertia::render('avana/crm/deal', [
            'deal' => [
                ...$this->transformDeal($deal),
                'contact_email' => $deal->contact?->email,
                'contact_phone' => $deal->contact?->phone,
                'project_id' => $deal->project_id,
                'project' => $deal->project?->name,
            ],
            'activities' => $deal->activities->map(fn (CrmActivity $activity): array => [
                'id' => $activity->id,
                'type' => $activity->type,
                'note' => $activity->note,
                'activity_date' => $activity->activity_date?->format('d M Y'),
                'outcome' => $activity->outcome,
                'author' => $activity->creator?->name,
            ])->all(),
            'tasks' => $deal->tasks->map(fn (CrmTask $task): array => [
                'id' => $task->id,
                'title' => $task->title,
                'due_date' => $task->due_date?->format('d M Y'),
                'due_date_raw' => $task->due_date?->toDateString(),
                'is_overdue' => $task->status === 'pending' && $task->due_date !== null && $task->due_date->isPast(),
                'status' => $task->status,
                'assignee_id' => $task->assignee_id,
                'assignee' => $task->assignee?->full_name,
            ])->all(),
            'members' => $deal->members->map(fn (CrmDealMember $member): array => [
                'id' => $member->id,
                'employee_id' => $member->employee_id,
                'name' => $member->employee?->full_name,
                'role' => $member->role,
            ])->all(),
            'employeeOptions' => $this->ownerOptions($tenantId),
            'projectOptions' => Project::forTenant($tenantId)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn (Project $project): array => ['id' => $project->id, 'name' => $project->name])
                ->all(),
            'activityTypes' => $this->activityTypeOptions(),
        ]);
    }

    /**
     * Record a follow-up activity against a deal.
     */
    public function storeActivity(Request $request, CrmDeal $deal): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->guardTenant($request, $deal);

        $data = $request->validate([
            'type' => ['required', Rule::in(self::ACTIVITY_TYPES)],
            'note' => ['required', 'string'],
            'activity_date' => ['required', 'date'],
            'outcome' => ['nullable', 'string', 'max:255'],
        ]);

        $deal->activities()->create([
            ...$data,
            'tenant_id' => $deal->tenant_id,
            'contact_id' => $deal->contact_id,
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Aktivitas dicatat');
    }

    /**
     * Delete a follow-up activity.
     */
    public function destroyActivity(Request $request, CrmActivity $activity): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->guardTenant($request, $activity);

        $activity->delete();

        return back()->with('success', 'Aktivitas dihapus');
    }

    /**
     * Create a sales task against a deal.
     */
    public function storeTask(Request $request, CrmDeal $deal): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->guardTenant($request, $deal);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'due_date' => ['nullable', 'date'],
            'assignee_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $deal->tenant_id)],
        ]);

        $deal->tasks()->create([
            ...$data,
            'tenant_id' => $deal->tenant_id,
            'status' => 'pending',
            'created_by' => $request->user()->id,
        ]);

        return back()->with('success', 'Task ditambahkan');
    }

    /**
     * Toggle a task between pending and done.
     */
    public function toggleTask(Request $request, CrmTask $task): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->guardTenant($request, $task);

        $done = $task->status === 'done';

        $task->update([
            'status' => $done ? 'pending' : 'done',
            'completed_at' => $done ? null : Carbon::now(),
        ]);

        return back()->with('success', $done ? 'Task dibuka kembali' : 'Task selesai');
    }

    /**
     * Delete a sales task.
     */
    public function destroyTask(Request $request, CrmTask $task): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->guardTenant($request, $task);

        $task->delete();

        return back()->with('success', 'Task dihapus');
    }

    /**
     * Add a collaborating team member to a deal.
     */
    public function storeMember(Request $request, CrmDeal $deal): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->guardTenant($request, $deal);

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $deal->tenant_id)],
            'role' => ['nullable', 'string', 'max:255'],
        ]);

        $deal->members()->firstOrCreate(
            ['employee_id' => $data['employee_id']],
            ['tenant_id' => $deal->tenant_id, 'role' => $data['role'] ?? null],
        );

        return back()->with('success', 'Anggota tim ditambahkan');
    }

    /**
     * Remove a collaborating team member from a deal.
     */
    public function destroyMember(Request $request, CrmDealMember $member): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->guardTenant($request, $member);

        $member->delete();

        return back()->with('success', 'Anggota tim dihapus');
    }

    /**
     * Link (or unlink) a deal to a delivery project.
     */
    public function linkProject(Request $request, CrmDeal $deal): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->guardTenant($request, $deal);

        $data = $request->validate([
            'project_id' => ['nullable', 'integer', Rule::exists('projects', 'id')->where('tenant_id', $deal->tenant_id)],
        ]);

        $deal->update(['project_id' => $data['project_id'] ?? null]);

        return back()->with('success', 'Proyek terhubung diperbarui');
    }

    /**
     * Render the CRM insights dashboard with funnel, win-rate, and forecast.
     */
    public function insights(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $deals = CrmDeal::forTenant($tenantId)->with('owner:id,full_name')->get();

        $funnel = collect(self::STAGES)
            ->map(fn (string $stage): array => [
                'stage' => $stage,
                'label' => $this->stageLabel($stage),
                'count' => $deals->where('stage', $stage)->count(),
                'value' => (float) $deals->where('stage', $stage)->sum('value'),
            ])
            ->all();

        $won = $deals->where('stage', 'won');
        $lost = $deals->where('stage', 'lost');
        $open = $deals->whereNotIn('stage', ['won', 'lost']);
        $decided = $won->count() + $lost->count();

        $byOwner = $deals
            ->groupBy(fn (CrmDeal $deal): string => $deal->owner?->full_name ?? 'Tanpa PIC')
            ->map(fn ($group, string $name): array => [
                'name' => $name,
                'deals' => $group->count(),
                'won' => $group->where('stage', 'won')->count(),
                'won_value' => (float) $group->where('stage', 'won')->sum('value'),
                'open_value' => (float) $group->whereNotIn('stage', ['won', 'lost'])->sum('value'),
            ])
            ->sortByDesc('won_value')
            ->values()
            ->all();

        $monthStart = Carbon::now()->startOfMonth();

        return Inertia::render('avana/crm/insights', [
            'funnel' => $funnel,
            'kpis' => [
                'total_deals' => $deals->count(),
                'open_value' => (float) $open->sum('value'),
                'won_value' => (float) $won->sum('value'),
                'win_rate' => $decided > 0 ? round($won->count() / $decided * 100, 1) : 0.0,
                'forecast' => round($open->sum(fn (CrmDeal $deal): float => (float) $deal->value * (self::STAGE_PROBABILITY[$deal->stage] ?? 0.0)), 2),
                'activities_this_month' => CrmActivity::forTenant($tenantId)->where('activity_date', '>=', $monthStart->toDateString())->count(),
                'open_tasks' => CrmTask::forTenant($tenantId)->where('status', 'pending')->count(),
                'overdue_tasks' => CrmTask::forTenant($tenantId)->where('status', 'pending')->whereNotNull('due_date')->whereDate('due_date', '<', Carbon::now()->toDateString())->count(),
            ],
            'byOwner' => $byOwner,
        ]);
    }

    /**
     * Validate the create/update payload for a deal.
     *
     * @return array<string, mixed>
     */
    private function validateDeal(Request $request, ?int $tenantId): array
    {
        return $request->validate([
            'contact_id' => ['nullable', 'integer', Rule::exists('crm_contacts', 'id')->where('tenant_id', $tenantId)],
            'title' => ['required', 'string', 'max:255'],
            'value' => ['required', 'numeric', 'min:0'],
            'stage' => ['required', Rule::in(self::STAGES)],
            'owner_id' => ['nullable', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'expected_close' => ['nullable', 'date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    /**
     * Build the card shape consumed by the pipeline board.
     *
     * @return array<string, mixed>
     */
    private function transformDeal(CrmDeal $deal): array
    {
        return [
            'id' => $deal->id,
            'title' => $deal->title,
            'value' => (float) $deal->value,
            'stage' => $deal->stage,
            'contact_id' => $deal->contact_id,
            'contact' => $deal->contact?->name,
            'company' => $deal->contact?->company,
            'owner_id' => $deal->owner_id,
            'owner' => $deal->owner?->full_name,
            'expected_close' => $deal->expected_close?->toDateString(),
            'notes' => $deal->notes,
            'activities_count' => (int) ($deal->activities_count ?? 0),
            'open_tasks_count' => (int) ($deal->open_tasks_count ?? 0),
        ];
    }

    /**
     * Build the row shape consumed by the contacts list.
     *
     * @return array<string, mixed>
     */
    private function transformContact(CrmContact $contact): array
    {
        return [
            'id' => $contact->id,
            'name' => $contact->name,
            'company' => $contact->company,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'notes' => $contact->notes,
            'deals_count' => $contact->deals_count,
        ];
    }

    /**
     * Build the tenant's selectable deal-owner (employee) options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function ownerOptions(int $tenantId): array
    {
        return Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of deal pipeline stages.
     *
     * @return array<int, array<string, string>>
     */
    private function stageOptions(): array
    {
        $labels = [
            'lead' => 'Lead',
            'qualified' => 'Terkualifikasi',
            'proposal' => 'Proposal',
            'won' => 'Menang',
            'lost' => 'Kalah',
        ];

        return collect(self::STAGES)
            ->map(fn (string $stage): array => [
                'value' => $stage,
                'label' => $labels[$stage],
            ])
            ->all();
    }

    /**
     * Indonesian label for a single pipeline stage.
     */
    private function stageLabel(string $stage): string
    {
        return [
            'lead' => 'Lead',
            'qualified' => 'Terkualifikasi',
            'proposal' => 'Proposal',
            'won' => 'Menang',
            'lost' => 'Kalah',
        ][$stage] ?? $stage;
    }

    /**
     * Build the `{ value, label }` list of follow-up activity types.
     *
     * @return array<int, array<string, string>>
     */
    private function activityTypeOptions(): array
    {
        $labels = [
            'call' => 'Telepon',
            'email' => 'Email',
            'meeting' => 'Meeting',
            'whatsapp' => 'WhatsApp',
            'visit' => 'Kunjungan',
            'note' => 'Catatan',
        ];

        return collect(self::ACTIVITY_TYPES)
            ->map(fn (string $type): array => [
                'value' => $type,
                'label' => $labels[$type],
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, CrmDeal $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 404 when any tenant-scoped record is not the user's tenant.
     */
    private function guardTenant(Request $request, Model $record): void
    {
        abort_if((int) $record->tenant_id !== (int) $request->user()->tenant_id, 404);
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

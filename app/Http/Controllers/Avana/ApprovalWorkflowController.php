<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\ApprovalStep;
use App\Models\ApprovalWorkflow;
use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveType;
use App\Models\Position;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalWorkflowController extends Controller
{
    /**
     * Permission modules that gate this admin screen — the same "Pengaturan"
     * cluster the nav leaf lives under. Holding the action on ANY of them
     * grants access, matching {@see AvanaNav::MANAGE_MODULES}.
     *
     * @var array<int, string>
     */
    private const MANAGE_MODULES = ['settings', 'role', 'permission'];

    /**
     * The request modules a workflow can be attached to. `key` is stored in
     * `approval_workflows.request_type`; the rest drives the wizard picker.
     *
     * @var array<int, array{key: string, label: string, description: string, icon: string, color: string}>
     */
    private const MODULES = [
        ['key' => 'leave', 'label' => 'Cuti (Leave)', 'description' => 'Pengajuan cuti karyawan', 'icon' => 'calendar', 'color' => '#2F54C9'],
        ['key' => 'overtime', 'label' => 'Lembur (Overtime)', 'description' => 'Pengajuan lembur', 'icon' => 'clock', 'color' => '#6E9BE6'],
        ['key' => 'reimbursement', 'label' => 'Klaim (Reimbursement)', 'description' => 'Pengajuan klaim biaya', 'icon' => 'receipt', 'color' => '#D97706'],
        ['key' => 'permission', 'label' => 'Izin', 'description' => 'Pengajuan izin karyawan', 'icon' => 'clock', 'color' => '#0EA5E9'],
        ['key' => 'attendance_correction', 'label' => 'Koreksi Absen', 'description' => 'Pengajuan koreksi absensi', 'icon' => 'calendar', 'color' => '#7C3AED'],
        ['key' => 'duty_travel', 'label' => 'Perjalanan Dinas', 'description' => 'Pengajuan perjalanan dinas', 'icon' => 'plane', 'color' => '#16A34A'],
        ['key' => 'document_request', 'label' => 'Permintaan Dokumen', 'description' => 'Permintaan dokumen HR', 'icon' => 'folder', 'color' => '#0E1A3A'],
        ['key' => 'data_change', 'label' => 'Perubahan Data Pribadi', 'description' => 'Pengajuan perubahan data', 'icon' => 'user-round-cog', 'color' => '#DC2626'],
    ];

    /**
     * Legacy request_type aliases → module label, so workflows seeded before
     * the canonical keys existed still render a friendly module name.
     *
     * @var array<string, string>
     */
    private const LEGACY_MODULE_LABELS = [
        'lembur' => 'Lembur (Overtime)',
        'reimburse' => 'Klaim (Reimbursement)',
    ];

    /**
     * Approver targeting types available per step.
     *
     * @var array<int, array{key: string, label: string, ref: ?string}>
     */
    private const APPROVER_TYPES = [
        ['key' => 'direct_manager', 'label' => 'Direct Manager (Atasan Langsung)', 'ref' => null],
        ['key' => 'role', 'label' => 'Role Tertentu', 'ref' => 'role'],
        ['key' => 'department', 'label' => 'Role / Departemen', 'ref' => 'department'],
        ['key' => 'position', 'label' => 'Jabatan Tertentu', 'ref' => 'position'],
        ['key' => 'specific_user', 'label' => 'Karyawan Tertentu', 'ref' => 'employee'],
    ];

    /**
     * List the tenant's approval workflows plus everything the wizard needs.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = $request->user()->tenant_id;

        $workflows = ApprovalWorkflow::forTenant($tenantId)
            ->with([
                'department:id,name',
                'steps.approverEmployee:id,full_name',
                'steps.approverRole:id,name',
                'steps.approverDepartment:id,name',
                'steps.approverPosition:id,name',
            ])
            ->withCount('steps')
            ->latest('updated_at')
            ->get()
            ->map(fn (ApprovalWorkflow $workflow): array => $this->shapeWorkflow($workflow));

        return Inertia::render('avana/approval-workflow/index', [
            'workflows' => $workflows,
            'modules' => self::MODULES,
            'approverTypes' => self::APPROVER_TYPES,
            'options' => $this->options($tenantId),
            'kpis' => [
                'total' => $workflows->count(),
                'active' => $workflows->where('is_active', true)->count(),
            ],
        ]);
    }

    /**
     * Persist a new workflow with its steps and optional conditions.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');

        $tenantId = $request->user()->tenant_id;
        $data = $this->validatePayload($request, $tenantId);

        DB::transaction(function () use ($data, $tenantId): void {
            /** @var ApprovalWorkflow $workflow */
            $workflow = ApprovalWorkflow::create([
                'tenant_id' => $tenantId,
                'name' => $data['name'],
                'request_type' => $data['request_type'],
                'department_id' => $data['department_id'],
                'approval_mode' => $data['approval_mode'],
                'is_active' => $data['is_active'],
                'conditions' => $data['conditions'],
            ]);

            $this->syncSteps($workflow, $tenantId, $data['steps']);
        });

        return redirect()->route('avana.approval-workflow')
            ->with('success', 'Alur persetujuan dibuat');
    }

    /**
     * Update an existing workflow, replacing its steps.
     */
    public function update(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $workflow);

        $tenantId = $request->user()->tenant_id;
        $data = $this->validatePayload($request, $tenantId, $workflow);

        DB::transaction(function () use ($workflow, $data, $tenantId): void {
            $workflow->update([
                'name' => $data['name'],
                'request_type' => $data['request_type'],
                'department_id' => $data['department_id'],
                'approval_mode' => $data['approval_mode'],
                'is_active' => $data['is_active'],
                'conditions' => $data['conditions'],
            ]);

            $workflow->steps()->delete();
            $this->syncSteps($workflow, $tenantId, $data['steps']);
        });

        return redirect()->route('avana.approval-workflow')
            ->with('success', 'Alur persetujuan diperbarui');
    }

    /**
     * Toggle the active flag of a workflow.
     */
    public function toggle(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureTenantOwnership($request, $workflow);

        $workflow->update(['is_active' => ! $workflow->is_active]);

        return back()->with('success', $workflow->is_active ? 'Alur diaktifkan' : 'Alur dinonaktifkan');
    }

    /**
     * Soft-delete a workflow (and its steps).
     */
    public function destroy(Request $request, ApprovalWorkflow $workflow): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureTenantOwnership($request, $workflow);

        DB::transaction(function () use ($workflow): void {
            $workflow->steps()->delete();
            $workflow->delete();
        });

        return back()->with('success', 'Alur persetujuan dihapus');
    }

    /**
     * Validate the wizard payload and normalise it into a persistable array.
     *
     * @return array{name: string, request_type: string, approval_mode: string, is_active: bool, steps: array<int, array<string, mixed>>, conditions: array<int, array<string, mixed>>|null}
     */
    private function validatePayload(Request $request, int $tenantId, ?ApprovalWorkflow $ignore = null): array
    {
        $moduleKeys = array_column(self::MODULES, 'key');
        $approverKeys = array_column(self::APPROVER_TYPES, 'key');

        // One workflow per module per DIVISION. Two active flows for the same
        // scope would leave the engine picking one of them silently, so the
        // second one is refused rather than quietly ignored. A division whose
        // approvals run differently gets its own scoped flow; department NULL
        // is the tenant-wide default the engine falls back to.
        $departmentId = $request->input('department_id') ?: null;

        $onePerScope = Rule::unique('approval_workflows', 'request_type')
            ->where(fn ($query) => $query
                ->where('tenant_id', $tenantId)
                ->whereNull('deleted_at')
                ->when(
                    $departmentId === null,
                    fn ($sub) => $sub->whereNull('department_id'),
                    fn ($sub) => $sub->where('department_id', $departmentId),
                ));

        if ($ignore !== null) {
            $onePerScope = $onePerScope->ignore($ignore->getKey());
        }

        $validated = $request->validate([
            'name' => ['nullable', 'string', 'max:120'],
            'request_type' => ['required', Rule::in($moduleKeys), $onePerScope],
            'department_id' => ['nullable', 'integer', "exists:departments,id,tenant_id,{$tenantId}"],
            'approval_mode' => ['required', Rule::in(['sequential', 'parallel'])],
            'is_active' => ['required', 'boolean'],

            'steps' => ['required', 'array', 'min:1', 'max:10'],
            'steps.*.approver_type' => ['required', Rule::in($approverKeys)],
            'steps.*.approver_role_id' => ['nullable', 'integer', "exists:roles,id,tenant_id,{$tenantId}"],
            'steps.*.approver_department_id' => ['nullable', 'integer', "exists:departments,id,tenant_id,{$tenantId}"],
            'steps.*.approver_position_id' => ['nullable', 'integer', "exists:positions,id,tenant_id,{$tenantId}"],
            'steps.*.approver_user_id' => ['nullable', 'integer', "exists:employees,id,tenant_id,{$tenantId}"],

            'conditions' => ['nullable', 'array', 'max:10'],
            'conditions.*.field' => ['required', Rule::in(['days', 'amount', 'leave_type'])],
            'conditions.*.operator' => ['required', Rule::in(['>', '>=', '=', '<', '<='])],
            'conditions.*.value' => ['required', 'string', 'max:120'],
            'conditions.*.extra_approver_type' => ['required', Rule::in($approverKeys)],
            'conditions.*.extra_approver_ref' => ['nullable', 'integer'],
        ], [
            'request_type.unique' => $departmentId === null
                ? 'Modul ini sudah punya alur default (semua divisi). Ubah alur yang ada, atau buat alur khusus untuk satu divisi.'
                : 'Divisi ini sudah punya alur untuk modul tersebut. Ubah alur yang ada, jangan buat yang kedua.',
        ]);

        $moduleLabel = collect(self::MODULES)->firstWhere('key', $validated['request_type'])['label'];

        if (($validated['department_id'] ?? null) !== null && ! $request->filled('name')) {
            $departmentName = Department::forTenant($tenantId)->find($validated['department_id'])?->name;
            $moduleLabel .= $departmentName !== null ? ' — '.$departmentName : '';
        }

        return [
            'name' => $validated['name'] ?: $moduleLabel,
            'request_type' => $validated['request_type'],
            'department_id' => $validated['department_id'] ?? null,
            'approval_mode' => $validated['approval_mode'],
            'is_active' => (bool) $validated['is_active'],
            'steps' => $validated['steps'],
            'conditions' => $validated['conditions'] ?? null,
        ];
    }

    /**
     * Create the ordered steps for a workflow from validated input, keeping
     * only the approver reference relevant to each step's approver type.
     *
     * @param  array<int, array<string, mixed>>  $steps
     */
    private function syncSteps(ApprovalWorkflow $workflow, int $tenantId, array $steps): void
    {
        foreach (array_values($steps) as $index => $step) {
            $type = $step['approver_type'];

            ApprovalStep::create([
                'tenant_id' => $tenantId,
                'approval_workflow_id' => $workflow->id,
                'step_order' => $index + 1,
                'approver_type' => $type,
                'approver_role_id' => $type === 'role' ? ($step['approver_role_id'] ?? null) : null,
                'approver_department_id' => $type === 'department' ? ($step['approver_department_id'] ?? null) : null,
                'approver_position_id' => $type === 'position' ? ($step['approver_position_id'] ?? null) : null,
                'approver_user_id' => $type === 'specific_user' ? ($step['approver_user_id'] ?? null) : null,
                'condition' => $step['condition'] ?? null,
            ]);
        }
    }

    /**
     * Shape a workflow (with steps) for the list + preview.
     *
     * @return array<string, mixed>
     */
    private function shapeWorkflow(ApprovalWorkflow $workflow): array
    {
        return [
            'id' => $workflow->id,
            'name' => $workflow->name,
            'request_type' => $workflow->request_type,
            'department_id' => $workflow->department_id,
            'scope_label' => $workflow->department?->name ?? 'Semua Divisi',
            'module_label' => $this->moduleLabel($workflow->request_type),
            'module_icon' => $this->moduleIcon($workflow->request_type),
            'module_color' => $this->moduleColor($workflow->request_type),
            'approval_mode' => $workflow->approval_mode,
            'approval_mode_label' => $workflow->approval_mode === 'parallel' ? 'Paralel' : 'Berurutan',
            'step_count' => $workflow->steps_count,
            'is_active' => (bool) $workflow->is_active,
            'conditions' => $workflow->conditions ?? [],
            'updated_at' => $workflow->updated_at?->format('d M Y H:i'),
            'steps' => $workflow->steps->map(fn (ApprovalStep $step): array => [
                'step_order' => $step->step_order,
                'approver_type' => $step->approver_type,
                'approver_type_label' => $this->approverTypeLabel($step->approver_type),
                'approver_label' => $this->approverLabel($step),
                'approver_role_id' => $step->approver_role_id,
                'approver_department_id' => $step->approver_department_id,
                'approver_position_id' => $step->approver_position_id,
                'approver_user_id' => $step->approver_user_id,
                'condition' => $step->condition,
            ])->values(),
        ];
    }

    /**
     * The human module name for a stored request_type (canonical or legacy).
     */
    private function moduleLabel(?string $requestType): string
    {
        if ($requestType === null) {
            return 'Umum';
        }

        $module = collect(self::MODULES)->firstWhere('key', $requestType);

        return $module['label']
            ?? self::LEGACY_MODULE_LABELS[$requestType]
            ?? ucwords(str_replace('_', ' ', $requestType));
    }

    private function moduleIcon(?string $requestType): string
    {
        return collect(self::MODULES)->firstWhere('key', $requestType)['icon'] ?? 'git-branch';
    }

    private function moduleColor(?string $requestType): string
    {
        return collect(self::MODULES)->firstWhere('key', $requestType)['color'] ?? '#2F54C9';
    }

    private function approverTypeLabel(?string $type): string
    {
        return collect(self::APPROVER_TYPES)->firstWhere('key', $type)['label']
            ?? ucwords(str_replace('_', ' ', (string) $type));
    }

    /**
     * The resolved approver target name for a step.
     */
    private function approverLabel(ApprovalStep $step): string
    {
        return match ($step->approver_type) {
            'direct_manager' => 'Atasan Langsung',
            'role' => $step->approverRole?->name ?? 'Role',
            'department' => $step->approverDepartment?->name ?? 'Departemen',
            'position' => $step->approverPosition?->name ?? 'Jabatan',
            'specific_user' => $step->approverEmployee?->full_name ?? 'Karyawan',
            default => ucwords(str_replace('_', ' ', (string) $step->approver_type)),
        };
    }

    /**
     * The reference option lists the wizard selects need.
     *
     * @return array<string, mixed>
     */
    private function options(int $tenantId): array
    {
        return [
            'roles' => Role::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Role $role): array => ['value' => $role->id, 'label' => $role->name]),
            'departments' => Department::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Department $d): array => ['value' => $d->id, 'label' => $d->name]),
            'positions' => Position::forTenant($tenantId)->orderBy('name')->get(['id', 'name'])
                ->map(fn (Position $p): array => ['value' => $p->id, 'label' => $p->name]),
            'employees' => Employee::forTenant($tenantId)->orderBy('full_name')->get(['id', 'full_name'])
                ->map(fn (Employee $e): array => ['value' => $e->id, 'label' => $e->full_name]),
            // A sub-type may need its own approval path, so both levels are
            // offered — prefixed with the parent so the label stays unambiguous.
            'leaveTypes' => LeaveType::forTenant($tenantId)
                ->with('parent:id,name')
                ->orderBy('name')
                ->get(['id', 'name', 'parent_id'])
                ->map(fn (LeaveType $t): array => [
                    'value' => $t->id,
                    'label' => $t->parent === null ? $t->name : $t->parent->name.' › '.$t->name,
                ]),
        ];
    }

    /**
     * Abort with 404 when the workflow does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, ApprovalWorkflow $workflow): void
    {
        abort_if((int) $workflow->tenant_id !== (int) $request->user()->tenant_id, 404);
    }

    /**
     * Abort with 403 unless the user is privileged or holds the given action
     * on any of the manage modules (settings / role / permission).
     */
    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        foreach (self::MANAGE_MODULES as $module) {
            if ($user->hasPermissionTo($module.'.'.$action)) {
                return;
            }
        }

        abort(403);
    }
}

<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\KeyResult;
use App\Models\Objective;
use App\Models\PerformanceCycle;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OkrController extends Controller
{
    /**
     * Roles that may always manage OKRs within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Allowed objective level enum values.
     *
     * @var array<int, string>
     */
    private const LEVELS = ['company', 'team', 'individual'];

    /**
     * Allowed objective status enum values, in display order.
     *
     * @var array<int, string>
     */
    private const STATUSES = ['draft', 'active', 'done', 'cancelled'];

    /**
     * Balanced Scorecard perspectives an objective can be mapped to.
     *
     * @var array<int, string>
     */
    private const PERSPECTIVES = ['financial', 'customer', 'internal', 'learning'];

    /**
     * Display objectives together with their key results and KPIs.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $objectives = Objective::forTenant($tenantId)
            ->with(['keyResults' => fn ($query) => $query->orderBy('id'), 'cycle:id,name', 'employee:id,full_name'])
            ->latest('id')
            ->get()
            ->map(fn (Objective $objective): array => $this->transformObjective($objective));

        $avgProgress = $objectives->isNotEmpty()
            ? (int) round($objectives->avg('progress'))
            : 0;

        return Inertia::render('avana/okr/index', [
            'objectives' => $objectives,
            'cycles' => $this->cycleOptions($tenantId),
            'employees' => $this->employeeOptions($tenantId),
            'levels' => $this->levelOptions(),
            'statuses' => $this->statusOptions(),
            'perspectives' => $this->perspectiveOptions(),
            'kpis' => [
                'total_objectives' => $objectives->count(),
                'avg_progress' => $avgProgress,
                'on_track' => $objectives->where('status', 'active')->where('progress', '>=', 70)->count(),
            ],
        ]);
    }

    /**
     * Show the form for creating a new objective.
     */
    public function create(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/okr/create', [
            'cycles' => $this->cycleOptions($tenantId),
            'employees' => $this->employeeOptions($tenantId),
            'levels' => $this->levelOptions(),
            'statuses' => $this->statusOptions(),
            'perspectives' => $this->perspectiveOptions(),
        ]);
    }

    /**
     * Show the form for editing an existing objective.
     */
    public function edit(Request $request, Objective $objective): Response
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $objective);

        $tenantId = $request->user()->tenant_id;

        return Inertia::render('avana/okr/edit', [
            'objective' => [
                'id' => $objective->id,
                'title' => $objective->title,
                'description' => $objective->description,
                'level' => $objective->level,
                'perspective' => $objective->perspective,
                'status' => $objective->status,
                'cycle_id' => $objective->cycle_id,
                'employee_id' => $objective->employee_id,
            ],
            'cycles' => $this->cycleOptions($tenantId),
            'employees' => $this->employeeOptions($tenantId),
            'levels' => $this->levelOptions(),
            'statuses' => $this->statusOptions(),
            'perspectives' => $this->perspectiveOptions(),
        ]);
    }

    /**
     * Persist a new objective under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateObjective($request, $tenantId);

        Objective::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.okr')
            ->with('success', 'Objective berhasil ditambahkan');
    }

    /**
     * Update an existing objective.
     */
    public function update(Request $request, Objective $objective): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $objective);

        $data = $this->validateObjective($request, $request->user()->tenant_id);

        $objective->update($data);

        return redirect()->route('avana.okr')
            ->with('success', 'Objective berhasil diperbarui');
    }

    /**
     * Delete an objective (cascades its key results).
     */
    public function destroy(Request $request, Objective $objective): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $objective);

        $objective->delete();

        return back()->with('success', 'Objective dihapus');
    }

    /**
     * Add a key result to an objective and recompute the objective progress.
     */
    public function storeKeyResult(Request $request, Objective $objective): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $objective);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_value' => ['required', 'numeric', 'gt:0'],
            'current_value' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:255'],
        ]);

        KeyResult::create([
            'tenant_id' => $objective->tenant_id,
            'objective_id' => $objective->id,
            'title' => $data['title'],
            'target_value' => $data['target_value'],
            'current_value' => $data['current_value'],
            'unit' => $data['unit'] ?? null,
            'progress' => $this->keyResultProgress((float) $data['current_value'], (float) $data['target_value']),
        ]);

        $this->recomputeObjectiveProgress($objective);

        return back()->with('success', 'Key result ditambahkan');
    }

    /**
     * Update a key result and recompute its parent objective progress.
     */
    public function updateKeyResult(Request $request, KeyResult $keyResult): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $keyResult);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'target_value' => ['required', 'numeric', 'gt:0'],
            'current_value' => ['required', 'numeric', 'min:0'],
            'unit' => ['nullable', 'string', 'max:255'],
        ]);

        $keyResult->update([
            'title' => $data['title'],
            'target_value' => $data['target_value'],
            'current_value' => $data['current_value'],
            'unit' => $data['unit'] ?? null,
            'progress' => $this->keyResultProgress((float) $data['current_value'], (float) $data['target_value']),
        ]);

        $this->recomputeObjectiveProgress($keyResult->objective);

        return back()->with('success', 'Key result diperbarui');
    }

    /**
     * Delete a key result and recompute its parent objective progress.
     */
    public function destroyKeyResult(Request $request, KeyResult $keyResult): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $keyResult);

        $objective = $keyResult->objective;

        $keyResult->delete();

        $this->recomputeObjectiveProgress($objective);

        return back()->with('success', 'Key result dihapus');
    }

    /**
     * Validate the create/update payload for an objective.
     *
     * @return array<string, mixed>
     */
    private function validateObjective(Request $request, ?int $tenantId): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'level' => ['required', Rule::in(self::LEVELS)],
            'perspective' => ['nullable', Rule::in(self::PERSPECTIVES)],
            'status' => ['required', Rule::in(self::STATUSES)],
            'cycle_id' => [
                'nullable',
                'integer',
                Rule::exists('performance_cycles', 'id')->where('tenant_id', $tenantId),
            ],
            'employee_id' => [
                'nullable',
                'integer',
                Rule::exists('employees', 'id')->where('tenant_id', $tenantId),
            ],
        ]);
    }

    /**
     * Compute a clamped 0-100 progress value for a key result.
     */
    private function keyResultProgress(float $currentValue, float $targetValue): int
    {
        if ($targetValue <= 0) {
            return 0;
        }

        return (int) round(min(100, max(0, $currentValue / $targetValue * 100)));
    }

    /**
     * Recompute and persist an objective's progress as the average of its key results.
     */
    private function recomputeObjectiveProgress(Objective $objective): void
    {
        $average = $objective->keyResults()->avg('progress');

        $objective->update([
            'progress' => $average !== null ? (int) round($average) : 0,
        ]);
    }

    /**
     * Build the card shape consumed by the OKR board.
     *
     * @return array<string, mixed>
     */
    private function transformObjective(Objective $objective): array
    {
        return [
            'id' => $objective->id,
            'title' => $objective->title,
            'description' => $objective->description,
            'level' => $objective->level,
            'perspective' => $objective->perspective,
            'status' => $objective->status,
            'progress' => (int) $objective->progress,
            'cycle_id' => $objective->cycle_id,
            'cycle' => $objective->cycle?->name,
            'employee_id' => $objective->employee_id,
            'employee' => $objective->employee?->full_name,
            'key_results' => $objective->keyResults->map(fn (KeyResult $keyResult): array => [
                'id' => $keyResult->id,
                'title' => $keyResult->title,
                'target_value' => (float) $keyResult->target_value,
                'current_value' => (float) $keyResult->current_value,
                'unit' => $keyResult->unit,
                'progress' => (int) $keyResult->progress,
            ])->all(),
        ];
    }

    /**
     * Build the tenant's selectable cycle options.
     *
     * @return array<int, array<string, mixed>>
     */
    private function cycleOptions(int $tenantId): array
    {
        return PerformanceCycle::forTenant($tenantId)
            ->latest('id')
            ->get(['id', 'name'])
            ->map(fn (PerformanceCycle $cycle): array => [
                'id' => $cycle->id,
                'name' => $cycle->name,
            ])
            ->all();
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
     * Build the `{ value, label }` list of objective level options.
     *
     * @return array<int, array<string, string>>
     */
    private function levelOptions(): array
    {
        $labels = [
            'company' => 'Perusahaan',
            'team' => 'Tim',
            'individual' => 'Individu',
        ];

        return collect(self::LEVELS)
            ->map(fn (string $level): array => [
                'value' => $level,
                'label' => $labels[$level],
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of Balanced Scorecard perspectives.
     *
     * @return array<int, array<string, string>>
     */
    private function perspectiveOptions(): array
    {
        $labels = [
            'financial' => 'Keuangan',
            'customer' => 'Pelanggan',
            'internal' => 'Proses Internal',
            'learning' => 'Pembelajaran & Pertumbuhan',
        ];

        return collect(self::PERSPECTIVES)
            ->map(fn (string $perspective): array => [
                'value' => $perspective,
                'label' => $labels[$perspective],
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of objective status options.
     *
     * @return array<int, array<string, string>>
     */
    private function statusOptions(): array
    {
        $labels = [
            'draft' => 'Draf',
            'active' => 'Aktif',
            'done' => 'Selesai',
            'cancelled' => 'Dibatalkan',
        ];

        return collect(self::STATUSES)
            ->map(fn (string $status): array => [
                'value' => $status,
                'label' => $labels[$status],
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Objective|KeyResult $record): void
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

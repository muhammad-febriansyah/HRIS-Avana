<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\TalentAssessment;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TalentController extends Controller
{
    /**
     * Roles that may always manage talent assessments within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Allowed performance/potential rating levels.
     *
     * @var array<int, string>
     */
    private const LEVELS = ['low', 'medium', 'high'];

    /**
     * Display the 9-box talent grid with succession candidates.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $assessments = TalentAssessment::forTenant($tenantId)
            ->with('employee:id,full_name,employee_number')
            ->latest('id')
            ->get()
            ->map(fn (TalentAssessment $assessment): array => $this->transformAssessment($assessment));

        $successors = $assessments
            ->filter(fn (array $row): bool => ! empty($row['successor_for']))
            ->values()
            ->all();

        return Inertia::render('avana/talenta/index', [
            'assessments' => $assessments->all(),
            'successors' => $successors,
            'employees' => $this->employeeOptions($tenantId),
            'levels' => $this->levelOptions(),
            'kpis' => [
                'assessed' => $assessments->count(),
                'stars' => $assessments
                    ->where('performance_level', 'high')
                    ->where('potential_level', 'high')
                    ->count(),
                'risks' => $assessments
                    ->where('performance_level', 'low')
                    ->where('potential_level', 'low')
                    ->count(),
            ],
        ]);
    }

    /**
     * Create or update the assessment for an employee under the tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'performance_level' => ['required', Rule::in(self::LEVELS)],
            'potential_level' => ['required', Rule::in(self::LEVELS)],
            'note' => ['nullable', 'string'],
            'successor_for' => ['nullable', 'string', 'max:255'],
        ]);

        TalentAssessment::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'employee_id' => $data['employee_id'],
            ],
            [
                'performance_level' => $data['performance_level'],
                'potential_level' => $data['potential_level'],
                'note' => $data['note'] ?? null,
                'successor_for' => $data['successor_for'] ?? null,
            ],
        );

        return redirect()->route('avana.talenta')
            ->with('success', 'Penilaian talenta disimpan');
    }

    /**
     * Update an existing talent assessment.
     */
    public function update(Request $request, TalentAssessment $assessment): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $assessment);

        $data = $request->validate([
            'performance_level' => ['required', Rule::in(self::LEVELS)],
            'potential_level' => ['required', Rule::in(self::LEVELS)],
            'note' => ['nullable', 'string'],
            'successor_for' => ['nullable', 'string', 'max:255'],
        ]);

        $assessment->update([
            'performance_level' => $data['performance_level'],
            'potential_level' => $data['potential_level'],
            'note' => $data['note'] ?? null,
            'successor_for' => $data['successor_for'] ?? null,
        ]);

        return redirect()->route('avana.talenta')
            ->with('success', 'Penilaian talenta diperbarui');
    }

    /**
     * Delete a talent assessment.
     */
    public function destroy(Request $request, TalentAssessment $assessment): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $assessment);

        $assessment->delete();

        return back()->with('success', 'Penilaian talenta dihapus');
    }

    /**
     * Build the chip shape consumed by the 9-box grid.
     *
     * @return array<string, mixed>
     */
    private function transformAssessment(TalentAssessment $assessment): array
    {
        return [
            'id' => $assessment->id,
            'employee_id' => $assessment->employee_id,
            'employee' => $assessment->employee?->full_name,
            'employee_number' => $assessment->employee?->employee_number,
            'performance_level' => $assessment->performance_level,
            'potential_level' => $assessment->potential_level,
            'note' => $assessment->note,
            'successor_for' => $assessment->successor_for,
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
            ->get(['id', 'full_name'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
            ])
            ->all();
    }

    /**
     * Build the `{ value, label }` list of rating levels.
     *
     * @return array<int, array<string, string>>
     */
    private function levelOptions(): array
    {
        $labels = [
            'low' => 'Rendah',
            'medium' => 'Sedang',
            'high' => 'Tinggi',
        ];

        return collect(self::LEVELS)
            ->map(fn (string $level): array => [
                'value' => $level,
                'label' => $labels[$level],
            ])
            ->all();
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, TalentAssessment $record): void
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

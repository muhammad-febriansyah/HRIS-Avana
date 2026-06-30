<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Competency;
use App\Models\Employee;
use App\Models\EmployeeCompetency;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompetencyController extends Controller
{
    /**
     * Roles that may always manage competencies within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Display the competency master together with the assessment matrix.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $competencies = Competency::forTenant($tenantId)
            ->orderBy('name')
            ->get()
            ->map(fn (Competency $competency): array => $this->transformCompetency($competency));

        $employees = Employee::forTenant($tenantId)
            ->orderBy('full_name')
            ->get(['id', 'full_name'])
            ->map(fn (Employee $employee): array => [
                'id' => $employee->id,
                'name' => $employee->full_name,
            ]);

        $records = EmployeeCompetency::forTenant($tenantId)->get();

        $matrix = $records
            ->mapWithKeys(fn (EmployeeCompetency $record): array => [
                "{$record->employee_id}-{$record->competency_id}" => (int) $record->level,
            ])
            ->all();

        return Inertia::render('avana/kompetensi/index', [
            'competencies' => $competencies->all(),
            'employees' => $employees->all(),
            'matrix' => $matrix,
            'kpis' => [
                'total_competencies' => $competencies->count(),
                'average_level' => round((float) $records->avg('level'), 2),
            ],
        ]);
    }

    /**
     * Persist a new competency under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateCompetency($request);

        Competency::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.kompetensi')
            ->with('success', 'Kompetensi berhasil ditambahkan');
    }

    /**
     * Update an existing competency.
     */
    public function update(Request $request, Competency $competency): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $competency);

        $data = $this->validateCompetency($request);

        $competency->update($data);

        return redirect()->route('avana.kompetensi')
            ->with('success', 'Kompetensi berhasil diperbarui');
    }

    /**
     * Delete a competency.
     */
    public function destroy(Request $request, Competency $competency): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $competency);

        $competency->delete();

        return back()->with('success', 'Kompetensi dihapus');
    }

    /**
     * Record (or update) an employee's level for a competency.
     */
    public function assess(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'competency_id' => ['required', 'integer', Rule::exists('competencies', 'id')->where('tenant_id', $tenantId)],
            'level' => ['required', 'integer', 'min:1', 'max:5'],
            'assessed_at' => ['nullable', 'date'],
        ]);

        EmployeeCompetency::updateOrCreate(
            [
                'employee_id' => $data['employee_id'],
                'competency_id' => $data['competency_id'],
            ],
            [
                'tenant_id' => $tenantId,
                'level' => $data['level'],
                'assessed_at' => $data['assessed_at'] ?? now()->toDateString(),
            ],
        );

        return back()->with('success', 'Penilaian kompetensi disimpan');
    }

    /**
     * Validate the create/update payload for a competency.
     *
     * @return array<string, mixed>
     */
    private function validateCompetency(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);
    }

    /**
     * Build the row shape consumed by the competency master table.
     *
     * @return array<string, mixed>
     */
    private function transformCompetency(Competency $competency): array
    {
        return [
            'id' => $competency->id,
            'name' => $competency->name,
            'category' => $competency->category,
            'description' => $competency->description,
        ];
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, Competency $record): void
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

<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\SalaryGrade;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SalaryStructureController extends Controller
{
    /**
     * Roles that may always manage the salary structure within their tenant.
     *
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr', 'manager'];

    /**
     * Display the tenant's salary grades ordered by level.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $grades = SalaryGrade::forTenant($tenantId)
            ->orderBy('level')
            ->orderBy('grade_code')
            ->get()
            ->map(fn (SalaryGrade $grade): array => $this->shapeGrade($grade));

        return Inertia::render('avana/struktur-upah/index', [
            'grades' => $grades,
            'kpis' => [
                'total_grades' => $grades->count(),
                'lowest_salary' => $grades->isEmpty() ? 0.0 : (float) $grades->min('min_salary'),
                'highest_salary' => $grades->isEmpty() ? 0.0 : (float) $grades->max('max_salary'),
            ],
        ]);
    }

    /**
     * Persist a new salary grade under the acting user's tenant.
     */
    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $data = $this->validateGrade($request);

        SalaryGrade::create([
            ...$data,
            'tenant_id' => $tenantId,
        ]);

        return redirect()->route('avana.struktur-upah')
            ->with('success', 'Grade upah ditambahkan');
    }

    /**
     * Update an existing salary grade.
     */
    public function update(Request $request, SalaryGrade $grade): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $grade);

        $data = $this->validateGrade($request);

        $grade->update($data);

        return redirect()->route('avana.struktur-upah')
            ->with('success', 'Grade upah diperbarui');
    }

    /**
     * Delete a salary grade.
     */
    public function destroy(Request $request, SalaryGrade $grade): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureTenantOwnership($request, $grade);

        $grade->delete();

        return back()->with('success', 'Grade upah dihapus');
    }

    /**
     * Validate the create/update payload for a salary grade.
     *
     * @return array<string, mixed>
     */
    private function validateGrade(Request $request): array
    {
        return $request->validate([
            'grade_code' => ['required', 'string', 'max:50'],
            'grade_name' => ['required', 'string', 'max:255'],
            'level' => ['required', 'integer', 'min:1'],
            'min_salary' => ['required', 'numeric', 'min:0'],
            'mid_salary' => ['required', 'numeric', 'min:0', 'gte:min_salary'],
            'max_salary' => ['required', 'numeric', 'min:0', 'gte:mid_salary'],
        ]);
    }

    /**
     * Build the row shape consumed by the salary grade table.
     *
     * @return array<string, mixed>
     */
    private function shapeGrade(SalaryGrade $grade): array
    {
        return [
            'id' => $grade->id,
            'grade_code' => $grade->grade_code,
            'grade_name' => $grade->grade_name,
            'level' => (int) $grade->level,
            'min_salary' => (float) $grade->min_salary,
            'mid_salary' => (float) $grade->mid_salary,
            'max_salary' => (float) $grade->max_salary,
        ];
    }

    /**
     * Abort with 404 when the record does not belong to the user's tenant.
     */
    private function ensureTenantOwnership(Request $request, SalaryGrade $grade): void
    {
        abort_if((int) $grade->tenant_id !== (int) $request->user()->tenant_id, 404);
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

<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\SalaryGrade;
use App\Models\SalaryGradeStep;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Nilai Upah" (BPR payroll submenu): the concrete basic-wage nominal per
 * salary grade × masa-kerja step (the golongan-ruang wage scale). Struktur &
 * Skala Upah defines the grade bands; this fills in the exact rupiah per step.
 */
class SalaryGradeStepController extends Controller
{
    /**
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $grades = SalaryGrade::forTenant($tenantId)
            ->orderBy('level')
            ->orderBy('grade_code')
            ->get(['id', 'grade_code', 'grade_name', 'level', 'min_salary', 'max_salary'])
            ->map(fn (SalaryGrade $g): array => [
                'id' => $g->id,
                'grade_code' => $g->grade_code,
                'grade_name' => $g->grade_name,
                'level' => (int) $g->level,
                'min_salary' => (float) $g->min_salary,
                'max_salary' => (float) $g->max_salary,
            ]);

        $steps = SalaryGradeStep::forTenant($tenantId)
            ->orderBy('salary_grade_id')
            ->orderBy('masa_kerja')
            ->get()
            ->map(fn (SalaryGradeStep $s): array => [
                'id' => $s->id,
                'salary_grade_id' => $s->salary_grade_id,
                'masa_kerja' => (int) $s->masa_kerja,
                'amount' => (float) $s->amount,
                'note' => $s->note,
            ]);

        return Inertia::render('avana/payroll-nilai-upah/index', [
            'grades' => $grades,
            'steps' => $steps,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'salary_grade_id' => ['required', 'integer', Rule::exists('salary_grades', 'id')->where('tenant_id', $tenantId)],
            'masa_kerja' => ['required', 'integer', 'min:0', 'max:100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        SalaryGradeStep::updateOrCreate(
            [
                'tenant_id' => $tenantId,
                'salary_grade_id' => $data['salary_grade_id'],
                'masa_kerja' => $data['masa_kerja'],
            ],
            ['amount' => $data['amount'], 'note' => $data['note'] ?? null],
        );

        return back()->with('success', 'Nilai Upah disimpan');
    }

    public function destroy(Request $request, SalaryGradeStep $nilaiUpah): RedirectResponse
    {
        $this->ensureCanManage($request);
        abort_if((int) $nilaiUpah->tenant_id !== (int) $request->user()->tenant_id, 404);

        $nilaiUpah->delete();

        return back()->with('success', 'Nilai Upah dihapus');
    }

    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::PRIVILEGED_ROLES)->isNotEmpty();

        $hasPermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->contains(fn (string $code): bool => str_starts_with($code, 'payroll.'));

        abort_unless($isPrivileged || $hasPermission, 403);
    }
}

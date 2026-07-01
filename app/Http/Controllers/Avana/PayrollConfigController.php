<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\BpjsProgram;
use App\Models\BpjsRate;
use App\Models\EmployeeBpjsProfile;
use App\Models\Pph21TerRate;
use App\Models\TaxProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Manages the global payroll statutory configuration: BPJS programs (with their
 * contribution rates) and PPh 21 TER tax brackets.
 *
 * NOTE: bpjs_programs, bpjs_rates and pph21_ter_rates are GLOBAL master tables —
 * they carry no tenant_id and are shared across every tenant. Only the profile
 * counts surfaced on the index are tenant-scoped.
 */
class PayrollConfigController extends Controller
{
    /**
     * Roles that may always manage the payroll statutory configuration.
     *
     * @var array<int, string>
     */
    private const MANAGER_ROLES = ['super_admin', 'admin_tenant_hr'];

    /**
     * Permissions that grant access to manage the configuration.
     *
     * @var array<int, string>
     */
    private const MANAGE_PERMISSIONS = ['bpjs.manage', 'pph21.manage'];

    /**
     * Render the BPJS & PPh 21 configuration screen with every program (and its
     * rates), the PPh 21 TER brackets, and tenant-scoped profile counts.
     */
    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = $request->user()->tenant_id;

        $programs = BpjsProgram::query()
            ->with(['rates' => fn ($query) => $query->orderByDesc('effective_start_date')->orderByDesc('id')])
            ->orderBy('name')
            ->get()
            ->map(fn (BpjsProgram $program): array => [
                'id' => $program->id,
                'code' => $program->code,
                'name' => $program->name,
                'type' => $program->type,
                'description' => $program->description,
                'is_active' => $program->is_active,
                'rates' => $program->rates->map(fn (BpjsRate $rate): array => [
                    'id' => $rate->id,
                    'employee_rate' => $rate->employee_rate,
                    'company_rate' => $rate->company_rate,
                    'max_wage' => $rate->max_wage,
                    'min_wage' => $rate->min_wage,
                    'risk_level' => $rate->risk_level,
                    'effective_start_date' => $rate->effective_start_date?->toDateString(),
                    'effective_end_date' => $rate->effective_end_date?->toDateString(),
                    'is_active' => $rate->is_active,
                ])->all(),
            ]);

        $terRates = Pph21TerRate::query()
            ->orderBy('category')
            ->orderBy('income_min')
            ->get()
            ->map(fn (Pph21TerRate $rate): array => [
                'id' => $rate->id,
                'category' => $rate->category,
                'income_min' => $rate->income_min,
                'income_max' => $rate->income_max,
                'rate' => $rate->rate,
                'effective_start_date' => $rate->effective_start_date?->toDateString(),
                'effective_end_date' => $rate->effective_end_date?->toDateString(),
                'is_active' => $rate->is_active,
            ]);

        return Inertia::render('avana/payroll-config/index', [
            'programs' => $programs,
            'terRates' => $terRates,
            'profileStats' => [
                'bpjs_profiles' => EmployeeBpjsProfile::where('tenant_id', $tenantId)->count(),
                'tax_profiles' => TaxProfile::where('tenant_id', $tenantId)->count(),
            ],
        ]);
    }

    /**
     * Validate and create a new BPJS program together with its primary rate.
     */
    public function storeBpjsProgram(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate(
            $this->bpjsProgramRules(),
            $this->messages(),
        );

        $program = BpjsProgram::create([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $program->rates()->create($this->rateAttributes($request, $validated));

        return back()->with('success', 'Program BPJS disimpan');
    }

    /**
     * Validate and update a BPJS program, updating or creating its latest rate.
     */
    public function updateBpjsProgram(Request $request, BpjsProgram $program): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate(
            $this->bpjsProgramRules($program->id),
            $this->messages(),
        );

        $program->update([
            'code' => $validated['code'],
            'name' => $validated['name'],
            'type' => $validated['type'],
            'description' => $validated['description'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $rate = $program->rates()
            ->orderByDesc('effective_start_date')
            ->orderByDesc('id')
            ->first();

        $attributes = $this->rateAttributes($request, $validated);

        if ($rate !== null) {
            $rate->update($attributes);
        } else {
            $program->rates()->create($attributes);
        }

        return back()->with('success', 'Program BPJS diperbarui');
    }

    /**
     * Soft delete a BPJS program.
     */
    public function destroyBpjsProgram(Request $request, BpjsProgram $program): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $program->delete();

        return back()->with('success', 'Program BPJS dihapus');
    }

    /**
     * Validate and create a new PPh 21 TER tax bracket.
     */
    public function storeTerRate(Request $request): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate(
            $this->terRateRules(),
            $this->messages(),
        );

        Pph21TerRate::create([
            'category' => $validated['category'],
            'income_min' => $validated['income_min'],
            'income_max' => $validated['income_max'] ?? null,
            'rate' => $validated['rate'],
            'effective_start_date' => $validated['effective_start_date'],
            'effective_end_date' => $validated['effective_end_date'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Tarif PPh 21 disimpan');
    }

    /**
     * Validate and update an existing PPh 21 TER tax bracket.
     */
    public function updateTerRate(Request $request, Pph21TerRate $rate): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $validated = $request->validate(
            $this->terRateRules(),
            $this->messages(),
        );

        $rate->update([
            'category' => $validated['category'],
            'income_min' => $validated['income_min'],
            'income_max' => $validated['income_max'] ?? null,
            'rate' => $validated['rate'],
            'effective_start_date' => $validated['effective_start_date'],
            'effective_end_date' => $validated['effective_end_date'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        return back()->with('success', 'Tarif PPh 21 diperbarui');
    }

    /**
     * Delete a PPh 21 TER tax bracket.
     */
    public function destroyTerRate(Request $request, Pph21TerRate $rate): RedirectResponse
    {
        $this->ensureSuperAdmin($request);

        $rate->delete();

        return back()->with('success', 'Tarif PPh 21 dihapus');
    }

    /**
     * Validation rules for a BPJS program and its primary rate.
     *
     * @return array<string, array<int, mixed>>
     */
    private function bpjsProgramRules(?int $programId = null): array
    {
        $code = Rule::unique('bpjs_programs', 'code');

        if ($programId !== null) {
            $code->ignore($programId);
        }

        return [
            'code' => ['required', 'string', 'max:255', $code],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
            'employee_rate' => ['required', 'numeric', 'min:0'],
            'company_rate' => ['required', 'numeric', 'min:0'],
            'min_wage' => ['nullable', 'numeric', 'min:0'],
            'max_wage' => ['nullable', 'numeric', 'min:0'],
            'risk_level' => ['nullable', 'string', 'max:255'],
            'effective_start_date' => ['required', 'date'],
            'effective_end_date' => ['nullable', 'date', 'after_or_equal:effective_start_date'],
        ];
    }

    /**
     * Validation rules for a PPh 21 TER tax bracket.
     *
     * @return array<string, array<int, mixed>>
     */
    private function terRateRules(): array
    {
        return [
            'category' => ['required', 'string', 'max:255'],
            'income_min' => ['required', 'numeric', 'min:0'],
            'income_max' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0'],
            'effective_start_date' => ['required', 'date'],
            'effective_end_date' => ['nullable', 'date', 'after_or_equal:effective_start_date'],
            'is_active' => ['boolean'],
        ];
    }

    /**
     * Map validated input onto a BPJS rate's mass-assignable attributes.
     *
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function rateAttributes(Request $request, array $validated): array
    {
        return [
            'employee_rate' => $validated['employee_rate'],
            'company_rate' => $validated['company_rate'],
            'min_wage' => $validated['min_wage'] ?? null,
            'max_wage' => $validated['max_wage'] ?? null,
            'risk_level' => $validated['risk_level'] ?? null,
            'effective_start_date' => $validated['effective_start_date'],
            'effective_end_date' => $validated['effective_end_date'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];
    }

    /**
     * Indonesian validation messages shared across both sections.
     *
     * @return array<string, string>
     */
    private function messages(): array
    {
        return [
            'code.required' => 'Kode wajib diisi.',
            'code.unique' => 'Kode sudah digunakan.',
            'name.required' => 'Nama wajib diisi.',
            'type.required' => 'Jenis wajib dipilih.',
            'employee_rate.required' => 'Iuran karyawan wajib diisi.',
            'employee_rate.numeric' => 'Iuran karyawan harus berupa angka.',
            'company_rate.required' => 'Iuran perusahaan wajib diisi.',
            'company_rate.numeric' => 'Iuran perusahaan harus berupa angka.',
            'effective_start_date.required' => 'Tanggal berlaku wajib diisi.',
            'effective_start_date.date' => 'Tanggal berlaku tidak valid.',
            'category.required' => 'Kategori wajib diisi.',
            'income_min.required' => 'Batas bawah penghasilan wajib diisi.',
            'income_min.numeric' => 'Batas bawah penghasilan harus berupa angka.',
            'rate.required' => 'Tarif wajib diisi.',
            'rate.numeric' => 'Tarif harus berupa angka.',
        ];
    }

    /**
     * Abort with 403 unless the user is privileged or holds a manage permission.
     */
    private function ensureCanManage(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles.permissions');

        $isPrivileged = $user->roles->whereIn('code', self::MANAGER_ROLES)->isNotEmpty();

        $hasManagePermission = $user->roles
            ->pluck('permissions')
            ->flatten()
            ->pluck('code')
            ->intersect(self::MANAGE_PERMISSIONS)
            ->isNotEmpty();

        abort_unless($isPrivileged || $hasManagePermission, 403);
    }

    /**
     * Abort with 403 unless the user is a super admin. BPJS programs/rates and
     * PPh 21 TER rates are GLOBAL (no tenant_id) statutory config shared by every
     * tenant, so only a super admin may change them — a tenant admin editing them
     * would alter the rates for all tenants.
     */
    private function ensureSuperAdmin(Request $request): void
    {
        /** @var User $user */
        $user = $request->user();
        $user->loadMissing('roles');

        abort_unless($user->roles->contains(fn ($role): bool => $role->code === 'super_admin'), 403);
    }
}

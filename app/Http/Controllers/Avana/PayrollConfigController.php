<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\BpjsProgram;
use App\Models\BpjsRate;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\PkpRate;
use App\Models\PtkpRate;
use App\Models\TaxProfile;
use App\Models\User;
use App\Support\FeatureGate;
use App\Support\Pph21Calculator;
use App\Support\Pph21Ter;
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
     * Render the BPJS & PPh 21 configuration screen with every program (and its
     * rates), the PPh 21 TER brackets, and tenant-scoped profile counts.
     */
    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'payroll', 'view');

        // The screen has no menu of its own beyond the payroll feature, so its
        // two halves answer to the BPJS and PPh 21 toggles: with both off there
        // is nothing here for the tenant to configure.
        FeatureGate::ensureAny($request->user(), ['bpjs', 'pph21']);

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

        $ptkpRates = PtkpRate::forTenant($tenantId)
            ->orderByDesc('year')
            ->orderBy('ptkp_status')
            ->get()
            ->map(fn (PtkpRate $rate): array => [
                'id' => $rate->id,
                'ptkp_status' => $rate->ptkp_status,
                'year' => $rate->year,
                'amount' => (float) $rate->amount,
                'note' => $rate->note,
            ]);

        $pkpRates = PkpRate::forTenant($tenantId)
            ->orderByDesc('year')
            ->orderBy('sort_order')
            ->orderBy('up_to')
            ->get()
            ->map(fn (PkpRate $rate): array => [
                'id' => $rate->id,
                'year' => $rate->year,
                'up_to' => $rate->up_to !== null ? (float) $rate->up_to : null,
                'rate' => (float) $rate->rate,
                'sort_order' => $rate->sort_order,
            ]);

        $taxProfiles = Employee::forTenant($tenantId)
            ->with('taxProfile')
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'employee_number'])
            ->map(fn (Employee $e): array => [
                'employee_id' => $e->id,
                'name' => $e->full_name,
                'employee_number' => $e->employee_number,
                'npwp' => $e->taxProfile?->npwp,
                'nik' => $e->taxProfile?->nik,
                'ptkp_status' => $e->taxProfile?->ptkp_status,
                'tax_subject' => $e->taxProfile?->tax_subject ?? 'pegawai_tetap',
                'wage_basis' => $e->taxProfile?->wage_basis ?? 'monthly',
                'daily_wage' => $e->taxProfile?->daily_wage !== null ? (float) $e->taxProfile->daily_wage : null,
                'is_pph21_exempt' => (bool) $e->taxProfile?->is_pph21_exempt,
                'pph21_exempt_reason' => $e->taxProfile?->pph21_exempt_reason,
            ]);

        return Inertia::render('avana/payroll-config/index', [
            'programs' => $programs,
            'ptkpRates' => $ptkpRates,
            'pkpRates' => $pkpRates,
            'taxProfiles' => $taxProfiles,
            'taxSubjects' => $this->taxSubjectOptions(),
            'ptkpStatuses' => Pph21Ter::PTKP_STATUSES,
            'profileStats' => [
                'bpjs_profiles' => EmployeeBpjsProfile::where('tenant_id', $tenantId)->count(),
                'tax_profiles' => TaxProfile::where('tenant_id', $tenantId)->count(),
            ],
            'settings' => [
                'enforce_payroll_segregation' => (bool) $request->user()->tenant?->enforce_payroll_segregation,
            ],
            'features' => FeatureGate::map($request->user(), ['bpjs', 'pph21']),
        ]);
    }

    /**
     * The PPh 21 subject options ({value, label}) mirroring the official
     * "Resume Skema" income-recipient categories.
     *
     * @return array<int, array{value: string, label: string}>
     */
    /**
     * Tidy a PTKP status typed by hand: "tk/0", " K/2 " and "k-3" all name a
     * real status. Anything else is left as-is for the rule to reject.
     */
    private function normalisePtkp(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        return str_replace(['-', ' '], ['/', ''], strtoupper(trim($value)));
    }

    private function taxSubjectOptions(): array
    {
        $labels = [
            'pegawai_tetap' => 'Pegawai Tetap & Pensiunan',
            'pns' => 'PNS/TNI/Polri/Pejabat Negara',
            'komisaris' => 'Dewan Komisaris/Pengawas',
            'pegawai_tidak_tetap' => 'Pegawai Tidak Tetap',
            'bukan_pegawai' => 'Bukan Pegawai',
            'peserta_kegiatan' => 'Peserta Kegiatan',
            'peserta_pensiun' => 'Peserta Program Pensiun',
            'mantan_pegawai' => 'Mantan Pegawai',
        ];

        return array_map(
            fn (string $key): array => ['value' => $key, 'label' => $labels[$key] ?? $key],
            Pph21Calculator::SUBJECTS,
        );
    }

    /**
     * Create or update an employee's PPh 21 tax profile — the subject category
     * (drives the withholding scheme), PTKP status, and daily-wage settings.
     */
    public function upsertTaxProfile(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'pph21', 'update');

        $tenantId = (int) $request->user()->tenant_id;

        // "tk/0" is the same status as "TK/0" — normalise before the rule sees
        // it, so case is forgiven while a genuine typo is not.
        $request->merge(['ptkp_status' => $this->normalisePtkp($request->input('ptkp_status'))]);

        $validated = $request->validate([
            'employee_id' => ['required', 'integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
            'tax_subject' => ['required', Rule::in(Pph21Calculator::SUBJECTS)],
            // Free text used to be accepted here, and a typo like "K/O" (letter
            // O) fell through to Kategori A without a word — the wrong rate on
            // every payslip until somebody noticed.
            'ptkp_status' => ['nullable', Rule::in(Pph21Ter::PTKP_STATUSES)],
            'wage_basis' => ['required', Rule::in(['monthly', 'daily'])],
            'daily_wage' => ['nullable', 'numeric', 'min:0'],
            'npwp' => ['nullable', 'string', 'max:32'],
            'nik' => ['nullable', 'string', 'max:32'],
            // Someone the tenant does not withhold Article 21 for at all — an
            // expatriate under PPh 26, a person already withheld at source.
            'is_pph21_exempt' => ['nullable', 'boolean'],
            'pph21_exempt_reason' => ['nullable', 'string', 'max:255'],
        ]);

        TaxProfile::updateOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => $validated['employee_id']],
            [
                'tax_subject' => $validated['tax_subject'],
                'ptkp_status' => $validated['ptkp_status'] !== null && $validated['ptkp_status'] !== ''
                    ? strtoupper($validated['ptkp_status'])
                    : null,
                'wage_basis' => $validated['wage_basis'],
                'daily_wage' => $validated['wage_basis'] === 'daily' ? ($validated['daily_wage'] ?? null) : null,
                'npwp' => $validated['npwp'] ?? null,
                'nik' => $validated['nik'] ?? null,
                'is_pph21_exempt' => (bool) ($validated['is_pph21_exempt'] ?? false),
                'pph21_exempt_reason' => ($validated['is_pph21_exempt'] ?? false)
                    ? ($validated['pph21_exempt_reason'] ?? null)
                    : null,
            ],
        );

        return back()->with('success', 'Profil pajak karyawan disimpan');
    }

    /**
     * Toggle tenant-level payroll controls (currently segregation of duties).
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $user = $request->user();
        $user->loadMissing('roles');
        abort_unless($user->roles->whereIn('code', self::MANAGER_ROLES)->isNotEmpty(), 403);

        $validated = $request->validate([
            'enforce_payroll_segregation' => ['required', 'boolean'],
        ]);

        $tenant = $user->tenant;
        abort_if($tenant === null, 404);

        $tenant->update([
            'enforce_payroll_segregation' => $validated['enforce_payroll_segregation'],
        ]);

        return back()->with('success', 'Pengaturan payroll disimpan');
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
     * Create a Tarif PTKP entry (annual allowance per marital status/year).
     */
    public function storePtkpRate(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'pph21', 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $request->merge(['ptkp_status' => $this->normalisePtkp($request->input('ptkp_status'))]);

        $validated = $request->validate([
            // A rate row keyed to a status nothing resolves to would never be
            // read — the lookup falls back to the statutory formula instead.
            'ptkp_status' => ['required', Rule::in(Pph21Ter::PTKP_STATUSES)],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'amount' => ['required', 'numeric', 'min:0'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        PtkpRate::updateOrCreate(
            ['tenant_id' => $tenantId, 'ptkp_status' => strtoupper($validated['ptkp_status']), 'year' => $validated['year']],
            ['amount' => $validated['amount'], 'note' => $validated['note'] ?? null],
        );

        return back()->with('success', 'Tarif PTKP disimpan');
    }

    /**
     * Delete a Tarif PTKP entry.
     */
    public function destroyPtkpRate(Request $request, PtkpRate $rate): RedirectResponse
    {
        $this->ensureCan($request, 'pph21', 'archive');
        abort_if((int) $rate->tenant_id !== (int) $request->user()->tenant_id, 404);

        $rate->delete();

        return back()->with('success', 'Tarif PTKP dihapus');
    }

    /**
     * Create a Tarif PKP progressive bracket (cumulative upper bound + rate).
     */
    public function storePkpRate(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'pph21', 'create');

        $tenantId = (int) $request->user()->tenant_id;

        $validated = $request->validate([
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'up_to' => ['nullable', 'numeric', 'min:0'],
            'rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        PkpRate::create([
            'tenant_id' => $tenantId,
            'year' => $validated['year'],
            'up_to' => $validated['up_to'] ?? null,
            'rate' => $validated['rate'],
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return back()->with('success', 'Tarif PKP disimpan');
    }

    /**
     * Delete a Tarif PKP bracket.
     */
    public function destroyPkpRate(Request $request, PkpRate $rate): RedirectResponse
    {
        $this->ensureCan($request, 'pph21', 'archive');
        abort_if((int) $rate->tenant_id !== (int) $request->user()->tenant_id, 404);

        $rate->delete();

        return back()->with('success', 'Tarif PKP dihapus');
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
     * Authorize a `{module}.{action}` permission (super admin bypasses). The
     * module is passed per call because this screen spans several permission
     * domains (payroll settings, PPh 21 tax tables).
     */
    private function ensureCan(Request $request, string $module, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        // A module that is also a tenant feature (pph21, bpjs) answers to its
        // switch first — the permission only decides what to do once the tenant
        // actually has the feature.
        if (array_key_exists($module, FeatureGate::TAB_FEATURES)) {
            FeatureGate::ensure($user, $module);
        }

        abort_unless($user->hasPermissionTo($module.'.'.$action), 403);
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

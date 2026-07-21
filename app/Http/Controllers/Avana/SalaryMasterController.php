<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\DayCalcMethod;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\SalaryMaster;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Master Gaji" (BPR manual 1.2.1): salary templates — a named set of
 * components plus period/cut-off/day settings — attached to employees so
 * payroll runs against a consistent structure per kategori gaji.
 */
class SalaryMasterController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'payroll';

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        $masters = SalaryMaster::forTenant($tenantId)
            ->withCount(['employees', 'components as included_count' => fn ($q) => $q->where('included', true)])
            ->orderBy('code')
            ->get()
            ->map(fn (SalaryMaster $m): array => [
                'id' => $m->id,
                'code' => $m->code,
                'category' => $m->category,
                'note' => $m->note,
                'is_active' => $m->is_active,
                'employees_count' => $m->employees_count,
                'included_count' => $m->included_count,
            ]);

        return Inertia::render('avana/payroll-master-gaji/index', [
            'masters' => $masters,
        ]);
    }

    /**
     * The Master Gaji detail/setting form (BPR reference screens): the
     * Penerimaan|Potongan membership checklist, the perhitungan settings, and
     * the Prorate / Overtime / Kompensasi component checklists.
     */
    public function setting(Request $request, SalaryMaster $master): Response
    {
        $this->ensureCan($request, 'view');
        $this->ensureOwnership($request, $master->tenant_id);

        $tenantId = (int) $request->user()->tenant_id;

        $master->load('components');

        $flags = $master->components->keyBy('payroll_component_id');

        $components = PayrollComponent::forTenant($tenantId)
            ->orderBy('name')
            ->get(['id', 'name', 'component_group'])
            ->map(fn (PayrollComponent $c): array => [
                'id' => $c->id,
                'name' => $c->name,
                'group' => $c->component_group ?? 'penerimaan',
                'included' => (bool) ($flags[$c->id]->included ?? false),
                'amount' => (float) ($flags[$c->id]->amount ?? 0),
                'is_prorate' => (bool) ($flags[$c->id]->is_prorate ?? false),
                'is_overtime_base' => (bool) ($flags[$c->id]->is_overtime_base ?? false),
                'is_kompensasi' => (bool) ($flags[$c->id]->is_kompensasi ?? false),
            ]);

        return Inertia::render('avana/payroll-master-gaji/setting', [
            'master' => [
                'id' => $master->id,
                'code' => $master->code,
                'category' => $master->category,
                'note' => $master->note,
                'is_active' => $master->is_active,
                'process_type' => $master->process_type,
                'period_start_day' => $master->period_start_day,
                'period_end_day' => $master->period_end_day,
                'cut_off_day' => $master->cut_off_day,
                'day_divisor' => $master->day_divisor,
                'day_calc_method' => $master->day_calc_method,
                'day_calc_method_id' => $master->day_calc_method_id,
                'overtime_calc_method' => $master->overtime_calc_method,
                'overtime_start_day' => $master->overtime_start_day,
                'overtime_end_day' => $master->overtime_end_day,
                'overtime_period' => $master->overtime_period,
                'attendance_start_day' => $master->attendance_start_day,
                'attendance_end_day' => $master->attendance_end_day,
                'attendance_period' => $master->attendance_period,
                'compensation_method' => $master->compensation_method,
                'probation_months' => $master->probation_months,
                'employees_count' => $master->employees()->count(),
            ],
            'components' => $components,
            'dayCalcMethods' => DayCalcMethod::forTenant($tenantId)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'basis', 'divisor'])
                ->map(fn (DayCalcMethod $m): array => [
                    'id' => $m->id,
                    'name' => $m->name,
                    'basis' => $m->basis,
                    'divisor' => $m->divisor,
                ])->all(),
            'employeeOptions' => Employee::forTenant($tenantId)
                ->where('status', 'active')
                ->orderBy('full_name')
                ->get(['id', 'full_name', 'salary_master_id'])
                ->map(fn (Employee $e): array => [
                    'id' => $e->id,
                    'name' => $e->full_name,
                    'salary_master_id' => $e->salary_master_id,
                ])->all(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'create');
        $tenantId = (int) $request->user()->tenant_id;

        $data = $this->validateMaster($request, $tenantId);

        SalaryMaster::create([
            'tenant_id' => $tenantId,
            ...$this->masterAttributes($data, $request),
        ]);

        return back()->with('success', 'Master Gaji disimpan');
    }

    public function update(Request $request, SalaryMaster $master): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $master->tenant_id);

        $data = $this->validateMaster($request, (int) $master->tenant_id, $master->id);

        $master->update($this->masterAttributes($data, $request));

        return back()->with('success', 'Master Gaji diperbarui');
    }

    public function destroy(Request $request, SalaryMaster $master): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        $this->ensureOwnership($request, $master->tenant_id);

        $master->delete();

        return back()->with('success', 'Master Gaji dihapus');
    }

    /**
     * @var array<int, string>
     */
    private const COMPONENT_FLAGS = ['included', 'is_prorate', 'is_overtime_base', 'is_kompensasi'];

    /**
     * Toggle one checklist flag (included membership, or the prorate / overtime /
     * kompensasi section flags) on a component for this Master Gaji. The row is
     * kept while any flag is set and removed once every flag is cleared.
     */
    public function setComponent(Request $request, SalaryMaster $master): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $master->tenant_id);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'payroll_component_id' => ['required', 'integer', Rule::exists('payroll_components', 'id')->where('tenant_id', $tenantId)],
            'flag' => ['required', Rule::in(self::COMPONENT_FLAGS)],
            'checked' => ['required', 'boolean'],
        ]);

        $row = $master->components()->firstOrNew(['payroll_component_id' => $data['payroll_component_id']]);

        // A brand-new row must start with every flag off (the table default for
        // `included` is true), so toggling e.g. prorate never silently marks the
        // component as a paid member.
        if (! $row->exists) {
            $row->included = false;
            $row->is_prorate = false;
            $row->is_overtime_base = false;
            $row->is_kompensasi = false;
        }

        $row->{$data['flag']} = $data['checked'];
        $row->save();

        // Drop the row once no flag is set so it does not linger empty.
        $stillReferenced = collect(self::COMPONENT_FLAGS)->contains(fn (string $f): bool => (bool) $row->{$f});

        if (! $stillReferenced) {
            $row->delete();
        }

        return back()->with('success', 'Komponen master diperbarui');
    }

    /**
     * Set the monthly nominal for a component on this Master Gaji. Master Gaji is
     * the single place nominals live; a per-employee salary row still overrides
     * it. The component is marked included so the amount is actually paid.
     */
    public function setComponentAmount(Request $request, SalaryMaster $master): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $master->tenant_id);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'payroll_component_id' => ['required', 'integer', Rule::exists('payroll_components', 'id')->where('tenant_id', $tenantId)],
            'amount' => ['required', 'numeric', 'min:0'],
        ]);

        $row = $master->components()->firstOrNew(['payroll_component_id' => $data['payroll_component_id']]);

        // Setting a nominal implies membership, so a fresh row is paid.
        if (! $row->exists) {
            $row->included = true;
            $row->is_prorate = false;
            $row->is_overtime_base = false;
            $row->is_kompensasi = false;
        }

        $row->amount = $data['amount'];
        $row->save();

        return back()->with('success', 'Nominal komponen master disimpan');
    }

    /**
     * Attach this Master Gaji to the given employees (BPR manual: master gaji
     * "ditempelkan ke data pegawai").
     */
    public function assign(Request $request, SalaryMaster $master): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        $this->ensureOwnership($request, $master->tenant_id);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'employee_ids' => ['required', 'array'],
            'employee_ids.*' => ['integer', Rule::exists('employees', 'id')->where('tenant_id', $tenantId)],
        ]);

        Employee::forTenant($tenantId)
            ->whereIn('id', $data['employee_ids'])
            ->update(['salary_master_id' => $master->id]);

        return back()->with('success', 'Master Gaji ditempel ke pegawai');
    }

    /**
     * @return array<string, mixed>
     */
    private function validateMaster(Request $request, int $tenantId, ?int $ignoreId = null): array
    {
        $code = Rule::unique('salary_masters', 'code')->where('tenant_id', $tenantId);

        if ($ignoreId !== null) {
            $code->ignore($ignoreId);
        }

        $day = ['nullable', 'integer', 'min:1', 'max:31'];
        $period = ['nullable', Rule::in(['berjalan', 'bulan_lalu'])];

        return $request->validate([
            'code' => ['required', 'string', 'max:255', $code],
            'category' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'process_type' => ['nullable', Rule::in(['normal', 'bulan_lalu'])],
            'period_start_day' => $day,
            'period_end_day' => $day,
            'cut_off_day' => $day,
            'day_divisor' => ['nullable', 'integer', 'min:1', 'max:31'],
            'day_calc_method' => ['nullable', Rule::in(['absen', 'hari_kerja', 'hari_kalender', 'formula'])],
            'day_calc_method_id' => ['nullable', 'integer', Rule::exists('day_calc_methods', 'id')->where('tenant_id', $tenantId)],
            'overtime_calc_method' => ['nullable', Rule::in(['reguler', 'flat'])],
            'overtime_start_day' => $day,
            'overtime_end_day' => $day,
            'overtime_period' => $period,
            'attendance_start_day' => $day,
            'attendance_end_day' => $day,
            'attendance_period' => $period,
            'compensation_method' => ['nullable', 'string', 'max:255'],
            'probation_months' => ['nullable', 'integer', 'min:0', 'max:36'],
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function masterAttributes(array $data, Request $request): array
    {
        return [
            'code' => $data['code'],
            'category' => $data['category'],
            'note' => $data['note'] ?? null,
            'is_active' => $request->boolean('is_active', true),
            'process_type' => $data['process_type'] ?? null,
            'period_start_day' => $data['period_start_day'] ?? null,
            'period_end_day' => $data['period_end_day'] ?? null,
            'cut_off_day' => $data['cut_off_day'] ?? null,
            'day_divisor' => $data['day_divisor'] ?? null,
            'day_calc_method' => $data['day_calc_method'] ?? null,
            'day_calc_method_id' => $data['day_calc_method_id'] ?? null,
            'overtime_calc_method' => $data['overtime_calc_method'] ?? null,
            'overtime_start_day' => $data['overtime_start_day'] ?? null,
            'overtime_end_day' => $data['overtime_end_day'] ?? null,
            'overtime_period' => $data['overtime_period'] ?? null,
            'attendance_start_day' => $data['attendance_start_day'] ?? null,
            'attendance_end_day' => $data['attendance_end_day'] ?? null,
            'attendance_period' => $data['attendance_period'] ?? null,
            'compensation_method' => $data['compensation_method'] ?? null,
            'probation_months' => $data['probation_months'] ?? null,
        ];
    }

    private function ensureOwnership(Request $request, int|string|null $tenantId): void
    {
        abort_if((int) $tenantId !== (int) $request->user()->tenant_id, 404);
    }

    private function ensureCan(Request $request, string $action): void
    {
        /** @var User $user */
        $user = $request->user();

        if ($user->isSuperAdmin()) {
            return;
        }

        abort_unless($user->hasPermissionTo(self::MODULE.'.'.$action), 403);
    }
}

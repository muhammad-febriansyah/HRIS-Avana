<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
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
     * @var array<int, string>
     */
    private const PRIVILEGED_ROLES = ['super_admin', 'admin_tenant_hr'];

    public function index(Request $request): Response
    {
        $this->ensureCanManage($request);

        $tenantId = (int) $request->user()->tenant_id;

        $masters = SalaryMaster::forTenant($tenantId)
            ->with(['components.component:id,name,component_group'])
            ->withCount('employees')
            ->orderBy('code')
            ->get()
            ->map(fn (SalaryMaster $m): array => [
                'id' => $m->id,
                'code' => $m->code,
                'category' => $m->category,
                'note' => $m->note,
                'is_active' => $m->is_active,
                'period_day' => $m->period_day,
                'cut_off_day' => $m->cut_off_day,
                'day_divisor' => $m->day_divisor,
                'overtime_period' => $m->overtime_period,
                'attendance_period' => $m->attendance_period,
                'employees_count' => $m->employees_count,
                'components' => $m->components->map(fn ($c): array => [
                    'id' => $c->id,
                    'payroll_component_id' => $c->payroll_component_id,
                    'name' => $c->component?->name,
                    'group' => $c->component?->component_group,
                    'is_prorate' => $c->is_prorate,
                    'is_overtime_base' => $c->is_overtime_base,
                ])->all(),
            ]);

        return Inertia::render('avana/payroll-master-gaji/index', [
            'masters' => $masters,
            'componentOptions' => PayrollComponent::forTenant($tenantId)
                ->orderBy('component_group')
                ->orderBy('name')
                ->get(['id', 'name', 'component_group'])
                ->map(fn (PayrollComponent $c): array => [
                    'id' => $c->id,
                    'name' => $c->name,
                    'group' => $c->component_group,
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
        $this->ensureCanManage($request);
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
        $this->ensureCanManage($request);
        $this->ensureOwnership($request, $master->tenant_id);

        $data = $this->validateMaster($request, (int) $master->tenant_id, $master->id);

        $master->update($this->masterAttributes($data, $request));

        return back()->with('success', 'Master Gaji diperbarui');
    }

    public function destroy(Request $request, SalaryMaster $master): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureOwnership($request, $master->tenant_id);

        $master->delete();

        return back()->with('success', 'Master Gaji dihapus');
    }

    /**
     * Toggle a component into/out of the template's checklist, carrying the
     * prorate / overtime-base flags.
     */
    public function setComponent(Request $request, SalaryMaster $master): RedirectResponse
    {
        $this->ensureCanManage($request);
        $this->ensureOwnership($request, $master->tenant_id);

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'payroll_component_id' => ['required', 'integer', Rule::exists('payroll_components', 'id')->where('tenant_id', $tenantId)],
            'checked' => ['required', 'boolean'],
            'is_prorate' => ['boolean'],
            'is_overtime_base' => ['boolean'],
        ]);

        if ($data['checked']) {
            $master->components()->updateOrCreate(
                ['payroll_component_id' => $data['payroll_component_id']],
                [
                    'is_prorate' => $request->boolean('is_prorate'),
                    'is_overtime_base' => $request->boolean('is_overtime_base'),
                ],
            );

            return back()->with('success', 'Komponen ditambahkan ke master');
        }

        $master->components()->where('payroll_component_id', $data['payroll_component_id'])->delete();

        return back()->with('success', 'Komponen dilepas dari master');
    }

    /**
     * Attach this Master Gaji to the given employees (BPR manual: master gaji
     * "ditempelkan ke data pegawai").
     */
    public function assign(Request $request, SalaryMaster $master): RedirectResponse
    {
        $this->ensureCanManage($request);
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

        return $request->validate([
            'code' => ['required', 'string', 'max:255', $code],
            'category' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
            'period_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'cut_off_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'day_divisor' => ['nullable', 'integer', 'min:1', 'max:31'],
            'overtime_period' => ['nullable', Rule::in(['berjalan', 'bulan_lalu'])],
            'attendance_period' => ['nullable', Rule::in(['berjalan', 'bulan_lalu'])],
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
            'period_day' => $data['period_day'] ?? null,
            'cut_off_day' => $data['cut_off_day'] ?? null,
            'day_divisor' => $data['day_divisor'] ?? null,
            'overtime_period' => $data['overtime_period'] ?? null,
            'attendance_period' => $data['attendance_period'] ?? null,
        ];
    }

    private function ensureOwnership(Request $request, int|string|null $tenantId): void
    {
        abort_if((int) $tenantId !== (int) $request->user()->tenant_id, 404);
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

<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\OvertimePolicy;
use App\Models\OvertimeRate;
use App\Models\PayrollComponent;
use App\Models\SalaryMasterComponent;
use App\Models\User;
use App\Support\OvertimeRules;
use App\Support\SalaryCompliance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Setup Lembur": the rules behind the overtime line on a payslip.
 *
 * Three of them, in the order the documentation sets them up — which components
 * form the basis (read from each Master Gaji's Komponen Overtime checklist),
 * the multiplier table per kind of day, and the hour ceilings. Everything here
 * is read by the payroll run, so a change on this screen moves a payslip.
 */
class OvertimeRuleController extends Controller
{
    /**
     * The permission module that gates this controller's action-level checks.
     */
    private const MODULE = 'payroll';

    public function index(Request $request): Response
    {
        $this->ensureCan($request, 'view');

        $tenantId = (int) $request->user()->tenant_id;

        OvertimeRules::forget();
        OvertimeRules::ensureRatesFor($tenantId);

        $policy = OvertimeRules::policyFor($tenantId);

        return Inertia::render('avana/payroll-lembur/index', [
            'policy' => [
                'max_hours_per_day' => (float) $policy->max_hours_per_day,
                'max_hours_per_week' => (float) $policy->max_hours_per_week,
                'hours_divisor' => (int) $policy->hours_divisor,
                'fixed_basis_min_ratio' => (float) $policy->fixed_basis_min_ratio,
                'enforce_hour_limits' => (bool) $policy->enforce_hour_limits,
            ],
            'rates' => OvertimeRate::forTenant($tenantId)
                // Workday first, as the design tabulates it — alphabetical
                // order would put "holiday" above it.
                ->orderByRaw("day_type = 'workday' DESC")
                ->orderBy('day_type')
                ->orderBy('hour_from')
                ->get()
                ->map(fn (OvertimeRate $rate): array => [
                    'id' => $rate->id,
                    'day_type' => $rate->day_type,
                    'day_type_label' => OvertimeRules::DAY_TYPES[OvertimeRules::normaliseDayType($rate->day_type)],
                    'hour_from' => (int) $rate->hour_from,
                    'hour_to' => $rate->hour_to !== null ? (int) $rate->hour_to : null,
                    'multiplier' => (float) $rate->multiplier,
                ])->values(),
            'dayTypes' => OvertimeRules::dayTypeOptions(),
            'basis' => $this->basisComponents($tenantId),
            'example' => $this->workedExample($tenantId, $policy),
        ]);
    }

    /**
     * Save the hour ceilings, the hourly divisor and the basis floor.
     */
    public function updatePolicy(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'max_hours_per_day' => ['required', 'numeric', 'min:0', 'max:24'],
            'max_hours_per_week' => ['required', 'numeric', 'min:0', 'max:168'],
            'hours_divisor' => ['required', 'integer', 'min:1', 'max:744'],
            'fixed_basis_min_ratio' => ['required', 'numeric', 'min:0', 'max:1'],
            'enforce_hour_limits' => ['required', 'boolean'],
        ]);

        OvertimeRules::policyFor($tenantId)->update($data);
        OvertimeRules::forget();

        return back()->with('success', 'Aturan lembur disimpan');
    }

    /**
     * Add or replace one band of the multiplier table.
     */
    public function storeRate(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $tenantId = (int) $request->user()->tenant_id;

        $data = $request->validate([
            'day_type' => ['required', Rule::in(array_keys(OvertimeRules::DAY_TYPES))],
            'hour_from' => ['required', 'integer', 'min:1', 'max:24'],
            'hour_to' => ['nullable', 'integer', 'min:1', 'max:24'],
            'multiplier' => ['required', 'numeric', 'min:0', 'max:99'],
        ]);

        if ($data['hour_to'] !== null && $data['hour_to'] < $data['hour_from']) {
            throw ValidationException::withMessages([
                'hour_to' => 'Jam akhir tidak boleh lebih kecil dari jam awal.',
            ]);
        }

        OvertimeRate::updateOrCreate(
            ['tenant_id' => $tenantId, 'day_type' => $data['day_type'], 'hour_from' => $data['hour_from']],
            ['hour_to' => $data['hour_to'], 'multiplier' => $data['multiplier']],
        );

        OvertimeRules::forget();

        return back()->with('success', 'Baris pengali disimpan');
    }

    public function destroyRate(Request $request, OvertimeRate $rate): RedirectResponse
    {
        $this->ensureCan($request, 'archive');
        abort_if((int) $rate->tenant_id !== (int) $request->user()->tenant_id, 404);

        $rate->delete();
        OvertimeRules::forget();

        return back()->with('success', 'Baris pengali dihapus');
    }

    /**
     * Put the statutory PP 35/2021 table back, discarding local edits.
     */
    public function resetRates(Request $request): RedirectResponse
    {
        $this->ensureCan($request, 'update');

        $tenantId = (int) $request->user()->tenant_id;

        OvertimeRate::forTenant($tenantId)->delete();

        foreach (OvertimeRules::statutoryRates() as $dayType => $bands) {
            foreach ($bands as $band) {
                OvertimeRate::create([
                    'tenant_id' => $tenantId,
                    'day_type' => $dayType,
                    'hour_from' => $band['from'],
                    'hour_to' => $band['to'],
                    'multiplier' => $band['multiplier'],
                ]);
            }
        }

        OvertimeRules::forget();

        return back()->with('success', 'Tabel pengali dikembalikan ke PP 35/2021');
    }

    /**
     * Tick or untick a component as a fixed allowance — the "Tetap" mark the
     * overtime basis is summed from.
     */
    public function setBasisComponent(Request $request, PayrollComponent $component): RedirectResponse
    {
        $this->ensureCan($request, 'update');
        abort_if((int) $component->tenant_id !== (int) $request->user()->tenant_id, 404);

        $data = $request->validate(['is_fixed' => ['required', 'boolean']]);

        // Gaji Pokok always counts; PP 35/2021 gives no way to leave it out.
        if ($component->code === 'BASIC' && ! $data['is_fixed']) {
            throw ValidationException::withMessages([
                'is_fixed' => 'Gaji Pokok selalu ikut basis lembur dan tidak bisa dilepas.',
            ]);
        }

        $component->update(['is_fixed' => $data['is_fixed']]);

        return back()->with('success', $data['is_fixed']
            ? $component->name.' ditandai Tetap'
            : $component->name.' dilepas dari basis lembur');
    }

    /**
     * The basis checklist: every earning that could form the overtime rate,
     * with the ones marked "Tetap" ticked. Gaji Pokok is pinned — PP 35/2021
     * builds the rate from it plus the fixed allowances, so it can never be
     * unticked.
     *
     * @return list<array{id: int, code: string|null, name: string, is_fixed: bool, locked: bool, variable: bool}>
     */
    private function basisComponents(int $tenantId): array
    {
        return PayrollComponent::forTenant($tenantId)
            ->where(fn ($query) => $query->whereNull('status')->orWhere('status', 'active'))
            ->where(fn ($query) => $query->whereNull('type')->orWhere('type', '!=', 'deduction'))
            ->where(fn ($query) => $query->whereNull('component_group')->orWhere('component_group', '!=', 'potongan'))
            ->where('code', '!=', 'LEMBUR')
            ->orderByRaw("code = 'BASIC' DESC")
            ->orderBy('name')
            ->get(['id', 'code', 'name', 'calc_basis', 'is_fixed'])
            ->map(fn (PayrollComponent $component): array => [
                'id' => $component->id,
                'code' => $component->code,
                'name' => $component->name,
                'is_fixed' => $component->code === 'BASIC' || (bool) $component->is_fixed,
                'locked' => $component->code === 'BASIC',
                // Paid per present day or per overtime hour, so it is not a
                // fixed allowance however it is ticked.
                'variable' => in_array($component->calc_basis, ['per_present_day', 'per_overtime_hour'], true),
            ])
            ->values()
            ->all();
    }

    /**
     * The worked example the design puts under the multiplier table, computed
     * from a real employee rather than typed in — so what the screen promises
     * and what a payslip pays are the same number by construction.
     *
     * @return array<string, mixed>|null
     */
    private function workedExample(int $tenantId, OvertimePolicy $policy): ?array
    {
        // The employee the setup documentation works its example from, falling
        // back to the first on the books when a tenant has no such record.
        $employee = Employee::forTenant($tenantId)
            ->where('status', 'active')
            ->orderByRaw("employee_number = 'EMP-0001' DESC")
            ->orderBy('employee_number')
            ->first();

        if ($employee === null) {
            return null;
        }

        $wage = SalaryCompliance::monthlyWage($employee, $tenantId);
        $fixed = $this->fixedWageOf($employee, $tenantId);
        $resolved = OvertimeRules::basisFor($fixed, $wage['total'], (float) $policy->fixed_basis_min_ratio);

        $divisor = max(1, (int) $policy->hours_divisor);
        // Whole rupiah before the multipliers, exactly as the payroll run does.
        $hourly = round($resolved['basis'] / $divisor);
        $rates = OvertimeRules::ratesFor($tenantId);

        $firstHour = $hourly * OvertimeRules::multiplierFor($rates, 'workday', 1.0);
        $threeHours = $hourly * OvertimeRules::multiplierFor($rates, 'workday', 3.0);

        return [
            'employee' => $employee->full_name,
            'basis' => round($resolved['basis']),
            'basis_floored' => $resolved['floored'],
            'divisor' => $divisor,
            'hourly' => round($hourly),
            'first_hour' => round($firstHour),
            'later_hours' => round($threeHours - $firstHour),
            'total' => round($threeHours),
        ];
    }

    /**
     * An employee's monthly earnings from the components marked "Tetap".
     */
    private function fixedWageOf(Employee $employee, int $tenantId): float
    {
        $fixedIds = PayrollComponent::forTenant($tenantId)
            ->where('is_fixed', true)
            ->pluck('id')
            ->all();

        $total = (float) EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->effectiveOn()
            ->whereIn('payroll_component_id', $fixedIds)
            ->sum('amount');

        $handled = EmployeeSalaryComponent::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->effectiveOn()
            ->pluck('payroll_component_id')
            ->all();

        if ($employee->salary_master_id !== null) {
            $total += (float) SalaryMasterComponent::query()
                ->where('salary_master_id', $employee->salary_master_id)
                ->where('included', true)
                ->whereIn('payroll_component_id', $fixedIds)
                ->whereNotIn('payroll_component_id', $handled)
                ->sum('amount');
        }

        return $total;
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

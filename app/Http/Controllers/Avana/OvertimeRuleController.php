<?php

namespace App\Http\Controllers\Avana;

use App\Http\Controllers\Controller;
use App\Models\OvertimeRate;
use App\Models\PayrollComponent;
use App\Models\SalaryMaster;
use App\Models\SalaryMasterComponent;
use App\Models\User;
use App\Support\OvertimeRules;
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
            'basis' => $this->basisPerMaster($tenantId),
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
     * What each Master Gaji currently counts as the overtime basis, so the rule
     * screen shows the basis alongside the multipliers rather than sending the
     * reader to another page to find out.
     *
     * @return list<array{id: int, code: string, category: string|null, components: list<string>, total: float}>
     */
    private function basisPerMaster(int $tenantId): array
    {
        $components = PayrollComponent::forTenant($tenantId)->get(['id', 'name'])->keyBy('id');

        return SalaryMaster::forTenant($tenantId)
            ->orderBy('code')
            ->get(['id', 'code', 'category'])
            ->map(function (SalaryMaster $master) use ($components): array {
                $rows = SalaryMasterComponent::query()
                    ->where('salary_master_id', $master->id)
                    ->where('is_overtime_base', true)
                    ->get(['payroll_component_id', 'amount']);

                return [
                    'id' => $master->id,
                    'code' => $master->code,
                    'category' => $master->category,
                    'components' => $rows
                        ->map(fn ($row): string => (string) ($components[$row->payroll_component_id]->name ?? '—'))
                        ->values()
                        ->all(),
                    'total' => (float) $rows->sum('amount'),
                ];
            })
            ->values()
            ->all();
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

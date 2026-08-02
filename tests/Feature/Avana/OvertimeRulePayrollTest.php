<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Employee;
use App\Models\EmployeeSalaryComponent;
use App\Models\OvertimeRequest;
use App\Models\Payday;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Tenant;
use App\Models\User;
use App\Support\OvertimeRules;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    OvertimeRules::forget();

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    // Clear the demo's own overtime so each case controls what payroll reads.
    OvertimeRequest::forTenant($this->tenant->id)->where('employee_id', $this->employee->id)->delete();

    Route::middleware('web')->prefix('spec-overtime')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
    });
});

/** Give the employee a fixed monthly component, optionally as overtime basis. */
function overtimeBasisComponent(Employee $employee, string $code, float $amount, bool $isBasis): PayrollComponent
{
    $component = PayrollComponent::forTenant($employee->tenant_id)->where('code', $code)->firstOrFail();
    $component->update(['calc_basis' => null, 'basis_type' => null]);

    giveMasterComponent($employee, $component, $amount, ['is_overtime_base' => $isBasis]);

    EmployeeSalaryComponent::where('employee_id', $employee->id)
        ->where('payroll_component_id', $component->id)
        ->delete();

    return $component;
}

/** File an approved overtime record inside the period. */
function approvedOvertime(object $ctx, float $hours, string $dayType = 'workday'): OvertimeRequest
{
    return OvertimeRequest::create([
        'tenant_id' => $ctx->tenant->id,
        'employee_id' => $ctx->employee->id,
        'branch_id' => $ctx->employee->branch_id,
        'date' => $ctx->period->start_date->copy()->addDay()->toDateString(),
        'day_type' => $dayType,
        'hours' => $hours,
        'status' => 'approved',
    ]);
}

/** Run payroll and return this employee's item. */
function overtimeRunItem(object $ctx): PayrollRunItem
{
    actingAs($ctx->admin)->post('spec-overtime/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)
        ->where('payroll_period_id', $ctx->period->id)
        ->latest('id')
        ->firstOrFail();

    return PayrollRunItem::where('payroll_run_id', $run->id)
        ->where('employee_id', $ctx->employee->id)
        ->firstOrFail();
}

/** The Rupiah value of the "Lembur" earning line. */
function overtimeLine(PayrollRunItem $item): float
{
    $line = collect($item->calculation_snapshot['earnings'])->firstWhere('name', 'Lembur');

    return $line === null ? 0.0 : (float) $line['amount'];
}

it('builds the overtime basis from every component marked as overtime basis', function (): void {
    overtimeBasisComponent($this->employee, 'BASIC', 10_000_000, true);
    overtimeBasisComponent($this->employee, 'TJ-JAB', 1_500_000, true);
    overtimeBasisComponent($this->employee, 'TJ-TRP', 850_000, true);
    // Deliberately outside the basis.
    overtimeBasisComponent($this->employee, 'TJ-MKN', 1_000_000, false);

    approvedOvertime($this, 3.0);

    $item = overtimeRunItem($this);
    $snapshot = $item->calculation_snapshot['overtime'];

    // Basis 12.350.000 — the worked example of the setup documentation.
    expect((float) $snapshot['basis'])->toBe(12_350_000.0);
    expect($snapshot['basis_floored'])->toBeFalse();
    expect(overtimeLine($item))->toBe(round(12_350_000 / 173 * 5.5));
});

it('pays the basic wage alone when no component is marked as overtime basis', function (): void {
    overtimeBasisComponent($this->employee, 'BASIC', 10_000_000, false);
    overtimeBasisComponent($this->employee, 'TJ-JAB', 1_500_000, false);
    overtimeBasisComponent($this->employee, 'TJ-TRP', 0, false);
    overtimeBasisComponent($this->employee, 'TJ-MKN', 0, false);

    approvedOvertime($this, 1.0);

    $item = overtimeRunItem($this);

    expect((float) $item->calculation_snapshot['overtime']['basis'])->toBe(10_000_000.0);
});

it('lifts the basis to 75% of monthly earnings when the fixed part falls short', function (): void {
    // Only the 4jt basic counts as basis, out of 20jt total earnings — 20%.
    overtimeBasisComponent($this->employee, 'BASIC', 4_000_000, true);
    overtimeBasisComponent($this->employee, 'TJ-JAB', 16_000_000, false);
    overtimeBasisComponent($this->employee, 'TJ-TRP', 0, false);
    overtimeBasisComponent($this->employee, 'TJ-MKN', 0, false);

    approvedOvertime($this, 1.0);

    $item = overtimeRunItem($this);
    $snapshot = $item->calculation_snapshot['overtime'];

    expect((float) $snapshot['basis'])->toBe(15_000_000.0);
    expect($snapshot['basis_floored'])->toBeTrue();
});

it('pays a rest day at the holiday multipliers, not the workday ones', function (): void {
    overtimeBasisComponent($this->employee, 'BASIC', 8_650_000, true);
    overtimeBasisComponent($this->employee, 'TJ-JAB', 0, false);
    overtimeBasisComponent($this->employee, 'TJ-TRP', 0, false);
    overtimeBasisComponent($this->employee, 'TJ-MKN', 0, false);

    approvedOvertime($this, 3.0, 'holiday');

    $item = overtimeRunItem($this);
    $hourly = 8_650_000 / 173;

    // 3 hours on a rest day = 2x each, versus 1,5x + 2x + 2x on a workday.
    expect(overtimeLine($item))->toBe(round($hourly * 6.0));
});

it('honours a tenant edit to the hourly divisor', function (): void {
    overtimeBasisComponent($this->employee, 'BASIC', 8_650_000, true);
    overtimeBasisComponent($this->employee, 'TJ-JAB', 0, false);
    overtimeBasisComponent($this->employee, 'TJ-TRP', 0, false);
    overtimeBasisComponent($this->employee, 'TJ-MKN', 0, false);

    OvertimeRules::policyFor((int) $this->tenant->id)->update(['hours_divisor' => 100]);
    OvertimeRules::forget();

    approvedOvertime($this, 1.0);

    $item = overtimeRunItem($this);

    expect(overtimeLine($item))->toBe(round(8_650_000 / 100 * 1.5));
});

it('records the payday group cut-off and pay date on the payslip', function (): void {
    $payday = Payday::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PD-SPEC',
        'name' => 'Kantor Pusat & Staff',
        'pay_mode' => 'date',
        'pay_day' => 25,
        'cut_off_start_day' => 21,
        'cut_off_end_day' => 20,
        'is_active' => true,
    ]);

    $this->employee->update(['payday_id' => $payday->id]);

    $item = overtimeRunItem($this);
    $snapshot = $item->calculation_snapshot['payday'];

    expect($snapshot['name'])->toBe('Kantor Pusat & Staff');
    expect($snapshot['pay_label'])->toBe('Tanggal 25');
    expect($snapshot['cut_off'])->toBe('21 – 20 bulan berjalan');
    // The window opens in the previous month because 21 > 20.
    expect($snapshot['window'][0])->toBe(
        $this->period->end_date->copy()->subMonthNoOverflow()->day(21)->toDateString(),
    );
    expect($snapshot['pay_date'])->toBe($this->period->end_date->copy()->day(25)->toDateString());
});

it('pays an end-of-month payday group on the last day of the month', function (): void {
    $payday = Payday::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PD-OPS',
        'name' => 'Warehouse & Operasional',
        'pay_mode' => 'end_of_month',
        'pay_day' => null,
        'is_active' => true,
    ]);

    $this->employee->update(['payday_id' => $payday->id]);

    $item = overtimeRunItem($this);
    $snapshot = $item->calculation_snapshot['payday'];

    expect($snapshot['pay_label'])->toBe('Akhir bulan');
    expect($snapshot['pay_date'])->toBe($this->period->end_date->copy()->endOfMonth()->toDateString());
});

it('leaves the payslip payday snapshot empty when the employee is unmapped', function (): void {
    $this->employee->update(['payday_id' => null]);

    expect(overtimeRunItem($this)->calculation_snapshot['payday'])->toBeNull();
});

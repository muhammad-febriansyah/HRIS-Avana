<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\OvertimeRequest;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-calc')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
    });
});

/** Set a component's calculation basis and the employee's Master Gaji nominal. */
function configureComponent(Employee $employee, string $code, string $basis, float $amount): PayrollComponent
{
    $component = PayrollComponent::forTenant($employee->tenant_id)->where('code', $code)->firstOrFail();
    $component->update(['calc_basis' => $basis]);

    giveMasterComponent($employee, $component, $amount);

    return $component;
}

/** Seed N present attendance days for an employee inside the period. */
function seedPresentDays(int $tenantId, Employee $employee, PayrollPeriod $period, int $days): void
{
    $date = $period->start_date->copy();
    for ($i = 0; $i < $days; $i++) {
        $attendance = Attendance::forTenant($tenantId)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $date->toDateString())
            ->first();

        ($attendance ?? new Attendance([
            'tenant_id' => $tenantId,
            'employee_id' => $employee->id,
            'date' => $date->toDateString(),
        ]))->fill([
            'branch_id' => $employee->branch_id,
            'status' => 'present',
        ])->save();

        $date = $date->addDay();
    }
}

/** Run payroll and return the computed item for the employee. */
function runAndItem(object $ctx): PayrollRunItem
{
    actingAs($ctx->admin)->post('spec-calc/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)->where('payroll_period_id', $ctx->period->id)->latest('id')->firstOrFail();

    return PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $ctx->employee->id)->firstOrFail();
}

it('scales a per_present_day component by the present day count', function (): void {
    configureComponent($this->employee, 'TJ-MKN', 'per_present_day', 25_000);
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 10);

    $item = runAndItem($this);
    $earnings = collect($item->calculation_snapshot['earnings']);
    $presentDays = (int) $item->calculation_snapshot['present_days'];

    expect($presentDays)->toBeGreaterThanOrEqual(10);
    expect((float) $earnings->firstWhere('name', 'Tunjangan Makan')['amount'])->toBe(25_000.0 * $presentDays);
});

it('scales a per_overtime_hour component by approved overtime hours', function (): void {
    configureComponent($this->employee, 'TJ-TRP', 'per_overtime_hour', 30_000);

    OvertimeRequest::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'date' => $this->period->start_date->copy()->addDay()->toDateString(),
        'hours' => 5,
        'status' => 'approved',
    ]);

    $item = runAndItem($this);
    $earnings = collect($item->calculation_snapshot['earnings']);

    expect((float) $earnings->firstWhere('name', 'Tunjangan Transport')['amount'])->toBe(150_000.0);
    expect((float) $item->calculation_snapshot['overtime_hours'])->toBe(5.0);
});

it('ignores unapproved overtime in the per_overtime_hour calculation', function (): void {
    configureComponent($this->employee, 'TJ-TRP', 'per_overtime_hour', 30_000);

    OvertimeRequest::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'date' => $this->period->start_date->copy()->addDay()->toDateString(),
        'hours' => 5,
        'status' => 'pending',
    ]);

    $item = runAndItem($this);

    expect((float) $item->calculation_snapshot['overtime_hours'])->toBe(0.0);
});

it('deducts internal BPJS computed from the registered wage', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_000_000);

    EmployeeBpjsProfile::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'registered_wage' => 5_000_000,
        'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
        'jp_enabled' => true, 'kesehatan_enabled' => true,
        'effective_start_date' => '2026-01-01',
    ]);

    $item = runAndItem($this);

    // KESEHATAN 1% + JHT 2% + JP 1% of 5.000.000 = 200.000. JKK and JKM are
    // wholly employer-paid, so they add nothing to the employee's side.
    expect((float) $item->bpjs_employee_total)->toBe(200_000.0);
    expect((float) $item->bpjs_company_total)->toBeGreaterThan(0.0);
    expect(collect($item->calculation_snapshot['deductions'])->firstWhere('name', 'BPJS (Karyawan)'))->not->toBeNull();
});

it('computes internal PPh 21 with the monthly TER scheme (PMK 168/2023)', function (): void {
    // Gross 5.800.000, TK/0 → TER Kategori A. 5.800.000 falls in the
    // 5.650.001–5.950.000 bracket = 0,5% → 29.000/bln.
    configureComponent($this->employee, 'BASIC', 'fixed', 5_800_000);

    $item = runAndItem($this);

    expect((float) $item->gross_salary)->toBe(5_800_000.0);
    expect((float) $item->pph21_total)->toBe(29_000.0);
    expect($item->calculation_snapshot['tax']['method'])->toBe('ter_bulanan');
    expect($item->calculation_snapshot['tax']['ter_category'])->toBe('A');
    expect((float) $item->calculation_snapshot['tax']['ter_rate'])->toBe(0.005);
});

it('accumulates total tax on the run', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_800_000);

    actingAs($this->admin)->post('spec-calc/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($this->tenant->id)->where('payroll_period_id', $this->period->id)->latest('id')->firstOrFail();

    expect((float) $run->total_tax)->toBeGreaterThan(0.0);
});

it('counts the company BPJS Kesehatan premium as the employee income it is', function (): void {
    // PMK 168: the company's Kesehatan (and JKK/JKM) premium is a benefit the
    // employee receives, so it belongs in the bruto the TER rate is applied to.
    // JHT and JP company shares are deferred and must stay out of it.
    configureComponent($this->employee, 'BASIC', 'fixed', 5_800_000);

    EmployeeBpjsProfile::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'registered_wage' => 5_800_000,
        'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
        'jp_enabled' => true, 'kesehatan_enabled' => true,
        'effective_start_date' => '2026-01-01',
    ]);

    $item = runAndItem($this);

    // Company Kesehatan 4% + JKK 0,24% + JKM 0,30% of 5.800.000 = 263.320 →
    // bruto 6.063.320, which is a bracket up: 0,75% instead of 0,5%.
    expect((float) $item->taxable_gross)->toBe(6_063_320.0);
    expect((float) $item->calculation_snapshot['tax']['ter_rate'])->toBe(0.0075);
    expect((float) $item->pph21_total)->toBe(45_475.0);

    // The payslip gross is untouched — the premium is not paid to the employee.
    expect((float) $item->gross_salary)->toBe(5_800_000.0);
});

it('keeps the employee JHT and JP aside for the year-end deduction', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_800_000);

    EmployeeBpjsProfile::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'registered_wage' => 5_800_000,
        'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
        'jp_enabled' => true, 'kesehatan_enabled' => true,
        'effective_start_date' => '2026-01-01',
    ]);

    $item = runAndItem($this);

    // Employee JHT 2% + JP 1% of 5.800.000 = 174.000. Recorded, but NOT taken
    // off the monthly TER base — TER is charged on bruto.
    expect((float) $item->tax_deductible_premium)->toBe(174_000.0);
    expect((float) $item->taxable_gross)->toBe(6_063_320.0);
});

it('warns when a payroll run had to fall back to TK/0', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_800_000);

    // The demo tenant leaves most employees without a tax profile, and TK/0 is
    // the strictest category — a run that guessed it should say so.
    actingAs($this->admin)
        ->post('spec-calc/payroll/run')
        ->assertSessionHas('success')
        ->assertSessionHas('warning');

    expect(session('warning'))->toContain('status PTKP');
});

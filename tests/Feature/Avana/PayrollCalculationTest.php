<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Attendance;
use App\Models\BpjsProgram;
use App\Models\BpjsRate;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\EmployeeBpjsProgram;
use App\Models\OvertimeRequest;
use App\Models\Payday;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\SalaryMaster;
use App\Models\TaxProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SalaryMasterAssignment;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

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

/**
 * Push the period's end past today so its attendance cut-off is still open.
 * Moving the period forward, rather than travelling back, keeps every stored
 * timestamp older than the run — the staleness gate reads those.
 */
function openAttendanceWindow(object $ctx): void
{
    $ctx->period->update(['end_date' => Carbon::now()->addDays(5)->toDateString()]);
    $ctx->period->refresh();
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

it('counts one present day only once when attendance has multiple shift rows', function (): void {
    configureComponent($this->employee, 'TJ-MKN', 'per_present_day', 25_000);
    $date = $this->period->start_date->toDateString();

    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'date' => $date,
        'status' => 'present',
    ]);
    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'date' => $date,
        'status' => 'late',
    ]);

    $snapshot = runAndItem($this)->calculation_snapshot;

    expect((int) $snapshot['present_days'])->toBe(1);
    expect((float) collect($snapshot['earnings'])->firstWhere('name', 'Tunjangan Makan')['amount'])
        ->toBe(25_000.0);
});

it('does not prorate an attendance rate a second time', function (): void {
    $component = configureComponent($this->employee, 'TJ-MKN', 'per_present_day', 25_000);
    $master = SalaryMaster::findOrFail($this->employee->salary_master_id);
    $master->update(['day_calc_method' => 'absen', 'day_divisor' => 22]);
    $master->components()
        ->where('payroll_component_id', $component->id)
        ->update(['is_prorate' => true]);
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 10);

    $snapshot = runAndItem($this)->calculation_snapshot;

    expect((int) $snapshot['present_days'])->toBe(10);
    expect((float) collect($snapshot['earnings'])->firstWhere('name', 'Tunjangan Makan')['amount'])
        ->toBe(250_000.0);
});

it('holds a per_present_day component back while the attendance cut-off is still open', function (): void {
    configureComponent($this->employee, 'TJ-MKN', 'per_present_day', 25_000);
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 10);

    // The window still has days to run, so more attendance is coming and a
    // month's allowance cannot be totalled yet.
    openAttendanceWindow($this);

    $snapshot = runAndItem($this)->calculation_snapshot;

    expect(collect($snapshot['earnings'])->firstWhere('name', 'Tunjangan Makan'))->toBeNull();
    expect($snapshot['attendance_cut_off']['settled'])->toBeFalse();
    expect($snapshot['attendance_cut_off']['end'])->toBe($this->period->end_date->toDateString());
    expect($snapshot['attendance_cut_off']['deferred'])->toBe(['Tunjangan Makan']);
});

it('pays the per_present_day component on the run made after the cut-off', function (): void {
    configureComponent($this->employee, 'TJ-MKN', 'per_present_day', 25_000);
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 10);

    $snapshot = runAndItem($this)->calculation_snapshot;
    $presentDays = (int) $snapshot['present_days'];

    expect($snapshot['attendance_cut_off']['settled'])->toBeTrue();
    expect($snapshot['attendance_cut_off']['deferred'])->toBe([]);
    expect((float) collect($snapshot['earnings'])->firstWhere('name', 'Tunjangan Makan')['amount'])
        ->toBe(25_000.0 * $presentDays);
});

it('stamps the payslip release date at H-1 before the employee payday group pays', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_000_000);

    // The group's own pay date, not the period's — two groups in one period are
    // paid on different days, so the period cannot answer for both.
    $payday = Payday::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PD-SPEC',
        'name' => 'Spec',
        'pay_mode' => 'date',
        'pay_day' => 25,
        'cut_off_start_day' => 21,
        'cut_off_end_day' => 20,
        'is_active' => true,
    ]);
    $this->employee->update(['payday_id' => $payday->id]);
    $this->period->update(['pay_date' => '2026-06-10']);

    $item = runAndItem($this);

    // Payday group pays 25 Jun; the slip is handed over on the 24th.
    expect($item->released_at?->toDateString())->toBe('2026-06-24');
});

it('falls back to the period pay date when the employee has no payday group', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_000_000);

    $this->employee->update(['payday_id' => null]);
    $this->period->update(['pay_date' => '2026-06-25']);

    expect(runAndItem($this)->released_at?->toDateString())->toBe('2026-06-24');
});

it('leaves the release date empty when no pay date is known', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_000_000);

    $this->employee->update(['payday_id' => null]);
    $this->period->update(['pay_date' => null]);

    // Nothing to wait for, so locking alone hands the slip over.
    expect(runAndItem($this)->released_at)->toBeNull();
});

it('refuses to approve or lock a period whose attendance allowance is still waiting', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_000_000);
    configureComponent($this->employee, 'TJ-MKN', 'per_present_day', 25_000);
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 10);

    Route::middleware('web')->prefix('spec-calc')->group(function (): void {
        Route::post('payroll/approve', [PayrollController::class, 'approve']);
        Route::post('payroll/lock', [PayrollController::class, 'lock']);
    });

    openAttendanceWindow($this);

    actingAs($this->admin)->post('spec-calc/payroll/run')->assertSessionHas('success');
    actingAs($this->admin)->post('spec-calc/payroll/approve')->assertSessionHasErrors('payroll');
    expect(session('errors')->first('payroll'))->toContain('belum dapat dihitung sebelum cut-off kehadiran');

    // Keep lock as a second line of defence for an older run that may already
    // have been approved before this approval gate existed.
    PayrollRun::forTenant($this->tenant->id)
        ->where('payroll_period_id', $this->period->id)
        ->current()
        ->update(['status' => PayrollRun::STATUS_APPROVED]);
    actingAs($this->admin)->post('spec-calc/payroll/lock')->assertSessionHasErrors('payroll');
    expect(session('errors')->first('payroll'))->toContain('cut-off kehadiran');

    // Merely waiting is insufficient: the stale snapshot must still be rerun.
    $this->travelTo($this->period->end_date->copy()->addDay());
    actingAs($this->admin)->post('spec-calc/payroll/approve')->assertSessionHasErrors('payroll');
    expect(session('errors')->first('payroll'))->toContain('sudah lewat');

    // Past the cut-off the recompute generates it and the period closes.
    actingAs($this->admin)->post('spec-calc/payroll/run')->assertSessionHas('success');
    actingAs($this->admin)->post('spec-calc/payroll/approve')->assertSessionHas('success');
    actingAs($this->admin)->post('spec-calc/payroll/lock')->assertSessionHas('success');
});

it('says on the sample slip why the attendance allowance is not there yet', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_000_000);
    configureComponent($this->employee, 'TJ-MKN', 'per_present_day', 25_000);
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 10);

    openAttendanceWindow($this);

    Route::middleware('web')->get('spec-calc/payroll', [PayrollController::class, 'index']);

    actingAs($this->admin)
        ->get('spec-calc/payroll?slip_employee='.$this->employee->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('slip.notice', fn (?string $notice): bool => $notice !== null
                && str_contains($notice, 'Tunjangan Makan')
                && str_contains($notice, 'cut-off')));
});

it('tells the payroll page which cut-off the preview is still waiting on', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_000_000);
    configureComponent($this->employee, 'TJ-MKN', 'per_present_day', 25_000);
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 10);

    Route::middleware('web')->get('spec-calc/payroll', [PayrollController::class, 'index']);

    openAttendanceWindow($this);
    actingAs($this->admin)->post('spec-calc/payroll/run')->assertSessionHas('success');

    actingAs($this->admin)
        ->get('spec-calc/payroll')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pending_attendance_cut_off', $this->period->end_date->toDateString()));

    // Recomputed past the cut-off there is nothing left to wait for, so the
    // banner disappears rather than nagging about a settled run.
    $this->travelTo($this->period->end_date->copy()->addDay());
    actingAs($this->admin)->post('spec-calc/payroll/run')->assertSessionHas('success');

    actingAs($this->admin)
        ->get('spec-calc/payroll')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('pending_attendance_cut_off', null));
});

it('uses the assigned per-day rate snapshot after the master rate changes', function (): void {
    $component = configureComponent($this->employee, 'TJ-MKN', 'per_present_day', 25_000);
    $master = SalaryMaster::forTenant($this->tenant->id)->findOrFail($this->employee->salary_master_id);

    SalaryMasterAssignment::apply(
        (int) $this->tenant->id,
        $master,
        collect([$this->employee]),
        Carbon::parse($this->period->start_date),
        actorId: (int) $this->admin->id,
    );

    $master->components()->where('payroll_component_id', $component->id)->update(['amount' => 100_000]);
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 10);

    $item = runAndItem($this);
    $earnings = collect($item->calculation_snapshot['earnings']);
    $presentDays = (int) $item->calculation_snapshot['present_days'];

    expect((float) $earnings->firstWhere('name', 'Tunjangan Makan')['amount'])
        ->toBe(25_000.0 * $presentDays);
});

it('scales a per_overtime_hour component by approved overtime hours', function (): void {
    configureComponent($this->employee, 'TJ-TRP', 'per_overtime_hour', 30_000);

    $overtime = OvertimeRequest::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'date' => $this->period->start_date->copy()->addDay()->toDateString(),
        'hours' => 5,
        'status' => 'approved',
    ]);

    seedOvertimeAttendance($overtime, 5);

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
    // Deducted per programme so a payslip reconciles line by line.
    $deductions = collect($item->calculation_snapshot['deductions']);
    expect((float) $deductions->firstWhere('name', 'BPJS Kesehatan (Karyawan)')['amount'])->toBe(50_000.0);
    expect((float) $deductions->firstWhere('name', 'JHT (Karyawan)')['amount'])->toBe(100_000.0);
    expect((float) $deductions->firstWhere('name', 'JP (Karyawan)')['amount'])->toBe(50_000.0);
    expect($deductions->firstWhere('name', 'BPJS (Karyawan)'))->toBeNull();
});

it('skips every BPJS contribution for a non-participant employee', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_000_000);

    EmployeeBpjsProfile::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'participation_status' => 'not_participant',
        'registered_wage' => 5_000_000,
    ]);

    $item = runAndItem($this);

    expect((float) $item->bpjs_employee_total)->toBe(0.0)
        ->and((float) $item->bpjs_company_total)->toBe(0.0)
        ->and($item->calculation_snapshot['bpjs']['programs'] ?? [])->toBe([]);
});

it('calculates only the tenant programs selected for an employee', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_000_000);

    $jkp = BpjsProgram::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'JKP-SPEC',
        'name' => 'BPJS JKP Spec',
        'type' => 'jkp',
        'is_active' => true,
    ]);
    $jkp->rates()->create([
        'employee_rate' => 0.005,
        'company_rate' => 0.002,
        'effective_start_date' => '2026-01-01',
        'is_active' => true,
    ]);

    $profile = EmployeeBpjsProfile::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'participation_status' => 'participant',
        'registered_wage' => 5_000_000,
    ]);
    EmployeeBpjsProgram::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'program_id' => $jkp->id,
        'is_active' => true,
        'effective_start_date' => '2026-01-01',
    ]);

    $item = runAndItem($this);
    $programs = $item->calculation_snapshot['bpjs']['programs'] ?? [];

    expect($profile->participation_status)->toBe('participant')
        ->and(array_keys($programs))->toBe(['jkp-spec'])
        ->and((float) $programs['jkp-spec']['employee'])->toBe(25_000.0)
        ->and((float) $item->bpjs_employee_total)->toBe(25_000.0);
});

it('omits JP contributions when the tenant disables JP', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_000_000);

    EmployeeBpjsProfile::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'registered_wage' => 5_000_000,
        'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
        'jp_enabled' => true, 'kesehatan_enabled' => true,
        'effective_start_date' => '2026-01-01',
    ]);
    $this->tenant->update(['bpjs_jp_enabled' => false]);

    $item = runAndItem($this);
    $deductions = collect($item->calculation_snapshot['deductions']);

    expect((float) $item->bpjs_employee_total)->toBe(150_000.0)
        ->and($deductions->firstWhere('name', 'JP (Karyawan)'))->toBeNull()
        ->and($item->calculation_snapshot['bpjs']['programs']['jp'] ?? null)->toBeNull();
});

it('stops the BPJS premium at each programme wage ceiling', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 14_500_000);

    // The ceilings the client's payroll workbook uses: Perpres 82/2018 for
    // Kesehatan, and the BPJS TK circular in force for Jaminan Pensiun.
    BpjsRate::whereHas('program', fn ($query) => $query->where('code', 'KESEHATAN'))
        ->update(['max_wage' => 12_000_000]);
    BpjsRate::whereHas('program', fn ($query) => $query->where('code', 'JP'))
        ->update(['max_wage' => 11_086_300]);

    EmployeeBpjsProfile::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'registered_wage' => 14_500_000,
        'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
        'jp_enabled' => true, 'kesehatan_enabled' => true,
        'effective_start_date' => '2026-01-01',
    ]);

    $item = runAndItem($this);
    $deductions = collect($item->calculation_snapshot['deductions']);

    // Kesehatan and JP are capped; JHT has no ceiling, so it follows the wage.
    expect((float) $deductions->firstWhere('name', 'BPJS Kesehatan (Karyawan)')['amount'])->toBe(120_000.0);
    expect((float) $deductions->firstWhere('name', 'JHT (Karyawan)')['amount'])->toBe(290_000.0);
    expect((float) $deductions->firstWhere('name', 'JP (Karyawan)')['amount'])->toBe(110_863.0);
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

it('stops a payroll run when somebody has no PTKP status', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_800_000);

    // A missing PTKP status used to be guessed as TK/0 — the strictest
    // category, and the wrong tax quietly charged. The run now refuses until
    // the profile is filled in.
    TaxProfile::where('tenant_id', $this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->update(['ptkp_status' => null]);

    actingAs($this->admin)
        ->post('spec-calc/payroll/run')
        ->assertSessionHasErrors('payroll');

    expect(session('errors')->first('payroll'))->toContain('status PTKP');
});

it('shows the tax basis and TER rate on the sample payslip', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_800_000);

    Route::middleware('web')->get('spec-calc/payroll', [PayrollController::class, 'index']);

    actingAs($this->admin)
        ->get('spec-calc/payroll?slip_employee='.$this->employee->id)
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('slip.tax_info.0.k', 'Bruto Pajak')
            ->where('slip.tax_info.1.k', 'Tarif TER')
            ->where('slip.tax_info.1.v', '0,5% · Kategori A'));
});

it('keeps the company BPJS premium out of the TER base when the tenant switches it off', function (): void {
    configureComponent($this->employee, 'BASIC', 'fixed', 5_800_000);

    EmployeeBpjsProfile::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'registered_wage' => 5_800_000,
        'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
        'jp_enabled' => true, 'kesehatan_enabled' => true,
        'effective_start_date' => '2026-01-01',
    ]);

    $this->tenant->update(['tax_includes_employer_bpjs' => false]);

    $item = runAndItem($this);

    // Bruto pajak is the pay alone: 5.800.000 → TER A 0,5% → 29.000.
    expect((float) $item->taxable_gross)->toBe(5_800_000.0);
    expect((float) $item->pph21_total)->toBe(29_000.0);
    expect((float) $item->bpjs_company_total)->toBeGreaterThan(0.0);
});

it('adds the company BPJS premium to the TER base by default (PMK 168/2023)', function (): void {
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

    expect((float) $item->taxable_gross)->toBeGreaterThan(5_800_000.0);
});

<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\TaxProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pph21Ter;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    Pph21Ter::forget();

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    // A known PTKP status, so the expected tax is arithmetic rather than
    // whatever the demo data happened to hand this employee.
    TaxProfile::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id],
        ['ptkp_status' => 'TK/0', 'tax_method' => 'gross', 'tax_subject' => 'pegawai_tetap', 'effective_start_date' => '2026-01-01'],
    );

    Route::middleware('web')->prefix('spec-recon')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
    });
});

/**
 * Give the employee a flat monthly wage of exactly this much.
 */
function fixedWage(Employee $employee, float $amount): void
{
    $basic = PayrollComponent::forTenant($employee->tenant_id)->where('code', 'BASIC')->firstOrFail();
    $basic->update(['calc_basis' => 'fixed']);
    giveMasterComponent($employee, $basic, $amount);
}

function periodFor(Tenant $tenant, string $code, string $start, string $end, string $cycle = 'monthly', string $status = 'draft'): PayrollPeriod
{
    return PayrollPeriod::create([
        'tenant_id' => $tenant->id,
        'code' => $code,
        'name' => $code,
        'cycle' => $cycle,
        'start_date' => $start,
        'end_date' => $end,
        'status' => $status,
    ]);
}

/**
 * A payslip already issued for an earlier period, under a run of the given
 * status.
 */
function priorPayslip(Tenant $tenant, Employee $employee, PayrollPeriod $period, float $gross, float $withheld, string $runStatus): PayrollRunItem
{
    $run = PayrollRun::create([
        'tenant_id' => $tenant->id,
        'payroll_period_id' => $period->id,
        'status' => $runStatus,
    ]);

    return PayrollRunItem::create([
        'tenant_id' => $tenant->id,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'gross_salary' => $gross,
        'taxable_gross' => $gross,
        'tax_deductible_premium' => 0,
        'total_allowance' => 0,
        'total_deduction' => $withheld,
        'bpjs_employee_total' => 0,
        'bpjs_company_total' => 0,
        'pph21_total' => $withheld,
        'net_salary' => $gross - $withheld,
        'status' => $runStatus,
    ]);
}

function runPayrollFor(User $admin, PayrollPeriod $period): PayrollRunItem
{
    actingAs($admin)
        ->post('spec-recon/payroll/run', ['payroll_period_id' => $period->id])
        ->assertSessionHas('success');

    return PayrollRunItem::where('payroll_period_id', $period->id)
        ->whereHas('run', fn ($query) => $query->where('status', 'calculated'))
        ->where('employee_id', test()->employee->id)
        ->latest('id')
        ->firstOrFail();
}

it('pays back the excess when the year withheld more than it owed', function (): void {
    $jan = periodFor($this->tenant, 'MN-2026-01', '2026-01-01', '2026-01-31', 'monthly', 'locked');
    // 100jt earned, 30jt withheld — far more than the year turns out to owe.
    priorPayslip($this->tenant, $this->employee, $jan, 100_000_000, 30_000_000, PayrollRun::STATUS_LOCKED);

    $dec = periodFor($this->tenant, 'MN-2026-12', '2026-12-01', '2026-12-31');
    fixedWage($this->employee, 20_000_000);

    $item = runPayrollFor($this->admin, $dec);
    $tax = $item->calculation_snapshot['tax'];

    // Annual gross 120jt − biaya jabatan 6jt − PTKP 54jt = PKP 60jt → 3jt tax,
    // against 30jt already withheld: 27jt goes back to the employee.
    expect($tax['method'])->toBe('annual_reconciliation');
    expect((float) $tax['annual_tax'])->toBe(3_000_000.0);
    expect((float) $tax['ytd_withheld'])->toBe(30_000_000.0);
    expect((float) $tax['tax_refund'])->toBe(27_000_000.0);
    expect((float) $item->pph21_total)->toBe(-27_000_000.0);

    // It shows on the payslip, and lifts the net rather than vanishing.
    $refund = collect($item->calculation_snapshot['deductions'])
        ->firstWhere('name', 'Pengembalian PPh 21 (lebih potong)');

    expect($refund)->not->toBeNull();
    expect((float) $refund['amount'])->toBe(-27_000_000.0);
    expect((float) $item->net_salary)->toBe((float) $item->gross_salary + 27_000_000.0);
});

it('shows the refund on the payroll breakdown as money coming back', function (): void {
    $jan = periodFor($this->tenant, 'MN-2026-01', '2026-01-01', '2026-01-31', 'monthly', 'locked');
    priorPayslip($this->tenant, $this->employee, $jan, 100_000_000, 30_000_000, PayrollRun::STATUS_LOCKED);

    $dec = periodFor($this->tenant, 'MN-2026-12', '2026-12-01', '2026-12-31');
    fixedWage($this->employee, 20_000_000);
    runPayrollFor($this->admin, $dec);

    actingAs($this->admin)
        ->get(route('avana.payroll', ['period' => $dec->id, 'slip_employee' => $this->employee->id]))
        ->assertOk()
        ->assertInertia(function (AssertableInertia $page): void {
            $slip = $page->toArray()['props']['slip'];
            $refund = collect($slip['earnings'])->firstWhere('k', 'Pengembalian PPh 21 (lebih potong)');

            // On the earning side, positive, with the reconciliation explained.
            expect($refund)->not->toBeNull();
            expect($refund['v'])->toContain('27.000.000');
            expect($refund['why'])->toContain('dikembalikan pada slip ini');
            expect(collect($slip['deductions'])->pluck('k'))->not->toContain('Pengembalian PPh 21 (lebih potong)');
        });
});

it('counts only finalised runs as tax already withheld', function (): void {
    $jan = periodFor($this->tenant, 'MN-2026-01', '2026-01-01', '2026-01-31', 'monthly', 'draft');
    // Still being calculated: neither its gross nor its tax may count.
    priorPayslip($this->tenant, $this->employee, $jan, 100_000_000, 30_000_000, PayrollRun::STATUS_CALCULATED);

    $dec = periodFor($this->tenant, 'MN-2026-12', '2026-12-01', '2026-12-31');
    fixedWage($this->employee, 20_000_000);

    $tax = runPayrollFor($this->admin, $dec)->calculation_snapshot['tax'];

    expect((float) $tax['ytd_withheld'])->toBe(0.0);
    expect((float) $tax['annual_gross'])->toBe(20_000_000.0);
});

it('does not count a re-run period twice in the year to date', function (): void {
    $jan = periodFor($this->tenant, 'MN-2026-01', '2026-01-01', '2026-01-31', 'monthly', 'locked');
    // The same month run twice: an earlier approved run and the locked one that
    // superseded it. Only the latest may count.
    priorPayslip($this->tenant, $this->employee, $jan, 100_000_000, 30_000_000, PayrollRun::STATUS_APPROVED);
    priorPayslip($this->tenant, $this->employee, $jan, 100_000_000, 30_000_000, PayrollRun::STATUS_LOCKED);

    $dec = periodFor($this->tenant, 'MN-2026-12', '2026-12-01', '2026-12-31');
    fixedWage($this->employee, 20_000_000);

    $tax = runPayrollFor($this->admin, $dec)->calculation_snapshot['tax'];

    expect((float) $tax['annual_gross'])->toBe(120_000_000.0);
    expect((float) $tax['ytd_withheld'])->toBe(30_000_000.0);
});

it('charges a weekly run on the calendar month, not on its own slice', function (): void {
    $week1 = periodFor($this->tenant, 'WK-2026-03-01', '2026-03-01', '2026-03-07', 'weekly', 'locked');
    priorPayslip($this->tenant, $this->employee, $week1, 10_000_000, 200_000, PayrollRun::STATUS_LOCKED);

    $week2 = periodFor($this->tenant, 'WK-2026-03-02', '2026-03-08', '2026-03-14', 'weekly');
    fixedWage($this->employee, 10_000_000);

    $item = runPayrollFor($this->admin, $week2);
    $tax = $item->calculation_snapshot['tax'];

    // TER is a rate on the month's bruto: this run is taxed on the month to
    // date and credited with what the month already withheld.
    expect($tax['method'])->toBe('ter_bulanan');
    expect((float) $tax['month_gross'])->toBe((float) $tax['period_gross'] + 10_000_000.0);
    expect((float) $tax['month_withheld_before'])->toBe(200_000.0);
    expect((float) $item->pph21_total)
        ->toBe(round((float) $tax['month_gross'] * (float) $tax['ter_rate']) - 200_000.0);
});

it('reconciles only on the last weekly period of December', function (): void {
    fixedWage($this->employee, 10_000_000);

    $early = periodFor($this->tenant, 'WK-2026-12-01', '2026-12-01', '2026-12-07', 'weekly');
    $last = periodFor($this->tenant, 'WK-2026-12-05', '2026-12-25', '2026-12-31', 'weekly');

    // A period ending inside December is still an ordinary masa pajak: another
    // one comes after it.
    expect(runPayrollFor($this->admin, $early)->calculation_snapshot['tax']['method'])->toBe('ter_bulanan');
    // The one that closes the year reconciles.
    expect(runPayrollFor($this->admin, $last)->calculation_snapshot['tax']['method'])->toBe('annual_reconciliation');
});

it('stops the run when a PTKP status is not mapped to a TER category', function (): void {
    fixedWage($this->employee, 10_000_000);

    TaxProfile::where('tenant_id', $this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->update(['ptkp_status' => null]);

    $period = periodFor($this->tenant, 'MN-2026-09', '2026-09-01', '2026-09-30');

    actingAs($this->admin)
        ->post('spec-recon/payroll/run', ['payroll_period_id' => $period->id])
        ->assertSessionHasErrors('payroll');

    expect(PayrollRunItem::where('payroll_period_id', $period->id)->count())->toBe(0);
});

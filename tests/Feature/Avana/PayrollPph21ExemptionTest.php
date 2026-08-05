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
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)
        ->whereNotNull('position_id')
        ->orderBy('id')
        ->firstOrFail();

    // The run targets the latest draft period, so park the seeded ones out of
    // the way and let each spec create the period it means to test.
    PayrollPeriod::forTenant($this->tenant->id)->update(['status' => 'locked']);

    Route::middleware('web')->prefix('spec-exempt')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
    });
});

/** Give the employee a fixed basic salary big enough to be taxable. */
function payTheEmployee(Employee $employee, int $tenantId, float $amount): void
{
    $basic = PayrollComponent::forTenant($tenantId)->where('code', 'BASIC')->firstOrFail();
    $basic->update(['calc_basis' => 'fixed']);

    giveMasterComponent($employee, $basic, $amount);
}

/** The run item produced for an employee in the period. */
function runItemFor(PayrollPeriod $period, Employee $employee): PayrollRunItem
{
    return PayrollRunItem::where(
        'payroll_run_id',
        PayrollRun::where('payroll_period_id', $period->id)->latest('id')->value('id'),
    )
        ->where('employee_id', $employee->id)
        ->firstOrFail();
}

it('withholds no PPh 21 for an employee marked exempt', function (): void {
    $period = PayrollPeriod::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MN-2026-03', 'name' => 'Maret 2026',
        'cycle' => 'monthly', 'start_date' => '2026-03-01', 'end_date' => '2026-03-31',
        'status' => 'draft',
    ]);

    payTheEmployee($this->employee, $this->tenant->id, 20_000_000);

    // Baseline: taxed while the profile carries no exemption.
    actingAs($this->admin)->post('spec-exempt/payroll/run')->assertSessionHas('success');
    expect((float) runItemFor($period, $this->employee)->pph21_total)->toBeGreaterThan(0.0);

    TaxProfile::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id],
        [
            'tax_subject' => 'pegawai_tetap',
            'ptkp_status' => 'TK/0',
            'wage_basis' => 'monthly',
            'is_pph21_exempt' => true,
            'pph21_exempt_reason' => 'WNA, dipotong PPh 26',
        ],
    );

    actingAs($this->admin)->post('spec-exempt/payroll/run')->assertSessionHas('success');

    $item = runItemFor($period, $this->employee);

    expect((float) $item->pph21_total)->toBe(0.0);
    expect($item->calculation_snapshot['tax']['method'])->toBe('exempt');
    expect($item->calculation_snapshot['tax']['exempt_reason'])->toBe('WNA, dipotong PPh 26');
});

it('leaves the other employees taxed as usual', function (): void {
    $other = Employee::forTenant($this->tenant->id)
        ->whereNotNull('position_id')
        ->where('id', '!=', $this->employee->id)
        ->orderBy('id')
        ->firstOrFail();

    $period = PayrollPeriod::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MN-2026-04', 'name' => 'April 2026',
        'cycle' => 'monthly', 'start_date' => '2026-04-01', 'end_date' => '2026-04-30',
        'status' => 'draft',
    ]);

    payTheEmployee($this->employee, $this->tenant->id, 20_000_000);
    payTheEmployee($other, $this->tenant->id, 20_000_000);

    TaxProfile::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id],
        ['tax_subject' => 'pegawai_tetap', 'ptkp_status' => 'TK/0', 'wage_basis' => 'monthly', 'is_pph21_exempt' => true],
    );

    actingAs($this->admin)->post('spec-exempt/payroll/run')->assertSessionHas('success');

    expect((float) runItemFor($period, $this->employee)->pph21_total)->toBe(0.0);
    expect((float) runItemFor($period, $other)->pph21_total)->toBeGreaterThan(0.0);
});

it('skips the December reconciliation for an exempt employee', function (): void {
    $jan = PayrollPeriod::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MN-2026-01', 'name' => 'Januari 2026',
        'cycle' => 'monthly', 'start_date' => '2026-01-01', 'end_date' => '2026-01-31',
        'status' => 'locked',
    ]);
    $janRun = PayrollRun::create([
        'tenant_id' => $this->tenant->id, 'payroll_period_id' => $jan->id, 'status' => 'locked',
    ]);
    PayrollRunItem::create([
        'tenant_id' => $this->tenant->id, 'payroll_run_id' => $janRun->id, 'payroll_period_id' => $jan->id,
        'employee_id' => $this->employee->id, 'gross_salary' => 100_000_000,
        'taxable_gross' => 100_000_000, 'tax_deductible_premium' => 0, 'total_allowance' => 0,
        'total_deduction' => 0, 'bpjs_employee_total' => 0, 'bpjs_company_total' => 0,
        'pph21_total' => 0, 'net_salary' => 100_000_000, 'status' => 'locked',
    ]);

    $dec = PayrollPeriod::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MN-2026-12', 'name' => 'Desember 2026',
        'cycle' => 'monthly', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31',
        'status' => 'draft',
    ]);

    payTheEmployee($this->employee, $this->tenant->id, 20_000_000);

    TaxProfile::updateOrCreate(
        ['tenant_id' => $this->tenant->id, 'employee_id' => $this->employee->id],
        ['tax_subject' => 'pegawai_tetap', 'ptkp_status' => 'TK/0', 'wage_basis' => 'monthly', 'is_pph21_exempt' => true],
    );

    actingAs($this->admin)->post('spec-exempt/payroll/run')->assertSessionHas('success');

    $item = runItemFor($dec, $this->employee);

    // Without the exemption this month settles the whole year's tariff.
    expect($item->calculation_snapshot['tax']['method'])->toBe('exempt');
    expect((float) $item->pph21_total)->toBe(0.0);
});

it('saves the exemption from the tax profile screen and clears the reason when switched off', function (): void {
    actingAs($this->admin)
        ->post(route('avana.payroll.konfigurasi.tax-profile.upsert'), [
            'employee_id' => $this->employee->id,
            'tax_subject' => 'pegawai_tetap',
            'ptkp_status' => 'TK/0',
            'wage_basis' => 'monthly',
            'is_pph21_exempt' => true,
            'pph21_exempt_reason' => 'Dipotong kantor pusat',
        ])
        ->assertSessionHasNoErrors();

    $profile = TaxProfile::where('employee_id', $this->employee->id)->firstOrFail();

    expect($profile->is_pph21_exempt)->toBeTrue();
    expect($profile->pph21_exempt_reason)->toBe('Dipotong kantor pusat');

    actingAs($this->admin)
        ->post(route('avana.payroll.konfigurasi.tax-profile.upsert'), [
            'employee_id' => $this->employee->id,
            'tax_subject' => 'pegawai_tetap',
            'ptkp_status' => 'TK/0',
            'wage_basis' => 'monthly',
            'is_pph21_exempt' => false,
        ])
        ->assertSessionHasNoErrors();

    $profile->refresh();

    expect($profile->is_pph21_exempt)->toBeFalse();
    expect($profile->pph21_exempt_reason)->toBeNull();
});

<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Employee;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PositionPayrollComponent;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-tax')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
    });
});

it('reconciles PPh 21 with the progressive tariff in December', function (): void {
    // YTD (prior period): 100.000.000 gross, nothing withheld.
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
        'employee_id' => $this->employee->id, 'gross_salary' => 100_000_000, 'total_allowance' => 0,
        'total_deduction' => 0, 'bpjs_employee_total' => 0, 'bpjs_company_total' => 0,
        'pph21_total' => 0, 'net_salary' => 100_000_000, 'status' => 'locked',
    ]);

    // December period is the latest draft, so the run targets it.
    $dec = PayrollPeriod::create([
        'tenant_id' => $this->tenant->id, 'code' => 'MN-2026-12', 'name' => 'Desember 2026',
        'cycle' => 'monthly', 'start_date' => '2026-12-01', 'end_date' => '2026-12-31',
        'status' => 'draft',
    ]);

    $basic = PayrollComponent::forTenant($this->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $basic->update(['calc_basis' => 'fixed']);
    PositionPayrollComponent::updateOrCreate(
        ['position_id' => $this->employee->position_id, 'payroll_component_id' => $basic->id],
        ['tenant_id' => $this->tenant->id, 'amount' => 20_000_000],
    );

    actingAs($this->admin)->post('spec-tax/payroll/run')->assertSessionHas('success');

    $item = PayrollRunItem::where('payroll_run_id',
        PayrollRun::where('payroll_period_id', $dec->id)->latest('id')->value('id'))
        ->where('employee_id', $this->employee->id)->firstOrFail();

    // Annual gross 120jt − biaya jabatan 6jt − PTKP 54jt (TK/0) = PKP 60jt.
    // Progressive: 60jt @5% = 3.000.000; nothing withheld YTD.
    expect($item->calculation_snapshot['tax']['method'])->toBe('annual_reconciliation');
    expect((float) $item->pph21_total)->toBe(3_000_000.0);
    expect((float) $item->calculation_snapshot['tax']['pkp'])->toBe(60_000_000.0);
});

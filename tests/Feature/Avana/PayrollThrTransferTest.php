<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Employee;
use App\Models\EmployeeBankAccount;
use App\Models\EmployeeSalaryComponent;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\TaxProfile;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Database\Seeders\AvanaPayrollDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // Self-contained routes mirroring the production payroll actions so the THR
    // and bank transfer endpoints can be exercised on collision-free paths.
    Route::middleware('web')->prefix('spec-avana')->name('spec.')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run'])->name('payroll.run');
        Route::post('payroll/thr', [PayrollController::class, 'thr'])->name('payroll.thr');
        Route::get('payroll/transfer', [PayrollController::class, 'transferFile'])->name('payroll.transfer');
    });
});

/** Attach a fixed BASIC salary component to an employee for a known base. */
function attachBasic(int $tenantId, Employee $employee, float $amount): void
{
    $basic = PayrollComponent::forTenant($tenantId)->where('code', 'BASIC')->firstOrFail();

    EmployeeSalaryComponent::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'payroll_component_id' => $basic->id,
        'amount' => $amount,
    ]);
}

it('creates a THR period and run with one item per active employee', function (): void {
    $tenantId = $this->tenant->id;
    $activeCount = Employee::forTenant($tenantId)->where('status', 'active')->count();

    actingAs($this->admin)
        ->post('spec-avana/payroll/thr')
        ->assertSessionHas('success');

    $year = (int) now()->year;
    $period = PayrollPeriod::forTenant($tenantId)->where('code', 'THR-'.$year)->firstOrFail();
    $run = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->firstOrFail();

    expect($period->name)->toBe('THR '.$year);
    expect($period->status)->toBe('draft');
    expect($run->status)->toBe('calculated');
    expect((int) $run->employee_count)->toBe($activeCount);
    expect(PayrollRunItem::where('payroll_run_id', $run->id)->count())->toBe($activeCount);
});

it('does not recalculate a locked THR run', function (): void {
    $tenantId = $this->tenant->id;

    actingAs($this->admin)->post('spec-avana/payroll/thr')->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($tenantId)->where('code', 'THR-'.now()->year)->firstOrFail();
    $run = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->firstOrFail();
    $itemSnapshot = PayrollRunItem::where('payroll_run_id', $run->id)
        ->orderBy('id')
        ->get()
        ->map->only(['id', 'gross_salary', 'total_deduction', 'net_salary', 'calculation_snapshot'])
        ->all();

    $period->update(['status' => 'locked']);
    $run->update(['status' => 'locked']);

    actingAs($this->admin)
        ->post('spec-avana/payroll/thr')
        ->assertSessionHasErrors('payroll');

    expect($period->fresh()->status)->toBe('locked')
        ->and($run->fresh()->status)->toBe('locked')
        ->and(PayrollRunItem::where('payroll_run_id', $run->id)
            ->orderBy('id')
            ->get()
            ->map->only(['id', 'gross_salary', 'total_deduction', 'net_salary', 'calculation_snapshot'])
            ->all())->toBe($itemSnapshot);
});

it('removes an employee who is no longer eligible when THR is recalculated', function (): void {
    $tenantId = $this->tenant->id;
    $employee = Employee::forTenant($tenantId)->where('status', 'active')->orderBy('id')->firstOrFail();

    actingAs($this->admin)->post('spec-avana/payroll/thr')->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($tenantId)->where('code', 'THR-'.now()->year)->firstOrFail();
    $run = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->firstOrFail();

    expect(PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->exists())->toBeTrue();

    $employee->update(['status' => 'inactive', 'resign_date' => now()->subDays(31)->toDateString()]);

    actingAs($this->admin)->post('spec-avana/payroll/thr')->assertSessionHas('success');

    expect(PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->exists())->toBeFalse()
        ->and((int) $run->fresh()->employee_count)->toBe(
            PayrollRunItem::where('payroll_run_id', $run->id)->count(),
        );
});

it('pays a full month base to an employee with at least a year of tenure', function (): void {
    $tenantId = $this->tenant->id;

    $senior = Employee::forTenant($tenantId)->where('status', 'active')->whereNotNull('position_id')->orderBy('id')->firstOrFail();
    $senior->update(['join_date' => now()->subYears(3)->toDateString()]);

    attachBasic($tenantId, $senior, 6_000_000);

    actingAs($this->admin)->post('spec-avana/payroll/thr')->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($tenantId)->where('code', 'THR-'.now()->year)->firstOrFail();
    $run = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->firstOrFail();
    $item = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $senior->id)->firstOrFail();

    expect((int) $item->calculation_snapshot['months_worked'])->toBeGreaterThanOrEqual(12);
    expect((float) $item->gross_salary)->toBe(6_000_000.0);
    // THR is taxable in the month it is paid, so the take-home is the THR less
    // the withholding, not the THR itself.
    expect((float) $item->net_salary)
        ->toBe(6_000_000.0 - (float) $item->pph21_total);
});

it('withholds PPh 21 on THR at the bracket the combined income reaches', function (): void {
    // PMK 168 treats THR as penghasilan tidak teratur: it joins the month's
    // regular bruto, and the tax on it is what the combined amount costs minus
    // what the salary alone would have.
    $tenantId = $this->tenant->id;

    $employee = Employee::forTenant($tenantId)->where('status', 'active')->whereNotNull('position_id')->orderBy('id')->firstOrFail();
    $employee->update(['join_date' => now()->subYears(3)->toDateString()]);

    TaxProfile::updateOrCreate(
        ['tenant_id' => $tenantId, 'employee_id' => $employee->id],
        ['tax_subject' => 'pegawai_tetap', 'ptkp_status' => 'TK/0', 'wage_basis' => 'monthly'],
    );

    attachBasic($tenantId, $employee, 6_000_000);

    actingAs($this->admin)->post('spec-avana/payroll/thr')->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($tenantId)->where('code', 'THR-'.now()->year)->firstOrFail();
    $run = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->firstOrFail();
    $item = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->firstOrFail();

    $tax = $item->calculation_snapshot['tax'];

    expect($tax['method'])->toBe('ter_bulanan_thr')
        ->and($tax['ter_category'])->toBe('A')
        // Combined sits in a higher bracket than the salary on its own.
        ->and((float) $tax['ter_rate_combined'])->toBeGreaterThan((float) $tax['ter_rate_regular'])
        ->and((float) $item->pph21_total)->toBeGreaterThan(0.0);

    // The withholding is the difference between the two, not a rate on the THR.
    $expected = round(
        (float) $tax['combined_gross'] * (float) $tax['ter_rate_combined']
        - (float) $tax['regular_gross'] * (float) $tax['ter_rate_regular'],
    );
    expect((float) $item->pph21_total)->toBe((float) $expected);

    // December has to see this month too, so the THR counts as its own taxable
    // gross rather than hiding inside the payslip figure.
    expect((float) $item->taxable_gross)->toBe(6_000_000.0);
    expect((float) $run->total_tax)->toBeGreaterThan(0.0);
});

it('leaves THR untaxed for a subject that never withholds via TER', function (): void {
    $tenantId = $this->tenant->id;

    $employee = Employee::forTenant($tenantId)->where('status', 'active')->whereNotNull('position_id')->orderBy('id')->firstOrFail();
    $employee->update(['join_date' => now()->subYears(3)->toDateString()]);

    TaxProfile::updateOrCreate(
        ['tenant_id' => $tenantId, 'employee_id' => $employee->id],
        ['tax_subject' => 'bukan_pegawai', 'ptkp_status' => 'TK/0', 'wage_basis' => 'monthly'],
    );

    attachBasic($tenantId, $employee, 6_000_000);

    actingAs($this->admin)->post('spec-avana/payroll/thr')->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($tenantId)->where('code', 'THR-'.now()->year)->firstOrFail();
    $run = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->firstOrFail();
    $item = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $employee->id)->firstOrFail();

    expect((float) $item->pph21_total)->toBe(0.0);
});

it('prorates THR for an employee with less than a year of tenure', function (): void {
    $tenantId = $this->tenant->id;

    $employees = Employee::forTenant($tenantId)->where('status', 'active')->whereNotNull('position_id')->orderBy('id')->take(2)->get();
    $senior = $employees[0];
    $junior = $employees[1];

    $senior->update(['join_date' => now()->subYears(3)->toDateString()]);
    $junior->update(['join_date' => now()->subMonths(4)->subDays(2)->toDateString()]);

    attachBasic($tenantId, $senior, 6_000_000);
    attachBasic($tenantId, $junior, 6_000_000);

    actingAs($this->admin)->post('spec-avana/payroll/thr')->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($tenantId)->where('code', 'THR-'.now()->year)->firstOrFail();
    $run = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->firstOrFail();

    $seniorItem = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $senior->id)->firstOrFail();
    $juniorItem = PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $junior->id)->firstOrFail();

    $months = (int) $juniorItem->calculation_snapshot['months_worked'];
    $expected = round(6_000_000 * min(1.0, $months / 12));

    expect($months)->toBeLessThan(12);
    expect((float) $juniorItem->gross_salary)->toBe((float) $expected);
    expect((float) $juniorItem->gross_salary)->toBeLessThan((float) $seniorItem->gross_salary);
    expect((float) $juniorItem->gross_salary)->toBeGreaterThan(0.0);
});

it('streams a bank transfer CSV for the latest regular run', function (): void {
    $tenantId = $this->tenant->id;
    $this->seed(AvanaPayrollDemoSeeder::class);

    $employee = Employee::forTenant($tenantId)->where('status', 'active')->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    EmployeeBankAccount::create([
        'tenant_id' => $tenantId,
        'employee_id' => $employee->id,
        'bank_name' => 'Bank Mandiri',
        'account_number' => '1234567890',
        'account_holder' => $employee->full_name,
        'is_primary' => true,
    ]);

    actingAs($this->admin)->post('spec-avana/payroll/run')->assertSessionHas('success');

    // Bank transfer requires a finalized (locked) run.
    PayrollRun::forTenant($tenantId)
        ->whereHas('period', fn ($query) => $query->where('code', 'not like', 'THR-%'))
        ->latest('id')
        ->firstOrFail()
        ->update(['status' => 'locked']);

    $response = actingAs($this->admin)->get('spec-avana/payroll/transfer');

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/csv');

    $content = $response->streamedContent();

    // fputcsv quotes header fields containing spaces.
    expect($content)->toContain('Nama,Bank,"No Rekening","Atas Nama",Net');
    expect($content)->toContain('Bank Mandiri');
    expect($content)->toContain('1234567890');
    expect($content)->toContain($employee->full_name);
});

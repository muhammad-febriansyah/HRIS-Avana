<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\Loan;
use App\Models\OvertimeRequest;
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
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-resign')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
        Route::post('payroll/lock', [PayrollController::class, 'lock']);
    });
});

/** Set BASIC as a fixed component on the employee's position. */
function setBasic(object $ctx, float $amount): void
{
    $component = PayrollComponent::forTenant($ctx->tenant->id)->where('code', 'BASIC')->firstOrFail();
    $component->update(['calc_basis' => 'fixed']);

    PositionPayrollComponent::updateOrCreate(
        ['position_id' => $ctx->employee->position_id, 'payroll_component_id' => $component->id],
        ['tenant_id' => $ctx->tenant->id, 'amount' => $amount],
    );
}

function runResign(object $ctx): PayrollRunItem
{
    actingAs($ctx->admin)->post('spec-resign/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)->where('payroll_period_id', $ctx->period->id)->latest('id')->firstOrFail();

    return PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $ctx->employee->id)->firstOrFail();
}

it('still pays a resigned employee, prorated to their last working day', function (): void {
    setBasic($this, 6_000_000);

    // Resign mid-period; status stays active until the flag command runs.
    $resign = $this->period->start_date->copy()->addDays(14); // 15th working day
    $this->employee->update(['resign_date' => $resign->toDateString()]);

    $item = runResign($this);

    $calendarDays = $this->period->start_date->diffInDays($this->period->end_date) + 1;
    $factor = min(1.0, 15 / $calendarDays);
    $expected = round(6_000_000 * $factor);

    expect((float) $item->calculation_snapshot['proration_factor'])->toBeLessThan(1.0);
    expect((float) $item->gross_salary)->toBe($expected);
});

it('excludes an employee who resigned before the period started', function (): void {
    setBasic($this, 6_000_000);

    $this->employee->update([
        'status' => 'inactive',
        'resign_date' => $this->period->start_date->copy()->subDay()->toDateString(),
    ]);

    actingAs($this->admin)->post('spec-resign/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($this->tenant->id)->where('payroll_period_id', $this->period->id)->latest('id')->firstOrFail();

    expect(PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $this->employee->id)->exists())->toBeFalse();
});

it('deducts an approved loan installment and advances it on lock', function (): void {
    setBasic($this, 8_000_000);

    $loan = Loan::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'amount' => 5_000_000,
        'tenor_months' => 10,
        'interest_rate' => 0,
        'monthly_installment' => 500_000,
        'paid_installments' => 0,
        'purpose' => 'Test',
        'status' => 'approved',
    ]);

    $item = runResign($this);
    $deductions = collect($item->calculation_snapshot['deductions']);

    expect((float) $deductions->firstWhere('name', 'Cicilan Pinjaman')['amount'])->toBe(500_000.0);

    actingAs($this->admin)->post('spec-resign/payroll/lock')->assertSessionHas('success');

    expect($loan->fresh()->paid_installments)->toBe(1);
});

it('settles a loan once the final installment is paid on lock', function (): void {
    setBasic($this, 8_000_000);

    $loan = Loan::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'amount' => 1_000_000,
        'tenor_months' => 5,
        'interest_rate' => 0,
        'monthly_installment' => 200_000,
        'paid_installments' => 4,
        'purpose' => 'Test',
        'status' => 'approved',
    ]);

    runResign($this);
    actingAs($this->admin)->post('spec-resign/payroll/lock')->assertSessionHas('success');

    expect($loan->fresh()->paid_installments)->toBe(5);
    expect($loan->fresh()->status)->toBe('paid');
});

it('does not deduct a fully paid loan', function (): void {
    setBasic($this, 8_000_000);

    Loan::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'amount' => 1_000_000,
        'tenor_months' => 5,
        'interest_rate' => 0,
        'monthly_installment' => 200_000,
        'paid_installments' => 5,
        'purpose' => 'Test',
        'status' => 'approved',
    ]);

    $item = runResign($this);

    expect(collect($item->calculation_snapshot['deductions'])->firstWhere('name', 'Cicilan Pinjaman'))->toBeNull();
});

it('deducts an approved cash advance installment', function (): void {
    setBasic($this, 8_000_000);

    CashAdvance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'amount' => 900_000,
        'installments' => 3,
        'monthly_deduction' => 300_000,
        'paid_installments' => 0,
        'request_date' => $this->period->start_date->toDateString(),
        'reason' => 'Test',
        'status' => 'approved',
    ]);

    $item = runResign($this);

    expect((float) collect($item->calculation_snapshot['deductions'])->firstWhere('name', 'Potongan Kasbon')['amount'])->toBe(300_000.0);
});

it('adds statutory overtime pay using the Kepmenaker multipliers', function (): void {
    // Hourly wage = 3.460.000 / 173 = 20.000. Two overtime hours in one day
    // = 1,5x20.000 + 2x20.000 = 70.000.
    setBasic($this, 3_460_000);

    OvertimeRequest::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'date' => $this->period->start_date->copy()->addDay()->toDateString(),
        'hours' => 2,
        'status' => 'approved',
    ]);

    $item = runResign($this);
    $overtime = collect($item->calculation_snapshot['earnings'])->firstWhere('name', 'Lembur');

    expect((float) $overtime['amount'])->toBe(70_000.0);
});

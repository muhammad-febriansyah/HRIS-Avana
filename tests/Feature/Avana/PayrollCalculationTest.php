<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Http\Controllers\Avana\PositionComponentController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\EmployeeBpjsProfile;
use App\Models\OvertimeRequest;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\PositionPayrollComponent;
use App\Models\Role;
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

    Route::middleware('web')->prefix('spec-calc')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
        Route::put('payroll/components/basis', [PositionComponentController::class, 'updateBasis']);
    });
});

/** Set a component's calculation basis and the employee position's nominal. */
function configureComponent(int $tenantId, int $positionId, string $code, string $basis, float $amount): PayrollComponent
{
    $component = PayrollComponent::forTenant($tenantId)->where('code', $code)->firstOrFail();
    $component->update(['calc_basis' => $basis]);

    PositionPayrollComponent::updateOrCreate(
        ['position_id' => $positionId, 'payroll_component_id' => $component->id],
        ['tenant_id' => $tenantId, 'amount' => $amount],
    );

    return $component;
}

/** Seed N present attendance days for an employee inside the period. */
function seedPresentDays(int $tenantId, Employee $employee, PayrollPeriod $period, int $days): void
{
    $date = $period->start_date->copy();
    for ($i = 0; $i < $days; $i++) {
        Attendance::firstOrCreate(
            ['tenant_id' => $tenantId, 'employee_id' => $employee->id, 'date' => $date->toDateString()],
            ['branch_id' => $employee->branch_id, 'status' => 'present'],
        );
        $date->addDay();
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
    configureComponent($this->tenant->id, $this->employee->position_id, 'TJ-MKN', 'per_present_day', 25_000);
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 10);

    $item = runAndItem($this);
    $earnings = collect($item->calculation_snapshot['earnings']);
    $presentDays = (int) $item->calculation_snapshot['present_days'];

    expect($presentDays)->toBeGreaterThanOrEqual(10);
    expect((float) $earnings->firstWhere('name', 'Tunjangan Makan')['amount'])->toBe(25_000.0 * $presentDays);
});

it('scales a per_overtime_hour component by approved overtime hours', function (): void {
    configureComponent($this->tenant->id, $this->employee->position_id, 'TJ-TRP', 'per_overtime_hour', 30_000);

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
    configureComponent($this->tenant->id, $this->employee->position_id, 'TJ-TRP', 'per_overtime_hour', 30_000);

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
    configureComponent($this->tenant->id, $this->employee->position_id, 'BASIC', 'fixed', 5_000_000);

    EmployeeBpjsProfile::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'registered_wage' => 5_000_000,
        'jht_enabled' => true, 'jkk_enabled' => true, 'jkm_enabled' => true,
        'jp_enabled' => true, 'kesehatan_enabled' => true,
        'effective_start_date' => '2026-01-01',
    ]);

    $item = runAndItem($this);

    // KESEHATAN 1% + JHT 2% + JP 1% of 5.000.000 = 200.000 (JKK/JKM rates not seeded).
    expect((float) $item->bpjs_employee_total)->toBe(200_000.0);
    expect((float) $item->bpjs_company_total)->toBeGreaterThan(0.0);
    expect(collect($item->calculation_snapshot['deductions'])->firstWhere('name', 'BPJS (Karyawan)'))->not->toBeNull();
});

it('computes internal PPh 21 from the matching TER bracket', function (): void {
    // Gross 5.800.000 falls in TER bracket A 5.650.000–5.950.000 @ 0,5%.
    configureComponent($this->tenant->id, $this->employee->position_id, 'BASIC', 'fixed', 5_800_000);

    $item = runAndItem($this);

    expect((float) $item->gross_salary)->toBe(5_800_000.0);
    expect((float) $item->pph21_total)->toBe(29_000.0);
    expect($item->calculation_snapshot['tax']['ter_category'])->toBe('A');
});

it('accumulates total tax on the run', function (): void {
    configureComponent($this->tenant->id, $this->employee->position_id, 'BASIC', 'fixed', 5_800_000);

    actingAs($this->admin)->post('spec-calc/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($this->tenant->id)->where('payroll_period_id', $this->period->id)->latest('id')->firstOrFail();

    expect((float) $run->total_tax)->toBeGreaterThan(0.0);
});

it('updates a payroll component calculation basis', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-MKN')->firstOrFail();

    actingAs($this->admin)
        ->put('spec-calc/payroll/components/basis', [
            'payroll_component_id' => $component->id,
            'calc_basis' => 'per_present_day',
        ])
        ->assertSessionHas('success');

    expect($component->fresh()->calc_basis)->toBe('per_present_day');
});

it('rejects an invalid calculation basis', function (): void {
    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-MKN')->firstOrFail();

    actingAs($this->admin)
        ->put('spec-calc/payroll/components/basis', [
            'payroll_component_id' => $component->id,
            'calc_basis' => 'per_lunar_eclipse',
        ])
        ->assertSessionHasErrors('calc_basis');
});

it('forbids a plain employee from changing a component basis', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    $component = PayrollComponent::forTenant($this->tenant->id)->where('code', 'TJ-MKN')->firstOrFail();

    actingAs($staff)
        ->put('spec-calc/payroll/components/basis', [
            'payroll_component_id' => $component->id,
            'calc_basis' => 'per_present_day',
        ])
        ->assertForbidden();
});

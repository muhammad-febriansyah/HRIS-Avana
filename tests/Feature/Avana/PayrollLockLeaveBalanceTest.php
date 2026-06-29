<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
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

    // Self-contained routes mirroring the production payroll actions so the
    // lock/run flow can be exercised in isolation (see PayrollControllerTest).
    Route::middleware('web')->prefix('spec-avana')->name('spec.')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run'])->name('payroll.run');
        Route::post('payroll/lock', [PayrollController::class, 'lock'])->name('payroll.lock');
    });
});

it('locks the latest payroll period and its run', function (): void {
    $tenantId = $this->tenant->id;

    actingAs($this->admin)
        ->post('spec-avana/payroll/lock')
        ->assertSessionHas('success');

    $period = PayrollPeriod::forTenant($tenantId)->orderByDesc('start_date')->firstOrFail();
    $run = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->orderByDesc('id')->firstOrFail();

    expect($period->status)->toBe('locked');
    expect($run->status)->toBe('locked');
});

it('does not recompute a locked period on run', function (): void {
    $tenantId = $this->tenant->id;

    actingAs($this->admin)->post('spec-avana/payroll/lock')->assertSessionHas('success');

    actingAs($this->admin)
        ->post('spec-avana/payroll/run')
        ->assertSessionHasErrors('payroll');

    $period = PayrollPeriod::forTenant($tenantId)->orderByDesc('start_date')->firstOrFail();
    $run = PayrollRun::forTenant($tenantId)->where('payroll_period_id', $period->id)->orderByDesc('id')->firstOrFail();

    // Both stay locked; the run was not refreshed back to 'calculated'.
    expect($period->status)->toBe('locked');
    expect($run->status)->toBe('locked');
});

it('rejects a leave request that exceeds the remaining balance', function (): void {
    $tenantId = $this->tenant->id;
    $employee = Employee::forTenant($tenantId)->firstOrFail();
    $leaveType = LeaveType::forTenant($tenantId)->where('code', 'PENTING')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-14', // 5 days requested, quota is 2
            'reason' => 'Melebihi saldo',
        ])
        ->assertSessionHasErrors('leave_type_id');

    expect(LeaveRequest::where('employee_id', $employee->id)
        ->where('reason', 'Melebihi saldo')
        ->exists())->toBeFalse();
});

it('creates a leave request within the remaining balance', function (): void {
    $tenantId = $this->tenant->id;
    $employee = Employee::forTenant($tenantId)->firstOrFail();
    $leaveType = LeaveType::forTenant($tenantId)->where('code', 'TAHUNAN')->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-12', // 3 days, quota is 12
            'reason' => 'Dalam saldo',
        ])
        ->assertRedirect(route('avana.cuti'))
        ->assertSessionHas('success');

    expect(LeaveRequest::where('employee_id', $employee->id)
        ->where('reason', 'Dalam saldo')
        ->exists())->toBeTrue();
});

it('allows an over-balance request when the leave type permits negative balances', function (): void {
    $tenantId = $this->tenant->id;
    $employee = Employee::forTenant($tenantId)->firstOrFail();
    $leaveType = LeaveType::forTenant($tenantId)->where('code', 'PENTING')->firstOrFail();
    $leaveType->update(['allow_negative' => true]);

    actingAs($this->admin)
        ->post(route('avana.cuti.store'), [
            'employee_id' => $employee->id,
            'leave_type_id' => $leaveType->id,
            'start_date' => '2026-07-10',
            'end_date' => '2026-07-20', // 11 days requested against a quota of 2
            'reason' => 'Boleh minus',
        ])
        ->assertSessionHas('success');

    expect(LeaveRequest::where('employee_id', $employee->id)
        ->where('reason', 'Boleh minus')
        ->exists())->toBeTrue();
});

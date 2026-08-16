<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\OvertimePayableHours;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

/**
 * Overtime is paid on evidence, not on the plan: an approved request says how
 * long somebody meant to stay, attendance says how long they did, and payroll
 * pays the smaller of the two.
 */
beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-ot')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
    });
});

/** An approved 5-hour overtime record on the period's second day. */
function filedOvertime(object $ctx, float $hours = 5): OvertimeRequest
{
    return OvertimeRequest::create([
        'tenant_id' => $ctx->tenant->id,
        'employee_id' => $ctx->employee->id,
        'branch_id' => $ctx->employee->branch_id,
        'date' => $ctx->period->start_date->copy()->addDay()->toDateString(),
        'start_time' => '17:00',
        'hours' => $hours,
        'status' => 'approved',
    ]);
}

/** Pay the flat per-hour component so payable hours show up as rupiah. */
function payPerOvertimeHour(object $ctx, float $rate = 30_000): PayrollComponent
{
    $component = PayrollComponent::forTenant($ctx->tenant->id)->where('code', 'TJ-TRP')->firstOrFail();
    $component->update(['calc_basis' => 'per_overtime_hour']);

    giveMasterComponent($ctx->employee, $component, $rate);

    return $component;
}

/** Run payroll and return this employee's item. */
function overtimeItem(object $ctx): PayrollRunItem
{
    actingAs($ctx->admin)->post('spec-ot/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)->where('payroll_period_id', $ctx->period->id)->latest('id')->firstOrFail();

    return PayrollRunItem::where('payroll_run_id', $run->id)->where('employee_id', $ctx->employee->id)->firstOrFail();
}

it('pays nothing for approved overtime with no attendance on that day', function (): void {
    payPerOvertimeHour($this);
    $overtime = filedOvertime($this, 5);

    $item = overtimeItem($this);

    expect((float) $item->calculation_snapshot['overtime_hours'])->toBe(0.0);

    $audit = collect($item->calculation_snapshot['overtime_records'])->firstWhere('overtime_request_id', $overtime->id);

    expect((float) $audit['requested_hours'])->toBe(5.0)
        ->and($audit['actual_hours'])->toBeNull()
        ->and((float) $audit['payable_hours'])->toBe(0.0)
        ->and($audit['basis'])->toBe(OvertimePayableHours::BASIS_NO_ATTENDANCE);

    expect((float) $overtime->fresh()->payable_hours)->toBe(0.0);
});

it('pays nothing when the employee never clocked out', function (): void {
    payPerOvertimeHour($this);
    $overtime = filedOvertime($this, 5);

    Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'date' => $overtime->date->toDateString(),
        'status' => 'present',
        'clock_in_at' => $overtime->date->copy()->setTime(8, 0),
    ]);

    $item = overtimeItem($this);

    $audit = collect($item->calculation_snapshot['overtime_records'])->firstWhere('overtime_request_id', $overtime->id);

    expect((float) $item->calculation_snapshot['overtime_hours'])->toBe(0.0)
        ->and($audit['basis'])->toBe(OvertimePayableHours::BASIS_NO_CLOCK_OUT);
});

it('pays the actual hours when the employee left earlier than approved', function (): void {
    payPerOvertimeHour($this);
    $overtime = filedOvertime($this, 5);

    // Approved 5 hours from 17:00, clocked out at 19:00 — two hours worked.
    seedOvertimeAttendance($overtime, 2);

    $item = overtimeItem($this);
    $earnings = collect($item->calculation_snapshot['earnings']);
    $audit = collect($item->calculation_snapshot['overtime_records'])->firstWhere('overtime_request_id', $overtime->id);

    expect((float) $item->calculation_snapshot['overtime_hours'])->toBe(2.0)
        ->and((float) $audit['requested_hours'])->toBe(5.0)
        ->and((float) $audit['actual_hours'])->toBe(2.0)
        ->and((float) $audit['payable_hours'])->toBe(2.0)
        ->and($audit['basis'])->toBe(OvertimePayableHours::BASIS_ACTUAL)
        ->and((float) $earnings->firstWhere('name', 'Tunjangan Transport')['amount'])->toBe(60_000.0);
});

it('caps payment at the approved hours when the employee stayed longer', function (): void {
    payPerOvertimeHour($this);
    $overtime = filedOvertime($this, 3);

    // Stayed 6 hours; only the 3 approved are payable.
    seedOvertimeAttendance($overtime, 6);

    $item = overtimeItem($this);
    $earnings = collect($item->calculation_snapshot['earnings']);
    $audit = collect($item->calculation_snapshot['overtime_records'])->firstWhere('overtime_request_id', $overtime->id);

    expect((float) $item->calculation_snapshot['overtime_hours'])->toBe(3.0)
        ->and((float) $audit['actual_hours'])->toBe(6.0)
        ->and((float) $audit['payable_hours'])->toBe(3.0)
        ->and($audit['basis'])->toBe(OvertimePayableHours::BASIS_APPROVED)
        ->and((float) $earnings->firstWhere('name', 'Tunjangan Transport')['amount'])->toBe(90_000.0);
});

it('picks up an attendance correction on the next run, before the period is locked', function (): void {
    payPerOvertimeHour($this);
    $overtime = filedOvertime($this, 5);

    // First run: the clock-out was missed, so nothing is payable.
    $attendance = seedOvertimeAttendance($overtime, 5);
    $attendance->update(['clock_out_at' => null]);

    expect((float) overtimeItem($this)->calculation_snapshot['overtime_hours'])->toBe(0.0);

    // HR corrects the attendance; a re-run pays the corrected hours.
    $attendance->update(['clock_out_at' => $overtime->date->copy()->setTime(21, 0)]);

    $item = overtimeItem($this);

    expect((float) $item->calculation_snapshot['overtime_hours'])->toBe(4.0)
        ->and((float) collect($item->calculation_snapshot['earnings'])->firstWhere('name', 'Tunjangan Transport')['amount'])
        ->toBe(120_000.0);
});

it('refuses a second overtime filing that overlaps one already on the books', function (): void {
    $first = filedOvertime($this, 2);
    $first->update(['end_time' => '19:00']);

    // 17:00–19:00 already filed; 18:00–19:00 would be paid from the same
    // clock-out, so the same hour would be paid twice. Kept under the daily
    // ceiling so the overlap is what is being tested, not the cap.
    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.store'), [
            'employee_id' => $this->employee->id,
            'date' => $first->date->toDateString(),
            'start_time' => '18:00',
            'end_time' => '19:00',
        ])
        ->assertSessionHasErrors('start_time');

    // A stretch that does not overlap is still accepted.
    actingAs($this->admin)
        ->post(route('avana.cuti.lembur.store'), [
            'employee_id' => $this->employee->id,
            'date' => $first->date->toDateString(),
            'start_time' => '19:00',
            'end_time' => '20:00',
        ])
        ->assertSessionHasNoErrors();
});

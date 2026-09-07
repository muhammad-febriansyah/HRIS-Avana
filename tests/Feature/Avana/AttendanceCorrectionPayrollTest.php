<?php

use App\Http\Controllers\Avana\ApprovalController;
use App\Http\Controllers\Avana\PayrollController;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\Payday;
use App\Models\PayrollComponent;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::forTenant($this->tenant->id)->orderByDesc('start_date')->firstOrFail();
    $this->employee = Employee::forTenant($this->tenant->id)->whereNotNull('position_id')->orderBy('id')->firstOrFail();

    // The demo attendance is scenery; these specs count days exactly.
    Attendance::forTenant($this->tenant->id)->where('employee_id', $this->employee->id)->delete();

    Route::middleware('web')->prefix('spec-koreksi')->group(function (): void {
        Route::post('payroll/run', [PayrollController::class, 'run']);
        Route::post('payroll/approve', [PayrollController::class, 'approve']);
        Route::post('payroll/lock', [PayrollController::class, 'lock']);
        Route::post('approval/{type}/{id}/approve', [ApprovalController::class, 'approve']);
        Route::post('approval/{type}/{id}/reject', [ApprovalController::class, 'reject']);
    });
});

/** Put the meal allowance on the employee at a per-attended-day rate. */
function mealAllowancePerDay(Employee $employee, float $rate): PayrollComponent
{
    $component = PayrollComponent::forTenant($employee->tenant_id)->where('code', 'TJ-MKN')->firstOrFail();
    $component->update(['calc_basis' => 'per_present_day']);

    giveMasterComponent($employee, $component, $rate);

    return $component;
}

/**
 * File `$count` corrections for consecutive days starting `$fromOffset` days
 * after the period opens — days the employee has no attendance on at all.
 *
 * @return Collection<int, AttendanceCorrection>
 */
function fileCorrections(object $ctx, int $fromOffset, int $count): Collection
{
    return collect(range(0, $count - 1))->map(fn (int $offset): AttendanceCorrection => AttendanceCorrection::create([
        'tenant_id' => $ctx->tenant->id,
        'employee_id' => $ctx->employee->id,
        'date' => $ctx->period->start_date->copy()->addDays($fromOffset + $offset)->toDateString(),
        'correction_type' => 'manual',
        'requested_clock_in' => '08:00',
        'requested_clock_out' => '17:00',
        'reason' => 'Lupa absen',
        'status' => 'pending',
    ]));
}

/** The employee's computed run item for the period. */
function correctionRunItem(object $ctx): PayrollRunItem
{
    actingAs($ctx->admin)->post('spec-koreksi/payroll/run')->assertSessionHas('success');

    $run = PayrollRun::forTenant($ctx->tenant->id)
        ->where('payroll_period_id', $ctx->period->id)
        ->latest('id')
        ->firstOrFail();

    return PayrollRunItem::where('payroll_run_id', $run->id)
        ->where('employee_id', $ctx->employee->id)
        ->firstOrFail();
}

it('pays only the attendance corrections an approver actually approved', function (): void {
    mealAllowancePerDay($this->employee, 25_000);

    // 18 days clocked normally, plus five days the employee missed entirely and
    // filed a correction for — of which the approver signs off two.
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 18);
    $corrections = fileCorrections($this, 18, 5);

    foreach ($corrections->take(2) as $approved) {
        actingAs($this->admin)
            ->post('spec-koreksi/approval/koreksi/'.$approved->id.'/approve')
            ->assertSessionHas('success');
    }

    foreach ($corrections->skip(2) as $rejected) {
        actingAs($this->admin)
            ->post('spec-koreksi/approval/koreksi/'.$rejected->id.'/reject')
            ->assertSessionHas('success');
    }

    // A rejected correction writes no attendance, so the month is 18 + 2 = 20.
    expect(Attendance::forTenant($this->tenant->id)->where('employee_id', $this->employee->id)->count())->toBe(20);
    expect($corrections->skip(2)->map->fresh()->pluck('status')->all())->toBe(['rejected', 'rejected', 'rejected']);
    expect($corrections->skip(2)->map->fresh()->pluck('attendance_id')->all())->toBe([null, null, null]);

    $snapshot = correctionRunItem($this)->calculation_snapshot;

    expect((int) $snapshot['present_days'])->toBe(20);
    expect((float) collect($snapshot['earnings'])->firstWhere('name', 'Tunjangan Makan')['amount'])
        ->toBe(20 * 25_000.0);
});

it('counts only two approved corrections among twenty existing attendance rows', function (): void {
    mealAllowancePerDay($this->employee, 25_000);
    seedPresentDays($this->tenant->id, $this->employee, $this->period, 20);

    $attendanceNeedingCorrection = Attendance::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->orderByDesc('date')
        ->limit(5)
        ->get();

    $corrections = $attendanceNeedingCorrection->map(function (Attendance $attendance): AttendanceCorrection {
        $attendance->update([
            'clock_in_at' => null,
            'clock_out_at' => null,
            'status' => 'need_correction',
        ]);

        return AttendanceCorrection::create([
            'tenant_id' => $this->tenant->id,
            'attendance_id' => $attendance->id,
            'employee_id' => $this->employee->id,
            'date' => $attendance->date->toDateString(),
            'correction_type' => 'manual',
            'requested_clock_in' => '08:00',
            'requested_clock_out' => '17:00',
            'reason' => 'Data absen perlu diperbaiki',
            'status' => 'pending',
        ]);
    });

    foreach ($corrections->take(2) as $approved) {
        actingAs($this->admin)
            ->post('spec-koreksi/approval/koreksi/'.$approved->id.'/approve')
            ->assertSessionHas('success');
    }

    foreach ($corrections->skip(2) as $rejected) {
        actingAs($this->admin)
            ->post('spec-koreksi/approval/koreksi/'.$rejected->id.'/reject')
            ->assertSessionHas('success');
    }

    $snapshot = correctionRunItem($this)->calculation_snapshot;

    // Fifteen rows were already valid, two were approved, and the other three
    // remain excluded. The submitted correction count never becomes pay by itself.
    expect((int) $snapshot['present_days'])->toBe(17);
    expect((float) collect($snapshot['earnings'])->firstWhere('name', 'Tunjangan Makan')['amount'])
        ->toBe(17 * 25_000.0);
    expect($attendanceNeedingCorrection->skip(2)->map->fresh()->pluck('status')->all())
        ->toBe(['need_correction', 'need_correction', 'need_correction']);
});

it('leaves pay untouched while a correction is still waiting for a decision', function (): void {
    mealAllowancePerDay($this->employee, 25_000);

    seedPresentDays($this->tenant->id, $this->employee, $this->period, 18);
    fileCorrections($this, 18, 5);

    $snapshot = correctionRunItem($this)->calculation_snapshot;

    // A pending request is a claim, not a worked day.
    expect((int) $snapshot['present_days'])->toBe(18);
    expect((float) collect($snapshot['earnings'])->firstWhere('name', 'Tunjangan Makan')['amount'])
        ->toBe(18 * 25_000.0);
});

it('refuses to lock a run computed before a correction was approved', function (): void {
    mealAllowancePerDay($this->employee, 25_000);

    seedPresentDays($this->tenant->id, $this->employee, $this->period, 18);
    $corrections = fileCorrections($this, 18, 5);

    expect((int) correctionRunItem($this)->calculation_snapshot['present_days'])->toBe(18);

    // The approval lands after payroll was computed: the run now pays a day
    // count nobody would recognise, so finalising it has to stop. Staleness is
    // judged on stored timestamps, so the clock has to actually move on.
    $this->travelTo(now()->addMinutes(5));

    actingAs($this->admin)
        ->post('spec-koreksi/approval/koreksi/'.$corrections->first()->id.'/approve')
        ->assertSessionHas('success');

    actingAs($this->admin)->post('spec-koreksi/payroll/approve')->assertSessionHasErrors('payroll');

    // Recomputing takes the correction in, and the run finalises on 19 days.
    expect((int) correctionRunItem($this)->calculation_snapshot['present_days'])->toBe(19);

    actingAs($this->admin)->post('spec-koreksi/payroll/approve')->assertSessionHas('success');
    actingAs($this->admin)->post('spec-koreksi/payroll/lock')->assertSessionHas('success');
});

it('marks payroll stale for an approved correction in a cross-month cut-off window', function (): void {
    mealAllowancePerDay($this->employee, 25_000);

    $payday = Payday::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'PD-LINTAS-BULAN',
        'name' => 'Lintas Bulan',
        'pay_mode' => 'date',
        'pay_day' => 25,
        'cut_off_start_day' => 21,
        'cut_off_end_day' => 20,
        'is_active' => true,
    ]);
    $this->employee->update(['payday_id' => $payday->id]);

    $correctionDate = $this->period->start_date->copy()->subMonthNoOverflow()->day(25);
    $correction = AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'date' => $correctionDate->toDateString(),
        'correction_type' => 'manual',
        'requested_clock_in' => '08:00',
        'requested_clock_out' => '17:00',
        'reason' => 'Lupa absen sebelum awal bulan payroll',
        'status' => 'pending',
    ]);

    $item = correctionRunItem($this);
    expect($item->calculation_snapshot['attendance_cut_off']['start'])->toBe(
        $this->period->start_date->copy()->subMonthNoOverflow()->day(21)->toDateString(),
    );

    $this->travelTo(now()->addMinutes(5));
    actingAs($this->admin)
        ->post('spec-koreksi/approval/koreksi/'.$correction->id.'/approve')
        ->assertSessionHas('success');

    actingAs($this->admin)
        ->post('spec-koreksi/payroll/approve')
        ->assertSessionHasErrors('payroll');
});

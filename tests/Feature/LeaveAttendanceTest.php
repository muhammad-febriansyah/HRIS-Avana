<?php

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\LeaveAttendanceMarker;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->employee = $this->user->employee;

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    // jwt-auth caches the resolved user on the guard singleton across requests
    // in a test; flush it before each call so the bearer token is the sole auth.
    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };
});

/**
 * A leave request for the seeded ESS employee.
 */
function makeLeave(Employee $employee, array $overrides = []): LeaveRequest
{
    $leaveType = LeaveType::forTenant($employee->tenant_id)->firstOrFail();

    return LeaveRequest::create(array_merge([
        'tenant_id' => $employee->tenant_id,
        'employee_id' => $employee->id,
        'branch_id' => $employee->branch_id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-12',
        'total_days' => 3,
        'reason' => 'Keperluan keluarga',
        'status' => 'approved',
    ], $overrides));
}

it('marks every calendar day the leave covers as cuti', function (): void {
    $leave = makeLeave($this->employee);

    LeaveAttendanceMarker::mark($leave);

    $rows = Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', '>=', '2026-08-10')
        ->whereDate('date', '<=', '2026-08-12')
        ->orderBy('date')
        ->get();

    expect($rows)->toHaveCount(3)
        ->and($rows->pluck('status')->all())->toBe(['leave', 'leave', 'leave'])
        ->and($rows->pluck('clock_in_at')->filter()->all())->toBe([]);
});

it('marks a single-day leave', function (): void {
    $leave = makeLeave($this->employee, [
        'start_date' => '2026-08-10',
        'end_date' => '2026-08-10',
        'total_days' => 1,
    ]);

    LeaveAttendanceMarker::mark($leave);

    expect(Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', '2026-08-10')
        ->where('status', 'leave')
        ->count())->toBe(1);
});

it('never buries an existing clock-in under a leave marker', function (): void {
    Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', '2026-08-10')
        ->delete();

    $clockedIn = Attendance::create([
        'tenant_id' => $this->employee->tenant_id,
        'employee_id' => $this->employee->id,
        'branch_id' => $this->employee->branch_id,
        'date' => '2026-08-10',
        'clock_in_at' => '2026-08-10 08:00:00',
        'status' => 'present',
    ]);

    LeaveAttendanceMarker::mark(makeLeave($this->employee));

    expect($clockedIn->fresh()->status)->toBe('present');

    // The clocked-in day gained no duplicate row beside the real record, and
    // the rest of the range is still marked.
    expect(Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', '2026-08-10')
        ->count())->toBe(1)
        ->and(Attendance::where('employee_id', $this->employee->id)
            ->whereDate('date', '2026-08-11')
            ->where('status', 'leave')
            ->exists())->toBeTrue();
});

it('is idempotent across repeated approvals', function (): void {
    $leave = makeLeave($this->employee);

    LeaveAttendanceMarker::mark($leave);
    LeaveAttendanceMarker::mark($leave);

    expect(Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', '>=', '2026-08-10')
        ->whereDate('date', '<=', '2026-08-12')
        ->count())->toBe(3);
});

it('reports an approved leave as covering each of its days', function (): void {
    makeLeave($this->employee);

    $tenantId = $this->employee->tenant_id;
    $employeeId = $this->employee->id;

    expect(LeaveAttendanceMarker::covers($tenantId, $employeeId, '2026-08-10'))->toBeTrue()
        ->and(LeaveAttendanceMarker::covers($tenantId, $employeeId, '2026-08-11'))->toBeTrue()
        ->and(LeaveAttendanceMarker::covers($tenantId, $employeeId, '2026-08-12'))->toBeTrue()
        ->and(LeaveAttendanceMarker::covers($tenantId, $employeeId, '2026-08-09'))->toBeFalse()
        ->and(LeaveAttendanceMarker::covers($tenantId, $employeeId, '2026-08-13'))->toBeFalse();
});

it('does not treat a pending or rejected leave as covering the day', function (string $status): void {
    makeLeave($this->employee, ['status' => $status]);

    expect(LeaveAttendanceMarker::covers($this->employee->tenant_id, $this->employee->id, '2026-08-10'))
        ->toBeFalse();
})->with(['pending', 'rejected']);

it('refuses a clock-in on a day covered by approved leave', function (): void {
    Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', now()->toDateString())
        ->delete();

    makeLeave($this->employee, [
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDay()->toDateString(),
        'total_days' => 2,
    ]);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
    ])->assertStatus(422)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'cuti'));

    expect(Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', now()->toDateString())
        ->whereNotNull('clock_in_at')
        ->exists())->toBeFalse();
});

it('still allows a clock-in when the leave is only pending', function (): void {
    Attendance::where('employee_id', $this->employee->id)
        ->whereDate('date', now()->toDateString())
        ->delete();

    makeLeave($this->employee, [
        'start_date' => now()->toDateString(),
        'end_date' => now()->toDateString(),
        'total_days' => 1,
        'status' => 'pending',
    ]);

    ($this->auth)()->postJson('/api/v1/me/attendance/clock', [
        'type' => 'in', 'latitude' => -6.2146, 'longitude' => 106.8451,
    ])->assertOk();
});

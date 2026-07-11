<?php

use App\Models\Department;
use App\Models\Employee;
use App\Models\LeaveBalance;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->token = $this->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');

    $this->auth = function () {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$this->token);
    };

    $this->me = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail()->employee;
    $tenantId = $this->me->tenant_id;

    $this->dept = Department::forTenant($tenantId)->firstOrFail();
    $this->me->update(['department_id' => $this->dept->id]);

    $this->type = LeaveType::forTenant($tenantId)->firstOrFail();
});

it('breaks the leave balance down per type with pending days', function (): void {
    LeaveBalance::updateOrCreate(
        [
            'tenant_id' => $this->me->tenant_id, 'employee_id' => $this->me->id,
            'leave_type_id' => $this->type->id, 'year' => now()->year,
        ],
        ['quota' => 12, 'used' => 2, 'remaining' => 10],
    );

    // The seeder plants leave requests for this employee; start clean.
    LeaveRequest::where('employee_id', $this->me->id)->delete();

    LeaveRequest::create([
        'tenant_id' => $this->me->tenant_id, 'employee_id' => $this->me->id,
        'leave_type_id' => $this->type->id, 'start_date' => now()->startOfMonth()->addDays(2)->toDateString(),
        'end_date' => now()->startOfMonth()->addDays(4)->toDateString(), 'total_days' => 3,
        'current_approver_id' => $this->me->manager_id, 'status' => 'pending',
    ]);

    $res = ($this->auth)()->getJson('/api/v1/me/leave/balances')->assertOk();

    $row = collect($res->json('data'))->firstWhere('leave_type_id', $this->type->id);

    expect($row['entitled'])->toEqual(12);
    expect($row['used'])->toEqual(2);
    expect($row['available'])->toEqual(10);
    expect($row['pending'])->toEqual(3);
});

it('shows same-department colleagues on approved leave in the calendar', function (): void {
    $tenantId = $this->me->tenant_id;

    $sameDept = Employee::forTenant($tenantId)->where('id', '!=', $this->me->id)->where('status', 'active')->firstOrFail();
    $sameDept->update(['department_id' => $this->dept->id]);

    $otherDept = Department::forTenant($tenantId)->where('id', '!=', $this->dept->id)->first();
    $outsider = Employee::forTenant($tenantId)
        ->whereNotIn('id', [$this->me->id, $sameDept->id])
        ->where('status', 'active')
        ->first();
    if ($outsider !== null && $otherDept !== null) {
        $outsider->update(['department_id' => $otherDept->id]);
    }

    $approved = fn (Employee $e, string $start, string $end): LeaveRequest => LeaveRequest::create([
        'tenant_id' => $tenantId, 'employee_id' => $e->id, 'leave_type_id' => $this->type->id,
        'start_date' => $start, 'end_date' => $end, 'total_days' => 2,
        'current_approver_id' => $this->me->manager_id, 'status' => 'approved',
    ]);

    $inMonth = now()->startOfMonth()->addDays(10)->toDateString();
    $approved($sameDept, $inMonth, now()->startOfMonth()->addDays(11)->toDateString());
    // Outside the current month → excluded.
    $approved($sameDept, now()->subMonth()->startOfMonth()->toDateString(), now()->subMonth()->startOfMonth()->addDay()->toDateString());
    if ($outsider !== null && $otherDept !== null) {
        $approved($outsider, $inMonth, $inMonth);
    }

    $res = ($this->auth)()->getJson('/api/v1/me/leave/calendar')->assertOk()
        ->assertJsonStructure(['data' => [['id', 'employee' => ['id', 'name', 'initials'], 'is_me', 'leave_type', 'start_date', 'end_date']], 'meta' => ['start', 'end']]);

    $employeeIds = collect($res->json('data'))->pluck('employee.id');

    expect($employeeIds)->toContain($sameDept->id);
    if ($outsider !== null && $otherDept !== null) {
        expect($employeeIds)->not->toContain($outsider->id);
    }
    // Only the in-month entry for the same-dept colleague (not the previous-month one).
    expect(collect($res->json('data'))->where('employee.id', $sameDept->id))->toHaveCount(1);
});

it('honours the calendar period filter', function (): void {
    $start = now()->addMonth()->startOfMonth()->toDateString();
    $end = now()->addMonth()->endOfMonth()->toDateString();

    ($this->auth)()
        ->getJson('/api/v1/me/leave/calendar?start='.$start.'&end='.$end)
        ->assertOk()
        ->assertJsonPath('meta.start', $start)
        ->assertJsonPath('meta.end', $end);
});

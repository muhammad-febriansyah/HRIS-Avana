<?php

use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->director = Employee::where('is_top_approver', true)->firstOrFail();
    $this->tenantId = (int) $this->director->tenant_id;

    $this->staff = Employee::forTenant($this->tenantId)
        ->whereKeyNot($this->director->id)
        ->firstOrFail();

    // These models carry no factory, so a leave request is built the way the
    // controller builds one.
    $this->fileLeave = fn (?int $approverId): LeaveRequest => LeaveRequest::create([
        'tenant_id' => $this->tenantId,
        'employee_id' => $this->staff->id,
        'branch_id' => $this->staff->branch_id,
        'leave_type_id' => DB::table('leave_types')->where('tenant_id', $this->tenantId)->value('id'),
        'start_date' => now()->addDay()->toDateString(),
        'end_date' => now()->addDays(2)->toDateString(),
        'total_days' => 2,
        'current_approver_id' => $approverId,
        'status' => 'pending',
    ]);
});

it('routes a request to an approver who has no user row of the same id', function (): void {
    // `current_approver_id` carries an EMPLOYEE id but used to be constrained to
    // `users`, so it only worked while the two id sequences happened to line up.
    // The director sits high in the employee table and low in the users table —
    // exactly the case that used to fail the insert.
    expect(User::find($this->director->id))->toBeNull();

    $this->staff->update(['manager_id' => $this->director->id]);

    $leave = ($this->fileLeave)($this->staff->fresh()->manager_id);

    expect((int) $leave->fresh()->current_approver_id)->toBe($this->director->id);
});

it('resolves the approver relation to an employee, not a user', function (): void {
    // Every writer sets this from `$employee->manager_id`, so a User relation
    // resolved to whoever happened to hold that id in the users table.
    $leave = ($this->fileLeave)($this->director->id);

    expect($leave->currentApprover)->toBeInstanceOf(Employee::class)
        ->and($leave->currentApprover->full_name)->toBe($this->director->full_name);
});

it('constrains every request table to employees', function (): void {
    $tables = [
        'leave_requests',
        'overtime_requests',
        'permission_requests',
        'wfh_requests',
        'approval_requests',
        'attendance_corrections',
        'claims',
        'reimbursements',
        'settlements',
    ];

    foreach ($tables as $table) {
        $target = collect(Schema::getForeignKeys($table))
            ->firstWhere(fn (array $key): bool => $key['columns'] === ['current_approver_id']);

        expect($target)->not->toBeNull("{$table} has no approver constraint")
            ->and($target['foreign_table'])->toBe('employees', "{$table} points at the wrong table");
    }
});

it('leaves the demo tenant with a single head of the chart', function (): void {
    // Disconnected roots render as separate islands, which reads as a broken
    // chart rather than as missing data.
    $roots = Employee::forTenant($this->tenantId)->whereNull('manager_id')->get();

    expect($roots)->toHaveCount(1)
        ->and($roots->first()->id)->toBe($this->director->id);
});

it('shows an employee with no manager as another head of the chart', function (): void {
    // The chart flags this rather than hiding it: assigning a manager is HR's
    // job, and a silently reparented employee would be worse than a visible gap.
    $this->staff->update(['manager_id' => null]);

    $roots = Employee::forTenant($this->tenantId)->whereNull('manager_id')->pluck('id');

    expect($roots)->toContain($this->staff->id)
        ->and($roots)->toContain($this->director->id);
});

it('exempts the company head from the missing-manager flag', function (): void {
    // The chart tags a root that is missing a manager, but the top approver is
    // supposed to be a root — flagging them would report the correct structure
    // as a data error.
    $this->staff->update(['manager_id' => null]);

    actingAs(User::where('tenant_id', $this->tenantId)->whereNotNull('employee_id')->first()
        ?? User::where('tenant_id', $this->tenantId)->firstOrFail())
        ->get(route('avana.organisasi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('nodes', function ($nodes): bool {
                $rows = collect($nodes);

                $head = $rows->firstWhere('is_top_approver', true);
                $orphan = $rows->first(fn (array $row): bool => $row['manager_id'] === null
                    && $row['is_top_approver'] === false);

                return $head !== null
                    && $head['manager_id'] === null
                    && $orphan !== null;
            })
            ->etc());
});

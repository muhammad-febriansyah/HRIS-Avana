<?php

use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->managerRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'manager')->firstOrFail();
});

/**
 * Create a manager user with an employee record of their own.
 *
 * @return array{0: User, 1: Employee}
 */
function makeScopeManager(int $tenantId, int $roleId, string $name): array
{
    $user = User::factory()->create(['tenant_id' => $tenantId]);
    $user->roles()->sync([$roleId]);

    $employee = Employee::create([
        'tenant_id' => $tenantId,
        'user_id' => $user->id,
        'employee_number' => 'MGR-'.Str::random(6),
        'full_name' => $name,
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    return [$user, $employee];
}

/**
 * Create a pending leave request for a report of the given manager.
 */
function makeScopedLeave(int $tenantId, int $managerEmployeeId, string $name): LeaveRequest
{
    $leaveType = LeaveType::forTenant($tenantId)->firstOrFail();

    $report = Employee::create([
        'tenant_id' => $tenantId,
        'manager_id' => $managerEmployeeId,
        'employee_number' => 'STF-'.Str::random(6),
        'full_name' => $name,
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    return LeaveRequest::create([
        'tenant_id' => $tenantId,
        'employee_id' => $report->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-01',
        'end_date' => '2026-07-02',
        'total_days' => 2,
        'reason' => 'Keperluan keluarga',
        'status' => 'pending',
    ]);
}

/** Create a pending attendance correction for a report of the given manager. */
function makeScopedCorrection(int $tenantId, int $managerEmployeeId, string $name): AttendanceCorrection
{
    $report = Employee::create([
        'tenant_id' => $tenantId,
        'manager_id' => $managerEmployeeId,
        'employee_number' => 'CORR-'.Str::random(6),
        'full_name' => $name,
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    return AttendanceCorrection::create([
        'tenant_id' => $tenantId,
        'employee_id' => $report->id,
        'date' => '2026-07-01',
        'correction_type' => 'manual',
        'requested_clock_in' => '08:00',
        'reason' => 'Lupa absen',
        'current_approver_id' => $managerEmployeeId,
        'status' => 'pending',
    ]);
}

it('shows a manager their own reports and not another team', function (): void {
    [$mine, $myEmployee] = makeScopeManager($this->tenant->id, $this->managerRole->id, 'Manajer Satu');
    [, $theirEmployee] = makeScopeManager($this->tenant->id, $this->managerRole->id, 'Manajer Dua');

    $ours = makeScopedLeave($this->tenant->id, $myEmployee->id, 'Anggota Tim Satu');
    $theirs = makeScopedLeave($this->tenant->id, $theirEmployee->id, 'Anggota Tim Dua');

    actingAs($mine)
        ->get(route('avana.approval'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($ours, $theirs): void {
            $ids = collect($page->toArray()['props']['pending'])
                ->where('type', 'leave')
                ->pluck('id');

            expect($ids)->toContain($ours->id)
                ->and($ids)->not->toContain($theirs->id);
        });
});

it('refuses a manager deciding another team\'s request', function (): void {
    [$mine] = makeScopeManager($this->tenant->id, $this->managerRole->id, 'Manajer Satu');
    [, $theirEmployee] = makeScopeManager($this->tenant->id, $this->managerRole->id, 'Manajer Dua');

    $theirs = makeScopedLeave($this->tenant->id, $theirEmployee->id, 'Anggota Tim Dua');

    actingAs($mine)
        ->post(route('avana.approval.approve', ['type' => 'leave', 'id' => $theirs->id]))
        ->assertNotFound();

    expect($theirs->fresh()->status)->toBe('pending');
});

it('refuses a manager deciding another team\'s attendance correction', function (): void {
    [$mine] = makeScopeManager($this->tenant->id, $this->managerRole->id, 'Manajer Koreksi Satu');
    [, $theirEmployee] = makeScopeManager($this->tenant->id, $this->managerRole->id, 'Manajer Koreksi Dua');

    $theirs = makeScopedCorrection($this->tenant->id, $theirEmployee->id, 'Anggota Tim Koreksi Dua');

    actingAs($mine)
        ->post(route('avana.approval.approve', ['type' => 'koreksi', 'id' => $theirs->id]))
        ->assertNotFound();

    expect($theirs->fresh()->status)->toBe('pending');
});

it('shows a request routed to an approver who is not the line manager', function (): void {
    [$mine, $myEmployee] = makeScopeManager($this->tenant->id, $this->managerRole->id, 'Manajer Satu');
    [, $theirEmployee] = makeScopeManager($this->tenant->id, $this->managerRole->id, 'Manajer Dua');

    $routed = makeScopedLeave($this->tenant->id, $theirEmployee->id, 'Anggota Tim Dua');
    $routed->update(['current_approver_id' => $myEmployee->id]);

    actingAs($mine)
        ->get(route('avana.approval'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($routed): void {
            $ids = collect($page->toArray()['props']['pending'])
                ->where('type', 'leave')
                ->pluck('id');

            expect($ids)->toContain($routed->id);
        });
});

it('keeps the whole tenant visible to HR', function (): void {
    [, $theirEmployee] = makeScopeManager($this->tenant->id, $this->managerRole->id, 'Manajer Dua');
    $theirs = makeScopedLeave($this->tenant->id, $theirEmployee->id, 'Anggota Tim Dua');

    actingAs($this->admin)
        ->get(route('avana.approval'))
        ->assertOk()
        ->assertInertia(function (Assert $page) use ($theirs): void {
            $ids = collect($page->toArray()['props']['pending'])
                ->where('type', 'leave')
                ->pluck('id');

            expect($ids)->toContain($theirs->id);
        });
});

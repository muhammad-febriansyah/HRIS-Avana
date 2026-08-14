<?php

use App\Models\Attendance;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\AttendancePolicy;
use App\Policies\PayrollPolicy;
use App\Support\Access;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->tenant = Tenant::whereNotNull('id')->firstOrFail();
    Access::setEnforced(true);
});

afterEach(function (): void {
    Access::setEnforced(true);
});

/**
 * Build a tenant user whose single role holds exactly the given permission codes.
 *
 * @param  array<int, string>  $codes
 */
function userWithCodes(int $tenantId, array $codes): User
{
    $role = Role::create([
        'tenant_id' => $tenantId,
        'code' => 'lim-'.implode('-', $codes),
        'name' => 'Limited',
        'is_system' => false,
    ]);
    $role->permissions()->syncWithoutDetaching(
        Permission::whereIn('code', $codes)->pluck('id'),
    );

    $user = User::factory()->create(['tenant_id' => $tenantId]);
    $user->roles()->sync([$role->id]);

    return $user->fresh(['roles.permissions']);
}

it('blocks a view-only attendance role from creating a roster schedule', function (): void {
    $user = userWithCodes($this->tenant->id, ['attendance.view']);

    actingAs($user)
        ->post(route('avana.roster.store'), [])
        ->assertForbidden();
});

it('blocks a view-only attendance role from creating a penalty', function (): void {
    $user = userWithCodes($this->tenant->id, ['attendance.view']);

    actingAs($user)
        ->post(route('avana.sanksi.store'), [])
        ->assertForbidden();
});

it('blocks a view-only employee role from recording a mutation', function (): void {
    $user = userWithCodes($this->tenant->id, ['employee.view']);

    actingAs($user)
        ->post(route('avana.mutasi.store'), [])
        ->assertForbidden();
});

it('blocks an overtime view-only role from creating overtime', function (): void {
    // create() used to be gated by overtime.view; now it requires overtime.create.
    $user = userWithCodes($this->tenant->id, ['overtime.view']);

    actingAs($user)
        ->post(route('avana.cuti.lembur.store'), [])
        ->assertForbidden();
});

it('lets a wfh.create role submit a WFH request without approve rights', function (): void {
    // create() used to be wrongly gated by wfh.approve; now wfh.create suffices.
    $user = userWithCodes($this->tenant->id, ['wfh.create']);

    actingAs($user)
        ->post(route('avana.cuti.wfh.store'), [])
        ->assertRedirect(); // authorized → validation redirects back, not a 403
});

it('uses the seeded attendance correction permission for approve and reject', function (): void {
    $user = userWithCodes($this->tenant->id, ['attendance.correction.approve']);
    $policy = new AttendancePolicy;

    expect($policy->approveCorrection($user, Attendance::class))->toBeTrue()
        ->and($policy->rejectCorrection($user, Attendance::class))->toBeTrue();
});

it('splits payroll into distinct create, approve, update, and export gates', function (): void {
    // Previously every payroll action collapsed into a single payroll.run gate,
    // which defeats segregation of duties (the runner must not approve).
    $creator = userWithCodes($this->tenant->id, ['payroll.create']);
    $approver = userWithCodes($this->tenant->id, ['payroll.approve']);
    $policy = new PayrollPolicy;

    expect($policy->create($creator))->toBeTrue();
    expect($policy->approve($creator))->toBeFalse();
    expect($policy->update($creator))->toBeFalse();
    expect($policy->export($creator))->toBeFalse();

    expect($policy->approve($approver))->toBeTrue();
    expect($policy->create($approver))->toBeFalse();
});

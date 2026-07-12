<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->tenantId = User::where('email', 'rina.a@nusantara.co.id')->value('tenant_id');
});

/**
 * Create an active employee wired to an active login account.
 *
 * @return array{0: Employee, 1: User}
 */
function activeEmployeeWithLogin(int $tenantId): array
{
    $user = User::create([
        'tenant_id' => $tenantId,
        'name' => 'Block Test',
        'email' => 'block.test@contoh.co',
        'password' => 'password123',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $employee = Employee::create([
        'tenant_id' => $tenantId,
        'employee_number' => 'BLK-001',
        'full_name' => 'Block Test',
        'email' => 'block.test@contoh.co',
        'employment_status' => 'permanent',
        'status' => 'active',
        'user_id' => $user->id,
    ]);

    return [$employee, $user];
}

it('blocks the login and revokes tokens when the employee is set inactive', function (): void {
    [$employee, $user] = activeEmployeeWithLogin($this->tenantId);
    $versionBefore = (int) $user->fresh()->token_version;

    $employee->update(['status' => 'inactive']);

    $user->refresh();
    expect($user->status)->toBe('inactive');
    expect((int) $user->token_version)->toBe($versionBefore + 1);
});

it('restores the login when the employee is set active again', function (): void {
    [$employee] = activeEmployeeWithLogin($this->tenantId);

    $employee->update(['status' => 'inactive']);
    $blockedVersion = (int) $employee->user->refresh()->token_version;

    $employee->update(['status' => 'active']);

    $user = $employee->user->refresh();
    expect($user->status)->toBe('active');
    // Restoring access must not bump the token version again.
    expect((int) $user->token_version)->toBe($blockedVersion);
});

it('blocks a freshly created inactive employee that has a login', function (): void {
    $user = User::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Born Inactive',
        'email' => 'born.inactive@contoh.co',
        'password' => 'password123',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    Employee::create([
        'tenant_id' => $this->tenantId,
        'employee_number' => 'BLK-002',
        'full_name' => 'Born Inactive',
        'email' => 'born.inactive@contoh.co',
        'employment_status' => 'permanent',
        'status' => 'inactive',
        'user_id' => $user->id,
    ]);

    expect($user->fresh()->status)->toBe('inactive');
});

it('leaves the login untouched when a non-status field changes', function (): void {
    [$employee, $user] = activeEmployeeWithLogin($this->tenantId);
    $versionBefore = (int) $user->fresh()->token_version;

    $employee->update(['full_name' => 'Block Test Renamed']);

    $user->refresh();
    expect($user->status)->toBe('active');
    expect((int) $user->token_version)->toBe($versionBefore);
});

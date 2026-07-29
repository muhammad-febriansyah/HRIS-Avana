<?php

use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->tenantId = User::where('email', 'rina.a@nusantara.co.id')->value('tenant_id');

    $this->role = Role::where('tenant_id', $this->tenantId)->where('code', 'employee')->firstOrFail();

    $this->user = User::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Mobile Flag Tester',
        'email' => 'mobile.flag@contoh.co',
        'password' => 'secret12345',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    Employee::create([
        'tenant_id' => $this->tenantId,
        'employee_number' => 'MOB-FLAG-1',
        'full_name' => 'Mobile Flag Tester',
        'email' => 'mobile.flag@contoh.co',
        'employment_status' => 'permanent',
        'status' => 'active',
        'user_id' => $this->user->id,
    ]);

    $this->user->roles()->sync([$this->role->id]);
});

/** Log in through the mobile API. */
function roleMobileLogin(): TestResponse
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => 'mobile.flag@contoh.co',
        'password' => 'secret12345',
    ]);
}

it('lets a role that allows the app sign in', function (): void {
    roleMobileLogin()->assertOk();
});

it('denies mobile login when the role bars the app', function (): void {
    $this->role->update(['can_access_mobile' => false]);

    roleMobileLogin()
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Peran akun ini tidak diizinkan memakai aplikasi mobile.');
});

it('still allows the app when a second role permits it', function (): void {
    $this->role->update(['can_access_mobile' => false]);

    $manager = Role::where('tenant_id', $this->tenantId)->where('code', 'manager')->firstOrFail();
    $this->user->roles()->syncWithoutDetaching([$manager->id]);

    roleMobileLogin()->assertOk();
});

it('lets the account back in once the role is re-allowed', function (): void {
    $this->role->update(['can_access_mobile' => false]);
    roleMobileLogin()->assertStatus(422);

    $this->role->update(['can_access_mobile' => true]);
    roleMobileLogin()->assertOk();
});

it('leaves the web permissions untouched for a role barred from the app', function (): void {
    $before = $this->user->fresh()->permissionCodes()->sort()->values();

    $this->role->update(['can_access_mobile' => false]);

    $after = $this->user->fresh()->permissionCodes()->sort()->values();

    expect($after->all())->toBe($before->all())
        ->and($this->user->fresh()->canAccessMobile())->toBeFalse();
});

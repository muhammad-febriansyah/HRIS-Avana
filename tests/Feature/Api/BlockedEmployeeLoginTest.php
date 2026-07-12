<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->tenantId = User::where('email', 'rina.a@nusantara.co.id')->value('tenant_id');

    $this->user = User::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Mobile Tester',
        'email' => 'mobile.tester@contoh.co',
        'password' => 'secret12345',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    $this->employee = Employee::create([
        'tenant_id' => $this->tenantId,
        'employee_number' => 'MOB-001',
        'full_name' => 'Mobile Tester',
        'email' => 'mobile.tester@contoh.co',
        'employment_status' => 'permanent',
        'status' => 'active',
        'user_id' => $this->user->id,
    ]);
});

/** Log in through the mobile API and return the issued bearer token. */
function mobileLogin(string $email = 'mobile.tester@contoh.co', string $password = 'secret12345'): TestResponse
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => $email,
        'password' => $password,
    ]);
}

it('lets an active employee log in and reach the API', function (): void {
    $login = mobileLogin()->assertOk();
    $token = $login->json('access_token');

    expect($token)->toBeString()->not->toBeEmpty();

    test()->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('data.email', 'mobile.tester@contoh.co');
});

it('denies mobile login once the employee is set inactive', function (): void {
    $this->employee->update(['status' => 'inactive']);

    mobileLogin()
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Akun tidak aktif.');
});

it('revokes an already-issued token when the employee is blocked', function (): void {
    $token = mobileLogin()->assertOk()->json('access_token');

    // Session works before the block.
    test()->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])->assertOk();

    $this->employee->update(['status' => 'inactive']);

    // Drop the in-process guard cache so the next request re-reads the user
    // from the database, exactly as a fresh mobile HTTP request would.
    $this->app['auth']->forgetGuards();

    // The bumped token version invalidates the outstanding token.
    test()->getJson('/api/v1/auth/me', ['Authorization' => "Bearer {$token}"])
        ->assertStatus(401);
});

it('lets the employee log in again after being reactivated', function (): void {
    $this->employee->update(['status' => 'inactive']);
    mobileLogin()->assertStatus(422);

    $this->employee->update(['status' => 'active']);

    mobileLogin()->assertOk();
});

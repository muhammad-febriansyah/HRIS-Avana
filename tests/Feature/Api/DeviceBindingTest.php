<?php

use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
});

/** Fresh login request; flushes the cached jwt guard so each call re-auths. */
function login(array $extra = [])
{
    app('auth')->forgetGuards();

    return test()->postJson('/api/v1/auth/login', array_merge([
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ], $extra));
}

it('binds the first device on login and records its details', function (): void {
    login([
        'device_id' => 'DEV-AAA',
        'model' => 'Pixel 8',
        'platform' => 'android',
        'os_version' => '14',
    ])->assertOk()->assertJsonStructure(['access_token']);

    $device = UserDevice::where('user_id', $this->employee->id)->active()->firstOrFail();

    expect($device->device_id)->toBe('DEV-AAA');
    expect($device->model)->toBe('Pixel 8');
    expect($device->platform)->toBe('android');
    expect($device->bound_at)->not->toBeNull();
});

it('allows repeat login from the same device without duplicating', function (): void {
    login(['device_id' => 'DEV-AAA'])->assertOk();
    login(['device_id' => 'DEV-AAA'])->assertOk()->assertJsonStructure(['access_token']);

    expect(UserDevice::where('user_id', $this->employee->id)->active()->count())->toBe(1);
});

it('rejects login from a different device', function (): void {
    login(['device_id' => 'DEV-AAA'])->assertOk();

    login(['device_id' => 'DEV-BBB'])
        ->assertStatus(403)
        ->assertJsonPath('message', fn (string $m): bool => str_contains($m, 'Perangkat tidak dikenali'));
});

it('allows a new device to bind after the active one is reset', function (): void {
    login(['device_id' => 'DEV-AAA'])->assertOk();

    UserDevice::where('user_id', $this->employee->id)->active()->update(['status' => 'reset']);

    login(['device_id' => 'DEV-BBB'])->assertOk()->assertJsonStructure(['access_token']);
    expect(UserDevice::where('user_id', $this->employee->id)->active()->value('device_id'))->toBe('DEV-BBB');
});

it('allows login without device info for non-mobile clients', function (): void {
    login()->assertOk()->assertJsonStructure(['access_token']);

    expect(UserDevice::count())->toBe(0);
});

it('locks the old phone out the moment an admin resets the device', function (): void {
    $token = login([
        'device_id' => 'DEV-OLD',
        'model' => 'Pixel 8',
    ])->assertOk()->json('access_token');

    // The old phone works right up until the reset.
    app('auth')->forgetGuards();
    test()->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/profile')
        ->assertOk();

    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();

    test()->actingAs($admin)
        ->post(route('avana.employees.reset-device', $this->employee->employee))
        ->assertSessionHas('success');

    // Freeing the slot is not enough — the token it already holds must die too,
    // otherwise a lost phone keeps working until the refresh window closes.
    app('auth')->forgetGuards();
    test()->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me/profile')
        ->assertUnauthorized();

    app('auth')->forgetGuards();
    test()->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/auth/refresh')
        ->assertUnauthorized();
});

it('lets the employee bind a new phone after the reset', function (): void {
    login(['device_id' => 'DEV-OLD'])->assertOk();

    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    test()->actingAs($admin)
        ->post(route('avana.employees.reset-device', $this->employee->employee));

    login(['device_id' => 'DEV-NEW', 'model' => 'Galaxy S24'])->assertOk();

    expect(UserDevice::where('user_id', $this->employee->id)->active()->first()->device_id)
        ->toBe('DEV-NEW');
});

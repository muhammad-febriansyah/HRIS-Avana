<?php

use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Hash;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->user = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();

    $this->login = function (array $extra = []): string {
        $this->app['auth']->forgetGuards();

        return $this->postJson('/api/v1/auth/login', array_merge([
            'email' => 'bagus.p@nusantara.co.id',
            'password' => 'password',
        ], $extra))->json('access_token');
    };

    $this->withToken = function (string $token) {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', 'Bearer '.$token);
    };

    $this->token = ($this->login)();
});

it('rejects a password change with the wrong current password', function (): void {
    ($this->withToken)($this->token)
        ->postJson('/api/v1/me/security/password', [
            'current_password' => 'salah-password',
            'password' => 'rahasiaBaru123',
            'password_confirmation' => 'rahasiaBaru123',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors('current_password');
});

it('changes the password, rotates the token, and invalidates the old one', function (): void {
    $res = ($this->withToken)($this->token)
        ->postJson('/api/v1/me/security/password', [
            'current_password' => 'password',
            'password' => 'rahasiaBaru123',
            'password_confirmation' => 'rahasiaBaru123',
        ])
        ->assertOk()
        ->assertJsonStructure(['message', 'access_token', 'expires_in']);

    $newToken = $res->json('access_token');

    expect(Hash::check('rahasiaBaru123', $this->user->fresh()->password))->toBeTrue();
    expect($this->user->fresh()->token_version)->toBe(1);

    // The token used to make the change is now stale; the returned one works.
    ($this->withToken)($this->token)->getJson('/api/v1/auth/me')->assertStatus(401);
    ($this->withToken)($newToken)->getJson('/api/v1/auth/me')->assertOk();
});

it('signs out of all devices and invalidates outstanding tokens', function (): void {
    $token = ($this->login)(['device_id' => 'DEV-AAA']);

    ($this->withToken)($token)
        ->postJson('/api/v1/me/security/logout-all')
        ->assertOk();

    expect($this->user->fresh()->token_version)->toBe(1);
    expect(UserDevice::where('user_id', $this->user->id)->active()->count())->toBe(0);

    ($this->withToken)($token)->getJson('/api/v1/auth/me')->assertStatus(401);
});

it('lists the bound devices and flags the current one', function (): void {
    $token = ($this->login)([
        'device_id' => 'DEV-XYZ',
        'device_name' => 'Galaxy S24',
        'model' => 'SM-S921',
        'platform' => 'android',
    ]);

    ($this->withToken)($token)
        ->getJson('/api/v1/me/security/devices?device_id=DEV-XYZ')
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'device_name', 'platform', 'status', 'is_current', 'last_login_at']]])
        ->assertJsonFragment(['device_name' => 'Galaxy S24', 'is_current' => true, 'status' => 'active']);
});

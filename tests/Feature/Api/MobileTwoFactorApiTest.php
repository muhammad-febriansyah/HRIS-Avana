<?php

use App\Models\AttendancePolicy;
use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Arr;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
});

/** Fresh login request; flushes the cached jwt guard so each call re-auths. */
function twoFactorLogin(array $extra = [])
{
    app('auth')->forgetGuards();

    return test()->postJson('/api/v1/auth/login', array_merge([
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ], $extra));
}

/** Turn on two-factor for the given user, the way the web enrolment leaves it. */
function enrolTwoFactor(User $user): User
{
    $user->forceFill(Arr::only(
        User::factory()->withTwoFactor()->raw(),
        ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'],
    ))->save();

    return $user->refresh();
}

/** The code an authenticator app would be showing right now. */
function currentOtp(User $user): string
{
    return (new Google2FA)->getCurrentOtp(
        Fortify::currentEncrypter()->decrypt($user->fresh()->two_factor_secret),
    );
}

it('still hands a plain account its token in one call', function (): void {
    twoFactorLogin()
        ->assertOk()
        ->assertJsonStructure(['access_token', 'user'])
        ->assertJsonMissingPath('two_factor_required');
});

it('withholds the token and returns a challenge when two-factor is on', function (): void {
    enrolTwoFactor($this->employee);

    $response = twoFactorLogin()->assertOk();

    expect($response->json('two_factor_required'))->toBeTrue()
        ->and($response->json('challenge_token'))->toBeString()
        ->and($response->json('access_token'))->toBeNull()
        ->and($response->json('user'))->toBeNull();
});

it('exchanges a valid authenticator code for the token', function (): void {
    enrolTwoFactor($this->employee);

    $challenge = twoFactorLogin()->json('challenge_token');

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/two-factor', [
        'challenge_token' => $challenge,
        'code' => currentOtp($this->employee),
    ])
        ->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'user'])
        ->assertJsonPath('user.email', 'bagus.p@nusantara.co.id');
});

it('issues a token the rest of the API accepts', function (): void {
    enrolTwoFactor($this->employee);

    $challenge = twoFactorLogin()->json('challenge_token');

    app('auth')->forgetGuards();

    $token = $this->postJson('/api/v1/auth/two-factor', [
        'challenge_token' => $challenge,
        'code' => currentOtp($this->employee),
    ])->json('access_token');

    app('auth')->forgetGuards();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'bagus.p@nusantara.co.id');
});

it('rejects a wrong code and keeps the account out', function (): void {
    enrolTwoFactor($this->employee);

    $challenge = twoFactorLogin()->json('challenge_token');

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/two-factor', [
        'challenge_token' => $challenge,
        'code' => '000000',
    ])->assertStatus(422);
});

it('spends a challenge on success so it cannot be replayed', function (): void {
    enrolTwoFactor($this->employee);

    $challenge = twoFactorLogin()->json('challenge_token');
    $code = currentOtp($this->employee);

    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/two-factor', ['challenge_token' => $challenge, 'code' => $code])->assertOk();

    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/two-factor', ['challenge_token' => $challenge, 'code' => $code])
        ->assertUnauthorized();
});

it('retires a challenge after five wrong codes', function (): void {
    enrolTwoFactor($this->employee);

    $challenge = twoFactorLogin()->json('challenge_token');

    foreach (range(1, 5) as $attempt) {
        app('auth')->forgetGuards();
        $this->postJson('/api/v1/auth/two-factor', ['challenge_token' => $challenge, 'code' => '000000'])
            ->assertStatus(422);
    }

    // Even the right code is too late once the challenge has been thrown away.
    app('auth')->forgetGuards();
    $this->postJson('/api/v1/auth/two-factor', [
        'challenge_token' => $challenge,
        'code' => currentOtp($this->employee),
    ])->assertUnauthorized();
});

it('turns away a challenge token it never issued', function (): void {
    $this->postJson('/api/v1/auth/two-factor', ['challenge_token' => 'dibuat-buat', 'code' => '000000'])
        ->assertUnauthorized();
});

it('accepts a recovery code and spends it', function (): void {
    enrolTwoFactor($this->employee);

    $recoveryCode = $this->employee->fresh()->recoveryCodes()[0];

    $challenge = twoFactorLogin()->json('challenge_token');

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/two-factor', [
        'challenge_token' => $challenge,
        'recovery_code' => $recoveryCode,
    ])->assertOk()->assertJsonStructure(['access_token']);

    expect($this->employee->fresh()->recoveryCodes())->not->toContain($recoveryCode);
});

it('holds off device binding until the second factor lands', function (): void {
    AttendancePolicy::resolve($this->employee->tenant_id)->update(['device_binding_enabled' => true]);

    enrolTwoFactor($this->employee);

    $challenge = twoFactorLogin(['device_id' => 'DEV-AAA', 'model' => 'Pixel 8'])->json('challenge_token');

    // The password alone must not be able to claim the account's device slot.
    expect(UserDevice::where('user_id', $this->employee->id)->exists())->toBeFalse();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/two-factor', [
        'challenge_token' => $challenge,
        'code' => currentOtp($this->employee),
    ])->assertOk();

    $device = UserDevice::where('user_id', $this->employee->id)->active()->firstOrFail();

    expect($device->device_id)->toBe('DEV-AAA')
        ->and($device->model)->toBe('Pixel 8');
});

it('rejects a second device on the exchange, carrying the details from login', function (): void {
    AttendancePolicy::resolve($this->employee->tenant_id)->update(['device_binding_enabled' => true]);

    UserDevice::create([
        'tenant_id' => $this->employee->tenant_id,
        'user_id' => $this->employee->id,
        'device_id' => 'DEV-LAMA',
        'status' => 'active',
        'bound_at' => now(),
    ]);

    enrolTwoFactor($this->employee);

    $challenge = twoFactorLogin(['device_id' => 'DEV-BARU'])->json('challenge_token');

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/two-factor', [
        'challenge_token' => $challenge,
        'code' => currentOtp($this->employee),
    ])->assertStatus(403);
});

it('drops the challenge when two-factor is switched off mid-flow', function (): void {
    enrolTwoFactor($this->employee);

    $challenge = twoFactorLogin()->json('challenge_token');
    $code = currentOtp($this->employee);

    $this->employee->forceFill([
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ])->save();

    app('auth')->forgetGuards();

    $this->postJson('/api/v1/auth/two-factor', ['challenge_token' => $challenge, 'code' => $code])
        ->assertUnauthorized();
});

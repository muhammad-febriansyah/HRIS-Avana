<?php

use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Arr;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
});

/** A bearer token for the demo employee. */
function setupToken(): string
{
    app('auth')->forgetGuards();

    return test()->postJson('/api/v1/auth/login', [
        'email' => 'bagus.p@nusantara.co.id',
        'password' => 'password',
    ])->json('access_token');
}

/** Call the API as the demo employee. */
function asEmployee(string $token)
{
    app('auth')->forgetGuards();

    return test()->withHeader('Authorization', 'Bearer '.$token);
}

it('reports two-factor as off, with nothing to leak, before enrolment', function (): void {
    $token = setupToken();

    asEmployee($token)
        ->getJson('/api/v1/me/security/two-factor')
        ->assertOk()
        ->assertJsonPath('data.enabled', false)
        ->assertJsonPath('data.confirming', false)
        ->assertJsonPath('data.setup_key', null)
        ->assertJsonPath('data.qr_svg', null)
        ->assertJsonPath('data.recovery_codes', []);
});

it('will not begin enrolment without the password', function (): void {
    $token = setupToken();

    asEmployee($token)
        ->postJson('/api/v1/me/security/two-factor', ['current_password' => 'salah'])
        ->assertStatus(422);

    expect($this->employee->fresh()->two_factor_secret)->toBeNull();
});

it('hands back the QR and the key once enrolment begins', function (): void {
    $token = setupToken();

    $response = asEmployee($token)
        ->postJson('/api/v1/me/security/two-factor', ['current_password' => 'password'])
        ->assertOk();

    expect($response->json('data.confirming'))->toBeTrue()
        ->and($response->json('data.enabled'))->toBeFalse()
        ->and($response->json('data.qr_svg'))->toContain('<svg')
        ->and($response->json('data.setup_url'))->toStartWith('otpauth://')
        ->and($response->json('data.setup_key'))->toBeString()
        // Nothing to write down until the code has proved the app is set up.
        ->and($response->json('data.recovery_codes'))->toBe([]);
});

it('leaves the account unprotected until a code confirms it', function (): void {
    $token = setupToken();

    asEmployee($token)->postJson('/api/v1/me/security/two-factor', ['current_password' => 'password']);

    expect($this->employee->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();

    asEmployee($token)
        ->postJson('/api/v1/me/security/two-factor/confirm', ['code' => '000000'])
        ->assertStatus(422);

    expect($this->employee->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('turns it on with a valid code and issues recovery codes', function (): void {
    $token = setupToken();

    asEmployee($token)->postJson('/api/v1/me/security/two-factor', ['current_password' => 'password']);

    $secret = Fortify::currentEncrypter()->decrypt($this->employee->fresh()->two_factor_secret);

    $response = asEmployee($token)
        ->postJson('/api/v1/me/security/two-factor/confirm', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertOk();

    expect($response->json('data.enabled'))->toBeTrue()
        ->and($response->json('data.confirming'))->toBeFalse()
        // The secret has served its purpose; only the recovery codes stay.
        ->and($response->json('data.setup_key'))->toBeNull()
        ->and($response->json('data.recovery_codes'))->toHaveCount(8)
        ->and($this->employee->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('refuses to confirm an enrolment that was never begun', function (): void {
    $token = setupToken();

    asEmployee($token)
        ->postJson('/api/v1/me/security/two-factor/confirm', ['code' => '000000'])
        ->assertStatus(422);
});

it('reissues recovery codes, retiring the old set', function (): void {
    $token = setupToken();

    $this->employee->forceFill(Arr::only(
        User::factory()->withTwoFactor()->raw(),
        ['two_factor_secret', 'two_factor_recovery_codes', 'two_factor_confirmed_at'],
    ))->save();

    $original = $this->employee->fresh()->recoveryCodes();

    $response = asEmployee($token)
        ->postJson('/api/v1/me/security/two-factor/recovery-codes', ['current_password' => 'password'])
        ->assertOk();

    expect($response->json('data.recovery_codes'))
        ->toHaveCount(8)
        ->not->toEqual($original);
});

it('will not turn it off without the password', function (): void {
    $token = setupToken();

    asEmployee($token)->postJson('/api/v1/me/security/two-factor', ['current_password' => 'password']);

    asEmployee($token)
        ->deleteJson('/api/v1/me/security/two-factor', ['current_password' => 'salah'])
        ->assertStatus(422);

    expect($this->employee->fresh()->two_factor_secret)->not->toBeNull();
});

it('turns it off, secret and recovery codes with it', function (): void {
    $token = setupToken();

    asEmployee($token)->postJson('/api/v1/me/security/two-factor', ['current_password' => 'password']);

    $response = asEmployee($token)
        ->deleteJson('/api/v1/me/security/two-factor', ['current_password' => 'password'])
        ->assertOk();

    expect($response->json('data.enabled'))->toBeFalse();

    $fresh = $this->employee->fresh();

    expect($fresh->two_factor_secret)->toBeNull()
        ->and($fresh->two_factor_recovery_codes)->toBeNull();
});

it('is closed to anyone without a token', function (): void {
    $this->getJson('/api/v1/me/security/two-factor')->assertUnauthorized();
    $this->postJson('/api/v1/me/security/two-factor', ['current_password' => 'password'])->assertUnauthorized();
});

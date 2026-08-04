<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

/**
 * Sign in and clear the password confirmation gate the two-factor routes sit
 * behind, so each test can get straight to the behaviour it is about.
 */
function actingAsConfirmed(User $user)
{
    return test()
        ->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()]);
}

test('two factor is off until the user turns it on', function () {
    $user = User::factory()->create();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeFalse();

    actingAsConfirmed($user)
        ->get(route('security.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('twoFactorEnabled', false)
            ->where('twoFactorSecretKey', null)
            ->where('twoFactorRecoveryCodes', []),
        );
});

test('enabling generates a secret but leaves the account unprotected until confirmed', function () {
    $user = User::factory()->create();

    actingAsConfirmed($user)
        ->post(route('two-factor.enable'))
        ->assertRedirect();

    $user->refresh();

    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->two_factor_confirmed_at)->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('the setup page hands over the qr code and manual key while confirmation is pending', function () {
    $user = User::factory()->create();

    actingAsConfirmed($user)->post(route('two-factor.enable'));

    $user->refresh();

    actingAsConfirmed($user)
        ->get(route('security.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('twoFactorEnabled', false)
            ->where('requiresConfirmation', true)
            ->where('twoFactorSecretKey', Fortify::currentEncrypter()->decrypt($user->two_factor_secret))
            ->has('twoFactorQrCodeSvg'),
        );
});

test('an invalid confirmation code does not turn two factor on', function () {
    $user = User::factory()->create();

    actingAsConfirmed($user)->post(route('two-factor.enable'));

    actingAsConfirmed($user->refresh())
        ->from(route('security.edit'))
        ->post(route('two-factor.confirm'), ['code' => '000000'])
        ->assertSessionHasErrors('code', errorBag: 'confirmTwoFactorAuthentication');

    expect($user->refresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('a valid confirmation code turns two factor on and issues recovery codes', function () {
    $user = User::factory()->create();

    actingAsConfirmed($user)->post(route('two-factor.enable'));

    $user->refresh();
    $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

    actingAsConfirmed($user)
        ->post(route('two-factor.confirm'), ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($user->two_factor_confirmed_at)->not->toBeNull()
        ->and($user->recoveryCodes())->toHaveCount(8);

    actingAsConfirmed($user)
        ->get(route('security.edit'))
        ->assertInertia(fn ($page) => $page
            ->where('twoFactorEnabled', true)
            // The secret has served its purpose; only the recovery codes stay.
            ->where('twoFactorSecretKey', null)
            ->has('twoFactorRecoveryCodes', 8),
        );
});

test('recovery codes can be regenerated', function () {
    $user = User::factory()->withTwoFactor()->create();

    $original = $user->recoveryCodes();

    actingAsConfirmed($user)
        ->post(route('two-factor.regenerate-recovery-codes'))
        ->assertSessionHasNoErrors();

    expect($user->refresh()->recoveryCodes())
        ->toHaveCount(8)
        ->not->toEqual($original);
});

test('two factor can be turned back off', function () {
    $user = User::factory()->withTwoFactor()->create();

    actingAsConfirmed($user)
        ->delete(route('two-factor.disable'))
        ->assertRedirect();

    $user->refresh();

    expect($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull()
        ->and($user->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

test('managing two factor requires a confirmed password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('two-factor.enable'))
        ->assertRedirect(route('password.confirm'));

    expect($user->refresh()->two_factor_secret)->toBeNull();
});

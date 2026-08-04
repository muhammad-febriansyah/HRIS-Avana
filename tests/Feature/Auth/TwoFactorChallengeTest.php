<?php

use App\Models\User;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;
use PragmaRX\Google2FA\Google2FA;

beforeEach(function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());
});

/**
 * Log in far enough to be handed the challenge, without a session yet.
 */
function challenge(User $user): void
{
    test()->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);
}

test('the challenge screen renders for a user waiting to verify', function () {
    $user = User::factory()->withTwoFactor()->create();

    challenge($user);

    $this->get(route('two-factor.login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('auth/two-factor-challenge'));
});

test('the challenge screen is closed to anyone who has not passed a password', function () {
    $this->get(route('two-factor.login'))->assertRedirect(route('login'));
});

test('a valid authenticator code completes the login', function () {
    $user = User::factory()->withTwoFactor()->create();

    challenge($user);

    $secret = Fortify::currentEncrypter()->decrypt($user->two_factor_secret);

    $this->post(route('two-factor.login.store'), [
        'code' => (new Google2FA)->getCurrentOtp($secret),
    ])->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);
});

test('an invalid authenticator code leaves the user outside', function () {
    $user = User::factory()->withTwoFactor()->create();

    challenge($user);

    $this->post(route('two-factor.login.store'), ['code' => '000000'])
        ->assertSessionHasErrors('code');

    $this->assertGuest();
});

test('a recovery code completes the login and is then spent', function () {
    $user = User::factory()->withTwoFactor()->create();

    $recoveryCode = $user->recoveryCodes()[0];

    challenge($user);

    $this->post(route('two-factor.login.store'), ['recovery_code' => $recoveryCode])
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticatedAs($user);

    expect($user->refresh()->recoveryCodes())->not->toContain($recoveryCode);
});

test('an invalid recovery code leaves the user outside', function () {
    $user = User::factory()->withTwoFactor()->create();

    challenge($user);

    $this->post(route('two-factor.login.store'), ['recovery_code' => 'not-a-real-code'])
        ->assertSessionHasErrors('recovery_code');

    $this->assertGuest();
});

test('a user without two factor is logged straight in', function () {
    $user = User::factory()->create();

    challenge($user);

    $this->assertAuthenticatedAs($user);
});

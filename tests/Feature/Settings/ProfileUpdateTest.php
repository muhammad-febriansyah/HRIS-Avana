<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/profile')
            ->where('securityUnlocked', false)
            // The two-factor secret and recovery codes stay behind the
            // password confirmation, so they must not ship with the page.
            ->missing('twoFactorSecretKey')
            ->missing('twoFactorRecoveryCodes'));
});

test('the security panels ride along once the password was confirmed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('securityUnlocked', true)
            ->has('passkeys')
            ->has('passwordRules'));
});

test('the phone number is saved with the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $user->name,
            'email' => $user->email,
            'phone' => '0812345678',
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->phone)->toBe('0812345678');
});

test('the account photo is stored privately and can be removed', function () {
    Storage::fake('local');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.avatar'), [
            'avatar' => UploadedFile::fake()->image('foto.jpg'),
        ])
        ->assertRedirect(route('profile.edit'));

    $path = $user->refresh()->avatar_path;

    expect($path)->toStartWith('avatars/');
    Storage::disk('local')->assertExists($path);

    $this->actingAs($user)
        ->post(route('profile.avatar'), ['remove' => true])
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->avatar_path)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('a linked account cannot rename itself away from its employee record', function () {
    $tenant = Tenant::create(['name' => 'PT Coba Profil', 'slug' => 'pt-coba-profil']);
    $user = User::factory()->create(['name' => 'Nama Lama', 'tenant_id' => $tenant->id]);

    Employee::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'full_name' => 'Nida Raihani',
        'employee_number' => 'EMP-PROFIL-1',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Nama Karangan',
            'email' => $user->email,
        ])
        ->assertSessionHasNoErrors();

    expect($user->refresh()->name)->toBe('Nida Raihani');
});

test('changing the login email moves the employee record with it', function () {
    $tenant = Tenant::create(['name' => 'PT Coba Email', 'slug' => 'pt-coba-email']);
    $user = User::factory()->create(['tenant_id' => $tenant->id]);

    $employee = Employee::create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'full_name' => 'Nida Raihani',
        'employee_number' => 'EMP-PROFIL-2',
        'email' => $user->email,
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    $this->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => $employee->full_name,
            'email' => 'nida.baru@example.com',
        ])
        ->assertSessionHasNoErrors();

    expect($employee->refresh()->email)->toBe('nida.baru@example.com');
});

test('the account cannot be deleted from the settings screen', function () {
    // Self-service deletion was removed: the login is the key to a tenant\'s
    // payroll and approval history, and the employee row points at it.
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete('/settings/profile', ['password' => 'password'])
        // The URI still serves GET/PATCH, so a removed verb is a 405.
        ->assertMethodNotAllowed();

    expect($user->fresh())->not->toBeNull();
});

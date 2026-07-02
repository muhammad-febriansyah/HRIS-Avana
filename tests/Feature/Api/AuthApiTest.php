<?php

use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
});

it('exposes public app config without auth', function (): void {
    $this->getJson('/api/v1/app-config')
        ->assertOk()
        ->assertJsonStructure(['data' => ['site_name', 'tagline', 'logo_url', 'favicon_url', 'contact']]);
});

it('logs an employee in and returns access_token + user', function (): void {
    $response = $this->postJson('/api/v1/auth/login', [
        'email' => 'karyawan@avanahr.co.id',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'access_token',
            'token_type',
            'expires_in',
            'user' => ['id', 'name', 'email', 'roles', 'employee' => ['employee_no', 'full_name', 'employment']],
        ]);

    expect($response->json('user.roles'))->toContain('employee');
    expect($response->json('user.employee'))->not->toBeNull();
});

it('rejects a wrong password', function (): void {
    $this->postJson('/api/v1/auth/login', ['email' => 'karyawan@avanahr.co.id', 'password' => 'salah'])
        ->assertStatus(422);
});

it('returns the profile (enveloped) for a valid token', function (): void {
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'karyawan@avanahr.co.id', 'password' => 'password'])->json('access_token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'karyawan@avanahr.co.id');
});

it('blocks /auth/me without a token', function (): void {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

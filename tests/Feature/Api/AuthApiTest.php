<?php

use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
});

it('logs an employee in and returns a JWT with their profile', function (): void {
    $response = $this->postJson('/api/v1/login', [
        'email' => 'karyawan@avanahr.co.id',
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'expires_in',
            'user' => ['id', 'name', 'email', 'tenant_id', 'roles', 'employee' => ['id', 'employee_number', 'full_name']],
        ]);

    expect($response->json('token_type'))->toBe('Bearer');
    expect($response->json('user.employee'))->not->toBeNull();
    expect($response->json('user.roles'))->toContain('employee');
});

it('rejects a wrong password', function (): void {
    $this->postJson('/api/v1/login', [
        'email' => 'karyawan@avanahr.co.id',
        'password' => 'salah',
    ])->assertStatus(422);
});

it('returns the profile for a valid token', function (): void {
    $token = $this->postJson('/api/v1/login', [
        'email' => 'karyawan@avanahr.co.id',
        'password' => 'password',
    ])->json('token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('user.email', 'karyawan@avanahr.co.id');
});

it('blocks /me without a token', function (): void {
    $this->getJson('/api/v1/me')->assertUnauthorized();
});

it('invalidates the token on logout', function (): void {
    $token = $this->postJson('/api/v1/login', [
        'email' => 'karyawan@avanahr.co.id',
        'password' => 'password',
    ])->json('token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->postJson('/api/v1/logout')
        ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/me')
        ->assertUnauthorized();
});

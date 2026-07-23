<?php

use App\Models\Employee;
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
        'email' => 'bagus.p@nusantara.co.id',
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
    $this->postJson('/api/v1/auth/login', ['email' => 'bagus.p@nusantara.co.id', 'password' => 'salah'])
        ->assertStatus(422);
});

it('returns the profile (enveloped) for a valid token', function (): void {
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'bagus.p@nusantara.co.id', 'password' => 'password'])->json('access_token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.email', 'bagus.p@nusantara.co.id');
});

it('blocks /auth/me without a token', function (): void {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('marks the director (top approver) as a manager for the mobile Manager mode', function (): void {
    $token = $this->postJson('/api/v1/auth/login', ['email' => 'direktur@nusantara.co.id', 'password' => 'password'])->json('access_token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.employee.employment.position', 'Direktur Utama')
        ->assertJsonPath('data.employee.is_manager', true);
});

it('keeps a top approver a manager even with no direct reports', function (): void {
    // Strip the director's only report; the is_top_approver flag alone must
    // still unlock Manager mode.
    $directorId = Employee::where('employee_number', 'EMP-0000')->value('id');
    Employee::where('tenant_id', 1)->where('manager_id', $directorId)->update(['manager_id' => null]);

    $token = $this->postJson('/api/v1/auth/login', ['email' => 'direktur@nusantara.co.id', 'password' => 'password'])->json('access_token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.employee.is_manager', true);
});

it('does not mark a rank-and-file employee as a manager', function (): void {
    // Bagus is not a top approver; with his reports removed he has no elevated
    // access, so Manager mode stays hidden.
    $bagusId = Employee::where('employee_number', 'EMP-0002')->value('id');
    Employee::where('tenant_id', 1)->where('manager_id', $bagusId)->update(['manager_id' => null]);

    $token = $this->postJson('/api/v1/auth/login', ['email' => 'bagus.p@nusantara.co.id', 'password' => 'password'])->json('access_token');

    $this->withHeader('Authorization', 'Bearer '.$token)
        ->getJson('/api/v1/auth/me')
        ->assertOk()
        ->assertJsonPath('data.employee.is_manager', false);
});

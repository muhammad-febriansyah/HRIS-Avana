<?php

use App\Models\Company;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
});

/** Build an active employee-user under the given tenant. */
function makeMobileUser(?int $tenantId): User
{
    $user = User::create([
        'tenant_id' => $tenantId,
        'name' => 'Company Tester',
        'email' => 'company.tester@contoh.co',
        'password' => 'secret12345',
        'status' => 'active',
        'email_verified_at' => now(),
    ]);

    if ($tenantId !== null) {
        Employee::create([
            'tenant_id' => $tenantId,
            'employee_number' => 'CMP-001',
            'full_name' => 'Company Tester',
            'email' => 'company.tester@contoh.co',
            'employment_status' => 'permanent',
            'status' => 'active',
            'user_id' => $user->id,
        ]);
    }

    return $user;
}

function companyLogin(): TestResponse
{
    return test()->postJson('/api/v1/auth/login', [
        'email' => 'company.tester@contoh.co',
        'password' => 'secret12345',
    ]);
}

it('lets a user whose tenant has a company log in', function (): void {
    $tenantId = User::where('email', 'rina.a@nusantara.co.id')->value('tenant_id');
    makeMobileUser($tenantId);

    companyLogin()->assertOk();
});

it('denies login when the tenant has no company record', function (): void {
    $tenant = Tenant::create(['name' => 'Empty Co', 'slug' => 'empty-co', 'company_name' => 'Empty Co']);
    makeMobileUser($tenant->id);

    companyLogin()
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Akun belum terhubung ke perusahaan.');
});

it('denies login when the company is soft-deleted', function (): void {
    $tenant = Tenant::create(['name' => 'Gone Co', 'slug' => 'gone-co', 'company_name' => 'Gone Co']);
    Company::create(['tenant_id' => $tenant->id, 'name' => 'Gone Co'])->delete();
    makeMobileUser($tenant->id);

    companyLogin()
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Akun belum terhubung ke perusahaan.');
});

it('denies login when the user has no tenant at all', function (): void {
    makeMobileUser(null);

    companyLogin()
        ->assertStatus(422)
        ->assertJsonPath('errors.email.0', 'Akun belum terhubung ke perusahaan.');
});

<?php

use App\Models\Employee;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);

    $this->superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->hrAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('short-circuits queries under two characters', function (): void {
    actingAs($this->hrAdmin)
        ->getJson(route('avana.search', ['q' => 'a']))
        ->assertOk()
        ->assertExactJson(['employees' => [], 'tenants' => []]);
});

it('finds employees in the caller tenant', function (): void {
    $employee = Employee::where('tenant_id', $this->hrAdmin->tenant_id)
        ->where('status', 'active')
        ->firstOrFail();

    actingAs($this->hrAdmin)
        ->getJson(route('avana.search', ['q' => mb_substr($employee->full_name, 0, 4)]))
        ->assertOk()
        ->assertJsonFragment(['label' => $employee->full_name])
        ->assertJsonCount(0, 'tenants');
});

it('also returns matching tenants for a super admin', function (): void {
    actingAs($this->superAdmin)
        ->getJson(route('avana.search', ['q' => 'Nusantara']))
        ->assertOk()
        ->assertJsonPath('tenants.0.label', 'PT Nusantara Jaya');
});

it('does not expose tenants to a non super admin', function (): void {
    actingAs($this->hrAdmin)
        ->getJson(route('avana.search', ['q' => 'Nusantara']))
        ->assertOk()
        ->assertJsonCount(0, 'tenants');
});

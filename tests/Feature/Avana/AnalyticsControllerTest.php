<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('renders the analytics index with the expected props for an admin', function (): void {
    actingAs($this->admin)
        ->get(route('avana.analytics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/analytics/index', false)
            ->has('period')
            ->has('kpis')
            ->has('kpis.0', fn (Assert $kpi) => $kpi
                ->has('label')
                ->has('value')
                ->has('icon')
                ->has('color'))
            ->has('activeStatus')
            ->has('byDepartment')
            ->has('byEmploymentStatus')
            ->has('byGender')
            ->has('attendance')
            ->has('payroll'));
});

it('aggregates active vs inactive headcount from tenant employees', function (): void {
    actingAs($this->admin)
        ->get(route('avana.analytics'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('activeStatus.0.label', 'Aktif')
            ->where('activeStatus.1.label', 'Nonaktif'));
});

it('forbids a plain employee from viewing analytics', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.analytics'))
        ->assertForbidden();
});

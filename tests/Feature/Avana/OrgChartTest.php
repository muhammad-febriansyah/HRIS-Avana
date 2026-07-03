<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('renders the org chart with hierarchy nodes', function (): void {
    actingAs($this->admin)
        ->get(route('avana.organisasi'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('avana/employees/org-chart')
            ->has('nodes.0', fn (AssertableInertia $node) => $node
                ->has('id')
                ->has('name')
                ->has('employee_number')
                ->has('email')
                ->has('position')
                ->has('department')
                ->has('branch')
                ->has('join_date')
                ->has('manager_id')
                ->has('manager_name')));
});

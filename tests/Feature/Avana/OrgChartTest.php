<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Storage;
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
                ->has('route_key')
                ->has('name')
                ->has('employee_number')
                ->has('email')
                ->has('phone')
                ->has('photo_url')
                ->has('position')
                ->has('department')
                ->has('branch')
                ->has('join_date')
                ->has('manager_id')
                ->has('manager_name')
                ->has('is_top_approver')));
});

it('includes the employee photo URL in org chart nodes', function (): void {
    Storage::fake('local');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();
    $photoPath = "employee-photos/{$this->tenant->id}/org-chart-avatar.jpg";

    Storage::disk('local')->put($photoPath, 'fake-image');
    $employee->update(['photo_path' => $photoPath]);

    actingAs($this->admin)
        ->get(route('avana.organisasi'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('nodes', function ($nodes) use ($employee): bool {
                $row = collect($nodes)->firstWhere('id', $employee->id);

                return is_array($row)
                    && is_string($row['photo_url'] ?? null)
                    && str_contains($row['photo_url'], '/berkas/employee-photos/');
            })
            ->etc());
});

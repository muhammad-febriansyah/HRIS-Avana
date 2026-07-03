<?php

use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the admin dashboard lists only employees whose birthday is today', function () {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $tenant = Tenant::findOrFail($admin->tenant_id);

    // Clear the demo birthdays so this test controls exactly who celebrates today.
    Employee::forTenant($tenant->id)->update([
        'birth_date' => Carbon::today()->addMonth()->subYears(25)->toDateString(),
    ]);

    $employees = Employee::forTenant($tenant->id)->orderBy('id')->take(2)->get();

    $celebrant = $employees[0];
    $celebrant->update([
        'status' => 'active',
        'birth_date' => Carbon::today()->subYears(28)->toDateString(),
    ]);

    $notToday = $employees[1];
    $notToday->update([
        'status' => 'active',
        'birth_date' => Carbon::today()->addDay()->subYears(30)->toDateString(),
    ]);

    actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard')
            ->has('birthdays', 1, fn (Assert $row) => $row
                ->where('id', $celebrant->id)
                ->where('name', $celebrant->full_name)
                ->where('age', 28)
                ->has('ini')
                ->has('avBg')
                ->has('role')));
});

test('super admins get the platform dashboard instead of the tenant one', function () {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $superAdmin = User::whereHas('roles', fn ($query) => $query->where('code', 'super_admin'))
        ->firstOrFail();

    actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard-admin')
            ->has('kpis', 4)
            ->has('tenantGrowth.labels', 6)
            ->has('tenantGrowth.values', 6)
            ->has('revenueTrend.values', 6)
            ->has('recentTenants')
            ->has('overdueInvoices')
            ->has('quotaAlerts'));
});

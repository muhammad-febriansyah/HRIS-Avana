<?php

use App\Models\Notification;
use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

/**
 * A live tenant whose subscription ends `$daysLeft` days from today (negative
 * for one that already lapsed).
 */
function expiringTenant(int $daysLeft, string $status = 'active'): Tenant
{
    return Tenant::create([
        'name' => 'Klien '.fake()->unique()->company(),
        'slug' => Str::slug('klien-'.fake()->unique()->numberBetween(1, 99999)),
        'status' => $status,
        'start_date' => now()->subMonths(6)->toDateString(),
        'end_date' => now()->addDays($daysLeft)->toDateString(),
    ]);
}

function tenantAdmin(Tenant $tenant): User
{
    $role = Role::firstOrCreate(
        ['code' => 'admin_tenant_hr', 'tenant_id' => $tenant->id],
        ['name' => 'Admin Tenant / HR'],
    );

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->roles()->attach($role->id);

    return $user;
}

function employeeUser(Tenant $tenant): User
{
    $role = Role::firstOrCreate(
        ['code' => 'karyawan', 'tenant_id' => $tenant->id],
        ['name' => 'Karyawan'],
    );

    $user = User::factory()->create(['tenant_id' => $tenant->id]);
    $user->roles()->attach($role->id);

    return $user;
}

it('reminds the tenant admins on a countdown milestone', function (): void {
    $tenant = expiringTenant(7);
    $admin = tenantAdmin($tenant);

    artisan('avana:remind-billing')->assertSuccessful();

    $note = Notification::where('user_id', $admin->id)
        ->where('type', 'subscription_expiring')
        ->first();

    expect($note)->not->toBeNull();
    expect($note->title)->toBe('Langganan akan berakhir');
    expect($note->body)->toContain('berakhir 7 hari lagi');
    expect($note->data['days_left'])->toBe(7);
    expect((int) $note->tenant_id)->toBe($tenant->id);
});

it('sends nothing on a day that is not a milestone', function (): void {
    $tenant = expiringTenant(20);
    $admin = tenantAdmin($tenant);

    artisan('avana:remind-billing')->assertSuccessful();

    expect(Notification::where('user_id', $admin->id)->where('type', 'subscription_expiring')->exists())
        ->toBeFalse();
});

it('does not repeat the same milestone on a second run', function (): void {
    $tenant = expiringTenant(3);
    $admin = tenantAdmin($tenant);

    artisan('avana:remind-billing')->assertSuccessful();
    artisan('avana:remind-billing')->assertSuccessful();

    expect(Notification::where('user_id', $admin->id)->where('type', 'subscription_expiring')->count())
        ->toBe(1);
});

it('starts a fresh reminder series once the subscription is extended', function (): void {
    $tenant = expiringTenant(3);
    $admin = tenantAdmin($tenant);

    artisan('avana:remind-billing')->assertSuccessful();

    // A year on and renewed: the same "3 days left" milestone must fire again,
    // because the milestone key carries the end date it belongs to.
    $this->travel(1)->years();
    $tenant->update(['end_date' => now()->addDays(3)->toDateString()]);

    artisan('avana:remind-billing')->assertSuccessful();

    expect(Notification::where('user_id', $admin->id)->where('type', 'subscription_expiring')->count())
        ->toBe(2);
});

it('warns once after the subscription has already lapsed', function (): void {
    $tenant = expiringTenant(-4);
    $admin = tenantAdmin($tenant);

    artisan('avana:remind-billing')->assertSuccessful();
    artisan('avana:remind-billing')->assertSuccessful();

    $notes = Notification::where('user_id', $admin->id)
        ->where('type', 'subscription_expiring')
        ->get();

    expect($notes)->toHaveCount(1);
    expect($notes->first()->title)->toBe('Langganan telah berakhir');
    expect($notes->first()->body)->toContain('sudah berakhir');
});

it('prefers the active subscription end date over the tenant field', function (): void {
    $tenant = expiringTenant(90);
    $admin = tenantAdmin($tenant);
    $package = Package::create([
        'name' => 'Pro',
        'code' => 'pro-'.fake()->unique()->numberBetween(1, 9999),
        'price' => 1_000_000,
        'billing_cycle' => 'monthly',
    ]);

    Subscription::create([
        'tenant_id' => $tenant->id,
        'package_id' => $package->id,
        'price' => 1_000_000,
        'billing_cycle' => 'monthly',
        'status' => 'active',
        'start_date' => now()->subMonth()->toDateString(),
        'end_date' => now()->addDays(14)->toDateString(),
    ]);

    artisan('avana:remind-billing')->assertSuccessful();

    $note = Notification::where('user_id', $admin->id)
        ->where('type', 'subscription_expiring')
        ->first();

    expect($note)->not->toBeNull();
    expect($note->data['days_left'])->toBe(14);
    expect($note->body)->toContain('Pro');
});

it('shares the expiry banner with a tenant admin inside the warning window', function (): void {
    $this->withoutVite();
    $tenant = expiringTenant(5);
    $admin = tenantAdmin($tenant);

    actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscription.days_left', 5)
            ->where('subscription.level', 'critical')
            ->has('subscription.end_date_label')
            ->etc());
});

it('keeps the banner away while the subscription is comfortably active', function (): void {
    $this->withoutVite();
    $tenant = expiringTenant(120);
    $admin = tenantAdmin($tenant);

    actingAs($admin)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('subscription', null)->etc());
});

it('keeps the banner away from an ESS employee', function (): void {
    $this->withoutVite();
    $tenant = expiringTenant(2);
    $employee = employeeUser($tenant);

    actingAs($employee)
        ->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('subscription', null)->etc());
});

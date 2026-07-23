<?php

use App\Models\Notification;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function lifecycleTenant(string $status = 'trial'): Tenant
{
    return Tenant::create([
        'name' => 'Klien '.fake()->unique()->company(),
        'slug' => Str::slug('klien-'.fake()->unique()->numberBetween(1, 99999)),
        'status' => $status,
    ]);
}

function lifecycleSuper(int $tenantId): User
{
    $role = Role::firstOrCreate(['code' => 'super_admin'], ['name' => 'Super Admin', 'is_system' => true]);
    $user = User::factory()->create(['tenant_id' => $tenantId]);
    $user->roles()->attach($role->id);

    return $user;
}

it('notifies super admins when a new tenant is created', function () {
    $home = lifecycleTenant();
    $admin = lifecycleSuper($home->id);

    $created = lifecycleTenant();

    $note = Notification::where('user_id', $admin->id)
        ->where('type', 'tenant')
        ->where('data->tenant_ref', $created->id)
        ->first();

    expect($note)->not->toBeNull()
        ->and($note->title)->toBe('Klien baru')
        ->and($note->data['event'])->toBe('created')
        ->and($note->tenant_id)->toBe($created->id);
});

it('notifies super admins when a tenant converts from trial to active', function () {
    $home = lifecycleTenant();
    $admin = lifecycleSuper($home->id);
    $tenant = lifecycleTenant('trial');

    $tenant->update(['status' => 'active']);

    $note = Notification::where('user_id', $admin->id)
        ->where('data->tenant_ref', $tenant->id)
        ->where('data->event', 'activated')
        ->first();

    expect($note)->not->toBeNull()
        ->and($note->title)->toBe('Konversi trial');
});

it('does not fire a conversion alert when a non-trial tenant becomes active', function () {
    $home = lifecycleTenant();
    lifecycleSuper($home->id);
    $tenant = lifecycleTenant('suspended');

    $tenant->update(['status' => 'active']);

    expect(Notification::where('data->tenant_ref', $tenant->id)->where('data->event', 'activated')->count())->toBe(0);
});

it('notifies super admins when a tenant is suspended', function () {
    $home = lifecycleTenant();
    $admin = lifecycleSuper($home->id);
    $tenant = lifecycleTenant('active');

    $tenant->update(['status' => 'suspended']);

    $note = Notification::where('user_id', $admin->id)
        ->where('data->tenant_ref', $tenant->id)
        ->where('data->event', 'suspended')
        ->first();

    expect($note)->not->toBeNull()
        ->and($note->title)->toBe('Klien disuspend');
});

it('notifies super admins when a tenant is deactivated', function () {
    $home = lifecycleTenant();
    $admin = lifecycleSuper($home->id);
    $tenant = lifecycleTenant('active');

    $tenant->update(['status' => 'inactive']);

    $note = Notification::where('user_id', $admin->id)
        ->where('data->tenant_ref', $tenant->id)
        ->where('data->event', 'inactive')
        ->first();

    expect($note)->not->toBeNull()
        ->and($note->title)->toBe('Klien nonaktif');
});

it('does not notify on a tenant status change with no lifecycle meaning', function () {
    $home = lifecycleTenant();
    lifecycleSuper($home->id);
    $tenant = lifecycleTenant('active');

    $tenant->update(['status' => 'trial']);

    expect(Notification::where('data->tenant_ref', $tenant->id)
        ->whereIn('data->event', ['activated', 'suspended', 'inactive'])
        ->count())->toBe(0);
});

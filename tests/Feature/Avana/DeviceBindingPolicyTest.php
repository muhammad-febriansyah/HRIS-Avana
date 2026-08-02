<?php

use App\Models\AttendancePolicy;
use App\Models\Employee;
use App\Models\Tenant;
use App\Models\User;
use App\Models\UserDevice;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // User management sits behind its own permission; the super admin is the
    // one login that reaches both screens.
    $this->owner = User::where('email', 'superadmin@avanahr.id')->firstOrFail();

    $this->employee = Employee::forTenant($this->tenant->id)
        ->whereNotNull('user_id')
        ->where('user_id', '!=', $this->admin->id)
        ->firstOrFail();

    $this->staff = User::findOrFail($this->employee->user_id);

    $this->device = UserDevice::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->staff->id,
        'device_id' => 'uji-perangkat-1',
        'device_name' => 'Pixel Uji',
        'status' => 'active',
        'bound_at' => now(),
    ]);

    $this->setBinding = function (bool $on): void {
        AttendancePolicy::updateOrCreate(
            ['tenant_id' => $this->tenant->id],
            ['device_binding_enabled' => $on],
        );
    };
});

it('offers the device reset while one phone per account is on', function (): void {
    ($this->setBinding)(true);

    actingAs($this->admin)
        ->get(route('avana.employees.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('device_binding_enabled', true));
});

it('withdraws the device reset once one phone per account is off', function (): void {
    ($this->setBinding)(false);

    actingAs($this->admin)
        ->get(route('avana.employees.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('device_binding_enabled', false));
});

it('tells the user list the same thing', function (): void {
    ($this->setBinding)(false);

    actingAs($this->owner)
        ->get(route('avana.pengguna'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('device_binding_enabled', false));
});

it('refuses to reset an employee device while the policy is off', function (): void {
    ($this->setBinding)(false);

    actingAs($this->admin)
        ->post(route('avana.employees.reset-device', $this->employee))
        ->assertSessionHas('error');

    // Nothing released, and no session revoked on the back of a hidden button.
    expect($this->device->fresh()->status)->toBe('active');
    expect((int) $this->staff->fresh()->token_version)->toBe((int) $this->staff->token_version);
});

it('refuses to reset a user device while the policy is off', function (): void {
    ($this->setBinding)(false);

    actingAs($this->owner)
        ->post(route('avana.pengguna.reset-device', $this->staff))
        ->assertSessionHas('error');

    expect($this->device->fresh()->status)->toBe('active');
});

it('still resets while the policy is on', function (): void {
    ($this->setBinding)(true);

    actingAs($this->admin)
        ->post(route('avana.employees.reset-device', $this->employee))
        ->assertSessionHas('success');

    expect($this->device->fresh()->status)->toBe('reset');
});

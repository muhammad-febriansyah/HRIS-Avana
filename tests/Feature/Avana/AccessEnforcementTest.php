<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Access;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->tenant = Tenant::whereNotNull('id')->firstOrFail();

    // A user whose role holds only asset.view (can list, cannot create).
    $role = Role::create(['tenant_id' => $this->tenant->id, 'code' => 'kill-switch-viewer', 'name' => 'Viewer', 'is_system' => false]);
    $role->permissions()->syncWithoutDetaching(Permission::where('code', 'asset.view')->pluck('id'));

    $this->viewer = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->viewer->roles()->sync([$role->id]);
});

afterEach(function (): void {
    Access::setEnforced(true);
});

it('enforces action permissions while the kill-switch is on', function (): void {
    expect(Access::enforced())->toBeTrue();

    actingAs($this->viewer)->get(route('avana.aset.create'))->assertForbidden();
});

it('lets every action through once the kill-switch is off', function (): void {
    Access::setEnforced(false);

    expect($this->viewer->fresh()->hasPermissionTo('asset.create'))->toBeTrue();

    actingAs($this->viewer)->get(route('avana.aset.create'))->assertOk();
});

it('re-enables enforcement when switched back on', function (): void {
    Access::setEnforced(false);
    Access::setEnforced(true);

    actingAs($this->viewer)->get(route('avana.aset.create'))->assertForbidden();
});

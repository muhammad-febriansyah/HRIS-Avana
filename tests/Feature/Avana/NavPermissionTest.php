<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use App\Support\AvanaNav;
use Database\Seeders\AvanaDemoSeeder;

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->tenant = Tenant::where('code', '!=', '')->firstOrFail();
    $this->manager = Role::where('tenant_id', $this->tenant->id)->where('code', 'manager')->firstOrFail();
});

/** Flatten every leaf id visible to a user's nav. */
function navLeafIds(User $user): array
{
    $ids = [];
    foreach (AvanaNav::forUser($user) as $group) {
        foreach ($group['items'] as $item) {
            if (isset($item['children'])) {
                foreach ($item['children'] as $child) {
                    $ids[] = $child['id'];
                }
            } else {
                $ids[] = $item['id'];
            }
        }
    }

    return $ids;
}

function managerUser(object $ctx): User
{
    $user = User::factory()->create(['tenant_id' => $ctx->tenant->id]);
    $user->roles()->sync([$ctx->manager->id]);

    return $user->fresh(['roles.permissions']);
}

it('hides a permission-gated menu from a role without the module', function (): void {
    $user = managerUser($this);

    expect(navLeafIds($user))->not->toContain('crm');
});

it('shows the menu once the role is granted the module permission', function (): void {
    $crm = Permission::firstOrCreate(['code' => 'crm.view'], ['module' => 'crm', 'action' => 'view', 'name' => 'crm.view']);
    $this->manager->permissions()->syncWithoutDetaching([$crm->id]);

    $user = managerUser($this);

    expect(navLeafIds($user))->toContain('crm');
});

it('always shows every menu to an HR admin and hides platform items', function (): void {
    $admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $ids = navLeafIds($admin);

    // HR admin holds all non-tenant modules → sees enterprise menus…
    expect($ids)->toContain('crm');
    expect($ids)->toContain('kinerja');
    expect($ids)->toContain('jurnal');
    // …but platform (super-admin only) stays hidden.
    expect($ids)->not->toContain('klien');
    expect($ids)->not->toContain('billing');
});

it('shows platform menus to a super admin', function (): void {
    $superadmin = User::where('email', 'superadmin@avanahr.co.id')->firstOrFail();
    $ids = navLeafIds($superadmin);

    expect($ids)->toContain('klien');
    expect($ids)->toContain('billing');
});

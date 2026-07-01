<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->tenant = Tenant::where('code', '!=', '')->firstOrFail();

    $this->superadmin = User::where('email', 'superadmin@avanahr.co.id')->firstOrFail();
    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();

    $this->manager = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->manager->roles()->sync([
        Role::where('tenant_id', $this->tenant->id)->where('code', 'manager')->firstOrFail()->id,
    ]);
});

it('blocks a manager from a page whose module they lack', function (): void {
    actingAs($this->manager)->get('/avana/crm')->assertForbidden();
    actingAs($this->manager)->get('/avana/jurnal')->assertForbidden();
    actingAs($this->manager)->get('/avana/kinerja')->assertForbidden();
});

it('allows a manager into the approval center (team module)', function (): void {
    actingAs($this->manager)->get('/avana/approval')->assertOk();
});

it('lets an HR admin reach enterprise pages but not platform pages', function (): void {
    actingAs($this->admin)->get('/avana/crm')->assertOk();
    actingAs($this->admin)->get('/avana/kinerja')->assertOk();
    actingAs($this->admin)->get('/avana/klien')->assertForbidden();
    actingAs($this->admin)->get('/avana/billing')->assertForbidden();
});

it('lets a super admin reach platform pages', function (): void {
    actingAs($this->superadmin)->get('/avana/klien')->assertOk();
    actingAs($this->superadmin)->get('/avana/billing')->assertOk();
});

it('enforces the gate on real sub-routes by prefix', function (): void {
    // /avana/kinerja/create inherits the performance gate (not a menu leaf).
    actingAs($this->manager)->get('/avana/kinerja/create')->assertForbidden();
    actingAs($this->admin)->get('/avana/kinerja/create')->assertOk();
});

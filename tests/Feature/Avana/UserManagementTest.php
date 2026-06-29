<?php

use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
});

it('renders the paginated pengguna index with the expected props', function (): void {
    actingAs($this->admin)
        ->get(route('avana.pengguna'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/pengguna', false)
            ->has('users.data')
            ->has('users.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('name')
                ->has('email')
                ->has('phone')
                ->has('status')
                ->has('roles')
                ->has('initials')
                ->has('avatar_color')
                ->etc())
            ->has('roles')
            ->has('filters'));
});

it('only lists users that belong to the current tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    User::factory()->create(['tenant_id' => $otherTenant->id, 'email' => 'foreign@lain.test']);

    $tenantTotal = User::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.pengguna'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('users.meta.total', $tenantTotal));
});

it('excludes the super_admin role from the assignable roles list', function (): void {
    actingAs($this->admin)
        ->get(route('avana.pengguna'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles', fn ($roles) => collect($roles)->pluck('code')->doesntContain('super_admin')));
});

it('creates a user with roles scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.pengguna.store'), [
            'name' => 'Budi Pengguna',
            'email' => 'budi@pengguna.test',
            'phone' => '08123456789',
            'password' => 'rahasia123',
            'status' => 'active',
            'role_ids' => [$this->employeeRole->id],
        ])
        ->assertRedirect(route('avana.pengguna'))
        ->assertSessionHas('success');

    $user = User::where('email', 'budi@pengguna.test')->firstOrFail();

    expect($user->tenant_id)->toBe($this->tenant->id);
    expect($user->status)->toBe('active');
    expect($user->roles->pluck('id')->all())->toBe([$this->employeeRole->id]);
    expect(Hash::check('rahasia123', $user->password))->toBeTrue();
});

it('validates required fields and unique email on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.pengguna.store'), [
            'name' => '',
            'email' => '',
            'password' => '',
        ])
        ->assertSessionHasErrors(['name', 'email', 'password']);

    actingAs($this->admin)
        ->post(route('avana.pengguna.store'), [
            'name' => 'Duplikat',
            'email' => $this->admin->email,
            'password' => 'rahasia123',
            'status' => 'active',
        ])
        ->assertSessionHasErrors(['email']);
});

it('updates an existing user fields and roles', function (): void {
    $target = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

    actingAs($this->admin)
        ->put(route('avana.pengguna.update', $target), [
            'name' => 'Nama Diperbarui',
            'email' => 'diperbarui@pengguna.test',
            'phone' => '08000000000',
            'password' => '',
            'status' => 'inactive',
            'role_ids' => [$this->employeeRole->id],
        ])
        ->assertRedirect(route('avana.pengguna'))
        ->assertSessionHas('success');

    $target->refresh();

    expect($target->name)->toBe('Nama Diperbarui');
    expect($target->email)->toBe('diperbarui@pengguna.test');
    expect($target->status)->toBe('inactive');
    expect($target->roles->pluck('id')->all())->toBe([$this->employeeRole->id]);
});

it('keeps the password unchanged when left blank on update', function (): void {
    $target = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'password' => Hash::make('original-secret'),
    ]);
    $originalHash = $target->password;

    actingAs($this->admin)
        ->put(route('avana.pengguna.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => '',
            'status' => 'active',
        ])
        ->assertSessionHas('success');

    expect($target->fresh()->password)->toBe($originalHash);
});

it('toggles a user status between active and inactive', function (): void {
    $target = User::factory()->create(['tenant_id' => $this->tenant->id, 'status' => 'active']);

    actingAs($this->admin)
        ->post(route('avana.pengguna.toggle', $target))
        ->assertSessionHas('success');

    expect($target->fresh()->status)->toBe('inactive');

    actingAs($this->admin)
        ->post(route('avana.pengguna.toggle', $target))
        ->assertSessionHas('success');

    expect($target->fresh()->status)->toBe('active');
});

it('forbids deleting your own account', function (): void {
    actingAs($this->admin)
        ->delete(route('avana.pengguna.destroy', $this->admin))
        ->assertForbidden();

    expect(User::find($this->admin->id))->not->toBeNull();
});

it('forbids disabling your own account', function (): void {
    actingAs($this->admin)
        ->post(route('avana.pengguna.toggle', $this->admin))
        ->assertForbidden();

    expect($this->admin->fresh()->status)->toBe('active');
});

it('deletes another user on destroy', function (): void {
    $target = User::factory()->create(['tenant_id' => $this->tenant->id]);

    actingAs($this->admin)
        ->delete(route('avana.pengguna.destroy', $target))
        ->assertSessionHas('success');

    expect(User::find($target->id))->toBeNull();
});

it('returns 404 when updating a user from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = User::factory()->create(['tenant_id' => $otherTenant->id]);

    actingAs($this->admin)
        ->put(route('avana.pengguna.update', $foreign), [
            'name' => 'Hack',
            'email' => 'hack@asing.test',
            'status' => 'active',
        ])
        ->assertNotFound();
});

it('returns 404 when deleting a user from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = User::factory()->create(['tenant_id' => $otherTenant->id]);

    actingAs($this->admin)
        ->delete(route('avana.pengguna.destroy', $foreign))
        ->assertNotFound();

    expect(User::find($foreign->id))->not->toBeNull();
});

it('forbids users without user permissions from managing users', function (): void {
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$this->employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.pengguna'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.pengguna.store'), [
            'name' => 'Tidak Boleh',
            'email' => 'tidakboleh@pengguna.test',
            'password' => 'rahasia123',
            'status' => 'active',
        ])
        ->assertForbidden();
});

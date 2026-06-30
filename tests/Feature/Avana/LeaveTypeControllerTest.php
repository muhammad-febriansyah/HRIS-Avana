<?php

use App\Models\LeaveType;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/**
 * Create an employee-role user for the seeded tenant.
 */
function makeEmployeeUser(int $tenantId): User
{
    $employeeRole = Role::where('tenant_id', $tenantId)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $tenantId]);
    $staff->roles()->sync([$employeeRole->id]);

    return $staff;
}

it('renders the jenis cuti index with the expected props', function (): void {
    actingAs($this->admin)
        ->get(route('avana.cuti.jenis'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/jenis-cuti/index', false)
            ->has('leaveTypes')
            ->has('leaveTypes.0', fn (Assert $row) => $row
                ->has('id')
                ->has('code')
                ->has('name')
                ->has('default_quota')
                ->has('allow_negative')
                ->has('requires_attachment')
                ->has('status')
                ->has('usage')
                ->etc()));
});

it('only lists leave types that belong to the current tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    LeaveType::create([
        'tenant_id' => $otherTenant->id,
        'code' => 'ASING',
        'name' => 'Cuti Asing',
        'default_quota' => 5,
        'status' => 'active',
    ]);

    $tenantTotal = LeaveType::forTenant($this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.cuti.jenis'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->has('leaveTypes', $tenantTotal));
});

it('creates a leave type scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.cuti.jenis.store'), [
            'code' => 'MELAHIRKAN',
            'name' => 'Cuti Melahirkan',
            'default_quota' => 90,
            'allow_negative' => false,
            'requires_attachment' => true,
            'status' => 'active',
        ])
        ->assertRedirect(route('avana.cuti.jenis'))
        ->assertSessionHas('success');

    $leaveType = LeaveType::where('tenant_id', $this->tenant->id)
        ->where('code', 'MELAHIRKAN')
        ->firstOrFail();

    expect($leaveType->name)->toBe('Cuti Melahirkan');
    expect($leaveType->default_quota)->toBe(90);
    expect($leaveType->requires_attachment)->toBeTrue();
    expect($leaveType->allow_negative)->toBeFalse();
    expect($leaveType->status)->toBe('active');
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.cuti.jenis.store'), [
            'code' => '',
            'name' => '',
            'default_quota' => '',
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['code', 'name', 'default_quota', 'status']);
});

it('rejects a duplicate code within the same tenant', function (): void {
    $existing = LeaveType::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.cuti.jenis.store'), [
            'code' => $existing->code,
            'name' => 'Duplikat',
            'default_quota' => 10,
            'status' => 'active',
        ])
        ->assertSessionHasErrors('code');
});

it('updates a leave type belonging to the tenant', function (): void {
    $leaveType = LeaveType::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->put(route('avana.cuti.jenis.update', $leaveType), [
            'code' => $leaveType->code,
            'name' => 'Nama Diperbarui',
            'default_quota' => 20,
            'allow_negative' => true,
            'requires_attachment' => false,
            'status' => 'inactive',
        ])
        ->assertRedirect(route('avana.cuti.jenis'))
        ->assertSessionHas('success');

    $leaveType->refresh();
    expect($leaveType->name)->toBe('Nama Diperbarui');
    expect($leaveType->default_quota)->toBe(20);
    expect($leaveType->allow_negative)->toBeTrue();
    expect($leaveType->status)->toBe('inactive');
});

it('soft deletes a leave type belonging to the tenant', function (): void {
    $leaveType = LeaveType::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->delete(route('avana.cuti.jenis.destroy', $leaveType))
        ->assertRedirect(route('avana.cuti.jenis'))
        ->assertSessionHas('success');

    expect(LeaveType::where('id', $leaveType->id)->exists())->toBeFalse();
    expect(LeaveType::withTrashed()->where('id', $leaveType->id)->exists())->toBeTrue();
});

it('returns 404 when updating a leave type from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = LeaveType::create([
        'tenant_id' => $otherTenant->id,
        'code' => 'XXX',
        'name' => 'Cuti Asing',
        'default_quota' => 3,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->put(route('avana.cuti.jenis.update', $foreign), [
            'code' => 'XXX',
            'name' => 'Diretas',
            'default_quota' => 3,
            'status' => 'active',
        ])
        ->assertNotFound();

    expect($foreign->fresh()->name)->toBe('Cuti Asing');
});

it('returns 404 when deleting a leave type from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = LeaveType::create([
        'tenant_id' => $otherTenant->id,
        'code' => 'YYY',
        'name' => 'Cuti Asing',
        'default_quota' => 3,
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.cuti.jenis.destroy', $foreign))
        ->assertNotFound();

    expect(LeaveType::where('id', $foreign->id)->exists())->toBeTrue();
});

it('forbids users without leave permissions from listing leave types', function (): void {
    $staff = makeEmployeeUser($this->tenant->id);

    actingAs($staff)
        ->get(route('avana.cuti.jenis'))
        ->assertForbidden();
});

it('forbids users without leave permissions from creating leave types', function (): void {
    $staff = makeEmployeeUser($this->tenant->id);

    actingAs($staff)
        ->post(route('avana.cuti.jenis.store'), [
            'code' => 'NEW',
            'name' => 'Cuti Baru',
            'default_quota' => 5,
            'status' => 'active',
        ])
        ->assertForbidden();
});

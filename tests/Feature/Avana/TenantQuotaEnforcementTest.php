<?php

use App\Models\Applicant;
use App\Models\Branch;
use App\Models\Employee;
use App\Models\JobPosting;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantQuota;
use Database\Seeders\AvanaDemoSeeder;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

/** Pin the tenant's ceiling to exactly what it already uses, leaving no room. */
function fillQuota(Tenant $tenant, string $resource, string $column): void
{
    $tenant->forceFill([$column => TenantQuota::used($tenant, $resource)])->save();
}

it('blocks a new branch once the package limit is reached', function (): void {
    fillQuota($this->tenant, 'branches', 'max_branches');

    $before = Branch::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->from(route('avana.perusahaan'))
        ->post(route('avana.perusahaan.store', 'branches'), [
            'code' => 'CB-999',
            'name' => 'Cabang Kelebihan',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('name');

    expect(Branch::where('tenant_id', $this->tenant->id)->count())->toBe($before);
});

it('allows a new branch while the package still has room', function (): void {
    $this->tenant->forceFill([
        'max_branches' => TenantQuota::used($this->tenant, 'branches') + 1,
    ])->save();

    actingAs($this->admin)
        ->from(route('avana.perusahaan'))
        ->post(route('avana.perusahaan.store', 'branches'), [
            'code' => 'CB-777',
            'name' => 'Cabang Muat',
            'status' => 'active',
        ])
        ->assertSessionHasNoErrors();

    expect(Branch::where('tenant_id', $this->tenant->id)->where('code', 'CB-777')->exists())->toBeTrue();
});

it('blocks a new employee once the package limit is reached', function (): void {
    fillQuota($this->tenant, 'employees', 'max_employees');

    $before = Employee::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->from(route('avana.employees.index'))
        ->post(route('avana.employees.store'), [
            'full_name' => 'Karyawan Kelebihan',
            'employment_status' => 'permanent',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('full_name');

    expect(Employee::where('tenant_id', $this->tenant->id)->count())->toBe($before);
});

it('blocks a bulk import that would overshoot the employee limit', function (): void {
    $this->tenant->forceFill([
        'max_employees' => TenantQuota::used($this->tenant, 'employees') + 1,
    ])->save();

    $before = Employee::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->from(route('avana.employees.bulk'))
        ->post(route('avana.employees.bulk.store'), [
            'employees' => [
                ['full_name' => 'Batch Satu', 'employment_status' => 'permanent', 'status' => 'active'],
                ['full_name' => 'Batch Dua', 'employment_status' => 'permanent', 'status' => 'active'],
            ],
        ])
        ->assertSessionHasErrors('employees');

    expect(Employee::where('tenant_id', $this->tenant->id)->count())->toBe($before);
});

it('blocks activating a hired candidate once the employee limit is reached', function (): void {
    fillQuota($this->tenant, 'employees', 'max_employees');

    $posting = JobPosting::factory()->create(['tenant_id' => $this->tenant->id]);
    $applicant = Applicant::factory()->create([
        'tenant_id' => $this->tenant->id,
        'job_posting_id' => $posting->id,
        'stage' => 'hired',
        'offer_status' => 'accepted',
    ]);

    $before = Employee::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->from(route('avana.rekrutmen'))
        ->post(route('avana.rekrutmen.pelamar.activate', $applicant), [])
        ->assertSessionHasErrors('applicant');

    expect(Employee::where('tenant_id', $this->tenant->id)->count())->toBe($before);
});

it('blocks a new staff login once the package limit is reached', function (): void {
    fillQuota($this->tenant, 'users', 'max_users');

    // Creating logins is a super-admin action here, and the super admin sits in
    // this same tenant — so its package meters them too.
    actingAs(User::where('email', 'superadmin@avanahr.id')->firstOrFail())
        ->from(route('avana.pengguna'))
        ->post(route('avana.pengguna.store'), [
            'name' => 'Pengguna Kelebihan',
            'email' => 'lebih@nusantara.co.id',
            'password' => 'rahasia123',
            'status' => 'active',
        ])
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'lebih@nusantara.co.id')->exists())->toBeFalse();
});

it('does not spend the user quota on an employee ESS login', function (): void {
    $staffOnly = TenantQuota::used($this->tenant, 'users');

    Employee::create([
        'tenant_id' => $this->tenant->id,
        'employee_number' => 'EMP-QUOTA-1',
        'full_name' => 'Karyawan ESS',
        'employment_status' => 'permanent',
        'status' => 'active',
        'user_id' => User::create([
            'tenant_id' => $this->tenant->id,
            'name' => 'Karyawan ESS',
            'email' => 'ess.quota@nusantara.co.id',
            'password' => 'rahasia123',
            'status' => 'active',
        ])->id,
    ]);

    expect(TenantQuota::used($this->tenant->fresh(), 'users'))->toBe($staffOnly);
});

it('lifts the block as soon as a super admin raises the branch limit', function (): void {
    fillQuota($this->tenant, 'branches', 'max_branches');

    $superAdmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $payload = [
        'code' => 'CB-555',
        'name' => 'Cabang Setelah Naik Paket',
        'status' => 'active',
    ];

    actingAs($this->admin)
        ->from(route('avana.perusahaan'))
        ->post(route('avana.perusahaan.store', 'branches'), $payload)
        ->assertSessionHasErrors('name');

    // The super admin grants a bigger allowance from the klien edit form, which
    // posts the whole record — the subscription window included, since `update()`
    // overwrites every column it is given.
    actingAs($superAdmin)
        ->put(route('avana.klien.update', $this->tenant), [
            'name' => $this->tenant->name,
            'company_name' => $this->tenant->company_name,
            'slug' => $this->tenant->slug,
            'package_id' => $this->tenant->package_id,
            'status' => $this->tenant->status,
            'billing_status' => $this->tenant->billing_status,
            'start_date' => $this->tenant->start_date?->toDateString(),
            'end_date' => $this->tenant->end_date?->toDateString(),
            'max_users' => $this->tenant->max_users,
            'max_employees' => $this->tenant->max_employees,
            'max_branches' => TenantQuota::used($this->tenant, 'branches') + 15,
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    expect($this->tenant->fresh()->max_branches)->toBeGreaterThan(3);

    // A fresh instance, because `actingAs` keeps the model (and the tenant
    // relation it already loaded) alive across requests where a real session
    // would resolve the user again.
    //
    // The retry lands: the earlier rejection is still in the flashed error bag,
    // so the branch row itself is the proof, not the session.
    actingAs(User::findOrFail($this->admin->id))
        ->from(route('avana.perusahaan'))
        ->post(route('avana.perusahaan.store', 'branches'), $payload);

    expect(Branch::where('tenant_id', $this->tenant->id)->where('code', 'CB-555')->exists())->toBeTrue();
});

it('keeps branches a tenant already has when the package shrinks', function (): void {
    $used = TenantQuota::used($this->tenant, 'branches');
    $this->tenant->forceFill(['max_branches' => 1])->save();

    // Over the ceiling, so nothing new is allowed — but nothing is deleted either.
    expect(TenantQuota::remaining($this->tenant, 'branches'))->toBe(0)
        ->and(Branch::where('tenant_id', $this->tenant->id)->count())->toBe($used);
});

it('treats a null or zero limit as unlimited', function (): void {
    $this->tenant->forceFill(['max_branches' => null, 'max_employees' => 0])->save();

    expect(TenantQuota::remaining($this->tenant, 'branches'))->toBeNull()
        ->and(TenantQuota::remaining($this->tenant, 'employees'))->toBeNull();
});

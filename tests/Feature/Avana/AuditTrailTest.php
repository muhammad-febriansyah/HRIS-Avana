<?php

use App\Models\AuditLog;
use App\Models\Employee;
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
    $this->employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();
});

it('writes no audit rows during unauthenticated seeding', function (): void {
    // beforeEach seeds the demo data without an authenticated user, so the
    // audit trail must stay empty and the broader suite stays green.
    expect(AuditLog::count())->toBe(0);
});

it('logs an audit row when an authenticated user creates an employee', function (): void {
    actingAs($this->admin);

    $employee = Employee::create([
        'tenant_id' => $this->tenant->id,
        'employee_number' => 'EMP-AUDIT-1',
        'full_name' => 'Karyawan Audit',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    $log = AuditLog::where('auditable_type', Employee::class)
        ->where('auditable_id', $employee->id)
        ->where('action', 'created')
        ->firstOrFail();

    expect($log->user_id)->toBe($this->admin->id);
    expect($log->tenant_id)->toBe($this->tenant->id);
    expect($log->new_values)->toHaveKey('full_name');
    expect($log->new_values['full_name'])->toBe('Karyawan Audit');
    expect($log->old_values)->toBeNull();
    expect($log->ip_address)->not->toBeNull();
});

it('logs only the changed fields on update and never the password or timestamps', function (): void {
    actingAs($this->admin);

    $user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Nama Awal',
    ]);

    // Drop the create log so only the update is inspected below.
    AuditLog::query()->delete();

    $user->update([
        'name' => 'Nama Baru',
        'password' => 'rahasia-baru-123',
    ]);

    $log = AuditLog::where('auditable_type', User::class)
        ->where('auditable_id', $user->id)
        ->where('action', 'updated')
        ->firstOrFail();

    expect($log->new_values)->toHaveKey('name');
    expect($log->new_values['name'])->toBe('Nama Baru');
    expect($log->new_values)->not->toHaveKey('password');
    expect($log->new_values)->not->toHaveKey('remember_token');
    expect($log->new_values)->not->toHaveKey('updated_at');
    expect($log->new_values)->not->toHaveKey('created_at');
    expect(array_keys($log->old_values))->toBe(['name']);
});

it('renders the audit index scoped to the current tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain-audit']);

    AuditLog::create([
        'tenant_id' => $otherTenant->id,
        'user_id' => null,
        'auditable_type' => Employee::class,
        'auditable_id' => 999,
        'action' => 'created',
        'new_values' => ['full_name' => 'Karyawan Asing'],
        'ip_address' => '127.0.0.1',
    ]);

    AuditLog::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->admin->id,
        'auditable_type' => Employee::class,
        'auditable_id' => Employee::forTenant($this->tenant->id)->value('id'),
        'action' => 'updated',
        'old_values' => ['full_name' => 'Lama'],
        'new_values' => ['full_name' => 'Baru'],
        'ip_address' => '127.0.0.1',
    ]);

    $tenantTotal = AuditLog::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.audit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/audit/index', false)
            ->has('logs.data')
            ->has('filters')
            ->where('logs.meta.total', $tenantTotal)
            ->where('logs.data', fn ($rows) => collect($rows)
                ->pluck('label')
                ->doesntContain('Karyawan Asing')));
});

it('forbids a plain employee from viewing the audit trail', function (): void {
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$this->employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.audit'))
        ->assertForbidden();
});

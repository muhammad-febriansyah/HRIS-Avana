<?php

use App\Models\Employee;
use App\Models\EmployeeCareerHistory;
use App\Models\Position;
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
    $this->employee = Employee::where('tenant_id', $this->tenant->id)->firstOrFail();
});

it('renders the mutasi index with tenant-scoped props', function (): void {
    EmployeeCareerHistory::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $this->employee->id,
        'movement_type' => 'promotion',
        'effective_date' => '2026-01-10',
        'previous_position_id' => $this->employee->position_id,
        'position_id' => $this->employee->position_id,
        'previous_department_id' => $this->employee->department_id,
        'department_id' => $this->employee->department_id,
        'previous_branch_id' => $this->employee->branch_id,
        'branch_id' => $this->employee->branch_id,
        'previous_employment_status' => $this->employee->employment_status,
        'employment_status' => $this->employee->employment_status,
        'notes' => 'Promosi tahunan',
        'created_by' => $this->admin->id,
    ]);

    actingAs($this->admin)
        ->get(route('avana.mutasi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/mutasi', false)
            ->has('movements.data', 1)
            ->has('movements.data.0', fn (Assert $row) => $row
                ->has('id')
                ->has('employee')
                ->has('movement_type')
                ->has('effective_date')
                ->has('changes')
                ->has('employment_status')
                ->has('notes')
                ->has('created_at')
                ->etc())
            ->has('employees')
            ->has('positions')
            ->has('departments')
            ->has('branches')
            ->has('filters'));
});

it('only lists movements that belong to the current tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $foreignEmployee = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-9001',
        'full_name' => 'Karyawan Asing',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    EmployeeCareerHistory::create([
        'tenant_id' => $otherTenant->id,
        'employee_id' => $foreignEmployee->id,
        'movement_type' => 'mutation',
        'effective_date' => '2026-02-01',
        'previous_employment_status' => 'permanent',
        'employment_status' => 'permanent',
        'created_by' => $this->admin->id,
    ]);

    actingAs($this->admin)
        ->get(route('avana.mutasi'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('movements.meta.total', 0));
});

it('records a promotion and applies the new position to the employee', function (): void {
    $newPosition = Position::where('tenant_id', $this->tenant->id)
        ->where('id', '!=', $this->employee->position_id)
        ->firstOrFail();

    $previousPositionId = $this->employee->position_id;

    actingAs($this->admin)
        ->post(route('avana.mutasi.store'), [
            'employee_id' => $this->employee->id,
            'movement_type' => 'promotion',
            'effective_date' => '2026-03-01',
            'position_id' => $newPosition->id,
            'notes' => 'Naik jabatan',
        ])
        ->assertRedirect(route('avana.mutasi'))
        ->assertSessionHas('success');

    $history = EmployeeCareerHistory::where('employee_id', $this->employee->id)
        ->where('movement_type', 'promotion')
        ->firstOrFail();

    expect($history->tenant_id)->toBe($this->tenant->id);
    expect($history->previous_position_id)->toBe($previousPositionId);
    expect($history->position_id)->toBe($newPosition->id);
    expect($history->created_by)->toBe($this->admin->id);

    expect($this->employee->fresh()->position_id)->toBe($newPosition->id);
});

it('marks the employee inactive on resign', function (): void {
    expect($this->employee->status)->toBe('active');

    actingAs($this->admin)
        ->post(route('avana.mutasi.store'), [
            'employee_id' => $this->employee->id,
            'movement_type' => 'resign',
            'effective_date' => '2026-04-15',
            'employment_status' => 'resigned',
            'notes' => 'Mengundurkan diri',
        ])
        ->assertRedirect(route('avana.mutasi'))
        ->assertSessionHas('success');

    $fresh = $this->employee->fresh();

    expect($fresh->status)->toBe('inactive');
    expect($fresh->employment_status)->toBe('resigned');

    $history = EmployeeCareerHistory::where('employee_id', $this->employee->id)
        ->where('movement_type', 'resign')
        ->firstOrFail();

    expect($history->previous_employment_status)->toBe('permanent');
});

it('validates required fields and movement type on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.mutasi.store'), [
            'movement_type' => 'not-a-type',
            'effective_date' => '2026-05-01',
        ])
        ->assertSessionHasErrors(['employee_id', 'movement_type']);
});

it('rejects an employee id from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = Employee::create([
        'tenant_id' => $otherTenant->id,
        'employee_number' => 'EMP-8001',
        'full_name' => 'Karyawan Luar',
        'employment_status' => 'permanent',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post(route('avana.mutasi.store'), [
            'employee_id' => $foreign->id,
            'movement_type' => 'mutation',
            'effective_date' => '2026-06-01',
        ])
        ->assertSessionHasErrors(['employee_id']);

    expect(EmployeeCareerHistory::where('employee_id', $foreign->id)->count())->toBe(0);
});

it('forbids users without employee permissions from managing movements', function (): void {
    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$this->employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.mutasi'))
        ->assertForbidden();

    actingAs($staff)
        ->post(route('avana.mutasi.store'), [
            'employee_id' => $this->employee->id,
            'movement_type' => 'mutation',
            'effective_date' => '2026-06-01',
        ])
        ->assertForbidden();
});

<?php

use App\Models\Role;
use App\Models\SalaryGrade;
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
 * Create a salary grade for the given tenant.
 */
function makeSalaryGrade(int $tenantId, array $overrides = []): SalaryGrade
{
    return SalaryGrade::create(array_merge([
        'tenant_id' => $tenantId,
        'grade_code' => 'G'.fake()->unique()->numberBetween(1, 999),
        'grade_name' => 'Staff',
        'level' => 1,
        'min_salary' => 5_000_000,
        'mid_salary' => 6_500_000,
        'max_salary' => 8_000_000,
    ], $overrides));
}

it('renders the salary structure index with the expected props', function (): void {
    makeSalaryGrade($this->tenant->id);

    actingAs($this->admin)
        ->get(route('avana.struktur-upah'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/struktur-upah/index', false)
            ->has('grades.0', fn (Assert $row) => $row
                ->has('id')
                ->has('grade_code')
                ->has('grade_name')
                ->has('level')
                ->has('min_salary')
                ->has('mid_salary')
                ->has('max_salary'))
            ->has('kpis'));
});

it('only lists salary grades that belong to the current tenant', function (): void {
    makeSalaryGrade($this->tenant->id);

    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    makeSalaryGrade($otherTenant->id);

    $tenantTotal = SalaryGrade::where('tenant_id', $this->tenant->id)->count();

    actingAs($this->admin)
        ->get(route('avana.struktur-upah'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('grades', $tenantTotal));
});

it('creates a salary grade scoped to the current tenant', function (): void {
    actingAs($this->admin)
        ->post(route('avana.struktur-upah.store'), [
            'grade_code' => 'G3',
            'grade_name' => 'Senior Staff',
            'level' => 3,
            'min_salary' => 8_000_000,
            'mid_salary' => 10_000_000,
            'max_salary' => 12_000_000,
        ])
        ->assertRedirect(route('avana.struktur-upah'))
        ->assertSessionHas('success');

    $grade = SalaryGrade::where('grade_code', 'G3')->firstOrFail();

    expect($grade->tenant_id)->toBe($this->tenant->id);
    expect($grade->level)->toBe(3);
    expect((float) $grade->max_salary)->toBe(12_000_000.0);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post(route('avana.struktur-upah.store'), [
            'grade_code' => '',
            'grade_name' => '',
            'level' => 0,
            'min_salary' => '',
            'mid_salary' => '',
            'max_salary' => '',
        ])
        ->assertSessionHasErrors(['grade_code', 'grade_name', 'level', 'min_salary', 'mid_salary', 'max_salary']);
});

it('rejects a salary band where the bounds are out of order', function (): void {
    actingAs($this->admin)
        ->post(route('avana.struktur-upah.store'), [
            'grade_code' => 'G9',
            'grade_name' => 'Broken',
            'level' => 1,
            'min_salary' => 9_000_000,
            'mid_salary' => 7_000_000,
            'max_salary' => 5_000_000,
        ])
        ->assertSessionHasErrors(['mid_salary', 'max_salary']);
});

it('updates an existing salary grade', function (): void {
    $grade = makeSalaryGrade($this->tenant->id, ['grade_name' => 'Old Name']);

    actingAs($this->admin)
        ->put(route('avana.struktur-upah.update', $grade), [
            'grade_code' => $grade->grade_code,
            'grade_name' => 'New Name',
            'level' => 4,
            'min_salary' => 6_000_000,
            'mid_salary' => 7_000_000,
            'max_salary' => 9_000_000,
        ])
        ->assertRedirect(route('avana.struktur-upah'))
        ->assertSessionHas('success');

    $grade->refresh();

    expect($grade->grade_name)->toBe('New Name');
    expect($grade->level)->toBe(4);
});

it('deletes a salary grade', function (): void {
    $grade = makeSalaryGrade($this->tenant->id);

    actingAs($this->admin)
        ->delete(route('avana.struktur-upah.destroy', $grade))
        ->assertSessionHas('success');

    expect(SalaryGrade::find($grade->id))->toBeNull();
});

it('returns 404 when updating a salary grade from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Asing', 'slug' => 'pt-asing']);
    $foreign = makeSalaryGrade($otherTenant->id);

    actingAs($this->admin)
        ->put(route('avana.struktur-upah.update', $foreign), [
            'grade_code' => 'HACK',
            'grade_name' => 'Hack',
            'level' => 1,
            'min_salary' => 1_000_000,
            'mid_salary' => 2_000_000,
            'max_salary' => 3_000_000,
        ])
        ->assertNotFound();
});

it('forbids a plain employee from listing salary grades', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get(route('avana.struktur-upah'))
        ->assertForbidden();
});

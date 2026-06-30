<?php

use App\Http\Controllers\Avana\CompanySetupController;
use App\Models\Branch;
use App\Models\Department;
use App\Models\Position;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'admin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // Self-contained routes targeting the controller (routes/avana.php is
    // wired by the orchestrator and must not be edited here).
    Route::middleware('web')->group(function (): void {
        Route::get('spec-perusahaan', [CompanySetupController::class, 'index']);
        Route::post('spec-perusahaan/{entity}', [CompanySetupController::class, 'store']);
        Route::put('spec-perusahaan/{entity}/{record}', [CompanySetupController::class, 'update']);
        Route::delete('spec-perusahaan/{entity}/{record}', [CompanySetupController::class, 'destroy']);
    });
});

it('renders the company setup screen with the expected props', function (): void {
    actingAs($this->admin)
        ->get('spec-perusahaan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/perusahaan/index', false)
            ->has('branches')
            ->has('departments')
            ->has('positions')
            ->has('jobLevels')
            ->has('workLocations')
            ->has('shifts')
            ->has('options.departments')
            ->has('options.branches'));
});

it('creates, updates and deletes a branch', function (): void {
    actingAs($this->admin)
        ->post('spec-perusahaan/branches', [
            'code' => 'MDN',
            'name' => 'Medan',
            'phone' => '061-123456',
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $branch = Branch::where('tenant_id', $this->tenant->id)->where('code', 'MDN')->firstOrFail();
    expect($branch->name)->toBe('Medan');

    actingAs($this->admin)
        ->put('spec-perusahaan/branches/'.$branch->id, [
            'code' => 'MDN',
            'name' => 'Medan Kota',
            'status' => 'inactive',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($branch->fresh()->name)->toBe('Medan Kota');
    expect($branch->fresh()->status)->toBe('inactive');

    actingAs($this->admin)
        ->delete('spec-perusahaan/branches/'.$branch->id)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Branch::where('id', $branch->id)->exists())->toBeFalse();
    expect(Branch::withTrashed()->where('id', $branch->id)->exists())->toBeTrue();
});

it('creates a position assigned to a department', function (): void {
    $department = Department::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post('spec-perusahaan/positions', [
            'code' => 'NEW-POS',
            'name' => 'Posisi Baru',
            'department_id' => $department->id,
            'status' => 'active',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $position = Position::where('tenant_id', $this->tenant->id)->where('code', 'NEW-POS')->firstOrFail();
    expect($position->department_id)->toBe($department->id);
});

it('validates required fields on store', function (): void {
    actingAs($this->admin)
        ->post('spec-perusahaan/branches', [
            'code' => '',
            'name' => '',
            'status' => 'invalid',
        ])
        ->assertSessionHasErrors(['code', 'name', 'status']);
});

it('rejects an unknown entity slug with a 404', function (): void {
    actingAs($this->admin)
        ->post('spec-perusahaan/unknown', ['name' => 'X'])
        ->assertNotFound();
});

it('returns 404 when updating a record from another tenant', function (): void {
    $otherTenant = Tenant::create(['name' => 'PT Lain', 'slug' => 'pt-lain']);
    $foreign = Branch::create([
        'tenant_id' => $otherTenant->id,
        'code' => 'XXX',
        'name' => 'Cabang Asing',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->put('spec-perusahaan/branches/'.$foreign->id, [
            'code' => 'XXX',
            'name' => 'Diretas',
            'status' => 'active',
        ])
        ->assertNotFound();

    expect($foreign->fresh()->name)->toBe('Cabang Asing');
});

it('forbids users without manage permission from accessing the module', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get('spec-perusahaan')
        ->assertForbidden();
});

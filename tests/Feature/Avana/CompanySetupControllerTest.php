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

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // Self-contained routes targeting the controller (routes/avana.php is
    // wired by the orchestrator and must not be edited here).
    Route::middleware('web')->group(function (): void {
        Route::get('spec-perusahaan', [CompanySetupController::class, 'index']);
        Route::put('spec-perusahaan-profile', [CompanySetupController::class, 'updateProfile']);
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
            ->has('options.branches')
            ->has('company.name'));
});

it('updates the company profile and syncs the tenant display name', function (): void {
    actingAs($this->admin)
        ->put('spec-perusahaan-profile', [
            'name' => 'PT Nusantara Jaya Abadi',
            'legal_name' => 'PT Nusantara Jaya Abadi Tbk',
            'npwp' => '01.234.567.8-901.000',
            'email' => 'info@nusantara.co.id',
            'phone' => '021-5550000',
            'address' => 'Jl. Sudirman No. 1, Jakarta',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $company = App\Models\Company::forTenant($this->tenant->id)->firstOrFail();
    expect($company->name)->toBe('PT Nusantara Jaya Abadi')
        ->and($company->npwp)->toBe('01.234.567.8-901.000')
        ->and($company->email)->toBe('info@nusantara.co.id');
    // The tenant display name (topbar / white-label) follows the profile name.
    expect($this->tenant->fresh()->company_name)->toBe('PT Nusantara Jaya Abadi');
});

it('requires a company name on the profile', function (): void {
    actingAs($this->admin)
        ->put('spec-perusahaan-profile', ['name' => ''])
        ->assertSessionHasErrors('name');
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

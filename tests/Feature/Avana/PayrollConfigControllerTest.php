<?php

use App\Http\Controllers\Avana\PayrollConfigController;
use App\Models\BpjsProgram;
use App\Models\Pph21TerRate;
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
    $this->superadmin = User::where('email', 'superadmin@avanahr.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // Self-contained routes targeting the controller (routes/avana.php is
    // wired by the orchestrator and must not be edited here; its 'verified'
    // middleware is sidestepped with a plain 'web' group).
    Route::middleware('web')->group(function (): void {
        Route::get('spec-payroll-config', [PayrollConfigController::class, 'index']);
        Route::post('spec-payroll-config/bpjs', [PayrollConfigController::class, 'storeBpjsProgram']);
        Route::put('spec-payroll-config/bpjs/{program}', [PayrollConfigController::class, 'updateBpjsProgram']);
        Route::delete('spec-payroll-config/bpjs/{program}', [PayrollConfigController::class, 'destroyBpjsProgram']);
        Route::post('spec-payroll-config/pph21', [PayrollConfigController::class, 'storeTerRate']);
        Route::put('spec-payroll-config/pph21/{rate}', [PayrollConfigController::class, 'updateTerRate']);
        Route::delete('spec-payroll-config/pph21/{rate}', [PayrollConfigController::class, 'destroyTerRate']);
    });
});

it('renders the payroll config screen with the expected props', function (): void {
    actingAs($this->admin)
        ->get('spec-payroll-config')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/payroll-config/index', false)
            ->has('programs', 3)
            ->has('programs.0.rates')
            ->has('terRates', 3)
            ->has('profileStats.bpjs_profiles')
            ->has('profileStats.tax_profiles'));
});

it('creates a BPJS program together with its primary rate', function (): void {
    actingAs($this->superadmin)
        ->post('spec-payroll-config/bpjs', [
            'code' => 'JKK',
            'name' => 'BPJS JKK',
            'type' => 'jkk',
            'description' => 'Jaminan kecelakaan kerja',
            'is_active' => true,
            'employee_rate' => 0,
            'company_rate' => 0.0024,
            'min_wage' => 1000000,
            'max_wage' => 12000000,
            'effective_start_date' => '2026-01-01',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $program = BpjsProgram::where('code', 'JKK')->firstOrFail();
    expect($program->name)->toBe('BPJS JKK');
    expect($program->is_active)->toBeTrue();

    $rate = $program->rates()->first();
    expect($rate)->not->toBeNull();
    expect((float) $rate->company_rate)->toBe(0.0024);
    expect((float) $rate->max_wage)->toBe(12000000.0);
});

it('validates required fields on BPJS store', function (): void {
    actingAs($this->superadmin)
        ->post('spec-payroll-config/bpjs', [
            'code' => '',
            'name' => '',
            'type' => '',
            'employee_rate' => '',
            'company_rate' => '',
        ])
        ->assertSessionHasErrors(['code', 'name', 'type', 'employee_rate', 'company_rate', 'effective_start_date']);
});

it('rejects a duplicate BPJS code', function (): void {
    actingAs($this->superadmin)
        ->post('spec-payroll-config/bpjs', [
            'code' => 'KESEHATAN',
            'name' => 'Duplikat',
            'type' => 'kesehatan',
            'employee_rate' => 0.01,
            'company_rate' => 0.04,
            'effective_start_date' => '2026-01-01',
        ])
        ->assertSessionHasErrors('code');
});

it('updates a BPJS program and its latest rate', function (): void {
    $program = BpjsProgram::where('code', 'KESEHATAN')->firstOrFail();

    actingAs($this->superadmin)
        ->put('spec-payroll-config/bpjs/'.$program->id, [
            'code' => 'KESEHATAN',
            'name' => 'BPJS Kesehatan Baru',
            'type' => 'kesehatan',
            'is_active' => false,
            'employee_rate' => 0.015,
            'company_rate' => 0.045,
            'effective_start_date' => '2026-01-01',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $program->refresh();
    expect($program->name)->toBe('BPJS Kesehatan Baru');
    expect($program->is_active)->toBeFalse();

    $rate = $program->rates()->orderByDesc('effective_start_date')->orderByDesc('id')->first();
    expect((float) $rate->employee_rate)->toBe(0.015);
    expect((float) $rate->company_rate)->toBe(0.045);
});

it('soft deletes a BPJS program', function (): void {
    $program = BpjsProgram::where('code', 'JP')->firstOrFail();

    actingAs($this->superadmin)
        ->delete('spec-payroll-config/bpjs/'.$program->id)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(BpjsProgram::where('id', $program->id)->exists())->toBeFalse();
    expect(BpjsProgram::withTrashed()->where('id', $program->id)->exists())->toBeTrue();
});

it('creates a PPh 21 TER rate', function (): void {
    actingAs($this->superadmin)
        ->post('spec-payroll-config/pph21', [
            'category' => 'B',
            'income_min' => 6200000,
            'income_max' => 6500000,
            'rate' => 0.0075,
            'effective_start_date' => '2026-01-01',
            'is_active' => true,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $rate = Pph21TerRate::where('category', 'B')->where('income_min', 6200000)->firstOrFail();
    expect((float) $rate->rate)->toBe(0.0075);
    expect($rate->is_active)->toBeTrue();
});

it('validates required fields on PPh 21 store', function (): void {
    actingAs($this->superadmin)
        ->post('spec-payroll-config/pph21', [
            'category' => '',
            'income_min' => '',
            'rate' => '',
        ])
        ->assertSessionHasErrors(['category', 'income_min', 'rate', 'effective_start_date']);
});

it('updates a PPh 21 TER rate', function (): void {
    $rate = Pph21TerRate::where('category', 'A')->orderBy('income_min')->firstOrFail();

    actingAs($this->superadmin)
        ->put('spec-payroll-config/pph21/'.$rate->id, [
            'category' => 'A',
            'income_min' => 0,
            'income_max' => 5600000,
            'rate' => 0.001,
            'effective_start_date' => '2026-01-01',
            'is_active' => false,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $rate->refresh();
    expect((float) $rate->rate)->toBe(0.001);
    expect($rate->is_active)->toBeFalse();
});

it('deletes a PPh 21 TER rate', function (): void {
    $rate = Pph21TerRate::query()->firstOrFail();

    actingAs($this->superadmin)
        ->delete('spec-payroll-config/pph21/'.$rate->id)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(Pph21TerRate::where('id', $rate->id)->exists())->toBeFalse();
});

it('forbids a plain employee from viewing the configuration', function (): void {
    $staff = makeEmployee($this->tenant->id);

    actingAs($staff)
        ->get('spec-payroll-config')
        ->assertForbidden();
});

it('forbids a plain employee from creating a BPJS program', function (): void {
    $staff = makeEmployee($this->tenant->id);

    actingAs($staff)
        ->post('spec-payroll-config/bpjs', [
            'code' => 'JKM',
            'name' => 'BPJS JKM',
            'type' => 'jkm',
            'employee_rate' => 0,
            'company_rate' => 0.003,
            'effective_start_date' => '2026-01-01',
        ])
        ->assertForbidden();

    expect(BpjsProgram::where('code', 'JKM')->exists())->toBeFalse();
});

it('lets an HR admin view but not edit the global statutory config', function (): void {
    // BPJS/PPh21 tables are global (shared across tenants): a tenant admin can
    // read them but only a super admin may change them.
    actingAs($this->admin)->get('spec-payroll-config')->assertOk();

    actingAs($this->admin)
        ->post('spec-payroll-config/bpjs', [
            'code' => 'JKM',
            'name' => 'BPJS JKM',
            'type' => 'jkm',
            'employee_rate' => 0,
            'company_rate' => 0.003,
            'effective_start_date' => '2026-01-01',
        ])
        ->assertForbidden();

    actingAs($this->admin)
        ->post('spec-payroll-config/pph21', [
            'category' => 'C',
            'income_min' => 1000000,
            'rate' => 0.01,
            'effective_start_date' => '2026-01-01',
        ])
        ->assertForbidden();

    expect(BpjsProgram::where('code', 'JKM')->exists())->toBeFalse();
});

/**
 * Create a user holding only the tenant's plain employee role.
 */
function makeEmployee(int $tenantId): User
{
    $employeeRole = Role::where('tenant_id', $tenantId)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $tenantId]);
    $staff->roles()->sync([$employeeRole->id]);

    return $staff;
}

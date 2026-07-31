<?php

use App\Http\Controllers\Avana\PayrollConfigController;
use App\Models\BpjsProgram;
use App\Models\Employee;
use App\Models\Permission;
use App\Models\PkpRate;
use App\Models\PtkpRate;
use App\Models\Role;
use App\Models\TaxProfile;
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
    $this->superadmin = User::where('email', 'superadmin@avanahr.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);

    // Self-contained routes targeting the controller (routes/avana.php is
    // wired by the orchestrator and must not be edited here; its 'verified'
    // middleware is sidestepped with a plain 'web' group).
    Route::middleware('web')->group(function (): void {
        Route::get('spec-payroll-config', [PayrollConfigController::class, 'index']);
        Route::post('spec-payroll-config/bpjs', [PayrollConfigController::class, 'storeBpjsProgram']);
        Route::put('spec-payroll-config/bpjs/{program}', [PayrollConfigController::class, 'updateBpjsProgram']);
        Route::delete('spec-payroll-config/bpjs/{program}', [PayrollConfigController::class, 'destroyBpjsProgram']);
        Route::post('spec-payroll-config/ptkp', [PayrollConfigController::class, 'storePtkpRate']);
        Route::delete('spec-payroll-config/ptkp/{rate}', [PayrollConfigController::class, 'destroyPtkpRate']);
        Route::post('spec-payroll-config/pkp', [PayrollConfigController::class, 'storePkpRate']);
        Route::delete('spec-payroll-config/pkp/{rate}', [PayrollConfigController::class, 'destroyPkpRate']);
        Route::post('spec-payroll-config/tax-profile', [PayrollConfigController::class, 'upsertTaxProfile']);
    });
});

it('lists employee tax profiles and subject options', function (): void {
    actingAs($this->admin)
        ->get('spec-payroll-config')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('taxProfiles.0.tax_subject')
            ->has('taxSubjects.0.value')
            ->has('ptkpStatuses'));
});

it('upserts an employee PPh 21 tax profile (daily worker)', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post('spec-payroll-config/tax-profile', [
            'employee_id' => $employee->id,
            'tax_subject' => 'pegawai_tidak_tetap',
            'ptkp_status' => 'tk/0',
            'wage_basis' => 'daily',
            'daily_wage' => 400000,
            'npwp' => '12.345.678.9-012.000',
        ])
        ->assertSessionHas('success');

    $profile = TaxProfile::where('tenant_id', $this->tenant->id)
        ->where('employee_id', $employee->id)->firstOrFail();

    expect($profile->tax_subject)->toBe('pegawai_tidak_tetap')
        ->and($profile->wage_basis)->toBe('daily')
        ->and((float) $profile->daily_wage)->toBe(400000.0)
        ->and($profile->ptkp_status)->toBe('TK/0');
});

it('rejects a PTKP status that is not one of the eight', function (): void {
    // "K/O" is the letter O, not a zero. It used to be accepted and then fall
    // through to Kategori A — the wrong TER rate on every payslip after it.
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post('spec-payroll-config/tax-profile', [
            'employee_id' => $employee->id,
            'tax_subject' => 'pegawai_tetap',
            'ptkp_status' => 'K/O',
            'wage_basis' => 'monthly',
        ])
        ->assertSessionHasErrors('ptkp_status');

    actingAs($this->admin)
        ->post('spec-payroll-config/ptkp', [
            'ptkp_status' => 'K/4',
            'year' => 2026,
            'amount' => 76500000,
        ])
        ->assertSessionHasErrors('ptkp_status');
});

it('rejects an unknown tax subject', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post('spec-payroll-config/tax-profile', [
            'employee_id' => $employee->id,
            'tax_subject' => 'not_a_subject',
            'wage_basis' => 'monthly',
        ])
        ->assertSessionHasErrors('tax_subject');
});

it('renders the payroll config screen with the expected props', function (): void {
    actingAs($this->admin)
        ->get('spec-payroll-config')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/payroll-config/index', false)
            ->has('programs', 5)
            ->has('programs.0.rates')
            ->has('ptkpRates')
            ->has('pkpRates')
            ->has('profileStats.bpjs_profiles')
            ->has('profileStats.tax_profiles'));
});

it('creates a BPJS program together with its primary rate', function (): void {
    actingAs($this->superadmin)
        ->post('spec-payroll-config/bpjs', [
            'code' => 'JKP',
            'name' => 'BPJS JKP',
            'type' => 'jkp',
            'description' => 'Jaminan kehilangan pekerjaan',
            'is_active' => true,
            'employee_rate' => 0,
            'company_rate' => 0.0024,
            'min_wage' => 1000000,
            'max_wage' => 12000000,
            'effective_start_date' => '2026-01-01',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $program = BpjsProgram::where('code', 'JKP')->firstOrFail();
    expect($program->name)->toBe('BPJS JKP');
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

it('creates a Tarif PTKP entry', function (): void {
    actingAs($this->admin)
        ->post('spec-payroll-config/ptkp', [
            'ptkp_status' => 'K/2',
            'year' => 2026,
            'amount' => 67500000,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $rate = PtkpRate::where('tenant_id', $this->tenant->id)->where('ptkp_status', 'K/2')->where('year', 2026)->firstOrFail();
    expect((float) $rate->amount)->toBe(67500000.0);
});

it('validates required fields on Tarif PTKP store', function (): void {
    actingAs($this->admin)
        ->post('spec-payroll-config/ptkp', [
            'ptkp_status' => '',
            'year' => '',
            'amount' => '',
        ])
        ->assertSessionHasErrors(['ptkp_status', 'year', 'amount']);
});

it('creates a Tarif PKP progressive bracket', function (): void {
    actingAs($this->admin)
        ->post('spec-payroll-config/pkp', [
            'year' => 2026,
            'up_to' => 60000000,
            'rate' => 0.05,
            'sort_order' => 0,
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    $rate = PkpRate::where('tenant_id', $this->tenant->id)->where('year', 2026)->where('up_to', 60000000)->firstOrFail();
    expect((float) $rate->rate)->toBe(0.05);
});

it('deletes a Tarif PTKP entry', function (): void {
    $rate = PtkpRate::where('tenant_id', $this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->delete('spec-payroll-config/ptkp/'.$rate->id)
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(PtkpRate::where('id', $rate->id)->exists())->toBeFalse();
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
            'code' => 'JKP',
            'name' => 'BPJS JKP',
            'type' => 'jkp',
            'employee_rate' => 0,
            'company_rate' => 0.003,
            'effective_start_date' => '2026-01-01',
        ])
        ->assertForbidden();

    expect(BpjsProgram::where('code', 'JKP')->exists())->toBeFalse();
});

it('lets an HR admin view but not edit the global statutory config', function (): void {
    // BPJS/PPh21 tables are global (shared across tenants): a tenant admin can
    // read them but only a super admin may change them.
    actingAs($this->admin)->get('spec-payroll-config')->assertOk();

    actingAs($this->admin)
        ->post('spec-payroll-config/bpjs', [
            'code' => 'JKP',
            'name' => 'BPJS JKP',
            'type' => 'jkp',
            'employee_rate' => 0,
            'company_rate' => 0.003,
            'effective_start_date' => '2026-01-01',
        ])
        ->assertForbidden();

    // Tarif PTKP/PKP are tenant-scoped config: a tenant HR admin MAY manage them.
    actingAs($this->admin)
        ->post('spec-payroll-config/ptkp', [
            'ptkp_status' => 'TK/0',
            'year' => 2026,
            'amount' => 54000000,
        ])
        ->assertRedirect();

    expect(BpjsProgram::where('code', 'JKP')->exists())->toBeFalse();
});

it('maps each config action to its own permission module', function (): void {
    // A role scoped to only pph21.create: may add a PTKP tariff, but cannot open
    // the config landing (that needs payroll.view) nor touch BPJS (super admin).
    $role = Role::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'tax-editor',
        'name' => 'Tax Editor',
        'is_system' => false,
    ]);
    $role->permissions()->syncWithoutDetaching(
        Permission::where('code', 'pph21.create')->pluck('id'),
    );

    $taxEditor = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $taxEditor->roles()->sync([$role->id]);

    actingAs($taxEditor)
        ->post('spec-payroll-config/ptkp', [
            'ptkp_status' => 'K/1',
            'year' => 2026,
            'amount' => 63000000,
        ])
        ->assertRedirect();

    actingAs($taxEditor)
        ->get('spec-payroll-config')
        ->assertForbidden();
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

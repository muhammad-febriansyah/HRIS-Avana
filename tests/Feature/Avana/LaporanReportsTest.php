<?php

use App\Http\Controllers\Avana\LaporanController;
use App\Models\Employee;
use App\Models\PayrollPeriod;
use App\Models\PayrollRun;
use App\Models\PayrollRunItem;
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

    // Self-contained routes mirroring the production laporan actions so the
    // controller can be exercised in isolation, matching LaporanControllerTest.
    Route::middleware('web')->prefix('spec-avana')->name('spec.')->group(function (): void {
        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan');
        Route::get('laporan/export/{type}', [LaporanController::class, 'export'])->name('laporan.export');
    });
});

/**
 * Seed a fresh payroll run with one item so the run-based BPJS/PPh21 reports
 * have deterministic rows. Its id is the highest, so it becomes the latest run.
 *
 * @return array{employee: Employee, item: PayrollRunItem}
 */
function seedLaporanRunItem(Tenant $tenant): array
{
    $employee = Employee::where('tenant_id', $tenant->id)->orderBy('id')->firstOrFail();

    $period = PayrollPeriod::where('tenant_id', $tenant->id)->orderByDesc('id')->firstOrFail();

    $run = PayrollRun::create([
        'tenant_id' => $tenant->id,
        'payroll_period_id' => $period->id,
        'branch_id' => null,
        'status' => 'calculated',
        'total_gross' => 8_000_000,
        'total_deduction' => 320_000,
        'total_tax' => 80_000,
        'total_net' => 7_600_000,
        'employee_count' => 1,
    ]);

    $item = PayrollRunItem::create([
        'tenant_id' => $tenant->id,
        'payroll_run_id' => $run->id,
        'payroll_period_id' => $period->id,
        'employee_id' => $employee->id,
        'gross_salary' => 8_000_000,
        'total_allowance' => 2_000_000,
        'total_deduction' => 320_000,
        'bpjs_employee_total' => 240_000,
        'bpjs_company_total' => 456_000,
        'pph21_total' => 80_000,
        'net_salary' => 7_600_000,
        'calculation_snapshot' => [
            'tax' => ['ter_category' => 'A', 'tax_rate' => 0.01, 'pph21_amount' => 80_000],
        ],
        'status' => 'calculated',
    ]);

    return ['employee' => $employee, 'item' => $item];
}

it('renders the laporan index exposing the new report counts', function (): void {
    actingAs($this->admin)
        ->get('spec-avana/laporan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/laporan/index', false)
            ->has('stats', fn (Assert $stats) => $stats
                ->has('bpjs')
                ->has('pph21')
                ->has('turnover')
                ->etc()));
});

it('streams the bpjs CSV from the latest payroll run', function (): void {
    $seeded = seedLaporanRunItem($this->tenant);

    $response = actingAs($this->admin)->get('spec-avana/laporan/export/bpjs');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/csv');

    $body = $response->streamedContent();

    expect($body)->toContain('Nama', 'Employee Number', 'Upah Dasar', 'BPJS Karyawan', 'BPJS Perusahaan');
    expect($body)->toContain($seeded['employee']->full_name);
    expect($body)->toContain('240000', '456000');
});

it('streams the pph21 CSV from the latest payroll run', function (): void {
    $seeded = seedLaporanRunItem($this->tenant);

    $response = actingAs($this->admin)->get('spec-avana/laporan/export/pph21');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/csv');

    $body = $response->streamedContent();

    expect($body)->toContain('Nama', 'Employee Number', 'Penghasilan Bruto', 'Kategori TER', 'Tarif', 'PPh 21');
    expect($body)->toContain($seeded['employee']->full_name);
    expect($body)->toContain('8000000', '80000');
});

it('streams the turnover CSV for every employee', function (): void {
    $employee = Employee::where('tenant_id', $this->tenant->id)->orderBy('employee_number')->firstOrFail();

    $response = actingAs($this->admin)->get('spec-avana/laporan/export/turnover');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/csv');

    $body = $response->streamedContent();

    expect($body)->toContain('Nama', 'Employee Number', 'Departemen', 'Jabatan', 'Status', 'Tanggal Masuk', 'Tanggal Keluar');
    expect($body)->toContain($employee->full_name);
});

it('aborts with 404 for an unknown export type', function (): void {
    actingAs($this->admin)
        ->get('spec-avana/laporan/export/unknown-type')
        ->assertNotFound();
});

it('forbids a plain employee from exporting reports', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get('spec-avana/laporan/export/turnover')
        ->assertForbidden();
});

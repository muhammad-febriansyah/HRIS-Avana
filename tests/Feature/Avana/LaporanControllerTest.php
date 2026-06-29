<?php

use App\Http\Controllers\Avana\LaporanController;
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

    // Self-contained routes for the reports backend. The orchestrator wires the
    // production routes into routes/avana.php; these mirror the planned actions
    // on collision-free paths so the controller can be exercised in isolation.
    Route::middleware('web')->prefix('spec-avana')->name('spec.')->group(function (): void {
        Route::get('laporan', [LaporanController::class, 'index'])->name('laporan');
        Route::get('laporan/export/{type}', [LaporanController::class, 'export'])->name('laporan.export');
    });
});

it('renders the laporan index with the expected stats props', function (): void {
    actingAs($this->admin)
        ->get('spec-avana/laporan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/laporan', false)
            ->has('stats', fn (Assert $stats) => $stats
                ->has('karyawan')
                ->has('hadir_hari_ini')
                ->has('cuti_pending')
                ->has('payroll_net')
                ->etc()));
});

it('streams the karyawan CSV export with a seeded employee', function (): void {
    $response = actingAs($this->admin)->get('spec-avana/laporan/export/karyawan');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toStartWith('text/csv');

    $body = $response->streamedContent();

    expect($body)->toContain('Nomor', 'Nama', 'Email');
    expect($body)->toContain('Putri Anjani');
});

it('aborts with 404 for an unknown export type', function (): void {
    actingAs($this->admin)
        ->get('spec-avana/laporan/export/unknown')
        ->assertNotFound();
});

it('forbids users without report access from the laporan index', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get('spec-avana/laporan')
        ->assertForbidden();
});

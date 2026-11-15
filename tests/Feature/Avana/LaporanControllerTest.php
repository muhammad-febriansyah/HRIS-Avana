<?php

use App\Http\Controllers\Avana\LaporanController;
use App\Models\Attendance;
use App\Models\AttendanceCorrection;
use App\Models\Employee;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
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
            ->component('avana/laporan/index', false)
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

it('downloads the karyawan report as xlsx', function (): void {
    $response = actingAs($this->admin)->get('spec-avana/laporan/export/karyawan?format=xlsx');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('spreadsheetml');
});

it('downloads the absensi report as pdf', function (): void {
    $response = actingAs($this->admin)->get('spec-avana/laporan/export/absensi?format=pdf');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

it('scopes the absensi report to a date range', function (): void {
    $all = actingAs($this->admin)->get('spec-avana/laporan/export/absensi')->streamedContent();
    $future = actingAs($this->admin)
        ->get('spec-avana/laporan/export/absensi?start=2099-01-01&end=2099-12-31')
        ->streamedContent();

    // The header row survives; the future window drops every data row.
    expect($future)->toContain('Tanggal');
    expect(substr_count($future, "\n"))->toBeLessThan(substr_count($all, "\n"));
});

it('accepts a payroll period filter without error', function (): void {
    actingAs($this->admin)
        ->get('spec-avana/laporan/export/payroll?period_id=1&format=csv')
        ->assertOk();
});

it('exposes payroll periods to the index for scoping', function (): void {
    actingAs($this->admin)
        ->get('spec-avana/laporan')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/laporan/index', false)
            ->has('periods'));
});

it('forbids users without report access from the laporan index', function (): void {
    $employeeRole = Role::where('tenant_id', $this->tenant->id)->where('code', 'employee')->firstOrFail();

    $staff = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $staff->roles()->sync([$employeeRole->id]);

    actingAs($staff)
        ->get('spec-avana/laporan')
        ->assertForbidden();
});

it('exports the Absensi rekap as a readable workbook with letterhead, text ids and HR notes', function (): void {
    $employee = Employee::forTenant($this->tenant->id)->where('status', 'active')->orderBy('id')->firstOrFail();
    $employee->update(['employee_number' => '1111111111111111']);
    Attendance::where('employee_id', $employee->id)->whereDate('date', '2026-08-12')->delete();

    $attendance = Attendance::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $employee->id,
        'date' => '2026-08-12',
        'clock_in_at' => '2026-08-12 01:01:00',
        'status' => 'leave',
    ]);
    AttendanceCorrection::create([
        'tenant_id' => $this->tenant->id,
        'attendance_id' => $attendance->id,
        'employee_id' => $employee->id,
        'date' => '2026-08-12',
        'correction_type' => 'hr_leave',
        'reason' => 'Sakit, absen kepencet 2 kali',
        'status' => 'approved',
    ]);

    $response = actingAs($this->admin)
        ->get('spec-avana/laporan/export/absensi?format=xlsx&date_from=2026-08-12&date_to=2026-08-12')
        ->assertOk()
        ->assertDownload('rekap-absensi-'.now()->format('Y-m-d').'.xlsx');

    $sheet = IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet();

    expect($sheet->getTitle())->toBe('Rekap Absensi')
        ->and($sheet->getCell('A2')->getValue())->toBe('Rekap Absensi Karyawan')
        ->and((string) $sheet->getCell('A3')->getValue())->toContain('Periode: 12 Agustus 2026')
        ->and((string) $sheet->getCell('A4')->getValue())->toContain('Cuti 1')
        ->and($sheet->getCell('A6')->getValue())->toBe('No')
        ->and($sheet->getCell('K6')->getValue())->toBe('Keterangan')
        ->and($sheet->getFreezePane())->toBe('C7');

    $row = collect(range(7, $sheet->getHighestRow()))
        ->first(fn (int $r): bool => $sheet->getCell("C{$r}")->getValue() === $employee->full_name);

    expect($row)->not->toBeNull()
        // A 16-digit employee number keeps every digit, as text.
        ->and($sheet->getCell("D{$row}")->getValue())->toBe('1111111111111111')
        ->and($sheet->getCell("D{$row}")->getDataType())->toBe(DataType::TYPE_STRING)
        // A real date Excel can sort.
        ->and(Date::isDateTime($sheet->getCell("B{$row}")))->toBeTrue()
        ->and($sheet->getCell("J{$row}")->getValue())->toBe('Cuti')
        ->and($sheet->getStyle("J{$row}")->getFill()->getStartColor()->getRGB())->toBe('DBEAFE')
        ->and($sheet->getCell("K{$row}")->getValue())->toBe('Koreksi HR: Sakit, absen kepencet 2 kali');
});

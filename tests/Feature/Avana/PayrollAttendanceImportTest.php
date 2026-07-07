<?php

use App\Http\Controllers\Avana\PayrollController;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\OvertimeRequest;
use App\Models\PayrollPeriod;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
    $this->period = PayrollPeriod::create([
        'tenant_id' => $this->tenant->id,
        'code' => 'MN-SPEC-ABSEN',
        'name' => 'Spec Absen',
        'cycle' => 'monthly',
        'start_date' => '2026-06-01',
        'end_date' => '2026-06-30',
        'status' => 'draft',
    ]);
    $this->employee = Employee::forTenant($this->tenant->id)->orderBy('id')->firstOrFail();

    Route::middleware('web')->prefix('spec-absen')->group(function (): void {
        Route::get('upload', [PayrollController::class, 'attendanceUpload']);
        Route::get('template', [PayrollController::class, 'attendanceTemplate']);
        Route::post('import', [PayrollController::class, 'importAttendance']);
    });
});

function absenCsv(string $number, int $hariHadir, float $jamLembur): UploadedFile
{
    $content = "employee_number,nama,hari_hadir,jam_lembur\n"
        ."{$number},Nama,{$hariHadir},{$jamLembur}\n";

    return UploadedFile::fake()->createWithContent('absen.csv', $content);
}

it('renders the upload page with the tenant periods', function (): void {
    actingAs($this->admin)
        ->get('spec-absen/upload')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('avana/payroll/absensi', false)
            ->has('periods'));
});

it('streams a CSV template with a row per employee', function (): void {
    $response = actingAs($this->admin)->get('spec-absen/template');

    $response->assertOk();
    expect($response->headers->get('content-type'))->toContain('text/csv');
    expect($response->streamedContent())
        ->toContain('employee_number,nama,hari_hadir,jam_lembur')
        ->toContain($this->employee->employee_number);
});

it('materialises present days and approved overtime from the import', function (): void {
    actingAs($this->admin)
        ->post('spec-absen/import', [
            'period_id' => $this->period->id,
            'file' => absenCsv($this->employee->employee_number, 4, 6),
        ])
        ->assertSessionHas('success');

    $presentRows = Attendance::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('location_status', 'import')
        ->where('status', 'present')
        ->count();

    expect($presentRows)->toBe(4);

    $overtime = OvertimeRequest::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('reason', 'import')
        ->where('status', 'approved')
        ->first();

    expect($overtime)->not->toBeNull();
    expect((float) $overtime->hours)->toBe(6.0);
});

it('replaces imported rows on re-upload without accumulating', function (): void {
    actingAs($this->admin)->post('spec-absen/import', [
        'period_id' => $this->period->id,
        'file' => absenCsv($this->employee->employee_number, 5, 0),
    ]);

    actingAs($this->admin)->post('spec-absen/import', [
        'period_id' => $this->period->id,
        'file' => absenCsv($this->employee->employee_number, 2, 0),
    ]);

    $presentRows = Attendance::forTenant($this->tenant->id)
        ->where('employee_id', $this->employee->id)
        ->where('location_status', 'import')
        ->count();

    expect($presentRows)->toBe(2);
});

it('skips unknown employee numbers and reports them', function (): void {
    actingAs($this->admin)
        ->post('spec-absen/import', [
            'period_id' => $this->period->id,
            'file' => absenCsv('NOPE-999', 3, 0),
        ])
        ->assertSessionHas('success', 'Absensi diimpor untuk 0 karyawan. NIP tak dikenal: NOPE-999');

    $imported = Attendance::forTenant($this->tenant->id)
        ->where('location_status', 'import')
        ->count();

    expect($imported)->toBe(0);
});

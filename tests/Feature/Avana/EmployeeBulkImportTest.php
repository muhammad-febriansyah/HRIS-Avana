<?php

use App\Models\Branch;
use App\Models\Employee;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('downloads the bulk import template as xlsx', function (): void {
    $res = actingAs($this->admin)->get('/avana/employees/bulk/template');

    $res->assertOk();
    expect($res->headers->get('content-type'))->toContain('spreadsheetml');
});

it('previews an uploaded sheet, resolving names and flagging errors', function (): void {
    $csv = <<<'CSV'
    nama_lengkap,email,cabang,departemen,jabatan,status_kepegawaian,password
    Budi Test,budi.test@contoh.co,Jakarta Pusat,Engineering,Software Engineer,Tetap,
    Sari Error,sari.err@contoh.co,Bandung,Financee,Sales Executive,Kontrak,
    ,no.name@contoh.co,Surabaya,Sales,Sales Executive,Tetap,
    CSV;

    $file = UploadedFile::fake()->createWithContent('karyawan.csv', $csv);

    $res = actingAs($this->admin)->post('/avana/employees/bulk/preview', ['file' => $file]);

    $res->assertOk()
        ->assertJsonPath('summary.total', 3)
        ->assertJsonPath('summary.valid', 1)
        // Row 1 resolves cleanly.
        ->assertJsonPath('rows.0.full_name', 'Budi Test')
        ->assertJsonPath('rows.0.employment_status', 'permanent')
        ->assertJsonPath('rows.0.errors', [])
        ->assertJsonPath('rows.0.branch_id', fn ($id): bool => $id !== null)
        ->assertJsonPath('rows.0.department_id', fn ($id): bool => $id !== null)
        // Row 2 has an unknown department.
        ->assertJsonPath('rows.1.errors.department', 'Departemen tak dikenal')
        // Row 3 is missing the required name.
        ->assertJsonPath('rows.2.errors.full_name', 'Nama wajib diisi');
});

it('flags duplicate and already-used emails in the preview', function (): void {
    $existing = Employee::whereNotNull('email')->first()->email;

    $csv = "nama_lengkap,email,cabang,departemen,jabatan,status_kepegawaian,password\n"
        ."Dobel Satu,dupe@contoh.co,Jakarta Pusat,Engineering,Software Engineer,Tetap,\n"
        ."Dobel Dua,dupe@contoh.co,Jakarta Pusat,Engineering,Software Engineer,Tetap,\n"
        ."Sudah Ada,{$existing},Jakarta Pusat,Engineering,Software Engineer,Tetap,\n";

    $file = UploadedFile::fake()->createWithContent('dupe.csv', $csv);

    actingAs($this->admin)->post('/avana/employees/bulk/preview', ['file' => $file])
        ->assertOk()
        ->assertJsonPath('rows.1.errors.email', 'Email duplikat di file')
        ->assertJsonPath('rows.2.errors.email', 'Email sudah terpakai');
});

it('imports the reviewed rows via bulk store', function (): void {
    $branchId = Branch::forTenant($this->admin->tenant_id)->value('id');
    $before = Employee::count();

    actingAs($this->admin)->post('/avana/employees/bulk', [
        // The batch's logins all take this role — the tenant's own, not a
        // built-in "employee" default.
        'role_id' => Role::where('tenant_id', $this->admin->tenant_id)->value('id'),
        'employees' => [
            ['full_name' => 'Import A', 'email' => 'import.a@contoh.co', 'employment_status' => 'permanent', 'status' => 'active', 'branch_id' => $branchId, 'password' => 'password123'],
            ['full_name' => 'Import B', 'email' => null, 'employment_status' => 'contract', 'status' => 'active'],
        ],
    ])->assertRedirect();

    expect(Employee::count())->toBe($before + 2);
    expect(Employee::where('full_name', 'Import A')->exists())->toBeTrue();
});

it('forbids a non-privileged user from the bulk template', function (): void {
    $staff = User::factory()->create(['tenant_id' => $this->admin->tenant_id]);

    actingAs($staff)->get('/avana/employees/bulk/template')->assertForbidden();
});

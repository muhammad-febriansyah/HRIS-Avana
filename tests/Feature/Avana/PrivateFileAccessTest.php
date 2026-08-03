<?php

use App\Models\Employee;
use App\Models\EmployeeDocument;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PrivateFile;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Inertia\Testing\AssertableInertia as Assert;
use Symfony\Component\Finder\Finder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->tenant = Tenant::findOrFail($this->admin->tenant_id);
});

it('keeps an uploaded employee document off the public disk', function (): void {
    Storage::fake('public');
    Storage::fake('local');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)
        ->post(route('avana.dokumen.store'), [
            'employee_id' => $employee->id,
            'name' => 'Kontrak Kerja',
            'type' => 'kontrak',
            'file' => UploadedFile::fake()->create('kontrak.pdf', 12, 'application/pdf'),
        ])
        ->assertRedirect();

    $document = EmployeeDocument::forTenant($this->tenant->id)->latest('id')->firstOrFail();

    expect(Storage::disk('local')->exists($document->file_path))->toBeTrue()
        ->and(Storage::disk('public')->exists($document->file_path))->toBeFalse();
});

it('refuses an unsigned request for a private file', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('documents/1/rahasia.pdf', 'isi dokumen');

    get('/berkas/documents/1/rahasia.pdf')->assertForbidden();
});

it('serves a private file to a signed link without a session', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('documents/1/rahasia.pdf', 'isi dokumen');

    $url = PrivateFile::url('documents/1/rahasia.pdf');

    expect($url)->not->toBeNull();

    get($url)->assertOk();
});

it('refuses a signed link whose path was edited', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('documents/1/milik-saya.pdf', 'punya saya');
    Storage::disk('local')->put('documents/2/milik-orang.pdf', 'punya orang lain');

    $url = (string) PrivateFile::url('documents/1/milik-saya.pdf');
    $tampered = str_replace('documents/1/milik-saya.pdf', 'documents/2/milik-orang.pdf', $url);

    get($tampered)->assertForbidden();
});

it('refuses a link that has expired', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('documents/1/rahasia.pdf', 'isi dokumen');

    $url = URL::temporarySignedRoute('berkas.show', now()->subMinute(), ['path' => 'documents/1/rahasia.pdf']);

    get($url)->assertForbidden();
});

it('hands the documents screen a signed link rather than a storage URL', function (): void {
    Storage::fake('public');
    Storage::fake('local');

    $employee = Employee::forTenant($this->tenant->id)->firstOrFail();

    actingAs($this->admin)->post(route('avana.dokumen.store'), [
        'employee_id' => $employee->id,
        'name' => 'Ijazah',
        'file' => UploadedFile::fake()->create('ijazah.pdf', 8, 'application/pdf'),
    ]);

    actingAs($this->admin)
        ->get(route('avana.dokumen'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $url = (string) collect($page->toArray()['props']['documents'])->firstWhere('name', 'Ijazah')['download_url'];

            expect($url)->toContain('/berkas/')
                ->and($url)->toContain('signature=')
                ->and($url)->not->toContain('/storage/');
        });
});

it('leaves a generated avatar on the public disk', function (): void {
    $url = PrivateFile::urlFor('avatars/user-1.png');

    expect($url)->toContain('/storage/avatars/user-1.png')
        ->and($url)->not->toContain('signature=');
});

it('hands the employee their own document as a signed link', function (): void {
    Storage::fake('public');
    Storage::fake('local');

    $staff = User::whereHas('employee')->where('tenant_id', $this->tenant->id)->firstOrFail();

    EmployeeDocument::create([
        'tenant_id' => $this->tenant->id,
        'employee_id' => $staff->employee->id,
        'name' => 'Slip Gaji',
        'file_path' => 'documents/'.$this->tenant->id.'/slip.pdf',
        'file_size' => 1024,
        'uploaded_at' => now(),
    ]);

    actingAs($staff)
        ->get(route('avana.saya.dokumen'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $url = (string) collect($page->toArray()['props']['documents'])->firstWhere('name', 'Slip Gaji')['url'];

            expect($url)->toContain('/berkas/')
                ->and($url)->toContain('signature=');
        });
});

it('never builds a public URL for a private upload tree', function (): void {
    $offenders = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        $body = $file->getContents();

        if (! str_contains($body, "Storage::disk('public')")) {
            continue;
        }

        foreach (['photo_path', 'cv_path', 'receipt_path'] as $column) {
            // The field-visit and settlement trees are still public on purpose;
            // this guards only the columns that were moved.
            if (preg_match("/Storage::disk\('public'\)->(url|delete)\(\\\$[^)]*(?<![a-z_])".$column.'/', $body) === 1) {
                $offenders[] = $file->getRelativePathname().' → '.$column;
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('never uploads into a private tree on the public disk', function (): void {
    // The trees moved off the public disk. An upload written to one of them
    // with the public disk is both a leak and a broken screen: the reader
    // builds a signed link to the private disk and finds nothing there.
    $trees = [
        'documents', 'employee-documents', 'claims', 'reimbursements', 'recruitment', 'employee-photos',
        'selfies', 'visit-tasks', 'field-visits', 'settlements', 'cash-advances', 'payroll-corrections',
    ];
    $offenders = [];

    foreach (Finder::create()->files()->in(app_path())->name('*.php') as $file) {
        $body = $file->getContents();

        foreach ($trees as $tree) {
            if (preg_match('/->(store|put|storeAs)\([^;]*'.preg_quote($tree, '/')."[^;]*'public'/", $body) === 1) {
                $offenders[] = $file->getRelativePathname().' → '.$tree;
            }
        }
    }

    expect($offenders)->toBe([]);
});

<?php

use App\Models\Sop;
use App\Models\SopCategory;
use App\Models\User;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

/**
 * UI coverage for the SOP library.
 *
 * The upload itself is NOT driven from here: the plugin's test HTTP server
 * parses only `application/x-www-form-urlencoded` bodies and drops file
 * uploads outright (`LaravelHttpServer::handleRequest()`, "@TODO files..."),
 * so any multipart form reaches the controller with an empty payload. The
 * upload, the PDF text extraction and the edit flow are covered over a real
 * request in tests/Feature/Avana/SopManagementTest.php.
 */
uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(AvanaDemoSeeder::class);
    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

it('renders the SOP library with its KPI row', function () {
    actingAs($this->admin);

    $page = visit('/avana/sop');

    $page->assertSee('SOP & Prosedur')
        ->assertSee('Unggah SOP')
        ->assertSee('Total SOP')
        ->assertSee('Terindeks AI')
        ->assertSee('Belum ada dokumen SOP.')
        ->assertNoJavascriptErrors();
});

it('opens the upload modal with the PDF-only hint and both visibility choices', function () {
    actingAs($this->admin);

    $page = visit('/avana/sop');

    $page->click('Unggah SOP')
        ->assertSee('Berkas SOP harus berformat PDF')
        ->assertSee('Hanya PDF, maksimal 10 MB.')
        // A new SOP starts private: nothing reaches the assistant until the
        // admin deliberately opens it up.
        ->assertValue('select[name=visibility]', 'private')
        ->assertSee('Isi SOP (untuk AI Assistant)')
        ->assertNoJavascriptErrors();

    $page->select('select[name=visibility]', 'Public — semua karyawan bisa menanyakannya ke AI Assistant')
        ->assertValue('select[name=visibility]', 'public');
});

it('shows the stored PDF as a card when editing instead of an empty dropzone', function () {
    actingAs($this->admin);

    Sop::factory()->create([
        'tenant_id' => $this->admin->tenant_id,
        'title' => 'SOP Pengajuan Cuti',
        'file_name' => 'sop-pengajuan-cuti.pdf',
        'file_size' => 1741,
    ]);

    $page = visit('/avana/sop');

    $page->click('button[title="Ubah"]')
        ->assertSee('Ubah SOP')
        ->assertSee('sop-pengajuan-cuti.pdf')
        ->assertSee('Berkas tersimpan')
        // Replacing it is opt-in; leaving the card alone keeps the stored PDF.
        ->assertSee('Ganti')
        ->assertDontSee('atau seret ke sini')
        ->assertNoJavascriptErrors();
});

it('creates a jenis SOP through the modal', function () {
    actingAs($this->admin);

    $page = visit('/avana/sop');

    $page->click('Jenis SOP')
        ->assertSee('Tambah Jenis SOP')
        ->type('input[name=name]', 'Kepegawaian')
        ->type('input[name=code]', 'HR')
        ->click('Simpan')
        ->assertSee('Jenis SOP dibuat')
        ->assertNoJavascriptErrors();

    expect(SopCategory::where('name', 'Kepegawaian')->exists())->toBeTrue();
});

it('renames a jenis SOP from the jenis tab', function () {
    actingAs($this->admin);

    $category = SopCategory::create([
        'tenant_id' => $this->admin->tenant_id,
        'name' => 'Kepegawaian',
        'status' => 'active',
    ]);

    $page = visit('/avana/sop');

    $page->click('button[role=tab][aria-label="Jenis SOP"]')
        ->assertSee('Kepegawaian')
        ->click('button[title="Ubah"]')
        ->assertSee('Ubah Jenis SOP')
        ->fill('input[name=name]', 'Kepegawaian & Umum')
        ->click('Simpan')
        ->assertSee('Jenis SOP diperbarui')
        ->assertNoJavascriptErrors();

    expect($category->refresh()->name)->toBe('Kepegawaian & Umum');
});

it('lists an SOP with its visibility and AI indexing state', function () {
    actingAs($this->admin);

    $category = SopCategory::create([
        'tenant_id' => $this->admin->tenant_id,
        'name' => 'Kepegawaian',
        'status' => 'active',
    ]);

    Sop::factory()->publicVisibility()->create([
        'tenant_id' => $this->admin->tenant_id,
        'sop_category_id' => $category->id,
        'title' => 'SOP Pengajuan Cuti',
        'code' => 'SOP-HR-001',
    ]);

    Sop::factory()->create([
        'tenant_id' => $this->admin->tenant_id,
        'title' => 'SOP Rahasia Direksi',
        'content' => null,
    ]);

    $page = visit('/avana/sop');

    $page->assertSee('SOP Pengajuan Cuti')
        ->assertSee('SOP-HR-001')
        ->assertSee('Kepegawaian')
        ->assertSee('Public')
        ->assertSee('Terindeks')
        ->assertSee('SOP Rahasia Direksi')
        ->assertSee('Private')
        // A PDF whose text could not be read is flagged so the admin can
        // paste the content in by hand.
        ->assertSee('Belum terbaca')
        ->assertNoJavascriptErrors();
});

it('filters the SOP list down to the public documents', function () {
    actingAs($this->admin);

    Sop::factory()->publicVisibility()->create([
        'tenant_id' => $this->admin->tenant_id,
        'title' => 'SOP Pengajuan Cuti',
    ]);

    Sop::factory()->create([
        'tenant_id' => $this->admin->tenant_id,
        'title' => 'SOP Rahasia Direksi',
    ]);

    $page = visit('/avana/sop');

    $page->assertSee('SOP Rahasia Direksi')
        ->select('select[name=filter_tipe]', 'Public')
        ->assertSee('SOP Pengajuan Cuti')
        ->assertDontSee('SOP Rahasia Direksi')
        ->assertNoJavascriptErrors();
});

it('deletes an SOP after confirmation', function () {
    actingAs($this->admin);

    Sop::factory()->create([
        'tenant_id' => $this->admin->tenant_id,
        'title' => 'SOP Perjalanan Dinas',
    ]);

    $page = visit('/avana/sop');

    $page->assertSee('SOP Perjalanan Dinas')
        ->click('button[title="Hapus"]')
        ->assertSee('Hapus SOP?')
        ->click('button[aria-label="Konfirmasi hapus"]')
        ->assertSee('SOP dihapus')
        ->assertDontSee('SOP Perjalanan Dinas')
        ->assertNoJavascriptErrors();

    expect(Sop::count())->toBe(0);
});

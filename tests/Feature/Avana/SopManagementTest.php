<?php

use App\Models\Sop;
use App\Models\SopCategory;
use App\Models\User;
use App\Services\AiToolkit;
use App\Support\PdfTextExtractor;
use Barryvdh\DomPDF\Facade\Pdf;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    $this->admin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
    $this->employee = User::where('email', 'bagus.p@nusantara.co.id')->firstOrFail();
    $this->tenantId = (int) $this->admin->tenant_id;
});

/**
 * A minimal, uncompressed PDF whose single content stream draws one line of
 * text — enough for {@see PdfTextExtractor} to read back.
 */
function fakeSopPdf(string $line = 'Pengajuan cuti tahunan diajukan minimal tiga hari kerja sebelumnya.'): string
{
    return implode("\n", [
        '%PDF-1.4',
        '1 0 obj<</Type/Catalog/Pages 2 0 R>>endobj',
        '2 0 obj<</Type/Pages/Kids[3 0 R]/Count 1>>endobj',
        '3 0 obj<</Type/Page/Parent 2 0 R/MediaBox[0 0 612 792]/Contents 4 0 R>>endobj',
        '4 0 obj<</Length '.(strlen($line) + 40).'>>',
        'stream',
        'BT /F1 12 Tf 72 720 Td ('.$line.') Tj ET',
        'endstream',
        'endobj',
        'trailer<</Root 1 0 R>>',
        '%%EOF',
    ]);
}

function fakeSopUpload(string $name = 'sop-cuti.pdf'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, fakeSopPdf());
}

/**
 * A real, dompdf-rendered SOP: compressed content streams and a subset font,
 * i.e. what an admin actually uploads. Guards {@see PdfTextExtractor} against
 * only ever being proved on the hand-written fixture above.
 */
function realSopUpload(string $name = 'sop-cuti-asli.pdf'): UploadedFile
{
    $html = '<h1>SOP Pengajuan Cuti Karyawan</h1>'
        .'<p>Nomor SOP-HR-001, versi 1.0, berlaku 1 Januari 2026.</p>'
        .'<p>1. Karyawan mengajukan cuti melalui aplikasi minimal 3 hari kerja sebelumnya.</p>'
        .'<p>2. Atasan langsung menyetujui atau menolak maksimal 2x24 jam.</p>'
        .'<p>3. HR memverifikasi sisa saldo cuti karyawan.</p>';

    return UploadedFile::fake()->createWithContent($name, Pdf::loadHTML($html)->output());
}

it('renders the SOP index for a tenant admin', function (): void {
    $category = SopCategory::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Kepegawaian',
        'status' => 'active',
    ]);

    Sop::factory()->publicVisibility()->create([
        'tenant_id' => $this->tenantId,
        'sop_category_id' => $category->id,
        'effective_date' => '2026-01-01',
    ]);

    actingAs($this->admin)
        ->get(route('avana.sop'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/sop/index', false)
            ->has('categories', 1)
            ->has('sops', 1, fn (Assert $row) => $row
                ->where('category', 'Kepegawaian')
                ->where('visibility', 'public')
                ->where('effective_date', '2026-01-01')
                ->where('has_content', true)
                ->etc())
            ->where('kpis.total', 1)
            ->where('kpis.public', 1)
            ->where('kpis.private', 0));
});

it('forbids a plain employee from opening the SOP admin screen', function (): void {
    actingAs($this->employee)
        ->get(route('avana.sop'))
        ->assertForbidden();
});

it('creates a jenis SOP', function (): void {
    actingAs($this->admin)
        ->post(route('avana.sop.jenis.store'), [
            'name' => 'Keuangan',
            'code' => 'FIN',
            'description' => 'SOP proses keuangan',
            'status' => 'active',
        ])
        ->assertRedirect();

    expect(SopCategory::forTenant($this->tenantId)->where('name', 'Keuangan')->exists())->toBeTrue();
});

it('rejects a duplicate jenis SOP name within the tenant', function (): void {
    SopCategory::create(['tenant_id' => $this->tenantId, 'name' => 'Keuangan', 'status' => 'active']);

    actingAs($this->admin)
        ->post(route('avana.sop.jenis.store'), ['name' => 'Keuangan', 'status' => 'active'])
        ->assertSessionHasErrors('name');
});

it('uploads a PDF SOP and indexes its text for the assistant', function (): void {
    Storage::fake('local');

    $category = SopCategory::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Kepegawaian',
        'status' => 'active',
    ]);

    actingAs($this->admin)
        ->post(route('avana.sop.store'), [
            'sop_category_id' => $category->id,
            'code' => 'SOP-HR-001',
            'title' => 'SOP Pengajuan Cuti',
            'summary' => 'Alur pengajuan cuti karyawan',
            'visibility' => 'public',
            'status' => 'active',
            'version' => '1.0',
            'effective_date' => '2026-01-01',
            'file' => fakeSopUpload(),
        ])
        ->assertRedirect();

    $sop = Sop::forTenant($this->tenantId)->firstOrFail();

    expect($sop->title)->toBe('SOP Pengajuan Cuti')
        ->and($sop->visibility)->toBe('public')
        ->and($sop->sop_category_id)->toBe($category->id)
        ->and($sop->content)->toContain('cuti tahunan');

    Storage::disk('local')->assertExists($sop->file_path);
});

it('rejects a non-PDF SOP upload', function (): void {
    Storage::fake('local');

    actingAs($this->admin)
        ->post(route('avana.sop.store'), [
            'title' => 'SOP Salah Format',
            'visibility' => 'private',
            'status' => 'active',
            'file' => UploadedFile::fake()->create('sop.docx', 20),
        ])
        ->assertSessionHasErrors('file');

    expect(Sop::forTenant($this->tenantId)->count())->toBe(0);
});

it('updates an SOP visibility without replacing the file', function (): void {
    $sop = Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'visibility' => 'private',
        'file_path' => 'sop/'.$this->tenantId.'/existing.pdf',
    ]);

    actingAs($this->admin)
        ->post(route('avana.sop.update', $sop), [
            'title' => $sop->title,
            'visibility' => 'public',
            'status' => 'active',
        ])
        ->assertRedirect();

    $sop->refresh();

    expect($sop->visibility)->toBe('public')
        ->and($sop->file_path)->toBe('sop/'.$this->tenantId.'/existing.pdf');
});

it('deletes an SOP together with its stored file', function (): void {
    Storage::fake('local');
    Storage::disk('local')->put('sop/'.$this->tenantId.'/doc.pdf', fakeSopPdf());

    $sop = Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'file_path' => 'sop/'.$this->tenantId.'/doc.pdf',
    ]);

    actingAs($this->admin)
        ->delete(route('avana.sop.destroy', $sop))
        ->assertRedirect();

    expect(Sop::forTenant($this->tenantId)->count())->toBe(0);
    Storage::disk('local')->assertMissing('sop/'.$this->tenantId.'/doc.pdf');
});

it('keeps documents when their jenis SOP is deleted', function (): void {
    $category = SopCategory::create([
        'tenant_id' => $this->tenantId,
        'name' => 'Kepegawaian',
        'status' => 'active',
    ]);

    $sop = Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'sop_category_id' => $category->id,
    ]);

    actingAs($this->admin)
        ->delete(route('avana.sop.jenis.destroy', $category))
        ->assertRedirect();

    expect($sop->refresh()->sop_category_id)->toBeNull()
        ->and(SopCategory::forTenant($this->tenantId)->count())->toBe(0);
});

it('never shows a private SOP to an employee through the assistant', function (): void {
    Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'title' => 'SOP Rahasia Direksi',
        'content' => 'Prosedur rahasia untuk pengajuan cuti direksi.',
        'visibility' => 'private',
    ]);

    Sop::factory()->publicVisibility()->create([
        'tenant_id' => $this->tenantId,
        'title' => 'SOP Pengajuan Cuti',
        'content' => 'Prosedur umum untuk pengajuan cuti karyawan.',
    ]);

    $tool = collect(AiToolkit::forUser($this->employee))
        ->firstWhere(fn ($candidate): bool => $candidate->name() === 'baca_sop');

    $answer = $tool->handle(kata_kunci: 'cuti');

    expect($answer)->toContain('SOP Pengajuan Cuti')
        ->not->toContain('SOP Rahasia Direksi');
});

it('shows private SOPs to a user holding the sop.view permission', function (): void {
    Sop::factory()->create([
        'tenant_id' => $this->tenantId,
        'title' => 'SOP Rahasia Direksi',
        'content' => 'Prosedur rahasia untuk pengajuan cuti direksi.',
        'visibility' => 'private',
    ]);

    $tool = collect(AiToolkit::forUser($this->admin))
        ->firstWhere(fn ($candidate): bool => $candidate->name() === 'baca_sop');

    expect($tool->handle(kata_kunci: 'cuti'))->toContain('SOP Rahasia Direksi');
});

it('hides inactive SOPs from the assistant', function (): void {
    Sop::factory()->publicVisibility()->inactive()->create([
        'tenant_id' => $this->tenantId,
        'title' => 'SOP Lama',
        'content' => 'Prosedur lama pengajuan cuti.',
    ]);

    $tool = collect(AiToolkit::forUser($this->employee))
        ->firstWhere(fn ($candidate): bool => $candidate->name() === 'daftar_sop');

    expect($tool->handle())->not->toContain('SOP Lama');
});

it('extracts readable text from a PDF content stream', function (): void {
    expect(PdfTextExtractor::fromString(fakeSopPdf()))
        ->toContain('Pengajuan cuti tahunan');
});

it('indexes a real dompdf-rendered SOP end to end', function (): void {
    Storage::fake('local');

    actingAs($this->admin)
        ->post(route('avana.sop.store'), [
            'title' => 'SOP Pengajuan Cuti',
            'visibility' => 'public',
            'status' => 'active',
            'file' => realSopUpload(),
        ])
        ->assertRedirect();

    $sop = Sop::forTenant($this->tenantId)->firstOrFail();

    expect($sop->content)
        ->toContain('SOP Pengajuan Cuti Karyawan')
        ->toContain('minimal 3 hari kerja')
        ->toContain('HR memverifikasi sisa saldo cuti');

    // And the assistant can answer from it.
    $tool = collect(AiToolkit::forUser($this->employee))
        ->firstWhere(fn ($candidate): bool => $candidate->name() === 'baca_sop');

    expect($tool->handle(kata_kunci: 'cuti'))->toContain('minimal 3 hari kerja');
});

it('returns nothing for a PDF with no readable text', function (): void {
    expect(PdfTextExtractor::fromString('%PDF-1.4 no streams here %%EOF'))->toBe('');
});

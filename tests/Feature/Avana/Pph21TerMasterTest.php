<?php

use App\Exceptions\Pph21ConfigurationException;
use App\Models\AuditLog;
use App\Models\PayrollPeriod;
use App\Models\Pph21TerCategory;
use App\Models\Pph21TerRate;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Pph21Ter;
use App\Support\Pph21TerImport;
use Database\Seeders\AvanaDemoSeeder;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

beforeEach(function (): void {
    $this->withoutVite();
    $this->seed(AvanaDemoSeeder::class);

    Pph21Ter::forget();

    $this->superAdmin = User::whereHas('roles', fn ($q) => $q->where('code', 'super_admin'))->firstOrFail();
    $this->hrAdmin = User::where('email', 'rina.a@nusantara.co.id')->firstOrFail();
});

/**
 * The reference workbook, kept in the repository so the test does not depend on
 * a file in somebody's Downloads folder.
 */
function officialWorkbook(): string
{
    return base_path('tests/Fixtures/ter/Setup_TER_PPh21.xlsx');
}

function terUpload(): UploadedFile
{
    return new UploadedFile(officialWorkbook(), 'Setup_TER_PPh21.xlsx', null, null, true);
}

/**
 * Preview a workbook and hand back the token the publish step needs.
 *
 * @return array{token: string, preview: array<string, mixed>}
 */
function previewWorkbook(User $actor, string $from = '2027-01-01', string $reason = 'Uji terbit versi baru dari workbook resmi'): array
{
    $response = actingAs($actor)->post(route('avana.payroll.ter.preview'), [
        'file' => terUpload(),
        'effective_start_date' => $from,
        'source' => 'PMK contoh',
        'reason' => $reason,
    ]);

    $preview = session('terPreview');

    expect($preview)->not->toBeNull();

    $response->assertRedirect();

    return ['token' => $preview['token'], 'preview' => $preview];
}

/**
 * Publish a bracket set for a category from a date.
 *
 * @param  list<array{0: float|int, 1: float|int|null, 2: float}>  $brackets
 */
function publishTer(string $category, array $brackets, string $from): void
{
    foreach ($brackets as [$min, $max, $rate]) {
        Pph21TerRate::create([
            'category' => $category,
            'income_min' => $min,
            'income_max' => $max,
            'rate' => $rate,
            'effective_start_date' => $from,
        ]);
    }

    Pph21Ter::forget();
}

it('seeds the statutory PP 58/2023 tariff into the master', function (): void {
    expect(Pph21TerRate::where('category', 'A')->count())->toBe(42);
    expect(Pph21TerRate::where('category', 'B')->count())->toBe(39);
    expect(Pph21TerRate::where('category', 'C')->count())->toBe(41);
    expect(Pph21TerRate::where('category', 'HARIAN')->count())->toBe(2);
    expect(Pph21TerCategory::count())->toBe(8);
});

it('reads the rate from the master, matching the statutory table', function (): void {
    expect(Pph21Ter::monthlyRate('A', 6_500_000))->toBe(0.01);
    expect(Pph21Ter::monthlyRate('A', 10_000_000))->toBe(0.02);
    expect(Pph21Ter::monthlyRate('B', 6_200_000))->toBe(0.0);
    expect(Pph21Ter::monthlyRate('C', 1_500_000_000))->toBe(0.34);
    expect(Pph21Ter::dailyRate(400_000))->toBe(0.0);
    expect(Pph21Ter::dailyRate(1_000_000))->toBe(0.005);
    expect(Pph21Ter::category('K/3'))->toBe('C');
});

it('falls back to the statutory table when the master is empty', function (): void {
    Pph21TerRate::query()->delete();
    Pph21TerCategory::query()->delete();
    Pph21Ter::forget();

    expect(Pph21Ter::monthlyRate('A', 10_000_000))->toBe(0.02);
    expect(Pph21Ter::dailyRate(1_000_000))->toBe(0.005);
    expect(Pph21Ter::category('K/2'))->toBe('B');
});

it('refuses to guess a category for a PTKP status nobody mapped', function (): void {
    expect(Pph21Ter::hasCategory('K/3'))->toBeTrue();
    expect(Pph21Ter::hasCategory(null))->toBeFalse();
    expect(Pph21Ter::hasCategory('TK/9'))->toBeFalse();

    expect(fn () => Pph21Ter::categoryOrFail(null))
        ->toThrow(Pph21ConfigurationException::class);
    expect(fn () => Pph21Ter::categoryOrFail('TK/9'))
        ->toThrow(Pph21ConfigurationException::class);
});

it('applies a new tariff from its start date and leaves earlier months alone', function (): void {
    // A deliberately different table, effective mid-2027.
    publishTer('A', [[0, 20_000_000, 0.0], [20_000_000, null, 0.11]], '2027-07-01');

    // Before it takes effect the 2024 table still answers.
    expect(Pph21Ter::monthlyRate('A', 10_000_000, '2027-06-30'))->toBe(0.02);
    // From its start date the new one does.
    expect(Pph21Ter::monthlyRate('A', 10_000_000, '2027-07-01'))->toBe(0.0);
    expect(Pph21Ter::monthlyRate('A', 30_000_000, '2027-07-01'))->toBe(0.11);
});

it('follows a PTKP status moved to another category on its own date', function (): void {
    Pph21TerCategory::create([
        'ptkp_status' => 'K/0',
        'category' => 'C',
        'effective_start_date' => '2027-01-01',
    ]);
    Pph21TerCategory::where('ptkp_status', 'K/0')
        ->whereDate('effective_start_date', '2024-01-01')
        ->update(['effective_end_date' => '2026-12-31']);
    Pph21Ter::forget();

    expect(Pph21Ter::category('K/0', '2026-12-31'))->toBe('A');
    expect(Pph21Ter::category('K/0', '2027-01-01'))->toBe('C');
});

it('reproduces the worked example of the workbook calculator', function (): void {
    // Sheet "Kalkulator": K/0 on Rp12.000.000 bruto resolves to Kategori A at
    // 3,25%, withholding Rp390.000.
    $category = Pph21Ter::category('K/0');
    $rate = Pph21Ter::monthlyRate($category, 12_000_000);

    expect($category)->toBe('A');
    expect($rate)->toBe(0.0325);
    expect(round(12_000_000 * $rate))->toBe(390_000.0);
});

it('ships the calculator its own lookup tables', function (): void {
    // The screen resolves category and rate in the browser, so the brackets
    // and the PTKP mapping both have to reach it.
    actingAs($this->hrAdmin)
        ->get(route('avana.payroll.ter'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $props = $page->toArray()['props'];
            $categoryA = collect($props['categories'])->firstWhere('code', 'A');
            $mapped = collect($props['categoryMap'])->firstWhere('ptkp_status', 'K/0');

            expect($mapped['category'])->toBe('A');
            expect($categoryA['brackets'])->toHaveCount(42);

            // The same lookup the page does: first band whose ceiling clears
            // the gross.
            $band = collect($categoryA['brackets'])
                ->first(fn (array $b): bool => $b['income_max'] === null || $b['income_max'] >= 12_000_000);

            expect($band['rate'])->toBe(0.0325);
        });
});

it('renders the Tarif TER screen for someone who can see payroll', function (): void {
    actingAs($this->hrAdmin)
        ->get(route('avana.payroll.ter'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('avana/payroll-ter/index', false)
            ->has('categories', 4)
            ->has('categoryMap', 8)
            ->where('canManage', false)
            ->etc());
});

it('keeps a tenant admin out of every write endpoint', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.payroll.ter'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', true)->etc());

    $rate = Pph21TerRate::where('category', 'A')->orderBy('id')->firstOrFail();
    $intent = ['effective_start_date' => '2027-01-01', 'reason' => 'Percobaan tanpa hak akses'];

    actingAs($this->hrAdmin)->post(route('avana.payroll.ter.reset'), $intent)->assertForbidden();
    actingAs($this->hrAdmin)->post(route('avana.payroll.ter.preview'), [
        'file' => terUpload(),
        'source' => 'PMK contoh',
        ...$intent,
    ])->assertForbidden();
    actingAs($this->hrAdmin)->post(route('avana.payroll.ter.import'), [
        'file' => terUpload(),
        'source' => 'PMK contoh',
        'preview_token' => 'x',
        ...$intent,
    ])->assertForbidden();
    actingAs($this->hrAdmin)->put(route('avana.payroll.ter.bracket.update', $rate), [
        'income_min' => 0, 'income_max' => 5_400_000, 'rate' => 0.01, ...$intent,
    ])->assertForbidden();
    actingAs($this->hrAdmin)->delete(route('avana.payroll.ter.bracket.destroy', $rate), $intent)->assertForbidden();
    actingAs($this->hrAdmin)->post(route('avana.payroll.ter.kategori'), [
        'ptkp_status' => 'K/0', 'category' => 'B', ...$intent,
    ])->assertForbidden();
});

it('reports a gap in the tariff rather than letting it withhold nothing', function (): void {
    Pph21TerRate::where('category', 'A')->delete();
    publishTer('A', [[0, 10_000_000, 0.0], [12_000_000, null, 0.05]], '2024-01-01');

    actingAs($this->hrAdmin)
        ->get(route('avana.payroll.ter'))
        ->assertOk()
        ->assertInertia(function (Assert $page): void {
            $categories = collect($page->toArray()['props']['categories']);
            $issues = $categories->firstWhere('code', 'A')['issues'];

            expect($issues)->toContain('Ada celah antara 10.000.000 dan 12.000.000.');
        });
});

it('previews a workbook without writing anything', function (): void {
    ['preview' => $preview] = previewWorkbook($this->superAdmin);

    expect(Pph21TerRate::whereDate('effective_start_date', '2027-01-01')->count())->toBe(0);
    expect($preview['blockers'])->toBe([]);
    expect($preview['file_name'])->toBe('Setup_TER_PPh21.xlsx');
    expect(collect($preview['categories'])->pluck('incoming_brackets', 'code')->all())
        ->toBe(['A' => 42, 'B' => 39, 'C' => 41, 'HARIAN' => 2]);
    expect(collect($preview['category_map'])->every(fn (array $row): bool => $row['incoming'] !== null))->toBeTrue();
});

it('refuses to publish an import that was never previewed', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.import'), [
            'file' => terUpload(),
            'effective_start_date' => '2027-01-01',
            'source' => 'PMK contoh',
            'reason' => 'Uji terbit tanpa pratinjau',
            'preview_token' => 'token-palsu',
        ])
        ->assertSessionHasErrors('preview_token');

    expect(Pph21TerRate::whereDate('effective_start_date', '2027-01-01')->count())->toBe(0);
});

it('refuses a preview token issued for a different effective date', function (): void {
    ['token' => $token] = previewWorkbook($this->superAdmin, '2027-01-01');

    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.import'), [
            'file' => terUpload(),
            'effective_start_date' => '2027-02-01',
            'source' => 'PMK contoh',
            'reason' => 'Uji terbit versi baru dari workbook resmi',
            'preview_token' => $token,
        ])
        ->assertSessionHasErrors('preview_token');

    expect(Pph21TerRate::whereDate('effective_start_date', '2027-02-01')->count())->toBe(0);
});

it('publishes a previewed workbook as a new dated version', function (): void {
    ['token' => $token] = previewWorkbook($this->superAdmin);

    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.import'), [
            'file' => terUpload(),
            'effective_start_date' => '2027-01-01',
            'source' => 'PMK contoh',
            'reason' => 'Uji terbit versi baru dari workbook resmi',
            'preview_token' => $token,
        ])
        ->assertSessionHas('success');

    Pph21Ter::forget();

    // The 2024 set is closed the day before, the imported one runs on.
    expect(Pph21TerRate::where('category', 'A')->whereDate('effective_start_date', '2024-01-01')->value('effective_end_date')?->toDateString())
        ->toBe('2026-12-31');
    expect(Pph21TerRate::where('category', 'A')->whereDate('effective_start_date', '2027-01-01')->count())->toBe(42);
    expect(Pph21TerRate::where('category', 'B')->whereDate('effective_start_date', '2027-01-01')->count())->toBe(39);
    expect(Pph21TerRate::where('category', 'C')->whereDate('effective_start_date', '2027-01-01')->count())->toBe(41);

    // Author, reason and workbook checksum are on every published row.
    $row = Pph21TerRate::where('category', 'A')->whereDate('effective_start_date', '2027-01-01')->firstOrFail();
    expect((int) $row->created_by)->toBe($this->superAdmin->id);
    expect($row->change_reason)->toBe('Uji terbit versi baru dari workbook resmi');
    expect($row->source_checksum)->toBe(hash_file('sha256', officialWorkbook()));

    // And it resolves to the same numbers, since the file is the same tariff.
    expect(Pph21Ter::monthlyRate('A', 10_000_000, '2027-01-01'))->toBe(0.02);
    expect(Pph21Ter::monthlyRate('C', 1_500_000_000, '2027-01-01'))->toBe(0.34);
    expect(Pph21Ter::category('K/3', '2027-01-01'))->toBe('C');
});

it('matches the reference workbook bracket for bracket', function (): void {
    $parsed = Pph21TerImport::parse(officialWorkbook());

    foreach (['A', 'B', 'C', 'HARIAN'] as $category) {
        $master = Pph21TerRate::where('category', $category)
            ->whereDate('effective_start_date', '2024-01-01')
            ->orderBy('income_min')
            ->get();

        expect($master)->toHaveCount(count($parsed['brackets'][$category]));

        foreach ($parsed['brackets'][$category] as $index => $bracket) {
            expect((float) $master[$index]->income_min)->toBe($bracket['income_min']);
            expect($master[$index]->income_max !== null ? (float) $master[$index]->income_max : null)
                ->toBe($bracket['income_max']);
            expect((float) $master[$index]->rate)->toBe($bracket['rate']);
        }
    }

    expect($parsed['categories'])->toBe(Pph21Ter::statutoryCategoryMap());
});

it('refuses a second publication on an effective date already taken', function (): void {
    ['token' => $token] = previewWorkbook($this->superAdmin);

    $publish = fn (string $token) => actingAs($this->superAdmin)->post(route('avana.payroll.ter.import'), [
        'file' => terUpload(),
        'effective_start_date' => '2027-01-01',
        'source' => 'PMK contoh',
        'reason' => 'Uji terbit versi baru dari workbook resmi',
        'preview_token' => $token,
    ]);

    $publish($token)->assertSessionHas('success');

    // A second super admin racing the same date with their own valid preview
    // is refused rather than interleaving two tariffs on one day.
    ['token' => $second] = previewWorkbook($this->superAdmin);
    $publish($second)->assertSessionHasErrors('effective_start_date');

    expect(Pph21TerRate::where('category', 'A')->whereDate('effective_start_date', '2027-01-01')->count())->toBe(42);
});

it('refuses an effective date that reaches into a locked payroll period', function (): void {
    PayrollPeriod::create([
        'tenant_id' => Tenant::query()->value('id'),
        'code' => 'MN-2027-01',
        'name' => 'Januari 2027',
        'cycle' => 'monthly',
        'start_date' => '2027-01-01',
        'end_date' => '2027-01-31',
        'status' => 'locked',
    ]);

    ['preview' => $preview] = previewWorkbook($this->superAdmin);

    expect($preview['blockers'])->not->toBe([]);

    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.import'), [
            'file' => terUpload(),
            'effective_start_date' => '2027-01-01',
            'source' => 'PMK contoh',
            'reason' => 'Uji terbit versi baru dari workbook resmi',
            'preview_token' => $preview['token'],
        ])
        ->assertSessionHasErrors('effective_start_date');

    expect(Pph21TerRate::whereDate('effective_start_date', '2027-01-01')->count())->toBe(0);
});

it('rejects a file that is not a workbook', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.preview'), [
            'file' => UploadedFile::fake()->createWithContent('tarif.xlsx', 'bukan zip'),
            'effective_start_date' => '2027-01-01',
            'source' => 'PMK contoh',
            'reason' => 'Uji berkas rusak agar ditolak',
        ])
        ->assertSessionHasErrors('file');

    expect(Pph21TerRate::whereDate('effective_start_date', '2027-01-01')->count())->toBe(0);
});

it('restores the statutory tariff on demand', function (): void {
    Pph21TerRate::where('category', 'A')->delete();
    Pph21Ter::forget();

    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.reset'), [
            'effective_start_date' => '2027-01-01',
            'reason' => 'Kembali ke tarif statutori setelah impor keliru',
        ])
        ->assertSessionHas('success');

    Pph21Ter::forget();

    expect(Pph21TerRate::where('category', 'A')->whereDate('effective_start_date', '2027-01-01')->count())->toBe(42);
    expect(Pph21Ter::monthlyRate('A', 10_000_000, '2027-01-01'))->toBe(0.02);
});

it('refuses a reset with no reason given', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.reset'), ['effective_start_date' => '2027-01-01'])
        ->assertSessionHasErrors('reason');

    expect(Pph21TerRate::whereDate('effective_start_date', '2027-01-01')->count())->toBe(0);
});

it('corrects a bracket by publishing a new version instead of editing history', function (): void {
    $bracket = Pph21TerRate::where('category', 'A')
        ->whereDate('effective_start_date', '2024-01-01')
        ->orderBy('income_min')
        ->skip(1)
        ->first();

    actingAs($this->superAdmin)
        ->put(route('avana.payroll.ter.bracket.update', $bracket), [
            'income_min' => (float) $bracket->income_min,
            'income_max' => (float) $bracket->income_max,
            'rate' => 0.003,
            'effective_start_date' => '2027-01-01',
            'reason' => 'Koreksi salah ketik tarif bracket kedua',
        ])
        ->assertSessionHas('success');

    Pph21Ter::forget();

    // The row payroll already read is untouched, and closed the day before.
    expect((float) $bracket->fresh()->rate)->toBe(0.0025);
    expect($bracket->fresh()->effective_end_date?->toDateString())->toBe('2026-12-31');

    // The correction lives in the new version only.
    expect(Pph21Ter::monthlyRate('A', 5_500_000, '2026-12-31'))->toBe(0.0025);
    expect(Pph21Ter::monthlyRate('A', 5_500_000, '2027-01-01'))->toBe(0.003);
});

it('refuses a rate typed as a percentage instead of a decimal', function (): void {
    $bracket = Pph21TerRate::where('category', 'A')->orderBy('income_max')->first();

    actingAs($this->superAdmin)
        ->put(route('avana.payroll.ter.bracket.update', $bracket), [
            'income_min' => 0,
            'income_max' => 5_400_000,
            'rate' => 25,
            'effective_start_date' => '2027-01-01',
            'reason' => 'Uji tarif ditulis sebagai persen',
        ])
        ->assertSessionHasErrors('rate');
});

it('refuses to delete a bracket that would leave a hole in the tariff', function (): void {
    $middle = Pph21TerRate::where('category', 'A')
        ->whereDate('effective_start_date', '2024-01-01')
        ->orderBy('income_min')
        ->skip(2)
        ->first();

    actingAs($this->superAdmin)
        ->delete(route('avana.payroll.ter.bracket.destroy', $middle), [
            'effective_start_date' => '2027-01-01',
            'reason' => 'Uji hapus bracket tengah yang meninggalkan celah',
        ])
        ->assertSessionHasErrors('tarif');

    expect(Pph21TerRate::whereDate('effective_start_date', '2027-01-01')->count())->toBe(0);
    expect($middle->fresh()->effective_end_date)->toBeNull();
});

it('moves a PTKP status by republishing the whole mapping', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.kategori'), [
            'ptkp_status' => 'K/0',
            'category' => 'C',
            'effective_start_date' => '2027-01-01',
            'reason' => 'Mengikuti lampiran PMK terbaru untuk status K/0',
        ])
        ->assertSessionHas('success');

    Pph21Ter::forget();

    expect(Pph21Ter::category('K/0', '2026-12-31'))->toBe('A');
    expect(Pph21Ter::category('K/0', '2027-01-01'))->toBe('C');
    // The other seven statuses come along in the same dated version.
    expect(Pph21TerCategory::whereDate('effective_start_date', '2027-01-01')->count())->toBe(8);
    expect(Pph21Ter::category('K/3', '2027-01-01'))->toBe('C');
    expect(Pph21Ter::category('TK/0', '2027-01-01'))->toBe('A');
});

it('writes an audit trail for a published version', function (): void {
    ['token' => $token] = previewWorkbook($this->superAdmin);

    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.import'), [
            'file' => terUpload(),
            'effective_start_date' => '2027-01-01',
            'source' => 'PMK contoh',
            'reason' => 'Uji terbit versi baru dari workbook resmi',
            'preview_token' => $token,
        ])
        ->assertSessionHas('success');

    $created = AuditLog::where('auditable_type', Pph21TerRate::class)->where('action', 'created')->get();
    $closed = AuditLog::where('auditable_type', Pph21TerRate::class)->where('action', 'updated')->get();

    expect($created)->toHaveCount(124);
    expect($closed)->not->toBeEmpty();
    expect((int) $created->first()->user_id)->toBe($this->superAdmin->id);
    // The closing of the superseded version records what changed.
    expect($closed->first()->new_values)->toHaveKey('effective_end_date');
    expect(AuditLog::where('auditable_type', Pph21TerCategory::class)->where('action', 'created')->count())->toBe(8);
});

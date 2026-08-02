<?php

use App\Models\Pph21TerCategory;
use App\Models\Pph21TerRate;
use App\Models\User;
use App\Support\Pph21Ter;
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

/** The workbook the client publishes the tariff in. */
function officialWorkbook(): string
{
    return '/Users/muhammadfebriansyah/Downloads/Setup_TER_PPh21 (2).xlsx';
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

it('lets a super admin manage but keeps a tenant admin out of the writes', function (): void {
    actingAs($this->superAdmin)
        ->get(route('avana.payroll.ter'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('canManage', true)->etc());

    actingAs($this->hrAdmin)
        ->post(route('avana.payroll.ter.reset'), ['effective_start_date' => '2027-01-01'])
        ->assertForbidden();
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

it('imports the official workbook as a new dated version', function (): void {
    $path = officialWorkbook();

    if (! is_file($path)) {
        $this->markTestSkipped('Workbook resmi tidak ada di mesin ini.');
    }

    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.import'), [
            'file' => new UploadedFile($path, 'Setup_TER_PPh21.xlsx', null, null, true),
            'effective_start_date' => '2027-01-01',
            'source' => 'PMK contoh',
        ])
        ->assertSessionHas('success');

    Pph21Ter::forget();

    // The 2024 set is closed the day before, the imported one runs on.
    expect(Pph21TerRate::where('category', 'A')->whereDate('effective_start_date', '2024-01-01')->value('effective_end_date')?->toDateString())
        ->toBe('2026-12-31');
    expect(Pph21TerRate::where('category', 'A')->whereDate('effective_start_date', '2027-01-01')->count())->toBe(42);
    expect(Pph21TerRate::where('category', 'B')->whereDate('effective_start_date', '2027-01-01')->count())->toBe(39);
    expect(Pph21TerRate::where('category', 'C')->whereDate('effective_start_date', '2027-01-01')->count())->toBe(41);

    // And it resolves to the same numbers, since the file is the same tariff.
    expect(Pph21Ter::monthlyRate('A', 10_000_000, '2027-01-01'))->toBe(0.02);
    expect(Pph21Ter::monthlyRate('C', 1_500_000_000, '2027-01-01'))->toBe(0.34);
    expect(Pph21Ter::category('K/3', '2027-01-01'))->toBe('C');
});

it('rejects a file that is not a workbook', function (): void {
    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.import'), [
            'file' => UploadedFile::fake()->createWithContent('tarif.xlsx', 'bukan zip'),
            'effective_start_date' => '2027-01-01',
        ])
        ->assertSessionHasErrors('file');

    expect(Pph21TerRate::whereDate('effective_start_date', '2027-01-01')->count())->toBe(0);
});

it('restores the statutory tariff on demand', function (): void {
    Pph21TerRate::where('category', 'A')->delete();
    Pph21Ter::forget();

    actingAs($this->superAdmin)
        ->post(route('avana.payroll.ter.reset'), ['effective_start_date' => '2027-01-01'])
        ->assertSessionHas('success');

    Pph21Ter::forget();

    expect(Pph21TerRate::where('category', 'A')->whereDate('effective_start_date', '2027-01-01')->count())->toBe(42);
    expect(Pph21Ter::monthlyRate('A', 10_000_000, '2027-01-01'))->toBe(0.02);
});

it('corrects a single bracket without republishing the table', function (): void {
    $bracket = Pph21TerRate::where('category', 'A')
        ->whereDate('effective_start_date', '2024-01-01')
        ->orderBy('income_max')
        ->skip(1)
        ->first();

    actingAs($this->superAdmin)
        ->put(route('avana.payroll.ter.bracket.update', $bracket), [
            'income_min' => (float) $bracket->income_min,
            'income_max' => (float) $bracket->income_max,
            'rate' => 0.003,
        ])
        ->assertSessionHas('success');

    expect((float) $bracket->fresh()->rate)->toBe(0.003);
});

it('refuses a rate typed as a percentage instead of a decimal', function (): void {
    $bracket = Pph21TerRate::where('category', 'A')->orderBy('income_max')->first();

    actingAs($this->superAdmin)
        ->put(route('avana.payroll.ter.bracket.update', $bracket), [
            'income_min' => 0,
            'income_max' => 5_400_000,
            'rate' => 25,
        ])
        ->assertSessionHasErrors('rate');
});

<?php

use App\Support\Pph21Ter;
use App\Support\Pph21TerTableValidator;

/**
 * @return list<array{income_min: float, income_max: float|null, rate: float}>
 */
function bracketSet(array ...$rows): array
{
    return array_map(
        static fn (array $row): array => [
            'income_min' => (float) $row[0],
            'income_max' => $row[1] === null ? null : (float) $row[1],
            'rate' => (float) $row[2],
        ],
        $rows,
    );
}

/**
 * A workbook that passes: three monthly categories, the daily table, and all
 * eight PTKP statuses mapped.
 *
 * @return array{0: array<string, list<array{income_min: float, income_max: float|null, rate: float}>>, 1: array<string, string>}
 */
function validWorkbook(): array
{
    $monthly = bracketSet([0, 10_000_000, 0.0], [10_000_000, null, 0.05]);

    return [
        [
            'A' => $monthly,
            'B' => $monthly,
            'C' => $monthly,
            'HARIAN' => bracketSet([0, 450_000, 0.0], [450_000, 2_500_000, 0.005]),
        ],
        Pph21Ter::statutoryCategoryMap(),
    ];
}

it('accepts a complete workbook', function (): void {
    [$tables, $map] = validWorkbook();

    Pph21TerTableValidator::validate($tables, $map);
})->throwsNoExceptions();

it('rejects a workbook missing a category', function (): void {
    [$tables, $map] = validWorkbook();
    unset($tables['C']);

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class, 'Workbook belum lengkap');
});

it('rejects a workbook with no daily table', function (): void {
    [$tables, $map] = validWorkbook();
    unset($tables['HARIAN']);

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class, 'HARIAN');
});

it('rejects a partial PTKP mapping', function (): void {
    [$tables, $map] = validWorkbook();
    unset($map['K/3']);

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class, 'K/3');
});

it('rejects a PTKP status mapped to a category that is not A, B or C', function (): void {
    [$tables, $map] = validWorkbook();
    $map['K/3'] = 'HARIAN';

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class, 'harus A, B, atau C');
});

it('rejects a negative rate and a rate above 100%', function (): void {
    [, $map] = validWorkbook();

    $negative = validWorkbook()[0];
    $negative['A'] = bracketSet([0, 10_000_000, -0.01], [10_000_000, null, 0.05]);

    expect(fn () => Pph21TerTableValidator::validate($negative, $map))
        ->toThrow(RuntimeException::class, 'di luar rentang');

    $tooHigh = validWorkbook()[0];
    $tooHigh['A'] = bracketSet([0, 10_000_000, 0.0], [10_000_000, null, 1.5]);

    expect(fn () => Pph21TerTableValidator::validate($tooHigh, $map))
        ->toThrow(RuntimeException::class, 'di luar rentang');
});

it('rejects an upper bound that is not above the lower bound', function (): void {
    [$tables, $map] = validWorkbook();
    $tables['A'] = bracketSet([0, 0, 0.0], [0, null, 0.05]);

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class);
});

it('rejects a gap between two brackets', function (): void {
    [$tables, $map] = validWorkbook();
    $tables['A'] = bracketSet([0, 10_000_000, 0.0], [12_000_000, null, 0.05]);

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class, 'celah atau tumpang tindih');
});

it('rejects an overlap between two brackets', function (): void {
    [$tables, $map] = validWorkbook();
    $tables['A'] = bracketSet([0, 10_000_000, 0.0], [8_000_000, null, 0.05]);

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class);
});

it('rejects a duplicated bracket', function (): void {
    [$tables, $map] = validWorkbook();
    $tables['A'] = bracketSet([0, 10_000_000, 0.0], [10_000_000, 10_000_000.0, 0.02], [10_000_000, null, 0.05]);

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class);
});

it('rejects a monthly table with no open-ended top bracket', function (): void {
    [$tables, $map] = validWorkbook();
    $tables['A'] = bracketSet([0, 10_000_000, 0.0], [10_000_000, 50_000_000, 0.05]);

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class, 'tepat satu bracket tanpa batas');
});

it('rejects a daily table that runs to infinity', function (): void {
    [$tables, $map] = validWorkbook();
    $tables['HARIAN'] = bracketSet([0, 450_000, 0.0], [450_000, null, 0.005]);

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class, 'Pasal 17');
});

it('rejects a category whose first bracket does not start at zero', function (): void {
    [$tables, $map] = validWorkbook();
    $tables['A'] = bracketSet([1_000_000, 10_000_000, 0.0], [10_000_000, null, 0.05]);

    expect(fn () => Pph21TerTableValidator::validate($tables, $map))
        ->toThrow(RuntimeException::class, 'harus mulai dari 0');
});

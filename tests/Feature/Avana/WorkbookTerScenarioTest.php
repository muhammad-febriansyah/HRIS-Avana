<?php

use App\Support\Pph21Ter;

/**
 * The client's own workbook (Skenario_Payroll_Indonesia_1.xlsx, tab "Skenario
 * Payroll") is the acceptance test for the TER engine: eight employees spanning
 * every PTKP status and all three categories, each with the rate and the rupiah
 * the HR desk expects to see. If a bracket table is ever re-imported wrong,
 * this fails before a payslip does.
 *
 * Bruto Total (dasar TER) = fixed pay + allowances + incentive/bonus, which is
 * the column the workbook feeds into the rate lookup.
 */
it('withholds exactly what the payroll workbook does', function (
    string $ptkp,
    int $brutoTotal,
    string $category,
    float $rate,
    int $pph21,
): void {
    $on = '2026-08-31';

    expect(Pph21Ter::categoryOrFail($ptkp, $on))->toBe($category);

    $resolved = Pph21Ter::monthlyRate($category, $brutoTotal, $on);

    expect($resolved)->toBe($rate)
        ->and(round($brutoTotal * $resolved))->toBe((float) $pph21);
})->with([
    // [PTKP, bruto total, kategori, tarif, PPh 21]
    'Andi — TK/0 di bawah ambang' => ['TK/0', 5_400_000, 'A', 0.0, 0],
    'Siti — K/0' => ['K/0', 7_000_000, 'A', 0.0125, 87_500],
    'Budi — K/1 dengan insentif' => ['K/1', 12_800_000, 'B', 0.0225, 288_000],
    'Dewi — TK/1 dengan insentif' => ['TK/1', 16_000_000, 'A', 0.04, 640_000],
    'Rian — K/2' => ['K/2', 22_100_000, 'B', 0.035, 773_500],
    'Maya — TK/2 dengan bonus' => ['TK/2', 35_600_000, 'B', 0.0475, 1_691_000],
    'Farhan — K/3' => ['K/3', 43_100_000, 'C', 0.055, 2_370_500],
    'Raffa — K/3 dengan bonus besar' => ['K/3', 71_600_000, 'C', 0.0675, 4_833_000],
]);

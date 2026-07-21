<?php

use App\Support\Pph21Calculator;
use App\Support\Pph21Ter;

/**
 * UU HPP Pasal 17 progressive tariff, used as the injected resolver so the
 * calculator can be tested without the payroll engine.
 */
function pasal17(float $base): float
{
    $brackets = [
        [60_000_000.0, 0.05],
        [250_000_000.0, 0.15],
        [500_000_000.0, 0.25],
        [5_000_000_000.0, 0.30],
        [null, 0.35],
    ];

    $tax = 0.0;
    $lower = 0.0;
    foreach ($brackets as [$upper, $rate]) {
        if ($base <= $lower) {
            break;
        }
        $ceiling = $upper ?? $base;
        $slice = min($base, $ceiling) - $lower;
        if ($slice > 0) {
            $tax += $slice * $rate;
        }
        $lower = $ceiling;
    }

    return round($tax);
}

function pph(string $subject, ?string $ptkp, float $gross, array $opts = []): array
{
    return Pph21Calculator::compute($subject, $ptkp, $gross, $opts, fn (float $b): float => pasal17($b));
}

it('taxes a pegawai tetap via monthly TER (matches the official calculator)', function (): void {
    // K/0 → Kategori A; Rp12jt → 3,25% → Rp390.000 (Setup_TER_PPh21 calculator row).
    $r = pph('pegawai_tetap', 'K/0', 12_000_000);

    expect($r['method'])->toBe('ter_bulanan')
        ->and($r['ter_category'])->toBe('A')
        ->and($r['ter_rate'])->toBe(0.0325)
        ->and($r['amount'])->toBe(390000.0);
});

it('taxes PNS the same monthly TER way and flags annual reconciliation', function (): void {
    $r = pph('pns', 'K/0', 12_000_000);

    expect($r['method'])->toBe('ter_bulanan')->and($r['amount'])->toBe(390000.0);
    expect(Pph21Calculator::needsAnnualReconciliation('pns'))->toBeTrue();
});

it('taxes a komisaris via monthly TER without annual reconciliation', function (): void {
    // TK/0 → A; Rp20jt → 4,5%.
    $r = pph('komisaris', 'TK/0', 20_000_000);

    expect($r['method'])->toBe('ter_bulanan')
        ->and($r['ter_rate'])->toBe(0.045)
        ->and($r['amount'])->toBe(900000.0);
    expect(Pph21Calculator::needsAnnualReconciliation('komisaris'))->toBeFalse();
});

it('taxes a daily worker under Rp2,5jt/day via TER Harian', function (): void {
    // Daily Rp1jt → 0,5% (>450rb ≤2,5jt); monthly gross Rp20jt.
    $r = pph('pegawai_tidak_tetap', 'TK/0', 20_000_000, ['wage_basis' => 'daily', 'daily_wage' => 1_000_000]);

    expect($r['method'])->toBe('ter_harian')
        ->and($r['ter_rate'])->toBe(0.005)
        ->and($r['amount'])->toBe(100000.0);
});

it('exempts a daily worker at or below Rp450rb/day', function (): void {
    $r = pph('pegawai_tidak_tetap', 'TK/0', 8_000_000, ['wage_basis' => 'daily', 'daily_wage' => 400_000]);

    expect($r['method'])->toBe('ter_harian')
        ->and($r['ter_rate'])->toBe(0.0)
        ->and($r['amount'])->toBe(0.0);
});

it('taxes a daily worker over Rp2,5jt/day at 50% x Pasal 17', function (): void {
    // gross Rp60jt → base 50% = Rp30jt → 5% = Rp1,5jt.
    $r = pph('pegawai_tidak_tetap', 'TK/0', 60_000_000, ['wage_basis' => 'daily', 'daily_wage' => 3_000_000]);

    expect($r['method'])->toBe('50pct_pasal17')
        ->and($r['base'])->toBe(30000000.0)
        ->and($r['amount'])->toBe(1500000.0);
});

it('taxes a monthly-paid pegawai tidak tetap via monthly TER', function (): void {
    // TK/0 → A; Rp10jt → 2%.
    $r = pph('pegawai_tidak_tetap', 'TK/0', 10_000_000, ['wage_basis' => 'monthly']);

    expect($r['method'])->toBe('ter_bulanan')->and($r['amount'])->toBe(200000.0);
});

it('taxes bukan pegawai at 50% x Pasal 17', function (): void {
    // gross Rp20jt → base Rp10jt → 5% = Rp500rb.
    $r = pph('bukan_pegawai', null, 20_000_000);

    expect($r['method'])->toBe('50pct_pasal17')
        ->and($r['base'])->toBe(10000000.0)
        ->and($r['amount'])->toBe(500000.0);
});

it('taxes peserta kegiatan on full gross at Pasal 17', function (): void {
    $r = pph('peserta_kegiatan', null, 20_000_000);

    expect($r['method'])->toBe('pasal17')->and($r['amount'])->toBe(1000000.0);
});

it('taxes peserta pensiun across two Pasal 17 layers', function (): void {
    // Rp100jt → 60jt x 5% + 40jt x 15% = 3jt + 6jt = 9jt.
    $r = pph('peserta_pensiun', null, 100_000_000);

    expect($r['method'])->toBe('pasal17')->and($r['amount'])->toBe(9000000.0);
});

it('taxes mantan pegawai on full gross at Pasal 17', function (): void {
    $r = pph('mantan_pegawai', null, 50_000_000);

    expect($r['method'])->toBe('pasal17')->and($r['amount'])->toBe(2500000.0);
});

it('falls back to pegawai tetap for an unknown subject', function (): void {
    $r = pph('freelancer_random', 'K/0', 12_000_000);

    expect($r['subject'])->toBe('pegawai_tetap')->and($r['method'])->toBe('ter_bulanan');
});

it('resolves the TER Harian daily rate brackets', function (): void {
    expect(Pph21Ter::dailyRate(450_000))->toBe(0.0)
        ->and(Pph21Ter::dailyRate(450_001))->toBe(0.005)
        ->and(Pph21Ter::dailyRate(2_500_000))->toBe(0.005);
});

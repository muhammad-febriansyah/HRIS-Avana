<?php

use App\Support\Pph21Ter;

it('maps PTKP status to the correct TER category', function (?string $status, string $expected) {
    expect(Pph21Ter::category($status))->toBe($expected);
})->with([
    'null defaults to A' => [null, 'A'],
    'TK/0 -> A' => ['TK/0', 'A'],
    'TK/1 -> A' => ['TK/1', 'A'],
    'K/0 -> A' => ['K/0', 'A'],
    'TK/2 -> B' => ['TK/2', 'B'],
    'TK/3 -> B' => ['TK/3', 'B'],
    'K/1 -> B' => ['K/1', 'B'],
    'K/2 -> B' => ['K/2', 'B'],
    'K/3 -> C' => ['K/3', 'C'],
    'lowercase + spaces still map' => ['k / 3', 'C'],
]);

it('matches the DJP worked examples for Kategori A', function (float $gross, float $rate) {
    expect(Pph21Ter::monthlyRate('A', $gross))->toBe($rate);
})->with([
    'below floor is 0%' => [5_000_000.0, 0.0],
    'exactly at 5.4jt floor is 0%' => [5_400_000.0, 0.0],
    'DJP example Rp6.5jt -> 1%' => [6_500_000.0, 0.01],
    'DJP example Rp10jt -> 2%' => [10_000_000.0, 0.02],
    'Rp13.59jt -> 3.5%' => [13_590_000.0, 0.035],
    'Rp16.95jt boundary -> 4%' => [16_950_000.0, 0.04],
]);

it('applies the correct Kategori B and C floors', function () {
    // B floor = 6.2jt, C floor = 6.6jt.
    expect(Pph21Ter::monthlyRate('B', 6_200_000))->toBe(0.0);
    expect(Pph21Ter::monthlyRate('B', 6_300_000))->toBe(0.0025);
    expect(Pph21Ter::monthlyRate('C', 6_600_000))->toBe(0.0);
    expect(Pph21Ter::monthlyRate('C', 6_700_000))->toBe(0.0025);
});

it('computes the flat monthly withholding as rate x gross', function () {
    // Kategori A, gross 6.5jt, rate 1% => 65.000.
    $gross = 6_500_000.0;
    $tax = round($gross * Pph21Ter::monthlyRate('A', $gross));
    expect($tax)->toBe(65_000.0);
});

it('returns the top 34% rate for the highest bracket', function () {
    expect(Pph21Ter::monthlyRate('A', 2_000_000_000))->toBe(0.34);
    expect(Pph21Ter::monthlyRate('B', 2_000_000_000))->toBe(0.34);
    expect(Pph21Ter::monthlyRate('C', 2_000_000_000))->toBe(0.34);
});

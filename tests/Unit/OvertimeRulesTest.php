<?php

use App\Support\OvertimeRules;

$rates = OvertimeRules::statutoryRates();

it('pays the statutory workday bands', function (float $hours, float $expected) use ($rates) {
    expect(OvertimeRules::multiplierFor($rates, 'workday', $hours))->toBe($expected);
})->with([
    // 1,5x for the first hour, 2x for every hour after it.
    'first hour only' => [1.0, 1.5],
    'half an hour' => [0.5, 0.75],
    'two hours' => [2.0, 3.5],
    'three hours' => [3.0, 5.5],
    'one and a half' => [1.5, 2.5],
    'four hours' => [4.0, 7.5],
]);

it('pays the statutory holiday bands', function (float $hours, float $expected) use ($rates) {
    expect(OvertimeRules::multiplierFor($rates, 'holiday', $hours))->toBe($expected);
})->with([
    // 2x for hours 1–7, 3x for hour 8, 4x from hour 9.
    'one hour' => [1.0, 2.0],
    'seven hours' => [7.0, 14.0],
    'eight hours' => [8.0, 17.0],
    'nine hours' => [9.0, 21.0],
]);

it('treats an unknown day type as a workday rather than paying nothing', function () use ($rates) {
    expect(OvertimeRules::multiplierFor($rates, 'nonsense', 2.0))->toBe(3.5);
    expect(OvertimeRules::normaliseDayType('nonsense'))->toBe('workday');
    expect(OvertimeRules::normaliseDayType(null))->toBe('workday');
    expect(OvertimeRules::normaliseDayType('holiday'))->toBe('holiday');
});

it('pays nothing for a zero or negative stretch', function () use ($rates) {
    expect(OvertimeRules::multiplierFor($rates, 'workday', 0.0))->toBe(0.0);
    expect(OvertimeRules::multiplierFor($rates, 'workday', -3.0))->toBe(0.0);
});

it('keeps the fixed basis when it clears the 75% floor', function () {
    // 12jt fixed out of 15jt earnings = 80%, above the floor.
    $result = OvertimeRules::basisFor(12_000_000, 15_000_000, 0.75);

    expect($result['basis'])->toBe(12_000_000.0);
    expect($result['floored'])->toBeFalse();
});

it('raises the basis to 75% of earnings when the fixed part falls short', function () {
    // 6jt fixed out of 20jt earnings = 30%; PP 35/2021 Pasal 30 lifts it to 15jt.
    $result = OvertimeRules::basisFor(6_000_000, 20_000_000, 0.75);

    expect($result['basis'])->toBe(15_000_000.0);
    expect($result['floored'])->toBeTrue();
});

it('falls back to the floor when no component is marked as basis', function () {
    $result = OvertimeRules::basisFor(0.0, 10_000_000, 0.75);

    expect($result['basis'])->toBe(7_500_000.0);
    expect($result['floored'])->toBeTrue();
});

it('reproduces the worked example of the setup documentation', function () use ($rates) {
    // Basis 12.350.000 (pokok 10jt + transport 500rb + jabatan 1,5jt +
    // kesehatan 350rb), 3 hours on a workday.
    $hourly = 12_350_000 / 173;

    expect(round($hourly))->toBe(71_387.0);
    expect(round($hourly * OvertimeRules::multiplierFor($rates, 'workday', 3.0)))->toBe(392_630.0);
});

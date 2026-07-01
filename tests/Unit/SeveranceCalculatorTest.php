<?php

use App\Services\SeveranceCalculator;

beforeEach(function (): void {
    $this->calc = new SeveranceCalculator;
});

it('maps completed years to statutory severance-pay months', function (float $years, int $months): void {
    expect($this->calc->severanceMonths($years))->toBe($months);
})->with([
    [0.5, 1], [1.0, 2], [2.5, 3], [7.9, 8], [8.0, 9], [20.0, 9],
]);

it('maps completed years to statutory long-service months', function (float $years, int $months): void {
    expect($this->calc->longServiceMonths($years))->toBe($months);
})->with([
    [2.0, 0], [3.0, 2], [8.9, 3], [11.0, 4], [23.0, 8], [24.0, 10],
]);

it('computes a standard PHK settlement (1x UP + 1x UPMK)', function (): void {
    // 5 yr → UP 6 months, UPMK 2 months; wage 5.000.000.
    $result = $this->calc->calculate(5_000_000, 5.0, 'phk_biasa');

    expect($result['up_amount'])->toBe(30_000_000.0);
    expect($result['upmk_amount'])->toBe(10_000_000.0);
    expect($result['total'])->toBe(40_000_000.0);
});

it('doubles UP for death and adds UPH', function (): void {
    // 5 yr → UP 6 months x2 = 60.000.000; UPMK 2 months x1 = 10.000.000; UPH 1.000.000.
    $result = $this->calc->calculate(5_000_000, 5.0, 'meninggal', 1_000_000);

    expect($result['up_amount'])->toBe(60_000_000.0);
    expect($result['upmk_amount'])->toBe(10_000_000.0);
    expect($result['uph'])->toBe(1_000_000.0);
    expect($result['total'])->toBe(71_000_000.0);
});

it('pays only separation pay and UPH on resign (no statutory severance)', function (): void {
    $result = $this->calc->calculate(5_000_000, 5.0, 'resign', 500_000, 2_000_000);

    expect($result['up_amount'])->toBe(0.0);
    expect($result['upmk_amount'])->toBe(0.0);
    expect($result['separation_pay'])->toBe(2_000_000.0);
    expect($result['total'])->toBe(2_500_000.0);
});

it('applies the 1.75x UP multiplier for retirement', function (): void {
    // 8 yr → UP 9 months x1.75 = 78.750.000; UPMK 3 months x1 = 15.000.000.
    $result = $this->calc->calculate(5_000_000, 8.0, 'pensiun');

    expect($result['up_amount'])->toBe(78_750_000.0);
    expect($result['upmk_amount'])->toBe(15_000_000.0);
    expect($result['total'])->toBe(93_750_000.0);
});

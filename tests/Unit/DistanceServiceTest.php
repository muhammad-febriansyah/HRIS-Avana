<?php

use App\Services\DistanceService;

it('calculates haversine distance in meters', function (): void {
    $distance = app(DistanceService::class)->meters(
        -6.2146000,
        106.8451000,
        -6.2155000,
        106.8451000,
    );

    expect($distance)->toBeGreaterThan(99)->toBeLessThan(102);
});

it('returns zero for an unchanged coordinate', function (): void {
    expect(app(DistanceService::class)->meters(-6.2, 106.8, -6.2, 106.8))->toBe(0.0);
});

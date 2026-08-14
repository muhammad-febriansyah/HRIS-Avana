<?php

namespace App\Services;

final class DistanceService
{
    private const EARTH_RADIUS_METERS = 6371000;

    public function meters(
        float $fromLatitude,
        float $fromLongitude,
        float $toLatitude,
        float $toLongitude,
    ): float {
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $fromLatitudeRadians = deg2rad($fromLatitude);
        $toLatitudeRadians = deg2rad($toLatitude);

        $haversine = sin($latitudeDelta / 2) ** 2
            + cos($fromLatitudeRadians) * cos($toLatitudeRadians) * sin($longitudeDelta / 2) ** 2;

        return self::EARTH_RADIUS_METERS * 2 * atan2(sqrt($haversine), sqrt(1 - $haversine));
    }
}

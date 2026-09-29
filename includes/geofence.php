<?php

declare(strict_types=1);

const MAX_GPS_ACCURACY_METERS = 200.0;

function validGpsCoordinates(float $latitude, float $longitude): bool
{
    return is_finite($latitude)
        && is_finite($longitude)
        && $latitude >= -90.0
        && $latitude <= 90.0
        && $longitude >= -180.0
        && $longitude <= 180.0;
}

function gpsDistanceMeters(float $latitude1, float $longitude1, float $latitude2, float $longitude2): float
{
    $earthRadius = 6371000.0;
    $lat1 = deg2rad($latitude1);
    $lat2 = deg2rad($latitude2);
    $deltaLat = deg2rad($latitude2 - $latitude1);
    $deltaLongitude = deg2rad($longitude2 - $longitude1);
    $a = sin($deltaLat / 2) ** 2
        + cos($lat1) * cos($lat2) * sin($deltaLongitude / 2) ** 2;
    $a = min(1.0, max(0.0, $a));
    return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
}

function validateStudentGps(float $latitude, float $longitude, float $accuracy): ?string
{
    if (!validGpsCoordinates($latitude, $longitude)) {
        return 'The GPS coordinates are invalid. Please try again.';
    }
    if (!is_finite($accuracy) || $accuracy <= 0) {
        return 'GPS accuracy could not be determined. Please try again.';
    }
    if ($accuracy > MAX_GPS_ACCURACY_METERS) {
        return 'GPS accuracy is too low (' . round($accuracy) . 'm). Move to an open area and try again.';
    }
    return null;
}

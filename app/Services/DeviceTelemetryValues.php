<?php

namespace App\Services;

/** Shared display values without changing the device's recorded telemetry. */
class DeviceTelemetryValues
{
    public static function celsiusToFahrenheit($value): ?float
    {
        if (!is_numeric($value) || !is_finite((float) $value)) {
            return null;
        }

        $fahrenheit = (float) $value * 9 / 5 + 32;

        return is_finite($fahrenheit) ? round($fahrenheit, 4) : null;
    }

    public static function ctsState($value): ?string
    {
        if ($value === 0 || $value === 0.0 || $value === '0') {
            return 'Open';
        }
        if ($value === 1 || $value === 1.0 || $value === '1') {
            return 'Closed';
        }

        return null;
    }
}

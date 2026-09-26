<?php

namespace Tests\Unit;

use App\Services\DeviceTelemetryValues;
use PHPUnit\Framework\TestCase;

class DeviceTelemetryValuesTest extends TestCase
{
    public function test_temperatures_are_converted_without_inventing_missing_measurements(): void
    {
        foreach ([[0, 32.0], ['0', 32.0], [100, 212.0], [-40, -40.0], ['26.1135', 79.0043]] as [$input, $expected]) {
            $this->assertSame($expected, DeviceTelemetryValues::celsiusToFahrenheit($input));
        }
        foreach ([null, '', ' ', false, true, [], 'invalid', NAN, INF, -INF, PHP_FLOAT_MAX] as $input) {
            $this->assertNull(DeviceTelemetryValues::celsiusToFahrenheit($input));
        }
    }

    public function test_only_known_cts_values_receive_a_state_label(): void
    {
        foreach ([0, 0.0, '0'] as $input) {
            $this->assertSame('Open', DeviceTelemetryValues::ctsState($input));
        }
        foreach ([1, 1.0, '1'] as $input) {
            $this->assertSame('Closed', DeviceTelemetryValues::ctsState($input));
        }
        foreach ([null, '', ' ', false, true, [], 2, '2', 'invalid'] as $input) {
            $this->assertNull(DeviceTelemetryValues::ctsState($input));
        }
    }
}
